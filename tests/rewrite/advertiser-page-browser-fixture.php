<?php
/**
 * Seed/cleanup helper for the advertiser-page browser regression.
 */

use MillionDollarScript\V3\Media\AdvertiserPageManager;
use MillionDollarScript\V3\Media\AdvertiserPages;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$state_option = 'mds3_advertiser_browser_fixture_state';
$action = sanitize_key((string) getenv('MDS_ADVERTISER_FIXTURE_ACTION'));

if ('cleanup' === $action) {
    $state = get_option($state_option, []);
    $state = is_array($state) ? $state : [];
    if (!empty($state['post_id'])) {
        wp_delete_post(absint($state['post_id']), true);
    }
    if (!empty($state['placement_id'])) {
        $wpdb->delete(DB::table('placements'), ['id' => absint($state['placement_id'])]);
    }
    if (!empty($state['order_id'])) {
        $wpdb->delete(DB::table('orders'), ['id' => absint($state['order_id'])]);
    }
    if (array_key_exists('settings', $state)) {
        update_option('mds3_settings', is_array($state['settings']) ? $state['settings'] : [], false);
    }
    delete_option($state_option);
    flush_rewrite_rules(false);
    echo "Advertiser browser fixture cleaned.\n";
    return;
}

if ('seed' !== $action) {
    throw new RuntimeException('Set MDS_ADVERTISER_FIXTURE_ACTION to seed or cleanup.');
}

$existing = get_option($state_option, []);
if (is_array($existing) && !empty($existing['post_id'])) {
    wp_delete_post(absint($existing['post_id']), true);
}
if (is_array($existing) && !empty($existing['placement_id'])) {
    $wpdb->delete(DB::table('placements'), ['id' => absint($existing['placement_id'])]);
}
if (is_array($existing) && !empty($existing['order_id'])) {
    $wpdb->delete(DB::table('orders'), ['id' => absint($existing['order_id'])]);
}

$original_settings = get_option('mds3_settings', []);
$original_settings = is_array($original_settings) ? $original_settings : [];
$settings = array_merge($original_settings, [
    'mds-pixel-template' => 'yes',
    'exclude-from-search' => 'no',
    'mds-pixel-base' => 'browser-advertisers',
    'mds-pixel-slug-structure' => '%title%-%placement_id%',
]);
update_option('mds3_settings', $settings, false);
(new AdvertiserPages())->register_post_type();

$grid_id = absint($wpdb->get_var('SELECT id FROM ' . DB::ident(DB::table('grids')) . " WHERE status = 'active' ORDER BY id ASC LIMIT 1"));
if (!$grid_id) {
    throw new RuntimeException('An active grid is required.');
}
$now = current_time('mysql', true);
$wpdb->insert(DB::table('orders'), [
    'order_key' => wp_generate_uuid4(),
    'email' => 'browser-private@example.test',
    'status' => 'paid',
    'currency' => 'USD',
    'subtotal' => 1,
    'total' => 1,
    'created_at' => $now,
    'updated_at' => $now,
]);
$order_id = absint($wpdb->insert_id);
$wpdb->insert(DB::table('placements'), [
    'grid_id' => $grid_id,
    'order_id' => $order_id,
    'attachment_id' => 0,
    'x' => 0,
    'y' => 0,
    'width' => 10,
    'height' => 10,
    'fit_mode' => 'cover',
    'link_url' => 'https://example.com/browser-advertiser',
    'alt_text' => 'Browser Fixture Advertiser',
    'popup_text' => '<p>Responsive advertiser page fixture.</p>',
    'status' => 'active',
    'sort_order' => 0,
    'created_at' => $now,
    'updated_at' => $now,
]);
$placement_id = absint($wpdb->insert_id);
$post_id = (new AdvertiserPageManager())->synchronize($placement_id);
if (is_wp_error($post_id) || !$post_id) {
    throw new RuntimeException('Advertiser page seeding failed.');
}

update_option($state_option, [
    'settings' => $original_settings,
    'order_id' => $order_id,
    'placement_id' => $placement_id,
    'post_id' => absint($post_id),
], false);
flush_rewrite_rules(false);

echo wp_json_encode([
    'url' => get_permalink($post_id),
    'placement_id' => $placement_id,
]) . "\n";
