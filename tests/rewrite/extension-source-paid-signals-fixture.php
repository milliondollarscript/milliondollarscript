<?php
/**
 * WP-CLI fixture: what a paid signal is allowed to do to an order, an ad, and the blocks.
 *
 * Why this exists: extensions complete orders through one door - the payment-source facade - and
 * this release changed what sits behind that door. Core now publishes on a signal only when that
 * signal is the first one for the order, because a paid signal is not always a payment: the
 * subscriptions extension pays every cycle through it, WooCommerce fires payment_complete and
 * status-completed for the same order, and an admin can re-confirm an order that is already paid.
 * A second signal used to be able to put an ad back up that a site had deliberately taken down.
 *
 * It also separates the two kinds of source, which a reader cannot tell apart from the call site:
 * a source core owns ('mds-grid', where the source id IS the order) and a source an extension
 * owns ('bounty-engine', 'local-money-map', where the id is a row in the extension's own table).
 * Only the first changes a core order, its placements or its blocks. That boundary is what a site
 * owner means when they ask whether "the extension's order path" still works - so it is pinned
 * here rather than assumed.
 *
 * What this pins down:
 *   1. a first paid signal on a core-owned source pays the order, marks its blocks sold and puts
 *      the ad up;
 *   2. a second paid signal after the site took that ad down leaves it down;
 *   3. that second signal still leaves the order paid and does not start a second term clock;
 *   4. a source an extension owns changes nothing core owns: no order row, no placement, no block;
 *   5. cancelling an extension-owned source does not touch a core grid either.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/extension-source-paid-signals-fixture.php
 */

use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
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

// A grid of its own, so the result does not depend on what the site happens to have, and a grid
// that publishes without review - the hold case is the other fixture's subject.
$grid = (new GridRepository())->create([
    'title' => 'Extension source signals fixture',
    'slug' => 'mds-ext-source-signals-fixture',
    'width' => 200,
    'height' => 100,
    'block_width' => 100,
    'block_height' => 100,
    'settings' => ['auto_approve' => 'yes', 'auto_publish' => 'yes'],
]);
if (is_wp_error($grid) || empty($grid)) {
    echo "This fixture needs to create a grid.\n";
    exit(1);
}
$grid_id = is_object($grid) ? absint($grid->id()) : absint($grid);

$wpdb->insert(DB::table('blocks'), [
    'grid_id' => $grid_id,
    'x' => 0,
    'y' => 0,
    'width' => 100,
    'height' => 100,
    'status' => 'available',
    'created_at' => current_time('mysql', true),
    'updated_at' => current_time('mysql', true),
]);
$free_block = (int) $wpdb->insert_id;
if ($free_block <= 0) {
    echo "This fixture needs to create a block.\n";
    exit(1);
}
$orders = new OrderRepository();
$placements = new PlacementRepository();

// A placement needs artwork, so the fixture brings its own rather than borrowing a picture from
// whichever site it happens to run on.
$upload = wp_upload_bits('mds-ext-source-signals-fixture.png', null, base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
));
if (empty($upload['file'])) {
    echo "This fixture needs to write a test image.\n";
    exit(1);
}
$attachment_id = wp_insert_attachment([
    'post_title' => 'Extension source signals fixture artwork',
    'post_mime_type' => 'image/png',
    'post_status' => 'inherit',
], $upload['file']);
if (is_wp_error($attachment_id) || !absint($attachment_id)) {
    echo "This fixture needs to register a test image.\n";
    exit(1);
}

$cleanup = static function () use ($wpdb, &$created_order_ids, &$created_placement_ids, $grid_id, $attachment_id) {
    foreach ((array) $created_placement_ids as $placement_id) {
        $page_id = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT public_post_id FROM ' . DB::table('placements') . ' WHERE id = %d',
            absint($placement_id)
        ));
        if ($page_id > 0) {
            wp_delete_post($page_id, true);
        }
    }
    foreach ((array) $created_order_ids as $order_id) {
        foreach (['placements', 'order_items'] as $table) {
            $wpdb->delete(DB::table($table), ['order_id' => absint($order_id)]);
        }
        $wpdb->delete(DB::table('orders'), ['id' => absint($order_id)]);
    }
    $wpdb->delete(DB::table('blocks'), ['grid_id' => absint($grid_id)]);
    $wpdb->delete(DB::table('grids'), ['id' => absint($grid_id)]);
    wp_delete_post(absint($attachment_id), true);
};

$created_order_ids = [];
$created_placement_ids = [];

$make_order_with_ad = static function () use ($orders, $placements, $grid_id, $free_block, $attachment_id, &$created_order_ids, &$created_placement_ids) {
    $order_id = $orders->create([[
        'grid_id' => $grid_id,
        'block_id' => $free_block,
        'item_type' => 'block',
        'quantity' => 1,
        'unit_price' => 25.0,
        'total' => 25.0,
    ]], [
        'email' => 'extension-source-signals@example.test',
        'status' => 'pending_payment',
        'total' => 25.0,
        'currency' => 'USD',
    ]);
    if (is_wp_error($order_id) || !absint($order_id)) {
        throw new RuntimeException('Could not create the fixture order: ' . (is_wp_error($order_id) ? $order_id->get_error_message() : 'no id'));
    }
    $created_order_ids[] = absint($order_id);

    $placement_id = $placements->create([
        'grid_id' => $grid_id,
        'block_id' => $free_block,
        'order_id' => absint($order_id),
        'attachment_id' => absint($attachment_id),
        'x' => 0,
        'y' => 0,
        'width' => 1,
        'height' => 1,
        'status' => 'pending',
    ]);
    if (is_wp_error($placement_id) || !absint($placement_id)) {
        throw new RuntimeException('Could not create the fixture placement: ' . (is_wp_error($placement_id) ? $placement_id->get_error_message() : 'no id'));
    }
    $created_placement_ids[] = absint($placement_id);

    return absint($order_id);
};

$placement_states = static function ($order_id) use ($placements) {
    $states = [];
    foreach ((array) $placements->for_order(absint($order_id)) as $row) {
        $states[] = (string) ($row['status'] ?? '');
    }
    sort($states);
    return $states;
};

$block_status = static function () use ($wpdb, $free_block) {
    return (string) $wpdb->get_var($wpdb->prepare(
        'SELECT status FROM ' . DB::table('blocks') . ' WHERE id = %d',
        $free_block
    ));
};

try {
    // 1 and 2 and 3: one core-owned source, three signals.
    $order_id = $make_order_with_ad();
    // Holding the space for the order in progress is part of what an order does; it is also how
    // a later paid signal finds which blocks to mark.
    $check('reserved' === $block_status(), 'Creating an order for a block did not hold that space for it: got ' . $block_status());

    $first = Payments::mark_source_paid('mds-grid', $order_id, ['provider' => 'standalone']);
    $check(true === $first, 'The first paid signal was refused for a core-owned source.');

    $order = $orders->find($order_id);
    $check('paid' === (string) ($order['status'] ?? ''), 'A first paid signal did not pay the order.');
    $check(['active'] === $placement_states($order_id), 'A paid order did not put its ad up: ' . wp_json_encode($placement_states($order_id)));
    $check('sold' === $block_status(), 'A paid order did not mark its block sold.');
    $first_paid_at = (string) (json_decode((string) ($order['metadata'] ?? '{}'), true)['paid_at'] ?? '');
    $check('' !== $first_paid_at, 'The order did not record when it was paid.');

    // The site takes the ad down on purpose. Nothing that announces payment again - a renewal
    // cycle, the WooCommerce pair, an admin re-confirming - may decide otherwise.
    $placements->update_status_by_order($order_id, 'pending');
    $check(['pending'] === $placement_states($order_id), 'The fixture could not reach the unpublished state it needs.');

    $second = Payments::mark_source_paid('mds-grid', $order_id, ['provider' => 'standalone']);
    $check(true === $second, 'A repeated paid signal was refused.');

    $order = $orders->find($order_id);
    $check('paid' === (string) ($order['status'] ?? ''), 'A repeated paid signal moved an already-paid order off paid.');
    $again = (string) (json_decode((string) ($order['metadata'] ?? '{}'), true)['paid_at'] ?? '');
    $check($first_paid_at === $again, 'A repeated paid signal started a second term clock.');
    $check(['pending'] === $placement_states($order_id), 'A repeated paid signal put back an ad the site had taken down: ' . wp_json_encode($placement_states($order_id)));

    // 4 and 5: a source an extension owns. The extension keeps its own record; core's grid must
    // not move at all, and nothing core owns should be created or changed behind the call.
    $before_orders = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::table('orders'));
    $before_placements = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::table('placements'));

    $extension_paid = Payments::mark_source_paid('bounty-engine', 987654321, ['provider' => 'subscription_allowance']);
    $check(true === $extension_paid, 'An extension-owned source was refused a paid signal.');
    $check($before_orders === (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::table('orders')), 'An extension-owned paid signal created or changed core order rows.');
    $check($before_placements === (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::table('placements')), 'An extension-owned paid signal created or changed core placement rows.');

    Payments::mark_source_cancelled('local-money-map', 987654322, []);
    $check($before_orders === (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::table('orders')), 'Cancelling an extension-owned source touched core order rows.');
    $check('sold' === $block_status(), 'An extension-owned signal changed the grid blocks: got ' . $block_status());
} catch (Throwable $error) {
    $fails[] = 'The fixture could not run: ' . $error->getMessage();
} finally {
    $cleanup();
}

if ($fails) {
    fwrite(STDERR, "extension source paid-signals fixture failed:\n- " . implode("\n- ", $fails) . "\n");
    exit(1);
}

echo "extension source paid-signals fixture passed.\n";
