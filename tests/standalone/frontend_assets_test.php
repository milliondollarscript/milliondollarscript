<?php

declare(strict_types=1);

namespace MillionDollarScript\Classes\Data {
	class Options {
		public static array $options = [];

		public static function get_option( $name, $default = '' ) {
			return self::$options[ $name ] ?? $default;
		}
	}

	class MDSPageMetadataManager {
		public static ?self $instance = null;
		public array $metadata = [];

		public static function getInstanceSafely(): ?self {
			return self::$instance;
		}

		public function getMetadata( int $post_id ): ?object {
			return $this->metadata[ $post_id ] ?? null;
		}
	}
}

namespace MillionDollarScript\Classes\Language {
	class Language {
		public static function get( string $text ): string {
			return $text;
		}
	}
}

namespace MillionDollarScript\Classes\Orders {
	class Orders {
		public static array $orderDataBannerIds = [];

		public static function get_order_data( ?int $banner_id = null ): array {
			self::$orderDataBannerIds[] = $banner_id;

			return [
				'BID'       => $banner_id ?? 1,
				'orderData' => true,
			];
		}
	}
}

namespace MillionDollarScript\Classes\System {
	class Utility {
		public static function get_page_url( string $page_name ): string {
			return 'https://example.test/' . $page_name . '/';
		}
	}
}

namespace {
	use MillionDollarScript\Classes\Data\MDSPageMetadataManager;
	use MillionDollarScript\Classes\Data\Options;
	use MillionDollarScript\Classes\Orders\Orders;
	use MillionDollarScript\Classes\System\Functions;

	define( 'ABSPATH', __DIR__ . '/../../' );
	define( 'MDS_BASE_URL', 'https://assets.test/' );
	define( 'MDS_BASE_PATH', __DIR__ . '/../../' );
	define( 'MDS_CORE_URL', 'https://assets.test/src/Core/' );
	define( 'MDS_CORE_PATH', __DIR__ . '/../../src/Core/' );
	define( 'MDS_DB_PREFIX', 'wp_mds_' );
	define( 'MDS_PREFIX', 'milliondollarscript_' );

	class FrontendAssetsFakeWpdb {
		public function prepare( string $query, ...$args ): string {
			return $query . ' -- ' . implode( ',', array_map( 'strval', $args ) );
		}

		public function get_row( string $query ): ?object {
			return null;
		}
	}

	class FrontendAssetsFakeF2 {
		public array $bidArgs = [];

		public function bid( int $var = 0 ): int {
			$this->bidArgs[] = $var;

			return $var > 0 ? $var : 1;
		}
	}

	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( $value ): string {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
		}
	}

	function wp_script_is( string $handle, string $status = 'enqueued' ): bool {
		if ( $status === 'registered' ) {
			return array_key_exists( $handle, $GLOBALS['wp_registered_scripts'] );
		}

		return in_array( $handle, $GLOBALS['wp_enqueued_scripts'], true );
	}

	function wp_style_is( string $handle, string $status = 'enqueued' ): bool {
		if ( $status === 'registered' ) {
			return array_key_exists( $handle, $GLOBALS['wp_registered_styles'] );
		}

		return in_array( $handle, $GLOBALS['wp_enqueued_styles'], true );
	}

	function wp_register_script( string $handle, string $src, array $deps = [], $ver = false, bool $in_footer = false ): void {
		$GLOBALS['wp_registered_scripts'][ $handle ] = compact( 'src', 'deps', 'ver', 'in_footer' );
	}

	function wp_register_style( string $handle, string $src, array $deps = [], $ver = false ): void {
		$GLOBALS['wp_registered_styles'][ $handle ] = compact( 'src', 'deps', 'ver' );
	}

	function wp_enqueue_script( string $handle ): void {
		if ( ! in_array( $handle, $GLOBALS['wp_enqueued_scripts'], true ) ) {
			$GLOBALS['wp_enqueued_scripts'][] = $handle;
		}
	}

	function wp_enqueue_style( string $handle ): void {
		if ( ! in_array( $handle, $GLOBALS['wp_enqueued_styles'], true ) ) {
			$GLOBALS['wp_enqueued_styles'][] = $handle;
		}
	}

	function wp_localize_script( string $handle, string $object_name, array $data ): void {
		$GLOBALS['wp_localized_scripts'][ $handle ][ $object_name ] = $data;
	}

	function admin_url( string $path = '' ): string {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}

	function get_site_url(): string {
		return 'https://example.test';
	}

	function esc_js( $value ) {
		return $value;
	}

	function esc_url( $value ) {
		return $value;
	}

	function wp_create_nonce( string $action ): string {
		return 'nonce-' . $action;
	}

	function get_current_user_id(): int {
		return $GLOBALS['wp_current_user_id'];
	}

	function is_user_logged_in(): bool {
		return $GLOBALS['wp_logged_in'];
	}

	function get_queried_object_id(): int {
		return $GLOBALS['queried_object_id'];
	}

	function get_query_var( string $key ) {
		return $GLOBALS['wp_query']->query_vars[ $key ] ?? '';
	}

	function get_post_meta( int $post_id, string $key, bool $single = false ) {
		return $GLOBALS['post_meta'][ $post_id ][ $key ] ?? '';
	}

	function get_post( int $post_id ): ?object {
		return $GLOBALS['posts'][ $post_id ] ?? null;
	}

	function load_banner_constants( int $bid ): array {
		return [
			'G_WIDTH'      => 10,
			'G_HEIGHT'     => 10,
			'BLK_WIDTH'    => 5,
			'BLK_HEIGHT'   => 5,
			'G_MAX_BLOCKS' => 100,
			'G_MIN_BLOCKS' => 1,
			'G_PRICE'      => 1.5,
		];
	}

	require_once __DIR__ . '/../../src/Classes/System/Functions.php';

	$failures = [];

	function reset_frontend_asset_test_env(): void {
		$GLOBALS['wp_registered_scripts'] = [];
		$GLOBALS['wp_registered_styles']  = [];
		$GLOBALS['wp_enqueued_scripts']   = [];
		$GLOBALS['wp_enqueued_styles']    = [];
		$GLOBALS['wp_localized_scripts']  = [];
		$GLOBALS['wp_current_user_id']    = 7;
		$GLOBALS['wp_logged_in']          = true;
		$GLOBALS['queried_object_id']     = 0;
		$GLOBALS['post_meta']             = [];
		$GLOBALS['posts']                 = [];
		$GLOBALS['post']                  = null;
		$GLOBALS['wp_query']              = (object) [ 'query_vars' => [] ];
		$GLOBALS['wpdb']                  = new FrontendAssetsFakeWpdb();
		$GLOBALS['f2']                    = new FrontendAssetsFakeF2();

		Options::$options = [
			'enable-mouseover'        => 'NO',
			'endpoint'                => 'milliondollarscript',
			'invert-pixels'           => 'NO',
			'selection-adjacency-mode' => 'ADJACENT',
			'use-ajax'                => 'YES',
			'users-order-page'        => 0,
		];

		$metadata_manager = new MDSPageMetadataManager();
		MDSPageMetadataManager::$instance = $metadata_manager;
		Orders::$orderDataBannerIds = [];

		$reflection = new ReflectionClass( Functions::class );
		$property = $reflection->getProperty( 'localized_order_scripts' );
		$property->setAccessible( true );
		$property->setValue( null, [] );
	}

	function assert_asset_true( bool $condition, string $label ): void {
		global $failures;

		if ( ! $condition ) {
			$failures[] = $label;
		}
	}

	function assert_asset_same( $expected, $actual, string $label ): void {
		global $failures;

		if ( $expected !== $actual ) {
			$failures[] = $label . ' expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true );
		}
	}

	function assert_handle_enqueued( string $handle, string $label ): void {
		assert_asset_true( in_array( $handle, $GLOBALS['wp_enqueued_scripts'], true ), $label );
	}

	function assert_handle_not_enqueued( string $handle, string $label ): void {
		assert_asset_true( ! in_array( $handle, $GLOBALS['wp_enqueued_scripts'], true ), $label );
	}

	function assert_script_src_contains( string $handle, string $needle, string $label ): void {
		$src = $GLOBALS['wp_registered_scripts'][ $handle ]['src'] ?? '';
		assert_asset_true( str_contains( $src, $needle ), $label . ' src was ' . var_export( $src, true ) );
	}

	function assert_localized( string $handle, string $object_name, string $label ): void {
		assert_asset_true( isset( $GLOBALS['wp_localized_scripts'][ $handle ][ $object_name ] ), $label );
	}

	reset_frontend_asset_test_env();
	$GLOBALS['queried_object_id'] = 42;
	$GLOBALS['post_meta'][42]['_mds_page_type'] = 'order';
	Functions::enqueue_scripts( '' );
	assert_handle_enqueued( 'mds-select', 'advanced AJAX order page enqueues mds-select' );
	assert_script_src_contains( 'mds-select', 'src/Core/js/select.min.js', 'advanced AJAX order page registers select.min.js' );
	assert_localized( 'mds-select', 'MDS_OBJECT', 'advanced AJAX order page localizes MDS_OBJECT' );
	assert_handle_not_enqueued( 'mds-order', 'advanced AJAX order page does not enqueue SIMPLE order script' );
	assert_handle_not_enqueued( 'mds-core', 'frontend loader does not enqueue missing mds-core handle' );

	reset_frontend_asset_test_env();
	Options::$options['use-ajax'] = 'SIMPLE';
	$GLOBALS['post'] = (object) [
		'ID'           => 51,
		'post_content' => '',
	];
	MDSPageMetadataManager::$instance->metadata[51] = (object) [ 'page_type' => 'order' ];
	Functions::enqueue_scripts();
	assert_handle_enqueued( 'mds-order', 'SIMPLE metadata order page enqueues mds-order' );
	assert_script_src_contains( 'mds-order', 'src/Core/js/order.min.js', 'SIMPLE metadata order page registers order.min.js' );
	assert_localized( 'mds-order', 'MDS_OBJECT', 'SIMPLE metadata order page localizes MDS_OBJECT' );
	assert_handle_not_enqueued( 'mds-select', 'SIMPLE metadata order page does not enqueue advanced select script' );

	reset_frontend_asset_test_env();
	Functions::register_scripts();
	wp_enqueue_script( 'mds' );
	Functions::enqueue_scripts( [ 'type' => 'order', 'id' => '9' ] );
	assert_handle_enqueued( 'mds-select', 'late shortcode order render enqueues mds-select after mds was already enqueued' );
	assert_asset_same( [ 9 ], $GLOBALS['f2']->bidArgs, 'late shortcode order render passes shortcode banner id to select data' );
	assert_asset_same( 9, $GLOBALS['wp_localized_scripts']['mds-select']['MDS_OBJECT']['BID'] ?? null, 'late shortcode MDS_OBJECT uses shortcode banner id' );

	reset_frontend_asset_test_env();
	$GLOBALS['post'] = (object) [
		'ID'           => 62,
		'post_content' => '[milliondollarscript type="order" id="6"]',
	];
	Functions::enqueue_scripts();
	assert_handle_enqueued( 'mds-select', 'order shortcode in page content enqueues mds-select' );
	assert_asset_same( [ 6 ], $GLOBALS['f2']->bidArgs, 'order shortcode in page content passes parsed banner id' );

	reset_frontend_asset_test_env();
	$GLOBALS['post'] = (object) [
		'ID'           => 70,
		'post_content' => '[milliondollarscript type="grid"]',
	];
	$GLOBALS['wp_query']->query_vars['milliondollarscript'] = 'order';
	Functions::enqueue_scripts();
	assert_handle_enqueued( 'mds-select', 'endpoint order page enqueues mds-select even with non-order global post' );

	reset_frontend_asset_test_env();
	Options::$options['users-order-page'] = 88;
	$GLOBALS['queried_object_id'] = 88;
	Functions::enqueue_scripts();
	assert_handle_enqueued( 'mds-select', 'legacy users-order-page option enqueues mds-select' );

	reset_frontend_asset_test_env();
	$GLOBALS['wp_logged_in'] = false;
	$GLOBALS['queried_object_id'] = 91;
	$GLOBALS['post_meta'][91]['_mds_page_type'] = 'order';
	Functions::enqueue_scripts();
	assert_handle_not_enqueued( 'mds-select', 'logged-out order page does not enqueue mds-select' );
	assert_handle_not_enqueued( 'mds-order', 'logged-out order page does not enqueue mds-order' );

	reset_frontend_asset_test_env();
	$GLOBALS['post'] = (object) [
		'ID'           => 99,
		'post_content' => '[milliondollarscript type="grid"]',
	];
	Functions::enqueue_scripts();
	assert_handle_not_enqueued( 'mds-select', 'non-order page does not enqueue mds-select' );
	assert_handle_not_enqueued( 'mds-order', 'non-order page does not enqueue mds-order' );

	if ( ! empty( $failures ) ) {
		fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
		exit( 1 );
	}

	echo 'All frontend asset tests passed.' . PHP_EOL;
}
