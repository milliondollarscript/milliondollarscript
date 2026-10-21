<?php
/**
 * A rejected ad submission must name the control it failed on, and a rejected
 * attempt must not spend the visitor's public write cooldown - a submission that
 * changed nothing has to stay correctable, or the retry comes back as a duplicate
 * of the attempt that did nothing.
 *
 * From the workspace root:
 *   scripts/wp-fixture-suite.sh rejected-submission
 */

use MillionDollarScript\V3\Extensions\ExtensionSupport;
use MillionDollarScript\V3\Grid\GridAjax;
use MillionDollarScript\V3\Media\UploadValidator;

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
    // A refused file belongs at the file control: without this the only place the
    // customer sees it is the status line at the bottom of the form.
    ['mds3_upload_width_too_large', 'image'],
    ['mds3_upload_height_too_large', 'image'],
    ['mds3_upload_type_invalid', 'image'],
    ['mds3_upload_image_invalid', 'image'],
    ['mds3_draft_upload_missing', 'image'],
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

// A refused upload must also name the control at the source, and say what size the
// ad is rather than only the limit that was crossed (task 449b2302).
$upload_settings = array('max-upload-width' => 640, 'max-upload-height' => 100);
if (!function_exists('imagepng')) {
    $failures[] = 'this check needs GD to build a test image';
} else {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $png = tempnam(sys_get_temp_dir(), 'mds3-size-') . '.png';
    $image = imagecreatetruecolor(1024, 1024);
    imagepng($image, $png);
    imagedestroy($image);
    add_filter('million-dollar-script/is/uploaded/file', '__return_true');
    $refused = (new UploadValidator())->validate(
        array('name' => 'chosen.png', 'tmp_name' => $png, 'size' => (int) filesize($png), 'error' => UPLOAD_ERR_OK),
        'chosen.png',
        $upload_settings
    );
    remove_filter('million-dollar-script/is/uploaded/file', '__return_true');
    unlink($png);

    if (!is_wp_error($refused)) {
        $failures[] = 'a 1024x1024 file was accepted by a site whose ads are 640x100';
    } else {
        $data = is_array($refused->get_error_data()) ? $refused->get_error_data() : array();
        if (($data['field'] ?? '') !== 'image') {
            $failures[] = 'a refused upload did not name the image field at the source';
        }
        $message = (string) $refused->get_error_message();
        if (false === strpos($message, '640') || false === strpos($message, '100')) {
            $failures[] = 'the refusal did not state the size the ad accepts: ' . $message;
        }
        if (false === strpos($message, '1024')) {
            $failures[] = 'the refusal did not state the size that was chosen: ' . $message;
        }
    }
}

// The hint beside the field and the rejection must describe the same size, and a site
// with no limit must show no hint at all.
$hint = UploadValidator::size_hint_sentence($upload_settings);
if (false === strpos($hint, '640') || false === strpos($hint, '100')) {
    $failures[] = 'the field hint did not state the accepted size: ' . $hint;
}
if ('' !== UploadValidator::size_hint_sentence(array('max-upload-width' => 0, 'max-upload-height' => 0))) {
    $failures[] = 'a site with no size limit showed a size hint anyway';
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
