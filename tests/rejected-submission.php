<?php
/**
 * Runnable check for the two ad-form fixes (tasks d8b44ab2, e0a46f0b):
 * a rejected submission names the field it failed on, and a rejected attempt does
 * not spend the visitor's public write cooldown.
 *
 * From the workspace root:
 *   ./scripts/wp eval-file /var/www/html/wp-content/plugins/million-dollar-script/tests/rejected-submission.php
 */

use MillionDollarScript\V3\Extensions\ExtensionSupport;
use MillionDollarScript\V3\Grid\GridAjax;

$failures = [];

$handler = new GridAjax();
$payload_for = static function (WP_Error $error) use ($handler) {
    $method = new ReflectionMethod($handler, 'field_error_payload');
    $method->setAccessible(true);
    return $method->invoke($handler, $error);
};

$cases = [
    ['million_dollar_script_popup_too_long', 'popup_text'],
    ['million_dollar_script_popup_required', 'popup_text'],
    ['million_dollar_script_url_invalid', 'link_url'],
];
foreach ($cases as [$code, $expected]) {
    $payload = $payload_for(new WP_Error($code, 'Message.', ['status' => 400]));
    if (($payload['field'] ?? '') !== $expected) {
        $failures[] = "{$code} did not name the {$expected} field";
    }
}

$from_extension = $payload_for(new WP_Error('mds_fields_too_long', 'Message.', ['status' => 400, 'field' => 'mds_fields_12']));
if (($from_extension['field'] ?? '') !== 'mds_fields_12') {
    $failures[] = 'an extension-supplied field name was ignored';
}

$unrelated = $payload_for(new WP_Error('mds3_order_grid_mismatch', 'Message.', ['status' => 403]));
if (array_key_exists('field', $unrelated)) {
    $failures[] = 'a non-field error claimed a field name';
}

$bucket = 'selfcheck_' . wp_generate_password(8, false, false);
if (ExtensionSupport::is_rate_limited($bucket, 'mds3_selfcheck')) {
    $failures[] = 'a fresh write bucket already looked throttled';
}
ExtensionSupport::is_rate_limited($bucket, 'mds3_selfcheck');
if (ExtensionSupport::is_rate_limited($bucket, 'mds3_selfcheck')) {
    $failures[] = 'peeking at the throttle consumed the visitor cooldown';
}
ExtensionSupport::record_rate_limit($bucket, 'mds3_selfcheck', 30);
if (!ExtensionSupport::is_rate_limited($bucket, 'mds3_selfcheck')) {
    $failures[] = 'charging the throttle after a successful write did not take effect';
}
delete_transient('mds3_selfcheck_' . md5(ExtensionSupport::remote_ip() . '|' . $bucket));

if ($failures) {
    fwrite(STDERR, "rejected-submission check FAILED:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "rejected-submission check passed.\n";
