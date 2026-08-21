<?php
/**
 * Admin migration and legacy-plugin actions.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Grid\GridPostType;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Pages\PageRepository;
use MillionDollarScript\V3\Setup\LegacyPlugin;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesMigrationAdminActions {

    public function keep_mds2_active() {
        check_admin_referer('mds3_mds2_keep_active');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        LegacyPlugin::set_choice('keep_active');
        wp_safe_redirect(admin_url('admin.php?page=mds3-setup&mds2_action=kept'));
        exit;
    }

    public function deactivate_mds2() {
        check_admin_referer('mds3_mds2_deactivate');
        if (!current_user_can('activate_plugins')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $result = LegacyPlugin::deactivate_active_plugins();
        LegacyPlugin::set_choice(empty($result['skipped']) ? 'deactivated' : 'deactivation_partial');

        wp_safe_redirect(add_query_arg([
            'page' => 'mds3-setup',
            'mds2_action' => empty($result['skipped']) ? 'deactivated' : 'deactivation_partial',
            'deactivated' => count($result['deactivated'] ?? []),
            'skipped' => count($result['skipped'] ?? []),
        ], admin_url('admin.php')));
        exit;
    }

    public function import_and_deactivate_mds2() {
        check_admin_referer('mds3_mds2_import_deactivate');
        if (!current_user_can('manage_options') || !current_user_can('activate_plugins')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $source_prefix = LegacyPlugin::sanitize_source_prefix(wp_unslash($_POST['source_prefix'] ?? ''));
        if (!$this->grid_enabled()) {
            wp_safe_redirect(add_query_arg([
                'page' => 'mds3-setup',
                'mds2_action' => 'import_failed',
                'mds2_error' => rawurlencode(__('Enable Classic Pixel Grid before importing Million Dollar Script 2 data.', 'million-dollar-script')),
            ], admin_url('admin.php')));
            exit;
        }

        $result = (new Importer())->import($source_prefix);
        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'mds3-setup',
                'mds2_action' => 'import_failed',
                'mds2_error' => rawurlencode(wp_strip_all_tags($result->get_error_message())),
            ], admin_url('admin.php')));
            exit;
        }

        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $settings['legacy_mds2_source_prefix'] = $source_prefix;
        update_option('mds3_settings', $settings, false);

        $deactivation = LegacyPlugin::deactivate_active_plugins();
        LegacyPlugin::set_choice(empty($deactivation['skipped']) ? 'migrated_deactivated' : 'migrated_deactivation_partial');

        wp_safe_redirect(add_query_arg([
            'page' => 'mds3-setup',
            'mds2_action' => empty($deactivation['skipped']) ? 'migrated_deactivated' : 'migrated_deactivation_partial',
            'deactivated' => count($deactivation['deactivated'] ?? []),
            'skipped' => count($deactivation['skipped'] ?? []),
        ], admin_url('admin.php')));
        exit;
    }

    public function ensure_standard_pages() {
        check_admin_referer('mds3_ensure_standard_pages');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $redirect_to = sanitize_key(wp_unslash($_POST['redirect_to'] ?? 'migration'));
        $page = 'setup' === $redirect_to ? 'mds3-setup' : 'mds3-migration';
        if (!$this->grid_enabled()) {
            wp_safe_redirect(add_query_arg([
                'page' => $page,
                'pages_error' => rawurlencode(__('Enable Classic Pixel Grid before creating Million Dollar Script grid pages.', 'million-dollar-script')),
            ], admin_url('admin.php')));
            exit;
        }

        $repo = new PageRepository();
        $grid = (new GridRepository())->first_active();
        $grid_id = $grid ? $grid->id() : 0;

        foreach (PageRepository::standard_labels() as $type => $label) {
            $post_id = absint(get_option('mds3_page_' . $type . '_id', 0));
            if ($post_id && get_post($post_id)) {
                continue;
            }

            $page_grid_id = 'grid' === $type ? $grid_id : 0;

            if ('grid' === $type && $grid) {
                $post_id = GridPostType::ensure_page($grid);
                if (is_wp_error($post_id)) {
                    continue;
                }
            } else {
                $post_id = wp_insert_post([
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    'post_title' => (string) $label,
                    'post_name' => sanitize_title((string) $label),
                    'post_content' => PageRepository::shortcode($type, $page_grid_id),
                ], true);

                if (is_wp_error($post_id)) {
                    continue;
                }
            }

            update_post_meta($post_id, '_mds3_page_type', $type);
            if ($page_grid_id) {
                update_post_meta($post_id, '_mds3_grid_id', $page_grid_id);
            } else {
                delete_post_meta($post_id, '_mds3_grid_id');
            }
            update_option('mds3_page_' . $type . '_id', absint($post_id), false);

            $repo->upsert($post_id, $type, [
                'grid_id' => $page_grid_id,
                'source' => 'wizard',
                'configuration' => [
                    'created_by' => 'mds3_standard_pages',
                ],
            ]);
        }

        wp_safe_redirect(admin_url('admin.php?page=' . $page . '&pages=ensured'));
        exit;
    }

    public function run_migration_import() {
        check_admin_referer('mds3_run_migration_import');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $source_prefix = $this->sanitize_source_prefix(wp_unslash($_POST['source_prefix'] ?? ''));
        $run_id = absint($_POST['run_id'] ?? 0);
        if (!$this->grid_enabled()) {
            wp_safe_redirect(add_query_arg([
                'page' => 'mds3-migration',
                'source_prefix' => rawurlencode($source_prefix),
                'migration_error' => rawurlencode(__('Enable Classic Pixel Grid before importing Million Dollar Script 2 data.', 'million-dollar-script')),
            ], admin_url('admin.php')));
            exit;
        }

        $importer = new Importer();
        $result = $run_id ? $importer->run_resumable_step($run_id, ['resume' => true]) : $importer->start_resumable($source_prefix);
        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'mds3-migration',
                'source_prefix' => rawurlencode($source_prefix),
                'migration_error' => rawurlencode(wp_strip_all_tags($result->get_error_message())),
            ], admin_url('admin.php')));
            exit;
        }

        if (!empty($result['completed'])) {
            $this->record_completed_mds2_migration((string) ($result['source_prefix'] ?? $source_prefix));
        }

        $context = sanitize_key(wp_unslash($_POST['migration_context'] ?? ''));
        $result_flag = !empty($result['completed']) ? 'imported' : ('continue' === $context ? 'continued' : ('retry' === $context ? 'retried' : 'started'));
        wp_safe_redirect(add_query_arg([
            'page' => 'mds3-migration',
            $result_flag => 1,
            'migration_job' => absint($result['run_id'] ?? $run_id),
            'source_prefix' => (string) ($result['source_prefix'] ?? $source_prefix),
        ], admin_url('admin.php')));
        exit;
    }

    public function pause_migration_import() {
        check_admin_referer('mds3_pause_migration_import');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $run_id = absint($_POST['run_id'] ?? 0);
        $source_prefix = $this->sanitize_source_prefix(wp_unslash($_POST['source_prefix'] ?? ''));
        if ($run_id) {
            (new Importer())->pause_resumable($run_id);
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'mds3-migration',
            'migration_job' => $run_id,
            'paused' => 1,
            'source_prefix' => $source_prefix,
        ], admin_url('admin.php')));
        exit;
    }

    public function ajax_migration_step() {
        check_ajax_referer('mds3_migration_step', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'million-dollar-script')], 403);
        }

        $run_id = absint($_POST['run_id'] ?? 0);
        if (!$run_id) {
            wp_send_json_error(['message' => __('Migration job was not found.', 'million-dollar-script')], 404);
        }

        $result = (new Importer())->run_resumable_step($run_id);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => wp_strip_all_tags($result->get_error_message())], 500);
        }

        if (!empty($result['completed'])) {
            $this->record_completed_mds2_migration((string) ($result['source_prefix'] ?? ''));
        }

        wp_send_json_success($result);
    }

    private function record_completed_mds2_migration($source_prefix) {
        $source_prefix = $this->sanitize_source_prefix($source_prefix);
        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $settings['legacy_mds2_source_prefix'] = $source_prefix;
        update_option('mds3_settings', $settings, false);
        LegacyPlugin::set_choice('migrated');
    }
}
