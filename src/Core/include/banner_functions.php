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

function load_banner_row( $BID ) {

	if ( ! is_numeric( $BID ) ) {
		return false;
	}

	$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "banners` WHERE `banner_id`='" . intval( $BID ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );

	if ( ! $row ) {
		// No grid found with the given id so just select the first row.
		$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "banners` LIMIT 1";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$row = mysqli_fetch_array( $result );
	}

	return $row;
}

function load_banner_constants( $BID ): ?array {

	$row = load_banner_row( $BID );

	// Check non-existent grid.
	if ( $row == null ) {
		return null;
	}

	// defaults

	if ( ! $row['block_width'] ) {
		$row['block_width'] = 10;
	}
	if ( ! $row['block_height'] ) {
		$row['block_height'] = 10;
	}

	if ( ! $row['grid_block'] ) {
		$row['grid_block'] = get_default_image( 'grid_block' );
	}
	if ( ! $row['nfs_block'] ) {
		$row['nfs_block'] = get_default_image( 'nfs_block' );
	}
	if ( ! $row['usr_grid_block'] ) {
		$row['usr_grid_block'] = get_default_image( 'usr_grid_block' );
	}
	if ( ! $row['usr_nfs_block'] ) {
		$row['usr_nfs_block'] = get_default_image( 'usr_nfs_block' );
	}
	if ( ! $row['usr_sel_block'] ) {
		$row['usr_sel_block'] = get_default_image( 'usr_sel_block' );
	}
	if ( ! $row['usr_ord_block'] ) {
		$row['usr_ord_block'] = get_default_image( 'usr_ord_block' );
	}
	if ( ! $row['usr_res_block'] ) {
		$row['usr_res_block'] = get_default_image( 'usr_res_block' );
	}
	if ( ! $row['usr_sol_block'] ) {
		$row['usr_sol_block'] = get_default_image( 'usr_sol_block' );
	}

	// define constants

	$row["G_NAME"]     = $row['name'];
	$row["G_PRICE"]    = floatval($row['price_per_block']);
	$row["G_CURRENCY"] = $row['currency'];

	$row["DAYS_EXPIRE"] = intval($row['days_expire']);

	$row["BLK_WIDTH"]  = intval($row['block_width']);
	$row["BLK_HEIGHT"] = intval($row['block_height']);
	$row["BANNER_ID"]  = intval($row['banner_id']);
	$row["G_WIDTH"]    = intval($row['grid_width']);
	$row["G_HEIGHT"]   = intval($row['grid_height']);

	$row["NFS_COVERED"] = $row['nfs_covered'];

	$row["GRID_BLOCK"] = base64_decode( $row['grid_block'] );
	$row["NFS_BLOCK"]  = base64_decode( $row['nfs_block'] );

	$row["USR_GRID_BLOCK"] = base64_decode( $row['usr_grid_block'] );
	$row["USR_NFS_BLOCK"]  = base64_decode( $row['usr_nfs_block'] );
	$row["USR_SEL_BLOCK"]  = base64_decode( $row['usr_sel_block'] );
	$row["USR_ORD_BLOCK"]  = base64_decode( $row['usr_ord_block'] );
	$row["USR_RES_BLOCK"]  = base64_decode( $row['usr_res_block'] );
	$row["USR_SOL_BLOCK"]  = base64_decode( $row['usr_sol_block'] );

	$row["G_BGCOLOR"]    = $row['bgcolor'];
	$row["AUTO_APPROVE"] = $row['auto_approve'];
	$row["AUTO_PUBLISH"] = $row['auto_publish'];

	$row["G_MAX_ORDERS"] = intval($row['max_orders']);
	$row["G_MAX_BLOCKS"] = intval($row['max_blocks']);
	$row["G_MIN_BLOCKS"] = intval($row['min_blocks']);

	$row["TIME"] = intval($row['time_stamp']);

	//$row["BANNER_ROW", serialize($row));

	return $row;
}

function get_default_image( $image_name ) {

	switch ( $image_name ) {

		case "grid_block":
			return "iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAHklEQVR4nGO8cuUKA27AwsDAoK2tjUuaCY/W4SwNAJbvAxP1WmxKAAAAAElFTkSuQmCC";

		//temp/not_for_sale_block.png
		case "nfs_block":
			return "iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAFUlEQVR4nGP8//8/A27AhEduBEsDAKXjAxF9kqZqAAAAAElFTkSuQmCC";
		//../bg-main.gif
		case "tile":
			return "iVBORw0KGgoAAAANSUhEUgAAAHgAAAB4AQMAAAADqqSRAAAABlBMVEXW19b///9ZVCXjAAAAJklEQVR4nGNgQAP197///Y8gBpw/6r5R9426b9R9o+4bdd8wdB8AiRh20BqKw9IAAAAASUVORK5CYII=";

		//--------------------------------------------------------------------------------
		//../users/block.png

		case "usr_grid_block":
			return "iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAHklEQVR4nGO8cuUKA27AwsDAoK2tjUuaCY/W4SwNAJbvAxP1WmxKAAAAAElFTkSuQmCC";

		//../users/not_for_sale_block.png
		case "usr_nfs_block":
			return "iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAFUlEQVR4nGP8//8/A27AhEduBEsDAKXjAxF9kqZqAAAAAElFTkSuQmCC";
		//../users/ordered_block.png
		case "usr_ord_block":
			return "iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAFElEQVR4nGP83+DAgBsw4ZEbwdIAJ/sB02xWjpQAAAAASUVORK5CYII=";
		//../users/reserved_block.png
		case "usr_res_block":
			return "iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAE0lEQVR4nGP8/58BD2DCJzlypQF0BwISHGyJPgAAAABJRU5ErkJggg==";

		//../users/selected_block.png
		case "usr_sel_block":
			return "iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAE0lEQVR4nGNk+M+ABzDhkxy50gBALQETmXEDiQAAAABJRU5ErkJggg==";

		//../users/sold_block.png
		case "usr_sol_block":
			return "iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAEklEQVR4nGP8z4APMOGVHbHSAEEsAROxCnMTAAAAAElFTkSuQmCC";
	}
}
