<?php
/**
 * WP-CLI fixture for MDS3 migration media recovery.
 *
 * Live-site order 96 scenario (Vikunja #3): MDS2 blocks carry the ad image
 * only as base64 PNG in image_data. The ad post has no image meta and the
 * blocks have no file_name. The import must recover the image (decode,
 * write a file, register an attachment) and import the placement with no
 * warning, and a re-import must stay idempotent.
 *
 * Run with:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/migration-flattened-media-fixture.php
 */

use MillionDollarScript\V3\Grid\GridPostType;
use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$source_prefix = $wpdb->prefix . 'mdsv3f_';
$fails = [];
$check = function ($cond, $msg) use (&$fails) {
    if (!$cond) {
        $fails[] = $msg;
    }
};

// --- minimal legacy MDS2 dataset -------------------------------------------
$cols = [
    'banners' => "banner_id INT NOT NULL AUTO_INCREMENT, grid_width INT NOT NULL DEFAULT 0, grid_height INT NOT NULL DEFAULT 0, days_expire MEDIUMINT(9) DEFAULT 0, price_per_block FLOAT NOT NULL DEFAULT 0, name VARCHAR(255) NOT NULL DEFAULT '', currency CHAR(3) NOT NULL DEFAULT 'USD', publish_date DATETIME DEFAULT CURRENT_TIMESTAMP, max_orders INT NOT NULL DEFAULT 0, block_width INT NOT NULL DEFAULT 10, block_height INT NOT NULL DEFAULT 10, grid_block TEXT NOT NULL, nfs_block LONGBLOB NOT NULL, tile TEXT NOT NULL, usr_grid_block TEXT NOT NULL, usr_nfs_block LONGBLOB NOT NULL, usr_ord_block TEXT NOT NULL, usr_res_block TEXT NOT NULL, usr_sel_block TEXT NOT NULL, usr_sol_block TEXT NOT NULL, max_blocks INT NOT NULL DEFAULT 0, min_blocks INT NOT NULL DEFAULT 0, date_updated DATETIME DEFAULT CURRENT_TIMESTAMP, bgcolor VARCHAR(7) NOT NULL DEFAULT '#FFFFFF', auto_publish CHAR(1) NOT NULL DEFAULT 'N', auto_approve CHAR(1) NOT NULL DEFAULT 'N', nfs_covered CHAR(1) NOT NULL DEFAULT 'N', enabled CHAR(1) NOT NULL DEFAULT 'Y', time_stamp INT DEFAULT NULL, PRIMARY KEY (banner_id)",
    'blocks' => "block_id INT NOT NULL DEFAULT 0, user_id INT DEFAULT NULL, status VARCHAR(20) NOT NULL DEFAULT '', x INT NOT NULL DEFAULT 0, y INT NOT NULL DEFAULT 0, image_data TEXT NOT NULL, url VARCHAR(255) NOT NULL DEFAULT '', alt_text TEXT NOT NULL, file_name VARCHAR(255) NOT NULL DEFAULT '', mime_type VARCHAR(100) NOT NULL DEFAULT '', approved VARCHAR(1) NOT NULL DEFAULT '', published VARCHAR(1) NOT NULL DEFAULT '', currency CHAR(3) NOT NULL DEFAULT 'USD', order_id INT NOT NULL DEFAULT 0, price FLOAT DEFAULT NULL, banner_id INT NOT NULL DEFAULT 1, ad_id INT NOT NULL DEFAULT 0, click_count INT NOT NULL DEFAULT 0, view_count INT NOT NULL DEFAULT 0, PRIMARY KEY (block_id, banner_id)",
    'orders' => "user_id INT NOT NULL DEFAULT 0, order_id INT NOT NULL AUTO_INCREMENT, blocks TEXT NOT NULL, status VARCHAR(30) NOT NULL DEFAULT '', order_date DATETIME DEFAULT CURRENT_TIMESTAMP, price FLOAT NOT NULL DEFAULT 0, quantity INT NOT NULL DEFAULT 0, banner_id INT NOT NULL DEFAULT 1, currency CHAR(3) NOT NULL DEFAULT 'USD', days_expire INT NOT NULL DEFAULT 0, date_published DATETIME DEFAULT CURRENT_TIMESTAMP, date_stamp DATETIME DEFAULT CURRENT_TIMESTAMP, package_id INT NOT NULL DEFAULT 0, ad_id INT DEFAULT NULL, approved VARCHAR(1) NOT NULL DEFAULT 'N', published VARCHAR(1) NOT NULL DEFAULT '', subscr_status VARCHAR(32) NOT NULL DEFAULT '', original_order_id INT DEFAULT NULL, previous_order_id INT NOT NULL DEFAULT 0, block_info LONGTEXT NOT NULL, order_in_progress VARCHAR(1) NOT NULL DEFAULT 'N', current_step INT NOT NULL DEFAULT 0, PRIMARY KEY (order_id)",
    'packages' => "banner_id INT NOT NULL DEFAULT 0, days_expire INT NOT NULL DEFAULT 0, price FLOAT NOT NULL DEFAULT 0, currency CHAR(3) NOT NULL DEFAULT '', package_id INT NOT NULL AUTO_INCREMENT, is_default VARCHAR(1) DEFAULT NULL, max_orders MEDIUMINT(9) NOT NULL DEFAULT 0, description VARCHAR(255) NOT NULL DEFAULT '', PRIMARY KEY (package_id)",
    'prices' => "price_id INT NOT NULL AUTO_INCREMENT, banner_id INT NOT NULL DEFAULT 0, row_from INT NOT NULL DEFAULT 0, row_to INT NOT NULL DEFAULT 0, block_id_from INT NOT NULL DEFAULT 0, block_id_to INT NOT NULL DEFAULT 0, price FLOAT NOT NULL DEFAULT 0, currency CHAR(3) NOT NULL DEFAULT '', color VARCHAR(50) NOT NULL DEFAULT '', col_from INT DEFAULT NULL, col_to INT DEFAULT NULL, PRIMARY KEY (price_id)",
    'transactions' => "transaction_id INT NOT NULL AUTO_INCREMENT, order_id INT NOT NULL DEFAULT 0, PRIMARY KEY (transaction_id)",
    'clicks' => "banner_id INT NOT NULL, block_id INT NOT NULL, user_id INT NOT NULL, date DATE DEFAULT '1970-01-01', clicks INT NOT NULL, PRIMARY KEY (banner_id, block_id, date)",
    'views' => "banner_id INT NOT NULL, block_id INT NOT NULL, user_id INT NOT NULL, date DATE DEFAULT '1970-01-01', views INT NOT NULL, PRIMARY KEY (banner_id, block_id, date)",
];
$charset = $wpdb->get_charset_collate();
foreach ($cols as $suffix => $definition) {
    $wpdb->query('DROP TABLE IF EXISTS ' . DB::ident($source_prefix . $suffix));
    $wpdb->query('CREATE TABLE ' . DB::ident($source_prefix . $suffix) . " ($definition) {$charset}");
}

// A real small PNG, encoded exactly the way the legacy GridImageGenerator does.
if (function_exists('imagecreatetruecolor')) {
    $image = imagecreatetruecolor(120, 60);
    $red = imagecolorallocate($image, 200, 40, 40);
    imagefilledrectangle($image, 0, 0, 119, 59, $red);
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);
} else {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
}
$image_b64 = base64_encode($png);
$png_sha = sha1($png);

// Legacy ad post with NO image meta - the live order-96 condition.
$ad_id = wp_insert_post([
    'post_type' => 'mds-pixel',
    'post_status' => 'publish',
    'post_title' => 'Flattened Fixture Ad',
]);
update_post_meta($ad_id, '_milliondollarscript_text', '<p>Flattened fixture popup</p>');

$wpdb->insert($source_prefix . 'banners', [
    'banner_id' => 7,
    'grid_width' => 1000,
    'grid_height' => 1000,
    'days_expire' => 30,
    'price_per_block' => 2.5,
    'name' => 'Flattened Fixture Grid',
    'currency' => 'USD',
    'max_orders' => 10,
    'block_width' => 10,
    'block_height' => 10,
    'grid_block' => 'grid',
    'nfs_block' => '',
    'tile' => '',
    'usr_grid_block' => '',
    'usr_nfs_block' => '',
    'usr_ord_block' => '',
    'usr_res_block' => '',
    'usr_sel_block' => '',
    'usr_sol_block' => '',
    'max_blocks' => 20,
    'min_blocks' => 1,
    'bgcolor' => '#ffffff',
    'auto_publish' => 'Y',
    'auto_approve' => 'Y',
    'nfs_covered' => 'N',
    'enabled' => 'Y',
]);
$wpdb->insert($source_prefix . 'orders', [
    'order_id' => 96,
    'user_id' => 1,
    'blocks' => '0,1,2,3',
    'status' => 'completed',
    'order_date' => '2025-03-01 00:00:00',
    'price' => 10,
    'quantity' => 4,
    'banner_id' => 7,
    'currency' => 'USD',
    'days_expire' => 30,
    'package_id' => 0,
    'ad_id' => $ad_id,
    'approved' => 'Y',
    'published' => 'Y',
    'block_info' => '',
]);
foreach ([0, 1, 2, 3] as $bid) {
    $wpdb->insert($source_prefix . 'blocks', [
        'block_id' => $bid,
        'user_id' => 1,
        'status' => 'sold',
        'x' => $bid,
        'y' => 0,
        'image_data' => $image_b64,
        'url' => 'https://example.com/flattened',
        'alt_text' => 'Flattened fixture alt text',
        'file_name' => '',
        'mime_type' => '',
        'approved' => 'Y',
        'published' => 'Y',
        'currency' => 'USD',
        'order_id' => 96,
        'price' => 2.5,
        'banner_id' => 7,
        'ad_id' => $ad_id,
        'click_count' => 0,
        'view_count' => 0,
    ]);
}

// --- import -----------------------------------------------------------------
$attachment_id = 0;
$relative = '';
$file_path = '';

try {
    $result = (new Importer())->import($source_prefix);
    if (is_wp_error($result)) {
        $fails[] = 'import failed: ' . $result->get_error_message();
    } else {
        $totals = (array) ($result['imported'] ?? []);
        $check(0 === absint($totals['warnings'] ?? 0), 'expected 0 warnings, got ' . absint($totals['warnings'] ?? 0));
        $check(0 === absint($totals['skipped'] ?? 0), 'expected 0 skips, got ' . absint($totals['skipped'] ?? 0));
        $check(1 === absint($totals['placements'] ?? 0), 'expected 1 placement, got ' . absint($totals['placements'] ?? 0));

        $target_order_id = absint($wpdb->get_var($wpdb->prepare(
            'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND entity_type = 'order' AND legacy_id = '96' AND mds3_entity_type = 'order' LIMIT 1",
            $source_prefix
        )));
        $check($target_order_id > 0, 'target order not mapped');

        $placement = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id = %d LIMIT 1',
            $target_order_id
        ), ARRAY_A);
        $check($placement, 'no placement imported');
        $attachment_id = absint($placement['attachment_id'] ?? 0);
        $check($attachment_id > 0, 'placement has no attachment (image lost)');

        $attachment = $attachment_id ? get_post($attachment_id) : null;
        $check($attachment && 'attachment' === $attachment->post_type, 'recovered media is not a WP attachment');

        $relative = $attachment_id ? (string) get_post_meta($attachment_id, '_wp_attached_file', true) : '';
        $check('' !== $relative && false !== strpos($relative, 'milliondollarscript/migrated/'), 'recovered file is not in the migrated media directory: ' . $relative);
        $upload = wp_upload_dir();
        $file_path = $relative ? $upload['basedir'] . '/' . ltrim($relative, '/') : '';
        $check('' !== $relative && is_readable($file_path), 'recovered attachment file missing');
        if ($file_path) {
            $check($png_sha === sha1((string) file_get_contents($file_path)), 'recovered image bytes do not match the original');
        }

        // Second import: recovery must stay idempotent (no duplicate attachment/row).
        $result2 = (new Importer())->import($source_prefix);
        if (is_wp_error($result2)) {
            $fails[] = 'second import failed: ' . $result2->get_error_message();
        } else {
            $totals2 = (array) ($result2['imported'] ?? []);
            $check(0 === absint($totals2['warnings'] ?? 0), 'second import produced warnings');
            $dup_attachments = absint($wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DB::ident($wpdb->postmeta) . " WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
                $relative
            )));
            $check(1 === $dup_attachments, 're-import created a duplicate attachment (' . $dup_attachments . ' rows)');
            $dup_placements = absint($wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id = %d',
                $target_order_id
            )));
            $check(1 === $dup_placements, 're-import changed placement count (' . $dup_placements . ')');
        }
    }
} finally {
    // --- cleanup ---------------------------------------------------------------
    $mapped_grid_ids = $wpdb->get_col($wpdb->prepare(
        'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND mds3_entity_type = 'grid'",
        $source_prefix
    ));
    foreach ($wpdb->get_results($wpdb->prepare(
        'SELECT entity_type, legacy_id, mds3_id, mds3_entity_type FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s',
        $source_prefix
    ), ARRAY_A) ?: [] as $mapped) {
        $mds3_type = (string) $mapped['mds3_entity_type'];
        $mds3_id = absint($mapped['mds3_id']);
        if (!$mds3_id) {
            continue;
        }
        switch ($mds3_type) {
            case 'grid':
                $page_id = GridPostType::page_id($mds3_id);
                if ($page_id) {
                    wp_delete_post($page_id, true);
                }
                $wpdb->delete(DB::table('grids'), ['id' => $mds3_id]);
                break;
            case 'package':
                $wpdb->delete(DB::table('packages'), ['id' => $mds3_id]);
                break;
            case 'price_rule':
                $wpdb->delete(DB::table('price_rules'), ['id' => $mds3_id]);
                break;
            case 'order':
                $wpdb->delete(DB::table('order_items'), ['order_id' => $mds3_id]);
                $wpdb->delete(DB::table('placements'), ['order_id' => $mds3_id]);
                $wpdb->delete(DB::table('orders'), ['id' => $mds3_id]);
                break;
            case 'block':
                $wpdb->delete(DB::table('blocks'), ['id' => $mds3_id]);
                break;
        }
    }
    if ($mapped_grid_ids) {
        $grid_placeholders = implode(',', array_fill(0, count($mapped_grid_ids), '%d'));
        $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . DB::ident(DB::table('pages')) . " WHERE grid_id IN ({$grid_placeholders})",
            ...$mapped_grid_ids
        ));
    }
    $wpdb->delete(DB::table('migration_map'), ['source_prefix' => $source_prefix]);
    $wpdb->delete(DB::table('migration_runs'), ['source_prefix' => $source_prefix]);
    if ($attachment_id) {
        if ($file_path && is_file($file_path)) {
            @unlink($file_path);
        }
        wp_delete_post($attachment_id, true);
    }
    wp_delete_post($ad_id, true);
    foreach ($cols as $suffix => $_) {
        $wpdb->query('DROP TABLE IF EXISTS ' . DB::ident($source_prefix . $suffix));
    }
}

if ($fails) {
    throw new RuntimeException('Flattened media fixture failed: ' . wp_json_encode($fails));
}
echo wp_json_encode(['flattened_image_data_recovery' => 'pass']) . "\n";
