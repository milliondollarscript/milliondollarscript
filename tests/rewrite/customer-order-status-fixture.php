<?php
/**
 * WP-CLI fixture: the customer's order page tells the truth about where the order stands.
 *
 * Why this exists: this page is where a customer lands after saving an ad, and where their
 * order link takes them months later. It used to contain only the ad form - no status, no
 * next step - on the manual-payment path that a fresh install defaults to. A customer with an
 * order waiting for payment saw a form and nothing else (llm-task b95cc2f5).
 *
 * The wording is the feature, so what matters is not the exact sentence but the promises it is
 * allowed to make. This fixture pins the rules the sentences must never break: every state has
 * something to say, an unpaid order is never described as showing, a paid order is never asked
 * to pay again, a review is only promised on a grid that reviews, and a button only appears
 * when it goes somewhere.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/customer-order-status-fixture.php
 */

use MillionDollarScript\V3\Pages\PageShortcodes;
use MillionDollarScript\V3\Support\Template;

if (!defined('ABSPATH')) {
    exit;
}

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

$shortcodes = new PageShortcodes();
$publishing_grid = ['auto-publish' => true];
$review_grid = ['auto-publish' => false];

$state = static function (array $order, array $placements, array $settings, $payment_url = '') use ($shortcodes, $call) {
    return (array) $call($shortcodes, PageShortcodes::class, 'customer_order_state', [$order, $placements, $settings, $payment_url]);
};
$text = static function (array $state) {
    return strtolower(trim((string) ($state['headline'] ?? '') . ' ' . (string) ($state['detail'] ?? '')));
};
$observed = [];
$term = '2026-12-31 00:00:00';
$paid_order = ['id' => 1, 'status' => 'paid', 'metadata' => wp_json_encode(['expires_at' => $term])];
$awaiting_order = ['id' => 2, 'status' => 'pending_payment'];
$PAY_URL = 'https://example.test/pay?amount=25';

/*
 * 1. Every state has something to say. A blank line here is the bug the card is about.
 */
$statuses = ['pending_payment', 'paid', 'completed', 'renew_paid', 'reserved', 'draft', 'expired', 'cancelled', 'canceled', 'denied', 'failed', 'refunded', 'deleted', 'something_else', ''];
foreach ($statuses as $status) {
    $one = $state(['id' => 900, 'status' => $status], [['status' => 'pending']], $publishing_grid);
    $observed[$status === '' ? 'no_status' : $status] = $one;
    $check(
        '' !== trim((string) ($one['headline'] ?? '')),
        'an order with status "' . $status . '" is left with nothing to read'
    );
    $check(
        in_array((string) ($one['tone'] ?? ''), ['awaiting', 'live', 'closed', 'neutral'], true),
        'status "' . $status . '" has no idea what the customer should take away'
    );
}

/*
 * 2. An order waiting for payment is not described as showing, and the customer is told who
 *    acts next. Whether the grid reviews ads decides the sentence - never a review the site
 *    never asked for.
 */
$awaiting_no_url = $state($awaiting_order, [['status' => 'pending']], $publishing_grid);
$awaiting_review = $state($awaiting_order, [['status' => 'pending']], $review_grid);
$awaiting_with_url = $state($awaiting_order, [['status' => 'pending']], $publishing_grid, $PAY_URL);
$observed['awaiting_without_payment_route'] = $awaiting_no_url;

$copy = $text($awaiting_no_url);
$check(false !== strpos($copy, 'waiting for payment'), 'an order that has not been paid does not say so');
$check(false === strpos($copy, 'is on the grid') && false === strpos($copy, 'your ad is on the grid'), 'an unpaid order reads as if it were showing: ' . $copy);
$check(false !== strpos($copy, 'nothing more is needed from you'), 'the customer is not told that the site takes the next step');
$check('' === (string) ($awaiting_no_url['payment_url'] ?? ''), 'a Continue payment button was offered on a site that has no payment route');

$review_copy = $text($awaiting_review);
$check(false !== strpos($review_copy, 'checks your ad') || false !== strpos($review_copy, 'review'), 'a review grid did not mention that it checks ads first');
$check(false !== strpos($copy, 'goes on the grid as soon as') && false === strpos($copy, 'checks your ad'), 'a grid that publishes immediately promised a review it does not do: ' . $copy);

$check(
    $PAY_URL === (string) ($awaiting_with_url['payment_url'] ?? ''),
    'a site with a payment route did not offer it to a customer who still owes payment'
);
$check(false !== strpos($text($awaiting_with_url), 'continue payment'), 'the customer was not told how to pay when paying is possible');

/*
 * 3. A paid order is never asked to pay again, and what is showing is said exactly.
 */
$showing = $state($paid_order, [['status' => 'active']], $review_grid);
$held = $state($paid_order, [['status' => 'pending']], $review_grid);
$mixed = $state($paid_order, [['status' => 'active'], ['status' => 'pending']], $review_grid);
$ended = $state($paid_order, [['status' => 'cancelled']], $review_grid);
$observed['paid_showing'] = $showing;
$observed['paid_held'] = $held;

$check(false !== strpos($text($showing), 'is on the grid'), 'a showing ad is not described as showing');
$check(false === strpos($text($showing), 'waiting for payment'), 'a paid order reads as unpaid');
$check($term !== '' && '' !== (string) ($showing['shows_until'] ?? ''), 'a showing ad does not tell the customer how long it runs');
$check('' === (string) ($showing['payment_url'] ?? ''), 'a paid order was sent off to pay again');
$check(false !== strpos($text($held), 'waiting to be checked') && false === strpos($text($held), 'your ad is on the grid'), 'a paid but unreviewed ad reads as if it were already showing: ' . $text($held));
$check(false !== strpos($text($mixed), 'waiting to be checked'), 'an order with some pixels still unreviewed hides that from the customer');
$check(false !== strpos($text($ended), 'listing has ended') && false === strpos($text($ended), 'your ad is on the grid'), 'a paid order whose listing ended claims the ad is showing: ' . $text($ended));
$check('live' === (string) ($showing['tone'] ?? ''), 'a showing ad is not signalled as showing');
$check('awaiting' === (string) ($held['tone'] ?? ''), 'an ad waiting for review is not signalled as waiting');

/*
 * 3b. Imported records can carry an end date that has already gone by while the artwork is
 *     still up. A date that has passed is not something to tell a customer is still running.
 */
$past_term = $state(['id' => 903, 'status' => 'paid', 'metadata' => wp_json_encode(['expires_at' => '2001-03-04 05:06:07'])], [['status' => 'active']], $publishing_grid);
$observed['paid_showing_past_term'] = $past_term;
$check('' === (string) ($past_term['shows_until'] ?? ''), 'a listing whose end date has passed was given a date anyway');
$check(false === strpos($text($past_term), 'shows until'), 'the page tells the customer a past date is still running: ' . $text($past_term));

/*
 * 4. Closed states say they are closed, and do not invite the customer to pay.
 */
foreach (['expired', 'cancelled', 'denied', 'deleted', 'refunded'] as $status) {
    $closed = $state(['id' => 901, 'status' => $status], [['status' => 'cancelled']], $review_grid, $PAY_URL);
    $observed['closed_' . $status] = $closed;
    $check(false === strpos($text($closed), 'your ad is on the grid'), '"' . $status . '" reads as if the ad were showing: ' . $text($closed));
    $check('closed' === (string) ($closed['tone'] ?? ''), '"' . $status . '" is not presented as finished business');
    $check('' === (string) ($closed['payment_url'] ?? ''), '"' . $status . '" offered a payment that the site is not asking for');
}
$failed = $state(['id' => 902, 'status' => 'failed'], [['status' => 'cancelled']], $publishing_grid, $PAY_URL);
$check(false !== strpos($text($failed), 'did not go through'), 'a failed payment is not explained to the customer');
$check($PAY_URL === (string) ($failed['payment_url'] ?? ''), 'a failed payment was not offered a way to try again');

/*
 * 5. The page itself: the words arrive, and the button only exists when it goes somewhere.
 */
$render = static function (array $order_state) {
    return (string) Template::render('frontend/pages/order-upload.php', [
        'back_url' => '',
        'image' => '',
        'order' => ['id' => 5, 'status' => 'pending_payment', 'order_key' => 'k'],
        'order_id' => 5,
        'order_key' => 'k',
        'order_state' => $order_state,
        'placement' => [],
        'popup_rich_text' => false,
        'popup_text_max_chars' => 0,
        'popup_text_required' => false,
        'popup_text_visible' => true,
        'theme_class' => '',
        'url_required' => false,
        'url_visible' => true,
    ]);
};

$page_without_url = $render($awaiting_no_url);
$page_with_url = $render($awaiting_with_url);
$observed['rendered_without_route'] = substr(strip_tags($page_without_url), 0, 160);

$check(
    false !== strpos($page_without_url, 'Your order is waiting for payment.'),
    'the order page did not show the customer where their order stands'
);
$check(
    false === strpos($page_without_url, 'Continue payment'),
    'the order page offered a payment button with nowhere to go'
);
$check(
    false !== strpos($page_with_url, 'Continue payment') && false !== strpos($page_with_url, $PAY_URL),
    'the order page hid the payment route the site does have'
);
$check(
    false !== strpos($page_without_url, 'mds3-order-state mds3-order-state-awaiting'),
    'the state is not marked up as a state, so it reads as one more field of the form'
);

/*
 * 6. The other customer surface. An ended listing, a thank-you link and the "my pixels" list
 *    all reach the order summary panel, which until now described an order only as a table of
 *    labels. The same words have to appear there, and it must not grow a second payment button
 *    next to the one that panel already offers.
 */
$render_summary = static function (array $order, array $order_state) use ($shortcodes) {
    return (string) Template::render('frontend/pages/order-summary.php', [
        'back_url' => '',
        'cleanup_notice' => '',
        'manage_url' => '',
        'metadata' => [],
        'order' => $order,
        'order_state' => $order_state,
        'payment_url' => '',
        'renewal_available' => false,
        'renewal_error' => '',
        'renewal_notice' => '',
        'renewal_nonce' => '',
        'term_expires_at' => '',
        'theme_class' => '',
        'title' => 'Order Summary',
    ], $shortcodes);
};

$expired_order = ['id' => 904, 'status' => 'expired', 'order_key' => 'k', 'total' => 25, 'currency' => 'CAD'];
$summary_expired = $render_summary($expired_order, $state($expired_order, [['status' => 'cancelled']], $publishing_grid));
$check(
    false !== strpos($summary_expired, 'This listing has ended.'),
    'the summary panel still describes an ended order only as a label'
);
$check(
    false === strpos($summary_expired, 'mds3-order-state-actions'),
    'the summary panel now offers the same action twice'
);

$showing_order = ['id' => 905, 'status' => 'paid', 'order_key' => 'k', 'total' => 25, 'currency' => 'CAD'];
$summary_showing = $render_summary($showing_order, $state($showing_order, [['status' => 'active']], $publishing_grid));
$check(
    false !== strpos($summary_showing, 'Your ad is on the grid.'),
    'a paid order on its summary page does not say the ad is up'
);

echo wp_json_encode($observed), "\n";
if ($fails) {
    fwrite(STDERR, implode("\n", $fails) . "\n");
    exit(1);
}
echo "MDS3 customer order status fixture passed.\n";
