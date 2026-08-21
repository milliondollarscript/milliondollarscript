<?php
/**
 * Payment provider registry helpers.
 *
 * @package MillionDollarScript\V3\Commerce
 */

namespace MillionDollarScript\V3\Commerce\Concerns;

use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

trait ManagesPaymentProviders {

    public static function default_provider_options(array $options) {
        return array_merge([
            'standalone' => __('Standalone/manual checkout', 'million-dollar-script'),
        ], $options);
    }

    public static function default_providers(array $providers) {
        $providers['standalone'] = array_merge([
            'id' => 'standalone',
            'label' => __('Standalone/manual checkout', 'million-dollar-script'),
            'ready' => true,
            'create_checkout' => [__CLASS__, 'standalone_checkout'],
        ], is_array($providers['standalone'] ?? null) ? $providers['standalone'] : []);

        return $providers;
    }

    public static function provider_options() {
        $options = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/payment/provider/options', []);
        if (!is_array($options)) {
            $options = [];
        }

        return self::default_provider_options($options);
    }

    public static function providers() {
        $providers = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/payment/providers', []);
        if (!is_array($providers)) {
            $providers = [];
        }

        $providers = self::default_providers($providers);
        foreach ($providers as $id => $provider) {
            if (!is_array($provider)) {
                unset($providers[$id]);
                continue;
            }

            $provider['id'] = sanitize_key((string) ($provider['id'] ?? $id));
            if (!$provider['id']) {
                unset($providers[$id]);
                continue;
            }

            $providers[$provider['id']] = $provider;
            if ($provider['id'] !== $id) {
                unset($providers[$id]);
            }
        }

        return $providers;
    }

    public static function active_provider_id(array $settings = null) {
        $settings = null === $settings ? self::settings() : $settings;
        $configured = sanitize_key((string) ($settings['payment_provider'] ?? ''));
        if (!$configured) {
            $configured = 'standalone';
        }

        $providers = self::providers();
        $provider = $providers[$configured] ?? null;
        if (!$provider || !self::is_provider_ready($provider, $settings)) {
            return 'standalone';
        }

        return $configured;
    }

    public static function active_provider(array $settings = null) {
        $provider_id = self::active_provider_id($settings);

        return self::providers()[$provider_id] ?? self::providers()['standalone'];
    }

    public static function active_provider_label(array $settings = null) {
        $provider = self::active_provider($settings);

        return (string) ($provider['label'] ?? $provider['id'] ?? __('Standalone/manual checkout', 'million-dollar-script'));
    }

    public static function provider_ready($provider_id = null, array $settings = null) {
        if (null === $provider_id) {
            return self::is_provider_ready(self::active_provider($settings), $settings ?: self::settings());
        }

        $provider_id = sanitize_key((string) $provider_id);
        $provider = self::providers()[$provider_id] ?? null;

        return is_array($provider) && self::is_provider_ready($provider, $settings ?: self::settings());
    }

    public static function provider_locks_currency(array $settings = null) {
        $provider = self::active_provider($settings);

        return !empty($provider['locks_currency']);
    }

    public static function provider_currency_code($fallback = 'USD', array $settings = null) {
        $provider = self::active_provider($settings);
        $callback = $provider['currency_code'] ?? null;
        if (is_callable($callback)) {
            $value = call_user_func($callback, $fallback, $settings ?: self::settings(), $provider);
            if (is_scalar($value) && '' !== trim((string) $value)) {
                return Currency::normalize_code($value, $fallback);
            }
        }

        return Currency::normalize_code($fallback);
    }

    public static function provider_currency_symbol($fallback = '$', array $settings = null) {
        $provider = self::active_provider($settings);
        $callback = $provider['currency_symbol'] ?? null;
        if (is_callable($callback)) {
            $value = call_user_func($callback, $fallback, $settings ?: self::settings(), $provider);
            if (is_scalar($value) && '' !== trim((string) $value)) {
                return sanitize_text_field((string) $value);
            }
        }

        return sanitize_text_field((string) $fallback) ?: '$';
    }

    private static function is_provider_ready(array $provider, array $settings = null) {
        $ready = $provider['ready'] ?? false;
        if (is_callable($ready)) {
            $ready = call_user_func($ready, $settings ?: self::settings(), $provider);
        }

        return (bool) $ready;
    }

    private static function settings() {
        $settings = function_exists('get_option') ? get_option('mds3_settings', []) : [];
        $settings = is_array($settings) ? $settings : [];

        return function_exists('wp_parse_args') ? wp_parse_args($settings, SettingsSchema::defaults()) : array_merge(SettingsSchema::defaults(), $settings);
    }
}
