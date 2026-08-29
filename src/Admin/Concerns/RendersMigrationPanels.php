<?php
/**
 * MDS2 upgrade notices and migration verification panels.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Setup\LegacyPlugin;
use MillionDollarScript\V3\Support\DB;
use MillionDollarScript\V3\Support\Template;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

trait RendersMigrationPanels {

    private function sanitize_source_prefix($prefix) {
        return LegacyPlugin::sanitize_source_prefix($prefix);
    }

    private function mds2_action_notice($action, $deactivated = 0, $skipped = 0) {
        $class = 'notice-info';
        $message = '';

        switch ($action) {
            case 'kept':
                $message = __('Million Dollar Script 2 will remain active. Million Dollar Script will not migrate data or take over legacy Million Dollar Script 2 embeds while Million Dollar Script 2 is active.', 'million-dollar-script');
                break;
            case 'deactivated':
                $class = 'notice-success';
                $message = sprintf(
                    /* translators: %d: plugin count */
                    _n('%d Million Dollar Script 2 plugin was deactivated. Million Dollar Script data was not imported.', '%d Million Dollar Script 2 plugins were deactivated. Million Dollar Script data was not imported.', $deactivated, 'million-dollar-script'),
                    $deactivated
                );
                break;
            case 'deactivation_partial':
                $class = 'notice-warning';
                $message = sprintf(
                    /* translators: 1: deactivated plugins, 2: skipped plugins */
                    __('Million Dollar Script 2 deactivation was partial. Deactivated: %1$d. Skipped: %2$d.', 'million-dollar-script'),
                    $deactivated,
                    $skipped
                );
                break;
            case 'import_failed':
                $class = 'notice-error';
                $error = sanitize_text_field(rawurldecode(wp_unslash($_GET['mds2_error'] ?? '')));
                $message = $error ? sprintf(
                    /* translators: %s: migration error */
                    __('Million Dollar Script 2 import failed: %s', 'million-dollar-script'),
                    $error
                ) : __('Million Dollar Script 2 import failed. Review the migration dry run before trying again.', 'million-dollar-script');
                break;
        }

        if (!$message) {
            return;
        }

        Template::display('admin/partials/mds2-action-notice.php', [
            'class' => $class,
            'message' => $message,
        ], $this);
    }

    private function mds2_upgrade_step(array $legacy_plugins, array $legacy_source) {
        if (!$this->has_mds2_upgrade_context($legacy_plugins, $legacy_source)) {
            return;
        }

        $active = array_values(array_filter($legacy_plugins, static function ($plugin) {
            return !empty($plugin['active']) || !empty($plugin['network_active']);
        }));
        $has_data = !empty($legacy_source['has_data']);
        $grid_enabled = $this->grid_enabled();
        $choice = LegacyPlugin::choice();
        $is_complete = (!$active && !$has_data) || in_array($choice, ['keep_active', 'deactivated', 'migrated', 'migrated_deactivated'], true);
        $plugin_rows = [];

        foreach ($legacy_plugins as $plugin) {
            $plugin_rows[] = [
                'name' => $plugin['name'] ?? $plugin['plugin_file'],
                'plugin_file' => $plugin['plugin_file'] ?? '',
                'state' => !empty($plugin['network_active'])
                    ? __('network active', 'million-dollar-script')
                    : (!empty($plugin['active']) ? __('active', 'million-dollar-script') : __('installed inactive', 'million-dollar-script')),
            ];
        }

        Template::display('admin/partials/mds2-upgrade-step.php', [
            'active' => $active,
            'choice' => $choice,
            'grid_enabled' => $grid_enabled,
            'has_data' => $has_data,
            'is_complete' => $is_complete,
            'legacy_source' => $legacy_source,
            'plugin_rows' => $plugin_rows,
        ], $this);
    }

    private function has_mds2_upgrade_context(array $legacy_plugins, array $legacy_source) {
        if (!empty($legacy_plugins) || !empty($legacy_source['has_data']) || absint($legacy_source['rows'] ?? 0) > 0) {
            return true;
        }

        if (LegacyPlugin::choice()) {
            return true;
        }

        return !empty($_GET['mds2_action']) || !empty($_GET['mds2_error']);
    }

    private function latest_migration_run($source_prefix = '') {
        global $wpdb;

        if (!DB::table_exists(DB::table('migration_runs'))) {
            return null;
        }

        $sql = 'SELECT * FROM ' . DB::ident(DB::table('migration_runs')) . " WHERE mode = 'import'";
        $args = [];
        if ($source_prefix) {
            $sql .= ' AND source_prefix = %s';
            $args[] = $source_prefix;
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';

        $row = $args ? $wpdb->get_row($wpdb->prepare($sql, $args), ARRAY_A) : $wpdb->get_row($sql, ARRAY_A);
        if (!is_array($row)) {
            return null;
        }

        $row['totals'] = json_decode((string) ($row['totals'] ?? ''), true) ?: [];
        $row['report'] = json_decode((string) ($row['report'] ?? ''), true) ?: [];

        return $row;
    }

    private function migration_recovery_panel(array $run, $grid_enabled) {
        Template::display('admin/partials/migration-recovery-panel.php', [
            'grid_enabled' => (bool) $grid_enabled,
            'run' => $run,
            'summary' => $this->migration_run_summary($run),
        ], $this);
    }

    private function migration_verification_report(array $run) {
        $report = is_array($run['report'] ?? null) ? $run['report'] : [];
        $totals = is_array($run['totals'] ?? null) ? $run['totals'] : [];
        $mapped = is_array($report['mapped'] ?? null) ? $report['mapped'] : [];
        $warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
        $skipped = is_array($report['skipped'] ?? null) ? $report['skipped'] : [];
        $repairs = is_array($report['repairs'] ?? null) ? $report['repairs'] : [];
        $page_outcomes = is_array($report['page_outcomes'] ?? null) ? $report['page_outcomes'] : [];
        $run_status = sanitize_key((string) ($run['status'] ?? ''));
        $warning_count = max(count($warnings), absint($totals['warnings'] ?? 0));
        $entities = [
            'grids' => __('Grids', 'million-dollar-script'),
            'packages' => __('Packages', 'million-dollar-script'),
            'price_rules' => __('Price rules', 'million-dollar-script'),
            'orders' => __('Orders', 'million-dollar-script'),
            'blocks' => __('Blocks', 'million-dollar-script'),
            'order_items' => __('Order items', 'million-dollar-script'),
            'placements' => __('Placements', 'million-dollar-script'),
            'pages' => __('Pages', 'million-dollar-script'),
        ];
        $rows = [];

        foreach ($entities as $key => $label) {
            $mapped_number = array_key_exists($key, $mapped) ? absint($mapped[$key]) : null;
            $imported = absint($totals[$key] ?? 0);
            $rows[] = [
                'imported' => $imported,
                'label' => $label,
                'mapped_total' => null === $mapped_number ? __('n/a', 'million-dollar-script') : (string) $mapped_number,
                'status' => $this->migration_stage_status($run_status, $warning_count, $imported, $mapped_number),
            ];
        }

        Template::display('admin/partials/migration-verification-report.php', [
            'page_outcomes' => $page_outcomes,
            'repairs' => $repairs,
            'rows' => $rows,
            'run' => $run,
            'skipped' => $skipped,
        ], $this);
    }

    private function migration_run_summary(array $run) {
        $totals = is_array($run['totals'] ?? null) ? $run['totals'] : [];
        $report = is_array($run['report'] ?? null) ? $run['report'] : [];
        $warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
        $job = is_array($report['job'] ?? null) ? $report['job'] : [];
        $status = sanitize_key((string) ($run['status'] ?? ''));
        $warning_count = max(count($warnings), absint($totals['warnings'] ?? 0));
        $imported_total = 0;
        $source_total = 0;
        $processed_total = 0;
        $stages = ['settings', 'grids', 'packages', 'price_rules', 'orders', 'blocks', 'order_items', 'placements', 'pages'];
        $source_totals = is_array($job['source_totals'] ?? null) ? $job['source_totals'] : [];
        $processed = is_array($job['processed'] ?? null) ? $job['processed'] : [];
        foreach ($stages as $stage) {
            $source_total += absint($source_totals[$stage] ?? 0);
            $processed_total += min(absint($source_totals[$stage] ?? 0), absint($processed[$stage] ?? 0));
        }
        $progress_percent = $source_total > 0 ? (int) floor(($processed_total / $source_total) * 100) : ('completed' === $status ? 100 : 0);
        $progress_percent = min(100, max(0, $progress_percent));
        $stage_index = absint($job['stage_index'] ?? 0);
        $current_stage = $stages[$stage_index] ?? '';

        foreach (['settings', 'grids', 'packages', 'price_rules', 'orders', 'blocks', 'order_items', 'placements', 'pages'] as $key) {
            $imported_total += absint($totals[$key] ?? 0);
        }

        $summary = [
            'action_context' => 'import',
            'action_label' => __('Run import again', 'million-dollar-script'),
            'completed_at' => $this->migration_time_label($run['completed_at'] ?? ''),
            'description' => __('The latest import finished. Running it again updates existing migrated records and keeps Million Dollar Script 2 tables intact.', 'million-dollar-script'),
            'imported_total' => $imported_total,
            'is_resumable' => !empty($job),
            'progress_percent' => $progress_percent,
            'processed_total' => $processed_total,
            'repaired' => absint($totals['repaired'] ?? count((array) ($report['repairs'] ?? []))),
            'manual_support' => false,
            'needs_attention' => false,
            'source_total' => $source_total,
            'skipped' => absint($totals['skipped'] ?? 0),
            'started_at' => $this->migration_time_label($run['started_at'] ?? ''),
            'stage_label' => $current_stage ? $this->migration_stage_label($current_stage) : __('Complete', 'million-dollar-script'),
            'status_class' => 'completed',
            'status_label' => __('Completed', 'million-dollar-script'),
            'title' => __('Latest import completed', 'million-dollar-script'),
            'warnings' => $warning_count,
        ];

        if ('running' === $status) {
            $summary['action_context'] = 'continue';
            $summary['action_label'] = __('Process next batch', 'million-dollar-script');
            $summary['description'] = __('Import is running in bounded batches. You can leave this page and continue later without duplicating migrated records.', 'million-dollar-script');
            $summary['needs_attention'] = true;
            $summary['status_class'] = 'running';
            $summary['status_label'] = __('Running', 'million-dollar-script');
            $summary['title'] = __('Import in progress', 'million-dollar-script');
        } elseif ('paused' === $status) {
            $summary['action_context'] = 'continue';
            $summary['action_label'] = __('Resume import', 'million-dollar-script');
            $summary['description'] = __('Import is paused. Resume continues from the last saved stage and cursor.', 'million-dollar-script');
            $summary['needs_attention'] = true;
            $summary['status_class'] = 'paused';
            $summary['status_label'] = __('Paused', 'million-dollar-script');
            $summary['title'] = __('Import paused', 'million-dollar-script');
        } elseif ('failed' === $status) {
            $summary['action_context'] = 'retry';
            $summary['action_label'] = __('Retry import', 'million-dollar-script');
            $summary['description'] = __('The last import stopped before completion. Retry import is non-destructive and uses the migration map to avoid duplicate Million Dollar Script records.', 'million-dollar-script');
            $summary['manual_support'] = true;
            $summary['needs_attention'] = true;
            $summary['status_class'] = 'failed';
            $summary['status_label'] = __('Failed', 'million-dollar-script');
            $summary['title'] = __('Import needs attention', 'million-dollar-script');
        } elseif ($warning_count > 0) {
            $summary['action_context'] = 'retry';
            $summary['action_label'] = __('Run import again', 'million-dollar-script');
            $summary['description'] = __('The last import completed with warnings. Review the warning list, then run the import again after correcting the source data or media references.', 'million-dollar-script');
            $summary['needs_attention'] = true;
            $summary['status_class'] = 'warning';
            $summary['status_label'] = __('Warnings', 'million-dollar-script');
            $summary['title'] = __('Import completed with warnings', 'million-dollar-script');
        } elseif ($status && 'completed' !== $status) {
            $summary['needs_attention'] = true;
            $summary['status_class'] = 'warning';
            $summary['status_label'] = ucfirst($status);
            $summary['title'] = __('Import status needs review', 'million-dollar-script');
        }

        return $summary;
    }

    private function migration_stage_status($run_status, $warning_count, $imported, $mapped_total) {
        $has_records = $imported > 0 || (null !== $mapped_total && $mapped_total > 0);

        if ('failed' === $run_status) {
            return $has_records ? __('Ready to retry', 'million-dollar-script') : __('Pending retry', 'million-dollar-script');
        }

        if ('running' === $run_status) {
            return $has_records ? __('In progress', 'million-dollar-script') : __('Pending', 'million-dollar-script');
        }

        if ('paused' === $run_status) {
            return $has_records ? __('Paused', 'million-dollar-script') : __('Pending resume', 'million-dollar-script');
        }

        if ($warning_count > 0) {
            return $has_records ? __('Imported, review', 'million-dollar-script') : __('Review warnings', 'million-dollar-script');
        }

        return $has_records ? __('Verified', 'million-dollar-script') : __('No rows', 'million-dollar-script');
    }

    private function migration_time_label($value) {
        $value = trim((string) $value);
        if (!$value) {
            return __('n/a', 'million-dollar-script');
        }

        $timestamp = strtotime($value . ' UTC');
        if (!$timestamp) {
            return $value;
        }

        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    private function migration_stage_label($stage) {
        $labels = [
            'settings' => __('Settings', 'million-dollar-script'),
            'grids' => __('Grids', 'million-dollar-script'),
            'packages' => __('Packages', 'million-dollar-script'),
            'price_rules' => __('Price rules', 'million-dollar-script'),
            'orders' => __('Orders', 'million-dollar-script'),
            'blocks' => __('Blocks', 'million-dollar-script'),
            'order_items' => __('Order items', 'million-dollar-script'),
            'placements' => __('Placements', 'million-dollar-script'),
            'pages' => __('Pages', 'million-dollar-script'),
        ];

        return $labels[$stage] ?? ucfirst(str_replace('_', ' ', (string) $stage));
    }
}
