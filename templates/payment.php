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

defined( 'ABSPATH' ) or exit;

use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Utility;

// Check for duplicate payment BEFORE any output to allow clean redirects
mds_wp_login_check();

if ( ! empty( $_REQUEST['order_id'] ) ) {
	$order_id = intval( $_REQUEST['order_id'] );
	Orders::set_current_order_id( $order_id );
} else {
	$order_id = Orders::get_current_order_id();
}

if ( $order_id ) {
	global $wpdb;
	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id=" . intval( $order_id ) . " AND user_id=" . get_current_user_id();
	$order_result = mysqli_query( $GLOBALS['connection'], $sql );
	
	if ( mysqli_num_rows( $order_result ) > 0 ) {
		$order_row = mysqli_fetch_array( $order_result );
		
		// Check for duplicate payment before any output
		if ( get_user_meta( get_current_user_id(), 'mds_confirm', true ) || $order_row['status'] == 'confirmed' ) {
			if ( Orders::has_payment( $order_id ) ) {
				// Payment already exists - redirect immediately before any output
				Utility::redirect( Utility::get_page_url( 'manage', [ 'payment_error' => 'already_paid' ] ) );
				exit;
			}
		}
	}
}

// Start output buffering (allows for redirects)
ob_start();
$ob_level = ob_get_level();

\MillionDollarScript\Classes\System\Utility::get_header();

require_once MDS_CORE_PATH . 'users/payment.php';

\MillionDollarScript\Classes\System\Utility::get_footer();

// End output buffering
while ( ob_get_level() > $ob_level ) {
	ob_end_flush();
}