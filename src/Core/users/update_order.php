<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.2
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

use MillionDollarScript\Classes\Language;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

header( "Cache-Control: no-cache, must-revalidate" ); // HTTP/1.1
header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" );   // Date in the past

check_ajax_referer( 'mds-select' );

if ( isset( $_REQUEST['block_id'] ) ) {
	$block_id = intval( $_REQUEST['block_id'] );
} else {
	// possibly clicked reset or didn't pass a block_id
	$block_id = - 1;
}

global $BID, $f2, $banner_data;

$BID           = $f2->bid();
$output_result = "";

if ( ! is_user_logged_in() ) {
	echo json_encode( [
		"error" => "true",
		"type"  => "error",
		"data"  => [
			"value" => Language::get( 'You are not logged in. Please log in or sign up for a new account. The pixels that you have placed on order are saved and will be available once you log in.' ),
		]
	] );
	die();
}

$banner_data = load_banner_constants( $BID );

if ( ! is_numeric( $BID ) ) {
	die();
}

if ( isset( $_REQUEST['user_id'] ) && ! empty( $_REQUEST['user_id'] ) ) {
	$user_id = intval( $_REQUEST['user_id'] );
	if ( ! is_numeric( $_REQUEST['user_id'] ) ) {
		// Sometimes the user ID can be NaN in some cases so let's check WP user
		// For example if MDS was just installed and user hasn't been logged out and logged in
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
		} else {
			echo json_encode( [
				"error" => "true",
				"type"  => "error",
				"data"  => [
					"value" => Language::get( 'You are not logged in. Please log in or sign up for a new account. The pixels that you have placed on order are saved and will be available once you log in.' ),
				]
			] );
			die();
		}
	}
} else {
	$user_id = get_current_user_id();
}

if ( ! can_user_order( $banner_data, get_current_user_id() ) ) {
	$max_orders = true;
	echo json_encode( [
		"error" => "true",
		"type"  => "max_orders",
		"data"  => [
			"value" => Language::get( 'Cannot place pixels on order. You have reached the order limit for this grid. Please review your Order History.' ),
		]
	] );
	die();
}

// reset blocks
if ( isset( $_REQUEST['reset'] ) && $_REQUEST['reset'] == "true" ) {
	$sql = "delete from " . MDS_DB_PREFIX . "blocks where user_id='" . get_current_user_id() . "' AND status = 'reserved' AND banner_id='" . intval( $BID ) . "' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );

	$order_id = get_current_order_id();

	if ( ! empty( $order_id ) ) {
		$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET blocks = '' WHERE order_id=" . intval( $order_id );
		mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
	}

	echo json_encode( [
		"error" => "false",
		"type"  => "removed",
		"data"  => [
			"value" => "removed",
		]
	] );
	die();
}

$output_result = select_block( '', '', $block_id );

echo json_encode( $output_result );
