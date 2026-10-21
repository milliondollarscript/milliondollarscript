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
    <h2><?php esc_html_e('Million Dollar Script 2 Migration', 'million-dollar-script'); ?><?php $this->step_status($is_complete ? 'complete' : 'pending'); ?></h2>
    <?php if ($import_summary) : ?>
        <p><?php esc_html_e('Your older Million Dollar Script 2 data has been imported into Million Dollar Script. The original tables are kept, so you can review the dry run and run the import again at any time.', 'million-dollar-script'); ?></p>
    <?php else : ?>
        <p><?php esc_html_e('Million Dollar Script detected an older Million Dollar Script 2 install. Open the migration to review a dry run, then start the import on that screen. Nothing is imported or deleted until you start the import.', 'million-dollar-script'); ?></p>
    <?php endif; ?>

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

    <?php if ($import_summary) : ?>
        <p>
            <?php
            echo esc_html(sprintf(
                /* translators: 1: run id, 2: imported record count, 3: skipped count, 4: repaired count, 5: warning count */
                __('Import run %1$s completed %2$s: %3$d records imported, %4$d skipped, %5$d repaired, %6$d warnings.', 'million-dollar-script'),
                absint($import_run['id'] ?? 0),
                (string) ($import_summary['completed_at'] ?? ''),
                absint($import_summary['imported_total'] ?? 0),
                absint($import_summary['skipped'] ?? 0),
                absint($import_summary['repaired'] ?? 0),
                absint($import_summary['warnings'] ?? 0)
            ));
            ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=mds3-migration')); ?>"><?php esc_html_e('Review the verification report', 'million-dollar-script'); ?></a>
        </p>

        <?php
        // The customer site is the live example of this: an import started by an earlier
        // alpha, read later by a newer one. One sentence, written once in the summary, so
        // this screen and the migration screen cannot word the same fact differently.
        $older_build_note = trim((string) ($import_summary['older_build_note'] ?? ''));
        if ('' !== $older_build_note) : ?>
            <div class="notice notice-info inline">
                <p><?php echo esc_html($older_build_note); ?></p>
            </div>
        <?php endif; ?>

        <?php if ('' !== (string) ($import_summary['coverage_note'] ?? '')) : ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php echo esc_html((string) $import_summary['coverage_note']); ?>
                    <?php esc_html_e('Deactivating Million Dollar Script 2 now leaves those records only in the new site, where they are missing. Correct the old data and run the import again, or keep both plugins active until they are dealt with.', 'million-dollar-script'); ?>
                </p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

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
            <?php $this->inline_post_button('mds3_mds2_deactivate', 'mds3_mds2_deactivate', ['mds2_redirect' => 'setup'], $import_summary ? __('Deactivate Million Dollar Script 2') : __('Deactivate Million Dollar Script 2 without importing', 'million-dollar-script'), 'button-secondary'); ?>
        <?php endif; ?>
    </div>

    <?php if ('keep_active' === $choice) : ?>
        <p class="description"><?php esc_html_e('Current choice: keep both plugins active. Million Dollar Script legacy shortcode/block aliases stay disabled until Million Dollar Script 2 is no longer active.', 'million-dollar-script'); ?></p>
    <?php endif; ?>
</li>
