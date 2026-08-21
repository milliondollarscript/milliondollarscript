<?php
/**
 * Payment checkout payload helpers.
 *
 * @package MillionDollarScript\V3\Commerce
 */

namespace MillionDollarScript\V3\Commerce\Concerns;

use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Orders\OrderRepository;

if (!defined('ABSPATH')) {
    exit;
}

trait BuildsPaymentCheckouts {

    public static function create_checkout(array $transaction, array $args = []) {
        $filtered_transaction = \MillionDollarScript\Core\Hooks::apply(
            'million-dollar-script/payment/transaction',
            $transaction,
            $args
        );
        if (is_array($filtered_transaction)) {
            $transaction = $filtered_transaction;
        }
        $override = \MillionDollarScript\Core\Hooks::apply(
            'million-dollar-script/payment/pre/create-checkout',
            null,
            $transaction,
            $args
        );
        if (is_wp_error($override)) {
            return $override;
        }
        if (is_array($override)) {
            return $override;
        }

        $provider_id = sanitize_key((string) ($transaction['payment_provider'] ?? self::active_provider_id()));
        $provider_fell_back = false;
        $providers = self::providers();
        $provider = $providers[$provider_id] ?? $providers['standalone'] ?? null;
        if (!$provider || !self::is_provider_ready($provider)) {
            $provider_id = 'standalone';
            $provider = $providers['standalone'] ?? null;
            $provider_fell_back = true;
        }

        $default = is_array($args['default_payload'] ?? null) ? $args['default_payload'] : self::standalone_checkout($transaction);
        $default['provider'] = $provider_fell_back ? $provider_id : (sanitize_key((string) ($default['provider'] ?? $provider_id)) ?: $provider_id);
        $payload = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/payments/pre/checkout/payload', $default, $transaction, $provider);
        if (!is_array($payload)) {
            $payload = $default;
        }

        $callback = $provider['create_checkout'] ?? null;
        if ('standalone' !== $provider_id && is_callable($callback)) {
            $payload = call_user_func($callback, $transaction, $payload, $provider);
        }

        if (is_wp_error($payload)) {
            return $payload;
        }
        if (!is_array($payload)) {
            $payload = $default;
        }

        $payload['provider'] = sanitize_key((string) ($payload['provider'] ?? $provider_id)) ?: $provider_id;
        $payload['checkout_url'] = esc_url_raw((string) ($payload['checkout_url'] ?? ''));
        $payload['after_upload_url'] = esc_url_raw((string) ($payload['after_upload_url'] ?? ($payload['checkout_url'] ?: ($default['after_upload_url'] ?? ''))));
        $payload['provider_order_id'] = isset($payload['provider_order_id']) ? sanitize_text_field((string) $payload['provider_order_id']) : '';

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/payments/checkout/payload', $payload, $transaction, $provider);
    }

    public static function checkout_for_mds_order(array $order, array $default_payload = []) {
        $order_id = absint($order['id'] ?? 0);
        $provider_id = self::active_provider_id();
        $existing_provider = sanitize_key((string) ($order['commerce_provider'] ?? ''));
        $providers = self::providers();
        $existing_provider_ready = $existing_provider
            && 'standalone' !== $existing_provider
            && !empty($providers[$existing_provider])
            && self::is_provider_ready($providers[$existing_provider]);
        $transaction = [
            'source' => 'mds-grid',
            'source_id' => $order_id,
            'source_key' => (string) ($order['order_key'] ?? ''),
            'payment_provider' => $existing_provider_ready ? $existing_provider : $provider_id,
            'existing_provider_order_id' => $existing_provider_ready ? (string) ($order['commerce_order_id'] ?? '') : '',
            'user_id' => absint($order['user_id'] ?? 0),
            'email' => sanitize_email($order['email'] ?? ''),
            'currency' => Currency::code($order['currency'] ?? 'USD'),
            'subtotal' => (float) ($order['subtotal'] ?? 0),
            'total' => (float) ($order['total'] ?? 0),
            'items' => self::mds_order_items($order_id),
            'manage_url' => self::customer_manage_url_for_mds_order($order),
            'metadata' => [
                'mds_order_id' => $order_id,
                'mds_order_key' => (string) ($order['order_key'] ?? ''),
                'subscription_plan_id' => absint((json_decode((string) ($order['metadata'] ?? ''), true)['subscription_plan_id'] ?? 0)),
            ],
        ];

        $payload = self::create_checkout($transaction, ['default_payload' => $default_payload]);
        if (is_wp_error($payload)) {
            $payload = array_merge($default_payload, [
                'provider' => $transaction['payment_provider'],
                'error' => $payload->get_error_message(),
            ]);
        }

        if (is_array($payload) && 'standalone' !== sanitize_key((string) ($payload['provider'] ?? '')) && !empty($payload['provider_order_id'])) {
            $orders = new OrderRepository();
            $current_order = $orders->find($order_id) ?: $order;
            $current_status = sanitize_key((string) ($current_order['status'] ?? ''));
            $update = [
                'commerce_provider' => sanitize_key((string) $payload['provider']),
                'commerce_order_id' => sanitize_text_field((string) $payload['provider_order_id']),
            ];
            if (!in_array($current_status, ['paid', 'cancelled', 'failed', 'refunded', 'expired', 'denied', 'deleted'], true)) {
                $update['status'] = 'pending_payment';
            }
            $orders->update($order_id, $update);
        }

        return is_array($payload) ? $payload : $default_payload;
    }

    public static function standalone_checkout(array $transaction, array $default = []) {
        if ($default) {
            return $default;
        }

        $url = esc_url_raw((string) ($transaction['checkout_url'] ?? $transaction['manage_url'] ?? ''));

        return [
            'provider' => 'standalone',
            'checkout_url' => $url,
            'after_upload_url' => esc_url_raw((string) ($transaction['after_upload_url'] ?? $url)),
        ];
    }

    public static function customer_manage_url_for_mds_order(array $order) {
        $page_id = absint(get_option('mds3_page_upload_id', 0));
        $url = $page_id ? get_permalink($page_id) : '';
        if (!$url) {
            $grid_id = self::order_grid_id($order);
            $url = $grid_id ? self::grid_url($grid_id) : home_url('/');
        }

        return add_query_arg([
            'mds3_order_id' => absint($order['id'] ?? 0),
            'mds3_order_key' => rawurlencode((string) ($order['order_key'] ?? '')),
        ], $url);
    }

    private static function mds_order_items($order_id) {
        $groups = [];
        foreach ((new OrderRepository())->items(absint($order_id)) as $item) {
            $grid_id = absint($item['grid_id'] ?? 0);
            $key = $grid_id ?: 'order';
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'grid_id' => $grid_id,
                    'amount' => 0.0,
                    'quantity' => 0,
                    'block_count' => 0,
                    'item_count' => 0,
                ];
            }

            $quantity = max(1, absint($item['quantity'] ?? 1));
            $groups[$key]['amount'] += (float) ($item['total'] ?? 0);
            $groups[$key]['quantity'] += $quantity;
            $groups[$key]['item_count']++;
            if (!empty($item['block_id'])) {
                $groups[$key]['block_count'] += $quantity;
            }
        }

        return array_map(function ($group) {
            $block_count = max(1, absint($group['block_count'] ?? 0));

            return [
                'name' => self::grouped_item_name(absint($group['grid_id'] ?? 0), $block_count),
                'amount' => round((float) ($group['amount'] ?? 0), 2),
                'quantity' => max(1, absint($group['quantity'] ?? 0)),
                'metadata' => [
                    'grid_id' => absint($group['grid_id'] ?? 0),
                    'block_count' => $block_count,
                    'order_item_count' => max(1, absint($group['item_count'] ?? 0)),
                    'item_type' => 'grid_selection',
                ],
            ];
        }, array_values($groups));
    }

    private static function grouped_item_name($grid_id, $block_count) {
        return sprintf(
            /* translators: 1: grid ID, 2: block count */
            _n(
                'Million Dollar Script grid %1$d selection (%2$d block)',
                'Million Dollar Script grid %1$d selection (%2$d blocks)',
                max(1, absint($block_count)),
                'million-dollar-script'
            ),
            absint($grid_id),
            max(1, absint($block_count))
        );
    }

    private static function order_grid_id(array $order) {
        foreach ((new OrderRepository())->items(absint($order['id'] ?? 0)) as $item) {
            if (!empty($item['grid_id'])) {
                return absint($item['grid_id']);
            }
        }

        return 0;
    }

    private static function grid_url($grid_id) {
        $posts = get_posts([
            'post_type' => ['mds3_grid_page', 'page'],
            'post_status' => ['publish', 'private'],
            'meta_key' => '_mds3_grid_id',
            'meta_value' => absint($grid_id),
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
        ]);

        return $posts ? get_permalink(absint($posts[0])) : home_url('/');
    }
}
