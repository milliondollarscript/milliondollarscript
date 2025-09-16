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

use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Forms\FormFields;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\Orders\Steps;
use MillionDollarScript\Classes\System\Functions;
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

require_once MDS_CORE_PATH . "include/ads.inc.php";

global $f2, $BID;

$BID = $f2->bid();

$advanced_order = Options::get_option( 'use-ajax' ) == 'YES';

if ( ! isset( $_REQUEST['manage-pixels'] ) ) {

	global $wpdb;

	$sql = $wpdb->prepare(
		"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d",
		intval( Orders::get_current_order_id() )
	);

	$order_result = $wpdb->get_results( $sql );

	if ( count( $order_result ) == 0 ) {
		if ( wp_doing_ajax() ) {
			Orders::no_orders();
			wp_die();
		}

		Utility::redirect( Utility::get_page_url( 'no-orders' ) );
	}

	$row = $order_result[0];

	if ( Options::get_option( 'use-ajax' ) == 'SIMPLE' ) {
		Orders::update_temp_order_timestamp();
	}

	function display_ad_intro() {

		Utility::show_nav_status( 2 );

		Language::out( '<h3>Please write your advertisement and click "Save Ad" when done.</h3>' );

		if ( is_user_logged_in() ) {
			$_REQUEST['user_id'] = get_current_user_id();
		} else {
			Language::out( 'Sorry there was an error with your session.' );
			die;
		}
	}
}

// The Save Ad action is handled via admin-post (mds_form_submission)
// This template only renders the Write Your Ad UI and must not process saving here.

// Guard: if a POST routed here (unexpected), bail to avoid header/output conflicts
if ( isset( $_POST['mds_dest'] ) && $_POST['mds_dest'] == 'write-ad' && isset( $_POST['action'] ) && $_POST['action'] == 'mds_form_submission' ) {
	return;
}

require_once MDS_CORE_PATH . "html/header.php";
display_ad_intro();

if ( ! empty( $_REQUEST['aid'] ) ) {
	// Get the desired MDS Pixels post owned by the current user
	$pixels = get_posts( [
		'post_type'   => \MillionDollarScript\Classes\Forms\FormFields::$post_type,
		'post_status' => 'any',
		'p'           => intval( $_REQUEST['aid'] ),
		'author'      => get_current_user_id(),
	] );

	if ( ! empty( $pixels ) ) {
		$ad_id    = $pixels[0]->ID;
		$order_id = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'order' );
		Orders::set_current_order_id( $order_id );
	}
}

if ( empty( $order_id ) ) {
	$order_id = Orders::get_current_order_id();
}

// get the ad_id form the temp_orders table.
if ( empty( $ad_id ) ) {
	global $wpdb;

	$ad_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ad_id FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d",
			intval( $order_id )
		)
	);
}

// user is not logged in
$prams = load_ad_values( $ad_id );

display_ad_form( 1, 'user', $prams, Utility::get_page_url( 'write-ad' ) );

require_once MDS_CORE_PATH . "html/footer.php";
