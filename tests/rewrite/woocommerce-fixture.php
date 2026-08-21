<?php
/**
 * WP-CLI WooCommerce bridge fixture for MDS3.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/woocommerce-fixture.php
 */

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Orders\CheckoutRouter;
use MillionDollarScript\V3\Orders\OrderCleanup;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Orders\OrderRenewal;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('wc_create_order') || !class_exists(Payments::class) || !Payments::provider_ready('woocommerce')) {
    echo wp_json_encode(['skipped' => 'woocommerce_provider_unavailable']) . "\n";
    return;
}

$settings = get_option('mds3_settings', []);
$settings = is_array($settings) ? $settings : [];
$settings['payment_provider'] = 'woocommerce';
if (function_exists('get_woocommerce_currency')) {
    $settings['currency'] = get_woocommerce_currency();
}
if (function_exists('get_woocommerce_currency_symbol')) {
    $settings['currency-symbol'] = get_woocommerce_currency_symbol($settings['currency'] ?? '');
}
update_option('mds3_settings', $settings, false);

$fixture_user = get_user_by('login', 'mds_fixture_customer');
if (!$fixture_user) {
    $role = function_exists('wp_roles') && wp_roles()->is_role('customer') ? 'customer' : 'subscriber';
    $fixture_user_id = wp_insert_user([
        'user_login' => 'mds_fixture_customer',
        'user_pass' => wp_generate_password(24),
        'user_email' => 'mds-fixture-customer@example.test',
        'display_name' => 'MDS 3.0 Fixture Customer',
        'role' => $role,
    ]);
    if (is_wp_error($fixture_user_id)) {
        throw new RuntimeException($fixture_user_id->get_error_message());
    }
    $fixture_user = get_userdata(absint($fixture_user_id));
}

$fixture_user_id = $fixture_user ? absint($fixture_user->ID) : 0;
if (!$fixture_user_id) {
    throw new RuntimeException('Could not create or load fixture customer user.');
}
wp_set_current_user($fixture_user_id);

$grid = (new GridRepository())->first_active();
if (!$grid) {
    throw new RuntimeException('No active grid exists for WooCommerce fixture.');
}

$geometry = $grid->geometry();
$blocks = new BlockRepository();
$reserved = null;

for ($attempt = 0; $attempt < 100; $attempt++) {
    $row = ($attempt + 17) % max(1, $geometry->rows());
    $col = ($attempt + 23) % max(1, $geometry->columns());
    $block = $blocks->materialize($grid, $row, $col);
    if (is_wp_error($block) || 'available' !== ($block['status'] ?? '')) {
        continue;
    }

    $reserved = $blocks->reserve($block, 0);
    if (!is_wp_error($reserved)) {
        break;
    }
}

if (!$reserved || is_wp_error($reserved)) {
    throw new RuntimeException('Could not reserve an available block for WooCommerce fixture.');
}

$orders = new OrderRepository();
$order_id = $orders->create([
    [
        'grid_id' => $grid->id(),
        'block_id' => absint($reserved['id']),
        'item_type' => 'block',
        'quantity' => 1,
        'unit_price' => 1,
        'total' => 1,
        'metadata' => [
            'x' => absint($reserved['x']),
            'y' => absint($reserved['y']),
            'width' => absint($reserved['width']),
            'height' => absint($reserved['height']),
        ],
    ],
], [
    'user_id' => $fixture_user_id,
    'email' => sanitize_email((string) ($fixture_user->user_email ?? '')),
    'status' => 'reserved',
    'block_status' => 'reserved',
    'currency' => 'USD',
    'commerce_provider' => Payments::active_provider_id($settings),
]);

if (is_wp_error($order_id)) {
    throw new RuntimeException($order_id->get_error_message());
}

$checkout = (new CheckoutRouter())->payload($orders->find($order_id));

if ('woocommerce' !== ($checkout['provider'] ?? '') || empty($checkout['provider_order_id']) || empty($checkout['checkout_url'])) {
    throw new RuntimeException('WooCommerce checkout payload failed: ' . wp_json_encode($checkout));
}

do_action('woocommerce_payment_complete', absint($checkout['provider_order_id']));

$order = $orders->find($order_id);
$block_after = $GLOBALS['wpdb']->get_row(
    $GLOBALS['wpdb']->prepare(
        'SELECT status FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d',
        absint($reserved['id'])
    ),
    ARRAY_A
);

if ('paid' !== ($order['status'] ?? '') || 'sold' !== ($block_after['status'] ?? '')) {
    throw new RuntimeException('WooCommerce payment state did not sync back to MDS3.');
}

$_GET['mds3_order_id'] = $order_id;
$_GET['mds3_order_key'] = $order['order_key'] ?? '';
$summary_html = do_shortcode('[mds3_page type="thank-you" grid_id="' . absint($grid->id()) . '"]');
unset($_GET['mds3_order_id'], $_GET['mds3_order_key']);
if (false === strpos($summary_html, 'Manage upload') || false !== strpos($summary_html, 'Continue payment')) {
    throw new RuntimeException('Paid WooCommerce order summary showed an incorrect payment action.');
}

$wc_order = wc_get_order(absint($checkout['provider_order_id']));
if ($wc_order) {
    if ('mds-fixture-customer@example.test' !== sanitize_email((string) $wc_order->get_billing_email())) {
        throw new RuntimeException('WooCommerce order did not preserve the MDS3 customer billing email.');
    }

    $account_actions = apply_filters('woocommerce_my_account_my_orders_actions', [], $wc_order);
    $manage_action = is_array($account_actions['mds3_manage'] ?? null) ? $account_actions['mds3_manage'] : [];
    if (empty($manage_action['url']) || false === strpos((string) $manage_action['url'], 'mds3_order_id=' . absint($order_id)) || false === strpos((string) $manage_action['url'], 'mds3_order_key=')) {
        throw new RuntimeException('WooCommerce my-account actions did not include a Million Dollar Script manage link.');
    }

    $adapter = \MillionDollarScript\Extensions\WooCommerce\Main::instance();
    $existing_order_method = new ReflectionMethod($adapter, 'existing_order');
    $existing_order_method->setAccessible(true);
    $matching_existing_order = $existing_order_method->invoke($adapter, [
        'existing_provider_order_id' => absint($checkout['provider_order_id']),
        'source' => 'mds-grid',
        'source_id' => $order_id,
    ]);
    if (!$matching_existing_order || absint($matching_existing_order->get_id()) !== absint($checkout['provider_order_id'])) {
        throw new RuntimeException('WooCommerce adapter did not reuse a matching existing MDS3 order.');
    }

    $mismatched_existing_order = $existing_order_method->invoke($adapter, [
        'existing_provider_order_id' => absint($checkout['provider_order_id']),
        'source' => 'mds-grid',
        'source_id' => $order_id + 999,
    ]);
    if (null !== $mismatched_existing_order) {
        throw new RuntimeException('WooCommerce adapter reused an existing order with mismatched MDS3 source metadata.');
    }

    $wc_order->update_status('pending', 'Fixture reset before admin-paid completion check.', true);
}
(new OrderRepository())->update($order_id, ['status' => 'reserved']);
Payments::complete_provider_order_for_mds_order($order_id);
$wc_order = wc_get_order(absint($checkout['provider_order_id']));

if (!$wc_order || !$wc_order->has_status('completed')) {
    throw new RuntimeException('Admin-paid MDS3 order status did not complete WooCommerce order.');
}

$old_wc_order_id = absint($checkout['provider_order_id']);
$expired_term = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
(new OrderRepository())->update($order_id, [
    'status' => 'paid',
    'commerce_provider' => 'woocommerce',
    'commerce_order_id' => (string) $old_wc_order_id,
    'metadata' => [
        'duration_days' => 1,
        'paid_at' => gmdate('Y-m-d H:i:s', time() - (2 * DAY_IN_SECONDS)),
        'term_started_at' => gmdate('Y-m-d H:i:s', time() - (2 * DAY_IN_SECONDS)),
        'expires_at' => $expired_term,
    ],
]);
(new BlockRepository())->mark_by_order($order_id, 'sold');
$renewal_settings = get_option('mds3_settings', []);
$renewal_settings = is_array($renewal_settings) ? $renewal_settings : [];
$renewal_settings['expire-orders'] = 'yes';
$renewal_settings['minutes-unconfirmed'] = 0;
$renewal_settings['minutes-confirmed'] = 0;
$renewal_settings['minutes-renew'] = 60;
$renewal_settings['minutes-cancel'] = 0;
$renewal_settings['payment_provider'] = 'woocommerce';
update_option('mds3_settings', $renewal_settings, false);

(new OrderCleanup())->run(500);
$expired_renewal_order = (new OrderRepository())->find($order_id);
$expired_renewal_block = $GLOBALS['wpdb']->get_row(
    $GLOBALS['wpdb']->prepare(
        'SELECT status, order_id FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d',
        absint($reserved['id'])
    ),
    ARRAY_A
);
if ('expired' !== ($expired_renewal_order['status'] ?? '') || 'reserved' !== ($expired_renewal_block['status'] ?? '') || absint($expired_renewal_block['order_id'] ?? 0) !== absint($order_id)) {
    throw new RuntimeException('WooCommerce renewal setup did not retain expired paid-order inventory.');
}

(new OrderRepository())->update($order_id, [
    'commerce_provider' => 'legacy_mds2',
    'commerce_order_id' => '55',
]);
$expired_renewal_order = (new OrderRepository())->find($order_id);

$renewal = (new OrderRenewal())->start($expired_renewal_order, ['source' => 'fixture']);
if (is_wp_error($renewal)) {
    throw new RuntimeException($renewal->get_error_message());
}
$renewal_checkout = is_array($renewal['checkout'] ?? null) ? $renewal['checkout'] : [];
$new_wc_order_id = absint($renewal_checkout['provider_order_id'] ?? 0);
if ('woocommerce' !== ($renewal_checkout['provider'] ?? '') || !$new_wc_order_id || $new_wc_order_id === $old_wc_order_id || empty($renewal_checkout['checkout_url'])) {
    throw new RuntimeException('WooCommerce renewal did not create a fresh provider checkout: ' . wp_json_encode($renewal_checkout));
}

$renewal_checkout_query = [];
parse_str((string) wp_parse_url((string) $renewal_checkout['checkout_url'], PHP_URL_QUERY), $renewal_checkout_query);
if (
    absint($renewal_checkout_query['mds3_order_id'] ?? 0) !== absint($order_id) ||
    !hash_equals((string) ($expired_renewal_order['order_key'] ?? ''), (string) ($renewal_checkout_query['mds3_order_key'] ?? ''))
) {
    throw new RuntimeException('WooCommerce renewal checkout URL did not include verified MDS3 order credentials.');
}

$previous_get = $_GET;
$previous_user_id = get_current_user_id();
try {
    wp_set_current_user(0);
    $_GET = [
        'pay_for_order' => 'true',
        'key' => (string) ($renewal_checkout_query['key'] ?? ''),
    ];
    if (current_user_can('pay_for_order', $new_wc_order_id)) {
        throw new RuntimeException('WooCommerce renewal payment capability was granted without MDS3 order credentials.');
    }

    $_GET = $renewal_checkout_query;
    if (!current_user_can('pay_for_order', $new_wc_order_id)) {
        throw new RuntimeException('WooCommerce renewal payment capability was not granted for a verified MDS3 order-pay link.');
    }
} finally {
    $_GET = $previous_get;
    wp_set_current_user($previous_user_id);
}

$retry_renewal_order = (new OrderRepository())->find($order_id);
$retry_renewal = (new OrderRenewal())->start($retry_renewal_order, [
    'source' => 'fixture-retry',
    'force_new_checkout' => true,
]);
if (is_wp_error($retry_renewal)) {
    throw new RuntimeException($retry_renewal->get_error_message());
}
$retry_checkout = is_array($retry_renewal['checkout'] ?? null) ? $retry_renewal['checkout'] : [];
$retry_wc_order_id = absint($retry_checkout['provider_order_id'] ?? 0);
$retry_mds_order = (new OrderRepository())->find($order_id);
$retry_metadata = json_decode((string) ($retry_mds_order['metadata'] ?? ''), true);
$retry_terms = is_array($retry_metadata['renewal_terms'] ?? null) ? $retry_metadata['renewal_terms'] : [];
if (
    !$retry_wc_order_id ||
    $retry_wc_order_id === $old_wc_order_id ||
    $retry_wc_order_id === $new_wc_order_id ||
    empty($retry_checkout['checkout_url']) ||
    1 !== count($retry_terms)
) {
    throw new RuntimeException('WooCommerce renewal retry did not create a fresh provider checkout without duplicating term history: ' . wp_json_encode($retry_checkout));
}
$retry_checkout_query = [];
parse_str((string) wp_parse_url((string) $retry_checkout['checkout_url'], PHP_URL_QUERY), $retry_checkout_query);
if (
    absint($retry_checkout_query['mds3_order_id'] ?? 0) !== absint($order_id) ||
    !hash_equals((string) ($expired_renewal_order['order_key'] ?? ''), (string) ($retry_checkout_query['mds3_order_key'] ?? ''))
) {
    throw new RuntimeException('WooCommerce renewal retry checkout URL did not preserve MDS3 order credentials.');
}
$new_wc_order_id = $retry_wc_order_id;

do_action('woocommerce_payment_complete', $new_wc_order_id);
$renewed_order = (new OrderRepository())->find($order_id);
$renewed_metadata = json_decode((string) ($renewed_order['metadata'] ?? ''), true);
$renewed_block = $GLOBALS['wpdb']->get_row(
    $GLOBALS['wpdb']->prepare(
        'SELECT status, order_id FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d',
        absint($reserved['id'])
    ),
    ARRAY_A
);
if (
    'paid' !== ($renewed_order['status'] ?? '') ||
    (string) $new_wc_order_id !== (string) ($renewed_order['commerce_order_id'] ?? '') ||
    'sold' !== ($renewed_block['status'] ?? '') ||
    absint($renewed_block['order_id'] ?? 0) !== absint($order_id) ||
    empty($renewed_metadata['expires_at']) ||
    strtotime((string) $renewed_metadata['expires_at']) <= time()
) {
    throw new RuntimeException('WooCommerce renewal payment did not reactivate the MDS3 order with a fresh term.');
}

$failed_reserved = null;
for ($attempt = 0; $attempt < 100; $attempt++) {
    $row = ($attempt + 41) % max(1, $geometry->rows());
    $col = ($attempt + 47) % max(1, $geometry->columns());
    $block = $blocks->materialize($grid, $row, $col);
    if (is_wp_error($block) || 'available' !== ($block['status'] ?? '')) {
        continue;
    }

    $failed_reserved = $blocks->reserve($block, 0);
    if (!is_wp_error($failed_reserved)) {
        break;
    }
}
if (!$failed_reserved || is_wp_error($failed_reserved)) {
    throw new RuntimeException('Could not reserve a block for WooCommerce failed-payment fixture.');
}
$failed_order_id = $orders->create([
    [
        'grid_id' => $grid->id(),
        'block_id' => absint($failed_reserved['id']),
        'item_type' => 'block',
        'quantity' => 1,
        'unit_price' => 2,
        'total' => 2,
        'metadata' => [
            'x' => absint($failed_reserved['x']),
            'y' => absint($failed_reserved['y']),
            'width' => absint($failed_reserved['width']),
            'height' => absint($failed_reserved['height']),
        ],
    ],
], [
    'user_id' => $fixture_user_id,
    'email' => sanitize_email((string) ($fixture_user->user_email ?? '')),
    'status' => 'reserved',
    'block_status' => 'reserved',
    'currency' => 'USD',
    'commerce_provider' => Payments::active_provider_id($settings),
]);
if (is_wp_error($failed_order_id)) {
    throw new RuntimeException($failed_order_id->get_error_message());
}
$failed_checkout = (new CheckoutRouter())->payload($orders->find($failed_order_id));
$failed_wc_order = wc_get_order(absint($failed_checkout['provider_order_id'] ?? 0));
if (!$failed_wc_order) {
    throw new RuntimeException('WooCommerce failed-payment fixture did not create a provider order.');
}
$failed_wc_order->update_status('failed', 'Fixture failed-payment status sync check.', true);
$failed_order = $orders->find($failed_order_id);
$failed_block = $GLOBALS['wpdb']->get_row(
    $GLOBALS['wpdb']->prepare(
        'SELECT status, order_id FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d',
        absint($failed_reserved['id'])
    ),
    ARRAY_A
);
if ('failed' !== ($failed_order['status'] ?? '') || 'available' !== ($failed_block['status'] ?? '') || 0 !== absint($failed_block['order_id'] ?? 0)) {
    throw new RuntimeException('WooCommerce failed payment did not preserve failure status and release MDS3 inventory.');
}

$guest_checkout = Payments::create_checkout([
    'source' => 'fixture-guest',
    'source_id' => 1,
    'source_key' => 'fixture-guest',
    'user_id' => 0,
    'email' => 'mds-guest@example.test',
    'currency' => $settings['currency'] ?? 'USD',
    'subtotal' => 4.5,
    'total' => 4.5,
    'items' => [[
        'name' => 'Fixture guest checkout',
        'amount' => 4.5,
        'metadata' => ['fixture' => 'guest'],
    ]],
    'manage_url' => home_url('/fixture-guest-manage/'),
]);
if (is_wp_error($guest_checkout) || empty($guest_checkout['provider_order_id'])) {
    throw new RuntimeException('WooCommerce guest checkout fixture failed: ' . (is_wp_error($guest_checkout) ? $guest_checkout->get_error_message() : wp_json_encode($guest_checkout)));
}
$guest_wc_order = wc_get_order(absint($guest_checkout['provider_order_id']));
if (!$guest_wc_order || 0 !== absint($guest_wc_order->get_customer_id()) || 'mds-guest@example.test' !== sanitize_email((string) $guest_wc_order->get_billing_email())) {
    throw new RuntimeException('WooCommerce guest checkout did not preserve the MDS3 customer email.');
}

echo wp_json_encode([
    'mds_order_id' => absint($order_id),
    'wc_order_id' => absint($checkout['provider_order_id']),
    'guest_wc_order_id' => absint($guest_checkout['provider_order_id']),
    'status' => $order['status'],
    'block_status' => $block_after['status'],
    'wc_status' => $wc_order->get_status(),
]) . "\n";
