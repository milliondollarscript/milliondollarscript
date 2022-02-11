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

require_once __DIR__ . "/../include/init.php";
require( 'admin_common.php' );

global $f2;

$BID = $f2->bid();

$sql = "select * from " . MDS_DB_PREFIX . "banners where banner_id=" . intval( $BID );
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
$b_row = mysqli_fetch_array( $result );
if ( $_REQUEST['order_id'] ) {
	$_SESSION['MDS_order_id'] = $_REQUEST['order_id'];
}

$sql = "select block_id, status, user_id, url, alt_text FROM " . MDS_DB_PREFIX . "blocks where  status='sold' AND banner_id=" . intval( $BID );
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
while ( $row = mysqli_fetch_array( $result ) ) {
	$blocks[ $row['block_id'] ] = $row['status'];
	$owners[ $row['block_id'] ] = $row['user_id'];
}
?>
The image:
<table border="0" cellpadding=0 cellspacing=0>
    <tr>
        <td nowrap>
			<?php

			for ( $i = 0; $i < $b_row['grid_height']; $i ++ ) {
				//echo "<tr>";
				for ( $j = 0; $j < $b_row['grid_width']; $j ++ ) {

					switch ( $blocks[ $cell ] ) {

						case 'sold':

							echo '<img style="cursor: pointer;cursor: hand;"  src="get_image.php?block_id=' . $cell . '" width="10" height="10">';

							break;

						case '':
							echo "<img src='images/block.png'>";
					}

					$cell ++;
				}
				echo "<br>";
			}

			?>
        </td>
    </tr>
</table>
