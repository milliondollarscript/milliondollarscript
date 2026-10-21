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
use MillionDollarScript\V3\Setup\PageOptionAudit;

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

        $grid = (new GridRepository())->first_active();
        $upgrade_in_place = array_filter(array_map('absint', (array) ($_POST['mds2_upgrade_pages'] ?? [])));
        $create_pages = array_values(array_intersect(
            array_map('sanitize_key', (array) ($_POST['create_pages'] ?? [])),
            array_keys(PageRepository::optional_labels())
        ));
        $before_page_ids = self::standard_page_option_ids();

        self::ensure_standard_pages_core($grid ? $grid->id() : 0, $upgrade_in_place, $create_pages);

        $failed = get_transient('mds3_pages_failed_notice');
        $args = ['page' => $page, 'pages' => 'ensured'];
        if (is_array($failed) && $failed) {
            $args['pages_failed'] = count($failed);
        }

        // Compare the page roles before and after, so "nothing was added" is a fact rather
        // than the absence of a message. A click that produces no notice at all reads as a
        // dead button, and the next click is spent guessing what the screen wanted.
        if (!self::standard_pages_changed($before_page_ids) && !$this->missing_standard_pages()) {
            set_transient('mds3_pages_nothing_notice', 1, 5 * MINUTE_IN_SECONDS);
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Every standard page role and the page it points at right now.
     *
     * @return array<string,int>
     */
    private static function standard_page_option_ids() {
        $ids = [];
        foreach (array_keys(PageRepository::standard_labels()) as $type) {
            $ids[$type] = absint(get_option(PageOptionAudit::option_name($type), 0));
        }

        return $ids;
    }

    /**
     * True when this run moved a page role to a different page than it had before - a role
     * that gained a page, or one that pointed at a page that is gone and now points at a
     * real one. Only roles are compared, never the pages behind them, so a run that left
     * every mapping alone is recognisable as the one where nothing happened.
     */
    private static function standard_pages_changed(array $before) {
        foreach (self::standard_page_option_ids() as $type => $page_id) {
            if ($page_id !== (int) ($before[$type] ?? 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render (and clear) the one-shot notice for a standard-pages run that had
     * nothing to create. Silence here is what makes the button look broken.
     */
    public function render_pages_nothing_notice() {
        if (!get_transient('mds3_pages_nothing_notice')) {
            return;
        }
        delete_transient('mds3_pages_nothing_notice');

        echo '<div class="notice notice-success inline"><p>'
            . esc_html__('Every standard page your store needs was already in place, so nothing was created. The list on the Standard Pages step shows which page covers each role.', 'million-dollar-script')
            . '</p></div>';
    }

    /**
     * Create or adopt the standard pages for a grid. Returns type => post_id
     * for every page that already exists or was created/adopted. Split out of
     * the admin action so it is testable without the redirect/exit.
     *
     * A detected MDS2 page is upgraded in place when it is unmodified or its
     * ID is in $upgrade_in_place; otherwise it is left untouched and a fresh
     * MDS3 page is created instead (listed in the pages-created notice).
     *
     * $create_types is the list of optional page roles to create. Pass an empty
     * array to create only the pages a store needs, or null to create every
     * missing role — the older behaviour, kept for callers that offer no choice.
     * A role that already has a page, here or in the site being migrated, is
     * used whatever this says: only creating a page is the choice.
     */
    public static function ensure_standard_pages_core($grid_id, array $upgrade_in_place = [], $create_types = null) {
        $repo = new PageRepository();
        $grid = $grid_id ? (new GridRepository())->find(absint($grid_id)) : null;
        $grid_id = $grid ? $grid->id() : 0;
        $result = [];
        $created = [];
        $failed = [];
        $skipped = [];
        // Page detection scans posts and the legacy metadata table, so run it once
        // for the whole step instead of once per page type.
        $candidates_by_type = self::wizard_page_candidates_by_type();

        foreach (PageRepository::standard_labels() as $type => $label) {
            $candidate = $candidates_by_type[$type] ?? null;
            $existing_id = absint(get_option('mds3_page_' . $type . '_id', 0));
            $page_grid_id = 'grid' === $type ? $grid_id : 0;

            $is_optional = !in_array($type, PageRepository::core_types(), true);
            if (null !== $create_types && $is_optional && !in_array($type, $create_types, true) && !$candidate && !$existing_id) {
                // Not asked for and nothing to use: leave the role unset. The order
                // flow runs without this page, and this step creates it on request.
                $skipped[] = $type;
                continue;
            }

            if ($candidate && self::wizard_candidate_upgraded($candidate, $upgrade_in_place)) {
                $post_id = self::wizard_adopt_candidate_page($candidate, $type, $page_grid_id);
            } elseif (PageOptionAudit::is_usable_page($existing_id)) {
                // Already adopted (possibly by an earlier run): keep it. Deliberately the same
                // test the screen uses to decide a role is missing. The weaker check this used
                // - any post at all, including a trashed or draft one - is what made the button
                // look dead: the step counted the role as missing, this call reported it as
                // handled, so the click created nothing and said nothing (Ryan, 2026-10-16).
                $post_id = $existing_id;
            } elseif ('grid' === $type && $grid) {
                // The grid page is created with the grid; never an empty page.
                $post_id = GridPostType::ensure_page($grid);
                if (!is_wp_error($post_id) && $post_id) {
                    $created[] = ['post_id' => $post_id, 'title' => (string) $grid->get('title', $label), 'url' => (string) get_permalink($post_id)];
                }
            } else {
                $post_id = self::wizard_create_standard_page($type, $label, $grid, $page_grid_id);
                if ($post_id) {
                    $created[] = ['post_id' => $post_id, 'title' => (string) $label, 'url' => (string) get_permalink($post_id)];
                }
            }

            if (is_wp_error($post_id) || !$post_id) {
                $failed[] = [
                    'type' => $type,
                    'title' => (string) $label,
                    'message' => is_wp_error($post_id) ? (string) $post_id->get_error_message() : '',
                ];
                continue;
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

            $result[$type] = absint($post_id);
        }

        if ($created) {
            set_transient('mds3_pages_created_notice', $created, 5 * MINUTE_IN_SECONDS);
        }

        if ($failed) {
            set_transient('mds3_pages_failed_notice', $failed, 5 * MINUTE_IN_SECONDS);
        } else {
            delete_transient('mds3_pages_failed_notice');
        }

        if ($skipped) {
            $result['skipped'] = $skipped;
        }

        return $result;
    }

    /**
     * Render (and clear) the one-shot admin notice listing MDS3 pages that the
     * standard-pages step created next to untouched MDS2 pages.
     */
    public function render_pages_created_notice() {
        $created = get_transient('mds3_pages_created_notice');
        if (!is_array($created) || !$created) {
            return;
        }
        delete_transient('mds3_pages_created_notice');

        echo '<div class="notice notice-success is-dismissible"><p><strong>'
            . esc_html__('Million Dollar Script 2 pages kept; these new Million Dollar Script 3 pages were created instead:', 'million-dollar-script')
            . '</strong></p><ul>';
        foreach ($created as $item) {
            $url = (string) ($item['url'] ?? '');
            $title = (string) ($item['title'] ?? '');
            echo '<li>' . ($url && $title ? '<a href="' . esc_url($url) . '">' : '')
                . esc_html($title ? $title : __('(untitled)', 'million-dollar-script'))
                . ($url && $title ? '</a>' : '') . '</li>';
        }
        echo '</ul></div>';
    }

    private static function wizard_candidate_upgraded(array $candidate, array $upgrade_in_place) {
        return !empty($candidate['unmodified']) || isset($upgrade_in_place[absint($candidate['post_id'])]);
    }

    /**
     * Adopt a detected MDS2 page in place: preserve its original content and
     * rewrite it to the current MDS3 shortcode.
     */
    private static function wizard_adopt_candidate_page(array $candidate, $type, $page_grid_id) {
        $post_id = absint($candidate['post_id']);
        $content = PageRepository::shortcode($type, $page_grid_id);
        $post = get_post($post_id);
        if ($post && (string) $post->post_content !== $content) {
            if (!metadata_exists('post', $post_id, '_mds3_migration_original_content')) {
                update_post_meta($post_id, '_mds3_migration_original_content', (string) $post->post_content);
            }
            wp_update_post(['ID' => $post_id, 'post_content' => $content]);
        }

        return $post_id;
    }

    private static function wizard_create_standard_page($type, $label, $grid, $page_grid_id) {
        $post_id = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => (string) $label,
            'post_name' => sanitize_title((string) $label),
            'post_content' => PageRepository::shortcode($type, $page_grid_id),
        ], true);

        if (is_wp_error($post_id) || !$post_id) {
            return 0;
        }

        return $post_id;
    }

    public function render_pages_failed_notice() {
        $failed = get_transient('mds3_pages_failed_notice');
        if (!is_array($failed) || !$failed) {
            return;
        }
        delete_transient('mds3_pages_failed_notice');

        echo '<div class="notice notice-error is-dismissible"><p><strong>'
            . esc_html__('These standard pages could not be created:', 'million-dollar-script')
            . '</strong></p><ul>';
        foreach ($failed as $item) {
            $title = (string) ($item['title'] ?? '');
            $message = (string) ($item['message'] ?? '');
            echo '<li>' . esc_html($title ? $title : __('(untitled)', 'million-dollar-script'))
                . ($message ? ' <code>' . esc_html($message) . '</code>' : '') . '</li>';
        }
        echo '</ul></div>';
    }

    private static function wizard_page_candidates_by_type() {
        $by_type = [];
        foreach ((new LegacySource())->page_candidates() as $candidate) {
            $type = sanitize_key((string) ($candidate['type'] ?? ''));
            if ($type && !isset($by_type[$type])) {
                $by_type[$type] = $candidate;
            }
        }

        return $by_type;
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
            'upgrade_in_place' => array_filter(array_map('absint', (array) ($_POST['mds2_upgrade_pages'] ?? []))),
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

    public function run_migration_recheck() {
        check_admin_referer('mds3_migration_recheck');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $source_prefix = $this->sanitize_source_prefix(wp_unslash($_POST['mds3_source_prefix'] ?? ''));
        $recheck = \MillionDollarScript\V3\Migration\Recheck\Registry::find(wp_unslash($_POST['mds3_recheck'] ?? ''));

        $error = '';
        $run_id = 0;
        if (null === $recheck) {
            $error = __('This site does not know how to run that check.', 'million-dollar-script');
        } elseif (!$this->grid_enabled()) {
            $error = __('Enable Classic Pixel Grid before importing Million Dollar Script 2 data.', 'million-dollar-script');
        } else {
            $result = \MillionDollarScript\V3\Migration\Recheck\Runner::start($recheck, $source_prefix);
            if (is_wp_error($result)) {
                $error = wp_strip_all_tags($result->get_error_message());
            } else {
                $run_id = absint($result['run_id'] ?? 0);
            }
        }

        $query = [
            'page' => 'mds3-migration',
            'source_prefix' => rawurlencode($source_prefix),
        ];
        if ('' !== $error) {
            $query['migration_error'] = rawurlencode($error);
        } else {
            $query['recheck_started'] = rawurlencode((string) ($recheck ? $recheck->id() : ''));
            $query['migration_job'] = $run_id;
        }
        wp_safe_redirect(add_query_arg($query, admin_url('admin.php')));
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
