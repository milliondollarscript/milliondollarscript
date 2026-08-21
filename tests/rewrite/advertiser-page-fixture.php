<?php
/**
 * WordPress-backed individual advertiser page regression fixture.
 */

use MillionDollarScript\Media\Placements;
use MillionDollarScript\V3\Grid\GridAjax;
use MillionDollarScript\V3\Media\AdvertiserPageManager;
use MillionDollarScript\V3\Media\AdvertiserPages;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$original_settings = get_option('mds3_settings', []);
$original_settings = is_array($original_settings) ? $original_settings : [];
$original_history = get_option('mds3_advertiser_page_base_history', null);
$wpdb->query('START TRANSACTION');

try {
    $settings = array_merge($original_settings, [
        'mds-pixel-template' => 'yes',
        'exclude-from-search' => 'no',
        'mds-pixel-base' => 'advertisers',
        'mds-pixel-slug-structure' => '%title%-%placement_id%',
        'advertiser-page-popup-link' => 'yes',
        'advertiser-page-popup-label' => 'Read advertiser profile',
        'advertiser-page-link-target' => '_self',
    ]);
    update_option('mds3_settings', $settings, false);
    (new AdvertiserPages())->register_post_type();

    $grid_id = absint($wpdb->get_var('SELECT id FROM ' . DB::ident(DB::table('grids')) . " WHERE status = 'active' ORDER BY id ASC LIMIT 1"));
    if (!$grid_id) {
        throw new RuntimeException('An active grid is required for the advertiser-page fixture.');
    }

    $now = current_time('mysql', true);
    $wpdb->insert(DB::table('orders'), [
        'order_key' => wp_generate_uuid4(),
        'user_id' => get_current_user_id() ?: 1,
        'email' => 'private-fixture@example.test',
        'status' => 'paid',
        'currency' => 'USD',
        'subtotal' => 1,
        'total' => 1,
        'metadata' => wp_json_encode(['private_fixture' => true]),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $order_id = absint($wpdb->insert_id);
    $wpdb->insert(DB::table('placements'), [
        'grid_id' => $grid_id,
        'order_id' => $order_id,
        'user_id' => get_current_user_id() ?: 1,
        'attachment_id' => 0,
        'x' => 0,
        'y' => 0,
        'width' => 10,
        'height' => 10,
        'fit_mode' => 'cover',
        'link_url' => 'https://example.com/advertiser',
        'alt_text' => 'Private Fixture Advertiser',
        'popup_text' => '<p>Public fixture description.</p>',
        'status' => 'active',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $placement_id = absint($wpdb->insert_id);

    $manager = new AdvertiserPageManager();
    $post_id = $manager->synchronize($placement_id);
    if (is_wp_error($post_id) || !$post_id) {
        throw new RuntimeException('An active paid placement did not create an advertiser page.');
    }
    \MillionDollarScript\Core\Hooks::do('million-dollar-script/placement/saved', ['id' => $placement_id], ['id' => $order_id], []);
    if ('publish' !== get_post_status($post_id) || 0 !== (int) get_post_field('post_author', $post_id)) {
        throw new RuntimeException('Advertiser pages must publish without exposing an administrator as the author.');
    }
    $url = Placements::public_url($placement_id);
    if (false === strpos($url, '/advertisers/private-fixture-advertiser-' . $placement_id . '/')) {
        throw new RuntimeException('The public URL facade did not return the configured advertiser page URL.');
    }
    $view = Placements::public_view($placement_id);
    if (!is_array($view) || array_key_exists('order_id', $view) || array_key_exists('user_id', $view) || false !== strpos(wp_json_encode($view), 'private-fixture@example.test')) {
        throw new RuntimeException('The advertiser page view model exposed private order or account data.');
    }
    $rendered = (new \MillionDollarScript\V3\Media\AdvertiserPageView())->render($placement_id);
    if (false === strpos($rendered, 'Public fixture description') || false !== strpos($rendered, 'private-fixture@example.test')) {
        throw new RuntimeException('The default advertiser template omitted public content or exposed private order data.');
    }
    $component = new AdvertiserPages();
    $invalid_settings = $component->validate_settings(new WP_Error(), [
        'mds-pixel-base' => 'wp-json',
        'mds-pixel-slug-structure' => '%placement_id%',
    ], []);
    if (!$invalid_settings->has_errors()) {
        throw new RuntimeException('Reserved advertiser page bases were not rejected.');
    }
    if ($url !== $component->canonical('', get_post($post_id))) {
        throw new RuntimeException('The advertiser page canonical filter did not return its stable public URL.');
    }
    $sitemap_types = $component->sitemap_post_types(['mds_advertiser' => get_post_type_object('mds_advertiser')]);
    if (!isset($sitemap_types['mds_advertiser'])) {
        throw new RuntimeException('Indexable advertiser pages were removed from WordPress sitemaps.');
    }

    $payload_method = new ReflectionMethod(new GridAjax(), 'placement_payload');
    $payload_method->setAccessible(true);
    $payload = $payload_method->invoke(new GridAjax(), $manager->public_source($placement_id), array_merge($settings, [
        'popup-template' => '<div>%advertiser_page_link%</div>',
    ]));
    if ($url !== ($payload['advertiser_page_url'] ?? '') || false === strpos((string) ($payload['popover_html'] ?? ''), 'Read advertiser profile')) {
        throw new RuntimeException('Grid popups did not receive the configured advertiser page action: ' . wp_json_encode([
            'expected_url' => $url,
            'actual_url' => $payload['advertiser_page_url'] ?? '',
            'html' => $payload['popover_html'] ?? '',
        ]));
    }

    $settings['exclude-from-search'] = 'yes';
    update_option('mds3_settings', $settings, false);
    $sitemap_types = $component->sitemap_post_types(['mds_advertiser' => get_post_type_object('mds_advertiser')]);
    if (isset($sitemap_types['mds_advertiser'])) {
        throw new RuntimeException('Noindex advertiser pages remained in WordPress sitemaps.');
    }
    $settings['exclude-from-search'] = 'no';
    update_option('mds3_settings', $settings, false);

    $old_slug = (string) get_post_field('post_name', $post_id);
    $settings['mds-pixel-slug-structure'] = 'placement-%placement_id%';
    update_option('mds3_settings', $settings, false);
    $manager->synchronize($placement_id);
    if ($old_slug !== get_post_field('post_name', $post_id)) {
        throw new RuntimeException('A normal placement save changed its URL before the explicit slug migration.');
    }
    $migration = $manager->migrate_slugs(10, 0);
    if (empty($migration['changed']) || 'placement-' . $placement_id !== get_post_field('post_name', $post_id)) {
        throw new RuntimeException('The explicit advertiser slug migration did not apply the configured pattern.');
    }
    if (!in_array($old_slug, get_post_meta($post_id, '_wp_old_slug', false), true)) {
        throw new RuntimeException('The explicit slug migration did not preserve the exact prior slug for a 301 redirect.');
    }

    $wpdb->update(DB::table('placements'), ['status' => 'archived'], ['id' => $placement_id]);
    $manager->synchronize($placement_id);
    if ('draft' !== get_post_status($post_id) || '' !== Placements::public_url($placement_id) || null !== Placements::public_view($placement_id)) {
        throw new RuntimeException('A non-public placement remained accessible through its page facade.');
    }

    echo "Advertiser page fixture passed.\n";
} finally {
    $wpdb->query('ROLLBACK');
    update_option('mds3_settings', $original_settings, false);
    if (null === $original_history) {
        delete_option('mds3_advertiser_page_base_history');
    } else {
        update_option('mds3_advertiser_page_base_history', $original_history, false);
    }
    wp_cache_flush();
}
