<?php
/**
 * MDS2 sparse block import step.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration\Concerns;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

trait ImportsLegacyBlocks {

    private function import_blocks() {
        global $wpdb;

        $table = $this->source->table('blocks');
        if (!DB::table_exists($table)) {
            return 0;
        }

        $count = 0;
        foreach ($this->legacy_rows('blocks', ['banner_id', 'block_id']) as $row) {
            $target_id = $this->import_block_row($row);
            if ($target_id) {
                $count++;
            }
        }

        return $count;
    }

    private function import_block_row(array $row) {
        global $wpdb;

        $legacy_grid_id = absint($row['banner_id'] ?? 0) ?: 1;
        $legacy_block_id = absint($row['block_id'] ?? 0);
        $target_grid_id = $this->target_grid_id($legacy_grid_id);
        if (!$target_grid_id) {
            $this->warnings[] = 'Skipped block ' . $legacy_grid_id . ':' . $legacy_block_id . ' without a mapped grid.';
            $this->record_migration_skip('block', $legacy_grid_id . ':' . $legacy_block_id, __('No mapped target grid exists.', 'million-dollar-script'));
            return 0;
        }

        if (!$this->should_import_block($row)) {
            return 0;
        }

        $rect = $this->block_rect($legacy_grid_id, $legacy_block_id, $row);
        $legacy_key = $legacy_grid_id . ':' . $legacy_block_id;
        $order_id = !empty($row['order_id'])
            ? $this->map->target_id($this->source->source_prefix(), 'order', absint($row['order_id']), 'order')
            : 0;
        $status = self::block_status($row['status'] ?? '', $this->block_row_order_status($row));
        $metadata = $this->legacy_block_metadata($row);

        $payload = [
            'grid_id' => $target_grid_id,
            'x' => $rect['x'],
            'y' => $rect['y'],
            'width' => $rect['width'],
            'height' => $rect['height'],
            'status' => $status,
            'user_id' => !empty($row['user_id']) ? absint($row['user_id']) : null,
            'order_id' => $order_id ?: null,
            'price_override' => isset($row['price']) && '' !== (string) $row['price'] ? (float) $row['price'] : null,
            'reserved_until' => null,
            'metadata' => wp_json_encode($metadata),
            'updated_at' => current_time('mysql', true),
        ];

        $target_id = $this->map->target_id($this->source->source_prefix(), 'block', $legacy_key, 'block');
        if ($target_id && !$this->target_exists('blocks', $target_id)) {
            $target_id = 0;
        }
        if (!$target_id) {
            $target_id = absint($wpdb->get_var(
                $wpdb->prepare(
                    'SELECT id FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id = %d AND x = %d AND y = %d LIMIT 1',
                    $target_grid_id,
                    $rect['x'],
                    $rect['y']
                )
            ));
            if ($target_id) {
                $this->record_migration_repair('block', $legacy_key, __('Recovered an existing block whose migration-map row was missing.', 'million-dollar-script'));
            }
        }

        if ($target_id && $this->target_exists('blocks', $target_id)) {
            $wpdb->update(DB::table('blocks'), $payload, ['id' => $target_id]);
        } else {
            $payload['created_at'] = current_time('mysql', true);
            $wpdb->insert(DB::table('blocks'), $payload);
            $target_id = absint($wpdb->insert_id);
        }

        if ($target_id) {
            $this->map->remember($this->source->source_prefix(), 'block', $legacy_key, 'block', $target_id, ['legacy_grid_id' => $legacy_grid_id, 'legacy_block_id' => $legacy_block_id]);
        }

        return $target_id;
    }

    private function block_row_order_status(array $row) {
        $order_status = (string) ($row['__order_status'] ?? '');
        if ('' !== $order_status) {
            return $order_status;
        }

        // Raw MDS2 block rows are imported without a join to the orders table,
        // so resolve the order status when the block is tied to an order.
        if (empty($row['order_id'])) {
            return '';
        }

        global $wpdb;
        $orders_table = $this->source->table('orders');
        if (!DB::table_exists($orders_table)) {
            return '';
        }

        $order_status = $wpdb->get_var($wpdb->prepare(
            'SELECT status FROM ' . DB::ident($orders_table) . ' WHERE order_id = %d',
            absint($row['order_id'])
        ));

        return (string) $order_status;
    }

    private function ensure_order_block(array $order, $legacy_block_id) {
        $legacy_grid_id = absint($order['banner_id'] ?? 0) ?: 1;
        $legacy_key = $legacy_grid_id . ':' . absint($legacy_block_id);
        $target_id = $this->map->target_id($this->source->source_prefix(), 'block', $legacy_key, 'block');
        if ($target_id) {
            return $target_id;
        }

        $source_row = $this->source_block($legacy_grid_id, $legacy_block_id);
        if (!$source_row) {
            $block_count = max(1, absint($order['__reconciled_block_count'] ?? count($this->parse_blocks_csv($order['blocks'] ?? ''))));
            $source_row = [
                '__synthetic' => true,
                '__order_status' => $order['status'] ?? '',
                'block_id' => absint($legacy_block_id),
                'banner_id' => $legacy_grid_id,
                'user_id' => absint($order['user_id'] ?? 0),
                'status' => self::block_status('', $order['status'] ?? ''),
                'order_id' => absint($order['order_id'] ?? 0),
                'price' => (float) ($order['price'] ?? 0) / $block_count,
                'url' => '',
                'alt_text' => '',
                'image_data' => '',
            ];
            $this->record_migration_repair('block', $legacy_key, __('Reconstructed a missing source block from the legacy order inventory.', 'million-dollar-script'));
        }

        return $this->import_block_row($source_row);
    }

    private function should_import_block(array $row) {
        $status = sanitize_key($row['status'] ?? '');
        if ($status && 'free' !== $status) {
            return true;
        }

        if (!empty($row['order_id']) || !empty($row['ad_id'])) {
            return true;
        }

        if (isset($row['price']) && '' !== (string) $row['price'] && null !== $row['price']) {
            return true;
        }

        foreach (['image_data', 'file_name', 'url', 'alt_text'] as $key) {
            if (!empty($row[$key])) {
                return true;
            }
        }

        return !empty($row['__synthetic']);
    }

    private function block_rect($legacy_grid_id, $legacy_block_id, array $row) {
        $grid = $this->legacy_grid($legacy_grid_id);
        $dimensions = $grid ? self::banner_pixel_dimensions($grid) : [
            'block_width' => 10,
            'block_height' => 10,
            'blocks_wide' => 100,
            'blocks_high' => 100,
        ];

        $x = isset($row['x']) ? absint($row['x']) : 0;
        $y = isset($row['y']) ? absint($row['y']) : 0;

        if ((0 === $x && 0 === $y && $legacy_block_id > 0) || !array_key_exists('x', $row) || !array_key_exists('y', $row)) {
            $x = ($legacy_block_id % max(1, $dimensions['blocks_wide'])) * $dimensions['block_width'];
            $y = intdiv($legacy_block_id, max(1, $dimensions['blocks_wide'])) * $dimensions['block_height'];
        } else {
            // Legacy MDS2 x/y are block units; native MDS3 blocks store pixels.
            $x *= $dimensions['block_width'];
            $y *= $dimensions['block_height'];
        }

        return [
            'x' => $x,
            'y' => $y,
            'width' => max(1, absint($row['width'] ?? $dimensions['block_width'])),
            'height' => max(1, absint($row['height'] ?? $dimensions['block_height'])),
        ];
    }
}
