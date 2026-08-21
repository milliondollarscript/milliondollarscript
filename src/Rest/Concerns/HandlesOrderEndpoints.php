<?php
/**
 * Order REST endpoints and payload helpers.
 *
 * @package MillionDollarScript\V3\Rest
 */

namespace MillionDollarScript\V3\Rest\Concerns;

use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Orders\OrderRepository;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesOrderEndpoints {

    public function orders(\WP_REST_Request $request) {
        $limit = min(100, max(1, absint($request->get_param('per_page') ?: 50)));

        return array_map([$this, 'order_payload'], (new OrderRepository())->recent($limit));
    }

    public function order(\WP_REST_Request $request) {
        $repo = new OrderRepository();
        $order = $repo->find($request['id']);
        if (!$order) {
            return new \WP_Error('mds3_order_not_found', __('Order not found.', 'million-dollar-script'), ['status' => 404]);
        }

        $payload = $this->order_payload($order);
        $payload['items'] = $repo->items($request['id']);
        $payload['placement_rect'] = $repo->item_rect($request['id']);

        return $payload;
    }

    public function update_order_state(\WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        $status = sanitize_key($params['status'] ?? '');
        $allowed = ['reserved', 'pending_payment', 'paid', 'cancelled', 'failed', 'refunded', 'expired', 'denied', 'deleted'];
        if (!in_array($status, $allowed, true)) {
            return new \WP_Error('mds3_invalid_order_status', __('Unsupported order status.', 'million-dollar-script'), ['status' => 400]);
        }

        $repo = new OrderRepository();
        $order = $repo->find($request['id']);
        if (!$order) {
            return new \WP_Error('mds3_order_not_found', __('Order not found.', 'million-dollar-script'), ['status' => 404]);
        }

        if (!Payments::mark_source_status('mds-grid', $request['id'], $status, ['source' => 'rest'])) {
            return new \WP_Error('mds3_order_status_update_failed', __('Order status could not be updated.', 'million-dollar-script'), ['status' => 500]);
        }

        if ('paid' === $status) {
            Payments::complete_provider_order_for_mds_order(absint($request['id']));
        }

        return $this->order($request);
    }

    public function order_items(\WP_REST_Request $request) {
        $order = (new OrderRepository())->find($request['id']);
        if (!$order) {
            return new \WP_Error('mds3_order_not_found', __('Order not found.', 'million-dollar-script'), ['status' => 404]);
        }

        return (new OrderRepository())->items($request['id']);
    }

    private function order_payload(array $order) {
        $order['id'] = absint($order['id'] ?? 0);
        $order['user_id'] = absint($order['user_id'] ?? 0);
        $order['subtotal'] = (float) ($order['subtotal'] ?? 0);
        $order['total'] = (float) ($order['total'] ?? 0);
        $metadata = json_decode((string) ($order['metadata'] ?? ''), true);
        $order['metadata'] = is_array($metadata) ? $metadata : [];

        return $order;
    }
}
