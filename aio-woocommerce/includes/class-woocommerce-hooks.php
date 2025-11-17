<?php
/**
 * WooCommerce Hooks for Date Conversion
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIO_WC_WooCommerce_Hooks {
    
    private static $instance = null;
    private $settings = array();
    private $wp_dates_enabled = false;
    private $wp_pickers_enabled = false;
    private $wc_dates_enabled = false;
    private $wc_pickers_enabled = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->settings = get_option('aio_wc_settings', array());
        
        if (!$this->is_setting_enabled('persian_calendar_enabled')) {
            return;
        }
        
        $this->wp_dates_enabled = $this->is_setting_enabled('convert_wordpress_dates', 'convert_backend_dates');
        $this->wp_pickers_enabled = $this->is_setting_enabled('convert_wordpress_datepickers', 'convert_date_pickers');
        $this->wc_dates_enabled = $this->is_setting_enabled('convert_woocommerce_dates', 'convert_date_pickers');
        $this->wc_pickers_enabled = $this->is_setting_enabled('convert_woocommerce_datepickers', 'convert_date_pickers');
        
        if ($this->wp_dates_enabled) {
            $this->init_backend_date_conversion();
        }
        
        if ($this->wp_pickers_enabled || $this->wc_pickers_enabled || $this->wc_dates_enabled) {
            $this->init_date_picker_conversion();
        }
        
        // Initialize order list date conversion if WooCommerce dates are enabled
        // Note: We don't use woocommerce_admin_order_date_format filter as it breaks WooCommerce
        // Dates are converted via get_the_date filter instead
        if ($this->wc_dates_enabled) {
            $this->convert_order_list_dates();
        }
    }
    
    /**
     * Helper: determine if a toggle is enabled
     */
    private function is_setting_enabled($key, $fallback_key = null) {
        if (isset($this->settings[$key])) {
            return $this->settings[$key] === 'yes';
        }
        
        if ($fallback_key && isset($this->settings[$fallback_key])) {
            return $this->settings[$fallback_key] === 'yes';
        }
        
        return false;
    }
    
    /**
     * Initialize backend date conversion
     */
    private function init_backend_date_conversion() {
        // Convert dates for all post types (posts, pages, products) except orders
        // Orders are handled separately via JavaScript to avoid breaking WooCommerce functionality
        add_filter('get_the_date', array($this, 'convert_display_date'), 10, 3);
        add_filter('get_the_time', array($this, 'convert_display_date'), 10, 3);
        add_filter('get_the_modified_date', array($this, 'convert_display_date'), 10, 3);
        add_filter('get_the_modified_time', array($this, 'convert_display_date'), 10, 3);
        // Don't convert raw post_date/post_modified as it breaks date formatting
        // WordPress needs the raw database date to format it correctly
        // We'll convert the formatted output via get_the_date instead
        
        // Convert dates in order meta
        add_filter('woocommerce_order_item_meta_end', array($this, 'convert_order_meta_dates'), 10, 3);
        
        // Convert dates for Gutenberg editor (works regardless of site language)
        // Note: We DON'T convert REST API dates as Gutenberg needs ISO format for its date picker
        // We only convert display dates via JavaScript
        
        // Add JavaScript for media library dates
        add_action('admin_enqueue_scripts', array($this, 'enqueue_media_date_scripts'), 25);
        
        // Reverted: do not enqueue post edit page publish-date script for now
    }
    
    /**
     * Enqueue scripts for post edit page dates (Publish meta box)
     */
    public function enqueue_post_edit_date_scripts($hook) {
        // Intentionally disabled per user request (revert feature)
        return;
    }
    
    /**
     * Convert post time (used by Gutenberg)
     */
    public function convert_post_time($formatted_time, $format = '', $gmt = false, $post = null) {
        // Only convert in admin area
        if (!is_admin()) {
            return $formatted_time;
        }
        
        if (empty($formatted_time)) {
            return $formatted_time;
        }
        
        // Skip if it's just a format string or contains format characters
        if (preg_match('/\b[gGhHisAa]\b/', $formatted_time)) {
            if (!preg_match('/\d{4}[-\/]\d{1,2}[-\/]\d{1,2}/', $formatted_time) && 
                !preg_match('/[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4}/', $formatted_time)) {
                return $formatted_time;
            }
        }
        
        // Get post object
        if (is_null($post) || !is_object($post)) {
            global $post;
        }
        
        // Skip orders
        if ($post && isset($post->post_type) && $post->post_type === 'shop_order') {
            return $formatted_time;
        }
        
        // Convert the formatted time
        $converted = AIO_WC_Persian_Calendar::convert_to_persian($formatted_time, $format);
        
        // Validate conversion
        if ($converted && $converted !== $formatted_time && !empty($converted)) {
            if (preg_match('/-\d+/', $converted) || preg_match('/\d{5,}/', $converted)) {
                return $formatted_time;
            }
            return $converted;
        }
        
        return $formatted_time;
    }
    
    /**
     * Initialize date picker conversion
     * Only WooCommerce and WordPress list pages (edit.php) are supported
     * Gutenberg editor pages (post.php, post-new.php) are excluded as they don't use jQuery UI datepickers
     */
    private function init_date_picker_conversion() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_date_picker_scripts'), 20);
        
        if ($this->wc_dates_enabled || $this->wc_pickers_enabled) {
            add_filter('posts_where', array($this, 'convert_order_date_filter_query'), 10, 2);
        }
    }
    
    /**
     * Convert order date filter query - handles the date filter dropdown
     * This doesn't modify the query, but we'll handle display via JavaScript
     */
    public function convert_order_date_filter_query($where, $query) {
        // This is a placeholder - actual conversion happens in JavaScript
        // to avoid breaking WooCommerce's query logic
        return $where;
    }
    
    /**
     * Convert order date - DISABLED to prevent breaking order table
     * Date conversion is handled via JavaScript for display only
     */
    public function convert_order_date($date, $order) {
        // Don't convert dates here as it breaks WooCommerce order table functionality
        // Dates will be converted on the frontend via JavaScript for display purposes only
        return $date;
    }
    
    /**
     * Convert display date (for list tables and frontend)
     */
    public function convert_display_date($the_date, $d = '', $post = null) {
        // Only convert in admin area
        if (!is_admin()) {
            return $the_date;
        }
        
        if (empty($the_date)) {
            return $the_date;
        }
        
        // Check if we're on media library or media edit page - skip PHP conversion entirely
        // Let JavaScript handle all media dates to avoid double conversion issues
        global $pagenow;
        if ($pagenow === 'upload.php') {
            return $the_date;
        }
        
        // For media edit page, also skip PHP conversion
        if ($pagenow === 'post.php' && isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
            if ($post_id > 0 && get_post_type($post_id) === 'attachment') {
                return $the_date;
            }
        }
        
        // Skip if the date string looks like it contains format characters (like "g", "a", etc.)
        // Format characters shouldn't be converted
        if (preg_match('/\b[gGhHisAa]\b/', $the_date)) {
            // This might be a format string or already formatted date with format chars
            // Only convert if it looks like an actual date, not a format string
            // Also check for Persian month names (when site language is Persian)
            $has_persian_month = preg_match('/(ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)/u', $the_date);
            $has_english_month = preg_match('/[A-Za-z]{3,9}\s+[\d۰-۹]+,?\s+[\d۰-۹]{4}/u', $the_date); // Allow Persian numerals with English months
            $has_numeric_date = preg_match('/\d{4}[-\/]\d{1,2}[-\/]\d{1,2}/', $the_date);
            
            if (!$has_numeric_date && !$has_english_month && !$has_persian_month) {
                return $the_date;
            }
        }
        
        // Skip if the date string is clearly not a date (contains format-like patterns)
        // Check for patterns like "g:27 a" which are time format strings
        if (preg_match('/\b[gGhH]:\d+\s*[aApP]\b/i', $the_date)) {
            // This contains time format, extract just the date part if possible
            $date_part = preg_replace('/\s+at\s+.*$/i', '', $the_date);
            $date_part = trim($date_part);
            if ($date_part && $date_part !== $the_date) {
                $the_date = $date_part;
            } else {
                // Can't extract date part, skip conversion
                return $the_date;
            }
        }
        
        // Get post object - try multiple methods
        if (is_null($post) || !is_object($post)) {
            global $post;
        }
        
        // If we still don't have a post, try to get it from the global query
        if (!$post || !isset($post->post_type)) {
            global $wp_query;
            if (isset($wp_query->post) && $wp_query->post) {
                $post = $wp_query->post;
            }
        }
        
        // Determine post type
        $post_type = null;
        if ($post && isset($post->post_type)) {
            $post_type = $post->post_type;
        } elseif (isset($_GET['post_type'])) {
            $post_type = sanitize_text_field($_GET['post_type']);
        }
        
        // Check if date contains Persian language month names (Gregorian months in Persian)
        // These need to be converted even if they contain Persian numerals
        $has_persian_lang_month = preg_match('/(ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)/u', $the_date);
        
        // Check if it's already a Persian calendar date (contains Persian calendar month names)
        $has_persian_calendar_month = preg_match('/(فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند)/u', $the_date);
        
        // If it's already a Persian calendar date, don't convert again
        if ($has_persian_calendar_month) {
            return $the_date;
        }
        
        // For orders: Convert dates with Persian language month names
        // When site language is Persian, order dates show with Persian month names but are still Gregorian
        // We need to convert these to Persian calendar
        if ($post_type === 'shop_order') {
            // Only convert if it has Persian language month names (when site language is Persian)
            // This is safe because we're only converting the display text, not the actual order data
            if (!$has_persian_lang_month) {
                // Skip orders without Persian month names to avoid breaking WooCommerce functionality
                return $the_date;
            }
            // If it has Persian language month names, allow conversion (it's still a Gregorian date)
        }
        
        // Skip attachments/media - they're handled via JavaScript to avoid double conversion
        // The media library uses complex date formatting that breaks with PHP conversion
        if ($post_type === 'attachment') {
            return $the_date;
        }
        
        // Skip if it has weird year (5+ digits) and doesn't have Persian language month
        // This indicates it might be already incorrectly converted
        if (preg_match('/\d{5,}/', $the_date) && !$has_persian_lang_month) {
            return $the_date;
        }
        
        // Convert dates for all other post types (posts, pages, products, etc.)
        // Use the format parameter if provided, otherwise use WordPress date format
        $format = !empty($d) ? $d : get_option('date_format', 'Y/m/d');
        $converted = AIO_WC_Persian_Calendar::convert_to_persian($the_date, $format);
        
        // Only return converted if it's actually different and valid
        // Also check that converted doesn't contain obviously wrong values
        if ($converted && $converted !== $the_date && !empty($converted)) {
            // Validate: converted date shouldn't contain negative numbers or extremely large years
            if (preg_match('/-\d+/', $converted) || preg_match('/\d{5,}/', $converted)) {
                // Conversion produced invalid result, return original
                return $the_date;
            }
            return $converted;
        }
        
        return $the_date;
    }
    
    /**
     * Enqueue scripts for media library date conversion
     */
    public function enqueue_media_date_scripts($hook) {
        // Only on media library and media edit pages
        if ($hook !== 'upload.php' && $hook !== 'post.php') {
            return;
        }
        
        // Check if we're on media edit page
        if ($hook === 'post.php') {
            global $post;
            if (!$post || $post->post_type !== 'attachment') {
                return;
            }
        }
        
        // Only if WordPress dates conversion is enabled
        if (!$this->wp_dates_enabled) {
            return;
        }
        
        // Enqueue Persian numeral converter (must load first)
        wp_enqueue_script(
            'aio-wc-persian-numerals',
            AIO_WC_PLUGIN_URL . 'assets/js/persian-numerals.js',
            array(),
            AIO_WC_VERSION,
            true
        );
        
        // Enqueue Persian date converter library
        wp_enqueue_script(
            'aio-wc-persian-date',
            AIO_WC_PLUGIN_URL . 'assets/js/persian-date.js',
            array('aio-wc-persian-numerals'),
            AIO_WC_VERSION,
            true
        );
        
        // Enqueue media date conversion script
        wp_enqueue_script(
            'aio-wc-media-dates',
            AIO_WC_PLUGIN_URL . 'assets/js/media-dates.js',
            array('aio-wc-persian-date', 'jquery'),
            AIO_WC_VERSION,
            true
        );
    }
    
    /**
     * Add date column
     */
    public function add_date_column($columns) {
        // Date column already exists, we'll modify it via render
        return $columns;
    }
    
    /**
     * Render date column
     */
    public function render_date_column($column, $post_id) {
        if ($column === 'order_date') {
            if (!function_exists('wc_get_order')) {
                return;
            }
            $order = wc_get_order($post_id);
            if ($order) {
                $date = $order->get_date_created();
                if ($date) {
                    $persian_date = AIO_WC_Persian_Calendar::convert_to_persian($date->date('Y-m-d H:i:s'));
                    echo '<time>' . esc_html($persian_date) . '</time>';
                }
            }
        }
    }
    
    /**
     * Convert order meta dates
     */
    public function convert_order_meta_dates($item_id, $item, $order) {
        // This is a placeholder for future date conversion in order meta
        // You can extend this to convert specific meta fields
    }
    
    /**
     * Convert order list dates (for HPOS)
     */
    private function convert_order_list_dates() {
        // Don't hook into woocommerce_admin_order_date_format as it can break WooCommerce
        // Date conversion is handled via get_the_date filter instead
        // add_filter('woocommerce_admin_order_date_format', array($this, 'format_order_date'), 10, 2);
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'modify_order_columns'), 20);
    }
    
    /**
     * Format order date for display
     * DISABLED - This filter can break WooCommerce order table functionality
     * Date conversion is handled via get_the_date filter instead
     */
    public function format_order_date($date, $order) {
        // Don't use this filter as it can break WooCommerce
        // Return date as-is to prevent breaking order table functionality
        return $date;
    }
    
    /**
     * Modify order columns
     */
    public function modify_order_columns($columns) {
        return $columns;
    }
    
    /**
     * Add date picker script (removed - now handled by JavaScript)
     */
    public function add_date_picker_script() {
        // This method is kept for compatibility but date picker is now handled via JavaScript
    }
    
    /**
     * Enqueue date picker scripts
     */
    public function enqueue_date_picker_scripts($hook) {
        // Load on WooCommerce admin pages
        $woocommerce_pages = array(
            'wc-orders',
            'wc-reports',
            'shop_order',
            'edit-shop_order',
            'woocommerce_page_wc-reports',
            'woocommerce_page_wc-settings'
        );
        
        $is_woocommerce_page = false;
        foreach ($woocommerce_pages as $page) {
            if (strpos($hook, $page) !== false) {
                $is_woocommerce_page = true;
                break;
            }
        }
        
        // Check for WooCommerce order edit pages (these use classic editor, not Gutenberg)
        if (isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order') {
            $is_woocommerce_page = true;
        }
        
        // Check if it's a WooCommerce order edit page (post.php with shop_order)
        if ($hook === 'post.php' && isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
            if ($post_id > 0) {
                $post_type = get_post_type($post_id);
                if ($post_type === 'shop_order') {
                    $is_woocommerce_page = true;
                }
            }
        }
        
        // Check if it's a list page (edit.php) - but exclude post edit pages (post.php, post-new.php)
        // List pages need date conversion for both date picker filters AND date column display
        $is_wp_list_page = false;
        if (strpos($hook, 'edit.php') !== false && $hook !== 'post.php' && $hook !== 'post-new.php') {
            $is_wp_list_page = true;
        } elseif ($hook === 'upload.php') {
            $is_wp_list_page = true;
        }
        
        // Check if it's a Gutenberg editor page (post.php, post-new.php)
        // But exclude WooCommerce orders which use classic editor
        $is_gutenberg_page = false;
        if (($hook === 'post.php' || $hook === 'post-new.php') && !$is_woocommerce_page) {
            $is_gutenberg_page = true;
        }
        
        $should_load_wp_lists = $this->wp_pickers_enabled && $is_wp_list_page;
        $should_load_gutenberg = $this->wp_pickers_enabled && $is_gutenberg_page;
        $should_load_wc = ($this->wc_dates_enabled || $this->wc_pickers_enabled) && $is_woocommerce_page;
        
        // Only load scripts if at least one enabled area matches the current screen
        if (!$should_load_wp_lists && !$should_load_gutenberg && !$should_load_wc) {
            return;
        }
        
        // Enqueue Persian numeral converter (must load first)
        wp_enqueue_script(
            'aio-wc-persian-numerals',
            AIO_WC_PLUGIN_URL . 'assets/js/persian-numerals.js',
            array(),
            AIO_WC_VERSION,
            true
        );
        
        // Enqueue Persian date converter library
        wp_enqueue_script(
            'aio-wc-persian-date',
            AIO_WC_PLUGIN_URL . 'assets/js/persian-date.js',
            array('aio-wc-persian-numerals'),
            AIO_WC_VERSION,
            true
        );
        
        // Enqueue Persian date picker (classic screens)
        if ($should_load_wp_lists || $should_load_wc) {
            wp_enqueue_script(
                'aio-wc-persian-datepicker',
                AIO_WC_PLUGIN_URL . 'assets/js/persian-datepicker.js',
                array('jquery', 'aio-wc-persian-date', 'aio-wc-persian-numerals'),
                AIO_WC_VERSION,
                true
            );
            
            // Enqueue WooCommerce order dates conversion script
            if ($should_load_wc && $this->wc_dates_enabled) {
                wp_enqueue_script(
                    'aio-wc-woocommerce-order-dates',
                    AIO_WC_PLUGIN_URL . 'assets/js/woocommerce-order-dates.js',
                    array('jquery', 'aio-wc-persian-date', 'aio-wc-persian-numerals'),
                    AIO_WC_VERSION,
                    true
                );
            }
            
            // Enqueue Persian date picker styles
            wp_enqueue_style(
                'aio-wc-persian-datepicker',
                AIO_WC_PLUGIN_URL . 'assets/css/persian-datepicker.css',
                array(),
                AIO_WC_VERSION
            );
        }
        
        // Enqueue Gutenberg Persian calendar support (only for Gutenberg pages)
        if ($should_load_gutenberg) {
            wp_enqueue_script(
                'aio-wc-gutenberg-persian-calendar',
                AIO_WC_PLUGIN_URL . 'assets/js/gutenberg-persian-calendar.js',
                array('aio-wc-persian-date', 'aio-wc-persian-numerals', 'wp-date'),
                AIO_WC_VERSION,
                true
            );
        }
        
        // Localize script with Persian month/day names (for both classic and Gutenberg)
        $localize_script = $should_load_gutenberg ? 'aio-wc-gutenberg-persian-calendar' : 'aio-wc-persian-datepicker';
        wp_localize_script($localize_script, 'aioWcDatePicker', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aio_wc_date_conversion'),
            'persian_months' => array(
                'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
                'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
            ),
            'persian_days' => array(
                'شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'
            ),
            'persian_day_abbr' => array(
                'ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'
            )
        ));
        
        // Add AJAX handlers for date conversion
        add_action('wp_ajax_aio_wc_convert_to_persian', array($this, 'ajax_convert_to_persian'));
        add_action('wp_ajax_aio_wc_convert_to_gregorian', array($this, 'ajax_convert_to_gregorian'));
    }
    
    /**
     * AJAX: Convert Gregorian date to Persian
     */
    public function ajax_convert_to_persian() {
        check_ajax_referer('aio_wc_date_conversion', 'nonce');
        
        $date = sanitize_text_field($_POST['date'] ?? '');
        if (empty($date)) {
            wp_send_json_error(array('message' => __('Invalid date.', 'aio-woocommerce')));
        }
        
        $persian_date = AIO_WC_Persian_Calendar::convert_to_persian($date);
        wp_send_json_success(array('persian_date' => $persian_date));
    }
    
    /**
     * AJAX: Convert Persian date to Gregorian
     */
    public function ajax_convert_to_gregorian() {
        check_ajax_referer('aio_wc_date_conversion', 'nonce');
        
        $date = sanitize_text_field($_POST['date'] ?? '');
        if (empty($date)) {
            wp_send_json_error(array('message' => __('Invalid date.', 'aio-woocommerce')));
        }
        
        $timestamp = AIO_WC_Persian_Calendar::persian_to_timestamp($date);
        if ($timestamp === false) {
            wp_send_json_error(array('message' => __('Invalid Persian date.', 'aio-woocommerce')));
        }
        
        $gregorian_date = date('Y-m-d', $timestamp);
        wp_send_json_success(array('gregorian_date' => $gregorian_date));
    }
}

