<?php
/**
 * Admin API key and policy actions.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Rest\ApiGovernance;
use MillionDollarScript\V3\Rest\ApiKeyRepository;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesApiAdminActions {

    public function create_api_key() {
        check_admin_referer('mds3_create_api_key');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $post = wp_unslash($_POST);
        $scopes = $post['scopes'] ?? [];
        if (!is_array($scopes)) {
            $scopes = preg_split('/[\s,]+/', (string) $scopes);
        }
        $custom_scopes = preg_split('/[\s,]+/', (string) ($post['custom_scopes'] ?? ''));
        $scopes = array_merge(is_array($scopes) ? $scopes : [], is_array($custom_scopes) ? $custom_scopes : []);
        $created = (new ApiKeyRepository())->create(
            sanitize_text_field($post['name'] ?? ''),
            $scopes,
            absint($post['rate_limit_per_hour'] ?? 120)
        );
        if (is_wp_error($created)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'mds3-api',
                'api_error' => rawurlencode($created->get_error_message()),
            ], admin_url('admin.php')));
            exit;
        }

        set_transient('mds3_api_key_created_' . get_current_user_id(), (string) ($created['key'] ?? ''), 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=mds3-api&saved=1'));
        exit;
    }

    public function revoke_api_key() {
        $key_id = absint($_POST['key_id'] ?? 0);
        check_admin_referer('mds3_revoke_api_key_' . $key_id);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        (new ApiKeyRepository())->revoke($key_id);
        wp_safe_redirect(admin_url('admin.php?page=mds3-api&revoked=1'));
        exit;
    }

    public function rotate_api_key() {
        $key_id = absint($_POST['key_id'] ?? 0);
        check_admin_referer('mds3_rotate_api_key_' . $key_id);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $rotated = (new ApiKeyRepository())->rotate($key_id);
        if (is_wp_error($rotated)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'mds3-api',
                'api_error' => rawurlencode($rotated->get_error_message()),
            ], admin_url('admin.php')));
            exit;
        }

        set_transient('mds3_api_key_created_' . get_current_user_id(), (string) ($rotated['key'] ?? ''), 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=mds3-api&rotated=1'));
        exit;
    }

    public function save_api_policies() {
        check_admin_referer('mds3_save_api_policies');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $post = wp_unslash($_POST);
        (new ApiGovernance())->save_policies(is_array($post['policies'] ?? null) ? $post['policies'] : []);
        wp_safe_redirect(admin_url('admin.php?page=mds3-api&saved=1'));
        exit;
    }
}
