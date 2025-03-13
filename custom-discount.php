<?php
/**
 * Plugin Name: Custom Discount
 * Plugin URI: https://spielmann.com.br
 * Description: Plugin personalizado para aplicar descontos progressivos baseados na quantidade de produtos no carrinho.
 * Version: 2.0
 * Author: Spielmann
 * Author URI: https://spielmann.com.br
 * Text Domain: desconto-automatico
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Segurança: bloqueia acesso direto
}

// Definição de constantes
define('DESCONTO_AUTOMATICO_PATH', plugin_dir_path(__FILE__));
define('DESCONTO_AUTOMATICO_URL', plugin_dir_url(__FILE__));

// Carregamento dos arquivos do plugin
require_once DESCONTO_AUTOMATICO_PATH . 'includes/admin-settings.php';
require_once DESCONTO_AUTOMATICO_PATH . 'includes/discount-functions.php';
require_once DESCONTO_AUTOMATICO_PATH . 'includes/frontend-display.php';
require_once DESCONTO_AUTOMATICO_PATH . 'includes/coupon-management.php';
require_once DESCONTO_AUTOMATICO_PATH . 'includes/kit-functions.php';

// Verificação de compatibilidade com WooCommerce
function check_woocommerce_compatibility() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>';
            echo 'O plugin Desconto Automático requer o WooCommerce instalado e ativo.';
            echo '</p></div>';
        });
        return false;
    }

    // Versão mínima requerida do WooCommerce
    $required_version = '7.0.0';
    $wc_version = WC()->version;

    if (version_compare($wc_version, $required_version, '<')) {
        add_action('admin_notices', function() use ($required_version, $wc_version) {
            echo '<div class="error"><p>';
            echo sprintf('O plugin Desconto Automático requer WooCommerce %s ou superior. Você está usando a versão %s.', 
                        esc_html($required_version), 
                        esc_html($wc_version));
            echo '</p></div>';
        });
        return false;
    }

    return true;
}

// Ativação e desativação do plugin
function desconto_automatico_activate() {
    if (!check_woocommerce_compatibility()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Por favor, instale e ative o WooCommerce antes de ativar o plugin Desconto Automático.');
    }

    // Configurações padrão dos níveis de desconto
    $default_levels = array(
        array('quantity' => 6, 'percentage' => 10),
        array('quantity' => 10, 'percentage' => 15)
    );
    
    update_option('custom_discount_levels', $default_levels);
    update_option('custom_discount_max', 0); // 0 = sem limite
}
register_activation_hook(__FILE__, 'desconto_automatico_activate');

function desconto_automatico_deactivate() {
    delete_option('custom_discount_levels');
    delete_option('custom_discount_max');
}
register_deactivation_hook(__FILE__, 'desconto_automatico_deactivate');

// Registra os scripts e estilos
function register_custom_discount_assets() {
    wp_register_style('custom-discount-style', plugins_url('assets/css/custom-discount.css', __FILE__));
    wp_register_script('custom-discount-script', plugins_url('assets/js/custom-discount.js', __FILE__), array('jquery'), '1.0', true);
    
    // Registra o CSS do admin para kits
    if (is_admin() && get_post_type() === 'product') {
        wp_enqueue_style('custom-discount-admin-kit', plugins_url('assets/css/admin-kit.css', __FILE__));
    }
}
add_action('wp_enqueue_scripts', 'register_custom_discount_assets');
add_action('admin_enqueue_scripts', 'register_custom_discount_assets');
