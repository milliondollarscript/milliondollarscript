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

use MillionDollarScript\Classes\Config;
use MillionDollarScript\Classes\Functions;
use MillionDollarScript\Classes\Language;
use MillionDollarScript\Classes\Options;
use MillionDollarScript\Classes\Orders;
use MillionDollarScript\Classes\Steps;
use MillionDollarScript\Classes\Utility;
use MillionDollarScript\Classes\WooCommerceFunctions;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

$checkout_url = Options::get_option( 'checkout-url' );
if (
	// If using WooCommerce.
	( empty( $checkout_url ) && WooCommerceFunctions::is_wc_active() && Options::get_option( 'woocommerce' ) == 'yes' && ! empty( $_REQUEST['order_id'] ) && is_user_logged_in() ) ||

	// Checkout URL is not empty.
	( ! empty( $checkout_url ) )
) {
	// If not using WooCommerce check for endpoint or ajax request.
	if ( ! Utility::has_endpoint_or_ajax() ) {
		return;
	}
}

global $BID, $f2;
$BID = $f2->bid();

if ( ! empty( $_REQUEST['order_id'] ) ) {
	$order_id = intval( $_REQUEST['order_id'] );
	Orders::set_current_order_id( $order_id );
} else {
	$order_id = Orders::get_current_order_id();
}

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id=" . intval( $order_id ) . " AND user_id=" . get_current_user_id();
$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );

// Handle when no order id is found.
if ( mysqli_num_rows( $order_result ) == 0 ) {
	if ( wp_doing_ajax() ) {
		Functions::no_orders();
		wp_die();
	}

	Utility::redirect( Utility::get_page_url( 'no-orders' ) );
}

$order_row = mysqli_fetch_array( $order_result );

// Process confirmation
if ( isset( $_REQUEST['mds-action'] ) && ( ( $_REQUEST['mds-action'] == 'confirm' ) || ( $_REQUEST['mds-action'] == 'complete' ) ) ) {
	// move temp order to confirmed order
	$advanced_order = Config::get( 'USE_AJAX' ) == 'YES';

	if ( ! $advanced_order ) {
		$order_id = reserve_pixels_for_temp_order( $order_row );
	}

	if ( ! empty( $order_id ) ) {

		// check the user's rank
		$privileged = carbon_get_user_meta( get_current_user_id(), 'privileged' );

		if ( ( $order_row['price'] == 0 ) || ( $privileged == '1' ) ) {
			complete_order( get_current_user_id(), $order_id );
		} else {
			confirm_order( get_current_user_id(), $order_id );
		}

	} else {
		if ( wp_doing_ajax() ) {
			Language::out( '<h1>Pixel Reservation Not Yet Completed...</h1>' );
			Language::out_replace( '<p>We are sorry, it looks like you took too long! Either your session has timed out or the pixels we tried to reserve for you were snapped up by someone else in the meantime! Please go <a href="%ORDER_PAGE%">here</a> and try again.</p>', '%ORDER_PAGE%', Utility::get_page_url( 'order' ) );
			wp_die();
		}

		Utility::redirect( Utility::get_page_url( 'no-orders' ) );
	}

	$_REQUEST['order_id'] = $order_id;
}

if ( get_user_meta( get_current_user_id(), 'mds_confirm', true ) ) {
	Steps::update_step( 'payment' );
	\MillionDollarScript\Classes\Payment::handle_checkout();
}
