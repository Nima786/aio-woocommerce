<?php
/**
 * Payment Gateways tab content.
 *
 * Variables:
 * @var string $zarinpal_enabled
 * @var string $zarinpal_title
 * @var string $zarinpal_description
 * @var string $zarinpal_merchant_id
 * @var string $zarinpal_access_token
 * @var string $zarinpal_sandbox_mode
 * @var string $zarinpal_gate
 * @var string $zarinpal_commission_from
 * @var string $zarinpal_success_message
 * @var string $zarinpal_failed_message
 * @var string $zarinpal_post_purchase_description
 * @var string $zarinpal_trust_logo_code
 * @var string $zibal_enabled
 * @var string $zibal_title
 * @var string $zibal_description
 * @var string $zibal_merchant_id
 * @var string $zibal_sandbox_mode
 * @var string $zibal_success_message
 * @var string $zibal_failed_message
 */
?>
<div class="aio-wc-tab-content" id="payment-gateways" style="display: none;">
    <div class="aio-wc-admin__content-header">
        <h2 class="aio-wc-admin__content-title"><?php esc_html_e('Payment Gateways', 'aio-woocommerce'); ?></h2>
        <p class="aio-wc-admin__content-desc"><?php esc_html_e('Configure Persian payment gateway integrations for WooCommerce. All settings are managed here.', 'aio-woocommerce'); ?></p>
    </div>

    <!-- Sub-navigation Tabs -->
    <nav class="aio-wc-sub-nav">
        <a href="#payment-gateways/zarinpal" class="aio-wc-sub-nav__link aio-wc-sub-nav__link--active" data-sub-tab="zarinpal"><?php esc_html_e('Zarinpal', 'aio-woocommerce'); ?></a>
        <a href="#payment-gateways/zibal" class="aio-wc-sub-nav__link" data-sub-tab="zibal"><?php esc_html_e('Zibal', 'aio-woocommerce'); ?></a>
    </nav>

    <!-- Sub-tab Content Panels -->
    <div id="sub-tab-zarinpal" class="aio-wc-sub-tab-content aio-wc-sub-tab-content--active">
        <div class="aio-wc-card">
            <h3 class="aio-wc-card__title"><?php esc_html_e('Zarinpal Payment Gateway', 'aio-woocommerce'); ?></h3>
            <p class="description"><?php esc_html_e('Zarinpal payment gateway settings for WooCommerce', 'aio-woocommerce'); ?></p>

            <!-- Basic Settings -->
            <h4 style="margin-top: 24px; margin-bottom: 12px;"><?php esc_html_e('Basic Settings', 'aio-woocommerce'); ?></h4>

            <div class="aio-wc-form-field">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <?php echo $this->toggle_switch('payment_gateway_zarinpal[enabled]', 'yes', $zarinpal_enabled === 'yes', false, 'zarinpal_enabled'); ?>
                    <span class="aio-wc-toggle-label-text"><?php esc_html_e('Enable Zarinpal Gateway', 'aio-woocommerce'); ?></span>
                </div>
            </div>

            <div class="aio-wc-form-field">
                <label for="zarinpal_title" class="aio-wc-label"><?php esc_html_e('Gateway Title', 'aio-woocommerce'); ?></label>
                <input type="text" name="aio_wc_settings[payment_gateway_zarinpal][title]" id="zarinpal_title" value="<?php echo esc_attr($zarinpal_title); ?>" class="aio-wc-input aio-wc-input--full" placeholder="<?php esc_attr_e('Secure Payment via Zarinpal', 'aio-woocommerce'); ?>">
            </div>

            <div class="aio-wc-form-field">
                <label for="zarinpal_description" class="aio-wc-label"><?php esc_html_e('Gateway Description', 'aio-woocommerce'); ?></label>
                <textarea name="aio_wc_settings[payment_gateway_zarinpal][description]" id="zarinpal_description" class="aio-wc-textarea" rows="2" placeholder="<?php esc_attr_e('Secure payment via all Shetab member cards through Zarinpal gateway', 'aio-woocommerce'); ?>"><?php echo esc_textarea($zarinpal_description); ?></textarea>
            </div>

            <!-- Zarinpal Account Settings -->
            <h4 style="margin-top: 24px; margin-bottom: 12px;"><?php esc_html_e('Zarinpal Account Settings', 'aio-woocommerce'); ?></h4>

            <div class="aio-wc-form-field">
                <label for="zarinpal_merchant_id" class="aio-wc-label"><?php esc_html_e('Merchant ID', 'aio-woocommerce'); ?> <span style="color: #ef4444;">*</span></label>
                <input type="text" name="aio_wc_settings[payment_gateway_zarinpal][merchant_id]" id="zarinpal_merchant_id" value="<?php echo esc_attr($zarinpal_merchant_id); ?>" class="aio-wc-input aio-wc-input--full" placeholder="<?php esc_attr_e('Your Zarinpal Merchant ID', 'aio-woocommerce'); ?>">
                <p class="description"><?php esc_html_e('Enter your Zarinpal Merchant ID (Merchant Code) obtained from your Zarinpal account.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-form-field">
                <label for="zarinpal_access_token" class="aio-wc-label"><?php esc_html_e('Access Token (Optional - for refund service)', 'aio-woocommerce'); ?></label>
                <input type="text" name="aio_wc_settings[payment_gateway_zarinpal][access_token]" id="zarinpal_access_token" value="<?php echo esc_attr($zarinpal_access_token); ?>" class="aio-wc-input aio-wc-input--full" placeholder="<?php esc_attr_e('Access Token (Optional)', 'aio-woocommerce'); ?>">
                <p class="description"><?php esc_html_e('Optional access token for refund service.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-form-field">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <?php echo $this->toggle_switch('payment_gateway_zarinpal[sandbox_mode]', 'yes', $zarinpal_sandbox_mode === 'yes', false, 'zarinpal_sandbox_mode'); ?>
                    <span class="aio-wc-toggle-label-text"><?php esc_html_e('Enable Sandbox Mode', 'aio-woocommerce'); ?></span>
                </div>
                <p class="description"><?php esc_html_e('Use sandbox mode for testing. Disable for live payments.', 'aio-woocommerce'); ?></p>
            </div>

            <!-- Payment Operation Settings -->
            <h4 style="margin-top: 24px; margin-bottom: 12px;"><?php esc_html_e('Payment Operation Settings', 'aio-woocommerce'); ?></h4>

            <div class="aio-wc-form-field">
                <label for="zarinpal_gate" class="aio-wc-label"><?php esc_html_e('Gateway Type', 'aio-woocommerce'); ?></label>
                <select name="aio_wc_settings[payment_gateway_zarinpal][zarinpal_gate]" id="zarinpal_gate" class="aio-wc-select aio-wc-input--full">
                    <option value="normal" <?php selected($zarinpal_gate, 'normal'); ?>><?php esc_html_e('Normal Gateway', 'aio-woocommerce'); ?></option>
                    <option value="zaringate" <?php selected($zarinpal_gate, 'zaringate'); ?>><?php esc_html_e('ZarinGate', 'aio-woocommerce'); ?></option>
                    <option value="zarinlink" <?php selected($zarinpal_gate, 'zarinlink'); ?>><?php esc_html_e('ZarinLink', 'aio-woocommerce'); ?></option>
                </select>
                <p class="description"><?php esc_html_e('Choose the gateway type. ZarinGate requires special activation from Zarinpal.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-form-field">
                <label for="zarinpal_commission_from" class="aio-wc-label"><?php esc_html_e('Deduct Commission From', 'aio-woocommerce'); ?></label>
                <select name="aio_wc_settings[payment_gateway_zarinpal][commission_from]" id="zarinpal_commission_from" class="aio-wc-select aio-wc-input--full">
                    <option value="merchant" <?php selected($zarinpal_commission_from, 'merchant'); ?>><?php esc_html_e('Merchant (Default)', 'aio-woocommerce'); ?></option>
                    <option value="customer" <?php selected($zarinpal_commission_from, 'customer'); ?>><?php esc_html_e('Customer', 'aio-woocommerce'); ?></option>
                </select>
            </div>

            <div class="aio-wc-form-field">
                <label for="zarinpal_success_message" class="aio-wc-label"><?php esc_html_e('Success Payment Message', 'aio-woocommerce'); ?></label>
                <textarea name="aio_wc_settings[payment_gateway_zarinpal][success_message]" id="zarinpal_success_message" class="aio-wc-textarea" rows="2"><?php echo esc_textarea($zarinpal_success_message); ?></textarea>
                <p class="description"><?php esc_html_e('Message shown after successful payment. Use {transaction_id} for tracking code.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-form-field">
                <label for="zarinpal_failed_message" class="aio-wc-label"><?php esc_html_e('Failed Payment Message', 'aio-woocommerce'); ?></label>
                <textarea name="aio_wc_settings[payment_gateway_zarinpal][failed_message]" id="zarinpal_failed_message" class="aio-wc-textarea" rows="2"><?php echo esc_textarea($zarinpal_failed_message); ?></textarea>
                <p class="description"><?php esc_html_e('Message shown after failed payment. Use {fault} for error reason.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-form-field">
                <label for="zarinpal_post_purchase_description" class="aio-wc-label"><?php esc_html_e('Post Purchase Description', 'aio-woocommerce'); ?></label>
                <textarea name="aio_wc_settings[payment_gateway_zarinpal][post_purchase_description]" id="zarinpal_post_purchase_description" class="aio-wc-textarea" rows="2"><?php echo esc_textarea($zarinpal_post_purchase_description); ?></textarea>
            </div>

            <div class="aio-wc-form-field">
                <label for="zarinpal_trust_logo_code" class="aio-wc-label"><?php esc_html_e('Zarinpal Trust Logo Code', 'aio-woocommerce'); ?></label>
                <textarea name="aio_wc_settings[payment_gateway_zarinpal][trust_logo_code]" id="zarinpal_trust_logo_code" class="aio-wc-textarea" rows="3" style="font-family: monospace; font-size: 12px;"><?php echo esc_textarea($zarinpal_trust_logo_code); ?></textarea>
                <p class="description"><?php esc_html_e('Copy this code to your site footer to display Zarinpal trust logo.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-card__actions">
                <button type="button" class="aio-wc-section-save-btn aio-wc-btn aio-wc-btn--primary" data-section="zarinpal"><?php esc_html_e('Save Zarinpal Settings', 'aio-woocommerce'); ?></button>
            </div>
        </div>
    </div>

    <div id="sub-tab-zibal" class="aio-wc-sub-tab-content">
        <div class="aio-wc-card">
            <h3 class="aio-wc-card__title"><?php esc_html_e('Zibal Payment Gateway', 'aio-woocommerce'); ?></h3>
            <p class="description"><?php esc_html_e('Zibal payment gateway settings for WooCommerce', 'aio-woocommerce'); ?></p>

            <!-- Basic Settings -->
            <h4 style="margin-top: 24px; margin-bottom: 12px;"><?php esc_html_e('Basic Settings', 'aio-woocommerce'); ?></h4>

            <div class="aio-wc-form-field">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <?php echo $this->toggle_switch('payment_gateway_zibal[enabled]', 'yes', $zibal_enabled === 'yes', false, 'zibal_enabled'); ?>
                    <span class="aio-wc-toggle-label-text"><?php esc_html_e('Enable Zibal Gateway', 'aio-woocommerce'); ?></span>
                </div>
                <p class="description"><?php esc_html_e('Check this box to enable Zibal payment gateway.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-form-field">
                <label for="zibal_title" class="aio-wc-label"><?php esc_html_e('Gateway Title', 'aio-woocommerce'); ?></label>
                <input type="text" name="aio_wc_settings[payment_gateway_zibal][title]" id="zibal_title" value="<?php echo esc_attr($zibal_title); ?>" class="aio-wc-input aio-wc-input--full" placeholder="<?php esc_attr_e('Zibal', 'aio-woocommerce'); ?>">
                <p class="description"><?php esc_html_e('The gateway title displayed to customers during checkout.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-form-field">
                <label for="zibal_description" class="aio-wc-label"><?php esc_html_e('Gateway Description', 'aio-woocommerce'); ?></label>
                <input type="text" name="aio_wc_settings[payment_gateway_zibal][description]" id="zibal_description" value="<?php echo esc_attr($zibal_description); ?>" class="aio-wc-input aio-wc-input--full" placeholder="<?php esc_attr_e('Secure payment via all Shetab member cards through Zibal gateway', 'aio-woocommerce'); ?>">
                <p class="description"><?php esc_html_e('Description shown during the payment process for the gateway.', 'aio-woocommerce'); ?></p>
            </div>

            <!-- Zibal Account Settings -->
            <h4 style="margin-top: 24px; margin-bottom: 12px;"><?php esc_html_e('Zibal Account Settings', 'aio-woocommerce'); ?></h4>

            <div class="aio-wc-form-field">
                <label for="zibal_merchant_id" class="aio-wc-label"><?php esc_html_e('Merchant ID', 'aio-woocommerce'); ?></label>
                <input type="text" name="aio_wc_settings[payment_gateway_zibal][merchant_id]" id="zibal_merchant_id" value="<?php echo esc_attr($zibal_merchant_id); ?>" class="aio-wc-input aio-wc-input--full" placeholder="<?php esc_attr_e('Your Zibal Merchant ID', 'aio-woocommerce'); ?>">
                <p class="description"><?php esc_html_e('Enter your Zibal gateway Merchant ID.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-form-field">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <?php echo $this->toggle_switch('payment_gateway_zibal[sandbox_mode]', 'yes', $zibal_sandbox_mode === 'yes', false, 'zibal_sandbox_mode'); ?>
                    <span class="aio-wc-toggle-label-text"><?php esc_html_e('Enable Zibal Sandbox Mode', 'aio-woocommerce'); ?></span>
                </div>
                <p class="description"><?php esc_html_e('Check this box to enable Zibal sandbox mode for testing.', 'aio-woocommerce'); ?></p>
            </div>

            <!-- Payment Operation Settings -->
            <h4 style="margin-top: 24px; margin-bottom: 12px;"><?php esc_html_e('Payment Operation Settings', 'aio-woocommerce'); ?></h4>

            <div class="aio-wc-form-field">
                <label for="zibal_success_message" class="aio-wc-label"><?php esc_html_e('Success Payment Message', 'aio-woocommerce'); ?></label>
                <textarea name="aio_wc_settings[payment_gateway_zibal][success_message]" id="zibal_success_message" class="aio-wc-textarea" rows="3" placeholder="<?php esc_attr_e('Thank you. Your order has been successfully paid.', 'aio-woocommerce'); ?>"><?php echo esc_textarea($zibal_success_message); ?></textarea>
                <p class="description"><?php esc_html_e('Enter the message you want to display to users after successful payment. You can also use the {transaction_id} shortcode to display the tracking code (Zibal transaction code).', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-form-field">
                <label for="zibal_failed_message" class="aio-wc-label"><?php esc_html_e('Failed Payment Message', 'aio-woocommerce'); ?></label>
                <textarea name="aio_wc_settings[payment_gateway_zibal][failed_message]" id="zibal_failed_message" class="aio-wc-textarea" rows="3" placeholder="<?php esc_attr_e('Your payment was unsuccessful. Please try again or contact the site administrator if there is an issue.', 'aio-woocommerce'); ?>"><?php echo esc_textarea($zibal_failed_message); ?></textarea>
                <p class="description"><?php esc_html_e('Enter the message you want to display to users after failed payment. You can also use the {fault} shortcode to display the error reason. This error reason is sent from the Zibal website.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-card__actions">
                <button type="button" class="aio-wc-section-save-btn aio-wc-btn aio-wc-btn--primary" data-section="zibal"><?php esc_html_e('Save Zibal Settings', 'aio-woocommerce'); ?></button>
            </div>
        </div>
    </div>

    <div class="aio-wc-card" style="background: #f8f9fa; border-left: 4px solid #46b450; margin-top: 24px;">
        <p style="margin: 0; font-size: 13px;">
            <strong><?php esc_html_e('Note:', 'aio-woocommerce'); ?></strong>
            <?php esc_html_e('Payment gateway settings are managed here in the plugin. The "Enable" button in WooCommerce → Settings → Payments will sync back to these settings automatically. However, we recommend managing all settings here for consistency.', 'aio-woocommerce'); ?>
        </p>
    </div>
</div>