<?php
/**
 * Currency helpers for standalone and payment-provider-backed checkout.
 *
 * @package MillionDollarScript\V3\Commerce
 */

namespace MillionDollarScript\V3\Commerce;

use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

final class Currency {

    public static function provider_locks_currency(array $settings = null) {
        return class_exists(__NAMESPACE__ . '\\Payments') && Payments::provider_locks_currency($settings);
    }

    public static function code($fallback = 'USD', array $settings = null) {
        if (self::provider_locks_currency($settings)) {
            return self::normalize_code(Payments::provider_currency_code($fallback, $settings), 'USD');
        }

        return self::normalize_code(self::scalar_text($fallback, 'USD'), 'USD');
    }

    public static function symbol($fallback = '$', array $settings = null) {
        if (self::provider_locks_currency($settings)) {
            return self::clean_text(Payments::provider_currency_symbol($fallback, $settings));
        }

        return self::clean_text(self::scalar_text($fallback, '$'));
    }

    public static function current_code(array $settings = null) {
        $settings = $settings ?: self::settings();

        return self::code($settings['currency'] ?? 'USD', $settings);
    }

    public static function current_symbol(array $settings = null) {
        $settings = $settings ?: self::settings();

        return self::symbol($settings['currency-symbol'] ?? '$', $settings);
    }

    public static function effective_code($currency = '', array $settings = null, $max_length = 3) {
        $settings = $settings ?: self::settings();
        if (self::provider_locks_currency($settings)) {
            return self::current_code($settings);
        }

        return self::normalize_code($currency ?: self::current_code($settings), self::current_code($settings), $max_length);
    }

    public static function amount($amount) {
        return round(max(0, (float) $amount), 2);
    }

    public static function format($amount, $currency = '', array $settings = null, $include_symbol = false) {
        $settings = $settings ?: self::settings();
        $currency = self::scalar_text($currency, '');
        $currency = '' !== trim($currency)
            ? self::normalize_code($currency, $settings['currency'] ?? 'USD')
            : self::current_code($settings);
        $number = function_exists('number_format_i18n')
            ? number_format_i18n((float) $amount, 2)
            : number_format((float) $amount, 2, '.', ',');

        if ($include_symbol) {
            $symbol = self::symbol($settings['currency-symbol'] ?? '$', $settings);
            if ('' !== $symbol) {
                return $symbol . $number . ' ' . $currency;
            }
        }

        return trim($currency . ' ' . $number);
    }

    public static function settings_with_effective_values(array $settings) {
        if (self::provider_locks_currency($settings)) {
            $settings['currency'] = self::code($settings['currency'] ?? 'USD', $settings);
            $settings['currency-symbol'] = self::symbol($settings['currency-symbol'] ?? '$', $settings);
        }

        return $settings;
    }

    private static function settings() {
        $settings = function_exists('get_option') ? get_option('mds3_settings', []) : [];
        $settings = is_array($settings) ? $settings : [];

        return function_exists('wp_parse_args') ? wp_parse_args($settings, SettingsSchema::defaults()) : array_merge(SettingsSchema::defaults(), $settings);
    }

    public static function normalize_code($value, $fallback = 'USD', $max_length = 3) {
        $max_length = max(1, min(8, (int) $max_length));
        $code = strtoupper(substr(preg_replace('/[^A-Z]/', '', strtoupper(self::clean_text((string) $value))), 0, $max_length));

        $fallback_code = strtoupper(substr(preg_replace('/[^A-Z]/', '', strtoupper((string) $fallback)), 0, $max_length));

        return '' !== $code ? $code : ($fallback_code ?: 'USD');
    }

    private static function clean_text($value) {
        $text = sanitize_text_field($value);

        if (function_exists('wp_specialchars_decode')) {
            $text = wp_specialchars_decode($text, ENT_QUOTES);
        }

        return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    }

    private static function scalar_text($value, $fallback) {
        return is_scalar($value) || null === $value ? (string) $value : (string) $fallback;
    }
}
