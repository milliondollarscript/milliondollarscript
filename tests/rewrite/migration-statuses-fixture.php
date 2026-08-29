<?php
/**
 * WP-CLI migration status-mapping fixture for MDS3.
 *
 * Verifies that MDS2 lifecycle statuses map to correct MDS3 statuses. MDS2 keeps
 * block rows 'ordered' for both live and expired orders, so the mapping must be
 * order-status-driven:
 *
 *   confirmed (live pixel)  -> order paid, block sold, placement active
 *   new (unpaid)            -> order pending_payment, block reserved, placement pending
 *   expired (dead term)     -> order expired, block available (released), placement cancelled
 *
 * Run with:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/migration-statuses-fixture.php
 */

use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$source_prefix = $wpdb->prefix . 'mdsstatus_';
$charset = $wpdb->get_charset_collate();

// --- Static mapping contract -------------------------------------------------

$expected_order_status = [
    'confirmed' => 'paid',
    'paid' => 'paid',
    'completed' => 'paid',
    'renew_paid' => 'paid',
    'new' => 'pending_payment',
    'pending' => 'pending_payment',
    'expired' => 'expired',
    'renew_wait' => 'expired',
    'cancelled' => 'cancelled',
    'denied' => 'denied',
    'deleted' => 'deleted',
];
foreach ($expected_order_status as $legacy => $expected) {
    $actual = Importer::order_status($legacy);
    if ($actual !== $expected) {
        throw new RuntimeException("Expected legacy order status '{$legacy}' to map to '{$expected}', got '{$actual}'.");
    }
}
if ('pending_payment' !== Importer::order_status('')) {
    throw new RuntimeException('Expected an empty legacy order status to fall back to pending_payment.');
}

$expected_block_status = [
    ['ordered', 'confirmed', 'sold'],
    ['ordered', 'paid', 'sold'],
    ['ordered', 'completed', 'sold'],
    ['ordered', 'expired', 'available'],
    ['ordered', 'renew_wait', 'available'],
    ['ordered', 'cancelled', 'available'],
    ['ordered', 'new', 'reserved'],
    ['ordered', 'pending', 'reserved'],
    ['sold', '', 'sold'],
    ['reserved', '', 'reserved'],
    ['nfs', '', 'unavailable'],
    ['free', '', 'available'],
    ['', '', 'available'],
];
foreach ($expected_block_status as [$legacy, $order, $expected]) {
    $actual = Importer::block_status($legacy, $order);
    if ($actual !== $expected) {
        throw new RuntimeException("Expected block '{$legacy}' under order '{$order}' to map to '{$expected}', got '{$actual}'.");
    }
}

$expected_placement_status = [
    'confirmed' => 'active',
    'paid' => 'active',
    'new' => 'pending',
    'pending' => 'pending',
    'expired' => 'cancelled',
    'cancelled' => 'cancelled',
];
foreach ($expected_placement_status as $legacy => $expected) {
    $actual = Importer::placement_status($legacy);
    if ($actual !== $expected) {
        throw new RuntimeException("Expected legacy order '{$legacy}' to map to placement '{$expected}', got '{$actual}'.");
    }
}

// --- Source data --------------------------------------------------------------

foreach (['banners', 'blocks', 'orders'] as $suffix) {
    $wpdb->query('DROP TABLE IF EXISTS ' . DB::ident($source_prefix . $suffix));
}

$wpdb->query("CREATE TABLE " . DB::ident($source_prefix . 'banners') . " (
    banner_id INT NOT NULL AUTO_INCREMENT,
    grid_width INT NOT NULL DEFAULT 0,
    grid_height INT NOT NULL DEFAULT 0,
    days_expire MEDIUMINT(9) DEFAULT 0,
    price_per_block FLOAT NOT NULL DEFAULT 0,
    name VARCHAR(255) NOT NULL DEFAULT '',
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    publish_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    max_orders INT NOT NULL DEFAULT 0,
    block_width INT NOT NULL DEFAULT 10,
    block_height INT NOT NULL DEFAULT 10,
    grid_block TEXT NOT NULL,
    nfs_block LONGBLOB NOT NULL,
    tile TEXT NOT NULL,
    usr_grid_block TEXT NOT NULL,
    usr_nfs_block LONGBLOB NOT NULL,
    usr_ord_block TEXT NOT NULL,
    usr_res_block TEXT NOT NULL,
    usr_sel_block TEXT NOT NULL,
    usr_sol_block TEXT NOT NULL,
    max_blocks INT NOT NULL DEFAULT 0,
    min_blocks INT NOT NULL DEFAULT 0,
    date_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
    bgcolor VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
    auto_publish CHAR(1) NOT NULL DEFAULT 'N',
    auto_approve CHAR(1) NOT NULL DEFAULT 'N',
    nfs_covered CHAR(1) NOT NULL DEFAULT 'N',
    enabled CHAR(1) NOT NULL DEFAULT 'Y',
    time_stamp INT DEFAULT NULL,
    PRIMARY KEY (banner_id)
) {$charset}");

$wpdb->query("CREATE TABLE " . DB::ident($source_prefix . 'blocks') . " (
    block_id INT NOT NULL DEFAULT 0,
    user_id INT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT '',
    x INT NOT NULL DEFAULT 0,
    y INT NOT NULL DEFAULT 0,
    image_data TEXT NOT NULL,
    url VARCHAR(255) NOT NULL DEFAULT '',
    alt_text TEXT NOT NULL,
    file_name VARCHAR(255) NOT NULL DEFAULT '',
    mime_type VARCHAR(100) NOT NULL DEFAULT '',
    approved VARCHAR(1) NOT NULL DEFAULT '',
    published VARCHAR(1) NOT NULL DEFAULT '',
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    order_id INT NOT NULL DEFAULT 0,
    price FLOAT DEFAULT NULL,
    banner_id INT NOT NULL DEFAULT 1,
    ad_id INT NOT NULL DEFAULT 0,
    click_count INT NOT NULL DEFAULT 0,
    view_count INT NOT NULL DEFAULT 0,
    PRIMARY KEY (block_id, banner_id)
) {$charset}");

$wpdb->query("CREATE TABLE " . DB::ident($source_prefix . 'orders') . " (
    user_id INT NOT NULL DEFAULT 0,
    order_id INT NOT NULL AUTO_INCREMENT,
    blocks TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT '',
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    price FLOAT NOT NULL DEFAULT 0,
    quantity INT NOT NULL DEFAULT 0,
    banner_id INT NOT NULL DEFAULT 1,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    days_expire INT NOT NULL DEFAULT 0,
    date_published DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_stamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    package_id INT NOT NULL DEFAULT 0,
    ad_id INT DEFAULT NULL,
    approved VARCHAR(1) NOT NULL DEFAULT 'N',
    published VARCHAR(1) NOT NULL DEFAULT '',
    subscr_status VARCHAR(32) NOT NULL DEFAULT '',
    original_order_id INT DEFAULT NULL,
    previous_order_id INT NOT NULL DEFAULT 0,
    block_info LONGTEXT NOT NULL,
    order_in_progress VARCHAR(1) NOT NULL DEFAULT 'N',
    current_step INT NOT NULL DEFAULT 0,
    PRIMARY KEY (order_id)
) {$charset}");

// Media + advertiser post (reused across re-runs).
$upload = wp_upload_dir();
$image_dir = trailingslashit($upload['basedir']) . 'milliondollarscript/images';
wp_mkdir_p($image_dir);
$image_path = $image_dir . '/status-fixture-original.png';
if (!is_file($image_path)) {
    file_put_contents($image_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
}
$relative_image = 'milliondollarscript/images/status-fixture-original.png';
$attachment_id = absint($wpdb->get_var($wpdb->prepare("SELECT post_id FROM " . DB::ident($wpdb->postmeta) . " WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1", $relative_image)));
if (!$attachment_id) {
    $attachment_id = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => 'MDS3 Status Fixture Image',
        'post_status' => 'inherit',
    ], $image_path);
    update_post_meta($attachment_id, '_wp_attached_file', $relative_image);
}
require_once ABSPATH . 'wp-admin/includes/image.php';
$metadata = wp_generate_attachment_metadata($attachment_id, $image_path);
if (is_array($metadata)) {
    wp_update_attachment_metadata($attachment_id, $metadata);
}

$ad_post = get_page_by_path('mdsstatus-advertiser', OBJECT, 'mds-pixel');
$ad_id = $ad_post ? absint($ad_post->ID) : wp_insert_post([
    'post_type' => 'mds-pixel',
    'post_status' => 'publish',
    'post_title' => 'Status Fixture Advertiser',
    'post_name' => 'mdsstatus-advertiser',
]);
update_post_meta($ad_id, '_milliondollarscript_image', $attachment_id);
update_post_meta($ad_id, '_milliondollarscript_url', 'https://example.com/status-ad');
update_post_meta($ad_id, '_milliondollarscript_text', 'Status fixture popup text');

$wpdb->insert($source_prefix . 'banners', [
    'banner_id' => 70,
    'grid_width' => 20,
    'grid_height' => 20,
    'days_expire' => 30,
    'price_per_block' => 5,
    'name' => 'Status Fixture Grid',
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
    'max_blocks' => 10,
    'min_blocks' => 1,
    'bgcolor' => '#ffffff',
    'auto_publish' => 'Y',
    'auto_approve' => 'Y',
    'nfs_covered' => 'N',
    'enabled' => 'Y',
]);

// A live confirmed order, an unpaid new order, and a dead expired order.
$wpdb->insert($source_prefix . 'orders', [
    'order_id' => 60,
    'user_id' => 1,
    'blocks' => '200',
    'status' => 'confirmed',
    'order_date' => '2026-06-01 10:00:00',
    'date_published' => '2026-07-05 00:00:00',
    'price' => 10,
    'quantity' => 1,
    'banner_id' => 70,
    'currency' => 'USD',
    'days_expire' => 365,
    'package_id' => 0,
    'ad_id' => $ad_id,
    'approved' => 'Y',
    'published' => 'Y',
    'block_info' => '',
]);
$wpdb->insert($source_prefix . 'orders', [
    'order_id' => 61,
    'user_id' => 1,
    'blocks' => '201',
    'status' => 'new',
    'order_date' => '2026-08-20 09:00:00',
    'price' => 5,
    'quantity' => 1,
    'banner_id' => 70,
    'currency' => 'USD',
    'days_expire' => 30,
    'package_id' => 0,
    'ad_id' => $ad_id,
    'approved' => 'N',
    'published' => '',
    'block_info' => '',
]);
$wpdb->insert($source_prefix . 'orders', [
    'order_id' => 62,
    'user_id' => 1,
    'blocks' => '202',
    'status' => 'expired',
    'order_date' => '2025-01-01 00:00:00',
    'date_published' => '2025-01-05 00:00:00',
    'price' => 5,
    'quantity' => 1,
    'banner_id' => 70,
    'currency' => 'USD',
    'days_expire' => 30,
    'package_id' => 0,
    'ad_id' => $ad_id,
    'approved' => 'N',
    'published' => '',
    'block_info' => '',
]);

// MDS2 leaves confirmed AND expired blocks with status 'ordered'.
$status_fixture_blocks = [
    [200, 'ordered', 60, 'Y', 'Y', $image_path],
    [201, 'reserved', 61, 'N', '', ''],
    [202, 'ordered', 62, 'N', '', $image_path],
];
foreach ($status_fixture_blocks as [$block_id, $status, $order_id, $approved, $published, $file]) {
    $wpdb->insert($source_prefix . 'blocks', [
        'block_id' => $block_id,
        'user_id' => 1,
        'status' => $status,
        'x' => ($block_id % 20) * 10,
        'y' => intdiv($block_id, 20) * 10 + 100,
        'image_data' => '',
        'url' => 'https://example.com/status-ad',
        'alt_text' => 'Status fixture alt ' . $block_id,
        'file_name' => $file,
        'mime_type' => $file ? 'image/png' : '',
        'approved' => $approved,
        'published' => $published,
        'currency' => 'USD',
        'order_id' => $order_id,
        'price' => 5,
        'banner_id' => 70,
        'ad_id' => $ad_id,
        'click_count' => 0,
        'view_count' => 0,
    ]);
}

// --- Import --------------------------------------------------------------------

$result = (new Importer())->import($source_prefix);
if (is_wp_error($result)) {
    throw new RuntimeException('Status fixture import failed: ' . $result->get_error_message());
}

// --- Assertions ------------------------------------------------------------------

$mapped_block_id = static function ($legacy_key) use ($wpdb, $source_prefix) {
    return absint($wpdb->get_var($wpdb->prepare(
        'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND entity_type = %s AND legacy_id = %s AND mds3_entity_type = %s LIMIT 1',
        $source_prefix,
        'block',
        (string) $legacy_key,
        'block'
    )));
};
$order_status = static function ($legacy_id) use ($wpdb) {
    return (string) $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM " . DB::ident(DB::table('orders')) . " WHERE commerce_provider = 'legacy_mds2' AND commerce_order_id = %s LIMIT 1",
        (string) $legacy_id
    ));
};
$block_status = static function ($block_id) use ($wpdb) {
    return (string) $wpdb->get_var($wpdb->prepare('SELECT status FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d LIMIT 1', $block_id));
};
$placement_status = static function ($legacy_id) use ($wpdb) {
    return (string) $wpdb->get_var($wpdb->prepare(
        'SELECT p.status FROM ' . DB::ident(DB::table('placements')) . ' p JOIN ' . DB::ident(DB::table('orders')) . ' o ON o.id = p.order_id WHERE o.commerce_provider = %s AND o.commerce_order_id = %s LIMIT 1',
        'legacy_mds2',
        (string) $legacy_id
    ));
};

$block_200 = $mapped_block_id('70:200');
$block_201 = $mapped_block_id('70:201');
$block_202 = $mapped_block_id('70:202');

if (!$block_200 || !$block_201 || !$block_202) {
    throw new RuntimeException('Expected the status fixture blocks to be mapped.');
}

// Live confirmed order: pixel must be visible and the term must be active.
if ('paid' !== $order_status(60)) {
    throw new RuntimeException('Expected MDS2 confirmed order to import as paid, got ' . var_export($order_status(60), true) . '.');
}
if ('sold' !== $block_status($block_200)) {
    throw new RuntimeException('Expected the confirmed order block to import as sold, got ' . var_export($block_status($block_200), true) . '.');
}
if ('active' !== $placement_status(60)) {
    throw new RuntimeException('Expected the confirmed order placement to import as active, got ' . var_export($placement_status(60), true) . '.');
}

// Unpaid new order: held, visible for review, not yet live.
if ('pending_payment' !== $order_status(61)) {
    throw new RuntimeException('Expected MDS2 new order to import as pending_payment, got ' . var_export($order_status(61), true) . '.');
}
if ('reserved' !== $block_status($block_201)) {
    throw new RuntimeException('Expected the new order block to import as reserved, got ' . var_export($block_status($block_201), true) . '.');
}
if ('pending' !== $placement_status(61)) {
    throw new RuntimeException('Expected the new order placement to import as pending, got ' . var_export($placement_status(61), true) . '.');
}

// Expired order: released for resale, not rendered.
if ('expired' !== $order_status(62)) {
    throw new RuntimeException('Expected MDS2 expired order to import as expired, got ' . var_export($order_status(62), true) . '.');
}
if ('available' !== $block_status($block_202)) {
    throw new RuntimeException('Expected the expired order block to import as available, got ' . var_export($block_status($block_202), true) . '.');
}
if ('cancelled' !== $placement_status(62)) {
    throw new RuntimeException('Expected the expired order placement to import as cancelled, got ' . var_export($placement_status(62), true) . '.');
}

// Only the confirmed pixel is live on the grid.
$active_placements = (int) $wpdb->get_var(
    $wpdb->prepare(
        'SELECT COUNT(*) FROM ' . DB::ident(DB::table('placements')) . ' p JOIN ' . DB::ident(DB::table('orders')) . ' o ON o.id = p.order_id WHERE o.commerce_provider = %s AND o.commerce_order_id IN (\'60\',\'61\',\'62\') AND p.status = %s',
        'legacy_mds2',
        'active'
    )
);
if (1 !== $active_placements) {
    throw new RuntimeException('Expected exactly one active placement across the fixture orders, got ' . $active_placements . '.');
}

echo 'MDS3 migration status-mapping fixture passed.' . PHP_EOL;
