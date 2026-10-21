<?php
/**
 * WP-CLI fixture: an order the older site was holding stays held after the import.
 *
 * Why this exists: Million Dollar Script 2 separated payment from publication. A PAID order
 * could sit unapproved - the grid's own setting decided whether it was, and the customer
 * copy said "your order will be published as soon as the payment is cleared" - while the
 * published flag also started the term clock. This plugin asks one question about a
 * creative: active, or held until someone publishes it. Importing from payment alone
 * therefore published work a site owner was still reviewing, with no way back (Vikunja #30,
 * llm-task 4a071d0c).
 *
 * The other half of the story is the admin: a held record needs somewhere to go. So the
 * order screen has to name the state, explain why, and offer the control.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/migration-held-creatives-fixture.php
 */

use MillionDollarScript\V3\Admin\Admin;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/support/mds2-source.php';

global $wpdb;

$prefix = $wpdb->prefix . 'mdsheld_';
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

mds2_source_create_schema($prefix);
$seed = mds2_source_seed($prefix);

/**
 * Start from no imported rows of our own, so every number below describes this run.
 */
$purge_previous_run = function () use ($prefix, $wpdb) {
    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT entity_type, mds3_id FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s',
        $prefix
    ), ARRAY_A);
    $order_ids = [];
    $grid_ids = [];
    foreach ((array) $rows as $row) {
        if ('order' === $row['entity_type']) {
            $order_ids[] = absint($row['mds3_id']);
        } elseif ('grid' === $row['entity_type']) {
            $grid_ids[] = absint($row['mds3_id']);
        } elseif ('advertiser_page' === $row['entity_type'] && absint($row['mds3_id'])) {
            wp_delete_post(absint($row['mds3_id']), true);
        }
    }
    if ($order_ids) {
        $in = implode(',', $order_ids);
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('order_items')) . ' WHERE order_id IN (' . $in . ')');
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id IN (' . $in . ')');
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('orders')) . ' WHERE id IN (' . $in . ')');
    }
    if ($grid_ids) {
        $in = implode(',', $grid_ids);
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('placements')) . ' WHERE grid_id IN (' . $in . ')');
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id IN (' . $in . ')');
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('price_rules')) . ' WHERE grid_id IN (' . $in . ')');
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('packages')) . ' WHERE grid_id IN (' . $in . ')');
        $wpdb->query('DELETE FROM ' . DB::ident(DB::table('grids')) . ' WHERE id IN (' . $in . ')');
    }
    $wpdb->delete(DB::table('migration_map'), ['source_prefix' => $prefix]);
    $wpdb->delete(DB::table('migration_runs'), ['source_prefix' => $prefix]);
};
$purge_previous_run();

/**
 * The shared dataset has paid records approved and pending records unapproved, which is the
 * easy case: the two flags agree. The shape that matters is a settled order the site was
 * still reviewing - paid money, unapproved creative. That is the row the fixture changes.
 */
$held_legacy_order_id = 2;   // seeded as status=completed, approved=Y
$shown_legacy_order_id = 1;  // seeded as status=paid, approved=Y - must stay showing
$wpdb->update($prefix . 'orders', ['approved' => 'N'], ['order_id' => $held_legacy_order_id]);
$check(
    'N' === (string) $wpdb->get_var($wpdb->prepare('SELECT approved FROM ' . DB::ident($prefix . 'orders') . ' WHERE order_id = %d', $held_legacy_order_id)),
    'the fixture could not mark a paid order unapproved in the source data'
);

$result = (new Importer())->import($prefix);
$check(!is_wp_error($result), 'the import failed: ' . (is_wp_error($result) ? $result->get_error_message() : 'unknown'));

/**
 * @param int $legacy_order_id an order id in the seeded Million Dollar Script 2 tables
 */
$target_order_id = static function (int $legacy_order_id) use ($prefix, $wpdb) {
    return absint($wpdb->get_var($wpdb->prepare(
        'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND entity_type = %s AND legacy_id = %s LIMIT 1",
        $prefix,
        'order',
        (string) $legacy_order_id
    )));
};

$placement_states = static function (int $order_id) {
    $rows = (new PlacementRepository())->for_order($order_id);
    $states = [];
    foreach ($rows as $row) {
        $states[] = (string) ($row['status'] ?? '');
    }

    return $states;
};

$held_order_id = $target_order_id($held_legacy_order_id);
$shown_order_id = $target_order_id($shown_legacy_order_id);
$check($held_order_id > 0 && $shown_order_id > 0, 'either settled order failed to import at all');

$held_states = $placement_states($held_order_id);
$shown_states = $placement_states($shown_order_id);

$check(
    $held_states && !in_array('active', $held_states, true),
    'a record the older site had not approved came out published anyway: ' . wp_json_encode($held_states)
);
$check(
    in_array('pending', $held_states, true),
    'the held record was not left awaiting review: ' . wp_json_encode($held_states)
);
$check(
    $shown_states && !in_array('pending', $shown_states, true),
    'an approved paid record was held when the older site showed it: ' . wp_json_encode($shown_states)
);

/**
 * The import has to say what it did, or the difference looks like a bug in the new site.
 */
$report = is_array($result['verification'] ?? null) ? $result['verification'] : [];
$held_notes = [];
foreach ((array) ($report['repairs'] ?? []) as $note) {
    if ('placement' === (string) ($note['entity'] ?? '') && false !== stripos((string) ($note['reason'] ?? ''), 'Held for review')) {
        $held_notes[] = (string) ($note['source_id'] ?? '');
    }
}
$check(
    in_array((string) $held_legacy_order_id, $held_notes, true),
    'the report does not name the record it held: ' . wp_json_encode($held_notes)
);

/* ---------------------------------------------------------------- admin side */

$admin = new Admin();

$held_order = (new MillionDollarScript\V3\Orders\OrderRepository())->find($held_order_id);
$shown_order = (new MillionDollarScript\V3\Orders\OrderRepository())->find($shown_order_id);

$held_panel = $capture($admin, Admin::class, 'order_detail_panel', [$held_order, true]);
$check(
    strpos($held_panel, 'Ad visibility') !== false,
    'the order screen does not state whether the artwork is showing'
);
$check(
    strpos($held_panel, 'Not published') !== false && strpos($held_panel, 'value="mds3_publish_placement"') !== false,
    'a held record does not say it is held or offer the way forward'
);
$check(
    strpos($held_panel, 'value="mds3_unpublish_placement"') === false,
    'the screen offers to take down an ad that is not up'
);
$check(
    strpos($held_panel, 'This grid reviews paid uploads') === false,
    'a record held because the old site had not approved it blames the grid setting instead'
);

$shown_panel = $capture($admin, Admin::class, 'order_detail_panel', [$shown_order, true]);
$check(
    strpos($shown_panel, 'Published') !== false && strpos($shown_panel, 'value="mds3_unpublish_placement"') !== false,
    'a published record does not say so or offer to take it down'
);
$check(
    strpos($shown_panel, 'Not published') === false,
    'a published record also reads as not published'
);

/**
 * The control itself. Only a settled order may change what is showing; an order that has
 * not paid has nothing to publish, whatever an old page of the admin screen used to say.
 */
$changed = $call($admin, Admin::class, 'apply_placement_publication', [
    ['id' => $held_order_id, 'status' => 'paid'],
    'active',
]);
$check((bool) $changed, 'publishing a settled order did nothing');
$check(
    (new PlacementRepository())->for_order($held_order_id) && !in_array('pending', $placement_states($held_order_id), true),
    'the record is still awaiting review after it was published'
);
$check(
    in_array('active', $placement_states($held_order_id), true),
    'publishing did not make the artwork show'
);

// And back down again, without touching the money state.
$call($admin, Admin::class, 'apply_placement_publication', [
    ['id' => $held_order_id, 'status' => 'paid'],
    'pending',
]);
$check(
    in_array('pending', $placement_states($held_order_id), true),
    'taking a published ad down left it showing'
);
$order_after = (new MillionDollarScript\V3\Orders\OrderRepository())->find($held_order_id);
$check(
    'paid' === (string) ($order_after['status'] ?? ''),
    'changing what is showing changed the payment state: ' . wp_json_encode([$order_after['status'] ?? null])
);

$unpaid = $call($admin, Admin::class, 'apply_placement_publication', [
    ['id' => $shown_order_id, 'status' => 'pending_payment'],
    'active',
]);
$check(
    false === $unpaid,
    'an order that has not paid was allowed to publish artwork'
);

// An unpaid order is not offered the control at all.
$pending_panel = $capture($admin, Admin::class, 'order_detail_panel', [['id' => $shown_order_id, 'status' => 'pending_payment', 'total' => 0, 'currency' => 'USD'], true]);
$check(
    strpos($pending_panel, 'mds3_publish_placement') === false,
    'the order screen offers to publish an order that has not paid'
);

echo wp_json_encode([
    'held_source_order' => $held_legacy_order_id,
    'held_target_order' => $held_order_id,
    'held_placement_states' => $placement_states($held_order_id),
    'shown_source_order' => $shown_legacy_order_id,
    'shown_placement_states' => $shown_states,
    'named_in_report' => $held_notes,
]) . PHP_EOL;

if ($fails) {
    fwrite(STDERR, implode(PHP_EOL, $fails) . PHP_EOL);
    exit(1);
}

echo 'MDS3 held creatives fixture passed.' . PHP_EOL;
exit(0);
