<?php
/**
 * Paid order renewal helper.
 *
 * @package MillionDollarScript\V3\Orders
 */

namespace MillionDollarScript\V3\Orders;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class OrderRenewal {

    public function can_renew(array $order) {
        $eligibility = $this->eligibility($order);

        return !empty($eligibility['eligible']);
    }

    public function eligibility(array $order) {
        $order_id = absint($order['id'] ?? 0);
        $metadata = $this->metadata($order);
        $duration_days = $this->duration_days($metadata);
        $status = sanitize_key((string) ($order['status'] ?? ''));

        if (!$order_id || 'expired' !== $status) {
            return [
                'eligible' => false,
                'reason' => 'status',
            ];
        }

        if (!$duration_days) {
            return [
                'eligible' => false,
                'reason' => 'duration',
            ];
        }

        if (!$this->has_retained_inventory($order_id)) {
            return [
                'eligible' => false,
                'reason' => 'inventory',
            ];
        }

        return [
            'eligible' => true,
            'duration_days' => $duration_days,
            'expires_at' => sanitize_text_field((string) ($metadata['expires_at'] ?? '')),
        ];
    }

    public function start_by_credentials($order_id, $order_key, array $context = []) {
        $order = (new OrderRepository())->find($order_id);
        if (!$order || !hash_equals((string) ($order['order_key'] ?? ''), (string) $order_key)) {
            return new \WP_Error('mds3_renewal_order_not_verified', __('Order could not be verified.', 'million-dollar-script'));
        }

        return $this->start($order, $context);
    }

    public function start(array $order, array $context = []) {
        $eligibility = $this->eligibility($order);
        if (empty($eligibility['eligible'])) {
            return new \WP_Error('mds3_renewal_not_available', __('This order is not eligible for renewal.', 'million-dollar-script'));
        }

        $orders = new OrderRepository();
        $order_id = absint($order['id'] ?? 0);
        $metadata = $this->metadata($order);
        $source = sanitize_key((string) ($context['source'] ?? 'customer')) ?: 'customer';
        $force_new_checkout = !empty($context['force_new_checkout']);
        $renewal_started = !empty($metadata['renewal_started_at']);

        if ($force_new_checkout || !$renewal_started) {
            $metadata = $this->prepare_metadata_for_renewal($metadata, $order, $source, !$renewal_started);
            $update = ['metadata' => $metadata];
            if (!empty($order['commerce_order_id'])) {
                $update['commerce_order_id'] = '';
            }

            $updated = $orders->update($order_id, $update);
            if (is_wp_error($updated)) {
                return $updated;
            }
            $order = $updated;
        }

        $checkout = (new CheckoutRouter())->payload($order);
        if (!is_array($checkout)) {
            return new \WP_Error('mds3_renewal_checkout_failed', __('Renewal checkout could not be created.', 'million-dollar-script'));
        }

        $order = $orders->find($order_id) ?: $order;
        $this->store_provider_order($order, $checkout);
        $order = $orders->find($order_id) ?: $order;

        \MillionDollarScript\Core\Hooks::do('million-dollar-script/order/renewal/started', $order, $checkout, $context);

        return [
            'order' => $order,
            'checkout' => $checkout,
            'eligibility' => $eligibility,
        ];
    }

    private function prepare_metadata_for_renewal(array $metadata, array $order, $source, $archive_term = true) {
        $now = gmdate('Y-m-d H:i:s');
        $term = [];
        if ($archive_term) {
            foreach (['paid_at', 'term_started_at', 'expires_at'] as $key) {
                if (!empty($metadata[$key]) && is_scalar($metadata[$key])) {
                    $term[$key] = sanitize_text_field((string) $metadata[$key]);
                }
            }

            if ($term) {
                $term['ended_at'] = $now;
                $term['provider'] = sanitize_key((string) ($order['commerce_provider'] ?? ''));
                $term['provider_order_id'] = sanitize_text_field((string) ($order['commerce_order_id'] ?? ''));
                $history = is_array($metadata['renewal_terms'] ?? null) ? $metadata['renewal_terms'] : [];
                $history[] = array_filter($term, static function($value) {
                    return '' !== (string) $value;
                });
                $metadata['renewal_terms'] = array_slice($history, -20);
            }
        }

        if (empty($metadata['renewal_started_at'])) {
            $metadata['renewal_started_at'] = $now;
        } else {
            $metadata['renewal_refreshed_at'] = $now;
        }
        $metadata['renewal_source'] = $source;
        if (!empty($order['commerce_provider'])) {
            $metadata['renewal_previous_provider'] = sanitize_key((string) $order['commerce_provider']);
        }
        if (!empty($order['commerce_order_id'])) {
            $metadata['renewal_previous_provider_order_id'] = sanitize_text_field((string) $order['commerce_order_id']);
        }

        return $metadata;
    }

    private function store_provider_order(array $order, array $checkout) {
        $provider_order_id = sanitize_text_field((string) ($checkout['provider_order_id'] ?? ''));
        if (!$provider_order_id) {
            return;
        }

        $metadata = $this->metadata($order);
        if ($provider_order_id === (string) ($metadata['renewal_provider_order_id'] ?? '')) {
            return;
        }

        $metadata['renewal_provider_order_id'] = $provider_order_id;
        (new OrderRepository())->update(absint($order['id'] ?? 0), ['metadata' => $metadata]);
    }

    private function has_retained_inventory($order_id) {
        global $wpdb;

        $statuses = ['reserved', 'sold'];
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $args = array_merge([absint($order_id)], $statuses);

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DB::ident(DB::table('blocks')) . " WHERE order_id = %d AND status IN ({$placeholders})",
                $args
            )
        ) > 0;
    }

    private function duration_days(array $metadata) {
        return absint($metadata['duration_days'] ?? ($metadata['package']['duration_days'] ?? 0));
    }

    private function metadata(array $order) {
        $metadata = json_decode((string) ($order['metadata'] ?? ''), true);

        return is_array($metadata) ? $metadata : [];
    }
}
