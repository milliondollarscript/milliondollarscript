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
        $this->report_undrawn_positions();

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
        $order_row = $this->block_row_order($row);
        $status = self::block_status(
            $row['status'] ?? '',
            (string) ($order_row['status'] ?? ''),
            $this->legacy_order_paid((int) ($order_row['order_id'] ?? ($row['order_id'] ?? 0)), (int) ($order_row['ad_id'] ?? 0))
        );
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
            if (!$this->migration_update(DB::table('blocks'), $payload, ['id' => $target_id], 'block', $legacy_key)) {
                return 0;
            }
        } else {
            $payload['created_at'] = current_time('mysql', true);
            $target_id = $this->migration_insert(DB::table('blocks'), $payload, 'block', $legacy_key);
        }

        if ($target_id) {
            $this->map->remember($this->source->source_prefix(), 'block', $legacy_key, 'block', $target_id, ['legacy_grid_id' => $legacy_grid_id, 'legacy_block_id' => $legacy_block_id]);
        }

        return $target_id;
    }

    /**
     * The legacy order row a block belongs to, or an empty array when it belongs to none.
     *
     * The order state decides whether a block reads as sold, reserved or free again, and
     * whether payment was recorded decides an order left at "confirmed".
     */
    private function block_row_order(array $row) {
        if ('' !== (string) ($row['__order_status'] ?? '')) {
            return [
                'order_id' => absint($row['order_id'] ?? 0),
                'status' => (string) $row['__order_status'],
                'ad_id' => absint($row['__order_ad_id'] ?? 0),
            ];
        }

        // Raw MDS2 block rows are imported without a join to the orders table,
        // so resolve the order when the block is tied to one.
        if (empty($row['order_id'])) {
            return [];
        }

        global $wpdb;
        $orders_table = $this->source->table('orders');
        if (!DB::table_exists($orders_table)) {
            return [];
        }

        $order = $wpdb->get_row($wpdb->prepare(
            'SELECT order_id, status, ad_id FROM ' . DB::ident($orders_table) . ' WHERE order_id = %d',
            absint($row['order_id'])
        ), ARRAY_A);

        return is_array($order) ? $order : [];
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
                'status' => self::block_status('', $order['status'] ?? '', $this->legacy_order_paid((int) ($order['order_id'] ?? 0), (int) ($order['ad_id'] ?? 0))),
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

        // MDS2 already stores x and y as pixel offsets, not block indexes:
        // x = (block_id % grid_width) * block_width on every write path
        // (legacy Blocks.php, Orders.php and users/make_selection.php). An existing
        // source value is therefore used verbatim - scaling it again put every
        // migrated block ten times away from its real position.
        //
        // When a legacy row carries no usable x/y the block index is the grid
        // position, and deriving pixels from it is the same arithmetic MDS2 uses -
        // which also reproduces the legitimate 0,0 case, since block 0 is the
        // top-left block.
        if (!array_key_exists('x', $row) || !array_key_exists('y', $row) || (0 === $x && 0 === $y && $legacy_block_id > 0)) {
            $x = ($legacy_block_id % max(1, $dimensions['blocks_wide'])) * $dimensions['block_width'];
            $y = intdiv($legacy_block_id, max(1, $dimensions['blocks_wide'])) * $dimensions['block_height'];
        } elseif ($this->position_was_never_drawn($x, $y, $legacy_block_id, $dimensions)) {
            $drawn = $this->drawn_position($legacy_block_id, $dimensions);
            $this->note_undrawn_position($legacy_grid_id, $row, $x, $y, $drawn['x'], $drawn['y']);
            $x = $drawn['x'];
            $y = $drawn['y'];
        }

        return [
            'x' => $x,
            'y' => $y,
            'width' => max(1, absint($row['width'] ?? $dimensions['block_width'])),
            'height' => max(1, absint($row['height'] ?? $dimensions['block_height'])),
        ];
    }

    /**
     * The position a legacy block number says, in pixels.
     *
     * MDS2 numbered its blocks by cell - block_id = column + row * columns_per_row - and
     * that arithmetic is in its own selection code (users/make_selection.php builds the
     * number from the clicked pixel) and in its grid renderer (Core/include/output_grid.php
     * walks the cells and pastes each block's art at the cell it is standing in). Neither
     * reads the row's x/y.
     */
    private function drawn_position($legacy_block_id, array $dimensions) {
        $columns = max(1, $dimensions['blocks_wide']);

        return [
            'x' => (absint($legacy_block_id) % $columns) * $dimensions['block_width'],
            'y' => intdiv(absint($legacy_block_id), $columns) * $dimensions['block_height'],
        ];
    }

    /**
     * Whether a legacy row stores a position that site's grid could not have drawn.
     *
     * On the customer copy 100 block rows of one order store x from 1180 to 1270 on a grid
     * whose canvas is 1000 wide: each one sits exactly one canvas width past the cell its own
     * number names (block 1518 is stored at 1180, 140; its number says 180, 150). Whatever
     * wrote those rows was using geometry that grid no longer has. It matters only in this:
     * their grid draws a block at the cell its number names, not at its x/y
     * (Core/include/output_grid.php walks the cells and pastes each block there), so the
     * stored position is one that site could not have shown, while the position its number
     * names is one it could - and their own site did show the ad inside the grid there.
     *
     * A stored position that stays inside the grid is never second-guessed. A site that
     * resized a grid would have every one of its rows disagree with its block numbers, and
     * those rows are what a resized grid looks like - the x/y is the only thing that can
     * still be right there.
     */
    private function position_was_never_drawn($x, $y, $legacy_block_id, array $dimensions) {
        $drawn = $this->drawn_position($legacy_block_id, $dimensions);
        if ((int) $x === (int) $drawn['x'] && (int) $y === (int) $drawn['y']) {
            return false;
        }
        $off_the_grid = (int) $x >= (int) $dimensions['width'] || (int) $y >= (int) $dimensions['height'];
        $on_the_grid = (int) $drawn['x'] < (int) $dimensions['width'] && (int) $drawn['y'] < (int) $dimensions['height'];

        return $off_the_grid && $on_the_grid;
    }

    /**
     * Collect the corrections by order so the report says what happened once, not once per
     * cell of one ad.
     */
    private function note_undrawn_position($legacy_grid_id, array $row, $stored_x, $stored_y, $drawn_x, $drawn_y) {
        $key = absint($legacy_grid_id) . ':' . (int) ($row['order_id'] ?? 0);
        if (!isset($this->undrawn_positions[$key])) {
            $this->undrawn_positions[$key] = [
                'count' => 0,
                'stored_x' => (int) $stored_x,
                'stored_y' => (int) $stored_y,
                'drawn_x' => (int) $drawn_x,
                'drawn_y' => (int) $drawn_y,
            ];
        }
        $this->undrawn_positions[$key]['count']++;
    }

    private function report_undrawn_positions() {
        if (empty($this->undrawn_positions)) {
            return;
        }
        foreach ($this->undrawn_positions as $key => $group) {
            $this->record_migration_repair(
                'block',
                $key,
                sprintf(
                    /* translators: 1: blocks moved, 2-3: the position the old records stored, 4-5: where that site drew them. */
                    _n(
                        'This ad was stored at a position (%2$s, %3$s) that the grid could not show; it was placed where that site drew it, at (%4$s, %5$s).',
                        '%1$s blocks of this ad were stored at positions off the grid (the first at %2$s, %3$s); they were placed where that site drew them, starting at (%4$s, %5$s).',
                        $group['count'],
                        'million-dollar-script'
                    ),
                    number_format_i18n($group['count']),
                    (string) $group['stored_x'],
                    (string) $group['stored_y'],
                    (string) $group['drawn_x'],
                    (string) $group['drawn_y']
                )
            );
        }
        $this->undrawn_positions = [];
    }
}
