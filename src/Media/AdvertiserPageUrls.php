<?php
/**
 * Public advertiser page URL policy.
 *
 * @package MillionDollarScript\V3\Media
 */

namespace MillionDollarScript\V3\Media;

use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

final class AdvertiserPageUrls {

    private const BASE_HISTORY_OPTION = 'mds3_advertiser_page_base_history';
    private const MAX_BASE_HISTORY = 20;

    public static function settings() {
        $settings = get_option('mds3_settings', []);

        return wp_parse_args(is_array($settings) ? $settings : [], SettingsSchema::defaults());
    }

    public static function enabled(array $settings = []) {
        $settings = $settings ?: self::settings();

        return 'yes' === SettingsSchema::sanitize('mds-pixel-template', $settings['mds-pixel-template'] ?? 'no');
    }

    public static function base(array $settings = []) {
        $settings = $settings ?: self::settings();
        $base = sanitize_title((string) ($settings['mds-pixel-base'] ?? 'mds-pixel'));

        return '' !== $base ? $base : 'mds-pixel';
    }

    public static function pattern(array $settings = []) {
        $settings = $settings ?: self::settings();
        $pattern = trim((string) ($settings['mds-pixel-slug-structure'] ?? '%placement_id%'));

        return '' !== $pattern ? $pattern : '%placement_id%';
    }

    public static function page_link_enabled(array $settings = []) {
        $settings = $settings ?: self::settings();

        return self::enabled($settings) && 'yes' === SettingsSchema::sanitize('advertiser-page-popup-link', $settings['advertiser-page-popup-link'] ?? 'yes');
    }

    public static function build_slug(array $placement, array $grid, array $settings = []) {
        $pattern = self::pattern($settings ?: self::settings());
        $title = sanitize_text_field((string) ($placement['alt_text'] ?? ''));
        if ('' === $title) {
            $title = sanitize_text_field(wp_strip_all_tags((string) ($placement['popup_text'] ?? '')));
        }

        $values = [
            '%placement_id%' => (string) absint($placement['id'] ?? 0),
            '%pixel_id%' => (string) absint($placement['id'] ?? 0),
            '%order_id%' => (string) absint($placement['order_id'] ?? 0),
            '%grid%' => (string) ($grid['slug'] ?? $grid['id'] ?? ''),
            '%title%' => $title,
            '%text%' => $title,
            // MDS2 accepted identity tokens. MDS 3.0 deliberately resolves them
            // to an empty value so new public URLs never expose account names.
            '%username%' => '',
            '%display_name%' => '',
        ];
        $values = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/advertiser/page/slug/tokens', $values, $placement, $grid);
        $slug = sanitize_title(strtr($pattern, is_array($values) ? $values : []));

        return '' !== $slug ? substr($slug, 0, 180) : 'placement-' . absint($placement['id'] ?? 0);
    }

    public static function remember_base($base) {
        $base = sanitize_title((string) $base);
        if ('' === $base || self::base() === $base) {
            return;
        }

        $history = self::base_history();
        array_unshift($history, $base);
        $history = array_slice(array_values(array_unique(array_filter($history))), 0, self::MAX_BASE_HISTORY);
        update_option(self::BASE_HISTORY_OPTION, $history, false);
    }

    public static function import_legacy_base_history() {
        $legacy = get_option('_milliondollarscript_mds-pixel-base-history', []);
        foreach (is_array($legacy) ? $legacy : [] as $base) {
            self::remember_base($base);
        }
    }

    public static function base_history() {
        $history = get_option(self::BASE_HISTORY_OPTION, []);
        $history = is_array($history) ? $history : [];

        return array_slice(array_values(array_unique(array_filter(array_map('sanitize_title', $history)))), 0, self::MAX_BASE_HISTORY);
    }
}
