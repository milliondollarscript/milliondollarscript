<?php
/**
 * Stable hook dispatch with explicit Million Dollar Script 2 aliases.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class Hooks {
    private const LEGACY_ALIASES = [
        'million-dollar-script/loaded' => ['mds_initialized', 'mds_loaded', 'mds-loaded'],
        'million-dollar-script/register/extensions' => ['mds_register_extensions'],
        'million-dollar-script/grid/has/remote/tiles' => ['mds_grid_has_remote_tiles'],
        'million-dollar-script/grid/remote/tile/metadata' => ['mds_grid_remote_tile_metadata'],
        'million-dollar-script/grid/remote/tile/request' => ['mds_grid_remote_tile_request'],
    ];

    public static function apply(string $hook, $value, ...$args) {
        return self::apply_aliases($hook, self::legacy_aliases($hook), $value, ...$args);
    }

    public static function apply_compat(string $hook, array $legacy_aliases, $value, ...$args) {
        return self::apply_aliases($hook, $legacy_aliases, $value, ...$args);
    }

    public static function do(string $hook, ...$args): void {
        self::do_aliases($hook, self::legacy_aliases($hook), ...$args);
    }

    public static function do_compat(string $hook, array $legacy_aliases, ...$args): void {
        self::do_aliases($hook, $legacy_aliases, ...$args);
    }

    private static function apply_aliases(string $hook, array $legacy_aliases, $value, ...$args) {
        $value = apply_filters($hook, $value, ...$args);
        foreach (self::normalize_aliases($hook, $legacy_aliases) as $legacy) {
            if (!has_filter($legacy)) {
                continue;
            }

            $legacy_args = array_merge([$value], $args);
            $value = apply_filters($legacy, ...$legacy_args);
        }
        return $value;
    }

    private static function do_aliases(string $hook, array $legacy_aliases, ...$args): void {
        do_action($hook, ...$args);
        foreach (self::normalize_aliases($hook, $legacy_aliases) as $legacy) {
            if (!has_action($legacy)) {
                continue;
            }

            do_action($legacy, ...$args);
        }
    }

    public static function legacy_aliases(string $hook): array {
        return self::LEGACY_ALIASES[$hook] ?? [];
    }

    private static function normalize_aliases(string $hook, array $legacy_aliases): array {
        $aliases = array_filter(array_map('strval', $legacy_aliases), static function (string $alias) use ($hook): bool {
            return '' !== $alias && $hook !== $alias;
        });

        return array_values(array_unique($aliases));
    }
}
