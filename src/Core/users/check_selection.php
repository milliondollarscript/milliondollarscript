<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.9
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

use MillionDollarScript\Classes\Language;

defined( 'ABSPATH' ) or exit;

\MillionDollarScript\Classes\Functions::verify_nonce( 'mds-order' );

mds_wp_login_check();

header( "Cache-Control: no-cache, must-revalidate" ); // HTTP/1.1
header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" );   // Date in the past

global $f2, $banner_data;

$BID         = $f2->bid();
$banner_data = load_banner_constants( $BID );

// normalize...

$_REQUEST['map_x']    = floor( $_REQUEST['map_x'] / $banner_data['BLK_WIDTH'] ) * $banner_data['BLK_WIDTH'];
$_REQUEST['map_y']    = floor( $_REQUEST['map_y'] / $banner_data['BLK_HEIGHT'] ) * $banner_data['BLK_HEIGHT'];
$_REQUEST['block_id'] = floor( $_REQUEST['block_id'] );

// MAIN
// return true, or false if the image can fit

check_selection_main();

function check_selection_main(): void {

	global $banner_data;

	$upload_image_file = get_tmp_img_name();

	if ( empty( $upload_image_file ) ) {
		Language::out( 'The upload image file is empty.' );

		return;
	}

	$imagine = new Imagine\Gd\Imagine();

	$image = $imagine->open( $upload_image_file );
	$size  = $image->getSize();

	$cb_array = array();
	for ( $y = 0; $y < ( $size->getHeight() ); $y += $banner_data['BLK_HEIGHT'] ) {
		for ( $x = 0; $x < ( $size->getWidth() ); $x += $banner_data['BLK_WIDTH'] ) {

			$map_x = $x + intval( $_REQUEST['map_x'] );
			$map_y = $y + intval( $_REQUEST['map_y'] );

			$GRD_WIDTH  = $banner_data['BLK_WIDTH'] * $banner_data['G_WIDTH'];
			$cb         = ( ( $map_x ) / $banner_data['BLK_WIDTH'] ) + ( ( $map_y / $banner_data['BLK_HEIGHT'] ) * ( $GRD_WIDTH / $banner_data['BLK_WIDTH'] ) );
			$cb_array[] = $cb;
		}
	}

	$in_str = implode( ',', $cb_array );

	$available = check_pixels( $in_str );

	if ( $available ) {
		echo json_encode( [
			"error" => "false",
			"type"  => "available",
			"data"  => []
		] );
	}
}
