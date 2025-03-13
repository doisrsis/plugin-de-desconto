<?php
if (!defined('ABSPATH')) exit;

/**
 * Verifica se um produto está elegível para desconto
 */
function is_product_eligible_for_discount($product_id) {
    $product = wc_get_product($product_id);
    if (!$product) return false;

    // Se for um kit, não aplica desconto
    if (custom_discount_is_kit($product_id)) {
        return false;
    }

    // Obtém as categorias incluídas
    $included_categories = get_option('custom_discount_included_categories', array());
    
    // Se não houver categorias incluídas, nenhum produto recebe desconto
    if (empty($included_categories)) {
        return false;
    }

    // Obtém as categorias do produto
    $product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
    
    // Verifica se o produto tem pelo menos uma categoria incluída
    foreach ($product_categories as $category) {
        if (in_array($category, $included_categories)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Conta itens elegíveis no carrinho.
 *
 * @return int Número total de itens elegíveis para desconto.
 */
function get_eligible_items_count() {
    $count = 0;
    $cart = WC()->cart;

    if ($cart) {
        foreach ($cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];

            if (!is_product_eligible_for_discount($product_id)) {
                continue;
            }

            $count += $cart_item['quantity'];
        }
    }
    return $count;
}

/**
 * Calcula o subtotal de itens elegíveis no carrinho.
 *
 * @return float Subtotal dos produtos elegíveis para desconto.
 */
function get_eligible_items_subtotal() {
    $subtotal = 0;
    $cart = WC()->cart;

    if ($cart) {
        foreach ($cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];

            if (!is_product_eligible_for_discount($product_id)) {
                continue;
            }

            $subtotal += $cart_item['line_subtotal'];
        }
    }
    return $subtotal;
}

/**
 * Calcula o subtotal de itens não elegíveis no carrinho.
 *
 * @return float Subtotal dos produtos não elegíveis para desconto.
 */
function get_non_eligible_items_subtotal() {
    $subtotal = 0;
    $cart = WC()->cart;

    if ($cart) {
        foreach ($cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];

            if (is_product_eligible_for_discount($product_id)) {
                continue;
            }

            $subtotal += $cart_item['line_subtotal'];
        }
    }
    return $subtotal;
}

/**
 * Obtém o nível de desconto apropriado baseado na quantidade de itens.
 *
 * @param int $item_count Número de itens no carrinho.
 * @return array|false Array com quantidade e porcentagem ou false se não houver desconto aplicável.
 */
function get_applicable_discount_level($item_count) {
    $levels = get_option('custom_discount_levels', array(
        array('quantity' => 6, 'percentage' => 10),
        array('quantity' => 10, 'percentage' => 15)
    ));

    $applicable_level = false;

    foreach ($levels as $level) {
        if ($item_count >= $level['quantity']) {
            $applicable_level = $level;
        } else {
            break;
        }
    }

    return $applicable_level;
}

/**
 * Aplica o desconto personalizado no carrinho.
 */
function apply_custom_discount() {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $cart = WC()->cart;
    if (!$cart) return;

    $valid_items_count = get_eligible_items_count();
    $eligible_subtotal = get_eligible_items_subtotal();
    $non_eligible_subtotal = get_non_eligible_items_subtotal();

    // Obtém o nível de desconto aplicável
    $discount_level = get_applicable_discount_level($valid_items_count);

    if ($discount_level) {
        $discount_percentage = $discount_level['percentage'];
        $discount_amount = ($eligible_subtotal * ($discount_percentage / 100));

        // Verifica o limite máximo de desconto
        $max_discount = get_option('custom_discount_max', 0);
        if ($max_discount > 0 && $discount_amount > $max_discount) {
            $discount_amount = $max_discount;
        }

        // Ajuste no subtotal do carrinho
        $final_cart_total = ($eligible_subtotal - $discount_amount) + $non_eligible_subtotal;

        // Define o total correto no carrinho
        WC()->cart->set_total($final_cart_total);

        // Aplica o desconto com o valor percentual
        $label = sprintf(__('Desconto Automático (%s%%)', 'desconto-automatico'), 
                        number_format($discount_percentage, 1));
        $cart->add_fee($label, -$discount_amount);
    }
}
add_action('woocommerce_cart_calculate_fees', 'apply_custom_discount');

/**
 * Obtém a quantidade de itens restantes para ativar o desconto.
 *
 * @return int Quantidade restante de itens necessários para o desconto.
 */
function get_remaining_items_for_discount() {
    $min_items = get_option('custom_discount_min_items', 6);
    $cart = WC()->cart;
    $current_items = 0;

    if ($cart) {
        foreach ($cart->get_cart() as $cart_item) {
            // Verifica se o produto faz parte da categoria "kit"
            $product_id = $cart_item['product_id'];
            if (!is_product_eligible_for_discount($product_id)) {
                continue; // Ignora itens da categoria "kit"
            }
            $current_items += $cart_item['quantity'];
        }
    }

    $remaining_items = max(0, $min_items - $current_items);
    return $remaining_items;
}

/**
 * Retorna o número de produtos restantes para ativar o desconto via Ajax.
 */
function ajax_get_remaining_items_for_discount() {
    // Verifica se a função existe antes de chamá-la
    if (!function_exists('get_remaining_items_for_discount')) {
        wp_send_json_error(array('message' => 'Função não encontrada.'));
        wp_die();
    }

    // Obtém os itens restantes
    $remaining = get_remaining_items_for_discount();

    // Retorna os dados no formato JSON
    wp_send_json_success(array('remaining' => $remaining));
    wp_die();
}
add_action('wp_ajax_get_remaining_items_for_discount', 'ajax_get_remaining_items_for_discount');
add_action('wp_ajax_nopriv_get_remaining_items_for_discount', 'ajax_get_remaining_items_for_discount');

/**
 * Obtém a mensagem de progresso para o próximo nível de desconto.
 *
 * @return array Informações sobre o próximo nível de desconto.
 */
function get_next_discount_level_info() {
    $current_items = get_eligible_items_count();
    $levels = get_option('custom_discount_levels', array(
        array('quantity' => 6, 'percentage' => 10),
        array('quantity' => 10, 'percentage' => 15)
    ));

    $current_level = false;
    $next_level = false;

    foreach ($levels as $key => $level) {
        if ($current_items >= $level['quantity']) {
            $current_level = $level;
        } else {
            $next_level = $level;
            break;
        }
    }

    return array(
        'current_level' => $current_level,
        'next_level' => $next_level,
        'remaining_items' => $next_level ? ($next_level['quantity'] - $current_items) : 0
    );
}

/**
 * Endpoint AJAX para obter o nível atual de desconto
 */
function get_current_discount_level_ajax() {
    $discount_info = get_next_discount_level_info();
    wp_send_json_success($discount_info);
}
add_action('wp_ajax_get_current_discount_level', 'get_current_discount_level_ajax');
add_action('wp_ajax_nopriv_get_current_discount_level', 'get_current_discount_level_ajax');

/**
 * Formata a porcentagem de desconto mostrando decimais apenas quando necessário
 */
function format_discount_percentage($percentage) {
    // Se o número é inteiro (não tem casas decimais)
    if ($percentage == floor($percentage)) {
        return number_format($percentage, 0);
    }
    // Se tem casas decimais, mostra com uma casa decimal
    return number_format($percentage, 1);
}

/**
 * Substitui as variáveis na mensagem
 */
function replace_message_variables($message, $variables) {
    if (empty($message) || !is_string($message)) {
        return '';
    }

    foreach ($variables as $key => $value) {
        $message = str_replace('{' . $key . '}', $value, $message);
    }

    return $message;
}

/**
 * Prepara todas as variáveis disponíveis para as mensagens
 */
function prepare_message_variables($kit_info = null, $current_level = null, $next_level = null, $current_items = 0) {
    $variables = array();
    
    // Variáveis do kit
    if ($kit_info) {
        $variables['cart_quantity'] = $kit_info['cart_quantity'];
        $variables['total_quantity'] = $kit_info['total_quantity'];
        $variables['remaining'] = $kit_info['total_quantity'] - $kit_info['cart_quantity'];
        
        // Calcula o desconto baseado na quantidade total do kit
        $kit_discount = 0;
        $discount_levels = get_option('custom_discount_levels', array());
        if (!empty($discount_levels)) {
            usort($discount_levels, function($a, $b) {
                return $b['quantity'] - $a['quantity'];
            });
            
            foreach ($discount_levels as $level) {
                if ($kit_info['total_quantity'] >= $level['quantity']) {
                    $kit_discount = $level['percentage'];
                    break;
                }
            }
        }
        $variables['discount'] = format_discount_percentage($kit_discount);
        
        // Próximo nível de desconto para o kit
        $next_kit_discount = 0;
        foreach ($discount_levels as $level) {
            if ($kit_info['total_quantity'] < $level['quantity']) {
                $next_kit_discount = $level['percentage'];
                $variables['next_discount'] = format_discount_percentage($next_kit_discount);
                break;
            }
        }
        
        // Economia potencial
        if ($next_kit_discount > 0) {
            $cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;
            $current_savings = ($cart_total * $kit_discount) / 100;
            $potential_savings = ($cart_total * $next_kit_discount) / 100;
            $variables['savings'] = number_format($potential_savings - $current_savings, 2, ',', '.');
        }
        
        // Quantidade mínima do nível
        foreach ($discount_levels as $level) {
            if ($kit_info['total_quantity'] >= $level['quantity']) {
                $variables['level_quantity'] = $level['quantity'];
                break;
            }
        }
    }
    
    // Variáveis para produtos normais
    if ($current_level) {
        $variables['discount'] = format_discount_percentage($current_level['percentage']);
        $variables['level_quantity'] = $current_level['quantity'];
        
        // Economia atual
        $cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;
        $current_savings = ($cart_total * $current_level['percentage']) / 100;
        $variables['savings'] = number_format($current_savings, 2, ',', '.');
    }
    
    if ($next_level) {
        $variables['next_discount'] = format_discount_percentage($next_level['percentage']);
        $variables['remaining'] = $next_level['quantity'] - $current_items;
        $variables['level_quantity'] = $next_level['quantity'];
        
        // Economia potencial
        if (!isset($variables['savings'])) {
            $cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;
            $potential_savings = ($cart_total * $next_level['percentage']) / 100;
            $variables['savings'] = number_format($potential_savings, 2, ',', '.');
        }
    }
    
    return $variables;
}

/**
 * Gera a mensagem detalhada de desconto
 */
function custom_discount_message() {
    global $product;
    
    // Se não tiver produto, retorna vazio
    if (!$product) {
        return '';
    }

    // Verifica se é um kit
    $is_product_page_kit = is_product() && custom_discount_is_kit($product->get_id());

    // Se não for um kit ou página de produto, retorna vazio
    if (!$is_product_page_kit || !is_product()) {
        return '';
    }

    // Recupera todas as mensagens personalizadas
    $messages = get_option('custom_discount_messages', array(
        'has_discount' => 'Parabéns! Você já tem direito a {discount}% de desconto no carrinho! Compras mínimas de {level_quantity} itens.',
        'has_next_level' => 'Adicione mais {remaining} produtos para aumentar seu desconto para {next_discount}% e economizar mais R$ {savings}! Compras mínimas de {level_quantity} itens.',
        'no_discount' => 'Adicione {remaining} produtos ao carrinho para ganhar {next_discount}% de desconto e economizar R$ {savings}! Compras mínimas de {level_quantity} itens.',
        'kit_discount' => 'Este kit já tem um desconto especial de {discount}%, aproveite!',
        'kit_no_cart_match' => 'Você tem {cart_quantity} de {total_quantity} produtos deste kit no carrinho.',
        'kit_complete' => 'Parabéns! Você já tem a quantidade de produtos deste kit no carrinho, e garantiu seu desconto!'
    ));

    $message = '<div class="custom-product-discount-message">';

    // Obtém informações do kit
    $kit_info = custom_discount_get_kit_cart_info($product->get_id());
    $variables = prepare_message_variables($kit_info);

    error_log('Gerando mensagem - Cart Quantity: ' . $kit_info['cart_quantity'] . ', Total: ' . $kit_info['total_quantity']);
    
    // Se tem todos os produtos do kit
    if ($kit_info['cart_quantity'] >= $kit_info['total_quantity']) {
        $message .= replace_message_variables($messages['kit_complete'], $variables);
    }
    // Se tem alguns produtos do kit no carrinho
    else if ($kit_info['cart_quantity'] > 0) {
        $message .= replace_message_variables($messages['kit_no_cart_match'], $variables);
    }
    // Se não tem produtos do kit no carrinho
    else {
        $message .= replace_message_variables($messages['kit_discount'], $variables);
    }

    $message .= '</div>';
    return $message;
}

/**
 * Shortcode para exibir a mensagem de desconto.
 *
 * Uso: [custom_discount_message]
 */
function custom_discount_message_shortcode() {
    return custom_discount_message();
}
add_shortcode('custom_discount_message', 'custom_discount_message_shortcode');

/**
 * Adiciona a mensagem de desconto na página do produto
 */
function custom_discount_product_message() {
    if (!is_product()) return;
    
    echo custom_discount_message();
}
add_action('woocommerce_before_single_product', 'custom_discount_product_message', 9);

/**
 * Modifica a mensagem do WooCommerce ao adicionar um produto ao carrinho.
 */
/*function custom_woocommerce_add_to_cart_message($message, $products) {
    // Obtém a quantidade mínima necessária para o desconto
    $min_items = get_option('custom_discount_min_items', 6);
    $discount_percentage = get_option('custom_discount_percentage', 10);

    // Obtém a contagem de itens elegíveis no carrinho
    $cart = WC()->cart;
    $current_items = 0;

    if ($cart) {
        foreach ($cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];

            // Se o produto estiver na categoria "kit", ignoramos
            if (has_term('kit', 'product_cat', $product_id)) {
                continue; // Ignora itens da categoria "kit"
            }

            $current_items += $cart_item['quantity'];
        }
    }

    // Calcula quantos itens faltam para atingir o desconto
    $remaining_items = max(0, $min_items - $current_items);

    // Define a mensagem baseada na quantidade de itens
    if ($current_items >= $min_items) {
        $discount_message = "Parabéns! Você já tem direito a <strong style='color:#BD9E5E;'>{$discount_percentage}% de desconto</strong>.";
    } else {
        $discount_message = "Adicione mais <strong>{$remaining_items} produtos</strong> ao carrinho para obter <strong>{$discount_percentage}% de desconto</strong>!";
    }

    // Adiciona a mensagem à notificação padrão do WooCommerce
    $custom_message = $message . '<span class="custom-discount-notice">' . wp_kses_post($discount_message) . '</span>';

    return $custom_message;
}
add_filter('wc_add_to_cart_message_html', 'custom_woocommerce_add_to_cart_message', 10, 2);*/

/**
 * Calcula a economia em reais baseada no desconto
 * 
 * @param float $subtotal Subtotal dos produtos
 * @param float $discount_percentage Porcentagem de desconto
 * @return float Valor em reais que será economizado
 */
function calculate_savings($subtotal, $discount_percentage) {
    return $subtotal * ($discount_percentage / 100);
}

/**
 * Calcula a economia total possível considerando o produto atual
 * 
 * @param float $next_discount_percentage Porcentagem do próximo nível de desconto
 * @param int $remaining_items Quantidade de itens restantes para o próximo nível
 * @return float Valor em reais que poderá ser economizado
 */
function calculate_potential_savings($next_discount_percentage, $remaining_items) {
    global $product;
    
    // Obtém o subtotal atual de produtos elegíveis
    $current_subtotal = get_eligible_items_subtotal();
    
    // Se estivermos na página de produto, adiciona o valor do produto atual
    if ($product && is_product() && is_product_eligible_for_discount($product->get_id())) {
        $product_price = $product->get_price();
        // Multiplica o preço do produto pela quantidade restante
        $potential_additional = $product_price * $remaining_items;
        $current_subtotal += $potential_additional;
    }
    
    return calculate_savings($current_subtotal, $next_discount_percentage);
}
