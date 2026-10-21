<?php
/**
 * WP-CLI fixture: saving an ad always leaves the customer somewhere they can act.
 *
 * Why this exists: a site that takes payment by hand - which is the default for a fresh
 * install, and what the playground and Delphi16's first runs will hit - has no Checkout URL.
 * The save then said "Ad saved." and left the customer on the grid with no route to the order
 * they had just booked, while the order sat awaiting payment (llm-task e46ff359, and part of
 * the fresh-install path in 5a45f802). Ryan's report was "it just says Ad saved, does not
 * redirect to the next page like it should."
 *
 * The rule now: a payment page wins when the site has one; otherwise an order that still owes
 * payment goes to the customer's own order page; and where the site has no order page the
 * customer stays put rather than being sent to a page that does not exist. Being dropped on a
 * grid - which is what the email URL builder falls back to - is the dead end this closes, so
 * the two builders are deliberately different.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/ad-save-destination-fixture.php
 */

use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Grid\GridAjax;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$fails = [];
$check = function ($cond, $msg) use (&$fails) {
    if (!$cond) {
        $fails[] = $msg;
    }
};

$call = static function ($scope, $class, $method, array $args = []) {
    $method = new ReflectionMethod($class, $method);
    $method->setAccessible(true);

    return $method->invokeArgs($scope, $args);
};

$observed = [];
$page_option = 'mds3_page_upload_id';
$original_page = get_option($page_option, 0);
$thank_you_option = 'mds3_page_thank-you_id';
$original_thank_you = get_option($thank_you_option, 0);

/**
 * An order awaiting payment, as the manual-payment path leaves it.
 */
$order_id = (new OrderRepository())->create([], [
    'email' => 'ad-save-destination@example.test',
    'status' => 'pending_payment',
    'block_status' => 'reserved',
]);
if (is_wp_error($order_id) || !absint($order_id)) {
    throw new RuntimeException('Could not create the fixture order: ' . (is_wp_error($order_id) ? $order_id->get_error_message() : 'no id'));
}
$order = (new OrderRepository())->find($order_id);

$cleanup = static function () use ($wpdb, $order_id, $page_option, $original_page, $thank_you_option, $original_thank_you) {
    $wpdb->delete(DB::table('order_items'), ['order_id' => $order_id]);
    $wpdb->delete(DB::table('orders'), ['id' => $order_id]);
    update_option($page_option, $original_page, false);
    update_option($thank_you_option, $original_thank_you, false);
};

$ajax = new GridAjax();
$decide = static function (array $the_order, array $checkout, $manage = false, $unpaid = true) use ($ajax, $call) {
    return (string) $call($ajax, GridAjax::class, 'post_save_redirect', [$the_order, $checkout, $manage, $unpaid]);
};

if (empty($order['order_key'])) {
    $check(false, 'the fixture order has no key to send the customer with');
}

/**
 * 1. A site with a payment page behaves exactly as before.
 */
$with_checkout = $decide($order, ['checkout_url' => 'https://example.test/pay?amount=10']);
$observed['checkout_configured'] = $with_checkout;
$check(
    'https://example.test/pay?amount=10' === $with_checkout,
    'a configured payment page is no longer where the customer is sent: ' . $with_checkout
);

/**
 * 2. No payment page, order awaiting payment: the customer's own order page, with the order
 *    in the query so it opens on their order.
 */
$order_page_id = absint(get_option($page_option, 0));
$check($order_page_id > 0, 'this development site has no order/upload page to aim at');
$to_order = $decide($order, ['checkout_url' => '']);
$observed['no_checkout_goes_to'] = $to_order;
$check(
    false !== strpos($to_order, 'mds3_order_id=' . $order_id),
    'an unpaid order was not sent to the customer order page: ' . $to_order
);
$check(
    '' !== $to_order && false === strpos($to_order, 'pay?'),
    'the manual-payment path offered no destination, or invented a payment page: ' . $to_order
);

/**
 * 3. The two destinations we refuse.
 */
$from_manage = $decide($order, ['checkout_url' => ''], true);
$already_settled = $decide(['id' => $order_id, 'status' => 'paid'], ['checkout_url' => ''], false, false);
$not_yet_awaiting = $decide(['id' => $order_id, 'status' => 'reserved'], ['checkout_url' => '']);
$observed['refused'] = ['manage_context' => $from_manage, 'settled_order' => $already_settled, 'not_awaiting_payment' => $not_yet_awaiting];
$check('' === $from_manage, 'editing from the order page sent the customer away from it');
$check('' === $already_settled, 'an already settled order was sent off to pay again');
$check('' === $not_yet_awaiting, 'an order that is not awaiting payment was sent to the order page');

/**
 * 4. No order page on the site: stay where you are. The email builder deliberately falls
 *    back to a grid page so a letter always carries a link; sending a customer to a grid after
 *    they saved an ad is the dead end this fixture exists to keep closed.
 */
update_option($page_option, 999999, false);
$nowhere = $decide($order, ['checkout_url' => '']);
$email_url = (string) Payments::customer_manage_url_for_mds_order($order);
$observed['no_order_page'] = ['redirect' => $nowhere, 'email_builder_falls_back_to' => $email_url];
$check('' === $nowhere, 'the customer was sent to a page this site does not have: ' . $nowhere);
$check('' !== $email_url, 'the email builder lost its fallback, which it should keep');

update_option($page_option, $original_page, false);

/**
 * 5. An order the site completed by itself - manual payment with the auto-complete setting
 *    on, or a free order. The customer's part is finished, so the page that thanks them is
 *    the destination (the tester asked for the shape the older plugin had: review, confirm,
 *    thank you). A site with no thank-you page still lands on their own order page rather
 *    than staying on a grid with a success message.
 */
$thank_you_page_id = wp_insert_post([
    'post_title' => 'MDS3 fixture thank you',
    'post_type' => 'page',
    'post_status' => 'publish',
]);
if (is_wp_error($thank_you_page_id) || !absint($thank_you_page_id)) {
    $check(false, 'the fixture could not create a thank-you page');
} else {
    update_option($thank_you_option, absint($thank_you_page_id), false);
    update_option($page_option, $order_page_id, false);
    $settled_on_first_save = $decide(['id' => $order_id, 'order_key' => $order['order_key'], 'status' => 'paid'], ['checkout_url' => '']);
    $order_page_url = (string) get_permalink($order_page_id);
    $expected_thank_you = add_query_arg(
        ['mds3_order_id' => absint($order_id), 'mds3_order_key' => (string) $order['order_key']],
        (string) get_permalink(absint($thank_you_page_id))
    );
    $settings_now = (array) get_option('mds3_settings', []);
    $observed['completed_without_payment'] = [
        'thank_you_page_setting' => (string) ($settings_now['thank-you-page'] ?? ''),
        'redirect' => $settled_on_first_save,
        'order_page' => $order_page_url,
    ];
    $check(
        $expected_thank_you === $settled_on_first_save,
        'an order the site completed did not land on the Thank You page: ' . $settled_on_first_save . ' (expected ' . $expected_thank_you . ')'
    );
    $check(
        '' !== $settled_on_first_save && 0 !== strpos($settled_on_first_save, $order_page_url),
        'the customer was sent to the order page instead of the thank-you page: ' . $settled_on_first_save
    );

    // A Thank You Page URL that points at one of this plugin's other pages cannot be what
    // the owner meant, and it is a configuration this site really had: it sent a finished
    // order back to a page that asks for payment. With no Thank You page of its own either,
    // the customer gets their order page rather than the payment screen.
    update_option($thank_you_option, 0, false);
    $without_thank_you = $decide(['id' => $order_id, 'order_key' => $order['order_key'], 'status' => 'paid'], ['checkout_url' => '']);
    $observed['no_thank_you_page'] = $without_thank_you;
    $check(
        0 === strpos($without_thank_you, $order_page_url)
            && false !== strpos($without_thank_you, 'mds3_order_id=' . absint($order_id)),
        'with no thank-you page the customer was not sent to their order page: ' . $without_thank_you
    );

    wp_delete_post(absint($thank_you_page_id), true);
}

$cleanup();

echo wp_json_encode($observed), "\n";
if ($fails) {
    fwrite(STDERR, implode("\n", $fails) . "\n");
    exit(1);
}
echo "MDS3 ad-save destination fixture passed.\n";
