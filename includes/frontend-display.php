<?php
if (!defined('ABSPATH')) exit;

// Carrega os estilos CSS do plugin
function custom_discount_enqueue_styles() {
    wp_enqueue_style(
        'custom-discount-styles',
        DESCONTO_AUTOMATICO_URL . 'assets/css/custom-discount.css',
        array(),
        '1.0.0'
    );

    // Carrega o JavaScript
    wp_enqueue_script(
        'custom-discount-script',
        DESCONTO_AUTOMATICO_URL . 'assets/js/custom-discount.js',
        array('jquery'),
        '1.0.0',
        true
    );

    // Passa variáveis para o JavaScript
    wp_localize_script(
        'custom-discount-script',
        'customDiscount',
        array(
            'ajax_url' => admin_url('admin-ajax.php')
        )
    );
}
add_action('wp_enqueue_scripts', 'custom_discount_enqueue_styles');

/**
 * Exibe a mensagem de desconto na página do produto
 */
function custom_discount_display_message() {
    global $product;

    if (!$product) {
        return;
    }

    // Se não for página de produto, retorna
    if (!is_product()) {
        return;
    }

    // Obtém a mensagem
    $message = custom_discount_message();
    if (!empty($message)) {
        echo $message;
    }
}
add_action('woocommerce_before_add_to_cart_form', 'custom_discount_display_message');

/**
 * Exibe a mensagem de desconto no carrinho
 */
function custom_discount_display_cart_message() {
    $message = custom_discount_message();
    if (!empty($message)) {
        echo $message;
    }
}
add_action('woocommerce_before_cart', 'custom_discount_display_cart_message');
