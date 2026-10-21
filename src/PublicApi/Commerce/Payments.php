<?php
/**
 * Stable payment-provider facade.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Commerce;

use MillionDollarScript\V3\Commerce\Payments as InternalPayments;

if (!defined('ABSPATH')) {
    exit;
}

final class Payments {

    public static function provider_options(): array {
        return (array) InternalPayments::provider_options();
    }

    public static function providers(): array {
        return (array) InternalPayments::providers();
    }

    public static function provider_ready(?string $provider_id = null, ?array $settings = null): bool {
        return (bool) InternalPayments::provider_ready($provider_id, $settings);
    }

    public static function active_provider_id(?array $settings = null): string {
        return (string) InternalPayments::active_provider_id($settings);
    }

    /** @return array|\WP_Error */
    public static function create_checkout(array $transaction, array $args = []) {
        return InternalPayments::create_checkout($transaction, $args);
    }

    public static function recurring_adapters(): array {
        return (array) InternalPayments::recurring_adapters();
    }

    public static function recurring_adapter(string $provider_id): ?array {
        $adapter = InternalPayments::recurring_adapter($provider_id);

        return is_array($adapter) ? $adapter : null;
    }

    public static function recurring_capabilities(string $provider_id): array {
        return (array) InternalPayments::recurring_capabilities($provider_id);
    }

    public static function recurring_ready(string $provider_id, array $context = []): bool {
        return (bool) InternalPayments::recurring_ready($provider_id, $context);
    }

    /** @return array|\WP_Error */
    public static function prepare_recurring_checkout(string $provider_id, array $transaction, array $billing) {
        return InternalPayments::prepare_recurring_checkout($provider_id, $transaction, $billing);
    }

    /** @return array|\WP_Error */
    public static function collect_recurring_cycle(string $provider_id, array $cycle) {
        return InternalPayments::collect_recurring_cycle($provider_id, $cycle);
    }

    /** @return array|\WP_Error */
    public static function create_recurring_payment_link(string $provider_id, array $cycle) {
        return InternalPayments::create_recurring_payment_link($provider_id, $cycle);
    }

    /** @return string|\WP_Error */
    public static function recurring_payment_method_url(string $provider_id, array $subscription) {
        return InternalPayments::recurring_payment_method_url($provider_id, $subscription);
    }

    public static function customer_manage_url_for_mds_order(array $order): string {
        return (string) InternalPayments::customer_manage_url_for_mds_order($order);
    }

    public static function mark_source_paid($source, $source_id, array $context = []): bool {
        return (bool) InternalPayments::mark_source_paid($source, $source_id, $context);
    }

    /**
     * Take an order forward when nothing is going to confirm the money: a bank transfer, a
     * cheque, payment on collection, or an order with nothing to pay.
     *
     * A payment provider calls this when its own flow has stopped waiting, because a WooCommerce
     * order paid by bank transfer sits awaiting payment until someone acts. Whether that is
     * allowed is the site's decision, not the provider's - it comes from the site's manual
     * payment setting, each grid's own review setting, the order's total, and whether this
     * customer is allowed to order without paying. The order records which of those applied.
     *
     * @return bool true when the order was moved to paid
     */
    public static function accept_manual_payment($source, $source_id, array $context = []): bool {
        return (bool) InternalPayments::accept_manual_payment($source, $source_id, $context);
    }

    public static function mark_source_cancelled($source, $source_id, array $context = []): bool {
        return (bool) InternalPayments::mark_source_cancelled($source, $source_id, $context);
    }

    public static function mark_source_status($source, $source_id, $status, array $context = []): bool {
        return (bool) InternalPayments::mark_source_status($source, $source_id, $status, $context);
    }
}
