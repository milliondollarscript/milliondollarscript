<?php
/**
 * Seed a demo advertiser with a paid order + two pixels for eyeballing the
 * customer pixel-management UI locally. Idempotent per demo user.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/seed-demo-pixels.php
 */

use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\OrderRepository;

if (!defined('ABSPATH')) {
    exit;
}

$user = get_user_by('login', 'mds3-demo');
if ($user) {
    $user_id = (int) $user->ID;
} else {
    $user_id = wp_insert_user([
        'user_login' => 'mds3-demo',
        'user_email' => 'mds3-demo@example.test',
        'user_pass' => 'mds3-demo',
    ]);
    if (is_wp_error($user_id)) {
        throw new RuntimeException('Could not create the demo user: ' . $user_id->get_error_message());
    }
    $user_id = (int) $user_id;

$grid = (new GridRepository())->create([
    'title' => 'Demo Grid',
    'width' => 400,
    'height' => 40,
    'block_width' => 20,
    'block_height' => 5,
    'price_per_block' => 5,
    'currency' => 'USD',
    'status' => 'active',
]);
if (is_wp_error($grid)) {
    throw new RuntimeException('Could not create the demo grid.');
}

$page_id = wp_insert_post([
    'post_title' => 'Demo Grid',
    'post_name' => 'demo-grid',
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_content' => '[mds_grid id="' . $grid->id() . '"]',
]);
if (is_wp_error($page_id)) {
    throw new RuntimeException('Could not create the demo grid page.');
}

$order_id = (new OrderRepository())->create(
    [['grid_id' => $grid->id(), 'item_type' => 'block', 'quantity' => 1, 'unit_price' => 10, 'total' => 10]],
    ['user_id' => $user_id, 'email' => 'mds3-demo@example.test', 'status' => 'paid', 'total' => 10]
);
if (is_wp_error($order_id)) {
    throw new RuntimeException('Could not create the demo order.');
}
$order = (new OrderRepository())->find($order_id);

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
$tmp = wp_tempnam('mds3-demo-pixel.png');
file_put_contents($tmp, $png);
$attachment_id = wp_insert_attachment([
    'post_title' => 'Demo pixel',
    'post_mime_type' => 'image/png',
    'guid' => (string) $tmp,
], $tmp);
if (is_wp_error($attachment_id)) {
    throw new RuntimeException('Could not create the demo attachment.');
}

$placement_repo = new PlacementRepository();
foreach ([['x' => 20, 'y' => 5], ['x' => 40, 'y' => 5]] as $pos) {
    $placement_repo->create([
        'grid_id' => $grid->id(),
        'order_id' => $order_id,
        'attachment_id' => $attachment_id,
        'x' => $pos['x'],
        'y' => $pos['y'],
        'width' => 20,
        'height' => 5,
        'status' => 'active',
        'user_id' => $user_id,
    ]);
}

printf(
    "Demo seeded. login=mds3-demo pass=mds3-demo grid_id=%d order_id=%d order_key=%s manage=http://localhost:8081/manage-pixels/ grid=http://localhost:8081/demo-grid/\n",
    $grid->id(),
    $order_id,
    (string) $order['order_key']
);
