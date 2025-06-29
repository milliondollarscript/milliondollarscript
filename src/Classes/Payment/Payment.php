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

namespace MillionDollarScript\Classes\Payment;

use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Logs;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\WooCommerce\WooCommerceFunctions;

defined( 'ABSPATH' ) or exit;

class Payment {

	/**
	 * Handles the checkout process.
	 *
	 * @param int $order_id
	 *
	 * @return void
	 */
	public static function handle_checkout( int $order_id ): void {
		Orders::set_current_order_id( $order_id );

		$checkout_url = Options::get_option( 'checkout-url' );
		if ( empty( $checkout_url ) ) {
			// Check if WooCommerce is active and integration is enabled
			if ( WooCommerceFunctions::is_wc_active() && Options::get_option( 'woocommerce' ) == 'yes' ) {

				if ( ! empty( $order_id ) ) {

					if ( ! is_user_logged_in() ) {
						Utility::output_message( [
							'success' => false,
							'message' => Language::get( 'Error: You must be logged in to view this page' ),
						] );
					} else {

						$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id=" . $order_id . " AND user_id=" . get_current_user_id();
						$result = mysqli_query( $GLOBALS['connection'], $sql ) or mds_sql_error( $GLOBALS['connection'] );
						$row = mysqli_fetch_array( $result );

						if ( count( $row ) == 0 ) {
							Orders::no_orders();

							return;
						}

						if ( Options::get_option( 'auto-approve' ) == 'yes' ) {
							Orders::complete_order( $row['user_id'], $order_id );
							Payment::debit_transaction( $order_id, $row['price'], $row['currency'], 'WooCommerce', 'order', 'WooCommerce' );

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
									Logs::log( Language::get( 'Error adding product to cart: ' ) . $e->getMessage() );
								}
							}

							WC()->cart->calculate_totals();

							// Redirect to WooCommerce checkout from within an AJAX request.
							if ( wp_doing_ajax() ) {
								wp_send_json_success( [
									'redirect' => add_query_arg( 'update_cart', wp_create_nonce( 'woocommerce-cart' ), wc_get_checkout_url() ),
									'message'  => Language::get( 'Please wait...' ),
								] );
							}

							if ( ! headers_sent() ) {
								wp_redirect( add_query_arg( 'update_cart', wp_create_nonce( 'woocommerce-cart' ), wc_get_checkout_url() ) );
							} else {
								echo '
							    <script>
						        window.location.href = "' . esc_js( add_query_arg( 'update_cart', wp_create_nonce( 'woocommerce-cart' ), wc_get_checkout_url() ) ) . '";
							    </script>
							    ';
							}

						} else {
//							Utility::output_message( [
//								'success' => false,
//								'message' => Language::get( 'There was a problem adding the product to the cart.' ),
//							] );

							wc_add_notice( Language::get( 'There was a problem adding the product to the cart.' ), 'error' );
							if ( ! headers_sent() ) {
								wp_redirect( wc_get_cart_url() );
							} else {
								echo '
							    <script>
						        window.location.href = "' . esc_js( wc_get_cart_url() ) . '";
							    </script>
							    ';
							}
						}

						exit;
					}
				}

			} else {
				if ( Utility::has_endpoint_or_ajax() ) {
					self::default_message( $order_id );

					return;
				}

				return;
			}
		} else {
			if ( ! empty( $order_id ) && str_contains( $checkout_url, '%' ) ) {
				$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id=" . $order_id;
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

			if ( wp_doing_ajax() ) {
				wp_send_json_success( [
					'redirect' => $checkout_url,
					'message'  => Language::get( 'Please wait...' ),
				] );
			}

			if ( ! headers_sent() ) {
				wp_safe_redirect( $checkout_url );
			} else {
				echo '
			    <script>
		        window.location.href = "' . esc_js( $checkout_url ) . '";
			    </script>
			    ';
			}
			exit;
		}
	}

	/**
	 * Outputs default checkout messages for when WooCommerce integration isn't enabled.
	 *
	 * @param int $order_id
	 *
	 * @return void
	 */
	private static function default_message( int $order_id ): void {
		if ( empty( $order_id ) || ! Orders::is_owned_by( $order_id ) ) {
			if ( Utility::has_endpoint_or_ajax() ) {
				$message = Language::get( 'There was a problem processing your order. Please go back and try again.' );
				Utility::output_message( [
					'success' => false,
					'message' => $message,
				] );
			}

			return;
		}

		if ( Options::get_option( 'auto-approve' ) == 'yes' ) {
			if ( $order_id != '' ) {
				if ( ! is_user_logged_in() ) {
					if ( Utility::has_endpoint_or_ajax() ) {
						$message = Language::get( 'Error: You must be logged in to view this page' );
						Utility::output_message( [
							'success' => false,
							'message' => $message,
						] );
					}
				} else {
					$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id=" . $order_id;
					$result = mysqli_query( $GLOBALS['connection'], $sql ) or mds_sql_error( $GLOBALS['connection'] );
					$row = mysqli_fetch_array( $result );

					Orders::complete_order( $row['user_id'], $order_id );
					Payment::debit_transaction( $order_id, $row['price'], $row['currency'], '', 'order', 'MDS' );
					$message = Language::get( 'Your order has been successfully completed. Thank you for your purchase!' );
					Utility::output_message( [
						'success' => true,
						'message' => $message,
					] );
				}
			}

			// Only output if the URL has the endpoint in it or is an AJAX request.
			if ( Utility::has_endpoint_or_ajax() ) {
				$message = Language::get( 'Your order has been successfully submitted and is now being processed. Thank you for your purchase!' );
				Utility::output_message( [
					'success' => true,
					'message' => $message,
				] );
			}
		} else {

			Orders::pend_order( $order_id );

			// Only output if the URL has the endpoint in it or is an AJAX request.
			if ( Utility::has_endpoint_or_ajax() ) {
				$message = Language::get( 'Your order has been received and is pending approval. Please wait for confirmation from our team.' );
				Utility::output_message( [
					'success' => true,
					'message' => $message,
				] );
			}
		}

		$thank_you_page = Options::get_option( 'thank-you-page' );
		if ( ! empty( $thank_you_page ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_success( [
					'redirect' => $thank_you_page,
					'message'  => Language::get( 'Please wait...' ),
				] );
			}

			if ( ! headers_sent() ) {
				wp_safe_redirect( $thank_you_page );
			} else {
				echo '
			    <script>
		        window.location.href = "' . esc_js( $thank_you_page ) . '";
			    </script>
			    ';
			}
			exit;
		}
	}

	public static function credit_transaction( $order_id, $amount, $currency, $txn_id, $reason, $origin ) {

		$type = "CREDIT";

		$date = current_time( 'mysql' );

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "transactions where txn_id='" . mysqli_real_escape_string( $GLOBALS['connection'], $txn_id ) . "' and `type`='CREDIT' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		if ( mysqli_num_rows( $result ) != 0 ) {
			return; // there already is a credit for this txn_id
		}

		// check to make sure that there is a debit for this transaction

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "transactions where txn_id='" . mysqli_real_escape_string( $GLOBALS['connection'], $txn_id ) . "' and `type`='DEBIT' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		if ( mysqli_num_rows( $result ) > 0 ) {

			$sql = "INSERT INTO " . MDS_DB_PREFIX . "transactions (`txn_id`, `date`, `order_id`, `type`, `amount`, `currency`, `reason`, `origin`) VALUES('" . mysqli_real_escape_string( $GLOBALS['connection'], $txn_id ) . "', '$date', '" . intval( $order_id ) . "', '$type', '" . floatval( $amount ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $currency ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $reason ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $origin ) . "')";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mds_sql_error( $sql ) );
		}
	}

	public static function debit_transaction( $order_id, $amount, $currency, $txn_id, $reason, $origin ) {

		$type = "DEBIT";
		$date = current_time( 'mysql' );
		// check to make sure that there is no debit for this transaction already

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "transactions where txn_id='" . mysqli_real_escape_string( $GLOBALS['connection'], $txn_id ) . "' and `type`='DEBIT' AND order_id=" . intval( $order_id );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
		if ( mysqli_fetch_array( $result ) == 0 ) {
			$sql = "INSERT INTO " . MDS_DB_PREFIX . "transactions (`txn_id`, `date`, `order_id`, `type`, `amount`, `currency`, `reason`, `origin`) VALUES('" . mysqli_real_escape_string( $GLOBALS['connection'], $txn_id ) . "', '$date', '" . intval( $order_id ) . "', '$type', '" . floatval( $amount ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $currency ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $reason ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $origin ) . "')";

			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		}
	}
}