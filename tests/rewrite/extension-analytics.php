<?php

define('ABSPATH', __DIR__ . '/');
define('MILLION_DOLLAR_SCRIPT_BASENAME', 'million-dollar-script/million-dollar-script.php');

function plugin_basename($file) {
    return ltrim((string) $file, '/');
}

function sanitize_key($value) {
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
}

function sanitize_text_field($value) {
    return trim(strip_tags((string) $value));
}

function wp_parse_url($url, $component = -1) {
    return parse_url((string) $url, $component);
}

function is_multisite() {
    return false;
}

function is_plugin_active_for_network($file) {
    return false;
}

require_once dirname(__DIR__, 2) . '/src/Extensions/ExtensionAnalytics.php';

use MillionDollarScript\V3\Extensions\ExtensionAnalytics;

$plugins = [
    'million-dollar-script/million-dollar-script.php' => ['PluginURI' => 'https://milliondollarscript.com', 'Version' => '3.0.0'],
    'mds-fields/mds-fields.php' => ['PluginURI' => 'https://milliondollarscript.com/extensions/mds-fields', 'Version' => '1.0.22'],
    'mds-lookalike/mds-lookalike.php' => ['PluginURI' => 'https://example.com', 'Version' => '9.9.9'],
    'ordinary/ordinary.php' => ['AuthorURI' => 'https://milliondollarscript.com', 'Version' => '1.0.0'],
];

$snapshot = ExtensionAnalytics::snapshot_from_plugins($plugins, ['mds-fields/mds-fields.php']);
if ([['slug' => 'mds-fields', 'version' => '1.0.22', 'active' => true]] !== $snapshot) {
    fwrite(STDERR, 'Unexpected MDS 3.0 extension analytics snapshot: ' . var_export($snapshot, true) . PHP_EOL);
    exit(1);
}

echo "MDS 3.0 extension analytics checks passed.\n";
