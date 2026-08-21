<?php
/**
 * WP-CLI fixture for admin order placement moves.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/order-placement-move-fixture.php
 */

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Grid\PriceRuleRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\OrderPlacementMover;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Orders\ReservationService;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

function mds3_order_move_fixture_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function mds3_order_move_fixture_assert_error($result, $code, $message) {
    mds3_order_move_fixture_assert(is_wp_error($result), $message . ' Expected an error result.');
    mds3_order_move_fixture_assert($code === $result->get_error_code(), $message . ' Received ' . $result->get_error_code() . '.');
}

$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$admin) {
    throw new RuntimeException('Order move fixture requires an administrator user.');
}
wp_set_current_user(absint($admin[0]));

global $wpdb;

$grid_repo = new GridRepository();
$grid = null;
$order_id = 0;
$placement_id = 0;

try {
    $currency = Currency::current_code();
    $grid = $grid_repo->create([
        'title' => 'Order Move Fixture Grid',
        'width' => 100,
        'height' => 100,
        'block_width' => 10,
        'block_height' => 10,
        'price_per_block' => 1,
        'currency' => $currency,
        'status' => 'active',
    ]);
    if (is_wp_error($grid)) {
        throw new RuntimeException('Could not create order move fixture grid: ' . $grid->get_error_message());
    }

    $reservation = (new ReservationService())->reserve($grid, [
        ['row' => 1, 'col' => 1],
        ['row' => 1, 'col' => 2],
        ['row' => 2, 'col' => 1],
        ['row' => 2, 'col' => 2],
    ], ['user_id' => absint($admin[0])]);
    if (is_wp_error($reservation)) {
        throw new RuntimeException('Could not create order move fixture reservation: ' . $reservation->get_error_message());
    }

    $order_id = absint($reservation['order']['id'] ?? 0);
    mds3_order_move_fixture_assert($order_id > 0, 'Fixture reservation did not create an order.');
    $order_repo = new OrderRepository();
    $original_order = $order_repo->find($order_id);
    $original_total = (float) ($original_order['total'] ?? 0);
    $order_repo->update($order_id, ['status' => 'paid']);
    (new BlockRepository())->mark_by_order($order_id, 'sold');

    $items = $order_repo->items($order_id);
    $placement_id = (new PlacementRepository())->create([
        'grid_id' => $grid->id(),
        'block_id' => absint($items[0]['block_id'] ?? 0),
        'order_id' => $order_id,
        'user_id' => absint($admin[0]),
        'attachment_id' => 1,
        'x' => 10,
        'y' => 10,
        'width' => 20,
        'height' => 20,
        'status' => 'active',
    ]);
    if (is_wp_error($placement_id)) {
        throw new RuntimeException('Could not create order move fixture placement: ' . $placement_id->get_error_message());
    }

    $price_rule = (new PriceRuleRepository())->save([
        'grid_id' => $grid->id(),
        'row_from' => 5,
        'row_to' => 6,
        'col_from' => 5,
        'col_to' => 6,
        'price' => 3,
        'currency' => $currency,
        'status' => 'active',
    ]);
    if (is_wp_error($price_rule)) {
        throw new RuntimeException('Could not create order move fixture price zone: ' . $price_rule->get_error_message());
    }

    $block_repo = new BlockRepository();
    $unavailable = $block_repo->set_region_status($grid, [
        'row_from' => 7,
        'row_to' => 7,
        'col_from' => 7,
        'col_to' => 7,
    ], 'unavailable');
    if (is_wp_error($unavailable)) {
        throw new RuntimeException('Could not create order move fixture unavailable region: ' . $unavailable->get_error_message());
    }

    $occupied = $block_repo->materialize($grid, 8, 8);
    if (is_wp_error($occupied)) {
        throw new RuntimeException('Could not create order move fixture occupied block: ' . $occupied->get_error_message());
    }
    $occupied = $block_repo->reserve($occupied, absint($admin[0]), 60);
    if (is_wp_error($occupied)) {
        throw new RuntimeException('Could not reserve order move fixture occupied block: ' . $occupied->get_error_message());
    }

    $mover = new OrderPlacementMover();
    mds3_order_move_fixture_assert_error($mover->preview($order_id, $grid->id(), 1, 1), 'mds3_order_move_same_position', 'Same-position move was not rejected.');
    mds3_order_move_fixture_assert_error($mover->preview($order_id, $grid->id(), 9, 9), 'mds3_order_move_out_of_bounds', 'Out-of-bounds move was not rejected.');
    mds3_order_move_fixture_assert_error($mover->preview($order_id, $grid->id(), 7, 7), 'mds3_order_move_unavailable', 'Unavailable-region move was not rejected.');
    mds3_order_move_fixture_assert_error($mover->preview($order_id, $grid->id(), 8, 8), 'mds3_order_move_occupied', 'Occupied move was not rejected.');

    $preview = $mover->preview($order_id, $grid->id(), 5, 5);
    if (is_wp_error($preview)) {
        throw new RuntimeException('Valid order move preview failed: ' . $preview->get_error_message());
    }
    mds3_order_move_fixture_assert(4 === absint($preview['block_count'] ?? 0), 'Move preview did not preserve the four-block footprint.');
    mds3_order_move_fixture_assert(8.0 === (float) ($preview['price_difference'] ?? 0), 'Move preview did not report the target price-zone difference.');
    mds3_order_move_fixture_assert(!empty($preview['preserves_order_total']), 'Move preview did not state that the order total is preserved.');

    $moved = $mover->move($order_id, $grid->id(), 5, 5, [
        'source' => 'fixture',
        'user_id' => absint($admin[0]),
    ]);
    if (is_wp_error($moved)) {
        throw new RuntimeException('Valid order move failed: ' . $moved->get_error_message());
    }

    $updated_order = $order_repo->find($order_id);
    mds3_order_move_fixture_assert('paid' === ($updated_order['status'] ?? ''), 'Order status changed during placement move.');
    mds3_order_move_fixture_assert($original_total === (float) ($updated_order['total'] ?? -1), 'Order total changed during placement move.');

    $updated_items = $order_repo->items($order_id);
    $coordinates = [];
    foreach ($updated_items as $item) {
        $block = $block_repo->find(absint($item['block_id'] ?? 0));
        mds3_order_move_fixture_assert($block && 'sold' === ($block['status'] ?? ''), 'Target block did not retain sold status.');
        mds3_order_move_fixture_assert($order_id === absint($block['order_id'] ?? 0), 'Target block did not retain order ownership.');
        $coordinates[] = absint($block['y'] ?? 0) . ':' . absint($block['x'] ?? 0);
    }
    sort($coordinates);
    mds3_order_move_fixture_assert(['50:50', '50:60', '60:50', '60:60'] === $coordinates, 'Moved blocks did not preserve the original footprint.');

    foreach ([[10, 10], [20, 10], [10, 20], [20, 20]] as $pixel) {
        $old = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id = %d AND x = %d AND y = %d',
            $grid->id(),
            $pixel[0],
            $pixel[1]
        ), ARRAY_A);
        mds3_order_move_fixture_assert($old && 'available' === ($old['status'] ?? '') && !absint($old['order_id'] ?? 0), 'Old source block was not released.');
    }

    $placement = (new PlacementRepository())->find($placement_id);
    mds3_order_move_fixture_assert(50 === absint($placement['x'] ?? 0) && 50 === absint($placement['y'] ?? 0), 'Placement artwork coordinates did not move with the blocks.');
    mds3_order_move_fixture_assert('active' === ($placement['status'] ?? ''), 'Placement status changed during move.');

    $metadata = json_decode((string) ($updated_order['metadata'] ?? ''), true);
    $events = is_array($metadata['placement_events'] ?? null) ? $metadata['placement_events'] : [];
    mds3_order_move_fixture_assert($events && 'moved' === ($events[count($events) - 1]['action'] ?? ''), 'Placement move audit event was not recorded.');

    echo "Order placement move fixture passed.\n";
} finally {
    if ($order_id) {
        $wpdb->delete(DB::table('placements'), ['order_id' => $order_id]);
        $wpdb->delete(DB::table('order_items'), ['order_id' => $order_id]);
        $wpdb->delete(DB::table('orders'), ['id' => $order_id]);
    }
    if ($grid) {
        $wpdb->delete(DB::table('price_rules'), ['grid_id' => $grid->id()]);
        $wpdb->delete(DB::table('blocks'), ['grid_id' => $grid->id()]);
        $grid_repo->delete($grid->id());
    }
}
