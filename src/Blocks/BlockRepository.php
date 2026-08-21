<?php
/**
 * Sparse block repository.
 *
 * @package MillionDollarScript\V3\Blocks
 */

namespace MillionDollarScript\V3\Blocks;

use MillionDollarScript\V3\Blocks\Concerns\ManagesAvailabilityRegions;
use MillionDollarScript\V3\Grid\Grid;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class BlockRepository {
    use ManagesAvailabilityRegions;

    public function for_grid($grid_id, array $statuses = []) {
        global $wpdb;

        $sql = 'SELECT * FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id = %d';
        $args = [absint($grid_id)];
        $statuses = array_values(array_filter(array_map('sanitize_key', $statuses)));
        if ($statuses) {
            $sql .= ' AND status IN (' . implode(', ', array_fill(0, count($statuses), '%s')) . ')';
            $args = array_merge($args, $statuses);
        }
        $sql .= ' ORDER BY y ASC, x ASC';

        $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function find($id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d', absint($id)),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function find_by_coordinate(Grid $grid, $row, $col) {
        global $wpdb;

        $rect = $grid->geometry()->rect($row, $col);
        if (!$rect) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id = %d AND x = %d AND y = %d LIMIT 1',
                $grid->id(),
                $rect['x'],
                $rect['y']
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function materialize(Grid $grid, $row, $col) {
        global $wpdb;

        $geometry = $grid->geometry();
        $rect = $geometry->rect($row, $col);
        if (!$rect) {
            return new \WP_Error('mds3_block_out_of_range', __('Block coordinate is outside the grid.', 'million-dollar-script'));
        }

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id = %d AND x = %d AND y = %d LIMIT 1',
                $grid->id(),
                $rect['x'],
                $rect['y']
            ),
            ARRAY_A
        );

        if (is_array($existing)) {
            return $existing;
        }

        $now = current_time('mysql', true);
        $insert = [
            'grid_id' => $grid->id(),
            'x' => $rect['x'],
            'y' => $rect['y'],
            'width' => $rect['width'],
            'height' => $rect['height'],
            'status' => 'available',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $wpdb->insert(DB::table('blocks'), $insert);

        return false === $result
            ? new \WP_Error('mds3_block_create_failed', $wpdb->last_error)
            : $this->find($wpdb->insert_id);
    }

    public function reserve(array $block, $user_id = 0, $minutes = 30) {
        global $wpdb;

        if ('available' !== ($block['status'] ?? '')) {
            return new \WP_Error('mds3_block_unavailable', __('Block is not available.', 'million-dollar-script'));
        }

        $reserved_until = gmdate('Y-m-d H:i:s', time() + max(1, absint($minutes)) * MINUTE_IN_SECONDS);

        $wpdb->update(
            DB::table('blocks'),
            [
                'status' => 'reserved',
                'user_id' => absint($user_id) ?: null,
                'reserved_until' => $reserved_until,
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => absint($block['id'])]
        );

        return $this->find($block['id']);
    }

    public function counts($grid_id) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT status, COUNT(*) total FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id = %d GROUP BY status', absint($grid_id)),
            ARRAY_A
        );

        $counts = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        $grid = (new GridRepository())->find($grid_id);
        if ($grid) {
            $counts['unavailable'] = absint($counts['unavailable'] ?? 0) + $this->region_cell_count($this->stored_unavailable_regions($grid));
        }

        return $counts;
    }

    public function mark_by_order($order_id, $status) {
        global $wpdb;

        $order_id = absint($order_id);
        $status = sanitize_key($status);
        if (!$order_id || !$status) {
            return false;
        }

        $updated_at = current_time('mysql', true);
        $marked = false !== $wpdb->update(
            DB::table('blocks'),
            [
                'status' => $status,
                'reserved_until' => null,
                'updated_at' => $updated_at,
            ],
            ['order_id' => $order_id]
        );

        if (!$marked || !in_array($status, ['reserved', 'sold'], true)) {
            return $marked;
        }

        $block_ids = array_values(array_filter(array_map('absint', (array) $wpdb->get_col(
            $wpdb->prepare(
                'SELECT DISTINCT block_id FROM ' . DB::ident(DB::table('order_items')) . ' WHERE order_id = %d AND block_id IS NOT NULL AND block_id > 0',
                $order_id
            )
        ))));
        if (!$block_ids) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($block_ids), '%d'));
        $claimed = $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . DB::ident(DB::table('blocks')) . " SET status = %s, order_id = %d, reserved_until = NULL, updated_at = %s WHERE id IN ({$placeholders}) AND (order_id IS NULL OR order_id = 0 OR order_id = %d)",
                array_merge([$status, $order_id, $updated_at], $block_ids, [$order_id])
            )
        );

        return false !== $claimed;
    }

    public function release_by_order($order_id) {
        global $wpdb;

        return false !== $wpdb->update(
            DB::table('blocks'),
            [
                'status' => 'available',
                'user_id' => null,
                'order_id' => null,
                'reserved_until' => null,
                'updated_at' => current_time('mysql', true),
            ],
            ['order_id' => absint($order_id)]
        );
    }

    public function release_ids(array $block_ids) {
        global $wpdb;

        $block_ids = array_values(array_filter(array_map('absint', $block_ids)));
        if (!$block_ids) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($block_ids), '%d'));

        return false !== $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . DB::ident(DB::table('blocks')) . " SET status = %s, user_id = NULL, order_id = NULL, reserved_until = NULL, updated_at = %s WHERE id IN ({$placeholders}) AND status = %s AND (order_id IS NULL OR order_id = 0)",
                array_merge(['available', current_time('mysql', true)], $block_ids, ['reserved'])
            )
        );
    }
}
