<?php
/**
 * MDS2 upgrade choice setup step.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<li class="<?php echo esc_attr($is_complete ? 'is-complete' : 'is-pending'); ?> mds3-mds2-upgrade-step">
    <h2><?php esc_html_e('Million Dollar Script 2 Upgrade Choice', 'million-dollar-script'); ?></h2>
    <p><?php esc_html_e('Million Dollar Script detected whether an older Million Dollar Script 2 install is present. It will not import Million Dollar Script 2 data or deactivate the old plugin unless you choose that action here.', 'million-dollar-script'); ?></p>

    <?php if ($plugin_rows) : ?>
        <ul class="mds3-inline-list mds3-plugin-detection-list">
            <?php foreach ($plugin_rows as $plugin) : ?>
                <li><strong><?php echo esc_html($plugin['name']); ?></strong> <code><?php echo esc_html($plugin['plugin_file']); ?></code> <span class="mds3-badge"><?php echo esc_html($plugin['state']); ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php else : ?>
        <p><?php esc_html_e('No installed Million Dollar Script 2 plugin package was found.', 'million-dollar-script'); ?></p>
    <?php endif; ?>

    <p>
        <?php
        echo esc_html(sprintf(
            /* translators: 1: source prefix, 2: row count */
            __('Migration source prefix: %1$s. Rows detected in core Million Dollar Script 2 tables: %2$s.', 'million-dollar-script'),
            (string) ($legacy_source['source_prefix'] ?? ''),
            number_format_i18n(absint($legacy_source['rows'] ?? 0))
        ));
        ?>
    </p>

    <?php if ($active) : ?>
        <div class="notice notice-warning inline"><p><?php esc_html_e('Running Million Dollar Script 2 and Million Dollar Script together is allowed, but not recommended for long. Keep both only while you compare the new grid and migration results.', 'million-dollar-script'); ?></p></div>
    <?php endif; ?>

    <?php if ($has_data && !$grid_enabled) : ?>
        <div class="notice notice-info inline"><p><?php esc_html_e('Classic Pixel Grid is required before Million Dollar Script 2 grid data can be imported. Enable it in Site capabilities if you want to migrate the old grid.', 'million-dollar-script'); ?></p></div>
    <?php endif; ?>

    <div class="mds3-button-row mds3-mds2-actions">
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-migration&source_prefix=' . rawurlencode((string) ($legacy_source['source_prefix'] ?? '')))); ?>"><?php esc_html_e('Review migration dry run', 'million-dollar-script'); ?></a>
        <?php if ($has_data && !$grid_enabled) : ?>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=mds3-setup')); ?>"><?php esc_html_e('Enable Classic Pixel Grid', 'million-dollar-script'); ?></a>
        <?php endif; ?>
        <?php if ($active) : ?>
            <?php $this->inline_post_button('mds3_mds2_keep_active', 'mds3_mds2_keep_active', [], __('Keep Million Dollar Script 2 active for now', 'million-dollar-script'), 'button-secondary'); ?>
            <?php if ($has_data && $grid_enabled) : ?>
                <?php $this->inline_post_button('mds3_mds2_import_deactivate', 'mds3_mds2_import_deactivate', ['source_prefix' => (string) ($legacy_source['source_prefix'] ?? '')], __('Import Million Dollar Script 2 data and deactivate Million Dollar Script 2', 'million-dollar-script'), 'button-primary'); ?>
            <?php endif; ?>
            <?php $this->inline_post_button('mds3_mds2_deactivate', 'mds3_mds2_deactivate', [], __('Deactivate Million Dollar Script 2 without importing', 'million-dollar-script'), 'button-secondary'); ?>
        <?php elseif ($has_data && $grid_enabled) : ?>
            <?php $this->inline_post_button('mds3_run_migration_import', 'mds3_run_migration_import', ['source_prefix' => (string) ($legacy_source['source_prefix'] ?? '')], __('Import Million Dollar Script 2 data only', 'million-dollar-script'), 'button-primary'); ?>
        <?php endif; ?>
    </div>

    <?php if ('keep_active' === $choice) : ?>
        <p class="description"><?php esc_html_e('Current choice: keep both plugins active. Million Dollar Script legacy shortcode/block aliases stay disabled until Million Dollar Script 2 is no longer active.', 'million-dollar-script'); ?></p>
    <?php endif; ?>
</li>
