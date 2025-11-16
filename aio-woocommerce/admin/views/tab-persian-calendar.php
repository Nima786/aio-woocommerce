<?php
/**
 * Persian calendar tab content.
 *
 * @var string $convert_wordpress_dates
 * @var string $convert_wordpress_datepickers
 * @var string $convert_woocommerce_dates
 * @var string $convert_woocommerce_datepickers
 * @var string $date_format
 * @var bool   $woocommerce_active
 */
?>
<?php
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'persian-calendar';
$is_active = $active_tab === 'persian-calendar';
?>
<div class="aio-wc-tab-content" id="persian-calendar" style="display: <?php echo $is_active ? 'block' : 'none'; ?>;">
    <div class="aio-wc-admin__content-header">
        <h2 class="aio-wc-admin__content-title"><?php esc_html_e('Persian Calendar Settings', 'aio-woocommerce'); ?></h2>
        <p class="aio-wc-admin__content-desc"><?php esc_html_e('Configure Persian calendar conversion across WordPress admin screens, frontend tools, and optional WooCommerce areas.', 'aio-woocommerce'); ?></p>
    </div>

    <?php if (isset($woocommerce_active) && !$woocommerce_active) : ?>
        <div class="notice notice-info" style="margin-bottom: 16px;">
            <p><?php esc_html_e('WooCommerce is not active. WooCommerce-specific features are optional and the Persian calendar tools will still run for WordPress.', 'aio-woocommerce'); ?></p>
        </div>
    <?php endif; ?>

    <div class="aio-wc-card">
        <h3 class="aio-wc-card__title"><?php esc_html_e('WordPress Admin Dates', 'aio-woocommerce'); ?></h3>
        <p class="aio-wc-card__desc"><?php esc_html_e('Control conversion across core WordPress areas such as Posts, Pages, Media Library, and any custom post types.', 'aio-woocommerce'); ?></p>

        <div class="aio-wc-form-field">
            <div style="display: flex; align-items: center; gap: 10px;">
                <?php echo $this->toggle_switch('convert_wordpress_dates', 'yes', $convert_wordpress_dates === 'yes', false, 'convert_wordpress_dates'); ?>
                <span class="aio-wc-toggle-label-text"><?php esc_html_e('Convert List/Table Dates', 'aio-woocommerce'); ?></span>
            </div>
            <p class="description"><?php esc_html_e('Switch all WordPress admin date columns (Posts, Pages, Media, custom types) to the Persian calendar while respecting the saved time zone.', 'aio-woocommerce'); ?></p>
        </div>

        <div class="aio-wc-form-field">
            <div style="display: flex; align-items: center; gap: 10px;">
                <?php echo $this->toggle_switch('convert_wordpress_datepickers', 'yes', $convert_wordpress_datepickers === 'yes', false, 'convert_wordpress_datepickers'); ?>
                <span class="aio-wc-toggle-label-text"><?php esc_html_e('Convert WordPress Date Pickers', 'aio-woocommerce'); ?></span>
            </div>
            <p class="description"><?php esc_html_e('Use Persian date pickers on default WordPress filters (Posts, Media, Taxonomies) and in the Gutenberg editor calendar popovers.', 'aio-woocommerce'); ?></p>
        </div>

        <div class="aio-wc-card__actions">
            <button type="button" class="aio-wc-section-save-btn aio-wc-btn aio-wc-btn--primary">
                <?php esc_html_e('Save WordPress Settings', 'aio-woocommerce'); ?>
            </button>
        </div>
    </div>

    <div class="aio-wc-card">
        <h3 class="aio-wc-card__title"><?php esc_html_e('WooCommerce Admin Dates', 'aio-woocommerce'); ?></h3>
        <p class="aio-wc-card__desc">
            <?php esc_html_e('These options handle WooCommerce-specific screens such as Orders, Products, and WooCommerce reports. They are optional when WooCommerce is not installed.', 'aio-woocommerce'); ?>
        </p>

        <div class="aio-wc-form-field">
            <div style="display: flex; align-items: center; gap: 10px;">
                <?php
                $woocommerce_toggle_disabled = !$woocommerce_active;
                echo $this->toggle_switch(
                    'convert_woocommerce_dates',
                    'yes',
                    $convert_woocommerce_dates === 'yes',
                    $woocommerce_toggle_disabled,
                    'convert_woocommerce_dates'
                );
                ?>
                <span class="aio-wc-toggle-label-text"><?php esc_html_e('Convert WooCommerce Dates', 'aio-woocommerce'); ?></span>
            </div>
            <p class="description">
                <?php
                if ($woocommerce_active) {
                    esc_html_e('Display WooCommerce-specific dates (Orders, Products, coupons, reports) in the Persian calendar.', 'aio-woocommerce');
                } else {
                    esc_html_e('Install or activate WooCommerce to enable this option.', 'aio-woocommerce');
                }
                ?>
            </p>
        </div>

        <div class="aio-wc-form-field">
            <div style="display: flex; align-items: center; gap: 10px;">
                <?php
                echo $this->toggle_switch(
                    'convert_woocommerce_datepickers',
                    'yes',
                    $convert_woocommerce_datepickers === 'yes',
                    $woocommerce_toggle_disabled,
                    'convert_woocommerce_datepickers'
                );
                ?>
                <span class="aio-wc-toggle-label-text"><?php esc_html_e('Convert WooCommerce Date Pickers', 'aio-woocommerce'); ?></span>
            </div>
            <p class="description">
                <?php
                if ($woocommerce_active) {
                    esc_html_e('Use Persian date pickers on WooCommerce filters (Orders, Reports, Analytics) without affecting WordPress core pages.', 'aio-woocommerce');
                } else {
                    esc_html_e('Requires WooCommerce.', 'aio-woocommerce');
                }
                ?>
            </p>
        </div>

        <div class="aio-wc-card__actions">
            <button type="button" class="aio-wc-section-save-btn aio-wc-btn aio-wc-btn--primary" <?php disabled(!$woocommerce_active); ?>>
                <?php esc_html_e('Save WooCommerce Settings', 'aio-woocommerce'); ?>
            </button>
        </div>
    </div>

    <div class="aio-wc-card">
        <h3 class="aio-wc-card__title"><?php esc_html_e('Shortcodes', 'aio-woocommerce'); ?></h3>
        <div class="aio-wc-shortcode-info">
            <p><strong><?php esc_html_e('Available Shortcodes:', 'aio-woocommerce'); ?></strong></p>

            <h4 style="margin-top: 16px; margin-bottom: 8px;">&nbsp;<?php esc_html_e('Current Date:', 'aio-woocommerce'); ?></h4>
            <ul>
                <li><code>[aio_wc_date]</code> - <?php esc_html_e('Display current date (default format based on settings)', 'aio-woocommerce'); ?></li>
                <li><code>[aio_wc_date_persian]</code> - <?php esc_html_e('Display current date in Persian format only', 'aio-woocommerce'); ?></li>
                <li><code>[aio_wc_date_gregorian]</code> - <?php esc_html_e('Display current date in Gregorian format only', 'aio-woocommerce'); ?></li>
                <li><code>[aio_wc_date format="j F Y"]</code> - <?php esc_html_e('Display date with month name (e.g. 02 November 2025)', 'aio-woocommerce'); ?></li>
                <li><code>[aio_wc_date_both]</code> - <?php esc_html_e('Display both Persian and Gregorian dates', 'aio-woocommerce'); ?></li>
            </ul>

            <h4 style="margin-top: 16px; margin-bottom: 8px;">&nbsp;<?php esc_html_e('Post Date:', 'aio-woocommerce'); ?></h4>
            <ul>
                <li><code>[aio_wc_post_date]</code> - <?php esc_html_e('Display post published date (default format based on settings)', 'aio-woocommerce'); ?></li>
                <li><code>[aio_wc_post_date type="persian"]</code> - <?php esc_html_e('Display post date in Persian format only', 'aio-woocommerce'); ?></li>
                <li><code>[aio_wc_post_date type="gregorian"]</code> - <?php esc_html_e('Display post date in Gregorian format only', 'aio-woocommerce'); ?></li>
                <li><code>[aio_wc_post_date post_id="123"]</code> - <?php esc_html_e('Display date for specific post', 'aio-woocommerce'); ?></li>
                <li><code>[aio_wc_post_date field="post_modified"]</code> - <?php esc_html_e('Display post modified date instead of published date', 'aio-woocommerce'); ?></li>
                <li><code>[aio_wc_post_date format="l j F Y"]</code> - <?php esc_html_e('Display with weekday (e.g. دوشنبه ۲ آبان ۱۴۰۴)', 'aio-woocommerce'); ?></li>
            </ul>

            <h4 style="margin-top: 16px; margin-bottom: 8px;">&nbsp;<?php esc_html_e('Order Date:', 'aio-woocommerce'); ?></h4>
            <ul>
                <li><code>[aio_wc_order_date order_id="123"]</code> - <?php esc_html_e('Display order date in Persian format', 'aio-woocommerce'); ?></li>
                <li><code>[aio_wc_order_date order_id="123" format="j F Y \\ساعت H:i"]</code> - <?php esc_html_e('Display with readable month name and time', 'aio-woocommerce'); ?></li>
            </ul>

            <h4 style="margin-top: 16px; margin-bottom: 8px;">&nbsp;<?php esc_html_e('Page Builder Compatibility:', 'aio-woocommerce'); ?></h4>
            <p style="margin-bottom: 8px;"><?php esc_html_e('These shortcodes work with:', 'aio-woocommerce'); ?></p>
            <ul>
                <li><?php esc_html_e('Bricks Builder - Use shortcode element or dynamic tags', 'aio-woocommerce'); ?></li>
                <li><?php esc_html_e('Gutenberg - Use Shortcode block', 'aio-woocommerce'); ?></li>
                <li><?php esc_html_e('Elementor - Use Shortcode widget', 'aio-woocommerce'); ?></li>
                <li><?php esc_html_e('Other page builders - Use Shortcode element/widget', 'aio-woocommerce'); ?></li>
            </ul>
        </div>
    </div>
    <div class="aio-wc-card">
        <h3 class="aio-wc-card__title"><?php esc_html_e('Format Tips', 'aio-woocommerce'); ?></h3>
        <p><?php esc_html_e('نمونه‌هایی از شورت‌کدها و خروجی آن‌ها روی فرانت‌اند:', 'aio-woocommerce'); ?></p>
        <table class="aio-wc-format-table">
        <thead>
            <tr>
                <th><?php esc_html_e('شورت‌کد', 'aio-woocommerce'); ?></th>
                <th><?php esc_html_e('خروجی نمونه', 'aio-woocommerce'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>[aio_wc_date format="j F Y"]</code></td>
                <td><?php esc_html_e('۰۲ آبان ۱۴۰۴', 'aio-woocommerce'); ?></td>
            </tr>
            <tr>
                <td><code>[aio_wc_post_date format="l j F Y"]</code></td>
                <td><?php esc_html_e('شنبه ۰۲ آبان ۱۴۰۴', 'aio-woocommerce'); ?></td>
            </tr>
            <tr>
                <td><code>[aio_wc_date format="j F Y \\ساعت H:i"]</code></td>
                <td><?php esc_html_e('۰۲ آبان ۱۴۰۴ ساعت ۱۴:۳۵', 'aio-woocommerce'); ?></td>
            </tr>
            <tr>
                <td><code>[aio_wc_date_both primary="gregorian" separator=" | " format="j F Y"]</code></td>
                <td><?php esc_html_e('۰۲ آبان ۱۴۰۴ | 02 November 2025', 'aio-woocommerce'); ?></td>
            </tr>
        </tbody>
    </table>
        <p><?php esc_html_e('نام ماه‌های فارسی به‌صورت خودکار زمانی که از توکن‌های F یا M استفاده کنید نمایش داده می‌شوند. این الگوها را در هر شورت‌کدی می‌توانید ترکیب کنید و با پارامتر format="" نتیجهٔ دلخواه را بگیرید.', 'aio-woocommerce'); ?></p>
    </div>
</div>
