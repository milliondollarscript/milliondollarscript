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
require_once __DIR__ . "/../include/init.php";

require_once BASE_PATH . "/include/ads.inc.php";

global $f2, $label;

$BID = $f2->bid();

$sql = "select * from " . MDS_DB_PREFIX . "temp_orders where session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], get_current_order_id() ) . "' ";
$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

if ( mysqli_num_rows( $order_result ) == 0 ) {
	require_once BASE_PATH . "/html/header.php";

	if ( USE_AJAX == 'SIMPLE' ) {
		$order_page = 'order_pixels.php';
	} else {
		$order_page = 'select.php';
	}

	?>
    <h1><?php echo $label['no_order_in_progress']; ?></h1>
    <p><?php echo $label['no_order_in_progress_go_here'] = str_replace( '%ORDER_PAGE%', $order_page, $label['no_order_in_progress_go_here'] ); //echo $label['no_order_in_progress_go_here']; ?></p>
	<?php

	require_once BASE_PATH . "/html/footer.php";
	die();
}

$row = mysqli_fetch_array( $order_result );

update_temp_order_timestamp();

$has_packages = banner_get_packages( $BID );

function display_ad_intro() {
	global $label;
	?>
    <p>
		<?php
		show_nav_status( 2 );
		?>
    </p>
    <h3><?php echo $label['write_ad_instructions']; ?></h3>
	<?php
	if ( session_valid_id( session_id() ) ) {
		$_REQUEST['user_id'] = $user_id = session_id();
	} else {
		echo "Sorry there was an error with your session.";
		die;
	}
}

require_once BASE_PATH . "/html/header.php";

// saving
if ( isset( $_REQUEST['save'] ) && $_REQUEST['save'] != "" ) {

	$error = validate_ad_data( 1 );
	if ( $error != '' ) { // we have an error
		require_once BASE_PATH . "/html/header.php";
		$mode = "user";
		display_ad_intro();
		display_ad_form( 1, $mode, '' );
	} else {
		$ad_id = intval( insert_ad_data() );

		// save ad_id with the temp order...

		$sql = "UPDATE " . MDS_DB_PREFIX . "temp_orders SET ad_id='$ad_id' where session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], get_current_order_id() ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		header( "Location: " . BASE_HTTP_PATH . "users/confirm_order.php" );
		exit;
	}
} else {
	display_ad_intro();

	// get the ad_id form the temp_orders table..

	$sql = "SELECT ad_id FROM " . MDS_DB_PREFIX . "temp_orders WHERE session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], get_current_order_id() ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$row   = mysqli_fetch_array( $result );
	$ad_id = $row['ad_id'];

	// user is not logged in
	$prams = load_ad_values( $ad_id );

	display_ad_form( 1, 'user', $prams );
}

require_once BASE_PATH . "/html/footer.php";
