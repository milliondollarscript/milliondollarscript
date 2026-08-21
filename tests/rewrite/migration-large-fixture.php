<?php
/**
 * WP-CLI large migration fixture for MDS3 keyset batching.
 *
 * Run with:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/migration-large-fixture.php
 */

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Migration\DryRun;
use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Pages\PageRepository;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$source_prefix = $wpdb->prefix . 'mdslarge_';
$charset = $wpdb->get_charset_collate();
$row_count = 505;
$page_count = 24;
$legacy_banner_id = 77;
$fixture_page_ids = [];

$mapped_target_id = static function ($entity_type, $legacy_id, $mds3_entity_type) use ($wpdb, $source_prefix) {
    return absint($wpdb->get_var($wpdb->prepare(
        'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND entity_type = %s AND legacy_id = %s AND mds3_entity_type = %s LIMIT 1',
        $source_prefix,
        (string) $entity_type,
        (string) $legacy_id,
        (string) $mds3_entity_type
    )));
};

$mapped_count = static function ($entity_type, $mds3_entity_type) use ($wpdb, $source_prefix) {
    return (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND entity_type = %s AND mds3_entity_type = %s',
        $source_prefix,
        (string) $entity_type,
        (string) $mds3_entity_type
    ));
};

if (DB::table_exists(DB::table('migration_map'))) {
    $mapped_grid_ids = $wpdb->get_col($wpdb->prepare(
        'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND entity_type = %s AND mds3_entity_type = %s',
        $source_prefix,
        'banner',
        'grid'
    ));
    foreach ($mapped_grid_ids as $mapped_grid_id) {
        $mapped_grid_id = absint($mapped_grid_id);
        if (!$mapped_grid_id) {
            continue;
        }

        if (DB::table_exists(DB::table('orders')) && DB::table_exists(DB::table('order_items'))) {
            $mapped_order_ids = $wpdb->get_col($wpdb->prepare(
                'SELECT DISTINCT order_id FROM ' . DB::ident(DB::table('order_items')) . ' WHERE grid_id = %d',
                $mapped_grid_id
            ));
            foreach ($mapped_order_ids as $mapped_order_id) {
                (new BlockRepository())->release_by_order(absint($mapped_order_id));
                $wpdb->delete(DB::table('order_items'), ['order_id' => absint($mapped_order_id)]);
                if (DB::table_exists(DB::table('placements'))) {
                    $wpdb->delete(DB::table('placements'), ['order_id' => absint($mapped_order_id)]);
                }
                $wpdb->delete(DB::table('orders'), ['id' => absint($mapped_order_id)]);
            }
        }

        foreach (['placements', 'blocks', 'packages', 'price_rules'] as $fixture_table) {
            if (DB::table_exists(DB::table($fixture_table))) {
                $wpdb->delete(DB::table($fixture_table), ['grid_id' => $mapped_grid_id]);
            }
        }
        if (DB::table_exists(DB::table('grids'))) {
            $wpdb->delete(DB::table('grids'), ['id' => $mapped_grid_id]);
        }
    }

    $wpdb->delete(DB::table('migration_map'), ['source_prefix' => $source_prefix]);
}

if (DB::table_exists(DB::table('migration_runs'))) {
    $wpdb->delete(DB::table('migration_runs'), ['source_prefix' => $source_prefix]);
}

$old_pages = $wpdb->get_col(
    $wpdb->prepare(
        'SELECT ID FROM ' . DB::ident($wpdb->posts) . ' WHERE post_type = %s AND post_title LIKE %s',
        'page',
        $wpdb->esc_like('MDS2 Large Fixture Page ') . '%'
    )
);
foreach ($old_pages as $old_page_id) {
    if (DB::table_exists(DB::table('pages'))) {
        $wpdb->delete(DB::table('pages'), ['post_id' => absint($old_page_id)]);
    }
    wp_delete_post(absint($old_page_id), true);
}

$old_ads = $wpdb->get_col(
    $wpdb->prepare(
        'SELECT ID FROM ' . DB::ident($wpdb->posts) . ' WHERE post_title = %s',
        'MDS2 Large Fixture Ad'
    )
);
foreach ($old_ads as $old_ad_id) {
    wp_delete_post(absint($old_ad_id), true);
}

foreach (['banners', 'blocks', 'orders', 'packages', 'prices'] as $suffix) {
    $wpdb->query('DROP TABLE IF EXISTS ' . DB::ident($source_prefix . $suffix));
}

$wpdb->query('CREATE TABLE ' . DB::ident($source_prefix . 'banners') . " (
    banner_id INT NOT NULL AUTO_INCREMENT,
    grid_width INT NOT NULL DEFAULT 0,
    grid_height INT NOT NULL DEFAULT 0,
    days_expire MEDIUMINT(9) DEFAULT 0,
    price_per_block FLOAT NOT NULL DEFAULT 0,
    name VARCHAR(255) NOT NULL DEFAULT '',
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    publish_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    max_orders INT NOT NULL DEFAULT 0,
    block_width INT NOT NULL DEFAULT 1,
    block_height INT NOT NULL DEFAULT 1,
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
    auto_publish CHAR(1) NOT NULL DEFAULT 'Y',
    auto_approve CHAR(1) NOT NULL DEFAULT 'Y',
    nfs_covered CHAR(1) NOT NULL DEFAULT 'N',
    enabled CHAR(1) NOT NULL DEFAULT 'Y',
    PRIMARY KEY (banner_id)
) {$charset}");

$wpdb->query('CREATE TABLE ' . DB::ident($source_prefix . 'packages') . " (
    banner_id INT NOT NULL DEFAULT 0,
    days_expire INT NOT NULL DEFAULT 0,
    price FLOAT NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    package_id INT NOT NULL AUTO_INCREMENT,
    is_default VARCHAR(1) DEFAULT NULL,
    max_orders MEDIUMINT(9) NOT NULL DEFAULT 0,
    description VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (package_id)
) {$charset}");

$wpdb->query('CREATE TABLE ' . DB::ident($source_prefix . 'prices') . " (
    price_id INT NOT NULL AUTO_INCREMENT,
    banner_id INT NOT NULL DEFAULT 0,
    row_from INT NOT NULL DEFAULT 0,
    row_to INT NOT NULL DEFAULT 0,
    block_id_from INT NOT NULL DEFAULT 0,
    block_id_to INT NOT NULL DEFAULT 0,
    price FLOAT NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    color VARCHAR(50) NOT NULL DEFAULT '',
    col_from INT DEFAULT NULL,
    col_to INT DEFAULT NULL,
    PRIMARY KEY (price_id)
) {$charset}");

$wpdb->query('CREATE TABLE ' . DB::ident($source_prefix . 'orders') . " (
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
    approved VARCHAR(1) NOT NULL DEFAULT 'Y',
    published VARCHAR(1) NOT NULL DEFAULT 'Y',
    subscr_status VARCHAR(32) NOT NULL DEFAULT '',
    original_order_id INT DEFAULT NULL,
    previous_order_id INT NOT NULL DEFAULT 0,
    block_info LONGTEXT NOT NULL,
    order_in_progress VARCHAR(1) NOT NULL DEFAULT 'N',
    current_step INT NOT NULL DEFAULT 0,
    PRIMARY KEY (order_id)
) {$charset}");

$wpdb->query('CREATE TABLE ' . DB::ident($source_prefix . 'blocks') . " (
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
    approved VARCHAR(1) NOT NULL DEFAULT 'Y',
    published VARCHAR(1) NOT NULL DEFAULT 'Y',
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    order_id INT NOT NULL DEFAULT 0,
    price FLOAT DEFAULT NULL,
    banner_id INT NOT NULL DEFAULT 1,
    ad_id INT NOT NULL DEFAULT 0,
    click_count INT NOT NULL DEFAULT 0,
    view_count INT NOT NULL DEFAULT 0,
    PRIMARY KEY (block_id, banner_id)
) {$charset}");

$upload = wp_upload_dir();
$image_dir = trailingslashit($upload['basedir']) . 'milliondollarscript/images';
wp_mkdir_p($image_dir);
$image_path = $image_dir . '/large-fixture-original.png';
file_put_contents($image_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
$relative_image = 'milliondollarscript/images/large-fixture-original.png';
$attachment_id = absint($wpdb->get_var($wpdb->prepare('SELECT post_id FROM ' . DB::ident($wpdb->postmeta) . ' WHERE meta_key = %s AND meta_value = %s LIMIT 1', '_wp_attached_file', $relative_image)));
if (!$attachment_id) {
    $attachment_id = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => 'MDS2 Large Fixture Original',
        'post_status' => 'inherit',
    ], $image_path);
    update_post_meta($attachment_id, '_wp_attached_file', $relative_image);
}
require_once ABSPATH . 'wp-admin/includes/image.php';
$metadata = wp_generate_attachment_metadata($attachment_id, $image_path);
if (is_array($metadata)) {
    wp_update_attachment_metadata($attachment_id, $metadata);
}

$ad_id = wp_insert_post([
    'post_type' => 'post',
    'post_status' => 'publish',
    'post_title' => 'MDS2 Large Fixture Ad',
]);
update_post_meta($ad_id, '_milliondollarscript_image', $attachment_id);
update_post_meta($ad_id, '_milliondollarscript_url', 'https://example.com/large-fixture');
update_post_meta($ad_id, '_milliondollarscript_text', 'Large fixture alt text');

$page_types = PageRepository::TYPES;
for ($i = 1; $i <= $page_count; $i++) {
    $type = $page_types[($i - 1) % count($page_types)];
    $fixture_page_ids[] = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'MDS2 Large Fixture Page ' . $i,
        'post_content' => '[milliondollarscript id="' . $legacy_banner_id . '" type="' . esc_attr($type) . '"]',
    ]);
}

$wpdb->insert($source_prefix . 'banners', [
    'banner_id' => $legacy_banner_id,
    'grid_width' => 600,
    'grid_height' => 2,
    'days_expire' => 60,
    'price_per_block' => 1.25,
    'name' => 'Large Fixture Legacy Grid',
    'currency' => 'USD',
    'max_orders' => 0,
    'block_width' => 1,
    'block_height' => 1,
    'grid_block' => 'grid',
    'nfs_block' => '',
    'tile' => '',
    'usr_grid_block' => '',
    'usr_nfs_block' => '',
    'usr_ord_block' => '',
    'usr_res_block' => '',
    'usr_sel_block' => '',
    'usr_sol_block' => '',
    'max_blocks' => 0,
    'min_blocks' => 1,
    'bgcolor' => '#ffffff',
    'auto_publish' => 'Y',
    'auto_approve' => 'Y',
    'nfs_covered' => 'N',
    'enabled' => 'Y',
]);

for ($i = 1; $i <= $row_count; $i++) {
    $order_id = 1000 + $i;
    $block_id = $i;
    $x = ($block_id - 1) % 600;
    $y = intdiv($block_id - 1, 600);

    $wpdb->insert($source_prefix . 'packages', [
        'package_id' => $i,
        'banner_id' => $legacy_banner_id,
        'days_expire' => 30 + ($i % 30),
        'price' => 5 + ($i % 7),
        'currency' => 'USD',
        'is_default' => 1 === $i ? 'Y' : 'N',
        'max_orders' => 0,
        'description' => 'Large fixture package ' . $i,
    ]);

    $wpdb->insert($source_prefix . 'prices', [
        'price_id' => $i,
        'banner_id' => $legacy_banner_id,
        'row_from' => $y,
        'row_to' => $y,
        'block_id_from' => $block_id,
        'block_id_to' => $block_id,
        'price' => 1.5 + (($i % 5) / 10),
        'currency' => 'USD',
        'color' => '#0f766e',
        'col_from' => $x,
        'col_to' => $x,
    ]);

    $wpdb->insert($source_prefix . 'orders', [
        'order_id' => $order_id,
        'user_id' => 1,
        'blocks' => (string) $block_id,
        'status' => 'paid',
        'order_date' => gmdate('Y-m-d H:i:s', strtotime('2025-02-01 00:00:00') + $i),
        'price' => 2,
        'quantity' => 1,
        'banner_id' => $legacy_banner_id,
        'currency' => 'USD',
        'days_expire' => 30,
        'package_id' => $i,
        'ad_id' => $ad_id,
        'approved' => 'Y',
        'published' => 'Y',
        'block_info' => '',
    ]);

    $wpdb->insert($source_prefix . 'blocks', [
        'block_id' => $block_id,
        'user_id' => 1,
        'status' => 'sold',
        'x' => $x,
        'y' => $y,
        'image_data' => '',
        'url' => 'https://example.com/large-fixture/' . $i,
        'alt_text' => 'Large fixture block ' . $i,
        'file_name' => $image_path,
        'mime_type' => 'image/png',
        'approved' => 'Y',
        'published' => 'Y',
        'currency' => 'USD',
        'order_id' => $order_id,
        'price' => 2,
        'banner_id' => $legacy_banner_id,
        'ad_id' => $ad_id,
        'click_count' => $i % 17,
        'view_count' => $i % 29,
    ]);
}

$started = microtime(true);
$dry_run = (new DryRun())->report($source_prefix);
foreach (['packages', 'prices', 'orders', 'blocks'] as $suffix) {
    if ($row_count !== (int) ($dry_run['tables'][$suffix]['rows'] ?? 0)) {
        throw new RuntimeException('Large dry run row count mismatch for ' . $suffix . '.');
    }
}

$importer = new Importer();
$result = $importer->start_resumable($source_prefix, ['batch_size' => 75, 'time_budget' => 3]);
if (is_wp_error($result)) {
    throw new RuntimeException($result->get_error_message());
}
if (empty($result['run_id'])) {
    throw new RuntimeException('Large resumable migration did not create a run.');
}

$run_id = absint($result['run_id']);
$steps = 0;
$paused = (new Importer())->pause_resumable($run_id);
if (is_wp_error($paused) || 'paused' !== (string) ($paused['status'] ?? '')) {
    throw new RuntimeException('Large resumable migration did not pause correctly.');
}

$still_paused = (new Importer())->run_resumable_step($run_id, ['batch_size' => 75, 'time_budget' => 3]);
if (is_wp_error($still_paused) || 'paused' !== (string) ($still_paused['status'] ?? '')) {
    throw new RuntimeException('Paused large resumable migration advanced without resume.');
}

$result = (new Importer())->run_resumable_step($run_id, ['resume' => true, 'batch_size' => 75, 'time_budget' => 3]);
if (is_wp_error($result)) {
    throw new RuntimeException($result->get_error_message());
}
$steps++;

while (empty($result['completed'])) {
    $result = (new Importer())->run_resumable_step($run_id, ['batch_size' => 75, 'time_budget' => 3]);
    if (is_wp_error($result)) {
        throw new RuntimeException($result->get_error_message());
    }
    $steps++;
    if ($steps > 80) {
        throw new RuntimeException('Large resumable migration exceeded expected batch count: ' . wp_json_encode($result));
    }
}

if ($steps < 3) {
    throw new RuntimeException('Large resumable migration finished without proving multiple batches.');
}

$imported = is_array($result['totals'] ?? null) ? $result['totals'] : [];
if (0 !== (int) ($imported['warnings'] ?? 0)) {
    throw new RuntimeException('Large migration fixture produced warnings: ' . wp_json_encode($result));
}

$grid_id = $mapped_target_id('banner', (string) $legacy_banner_id, 'grid');
if (!$grid_id) {
    throw new RuntimeException('Large fixture grid was not mapped.');
}

$expected = [
    'packages' => $row_count,
    'price_rules' => $row_count,
    'orders' => $row_count,
    'blocks' => $row_count,
    'order_items' => $row_count,
    'placements' => $row_count,
];
foreach ($expected as $key => $total) {
    if ($total !== (int) ($imported[$key] ?? 0)) {
        throw new RuntimeException('Large import count mismatch for ' . $key . ': ' . wp_json_encode($imported));
    }
}

foreach ([1, 500, 501, 505] as $index) {
    $order_id = 1000 + $index;
    if (!$mapped_target_id('package', (string) $index, 'package')) {
        throw new RuntimeException('Large fixture package map missing at boundary ' . $index . '.');
    }
    if (!$mapped_target_id('price', (string) $index, 'price_rule')) {
        throw new RuntimeException('Large fixture price-rule map missing at boundary ' . $index . '.');
    }
    if (!$mapped_target_id('order', (string) $order_id, 'order')) {
        throw new RuntimeException('Large fixture order map missing at boundary ' . $order_id . '.');
    }
    if (!$mapped_target_id('block', $legacy_banner_id . ':' . $index, 'block')) {
        throw new RuntimeException('Large fixture block map missing at boundary ' . $index . '.');
    }
    if (!$mapped_target_id('placement', (string) $order_id, 'placement')) {
        throw new RuntimeException('Large fixture placement map missing at boundary ' . $order_id . '.');
    }
}

if (
    $row_count !== $mapped_count('package', 'package') ||
    $row_count !== $mapped_count('price', 'price_rule') ||
    $row_count !== $mapped_count('order', 'order') ||
    $row_count !== $mapped_count('block', 'block') ||
    $row_count !== $mapped_count('placement', 'placement')
) {
    throw new RuntimeException('Large migration map totals did not match expected batch counts.');
}

$last_order_id = $mapped_target_id('order', '1505', 'order');
$last_order = (new OrderRepository())->find($last_order_id);
$last_items = (new OrderRepository())->items($last_order_id);
if (!$last_order || 'paid' !== (string) ($last_order['status'] ?? '') || 1 !== count($last_items)) {
    throw new RuntimeException('Large fixture last order relationship did not import correctly.');
}

$last_block_id = $mapped_target_id('block', $legacy_banner_id . ':505', 'block');
$last_block = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d', $last_block_id), ARRAY_A);
if (!$last_block || 'sold' !== (string) ($last_block['status'] ?? '') || $last_order_id !== absint($last_block['order_id'] ?? 0)) {
    throw new RuntimeException('Large fixture last block relationship did not import correctly.');
}

$first_placement = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id = %d', $mapped_target_id('order', '1001', 'order')), ARRAY_A);
if (!$first_placement || 'https://example.com/large-fixture/1' !== (string) ($first_placement['link_url'] ?? '')) {
    throw new RuntimeException('Large fixture placement URL did not preserve first block data.');
}

$verified_pages = 0;
foreach ($fixture_page_ids as $fixture_page_id) {
    $fixture_page_id = absint($fixture_page_id);
    if (!$fixture_page_id) {
        continue;
    }
    $content = (string) get_post_field('post_content', $fixture_page_id);
    $mapped_page = $mapped_target_id('page', (string) $fixture_page_id, 'page');
    if ($mapped_page && false !== strpos($content, '[mds3_page') && false !== strpos($content, 'grid_id="' . $grid_id . '"')) {
        $verified_pages++;
    }
}
if ($page_count !== $verified_pages) {
    throw new RuntimeException('Large fixture pages did not all migrate to the mapped MDS3 grid.');
}

echo wp_json_encode([
    'dry_run' => [
        'orders' => $dry_run['tables']['orders']['rows'] ?? 0,
        'blocks' => $dry_run['tables']['blocks']['rows'] ?? 0,
        'packages' => $dry_run['tables']['packages']['rows'] ?? 0,
        'price_rules' => $dry_run['tables']['prices']['rows'] ?? 0,
        'pages_detected' => $dry_run['pages']['count'] ?? 0,
    ],
    'imported' => $imported,
    'migration_steps' => $steps,
    'mapped' => [
        'packages' => $mapped_count('package', 'package'),
        'price_rules' => $mapped_count('price', 'price_rule'),
        'orders' => $mapped_count('order', 'order'),
        'blocks' => $mapped_count('block', 'block'),
        'placements' => $mapped_count('placement', 'placement'),
        'verified_fixture_pages' => $verified_pages,
    ],
    'elapsed_seconds' => round(microtime(true) - $started, 3),
], JSON_PRETTY_PRINT) . "\n";
