<?php

declare(strict_types=1);

$pluginRoot = dirname(__DIR__, 2);
$workspaceRoot = dirname(__DIR__, 4);
$failures = [];

function assert_true( bool $condition, string $label ): void {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $label;
	}
}

function read_json_file( string $path ): array {
	$data = json_decode( file_get_contents( $path ), true );

	return is_array( $data ) ? $data : [];
}

$composerJson = read_json_file( $pluginRoot . '/composer.json' );
$composerLock = read_json_file( $pluginRoot . '/composer.lock' );
$packages = $composerLock['packages'] ?? [];
$pucPackage = null;

foreach ( $packages as $package ) {
	if ( ( $package['name'] ?? '' ) === 'yahnis-elsts/plugin-update-checker' ) {
		$pucPackage = $package;
		break;
	}
}

assert_true(
	( $composerJson['require']['yahnis-elsts/plugin-update-checker'] ?? '' ) === '^5.7',
	'composer.json must require PUC ^5.7 because source imports v5p7 classes'
);
assert_true(
	is_array( $pucPackage ) && ( $pucPackage['version'] ?? '' ) === 'v5.7',
	'composer.lock must pin Plugin Update Checker v5.7'
);
assert_true(
	in_array( 'load-v5p7.php', $pucPackage['autoload']['files'] ?? [], true ),
	'composer.lock must autoload load-v5p7.php'
);

$coreUpdater = file_get_contents( $pluginRoot . '/src/Classes/System/CorePluginUpdateChecker.php' );
$extensionHelper = file_get_contents( $pluginRoot . '/src/Classes/Extension/PluginUpdateCheckerHelper.php' );

assert_true(
	strpos( $coreUpdater, 'YahnisElsts\\PluginUpdateChecker\\v5p7\\Plugin\\UpdateChecker' ) !== false,
	'core updater must import the bundled v5p7 Plugin\\UpdateChecker class'
);
assert_true(
	strpos( $coreUpdater, 'YahnisElsts\\PluginUpdateChecker\\v5p6' ) === false,
	'core updater must not import v5p6 classes'
);
assert_true(
	strpos( $extensionHelper, "'v5p7', 'v5p6'" ) !== false,
	'extension update helper must support v5p7 and v5p6 namespaced PUC classes'
);
assert_true(
	strpos( $extensionHelper, 'Puc_v5p5_Vcs_PluginUpdateChecker' ) !== false,
	'extension update helper must keep the legacy v5p5 fallback'
);

$packageScript = $workspaceRoot . '/scripts/package-core-plugin.sh';
if ( is_file( $packageScript ) ) {
	$script = file_get_contents( $packageScript );

	assert_true(
		strpos( $script, "--exclude='composer.lock'" ) === false,
		'package script must copy composer.lock into the temporary build before composer install'
	);
}

$autoload = $pluginRoot . '/vendor/autoload.php';
if ( is_file( $autoload ) ) {
	require_once $autoload;

	assert_true(
		class_exists( \YahnisElsts\PluginUpdateChecker\v5p7\Plugin\UpdateChecker::class ),
		'installed vendor autoload must expose the v5p7 plugin update checker class'
	);
}

if ( ! empty( $failures ) ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo 'All PUC dependency tests passed.' . PHP_EOL;
