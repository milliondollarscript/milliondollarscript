<?php
/**
 * Stable order access for extensions.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Core;

use MillionDollarScript\V3\Orders\CustomerOrderService;
use MillionDollarScript\V3\Orders\OrderRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class Orders {

    /** @return int|\WP_Error */
    public static function create(array $items, array $data = []) {
        return (new OrderRepository())->create($items, $data);
    }

    public static function find($id): ?array {
        $order = (new OrderRepository())->find(absint($id));
        return is_array($order) ? $order : null;
    }

    public static function items($id): array {
        return (new OrderRepository())->items(absint($id));
    }

    /**
     * Return customer-safe orders for a verified user ID and/or email address.
     *
     * Callers must establish the principal before passing it here. Raw order
     * keys and customer email addresses are not included in the result. Pass
     * pagination=cursor for bounded keyset navigation; exact totals are then
     * omitted unless include_total is explicitly requested.
     *
     * @return array|\WP_Error
     */
    public static function query_for_principal(array $principal, array $args = []) {
        return (new CustomerOrderService())->query($principal, $args);
    }

    /**
     * Return customer-safe order detail when the principal owns the order.
     *
     * @return array|\WP_Error|null
     */
    public static function find_for_principal($id, array $principal) {
        return (new CustomerOrderService())->find(absint($id), $principal);
    }

    /** @return array|\WP_Error|null */
    public static function renewal_eligibility_for_principal($id, array $principal) {
        return (new CustomerOrderService())->renewal_eligibility(absint($id), $principal);
    }

    /** @return array|\WP_Error */
    public static function start_renewal_for_principal($id, array $principal, array $context = []) {
        return (new CustomerOrderService())->start_renewal(absint($id), $principal, $context);
    }

    /**
     * Create or resume a provider checkout after principal ownership is proven.
     *
     * @return array|\WP_Error
     */
    public static function start_checkout_for_principal($id, array $principal) {
        return (new CustomerOrderService())->start_checkout(absint($id), $principal);
    }

    /**
     * Update extension-owned order metadata without exposing lifecycle fields.
     *
     * @return array|\WP_Error|null
     */
    public static function update_metadata($id, array $metadata) {
        $updated = (new OrderRepository())->update(absint($id), ['metadata' => $metadata]);
        return is_array($updated) || is_wp_error($updated) ? $updated : null;
    }
}
