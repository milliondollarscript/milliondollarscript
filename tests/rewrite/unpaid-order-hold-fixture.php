<?php
/**
 * WordPress fixture for the space an unpaid order is holding.
 *
 * An order waiting for its ad, and an order waiting to be paid for, are released on different
 * windows. The date the admin queue shows and the date the scheduled cleanup works to have to
 * come from the same answer, or the screen promises a release that never happens.
 *
 * Run with:
 * ./scripts/wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/unpaid-order-hold-fixture.php
 */

if (!defined('ABSPATH')) {
    exit;
}

use MillionDollarScript\V3\Orders\OrderCleanup;
use MillionDollarScript\V3\Settings\SettingsSchema;

$failures = [];
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

$stored = get_option('mds3_settings', []);
$settings = is_array($stored) ? $stored : [];

$with = static function ($changes) use ($settings) {
    update_option('mds3_settings', array_merge($settings, $changes));
};

// Each unpaid status answers to its own window, in minutes.
$with(array_merge(SettingsSchema::defaults(), [
    'expire-orders' => 'yes',
    'minutes-unconfirmed' => 60,
    'minutes-confirmed' => 43200,
]));
$assert(
    60 === OrderCleanup::unpaid_hold_minutes('reserved'),
    'An order waiting for its ad should be held by the unconfirmed window.'
);
$assert(
    43200 === OrderCleanup::unpaid_hold_minutes('pending_payment'),
    'An order waiting to be paid for should be held by the confirmed window.'
);

// The date shown to the owner is counted from the order, not from now, so it does not move
// every time the page is reloaded.
$assert(
    '2026-10-20 07:00:00' === OrderCleanup::unpaid_hold_deadline('reserved', '2026-10-20 06:00:00'),
    'The release date should be the order time plus the window for its status.'
);
$assert(
    '2026-11-19 06:00:00' === OrderCleanup::unpaid_hold_deadline('pending_payment', '2026-10-20 06:00:00'),
    'An order with an ad should be held for the payment window, not the checkout window.'
);
$assert(
    OrderCleanup::unpaid_hold_deadline('reserved', '2026-10-20 06:00:00')
        === OrderCleanup::unpaid_hold_deadline('reserved', '2026-10-20 06:00:00'),
    'Asking the same order twice should give the same date.'
);

// A site that never releases space of its own should say so instead of showing a date.
$with(['expire-orders' => 'no', 'minutes-unconfirmed' => 60, 'minutes-confirmed' => 43200]);
$assert(
    0 === OrderCleanup::unpaid_hold_minutes('reserved'),
    'Turning expiry off should hold the space for no fixed window.'
);
$assert(
    '' === OrderCleanup::unpaid_hold_deadline('pending_payment', '2026-10-20 06:00:00'),
    'With expiry off there should be no release date to promise.'
);

// "Immediately" is a valid window, and the date it gives is one minute after the order - a date
// the sweep has already passed, which is why the queue should say "Returning now".
$with(['expire-orders' => 'yes', 'minutes-confirmed' => -1]);
$assert(
    -1 === OrderCleanup::unpaid_hold_minutes('pending_payment'),
    'A window of -1 should be read as immediate, not as a negative wait.'
);
$assert(
    '2026-10-20 05:59:00' === OrderCleanup::unpaid_hold_deadline('pending_payment', '2026-10-20 06:00:00'),
    'An immediate window should give a release date one minute after the order.'
);

// A paid order is not held by an unpaid window - its space comes back when its term ends, which
// is a different answer, so it must not be given one here.
$with(array_merge(SettingsSchema::defaults(), ['minutes-confirmed' => 43200, 'minutes-unconfirmed' => 60]));
$assert(
    0 === OrderCleanup::unpaid_hold_minutes('paid'),
    'A paid order should not be measured by the unpaid windows.'
);
$assert(
    '' === OrderCleanup::unpaid_hold_deadline('cancelled', '2026-10-20 06:00:00'),
    'A status the site does not hold space for should have no release date.'
);

update_option('mds3_settings', $settings);

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "Unpaid order hold fixture passed.\n";
