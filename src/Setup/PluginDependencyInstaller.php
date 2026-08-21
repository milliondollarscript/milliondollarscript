<?php
/**
 * Setup-time installer for supported free WordPress plugin dependencies.
 *
 * @package MillionDollarScript\V3\Setup
 */

namespace MillionDollarScript\V3\Setup;

if (!defined('ABSPATH')) {
    exit;
}

final class PluginDependencyInstaller {

    private const DEPENDENCIES = [
        'woocommerce' => [
            'name' => 'WooCommerce',
            'slug' => 'woocommerce',
            'plugin_file' => 'woocommerce/woocommerce.php',
            'capability' => 'install_plugins',
            'setup_url' => 'admin.php?page=wc-admin',
            'activation_redirect_transient' => '_wc_activation_redirect',
        ],
    ];

    public static function all_statuses() {
        $statuses = [];
        foreach (array_keys(self::DEPENDENCIES) as $id) {
            $statuses[$id] = self::status($id);
        }

        return $statuses;
    }

    public static function status($id) {
        $dependency = self::dependency($id);
        if (!$dependency) {
            return [];
        }

        self::load_plugin_functions();
        $plugin_file = (string) $dependency['plugin_file'];
        $installed = self::is_installed($plugin_file);
        $active = $installed && is_plugin_active($plugin_file);

        return [
            'id' => sanitize_key((string) $id),
            'name' => (string) $dependency['name'],
            'slug' => (string) $dependency['slug'],
            'plugin_file' => $plugin_file,
            'installed' => $installed,
            'active' => $active,
            'can_install' => current_user_can($dependency['capability'] ?? 'install_plugins'),
            'setup_url' => !empty($dependency['setup_url']) ? admin_url((string) $dependency['setup_url']) : '',
        ];
    }

    public function install_and_activate($id) {
        $id = sanitize_key((string) $id);
        $dependency = self::dependency($id);
        if (!$dependency) {
            return new \WP_Error('mds3_dependency_unknown', __('Unknown plugin dependency.', 'million-dollar-script'));
        }

        if (!current_user_can('install_plugins') || !current_user_can('activate_plugins')) {
            return new \WP_Error('mds3_dependency_permission', __('You do not have permission to install and activate plugins.', 'million-dollar-script'));
        }

        $plugin_file = (string) $dependency['plugin_file'];
        self::load_plugin_functions();

        if (!self::is_installed($plugin_file)) {
            $installed = $this->install_from_wp_org((string) $dependency['slug']);
            if (is_wp_error($installed)) {
                return $installed;
            }
            wp_clean_plugins_cache(true);
        }

        if (!self::is_installed($plugin_file)) {
            return new \WP_Error('mds3_dependency_not_found', __('The plugin was installed, but WordPress could not find the expected plugin file.', 'million-dollar-script'));
        }

        $activated_now = false;
        if (!is_plugin_active($plugin_file)) {
            // Run the normal activation hook so dependencies such as WooCommerce
            // finish their install before we clear their optional wizard redirect.
            $activated = activate_plugin($plugin_file);
            if (is_wp_error($activated)) {
                return $activated;
            }
            $activated_now = true;
        }

        if ($activated_now && !empty($dependency['activation_redirect_transient'])) {
            delete_transient((string) $dependency['activation_redirect_transient']);
        }

        return self::status($id);
    }

    private static function dependency($id) {
        $id = sanitize_key((string) $id);

        return self::DEPENDENCIES[$id] ?? null;
    }

    private static function is_installed($plugin_file) {
        $plugins = function_exists('get_plugins') ? get_plugins() : [];
        if (isset($plugins[$plugin_file])) {
            return true;
        }

        return file_exists(WP_PLUGIN_DIR . '/' . ltrim((string) $plugin_file, '/'));
    }

    private function install_from_wp_org($slug) {
        self::load_install_functions();

        $api = plugins_api('plugin_information', [
            'slug' => sanitize_key((string) $slug),
            'fields' => [
                'sections' => false,
            ],
        ]);
        if (is_wp_error($api)) {
            return $api;
        }

        $download_link = is_object($api) ? (string) ($api->download_link ?? '') : '';
        if (!$download_link) {
            return new \WP_Error('mds3_dependency_download_missing', __('WordPress.org did not return a plugin download link.', 'million-dollar-script'));
        }

        $skin = new \Automatic_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);
        $result = $upgrader->install($download_link);

        if (is_wp_error($result)) {
            return $result;
        }
        if (is_wp_error($skin->result ?? null)) {
            return $skin->result;
        }
        if (false === $result) {
            return new \WP_Error('mds3_dependency_install_failed', __('WordPress could not install the plugin automatically.', 'million-dollar-script'));
        }

        return true;
    }

    private static function load_plugin_functions() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    private static function load_install_functions() {
        self::load_plugin_functions();
        if (!function_exists('request_filesystem_credentials')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('plugins_api')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }
        if (!class_exists('\Plugin_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
    }
}
