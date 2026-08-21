<?php
/**
 * Database helpers.
 *
 * @package MillionDollarScript\V3\Support
 */

namespace MillionDollarScript\V3\Support;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class DB {

    /**
     * Return a plugin table name.
     *
     * @param string $name Logical table suffix.
     * @return string
     */
    public static function table($name) {
        global $wpdb;

        return $wpdb->prefix . 'mds3_' . preg_replace('/[^a-z0-9_]/', '', strtolower((string) $name));
    }

    /**
     * Quote a SQL identifier.
     *
     * @param string $identifier Identifier.
     * @return string
     */
    public static function ident($identifier) {
        return '`' . str_replace('`', '``', (string) $identifier) . '`';
    }

    /**
     * Return a JSON scalar expression for the active WordPress database layer.
     *
     * SQLite's JSON_EXTRACT() already returns an unquoted scalar, while MySQL
     * requires JSON_UNQUOTE() around the extracted JSON value.
     *
     * @param string $document SQL expression containing the JSON document.
     * @param string $path SQL expression containing the JSON path.
     * @return string
     */
    public static function json_scalar($document, $path) {
        $extracted = 'JSON_EXTRACT(' . (string) $document . ', ' . (string) $path . ')';

        return self::uses_sqlite() ? $extracted : 'JSON_UNQUOTE(' . $extracted . ')';
    }

    /**
     * Check whether WordPress is using its SQLite database implementation.
     *
     * @return bool
     */
    public static function uses_sqlite() {
        global $wpdb;

        if (defined('DB_ENGINE') && 'sqlite' === strtolower((string) DB_ENGINE)) {
            return true;
        }

        return is_object($wpdb) && false !== stripos(get_class($wpdb), 'sqlite');
    }

    /**
     * Check whether a table exists.
     *
     * @param string $table Table name.
     * @return bool
     */
    public static function table_exists($table) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /**
     * Check whether a table column exists.
     *
     * @param string $table Table name.
     * @param string $column Column name.
     * @return bool
     */
    public static function column_exists($table, $column) {
        global $wpdb;

        if (!self::table_exists($table)) {
            return false;
        }

        $row = $wpdb->get_var(
            $wpdb->prepare('SHOW COLUMNS FROM ' . self::ident($table) . ' LIKE %s', (string) $column)
        );

        return (string) $row === (string) $column;
    }
}
