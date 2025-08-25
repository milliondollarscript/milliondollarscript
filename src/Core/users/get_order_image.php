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
use Imagine\Gd\Imagine;
use Imagine\Image\Box;

global $wpdb;
use MillionDollarScript\Classes\Data\Options;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

global $f2;
$BID = $f2->bid();

$banner_data = load_banner_constants( $BID );

$imagine = new Imagine();

// get the order id
$order_id = null;
if ( isset( $_REQUEST['block_id'] ) && $_REQUEST['block_id'] != '' ) {
	global $wpdb;
	$block_id = intval( $_REQUEST['block_id'] );
	if ( $block_id <= 0 ) {
		http_response_code( 400 );
		exit( 'Invalid block ID' );
	}
	
	$sql          = "SELECT order_id FROM " . MDS_DB_PREFIX . "blocks WHERE block_id=%d AND banner_id=%s";
	$prepared_sql = $wpdb->prepare( $sql, $block_id, $BID );
	$order_id     = $wpdb->get_var( $prepared_sql );
} else if ( isset( $_REQUEST['aid'] ) && $_REQUEST['aid'] != '' ) {
	$ad_id     = intval( $_REQUEST['aid'] );
	if ( $ad_id <= 0 ) {
		http_response_code( 400 );
		exit( 'Invalid ad ID' );
	}
	
	$mds_pixel = get_post( $ad_id );
	if ( ! empty( $mds_pixel ) ) {
		$order_id = \carbon_get_post_meta( $ad_id, MDS_PREFIX . 'order' );
	}
} else {
	http_response_code( 400 );
	exit( 'Missing required parameters' );
}

if ( empty( $order_id ) ) {
	http_response_code( 404 );
	exit( 'Order not found' );
}

// Security check: verify user has permission to view this order
$current_user_id = get_current_user_id();
if ( $current_user_id > 0 ) {
	$order_user_id = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d", $order_id ) );
	
	// Allow access if user owns the order or if user is admin
	if ( $order_user_id != $current_user_id && !current_user_can( 'manage_options' ) ) {
		http_response_code( 403 );
		exit( 'Access denied' );
	}
}

$size = Utility::get_pixel_image_size( $order_id );

// load all the blocks wot
$sql = "SELECT block_id,x,y,image_data FROM " . MDS_DB_PREFIX . "blocks WHERE order_id = %d";
$block_results = $wpdb->get_results( $wpdb->prepare( $sql, intval( $order_id ) ), ARRAY_A );

if ( empty( $block_results ) ) {
	http_response_code( 404 );
	exit( 'No blocks found for this order' );
}

$blocks = array();

$i = 0;
foreach ( $block_results as $block_row ) {

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
	if ( $block_row['image_data'] == '' ) {
		$blocks[ $i ]['image_data'] = $imagine->load( $banner_data['GRID_BLOCK'] );
	} else {
		$blocks[ $i ]['image_data'] = $imagine->load( base64_decode( $block_row['image_data'] ) );
	}
	// Conditionally fill block space when auto-resize is enabled
	if ( Options::get_option('resize') === 'YES' ) {
		$blocks[ $i ]['image_data'] = $blocks[ $i ]['image_data']->thumbnail(
			new Box( $banner_data['BLK_WIDTH'], $banner_data['BLK_HEIGHT'] ),
			\Imagine\Image\ManipulatorInterface::THUMBNAIL_OUTBOUND
		);
	}
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
$size = new Box( $x_size, $y_size );

// create empty image
$palette = new \Imagine\Image\Palette\RGB();
$color   = $palette->color( '#000', 0 );
$image   = $imagine->create( $size, $color );

$block_count = 0;

for ( $y = 0; $y < $y_size; $y += $banner_data['BLK_HEIGHT'] ) {
	for ( $x = 0; $x < $x_size; $x += $banner_data['BLK_WIDTH'] ) {
		if ( isset( $new_blocks[ $x . $y ] ) && $new_blocks[ $x . $y ]['image_data'] != '' ) {
			$image->paste( $new_blocks[ $x . $y ]['image_data'], new \Imagine\Image\Point( $x, $y ) );
		}
	}
}

$image->show( "png", array( 'png_compression_level' => 9 ) );
