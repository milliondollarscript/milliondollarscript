<?php
/**
 * WP-CLI migration status-mapping fixture for MDS3.
 *
 * Verifies that MDS2 lifecycle statuses map to correct MDS3 statuses. MDS2 keeps
 * block rows 'ordered' for both live and expired orders, so the mapping must be
 * order-status-driven:
 *
 *   confirmed + recorded payment -> order paid, block sold, placement active
 *   confirmed, nothing recorded  -> order pending_payment, block reserved, placement pending
 *   new (unpaid)                 -> order pending_payment, block reserved, placement pending
 *   expired (dead term)          -> order expired, block available (released), placement cancelled
 *
 * "confirmed" is not a paid state in Million Dollar Script 2 - it is what a customer's
 * confirmation produces before payment - so an order left there arrives owing money unless
 * that site recorded payment: a row in its transactions table, or a WooCommerce order it
 * linked from the ad (see ReadsLegacyOrderPayments). Both paths are exercised here, the
 * second only on a site that has WooCommerce.
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
    'confirmed' => 'pending_payment',
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

// An order the older site left at the confirmation step is paid only when that site
// recorded payment for it.
if ('paid' !== Importer::order_status('confirmed', true)) {
    throw new RuntimeException('Expected a confirmed order with recorded payment to map to paid.');
}
if ('pending_payment' !== Importer::order_status('confirmed', false)) {
    throw new RuntimeException('Expected a confirmed order without recorded payment to stay awaiting payment.');
}
if ('paid' !== Importer::order_status('completed', false)) {
    throw new RuntimeException('Expected a completed order to map to paid whatever the payment records say.');
}

$expected_block_status = [
    ['ordered', 'confirmed', 'reserved'],
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
if ('sold' !== Importer::block_status('ordered', 'confirmed', true)) {
    throw new RuntimeException('Expected a block whose order was confirmed and paid to read as sold.');
}

$expected_placement_status = [
    'confirmed' => 'pending',
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
if ('active' !== Importer::placement_status('confirmed', true)) {
    throw new RuntimeException('Expected the creative of a confirmed, paid order to stay live.');
}

// --- Source data --------------------------------------------------------------

foreach (['banners', 'blocks', 'orders', 'transactions'] as $suffix) {
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

$wpdb->query("CREATE TABLE " . DB::ident($source_prefix . 'transactions') . " (
    transaction_id INT NOT NULL AUTO_INCREMENT,
    date DATETIME DEFAULT CURRENT_TIMESTAMP,
    order_id INT NOT NULL DEFAULT 0,
    type VARCHAR(32) NOT NULL DEFAULT '',
    amount FLOAT NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    txn_id VARCHAR(128) NOT NULL DEFAULT '',
    reason VARCHAR(255) NOT NULL DEFAULT '',
    origin VARCHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY (transaction_id)
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

/**
 * Another ad in the older site's shape - same picture, a different post - so one order
 * can carry recorded payment and another can carry none.
 */
$status_fixture_ad = static function ($slug, $title) use ($attachment_id) {
    $existing = get_page_by_path($slug, OBJECT, 'mds-pixel');
    $id = $existing ? absint($existing->ID) : absint(wp_insert_post([
        'post_type' => 'mds-pixel',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => $slug,
    ]));
    if (!$id) {
        throw new RuntimeException('Could not create the fixture ad post ' . $slug . '.');
    }
    update_post_meta($id, '_milliondollarscript_image', $attachment_id);
    update_post_meta($id, '_milliondollarscript_url', 'https://example.com/status-ad');
    update_post_meta($id, '_milliondollarscript_text', 'Status fixture popup text');

    return $id;
};

$fixture_ad_posts = [$ad_id];
$ad_id_unpaid = $status_fixture_ad('mdsstatus-advertiser-unpaid', 'Status Fixture Unpaid Advertiser');
$fixture_ad_posts[] = $ad_id_unpaid;
$ad_id_wc = 0;
if (function_exists('wc_create_order')) {
    $ad_id_wc = $status_fixture_ad('mdsstatus-advertiser-woocommerce', 'Status Fixture WooCommerce Advertiser');
    $fixture_ad_posts[] = $ad_id_wc;
}

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

// An order the older site left at the confirmation step with nothing recorded as paid:
// the advertiser had confirmed the ad, and the money was never taken.
$wpdb->insert($source_prefix . 'orders', [
    'order_id' => 63,
    'user_id' => 1,
    'blocks' => '203',
    'status' => 'confirmed',
    'order_date' => '2026-09-02 11:00:00',
    'date_published' => '2026-09-02 11:05:00',
    'price' => 5,
    'quantity' => 1,
    'banner_id' => 70,
    'currency' => 'USD',
    'days_expire' => 365,
    'package_id' => 0,
    'ad_id' => $ad_id_unpaid,
    'approved' => 'Y',
    'published' => 'Y',
    'block_info' => '',
]);

// The same confirmation step, with the payment that site recorded in its own
// transactions table: that order is paid.
$wpdb->insert($source_prefix . 'transactions', [
    'date' => '2026-06-01 10:30:00',
    'order_id' => 60,
    'type' => 'payment',
    'amount' => 10,
    'currency' => 'USD',
    'txn_id' => 'status-fixture-charge',
    'reason' => '',
    'origin' => 'fixture',
]);

// A third order at the confirmation step, paid the way most of these sites take payment -
// through WooCommerce, whose order id the older plugin stores on the ad post.
$wc_order_id = 0;
if ($ad_id_wc) {
    $wc_order = wc_create_order();
    if (is_wp_error($wc_order)) {
        throw new RuntimeException('Could not create the WooCommerce fixture order: ' . $wc_order->get_error_message());
    }
    $wc_order->set_status('completed');
    $wc_order->save();
    $wc_order_id = absint($wc_order->get_id());
    update_post_meta($ad_id_wc, '_wc_order_id', $wc_order_id);

    $wpdb->insert($source_prefix . 'orders', [
        'order_id' => 64,
        'user_id' => 1,
        'blocks' => '204',
        'status' => 'confirmed',
        'order_date' => '2026-09-03 09:00:00',
        'date_published' => '2026-09-03 09:10:00',
        'price' => 5,
        'quantity' => 1,
        'banner_id' => 70,
        'currency' => 'USD',
        'days_expire' => 365,
        'package_id' => 0,
        'ad_id' => $ad_id_wc,
        'approved' => 'Y',
        'published' => 'Y',
        'block_info' => '',
    ]);
}

// MDS2 leaves confirmed AND expired blocks with status 'ordered'.
$status_fixture_blocks = [
    [200, 'ordered', 60, 'Y', 'Y', $image_path, $ad_id],
    [201, 'reserved', 61, 'N', '', '', $ad_id],
    [202, 'ordered', 62, 'N', '', $image_path, $ad_id],
    [203, 'ordered', 63, 'Y', 'Y', $image_path, $ad_id_unpaid],
];
if ($ad_id_wc) {
    $status_fixture_blocks[] = [204, 'ordered', 64, 'Y', 'Y', $image_path, $ad_id_wc];
}
foreach ($status_fixture_blocks as $fixture_block) {
    list($block_id, $status, $order_id, $approved, $published, $file, $block_ad_id) = $fixture_block;
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
        'ad_id' => $block_ad_id,
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
$order_metadata = static function ($legacy_id) use ($wpdb) {
    $json = (string) $wpdb->get_var($wpdb->prepare(
        "SELECT metadata FROM " . DB::ident(DB::table('orders')) . " WHERE commerce_provider = 'legacy_mds2' AND commerce_order_id = %s LIMIT 1",
        (string) $legacy_id
    ));
    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : [];
};

$block_200 = $mapped_block_id('70:200');
$block_201 = $mapped_block_id('70:201');
$block_202 = $mapped_block_id('70:202');
$block_203 = $mapped_block_id('70:203');
$block_204 = $ad_id_wc ? $mapped_block_id('70:204') : 0;

if (!$block_200 || !$block_201 || !$block_202 || !$block_203 || ($ad_id_wc && !$block_204)) {
    throw new RuntimeException('Expected the status fixture blocks to be mapped.');
}

// A confirmed order the older site recorded payment for: pixel live, term active.
if ('paid' !== $order_status(60)) {
    throw new RuntimeException('Expected a confirmed order with a recorded charge to import as paid, got ' . var_export($order_status(60), true) . '.');
}
if ('sold' !== $block_status($block_200)) {
    throw new RuntimeException('Expected the confirmed order block to import as sold, got ' . var_export($block_status($block_200), true) . '.');
}
if ('active' !== $placement_status(60)) {
    throw new RuntimeException('Expected the confirmed order placement to import as active, got ' . var_export($placement_status(60), true) . '.');
}

// An order left at the confirmation step with nothing recorded as paid stays one the
// customer can still finish: the money is not claimed, and the cell is not sold.
if ('pending_payment' !== $order_status(63)) {
    throw new RuntimeException('Expected a confirmed order with no recorded payment to import as pending_payment, got ' . var_export($order_status(63), true) . '.');
}
if ('reserved' !== $block_status($block_203)) {
    throw new RuntimeException('Expected the unpaid confirmed order block to import as reserved, got ' . var_export($block_status($block_203), true) . '.');
}
if ('pending' !== $placement_status(63)) {
    throw new RuntimeException('Expected the unpaid confirmed order placement to import as pending, got ' . var_export($placement_status(63), true) . '.');
}
if (isset($order_metadata(63)['paid_at'])) {
    throw new RuntimeException('An order nothing was recorded as paid for still carries a paid date.');
}

// The same step paid through WooCommerce is a paid order, because that is the payment the
// older site recorded.
if ($ad_id_wc) {
    if ('paid' !== $order_status(64)) {
        throw new RuntimeException('Expected a confirmed order with a paid WooCommerce order to import as paid, got ' . var_export($order_status(64), true) . '.');
    }
    if ('sold' !== $block_status($block_204)) {
        throw new RuntimeException('Expected the WooCommerce-paid block to import as sold, got ' . var_export($block_status($block_204), true) . '.');
    }
    if ('active' !== $placement_status(64)) {
        throw new RuntimeException('Expected the WooCommerce-paid placement to import as active, got ' . var_export($placement_status(64), true) . '.');
    }
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

// Only the paid creatives are live on the grid: the recorded charge, and the WooCommerce
// payment where that path ran. The order left at the confirmation step is not.
$expected_active = $ad_id_wc ? 2 : 1;
$active_placements = (int) $wpdb->get_var(
    $wpdb->prepare(
        'SELECT COUNT(*) FROM ' . DB::ident(DB::table('placements')) . ' p JOIN ' . DB::ident(DB::table('orders')) . ' o ON o.id = p.order_id WHERE o.commerce_provider = %s AND o.commerce_order_id IN (\'60\',\'61\',\'62\',\'63\',\'64\') AND p.status = %s',
        'legacy_mds2',
        'active'
    )
);
if ($expected_active !== $active_placements) {
    throw new RuntimeException('Expected ' . $expected_active . ' active placements across the fixture orders, got ' . $active_placements . '.');
}

// The report says what the site now holds: orders counted by the state they arrived in,
// and the ones that came from the confirmation step named separately.
$order_outcomes = (array) ($result['verification']['order_status_outcomes'] ?? []);
$counts = isset($order_outcomes['counts']) && is_array($order_outcomes['counts']) ? $order_outcomes['counts'] : [];
$expected_counts = ['paid' => 1, 'pending_payment' => 2, 'expired' => 1];
if ($ad_id_wc) {
    $expected_counts['paid'] = 2;
}
foreach ($expected_counts as $expected_status => $expected_count) {
    if (absint($counts[$expected_status] ?? 0) !== $expected_count) {
        throw new RuntimeException('The migration report did not count ' . $expected_count . " orders as '{$expected_status}': " . wp_json_encode($counts));
    }
}
if (1 !== absint($order_outcomes['confirmed_without_payment'] ?? 0)) {
    throw new RuntimeException('The migration report did not single out the order left unpaid at the confirmation step: ' . wp_json_encode($order_outcomes));
}

if ($wc_order_id) {
    wp_delete_post($wc_order_id, true);
}

echo 'MDS3 migration status-mapping fixture passed.' . ($ad_id_wc ? ' (WooCommerce path included)' : ' (no WooCommerce on this site: the WooCommerce path was not exercised)') . PHP_EOL;
