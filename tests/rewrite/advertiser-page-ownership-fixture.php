<?php
/**
 * One public page per placement.
 *
 * A customer whose first migration attempt stopped halfway keeps the pages that
 * attempt made, and an older build could leave a page whose _placement_id meta
 * names one placement while another placement's row already points at that page.
 * Claiming the page anyway failed the unique link: the second ad was left with no
 * page at all and the first ad's heading was overwritten with the second's. On a
 * real migrated site that cost 35 of 101 ads their page (Vikunja #30).
 */

use MillionDollarScript\Media\Placements;
use MillionDollarScript\V3\Media\AdvertiserPageManager;
use MillionDollarScript\V3\Media\AdvertiserPages;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$original_settings = get_option('mds3_settings', []);
$original_settings = is_array($original_settings) ? $original_settings : [];
$wpdb->query('START TRANSACTION');

try {
    update_option('mds3_settings', array_merge($original_settings, [
        'mds-pixel-template' => 'yes',
        'mds-pixel-base' => 'advertisers',
        'mds-pixel-slug-structure' => '%title%-%placement_id%',
    ]), false);
    (new AdvertiserPages())->register_post_type();

    $grid_id = absint($wpdb->get_var('SELECT id FROM ' . DB::ident(DB::table('grids')) . " WHERE status = 'active' ORDER BY id ASC LIMIT 1"));
    if (!$grid_id) {
        throw new RuntimeException('An active grid is required for the advertiser-page ownership fixture.');
    }

    $now = current_time('mysql', true);
    $wpdb->insert(DB::table('orders'), [
        'order_key' => wp_generate_uuid4(),
        'user_id' => get_current_user_id() ?: 1,
        'email' => 'ownership-fixture@example.test',
        'status' => 'paid',
        'currency' => 'USD',
        'subtotal' => 1,
        'total' => 1,
        'metadata' => wp_json_encode(['ownership_fixture' => true]),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $order_id = absint($wpdb->insert_id);

    $placement = static function ($x, $alt_text, $description, $sort_order) use ($wpdb, $grid_id, $order_id, $now) {
        $wpdb->insert(DB::table('placements'), [
            'grid_id' => $grid_id,
            'order_id' => $order_id,
            'user_id' => get_current_user_id() ?: 1,
            'attachment_id' => 0,
            'x' => $x,
            'y' => 0,
            'width' => 10,
            'height' => 10,
            'fit_mode' => 'cover',
            'link_url' => 'https://example.com/advertiser',
            'alt_text' => $alt_text,
            'popup_text' => $description,
            'status' => 'active',
            'sort_order' => $sort_order,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return absint($wpdb->insert_id);
    };

    $placement_a = $placement(0, 'Ownership Fixture First Ad', '<p>First fixture description.</p>', 0);
    $manager = new AdvertiserPageManager();
    $page_a = $manager->synchronize($placement_a);
    if (is_wp_error($page_a) || !$page_a) {
        throw new RuntimeException('The first placement did not get an advertiser page: '
            . (is_wp_error($page_a) ? $page_a->get_error_message() : 'no page'));
    }

    $placement_b = $placement(10, 'Ownership Fixture Second Ad', '<p>Second fixture description.</p>', 1);

    // The state an interrupted migration leaves behind: the page belongs to the
    // first placement, but its meta names the second one.
    update_post_meta(absint($page_a), '_million_dollar_script_placement_id', $placement_b);
    wp_cache_flush();

    $title_a = (string) get_post_field('post_title', $page_a);
    $slug_a = (string) get_post_field('post_name', $page_a);
    $page_b = $manager->synchronize($placement_b);
    if (is_wp_error($page_b) || !$page_b) {
        throw new RuntimeException('The second placement was left without a page because its mapped page '
            . 'was already owned by the first: ' . (is_wp_error($page_b) ? $page_b->get_error_message() : 'no page'));
    }
    if (absint($page_b) === absint($page_a)) {
        throw new RuntimeException('Two placements were pointed at one advertiser page.');
    }

    $link = static function ($placement_id) use ($wpdb) {
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT public_post_id FROM ' . DB::ident(DB::table('placements')) . ' WHERE id = %d',
            absint($placement_id)
        ));
    };
    if ($link($placement_a) !== absint($page_a) || $link($placement_b) !== absint($page_b)) {
        throw new RuntimeException('The two placements did not each keep their own page: ' . wp_json_encode([
            'first_row' => $link($placement_a),
            'first_page' => $page_a,
            'second_row' => $link($placement_b),
            'second_page' => $page_b,
        ]));
    }
    if ($title_a !== (string) get_post_field('post_title', $page_a) || $slug_a !== (string) get_post_field('post_name', $page_a)) {
        throw new RuntimeException("The second placement overwrote the first placement's page heading or URL.");
    }
    if (absint(get_post_meta($page_a, '_million_dollar_script_placement_id', true)) !== $placement_a) {
        throw new RuntimeException('A disputed page kept a _placement_id meta naming another placement.');
    }
    if (absint(get_post_meta($page_b, '_million_dollar_script_placement_id', true)) !== $placement_b) {
        throw new RuntimeException("The second placement's own page did not record its owner.");
    }
    if ('' === Placements::public_url($placement_b) || '' === Placements::public_url($placement_a)) {
        throw new RuntimeException('Either placement lost its public page URL.');
    }
    if (Placements::public_url($placement_a) === Placements::public_url($placement_b)) {
        throw new RuntimeException('Two placements published the same advertiser page URL.');
    }

    echo "Advertiser page ownership fixture passed.\n";
} finally {
    $wpdb->query('ROLLBACK');
    update_option('mds3_settings', $original_settings, false);
    wp_cache_flush();
}
