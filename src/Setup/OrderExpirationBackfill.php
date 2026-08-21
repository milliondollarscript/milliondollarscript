<?php
/**
 * Bounded backfill for normalized order expiration timestamps.
 *
 * @package MillionDollarScript\V3\Setup
 */

namespace MillionDollarScript\V3\Setup;

use MillionDollarScript\V3\Orders\OrderLifecycleFields;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use the WordPress database API.

final class OrderExpirationBackfill {

    public const HOOK = 'million-dollar-script/order/expiration/backfill';

    private const COMPLETE_OPTION = 'mds3_order_expiration_backfill_version';

    private const CURSOR_OPTION = 'mds3_order_expiration_backfill_cursor';

    private const LOCK_TRANSIENT = 'mds3_order_expiration_backfill_lock';

    private const BATCH_SIZE = 1000;

    /**
     * Advance an existing-install backfill without making one request scan the table.
     */
    public static function maybe_schedule(): void {
        if (self::complete() || wp_next_scheduled(self::HOOK)) {
            return;
        }

        $table = DB::table('orders');
        if (!DB::table_exists($table) || !DB::column_exists($table, 'expires_at')) {
            return;
        }

        wp_schedule_single_event(time() + 10, self::HOOK);
    }

    /**
     * Advance one retry-safe batch from WordPress cron.
     */
    public static function run_scheduled(): void {
        if (self::complete()) {
            return;
        }

        $table = DB::table('orders');
        if (!DB::table_exists($table) || !DB::column_exists($table, 'expires_at')) {
            return;
        }

        if (get_transient(self::LOCK_TRANSIENT)) {
            self::maybe_schedule();
            return;
        }

        set_transient(self::LOCK_TRANSIENT, '1', MINUTE_IN_SECONDS);
        try {
            self::run_batch($table);
        } finally {
            delete_transient(self::LOCK_TRANSIENT);
        }

        self::maybe_schedule();
    }

    public static function unschedule(): void {
        wp_clear_scheduled_hook(self::HOOK);
    }

    private static function complete(): bool {
        return InstallerSchema::VERSION === get_option(self::COMPLETE_OPTION);
    }

    /**
     * Backfill the next stable primary-key window.
     */
    private static function run_batch($table): void {
        global $wpdb;

        $cursor = absint(get_option(self::CURSOR_OPTION, 0));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, metadata FROM ' . DB::ident($table) . ' WHERE id > %d ORDER BY id ASC LIMIT %d',
                $cursor,
                self::BATCH_SIZE
            ),
            ARRAY_A
        );

        if (!is_array($rows) || !$rows) {
            update_option(self::COMPLETE_OPTION, InstallerSchema::VERSION, false);
            delete_option(self::CURSOR_OPTION);
            return;
        }

        foreach ($rows as $row) {
            $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
            $expires_at = OrderLifecycleFields::expires_at(is_array($metadata) ? $metadata : []);
            if ($expires_at) {
                $wpdb->update($table, ['expires_at' => $expires_at], ['id' => absint($row['id'] ?? 0)], ['%s'], ['%d']);
            }
        }

        $last = end($rows);
        update_option(self::CURSOR_OPTION, absint($last['id'] ?? $cursor), false);
        if (count($rows) < self::BATCH_SIZE) {
            update_option(self::COMPLETE_OPTION, InstallerSchema::VERSION, false);
            delete_option(self::CURSOR_OPTION);
        }
    }
}
