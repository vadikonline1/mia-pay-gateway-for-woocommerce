<?php
/**
 * Plugin Name: MIA POS Payment Gateway for WooCommerce
 * Plugin URI: https://finergy.md/
 * Description: Accept payments in your WooCommerce store using MIA POS payment gateway.
 * Version: 1.0.0
 * Author: Finergy Tech
 * Author URI: https://finergy.md/
 * Text Domain: mia-pos-payment-gateway-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 * WC tested up to: 8.0
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

// Plugin constants
define('MIA_POS_PLUGIN_FILE', __FILE__);
define('MIA_POS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MIA_POS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MIA_POS_VERSION', '1.0.0');

/**
 * Declare HPOS compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

/**
 * Initialize the plugin
 */
function mia_pos_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'mia_pos_woocommerce_missing_notice');
        return;
    }

    // Load plugin text domain
    load_plugin_textdomain(
        'mia-pos-payment-gateway-for-woocommerce',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );

    // Include main gateway class
    require_once MIA_POS_PLUGIN_DIR . 'class-mia-pos-payment-gateway.php';

    // Register payment gateway
    add_filter('woocommerce_payment_gateways', 'mia_pos_add_gateway');

    add_action('wp_enqueue_scripts', 'enqueue_mia_pos_payment_gateway_styles');
    
    function enqueue_mia_pos_payment_gateway_styles()
    {
        // Get the version of your plugin from the plugin header
        $plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
        $plugin_version = $plugin_data['Version'];
    
        // Enqueue the custom CSS file with the plugin version
        wp_enqueue_style('mia-pos-payment-gateway-styles', MIA_POS_PLUGIN_DIR . 'assets/css/style.css', array(), $plugin_version);
    }
}
add_action('plugins_loaded', 'mia_pos_init');




/**
 * Add MIA POS Gateway to WooCommerce
 */
function mia_pos_add_gateway($gateways) {
    $gateways[] = 'WC_MIA_POS_Payment_Gateway';
    return $gateways;
}

/**
 * WooCommerce missing notice
 */
function mia_pos_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php
            echo sprintf(
                __('MIA POS Payment Gateway requires WooCommerce to be installed and active. You can download %s here.', 'mia-pos-payment-gateway-for-woocommerce'),
                '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
            );
        ?></p>
    </div>
    <?php
}

/**
 * Add plugin action links
 */
function mia_pos_plugin_action_links($links) {
    $plugin_links = array(
        '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=mia_pos')) . '">' . 
        __('Settings', 'mia-pos-payment-gateway-for-woocommerce') . '</a>'
    );
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'mia_pos_plugin_action_links');

// Block initialization in WooCommerce
add_action('woocommerce_blocks_loaded', function() {
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }
    
    require_once plugin_dir_path(__FILE__) . 'class-block.php';
    
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function(\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new MiaPosPaymentGateway_Blocks());
        }
    );
});

// Activation hook
register_activation_hook(__FILE__, 'mia_payment_gateway_activation');

function mia_payment_gateway_activation() {
    if (!class_exists('WC_Payment_Gateway')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Please install and activate WooCommerce before activating this plugin.', 'mia-pos-payment-gateway-for-woocommerce'));
    }
} 


// Auto-update from GitHub
add_filter('site_transient_update_plugins', function($transient){
    if(empty($transient->checked)) return $transient;

    $plugin_slug = plugin_basename(__FILE__);
    $remote_url = 'https://raw.githubusercontent.com/finergy-tech/mia-pay-gateway-for-woocommerce/main/mia-pos-payment-gateway-for-woocommerce.php
';

    $response = wp_remote_get($remote_url);
    if(is_wp_error($response)) return $transient;

    $remote_plugin_data = $response['body'];

    if(preg_match('/Version:\s*(\S+)/', $remote_plugin_data, $matches)){
        $remote_version = $matches[1];
        $current_version = $transient->checked[$plugin_slug];

        if(version_compare($remote_version, $current_version, '>')){
            $transient->response[$plugin_slug] = (object) [
                'slug' => $plugin_slug,
                'new_version' => $remote_version,
                'url' => 'https://raw.githubusercontent.com/finergy-tech/mia-pay-gateway-for-woocommerce/',
                'package' => 'https://raw.githubusercontent.com/finergy-tech/mia-pay-gateway-for-woocommerce/archive/refs/heads/main.zip'
            ];
        }
    }
    return $transient;
});
