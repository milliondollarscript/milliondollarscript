<?php
/**
 * Stable currency facade.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Commerce;

use MillionDollarScript\V3\Commerce\Currency as InternalCurrency;

if (!defined('ABSPATH')) {
    exit;
}

final class Currency {

    public static function provider_locks_currency(?array $settings = null): bool {
        return (bool) InternalCurrency::provider_locks_currency($settings);
    }

    public static function current_code(?array $settings = null): string {
        return (string) InternalCurrency::current_code($settings);
    }

    public static function effective_code($currency = '', ?array $settings = null, $max_length = 3): string {
        return (string) InternalCurrency::effective_code($currency, $settings, $max_length);
    }

    public static function amount($amount): float {
        return (float) InternalCurrency::amount($amount);
    }

    public static function format($amount, $currency = '', ?array $settings = null, $include_symbol = false): string {
        return (string) InternalCurrency::format($amount, $currency, $settings, $include_symbol);
    }

    public static function normalize_code($value, $fallback = 'USD', $max_length = 3): string {
        return (string) InternalCurrency::normalize_code($value, $fallback, $max_length);
    }
}
