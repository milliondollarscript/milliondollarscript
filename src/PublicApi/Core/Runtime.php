<?php
/**
 * Stable runtime metadata for extensions.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Core;

use MillionDollarScript\V3\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

final class Runtime {

    public static function version(): string {
        return defined('MILLION_DOLLAR_SCRIPT_VERSION') ? (string) MILLION_DOLLAR_SCRIPT_VERSION : '';
    }

    public static function api_version(): string {
        return '1';
    }

    public static function file(): string {
        return defined('MILLION_DOLLAR_SCRIPT_FILE') ? (string) MILLION_DOLLAR_SCRIPT_FILE : '';
    }

    public static function path(string $relative = ''): string {
        $base = defined('MILLION_DOLLAR_SCRIPT_PATH') ? (string) MILLION_DOLLAR_SCRIPT_PATH : '';
        return self::join($base, $relative);
    }

    public static function url(string $relative = ''): string {
        $base = defined('MILLION_DOLLAR_SCRIPT_URL') ? (string) MILLION_DOLLAR_SCRIPT_URL : '';
        return self::join($base, $relative);
    }

    public static function is_ready(): bool {
        return class_exists(Plugin::class, false) && Plugin::is_booted();
    }

    private static function join(string $base, string $relative): string {
        $relative = str_replace('\\', '/', trim($relative));
        if ('' === $relative) {
            return $base;
        }

        $trailing_slash = str_ends_with($relative, '/');

        $segments = explode('/', trim($relative, '/'));
        if (in_array('..', $segments, true) || str_contains($relative, "\0")) {
            return $base;
        }

        $joined = rtrim($base, '/\\') . '/' . implode('/', array_filter($segments, static fn(string $segment): bool => '' !== $segment && '.' !== $segment));

        return $joined . ($trailing_slash ? '/' : '');
    }
}
