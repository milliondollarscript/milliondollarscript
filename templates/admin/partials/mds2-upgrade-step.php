<?php
/**
 * MDS2 migration entry setup step.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<li class="<?php echo esc_attr($is_complete ? 'is-complete' : 'is-pending'); ?> mds3-mds2-upgrade-step">
    <h2><?php esc_html_e('Million Dollar Script 2 Migration', 'million-dollar-script'); ?></h2>
    <p><?php esc_html_e('Million Dollar Script detected an older Million Dollar Script 2 install. Open the migration to review a dry run, then start the import on that screen. Nothing is imported or deleted until you start the import.', 'million-dollar-script'); ?></p>

    <?php if ($plugin_rows) : ?>
        <ul class="mds3-inline-list mds3-plugin-detection-list">
            <?php foreach ($plugin_rows as $plugin) : ?>
                <li><strong><?php echo esc_html($plugin['name']); ?></strong> <code><?php echo esc_html($plugin['plugin_file']); ?></code> <span class="mds3-badge"><?php echo esc_html($plugin['state']); ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php else : ?>
        <p><?php esc_html_e('No installed Million Dollar Script 2 plugin package was found, but Million Dollar Script 2 tables were detected.', 'million-dollar-script'); ?></p>
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
        <div class="notice notice-warning inline"><p><?php esc_html_e('Running Million Dollar Script 2 and Million Dollar Script together is allowed while you compare the old grid with the migration results. Deactivate Million Dollar Script 2 once the import looks right.', 'million-dollar-script'); ?></p></div>
    <?php endif; ?>

    <?php if ($has_data && !$grid_enabled) : ?>
        <div class="notice notice-info inline"><p><?php esc_html_e('Classic Pixel Grid is required before Million Dollar Script 2 grid data can be imported. Enable it in Site capabilities first; you can still review the dry run without it.', 'million-dollar-script'); ?></p></div>
    <?php endif; ?>

    <div class="mds3-button-row mds3-mds2-actions">
        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=mds3-migration&source_prefix=' . rawurlencode((string) ($legacy_source['source_prefix'] ?? '')))); ?>"><?php esc_html_e('Open migration', 'million-dollar-script'); ?></a>
        <?php if ($has_data && !$grid_enabled) : ?>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-setup')); ?>"><?php esc_html_e('Enable Classic Pixel Grid', 'million-dollar-script'); ?></a>
        <?php endif; ?>
        <?php if ($active) : ?>
            <?php $this->inline_post_button('mds3_mds2_keep_active', 'mds3_mds2_keep_active', ['mds2_redirect' => 'setup'], __('Keep Million Dollar Script 2 active for now', 'million-dollar-script'), 'button-secondary'); ?>
            <?php $this->inline_post_button('mds3_mds2_deactivate', 'mds3_mds2_deactivate', ['mds2_redirect' => 'setup'], __('Deactivate Million Dollar Script 2 without importing', 'million-dollar-script'), 'button-secondary'); ?>
        <?php endif; ?>
    </div>

    <?php if ('keep_active' === $choice) : ?>
        <p class="description"><?php esc_html_e('Current choice: keep both plugins active. Million Dollar Script legacy shortcode/block aliases stay disabled until Million Dollar Script 2 is no longer active.', 'million-dollar-script'); ?></p>
    <?php endif; ?>
</li>
