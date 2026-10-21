<?php
/**
 * WP-CLI fixture: when an order may be taken forward without anything confirming the money.
 *
 * Why this exists: the site already had a switch called "Auto-complete Manual Payments", and it
 * worked for exactly one kind of order - a standalone site with no payment page of its own. The
 * two cases a site owner actually hits were left waiting forever. A shop order paid by bank
 * transfer sits at on-hold because no gateway is going to report it, so the ad never appeared.
 * An order with nothing to pay - a free placement, an extension's own zero-price order - waited
 * for a payment nobody would ever make. The older plugin finished both of those
 * (milliondollarscript-two/src/Classes/Orders/Orders.php:1172 treats a zero price and one named
 * person's permission as sufficient).
 *
 * What this pins down:
 *   1. a normal paid order is still not taken forward on the say-so of a switch that is off;
 *   2. the site's switch, an order with nothing to pay, and one person's permission each do it,
 *      and the order records which of the three applied;
 *   3. a grid that asks for review still holds the order back whatever the site switch says,
 *      because that request is about the ad, not the money;
 *   4. records that are not Million Dollar Script grid orders are not touched at all;
 *   5. the permission belongs to a person and is granted by someone who can change what other
 *      people may do - it cannot be given to oneself;
 *   6. the permission is remembered under the new name and the old word is not needed to read it.
 *
 * The WooCommerce half - an order the shop leaves at on-hold - is exercised through the same
 * decision, with the shop's own order deliberately left where it is until someone records the
 * money. That part needs a live WooCommerce order, so the extension's own fixture covers it.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/manual-payment-acceptance-fixture.php
 */

use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Orders\PaymentBypass;
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

$settings_option = 'mds3_settings';
$stored_settings = get_option($settings_option, []);
$stored_settings = is_array($stored_settings) ? $stored_settings : [];
$original_post = $_POST;

$with_settings = static function (array $changes) use ($stored_settings) {
    $merged = array_merge($stored_settings, $changes);
    update_option('mds3_settings', $merged, false);

    return $merged;
};

/**
 * An order on the grid this site already has, at a price of the caller's choosing, with an
 * optional customer. The order carries an item for that grid because that is what a grid's own
 * review setting applies to - an order holding nothing from that grid cannot be held back by it.
 * Nothing is paid at this point, which is the whole point of the fixture.
 */
$make_order = static function ($total, $user_id = 0, $status = 'pending_payment') use (&$grid_id) {
    $amount = (float) $total;
    $items = [[
        'grid_id' => $grid_id,
        'item_type' => $amount > 0 ? 'block' : 'package',
        'quantity' => 1,
        'unit_price' => $amount,
        'total' => $amount,
    ]];
    $order_id = (new OrderRepository())->create($items, [
        'email' => 'manual-payment@example.test',
        'user_id' => $user_id ?: null,
        'status' => $status,
        'total' => $amount,
        'currency' => 'USD',
        'commerce_provider' => 'woocommerce',
        'commerce_order_id' => '999001',
    ]);
    if (is_wp_error($order_id) || !absint($order_id)) {
        throw new RuntimeException('Could not create the fixture order: ' . (is_wp_error($order_id) ? $order_id->get_error_message() : 'no id'));
    }

    return absint($order_id);
};

$orders = new OrderRepository();
$grids = (new GridRepository())->all();
$grid_id = $grids ? absint($grids[0]->id()) : 0;
if (!$grid_id) {
    throw new RuntimeException('This fixture needs a grid, because a grid can hold an order back for review.');
}

$stored_grid_settings = (string) $wpdb->get_var($wpdb->prepare(
    'SELECT settings FROM ' . DB::table('grids') . ' WHERE id = %d',
    $grid_id
));

$set_grid_choice = static function ($choice) use ($wpdb, $grid_id, $stored_grid_settings) {
    $settings = json_decode((string) $stored_grid_settings, true);
    $settings = is_array($settings) ? $settings : [];
    if (null === $choice) {
        unset($settings['auto_approve']);
    } else {
        $settings['auto_approve'] = $choice;
    }
    $wpdb->update(DB::table('grids'), ['settings' => wp_json_encode($settings)], ['id' => $grid_id]);
};

/**
 * The people this fixture needs. A run that failed part-way left them behind once already, and a
 * fixture that cannot be run twice in a row is a fixture nobody can trust, so they are removed
 * before they are created.
 */
$fixture_users = [];
foreach ([
    'shop_customer' => ['mds3_manual_shop_customer', 'shop-customer@example.test', 'customer'],
    'invoiced_customer' => ['mds3_manual_invoiced_customer', 'invoiced-customer@example.test', 'customer'],
    'staff_placer' => ['mds3_manual_staff_placer', 'staff-placer@example.test', 'editor'],
    'site_owner' => ['mds3_manual_site_owner', 'site-owner@example.test', 'administrator'],
] as $who => $person) {
    $leftover_id = username_exists($person[0]);
    if ($leftover_id) {
        $wpdb->delete($wpdb->usermeta, ['user_id' => absint($leftover_id)]);
        $wpdb->delete($wpdb->users, ['ID' => absint($leftover_id)]);
        wp_cache_delete($leftover_id, 'users');
    }
    $created = wp_insert_user(['user_login' => $person[0], 'user_email' => $person[1], 'role' => $person[2]]);
    if (is_wp_error($created)) {
        throw new RuntimeException('Could not create the fixture user ' . $person[0] . ': ' . $created->get_error_message());
    }
    $fixture_users[$who] = absint($created);
}
$shop_user = $fixture_users['shop_customer'];
$invoice_user = $fixture_users['invoiced_customer'];
$editor_user = $fixture_users['staff_placer'];
$admin_user = $fixture_users['site_owner'];

$made_order_ids = [];
$track = static function ($order_id) use (&$made_order_ids) {
    $made_order_ids[] = absint($order_id);
};

$bypass = new PaymentBypass();

$cleanup = static function () use ($wpdb, $made_order_ids, $shop_user, $invoice_user, $editor_user, $admin_user, $stored_settings, $stored_grid_settings, $grid_id, $original_post) {
    $_POST = $original_post;
    foreach ($made_order_ids as $order_id) {
        $wpdb->delete(DB::table('order_items'), ['order_id' => $order_id]);
        $wpdb->delete(DB::table('orders'), ['id' => $order_id]);
    }
    foreach ([$shop_user, $invoice_user, $editor_user, $admin_user] as $user_id) {
        if (!is_wp_error($user_id) && absint($user_id)) {
            $wpdb->delete($wpdb->usermeta, ['user_id' => absint($user_id)]);
            $wpdb->delete($wpdb->users, ['ID' => absint($user_id)]);
        }
    }
    $wpdb->update(DB::table('grids'), ['settings' => $stored_grid_settings], ['id' => $grid_id]);
    update_option('mds3_settings', $stored_settings, false);
};

try {
    // The grid this site already has may carry its own review choice from an earlier run, and a
    // fixture that inherits a setting it never names is testing the last test that ran. Start
    // from the state the case describes: the grid says nothing, so the site switch decides.
    $set_grid_choice(null);

    // 1. The switch off means what it says: a normal order waits for a person.
    $held = $make_order('12.00', absint($shop_user));
    $track($held);
    $with_settings(['auto-approve' => 'no']);
    $check(false === Payments::accept_manual_payment('mds-grid', $held, []), 'a site that reviews manual payments took a paid order anyway');
    $check('pending_payment' === $orders->find($held)['status'], 'the order moved even though nothing approved it');

    // 2a. The site's switch takes it, and the order records which rule applied.
    $with_settings(['auto-approve' => 'yes']);
    $check(true === Payments::accept_manual_payment('mds-grid', $held, ['provider_order_id' => '999001']), 'the site switch did not take the order forward');
    $taken = $orders->find($held);
    $check('paid' === $taken['status'], 'the site switch left the order waiting');
    $reason = json_decode((string) $taken['metadata'], true)['paid_without_gateway_confirmation'] ?? '';
    $check('site_accepts_manual' === $reason, 'the order does not say the site accepted the payment: got ' . var_export($reason, true));

    // 2b. An order with nothing to pay is never left waiting, whatever the switch says.
    $with_settings(['auto-approve' => 'no']);
    $free = $make_order('0.00', absint($shop_user));
    $track($free);
    $check(true === Payments::accept_manual_payment('mds-grid', $free, []), 'a zero-price order was left waiting for a payment nobody owes');
    $free_meta = json_decode((string) $orders->find($free)['metadata'], true);
    $check('nothing_to_pay' === ($free_meta['paid_without_gateway_confirmation'] ?? ''), 'the free order does not say it was taken because nothing is owed');

    // 2c. One named person's permission is enough on its own.
    $invoiced = $make_order('250.00', absint($invoice_user));
    $track($invoiced);
    $check(false === Payments::accept_manual_payment('mds-grid', $invoiced, []), 'an ordinary customer was taken forward without paying');
    $bypass->allow(absint($invoice_user), true);
    $check(true === $bypass->allowed(absint($invoice_user)), 'the permission was not remembered');
    $check(true === Payments::accept_manual_payment('mds-grid', $invoiced, []), 'the permission did not let an invoiced order through');
    $invoiced_meta = json_decode((string) $orders->find($invoiced)['metadata'], true);
    $check('person_allowed' === ($invoiced_meta['paid_without_gateway_confirmation'] ?? ''), 'the order does not say it was taken on the customer’s permission');

    // 2d. The permission belongs to the person, not to the site: another customer is unaffected.
    $other = $make_order('250.00', absint($shop_user));
    $track($other);
    $check(false === Payments::accept_manual_payment('mds-grid', $other, []), 'granting one person the permission let every customer through');

    // 3. A grid that asks for review holds the order back even when the site accepts manual payments.
    $with_settings(['auto-approve' => 'yes']);
    $set_grid_choice('N');
    $reviewed = $make_order('12.00', absint($shop_user));
    $track($reviewed);
    $check(false === Payments::accept_manual_payment('mds-grid', $reviewed, []), 'a grid asking for review was overridden by the site switch');
    $check('pending_payment' === $orders->find($reviewed)['status'], 'the review request did not hold the order back');

    // A free order is not a review question: the grid cannot ask for a payment that is not owed.
    $free_reviewed = $make_order('0.00', absint($shop_user));
    $track($free_reviewed);
    $check(true === Payments::accept_manual_payment('mds-grid', $free_reviewed, []), 'a grid holding orders for review blocked an order with nothing to pay');
    $set_grid_choice(null);

    // 4. Someone else's records are not this decision to make.
    $check(false === Payments::accept_manual_payment('mds-sponsorboard', 1, []), 'the core took a decision about a record it does not own');
    $check(false === Payments::accept_manual_payment('mds-grid', 0, []), 'a missing order was treated as an answer');

    // 5. Granting the permission belongs to someone who can change what other people may do.
    //    An editor is deliberately not that person, so the same form changes nothing for them.
    //    The security check belongs to the person submitting the form, so each actor builds their
    //    own - one form reused across two people would be testing nonce arithmetic instead.
    $bypass->allow(absint($invoice_user), false);
    $check(false === $bypass->allowed(absint($invoice_user)), 'this part did not start with the permission removed');

    $form = static function ($granted) {
        $post = ['mds3_payment_bypass_nonce' => wp_create_nonce('mds3_payment_bypass')];
        if ($granted) {
            $post['mds3_order_without_payment'] = 'yes';
        }

        return $post;
    };

    wp_set_current_user(absint($editor_user));
    $_POST = $form(true);
    $bypass->save_profile_field(absint($invoice_user));
    $check(false === $bypass->allowed(absint($invoice_user)), 'someone who cannot change what others may do granted the permission');

    wp_set_current_user(absint($admin_user));
    $_POST = $form(true);
    $bypass->save_profile_field(absint($invoice_user));
    $check(true === $bypass->allowed(absint($invoice_user)), 'the site owner could not grant the permission from the profile form');

    $_POST = $form(false);
    $bypass->save_profile_field(absint($invoice_user));
    $check(false === $bypass->allowed(absint($invoice_user)), 'removing the permission in the profile form did not remove it');

    $_POST = array_merge($form(true), ['mds3_payment_bypass_nonce' => 'not a nonce']);
    $bypass->save_profile_field(absint($invoice_user));
    $check(false === $bypass->allowed(absint($invoice_user)), 'a form submission without a valid security check granted the permission');
    $_POST = [];

    // A customer cannot grant it to themselves, which is what makes it a permission and not a preference.
    wp_set_current_user(absint($shop_user));
    $_POST = $form(true);
    $bypass->save_profile_field(absint($shop_user));
    $check(false === $bypass->allowed(absint($shop_user)), 'a customer granted the permission to themselves');
    $_POST = [];

    // 6. What counts as having the permission is narrow and known; a stored "no" is not a yes.
    update_user_meta(absint($shop_user), PaymentBypass::META_KEY, 'no');
    $check(false === $bypass->allowed(absint($shop_user)), 'a stored “no” read as permission to order without paying');
    update_user_meta(absint($shop_user), PaymentBypass::META_KEY, 'true');
    $check(true === $bypass->allowed(absint($shop_user)), 'the answer words a migrated site may already hold were not honoured');
    delete_user_meta(absint($shop_user), PaymentBypass::META_KEY);
    $check(false === $bypass->allowed(0), 'a person who does not exist was treated as allowed');
    $bypass->allow(absint($editor_user), true);
    $check(0 < $bypass->count_allowed(), 'the count of people with the permission did not see the one just granted');

    // The setting still reads as the shipped default when a site never chose, and a stored choice
    // survives the settings schema - the switch is the same one the settings screen shows.
    $check('no' === SettingsSchema::sanitize('auto-approve', ''), 'an unset manual-payments choice read as on');
    $check('yes' === SettingsSchema::sanitize('auto-approve', 'yes'), 'a stored manual-payments choice did not survive the schema');

    $cleanup();
} catch (Throwable $e) {
    $cleanup();
    echo "manual-payment acceptance fixture errored: " . $e->getMessage() . "\n";
    exit(1);
}

if ($fails) {
    echo "manual-payment acceptance fixture FAILED\n";
    foreach ($fails as $fail) {
        echo ' - ' . $fail . "\n";
    }
    exit(1);
}

echo "manual-payment acceptance fixture passed.\n";
