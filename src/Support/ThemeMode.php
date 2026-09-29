<?php
/**
 * Shared front-end and admin theme mode resolution.
 *
 * @package MillionDollarScript\V3\Support
 */

namespace MillionDollarScript\V3\Support;

use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

final class ThemeMode {

    /**
     * Effective MDS theme for the current site settings: light, dark, or system.
     */
    public static function mode() {
        $settings = get_option('mds3_settings', []);
        $mode = is_array($settings) ? ($settings['theme_mode'] ?? 'light') : 'light';
        $mode = SettingsSchema::sanitize('theme_mode', $mode);

        return in_array($mode, ['light', 'dark', 'system'], true) ? $mode : 'light';
    }
}
