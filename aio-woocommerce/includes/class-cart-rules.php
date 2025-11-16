<?php
/**
 * Cart minimums & quantity rules manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIO_WC_Cart_Rules {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Cached settings
     */
    private $settings = null;
    
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
     * Enforce minimum cart total during checkout submission
     */
    public function validate_cart_minimum_at_checkout() {
        if (!$this->cart_minimum_enabled()) {
            return;
        }
        
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }
        
        $minimum = $this->get_cart_minimum_amount();
        if ($minimum <= 0) {
            return;
        }
        
        $items_total = WC()->cart->get_cart_contents_total();
        if ($items_total >= $minimum) {
            return;
        }
        
        $message = $this->get_cart_minimum_message($minimum, $items_total);
        wc_add_notice($message, 'error');
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->settings = get_option('aio_wc_settings', array());
        
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 5, 6);
        add_filter('woocommerce_update_cart_validation', array($this, 'validate_cart_item_update'), 10, 4);
        add_action('woocommerce_checkout_process', array($this, 'validate_cart_minimum_at_checkout'));
        add_filter('woocommerce_quantity_input_args', array($this, 'filter_quantity_input_args'), 20, 2);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('update_option_aio_wc_settings', array($this, 'on_settings_updated'), 10, 2);
    }
    
    /**
     * Refresh settings cache when option updated
     */
    public function on_settings_updated($old_value, $new_value) {
        $this->settings = is_array($new_value) ? $new_value : get_option('aio_wc_settings', array());
    }
    
    /**
     * Retrieve settings with simple caching
     */
    private function get_settings() {
        if ($this->settings === null) {
            $this->settings = get_option('aio_wc_settings', array());
        }
        return $this->settings;
    }
    
    private function min_rules_enabled() {
        $settings = $this->get_settings();
        if (!isset($settings['cart_min_rules_enabled'])) {
            return true;
        }
        return $settings['cart_min_rules_enabled'] === 'yes';
    }

    /**
     * Determine if cart minimum enforcement is enabled
     */
    private function cart_minimum_enabled() {
        $settings = $this->get_settings();
        return isset($settings['cart_minimum_enabled']) && $settings['cart_minimum_enabled'] === 'yes';
    }
    
    /**
     * Get cart minimum amount
     */
    private function get_cart_minimum_amount() {
        $settings = $this->get_settings();
        return isset($settings['cart_minimum_amount']) ? floatval($settings['cart_minimum_amount']) : 0;
    }
    
    /**
     * Get cart rules list
     */
    private function get_quantity_rules() {
        if (!$this->min_rules_enabled()) {
            return array();
        }
        $settings = $this->get_settings();
        if (!isset($settings['cart_rules']) || !is_array($settings['cart_rules'])) {
            return array();
        }
        return $settings['cart_rules'];
    }

    private function get_product_ids_for_comparison($product) {
        if (!$product instanceof WC_Product) {
            return array();
        }
        $ids = array((int) $product->get_id());
        if ($product->is_type('variation')) {
            $parent_id = (int) $product->get_parent_id();
            if ($parent_id > 0) {
                $ids[] = $parent_id;
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    private function get_product_term_ids($product, $taxonomy) {
        if (!$product instanceof WC_Product) {
            return array();
        }
        $term_ids = array();
        switch ($taxonomy) {
            case 'product_cat':
                $term_ids = $product->get_category_ids();
                break;
            case 'product_tag':
                $term_ids = $product->get_tag_ids();
                break;
        }
        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            if ($parent_id) {
                $parent = wc_get_product($parent_id);
                if ($parent instanceof WC_Product) {
                    switch ($taxonomy) {
                        case 'product_cat':
                            $term_ids = array_merge($term_ids, $parent->get_category_ids());
                            break;
                        case 'product_tag':
                            $term_ids = array_merge($term_ids, $parent->get_tag_ids());
                            break;
                    }
                }
            }
        }
        return array_values(array_unique(array_map('intval', (array) $term_ids)));
    }

    private function get_max_quantity_for_product($product) {
        if (!$product instanceof WC_Product) {
            return null;
        }
        $settings = $this->get_settings();
        // Product-level rules
        if (isset($settings['cart_max_rule_products_enabled']) && $settings['cart_max_rule_products_enabled'] === 'yes'
            && !empty($settings['cart_max_rule_products_ids']) && is_array($settings['cart_max_rule_products_ids'])) {
            $ids = $this->get_product_ids_for_comparison($product);
            if (!empty(array_intersect($ids, array_map('intval', $settings['cart_max_rule_products_ids'])))) {
                return array('max_qty' => max(1, intval($settings['cart_max_rule_products_qty'])));
            }
        }
        // Tag-level
        if (isset($settings['cart_max_rule_tags_enabled']) && $settings['cart_max_rule_tags_enabled'] === 'yes'
            && !empty($settings['cart_max_rule_tags_ids']) && is_array($settings['cart_max_rule_tags_ids'])) {
            $product_tags = $this->get_product_term_ids($product, 'product_tag');
            if (!empty(array_intersect($product_tags, array_map('intval', $settings['cart_max_rule_tags_ids'])))) {
                return array('max_qty' => max(1, intval($settings['cart_max_rule_tags_qty'])));
            }
        }
        // Category-level
        if (isset($settings['cart_max_rule_categories_enabled']) && $settings['cart_max_rule_categories_enabled'] === 'yes'
            && !empty($settings['cart_max_rule_categories_ids']) && is_array($settings['cart_max_rule_categories_ids'])) {
            $product_cats = $this->get_product_term_ids($product, 'product_cat');
            if (!empty(array_intersect($product_cats, array_map('intval', $settings['cart_max_rule_categories_ids'])))) {
                return array('max_qty' => max(1, intval($settings['cart_max_rule_categories_qty'])));
            }
        }
        // Global rule
        if (isset($settings['cart_max_rule_all_enabled']) && $settings['cart_max_rule_all_enabled'] === 'yes') {
            return array('max_qty' => max(1, intval($settings['cart_max_rule_all_qty'])));
        }
        return null;
    }

    private function get_cart_quantity_for_product($product, $exclude_key = null) {
        if (!WC()->cart) {
            return 0;
        }
        $target_ids = $this->get_product_ids_for_comparison($product);
        $total = 0;
        foreach (WC()->cart->get_cart() as $key => $item) {
            if ($exclude_key !== null && $key === $exclude_key) {
                continue;
            }
            if (!isset($item['data']) || !$item['data'] instanceof WC_Product) {
                continue;
            }
            $item_ids = $this->get_product_ids_for_comparison($item['data']);
            if (!empty(array_intersect($target_ids, $item_ids))) {
                $total += isset($item['quantity']) ? intval($item['quantity']) : 0;
            }
        }
        return $total;
    }

    private function format_max_quantity_message($product_name, $max_qty) {
        return $this->replace_placeholders(
            __('حداکثر تعداد مجاز برای {product} برابر با {max_qty} عدد است.', 'aio-woocommerce'),
            array(
                'product' => $product_name,
                'max_qty' => $max_qty,
            )
        );
    }

    private function format_min_max_conflict_message($product_name, $min_qty, $max_qty) {
        return $this->replace_placeholders(
            __('حداقل مقدار تعیین شده برای {product} ({min_qty}) از حداکثر مقدار مجاز ({max_qty}) بیشتر است.', 'aio-woocommerce'),
            array(
                'product' => $product_name,
                'min_qty' => $min_qty,
                'max_qty' => $max_qty,
            )
        );
    }
    
    /**
     * Determine if product is excluded from quantity rules
     */
    private function is_product_excluded($product) {
        if (!$product) {
            return true;
        }
        
        $settings = $this->get_settings();
        
        $excluded_categories = isset($settings['cart_rule_excluded_categories']) && is_array($settings['cart_rule_excluded_categories'])
            ? array_map('intval', $settings['cart_rule_excluded_categories'])
            : array();
        $excluded_tags = isset($settings['cart_rule_excluded_tags']) && is_array($settings['cart_rule_excluded_tags'])
            ? array_map('intval', $settings['cart_rule_excluded_tags'])
            : array();
        
        if (!empty($excluded_categories)) {
            $product_category_ids = $product->get_category_ids();
            if (array_intersect($product_category_ids, $excluded_categories)) {
                return true;
            }
        }
        
        if (!empty($excluded_tags)) {
            $product_tag_ids = $product->get_tag_ids();
            if (array_intersect($product_tag_ids, $excluded_tags)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Compute minimum quantity requirement for a product
     */
    private function determine_min_quantity($product) {
        if (!$this->min_rules_enabled()) {
            return null;
        }
        if (!$product instanceof WC_Product) {
            return null;
        }
        
        if ($this->is_product_excluded($product)) {
            return null;
        }
        
        $price = floatval($product->get_price());
        $rules = $this->get_quantity_rules();
        
        if (empty($rules) || $price <= 0) {
            return null;
        }
        
        foreach ($rules as $rule) {
            $min_price = isset($rule['price_min']) ? floatval($rule['price_min']) : 0;
            $max_price = isset($rule['price_max']) && $rule['price_max'] !== '' ? floatval($rule['price_max']) : '';
            $min_qty   = isset($rule['min_qty']) ? max(1, intval($rule['min_qty'])) : 1;
            
            if ($price >= $min_price && ($max_price === '' || $price < $max_price)) {
                // Respect stock levels - allow smaller orders when stock is limited and backorders disabled
                if ($product->managing_stock() && !$product->backorders_allowed()) {
                    $stock = $product->get_stock_quantity();
                    if ($stock !== null && $stock > 0 && $stock < $min_qty) {
                        return null;
                    }
                }
                
                return array(
                    'min_qty' => $min_qty,
                    'rule'    => $rule,
                    'price'   => $price,
                );
            }
        }
        
        return null;
    }
    
    /**
     * Validate add to cart requests - matches your working snippet
     */
    public function validate_add_to_cart($passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array()) {
        if (!$passed) {
            return false;
        }
        $product = wc_get_product($variation_id ?: $product_id);
        if (!$product) {
            return $passed;
        }

        $max_rule = $this->get_max_quantity_for_product($product);
        $requirement = $this->determine_min_quantity($product);

        if (!$this->min_rules_enabled()) {
            $requirement = null;
        }

        if ($requirement && $requirement['min_qty'] > 1 && $max_rule && $requirement['min_qty'] > $max_rule['max_qty']) {
            wc_add_notice($this->format_min_max_conflict_message($product->get_name(), $requirement['min_qty'], $max_rule['max_qty']), 'error');
            return false;
        }

        if (!$requirement || $requirement['min_qty'] <= 1) {
            if ($max_rule) {
                $existing_qty = $this->get_cart_quantity_for_product($product);
                if (($existing_qty + $quantity) > $max_rule['max_qty']) {
                    wc_add_notice($this->format_max_quantity_message($product->get_name(), $max_rule['max_qty']), 'error');
                    return false;
                }
            }
            return $passed;
        }

        $min_qty = $requirement['min_qty'];
        $product_name = $product->get_name();
        $max_purchasable = $product->get_max_purchase_quantity();

        if ($max_purchasable === 0) {
            wc_add_notice(sprintf(__('متأسفیم، %s در حال حاضر قابل خرید نیست.', 'aio-woocommerce'), $product_name), 'error');
            return false;
        }

        if ($max_purchasable > 0 && $quantity > $max_purchasable) {
            wc_add_notice(sprintf(__('متأسفیم، تنها %d عدد از %s در انبار موجود است و نمی‌توانید %d عدد اضافه کنید.', 'aio-woocommerce'), $max_purchasable, $product_name, $quantity), 'error');
            return false;
        }

        if ($quantity < $min_qty) {
            if ($max_rule && $min_qty > $max_rule['max_qty']) {
                wc_add_notice($this->format_min_max_conflict_message($product_name, $min_qty, $max_rule['max_qty']), 'error');
                return false;
            }
            $settings = $this->get_settings();
            $message_template = !empty($settings['cart_rule_message'])
                ? $settings['cart_rule_message']
                : __('مقدار سفارش برای {product} به حداقل مقدار {min_qty} عدد تنظیم شد.', 'aio-woocommerce');

            $message = $this->replace_placeholders($message_template, array(
                'product' => $product_name,
                'min_qty' => $min_qty,
                'price'   => wc_price($requirement['price']),
            ));

            wc_add_notice($message, 'notice');

            $temp_filter_func = function($qty_to_add) use ($min_qty, &$temp_filter_func) {
                remove_filter('woocommerce_add_to_cart_quantity', $temp_filter_func, 20);
                return $min_qty;
            };
            add_filter('woocommerce_add_to_cart_quantity', $temp_filter_func, 20, 1);

            add_filter('wc_add_to_cart_message_html', '__return_empty_string', 999);

            return $passed;
        }

        if ($max_rule) {
            $existing_qty = $this->get_cart_quantity_for_product($product);
            if (($existing_qty + $quantity) > $max_rule['max_qty']) {
                wc_add_notice($this->format_max_quantity_message($product_name, $max_rule['max_qty']), 'error');
                return false;
            }
        }

        return $passed;
    }
    
    /**
     * Validate cart updates (quantity edits in cart)
     */
    public function validate_cart_item_update($passed, $cart_item_key, $values, $quantity) {
        if (!$passed) {
            return false;
        }
        
        if (!isset($values['data']) || !$values['data'] instanceof WC_Product) {
            return $passed;
        }
        
        $product = $values['data'];
        $requirement = $this->determine_min_quantity($product);
        $max_rule = $this->get_max_quantity_for_product($product);

        if ($requirement && $requirement['min_qty'] > 1 && $max_rule && $requirement['min_qty'] > $max_rule['max_qty']) {
            wc_add_notice($this->format_min_max_conflict_message($product->get_name(), $requirement['min_qty'], $max_rule['max_qty']), 'error');
            return false;
        }

        if ($requirement && $requirement['min_qty'] > 1 && $quantity < $requirement['min_qty']) {
            $settings = $this->get_settings();
            $message_template = !empty($settings['cart_rule_message'])
                ? $settings['cart_rule_message']
                : __('حداقل تعداد سفارش برای "{product}" برابر با {min_qty} عدد است.', 'aio-woocommerce');
            
            $message = $this->replace_placeholders($message_template, array(
                'product' => $product->get_name(),
                'min_qty' => $requirement['min_qty'],
                'price'   => wc_price($requirement['price']),
            ));
            
            wc_add_notice($message, 'error');
            return false;
        }

        if ($max_rule) {
            $existing_qty = $this->get_cart_quantity_for_product($product, $cart_item_key);
            if (($existing_qty + $quantity) > $max_rule['max_qty']) {
                wc_add_notice($this->format_max_quantity_message($product->get_name(), $max_rule['max_qty']), 'error');
                return false;
            }
        }

        return $passed;
    }
    
    /**
     * Adjust quantity input minimums on product pages / cart lines
     */
    public function filter_quantity_input_args($args, $product) {
        if (!$product instanceof WC_Product) {
            return $args;
        }

        $requirement = $this->determine_min_quantity($product);
        $max_rule = $this->get_max_quantity_for_product($product);

        if ($requirement && $requirement['min_qty'] > 1 && $max_rule && $requirement['min_qty'] > $max_rule['max_qty']) {
            // Conflict handled during validation; here we clamp to max to avoid broken inputs.
            $requirement['min_qty'] = $max_rule['max_qty'];
        }

        if ($requirement && $requirement['min_qty'] > 1) {
            $min_qty = $requirement['min_qty'];
            $is_cart_context = (function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout());

            $attrs = isset($args['custom_attributes']) ? $args['custom_attributes'] : array();
            $attrs['data-aio-min-qty'] = $min_qty;
            $attrs['data-aio-enforce'] = 'hard';
            $args['custom_attributes'] = $attrs;

            if ($is_cart_context) {
                $current_min = isset($args['min_value']) ? intval($args['min_value']) : 1;
                if ($current_min < $min_qty) {
                    $args['min_value'] = $min_qty;
                }
                if (!isset($args['input_value']) || intval($args['input_value']) < $min_qty) {
                    $args['input_value'] = $min_qty;
                }
            } else {
                $args['min_value'] = 1;
                if (!isset($args['input_value']) || intval($args['input_value']) < $min_qty) {
                    $args['input_value'] = $min_qty;
                }
            }
        }

        if ($max_rule) {
            $max_qty = max(1, intval($max_rule['max_qty']));
            $attrs = isset($args['custom_attributes']) ? $args['custom_attributes'] : array();
            $attrs['data-aio-max-qty'] = $max_qty;
            $args['custom_attributes'] = $attrs;

            if (!isset($args['max_value']) || intval($args['max_value']) > $max_qty || $args['max_value'] === '') {
                $args['max_value'] = $max_qty;
            }
            if (isset($args['input_value']) && intval($args['input_value']) > $max_qty) {
                $args['input_value'] = $max_qty;
            }
        }

        return $args;
    }
    
    /**
     * Enqueue frontend enforcement script
     */
    public function enqueue_frontend_assets() {
        if (!function_exists('is_product') || (!is_product() && !is_cart() && !is_shop() && !is_product_category() && !is_product_tag())) {
            return;
        }
        
        wp_enqueue_script(
            'aio-wc-cart-rules',
            AIO_WC_PLUGIN_URL . 'assets/js/cart-rules.js',
            array('jquery'),
            AIO_WC_VERSION,
            true
        );
        
        $settings = get_option('aio_wc_settings', array());
        $raw_notice_template = isset($settings['cart_rule_message']) && $settings['cart_rule_message']
            ? $settings['cart_rule_message']
            : __('مقدار سفارش برای {product} به حداقل مقدار {min_qty} عدد تنظیم شد.', 'aio-woocommerce');
        $clean_notice_template = wp_strip_all_tags($raw_notice_template);

        $raw_max_notice = __('حداکثر تعداد مجاز برای {product} برابر با {max_qty} عدد است.', 'aio-woocommerce');
        $clean_max_notice = wp_strip_all_tags($raw_max_notice);

        $product_min_qty = 1;
        $product_max_qty = 0;
        $product_name = '';
        $product = null;
        if (function_exists('is_product') && is_product()) {
            $product = wc_get_product(get_the_ID());
        }
        if ($product instanceof WC_Product) {
            if ($this->min_rules_enabled()) {
                $requirement = $this->determine_min_quantity($product);
                if ($requirement && isset($requirement['min_qty'])) {
                    $product_min_qty = max(1, intval($requirement['min_qty']));
                }
            }
            $max_rule = $this->get_max_quantity_for_product($product);
            if ($max_rule) {
                $product_max_qty = max(1, intval($max_rule['max_qty']));
            }
            $product_name = $product->get_name();
        }

        wp_localize_script('aio-wc-cart-rules', 'aioWcCartRulesFrontend', array(
            'enabled'             => $this->min_rules_enabled(),
            'rules'               => $this->get_quantity_rules(),
            'notice_template'     => $clean_notice_template,
            'max_notice_template' => $clean_max_notice,
            'product_placeholder' => esc_html__('این محصول', 'aio-woocommerce'),
            'product_min_qty'     => $product_min_qty,
            'product_max_qty'     => $product_max_qty,
            'product_name'        => $product_name,
        ));
    }
    
    /**
     * Replace placeholders in a template
     */
    private function replace_placeholders($template, $vars = array()) {
        $replacements = array();
        foreach ($vars as $key => $value) {
            $placeholder = (strpos($key, '{') === 0) ? $key : '{' . $key . '}';
            $replacements[$placeholder] = $value;
        }
        return strtr($template, $replacements);
    }
    
    /**
     * Get formatted cart minimum message
     */
    private function get_cart_minimum_message($minimum, $current_total) {
        $settings = $this->get_settings();
        $template = !empty($settings['cart_minimum_message'])
            ? $settings['cart_minimum_message']
            : __('حداقل مبلغ سفارش {min_total} تومان است. مجموع فعلی {current_total} تومان می‌باشد.', 'aio-woocommerce');
        
        $formatted_min = wc_price($minimum);
        $formatted_current = wc_price($current_total);
        $plain_min = wc_trim_zeros(wc_format_localized_price($minimum));
        $plain_current = wc_trim_zeros(wc_format_localized_price($current_total));
        $currency_symbol = get_woocommerce_currency_symbol();
        
        return $this->replace_placeholders($template, array(
            'min_total'             => wp_strip_all_tags($formatted_min),
            'min_total_html'        => $formatted_min,
            'min_total_plain'       => $plain_min,
            'current_total'         => wp_strip_all_tags($formatted_current),
            'current_total_html'    => $formatted_current,
            'current_total_plain'   => $plain_current,
            'currency_symbol'       => $currency_symbol,
        ));
    }
    
}