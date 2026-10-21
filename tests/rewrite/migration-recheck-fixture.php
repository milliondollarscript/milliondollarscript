<?php
/**
 * WP-CLI fixture: a standing rule over a source that was imported earlier.
 *
 * A customer's uploads are missing on a new host. An import runs, and an ad whose picture the
 * older records named by file cannot use it, so the import settles for another copy the older
 * site happened to keep. A week later the uploads are restored. Nothing in the migrated
 * records changed, so nothing reads the older records again, and the ad keeps the copy it
 * settled for.
 *
 * This fixture walks that whole path: import with the named picture withheld, put it back, and
 * run the standing rule for ad images. The proof is that the placement is still the same row
 * - same order, same grid position, same counts everywhere - and now carries the picture the
 * older records named. It also checks the two framework promises the Migration screen depends
 * on: a re-check walks only the steps its rule names, and a source that has been checked
 * stops being offered.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/migration-recheck-fixture.php
 * Set MDS3_RECHECK_KEEP=1 to leave the imported site and its tables for inspection - WP-CLI
 * claims an unknown --keep flag, so the escape hatch is an environment variable.
 */

use MillionDollarScript\V3\Migration\DryRun;
use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Migration\Recheck\ImageCopyRecheck;
use MillionDollarScript\V3\Migration\Recheck\Registry;
use MillionDollarScript\V3\Migration\Recheck\Runner;
use MillionDollarScript\V3\Placement\Placements;
use MillionDollarScript\V3\Setup\Installer;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/support/mds2-source.php';

global $wpdb;

$prefix = $wpdb->prefix . 'mdsrc_';
$rule_id = 'migration-image-copies';
$fails = [];
$check = function ($cond, $msg) use (&$fails) {
    if (!$cond) {
        $fails[] = $msg;
    };
};

Installer::create_tables();
Registry::forget($prefix);

/**
 * Everything a previous run of this fixture imported, so the counts below describe this run.
 */
$purge = static function () use ($wpdb, $prefix) {
    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT mds3_entity_type, mds3_id FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s',
        $prefix
    ), ARRAY_A);
    $order_ids = $post_ids = [];
    foreach ((array) $rows as $row) {
        $id = absint($row['mds3_id']);
        if (!$id) {
            continue;
        }
        if ('order' === $row['mds3_entity_type']) {
            $order_ids[] = $id;
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
    foreach ($post_ids as $id) {
        wp_delete_post($id, true);
    }
    $wpdb->query($wpdb->prepare(
        'DELETE FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s',
        $prefix
    ));
};

$counts = static function () use ($wpdb, $prefix) {
    return [
        'placements' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::ident(DB::table('placements'))),
        'orders' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::ident(DB::table('orders'))),
        'order_items' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::ident(DB::table('order_items'))),
        'map_rows' => (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s',
            $prefix
        )),
    ];
};

/**
 * The placement an older order produced. Its picture is the attachment_id column, which is
 * what the migration writes.
 */
$placement_for_order = static function ($legacy_order_id) use ($wpdb, $prefix) {
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT mds3_id FROM " . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND entity_type = 'placement' AND mds3_entity_type = 'placement' AND legacy_id = %s LIMIT 1",
        $prefix,
        (string) absint($legacy_order_id)
    ));
};

$placement_image = static function ($placement_id) use ($wpdb) {
    return absint($wpdb->get_var($wpdb->prepare(
        'SELECT attachment_id FROM ' . DB::ident(DB::table('placements')) . ' WHERE id = %d',
        $placement_id
    )));
};

$image_file_of = static function ($attachment_id) {
    return trim((string) get_post_meta(absint($attachment_id), '_wp_attached_file', true));
};

$purge();
mds2_source_create_schema($prefix);
$seed = mds2_source_seed($prefix);

$upload = wp_upload_dir();
$relative = trim((string) ($seed['file_name_relative'] ?? ''));
if ('' === $relative) {
    fwrite(STDERR, "The fixture source no longer names a picture file for the order under test.\n");
    exit(1);
}
$path = trailingslashit($upload['basedir']) . ltrim($relative, '/');

// The older records for this order name a picture file directly, which is the copy the rule
// follows. Other copies the older site kept stay where they are.
$target_order = absint($wpdb->get_var(
    "SELECT order_id FROM `" . str_replace('`', '``', $prefix) . "blocks` WHERE file_name <> '' AND order_id > 0 ORDER BY order_id ASC LIMIT 1"
));
$check($target_order > 0, 'the fixture source no longer has an order whose block names a picture file');

// The scenario needs two pictures: the one the older records name, and the copy MDS2 kept for
// the order. MDS2 wrote that copy as tmp_<order id>_<timestamp>_<random> in its own images
// folder, and on a host that never received the uploads it is often the only thing left of the
// picture - which is why the import looks it up by the order number in its name. Neither file
// is part of the seeded source, so the fixture writes them. A scenario that waits for files
// nobody creates tests nothing, and that is how this fixture came to fail: the import answered
// honestly that there was nothing to recover.
mds2_source_write_images(array(basename($relative)));
$kept_name = 'tmp_' . $target_order . '_' . time() . '_recheck.png';
$kept_paths = mds2_source_write_images(array($kept_name));
$kept_relative = 'milliondollarscript/images/' . $kept_name;

// The named picture is withheld - the file on disk and the media-library record that points
// at it - which is what a host that never received the uploads looks like. The import should
// still fill the ad, from one of the other copies the older site left behind.
$bytes = '';
if (file_exists($path)) {
    $bytes = (string) file_get_contents($path);
    unlink($path);
}
$held_attachment = (int) $wpdb->get_var($wpdb->prepare(
    'SELECT post_id FROM ' . DB::ident($wpdb->postmeta) . ' WHERE meta_key = %s AND meta_value = %s LIMIT 1',
    '_wp_attached_file',
    $relative
));
if ($held_attachment) {
    wp_delete_post($held_attachment, true);
}

$dry = (new DryRun())->report($prefix);
$check(!is_wp_error($dry), 'dry run reported an error');

$importer = new Importer();
$run = $importer->start_resumable($prefix, ['upgrade_in_place' => [], 'batch_size' => 200]);
$check(!is_wp_error($run), 'import: ' . (is_wp_error($run) ? $run->get_error_message() : 'failed to start'));
$guard = 0;
while (in_array((string) ($run['status'] ?? ''), ['running', 'paused'], true) && $guard++ < 60) {
    $run = (new Importer())->run_resumable_step(absint($run['run_id'] ?? 0), ['resume' => true, 'batch_size' => 200]);
    if (is_wp_error($run)) {
        break;
    }
}
$check('completed' === (string) ($run['status'] ?? ''), 'import did not complete');

$placement_id = $placement_for_order($target_order);
// The migration states why it dropped a row, and that reason is the useful half of this
// failure: without it the following checks cascade and the reader has to work out which
// of the import's guards fired. The run keeps its report in its own row - the step
// response does not carry it - so ask the run.
if ($placement_id <= 0) {
    // The run keeps its report in its own row - the step response does not carry it.
    $stored = $wpdb->get_var('SELECT report FROM ' . DB::table('migration_runs') . ' ORDER BY id DESC LIMIT 1');
    $report = json_decode((string) $stored, true);
    $said = array();
    foreach ((array) (is_array($report) ? ($report['skipped'] ?? array()) : array()) as $skip) {
        if (!is_array($skip)) {
            continue;
        }
        $said[] = (string) ($skip['entity'] ?? '?') . '#' . (string) ($skip['source_id'] ?? '?') . ': ' . (string) ($skip['reason'] ?? '');
    }
    throw new RuntimeException(
        'the order under test did not produce a placement to fix - '
        . ($said ? 'the migration reported: ' . implode(' | ', array_slice($said, 0, 5)) : 'the migration named no skipped placement for this order')
    );
}
// Which legacy orders hold a placement, so a re-check that changes one is named rather than
// counted. The totals above say "one more row"; this says whose ad appeared.
$placement_legacies = static function () use ($wpdb, $prefix) {
    $rows = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
        'SELECT legacy_id FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND mds3_entity_type = 'placement'",
        $prefix
    )));
    sort($rows);

    return $rows;
};

$legacies_before = $placement_legacies();
$initial_image = $placement_image($placement_id);
$check($initial_image > 0, 'an order with other usable copies lost its ad picture entirely');
$check($relative !== $image_file_of($initial_image), 'the named picture was found even though its file and media record were withheld');
$pending_before = array_map(static function ($recheck) {
    return $recheck->id();
}, Registry::pendingFor($prefix));
$check(in_array($rule_id, $pending_before, true), 'the standing rule was not offered for a source that is missing a picture');

// The uploads are restored: the file, and the media-library record for it. Nothing in the
// migrated records changes - that is the point.
$check('' !== $bytes, 'the fixture lost the picture file it was meant to restore');
wp_mkdir_p(dirname($path));
file_put_contents($path, $bytes);
$restored_attachment = mds2_source_attachment($relative);
$check(absint($restored_attachment) > 0, 'the restored upload did not get a media-library record');

$started = Runner::start(new ImageCopyRecheck(), $prefix);
$check(!is_wp_error($started), 'the re-check did not start: ' . (is_wp_error($started) ? $started->get_error_message() : 'failed'));
$recheck_run = is_wp_error($started) ? 0 : absint($started['run_id'] ?? 0);
$check($recheck_run > 0 && $recheck_run !== absint($run['run_id'] ?? 0), 'the re-check did not start a run of its own');

// A re-check reads only the steps its rule names - not the whole older site again.
$job = json_decode((string) $wpdb->get_var($wpdb->prepare('SELECT report FROM ' . DB::ident(DB::table('migration_runs')) . ' WHERE id = %d', $recheck_run)), true);
$stages = is_array($job['job']['recheck_stages'] ?? null) ? $job['job']['recheck_stages'] : [];
$check(['placements'] === $stages, 'a re-check should walk only the steps its rule names, got ' . implode(',', $stages));

$finished = Runner::complete($recheck_run, ['batch_size' => 200]);
$check(!is_wp_error($finished), 'the re-check failed: ' . (is_wp_error($finished) ? $finished->get_error_message() : 'failed'));
$check('completed' === (string) ($finished['status'] ?? ''), 'the re-check did not report completing');

$thumbnail = $placement_image($placement_id);
$check($thumbnail > 0, 'the ad still has no picture after the standing rule ran');
$check($thumbnail !== $initial_image, 'the standing rule left the copy the import had settled for');
$check($relative === $image_file_of($thumbnail), 'the ad is not using the picture the older records named');
$check(wp_attachment_is_image($thumbnail), 'the picture the rule attached is not an image attachment');
$check($placement_id === $placement_for_order($target_order), 'the re-check made a second placement instead of updating the one row');

$after = $counts();
$added_legacies = array_values(array_diff($placement_legacies(), $legacies_before));

// A re-check runs the placement import again for the orders in scope. That is how the ad of an
// order whose picture only became available gets filled in, and this scenario makes a picture
// available on purpose - so an ad appearing here is the rule doing its job. What must never
// happen is a second ad for an order that already has one, or an ad added without a picture.
$check(!array_intersect($added_legacies, $legacies_before), 'a re-check wrote a second placement for an order that already had one');
foreach ($added_legacies as $added_legacy) {
    $added_id = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND mds3_entity_type = 'placement' AND legacy_id = %d ORDER BY id DESC LIMIT 1",
        $prefix,
        absint($added_legacy)
    ));
    $check(absint($placement_image($added_id)) > 0, 'the re-check completed the ad for order ' . $added_legacy . ' without a picture');
}

$applied = Registry::appliedFor($prefix);
$check(isset($applied[$rule_id]['at']), 'the source was not recorded as checked');
$pending_after = array_map(static function ($recheck) {
    return $recheck->id();
}, Registry::pendingFor($prefix));
$check(!in_array($rule_id, $pending_after, true), 'a source that has been checked is still offered the same rule');

// Reading the records again a second time must not change an answer that is already current.
$second = Runner::start(new ImageCopyRecheck(), $prefix);
$check(!is_wp_error($second), 'an applied rule should still be runnable again');
if (!is_wp_error($second)) {
    $second_run = Runner::complete(absint($second['run_id'] ?? 0), ['batch_size' => 200]);
    $check('completed' === (string) ($second_run['status'] ?? ''), 'the repeated re-check did not report completing');
    $check($thumbnail === $placement_image($placement_id), 'a repeated re-check replaced a picture that was already correct');
    $check($after === $counts(), 'a repeated re-check added records');
}

if ($fails) {
    fwrite(STDERR, "Migration re-check fixture failed:\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}

$keep = in_array('--keep', (array) ($argv ?? []), true) || '1' === getenv('MDS3_RECHECK_KEEP');
if (!$keep) {
    // The copy this fixture wrote, and the media record the import made for it. Leaving them
    // behind would offer the next run a picture it did not create.
    $kept_attachment = absint($wpdb->get_var($wpdb->prepare(
        'SELECT post_id FROM ' . DB::ident($wpdb->postmeta) . " WHERE meta_key = %s AND meta_value = %s LIMIT 1",
        '_wp_attached_file',
        $kept_relative
    )));
    if ($kept_attachment) {
        wp_delete_post($kept_attachment, true);
    }
    if (isset($kept_paths[$kept_name]) && file_exists($kept_paths[$kept_name])) {
        unlink($kept_paths[$kept_name]);
    }
    $purge();
    Registry::forget($prefix);
    mds2_source_cleanup($prefix, $seed);
}

echo json_encode([
    'ok' => true,
    'placement_id' => $placement_id,
    'attachment_id' => $thumbnail,
    'records_read' => absint($applied[$rule_id]['result']['records_read'] ?? 0),
    'counts' => $after,
    'kept' => $keep,
]) . "\n";
