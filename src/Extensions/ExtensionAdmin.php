<?php
/**
 * Extension hooks and compatibility.
 *
 * @package MillionDollarScript\V3\Extensions
 */

namespace MillionDollarScript\V3\Extensions;

use MillionDollarScript\V3\Support\Component;

if (!defined('ABSPATH')) {
    exit;
}

final class ExtensionAdmin implements Component {

    public function register() {
        add_action('admin_post_mds3_activate_extension', [$this, 'activate_extension']);
        add_action('admin_post_mds3_deactivate_extension', [$this, 'deactivate_extension']);
        add_action('admin_post_mds3_save_extension_license', [$this, 'save_extension_license']);
        add_action('admin_post_mds3_deactivate_extension_license', [$this, 'deactivate_extension_license']);
        add_action('admin_post_mds3_claim_extension_license', [$this, 'claim_extension_license']);
        add_action('admin_post_mds3_activate_tester_access', [$this, 'activate_tester_access']);
        add_action('admin_post_mds3_deactivate_tester_access', [$this, 'deactivate_tester_access']);
        add_action('admin_post_million_dollar_script_refresh_extension_access', [$this, 'refresh_extension_access']);
        add_action('million-dollar-script/extension/entitlements/refresh', [$this, 'scheduled_refresh_extension_access']);
        add_action('admin_init', [$this, 'register_plugin_dependency_guards'], 5);
        add_filter('million-dollar-script/extension/update/license/key', [$this, 'extension_update_license_key'], 10, 3);
        add_filter('million-dollar-script/extension/update/license/candidates', [$this, 'extension_update_license_candidates'], 10, 3);
        if (!wp_next_scheduled('million-dollar-script/extension/entitlements/refresh')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'million-dollar-script/extension/entitlements/refresh');
        }
    }

    public function activate_extension() {
        $plugin_file = plugin_basename(sanitize_text_field(wp_unslash($_POST['plugin_file'] ?? '')));
        check_admin_referer('mds3_activate_extension_' . $plugin_file);
        if (!current_user_can('activate_plugins')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $item = (new ExtensionCatalog())->installed_item_by_file($plugin_file);
        if (!$item) {
            $this->redirect('error', __('Extension is not installed.', 'million-dollar-script'));
        }

        $result = (new ExtensionLifecycle())->activate($plugin_file, $item);
        if (is_wp_error($result)) {
            $this->redirect('error', $result->get_error_message());
        }

        (new ExtensionSetup())->apply_activation_defaults((string) ($item['slug'] ?? ''));
        $this->redirect('success', __('Extension activated.', 'million-dollar-script'));
    }

    public function deactivate_extension() {
        $plugin_file = plugin_basename(sanitize_text_field(wp_unslash($_POST['plugin_file'] ?? '')));
        check_admin_referer('mds3_deactivate_extension_' . $plugin_file);
        if (!current_user_can('activate_plugins')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $item = (new ExtensionCatalog())->installed_item_by_file($plugin_file);
        if (!$item) {
            $this->redirect('error', __('Extension is not installed.', 'million-dollar-script'));
        }

        $deactivation_error = (new ExtensionLifecycle())->deactivation_error($plugin_file);
        if (is_wp_error($deactivation_error)) {
            $this->redirect('error', $deactivation_error->get_error_message());
        }

        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        deactivate_plugins($plugin_file, true);

        $this->redirect('success', __('Extension deactivated.', 'million-dollar-script'));
    }

    public function save_extension_license() {
        $slug = sanitize_key(wp_unslash($_POST['slug'] ?? ''));
        check_admin_referer('mds3_save_extension_license_' . $slug);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $catalog = new ExtensionCatalog();
        $item = $catalog->bundle_item($slug) ?: $catalog->available_item($slug) ?: $catalog->installed_item_by_slug($slug) ?: ['slug' => $slug];
        $license_key = isset($_POST['license_key']) && is_scalar($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
        $version = (string) ($item['version'] ?? '');
        $result = (new ExtensionLicenseManager())->activate($slug, $license_key, $item, $version);
        if (is_wp_error($result)) {
            $this->redirect('error', $result->get_error_message());
        }

        $this->redirect('success', __('Extension license activated.', 'million-dollar-script'));
    }

    public function deactivate_extension_license() {
        $slug = sanitize_key(wp_unslash($_POST['slug'] ?? ''));
        check_admin_referer('mds3_deactivate_extension_license_' . $slug);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $result = (new ExtensionLicenseManager())->deactivate($slug);
        if (is_wp_error($result)) {
            $this->redirect('error', $result->get_error_message());
        }

        $this->redirect('success', __('Extension license deactivated.', 'million-dollar-script'));
    }

    public function claim_extension_license() {
        $slug = sanitize_key(wp_unslash($_POST['slug'] ?? ''));
        check_admin_referer('mds3_claim_extension_license_' . $slug);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $catalog = new ExtensionCatalog();
        $item = $catalog->bundle_item($slug) ?: $catalog->available_item($slug) ?: ['slug' => $slug];
        $claim_token = isset($_POST['claim_token']) && is_scalar($_POST['claim_token']) ? sanitize_text_field(wp_unslash($_POST['claim_token'])) : '';
        $result = (new ExtensionLicenseManager())->claim($slug, $claim_token, $item);
        if (is_wp_error($result)) {
            $this->redirect('error', $result->get_error_message());
        }

        $this->redirect('success', __('Purchased license claimed and activated.', 'million-dollar-script'));
    }

    public function activate_tester_access() {
        check_admin_referer('mds3_activate_tester_access');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $access_key = isset($_POST['access_key']) && is_scalar($_POST['access_key']) ? sanitize_text_field(wp_unslash($_POST['access_key'])) : '';
        $result = (new ExtensionLicenseManager())->activate_tester_access($access_key);
        if (is_wp_error($result)) {
            $this->redirect('error', $result->get_error_message());
        }

        $this->redirect('success', __('Tester access connected. Approved premium extensions and documentation are now available to this site.', 'million-dollar-script'));
    }

    public function deactivate_tester_access() {
        check_admin_referer('mds3_deactivate_tester_access');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        (new ExtensionLicenseManager())->deactivate_tester_access();
        $this->redirect('success', __('Tester access disconnected from this site.', 'million-dollar-script'));
    }

    public function extension_update_license_key($license_key, $slug, $item) {
        return (new ExtensionLicenseManager())->update_license_key($license_key, $slug, is_array($item) ? $item : []);
    }

    public function extension_update_license_candidates($candidates, $slug, $item) {
        return (new ExtensionLicenseManager())->update_license_candidates($candidates, $slug, is_array($item) ? $item : []);
    }

    public function refresh_extension_access() {
        check_admin_referer('million_dollar_script_refresh_extension_access');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $result = (new ExtensionLicenseManager())->refresh_all();
        $this->redirect('success', (string) ($result['message'] ?? __('Extension access refreshed.', 'million-dollar-script')));
    }

    public function scheduled_refresh_extension_access() {
        (new ExtensionLicenseManager())->refresh_all();
    }

    public function register_plugin_dependency_guards() {
        if (!is_admin() || !current_user_can('activate_plugins')) {
            return;
        }

        foreach ((new ExtensionCatalog())->installed() as $item) {
            $plugin_file = plugin_basename((string) ($item['plugin_file'] ?? ''));
            if (!$plugin_file) {
                continue;
            }

            add_action('activate_' . $plugin_file, function () use ($plugin_file) {
                $this->guard_direct_activation($plugin_file);
            }, 0);

            add_action('deactivate_' . $plugin_file, function () use ($plugin_file) {
                $this->guard_direct_deactivation($plugin_file);
            }, 0);
        }
    }

    private function guard_direct_activation($plugin_file) {
        $activation_error = (new ExtensionLifecycle())->activation_error($plugin_file);
        if (is_wp_error($activation_error)) {
            $this->dependency_wp_die($activation_error, __('Plugin Activation Error', 'million-dollar-script'));
        }
    }

    private function guard_direct_deactivation($plugin_file) {
        $deactivation_error = (new ExtensionLifecycle())->deactivation_error($plugin_file);
        if (is_wp_error($deactivation_error)) {
            $this->dependency_wp_die($deactivation_error, __('Plugin Deactivation Error', 'million-dollar-script'));
        }
    }

    private function dependency_wp_die(\WP_Error $error, $title) {
        wp_die(
            esc_html($error->get_error_message()),
            esc_html($title),
            ['back_link' => true]
        );
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
