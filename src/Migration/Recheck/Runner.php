<?php
/**
 * Running a standing rule over a source that was migrated earlier.
 *
 * The work is the import's own: read the older records again and let each step update what
 * it already made. What this adds is where to start (the steps a rule touches, not the whole
 * site), the bounded batches of a resumable run, and the record that this source has now had
 * the rule applied.
 *
 * @see \MillionDollarScript\V3\Migration\Recheck\Registry
 */

namespace MillionDollarScript\V3\Migration\Recheck;

use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

class Runner {
    /**
     * Start a re-check. It runs in bounded batches like any other import: the Migration
     * screen continues it from where a batch stopped.
     *
     * @return array|\WP_Error
     */
    public static function start(Recheck $recheck, string $source_prefix, array $args = []) {
        $stages = Registry::stagesFor($recheck);
        if (is_wp_error($stages)) {
            return $stages;
        }

        $existing = self::running($source_prefix, $recheck->id());
        if ($existing) {
            return $existing;
        }

        $importer = new Importer();

        return $importer->start_resumable($source_prefix, array_merge($args, [
            'start_stage' => (string) $stages[0],
            'recheck' => $recheck->id(),
            'recheck_stages' => $stages,
        ]));
    }

    /**
     * Carry a re-check through to the end, in bounded batches. The Migration screen drives
     * the same batches one request at a time; this is the path a command or a test takes.
     *
     * @return array|\WP_Error
     */
    public static function complete($run_id, array $args = [], $max_batches = 60) {
        $run_id = absint($run_id);
        if ($run_id < 1) {
            return new \WP_Error('mds3_recheck_run_missing', 'A re-check run is required.');
        }

        $result = new \WP_Error('mds3_recheck_incomplete', 'The re-check did not finish within its batches.');
        for ($batch = 0; $batch < max(1, absint($max_batches)); $batch++) {
            $importer = new Importer();
            $result = $importer->run_resumable_step($run_id, array_merge($args, ['resume' => true]));
            if (is_wp_error($result)) {
                return $result;
            }
            if ('completed' === (string) ($result['status'] ?? '')) {
                return $result;
            }
        }

        return $result;
    }

    /**
     * A re-check run that reached the end counts as applied for this source.
     *
     * Called by the import itself, which is the only thing that knows a run finished: an
     * admin closing the tab, or a batch stopping at the host, leaves the run unfinished and
     * the rule still offered.
     */
    public static function recordCompletion(string $source_prefix, array $job, array $totals, $run_id = 0): void {
        $id = sanitize_key((string) ($job['recheck'] ?? ''));
        if ('' === $id) {
            return;
        }

        $counts = [];
        foreach (Importer::import_stages() as $stage) {
            $counts[$stage] = absint($totals[$stage] ?? 0);
        }

        Registry::record($source_prefix, $id, [
            'run_id' => absint($run_id ?: ($job['run_id'] ?? 0)),
            'records_read' => array_sum($counts),
            'stages' => array_map('strval', (array) ($job['recheck_stages'] ?? [])),
        ]);
    }

    /**
     * A re-check for this source that is already under way, as a resumable status.
     *
     * @return array|\WP_Error
     */
    public static function running(string $source_prefix, $recheck_id = '') {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id, report FROM ' . DB::ident(DB::table('migration_runs')) . " WHERE source_prefix = %s AND mode = 'import' AND status IN ('running','paused') ORDER BY id DESC LIMIT 5",
            $source_prefix
        ), ARRAY_A);

        foreach ((array) $rows as $row) {
            $report = json_decode((string) ($row['report'] ?? ''), true);
            $job = is_array($report) && is_array($report['job'] ?? null) ? $report['job'] : [];
            $id = sanitize_key((string) ($job['recheck'] ?? ''));
            if ('' === $id || ($recheck_id && $recheck_id !== $id)) {
                continue;
            }

            return ['run_id' => absint($row['id']), 'status' => (string) ($row['status'] ?? ''), 'recheck' => $id];
        }

        return [];
    }
}
