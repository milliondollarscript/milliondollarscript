<?php
/**
 * WP-CLI migration fixture for MDS3.
 *
 * Run with:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/migration-fixture.php
 */

use MillionDollarScript\V3\Media\OriginalImage;
use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Grid\GridAjax;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Migration\DryRun;
use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Orders\ReservationService;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$source_prefix = $wpdb->prefix . 'mdsfixture_';
$charset = $wpdb->get_charset_collate();
$legacy_long_title = "This legacy advertiser title contains the complete sponsor description, campaign background, and visitor guidance that should remain readable as body content instead of becoming one oversized heading.\nIt also includes a second line from the old single-line field workflow.";

$runtime_fixture_emails = ['zone@example.com', 'nfs@example.com', 'released@example.com', 'package@example.com', 'package-limit@example.com', 'grid-limit@example.com', 'gap-selection@example.com'];
if (DB::table_exists(DB::table('orders'))) {
    $email_placeholders = implode(',', array_fill(0, count($runtime_fixture_emails), '%s'));
    $runtime_order_ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . DB::ident(DB::table('orders')) . " WHERE email IN ({$email_placeholders})", $runtime_fixture_emails));
    foreach ($runtime_order_ids as $runtime_order_id) {
        (new BlockRepository())->release_by_order(absint($runtime_order_id));
        if (DB::table_exists(DB::table('order_items'))) {
            $wpdb->delete(DB::table('order_items'), ['order_id' => absint($runtime_order_id)]);
        }
        if (DB::table_exists(DB::table('placements'))) {
            $wpdb->delete(DB::table('placements'), ['order_id' => absint($runtime_order_id)]);
        }
        $wpdb->delete(DB::table('orders'), ['id' => absint($runtime_order_id)]);
    }
}

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
                if (DB::table_exists(DB::table('order_items'))) {
                    $wpdb->delete(DB::table('order_items'), ['order_id' => absint($mapped_order_id)]);
                }
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
    }
}

$fixture_titles = ['Legacy Fixture Grid', 'Legacy Fixture Secondary Grid', 'Legacy Fixture Order', 'Legacy Fixture Block', 'Legacy Fixture List Without Grid', 'Foreign Legacy Fixture Page', 'Native MDS3 Fixture Page', 'Native MDS3 Unmanaged Fixture Page', 'MDS2 Fixture Ad', $legacy_long_title];
$placeholders = implode(',', array_fill(0, count($fixture_titles), '%s'));
$old_fixture_posts = $wpdb->get_col($wpdb->prepare('SELECT ID FROM ' . DB::ident($wpdb->posts) . " WHERE post_title IN ({$placeholders})", $fixture_titles));
foreach ($old_fixture_posts as $old_post_id) {
    if (DB::table_exists(DB::table('pages'))) {
        $wpdb->delete(DB::table('pages'), ['post_id' => absint($old_post_id)]);
    }
    wp_delete_post(absint($old_post_id), true);
}
if (DB::table_exists(DB::table('migration_map'))) {
    $wpdb->delete(DB::table('migration_map'), [
        'source_prefix' => $source_prefix,
    ]);
}

foreach (['banners', 'blocks', 'orders', 'packages', 'prices', 'transactions', 'clicks', 'views'] as $suffix) {
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

$wpdb->query("CREATE TABLE " . DB::ident($source_prefix . 'packages') . " (
    banner_id INT NOT NULL DEFAULT 0,
    days_expire INT NOT NULL DEFAULT 0,
    price FLOAT NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT '',
    package_id INT NOT NULL AUTO_INCREMENT,
    is_default VARCHAR(1) DEFAULT NULL,
    max_orders MEDIUMINT(9) NOT NULL DEFAULT 0,
    description VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (package_id)
) {$charset}");

$wpdb->query("CREATE TABLE " . DB::ident($source_prefix . 'prices') . " (
    price_id INT NOT NULL AUTO_INCREMENT,
    banner_id INT NOT NULL DEFAULT 0,
    row_from INT NOT NULL DEFAULT 0,
    row_to INT NOT NULL DEFAULT 0,
    block_id_from INT NOT NULL DEFAULT 0,
    block_id_to INT NOT NULL DEFAULT 0,
    price FLOAT NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT '',
    color VARCHAR(50) NOT NULL DEFAULT '',
    col_from INT DEFAULT NULL,
    col_to INT DEFAULT NULL,
    PRIMARY KEY (price_id)
) {$charset}");

$wpdb->query('CREATE TABLE ' . DB::ident($source_prefix . 'transactions') . " (transaction_id INT NOT NULL AUTO_INCREMENT, order_id INT NOT NULL DEFAULT 0, PRIMARY KEY (transaction_id)) {$charset}");
$wpdb->query('CREATE TABLE ' . DB::ident($source_prefix . 'clicks') . " (banner_id INT NOT NULL, block_id INT NOT NULL, user_id INT NOT NULL, date DATE DEFAULT '1970-01-01', clicks INT NOT NULL, PRIMARY KEY (banner_id, block_id, date)) {$charset}");
$wpdb->query('CREATE TABLE ' . DB::ident($source_prefix . 'views') . " (banner_id INT NOT NULL, block_id INT NOT NULL, user_id INT NOT NULL, date DATE DEFAULT '1970-01-01', views INT NOT NULL, PRIMARY KEY (banner_id, block_id, date)) {$charset}");

$upload = wp_upload_dir();
$image_dir = trailingslashit($upload['basedir']) . 'milliondollarscript/images';
wp_mkdir_p($image_dir);
$image_path = $image_dir . '/fixture-original.png';
if (function_exists('imagecreatetruecolor')) {
    $image = imagecreatetruecolor(96, 96);
    $blue = imagecolorallocate($image, 37, 99, 235);
    $green = imagecolorallocate($image, 15, 118, 110);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefilledrectangle($image, 0, 0, 95, 95, $blue);
    imagefilledrectangle($image, 48, 0, 95, 95, $green);
    imageline($image, 0, 0, 95, 95, $white);
    imageline($image, 95, 0, 0, 95, $white);
    imagepng($image, $image_path);
    imagedestroy($image);
} else {
    file_put_contents($image_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
}

$relative_image = 'milliondollarscript/images/fixture-original.png';
$attachment_id = absint($wpdb->get_var($wpdb->prepare("SELECT post_id FROM " . DB::ident($wpdb->postmeta) . " WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1", $relative_image)));
if (!$attachment_id) {
    $attachment_id = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => 'MDS2 Fixture Original',
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
    'post_type' => 'mds-pixel',
    'post_status' => 'publish',
    'post_title' => $legacy_long_title,
    'post_name' => 'legacy-fixture-advertiser',
]);
add_post_meta($ad_id, '_wp_old_slug', 'older-fixture-advertiser');
update_post_meta($ad_id, '_milliondollarscript_image', $attachment_id);
update_post_meta($ad_id, '_milliondollarscript_url', 'https://example.com/ad');
update_post_meta($ad_id, '_milliondollarscript_text', '<p>Fixture popup text <strong>with image</strong></p>');
update_post_meta($ad_id, '_milliondollarscript_mds3_fixture_custom_name', 'Legacy Custom Name');
update_post_meta($ad_id, '_milliondollarscript_mds3_fixture_custom_url', 'https://example.com/custom-field');
update_post_meta($ad_id, '_milliondollarscript_partner_code', 'partner-42');
update_post_meta($ad_id, '_edit_lock', 'fixture-noise');

$custom_fields_table = $wpdb->prefix . 'mds_custom_fields';
$wpdb->query("CREATE TABLE IF NOT EXISTS " . DB::ident($custom_fields_table) . " (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    field_key varchar(100) NOT NULL,
    field_label varchar(255) NOT NULL,
    field_type varchar(50) NOT NULL,
    field_options longtext DEFAULT NULL,
    field_validation longtext DEFAULT NULL,
    field_order int(11) NOT NULL DEFAULT 0,
    field_group_id bigint(20) unsigned DEFAULT NULL,
    is_required tinyint(1) NOT NULL DEFAULT 0,
    is_active tinyint(1) NOT NULL DEFAULT 1,
    show_in_popup tinyint(1) NOT NULL DEFAULT 0,
    popup_template_id bigint(20) unsigned DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY field_key (field_key),
    KEY field_order (field_order),
    KEY is_active (is_active)
) {$charset}");
$fixture_field_keys = ['mds3_fixture_custom_name', 'mds3_fixture_custom_url'];
$field_placeholders = implode(',', array_fill(0, count($fixture_field_keys), '%s'));
$wpdb->query($wpdb->prepare('DELETE FROM ' . DB::ident($custom_fields_table) . " WHERE field_key IN ({$field_placeholders})", $fixture_field_keys));
$wpdb->insert($custom_fields_table, [
    'field_key' => 'mds3_fixture_custom_name',
    'field_label' => 'Fixture Custom Name',
    'field_type' => 'text',
    'field_order' => 9001,
    'is_active' => 1,
    'show_in_popup' => 1,
]);
$wpdb->insert($custom_fields_table, [
    'field_key' => 'mds3_fixture_custom_url',
    'field_label' => 'Fixture Custom URL',
    'field_type' => 'url',
    'field_order' => 9002,
    'is_active' => 1,
    'show_in_popup' => 1,
]);

$grid_page = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Legacy Fixture Grid',
    'post_content' => '[milliondollarscript id="7" type="grid" width="100%" height="700px"]',
]);
$secondary_grid_page = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Legacy Fixture Secondary Grid',
    'post_content' => '[milliondollarscript id="9" type="grid"]',
]);
$order_page = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Legacy Fixture Order',
    'post_content' => '[milliondollarscript id="7" type="order"]',
]);
$block_page = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Legacy Fixture Block',
    'post_content' => '<!-- wp:carbon-fields/million-dollar-script {"data":{"milliondollarscript_type":"upload","milliondollarscript_id":"7","milliondollarscript_align":"center","milliondollarscript_width":"100%","milliondollarscript_height":"auto"}} /-->',
]);
$list_page_without_grid = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Legacy Fixture List Without Grid',
    'post_content' => '[milliondollarscript type="list"]',
]);
$foreign_legacy_page = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Foreign Legacy Fixture Page',
    'post_content' => '[mds_grid id="70" read_only="false"]',
]);
$native_mds3_content = '[mds3_page type="grid" grid_id="7"]';
$native_mds3_page = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Native MDS3 Fixture Page',
    'post_content' => $native_mds3_content,
]);
update_post_meta($native_mds3_page, '_mds3_page_type', 'grid');
update_post_meta($native_mds3_page, '_mds3_grid_id', 7);
$unmanaged_mds3_page = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Native MDS3 Unmanaged Fixture Page',
    'post_content' => '[mds3_page type="grid" grid_id="7"]',
]);
update_option('_milliondollarscript_grid-page', $grid_page, false);
update_option('_milliondollarscript_users-order-page', $order_page, false);
update_option('_milliondollarscript_currency', 'USD', false);
update_option('_milliondollarscript_extension_server_url', 'https://extensions.milliondollarscript.com', false);
update_option('_milliondollarscript_account-page', 'https://example.com/account', false);
update_option('_milliondollarscript_login-redirect', 'https://example.com/welcome', false);
update_option('_milliondollarscript_enable-cloaking', 'NO', false);
update_option('_milliondollarscript_mds-pixel-template', 'no', false);
update_option('_milliondollarscript_resize', 'no', false);
update_option('_milliondollarscript_max-upload-width', '640', false);
update_option('_milliondollarscript_use_woocommerce_integration', 'no', false);

$metadata_table = $wpdb->prefix . 'mds_page_metadata';
$wpdb->query("CREATE TABLE IF NOT EXISTS " . DB::ident($metadata_table) . " (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT(20) UNSIGNED NOT NULL,
    page_type VARCHAR(50) NOT NULL,
    creation_method VARCHAR(20) NOT NULL,
    creation_source VARCHAR(100) DEFAULT NULL,
    mds_version VARCHAR(20) NOT NULL,
    content_type VARCHAR(20) NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    confidence_score DECIMAL(3,2) DEFAULT 1.00,
    shortcode_attributes LONGTEXT DEFAULT NULL,
    block_attributes LONGTEXT DEFAULT NULL,
    detected_patterns LONGTEXT DEFAULT NULL,
    page_config LONGTEXT DEFAULT NULL,
    display_settings LONGTEXT DEFAULT NULL,
    integration_settings LONGTEXT DEFAULT NULL,
    last_validated DATETIME DEFAULT NULL,
    validation_errors LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_post_id (post_id)
) {$charset}");
$wpdb->delete($metadata_table, ['creation_source' => 'mds3_fixture']);
$wpdb->replace($metadata_table, [
    'post_id' => $grid_page,
    'page_type' => 'grid',
    'creation_method' => 'wizard',
    'creation_source' => 'mds3_fixture',
    'mds_version' => '2.6.0',
    'content_type' => 'shortcode',
    'status' => 'active',
    'shortcode_attributes' => wp_json_encode(['id' => 7, 'type' => 'grid']),
    'page_config' => wp_json_encode(['grid_id' => 7]),
]);
$wpdb->replace($metadata_table, [
    'post_id' => $order_page,
    'page_type' => 'order',
    'creation_method' => 'wizard',
    'creation_source' => 'mds3_fixture',
    'mds_version' => '2.6.0',
    'content_type' => 'shortcode',
    'status' => 'active',
    'shortcode_attributes' => wp_json_encode(['id' => 7, 'type' => 'order']),
    'page_config' => wp_json_encode(['grid_id' => 7]),
]);

$wpdb->insert($source_prefix . 'banners', [
    'banner_id' => 7,
    'grid_width' => 100,
    'grid_height' => 100,
    'days_expire' => 30,
    'price_per_block' => 2.5,
    'name' => 'Fixture Legacy Grid',
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
$wpdb->insert($source_prefix . 'banners', [
    'banner_id' => 9,
    'grid_width' => 50,
    'grid_height' => 40,
    'days_expire' => 0,
    'price_per_block' => 1.5,
    'name' => 'Fixture Secondary Legacy Grid',
    'currency' => 'USD',
    'max_orders' => 0,
    'block_width' => 10,
    'block_height' => 10,
    'grid_block' => 'grid-secondary',
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
    'bgcolor' => '#eeeeee',
    'auto_publish' => 'N',
    'auto_approve' => 'N',
    'nfs_covered' => 'N',
    'enabled' => 'Y',
]);
$wpdb->insert($source_prefix . 'packages', [
    'package_id' => 3,
    'banner_id' => 7,
    'days_expire' => 30,
    'price' => 10,
    'currency' => 'USD',
    'is_default' => 'Y',
    'max_orders' => 5,
    'description' => 'Fixture package',
]);
$wpdb->insert($source_prefix . 'prices', [
    'price_id' => 9,
    'banner_id' => 7,
    'row_from' => 0,
    'row_to' => 2,
    'block_id_from' => 0,
    'block_id_to' => 101,
    'price' => 3,
    'currency' => 'USD',
    'color' => '#ff0000',
    'col_from' => 0,
    'col_to' => 2,
]);
$wpdb->insert($source_prefix . 'orders', [
    'order_id' => 55,
    'user_id' => 1,
    // Older or interrupted installs can leave this snapshot stale even though
    // the linked block rows below still identify the complete paid order.
    'blocks' => '[0,1,100]',
    'status' => 'paid',
    'order_date' => '2025-01-02 03:04:05',
    'date_published' => '2025-01-05 00:00:00',
    'price' => 10,
    'quantity' => 4,
    'banner_id' => 7,
    'currency' => 'USD',
    'days_expire' => 30,
    'package_id' => 3,
    'ad_id' => $ad_id,
    'approved' => 'Y',
    'published' => 'Y',
    'block_info' => '',
]);
$wpdb->insert($source_prefix . 'orders', [
    'order_id' => 56,
    'user_id' => 1,
    'blocks' => '0',
    'status' => 'completed',
    'order_date' => '2025-02-02 03:04:05',
    'date_published' => '2025-02-03 00:00:00',
    'price' => 1.5,
    'quantity' => 1,
    'banner_id' => 9,
    'currency' => 'USD',
    'days_expire' => 0,
    'package_id' => 0,
    'ad_id' => $ad_id,
    'approved' => 'Y',
    'published' => 'Y',
    'block_info' => '',
]);

foreach ([[0, 0, 0], [1, 10, 0], [100, 0, 10], [101, 10, 10]] as $block) {
    $wpdb->insert($source_prefix . 'blocks', [
        'block_id' => $block[0],
        'user_id' => 1,
        'status' => 'sold',
        'x' => $block[1],
        'y' => $block[2],
        'image_data' => 0 === $block[0] ? 'flattened' : '',
        'url' => 'https://example.com/ad',
        'alt_text' => 'Fixture alt text',
        'file_name' => $image_path,
        'mime_type' => 'image/png',
        'approved' => 'Y',
        'published' => 'Y',
        'currency' => 'USD',
        'order_id' => 55,
        'price' => 2.5,
        'banner_id' => 7,
        'ad_id' => $ad_id,
        'click_count' => 2,
        'view_count' => 3,
    ]);
}
$wpdb->insert($source_prefix . 'blocks', [
    'block_id' => 5,
    'user_id' => 0,
    'status' => 'nfs',
    'x' => 50,
    'y' => 0,
    'image_data' => '',
    'url' => '',
    'alt_text' => '',
    'file_name' => '',
    'mime_type' => '',
    'approved' => 'Y',
    'published' => 'Y',
    'currency' => 'USD',
    'order_id' => 0,
    'price' => null,
    'banner_id' => 7,
    'ad_id' => 0,
    'click_count' => 0,
    'view_count' => 0,
]);
$wpdb->insert($source_prefix . 'blocks', [
    'block_id' => 0,
    'user_id' => 1,
    'status' => 'sold',
    'x' => 0,
    'y' => 0,
    'image_data' => '',
    'url' => 'https://example.com/secondary',
    'alt_text' => 'Secondary fixture alt text',
    'file_name' => $image_path,
    'mime_type' => 'image/png',
    'approved' => 'Y',
    'published' => 'Y',
    'currency' => 'USD',
    'order_id' => 56,
    'price' => 1.5,
    'banner_id' => 9,
    'ad_id' => $ad_id,
    'click_count' => 0,
    'view_count' => 0,
]);

$dry_run = (new DryRun())->report($source_prefix);
if (2 !== (int) ($dry_run['tables']['banners']['rows'] ?? 0) || 4 > (int) ($dry_run['pages']['count'] ?? 0)) {
    throw new RuntimeException('Dry run did not detect fixture tables/pages.');
}
$dry_run_page_ids = array_map('absint', wp_list_pluck((array) ($dry_run['pages']['candidates'] ?? []), 'post_id'));
foreach ([$foreign_legacy_page, $native_mds3_page, $unmanaged_mds3_page] as $current_page_id) {
    if (in_array(absint($current_page_id), $dry_run_page_ids, true)) {
        throw new RuntimeException('Dry run incorrectly included a page outside the selected legacy source.');
    }
}

$result = (new Importer())->import($source_prefix);
if (is_wp_error($result)) {
    throw new RuntimeException($result->get_error_message());
}

$grid_id = absint($wpdb->get_var($wpdb->prepare(
    'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND entity_type = 'banner' AND legacy_id = '7' AND mds3_entity_type = 'grid' LIMIT 1",
    $source_prefix
)));
$grid = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('grids')) . ' WHERE id = %d', $grid_id), ARRAY_A);
if (!$grid || 1000 !== (int) $grid['width'] || 1000 !== (int) $grid['height']) {
    throw new RuntimeException('Imported grid dimensions are wrong.');
}
$grid_settings = json_decode((string) ($grid['settings'] ?? ''), true);
if (!is_array($grid_settings) || 'auto' !== ($grid_settings['renderer_mode'] ?? '')) {
    throw new RuntimeException('Imported grid renderer mode should default to auto.');
}
$target_order_id = absint($wpdb->get_var($wpdb->prepare(
    'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND entity_type = 'order' AND legacy_id = '55' AND mds3_entity_type = 'order' LIMIT 1",
    $source_prefix
)));
$target_order = (new OrderRepository())->find($target_order_id);
$target_order_metadata = json_decode((string) ($target_order['metadata'] ?? ''), true);
if (!is_array($target_order_metadata)) {
    throw new RuntimeException('Imported order metadata did not decode.');
}
$target_placement_id = absint($wpdb->get_var($wpdb->prepare(
    'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND entity_type = 'placement' AND legacy_id = '55' AND mds3_entity_type = 'placement' LIMIT 1",
    $source_prefix
)));
$advertiser_page_id = absint($wpdb->get_var($wpdb->prepare(
    'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND entity_type = 'mds-pixel' AND legacy_id = %s AND mds3_entity_type = 'advertiser_page' LIMIT 1",
    $source_prefix,
    (string) $ad_id
)));
if (
    !$target_placement_id ||
    !$advertiser_page_id ||
    $advertiser_page_id !== absint($wpdb->get_var($wpdb->prepare('SELECT public_post_id FROM ' . DB::ident(DB::table('placements')) . ' WHERE id = %d', $target_placement_id))) ||
    'legacy-fixture-advertiser' !== get_post_field('post_name', $advertiser_page_id) ||
    !in_array('older-fixture-advertiser', get_post_meta($advertiser_page_id, '_wp_old_slug', false), true) ||
    'draft' !== get_post_status($advertiser_page_id)
) {
    throw new RuntimeException('MDS2 advertiser page identity, slug history, or disabled-by-default privacy was not preserved.');
}
if ('Fixture alt text' !== get_post_field('post_title', $advertiser_page_id)) {
    throw new RuntimeException('Oversized MDS2 advertiser title was not replaced by the concise placement alt text.');
}
$target_placement_popup = (string) $wpdb->get_var($wpdb->prepare(
    'SELECT popup_text FROM ' . DB::ident(DB::table('placements')) . ' WHERE id = %d',
    $target_placement_id
));
if (false === strpos(wp_strip_all_tags($target_placement_popup), 'Fixture popup text with image')) {
    throw new RuntimeException('Advertiser title normalization discarded or replaced the full placement description.');
}
$title_repairs = array_values(array_filter((array) ($result['verification']['repairs'] ?? []), static function ($repair) use ($ad_id) {
    return is_array($repair)
        && 'advertiser_page' === ($repair['entity'] ?? '')
        && (string) $ad_id === (string) ($repair['source_id'] ?? '')
        && false !== strpos((string) ($repair['reason'] ?? ''), 'Normalized an oversized legacy advertiser title');
}));
if (1 !== count($title_repairs) || false !== strpos(wp_json_encode($title_repairs), $legacy_long_title)) {
    throw new RuntimeException('Migration reconciliation did not flag the anonymous legacy title normalization safely.');
}
if (
    30 !== absint($target_order_metadata['duration_days'] ?? 0) ||
    '2025-01-05 00:00:00' !== (string) ($target_order_metadata['term_started_at'] ?? '') ||
    gmdate('Y-m-d H:i:s', strtotime('2025-01-05 00:00:00') + (30 * DAY_IN_SECONDS)) !== (string) ($target_order_metadata['expires_at'] ?? '')
) {
    throw new RuntimeException('Imported paid legacy order did not preserve term and expiration metadata.');
}
$imported_custom_fields = is_array($target_order_metadata['mds_fields'] ?? null) ? $target_order_metadata['mds_fields'] : [];
if (
    'Legacy Custom Name' !== (string) ($imported_custom_fields['mds3_fixture_custom_name']['value'] ?? '') ||
    'https://example.com/custom-field' !== (string) ($imported_custom_fields['mds3_fixture_custom_url']['value'] ?? '')
) {
    throw new RuntimeException('MDS2 custom fields were not migrated into MDS3 order metadata.');
}
$legacy_ad_post_meta = is_array($target_order_metadata['legacy_ad_post_meta'] ?? null) ? $target_order_metadata['legacy_ad_post_meta'] : [];
if ('partner-42' !== (string) ($legacy_ad_post_meta['_milliondollarscript_partner_code'] ?? '')) {
    throw new RuntimeException('Unmapped legacy ad post meta was not preserved.');
}
if (array_key_exists('_edit_lock', $legacy_ad_post_meta)) {
    throw new RuntimeException('Noisy WordPress edit meta should not be preserved in migrated order metadata.');
}
$secondary_grid_id = absint($wpdb->get_var($wpdb->prepare(
    'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND entity_type = 'banner' AND legacy_id = '9' AND mds3_entity_type = 'grid' LIMIT 1",
    $source_prefix
)));
$secondary_order_id = absint($wpdb->get_var($wpdb->prepare(
    'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND entity_type = 'order' AND legacy_id = '56' AND mds3_entity_type = 'order' LIMIT 1",
    $source_prefix
)));
if (
    !$secondary_grid_id ||
    !$secondary_order_id ||
    $secondary_grid_id !== absint(get_post_meta($secondary_grid_page, '_mds3_grid_id', true)) ||
    1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::ident(DB::table('order_items')) . ' WHERE order_id = %d AND grid_id = %d', $secondary_order_id, $secondary_grid_id)) ||
    1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id = %d AND grid_id = %d', $secondary_order_id, $secondary_grid_id))
) {
    throw new RuntimeException('Multiple-grid migration did not preserve the second order, page, item, and placement identity.');
}

$checks = [
    'packages' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::ident(DB::table('packages')) . ' WHERE grid_id = %d', $grid_id)),
    'price_rules' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::ident(DB::table('price_rules')) . ' WHERE grid_id = %d', $grid_id)),
    'orders' => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . DB::ident(DB::table('orders')) . " WHERE commerce_provider = 'legacy_mds2'"),
    'blocks' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id = %d', $grid_id)),
    'order_items' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::ident(DB::table('order_items')) . ' WHERE order_id = %d', $target_order_id)),
    'placements' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::ident(DB::table('placements')) . ' WHERE grid_id = %d', $grid_id)),
    'pages' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::ident(DB::table('pages'))),
];

foreach (['packages', 'price_rules', 'orders', 'placements'] as $key) {
    if ($checks[$key] < 1) {
        throw new RuntimeException("Expected {$key} to import.");
    }
}
if ($checks['blocks'] < 5 || $checks['order_items'] < 4 || $checks['pages'] < 3) {
    throw new RuntimeException('Expected blocks, order items, and pages to import: ' . wp_json_encode($checks));
}

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

$pre_recovery = [
    'grid_id' => $grid_id,
    'order_id' => $target_order_id,
    'block_101_id' => $mapped_target_id('block', '7:101', 'block'),
    'package_id' => $mapped_target_id('package', '3', 'package'),
    'price_rule_id' => $mapped_target_id('price', '9', 'price_rule'),
    'placement_id' => $mapped_target_id('placement', '55', 'placement'),
    'pages' => $checks['pages'],
];
if (!$pre_recovery['block_101_id'] || !$pre_recovery['package_id'] || !$pre_recovery['price_rule_id'] || !$pre_recovery['placement_id']) {
    throw new RuntimeException('Initial migration map was incomplete before recovery simulation.');
}

$wpdb->delete(DB::table('order_items'), ['order_id' => $target_order_id]);
$wpdb->delete(DB::table('placements'), ['id' => $pre_recovery['placement_id']]);
$wpdb->delete(DB::table('packages'), ['id' => $pre_recovery['package_id']]);
$wpdb->delete(DB::table('price_rules'), ['id' => $pre_recovery['price_rule_id']]);
$wpdb->delete(DB::table('migration_map'), [
    'source_prefix' => $source_prefix,
    'entity_type' => 'block',
    'legacy_id' => '7:101',
    'mds3_entity_type' => 'block',
]);

$recovery_result = (new Importer())->import($source_prefix);
if (is_wp_error($recovery_result)) {
    throw new RuntimeException('Interrupted migration recovery failed: ' . $recovery_result->get_error_message());
}
if (0 !== (int) ($recovery_result['imported']['warnings'] ?? 0)) {
    throw new RuntimeException('Interrupted migration recovery produced warnings: ' . wp_json_encode($recovery_result['verification']['warnings'] ?? []));
}

$grid_id = $mapped_target_id('banner', '7', 'grid');
$target_order_id = $mapped_target_id('order', '55', 'order');
$target_order = (new OrderRepository())->find($target_order_id);
$recovered_package_id = $mapped_target_id('package', '3', 'package');
$recovered_price_rule_id = $mapped_target_id('price', '9', 'price_rule');
$recovered_placement_id = $mapped_target_id('placement', '55', 'placement');
$recovered_block_101_id = $mapped_target_id('block', '7:101', 'block');
if ($pre_recovery['grid_id'] !== $grid_id || $pre_recovery['order_id'] !== $target_order_id) {
    throw new RuntimeException('Recovery rerun changed stable grid or order mappings.');
}
if ($pre_recovery['block_101_id'] !== $recovered_block_101_id) {
    throw new RuntimeException('Recovery rerun duplicated a block instead of restoring the lost block map.');
}
if (!$recovered_package_id || !$recovered_price_rule_id || !$recovered_placement_id) {
    throw new RuntimeException('Recovery rerun did not recreate missing mapped package, price rule, or placement targets.');
}

$recovered_order_metadata = json_decode((string) ($target_order['metadata'] ?? ''), true);
if (!is_array($recovered_order_metadata) || $recovered_package_id !== absint($recovered_order_metadata['package_id'] ?? 0)) {
    throw new RuntimeException('Recovery rerun did not refresh the migrated order package relationship.');
}
if ('legacy_mds2' !== (string) ($target_order['commerce_provider'] ?? '') || '55' !== (string) ($target_order['commerce_order_id'] ?? '')) {
    throw new RuntimeException('Recovery rerun did not preserve the legacy order commerce relationship.');
}

$checks = [
    'packages' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::ident(DB::table('packages')) . ' WHERE grid_id = %d', $grid_id)),
    'price_rules' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::ident(DB::table('price_rules')) . ' WHERE grid_id = %d', $grid_id)),
    'orders' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . DB::ident(DB::table('orders')) . " WHERE commerce_provider = 'legacy_mds2' AND commerce_order_id = %s", '55')),
    'blocks' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id = %d', $grid_id)),
    'order_items' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::ident(DB::table('order_items')) . ' WHERE order_id = %d', $target_order_id)),
    'placements' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::ident(DB::table('placements')) . ' WHERE grid_id = %d', $grid_id)),
    'pages' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::ident(DB::table('pages'))),
    'mapped_blocks' => $mapped_count('block', 'block'),
];
if (
    1 !== $checks['packages'] ||
    1 !== $checks['price_rules'] ||
    1 !== $checks['orders'] ||
    5 !== $checks['blocks'] ||
    4 !== $checks['order_items'] ||
    1 !== $checks['placements'] ||
    $pre_recovery['pages'] !== $checks['pages'] ||
    6 !== $checks['mapped_blocks']
) {
    throw new RuntimeException('Recovery rerun duplicated or missed imported entities: ' . wp_json_encode($checks));
}

// Losing map rows after an interrupted/manual recovery must reconnect the
// existing targets rather than create duplicate grids, orders, or placements.
foreach ([
    ['banner', '7', 'grid'],
    ['order', '55', 'order'],
    ['placement', '55', 'placement'],
] as $lost_map) {
    $wpdb->delete(DB::table('migration_map'), [
        'source_prefix' => $source_prefix,
        'entity_type' => $lost_map[0],
        'legacy_id' => $lost_map[1],
        'mds3_entity_type' => $lost_map[2],
    ]);
}

$map_recovery_result = (new Importer())->import($source_prefix);
if (is_wp_error($map_recovery_result)) {
    throw new RuntimeException('Missing migration-map recovery failed: ' . $map_recovery_result->get_error_message());
}
$repair_entities = array_values(array_unique(array_map(static function ($repair) {
    return sanitize_key((string) ($repair['entity'] ?? ''));
}, (array) ($map_recovery_result['verification']['repairs'] ?? []))));
foreach (['grid', 'order', 'placement'] as $repaired_entity) {
    if (!in_array($repaired_entity, $repair_entities, true)) {
        throw new RuntimeException('Missing migration-map recovery was not reported for ' . $repaired_entity . '.');
    }
}
if (
    $grid_id !== $mapped_target_id('banner', '7', 'grid') ||
    $target_order_id !== $mapped_target_id('order', '55', 'order') ||
    $recovered_placement_id !== $mapped_target_id('placement', '55', 'placement') ||
    1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::ident(DB::table('grids')) . ' WHERE id = %d', $grid_id)) ||
    1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::ident(DB::table('orders')) . ' WHERE order_key = %s', (string) ($target_order['order_key'] ?? ''))) ||
    1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id = %d', $target_order_id))
) {
    throw new RuntimeException('Missing migration-map recovery changed identities or duplicated target records.');
}

if (!metadata_exists('post', $grid_page, '_mds3_migration_original_content')) {
    throw new RuntimeException('Grid page original content was not backed up.');
}
if (false === strpos((string) get_post_field('post_content', $grid_page), '[mds3_page')) {
    throw new RuntimeException('Grid page content was not migrated to MDS3 shortcode.');
}
if (!metadata_exists('post', $block_page, '_mds3_migration_original_content')) {
    throw new RuntimeException('MDS2 block page original content was not backed up.');
}
if (
    !metadata_exists('post', $list_page_without_grid, '_mds3_migration_original_content') ||
    $grid_id !== absint(get_post_meta($list_page_without_grid, '_mds3_grid_id', true)) ||
    false === strpos((string) get_post_field('post_content', $list_page_without_grid), 'grid_id="' . $grid_id . '"')
) {
    throw new RuntimeException('A legacy page without an explicit grid ID did not use a grid mapped from the same migration source.');
}
if (false === strpos((string) get_post_field('post_content', $block_page), 'type="upload"')) {
    throw new RuntimeException('MDS2 block page was not migrated to the matching MDS3 page shortcode.');
}
if (
    $native_mds3_content !== (string) get_post_field('post_content', $native_mds3_page) ||
    metadata_exists('post', $native_mds3_page, '_mds3_migration_original_content') ||
    '' !== (string) get_post_meta($native_mds3_page, '_mds3_migration_source', true)
) {
    throw new RuntimeException('Migration modified a page owned by the current Million Dollar Script installation.');
}
$list_html = do_shortcode('[mds3_page type="list" grid_id="' . absint($grid_id) . '"]');
if (false === strpos($list_html, 'Fixture alt text') || false === strpos($list_html, 'https://example.com/ad')) {
    throw new RuntimeException('Advertiser list page did not render imported placement data.');
}
$previous_user_id = get_current_user_id();
wp_set_current_user(1);
$manage_html = do_shortcode('[mds3_page type="manage" grid_id="' . absint($grid_id) . '"]');
wp_set_current_user($previous_user_id);
if (false === strpos($manage_html, '#' . absint($target_order_id)) || false === strpos($manage_html, 'Manage')) {
    throw new RuntimeException('Manage page did not render imported customer order data.');
}
$target_order = (new \MillionDollarScript\V3\Orders\OrderRepository())->find($target_order_id);
$_GET['mds3_order_id'] = $target_order_id;
$_GET['mds3_order_key'] = $target_order['order_key'] ?? '';
$summary_html = do_shortcode('[mds3_page type="thank-you" grid_id="' . absint($grid_id) . '"]');
unset($_GET['mds3_order_id'], $_GET['mds3_order_key']);
if (false === strpos($summary_html, '#' . absint($target_order_id)) || false === strpos($summary_html, 'Manage upload')) {
    throw new RuntimeException('Order-key summary page did not render imported order data.');
}
$settings = get_option('mds3_settings', []);
if (!is_array($settings) || 'https://example.com/account' !== ($settings['account-page'] ?? '')) {
    throw new RuntimeException('MDS2 account page setting was not mapped.');
}
if ('NO' !== ($settings['enable-cloaking'] ?? '') || 'no' !== ($settings['resize'] ?? '') || 640 !== (int) ($settings['max-upload-width'] ?? 0)) {
    throw new RuntimeException('MDS2 display/upload settings were not mapped.');
}
if ('standalone' !== (string) ($settings['payment_provider'] ?? '')) {
    throw new RuntimeException('MDS2 payment provider preference was not mapped.');
}

$placement_attachment = absint($wpdb->get_var($wpdb->prepare('SELECT attachment_id FROM ' . DB::ident(DB::table('placements')) . ' WHERE grid_id = %d LIMIT 1', $grid_id)));
if (!(new OriginalImage())->resolve($placement_attachment)) {
    throw new RuntimeException('Placement original image did not resolve.');
}
$legacy_media_resolver = new ReflectionMethod(Importer::class, 'attachment_from_path');
$legacy_media_resolver->setAccessible(true);
$media_importer = new Importer();
$wamp_attachment = $legacy_media_resolver->invoke($media_importer, 'C:\\wamp64\\www\\wordpress\\wp-content\\uploads\\milliondollarscript\\images\\fixture-original.png');
if (absint($wamp_attachment) !== absint($placement_attachment)) {
    throw new RuntimeException('WAMP-style legacy media path did not resolve to the original attachment.');
}
$relative_windows_attachment = $legacy_media_resolver->invoke($media_importer, 'wp-content\\uploads\\milliondollarscript\\images\\fixture-original.png');
if (absint($relative_windows_attachment) !== absint($placement_attachment)) {
    throw new RuntimeException('Relative Windows legacy media path did not resolve to the original attachment.');
}
$placement = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('placements')) . ' WHERE grid_id = %d AND link_url <> "" ORDER BY id ASC LIMIT 1', $grid_id), ARRAY_A);
if (!$placement) {
    throw new RuntimeException('Imported placement with advertiser URL did not exist.');
}
if (
    false === strpos((string) ($placement['popup_text'] ?? ''), 'Fixture popup text') ||
    false === strpos((string) ($placement['popup_text'] ?? ''), '<strong>with image</strong>')
) {
    throw new RuntimeException('Imported placement did not preserve legacy popup text.');
}
$placement_payload_method = new ReflectionMethod(GridAjax::class, 'placement_payload');
$placement_payload_method->setAccessible(true);
$redirect_payload = $placement_payload_method->invoke(new GridAjax(), $placement, $settings);
if (
    ($redirect_payload['link_url'] ?? '') !== 'https://example.com/ad' ||
    false === strpos((string) ($redirect_payload['click_url'] ?? ''), 'action=mds3_click') ||
    false === strpos((string) ($redirect_payload['click_url'] ?? ''), 'placement_id=' . absint($placement['id']))
) {
    throw new RuntimeException('Migrated cloaked placement did not expose an MDS3 redirect click URL.');
}
$direct_payload = $placement_payload_method->invoke(new GridAjax(), $placement, array_merge($settings, ['enable-cloaking' => 'YES']));
if (($direct_payload['click_url'] ?? '') !== 'https://example.com/ad') {
    throw new RuntimeException('Direct-link placement did not expose the advertiser URL.');
}
$popup_payload = $placement_payload_method->invoke(new GridAjax(), $placement, array_merge($settings, [
    'enable-cloaking' => 'YES',
    'popup-rich-text' => 'yes',
    'popup-template' => '<div>%image%%text%</div>',
]));
if (
    'Fixture popup text with image' !== (string) ($popup_payload['popup_text'] ?? '') ||
    false === strpos((string) ($popup_payload['popup_text_html'] ?? ''), '<strong>with image</strong>') ||
    false === strpos((string) ($popup_payload['popover_html'] ?? ''), '%image%')
) {
    throw new RuntimeException('Imported placement popup payload did not expose legacy text and image placeholder.');
}

$grid_entity = (new GridRepository())->find($grid_id);
if (!$grid_entity) {
    throw new RuntimeException('Imported grid entity did not load.');
}

$zone_reservation = (new ReservationService())->reserve($grid_entity, [['row' => 2, 'col' => 2]], ['email' => 'zone@example.com']);
if (is_wp_error($zone_reservation)) {
    throw new RuntimeException('Price-zone reservation failed: ' . $zone_reservation->get_error_message());
}
if (3.0 !== (float) ($zone_reservation['order']['total'] ?? 0)) {
    throw new RuntimeException('Migrated price zone did not price a new reservation at 3.00.');
}
$zone_items = (new \MillionDollarScript\V3\Orders\OrderRepository())->items(absint($zone_reservation['order']['id'] ?? 0));
$zone_item_metadata = json_decode((string) ($zone_items[0]['metadata'] ?? ''), true);
if (!is_array($zone_item_metadata) || 'price_rule' !== ($zone_item_metadata['price_source'] ?? '')) {
    throw new RuntimeException('Reservation item did not record price-rule pricing metadata.');
}

$nfs_reservation = (new ReservationService())->reserve($grid_entity, [['row' => 0, 'col' => 5]], ['email' => 'nfs@example.com']);
if (!is_wp_error($nfs_reservation)) {
    throw new RuntimeException('Migrated NFS/unavailable block was reservable.');
}

$availability = (new BlockRepository())->set_region_status($grid_entity, [
    'row_from' => 0,
    'row_to' => 0,
    'col_from' => 5,
    'col_to' => 5,
], 'available', ['note' => 'fixture release']);
if (is_wp_error($availability) || empty($availability['changed'])) {
    throw new RuntimeException('Unavailable region could not be released.');
}
$released_reservation = (new ReservationService())->reserve($grid_entity, [['row' => 0, 'col' => 5]], ['email' => 'released@example.com']);
if (is_wp_error($released_reservation) || 2.5 !== (float) ($released_reservation['order']['total'] ?? 0)) {
    throw new RuntimeException('Released block did not reserve at base grid price.');
}

$blocks_repo = new BlockRepository();
$admin_region = $blocks_repo->set_region_status($grid_entity, [
    'row_from' => 50,
    'row_to' => 51,
    'col_from' => 50,
    'col_to' => 52,
], 'unavailable', ['note' => 'fixture unavailable region']);
if (is_wp_error($admin_region) || 6 !== (int) ($admin_region['changed'] ?? 0)) {
    throw new RuntimeException('Admin unavailable region was not created with the expected block count.');
}
$regions = $blocks_repo->unavailable_regions($grid_entity);
$matched_region = null;
foreach ($regions as $region) {
    if ('fixture unavailable region' === ($region['note'] ?? '')) {
        $matched_region = $region;
        break;
    }
}
if (!$matched_region || 50 !== (int) $matched_region['row_from'] || 51 !== (int) $matched_region['row_to'] || 50 !== (int) $matched_region['col_from'] || 52 !== (int) $matched_region['col_to'] || 6 !== (int) $matched_region['count']) {
    throw new RuntimeException('Admin unavailable region metadata did not group as a removable region.');
}
$admin_region_release = $blocks_repo->set_region_status($grid_entity, $matched_region, 'available', ['note' => 'fixture unavailable region release']);
if (is_wp_error($admin_region_release) || empty($admin_region_release['changed'])) {
    throw new RuntimeException('Admin unavailable region was not released by its saved bounds.');
}

$package_id = absint($wpdb->get_var($wpdb->prepare('SELECT id FROM ' . DB::ident(DB::table('packages')) . ' WHERE grid_id = %d ORDER BY id ASC LIMIT 1', $grid_id)));
$package_reservation = (new ReservationService())->reserve($grid_entity, [['row' => 3, 'col' => 3]], [
    'email' => 'package@example.com',
    'package_id' => $package_id,
]);
if (is_wp_error($package_reservation) || 10.0 !== (float) ($package_reservation['order']['total'] ?? 0)) {
    throw new RuntimeException('Selected package did not drive reservation total.');
}
$package_metadata = json_decode((string) ($package_reservation['order']['metadata'] ?? ''), true);
if (!is_array($package_metadata) || $package_id !== absint($package_metadata['package_id'] ?? 0) || empty($package_metadata['expires_at'])) {
    throw new RuntimeException('Package reservation metadata did not preserve package and duration details.');
}
$wpdb->update(DB::table('packages'), ['max_orders' => 1], ['id' => $package_id]);
$package_limit = (new ReservationService())->reserve($grid_entity, [['row' => 3, 'col' => 4]], [
    'email' => 'package-limit@example.com',
    'package_id' => $package_id,
]);
if (!is_wp_error($package_limit) || 'mds3_package_order_limit_reached' !== $package_limit->get_error_code()) {
    throw new RuntimeException('Package max-order limit was not enforced.');
}

$original_grid_settings = $grid_entity->settings();
$limited_grid_settings = $original_grid_settings;
$limited_grid_settings['max_orders'] = max(1, (new OrderRepository())->active_count_for_grid($grid_id));
$wpdb->update(DB::table('grids'), ['settings' => wp_json_encode($limited_grid_settings)], ['id' => $grid_id]);
$grid_limited = (new GridRepository())->find($grid_id);
$grid_limit = (new ReservationService())->reserve($grid_limited, [['row' => 3, 'col' => 5]], [
    'email' => 'grid-limit@example.com',
]);
$wpdb->update(DB::table('grids'), ['settings' => wp_json_encode($original_grid_settings)], ['id' => $grid_id]);
$grid_entity = (new GridRepository())->find($grid_id);
if (!is_wp_error($grid_limit) || 'mds3_grid_order_limit_reached' !== $grid_limit->get_error_code()) {
    throw new RuntimeException('Grid max-order limit was not enforced.');
}

$settings_before_gap = get_option('mds3_settings', []);
$settings_gap = is_array($settings_before_gap) ? $settings_before_gap : [];
$settings_gap['block-selection-mode'] = 'YES';
$settings_gap['selection-adjacency-mode'] = 'ADJACENT';
update_option('mds3_settings', $settings_gap);
$adjacent_rejection = (new ReservationService())->reserve($grid_entity, [
    ['row' => 80, 'col' => 80],
    ['row' => 80, 'col' => 82],
], ['email' => 'adjacent-rejection@example.com']);
if (!is_wp_error($adjacent_rejection)) {
    update_option('mds3_settings', $settings_before_gap);
    throw new RuntimeException('Adjacent selection mode accepted a disconnected selection.');
}

$settings_gap['selection-adjacency-mode'] = 'RECTANGLE';
update_option('mds3_settings', $settings_gap);
$rectangle_rejection = (new ReservationService())->reserve($grid_entity, [
    ['row' => 81, 'col' => 80],
    ['row' => 81, 'col' => 81],
    ['row' => 82, 'col' => 80],
], ['email' => 'rectangle-rejection@example.com']);
if (!is_wp_error($rectangle_rejection)) {
    update_option('mds3_settings', $settings_before_gap);
    throw new RuntimeException('Rectangle selection mode accepted an incomplete rectangle.');
}

$settings_gap['block-selection-mode'] = 'YES';
$settings_gap['selection-adjacency-mode'] = 'NONE';
update_option('mds3_settings', $settings_gap);
$gap_reservation = (new ReservationService())->reserve($grid_entity, [
    ['row' => 80, 'col' => 80],
    ['row' => 80, 'col' => 82],
], ['email' => 'gap-selection@example.com']);
update_option('mds3_settings', $settings_before_gap);
if (is_wp_error($gap_reservation)) {
    throw new RuntimeException('Unrestricted gap selection reservation failed: ' . $gap_reservation->get_error_message());
}
$gap_rect = $gap_reservation['placement_rect'] ?? [];
$gap_order_id = absint($gap_reservation['order']['id'] ?? 0);
$placement_payload_method = new ReflectionMethod(new GridAjax(), 'placement_payload');
$placement_payload_method->setAccessible(true);
$gap_payload = $placement_payload_method->invoke(new GridAjax(), [
    'id' => 9999,
    'grid_id' => $grid_id,
    'block_id' => absint($gap_rect['block_id'] ?? 0),
    'order_id' => $gap_order_id,
    'attachment_id' => 0,
    'x' => absint($gap_rect['x'] ?? 0),
    'y' => absint($gap_rect['y'] ?? 0),
    'width' => absint($gap_rect['width'] ?? 0),
    'height' => absint($gap_rect['height'] ?? 0),
    'fit_mode' => 'cover',
    'link_url' => 'https://example.com/gap',
    'status' => 'active',
], ['enable-cloaking' => 'YES']);
if (30 !== absint($gap_payload['width'] ?? 0) || 2 !== count($gap_payload['mask'] ?? [])) {
    throw new RuntimeException('Gap selection placement did not preserve the selected-block mask.');
}
(new BlockRepository())->release_by_order($gap_order_id);
foreach ($gap_payload['mask'] as $mask_block) {
    $wpdb->delete(DB::table('blocks'), [
        'grid_id' => $grid_id,
        'x' => absint($mask_block['x'] ?? 0),
        'y' => absint($mask_block['y'] ?? 0),
        'status' => 'available',
    ]);
}

if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $source_prefix . 'banners')) !== $source_prefix . 'banners') {
    throw new RuntimeException('Source fixture table was unexpectedly removed.');
}

$wpdb->query($wpdb->prepare('DELETE FROM ' . DB::ident($custom_fields_table) . " WHERE field_key IN ({$field_placeholders})", $fixture_field_keys));

echo wp_json_encode([
    'dry_run_pages' => $dry_run['pages']['count'],
    'imported' => $result['imported'],
    'checks' => $checks,
], JSON_PRETTY_PRINT) . "\n";
