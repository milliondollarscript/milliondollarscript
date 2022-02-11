<?php
/*
 * @package       mds
 * @copyright     (C) Copyright 2022 Ryan Rhode, All rights reserved.
 * @author        Ryan Rhode, ryan@milliondollarscript.com
 * @version       2022-01-30 17:07:25 EST
 * @license       This program is free software; you can redistribute it and/or modify
 *        it under the terms of the GNU General Public License as published by
 *        the Free Software Foundation; either version 3 of the License, or
 *        (at your option) any later version.
 *
 *        This program is distributed in the hope that it will be useful,
 *        but WITHOUT ANY WARRANTY; without even the implied warranty of
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *        GNU General Public License for more details.
 *
 *        You should have received a copy of the GNU General Public License along
 *        with this program;  If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 *
 *  * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *        Million Dollar Script
 *        A pixel script for selling pixels on your website.
 *
 *        For instructions see README.txt
 *
 *        Visit our website for FAQs, documentation, a list team members,
 *        to post any bugs or feature requests, and a community forum:
 *        https://milliondollarscript.com/
 *
 */

require_once __DIR__ . "/../include/login_functions.php";
mds_start_session();
define( 'NO_HOUSE_KEEP', 'YES' );
// check the image selection.
require_once __DIR__ . "/../include/init.php";

header( "Cache-Control: no-cache, must-revalidate" ); // HTTP/1.1
header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" ); // Date in the past

global $f2;

$BID         = $f2->bid();
$banner_data = load_banner_constants( $BID );

// normalize...

$_REQUEST['map_x']    = floor( $_REQUEST['map_x'] / $banner_data['BLK_WIDTH'] ) * $banner_data['BLK_WIDTH'];
$_REQUEST['map_y']    = floor( $_REQUEST['map_y'] / $banner_data['BLK_HEIGHT'] ) * $banner_data['BLK_HEIGHT'];
$_REQUEST['block_id'] = floor( $_REQUEST['block_id'] );

// MAIN
// return true, or false if the image can fit

check_selection_main();

function check_selection_main() {

	global $f2, $banner_data;

	$upload_image_file = get_tmp_img_name();

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
	$f2->write_log( "in_str is:" . $in_str );
	check_pixels( $in_str );
}
