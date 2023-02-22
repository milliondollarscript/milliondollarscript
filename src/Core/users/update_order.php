<?php
/*
 * @package       mds
 * @copyright     (C) Copyright 2022 Ryan Rhode, All rights reserved.
 * @author        Ryan Rhode, ryan@milliondollarscript.com
 * @version       2022-01-30 17:07:25 EST
 * @license       This program is free software; you can redistribute it and/or modify
 *        it under the terms of the GNU General Public License as published by
 *        the Free Software Foundation; either version 3 of the License, or
 *        (at your option) any later version.
 *
 *        This program is distributed in the hope that it will be useful,
 *        but WITHOUT ANY WARRANTY; without even the implied warranty of
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *        GNU General Public License for more details.
 *
 *        You should have received a copy of the GNU General Public License along
 *        with this program;  If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 *
 *  * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *        Million Dollar Script
 *        A pixel script for selling pixels on your website.
 *
 *        For instructions see README.txt
 *
 *        Visit our website for FAQs, documentation, a list team members,
 *        to post any bugs or feature requests, and a community forum:
 *        https://milliondollarscript.com/
 *
 */

require_once __DIR__ . "/../include/login_functions.php";
mds_start_session();
define( 'NO_HOUSE_KEEP', 'YES' );

header( "Cache-Control: no-cache, must-revalidate" ); // HTTP/1.1
header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" );   // Date in the past

require_once __DIR__ . "/../include/init.php";

if ( isset( $_REQUEST['block_id'] ) ) {
	$block_id = intval( $_REQUEST['block_id'] );
} else {
	// possibly clicked reset or didn't pass a block_id
	$block_id = - 1;
}

global $f2, $banner_data, $label;

$BID           = $f2->bid();
$output_result = "";

if ( ! is_user_logged_in() ) {
	echo json_encode( [
		"error" => "true",
		"type"  => "error",
		"data"  => [
			"value" => $label['not_logged_in'],
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
					"value" => $label['not_logged_in'],
				]
			] );
			die();
		}
	}
} else {
	$user_id = intval( $_SESSION['MDS_ID'] );
}

if ( ! can_user_order( $banner_data, $_SESSION['MDS_ID'] ) ) {
	$max_orders = true;
	echo json_encode( [
		"error" => "true",
		"type"  => "max_orders",
		"data"  => [
			"value" => $label['advertiser_max_order'],
		]
	] );
	die();
}

// reset blocks
if ( isset( $_REQUEST['reset'] ) && $_REQUEST['reset'] == "true" ) {
	$sql = "delete from " . MDS_DB_PREFIX . "blocks where user_id='" . intval( $_SESSION['MDS_ID'] ) . "' AND status = 'reserved' AND banner_id='" . intval( $BID ) . "' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );

	if ( isset( $_SESSION['MDS_order_id'] ) ) {
		$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET blocks = '' WHERE order_id=" . intval( $_SESSION['MDS_order_id'] );
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
