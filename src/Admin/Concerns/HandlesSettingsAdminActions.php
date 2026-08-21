<?php
/**
 * Admin settings and setup actions.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Extensions\ExtensionOnboarding;
use MillionDollarScript\V3\Extensions\ExtensionCleanupPolicy;
use MillionDollarScript\V3\Extensions\ExtensionSetup;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Settings\SettingsTransfer;
use MillionDollarScript\V3\Setup\PluginDependencyInstaller;
use MillionDollarScript\V3\Setup\StarterSite;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesSettingsAdminActions {

    public function save_settings() {
        check_admin_referer('mds3_save_settings');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $current = get_option('mds3_settings', []);
        $current = is_array($current) ? $current : [];
        $raw_post = wp_unslash($_POST);
        $errors = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/validate/settings', new \WP_Error(), $raw_post, $current);
        if (is_wp_error($errors) && $errors->has_errors()) {
            wp_safe_redirect(add_query_arg('settings_error', wp_strip_all_tags($errors->get_error_message()), admin_url('admin.php?page=mds3-settings')));
            exit;
        }

        $next = [];
        foreach ($this->settings_fields_for_save() as $key => $field) {
            $next[$key] = SettingsSchema::sanitize($key, $raw_post[$key] ?? ($field['default'] ?? ''));
        }
        $next = Currency::settings_with_effective_values($next);
        $next = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/sanitize/settings', $next, $raw_post, $current);

        $saved = array_merge($current, $next, [
            'legacy_mds2_source_prefix' => sanitize_text_field($raw_post['legacy_mds2_source_prefix'] ?? ''),
        ]);

        update_option('mds3_settings', $saved, false);
        ExtensionCleanupPolicy::save_inclusions((array) ($raw_post['mds3_extension_cleanup_included'] ?? []));
        \MillionDollarScript\Core\Hooks::do('million-dollar-script/admin/settings/saved', $saved, $raw_post, $current);

        wp_safe_redirect(admin_url('admin.php?page=mds3-settings&updated=1'));
        exit;
    }

    public function export_settings() {
        check_admin_referer('mds3_export_settings');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $current = get_option('mds3_settings', []);
        $current = is_array($current) ? $current : [];
        $fields = $this->settings_transfer_fields();
        $payload = (new SettingsTransfer())->export_payload(wp_parse_args($current, SettingsSchema::defaults()), $fields);
        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            wp_die(esc_html__('Settings export could not be generated.', 'million-dollar-script'));
        }

        nocache_headers();
        header('Content-Type: application/json; charset=' . get_option('blog_charset', 'UTF-8'));
        header('Content-Disposition: attachment; filename="million-dollar-script-settings-' . gmdate('Ymd-His') . '.json"');
        header('X-Content-Type-Options: nosniff');
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public function preview_settings_import() {
        check_admin_referer('mds3_preview_settings_import');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $transfer = new SettingsTransfer();
        $payload = $transfer->decode_uploaded_file(is_array($_FILES['settings_file'] ?? null) ? $_FILES['settings_file'] : []);
        if (is_wp_error($payload)) {
            $this->redirect_settings_import_error($payload);
        }

        $current = get_option('mds3_settings', []);
        $current = is_array($current) ? $current : [];
        $fields = $this->settings_transfer_fields();
        $current_with_defaults = wp_parse_args($current, SettingsSchema::defaults());
        $preview = $transfer->preview($payload, $current_with_defaults, $fields);
        if (is_wp_error($preview)) {
            $this->redirect_settings_import_error($preview);
        }
        $effective_next = Currency::settings_with_effective_values(array_merge($current_with_defaults, $preview['settings']));
        $preview = $transfer->preview_with_effective_settings($preview, $effective_next, $current_with_defaults, $fields);

        $transfer->save_preview($preview);

        wp_safe_redirect(add_query_arg([
            'page' => 'mds3-settings',
            'tab' => 'settings-transfer',
            'settings_import_preview' => '1',
        ], admin_url('admin.php')));
        exit;
    }

    public function apply_settings_import() {
        check_admin_referer('mds3_apply_settings_import');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $transfer = new SettingsTransfer();
        $preview = $transfer->preview_for_user();
        if (!$preview || empty($preview['settings']) || !is_array($preview['settings'])) {
            $this->redirect_settings_import_error(new \WP_Error('mds3_settings_import_preview_missing', __('Preview the settings import again before applying it.', 'million-dollar-script')));
        }

        $current = get_option('mds3_settings', []);
        $current = is_array($current) ? $current : [];
        $transfer->backup_current($current);

        $next = array_merge($current, $preview['settings']);
        $next = Currency::settings_with_effective_values($next);
        update_option('mds3_settings', $next, false);
        $transfer->clear_preview();
        \MillionDollarScript\Core\Hooks::do('million-dollar-script/admin/settings/imported', $next, $preview, $current);

        wp_safe_redirect(add_query_arg([
            'page' => 'mds3-settings',
            'tab' => 'settings-transfer',
            'settings_imported' => '1',
        ], admin_url('admin.php')));
        exit;
    }

    public function clear_settings_import_preview() {
        check_admin_referer('mds3_clear_settings_import_preview');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        (new SettingsTransfer())->clear_preview();

        wp_safe_redirect(admin_url('admin.php?page=mds3-settings&tab=settings-transfer'));
        exit;
    }

    public function save_setup() {
        check_admin_referer('mds3_save_setup');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $post = wp_unslash($_POST);
        $requested_payment_provider = SettingsSchema::sanitize('payment_provider', $post['payment_provider'] ?? 'standalone');
        $extension_result = (new ExtensionSetup())->save_selection(is_array($post['mds3_setup_extensions'] ?? null) ? $post['mds3_setup_extensions'] : []);

        if ('standalone' !== $requested_payment_provider && !Payments::provider_ready($requested_payment_provider, array_merge($settings, ['payment_provider' => $requested_payment_provider]))) {
            wp_safe_redirect(add_query_arg([
                'page' => 'mds3-setup',
                'payment_error' => rawurlencode(__('That payment provider is not ready yet. Install and activate its required plugins, or choose standalone/manual checkout.', 'million-dollar-script')),
                'extensions_activated' => count($extension_result['activated'] ?? []),
                'extension_errors' => count($extension_result['errors'] ?? []) + count($extension_result['skipped'] ?? []),
            ], admin_url('admin.php')));
            exit;
        }

        $settings['payment_provider'] = $requested_payment_provider;
        $settings = Currency::settings_with_effective_values($settings);
        update_option('mds3_settings', $settings, false);
        update_option('mds3_setup_complete', 'yes', false);

        $starter_site_requested = !empty($post['mds3_create_starter_site']);
        $starter_site_result = [];
        if ($starter_site_requested) {
            $starter_site_result = (new StarterSite())->create();
            set_transient('mds3_starter_site_result_' . get_current_user_id(), $starter_site_result, 60);
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'mds3-setup',
            'saved' => '1',
            'extensions_activated' => count($extension_result['activated'] ?? []),
            'extension_errors' => count($extension_result['errors'] ?? []) + count($extension_result['skipped'] ?? []),
            'starter_site' => $starter_site_requested ? '1' : '0',
            'starter_site_errors' => count($starter_site_result['errors'] ?? []),
        ], admin_url('admin.php')));
        exit;
    }

    public function install_plugin_dependency() {
        check_admin_referer('mds3_install_plugin_dependency');
        if (!current_user_can('install_plugins') || !current_user_can('activate_plugins')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $dependency = sanitize_key(wp_unslash($_POST['dependency'] ?? ''));
        $result = $this->install_plugin_dependency_result($dependency);
        $args = [
            'page' => 'mds3-setup',
        ];
        if (is_wp_error($result)) {
            $args['dependency_error'] = rawurlencode(wp_strip_all_tags($result->get_error_message()));
        } else {
            $args['dependency_installed'] = $dependency;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public function ajax_install_plugin_dependency() {
        check_ajax_referer('mds3_install_plugin_dependency', 'nonce');
        if (!current_user_can('install_plugins') || !current_user_can('activate_plugins')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'million-dollar-script'),
            ], 403);
        }

        $dependency = sanitize_key(wp_unslash($_POST['dependency'] ?? ''));
        $result = $this->install_plugin_dependency_result($dependency);
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ], 400);
        }

        wp_send_json_success([
            'message' => __('Plugin installed and activated. You can continue setup.', 'million-dollar-script'),
            'status' => $result,
        ]);
    }

    private function install_plugin_dependency_result($dependency) {
        return (new PluginDependencyInstaller())->install_and_activate($dependency);
    }

    private function settings_transfer_fields() {
        $fields = array_merge(SettingsSchema::fields(), $this->settings_fields_for_save());
        $fields['legacy_mds2_source_prefix'] = [
            'key' => 'legacy_mds2_source_prefix',
            'label' => __('Last Million Dollar Script 2 Source Prefix', 'million-dollar-script'),
            'type' => 'text',
            'default' => '',
        ];

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/settings/transfer/fields', $fields, $this->grid_enabled());
    }

    private function redirect_settings_import_error(\WP_Error $error) {
        wp_safe_redirect(add_query_arg([
            'page' => 'mds3-settings',
            'tab' => 'settings-transfer',
            'settings_import_error' => rawurlencode(wp_strip_all_tags($error->get_error_message())),
        ], admin_url('admin.php')));
        exit;
    }

    public function create_extension_legal_pages() {
        check_admin_referer('mds3_create_extension_legal_pages', '_mds3_extension_legal_pages_nonce');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $post = wp_unslash($_POST);
        $document_slugs = is_array($post['mds3_extension_legal_documents'] ?? null) ? $post['mds3_extension_legal_documents'] : [];
        $extension_setup = new ExtensionSetup();
        $selected_slugs = $extension_setup->selected_slugs();
        $result = (new ExtensionOnboarding())->create_legal_pages($document_slugs, $selected_slugs);
        set_transient('mds3_extension_legal_pages_result_' . get_current_user_id(), $result, 60);

        wp_safe_redirect(add_query_arg([
            'page' => 'mds3-setup',
            'extension_legal_pages' => '1',
            'legal_pages_created' => count($result['created'] ?? []),
            'legal_pages_updated' => count($result['updated'] ?? []),
            'legal_pages_skipped' => count($result['skipped'] ?? []),
            'legal_pages_errors' => count($result['errors'] ?? []),
        ], admin_url('admin.php')));
        exit;
    }

    public function create_extension_setup_pages() {
        check_admin_referer('mds3_create_extension_setup_pages', '_mds3_extension_setup_pages_nonce');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $post = wp_unslash($_POST);
        $page_slugs = is_array($post['mds3_extension_setup_pages'] ?? null) ? $post['mds3_extension_setup_pages'] : [];
        $extension_setup = new ExtensionSetup();
        $selected_slugs = $extension_setup->selected_slugs();
        $result = (new ExtensionOnboarding())->create_setup_pages($page_slugs, $selected_slugs);
        set_transient('mds3_extension_setup_pages_result_' . get_current_user_id(), $result, 60);

        wp_safe_redirect(add_query_arg([
            'page' => 'mds3-setup',
            'extension_setup_pages' => '1',
            'setup_pages_created' => count($result['created'] ?? []),
            'setup_pages_updated' => count($result['updated'] ?? []),
            'setup_pages_skipped' => count($result['skipped'] ?? []),
            'setup_pages_errors' => count($result['errors'] ?? []),
        ], admin_url('admin.php')));
        exit;
    }

    public function create_extension_onboarding_pages() {
        check_admin_referer('mds3_create_extension_setup_pages', '_mds3_extension_setup_pages_nonce');
        check_admin_referer('mds3_create_extension_legal_pages', '_mds3_extension_legal_pages_nonce');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $post = wp_unslash($_POST);
        $page_slugs = is_array($post['mds3_extension_setup_pages'] ?? null) ? $post['mds3_extension_setup_pages'] : [];
        $document_slugs = is_array($post['mds3_extension_legal_documents'] ?? null) ? $post['mds3_extension_legal_documents'] : [];
        $extension_setup = new ExtensionSetup();
        $selected_slugs = $extension_setup->selected_slugs();
        $onboarding = new ExtensionOnboarding();
        $empty_result = [
            'created' => [],
            'updated' => [],
            'skipped' => [],
            'errors' => [],
        ];
        $page_result = $page_slugs ? $onboarding->create_setup_pages($page_slugs, $selected_slugs) : $empty_result;
        $legal_result = $document_slugs ? $onboarding->create_legal_pages($document_slugs, $selected_slugs) : $empty_result;

        if (!$page_slugs && !$document_slugs) {
            $page_result['errors'][] = __('Select at least one recommended page or legal draft to create or update.', 'million-dollar-script');
        }

        set_transient('mds3_extension_setup_pages_result_' . get_current_user_id(), $page_result, 60);
        set_transient('mds3_extension_legal_pages_result_' . get_current_user_id(), $legal_result, 60);

        $redirect_args = [
            'page' => 'mds3-setup',
        ];

        if ($page_slugs || !empty($page_result['errors'])) {
            $redirect_args = array_merge($redirect_args, [
                'extension_setup_pages' => '1',
                'setup_pages_created' => count($page_result['created'] ?? []),
                'setup_pages_updated' => count($page_result['updated'] ?? []),
                'setup_pages_skipped' => count($page_result['skipped'] ?? []),
                'setup_pages_errors' => count($page_result['errors'] ?? []),
            ]);
        }

        if ($document_slugs || !empty($legal_result['errors'])) {
            $redirect_args = array_merge($redirect_args, [
                'extension_legal_pages' => '1',
                'legal_pages_created' => count($legal_result['created'] ?? []),
                'legal_pages_updated' => count($legal_result['updated'] ?? []),
                'legal_pages_skipped' => count($legal_result['skipped'] ?? []),
                'legal_pages_errors' => count($legal_result['errors'] ?? []),
            ]);
        }

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
}
