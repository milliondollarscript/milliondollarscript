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

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

check_ajax_referer( 'mds-select' );

if ( isset( $_REQUEST['block_id'] ) ) {
	$block_id = intval( $_REQUEST['block_id'] );
} else {
	// possibly clicked reset or didn't pass a block_id
	$block_id = - 1;
}

global $BID, $f2, $banner_data;

$BID           = $f2->bid();
$output_result = "";

if ( ! is_user_logged_in() ) {
	wp_send_json_error( [
		"error" => "true",
		"type"  => "error",
		"data"  => [
			"value" => Language::get( 'You are not logged in. Please log in or sign up for a new account. The pixels that you have placed on order are saved and will be available once you log in.' ),
		]
	] );
}

$banner_data = load_banner_constants( $BID );

if ( ! is_numeric( $BID ) ) {
	die();
}

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

if ( ! Orders::can_user_order( $banner_data, get_current_user_id() ) ) {
	$max_orders = true;
	wp_send_json_error( [
		"error" => "true",
		"type"  => "max_orders",
		"data"  => [
			"value" => Language::get( 'Cannot place pixels on order. You have reached the order limit for this grid. Please review your orders under Manage Pixels.' ),
		]
	] );
}

// reset blocks
if ( isset( $_REQUEST['reset'] ) && $_REQUEST['reset'] == "true" ) {

	global $wpdb;

	$order_id = Orders::get_current_order_id();

	if ( ! empty( $order_id ) ) {
		// Check if order has any blocks
		$sql = $wpdb->prepare( "SELECT blocks FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%d", $order_id );
		$row = $wpdb->get_row( $sql, ARRAY_A );

		if ( ! empty( $row['blocks'] ) ) {

			$wpdb->update(
				MDS_DB_PREFIX . 'orders',
				[ 'blocks' => '' ],
				[ 'order_id' => $order_id ]
			);

			// Assemble query to delete all blocks for the current order for the current user.
			$current_user_id = get_current_user_id();
			$wpdb->delete(
				MDS_DB_PREFIX . 'blocks',
				[
					'user_id'   => $current_user_id,
					'banner_id' => intval( $BID ),
					'order_id'  => $order_id
				]
			);

			// Update the last modification time
			Orders::set_last_order_modification_time();
		}

	} else {
		// Assemble query to delete all reserved blocks for the current user.
		$user_id = get_current_user_id();
		$result  = $wpdb->delete(
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

		// Update the last modification time
		Orders::set_last_order_modification_time();
	}

	wp_send_json_success( [
		"error" => "false",
		"type"  => "removed",
		"data"  => [
			"value" => "removed",
		]
	] );
}

$size = isset( $banner_data['G_MIN_BLOCKS'] ) ? intval( $banner_data['G_MIN_BLOCKS'] ) : 1;
if ( ! empty( $_REQUEST['selection_size'] ) ) {
	$size = intval( $_REQUEST['selection_size'] );
}

$output_result = Blocks::select_block( $block_id, $banner_data, $size, $user_id );

if ( $output_result['error'] == 'false' ) {
	// Update the last modification time
	Orders::set_last_order_modification_time();
	wp_send_json_success( $output_result );
} else {
	wp_send_json_error( $output_result );
}
