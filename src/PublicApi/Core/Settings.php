<?php
/**
 * Stable settings access for extensions.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Core;

use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings {

    public static function all(): array {
        $settings = get_option('mds3_settings', []);
        return wp_parse_args(is_array($settings) ? $settings : [], SettingsSchema::defaults());
    }

    public static function get(string $key, $default = null) {
        $settings = self::all();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function defaults(): array {
        return SettingsSchema::defaults();
    }

    public static function sanitize(string $key, $value) {
        return SettingsSchema::sanitize($key, $value);
    }

    public static function field_classification(string $key): string {
        return (string) SettingsSchema::field_classification($key);
    }

    public static function is_admin_visible(string $key): bool {
        return (bool) SettingsSchema::is_admin_visible($key);
    }
}
