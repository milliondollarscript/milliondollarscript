<?php
/**
 * WP-CLI fixture: a payment route has to lead somewhere.
 *
 * Two real failures from the alpha (Vikunja #29 / llm-task 6d3e6079): with Settings >
 * Checkout URL pointed at the site's own Payment page, "Continue payment" reloaded the page
 * it was on, and the checkout address carried a bare "&return_url" with no value because no
 * thank-you page existed.
 *
 * Run with:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/payment-checkout-loop-fixture.php
 */

use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Orders\CheckoutRouter;
use MillionDollarScript\V3\Pages\PageShortcodes;
use MillionDollarScript\V3\Support\DB;
use MillionDollarScript\V3\Support\PageUrls;

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

$original_settings = get_option('mds3_settings', []);
$original_settings = is_array($original_settings) ? $original_settings : [];
$original_payment_page = absint(get_option('mds3_page_payment_id', 0));
$original_thanks_page = absint(get_option('mds3_page_thank-you_id', 0));
$original_get = $_GET;
$original_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

$grid_id = 0;
$order_id = 0;
$page_ids = [];

/**
 * The href of the payment button in a rendered panel, or an empty string when there is none.
 */
$payment_link = function (array $html_parts) {
    foreach ($html_parts as $html) {
        if (1 !== preg_match('/<a[^>]+class="[^"]*button[^"]*"[^>]+href="([^"]+)"[^>]*>\s*Continue payment\s*<\/a>/i', (string) $html, $match)) {
            continue;
        }

        return (string) $match[1];
    }

    return '';
};

try {
    $grid = (new GridRepository())->create([
        'title' => 'Checkout Loop Fixture Grid',
        'width' => 200,
        'height' => 200,
        'block_width' => 10,
        'block_height' => 10,
        'price_per_block' => 4,
        'currency' => 'USD',
        'status' => 'active',
    ]);
    if (is_wp_error($grid) || !$grid) {
        throw new RuntimeException('the fixture grid could not be created: ' . (is_wp_error($grid) ? $grid->get_error_message() : 'unknown'));
    }
    $grid_id = absint($grid->id());

    $order_key = wp_generate_uuid4();
    $now = current_time('mysql', true);
    $wpdb->insert(DB::table('orders'), [
        'order_key' => $order_key,
        'user_id' => 1,
        'email' => 'checkout-loop-fixture@example.test',
        'status' => 'pending',
        'currency' => 'USD',
        'subtotal' => 4,
        'total' => 4,
        'commerce_provider' => 'standalone',
        'metadata' => wp_json_encode(['checkout_loop_fixture' => true]),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $order_id = absint($wpdb->insert_id);
    if (!$order_id) {
        throw new RuntimeException('the fixture order could not be created: ' . (string) $wpdb->last_error);
    }
    $order = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('orders')) . ' WHERE id = %d', $order_id), ARRAY_A);

    $payment_page_id = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'Checkout Loop Fixture Payment',
        'post_content' => '[mds3_page type="payment"]',
    ]);
    $thanks_page_id = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'Checkout Loop Fixture Thank You',
    ]);
    if (is_wp_error($payment_page_id) || is_wp_error($thanks_page_id)) {
        throw new RuntimeException('the fixture pages could not be created.');
    }
    $page_ids = [absint($payment_page_id), absint($thanks_page_id)];
    $payment_permalink = (string) get_permalink($payment_page_id);
    $thanks_permalink = (string) get_permalink($thanks_page_id);
    $external_checkout = 'https://pay.example.test/checkout';

    // Stand on the Payment page with this order in the request, which is how the customer
    // reaches it: from the order email or the manage link.
    update_option('mds3_page_payment_id', absint($payment_page_id), false);
    delete_option('mds3_page_thank-you_id');
    $_GET = ['mds3_order_id' => (string) $order_id, 'mds3_order_key' => $order_key];
    // A browser on a plain-permalink page sends the page number in the request line, so the
    // simulated request has to carry it too - a path alone would say “/” and name no page.
    $requested_path = (string) wp_parse_url($payment_permalink, PHP_URL_PATH);
    $requested_query = (string) wp_parse_url($payment_permalink, PHP_URL_QUERY);
    $_SERVER['REQUEST_URI'] = '' === $requested_query ? $requested_path : $requested_path . '?' . $requested_query;

    $with_checkout = function ($checkout_url, $thank_you_url = '') use ($grid_id) {
        update_option('mds3_settings', array_merge(get_option('mds3_settings', []), [
            'checkout-url' => (string) $checkout_url,
            'commerce-provider' => 'standalone',
            // A site with no thank-you page has neither the setting nor the option; the
            // dev stack has leftovers from other testing, so state both.
            'thank-you-page' => (string) $thank_you_url,
        ]), false);
        if ('' === (string) $thank_you_url) {
            delete_option('mds3_page_thank-you_id');
        }

        return (string) (new PageShortcodes())->render(['type' => 'payment', 'grid_id' => (string) $grid_id]);
    };

    // 1. The setting points at this very page: the dead button the tester reported.
    $html = $with_checkout($payment_permalink);
    $check(false === strpos($html, 'Continue payment'), 'a Checkout URL pointed at the Payment page still offers Continue payment on that page');

    // 2. A checkout address elsewhere works, and carries no empty return parameter.
    delete_option('mds3_page_thank-you_id');
    $html = $with_checkout($external_checkout);
    $link = $payment_link([$html]);
    $check('' !== $link && false !== strpos(html_entity_decode($link), $external_checkout), 'the payment button is missing or does not lead to the configured checkout address: ' . var_export($link, true));
    $check('' === $link || false === strpos(html_entity_decode($link), 'return_url'), 'an empty return address was sent to the checkout when the site has no thank-you page: ' . var_export($link, true));

    // 3. With a thank-you page, the checkout is told where to send the customer back - and
    // that address carries the order reference, so it has to arrive whole and not truncated
    // at the first parameter.
    update_option('mds3_page_thank-you_id', absint($thanks_page_id), false);
    $html = $with_checkout($external_checkout, $thanks_permalink);
    $link = $payment_link([$html]);
    parse_str((string) wp_parse_url('' === $link ? '' : html_entity_decode($link), PHP_URL_QUERY), $link_args);
    $returned = (string) ($link_args['return_url'] ?? '');
    $check('' !== $link && PageUrls::is_same_page($returned, $thanks_permalink), 'the checkout address lost the thank-you return address: ' . var_export($link, true));
    $check('' !== $returned && false !== strpos($returned, 'mds3_order_key=' . $order_key), 'the return address lost the order reference it was built with: ' . var_export($returned, true));

    // 4. The router is the contract behind that link, checked where it is built.
    delete_option('mds3_page_thank-you_id');
    $query = (string) wp_parse_url((new CheckoutRouter())->standalone_checkout_url($order, ['checkout-url' => $external_checkout, 'thank-you-page' => '']), PHP_URL_QUERY);
    parse_str($query, $router_args);
    $check(!array_key_exists('return_url', $router_args), 'the checkout router added a return_url key with nothing to put in it: ' . $query);
    $check(!empty($router_args['mds3_order_id']) && (int) $router_args['mds3_order_id'] === $order_id, 'the checkout router lost the order reference: ' . $query);
    update_option('mds3_page_thank-you_id', absint($thanks_page_id), false);
    $query = (string) wp_parse_url((new CheckoutRouter())->standalone_checkout_url($order, ['checkout-url' => $external_checkout, 'thank-you-page' => '']), PHP_URL_QUERY);
    parse_str($query, $router_args);
    $check(!empty($router_args['return_url']) && PageUrls::is_same_page((string) $router_args['return_url'], $thanks_permalink), 'the checkout router lost a configured thank-you page: ' . $query);
    $check(!empty($router_args['return_url']) && false !== strpos((string) $router_args['return_url'], 'mds3_order_key=' . (string) $order['order_key']), 'the checkout router sent a return address without the order reference: ' . $query);

    // 5. The panel a customer sees before an order exists must not loop either.
    $_GET = [];
    $html = $with_checkout($payment_permalink);
    preg_match('/<a[^>]+href="([^"]+)"[^>]*>\s*Open checkout\s*<\/a>/i', $html, $open_checkout);
    $open_url = isset($open_checkout[1]) ? html_entity_decode($open_checkout[1]) : '';
    $check('' !== $open_url, 'the no-order payment panel lost its checkout action');
    $check('' !== $open_url && !PageUrls::is_same_page($open_url, $payment_permalink), 'the no-order payment panel sends the customer back to the same page: ' . var_export($open_url, true));

    // 6. The admin is told when they set the value that caused this - in either direction.
    $guard = new ReflectionMethod('MillionDollarScript\V3\Admin\Admin', 'page_url_conflict');
    $guard->setAccessible(true);
    $admin = (new ReflectionClass('MillionDollarScript\V3\Admin\Admin'))->newInstanceWithoutConstructor();
    $refuse = static function (array $submitted, array $current, $key, $allowed) use ($guard, $admin) {
        return (string) $guard->invoke($admin, $submitted, $current, $key, $allowed);
    };
    $conflict = $refuse(['checkout-url' => $payment_permalink], [], 'checkout-url', '');
    $check('' !== $conflict && false !== strpos($conflict, 'Payment'), 'setting the Checkout URL to the Payment page was accepted without explanation: ' . var_export($conflict, true));
    $check('' === $refuse(['checkout-url' => $external_checkout], [], 'checkout-url', ''), 'an ordinary checkout address was refused');
    $check('' === $refuse(['checkout-url' => $payment_permalink], ['checkout-url' => $payment_permalink], 'checkout-url', ''), 'saving unrelated settings was blocked by a Checkout URL that was already stored');
    $check('' === $refuse(['checkout-url' => ''], ['checkout-url' => $payment_permalink], 'checkout-url', ''), 'clearing the Checkout URL was blocked');

    $thank_you_conflict = $refuse(['thank-you-page' => $payment_permalink], [], 'thank-you-page', 'thank-you');
    $check('' !== $thank_you_conflict && false !== strpos($thank_you_conflict, 'Payment'), 'setting the Thank You Page URL to the Payment page was accepted without explanation: ' . var_export($thank_you_conflict, true));
    $check('' === $refuse(['thank-you-page' => $thanks_permalink], [], 'thank-you-page', 'thank-you'), 'the site’s own Thank You page was refused as a Thank You Page URL');
    $check('' === $refuse(['thank-you-page' => 'https://example.test/thanks'], [], 'thank-you-page', 'thank-you'), 'an ordinary thank-you address was refused');
} finally {
    $_GET = $original_get;
    $_SERVER['REQUEST_URI'] = $original_uri;
    update_option('mds3_settings', $original_settings, false);
    if ($original_payment_page) {
        update_option('mds3_page_payment_id', $original_payment_page, false);
    } else {
        delete_option('mds3_page_payment_id');
    }
    if ($original_thanks_page) {
        update_option('mds3_page_thank-you_id', $original_thanks_page, false);
    } else {
        delete_option('mds3_page_thank-you_id');
    }
    foreach ($page_ids as $page_id) {
        wp_delete_post(absint($page_id), true);
    }
    if ($order_id) {
        $wpdb->delete(DB::table('orders'), ['id' => $order_id]);
    }
    if ($grid_id) {
        (new GridRepository())->delete($grid_id);
    }
}

if ($fails) {
    fwrite(STDERR, implode(PHP_EOL, $fails) . PHP_EOL);
    exit(1);
}

echo 'MDS3 checkout loop fixture passed.' . PHP_EOL;
