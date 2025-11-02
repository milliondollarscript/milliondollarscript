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

defined( 'ABSPATH' ) or exit;

// Admin capability check
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Unauthorized access.' );
}

header( "Cache-Control: no-cache, must-revalidate" ); // HTTP/1.1
header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" ); // Date in the past

global $f2;
$BID = $f2->bid();

// Validate and sanitize input
$block_id = isset( $_REQUEST['block_id'] ) ? intval( $_REQUEST['block_id'] ) : 0;
if ( $block_id <= 0 ) {
	wp_die( 'Invalid block ID.' );
}

global $wpdb;
$table_name = $wpdb->prefix . MDS_DB_PREFIX . 'blocks';
$row = $wpdb->get_row( $wpdb->prepare( 
	"SELECT * FROM $table_name WHERE block_id = %d AND banner_id = %d", 
	$block_id, $BID 
), ARRAY_A );

if ( ! $row ) {
	wp_die( 'Block not found.' );
}

if ( isset($row['image_data']) && $row['image_data'] == '' ) {
	$banner_data       = load_banner_constants( $BID );
	$row['image_data'] = base64_encode( $banner_data['GRID_BLOCK'] );
	//$row['image_data'] =  "iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAABGdBTUEAALGPC/xhBQAAABZJREFUKFNj/N/gwIAHAKXxIIYRKg0AB3qe55E8bNQAAAAASUVORK5CYII=";
}

header( "Content-Type: image/png" );
echo base64_decode( $row['image_data'] );
