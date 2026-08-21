<?php
/**
 * Database schema management for installer lifecycle.
 *
 * @package MillionDollarScript\V3\Setup
 */

namespace MillionDollarScript\V3\Setup;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class InstallerSchema {

    public const VERSION = '3.0.0-20260812.1';

    private const TABLES = [
        'grids',
        'blocks',
        'placements',
        'orders',
        'packages',
        'price_rules',
        'order_items',
        'pages',
        'render_jobs',
        'migration_runs',
        'migration_map',
        'api_keys',
        'api_audit_logs',
    ];

    /**
     * Create or update runtime tables.
     *
     * @return void
     */
    public static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE " . DB::table('grids') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slug varchar(191) NOT NULL,
            title varchar(255) NOT NULL,
            description text NULL,
            width int unsigned NOT NULL DEFAULT 1000,
            height int unsigned NOT NULL DEFAULT 1000,
            block_width int unsigned NOT NULL DEFAULT 10,
            block_height int unsigned NOT NULL DEFAULT 10,
            price_per_block decimal(12,2) NOT NULL DEFAULT 1.00,
            currency char(3) NOT NULL DEFAULT 'USD',
            status varchar(20) NOT NULL DEFAULT 'active',
            settings longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status)
        ) {$charset};");

        dbDelta("CREATE TABLE " . DB::table('blocks') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            grid_id bigint(20) unsigned NOT NULL,
            x int unsigned NOT NULL,
            y int unsigned NOT NULL,
            width int unsigned NOT NULL,
            height int unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'available',
            user_id bigint(20) unsigned NULL,
            order_id bigint(20) unsigned NULL,
            price_override decimal(12,2) NULL,
            reserved_until datetime NULL,
            metadata longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY grid_position (grid_id, x, y),
            KEY grid_status (grid_id, status),
            KEY order_id (order_id),
            KEY user_id (user_id)
        ) {$charset};");

        dbDelta("CREATE TABLE " . DB::table('placements') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            grid_id bigint(20) unsigned NOT NULL,
            block_id bigint(20) unsigned NULL,
            order_id bigint(20) unsigned NULL,
            user_id bigint(20) unsigned NULL,
            attachment_id bigint(20) unsigned NOT NULL,
            x int unsigned NOT NULL,
            y int unsigned NOT NULL,
            width int unsigned NOT NULL,
            height int unsigned NOT NULL,
            crop_x decimal(10,4) NULL,
            crop_y decimal(10,4) NULL,
            crop_width decimal(10,4) NULL,
            crop_height decimal(10,4) NULL,
            focal_x decimal(10,4) NULL,
            focal_y decimal(10,4) NULL,
            fit_mode varchar(20) NOT NULL DEFAULT 'cover',
            link_url varchar(500) NULL,
            alt_text varchar(500) NULL,
            popup_text longtext NULL,
            public_post_id bigint(20) unsigned NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            sort_order int NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY grid_id (grid_id),
            KEY block_id (block_id),
            KEY order_id (order_id),
            KEY user_id (user_id),
            KEY attachment_id (attachment_id),
            UNIQUE KEY public_post_id (public_post_id),
            KEY status (status),
            KEY public_status_sort (status, sort_order, id),
            KEY public_grid_status_sort (grid_id, status, sort_order, id)
        ) {$charset};");

        dbDelta("CREATE TABLE " . DB::table('orders') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_key varchar(64) NOT NULL,
            user_id bigint(20) unsigned NULL,
            email varchar(191) NULL,
            status varchar(30) NOT NULL DEFAULT 'draft',
            currency char(3) NOT NULL DEFAULT 'USD',
            subtotal decimal(12,2) NOT NULL DEFAULT 0.00,
            total decimal(12,2) NOT NULL DEFAULT 0.00,
            commerce_provider varchar(50) NULL,
            commerce_order_id varchar(191) NULL,
            expires_at datetime NULL,
            metadata longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY order_key (order_key),
            KEY user_id (user_id),
            KEY status (status),
            KEY status_expires (status, expires_at, id),
            KEY commerce (commerce_provider, commerce_order_id)
        ) {$charset};");

        dbDelta("CREATE TABLE " . DB::table('packages') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            grid_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            description varchar(500) NULL,
            duration_days int unsigned NOT NULL DEFAULT 0,
            price decimal(12,2) NOT NULL DEFAULT 0.00,
            currency char(3) NOT NULL DEFAULT 'USD',
            max_orders int unsigned NOT NULL DEFAULT 0,
            is_default tinyint(1) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            metadata longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY grid_id (grid_id),
            KEY status (status),
            KEY is_default (is_default)
        ) {$charset};");

        dbDelta("CREATE TABLE " . DB::table('price_rules') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            grid_id bigint(20) unsigned NOT NULL,
            row_from int unsigned NULL,
            row_to int unsigned NULL,
            col_from int unsigned NULL,
            col_to int unsigned NULL,
            block_id_from int unsigned NULL,
            block_id_to int unsigned NULL,
            price decimal(12,2) NOT NULL DEFAULT 0.00,
            currency char(3) NOT NULL DEFAULT 'USD',
            color varchar(50) NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            metadata longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY grid_id (grid_id),
            KEY status (status)
        ) {$charset};");

        dbDelta("CREATE TABLE " . DB::table('order_items') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            grid_id bigint(20) unsigned NOT NULL,
            block_id bigint(20) unsigned NULL,
            placement_id bigint(20) unsigned NULL,
            item_type varchar(30) NOT NULL DEFAULT 'block',
            quantity int unsigned NOT NULL DEFAULT 1,
            unit_price decimal(12,2) NOT NULL DEFAULT 0.00,
            total decimal(12,2) NOT NULL DEFAULT 0.00,
            metadata longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY grid_id (grid_id),
            KEY block_id (block_id),
            KEY placement_id (placement_id)
        ) {$charset};");

        dbDelta("CREATE TABLE " . DB::table('pages') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            page_type varchar(50) NOT NULL,
            grid_id bigint(20) unsigned NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            source varchar(50) NULL,
            legacy_post_id bigint(20) unsigned NULL,
            legacy_metadata longtext NULL,
            configuration longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY page_type (page_type),
            KEY grid_id (grid_id),
            KEY status (status)
        ) {$charset};");

        dbDelta("CREATE TABLE " . DB::table('render_jobs') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            grid_id bigint(20) unsigned NOT NULL,
            provider varchar(50) NOT NULL DEFAULT 'local',
            remote_job_id varchar(191) NULL,
            remote_tileset_id varchar(191) NULL,
            status varchar(30) NOT NULL DEFAULT 'pending',
            estimate longtext NULL,
            result longtext NULL,
            error_message text NULL,
            stale tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY grid_id (grid_id),
            KEY provider (provider),
            KEY status (status),
            KEY stale (stale)
        ) {$charset};");

        dbDelta("CREATE TABLE " . DB::table('migration_runs') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_prefix varchar(191) NOT NULL,
            mode varchar(30) NOT NULL DEFAULT 'dry_run',
            status varchar(30) NOT NULL DEFAULT 'pending',
            totals longtext NULL,
            report longtext NULL,
            started_at datetime NULL,
            completed_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY source_prefix (source_prefix),
            KEY mode (mode),
            KEY status (status)
        ) {$charset};");

        dbDelta("CREATE TABLE " . DB::table('migration_map') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_prefix varchar(191) NOT NULL,
            entity_type varchar(50) NOT NULL,
            legacy_id varchar(191) NOT NULL,
            mds3_entity_type varchar(50) NOT NULL,
            mds3_id bigint(20) unsigned NOT NULL,
            metadata longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY legacy_entity (source_prefix, entity_type, legacy_id, mds3_entity_type),
            KEY mds3_entity (mds3_entity_type, mds3_id)
        ) {$charset};");

        dbDelta("CREATE TABLE " . DB::table('api_keys') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            key_prefix varchar(32) NOT NULL,
            key_hash char(64) NOT NULL,
            name varchar(255) NOT NULL,
            scopes longtext NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            rate_limit_per_hour int unsigned NOT NULL DEFAULT 120,
            last_used_at datetime NULL,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            revoked_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY key_hash (key_hash),
            KEY key_prefix (key_prefix),
            KEY status (status),
            KEY created_by (created_by)
        ) {$charset};");

        dbDelta("CREATE TABLE " . DB::table('api_audit_logs') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            key_id bigint(20) unsigned NOT NULL DEFAULT 0,
            auth_type varchar(32) NOT NULL DEFAULT 'api_key',
            endpoint_id varchar(191) NULL,
            actor_ref varchar(64) NULL,
            route varchar(191) NULL,
            method varchar(20) NULL,
            scope varchar(191) NULL,
            decision varchar(20) NOT NULL,
            reason_code varchar(64) NULL,
            ip_hash varchar(64) NULL,
            user_agent_hash varchar(64) NULL,
            message varchar(500) NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY key_id (key_id),
            KEY auth_type (auth_type),
            KEY endpoint_id (endpoint_id),
            KEY decision (decision),
            KEY created_at (created_at)
        ) {$charset};");

        update_option('mds3_db_version', self::VERSION, false);
    }

    /**
     * Drop runtime tables when uninstall data removal is explicitly enabled.
     *
     * @return void
     */
    public static function drop_tables() {
        global $wpdb;

        foreach (array_reverse(self::TABLES) as $table) {
            $wpdb->query('DROP TABLE IF EXISTS ' . DB::ident(DB::table($table)));
        }
    }

    /**
     * Check every table used by the fresh runtime.
     *
     * @return bool
     */
    public static function required_tables_exist() {
        foreach (self::TABLES as $table) {
            if (!DB::table_exists(DB::table($table))) {
                return false;
            }
        }

        foreach (['popup_text', 'public_post_id'] as $column) {
            if (!DB::column_exists(DB::table('placements'), $column)) {
                return false;
            }
        }

        foreach (['auth_type', 'endpoint_id', 'actor_ref', 'reason_code'] as $column) {
            if (!DB::column_exists(DB::table('api_audit_logs'), $column)) {
                return false;
            }
        }

        if (!DB::column_exists(DB::table('orders'), 'expires_at')) {
            return false;
        }

        return true;
    }
}
