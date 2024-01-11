<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.9
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

class Payment {

	/**
	 * Handles the checkout process.
	 *
	 * @return void
	 */
	public static function handle_checkout( $from_shortcode = false ): void {
		$checkout_url = Options::get_option( 'checkout-url' );
		if ( empty( $checkout_url ) ) {
			if ( WooCommerceFunctions::is_wc_active() ) {
				if ( Options::get_option( 'woocommerce' ) == 'yes' ) {

					if ( ! empty( $_REQUEST['order_id'] ) ) {

						if ( ! is_user_logged_in() ) {
							Language::out( 'Error: You must be logged in to view this page' );
						} else {

							$order_id = intval( $_REQUEST['order_id'] );

							$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id=" . $order_id . " AND user_id=" . get_current_user_id();
							$result = mysqli_query( $GLOBALS['connection'], $sql ) or mds_sql_error( $GLOBALS['connection'] );
							$row = mysqli_fetch_array( $result );

							if ( count( $row ) == 0 ) {
								Functions::no_orders();

								return;
							}

							if ( Options::get_option( 'auto-approve' ) ) {
								complete_order( $row['user_id'], $order_id );
								debit_transaction( $order_id, $row['price'], $row['currency'], 'WooCommerce', 'order', 'WooCommerce' );
								Utility::redirect( Utility::get_page_url( 'thank-you' ) );
							}

							$banner_data = load_banner_constants( $row['banner_id'] );
							$quantity    = intval( $row['quantity'] ) / intval( $banner_data['block_width'] ) / intval( $banner_data['block_height'] );

							$variation_id = WooCommerceFunctions::get_variation_id( $row['banner_id'] );
							$product_id   = WooCommerceFunctions::get_product_id();

							WC()->session->set( "mds_order_id", $order_id );
							WC()->session->set( "mds_variation_id", $variation_id );
							WC()->session->set( "mds_quantity", $quantity );

							$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
							if ( $passed_validation ) {
								$cart         = WC()->cart;
								$cart_id      = $cart->generate_cart_id( $product_id, $variation_id );
								$cart_item_id = $cart->find_product_in_cart( $cart_id );

								if ( $cart_item_id ) {
									// Product exists in the cart, so just update the quantity
									$cart->set_quantity( $cart_item_id, $quantity );
								} else {
									// Product is not in the cart, so add it
									try {
										$cart_item_key = $cart->add_to_cart( $product_id, $quantity, $variation_id );
										$cart->set_quantity( $cart_item_key, $quantity );
									} catch ( \Exception $e ) {
										error_log( Language::get( 'Error adding product to cart: ' ) . $e->getMessage() );
									}
								}

								WC()->cart->calculate_totals();

								wp_redirect( add_query_arg( 'update_cart', wp_create_nonce( 'woocommerce-cart' ), wc_get_checkout_url() ) );
							} else {
								wc_add_notice( Language::get( 'There was a problem adding the product to the cart.' ), 'error' );
								wp_redirect( wc_get_cart_url() );
							}

							exit;
						}
					} else {
						// If not called from a shortcode output a message. This causes the AJAX request to display the message.
						if ( ! $from_shortcode ) {
							Language::out( "There was a problem processing your order. Please go back and try again." );
						}
					}
				} else {
					self::default_message();
					Orders::reset_order_progress();

					return;
				}
			} else {
				self::default_message();
				Orders::reset_order_progress();

				return;
			}
		} else {
			if ( ! empty( $_REQUEST['order_id'] ) && str_contains( $checkout_url, '%' ) ) {
				$order_id = intval( $_REQUEST['order_id'] );
				$sql      = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id=" . $order_id;
				$result = mysqli_query( $GLOBALS['connection'], $sql ) or mds_sql_error( $GLOBALS['connection'] );
				$row = mysqli_fetch_array( $result );

				$checkout_url = str_replace(
					[ '%AMOUNT%', '%CURRENCY%', '%QUANTITY%', '%ORDERID', '%USERID%', '%GRID%', '%PIXELID%' ],
					[
						$row['price'],
						$row['currency'],
						$row['quantity'],
						$row['order_id'],
						$row['user_id'],
						$row['banner_id'],
						$row['ad_id']
					],
					$checkout_url
				);
			}

			Orders::reset_order_progress();

			wp_safe_redirect( $checkout_url );
			exit;
		}
	}

	/**
	 * Outputs default checkout messages for when WooCommerce integration isn't enabled.
	 *
	 * @return void
	 */
	private static function default_message(): void {

		if ( Options::get_option( 'auto-approve' ) ) {
			if ( ( $_REQUEST['order_id'] != '' ) ) {
				if ( ! is_user_logged_in() ) {
					if ( Utility::has_endpoint_or_ajax() ) {
						Language::out( 'Error: You must be logged in to view this page' );
					}
				} else {

					$order_id = intval( $_REQUEST['order_id'] );
					$sql      = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id=" . $order_id;
					$result = mysqli_query( $GLOBALS['connection'], $sql ) or mds_sql_error( $GLOBALS['connection'] );
					$row = mysqli_fetch_array( $result );

					complete_order( $row['user_id'], $order_id );
					debit_transaction( $order_id, $row['price'], $row['currency'], '', 'order', 'MDS' );
				}
			}

			// Only output if the URL has the endpoint in it or is an AJAX request.
			if ( Utility::has_endpoint_or_ajax() ) {
				Language::out( 'Your order has been successfully submitted and is now being processed. Thank you for your purchase!' );
			}
		} else {
			confirm_order( get_current_user_id(), \MillionDollarScript\Classes\Orders::get_current_order_id() );

			// Only output if the URL has the endpoint in it or is an AJAX request.
			if ( Utility::has_endpoint_or_ajax() ) {
				Language::out( 'Your order has been received and is pending approval. Please wait for confirmation from our team.' );
			}

		}

		$thank_you_page = \MillionDollarScript\Classes\Options::get_option( 'thank-you-page' );
		if ( ! empty( $thank_you_page ) ) {
			wp_safe_redirect( $thank_you_page );
			exit;
		}
	}
}