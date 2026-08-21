<?php
/**
 * Popup text formatting helpers.
 *
 * @package MillionDollarScript\V3\Grid
 */

namespace MillionDollarScript\V3\Grid;

use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

final class PopupText {

    public static function rich_enabled(array $settings) {
        return 'yes' === SettingsSchema::sanitize('popup-rich-text', $settings['popup-rich-text'] ?? 'no');
    }

    public static function sanitize($value, array $settings = []) {
        if (is_array($value)) {
            return '';
        }

        $value = function_exists('wp_unslash') ? wp_unslash($value) : stripslashes((string) $value);
        if (self::rich_enabled($settings ?: self::settings())) {
            return trim(self::filter_html((string) $value));
        }

        return sanitize_textarea_field(wp_strip_all_tags((string) $value));
    }

    public static function html($value, array $settings = []) {
        $value = (string) $value;
        if ('' === trim($value)) {
            return '';
        }

        if (self::rich_enabled($settings ?: self::settings())) {
            return self::filter_html($value);
        }

        return nl2br(esc_html(wp_strip_all_tags($value)));
    }

    public static function plain($value) {
        return trim(wp_strip_all_tags((string) $value));
    }

    public static function allowed_html() {
        $allowed = [
            'br' => [],
            'b' => [],
            'strong' => [],
            'i' => [],
            'em' => [],
            'p' => [],
        ];

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/popup/text/allowed/html', $allowed);
    }

    private static function filter_html($html) {
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', (string) $html);
        if (function_exists('wp_kses')) {
            return wp_kses((string) $html, self::allowed_html());
        }

        $html = strip_tags((string) $html, '<br><b><strong><i><em><p>');
        return preg_replace('/<(br|b|strong|i|em|p)\b[^>]*>/i', '<$1>', (string) $html);
    }

    private static function settings() {
        $settings = function_exists('get_option') ? get_option('mds3_settings', []) : [];
        return wp_parse_args(is_array($settings) ? $settings : [], SettingsSchema::defaults());
    }
}
