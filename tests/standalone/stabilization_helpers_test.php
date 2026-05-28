<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/../../' );
define( 'MDS_PREFIX', 'milliondollarscript_' );

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $value ) {
		return strip_tags( (string) $value );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url ) {
		return parse_url( $url );
	}
}

if ( ! function_exists( 'wp_http_validate_url' ) ) {
	function wp_http_validate_url( $url ) {
		return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : false;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url, $protocols = null ) {
		return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $filename ) {
		return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $filename );
	}
}

if ( ! function_exists( 'wp_max_upload_size' ) ) {
	function wp_max_upload_size() {
		return 1024 * 1024;
	}
}

if ( ! function_exists( 'size_format' ) ) {
	function size_format( $bytes ) {
		return $bytes . ' bytes';
	}
}

if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
	function wp_check_filetype_and_ext( $tmp_name, $filename, $mimes = null ) {
		$extension = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		$mime      = [
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
		][ $extension ] ?? '';

		return [
			'ext'  => $mime ? $extension : false,
			'type' => $mime ?: false,
		];
	}
}

require_once __DIR__ . '/../../src/Classes/System/Request.php';
require_once __DIR__ . '/../../src/Classes/System/Url.php';
require_once __DIR__ . '/../../src/Classes/System/FileValidator.php';
require_once __DIR__ . '/../../src/Classes/Admin/MigrationReadinessReport.php';

use MillionDollarScript\Classes\Admin\MigrationReadinessReport;
use MillionDollarScript\Classes\System\FileValidator;
use MillionDollarScript\Classes\System\Request;
use MillionDollarScript\Classes\System\Url;

$failures = [];

function assert_same( $expected, $actual, string $label ): void {
	global $failures;

	if ( $expected !== $actual ) {
		$failures[] = $label . ' expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true );
	}
}

assert_same( 'https://example.com', Url::advertiser_url( 'example.com' ), 'bare domain defaults to https' );
assert_same( 'http://example.com/path', Url::advertiser_url( 'http://example.com/path' ), 'http URL allowed' );
assert_same( 'https://example.com/path', Url::advertiser_url( '//example.com/path' ), 'scheme-relative URL normalized' );
assert_same( '', Url::advertiser_url( 'javascript:alert(1)' ), 'javascript URL rejected' );
assert_same( '', Url::advertiser_url( 'https://user@example.com' ), 'credential URL rejected' );
assert_same( '', Url::advertiser_url( 'https://exa mple.com' ), 'malformed host rejected' );

$request = [
	'id'      => '42',
	'bad_id'  => '-2',
	'enabled' => 'yes',
	'mode'    => 'rectangle',
	'payload' => '{"one":"two","nested":{"three":"four"},"bad":{}}',
];

assert_same( 42, Request::positive_int( $request, 'id' ), 'positive int parsed' );
assert_same( 9, Request::positive_int( $request, 'bad_id', 9 ), 'negative positive int rejected' );
assert_same( true, Request::bool( $request, 'enabled' ), 'boolean parsed' );
assert_same( 'RECTANGLE', Request::mode( $request, 'mode', [ 'ADJACENT', 'RECTANGLE', 'NONE' ] ), 'mode parsed' );
assert_same( 'two', Request::json_payload( $request, 'payload' )['one'] ?? null, 'JSON payload parsed' );

$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true );
$tmp = tempnam( sys_get_temp_dir(), 'mds-png-' );
file_put_contents( $tmp, $png );
$valid_upload = FileValidator::validate_image_upload(
	[
		'tmp_name' => $tmp,
		'name'     => 'pixel.png',
		'size'     => filesize( $tmp ),
	],
	false
);
assert_same( true, $valid_upload['valid'], 'valid PNG upload accepted' );
unlink( $tmp );

$tmp = tempnam( sys_get_temp_dir(), 'mds-gif-' );
file_put_contents( $tmp, "GIF89a<?php echo 'bad';" );
$bad_upload = FileValidator::validate_image_upload(
	[
		'tmp_name' => $tmp,
		'name'     => 'pixel.gif',
		'size'     => filesize( $tmp ),
	],
	false
);
assert_same( false, $bad_upload['valid'], 'malicious image payload rejected' );
unlink( $tmp );

$report = MigrationReadinessReport::aggregate(
	[
		'banners'                   => 2,
		'block_statuses'            => [ 'sold' => 3, 'nfs' => 1 ],
		'packages'                  => 1,
		'price_zones'               => 2,
		'order_statuses'            => [ 'completed' => 2, 'renew_wait' => 1 ],
		'woocommerce_linked_orders' => 1,
		'woocommerce_enabled'       => false,
		'nfs_blocks'                => 1,
		'unavailable_blocks'        => 4,
		'account_manage_renew_orders' => 1,
		'mds_shortcode_pages'       => 3,
	],
	[
		[ 'page_type' => 'grid', 'exists' => true, 'page_id' => 10, 'has_shortcode' => true ],
		[ 'page_type' => 'manage', 'exists' => true, 'page_id' => 11, 'has_shortcode' => true ],
		[ 'page_type' => 'payment', 'exists' => false, 'page_id' => 0, 'has_shortcode' => false ],
	],
	[
		'missing_block_images'   => 1,
		'invalid_block_images'   => 2,
		'missing_ad_attachments' => 3,
		'invalid_ad_attachments' => 4,
	]
);

assert_same( true, $report['flags']['packages_price_zones']['active'], 'packages/price zones flag active' );
assert_same( true, $report['flags']['woocommerce']['active'], 'WooCommerce flag active through linked orders' );
assert_same( 2, $report['counts']['pages']['existing'], 'page aggregate counts existing pages' );
assert_same( 2, $report['counts']['media']['invalid_block_images'], 'media aggregate preserves invalid block count' );

if ( ! empty( $failures ) ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo 'All stabilization helper tests passed.' . PHP_EOL;
