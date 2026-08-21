<?php
/**
 * Frontend page order, URL, and legacy shortcode helpers.
 *
 * @package MillionDollarScript\V3\Pages
 */

namespace MillionDollarScript\V3\Pages\Concerns;

use MillionDollarScript\V3\Orders\CheckoutRouter;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Pages\PageRepository;
use MillionDollarScript\V3\Commerce\Currency;

if (!defined('ABSPATH')) {
    exit;
}

trait ResolvesPageOrders {

    private function request_order() {
        if (empty($_GET['mds3_order_id']) || empty($_GET['mds3_order_key'])) {
            return null;
        }

        $order_id = absint($_GET['mds3_order_id']);
        $order_key = sanitize_text_field(wp_unslash($_GET['mds3_order_key']));
        $order = (new OrderRepository())->find($order_id);

        return $order && hash_equals((string) ($order['order_key'] ?? ''), (string) $order_key) ? $order : null;
    }

    private function customer_order_url(array $order) {
        $page_id = absint(get_option('mds3_page_upload_id', 0));
        $url = $page_id ? get_permalink($page_id) : '';
        if (!$url) {
            $grid_id = $this->order_grid_id($order);
            $url = $grid_id ? $this->grid_url($grid_id) : home_url('/');
        }

        return add_query_arg([
            'mds3_order_id' => absint($order['id'] ?? 0),
            'mds3_order_key' => rawurlencode((string) ($order['order_key'] ?? '')),
        ], $url);
    }

    private function account_url() {
        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $url = esc_url_raw((string) ($settings['account-page'] ?? ''));
        if (!$url) {
            $url = esc_url_raw((string) ($settings['login-page'] ?? ''));
        }
        if (!$url) {
            $url = admin_url('profile.php');
        }

        $filtered = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/account/url', $url, $settings);

        return esc_url_raw(is_scalar($filtered) ? (string) $filtered : $url) ?: $url;
    }

    private function checkout_landing_url($fallback) {
        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $url = esc_url_raw((string) ($settings['checkout-url'] ?? ''));
        if (!$url || false !== strpos($url, '%')) {
            $url = esc_url_raw((string) $fallback);
        }

        $filtered = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/checkout/landing/url', $url, $settings, $fallback);

        return esc_url_raw(is_scalar($filtered) ? (string) $filtered : $url) ?: $url;
    }

    private function order_grid_id(array $order) {
        foreach ((new OrderRepository())->items(absint($order['id'] ?? 0)) as $item) {
            if (!empty($item['grid_id'])) {
                return absint($item['grid_id']);
            }
        }

        return 0;
    }

    private function order_payment_url(array $order) {
        if (!$this->order_needs_payment($order)) {
            return '';
        }

        if ('standalone' === (sanitize_key((string) ($order['commerce_provider'] ?? 'standalone')) ?: 'standalone')) {
            return (new CheckoutRouter())->standalone_checkout_url($order);
        }

        $payload = (new CheckoutRouter())->payload($order);

        return esc_url_raw($payload['checkout_url'] ?? '');
    }

    private function order_needs_payment(array $order) {
        $status = sanitize_key((string) ($order['status'] ?? ''));

        return !in_array($status, ['paid', 'completed', 'cancelled', 'canceled', 'failed', 'refunded', 'expired', 'denied', 'deleted'], true);
    }

    private function money(array $order) {
        return Currency::format((float) ($order['total'] ?? 0), $order['currency'] ?? '');
    }

    private function status_label($status) {
        $status = sanitize_key((string) $status);
        if (!$status) {
            return __('Unknown', 'million-dollar-script');
        }

        return ucwords(str_replace('_', ' ', $status));
    }

    private function grid_url($grid_id) {
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

    private function normalize_legacy_atts(array $atts) {
        $mapped = [];
        $aliases = [
            'milliondollarscript_type' => 'type',
            'mds_type' => 'type',
            'display' => 'type',
            'display_type' => 'type',
            'grid_size' => 'type',
            'milliondollarscript_id' => 'grid_id',
            'mds_id' => 'grid_id',
            'banner_id' => 'grid_id',
            'bid' => 'grid_id',
            'BID' => 'grid_id',
            'id' => 'grid_id',
            'w' => 'width',
            'h' => 'height',
            'alignment' => 'align',
            'readonly' => 'read_only',
        ];

        foreach ($atts as $key => $value) {
            $mapped[$aliases[$key] ?? $key] = $value;
        }

        $mapped['type'] = sanitize_key($mapped['type'] ?? 'grid');
        $type_aliases = [
            'users' => 'order',
            'checkout' => 'confirm-order',
            'confirm' => 'confirm-order',
            'write_ad' => 'write-ad',
            'thankyou' => 'thank-you',
        ];
        $mapped['type'] = $type_aliases[$mapped['type']] ?? $mapped['type'];
        if (!PageRepository::is_valid_type($mapped['type'])) {
            $mapped['type'] = 'grid';
        }
        if (empty($mapped['grid_id'])) {
            $mapped['grid_id'] = absint($mapped['id'] ?? 0);
        }
        if (!empty($mapped['width']) && empty($mapped['height']) && preg_match('/^\d+(?:\.\d+)?(?:px|rem|em|vh|vw|%)$/', (string) $mapped['width'])) {
            $mapped['height'] = $mapped['width'];
        }

        return $mapped;
    }
}
