<?php
/**
 * WP-CLI settings import/export fixture for Million Dollar Script.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/settings-transfer-fixture.php
 */

use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Settings\SettingsTransfer;

if (!defined('ABSPATH')) {
    exit;
}

$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$admin) {
    throw new RuntimeException('Settings transfer fixture requires an administrator user.');
}

$user_id = absint($admin[0]);
wp_set_current_user($user_id);

$transfer = new SettingsTransfer();
$fields = SettingsSchema::fields();
$fields['legacy_mds2_source_prefix'] = [
    'key' => 'legacy_mds2_source_prefix',
    'label' => 'Last Million Dollar Script 2 Source Prefix',
    'type' => 'text',
    'default' => '',
];

$original_settings = get_option('mds3_settings', []);
$original_settings = is_array($original_settings) ? $original_settings : [];
$original_backups = get_option('mds3_settings_import_backups', null);
$original_last_backup = get_option('mds3_settings_last_import_backup', null);

try {
    $current = wp_parse_args([
        'currency' => 'USD',
        'currency-symbol' => '$',
        'button-color' => '#2563eb',
        'theme_mode' => 'light',
        'updates' => 'main',
        'popup-template' => '<p>Current</p>',
        'resize' => 'yes',
        'redirect-url' => 'https://example.com',
        'legacy_mds2_source_prefix' => 'wp_mds_',
    ], SettingsSchema::defaults());
    update_option('mds3_settings', $current, false);

    $export = $transfer->export_payload($current, $fields);
    if (($export['package'] ?? '') !== 'million-dollar-script' || empty($export['settings']['currency'])) {
        throw new RuntimeException('Settings export payload is incomplete.');
    }
    if (!is_string(wp_json_encode($export))) {
        throw new RuntimeException('Settings export payload is not JSON encodable.');
    }
    if (($export['settings']['resize'] ?? '') !== 'yes' || ($export['settings']['redirect-url'] ?? '') !== 'https://example.com') {
        throw new RuntimeException('Settings export did not preserve hidden compatibility/deferred fields.');
    }

    $preview = $transfer->preview([
        'package' => 'million-dollar-script',
        'type' => 'settings',
        'schema_version' => 1,
        'settings' => [
            'currency' => 'CAD',
            'currency-symbol' => 'C$',
            'button-color' => '#111111',
            'popup-template' => '<script>alert(1)</script><p>Imported</p>',
            'updates' => 'alpha',
            'resize' => 'no',
            'redirect-url' => 'https://example.test/order',
            'theme_mode' => 'invalid-theme',
            'max-upload-width' => 'not-a-number',
            'not-a-real-setting' => 'ignored',
        ],
    ], $current, $fields);

    if (is_wp_error($preview)) {
        throw new RuntimeException('Settings import preview failed: ' . $preview->get_error_message());
    }
    if (empty($preview['settings']['currency']) || 'CAD' !== $preview['settings']['currency']) {
        throw new RuntimeException('Settings import did not sanitize importable currency.');
    }
    if (false !== strpos((string) ($preview['settings']['popup-template'] ?? ''), '<script')) {
        throw new RuntimeException('Settings import did not strip unsafe editor HTML.');
    }
    if (($preview['settings']['resize'] ?? '') !== 'no' || ($preview['settings']['redirect-url'] ?? '') !== 'https://example.test/order') {
        throw new RuntimeException('Settings import did not preserve hidden compatibility/deferred fields.');
    }
    if (empty($preview['unknown']) || !in_array('not-a-real-setting', $preview['unknown'], true)) {
        throw new RuntimeException('Settings import did not report unknown settings.');
    }
    $rejected_keys = array_map(static function ($item) {
        return (string) ($item['key'] ?? '');
    }, is_array($preview['rejected'] ?? null) ? $preview['rejected'] : []);
    foreach (['theme_mode', 'max-upload-width'] as $expected_rejection) {
        if (!in_array($expected_rejection, $rejected_keys, true)) {
            throw new RuntimeException('Settings import did not reject unsafe value for ' . $expected_rejection . '.');
        }
    }
    if (empty($preview['changes']) || count($preview['changes']) < 4) {
        throw new RuntimeException('Settings import preview did not report expected changes.');
    }

    $transfer->save_preview($preview, $user_id);
    $stored_preview = $transfer->preview_for_user($user_id);
    if (!$stored_preview || ($stored_preview['settings']['currency'] ?? '') !== 'CAD') {
        throw new RuntimeException('Settings import preview was not saved for the current user.');
    }

    $transfer->backup_current($current, $user_id);
    $next = array_merge($current, $stored_preview['settings']);
    update_option('mds3_settings', $next, false);
    $saved = get_option('mds3_settings', []);
    if (!is_array($saved) || 'CAD' !== ($saved['currency'] ?? '') || '#111111' !== ($saved['button-color'] ?? '')) {
        throw new RuntimeException('Settings import application did not save sanitized settings.');
    }
    $backups = get_option('mds3_settings_import_backups', []);
    if (!is_array($backups) || empty($backups[0]['settings']) || 'USD' !== ($backups[0]['settings']['currency'] ?? '')) {
        throw new RuntimeException('Settings import did not create a usable backup.');
    }
} finally {
    update_option('mds3_settings', $original_settings, false);
    if (null === $original_backups) {
        delete_option('mds3_settings_import_backups');
    } else {
        update_option('mds3_settings_import_backups', $original_backups, false);
    }
    if (null === $original_last_backup) {
        delete_option('mds3_settings_last_import_backup');
    } else {
        update_option('mds3_settings_last_import_backup', $original_last_backup, false);
    }
    $transfer->clear_preview($user_id);
}

WP_CLI::success('Settings import/export fixture passed.');
