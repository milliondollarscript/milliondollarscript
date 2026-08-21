<?php
/**
 * Stable commerce-source facade.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Commerce;

use MillionDollarScript\V3\Commerce\Sources as InternalSources;

if (!defined('ABSPATH')) {
    exit;
}

final class Sources {

    public static function all(): array {
        return (array) InternalSources::all();
    }

    public static function get(string $source_id): ?array {
        $source = InternalSources::get($source_id);

        return is_array($source) ? $source : null;
    }

    public static function supports(string $source_id, string $mode): bool {
        return (bool) InternalSources::supports($source_id, $mode);
    }

    /** @return mixed|\WP_Error */
    public static function invoke(string $source_id, string $operation, $resource_id, array $context = []) {
        return InternalSources::invoke($source_id, $operation, $resource_id, $context);
    }
}
