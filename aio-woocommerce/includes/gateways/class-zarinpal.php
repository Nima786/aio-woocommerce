<?php
/**
 * Zarinpal Payment Gateway
 * 
 * Integrates Zarinpal payment gateway with WooCommerce
 * Based on Zarinpal REST API v4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define the default Sandbox Merchant ID for Zarinpal
define('AIO_WC_ZARINPAL_SANDBOX_MERCHANT_ID', '00000000-0000-0000-0000-000000000000');

class AIO_WC_Gateway_Zarinpal extends WC_Payment_Gateway {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'aio_wc_zarinpal';
        $this->icon               = '';
        $this->has_fields         = false;
        $this->method_title       = __('Zarinpal', 'aio-woocommerce');
        $this->method_description = __('Accept payments via Zarinpal gateway. Supports both Sandbox and Live modes.', 'aio-woocommerce');
        
        // Load default settings first
        $this->load_settings();
        
        // Define admin form fields
        $this->init_form_fields();
        
        // Initialize WooCommerce settings first (needed for WooCommerce to recognize the gateway)
        $this->init_settings();
        
        // Now load from plugin settings (primary source of truth) and override WooCommerce settings
        $plugin_settings = get_option('aio_wc_settings', array());
        $gateway_settings = isset($plugin_settings['payment_gateway_zarinpal']) && is_array($plugin_settings['payment_gateway_zarinpal']) 
            ? $plugin_settings['payment_gateway_zarinpal'] 
            : array();
        
        // Plugin settings take precedence - always use them if they exist
        if (!empty($gateway_settings)) {
            $this->enabled                   = isset($gateway_settings['enabled']) && $gateway_settings['enabled'] === 'yes' ? 'yes' : 'no';
            $this->title                     = isset($gateway_settings['title']) && !empty($gateway_settings['title']) ? $gateway_settings['title'] : $this->title;
            $this->description               = isset($gateway_settings['description']) ? $gateway_settings['description'] : $this->description;
            $this->merchant_id               = isset($gateway_settings['merchant_id']) ? $gateway_settings['merchant_id'] : $this->merchant_id;
            $this->access_token              = isset($gateway_settings['access_token']) ? $gateway_settings['access_token'] : $this->access_token;
            $this->sandbox_mode              = isset($gateway_settings['sandbox_mode']) && $gateway_settings['sandbox_mode'] === 'yes' ? 'yes' : 'no';
            $this->zarinpal_gate             = isset($gateway_settings['zarinpal_gate']) ? $gateway_settings['zarinpal_gate'] : $this->zarinpal_gate;
            $this->commission_deduct_from    = isset($gateway_settings['commission_from']) ? $gateway_settings['commission_from'] : (isset($gateway_settings['commission_deduct_from']) ? $gateway_settings['commission_deduct_from'] : $this->commission_deduct_from);
            $this->success_message           = isset($gateway_settings['success_message']) ? $gateway_settings['success_message'] : $this->success_message;
            $this->failed_message            = isset($gateway_settings['failed_message']) ? $gateway_settings['failed_message'] : $this->failed_message;
            $this->post_purchase_description = isset($gateway_settings['post_purchase_description']) ? $gateway_settings['post_purchase_description'] : $this->post_purchase_description;
            $this->trust_logo_code           = isset($gateway_settings['trust_logo_code']) ? $gateway_settings['trust_logo_code'] : $this->trust_logo_code;
            
            // Sync to WooCommerce options so WooCommerce knows the gateway state
            $wc_settings = array(
                'enabled'                  => $this->enabled,
                'title'                    => $this->title,
                'description'              => $this->description,
                'merchant_id'              => $this->merchant_id,
                'access_token'             => $this->access_token,
                'sandbox_mode'             => $this->sandbox_mode,
                'zarinpal_gate'            => $this->zarinpal_gate,
                'commission_deduct_from'   => $this->commission_deduct_from,
                'success_message'          => $this->success_message,
                'failed_message'           => $this->failed_message,
                'post_purchase_description' => $this->post_purchase_description,
                'trust_logo_code'          => $this->trust_logo_code,
            );
            // Use update_option directly (not through WooCommerce) to avoid triggering hooks during construction
            update_option('woocommerce_' . $this->id . '_settings', $wc_settings, false);
        } else {
            // If no plugin settings, use WooCommerce settings as fallback
            if (isset($this->settings) && is_array($this->settings)) {
                $this->enabled                   = isset($this->settings['enabled']) ? $this->settings['enabled'] : $this->enabled;
                $this->title                     = isset($this->settings['title']) ? $this->settings['title'] : $this->title;
                $this->description               = isset($this->settings['description']) ? $this->settings['description'] : $this->description;
                $this->merchant_id               = isset($this->settings['merchant_id']) ? $this->settings['merchant_id'] : $this->merchant_id;
                $this->access_token              = isset($this->settings['access_token']) ? $this->settings['access_token'] : $this->access_token;
                $this->sandbox_mode              = isset($this->settings['sandbox_mode']) && $this->settings['sandbox_mode'] === 'yes' ? 'yes' : $this->sandbox_mode;
                $this->zarinpal_gate             = isset($this->settings['zarinpal_gate']) ? $this->settings['zarinpal_gate'] : $this->zarinpal_gate;
                $this->commission_deduct_from    = isset($this->settings['commission_deduct_from']) ? $this->settings['commission_deduct_from'] : (isset($this->settings['commission_from']) ? $this->settings['commission_from'] : $this->commission_deduct_from);
                $this->success_message           = isset($this->settings['success_message']) ? $this->settings['success_message'] : $this->success_message;
                $this->failed_message            = isset($this->settings['failed_message']) ? $this->settings['failed_message'] : $this->failed_message;
                $this->post_purchase_description = isset($this->settings['post_purchase_description']) ? $this->settings['post_purchase_description'] : $this->post_purchase_description;
                $this->trust_logo_code           = isset($this->settings['trust_logo_code']) ? $this->settings['trust_logo_code'] : $this->trust_logo_code;
            }
        }

        // If in sandbox mode and no merchant ID is provided, use the default test ID.
        if ($this->sandbox_mode === 'yes' && empty($this->merchant_id)) {
            $this->merchant_id = AIO_WC_ZARINPAL_SANDBOX_MERCHANT_ID;
        }
        
        // Validate merchant ID
        if (empty($this->merchant_id) && $this->enabled === 'yes') {
            add_action('admin_notices', array($this, 'merchant_id_missing_notice'));
        }
        
        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_aio_wc_zarinpal_return', array($this, 'handle_return'));
        add_action('woocommerce_api_aio_wc_zarinpal_verify', array($this, 'handle_verify'));
        
        // Sync WooCommerce settings back to plugin settings when updated directly in WooCommerce
        add_action('update_option_woocommerce_' . $this->id . '_settings', array($this, 'sync_wc_settings_to_plugin'), 10, 2);
    }
    
    /**
     * Load settings from plugin options
     */
    private function load_settings() {
        // Set default values - WooCommerce will load actual settings via init_settings()
        $this->enabled       = 'no';
        $this->title         = __('پرداخت امن زرین پال', 'aio-woocommerce');
        $this->description   = __('پرداخت امن به وسیله کلیه کارتهای عضو شتاب از طریق درگاه زرین پال', 'aio-woocommerce');
        $this->merchant_id   = '';
        $this->access_token  = '';
        $this->sandbox_mode  = 'yes';
        $this->zarinpal_gate = 'normal';
        $this->commission_deduct_from = 'merchant';
        $this->success_message = __('با تشکر از شما سفارش شما با موفقیت پرداخت شد', 'aio-woocommerce');
        $this->failed_message = __('پرداخت شما ناموفق بوده است. لطفاً مجدداً تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید', 'aio-woocommerce');
        $this->post_purchase_description = '';
        $this->trust_logo_code = '<style>#zarinpal{margin:auto} #zarinpal img {width: 80px;}</style><div id="zarinpal"><script src="https://www.zarinpal.com/webservice/TrustCode" type="text/javascript"></script></div>';
    }
    
    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'aio-woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('فعالسازی درگاه زرین پال', 'aio-woocommerce'),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __('Gateway Title', 'aio-woocommerce'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'aio-woocommerce'),
                'default'     => __('پرداخت امن زرین پال', 'aio-woocommerce'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Gateway Description', 'aio-woocommerce'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'aio-woocommerce'),
                'default'     => __('پرداخت امن به وسیله کلیه کارتهای عضو شتاب از طریق درگاه زرین پال', 'aio-woocommerce'),
                'desc_tip'    => true,
            ),
            'merchant_id' => array(
                'title'       => __('Merchant Code', 'aio-woocommerce'),
                'type'        => 'text',
                'description' => __('Enter your Zarinpal Merchant ID (Merchant Code).', 'aio-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
                'required'    => true,
            ),
            'access_token' => array(
                'title'       => __('Access Token', 'aio-woocommerce'),
                'type'        => 'text',
                'description' => __('توکن دسترسی (اختیاری ویژه سرویس استرداد وجه)', 'aio-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'sandbox_mode' => array(
                'title'   => __('Test Mode (Sandbox)', 'aio-woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('فعالسازی حالت آزمایشی', 'aio-woocommerce'),
                'default' => 'yes',
            ),
            'zarinpal_gate' => array(
                'title'       => __('Gateway Type', 'aio-woocommerce'),
                'type'        => 'select',
                'description' => __('Choose the gateway type. ZarinGate requires special activation.', 'aio-woocommerce'),
                'default'     => 'normal',
                'options'     => array(
                    'normal'    => __('Normal Gateway', 'aio-woocommerce'),
                    'zaringate' => __('ZarinGate', 'aio-woocommerce'),
                    'zarinlink' => __('ZarinLink', 'aio-woocommerce'),
                ),
                'desc_tip'    => true,
            ),
            'commission_deduct_from' => array(
                'title'       => __('Deduct commission from', 'aio-woocommerce'),
                'type'        => 'select',
                'description' => __('کسر کارمزد از', 'aio-woocommerce'),
                'default'     => 'merchant',
                'options'     => array(
                    'merchant' => __('پذیرنده (پیش فرض)', 'aio-woocommerce'),
                    'customer' => __('مشتری', 'aio-woocommerce'),
                ),
                'desc_tip'    => true,
            ),
            'success_message' => array(
                'title'       => __('Successful Payment Message', 'aio-woocommerce'),
                'type'        => 'textarea',
                'description' => __('پیام پرداخت موفق. می‌توانید از {transaction_id} استفاده کنید.', 'aio-woocommerce'),
                'default'     => __('با تشکر از شما سفارش شما با موفقیت پرداخت شد', 'aio-woocommerce'),
                'desc_tip'    => true,
            ),
            'failed_message' => array(
                'title'       => __('Failed Payment Message', 'aio-woocommerce'),
                'type'        => 'textarea',
                'description' => __('پیام پرداخت ناموفق. می‌توانید از {fault} استفاده کنید.', 'aio-woocommerce'),
                'default'     => __('پرداخت شما ناموفق بوده است. لطفاً مجدداً تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید', 'aio-woocommerce'),
                'desc_tip'    => true,
            ),
            'post_purchase_description' => array(
                'title'       => __('Post-purchase description', 'aio-woocommerce'),
                'type'        => 'textarea',
                'description' => __('توضیحات پس از خرید', 'aio-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'trust_logo_code' => array(
                'title'       => __('Zarinpal Logo Trust Code', 'aio-woocommerce'),
                'type'        => 'textarea',
                'description' => __('کد تراست لوگوی زرین پال را کپی نمایید و در فوتر سایت خود قرار دهید', 'aio-woocommerce'),
                'default'     => '<style>#zarinpal{margin:auto} #zarinpal img {width: 80px;}</style><div id="zarinpal"><script src="https://www.zarinpal.com/webservice/TrustCode" type="text/javascript"></script></div>',
                'css'         => 'font-family: monospace; font-size: 12px;',
            ),
        );
    }
    
    /**
     * Override admin options to redirect to plugin settings page
     * This prevents duplicate settings pages
     */
    public function admin_options() {
        // Redirect to our plugin's payment gateways tab using hash navigation
        $redirect_url = add_query_arg(array(
            'page' => 'aio-woocommerce',
        ), admin_url('admin.php')) . '#payment-gateways';
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Process payment
     * 
     * @param int $order_id Order ID
     * @return array Payment result
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
        
        // Get payment request URL from Zarinpal
        $payment_url = $this->request_payment($order);
        
        if (is_wp_error($payment_url)) {
            wc_add_notice($payment_url->get_error_message(), 'error');
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
        
        // Store order ID in session for verification
        WC()->session->set('aio_wc_zarinpal_order_id', $order_id);
        
        return array(
            'result'   => 'success',
            'redirect' => $payment_url,
        );
    }
    
    /**
     * Request payment from Zarinpal
     * 
     * @param WC_Order $order Order object
     * @return string|WP_Error Payment URL or error
     */
    private function request_payment($order) {
        $merchant_id = $this->merchant_id;
        $amount      = $this->get_order_total_rials($order);
        $callback    = $this->get_callback_url();
        $description = sprintf(__('Order #%s', 'aio-woocommerce'), $order->get_order_number());
        
        // Determine API endpoint based on sandbox mode
        $api_url = $this->sandbox_mode === 'yes'
            ? 'https://sandbox.zarinpal.com/pg/v4/payment/request.json'
            : 'https://api.zarinpal.com/pg/v4/payment/request.json';
        
        // Prepare request data
        $data = array(
            'merchant_id'  => $merchant_id,
            'amount'       => $amount,
            'callback_url' => $callback,
            'description'  => $description,
            'metadata'     => array(
                'email'       => $order->get_billing_email(),
                'mobile'      => $order->get_billing_phone(),
                // *** FIX: Cast the integer Order ID to a string to match API requirements ***
                'order_id'    => (string) $order->get_id(),
            ),
        );
        
        // Add gateway type if not normal
        if ($this->zarinpal_gate !== 'normal') {
            $data['zarin_gate'] = $this->zarinpal_gate === 'zaringate' ? true : false;
        }
        
        // Send request to Zarinpal
        $response = wp_remote_post($api_url, array(
            'body'    => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['data']) || !isset($body['data']['authority'])) {
            $error_message = isset($body['errors']['message']) 
                ? $body['errors']['message'] 
                : __('Failed to initiate payment. Please try again.', 'aio-woocommerce');
            return new WP_Error('zarinpal_error', $error_message);
        }
        
        $authority = $body['data']['authority'];
        
        // Build payment URL
        if ($this->sandbox_mode === 'yes') {
            $payment_url = 'https://sandbox.zarinpal.com/pg/StartPay/' . $authority;
        } else {
            $payment_url = 'https://www.zarinpal.com/pg/StartPay/' . $authority;
        }
        
        // Store authority in order meta
        $order->update_meta_data('_aio_wc_zarinpal_authority', $authority);
        $order->save();
        
        return $payment_url;
    }
    
    /**
     * Handle return from Zarinpal
     */
    public function handle_return() {
        $authority = isset($_GET['Authority']) ? sanitize_text_field($_GET['Authority']) : '';
        $status    = isset($_GET['Status']) ? sanitize_text_field($_GET['Status']) : '';
        
        $order_id = WC()->session->get('aio_wc_zarinpal_order_id');
        
        if (!$order_id && !empty($authority)) {
            // Try to get order ID from authority stored in order meta
            // Support both HPOS and legacy post meta
            if (function_exists('wc_get_orders')) {
                $orders = wc_get_orders(array(
                    'limit'        => 1,
                    'meta_key'     => '_aio_wc_zarinpal_authority',
                    'meta_value'   => $authority,
                    'return'       => 'ids',
                ));
                if (!empty($orders)) {
                    $order_id = $orders[0];
                }
            } else {
                // Fallback for legacy
                global $wpdb;
                $order_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_aio_wc_zarinpal_authority' AND meta_value = %s LIMIT 1",
                    $authority
                ));
            }
        }
        
        if (!$order_id) {
            wc_add_notice(__('Order not found.', 'aio-woocommerce'), 'error');
            wp_redirect(wc_get_cart_url());
            exit;
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wc_add_notice(__('Order not found.', 'aio-woocommerce'), 'error');
            wp_redirect(wc_get_cart_url());
            exit;
        }
        
        // Verify payment
        $this->verify_payment($order, $authority, $status);
    }
    
    /**
     * Handle verification callback
     */
    public function handle_verify() {
        // This can be used for server-to-server verification if needed
        $this->handle_return();
    }
    
    /**
     * Verify payment with Zarinpal
     * 
     * @param WC_Order $order Order object
     * @param string $authority Authority code
     * @param string $status Status from Zarinpal
     */
    private function verify_payment($order, $authority, $status) {
        if ($status !== 'OK') {
            wc_add_notice(__('Payment was cancelled or failed.', 'aio-woocommerce'), 'error');
            wp_redirect($order->get_cancel_order_url());
            exit;
        }
        
        $merchant_id = $this->merchant_id;
        $amount      = $this->get_order_total_rials($order);
        
        // Determine API endpoint
        $api_url = $this->sandbox_mode === 'yes'
            ? 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json'
            : 'https://api.zarinpal.com/pg/v4/payment/verify.json';
        
        // Prepare verification data
        $data = array(
            'merchant_id' => $merchant_id,
            'authority'   => $authority,
            'amount'      => $amount,
        );
        
        // Send verification request
        $response = wp_remote_post($api_url, array(
            'body'    => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            wc_add_notice(__('Payment verification failed. Please contact support.', 'aio-woocommerce'), 'error');
            wp_redirect($order->get_checkout_payment_url());
            exit;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['data']) || !isset($body['data']['code'])) {
            wc_add_notice(__('Payment verification failed. Please contact support.', 'aio-woocommerce'), 'error');
            wp_redirect($order->get_checkout_payment_url());
            exit;
        }
        
        $code = $body['data']['code'];
        
        // Check verification code
        // 100 = Success
        // 101 = Already verified
        if ($code == 100 || $code == 101) {
            // Payment successful
            $ref_id = isset($body['data']['ref_id']) ? $body['data']['ref_id'] : '';
            
            // Update order
            $order->payment_complete($ref_id);
            $order->add_order_note(sprintf(__('Zarinpal payment completed. Ref ID: %s', 'aio-woocommerce'), $ref_id));
            
            // Store ref_id in order meta
            $order->update_meta_data('_aio_wc_zarinpal_ref_id', $ref_id);
            $order->update_meta_data('_aio_wc_zarinpal_authority', $authority);
            $order->save();
            
            // Clear session
            WC()->session->__unset('aio_wc_zarinpal_order_id');
            
            // Show success message with transaction ID (if custom message is set)
            if (!empty($this->success_message)) {
                $message = str_replace('{transaction_id}', $ref_id, $this->success_message);
                // Store in session to show on thank you page
                WC()->session->set('aio_wc_zarinpal_success_message', $message);
            }
            
            // Redirect to thank you page
            wp_redirect($this->get_return_url($order));
            exit;
        } else {
            // Payment failed
            $error_message = $this->get_error_message($code);
            $failed_msg = !empty($this->failed_message) 
                ? str_replace('{fault}', $error_message, $this->failed_message)
                : $error_message;
            wc_add_notice($failed_msg, 'error');
            wp_redirect($order->get_checkout_payment_url());
            exit;
        }
    }
    
    /**
     * Sync WooCommerce settings back to plugin settings
     * This ensures that if someone enables/disables the gateway in WooCommerce settings,
     * it syncs back to our plugin settings
     * 
     * @param array $old_value Old WooCommerce settings
     * @param array $new_value New WooCommerce settings
     */
    public function sync_wc_settings_to_plugin($old_value, $new_value) {
        // Get current plugin settings
        $plugin_settings = get_option('aio_wc_settings', array());
        
        // Update plugin settings with WooCommerce settings
        if (!isset($plugin_settings['payment_gateway_zarinpal'])) {
            $plugin_settings['payment_gateway_zarinpal'] = array();
        }
        
        $plugin_settings['payment_gateway_zarinpal']['enabled'] = isset($new_value['enabled']) ? $new_value['enabled'] : 'no';
        $plugin_settings['payment_gateway_zarinpal']['title'] = isset($new_value['title']) ? $new_value['title'] : __('پرداخت امن زرین پال', 'aio-woocommerce');
        $plugin_settings['payment_gateway_zarinpal']['description'] = isset($new_value['description']) ? $new_value['description'] : '';
        $plugin_settings['payment_gateway_zarinpal']['merchant_id'] = isset($new_value['merchant_id']) ? $new_value['merchant_id'] : '';
        $plugin_settings['payment_gateway_zarinpal']['access_token'] = isset($new_value['access_token']) ? $new_value['access_token'] : '';
        $plugin_settings['payment_gateway_zarinpal']['sandbox_mode'] = isset($new_value['sandbox_mode']) ? $new_value['sandbox_mode'] : 'no';
        $plugin_settings['payment_gateway_zarinpal']['zarinpal_gate'] = isset($new_value['zarinpal_gate']) ? $new_value['zarinpal_gate'] : 'normal';
        $plugin_settings['payment_gateway_zarinpal']['commission_from'] = isset($new_value['commission_from']) ? $new_value['commission_from'] : (isset($new_value['commission_deduct_from']) ? $new_value['commission_deduct_from'] : 'merchant');
        $plugin_settings['payment_gateway_zarinpal']['success_message'] = isset($new_value['success_message']) ? $new_value['success_message'] : __('با تشکر از شما سفارش شما با موفقیت پرداخت شد', 'aio-woocommerce');
        $plugin_settings['payment_gateway_zarinpal']['failed_message'] = isset($new_value['failed_message']) ? $new_value['failed_message'] : __('پرداخت شما ناموفق بوده است. لطفاً مجدداً تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید', 'aio-woocommerce');
        $plugin_settings['payment_gateway_zarinpal']['post_purchase_description'] = isset($new_value['post_purchase_description']) ? $new_value['post_purchase_description'] : '';
        $plugin_settings['payment_gateway_zarinpal']['trust_logo_code'] = isset($new_value['trust_logo_code']) ? $new_value['trust_logo_code'] : '';
        
        // Save plugin settings
        // Temporarily disable the admin sync hook to avoid infinite loop
        if (class_exists('AIO_WC_Admin')) {
            $admin_instance = AIO_WC_Admin::get_instance();
            if (has_action('update_option_aio_wc_settings', array($admin_instance, 'sync_payment_gateway_settings_on_save'))) {
                remove_action('update_option_aio_wc_settings', array($admin_instance, 'sync_payment_gateway_settings_on_save'));
            }
        }
        update_option('aio_wc_settings', $plugin_settings);
        if (class_exists('AIO_WC_Admin') && isset($admin_instance)) {
            add_action('update_option_aio_wc_settings', array($admin_instance, 'sync_payment_gateway_settings_on_save'), 10, 2);
        }
    }
    
    /**
     * Get error message by code
     * 
     * @param int $code Error code
     * @return string Error message
     */
    private function get_error_message($code) {
        $messages = array(
            -9  => __('خطای اعتبار سنجی', 'aio-woocommerce'),
            -10 => __('IP یا مرچنت کد پذیرنده صحیح نیست', 'aio-woocommerce'),
            -11 => __('مرچنت کد پذیرنده صحیح نیست', 'aio-woocommerce'),
            -12 => __('تلاش بیش از حد در یک بازه زمانی کوتاه', 'aio-woocommerce'),
            -15 => __('ترمینال شما به حالت تعلیق در آمده است', 'aio-woocommerce'),
            -16 => __('سطح تایید پذیرنده پایین تر از سطح نقره ای است', 'aio-woocommerce'),
            -30 => __('مبلغ از حداقل تراکنش کمتر است', 'aio-woocommerce'),
            -31 => __('مبلغ از حداکثر تراکنش بیشتر است', 'aio-woocommerce'),
            -32 => __('مبلغ از سقف تراکنش بیشتر است', 'aio-woocommerce'),
            -33 => __('مبلغ از سقف ماهانه تراکنش بیشتر است', 'aio-woocommerce'),
            -34 => __('مبلغ از سقف سالانه تراکنش بیشتر است', 'aio-woocommerce'),
            -40 => __('خطا در دریافت اطلاعات تسویه', 'aio-woocommerce'),
            -50 => __('مبلغ پرداخت شده با مقدار مبلغ درخواستی مطابقت ندارد', 'aio-woocommerce'),
            -51 => __('پرداخت ناموفق', 'aio-woocommerce'),
            -52 => __('خطای غیر منتظره', 'aio-woocommerce'),
            -53 => __('اتصال به درگاه برقرار نشد', 'aio-woocommerce'),
            -54 => __('عملیات ناموفق', 'aio-woocommerce'),
            -101 => __('تراکنش یافت نشد', 'aio-woocommerce'),
        );
        
        return isset($messages[$code]) 
            ? $messages[$code] 
            : sprintf(__('خطای ناشناخته با کد: %s', 'aio-woocommerce'), $code);
    }
    
    /**
     * Get callback URL
     * 
     * @return string Callback URL
     */
    private function get_callback_url() {
        return add_query_arg('wc-api', 'aio_wc_zarinpal_return', home_url('/'));
    }
    
    /**
     * Get order total in Rials
     * 
     * @param WC_Order $order Order object
     * @return int Amount in Rials
     */
    private function get_order_total_rials($order) {
        $total = $order->get_total();
        
        // Convert to Rials (assuming Toman, multiply by 10)
        // Adjust this based on your currency setup
        if (get_woocommerce_currency() === 'IRR') {
            // Already in Rials
            return intval($total);
        } else {
            // Assuming Toman, convert to Rials
            return intval($total * 10);
        }
    }
    
    /**
     * Show notice if merchant ID is missing
     */
    public function merchant_id_missing_notice() {
        if (isset($_GET['page']) && $_GET['page'] === 'wc-settings' && 
            isset($_GET['tab']) && $_GET['tab'] === 'checkout' &&
            isset($_GET['section']) && $_GET['section'] === $this->id) {
            ?>
            <div class="notice notice-error">
                <p><?php echo esc_html__('مرچنت کد درگاه زرین پال خالی است. لطفاً آن را در تنظیمات درگاه وارد نمایید', 'aio-woocommerce'); ?></p>
            </div>
            <?php
        }
    }
}