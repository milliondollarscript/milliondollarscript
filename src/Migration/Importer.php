<?php
/**
 * Conservative MDS2 importer.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration;

use MillionDollarScript\V3\Extensions\ExtensionSetup;
use MillionDollarScript\V3\Migration\Concerns\ResolvesLegacyAdMetadata;
use MillionDollarScript\V3\Migration\Concerns\ImportsLegacyOrdersAndBlocks;
use MillionDollarScript\V3\Migration\Concerns\ImportsLegacySettingsAndInventory;
use MillionDollarScript\V3\Migration\Concerns\ReportsMigrationCoverage;
use MillionDollarScript\V3\Migration\Concerns\ResolvesLegacyMedia;
use MillionDollarScript\V3\Migration\Concerns\TracksMigrationImportState;
use MillionDollarScript\V3\Migration\Concerns\ReadsLegacyOrderPayments;
use MillionDollarScript\V3\Migration\Concerns\RecordsMigrationReconciliation;
use MillionDollarScript\V3\Setup\Installer;

if (!defined('ABSPATH')) {
    exit;
}

final class Importer {
    use ImportsLegacySettingsAndInventory;
    use ImportsLegacyOrdersAndBlocks;
    use ReadsLegacyOrderPayments;
    use ResolvesLegacyAdMetadata;
    use ResolvesLegacyMedia;
    use ReportsMigrationCoverage;
    use TracksMigrationImportState;
    use RecordsMigrationReconciliation;

    private LegacySource $source;
    private MigrationMap $map;
    private array $legacy_grids = [];
    private array $warnings = [];
    /**
     * What this run did that needs no action. Kept apart from warnings on purpose: a
     * warning is a record that needs attention, a notice explains the run. Counting them
     * together made a finished import look like it had a problem.
     */
    private array $notices = [];
    private array $migration_skips = [];
    private array $migration_repairs = [];
    private array $page_options = [];
    private array $grid_page_disposition = [];
    private array $page_outcomes = [];
    private array $settings_outcomes = [];
    /** MDS3 order states the migrated orders arrived in, counted. */
    private array $order_status_outcomes = [];
    /** Legacy orders whose ad picture could only be recovered at cell size. */
    private array $cell_size_copies = [];
    /**
     * Legacy blocks whose stored position sits off their grid while their own block number
     * names a position on it, grouped by grid and order so the report says once what happened.
     */
    private array $undrawn_positions = [];

    /**
     * Post meta keys Million Dollar Script 2 has used to point an ad record at its
     * image. One list, because the importer follows them in order and the coverage
     * check has to count the same places when it asks whether an image existed.
     */
    public const IMAGE_META_KEYS = [
        '_milliondollarscript_image',
        'milliondollarscript_image',
        '_mds_image',
        'mds_image',
        '_thumbnail_id',
    ];

    private const JOB_STAGES = [
        'settings',
        'grids',
        'packages',
        'price_rules',
        'orders',
        'blocks',
        'order_items',
        'placements',
        'pages',
    ];

    /**
     * Format version of the resumable job blob kept in a run report.
     *
     * A paused import stores which stage and which row it had reached, and both are
     * positions in JOB_STAGES. If that list is ever reordered, renamed, merged or
     * narrowed, a checkpoint saved by an older build means something different under the
     * new one and could silently skip or repeat a stage. Bump this constant in the same
     * change that alters JOB_STAGES, or the meaning of a cursor: a checkpoint written in
     * another format then restarts from the first stage instead of being re-read, and the
     * report says so. Re-processing records that are already imported is safe because each
     * write is keyed by the migration map.
     *
     * @see self::normalize_job_state()
     * @see tests/rewrite/migration-cross-version-resume-fixture.php
     */
    private const JOB_FORMAT_VERSION = 1;

    /**
     * The steps an import works through, in order.
     *
     * A standing rule names the steps it needs re-read (Recheck::stages()); this is the
     * list those names are checked against.
     *
     * @return list<string>
     */
    public static function import_stages() {
        return self::JOB_STAGES;
    }

    /**
     * Set when a saved checkpoint turned out to be written in another job format, so the
     * run can say so once. @see self::normalize_job_state()
     */
    private $job_format_restarted = false;

    /**
     * Per-page upgrade choices; used by the one-shot import(). The resumable
     * path persists the same choices in the job state.
     *
     * @param array $options upgrade_in_place => list of legacy page IDs to
     * overwrite in place. Modified pages without an entry keep their content
     * and get a fresh MDS3 page instead (reported in the page outcomes).
     */
    public function set_page_options(array $options) {
        $this->page_options = self::normalize_page_options($options);

        return $this;
    }

    private static function normalize_page_options(array $args) {
        $upgrade_in_place = [];
        foreach (array_map('absint', (array) ($args['upgrade_in_place'] ?? [])) as $post_id) {
            if ($post_id > 0) {
                $upgrade_in_place[$post_id] = true;
            }
        }

        return ['upgrade_in_place' => $upgrade_in_place];
    }

    /**
     * Import supported MDS2 records without mutating source tables.
     *
     * @param string $source_prefix Source table prefix.
     * @return array|\WP_Error
     */
    public function import($source_prefix = '') {
        Installer::create_tables();
        (new ExtensionSetup())->ensure_selected('mds-grid');

        $this->initialize_source($source_prefix);
        $dry_run = (new DryRun())->report($this->source->source_prefix());
        $run_id = $this->create_run($this->source->source_prefix(), 'import', 'running', $dry_run);

        $totals = $this->empty_totals();

        try {
            $totals['settings'] = $this->import_settings();
            $totals['grids'] = $this->import_grids();
            $totals['packages'] = $this->import_packages();
            $totals['price_rules'] = $this->import_price_rules();
            $totals['orders'] = $this->import_orders();
            $totals['blocks'] = $this->import_blocks();
            $totals['order_items'] = $this->import_order_items();
            $totals['placements'] = $this->import_placements();
            $totals['pages'] = $this->import_pages();
            $this->record_database_rejection_warning();
            $totals['skipped'] = count($this->migration_skips);
            $totals['repaired'] = count($this->migration_repairs);
            $totals['warnings'] = count($this->warnings);

            $verification = $this->verification_report($totals);
            $verification['orphan_grid_pages_created'] = $this->reconcile_grid_pages();
            $this->finish_run($run_id, 'completed', $totals, $verification);

            return [
                'run_id' => $run_id,
                'source_prefix' => $this->source->source_prefix(),
                'imported' => $totals,
                'verification' => $verification,
                'will_drop_mds2_tables' => false,
            ];
        } catch (\Throwable $throwable) {
            $this->record_database_rejection_warning();
            $totals['skipped'] = count($this->migration_skips);
            $totals['repaired'] = count($this->migration_repairs);
            $totals['warnings'] = count($this->warnings);
            $this->warnings[] = $throwable->getMessage();
            $verification = $this->verification_report($totals);
            $this->finish_run($run_id, 'failed', $totals, $verification);

            return new \WP_Error('mds3_migration_failed', $throwable->getMessage(), ['run_id' => $run_id]);
        }
    }

    public function start_resumable($source_prefix = '', array $args = []) {
        Installer::create_tables();
        (new ExtensionSetup())->ensure_selected('mds-grid');
        $this->initialize_source($source_prefix);

        $dry_run = (new DryRun())->report($this->source->source_prefix());
        $totals = $this->empty_totals();
        $report = $dry_run;
        $report['warnings'] = [];
        $report['job'] = $this->new_job_state($dry_run, $args);

        $run_id = $this->create_run($this->source->source_prefix(), 'import', 'running', $report);
        $this->update_run($run_id, 'running', $totals, $report);

        return $this->resumable_status($run_id);
    }

    public function run_resumable_step($run_id, array $args = []) {
        $run = $this->load_run($run_id);
        if (!$run || 'import' !== (string) ($run['mode'] ?? '')) {
            return new \WP_Error('mds3_migration_job_missing', __('Migration job was not found.', 'million-dollar-script'));
        }

        if ('completed' === (string) ($run['status'] ?? '')) {
            return $this->job_response($run);
        }

        if ('paused' === (string) ($run['status'] ?? '') && empty($args['resume'])) {
            return $this->job_response($run);
        }

        $this->initialize_source($run['source_prefix'] ?? '');
        $totals = $this->normalize_totals(is_array($run['totals'] ?? null) ? $run['totals'] : []);
        $report = is_array($run['report'] ?? null) ? $run['report'] : [];
        $job = $this->normalize_job_state($report['job'] ?? [], $report);
        $this->warnings = is_array($report['warnings'] ?? null) ? array_values(array_unique($report['warnings'])) : [];
        $this->notices = is_array($report['notices'] ?? null) ? array_values(array_unique($report['notices'])) : [];
        $this->migration_skips = $this->reconciliation_entries($report['skipped'] ?? []);
        $this->migration_repairs = $this->reconciliation_entries($report['repairs'] ?? []);
        $this->database_rejections = self::normalize_database_rejections($job['database_rejections'] ?? []);
        $this->page_options = self::normalize_page_options((array) ($job['page_options'] ?? []));
        $this->grid_page_disposition = (array) ($job['grid_page_disposition'] ?? []);
        $this->page_outcomes = (array) ($job['page_outcomes'] ?? []);
        $this->settings_outcomes = (array) ($job['settings_outcomes'] ?? []);
        $this->order_status_outcomes = (array) ($job['order_status_outcomes'] ?? []);

        if ($this->job_format_restarted) {
            // An import that starts again looks like it is losing its place, so the run
            // has to say why. This explains what happened; it is not a record needing a fix.
            $this->migration_notice(__('The saved progress of this import was written in another format by a different version of Million Dollar Script 3.0, so this import started again from its first step. Nothing that was already imported is created a second time.', 'million-dollar-script'));
        }

        $batch_size = max(1, min(500, absint($args['batch_size'] ?? $job['batch_size'] ?? 100)));
        $time_budget = max(1, min(20, (float) ($args['time_budget'] ?? $job['time_budget'] ?? 8)));
        $deadline = microtime(true) + $time_budget;
        $processed = 0;

        try {
            while ($job['stage_index'] < count(self::JOB_STAGES) && $processed < $batch_size && microtime(true) < $deadline) {
                $stage = self::JOB_STAGES[$job['stage_index']];
                $remaining = max(1, $batch_size - $processed);
                $result = $this->process_job_stage($stage, $job, $remaining);

                $processed += absint($result['processed'] ?? 0);
                $totals[$stage] = absint($totals[$stage] ?? 0) + absint($result['imported'] ?? 0);
                $job['processed'][$stage] = absint($job['processed'][$stage] ?? 0) + absint($result['processed'] ?? 0);
                $job['cursor'] = is_array($result['cursor'] ?? null) ? $result['cursor'] : [];
                $job['last_message'] = $this->stage_label($stage);

                if ('settings' === $stage) {
                    $job['settings_outcomes'] = array_values($this->settings_outcomes);
                }

                if ('orders' === $stage) {
                    $job['order_status_outcomes'] = $this->order_status_outcomes;
                }

                if ('pages' === $stage) {
                    $job['grid_page_disposition'] = $this->grid_page_disposition;
                    $job['page_outcomes'] = array_values($this->page_outcomes);
                    if (!empty($result['orphan_grid_pages_created'])) {
                        $job['orphan_grid_pages_created'] = (int) $result['orphan_grid_pages_created'];
                    }
                }

                if (!empty($result['done'])) {
                    $job['completed_stages'] = array_values(array_unique(array_merge($job['completed_stages'], [$stage])));
                    $job['stage_index']++;
                    $job['cursor'] = [];
                } elseif (absint($result['processed'] ?? 0) < 1) {
                    break;
                }
            }

            $completed = $job['stage_index'] >= count(self::JOB_STAGES);
            $status = $completed ? 'completed' : 'running';
            $job['status'] = $status;
            $job['updated_at'] = current_time('mysql', true);
            if ($completed) {
                $this->record_database_rejection_warning();
            }
            $job['database_rejections'] = $this->database_rejections;
            if ($completed && '' !== (string) ($job['recheck'] ?? '')) {
                // A standing rule counts as applied only for a run that reached the end. A
                // batch that stopped at the host leaves it offered again.
                \MillionDollarScript\V3\Migration\Recheck\Runner::recordCompletion(
                    $this->source->source_prefix(),
                    $job,
                    $totals,
                    absint($run['id'] ?? 0)
                );
            }
            $totals['warnings'] = count(array_values(array_unique($this->warnings)));
            $totals['skipped'] = count($this->migration_skips);
            $totals['repaired'] = count($this->migration_repairs);
            $report = $this->verification_report($totals);
            $report['job'] = $job;
            $this->update_run($run['id'], $status, $totals, $report, $completed);

            return $this->resumable_status($run['id']);
        } catch (\Throwable $throwable) {
            $this->warnings[] = $throwable->getMessage();
            $job['status'] = 'failed';
            $job['last_error'] = $throwable->getMessage();
            $job['updated_at'] = current_time('mysql', true);
            $this->record_database_rejection_warning();
            $job['database_rejections'] = $this->database_rejections;
            $totals['warnings'] = count(array_values(array_unique($this->warnings)));
            $totals['skipped'] = count($this->migration_skips);
            $totals['repaired'] = count($this->migration_repairs);
            $report = $this->verification_report($totals);
            $report['job'] = $job;
            $this->update_run($run['id'], 'failed', $totals, $report, true);

            return new \WP_Error('mds3_migration_job_failed', $throwable->getMessage(), ['run_id' => absint($run['id'])]);
        }
    }

    public function pause_resumable($run_id) {
        $run = $this->load_run($run_id);
        if (!$run || 'import' !== (string) ($run['mode'] ?? '')) {
            return new \WP_Error('mds3_migration_job_missing', __('Migration job was not found.', 'million-dollar-script'));
        }

        if (in_array((string) ($run['status'] ?? ''), ['completed', 'failed'], true)) {
            return $this->job_response($run);
        }

        $report = is_array($run['report'] ?? null) ? $run['report'] : [];
        $job = $this->normalize_job_state($report['job'] ?? [], $report);
        $job['status'] = 'paused';
        $job['updated_at'] = current_time('mysql', true);
        $report['job'] = $job;
        $this->update_run($run['id'], 'paused', $this->normalize_totals($run['totals'] ?? []), $report);

        return $this->resumable_status($run['id']);
    }

    public function resumable_status($run_id) {
        $run = $this->load_run($run_id);
        if (!$run) {
            return new \WP_Error('mds3_migration_job_missing', __('Migration job was not found.', 'million-dollar-script'));
        }

        return $this->job_response($run);
    }

    private function initialize_source($source_prefix) {
        $this->source = new LegacySource($source_prefix);
        $this->map = new MigrationMap();
        $this->legacy_grids = [];
        $this->warnings = [];
        $this->notices = [];
        $this->migration_skips = [];
        $this->migration_repairs = [];
        $this->grid_page_disposition = [];
        $this->page_outcomes = [];
        $this->settings_outcomes = [];
        $this->order_status_outcomes = [];
        $this->cell_size_copies = [];
        $this->undrawn_positions = [];
        $this->database_rejections = [];
    }

    /**
     * Keep the tallies of values the database could not store across the steps of a
     * resumable run. A step boundary must not lose the count, or a large site reports
     * nothing for a truncated write that happened two requests ago.
     */
    private static function normalize_database_rejections($raw): array {
        $normalized = [];
        foreach (is_array($raw) ? $raw : [] as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $table = sanitize_text_field((string) ($entry['table'] ?? ''));
            if ('' === $table) {
                continue;
            }
            $column = sanitize_text_field((string) ($entry['column'] ?? ''));
            $normalized[$table . '.' . ('' !== $column ? $column : '?')] = [
                'table' => $table,
                'column' => $column,
                'kind' => sanitize_text_field((string) ($entry['kind'] ?? '')),
                'count' => absint($entry['count'] ?? 0),
            ];
        }

        return $normalized;
    }

    private function empty_totals() {
        return [
            'settings' => 0,
            'grids' => 0,
            'packages' => 0,
            'price_rules' => 0,
            'orders' => 0,
            'blocks' => 0,
            'order_items' => 0,
            'placements' => 0,
            'pages' => 0,
            'skipped' => 0,
            'repaired' => 0,
            'warnings' => 0,
        ];
    }

    private function normalize_totals(array $totals) {
        $normalized = $this->empty_totals();
        foreach ($normalized as $key => $default) {
            $normalized[$key] = absint($totals[$key] ?? $default);
        }

        return $normalized;
    }

    private function new_job_state(array $dry_run, array $args = []) {
        // A re-check does not re-read the whole older site: it starts at the first step its
        // rule touches. Anything earlier is already on the site and unchanged by the rule.
        $stage_index = 0;
        $start_stage = sanitize_key((string) ($args['start_stage'] ?? ''));
        if ('' !== $start_stage) {
            $position = array_search($start_stage, self::JOB_STAGES, true);
            if (false !== $position) {
                $stage_index = $position;
            }
        }

        return [
            'version' => self::JOB_FORMAT_VERSION,
            'status' => 'running',
            'stage_index' => $stage_index,
            'cursor' => [],
            'processed' => array_fill_keys(self::JOB_STAGES, 0),
            'completed_stages' => [],
            'source_totals' => $this->job_source_totals($dry_run),
            'batch_size' => max(1, min(500, absint($args['batch_size'] ?? 100))),
            'page_options' => self::normalize_page_options($args),
            'grid_page_disposition' => [],
            'page_outcomes' => [],
            'settings_outcomes' => [],
            'order_status_outcomes' => [],
            'database_rejections' => [],
            // Which standing rule this run is applying, and the steps it asked for. Kept on
            // the job so the run can be recognised as a re-check, and so a rule counts as
            // applied only once the run reaches the end.
            'recheck' => sanitize_key((string) ($args['recheck'] ?? '')),
            'recheck_stages' => array_values(array_map('sanitize_key', (array) ($args['recheck_stages'] ?? []))),
            'time_budget' => max(1, min(20, (float) ($args['time_budget'] ?? 8))),
            'started_by' => get_current_user_id(),
            'last_message' => '',
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ];
    }

    private function normalize_job_state($job, array $report) {
        $job = is_array($job) ? $job : [];
        // A checkpoint from another build may record source totals for fewer stages than this
        // one has. Fill what the run's own dry run can still answer, and leave the rest
        // unknown rather than inventing zero: a zero makes the progress figures on the
        // migration screen claim the older site had nothing to import.
        $stored_totals = is_array($job['source_totals'] ?? null) ? $job['source_totals'] : [];
        $reported_totals = is_array($report['tables'] ?? null) ? $this->job_source_totals($report) : [];
        $source_totals = [];
        foreach (self::JOB_STAGES as $stage) {
            if (array_key_exists($stage, $stored_totals)) {
                $source_totals[$stage] = absint($stored_totals[$stage]);
            } elseif (array_key_exists($stage, $reported_totals)) {
                $source_totals[$stage] = absint($reported_totals[$stage]);
            } else {
                $source_totals[$stage] = null;
            }
        }
        $processed = is_array($job['processed'] ?? null) ? $job['processed'] : [];
        $normalized_processed = [];
        foreach (self::JOB_STAGES as $stage) {
            $normalized_processed[$stage] = absint($processed[$stage] ?? 0);
        }

        $stage_index = absint($job['stage_index'] ?? 0);
        // A checkpoint written in another job format describes positions in a stage list
        // that no longer applies, so its position is not trusted: the import starts again
        // from its first stage. Records that are already imported are recognised through
        // the migration map, so re-processing them changes nothing.
        if ((absint($job['version'] ?? 1) ?: 1) !== self::JOB_FORMAT_VERSION) {
            $stage_index = 0;
            $job['completed_stages'] = [];
            $job['cursor'] = [];
            $this->job_format_restarted = true;
        }
        $stage_index = min(count(self::JOB_STAGES), $stage_index);

        return [
            'version' => self::JOB_FORMAT_VERSION,
            'status' => sanitize_key((string) ($job['status'] ?? 'running')),
            'stage_index' => $stage_index,
            'cursor' => is_array($job['cursor'] ?? null) ? $job['cursor'] : [],
            'processed' => $normalized_processed,
            'completed_stages' => is_array($job['completed_stages'] ?? null) ? array_values(array_intersect(self::JOB_STAGES, $job['completed_stages'])) : [],
            'source_totals' => $source_totals,
            'batch_size' => max(1, min(500, absint($job['batch_size'] ?? 100))),
            'page_options' => self::normalize_page_options((array) ($job['page_options'] ?? [])),
            'grid_page_disposition' => (array) ($job['grid_page_disposition'] ?? []),
            'page_outcomes' => (array) ($job['page_outcomes'] ?? []),
            'settings_outcomes' => (array) ($job['settings_outcomes'] ?? []),
            'order_status_outcomes' => (array) ($job['order_status_outcomes'] ?? []),
            'database_rejections' => self::normalize_database_rejections($job['database_rejections'] ?? []),
            'recheck' => sanitize_key((string) ($job['recheck'] ?? '')),
            'recheck_stages' => array_values(array_map('sanitize_key', (array) ($job['recheck_stages'] ?? []))),
            'time_budget' => max(1, min(20, (float) ($job['time_budget'] ?? 8))),
            'started_by' => absint($job['started_by'] ?? 0),
            'last_message' => sanitize_text_field((string) ($job['last_message'] ?? '')),
            'last_error' => sanitize_text_field((string) ($job['last_error'] ?? '')),
            'created_at' => sanitize_text_field((string) ($job['created_at'] ?? '')),
            'updated_at' => sanitize_text_field((string) ($job['updated_at'] ?? '')),
        ];
    }

    private function job_source_totals(array $report) {
        $table_rows = static function ($suffix) use ($report) {
            return absint($report['tables'][$suffix]['rows'] ?? 0);
        };

        return [
            'settings' => 1,
            'grids' => $table_rows('banners'),
            'packages' => $table_rows('packages'),
            'price_rules' => $table_rows('prices'),
            'orders' => $table_rows('orders'),
            'blocks' => $table_rows('blocks'),
            'order_items' => $table_rows('orders'),
            'placements' => $table_rows('orders'),
            'pages' => absint($report['pages']['count'] ?? 0),
        ];
    }

    private function process_job_stage($stage, array $job, $limit) {
        $stage = sanitize_key($stage);
        $limit = max(1, absint($limit));

        if ('settings' === $stage) {
            if (absint($job['processed']['settings'] ?? 0) > 0) {
                return ['cursor' => [], 'done' => true, 'imported' => 0, 'processed' => 0];
            }

            return [
                'cursor' => [],
                'done' => true,
                'imported' => $this->import_settings(),
                'processed' => 1,
            ];
        }

        if ('pages' === $stage) {
            return $this->process_page_stage($job, $limit);
        }

        $definition = $this->table_stage_definition($stage);
        if (!$definition) {
            return ['cursor' => [], 'done' => true, 'imported' => 0, 'processed' => 0];
        }

        $batch = $this->legacy_batch($definition['table'], $definition['order'], is_array($job['cursor'] ?? null) ? $job['cursor'] : [], $limit);
        $imported = 0;
        foreach ($batch['rows'] as $row) {
            $result = $this->{$definition['handler']}($row);
            $imported += 'order_items' === $stage ? absint($result) : absint($result ? 1 : 0);
        }

        return [
            'cursor' => is_array($batch['cursor'] ?? null) ? $batch['cursor'] : [],
            'done' => empty($batch['has_more']),
            'imported' => $imported,
            'processed' => count($batch['rows']),
        ];
    }

    private function process_page_stage(array $job, $limit) {
        $repo = new \MillionDollarScript\V3\Pages\PageRepository();
        $candidates = $this->source->page_candidates();
        $offset = absint($job['cursor']['offset'] ?? 0);
        $slice = array_slice($candidates, $offset, max(1, absint($limit)));
        $imported = 0;

        foreach ($slice as $candidate) {
            $imported += absint($this->import_page_candidate($candidate, $repo) ? 1 : 0);
        }

        $next_offset = $offset + count($slice);
        $done = $next_offset >= count($candidates);
        $result = [
            'cursor' => ['offset' => $next_offset],
            'done' => $done,
            'imported' => $imported,
            'processed' => count($slice),
        ];
        if ($done) {
            $result['orphan_grid_pages_created'] = $this->reconcile_grid_pages();
        }

        return $result;
    }

    private function table_stage_definition($stage) {
        switch ($stage) {
            case 'grids':
                return ['handler' => 'import_grid_row', 'order' => ['banner_id'], 'table' => 'banners'];
            case 'packages':
                return ['handler' => 'import_package_row', 'order' => ['package_id'], 'table' => 'packages'];
            case 'price_rules':
                return ['handler' => 'import_price_rule_row', 'order' => ['price_id'], 'table' => 'prices'];
            case 'orders':
                return ['handler' => 'import_order_row', 'order' => ['order_id'], 'table' => 'orders'];
            case 'blocks':
                return ['handler' => 'import_block_row', 'order' => ['banner_id', 'block_id'], 'table' => 'blocks'];
            case 'order_items':
                return ['handler' => 'import_order_items_row', 'order' => ['order_id'], 'table' => 'orders'];
            case 'placements':
                return ['handler' => 'import_placement_row', 'order' => ['order_id'], 'table' => 'orders'];
        }

        return null;
    }

    private function job_response(array $run) {
        $report = is_array($run['report'] ?? null) ? $run['report'] : [];
        $job = $this->normalize_job_state($report['job'] ?? [], $report);
        $totals = $this->normalize_totals(is_array($run['totals'] ?? null) ? $run['totals'] : []);
        $source_total = 0;
        $processed_total = 0;
        foreach (self::JOB_STAGES as $stage) {
            $source_total += absint($job['source_totals'][$stage] ?? 0);
            $processed_total += min(absint($job['source_totals'][$stage] ?? 0), absint($job['processed'][$stage] ?? 0));
        }

        $status = sanitize_key((string) ($run['status'] ?? $job['status'] ?? 'running'));
        $completed = 'completed' === $status || $job['stage_index'] >= count(self::JOB_STAGES);
        $current_stage = $completed ? '' : (self::JOB_STAGES[$job['stage_index']] ?? '');
        $percent = $source_total > 0 ? (int) floor(($processed_total / $source_total) * 100) : ($completed ? 100 : 0);
        $percent = min(100, max(0, $percent));

        return [
            'run_id' => absint($run['id'] ?? 0),
            'source_prefix' => (string) ($run['source_prefix'] ?? ''),
            'status' => $status,
            'status_label' => $this->job_status_label($status),
            'completed' => $completed,
            'stage' => $current_stage,
            'stage_label' => $current_stage ? $this->stage_label($current_stage) : __('Complete', 'million-dollar-script'),
            'stage_index' => absint($job['stage_index'] ?? 0),
            'stage_count' => count(self::JOB_STAGES),
            'processed_total' => $processed_total,
            'source_total' => $source_total,
            'percent' => $percent,
            'warnings' => absint($totals['warnings'] ?? 0),
            'imported_total' => array_sum(array_map('absint', array_intersect_key($totals, array_fill_keys(self::JOB_STAGES, true)))),
            'totals' => $totals,
            'job' => $job,
            'message' => $this->job_message($status, $current_stage, $percent),
        ];
    }

    private function stage_label($stage) {
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

    private function job_status_label($status) {
        switch (sanitize_key($status)) {
            case 'completed':
                return __('Completed', 'million-dollar-script');
            case 'paused':
                return __('Paused', 'million-dollar-script');
            case 'failed':
                return __('Failed', 'million-dollar-script');
            case 'running':
                return __('Running', 'million-dollar-script');
            default:
                return __('Pending', 'million-dollar-script');
        }
    }

    private function job_message($status, $stage, $percent) {
        if ('completed' === $status) {
            return __('Migration import completed.', 'million-dollar-script');
        }

        if ('paused' === $status) {
            return __('Migration import is paused.', 'million-dollar-script');
        }

        if ('failed' === $status) {
            return __('Migration import stopped before completion. Review the warnings and retry.', 'million-dollar-script');
        }

        return sprintf(
            /* translators: 1: stage label, 2: percent complete */
            __('Importing %1$s. %2$d%% complete.', 'million-dollar-script'),
            $stage ? $this->stage_label($stage) : __('migration data', 'million-dollar-script'),
            absint($percent)
        );
    }

    public static function banner_pixel_dimensions(array $row) {
        $block_width = max(1, absint($row['block_width'] ?? $row['pixel_width'] ?? 10));
        $block_height = max(1, absint($row['block_height'] ?? $row['pixel_height'] ?? 10));
        $blocks_wide = max(1, absint($row['grid_width'] ?? $row['width'] ?? 100));
        $blocks_high = max(1, absint($row['grid_height'] ?? $row['height'] ?? 100));

        return [
            'width' => $blocks_wide * $block_width,
            'height' => $blocks_high * $block_height,
            'block_width' => $block_width,
            'block_height' => $block_height,
            'blocks_wide' => $blocks_wide,
            'blocks_high' => $blocks_high,
        ];
    }

    public static function block_status($legacy_status, $fallback_order_status = '', $legacy_order_paid = false) {
        $status = sanitize_key($legacy_status);
        $order_status = sanitize_key($fallback_order_status);

        // MDS2 keeps block rows 'ordered' long after their order has expired, so the
        // order status is authoritative when a block is tied to an order.
        if ('' !== $order_status) {
            switch (self::order_status($order_status, $legacy_order_paid)) {
                case 'paid':
                    return 'sold';
                case 'reserved':
                case 'pending_payment':
                    return 'reserved';
                default:
                    // expired, cancelled, denied, deleted — release the block for resale.
                    return 'available';
            }
        }

        switch ($status) {
            case 'sold':
            case 'ordered':
                return 'sold';
            case 'reserved':
                return 'reserved';
            case 'nfs':
                return 'unavailable';
            case 'free':
            case 'available':
            case '':
            default:
                return 'available';
        }
    }

    /**
     * The MDS3 order status for one Million Dollar Script 2 order row.
     *
     * "confirmed" is not a paid state in the older plugin: it is what a customer's own
     * confirmation produces before payment. MDS2 says so itself - a confirmed order with no
     * WooCommerce order should still show "Pay Now" (src/Classes/Orders/Orders.php:2994), and
     * only a completed order counts there as having payment (same method, line 3001). A
     * confirmed row therefore arrives as paid only when the older site recorded payment for it
     * (see ReadsLegacyOrderPayments); otherwise it arrives as an order the customer can still
     * finish, and its blocks stay reserved instead of sold. Guessing paid would overstate this
     * site's revenue and leave the customer with no way to complete what they started.
     *
     * @param string $legacy_status   The status stored by the older plugin.
     * @param bool   $legacy_order_paid Whether that site recorded payment for this order.
     */
    public static function order_status($legacy_status, $legacy_order_paid = false) {
        switch (sanitize_key($legacy_status)) {
            case 'confirmed':
                return $legacy_order_paid ? 'paid' : 'pending_payment';
            case 'paid':
            case 'completed':
            case 'renew_paid':
                return 'paid';
            case 'new':
            case 'pending':
                return 'pending_payment';
            case 'expired':
            case 'renew_wait':
                return 'expired';
            case 'cancelled':
                return 'cancelled';
            case 'deleted':
                return 'deleted';
            case 'denied':
                return 'denied';
            default:
                // Valid MDS3 status so unmapped rows stay visible and can be
                // reviewed by the admin or aged out by the unpaid cleanup job.
                return 'pending_payment';
        }
    }

    public static function placement_status($legacy_order_status, $legacy_order_paid = false) {
        $status = self::order_status($legacy_order_status, $legacy_order_paid);
        if ('paid' === $status) {
            return 'active';
        }
        if (in_array($status, ['reserved', 'pending_payment'], true)) {
            return 'pending';
        }
        return 'cancelled';
    }
}
