<?php
/**
 * Cart rules tab content.
 *
 * Variables:
 * @var string $cart_minimum_enabled
 * @var string $cart_min_rules_enabled
 * @var string $cart_minimum_display_value
 * @var string $cart_minimum_raw_value
 * @var string $cart_minimum_message
 * @var array  $cart_rules_normalized
 * @var string $cart_rule_message
 * @var array  $cart_rule_excluded_categories
 * @var array  $cart_rule_excluded_tags
 * @var array  $categories
 * @var array  $tags
 * @var string $max_rule_all_enabled
 * @var int    $max_rule_all_qty
 * @var string $max_rule_categories_enabled
 * @var int    $max_rule_categories_qty
 * @var array  $max_rule_categories_ids
 * @var string $max_rule_tags_enabled
 * @var int    $max_rule_tags_qty
 * @var array  $max_rule_tags_ids
 * @var string $max_rule_products_enabled
 * @var int    $max_rule_products_qty
 * @var string $max_rule_products_input
 */
?>
<?php
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'persian-calendar';
$is_active = $active_tab === 'cart-rules';
?>
<div class="aio-wc-tab-content" id="cart-rules" style="display: <?php echo $is_active ? 'block' : 'none'; ?>;">
    <div class="aio-wc-admin__content-header">
        <h2 class="aio-wc-admin__content-title"><?php esc_html_e('Cart, Min & Max Rules', 'aio-woocommerce'); ?></h2>
        <p class="aio-wc-admin__content-desc"><?php esc_html_e('Configure cart minimum totals and quantity limits based on product pricing or taxonomy.', 'aio-woocommerce'); ?></p>
    </div>

    <!-- Sub-navigation Tabs -->
    <nav class="aio-wc-sub-nav">
        <a href="#cart-rules/min-total" class="aio-wc-sub-nav__link aio-wc-sub-nav__link--active" data-sub-tab="min-total"><?php esc_html_e('Minimum Cart Total', 'aio-woocommerce'); ?></a>
        <a href="#cart-rules/min-quantity" class="aio-wc-sub-nav__link" data-sub-tab="min-quantity"><?php esc_html_e('Minimum Quantity Rules', 'aio-woocommerce'); ?></a>
        <a href="#cart-rules/max-quantity" class="aio-wc-sub-nav__link" data-sub-tab="max-quantity"><?php esc_html_e('Maximum Quantity Rules', 'aio-woocommerce'); ?></a>
    </nav>

    <!-- Sub-tab Content Panels -->
    <div id="sub-tab-min-total" class="aio-wc-sub-tab-content aio-wc-sub-tab-content--active">
        <div class="aio-wc-card">
            <h3 class="aio-wc-card__title"><?php esc_html_e('Minimum Cart Total', 'aio-woocommerce'); ?></h3>

            <div class="aio-wc-form-field">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <?php echo $this->toggle_switch('cart_minimum_enabled', 'yes', $cart_minimum_enabled === 'yes', false, 'cart_minimum_enabled'); ?>
                    <span class="aio-wc-toggle-label-text"><?php esc_html_e('Enforce minimum cart total', 'aio-woocommerce'); ?></span>
                </div>
                <p class="description"><?php esc_html_e('When enabled, the cart must meet the minimum total before checkout.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-form-field">
                <label for="cart_minimum_amount" class="aio-wc-label"><?php esc_html_e('Minimum Cart Total (Toman)', 'aio-woocommerce'); ?></label>
                <input type="text" inputmode="decimal" name="aio_wc_settings[cart_minimum_amount]" id="cart_minimum_amount" value="<?php echo esc_attr($cart_minimum_display_value); ?>" data-raw-value="<?php echo esc_attr($cart_minimum_raw_value); ?>" class="aio-wc-input aio-wc-input--full" placeholder="3,000,000">
                <p class="description"><?php esc_html_e('Customers must reach this cart total before they can checkout.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-form-field">
                <label for="cart_minimum_message" class="aio-wc-label"><?php esc_html_e('Cart Minimum Message', 'aio-woocommerce'); ?></label>
                <textarea name="aio_wc_settings[cart_minimum_message]" id="cart_minimum_message" rows="3" class="aio-wc-textarea"><?php echo esc_textarea($cart_minimum_message); ?></textarea>
                <p class="description"><?php esc_html_e('Available placeholders: {min_total}, {current_total}.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-card__actions">
                <button type="button" class="aio-wc-section-save-btn aio-wc-btn aio-wc-btn--primary"><?php esc_html_e('Save Cart Minimum Settings', 'aio-woocommerce'); ?></button>
            </div>
        </div>
    </div>

    <div id="sub-tab-min-quantity" class="aio-wc-sub-tab-content">
        <div class="aio-wc-card aio-wc-card--cart-rules">
            <h3 class="aio-wc-card__title"><?php esc_html_e('Minimum Quantity Rules', 'aio-woocommerce'); ?></h3>
            <p class="description" style="margin-bottom: var(--aio-wc-spacing-4);">
                <?php esc_html_e('Rules are evaluated from top to bottom. Leave the maximum price empty to cover all higher prices.', 'aio-woocommerce'); ?>
            </p>

            <div class="aio-wc-form-field">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <?php echo $this->toggle_switch('cart_min_rules_enabled', 'yes', $cart_min_rules_enabled === 'yes', false, 'cart_min_rules_enabled'); ?>
                    <span class="aio-wc-toggle-label-text"><?php esc_html_e('Enforce minimum quantity rules', 'aio-woocommerce'); ?></span>
                </div>
                <p class="description"><?php esc_html_e('Toggle off to ignore all minimum quantity tiers regardless of price.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-cart-rules__table-wrapper">
                <table class="aio-wc-cart-rules__table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Minimum Price (Toman)', 'aio-woocommerce'); ?></th>
                            <th><?php esc_html_e('Maximum Price (Toman)', 'aio-woocommerce'); ?></th>
                            <th><?php esc_html_e('Minimum Quantity', 'aio-woocommerce'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="aio-wc-cart-rules__rows"></tbody>
                </table>

                <div class="aio-wc-cart-rules__actions">
                    <button type="button" class="button button-secondary aio-wc-cart-rules__add"><?php esc_html_e('Add Rule', 'aio-woocommerce'); ?></button>
                </div>

                <input type="hidden" id="aio_wc_cart_rules" name="aio_wc_settings[cart_rules]" value="<?php echo esc_attr(wp_json_encode($cart_rules_normalized)); ?>">
            </div>

            <div class="aio-wc-form-field">
                <label for="cart_rule_message" class="aio-wc-label"><?php esc_html_e('Quantity Adjustment Message', 'aio-woocommerce'); ?></label>
                <textarea name="aio_wc_settings[cart_rule_message]" id="cart_rule_message" rows="3" class="aio-wc-textarea" placeholder="<?php echo esc_attr(__('مقدار سفارش برای {product} به حداقل مقدار {min_qty} عدد تنظیم شد.', 'aio-woocommerce')); ?>"><?php echo esc_textarea($cart_rule_message); ?></textarea>
                <p class="description"><?php esc_html_e('پیام نمایش داده شده هنگام تنظیم خودکار مقدار به حداقل.', 'aio-woocommerce'); ?></p>
                <p class="description"><?php esc_html_e('Available placeholders: {product}, {min_qty}, {price}.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-form-field">
                <label class="aio-wc-label"><?php esc_html_e('Exclude Categories', 'aio-woocommerce'); ?></label>
                <div class="aio-wc-toggle-grid">
                    <?php if (!empty($categories)) :
                        foreach ($categories as $category) :
                            $field_id = 'aio-wc-excluded-cat-' . $category->term_id;
                            $checked  = in_array($category->term_id, $cart_rule_excluded_categories, true);
                            ?>
                            <label class="aio-wc-toggle-option" for="<?php echo esc_attr($field_id); ?>">
                                <span class="aio-wc-toggle">
                                    <input type="checkbox" class="aio-wc-toggle__input" id="<?php echo esc_attr($field_id); ?>" name="aio_wc_settings[cart_rule_excluded_categories][]" value="<?php echo esc_attr($category->term_id); ?>" <?php checked($checked); ?>>
                                    <span class="aio-wc-toggle__slider"></span>
                                    <span class="aio-wc-toggle__status"></span>
                                </span>
                                <span class="aio-wc-toggle-option__label"><?php echo esc_html($category->name); ?></span>
                            </label>
                        <?php endforeach; else : ?>
                        <p class="description"><?php esc_html_e('No categories found.', 'aio-woocommerce'); ?></p>
                    <?php endif; ?>
                </div>
                <p class="description"><?php esc_html_e('Selected categories will bypass minimum quantity rules.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-form-field">
                <label class="aio-wc-label"><?php esc_html_e('Exclude Tags', 'aio-woocommerce'); ?></label>
                <div class="aio-wc-toggle-grid">
                    <?php if (!empty($tags)) :
                        foreach ($tags as $tag) :
                            $field_id = 'aio-wc-excluded-tag-' . $tag->term_id;
                            $checked  = in_array($tag->term_id, $cart_rule_excluded_tags, true);
                            ?>
                            <label class="aio-wc-toggle-option" for="<?php echo esc_attr($field_id); ?>">
                                <span class="aio-wc-toggle">
                                    <input type="checkbox" class="aio-wc-toggle__input" id="<?php echo esc_attr($field_id); ?>" name="aio_wc_settings[cart_rule_excluded_tags][]" value="<?php echo esc_attr($tag->term_id); ?>" <?php checked($checked); ?>>
                                    <span class="aio-wc-toggle__slider"></span>
                                    <span class="aio-wc-toggle__status"></span>
                                </span>
                                <span class="aio-wc-toggle-option__label"><?php echo esc_html($tag->name); ?></span>
                            </label>
                        <?php endforeach; else : ?>
                        <p class="description"><?php esc_html_e('No tags found.', 'aio-woocommerce'); ?></p>
                    <?php endif; ?>
                </div>
                <p class="description"><?php esc_html_e('Selected tags will bypass minimum quantity rules.', 'aio-woocommerce'); ?></p>
            </div>

            <div class="aio-wc-card__actions">
                <button type="button" class="aio-wc-section-save-btn aio-wc-btn aio-wc-btn--primary"><?php esc_html_e('Save Quantity Rules', 'aio-woocommerce'); ?></button>
            </div>
        </div>
    </div>

    <div id="sub-tab-max-quantity" class="aio-wc-sub-tab-content">
        <div class="aio-wc-card aio-wc-card--max-rules">
            <h3 class="aio-wc-card__title"><?php esc_html_e('Maximum Quantity Rules', 'aio-woocommerce'); ?></h3>
            <p class="description" style="margin-bottom: var(--aio-wc-spacing-4);">
                <?php esc_html_e('Set optional caps for how many units a customer can buy, targeting specific products, categories, tags, or the entire store.', 'aio-woocommerce'); ?>
            </p>

            <div class="aio-wc-max-block">
                <div class="aio-wc-max-block__header">
                    <?php echo $this->toggle_switch('cart_max_rule_all_enabled', 'yes', $max_rule_all_enabled === 'yes', false, 'cart_max_rule_all_enabled'); ?>
                    <span class="aio-wc-toggle-label-text"><?php esc_html_e('Limit entire store', 'aio-woocommerce'); ?></span>
                </div>
                <div class="aio-wc-max-block__body">
                    <label for="cart_max_rule_all_qty" class="aio-wc-label"><?php esc_html_e('Maximum quantity', 'aio-woocommerce'); ?></label>
                    <input type="number" min="1" step="1" id="cart_max_rule_all_qty" name="aio_wc_settings[cart_max_rule_all_qty]" value="<?php echo esc_attr($max_rule_all_qty); ?>" class="aio-wc-input aio-wc-input--compact">
                </div>
            </div>

            <div class="aio-wc-max-block">
                <div class="aio-wc-max-block__header">
                    <?php echo $this->toggle_switch('cart_max_rule_categories_enabled', 'yes', $max_rule_categories_enabled === 'yes', false, 'cart_max_rule_categories_enabled'); ?>
                    <span class="aio-wc-toggle-label-text"><?php esc_html_e('Limit specific categories', 'aio-woocommerce'); ?></span>
                </div>
                <div class="aio-wc-max-block__body">
                    <div class="aio-wc-max-block__grid">
                        <div class="aio-wc-max-block__grid-column">
                            <span class="aio-wc-max-block__grid-heading"><?php esc_html_e('Choose categories', 'aio-woocommerce'); ?></span>
                            <div class="aio-wc-max-block__grid-list">
                                <?php if (!empty($categories)) :
                                    foreach ($categories as $category) :
                                        $field_id = 'aio-wc-max-cat-' . $category->term_id;
                                        $checked  = in_array($category->term_id, $max_rule_categories_ids, true);
                                        ?>
                                        <label class="aio-wc-toggle-option" for="<?php echo esc_attr($field_id); ?>">
                                            <span class="aio-wc-toggle">
                                                <input type="checkbox" class="aio-wc-toggle__input" id="<?php echo esc_attr($field_id); ?>" name="aio_wc_settings[cart_max_rule_categories_ids][]" value="<?php echo esc_attr($category->term_id); ?>" <?php checked($checked); ?>>
                                                <span class="aio-wc-toggle__slider"></span>
                                                <span class="aio-wc-toggle__status"></span>
                                            </span>
                                            <span class="aio-wc-toggle-option__label"><?php echo esc_html($category->name); ?></span>
                                        </label>
                                    <?php endforeach; else : ?>
                                    <p class="description"><?php esc_html_e('No categories found.', 'aio-woocommerce'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="aio-wc-max-block__grid-input">
                            <label for="cart_max_rule_categories_qty" class="aio-wc-label"><?php esc_html_e('Maximum quantity', 'aio-woocommerce'); ?></label>
                            <input type="number" min="1" step="1" id="cart_max_rule_categories_qty" name="aio_wc_settings[cart_max_rule_categories_qty]" value="<?php echo esc_attr($max_rule_categories_qty); ?>" class="aio-wc-input aio-wc-input--compact">
                        </div>
                    </div>
                </div>
            </div>

            <div class="aio-wc-max-block">
                <div class="aio-wc-max-block__header">
                    <?php echo $this->toggle_switch('cart_max_rule_tags_enabled', 'yes', $max_rule_tags_enabled === 'yes', false, 'cart_max_rule_tags_enabled'); ?>
                    <span class="aio-wc-toggle-label-text"><?php esc_html_e('Limit specific tags', 'aio-woocommerce'); ?></span>
                </div>
                <div class="aio-wc-max-block__body">
                    <div class="aio-wc-max-block__grid">
                        <div class="aio-wc-max-block__grid-column">
                            <span class="aio-wc-max-block__grid-heading"><?php esc_html_e('Choose tags', 'aio-woocommerce'); ?></span>
                            <div class="aio-wc-max-block__grid-list">
                                <?php if (!empty($tags)) :
                                    foreach ($tags as $tag) :
                                        $field_id = 'aio-wc-max-tag-' . $tag->term_id;
                                        $checked  = in_array($tag->term_id, $max_rule_tags_ids, true);
                                        ?>
                                        <label class="aio-wc-toggle-option" for="<?php echo esc_attr($field_id); ?>">
                                            <span class="aio-wc-toggle">
                                                <input type="checkbox" class="aio-wc-toggle__input" id="<?php echo esc_attr($field_id); ?>" name="aio_wc_settings[cart_max_rule_tags_ids][]" value="<?php echo esc_attr($tag->term_id); ?>" <?php checked($checked); ?>>
                                                <span class="aio-wc-toggle__slider"></span>
                                                <span class="aio-wc-toggle__status"></span>
                                            </span>
                                            <span class="aio-wc-toggle-option__label"><?php echo esc_html($tag->name); ?></span>
                                        </label>
                                    <?php endforeach; else : ?>
                                    <p class="description"><?php esc_html_e('No tags found.', 'aio-woocommerce'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="aio-wc-max-block__grid-input">
                            <label for="cart_max_rule_tags_qty" class="aio-wc-label"><?php esc_html_e('Maximum quantity', 'aio-woocommerce'); ?></label>
                            <input type="number" min="1" step="1" id="cart_max_rule_tags_qty" name="aio_wc_settings[cart_max_rule_tags_qty]" value="<?php echo esc_attr($max_rule_tags_qty); ?>" class="aio-wc-input aio-wc-input--compact">
                        </div>
                    </div>
                </div>
            </div>

            <div class="aio-wc-max-block">
                <div class="aio-wc-max-block__header">
                    <?php echo $this->toggle_switch('cart_max_rule_products_enabled', 'yes', $max_rule_products_enabled === 'yes', false, 'cart_max_rule_products_enabled'); ?>
                    <span class="aio-wc-toggle-label-text"><?php esc_html_e('Limit specific products', 'aio-woocommerce'); ?></span>
                </div>
                <div class="aio-wc-max-block__body">
                    <label for="cart_max_rule_products_ids" class="aio-wc-label"><?php esc_html_e('Product IDs (comma separated)', 'aio-woocommerce'); ?></label>
                    <input type="text" id="cart_max_rule_products_ids" name="aio_wc_settings[cart_max_rule_products_ids]" value="<?php echo esc_attr($max_rule_products_input); ?>" class="aio-wc-input aio-wc-input--full" placeholder="12, 34, 56">
                    <label for="cart_max_rule_products_qty" class="aio-wc-label" style="margin-top: var(--aio-wc-spacing-3);">
                        <?php esc_html_e('Maximum quantity', 'aio-woocommerce'); ?>
                    </label>
                    <input type="number" min="1" step="1" id="cart_max_rule_products_qty" name="aio_wc_settings[cart_max_rule_products_qty]" value="<?php echo esc_attr($max_rule_products_qty); ?>" class="aio-wc-input aio-wc-input--compact">
                    <p class="description" style="margin-top: var(--aio-wc-spacing-2);">
                        <?php esc_html_e('Enter product IDs separated by commas. Leave empty to disable product-specific rule.', 'aio-woocommerce'); ?>
                    </p>
                </div>
            </div>

            <div class="aio-wc-card__actions">
                <button type="button" class="aio-wc-section-save-btn aio-wc-btn aio-wc-btn--primary"><?php esc_html_e('Save Maximum Rules', 'aio-woocommerce'); ?></button>
            </div>
        </div>
    </div>
</div>