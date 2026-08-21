<?php
/**
 * Persist validated same-grid order placement moves.
 *
 * @package MillionDollarScript\V3\Orders
 */

namespace MillionDollarScript\V3\Orders;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class OrderPlacementMover {

    private OrderPlacementMovePlanner $planner;

    public function __construct(?OrderPlacementMovePlanner $planner = null) {
        $this->planner = $planner ?: new OrderPlacementMovePlanner();
    }

    public function preview($order_id, $grid_id, $target_row, $target_col) {
        return $this->planner->preview($order_id, $grid_id, $target_row, $target_col);
    }

    public function move($order_id, $grid_id, $target_row, $target_col, array $context = []) {
        global $wpdb;

        if (false === $wpdb->query('START TRANSACTION')) {
            return $this->database_error('mds3_order_move_transaction_failed');
        }

        $plan = $this->planner->plan($order_id, $grid_id, $target_row, $target_col, true);
        if (is_wp_error($plan)) {
            $wpdb->query('ROLLBACK');
            return $plan;
        }

        $materialized = $this->materialize_missing_targets($plan);
        if (is_wp_error($materialized)) {
            $wpdb->query('ROLLBACK');
            return $materialized;
        }

        // Rebuild under the same transaction so concurrent claims are checked
        // after all sparse target blocks exist and are locked.
        $plan = $this->planner->plan($order_id, $grid_id, $target_row, $target_col, true);
        if (is_wp_error($plan)) {
            $wpdb->query('ROLLBACK');
            return $plan;
        }

        foreach (['claim_target_blocks', 'update_order_items', 'update_placements', 'release_old_blocks'] as $operation) {
            $result = $this->{$operation}($plan);
            if (is_wp_error($result)) {
                $wpdb->query('ROLLBACK');
                return $result;
            }
        }

        $event_result = $this->record_move_event($plan, $context);
        if (is_wp_error($event_result)) {
            $wpdb->query('ROLLBACK');
            return $event_result;
        }

        if (false === $wpdb->query('COMMIT')) {
            $wpdb->query('ROLLBACK');
            return $this->database_error('mds3_order_move_commit_failed');
        }

        $summary = $this->planner->summary($plan);
        $summary['moved'] = true;
        $summary['moved_at'] = gmdate('Y-m-d H:i:s');

        \MillionDollarScript\Core\Hooks::do('million-dollar-script/order/placement/moved', absint($order_id), absint($grid_id), $summary, $context);

        return $summary;
    }

    private function materialize_missing_targets(array $plan) {
        global $wpdb;

        $missing = [];
        foreach ($plan['items'] as $item) {
            if (!empty($item['target_block'])) {
                continue;
            }
            $rect = $item['target_rect'];
            $missing[$rect['x'] . ':' . $rect['y']] = $rect;
        }

        if (!$missing) {
            return true;
        }

        $now = current_time('mysql', true);
        foreach (array_chunk(array_values($missing), 250) as $chunk) {
            $values = [];
            $args = [];
            foreach ($chunk as $rect) {
                $values[] = '(%d, %d, %d, %d, %d, %s, %s, %s)';
                array_push(
                    $args,
                    absint($plan['grid_id']),
                    absint($rect['x']),
                    absint($rect['y']),
                    absint($rect['width']),
                    absint($rect['height']),
                    'available',
                    $now,
                    $now
                );
            }

            $sql = 'INSERT IGNORE INTO ' . DB::ident(DB::table('blocks'))
                . ' (grid_id, x, y, width, height, status, created_at, updated_at) VALUES '
                . implode(', ', $values);
            if (false === $wpdb->query($wpdb->prepare($sql, $args))) {
                return $this->database_error('mds3_order_move_materialize_failed');
            }
        }

        return true;
    }

    private function claim_target_blocks(array $plan) {
        global $wpdb;

        $groups = [];
        foreach ($plan['items'] as $item) {
            $target_block_id = absint($item['target_block']['id'] ?? 0);
            if (!$target_block_id) {
                return $this->database_error('mds3_order_move_target_missing');
            }

            $values = [
                'status' => sanitize_key((string) ($item['block_status'] ?? 'reserved')),
                'user_id' => absint($item['block_user_id'] ?? 0),
                'reserved_until' => sanitize_text_field((string) ($item['block_reserved_until'] ?? '')),
            ];
            $key = md5(wp_json_encode($values));
            if (!isset($groups[$key])) {
                $groups[$key] = ['values' => $values, 'ids' => []];
            }
            $groups[$key]['ids'][] = $target_block_id;
        }

        foreach ($groups as $group) {
            $ids = array_values(array_unique(array_filter(array_map('absint', $group['ids']))));
            foreach (array_chunk($ids, 500) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
                $values = $group['values'];
                $user_sql = $values['user_id'] ? '%d' : 'NULL';
                $reserved_sql = $values['reserved_until'] ? '%s' : 'NULL';
                $args = [$values['status']];
                if ($values['user_id']) {
                    $args[] = $values['user_id'];
                }
                if ($values['reserved_until']) {
                    $args[] = $values['reserved_until'];
                }
                $args[] = absint($plan['order_id']);
                $args[] = current_time('mysql', true);
                $args = array_merge($args, $chunk, [absint($plan['order_id'])]);
                $sql = 'UPDATE ' . DB::ident(DB::table('blocks'))
                    . " SET status = %s, user_id = {$user_sql}, reserved_until = {$reserved_sql}, order_id = %d, updated_at = %s WHERE id IN ({$placeholders})"
                    . " AND (order_id = %d OR ((order_id IS NULL OR order_id = 0) AND status = 'available'))";
                $updated = $wpdb->query($wpdb->prepare($sql, $args));
                if (false === $updated) {
                    return new \WP_Error('mds3_order_move_target_changed', __('The proposed blocks changed before the move could be saved. Preview the placement again.', 'million-dollar-script'));
                }
            }
        }

        $target_ids = array_values(array_unique(array_filter(array_map(static function ($item) {
            return absint($item['target_block']['id'] ?? 0);
        }, $plan['items']))));
        $placeholders = implode(',', array_fill(0, count($target_ids), '%d'));
        $claimed = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . DB::ident(DB::table('blocks')) . " WHERE id IN ({$placeholders}) AND order_id = %d AND status IN ('reserved', 'sold')",
            array_merge($target_ids, [absint($plan['order_id'])])
        ));
        if ($claimed !== count($target_ids)) {
            return new \WP_Error('mds3_order_move_target_changed', __('The proposed blocks changed before the move could be saved. Preview the placement again.', 'million-dollar-script'));
        }

        return true;
    }

    private function update_order_items(array $plan) {
        global $wpdb;

        foreach (array_chunk($plan['items'], 100) as $chunk) {
            $block_cases = [];
            $block_args = [];
            $metadata_cases = [];
            $metadata_args = [];
            $ids = [];

            foreach ($chunk as $item) {
                $item_id = absint($item['item_id'] ?? 0);
                $target_block_id = absint($item['target_block']['id'] ?? 0);
                $rect = $item['target_rect'];
                $metadata = is_array($item['item_metadata_array'] ?? null) ? $item['item_metadata_array'] : [];
                $metadata['x'] = absint($rect['x']);
                $metadata['y'] = absint($rect['y']);
                $metadata['width'] = absint($rect['width']);
                $metadata['height'] = absint($rect['height']);

                $block_cases[] = 'WHEN %d THEN %d';
                array_push($block_args, $item_id, $target_block_id);
                $metadata_cases[] = 'WHEN %d THEN %s';
                array_push($metadata_args, $item_id, wp_json_encode($metadata));
                $ids[] = $item_id;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $sql = 'UPDATE ' . DB::ident(DB::table('order_items'))
                . ' SET block_id = CASE id ' . implode(' ', $block_cases) . ' ELSE block_id END,'
                . ' metadata = CASE id ' . implode(' ', $metadata_cases) . ' ELSE metadata END'
                . " WHERE order_id = %d AND id IN ({$placeholders})";
            $args = array_merge($block_args, $metadata_args, [absint($plan['order_id'])], $ids);
            $updated = $wpdb->query($wpdb->prepare($sql, $args));
            if (false === $updated || (int) $updated !== count($ids)) {
                return $this->database_error('mds3_order_move_items_failed');
            }
        }

        return true;
    }

    private function update_placements(array $plan) {
        global $wpdb;

        $placements = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id = %d AND grid_id = %d FOR UPDATE',
                absint($plan['order_id']),
                absint($plan['grid_id'])
            ),
            ARRAY_A
        );
        $block_map = [];
        foreach ($plan['items'] as $item) {
            $block_map[absint($item['block_id'] ?? 0)] = absint($item['target_block']['id'] ?? 0);
        }

        $grid = $plan['grid'];
        $block_width = max(1, absint($grid->get('block_width', 1)));
        $block_height = max(1, absint($grid->get('block_height', 1)));
        $delta_x = ((int) $plan['target_origin']['col'] - (int) $plan['source_origin']['col']) * $block_width;
        $delta_y = ((int) $plan['target_origin']['row'] - (int) $plan['source_origin']['row']) * $block_height;

        foreach (is_array($placements) ? $placements : [] as $placement) {
            $payload = [
                'x' => max(0, (int) ($placement['x'] ?? 0) + $delta_x),
                'y' => max(0, (int) ($placement['y'] ?? 0) + $delta_y),
                'updated_at' => current_time('mysql', true),
            ];
            $old_block_id = absint($placement['block_id'] ?? 0);
            if ($old_block_id && isset($block_map[$old_block_id])) {
                $payload['block_id'] = $block_map[$old_block_id];
            }

            if (false === $wpdb->update(DB::table('placements'), $payload, ['id' => absint($placement['id'] ?? 0)])) {
                return $this->database_error('mds3_order_move_placements_failed');
            }
        }

        return true;
    }

    private function release_old_blocks(array $plan) {
        global $wpdb;

        $target_ids = array_values(array_unique(array_filter(array_map(static function ($item) {
            return absint($item['target_block']['id'] ?? 0);
        }, $plan['items']))));
        $release_ids = array_values(array_diff($plan['source_ids'], $target_ids));
        foreach (array_chunk($release_ids, 500) as $chunk) {
            if (!$chunk) {
                continue;
            }
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
            $args = array_merge(['available', current_time('mysql', true)], $chunk, [absint($plan['order_id'])]);
            $sql = 'UPDATE ' . DB::ident(DB::table('blocks'))
                . " SET status = %s, user_id = NULL, order_id = NULL, reserved_until = NULL, updated_at = %s WHERE id IN ({$placeholders}) AND order_id = %d";
            if (false === $wpdb->query($wpdb->prepare($sql, $args))) {
                return $this->database_error('mds3_order_move_release_failed');
            }
        }

        return true;
    }

    private function record_move_event(array $plan, array $context) {
        $order = $plan['order'];
        $metadata = json_decode((string) ($order['metadata'] ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];
        $events = is_array($metadata['placement_events'] ?? null) ? $metadata['placement_events'] : [];
        $events[] = [
            'action' => 'moved',
            'block_count' => absint($plan['block_count'] ?? 0),
            'created_at' => gmdate('Y-m-d H:i:s'),
            'current_list_price' => (float) ($plan['current_list_price'] ?? 0),
            'currency' => sanitize_key((string) ($plan['currency'] ?? '')),
            'from' => $plan['source_origin'],
            'grid_id' => absint($plan['grid_id']),
            'source' => sanitize_key((string) ($context['source'] ?? 'admin')),
            'target_list_price' => (float) ($plan['target_list_price'] ?? 0),
            'to' => $plan['target_origin'],
            'user_id' => absint($context['user_id'] ?? get_current_user_id()),
        ];
        $metadata['placement_events'] = array_slice($events, -50);

        $updated = (new OrderRepository())->update($plan['order_id'], ['metadata' => $metadata]);

        return is_wp_error($updated) ? $updated : true;
    }

    private function database_error($code) {
        return new \WP_Error($code, __('The placement move could not be saved. No order changes were applied.', 'million-dollar-script'));
    }
}
