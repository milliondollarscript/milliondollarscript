<?php

namespace MillionDollarScript\V3\Migration;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read one value that was copied out of another site's storage.
 *
 * WordPress' maybe_unserialize() would let a serialized object name any class
 * that exists on the site, and a class with a destructor or __wakeup() runs
 * before the value is ever looked at. Migration input comes from tables this
 * plugin did not write - the previous plugin's post meta, options and order
 * rows - so nothing in it is allowed to be an object. Arrays, numbers and
 * strings decode exactly as before; an object stays data.
 */
final class LegacyValue
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public static function read($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ('' === $trimmed || !self::looks_serialized($trimmed)) {
            return $value;
        }

        $decoded = @unserialize($trimmed, ['allowed_classes' => false]);
        if (false === $decoded && 'b:0;' !== $trimmed) {
            // Not readable after all: hand back what we were given rather than
            // turning a stored string into a false.
            return $value;
        }

        return $decoded;
    }

    /**
     * Is this string a serialized value at all? Inside WordPress this is WP's own
     * test, so a value decodes here exactly when maybe_unserialize() would have
     * decoded it. The fallback mirrors the same checks for a context without the
     * WordPress helpers (the offline test runner).
     */
    private static function looks_serialized(string $value): bool
    {
        if (function_exists('is_serialized')) {
            return is_serialized($value);
        }

        if ('N;' === $value) {
            return true;
        }
        if (strlen($value) < 4 || ':' !== $value[1]) {
            return false;
        }
        $last = substr($value, -1);
        if (';' !== $last && '}' !== $last) {
            return false;
        }

        return in_array($value[0], ['s', 'a', 'O', 'b', 'i', 'd'], true);
    }
}
