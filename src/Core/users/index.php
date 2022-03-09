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

process_login();

// check if user has permission to access this page
if ( ! mds_check_permission( "mds_my_account" ) ) {
	require_once BASE_PATH . "/html/header.php";
	_e( "No Access", 'milliondollarscript' );
	require_once BASE_PATH . "/html/footer.php";
	exit;
}

require_once BASE_PATH . "/html/header.php";

global $f2, $label;
$BID = $f2->bid();
$sql = "SELECT grid_width,grid_height, block_width, block_height, bgcolor, time_stamp FROM " . MDS_DB_PREFIX . "banners WHERE (banner_id = '$BID')";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
$b_row = mysqli_fetch_array( $result );

if ( ! $b_row['block_width'] ) {
	$b_row['block_width'] = 10;
}
if ( ! $b_row['block_height'] ) {
	$b_row['block_height'] = 10;
}

$sql = "select block_id from " . MDS_DB_PREFIX . "blocks where user_id='" . intval( $_SESSION['MDS_ID'] ) . "' and status='sold' ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
$pixels = mysqli_num_rows( $result ) * ( $b_row['block_width'] * $b_row['block_height'] );

$sql = "select block_id from " . MDS_DB_PREFIX . "blocks where user_id='" . intval( $_SESSION['MDS_ID'] ) . "' and status='ordered' ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
$ordered = mysqli_num_rows( $result ) * ( $b_row['block_width'] * $b_row['block_height'] );

$sql = "select * from " . MDS_DB_PREFIX . "users where ID='" . intval( $_SESSION['MDS_ID'] ) . "' ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
$user_row = mysqli_fetch_array( $result );

if ( $user_row == null ) {
	// user might not be logged in correctly so log them out
	require_once MDS_BASE_PATH . 'src/Core/users/wplogout.php';
}

?>
<h3><?php echo $label['advertiser_home_welcome']; ?></h3>
<p>
	<?php echo $label['advertiser_home_line2'] . "<br>"; ?>
<p>
<p>
	<?php
	$label['advertiser_home_blkyouown'] = str_replace( "%PIXEL_COUNT%", $pixels, $label['advertiser_home_blkyouown'] );
	$label['advertiser_home_blkyouown'] = str_replace( "%BLOCK_COUNT%", ( $pixels / ( $b_row['block_width'] * $b_row['block_height'] ) ), $label['advertiser_home_blkyouown'] );
	echo $label['advertiser_home_blkyouown'] . "<br>";

	$label['advertiser_home_blkonorder'] = str_replace( "%PIXEL_ORD_COUNT%", $ordered, $label['advertiser_home_blkonorder'] );
	$label['advertiser_home_blkonorder'] = str_replace( "%BLOCK_ORD_COUNT%", ( $ordered / ( $b_row['block_width'] * $b_row['block_height'] ) ), $label['advertiser_home_blkonorder'] );

	if ( USE_AJAX == 'SIMPLE' ) {
		$label['advertiser_home_blkonorder'] = str_replace( 'select.php', 'order_pixels.php', $label['advertiser_home_blkonorder'] );
	}
	echo $label['advertiser_home_blkonorder'] . "<br>";

	$label['advertiser_home_click_count'] = str_replace( "%CLICK_COUNT%", number_format( $user_row['click_count'] ), $label['advertiser_home_click_count'] );
	echo $label['advertiser_home_click_count'] . "<br>";

	$label['advertiser_home_view_count'] = str_replace( "%VIEW_COUNT%", number_format( $user_row['view_count'] ), $label['advertiser_home_view_count'] );
	echo $label['advertiser_home_view_count'] . "<br>";
	?>
</p>

<h3><?php echo $label['advertiser_home_sub_head']; ?></h3>
<p>
	<?php

	if ( USE_AJAX == 'SIMPLE' ) {
		$label['advertiser_home_selectlink'] = str_replace( 'select.php', 'order_pixels.php', $label['advertiser_home_selectlink'] );
	}
	if ( WP_ENABLED == 'YES' ) {
		$label['advertiser_home_editlink'] = str_replace( [ 'edit.php', 'href' ], [ \MillionDollarScript\Classes\Options::get_option( 'account-page' ), 'target=\'_top\' href' ], $label['advertiser_home_editlink'] );
	}

	echo $label['advertiser_home_selectlink']; ?><br>
	<?php echo $label['advertiser_home_managelink']; ?><br>
	<?php echo $label['advertiser_home_ordlink']; ?><br>
	<?php echo $label['advertiser_home_editlink']; ?><br>
</p>
<p>
	<?php echo $label['advertiser_home_quest']; ?>
</p>
<?php require_once BASE_PATH . "/html/footer.php"; ?>
