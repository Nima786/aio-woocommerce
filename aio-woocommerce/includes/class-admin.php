<?php
/**
 * Admin Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIO_WC_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register admin menu with high priority
        add_action('admin_menu', array($this, 'add_admin_menu'), 1);
        add_action('admin_init', array($this, 'register_settings'));
        // Ensure administrators always retain access (safety net after updates)
        add_action('admin_init', array($this, 'ensure_admin_access'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_aio_wc_save_settings_section', array($this, 'ajax_save_settings_section'));
        // Map custom capability dynamically from saved roles as a safety net
        add_filter('user_has_cap', array($this, 'filter_user_caps'), 10, 4);
        
        // Sync payment gateway settings after they're saved
        add_action('update_option_aio_wc_settings', array($this, 'sync_payment_gateway_settings_on_save'), 10, 2);
        
        // Add settings link to plugin row
        add_filter('plugin_action_links', array($this, 'add_plugin_action_links'), 10, 2);
        
        // Add body class to identify our admin page
        add_filter('admin_body_class', array($this, 'add_admin_body_class'));

        add_filter('upload_mimes', array($this, 'allow_font_mimes'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Check user capabilities (allow administrators even if mapping not saved yet)
        $cap = defined('AIO_WC_CAPABILITY') ? AIO_WC_CAPABILITY : 'manage_options';
        if (!(current_user_can($cap) || current_user_can('manage_options'))) {
            return;
        }
        
        // Add main menu page with icon
        $hook = add_menu_page(
            __('AIO WooCommerce', 'aio-woocommerce'),
            __('AIO WooCommerce', 'aio-woocommerce'),
            $cap,
            'aio-woocommerce',
            array($this, 'render_settings_page'),
            $this->get_menu_icon(),
            56
        );
        
        // Add submenu page (this will be the default page when clicking the main menu)
        add_submenu_page(
            'aio-woocommerce',
            __('Persian Calendar Settings', 'aio-woocommerce'),
            __('Persian Calendar', 'aio-woocommerce'),
            $cap,
            'aio-woocommerce',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Get menu icon (temporary - replace with custom icon later)
     * 
     * To replace with custom icon:
     * 1. Add your icon file to: assets/images/icon.svg (or .png)
     * 2. Update this method to return: AIO_WC_PLUGIN_URL . 'assets/images/icon.svg'
     * 
     * @return string Icon URL or dashicon class
     */
    private function get_menu_icon() {
        // Check if custom icon exists
        $custom_icon = AIO_WC_PLUGIN_DIR . 'assets/images/icon.svg';
        if (file_exists($custom_icon)) {
            return AIO_WC_PLUGIN_URL . 'assets/images/icon.svg';
        }
        
        // Temporary: Use dashicon for now (replace with custom icon later)
        // You can also return a data URI SVG or icon URL
        return 'dashicons-calendar-alt';
    }
    
    /**
     * Add settings link to plugin action links
     */
    public function add_plugin_action_links($links, $plugin_file) {
        // Check if this is our plugin
        $plugin_basename = defined('AIO_WC_PLUGIN_BASENAME') ? AIO_WC_PLUGIN_BASENAME : '';
        
        // Also check by file name
        if (strpos($plugin_file, 'aio-woocommerce.php') !== false || 
            $plugin_file === $plugin_basename ||
            strpos($plugin_file, 'AIO-Woocommerec') !== false ||
            strpos($plugin_file, 'AIO-Woocommerce') !== false) {
            
            $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=aio-woocommerce')) . '">' . esc_html__('Settings', 'aio-woocommerce') . '</a>';
            array_unshift($links, $settings_link);
        }
        
        return $links;
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('aio_wc_settings', 'aio_wc_settings', array($this, 'sanitize_settings'));
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        if (!is_array($input)) {
            $input = array();
        }
        
        $sanitized = array();
        $format_decimal = function($value) {
            if (function_exists('wc_format_decimal')) {
                return wc_format_decimal($value);
            }
            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed === '') {
                    return '';
                }
                $value = str_replace(',', '', $trimmed);
            }
            if ($value === null || $value === '') {
                return '';
            }
            return is_numeric($value) ? $value : 0;
        };
        
        // Calendar toggles
        $sanitized['persian_calendar_enabled'] = 'yes';
        
        $convert_wp_dates = 'no';
        if (isset($input['convert_wordpress_dates'])) {
            $convert_wp_dates = ($input['convert_wordpress_dates'] === 'yes') ? 'yes' : 'no';
        } elseif (isset($input['convert_backend_dates'])) {
            $convert_wp_dates = ($input['convert_backend_dates'] === 'yes') ? 'yes' : 'no';
        }
        $sanitized['convert_wordpress_dates'] = $convert_wp_dates;
        // Maintain legacy key for backward compatibility
        $sanitized['convert_backend_dates'] = $convert_wp_dates;
        
        $convert_wp_pickers = 'no';
        if (isset($input['convert_wordpress_datepickers'])) {
            $convert_wp_pickers = ($input['convert_wordpress_datepickers'] === 'yes') ? 'yes' : 'no';
        } elseif (isset($input['convert_date_pickers'])) {
            $convert_wp_pickers = ($input['convert_date_pickers'] === 'yes') ? 'yes' : 'no';
        }
        $sanitized['convert_wordpress_datepickers'] = $convert_wp_pickers;
        
        $convert_wc_dates = 'no';
        if (isset($input['convert_woocommerce_dates'])) {
            $convert_wc_dates = ($input['convert_woocommerce_dates'] === 'yes') ? 'yes' : 'no';
        } elseif (isset($input['convert_date_pickers'])) {
            $convert_wc_dates = ($input['convert_date_pickers'] === 'yes') ? 'yes' : 'no';
        }
        $sanitized['convert_woocommerce_dates'] = $convert_wc_dates;
        
        $convert_wc_pickers = 'no';
        if (isset($input['convert_woocommerce_datepickers'])) {
            $convert_wc_pickers = ($input['convert_woocommerce_datepickers'] === 'yes') ? 'yes' : 'no';
        } elseif (isset($input['convert_date_pickers'])) {
            $convert_wc_pickers = ($input['convert_date_pickers'] === 'yes') ? 'yes' : 'no';
        }
        $sanitized['convert_woocommerce_datepickers'] = $convert_wc_pickers;
        
        // Maintain legacy key for backwards compatibility
        $sanitized['convert_date_pickers'] = ($convert_wp_pickers === 'yes' || $convert_wc_pickers === 'yes') ? 'yes' : 'no';
        $sanitized['cleanup_on_delete'] = (isset($input['cleanup_on_delete']) && $input['cleanup_on_delete'] === 'yes') ? 'yes' : 'no';
        
        // Access control - allowed roles to manage the plugin
        // Only process allowed_roles if this section was submitted (avoid clobbering other saves)
        if (isset($input['allowed_roles']) && is_array($input['allowed_roles'])) {
            $allowed_roles = array();
            foreach ($input['allowed_roles'] as $role_key => $value) {
                if ($value === 'yes') {
                    $allowed_roles[] = sanitize_text_field($role_key);
                }
            }
            // Always keep administrators to avoid lockout
            if (!in_array('administrator', $allowed_roles, true)) {
                $allowed_roles[] = 'administrator';
            }
            $sanitized['allowed_roles'] = array_values(array_unique($allowed_roles));
        }
        
        // Calendar formats
        $sanitized['date_format']             = sanitize_text_field($input['date_format'] ?? 'Y/m/d');

        $font_presets = array_merge($this->get_admin_font_presets(), array('custom' => array()));
        $font_choice = isset($input['admin_font_choice']) ? sanitize_text_field($input['admin_font_choice']) : 'default';
        if (!array_key_exists($font_choice, $font_presets)) {
            $font_choice = 'default';
        }
        $sanitized['admin_font_choice'] = $font_choice;
        $sanitized['admin_font_custom_name'] = isset($input['admin_font_custom_name']) ? sanitize_text_field($input['admin_font_custom_name']) : '';
        $sanitized['admin_font_custom_url'] = isset($input['admin_font_custom_url']) ? esc_url_raw($input['admin_font_custom_url']) : '';
        if ('custom' !== $font_choice) {
            $sanitized['admin_font_custom_name'] = '';
            $sanitized['admin_font_custom_url'] = '';
        }
        
        // Cart minimum settings
        $sanitized['cart_minimum_enabled'] = (isset($input['cart_minimum_enabled']) && $input['cart_minimum_enabled'] === 'yes') ? 'yes' : 'no';
        $raw_cart_minimum = isset($input['cart_minimum_amount']) ? $format_decimal($input['cart_minimum_amount']) : 0;
        $sanitized['cart_minimum_amount']  = $raw_cart_minimum !== '' ? floatval($raw_cart_minimum) : 0;
        if ($sanitized['cart_minimum_amount'] < 0) {
            $sanitized['cart_minimum_amount'] = 0;
        }
        
        $sanitized['cart_minimum_message'] = isset($input['cart_minimum_message'])
            ? wp_kses_post($input['cart_minimum_message'])
            : '';
        $sanitized['cart_min_rules_enabled'] = (isset($input['cart_min_rules_enabled']) && $input['cart_min_rules_enabled'] === 'yes') ? 'yes' : 'no';
        
        // Quantity rule message
        $sanitized['cart_rule_message'] = isset($input['cart_rule_message'])
            ? wp_kses_post($input['cart_rule_message'])
            : '';
        
        // Maximum quantity rules - entire store
        $sanitized['cart_max_rule_all_enabled'] = (isset($input['cart_max_rule_all_enabled']) && $input['cart_max_rule_all_enabled'] === 'yes') ? 'yes' : 'no';
        $sanitized['cart_max_rule_all_qty'] = isset($input['cart_max_rule_all_qty']) ? max(1, intval($input['cart_max_rule_all_qty'])) : 1;
        
        // Maximum quantity rules - categories
        $sanitized['cart_max_rule_categories_enabled'] = (isset($input['cart_max_rule_categories_enabled']) && $input['cart_max_rule_categories_enabled'] === 'yes') ? 'yes' : 'no';
        $sanitized['cart_max_rule_categories_qty'] = isset($input['cart_max_rule_categories_qty']) ? max(1, intval($input['cart_max_rule_categories_qty'])) : 1;
        $sanitized['cart_max_rule_categories_ids'] = array();
        // Check if the field exists in input (even if empty) - this indicates user intentionally cleared it
        if (isset($input['cart_max_rule_categories_ids'])) {
            if (!empty($input['cart_max_rule_categories_ids']) && is_array($input['cart_max_rule_categories_ids'])) {
                foreach ($input['cart_max_rule_categories_ids'] as $category_id) {
                    $category_id = intval($category_id);
                    if ($category_id > 0) {
                        $sanitized['cart_max_rule_categories_ids'][] = $category_id;
                    }
                }
                $sanitized['cart_max_rule_categories_ids'] = array_values(array_unique($sanitized['cart_max_rule_categories_ids']));
            }
            // If field exists but is empty, $sanitized['cart_max_rule_categories_ids'] remains empty array (clears the setting)
        }
        
        // Maximum quantity rules - tags
        $sanitized['cart_max_rule_tags_enabled'] = (isset($input['cart_max_rule_tags_enabled']) && $input['cart_max_rule_tags_enabled'] === 'yes') ? 'yes' : 'no';
        $sanitized['cart_max_rule_tags_qty'] = isset($input['cart_max_rule_tags_qty']) ? max(1, intval($input['cart_max_rule_tags_qty'])) : 1;
        $sanitized['cart_max_rule_tags_ids'] = array();
        // Check if the field exists in input (even if empty) - this indicates user intentionally cleared it
        if (isset($input['cart_max_rule_tags_ids'])) {
            if (!empty($input['cart_max_rule_tags_ids']) && is_array($input['cart_max_rule_tags_ids'])) {
                foreach ($input['cart_max_rule_tags_ids'] as $tag_id) {
                    $tag_id = intval($tag_id);
                    if ($tag_id > 0) {
                        $sanitized['cart_max_rule_tags_ids'][] = $tag_id;
                    }
                }
                $sanitized['cart_max_rule_tags_ids'] = array_values(array_unique($sanitized['cart_max_rule_tags_ids']));
            }
            // If field exists but is empty, $sanitized['cart_max_rule_tags_ids'] remains empty array (clears the setting)
        }
        
        // Maximum quantity rules - products
        $sanitized['cart_max_rule_products_enabled'] = (isset($input['cart_max_rule_products_enabled']) && $input['cart_max_rule_products_enabled'] === 'yes') ? 'yes' : 'no';
        $sanitized['cart_max_rule_products_qty'] = isset($input['cart_max_rule_products_qty']) ? max(1, intval($input['cart_max_rule_products_qty'])) : 1;
        $sanitized['cart_max_rule_products_ids'] = array();
        if (!empty($input['cart_max_rule_products_ids'])) {
            $raw_ids = $input['cart_max_rule_products_ids'];
            if (is_array($raw_ids)) {
                $pieces = $raw_ids;
            } else {
                $pieces = preg_split('/[,،\s]+/', wp_kses_post($raw_ids));
            }
            if (is_array($pieces)) {
                foreach ($pieces as $piece) {
                    $piece = trim($piece);
                    if ($piece === '') {
                        continue;
                    }
                    $product_id = intval($piece);
                    if ($product_id > 0) {
                        $sanitized['cart_max_rule_products_ids'][] = $product_id;
                    }
                }
                $sanitized['cart_max_rule_products_ids'] = array_values(array_unique($sanitized['cart_max_rule_products_ids']));
            }
        }
        
        // Decode cart rules table (JSON string or array)
        $raw_rules = $input['cart_rules'] ?? array();
        
        // Debug logging
        error_log('🔍 sanitize_settings: cart_rules type: ' . gettype($raw_rules));
        if (is_string($raw_rules)) {
            error_log('🔍 cart_rules is string, length: ' . strlen($raw_rules));
            $decoded = json_decode(wp_unslash($raw_rules), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('❌ JSON decode error: ' . json_last_error_msg());
            }
            if (is_array($decoded)) {
                $raw_rules = $decoded;
                error_log('✅ cart_rules decoded to array with ' . count($raw_rules) . ' rules');
            } else {
                error_log('⚠️ cart_rules decoded but not an array');
            }
        } elseif (is_array($raw_rules)) {
            error_log('✅ cart_rules is already an array with ' . count($raw_rules) . ' rules');
        } else {
            error_log('⚠️ cart_rules is neither string nor array: ' . gettype($raw_rules));
        }
        
        $sanitized_rules = array();
        if (is_array($raw_rules)) {
            foreach ($raw_rules as $rule) {
                if (!is_array($rule)) {
                    continue;
                }
                
                $raw_price_min = isset($rule['price_min']) ? $format_decimal($rule['price_min']) : 0;
                $raw_price_max = isset($rule['price_max']) ? $format_decimal($rule['price_max']) : '';

                $price_min = $raw_price_min !== '' ? floatval($raw_price_min) : 0;
                $price_max = ($raw_price_max !== '' && $raw_price_max !== null) ? floatval($raw_price_max) : '';
                $min_qty   = isset($rule['min_qty']) ? intval($rule['min_qty']) : 1;
                
                if ($min_qty < 1) {
                    $min_qty = 1;
                }
                if ($price_max !== '' && $price_max < $price_min) {
                    $price_max = '';
                }
                
                $sanitized_rules[] = array(
                    'price_min' => $price_min,
                    'price_max' => $price_max,
                    'min_qty'   => $min_qty,
                );
            }
        }
        
        if (!empty($sanitized_rules)) {
            usort($sanitized_rules, function($a, $b) {
                if ($a['price_min'] === $b['price_min']) {
                    return 0;
                }
                return ($a['price_min'] < $b['price_min']) ? -1 : 1;
            });
        }
        
        $sanitized['cart_rules'] = $sanitized_rules;
        
        $sanitized['cart_max_rules'] = array_filter(array(
            ($sanitized['cart_max_rule_all_enabled'] === 'yes') ? array(
                'scope'   => 'all',
                'ids'     => array(),
                'max_qty' => $sanitized['cart_max_rule_all_qty'],
            ) : null,
            ($sanitized['cart_max_rule_categories_enabled'] === 'yes' && !empty($sanitized['cart_max_rule_categories_ids'])) ? array(
                'scope'   => 'categories',
                'ids'     => $sanitized['cart_max_rule_categories_ids'],
                'max_qty' => $sanitized['cart_max_rule_categories_qty'],
            ) : null,
            ($sanitized['cart_max_rule_tags_enabled'] === 'yes' && !empty($sanitized['cart_max_rule_tags_ids'])) ? array(
                'scope'   => 'tags',
                'ids'     => $sanitized['cart_max_rule_tags_ids'],
                'max_qty' => $sanitized['cart_max_rule_tags_qty'],
            ) : null,
            ($sanitized['cart_max_rule_products_enabled'] === 'yes' && !empty($sanitized['cart_max_rule_products_ids'])) ? array(
                'scope'   => 'products',
                'ids'     => $sanitized['cart_max_rule_products_ids'],
                'max_qty' => $sanitized['cart_max_rule_products_qty'],
            ) : null,
        ));
        
        // Exclusions - categories
        $sanitized['cart_rule_excluded_categories'] = array();
        // Check if the field exists in input (even if empty) - this indicates user intentionally cleared it
        if (isset($input['cart_rule_excluded_categories'])) {
            if (!empty($input['cart_rule_excluded_categories']) && is_array($input['cart_rule_excluded_categories'])) {
                foreach ($input['cart_rule_excluded_categories'] as $category_id) {
                    $category_id = intval($category_id);
                    if ($category_id > 0) {
                        $sanitized['cart_rule_excluded_categories'][] = $category_id;
                    }
                }
            }
            // If field exists but is empty, $sanitized['cart_rule_excluded_categories'] remains empty array (clears the setting)
        }
        
        // Exclusions - tags
        $sanitized['cart_rule_excluded_tags'] = array();
        // Check if the field exists in input (even if empty) - this indicates user intentionally cleared it
        if (isset($input['cart_rule_excluded_tags'])) {
            if (!empty($input['cart_rule_excluded_tags']) && is_array($input['cart_rule_excluded_tags'])) {
                foreach ($input['cart_rule_excluded_tags'] as $tag_id) {
                    $tag_id = intval($tag_id);
                    if ($tag_id > 0) {
                        $sanitized['cart_rule_excluded_tags'][] = $tag_id;
                    }
                }
            }
            // If field exists but is empty, $sanitized['cart_rule_excluded_tags'] remains empty array (clears the setting)
        }
        
        // Payment gateway settings - Zarinpal
        // Only process if payment gateway data is actually in the input
        // This prevents overwriting existing settings when saving other sections
        // Check if the key exists and is an array (even if it only contains 'enabled' => 'no')
        if (isset($input['payment_gateway_zarinpal']) && is_array($input['payment_gateway_zarinpal'])) {
            $zarinpal = $input['payment_gateway_zarinpal'];
            
            // Get current settings as fallback for missing fields
            $current_settings_for_fallback = get_option('aio_wc_settings', array());
            $current_zarinpal = isset($current_settings_for_fallback['payment_gateway_zarinpal']) && is_array($current_settings_for_fallback['payment_gateway_zarinpal']) 
                ? $current_settings_for_fallback['payment_gateway_zarinpal'] 
                : array();
            
            // Always set enabled explicitly based on input (critical for toggle persistence)
            $enabled_value = (isset($zarinpal['enabled']) && $zarinpal['enabled'] === 'yes') ? 'yes' : 'no';
            
            $sanitized['payment_gateway_zarinpal'] = array(
                'enabled'                   => $enabled_value,
                'title'                     => isset($zarinpal['title']) && $zarinpal['title'] !== '' ? sanitize_text_field($zarinpal['title']) : (isset($current_zarinpal['title']) && $current_zarinpal['title'] !== '' ? $current_zarinpal['title'] : __('پرداخت امن زرین پال', 'aio-woocommerce')),
                'description'               => isset($zarinpal['description']) ? wp_kses_post($zarinpal['description']) : (isset($current_zarinpal['description']) ? $current_zarinpal['description'] : ''),
                'merchant_id'               => isset($zarinpal['merchant_id']) ? sanitize_text_field($zarinpal['merchant_id']) : (isset($current_zarinpal['merchant_id']) ? $current_zarinpal['merchant_id'] : ''),
                'access_token'              => isset($zarinpal['access_token']) ? sanitize_text_field($zarinpal['access_token']) : (isset($current_zarinpal['access_token']) ? $current_zarinpal['access_token'] : ''),
                'sandbox_mode'              => (isset($zarinpal['sandbox_mode']) && $zarinpal['sandbox_mode'] === 'yes') ? 'yes' : 'no',
                'zarinpal_gate'             => isset($zarinpal['zarinpal_gate']) && $zarinpal['zarinpal_gate'] !== '' ? sanitize_text_field($zarinpal['zarinpal_gate']) : (isset($current_zarinpal['zarinpal_gate']) && $current_zarinpal['zarinpal_gate'] !== '' ? $current_zarinpal['zarinpal_gate'] : 'normal'),
                'commission_from'           => isset($zarinpal['commission_from']) && $zarinpal['commission_from'] !== '' ? sanitize_text_field($zarinpal['commission_from']) : (isset($current_zarinpal['commission_from']) && $current_zarinpal['commission_from'] !== '' ? $current_zarinpal['commission_from'] : (isset($current_zarinpal['commission_deduct_from']) && $current_zarinpal['commission_deduct_from'] !== '' ? $current_zarinpal['commission_deduct_from'] : 'merchant')),
                'success_message'           => isset($zarinpal['success_message']) ? wp_kses_post($zarinpal['success_message']) : (isset($current_zarinpal['success_message']) ? $current_zarinpal['success_message'] : __('با تشکر از شما سفارش شما با موفقیت پرداخت شد', 'aio-woocommerce')),
                'failed_message'            => isset($zarinpal['failed_message']) ? wp_kses_post($zarinpal['failed_message']) : (isset($current_zarinpal['failed_message']) ? $current_zarinpal['failed_message'] : __('پرداخت شما ناموفق بوده است. لطفاً مجدداً تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید', 'aio-woocommerce')),
                'post_purchase_description' => isset($zarinpal['post_purchase_description']) ? wp_kses_post($zarinpal['post_purchase_description']) : (isset($current_zarinpal['post_purchase_description']) ? $current_zarinpal['post_purchase_description'] : ''),
                'trust_logo_code'           => isset($zarinpal['trust_logo_code']) ? wp_kses_post($zarinpal['trust_logo_code']) : (isset($current_zarinpal['trust_logo_code']) ? $current_zarinpal['trust_logo_code'] : ''),
            );
        }
        // If payment_gateway_zarinpal is not in input, don't set it in sanitized - existing settings will be preserved
        
        // Payment gateway settings - Zibal
        // Only process if payment gateway data is actually in the input
        // This prevents overwriting existing settings when saving other sections
        // Check if the key exists and is an array (even if it only contains 'enabled' => 'no')
        if (isset($input['payment_gateway_zibal']) && is_array($input['payment_gateway_zibal'])) {
            $zibal = $input['payment_gateway_zibal'];
            
            // Get current settings as fallback for missing fields
            $current_settings_for_fallback = get_option('aio_wc_settings', array());
            $current_zibal = isset($current_settings_for_fallback['payment_gateway_zibal']) && is_array($current_settings_for_fallback['payment_gateway_zibal']) 
                ? $current_settings_for_fallback['payment_gateway_zibal'] 
                : array();
            
            $sanitized['payment_gateway_zibal'] = array(
                'enabled'         => (isset($zibal['enabled']) && $zibal['enabled'] === 'yes') ? 'yes' : 'no',
                'title'           => !empty($zibal['title']) ? sanitize_text_field($zibal['title']) : (isset($current_zibal['title']) && !empty($current_zibal['title']) ? $current_zibal['title'] : __('زیبال', 'aio-woocommerce')),
                'description'     => isset($zibal['description']) ? wp_kses_post($zibal['description']) : (isset($current_zibal['description']) ? $current_zibal['description'] : __('پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه زیبال', 'aio-woocommerce')),
                'merchant_id'     => isset($zibal['merchant_id']) ? sanitize_text_field($zibal['merchant_id']) : (isset($current_zibal['merchant_id']) ? $current_zibal['merchant_id'] : ''),
                'sandbox_mode'    => (isset($zibal['sandbox_mode']) && $zibal['sandbox_mode'] === 'yes') ? 'yes' : 'no',
                'success_message' => isset($zibal['success_message']) ? wp_kses_post($zibal['success_message']) : (isset($current_zibal['success_message']) ? $current_zibal['success_message'] : __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'aio-woocommerce')),
                'failed_message'  => isset($zibal['failed_message']) ? wp_kses_post($zibal['failed_message']) : (isset($current_zibal['failed_message']) ? $current_zibal['failed_message'] : __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'aio-woocommerce')),
            );
        }
        // If payment_gateway_zibal is not in input, don't set it in sanitized - let array_merge preserve existing
        
        $existing_settings_for_defaults = get_option('aio_wc_settings', array());
        if (isset($existing_settings_for_defaults['defaults_version'])) {
            $sanitized['defaults_version'] = sanitize_text_field($existing_settings_for_defaults['defaults_version']);
        } else {
            $sanitized['defaults_version'] = defined('AIO_WC_DEFAULTS_VERSION') ? AIO_WC_DEFAULTS_VERSION : AIO_WC_VERSION;
        }
        
        return $sanitized;
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Always load admin CSS on all admin pages for menu styling
        // Load with high priority to ensure it overrides WordPress defaults
        wp_enqueue_style(
            'aio-wc-admin',
            AIO_WC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AIO_WC_VERSION,
            'all'
        );
        
        // Load font settings on all admin pages
        $settings = get_option('aio_wc_settings', array());
        $font_presets = $this->get_admin_font_presets();
        $font_choice = isset($settings['admin_font_choice']) ? $settings['admin_font_choice'] : 'default';
        $font_family = '';
        $font_face_css = '';

        if ($font_choice && $font_choice !== 'default') {
            if ('custom' === $font_choice) {
                $custom_name = isset($settings['admin_font_custom_name']) ? trim($settings['admin_font_custom_name']) : '';
                $custom_url  = isset($settings['admin_font_custom_url']) ? esc_url($settings['admin_font_custom_url']) : '';
                if ($custom_name && $custom_url) {
                    $font_family = '"' . esc_attr($custom_name) . '"';
                    $format = $this->detect_font_format($custom_url);
                    $font_face_css = "@font-face{font-family:{$font_family};font-style:normal;font-weight:400;font-display:swap;src:url('{$custom_url}') format('{$format}');}";
                }
            } elseif (isset($font_presets[$font_choice])) {
                $preset = $font_presets[$font_choice];
                $font_family = isset($preset['family']) ? $preset['family'] : '';
                if (!empty($preset['css'])) {
                    wp_enqueue_style(
                        'aio-wc-font-' . $font_choice,
                        $preset['css'],
                        array(),
                        AIO_WC_VERSION
                    );
                }
            }
        }

        if ($font_family) {
            $inline_font_css = '';
            if ($font_face_css) {
                $inline_font_css .= $font_face_css;
            }
            // Preserve icon fonts first (admin bar, dashicons, etc.)
            $inline_font_css .= '#wpadminbar i,#wpadminbar .dashicons,#wpadminbar [class*="dashicon"],#wpadminbar .ab-icon,#wpadminbar [class*="icon"]{font-family:"dashicons" !important;}';
            $inline_font_css .= 'body.wp-admin i.dashicons,body.wp-admin .dashicons,body.wp-admin [class*="dashicon"]{font-family:"dashicons" !important;}';
            // Apply font to entire WordPress admin dashboard, excluding icon elements
            $inline_font_css .= 'body.wp-admin{font-family:' . $font_family . ',sans-serif !important;}';
            $inline_font_css .= 'body.wp-admin *:not(i):not(.dashicons):not([class*="dashicon"]):not([class*="CodeMirror"]):not([class*="ace_"]):not(code):not(pre){font-family:' . $font_family . ',sans-serif !important;}';
            // Also apply to plugin-specific areas
            $inline_font_css .= '.aio-wc-admin, .aio-wc-admin *:not(i){font-family:' . $font_family . ',sans-serif;}';
            $inline_font_css .= '.wrap.aio-wc-admin-wrap, .wrap.aio-wc-admin-wrap *:not(i){font-family:' . $font_family . ',sans-serif;}';
            wp_add_inline_style('aio-wc-admin', $inline_font_css);
        }
        
        // Only load scripts on plugin pages
        if (strpos($hook, 'aio-woocommerce') === false) {
            return;
        }
        
        wp_enqueue_media();
        $cart_rules = isset($settings['cart_rules']) && is_array($settings['cart_rules']) ? $settings['cart_rules'] : array();
        $cart_rules_normalized = array_map(function ($rule) {
            $rule = is_array($rule) ? $rule : array();
            if (!isset($rule['price_min'])) {
                $rule['price_min'] = 0;
            }
            if (!isset($rule['price_max'])) {
                $rule['price_max'] = '';
            }
            if (!isset($rule['min_qty']) || intval($rule['min_qty']) < 1) {
                $rule['min_qty'] = 1;
            }
            return array(
                'price_min' => is_numeric($rule['price_min']) ? floatval($rule['price_min']) : 0,
                'price_max' => ($rule['price_max'] === '' || $rule['price_max'] === null) ? '' : floatval($rule['price_max']),
                'min_qty'   => intval($rule['min_qty']),
            );
        }, $cart_rules);

        $cart_rule_excluded_categories = isset($settings['cart_rule_excluded_categories']) && is_array($settings['cart_rule_excluded_categories']) ? $settings['cart_rule_excluded_categories'] : array();
        $cart_rule_excluded_tags = isset($settings['cart_rule_excluded_tags']) && is_array($settings['cart_rule_excluded_tags']) ? $settings['cart_rule_excluded_tags'] : array();

        $max_rule_all_enabled = (isset($settings['cart_max_rule_all_enabled']) && $settings['cart_max_rule_all_enabled'] === 'yes') ? 'yes' : 'no';
        $max_rule_all_qty = isset($settings['cart_max_rule_all_qty']) ? max(1, intval($settings['cart_max_rule_all_qty'])) : 1;

        $max_rule_categories_enabled = (isset($settings['cart_max_rule_categories_enabled']) && $settings['cart_max_rule_categories_enabled'] === 'yes') ? 'yes' : 'no';
        $max_rule_categories_qty = isset($settings['cart_max_rule_categories_qty']) ? max(1, intval($settings['cart_max_rule_categories_qty'])) : 1;
        $max_rule_categories_ids = isset($settings['cart_max_rule_categories_ids']) && is_array($settings['cart_max_rule_categories_ids'])
            ? array_values(array_filter(array_map('intval', $settings['cart_max_rule_categories_ids']), function ($value) {
                return $value > 0;
            }))
            : array();

        $max_rule_tags_enabled = (isset($settings['cart_max_rule_tags_enabled']) && $settings['cart_max_rule_tags_enabled'] === 'yes') ? 'yes' : 'no';
        $max_rule_tags_qty = isset($settings['cart_max_rule_tags_qty']) ? max(1, intval($settings['cart_max_rule_tags_qty'])) : 1;
        $max_rule_tags_ids = isset($settings['cart_max_rule_tags_ids']) && is_array($settings['cart_max_rule_tags_ids'])
            ? array_values(array_filter(array_map('intval', $settings['cart_max_rule_tags_ids']), function ($value) {
                return $value > 0;
            }))
            : array();

        $max_rule_products_enabled = (isset($settings['cart_max_rule_products_enabled']) && $settings['cart_max_rule_products_enabled'] === 'yes') ? 'yes' : 'no';
        $max_rule_products_qty = isset($settings['cart_max_rule_products_qty']) ? max(1, intval($settings['cart_max_rule_products_qty'])) : 1;
        $max_rule_products_ids = isset($settings['cart_max_rule_products_ids']) && is_array($settings['cart_max_rule_products_ids'])
            ? array_values(array_filter(array_map('intval', $settings['cart_max_rule_products_ids']), function ($value) {
                return $value > 0;
            }))
            : array();
        $max_rule_products_input = !empty($max_rule_products_ids) ? implode(', ', $max_rule_products_ids) : '';

        $all_product_categories = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ));

        $all_product_tags = get_terms(array(
            'taxonomy'   => 'product_tag',
            'hide_empty' => false,
        ));
        
        // Enqueue admin tabs script (for tab navigation)
        wp_enqueue_script(
            'aio-wc-admin-tabs',
            AIO_WC_PLUGIN_URL . 'assets/js/admin-tabs.js',
            array(),
            AIO_WC_VERSION,
            true
        );
        
        wp_enqueue_script(
            'aio-wc-admin-general-settings',
            AIO_WC_PLUGIN_URL . 'assets/js/admin-general-settings.js',
            array('media-editor'),
            AIO_WC_VERSION,
            true
        );
        
        // Enqueue section save script
        wp_enqueue_script(
            'aio-wc-admin-section-save',
            AIO_WC_PLUGIN_URL . 'assets/js/admin-section-save.js',
            array(),
            AIO_WC_VERSION,
            true
        );
        
        wp_localize_script('aio-wc-admin-section-save', 'aioWcSectionSave', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('aio_wc_settings_nonce'),
            'action'   => 'aio_wc_save_settings_section'
        ));
        
        // Simple script to ensure toggle checkboxes match their rendered state on page load
        wp_add_inline_script('aio-wc-admin-section-save', '
            (function() {
                function syncToggleStates() {
                    var checkboxes = document.querySelectorAll("#general-settings input.aio-wc-toggle__input[id^=\"allowed_role_\"]");
                    checkboxes.forEach(function(checkbox) {
                        // The checkbox should already have the correct checked state from PHP
                        // Just trigger change event to update visual toggle if needed
                        if (checkbox.checked) {
                            checkbox.dispatchEvent(new Event("change", { bubbles: true }));
                        }
                    });
                }
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", syncToggleStates);
                } else {
                    syncToggleStates();
                }
                // Also sync when tab becomes visible
                setTimeout(function() {
                    var tab = document.getElementById("general-settings");
                    if (tab) {
                        var observer = new MutationObserver(function() {
                            if (window.getComputedStyle(tab).display !== "none") {
                                setTimeout(syncToggleStates, 50);
                            }
                        });
                        observer.observe(tab, { attributes: true, attributeFilter: ["style"] });
                    }
                }, 500);
            })();
        ', 'after');
        
        // Enqueue cart rules manager
        wp_enqueue_script(
            'aio-wc-admin-cart-rules',
            AIO_WC_PLUGIN_URL . 'assets/js/admin-cart-rules.js',
            array(),
            AIO_WC_VERSION,
            true
        );
        
        wp_localize_script('aio-wc-admin-cart-rules', 'aioWcCartRulesData', array(
            'rules'        => $cart_rules_normalized,
            'i18nRemove'   => __('Remove rule', 'aio-woocommerce'),
            'thousand_sep' => function_exists('wc_get_price_thousand_separator') ? wc_get_price_thousand_separator() : ',',
            'decimal_sep'  => function_exists('wc_get_price_decimal_separator') ? wc_get_price_decimal_separator() : '.',
            'decimals'     => function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 0,
        ));
    }
    
    /**
     * AJAX save settings section (saves entire form)
     */
    public function ajax_save_settings_section() {
        // Set error handler to catch fatal errors and return JSON instead of blank page
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            if (error_reporting() === 0) {
                return false; // Suppress @-suppressed errors
            }
            error_log("AIO WC AJAX Error: [$errno] $errstr in $errfile on line $errline");
            ob_clean();
            wp_send_json_error(array(
                'message' => __('A server error occurred. Please check your error logs.', 'aio-woocommerce'),
                'error' => $errstr
            ));
            exit;
        }, E_ALL & ~E_NOTICE & ~E_WARNING);
        
        ob_start();
        $buffer_flushed = false;
        $flush_ajax_buffer = function() use (&$buffer_flushed) {
            if ($buffer_flushed) {
                return;
            }
            $buffer_flushed = true;
            $buffer = ob_get_clean();
            if ($buffer !== false) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    error_log('AIO WC Admin AJAX: unexpected output during save: ' . mb_substr($trimmed, 0, 500));
                }
            }
        };

        try {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aio_wc_settings_nonce')) {
                $flush_ajax_buffer();
            wp_send_json_error(array('message' => __('Security check failed.', 'aio-woocommerce')));
            return;
        }
        
        $cap = defined('AIO_WC_CAPABILITY') ? AIO_WC_CAPABILITY : 'manage_options';
        if (!(current_user_can($cap) || current_user_can('manage_options'))) {
            $flush_ajax_buffer();
            wp_send_json_error(array('message' => __('Permission denied.', 'aio-woocommerce')));
            return;
        }
        
        $input = isset($_POST['aio_wc_settings']) ? wp_unslash($_POST['aio_wc_settings']) : array();
        if (!is_array($input)) {
            $input = array();
        }
        
        // Debug: Log received cart_rules data
        if (isset($input['cart_rules'])) {
            error_log('🔍 AJAX received cart_rules: ' . (is_string($input['cart_rules']) ? 'string (' . strlen($input['cart_rules']) . ' chars)' : gettype($input['cart_rules'])));
            if (is_string($input['cart_rules'])) {
                error_log('🔍 cart_rules value (first 200 chars): ' . substr($input['cart_rules'], 0, 200));
            }
        } else {
            error_log('⚠️ cart_rules NOT in $_POST input!');
        }
        
        // Sanitize incoming data
        $sanitized = $this->sanitize_settings($input);
        
        // Get current settings to preserve anything not being updated
        $current_settings = get_option('aio_wc_settings', array());
        
        // Merge settings: only update what's in sanitized, preserve the rest
        // This is critical for payment gateways - if they're not in the input, don't overwrite them
        // For nested arrays (like payment_gateway_zarinpal), do a recursive merge to preserve nested values
        $settings = $current_settings;
        foreach ($sanitized as $key => $value) {
            // Special case: allowed_roles should always be replaced completely, not merged
            // This ensures that when saving Access Control, only the roles in the current save are kept
            if ($key === 'allowed_roles' && is_array($value)) {
                $settings[$key] = $value;
                continue;
            }
            
            if (is_array($value) && isset($current_settings[$key]) && is_array($current_settings[$key])) {
                // Check if this is a simple array (like excluded_tags) or a nested object (like payment_gateway_zarinpal)
                // Simple arrays should be replaced, not merged (so empty arrays clear the setting)
                // Nested objects should be merged to preserve values that aren't being updated
                $is_nested_object = false;
                foreach ($value as $sub_key => $sub_value) {
                    if (!is_numeric($sub_key) && !is_int($sub_key)) {
                        $is_nested_object = true;
                        break;
                    }
                }
                
                if ($is_nested_object) {
                    // For nested objects (like payment_gateway_zarinpal), merge recursively
                    $settings[$key] = array_merge($current_settings[$key], $value);
                } else {
                    // For simple arrays (like excluded_tags), replace completely
                    // This allows empty arrays to clear the setting
                    $settings[$key] = $value;
                }
            } else {
                // For non-arrays or new keys, just set the value
                $settings[$key] = $value;
            }
        }
        
        // If nothing changed, still return success for UX consistency
        if ($current_settings == $settings) {
            $flush_ajax_buffer();
            wp_send_json_success(array(
                'message'  => __('Settings already up to date.', 'aio-woocommerce'),
                'settings' => $settings
            ));
            return;
        }
        
        $result = update_option('aio_wc_settings', $settings);
        
        // Clear any object cache that might be caching the option
        wp_cache_delete('aio_wc_settings', 'options');
        wp_cache_delete('alloptions', 'options');
        
        // Update role capabilities based on allowed_roles
        if (isset($settings['allowed_roles']) && is_array($settings['allowed_roles'])) {
            $this->apply_allowed_roles_caps($settings['allowed_roles']);
        }
        
        // Sync payment gateway settings to WooCommerce after saving
        // This ensures WooCommerce immediately recognizes the gateway state
        // Always sync, even if payment gateway settings weren't in this save (to handle updates from other sources)
        $this->sync_payment_gateway_settings_to_wc($settings);
        
        // Force WooCommerce to reload payment gateways by clearing any caches
        if (function_exists('WC')) {
            // Clear payment gateway cache if it exists
            delete_transient('woocommerce_gateway_' . 'aio_wc_zarinpal');
            delete_transient('woocommerce_gateway_' . 'aio_wc_zibal');
            
            // Clear WooCommerce payment gateway options cache
            wp_cache_delete('woocommerce_gateway_aio_wc_zarinpal', 'options');
            wp_cache_delete('woocommerce_gateway_aio_wc_zibal', 'options');
            
            // Reload payment gateways to ensure they pick up the new settings
            WC()->payment_gateways()->init();
        }
        
            // Treat no-op updates as success for better UX
            $flush_ajax_buffer();
            if ($result) {
            wp_send_json_success(array(
                    'message'  => __('Settings saved successfully.', 'aio-woocommerce'),
                    'settings' => $settings,
            ));
        } else {
                wp_send_json_success(array(
                    'message'  => __('Settings already up to date.', 'aio-woocommerce'),
                    'settings' => $settings,
                ));
            }
        } catch (Exception $e) {
            $flush_ajax_buffer();
            error_log('AIO WC AJAX Exception: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('An error occurred while saving settings.', 'aio-woocommerce'),
                'error' => $e->getMessage()
            ));
        } finally {
            // Restore default error handler
            restore_error_handler();
        }
    }
    
    /**
     * Apply capability to selected roles.
     *
     * @param array $allowed_roles
     * @return void
     */
    private function apply_allowed_roles_caps($allowed_roles) {
        if (!function_exists('get_editable_roles')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        $all_roles = get_editable_roles();
        $cap = defined('AIO_WC_CAPABILITY') ? AIO_WC_CAPABILITY : 'manage_options';
        foreach ($all_roles as $role_key => $role_data) {
            $role = get_role($role_key);
            if (!$role) {
                continue;
            }
            if (in_array($role_key, $allowed_roles, true)) {
                if (!$role->has_cap($cap)) {
                    $role->add_cap($cap);
                }
            } else {
                if ($role_key !== 'administrator' && $role->has_cap($cap)) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
    
    /**
     * Dynamically grant the plugin capability based on saved roles and admin status.
     * Acts as a safety net so changes take effect immediately without relying on role option caches.
     *
     * @param array $allcaps All capabilities for the user.
     * @param array $caps    Primitive caps being checked.
     * @param array $args    [0] requested cap, [1] user ID, ...
     * @param WP_User $user  The WP_User instance.
     * @return array
     */
    public function filter_user_caps( $allcaps, $caps, $args, $user ) {
        try {
            $requested = isset($args[0]) ? $args[0] : '';
            $plugin_cap = defined('AIO_WC_CAPABILITY') ? AIO_WC_CAPABILITY : 'manage_options';
            
            if ($requested !== $plugin_cap) {
                return $allcaps;
            }
            
            // Admins always pass
            if ( isset($allcaps['manage_options']) && $allcaps['manage_options'] ) {
                $allcaps[$plugin_cap] = true;
                return $allcaps;
            }
            
            // Check roles against saved mapping
            $settings = get_option('aio_wc_settings', array());
            $allowed = isset($settings['allowed_roles']) && is_array($settings['allowed_roles']) ? $settings['allowed_roles'] : array('administrator');
            $user_roles = is_array( $user->roles ?? null ) ? $user->roles : array();
            if ( array_intersect( $allowed, $user_roles ) ) {
                $allcaps[$plugin_cap] = true;
            }
        } catch ( \Throwable $e ) {
            // Fail open for admins only
            if ( isset($allcaps['manage_options']) && $allcaps['manage_options'] ) {
                $allcaps[ $plugin_cap ] = true;
            }
        }
        return $allcaps;
    }
    
    /**
     * Ensure administrators always have access capability.
     */
    public function ensure_admin_access() {
        if (!defined('AIO_WC_CAPABILITY')) {
            return;
        }
        $admin = get_role('administrator');
        if ($admin && !$admin->has_cap(AIO_WC_CAPABILITY)) {
            $admin->add_cap(AIO_WC_CAPABILITY);
        }
    }
    
    /**
     * Add body class to admin page
     */
    public function add_admin_body_class($classes) {
        // Check if we're on our plugin's admin page
        $screen = get_current_screen();
        if ($screen) {
            // WordPress automatically adds 'toplevel_page_{slug}' for top-level pages
            if (strpos($screen->id, 'aio-woocommerce') !== false || 
                (isset($_GET['page']) && $_GET['page'] === 'aio-woocommerce')) {
                $classes .= ' toplevel_page_aio-woocommerce bricks_page_aio-woocommerce';
            }
        } elseif (isset($_GET['page']) && $_GET['page'] === 'aio-woocommerce') {
            // Fallback: check page parameter directly
            $classes .= ' toplevel_page_aio-woocommerce bricks_page_aio-woocommerce';
        }
        return $classes;
    }
    
    /**
     * Helper: load an admin view partial
     */
    private function render_view($template, $vars = array()) {
        $file = AIO_WC_PLUGIN_DIR . 'admin/views/' . $template . '.php';
        if (!file_exists($file)) {
            return;
        }
        if (!empty($vars)) {
            extract($vars, EXTR_SKIP);
        }
        include $file;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings = get_option('aio_wc_settings', array());
        
        $persian_calendar_enabled = (isset($settings['persian_calendar_enabled']) && $settings['persian_calendar_enabled'] === 'yes') ? 'yes' : 'no';
        if (isset($settings['convert_wordpress_dates'])) {
            $convert_wordpress_dates = $settings['convert_wordpress_dates'] === 'yes' ? 'yes' : 'no';
        } else {
            $convert_wordpress_dates = (isset($settings['convert_backend_dates']) && $settings['convert_backend_dates'] === 'yes') ? 'yes' : 'no';
        }
        
        if (isset($settings['convert_wordpress_datepickers'])) {
            $convert_wordpress_datepickers = $settings['convert_wordpress_datepickers'] === 'yes' ? 'yes' : 'no';
        } else {
            $convert_wordpress_datepickers = (isset($settings['convert_date_pickers']) && $settings['convert_date_pickers'] === 'yes') ? 'yes' : 'no';
        }
        
        if (isset($settings['convert_woocommerce_dates'])) {
            $convert_woocommerce_dates = $settings['convert_woocommerce_dates'] === 'yes' ? 'yes' : 'no';
        } else {
            $convert_woocommerce_dates = (isset($settings['convert_date_pickers']) && $settings['convert_date_pickers'] === 'yes') ? 'yes' : 'no';
        }
        
        if (isset($settings['convert_woocommerce_datepickers'])) {
            $convert_woocommerce_datepickers = $settings['convert_woocommerce_datepickers'] === 'yes' ? 'yes' : 'no';
        } else {
            $convert_woocommerce_datepickers = (isset($settings['convert_date_pickers']) && $settings['convert_date_pickers'] === 'yes') ? 'yes' : 'no';
        }
        $date_format = isset($settings['date_format']) && !empty($settings['date_format']) ? $settings['date_format'] : 'Y/m/d';
        
        $cart_minimum_enabled = (isset($settings['cart_minimum_enabled']) && $settings['cart_minimum_enabled'] === 'yes') ? 'yes' : 'no';
        $cart_min_rules_enabled = (isset($settings['cart_min_rules_enabled']) && $settings['cart_min_rules_enabled'] === 'yes') ? 'yes' : 'no';
        $cart_minimum_amount = isset($settings['cart_minimum_amount']) ? floatval($settings['cart_minimum_amount']) : 0;
        $cart_minimum_message = !empty($settings['cart_minimum_message']) ? $settings['cart_minimum_message'] : __('حداقل مبلغ سفارش {min_total} تومان است. مجموع فعلی {current_total} تومان می‌باشد.', 'aio-woocommerce');
        $cart_rule_message = !empty($settings['cart_rule_message']) ? $settings['cart_rule_message'] : __('مقدار سفارش برای {product} به حداقل مقدار {min_qty} عدد تنظیم شد.', 'aio-woocommerce');
        $cart_rules = isset($settings['cart_rules']) && is_array($settings['cart_rules']) ? $settings['cart_rules'] : array();
        $cart_rule_excluded_categories = isset($settings['cart_rule_excluded_categories']) && is_array($settings['cart_rule_excluded_categories']) ? $settings['cart_rule_excluded_categories'] : array();
        $cart_rule_excluded_tags = isset($settings['cart_rule_excluded_tags']) && is_array($settings['cart_rule_excluded_tags']) ? $settings['cart_rule_excluded_tags'] : array();

        $all_product_categories = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ));

        $all_product_tags = get_terms(array(
            'taxonomy'   => 'product_tag',
            'hide_empty' => false,
        ));

        $categories_list = !is_wp_error($all_product_categories) ? $all_product_categories : array();
        $tags_list = !is_wp_error($all_product_tags) ? $all_product_tags : array();
        
        $price_decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 0;
        $cart_minimum_display_value = number_format_i18n($cart_minimum_amount, $price_decimals);
        if (function_exists('wc_format_decimal')) {
            $cart_minimum_raw_value = wc_format_decimal($cart_minimum_amount);
        } else {
            $cart_minimum_raw_value = is_numeric($cart_minimum_amount) ? (string) $cart_minimum_amount : '';
        }
        
        $cart_rules_normalized = array_map(function ($rule) {
            $rule = is_array($rule) ? $rule : array();
            if (!isset($rule['price_min'])) {
                $rule['price_min'] = 0;
            }
            if (!isset($rule['price_max'])) {
                $rule['price_max'] = '';
            }
            if (!isset($rule['min_qty']) || intval($rule['min_qty']) < 1) {
                $rule['min_qty'] = 1;
            }
            return array(
                'price_min' => $rule['price_min'],
                'price_max' => $rule['price_max'],
                'min_qty'   => $rule['min_qty'],
            );
        }, $cart_rules);

        $max_rule_all_enabled = (isset($settings['cart_max_rule_all_enabled']) && $settings['cart_max_rule_all_enabled'] === 'yes') ? 'yes' : 'no';
        $max_rule_all_qty = isset($settings['cart_max_rule_all_qty']) ? max(1, intval($settings['cart_max_rule_all_qty'])) : 1;

        $max_rule_categories_enabled = (isset($settings['cart_max_rule_categories_enabled']) && $settings['cart_max_rule_categories_enabled'] === 'yes') ? 'yes' : 'no';
        $max_rule_categories_qty = isset($settings['cart_max_rule_categories_qty']) ? max(1, intval($settings['cart_max_rule_categories_qty'])) : 1;
        $max_rule_categories_ids = isset($settings['cart_max_rule_categories_ids']) && is_array($settings['cart_max_rule_categories_ids'])
            ? array_values(array_filter(array_map('intval', $settings['cart_max_rule_categories_ids']), function ($value) {
                return $value > 0;
            }))
            : array();

        $max_rule_tags_enabled = (isset($settings['cart_max_rule_tags_enabled']) && $settings['cart_max_rule_tags_enabled'] === 'yes') ? 'yes' : 'no';
        $max_rule_tags_qty = isset($settings['cart_max_rule_tags_qty']) ? max(1, intval($settings['cart_max_rule_tags_qty'])) : 1;
        $max_rule_tags_ids = isset($settings['cart_max_rule_tags_ids']) && is_array($settings['cart_max_rule_tags_ids'])
            ? array_values(array_filter(array_map('intval', $settings['cart_max_rule_tags_ids']), function ($value) {
                return $value > 0;
            }))
            : array();

        $max_rule_products_enabled = (isset($settings['cart_max_rule_products_enabled']) && $settings['cart_max_rule_products_enabled'] === 'yes') ? 'yes' : 'no';
        $max_rule_products_qty = isset($settings['cart_max_rule_products_qty']) ? max(1, intval($settings['cart_max_rule_products_qty'])) : 1;
        $max_rule_products_ids_option = isset($settings['cart_max_rule_products_ids']) ? $settings['cart_max_rule_products_ids'] : array();
        if (is_string($max_rule_products_ids_option)) {
            $max_rule_products_ids = array_values(array_filter(array_map('intval', preg_split('/[,،\s]+/', $max_rule_products_ids_option)), function ($value) {
                return $value > 0;
            }));
        } elseif (is_array($max_rule_products_ids_option)) {
            $max_rule_products_ids = array_values(array_filter(array_map('intval', $max_rule_products_ids_option), function ($value) {
                return $value > 0;
            }));
        } else {
            $max_rule_products_ids = array();
        }
        $max_rule_products_input = !empty($max_rule_products_ids) ? implode(', ', $max_rule_products_ids) : '';

        // Get active tab from URL
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'persian-calendar';
        
        $sidebar_context = array(
            'title' => esc_html__('AIO WooCommerce', 'aio-woocommerce'),
            'links' => array(
                array('id' => 'persian-calendar', 'label' => esc_html__('Persian Calendar', 'aio-woocommerce'), 'active' => $active_tab === 'persian-calendar'),
                array('id' => 'cart-rules', 'label' => esc_html__('Cart, Min & Max Rules', 'aio-woocommerce'), 'active' => $active_tab === 'cart-rules'),
                array('id' => 'payment-gateways', 'label' => esc_html__('Payment Gateways', 'aio-woocommerce'), 'active' => $active_tab === 'payment-gateways'),
                array('id' => 'general-settings', 'label' => esc_html__('Misc & Cleanup', 'aio-woocommerce'), 'active' => $active_tab === 'general-settings'),
            ),
        );

        $persian_tab_context = array(
            'persian_calendar_enabled' => $persian_calendar_enabled,
            'convert_wordpress_dates'       => $convert_wordpress_dates,
            'convert_wordpress_datepickers' => $convert_wordpress_datepickers,
            'convert_woocommerce_dates'     => $convert_woocommerce_dates,
            'convert_woocommerce_datepickers' => $convert_woocommerce_datepickers,
            'date_format'              => $date_format,
            'woocommerce_active'       => class_exists('WooCommerce'),
        );

        $cart_rules_tab_context = array(
            'cart_minimum_enabled'          => $cart_minimum_enabled,
            'cart_min_rules_enabled'        => $cart_min_rules_enabled,
            'cart_minimum_display_value'    => $cart_minimum_display_value,
            'cart_minimum_raw_value'        => $cart_minimum_raw_value,
            'cart_minimum_message'          => $cart_minimum_message,
            'cart_rules_normalized'         => $cart_rules_normalized,
            'cart_rule_message'             => $cart_rule_message,
            'cart_rule_excluded_categories' => $cart_rule_excluded_categories,
            'cart_rule_excluded_tags'       => $cart_rule_excluded_tags,
            'categories'                    => $categories_list,
            'tags'                          => $tags_list,
            'max_rule_all_enabled'          => $max_rule_all_enabled,
            'max_rule_all_qty'              => $max_rule_all_qty,
            'max_rule_categories_enabled'   => $max_rule_categories_enabled,
            'max_rule_categories_qty'       => $max_rule_categories_qty,
            'max_rule_categories_ids'       => $max_rule_categories_ids,
            'max_rule_tags_enabled'         => $max_rule_tags_enabled,
            'max_rule_tags_qty'             => $max_rule_tags_qty,
            'max_rule_tags_ids'             => $max_rule_tags_ids,
            'max_rule_products_enabled'     => $max_rule_products_enabled,
            'max_rule_products_qty'         => $max_rule_products_qty,
            'max_rule_products_input'       => $max_rule_products_input,
        );
        
        $general_settings_context = array(
            'cleanup_on_delete'      => isset($settings['cleanup_on_delete']) ? $settings['cleanup_on_delete'] : 'no',
            'admin_font_choice'      => isset($settings['admin_font_choice']) ? $settings['admin_font_choice'] : 'default',
            'admin_font_custom_name' => isset($settings['admin_font_custom_name']) ? $settings['admin_font_custom_name'] : '',
            'admin_font_custom_url'  => isset($settings['admin_font_custom_url']) ? $settings['admin_font_custom_url'] : '',
            'font_presets'           => $this->get_admin_font_presets(),
        );
        ?>
        <div class="wrap aio-wc-admin-wrap" data-aio-wc-page="true">
            <div class="aio-wc-admin">
                <div class="aio-wc-layout">
                    <?php $this->render_view('sidebar', $sidebar_context); ?>
                    <main class="aio-wc-main">
                        <form method="post" action="options.php" id="aio-wc-settings-form">
                            <?php settings_fields('aio_wc_settings'); ?>
                            <?php 
                            // Get active tab from URL
                            $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'persian-calendar';
                            ?>
                            <?php $this->render_view('tab-persian-calendar', $persian_tab_context); ?>
                            <?php $this->render_view('tab-cart-rules', $cart_rules_tab_context); ?>
                            <?php 
                            // Only render payment gateways tab if WooCommerce is active
                            if (class_exists('WooCommerce')) {
                                $payment_gateways_tab_context = $this->get_payment_gateways_tab_context($settings);
                                $this->render_view('tab-payment-gateways', $payment_gateways_tab_context); 
                            }
                            ?>
                            <?php $this->render_view('tab-settings', $general_settings_context); ?>
                            <?php submit_button(__('Save All Settings', 'aio-woocommerce'), 'primary', 'submit', false, array('style' => 'margin-top: 24px;')); ?>
                        </form>
                    </main>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Return preset list for admin font selector.
     *
     * @return array
     */
    private function get_admin_font_presets() {
        return array(
            'default' => array(
                'label'  => __('WordPress default', 'aio-woocommerce'),
                'family' => "'IRANSans','Tahoma','Helvetica Neue','Segoe UI',sans-serif",
                'css'    => '',
            ),
            'vazirmatn' => array(
                'label'  => __('Vazirmatn (Google Fonts)', 'aio-woocommerce'),
                'family' => "'Vazirmatn','IRANSans','Tahoma','Segoe UI',sans-serif",
                'css'    => 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600&display=swap',
            ),
            'estedad' => array(
                'label'  => __('Estedad (Google Fonts)', 'aio-woocommerce'),
                'family' => "'Estedad','IRANSans','Tahoma','Segoe UI',sans-serif",
                'css'    => 'https://fonts.googleapis.com/css2?family=Estedad:wght@400;500;600&display=swap',
            ),
        );
    }
    
    /**
     * Detect font format from URL extension for @font-face declarations.
     *
     * @param string $url
     * @return string
     */
    private function detect_font_format($url) {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path ? $path : '', PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'woff2':
                return 'woff2';
            case 'ttf':
                return 'truetype';
            case 'otf':
                return 'opentype';
            case 'eot':
                return 'embedded-opentype';
            case 'svg':
                return 'svg';
            case 'woff':
            default:
                return 'woff';
        }
    }
    
    /**
     * Allow font file types in WordPress media uploader.
     *
     * @param array $mimes Existing mime types
     * @return array Modified mime types
     */
    public function allow_font_mimes($mimes) {
        $mimes['woff']  = 'font/woff';
        $mimes['woff2'] = 'font/woff2';
        $mimes['ttf']  = 'font/ttf';
        $mimes['otf']  = 'font/otf';
        return $mimes;
    }
    
    /**
     * Get payment gateways tab context
     * 
     * @param array $settings Plugin settings
     * @return array Tab context
     */
    private function get_payment_gateways_tab_context($settings) {
        // Zarinpal settings
        $zarinpal_settings = isset($settings['payment_gateway_zarinpal']) && is_array($settings['payment_gateway_zarinpal']) 
            ? $settings['payment_gateway_zarinpal'] 
            : array();
        
        $zibal_settings = isset($settings['payment_gateway_zibal']) && is_array($settings['payment_gateway_zibal']) 
            ? $settings['payment_gateway_zibal'] 
            : array();
        
        return array(
            'zarinpal_enabled'                   => isset($zarinpal_settings['enabled']) && $zarinpal_settings['enabled'] === 'yes' ? 'yes' : 'no',
            'zarinpal_title'                     => isset($zarinpal_settings['title']) ? $zarinpal_settings['title'] : __('پرداخت امن زرین پال', 'aio-woocommerce'),
            'zarinpal_description'               => isset($zarinpal_settings['description']) ? $zarinpal_settings['description'] : __('پرداخت امن به وسیله کلیه کارتهای عضو شتاب از طریق درگاه زرین پال', 'aio-woocommerce'),
            'zarinpal_merchant_id'               => isset($zarinpal_settings['merchant_id']) ? $zarinpal_settings['merchant_id'] : '',
            'zarinpal_access_token'              => isset($zarinpal_settings['access_token']) ? $zarinpal_settings['access_token'] : '',
            'zarinpal_sandbox_mode'              => isset($zarinpal_settings['sandbox_mode']) && $zarinpal_settings['sandbox_mode'] === 'yes' ? 'yes' : 'no',
            'zarinpal_gate'                      => isset($zarinpal_settings['zarinpal_gate']) ? $zarinpal_settings['zarinpal_gate'] : 'normal',
            'zarinpal_commission_from'           => isset($zarinpal_settings['commission_from']) ? $zarinpal_settings['commission_from'] : (isset($zarinpal_settings['commission_deduct_from']) ? $zarinpal_settings['commission_deduct_from'] : 'merchant'),
            'zarinpal_success_message'           => isset($zarinpal_settings['success_message']) ? $zarinpal_settings['success_message'] : __('با تشکر از شما سفارش شما با موفقیت پرداخت شد', 'aio-woocommerce'),
            'zarinpal_failed_message'            => isset($zarinpal_settings['failed_message']) ? $zarinpal_settings['failed_message'] : __('پرداخت شما ناموفق بوده است. لطفاً مجدداً تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید', 'aio-woocommerce'),
            'zarinpal_post_purchase_description' => isset($zarinpal_settings['post_purchase_description']) ? $zarinpal_settings['post_purchase_description'] : '',
            'zarinpal_trust_logo_code'           => isset($zarinpal_settings['trust_logo_code']) ? $zarinpal_settings['trust_logo_code'] : '<style>#zarinpal{margin:auto} #zarinpal img {width: 80px;}</style><div id="zarinpal"><script src="https://www.zarinpal.com/webservice/TrustCode" type="text/javascript"></script></div>',
            'zibal_enabled'                     => isset($zibal_settings['enabled']) && $zibal_settings['enabled'] === 'yes' ? 'yes' : 'no',
            'zibal_title'                       => isset($zibal_settings['title']) ? $zibal_settings['title'] : __('زیبال', 'aio-woocommerce'),
            'zibal_description'                 => isset($zibal_settings['description']) ? $zibal_settings['description'] : __('پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه زیبال', 'aio-woocommerce'),
            'zibal_merchant_id'                 => isset($zibal_settings['merchant_id']) ? $zibal_settings['merchant_id'] : '',
            'zibal_sandbox_mode'                => isset($zibal_settings['sandbox_mode']) && $zibal_settings['sandbox_mode'] === 'yes' ? 'yes' : 'no',
            'zibal_success_message'             => isset($zibal_settings['success_message']) ? $zibal_settings['success_message'] : __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'aio-woocommerce'),
            'zibal_failed_message'              => isset($zibal_settings['failed_message']) ? $zibal_settings['failed_message'] : __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'aio-woocommerce'),
        );
    }
    
    /**
     * Toggle switch function
     */
    private function toggle_switch($name, $value = 'yes', $checked = false, $disabled = false, $id = '') {
        if (empty($id)) {
            $id = $name;
        }
        
        $checked_attr = $checked ? 'checked="checked"' : '';
        $disabled_attr = $disabled ? 'disabled="disabled"' : '';
        
        // Handle nested field names like "payment_gateway_zarinpal[enabled]"
        // Convert to proper format: aio_wc_settings[payment_gateway_zarinpal][enabled]
        if (strpos($name, '[') !== false && strpos($name, ']') !== false) {
            // Parse nested structure: "parent[child]" -> "aio_wc_settings[parent][child]"
            if (preg_match('/^([^\[]+)\[([^\]]+)\]$/', $name, $matches)) {
                $parent = $matches[1];
                $child = $matches[2];
                $field_name = 'aio_wc_settings[' . esc_attr($parent) . '][' . esc_attr($child) . ']';
            } else {
                // Fallback: use as-is (shouldn't happen, but safe fallback)
                $field_name = 'aio_wc_settings[' . esc_attr($name) . ']';
            }
        } else {
            // Simple field name (non-nested)
            $field_name = 'aio_wc_settings[' . esc_attr($name) . ']';
        }
        
        ob_start();
        ?>
        <label class="aio-wc-toggle">
            <input type="hidden" name="<?php echo esc_attr($field_name); ?>" value="no">
            <input type="checkbox" 
                   name="<?php echo esc_attr($field_name); ?>" 
                   value="<?php echo esc_attr($value); ?>" 
                   id="<?php echo esc_attr($id); ?>" 
                   class="aio-wc-toggle__input" 
                   <?php echo $checked_attr; ?> 
                   <?php echo $disabled_attr; ?>>
            <span class="aio-wc-toggle__slider"></span>
            <span class="aio-wc-toggle__status"></span>
        </label>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Sync payment gateway settings after option is updated
     * This handles standard form submissions (not just AJAX)
     * 
     * @param array $old_value Old settings
     * @param array $new_value New settings
     */
    public function sync_payment_gateway_settings_on_save($old_value, $new_value) {
        // Apply role capability changes even when settings are saved via options.php
        if (isset($new_value['allowed_roles']) && is_array($new_value['allowed_roles'])) {
            $this->apply_allowed_roles_caps($new_value['allowed_roles']);
        } else {
            // Safety: if key missing on full save, keep administrators at minimum
            $this->apply_allowed_roles_caps(array('administrator'));
        }
        $this->sync_payment_gateway_settings_to_wc($new_value);
    }
    
    /**
     * Sync payment gateway settings to WooCommerce
     * 
     * @param array $settings Plugin settings
     */
    private function sync_payment_gateway_settings_to_wc($settings) {
        // Sync Zarinpal settings
        if (isset($settings['payment_gateway_zarinpal']) && is_array($settings['payment_gateway_zarinpal'])) {
            $zarinpal = $settings['payment_gateway_zarinpal'];
            $wc_settings = array(
                'enabled'                  => isset($zarinpal['enabled']) && $zarinpal['enabled'] === 'yes' ? 'yes' : 'no',
                'title'                    => isset($zarinpal['title']) && !empty($zarinpal['title']) ? $zarinpal['title'] : __('پرداخت امن زرین پال', 'aio-woocommerce'),
                'description'              => isset($zarinpal['description']) ? $zarinpal['description'] : '',
                'merchant_id'              => isset($zarinpal['merchant_id']) ? $zarinpal['merchant_id'] : '',
                'access_token'             => isset($zarinpal['access_token']) ? $zarinpal['access_token'] : '',
                'sandbox_mode'             => isset($zarinpal['sandbox_mode']) && $zarinpal['sandbox_mode'] === 'yes' ? 'yes' : 'no',
                'zarinpal_gate'            => isset($zarinpal['zarinpal_gate']) && !empty($zarinpal['zarinpal_gate']) ? $zarinpal['zarinpal_gate'] : 'normal',
                'commission_deduct_from'   => isset($zarinpal['commission_from']) && !empty($zarinpal['commission_from']) ? $zarinpal['commission_from'] : (isset($zarinpal['commission_deduct_from']) && !empty($zarinpal['commission_deduct_from']) ? $zarinpal['commission_deduct_from'] : 'merchant'),
                'success_message'          => isset($zarinpal['success_message']) ? $zarinpal['success_message'] : __('با تشکر از شما سفارش شما با موفقیت پرداخت شد', 'aio-woocommerce'),
                'failed_message'           => isset($zarinpal['failed_message']) ? $zarinpal['failed_message'] : __('پرداخت شما ناموفق بوده است. لطفاً مجدداً تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید', 'aio-woocommerce'),
                'post_purchase_description' => isset($zarinpal['post_purchase_description']) ? $zarinpal['post_purchase_description'] : '',
                'trust_logo_code'          => isset($zarinpal['trust_logo_code']) ? $zarinpal['trust_logo_code'] : '',
            );
            // Use update_option with autoload = false to prevent cache issues
            update_option('woocommerce_aio_wc_zarinpal_settings', $wc_settings, false);
            
            // Clear any WooCommerce caches
            if (function_exists('WC')) {
                wp_cache_delete('woocommerce_gateway_aio_wc_zarinpal', 'options');
            }
        }
        
        // Sync Zibal settings
        if (isset($settings['payment_gateway_zibal']) && is_array($settings['payment_gateway_zibal'])) {
            $zibal = $settings['payment_gateway_zibal'];
            $wc_settings = array(
                'enabled'      => isset($zibal['enabled']) && $zibal['enabled'] === 'yes' ? 'yes' : 'no',
                'title'        => isset($zibal['title']) && !empty($zibal['title']) ? $zibal['title'] : __('Zibal', 'aio-woocommerce'),
                'description'  => isset($zibal['description']) ? $zibal['description'] : '',
                'merchant_id'  => isset($zibal['merchant_id']) ? $zibal['merchant_id'] : '',
                'api_key'      => isset($zibal['api_key']) ? $zibal['api_key'] : '',
                'sandbox_mode' => isset($zibal['sandbox_mode']) && $zibal['sandbox_mode'] === 'yes' ? 'yes' : 'no',
            );
            // Use update_option with autoload = false to prevent cache issues
            update_option('woocommerce_aio_wc_zibal_settings', $wc_settings, false);
            
            // Clear any WooCommerce caches
            if (function_exists('WC')) {
                wp_cache_delete('woocommerce_gateway_aio_wc_zibal', 'options');
            }
        }
    }
}