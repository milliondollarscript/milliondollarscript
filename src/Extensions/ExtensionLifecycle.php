<?php
/**
 * Shared extension activation and dependency checks.
 *
 * @package MillionDollarScript\V3\Extensions
 */

namespace MillionDollarScript\V3\Extensions;

if (!defined('ABSPATH')) {
    exit;
}

final class ExtensionLifecycle {

    public function activate($plugin_file, array $fallback_item = []) {
        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = plugin_basename((string) $plugin_file);
        $activation_error = $this->activation_error($plugin_file, $fallback_item);
        if (is_wp_error($activation_error)) {
            return $activation_error;
        }

        return activate_plugin($plugin_file, '', false, true);
    }

    public function activation_error($plugin_file, array $fallback_item = []) {
        if (function_exists('wp_clean_plugins_cache')) {
            wp_clean_plugins_cache(false);
        }

        $plugin_file = plugin_basename((string) $plugin_file);
        $item = (new ExtensionCatalog())->installed_item_by_file($plugin_file);
        if (!$item && $fallback_item) {
            $item = array_merge($fallback_item, [
                'plugin_file' => $plugin_file,
                'installed' => true,
                'active' => false,
            ]);
        }

        if (!$item) {
            return null;
        }

        return (new ExtensionDependencyResolver())->activation_error($item);
    }

    public function deactivation_error($plugin_file) {
        $item = (new ExtensionCatalog())->installed_item_by_file(plugin_basename((string) $plugin_file));
        if (!$item) {
            return null;
        }

        return (new ExtensionDependencyResolver())->deactivation_error($item);
    }
}
