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
// What the dry run or the last import explained about itself. These are kept apart from
// warnings: a warning points at records that need attention, this says what happened.
$dry_run_notices = is_array($report['notices'] ?? null) ? $report['notices'] : [];
// A dry run used to leave no trace of itself: the button reloaded the page and
// the only result was a wall of tables further down, so the click read as doing
// nothing (Vikunja #31 item 3). Count what it found so the notice can say so.
$dry_run_ran = !empty($_GET['mds3_dry_run']);
$dry_run_source_tables = 0;
$dry_run_source_rows = 0;
$dry_run_prefix = strtolower((string) ($report['source_prefix'] ?? ''));
foreach ((array) ($report['tables'] ?? []) as $dry_run_table) {
    // Only the tables under the prefix being scanned belong to that Million
    // Dollar Script 2 install; a few page tables sit at fixed names site-wide.
    $dry_run_table_name = strtolower((string) ($dry_run_table['table'] ?? ''));
    if (!empty($dry_run_table['exists']) && ('' === $dry_run_prefix || 0 === strpos($dry_run_table_name, $dry_run_prefix))) {
        $dry_run_source_tables++;
        $dry_run_source_rows += absint($dry_run_table['rows'] ?? 0);
    }
}
$latest_report = is_array($latest['report'] ?? null) ? $latest['report'] : [];
$latest_warnings = is_array($latest_report['warnings'] ?? null) ? $latest_report['warnings'] : [];
$latest_notices = is_array($latest_report['notices'] ?? null) ? $latest_report['notices'] : [];
$active_legacy_plugins = array_values(array_filter(is_array($legacy_plugins ?? null) ? $legacy_plugins : [], static function ($plugin) {
    return !empty($plugin['active']) || !empty($plugin['network_active']);
}));
?>
<div class="wrap mds3-admin">
    <h1><?php esc_html_e('Million Dollar Script 2 Migration Dry Run', 'million-dollar-script'); ?></h1>
    <section class="mds3-card">
        <p><?php esc_html_e('This screen inventories Million Dollar Script 2 tables, options, pages, users, and media references. It never drops or mutates Million Dollar Script 2 tables.', 'million-dollar-script'); ?></p>

        <?php if (!empty($_GET['mds2_action'])) : ?>
            <?php $this->mds2_action_notice(sanitize_key(wp_unslash($_GET['mds2_action'])), absint($_GET['deactivated'] ?? 0), absint($_GET['skipped'] ?? 0)); ?>
        <?php endif; ?>

        <?php if (!$grid_enabled) : ?>
            <div class="notice notice-info inline">
                <p><?php esc_html_e('Classic Pixel Grid is required before importing Million Dollar Script 2 grid data. You can still review this dry run without importing anything.', 'million-dollar-script'); ?></p>
                <p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=mds3-setup')); ?>"><?php esc_html_e('Enable Classic Pixel Grid', 'million-dollar-script'); ?></a></p>
            </div>
        <?php endif; ?>

        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="mds3-migration" />
            <input type="hidden" name="mds3_dry_run" value="1" />
            <?php $this->field('source_prefix', __('Million Dollar Script 2 Table Prefix', 'million-dollar-script'), 'text', $report['source_prefix'] ?? $source_prefix); ?>
            <?php submit_button(__('Run dry run', 'million-dollar-script'), 'secondary', '', false); ?>
        </form>

        <?php if ($dry_run_ran) : ?>
            <div class="notice notice-success inline">
                <p><?php esc_html_e('Dry run complete. Nothing was imported, changed, or deleted.', 'million-dollar-script'); ?></p>
                <p>
                    <?php
                    echo esc_html(sprintf(
                        /* translators: 1: table count, 2: row count, 3: option count, 4: page count, 5: warning count */
                        __('%1$d Million Dollar Script 2 tables hold %2$d rows. %3$d plugin options and %4$d pages were found. %5$d need attention before importing.', 'million-dollar-script'),
                        $dry_run_source_tables,
                        number_format_i18n($dry_run_source_rows),
                        absint($report['options']['count'] ?? 0),
                        absint($report['pages']['count'] ?? 0),
                        count($dry_run_warnings)
                    ));
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['imported'])) : ?>
            <div class="notice notice-success inline"><p><?php esc_html_e('Migration import completed. Review grids, migrated pages, and the latest verification report.', 'million-dollar-script'); ?></p></div>
        <?php endif; ?>

        <?php if (!empty($_GET['started'])) : ?>
            <div class="notice notice-info inline"><p><?php esc_html_e('Migration import started. Keep this page open to process the batches it started; if you leave it, the import stops where it is and you choose Process next batch to carry on.', 'million-dollar-script'); ?></p></div>
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

        <?php if (!empty($_GET['recheck_started'])) : ?>
            <?php
            $recheck_started = \MillionDollarScript\V3\Migration\Recheck\Registry::find(
                sanitize_text_field(rawurldecode(wp_unslash($_GET['recheck_started'])))
            );
            ?>
            <?php if ($recheck_started) : ?>
                <div class="notice notice-success inline">
                    <p>
                        <?php
                        echo esc_html(sprintf(
                            /* translators: %s: name of the re-check just started */
                            __('Started: %s. This page shows how far it has got and continues it where it stopped.', 'million-dollar-script'),
                            $recheck_started->title()
                        ));
                        ?>
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($_GET['pages']) && empty($_GET['pages_failed'])) : ?>
            <div class="notice notice-success inline"><p><?php esc_html_e('Standard pages are ready. Existing migrated pages were preserved.', 'million-dollar-script'); ?></p></div>
        <?php endif; ?>

        <?php $this->render_pages_created_notice(); ?>
        <?php $this->render_pages_failed_notice(); ?>

        <?php if (!empty($_GET['pages_error'])) : ?>
            <div class="notice notice-error inline"><p><?php echo esc_html(sanitize_text_field(rawurldecode(wp_unslash($_GET['pages_error'])))); ?></p></div>
        <?php endif; ?>

        <?php if ($dry_run_notices || $latest_notices) : ?>
            <div id="mds3-migration-notes" class="notice notice-info inline mds3-migration-note-summary" role="status" aria-live="polite">
                <p class="mds3-migration-note-title"><?php esc_html_e('About this migration', 'million-dollar-script'); ?></p>
                <p><?php esc_html_e('This explains what the migration did. Nothing here needs fixing.', 'million-dollar-script'); ?></p>

                <?php if ($dry_run_notices) : ?>
                    <div class="mds3-migration-note-group">
                        <p><?php esc_html_e('Dry run notes:', 'million-dollar-script'); ?></p>
                        <ul>
                            <?php foreach ($dry_run_notices as $notice) : ?>
                                <li><?php echo esc_html((string) $notice); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($latest_notices) : ?>
                    <div class="mds3-migration-note-group">
                        <p><?php esc_html_e('Latest import notes:', 'million-dollar-script'); ?></p>
                        <ul>
                            <?php foreach ($latest_notices as $notice) : ?>
                                <li><?php echo esc_html((string) $notice); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
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
            <?php $this->migration_rechecks_panel((string) ($report['source_prefix'] ?? '')); ?>
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
        <?php $this->standard_pages_panel(false, $report['pages']['candidates'] ?? [], 'migration', 'mds3-ensure-standard-pages mds3-migration-import'); ?>

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
            <form id="mds3-migration-import" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mds3_run_migration_import'); ?>
                <input type="hidden" name="action" value="mds3_run_migration_import" />
                <input type="hidden" name="source_prefix" value="<?php echo esc_attr($report['source_prefix'] ?? ''); ?>" />
                <p class="description"><?php esc_html_e('This import also prepares the standard pages: the page choices in the Standard Pages section above are applied as part of the import, so a separate import run does not remove them.', 'million-dollar-script'); ?></p>
                <?php submit_button(__('Start migration import', 'million-dollar-script'), 'secondary'); ?>
            </form>
        <?php endif; ?>

        <?php if ($active_legacy_plugins) : ?>
            <h2><?php esc_html_e('Million Dollar Script 2 Plugin', 'million-dollar-script'); ?></h2>
            <p><?php esc_html_e('Finish the import and review the verification report below before deactivating Million Dollar Script 2.', 'million-dollar-script'); ?></p>
            <div class="mds3-button-row mds3-mds2-actions">
                <?php $this->inline_post_button('mds3_mds2_deactivate', 'mds3_mds2_deactivate', ['mds2_redirect' => 'migration'], __('Deactivate Million Dollar Script 2', 'million-dollar-script'), 'button-primary'); ?>
                <?php $this->inline_post_button('mds3_mds2_keep_active', 'mds3_mds2_keep_active', ['mds2_redirect' => 'migration'], __('Keep Million Dollar Script 2 active for now', 'million-dollar-script'), 'button-secondary'); ?>
            </div>
        <?php endif; ?>

        <?php if ($latest) : ?>
            <?php $this->migration_verification_report($latest); ?>
        <?php endif; ?>
    </section>
</div>
