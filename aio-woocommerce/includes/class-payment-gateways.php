<?php
/**
 * Payment Gateways Manager
 * 
 * Manages Persian payment gateway integrations (Zarinpal, Zibal, etc.)
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIO_WC_Payment_Gateways {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register payment gateways with WooCommerce
        add_filter('woocommerce_payment_gateways', array($this, 'register_gateways'));
        
        // Hide our gateways from WooCommerce settings page to avoid duplication
        add_filter('woocommerce_get_settings_pages', array($this, 'hide_gateway_settings_page'), 999);
        
        // Redirect WooCommerce payment gateway settings to our plugin page
        add_action('admin_init', array($this, 'redirect_gateway_settings'));
        
        // Load gateway classes
        $this->load_gateways();
    }
    
    /**
     * Hide gateway settings from WooCommerce payment settings page
     * We manage them in our plugin's admin tab instead
     */
    public function hide_gateway_settings_page($settings) {
        // Don't remove the settings page, but we'll redirect users to our plugin page instead
        return $settings;
    }
    
    /**
     * Redirect users from WooCommerce payment gateway settings to our plugin page
     */
    public function redirect_gateway_settings() {
        if (!is_admin() || !isset($_GET['page']) || $_GET['page'] !== 'wc-settings') {
            return;
        }
        
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'checkout') {
            return;
        }
        
        // Check if user is trying to access our gateway settings
        if (isset($_GET['section']) && in_array($_GET['section'], array('aio_wc_zarinpal', 'aio_wc_zibal'))) {
            // Redirect to our plugin's payment gateways tab using hash navigation
            $redirect_url = add_query_arg(array(
                'page' => 'aio-woocommerce',
            ), admin_url('admin.php')) . '#payment-gateways';
            
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Load payment gateway classes
     */
    private function load_gateways() {
        require_once AIO_WC_PLUGIN_DIR . 'includes/gateways/class-zarinpal.php';
        require_once AIO_WC_PLUGIN_DIR . 'includes/gateways/class-zibal.php';
    }
    
    /**
     * Register payment gateways with WooCommerce
     * 
     * @param array $gateways Existing gateways
     * @return array Updated gateways list
     */
    public function register_gateways($gateways) {
        $gateways[] = 'AIO_WC_Gateway_Zarinpal';
        $gateways[] = 'AIO_WC_Gateway_Zibal';
        return $gateways;
    }
    
    /**
     * Get gateway settings
     * 
     * @param string $gateway_id Gateway ID (zarinpal, zibal)
     * @return array Gateway settings
     */
    public static function get_gateway_settings($gateway_id) {
        $settings = get_option('aio_wc_settings', array());
        $gateway_key = 'payment_gateway_' . $gateway_id;
        
        if (!isset($settings[$gateway_key])) {
            return array();
        }
        
        return $settings[$gateway_key];
    }
    
    /**
     * Check if gateway is enabled
     * 
     * @param string $gateway_id Gateway ID
     * @return bool True if enabled
     */
    public static function is_gateway_enabled($gateway_id) {
        $settings = self::get_gateway_settings($gateway_id);
        return isset($settings['enabled']) && $settings['enabled'] === 'yes';
    }
}

