<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.4
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

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

global $BID, $f2;
$BID = $f2->bid();

$sql = "select * from " . MDS_DB_PREFIX . "banners where banner_id='$BID'";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
$b_row = mysqli_fetch_array( $result );
if ( $_REQUEST['order_id'] != '' ) {
	$order_id = $_REQUEST['order_id'];
} else {
	$order_id = get_current_order_id();
}

$sql = "SELECT * from " . MDS_DB_PREFIX . "orders where order_id=" . intval( $order_id );
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
$order_row = mysqli_fetch_array( $result );

// Process confirmation
if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'confirm' ) {
	// move temp order to confirmed order
	confirm_order( get_current_user_id(), $order_id );
}

\MillionDollarScript\Classes\Payment::handle_checkout();
