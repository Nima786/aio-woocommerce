<?php
/**
 * Sidebar navigation for AIO WooCommerce admin.
 *
 * @var string $title
 * @var array  $links
 */
?>
<aside class="aio-wc-sidebar">
    <div class="aio-wc-sidebar__header">
        <div class="aio-wc-sidebar__header-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
                <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
        </div>
        <div class="aio-wc-sidebar__header-title"><?php echo esc_html($title); ?></div>
    </div>
    <nav class="aio-wc-sidebar__nav">
        <?php foreach ($links as $link) :
            $is_active = !empty($link['active']);
            $classes   = 'aio-wc-sidebar__link' . ($is_active ? ' aio-wc-sidebar__link--active' : '');
            $url = add_query_arg('tab', $link['id'], admin_url('admin.php?page=aio-woocommerce'));
            ?>
            <a href="<?php echo esc_url($url); ?>" data-tab="<?php echo esc_attr($link['id']); ?>" class="<?php echo esc_attr($classes); ?>">
                <?php echo esc_html($link['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
