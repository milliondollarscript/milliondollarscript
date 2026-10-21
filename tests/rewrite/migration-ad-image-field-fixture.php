<?php
/**
 * WP-CLI fixture: an ad picture kept under the site's own field name.
 *
 * Why this exists: the older plugin writes an ad record through its generic form
 * system, so the field holding the picture is named after the form field on that
 * site. A fixed list of meta keys cannot cover every site (Vikunja #30: a real
 * site's ads had no image after import while the site itself showed them). The
 * import has to find the picture by what the field looks like, say which field it
 * used, and not guess between two unnamed fields.
 *
 * Run with:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/migration-ad-image-field-fixture.php
 */

use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/support/mds2-source.php';

global $wpdb;

$prefix = $wpdb->prefix . 'mdsv3i_';
$fails = [];
$check = function ($cond, $msg) use (&$fails) {
    if (!$cond) {
        $fails[] = $msg;
    }
};

mds2_source_create_schema($prefix);
$seed = mds2_source_seed($prefix);

/**
 * Start from no imported rows of our own. The other migration fixtures leave what they
 * import behind, and each run of this one uses the same legacy ids, so without this the
 * second run would inherit the first run's placement - including the image this fixture
 * is trying to find out about.
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
    if ($grid_ids) {
        $ids = implode(',', $grid_ids);
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id IN (' . $ids . ')');
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('grids')) . ' WHERE id IN (' . $ids . ')');
    }
    if ($order_ids) {
        $ids = implode(',', $order_ids);
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id IN (' . $ids . ')');
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('order_items')) . ' WHERE order_id IN (' . $ids . ')');
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('orders')) . ' WHERE id IN (' . $ids . ')');
    }
    $wpdb->query($wpdb->prepare('DELETE FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s', $prefix));
};
$purge_previous_run();

$grid_id = absint($seed['grid_id']);
$blocks_wide = absint($seed['grid_blocks_wide']);
$block_px_wide = absint($seed['block_px_wide']);
$block_px_high = absint($seed['block_px_high']);
$free_block_ids = [195, 196];
$order_ids = [900, 901];

$used_blocks = (array) $wpdb->get_col(
    'SELECT block_id FROM ' . DB::ident($prefix . 'blocks') . ' WHERE block_id IN (' . implode(',', $free_block_ids) . ')'
);
$check(!$used_blocks, 'fixture block ids are already used by the seeded grid: ' . implode(',', $used_blocks));
$used_orders = (array) $wpdb->get_col(
    'SELECT order_id FROM ' . DB::ident($prefix . 'orders') . ' WHERE order_id IN (' . implode(',', $order_ids) . ')'
);
$check(!$used_orders, 'fixture order ids are already used by the seeded grid: ' . implode(',', $used_orders));

// Two pictures of their own, so a wrong pick would be visible as the wrong file.
$paths = mds2_source_write_images(['field-picture-a.png', 'field-picture-b.png', 'field-picture-c.png']);
$relative_a = 'milliondollarscript/images/field-picture-a.png';
$relative_b = 'milliondollarscript/images/field-picture-b.png';
$relative_c = 'milliondollarscript/images/field-picture-c.png';
$attachment_a = mds2_source_attachment($relative_a);
$attachment_b = mds2_source_attachment($relative_b);
$attachment_c = mds2_source_attachment($relative_c);
$seed['created_files'] = array_merge((array) ($seed['created_files'] ?? []), [$relative_a, $relative_b, $relative_c]);
$seed['created_attachments'] = array_merge((array) ($seed['created_attachments'] ?? []), [$attachment_a, $attachment_b, $attachment_c]);

/**
 * One older order whose ad record keeps its picture in a field named by that site's form.
 */
$seed_field_case = function ($order_id, $block_id, array $meta_keys_to_values) use ($prefix, $grid_id, $blocks_wide, $block_px_wide, $block_px_high) {
    global $wpdb;
    $advertiser = mds2_source_advertiser(
        'field-' . $order_id,
        'Field Picture ' . $order_id,
        'https://example.com/field-' . $order_id,
        '<p>Field picture ' . $order_id . '</p>',
        0,
        $prefix
    );
    $ad_id = absint($advertiser['id'] ?? 0);
    foreach ($meta_keys_to_values as $key => $value) {
        update_post_meta($ad_id, $key, $value);
    }

    $wpdb->insert($prefix . 'orders', [
        'user_id' => 1,
        'order_id' => $order_id,
        'blocks' => '[' . $block_id . ']',
        'status' => 'paid',
        'order_date' => '2025-08-01 00:00:00',
        'price' => 4.5,
        'quantity' => 1,
        'banner_id' => $grid_id,
        'currency' => 'CAD',
        'days_expire' => 90,
        'date_published' => '2025-08-01 00:00:00',
        'package_id' => 0,
        'ad_id' => $ad_id,
        'approved' => 'Y',
        'published' => 'Y',
        'subscr_status' => 'active',
        'original_order_id' => $order_id,
        'block_info' => '',
    ]);
    $wpdb->insert($prefix . 'blocks', [
        'block_id' => $block_id,
        'banner_id' => $grid_id,
        'user_id' => 1,
        'status' => 'sold',
        // MDS 2 geometry: pixels derived from the block id.
        'x' => ($block_id % $blocks_wide) * $block_px_wide,
        'y' => intdiv($block_id, $blocks_wide) * $block_px_high,
        // Neither of the other two media paths: the field on the ad record is all there is.
        'image_data' => '',
        'url' => 'https://example.com/field-' . $order_id,
        'alt_text' => 'Field picture ' . $order_id,
        'file_name' => '',
        'mime_type' => '',
        'approved' => 'Y',
        'published' => 'Y',
        'currency' => 'CAD',
        'order_id' => $order_id,
        'price' => 4.5,
        'ad_id' => $ad_id,
        'click_count' => 0,
        'view_count' => 0,
    ]);

    return $ad_id;
};

// Case A: the field says what it holds, under a name no fixed list would know.
$field_key_a = 'gallery_picture_upload';
$seed_field_case($order_ids[0], $free_block_ids[0], [$field_key_a => $attachment_a]);

// Case B: two fields say nothing about pictures, so there is nothing safe to choose.
$seed_field_case($order_ids[1], $free_block_ids[1], [
    'primary_sponsor_tile' => $attachment_b,
    'secondary_sponsor_tile' => $attachment_c,
]);

$result = (new Importer())->import($prefix);
if (is_wp_error($result)) {
    fwrite(STDERR, 'import failed: ' . $result->get_error_message() . PHP_EOL);
    exit(1);
}

// The coverage query reaches the same fields the importer does, so a broken statement
// here would show up as an empty count rather than an error.
$check('' === (string) $wpdb->last_error, 'a migration query failed: ' . (string) $wpdb->last_error);

$target_ids = [];
foreach ($order_ids as $order_id) {
    $target_ids[$order_id] = absint($wpdb->get_var($wpdb->prepare(
        'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND entity_type = 'order' AND mds3_entity_type = 'order' AND legacy_id = %s LIMIT 1",
        $prefix,
        (string) $order_id
    )));
    $check($target_ids[$order_id] > 0, 'order ' . $order_id . ' was not imported');
}

$placement_for = function ($order_id) use ($target_ids, $wpdb) {
    return $target_ids[$order_id]
        ? $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id = %d LIMIT 1',
            $target_ids[$order_id]
        ), ARRAY_A)
        : null;
};

$repairs = array_column((array) ($result['verification']['repairs'] ?? []), 'reason');
$repairs_for = function ($needle) use ($repairs) {
    $hits = [];
    foreach ($repairs as $reason) {
        if (false !== strpos((string) $reason, $needle)) {
            $hits[] = (string) $reason;
        }
    }

    return $hits;
};

$placement_a = $placement_for($order_ids[0]);
$check($placement_a, 'case A has no placement');
$check($placement_a && absint($placement_a['attachment_id'] ?? 0) === $attachment_a, 'case A: expected the picture from the site field name, got ' . var_export($placement_a['attachment_id'] ?? null, true));

$named = $repairs_for($field_key_a);
$check($named, 'case A: the run did not name the field the picture came from');
$check($named && false !== strpos($named[0], 'not fixed'), 'case A: the report does not explain that the field name comes from the site: ' . var_export($named[0] ?? null, true));

$placement_b = $placement_for($order_ids[1]);
$check(!$repairs_for('primary_sponsor_tile') && !$repairs_for('secondary_sponsor_tile'), 'case B: the run chose between two fields that say nothing about pictures');
$check(!$placement_b || 0 === absint($placement_b['attachment_id'] ?? 0), 'case B: an ambiguous ad was given a picture anyway: ' . var_export($placement_b['attachment_id'] ?? null, true));

// Cleanup: the source tables, everything the seeded dataset and this run added (ad
// records, attachments, uploaded files), and what this run imported.
$purge_previous_run();
mds2_source_cleanup($prefix, $seed);

if ($fails) {
    fwrite(STDERR, implode(PHP_EOL, $fails) . PHP_EOL);
    exit(1);
}

echo 'MDS3 ad image field fixture passed.' . PHP_EOL;
