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

class WooCommerceFunctions {

	/**
	 * @param array $grids
	 * @param \WC_Product_Variable|\WC_Product $product
	 *
	 * @return void
	 */
	public static function update_attribute( array $grids, \WC_Product_Variable|\WC_Product $product ): void {
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
				$variation->set_downloadable( true );
				$variation->set_attributes( [ 'grid' => $new_attribute_value ] );
				$variation->save();
			} else if ( $new_attribute_value == $BID ) {
				// If an existing variation was found, update it with the new data only if it's the one being edited.
				$variation = wc_get_product( $variation_id );
				$variation->set_regular_price( $grid->price_per_block );
				$variation->set_virtual( true );
				$variation->set_downloadable( true );
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
			$variation->set_downloadable( true );

			$variation->set_attributes( [
				'grid' => $grid->banner_id
			] );

			$variation->save();
		}
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
	 * Create a product for MDS to use.
	 */
	public static function create_product(): \WC_Product_Variable {

		$product = new \WC_Product_Variable();
		$product->set_name( Language::get( 'Pixels' ) );
		$product->add_meta_data( '_milliondollarscript', 'yes' );

		self::add_grid_attributes( $product );

		return $product;
	}

	public static function is_wc_active(): bool {
		return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
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

	public static function delete_duplicate_variations( $product, $attribute = 'grid' ): void {
		$all_variations = $product->get_children();
		if ( count( $all_variations ) <= 0 ) {
			return;
		}
		$attribute_values = array();
		$duplicate_ids    = array();
		foreach ( $all_variations as $variation_id ) {
			$variation       = new \WC_Product_Variation( $variation_id );
			$attribute_value = $variation->get_attribute( $attribute );
			if ( in_array( $attribute_value, $attribute_values ) ) {
				$duplicate_ids[] = $variation_id;
			} else {
				$attribute_values[] = $attribute_value;
			}
		}
		foreach ( $duplicate_ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	/**
	 * Check MDS order quantity against the given value using the MDS order id instead of WC order id.
	 *
	 * @param $item
	 * @param $mds_order_id
	 * @param null $quantity
	 *
	 * @return bool
	 */
	public static function check_quantity_mds( $item, $mds_order_id, $quantity = null ): bool {

		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		global $wpdb;
		$mds_quantity = $wpdb->get_var( $wpdb->prepare( "SELECT `quantity` FROM `" . MDS_DB_PREFIX . "orders` WHERE `order_id`=%d", intval( $mds_order_id ) ) );

		global $f2;
		$BID         = $f2->bid();
		$banner_data = load_banner_constants( $BID );

		// Get the item quantity
		$item_quantity = absint( $quantity ?? $item['quantity'] ) * ( $banner_data['block_width'] * $banner_data['block_height'] );

		// verify quantity isn't modified
		if ( $mds_quantity != $item_quantity ) {
			return false;
		}

		// verify there is a quantity
		if ( $mds_quantity == 0 || $item_quantity == 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if cart contains an MDS item.
	 *
	 * @return bool
	 */
	public static function is_mds_cart(): bool {
		global $woocommerce;

		if ( ! isset( $woocommerce->cart ) || ! isset( $woocommerce->cart->cart_contents ) ) {
			return false;
		}

		foreach ( $woocommerce->cart->cart_contents as $item ) {
			$meta_values = get_post_custom( $item['product_id'] );

			if ( isset( $meta_values['_milliondollarscript'] ) && $meta_values['_milliondollarscript'][0] === "yes" ) {
				// MDS item found
				return true;
			}
		}

		return false;
	}

	/**
	 * Check MDS order quantity against the given value.
	 *
	 * @param $id
	 *
	 * @return bool
	 */
	public static function check_quantity( $id ): bool {

		if ( ! self::is_mds_order( $id ) ) {
			// No MDS product so don't check quantity
			return true;
		}

		$order = wc_get_order( $id );

		$mds_order_id = get_post_meta( $id, 'mds_order_id', true );

		// If there is no mds_order_id meta yet then the order hasn't completed yet so there's no point in trying to make it complete in MDS yet.
		if ( $mds_order_id == null ) {
			return false;
		}

		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Get external payment module

		global $wpdb;
		$mds_quantity = $wpdb->get_var( $wpdb->prepare( "SELECT `quantity` FROM `" . MDS_DB_PREFIX . "orders` WHERE `order_id`=%d", intval( $mds_order_id ) ) );

//		$mds_quantity = absint( $external_payment->get_quantity( $mds_order_id ) );
		$good = true;

		global $f2;
		$BID         = $f2->bid();
		$banner_data = load_banner_constants( $BID );

		// https://stackoverflow.com/a/40715347
		// Iterating through each "line" items in the order
		foreach ( $order->get_items() as $item_id => $item_data ) {

			// Get the item quantity
			$item_quantity = absint( $item_data->get_quantity() ) * ( $banner_data['block_width'] * $banner_data['block_height'] );

			// verify quantity isn't modified
			if ( $mds_quantity != $item_quantity ) {
				$good = false;
			}

			// verify there is a quantity
			if ( $mds_quantity == 0 || $item_quantity == 0 ) {
				$good = false;
			}
		}

		return $good;
	}

	/**
	 * Check if given order id is for an MDS item.
	 *
	 * @param $id
	 *
	 * @return bool
	 */
	public static function is_mds_order( $id ): bool {

		$order = wc_get_order( $id );

		// Check if there is an MDS item in the order
		foreach ( $order->get_items() as $item ) {
			$meta_values = get_post_custom( $item['product_id'] );

			if ( isset( $meta_values['_milliondollarscript'] ) && $meta_values['_milliondollarscript'][0] === "yes" ) {
				// MDS item found
				return true;
			}
		}

		return false;
	}

	/**
	 * Tell MDS the order is complete.
	 *
	 * @param $id int WooCommerce order id
	 */
	public static function complete_mds_order( int $id ): void {
		if ( ! self::is_mds_order( $id ) ) {
			// Not a Million Dollar Script order
			return;
		}

		if ( self::check_quantity( $id ) ) {
			$mds_order_id = get_post_meta( $id, 'mds_order_id', true );
			global $wpdb;
			$sql          = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%d";
			$prepared_sql = $wpdb->prepare( $sql, intval( $mds_order_id ) );
			$row          = $wpdb->get_row( $prepared_sql, ARRAY_A );

			complete_order( $row['user_id'], $mds_order_id );
			debit_transaction( $mds_order_id, $row['price'], $row['currency'], 'WooCommerce', 'order', 'WooCommerce' );
		}
	}

	/**
	 * Checks for an MDS order with new or confirmed status.
	 *
	 * @return bool
	 */
	public static function valid_mds_order(): bool {
		global $wpdb;

		$mds_order_id = absint( WC()->session->get( "mds_order_id" ) );

		// Check for valid MDS order
		// Valid order statuses are 'pending','completed','cancelled','confirmed','new','expired','deleted','renew_wait','renew_paid'
		// We only want to check for new or confirmed
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `" . MDS_DB_PREFIX . "orders` WHERE `order_id`=%d AND (`status`='new' OR `status`='confirmed')",
				$mds_order_id
			)
		);

		return $count > 0;
	}

	/**
	 * Clear WooCommerce cart when adding a new item.
	 */
	public static function clear_cart(): void {
		if ( ! WooCommerceFunctions::is_mds_cart() ) {
			// Not a Million Dollar Script order
			return;
		}

		if ( WC()->session->get( "mds_order_id" ) ) {
			WC()->cart->empty_cart();
		}
	}

	public static function reset_session_variables( $user_id ): void {
		$keys = [ 'mds_order_id', 'mds_variation_id', 'mds_quantity' ];

		// Check if WooCommerce is active
		if ( class_exists( 'WooCommerce' ) ) {
			// Check if the session is not null
			if ( is_null( WC()->session ) ) {
				// Initialize session
				WC()->session = new \WC_Session_Handler;
			}

			// Load the user session data
			WC()->session->set_customer_session_cookie( true );

			// Get user sessions
			$session_handler = new \WC_Session_Handler();
			$session         = $session_handler->get_session( $user_id );

			if ( $session ) {
				foreach ( $keys as $key ) {
					// Reset the relevant session data.
					WC()->session->__unset( $key );
				}
			}
		}

		Orders::reset_progress( $user_id );
		delete_user_meta( $user_id, 'mds_confirm' );
	}

	public static function reset_wc_session_variables_by_order_id( $mds_order_id ): void {
		global $wpdb;

		// Get all sessions from the database
		$sessions = $wpdb->get_results( "SELECT * FROM wp_woocommerce_sessions" );

		foreach ( $sessions as $session ) {
			// Unserialize the session data
			$session_data = maybe_unserialize( $session->session_value );

			// Check if the session contains the mds_order_id
			if ( isset( $session_data['mds_order_id'] ) && $session_data['mds_order_id'] == $mds_order_id ) {
				// Reset the relevant session data
				unset( $session_data['mds_order_id'] );
				unset( $session_data['mds_variation_id'] );
				unset( $session_data['mds_quantity'] );

				// Serialize the session data again
				$session_data = maybe_serialize( $session_data );

				// Update the session data in the database
				$wpdb->update( 'wp_woocommerce_sessions', [ 'session_value' => $session_data ], [ 'session_key' => $session->session_key ] );
			}
		}
	}

	/**
	 * Reset the MDS order id for the given MDS order id.
	 */
	public static function remove_item_from_cart( $user_id, $mds_order_id ): void {
		// Get the user's cart data
		$cart_data = get_user_meta( $user_id, '_woocommerce_persistent_cart_' . get_current_blog_id(), true );

		if ( ! empty( $cart_data ) && isset( $cart_data['cart'] ) ) {
			// Loop through the cart items
			foreach ( $cart_data['cart'] as $cart_item_key => $cart_item ) {
				// Get the MDS order id of the cart item
				$cart_item_mds_order_id = $cart_item['mds_order_id'] ?? null;

				// If the MDS order id of the cart item matches the given MDS order id, remove the item from the cart
				if ( $cart_item_mds_order_id == $mds_order_id ) {
					unset( $cart_data['cart'][ $cart_item_key ] );
					break;
				}
			}

			// Update the user's cart data
			update_user_meta( $user_id, '_woocommerce_persistent_cart_' . get_current_blog_id(), $cart_data );
		}

		self::reset_wc_session_variables_by_order_id( $mds_order_id );
	}
}