<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.10
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

class WooCommerce {

	function __construct() {
		add_filter( 'product_type_options', [ __CLASS__, 'product_type_options' ] );
		add_action( 'woocommerce_process_product_meta', [ __CLASS__, 'save_option_field' ] );
		add_action( 'woocommerce_update_product', [ __CLASS__, 'update_product' ], 10, 1 );
		add_action( 'pre_get_posts', [ __CLASS__, 'pre_get_posts' ] );
		add_action( 'woocommerce_order_status_changed', [ __CLASS__, 'status_changed' ], 20, 4 );
		add_action( 'woocommerce_order_edit_status', [ __CLASS__, 'status_edit' ], 20, 2 );
		add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'validate_order' ], 10, 1 );
		add_filter( 'woocommerce_checkout_update_order_meta', [ __CLASS__, 'create_order' ], 10, 2 );
		add_filter( 'woocommerce_update_cart_validation', [ __CLASS__, 'validate_cart' ], 10, 4 );
		add_filter( 'woocommerce_update_cart_action_cart_updated', [ __CLASS__, 'update_cart_action_cart_updated' ] );
		add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'add_to_cart_validation' ], 10, 5 );
		add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'before_calculate_totals' ] );
		add_action( 'woocommerce_after_calculate_totals', [ __CLASS__, 'reset_quantity' ] );
		add_filter( 'woocommerce_cart_item_quantity', [ __CLASS__, 'disable_quantity_field' ], 10, 3 );
		add_filter( 'woocommerce_quantity_input_args', [ __CLASS__, 'disable_product_quantity_input' ], 10, 2 );
		add_action( 'woocommerce_thankyou', [ __CLASS__, 'complete_order' ], 9, 1 );
		add_action( 'woocommerce_thankyou', [ __CLASS__, 'thank_you_redirect' ], 11, 1 );
		add_action( 'woocommerce_payment_complete', [ __CLASS__, 'payment_complete' ] );
		add_action( 'woocommerce_after_cart_item_quantity_update', [ __CLASS__, 'adjust_cart_item_quantity_after_update' ], 20, 4 );
	}

	public static function adjust_cart_item_quantity_after_update( $cart_item_key, $quantity, $old_quantity, $cart ): void {
		$product_id   = WooCommerceFunctions::get_product_id();
		$variation_id = absint( WC()->session->get( "mds_variation_id" ) );

		$cart_item = $cart->get_cart_item( $cart_item_key );
		if ( $cart_item['product_id'] == $product_id && ( ! empty( $cart_item['variation_id'] ) && $cart_item['variation_id'] == $variation_id ) ) {
			// This is the product we're interested in, set the quantity back to the desired value
			$desired_quantity = absint( WC()->session->get( "mds_quantity" ) );
			if ( $quantity != $desired_quantity ) {
				$cart->set_quantity( $cart_item_key, $desired_quantity, false );
			}
		}
	}

	public static function payment_complete( $order_id ): void {
		$order = wc_get_order( $order_id );

		$auto_complete = \MillionDollarScript\Classes\Options::get_option( 'wc-auto-complete', true );
		if ( $auto_complete ) {
			$to = 'completed';
		} else {
			$to = 'processing';
		}
		$order->update_status( $to );
	}

	public static function complete_order( $order_id ): void {
		if ( ! WooCommerceFunctions::is_mds_order( $order_id ) ) {
			// Not a Million Dollar Script order
			return;
		}

		$mds_order_id = absint( WC()->session->get( "mds_order_id" ) );
		if ( empty( $mds_order_id ) ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( ! empty( \MillionDollarScript\Classes\Orders::get_current_order_id() ) ) {
			$order = wc_get_order( $order_id );

			if ( Options::get_option( 'auto-approve' ) ) {

				$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id=" . $order_id;
				$result = mysqli_query( $GLOBALS['connection'], $sql ) or mds_sql_error( $GLOBALS['connection'] );
				$row = mysqli_fetch_array( $result );

				complete_order( $user_id, $mds_order_id );

				$transaction_id = $order->get_transaction_id();
				debit_transaction( $mds_order_id, $row['price'], $row['currency'], $transaction_id, 'order', 'WC' );

			} else {
				// Should already be confirmed at this point, but sometimes they may not follow the exact checkout path
				confirm_order( $user_id, $mds_order_id );
			}

			// If the order doesn't have mds_order_id yet then add it and a note.
			$post_meta_mds_order_id = get_post_meta( $order_id, 'mds_order_id', true );
			if ( empty( $post_meta_mds_order_id ) ) {
				update_post_meta( $order_id, "mds_order_id", $mds_order_id );
				$order->add_order_note( "MDS order id: <a href='" . esc_url( admin_url( 'admin.php?page=mds-orders&order_id=' . $mds_order_id ) ) . "'>" . $mds_order_id . "</a>" );
				$order->save();
			}
		}

		WooCommerceFunctions::reset_session_variables( $user_id );
	}

	public static function thank_you_redirect( $order_id ): void {
		$thank_you_page = \MillionDollarScript\Classes\Options::get_option( 'thank-you-page' );
		if ( ! empty( $thank_you_page ) ) {
			wp_safe_redirect( $thank_you_page );
			exit;
		}
	}

	public static function disable_product_quantity_input( $args, $product ) {
		$product_id = WooCommerceFunctions::get_product_id();
		if ( $product->get_id() === $product_id ) {
			$args['input_value'] = 1;
			$args['max_value']   = 1;
			$args['min_value']   = 1;
		}

		return $args;
	}

	public static function reset_quantity( $cart ): void {
		$product_id   = WooCommerceFunctions::get_product_id();
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
		$product_id   = WooCommerceFunctions::get_product_id();
		$variation_id = absint( WC()->session->get( "mds_variation_id" ) );

		if ( $cart_item['product_id'] == $product_id && ( ! empty( $cart_item['variation_id'] ) && $cart_item['variation_id'] == $variation_id ) ) {
			return sprintf( '%s <input type="hidden" name="cart[%s][qty]" value="%s" />', $cart_item['quantity'], $cart_item_key, $cart_item['quantity'] );
		}

		return $product_quantity;
	}

	public static function before_calculate_totals( $cart_object ): void {
		if ( is_checkout() ) {
			global $wpdb;

			$product_id   = WooCommerceFunctions::get_product_id();
			$mds_order_id = absint( WC()->session->get( "mds_order_id" ) );
			$quantity     = absint( WC()->session->get( "mds_quantity" ) );

			foreach ( $cart_object->get_cart() as $cart_item_key => $cart_item ) {
				if ( $cart_item['product_id'] == $product_id ) {
					$table = MDS_DB_PREFIX . 'orders';
					$price = $wpdb->get_var( $wpdb->prepare(
						"SELECT `price` FROM `$table` WHERE `order_id`=%d",
						$mds_order_id
					) );

					if ( $price ) {
						// Check if the price is already the desired value before setting it
						$current_price = $cart_item['data']->get_price();
						$new_price     = $price / $quantity;
						if ( $current_price != $new_price ) {
							$cart_item['data']->set_price( $new_price );
						}
					}

					// Check if the quantity is already the desired value before setting it
					$current_quantity = $cart_item['quantity'];
					if ( $current_quantity != $quantity ) {
						$cart_object->set_quantity( $cart_item_key, $quantity );
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

			if ( ! WooCommerceFunctions::check_quantity_mds( $values, $mds_order_id, $quantity ) ) {
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
		$product_id = WooCommerceFunctions::get_product_id();

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

			if ( ! WooCommerceFunctions::check_quantity_mds( $cart_item, $mds_order_id ) ) {
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
			WooCommerceFunctions::clear_cart();
		}

		// Check if the product added to the cart was an MDS product
		$product = wc_get_product( $product_id );
		if ( $product->get_meta( '_milliondollarscript', true ) == 'yes' ) {
			if ( WooCommerceFunctions::valid_mds_order() ) {
				// Run custom functions
				do_action( 'mds_added_to_cart', $product, $quantity );
			} else {
				// Do not add to cart because there was no valid MDS order found.
				$passed = false;
			}
		}

		return $passed;
	}

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
	 * Add order notes with order id numbers when the order status changes.
	 *
	 * @param $id int
	 * @param $from string
	 * @param $to string
	 * @param $order \WC_order
	 */
	public static function status_changed( int $id, string $from, string $to, \WC_order $order ): void {
		if ( ! WooCommerceFunctions::is_mds_order( $id ) ) {
			// Not a Million Dollar Script order
			return;
		}

		$mds_order_id = get_post_meta( $id, 'mds_order_id', true );
		if ( ! empty( $mds_order_id ) ) {
			$order->add_order_note( "MDS order id: <a href='" . esc_url( admin_url( 'admin.php?page=mds-orders&order_id=' . $mds_order_id ) ) . "'>" . $mds_order_id . "</a>" );
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

		$auto_complete = \MillionDollarScript\Classes\Options::get_option( 'wc-auto-complete', true );
		if ( $auto_complete ) {
			$to = 'completed';
		} else {
			$to = 'processing';
		}

		// Action hook for MDS status change
		do_action( 'mds-status-changed', $id, $mds_order_id, $from, $to, $order );

		// Complete MDS payment if order goes to 'completed'
		if ( $to === "completed" ) {
			WooCommerceFunctions::complete_mds_order( $id );
		}
	}

	/**
	 * Manual order status change to 'completed'.
	 *
	 * @param $id
	 * @param $to
	 */
	public static function status_edit( $id, $to ): void {
		if ( ! WooCommerceFunctions::is_mds_order( $id ) ) {
			// Not a Million Dollar Script order
			return;
		}

		if ( $to === "completed" ) {
			$order        = wc_get_order( $id );
			$mds_order_id = get_post_meta( $id, 'mds_order_id', true );
			if ( ! empty( $mds_order_id ) ) {
				$order->add_order_note( "MDS order id: <a href='" . esc_url( admin_url( 'admin.php?page=mds-orders&order_id=' . $mds_order_id ) ) . "'>" . $mds_order_id . "</a>" );
			}

			WooCommerceFunctions::complete_mds_order( $id );
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
		if ( ! WooCommerceFunctions::is_mds_order( $order_id ) ) {
			// Not a Million Dollar Script order
			return null;
		}

		$order        = wc_get_order( $order_id );
		$mds_order_id = absint( WC()->session->get( "mds_order_id" ) );
		update_post_meta( $order_id, "mds_order_id", $mds_order_id );
		if ( ! WooCommerceFunctions::check_quantity( $order_id ) ) {
			$message = "Quantity does not match! (3)";
			throw new \Exception( '<a href="' . wc_get_cart_url() . '" class="button wc-forward">' . __( 'View cart', 'woocommerce' ) . '</a> ' . $message );
		}
		$order->add_order_note( "MDS order id: <a href='" . esc_url( admin_url( 'admin.php?page=mds-orders&order_id=' . $mds_order_id ) ) . "'>" . $mds_order_id . "</a>" );

		return null;
	}

}
