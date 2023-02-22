<?php

/**
 * Million Dollar Script Two
 *
 * @version 2.3.6
 * @author Ryan Rhode
 * @copyright (C) 2022, Ryan Rhode
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
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
 */

namespace MillionDollarScript\Classes;

defined( 'ABSPATH' ) or exit;

class Functions {

	/**
	 * Enqueue scripts and styles
	 */
	public static function enqueue_scripts(): void {
		require_once MDS_CORE_PATH . 'include/init.php';

		global $f2;
		$BID         = $f2->bid();
		$banner_data = load_banner_constants( $BID );

		$data = [
			'ajaxurl'          => admin_url( 'admin-ajax.php' ),
			'mds_core_nonce'   => wp_create_nonce( 'mds_core_nonce' ),
			'mds_nonce'        => wp_create_nonce( 'mds_nonce' ),
			'users'            => Options::get_option( 'users', false, 'options', 'no' ),
			'wp'               => WP_URL,
			'winWidth'         => intval( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ),
			'winHeight'        => intval( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ),
			'time'             => time(),
			'BASE_HTTP_PATH'   => BASE_HTTP_PATH,
			'REDIRECT_SWITCH'  => REDIRECT_SWITCH,
			'REDIRECT_URL'     => REDIRECT_URL,
			'ENABLE_MOUSEOVER' => ENABLE_MOUSEOVER,
			'TOOLTIP_TRIGGER'  => TOOLTIP_TRIGGER,
			'BID'              => intval( $BID ),
		];

		wp_enqueue_style( 'mds', MDS_BASE_URL . 'src/Assets/css/mds.css', [], filemtime( MDS_BASE_PATH . 'src/Assets/css/mds.css' ) );
		wp_enqueue_style( 'tippy-light', BASE_HTTP_PATH . 'css/tippy/light.css', [], filemtime( BASE_PATH . '/css/tippy/light.css' ) );
		wp_enqueue_style( 'mds-main', BASE_HTTP_PATH . 'css/main.css', [], filemtime( BASE_PATH . '/css/main.css' ) );

		$tooltips = \MillionDollarScript\Classes\Config::get( 'ENABLE_MOUSEOVER' );
		if ( $tooltips == 'POPUP' ) {
			wp_enqueue_script( 'popper', BASE_HTTP_PATH . 'js/third-party/popper.js', [], filemtime( BASE_PATH . '/js/third-party/popper.js' ), true );
			wp_enqueue_script( 'tippy', BASE_HTTP_PATH . 'js/third-party/tippy-bundle.umd.js', [ 'popper' ], filemtime( BASE_PATH . '/js/third-party/tippy-bundle.umd.js' ), true );
			$deps = 'tippy';
		} else {
			$deps = '';
		}

		wp_enqueue_script( 'image-scale', BASE_HTTP_PATH . 'js/third-party/image-scale.min.js', [ $deps ], filemtime( BASE_PATH . '/js/third-party/image-scale.min.js' ), true );
		wp_enqueue_script( 'image-map', BASE_HTTP_PATH . 'js/third-party/image-map.js', [ 'image-scale' ], filemtime( BASE_PATH . '/js/third-party/image-map.js' ), true );
		wp_enqueue_script( 'contact', BASE_HTTP_PATH . 'js/third-party/contact.min.js', [ 'image-map' ], filemtime( BASE_PATH . '/js/third-party/contact.min.js' ), true );

		wp_register_script( 'mds-core', BASE_HTTP_PATH . 'js/mds.js', [ 'contact' ], filemtime( BASE_PATH . '/js/mds.js' ), true );
		wp_localize_script( 'mds-core', 'MDS', $data );
		wp_enqueue_script( 'mds-core' );

		wp_register_script( 'mds', MDS_BASE_URL . 'src/Assets/js/mds.js', [ 'jquery', 'mds-core' ], filemtime( MDS_BASE_PATH . 'src/Assets/js/mds.js' ), true );
		wp_enqueue_script( 'mds' );
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
	 * Get attribute and grid data for all grids or a single grid if a grid id is provided. Used for updating.
	 *
	 * @param int|null $grid_id
	 *
	 * @return array
	 */
	public static function get_update_attribute_data( int $grid_id = null ): array {

		global $wpdb;

		if ( $grid_id == null ) {
			$grids = $wpdb->get_results( "SELECT `banner_id`, `price_per_block` FROM `" . MDS_DB_PREFIX . "banners` ORDER BY `banner_id` ASC" );
		} else {
			$grids = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `banner_id`, `price_per_block` FROM `" . MDS_DB_PREFIX . "banners` WHERE `banner_id` = %d ORDER BY `banner_id` ASC",
					$grid_id
				)
			);
		}

		return $grids;
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
	 * Create a product for MDS to use.
	 */
	public static function create_product(): \WC_Product_Variable {

		$product = new \WC_Product_Variable();
		$product->set_name( __( 'Pixels', 'milliondollarscript' ) );
		$product->add_meta_data( '_milliondollarscript', 'yes' );

		self::add_grid_attributes( $product );

		return $product;
	}

	/**
	 * Used to update the product in the database upgrade.
	 *
	 * @return \WC_Product|null
	 */
	public static function migrate_product(): null|\WC_Product {
		global $wp_query;

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
					error_log( "Product with id " . $product_id . " wasn't found from wc_get_product function." );

					return null;
				}

				if ( $product->is_type( 'simple' ) ) {
					wp_remove_object_terms( $product_id, 'simple', 'product_type' );
					wp_set_object_terms( $product_id, 'variable', 'product_type', true );
				}

				self::get_attribute_data();
				self::add_grid_attributes( $product );
			}
			wp_reset_postdata();
		}

		return $product;
	}

	/**
	 * Get a product id. Checks for an existing MDS product first and if one doesn't exist creates one.
	 *
	 * @return bool|int
	 */
	public static function get_product_id(): bool|int {
		global $wp_query;

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
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'EXTERNAL_ENABLED', 'val' => 'N' ] );

		// Enable WooCommerce module
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_ENABLED', 'val' => 'Y' ] );

		// $product_id = self::get_product_id();

		$checkout_url = wc_get_checkout_url() . '?add-to-cart=%VARIATION%&quantity=%QUANTITY%';
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_URL', 'val' => $checkout_url ] );

		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_AUTO_APPROVE', 'val' => 'yes' ] );
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_BUTTON_TEXT', 'val' => '' ] );
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_BUTTON_IMAGE', 'val' => '' ] );
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_REDIRECT', 'val' => 'yes' ] );
	}

	/**
	 * Disable WooCommerce payment module.
	 *
	 * @return void
	 */
	public static function disable_woocommerce_payments(): void {
		global $wpdb;
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_ENABLED', 'val' => 'N' ] );
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
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_AUTO_APPROVE', 'val' => $val ] );
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
	 * Get product from options.
	 *
	 * @return false|\WC_Product_Variable|null
	 */
	public static function get_product() {
		// Get product from CarbonFields
		$product_option = Options::get_option( 'product', true );

		// Product id
		$product_id = $product_option[0]['id'];

		// WC Product
		return wc_get_product( $product_id );
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
