<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.3
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
use MillionDollarScript\Classes\FormFields;
use MillionDollarScript\Classes\Functions;
use MillionDollarScript\Classes\Language;
use MillionDollarScript\Classes\Utility;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

require_once MDS_CORE_PATH . "include/ads.inc.php";

global $f2;

$BID = $f2->bid();

$advanced_order = Config::get( 'USE_AJAX' ) == 'YES';

$sql = "select * from " . MDS_DB_PREFIX . "orders where order_id='" . mysqli_real_escape_string( $GLOBALS['connection'], get_current_order_id() ) . "' ";

$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
if ( mysqli_num_rows( $order_result ) == 0 ) {
	Utility::redirect( Utility::get_page_url( 'no-orders' ) );
}

$row = mysqli_fetch_array( $order_result );

if ( Config::get( 'USE_AJAX' ) == 'SIMPLE' ) {
	update_temp_order_timestamp();
}

$has_packages = banner_get_packages( $BID );

function display_ad_intro() {

	show_nav_status( 2 );

	Language::out( '<h3>Please write your advertisement and click "Save Ad" when done.</h3>' );

	if ( is_user_logged_in() ) {
		$_REQUEST['user_id'] = get_current_user_id();
	} else {
		Language::out( 'Sorry there was an error with your session.' );
		die;
	}
}

// saving
if ( isset( $_REQUEST['save'] ) && $_REQUEST['save'] != "" ) {
	Functions::verify_nonce( 'mds-form' );

	$ad_id = FormFields::add();

	if ( is_a( $ad_id, 'WP_Error' ) ) {
		// we have an error
		require_once MDS_CORE_PATH . "html/header.php";
		$mode = "user";
		display_ad_intro();
		display_ad_form( 1, $mode, '', Utility::get_page_url( 'write-ad' ) );
	} else {
		// save ad_id with the temp order...
		$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET ad_id='$ad_id' WHERE order_id='" . intval( get_current_order_id() ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		header( "Location: " . Utility::get_page_url( 'confirm-order' ) );
		exit;
	}
} else {
	require_once MDS_CORE_PATH . "html/header.php";
	display_ad_intro();

	// get the ad_id form the temp_orders table.
	$sql = "SELECT ad_id FROM " . MDS_DB_PREFIX . "orders where order_id='" . mysqli_real_escape_string( $GLOBALS['connection'], get_current_order_id() ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$row   = mysqli_fetch_array( $result );
	$ad_id = $row['ad_id'];

	// user is not logged in
	$prams = load_ad_values( $ad_id );

	display_ad_form( 1, 'user', $prams, Utility::get_page_url( 'write-ad' ) );
}

require_once MDS_CORE_PATH . "html/footer.php";
