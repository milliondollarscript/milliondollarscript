<?php
/**
 * Customer-scoped order data used by the stable extension API.
 *
 * @package MillionDollarScript\V3\Orders
 */

namespace MillionDollarScript\V3\Orders;

use MillionDollarScript\V3\Media\PlacementRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class CustomerOrderService {

    /**
     * Return a bounded page of orders owned by a verified principal.
     *
     * Offset mode preserves the early-alpha exact-total contract. Cursor mode
     * is opt-in, omits the unbounded exact count unless include_total is true,
     * and returns a stable next_cursor token.
     *
     * @return array|\WP_Error
     */
    public function query(array $principal, array $args = []) {
        $owner = $this->owner_args($principal);
        if (is_wp_error($owner)) {
            return $owner;
        }

        $limit = min(100, max(1, absint($args['limit'] ?? 20)));
        $offset = max(0, absint($args['offset'] ?? 0));
        $allowed = [
            'status',
            'statuses',
            'grid_id',
            'payment_state',
            'upload_state',
            'placement_state',
            'expiration_state',
            'action_required',
            'date_from',
            'date_to',
            'search',
            'orderby',
            'order',
        ];
        $query = array_intersect_key($args, array_flip($allowed));
        $query = array_merge($query, $owner, ['limit' => $limit]);
        $orders = new OrderRepository();

        $cursor_mode = 'cursor' === sanitize_key((string) ($args['pagination'] ?? '')) || array_key_exists('cursor', $args);
        if ($cursor_mode) {
            $cursor = $this->decode_cursor((string) ($args['cursor'] ?? ''), (string) ($query['order'] ?? 'desc'));
            if (is_wp_error($cursor)) {
                return $cursor;
            }
            $page = $orders->cursor_page(array_merge($query, ['cursor_id' => $cursor]));
            $result = [
                'items' => array_map([$this, 'payload'], $page['items']),
                'limit' => $limit,
                'has_more' => (bool) $page['has_more'],
                'next_cursor' => $page['has_more'] && $page['next_id']
                    ? $this->encode_cursor($page['next_id'], (string) ($query['order'] ?? 'desc'))
                    : '',
            ];
            if (!empty($args['include_total'])) {
                $result['total'] = $orders->count($query);
            }

            return $result;
        }

        $query['offset'] = $offset;

        return [
            'items' => array_map([$this, 'payload'], $orders->query($query)),
            'total' => $orders->count($query),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /** @return int|\WP_Error */
    private function decode_cursor($cursor, $order) {
        $cursor = trim((string) $cursor);
        if ('' === $cursor) {
            return 0;
        }

        $padding = (4 - (strlen($cursor) % 4)) % 4;
        $decoded = base64_decode(strtr($cursor . str_repeat('=', $padding), '-_', '+/'), true);
        $payload = is_string($decoded) ? json_decode($decoded, true) : null;
        $expected_order = 'asc' === strtolower((string) $order) ? 'asc' : 'desc';
        if (!is_array($payload) || 1 !== absint($payload['v'] ?? 0) || $expected_order !== ($payload['order'] ?? '') || !absint($payload['id'] ?? 0)) {
            return new \WP_Error('million_dollar_script_invalid_order_cursor', __('The order cursor is invalid or no longer matches this sort.', 'million-dollar-script'));
        }

        return absint($payload['id']);
    }

    private function encode_cursor($id, $order): string {
        $payload = wp_json_encode([
            'v' => 1,
            'id' => absint($id),
            'order' => 'asc' === strtolower((string) $order) ? 'asc' : 'desc',
        ]);

        return rtrim(strtr(base64_encode((string) $payload), '+/', '-_'), '=');
    }

    /**
     * Find an order only when it belongs to the verified principal.
     *
     * @return array|\WP_Error|null
     */
    public function find($order_id, array $principal) {
        $owner = $this->owner_args($principal);
        if (is_wp_error($owner)) {
            return $owner;
        }

        $rows = (new OrderRepository())->query(array_merge($owner, [
            'order_id' => absint($order_id),
            'limit' => 1,
        ]));

        return $rows ? $this->payload($rows[0], true) : null;
    }

    /**
     * Return the internal order only after applying principal ownership.
     *
     * This method is for core facades that need to delegate a protected action.
     *
     * @return array|\WP_Error|null
     */
    public function internal_order($order_id, array $principal) {
        $owner = $this->owner_args($principal);
        if (is_wp_error($owner)) {
            return $owner;
        }

        $rows = (new OrderRepository())->query(array_merge($owner, [
            'order_id' => absint($order_id),
            'limit' => 1,
        ]));
        if (!$rows) {
            return null;
        }

        return (new OrderRepository())->find(absint($rows[0]['id'] ?? 0));
    }

    public function renewal_eligibility($order_id, array $principal) {
        $order = $this->internal_order($order_id, $principal);
        if (is_wp_error($order) || !$order) {
            return $order ?: null;
        }

        return (new OrderRenewal())->eligibility($order);
    }

    public function start_renewal($order_id, array $principal, array $context = []) {
        $order = $this->internal_order($order_id, $principal);
        if (is_wp_error($order)) {
            return $order;
        }
        if (!$order) {
            return new \WP_Error('million_dollar_script_order_not_found', __('Placement could not be found.', 'million-dollar-script'));
        }

        $context['source'] = sanitize_key((string) ($context['source'] ?? 'extension-workspace')) ?: 'extension-workspace';

        $result = (new OrderRenewal())->start($order, $context);
        if (is_wp_error($result)) {
            return $result;
        }

        $refreshed = $this->find($order_id, $principal);

        return [
            'order' => is_array($refreshed) ? $refreshed : null,
            'checkout' => $this->checkout_payload(is_array($result['checkout'] ?? null) ? $result['checkout'] : []),
            'eligibility' => is_array($result['eligibility'] ?? null) ? $result['eligibility'] : [],
        ];
    }

    public function start_checkout($order_id, array $principal) {
        $order = $this->internal_order($order_id, $principal);
        if (is_wp_error($order)) {
            return $order;
        }
        if (!$order) {
            return new \WP_Error('million_dollar_script_order_not_found', __('Placement could not be found.', 'million-dollar-script'));
        }

        $status = sanitize_key((string) ($order['status'] ?? ''));
        if (in_array($status, ['paid', 'completed', 'cancelled', 'failed', 'refunded', 'expired', 'denied', 'deleted'], true)) {
            return new \WP_Error('million_dollar_script_checkout_unavailable', __('Payment is not available for this placement.', 'million-dollar-script'));
        }

        return $this->checkout_payload((new CheckoutRouter())->payload($order));
    }

    private function payload(array $order, $include_details = false) {
        $metadata = $this->metadata($order);
        $order_id = absint($order['id'] ?? 0);
        $payload = [
            'id' => $order_id,
            'status' => sanitize_key((string) ($order['status'] ?? '')),
            'currency' => sanitize_text_field((string) ($order['currency'] ?? '')),
            'subtotal' => (float) ($order['subtotal'] ?? 0),
            'total' => (float) ($order['total'] ?? 0),
            'commerce_provider' => sanitize_key((string) ($order['commerce_provider'] ?? 'standalone')) ?: 'standalone',
            'grid_ids' => array_values(array_filter(array_map('absint', explode(',', (string) ($order['grid_ids'] ?? ''))))),
            'grid_titles' => array_values(array_filter(array_map('trim', explode(',', (string) ($order['grid_titles'] ?? ''))))),
            'placement_count' => absint($order['placement_count'] ?? 0),
            'upload_count' => absint($order['upload_count'] ?? 0),
            'active_placement_count' => absint($order['active_placement_count'] ?? 0),
            'retained_inventory_count' => absint($order['retained_inventory_count'] ?? 0),
            'term_started_at' => sanitize_text_field((string) ($metadata['term_started_at'] ?? '')),
            'expires_at' => sanitize_text_field((string) ($metadata['expires_at'] ?? '')),
            'duration_days' => absint($metadata['duration_days'] ?? ($metadata['package']['duration_days'] ?? 0)),
            'created_at' => sanitize_text_field((string) ($order['created_at'] ?? '')),
            'updated_at' => sanitize_text_field((string) ($order['updated_at'] ?? '')),
        ];

        if ($include_details) {
            $payload['items'] = $this->items($order_id);
            $payload['placements'] = $this->placements($order_id);
            $payload['history'] = $this->history($order, $metadata);
            $payload['renewal'] = (new OrderRenewal())->eligibility($order);
        }

        $filtered = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/customer/order/payload', $payload, $order, $include_details);

        return is_array($filtered) ? $filtered : $payload;
    }

    private function items($order_id) {
        $items = [];
        foreach ((new OrderRepository())->items($order_id) as $item) {
            $metadata = json_decode((string) ($item['metadata'] ?? ''), true);
            $items[] = [
                'id' => absint($item['id'] ?? 0),
                'grid_id' => absint($item['grid_id'] ?? 0),
                'block_id' => absint($item['block_id'] ?? 0),
                'placement_id' => absint($item['placement_id'] ?? 0),
                'item_type' => sanitize_key((string) ($item['item_type'] ?? '')),
                'quantity' => max(1, absint($item['quantity'] ?? 1)),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'total' => (float) ($item['total'] ?? 0),
                'rect' => [
                    'x' => absint($metadata['x'] ?? 0),
                    'y' => absint($metadata['y'] ?? 0),
                    'width' => absint($metadata['width'] ?? 0),
                    'height' => absint($metadata['height'] ?? 0),
                ],
            ];
        }

        return $items;
    }

    private function placements($order_id) {
        $placements = [];
        foreach ((new PlacementRepository())->for_order($order_id) as $placement) {
            $attachment_id = absint($placement['attachment_id'] ?? 0);
            $placements[] = [
                'id' => absint($placement['id'] ?? 0),
                'grid_id' => absint($placement['grid_id'] ?? 0),
                'attachment_id' => $attachment_id,
                'image_url' => $attachment_id ? esc_url_raw((string) wp_get_attachment_image_url($attachment_id, 'large')) : '',
                'x' => absint($placement['x'] ?? 0),
                'y' => absint($placement['y'] ?? 0),
                'width' => absint($placement['width'] ?? 0),
                'height' => absint($placement['height'] ?? 0),
                'fit_mode' => sanitize_key((string) ($placement['fit_mode'] ?? 'cover')),
                'link_url' => esc_url_raw((string) ($placement['link_url'] ?? '')),
                'alt_text' => sanitize_text_field((string) ($placement['alt_text'] ?? '')),
                'popup_text' => wp_kses_post((string) ($placement['popup_text'] ?? '')),
                'status' => sanitize_key((string) ($placement['status'] ?? '')),
                'created_at' => sanitize_text_field((string) ($placement['created_at'] ?? '')),
                'updated_at' => sanitize_text_field((string) ($placement['updated_at'] ?? '')),
            ];
        }

        return $placements;
    }

    private function history(array $order, array $metadata) {
        $history = [[
            'type' => 'created',
            'created_at' => sanitize_text_field((string) ($order['created_at'] ?? '')),
        ]];

        foreach (is_array($metadata['status_events'] ?? null) ? $metadata['status_events'] : [] as $event) {
            $history[] = [
                'type' => 'status',
                'status' => sanitize_key((string) ($event['status'] ?? '')),
                'source' => sanitize_key((string) ($event['source'] ?? '')),
                'created_at' => sanitize_text_field((string) ($event['created_at'] ?? '')),
            ];
        }
        foreach (is_array($metadata['placement_events'] ?? null) ? $metadata['placement_events'] : [] as $event) {
            $history[] = [
                'type' => 'placement',
                'action' => sanitize_key((string) ($event['action'] ?? '')),
                'source' => sanitize_key((string) ($event['source'] ?? '')),
                'grid_id' => absint($event['grid_id'] ?? 0),
                'created_at' => sanitize_text_field((string) ($event['created_at'] ?? '')),
            ];
        }
        foreach (is_array($metadata['renewal_terms'] ?? null) ? $metadata['renewal_terms'] : [] as $term) {
            $history[] = [
                'type' => 'renewal_term',
                'started_at' => sanitize_text_field((string) ($term['term_started_at'] ?? '')),
                'expires_at' => sanitize_text_field((string) ($term['expires_at'] ?? '')),
                'created_at' => sanitize_text_field((string) ($term['ended_at'] ?? '')),
            ];
        }

        usort($history, static function(array $left, array $right): int {
            return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
        });

        return $history;
    }

    private function metadata(array $order) {
        $metadata = json_decode((string) ($order['metadata'] ?? ''), true);

        return is_array($metadata) ? $metadata : [];
    }

    private function checkout_payload(array $checkout) {
        return [
            'provider' => sanitize_key((string) ($checkout['provider'] ?? '')),
            'checkout_url' => esc_url_raw((string) ($checkout['checkout_url'] ?? '')),
            'after_upload_url' => esc_url_raw((string) ($checkout['after_upload_url'] ?? '')),
            'provider_order_id' => sanitize_text_field((string) ($checkout['provider_order_id'] ?? '')),
            'error' => sanitize_text_field((string) ($checkout['error'] ?? '')),
        ];
    }

    /** @return array|\WP_Error */
    private function owner_args(array $principal) {
        $user_id = absint($principal['user_id'] ?? 0);
        $email = sanitize_email((string) ($principal['email'] ?? ''));
        if (!$user_id && !$email) {
            return new \WP_Error('million_dollar_script_principal_required', __('Verified placement access is required.', 'million-dollar-script'));
        }

        return [
            'owner_user_id' => $user_id,
            'owner_email' => $email,
        ];
    }
}
