<?php
/**
 * Settings tab (General/Misc)
 *
 * @var string $cleanup_on_delete
 * @var string $admin_font_choice
 * @var string $admin_font_custom_name
 * @var string $admin_font_custom_url
 * @var array  $font_presets
 */
?>
<?php
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'persian-calendar';
$is_active = $active_tab === 'general-settings';
?>
<div class="aio-wc-tab-content" id="general-settings" style="display: <?php echo $is_active ? 'block' : 'none'; ?>;">
    <div class="aio-wc-admin__content-header">
        <h2 class="aio-wc-admin__content-title"><?php esc_html_e('Plugin Settings', 'aio-woocommerce'); ?></h2>
        <p class="aio-wc-admin__content-desc"><?php esc_html_e('Global preferences for how the AIO WooCommerce plugin behaves.', 'aio-woocommerce'); ?></p>
    </div>

    <div class="aio-wc-card">
        <h3 class="aio-wc-card__title"><?php esc_html_e('Data Cleanup', 'aio-woocommerce'); ?></h3>
        <p class="aio-wc-card__desc"><?php esc_html_e('Choose whether to delete plugin data automatically when you delete the plugin.', 'aio-woocommerce'); ?></p>

        <div class="aio-wc-form-field">
            <div style="display: flex; align-items: center; gap: 10px;">
                <?php echo $this->toggle_switch('cleanup_on_delete', 'yes', isset($cleanup_on_delete) && $cleanup_on_delete === 'yes', false, 'cleanup_on_delete'); ?>
                <span class="aio-wc-toggle-label-text"><?php esc_html_e('Remove data when deleting plugin', 'aio-woocommerce'); ?></span>
            </div>
            <p class="description">
                <?php esc_html_e('When enabled, all plugin settings (including payment gateway credentials) are erased from the database during plugin deletion.', 'aio-woocommerce'); ?>
            </p>
        </div>

        <div class="aio-wc-card__actions">
            <button type="button" class="aio-wc-section-save-btn aio-wc-btn aio-wc-btn--primary">
                <?php esc_html_e('Save Settings', 'aio-woocommerce'); ?>
            </button>
        </div>
    </div>

    <div class="aio-wc-card">
        <h3 class="aio-wc-card__title"><?php esc_html_e('Access Control', 'aio-woocommerce'); ?></h3>
        <p class="aio-wc-card__desc"><?php esc_html_e('Choose which WordPress roles can manage this plugin. Administrators always retain access.', 'aio-woocommerce'); ?></p>

        <div class="aio-wc-form-grid">
            <?php
            if ( ! function_exists( 'get_editable_roles' ) ) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }
            $roles = get_editable_roles();
            // Clear cache before reading to ensure we get the latest values
            wp_cache_delete('aio_wc_settings', 'options');
            wp_cache_delete('alloptions', 'options');
            $settings_all = get_option( 'aio_wc_settings', array() );
            $allowed_roles = isset( $settings_all['allowed_roles'] ) && is_array( $settings_all['allowed_roles'] ) ? $settings_all['allowed_roles'] : array( 'administrator' );
            foreach ( $roles as $role_key => $role_data ) :
                $raw_label = isset( $role_data['name'] ) ? $role_data['name'] : ucfirst( $role_key );
                // Translate core roles
                if ( function_exists( 'translate_user_role' ) ) {
                    $label = translate_user_role( $raw_label );
                } else {
                    $label = $raw_label;
                }
                // Ensure WooCommerce roles are localized as well
                if ( in_array( $role_key, array( 'customer', 'shop_manager' ), true ) ) {
                    if ( 'customer' === $role_key ) {
                        $label = _x( 'Customer', 'User role', 'woocommerce' );
                    } elseif ( 'shop_manager' === $role_key ) {
                        $label = _x( 'Shop manager', 'User role', 'woocommerce' );
                    }
                }
                $checked = in_array( $role_key, $allowed_roles, true );
                $disabled = ( $role_key === 'administrator' ); // Always granted; cannot disable to avoid lockout
            ?>
            <div class="aio-wc-form-field" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <span class="aio-wc-label"><?php echo esc_html( $label ); ?></span>
                <div>
                    <?php
                        // render toggle: name format aio_wc_settings[allowed_roles][role_key] = yes|no
                        echo $this->toggle_switch( 'allowed_roles[' . $role_key . ']', 'yes', $checked, $disabled, 'allowed_role_' . esc_attr( $role_key ) );
                    ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="aio-wc-card__actions">
            <button type="button" class="aio-wc-section-save-btn aio-wc-btn aio-wc-btn--primary">
                <?php esc_html_e('Save Settings', 'aio-woocommerce'); ?>
            </button>
        </div>
    </div>

    <div class="aio-wc-card">
        <h3 class="aio-wc-card__title"><?php esc_html_e('Admin Typography', 'aio-woocommerce'); ?></h3>
        <p class="aio-wc-card__desc"><?php esc_html_e('Change the font that appears inside the AIO WooCommerce admin experience.', 'aio-woocommerce'); ?></p>

        <div class="aio-wc-form-field">
            <label for="admin_font_choice" class="aio-wc-label"><?php esc_html_e('Select a font', 'aio-woocommerce'); ?></label>
            <select name="aio_wc_settings[admin_font_choice]" id="admin_font_choice" class="aio-wc-select" data-font-choice>
                <?php foreach ($font_presets as $font_key => $font_data) : ?>
                    <option value="<?php echo esc_attr($font_key); ?>" <?php selected($admin_font_choice, $font_key); ?> data-font-family="<?php echo esc_attr(isset($font_data['family']) ? $font_data['family'] : ''); ?>">
                        <?php echo esc_html($font_data['label']); ?>
                    </option>
                <?php endforeach; ?>
                <option value="custom" <?php selected($admin_font_choice, 'custom'); ?>>
                    <?php esc_html_e('Upload custom font', 'aio-woocommerce'); ?>
                </option>
            </select>
            <p class="description"><?php esc_html_e('Choose one of the bundled presets or switch to "Upload custom font" to use your own files.', 'aio-woocommerce'); ?></p>
        </div>

        <div class="aio-wc-font-custom" data-font-custom-fields style="<?php echo ($admin_font_choice === 'custom') ? '' : 'display: none;'; ?>">
            <div class="aio-wc-form-field">
                <label for="admin_font_custom_name" class="aio-wc-label"><?php esc_html_e('Custom font family name', 'aio-woocommerce'); ?></label>
                <input type="text" id="admin_font_custom_name" name="aio_wc_settings[admin_font_custom_name]" class="aio-wc-input" value="<?php echo esc_attr($admin_font_custom_name); ?>" placeholder="<?php esc_attr_e('مثال: IranYekan', 'aio-woocommerce'); ?>">
                <p class="description"><?php esc_html_e('This text is used inside CSS. It should match the font\'s "font-family" name.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-form-field">
                <label for="admin_font_custom_url" class="aio-wc-label"><?php esc_html_e('Upload font file', 'aio-woocommerce'); ?></label>
                <div class="aio-wc-upload-field">
                    <input type="text" id="admin_font_custom_url" class="aio-wc-input" name="aio_wc_settings[admin_font_custom_url]" value="<?php echo esc_attr($admin_font_custom_url); ?>" placeholder="<?php esc_attr_e('https://example.com/fonts/my-font.woff2', 'aio-woocommerce'); ?>" data-preview-text="<?php esc_attr_e('پیش نمایش فونت در محیط مدیریت', 'aio-woocommerce'); ?>">
                    <button type="button" class="button" data-font-upload data-title="<?php esc_attr_e('Select font file', 'aio-woocommerce'); ?>" data-button="<?php esc_attr_e('Use this font', 'aio-woocommerce'); ?>">
                        <?php esc_html_e('Upload / Choose', 'aio-woocommerce'); ?>
                    </button>
                </div>
                <p class="description"><?php esc_html_e('Supported formats: WOFF2, WOFF, TTF, OTF. One regular weight is enough—other styles can be added via custom CSS if needed.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-font-preview" data-font-preview>
                <?php esc_html_e('نمونه نوشته برای پیش نمایش فونت', 'aio-woocommerce'); ?>
            </div>
        </div>

        <div class="aio-wc-card__actions">
            <button type="button" class="aio-wc-section-save-btn aio-wc-btn aio-wc-btn--primary">
                <?php esc_html_e('Save Settings', 'aio-woocommerce'); ?>
            </button>
        </div>
    </div>
</div>

