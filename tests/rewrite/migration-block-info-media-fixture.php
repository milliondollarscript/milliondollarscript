<?php
/**
 * WP-CLI fixture: a picture that survives only in the order record.
 *
 * Million Dollar Script 2 wrote a normalised block payload - coordinates and the image
 * itself - into orders.block_info on every selection, and its own Orders class says so.
 * An order whose block rows were never written, or whose block rows carry no image, has
 * its picture only in that column. Until now the importer measured the column, dropped
 * it, and reported "no recoverable image".
 *
 * Three orders in one run, so the paths cannot be confused with each other:
 *   97 - block rows with no image; block_info is the PHP-serialized map the customer
 *        dumps actually hold. Expect the picture back, and a report entry saying where
 *        it came from and how big it is.
 *   98 - the same payload as JSON. Expect the same, because the column is plain text
 *        written across versions.
 *   99 - block_info holds something that is not a block map, and nothing else has a
 *        picture either. Expect no image and no claim about the payload - and no block
 *        invented from it: blocks come from the older blocks table, or the grid
 *        inventory and the coverage counts stop agreeing.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/migration-block-info-media-fixture.php
 */

use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

require_once __DIR__ . '/support/mds2-source.php';

$source_prefix = $wpdb->prefix . 'mdsbinfo_';
$grid_id = 31;
$fails = [];
$check = function ($cond, $msg) use (&$fails) {
    if (!$cond) {
        $fails[] = $msg;
    }
};

$map_target = static function ($entity_type, $legacy_id) use ($wpdb, $source_prefix) {
    return absint($wpdb->get_var($wpdb->prepare(
        'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND entity_type = %s AND legacy_id = %s AND mds3_entity_type = %s LIMIT 1',
        $source_prefix,
        $entity_type,
        (string) $legacy_id,
        $entity_type
    )));
};

mds2_source_create_schema($source_prefix);

// One cell of a grid, which is the size the older grid generator kept. The fixture
// seeds a 10x10 cell so the recovered copy is exactly at cell size - the case the
// report has to be honest about.
if (function_exists('imagecreatetruecolor')) {
    $image = imagecreatetruecolor(10, 10);
    $teal = imagecolorallocate($image, 20, 120, 110);
    imagefilledrectangle($image, 0, 0, 9, 9, $teal);
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);
} else {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
}
$image_b64 = base64_encode($png);
$png_sha = sha1($png);

$ad_ids = [];
foreach ([97 => 'Block Info Serialized Ad', 98 => 'Block Info Json Ad', 99 => 'Nothing Left To Recover Ad'] as $order_id => $title) {
    $ad_ids[$order_id] = (int) wp_insert_post([
        'post_type' => 'mds-pixel',
        'post_status' => 'publish',
        'post_title' => $title,
    ]);
    update_post_meta($ad_ids[$order_id], '_milliondollarscript_text', '<p>' . $title . ' popup</p>');
}

$wpdb->insert($source_prefix . 'banners', [
    'banner_id' => $grid_id,
    'grid_width' => 1000,
    'grid_height' => 1000,
    'days_expire' => 30,
    'price_per_block' => 2.5,
    'name' => 'Block Info Fixture Grid',
    'currency' => 'USD',
    'max_orders' => 10,
    'block_width' => 10,
    'block_height' => 10,
    'grid_block' => 'grid',
    'max_blocks' => 20,
    'min_blocks' => 1,
    'bgcolor' => '#ffffff',
    'auto_publish' => 'Y',
    'auto_approve' => 'Y',
    'nfs_covered' => 'N',
    'enabled' => 'Y',
]);

// The block rows exist and say which cells the ad held; they carry no picture. That is
// what makes this the last-resort path: nothing before block_info can find the image.
$seeded_orders = [
    97 => [5, 6],
    98 => [7],
    99 => [8],
];
$block_info = [
    // The shape the customer dumps hold: a serialized map keyed by block id.
    97 => serialize([
        5 => ['map_x' => 50, 'map_y' => 0, 'image_data' => $image_b64, 'currency' => 'USD', 'price' => '2.5', 'banner_id' => $grid_id],
        6 => ['map_x' => 60, 'map_y' => 0, 'image_data' => '', 'currency' => 'USD', 'price' => '2.5', 'banner_id' => $grid_id],
    ]),
    98 => json_encode([7 => ['map_x' => 70, 'map_y' => 0, 'image_data' => $image_b64, 'currency' => 'USD', 'price' => '2.5', 'banner_id' => $grid_id]]),
    99 => 'this is not a block payload',
];

foreach ($seeded_orders as $order_id => $blocks) {
    $wpdb->insert($source_prefix . 'orders', [
        'order_id' => $order_id,
        'user_id' => 1,
        'blocks' => '[' . implode(',', $blocks) . ']',
        'status' => 'completed',
        'order_date' => '2025-03-01 00:00:00',
        'price' => 2.5 * count($blocks),
        'quantity' => count($blocks),
        'banner_id' => $grid_id,
        'currency' => 'USD',
        'days_expire' => 30,
        'package_id' => 0,
        'ad_id' => $ad_ids[$order_id],
        'approved' => 'Y',
        'published' => 'Y',
        'block_info' => $block_info[$order_id],
    ]);
    $wpdb->insert($source_prefix . 'transactions', ['order_id' => $order_id]);
    foreach ($blocks as $block_id) {
        $wpdb->insert($source_prefix . 'blocks', [
            'block_id' => $block_id,
            'user_id' => 1,
            'status' => 'sold',
            'x' => ($block_id % 1000) * 10,
            'y' => intdiv($block_id, 1000) * 10,
            'image_data' => '',
            'url' => 'https://example.com/block-info',
            'alt_text' => 'Block info fixture alt text',
            'file_name' => '',
            'approved' => 'Y',
            'published' => 'Y',
            'currency' => 'USD',
            'order_id' => $order_id,
            'price' => 2.5,
            'banner_id' => $grid_id,
            'ad_id' => $ad_ids[$order_id],
        ]);
    }
}

$created_attachments = [];
$created_files = [];

try {
    $result = (new Importer())->import($source_prefix);
    if (is_wp_error($result)) {
        throw new RuntimeException('import failed: ' . $result->get_error_message());
    }

    $verification = (array) ($result['verification'] ?? []);
    $repairs = array_column((array) ($verification['repairs'] ?? []), 'reason');

    // ---- 97 and 98: the picture comes back, and the report says where from. ----
    foreach ([97, 98] as $order_id) {
        $mds3_order = $map_target('order', $order_id);
        $check($mds3_order > 0, sprintf('order %d was not mapped', $order_id));

        $placement = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id = %d LIMIT 1',
            $mds3_order
        ), ARRAY_A);
        $check(is_array($placement), sprintf('order %d got no placement', $order_id));

        $attachment_id = absint($placement['attachment_id'] ?? 0);
        $check($attachment_id > 0, sprintf('order %d lost its picture: the block_info payload was not used', $order_id));
        if ($attachment_id) {
            $created_attachments[] = $attachment_id;
            $relative = (string) get_post_meta($attachment_id, '_wp_attached_file', true);
            $check(false !== strpos($relative, 'milliondollarscript/migrated/'), sprintf('order %d attachment is not in the migrated media directory: %s', $order_id, $relative));
            $upload = wp_upload_dir();
            $path = '' === $relative ? '' : trailingslashit($upload['basedir']) . ltrim($relative, '/');
            if ($path && is_readable($path)) {
                $created_files[] = $path;
                $check($png_sha === sha1((string) file_get_contents($path)), sprintf('order %d recovered the wrong bytes', $order_id));
            } else {
                $check(false, sprintf('order %d attachment file is missing: %s', $order_id, $relative));
            }
        }

        $named_source = array_values(array_filter($repairs, static function ($reason) use ($order_id) {
            return false !== strpos((string) $reason, 'kept in the order record') && false !== stripos((string) $reason, 'block');
        }));
        $check([] !== $named_source, sprintf('order %d: the report does not say the picture came from the order record', $order_id));
        $check(
            [] !== array_filter($named_source, static function ($reason) {
                return false !== stripos((string) $reason, 'cell size (10 by 10 pixels)');
            }),
            sprintf('order %d: the report does not name the pixel size of what was recovered: %s', $order_id, wp_json_encode($named_source))
        );
    }

    // The two working paths must not be mistaken for the blocks.image_data path, whose
    // report entry names the grid records. Every cell-sized copy in this run came from
    // an order record, and only the two orders that have one are reported at all.
    $cell_size_lines = array_values(array_filter($repairs, static function ($reason) {
        return false !== stripos((string) $reason, 'kept at cell size');
    }));
    $check(2 === count($cell_size_lines), 'expected two cell-sized recoveries, saw ' . count($cell_size_lines) . ': ' . wp_json_encode($cell_size_lines));
    $check(
        [] === array_filter($cell_size_lines, static function ($reason) {
            return false === stripos((string) $reason, 'order record');
        }),
        'a block_info recovery was reported as if it came from the grid records: ' . wp_json_encode($cell_size_lines)
    );

    // ---- 99: nothing to recover, and nothing invented. ----
    // An ad with no picture gets no placement - that is the existing contract, and the
    // run says so once. The block_info path must not quietly change it, and must not
    // invent a block to hang a made-up picture on.
    $order_99 = $map_target('order', 99);
    $check($order_99 > 0, 'order 99 was not mapped');
    $placement_99 = $wpdb->get_row($wpdb->prepare(
        'SELECT attachment_id FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id = %d LIMIT 1',
        $order_99
    ), ARRAY_A);
    $check(!is_array($placement_99), 'order 99 was given a placement although nothing could be recovered');
    $skipped_99 = array_values(array_filter(
        array_column((array) ($verification['skipped'] ?? []), 'reason'),
        static function ($reason) {
            return false !== stripos((string) $reason, 'no recoverable original image');
        }
    ));
    $check([] !== $skipped_99, 'the run did not say that order 99 has no recoverable picture: ' . wp_json_encode($verification['skipped'] ?? []));
    $check(
        [] === array_filter($repairs, static function ($reason) {
            return false !== stripos((string) $reason, 'kept in the order record for block 8');
        }),
        'an unreadable block_info payload was treated as a picture'
    );

    // Blocks come from the older blocks table only: two for order 97, one each for the
    // others. A block invented out of block_info would make five.
    $check(4 === absint($wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND entity_type = 'block'",
        $source_prefix
    ))), 'block rows were added or lost by this run');

    // ---- A second run: the same attachment, no duplicates. ----
    $result2 = (new Importer())->import($source_prefix);
    if (is_wp_error($result2)) {
        throw new RuntimeException('second import failed: ' . $result2->get_error_message());
    }
    foreach ([97, 98] as $order_id) {
        $mds3_order = $map_target('order', $order_id);
        $again = $wpdb->get_row($wpdb->prepare(
            'SELECT attachment_id FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id = %d LIMIT 1',
            $mds3_order
        ), ARRAY_A);
        $check(is_array($again) && absint($again['attachment_id'] ?? 0) > 0, sprintf('order %d lost its picture on the second run', $order_id));
        $placements = absint($wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id = %d',
            $mds3_order
        )));
        $check(1 === $placements, sprintf('order %d gained a second placement on the re-run (%d)', $order_id, $placements));
    }
    foreach ($created_attachments as $attachment_id) {
        $relative = (string) get_post_meta($attachment_id, '_wp_attached_file', true);
        $duplicates = absint($wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . DB::ident($wpdb->postmeta) . " WHERE meta_key = %s AND meta_value = %s",
            '_wp_attached_file',
            $relative
        )));
        $check(1 === $duplicates, 'the re-run created a duplicate attachment for ' . $relative . ' (' . $duplicates . ' rows)');
    }

    echo wp_json_encode([
        'repaired_source_lines' => array_slice(array_values($named_source), 0, 2),
        'block_info_covers' => absint($verification['coverage']['images']['cell_size_only'] ?? 0),
        'created_attachments' => $created_attachments,
    ]) . PHP_EOL;
} finally {
    // A browser pass over the migration report needs the run to still be there. A
    // caller that is looking at the screen defines MDS_FIXTURE_KEEP_STATE before
    // including this file; running the fixture normally afterwards cleans up both.
    if (!defined('MDS_FIXTURE_KEEP_STATE')) {
        $map_rows = (array) $wpdb->get_results($wpdb->prepare(
            'SELECT entity_type, mds3_id FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s',
            $source_prefix
        ), ARRAY_A);
        foreach ($map_rows as $mapped) {
            switch ((string) ($mapped['entity_type'] ?? '')) {
                case 'placement':
                    $wpdb->delete(DB::table('placements'), ['id' => absint($mapped['mds3_id'])]);
                    break;
                case 'order':
                    $wpdb->delete(DB::table('orders'), ['id' => absint($mapped['mds3_id'])]);
                    break;
                case 'block':
                    $wpdb->delete(DB::table('blocks'), ['id' => absint($mapped['mds3_id'])]);
                    break;
                case 'order_item':
                    $wpdb->delete(DB::table('order_items'), ['id' => absint($mapped['mds3_id'])]);
                    break;
            }
        }
        $grid_ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            'SELECT DISTINCT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND entity_type = 'grid' AND mds3_entity_type = 'grid'",
            $source_prefix
        )));
        if ($grid_ids) {
            $wpdb->query('DELETE FROM ' . DB::ident(DB::table('grids')) . ' WHERE id IN (' . implode(',', $grid_ids) . ')');
            $wpdb->query('DELETE FROM ' . DB::ident(DB::table('pages')) . ' WHERE grid_id IN (' . implode(',', $grid_ids) . ')');
        }
        $wpdb->delete(DB::table('migration_map'), ['source_prefix' => $source_prefix]);
        $wpdb->delete(DB::table('migration_runs'), ['source_prefix' => $source_prefix]);
        foreach ($created_attachments as $attachment_id) {
            $relative = (string) get_post_meta($attachment_id, '_wp_attached_file', true);
            if ('' !== $relative) {
                $upload = wp_upload_dir();
                $path = trailingslashit($upload['basedir']) . ltrim($relative, '/');
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            wp_delete_post($attachment_id, true);
        }
        foreach ($created_files as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        foreach ($ad_ids as $ad_id) {
            wp_delete_post((int) $ad_id, true);
        }
        mds2_source_cleanup($source_prefix, []);
    }
}

if ($fails) {
    fwrite(STDERR, implode(PHP_EOL, $fails) . PHP_EOL);
    exit(1);
}

echo 'MDS3 block_info media recovery fixture passed.' . PHP_EOL;
exit(0);
