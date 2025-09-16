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
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\Orders\Steps;
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

//if ( ! Utility::has_endpoint_or_ajax() ) {
//	return;
//}

global $BID, $f2, $wpdb;
$BID = $f2->bid();

if ( ! empty( $_REQUEST['order_id'] ) ) {
	$order_id = intval( $_REQUEST['order_id'] );
} else {
	$order_id = Orders::get_current_order_id();
}

// Ensure current order context is set and in progress for payment flow
Orders::ensure_current_order_context( $order_id, isset($_REQUEST['aid']) ? intval($_REQUEST['aid']) : null, true );

$sql = $wpdb->prepare(
	"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%d AND user_id=%d",
	intval( $order_id ),
	get_current_user_id()
);
$order_row = $wpdb->get_row( $sql, ARRAY_A );

// Handle when no order id is found.
if ( ! $order_row ) {
	if ( wp_doing_ajax() ) {
		Orders::no_orders();
		wp_die();
	}

	Utility::redirect( Utility::get_page_url( 'no-orders' ) );
}

// Check for database errors
if ( $wpdb->last_error ) {
	wp_die( esc_html( $wpdb->last_error ) );
}

// Process confirmation
if ( isset( $_REQUEST['mds-action'] ) && ( ( $_REQUEST['mds-action'] == 'confirm' ) || ( $_REQUEST['mds-action'] == 'complete' ) ) ) {
	// move temp order to confirmed order
	$advanced_order = Options::get_option( 'use-ajax' ) == 'YES';

	if ( ! $advanced_order ) {
		$order_id = Orders::reserve_pixels_for_temp_order( $order_row );
	}

	if ( ! empty( $order_id ) ) {

		// check the user's rank
		$privileged = carbon_get_user_meta( get_current_user_id(), MDS_PREFIX . 'privileged' );

		if ( ( $order_row['price'] == 0 ) || ( $privileged == '1' ) ) {
			// Privileged or free order: complete immediately and go to Thank You
			Orders::complete_order( get_current_user_id(), $order_id );
			Orders::reset_order_progress();

			// 1) Custom override URL if provided in Options
			$custom_ty = \MillionDollarScript\Classes\Data\Options::get_option( 'thank-you-page', '' );
			if ( ! empty( $custom_ty ) ) {
				$custom_ty_url = esc_url_raw( (string) $custom_ty );
				if ( ! empty( $custom_ty_url ) ) {
					\MillionDollarScript\Classes\System\Utility::redirect( $custom_ty_url );
					exit;
				}
			}

			// 2) Fall back to MDS Thank You page
			$thank_you_url = \MillionDollarScript\Classes\System\Utility::get_page_url( 'thank-you' );
			if ( ! empty( $thank_you_url ) ) {
				\MillionDollarScript\Classes\System\Utility::redirect( $thank_you_url );
				exit;
			}

			// 3) Last resort: show a generic message with a Manage link
			$message  = \MillionDollarScript\Classes\Language\Language::get( '<h3>Your order was completed.</h3>' );
			$manage   = \MillionDollarScript\Classes\System\Utility::get_page_url( 'manage' );
			$message .= \MillionDollarScript\Classes\Language\Language::get_replace( '<p>You can manage your pixels under <a href="%MANAGE_URL%">Manage Pixels</a>.</p>', '%MANAGE_URL%', $manage ?: home_url( '/' ) );
			echo $message;
			return;
		} else {
			error_log( '[MDS DEBUG] users/payment.php confirm branch for order ' . $order_id );
			Orders::confirm_order( get_current_user_id(), $order_id );
			// After confirming, let the payment flow continue below
		}

	} else {
		if ( wp_doing_ajax() ) {
			$message = Language::get( '<h1>Pixel Reservation Not Yet Completed...</h1>' );
			$message .= Language::get_replace( '<p>We are sorry, it looks like you took too long! Either your session has timed out or the pixels we tried to reserve for you were snapped up by someone else in the meantime! Please go <a href="%ORDER_PAGE%">here</a> and try again.</p>', '%ORDER_PAGE%', Utility::get_page_url( 'order' ) );
			Utility::output_message( [
				'message' => $message,
			] );
		}

		Utility::redirect( Utility::get_page_url( 'no-orders' ) );
	}

	$_REQUEST['order_id'] = $order_id;

}

if ( get_user_meta( get_current_user_id(), 'mds_confirm', true ) || $order_row['status'] == 'confirmed' ) {
	Steps::update_step( 'payment' );
	$_REQUEST['order_id'] = $order_id;
	
	// Reset order progress now that payment is starting - gives users a clean slate
	// while preserving the current confirmed order for payment processing
	Orders::reset_order_progress();
	
	// For advanced mode (use-ajax == YES), ensure order status is set to pending when reaching payment screen
	// This ensures consistency with simple mode behavior
	$advanced_order = Options::get_option( 'use-ajax' ) == 'YES';
	if ( $advanced_order && $order_row['status'] == 'confirmed' ) {
		Orders::pend_order( $order_id );
	}
	
	\MillionDollarScript\Classes\Payment\Payment::handle_checkout( $order_id );
} else {
	if ( isset( $_REQUEST['renew'] ) && $_REQUEST['renew'] === 'true' ) {
		$order = Orders::get_order( $order_id );
		if ( in_array( $order->status, [ 'expired', 'renew_wait' ] ) ) {
			$_REQUEST['order_id'] = $order_id;
			\MillionDollarScript\Classes\Payment\Payment::handle_checkout( $order_id );
		}
	}
}
