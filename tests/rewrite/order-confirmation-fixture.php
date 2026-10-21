<?php
/**
 * WP-CLI fixture: the customer's review step, and what confirming an order does.
 *
 * Why this exists: the setting that asks customers to confirm an order came from the older
 * plugin (milliondollarscript-two stored it as Options.php:120 and chose the destination after
 * the upload form from it, Classes/Forms/Forms.php:526), but nothing in MDS3 read it - a control
 * with no effect. The tester asked for that step back (Vikunja #29 item 3), and Ryan's rule for
 * the default is the opposite of MDS2's: sites ask for fewer steps to payment, not more, so a
 * fresh install is off and a migrated MDS2 site keeps what it was running.
 *
 * What this pins down:
 *   1. the default is off for a fresh install, and a migration carries the MDS2 answer;
 *   2. an order counts as awaiting its customer only when the ad is on it - a reservation nobody
 *      has filled in is not "waiting to be confirmed";
 *   3. a site that asks for the step stops the customer on the Confirm Order page first, and a
 *      site without such a page skips the step instead of inventing a page;
 *   4. confirming is what moves the order forward - to payment, or published when it owes nothing -
 *      and a site whose payment is handled elsewhere is left alone;
 *   5. the review screen offers the Confirm control and the other screens do not.
 *
 * The successful Confirm click itself redirects, which a CLI fixture cannot follow, so that part
 * is verified in a browser on the development site (llm-task 798e3f93).
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/order-confirmation-fixture.php
 */

use MillionDollarScript\V3\Grid\GridAjax;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\OrderConfirmation;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Pages\PageShortcodes;
use MillionDollarScript\V3\Settings\SettingsSchema;
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

$call = static function ($scope, $class, $method, array $args = []) {
    $method = new ReflectionMethod($class, $method);
    $method->setAccessible(true);

    return $method->invokeArgs($scope, $args);
};

$settings_option = 'mds3_settings';
$stored_settings = get_option($settings_option, []);
$stored_settings = is_array($stored_settings) ? $stored_settings : [];
$confirm_option = 'mds3_page_confirm-order_id';
$order_page_option = 'mds3_page_upload_id';
$originals = [
    $confirm_option => get_option($confirm_option, 0),
    $order_page_option => get_option($order_page_option, 0),
];
$original_get = $_GET;
$original_post = $_POST;
$original_method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';

/**
 * Set the site's settings for one case, keeping everything else that is stored, and hand back
 * the same array the code under test would read. A fixture that decided one list and stored
 * another would be testing its own bookkeeping.
 */
$with_settings = static function (array $changes) use ($stored_settings) {
    $merged = array_merge($stored_settings, $changes);
    update_option('mds3_settings', $merged, false);

    return $merged;
};

/**
 * A customer who has uploaded their artwork and not confirmed it: the order is still a
 * reservation, and the placement is the evidence that they finished their part.
 */
$order_id = (new OrderRepository())->create([], [
    'email' => 'review-step@example.test',
    'status' => 'reserved',
    'block_status' => 'reserved',
    'total' => '12.00',
    'currency' => 'USD',
]);
if (is_wp_error($order_id) || !absint($order_id)) {
    throw new RuntimeException('Could not create the fixture order: ' . (is_wp_error($order_id) ? $order_id->get_error_message() : 'no id'));
}

$attachment_id = (int) wp_insert_post([
    'post_type' => 'attachment',
    'post_title' => 'MDS3 fixture review artwork',
    'post_mime_type' => 'image/png',
    'post_status' => 'inherit',
]);
$grids = (new GridRepository())->all();
$grid_id = $grids ? absint($grids[0]->id()) : 0;
if (!$grid_id) {
    throw new RuntimeException('This site has no grid to place the fixture ad on.');
}
$placement_repo = new PlacementRepository();
$placement_id = $placement_repo->create([
    'grid_id' => $grid_id,
    'order_id' => absint($order_id),
    'attachment_id' => $attachment_id,
    'x' => 0,
    'y' => 0,
    'width' => 2,
    'height' => 1,
    'status' => 'pending',
]);

$empty_order_id = (new OrderRepository())->create([], [
    'email' => 'nobody-here-yet@example.test',
    'status' => 'reserved',
    'block_status' => 'reserved',
]);

$cleanup = static function () use ($wpdb, $order_id, $empty_order_id, $placement_id, $attachment_id, $stored_settings, $originals, $original_get, $original_post, $original_method) {
    $_GET = $original_get;
    $_POST = $original_post;
    $_SERVER['REQUEST_METHOD'] = $original_method;
    if (null !== $placement_id) {
        $wpdb->delete(DB::table('placements'), ['id' => absint($placement_id)]);
    }
    if ($attachment_id > 0) {
        wp_delete_post($attachment_id, true);
    }
    $wpdb->delete(DB::table('order_items'), ['order_id' => absint($order_id)]);
    $wpdb->delete(DB::table('orders'), ['id' => absint($order_id)]);
    $wpdb->delete(DB::table('orders'), ['id' => absint($empty_order_id)]);
    update_option('mds3_settings', $stored_settings, false);
    foreach ($originals as $option => $value) {
        update_option($option, $value, false);
    }
};

try {
    if (is_wp_error($placement_id) || !absint($placement_id)) {
        throw new RuntimeException('Could not create the fixture placement: ' . (is_wp_error($placement_id) ? $placement_id->get_error_message() : 'no id'));
    }

    $orders = new OrderRepository();
    $order = $orders->find($order_id);
    $empty_order = $orders->find($empty_order_id);
    $confirmation = new OrderConfirmation();

    /**
     * 1. The defaults: a new site does not add a step nobody asked for. A migrated MDS2
     *    site keeps the step it was already running, and a choice made here survives a
     *    second migration run.
     */
    $defaults = SettingsSchema::defaults();
    $check(
        !array_key_exists('confirm-orders', $defaults) || 'no' === $defaults['confirm-orders'],
        'a fresh install now asks customers to confirm their orders: ' . var_export($defaults['confirm-orders'] ?? null, true)
    );
    $check(
        'yes' === SettingsSchema::legacy_confirm_orders([], []),
        'an MDS2 site that never stored the option was running the review step, and the migration did not say so'
    );
    $check(
        'no' === SettingsSchema::legacy_confirm_orders(['_milliondollarscript_confirm-orders' => 'no'], []),
        'a site that had turned the step off was switched back on by migrating'
    );
    // Sitting on this plugin's shipped default is not a decision, so it does not
    // outweigh what the migrated site was actually doing to its customers.
    $check(
        'yes' === SettingsSchema::legacy_confirm_orders([], ['confirm-orders' => $defaults['confirm-orders']]),
        'the migration dropped a step the old site had been running, because nobody here had chosen anything'
    );

    // Which answer came from where is what the report has to be able to say.
    $carried = SettingsSchema::confirm_orders_choice(['_milliondollarscript_confirm-orders' => 'no'], []);
    $check('no' === $carried['value'] && 'mds2' === $carried['source'], 'the old site answer was not carried or not attributed to it');
    $kept = SettingsSchema::confirm_orders_choice(['_milliondollarscript_confirm-orders' => 'no'], ['confirm-orders' => 'yes']);
    $check('yes' === $kept['value'] && 'mds3' === $kept['source'], 'a choice made in Million Dollar Script 3 did not win');
    $assumed = SettingsSchema::confirm_orders_choice([], []);
    $check('yes' === $assumed['value'] && 'mds2_default' === $assumed['source'], 'the old plugin default was not attributed to it');

    /**
     * 1b. The migration report says what the site will do to customers and where that
     *    came from, instead of leaving an owner to compare two plugins' defaults.
     */
    $notes = [
        SettingsSchema::describe_confirm_orders_choice(['_milliondollarscript_confirm-orders' => 'no'], []),
        SettingsSchema::describe_confirm_orders_choice(['_milliondollarscript_confirm-orders' => 'no'], ['confirm-orders' => 'yes']),
        SettingsSchema::describe_confirm_orders_choice([], []),
    ];
    foreach ($notes as $note_index => $note) {
        $check('' !== trim((string) $note), 'the report says nothing about order confirmation (case ' . $note_index . ')');
        $check(false !== strpos((string) $note, 'Settings > Orders'), 'the report does not say where to change it (case ' . $note_index . '): ' . var_export($note, true));
    }
    $check(3 === count(array_unique($notes)), 'the report gives the same words to different situations, so the reader cannot tell what happened to their site');

    /**
     * 2. Only an order that carries an ad is waiting on its customer.
     */
    $with_settings(['confirm-orders' => 'no']);
    $check(
        !$confirmation->await_confirmation($order, $with_settings(['confirm-orders' => 'no'])),
        'the review step was asked for on a site that has it off'
    );
    $site_settings = $with_settings(['confirm-orders' => 'yes']);
    $check(
        $confirmation->await_confirmation($order, $site_settings),
        'a customer who uploaded their artwork was not treated as needing to confirm it'
    );
    $check(
        !$confirmation->await_confirmation($empty_order, $site_settings),
        'a reservation nobody has filled in was treated as waiting to be confirmed'
    );

    /**
     * 3. Where the save sends them. The GridAjax decision reads the stored settings, so this is
     *    the real storefront path, not a copy of it.
     */
    $ajax = new GridAjax();
    $decide = static function (array $the_order) use ($ajax, $call) {
        return (string) $call($ajax, GridAjax::class, 'post_save_redirect', [$the_order, ['checkout_url' => ''], false, true]);
    };

    $confirm_page_id = wp_insert_post([
        'post_title' => 'MDS3 fixture confirm order',
        'post_type' => 'page',
        'post_status' => 'publish',
    ]);
    if (is_wp_error($confirm_page_id) || !absint($confirm_page_id)) {
        $check(false, 'the fixture could not create a Confirm Order page');
        update_option($confirm_option, 0, false);
    } else {
        update_option($confirm_option, absint($confirm_page_id), false);
        $expected = add_query_arg(
            ['mds3_order_id' => absint($order_id), 'mds3_order_key' => (string) ($order['order_key'] ?? '')],
            (string) get_permalink(absint($confirm_page_id))
        );
        $sent = $decide($order);
        $check(
            $expected === $sent,
            'the review step did not send the customer to the Confirm Order page: ' . $sent . ' (expected ' . $expected . ')'
        );
    }

    update_option($confirm_option, 0, false);
    $check(
        '' === $decide($order),
        'a site without a Confirm Order page was sent somewhere invented: ' . $decide($order)
    );

    /**
     * 4. What confirming does. A paid-by-hand order moves to payment; a site that marks
     *    orders paid itself publishes it; and a site whose money is handled elsewhere is left
     *    to its payment provider.
     */
    $with_settings(['confirm-orders' => 'yes', 'auto-approve' => 'no', 'checkout-url' => '']);
    $held_back = $confirmation->advance($orders, $placement_repo, $order, $with_settings(['confirm-orders' => 'yes', 'auto-approve' => 'no', 'checkout-url' => '']));
    $check(!is_wp_error($held_back), 'confirming an order failed: ' . (is_wp_error($held_back) ? $held_back->get_error_message() : ''));
    $check(
        is_array($held_back) && 'pending_payment' === sanitize_key((string) ($held_back['status'] ?? '')),
        'confirming an order that owes money did not move it to payment: ' . (is_array($held_back) ? (string) $held_back['status'] : 'not updated')
    );

    $orders->update(absint($order_id), ['status' => 'reserved']);
    $placement_repo->update_status_by_order(absint($order_id), 'pending');
    $order = $orders->find($order_id);
    $approved = $with_settings(['confirm-orders' => 'yes', 'auto-approve' => 'yes', 'checkout-url' => '']);
    $published = $confirmation->advance($orders, $placement_repo, $order, $approved);
    $check(
        is_array($published) && 'paid' === sanitize_key((string) ($published['status'] ?? '')),
        'confirming an order the site approves itself did not complete it: ' . (is_array($published) ? (string) $published['status'] : 'not updated')
    );
    $placements_now = $placement_repo->for_order(absint($order_id));
    $check(
        !empty($placements_now) && 'active' === sanitize_key((string) ($placements_now[0]['status'] ?? '')),
        'confirming an approved order did not put the ad on the grid'
    );

    $orders->update(absint($order_id), ['status' => 'reserved']);
    $placement_repo->update_status_by_order(absint($order_id), 'pending');
    $order = $orders->find($order_id);
    $handled_elsewhere = $with_settings(['confirm-orders' => 'yes', 'auto-approve' => 'no', 'checkout-url' => 'https://example.test/pay']);
    $untouched = $confirmation->advance($orders, $placement_repo, $order, $handled_elsewhere);
    $check(
        is_array($untouched) && 'reserved' === sanitize_key((string) ($untouched['status'] ?? '')),
        'confirming took over an order whose payment is handled by a payment page'
    );

    /**
     * 4b. Re-confirming does not restart the clock. An unpaid order holds grid space for a window
     *     counted from when it started waiting; a customer editing their ad must not quietly buy
     *     another window by touching it again.
     */
    $wpdb->update(
        DB::table('orders'),
        ['status' => 'pending_payment', 'updated_at' => '2026-01-01 00:00:00'],
        ['id' => absint($order_id)]
    );
    $order = $orders->find($order_id);
    $again = $confirmation->advance($orders, $placement_repo, $order, $with_settings(['confirm-orders' => 'yes', 'auto-approve' => 'no', 'checkout-url' => '']));
    $check(
        is_array($again) && 'pending_payment' === sanitize_key((string) ($again['status'] ?? '')),
        'confirming an order twice moved it out of the payment queue: ' . (is_array($again) ? (string) $again['status'] : 'not updated')
    );
    $touched_at = (string) $wpdb->get_var($wpdb->prepare('SELECT updated_at FROM ' . DB::table('orders') . ' WHERE id = %d', absint($order_id)));
    $check(
        '2026-01-01 00:00:00' === $touched_at,
        'confirming an order that was already awaiting payment restarted the window it holds its space for (updated_at is now ' . $touched_at . ')'
    );

    $orders->update(absint($order_id), ['status' => 'reserved']);
    $placement_repo->update_status_by_order(absint($order_id), 'pending');
    $order = $orders->find($order_id);

    /**
     * 5. The screens. The review asks for one action; other screens do not.
     */
    $render = static function ($type) {
        return trim(preg_replace('/\s+/', ' ', strip_tags((string) (new PageShortcodes())->render(['type' => $type]))));
    };
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = [
        'mds3_order_id' => (string) absint($order_id),
        'mds3_order_key' => (string) ($order['order_key'] ?? ''),
    ];

    if (absint($confirm_page_id) > 0 && !is_wp_error($confirm_page_id)) {
        update_option($confirm_option, absint($confirm_page_id), false);

        // The words on the review screen follow what the site will really do with the order, not
        // its price: a site that asks for payment says so, and a site that publishes on confirm
        // does not promise a payment step that is not coming.
        $payment_settings = ['confirm-orders' => 'yes', 'auto-approve' => 'no', 'checkout-url' => ''];
        $with_settings($payment_settings);
        $review = $render('confirm-order');
        $check(
            false !== stripos($review, 'Confirm and continue to payment'),
            'the review screen did not offer a Confirm action: ' . substr($review, 0, 240)
        );

        $with_settings(['confirm-orders' => 'yes', 'auto-approve' => 'yes', 'checkout-url' => '']);
        $publishes_directly = $render('confirm-order');
        $check(
            false === stripos($publishes_directly, 'Confirm and continue to payment'),
            'the review screen promised a payment step to a customer on a site that publishes their ad on confirm: ' . substr($publishes_directly, 0, 240)
        );
        $check(
            false !== stripos($publishes_directly, 'Confirm order'),
            'a site that publishes on confirm lost its Confirm action: ' . substr($publishes_directly, 0, 240)
        );
    }

    $with_settings(['confirm-orders' => 'no', 'auto-approve' => 'no', 'checkout-url' => '']);
    $orders->update(absint($order_id), ['status' => 'reserved']);
    $without_step = $render('confirm-order');
    $check(
        false === stripos($without_step, 'Confirm and continue to payment'),
        'a site with the step off still asked the customer to confirm: ' . substr($without_step, 0, 240)
    );

    /**
     * A Confirm click that cannot be verified changes nothing. (The click that can be verified
     * redirects, which is followed in a browser rather than here.)
     */
    $with_settings(['confirm-orders' => 'yes']);
    $orders->update(absint($order_id), ['status' => 'reserved']);
    $order = $orders->find($order_id);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'mds3_action' => 'confirm_order',
        'order_id' => (string) absint($order_id),
        'order_key' => 'not-the-orders-key',
        '_wpnonce' => wp_create_nonce('mds3_confirm_order_' . absint($order_id)),
    ];
    $error = '';
    $notice = '';
    $shortcodes = new PageShortcodes();
    $refused = $call($shortcodes, PageShortcodes::class, 'maybe_confirm_order', [&$order, &$error, &$notice]);
    $check(
        is_array($refused) && 'reserved' === sanitize_key((string) ($refused['status'] ?? '')),
        'an unverified confirm click moved the order forward'
    );
    $check('' !== $error, 'an unverified confirm click passed without saying anything');
} finally {
    if (!empty($confirm_page_id) && !is_wp_error($confirm_page_id)) {
        wp_delete_post(absint($confirm_page_id), true);
    }
    $cleanup();
}

if ($fails) {
    fwrite(STDERR, implode("\n", $fails) . "\n");
    exit(1);
}

echo "MDS3 order confirmation fixture passed.\n";
