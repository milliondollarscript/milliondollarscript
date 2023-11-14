<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.5
 * @author      Ryan Rhode
 * @copyright   (C) 2023, Ryan Rhode
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

header( "Cache-Control: no-cache, must-revalidate" ); // HTTP/1.1
header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" );   // Date in the past

global $BID, $f2, $banner_data;
$BID         = $f2->bid();
$banner_data = load_banner_constants( $BID );

// normalize...
//$_REQUEST['map_x']    = floor( $_REQUEST['map_x'] / $banner_data['BLK_WIDTH'] ) * $banner_data['BLK_WIDTH'];
//$_REQUEST['map_y']    = floor( $_REQUEST['map_y'] / $banner_data['BLK_HEIGHT'] ) * $banner_data['BLK_HEIGHT'];
//$_REQUEST['block_id'] = floor( $_REQUEST['block_id'] );

$floorx               = floor( intval( $_REQUEST['map_x'] ) / intval( $banner_data['BLK_WIDTH'] ) );
$floory               = floor( intval( $_REQUEST['map_y'] ) / intval( $banner_data['BLK_HEIGHT'] ) );
$floorid              = floor( intval( $_REQUEST['block_id'] ) );
$floorx               = $floorx ?: 0;
$floory               = $floory ?: 0;
$floorid              = $floorid ?: 0;
$_REQUEST['map_x']    = $floorx * $banner_data['BLK_WIDTH'];
$_REQUEST['map_y']    = $floory * $banner_data['BLK_HEIGHT'];
$_REQUEST['block_id'] = $floorid;

// place on temp order -> then
function place_temp_order( $in_str ) {

	global $BID, $f2, $banner_data, $wpdb;

	// cannot place order if there is no session!
	mds_wp_login_check();

	$blocks = explode( ',', $in_str );

	$quantity = sizeof( $blocks ) * ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

	$now = ( gmdate( "Y-m-d H:i:s" ) );

	// preserve ad_id & block info...
	$order_id = get_current_order_id();
	$sql      = $wpdb->prepare( "SELECT ad_id, block_info FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%s", $order_id );
	$row      = $wpdb->get_row( $sql, ARRAY_A );

	if ( $row ) {
		$ad_id      = intval( $row['ad_id'] );
		$block_info = wp_kses_data( $row['block_info'] );
	} else {
		$ad_id      = 0;
		$block_info = '';
	}

	// DAYS_EXPIRE comes form load_banner_constants()
	$table_name = MDS_DB_PREFIX . 'orders';

	// TODO: add any extra columns necessary such as package_id
	$order_id = get_current_order_id();
	$data     = array(
		'user_id'           => get_current_user_id(),
		'order_id'          => $order_id,
		'blocks'            => $in_str,
		'status'            => 'new',
		'order_date'        => $now,
		'price'             => 0,
		'quantity'          => intval( $quantity ),
		'days_expire'       => intval( $banner_data['DAYS_EXPIRE'] ),
		'banner_id'         => $BID,
		'currency'          => get_default_currency(),
		'date_stamp'        => $now,
		'ad_id'             => $ad_id,
		'published'         => 'N',
		'block_info'        => $block_info,
		'original_order_id' => $order_id,
	);

	$format = array(
		'%s', // user_id
		'%s', // order_id
		'%s', // blocks
		'%s', // status
		'%s', // order_date
		'%d', // price
		'%d', // quantity
		'%d', // days_expire
		'%d', // banner_id
		'%s', // currency
		'%s', // date_stamp
		'%d', // ad_id
		'%s', // published
		'%s', // block_info
		'%s', // original_order_id
	);

	$result = $wpdb->replace( $table_name, $data, $format );
	if ( false === $result ) {
		mds_sql_error( 'Error: ' . $wpdb->last_error );
	}
	$f2->write_log( 'Placed Temp order. ' . $sql );

	get_current_order_id();

	return true;
}

// reserves the pixels for the temp order..

$price_table = '';

function reserve_temp_order_pixels( $block_info, $in_str ): bool {

	global $BID, $f2, $wpdb;

	// cannot reserve pixels if there is no session
	if ( ! is_user_logged_in() ) {
		$f2->write_log( 'Cannot reserve pixels if there is no session!' );

		return false;
	}

	$total = 0;
	foreach ( $block_info as $key => $block ) {

		$price = get_zone_price( $BID, $block['map_y'], $block['map_x'] );

		$currency = get_default_currency();

		// enhance block info...
		$block_info[ $key ]['currency']  = $currency;
		$block_info[ $key ]['price']     = $price;
		$block_info[ $key ]['banner_id'] = $BID;

		$total += $price;
	}

	$sql = $wpdb->prepare(
		"UPDATE " . MDS_DB_PREFIX . "orders SET price=%f, block_info=%s WHERE order_id=%d",
		floatval( $total ),
		serialize( $block_info ),
		get_current_order_id()
	);
	$wpdb->query( $sql ) or mds_sql_log_die( $sql );
	$f2->write_log( 'Reserved Temp order. ' . $sql );

	return true;
}

// MAIN
// return true, or false if the image can fit

check_selection_main();

function check_selection_main(): void {

	global $banner_data;

	$upload_image_file = get_tmp_img_name();

	$imagine = new Imagine\Gd\Imagine();

	$image = $imagine->open( $upload_image_file );
	$size  = $image->getSize();

	$new_size = get_required_size( $size->getWidth(), $size->getHeight(), $banner_data );

	if ( $size->getWidth() != $new_size[0] || $size->getHeight() != $new_size[1] ) {
		$resize = new Imagine\Image\Box( $new_size[0], $new_size[1] );
		$image->resize( $resize );
	}

	$block_size = new Imagine\Image\Box( $banner_data['BLK_WIDTH'], $banner_data['BLK_HEIGHT'] );
	$palette    = new Imagine\Image\Palette\RGB();
	$color      = $palette->color( '#000', 0 );
	//$zero_point = new Imagine\Image\Point( 0, 0 );

	$block_info = $cb_array = array();
	for ( $y = 0; $y < ( $size->getHeight() ); $y += $banner_data['BLK_HEIGHT'] ) {
		for ( $x = 0; $x < ( $size->getWidth() ); $x += $banner_data['BLK_WIDTH'] ) {

			$map_x = $x + $_REQUEST['map_x'];
			$map_y = $y + $_REQUEST['map_y'];

			$GRD_WIDTH  = $banner_data['BLK_WIDTH'] * $banner_data['G_WIDTH'];
			$cb         = ( ( $map_x ) / $banner_data['BLK_WIDTH'] ) + ( ( $map_y / $banner_data['BLK_HEIGHT'] ) * ( $GRD_WIDTH / $banner_data['BLK_WIDTH'] ) );
			$cb_array[] = $cb;

			$block_info[ $cb ]['map_x'] = $map_x;
			$block_info[ $cb ]['map_y'] = $map_y;

			// create new destination image
			$dest = $imagine->create( $block_size, $color );

			// crop a part from the tiled image
			//$block = $image->copy();
			//$block->crop( new Imagine\Image\Point( $x, $y ), $block_size );

			// paste the block into the destination image
			//$dest->paste( $block, $zero_point );

			// much faster
			imagecopy( $dest->getGdResource(), $image->getGdResource(), 0, 0, $x, $y, $banner_data['BLK_WIDTH'], $banner_data['BLK_HEIGHT'] );

			// save the image as a base64 encoded string
			$data = base64_encode( $dest->get( "png", array( 'png_compression_level' => 9 ) ) );

			$block_info[ $cb ]['image_data'] = $data;
		}
	}

	$in_str = implode( ',', $cb_array );

	if ( ! check_pixels( $in_str ) ) {
		return;
	}

	// create a temporary order and place the blocks on a temp order
	place_temp_order( $in_str );
//	$f2->write_log( "in_str is:" . $in_str );
//	$f2->write_log( '$block_info: '. print_r($block_info, true) );
	reserve_temp_order_pixels( $block_info, $in_str );
}
