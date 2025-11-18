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

// Only admins should be able to fetch admin asset variants.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Unauthorized access.' );
}

// accepts $_REQUEST['BID'] and $_REQUEST['image_name']

global $f2;
$BID = $f2->bid();

// Sanitize the requested key to avoid accessing unexpected array fields.
$image_name = isset( $_REQUEST['image_name'] ) ? sanitize_key( wp_unslash( $_REQUEST['image_name'] ) ) : '';
if ( $image_name === '' ) {
	wp_die( 'Invalid image request.' );
}

$row = load_banner_constants( $BID );

// Guard against missing keys to prevent notices and accidental data leakage.
$image = $row[ $image_name ] ?? '';

if ( $image === '' ) {
	$image = get_default_image( $image_name );
}

header( "Cache-Control: max-age=60, must-revalidate" ); // HTTP/1.1
header( "Expires: " . wp_date( 'r', time() + 60 ) ); // Date in the past
header( "Content-Type: image/png" );

echo base64_decode( $image );
