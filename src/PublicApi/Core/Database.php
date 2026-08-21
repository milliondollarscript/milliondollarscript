<?php
/**
 * Stable read-oriented database helpers for extension-owned reports.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Core;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

final class Database {

    public static function table(string $name): string {
        return DB::table($name);
    }

    public static function ident(string $identifier): string {
        return DB::ident($identifier);
    }

    public static function table_exists(string $table): bool {
        return DB::table_exists($table);
    }

    public static function column_exists(string $table, string $column): bool {
        return DB::column_exists($table, $column);
    }
}
