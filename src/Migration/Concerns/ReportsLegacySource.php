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
use MillionDollarScript\V3\Migration\LegacyValue;

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
        $capabilities = $this->database_capability_report();

        $targets = [];
        foreach (['grids', 'blocks', 'placements', 'orders', 'packages', 'price_rules', 'order_items', 'pages', 'migration_runs', 'migration_map'] as $suffix) {
            $table = DB::table($suffix);
            $exists = DB::table_exists($table);
            $capability = $capabilities['tables'][$table] ?? ['charset' => '', 'collation' => '', 'stores_4_byte_characters' => true];
            $targets[$suffix] = [
                'table' => $table,
                'exists' => $exists,
                'rows' => $exists ? (int) $this->count_rows($table) : 0,
                'charset' => $capability['charset'],
                'stores_4_byte_characters' => $capability['stores_4_byte_characters'],
            ];
        }

        return $targets;
    }

    /**
     * What the destination can actually hold.
     *
     * A WordPress install may still run its tables in utf8mb3 (the old "utf8"), and a
     * host may connect in a character set that cannot carry every code point. In both
     * cases the database quietly removes what it cannot store: the customer sees text
     * with characters missing, and nothing in a migration log explains it. This names
     * the character set per table before anything is written, so the notice can say
     * what to change instead of pointing at a symptom.
     */
    public function database_capability_report(array $tables = []) {
        global $wpdb;

        $names = [];
        if ([] === $tables) {
            foreach (['grids', 'blocks', 'placements', 'orders', 'packages', 'price_rules', 'order_items', 'migration_map'] as $suffix) {
                $names[] = DB::table($suffix);
            }
        } else {
            foreach ($tables as $table) {
                $names[] = (string) $table;
            }
        }

        $connection = [];
        // Read the variables as scalars. SHOW VARIABLES returns the name in its first
        // column, which get_var would hand back instead of the value.
        foreach (['character_set_client', 'character_set_connection'] as $variable) {
            $connection[$variable] = strtolower(trim((string) $wpdb->get_var('SELECT @@SESSION.' . $variable)));
        }
        foreach (['character_set_database', 'character_set_server'] as $variable) {
            $connection[$variable] = strtolower(trim((string) $wpdb->get_var('SELECT @@GLOBAL.' . $variable)));
        }

        $table_capabilities = [];
        foreach ($names as $table) {
            $collation = (string) $wpdb->get_var(
                $wpdb->prepare('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s LIMIT 1', $table)
            );
            $charset = false !== strpos($collation, '_') ? substr($collation, 0, strpos($collation, '_')) : '';

            // A table can default to utf8mb4 and still hold a column created in the old
            // utf8, which is exactly what an older WordPress site looks like. The column
            // is what decides whether a character survives, so it is what we check.
            $narrow_columns = [];
            foreach ((array) $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT COLUMN_NAME, CHARACTER_SET_NAME FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s
                        AND CHARACTER_SET_NAME IS NOT NULL AND CHARACTER_SET_NAME NOT LIKE %s
                        ORDER BY ORDINAL_POSITION ASC',
                    $table,
                    'utf8mb4%'
                ),
                ARRAY_A
            ) as $column) {
                if (is_array($column) && '' !== (string) ($column['COLUMN_NAME'] ?? '')) {
                    $narrow_columns[(string) $column['COLUMN_NAME']] = strtolower((string) ($column['CHARACTER_SET_NAME'] ?? ''));
                }
            }

            $table_capabilities[$table] = [
                'charset' => $charset,
                'collation' => $collation,
                'columns_without_4_byte_support' => $narrow_columns,
                'stores_4_byte_characters' => ('' === $charset || 0 === strpos($charset, 'utf8mb4')) && [] === $narrow_columns,
            ];
        }

        $unsupported = [];
        foreach ($table_capabilities as $table => $capability) {
            if (empty($capability['stores_4_byte_characters'])) {
                $unsupported[] = $table;
            }
        }

        $connection_charset = (string) ($connection['character_set_connection'] ?? '');

        return [
            'wordpress_configured_charset' => defined('DB_CHARSET') ? (string) DB_CHARSET : '',
            'connection' => $connection,
            'connection_stores_4_byte_characters' => '' === $connection_charset || 0 === strpos($connection_charset, 'utf8mb4'),
            'tables' => $table_capabilities,
            'tables_without_4_byte_support' => $unsupported,
            'stores_4_byte_characters' => [] === $unsupported && ('' === $connection_charset || 0 === strpos($connection_charset, 'utf8mb4')),
        ];
    }

    private function count_rows($table) {
        global $wpdb;

        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::ident($table));
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
                $selected[$name] = LegacyValue::read($value);
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
            $values[(string) $row['option_name']] = LegacyValue::read($row['option_value']);
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
