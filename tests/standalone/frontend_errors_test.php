<?php

declare(strict_types=1);

namespace MillionDollarScript\Classes\Data {
	class Options {
		public static function get_option( $name, $default = '' ) {
			return $default;
		}
	}
}

namespace MillionDollarScript\Classes\Language {
	class Language {
		public static function allowed_html(): array {
			return [];
		}

		public static function get( string $content, bool $html = true ): string {
			return $html ? $content : esc_html( $content );
		}
	}
}

namespace {
	use MillionDollarScript\Classes\System\Utility;

	define( 'ABSPATH', __DIR__ . '/../../' );
	define( 'MDS_TEXT_DOMAIN', 'million-dollar-script' );

	if ( ! function_exists( '__' ) ) {
		function __( string $content, string $domain = '' ): string {
			return $content;
		}
	}

	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( string $hook_name, $value ) {
			return $value;
		}
	}

	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( $value ): string {
			return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'is_user_logged_in' ) ) {
		function is_user_logged_in(): bool {
			return $GLOBALS['wp_logged_in'];
		}
	}

	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $value ): string {
			$value = strip_tags( (string) $value );
			$value = preg_replace( '/[\r\n\t ]+/', ' ', $value );

			return trim( $value );
		}
	}

	if ( ! function_exists( 'wp_kses' ) ) {
		function wp_kses( string $content, array $allowed_html = [] ): string {
			return $content;
		}
	}

	if ( ! function_exists( 'wp_unslash' ) ) {
		function wp_unslash( $value ) {
			return is_string( $value ) ? stripslashes( $value ) : $value;
		}
	}

	require_once __DIR__ . '/../../src/Classes/System/Utility.php';

	$failures = [];

	function reset_frontend_error_test_env(): void {
		$_GET = [];
		$_POST = [];
		$GLOBALS['wp_logged_in'] = true;
		$GLOBALS['mds_error'] = null;
	}

	function render_mds_header(): string {
		ob_start();
		include __DIR__ . '/../../src/Core/html/header.php';

		return (string) ob_get_clean();
	}

	function assert_frontend_error_contains( string $haystack, string $needle, string $label ): void {
		global $failures;

		if ( ! str_contains( $haystack, $needle ) ) {
			$failures[] = $label . ' missing ' . var_export( $needle, true );
		}
	}

	function assert_frontend_error_not_contains( string $haystack, string $needle, string $label ): void {
		global $failures;

		if ( str_contains( $haystack, $needle ) ) {
			$failures[] = $label . ' unexpectedly contained ' . var_export( $needle, true );
		}
	}

	function assert_frontend_error_same( $expected, $actual, string $label ): void {
		global $failures;

		if ( $expected !== $actual ) {
			$failures[] = $label . ' expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true );
		}
	}

	reset_frontend_error_test_env();
	$_GET['upload_error'] = 'The+uploaded+image+height+%28512+pixels%29+exceeds+the+maximum+of+100+pixels.';
	$html = render_mds_header();
	assert_frontend_error_contains(
		$html,
		'<div class="mds-error">The uploaded image height (512 pixels) exceeds the maximum of 100 pixels.</div>',
		'upload_error redirect message renders in frontend header'
	);

	reset_frontend_error_test_env();
	$_GET['mds_error'] = '<script>alert(1)</script>Please correct the highlighted fields.';
	$html = render_mds_header();
	assert_frontend_error_contains( $html, '<div class="mds-error">alert(1)Please correct the highlighted fields.</div>', 'mds_error renders sanitized text' );
	assert_frontend_error_not_contains( $html, '<script>', 'mds_error does not render script tags' );

	reset_frontend_error_test_env();
	$GLOBALS['mds_error'] = 'File upload error: The uploaded file could not be read.';
	$html = render_mds_header();
	assert_frontend_error_contains(
		$html,
		'<div class="mds-error">File upload error: The uploaded file could not be read.</div>',
		'direct upload processing error renders in frontend header'
	);

	reset_frontend_error_test_env();
	$_GET['upload_error'] = [ 'bad' ];
	assert_frontend_error_same( null, Utility::get_error(), 'array upload_error is ignored without fatal error' );

	reset_frontend_error_test_env();
	$_GET['mds_error'] = [ 'bad' ];
	$_GET['upload_error'] = 'Upload+failed.';
	assert_frontend_error_same( 'Upload failed.', Utility::get_error(), 'invalid mds_error does not block upload_error fallback' );

	if ( ! empty( $failures ) ) {
		fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
		exit( 1 );
	}

	echo 'All frontend error tests passed.' . PHP_EOL;
}
