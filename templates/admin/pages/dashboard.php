<?php
/**
 * MDS3 dashboard page.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$grid_count = absint($grid_count ?? 0);
?>
<div class="wrap mds3-admin mds3-dashboard">
    <h1 class="screen-reader-text"><?php esc_html_e('Million Dollar Script Dashboard', 'million-dollar-script'); ?></h1>
    <section class="mds3-dashboard-hero">
        <div class="mds3-dashboard-hero-main">
            <p class="mds3-dashboard-eyebrow"><?php esc_html_e('Pixels to Profit', 'million-dollar-script'); ?></p>
            <div class="mds3-dashboard-title" role="heading" aria-level="1"><?php esc_html_e('Million Dollar Script', 'million-dollar-script'); ?></div>
            <p>
                <?php echo esc_html($grid_enabled ? __('Manage pixel grids, payments, extensions, and API access from one clear workspace.', 'million-dollar-script') : __('Choose site capabilities, connect extensions, and expose secure APIs from one clear workspace.', 'million-dollar-script')); ?>
            </p>
            <div class="mds3-dashboard-actions">
                <?php if ($grid_enabled) : ?>
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=mds3-grids')); ?>">
                        <?php echo esc_html($grid_count ? __('Manage grids', 'million-dollar-script') : __('Create first grid', 'million-dollar-script')); ?>
                    </a>
                <?php else : ?>
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=mds3-setup')); ?>">
                        <?php esc_html_e('Choose capabilities', 'million-dollar-script'); ?>
                    </a>
                <?php endif; ?>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-extensions')); ?>"><?php esc_html_e('Explore extensions', 'million-dollar-script'); ?></a>
                <a class="button" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View site', 'million-dollar-script'); ?></a>
            </div>
        </div>
        <div class="mds3-dashboard-market-map" aria-hidden="true">
            <?php foreach ($this->dashboard_hero_placements() as $placement) : ?>
                <span class="mds3-placement-<?php echo esc_attr($placement['state']); ?>" style="--x:<?php echo esc_attr($placement['x']); ?>%;--y:<?php echo esc_attr($placement['y']); ?>%;--w:<?php echo esc_attr($placement['w']); ?>%;--h:<?php echo esc_attr($placement['h']); ?>%;--delay:<?php echo esc_attr($placement['delay']); ?>s"></span>
            <?php endforeach; ?>
        </div>
    </section>

    <?php $this->dashboard_menu(); ?>

    <?php $this->dashboard_extension_cards($catalog ?? []); ?>

    <section class="mds3-card mds3-dashboard-services-panel">
        <div class="mds3-card-heading">
            <div>
                <h2><?php esc_html_e('Services and upgrades', 'million-dollar-script'); ?></h2>
                <p><?php esc_html_e('Helpful add-ons, setup help, custom work, and hosting resources.', 'million-dollar-script'); ?></p>
            </div>
        </div>
        <?php $this->dashboard_service_cards(); ?>
    </section>

    <section class="mds3-dashboard-metrics" aria-label="<?php esc_attr_e('Dashboard metrics', 'million-dollar-script'); ?>">
        <?php if ($grid_enabled) : ?>
            <?php $this->dashboard_metric(__('Grids', 'million-dollar-script'), number_format_i18n($grid_count), __('Configured advertising grids', 'million-dollar-script'), admin_url('admin.php?page=mds3-grids')); ?>
            <?php $this->dashboard_metric(__('Waiting orders', 'million-dollar-script'), number_format_i18n(absint($order_counts['reserved'] ?? 0) + absint($order_counts['pending_payment'] ?? 0)), __('Reserved or awaiting payment', 'million-dollar-script'), admin_url('admin.php?page=mds3-orders')); ?>
            <?php
            $paid_revenue_url = \MillionDollarScript\Core\Hooks::apply(
                'million-dollar-script/dashboard/paid/revenue/url',
                admin_url('admin.php?page=mds3-orders'),
                $order_counts,
                $settings
            );
            $this->dashboard_metric(__('Paid revenue', 'million-dollar-script'), $this->dashboard_money((float) ($order_counts['paid_total'] ?? 0), $settings), __('Completed Million Dollar Script orders', 'million-dollar-script'), $paid_revenue_url);
            ?>
            <?php
            $this->dashboard_metric(__('Standard pages', 'million-dollar-script'), $missing_pages ? sprintf(
                /* translators: %d: missing pages */
                __('%d missing', 'million-dollar-script'),
                count($missing_pages)
            ) : __('Ready', 'million-dollar-script'), __('Required public pages', 'million-dollar-script'), admin_url('admin.php?page=mds3-setup'));
            ?>
        <?php else : ?>
            <?php $this->dashboard_metric(__('Classic grid', 'million-dollar-script'), __('Disabled', 'million-dollar-script'), __('Enable it from setup when you want pixel-grid sales.', 'million-dollar-script'), admin_url('admin.php?page=mds3-setup')); ?>
            <?php $this->dashboard_metric(__('Active extensions', 'million-dollar-script'), number_format_i18n($active_extensions), __('Installed extensions currently running.', 'million-dollar-script'), admin_url('admin.php?page=mds3-extensions')); ?>
            <?php $this->dashboard_metric(__('API endpoints', 'million-dollar-script'), number_format_i18n($endpoint_count), __('Discoverable secure platform routes.', 'million-dollar-script'), admin_url('admin.php?page=mds3-api')); ?>
            <?php $this->dashboard_metric(__('Catalog', 'million-dollar-script'), number_format_i18n($available_extensions), __('Extensions available from the catalog.', 'million-dollar-script'), admin_url('admin.php?page=mds3-extensions')); ?>
        <?php endif; ?>
    </section>

    <?php if ($grid_enabled) : ?>
        <section class="mds3-card mds3-dashboard-card-large">
            <div class="mds3-card-heading">
                <div>
                    <h2><?php esc_html_e('Recent orders', 'million-dollar-script'); ?></h2>
                    <p><?php esc_html_e('Newest reservations, payments, and advertiser uploads.', 'million-dollar-script'); ?></p>
                </div>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-orders')); ?>"><?php esc_html_e('View all', 'million-dollar-script'); ?></a>
            </div>
            <?php $this->dashboard_recent_orders($orders); ?>
        </section>
    <?php else : ?>
        <section class="mds3-card mds3-dashboard-card-large">
            <div class="mds3-card-heading">
                <div>
                    <h2><?php esc_html_e('Capability setup', 'million-dollar-script'); ?></h2>
                    <p><?php esc_html_e('Classic Pixel Grid is disabled, so grid pages, orders, and grid REST routes are not registered.', 'million-dollar-script'); ?></p>
                </div>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-setup')); ?>"><?php esc_html_e('Open setup', 'million-dollar-script'); ?></a>
            </div>
            <p><?php esc_html_e('Enable Classic Pixel Grid for a traditional Million Dollar Script site, or keep core lean while you install another product extension.', 'million-dollar-script'); ?></p>
        </section>
    <?php endif; ?>

    <?php \MillionDollarScript\Core\Hooks::do('million-dollar-script/dashboard/after', $this); ?>
</div>
