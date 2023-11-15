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

use MillionDollarScript\Classes\Functions;
use MillionDollarScript\Classes\Language;
use MillionDollarScript\Classes\Utility;

defined( 'ABSPATH' ) or exit;

@set_time_limit( 260 );

mds_wp_login_check();

if ( isset( $_REQUEST['order_id'] ) ) {
	set_current_order_id( $_REQUEST['order_id'] );
}
$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id='" . intval( get_current_order_id() ) . "' ";
$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

if ( mysqli_num_rows( $order_result ) == 0 ) {
	// no order id found...
	if ( wp_doing_ajax() ) {
		Functions::no_orders();
		wp_die();
	}

	Utility::redirect( Utility::get_page_url( 'no-orders' ) );
}

$order_row = mysqli_fetch_array( $order_result );

if ( isset( $_REQUEST['mds-action'] ) && ( ( $_REQUEST['mds-action'] == 'confirm' ) || ( $_REQUEST['mds-action'] == 'complete' ) ) ) {

	// move temp order to confirmed order

	if ( $order_id = reserve_pixels_for_temp_order( $order_row ) ) {

		// check the user's rank
		$privileged = carbon_get_user_meta( get_current_user_id(), 'privileged' );

		if ( ( $order_row['price'] == 0 ) || ( $privileged == '1' ) ) {
			complete_order( get_current_user_id(), $order_id );
		} else {
			confirm_order( get_current_user_id(), $order_id );
		}
	} else {
		// we have a problem...
		require_once MDS_CORE_PATH . "html/header.php";

		Language::out( '<h1>Pixel Reservation Not Yet Completed...</h1>' );
		Language::out_replace( '<p>We are sorry, it looks like you took too long! Either your session has timed out or the pixels we tried to reserve for you were snapped up by someone else in the meantime! Please go <a href="%ORDER_PAGE%">here</a> and try again.</p>', '%ORDER_PAGE%', Utility::get_page_url( 'order' ) );

		require_once MDS_CORE_PATH . "html/footer.php";
		die();
	}

	$_REQUEST['order_id'] = $order_id;
} else {
	$order_id = $_REQUEST['order_id'] ?? get_current_order_id();
}

if ( get_user_meta( get_current_user_id(), 'mds_confirm', true ) ) {
	delete_user_meta( get_current_user_id(), 'mds_confirm' );
	\MillionDollarScript\Classes\Payment::handle_checkout();
}
