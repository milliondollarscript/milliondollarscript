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

use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

// select all the blocks...
global $wpdb;

$sql    = "SELECT order_id, block_id, banner_id FROM " . MDS_DB_PREFIX . "blocks WHERE status <> 'nfs'"; // nfs blocks do not have an order.
$result = $wpdb->get_results( $sql, ARRAY_A );

foreach ( $result as $row ) {

	$sql = "SELECT order_id FROM " . MDS_DB_PREFIX . "orders WHERE banner_id='" . intval( $row['banner_id'] ) . "' AND  order_id='" . intval( $row['order_id'] ) . "'";
	$result2 = $wpdb->get_results( $sql, ARRAY_A );
	if ( count( $result2 ) == 0 ) { // there is no order matching
		// delete the blocks.
		echo "Deleting block #" . $row['block_id'] . "<br>";
		$sql = "DELETE from " . MDS_DB_PREFIX . "blocks WHERE block_id='" . intval( $row['block_id'] ) . "' AND banner_id='" . intval( $row['banner_id'] ) . "' ";
		$wpdb->query( $sql );
	}
}
Language::out( "Check Completed." );
