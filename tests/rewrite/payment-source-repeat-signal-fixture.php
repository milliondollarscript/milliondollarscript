<?php
/**
 * WP-CLI fixture: a payment signal that is not a payment must not change what is showing.
 *
 * Why this exists: a paid signal for a grid order arrives from more than one place, and not
 * all of them are a payment. The subscriptions extension pays each cycle through
 * Payments::mark_source_paid() (premium/mds-subscriptions/src/SourceAdapters.php:191) on an
 * order that is already paid; mds-woocommerce fires woocommerce_payment_complete AND
 * woocommerce_order_status_processing and _completed for the same order; an admin can
 * re-confirm a paid order. Answering every one of those by setting the order's placements to
 * active means the next renewal quietly puts back a creative the site had taken down - and
 * since this plugin now lets an admin publish or unpublish deliberately, and can import a
 * record the older site was still reviewing, "active" is a decision somebody made (llm-task
 * bc694a0c).
 *
 * The rule is the one paid_order_metadata() already applies to the term clock: act on a
 * transition into paid, not on a signal that arrives while the order is already paid. A
 * genuine renewal - an expired order paying again - is a transition and must still put the
 * artwork back up.
 *
 * Step 6 belongs here for the same reason: the signal that pays an order through a store is
 * not the signal that says how much was paid. The amount the provider reports is recorded
 * next to the amount the order asked for, and a difference is flagged rather than trusted.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/payment-source-repeat-signal-fixture.php
 */

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
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

$observed = [];
$placements_of = static function ($order_id) {
    $rows = (new PlacementRepository())->for_order(absint($order_id));

    $states = [];
    foreach ((array) $rows as $row) {
        $states[] = (string) ($row['status'] ?? '');
    }
    sort($states);

    return $states;
};

$meta_of = static function ($order_id) {
    $order = (new OrderRepository())->find(absint($order_id));

    return is_array($order) ? (array) json_decode((string) ($order['metadata'] ?? '{}'), true) : [];
};

/**
 * One grid, one order, two creatives, one block. Everything here is created by this run and
 * removed again at the end, so the numbers below describe only this fixture.
 */
$grid = (new GridRepository())->create([
    'title' => 'Repeat Payment Signal Fixture',
    'width' => 10,
    'height' => 10,
    'block_width' => 10,
    'block_height' => 10,
    'price_per_block' => 25,
    'currency' => 'USD',
    'status' => 'active',
]);
if (is_wp_error($grid)) {
    throw new RuntimeException('Could not create the fixture grid.');
}
$grid_id = (int) $grid->id();

// Block rows are materialized on demand, not when a grid is made.
$block_row = (new BlockRepository())->materialize($grid, 0, 0);
$block_id = (int) ($block_row['id'] ?? 0);
if (!$block_id) {
    throw new RuntimeException('The fixture grid produced no block to sell.');
}

$order_id = (new OrderRepository())->create([
    [
        'grid_id' => $grid_id,
        'block_id' => $block_id,
        'item_type' => 'block',
        'quantity' => 1,
        'unit_price' => 25,
        'total' => 25,
    ],
], [
    'email' => 'repeat-signal@example.test',
    'status' => 'reserved',
    'block_status' => 'reserved',
    'currency' => 'USD',
    'metadata' => ['duration_days' => 30],
]);
if (is_wp_error($order_id) || !absint($order_id)) {
    throw new RuntimeException('Could not create the fixture order.');
}

$placement_ids = [];
foreach ([['y' => 0], ['y' => 1]] as $slot) {
    $placement_id = (new PlacementRepository())->create([
        'grid_id' => $grid_id,
        'block_id' => $block_id,
        'order_id' => $order_id,
        'attachment_id' => 1,
        'x' => 0,
        'y' => (int) $slot['y'],
        'width' => 1,
        'height' => 1,
        'status' => 'pending',
    ]);
    if (is_wp_error($placement_id) || !absint($placement_id)) {
        throw new RuntimeException('Could not create a fixture placement.');
    }
    $placement_ids[] = (int) $placement_id;
}

$cleanup = static function () use ($wpdb, $order_id, $grid_id, $placement_ids) {
    foreach (['placements', 'order_items'] as $table) {
        $wpdb->delete(DB::table($table), ['order_id' => $order_id]);
    }
    $wpdb->delete(DB::table('orders'), ['id' => $order_id]);
    // GridRepository::delete() leaves block rows behind, so this fixture cleans up after
    // the block it materialized rather than adding an orphan to a development site.
    $wpdb->delete(DB::table('blocks'), ['grid_id' => $grid_id]);
    (new GridRepository())->delete($grid_id);
};

$block_state = static function () use ($wpdb, $block_id) {
    return (string) $wpdb->get_var($wpdb->prepare('SELECT status FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d', $block_id));
};

$observed['before'] = ['order' => 'reserved', 'placements' => $placements_of($order_id)];

/**
 * 1. The payment. Everything a paid order implies has to happen here.
 */
if (!Payments::mark_source_paid('mds-grid', $order_id, ['provider' => 'standalone'])) {
    $check(false, 'the first paid signal did not run at all');
}
$order = (new OrderRepository())->find($order_id);
$observed['first_paid'] = ['order' => (string) ($order['status'] ?? ''), 'placements' => $placements_of($order_id), 'block' => $block_state()];
$check('paid' === (string) ($order['status'] ?? ''), 'the first paid signal did not pay the order');
$check(['active', 'active'] === $placements_of($order_id), 'a genuine payment did not put the artwork up: ' . wp_json_encode($placements_of($order_id)));
$check('sold' === $block_state(), 'a genuine payment did not mark the sold block sold');
$paid_at = (string) ($meta_of($order_id)['paid_at'] ?? '');
$check('' !== $paid_at, 'the first paid signal did not record when the order was paid');
$check(!array_key_exists('provider_paid_total', $meta_of($order_id)), 'a signal that reported no amount recorded an amount anyway');

/**
 * 2. The site takes one creative down - the same repository call the order screen's Unpublish
 *    button makes - and leaves the other up.
 */
(new PlacementRepository())->update_status_by_order($order_id, 'pending');
$wpdb->update(DB::table('placements'), ['status' => 'active'], ['id' => $placement_ids[0]]);
$observed['after_admin_took_one_down'] = $placements_of($order_id);
$check(['active', 'pending'] === $placements_of($order_id), 'the fixture did not reach the mixed state it needs: ' . wp_json_encode($placements_of($order_id)));

/**
 * 3. A cycle is paid again. The order is already paid, so this signal is not a payment.
 */
if (!Payments::mark_source_paid('mds-grid', $order_id, ['provider' => 'standalone', 'subscription_event' => 'cycle_paid'])) {
    $check(false, 'the repeat paid signal did not run at all');
}
$states = $placements_of($order_id);
$observed['repeat_paid'] = ['order' => (string) ((new OrderRepository())->find($order_id)['status'] ?? ''), 'placements' => $states, 'block' => $block_state()];
$check(['active', 'pending'] === $states, 'a repeat payment signal put back a creative the site had taken down: ' . wp_json_encode($states));
$check('sold' === $block_state(), 'a repeat payment signal released a block that is still sold');
$check($paid_at === (string) ($meta_of($order_id)['paid_at'] ?? ''), 'a repeat payment signal re-stamped when the order was paid');

/**
 * 4. The term runs out. Unchanged behaviour, asserted so this fixture cannot quietly make
 *    the paid path the only one that still works.
 */
Payments::mark_source_status('mds-grid', $order_id, 'expired', ['subscription_event' => 'ended']);
$expired_states = $placements_of($order_id);
$observed['expired'] = ['order' => (string) ((new OrderRepository())->find($order_id)['status'] ?? ''), 'placements' => $expired_states];
$check('expired' === (string) ((new OrderRepository())->find($order_id)['status'] ?? ''), 'the expired signal did not expire the order');
$check(!in_array('active', $expired_states, true), 'an expired order is still showing artwork: ' . wp_json_encode($expired_states));

/**
 * 5. A real renewal: this one IS a transition into paid, so the artwork goes back up.
 */
Payments::mark_source_paid('mds-grid', $order_id, ['provider' => 'standalone']);
$renewed_states = $placements_of($order_id);
$observed['renewed'] = ['order' => (string) ((new OrderRepository())->find($order_id)['status'] ?? ''), 'placements' => $renewed_states, 'block' => $block_state()];
$check('paid' === (string) ((new OrderRepository())->find($order_id)['status'] ?? ''), 'the renewal did not pay the order again');
$check(['active', 'active'] === $renewed_states, 'a renewal after expiry did not put the artwork back up: ' . wp_json_encode($renewed_states));
$check('sold' === $block_state(), 'the renewal did not mark the block sold again');

$dbg_row = (new OrderRepository())->find($order_id);
fwrite(STDERR, 'DEBUG asked total=' . var_export($dbg_row['total'] ?? null, true) . ' currency=' . var_export($dbg_row['currency'] ?? null, true) . PHP_EOL);

/**
 * 6. A provider reports what it was actually paid. The store can complete an order at a
 *    different amount than the MDS order asked for - an admin edits the WooCommerce order,
 *    or a discount is applied there - and this path has no reason to suspect it. Nothing is
 *    blocked, because publishing is a decision a site makes; what must not happen is the
 *    difference being invisible.
 *
 * The currency used below is the one the order actually carries rather than the one this
 * fixture asked for: a store with WooCommerce active sets its own currency, and the point of
 * the check is the difference between the order and the signal, not the name of either.
 */
$asked_currency = strtoupper((string) ((new OrderRepository())->find($order_id)['currency'] ?? ''));
if ('' === $asked_currency) {
    throw new RuntimeException('The fixture order has no currency, so a currency difference cannot be shown.');
}
$other_currency = 'USD' === $asked_currency ? 'CAD' : 'USD';

Payments::mark_source_status('mds-grid', $order_id, 'paid', [
    'provider' => 'woocommerce',
    'provider_total' => 1.0,
    'provider_currency' => $asked_currency,
]);
$meta = $meta_of($order_id);
$check('1.00' === (string) ($meta['provider_paid_total'] ?? ''), 'the amount the provider reported was not recorded');
$check(is_array($meta['provider_amount_mismatch'] ?? null), 'a paid amount different from the order total was not flagged');
$check('25.00' === (string) ($meta['provider_amount_mismatch']['asked_total'] ?? ''), 'the mismatch did not keep the amount the order asked for');
$check('1.00' === (string) ($meta['provider_amount_mismatch']['paid_total'] ?? ''), 'the mismatch did not keep the amount that was paid');
$check(false === filter_var($meta['provider_amount_mismatch']['currency_only'] ?? true, FILTER_VALIDATE_BOOLEAN), 'an amount difference in the same currency was labelled a currency problem');
$words = \MillionDollarScript\V3\Commerce\PaymentAmountEvidence::describe($meta);
$check(is_array($words) && false !== strpos((string) ($words['text'] ?? ''), '1.00') && false !== strpos((string) ($words['text'] ?? ''), '25.00'), 'the order screen was given no sentence naming both amounts');
$check(is_array($words) && false === stripos((string) ($words['text'] ?? ''), 'amounts match'), 'an amount difference was described as a currency difference');
$check('paid' === (string) ((new OrderRepository())->find($order_id)['status'] ?? ''), 'a reported amount difference stopped an order being paid');

// A later signal with a matching amount clears the flag, so fixing it upstream fixes the record.
Payments::mark_source_status('mds-grid', $order_id, 'paid', [
    'provider' => 'woocommerce',
    'provider_total' => 25.0,
    'provider_currency' => $asked_currency,
]);
$meta = $meta_of($order_id);
$check('25.00' === (string) ($meta['provider_paid_total'] ?? ''), 'the matching amount was not recorded');
$check(!array_key_exists('provider_amount_mismatch', $meta), 'a later matching payment did not clear the mismatch flag');
$check(null === \MillionDollarScript\V3\Commerce\PaymentAmountEvidence::describe($meta), 'a cleared difference still had something to say about it');

// A different currency at the same number is a different kind of problem.
Payments::mark_source_status('mds-grid', $order_id, 'paid', [
    'provider' => 'woocommerce',
    'provider_total' => 25.0,
    'provider_currency' => $other_currency,
]);
$meta = $meta_of($order_id);
$check(true === filter_var($meta['provider_amount_mismatch']['currency_only'] ?? false, FILTER_VALIDATE_BOOLEAN), 'a currency difference was not identified as one');
$currency_words = \MillionDollarScript\V3\Commerce\PaymentAmountEvidence::describe($meta);
$check(is_array($currency_words) && false !== stripos((string) ($currency_words['text'] ?? ''), 'amounts match'), 'a currency-only difference was described as an amount difference');

$cleanup();

echo wp_json_encode($observed), "\n";
if ($fails) {
    fwrite(STDERR, implode("\n", $fails) . "\n");
    exit(1);
}
echo "MDS3 payment source repeat-signal fixture passed.\n";
