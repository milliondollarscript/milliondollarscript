<?php
/**
 * Order repository.
 *
 * @package MillionDollarScript\V3\Orders
 */

namespace MillionDollarScript\V3\Orders;

use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class OrderRepository {

    public const STATUSES = ['reserved', 'pending_payment', 'paid', 'cancelled', 'failed', 'refunded', 'expired', 'denied', 'deleted'];

    private const ACTIVE_STATUSES = ['reserved', 'pending_payment', 'paid'];

    private const ITEM_MASK_BATCH_SIZE = 500;

    private const OVERVIEW_CACHE_KEY = 'mds3_order_overview_v1';

    private const OVERVIEW_CACHE_TTL = 60;

    public function find($id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('orders')) . ' WHERE id = %d', absint($id)),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function for_ids(array $ids) {
        global $wpdb;

        $ids = array_values(array_filter(array_map('absint', $ids)));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('orders')) . ' WHERE id IN (' . $placeholders . ') ORDER BY id ASC', $ids), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function find_by_key($order_key) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('orders')) . ' WHERE order_key = %s', sanitize_text_field((string) $order_key)),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function create(array $items, array $data = []) {
        global $wpdb;

        $now = current_time('mysql', true);
        $currency = Currency::code($data['currency'] ?? Currency::current_code());
        $subtotal = 0.0;

        foreach ($items as $item) {
            $subtotal += (float) ($item['total'] ?? $item['unit_price'] ?? 0);
        }

        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

        $order = [
            'order_key' => wp_generate_uuid4(),
            'user_id' => array_key_exists('user_id', $data) ? (absint($data['user_id']) ?: null) : (get_current_user_id() ?: null),
            'email' => sanitize_email($data['email'] ?? ''),
            'status' => sanitize_key($data['status'] ?? 'draft'),
            'currency' => $currency,
            'subtotal' => $subtotal,
            'total' => (float) ($data['total'] ?? $subtotal),
            'commerce_provider' => sanitize_key($data['commerce_provider'] ?? ''),
            'commerce_order_id' => sanitize_text_field($data['commerce_order_id'] ?? ''),
            'expires_at' => OrderLifecycleFields::expires_at($metadata),
            'metadata' => wp_json_encode($metadata),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $wpdb->insert(DB::table('orders'), $order);
        if (false === $result) {
            return new \WP_Error('mds3_order_create_failed', $wpdb->last_error);
        }

        self::invalidate_overview_cache();

        $order_id = absint($wpdb->insert_id);
        foreach ($items as $item) {
            $block_id = !empty($item['block_id']) ? absint($item['block_id']) : null;

            $wpdb->insert(DB::table('order_items'), [
                'order_id' => $order_id,
                'grid_id' => absint($item['grid_id'] ?? 0),
                'block_id' => $block_id,
                'placement_id' => !empty($item['placement_id']) ? absint($item['placement_id']) : null,
                'item_type' => sanitize_key($item['item_type'] ?? 'block'),
                'quantity' => max(1, absint($item['quantity'] ?? 1)),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'total' => (float) ($item['total'] ?? $item['unit_price'] ?? 0),
                'metadata' => wp_json_encode(is_array($item['metadata'] ?? null) ? $item['metadata'] : []),
                'created_at' => $now,
            ]);

            if ($block_id) {
                $wpdb->update(
                    DB::table('blocks'),
                    [
                        'order_id' => $order_id,
                        'status' => sanitize_key($data['block_status'] ?? 'reserved'),
                        'updated_at' => $now,
                    ],
                    ['id' => $block_id]
                );
            }
        }

        return $order_id;
    }

    public function update($id, array $data) {
        global $wpdb;

        $allowed = ['email', 'status', 'currency', 'subtotal', 'total', 'commerce_provider', 'commerce_order_id', 'metadata'];
        $update = [];
        $before = null;

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            switch ($key) {
                case 'email':
                    $update[$key] = sanitize_email((string) $data[$key]);
                    break;
                case 'status':
                case 'commerce_provider':
                    $update[$key] = sanitize_key((string) $data[$key]);
                    break;
                case 'currency':
                    $update[$key] = Currency::code($data[$key]);
                    break;
                case 'subtotal':
                case 'total':
                    $update[$key] = (float) $data[$key];
                    break;
                case 'metadata':
                    $metadata = is_array($data[$key]) ? $data[$key] : [];
                    $update[$key] = wp_json_encode($metadata);
                    $update['expires_at'] = OrderLifecycleFields::expires_at($metadata);
                    break;
                default:
                    $update[$key] = sanitize_text_field((string) $data[$key]);
                    break;
            }
        }

        if (!$update) {
            return $this->find($id);
        }

        if (array_key_exists('status', $update)) {
            $before = $this->find($id);
        }

        $update['updated_at'] = current_time('mysql', true);
        $result = $wpdb->update(DB::table('orders'), $update, ['id' => absint($id)]);

        if (false === $result) {
            return new \WP_Error('mds3_order_update_failed', $wpdb->last_error);
        }

        if (array_key_exists('status', $update) || array_key_exists('commerce_provider', $update)) {
            self::invalidate_overview_cache();
        }

        $updated = $this->find($id);
        if (
            $before &&
            $updated &&
            array_key_exists('status', $update) &&
            sanitize_key((string) ($before['status'] ?? '')) !== sanitize_key((string) ($updated['status'] ?? ''))
        ) {
            \MillionDollarScript\Core\Hooks::do(
                'million-dollar-script/order/status/changed',
                absint($id),
                sanitize_key((string) ($updated['status'] ?? '')),
                sanitize_key((string) ($before['status'] ?? '')),
                $updated,
                $before,
                $data
            );
        }

        return $updated;
    }

    public function recent($limit = 20) {
        return $this->query(['limit' => $limit]);
    }

    public function query(array $args = []) {
        global $wpdb;

        $limit = min(200, max(1, absint($args['limit'] ?? 50)));
        $offset = max(0, absint($args['offset'] ?? 0));
        $params = [];
        $where = $this->query_where($args, $params);
        $sql = 'SELECT o.*, ' . $this->order_summary_selects() . ' FROM ' . DB::ident(DB::table('orders')) . ' o' . $where . ' ORDER BY ' . $this->orderby_clause($args) . ' LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Return a forward-only keyset page for the stable ID sort.
     *
     * Numbered admin pages continue to use query() for bookmarked URL
     * compatibility. High-volume API callers can opt into this path so page
     * cost does not grow with the distance from the first row.
     *
     * @return array{items:array,has_more:bool,next_id:int}
     */
    public function cursor_page(array $args = []) {
        $limit = min(100, max(1, absint($args['limit'] ?? 20)));
        $order = 'asc' === strtolower((string) ($args['order'] ?? '')) ? 'asc' : 'desc';
        $cursor_id = absint($args['cursor_id'] ?? 0);

        unset($args['offset'], $args['cursor_id']);
        $args['orderby'] = 'id';
        $args['order'] = $order;
        $args['limit'] = $limit + 1;
        if ($cursor_id) {
            $args['after_id'] = $cursor_id;
        }

        $rows = $this->query($args);
        $has_more = count($rows) > $limit;
        if ($has_more) {
            $rows = array_slice($rows, 0, $limit);
        }
        $last = end($rows);

        return [
            'items' => $rows,
            'has_more' => $has_more,
            'next_id' => is_array($last) ? absint($last['id'] ?? 0) : 0,
        ];
    }

    public function count(array $args = []) {
        global $wpdb;

        $params = [];
        $where = $this->query_where($args, $params);
        $sql = 'SELECT COUNT(*) FROM ' . DB::ident(DB::table('orders')) . ' o' . $where;

        return absint($params ? $wpdb->get_var($wpdb->prepare($sql, $params)) : $wpdb->get_var($sql));
    }

    public function counts_by_status() {
        $summary = $this->overview_summary();

        return $summary['counts'];
    }

    public static function statuses() {
        return self::STATUSES;
    }

    public function providers() {
        $summary = $this->overview_summary();

        return $summary['providers'];
    }

    /**
     * Invalidate the bounded-staleness overview after count-relevant writes.
     */
    public static function invalidate_overview_cache(): void {
        delete_transient(self::OVERVIEW_CACHE_KEY);
    }

    /** @return array{counts:array<string,int>,providers:array<int,string>} */
    private function overview_summary(): array {
        $cached = get_transient(self::OVERVIEW_CACHE_KEY);
        if (is_array($cached) && is_array($cached['counts'] ?? null) && is_array($cached['providers'] ?? null)) {
            return $cached;
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            'SELECT status, COALESCE(NULLIF(commerce_provider, \'\'), \'standalone\') provider, COUNT(*) total FROM '
                . DB::ident(DB::table('orders')) . ' GROUP BY status, provider',
            ARRAY_A
        );
        $counts = [];
        $providers = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $status = sanitize_key((string) ($row['status'] ?? ''));
            $provider = sanitize_key((string) ($row['provider'] ?? '')) ?: 'standalone';
            $total = absint($row['total'] ?? 0);
            if ($status) {
                $counts[$status] = absint($counts[$status] ?? 0) + $total;
            }
            $providers[$provider] = $provider;
        }
        if (!$providers) {
            $providers['standalone'] = 'standalone';
        }
        ksort($providers, SORT_STRING);

        $summary = [
            'counts' => $counts,
            'providers' => array_values($providers),
        ];
        set_transient(self::OVERVIEW_CACHE_KEY, $summary, self::OVERVIEW_CACHE_TTL);

        return $summary;
    }

    public function active_count_for_grid($grid_id) {
        global $wpdb;

        $statuses = self::ACTIVE_STATUSES;
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $args = array_merge([absint($grid_id)], $statuses);

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(DISTINCT o.id) FROM ' . DB::ident(DB::table('orders')) . ' o INNER JOIN ' . DB::ident(DB::table('order_items')) . " i ON i.order_id = o.id WHERE i.grid_id = %d AND o.status IN ({$placeholders})",
                $args
            )
        );
    }

    public function active_count_for_package($package_id) {
        global $wpdb;

        $package_id = absint($package_id);
        if (!$package_id) {
            return 0;
        }

        $statuses = self::ACTIVE_STATUSES;
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $package_pattern = '%' . $wpdb->esc_like('"package_id":' . $package_id) . '%';
        $nested_package_pattern = '%' . $wpdb->esc_like('"package":{"id":' . $package_id) . '%';
        $args = array_merge($statuses, [$package_pattern, $nested_package_pattern]);

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DB::ident(DB::table('orders')) . " WHERE status IN ({$placeholders}) AND (metadata LIKE %s OR metadata LIKE %s)",
                $args
            )
        );
    }

    public function items($order_id) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('order_items')) . ' WHERE order_id = %d ORDER BY id ASC', absint($order_id)),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Return placement-mask rectangles keyed by order without an N+1 query loop.
     *
     * Order ids are intentionally chunked so a high-occupancy grid cannot build
     * an oversized placeholder list or exceed conservative shared-host limits.
     */
    public function item_masks(array $order_ids) {
        global $wpdb;

        $order_ids = array_values(array_unique(array_filter(array_map('absint', $order_ids))));
        if (!$order_ids) {
            return [];
        }

        $masks = array_fill_keys($order_ids, []);
        foreach (array_chunk($order_ids, self::ITEM_MASK_BATCH_SIZE) as $batch) {
            $placeholders = implode(',', array_fill(0, count($batch), '%d'));
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT order_id, metadata FROM ' . DB::ident(DB::table('order_items')) . " WHERE order_id IN ({$placeholders}) ORDER BY order_id ASC, id ASC",
                    $batch
                ),
                ARRAY_A
            );

            foreach (is_array($rows) ? $rows : [] as $item) {
                $order_id = absint($item['order_id'] ?? 0);
                $rect = $this->item_mask_rect($item['metadata'] ?? '');
                if ($order_id && $rect && array_key_exists($order_id, $masks)) {
                    $masks[$order_id][] = $rect;
                }
            }
        }

        return $masks;
    }

    public function item_rect($order_id) {
        $items = $this->items($order_id);
        $min_x = null;
        $min_y = null;
        $max_x = null;
        $max_y = null;
        $grid_id = 0;
        $block_id = 0;

        foreach ($items as $item) {
            $metadata = json_decode((string) ($item['metadata'] ?? ''), true);
            if (!is_array($metadata)) {
                continue;
            }

            $x = isset($metadata['x']) ? absint($metadata['x']) : null;
            $y = isset($metadata['y']) ? absint($metadata['y']) : null;
            $width = isset($metadata['width']) ? absint($metadata['width']) : 0;
            $height = isset($metadata['height']) ? absint($metadata['height']) : 0;

            if (null === $x || null === $y || !$width || !$height) {
                continue;
            }

            $grid_id = $grid_id ?: absint($item['grid_id'] ?? 0);
            $block_id = $block_id ?: absint($item['block_id'] ?? 0);
            $min_x = null === $min_x ? $x : min($min_x, $x);
            $min_y = null === $min_y ? $y : min($min_y, $y);
            $max_x = null === $max_x ? $x + $width : max($max_x, $x + $width);
            $max_y = null === $max_y ? $y + $height : max($max_y, $y + $height);
        }

        if (null === $min_x || null === $min_y || null === $max_x || null === $max_y) {
            return null;
        }

        return [
            'grid_id' => $grid_id,
            'block_id' => $block_id,
            'x' => $min_x,
            'y' => $min_y,
            'width' => max(1, $max_x - $min_x),
            'height' => max(1, $max_y - $min_y),
        ];
    }

    private function item_mask_rect($metadata) {
        $metadata = json_decode((string) $metadata, true);
        if (!is_array($metadata) || !array_key_exists('x', $metadata) || !array_key_exists('y', $metadata)) {
            return null;
        }

        $width = absint($metadata['width'] ?? 0);
        $height = absint($metadata['height'] ?? 0);
        if (!$width || !$height) {
            return null;
        }

        return [
            'x' => absint($metadata['x']),
            'y' => absint($metadata['y']),
            'width' => $width,
            'height' => $height,
        ];
    }

    private function query_where(array $args, array &$params) {
        global $wpdb;

        $where = [];
        $order_id = absint($args['order_id'] ?? 0);
        if ($order_id) {
            $where[] = 'o.id = %d';
            $params[] = $order_id;
        }

        $after_id = absint($args['after_id'] ?? 0);
        if ($after_id) {
            $ascending = 'asc' === strtolower((string) ($args['order'] ?? ''));
            $where[] = $ascending ? 'o.id > %d' : 'o.id < %d';
            $params[] = $after_id;
        }

        $user_id = absint($args['user_id'] ?? 0);
        if ($user_id) {
            $where[] = 'o.user_id = %d';
            $params[] = $user_id;
        }

        $email = sanitize_email((string) ($args['email'] ?? ''));
        if ($email) {
            $where[] = 'LOWER(o.email) = LOWER(%s)';
            $params[] = $email;
        }

        $owner_user_id = absint($args['owner_user_id'] ?? 0);
        $owner_email = sanitize_email((string) ($args['owner_email'] ?? ''));
        if ($owner_user_id || $owner_email) {
            $owner_where = [];
            if ($owner_user_id) {
                $owner_where[] = 'o.user_id = %d';
                $params[] = $owner_user_id;
            }
            if ($owner_email) {
                $owner_where[] = 'LOWER(o.email) = LOWER(%s)';
                $params[] = $owner_email;
            }
            $where[] = '(' . implode(' OR ', $owner_where) . ')';
        }

        $status = sanitize_key((string) ($args['status'] ?? ''));
        if ($status && in_array($status, self::STATUSES, true)) {
            $where[] = 'o.status = %s';
            $params[] = $status;
        }

        $statuses = is_array($args['statuses'] ?? null)
            ? array_values(array_intersect(self::STATUSES, array_map('sanitize_key', $args['statuses'])))
            : [];
        if ($statuses) {
            $where[] = $this->status_in_clause($statuses, $params);
        }

        $grid_id = absint($args['grid_id'] ?? 0);
        if ($grid_id) {
            $where[] = 'EXISTS (SELECT 1 FROM ' . DB::ident(DB::table('order_items')) . ' i_grid WHERE i_grid.order_id = o.id AND i_grid.grid_id = %d)';
            $params[] = $grid_id;
        }

        $provider = sanitize_key((string) ($args['provider'] ?? ''));
        if ($provider) {
            if ('standalone' === $provider) {
                $where[] = "(o.commerce_provider IS NULL OR o.commerce_provider = '' OR o.commerce_provider = %s)";
                $params[] = 'standalone';
            } else {
                $where[] = 'o.commerce_provider = %s';
                $params[] = $provider;
            }
        }

        $payment_state = sanitize_key((string) ($args['payment_state'] ?? ''));
        if ($payment_state) {
            $payment_statuses = [
                'paid' => ['paid'],
                'unpaid' => ['reserved', 'pending_payment'],
                'failed' => ['cancelled', 'failed', 'denied'],
                'refunded' => ['refunded'],
            ];
            if (!empty($payment_statuses[$payment_state])) {
                $where[] = $this->status_in_clause($payment_statuses[$payment_state], $params);
            }
        }

        $upload_state = sanitize_key((string) ($args['upload_state'] ?? ''));
        if ('uploaded' === $upload_state) {
            $where[] = 'EXISTS (SELECT 1 FROM ' . DB::ident(DB::table('placements')) . ' p_upload WHERE p_upload.order_id = o.id AND p_upload.attachment_id > 0)';
        } elseif ('missing' === $upload_state) {
            $where[] = 'NOT EXISTS (SELECT 1 FROM ' . DB::ident(DB::table('placements')) . ' p_upload WHERE p_upload.order_id = o.id AND p_upload.attachment_id > 0)';
        }

        $placement_state = sanitize_key((string) ($args['placement_state'] ?? ''));
        if (in_array($placement_state, ['pending', 'active', 'cancelled', 'archived'], true)) {
            $where[] = 'EXISTS (SELECT 1 FROM ' . DB::ident(DB::table('placements')) . ' p_state WHERE p_state.order_id = o.id AND p_state.status = %s)';
            $params[] = $placement_state;
        } elseif ('none' === $placement_state) {
            $where[] = 'NOT EXISTS (SELECT 1 FROM ' . DB::ident(DB::table('placements')) . ' p_state WHERE p_state.order_id = o.id)';
        } elseif ('not_active' === $placement_state) {
            $where[] = 'NOT EXISTS (SELECT 1 FROM ' . DB::ident(DB::table('placements')) . " p_state WHERE p_state.order_id = o.id AND p_state.status = 'active')";
        }

        if (!empty($args['action_required'])) {
            $where[] = '('
                . $this->status_in_clause(['reserved', 'pending_payment'], $params)
                . ' OR NOT EXISTS (SELECT 1 FROM ' . DB::ident(DB::table('placements')) . ' p_action WHERE p_action.order_id = o.id AND p_action.attachment_id > 0)'
                . ' OR (o.status = %s AND (o.metadata LIKE %s OR o.metadata LIKE %s) AND EXISTS (SELECT 1 FROM ' . DB::ident(DB::table('blocks')) . " b_action WHERE b_action.order_id = o.id AND b_action.status IN ('reserved', 'sold')))"
                . ')';
            $params[] = 'expired';
            $params[] = '%' . $wpdb->esc_like('duration_days') . '%';
            $params[] = '%' . $wpdb->esc_like('"package"') . '%';
        }

        $expiration_state = sanitize_key((string) ($args['expiration_state'] ?? ''));
        if ($expiration_state) {
            $this->expiration_where($expiration_state, $where, $params);
        }

        $date_from = $this->date_arg($args['date_from'] ?? '');
        if ($date_from) {
            $where[] = 'o.created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }

        $date_to = $this->date_arg($args['date_to'] ?? '');
        if ($date_to) {
            $where[] = 'o.created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        $search = trim(sanitize_text_field((string) ($args['search'] ?? '')));
        if ('' !== $search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $clauses = [
                'o.email LIKE %s',
                'o.commerce_provider LIKE %s',
                'o.commerce_order_id LIKE %s',
                'EXISTS (SELECT 1 FROM ' . DB::ident(DB::table('order_items')) . ' i_search INNER JOIN ' . DB::ident(DB::table('grids')) . ' g_search ON g_search.id = i_search.grid_id WHERE i_search.order_id = o.id AND (g_search.title LIKE %s OR g_search.slug LIKE %s OR CAST(g_search.id AS CHAR) LIKE %s))',
            ];
            $search_params = [$like, $like, $like, $like, $like, $like];

            if (ctype_digit($search)) {
                array_unshift($clauses, 'o.id = %d');
                array_unshift($search_params, absint($search));
            }

            $where[] = '(' . implode(' OR ', $clauses) . ')';
            $params = array_merge($params, $search_params);
        }

        return $where ? ' WHERE ' . implode(' AND ', $where) : '';
    }

    private function order_summary_selects() {
        $items = DB::ident(DB::table('order_items'));
        $grids = DB::ident(DB::table('grids'));
        $placements = DB::ident(DB::table('placements'));
        $blocks = DB::ident(DB::table('blocks'));
        $expires_at = OrderLifecycleFields::expiration_sql('o.metadata', 'o.expires_at');

        if (DB::uses_sqlite()) {
            return "(SELECT GROUP_CONCAT(grid_id, ',') FROM (SELECT DISTINCT g_ids.id grid_id FROM {$items} i_ids INNER JOIN {$grids} g_ids ON g_ids.id = i_ids.grid_id WHERE i_ids.order_id = o.id ORDER BY g_ids.id ASC)) grid_ids, "
                . "(SELECT GROUP_CONCAT(grid_title, ', ') FROM (SELECT DISTINCT g_titles.title grid_title FROM {$items} i_titles INNER JOIN {$grids} g_titles ON g_titles.id = i_titles.grid_id WHERE i_titles.order_id = o.id ORDER BY g_titles.title ASC)) grid_titles, "
                . "(SELECT COUNT(DISTINCT i_count.grid_id) FROM {$items} i_count WHERE i_count.order_id = o.id) grid_count, "
                . "(SELECT MIN(g_sort.title) FROM {$items} i_sort INNER JOIN {$grids} g_sort ON g_sort.id = i_sort.grid_id WHERE i_sort.order_id = o.id) grid_sort, "
                . "(SELECT COUNT(*) FROM {$placements} p_count WHERE p_count.order_id = o.id) placement_count, "
                . "(SELECT COUNT(*) FROM {$placements} p_upload WHERE p_upload.order_id = o.id AND p_upload.attachment_id > 0) upload_count, "
                . "(SELECT COUNT(*) FROM {$placements} p_active WHERE p_active.order_id = o.id AND p_active.status = 'active') active_placement_count, "
                . "(SELECT GROUP_CONCAT(placement_status, ',') FROM (SELECT DISTINCT p_status.status placement_status FROM {$placements} p_status WHERE p_status.order_id = o.id ORDER BY p_status.status ASC)) placement_statuses, "
                . "(SELECT COUNT(*) FROM {$blocks} b_retained WHERE b_retained.order_id = o.id AND b_retained.status IN ('reserved', 'sold')) retained_inventory_count, "
                . "{$expires_at} term_expires_at";
        }

        return "(SELECT GROUP_CONCAT(DISTINCT g_ids.id ORDER BY g_ids.id SEPARATOR ',') FROM {$items} i_ids INNER JOIN {$grids} g_ids ON g_ids.id = i_ids.grid_id WHERE i_ids.order_id = o.id) grid_ids, "
            . "(SELECT GROUP_CONCAT(DISTINCT g_titles.title ORDER BY g_titles.title SEPARATOR ', ') FROM {$items} i_titles INNER JOIN {$grids} g_titles ON g_titles.id = i_titles.grid_id WHERE i_titles.order_id = o.id) grid_titles, "
            . "(SELECT COUNT(DISTINCT i_count.grid_id) FROM {$items} i_count WHERE i_count.order_id = o.id) grid_count, "
            . "(SELECT MIN(g_sort.title) FROM {$items} i_sort INNER JOIN {$grids} g_sort ON g_sort.id = i_sort.grid_id WHERE i_sort.order_id = o.id) grid_sort, "
            . "(SELECT COUNT(*) FROM {$placements} p_count WHERE p_count.order_id = o.id) placement_count, "
            . "(SELECT COUNT(*) FROM {$placements} p_upload WHERE p_upload.order_id = o.id AND p_upload.attachment_id > 0) upload_count, "
            . "(SELECT COUNT(*) FROM {$placements} p_active WHERE p_active.order_id = o.id AND p_active.status = 'active') active_placement_count, "
            . "(SELECT GROUP_CONCAT(DISTINCT p_status.status ORDER BY p_status.status SEPARATOR ',') FROM {$placements} p_status WHERE p_status.order_id = o.id) placement_statuses, "
            . "(SELECT COUNT(*) FROM {$blocks} b_retained WHERE b_retained.order_id = o.id AND b_retained.status IN ('reserved', 'sold')) retained_inventory_count, "
            . "{$expires_at} term_expires_at";
    }

    private function orderby_clause(array $args) {
        $orderby = sanitize_key((string) ($args['orderby'] ?? 'id'));
        $order = 'asc' === strtolower((string) ($args['order'] ?? '')) ? 'ASC' : 'DESC';
        $map = [
            'id' => 'o.id',
            'date' => 'o.created_at',
            'status' => 'o.status',
            'customer' => 'o.email',
            'total' => 'o.total',
            'provider' => "COALESCE(NULLIF(o.commerce_provider, ''), 'standalone')",
            'grid' => 'grid_sort',
            'placement' => 'active_placement_count',
            'term' => 'term_expires_at',
        ];
        $field = $map[$orderby] ?? $map['id'];

        return $field . ' ' . $order . ', o.id DESC';
    }

    private function status_in_clause(array $statuses, array &$params) {
        $statuses = array_values(array_filter(array_map('sanitize_key', $statuses)));
        if (!$statuses) {
            return '1 = 1';
        }

        $params = array_merge($params, $statuses);

        return 'o.status IN (' . implode(',', array_fill(0, count($statuses), '%s')) . ')';
    }

    private function expiration_where($state, array &$where, array &$params) {
        $now = gmdate('Y-m-d H:i:s');
        $expires_at = OrderLifecycleFields::expiration_sql('o.metadata', 'o.expires_at');

        switch ($state) {
            case 'has_term':
                $where[] = $expires_at . ' IS NOT NULL';
                break;
            case 'no_term':
                $where[] = $expires_at . ' IS NULL';
                break;
            case 'active_term':
                $where[] = 'o.status = %s';
                $params[] = 'paid';
                $where[] = $expires_at . ' > %s';
                $params[] = $now;
                break;
            case 'expiring_soon':
                $where[] = 'o.status = %s';
                $params[] = 'paid';
                $where[] = $expires_at . ' > %s';
                $params[] = $now;
                $where[] = $expires_at . ' <= %s';
                $params[] = gmdate('Y-m-d H:i:s', time() + (30 * DAY_IN_SECONDS));
                break;
            case 'expired_term':
                $where[] = '((' . $expires_at . ' IS NOT NULL AND ' . $expires_at . ' <= %s) OR o.status = %s)';
                $params[] = $now;
                $params[] = 'expired';
                break;
            case 'renewable':
                $where[] = 'o.status = %s';
                $params[] = 'expired';
                $where[] = '(o.metadata LIKE %s OR o.metadata LIKE %s)';
                $params[] = '%' . $wpdb->esc_like('duration_days') . '%';
                $params[] = '%' . $wpdb->esc_like('"package"') . '%';
                $where[] = 'EXISTS (SELECT 1 FROM ' . DB::ident(DB::table('blocks')) . " b_renew WHERE b_renew.order_id = o.id AND b_renew.status IN ('reserved', 'sold'))";
                break;
            case 'renewal_started':
                $where[] = 'o.metadata LIKE %s';
                $params[] = '%' . $wpdb->esc_like('renewal_started_at') . '%';
                break;
        }
    }

    private function date_arg($date) {
        $date = trim(sanitize_text_field((string) $date));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
    }
}
