<?php
/**
 * Legacy source discovery reports.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration\Concerns;

use MillionDollarScript\V3\Pages\PageRepository;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

trait ReportsLegacySource {

    public function table_report() {
        $tables = [];
        foreach (self::CORE_TABLES as $suffix) {
            $tables[$suffix] = $this->describe_table($this->table($suffix));
        }

        $tables['page_metadata'] = $this->describe_table($this->page_metadata_table());
        $tables['page_config'] = $this->describe_table($this->page_config_table());
        $tables['page_detection_log'] = $this->describe_table($this->page_detection_log_table());

        return $tables;
    }

    public function target_report() {
        global $wpdb;

        $targets = [];
        foreach (['grids', 'blocks', 'placements', 'orders', 'packages', 'price_rules', 'order_items', 'pages', 'migration_runs', 'migration_map'] as $suffix) {
            $table = DB::table($suffix);
            $exists = DB::table_exists($table);
            $targets[$suffix] = [
                'table' => $table,
                'exists' => $exists,
                'rows' => $exists ? (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::ident($table)) : 0,
            ];
        }

        return $targets;
    }

    public function options_report() {
        global $wpdb;

        $patterns = [
            $wpdb->esc_like('_milliondollarscript_') . '%',
            $wpdb->esc_like('milliondollarscript_') . '%',
            $wpdb->esc_like('_mds_') . '%',
            $wpdb->esc_like('mds_') . '%',
        ];

        $where = implode(' OR ', array_fill(0, count($patterns), 'option_name LIKE %s'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT option_name, option_value FROM ' . DB::ident($wpdb->options) . " WHERE {$where} ORDER BY option_name ASC",
                $patterns
            ),
            ARRAY_A
        );

        $page_options = [];
        foreach (PageRepository::option_aliases() as $type => $aliases) {
            foreach ($aliases as $alias) {
                $value = get_option($alias, null);
                if (null !== $value && false !== $value && '' !== $value) {
                    $page_options[$type][$alias] = absint($value) ?: (string) $value;
                }
            }
        }

        $selected = [];
        foreach ($this->known_option_names() as $name) {
            $value = get_option($name, null);
            if (null !== $value && false !== $value && '' !== $value) {
                $selected[$name] = maybe_unserialize($value);
            }
        }

        return [
            'count' => is_array($rows) ? count($rows) : 0,
            'names' => array_map(static fn($row) => (string) $row['option_name'], is_array($rows) ? $rows : []),
            'page_options' => $page_options,
            'selected' => $selected,
        ];
    }

    public function option_values() {
        global $wpdb;

        $patterns = [
            $wpdb->esc_like('_milliondollarscript_') . '%',
            $wpdb->esc_like('milliondollarscript_') . '%',
            $wpdb->esc_like('_mds_') . '%',
            $wpdb->esc_like('mds_') . '%',
        ];

        $where = implode(' OR ', array_fill(0, count($patterns), 'option_name LIKE %s'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT option_name, option_value FROM ' . DB::ident($wpdb->options) . " WHERE {$where} ORDER BY option_name ASC",
                $patterns
            ),
            ARRAY_A
        );

        $values = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $values[(string) $row['option_name']] = maybe_unserialize($row['option_value']);
        }

        return $values;
    }

    public function source_counts() {
        global $wpdb;

        $counts = [
            'users_with_mds_orders' => 0,
            'users_with_mds_blocks' => 0,
            'attachments_in_ads' => 0,
        ];

        $orders = $this->table('orders');
        if (DB::table_exists($orders)) {
            $counts['users_with_mds_orders'] = (int) $wpdb->get_var('SELECT COUNT(DISTINCT user_id) FROM ' . DB::ident($orders) . ' WHERE user_id > 0');
        }

        $blocks = $this->table('blocks');
        if (DB::table_exists($blocks)) {
            $counts['users_with_mds_blocks'] = (int) $wpdb->get_var('SELECT COUNT(DISTINCT user_id) FROM ' . DB::ident($blocks) . ' WHERE user_id > 0');
        }

        $counts['attachments_in_ads'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(DISTINCT post_id) FROM ' . DB::ident($wpdb->postmeta) . ' WHERE meta_key IN (%s, %s, %s, %s) AND meta_value REGEXP %s',
                '_milliondollarscript_image',
                'milliondollarscript_image',
                '_mds_image',
                'mds_image',
                '^[0-9]+$'
            )
        );

        return $counts;
    }

    private function describe_table($table) {
        global $wpdb;

        $exists = DB::table_exists($table);
        $columns = [];
        if ($exists) {
            $column_rows = $wpdb->get_results('SHOW COLUMNS FROM ' . DB::ident($table), ARRAY_A);
            foreach (is_array($column_rows) ? $column_rows : [] as $row) {
                $columns[] = (string) ($row['Field'] ?? '');
            }
        }

        return [
            'table' => $table,
            'exists' => $exists,
            'rows' => $exists ? (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::ident($table)) : 0,
            'columns' => array_values(array_filter($columns)),
        ];
    }

    private function known_option_names() {
        $names = ['_milliondollarscript_product', 'milliondollarscript_product', '_milliondollarscript_db-version', 'milliondollarscript_db-version'];

        foreach (SettingsSchema::fields() as $key => $field) {
            foreach (SettingsSchema::aliases($key) as $alias) {
                $names[] = $alias;
            }
        }

        foreach (PageRepository::option_aliases() as $aliases) {
            foreach ($aliases as $alias) {
                $names[] = $alias;
            }
        }

        return array_values(array_unique($names));
    }
}
