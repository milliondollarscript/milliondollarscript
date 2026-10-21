<?php
/**
 * Payment source status updates.
 *
 * @package MillionDollarScript\V3\Commerce
 */

namespace MillionDollarScript\V3\Commerce\Concerns;

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\OrderConfirmation;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Orders\PublishGate;

if (!defined('ABSPATH')) {
    exit;
}

trait UpdatesPaymentSources {

    public static function mark_source_paid($source, $source_id, array $context = []) {
        return self::mark_source_status($source, $source_id, 'paid', $context);
    }

    public static function mark_source_cancelled($source, $source_id, array $context = []) {
        return self::mark_source_status($source, $source_id, 'cancelled', $context);
    }

    /**
     * Take an order forward when nothing is going to confirm the money.
     *
     * This is the door a payment provider knocks on when its own flow has stopped waiting: a
     * WooCommerce order paid by bank transfer, cheque or on collection sits at on-hold forever,
     * because no gateway will ever report it as paid. Whether that is allowed is not the
     * provider's question to answer - Orders/OrderConfirmation owns it, from the site switch, the
     * grids in the basket, the order's total and the customer's own permission - so this method
     * asks once and records the answer it was given.
     *
     * @return bool whether the order was moved to paid
     */
    public static function accept_manual_payment($source, $source_id, array $context = []) {
        if ('mds-grid' !== sanitize_key((string) $source)) {
            return false;
        }

        $orders = new OrderRepository();
        $order = $orders->find(absint($source_id));
        if (!$order) {
            return false;
        }

        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $reason = (new OrderConfirmation())->manual_payment_reason($order, $settings);
        if ('' === $reason) {
            return false;
        }

        return self::mark_source_status($source, $source_id, 'paid', array_merge($context, [
            'manual_payment_reason' => $reason,
        ]));
    }

    public static function mark_source_status($source, $source_id, $status, array $context = []) {
        $source = sanitize_key((string) $source);
        $source_id = absint($source_id);
        $status = sanitize_key((string) $status);
        $allowed = ['reserved', 'pending_payment', 'paid', 'cancelled', 'failed', 'refunded', 'expired', 'denied', 'deleted'];
        if (!$source || !$source_id || !in_array($status, $allowed, true)) {
            return false;
        }

        if ('mds-grid' === $source) {
            $orders = new OrderRepository();
            $order = $orders->find($source_id);
            if (!$order) {
                return false;
            }

            // A paid signal is not always a payment. The subscriptions extension pays every
            // cycle through here (SourceAdapters::apply_paid_cycle), WooCommerce fires
            // payment_complete and status-completed for the same order, and an admin can
            // re-confirm a paid order. paid_order_metadata() below already refuses to start a
            // second term clock for an order that has already paid; the creative it is showing
            // now follows the same rule. What is published is a decision a site makes - by the
            // grid's review setting, from the order screen, or by taking a migrated record out
            // of the queue - and a repeated signal must not quietly undo it.
            $newly_paid = 'paid' !== sanitize_key((string) ($order['status'] ?? ''));
            $update = ['status' => $status];
            if ('paid' === $status) {
                $update['metadata'] = self::paid_order_metadata($order, $context);
                $provider = sanitize_key((string) ($context['provider'] ?? ''));
                $provider_order_id = sanitize_text_field((string) ($context['provider_order_id'] ?? ''));
                if ($provider) {
                    $update['commerce_provider'] = $provider;
                }
                if ($provider_order_id) {
                    $update['commerce_order_id'] = $provider_order_id;
                }
            }
            $orders->update($source_id, $update);
            if ('paid' === $status) {
                (new BlockRepository())->mark_by_order($source_id, 'sold');
                if ($newly_paid) {
                    PublishGate::publish($source_id, PublishGate::PAYMENT_CAPTURED);
                }
            } elseif (in_array($status, ['cancelled', 'failed', 'refunded', 'expired', 'denied', 'deleted'], true)) {
                $release_inventory = array_key_exists('release_inventory', $context) ? (bool) $context['release_inventory'] : true;
                if ($release_inventory) {
                    (new BlockRepository())->release_by_order($source_id);
                    (new PlacementRepository())->update_status_by_order($source_id, 'cancelled');
                } else {
                    (new BlockRepository())->mark_by_order($source_id, 'reserved');
                    (new PlacementRepository())->update_status_by_order($source_id, 'archived');
                }
            }
        }

        if ('paid' === $status) {
            \MillionDollarScript\Core\Hooks::do('million-dollar-script/payment/source/paid', $source, $source_id, $context);
        } elseif ('cancelled' === $status) {
            \MillionDollarScript\Core\Hooks::do('million-dollar-script/payment/source/cancelled', $source, $source_id, $context);
        }

        $inventory_released = !array_key_exists('release_inventory', $context) || (bool) $context['release_inventory'];
        if ($inventory_released && in_array($status, ['cancelled', 'failed', 'refunded', 'expired', 'denied', 'deleted'], true)) {
            \MillionDollarScript\Core\Hooks::do('million-dollar-script/payment/source/released', $source, $source_id, $status, $context);
        }

        \MillionDollarScript\Core\Hooks::do('million-dollar-script/payment/source/status', $source, $source_id, $status, $context);

        return true;
    }

    private static function paid_order_metadata(array $order, array $context = []) {
        $metadata = json_decode((string) ($order['metadata'] ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];
        $current_status = sanitize_key((string) ($order['status'] ?? ''));
        $now = gmdate('Y-m-d H:i:s');
        if ('paid' !== $current_status) {
            $metadata['paid_at'] = $now;
        }

        // An ad that went live without a gateway taking money is a decision a site made, and a
        // person reading the order later is entitled to know which one. Without this the only
        // clue is the absence of a payment.
        $manual_reason = sanitize_key((string) ($context['manual_payment_reason'] ?? ''));
        if ('' !== $manual_reason && 'paid' !== $current_status) {
            $metadata['paid_without_gateway_confirmation'] = $manual_reason;
        }

        // What the provider says it was paid, next to what the order asked for. A provider
        // completes an order it was told to complete; the amount is verified by the checkout,
        // not by this path. Recording both keeps a mismatch visible to a person instead of
        // leaving it to the invoice.
        $provider_total = isset($context['provider_total']) && is_numeric($context['provider_total']) ? (float) $context['provider_total'] : null;
        $provider_currency = sanitize_text_field((string) ($context['provider_currency'] ?? ''));
        if (null !== $provider_total) {
            $metadata['provider_paid_total'] = number_format($provider_total, 2, '.', '');
            if ('' !== $provider_currency) {
                $metadata['provider_paid_currency'] = $provider_currency;
            }

            $asked_total = isset($order['total']) && is_numeric($order['total']) ? (float) $order['total'] : null;
            $asked_currency = strtoupper(sanitize_text_field((string) ($order['currency'] ?? '')));
            $currency_matches = '' === $provider_currency || '' === $asked_currency || strtoupper($provider_currency) === $asked_currency;
            $amount_matches = null === $asked_total || abs($provider_total - $asked_total) < 0.005;
            if ($currency_matches && $amount_matches) {
                unset($metadata['provider_amount_mismatch']);
            } else {
                $metadata['provider_amount_mismatch'] = [
                    'asked_total' => null === $asked_total ? null : number_format($asked_total, 2, '.', ''),
                    'asked_currency' => $asked_currency,
                    'paid_total' => number_format($provider_total, 2, '.', ''),
                    'paid_currency' => $provider_currency,
                    'currency_only' => !$currency_matches && $amount_matches,
                    'recorded_at' => $now,
                ];
            }
        }

        $duration_days = absint($metadata['duration_days'] ?? ($metadata['package']['duration_days'] ?? 0));
        if ($duration_days && 'paid' !== $current_status) {
            $metadata['duration_days'] = $duration_days;
            $metadata['term_started_at'] = $now;
            $metadata['expires_at'] = gmdate('Y-m-d H:i:s', time() + ($duration_days * DAY_IN_SECONDS));
            unset(
                $metadata['renewal_started_at'],
                $metadata['renewal_source'],
                $metadata['renewal_provider_order_id'],
                $metadata['renewal_previous_provider'],
                $metadata['renewal_previous_provider_order_id']
            );
        }

        return $metadata;
    }

    public static function complete_provider_order_for_mds_order($mds_order_id) {
        $order = (new OrderRepository())->find(absint($mds_order_id));
        if (!$order || empty($order['commerce_provider']) || empty($order['commerce_order_id'])) {
            return false;
        }

        $provider = self::providers()[sanitize_key((string) $order['commerce_provider'])] ?? null;
        $callback = is_array($provider) ? ($provider['complete_source_order'] ?? null) : null;
        if (!is_callable($callback)) {
            return false;
        }

        return (bool) call_user_func($callback, $order);
    }
}
