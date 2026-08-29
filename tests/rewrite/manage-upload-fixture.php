<?php
/**
 * WP-CLI fixture: customer-facing pixel management (list, summary, upload).
 *
 * Covers: the manage list shows per-order pixel details, expired orders do
 * not link back to themselves from the summary, and the grid state only
 * exposes a manage URL to the order owner.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/manage-upload-fixture.php
 */

use MillionDollarScript\V3\Grid\GridAjax;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Pages\PageShortcodes;

if (!defined('ABSPATH')) {
    exit;
}

function mds3_mu_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$mds3_mu_attachment_id = 0;
$mds3_mu_attachment_path = '';
{
    $tmp = wp_tempnam('mds3-mu-' . substr(wp_generate_uuid4(), 0, 8) . '.png');
    file_put_contents($tmp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    $mds3_mu_attachment_id = wp_insert_attachment([
        'post_title' => 'Manage upload fixture pixel',
        'post_mime_type' => 'image/png',
        'guid' => (string) $tmp,
    ], $tmp);
    $mds3_mu_attachment_path = $tmp;
}
mds3_mu_assert($mds3_mu_attachment_id && !is_wp_error($mds3_mu_attachment_id), 'Could not create the fixture attachment.');

$slug = substr(wp_generate_uuid4(), 0, 8);
$user_id = wp_insert_user([
    'user_login' => 'mds3-mu-' . $slug,
    'user_email' => 'mds3-mu-' . $slug . '@example.test',
    'user_pass' => wp_generate_password(24),
]);
mds3_mu_assert(!is_wp_error($user_id), 'Could not create the manage-upload fixture user.');
$other_user_id = wp_insert_user([
    'user_login' => 'mds3-mu-other-' . $slug,
    'user_email' => 'mds3-mu-other-' . $slug . '@example.test',
    'user_pass' => wp_generate_password(24),
]);
mds3_mu_assert(!is_wp_error($other_user_id), 'Could not create the second manage-upload fixture user.');

$grid = (new GridRepository())->create([
    'title' => 'Manage Upload Fixture ' . $slug,
    'width' => 1000,
    'height' => 100,
    'block_width' => 100,
    'block_height' => 10,
    'price_per_block' => 1,
    'currency' => 'USD',
    'status' => 'active',
]);
mds3_mu_assert(!is_wp_error($grid), 'Could not create the manage-upload fixture grid: ' . (is_wp_error($grid) ? $grid->get_error_message() : ''));

$order_repo = new OrderRepository();
$previous_user = get_current_user_id();
wp_set_current_user($user_id);
$paid_order_id = $order_repo->create(
    [['grid_id' => $grid->id(), 'item_type' => 'block', 'quantity' => 1, 'unit_price' => 10, 'total' => 10]],
    ['user_id' => $user_id, 'email' => 'mds3-mu-' . $slug . '@example.test', 'status' => 'paid', 'total' => 10]
);
$expired_order_id = $order_repo->create(
    [['grid_id' => $grid->id(), 'item_type' => 'block', 'quantity' => 1, 'unit_price' => 10, 'total' => 10]],
    ['user_id' => $user_id, 'email' => 'mds3-mu-' . $slug . '@example.test', 'status' => 'expired', 'total' => 10]
);
mds3_mu_assert(!is_wp_error($paid_order_id) && $paid_order_id, 'Could not create the paid fixture order.');
mds3_mu_assert(!is_wp_error($expired_order_id) && $expired_order_id, 'Could not create the expired fixture order.');
wp_set_current_user($previous_user);

$paid_order = $order_repo->find($paid_order_id);
$expired_order = $order_repo->find($expired_order_id);
mds3_mu_assert($paid_order && $expired_order, 'Fixture orders were not persisted.');
mds3_mu_assert(absint($paid_order['user_id']) === $user_id, 'Fixture order user ownership was not recorded.');

$placement_repo = new PlacementRepository();
$paid_placement_id = $placement_repo->create([
    'grid_id' => $grid->id(),
    'order_id' => $paid_order_id,
    'attachment_id' => $mds3_mu_attachment_id,
    'x' => 10,
    'y' => 10,
    'width' => 100,
    'height' => 10,
    'status' => 'active',
    'user_id' => $user_id,
]);
mds3_mu_assert(!is_wp_error($paid_placement_id) && $paid_placement_id, 'Could not create the paid fixture placement.');
$second_placement_id = $placement_repo->create([
    'grid_id' => $grid->id(),
    'order_id' => $paid_order_id,
    'attachment_id' => $mds3_mu_attachment_id,
    'x' => 110,
    'y' => 10,
    'width' => 100,
    'height' => 10,
    'status' => 'active',
    'user_id' => $user_id,
]);
mds3_mu_assert(!is_wp_error($second_placement_id) && $second_placement_id, 'Could not create the second fixture placement.');

try {
    // 1. Manage list: pixel column + manage links.
    wp_set_current_user($user_id);
    unset($_GET['mds3_order_id'], $_GET['mds3_order_key']);
    $list_html = (string) (new PageShortcodes())->render(['type' => 'manage']);
    mds3_mu_assert(false !== strpos($list_html, 'Manage Pixels'), 'Expected the manage list heading.');
    mds3_mu_assert(false !== strpos($list_html, '#' . $paid_order_id), 'Expected the paid order row in the manage list.');
    mds3_mu_assert(false !== strpos($list_html, '#' . $expired_order_id), 'Expected the expired order row in the manage list.');
    mds3_mu_assert(false !== strpos($list_html, '100×10 @ 10,10'), 'Expected the pixel position label in the manage list.');
    mds3_mu_assert(false !== strpos($list_html, '· +1'), 'Expected the extra placement count in the pixel label.');
    mds3_mu_assert(false !== strpos($list_html, 'mds3-pixel-thumb'), 'Expected the pixel thumbnail in the manage list.');
    mds3_mu_assert(false !== strpos($list_html, 'mds3_order_id=' . $paid_order_id), 'Expected a manage link for the paid order.');
    mds3_mu_assert(false !== strpos($list_html, 'mds3_order_key=' . rawurlencode((string) $paid_order['order_key'])), 'Expected the owner manage link to carry the order key.');

    // 2. Expired order: summary panel must not link back to itself.
    $_GET['mds3_order_id'] = (string) $expired_order_id;
    $_GET['mds3_order_key'] = (string) $expired_order['order_key'];
    $expired_html = (string) (new PageShortcodes())->render(['type' => 'manage']);
    mds3_mu_assert(false === strpos($expired_html, 'Manage upload'), 'Expected the expired summary to omit the self-referencing manage link.');
    mds3_mu_assert(false !== strpos($expired_html, 'My pixels'), 'Expected the expired summary to offer a back link to the pixel list.');
    mds3_mu_assert(false !== stripos($expired_html, 'Expired'), 'Expected the expired status label.');

    // 3. Paid order: upload form renders (no fatal) and offers the back link.
    $_GET['mds3_order_id'] = (string) $paid_order_id;
    $_GET['mds3_order_key'] = (string) $paid_order['order_key'];
    $upload_html = (string) (new PageShortcodes())->render(['type' => 'upload']);
    mds3_mu_assert(false !== strpos($upload_html, 'mds3-order-upload-form'), 'Expected the paid order to render the upload form.');
    mds3_mu_assert(false !== strpos($upload_html, 'My pixels'), 'Expected the upload panel to offer a back link to the pixel list.');

    // 4. Order key is the bearer credential: valid key works, invalid key is rejected.
    wp_set_current_user($other_user_id);
    $foreign_html = (string) (new PageShortcodes())->render(['type' => 'upload']);
    mds3_mu_assert(false !== strpos($foreign_html, 'mds3-order-upload-form'), 'Expected a valid order key to grant the upload panel regardless of the logged-in user.');
    $_GET['mds3_order_key'] = wp_generate_uuid4();
    $invalid_html = (string) (new PageShortcodes())->render(['type' => 'upload']);
    mds3_mu_assert(false !== strpos($invalid_html, 'Order could not be verified'), 'Expected an invalid order key to be rejected.');
    mds3_mu_assert(false === strpos($invalid_html, 'mds3-order-upload-form'), 'Expected an invalid order key to never receive the upload form.');
    $_GET['mds3_order_key'] = (string) $paid_order['order_key'];
    wp_set_current_user(0);
    $anonymous_html = (string) (new PageShortcodes())->render(['type' => 'upload']);
    mds3_mu_assert(false !== strpos($anonymous_html, 'mds3-order-upload-form'), 'Expected the bearer key to work for anonymous holders.');
    wp_set_current_user($previous_user);

    // 5. Grid state: owner-scoped orders map + manage_url.
    $ajax = new GridAjax();
    $placement_rows = $placement_repo->for_order($paid_order_id);
    $placement_row = is_array($placement_rows) && $placement_rows ? $placement_rows[0] : [];
    mds3_mu_assert($placement_row, 'Expected the fixture placement to exist.');
    $orders_method = new ReflectionMethod($ajax, 'orders_for_placements');
    $orders_method->setAccessible(true);
    $order_map = $orders_method->invoke($ajax, $placement_rows);
    mds3_mu_assert(isset($order_map[$paid_order_id]) && $order_map[$paid_order_id]['order_key'] === $paid_order['order_key'], 'Expected orders_for_placements to resolve the fixture order.');
    $payload_method = new ReflectionMethod($ajax, 'placement_payload');
    $payload_method->setAccessible(true);
    wp_set_current_user($user_id);
    $owner_payload = $payload_method->invoke($ajax, $placement_row, [], [], $paid_order);
    mds3_mu_assert(
        is_array($owner_payload) && false !== strpos((string) ($owner_payload['manage_url'] ?? ''), 'mds3_order_id=' . $paid_order_id) &&
        false !== strpos((string) ($owner_payload['manage_url'] ?? ''), rawurlencode((string) $paid_order['order_key'])),
        'Expected the owner grid payload to expose the manage URL.'
    );
    wp_set_current_user($other_user_id);
    $foreign_payload = $payload_method->invoke($ajax, $placement_row, [], [], $paid_order);
    mds3_mu_assert(is_array($foreign_payload) && '' === ($foreign_payload['manage_url'] ?? 'missing'), 'Expected non-owner grid payloads to omit the manage URL.');
    wp_set_current_user(0);
    $anonymous_payload = $payload_method->invoke($ajax, $placement_row, [], [], $paid_order);
    mds3_mu_assert(is_array($anonymous_payload) && '' === ($anonymous_payload['manage_url'] ?? 'missing'), 'Expected anonymous grid payloads to omit the manage URL.');
    wp_set_current_user($previous_user);
} finally {
    unset($_GET['mds3_order_id'], $_GET['mds3_order_key']);
    (new GridRepository())->delete($grid->id());
    if ($mds3_mu_attachment_path) {
        @unlink($mds3_mu_attachment_path);
    }
    if ($mds3_mu_attachment_id) {
        wp_delete_attachment($mds3_mu_attachment_id, true);
    }
    wp_delete_user($other_user_id);
    wp_delete_user($user_id);
}

echo "Manage upload fixture passed.\n";
