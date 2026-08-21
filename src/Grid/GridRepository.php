<?php
/**
 * Grid repository.
 *
 * @package MillionDollarScript\V3\Grid
 */

namespace MillionDollarScript\V3\Grid;

use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class GridRepository {

    public static function renderer_modes() {
        return ['auto', 'openlayers', 'classic'];
    }

    public static function normalize_renderer_mode($mode) {
        $mode = sanitize_key((string) $mode);
        if ('canvas' === $mode) {
            $mode = 'classic';
        }

        return in_array($mode, self::renderer_modes(), true) ? $mode : 'auto';
    }

    public function all() {
        global $wpdb;

        $rows = $wpdb->get_results('SELECT * FROM ' . DB::ident(DB::table('grids')) . ' ORDER BY id DESC', ARRAY_A);

        return array_map(static fn($row) => new Grid($row), is_array($rows) ? $rows : []);
    }

    public static function admin_statuses() {
        return ['active', 'paused', 'archived'];
    }

    public static function admin_orderby_keys() {
        return ['id', 'title', 'status', 'renderer', 'dimensions', 'updated'];
    }

    public function admin_status_counts($search = '') {
        global $wpdb;

        $args = [];
        $search_where = $this->admin_search_where($search, $args);
        $sql = 'SELECT status, COUNT(*) AS total FROM ' . DB::ident(DB::table('grids'));
        if ($search_where) {
            $sql .= ' WHERE ' . $search_where;
        }
        $sql .= ' GROUP BY status';
        if ($args) {
            $sql = $wpdb->prepare($sql, $args);
        }

        $counts = ['all' => 0];
        foreach (self::admin_statuses() as $status) {
            $counts[$status] = 0;
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        foreach (is_array($rows) ? $rows : [] as $row) {
            $status = sanitize_key((string) ($row['status'] ?? ''));
            $total = absint($row['total'] ?? 0);
            if (isset($counts[$status])) {
                $counts[$status] = $total;
                $counts['all'] += $total;
            }
        }

        return $counts;
    }

    public function admin_count($status = 'all', $search = '') {
        global $wpdb;

        $args = [];
        $where = $this->admin_where($status, $search, $args);
        $sql = 'SELECT COUNT(*) FROM ' . DB::ident(DB::table('grids'));
        if ($where) {
            $sql .= ' WHERE ' . $where;
        }
        if ($args) {
            $sql = $wpdb->prepare($sql, $args);
        }

        return absint($wpdb->get_var($sql));
    }

    public function admin_page(array $args = []) {
        global $wpdb;

        $page = max(1, absint($args['page'] ?? 1));
        $per_page = max(1, min(100, absint($args['per_page'] ?? 20)));
        $offset = ($page - 1) * $per_page;
        $status = $args['status'] ?? 'all';
        $search = $args['search'] ?? '';
        $orderby = sanitize_key((string) ($args['orderby'] ?? 'id'));
        $order = 'asc' === strtolower((string) ($args['order'] ?? '')) ? 'ASC' : 'DESC';
        $query_args = [];
        $where = $this->admin_where($status, $search, $query_args);
        $orderby_sql = $this->admin_orderby_sql($orderby);

        $query_args[] = $per_page;
        $query_args[] = $offset;

        $sql = 'SELECT * FROM ' . DB::ident(DB::table('grids'));
        if ($where) {
            $sql .= ' WHERE ' . $where;
        }
        $sql .= ' ORDER BY ' . $orderby_sql . ' ' . $order . ', id ' . $order . ' LIMIT %d OFFSET %d';

        $rows = $wpdb->get_results($wpdb->prepare($sql, $query_args), ARRAY_A);

        return array_map(static fn($row) => new Grid($row), is_array($rows) ? $rows : []);
    }

    public function active() {
        global $wpdb;

        $rows = $wpdb->get_results("SELECT * FROM " . DB::ident(DB::table('grids')) . " WHERE status = 'active' ORDER BY id ASC", ARRAY_A);

        return array_map(static fn($row) => new Grid($row), is_array($rows) ? $rows : []);
    }

    public function active_count($search = '') {
        global $wpdb;

        $where = ["status = 'active'"];
        $args = [];
        $search = trim((string) $search);

        if ('' !== $search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(title LIKE %s OR slug LIKE %s OR description LIKE %s)';
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
        }

        $sql = 'SELECT COUNT(*) FROM ' . DB::ident(DB::table('grids')) . ' WHERE ' . implode(' AND ', $where);
        if ($args) {
            $sql = $wpdb->prepare($sql, $args);
        }

        return absint($wpdb->get_var($sql));
    }

    public function active_page($page = 1, $per_page = 20, $search = '') {
        global $wpdb;

        $page = max(1, absint($page));
        $per_page = max(1, min(100, absint($per_page)));
        $offset = ($page - 1) * $per_page;
        $where = ["status = 'active'"];
        $args = [];
        $search = trim((string) $search);

        if ('' !== $search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(title LIKE %s OR slug LIKE %s OR description LIKE %s)';
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
        }

        $args[] = $per_page;
        $args[] = $offset;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . DB::ident(DB::table('grids')) . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY id ASC LIMIT %d OFFSET %d',
                $args
            ),
            ARRAY_A
        );

        return array_map(static fn($row) => new Grid($row), is_array($rows) ? $rows : []);
    }

    public function find($id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('grids')) . ' WHERE id = %d', absint($id)),
            ARRAY_A
        );

        return is_array($row) ? new Grid($row) : null;
    }

    /**
     * Return a fallback grid for embeds that intentionally omit a grid id.
     *
     * This is not a global "active grid" setting; every block, shortcode, and
     * page flow should continue to honor an explicit grid id when one is given.
     */
    public function first_active() {
        global $wpdb;

        $row = $wpdb->get_row(
            "SELECT * FROM " . DB::ident(DB::table('grids')) . " WHERE status = 'active' ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );

        return is_array($row) ? new Grid($row) : null;
    }

    public function create(array $data) {
        global $wpdb;

        $now = current_time('mysql', true);
        $title = sanitize_text_field($data['title'] ?? __('Untitled grid', 'million-dollar-script'));
        $slug = sanitize_title($data['slug'] ?? $title);
        if (!$slug) {
            $slug = 'grid-' . wp_generate_password(8, false, false);
        }

        $settings = $this->settings_payload($data);

        $insert = [
            'slug' => $this->unique_slug($slug),
            'title' => $title,
            'description' => wp_kses_post($data['description'] ?? ''),
            'width' => max(1, absint($data['width'] ?? 1000)),
            'height' => max(1, absint($data['height'] ?? 1000)),
            'block_width' => max(1, absint($data['block_width'] ?? 10)),
            'block_height' => max(1, absint($data['block_height'] ?? 10)),
            'price_per_block' => (float) ($data['price_per_block'] ?? 1),
            'currency' => Currency::code($data['currency'] ?? Currency::current_code()),
            'status' => $this->status($data['status'] ?? 'active'),
            'settings' => wp_json_encode($settings),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $wpdb->insert(DB::table('grids'), $insert);

        return false === $result ? new \WP_Error('mds3_grid_create_failed', $wpdb->last_error) : $this->find($wpdb->insert_id);
    }

    public function update($id, array $data) {
        global $wpdb;

        $grid = $this->find($id);
        if (!$grid) {
            return new \WP_Error('mds3_grid_not_found', __('Grid not found.', 'million-dollar-script'));
        }

        $allowed = ['title', 'description', 'width', 'height', 'block_width', 'block_height', 'price_per_block', 'currency', 'status'];
        $update = [];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            switch ($key) {
                case 'title':
                case 'currency':
                    $update[$key] = sanitize_text_field((string) $data[$key]);
                    break;
                case 'status':
                    $update[$key] = $this->status($data[$key]);
                    break;
                case 'description':
                    $update[$key] = wp_kses_post((string) $data[$key]);
                    break;
                case 'price_per_block':
                    $update[$key] = (float) $data[$key];
                    break;
                default:
                    $update[$key] = max(1, absint($data[$key]));
                    break;
            }
        }

        $settings_keys = [
            'settings', 'renderer_mode', 'days_expire', 'max_orders', 'max_blocks', 'min_blocks',
            'auto_publish', 'auto_approve', 'nfs_covered', 'show_public_stats', 'background_color',
            'background_image_id', 'background_image_fit', 'background_image_position',
            'background_image_repeat', 'background_image_opacity',
        ];
        if (array_intersect($settings_keys, array_keys($data))) {
            $update['settings'] = wp_json_encode($this->settings_payload($data, $grid));
        }

        if (array_key_exists('currency', $update)) {
            $update['currency'] = Currency::code($update['currency']);
        }

        $update['updated_at'] = current_time('mysql', true);

        $wpdb->update(DB::table('grids'), $update, ['id' => absint($id)]);

        return $this->find($id);
    }

    public function archive($id) {
        return $this->update($id, ['status' => 'archived']);
    }

    public function delete($id) {
        global $wpdb;

        $grid = $this->find($id);
        if (!$grid) {
            return new \WP_Error('mds3_grid_not_found', __('Grid not found.', 'million-dollar-script'));
        }

        $wpdb->delete(DB::table('grids'), ['id' => absint($id)]);

        return true;
    }

    private function unique_slug($base) {
        global $wpdb;

        $slug = $base;
        $i = 2;

        while ($wpdb->get_var($wpdb->prepare('SELECT id FROM ' . DB::ident(DB::table('grids')) . ' WHERE slug = %s', $slug))) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function admin_where($status, $search, array &$args) {
        $where = [];
        $status = sanitize_key((string) $status);

        if (in_array($status, self::admin_statuses(), true)) {
            $where[] = 'status = %s';
            $args[] = $status;
        }

        $search_where = $this->admin_search_where($search, $args);
        if ($search_where) {
            $where[] = '(' . $search_where . ')';
        }

        return implode(' AND ', $where);
    }

    private function admin_search_where($search, array &$args) {
        global $wpdb;

        $search = trim((string) $search);
        if ('' === $search) {
            return '';
        }

        $like = '%' . $wpdb->esc_like($search) . '%';
        $clauses = ['title LIKE %s', 'slug LIKE %s', 'description LIKE %s'];
        $args[] = $like;
        $args[] = $like;
        $args[] = $like;

        if (ctype_digit($search)) {
            $clauses[] = 'id = %d';
            $args[] = absint($search);
        }

        return implode(' OR ', $clauses);
    }

    private function admin_orderby_sql($orderby) {
        $orderby = sanitize_key((string) $orderby);

        switch ($orderby) {
            case 'title':
                return 'title';
            case 'status':
                return 'status';
            case 'renderer':
                return "CASE WHEN settings LIKE '%\"renderer_mode\":\"classic\"%' THEN 'classic' WHEN settings LIKE '%\"renderer_mode\":\"openlayers\"%' THEN 'openlayers' ELSE 'auto' END";
            case 'dimensions':
                return '(width * height)';
            case 'updated':
                return 'updated_at';
            case 'id':
            default:
                return 'id';
        }
    }

    private function settings_payload(array $data, Grid $grid = null) {
        $settings = $grid ? $grid->settings() : [];

        if (is_array($data['settings'] ?? null)) {
            $settings = array_merge($settings, $data['settings']);
        } elseif (is_string($data['settings'] ?? null)) {
            $decoded = json_decode((string) $data['settings'], true);
            if (is_array($decoded)) {
                $settings = array_merge($settings, $decoded);
            }
        }

        if (array_key_exists('renderer_mode', $data)) {
            $settings['renderer_mode'] = self::normalize_renderer_mode($data['renderer_mode']);
        }

        foreach (['days_expire', 'max_orders', 'max_blocks', 'min_blocks'] as $key) {
            if (array_key_exists($key, $data)) {
                $settings[$key] = absint($data[$key]);
            }
        }

        foreach (['auto_publish', 'auto_approve', 'nfs_covered', 'show_public_stats'] as $key) {
            if (array_key_exists($key, $data)) {
                $settings[$key] = 'Y' === strtoupper((string) $data[$key]) ? 'Y' : 'N';
            }
        }

        if (array_key_exists('background_color', $data)) {
            $settings['background_color'] = sanitize_hex_color((string) $data['background_color']) ?: '';
        }

        foreach (['background_image_id', 'background_image_fit', 'background_image_position', 'background_image_repeat', 'background_image_opacity'] as $key) {
            if (array_key_exists($key, $data)) {
                $settings[$key] = $data[$key];
            }
        }

        $settings = GridBackground::storage_settings($settings);

        if (empty($settings['renderer_mode'])) {
            $settings['renderer_mode'] = 'auto';
        } else {
            $settings['renderer_mode'] = self::normalize_renderer_mode($settings['renderer_mode']);
        }

        return $settings;
    }

    private function status($status) {
        $status = sanitize_key((string) $status);

        return in_array($status, ['active', 'paused', 'archived'], true) ? $status : 'active';
    }
}
