<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.4
 * @author      Ryan Rhode
 * @copyright   (C) 2023, Ryan Rhode
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
			'link_target'      => esc_js( \MillionDollarScript\Classes\Options::get_option( 'link-target' ) )
		];
	}

	public static function get_select_data(): array {

		global $f2;
		$BID = $f2->bid();

		if ( ! is_numeric( $BID ) ) {
			die();
		}

		$banner_data = load_banner_constants( $BID );

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE user_id='" . get_current_user_id() . "' AND status='new'";
		$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		$order_row = mysqli_fetch_array( $order_result );

		// load any existing blocks for this order
		$order_row_blocks = ! empty( $order_row['blocks'] ) ? $order_row['blocks'] : '';
		$block_ids        = $order_row_blocks !== '' ? array_map( 'intval', explode( ',', $order_row_blocks ) ) : [];
		$block_str        = $order_row_blocks !== '' ? implode( ',', $block_ids ) : "";
		$order_blocks     = array_map( function ( $block_id ) use ( $BID ) {
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

		$sql          = $wpdb->prepare(
			"SELECT * FROM $table_name WHERE user_id = %d AND status = %s",
			$user_id,
			$status
		);
		$order_result = $wpdb->get_row( $sql );
		$order_row    = $order_result ? (array) $order_result : [];

		// load any existing blocks for this order
		$order_row_blocks = ! empty( $order_row['blocks'] ) ? $order_row['blocks'] : '';
		$block_ids        = $order_row_blocks !== '' ? array_map( 'intval', explode( ',', $order_row_blocks ) ) : [];
		$block_str        = $order_row_blocks !== '' ? implode( ',', $block_ids ) : "";
		$order_blocks     = array_map( function ( $block_id ) use ( $BID ) {
			$pos = get_block_position( $block_id, $BID );

			return [
				'block_id' => $block_id,
				'x'        => $pos['x'],
				'y'        => $pos['y'],
			];
		}, $block_ids );

		$low_x = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];
		$low_y = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];

		$sql    = $wpdb->prepare( "SELECT block_info FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%s", get_current_order_id() );
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
		wp_register_style( 'tippy-light', MDS_CORE_URL . 'css/tippy/light.css', [], filemtime( MDS_CORE_PATH . 'css/tippy/light.css' ) );

		self::$tooltips = Config::get( 'ENABLE_MOUSEOVER' );
		if ( self::$tooltips == 'POPUP' ) {
			wp_register_script( 'popper', MDS_CORE_URL . 'js/third-party/popper.min.js', [], filemtime( MDS_CORE_PATH . 'js/third-party/popper.min.js' ), true );
			wp_register_script( 'tippy', MDS_CORE_URL . 'js/third-party/tippy-bundle.umd.min.js', [ 'popper' ], filemtime( MDS_CORE_PATH . 'js/third-party/tippy-bundle.umd.min.js' ), true );
			$deps = 'tippy';
		} else {
			$deps = '';
		}

		wp_register_script( 'image-scale', MDS_CORE_URL . 'js/third-party/image-scale.min.js', [ 'jquery', $deps ], filemtime( MDS_CORE_PATH . 'js/third-party/image-scale.min.js' ), true );
		wp_register_script( 'image-map', MDS_CORE_URL . 'js/third-party/image-map.min.js', [ 'image-scale' ], filemtime( MDS_CORE_PATH . 'js/third-party/image-map.min.js' ), true );
		wp_register_script( 'contact', MDS_CORE_URL . 'js/third-party/contact.nomodule.min.js', [ 'image-map' ], filemtime( MDS_CORE_PATH . 'js/third-party/contact.nomodule.min.js' ), true );

		wp_register_script( 'mds', MDS_BASE_URL . 'src/Assets/js/mds.min.js', [ 'jquery', 'contact', 'image-scale', 'image-map' ], filemtime( MDS_BASE_PATH . 'src/Assets/js/mds.min.js' ), true );
		wp_add_inline_script( 'mds', 'const MDS = ' . json_encode( self::get_script_data() ), 'before' );

		$order_script  = "";
		$data_function = "";

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
			$register_script = false;

			global $post;
			if ( isset( $post ) && $post->ID == Options::get_option( 'users-order-page' ) ) {
				$register_script = true;
			} else {
				global $wp_query;
				$MDS_ENDPOINT = Options::get_option( 'endpoint', 'milliondollarscript' );
				if ( isset( $wp_query->query_vars[ $MDS_ENDPOINT ] ) ) {
					$page = $wp_query->query_vars[ $MDS_ENDPOINT ];
					if ( $page == 'order' ) {
						$register_script = true;
					}
				}
			}

			if ( $register_script ) {
				wp_register_script( 'mds-' . $order_script, MDS_CORE_URL . 'js/' . $order_script . '.min.js', [ 'jquery', 'mds', 'contact' ], filemtime( MDS_CORE_PATH . 'js/' . $order_script . '.min.js' ), true );
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
		wp_enqueue_style( 'tippy-light' );

		self::$tooltips = Config::get( 'ENABLE_MOUSEOVER' );
		if ( self::$tooltips == 'POPUP' ) {
			wp_enqueue_script( 'popper' );
			wp_enqueue_script( 'tippy' );
		}

		wp_enqueue_script( 'image-scale' );
		wp_enqueue_script( 'image-map' );
		wp_enqueue_script( 'contact' );

		wp_enqueue_script( 'mds-core' );

		wp_enqueue_script( 'mds' );
		wp_enqueue_script( 'mds-select' );
		wp_enqueue_script( 'mds-order' );
	}

	/**
	 * Update product attributes and variations.
	 *
	 * @param \WC_Product_Variable|\WC_Product $product
	 *
	 * @return void
	 */
	public static function update_attributes( \WC_Product_Variable|\WC_Product $product ): void {
		global $BID;

		// Fetch data
		$grids               = self::get_update_attribute_data();
		$existing_variations = $product->get_children();

		self::update_attribute( $grids, $product );

		// Loop through all grids.
		foreach ( $grids as $grid ) {
			$new_attribute_value = intval( $grid->banner_id );

			$exists       = false;
			$variation    = null;
			$variation_id = 0;

			// Loop through all existing variations (grids on the product).
			foreach ( $existing_variations as $existing_variation_id ) {
				// Check cache
				$cache_key          = 'wc_get_product_' . $existing_variation_id;
				$existing_variation = wp_cache_get( $cache_key );
				if ( false === $existing_variation ) {
					// Store cache
					$existing_variation = \WC()->product_factory->get_product( $existing_variation_id );
					wp_cache_set( $cache_key, $existing_variation );
				}

				// Check cache
				$cache_key              = 'wc_get_attribute_' . $existing_variation_id;
				$exists_attribute_value = wp_cache_get( $cache_key );
				if ( false === $exists_attribute_value ) {
					// Store cache
					$exists_attribute_value = intval( $existing_variation->get_attribute( 'grid' ) );
					wp_cache_set( $cache_key, $exists_attribute_value );
				}

				// If a variation exists for the grid save it for later and break out of the loop.
				if ( $exists_attribute_value == $new_attribute_value ) {
					$exists       = true;
					$variation_id = $existing_variation_id;
					break;
				}
			}
			unset( $existing_variation_id );

			if ( ! $exists ) {
				// If no existing variation was found, make a new one.
				$variation = new \WC_Product_Variation();
				$variation->set_parent_id( $product->get_id() );
				$variation->set_regular_price( $grid->price_per_block );
				$variation->set_virtual( true );
				$variation->set_attributes( [ 'grid' => $new_attribute_value ] );
				$variation->save();
			} else if ( $new_attribute_value == $BID ) {
				// If an existing variation was found, update it with the new data only if it's the one being edited.
				$variation = wc_get_product( $variation_id );
				$variation->set_regular_price( $grid->price_per_block );
				$variation->set_virtual( true );
				$variation->set_attributes( [ 'grid' => $new_attribute_value ] );
				$variation->save();
			}
		}
		unset( $grid );

		// Cleanup
		foreach ( $existing_variations as $existing_variation_id ) {
			wp_cache_delete( 'wc_get_product_' . $existing_variation_id );
			wp_cache_delete( 'wc_get_attribute_' . $existing_variation_id );
		}
	}

	/**
	 * Get attribute and grid data for all grids or a single grid if a grid id is provided. Used for updating.
	 *
	 * @param int|null $grid_id
	 *
	 * @return array
	 */
	public static function get_update_attribute_data( int $grid_id = null ): array {

		global $wpdb;

		if ( $grid_id == null ) {
			$grids = $wpdb->get_results( "SELECT `banner_id`, `price_per_block` FROM `" . MDS_DB_PREFIX . "banners` ORDER BY `banner_id`" );
		} else {
			$grids = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `banner_id`, `price_per_block` FROM `" . MDS_DB_PREFIX . "banners` WHERE `banner_id` = %d ORDER BY `banner_id`",
					$grid_id
				)
			);
		}

		return $grids;
	}

	/**
	 * Get product from options.
	 *
	 * @return false|\WC_Product_Variable|null
	 */
	public static function get_product() {
		// Get product from CarbonFields
		$product_option = Options::get_option( 'product', null, true );

		// Product id
		$product_id = $product_option[0]['id'];

		// WC Product
		return wc_get_product( $product_id );
	}

	/**
	 * Delete a variation from the given product for the given grid.
	 *
	 * @param \WC_Product_Variable|\WC_Product $product
	 * @param int $grid_id
	 *
	 * @return void
	 */
	public static function delete_variation( \WC_Product_Variable|\WC_Product $product, int $grid_id ): void {
		$variations = $product->get_available_variations();

		$cache_key = '';
		foreach ( $variations as $variation ) {
			if ( isset( $variation['attributes']['attribute_grid'] ) && $variation['attributes']['attribute_grid'] == $grid_id ) {

				// Check cache
				$cache_key         = 'wc_get_product_' . $variation['variation_id'];
				$variation_product = wp_cache_get( $cache_key );
				if ( false === $variation_product ) {
					// Store cache
					$variation_product = wc_get_product( $variation['variation_id'] );
					wp_cache_set( $cache_key, $variation_product );
				}

				// Delete it
				$variation_product->delete();
				break;
			}
		}

		$grids = self::get_update_attribute_data();

		self::update_attribute( $grids, $product );

		wp_cache_delete( $cache_key );
	}

	/**
	 * Used to update the product in the database upgrade.
	 *
	 * @return \WC_Product|null
	 */
	public static function migrate_product(): null|\WC_Product {
		$mds_migrate_product_executed = get_option( 'mds_migrate_product_executed' );
		if ( ! $mds_migrate_product_executed ) {
			global $wp_query;
			wp_reset_postdata();

			$product = null;

			$wc_query = new \WP_Query( [
				'posts_per_page' => 1,
				'post_type'      => 'product',
				'meta_key'       => '_milliondollarscript',
				'meta_value'     => 'yes',
				'meta_compare'   => '==',
				'post_status'    => 'publish',
			] );

			if ( $wp_query == null ) {
				$wp_query = $wc_query;
			}

			if ( $wc_query->have_posts() ) {
				while ( $wc_query->have_posts() ) {
					$wc_query->the_post();

					$product_id = get_the_ID();
					$product    = wc_get_product( $product_id );

					if ( $product === false || $product === null ) {
						return null;
					}

					if ( $product->is_type( 'simple' ) ) {
						$product_classname = \WC_Product_Factory::get_product_classname( $product_id, 'variable' );
						$product           = new $product_classname( $product_id );
						$product->save();
					}

					self::get_attribute_data();
					self::add_grid_attributes( $product );
				}
				wp_reset_postdata();
			}

			update_option( 'mds_migrate_product_executed', true );

			return $product;
		}

		return null;
	}

	/**
	 * Get attribute and grid data for all grids or a single grid if a grid id is provided.
	 *
	 * @param int|null $grid_id
	 *
	 * @return array
	 */
	public static function get_attribute_data( int $grid_id = null ): array {

		global $wpdb;

		if ( $grid_id == null ) {
			$grids = $wpdb->get_results( "SELECT `banner_id`, `name`, `price_per_block` FROM `" . MDS_DB_PREFIX . "banners`" );
		} else {
			$grids = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `banner_id`, `name`, `price_per_block` FROM `" . MDS_DB_PREFIX . "banners` WHERE `banner_id` = %d",
					$grid_id
				)
			);
		}

		$options = [];
		foreach ( $grids as $grid ) {
			$options[] = $grid->banner_id;
		}

		$attribute = new \WC_Product_Attribute();
		$attribute->set_id( 0 );
		$attribute->set_name( 'Grid' );
		$attribute->set_position( 0 );
		$attribute->set_visible( 1 );
		$attribute->set_variation( 1 );
		$attribute->set_options( $options );

		return [ $attribute, $grids ];
	}

	/**
	 * Add attribute data to the product.
	 *
	 * @param \WC_Product_Variable|\WC_Product $product
	 */
	public static function add_grid_attributes( \WC_Product_Variable|\WC_Product $product ): void {

		list( $attribute, $grids ) = self::get_attribute_data();

		$product->set_attributes( [ $attribute ] );
		$product->set_reviews_allowed( false );
		$product_id = $product->save();

		foreach ( $grids as $grid ) {

			$variation = new \WC_Product_Variation();
			$variation->set_regular_price( $grid->price_per_block );
			$variation->set_parent_id( $product_id );
			$variation->set_virtual( true );

			$variation->set_attributes( [
				'grid' => $grid->banner_id
			] );

			$variation->save();
		}
	}

	/**
	 * Get a variation id for the given grid id.
	 *
	 * @param $grid_id
	 *
	 * @return int|null
	 */
	public static function get_variation_id( $grid_id ): ?int {
		$product = self::get_product();

		$variations = $product->get_available_variations();

		foreach ( $variations as $variation ) {
			if ( isset( $variation['attributes']['attribute_grid'] ) && $variation['attributes']['attribute_grid'] == $grid_id ) {
				// Get the variation product object
				$variation_product = wc_get_product( $variation['variation_id'] );

				return $variation_product->get_id();
			}
		}

		return null;
	}

	/**
	 * Enable WooCommerce payments module in MDS.
	 *
	 * @return void
	 */
	public static function enable_woocommerce_payments(): void {
		global $wpdb;

		// Disable "Payment" module
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'config_key' => 'EXTERNAL_ENABLED', 'val' => 'N' ] );

		// Enable WooCommerce module
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'config_key' => 'WOOCOMMERCE_ENABLED', 'val' => 'Y' ] );

		// $product_id = self::get_product_id();

		$checkout_url = wc_get_checkout_url() . '?add-to-cart=%VARIATION%&quantity=%QUANTITY%';
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'config_key' => 'WOOCOMMERCE_URL', 'val' => $checkout_url ] );

		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'config_key' => 'WOOCOMMERCE_AUTO_APPROVE', 'val' => 'yes' ] );
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'config_key' => 'WOOCOMMERCE_BUTTON_TEXT', 'val' => '' ] );
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'config_key' => 'WOOCOMMERCE_BUTTON_IMAGE', 'val' => '' ] );
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'config_key' => 'WOOCOMMERCE_REDIRECT', 'val' => 'yes' ] );
	}

	/**
	 * Disable WooCommerce payment module.
	 *
	 * @return void
	 */
	public static function disable_woocommerce_payments(): void {
		global $wpdb;
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'config_key' => 'WOOCOMMERCE_ENABLED', 'val' => 'N' ] );
	}

	/**
	 * Enable auto approve in WooCommerce payment module.
	 *
	 * @param $val
	 *
	 * @return void
	 */
	public static function woocommerce_auto_approve( $val = 'yes' ): void {
		global $wpdb;
		if ( empty( $val ) ) {
			$val = 'no';
		}
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'config_key' => 'WOOCOMMERCE_AUTO_APPROVE', 'val' => $val ] );
	}

	/**
	 * Set the product for the option field.
	 *
	 * @param \Carbon_Fields\Field\Field $field
	 *
	 * @return \Carbon_Fields\Field\Field
	 */
	public static function set_default_product( \Carbon_Fields\Field\Field $field ): \Carbon_Fields\Field\Field {
		$product_id = self::get_product_id();

		$field->set_value( [
			[
				'value'   => 'post:product:' . $product_id,
				'type'    => 'post',
				'subtype' => 'product',
				'id'      => $product_id,
			],
		] );

		return $field;
	}

	/**
	 * Get a product id. Checks for an existing MDS product first and if one doesn't exist creates one.
	 *
	 * @return bool|int
	 */
	public static function get_product_id(): bool|int {
		global $wp_query;
		wp_reset_postdata();

		$product_id = 0;

		$wc_query = new \WP_Query( [
			'posts_per_page' => 1,
			'post_type'      => 'product',
			'meta_key'       => '_milliondollarscript',
			'meta_value'     => 'yes',
			'meta_compare'   => '==',
			'post_status'    => 'publish',
		] );

		if ( $wp_query == null ) {
			$wp_query = $wc_query;
		}

		if ( $wc_query->have_posts() ) {
			// existing product
			while ( $wc_query->have_posts() ) {
				$wc_query->the_post();
				$product_id = get_the_ID();
			}
			wp_reset_postdata();
		} else {
			// create new product
			$product    = self::create_product();
			$product_id = $product->get_id();
		}

		return $product_id;
	}

	/**
	 * Create a product for MDS to use.
	 */
	public static function create_product(): \WC_Product_Variable {

		$product = new \WC_Product_Variable();
		$product->set_name( Language::get( 'Pixels' ) );
		$product->add_meta_data( '_milliondollarscript', 'yes' );

		self::add_grid_attributes( $product );

		return $product;
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

	public static function is_wc_active(): bool {
		return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
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
	 * @param array $grids
	 * @param \WC_Product_Variable|\WC_Product $product
	 *
	 * @return void
	 */
	private static function update_attribute( array $grids, \WC_Product_Variable|\WC_Product $product ): void {
		$options = array_column( $grids, 'banner_id' );

		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'grid' );
		$attribute->set_options( $options );
		$attribute->set_position( 0 );
		$attribute->set_visible( 1 );
		$attribute->set_variation( 1 );
		$attribute->set_id( 0 );

		$product->set_attributes( [ 'grid' => $attribute ] );

		// Save the product.
		$product->save();
	}
}
