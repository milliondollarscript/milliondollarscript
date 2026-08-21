<?php
/**
 * Direct-distribution extension install and update delivery.
 *
 * This component is omitted from the WordPress.org package.
 *
 * @package MillionDollarScript\V3\Extensions
 */

namespace MillionDollarScript\V3\Extensions;

use MillionDollarScript\V3\Support\Component;

if (!defined('ABSPATH')) {
    exit;
}

final class ExtensionPackageDelivery implements Component {

    public function register() {
        add_action('admin_post_mds3_install_extension', [$this, 'install_extension']);
        add_action('admin_post_mds3_update_extension', [$this, 'update_extension']);
        add_action('admin_post_mds3_check_extension_updates', [$this, 'check_extension_updates']);
        add_action('admin_post_mds3_check_extension_update', [$this, 'check_extension_update']);
    }

    public function install_extension() {
        $slug = sanitize_key(wp_unslash($_POST['slug'] ?? ''));
        check_admin_referer('mds3_install_extension_' . $slug);
        if (!current_user_can('install_plugins')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $catalog = new ExtensionCatalog();
        $item = $catalog->available_item($slug);
        if (!$item) {
            $this->redirect('error', __('Extension package is not available for install.', 'million-dollar-script'));
        }

        if ($catalog->installed_item_by_slug($slug)) {
            $this->redirect('info', __('Extension is already installed.', 'million-dollar-script'));
        }

        $existing_plugin_file = $this->existing_plugin_file_for_slug($slug);
        if ($existing_plugin_file) {
            $this->redirect('warning', sprintf(
                /* translators: %s: plugin file path */
                __('Extension files already exist at %s, but this installed copy is not registered as a compatible Million Dollar Script extension. Activate or update the existing plugin if it is compatible, or delete it before installing from the catalog.', 'million-dollar-script'),
                $existing_plugin_file
            ));
        }

        $activation_error = (new ExtensionDependencyResolver())->activation_error($item);
        if (is_wp_error($activation_error)) {
            $this->redirect('error', $activation_error->get_error_message());
        }

        $download_url = (string) ($item['download_url'] ?? '');
        if (!empty($item['license_required'])) {
            $download_url = (new ExtensionLicenseManager())->protected_package_url($item);
            if (!$download_url) {
                $this->redirect('error', __('Activate a license before installing this premium extension.', 'million-dollar-script'));
            }
        } elseif (!$download_url) {
            $this->redirect('error', __('Extension package is not available for install.', 'million-dollar-script'));
        }

        $upgrader = $this->upgrader();
        $result = $this->run_with_download_host_allowlist($download_url, function () use ($upgrader, $download_url) {
            return $upgrader->install($download_url);
        });
        if (is_wp_error($result)) {
            $this->redirect('error', $result->get_error_message());
        }
        if (!$result) {
            $this->redirect('error', $this->upgrader_error_message($upgrader, __('Extension install failed.', 'million-dollar-script')));
        }

        $plugin_file = $upgrader->plugin_info();
        if (!$plugin_file && ($installed = $catalog->installed_item_by_slug($slug))) {
            $plugin_file = (string) ($installed['plugin_file'] ?? '');
        }

        if (!empty($_POST['activate']) && $plugin_file) {
            $activated = (new ExtensionLifecycle())->activate($plugin_file, $item);
            if (is_wp_error($activated)) {
                $this->redirect('warning', sprintf(
                    /* translators: %s: activation error */
                    __('Extension installed, but activation failed: %s', 'million-dollar-script'),
                    $activated->get_error_message()
                ));
            }

            (new ExtensionSetup())->apply_activation_defaults($slug);
            $this->redirect('success', __('Extension installed and activated.', 'million-dollar-script'));
        }

        $this->redirect('success', __('Extension installed.', 'million-dollar-script'));
    }

    public function update_extension() {
        $plugin_file = plugin_basename(sanitize_text_field(wp_unslash($_POST['plugin_file'] ?? '')));
        check_admin_referer('mds3_update_extension_' . $plugin_file);
        if (!current_user_can('update_plugins')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $item = $this->catalog_item_by_file($plugin_file, true);
        if (!$item) {
            $this->redirect('error', __('Extension is not installed.', 'million-dollar-script'));
        }

        $was_active = is_plugin_active($plugin_file);

        if (!empty($item['download_url']) && !empty($item['update_version'])) {
            $this->inject_update_package($plugin_file, $item);
        } elseif (!empty($item['update_available'])) {
            $this->redirect('error', __('Activate a license before updating this premium extension.', 'million-dollar-script'));
        }

        $upgrader = $this->upgrader();
        $result = $this->run_with_download_host_allowlist((string) ($item['download_url'] ?? ''), function () use ($upgrader, $plugin_file) {
            return $upgrader->upgrade($plugin_file);
        });
        if (is_wp_error($result)) {
            $this->redirect('error', $result->get_error_message());
        }
        if (!$result) {
            $this->redirect('info', __('No extension update was available.', 'million-dollar-script'));
        }

        $activation_error = $this->restore_activation_after_update($plugin_file, $item, $was_active);
        if (is_wp_error($activation_error)) {
            $this->redirect('warning', sprintf(
                /* translators: %s: activation error */
                __('Extension updated, but reactivation failed: %s', 'million-dollar-script'),
                $activation_error->get_error_message()
            ));
        }

        $this->redirect('success', __('Extension updated.', 'million-dollar-script'));
    }

    /**
     * Restore the active state WordPress clears during an individual plugin upgrade.
     *
     * @param string $plugin_file Plugin basename.
     * @param array  $item        Catalog item used for dependency validation.
     * @param bool   $was_active  Whether the extension was active before the upgrade.
     * @return null|\WP_Error
     */
    private function restore_activation_after_update($plugin_file, array $item, $was_active) {
        if (!$was_active || is_plugin_active($plugin_file)) {
            return null;
        }

        $result = (new ExtensionLifecycle())->activate($plugin_file, $item);

        return is_wp_error($result) ? $result : null;
    }

    public function check_extension_updates() {
        check_admin_referer('mds3_check_extension_updates');
        if (!current_user_can('update_plugins')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        if (!function_exists('wp_update_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }
        if (function_exists('wp_clean_plugins_cache')) {
            wp_clean_plugins_cache(false);
        }
        delete_site_transient('update_plugins');
        wp_update_plugins();
        (new ExtensionCatalog())->catalog(true);

        $this->redirect('success', __('Extension updates checked.', 'million-dollar-script'));
    }

    public function check_extension_update() {
        $plugin_file = plugin_basename(sanitize_text_field(wp_unslash($_POST['plugin_file'] ?? '')));
        check_admin_referer('mds3_check_extension_update_' . $plugin_file);
        if (!current_user_can('update_plugins')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $item = $this->catalog_item_by_file($plugin_file, true);
        if (!$item) {
            $this->redirect('error', __('Extension is not installed.', 'million-dollar-script'));
        }

        if (!empty($item['license_required']) && !(new ExtensionLicenseManager())->has_access($item['slug'] ?? '')) {
            $this->redirect('error', __('Activate a license before checking updates for this premium extension.', 'million-dollar-script'));
        }

        if (!empty($item['update_available'])) {
            if (!empty($item['download_url']) && !empty($item['update_version'])) {
                $this->inject_update_package($plugin_file, $item);
            }

            $this->redirect('warning', sprintf(
                /* translators: %s: update version */
                __('Extension update available: %s.', 'million-dollar-script'),
                (string) ($item['update_version'] ?? '')
            ));
        }

        $this->redirect('success', __('This extension is up to date.', 'million-dollar-script'));
    }

    private function upgrader() {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        return new \Plugin_Upgrader(new \Automatic_Upgrader_Skin());
    }

    private function run_with_download_host_allowlist($download_url, callable $callback) {
        $hosts = $this->local_download_hosts($download_url);
        $ports = $this->local_download_ports($download_url);
        if (!$hosts && !$ports) {
            return $callback();
        }

        $host_filter = static function ($allow, $host) use ($hosts) {
            if ($allow || !ExtensionServer::is_local()) {
                return $allow;
            }

            return in_array(strtolower((string) $host), $hosts, true) ? true : $allow;
        };
        $port_filter = static function ($allowed_ports) use ($ports) {
            if (!ExtensionServer::is_local() || !is_array($allowed_ports)) {
                return $allowed_ports;
            }

            return array_values(array_unique(array_merge($allowed_ports, $ports)));
        };

        add_filter('http_request_host_is_external', $host_filter, 10, 2);
        add_filter('http_allowed_safe_ports', $port_filter, 10, 1);
        try {
            return $callback();
        } finally {
            remove_filter('http_request_host_is_external', $host_filter, 10);
            remove_filter('http_allowed_safe_ports', $port_filter, 10);
        }
    }

    private function local_download_hosts($download_url) {
        if (!ExtensionServer::is_local()) {
            return [];
        }

        $urls = [
            $download_url,
            ExtensionServer::base_url(),
            ExtensionServer::public_url(),
            ExtensionServer::LOCAL_URL,
            ExtensionServer::LOCAL_PUBLIC_URL,
            'http://extension-server-go:3030',
            'http://extension-server:3030',
        ];

        $hosts = [];
        foreach ($urls as $url) {
            $parts = wp_parse_url((string) $url);
            if (!empty($parts['host'])) {
                $hosts[] = strtolower((string) $parts['host']);
            }
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    private function local_download_ports($download_url) {
        if (!ExtensionServer::is_local()) {
            return [];
        }

        $urls = [
            $download_url,
            ExtensionServer::base_url(),
            ExtensionServer::public_url(),
            ExtensionServer::LOCAL_URL,
            ExtensionServer::LOCAL_PUBLIC_URL,
            'http://extension-server-go:3030',
            'http://extension-server:3030',
        ];

        $ports = [];
        foreach ($urls as $url) {
            $parts = wp_parse_url((string) $url);
            if (!empty($parts['port'])) {
                $ports[] = (int) $parts['port'];
            }
        }

        return array_values(array_unique(array_filter($ports)));
    }

    private function upgrader_error_message(\Plugin_Upgrader $upgrader, $fallback) {
        $errors = is_object($upgrader->skin ?? null) && method_exists($upgrader->skin, 'get_errors')
            ? $upgrader->skin->get_errors()
            : null;

        if (is_wp_error($errors) && $errors->has_errors()) {
            return $errors->get_error_message();
        }

        return $fallback;
    }

    private function inject_update_package($plugin_file, array $item) {
        $updates = get_site_transient('update_plugins');
        if (!is_object($updates)) {
            $updates = new \stdClass();
        }
        if (!is_array($updates->response ?? null)) {
            $updates->response = [];
        }

        $updates->response[$plugin_file] = (object) [
            'id' => (string) ($item['id'] ?? $item['slug'] ?? ''),
            'slug' => (string) ($item['slug'] ?? dirname($plugin_file)),
            'plugin' => $plugin_file,
            'new_version' => (string) ($item['update_version'] ?? $item['catalog_version'] ?? ''),
            'package' => (string) ($item['download_url'] ?? ''),
            'url' => (string) ($item['purchase_url'] ?? $item['license_url'] ?? ''),
        ];

        set_site_transient('update_plugins', $updates);
    }

    private function catalog_item_by_file($plugin_file, $force_update_check = false) {
        $plugin_file = plugin_basename((string) $plugin_file);
        $catalog = (new ExtensionCatalog())->catalog((bool) $force_update_check);
        foreach (($catalog['installed'] ?? []) as $item) {
            if ($plugin_file === (string) ($item['plugin_file'] ?? '')) {
                return $item;
            }
        }

        return null;
    }

    private function existing_plugin_file_for_slug($slug) {
        $slug = sanitize_key((string) $slug);
        if (!$slug) {
            return '';
        }

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach (get_plugins() as $file => $plugin) {
            unset($plugin);

            if ($slug === sanitize_key('.' === dirname($file) ? basename($file, '.php') : dirname($file))) {
                return plugin_basename($file);
            }
        }

        return '';
    }

    private function redirect($status, $message) {
        $page = 'setup' === sanitize_key(wp_unslash($_POST['redirect_to'] ?? '')) ? 'mds3-setup' : 'mds3-extensions';
        wp_safe_redirect(add_query_arg([
            'mds3_extension_status' => sanitize_key($status),
            'mds3_extension_message' => wp_strip_all_tags((string) $message),
        ], admin_url('admin.php?page=' . $page)));
        exit;
    }
}
