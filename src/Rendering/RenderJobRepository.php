<?php
/**
 * Render job repository.
 *
 * @package MillionDollarScript\V3\Rendering
 */

namespace MillionDollarScript\V3\Rendering;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class RenderJobRepository {

    public function find($id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('render_jobs')) . ' WHERE id = %d', absint($id)),
            ARRAY_A
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function latest_for_grid($grid_id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('render_jobs')) . ' WHERE grid_id = %d ORDER BY id DESC LIMIT 1', absint($grid_id)),
            ARRAY_A
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function upsert_local_ready($grid_id, array $result = []) {
        return $this->create([
            'grid_id' => absint($grid_id),
            'provider' => 'local',
            'status' => 'ready',
            'result' => $result,
        ]);
    }

    public function create(array $data) {
        global $wpdb;

        $now = current_time('mysql', true);
        $insert = [
            'grid_id' => absint($data['grid_id'] ?? 0),
            'provider' => sanitize_key($data['provider'] ?? 'local'),
            'remote_job_id' => sanitize_text_field($data['remote_job_id'] ?? ''),
            'remote_tileset_id' => sanitize_text_field($data['remote_tileset_id'] ?? ''),
            'status' => sanitize_key($data['status'] ?? 'pending'),
            'estimate' => wp_json_encode(is_array($data['estimate'] ?? null) ? $data['estimate'] : []),
            'result' => wp_json_encode(is_array($data['result'] ?? null) ? $data['result'] : []),
            'error_message' => sanitize_text_field($data['error_message'] ?? ''),
            'stale' => !empty($data['stale']) ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $wpdb->insert(DB::table('render_jobs'), $insert);

        return false === $result ? new \WP_Error('mds3_render_job_failed', $wpdb->last_error) : absint($wpdb->insert_id);
    }

    public function update($id, array $data) {
        global $wpdb;

        $allowed = ['remote_job_id', 'remote_tileset_id', 'status', 'estimate', 'result', 'error_message', 'stale'];
        $update = [];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            if (in_array($key, ['estimate', 'result'], true)) {
                $update[$key] = wp_json_encode(is_array($data[$key]) ? $data[$key] : []);
            } elseif ('stale' === $key) {
                $update[$key] = !empty($data[$key]) ? 1 : 0;
            } elseif ('status' === $key) {
                $update[$key] = sanitize_key($data[$key]);
            } else {
                $update[$key] = sanitize_text_field((string) $data[$key]);
            }
        }

        if (!$update) {
            return $this->find($id);
        }

        $update['updated_at'] = current_time('mysql', true);
        $result = $wpdb->update(DB::table('render_jobs'), $update, ['id' => absint($id)]);

        return false === $result ? new \WP_Error('mds3_render_job_update_failed', $wpdb->last_error) : $this->find($id);
    }

    private function hydrate(array $row) {
        foreach (['id', 'grid_id'] as $key) {
            $row[$key] = absint($row[$key] ?? 0);
        }

        $row['stale'] = !empty($row['stale']);
        foreach (['estimate', 'result'] as $key) {
            $decoded = json_decode((string) ($row[$key] ?? ''), true);
            $row[$key] = is_array($decoded) ? $decoded : [];
        }

        return $row;
    }
}
