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

    // Processa o formulário quando enviado
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['custom_discount_save'])) {
        check_admin_referer('custom_discount_settings');

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

            usort($discount_levels, function($a, $b) {
                return $a['quantity'] - $b['quantity'];
            });

            update_option('custom_discount_levels', $discount_levels);
        }

        // Valor máximo de desconto
        $max_discount = !empty($_POST['custom_discount_max']) ? floatval($_POST['custom_discount_max']) : 0;
        update_option('custom_discount_max', $max_discount);

        // Salva as categorias incluídas
        $included_categories = isset($_POST['included_categories']) ? array_map('sanitize_text_field', $_POST['included_categories']) : array();
        update_option('custom_discount_included_categories', $included_categories);

        // Salva as mensagens personalizadas
        $messages = array(
            'has_discount' => wp_kses_post($_POST['custom_discount_messages_has_discount']),
            'has_next_level' => wp_kses_post($_POST['custom_discount_messages_has_next_level']),
            'no_discount' => wp_kses_post($_POST['custom_discount_messages_no_discount'])
        );
        update_option('custom_discount_messages', $messages);

        echo '<div class="updated"><p>Configurações salvas com sucesso!</p></div>';
    }

    // Recupera as configurações salvas
    $discount_levels = get_option('custom_discount_levels', array(
        array('quantity' => 6, 'percentage' => 10),
        array('quantity' => 10, 'percentage' => 15)
    ));
    $max_discount = get_option('custom_discount_max', 0);
    $included_categories = get_option('custom_discount_included_categories', array());
    $messages = get_option('custom_discount_messages', array(
        'has_discount' => 'Parabéns! Você já tem direito a {discount}% de desconto no carrinho!',
        'has_next_level' => 'Adicione mais {remaining} produtos para aumentar seu desconto para {next_discount}% e economizar mais R$ {savings}!',
        'no_discount' => 'Adicione {remaining} produtos ao carrinho para ganhar {next_discount}% de desconto e economizar R$ {savings}!'
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
            background: #fff;
            padding: 20px;
            border: 1px solid #ccc;
            border-top: none;
        }
        .tab-content.active {
            display: block;
        }
        .discount-level {
            background: #f9f9f9;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .add-level-button {
            margin: 10px 0 20px;
        }
        .remove-level {
            color: #dc3232;
            cursor: pointer;
            margin-left: auto;
            padding: 5px 10px;
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
        .discount-level input[type="number"] {
            width: 100px;
        }
        .discount-level label {
            min-width: 150px;
            display: inline-block;
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
                <div id="discount-levels">
                    <?php foreach ($discount_levels as $index => $level): ?>
                    <div class="discount-level">
                        <label><?php _e('Quantidade de Produtos:', 'desconto-automatico'); ?></label>
                        <input type="number"
                               name="discount_level_quantity[]"
                               value="<?php echo esc_attr($level['quantity']); ?>"
                               min="1"
                               required />

                        <label><?php _e('Porcentagem de Desconto:', 'desconto-automatico'); ?></label>
                        <input type="number"
                               name="discount_level_percentage[]"
                               value="<?php echo esc_attr($level['percentage']); ?>"
                               min="0"
                               max="100"
                               step="0.1"
                               required />%

                        <span class="remove-level" title="Remover nível">×</span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="button add-level-button"><?php _e('Adicionar Nível', 'desconto-automatico'); ?></button>

                <table class="form-table">
                    <tr>
                        <th><?php _e('Valor Máximo de Desconto (R$)', 'desconto-automatico'); ?></th>
                        <td>
                            <input type="number" name="custom_discount_max" value="<?php echo esc_attr($max_discount); ?>" min="0" step="0.01" />
                            <p class="description"><?php _e('Digite 0 para não ter limite', 'desconto-automatico'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="tab-messages" class="tab-content">
                <div class="message-box">
                    <h3>Mensagem quando já tem desconto</h3>
                    <p class="description">Esta mensagem é exibida quando o cliente já atingiu um nível de desconto.</p>
                    <p class="variables-list">
                        Variáveis disponíveis:
                        <code>{discount}</code> - Porcentagem de desconto atual
                        <code>{current_items}</code> - Quantidade atual de itens no carrinho
                        <code>{savings}</code> - Valor em reais que está sendo economizado no momento
                    </p>
                    <?php
                    wp_editor(
                        $messages['has_discount'],
                        'custom_discount_messages_has_discount',
                        array('textarea_rows' => 3)
                    );
                    ?>
                </div>

                <div class="message-box">
                    <h3>Mensagem quando existe próximo nível</h3>
                    <p class="description">Esta mensagem é exibida quando o cliente já tem um desconto, mas pode aumentar para o próximo nível.</p>
                    <p class="variables-list">
                        Variáveis disponíveis:
                        <code>{remaining}</code> - Quantidade de produtos que faltam
                        <code>{next_discount}</code> - Porcentagem do próximo nível de desconto
                        <code>{level_quantity}</code> - Quantidade de produtos necessária para este nível
                        <code>{savings}</code> - Valor adicional em reais que será economizado
                        <code>{total_savings}</code> - Valor total em reais que será economizado
                        <code>{current_items}</code> - Quantidade atual de itens no carrinho
                    </p>
                    <?php
                    wp_editor(
                        $messages['has_next_level'],
                        'custom_discount_messages_has_next_level',
                        array('textarea_rows' => 3)
                    );
                    ?>
                </div>

                <div class="message-box">
                    <h3>Mensagem quando não tem desconto</h3>
                    <p class="description">Esta mensagem é exibida quando o cliente ainda não atingiu nenhum nível de desconto.</p>
                    <p class="variables-list">
                        Variáveis disponíveis:
                        <code>{remaining}</code> - Quantidade de produtos que faltam
                        <code>{next_discount}</code> - Porcentagem do próximo nível de desconto
                        <code>{level_quantity}</code> - Quantidade de produtos necessária para este nível
                        <code>{savings}</code> - Valor em reais que será economizado
                        <code>{current_items}</code> - Quantidade atual de itens no carrinho
                    </p>
                    <?php
                    wp_editor(
                        $messages['no_discount'],
                        'custom_discount_messages_no_discount',
                        array('textarea_rows' => 3)
                    );
                    ?>
                </div>
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
        // Sistema de abas
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var targetTab = $(this).attr('href');
            
            // Atualiza as abas
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Atualiza o conteúdo
            $('.tab-content').removeClass('active');
            $(targetTab).addClass('active');
        });

        // Template para novo nível de desconto
        function getNewLevelTemplate() {
            return `
                <div class="discount-level">
                    <label><?php _e('Quantidade de Produtos:', 'desconto-automatico'); ?></label>
                    <input type="number"
                           name="discount_level_quantity[]"
                           value=""
                           min="1"
                           required />

                    <label><?php _e('Porcentagem de Desconto:', 'desconto-automatico'); ?></label>
                    <input type="number"
                           name="discount_level_percentage[]"
                           value=""
                           min="0"
                           max="100"
                           step="0.1"
                           required />%

                    <span class="remove-level" title="Remover nível">×</span>
                </div>
            `;
        }

        // Adicionar novo nível
        $('.add-level-button').on('click', function() {
            $('#discount-levels').append(getNewLevelTemplate());
        });

        // Remover nível
        $(document).on('click', '.remove-level', function() {
            $(this).closest('.discount-level').remove();
        });

        // Validação do formulário
        $('#discount-settings-form').on('submit', function(e) {
            const levels = $('.discount-level');
            if (levels.length === 0) {
                e.preventDefault();
                alert('Adicione pelo menos um nível de desconto.');
                return false;
            }
            return true;
        });
    });
    </script>
    <?php
}
