<?php
/**
 * WP-CLI fixture: what a grid popup and a placement click do about the advertiser's
 * own page, and how far the popup text is shortened.
 *
 * This used to carry a second story as well - falling back to the older plugin's
 * pixel page when a migrated ad had no MDS 3.0 page. That fallback was removed on
 * purpose (MDS 3.0 does not read Million Dollar Script 2 tables outside a
 * migration), so the page link now resolves through the MDS 3.0 advertiser page
 * the site itself publishes. What remains protected here is the part that has
 * always mattered: the popup link toggle gates the link, a placement that is not
 * on the grid gets no link, a limited popup is shortened at a word boundary while
 * the advertiser page keeps the full text, and a click mode that asks for a page
 * the site does not have falls back to the popup rather than to a dead link.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/popup-page-link-fixture.php
 */

use MillionDollarScript\V3\Grid\GridAjax;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Grid\PopupText;
use MillionDollarScript\V3\Media\AdvertiserPageManager;
use MillionDollarScript\V3\Media\PlacementFieldContract;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

$original_settings = get_option('mds3_settings', []);
$original_settings = is_array($original_settings) ? $original_settings : [];

/**
 * Site settings as an advertiser-page site would have them, with one override
 * applied per call.
 */
function popup_fixture_settings(array $overrides = []) {
    $settings = wp_parse_args((array) get_option('mds3_settings', []), SettingsSchema::defaults());
    $settings['mds-pixel-template'] = 'yes';
    $settings['advertiser-page-popup-link'] = 'yes';

    return array_merge($settings, $overrides);
}

/**
 * The grid payload for one placement, through the class that serves it.
 */
function popup_fixture_payload(array $placement, array $settings) {
    static $ref = null;
    if (null === $ref) {
        $ref = new ReflectionMethod(GridAjax::class, 'placement_payload');
        $ref->setAccessible(true);
    }

    return $ref->invoke(new GridAjax(), $placement, $settings);
}

function get_grid_fixture_setting($grid_id, $key) {
    $grid = (new GridRepository())->find(absint($grid_id));

    return $grid ? ($grid->settings()[$key] ?? null) : null;
}

$grid = null;
$placement_id = 0;
$off_grid_id = 0;
$page_post_id = 0;
$fixture_attachment = 0;

try {
    // Advertiser pages are a site switch, and the page link is only published for
    // a site that has it on.
    $start_settings = wp_parse_args($original_settings, SettingsSchema::defaults());
    $start_settings['mds-pixel-template'] = 'yes';
    update_option('mds3_settings', $start_settings, false);

    $fixture_attachment = wp_insert_attachment(['post_type' => 'attachment', 'post_status' => 'inherit'], '', 0);
    if (is_wp_error($fixture_attachment)) {
        throw new RuntimeException('Could not create fixture attachment: ' . $fixture_attachment->get_error_message());
    }

    $grid = (new GridRepository())->create([
        'title' => 'Popup Page Link Fixture',
        'width' => 10,
        'height' => 10,
        'price_per_block' => 1,
        'currency' => 'USD',
        'status' => 'active',
    ]);
    if (is_wp_error($grid)) {
        throw new RuntimeException('Could not create fixture grid: ' . $grid->get_error_message());
    }

    $placement_id = (new PlacementRepository())->create([
        'grid_id' => $grid->id(),
        'attachment_id' => $fixture_attachment,
        'x' => 0,
        'y' => 0,
        'width' => 1,
        'height' => 1,
        'status' => 'active',
    ]);
    if (is_wp_error($placement_id)) {
        throw new RuntimeException('Could not create fixture placement: ' . $placement_id->get_error_message());
    }
    $placement = (new PlacementRepository())->find($placement_id);
    if (!$placement) {
        throw new RuntimeException('Fixture placement row was not found.');
    }

    // The site's own page for this ad, published the way the plugin publishes it.
    $page_post_id = (new AdvertiserPageManager())->synchronize($placement_id);
    if (is_wp_error($page_post_id)) {
        throw new RuntimeException('Could not publish the fixture advertiser page: ' . $page_post_id->get_error_message());
    }
    $expected_page_url = (string) get_permalink($page_post_id);
    if ('' === $expected_page_url) {
        throw new RuntimeException('The fixture advertiser page has no URL.');
    }
    if ($expected_page_url !== (string) (new AdvertiserPageManager())->public_url($placement_id)) {
        throw new RuntimeException('The advertiser page for a placement did not resolve to the published page.');
    }

    // A popup on a site with the link switched on points at that page.
    $payload = popup_fixture_payload($placement, popup_fixture_settings());
    if ($expected_page_url !== (string) ($payload['advertiser_page_url'] ?? '')) {
        throw new RuntimeException('Popup payload did not link the advertiser page: ' . var_export($payload['advertiser_page_url'] ?? null, true));
    }

    // The popup link toggle still gates the link even when the page exists.
    $off_payload = popup_fixture_payload($placement, popup_fixture_settings(['advertiser-page-popup-link' => 'no']));
    if ('' !== (string) ($off_payload['advertiser_page_url'] ?? '')) {
        throw new RuntimeException('Popup page link ignored the advertiser-page-popup-link=no setting.');
    }

    // An ad that is not on the grid has no page to link, so it stays unlinked.
    $off_grid_id = (new PlacementRepository())->create([
        'grid_id' => $grid->id(),
        'attachment_id' => $fixture_attachment,
        'x' => 1,
        'y' => 0,
        'width' => 1,
        'height' => 1,
        'status' => 'pending',
    ]);
    if (is_wp_error($off_grid_id)) {
        throw new RuntimeException('Could not create the off-grid fixture placement: ' . $off_grid_id->get_error_message());
    }
    $off_grid = (new PlacementRepository())->find($off_grid_id);
    $off_grid_payload = popup_fixture_payload($off_grid, popup_fixture_settings());
    if ('' !== (string) ($off_grid_payload['advertiser_page_url'] ?? '')) {
        throw new RuntimeException('A placement that is not on the grid resolved an advertiser page URL.');
    }

    // Popup character limit: global setting, per-grid override, and the advertiser page keeps the full text.
    $long_text = 'Alpha bravo charlie delta echo foxtrot golf hotel india juliett';
    $limited_placement = array_merge($placement, ['popup_text' => $long_text]);

    $global_limit_payload = popup_fixture_payload($limited_placement, popup_fixture_settings(['popup-text-max-chars' => 30]));
    if ('Alpha bravo charlie delta…' !== (string) $global_limit_payload['popup_text']) {
        throw new RuntimeException('Global popup limit did not shorten at a word boundary: ' . var_export($global_limit_payload['popup_text'], true));
    }

    $no_limit_payload = popup_fixture_payload($limited_placement, popup_fixture_settings(['popup-text-max-chars' => 0]));
    if ($long_text !== (string) $no_limit_payload['popup_text']) {
        throw new RuntimeException('popup-text-max-chars=0 should leave the popup text intact.');
    }

    if (10 !== PlacementFieldContract::popup_display_max_chars(['popup-text-max-chars' => 20], ['popup_display_max_chars' => '10'])) {
        throw new RuntimeException('Per-grid popup limit did not override the global limit.');
    }
    if (20 !== PlacementFieldContract::popup_display_max_chars(['popup-text-max-chars' => 20], ['popup_display_max_chars' => ''])) {
        throw new RuntimeException('Blank per-grid popup limit should inherit the global limit.');
    }

    $override_limit = PlacementFieldContract::popup_display_max_chars(
        popup_fixture_settings(['popup-text-max-chars' => 30]),
        ['popup_display_max_chars' => '11']
    );
    $grid_override_payload = popup_fixture_payload($limited_placement, popup_fixture_settings(['popup-text-max-chars' => $override_limit]));
    if ('Alpha bravo…' !== (string) $grid_override_payload['popup_text']) {
        throw new RuntimeException('Per-grid popup limit was not applied to the payload: ' . var_export($grid_override_payload['popup_text'], true));
    }

    // The full-page advertiser view renders from the stored text, not the popup payload.
    if ($long_text !== PopupText::plain($long_text)) {
        throw new RuntimeException('Advertiser page text should stay intact.');
    }

    // Placement click mode: global setting, per-grid override, and the popup fallback.
    if ('popup' !== PlacementFieldContract::advertiser_page_click_mode(['advertiser-page-click-mode' => 'popup'], ['advertiser_page_click_mode' => ''])) {
        throw new RuntimeException('Blank per-grid click mode should inherit the global popup mode.');
    }
    if ('page' !== PlacementFieldContract::advertiser_page_click_mode(['advertiser-page-click-mode' => 'popup'], ['advertiser_page_click_mode' => 'page'])) {
        throw new RuntimeException('Per-grid click mode did not override the global setting.');
    }
    if ('popup' !== PlacementFieldContract::advertiser_page_click_mode(['advertiser-page-click-mode' => 'popup'], ['advertiser_page_click_mode' => 'bogus'])) {
        throw new RuntimeException('Unknown per-grid click mode should fall back to the global setting.');
    }

    $default_click_payload = popup_fixture_payload($placement, popup_fixture_settings());
    if ('popup' !== (string) ($default_click_payload['advertiser_page_click'] ?? '')) {
        throw new RuntimeException('Placement clicks should default to the popup.');
    }

    $page_click_payload = popup_fixture_payload($placement, popup_fixture_settings(['advertiser-page-click-mode' => 'page']));
    if ('page' !== (string) ($page_click_payload['advertiser_page_click'] ?? '') || $expected_page_url !== (string) ($page_click_payload['advertiser_page_url'] ?? '')) {
        throw new RuntimeException('Page click mode did not expose the advertiser page: ' . var_export($page_click_payload['advertiser_page_click'] ?? null, true));
    }

    // Asking for a page this site does not publish must fall back to the popup,
    // not send the visitor to a link that goes nowhere.
    $page_click_off_grid_payload = popup_fixture_payload($off_grid, popup_fixture_settings(['advertiser-page-click-mode' => 'page']));
    if ('popup' !== (string) ($page_click_off_grid_payload['advertiser_page_click'] ?? '') || '' !== (string) ($page_click_off_grid_payload['advertiser_page_url'] ?? '')) {
        throw new RuntimeException('A placement with no published page did not keep the popup in page click mode.');
    }

    $stored_click_settings = (new GridRepository())->update($grid->id(), ['advertiser_page_click_mode' => 'bogus']);
    if (is_wp_error($stored_click_settings)) {
        throw new RuntimeException('Could not store the fixture click mode: ' . $stored_click_settings->get_error_message());
    }
    if ('' !== (string) get_grid_fixture_setting($grid->id(), 'advertiser_page_click_mode')) {
        throw new RuntimeException('An unknown per-grid click mode should be stored as inherit.');
    }
    (new GridRepository())->update($grid->id(), ['advertiser_page_click_mode' => 'page']);
    if ('page' !== (string) get_grid_fixture_setting($grid->id(), 'advertiser_page_click_mode')) {
        throw new RuntimeException('The per-grid click mode did not persist.');
    }

    echo 'popup-page-link: ok (page link -> ' . $expected_page_url . "); ads that are not on the grid stay unlinked\n";
} finally {
    global $wpdb;

    if ($placement_id) {
        $wpdb->delete(DB::table('placements'), ['id' => absint($placement_id)]);
    }
    if ($off_grid_id) {
        $wpdb->delete(DB::table('placements'), ['id' => absint($off_grid_id)]);
    }
    if ($page_post_id && !is_wp_error($page_post_id)) {
        wp_delete_post(absint($page_post_id), true);
    }
    if ($grid && !is_wp_error($grid)) {
        (new GridRepository())->delete($grid->id());
    }
    if ($fixture_attachment) {
        wp_delete_post($fixture_attachment, true);
    }
    update_option('mds3_settings', $original_settings, false);
}
