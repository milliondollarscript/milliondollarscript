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

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

global $f2;
$row = load_banner_constants( $f2->bid() );

$image = $row[ $_REQUEST['image_name'] ];

if ( $image == '' ) {
	$image = get_default_image( $_REQUEST['image_name'] );
}

header( "Cache-Control: max-age=60, must-revalidate" ); // HTTP/1.1

header( "Expires: " . gmdate( 'r', time() + 60 ) ); // Date in the past

header( "Content-type: image/x-png" );
echo base64_decode( $image );
