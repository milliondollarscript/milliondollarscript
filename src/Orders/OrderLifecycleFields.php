<?php
/**
 * Normalized order lifecycle fields derived from metadata.
 *
 * @package MillionDollarScript\V3\Orders
 */

namespace MillionDollarScript\V3\Orders;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderLifecycleFields {

    private const BACKFILL_OPTION = 'mds3_order_expiration_backfill_version';

    /**
     * Return a valid UTC MySQL expiration timestamp or null.
     *
     * @param array<string,mixed> $metadata Order metadata.
     */
    public static function expires_at(array $metadata): ?string {
        $value = trim(sanitize_text_field((string) ($metadata['expires_at'] ?? '')));
        if ('' === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || (is_array($errors) && (!empty($errors['warning_count']) || !empty($errors['error_count'])))) {
            return null;
        }

        return $date->format('Y-m-d H:i:s') === $value ? $value : null;
    }

    /**
     * Whether every pre-column order has been examined by the backfill.
     */
    public static function normalized_expiration_ready(): bool {
        if (!function_exists('get_option')) {
            return true;
        }

        $database_version = (string) get_option('mds3_db_version', '');

        return '' !== $database_version && $database_version === (string) get_option(self::BACKFILL_OPTION, '');
    }

    /**
     * SQL expression that remains correct while an existing-install backfill runs.
     *
     * Callers provide trusted, internally constructed column references.
     */
    public static function expiration_sql($metadata_column = 'metadata', $expires_column = 'expires_at'): string {
        if (self::normalized_expiration_ready()) {
            return (string) $expires_column;
        }

        $metadata_column = (string) $metadata_column;
        $expires_column = (string) $expires_column;
        $legacy = DB::json_scalar($metadata_column, "'$.expires_at'");

        return 'COALESCE(' . $expires_column . ', CASE WHEN JSON_VALID(' . $metadata_column . ') THEN ' . $legacy . ' ELSE NULL END)';
    }
}
