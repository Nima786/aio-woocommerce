<?php
/**
 * Frontend Shortcodes and Date Display
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIO_WC_Frontend {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register shortcodes
        add_shortcode('aio_wc_date', array($this, 'shortcode_date'));
        add_shortcode('aio_wc_date_persian', array($this, 'shortcode_date_persian'));
        add_shortcode('aio_wc_date_gregorian', array($this, 'shortcode_date_gregorian'));
        add_shortcode('aio_wc_date_both', array($this, 'shortcode_date_both'));
        add_shortcode('aio_wc_post_date', array($this, 'shortcode_post_date'));
        add_shortcode('aio_wc_order_date', array($this, 'shortcode_order_date'));
        
        // Filter WooCommerce frontend dates
        add_filter('woocommerce_order_date', array($this, 'filter_order_date'), 10, 2);
        add_filter('woocommerce_order_date_created', array($this, 'filter_order_date'), 10, 2);
        
        // Register dynamic tags for page builders
        $this->register_page_builder_support();
    }
    
    /**
     * Register support for page builders (Bricks, Elementor, etc.)
     */
    private function register_page_builder_support() {
        // Bricks Builder support - shortcodes work natively in Bricks via Shortcode element
        // Also register as dynamic data provider for better integration
        add_filter('bricks/dynamic_data/providers', array($this, 'register_bricks_providers'));
        add_filter('bricks/dynamic_data/list', array($this, 'register_bricks_dynamic_tags'));
        
        // Elementor support (if Elementor is active)
        if (did_action('elementor/loaded')) {
            add_action('elementor/dynamic_tags/register_tags', array($this, 'register_elementor_dynamic_tags'));
        }
    }
    
    /**
     * Register Bricks Builder providers
     */
    public function register_bricks_providers($providers) {
        $providers['aio_wc_dates'] = array(
            'name' => 'AIO WooCommerce Dates',
            'label' => 'Persian/Gregorian Dates',
        );
        return $providers;
    }
    
    /**
     * Shortcode: Display current date (defaults to Persian if enabled)
     * 
     * Usage: 
     * [aio_wc_date] - Current date in Persian (if enabled)
     * [aio_wc_date format="Y/m/d"] - Custom format
     * [aio_wc_date type="persian"] - Force Persian
     * [aio_wc_date type="gregorian"] - Force Gregorian
     * [aio_wc_date date="2024-01-15"] - Specific date
     */
    public function shortcode_date($atts) {
        $settings = get_option('aio_wc_settings', array());
        
        $atts = shortcode_atts(array(
            'format' => isset($settings['date_format']) ? $settings['date_format'] : 'Y/m/d',
            'date' => '',
            'type' => '' // 'persian', 'gregorian', or empty for default
        ), $atts, 'aio_wc_date');
        
        if (!empty($atts['date'])) {
            $date_string = $atts['date'];
        } else {
            $date_string = current_time('mysql');
        }
        
        // Determine which format to use
        $use_persian = false;
        if (!empty($atts['type'])) {
            $use_persian = ($atts['type'] === 'persian');
        } else {
            $use_persian = (isset($settings['persian_calendar_enabled']) && $settings['persian_calendar_enabled'] === 'yes');
        }
        
        if ($use_persian) {
            return AIO_WC_Persian_Calendar::convert_to_persian($date_string, $atts['format']);
        } else {
            $timestamp = strtotime($date_string);
            return date_i18n($atts['format'], $timestamp);
        }
    }
    
    /**
     * Shortcode: Display Persian date only
     * 
     * Usage: 
     * [aio_wc_date_persian] - Current date in Persian
     * [aio_wc_date_persian format="Y/m/d"] - Custom format
     * [aio_wc_date_persian date="2024-01-15"] - Specific date
     */
    public function shortcode_date_persian($atts) {
        $settings = get_option('aio_wc_settings', array());
        
        $atts = shortcode_atts(array(
            'format' => isset($settings['date_format']) ? $settings['date_format'] : 'Y/m/d',
            'date' => ''
        ), $atts, 'aio_wc_date_persian');
        
        if (!empty($atts['date'])) {
            $date_string = $atts['date'];
        } else {
            $date_string = current_time('mysql');
        }
        
        return AIO_WC_Persian_Calendar::convert_to_persian($date_string, $atts['format']);
    }
    
    /**
     * Shortcode: Display Gregorian date only
     * 
     * Usage: 
     * [aio_wc_date_gregorian] - Current date in Gregorian
     * [aio_wc_date_gregorian format="Y/m/d"] - Custom format
     * [aio_wc_date_gregorian date="2024-01-15"] - Specific date
     */
    public function shortcode_date_gregorian($atts) {
        $settings = get_option('aio_wc_settings', array());
        
        $atts = shortcode_atts(array(
            'format' => isset($settings['date_format']) ? $settings['date_format'] : 'Y/m/d',
            'date' => ''
        ), $atts, 'aio_wc_date_gregorian');
        
        if (!empty($atts['date'])) {
            $date_string = $atts['date'];
        } else {
            $date_string = current_time('mysql');
        }
        
        $timestamp = strtotime($date_string);
        return date_i18n($atts['format'], $timestamp);
    }
    
    /**
     * Shortcode: Display post date
     * 
     * Usage: 
     * [aio_wc_post_date] - Post published date in Persian (if enabled)
     * [aio_wc_post_date type="persian"] - Force Persian
     * [aio_wc_post_date type="gregorian"] - Force Gregorian
     * [aio_wc_post_date format="Y/m/d"] - Custom format
     * [aio_wc_post_date post_id="123"] - Specific post
     */
    public function shortcode_post_date($atts) {
        global $post;
        
        $settings = get_option('aio_wc_settings', array());
        
        $atts = shortcode_atts(array(
            'format' => isset($settings['date_format']) ? $settings['date_format'] : 'Y/m/d',
            'type' => '', // 'persian', 'gregorian', or empty for default
            'post_id' => 0,
            'field' => 'post_date' // 'post_date', 'post_modified'
        ), $atts, 'aio_wc_post_date');
        
        // Get post ID
        $post_id = !empty($atts['post_id']) ? intval($atts['post_id']) : (isset($post->ID) ? $post->ID : 0);
        
        if (empty($post_id)) {
            return '';
        }
        
        // Get post
        $target_post = get_post($post_id);
        if (!$target_post) {
            return '';
        }
        
        // Get date field
        $date_string = ($atts['field'] === 'post_modified') ? $target_post->post_modified : $target_post->post_date;
        
        // Determine which format to use
        $use_persian = false;
        if (!empty($atts['type'])) {
            $use_persian = ($atts['type'] === 'persian');
        } else {
            $use_persian = (isset($settings['persian_calendar_enabled']) && $settings['persian_calendar_enabled'] === 'yes');
        }
        
        if ($use_persian) {
            return AIO_WC_Persian_Calendar::convert_to_persian($date_string, $atts['format']);
        } else {
            $timestamp = strtotime($date_string);
            return date_i18n($atts['format'], $timestamp);
        }
    }
    
    /**
     * Shortcode: Display both Persian and Gregorian dates
     * 
     * Usage: [aio_wc_date_both], [aio_wc_date_both date="2024-01-15"],
     *        [aio_wc_date_both primary="gregorian"]
     */
    public function shortcode_date_both($atts) {
        $settings = get_option('aio_wc_settings', array());
        
        $atts = shortcode_atts(array(
            'date' => current_time('mysql'),
            'format' => isset($settings['date_format']) ? $settings['date_format'] : 'Y/m/d',
            'separator' => ' - ',
            'primary' => isset($settings['frontend_default_format']) ? $settings['frontend_default_format'] : 'persian',
        ), $atts, 'aio_wc_date_both');
        
        $date_string = $atts['date'];
        
        // Get Persian date
        $persian_date = '';
        if (isset($settings['persian_calendar_enabled']) && $settings['persian_calendar_enabled'] === 'yes') {
            $persian_date = AIO_WC_Persian_Calendar::convert_to_persian($date_string, $atts['format']);
        }
        
        // Get Gregorian date
        $timestamp = strtotime($date_string);
        $gregorian_date = date_i18n($atts['format'], $timestamp);
        
        // Determine which date appears first
        $primary = strtolower($atts['primary']);
        if (!in_array($primary, array('persian', 'gregorian'), true)) {
            $primary = 'persian';
        }
        
        if ($primary === 'persian' && !empty($persian_date)) {
            return esc_html($persian_date) . esc_html($atts['separator']) . esc_html($gregorian_date);
        } else {
            return esc_html($gregorian_date) . esc_html($atts['separator']) . esc_html($persian_date);
        }
    }
    
    /**
     * Shortcode: Display order date
     * 
     * Usage: [aio_wc_order_date order_id="123"] or [aio_wc_order_date order_id="123" format="Y/m/d"]
     */
    public function shortcode_order_date($atts) {
        $settings = get_option('aio_wc_settings', array());
        
        $atts = shortcode_atts(array(
            'order_id' => 0,
            'format' => isset($settings['date_format']) ? $settings['date_format'] : 'Y/m/d',
            'type' => 'created' // created, paid, completed
        ), $atts, 'aio_wc_order_date');
        
        if (empty($atts['order_id'])) {
            return '';
        }

        if (!function_exists('wc_get_order')) {
            return '';
        }
        
        $order = wc_get_order($atts['order_id']);
        if (!$order) {
            return '';
        }
        
        $date = null;
        switch ($atts['type']) {
            case 'paid':
                $date = $order->get_date_paid();
                break;
            case 'completed':
                $date = $order->get_date_completed();
                break;
            case 'created':
            default:
                $date = $order->get_date_created();
                break;
        }
        
        if (!$date) {
            return '';
        }
        
        $date_string = $date->date('Y-m-d H:i:s');
        
        if (isset($settings['persian_calendar_enabled']) && $settings['persian_calendar_enabled'] === 'yes') {
            return AIO_WC_Persian_Calendar::convert_to_persian($date_string, $atts['format']);
        }
        
        return $date->date_i18n($atts['format']);
    }
    
    /**
     * Filter order date in frontend
     */
    public function filter_order_date($date, $order) {
        $settings = get_option('aio_wc_settings', array());
        
        if (isset($settings['persian_calendar_enabled']) && $settings['persian_calendar_enabled'] === 'yes') {
            if (!is_admin()) {
                return AIO_WC_Persian_Calendar::convert_to_persian($date);
            }
        }
        
        return $date;
    }
    
    /**
     * Register Bricks Builder dynamic tags
     */
    public function register_bricks_dynamic_tags($list) {
        if (!function_exists('bricks_is_builder')) {
            return $list;
        }
        
        // Register dynamic tags for Bricks
        $list['aio_wc_date_persian'] = array(
            'name' => 'Persian Date',
            'group' => 'AIO WooCommerce Dates',
            'callback' => array($this, 'bricks_tag_persian_date'),
        );
        
        $list['aio_wc_date_gregorian'] = array(
            'name' => 'Gregorian Date',
            'group' => 'AIO WooCommerce Dates',
            'callback' => array($this, 'bricks_tag_gregorian_date'),
        );
        
        $list['aio_wc_post_date_persian'] = array(
            'name' => 'Post Date (Persian)',
            'group' => 'AIO WooCommerce Dates',
            'callback' => array($this, 'bricks_tag_post_date_persian'),
        );
        
        $list['aio_wc_post_date_gregorian'] = array(
            'name' => 'Post Date (Gregorian)',
            'group' => 'AIO WooCommerce Dates',
            'callback' => array($this, 'bricks_tag_post_date_gregorian'),
        );
        
        return $list;
    }
    
    /**
     * Bricks Builder tag: Persian date
     */
    public function bricks_tag_persian_date($tag, $post, $args = array()) {
        $format = isset($args['format']) ? $args['format'] : 'Y/m/d';
        $date = isset($args['date']) ? $args['date'] : current_time('mysql');
        return AIO_WC_Persian_Calendar::convert_to_persian($date, $format);
    }
    
    /**
     * Bricks Builder tag: Gregorian date
     */
    public function bricks_tag_gregorian_date($tag, $post, $args = array()) {
        $format = isset($args['format']) ? $args['format'] : 'Y/m/d';
        $date = isset($args['date']) ? $args['date'] : current_time('mysql');
        $timestamp = strtotime($date);
        return date_i18n($format, $timestamp);
    }
    
    /**
     * Bricks Builder tag: Post date (Persian)
     */
    public function bricks_tag_post_date_persian($tag, $post, $args = array()) {
        if (!$post || !isset($post->ID)) {
            return '';
        }
        $format = isset($args['format']) ? $args['format'] : 'Y/m/d';
        $field = isset($args['field']) ? $args['field'] : 'post_date';
        $date_string = ($field === 'post_modified') ? $post->post_modified : $post->post_date;
        return AIO_WC_Persian_Calendar::convert_to_persian($date_string, $format);
    }
    
    /**
     * Bricks Builder tag: Post date (Gregorian)
     */
    public function bricks_tag_post_date_gregorian($tag, $post, $args = array()) {
        if (!$post || !isset($post->ID)) {
            return '';
        }
        $format = isset($args['format']) ? $args['format'] : 'Y/m/d';
        $field = isset($args['field']) ? $args['field'] : 'post_date';
        $date_string = ($field === 'post_modified') ? $post->post_modified : $post->post_date;
        $timestamp = strtotime($date_string);
        return date_i18n($format, $timestamp);
    }
    
    /**
     * Register Elementor dynamic tags (if Elementor is active)
     */
    public function register_elementor_dynamic_tags($dynamic_tags_manager) {
        // Elementor dynamic tags would be registered here
        // This is a placeholder for future Elementor integration
    }
}

