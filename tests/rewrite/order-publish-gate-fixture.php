<?php
/**
 * WP-CLI fixture: an integration may hold a finished ad off the grid, and only itself.
 *
 * The paid pixel and the ad that appears on it are two different decisions. Selling the
 * pixel belongs to core - an unsold pixel is one the site may sell again - so the gate
 * asked at the point of publication is about the placement alone. This fixture is the
 * contract for that question: a site with no integration publishing is unaffected, a
 * hold keeps the ad out of the grid's own display query, an answer that is not literal
 * true holds the ad rather than being skipped, and the integration can publish the same
 * ad later by asking the same way core does.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/order-publish-gate-fixture.php
 */

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Orders\PublishGate;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$grid = (new GridRepository())->first_active();
if (!$grid) {
    fwrite(STDERR, "MDS3-SKIP: this fixture needs an active grid; the suite runs it against a site that has one (scripts/wp-fixture-suite.sh, or the seeded sandbox stack).\n");
    return;
}

$geometry = $grid->geometry();
$block = null;
$blocks_repo = new BlockRepository();
// Blocks exist as rows once something has claimed or marked them, so ask for the
// corner block the way the grid itself would, and take the first one that is free.
for ($attempt = 0; $attempt < 5 && null === $block; $attempt++) {
    $row = max(0, $geometry->rows() - 1 - $attempt);
    $col = max(0, $geometry->columns() - 1);
    $candidate = $blocks_repo->materialize($grid, $row, $col);
    if (!is_wp_error($candidate) && 'available' === (string) ($candidate['status'] ?? '')) {
        $block = $candidate;
    }
}
if (null === $block) {
    fwrite(STDERR, "MDS3-SKIP: this fixture needs one free block on the active grid, and the corner blocks of grid {$grid->id()} are all claimed.\n");
    return;
}

$uploads = wp_upload_bits('mds3-publish-gate-fixture.png', '', 'PNG');
if (empty($uploads['file'])) {
    throw new RuntimeException('The fixture could not write the image it needs.');
}
$attachment_id = (int) wp_insert_attachment(array(
    'post_mime_type' => 'image/png',
    'post_status' => 'inherit',
    'post_title' => 'MDS3 publish gate fixture',
), $uploads['file']);
if (!$attachment_id) {
    throw new RuntimeException('The fixture could not register its image.');
}

$order_id = (new OrderRepository())->create(array(), array(
    'email' => 'publish-gate@example.test',
    'status' => 'paid',
));
if (is_wp_error($order_id) || !absint($order_id)) {
    throw new RuntimeException('The fixture could not create its order: ' . (is_wp_error($order_id) ? $order_id->get_error_message() : 'no id'));
}

$placement_id = (new PlacementRepository())->create(array(
    'attachment_id' => $attachment_id,
    'block_id' => (int) $block['id'],
    'grid_id' => $grid->id(),
    'height' => 1,
    'order_id' => absint($order_id),
    'status' => 'pending',
    'width' => 1,
    'x' => (int) $block['x'],
    'y' => (int) $block['y'],
));
if (is_wp_error($placement_id) || !absint($placement_id)) {
    throw new RuntimeException('The fixture could not place its ad: ' . (is_wp_error($placement_id) ? $placement_id->get_error_message() : 'no id'));
}

$asked = array();
$answer = null;
$listener = static function ($eligible, $asked_order_id, $context) use (&$asked, &$answer) {
    $asked[] = array((int) $asked_order_id, (string) $context);
    return null === $answer ? $eligible : $answer;
};

$status = static function () use ($wpdb, $placement_id) {
    return (string) $wpdb->get_var('SELECT status FROM ' . DB::table('placements') . ' WHERE id = ' . absint($placement_id));
};

$on_the_grid = static function () use ($wpdb, $grid, $placement_id) {
    return absint($wpdb->get_var(
        'SELECT COUNT(*) FROM ' . DB::table('placements') . ' p INNER JOIN ' . DB::table('grids') . ' g ON g.id = p.grid_id WHERE p.id = ' . absint($placement_id) . " AND p.status = 'active' AND g.status = 'active'"
    )) > 0;
};

try {
    // 1. A site with no integration answering is unaffected: the ad would publish.
    if (!PublishGate::eligible($order_id, PublishGate::PAYMENT_CAPTURED)) {
        throw new RuntimeException('An order with nothing holding it was refused a place on the grid.');
    }
    if (array() !== $asked) {
        throw new RuntimeException('The gate asked a listener that does not exist.');
    }

    // 2. An integration holding the ad keeps it off the grid, and the block stays sold.
    add_filter(PublishGate::HOOK, $listener, 10, 3);
    $answer = false;
    if (PublishGate::publish($order_id, PublishGate::CUSTOMER_CONFIRMED)) {
        throw new RuntimeException('An integration said not yet and the ad went up anyway.');
    }
    if ('pending' !== $status()) {
        throw new RuntimeException('A held ad left its placement in state ' . $status() . '.');
    }
    if ($on_the_grid()) {
        throw new RuntimeException('A held ad was still in the grid display query.');
    }
    if (array(array(absint($order_id), PublishGate::CUSTOMER_CONFIRMED)) !== $asked) {
        throw new RuntimeException('The gate did not say which order it was asking about and why: ' . wp_json_encode($asked) . '.');
    }

    // 3. An answer that only looks like agreement holds the ad, and is not skipped.
    $asked = array();
    $answer = 'yes';
    if (PublishGate::publish($order_id, PublishGate::EXTENSION_READY)) {
        throw new RuntimeException('An integration returned the string "yes" and core read that as agreement.');
    }
    if ('pending' !== $status()) {
        throw new RuntimeException('A lookalike answer published the ad: ' . $status() . '.');
    }
    if (array(array(absint($order_id), PublishGate::EXTENSION_READY)) !== $asked) {
        throw new RuntimeException('A held ad did not record the reason it was held: ' . wp_json_encode($asked) . '.');
    }

    // 4. When its own moment arrives, the integration publishes through the public
    // facade, which asks the same question core asked at checkout.
    $asked = array();
    $answer = true;
    if (!\MillionDollarScript\Core\Orders::publish_ad($order_id, 'time_capsule_reveal')) {
        throw new RuntimeException('An integration that said ready could not put the ad up.');
    }
    if ('active' !== $status()) {
        throw new RuntimeException('A released ad is in state ' . $status() . '.');
    }
    if (!$on_the_grid()) {
        throw new RuntimeException('A released ad is still not in the grid display query.');
    }
    if (array(array(absint($order_id), 'time_capsule_reveal')) !== $asked) {
        throw new RuntimeException('The reason an integration named was not the reason core passed: ' . wp_json_encode($asked) . '.');
    }

    // 5. The sale is not what is gated: asking about the ad never touches the pixel.
    $block_status = (string) $wpdb->get_var('SELECT status FROM ' . DB::table('blocks') . ' WHERE id = ' . absint($block['id']));
    if ($block_status !== (string) $block['status']) {
        throw new RuntimeException('A question about the ad changed what the pixel itself was marked: ' . $block['status'] . ' became ' . $block_status . '.');
    }

    echo "order-publish-gate fixture passed\n";
} finally {
    remove_filter(PublishGate::HOOK, $listener, 10);
    $wpdb->delete(DB::table('placements'), array('id' => absint($placement_id)));
    $wpdb->delete(DB::table('blocks'), array('id' => absint($block['id'])));
    (new OrderRepository())->delete_permanently(array(absint($order_id)));
    wp_delete_post($attachment_id, true);
}
