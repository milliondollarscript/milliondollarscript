<?php

/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2024, Ryan Rhode
 * @license     https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *    Million Dollar Script
 *    Pixels to Profit: Ignite Your Revolution
 *    https://milliondollarscript.com/
 *
 */

namespace MillionDollarScript\Classes;

defined( 'ABSPATH' ) or exit;

class Functions {
	private static ?string $tooltips;

	public static function get_script_data(): array {
		// global $f2;
		// $BID         = $f2->bid();
		// $banner_data = load_banner_constants( $BID );

		return [
			'ajaxurl'          => esc_js( admin_url( 'admin-ajax.php' ) ),
			'mds_core_nonce'   => esc_js( wp_create_nonce( 'mds_core_nonce' ) ),
			'mds_nonce'        => esc_js( wp_create_nonce( 'mds_nonce' ) ),
			'wp'               => esc_js( get_site_url() ),
			// 'winWidth'         => intval( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ),
			// 'winHeight'        => intval( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ),
			'time'             => esc_js( time() ),
			'MDS_CORE_URL'     => esc_js( MDS_CORE_URL ),
			'REDIRECT_SWITCH'  => esc_js( Config::get( 'REDIRECT_SWITCH' ) ),
			'REDIRECT_URL'     => esc_js( Config::get( 'REDIRECT_URL' ) ),
			'ENABLE_MOUSEOVER' => esc_js( Config::get( 'ENABLE_MOUSEOVER' ) ),
			'TOOLTIP_TRIGGER'  => esc_js( Config::get( 'TOOLTIP_TRIGGER' ) ),
			'MAX_POPUP_SIZE'   => esc_js( Options::get_option( 'max-popup-size' ) ),
			// 'BID'              => intval( $BID ),
			'link_target'      => esc_js( \MillionDollarScript\Classes\Options::get_option( 'link-target' ) ),
			'WAIT'             => Language::get( 'Please Wait...' ),
			'UPLOADING'        => Language::get( 'Uploading...' ),
			'SAVING'           => Language::get( 'Saving...' ),
			'COMPLETING'       => Language::get( 'Completing...' ),
			'CONFIRMING'       => Language::get( 'Confirming...' ),
		];
	}

	public static function get_select_data(): array {

		global $f2, $wpdb;
		$BID = $f2->bid();

		if ( ! is_numeric( $BID ) ) {
			die();
		}

		$banner_data = load_banner_constants( $BID );

		$table_name = MDS_DB_PREFIX . 'orders';
		$user_id    = get_current_user_id();

		$order_id = Orders::get_current_order_id();

		if ( ! empty( $order_id ) ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d AND order_id = %d",
				$user_id,
				$order_id
			);
		} else {
			$status = 'new';
			$sql    = $wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d AND status = %s",
				$user_id,
				$status
			);
		}
		$order_result = $wpdb->get_row( $sql );
		$order_row    = $order_result ? (array) $order_result : [];

		// load any existing blocks for this order
		$order_row_blocks = '';
		if ( isset( $order_row['blocks'] ) ) {
			if ( ! empty( $order_row['blocks'] ) || $order_row['blocks'] == '0' ) {
				$order_row_blocks = $order_row['blocks'];
			}
		}
		$block_ids    = $order_row_blocks !== '' ? array_map( 'intval', explode( ',', $order_row_blocks ) ) : [];
		$block_str    = $order_row_blocks !== '' ? implode( ',', $block_ids ) : "";
		$order_blocks = array_map( function ( $block_id ) use ( $BID ) {
			$pos = get_block_position( $block_id, $BID );

			return [
				'block_id' => $block_id,
				'x'        => $pos['x'],
				'y'        => $pos['y'],
			];
		}, $block_ids );

		return [
			'NONCE'                => wp_create_nonce( 'mds-select' ),
			'UPDATE_ORDER'         => esc_url( Utility::get_page_url( 'update-order' ) ),
			'USE_AJAX'             => Config::get( 'USE_AJAX' ),
			'block_str'            => $block_str,
			'grid_width'           => intval( $banner_data['G_WIDTH'] ),
			'grid_height'          => intval( $banner_data['G_HEIGHT'] ),
			'BLK_WIDTH'            => intval( $banner_data['BLK_WIDTH'] ),
			'BLK_HEIGHT'           => intval( $banner_data['BLK_HEIGHT'] ),
			'G_MAX_BLOCKS'         => intval( $banner_data['G_MAX_BLOCKS'] ),
			'G_MIN_BLOCKS'         => intval( $banner_data['G_MIN_BLOCKS'] ),
			'G_PRICE'              => floatval( $banner_data['G_PRICE'] ),
			'blocks'               => $order_blocks,
			'user_id'              => get_current_user_id(),
			'BID'                  => intval( $BID ),
			'time'                 => time(),
			'advertiser_max_order' => Language::get( 'Cannot place pixels on order. You have reached the order limit for this grid. Please review your Order History.' ),
			'not_adjacent'         => Language::get( 'You must select a block adjacent to another one.' ),
			'no_blocks_selected'   => Language::get( 'You have no blocks selected.' ),
			'MDS_CORE_URL'         => esc_url( MDS_CORE_URL ),
			'INVERT_PIXELS'        => Config::get( 'INVERT_PIXELS' ),
			'WAIT'                 => Language::get( 'Please Wait! Reserving Pixels...' ),
		];
	}

	public static function get_order_data(): array {

		global $f2, $wpdb;
		$BID = $f2->bid();

		if ( ! is_numeric( $BID ) ) {
			die();
		}

		$banner_data = load_banner_constants( $BID );

		$table_name = MDS_DB_PREFIX . 'orders';
		$user_id    = get_current_user_id();
		$status     = 'new';

		$order_id = Orders::get_current_order_id();

		if ( ! empty( $order_id ) ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d AND order_id = %d",
				$user_id,
				$order_id
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d AND status = %s",
				$user_id,
				$status
			);
		}
		$order_result = $wpdb->get_row( $sql );
		$order_row    = $order_result ? (array) $order_result : [];

		// load any existing blocks for this order
		$order_row_blocks = ! empty( $order_row['blocks'] ) || isset( $order_row['blocks'] ) && $order_row['blocks'] == '0' ? $order_row['blocks'] : '';
		$block_ids        = $order_row_blocks !== '' ? array_map( 'intval', explode( ',', $order_row_blocks ) ) : [];
		$block_str        = $order_row_blocks !== '' ? implode( ',', $block_ids ) : "";

		$low_x = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];
		$low_y = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];

		$sql    = $wpdb->prepare( "SELECT block_info FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%s", \MillionDollarScript\Classes\Orders::get_current_order_id() );
		$result = $wpdb->get_results( $sql );

		if ( ! empty( $result ) ) {
			$block_info = unserialize( $result[0]->block_info );
		}

		$init = false;
		if ( isset( $block_info ) && is_array( $block_info ) ) {

			foreach ( $block_info as $block ) {

				if ( $low_x >= $block['map_x'] ) {
					$low_x = $block['map_x'];
					$init  = true;
				}

				if ( $low_y >= $block['map_y'] ) {
					$low_y = $block['map_y'];
					$init  = true;
				}
			}
		}

		$reinit = false;
		if ( isset( $_REQUEST['banner_change'] ) && $_REQUEST['banner_change'] != '' ) {
			$reinit = true;
		}

		if ( ! $init || $reinit === true ) {
			$low_x     = 0;
			$low_y     = 0;
			$is_moving = true;
		} else {
			$is_moving = false;
		}

		return [
			'NONCE'           => wp_create_nonce( 'mds-order' ),
			'UPDATE_ORDER'    => esc_url( Utility::get_page_url( 'update-order' ) ),
			'CHECK_SELECTION' => esc_url( Utility::get_page_url( 'check-selection' ) ),
			'MAKE_SELECTION'  => esc_url( Utility::get_page_url( 'make-selection' ) ),
			'low_x'           => intval( $low_x ),
			'low_y'           => intval( $low_y ),
			'is_moving'       => $is_moving,
			'block_str'       => $block_str,
			'grid_width'      => intval( $banner_data['G_WIDTH'] ),
			'grid_height'     => intval( $banner_data['G_HEIGHT'] ),
			'BLK_WIDTH'       => intval( $banner_data['BLK_WIDTH'] ),
			'BLK_HEIGHT'      => intval( $banner_data['BLK_HEIGHT'] ),
			'user_id'         => get_current_user_id(),
			'BID'             => intval( $BID ),
			'time'            => time(),
			'WAIT'            => Language::get( 'Please Wait! Reserving Pixels...' ),
			'WRITE'           => Language::get( 'Write Your Ad' ),
		];
	}

	/**
	 * Register scripts and styles
	 */
	public static function register_scripts(): void {
		if ( wp_script_is( 'mds' ) ) {
			return;
		}

		wp_register_style( 'mds', MDS_BASE_URL . 'src/Assets/css/mds.css', [], filemtime( MDS_BASE_PATH . 'src/Assets/css/mds.css' ) );

		self::$tooltips = Config::get( 'ENABLE_MOUSEOVER' );
		if ( self::$tooltips == 'POPUP' ) {
			wp_register_script( 'popper', MDS_CORE_URL . 'js/third-party/popper.min.js', [], filemtime( MDS_CORE_PATH . 'js/third-party/popper.min.js' ), true );
			wp_register_script( 'tippy', MDS_CORE_URL . 'js/third-party/tippy-bundle.umd.min.js', [ 'popper' ], filemtime( MDS_CORE_PATH . 'js/third-party/tippy-bundle.umd.min.js' ), true );
			wp_register_style( 'tippy-light', MDS_CORE_URL . 'css/tippy/light.css', [], filemtime( MDS_CORE_PATH . 'css/tippy/light.css' ) );
			$deps = [ 'jquery', 'tippy' ];
		} else {
			$deps = [];
		}

		wp_register_script( 'image-scale', MDS_CORE_URL . 'js/third-party/image-scale.min.js', $deps, filemtime( MDS_CORE_PATH . 'js/third-party/image-scale.min.js' ), true );
		wp_register_script( 'image-map', MDS_CORE_URL . 'js/third-party/image-map.min.js', [ 'image-scale' ], filemtime( MDS_CORE_PATH . 'js/third-party/image-map.min.js' ), true );
		wp_register_script( 'contact', MDS_CORE_URL . 'js/third-party/contact.nomodule.min.js', [ 'image-map' ], filemtime( MDS_CORE_PATH . 'js/third-party/contact.nomodule.min.js' ), true );

		wp_register_script( 'mds', MDS_BASE_URL . 'src/Assets/js/mds.min.js', [
			'jquery',
			'contact',
			'image-scale',
			'image-map'
		], filemtime( MDS_BASE_PATH . 'src/Assets/js/mds.min.js' ), true );
		wp_add_inline_script( 'mds', 'const MDS = ' . json_encode( self::get_script_data() ), 'before' );

		$order_script  = "";
		$data_function = "";

		$register_order_script = false;

		global $post;
		if ( isset( $post ) && $post->ID == Options::get_option( 'users-order-page' ) ) {
			$register_order_script = true;
		} else {
			global $wp_query;
			$MDS_ENDPOINT = Options::get_option( 'endpoint', 'milliondollarscript' );
			if ( isset( $wp_query->query_vars[ $MDS_ENDPOINT ] ) ) {
				$page = $wp_query->query_vars[ $MDS_ENDPOINT ];
				if ( $page == 'order' ) {
					$register_order_script = true;
				}
			}
		}

		if ( $register_order_script ) {

			// select.js
			if ( is_user_logged_in() && Config::get( 'USE_AJAX' ) == 'YES' ) {
				$order_script  = 'select';
				$data_function = self::get_select_data();
			}

			// order.js
			if ( is_user_logged_in() && Config::get( 'USE_AJAX' ) == 'SIMPLE' ) {
				$order_script  = 'order';
				$data_function = self::get_order_data();
			}

			if ( ! empty( $order_script ) ) {
				wp_register_script( 'mds-' . $order_script, MDS_CORE_URL . 'js/' . $order_script . '.min.js', [
					'jquery',
					'mds',
					'contact'
				], filemtime( MDS_CORE_PATH . 'js/' . $order_script . '.min.js' ), true );
				wp_add_inline_script( 'mds-' . $order_script, 'const MDS_OBJECT = ' . json_encode( $data_function ), 'before' );
			}
		}
	}

	/**
	 * Enqueue scripts and styles
	 */
	public static function enqueue_scripts(): void {
		wp_enqueue_script( 'jquery' );

		wp_enqueue_style( 'mds' );

		self::$tooltips = Config::get( 'ENABLE_MOUSEOVER' );
		if ( self::$tooltips == 'POPUP' ) {
			wp_enqueue_script( 'popper' );
			wp_enqueue_script( 'tippy' );
			wp_enqueue_style( 'tippy-light' );
		}

		wp_enqueue_script( 'image-scale' );
		wp_enqueue_script( 'image-map' );
		wp_enqueue_script( 'contact' );

		wp_enqueue_script( 'mds-core' );

		wp_enqueue_script( 'mds' );
		wp_enqueue_script( 'mds-select' );
		wp_enqueue_script( 'mds-order' );
	}

	public static function sanitize_array( $value ): array|string {
		$allowed_html = array(
			'comma' => array()
		);

		$value           = str_replace( ',', '<comma>', $value );
		$sanitized_value = wp_kses( $value, $allowed_html );

		return str_replace( '<comma>', ',', $sanitized_value );
	}

	public static function order_screen(): void {
		// TODO: handle packages
		if ( ! isset( $_REQUEST['banner_change'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && ! isset( $_POST['type'] ) ) {
			\MillionDollarScript\Classes\Functions::verify_nonce( 'mds-form' );

			if ( isset( $_POST['mds_dest'] ) ) {
				if ( $_POST['mds_dest'] == 'order' ) {
					// Simple pixel selection
					require_once MDS_CORE_PATH . 'users/order-pixels.php';
				}
			}
		} else {
			require_once MDS_CORE_PATH . 'users/' . ( Config::get( 'USE_AJAX' ) == 'SIMPLE' ? 'order-pixels.php' : 'select.php' );
		}
	}

	public static function verify_nonce( $action, $nonce = null ): void {
		if ( $nonce === null && isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = $_REQUEST['_wpnonce'];
		}

		if ( $nonce === null || false === wp_verify_nonce( $nonce, $action ) ) {
			wp_nonce_ays( null );
			exit;
		}
	}

	public static function no_orders(): void {
		require_once MDS_CORE_PATH . "html/header.php";

		Language::out( '<h1>No new orders in progress</h1>' );
		Language::out_replace(
			'<p>You don\'t have any orders in progress. Please go <a href="%ORDER_URL%">here to order pixels</a>.</p>',
			'%ORDER_URL%',
			Utility::get_page_url( 'order' )
		);

		require_once MDS_CORE_PATH . "html/footer.php";
	}

	public static function not_enough_blocks( mixed $order_id, $min_blocks ): void {
		Language::out( '<h3>Not enough blocks selected</h3>' );
		Language::out_replace(
			'<p>You are required to select at least %MIN_BLOCKS% blocks form the grid. Please go back to select more pixels.</p>',
			[ '%MIN_BLOCKS%' ],
			[ $min_blocks ]
		);
		display_edit_order_button( $order_id );
	}

	/**
	 * Generate navigation links.
	 *
	 * @param int $current_page
	 * @param int $total_records
	 * @param int $records_per_page
	 * @param array $additional_params
	 * @param string $page
	 * @param string $order_by
	 *
	 * @return string
	 */
	public static function generate_navigation( int $current_page, int $total_records, int $records_per_page, array $additional_params = [], string $page = '', string $order_by = '' ): string {
		$total_pages       = ceil( $total_records / $records_per_page );
		$additional_params = array_map( 'sanitize_text_field', $additional_params );
		$order_by          = sanitize_text_field( $order_by );
		$navigation        = "";

		// First page link
		if ( $current_page > 1 ) {
			$first_params = array_merge( [ 'offset' => 0, 'order_by' => $order_by ], $additional_params );
			$first_url    = add_query_arg( $first_params, $page );
			$navigation   .= "<a href='" . esc_url( $first_url ) . "'>«</a>";
		}

		// Previous link
		if ( $current_page > 1 ) {
			$previous_page = $current_page - 1;
			$prev_params   = array_merge( [ 'offset' => ( ( $previous_page - 1 ) * $records_per_page ), 'order_by' => $order_by ], $additional_params );
			$prev_url      = add_query_arg( $prev_params, $page );
			$navigation    .= "<a href='" . esc_url( $prev_url ) . "'><</a>";
		}

		// Page links
		$start_page = max( 1, $current_page - 2 );
		$end_page   = min( $total_pages, $current_page + 2 );
		for ( $i = $start_page; $i <= $end_page; $i ++ ) {
			$page_params = array_merge( [ 'offset' => ( ( $i - 1 ) * $records_per_page ), 'order_by' => $order_by ], $additional_params );
			$page_url    = add_query_arg( $page_params, $page );
			if ( $i == $current_page ) {
				$navigation .= "<span>" . $i . "</span>";
			} else {
				$navigation .= "<a href='" . esc_url( $page_url ) . "'>" . $i . "</a>";
			}
		}

		// Next link
		if ( $current_page < $total_pages ) {
			$next_page   = $current_page + 1;
			$next_params = array_merge( [ 'offset' => ( ( $next_page - 1 ) * $records_per_page ), 'order_by' => $order_by ], $additional_params );
			$next_url    = add_query_arg( $next_params, $page );
			$navigation  .= "<a href='" . esc_url( $next_url ) . "'>></a>";
		}

		// Last page link
		if ( $current_page < $total_pages ) {
			$last_page   = $total_pages;
			$last_params = array_merge( [ 'offset' => ( ( $last_page - 1 ) * $records_per_page ), 'order_by' => $order_by ], $additional_params );
			$last_url    = add_query_arg( $last_params, $page );
			$navigation  .= "<a href='" . esc_url( $last_url ) . "'>»</a>";
		}

		return '<div class="mds-navigation">' . $navigation . '</div>';
	}
}
