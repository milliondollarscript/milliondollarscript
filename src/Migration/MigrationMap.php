<?php
/**
 * Idempotent legacy-to-MDS3 entity map.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class MigrationMap {

    public function target_id($source_prefix, $entity_type, $legacy_id, $mds3_entity_type) {
        $row = $this->get($source_prefix, $entity_type, $legacy_id, $mds3_entity_type);

        return $row ? absint($row['mds3_id']) : 0;
    }

    public function get($source_prefix, $entity_type, $legacy_id, $mds3_entity_type) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND entity_type = %s AND legacy_id = %s AND mds3_entity_type = %s LIMIT 1',
                (string) $source_prefix,
                sanitize_key($entity_type),
                (string) $legacy_id,
                sanitize_key($mds3_entity_type)
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function remember($source_prefix, $entity_type, $legacy_id, $mds3_entity_type, $mds3_id, array $metadata = []) {
        global $wpdb;

        $source_prefix = (string) $source_prefix;
        $entity_type = sanitize_key($entity_type);
        $legacy_id = (string) $legacy_id;
        $mds3_entity_type = sanitize_key($mds3_entity_type);
        $now = current_time('mysql', true);

        $existing = $this->get($source_prefix, $entity_type, $legacy_id, $mds3_entity_type);
        $row = [
            'source_prefix' => $source_prefix,
            'entity_type' => $entity_type,
            'legacy_id' => $legacy_id,
            'mds3_entity_type' => $mds3_entity_type,
            'mds3_id' => absint($mds3_id),
            'metadata' => wp_json_encode($metadata),
        ];

        if ($existing) {
            $wpdb->update(DB::table('migration_map'), $row, ['id' => absint($existing['id'])]);

            return absint($existing['id']);
        }

        $row['created_at'] = $now;
        $wpdb->insert(DB::table('migration_map'), $row);

        return absint($wpdb->insert_id);
    }

    public function mapped_count($source_prefix, $entity_type, $mds3_entity_type = '') {
        global $wpdb;

        if ($mds3_entity_type) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND entity_type = %s AND mds3_entity_type = %s',
                    (string) $source_prefix,
                    sanitize_key($entity_type),
                    sanitize_key($mds3_entity_type)
                )
            );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s AND entity_type = %s',
                (string) $source_prefix,
                sanitize_key($entity_type)
            )
        );
    }
}
