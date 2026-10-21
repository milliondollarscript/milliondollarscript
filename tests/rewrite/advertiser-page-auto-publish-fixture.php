<?php
/**
 * WP-CLI fixture: the advertiser-page link must appear without anyone clicking Synchronize.
 *
 * Why this exists (llm-task 1d7a3e04): a site owner enabled "Enable Individual Advertiser
 * Pages" and "Link Popups To Advertiser Pages", looked at a live ad, and saw no link. The link
 * only appeared after visiting Million Dollar Script > Settings > Advertiser Pages and pressing
 * "Synchronize advertiser pages". A page for an ad is a thing the site has to keep up by itself:
 * saving the setting publishes pages for the ads already live, and an ad becoming public publishes
 * its own page. The bulk button stays as a repair tool, not as a required step.
 *
 * What this pins down:
 *   1. with the feature off, nothing is published - not by a settings save, not by an ad going public;
 *   2. turning the feature on publishes pages for the ads already live, from the settings save alone;
 *   3. an ad becoming public later publishes its own page, with no admin involved;
 *   4. doing it twice does not create a second page;
 *   5. an ad with no page has no link at all - there is no fallback destination to fall back to;
 *   6. the settings copy does not send people to the older plugin's pages.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/advertiser-page-auto-publish-fixture.php
 */

use MillionDollarScript\V3\Media\AdvertiserPageManager;
use MillionDollarScript\V3\Media\AdvertiserPages;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    echo "This fixture must run inside a WordPress root (wp eval-file).\n";
    exit(1);
}

global $wpdb;

$original_settings = get_option('mds3_settings', []);
$original_settings = is_array($original_settings) ? $original_settings : [];

$fails = [];
$check = static function ($ok, $message) use (&$fails) {
    if (!$ok) {
        $fails[] = $message;
    }
};

$settings_with = static function (array $changes) use ($original_settings) {
    return array_merge($original_settings, [
        'mds-pixel-template' => 'no',
        'exclude-from-search' => 'yes',
        'mds-pixel-base' => 'advertisers-auto-fixture',
        'mds-pixel-slug-structure' => '%title%-%placement_id%',
        'advertiser-page-popup-link' => 'yes',
        'advertiser-page-popup-label' => 'Read advertiser profile',
        'advertiser-page-link-target' => '_self',
    ], $changes);
};

$save_settings = static function (array $settings, array $previous) {
    update_option('mds3_settings', $settings, false);
    wp_cache_flush();
    do_action('million-dollar-script/admin/settings/saved', $settings, $settings, $previous);
};

/**
 * An ad in the state the site would put it in when an order is paid for it.
 */
$make_placement = static function ($grid_id, $status, $title) {
    global $wpdb;
    $now = current_time('mysql', true);

    $wpdb->insert(DB::table('orders'), [
        'order_key' => wp_generate_uuid4(),
        'user_id' => get_current_user_id() ?: 1,
        'email' => 'auto-advertiser-fixture@example.test',
        'status' => 'paid',
        'currency' => 'USD',
        'subtotal' => 1,
        'total' => 1,
        'metadata' => wp_json_encode(['auto_advertiser_fixture' => true]),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $order_id = absint($wpdb->insert_id);

    $wpdb->insert(DB::table('placements'), [
        'grid_id' => absint($grid_id),
        'order_id' => $order_id,
        'user_id' => get_current_user_id() ?: 1,
        'attachment_id' => 0,
        'x' => 0,
        'y' => 0,
        'width' => 10,
        'height' => 10,
        'fit_mode' => 'cover',
        'link_url' => 'https://example.com/advertiser',
        'alt_text' => $title,
        'popup_text' => '<p>Public fixture description.</p>',
        'status' => $status,
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return [absint($wpdb->insert_id), $order_id];
};

$page_id_for = static function ($placement_id) {
    global $wpdb;

    return absint($wpdb->get_var($wpdb->prepare('SELECT public_post_id FROM ' . DB::ident(DB::table('placements')) . ' WHERE id = %d', absint($placement_id))));
};

$page_count_for = static function ($placement_id) {
    $found = get_posts([
        'post_type' => AdvertiserPageManager::POST_TYPE,
        'post_status' => 'any',
        'numberposts' => 5,
        'meta_key' => '_million_dollar_script_placement_id',
        'meta_value' => absint($placement_id),
        'fields' => 'ids',
    ]);

    return count($found);
};

$wpdb->query('START TRANSACTION');

try {
    $grid_id = absint($wpdb->get_var('SELECT id FROM ' . DB::ident(DB::table('grids')) . " WHERE status = 'active' ORDER BY id ASC LIMIT 1"));
    if (!$grid_id) {
        throw new RuntimeException('An active grid is required for this fixture.');
    }

    $pages = new AdvertiserPages();
    $pages->register();
    $manager = new AdvertiserPageManager();

    $off = $settings_with([ 'mds-pixel-template' => 'no' ]);
    $on = $settings_with([ 'mds-pixel-template' => 'yes' ]);
    $save_settings($off, $off);

    // 1. While the feature is off, an ad going public publishes nothing. The placement row is
    // read directly: public_url() would publish the page itself, which is not what is being asked.
    list($held_id, $held_order) = $make_placement($grid_id, 'active', 'Auto Fixture Held Ad');
    do_action('million-dollar-script/placement/saved', ['id' => $held_id], ['id' => $held_order], []);
    $check(0 === $page_id_for($held_id), 'an ad got a public page while advertiser pages are turned off');

    // 2. Turning the feature on publishes the ad that is already live, from the save alone.
    $save_settings($on, $off);
    $page_id = $page_id_for($held_id);
    $check($page_id > 0, 'enabling advertiser pages left an ad that was already live without a page - the link only appears after someone clicks Synchronize');
    if ($page_id > 0) {
        $check('publish' === get_post_status($page_id), 'the page published by the settings save is not published: ' . var_export(get_post_status($page_id), true));
        $check(0 === (int) get_post_field('post_author', $page_id), 'the published advertiser page names an administrator as its author');
        $page_url = (string) get_permalink($page_id);
        $check(false !== strpos($page_url, 'auto-fixture-held-ad-' . $held_id), 'the page published by the settings save does not follow the configured slug pattern: ' . $page_url);

        // 4. A second signal about the same ad does not create a second page.
        do_action('million-dollar-script/placement/saved', ['id' => $held_id], ['id' => $held_order], []);
        $manager->synchronize($held_id);
        $manager->synchronize($held_id);
        $check(1 === $page_count_for($held_id), 'the same ad ended up with ' . $page_count_for($held_id) . ' advertiser pages');
        $check($page_id === $page_id_for($held_id), 'repeating the publish changed where the ad page lives');
    }

    // 3. An ad that becomes public later publishes its own page, with no admin in the loop and no
    // one having to open the grid for the page to exist.
    list($new_id, $new_order) = $make_placement($grid_id, 'active', 'Auto Fixture New Ad');
    $check(0 === $page_id_for($new_id), 'a new ad already had a page before it was saved');
    do_action('million-dollar-script/placement/saved', ['id' => $new_id], ['id' => $new_order], []);
    $check($page_id_for($new_id) > 0, 'saving an ad that is live did not give it a page; the link needs the bulk Synchronize button');

    // 5. An ad with no page has no link, because there is nothing else to link to.
    list($draft_id, $draft_order) = $make_placement($grid_id, 'pending_payment', 'Auto Fixture Not Public Ad');
    do_action('million-dollar-script/placement/saved', ['id' => $draft_id], ['id' => $draft_order], []);
    $check(0 === $page_id_for($draft_id), 'an ad that is not public was given a public advertiser page');
    $check('' === $manager->public_url($draft_id), 'a popup would be given a link for an ad that is not public');

    // 6. The copy in the advertiser-pages settings does not point at the older plugin's pages.
    $descriptions = [];
    $advertiser_group = SettingsSchema::groups()['Advertiser Pages'] ?? [];
    foreach ($advertiser_group as $field) {
        if (is_array($field) && isset($field['key'])) {
            $descriptions[(string) $field['key']] = strtolower((string) ($field['description'] ?? ''));
        }
    }
    foreach ($descriptions as $key => $description) {
        $check(false === strpos($description, 'mds2') && false === strpos($description, 'million dollar script 2'), 'the ' . $key . ' setting text talks about the older plugin: ' . $description);
        $check(false === strpos($description, 'pixel page'), 'the ' . $key . ' setting text describes a pixel-page destination: ' . $description);
    }
    $popup_copy = $descriptions['advertiser-page-popup-link'] ?? null;
    $check(null !== $popup_copy, 'the Link Popups To Advertiser Pages setting is not in the settings schema');
    $check(false !== stripos((string) $popup_copy, 'public advertiser page'), 'the popup link setting no longer says where its link goes: ' . $popup_copy);
} catch (Throwable $e) {
    $wpdb->query('ROLLBACK');
    update_option('mds3_settings', $original_settings, false);
    wp_cache_flush();
    echo 'advertiser page auto-publish fixture errored: ' . $e->getMessage() . "\n";
    exit(1);
}

$wpdb->query('ROLLBACK');
update_option('mds3_settings', $original_settings, false);
delete_option('mds3_advertiser_page_sync_cursor');
wp_cache_flush();

if ($fails) {
    echo "advertiser page auto-publish fixture FAILED\n";
    foreach ($fails as $fail) {
        echo ' - ' . $fail . "\n";
    }
    exit(1);
}

echo "advertiser page auto-publish fixture passed.\n";
