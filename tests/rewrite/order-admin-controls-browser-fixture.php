<?php
/**
 * WP-CLI fixture for the Orders admin control browser regression.
 *
 * @package MillionDollarScript\V3\Tests
 */

use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

$action = sanitize_key((string) getenv('MDS_ORDER_ADMIN_CONTROLS_FIXTURE_ACTION'));
$option = 'mds3_order_admin_controls_fixture_order_id';
$order_id = absint(get_option($option, 0));
$orders = new OrderRepository();

$cleanup = static function ($id) {
    global $wpdb;

    $id = absint($id);
    if (!$id) {
        return;
    }
    $wpdb->delete(DB::table('order_items'), ['order_id' => $id], ['%d']);
    $wpdb->delete(DB::table('orders'), ['id' => $id], ['%d']);
};

if ('cleanup' === $action) {
    $cleanup($order_id);
    delete_option($option);
    echo wp_json_encode(['cleaned' => true]);
    return;
}

if ('status' === $action) {
    $order = $order_id ? $orders->find($order_id) : null;
    echo wp_json_encode([
        'id' => $order_id,
        'status' => is_array($order) ? sanitize_key((string) ($order['status'] ?? '')) : '',
    ]);
    return;
}

$cleanup($order_id);
$email = 'order-admin-controls-' . wp_generate_uuid4() . '@example.test';
$order_id = $orders->create([], [
    'email' => $email,
    'status' => 'pending_payment',
    'currency' => 'USD',
    'total' => 25,
]);
if (is_wp_error($order_id)) {
    throw new RuntimeException('Could not create the Orders admin browser fixture: ' . $order_id->get_error_message());
}

update_option($option, absint($order_id), false);
echo wp_json_encode([
    'email' => $email,
    'order_id' => absint($order_id),
    'url' => admin_url('admin.php?page=mds3-orders&s=' . rawurlencode($email)),
]);
