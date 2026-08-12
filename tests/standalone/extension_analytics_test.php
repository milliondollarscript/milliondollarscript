<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

function plugin_basename(string $file): string {
	return ltrim($file, '/');
}

function sanitize_key(string $value): string {
	return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($value));
}

function sanitize_text_field(string $value): string {
	return trim(strip_tags($value));
}

function wp_parse_url(string $url, int $component = -1) {
	return parse_url($url, $component);
}

function is_multisite(): bool {
	return false;
}

function is_plugin_active_for_network(string $file): bool {
	return false;
}

require_once dirname(__DIR__, 2) . '/src/Classes/Extension/ExtensionAnalytics.php';

$snapshot = \MillionDollarScript\Classes\Extension\ExtensionAnalytics::snapshot_from_plugins(
	[
		'mds-fields/mds-fields.php'       => [ 'PluginURI' => 'https://milliondollarscript.com/extensions/mds-fields', 'Version' => '1.0.22' ],
		'mds-lookalike/mds-lookalike.php' => [ 'PluginURI' => 'https://example.com', 'Version' => '9.9.9' ],
		'ordinary/ordinary.php'           => [ 'AuthorURI' => 'https://milliondollarscript.com', 'Version' => '1.0.0' ],
	],
	[ 'mds-fields/mds-fields.php' ]
);

$expected = [ [ 'slug' => 'mds-fields', 'version' => '1.0.22', 'active' => true ] ];
if ( $expected !== $snapshot ) {
	fwrite( STDERR, 'Unexpected MDS2 extension analytics snapshot: ' . var_export( $snapshot, true ) . PHP_EOL );
	exit( 1 );
}

echo "MDS2 extension analytics checks passed.\n";
