<?php
/**
 * Media placement repository.
 *
 * @package MillionDollarScript\V3\Media
 */

namespace MillionDollarScript\V3\Media;

use MillionDollarScript\V3\Grid\PopupText;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class PlacementRepository {

    /**
     * Return a bounded public advertiser page across one or every active grid.
     *
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function public_advertiser_page(array $args = []) {
        global $wpdb;

        $grid_id = absint($args['grid_id'] ?? 0);
        $page = max(1, absint($args['page'] ?? 1));
        $per_page = max(1, min(100, absint($args['per_page'] ?? 24)));
        $search = trim(substr(sanitize_text_field((string) ($args['search'] ?? '')), 0, 100));
        $where = ["p.status = 'active'", "g.status = 'active'"];
        $params = [];

        if ($grid_id) {
            $where[] = 'p.grid_id = %d';
            $params[] = $grid_id;
        }

        if ('' !== $search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $search_where = [
                'p.alt_text LIKE %s',
                'p.link_url LIKE %s',
                'p.popup_text LIKE %s',
                'g.title LIKE %s',
                'g.slug LIKE %s',
            ];
            array_push($params, $like, $like, $like, $like, $like);

            $extension_ids = \MillionDollarScript\Core\Hooks::apply(
                'million-dollar-script/advertiser/list/search/placement/ids',
                [],
                $search,
                ['grid_id' => $grid_id]
            );
            $extension_ids = array_slice(array_values(array_unique(array_filter(array_map('absint', is_array($extension_ids) ? $extension_ids : [])))), 0, 1000);
            if ($extension_ids) {
                $search_where[] = 'p.id IN (' . implode(',', array_fill(0, count($extension_ids), '%d')) . ')';
                $params = array_merge($params, $extension_ids);
            }

            $where[] = '(' . implode(' OR ', $search_where) . ')';
        }

        $from = ' FROM ' . DB::ident(DB::table('placements')) . ' p'
            . ' INNER JOIN ' . DB::ident(DB::table('grids')) . ' g ON g.id = p.grid_id';
        $where_sql = ' WHERE ' . implode(' AND ', $where);
        $count_sql = 'SELECT COUNT(*)' . $from . $where_sql;
        $total = $params
            ? absint($wpdb->get_var($wpdb->prepare($count_sql, $params)))
            : absint($wpdb->get_var($count_sql));
        $total_pages = max(1, (int) ceil($total / $per_page));
        $page = min($page, $total_pages);
        $offset = ($page - 1) * $per_page;

        $sql = 'SELECT p.id, p.grid_id, p.attachment_id, p.link_url, p.alt_text, p.popup_text, p.sort_order, '
            . 'g.title grid_title, g.slug grid_slug'
            . $from . $where_sql . ' ORDER BY p.sort_order ASC, p.id ASC LIMIT %d OFFSET %d';
        $items = $wpdb->get_results($wpdb->prepare($sql, array_merge($params, [$per_page, $offset])), ARRAY_A);

        return [
            'items' => is_array($items) ? $items : [],
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
        ];
    }

    /**
     * Return bounded placement records for trusted scheduling integrations.
     *
     * @return array{items:array<int,array<string,mixed>>,total:int,limit:int,offset:int}
     */
    public function query_for_scheduling(array $args = []) {
        global $wpdb;

        $limit = min(100, max(1, absint($args['limit'] ?? 20)));
        $offset = max(0, absint($args['offset'] ?? 0));
        $search = sanitize_text_field((string) ($args['search'] ?? ''));
        $grid_id = absint($args['grid_id'] ?? 0);
        $status = sanitize_key((string) ($args['status'] ?? ''));
        $where = ['1=1'];
        $params = [];

        if ($grid_id) {
            $where[] = 'p.grid_id = %d';
            $params[] = $grid_id;
        }
        if (in_array($status, ['pending', 'active', 'cancelled', 'archived'], true)) {
            $where[] = 'p.status = %s';
            $params[] = $status;
        }
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(p.alt_text LIKE %s OR g.title LIKE %s OR CAST(p.id AS CHAR) LIKE %s OR CAST(p.order_id AS CHAR) LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }

        $from = ' FROM ' . DB::ident(DB::table('placements')) . ' p'
            . ' INNER JOIN ' . DB::ident(DB::table('grids')) . ' g ON g.id = p.grid_id'
            . ' LEFT JOIN ' . DB::ident(DB::table('orders')) . ' o ON o.id = p.order_id';
        $where_sql = ' WHERE ' . implode(' AND ', $where);
        $count_sql = 'SELECT COUNT(*)' . $from . $where_sql;
        $total = $params
            ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params))
            : (int) $wpdb->get_var($count_sql);

        $sql = 'SELECT p.*, g.title grid_title, g.width grid_width, g.height grid_height, '
            . 'g.currency grid_currency, g.status grid_status, o.status order_status, '
            . 'o.currency order_currency, o.user_id order_user_id, o.email order_email'
            . $from . $where_sql . ' ORDER BY p.updated_at DESC, p.id DESC LIMIT %d OFFSET %d';
        $query_params = array_merge($params, [$limit, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($sql, $query_params), ARRAY_A);

        return [
            'items' => is_array($items) ? $items : [],
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /** @return array<string,mixed>|null */
    public function find_for_scheduling($id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT p.*, g.title grid_title, g.width grid_width, g.height grid_height, '
                . 'g.currency grid_currency, g.status grid_status, o.status order_status, '
                . 'o.currency order_currency, o.user_id order_user_id, o.email order_email, '
                . 'b.status block_status, b.order_id block_order_id '
                . 'FROM ' . DB::ident(DB::table('placements')) . ' p '
                . 'INNER JOIN ' . DB::ident(DB::table('grids')) . ' g ON g.id = p.grid_id '
                . 'LEFT JOIN ' . DB::ident(DB::table('orders')) . ' o ON o.id = p.order_id '
                . 'LEFT JOIN ' . DB::ident(DB::table('blocks')) . ' b ON b.id = p.block_id '
                . 'WHERE p.id = %d LIMIT 1',
                absint($id)
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Atomically update one scheduler-controlled visibility state.
     *
     * @return array|\WP_Error|null
     */
    public function transition_for_scheduling($id, $status, array $expected_statuses = []) {
        global $wpdb;

        $id = absint($id);
        $status = $this->status($status);
        $expected_statuses = array_values(array_intersect(
            ['pending', 'active', 'cancelled', 'archived'],
            array_map('sanitize_key', $expected_statuses)
        ));
        if (!$id || !$expected_statuses) {
            return new \WP_Error('million_dollar_script_schedule_transition_invalid', __('A placement and expected state are required.', 'million-dollar-script'));
        }

        $placeholders = implode(',', array_fill(0, count($expected_statuses), '%s'));
        $updated = $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . DB::ident(DB::table('placements'))
                . " SET status = %s, updated_at = %s WHERE id = %d AND status IN ({$placeholders})",
                array_merge([$status, current_time('mysql', true), $id], $expected_statuses)
            )
        );
        if (false === $updated) {
            return new \WP_Error('million_dollar_script_schedule_transition_failed', __('The placement visibility could not be updated.', 'million-dollar-script'));
        }
        if (0 === $updated) {
            return new \WP_Error('million_dollar_script_schedule_state_changed', __('The placement changed before the scheduled action could run.', 'million-dollar-script'));
        }

        return $this->find_for_scheduling($id);
    }

    public function for_grid($grid_id, array $statuses = []) {
        global $wpdb;

        $sql = 'SELECT * FROM ' . DB::ident(DB::table('placements')) . ' WHERE grid_id = %d';
        $args = [absint($grid_id)];

        if ($statuses) {
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $sql .= " AND status IN ({$placeholders})";
            foreach ($statuses as $status) {
                $args[] = sanitize_key($status);
            }
        }

        $sql .= ' ORDER BY sort_order ASC, id ASC';

        $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function find($id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('placements')) . ' WHERE id = %d', absint($id)),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function for_order($order_id) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id = %d ORDER BY id ASC', absint($order_id)),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return array<int,array<string,mixed>> */
    public function for_orders(array $order_ids) {
        global $wpdb;

        $ids = array_values(array_filter(array_map('absint', $order_ids)));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = 'SELECT * FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id IN (' . $placeholders . ') ORDER BY id ASC';
        $rows = $wpdb->get_results($wpdb->prepare($sql, $ids), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function create(array $data) {
        global $wpdb;

        $now = current_time('mysql', true);
        $insert = [
            'grid_id' => absint($data['grid_id'] ?? 0),
            'block_id' => !empty($data['block_id']) ? absint($data['block_id']) : null,
            'order_id' => !empty($data['order_id']) ? absint($data['order_id']) : null,
            'user_id' => !empty($data['user_id']) ? absint($data['user_id']) : null,
            'attachment_id' => absint($data['attachment_id'] ?? 0),
            'x' => max(0, absint($data['x'] ?? 0)),
            'y' => max(0, absint($data['y'] ?? 0)),
            'width' => max(1, absint($data['width'] ?? 1)),
            'height' => max(1, absint($data['height'] ?? 1)),
            'fit_mode' => $this->fit_mode($data['fit_mode'] ?? 'cover'),
            'link_url' => esc_url_raw($data['link_url'] ?? ''),
            'alt_text' => sanitize_text_field($data['alt_text'] ?? ''),
            'popup_text' => PopupText::sanitize($data['popup_text'] ?? ''),
            'status' => $this->status($data['status'] ?? 'pending'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (!$insert['grid_id'] || !$insert['attachment_id']) {
            return new \WP_Error('mds3_placement_invalid', __('Grid and attachment are required.', 'million-dollar-script'));
        }

        $result = $wpdb->insert(DB::table('placements'), $insert);

        if (false !== $result) {
            $placement_id = absint($wpdb->insert_id);
            (new AdvertiserPageManager())->synchronize($placement_id);

            return $placement_id;
        }

        return new \WP_Error('mds3_placement_create_failed', $wpdb->last_error);
    }

    public function update($id, array $data) {
        global $wpdb;

        $allowed = ['attachment_id', 'fit_mode', 'link_url', 'alt_text', 'popup_text', 'status', 'sort_order'];
        $update = [];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            switch ($key) {
                case 'attachment_id':
                    $update[$key] = absint($data[$key]);
                    break;
                case 'link_url':
                    $update[$key] = esc_url_raw($data[$key]);
                    break;
                case 'alt_text':
                    $update[$key] = sanitize_text_field($data[$key]);
                    break;
                case 'popup_text':
                    $update[$key] = PopupText::sanitize($data[$key]);
                    break;
                case 'sort_order':
                    $update[$key] = (int) $data[$key];
                    break;
                case 'fit_mode':
                    $update[$key] = $this->fit_mode($data[$key]);
                    break;
                case 'status':
                    $update[$key] = $this->status($data[$key]);
                    break;
                default:
                    $update[$key] = sanitize_key($data[$key]);
                    break;
            }
        }

        if (!$update) {
            return $this->find($id);
        }

        $update['updated_at'] = current_time('mysql', true);
        $result = $wpdb->update(DB::table('placements'), $update, ['id' => absint($id)]);

        if (false !== $result) {
            (new AdvertiserPageManager())->synchronize(absint($id));
        }

        return false === $result ? new \WP_Error('mds3_placement_update_failed', $wpdb->last_error) : $this->find($id);
    }

    public function update_status_by_order($order_id, $status) {
        global $wpdb;

        $status = sanitize_key($status);
        if (!$status) {
            return false;
        }

        $updated = $wpdb->update(
            DB::table('placements'),
            [
                'status' => $this->status($status),
                'updated_at' => current_time('mysql', true),
            ],
            ['order_id' => absint($order_id)]
        );
        if (false !== $updated) {
            \MillionDollarScript\Core\Hooks::do('million-dollar-script/placements/order/saved', absint($order_id));
        }

        return false !== $updated;
    }

    private function fit_mode($fit_mode) {
        $fit_mode = sanitize_key((string) $fit_mode);

        return in_array($fit_mode, ['cover', 'contain'], true) ? $fit_mode : 'cover';
    }

    private function status($status) {
        $status = sanitize_key((string) $status);

        return in_array($status, ['pending', 'active', 'cancelled', 'archived'], true) ? $status : 'pending';
    }
}
