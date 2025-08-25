<?php

use MillionDollarScript\Classes\System\Logs;
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

// Admin capability check
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Unauthorized access.' );
}

global $f2;
$BID = $f2->bid();

$banner_data = load_banner_constants( $BID );

$imagine = new Imagine\Gd\Imagine();

// get the order id
global $wpdb;
$row = null;

if ( isset( $_REQUEST['block_id'] ) && $_REQUEST['block_id'] != '' ) {
	$block_id = intval( $_REQUEST['block_id'] );
	if ( $block_id <= 0 ) {
		wp_die( 'Invalid block ID.' );
	}
	
	$table_name = $wpdb->prefix . MDS_DB_PREFIX . 'blocks';
	$row = $wpdb->get_row( $wpdb->prepare( 
		"SELECT * FROM $table_name WHERE block_id = %d AND banner_id = %d", 
		$block_id, $BID 
	), ARRAY_A );
} else if ( isset( $_REQUEST['aid'] ) && $_REQUEST['aid'] != '' ) {
	$aid = intval( $_REQUEST['aid'] );
	if ( $aid <= 0 ) {
		wp_die( 'Invalid ad ID.' );
	}
	
	$table_name = $wpdb->prefix . MDS_DB_PREFIX . 'ads';
	$row = $wpdb->get_row( $wpdb->prepare( 
		"SELECT * FROM $table_name WHERE ad_id = %d", 
		$aid 
	), ARRAY_A );
}

if ( ! $row ) {
	Logs::log( "No block_id or aid found in request, or record not found!" );
	wp_die( 'Record not found.' );
}

// load all the blocks wot
$table_name = $wpdb->prefix . MDS_DB_PREFIX . 'blocks';
$block_results = $wpdb->get_results( $wpdb->prepare( 
	"SELECT * FROM $table_name WHERE order_id = %d", 
	intval( $row['order_id'] ) 
), ARRAY_A );

if ( ! $block_results ) {
	wp_die( 'No blocks found for this order.' );
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

for ( $y = 0; $y < ( $y_size ); $y += $banner_data['BLK_HEIGHT'] ) {
	for ( $x = 0; $x < ( $x_size ); $x += $banner_data['BLK_WIDTH'] ) {
		if ( isset( $new_blocks["$x$y"] ) && $new_blocks["$x$y"]['image_data'] != '' ) {
			$image->paste( $new_blocks["$x$y"]['image_data'], new Imagine\Image\Point( $x, $y ) );
		}
	}
}

$image->show( "png", array( 'png_compression_level' => 9 ) );
