<?php
/**
 * MDS3 system status admin page.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$grid_capacity_status = is_array($grid_capacity_status ?? null) ? $grid_capacity_status : [];
$network_diagnostics = is_array($network_diagnostics ?? null) ? $network_diagnostics : [];
?>
<div class="wrap mds3-admin">
    <h1><?php esc_html_e('System Status', 'million-dollar-script'); ?></h1>
    <section class="mds3-card">
        <div class="mds3-card-heading">
            <div>
                <h2><?php esc_html_e('System Status', 'million-dollar-script'); ?></h2>
                <p><?php esc_html_e('Environment details useful for support, diagnostics, and launch checks.', 'million-dollar-script'); ?></p>
            </div>
        </div>
        <?php $this->dashboard_system_status($settings); ?>
    </section>

    <?php if (!empty($_GET['mds3_cache_cleared'])) : ?>
        <?php
        $tile_dirs_removed = absint($_GET['mds3_tile_dirs_removed'] ?? 0);
        $grids_rotated = absint($_GET['mds3_grids_rotated'] ?? 0);
        $transients_cleared = absint($_GET['mds3_transients_cleared'] ?? 0);
        $cache_cleared_message = sprintf(
            /* translators: 1: grid count, 2: transient count */
            _n('MDS cache cleared. Rotated cache keys for %1$d grid and removed %2$d plugin transient.', 'MDS cache cleared. Rotated cache keys for %1$d grids and removed %2$d plugin transients.', $grids_rotated, 'million-dollar-script'),
            $grids_rotated,
            $transients_cleared
        );
        ?>
        <div class="notice notice-success inline"><p>
            <?php echo esc_html($cache_cleared_message); ?>
            <?php if ($tile_dirs_removed > 0) : ?>
                <?php echo esc_html(sprintf(/* translators: 1: tile directory count */ _n('Also deleted %1$d cached tile directory.', 'Also deleted %1$d cached tile directories.', $tile_dirs_removed, 'million-dollar-script'), $tile_dirs_removed)); ?>
            <?php endif; ?>
        </p></div>
    <?php endif; ?>

    <section class="mds3-card">
        <div class="mds3-card-heading">
            <div>
                <h2><?php esc_html_e('Cache', 'million-dollar-script'); ?></h2>
                <p><?php esc_html_e('Clear the plugin cache without touching grid data, orders, or external CDN/page caches.', 'million-dollar-script'); ?></p>
            </div>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="mds3_clear_cache">
            <?php wp_nonce_field('mds3_clear_cache'); ?>
            <p><?php esc_html_e('Clear cache rotates each grid\'s tile cache key so browsers and proxies re-download the existing tile files instead of serving stale ones, and removes short-lived plugin transients. Existing tiles are kept and served as-is; nothing is regenerated. Grids using ImageGrid keep serving their remote tiles; nothing is sent to, or cleared from, any external service.', 'million-dollar-script'); ?></p>
            <p>
                <label>
                    <input type="checkbox" name="mds3_delete_tile_files" value="1">
                    <?php esc_html_e('Also delete cached tile files. This makes tiles regenerate from scratch on the next view, which can be slow for large grids.', 'million-dollar-script'); ?>
                </label>
            </p>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e('Clear cache', 'million-dollar-script'); ?></button>
            </p>
        </form>
    </section>

    <section class="mds3-card">
        <div class="mds3-card-heading">
            <div>
                <h2><?php esc_html_e('Grid Capacity', 'million-dollar-script'); ?></h2>
                <p><?php esc_html_e('Indexed counts that help identify when a grid needs a capacity review.', 'million-dollar-script'); ?></p>
            </div>
        </div>
        <table class="widefat striped">
            <tbody>
                <tr><th><?php esc_html_e('Configured grids', 'million-dollar-script'); ?></th><td><?php echo esc_html(number_format_i18n(absint($grid_capacity_status['grid_count'] ?? 0))); ?></td></tr>
                <tr><th><?php esc_html_e('Active placements', 'million-dollar-script'); ?></th><td><?php echo esc_html(number_format_i18n(absint($grid_capacity_status['active_placements'] ?? 0))); ?></td></tr>
                <tr><th><?php esc_html_e('Largest active grid', 'million-dollar-script'); ?></th><td><?php echo esc_html(sprintf(
                    /* translators: 1: grid ID, 2: active placement count. */
                    __('Grid #%1$d · %2$s placements', 'million-dollar-script'),
                    absint($grid_capacity_status['largest_grid_id'] ?? 0),
                    number_format_i18n(absint($grid_capacity_status['largest_active_placements'] ?? 0))
                )); ?></td></tr>
            </tbody>
        </table>
        <?php if (!empty($grid_capacity_status['needs_review'])) : ?>
            <p><a class="button" href="<?php echo esc_url(\MillionDollarScript\V3\Support\GridCapacityStatus::capacity_guide_url()); ?>"><?php esc_html_e('Review grid capacity guidance', 'million-dollar-script'); ?></a></p>
        <?php endif; ?>
    </section>
    <section class="mds3-card">
        <div class="mds3-card-heading">
            <div>
                <h2><?php esc_html_e('Network Diagnostics', 'million-dollar-script'); ?></h2>
                <p><?php esc_html_e('Check each required Million Dollar Script service separately. Results include only the endpoint, response class, timing, and a provider request ID when available.', 'million-dollar-script'); ?></p>
            </div>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mds3_run_network_diagnostics'); ?>
            <input type="hidden" name="action" value="mds3_run_network_diagnostics" />
            <button type="submit" class="button button-secondary"><?php esc_html_e('Run network diagnostics', 'million-dollar-script'); ?></button>
        </form>
        <?php if (!empty($network_diagnostics['checks']) && is_array($network_diagnostics['checks'])) : ?>
            <p><?php echo esc_html(sprintf(
                /* translators: %s: UTC timestamp. */
                __('Last checked %s', 'million-dollar-script'),
                (string) ($network_diagnostics['checked_at'] ?? '')
            )); ?></p>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('Service', 'million-dollar-script'); ?></th><th><?php esc_html_e('Result', 'million-dollar-script'); ?></th><th><?php esc_html_e('HTTP', 'million-dollar-script'); ?></th><th><?php esc_html_e('Time', 'million-dollar-script'); ?></th><th><?php esc_html_e('Request ID', 'million-dollar-script'); ?></th></tr></thead>
                <tbody>
                    <?php foreach ($network_diagnostics['checks'] as $check) : ?>
                        <tr>
                            <td><strong><?php echo esc_html((string) ($check['label'] ?? '')); ?></strong><br><code><?php echo esc_html((string) ($check['url'] ?? '')); ?></code></td>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', (string) ($check['outcome'] ?? 'unknown')))); ?><?php if (!empty($check['message'])) : ?><br><?php echo esc_html((string) $check['message']); ?><?php endif; ?></td>
                            <td><?php echo esc_html((string) ($check['status'] ?: '—')); ?></td>
                            <td><?php echo esc_html(absint($check['duration_ms'] ?? 0) . ' ms'); ?></td>
                            <td><code><?php echo esc_html((string) ($check['request_id'] ?: '—')); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><?php esc_html_e('A 401 or 403 may indicate authentication, account policy, a WAF rule, or a legally required restriction. Keep the request ID when contacting support; do not attempt to bypass an access decision.', 'million-dollar-script'); ?></p>
        <?php endif; ?>
    </section>
</div>
