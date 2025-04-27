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

defined( 'ABSPATH' ) or exit;

\MillionDollarScript\Classes\System\Functions::verify_nonce( 'mds-order' );

mds_wp_login_check();

header( "Cache-Control: no-cache, must-revalidate" ); // HTTP/1.1
header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" );   // Date in the past

global $f2, $banner_data;

$BID         = $f2->bid();
$banner_data = load_banner_constants( $BID );

// normalize...
$_REQUEST['map_x']    = intval( floor( $_REQUEST['map_x'] ) / $banner_data['BLK_WIDTH'] * $banner_data['BLK_WIDTH'] );
$_REQUEST['map_y']    = intval( floor( $_REQUEST['map_y'] ) / $banner_data['BLK_HEIGHT'] * $banner_data['BLK_HEIGHT'] );
$_REQUEST['block_id'] = intval( floor( $_REQUEST['block_id'] ) );

// MAIN
// return true, or false if the image can fit

check_selection_main();

function check_selection_main(): void {

	global $banner_data;

	$upload_image_file = Orders::get_tmp_img_name();

	if ( empty( $upload_image_file ) ) {
		Language::out( 'The upload image file is empty.' );

		return;
	}

	$imagine = new Imagine\Gd\Imagine();

	$image     = $imagine->open( $upload_image_file );
	$size      = $image->getSize();

	// Get client coordinates and pointer dimensions from request
	$client_x = intval($_REQUEST['map_x']);
	$client_y = intval($_REQUEST['map_y']);
	
	// Get grid dimensions from banner data
	$grid_width_px = $banner_data['BLK_WIDTH'] * $banner_data['G_WIDTH'];
	$grid_height_px = $banner_data['BLK_HEIGHT'] * $banner_data['G_HEIGHT'];
	
	// Calculate blocks covered by the pointer
	$cb_array = array();
	
	// Get pointer dimensions from uploaded image size
	$pointer_width_px = $size->getWidth();
	$pointer_height_px = $size->getHeight();
	
	// Calculate how many blocks the pointer spans horizontally and vertically
	$pointer_width_blocks = ceil($pointer_width_px / $banner_data['BLK_WIDTH']);
	$pointer_height_blocks = ceil($pointer_height_px / $banner_data['BLK_HEIGHT']);
	
	// Calculate block positions from pixel coordinates
	$start_block_x = floor($client_x / $banner_data['BLK_WIDTH']);
	$start_block_y = floor($client_y / $banner_data['BLK_HEIGHT']);
	

	// Loop through all blocks covered by the pointer
	for ($y = 0; $y < $pointer_height_blocks; $y++) {
		for ($x = 0; $x < $pointer_width_blocks; $x++) {
			// Calculate the block coordinates
			$block_x = $start_block_x + $x;
			$block_y = $start_block_y + $y;
			
			// Skip if the block is outside the grid
			if ($block_x >= $banner_data['G_WIDTH'] || $block_y >= $banner_data['G_HEIGHT']) {
				continue;
			}
			
			// Calculate the block ID using the grid formula
			$block_id = $block_x + ($block_y * $banner_data['G_WIDTH']);
			$cb_array[] = $block_id;
		}
	}
	


	$in_str = implode( ',', $cb_array );

	$available = Utility::check_pixels( $in_str );

	// If pixels are available, send success response
	if ( $available ) {
		echo json_encode( [
			"error" => "false",
			"type"  => "available",
			"data"  => []
		] );
	}
	// Function check_pixels() already outputs the JSON error if not available
}
