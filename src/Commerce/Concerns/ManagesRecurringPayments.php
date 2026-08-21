<?php
/**
 * Provider-neutral recurring payment adapter helpers.
 *
 * @package MillionDollarScript\V3\Commerce
 */

namespace MillionDollarScript\V3\Commerce\Concerns;

if (!defined('ABSPATH')) {
    exit;
}

trait ManagesRecurringPayments {

    public static function recurring_adapters() {
        $adapters = \MillionDollarScript\Core\Hooks::apply(
            'million-dollar-script/payment/recurring/adapters',
            []
        );
        if (!is_array($adapters)) {
            return [];
        }

        $normalized = [];
        foreach ($adapters as $id => $adapter) {
            if (!is_array($adapter)) {
                continue;
            }

            $provider_id = sanitize_key((string) ($adapter['provider'] ?? $adapter['id'] ?? $id));
            if (!$provider_id || !isset(self::providers()[$provider_id])) {
                continue;
            }

            $capabilities = is_array($adapter['capabilities'] ?? null)
                ? array_values(array_unique(array_filter(array_map('sanitize_key', $adapter['capabilities']))))
                : [];
            $adapter['id'] = sanitize_key((string) ($adapter['id'] ?? $provider_id)) ?: $provider_id;
            $adapter['provider'] = $provider_id;
            $adapter['capabilities'] = $capabilities;
            $normalized[$provider_id] = $adapter;
        }

        return $normalized;
    }

    public static function recurring_adapter($provider_id) {
        $provider_id = sanitize_key((string) $provider_id);

        return self::recurring_adapters()[$provider_id] ?? null;
    }

    public static function recurring_capabilities($provider_id) {
        $adapter = self::recurring_adapter($provider_id);

        return is_array($adapter) ? (array) ($adapter['capabilities'] ?? []) : [];
    }

    public static function recurring_ready($provider_id, array $context = []) {
        $adapter = self::recurring_adapter($provider_id);
        if (!$adapter || !self::provider_ready($provider_id)) {
            return false;
        }

        $ready = $adapter['ready'] ?? false;
        if (is_callable($ready)) {
            $ready = call_user_func($ready, $context, $adapter);
        }

        return (bool) $ready;
    }

    public static function prepare_recurring_checkout($provider_id, array $transaction, array $billing) {
        return self::call_recurring_adapter(
            $provider_id,
            'prepare_checkout',
            [$transaction, $billing],
            'mds_recurring_prepare_unsupported',
            __('This payment provider cannot prepare recurring checkout.', 'million-dollar-script')
        );
    }

    public static function collect_recurring_cycle($provider_id, array $cycle) {
        return self::call_recurring_adapter(
            $provider_id,
            'collect_cycle',
            [$cycle],
            'mds_recurring_collection_unsupported',
            __('This payment provider cannot collect automatic renewals.', 'million-dollar-script')
        );
    }

    public static function create_recurring_payment_link($provider_id, array $cycle) {
        return self::call_recurring_adapter(
            $provider_id,
            'create_payment_link',
            [$cycle],
            'mds_recurring_payment_link_unsupported',
            __('This payment provider cannot create a renewal payment link.', 'million-dollar-script')
        );
    }

    public static function recurring_payment_method_url($provider_id, array $subscription) {
        return self::call_recurring_adapter(
            $provider_id,
            'payment_method_url',
            [$subscription],
            'mds_recurring_payment_method_unsupported',
            __('This payment provider cannot update the recurring payment method.', 'million-dollar-script')
        );
    }

    private static function call_recurring_adapter($provider_id, $operation, array $arguments, $error_code, $message) {
        $provider_id = sanitize_key((string) $provider_id);
        $adapter = self::recurring_adapter($provider_id);
        if (!$adapter || !self::recurring_ready($provider_id, ['operation' => $operation])) {
            return new \WP_Error($error_code, $message, ['status' => 409, 'provider' => $provider_id]);
        }

        $callback = $adapter[$operation] ?? null;
        if (!is_callable($callback)) {
            return new \WP_Error($error_code, $message, ['status' => 409, 'provider' => $provider_id]);
        }

        $result = call_user_func_array($callback, $arguments);
        if (is_wp_error($result)) {
            return $result;
        }

        return $result;
    }
}
