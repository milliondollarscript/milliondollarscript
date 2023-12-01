<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.6
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

global $f2;
$BID = $f2->bid();

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "blocks where block_id='" . intval( $_REQUEST['block_id'] ) . "' banner_id='$BID' ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
$row = mysqli_fetch_array( $result, MYSQLI_ASSOC );

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
	header( "Content-type: image/x-png" );
	echo base64_decode( $data );
} else {
	header( "Content-type: " . $row['mime_type'] );
	echo base64_decode( $row['image_data'] );
}
