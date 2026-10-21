<?php
/**
 * WP-CLI fixture: a migration record written by one build, read by another.
 *
 * Why this exists: a customer site can hold an import started by an older alpha and then
 * be updated - the site in Vikunja #30 is exactly that shape, including a hand-patched
 * copy of the plugin. Two things have to hold. The record must say which build made it,
 * so a mixed history is visible instead of inferred. And a newer build re-running that
 * import must neither duplicate what the older run created nor lose anything it missed.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/migration-upgrade-rerun-fixture.php
 */

use MillionDollarScript\V3\Admin\Admin;
use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Setup\Installer;
use MillionDollarScript\V3\Support\Build;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/support/mds2-source.php';

global $wpdb;

$prefix = $wpdb->prefix . 'mdsupgrade_';
$fails = [];
$check = function ($cond, $msg) use (&$fails) {
    if (!$cond) {
        $fails[] = $msg;
    }
};

// The plugin was just updated, so the schema moved with it. On a database created before
// this column existed, this is the step that adds it.
Installer::create_tables();
$check(
    $wpdb->get_results("SHOW COLUMNS FROM " . DB::ident(DB::table('migration_runs')) . " LIKE 'plugin_version'"),
    'the run table has no plugin version column after the schema update'
);

mds2_source_create_schema($prefix);
$seed = mds2_source_seed($prefix);

/**
 * Start from no imported rows of our own, so the counts below describe this run and not
 * an earlier run of the same fixture. The other migration fixtures leave what they import
 * behind, and every run here reuses the same legacy ids.
 */
$purge_previous_run = function () use ($prefix, $wpdb) {
    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT entity_type, mds3_id FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s',
        $prefix
    ), ARRAY_A);
    $order_ids = [];
    $grid_ids = [];
    foreach ((array) $rows as $row) {
        if ('order' === $row['entity_type']) {
            $order_ids[] = absint($row['mds3_id']);
        } elseif ('grid' === $row['entity_type']) {
            $grid_ids[] = absint($row['mds3_id']);
        } elseif ('advertiser_page' === $row['entity_type'] && absint($row['mds3_id'])) {
            wp_delete_post(absint($row['mds3_id']), true);
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
    $wpdb->delete(DB::table('migration_map'), ['source_prefix' => $prefix]);
    $wpdb->delete(DB::table('migration_runs'), ['source_prefix' => $prefix]);
};
$purge_previous_run();

/**
 * The run record as stored.
 */
$run_row = static function (int $run_id) {
    global $wpdb;

    $row = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . DB::ident(DB::table('migration_runs')) . ' WHERE id = %d LIMIT 1',
        $run_id
    ), ARRAY_A);

    return $row;
};

/**
 * What the site holds that came from this source. A second run must not change it.
 */
$footprint = static function () use ($prefix, $wpdb) {
    $out = [];
    $ids = $wpdb->get_col($wpdb->prepare(
        'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND mds3_entity_type IN (%s, %s, %s, %s)',
        $prefix,
        'grid',
        'order',
        'placement',
        'block'
    ));
    $out['mapped_entities'] = count(array_unique($ids));
    foreach (['grids' => 'grid', 'orders' => 'order', 'placements' => 'placement', 'blocks' => 'block'] as $table => $type) {
        $out[$table] = absint($wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND mds3_entity_type = %s',
            $prefix,
            $type
        )));
    }

    return $out;
};

/**
 * The imported rows themselves, in stable order, without their timestamps. Two runs must
 * produce the same data: the same attachment ids, the same text, the same coordinates, and
 * no row that turned into nothing.
 */
$data_fingerprint = static function () use ($prefix, $wpdb) {
    $sets = [
        'orders' => 'order',
        'placements' => 'placement',
        'blocks' => 'block',
    ];
    $out = [];
    foreach ($sets as $table => $entity_type) {
        $ids = $wpdb->get_col($wpdb->prepare(
            'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND mds3_entity_type = %s ORDER BY mds3_id',
            $prefix,
            $entity_type
        ));
        $rows = [];
        foreach ($ids as $id) {
            $row = $wpdb->get_row($wpdb->prepare(
                'SELECT * FROM ' . DB::ident(DB::table($table)) . ' WHERE id = %d',
                absint($id)
            ), ARRAY_A);
            if (!is_array($row)) {
                $rows[] = 'MISSING:' . absint($id);
                continue;
            }
            unset($row['created_at'], $row['updated_at']);
            $rows[] = $row;
        }
        $out[$table] = md5(wp_json_encode($rows));
    }

    return $out;
};

$installed = Build::version();

// 1. An older build ran this import. Record it as that build would have, then move the
//    record's version back to it: the situation a site is in after an update.
$first = (new Importer())->import($prefix);
$first_run_id = absint($first['run_id'] ?? 0);
$check($first_run_id > 0, 'the first import left no run record: ' . wp_json_encode(is_object($first) ? get_class($first) : array_slice((array) $first, 0, 2)) . ' ' . (string) $wpdb->last_error);
if ($fails) {
    // Nothing below makes sense without a run to look at.
    fwrite(STDERR, implode(PHP_EOL, $fails) . PHP_EOL);
    exit(1);
}

$first_row = $run_row($first_run_id);
$check(is_array($first_row), 'the first run record cannot be read back');
$check(
    is_array($first_row) && (string) $first_row['plugin_version'] === $installed,
    'the run record does not name the build that made it: ' . (is_array($first_row) ? (string) $first_row['plugin_version'] : '(no row)') . ' against ' . $installed
);

$first_report = json_decode((string) ($first_row['report'] ?? ''), true) ?: [];
$check(
    ($first_report['plugin_version'] ?? '') === $installed,
    'the stored report does not name the build that wrote it'
);

$first_totals = (array) ($first['imported'] ?? []);
$check(
    absint($first_totals['grids'] ?? 0) > 0 && absint($first_totals['orders'] ?? 0) > 0,
    'the first import created nothing, so a re-run would prove nothing: ' . wp_json_encode($first_totals)
);

$older_version = '3.0.0-alpha.5';
$wpdb->update(DB::table('migration_runs'), ['plugin_version' => $older_version], ['id' => $first_run_id]);
$older_row = $run_row($first_run_id);
$first_footprint = $footprint();
$first_data = $data_fingerprint();
$check(
    $first_footprint['mapped_entities'] > 0 && $first_footprint['orders'] > 0,
    'nothing is mapped to this source, so the re-run comparison is empty'
);

// 2. The newer build must be able to say, and show, that the record is older than it is.
$admin = new Admin();
$call = static function ($method, array $args) use ($admin) {
    $method = new ReflectionMethod(Admin::class, $method);
    $method->setAccessible(true);

    return $method->invokeArgs($admin, $args);
};

$summary = $call('migration_run_summary', [$older_row]);
$check(
    ($summary['recorded_by'] ?? '') === $older_version,
    'the run summary does not report the version that made the record'
);
$check(
    ($summary['plugin_version'] ?? '') === $installed,
    'the run summary does not report the installed version'
);
// The sentence a site owner can act on, not a pair of version numbers in a metric box.
$older_build_note = (string) ($summary['older_build_note'] ?? '');
$check(
    '' !== $older_build_note && strpos($older_build_note, $older_version) !== false && strpos($older_build_note, $installed) !== false,
    'the run summary does not say in one sentence that an older build made this record'
);

$rendered = '';
if ($installed !== 'unknown') {
    // The notice is only worth showing when both versions are known, which is the case on
    // a real site. Rendered through the plugin's own renderer, which supplies the partial's
    // variables and its object scope.
    ob_start();
    $call('migration_recovery_panel', [$older_row, true]);
    $rendered = (string) ob_get_clean();
}

$check(
    $installed === 'unknown' || ('' !== $older_build_note && strpos($rendered, $older_build_note) !== false),
    'the migration screen does not show the sentence naming the older build that made this record'
);

// The setup wizard step is where an admin decides what to do with the old plugin, so it
// has to name the build that produced the import it is describing. Rendered through the
// plugin's own renderer, which is what supplies the template's variables.
$upgrade_step = '';
if ($installed !== 'unknown') {
    $legacy_plugins = [[
        'plugin_file' => 'milliondollarscript-two/milliondollarscript-two.php',
        'name' => 'Million Dollar Script 2',
        'active' => true,
    ]];
    $legacy_source = ['has_data' => true, 'rows' => 40, 'prefix' => $prefix];
    ob_start();
    $call('mds2_upgrade_step', [$legacy_plugins, $legacy_source]);
    $upgrade_step = (string) ob_get_clean();
}
$check(
    $installed === 'unknown' || strpos($upgrade_step, 'This import was run by ' . $older_version) !== false,
    'the upgrade step does not say which build ran the import it is describing'
);

// 3. The upgraded build runs the same import again. Nothing may duplicate or disappear.
$second = (new Importer())->import($prefix);
$second_run_id = absint($second['run_id'] ?? 0);
$check($second_run_id > 0 && $second_run_id !== $first_run_id, 'the second import did not create its own run record');

$second_row = $run_row($second_run_id);
$check(
    is_array($second_row) && (string) $second_row['plugin_version'] === $installed,
    'the new run record does not name the build that made it'
);
$second_report = json_decode((string) ($second_row['report'] ?? ''), true) ?: [];

// A record made by the build that is reading it needs no explanation, so the second run -
// marked with the installed version - must produce no sentence at all.
$second_summary = is_array($second_row) ? $call('migration_run_summary', [$second_row]) : [];
$check(
    '' === (string) ($second_summary['older_build_note'] ?? ''),
    'the screen is being told to explain a record made by this same build'
);

$second_footprint = $footprint();
$second_data = $data_fingerprint();
$check(
    $second_data === $first_data,
    're-running after the update changed the imported data: '
        . wp_json_encode([$first_data, $second_data])
);
$check(
    $second_footprint === $first_footprint,
    're-running after the update changed what the site holds: '
        . wp_json_encode($first_footprint) . ' against ' . wp_json_encode($second_footprint)
);
$check(
    ($second_report['coverage'] ?? null) === ($first_report['coverage'] ?? null),
    'the re-run saw different gaps than the first run: '
        . wp_json_encode([array_keys((array) ($first_report['coverage'] ?? [])), array_keys((array) ($second_report['coverage'] ?? []))])
);
// The repair counts are expected to differ: a repair is work done, and a second run finds
// less to do because the first run already did it. What must not change is the data above.
$check(
    count((array) ($second_report['repairs'] ?? [])) <= count((array) ($first_report['repairs'] ?? [])),
    'the re-run reported more records needing repair than the first run, which means the first run did not finish its work: '
        . wp_json_encode([count((array) ($first_report['repairs'] ?? [])), count((array) ($second_report['repairs'] ?? []))])
);

// 4. The older record keeps its own history; the newer build must not overwrite it.
$still_old = $run_row($first_run_id);
$check(
    is_array($still_old) && (string) $still_old['plugin_version'] === $older_version,
    'the older run record was rewritten by the newer build'
);

echo wp_json_encode([
    'installed_version' => $installed,
    'first_run' => ['id' => $first_run_id, 'made_by' => $still_old['plugin_version'] ?? null, 'totals' => $first_totals],
    'second_run' => ['id' => $second_run_id, 'made_by' => $second_row['plugin_version'] ?? null],
    'footprint_after_first' => $first_footprint,
    'footprint_after_second' => $second_footprint,
    'data_unchanged' => $second_data === $first_data,
    'data_fingerprints' => $first_data,
    'repairs' => [count((array) ($first_report['repairs'] ?? [])), count((array) ($second_report['repairs'] ?? []))],
    'screen_names_older_build' => '' !== $older_build_note && strpos($rendered, $older_build_note) !== false,
    'explains_a_run_of_this_build' => '' !== (string) ($second_summary['older_build_note'] ?? ''),
    'upgrade_step_names_older_build' => strpos($upgrade_step, 'This import was run by ' . $older_version) !== false,
]) . PHP_EOL;

if ($fails) {
    fwrite(STDERR, implode(PHP_EOL, $fails) . PHP_EOL);
    exit(1);
}

echo 'MDS3 migration upgrade re-run fixture passed.' . PHP_EOL;
exit(0);
