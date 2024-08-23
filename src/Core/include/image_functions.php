<?php
/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2024, Ryan Rhode
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

use MillionDollarScript\Classes\Data\Config;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

function publish_image( $BID ): void {
	global $wpdb;

	if ( ! is_numeric( $BID ) ) {
		return;
	}

	$BID = intval( $BID );

	$imagine = "";
	if ( class_exists( 'Imagick' ) ) {
		$imagine = new Imagine\Imagick\Imagine();
	} else if ( function_exists( 'gd_info' ) ) {
		$imagine = new Imagine\Gd\Imagine();
	}

	$dest        = get_banner_dir();
	$ext         = Utility::get_file_extension();
	$source_path = Utility::get_upload_path() . "grids/grid$BID.$ext";
	$dest_path   = $dest . "grid$BID.$ext";

	// TODO: Use Filesystem class to copy.
	if ( $source_path != $dest_path && ! copy( $source_path, $dest_path ) ) {
		return;
	}

	// Output the tile image
	if ( Config::get( 'DISPLAY_PIXEL_BACKGROUND' ) == "YES" ) {
		$b_row = load_banner_row( $BID );

		if ( $b_row['tile'] == '' ) {
			$b_row['tile'] = get_default_image( 'tile' );
		}
		$tile = $imagine->load( base64_decode( $b_row['tile'] ) );
		$tile->save( $dest . "bg-main$BID.gif" );
	}

	// Update the records
	$blocks_table  = MDS_DB_PREFIX . 'blocks';
	$orders_table  = MDS_DB_PREFIX . 'orders';
	$banners_table = MDS_DB_PREFIX . 'banners';

	// Handle approved blocks
	$sql             = $wpdb->prepare(
		"SELECT * FROM $blocks_table WHERE approved = 'Y' AND status IN ('sold','ordered') AND image_data <> '' AND banner_id = %d",
		$BID
	);
	$results         = $wpdb->get_results( $sql, ARRAY_A );
	$orders_approved = [];

	foreach ( $results as $row ) {
		$wpdb->update(
			$blocks_table,
			[ 'published' => 'Y' ],
			[ 'block_id' => intval( $row['block_id'] ), 'banner_id' => $BID ],
			[ '%s' ],
			[ '%d', '%d' ]
		);

		$orders_approved[] = intval( $row['order_id'] );
	}

	foreach ( $orders_approved as $order_id ) {
		$now = current_time( 'mysql' );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $orders_table SET date_published = %s WHERE order_id = %d AND date_published IS NULL",
				$now,
				$order_id
			)
		);

		$wpdb->update(
			$orders_table,
			[ 'published' => 'Y' ],
			[ 'order_id' => $order_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	// Handle disapproved blocks
	$sql                = $wpdb->prepare(
		"SELECT block_id, order_id FROM $blocks_table WHERE approved = 'N' AND status = 'sold' AND banner_id = %d",
		$BID
	);
	$results            = $wpdb->get_results( $sql, ARRAY_A );
	$orders_disapproved = [];

	foreach ( $results as $row ) {
		$wpdb->update(
			$blocks_table,
			[ 'published' => 'N' ],
			[ 'block_id' => intval( $row['block_id'] ), 'banner_id' => $BID ],
			[ '%s' ],
			[ '%d', '%d' ]
		);

		$orders_disapproved[] = intval( $row['order_id'] );
	}

	foreach ( $orders_disapproved as $order_id ) {
		$wpdb->update(
			$orders_table,
			[ 'published' => 'N' ],
			[ 'order_id' => $order_id, 'banner_id' => $BID ],
			[ '%s' ],
			[ '%d', '%d' ]
		);
	}

	// Update the time-stamp on the banner
	$wpdb->update(
		$banners_table,
		[ 'time_stamp' => time() ],
		[ 'banner_id' => $BID ],
		[ '%d' ],
		[ '%d' ]
	);

	// Update the last modification time
	Orders::set_last_order_modification_time();
}

function process_image( $BID ) {

	require_once( "output_grid.php" );

	return output_grid( false, Utility::get_upload_path() . "grids/grid$BID", $BID, array(
		'background',
		'orders',
		'nfs_front',
		'grid',
	) );
}

function get_grid_shortcode( $BID ) {
	$BID = intval( $BID );

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners WHERE banner_id='" . $BID . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$b_row = mysqli_fetch_array( $result );

	if ( ! $b_row['block_width'] ) {
		$b_row['block_width'] = 10;
	}
	if ( ! $b_row['block_height'] ) {
		$b_row['block_height'] = 10;
	}

	$width  = $b_row['grid_width'] * $b_row['block_width'];
	$height = $b_row['grid_height'] * $b_row['block_height'];

	return wp_sprintf(
		'[milliondollarscript id="%d" align="center" width="%dpx" height="%dpx" type="grid"]',
		$BID,
		$width,
		$height
	);
}

function get_stats_shortcode( $BID ) {
	$BID = intval( $BID );

	return wp_sprintf(
		'[milliondollarscript id="%d" align="center" width="150px" height="60px" type="stats"]',
		$BID,
	);
}

/**
 * Calculates restricted dimensions with a maximum of $goal_width by $goal_height
 *
 * @link https://stackoverflow.com/questions/6606445/calculating-width-and-height-to-resize-image/7877615#7877615
 *
 * @param $goal_width
 * @param $goal_height
 * @param $width
 * @param $height
 *
 * @return array
 */
function resize_dimensions( $goal_width, $goal_height, $width, $height ) {
	$return = array( 'width' => $width, 'height' => $height );

	// If the ratio > goal ratio and the width > goal width resize down to goal width
	if ( $width / $height > $goal_width / $goal_height && $width > $goal_width ) {
		$return['width']  = $goal_width;
		$return['height'] = $goal_width / $width * $height;
	} // Otherwise, if the height > goal, resize down to goal height
	else if ( $height > $goal_height ) {
		$return['width']  = $goal_height / $height * $width;
		$return['height'] = $goal_height;
	}

	return $return;
}
