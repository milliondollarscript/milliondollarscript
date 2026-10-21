<?php
/**
 * Where a saved ad sends the customer, on the sites a fresh install actually is.
 *
 * A fresh site takes payment by hand. It has no payment page, and until its
 * owner adds one it has no page for the customer's own order either. The save
 * used to answer that with an empty address, and the browser was sent to a blank
 * page with the ad already booked - reproduced twice by a tester on a reset
 * site (llm-task bf3e102f). What the router owes a customer is one of two
 * answers: a page the site really has, or no address at all, which leaves them
 * where they are with the form's own confirmation on screen.
 *
 * Run inside WordPress:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/order-destination-fixture.php --allow-root
 */

use MillionDollarScript\V3\Orders\CheckoutRouter;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    echo "This fixture must run inside a WordPress root (wp eval-file).\n";
    exit(1);
}

global $wpdb;

$fails = [];
$check = static function ($ok, $message) use (&$fails) {
    if (!$ok) {
        $fails[] = $message;
    }
};

// Page roles and settings belong to the site. A fixture that moves them leaves
// the next session reading the wrong page, so both are put back at the end.
$guarded_options = array('mds3_page_upload_id', 'mds3_page_thank-you_id');
$original_options = array();
foreach ($guarded_options as $name) {
    $original_options[$name] = get_option($name);
}

$order_id = (new OrderRepository())->create(array(), array(
    'email' => 'order-destination@example.test',
    'status' => 'pending_payment',
    'block_status' => 'reserved',
));
if (is_wp_error($order_id) || !absint($order_id)) {
    throw new RuntimeException('Could not create the fixture order: ' . (is_wp_error($order_id) ? $order_id->get_error_message() : 'no id'));
}
$order = (new OrderRepository())->find(absint($order_id));

$order_page_id = 0;

$make_page = static function ($title) {
    $post_id = wp_insert_post(array(
        'post_content' => '',
        'post_name' => sanitize_title('mds3-fixture-' . $title),
        'post_status' => 'publish',
        'post_title' => 'MDS3 Fixture ' . $title,
        'post_type' => 'page',
    ), true);

    return is_wp_error($post_id) ? 0 : absint($post_id);
};

$router = new CheckoutRouter();
$no_payment_page = array('checkout_url' => '');
$settings = array('checkout-url' => '', 'thank-you-page' => '', 'confirm-orders' => 'no');

try {
    if (empty($order['order_key'])) {
        $check(false, 'the fixture order has no key to name it with');
    }

    // 1. The site has its customer order page: that is the destination, and it
    // arrives with the order in the address so the page knows whose order to show.
    $order_page_id = $make_page('order destination');
    if (!$order_page_id) {
        throw new RuntimeException('Could not create the fixture order page.');
    }
    update_option('mds3_page_upload_id', $order_page_id);
    $to_order_page = (string) $router->after_upload_url($order, $no_payment_page, $settings, false);
    $page_url = (string) get_permalink($order_page_id);
    $check(
        '' !== $to_order_page && false !== strpos($to_order_page, $page_url),
        'a save on a site with an order page did not send the customer there: ' . var_export($to_order_page, true)
    );
    $check(
        false !== strpos($to_order_page, 'mds3_order_id=' . absint($order_id)),
        'the destination did not carry the order it belongs to: ' . $to_order_page
    );

    // 2. The site has nothing to send them to. This is the state that produced
    // the blank page: the only safe answer is no address at all, so the browser
    // keeps the customer on the grid with the form's own message in front of them.
    update_option('mds3_page_upload_id', 0);
    $nowhere = (string) $router->after_upload_url($order, $no_payment_page, $settings, false);
    $check(
        '' === $nowhere,
        'a save with no order page invented a destination instead of leaving the customer where they are: ' . var_export($nowhere, true)
    );

    // 3. A reservation on a site with no payment step still ends somewhere the
    // site has, because the browser moves on the promise of the reservation.
    $order_page_id = $order_page_id ?: $make_page('order destination again');
    update_option('mds3_page_upload_id', $order_page_id);
    $reserved = $router->destination_after_form($order, $no_payment_page, $settings, 'reserved', false);
    $check(
        '' !== (string) $reserved && false !== strpos((string) $reserved, (string) get_permalink($order_page_id)),
        'a reservation on a site with no payment step left the browser with no address to move to'
    );

    // 4. No answer may be a page this site does not have. That is the whole
    // contract: every destination is a real page, or there is no destination.
    foreach (array('reserved', 'pending_payment', 'paid', 'completed', 'denied', 'expired') as $status) {
        foreach (array(true, false) as $while_editing) {
            $answer = (string) $router->destination_after_form($order, $no_payment_page, $settings, $status, $while_editing);
            if ('' === $answer) {
                continue;
            }
            $check(
                0 === strpos($answer, (string) home_url('/')),
                $status . ' offered a destination outside this site: ' . $answer
            );
            $path = (string) wp_parse_url($answer, PHP_URL_PATH);
            // A site on plain permalinks names its pages with ?page_id=, where there is no path
            // to look a page up by. Resolve the page the URL points at either way.
            $query = array();
            parse_str((string) wp_parse_url($answer, PHP_URL_QUERY), $query);
            $named_id = isset($query['page_id']) ? absint($query['page_id']) : 0;
            $by_path = $named_id ? get_post($named_id) : get_page_by_path(ltrim($path, '/'));
            $check(
                $by_path instanceof \WP_Post && 'publish' === $by_path->post_status,
                $status . ' named a page this site does not have: ' . $answer
            );
        }
    }
} finally {
    foreach ($guarded_options as $name) {
        // An option that did not exist before stays removed: leaving a fixture
        // value behind is how a later session ends up pointing at a fixture page.
        if (false === $original_options[$name] || null === $original_options[$name]) {
            delete_option($name);
            continue;
        }
        update_option($name, $original_options[$name]);
    }
    if ($order_page_id) {
        wp_delete_post($order_page_id, true);
    }
    $wpdb->delete(DB::table('order_items'), array('order_id' => absint($order_id)));
    $wpdb->delete(DB::table('orders'), array('id' => absint($order_id)));
}

if ($fails) {
    fwrite(STDERR, implode("\n", $fails) . "\n");
    exit(1);
}

echo "MDS3 order destination fixture passed.\n";
