<?php
/**
 * MDS3 page records.
 *
 * @package MillionDollarScript\V3\Pages
 */

namespace MillionDollarScript\V3\Pages;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class PageRepository {

    public const TYPES = [
        'grid',
        'order',
        'write-ad',
        'confirm-order',
        'payment',
        'manage',
        'thank-you',
        'list',
        'upload',
        'no-orders',
        'stats',
    ];

    public static function labels() {
        return [
            'grid' => __('Pixel Grid', 'million-dollar-script'),
            'order' => __('Order Pixels', 'million-dollar-script'),
            'write-ad' => __('Write Content', 'million-dollar-script'),
            'confirm-order' => __('Confirm Order', 'million-dollar-script'),
            'payment' => __('Payment', 'million-dollar-script'),
            'manage' => __('Manage Pixels', 'million-dollar-script'),
            'thank-you' => __('Thank You', 'million-dollar-script'),
            'list' => __('Advertiser List', 'million-dollar-script'),
            'upload' => __('Upload', 'million-dollar-script'),
            'no-orders' => __('No Orders', 'million-dollar-script'),
            'stats' => __('Statistics Page', 'million-dollar-script'),
        ];
    }

    public static function standard_labels() {
        $labels = self::labels();
        unset($labels['stats']);

        return $labels;
    }

    public static function option_aliases() {
        return [
            'grid' => ['_milliondollarscript_grid-page', 'milliondollarscript_grid-page', '_mds_grid-page', 'mds_grid-page'],
            'order' => ['_milliondollarscript_users-order-page', 'milliondollarscript_users-order-page', '_mds_users-order-page', 'mds_users-order-page'],
            'write-ad' => ['_milliondollarscript_users-write-ad-page', 'milliondollarscript_users-write-ad-page', '_mds_users-write-ad-page', 'mds_users-write-ad-page'],
            'confirm-order' => ['_milliondollarscript_users-confirm-order-page', '_milliondollarscript_users-checkout-page', 'milliondollarscript_users-confirm-order-page', 'milliondollarscript_users-checkout-page', '_mds_users-confirm-order-page', '_mds_users-checkout-page', 'mds_users-confirm-order-page', 'mds_users-checkout-page'],
            'payment' => ['_milliondollarscript_users-payment-page', 'milliondollarscript_users-payment-page', '_mds_users-payment-page', 'mds_users-payment-page'],
            'manage' => ['_milliondollarscript_users-manage-page', '_milliondollarscript_users-pixels-page', 'milliondollarscript_users-manage-page', 'milliondollarscript_users-pixels-page', '_mds_users-manage-page', '_mds_users-pixels-page', 'mds_users-manage-page', 'mds_users-pixels-page'],
            'thank-you' => ['_milliondollarscript_users-thank-you-page', 'milliondollarscript_users-thank-you-page', '_mds_users-thank-you-page', 'mds_users-thank-you-page'],
            'list' => ['_milliondollarscript_users-list-page', 'milliondollarscript_users-list-page', '_mds_users-list-page', 'mds_users-list-page'],
            'upload' => ['_milliondollarscript_users-upload-page', 'milliondollarscript_users-upload-page', '_mds_users-upload-page', 'mds_users-upload-page'],
            'no-orders' => ['_milliondollarscript_users-no-orders-page', 'milliondollarscript_users-no-orders-page', '_mds_users-no-orders-page', 'mds_users-no-orders-page'],
            'stats' => ['_milliondollarscript_users-stats-page', 'milliondollarscript_users-stats-page', '_mds_users-stats-page', 'mds_users-stats-page'],
        ];
    }

    public static function is_valid_type($type) {
        return in_array(sanitize_key($type), self::TYPES, true);
    }

    public function upsert($post_id, $type, array $data = []) {
        global $wpdb;

        $type = sanitize_key($type);
        if (!self::is_valid_type($type) || !get_post(absint($post_id))) {
            return new \WP_Error('mds3_page_invalid', __('A valid page and page type are required.', 'million-dollar-script'));
        }

        $now = current_time('mysql', true);
        $row = [
            'post_id' => absint($post_id),
            'page_type' => $type,
            'grid_id' => !empty($data['grid_id']) ? absint($data['grid_id']) : null,
            'status' => sanitize_key($data['status'] ?? 'active'),
            'source' => sanitize_key($data['source'] ?? ''),
            'legacy_post_id' => !empty($data['legacy_post_id']) ? absint($data['legacy_post_id']) : null,
            'legacy_metadata' => wp_json_encode(is_array($data['legacy_metadata'] ?? null) ? $data['legacy_metadata'] : []),
            'configuration' => wp_json_encode(is_array($data['configuration'] ?? null) ? $data['configuration'] : []),
            'updated_at' => $now,
        ];

        $existing = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . DB::ident(DB::table('pages')) . ' WHERE post_id = %d', absint($post_id)));
        if ($existing) {
            $wpdb->update(DB::table('pages'), $row, ['id' => absint($existing)]);
            return absint($existing);
        }

        $row['created_at'] = $now;
        $wpdb->insert(DB::table('pages'), $row);

        return absint($wpdb->insert_id);
    }

    public function by_type($type) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('pages')) . ' WHERE page_type = %s AND status = %s ORDER BY id ASC LIMIT 1', sanitize_key($type), 'active'),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public static function shortcode($type, $grid_id = 0) {
        $type = sanitize_key($type);
        $atts = ' type="' . esc_attr($type) . '"';
        if ($grid_id) {
            $atts .= ' grid_id="' . absint($grid_id) . '"';
        }
        if ('order' === $type) {
            $atts .= ' read_only="false"';
        }

        return '[mds3_page' . $atts . ']';
    }
}
