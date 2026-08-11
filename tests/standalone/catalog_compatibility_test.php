<?php

declare(strict_types=1);

$pluginRoot = dirname(__DIR__, 2);
$failures   = [];

function add_query_arg( array $args, string $url ): string {
	$separator = str_contains( $url, '?' ) ? '&' : '?';

	return $url . $separator . http_build_query( $args );
}

function assert_catalog_true( bool $condition, string $label ): void {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $label;
	}
}

require_once $pluginRoot . '/src/Classes/Extensions/CatalogCompatibility.php';

$class = \MillionDollarScript\Classes\Extensions\CatalogCompatibility::class;
$args  = $class::query_args();
$pluginHeader = (string) file_get_contents( $pluginRoot . '/milliondollarscript-two.php' );
$pluginVersion = '';
if ( preg_match( '/^[ \t*#@\/]*Version:\s*([^\r\n]+)/mi', $pluginHeader, $matches ) ) {
	$pluginVersion = trim( (string) $matches[1] );
}

assert_catalog_true( 'mds2' === ( $args['platform'] ?? '' ), 'platform marker must identify MDS2' );
assert_catalog_true( '2' === ( $args['mds_generation'] ?? '' ), 'generation marker must be 2' );
assert_catalog_true( 'milliondollarscript-two' === ( $args['core'] ?? '' ), 'core marker must use the legacy package slug' );
assert_catalog_true( '' !== $pluginVersion, 'test fixture must expose an MDS2 plugin version' );
assert_catalog_true( $pluginVersion === ( $args['core_version'] ?? '' ), 'core version must come from the MDS2 plugin package' );

$url   = $class::append_query( 'https://example.test/api/public/extensions?limit=10' );
$query = [];
parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );

assert_catalog_true( '10' === ( $query['limit'] ?? '' ), 'existing query parameters must be preserved' );
assert_catalog_true( 'mds2' === ( $query['platform'] ?? '' ), 'URL marker must identify MDS2' );
assert_catalog_true( '2' === ( $query['mds_generation'] ?? '' ), 'URL generation marker must be 2' );

$body = $class::request_body(
	[
		'platform'     => 'wrong',
		'licenseKey'   => 'test-key',
		'extension_id' => 'example',
	]
);

assert_catalog_true( 'mds2' === ( $body['platform'] ?? '' ), 'trusted compatibility marker must override caller input' );
assert_catalog_true( 'test-key' === ( $body['licenseKey'] ?? '' ), 'request payload fields must be preserved' );

$coreUpdater = (string) file_get_contents( $pluginRoot . '/src/Classes/System/CorePluginUpdateVcsApi.php' );
assert_catalog_true(
	str_contains( $coreUpdater, 'CatalogCompatibility::request_body( $request_body )' ),
	'core update requests must include MDS2 compatibility markers'
);

$integrationFiles = [
	'src/Classes/Extension/API.php'                       => 'CatalogCompatibility::request_body',
	'src/Classes/Extension/PluginUpdateCheckerHelper.php' => 'CatalogCompatibility::request_body',
	'src/Classes/Extensions/UnifiedExtensionsManager.php' => 'CatalogCompatibility::append_query',
	'src/Classes/Pages/Extensions.php'                    => 'CatalogCompatibility::',
	'src/Classes/Products/ProductsClient.php'             => 'CatalogCompatibility::',
	'src/Classes/System/ExtensionServerTest.php'           => 'CatalogCompatibility::append_query',
];

foreach ( $integrationFiles as $relativePath => $expectedCall ) {
	$source = (string) file_get_contents( $pluginRoot . '/' . $relativePath );
	assert_catalog_true(
		str_contains( $source, $expectedCall ),
		$relativePath . ' must add MDS2 compatibility markers'
	);
}

$extensionsPage = (string) file_get_contents( $pluginRoot . '/src/Classes/Pages/Extensions.php' );
assert_catalog_true(
	str_contains( $extensionsPage, "'one_time'     => 'one_time'" )
		&& str_contains( $extensionsPage, "'lifetime'     => 'one_time'" ),
	'MDS2 must normalize server lifetime plans to the one_time catalog contract'
);
assert_catalog_true(
	str_contains( $extensionsPage, "private const STRIPE_PRICE_PLANS = ['monthly', 'yearly', 'one_time'];" ),
	'MDS2 must preserve monthly, yearly, and lifetime purchase choices returned by the server'
);
assert_catalog_true(
	str_contains( $extensionsPage, "\$allowed_plans = ['one_time', 'monthly', 'yearly'];" ),
	'MDS2 must constrain checkout requests to supported server plan keys'
);
$productsClient = (string) file_get_contents( $pluginRoot . '/src/Classes/Products/ProductsClient.php' );
assert_catalog_true(
	str_contains( $productsClient, "'plan'          => \$args['plan']," )
		&& str_contains( $productsClient, "'priceId'       => \$args['priceId']," ),
	'MDS2 checkout requests must send the selected server plan and price for validation'
);

if ( ! empty( $failures ) ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo 'All catalog compatibility tests passed.' . PHP_EOL;
