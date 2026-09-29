<?php
/**
 * WP-CLI fixture: popup full-page link, including legacy MDS2 page fallback.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/popup-page-link-fixture.php
 */

use MillionDollarScript\V3\Grid\GridAjax;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\AdvertiserPageManager;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Migration\MigrationMap;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

$original_settings = get_option('mds3_settings', []);
$original_settings = is_array($original_settings) ? $original_settings : [];

function popup_fixture_settings(array $overrides = []) {
    $settings = wp_parse_args(get_option('mds3_settings', []), SettingsSchema::defaults());
    $settings['mds-pixel-template'] = 'no';
    $settings['advertiser-page-popup-link'] = 'yes';
    return array_merge($settings, $overrides);
}

function popup_fixture_payload($placement, array $settings, array $legacy_urls = []) {
    static $ref = null;
    if (null === $ref) {
        $ref = new ReflectionMethod(GridAjax::class, 'placement_payload');
        $ref->setAccessible(true);
    }

    return $ref->invoke(new GridAjax(), $placement, $settings, null, null, $legacy_urls);
}

function popup_fixture_legacy_helper(array $settings, array $placement_ids) {
    static $ref = null;
    if (null === $ref) {
        $ref = new ReflectionMethod(GridAjax::class, 'legacy_popup_page_urls');
        $ref->setAccessible(true);
    }

    return $ref->invoke(new GridAjax(), $settings, $placement_ids);
}

function get_grid_fixture_setting($grid_id, $key) {
    $grid = (new GridRepository())->find(absint($grid_id));

    return $grid ? ($grid->settings()[$key] ?? null) : null;
}

$grid = null;
$placement_id = 0;
$legacy_post_id = 0;
$fixture_attachment = 0;

try {
    // Stand-in for the MDS2 pixel CPT, which the migration leaves live.
    if (!post_type_exists('mds-pixel')) {
        register_post_type('mds-pixel', [
            'label' => 'Popup Fixture Pixel',
            'public' => true,
            'supports' => ['title'],
        ]);
    }

    $legacy_post_id = wp_insert_post([
        'post_type' => 'mds-pixel',
        'post_status' => 'publish',
        'post_title' => 'Popup Fixture Pixel Page',
        'post_name' => 'popup-page-link-fixture-' . wp_generate_password(6, false, false),
    ]);
    if (is_wp_error($legacy_post_id)) {
        throw new RuntimeException('Could not create legacy fixture pixel post: ' . $legacy_post_id->get_error_message());
    }

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

    // Map the placement to the legacy pixel post the way the migration does.
    (new MigrationMap())->remember($GLOBALS['wpdb']->prefix . 'mds_', 'placement', '900001', 'placement', $placement_id, ['legacy_ad_id' => $legacy_post_id]);

    $expected_legacy_url = (string) get_permalink($legacy_post_id);
    $legacy_urls = (new AdvertiserPageManager())->legacy_public_urls([$placement_id]);
    if (!isset($legacy_urls[$placement_id]) || $expected_legacy_url !== (string) $legacy_urls[$placement_id]) {
        throw new RuntimeException('Legacy page URL lookup did not resolve the mapped MDS2 pixel page.');
    }

    $settings = popup_fixture_settings();
    $payload = popup_fixture_payload($placement, $settings, $legacy_urls);
    if ($expected_legacy_url !== (string) ($payload['advertiser_page_url'] ?? '')) {
        throw new RuntimeException('Popup payload did not fall back to the legacy MDS2 page URL: ' . var_export($payload['advertiser_page_url'] ?? null, true));
    }

    $helper_urls = popup_fixture_legacy_helper($settings, [$placement_id]);
    if ($helper_urls !== $legacy_urls) {
        throw new RuntimeException('Batch legacy lookup helper did not return the mapped URLs.');
    }

    // With MDS 3.0 pages enabled there is no legacy fallback.
    $enabled_settings = popup_fixture_settings(['mds-pixel-template' => 'yes']);
    if ([] !== popup_fixture_legacy_helper($enabled_settings, [$placement_id])) {
        throw new RuntimeException('Legacy fallback leaked while MDS 3.0 advertiser pages are enabled.');
    }

    // The popup link toggle still gates everything.
    $disabled_settings = popup_fixture_settings(['advertiser-page-popup-link' => 'no']);
    $disabled_payload = popup_fixture_payload($placement, $disabled_settings);
    if ('' !== (string) ($disabled_payload['advertiser_page_url'] ?? '')) {
        throw new RuntimeException('Popup page link ignored the advertiser-page-popup-link=no setting.');
    }

    // An unmapped placement has no full page at all.
    $unmapped_id = (new PlacementRepository())->create([
        'grid_id' => $grid->id(),
        'attachment_id' => $fixture_attachment,
        'x' => 1,
        'y' => 0,
        'width' => 1,
        'height' => 1,
        'status' => 'active',
    ]);
    $unmapped = (new PlacementRepository())->find($unmapped_id);
    $unmapped_payload = popup_fixture_payload($unmapped, $settings);
    if ('' !== (string) ($unmapped_payload['advertiser_page_url'] ?? '')) {
        throw new RuntimeException('Unmapped placement resolved an unexpected full page URL.');
    }
    $GLOBALS['wpdb']->delete(DB::table('placements'), ['id' => absint($unmapped_id)]);

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

    if (10 !== \MillionDollarScript\V3\Media\PlacementFieldContract::popup_display_max_chars(['popup-text-max-chars' => 20], ['popup_display_max_chars' => '10'])) {
        throw new RuntimeException('Per-grid popup limit did not override the global limit.');
    }
    if (20 !== \MillionDollarScript\V3\Media\PlacementFieldContract::popup_display_max_chars(['popup-text-max-chars' => 20], ['popup_display_max_chars' => ''])) {
        throw new RuntimeException('Blank per-grid popup limit should inherit the global limit.');
    }

    $override_limit = \MillionDollarScript\V3\Media\PlacementFieldContract::popup_display_max_chars(
        popup_fixture_settings(['popup-text-max-chars' => 30]),
        ['popup_display_max_chars' => '11']
    );
    $grid_override_payload = popup_fixture_payload($limited_placement, popup_fixture_settings(['popup-text-max-chars' => $override_limit]));
    if ('Alpha bravo…' !== (string) $grid_override_payload['popup_text']) {
        throw new RuntimeException('Per-grid popup limit was not applied to the payload: ' . var_export($grid_override_payload['popup_text'], true));
    }

    // The full-page advertiser view renders from the stored text, not the popup payload.
    if ($long_text !== \MillionDollarScript\V3\Grid\PopupText::plain($long_text)) {
        throw new RuntimeException('Advertiser page text should stay intact.');
    }

    // Placement click mode: global setting, per-grid override, and the popup fallback.
    $click_contract = \MillionDollarScript\V3\Media\PlacementFieldContract::class;
    if ('popup' !== $click_contract::advertiser_page_click_mode(['advertiser-page-click-mode' => 'popup'], ['advertiser_page_click_mode' => ''])) {
        throw new RuntimeException('Blank per-grid click mode should inherit the global popup mode.');
    }
    if ('page' !== $click_contract::advertiser_page_click_mode(['advertiser-page-click-mode' => 'popup'], ['advertiser_page_click_mode' => 'page'])) {
        throw new RuntimeException('Per-grid click mode did not override the global setting.');
    }
    if ('popup' !== $click_contract::advertiser_page_click_mode(['advertiser-page-click-mode' => 'popup'], ['advertiser_page_click_mode' => 'bogus'])) {
        throw new RuntimeException('Unknown per-grid click mode should fall back to the global setting.');
    }

    $default_click_payload = popup_fixture_payload($placement, $settings, $legacy_urls);
    if ('popup' !== (string) ($default_click_payload['advertiser_page_click'] ?? '')) {
        throw new RuntimeException('Placement clicks should default to the popup.');
    }

    $page_click_payload = popup_fixture_payload($placement, popup_fixture_settings(['advertiser-page-click-mode' => 'page']), $legacy_urls);
    if ('page' !== (string) ($page_click_payload['advertiser_page_click'] ?? '') || $expected_legacy_url !== (string) ($page_click_payload['advertiser_page_url'] ?? '')) {
        throw new RuntimeException('Page click mode did not expose the advertiser page: ' . var_export($page_click_payload['advertiser_page_click'] ?? null, true));
    }

    $page_click_unmapped_payload = popup_fixture_payload($unmapped, popup_fixture_settings(['advertiser-page-click-mode' => 'page']));
    if ('popup' !== (string) ($page_click_unmapped_payload['advertiser_page_click'] ?? '')) {
        throw new RuntimeException('A placement with no published page should keep the popup in page click mode.');
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

    echo "popup-page-link: ok (legacy fallback -> " . get_permalink($legacy_post_id) . "); unmapped placements stay unlinked\n";
} finally {
    global $wpdb;

    if ($placement_id) {
        $wpdb->delete(DB::table('placements'), ['id' => absint($placement_id)]);
    }
    $wpdb->delete(DB::table('migration_map'), ['mds3_id' => absint($placement_id)], ['%d']);
    if ($grid && !is_wp_error($grid)) {
        (new GridRepository())->delete($grid->id());
    }
    if ($legacy_post_id) {
        wp_delete_post($legacy_post_id, true);
    }
    if ($fixture_attachment) {
        wp_delete_post($fixture_attachment, true);
    }
    update_option('mds3_settings', $original_settings, false);
}
