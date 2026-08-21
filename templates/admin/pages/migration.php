<?php
/**
 * MDS3 migration dry-run admin page.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$dry_run_warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
$latest_report = is_array($latest['report'] ?? null) ? $latest['report'] : [];
$latest_warnings = is_array($latest_report['warnings'] ?? null) ? $latest_report['warnings'] : [];
?>
<div class="wrap mds3-admin">
    <h1><?php esc_html_e('Million Dollar Script 2 Migration Dry Run', 'million-dollar-script'); ?></h1>
    <section class="mds3-card">
        <p><?php esc_html_e('This screen inventories Million Dollar Script 2 tables, options, pages, users, and media references. It never drops or mutates Million Dollar Script 2 tables.', 'million-dollar-script'); ?></p>

        <?php if (!$grid_enabled) : ?>
            <div class="notice notice-info inline">
                <p><?php esc_html_e('Classic Pixel Grid is required before importing Million Dollar Script 2 grid data. You can still review this dry run without importing anything.', 'million-dollar-script'); ?></p>
                <p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=mds3-setup')); ?>"><?php esc_html_e('Enable Classic Pixel Grid', 'million-dollar-script'); ?></a></p>
            </div>
        <?php endif; ?>

        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="mds3-migration" />
            <?php $this->field('source_prefix', __('Million Dollar Script 2 Table Prefix', 'million-dollar-script'), 'text', $report['source_prefix'] ?? $source_prefix); ?>
            <?php submit_button(__('Run dry run', 'million-dollar-script'), 'secondary', '', false); ?>
        </form>

        <?php if (!empty($_GET['imported'])) : ?>
            <div class="notice notice-success inline"><p><?php esc_html_e('Migration import completed. Review grids, migrated pages, and the latest verification report.', 'million-dollar-script'); ?></p></div>
        <?php endif; ?>

        <?php if (!empty($_GET['started'])) : ?>
            <div class="notice notice-info inline"><p><?php esc_html_e('Migration import started. Keep this page open to process batches automatically, or return later to continue.', 'million-dollar-script'); ?></p></div>
        <?php endif; ?>

        <?php if (!empty($_GET['continued'])) : ?>
            <div class="notice notice-success inline"><p><?php esc_html_e('Migration import continued. Review the latest verification report before deactivating Million Dollar Script 2.', 'million-dollar-script'); ?></p></div>
        <?php endif; ?>

        <?php if (!empty($_GET['retried'])) : ?>
            <div class="notice notice-success inline"><p><?php esc_html_e('Migration import ran again. Review the latest verification report and warning list.', 'million-dollar-script'); ?></p></div>
        <?php endif; ?>

        <?php if (!empty($_GET['paused'])) : ?>
            <div class="notice notice-info inline"><p><?php esc_html_e('Migration import paused. Resume it when you are ready to continue processing batches.', 'million-dollar-script'); ?></p></div>
        <?php endif; ?>

        <?php if (!empty($_GET['migration_error'])) : ?>
            <div class="notice notice-error inline">
                <p>
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %s: migration error */
                        __('Migration import failed: %s', 'million-dollar-script'),
                        sanitize_text_field(rawurldecode(wp_unslash($_GET['migration_error'])))
                    ));
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['pages'])) : ?>
            <div class="notice notice-success inline"><p><?php esc_html_e('Standard pages are ready. Existing migrated pages were preserved.', 'million-dollar-script'); ?></p></div>
        <?php endif; ?>

        <?php if (!empty($_GET['pages_error'])) : ?>
            <div class="notice notice-error inline"><p><?php echo esc_html(sanitize_text_field(rawurldecode(wp_unslash($_GET['pages_error'])))); ?></p></div>
        <?php endif; ?>

        <?php if ($dry_run_warnings || $latest_warnings) : ?>
            <div id="mds3-migration-warnings" class="notice notice-warning inline mds3-migration-warning-summary" role="status" aria-live="polite">
                <p class="mds3-migration-warning-title"><?php esc_html_e('Migration warnings', 'million-dollar-script'); ?></p>
                <p><?php esc_html_e('Review these before importing again or deactivating Million Dollar Script 2.', 'million-dollar-script'); ?></p>

                <?php if ($dry_run_warnings) : ?>
                    <div class="mds3-migration-warning-group">
                        <p><?php esc_html_e('Dry run warnings:', 'million-dollar-script'); ?></p>
                        <ul>
                            <?php foreach ($dry_run_warnings as $warning) : ?>
                                <li><?php echo esc_html($warning); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($latest_warnings) : ?>
                    <div class="mds3-migration-warning-group">
                        <p><?php esc_html_e('Latest import warnings:', 'million-dollar-script'); ?></p>
                        <ul>
                            <?php foreach ($latest_warnings as $warning) : ?>
                                <li><?php echo esc_html($warning); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($latest) : ?>
            <?php $this->migration_recovery_panel($latest, $grid_enabled); ?>
        <?php endif; ?>

        <h2><?php esc_html_e('Source Tables', 'million-dollar-script'); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Table', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Exists', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Rows', 'million-dollar-script'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['tables'] as $table) : ?>
                    <tr>
                        <td><code><?php echo esc_html($table['table']); ?></code></td>
                        <td><?php echo esc_html($table['exists'] ? 'yes' : 'no'); ?></td>
                        <td><?php echo esc_html($table['rows']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2><?php esc_html_e('Pages And Options', 'million-dollar-script'); ?></h2>
        <p>
            <?php
            /* translators: 1: detected legacy option count, 2: detected legacy page count. */
            $pages_options_message = __('Detected %1$d Million Dollar Script option records and %2$d Million Dollar Script pages.', 'million-dollar-script');
            echo esc_html(sprintf(
                $pages_options_message,
                absint($report['options']['count'] ?? 0),
                absint($report['pages']['count'] ?? 0)
            ));
            ?>
        </p>
        <?php if (!empty($report['pages']['by_type'])) : ?>
            <ul class="mds3-inline-list">
                <?php foreach ($report['pages']['by_type'] as $type => $total) : ?>
                    <li><code><?php echo esc_html($type); ?></code>: <?php echo esc_html($total); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php $this->standard_pages_panel(false); ?>

        <h2><?php esc_html_e('Target Tables', 'million-dollar-script'); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Table', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Exists', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Rows', 'million-dollar-script'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['target'] as $table) : ?>
                    <tr>
                        <td><code><?php echo esc_html($table['table']); ?></code></td>
                        <td><?php echo esc_html($table['exists'] ? 'yes' : 'no'); ?></td>
                        <td><?php echo esc_html($table['rows']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($grid_enabled) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mds3_run_migration_import'); ?>
                <input type="hidden" name="action" value="mds3_run_migration_import" />
                <input type="hidden" name="source_prefix" value="<?php echo esc_attr($report['source_prefix'] ?? ''); ?>" />
                <?php submit_button(__('Start migration import', 'million-dollar-script'), 'secondary'); ?>
            </form>
        <?php endif; ?>

        <?php if ($latest) : ?>
            <?php $this->migration_verification_report($latest); ?>
        <?php endif; ?>
    </section>
</div>
