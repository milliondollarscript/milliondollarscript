<?php
/**
 * Admin grid, package, price-rule, and availability actions.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Grid\GridPostType;
use MillionDollarScript\V3\Grid\GridBackground;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Grid\GridTransfer;
use MillionDollarScript\V3\Grid\PackageRepository;
use MillionDollarScript\V3\Grid\PriceRuleRepository;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesGridAdminActions {

    public function create_grid() {
        check_admin_referer('mds3_create_grid');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $raw_post = wp_unslash($_POST);
        $background_error = $this->validate_grid_background($raw_post, null);
        if (is_wp_error($background_error)) {
            wp_safe_redirect(add_query_arg(
                'grid_error',
                wp_strip_all_tags($background_error->get_error_message()),
                admin_url('admin.php?page=mds3-grids')
            ));
            exit;
        }
        $errors = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/validate/grid', new \WP_Error(), $raw_post, null, 'create');
        if (is_wp_error($errors) && $errors->has_errors()) {
            wp_safe_redirect(add_query_arg(
                'grid_error',
                wp_strip_all_tags($errors->get_error_message()),
                admin_url('admin.php?page=mds3-grids')
            ));
            exit;
        }

        $data = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/sanitize/grid', $raw_post, null, 'create');
        $created = (new GridRepository())->create(is_array($data) ? $data : $raw_post);
        if (is_wp_error($created)) {
            wp_safe_redirect(add_query_arg(
                'grid_error',
                wp_strip_all_tags($created->get_error_message()),
                admin_url('admin.php?page=mds3-grids')
            ));
            exit;
        }

        if ($created) {
            GridPostType::ensure_page($created);
            \MillionDollarScript\Core\Hooks::do('million-dollar-script/admin/grid/saved', $created, is_array($data) ? $data : $raw_post, null, 'create');
        }
        wp_safe_redirect(admin_url('admin.php?page=mds3-grids&grid_id=' . absint($created->id()) . '&public_page=1'));
        exit;
    }

    public function update_grid() {
        $grid_id = absint($_POST['grid_id'] ?? 0);
        check_admin_referer('mds3_update_grid_' . $grid_id);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $repo = new GridRepository();
        $grid = $repo->find($grid_id);
        if (!$grid) {
            wp_safe_redirect(add_query_arg(
                'grid_error',
                wp_strip_all_tags(__('Grid not found.', 'million-dollar-script')),
                admin_url('admin.php?page=mds3-grids')
            ));
            exit;
        }

        $raw_post = wp_unslash($_POST);
        $background_error = $this->validate_grid_background($raw_post, $grid);
        if (is_wp_error($background_error)) {
            wp_safe_redirect(add_query_arg(
                'grid_error',
                wp_strip_all_tags($background_error->get_error_message()),
                admin_url('admin.php?page=mds3-grids&grid_id=' . $grid_id)
            ));
            exit;
        }
        $errors = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/validate/grid', new \WP_Error(), $raw_post, $grid, 'update');
        if (is_wp_error($errors) && $errors->has_errors()) {
            wp_safe_redirect(add_query_arg(
                'grid_error',
                wp_strip_all_tags($errors->get_error_message()),
                admin_url('admin.php?page=mds3-grids&grid_id=' . $grid_id)
            ));
            exit;
        }

        $data = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/sanitize/grid', $raw_post, $grid, 'update');
        $updated = $repo->update($grid_id, is_array($data) ? $data : $raw_post);
        if (is_wp_error($updated)) {
            wp_safe_redirect(add_query_arg(
                'grid_error',
                wp_strip_all_tags($updated->get_error_message()),
                admin_url('admin.php?page=mds3-grids&grid_id=' . $grid_id)
            ));
            exit;
        }

        \MillionDollarScript\Core\Hooks::do('million-dollar-script/admin/grid/saved', $updated, is_array($data) ? $data : $raw_post, $grid, 'update');
        wp_safe_redirect(admin_url('admin.php?page=mds3-grids&grid_id=' . $grid_id . '&updated=1'));
        exit;
    }

    private function validate_grid_background(array $data, $grid) {
        if (!array_key_exists('background_image_id', $data)) {
            return true;
        }

        $attachment_id = absint($data['background_image_id']);
        $existing_id = $grid && method_exists($grid, 'settings')
            ? absint($grid->settings()['background_image_id'] ?? 0)
            : 0;
        if ($attachment_id !== $existing_id && !current_user_can('upload_files')) {
            return new \WP_Error('mds3_grid_background_permission', __('You do not have permission to choose a grid background image.', 'million-dollar-script'));
        }

        return GridBackground::validate_attachment($attachment_id);
    }

    public function archive_grid() {
        $grid_id = absint($_POST['grid_id'] ?? 0);
        check_admin_referer('mds3_archive_grid_' . $grid_id);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        (new GridRepository())->archive($grid_id);
        wp_safe_redirect(admin_url('admin.php?page=mds3-grids'));
        exit;
    }

    public function export_grids() {
        check_admin_referer('mds3_export_grids');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $raw_post = wp_unslash($_POST);
        $grid_ids = array_map('absint', (array) ($raw_post['grid_ids'] ?? []));
        if (!empty($raw_post['grid_id'])) {
            $grid_ids[] = absint($raw_post['grid_id']);
        }

        $payload = (new GridTransfer())->export_payload($grid_ids);
        if (is_wp_error($payload)) {
            wp_safe_redirect(add_query_arg(
                'grid_error',
                wp_strip_all_tags($payload->get_error_message()),
                admin_url('admin.php?page=mds3-grids')
            ));
            exit;
        }

        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            wp_safe_redirect(add_query_arg(
                'grid_error',
                wp_strip_all_tags(__('Grid export could not be encoded.', 'million-dollar-script')),
                admin_url('admin.php?page=mds3-grids')
            ));
            exit;
        }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="million-dollar-script-grids-' . gmdate('Ymd-His') . '.json"');
        header('X-Content-Type-Options: nosniff');
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public function import_grids() {
        check_admin_referer('mds3_import_grids');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $file = $_FILES['grid_import_file'] ?? null;
        $tmp_name = is_array($file) ? (string) ($file['tmp_name'] ?? '') : '';
        $name = is_array($file) ? sanitize_file_name((string) ($file['name'] ?? '')) : '';
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $size = is_array($file) ? absint($file['size'] ?? 0) : 0;
        $max_size = (int) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/grid/import/max/file/size', 5 * MB_IN_BYTES);
        $error = is_array($file) ? absint($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

        if (UPLOAD_ERR_OK !== $error || !$tmp_name || !is_uploaded_file($tmp_name) || 'json' !== $extension) {
            $this->redirect_grid_import_error(__('Choose a valid grid export file.', 'million-dollar-script'));
        }

        if ($size > max(1, $max_size)) {
            $this->redirect_grid_import_error(__('The grid export file is too large.', 'million-dollar-script'));
        }

        $contents = file_get_contents($tmp_name);
        if (!is_string($contents) || '' === trim($contents)) {
            $this->redirect_grid_import_error(__('The grid export file is empty.', 'million-dollar-script'));
        }

        $result = (new GridTransfer())->import_payload($contents);
        if (is_wp_error($result)) {
            $this->redirect_grid_import_error($result->get_error_message());
        }

        set_transient('mds3_grid_import_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg(
            'grid_imported',
            count($result['created'] ?? []),
            admin_url('admin.php?page=mds3-grids')
        ));
        exit;
    }

    public function create_grid_page() {
        $grid_id = absint($_POST['grid_id'] ?? 0);
        check_admin_referer('mds3_create_grid_page_' . $grid_id);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $grid = (new GridRepository())->find($grid_id);
        if ($grid) {
            GridPostType::ensure_page($grid);
        }

        wp_safe_redirect(admin_url('admin.php?page=mds3-grids'));
        exit;
    }

    public function set_grid_page_mode() {
        $grid_id = absint($_POST['grid_id'] ?? 0);
        check_admin_referer('mds3_set_grid_page_mode_' . $grid_id);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $grid = (new GridRepository())->find($grid_id);
        $mode = sanitize_key(wp_unslash($_POST['page_mode'] ?? 'read_only'));
        if (!$grid || !in_array($mode, ['read_only', 'interactive'], true)) {
            wp_safe_redirect(add_query_arg(
                'grid_error',
                wp_strip_all_tags(__('Grid page mode could not be updated.', 'million-dollar-script')),
                admin_url('admin.php?page=mds3-grids&grid_id=' . $grid_id . '&public_page=1')
            ));
            exit;
        }

        $updated = GridPostType::set_page_mode($grid, $mode);
        $redirect = admin_url('admin.php?page=mds3-grids&grid_id=' . $grid_id . '&public_page=1');
        if (is_wp_error($updated)) {
            $redirect = add_query_arg('grid_error', wp_strip_all_tags($updated->get_error_message()), $redirect);
        } else {
            $redirect = add_query_arg('page_mode', $mode, $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public function save_package() {
        $grid_id = absint($_POST['grid_id'] ?? 0);
        check_admin_referer('mds3_save_package_' . $grid_id);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        (new PackageRepository())->save(wp_unslash($_POST));
        wp_safe_redirect(admin_url('admin.php?page=mds3-grids&grid_id=' . $grid_id . '&packages=1'));
        exit;
    }

    public function archive_package() {
        $package_id = absint($_POST['package_id'] ?? 0);
        $grid_id = absint($_POST['grid_id'] ?? 0);
        check_admin_referer('mds3_archive_package_' . $package_id);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        (new PackageRepository())->archive($package_id);
        wp_safe_redirect(admin_url('admin.php?page=mds3-grids&grid_id=' . $grid_id . '&packages=1'));
        exit;
    }

    public function save_price_rule() {
        $grid_id = absint($_POST['grid_id'] ?? 0);
        check_admin_referer('mds3_save_price_rule_' . $grid_id);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        (new PriceRuleRepository())->save(wp_unslash($_POST));
        wp_safe_redirect(admin_url('admin.php?page=mds3-grids&grid_id=' . $grid_id . '&price_rules=1'));
        exit;
    }

    public function archive_price_rule() {
        $rule_id = absint($_POST['price_rule_id'] ?? 0);
        $grid_id = absint($_POST['grid_id'] ?? 0);
        check_admin_referer('mds3_archive_price_rule_' . $rule_id);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        (new PriceRuleRepository())->archive($rule_id);
        wp_safe_redirect(admin_url('admin.php?page=mds3-grids&grid_id=' . $grid_id . '&price_rules=1'));
        exit;
    }

    public function set_region_status() {
        $grid_id = absint($_POST['grid_id'] ?? 0);
        check_admin_referer('mds3_set_region_status_' . $grid_id);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $grid = (new GridRepository())->find($grid_id);
        $result = null;
        if ($grid) {
            $result = (new BlockRepository())->set_region_status($grid, wp_unslash($_POST), sanitize_key(wp_unslash($_POST['region_status'] ?? 'unavailable')), [
                'note' => sanitize_text_field(wp_unslash($_POST['note'] ?? '')),
            ]);
        }

        $redirect = admin_url('admin.php?page=mds3-grids&grid_id=' . $grid_id . '&availability=1');
        if (is_wp_error($result)) {
            $redirect = add_query_arg('region_error', wp_strip_all_tags($result->get_error_message()), $redirect);
        } elseif (is_array($result)) {
            $redirect = add_query_arg([
                'region_updated' => absint($result['changed'] ?? 0),
                'region_skipped' => absint($result['skipped'] ?? 0),
            ], $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    private function redirect_grid_import_error($message) {
        wp_safe_redirect(add_query_arg(
            'grid_error',
            wp_strip_all_tags((string) $message),
            admin_url('admin.php?page=mds3-grids')
        ));
        exit;
    }
}
