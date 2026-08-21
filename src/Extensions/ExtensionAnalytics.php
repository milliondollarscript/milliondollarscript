<?php
/**
 * Privacy-bounded first-party extension analytics.
 *
 * @package MillionDollarScript\V3\Extensions
 */

namespace MillionDollarScript\V3\Extensions;

if (!defined('ABSPATH')) {
    exit;
}

final class ExtensionAnalytics {

    private const MAX_EXTENSIONS = 64;

    public static function snapshot() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return self::snapshot_from_plugins(get_plugins(), (array) get_option('active_plugins', []));
    }

    public static function snapshot_from_plugins(array $plugins, array $active_files) {
        $active = array_fill_keys(array_map('plugin_basename', $active_files), true);
        $snapshot = [];

        foreach ($plugins as $file => $plugin) {
            $file = plugin_basename((string) $file);
            $slug = sanitize_key('.' === dirname($file) ? basename($file, '.php') : dirname($file));
            if ($file === MILLION_DOLLAR_SCRIPT_BASENAME || 0 !== strpos($slug, 'mds-') || !self::is_official($plugin)) {
                continue;
            }

            $snapshot[] = [
                'slug' => $slug,
                'version' => sanitize_text_field((string) ($plugin['Version'] ?? '')),
                'active' => isset($active[$file]) || (is_multisite() && function_exists('is_plugin_active_for_network') && is_plugin_active_for_network($file)),
            ];
            if (count($snapshot) >= self::MAX_EXTENSIONS) {
                break;
            }
        }

        usort($snapshot, static function ($left, $right) {
            return strcmp((string) ($left['slug'] ?? ''), (string) ($right['slug'] ?? ''));
        });

        return $snapshot;
    }

    private static function is_official(array $plugin) {
        foreach (['PluginURI', 'Plugin URI', 'AuthorURI', 'Author URI'] as $key) {
            $host = strtolower((string) wp_parse_url((string) ($plugin[$key] ?? ''), PHP_URL_HOST));
            if ('milliondollarscript.com' === $host || 'www.milliondollarscript.com' === $host) {
                return true;
            }
        }

        return false;
    }
}
