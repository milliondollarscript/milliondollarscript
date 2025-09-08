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

use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Blocks;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Debug;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

try {
	// Robust nonce verification
	$nonce = $_REQUEST['_wpnonce'] ?? ($_REQUEST['NONCE'] ?? ($_REQUEST['mds_nonce'] ?? ''));
	$nonce_valid = ( ! empty( $nonce ) ) && ( wp_verify_nonce( $nonce, 'mds-select' ) || wp_verify_nonce( $nonce, 'mds-order' ) );

	if ( ! $nonce_valid ) {
		wp_send_json_error( [
			"error" => "true",
			"type"  => "security",
			"data"  => [
				"value" => Language::get( 'Security check failed. Please reload the page and try again.' ),
			]
		], 403 );
	}

	// Normalize incoming parameters
	$block_id = isset( $_REQUEST['block_id'] ) ? intval( $_REQUEST['block_id'] ) : -1;

	global $BID, $f2, $banner_data;

	// Determine BID
	if ( isset( $_REQUEST['BID'] ) && is_numeric( $_REQUEST['BID'] ) ) {
		$BID = intval( $_REQUEST['BID'] );
	} else {
		$BID = ( isset( $f2 ) && is_object( $f2 ) && method_exists( $f2, 'bid' ) ) ? intval( $f2->bid() ) : 0;
	}
	$output_result = "";

	// Check if user is logged in
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( [
			"error" => "true",
			"type"  => "error",
			"data"  => [
				"value" => Language::get( 'You are not logged in. Please log in or sign up for a new account. The pixels that you have placed on order are saved and will be available once you log in.' ),
			]
		] );
	}

	// Validate BID
	if ( ! is_numeric( $BID ) || $BID <= 0 ) {
		wp_send_json_error( [
			"error" => "true",
			"type"  => "invalid_grid",
			"data"  => [
				"value" => Language::get( 'Invalid grid.' ),
			]
		], 400 );
	}

	// Load banner data
	$banner_data = load_banner_constants( $BID );
	if ( empty( $banner_data ) ) {
		wp_send_json_error( [
			"error" => "true",
			"type"  => "invalid_grid",
			"data"  => [
				"value" => Language::get( 'Invalid grid configuration.' ),
			]
		], 400 );
	}

	// Get user ID
	if ( is_user_logged_in() ) {
		$user_id = get_current_user_id();
	} else {
		wp_send_json_error( [
			"error" => "true",
			"type"  => "error",
			"data"  => [
				"value" => Language::get( 'You are not logged in. Please log in or sign up for a new account.' ),
			]
		] );
	}

	// Check user order permissions
	$can_order = Orders::can_user_order( $banner_data, get_current_user_id() );
	if ( ! $can_order ) {
		wp_send_json_error( [
			"error" => "true",
			"type"  => "max_orders",
			"data"  => [
				"value" => Language::get( 'Cannot place pixels on order. You have reached the order limit for this grid. Please review your orders under Manage Pixels.' ),
			]
		] );
	}

	// Handle reset blocks
	$reset_flag = $_REQUEST['reset'] ?? ($_REQUEST['erase_all'] ?? false);
	$do_reset = ($reset_flag === true) || ($reset_flag === 'true') || ($reset_flag === '1') || ($reset_flag === 1);

	if ( $do_reset ) {
		global $wpdb;

		$order_id = Orders::get_current_order_id();

		if ( ! empty( $order_id ) ) {
			$sql = $wpdb->prepare( "SELECT blocks FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%d", $order_id );
			$row = $wpdb->get_row( $sql, ARRAY_A );

			if ( isset( $row['blocks'] ) ) {
				$wpdb->update(
					MDS_DB_PREFIX . 'orders',
					[ 'blocks' => '' ],
					[ 'order_id' => $order_id ]
				);

				$current_user_id = get_current_user_id();
				$wpdb->delete(
					MDS_DB_PREFIX . 'blocks',
					[
						'user_id'   => $current_user_id,
						'banner_id' => intval( $BID ),
						'order_id'  => $order_id
					]
				);

				Orders::set_last_order_modification_time();
			}

		} else {
			$user_id = get_current_user_id();
			$wpdb->delete(
				MDS_DB_PREFIX . "blocks",
				[
					"user_id"   => $user_id,
					"status"    => 'reserved',
					"banner_id" => $BID
				],
				[
					"%d",
					"%s",
					"%d"
				]
			);
			Orders::set_last_order_modification_time();
		}

		wp_send_json_success( [
			"error" => "false",
			"type"  => "removed",
			"data"  => [
				"value" => "removed",
			]
		], 200 );
	}

	// Get blocks to add and remove from the request
	$blocks_to_add = [];
	if ( isset( $_REQUEST['blocks_to_add'] ) && $_REQUEST['blocks_to_add'] !== '' ) {
		$raw = $_REQUEST['blocks_to_add'];
		$blocks_to_add = is_array( $raw ) ? array_map( 'intval', $raw ) : array_map( 'intval', explode( ',', $raw ) );
	}

	$blocks_to_remove = [];
	if ( isset( $_REQUEST['blocks_to_remove'] ) && $_REQUEST['blocks_to_remove'] !== '' ) {
		$raw = $_REQUEST['blocks_to_remove'];
		$blocks_to_remove = is_array( $raw ) ? array_map( 'intval', $raw ) : array_map( 'intval', explode( ',', $raw ) );
	}

	$action = $_REQUEST['action'] ?? 'select';

	// Mark this request as an in-progress selection phase: adjacency/min checks should be deferred
	$GLOBALS['MDS_SELECTION_PHASE'] = true;
	
	switch ( $action ) {
		case 'invert':
			$selection = array_unique( array_merge( $blocks_to_add, $blocks_to_remove ) );
			$output_result = Blocks::invert_selection( $banner_data, $user_id, $selection );
			break;
		case 'remove':
			$added_blocks = [];
			$output_result = Blocks::select_block( $banner_data, $user_id, $added_blocks, $blocks_to_remove );
			break;
		case 'select':
		default:
			$output_result = Blocks::select_block( $banner_data, $user_id, $blocks_to_add, $blocks_to_remove );
			break;
	}

	// End selection phase flag (not strictly necessary to unset, but keep code clean)
	unset( $GLOBALS['MDS_SELECTION_PHASE'] );

	if ( isset( $output_result['error'] ) && !json_decode($output_result['error'])) {
		Orders::set_last_order_modification_time();
		wp_send_json_success( $output_result, 200 );
	} else {
		wp_send_json_error( $output_result, 400 );
	}

} catch ( \Throwable $e ) {
	// Log the error for debugging
	error_log("MDS Update Order Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
	wp_send_json_error( [
		"error" => "true",
		"type"  => "exception",
		"data"  => [
			"value" => "Unexpected error: " . $e->getMessage(),
		]
	], 500 );
}
