<?php
/**
 * Documentation admin actions.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Docs\RemoteDocsClient;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesDocsAdminActions {

    public function refresh_docs() {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have permission to refresh documentation.', 'million-dollar-script'),
                esc_html__('Documentation refresh denied', 'million-dollar-script'),
                ['response' => 403]
            );
        }

        check_admin_referer('million_dollar_script_refresh_docs');

        $redirect_args = ['page' => 'mds3-docs'];
        $package_slug = sanitize_key(wp_unslash($_POST['package'] ?? ''));
        $doc_key = sanitize_text_field(wp_unslash($_POST['doc'] ?? ''));
        $search = sanitize_text_field(wp_unslash($_POST['s'] ?? ''));
        if ($package_slug) {
            $redirect_args['package'] = $package_slug;
        }
        if ($doc_key) {
            $redirect_args['doc'] = $doc_key;
        }
        if ($search) {
            $redirect_args['s'] = $search;
        }

        $result = RemoteDocsClient::manual_refresh();
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            $redirect_args['docs_refresh'] = 'cooldown';
            $redirect_args['retry_after'] = max(1, absint(is_array($data) ? ($data['retry_after'] ?? 0) : 0));
        } else {
            $redirect_args['docs_refresh'] = 'refreshed';
        }

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
}
