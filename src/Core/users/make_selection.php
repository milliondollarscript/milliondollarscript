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
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\System\Logs;

defined( 'ABSPATH' ) or exit;

if ( ! is_user_logged_in() ) {
	wp_send_json( [
		"error"    => "true",
		"type"     => "logged_out",
		"data"     => [
			"value" => Language::get( 'You are not logged in. Please log in or sign up for a new account.' ),
		],
		'redirect' => wp_login_url( Utility::get_page_url( 'order' ) ),
	] );
}

header( "Cache-Control: no-cache, must-revalidate" ); // HTTP/1.1
header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" );   // Date in the past

global $BID, $f2, $banner_data;

// Get BID directly from POST data for AJAX requests, ensuring it's a valid integer.
// Fallback to $f2->bid() for non-AJAX contexts, though this specific file is only included via AJAX.
if ( isset( $_POST['BID'] ) && is_numeric( $_POST['BID'] ) ) {
	$BID = intval( $_POST['BID'] );
} else {
	// Original method as fallback, though less likely to be correct in this context.
	// Consider adding more robust error handling if BID is missing/invalid.
	$BID = $f2->bid();
}

$banner_data = load_banner_constants( $BID );

// Ensure banner data was loaded successfully
if ( is_null( $banner_data ) ) {
	if ( wp_doing_ajax() ) {
		wp_send_json_error( [
			"error" => "true",
			"type"  => "invalid_grid",
			"data"  => [
				"value" => Language::get( 'Error: Could not load grid data for the specified ID.' ),
			]
		] );
	} else {
		// Handle non-AJAX error appropriately
		// Maybe redirect or show an error message
		wp_die( Language::get( 'Error: Could not load grid data.' ) );
	}
}

// normalize...
//$_REQUEST['map_x']    = floor( $_REQUEST['map_x'] / $banner_data['BLK_WIDTH'] ) * $banner_data['BLK_WIDTH'];
//$_REQUEST['map_y']    = floor( $_REQUEST['map_y'] / $banner_data['BLK_HEIGHT'] ) * $banner_data['BLK_HEIGHT'];
//$_REQUEST['block_id'] = floor( $_REQUEST['block_id'] );

$floorx               = intval( floor( intval( $_REQUEST['map_x'] ) / intval( $banner_data['BLK_WIDTH'] ) ) );
$floory               = intval( floor( intval( $_REQUEST['map_y'] ) / intval( $banner_data['BLK_HEIGHT'] ) ) );
$floorid              = intval( floor( $_REQUEST['block_id'] ) );
$floorx               = $floorx ?: 0;
$floory               = $floory ?: 0;
$floorid              = $floorid ?: 0;
$_REQUEST['map_x']    = $floorx * $banner_data['BLK_WIDTH'];
$_REQUEST['map_y']    = $floory * $banner_data['BLK_HEIGHT'];
$_REQUEST['block_id'] = $floorid;

// MAIN
// return true, or false if the image can fit

// Logs::log( 'MDS Legacy make_selection entry point invoked; delegating to Orders::persist_selection.' );
// Logging disabled above to avoid excessive noise while the legacy shim remains.

$upload_image_file = Orders::get_tmp_img_name();

if ( empty( $upload_image_file ) && $_POST['type'] == 'make-selection' ) {
	wp_send_json( [
		"error"    => "true",
		"type"     => "no_orders",
		"data"     => [
			"value" => Language::get( '<h1>No new orders in progress</h1>' ),
		],
		'redirect' => Utility::get_page_url( 'no-orders' ),
	] );
}

try {
	$imagine = new Imagine\Gd\Imagine();

	$image = $imagine->open( $upload_image_file );
	$size  = $image->getSize();

	$new_size = Utility::get_required_size( $size->getWidth(), $size->getHeight(), $banner_data );

	if ( $size->getWidth() != $new_size[0] || $size->getHeight() != $new_size[1] ) {
		$resize = new Imagine\Image\Box( $new_size[0], $new_size[1] );
		$image->resize( $resize );
	}

	$block_size = new Imagine\Image\Box( $banner_data['BLK_WIDTH'], $banner_data['BLK_HEIGHT'] );
	$palette    = new Imagine\Image\Palette\RGB();
	$color      = $palette->color( '#000', 0 );

	$block_info = $cb_array = array();
	for ( $y = 0; $y < ( $new_size[1] ); $y += $banner_data['BLK_HEIGHT'] ) {
		for ( $x = 0; $x < ( $new_size[0] ); $x += $banner_data['BLK_WIDTH'] ) {

			$map_x = $x + $_REQUEST['map_x'];
			$map_y = $y + $_REQUEST['map_y'];

			$GRD_WIDTH  = $banner_data['BLK_WIDTH'] * $banner_data['G_WIDTH'];
			$cb         = ( ( $map_x ) / $banner_data['BLK_WIDTH'] ) + ( ( $map_y / $banner_data['BLK_HEIGHT'] ) * ( $GRD_WIDTH / $banner_data['BLK_WIDTH'] ) );
			$cb_array[] = $cb;

			$block_info[ $cb ]['map_x'] = $map_x;
			$block_info[ $cb ]['map_y'] = $map_y;

			$dest = $imagine->create( $block_size, $color );
			imagecopy( $dest->getGdResource(), $image->getGdResource(), 0, 0, $x, $y, $banner_data['BLK_WIDTH'], $banner_data['BLK_HEIGHT'] );

			$data = base64_encode( $dest->get( "png", array( 'png_compression_level' => 9 ) ) );

			$block_info[ $cb ]['image_data'] = $data;
		}
	}

	$in_str = implode( ',', $cb_array );

	if ( ! Utility::check_pixels( $in_str ) ) {
		return;
	}

	$package_id = isset( $_REQUEST['package'] ) ? intval( $_REQUEST['package'] ) : 0;
	$persisted  = Orders::persist_selection( $BID, $banner_data, $in_str, $block_info, $package_id );

	wp_send_json( [
		"error" => "false",
		"type"  => "available",
		"data"  => [
			"order_id"    => $persisted['order_id'],
			"block_count" => $persisted['block_count'],
			"total"       => $persisted['total'],
		],
	] );

} catch ( \Throwable $e ) {
	Logs::log( 'MDS make_selection persistence failure: ' . $e->getMessage() );
	wp_send_json( [
		"error" => "true",
		"type"  => "unavailable",
		"data"  => [
			"value" => Language::get( 'This space is not available! Please try to place your pixels in a different area.' ) . " (E434)",
		],
	] );
}
