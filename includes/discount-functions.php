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
 * Obtem o nível de desconto apropriado baseado na quantidade de itens.
 *
 * @param int $item_count Número de itens no carrinho.
 * @return array|false Array com quantidade e porcentagem ou false se não houver desconto aplicável.
 */
function get_applicable_discount_level($item_count) {
    $min_items = get_option('custom_discount_min_items', 6);
    $discount_percentage = get_option('custom_discount_percentage', 10);
    
    if ($item_count >= $min_items) {
        return array(
            'quantity' => $min_items,
            'percentage' => $discount_percentage
        );
    }
    
    return false;
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
 * Obtem a quantidade de itens restantes para ativar o desconto.
 *
 * @return int Quantidade restante de itens necessários para o desconto.
 */
function get_remaining_items_for_discount() {
    $valid_items_count = get_eligible_items_count();
    $min_items = get_option('custom_discount_min_items', 6);
    
    if ($valid_items_count >= $min_items) {
        return 0;
    }
    
    return $min_items - $valid_items_count;
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
 * Obtem a mensagem de progresso para o próximo nível de desconto.
 *
 * @return array Informações sobre o próximo nível de desconto.
 */
function get_next_discount_level_info() {
    $current_items = get_eligible_items_count();
    $min_items = get_option('custom_discount_min_items', 6);
    $discount_percentage = get_option('custom_discount_percentage', 10);

    $current_level = false;
    $next_level = false;
    $remaining_items = 0;

    if ($current_items >= $min_items) {
        $current_level = array(
            'quantity' => $min_items,
            'percentage' => $discount_percentage
        );
    } else {
        $next_level = array(
            'quantity' => $min_items,
            'percentage' => $discount_percentage
        );
        $remaining_items = $min_items - $current_items;
    }

    return array(
        'current_level' => $current_level,
        'next_level' => $next_level,
        'remaining_items' => $remaining_items
    );
}

/**
 * Endpoint AJAX para obter o nível atual de desconto
 */
function get_current_discount_level_ajax() {
    $current_items = get_eligible_items_count();
    $current_level = get_applicable_discount_level($current_items);
    $next_level = get_next_discount_level_info();
    
    // Obter a mensagem personalizada para o toast
    $messages = get_option('custom_discount_messages', array(
        'toast_notification' => '<strong>Parabéns!</strong><br>Você atingiu {discount}% de desconto!<br>Adicione mais {remaining} produtos para {next_discount}%'
    ));
    
    // Obter as posições da notificação toast
    $toast_position_h = get_option('custom_discount_toast_position_h', 'right');
    $toast_position_v = get_option('custom_discount_toast_position_v', 'bottom');
    
    // Preparar variáveis para a mensagem
    $variables = prepare_message_variables(null, $current_level, $next_level, $current_items);
    $toast_message = isset($messages['toast_notification']) ? replace_message_variables($messages['toast_notification'], $variables) : '';
    
    wp_send_json_success(array(
        'current_level' => $current_level,
        'next_level' => $next_level,
        'remaining_items' => $next_level ? $next_level['quantity'] - $current_items : 0,
        'toast_message' => $toast_message,
        'toast_position_h' => $toast_position_h,
        'toast_position_v' => $toast_position_v
    ));
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
        $discount_percentage = get_option('custom_discount_percentage', 10);
        if ($kit_info['total_quantity'] >= get_option('custom_discount_min_items', 6)) {
            $kit_discount = $discount_percentage;
        }
        $variables['discount'] = format_discount_percentage($kit_discount);
        
        // Adiciona a variável admin_discount para exibir a porcentagem cadastrada no admin
        $variables['admin_discount'] = format_discount_percentage($discount_percentage);
        
        // Próximo nível de desconto para o kit
        $next_kit_discount = 0;
        if ($kit_info['total_quantity'] < get_option('custom_discount_min_items', 6)) {
            $next_kit_discount = $discount_percentage;
            $variables['next_discount'] = format_discount_percentage($next_kit_discount);
        }
        
        // Economia potencial
        if ($next_kit_discount > 0) {
            $cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;
            $current_savings = ($cart_total * $kit_discount) / 100;
            $potential_savings = ($cart_total * $next_kit_discount) / 100;
            $variables['savings'] = number_format($potential_savings - $current_savings, 2, ',', '.');
        }
        
        // Quantidade mínima do nível
        $variables['level_quantity'] = get_option('custom_discount_min_items', 6);
        
        // Adiciona valor do produto com desconto
        global $product;
        if ($product) {
            $product_price = $product->get_price();
            $discounted_price = $product_price - ($product_price * $kit_discount / 100);
            $variables['product_discounted_price'] = number_format($discounted_price, 2, ',', '.');
            
            // Adiciona valor do rótulo individual do kit com desconto
            // Obtemos a quantidade total de rótulos no kit
            $kit_products = get_post_meta($product->get_id(), '_custom_discount_kit_products', true);
            $total_rotulos = 0;
            
            if (is_array($kit_products) && !empty($kit_products)) {
                foreach ($kit_products as $prod_id => $quantity) {
                    $total_rotulos += intval($quantity);
                }
                
                // Se temos rótulos no kit, calculamos o preço por rótulo
                if ($total_rotulos > 0) {
                    // O preço por rótulo é simplesmente o preço do kit dividido pela quantidade de rótulos
                    $preco_por_rotulo = $product_price / $total_rotulos;
                    $variables['kit_rotulo_price'] = number_format($preco_por_rotulo, 2, ',', '.');
                }
            }
        }
    }
    else {
        // Variáveis para mensagens de desconto com base na quantidade
        $min_items = get_option('custom_discount_min_items', 6);
        $discount_percentage = get_option('custom_discount_percentage', 10);

        // Adiciona a variável admin_discount para exibir a porcentagem cadastrada no admin
        $variables['admin_discount'] = format_discount_percentage($discount_percentage);
        $variables['discount'] = format_discount_percentage($discount_percentage);
        $variables['level_quantity'] = $min_items;

        $remaining_items = get_remaining_items_for_discount();
        $variables['remaining'] = $remaining_items;

        // Calcula a economia potencial
        $potential_savings = calculate_potential_savings($discount_percentage, $remaining_items);
        $variables['savings'] = number_format($potential_savings, 2, ',', '.');

        // Informações sobre o próximo nível de desconto
        $next_level = get_next_discount_level_info();
        if ($next_level && $next_level['next_level']) {
            $variables['next_discount'] = format_discount_percentage($discount_percentage);
        }
        
        // Adiciona valor do produto com desconto
        global $product;
        if ($product && is_product()) {
            $product_price = $product->get_price();
            $discounted_price = $product_price - ($product_price * $discount_percentage / 100);
            $variables['product_discounted_price'] = number_format($discounted_price, 2, ',', '.');
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

    // Recupera todas as mensagens personalizadas
    $messages = get_option('custom_discount_messages', array(
        'has_discount' => 'Parabéns! Você já tem direito a {discount}% de desconto no carrinho! Compras mínimas de {level_quantity} itens.',
        'has_next_level' => 'Adicione mais {remaining} produtos para aumentar seu desconto para {next_discount}% e economizar mais R$ {savings}! Compras mínimas de {level_quantity} itens.',
        'no_discount' => 'Adicione {remaining} produtos ao carrinho para ganhar {next_discount}% de desconto e economizar R$ {savings}! Compras mínimas de {level_quantity} itens.',
        'kit_discount' => 'Este kit já tem um desconto especial de {discount}%, aproveite!',
        'kit_no_cart_match' => 'Você tem {cart_quantity} de {total_quantity} produtos deste kit no carrinho.',
        'kit_complete' => 'Parabéns! Você já tem a quantidade de produtos deste kit no carrinho, e garantiu seu desconto!'
    ));

    $message = '';

    // Se for um kit
    if ($is_product_page_kit) {
        // Obtém informações do kit
        $kit_info = custom_discount_get_kit_cart_info($product->get_id());
        $variables = prepare_message_variables($kit_info);

        // Se tem todos os produtos do kit
        if ($kit_info['cart_quantity'] >= $kit_info['total_quantity']) {
            $message = replace_message_variables($messages['kit_complete'], $variables);
        }
        // Se tem alguns produtos do kit no carrinho
        else if ($kit_info['cart_quantity'] > 0) {
            $message = replace_message_variables($messages['kit_no_cart_match'], $variables);
        }
        // Se não tem produtos do kit no carrinho
        else {
            $message = replace_message_variables($messages['kit_discount'], $variables);
        }
    }
    // Se for um produto individual (não kit)
    else if (is_product() && is_product_eligible_for_discount($product->get_id())) {
        // Prepara as variáveis para produtos individuais
        $variables = prepare_message_variables();
        $valid_items_count = get_eligible_items_count();
        $min_items = get_option('custom_discount_min_items', 6);
        
        // Verifica se já atingiu o desconto
        if ($valid_items_count >= $min_items) {
            $message = replace_message_variables($messages['has_discount'], $variables);
        } else {
            $message = replace_message_variables($messages['no_discount'], $variables);
        }
    } else {
        // Não é um produto elegível para desconto
        return '';
    }

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
    
    global $product;
    if (!$product) return;
    
    $product_id = $product->get_id();
    $is_eligible = is_product_eligible_for_discount($product_id);
    
    // Se o produto é elegível para desconto, exibe a mensagem de desconto
    if ($is_eligible) {
        // Recupera todas as mensagens personalizadas
        $messages = get_option('custom_discount_messages', array(
            'has_discount' => 'Parabéns! Você já tem direito a {discount}% de desconto no carrinho! Compras mínimas de {level_quantity} itens.',
            'no_discount' => 'Adicione {remaining} produtos ao carrinho para ganhar {next_discount}% de desconto e economizar R$ {savings}! Compras mínimas de {level_quantity} itens.'
        ));
        
        // Prepara as variáveis para produtos individuais
        $variables = prepare_message_variables();
        $valid_items_count = get_eligible_items_count();
        $min_items = get_option('custom_discount_min_items', 6);
        
        echo '<div class="custom-discount-message-top debug-mobile-message">';
        
        // Verifica se já atingiu o desconto
        if ($valid_items_count >= $min_items) {
            echo replace_message_variables($messages['has_discount'], $variables);
        } else {
            echo replace_message_variables($messages['no_discount'], $variables);
        }
        
        echo '</div>';
    } else {
        // Se for um kit, usa a função original
        $message = custom_discount_message();
        if (!empty($message)) {
            echo '<div class="custom-discount-message-top debug-mobile-message">' . $message . '</div>';
        }
    }
}
add_action('woocommerce_before_single_product', 'custom_discount_product_message', 9);

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

/**
 * Endpoint AJAX para obter o ponto de quebra para dispositivos móveis
 */
function get_mobile_breakpoint_ajax() {
    // Obtem as configurações de personalização visual
    $breakpoint = get_option('custom_discount_mobile_breakpoint', 768);
    $bg_color = get_option('custom_discount_message_bg_color', '#ffffff');
    $border_color = get_option('custom_discount_message_border_color', '#dddddd');
    $text_color = get_option('custom_discount_message_text_color', '#333333');
    $font_family = get_option('custom_discount_message_font_family', 'inherit');
    $font_size = get_option('custom_discount_message_font_size', 14);
    
    wp_send_json_success(array(
        'breakpoint' => $breakpoint,
        'styles' => array(
            'bg_color' => $bg_color,
            'border_color' => $border_color,
            'text_color' => $text_color,
            'font_family' => $font_family,
            'font_size' => $font_size
        )
    ));
}
add_action('wp_ajax_get_mobile_breakpoint', 'get_mobile_breakpoint_ajax');
add_action('wp_ajax_nopriv_get_mobile_breakpoint', 'get_mobile_breakpoint_ajax');
