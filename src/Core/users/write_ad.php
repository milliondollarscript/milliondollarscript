<?php
/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2024, Ryan Rhode
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

use MillionDollarScript\Classes\Data\Config;
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

$advanced_order = Config::get( 'USE_AJAX' ) == 'YES';

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

	if ( Config::get( 'USE_AJAX' ) == 'SIMPLE' ) {
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

// saving
if ( isset( $_REQUEST['save'] ) && $_REQUEST['save'] != "" ) {
	Functions::verify_nonce( 'mds-form' );
	$user_id = get_current_user_id();

	if ( \MillionDollarScript\Classes\Data\Options::get_option( 'order-locking', false ) ) {
		// Order locking is enabled so check if the order is approved or completed before allowing the user to save the ad.
		$is_error = false;
		if ( isset( $_REQUEST['manage-pixels'] ) && isset( $_REQUEST['order_id'] ) ) {
			// This is a request to manage pixels, so we need to check the order status.

			$order_id = intval( $_REQUEST['order_id'] );
			if ( empty( $order_id ) ) {
				$is_error = true;
			} else {
				global $wpdb;
				$sql = $wpdb->prepare(
					"SELECT status FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%d AND user_id=%d",
					$order_id,
					$user_id
				);

				$status            = $wpdb->get_var( $sql );
				$completion_status = Orders::get_completion_status( $order_id, $user_id );
				if ( ! $completion_status && $status !== 'denied' ) {
					$is_error = true;
				}
			}
		}

		if ( $is_error ) {
			// User should never get here, so we will just redirect them to the manage page.
			Utility::redirect( Utility::get_page_url( 'manage' ) );
		}
	}

	// Verify order id
	if ( isset( $_REQUEST['manage-pixels'] ) && isset( $_REQUEST['order_id'] ) ) {
		$order_id = intval( $_REQUEST['order_id'] );
		if ( empty( $order_id ) || ! Orders::is_owned_by( $order_id ) ) {
			Utility::redirect( Utility::get_page_url( 'manage' ) );
		}
	}

	$ad_id = FormFields::add();

	if ( ! is_int( $ad_id ) ) {
		// we have an error
		update_user_meta( $user_id, 'error_message', $ad_id );

		return;
	} else {
		// save ad_id with the temp order...

		$order_id = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'order' );

		global $wpdb;

		$update = $wpdb->update(
			MDS_DB_PREFIX . 'orders',
			array( 'ad_id' => intval( $ad_id ) ),
			array( 'order_id' => intval( $order_id ) ),
			array( '%d' ),
			array( '%d' )
		);

		$sql = $wpdb->prepare(
			"SELECT status FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%d AND user_id=%d",
			$order_id,
			$user_id
		);

		$status = $wpdb->get_var( $sql );
		if ( $status == 'denied' ) {
			Orders::pend_order( $order_id );
			Orders::reset_order_progress();
		}

		if ( isset( $_REQUEST['manage-pixels'] ) ) {
			Utility::redirect( Utility::get_page_url( 'manage' ) );
		}

		Steps::update_step( 'confirm_order' );

		Utility::redirect( Utility::get_page_url( 'confirm-order' ) );
	}
} else {

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
}

require_once MDS_CORE_PATH . "html/footer.php";
