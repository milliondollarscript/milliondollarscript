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

		// Add action hook for post-refund sync
		add_action( 'woocommerce_order_refunded', [ __CLASS__, 'sync_mds_on_refund' ], 10, 2 );
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
			//$order->add_order_note( Language::get_replace( 'MDS: Order partially refunded (%TOTAL_REFUNDED%). Pixels not automatically cancelled.', '%TOTAL_REFUNDED%', wc_price( $order->get_total_refunded() ) ) );
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
