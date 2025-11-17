<?php
/**
 * Zibal Payment Gateway
 * 
 * Integrates Zibal payment gateway with WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define the default Sandbox Merchant ID for Zibal
define('AIO_WC_ZIBAL_SANDBOX_MERCHANT_ID', 'zibal');

class AIO_WC_Gateway_Zibal extends WC_Payment_Gateway {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'aio_wc_zibal';
        $this->icon               = '';
        $this->has_fields         = false;
        $this->method_title       = __('Zibal', 'aio-woocommerce');
        $this->method_description = __('Accept payments via Zibal gateway. Supports both Sandbox and Live modes.', 'aio-woocommerce');
        
        // Load default settings first
        $this->load_settings();
        
        // Define admin form fields
        $this->init_form_fields();
        
        // Initialize WooCommerce settings first (needed for WooCommerce to recognize the gateway)
        $this->init_settings();
        
        // Now load from plugin settings (primary source of truth) and override WooCommerce settings
        $plugin_settings = get_option('aio_wc_settings', array());
        $gateway_settings = isset($plugin_settings['payment_gateway_zibal']) && is_array($plugin_settings['payment_gateway_zibal']) 
            ? $plugin_settings['payment_gateway_zibal'] 
            : array();
        
        // Plugin settings take precedence - always use them if they exist
        if (!empty($gateway_settings)) {
            $this->enabled         = isset($gateway_settings['enabled']) && $gateway_settings['enabled'] === 'yes' ? 'yes' : 'no';
            $this->title           = isset($gateway_settings['title']) && !empty($gateway_settings['title']) ? $gateway_settings['title'] : $this->title;
            $this->description     = isset($gateway_settings['description']) ? $gateway_settings['description'] : $this->description;
            $this->merchant_id     = isset($gateway_settings['merchant_id']) ? $gateway_settings['merchant_id'] : $this->merchant_id;
            $this->sandbox_mode    = isset($gateway_settings['sandbox_mode']) && $gateway_settings['sandbox_mode'] === 'yes' ? 'yes' : $this->sandbox_mode;
            $this->success_message = isset($gateway_settings['success_message']) ? $gateway_settings['success_message'] : $this->success_message;
            $this->failed_message  = isset($gateway_settings['failed_message']) ? $gateway_settings['failed_message'] : $this->failed_message;
            
            // Immediately sync to WooCommerce options so WooCommerce recognizes the gateway state
            $wc_settings = array(
                'enabled'         => $this->enabled,
                'title'           => $this->title,
                'description'     => $this->description,
                'merchant_id'     => $this->merchant_id,
                'sandbox_mode'    => $this->sandbox_mode,
                'success_message' => $this->success_message,
                'failed_message'  => $this->failed_message,
            );
            // Use update_option directly (not through WooCommerce) to avoid triggering hooks during construction
            update_option('woocommerce_' . $this->id . '_settings', $wc_settings, false);
        } else {
            // If no plugin settings, use WooCommerce settings as fallback
            if (isset($this->settings) && is_array($this->settings)) {
                $this->enabled         = isset($this->settings['enabled']) ? $this->settings['enabled'] : $this->enabled;
                $this->title           = isset($this->settings['title']) ? $this->settings['title'] : $this->title;
                $this->description     = isset($this->settings['description']) ? $this->settings['description'] : $this->description;
                $this->merchant_id     = isset($this->settings['merchant_id']) ? $this->settings['merchant_id'] : $this->merchant_id;
                $this->sandbox_mode    = isset($this->settings['sandbox_mode']) && $this->settings['sandbox_mode'] === 'yes' ? 'yes' : $this->sandbox_mode;
                $this->success_message = isset($this->settings['success_message']) ? $this->settings['success_message'] : $this->success_message;
                $this->failed_message  = isset($this->settings['failed_message']) ? $this->settings['failed_message'] : $this->failed_message;
            }
        }

        // *** FIX STARTS HERE ***
        // If in sandbox mode, the merchant ID MUST be the string 'zibal'.
        if ($this->sandbox_mode === 'yes') {
            $this->merchant_id = AIO_WC_ZIBAL_SANDBOX_MERCHANT_ID;
        }

        // Validate merchant ID
        if (empty($this->merchant_id) && $this->enabled === 'yes') {
            add_action('admin_notices', array($this, 'merchant_id_missing_notice'));
        }
        // *** FIX ENDS HERE ***
        
        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        // Register callback handler - WooCommerce API endpoint matches gateway ID
        add_action('woocommerce_api_' . $this->id, array($this, 'handle_return'));
        
        // Sync WooCommerce settings back to plugin settings when updated directly in WooCommerce
        add_action('update_option_woocommerce_' . $this->id . '_settings', array($this, 'sync_wc_settings_to_plugin'), 10, 2);
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
        if (!isset($plugin_settings['payment_gateway_zibal'])) {
            $plugin_settings['payment_gateway_zibal'] = array();
        }
        
        $plugin_settings['payment_gateway_zibal']['enabled'] = isset($new_value['enabled']) ? $new_value['enabled'] : 'no';
        $plugin_settings['payment_gateway_zibal']['title'] = isset($new_value['title']) ? $new_value['title'] : __('زیبال', 'aio-woocommerce');
        $plugin_settings['payment_gateway_zibal']['description'] = isset($new_value['description']) ? $new_value['description'] : __('پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه زیبال', 'aio-woocommerce');
        $plugin_settings['payment_gateway_zibal']['merchant_id'] = isset($new_value['merchant_id']) ? $new_value['merchant_id'] : '';
        $plugin_settings['payment_gateway_zibal']['sandbox_mode'] = isset($new_value['sandbox_mode']) ? $new_value['sandbox_mode'] : 'no';
        $plugin_settings['payment_gateway_zibal']['success_message'] = isset($new_value['success_message']) ? $new_value['success_message'] : __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'aio-woocommerce');
        $plugin_settings['payment_gateway_zibal']['failed_message'] = isset($new_value['failed_message']) ? $new_value['failed_message'] : __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'aio-woocommerce');
        
        // Save plugin settings
        update_option('aio_wc_settings', $plugin_settings);
    }
    
    /**
     * Load settings from plugin options
     */
    private function load_settings() {
        // Set default values
        $this->enabled         = 'no';
        $this->title           = __('زیبال', 'aio-woocommerce');
        $this->description     = __('پرداخت امن به وسیله کلیه کارتهای عضو شتاب از طریق درگاه زیبال', 'aio-woocommerce');
        $this->merchant_id     = '';
        $this->sandbox_mode    = 'no';
        $this->success_message = __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'aio-woocommerce');
        $this->failed_message  = __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'aio-woocommerce');
    }
    
    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'aio-woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Enable Zibal gateway', 'aio-woocommerce'),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __('Title', 'aio-woocommerce'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'aio-woocommerce'),
                'default'     => __('Zibal', 'aio-woocommerce'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'aio-woocommerce'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'aio-woocommerce'),
                'default'     => __('Pay securely via Zibal.', 'aio-woocommerce'),
                'desc_tip'    => true,
            ),
            'merchant_id' => array(
                'title'       => __('Merchant ID', 'aio-woocommerce'),
                'type'        => 'text',
                'description' => __('Enter your Zibal Merchant ID.', 'aio-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'sandbox_mode' => array(
                'title'   => __('فعالسازی حالت آزمایشی', 'aio-woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('فعالسازی حالت آزمایشی زیبال', 'aio-woocommerce'),
                'default' => 'no',
                'description' => __('برای فعال سازی حالت آزمایشی زیبال چک باکس را تیک بزنید .', 'aio-woocommerce'),
            ),
            'success_message' => array(
                'title'       => __('پیام پرداخت موفق', 'aio-woocommerce'),
                'type'        => 'textarea',
                'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (کد تراکنش زیبال) استفاده نمایید .', 'aio-woocommerce'),
                'default'     => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'aio-woocommerce'),
            ),
            'failed_message' => array(
                'title'       => __('پیام پرداخت ناموفق', 'aio-woocommerce'),
                'type'        => 'textarea',
                'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت زیبال ارسال میگردد .', 'aio-woocommerce'),
                'default'     => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'aio-woocommerce'),
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
        
        // Get payment request URL from Zibal
        $payment_url = $this->request_payment($order);
        
        if (is_wp_error($payment_url)) {
            wc_add_notice($payment_url->get_error_message(), 'error');
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
        
        // Store order ID in session as fallback (callback URL already includes wc_order parameter)
        if (WC()->session) {
            WC()->session->set('aio_wc_zibal_order_id', $order_id);
        }
        
        return array(
            'result'   => 'success',
            'redirect' => $payment_url,
        );
    }
    
    /**
     * Request payment from Zibal
     * 
     * @param WC_Order $order Order object
     * @return string|WP_Error Payment URL or error
     */
    private function request_payment($order) {
        $merchant_id = $this->merchant_id;
        $amount      = $this->get_order_total_rials($order);
        $callback    = $this->get_callback_url($order->get_id());
        
        // Build description with customer and product info (matching official plugin)
        $products = array();
        $order_items = $order->get_items();
        foreach ($order_items as $item) {
            $products[] = $item->get_name() . ' (' . $item->get_quantity() . ') ';
        }
        $products = implode(' - ', $products);
        $description = 'خریدار : ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . ' | محصولات : ' . $products;
        
        // Get customer info
        $mobile = intval($order->get_billing_phone());
        $email = $order->get_billing_email();
        
        // *** FIX: Use the correct, single URL for all requests ***
        $api_url = 'https://gateway.zibal.ir/v1/request';
        
        // Prepare request data to match Zibal's API
        $data = array(
            'merchant'     => $merchant_id,
            'amount'       => $amount,
            'callbackUrl'  => $callback,
            'description'  => $description,
            'orderId'      => $order->get_id(),
            'mobile'       => $mobile,
            'email'        => $email,
        );
        
        // Send request to Zibal
        $response = wp_remote_post($api_url, array(
            'body'    => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), false);
        
        // Handle Zibal's response structure (response is an object, not array)
        if (!isset($body->result) || $body->result != 100 || !isset($body->trackId)) {
            $error_code = isset($body->result) ? (string)$body->result : 'unknown';
            $error_message = $this->get_error_message($error_code);
            return new WP_Error('zibal_error', $error_message);
        }
        
        $trackId = $body->trackId;
        $payment_url = 'https://gateway.zibal.ir/start/' . $trackId;
        
        // Store trackId in order meta
        $order->update_meta_data('_aio_wc_zibal_trackId', $trackId);
        $order->save();
        
        return $payment_url;
    }
    
    /**
     * Handle return from Zibal
     */
    public function handle_return() {
        $success = isset($_GET['success']) ? sanitize_text_field($_GET['success']) : '';
        $trackId = isset($_GET['trackId']) ? sanitize_text_field($_GET['trackId']) : '';
        
        // Get order ID from URL parameter (primary method) or session (fallback)
        $order_id = isset($_GET['wc_order']) ? intval($_GET['wc_order']) : 0;
        
        if (!$order_id && WC()->session) {
            $order_id = WC()->session->get('aio_wc_zibal_order_id');
            if ($order_id && WC()->session) {
                WC()->session->__unset('aio_wc_zibal_order_id');
            }
        }
        
        if (!$order_id) {
            wc_add_notice(__('شماره سفارش وجود ندارد .', 'aio-woocommerce'), 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wc_add_notice(__('سفارش یافت نشد.', 'aio-woocommerce'), 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }
        
        // Check if order is already completed
        if ($order->get_status() === 'completed') {
            // Order already completed - show success message
            $notice = wpautop(wptexturize($this->success_message));
            $transaction_id = $order->get_meta('_aio_wc_zibal_trackId');
            if ($transaction_id) {
                $notice = str_replace('{transaction_id}', $transaction_id, $notice);
            }
            wc_add_notice($notice, 'success');
            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
            exit;
        }
        
        // Verify payment
        $this->verify_payment($order, $trackId, $success);
    }
    
    /**
     * Handle verification callback
     */
    public function handle_verify() {
        // This can be used for server-to-server verification if needed
        $this->handle_return();
    }
    
    /**
     * Verify payment with Zibal
     * 
     * @param WC_Order $order Order object
     * @param string $trackId Transaction ID
     * @param string $status Status from Zibal
     */
    private function verify_payment($order, $trackId, $success) {
        // Zibal success parameter is '1' when payment is successful
        if ($success != '1') {
            // Payment failed or cancelled
            $fault = __('تراکنش انجام نشد .', 'aio-woocommerce');
            $notice = wpautop(wptexturize($this->failed_message));
            $notice = str_replace('{fault}', $fault, $notice);
            wc_add_notice($notice, 'error');
            
            $order->add_order_note(sprintf(__('خطا در هنگام بازگشت از بانک : %s', 'aio-woocommerce'), $fault));
            
            wp_redirect(wc_get_checkout_url());
            exit;
        }
        
        $merchant_id = $this->merchant_id;
        
        // *** FIX: Use the correct, single URL for all verification requests ***
        $api_url = 'https://gateway.zibal.ir/v1/verify';
        
        // Prepare verification data to match Zibal's API
        $data = array(
            'merchant' => $merchant_id,
            'trackId'  => $trackId,
        );
        
        // Send verification request
        $response = wp_remote_post($api_url, array(
            'body'    => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            $fault = __('خطا در ارتباط با درگاه پرداخت', 'aio-woocommerce');
            $notice = wpautop(wptexturize($this->failed_message));
            $notice = str_replace('{fault}', $fault, $notice);
            wc_add_notice($notice, 'error');
            $order->add_order_note(sprintf(__('خطا در هنگام بازگشت از بانک : %s', 'aio-woocommerce'), $fault));
            wp_redirect(wc_get_checkout_url());
            exit;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), false);
        
        // Handle Zibal's response structure (response is an object, not array)
        if (!isset($body->result) || $body->result != 100) {
            // Check if transaction was already verified (result 201)
            if (isset($body->result) && $body->result == 201) {
                // Transaction already verified - show success message
                $transaction_id = $trackId;
                $notice = wpautop(wptexturize($this->success_message));
                $notice = str_replace('{transaction_id}', $transaction_id, $notice);
                wc_add_notice($notice, 'success');
                wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                exit;
            }
            
            $error_code = isset($body->result) ? (string)$body->result : 'unknown';
            $fault = $this->get_error_message($error_code);
            $notice = wpautop(wptexturize($this->failed_message));
            $notice = str_replace('{transaction_id}', $trackId, $notice);
            $notice = str_replace('{fault}', $fault, $notice);
            wc_add_notice($notice, 'error');
            $order->add_order_note(sprintf(__('خطا در هنگام بازگشت از بانک : %s', 'aio-woocommerce'), $fault));
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        // Check if the verified amount matches the order total
        $verified_amount_rials = isset($body->amount) ? intval($body->amount) : 0;
        $order_amount_rials = $this->get_order_total_rials($order);
        if ($verified_amount_rials != $order_amount_rials) {
            $fault = __('مبلغ پرداختی با مبلغ سفارش مطابقت ندارد', 'aio-woocommerce');
            $notice = wpautop(wptexturize($this->failed_message));
            $notice = str_replace('{fault}', $fault, $notice);
            wc_add_notice($notice, 'error');
            $order->add_order_note(sprintf(__('خطا در هنگام بازگشت از بانک : %s', 'aio-woocommerce'), $fault));
            wp_redirect(wc_get_checkout_url());
            exit;
        }
        
        // Payment successful
        $transaction_id = $trackId;
        $card_number = isset($_POST['cardnumber']) ? sanitize_text_field($_POST['cardnumber']) : '';
        $tracking_number = isset($_POST['tracking_number']) ? sanitize_text_field($_POST['tracking_number']) : '';
        
        // Update order
        $order->payment_complete($transaction_id);
        WC()->cart->empty_cart();
        
        // Store transaction details
        $order->update_meta_data('_transaction_id', $transaction_id);
        $order->update_meta_data('_aio_wc_zibal_trackId', $trackId);
        if ($card_number) {
            $order->update_meta_data('_card_number', $card_number);
        }
        if ($tracking_number) {
            $order->update_meta_data('_tracking_number', $tracking_number);
        }
        $order->save();
        
        // Add order note
        $note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'aio-woocommerce'), $transaction_id);
        if ($card_number) {
            $note .= sprintf(__('<br/> شماره کارت پرداخت کننده : %s', 'aio-woocommerce'), $card_number);
        }
        if ($tracking_number) {
            $note .= sprintf(__('<br/> شماره تراکنش : %s', 'aio-woocommerce'), $tracking_number);
        }
        $order->add_order_note($note, 1);
        
        // Show success message
        $notice = wpautop(wptexturize($this->success_message));
        $notice = str_replace('{transaction_id}', $transaction_id, $notice);
        wc_add_notice($notice, 'success');
        
        // Redirect to thank you page
        wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
        exit;
    }
    
    /**
     * Get callback URL
     * 
     * @return string Callback URL
     */
    private function get_callback_url($order_id = null) {
        // Use gateway ID as endpoint (matches WooCommerce API hook registration)
        $callback_url = WC()->api_request_url($this->id);
        
        // Add order ID to callback URL for reliable order retrieval
        if ($order_id) {
            $callback_url = add_query_arg('wc_order', $order_id, $callback_url);
        }
        
        return $callback_url;
    }
    
    /**
     * Get order total in Rials
     * 
     * @param WC_Order $order Order object
     * @return int Order total in Rials
     */
    private function get_order_total_rials($order) {
        $currency = $order->get_currency();
        $amount = intval($order->get_total());
        
        // Convert based on currency (matching official plugin logic)
        // Toman/IRT → Rials (× 10)
        if (
            strtolower($currency) == strtolower('IRT') || 
            strtolower($currency) == strtolower('TOMAN') || 
            strtolower($currency) == strtolower('Iran TOMAN') || 
            strtolower($currency) == strtolower('Iranian TOMAN') || 
            strtolower($currency) == strtolower('Iran-TOMAN') || 
            strtolower($currency) == strtolower('Iranian-TOMAN') || 
            strtolower($currency) == strtolower('Iran_TOMAN') || 
            strtolower($currency) == strtolower('Iranian_TOMAN') || 
            strtolower($currency) == strtolower('تومان') || 
            strtolower($currency) == strtolower('تومان ایران')
        ) {
            $amount = $amount * 10;
        } elseif (strtolower($currency) == strtolower('IRHT')) {
            // Hezar Toman → Rials (× 10000)
            $amount = $amount * 10000;
        } elseif (strtolower($currency) == strtolower('IRHR')) {
            // Hezar Rials → Rials (× 1000)
            $amount = $amount * 1000;
        } elseif (strtolower($currency) == strtolower('IRR')) {
            // Already in Rials
            $amount = $amount;
        }
        
        return $amount;
    }
    
    /**
     * Get error message
     * 
     * @param string $error_code Error code
     * @return string Error message
     */
    private function get_error_message($error_code) {
        $messages = array(
            '100' => 'موفق',
            '102' => 'merchant یافت نشد',
            '103' => 'merchant غیرفعال',
            '104' => 'merchant نامعتبر',
            '201' => 'قبلا تایید شده',
            '105' => 'amount بایستی بزرگتر از 1,000 ریال باشد',
            '106' => 'callbackUrl نامعتبر می‌باشد',
            '113' => 'amount مبلغ تراکنش از سقف میزان تراکنش بیشتر است',
        );
        
        return isset($messages[$error_code]) 
            ? $messages[$error_code] 
            : __('An error occurred. Please try again.', 'aio-woocommerce');
    }

    /**
     * Show notice if merchant ID is missing
     */
    public function merchant_id_missing_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $settings_url = add_query_arg(array(
            'page' => 'aio-woocommerce',
        ), admin_url('admin.php')) . '#payment-gateways';
        
        $message = sprintf(
            __('مرچنت کد درگاه زیبال خالی است. برای تکمیل مورد مربوطه به تنظیمات درگاه <a href="%s">اینجا</a> مراجعه کنید.', 'aio-woocommerce'),
            esc_url($settings_url)
        );
        
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p>' . $message . '</p>';
        echo '</div>';
    }
}