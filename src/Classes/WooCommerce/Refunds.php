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

namespace MillionDollarScript\Classes\WooCommerce;

use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) || exit;

/**
 * Handles WooCommerce Refund Integration for MDS.
 */
class Refunds {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Add hooks here only if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Add filter for order list actions
		add_filter( 'woocommerce_admin_order_actions', [ __CLASS__, 'add_refund_order_action' ], 10, 2 );

		// Add AJAX handler for the refund action
		add_action( 'wp_ajax_mds_wc_refund_order', [ __CLASS__, 'handle_ajax_refund' ] );

		// Add action hook for post-refund sync
		add_action( 'woocommerce_order_refunded', [ __CLASS__, 'sync_mds_on_refund' ], 10, 2 );
	}

	/**
	 * Add custom refund action to the WooCommerce order list.
	 *
	 * @param array    $actions Existing actions.
	 * @param \WC_Order $order   The order object.
	 * @return array Modified actions.
	 */
	public static function add_refund_order_action( $actions, $order ) {
		// Check if it's an MDS order by looking for the meta key
		$mds_order_id = get_post_meta( $order->get_id(), 'mds_order_id', true );

		// Check if paid and if there is an amount that can be refunded
		if ( ! empty( $mds_order_id ) && $order->is_paid() && $order->get_remaining_refund_amount() > 0 ) {
			// Generate the URL for the refund action (we'll use AJAX)
			$refund_url = wp_nonce_url(
				add_query_arg(
					[
						'action'   => 'mds_wc_refund_order',
						'order_id' => $order->get_id(),
					],
					admin_url( 'admin-ajax.php' )
				),
				'mds_wc_refund_order_nonce',
				'_mds_wc_refund_nonce'
			);

			// Add the action
			$actions['mds_refund'] = [
				'url'    => $refund_url,
				'name'   => Language::get( 'MDS Refund' ),
				'action' => 'mds-refund', // class name for the button
			];
		}

		return $actions;
	}

	/**
	 * Handle the AJAX request to refund an MDS order via WooCommerce.
	 */
	public static function handle_ajax_refund() {
		// Verify nonce
		if ( ! isset( $_REQUEST['_mds_wc_refund_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_mds_wc_refund_nonce'] ), 'mds_wc_refund_order_nonce' ) ) {
			wp_send_json_error( [ 'message' => Language::get( 'Nonce verification failed.' ) ], 403 );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => Language::get( 'You do not have permission to issue refunds.' ) ], 403 );
		}

		// Get Order ID
		$order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;
		if ( ! $order_id ) {
			wp_send_json_error( [ 'message' => Language::get( 'Invalid Order ID.' ) ], 400 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => Language::get( 'Could not retrieve order.' ) ], 404 );
		}

		// Double check if it's an MDS order and eligible
		$mds_order_id = get_post_meta( $order->get_id(), 'mds_order_id', true );
		if ( empty( $mds_order_id ) || ! $order->is_paid() || $order->get_remaining_refund_amount() <= 0 ) {
			wp_send_json_error( [ 'message' => Language::get( 'Order is not eligible for MDS refund via WooCommerce.' ) ], 400 );
		}

		// Process the refund (full refund for now)
		try {
			$refund_amount = $order->get_total(); // Full refund
			$refund_reason = Language::get( 'MDS Refund initiated from order list.' );

			$result = wc_create_refund( [
				'amount'   => $refund_amount,
				'reason'   => $refund_reason,
				'order_id' => $order_id,
				'refund_payment' => true, // Attempt to process refund through gateway
				'restock_items'  => true, // Let the sync handler manage pixel restocking
			] );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
			} else {
				// Refund successful (Note: The 'woocommerce_order_refunded' action will handle MDS data sync)
				wp_send_json_success( [ 'message' => Language::get( 'Refund processed successfully via WooCommerce.' ) ] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}

		// Should not reach here, but just in case
		wp_die();
	}

	/**
	 * Synchronize MDS data when a WooCommerce order is refunded.
	 *
	 * @param int $order_id  The WooCommerce Order ID.
	 * @param int $refund_id The Refund ID.
	 */
	public static function sync_mds_on_refund( $order_id, $refund_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			// Could not get order object
			return;
		}

		// Check if it's an MDS order
		$mds_order_id = get_post_meta( $order_id, 'mds_order_id', true );
		if ( empty( $mds_order_id ) ) {
			return; // Not an MDS order
		}

		// Check if the order is fully refunded
		// We only cancel MDS pixels on a full refund for simplicity
		if ( $order->get_remaining_refund_amount() > 0 ) {
			// Optional: Add note for partial refund?
			//$order->add_order_note( Language::get_replace( 'MDS: Order partially refunded (%s). Pixels not automatically cancelled.', wc_price( $order->get_total_refunded() ) ) );
			return; // Not fully refunded
		}

		// Order is fully refunded, cancel the corresponding MDS order/blocks
		try {
			// Ensure the Orders class is available
			if ( class_exists( '\MillionDollarScript\Classes\Orders\Orders' ) ) {
				\MillionDollarScript\Classes\Orders\Orders::cancel_order( $mds_order_id );

				// Add a system note (not visible to customer, not added by user)
				// Arguments: note, is_customer_note, added_by_user
				$order->add_order_note( Language::get_replace( 'MDS: Corresponding MDS Order %MDS_ORDER_ID% pixels automatically cancelled due to full WooCommerce refund.', '%MDS_ORDER_ID%', $mds_order_id ), false, false );
			} else {
				// Add a private system note (visible to admin only, not added by user)
				// Arguments: note, is_customer_note, added_by_user
				$order->add_order_note( Language::get( 'MDS Error: Could not cancel associated pixels during refund sync - Orders class not found.' ), true, false );
			}
		} catch ( \Exception $e ) {
			// Add a private system note (visible to admin only, not added by user)
			// Arguments: note, is_customer_note, added_by_user
			$order->add_order_note( Language::get_replace( 'MDS Error: Exception during pixel cancellation sync: %ERROR_MESSAGE%', '%ERROR_MESSAGE%', $e->getMessage() ), true, false );
		}
	}

	// TODO: Implement methods for syncing data.

}
