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

// select all the blocks...

$sql    = "SELECT order_id, block_id, banner_id FROM " . MDS_DB_PREFIX . "blocks WHERE status <> 'nfs'"; // nfs blocks do not have an order.
$result = mysqli_query( $GLOBALS['connection'], $sql );

while ( $row = mysqli_fetch_array( $result ) ) {

	$sql = "SELECT order_id FROM " . MDS_DB_PREFIX . "orders WHERE banner_id='" . intval( $row['banner_id'] ) . "' AND  order_id='" . intval( $row['order_id'] ) . "'";
	$result2 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	if ( mysqli_num_rows( $result2 ) == 0 ) { // there is no order matching
		// delete the blocks.
		echo "Deleting block #" . $row['block_id'] . "<br>";
		$sql = "DELETE from " . MDS_DB_PREFIX . "blocks WHERE block_id='" . intval( $row['block_id'] ) . "' AND banner_id='" . intval( $row['banner_id'] ) . "' ";
		mysqli_query( $GLOBALS['connection'], $sql );
	}
}

echo "Check Completed.";