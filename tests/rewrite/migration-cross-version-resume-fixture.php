<?php
/**
 * WP-CLI fixture: an import paused by one build and continued by another.
 *
 * A customer site can hold a paused import whose saved progress was written before the
 * plugin was updated, then continue it on the current build. Two things have to hold. The
 * continuation must neither process a record twice nor lose one it never reached - the
 * proof here is that the finished site matches a clean import of the same records. And a
 * checkpoint that describes a different job format must not be re-read under the current
 * stage list: it restarts from the first stage and says so, because a position in a stage
 * list that no longer exists means nothing.
 *
 * The version story is checked in migration-upgrade-rerun-fixture.php, which covers
 * re-running a completed import after an update. This fixture covers the resumable path.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/migration-cross-version-resume-fixture.php
 */

use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Setup\Installer;
use MillionDollarScript\V3\Support\Build;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/support/mds2-source.php';

global $wpdb;

$prefix = $wpdb->prefix . 'mdscxr_';
$control_prefix = $wpdb->prefix . 'mdscxrc_';
$older_version = '3.0.0-alpha.3';
$fails = [];
$check = function ($cond, $msg) use (&$fails) {
    if (!$cond) {
        $fails[] = $msg;
    }
};

Installer::create_tables();
mds2_source_create_schema($prefix);
$seed = mds2_source_seed($prefix);
mds2_source_create_schema($control_prefix);
$control_seed = mds2_source_seed($control_prefix);

/**
 * Everything this fixture's imports created, so a repeated run starts from an empty site
 * for these legacy ids and the counts below describe this run only.
 */
$purge = static function ($source_prefix) use ($wpdb) {
    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT entity_type, mds3_entity_type, mds3_id FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s',
        $source_prefix
    ), ARRAY_A);
    $order_ids = [];
    $grid_ids = [];
    $post_ids = [];
    foreach ((array) $rows as $row) {
        $id = absint($row['mds3_id']);
        if (!$id) {
            continue;
        }
        if ('order' === $row['mds3_entity_type']) {
            $order_ids[] = $id;
        } elseif ('grid' === $row['mds3_entity_type']) {
            $grid_ids[] = $id;
        } elseif (in_array($row['mds3_entity_type'], ['page', 'advertiser_page', 'attachment'], true)) {
            $post_ids[] = $id;
        }
    }
    if ($order_ids) {
        $in = implode(',', $order_ids);
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('order_items')) . ' WHERE order_id IN (' . $in . ')');
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id IN (' . $in . ')');
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('orders')) . ' WHERE id IN (' . $in . ')');
    }
    if ($grid_ids) {
        $in = implode(',', $grid_ids);
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('placements')) . ' WHERE grid_id IN (' . $in . ')');
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id IN (' . $in . ')');
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('price_rules')) . ' WHERE grid_id IN (' . $in . ')');
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('packages')) . ' WHERE grid_id IN (' . $in . ')');
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('grids')) . ' WHERE id IN (' . $in . ')');
    }
    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if ($post && 'attachment' === $post->post_type) {
            wp_delete_attachment($post_id, true);
        } elseif ($post) {
            wp_delete_post($post_id, true);
        }
    }
    $wpdb->delete(DB::table('migration_map'), ['source_prefix' => $source_prefix]);
    $wpdb->delete(DB::table('migration_runs'), ['source_prefix' => $source_prefix]);
};
$purge($prefix);
$purge($control_prefix);

$run_row = static function (int $run_id) use ($wpdb) {
    $row = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . DB::ident(DB::table('migration_runs')) . ' WHERE id = %d LIMIT 1',
        $run_id
    ), ARRAY_A);

    return is_array($row) ? $row : [];
};

/**
 * How many records of each kind the site holds for one legacy source. This is the number
 * a continuation must not exceed (nothing duplicated) and must not fall short of (nothing
 * missing).
 */
$holdings = static function (string $source_prefix) use ($wpdb) {
    $out = [];
    foreach (['grid', 'package', 'price_rule', 'order', 'block', 'order_item', 'placement'] as $type) {
        $out[$type] = absint($wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(DISTINCT mds3_id) FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND mds3_entity_type = %s',
            $source_prefix,
            $type
        )));
    }
    $out['map_rows'] = absint($wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND mds3_entity_type NOT IN (%s, %s, %s)',
        $source_prefix,
        'page',
        'advertiser_page',
        'attachment'
    )));
    foreach (['orders' => 'order', 'placements' => 'placement', 'blocks' => 'block', 'grids' => 'grid', 'packages' => 'package', 'price_rules' => 'price_rule'] as $table => $type) {
        $ids = $wpdb->get_col($wpdb->prepare(
            'SELECT DISTINCT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND mds3_entity_type = %s',
            $source_prefix,
            $type
        ));
        $present = 0;
        foreach ($ids as $id) {
            $found = absint($wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DB::ident(DB::table($table)) . ' WHERE id = %d',
                absint($id)
            )));
            if ($found > 0) {
                $present++;
            }
        }
        $out['rows_' . $table] = $present;
    }

    return $out;
};

/**
 * Pages are compared loosely: a later import in the same site may reuse or relocate a page
 * an earlier import made, so their count is not a stable measure of one run. The records
 * above are.
 */
$page_holdings = static function (string $source_prefix) use ($wpdb) {
    return absint($wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(DISTINCT mds3_id) FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND mds3_entity_type IN ('page', 'advertiser_page')",
        $source_prefix
    )));
};

/**
 * The rows an import wrote, in full and without their timestamps, keyed by the mapped id.
 * A continuation may add rows; it must not change or replace ones that already exist.
 */
$rows_snapshot = static function (string $source_prefix) use ($wpdb) {
    $sets = [
        'grids' => 'grid',
        'packages' => 'package',
        'price_rules' => 'price_rule',
        'orders' => 'order',
        'blocks' => 'block',
        'placements' => 'placement',
    ];
    $out = [];
    foreach ($sets as $table => $type) {
        $ids = $wpdb->get_col($wpdb->prepare(
            'SELECT DISTINCT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND mds3_entity_type = %s',
            $source_prefix,
            $type
        ));
        foreach ($ids as $id) {
            $row = $wpdb->get_row($wpdb->prepare(
                'SELECT * FROM ' . DB::ident(DB::table($table)) . ' WHERE id = %d',
                absint($id)
            ), ARRAY_A);
            if (!is_array($row)) {
                $out[$table . ':' . absint($id)] = 'MISSING';
                continue;
            }
            unset($row['created_at'], $row['updated_at']);
            $out[$table . ':' . absint($id)] = $row;
        }
    }

    return $out;
};

/**
 * Read the saved job blob of a run.
 */
$job_of = static function (array $row) {
    $report = json_decode((string) ($row['report'] ?? ''), true);

    return is_array($report) && is_array($report['job'] ?? null) ? $report['job'] : [];
};

$installed = Build::version();
$check($installed !== 'unknown', 'this build does not report a version, so the comparison below is meaningless');

// 1. A clean import of the same records, as the authority for what a correct result holds.
$control = (new Importer())->import($control_prefix);
$control_run = $run_row(absint($control['run_id'] ?? 0));
$control_totals = is_array($control['imported'] ?? null) ? (array) $control['imported'] : [];
$control_holdings = $holdings($control_prefix);
$control_pages = $page_holdings($control_prefix);
$check(($control_totals['orders'] ?? 0) > 0 && ($control_totals['placements'] ?? 0) > 0, 'the control import produced nothing to compare against: ' . wp_json_encode($control_totals));
$check(($control_holdings['order'] ?? 0) > 0 && ($control_holdings['placement'] ?? 0) > 0, 'the control import left no mapped rows to compare against: ' . wp_json_encode($control_totals));

// 2. An older build paused this import part-way. Run the real resumable path, then save
//    the checkpoint the way that build would have saved it: its own version stamp, no job
//    format version field, a stage list that included work this build does not have, and
//    progress recorded only up to the orders stage.
$start = (new Importer())->start_resumable($prefix, ['batch_size' => 2, 'time_budget' => 1]);
$run_id = absint($start['run_id'] ?? 0);
$check($run_id > 0, 'the resumable import did not leave a run record: ' . wp_json_encode(is_array($start) ? array_slice($start, 0, 2) : get_class($start)));
if ($run_id <= 0) {
    fwrite(STDERR, implode(PHP_EOL, $fails) . PHP_EOL);
    exit(1);
}

$steps = 0;
while ($steps < 40) {
    $row = $run_row($run_id);
    $job = $job_of($row);
    if ((string) ($row['status'] ?? '') === 'completed' || absint($job['stage_index'] ?? 0) >= 5) {
        break;
    }
    (new Importer())->run_resumable_step($run_id, ['batch_size' => 2, 'time_budget' => 1]);
    $steps++;
}
$row = $run_row($run_id);
$checkpoint = $job_of($row);
$check(($row['status'] ?? '') !== 'completed', 'the older build would have finished the whole import in one step, so there is no paused run to continue');
$check(absint($checkpoint['stage_index'] ?? 0) >= 5, 'the first build never reached the orders stage: ' . wp_json_encode(array_intersect_key((array) $checkpoint, array_flip(['stage_index', 'processed']))));
if (($row['status'] ?? '') === 'completed' || absint($checkpoint['stage_index'] ?? 0) < 5) {
    fwrite(STDERR, implode(PHP_EOL, $fails) . PHP_EOL);
    exit(1);
}

$before_resume = $rows_snapshot($prefix);
$check(count($before_resume) > 0, 'the paused import wrote no rows, so there is nothing to protect against duplication');

$stages_through_orders = array_slice((array) (new ReflectionClass(Importer::class))->getConstant('JOB_STAGES'), 0, 5);
$paused_report = json_decode((string) ($row['report'] ?? ''), true);
$paused_job = [
    'status' => 'paused',
    'stage_index' => 5,
    'cursor' => [],
    'processed' => array_merge((array) ($checkpoint['processed'] ?? []), ['legacy_pixels' => 9]),
    'completed_stages' => array_merge($stages_through_orders, ['legacy_pixels']),
    'source_totals' => array_intersect_key((array) ($checkpoint['source_totals'] ?? []), array_flip(['settings', 'grids'])),
    'batch_size' => 40,
    'page_options' => (array) ($checkpoint['page_options'] ?? []),
    'grid_page_disposition' => (array) ($checkpoint['grid_page_disposition'] ?? []),
    'page_outcomes' => (array) ($checkpoint['page_outcomes'] ?? []),
    'settings_outcomes' => (array) ($checkpoint['settings_outcomes'] ?? []),
    'order_status_outcomes' => (array) ($checkpoint['order_status_outcomes'] ?? []),
    'created_at' => (string) ($checkpoint['created_at'] ?? ''),
    'updated_at' => (string) ($checkpoint['updated_at'] ?? ''),
];
$paused_report['job'] = $paused_job;
$wpdb->update(DB::table('migration_runs'), [
    'status' => 'paused',
    'plugin_version' => $older_version,
    'report' => wp_json_encode($paused_report),
], ['id' => $run_id]);
$row = $run_row($run_id);
$check((string) ($row['plugin_version'] ?? '') === $older_version, 'the paused run does not read back as the older build that made it');
$check(!isset($job_of($row)['version']), 'the saved checkpoint does not look like one written before job formats were versioned');

// 3. The current build continues it. Nothing already written may change, and the records
//    the paused build never reached have to arrive.
$resumed = null;
$steps = 0;
while ($steps < 40) {
    $resumed = (new Importer())->run_resumable_step($run_id, ['resume' => 1, 'batch_size' => 40, 'time_budget' => 8]);
    $row = $run_row($run_id);
    if ((string) ($row['status'] ?? '') === 'completed') {
        break;
    }
    $steps++;
}
$check(($row['status'] ?? '') === 'completed', 'the continued import did not finish: ' . wp_json_encode(array_intersect_key((array) $job_of($row), array_flip(['stage_index', 'last_message', 'last_error']))));

$job = $job_of($row);
$check((string) ($row['plugin_version'] ?? '') === $older_version, 'the continued run overwrote the build that created the record');
$continued_made_by = (string) ($row['plugin_version'] ?? '');
$continued_reported_by = (string) (json_decode((string) ($row['report'] ?? ''), true)['plugin_version'] ?? '');
$check(($row['report'] && (json_decode((string) $row['report'], true)['plugin_version'] ?? '') === $installed), 'the finished report does not name the build that completed it');
$current_stages = (array) (new ReflectionClass(Importer::class))->getConstant('JOB_STAGES');
$check(
    [] === array_diff(array_map('strval', (array) ($job['completed_stages'] ?? [])), $current_stages),
    'a stage that no longer exists survived into the finished job: ' . wp_json_encode((array) ($job['completed_stages'] ?? []))
);
$check((int) ($job['version'] ?? 0) > 0, 'the finished job does not record its own format version');
$missing_totals = array_values(array_diff($current_stages, array_map('strval', array_keys((array) ($job['source_totals'] ?? [])))));
$check(
    !$missing_totals,
    'a checkpoint that recorded source totals for only some stages was not completed for the rest: ' . wp_json_encode($missing_totals)
);
// A count the older checkpoint never recorded stays unknown rather than turning into zero:
// a zero would make the migration screen say the older site had nothing to import.
$invented = [];
foreach (['orders', 'blocks', 'placements'] as $stage) {
    if (0 === ($job['source_totals'][$stage] ?? null)) {
        $invented[] = $stage;
    }
}
$check(
    !$invented,
    'a stage the older checkpoint never recorded was counted as having nothing to import: ' . wp_json_encode($job['source_totals'] ?? null)
);
$check(
    (int) ($job['source_totals']['settings'] ?? 0) > 0 && (int) ($job['source_totals']['grids'] ?? 0) > 0,
    'the counts an older checkpoint did record were not kept: ' . wp_json_encode($job['source_totals'] ?? null)
);

$after_resume = $holdings($prefix);
$check(
    $after_resume === $control_holdings,
    'the continued import holds a different set of records than a clean import of the same source: '
        . wp_json_encode(['continued' => $after_resume, 'clean_import' => $control_holdings])
);
$check(
    $control_pages === 0 || $page_holdings($prefix) > 0,
    'the continued import produced no pages while a clean import of the same source made ' . $control_pages
);

$duplicates = $wpdb->get_results($wpdb->prepare(
    'SELECT entity_type, legacy_id, mds3_entity_type, COUNT(DISTINCT mds3_id) AS targets FROM ' . DB::ident(DB::table('migration_map'))
        . ' WHERE source_prefix = %s GROUP BY entity_type, legacy_id, mds3_entity_type HAVING COUNT(DISTINCT mds3_id) > 1',
    $prefix
), ARRAY_A);
$check(!$duplicates, 'a legacy record ended up pointing at more than one new record: ' . wp_json_encode(array_slice((array) $duplicates, 0, 3)));

$snapshot_now = $rows_snapshot($prefix);
$changed = [];
foreach ($before_resume as $key => $row_state) {
    if (($snapshot_now[$key] ?? null) !== $row_state) {
        $changed[] = $key;
    }
}
$check(
    !$changed,
    'continuing the import rewrote records the paused build had already finished: ' . wp_json_encode(array_slice($changed, 0, 6))
);
$missing = array_values(array_filter($snapshot_now, static function ($state) {
    return $state === 'MISSING';
}));
$check(!$missing, 'a record the paused build wrote is gone after continuing: ' . wp_json_encode(array_slice($missing, 0, 3)));

// 4. A checkpoint written in another job format is not re-read: it restarts, and says so.
$restart = (new Importer())->start_resumable($prefix, ['batch_size' => 40, 'time_budget' => 8]);
$restart_run_id = absint($restart['run_id'] ?? 0);
$check($restart_run_id > 0, 'the second resumable run did not leave a record: ' . wp_json_encode(is_array($restart) ? array_slice($restart, 0, 2) : get_class($restart)));
$row = $run_row($restart_run_id);
$report = json_decode((string) ($row['report'] ?? ''), true);
$midway = (array) ($report['job'] ?? []);
$midway['version'] = (int) ($midway['version'] ?? 1) + 1;
$midway['stage_index'] = 6;
$midway['completed_stages'] = array_slice($current_stages, 0, 6);
$midway['cursor'] = [];
$report['job'] = $midway;
$wpdb->update(DB::table('migration_runs'), ['status' => 'paused', 'report' => wp_json_encode($report)], ['id' => $restart_run_id]);

$holdings_before_restart = $holdings($prefix);
$snapshot_before_restart = $rows_snapshot($prefix);

$steps = 0;
while ($steps < 40) {
    (new Importer())->run_resumable_step($restart_run_id, ['resume' => 1, 'batch_size' => 40, 'time_budget' => 8]);
    $row = $run_row($restart_run_id);
    if ((string) ($row['status'] ?? '') === 'completed') {
        break;
    }
    $steps++;
}
$check(($row['status'] ?? '') === 'completed', 'the run whose checkpoint was in another format did not finish: ' . wp_json_encode(array_intersect_key((array) $job_of($row), array_flip(['stage_index', 'last_message', 'last_error']))));
$restarted_report = json_decode((string) ($row['report'] ?? ''), true);
$notice = '';
foreach (array_merge((array) ($restarted_report['notices'] ?? []), (array) ($restarted_report['warnings'] ?? [])) as $message) {
    if (false !== stripos((string) $message, 'another format')) {
        $notice = (string) $message;
        break;
    }
}
$check('' !== $notice, 'a checkpoint in another job format was re-read without telling the site what happened');
$check(
    [] === array_filter((array) ($restarted_report['warnings'] ?? []), static function ($warning) {
        return false !== stripos((string) $warning, 'another format');
    }),
    'an explanation of the run was counted as a warning needing attention'
);
$check(
    'unknown' === $installed || (string) ($restarted_report['job']['version'] ?? '') === (string) (new ReflectionClass(Importer::class))->getConstant('JOB_FORMAT_VERSION'),
    'the finished job did not record this build as its format: ' . wp_json_encode($restarted_report['job']['version'] ?? null)
);
$check(
    $holdings($prefix) === $holdings_before_restart,
    're-walking the whole import changed what the site holds: '
        . wp_json_encode(['after' => $holdings($prefix), 'before' => $holdings_before_restart])
);
$snapshot_after_restart = $rows_snapshot($prefix);
$changed = [];
foreach ($snapshot_before_restart as $key => $row_state) {
    if (($snapshot_after_restart[$key] ?? null) !== $row_state) {
        $changed[] = $key;
    }
}
$check(
    !$changed,
    're-walking the whole import rewrote records that were already imported: ' . wp_json_encode(array_slice($changed, 0, 6))
);

echo wp_json_encode([
    'installed_version' => $installed,
    'older_version' => $older_version,
    'clean_import_holdings' => $control_holdings,
    'continued_run' => [
        'id' => $run_id,
        'made_by' => $continued_made_by,
        'reported_by' => $continued_reported_by,
    ],
    'continued_holdings' => $after_resume,
    'rows_protected_from_rewrite' => count($before_resume),
    'restart_notice' => $notice,
    'holdings_unchanged_by_full_rewalk' => $holdings($prefix) === $holdings_before_restart,
]) . PHP_EOL;

$wpdb->query('DROP TABLE IF EXISTS ' . DB::ident($prefix . 'banners') . ', ' . DB::ident($control_prefix . 'banners'));
$purge($prefix);
$purge($control_prefix);
mds2_source_cleanup($prefix, $seed);
mds2_source_cleanup($control_prefix, $control_seed);

if ($fails) {
    fwrite(STDERR, implode(PHP_EOL, $fails) . PHP_EOL);
    exit(1);
}

echo 'MDS3 cross-version migration resume fixture passed.' . PHP_EOL;
exit(0);
