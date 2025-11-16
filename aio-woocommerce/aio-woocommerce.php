<?php
/**
 * Plugin Name: AIO WooCommerce - Persian Enhancement
 * Plugin URI: https://example.com
 * Description: Persian calendar conversion for WordPress and optional WooCommerce enhancements including date pickers and frontend date tags.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: aio-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AIO_WC_VERSION', '1.4.8');
define('AIO_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIO_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIO_WC_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('AIO_WC_DEFAULTS_VERSION', '1.5.0');

/**
 * Main plugin class
 */
class AIO_WooCommerce {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load plugin files
        $this->load_dependencies();
        
        // Declare WooCommerce HPOS compatibility
        add_action('before_woocommerce_init', array($this, 'declare_woocommerce_compatibility'));
        
        // Initialize components
        add_action('plugins_loaded', array($this, 'load_components'), 5);
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Declare WooCommerce compatibility
     */
    public function declare_woocommerce_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once AIO_WC_PLUGIN_DIR . 'includes/class-persian-calendar.php';
        require_once AIO_WC_PLUGIN_DIR . 'includes/class-admin.php';
        require_once AIO_WC_PLUGIN_DIR . 'includes/class-woocommerce-hooks.php';
        require_once AIO_WC_PLUGIN_DIR . 'includes/class-cart-rules.php';
        require_once AIO_WC_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once AIO_WC_PLUGIN_DIR . 'includes/class-payment-gateways.php';
    }
    
    /**
     * Load components
     */
    public function load_components() {
        // Initialize admin - always load admin menu
        if (is_admin() && class_exists('AIO_WC_Admin')) {
            AIO_WC_Admin::get_instance();
        }
        
        // Initialize WooCommerce hooks
        if (class_exists('AIO_WC_WooCommerce_Hooks')) {
            AIO_WC_WooCommerce_Hooks::get_instance();
        }
        
        // Initialize cart rules
        if (class_exists('WooCommerce') && class_exists('AIO_WC_Cart_Rules')) {
            AIO_WC_Cart_Rules::get_instance();
        }
        
        // Initialize frontend
        if (class_exists('AIO_WC_Frontend')) {
            AIO_WC_Frontend::get_instance();
        }
        
        // Initialize payment gateways (only if WooCommerce is active)
        if (class_exists('WooCommerce') && class_exists('AIO_WC_Payment_Gateways')) {
            AIO_WC_Payment_Gateways::get_instance();
        }
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('aio-woocommerce', false, dirname(AIO_WC_PLUGIN_BASENAME) . '/languages');
    }
    
}

/**
 * Initialize plugin
 */
function aio_wc_init() {
    return AIO_WooCommerce::get_instance();
}

// Start the plugin - use earlier hook
add_action('plugins_loaded', 'aio_wc_init', 1);

/**
 * Activation hook
 */
register_activation_hook(__FILE__, 'aio_wc_activate');

function aio_wc_activate() {
    // Set default options
    $defaults = array(
        'persian_calendar_enabled' => 'yes',
        'convert_wordpress_dates'  => 'no',
        'convert_backend_dates'    => 'no',
        'convert_wordpress_datepickers' => 'no',
        'convert_woocommerce_dates' => 'no',
        'convert_woocommerce_datepickers' => 'no',
        'convert_date_pickers'     => 'no',
        'date_format'              => 'Y/m/d',
        'admin_font_choice'        => 'default',
        'admin_font_custom_name'   => '',
        'admin_font_custom_url'    => '',
        'cart_minimum_enabled'     => 'no',
        'cart_minimum_amount'      => 0,
        'cart_minimum_message'     => __('حداقل مبلغ سفارش {min_total} تومان است. مجموع فعلی {current_total} تومان می‌باشد.', 'aio-woocommerce'),
        'cart_rule_message'        => __('حداقل تعداد سفارش برای "{product}" برابر با {min_qty} عدد است.', 'aio-woocommerce'),
        'cart_rules'               => array(
            array(
                'price_min' => 0,
                'price_max' => 30000,
                'min_qty'   => 12,
            ),
            array(
                'price_min' => 30000,
                'price_max' => 50000,
                'min_qty'   => 6,
            ),
            array(
                'price_min' => 50000,
                'price_max' => '',
                'min_qty'   => 1,
            ),
        ),
        'cart_rule_excluded_categories' => array(),
        'cart_rule_excluded_tags'       => array(),
    );
    
    $current_settings = get_option('aio_wc_settings', null);
    $defaults_version_key = 'defaults_version';
    $defaults_version_value = AIO_WC_DEFAULTS_VERSION;
    
    if ($current_settings === null) {
        $defaults[$defaults_version_key] = $defaults_version_value;
        add_option('aio_wc_settings', $defaults);
        return;
    }
    
    $current_settings = is_array($current_settings) ? $current_settings : array();
    $has_new_defaults = isset($current_settings[$defaults_version_key]) && version_compare($current_settings[$defaults_version_key], $defaults_version_value, '>=');
    
    if (!$has_new_defaults) {
        $legacy_keys = array('persian_calendar_enabled', 'convert_wordpress_dates', 'convert_backend_dates', 'convert_date_pickers');
        $looks_like_legacy_defaults = true;
        foreach ($legacy_keys as $legacy_key) {
            $value = isset($current_settings[$legacy_key]) ? $current_settings[$legacy_key] : 'yes';
            if ($value === 'no') {
                $looks_like_legacy_defaults = false;
                break;
            }
        }
        
        if ($looks_like_legacy_defaults) {
            $current_settings = array_merge($current_settings, $defaults);
        } else {
            foreach ($defaults as $key => $value) {
                if (!array_key_exists($key, $current_settings)) {
                    $current_settings[$key] = $value;
                }
            }
        }
        
        $current_settings[$defaults_version_key] = $defaults_version_value;
        update_option('aio_wc_settings', $current_settings);
    }
}

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, 'aio_wc_deactivate');

function aio_wc_deactivate() {
    // Clean up if needed
}

/**
 * Uninstall hook - remove stored settings so a fresh install starts clean
 */
register_uninstall_hook(__FILE__, 'aio_wc_uninstall');

function aio_wc_uninstall() {
    $settings = get_option('aio_wc_settings', array());
    $should_wipe_everything = isset($settings['cleanup_on_delete']) ? $settings['cleanup_on_delete'] === 'yes' : false;
    
    delete_option('aio_wc_settings');
    
    if ($should_wipe_everything) {
        delete_option('woocommerce_aio_wc_zarinpal_settings');
        delete_option('woocommerce_aio_wc_zibal_settings');
    }
}