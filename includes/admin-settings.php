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
        // Salva as configurações
        update_option('custom_discount_included_categories', isset($_POST['included_categories']) ? $_POST['included_categories'] : array());
        update_option('custom_discount_min_items', isset($_POST['custom_discount_min_items']) ? intval($_POST['custom_discount_min_items']) : 6);
        update_option('custom_discount_percentage', isset($_POST['custom_discount_percentage']) ? floatval($_POST['custom_discount_percentage']) : 10);
        update_option('custom_discount_max', isset($_POST['custom_discount_max']) ? floatval($_POST['custom_discount_max']) : 0);
        update_option('custom_discount_toast_position_h', isset($_POST['custom_discount_toast_position_h']) ? sanitize_text_field($_POST['custom_discount_toast_position_h']) : 'right');
        update_option('custom_discount_toast_position_v', isset($_POST['custom_discount_toast_position_v']) ? sanitize_text_field($_POST['custom_discount_toast_position_v']) : 'bottom');
        update_option('custom_discount_messages', array(
            'has_discount' => sanitize_text_field($_POST['message_has_discount']),
            'no_discount' => sanitize_text_field($_POST['message_no_discount']),
            'kit_discount' => sanitize_text_field($_POST['message_kit_discount']),
            'kit_no_cart_match' => sanitize_text_field($_POST['message_kit_no_cart_match']),
            'kit_complete' => sanitize_text_field($_POST['message_kit_complete']),
            'toast_notification' => sanitize_text_field($_POST['message_toast_notification'])
        ));
        echo '<div class="updated"><p>Configurações salvas com sucesso!</p></div>';
    }

    // Recupera as configurações salvas
    $included_categories = get_option('custom_discount_included_categories', array());
    $min_items = get_option('custom_discount_min_items', 6);
    $discount_percentage = get_option('custom_discount_percentage', 10);
    $max_discount = get_option('custom_discount_max', 0);
    $toast_position_h = get_option('custom_discount_toast_position_h', 'right');
    $toast_position_v = get_option('custom_discount_toast_position_v', 'bottom');
    $messages = get_option('custom_discount_messages', array(
        'has_discount' => 'Parabéns! Você já tem direito a {discount}% de desconto no carrinho! Compras mínimas de {level_quantity} itens.',
        'no_discount' => 'Adicione {remaining} produtos ao carrinho para ganhar {next_discount}% de desconto e economizar R$ {savings}! Compras mínimas de {level_quantity} itens.',
        'kit_discount' => 'Este kit já tem um desconto especial de {discount}%, aproveite!',
        'kit_no_cart_match' => 'Você tem {cart_quantity} de {total_quantity} produtos deste kit no carrinho.',
        'kit_complete' => 'Parabéns! Você tem {cart_quantity} produtos de {total_quantity} deste kit no carrinho e ganhou o desconto de {discount}%.',
        'toast_notification' => '<strong>Parabéns!</strong><br>Você atingiu {discount}% de desconto!<br>Adicione mais {remaining} produtos para {next_discount}%'
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
    </style>

    <div class="wrap">
        <h2><?php _e('Configurações de Desconto', 'desconto-automatico'); ?></h2>

        <h2 class="nav-tab-wrapper">
            <a href="#tab-levels" class="nav-tab nav-tab-active"><?php _e('Configurações de Desconto', 'desconto-automatico'); ?></a>
            <a href="#tab-messages" class="nav-tab"><?php _e('Mensagens Personalizadas', 'desconto-automatico'); ?></a>
            <a href="#tab-categories" class="nav-tab"><?php _e('Categorias Incluídas', 'desconto-automatico'); ?></a>
            <a href="#tab-toast" class="nav-tab"><?php _e('Notificação Toast', 'desconto-automatico'); ?></a>
        </h2>

        <form method="post" id="discount-settings-form">
            <?php wp_nonce_field('custom_discount_settings'); ?>

            <div id="tab-levels" class="tab-content active">
                <h3>Configurações de Desconto</h3>
                <table class="form-table">
                    <tr>
                        <th>Quantidade Mínima de Produtos</th>
                        <td>
                            <input type="number" name="custom_discount_min_items" value="<?php echo esc_attr($min_items); ?>" min="1" />
                            <p class="description">Quantidade mínima de produtos para aplicar o desconto</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Porcentagem de Desconto</th>
                        <td>
                            <input type="number" name="custom_discount_percentage" value="<?php echo esc_attr($discount_percentage); ?>" min="0" max="100" step="0.1" />
                            <p class="description">Porcentagem de desconto a ser aplicada</p>
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
                        <li><code>{discount}</code> - Porcentagem de desconto atual (calculada com base na quantidade de itens no carrinho)</li>
                        <li><code>{admin_discount}</code> - Porcentagem de desconto configurada no painel administrativo (sempre mostra o valor configurado)</li>
                        <li><code>{remaining}</code> - Quantidade de produtos que faltam para atingir o desconto</li>
                        <li><code>{savings}</code> - Valor da economia em reais</li>
                        <li><code>{cart_quantity}</code> - Quantidade de produtos no carrinho</li>
                        <li><code>{total_quantity}</code> - Quantidade total de produtos no kit</li>
                        <li><code>{level_quantity}</code> - Quantidade mínima de produtos para obter o desconto</li>
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
                            <p class="description">Esta mensagem é exibida quando o cliente já tem produtos suficientes no carrinho para receber o desconto. Aparece na página do produto individual e no carrinho.</p>
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
                            <p class="description">Esta mensagem é exibida quando o cliente ainda não tem produtos suficientes no carrinho para receber o desconto. Aparece na página do produto individual e no carrinho.</p>
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
                            <p class="description">Esta mensagem é exibida na página do produto kit quando não há produtos deste kit no carrinho.</p>
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
                            <p class="description">Esta mensagem é exibida na página do produto kit quando o cliente tem alguns produtos deste kit no carrinho, mas não a quantidade completa.</p>
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
                            <p class="description">Esta mensagem é exibida na página do produto kit quando o cliente tem a quantidade completa de produtos deste kit no carrinho.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Mensagem de notificação de desconto</th>
                        <td>
                            <?php
                            wp_editor(
                                $messages['toast_notification'],
                                'message_toast_notification',
                                array('textarea_rows' => 3)
                            );
                            ?>
                            <p class="description">Esta mensagem é exibida como uma notificação de desconto.</p>
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

            <div id="tab-toast" class="tab-content">
                <h3>Notificação Toast</h3>
                <table class="form-table">
                    <tr>
                        <th>Posição Horizontal da Notificação</th>
                        <td>
                            <select name="custom_discount_toast_position_h">
                                <option value="left" <?php selected($toast_position_h, 'left'); ?>><?php _e('Esquerda', 'desconto-automatico'); ?></option>
                                <option value="right" <?php selected($toast_position_h, 'right'); ?>><?php _e('Direita', 'desconto-automatico'); ?></option>
                            </select>
                            <p class="description">Selecione a posição horizontal da notificação toast.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Posição Vertical da Notificação</th>
                        <td>
                            <select name="custom_discount_toast_position_v">
                                <option value="top" <?php selected($toast_position_v, 'top'); ?>><?php _e('Topo', 'desconto-automatico'); ?></option>
                                <option value="bottom" <?php selected($toast_position_v, 'bottom'); ?>><?php _e('Fundo', 'desconto-automatico'); ?></option>
                            </select>
                            <p class="description">Selecione a posição vertical da notificação toast.</p>
                        </td>
                    </tr>
                </table>
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
    });
    </script>
    <?php
}
