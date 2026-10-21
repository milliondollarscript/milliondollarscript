<?php
/**
 * WordPress fixture for settings that ask for a number.
 *
 * A typed value that cannot be the requested number is rejected and named, rather than
 * quietly stored as a different number.
 *
 * Run with:
 * ./scripts/wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/settings-number-validation-fixture.php
 */

if (!defined('ABSPATH')) {
    exit;
}

use MillionDollarScript\V3\Settings\SettingsSchema;

$failures = [];
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

$fields = [
    'renewal-reminder-days-1' => ['key' => 'renewal-reminder-days-1', 'label' => 'First Renewal Reminder Days', 'type' => 'number'],
    'minutes-renew' => ['key' => 'minutes-renew', 'label' => 'Minutes Until Reserved Pixels Expire', 'type' => 'number'],
    'grid-columns' => ['key' => 'grid-columns', 'label' => 'Grid Width', 'type' => 'number'],
    'site-title' => ['key' => 'site-title', 'label' => 'Site Title', 'type' => 'text'],
];

// A word where a number belongs, and a negative where only counts belong, are both refused
// by name so the owner knows which box to fix.
$rejected = SettingsSchema::validate_number_fields(
    ['renewal-reminder-days-1' => 'abc'],
    $fields
);
$assert($rejected->has_errors(), 'A word entered in a number field should be refused.');
$assert(
    false !== strpos((string) $rejected->get_error_message(), 'First Renewal Reminder Days'),
    'The refusal should name the setting the owner typed into.'
);

$rejected = SettingsSchema::validate_number_fields(['renewal-reminder-days-1' => '-5'], $fields);
$assert($rejected->has_errors(), 'A negative count of days should be refused.');

// -1 means "no expiry" on the minutes fields, so it is a value the owner is allowed to set;
// -5 means nothing and is refused instead of being clamped to -1 behind their back.
$accepted = SettingsSchema::validate_number_fields(['minutes-renew' => '-1'], $fields);
$assert(!$accepted->has_errors(), 'The documented -1 value on a signed minutes setting should be accepted.');
$rejected = SettingsSchema::validate_number_fields(['minutes-renew' => '-5'], $fields);
$assert($rejected->has_errors(), 'A signed minutes setting should refuse anything below -1.');

foreach (['7', '0', ' 12 ', ''] as $value) {
    $accepted = SettingsSchema::validate_number_fields(['renewal-reminder-days-1' => $value], $fields);
    $assert(!$accepted->has_errors(), 'The value "' . $value . '" is a valid number of days and should be accepted.');
}

// Untouched fields and other field types are left alone.
$accepted = SettingsSchema::validate_number_fields(['site-title' => 'Fine as text'], $fields);
$assert(!$accepted->has_errors(), 'Non-number fields should not be checked by the number validator.');

// The shipped settings must all be reachable by this validator, so a number field added later
// is covered without extra work.
$schema = SettingsSchema::fields();
$number_keys = array_keys(array_filter($schema, static function ($field) {
    return is_array($field) && ('number' === ($field['type'] ?? ''));
}));
$assert($number_keys, 'The settings schema should still declare number fields for this fixture to exercise.');
$sent = [];
foreach ($number_keys as $key) {
    $sent[$key] = 'not a number';
}
$all = SettingsSchema::validate_number_fields($sent, $schema);
$assert(
    count($all->get_error_codes()) === count($number_keys),
    'Every number setting should refuse a non-numeric submission.'
);

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "Settings number validation fixture passed.\n";
