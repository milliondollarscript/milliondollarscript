<?php
/**
 * Where a customer who arrives with their order in the address is taken.
 *
 * The Thank You, Payment and Order Confirmation pages are meant to work without being tied to
 * one grid, and they offer a grid chooser for that case. That chooser used to win even when
 * the request carried a specific order - so a customer sent to the Thank You page after an
 * order the site completed themselves saw a list of grids instead of their own receipt.
 *
 * Run inside WordPress:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/thank-you-order-panel-fixture.php --allow-root
 */

use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Pages\PageShortcodes;
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

$original_get = $_GET;

// This fixture compares what a page answers on a one-grid site with what it answers on a
// two-grid site, so it has to know the starting count. A seeded site has exactly one active
// grid; if it has any other number, the one-grid half cannot be judged and the fixture says
// that out loud instead of reporting a failure that is about the site, not the page.
$grids = new GridRepository();
$starting_grids = (int) $grids->active_count();
if (1 !== $starting_grids) {
    fwrite(STDERR, "MDS3-SKIP: this fixture needs a site with exactly one active grid to compare against a second one; this site has {$starting_grids}. The suite runs it against the seeded stack (scripts/wp-fixture-suite.sh).\n");
    return;
}

$order_id = (new OrderRepository())->create([], [
    'email' => 'thank-you-panel@example.test',
    'status' => 'paid',
    'block_status' => 'sold',
]);
if (is_wp_error($order_id) || !absint($order_id)) {
    throw new RuntimeException('Could not create the fixture order: ' . (is_wp_error($order_id) ? $order_id->get_error_message() : 'no id'));
}
$order = (new OrderRepository())->find($order_id);

$render = static function ($type) {
    // The panel markup puts the order number inside its own element, so what a customer reads
    // is what this fixture checks, not the HTML around it.
    return trim(preg_replace('/\s+/', ' ', strip_tags((string) (new PageShortcodes())->render(['type' => $type]))));
};

try {
    if (empty($order['order_key'])) {
        $check(false, 'the fixture order has no key to arrive with');
    }

    $_GET = [
        'mds3_order_id' => (string) absint($order_id),
        'mds3_order_key' => (string) ($order['order_key'] ?? ''),
    ];
    $with_order = $render('thank-you');
    $check(
        1 === preg_match('/order\s*#/i', $with_order),
        'the Thank You page did not name the order the customer arrived with: ' . substr($with_order, 0, 220)
    );
    $check(
        false === stripos($with_order, 'Choose the grid you want'),
        'the Thank You page offered a grid chooser to a customer who already has an order: ' . substr($with_order, 0, 220)
    );

    // A page visited on its own answers with the grid the customer came for. With one active
    // grid there is nothing to choose, and asking someone to pick from a list of one is a worse
    // answer than the link they wanted - the chooser belongs to a site with more than one grid,
    // which is the case created below.
    $_GET = [];
    $without_order = $render('thank-you');
    $check(
        false !== stripos($without_order, 'Open grid'),
        'a Thank You page with no order in the address did not offer the site\'s one grid: ' . substr($without_order, 0, 220)
    );
    $check(
        false === stripos($without_order, 'Choose the grid you want'),
        'a Thank You page asked a customer to choose between one grid: ' . substr($without_order, 0, 220)
    );
    $check(
        1 !== preg_match('/order\s*#/i', $without_order),
        'a Thank You page with no order in the address invented one'
    );

    // The case the grid picker exists for: a site with more than one active grid, visited with
    // no order in the address. Until a second grid exists the fixture cannot see that case, so
    // it creates one and removes it again in the same block that restores everything else.
    $second_grid = $grids->create(array(
        'block_height' => 10,
        'block_width' => 10,
        'currency' => 'USD',
        'height' => 50,
        'price_per_block' => 1,
        'slug' => 'thank-you-panel-fixture-second-grid',
        'status' => 'active',
        'title' => 'Thank You Panel Fixture Second Grid',
        'width' => 50,
    ));
    if (is_wp_error($second_grid)) {
        $check(false, 'the fixture could not create the second grid it needs: ' . $second_grid->get_error_message());
    } else {
        $second_grid_id = (int) $second_grid->id();
        $_GET = [];
        $two_grids = $render('thank-you');
        $check(
            false !== stripos($two_grids, 'Choose the grid you want'),
            'a Thank You page on a site with two active grids did not offer the grid chooser: ' . substr($two_grids, 0, 220)
        );
        $check(
            false !== stripos($two_grids, 'Thank You Panel Fixture Second Grid'),
            'the grid chooser did not name the second active grid, so a customer could not pick it'
        );
    }

    // The same rule for the payment page, which is where an unpaid manual order is sent: their
    // order, with its own continue action, ahead of a list of grids.
    $_GET = [
        'mds3_order_id' => (string) absint($order_id),
        'mds3_order_key' => (string) ($order['order_key'] ?? ''),
    ];
    $payment_with_order = $render('payment');
    $check(
        1 === preg_match('/order\s*#/i', $payment_with_order),
        'the Payment page did not show the order the customer arrived with: ' . substr($payment_with_order, 0, 220)
    );
} finally {
    $_GET = $original_get;
    if (isset($second_grid_id) && $second_grid_id) {
        // The grid is removed with anything that pointed at it, including the page created for
        // it: a site left with an extra active grid changes what the next page test sees.
        foreach (array('placements', 'blocks', 'order_items') as $grid_table) {
            $wpdb->delete(DB::table($grid_table), array('grid_id' => absint($second_grid_id)));
        }
        (new GridRepository())->delete(absint($second_grid_id));
        foreach ($wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_name = %s", 'thank-you-panel-fixture-second-grid')) as $grid_page_id) {
            wp_delete_post(absint($grid_page_id), true);
        }
    }
    $wpdb->delete(DB::table('order_items'), ['order_id' => absint($order_id)]);
    $wpdb->delete(DB::table('orders'), ['id' => absint($order_id)]);
}

if ($fails) {
    fwrite(STDERR, implode("\n", $fails) . "\n");
    exit(1);
}

echo "MDS3 order-panel destination fixture passed.\n";
