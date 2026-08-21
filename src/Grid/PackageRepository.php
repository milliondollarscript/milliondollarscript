<?php
/**
 * Grid package repository.
 *
 * @package MillionDollarScript\V3\Grid
 */

namespace MillionDollarScript\V3\Grid;

use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class PackageRepository {

    public function for_grid($grid_id, array $statuses = []) {
        global $wpdb;

        $sql = 'SELECT * FROM ' . DB::ident(DB::table('packages')) . ' WHERE grid_id = %d';
        $args = [absint($grid_id)];
        if ($statuses) {
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $sql .= " AND status IN ({$placeholders})";
            foreach ($statuses as $status) {
                $args[] = sanitize_key($status);
            }
        }
        $sql .= ' ORDER BY is_default DESC, id ASC';

        $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);

        return array_map([$this, 'normalize'], is_array($rows) ? $rows : []);
    }

    public function active_for_grid($grid_id) {
        return $this->for_grid($grid_id, ['active']);
    }

    public function find($id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('packages')) . ' WHERE id = %d', absint($id)),
            ARRAY_A
        );

        return is_array($row) ? $this->normalize($row) : null;
    }

    public function default_for_grid($grid_id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . DB::ident(DB::table('packages')) . " WHERE grid_id = %d AND status = 'active' AND is_default = 1 ORDER BY id ASC LIMIT 1",
                absint($grid_id)
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->normalize($row) : null;
    }

    public function save(array $data) {
        global $wpdb;

        $grid_id = absint($data['grid_id'] ?? 0);
        if (!$grid_id) {
            return new \WP_Error('mds3_package_grid_required', __('Grid is required.', 'million-dollar-script'));
        }

        $id = absint($data['id'] ?? 0);
        $now = current_time('mysql', true);
        $is_default = !empty($data['is_default']) ? 1 : 0;
        $status = sanitize_key($data['status'] ?? 'active');
        if (!in_array($status, ['active', 'paused', 'archived'], true)) {
            $status = 'active';
        }

        $payload = [
            'grid_id' => $grid_id,
            'title' => sanitize_text_field($data['title'] ?? __('Untitled package', 'million-dollar-script')),
            'description' => sanitize_text_field($data['description'] ?? ''),
            'duration_days' => absint($data['duration_days'] ?? 0),
            'price' => (float) ($data['price'] ?? 0),
            'currency' => Currency::code($data['currency'] ?? Currency::current_code()),
            'max_orders' => absint($data['max_orders'] ?? 0),
            'is_default' => $is_default,
            'status' => $status,
            'metadata' => $this->metadata_json($data['metadata'] ?? []),
            'updated_at' => $now,
        ];

        if ($is_default) {
            $wpdb->update(DB::table('packages'), ['is_default' => 0], ['grid_id' => $grid_id]);
        }

        if ($id && $this->find($id)) {
            $result = $wpdb->update(DB::table('packages'), $payload, ['id' => $id]);

            return false === $result ? new \WP_Error('mds3_package_update_failed', $wpdb->last_error) : $this->find($id);
        }

        $payload['created_at'] = $now;
        $result = $wpdb->insert(DB::table('packages'), $payload);

        return false === $result ? new \WP_Error('mds3_package_create_failed', $wpdb->last_error) : $this->find($wpdb->insert_id);
    }

    public function archive($id) {
        global $wpdb;

        $result = $wpdb->update(
            DB::table('packages'),
            [
                'status' => 'archived',
                'is_default' => 0,
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => absint($id)]
        );

        return false !== $result;
    }

    private function normalize(array $row) {
        $row['id'] = absint($row['id'] ?? 0);
        $row['grid_id'] = absint($row['grid_id'] ?? 0);
        $row['duration_days'] = absint($row['duration_days'] ?? 0);
        $row['price'] = (float) ($row['price'] ?? 0);
        $row['max_orders'] = absint($row['max_orders'] ?? 0);
        $row['is_default'] = !empty($row['is_default']) ? 1 : 0;
        $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
        $row['metadata'] = is_array($metadata) ? $metadata : [];

        return $row;
    }

    private function metadata_json($metadata) {
        if (is_array($metadata)) {
            return wp_json_encode($metadata);
        }

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            if (is_array($decoded)) {
                return wp_json_encode($decoded);
            }
        }

        return wp_json_encode([]);
    }
}
