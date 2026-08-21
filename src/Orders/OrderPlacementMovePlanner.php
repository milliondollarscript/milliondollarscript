<?php
/**
 * Validate and price a same-grid order placement move.
 *
 * @package MillionDollarScript\V3\Orders
 */

namespace MillionDollarScript\V3\Orders;

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Grid\PriceRuleRepository;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class OrderPlacementMovePlanner {

    private const MOVABLE_STATUSES = ['reserved', 'pending_payment', 'paid', 'expired'];

    public static function can_move_status($status) {
        return in_array(sanitize_key((string) $status), self::MOVABLE_STATUSES, true);
    }

    public function preview($order_id, $grid_id, $target_row, $target_col) {
        $plan = $this->plan($order_id, $grid_id, $target_row, $target_col);

        return is_wp_error($plan) ? $plan : $this->summary($plan);
    }

    public function plan($order_id, $grid_id, $target_row, $target_col, $lock = false) {
        $order_id = absint($order_id);
        $grid_id = absint($grid_id);
        $target_row = (int) $target_row;
        $target_col = (int) $target_col;

        if (!$order_id || !$grid_id || $target_row < 0 || $target_col < 0) {
            return new \WP_Error('mds3_order_move_invalid_target', __('Choose a valid target row and column.', 'million-dollar-script'));
        }

        $order = $this->order($order_id, $lock);
        if (!$order) {
            return new \WP_Error('mds3_order_move_not_found', __('Order not found.', 'million-dollar-script'));
        }
        if (!self::can_move_status($order['status'] ?? '')) {
            return new \WP_Error('mds3_order_move_status', __('Only reserved, awaiting-payment, paid, or renewable expired orders can be moved.', 'million-dollar-script'));
        }

        $grid = (new GridRepository())->find($grid_id);
        if (!$grid) {
            return new \WP_Error('mds3_order_move_grid_not_found', __('The order grid could not be found.', 'million-dollar-script'));
        }

        $source_items = $this->source_items($order_id, $grid_id, $lock);
        $expected_items = array_values(array_filter((new OrderRepository())->items($order_id), static function ($item) use ($grid_id) {
            return absint($item['grid_id'] ?? 0) === $grid_id;
        }));
        if (!$source_items || count($source_items) !== count($expected_items)) {
            return new \WP_Error('mds3_order_move_inventory_incomplete', __('This placement does not have a complete movable block mapping.', 'million-dollar-script'));
        }

        $geometry = $grid->geometry();
        $min_row = null;
        $min_col = null;
        $max_row = null;
        $max_col = null;
        $source_ids = [];

        foreach ($source_items as &$source_item) {
            if (absint($source_item['block_order_id'] ?? 0) !== $order_id || !in_array(sanitize_key((string) ($source_item['block_status'] ?? '')), ['reserved', 'sold'], true)) {
                return new \WP_Error('mds3_order_move_inventory_released', __('This order no longer owns all of its original blocks.', 'million-dollar-script'));
            }

            $coordinate = $geometry->coordinate_from_pixel(absint($source_item['block_x'] ?? 0), absint($source_item['block_y'] ?? 0));
            if (!$coordinate) {
                return new \WP_Error('mds3_order_move_source_invalid', __('The current placement contains an invalid block coordinate.', 'million-dollar-script'));
            }

            $source_item['source_row'] = absint($coordinate['row']);
            $source_item['source_col'] = absint($coordinate['col']);
            $source_ids[] = absint($source_item['block_id']);
            $min_row = null === $min_row ? $source_item['source_row'] : min($min_row, $source_item['source_row']);
            $min_col = null === $min_col ? $source_item['source_col'] : min($min_col, $source_item['source_col']);
            $max_row = null === $max_row ? $source_item['source_row'] : max($max_row, $source_item['source_row']);
            $max_col = null === $max_col ? $source_item['source_col'] : max($max_col, $source_item['source_col']);
        }
        unset($source_item);

        $row_span = max(1, $max_row - $min_row + 1);
        $col_span = max(1, $max_col - $min_col + 1);
        if (!$geometry->contains($target_row + $row_span - 1, $target_col + $col_span - 1)) {
            return new \WP_Error('mds3_order_move_out_of_bounds', __('The placement would extend beyond the grid.', 'million-dollar-script'));
        }

        $block_repo = new BlockRepository();
        $target_rects = [];
        $shape = [];
        foreach ($source_items as &$source_item) {
            $row_offset = $source_item['source_row'] - $min_row;
            $col_offset = $source_item['source_col'] - $min_col;
            $row = $target_row + $row_offset;
            $col = $target_col + $col_offset;
            $rect = $geometry->rect($row, $col);
            if (!$rect) {
                return new \WP_Error('mds3_order_move_out_of_bounds', __('The placement would extend beyond the grid.', 'million-dollar-script'));
            }
            if ($block_repo->coordinate_in_stored_unavailable_region($grid, $row, $col)) {
                return new \WP_Error('mds3_order_move_unavailable', __('The proposed placement includes blocks marked unavailable.', 'million-dollar-script'));
            }

            $source_item['target_row'] = $row;
            $source_item['target_col'] = $col;
            $source_item['target_rect'] = $rect;
            $target_rects[$row . ':' . $col] = $rect;
            $shape[] = ['row' => $row_offset, 'col' => $col_offset];
        }
        unset($source_item);

        $target_blocks = $this->target_blocks($grid_id, $target_rects, $lock);
        $source_ids = array_values(array_unique(array_filter($source_ids)));
        $target_ids = [];
        $same_position = true;

        foreach ($source_items as &$source_item) {
            $key = $source_item['target_row'] . ':' . $source_item['target_col'];
            $target_block = $target_blocks[$key] ?? null;
            if ($target_block) {
                $target_block_id = absint($target_block['id'] ?? 0);
                $target_order_id = absint($target_block['order_id'] ?? 0);
                $owned_source = $target_order_id === $order_id && in_array($target_block_id, $source_ids, true);
                $available = 'available' === sanitize_key((string) ($target_block['status'] ?? '')) && !$target_order_id;
                if (!$owned_source && !$available) {
                    return new \WP_Error('mds3_order_move_occupied', __('The proposed placement includes blocks that are already reserved or sold.', 'million-dollar-script'));
                }
                $target_ids[] = $target_block_id;
            }

            $source_item['target_block'] = $target_block;
            if ($source_item['source_row'] !== $source_item['target_row'] || $source_item['source_col'] !== $source_item['target_col']) {
                $same_position = false;
            }
        }
        unset($source_item);

        if ($same_position) {
            return new \WP_Error('mds3_order_move_same_position', __('Choose a different placement before previewing the move.', 'million-dollar-script'));
        }

        $price_rules = new PriceRuleRepository();
        $active_rules = $price_rules->active_for_grid($grid_id);
        $currency = Currency::code($order['currency'] ?? $grid->get('currency', 'USD'));
        $current_list_price = 0.0;
        $target_list_price = 0.0;

        foreach ($source_items as &$source_item) {
            $item_metadata = json_decode((string) ($source_item['item_metadata'] ?? ''), true);
            $item_metadata = is_array($item_metadata) ? $item_metadata : [];
            $current_price = (float) ($source_item['unit_price'] ?? 0);
            $target_price = $current_price;

            if ('package' !== sanitize_key((string) ($item_metadata['price_source'] ?? ''))) {
                $target_payload = is_array($source_item['target_block']) ? $source_item['target_block'] : $source_item['target_rect'];
                $pricing = $price_rules->effective_price($grid, $target_payload, $active_rules);
                if (Currency::code($pricing['currency'] ?? $currency) !== $currency) {
                    return new \WP_Error('mds3_order_move_currency', __('The target price zone uses a different currency, so this order cannot be moved there.', 'million-dollar-script'));
                }
                $target_price = (float) ($pricing['price'] ?? $current_price);
            }

            $source_item['item_metadata_array'] = $item_metadata;
            $source_item['target_list_price'] = $target_price;
            $current_list_price += $current_price;
            $target_list_price += $target_price;
        }
        unset($source_item);

        return [
            'block_count' => count($source_items),
            'col_span' => $col_span,
            'current_list_price' => round($current_list_price, 2),
            'currency' => $currency,
            'grid' => $grid,
            'grid_id' => $grid_id,
            'items' => $source_items,
            'order' => $order,
            'order_id' => $order_id,
            'row_span' => $row_span,
            'shape' => $shape,
            'source_ids' => $source_ids,
            'source_origin' => ['row' => $min_row, 'col' => $min_col],
            'target_ids' => array_values(array_unique(array_filter($target_ids))),
            'target_list_price' => round($target_list_price, 2),
            'target_origin' => ['row' => $target_row, 'col' => $target_col],
        ];
    }

    public function summary(array $plan) {
        return [
            'block_count' => absint($plan['block_count'] ?? 0),
            'col_span' => absint($plan['col_span'] ?? 0),
            'current_list_price' => (float) ($plan['current_list_price'] ?? 0),
            'currency' => Currency::code($plan['currency'] ?? ''),
            'grid_id' => absint($plan['grid_id'] ?? 0),
            'order_id' => absint($plan['order_id'] ?? 0),
            'order_status' => sanitize_key((string) ($plan['order']['status'] ?? '')),
            'order_total' => (float) ($plan['order']['total'] ?? 0),
            'price_difference' => round((float) ($plan['target_list_price'] ?? 0) - (float) ($plan['current_list_price'] ?? 0), 2),
            'preserves_order_total' => true,
            'row_span' => absint($plan['row_span'] ?? 0),
            'shape' => is_array($plan['shape'] ?? null) ? $plan['shape'] : [],
            'source_origin' => is_array($plan['source_origin'] ?? null) ? $plan['source_origin'] : [],
            'target_list_price' => (float) ($plan['target_list_price'] ?? 0),
            'target_origin' => is_array($plan['target_origin'] ?? null) ? $plan['target_origin'] : [],
        ];
    }

    private function order($order_id, $lock) {
        global $wpdb;

        $sql = $wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('orders')) . ' WHERE id = %d', $order_id);
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    private function source_items($order_id, $grid_id, $lock) {
        global $wpdb;

        $sql = $wpdb->prepare(
            'SELECT i.id item_id, i.order_id, i.grid_id, i.block_id, i.placement_id, i.item_type, i.quantity, i.unit_price, i.total, i.metadata item_metadata, i.created_at item_created_at, '
            . 'b.x block_x, b.y block_y, b.width block_width, b.height block_height, b.status block_status, b.user_id block_user_id, b.order_id block_order_id, b.price_override block_price_override, b.reserved_until block_reserved_until '
            . 'FROM ' . DB::ident(DB::table('order_items')) . ' i INNER JOIN ' . DB::ident(DB::table('blocks')) . ' b ON b.id = i.block_id '
            . 'WHERE i.order_id = %d AND i.grid_id = %d ORDER BY i.id ASC',
            $order_id,
            $grid_id
        );
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    private function target_blocks($grid_id, array $target_rects, $lock) {
        global $wpdb;

        if (!$target_rects) {
            return [];
        }

        $xs = wp_list_pluck($target_rects, 'x');
        $ys = wp_list_pluck($target_rects, 'y');
        $sql = $wpdb->prepare(
            'SELECT * FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id = %d AND x BETWEEN %d AND %d AND y BETWEEN %d AND %d',
            $grid_id,
            min($xs),
            max($xs),
            min($ys),
            max($ys)
        );
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $rows = $wpdb->get_results($sql, ARRAY_A);
        $indexed = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $indexed[absint($row['y'] ?? 0) . ':' . absint($row['x'] ?? 0)] = $row;
        }

        $geometry_map = [];
        foreach ($target_rects as $coordinate_key => $rect) {
            $geometry_map[absint($rect['y'] ?? 0) . ':' . absint($rect['x'] ?? 0)] = $coordinate_key;
        }

        $result = [];
        foreach ($indexed as $pixel_key => $row) {
            if (isset($geometry_map[$pixel_key])) {
                $result[$geometry_map[$pixel_key]] = $row;
            }
        }

        return $result;
    }
}
