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

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

global $f2, $wpdb;
$BID = $f2->bid();

// Validate and sanitize input
$block_id = intval( $_REQUEST['block_id'] ?? 0 );
if ( $block_id <= 0 ) {
	http_response_code( 400 );
	exit( 'Invalid block ID' );
}

// Use prepared statement with $wpdb
$sql = "SELECT * FROM " . MDS_DB_PREFIX . "blocks WHERE block_id = %d AND banner_id = %s";
$row = $wpdb->get_row( $wpdb->prepare( $sql, $block_id, $BID ), ARRAY_A );

// Check if block exists
if ( empty( $row ) ) {
	http_response_code( 404 );
	exit( 'Block not found' );
}

// Additional security check: verify user has permission to view this block
// Check if user owns this block or if it's a public block
$current_user_id = get_current_user_id();
if ( $current_user_id > 0 && $row['status'] === 'sold' ) {
	// Check if current user is the owner of this block
	$order_sql = "SELECT user_id FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d";
	$order_user_id = $wpdb->get_var( $wpdb->prepare( $order_sql, $row['order_id'] ) );
	
	// Allow access if user owns the block or if user is admin
	if ( $order_user_id != $current_user_id && !current_user_can( 'manage_options' ) ) {
		http_response_code( 403 );
		exit( 'Access denied' );
	}
}

if ( $row['image_data'] == '' ) {

	if ( $row['status'] == "sold" ) {
		$file_name = MDS_CORE_PATH . 'images/ordered_block.png';

		// hard coded above file to save a file read...

		$data = "iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAABGdBTUEAALGPC/xhBQAAABZJREFUKFNj/N/gwIAHAKXxIIYRKg0AB3qe55E8bNQAAAAASUVORK5CYII=";
	} else {
		$file_name = MDS_CORE_PATH . 'images/block.png';

		// hard coded above file to save a file read...

		$data = "iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAABGdBTUEAALGPC/xhBQAAABdJREFUKFNjvHLlCgMeAJT+jxswjFBpAOAoCvbvqFc9AAAAAElFTkSuQmCC";
	}
    header( "Content-Type: image/png" );
    echo base64_decode( $data );
} else {
    // Enforce image MIME allowlist to prevent header abuse
    $allowed_mimes = [ 'image/png', 'image/jpeg', 'image/gif' ];
    $mime = in_array( $row['mime_type'], $allowed_mimes, true ) ? $row['mime_type'] : 'image/png';
    header( "Content-Type: {$mime}" );
    echo base64_decode( $row['image_data'] );
}
