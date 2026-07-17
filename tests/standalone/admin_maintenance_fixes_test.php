<?php

declare(strict_types=1);

namespace MillionDollarScript\Classes\Language {
	class Language {
		public static function get( string $message ): string {
			return $message;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );

	function wp_json_encode( mixed $value ): string|false {
		return json_encode( $value );
	}

	function wp_strip_all_tags( string $value ): string {
		return strip_tags( $value );
	}

	function trailingslashit( string $value ): string {
		return rtrim( $value, '/\\' ) . '/';
	}

	$pluginRoot = dirname( __DIR__, 2 );
	$failures   = [];

	function assert_maintenance_true( bool $condition, string $label ): void {
		global $failures;

		if ( ! $condition ) {
			$failures[] = $label;
		}
	}

	require_once $pluginRoot . '/src/Classes/Data/MDSPageWordPressIntegration.php';
	require_once $pluginRoot . '/src/Classes/Language/LanguageScanner.php';

	$pageClass = new \ReflectionClass( \MillionDollarScript\Classes\Data\MDSPageWordPressIntegration::class );
	$pageIntegration = $pageClass->newInstanceWithoutConstructor();
	$normalize = $pageClass->getMethod( 'getValidationErrorMessages' );
	$messages = $normalize->invoke(
		$pageIntegration,
		[
			'Simple warning',
			[
				'message'       => 'Structured warning',
				'description'   => '<b>Readable details</b>',
				'suggested_fix' => 'Repair the page',
			],
			(object) [ 'type' => 'missing_shortcode' ],
			[ 'unexpected' => [ 'nested' => 'value' ] ],
		]
	);

	assert_maintenance_true( is_array( $messages ), 'validation errors must normalize to an array' );
	assert_maintenance_true( 4 === count( $messages ), 'all supported validation error shapes must remain visible' );
	assert_maintenance_true( 'Simple warning' === $messages[0], 'plain validation warnings must remain unchanged' );
	assert_maintenance_true(
		'Structured warning Readable details Suggested Fix: Repair the page' === $messages[1],
		'structured validation errors must become readable text'
	);
	assert_maintenance_true( 'Missing shortcode' === $messages[2], 'typed validation errors must get a readable fallback' );
	assert_maintenance_true( '{"unexpected":{"nested":"value"}}' === $messages[3], 'unknown validation errors must be safely encoded' );

	$scannerClass = new \ReflectionClass( \MillionDollarScript\Classes\Language\LanguageScanner::class );
	$scanner = $scannerClass->newInstanceWithoutConstructor();
	$pluginFolder = $scannerClass->getProperty( 'plugin_folder' );
	$pluginFolder->setValue( $scanner, trailingslashit( $pluginRoot ) );
	$pluginVersion = $scannerClass->getMethod( 'plugin_version' )->invoke( $scanner );

	$pluginHeader = (string) file_get_contents( $pluginRoot . '/milliondollarscript-two.php' );
	$expectedVersion = '';
	if ( preg_match( '/^[ \t*#@\/]*Version:\s*([^\r\n]+)/mi', $pluginHeader, $matches ) ) {
		$expectedVersion = trim( (string) $matches[1] );
	}

	assert_maintenance_true( '' !== $expectedVersion, 'plugin header must provide a version' );
	assert_maintenance_true( $expectedVersion === $pluginVersion, 'language scanner must use the owning MDS2 package version' );

	$fileList = $scannerClass->getMethod( 'get_php_files' )->invoke( $scanner, $pluginRoot . '/src' );
	$sortedFileList = $fileList;
	sort( $sortedFileList, SORT_STRING );
	assert_maintenance_true( $sortedFileList === $fileList, 'language scanner input files must be deterministically sorted' );

	$wpFunctions = (string) file_get_contents( $pluginRoot . '/src/Core/include/wp_functions.php' );
	assert_maintenance_true(
		! str_contains( $wpFunctions, "require_once ABSPATH . '/wp-load.php'" ),
		'legacy helpers must not bootstrap WordPress a second time'
	);
	assert_maintenance_true(
		str_contains( $wpFunctions, "function_exists( 'wp_get_current_user' )" ),
		'legacy helpers should load pluggable functions only when unavailable'
	);

	if ( ! empty( $failures ) ) {
		fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
		exit( 1 );
	}

	echo 'All admin maintenance fix tests passed.' . PHP_EOL;
}
