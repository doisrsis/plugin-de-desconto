<?php
if (!defined('ABSPATH')) exit;

// Adiciona o menu no painel do WordPress
add_action('admin_menu', 'custom_discount_settings_menu');
function custom_discount_settings_menu() {
    add_menu_page(
        __('Configurações de Desconto', 'desconto-automatico'),
        __('Desconto Automático', 'desconto-automatico'),
        'manage_options',
        'custom-discount-settings',
        'custom_discount_settings_page',
        'dashicons-tickets',
        20
    );
}

// Página de configurações
function custom_discount_settings_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['custom_discount_save'])) {
        // Processa os níveis de desconto
        $discount_levels = array();
        if (isset($_POST['discount_level_quantity']) && isset($_POST['discount_level_percentage'])) {
            $quantities = $_POST['discount_level_quantity'];
            $percentages = $_POST['discount_level_percentage'];

            foreach ($quantities as $key => $quantity) {
                if (!empty($quantity) && isset($percentages[$key]) && $percentages[$key] !== '') {
                    $discount_levels[] = array(
                        'quantity' => intval($quantity),
                        'percentage' => floatval($percentages[$key])
                    );
                }
            }

            // Ordena os níveis por quantidade
            usort($discount_levels, function($a, $b) {
                return $a['quantity'] - $b['quantity'];
            });
        }

        // Salva as configurações
        update_option('custom_discount_included_categories', isset($_POST['included_categories']) ? $_POST['included_categories'] : array());
        update_option('custom_discount_levels', $discount_levels);
        update_option('custom_discount_max', isset($_POST['custom_discount_max']) ? floatval($_POST['custom_discount_max']) : 0);
        update_option('custom_discount_messages', array(
            'has_discount' => sanitize_text_field($_POST['message_has_discount']),
            'has_next_level' => sanitize_text_field($_POST['message_has_next_level']),
            'no_discount' => sanitize_text_field($_POST['message_no_discount']),
            'kit_discount' => sanitize_text_field($_POST['message_kit_discount']),
            'kit_no_cart_match' => sanitize_text_field($_POST['message_kit_no_cart_match']),
            'kit_complete' => sanitize_text_field($_POST['message_kit_complete'])
        ));
        echo '<div class="updated"><p>Configurações salvas com sucesso!</p></div>';
    }

    // Recupera as configurações salvas
    $included_categories = get_option('custom_discount_included_categories', array());
    $discount_levels = get_option('custom_discount_levels', array(
        array('quantity' => 6, 'percentage' => 10),
        array('quantity' => 10, 'percentage' => 15)
    ));
    $max_discount = get_option('custom_discount_max', 0);
    $messages = get_option('custom_discount_messages', array(
        'has_discount' => 'Parabéns! Você já tem direito a {discount}% de desconto no carrinho! Compras mínimas de {level_quantity} itens.',
        'has_next_level' => 'Adicione mais {remaining} produtos para aumentar seu desconto para {next_discount}% e economizar mais R$ {savings}! Compras mínimas de {level_quantity} itens.',
        'no_discount' => 'Adicione {remaining} produtos ao carrinho para ganhar {next_discount}% de desconto e economizar R$ {savings}! Compras mínimas de {level_quantity} itens.',
        'kit_discount' => 'Este kit já tem um desconto especial de {discount}%, aproveite!',
        'kit_no_cart_match' => 'Você tem {cart_quantity} de {total_quantity} produtos deste kit no carrinho.',
        'kit_complete' => 'Parabéns! Você tem {cart_quantity} produtos de {total_quantity} deste kit no carrinho e ganhou o desconto de {discount}%.'
    ));

    // Obtém todas as categorias do WooCommerce
    $product_categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ));

    // Adiciona o editor WYSIWYG
    wp_enqueue_editor();
    ?>
    <style>
        .nav-tab-wrapper {
            margin-bottom: 20px;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .discount-level {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .discount-level input[type="number"] {
            width: 100px;
        }
        .remove-level {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        #add-level {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        .variables-description {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .variables-description ul {
            margin: 10px 0;
            list-style-type: none;
        }
        .variables-description li {
            margin: 5px 0;
        }
        .variables-description code {
            background: #e9ecef;
            padding: 2px 5px;
            border-radius: 3px;
        }
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .category-item {
            background: #f9f9f9;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .message-variables {
            background: #f5f5f5;
            padding: 15px;
            margin: 0 0 20px;
            border-left: 4px solid #0073aa;
        }
        .message-variables code {
            background: #fff;
            padding: 2px 5px;
            border-radius: 3px;
            margin: 0 3px;
        }
        .message-box {
            background: #fff;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .message-box h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #0073aa;
            color: #23282d;
        }
        .message-box .wp-editor-wrap {
            margin-top: 15px;
        }
        .submit-wrapper {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-top: 1px solid #ddd;
            text-align: right;
        }
    </style>

    <div class="wrap">
        <h2><?php _e('Configurações de Desconto', 'desconto-automatico'); ?></h2>

        <h2 class="nav-tab-wrapper">
            <a href="#tab-levels" class="nav-tab nav-tab-active"><?php _e('Níveis de Desconto', 'desconto-automatico'); ?></a>
            <a href="#tab-messages" class="nav-tab"><?php _e('Mensagens Personalizadas', 'desconto-automatico'); ?></a>
            <a href="#tab-categories" class="nav-tab"><?php _e('Categorias Incluídas', 'desconto-automatico'); ?></a>
        </h2>

        <form method="post" id="discount-settings-form">
            <?php wp_nonce_field('custom_discount_settings'); ?>

            <div id="tab-levels" class="tab-content active">
                <h3>Níveis de Desconto</h3>
                <table class="form-table">
                    <tr>
                        <th>Níveis</th>
                        <td>
                            <div id="discount-levels">
                                <?php foreach ($discount_levels as $level): ?>
                                <div class="discount-level">
                                    <input type="number" name="discount_level_quantity[]" value="<?php echo esc_attr($level['quantity']); ?>" min="1" placeholder="Quantidade" />
                                    <input type="number" name="discount_level_percentage[]" value="<?php echo esc_attr($level['percentage']); ?>" min="0" max="100" step="0.1" placeholder="Porcentagem" />
                                    <button type="button" class="remove-level">Remover</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" id="add-level">Adicionar Nível</button>
                            <p class="description">Configure os níveis de desconto baseados na quantidade de produtos.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Valor Máximo de Desconto (R$)</th>
                        <td>
                            <input type="number" name="custom_discount_max" value="<?php echo esc_attr($max_discount); ?>" min="0" step="0.01" />
                            <p class="description">Digite 0 para não ter limite no valor do desconto</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="tab-messages" class="tab-content">
                <h3>Variáveis Disponíveis para Todas as Mensagens</h3>
                <div class="variables-description">
                    <p>Você pode usar qualquer uma dessas variáveis em qualquer mensagem:</p>
                    <ul>
                        <li><code>{discount}</code> - Porcentagem de desconto atual</li>
                        <li><code>{next_discount}</code> - Porcentagem do próximo nível de desconto</li>
                        <li><code>{remaining}</code> - Quantidade de produtos que faltam</li>
                        <li><code>{savings}</code> - Valor da economia em reais</li>
                        <li><code>{cart_quantity}</code> - Quantidade de produtos no carrinho</li>
                        <li><code>{total_quantity}</code> - Quantidade total de produtos no kit</li>
                        <li><code>{level_quantity}</code> - Quantidade mínima de produtos para o nível atual</li>
                    </ul>
                </div>

                <table class="form-table">
                    <tr>
                        <th>Mensagem quando tem desconto</th>
                        <td>
                            <?php
                            wp_editor(
                                $messages['has_discount'],
                                'message_has_discount',
                                array('textarea_rows' => 3)
                            );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Mensagem quando tem próximo nível</th>
                        <td>
                            <?php
                            wp_editor(
                                $messages['has_next_level'],
                                'message_has_next_level',
                                array('textarea_rows' => 3)
                            );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Mensagem quando não tem desconto</th>
                        <td>
                            <?php
                            wp_editor(
                                $messages['no_discount'],
                                'message_no_discount',
                                array('textarea_rows' => 3)
                            );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Mensagem para kits</th>
                        <td>
                            <?php
                            wp_editor(
                                $messages['kit_discount'],
                                'message_kit_discount',
                                array('textarea_rows' => 3)
                            );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Mensagem quando quantidade no carrinho é diferente</th>
                        <td>
                            <?php
                            wp_editor(
                                $messages['kit_no_cart_match'],
                                'message_kit_no_cart_match',
                                array('textarea_rows' => 3)
                            );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Mensagem quando kit está completo</th>
                        <td>
                            <?php
                            wp_editor(
                                $messages['kit_complete'],
                                'message_kit_complete',
                                array('textarea_rows' => 3)
                            );
                            ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="tab-categories" class="tab-content">
                <p class="description"><?php _e('Selecione as categorias que receberão desconto:', 'desconto-automatico'); ?></p>

                <div class="categories-grid">
                    <?php foreach ($product_categories as $category): ?>
                    <div class="category-item">
                        <label>
                            <input type="checkbox"
                                   name="included_categories[]"
                                   value="<?php echo esc_attr($category->slug); ?>"
                                   <?php checked(in_array($category->slug, $included_categories)); ?> />
                            <?php echo esc_html($category->name); ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="submit-wrapper">
                <input type="submit" name="custom_discount_save" class="button-primary" value="<?php _e('Salvar Configurações', 'desconto-automatico'); ?>" />
            </div>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Tabs
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.tab-content').removeClass('active');
            $($(this).attr('href')).addClass('active');
        });

        // Função para template do novo nível
        function getNewLevelTemplate() {
            return `
                <div class="discount-level">
                    <input type="number" name="discount_level_quantity[]" value="" min="1" placeholder="Quantidade" />
                    <input type="number" name="discount_level_percentage[]" value="" min="0" max="100" step="0.1" placeholder="Porcentagem" />
                    <button type="button" class="remove-level">Remover</button>
                </div>
            `;
        }

        // Adicionar novo nível
        $('#add-level').on('click', function() {
            $('#discount-levels').append(getNewLevelTemplate());
        });

        // Remover nível
        $(document).on('click', '.remove-level', function() {
            $(this).closest('.discount-level').remove();
        });
    });
    </script>
    <?php
}
