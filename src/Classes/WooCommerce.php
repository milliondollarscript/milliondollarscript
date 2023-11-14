<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.5
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

class WooCommerce {

	private $_PAYMENT_OBJECTS;

	function __construct() {
//		add_filter( 'product_type_selector', [ __CLASS__, 'product_type_selector' ] );
//		add_filter( 'woocommerce_product_class', [ __CLASS__, 'woocommerce_product_class' ], 10, 3 );
//		add_action( 'admin_footer', [ __CLASS__, 'admin_footer' ] );
		add_filter( 'product_type_options', [ __CLASS__, 'product_type_options' ] );
		//add_action( 'woocommerce_process_product_meta_milliondollarscript', [ __CLASS__, 'save_option_field' ] );
		add_action( 'woocommerce_process_product_meta', [ __CLASS__, 'save_option_field' ] );
		add_action( 'woocommerce_update_product', [ __CLASS__, 'update_product' ], 10, 1 );
		add_action( 'pre_get_posts', [ __CLASS__, 'pre_get_posts' ] );

		add_action( 'woocommerce_order_status_changed', [ __CLASS__, 'status_changed' ], 20, 4 );
		add_action( 'woocommerce_order_edit_status', [ __CLASS__, 'status_edit' ], 20, 2 );

		// add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'maybe_clear_cart' ] );
		add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'validate_order' ], 10, 1 );

		add_filter( 'woocommerce_checkout_update_order_meta', [ __CLASS__, 'create_order' ], 10, 2 );

//		add_action( 'woocommerce_before_cart', [ __CLASS__, 'add_discount' ] );
//		add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'add_discount' ] );

		add_filter( 'woocommerce_update_cart_validation', [ __CLASS__, 'validate_cart' ], 10, 4 );
		add_filter( 'woocommerce_update_cart_action_cart_updated', [ __CLASS__, 'update_cart_action_cart_updated' ], 10, 1 );
		//woocommerce_stock_amount_cart_item

		add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'add_to_cart_validation' ], 10, 5 );

		add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'before_calculate_totals' ] );

		add_action( 'woocommerce_after_calculate_totals', [ __CLASS__, 'reset_quantity' ] );

		add_filter( 'woocommerce_cart_item_quantity', [ __CLASS__, 'disable_quantity_field' ], 10, 3 );
		add_filter( 'woocommerce_quantity_input_args', [ __CLASS__, 'disable_product_quantity_input' ], 10, 2 );

		add_action( 'woocommerce_thankyou', [ __CLASS__, 'reset_session_variables' ], 10, 1 );
		add_action( 'woocommerce_thankyou', [ __CLASS__, 'thank_you_redirect' ], 11, 1 );
	}

	public static function thank_you_redirect( $order_id ): void {
		$thank_you_page = \MillionDollarScript\Classes\Options::get_option( 'thank-you-page' );
		if ( ! empty( $thank_you_page ) ) {
			wp_safe_redirect( $thank_you_page );
			exit;
		}
	}

	public static function disable_product_quantity_input( $args, $product ) {
		$product_id = \MillionDollarScript\Classes\Functions::get_product_id();
		if ( $product->get_id() === $product_id ) {
			$args['input_value'] = 1;
			$args['max_value']   = 1;
			$args['min_value']   = 1;
		}

		return $args;
	}

	public static function reset_session_variables( $order_id ): void {
		$keys = [ 'mds_order_id', 'mds_variation_id', 'mds_quantity' ];

		foreach ( $keys as $key ) {
			WC()->session->__unset( $key );
		}

		delete_user_meta( get_current_user_id(), MDS_PREFIX . 'current_order_id' );
	}

	public static function reset_quantity( $cart ): void {
		$product_id   = \MillionDollarScript\Classes\Functions::get_product_id();
		$variation_id = absint( WC()->session->get( "mds_variation_id" ) );
		$quantity     = absint( WC()->session->get( "mds_quantity" ) );

		$cart_id      = $cart->generate_cart_id( $product_id, $variation_id );
		$cart_item_id = $cart->find_product_in_cart( $cart_id );

		if ( $cart_item_id ) {
			$cart_item = $cart->get_cart_item( $cart_item_id );

			if ( $cart_item['quantity'] != $quantity ) {
				$cart->set_quantity( $cart_item_id, $quantity, true );
			}
		}
	}

	public static function disable_quantity_field( $product_quantity, $cart_item_key, $cart_item ): string {
		$product_id   = \MillionDollarScript\Classes\Functions::get_product_id();
		$variation_id = absint( WC()->session->get( "mds_variation_id" ) );

		if ( $cart_item['product_id'] == $product_id && ( ! empty( $cart_item['variation_id'] ) && $cart_item['variation_id'] == $variation_id ) ) {
			return sprintf( '%s <input type="hidden" name="cart[%s][qty]" value="%s" />', $cart_item['quantity'], $cart_item_key, $cart_item['quantity'] );
		}

		return $product_quantity;
	}

	public static function before_calculate_totals( $cart_object ): void {
		if ( is_checkout() ) {
			global $wpdb;

			$product_id   = \MillionDollarScript\Classes\Functions::get_product_id();
			$mds_order_id = absint( WC()->session->get( "mds_order_id" ) );

			foreach ( $cart_object->get_cart() as $cart_item ) {
				if ( $cart_item['product_id'] == $product_id ) {
					$table = MDS_DB_PREFIX . 'orders';
					$price = $wpdb->get_var( $wpdb->prepare(
						"SELECT `price` FROM `$table` WHERE `order_id`=%d",
						$mds_order_id
					) );

					if ( $price ) {
						// Order total is the price of all blocks in all price zones.
						// Here we will divide by the quantity to get the right price since we are using the total order price from the MDS table and WC will multiply that by the quantity.
						$cart_item['data']->set_price( $price / $cart_item['quantity'] );
					}
				}
			}
		}
	}

	/**
	 * Validates the cart when it's updated. This filter loops all the cart contents so no loop required in here.
	 */
	public static function validate_cart( $passed_validation, $cart_item_key, $values, $quantity ) {

		global $passed_validation_mds;

		$meta_values = get_post_custom( $values['product_id'] );

		if ( isset( $meta_values['_milliondollarscript'] ) && $meta_values['_milliondollarscript'][0] === "yes" ) {
			// MDS item found
			$mds_order_id = absint( WC()->session->get( "mds_order_id" ) );

			if ( ! self::check_quantity_mds( $values, $mds_order_id, $quantity ) ) {
				$passed_validation_mds = false;

				return false;
			}
		}

		return $passed_validation;
	}

	/**
	 * Possibly prevent cart from being updated if quantity was wrong.
	 *
	 */
	public static function update_cart_action_cart_updated( $cart_updated ) {
		global $passed_validation_mds;

		if ( $passed_validation_mds === false ) {
			$message = Language::get( "Quantity does not match! (1)" );
			\wc_add_notice( $message, 'error' );

			return false;
		}

		return $cart_updated;
	}

	/**
	 * Validates the order before it's created only using the contents of the cart.
	 */
	public static function validate_order( \WC_Checkout $checkout ): void {
		if ( WC()->cart->is_empty() ) {
			return;
		}

		$cart       = WC()->cart->get_cart();
		$product_id = Functions::get_product_id();

		foreach ( $cart as $cart_item_key => $cart_item ) {
			if ( $cart_item['product_id'] != $product_id ) {
				continue;
			}

			$meta_value = get_post_meta( $product_id, '_milliondollarscript', true );
			if ( $meta_value != 'yes' ) {
				continue;
			}

			// MDS item found
			$mds_order_id = absint( WC()->session->get( "mds_order_id" ) );

			if ( ! self::check_quantity_mds( $cart_item, $mds_order_id ) ) {
				$message = Language::get( "Quantity does not match! (2)" );
				\wc_add_notice( '<a href="' . wc_get_cart_url() . '" class="button wc-forward">' . __( 'View cart', 'woocommerce' ) . '</a> ' . $message, 'error' );
			}
		}
	}

	/**
	 * Validate item added to cart.
	 *
	 * @param $passed
	 * @param $product_id
	 * @param $quantity
	 * @param string $variation_id
	 * @param string $variations
	 *
	 * @return mixed
	 */
	public static function add_to_cart_validation( $passed, $product_id, $quantity, $variation_id = '', $variations = '' ): mixed {

		// Clear cart if option is set to yes
		if ( Options::get_option( 'clear-cart', 'no', false, 'options' ) == 'yes' ) {
			self::clear_cart();
		}

		// Check if the product added to the cart was an MDS product
		$product = wc_get_product( $product_id );
		if ( $product->get_meta( '_milliondollarscript', true ) == 'yes' ) {
			if ( self::valid_mds_order() ) {
				// Run custom functions
				do_action( 'mds_added_to_cart', $product, $quantity );
			} else {
				// Do not add to cart because there was no valid MDS order found.
				$passed = false;
			}
		}

		return $passed;
	}

	/*
	public static function register_product_type() {
		require_once __DIR__ . '/ProductType.php';
	}

	public static function product_type_selector( $types ) {
		$types['milliondollarscript'] = 'Million Dollar Script';

		return $types;
	}

	public static function woocommerce_product_class( $classname, $product_type, $product_id ): string {
		if ( $product_type == 'milliondollarscript' ) {
			$classname = '\MillionDollarScript\Classes\ProductType';
		}

		return $classname;
	}

	function admin_footer() {
		if ( 'product' != get_post_type() ) {
			return;
		}

		?>
		<script type='text/javascript'>
			jQuery(document).ready(function () {
				jQuery('.options_group.pricing').addClass('show_if_milliondollarscript').show();

				// check which product type is selected
				let selectedProductType = jQuery('#product-type').val();
				if (selectedProductType === 'milliondollarscript') {
					// Deactivate Shipping panel in left Menu
					jQuery('.shipping_tab').removeClass('active');
					// Hide Shipping panel on load
					jQuery('#shipping_product_data').addClass('hidden').hide();

					// Deactivate Linked Products panel in left Menu
					jQuery('.linked_product_tab').removeClass('active');
					// Hide Shipping panel on load
					jQuery('#linked_product_data').addClass('hidden').hide();

					// Activate General panel in left Menu
					jQuery('.general_tab').addClass('active').show();
					// Show General panel on load
					jQuery('#general_product_data').removeClass('hidden').show();
				}
			});
		</script>
		<?php
	}
	*/

	public static function product_type_options( $product_type_options ) {
		$product_type_options['milliondollarscript'] = array(
			'id'            => '_milliondollarscript',
			'wrapper_class' => '',
			'label'         => Language::get( 'Million Dollar Script Pixels' ),
			'description'   => Language::get( 'Check this to set this product as a Million Dollar Script Pixels product.' ),
			'default'       => 'no'
		);

		return $product_type_options;
	}

	public static function save_option_field( $post_id ): void {
		$mds = isset( $_POST['_milliondollarscript'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_milliondollarscript', $mds );
	}

	/**
	 * Sets a MDS product as hidden when saved so it doesn't show up in the shop.
	 *
	 * @param $product_id
	 */
	public static function update_product( $product_id ): void {
		$product = wc_get_product( $product_id );
		if ( $product->get_meta( '_milliondollarscript', true ) == 'yes' ) {

			// Set as hidden from the shop
			$visibility = $product->get_catalog_visibility();
			if ( $visibility != 'hidden' ) {
				try {
					$product->set_catalog_visibility( 'hidden' );
					$product->save();
				} catch ( \WC_Data_Exception $e ) {
					error_log( 'Error setting MDS product hidden: ' . $e->getTraceAsString() );
				}
			}
		}
	}

	/**
	 * Exclude proper terms from the search query since WC doesn't for some reason.
	 *
	 * @param $query \WP_Query
	 *
	 * @return mixed
	 */
	public static function pre_get_posts( \WP_Query $query ): \WP_Query {
		if ( $query->is_search() && ! is_admin() && $query->is_main_query() ) {
			$query->set( 'tax_query', [
				[
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => [ 'exclude-from-catalog', 'exclude-from-search' ],
					'operator' => 'NOT IN',
				]
			] );
		}

		return $query;
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

		// Check if there is a MDS item in the order
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
	 * Add order notes with order id numbers when the order status changes.
	 *
	 * @param $id
	 * @param $from
	 * @param $to
	 * @param $order
	 */
	public static function status_changed( $id, $from, $to, $order ): void {
		if ( ! self::is_mds_order( $id ) ) {
			// Not a Million Dollar Script order
			return;
		}

		$mds_order_id = get_post_meta( $id, 'mds_order_id', true );
		if ( ! empty( $mds_order_id ) ) {
			$order->add_order_note( "MDS order id: " . $mds_order_id );
		}

		$btcpay_id = get_post_meta( $id, 'BTCPay_id', true );
		if ( ! empty( $btcpay_id ) ) {
			$order->add_order_note( "BTCPay id: " . $btcpay_id );
		}

		$coinpayments_id = get_post_meta( $id, 'Transaction ID', true );
		if ( ! empty( $coinpayments_id ) ) {
			$order->add_order_note( "CoinPayments Transaction ID: " . $coinpayments_id );
		}

		$coinbase_id = get_post_meta( $id, '_coinbase_charge_id', true );
		if ( ! empty( $coinbase_id ) ) {
			$order->add_order_note( "Coinbase Charge ID: " . $coinbase_id );
		}

		// Action hook for MDS status change
		do_action( 'mds-status-changed', $id, $mds_order_id, $from, $to, $order );

		// Complete MDS payment if order goes to 'completed'
		if ( $to === "completed" ) {
			self::complete_mds_order( $id );
		}
	}

	/**
	 * Manual order status change to 'completed'.
	 *
	 * @param $id
	 * @param $to
	 */
	public static function status_edit( $id, $to ): void {
		if ( ! self::is_mds_order( $id ) ) {
			// Not a Million Dollar Script order
			return;
		}

		if ( $to === "completed" ) {
			$order        = wc_get_order( $id );
			$mds_order_id = get_post_meta( $id, 'mds_order_id', true );
			if ( ! empty( $mds_order_id ) ) {
				$order->add_order_note( "MDS order id: " . $mds_order_id );
			}

			self::complete_mds_order( $id );
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
	 * Check if we should clear WooCommerce cart when viewing the checkout form.
	 * Returns true of cart is cleared otherwise returns false.
	 *
	 * @return void
	 */
	// public static function maybe_clear_cart(): void {
	// 	$product_id = Functions::get_product_id();
	// 	$cart       = WC()->cart->get_cart();
	// 	foreach ( $cart as $cart_item_key => $cart_item ) {
	// 		if ( $cart_item['product_id'] == $product_id ) {
	// 			$meta_value = get_post_meta( $product_id, '_milliondollarscript', true );
	// 			if ( $meta_value == 'yes' ) {
	// 				// MDS item found
	//
	// 				if ( ! self::valid_mds_order() ) {
	// 					WC()->cart->remove_cart_item( $cart_item_key );
	// 					WC()->cart->calculate_totals();
	// 				}
	// 			}
	// 		}
	// 	}
	// }

	/**
	 * Clear WooCommerce cart when adding a new item.
	 */
	public static function clear_cart(): void {
		if ( ! self::is_mds_cart() ) {
			// Not a Million Dollar Script order
			return;
		}

		if ( WC()->session->get( "mds_order_id" ) ) {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * Save MDS Order id
	 *
	 * @param $order_id
	 * @param $data
	 *
	 * @return mixed
	 * @throws \Exception
	 * @see WC_Checkout::create_order() WooCommerce create order function.
	 *
	 */
	public static function create_order( $order_id, $data ): mixed {
		if ( ! self::is_mds_order( $order_id ) ) {
			// Not a Million Dollar Script order
			return null;
		}

		$order        = wc_get_order( $order_id );
		$mds_order_id = absint( WC()->session->get( "mds_order_id" ) );
		update_post_meta( $order_id, "mds_order_id", $mds_order_id );
		if ( ! self::check_quantity( $order_id ) ) {
			$message = "Quantity does not match! (3)";
			throw new \Exception( '<a href="' . wc_get_cart_url() . '" class="button wc-forward">' . __( 'View cart', 'woocommerce' ) . '</a> ' . $message );
		}
		$order->add_order_note( "MDS order id: " . $mds_order_id );

		return null;
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

}
