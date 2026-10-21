<?php
/**
 * MDS2 upgrade notices and migration verification panels.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Migration\LegacySource;
use MillionDollarScript\V3\Migration\Recheck\Registry as RecheckRegistry;
use MillionDollarScript\V3\Migration\Recheck\Runner as RecheckRunner;
use MillionDollarScript\V3\Setup\LegacyPlugin;
use MillionDollarScript\V3\Support\Build;
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
                $count = absint($deactivated);
                $message = sprintf(
                    /* translators: %d: plugin count */
                    _n('%d Million Dollar Script 2 plugin was deactivated.', '%d Million Dollar Script 2 plugins were deactivated.', $count, 'million-dollar-script'),
                    $count
                );
                // The old copy appended "Million Dollar Script data was not
                // imported." to this sentence on every site, including sites that
                // had just finished an import - it read as though the imported
                // data had been thrown away (Vikunja #30 section 4, #31 item 1).
                $message .= ' ' . $this->deactivation_data_sentence();
                break;
            case 'deactivation_partial':
                $class = 'notice-warning';
                $message = sprintf(
                    /* translators: 1: deactivated plugins, 2: skipped plugins */
                    __('Million Dollar Script 2 deactivation was partial. Deactivated: %1$d. Skipped: %2$d.', 'million-dollar-script'),
                    $deactivated,
                    $skipped
                );
                $message .= ' ' . $this->deactivation_data_sentence();
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

    /**
     * What to tell the admin about their data after a plugin choice.
     *
     * Three different situations exist and the fixed sentence claimed the wrong
     * one for two of them. Pick the sentence from the recorded run state instead
     * (Vikunja #30 section 4).
     */
    private function deactivation_data_sentence() {
        $completed = $this->latest_completed_migration_import();
        if ($completed) {
            return sprintf(
                /* translators: %d: migration run id */
                __('Your imported data is unaffected: import run %1$d is kept in Million Dollar Script, and its verification report is on the Migration page.', 'million-dollar-script'),
                absint($completed['id'] ?? 0)
            );
        }

        $started = $this->latest_migration_run();
        if ($started) {
            return sprintf(
                /* translators: %d: migration run id */
                __('An import was started (run %1$d) but did not finish; deactivating Million Dollar Script 2 does not change that. Open the Migration page to continue the import, then deactivate once the verification report looks right.', 'million-dollar-script'),
                absint($started['id'] ?? 0)
            );
        }

        return __('No import has been run yet, so no Million Dollar Script 2 data was copied. Your Million Dollar Script 2 data is unchanged, and the dry run and import stay available on the Migration page.', 'million-dollar-script');
    }

    /**
     * The most recent completed import run, or null when none finished.
     *
     * The setup step, the wizard copy and the dashboard gate all need this one
     * fact. They previously inferred it from the keep/deactivate choice, which is
     * a different question (Vikunja #31 items 1 and 5).
     */
    private function latest_completed_migration_import() {
        global $wpdb;

        if (!DB::table_exists(DB::table('migration_runs'))) {
            return null;
        }

        $sql = 'SELECT * FROM ' . DB::ident(DB::table('migration_runs'))
            . " WHERE mode = 'import' AND status = 'completed' ORDER BY id DESC LIMIT 1";
        $row = $wpdb->get_row($sql, ARRAY_A);
        if (!is_array($row)) {
            return null;
        }

        $row['totals'] = json_decode((string) ($row['totals'] ?? ''), true) ?: [];
        $row['report'] = json_decode((string) ($row['report'] ?? ''), true) ?: [];

        return $row;
    }

    /**
     * The recorded state of a setup step, said in words.
     *
     * The step cards have carried a coloured edge since the first version, which
     * is the only way to tell a finished step from an unfinished one. A tester
     * reading the wizard could not use the colour, and asked for the state to be
     * written out (Vikunja #31 recommendation 1).
     */
    private function step_status(string $state): void
    {
        $labels = [
            'complete' => __('Completed', 'million-dollar-script'),
            'pending' => __('Pending', 'million-dollar-script'),
        ];
        // A state the plugin does not know reads as unfinished, never as done.
        $state = isset($labels[$state]) ? $state : 'pending';
        echo '<span class="mds3-setup-step-status is-' . esc_attr($state)
            . '" data-mds3-step-status="' . esc_attr($state) . '">'
            . esc_html($labels[$state])
            . '</span>';
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
        // A finished import is what this step exists to lead to, so it completes
        // the step on its own. The importer already records a 'migrated' choice;
        // checking the run too covers imports completed before that record existed
        // and covers the case where the old plugin is later reactivated
        // (Vikunja #31 item 1).
        $import_run = $this->latest_completed_migration_import();
        $is_complete = (!$active && !$has_data) || (bool) $import_run || in_array($choice, ['keep_active', 'deactivated', 'migrated', 'migrated_deactivated'], true);
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
            'import_summary' => $import_run ? $this->migration_run_summary($import_run) : null,
            'import_run' => $import_run,
            'is_complete' => $is_complete,
            'legacy_source' => $legacy_source,
            'plugin_rows' => $plugin_rows,
        ], $this);
    }

    private function has_mds2_upgrade_context(array $legacy_plugins, array $legacy_source) {
        if (!empty($_GET['mds2_action']) || !empty($_GET['mds2_error'])) {
            return true;
        }

        return LegacyPlugin::has_legacy_context((string) ($legacy_source['source_prefix'] ?? ''));
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
        // A run left in a running state must not start working again just because
        // someone opened the page. It continues when the admin asks it to, which is
        // the request that came from start, continue or retry (Vikunja #31 item 3,
        // and Ryan's decision: an interrupted run was interrupted for a reason).
        // A re-check the admin just pressed belongs to the same moment: the page that
        // started it is the one carrying it through its batches.
        $clicked_through = false;
        foreach (['started', 'continued', 'retried', 'recheck_started'] as $from_action) {
            if (!empty($_GET[$from_action])) {
                $clicked_through = true;
                break;
            }
        }
        // Only this render is actually driving batches, and only while the recorded
        // status really is running. Everything else is a stopped run waiting.
        $is_working = $clicked_through && 'running' === sanitize_key((string) ($run['status'] ?? ''));

        Template::display('admin/partials/migration-recovery-panel.php', [
            'auto_run' => $is_working,
            'grid_enabled' => (bool) $grid_enabled,
            'run' => $run,
            'summary' => $this->migration_run_summary($run, $is_working),
        ], $this);
    }

    /**
     * What the migrated orders arrived as, most common first.
     *
     * The older plugin and this one do not describe an order the same way, so the report
     * says what this site now holds instead of leaving an owner to compare two status lists.
     */
    private function migration_order_status_rows(array $order_status_outcomes) {
        $counts = is_array($order_status_outcomes['counts'] ?? null) ? $order_status_outcomes['counts'] : [];
        $labels = $this->order_status_labels();
        $rows = [];
        foreach ($counts as $status => $count) {
            $status = sanitize_key((string) $status);
            $count = absint($count);
            if ('' === $status || $count < 1) {
                continue;
            }
            $rows[] = [
                'label' => isset($labels[$status]) ? $labels[$status] : ucfirst($status),
                'count' => $count,
            ];
        }
        usort($rows, static function ($a, $b) {
            return $b['count'] !== $a['count'] ? $b['count'] - $a['count'] : strcmp((string) $a['label'], (string) $b['label']);
        });

        return $rows;
    }

    /**
     * Why some migrated orders arrived owing money, when that happened.
     *
     * "Confirmed" in the older plugin is the state a customer's confirmation produces before
     * payment, so an ad left there was never paid for. Saying so in the report keeps the
     * money honest and tells the site owner what to do with those orders.
     */
    private function migration_order_status_note(array $order_status_outcomes) {
        $confirmed = absint($order_status_outcomes['confirmed_without_payment'] ?? 0);
        if ($confirmed < 1) {
            return '';
        }

        return sprintf(
            _n(
                '%s order was left at the confirmation step by the site that was migrated: the advertiser had confirmed the ad, but no payment was recorded for it there. It arrives awaiting payment, so the customer can still complete it. If it was settled outside the older plugin, mark it paid under Orders.',
                '%s orders were left at the confirmation step by the site that was migrated: the advertisers had confirmed their ads, but no payment was recorded for them there. They arrive awaiting payment, so customers can still complete them. If one was settled outside the older plugin, mark it paid under Orders.',
                $confirmed,
                'million-dollar-script'
            ),
            number_format_i18n($confirmed)
        );
    }

    private function migration_verification_report(array $run) {
        $report = is_array($run['report'] ?? null) ? $run['report'] : [];
        $totals = is_array($run['totals'] ?? null) ? $run['totals'] : [];
        $mapped = is_array($report['mapped'] ?? null) ? $report['mapped'] : [];
        $warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
        $skipped = is_array($report['skipped'] ?? null) ? $report['skipped'] : [];
        $repairs = is_array($report['repairs'] ?? null) ? $report['repairs'] : [];
        $page_outcomes = is_array($report['page_outcomes'] ?? null) ? $report['page_outcomes'] : [];
        $settings_outcomes = is_array($report['settings_outcomes'] ?? null) ? $report['settings_outcomes'] : [];
        $order_status_outcomes = is_array($report['order_status_outcomes'] ?? null) ? $report['order_status_outcomes'] : [];
        $order_status_rows = $this->migration_order_status_rows($order_status_outcomes);
        $order_status_note = $this->migration_order_status_note($order_status_outcomes);
        $coverage = is_array($report['coverage'] ?? null) ? $report['coverage'] : [];
        $database_rejections = is_array($report['database_rejections'] ?? null) ? $report['database_rejections'] : [];
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
        $missing = [
            'orders' => absint($coverage['orders']['unexplained'] ?? 0),
            'order_items' => absint($coverage['orders']['unexplained'] ?? 0),
            'placements' => absint($coverage['orders']['unexplained'] ?? 0) + absint($coverage['images']['short'] ?? 0),
        ];

        foreach ($entities as $key => $label) {
            $mapped_number = array_key_exists($key, $mapped) ? absint($mapped[$key]) : null;
            $imported = absint($totals[$key] ?? 0);
            $status = $this->migration_stage_status($run_status, $warning_count, $imported, $mapped_number);
            if (!empty($missing[$key]) && !empty($coverage)) {
                // A stage can be verified as written and still leave the site
                // short: every row may have imported while some orders never
                // reached the grid. "Verified" alone would be true and useless.
                $status = sprintf(
                    /* translators: 1: status label, 2: number of records without their counterpart. */
                    __('%1$s - %2$d missing', 'million-dollar-script'),
                    $status,
                    (int) $missing[$key]
                );
            }
            $rows[] = [
                'imported' => $imported,
                'label' => $label,
                'mapped_total' => null === $mapped_number ? __('n/a', 'million-dollar-script') : (string) $mapped_number,
                'status' => $status,
            ];
        }

        Template::display('admin/partials/migration-verification-report.php', [
            'coverage' => $coverage,
            'coverage_note' => '' !== $coverage ? $this->migration_coverage_note($coverage) : '',
            'database_rejections' => $database_rejections,
            'page_outcomes' => $page_outcomes,
            'repairs' => $repairs,
            'rows' => $rows,
            'run' => $run,
            'settings_outcomes' => $settings_outcomes,
            'order_status_rows' => $order_status_rows,
            'order_status_note' => $order_status_note,
            'skipped' => $skipped,
        ], $this);
    }

    /**
     * Plain-language summary of what the coverage checks found short.
     *
     * It is shown next to the deactivate control as well as in the report, so a
     * site owner sees the count before switching Million Dollar Script 2 off and
     * not only after the old tables are gone.
     */
    private function migration_coverage_note(array $coverage): string {
        $parts = [];
        $orders_short = absint($coverage['orders']['unexplained'] ?? 0);
        if ($orders_short > 0) {
            $parts[] = sprintf(
                /* translators: 1: orders without a placement and without an explanation, 2: orders in the older data. */
                _n(
                    '%1$d of %2$d orders has no place on the new grid, and the report does not say why.',
                    '%1$d of %2$d orders have no place on the new grid, and the report does not say why.',
                    $orders_short,
                    'million-dollar-script'
                ),
                number_format_i18n($orders_short),
                number_format_i18n(absint($coverage['orders']['source'] ?? 0))
            );
        }

        $images_short = absint($coverage['images']['short'] ?? 0);
        if ($images_short > 0) {
            $parts[] = sprintf(
                /* translators: 1: migrated ads that had an image in the older data, 2: those showing an image now. */
                _n(
                    '%1$d ad that had an image in the older data shows none, while %2$d show one.',
                    '%1$d ads that had an image in the older data show none, while %2$d show one.',
                    $images_short,
                    'million-dollar-script'
                ),
                number_format_i18n(absint($coverage['images']['expected'] ?? 0) - absint($coverage['images']['attached'] ?? 0)),
                number_format_i18n(absint($coverage['images']['attached'] ?? 0))
            );
        }

        $canvas_out = absint($coverage['canvas']['outside'] ?? 0);
        if ($canvas_out > 0) {
            $parts[] = sprintf(
                /* translators: 1: placements outside the grid canvas, 2: placements checked. */
                _n(
                    '%1$d of %2$d placement falls outside the grid picture and cannot be seen on the site.',
                    '%1$d of %2$d placements fall outside the grid picture and cannot be seen on the site.',
                    $canvas_out,
                    'million-dollar-script'
                ),
                number_format_i18n($canvas_out),
                number_format_i18n(absint($coverage['canvas']['placements'] ?? 0))
            );
        }

        return implode(' ', $parts);
    }

    private function migration_run_summary(array $run, bool $is_working = false) {
        $totals = is_array($run['totals'] ?? null) ? $run['totals'] : [];
        $report = is_array($run['report'] ?? null) ? $run['report'] : [];
        $warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
        // A notice explains the run; a warning names records that need attention. The panel
        // treats them differently, and only warnings change what it asks the admin to do.
        $notices = is_array($report['notices'] ?? null) ? $report['notices'] : [];
        $job = is_array($report['job'] ?? null) ? $report['job'] : [];
        $status = sanitize_key((string) ($run['status'] ?? ''));
        $warning_count = max(count($warnings), absint($totals['warnings'] ?? 0));
        $imported_total = 0;
        $recorded_by = trim((string) ($run['plugin_version'] ?? ''));
        $installed_version = Build::version();
        // Which build wrote this record, said once and in a sentence a site owner can act
        // on. The two screens that show it used to word it differently, and one of them put
        // it inside a box built for numbers, where "Made by 3.0.0-alpha.5" read like a
        // plugin credit instead of an explanation. Both now print this.
        $older_build_note = '';
        if ('' !== $recorded_by && 'unknown' !== $recorded_by && '' !== $installed_version && 'unknown' !== $installed_version && $recorded_by !== $installed_version) {
            $older_build_note = sprintf(
                /* translators: 1: version that ran the import, 2: installed version */
                __('This import was run by %1$s; the installed version is %2$s. Running the import again re-reads the old site and updates what that older run left behind, including images it could not find and text it had to shorten.', 'million-dollar-script'),
                $recorded_by,
                $installed_version
            );
        }
        $source_total = 0;
        $processed_total = 0;
        $known_source_stages = 0;
        $completed_step_count = 0;
        $stages = ['settings', 'grids', 'packages', 'price_rules', 'orders', 'blocks', 'order_items', 'placements', 'pages'];
        $source_totals = is_array($job['source_totals'] ?? null) ? $job['source_totals'] : [];
        $processed = is_array($job['processed'] ?? null) ? $job['processed'] : [];
        $completed_stages = array_map('strval', is_array($job['completed_stages'] ?? null) ? $job['completed_stages'] : []);
        foreach ($stages as $stage) {
            $recorded_count = $source_totals[$stage] ?? null;
            if (null !== $recorded_count) {
                $source_total += absint($recorded_count);
                $known_source_stages++;
            }
            $processed_total += min(absint($recorded_count ?? 0), absint($processed[$stage] ?? 0));
            if (in_array($stage, $completed_stages, true)) {
                $completed_step_count++;
            }
        }
        // How far along to show. With record counts for every step, records are the honest
        // measure. Without them - a checkpoint left by an older build may not have recorded
        // all of them - the steps are the only thing that is not a guess.
        $source_counts_known = $known_source_stages >= count($stages);
        if ('completed' === $status) {
            $progress_percent = 100;
        } elseif ($source_counts_known && $source_total > 0) {
            $progress_percent = (int) floor(($processed_total / $source_total) * 100);
        } else {
            $progress_percent = (int) floor(($completed_step_count / max(1, count($stages))) * 100);
        }
        $progress_percent = min(100, max(0, $progress_percent));
        $handled = 0;
        foreach ($stages as $stage) {
            $handled += absint($processed[$stage] ?? 0);
        }
        $remaining = max(0, $source_total - $handled);
        $stage_index = absint($job['stage_index'] ?? 0);
        $current_stage = $stages[$stage_index] ?? '';

        foreach (['settings', 'grids', 'packages', 'price_rules', 'orders', 'blocks', 'order_items', 'placements', 'pages'] as $key) {
            $imported_total += absint($totals[$key] ?? 0);
        }

        $summary = [
            'action_context' => 'import',
            'action_label' => __('Run import again', 'million-dollar-script'),
            'completed_at' => $this->migration_time_label($run['completed_at'] ?? ''),
            'coverage_note' => '',
            'coverage_short' => 0,
            'description' => __('The latest import finished. Running it again updates existing migrated records and keeps Million Dollar Script 2 tables intact.', 'million-dollar-script'),
            'imported_total' => $imported_total,
            'is_resumable' => !empty($job),
            'progress_percent' => $progress_percent,
            'progress_text' => $this->migration_progress_text($status, $is_working, $current_stage, $handled, $source_total, $source_counts_known),
            // Which build wrote this record, and which build is reading it. A site can
            // hold a run made by an earlier alpha - the customer site does - and the
            // screens should say so rather than let an admin infer it.
            'plugin_version' => $installed_version,
            'recorded_by' => $recorded_by,
            'older_build_note' => $older_build_note,
            'processed_total' => $processed_total,
            'repaired' => absint($totals['repaired'] ?? count((array) ($report['repairs'] ?? []))),
            'manual_support' => false,
            'needs_attention' => false,
            'notices' => array_values(array_map('strval', $notices)),
            'notice_count' => count($notices),
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
            $summary['needs_attention'] = true;
            $summary['status_class'] = 'running';
            $summary['title'] = __('Import in progress', 'million-dollar-script');
            if ($is_working) {
                // This render came from the admin asking for the next batch, so the
                // browser really is processing right now.
                $summary['description'] = __('Processing batches now. If you leave this page the import stops where it is and nothing resumes by itself.', 'million-dollar-script');
                $summary['status_label'] = __('Running', 'million-dollar-script');
            } else {
                // A run left in a running state is not evidence that anything is
                // running. Saying so made the screen look like it had started an
                // import on its own (Vikunja #31 item 3).
                $summary['description'] = __('This import stopped before finishing. Nothing runs until you choose Process next batch, which carries on from the last saved stage and cursor without duplicating migrated records.', 'million-dollar-script');
                $summary['status_label'] = __('Stopped, waiting to continue', 'million-dollar-script');
            }
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

        $summary['coverage'] = is_array($report['coverage'] ?? null) ? $report['coverage'] : [];
        $summary['coverage_short'] = self::migration_coverage_shortfall($summary['coverage']);
        if ($summary['coverage_short'] > 0) {
            $summary['coverage_note'] = $this->migration_coverage_note($summary['coverage']);
        }

        if ('completed' === $status && $summary['coverage_short'] > 0 && 0 === $warning_count) {
            // Nothing failed and nothing was rejected, yet parts of the old site
            // have no counterpart here. Saying "completed" beside a Deactivate
            // button would send the owner off with the old tables and a smaller
            // grid, so this state asks for a decision instead.
            $summary['needs_attention'] = true;
            $summary['status_class'] = 'warning';
            $summary['status_label'] = __('Review coverage', 'million-dollar-script');
            $summary['title'] = __('Import completed, records did not carry over', 'million-dollar-script');
            $summary['description'] = $summary['coverage_note'] . ' ' . __('Review the report below, then correct the old data and run the import again, or keep Million Dollar Script 2 active until the missing records are dealt with.', 'million-dollar-script');
        }

        return $summary;
    }

    /**
     * Records a completed import left without their counterpart.
     */
    private static function migration_coverage_shortfall(array $coverage): int {
        return absint($coverage['orders']['unexplained'] ?? 0)
            + absint($coverage['images']['short'] ?? 0)
            + absint($coverage['canvas']['outside'] ?? 0);
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

    /**
     * The standing rules that would bring a migrated source up to date, and what has already
     * been read again for it.
     *
     * Offered only for a source this site has actually imported from: an empty prefix, or one
     * typed into the form and never used, has no records here to bring up to date.
     */
    public function migration_rechecks_panel($source_prefix) {
        global $wpdb;

        $source_prefix = strtolower(trim((string) $source_prefix));
        if ('' === $source_prefix) {
            return;
        }

        $imported = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . DB::ident(DB::table('migration_runs')) . " WHERE source_prefix = %s AND mode = 'import' AND status = 'completed' LIMIT 1",
            $source_prefix
        ));
        if (!$imported) {
            return;
        }

        $applied = RecheckRegistry::appliedFor($source_prefix);
        // The older tables may have gone since the import - a restore, or the site was tidied
        // up afterwards. A rule that reads from them again cannot run then, and saying so is
        // kinder than a button that fails.
        $legacy = new LegacySource($source_prefix);
        $source_present = DB::table_exists($legacy->table('orders')) && DB::table_exists($legacy->table('blocks'));
        $items = [];

        foreach (RecheckRegistry::all() as $recheck) {
            $done = is_array($applied[$recheck->id()] ?? null) ? $applied[$recheck->id()] : null;
            $running = (array) RecheckRunner::running($source_prefix, $recheck->id());
            $when = trim((string) ($done['at'] ?? ''));

            $items[] = [
                'id' => $recheck->id(),
                'source_prefix' => $source_prefix,
                'title' => $recheck->title(),
                'summary' => $recheck->summary(),
                'benefit' => $recheck->benefit(),
                'applied_at' => '' === $when ? '' : $this->migration_time_label($when),
                'records_read' => absint($done['result']['records_read'] ?? 0),
                'running' => (bool) $running,
                'can_run' => !$running && $source_present,
                'unavailable' => $source_present ? '' : __('The older site records are no longer on this site, so they cannot be read again.', 'million-dollar-script'),
            ];
        }

        Template::display('admin/partials/migration-rechecks.php', ['recheck_items' => $items]);
    }

    /**
     * The line under the progress bar, in the units the site owner is watching.
     *
     * It used to count internal work units, so a finished nine-step import that had read
     * hundreds of records could say "Processed 2 of 2 migration work items" two rows under
     * "Imported 372". Steps and records are what the screen is about, and a run that
     * stopped says where it stopped - that is what the continue control depends on.
     */
    private function migration_progress_text($status, $is_working, $current_stage, $handled, $source_total, $source_counts_known) {
        $handled = absint($handled);
        $source_total = absint($source_total);
        $remaining = $source_counts_known ? max(0, $source_total - $handled) : 0;

        if ('completed' === $status) {
            if ($handled < 1) {
                return __('Every step finished.', 'million-dollar-script');
            }

            return sprintf(
                /* translators: %s: number of records read from the older site */
                __('Every step finished. %s records were read from the older site.', 'million-dollar-script'),
                number_format_i18n($handled)
            );
        }

        if (!$current_stage) {
            return '';
        }

        $step = $this->migration_stage_label($current_stage);
        $stopped = 'paused' === $status || ('running' === $status && empty($is_working));
        $text = sprintf(
            /* translators: 1: name of the step, 2: number of records read so far */
            $stopped
                ? __('Stopped at: %1$s. %2$s records read so far.', 'million-dollar-script')
                : __('Working on: %1$s. %2$s records read so far.', 'million-dollar-script'),
            $step,
            number_format_i18n($handled)
        );

        if ($remaining > 0) {
            $text .= ' ' . sprintf(
                /* translators: %s: number of records still to read */
                __('%s records in the older site still to read.', 'million-dollar-script'),
                number_format_i18n($remaining)
            );
        }

        return $text;
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
