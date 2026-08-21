<?php
/**
 * Availability-region operations for sparse grids.
 *
 * @package MillionDollarScript\V3\Blocks
 */

namespace MillionDollarScript\V3\Blocks\Concerns;

use MillionDollarScript\V3\Grid\Grid;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

trait ManagesAvailabilityRegions {
    use ComputesAvailabilityRegions;
    use PersistsAvailabilityRegions;

    public function unavailable_regions(Grid $grid) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, x, y, metadata FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id = %d AND status = %s ORDER BY y ASC, x ASC',
                $grid->id(),
                'unavailable'
            ),
            ARRAY_A
        );

        $geometry = $grid->geometry();
        $groups = [];
        foreach ($this->stored_unavailable_regions($grid) as $region) {
            $groups['stored:' . $region['id']] = $region;
        }

        foreach (is_array($rows) ? $rows : [] as $row) {
            $coord = $geometry->coordinate_from_pixel(absint($row['x'] ?? 0), absint($row['y'] ?? 0));
            if (!$coord) {
                continue;
            }

            $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
            $metadata = is_array($metadata) ? $metadata : [];
            $region_id = sanitize_key((string) ($metadata['availability_region_id'] ?? ''));
            $key = $region_id ? 'region:' . $region_id : 'block:' . absint($row['id']);

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'id' => $region_id,
                    'row_from' => absint($coord['row']),
                    'row_to' => absint($coord['row']),
                    'col_from' => absint($coord['col']),
                    'col_to' => absint($coord['col']),
                    'count' => 0,
                    'note' => sanitize_text_field((string) ($metadata['note'] ?? '')),
                ];
            }

            $groups[$key]['row_from'] = min($groups[$key]['row_from'], absint($coord['row']));
            $groups[$key]['row_to'] = max($groups[$key]['row_to'], absint($coord['row']));
            $groups[$key]['col_from'] = min($groups[$key]['col_from'], absint($coord['col']));
            $groups[$key]['col_to'] = max($groups[$key]['col_to'], absint($coord['col']));
            $groups[$key]['count']++;
        }

        return array_values($groups);
    }

    public function coordinate_in_stored_unavailable_region(Grid $grid, $row, $col) {
        $row = absint($row);
        $col = absint($col);

        foreach ($this->stored_unavailable_regions_for_grid($grid) as $region) {
            if (
                $row >= absint($region['row_from'] ?? 0) &&
                $row <= absint($region['row_to'] ?? 0) &&
                $col >= absint($region['col_from'] ?? 0) &&
                $col <= absint($region['col_to'] ?? 0)
            ) {
                return true;
            }
        }

        return false;
    }

    public function stored_unavailable_regions_for_grid(Grid $grid) {
        return $this->stored_unavailable_regions($grid);
    }

    public function set_region_status(Grid $grid, array $bounds, $status, array $metadata = []) {
        global $wpdb;

        $status = sanitize_key($status);
        if (!in_array($status, ['available', 'unavailable'], true)) {
            return new \WP_Error('mds3_invalid_block_status', __('Only available and unavailable region updates are supported.', 'million-dollar-script'));
        }

        $region = $this->normalize_region($grid, $bounds);
        if (is_wp_error($region)) {
            return $region;
        }

        $total = ($region['row_to'] - $region['row_from'] + 1) * ($region['col_to'] - $region['col_from'] + 1);
        $rect = $this->region_rect($grid, $region);
        if (is_wp_error($rect)) {
            return $rect;
        }

        $table = DB::ident(DB::table('blocks'));
        $now = current_time('mysql', true);
        $skipped = $this->count_protected_blocks($grid, $rect);
        $changed = 0;

        if ('available' === $status) {
            $stored_regions = $this->stored_unavailable_regions($grid);
            $stored_result = $this->subtract_stored_unavailable_region($grid, $stored_regions, $region);
            if (is_wp_error($stored_result)) {
                return $stored_result;
            }

            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE grid_id = %d AND x BETWEEN %d AND %d AND y BETWEEN %d AND %d AND status = %s AND (order_id IS NULL OR order_id = 0) AND (user_id IS NULL OR user_id = 0) AND price_override IS NULL AND (reserved_until IS NULL OR reserved_until = %s)",
                    $grid->id(),
                    $rect['x_from'],
                    $rect['x_to'],
                    $rect['y_from'],
                    $rect['y_to'],
                    'unavailable',
                    '0000-00-00 00:00:00'
                )
            );
            if (false === $deleted) {
                return new \WP_Error('mds3_region_delete_failed', $wpdb->last_error);
            }

            $updated = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s, reserved_until = NULL, updated_at = %s WHERE grid_id = %d AND x BETWEEN %d AND %d AND y BETWEEN %d AND %d AND status NOT IN (%s, %s) AND (status <> %s OR reserved_until IS NOT NULL)",
                    'available',
                    $now,
                    $grid->id(),
                    $rect['x_from'],
                    $rect['x_to'],
                    $rect['y_from'],
                    $rect['y_to'],
                    'sold',
                    'reserved',
                    'available'
                )
            );
            if (false === $updated) {
                return new \WP_Error('mds3_region_update_failed', $wpdb->last_error);
            }

            $changed = max(0, (int) $deleted) + max(0, (int) $updated) + max(0, absint($stored_result['changed'] ?? 0));
        } else {
            $metadata = array_merge($metadata, [
                'availability_source' => 'admin_region',
                'availability_region_id' => sanitize_key((string) ($metadata['availability_region_id'] ?? '')) ?: wp_generate_uuid4(),
                'region' => $region,
            ]);

            $stored_regions = $this->stored_unavailable_regions($grid);
            $remaining_regions = [];
            $already_unavailable = 0;
            foreach ($stored_regions as $stored_region) {
                $pieces = $this->subtract_region($stored_region, $region);
                $already_unavailable += max(0, $this->region_area($stored_region) - $this->region_cell_count($pieces));
                foreach ($pieces as $piece) {
                    $piece['note'] = sanitize_text_field((string) ($stored_region['note'] ?? ''));
                    $piece['source'] = sanitize_key((string) ($stored_region['source'] ?? 'admin_region'));
                    $remaining_regions[] = $piece;
                }
            }

            $remaining_regions[] = $this->stored_region_payload($grid, $region, $metadata);
            $saved = $this->save_stored_unavailable_regions($grid, array_filter($remaining_regions));
            if (is_wp_error($saved)) {
                return $saved;
            }

            $changed = max(0, $total - $skipped - $already_unavailable);
        }

        return [
            'grid_id' => $grid->id(),
            'status' => $status,
            'region' => $region,
            'requested' => $total,
            'changed' => $changed,
            'skipped' => $skipped,
        ];
    }
}
