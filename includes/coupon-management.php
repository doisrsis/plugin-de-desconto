<?php
if (!defined('ABSPATH')) exit;

/**
 * Aplica ou remove automaticamente o cupom de desconto, ignorando produtos da categoria "kit".
 */
function auto_apply_discount_coupon() {
    if (is_admin()) return;

    $args = array(
        'posts_per_page' => 1,
        'post_type'      => 'shop_coupon',
        'post_status'    => 'publish',
        'meta_query'     => array(
            array(
                'key'     => '_is_auto_discount',
                'value'   => '1',
                'compare' => '='
            )
        )
    );

    $current_coupons = get_posts($args);
    if (!empty($current_coupons)) {
        $coupon = new WC_Coupon($current_coupons[0]->post_title);
        $min_items = get_option('custom_discount_min_items', 6);
        $valid_items_count = get_non_kit_items_count();

        if ($valid_items_count >= $min_items && !WC()->cart->has_discount($coupon->get_code())) {
            WC()->cart->apply_coupon($coupon->get_code());
        } elseif ($valid_items_count < $min_items && WC()->cart->has_discount($coupon->get_code())) {
            WC()->cart->remove_coupon($coupon->get_code());
        }
    }
}
add_action('woocommerce_before_cart', 'auto_apply_discount_coupon');
add_action('woocommerce_before_checkout_form', 'auto_apply_discount_coupon');

