<?php

/**
 * Million Dollar Script Two
 *
 * @version 2.3.2
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

		//add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'clear_cart' ] );

		add_filter( 'woocommerce_checkout_update_order_meta', [ __CLASS__, 'create_order' ], 10, 2 );

//		add_action( 'woocommerce_before_cart', [ __CLASS__, 'add_discount' ] );
//		add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'add_discount' ] );

		add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'add_to_cart_validation' ], 10, 5 );
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
	public static function add_to_cart_validation( $passed, $product_id, $quantity, $variation_id = '', $variations = '' ) {

		// Clear cart if option is set to yes
		if ( Options::get_option( 'clear-cart', 'options', 'no' ) == 'yes' ) {
			self::clear_cart();
		}

		// https://example.com/checkout/?add-to-cart=17&quantity=100&mdsid=18
		if ( ( isset( $_REQUEST['add-to-cart'] ) && ! empty( $_REQUEST['add-to-cart'] ) ) && ( isset( $_REQUEST['quantity'] ) && ! empty( $_REQUEST['quantity'] ) ) ) {
			WC()->cart->empty_cart();
			if ( isset( $_REQUEST['mdsid'] ) && ! empty( $_REQUEST['mdsid'] ) ) {
				$value = absint( $_REQUEST['mdsid'] );
				WC()->session->set( "mds_order_id", $value );
			}
		}

		// Check if the product added to the cart was an MDS product
		$product = wc_get_product( $product_id );
		if ( $product->get_meta( '_milliondollarscript', true ) == 'yes' ) {
			// Run custom functions
			do_action( 'mds_added_to_cart', $product, $quantity );
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
			'label'         => __( 'Million Dollar Script Pixels', 'milliondollarscript' ),
			'description'   => __( 'Check this to set this product as a Million Dollar Script Pixels product.', 'milliondollarscript' ),
			'default'       => 'no'
		);

		return $product_type_options;
	}

	public static function save_option_field( $post_id ) {
		$mds = isset( $_POST['_milliondollarscript'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_milliondollarscript', $mds );
	}

	/**
	 * Sets a MDS product as hidden when saved so it doesn't show up in the shop.
	 *
	 * @param $product_id
	 */
	public static function update_product( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( $product->get_meta( '_milliondollarscript', true ) == 'yes' ) {
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
//		$mdspath = Options::get_mds_path();
//		require_once $mdspath . "payment/external.php";
//
//		$external_payment = new \external;
		global $wpdb;
		$mds_quantity = $wpdb->get_var( $wpdb->prepare( "SELECT `quantity` FROM `" . MDS_DB_PREFIX . "orders` WHERE `order_id`=%d", intval( $mds_order_id ) ) );

//		$mds_quantity = absint( $external_payment->get_quantity( $mds_order_id ) );
		$good         = true;

		$mdspath = Options::get_mds_path();
		require_once $mdspath . "include/init.php";
		global $f2;
		$BID = $f2->bid();
		$banner_data = load_banner_constants( $BID );

		// https://stackoverflow.com/a/40715347
		// Iterating through each "line" items in the order
		foreach ( $order->get_items() as $item_id => $item_data ) {

			// Get the item quantity
			$item_quantity = absint( $item_data->get_quantity() ) * ($banner_data['block_width'] * $banner_data['block_height']);

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
	public static function complete_mds_order( int $id ) {
		if ( ! self::is_mds_order( $id ) ) {
			// Not a Million Dollar Script order
			return;
		}

		if ( self::check_quantity( $id ) ) {
			if ( ! function_exists( 'get_home_path' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			// Get external payment module
			$mdspath = Options::get_mds_path();
			require_once $mdspath . "payment/external.php";

			$external_payment = new \external;

			$mds_order_id = get_post_meta( $id, 'mds_order_id', true );
			$external_payment->complete_order( $mds_order_id );
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
	public static function status_changed( $id, $from, $to, $order ) {
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

		// Complete MDS payment if order goes to completed
		if ( $to === "completed" ) {
			self::complete_mds_order( $id );
		}
	}

	/**
	 * Manual order status change to completed.
	 *
	 * @param $id
	 * @param $to
	 */
	public static function status_edit( $id, $to ) {
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
	 * Clear WooCommerce cart when adding a new item.
	 */
	public static function clear_cart() {
		if ( ! self::is_mds_cart() ) {
			// Not a Million Dollar Script order
			return;
		}

		// https://example.com/checkout/?add-to-cart=17&quantity=100&mdsid=18
		if ( ( isset( $_REQUEST['add-to-cart'] ) && ! empty( $_REQUEST['add-to-cart'] ) ) && ( isset( $_REQUEST['quantity'] ) && ! empty( $_REQUEST['quantity'] ) ) ) {
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
	public static function create_order( $order_id, $data ) {
		if ( ! self::is_mds_order( $order_id ) ) {
			// Not a Million Dollar Script order
			return;
		}

		$order        = wc_get_order( $order_id );
		$mds_order_id = absint( WC()->session->get( "mds_order_id" ) );
		update_post_meta( $order_id, "mds_order_id", $mds_order_id );
		if ( ! self::check_quantity( $order_id ) ) {
			throw new \Exception( "Quantity does not match!" );
		}
		$order->add_order_note( "MDS order id: " . $mds_order_id );

		return null;
	}
}
