<?php
/**
 * Stable theme-mode access for extensions.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Core;

use MillionDollarScript\V3\Support\ThemeMode as InternalThemeMode;

if (!defined('ABSPATH')) {
    exit;
}

final class ThemeMode {

    /**
     * The site's configured appearance mode: "light" or "dark".
     *
     * This is the plugin setting, not the operating-system preference. Markup
     * that paints a themed surface should carry the matching `mds3-theme-*`
     * root class so its own stylesheet can style that subtree.
     */
    public static function mode(): string {
        return InternalThemeMode::mode();
    }
}
