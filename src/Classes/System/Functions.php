<?php

/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2025, Ryan Rhode
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

namespace MillionDollarScript\Classes\System;

use MillionDollarScript\Classes\Data\Config;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Data\MDSPageMetadataManager;
use MillionDollarScript\Classes\Email\Emails;
use MillionDollarScript\Classes\Forms\FormFields;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Blocks;
use MillionDollarScript\Classes\Orders\Orders;
use WP_Post;

defined( 'ABSPATH' ) or exit;

class Functions {
	private static ?string $tooltips;
	private static array $localized_order_scripts = [];

	public static function get_script_data(): array {
		// global $f2;
		// $BID         = $f2->bid();
		// $banner_data = load_banner_constants( $BID );

		return [
			'ajaxurl'          => esc_js( admin_url( 'admin-ajax.php' ) ),
			'manageurl'        => esc_js( Utility::get_page_url( 'manage' ) ),
			'mds_core_nonce'   => esc_js( wp_create_nonce( 'mds_core_nonce' ) ),
			'mds_nonce'        => esc_js( wp_create_nonce( 'mds_nonce' ) ),
			'wp'               => esc_js( get_site_url() ),
			// 'winWidth'         => intval( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ),
			// 'winHeight'        => intval( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ),
			'time'             => esc_js( time() ),
			'MDS_CORE_URL'     => esc_js( MDS_CORE_URL ),
			'REDIRECT_SWITCH'  => esc_js( Options::get_option( 'redirect-switch' ) ),
			'REDIRECT_URL'     => esc_js( Options::get_option( 'redirect-url' ) ),
			'ENABLE_MOUSEOVER' => esc_js( Options::get_option( 'enable-mouseover' ) ),
			'TOOLTIP_TRIGGER'  => esc_js( Options::get_option( 'tooltip-trigger' ) ),
			'MAX_POPUP_SIZE'   => esc_js( Options::get_option( 'max-popup-size' ) ),
			// 'BID'              => intval( $BID ),
			'link_target'      => esc_js( Options::get_option( 'link-target' ) ),
			'WAIT'             => Language::get( 'Please Wait...' ),
			'UPLOADING'        => Language::get( 'Uploading...' ),
			'SAVING'           => Language::get( 'Saving...' ),
			'COMPLETING'       => Language::get( 'Completing...' ),
			'CONFIRMING'       => Language::get( 'Confirming...' ),
		];
	}

	public static function get_select_data( ?int $banner_id = null ): array {

		global $f2, $wpdb;
		$BID = $banner_id && $banner_id > 0 ? $f2->bid( $banner_id ) : $f2->bid();

		if ( ! is_numeric( $BID ) ) {
			die();
		}

		$banner_data = load_banner_constants( $BID );

		$table_name = MDS_DB_PREFIX . 'orders';
		$user_id    = get_current_user_id();

		// Only use orders with 'new' status and order_in_progress = 'Y' to ensure clean slate
		$sql = $wpdb->prepare(
			"SELECT * FROM $table_name WHERE user_id = %d AND status = 'new' AND order_in_progress = 'Y' ORDER BY order_id DESC LIMIT 1",
			$user_id
		);
		$order_result = $wpdb->get_row( $sql );
		$order_row    = $order_result ? (array) $order_result : [];
		$order_id     = isset( $order_row['order_id'] ) ? intval( $order_row['order_id'] ) : 0;

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
			$pos = Blocks::get_block_position( $block_id, $BID );

			return [
				'block_id' => $block_id,
				'x'        => $pos['x'],
				'y'        => $pos['y'],
			];
		}, $block_ids );

		return [
			'NONCE'                => wp_create_nonce( 'mds-select' ),
			'mds_nonce'            => wp_create_nonce( 'mds_nonce' ),
			'ajaxurl'              => esc_url( admin_url( 'admin-ajax.php' ) ),
			'UPDATE_ORDER'         => esc_url( Utility::get_page_url( 'update-order' ) ),
			'USE_AJAX'             => Options::get_option( 'use-ajax' ),
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
			'order_id'             => $order_id,
			'BID'                  => intval( $BID ),
			'time'                 => time(),
			'advertiser_max_order' => Language::get( 'Cannot place pixels on order. You have reached the order limit for this grid. Please review your order history under Manage Pixels.' ),
			'not_adjacent'         => Language::get( 'You must select a block adjacent to another one.' ),
			'rectangle_required'   => Language::get( 'You must select blocks forming a rectangle or square.' ),
			'no_blocks_selected'   => Language::get( 'You have no blocks selected.' ),
			'MDS_CORE_URL'         => esc_url( MDS_CORE_URL ),
			'INVERT_PIXELS'        => Options::get_option( 'invert-pixels' ),
			'WAIT'                 => Language::get( 'Please Wait! Reserving Pixels...' ),
			'selection_adjacency_mode' => Options::get_option( 'selection-adjacency-mode', 'ADJACENT' ),
		];
	}

	/**
	 * Register scripts and styles
	 */
	public static function register_scripts( mixed $shortcode_atts = null ): void {
		$shortcode_atts = is_array( $shortcode_atts ) ? $shortcode_atts : null;

		if ( ! wp_style_is( 'mds', 'registered' ) ) {
			wp_register_style( 'mds', MDS_BASE_URL . 'src/Assets/css/mds.css', [], filemtime( MDS_BASE_PATH . 'src/Assets/css/mds.css' ) );
		}

		self::$tooltips = Options::get_option( 'enable-mouseover' );
		if ( self::$tooltips == 'POPUP' ) {
			if ( ! wp_script_is( 'popper', 'registered' ) ) {
				wp_register_script( 'popper', MDS_CORE_URL . 'js/third-party/popper.min.js', [], filemtime( MDS_CORE_PATH . 'js/third-party/popper.min.js' ), true );
			}
			if ( ! wp_script_is( 'tippy', 'registered' ) ) {
				wp_register_script( 'tippy', MDS_CORE_URL . 'js/third-party/tippy-bundle.umd.min.js', [ 'popper' ], filemtime( MDS_CORE_PATH . 'js/third-party/tippy-bundle.umd.min.js' ), true );
			}
			if ( ! wp_style_is( 'tippy-light', 'registered' ) ) {
				wp_register_style( 'tippy-light', MDS_CORE_URL . 'css/tippy/light.css', [], filemtime( MDS_CORE_PATH . 'css/tippy/light.css' ) );
			}
			$deps = [ 'jquery', 'tippy' ];
		} else {
			$deps = [];
		}

		if ( ! wp_script_is( 'image-scale', 'registered' ) ) {
			wp_register_script( 'image-scale', MDS_CORE_URL . 'js/third-party/image-scale.min.js', $deps, filemtime( MDS_CORE_PATH . 'js/third-party/image-scale.min.js' ), true );
		}
		if ( ! wp_script_is( 'image-map', 'registered' ) ) {
			wp_register_script( 'image-map', MDS_CORE_URL . 'js/third-party/image-map.min.js', [ 'image-scale' ], filemtime( MDS_CORE_PATH . 'js/third-party/image-map.min.js' ), true );
		}
		if ( ! wp_script_is( 'contact', 'registered' ) ) {
			wp_register_script( 'contact', MDS_CORE_URL . 'js/third-party/contact.nomodule.min.js', [ 'image-map' ], filemtime( MDS_CORE_PATH . 'js/third-party/contact.nomodule.min.js' ), true );
		}

		if ( ! wp_script_is( 'mds', 'registered' ) ) {
			wp_register_script( 'mds', MDS_BASE_URL . 'src/Assets/js/mds.min.js', [
				'jquery',
				'contact',
				'image-scale',
				'image-map',
				'wp-hooks'
			], filemtime( MDS_BASE_PATH . 'src/Assets/js/mds.min.js' ), true );
			wp_localize_script( 'mds', 'MDS', self::get_script_data() );
		}

		self::register_order_script( $shortcode_atts );
	}

	/**
	 * Enqueue scripts and styles
	 */
	public static function enqueue_scripts( mixed $shortcode_atts = null ): void {
		$shortcode_atts = is_array( $shortcode_atts ) ? $shortcode_atts : null;

		// Ensure scripts are registered (for admin pages)
		self::register_scripts( $shortcode_atts );
		wp_enqueue_script( 'wp-hooks' );
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-accordion' );

		wp_enqueue_style( 'mds' );

		self::$tooltips = Options::get_option( 'enable-mouseover' );
		if ( self::$tooltips == 'POPUP' ) {
			wp_enqueue_script( 'popper' );
			wp_enqueue_script( 'tippy' );
			wp_enqueue_style( 'tippy-light' );
		}

		wp_enqueue_script( 'image-scale' );
		wp_enqueue_script( 'image-map' );
		wp_enqueue_script( 'contact' );

		wp_enqueue_script( 'mds' );

		$order_handle = self::get_order_script_handle( $shortcode_atts );
		if ( $order_handle !== null ) {
			wp_enqueue_script( $order_handle );
		}
	}

	private static function register_order_script( ?array $shortcode_atts = null ): ?string {
		$order_handle = self::get_order_script_handle( $shortcode_atts );
		if ( $order_handle === null ) {
			return null;
		}

		$script      = str_replace( 'mds-', '', $order_handle );
		$context     = self::get_order_context( $shortcode_atts );
		$banner_id   = $context['banner_id'];
		$script_url  = MDS_CORE_URL . 'js/' . $script . '.min.js';
		$script_path = MDS_CORE_PATH . 'js/' . $script . '.min.js';

		if ( ! wp_script_is( $order_handle, 'registered' ) ) {
			wp_register_script( $order_handle, $script_url, [
				'jquery',
				'mds',
				'contact'
			], filemtime( $script_path ), true );
		}

		$localization_key = $banner_id === null ? 'default' : (string) $banner_id;
		if ( ( self::$localized_order_scripts[ $order_handle ] ?? null ) !== $localization_key ) {
			$data = $script === 'select'
				? self::get_select_data( $banner_id )
				: Orders::get_order_data( $banner_id );
			wp_localize_script( $order_handle, 'MDS_OBJECT', $data );
			self::$localized_order_scripts[ $order_handle ] = $localization_key;
		}

		return $order_handle;
	}

	private static function get_order_script_handle( ?array $shortcode_atts = null ): ?string {
		if ( ! is_user_logged_in() ) {
			return null;
		}

		$context = self::get_order_context( $shortcode_atts );
		if ( ! $context['is_order'] ) {
			return null;
		}

		return match ( Options::get_option( 'use-ajax' ) ) {
			'YES' => 'mds-select',
			'SIMPLE' => 'mds-order',
			default => null,
		};
	}

	private static function get_order_context( ?array $shortcode_atts = null ): array {
		$shortcode_context = self::get_order_context_from_atts( $shortcode_atts );
		if ( $shortcode_context['is_order'] ) {
			return $shortcode_context;
		}

		if ( self::is_order_endpoint() ) {
			return [
				'is_order'  => true,
				'banner_id' => null,
			];
		}

		foreach ( self::get_current_post_ids() as $post_id ) {
			$post_context = self::get_order_context_from_post( $post_id );
			if ( $post_context['is_order'] ) {
				return $post_context;
			}
		}

		return [
			'is_order'  => false,
			'banner_id' => null,
		];
	}

	private static function get_order_context_from_atts( ?array $atts ): array {
		if ( ! is_array( $atts ) ) {
			return [
				'is_order'  => false,
				'banner_id' => null,
			];
		}

		$page_type = self::get_context_page_type( $atts );
		if ( $page_type !== 'order' ) {
			return [
				'is_order'  => false,
				'banner_id' => null,
			];
		}

		return [
			'is_order'  => true,
			'banner_id' => self::get_context_banner_id( $atts ),
		];
	}

	private static function get_order_context_from_post( int $post_id ): array {
		if ( $post_id <= 0 ) {
			return [
				'is_order'  => false,
				'banner_id' => null,
			];
		}

		$legacy_order_page_id = intval( Options::get_option( 'users-order-page' ) );
		if ( $legacy_order_page_id > 0 && $post_id === $legacy_order_page_id ) {
			return [
				'is_order'  => true,
				'banner_id' => null,
			];
		}

		if ( function_exists( 'get_post_meta' ) ) {
			$page_type = self::normalize_page_type( get_post_meta( $post_id, '_mds_page_type', true ) );
			if ( $page_type === 'order' ) {
				return [
					'is_order'  => true,
					'banner_id' => null,
				];
			}
		}

		$metadata_manager = MDSPageMetadataManager::getInstanceSafely();
		if ( $metadata_manager !== null ) {
			try {
				$metadata = $metadata_manager->getMetadata( $post_id );
				if ( $metadata && isset( $metadata->page_type ) && self::normalize_page_type( $metadata->page_type ) === 'order' ) {
					return [
						'is_order'  => true,
						'banner_id' => null,
					];
				}
			} catch ( \Throwable ) {
				// Metadata lookup failures must not prevent frontend asset loading.
			}
		}

		$post = self::get_post_for_id( $post_id );
		if ( $post && isset( $post->post_content ) ) {
			return self::get_order_context_from_content( (string) $post->post_content );
		}

		return [
			'is_order'  => false,
			'banner_id' => null,
		];
	}

	private static function get_order_context_from_content( string $content ): array {
		if ( $content === '' ) {
			return [
				'is_order'  => false,
				'banner_id' => null,
			];
		}

		if ( preg_match_all( '/\[(milliondollarscript|mds)\b([^\]]*)\]/i', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$atts = self::parse_shortcode_atts( $match[2] ?? '' );
				$context = self::get_order_context_from_atts( $atts );
				if ( $context['is_order'] ) {
					return $context;
				}
			}
		}

		$block_context = self::get_order_context_from_blocks( $content );
		if ( $block_context['is_order'] ) {
			return $block_context;
		}

		return [
			'is_order'  => false,
			'banner_id' => null,
		];
	}

	private static function get_order_context_from_blocks( string $content ): array {
		if ( function_exists( 'parse_blocks' ) ) {
			$context = self::find_order_block_context( parse_blocks( $content ) );
			if ( $context['is_order'] ) {
				return $context;
			}
		}

		if ( preg_match( '/"milliondollarscript_type"\s*:\s*"order"/i', $content ) || preg_match( '/<!--\s+wp:milliondollarscript\/order(?:-block)?\b/i', $content ) ) {
			$banner_id = null;
			if ( preg_match( '/"milliondollarscript_id"\s*:\s*"?(\d+)"?/i', $content, $matches ) ) {
				$banner_id = intval( $matches[1] );
			}

			return [
				'is_order'  => true,
				'banner_id' => $banner_id > 0 ? $banner_id : null,
			];
		}

		return [
			'is_order'  => false,
			'banner_id' => null,
		];
	}

	private static function find_order_block_context( array $blocks ): array {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$block_context = self::get_order_context_from_block( $block );
			if ( $block_context['is_order'] ) {
				return $block_context;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$inner_context = self::find_order_block_context( $block['innerBlocks'] );
				if ( $inner_context['is_order'] ) {
					return $inner_context;
				}
			}
		}

		return [
			'is_order'  => false,
			'banner_id' => null,
		];
	}

	private static function get_order_context_from_block( array $block ): array {
		$block_name    = (string) ( $block['blockName'] ?? '' );
		$attrs         = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [];
		$context_attrs = is_array( $attrs['data'] ?? null ) ? $attrs['data'] : $attrs;
		$page_type     = null;

		if ( $block_name === 'carbon-fields/million-dollar-script' ) {
			$page_type = self::get_context_page_type( $context_attrs );
		} elseif ( preg_match( '/milliondollarscript\/(.+?)(?:-block)?$/', $block_name, $matches ) ) {
			$page_type = self::normalize_page_type( $matches[1] );
		}

		if ( $page_type === null ) {
			$page_type = self::get_context_page_type( $attrs );
		}

		if ( $page_type !== 'order' ) {
			return [
				'is_order'  => false,
				'banner_id' => null,
			];
		}

		return [
			'is_order'  => true,
			'banner_id' => self::get_context_banner_id( $context_attrs ),
		];
	}

	private static function get_current_post_ids(): array {
		$post_ids = [];

		if ( function_exists( 'get_queried_object_id' ) ) {
			$post_ids[] = intval( get_queried_object_id() );
		}

		global $post;
		if ( isset( $post->ID ) ) {
			$post_ids[] = intval( $post->ID );
		}

		return array_values( array_unique( array_filter( $post_ids ) ) );
	}

	private static function get_post_for_id( int $post_id ): ?object {
		global $post;
		if ( isset( $post->ID ) && intval( $post->ID ) === $post_id ) {
			return $post;
		}

		if ( function_exists( 'get_post' ) ) {
			$post_object = get_post( $post_id );
			if ( is_object( $post_object ) ) {
				return $post_object;
			}
		}

		return null;
	}

	private static function is_order_endpoint(): bool {
		$endpoint = Options::get_option( 'endpoint', 'milliondollarscript' );
		$page     = null;

		if ( function_exists( 'get_query_var' ) ) {
			$page = get_query_var( $endpoint );
		}

		if ( empty( $page ) ) {
			global $wp_query;
			if ( isset( $wp_query->query_vars[ $endpoint ] ) ) {
				$page = $wp_query->query_vars[ $endpoint ];
			}
		}

		return self::normalize_page_type( $page ) === 'order';
	}

	private static function get_context_page_type( array $context ): ?string {
		foreach ( [ 'type', 'page', 'display', 'mds_type', 'milliondollarscript_type' ] as $key ) {
			if ( isset( $context[ $key ] ) && $context[ $key ] !== '' ) {
				return self::normalize_page_type( $context[ $key ] );
			}
		}

		return null;
	}

	private static function get_context_banner_id( array $context ): ?int {
		foreach ( [ 'id', 'BID', 'bid', 'milliondollarscript_id' ] as $key ) {
			if ( isset( $context[ $key ] ) && is_numeric( $context[ $key ] ) && intval( $context[ $key ] ) > 0 ) {
				return intval( $context[ $key ] );
			}
		}

		return null;
	}

	private static function normalize_page_type( mixed $value ): string {
		$value = strtolower( trim( (string) $value ) );

		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?: '';
	}

	private static function parse_shortcode_atts( string $text ): array {
		if ( function_exists( 'shortcode_parse_atts' ) ) {
			$atts = shortcode_parse_atts( $text );
			if ( is_array( $atts ) ) {
				return $atts;
			}
		}

		$atts = [];
		if ( preg_match_all( '/([\w-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+))/', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$atts[ $match[1] ] = $match[2] !== '' ? $match[2] : ( $match[3] !== '' ? $match[3] : $match[4] );
			}
		}

		return $atts;
	}

	public static function sanitize_array( $value ): array|string {
		$allowed_html = array(
			'comma' => array()
		);

		$value           = str_replace( ',', '<comma>', $value );
		$sanitized_value = wp_kses( $value, $allowed_html );

		return str_replace( '<comma>', ',', $sanitized_value );
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

	/**
	 * Manage pixel.
	 *
	 * @param int $ad_id
	 * @param array|WP_Post|null $mds_pixel
	 * @param array|null $banner_data
	 * @param float|int|string $BID
	 * @param string $gif_support
	 * @param string $jpeg_support
	 * @param string $png_support
	 *
	 * @return bool
	 */
	public static function manage_pixel( int $ad_id, array|WP_Post|null $mds_pixel, ?array $banner_data, float|int|string $BID, string $gif_support, string $jpeg_support, string $png_support ): bool {

		if ( empty( $mds_pixel ) ) {
			// Make sure the mds-pixel exists.
			$mds_pixel = get_post( $ad_id );
		}

		if ( empty( $mds_pixel ) ) {
			global $mds_error;
			$mds_error = Language::get( 'Unable to find pixels.' );
			return false;
		}

		global $wpdb;

		$user_id      = get_current_user_id();
		$sql          = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE ad_id=%d AND user_id=%d";
		$prepared_sql = $wpdb->prepare( $sql, $ad_id, $user_id );
		$result       = $wpdb->get_results( $prepared_sql, ARRAY_A );

		if ( ! empty( $result ) ) {
			$row = $result[0];
		} else {
			global $mds_error;
			$mds_error = Language::get( 'Unable to find pixels.' );
			return false;
		}

		$order_id = $row['order_id'];
		if ( /*! empty( $_REQUEST['change_pixels'] ) && */
		Options::get_option( 'order-locking', 'no' ) == 'yes' ) {
			// Order locking is enabled so check if the order is approved or completed before allowing the user to save the ad.
			$completion_status = Orders::get_completion_status( $order_id, $user_id );
			if ( ! $completion_status && $row['status'] !== 'denied' ) {
				// User should never get here, so we will just redirect them to the manage page.
				if ( empty( $_REQUEST['json'] ) ) {
					echo '<script>window.location.href="' . esc_url( Utility::get_page_url( 'manage' ) ) . '";</script>';
					wp_die();
				} else {
					Utility::redirect( Utility::get_page_url( 'manage' ) );
				}
			}
		}

		$blocks = explode( ',', $row['blocks'] );

		$size          = Utility::get_pixel_image_size( $row['order_id'] );
		$pixels        = $size['x'] * $size['y'];

		// --- Only process upload if the form was submitted with a file ---
		if ( ! empty( $_REQUEST['change_pixels'] ) && ! empty( $_FILES['pixels']['tmp_name'] ) ) {
			$upload_result = upload_changed_pixels( $order_id, $row['banner_id'], $size, $banner_data );

			// Check if upload succeeded (true) or returned an error string
			if ( $upload_result !== true ) {
				global $mds_error;
				// Assign the returned error string to the global variable for display
				$mds_error = $upload_result;
				return false;
			}
			// If upload was successful (and form submitted)
			else {
				// The image was uploaded successfully.
				if ( $row['status'] == 'denied' ) {
					Orders::pend_order( $order_id );
					Orders::reset_order_progress();
				}
			}
		} // --- End upload processing ---

		// If not uploading a new image, check if the order is valid.
		if ( empty( $_REQUEST['change_pixels'] ) ) {
			$order_id      = Orders::get_order_id_from_ad_id( $ad_id );
			$sql           = $wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE status IN ('confirmed', 'new', 'deleted') AND order_id=%d;",
				$order_id
			);
			$check_results = $wpdb->get_results( $sql, ARRAY_A );
			if ( ! empty( $check_results ) ) {
				// The order is not valid so don't display it.
				Language::out( 'Sorry, you are not allowed to access this page.' );

				return false;
			}
			unset( $sql, $check_results );
		}

		if ( isset( $_POST['mds_dest'] ) ) {
			return true;
		}

		// Get current theme mode and apply theme classes for the pixel editing interface
		$theme_mode = Options::get_option( 'theme_mode', 'light' );
		$logged_in = is_user_logged_in() ? ' logged-in' : '';
		$theme_classes = ' mds-theme-' . $theme_mode . ' mds-theme-active';

		// Ad forms:
		?>
		<div class="mds-container<?php echo $logged_in . $theme_classes; ?>">
			<div class="outer">
				<div class="inner">
        		<div class="fancy-heading"><?php Language::out( 'Edit your Ad / Change your pixels' ); ?></div>
		<?php
		// Display the global error message if it's set
		global $mds_error;
		if ( ! empty( $mds_error ) ) {
			// Use WordPress admin notice styling
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $mds_error ) . '</p></div>';
		}
		Language::out( '<p>Here you can edit your ad or change your pixels.</p>' );
		Language::out( '<p><b>Your Pixels:</b></p>' );
		?>
        <div class="mds-pixel-info-container">
			<div class="mds-pixel-info-block mds-pixel-info-image">
				<b><?php Language::out( 'Pixels' ); ?></b><br/>
				<img src="<?php echo esc_url( Utility::get_page_url( 'get-order-image', [ 'BID' => $BID, 'aid' => $ad_id ] ) ); ?>" alt="">
			</div>
			<div class="mds-pixel-info-block mds-pixel-info-details">
				<b><?php Language::out( 'Pixel Info' ); ?></b><br>
				<?php
				Language::out_replace(
					'%PIXEL_COUNT% pixels<br>(%SIZE_X% wide,  %SIZE_Y% high)',
					[ '%SIZE_X%', '%SIZE_Y%', '%PIXEL_COUNT%' ],
					[ $size['x'], $size['y'], $pixels ]
				);
				?><br>
			</div>
			<div class="mds-pixel-info-block mds-pixel-info-upload">
				<b><?php Language::out( 'Change Pixels' ); ?></b><br>
				<?php
				if(Options::get_option('resize', 'YES') == 'YES') {
					Language::out_replace(
						'To change these pixels, select an image and click "upload". It will be resized to fit %SIZE_X% pixels wide & %SIZE_Y% pixels high.',
						[ '%SIZE_X%', '%SIZE_Y%' ],
						[ $size['x'], $size['y'] ]
					);
				} else {
					Language::out_replace(
						'To change these pixels, select an image up to %SIZE_X% pixels wide & %SIZE_Y% pixels high and click "upload".',
						[ '%SIZE_X%', '%SIZE_Y%' ],
						[ $size['x'], $size['y'] ]
					);
				}
				?>
				<form name="change" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					enctype="multipart/form-data" method="post">
					<?php wp_nonce_field( 'mds-form' ); ?>
					<input type="hidden" name="action" value="mds_form_submission">
					<input type="hidden" name="mds_dest" value="manage">
					<input type="file" name='pixels'><br>
					<input type="hidden" name="aid" value="<?php echo $ad_id; ?>">
					<input type="submit" name="change_pixels" class="mds-upload-submit"
						value="<?php echo esc_attr( Language::get( 'Upload' ) ); ?>">
				</form>
				<?php Language::out( 'Supported formats:' ); ?><?php echo "$gif_support $jpeg_support $png_support"; ?>
			</div>
		</div>

        <p><b><?php Language::out( 'Edit Your Ad:' ); ?></b></p>
		<?php

		global $prams;

		// Get the desired MDS Pixels post owned by the current user
		$pixels = get_posts( [
			'post_type'   => FormFields::$post_type,
			'post_status' => 'any',
			'p'           => $ad_id,
			'author'      => $user_id,
		] );

		if ( ! empty( $pixels ) ) {
			$ad_id    = $pixels[0]->ID;
			$order_id = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'order' );
		}

		if ( empty( $ad_id ) ) {
			$ad_id = insert_ad_data( $order_id );
		}

		if ( ! empty( $_REQUEST['save'] ) ) {
			Functions::verify_nonce( 'mds_form' );

			$prams = load_ad_values( $ad_id );
			?>
            <div class='ok_msg_label'><?php Language::out( 'Ad Saved' ); ?></div>
			<?php

			$mode = "user";

			display_ad_form( 1, $mode, $prams );

			// disapprove the pixels because the ad was modified..

			if ( $banner_data['AUTO_APPROVE'] != 'Y' ) { // to be approved by the admin
				disapprove_modified_order( $prams['order_id'], $BID );
			}

			if ( $banner_data['AUTO_PUBLISH'] == 'Y' ) {
				process_image( $BID );
				publish_image( $BID );
				process_map( $BID );
			}

			// send pixel change notification
			if ( Options::get_option( 'email-admin-publish-notify' ) == 'YES' ) {
				Emails::send_published_pixels_notification( $user_id, $prams['order_id'] );
			}
		} else {
			$prams = load_ad_values( $ad_id );
			display_ad_form( 1, 'user', $prams );
		}
		?>
				</div>
			</div>
		</div>
		<?php

		require_once MDS_CORE_PATH . "html/footer.php";

		return true;
	}

	/**
	 * If width or height weren't set on the shortcode then get them from the database.
	 *
	 * @param array $atts
	 *
	 * @return array|void
	 */
	public static function maybe_set_dimensions( array $atts ) {
		if ( $atts['width'] == 0 || $atts['height'] == 0 ) {
			global $wpdb;
			$table_name = MDS_DB_PREFIX . 'banners';
			$sql        = $wpdb->prepare( "SELECT grid_width, block_width, grid_height, block_height FROM `$table_name` WHERE `banner_id` = %d", intval( $atts['id'] ) );
			$row        = $wpdb->get_row( $sql, ARRAY_A );
			if ( empty( $row ) ) {
				die( 'Error retrieving data from the database.' );
			}

			if ( $atts['width'] == 0 ) {
				$grid_width    = $row['grid_width'];
				$block_width   = $row['block_width'];
				$pixel_width   = ( $grid_width * $block_width ) . 'px';
				$atts['width'] = $pixel_width;
			}
			if ( $atts['height'] == 0 ) {
				$grid_height    = $row['grid_height'];
				$block_height   = $row['block_height'];
				$pixel_height   = ( $grid_height * $block_height ) . 'px';
				$atts['height'] = $pixel_height;
			}
		}

		return $atts;
	}

	/**
	 * Transliterates Cyrillic text to Latin.
	 *
	 * @param string $text The Cyrillic text.
	 *
	 * @return string The transliterated Latin text.
	 */
	public static function transliterate_cyrillic_to_latin( string $text ): string {
		$cyr = [
			'а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п',
			'р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я',
			'А','Б','В','Г','Д','Е','Ё','Ж','З','И','Й','К','Л','М','Н','О','П',
			'Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я'
		];
		$lat = [
			'a','b','v','g','d','e','io','zh','z','i','y','k','l','m','n','o','p',
			'r','s','t','u','f','h','ts','ch','sh','sht','a','i','y','e','yu','ya',
			'A','B','V','G','D','E','Io','Zh','Z','I','Y','K','L','M','N','O','P',
			'R','S','T','U','F','H','Ts','Ch','Sh','Sht','A','I','Y','e','Yu','Ya'
		];
		return str_replace( $cyr, $lat, $text );
	}

	function mds_allowed_mds_params( $allowed_tags ) {
		$allowed_tags['div']['data-mds-params'] = true;

		return $allowed_tags;
	}
}
