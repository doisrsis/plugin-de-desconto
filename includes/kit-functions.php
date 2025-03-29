<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adiciona um campo para selecionar produtos e quantidades vinculados no admin
 */
function custom_discount_add_kit_meta_box() {
    // Removida a linha que ocultava a imagem destacada
    add_meta_box(
        'custom_discount_kit_products',
        'Produtos do Kit',
        'custom_discount_render_kit_meta_box',
        'product',
        'after_title',
        'high'
    );
}
add_action('add_meta_boxes', 'custom_discount_add_kit_meta_box', 1);

/**
 * Renderiza o metabox de produtos do kit
 */
function custom_discount_render_kit_meta_box($post) {
    $linked_products = get_post_meta($post->ID, '_custom_discount_kit_products', true);
    $linked_products = is_array($linked_products) ? $linked_products : [];

    // Busca produtos que NÃO são kits
    $args = [
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'post__not_in'   => [$post->ID],
        'tax_query'      => [
            [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => 'kit',
                'operator' => 'NOT IN'
            ]
        ]
    ];
    $products = get_posts($args);

    if (empty($products)) {
        echo '<p>Nenhum produto disponível para adicionar ao kit.</p>';
        return;
    }

    echo '<p>Selecione os produtos que fazem parte deste kit e suas quantidades:</p>';
    echo '<div class="kit-products-list" style="max-height: 200px; overflow-y: auto; margin-bottom: 10px;">';
    echo '<table class="widefat" style="border: none;">';
    echo '<thead>
            <tr>
                <th style="width: 20px;"></th>
                <th>Produto</th>
                <th style="width: 80px;">Qtd</th>
            </tr>
          </thead>';
    echo '<tbody>';
    foreach ($products as $product) {
        $quantity = isset($linked_products[$product->ID]) ? intval($linked_products[$product->ID]) : 0;
        echo '<tr>
                <td>
                    <input type="checkbox" 
                           name="custom_discount_kit_products[' . esc_attr($product->ID) . ']" 
                           value="' . esc_attr($product->ID) . '" 
                           ' . (isset($linked_products[$product->ID]) ? 'checked' : '') . '>
                </td>
                <td>' . esc_html($product->post_title) . '</td>
                <td>
                    <input type="number" 
                           name="custom_discount_kit_quantities[' . esc_attr($product->ID) . ']" 
                           value="' . esc_attr($quantity) . '" 
                           min="0" 
                           style="width: 60px;">
                </td>
              </tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    wp_nonce_field('custom_discount_save_kit', 'custom_discount_kit_nonce');
}

/**
 * Move a meta box para a coluna principal
 */
function custom_discount_move_kit_metabox($post_type) {
    // Remove a meta box atual
    remove_meta_box('custom_discount_kit_products', 'product', 'side');
    remove_meta_box('custom_discount_kit_products', 'product', 'normal');
    remove_meta_box('custom_discount_kit_products', 'product', 'advanced');

    // Adiciona na coluna principal com alta prioridade
    add_meta_box(
        'custom_discount_kit_products',
        'Produtos do Kit',
        'custom_discount_render_kit_meta_box',
        'product',
        'advanced',
        'high'
    );
}

/**
 * Inicializa as meta boxes do kit
 */
function custom_discount_init_kit_metaboxes() {
    add_action('add_meta_boxes_product', 'custom_discount_move_kit_metabox');
}
add_action('admin_init', 'custom_discount_init_kit_metaboxes', 1);

/**
 * Salva os produtos e quantidades vinculados ao kit
 */
function custom_discount_save_kit_meta_box($post_id) {
    if (!isset($_POST['custom_discount_kit_nonce']) || !wp_verify_nonce($_POST['custom_discount_kit_nonce'], 'custom_discount_save_kit')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $linked_products = isset($_POST['custom_discount_kit_products']) ? $_POST['custom_discount_kit_products'] : [];
    $quantities = isset($_POST['custom_discount_kit_quantities']) ? $_POST['custom_discount_kit_quantities'] : [];

    $kit_products = [];
    foreach ($linked_products as $product_id) {
        $kit_products[intval($product_id)] = isset($quantities[$product_id]) ? max(0, intval($quantities[$product_id])) : 0;
    }

    update_post_meta($post_id, '_custom_discount_kit_products', $kit_products);
}
add_action('save_post', 'custom_discount_save_kit_meta_box');

/**
 * Obtém informações do kit no carrinho
 */
function custom_discount_get_kit_cart_info($product_id) {
    // Obtém os produtos do kit
    $kit_products = get_post_meta($product_id, '_custom_discount_kit_products', true);
    if (empty($kit_products)) {
        error_log('Kit vazio para o produto ' . $product_id);
        return array(
            'total_quantity' => 0,
            'cart_quantity' => 0,
            'remaining' => 0,
            'discount_level_quantity' => 0
        );
    }

    error_log('Produtos do kit: ' . print_r($kit_products, true));

    // Quantidade total de produtos do kit
    $total_quantity = 0;
    foreach ($kit_products as $product_id => $quantity) {
        $total_quantity += $quantity;
    }
    
    error_log('Quantidade total necessária: ' . $total_quantity);
    
    // Obtém o nível de desconto mais baixo
    $discount_levels = get_option('custom_discount_levels', array());
    $discount_level_quantity = 0;
    if (!empty($discount_levels)) {
        // Ordena os níveis por quantidade (crescente)
        usort($discount_levels, function($a, $b) {
            return $a['quantity'] - $b['quantity'];
        });
        $discount_level_quantity = $discount_levels[0]['quantity'];
    }
    
    // Verifica se tem carrinho
    if (!WC()->cart) {
        error_log('Carrinho não disponível');
        return array(
            'total_quantity' => $total_quantity,
            'cart_quantity' => 0,
            'remaining' => $total_quantity,
            'discount_level_quantity' => $discount_level_quantity
        );
    }

    // Primeiro, conta quantos produtos de cada tipo tem no carrinho
    $products_in_cart = array();
    foreach (WC()->cart->get_cart() as $cart_item) {
        $cart_product_id = $cart_item['product_id'];
        if (isset($kit_products[$cart_product_id])) {
            $products_in_cart[$cart_product_id] = $cart_item['quantity'];
            error_log(sprintf(
                'Produto %d encontrado no carrinho: %d unidades (necessário: %d)', 
                $cart_product_id, 
                $cart_item['quantity'],
                $kit_products[$cart_product_id]
            ));
        }
    }

    error_log('Produtos no carrinho: ' . print_r($products_in_cart, true));

    // Calcula a quantidade de produtos do kit no carrinho
    $cart_quantity = 0;
    $all_products_present = true;

    foreach ($kit_products as $product_id => $quantity_needed) {
        // Se não tem o produto no carrinho
        if (!isset($products_in_cart[$product_id])) {
            error_log('Produto ' . $product_id . ' não está no carrinho');
            $all_products_present = false;
            continue;
        }

        // Calcula quantos produtos deste tipo podemos usar
        $quantity_in_cart = $products_in_cart[$product_id];
        $quantity_to_use = min($quantity_in_cart, $quantity_needed);
        $cart_quantity += $quantity_to_use;

        error_log(sprintf(
            'Produto %d: %d no carrinho, usando %d unidades', 
            $product_id,
            $quantity_in_cart,
            $quantity_to_use
        ));
    }

    // Se algum produto estiver faltando, zera a quantidade
    if (!$all_products_present) {
        $cart_quantity = 0;
        error_log('Faltam produtos do kit - zerando quantidade');
    }

    error_log(sprintf(
        'Kit Info - ID: %d, Total: %d, No Carrinho: %d, Nível Mínimo: %d', 
        $product_id, 
        $total_quantity, 
        $cart_quantity, 
        $discount_level_quantity
    ));

    return array(
        'total_quantity' => $total_quantity,
        'cart_quantity' => $cart_quantity,
        'remaining' => max(0, $total_quantity - $cart_quantity),
        'discount_level_quantity' => $discount_level_quantity
    );
}

/**
 * Adiciona os produtos do kit ao carrinho e remove o kit
 */
function custom_discount_process_kit($cart_item_key, $product_id) {
    // Verifica se é um kit
    if (!custom_discount_is_kit($product_id)) {
        return;
    }

    // Obtém os produtos do kit
    $kit_products = get_post_meta($product_id, '_custom_discount_kit_products', true);
    if (empty($kit_products)) {
        return;
    }

    // Adiciona cada produto do kit ao carrinho com sua quantidade específica
    foreach ($kit_products as $product_id => $quantity) {
        WC()->cart->add_to_cart($product_id, $quantity);
    }

    // Remove o kit do carrinho
    WC()->cart->remove_cart_item($cart_item_key);
}
add_action('woocommerce_add_to_cart', 'custom_discount_process_kit', 10, 2);

/**
 * Verifica se um produto é um kit
 */
function custom_discount_is_kit($product_id) {
    $kit_products = get_post_meta($product_id, '_custom_discount_kit_products', true);
    $is_kit = !empty($kit_products);
    error_log('Verificando kit - ID: ' . $product_id . ', É kit? ' . ($is_kit ? 'Sim' : 'Não'));
    return $is_kit;
}

/**
 * Calcula o desconto do kit com base na quantidade de produtos
 */
function custom_discount_get_kit_discount($product_id) {
    // Obtém os produtos vinculados ao kit
    $linked_products = get_post_meta($product_id, '_custom_discount_kit_products', true);
    if (!is_array($linked_products)) {
        return 0;
    }

    // Soma a quantidade total de produtos no kit
    $total_quantity = 0;
    foreach ($linked_products as $linked_id => $quantity) {
        $total_quantity += intval($quantity);
    }

    // Obtém os níveis de desconto
    $discount_levels = get_option('custom_discount_levels', array());
    if (empty($discount_levels)) {
        return 0;
    }

    // Ordena os níveis por quantidade (decrescente)
    usort($discount_levels, function($a, $b) {
        return $b['quantity'] - $a['quantity'];
    });

    // Encontra o nível de desconto adequado
    foreach ($discount_levels as $level) {
        if ($total_quantity >= $level['quantity']) {
            return floatval($level['percentage']);
        }
    }

    return 0;
}
