<?php
/**
 * Checkout URL routing for standalone and extension-provided payment flows.
 *
 * @package MillionDollarScript\V3\Orders
 */

namespace MillionDollarScript\V3\Orders;

use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

final class CheckoutRouter {

    public function payload(array $order) {
        $provider = sanitize_key((string) ($order['commerce_provider'] ?? 'standalone')) ?: 'standalone';
        $providers = Payments::providers();
        if ('standalone' !== $provider && empty($providers[$provider])) {
            $provider = Payments::active_provider_id();
        }

        $settings = $this->settings();
        $standalone_url = 'standalone' === $provider ? $this->standalone_checkout_url($order, $settings) : '';
        $default = [
            'provider' => $provider,
            'checkout_url' => $standalone_url,
            'after_upload_url' => $standalone_url ?: $this->thank_you_url($order, $settings),
        ];

        $payload = Payments::checkout_for_mds_order($order, $default);
        if (!is_array($payload)) {
            $payload = $default;
        }

        $payload['provider'] = sanitize_key((string) ($payload['provider'] ?? $provider)) ?: $provider;
        $payload['checkout_url'] = esc_url_raw($payload['checkout_url'] ?? '');
        $payload['after_upload_url'] = esc_url_raw($payload['after_upload_url'] ?? ($payload['checkout_url'] ?: $default['after_upload_url']));

        return $payload;
    }

    public function standalone_checkout_url(array $order, array $settings = null) {
        $settings = $settings ?: $this->settings();
        $url = esc_url_raw($settings['checkout-url'] ?? '');
        if ('' === $url) {
            return '';
        }

        $values = $this->order_placeholder_values($order);
        $has_legacy_placeholders = false !== strpos($url, '%');

        $url = str_replace(
            ['%AMOUNT%', '%CURRENCY%', '%QUANTITY%', '%ORDERID%', '%ORDERID', '%USERID%', '%GRID%', '%PIXELID%'],
            [
                $values['amount'],
                $values['currency'],
                $values['quantity'],
                $values['order_id'],
                $values['order_id'],
                $values['user_id'],
                $values['grid_id'],
                $values['block_id'],
            ],
            $url
        );

        if ($has_legacy_placeholders) {
            return esc_url_raw($url);
        }

        return add_query_arg([
            'mds3_order_id' => $values['order_id'],
            'mds3_order_key' => $values['order_key'],
            'amount' => $values['amount'],
            'currency' => $values['currency'],
            'quantity' => $values['quantity'],
            'grid_id' => $values['grid_id'],
            'block_id' => $values['block_id'],
            'return_url' => $this->thank_you_url($order, $settings),
        ], $url);
    }

    public function thank_you_url(array $order, array $settings = null) {
        $settings = $settings ?: $this->settings();
        $base = esc_url_raw($settings['thank-you-page'] ?? '');
        if ('' === $base) {
            $page_id = absint(get_option('mds3_page_thank-you_id', 0));
            $base = $page_id ? get_permalink($page_id) : '';
        }

        if (!$base) {
            return '';
        }

        return add_query_arg([
            'mds3_order_id' => absint($order['id'] ?? 0),
            'mds3_order_key' => (string) ($order['order_key'] ?? ''),
        ], $base);
    }

    private function order_placeholder_values(array $order) {
        $items = (new OrderRepository())->items($order['id'] ?? 0);
        $quantity = 0;
        $grid_id = 0;
        $block_id = 0;

        foreach ($items as $item) {
            $quantity += max(1, absint($item['quantity'] ?? 1));
            if (!$grid_id) {
                $grid_id = absint($item['grid_id'] ?? 0);
            }
            if (!$block_id && !empty($item['block_id'])) {
                $block_id = absint($item['block_id']);
            }
        }

        return [
            'amount' => number_format((float) ($order['total'] ?? 0), 2, '.', ''),
            'currency' => Currency::code($order['currency'] ?? 'USD'),
            'quantity' => $quantity,
            'order_id' => absint($order['id'] ?? 0),
            'order_key' => (string) ($order['order_key'] ?? ''),
            'user_id' => absint($order['user_id'] ?? 0),
            'grid_id' => $grid_id,
            'block_id' => $block_id,
        ];
    }

    private function settings() {
        $stored_settings = get_option('mds3_settings', []);

        return wp_parse_args(is_array($stored_settings) ? $stored_settings : [], SettingsSchema::defaults());
    }
}
