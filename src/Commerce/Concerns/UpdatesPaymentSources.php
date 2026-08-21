<?php
/**
 * Payment source status updates.
 *
 * @package MillionDollarScript\V3\Commerce
 */

namespace MillionDollarScript\V3\Commerce\Concerns;

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\OrderRepository;

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

            $update = ['status' => $status];
            if ('paid' === $status) {
                $update['metadata'] = self::paid_order_metadata($order);
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
                (new PlacementRepository())->update_status_by_order($source_id, 'active');
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

    private static function paid_order_metadata(array $order) {
        $metadata = json_decode((string) ($order['metadata'] ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];
        $current_status = sanitize_key((string) ($order['status'] ?? ''));
        $now = gmdate('Y-m-d H:i:s');
        if ('paid' !== $current_status) {
            $metadata['paid_at'] = $now;
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
