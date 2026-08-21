<?php
/**
 * Safely injects namespaced browser configuration before registered scripts.
 *
 * @package MillionDollarScript\V3\Support
 */

namespace MillionDollarScript\V3\Support;

if (!defined('ABSPATH')) {
    exit;
}

final class BrowserConfig {

    public static function add(string $handle, string $key, array $config): bool {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $key)) {
            return false;
        }

        $encoded = wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (false === $encoded) {
            return false;
        }

        $script = 'window.MillionDollarScript=window.MillionDollarScript||{};'
            . 'window.MillionDollarScript[' . wp_json_encode($key) . ']=' . $encoded . ';';

        return (bool) wp_add_inline_script($handle, $script, 'before');
    }
}
