<?php
/**
 * WP-CLI fixture: on a grid that reviews paid uploads, the admin's Publish action publishes.
 *
 * Why this exists: a grid with "Auto Publish Uploads" off keeps a paid creative at
 * status=pending so a person can look at it first - PlacementRepository holds it back when a
 * payment asks for 'active'. That is the whole point of the setting, and it is also the
 * promise the customer-facing copy makes: the work will appear once it is cleared. The
 * promise needs a destination, so the order screen states the state and offers Publish /
 * Unpublish. But the control went through the same repository call as the payment, which
 * excludes held grids: on exactly the grids that ask for a review the button reported success
 * and changed nothing, while the panel above it said the artwork was awaiting review
 * (llm-task 3720081e, and the gap Vikunja #30 hit as "paid but not showing").
 *
 * So three rules have to hold at once, and this fixture holds all three:
 *   - a payment on its own does not publish on a review grid;
 *   - a person publishing does, whatever the grid setting says;
 *   - anything that is not showing on a paid order has a way forward, including a creative
 *     whose listing ended and was paid again by hand, and the panel may only name a cause it
 *     can prove.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/review-grid-publish-fixture.php
 */

use MillionDollarScript\V3\Admin\Admin;
use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\PlacementFieldContract;
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

$call = static function ($scope, $class, $method, array $args = []) {
    $method = new ReflectionMethod($class, $method);
    $method->setAccessible(true);

    return $method->invokeArgs($scope, $args);
};

$capture = static function ($scope, $class, $method, array $args = []) use ($call) {
    ob_start();
    $call($scope, $class, $method, $args);

    return (string) ob_get_clean();
};

$placements_of = static function ($order_id) {
    $states = [];
    foreach ((array) (new PlacementRepository())->for_order(absint($order_id)) as $row) {
        $states[] = (string) ($row['status'] ?? '');
    }
    sort($states);

    return $states;
};

$observed = [];

/*
 * A grid that reviews paid uploads, one order, one creative, one block. Everything here is
 * created by this run and removed again at the end.
 */
$grid = (new GridRepository())->create([
    'title' => 'Review Grid Publish Fixture',
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

$grid = (new GridRepository())->update($grid_id, ['auto_publish' => 'N']);
$check(
    $grid && !PlacementFieldContract::auto_publish(is_object($grid) ? $grid->settings() : []),
    'the fixture grid did not end up holding paid uploads for review'
);

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
    'email' => 'review-grid@example.test',
    'status' => 'reserved',
    'block_status' => 'reserved',
    'metadata' => ['duration_days' => 30],
]);
if (is_wp_error($order_id) || !absint($order_id)) {
    throw new RuntimeException('Could not create the fixture order.');
}

$placement_id = (new PlacementRepository())->create([
    'grid_id' => $grid_id,
    'block_id' => $block_id,
    'order_id' => $order_id,
    'attachment_id' => 1,
    'x' => 0,
    'y' => 0,
    'width' => 1,
    'height' => 1,
    'status' => 'pending',
]);
if (is_wp_error($placement_id) || !absint($placement_id)) {
    throw new RuntimeException('Could not create the fixture placement.');
}

$cleanup = static function () use ($wpdb, $order_id, $grid_id) {
    foreach (['placements', 'order_items'] as $table) {
        $wpdb->delete(DB::table($table), ['order_id' => $order_id]);
    }
    $wpdb->delete(DB::table('orders'), ['id' => $order_id]);
    // GridRepository::delete() leaves block rows behind, so this fixture cleans up after the
    // block it materialized rather than leaving an orphan on a development site.
    $wpdb->delete(DB::table('blocks'), ['grid_id' => $grid_id]);
    (new GridRepository())->delete($grid_id);
};

$block_state = static function () use ($wpdb, $block_id) {
    return (string) $wpdb->get_var($wpdb->prepare('SELECT status FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d', $block_id));
};

/*
 * 1. The payment arrives on its own. The order is paid, the block is sold, and the creative
 *    waits - that is what the grid setting is for, and the fix below must not break it.
 */
if (!Payments::mark_source_paid('mds-grid', $order_id, ['provider' => 'standalone'])) {
    $check(false, 'the paid signal did not run at all');
}
$order = (new OrderRepository())->find($order_id);
$held_states = $placements_of($order_id);
$observed['paid_and_held'] = [
    'order' => (string) ($order['status'] ?? ''),
    'placements' => $held_states,
    'block' => $block_state(),
];
$check('paid' === (string) ($order['status'] ?? ''), 'the paid signal did not pay the order');
$check('sold' === $block_state(), 'the paid signal did not mark the sold block sold');
$check(['pending'] === $held_states, 'a grid that reviews paid uploads published the upload by itself: ' . wp_json_encode($held_states));

/*
 * 2. The order screen has to say what is happening, and offer the way forward. The message
 *    blames the grid setting only when that is the truth.
 */
$admin = new Admin();

$panel = $capture($admin, Admin::class, 'order_detail_panel', [$order, true]);
$check(strpos($panel, 'Ad visibility') !== false, 'the order screen does not state whether the artwork is showing');
$check(strpos($panel, 'Not published') !== false, 'a held creative does not say it is held');
$check(strpos($panel, 'This grid reviews paid uploads') !== false, 'the screen does not name the reason this creative is held');
$check(strpos($panel, 'value="mds3_publish_placement"') !== false, 'a held creative offers no way to publish it');
$check(strpos($panel, 'value="mds3_unpublish_placement"') === false, 'the screen offers to take down an ad that is not up');

/*
 * 3. A person publishes. This is the decision the grid setting was asking for, so the hold
 *    must not apply to it.
 */
$published = $call($admin, Admin::class, 'apply_placement_publication', [
    ['id' => $order_id, 'status' => 'paid'],
    'active',
]);
$active_states = $placements_of($order_id);
$observed['admin_published'] = ['returned' => (bool) $published, 'placements' => $active_states, 'block' => $block_state()];
$check(true === (bool) $published, 'the Publish action reported that it did nothing');
$check(['active'] === $active_states, 'the Publish action did not show the creative on a review grid: ' . wp_json_encode($active_states));
$check('sold' === $block_state(), 'publishing an approved creative released a block that is still sold');

$panel = $capture($admin, Admin::class, 'order_detail_panel', [$order, true]);
$check(strpos($panel, 'this artwork is showing on the grid') !== false, 'a published creative does not say it is showing');
$check(strpos($panel, 'value="mds3_unpublish_placement"') !== false, 'a showing creative offers no way to take it down');
$check(strpos($panel, 'Not published') === false, 'a showing creative also reads as not published');

/*
 * 4. And the other way. Taking a creative down is a person's decision too, and taking it
 *    down again must not be reversed by the grid's own settings.
 */
$hidden = $call($admin, Admin::class, 'apply_placement_publication', [
    ['id' => $order_id, 'status' => 'paid'],
    'pending',
]);
$held_again_states = $placements_of($order_id);
$observed['admin_unpublished'] = ['returned' => (bool) $hidden, 'placements' => $held_again_states];
$check(true === (bool) $hidden, 'the Unpublish action reported that it did nothing');
$check(['pending'] === $held_again_states, 'the Unpublish action left the creative showing: ' . wp_json_encode($held_again_states));

$panel = $capture($admin, Admin::class, 'order_detail_panel', [$order, true]);
$check(strpos($panel, 'value="mds3_publish_placement"') !== false, 'a creative taken down offers no way to put it back');

/*
 * 5. An order that has not paid has no artwork to publish, whatever an old admin page said.
 */
$unpaid = $call($admin, Admin::class, 'apply_placement_publication', [
    ['id' => $order_id, 'status' => 'pending_payment'],
    'active',
]);
$observed['unpaid_order'] = ['returned' => (bool) $unpaid, 'placements' => $placements_of($order_id)];
$check(false === (bool) $unpaid, 'an unpaid order was allowed to publish artwork');
$check(['pending'] === $placements_of($order_id), 'an unpaid order changed what is showing: ' . wp_json_encode($placements_of($order_id)));

/*
 * 6. A creative whose listing ended, on a site whose grid publishes immediately. This is the
 *    state a site owner reaches by hand: the listing expired, the order was paid again from
 *    the order screen, and the artwork is still down. There has to be a way back, and the
 *    explanation must not blame the grid setting - that grid publishes on its own.
 */
$grid = (new GridRepository())->update($grid_id, ['auto_publish' => 'Y']);
$wpdb->update(DB::table('placements'), ['status' => 'cancelled'], ['id' => $placement_id]);
$order = (new OrderRepository())->find($order_id);
$ended_states = $placements_of($order_id);
$observed['listing_ended'] = ['order' => (string) ($order['status'] ?? ''), 'placements' => $ended_states];
$check(['cancelled'] === $ended_states, 'the fixture did not reach the ended state it needs: ' . wp_json_encode($ended_states));

$panel = $capture($admin, Admin::class, 'order_detail_panel', [$order, true]);
$check(
    strpos($panel, 'value="mds3_publish_placement"') !== false,
    'a creative whose listing ended has no way back on a paid order'
);
$check(
    strpos($panel, 'stopped showing when its listing ended') !== false,
    'the panel does not explain why this artwork is down'
);
$check(
    strpos($panel, 'This grid reviews paid uploads') === false,
    'a grid that publishes immediately is blamed for a creative whose listing ended'
);

$restored = $call($admin, Admin::class, 'apply_placement_publication', [
    ['id' => $order_id, 'status' => 'paid'],
    'active',
]);
$back_states = $placements_of($order_id);
$observed['listing_ended_republished'] = ['returned' => (bool) $restored, 'placements' => $back_states];
$check(true === (bool) $restored, 'publishing an ended creative reported that it did nothing');
$check(['active'] === $back_states, 'publishing an ended creative did not put it back: ' . wp_json_encode($back_states));

// What the store paid next to what the order asked for is recorded on every paid
// order; the order screen has to say so, or the difference stays in metadata.
$mismatch_meta = (new OrderRepository())->find($order_id)['metadata'] ?? [];
$mismatch_meta = is_array($mismatch_meta) ? $mismatch_meta : [];
$mismatch_meta['provider_amount_mismatch'] = [
    'asked_total' => '25.00',
    'asked_currency' => 'USD',
    'paid_total' => '1.00',
    'paid_currency' => 'USD',
    'currency_only' => false,
    'recorded_at' => '2026-10-17 12:00:00',
];
(new OrderRepository())->update($order_id, ['metadata' => $mismatch_meta]);
$flagged = $capture($admin, Admin::class, 'order_detail_panel', [(new OrderRepository())->find($order_id), true]);

$check(
    strpos($flagged, 'mds3-payment-amount-mismatch') !== false
        && strpos($flagged, '1.00') !== false
        && strpos($flagged, '25.00') !== false,
    'a recorded amount difference is not shown on the order screen'
);
$check(
    strpos($flagged, 'amounts match') === false,
    'an amount difference was worded as a currency difference'
);
unset($mismatch_meta['provider_amount_mismatch']);
(new OrderRepository())->update($order_id, ['metadata' => $mismatch_meta]);
$unflagged = $capture($admin, Admin::class, 'order_detail_panel', [(new OrderRepository())->find($order_id), true]);
$check(
    strpos($unflagged, 'mds3-payment-amount-mismatch') === false,
    'the amount notice survived after the difference was cleared'
);

$cleanup();

echo wp_json_encode($observed), "\n";
if ($fails) {
    fwrite(STDERR, implode("\n", $fails) . "\n");
    exit(1);
}
echo "MDS3 review-grid publish fixture passed.\n";
