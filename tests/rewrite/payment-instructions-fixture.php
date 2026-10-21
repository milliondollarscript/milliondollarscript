<?php
/**
 * WP-CLI fixture: a site that takes payment outside the plugin tells its customer how to pay.
 *
 * Why this exists: on the manual-payment path a fresh install defaults to, an order waiting for
 * payment showed the customer one sentence - "the site confirms the payment" - and no way to act.
 * The site owner knows how they want to be paid (bank transfer, cheque, in person) and what the
 * customer must put on the payment so it can be matched to an order, but had nowhere to say it
 * and the customer had nowhere to read it (llm-task 798e3f93, the flow Ryan set: blocks, ad form,
 * then pay - with the manual path ending on instructions the site owner writes).
 *
 * The rules this pins:
 *   - when there is no payment page to send them to, the site owner's own wording appears;
 *   - when there is a payment page, the button is the answer and the instructions are not shown;
 *   - when nothing has been written, nothing is invented;
 *   - the text is stored and returned as written, because a bank account and a payment reference
 *     are not a sentence.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/payment-instructions-fixture.php
 */

use MillionDollarScript\V3\Pages\PageShortcodes;
use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

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

$shortcodes = new PageShortcodes();
$PAY_URL = 'https://example.test/pay?amount=25';
$INSTRUCTIONS = "Pay by bank transfer to:\nAccount 12-3456-7890, Sort 123456\nPlease quote order 4321.";

$state = static function (array $order, array $settings, $payment_url = '') use ($shortcodes, $call) {
    return (array) $call($shortcodes, PageShortcodes::class, 'customer_order_state', [$order, [['status' => 'pending']], $settings, $payment_url]);
};

$awaiting = ['id' => 4321, 'status' => 'pending_payment'];
$settings_with = ['auto-publish' => true, 'payment-instructions' => $INSTRUCTIONS];
$settings_without = ['auto-publish' => true];
$observed = [];

/*
 * 1. No payment page: the customer is told how this site is paid.
 */
$manual = $state($awaiting, $settings_with);
$observed['manual_payment'] = $manual;
$check(
    '' !== trim((string) ($manual['payment_instructions'] ?? '')),
    'a site taking payment itself left the customer with no way to act on an unpaid order'
);
$check(
    false !== strpos((string) ($manual['payment_instructions'] ?? ''), 'Account 12-3456-7890'),
    'the site owner’s own payment wording did not reach the customer'
);
$check(
    false !== strpos((string) ($manual['payment_instructions'] ?? ''), "\n"),
    'the payment details arrived as one run-on line, so they cannot be copied from'
);
$check(
    '' !== trim((string) ($manual['headline'] ?? '')),
    'the order stopped saying what its status is once instructions were added'
);

/*
 * 2. There is a payment page: the button is the answer, and the instructions stay out of the way.
 */
$with_payment_page = $state($awaiting, $settings_with, $PAY_URL);
$observed['with_payment_page'] = ['payment_url' => $with_payment_page['payment_url'] ?? '', 'payment_instructions' => $with_payment_page['payment_instructions'] ?? ''];
$check(
    $PAY_URL === (string) ($with_payment_page['payment_url'] ?? ''),
    'a site with a payment page stopped offering it'
);
$check(
    '' === (string) ($with_payment_page['payment_instructions'] ?? ''),
    'the customer was shown transfer instructions when a page could take the payment'
);

/*
 * 3. Nothing written, nothing invented.
 */
$nothing = $state($awaiting, $settings_without);
$observed['no_instructions_written'] = ['payment_instructions' => $nothing['payment_instructions'] ?? '', 'detail' => $nothing['detail'] ?? ''];
$check(
    '' === trim((string) ($nothing['payment_instructions'] ?? '')),
    'an empty setting produced text the site owner never wrote'
);
$check(
    '' !== trim((string) ($nothing['detail'] ?? '')),
    'a site with no instructions left the customer with no sentence at all'
);

/*
 * 4. A paid order is not asked how to pay, whatever the setting says.
 */
$paid = $state(['id' => 4321, 'status' => 'paid', 'metadata' => wp_json_encode(['expires_at' => '2026-12-31 00:00:00'])], $settings_with);
$observed['paid_order'] = ['payment_instructions' => $paid['payment_instructions'] ?? ''];
$check(
    '' === (string) ($paid['payment_instructions'] ?? ''),
    'an order that has been paid was shown payment instructions again'
);

/*
 * 5. The setting is stored as written. It is saved through the same path the settings form uses,
 *    so a line break lost here is lost on every site.
 */
$saved = SettingsSchema::sanitize('payment-instructions', $INSTRUCTIONS . '<script>alert(1)</script>');
$observed['stored'] = $saved;
$check(
    false !== strpos((string) $saved, 'Sort 123456'),
    'the settings path dropped part of what the site owner wrote'
);
$check(
    false === strpos((string) $saved, '<script>'),
    'the payment instructions kept markup that runs on the customer’s page'
);
$check(
    $INSTRUCTIONS === SettingsSchema::sanitize('payment-instructions', $INSTRUCTIONS),
    'the payment instructions were not stored as written'
);

echo wp_json_encode($observed), "\n";
if ($fails) {
    fwrite(STDERR, implode("\n", $fails) . "\n");
    exit(1);
}
echo "Payment instructions fixture passed.\n";
