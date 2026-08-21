<?php
/**
 * MDS2 import target lookup helpers.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration\Concerns;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

trait ResolvesLegacyImportTargets {

    private function source_block($legacy_grid_id, $legacy_block_id) {
        global $wpdb;

        $table = $this->source->table('blocks');
        if (!DB::table_exists($table)) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . DB::ident($table) . ' WHERE banner_id = %d AND block_id = %d LIMIT 1',
                absint($legacy_grid_id),
                absint($legacy_block_id)
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    private function legacy_grid($legacy_grid_id) {
        global $wpdb;

        $legacy_grid_id = absint($legacy_grid_id);
        if (isset($this->legacy_grids[$legacy_grid_id])) {
            return $this->legacy_grids[$legacy_grid_id];
        }

        $table = $this->source->table('banners');
        if (!DB::table_exists($table)) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::ident($table) . ' WHERE banner_id = %d LIMIT 1', $legacy_grid_id),
            ARRAY_A
        );
        if (is_array($row)) {
            $this->legacy_grids[$legacy_grid_id] = $row;

            return $row;
        }

        return null;
    }

    private function target_grid_id($legacy_grid_id) {
        return $this->map->target_id($this->source->source_prefix(), 'banner', absint($legacy_grid_id), 'grid');
    }

    private function first_target_grid_id() {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT legacy_id, mds3_id FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND entity_type = %s AND mds3_entity_type = %s',
            $this->source->source_prefix(),
            'banner',
            'grid'
        ), ARRAY_A);
        $rows = is_array($rows) ? $rows : [];
        usort($rows, static function ($left, $right) {
            $numeric = absint($left['legacy_id'] ?? 0) <=> absint($right['legacy_id'] ?? 0);

            return 0 !== $numeric ? $numeric : strcmp((string) ($left['legacy_id'] ?? ''), (string) ($right['legacy_id'] ?? ''));
        });

        foreach ($rows as $row) {
            $target_id = absint($row['mds3_id'] ?? 0);
            if ($target_id && $this->target_exists('grids', $target_id)) {
                return $target_id;
            }
        }

        return 0;
    }

    private function is_source_target_grid($target_grid_id) {
        global $wpdb;

        $target_grid_id = absint($target_grid_id);
        if (!$target_grid_id || !$this->target_exists('grids', $target_grid_id)) {
            return false;
        }

        return (bool) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND entity_type = %s AND mds3_entity_type = %s AND mds3_id = %d LIMIT 1',
            $this->source->source_prefix(),
            'banner',
            'grid',
            $target_grid_id
        ));
    }

    private function update_grid($grid_id, array $payload) {
        global $wpdb;

        $wpdb->update(DB::table('grids'), [
            'title' => $payload['title'],
            'description' => $payload['description'],
            'width' => $payload['width'],
            'height' => $payload['height'],
            'block_width' => $payload['block_width'],
            'block_height' => $payload['block_height'],
            'price_per_block' => $payload['price_per_block'],
            'currency' => $payload['currency'],
            'status' => $payload['status'],
            'settings' => wp_json_encode($payload['settings']),
            'updated_at' => current_time('mysql', true),
        ], ['id' => absint($grid_id)]);
    }

    private function target_exists($table, $id) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . DB::ident(DB::table($table)) . ' WHERE id = %d LIMIT 1', absint($id)));
    }

    private function target_block($id) {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d', absint($id)), ARRAY_A);

        return is_array($row) ? $row : null;
    }
}
