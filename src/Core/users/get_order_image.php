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

use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

global $f2;
$BID = $f2->bid();

$banner_data = load_banner_constants( $BID );

$imagine = new Imagine\Gd\Imagine();

// get the order id
$order_id = null;
if ( isset( $_REQUEST['block_id'] ) && $_REQUEST['block_id'] != '' ) {
	global $wpdb;
	$sql          = "SELECT order_id FROM " . MDS_DB_PREFIX . "blocks WHERE block_id=%d AND banner_id=%d";
	$prepared_sql = $wpdb->prepare( $sql, intval( $_REQUEST['block_id'] ), $BID );
	$order_id     = $wpdb->get_var( $prepared_sql );
} else if ( isset( $_REQUEST['aid'] ) && $_REQUEST['aid'] != '' ) {
	$ad_id     = intval( $_REQUEST['aid'] );
	$mds_pixel = get_post( $ad_id );
	if ( ! empty( $mds_pixel ) ) {
		$order_id = \carbon_get_post_meta( $ad_id, MDS_PREFIX . 'order' );
	}
} else {
	exit;
}

if ( empty( $order_id ) ) {
	exit;
}

$size = Utility::get_pixel_image_size( $order_id );

// load all the blocks wot
$sql = "SELECT block_id,x,y,image_data FROM " . MDS_DB_PREFIX . "blocks WHERE order_id='" . intval( $order_id ) . "' ";
$result3 = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

$blocks = array();

$i = 0;
while ( $block_row = mysqli_fetch_array( $result3 ) ) {

	$high_x = ! isset( $high_x ) ? $block_row['x'] : $high_x;
	$high_y = ! isset( $high_y ) ? $block_row['y'] : $high_y;
	$low_x  = ! isset( $low_x ) ? $block_row['x'] : $low_x;
	$low_y  = ! isset( $low_y ) ? $block_row['y'] : $low_y;

	if ( $block_row['x'] > $high_x ) {
		$high_x = $block_row['x'];
	}

	if ( $block_row['y'] > $high_y ) {
		$high_y = $block_row['y'];
	}

	if ( $block_row['y'] < $low_y ) {
		$low_y = $block_row['y'];
	}

	if ( $block_row['x'] < $low_x ) {
		$low_x = $block_row['x'];
	}

	$blocks[ $i ]['block_id'] = $block_row['block_id'];
	$image_object; // Declare variable to hold the image object
	if ( $block_row['image_data'] == '' ) {
		$image_object = $imagine->load( $banner_data['GRID_BLOCK'] );
	} else {
		$image_object = $imagine->load( base64_decode( $block_row['image_data'] ) );
	}

	// Conditionally resize based on auto-resize option
	// Ensure Options class is available or use appropriate method to get option
	if (class_exists('MillionDollarScript\Classes\Data\Options') && \MillionDollarScript\Classes\Data\Options::get_option('auto-resize') == 'yes') {
		$block_size_obj = new \Imagine\Image\Box($banner_data['BLK_WIDTH'], $banner_data['BLK_HEIGHT']);
		$image_object->resize($block_size_obj);
	}
	$blocks[ $i ]['image_data'] = $image_object; // Store the (potentially resized) image object

	$blocks[ $i ]['x'] = $block_row['x'];
	$blocks[ $i ]['y'] = $block_row['y'];

	$i ++;
}

$high_x = ! isset( $high_x ) ? 0 : $high_x;
$high_y = ! isset( $high_y ) ? 0 : $high_y;
$low_x  = ! isset( $low_x ) ? 0 : $low_x;
$low_y  = ! isset( $low_y ) ? 0 : $low_y;

$x_size = ( $high_x + $banner_data['BLK_WIDTH'] ) - $low_x;
$y_size = ( $high_y + $banner_data['BLK_HEIGHT'] ) - $low_y;

$new_blocks = array();
foreach ( $blocks as $block ) {
	$id                = ( $block['x'] - $low_x ) . ( $block['y'] - $low_y );
	$new_blocks[ $id ] = $block;
}

$std_image = $imagine->load( $banner_data['GRID_BLOCK'] );

// grid size
$size = new Imagine\Image\Box( $x_size, $y_size );

// create empty image
$palette = new Imagine\Image\Palette\RGB();
$color   = $palette->color( '#000', 0 );
$image   = $imagine->create( $size, $color );

$block_count = 0;

for ( $y = 0; $y < $y_size; $y += $banner_data['BLK_HEIGHT'] ) {
	for ( $x = 0; $x < $x_size; $x += $banner_data['BLK_WIDTH'] ) {
		if ( isset( $new_blocks[ $x . $y ] ) && $new_blocks[ $x . $y ]['image_data'] != '' ) {
			$image->paste( $new_blocks[ $x . $y ]['image_data'], new Imagine\Image\Point( $x, $y ) );
		}
	}
}

$image->show( "png", array( 'png_compression_level' => 9 ) );
