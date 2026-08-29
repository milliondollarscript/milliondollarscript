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
use MillionDollarScript\V3\Migration\LegacySource;
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
        wp_safe_redirect($this->mds2_choice_redirect('kept'));
        exit;
    }

    public function deactivate_mds2() {
        check_admin_referer('mds3_mds2_deactivate');
        if (!current_user_can('activate_plugins')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $result = LegacyPlugin::deactivate_active_plugins();
        $partial = !empty($result['skipped']);
        LegacyPlugin::set_choice($partial ? 'deactivation_partial' : 'deactivated');

        wp_safe_redirect($this->mds2_choice_redirect($partial ? 'deactivation_partial' : 'deactivated', [
            'deactivated' => count($result['deactivated'] ?? []),
            'skipped' => count($result['skipped'] ?? []),
        ]));
        exit;
    }

    private function mds2_choice_redirect($action, array $extra = []) {
        $page = 'migration' === sanitize_key(wp_unslash($_POST['mds2_redirect'] ?? '')) ? 'mds3-migration' : 'mds3-setup';

        return add_query_arg(array_merge(['page' => $page, 'mds2_action' => $action], $extra), admin_url('admin.php'));
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
        $replace_modified = !empty($_POST['mds2_replace_modified_pages']);
        $create_new = !empty($_POST['mds2_create_new_pages']) && !$replace_modified;

        foreach (PageRepository::standard_labels() as $type => $label) {
            $post_id = absint(get_option('mds3_page_' . $type . '_id', 0));
            if ($post_id && get_post($post_id)) {
                continue;
            }

            if ('grid' === $type && !$grid) {
                // The grid page is created with the grid; never an empty page.
                continue;
            }

            $page_grid_id = 'grid' === $type ? $grid_id : 0;

            if ('grid' === $type && $grid) {
                $post_id = $this->wizard_grid_page_id($grid, $replace_modified, $create_new);
                if (is_wp_error($post_id) || !$post_id) {
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

    /**
     * Grid page for the standard-pages wizard. Adopts an existing MDS2 grid page
     * (unmodified, or when replace is opted in) instead of stacking a duplicate.
     */
    private function wizard_grid_page_id($grid, $replace_modified, $create_new) {
        $candidate = $this->wizard_first_grid_candidate();
        if ($candidate) {
            $unmodified = !empty($candidate['unmodified']);
            if ($unmodified || $replace_modified) {
                $post_id = absint($candidate['post_id']);
                $content = PageRepository::shortcode('grid', $grid->id());
                $post = get_post($post_id);
                if ($post && (string) $post->post_content !== $content) {
                    if (!metadata_exists('post', $post_id, '_mds3_migration_original_content')) {
                        update_post_meta($post_id, '_mds3_migration_original_content', (string) $post->post_content);
                    }
                    wp_update_post(['ID' => $post_id, 'post_content' => $content]);
                }

                return $post_id;
            }
        }

        return GridPostType::ensure_page($grid);
    }

    private function wizard_first_grid_candidate() {
        $source = new LegacySource();
        foreach ($source->page_candidates() as $candidate) {
            if ('grid' === ($candidate['type'] ?? '')) {
                return $candidate;
            }
        }

        return null;
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
        $page_options = [
            'replace_modified' => !empty($_POST['mds2_replace_modified_pages']),
            'create_new' => !empty($_POST['mds2_create_new_pages']),
        ];
        $result = $run_id ? $importer->run_resumable_step($run_id, ['resume' => true]) : $importer->start_resumable($source_prefix, $page_options);
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
