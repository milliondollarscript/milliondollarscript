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

use MillionDollarScript\Classes\Data\Options;

function load_banner_row( $BID ) {

	if ( ! is_numeric( $BID ) ) {
		return false;
	}

	global $wpdb;
	$table_name = MDS_DB_PREFIX . 'banners';
	
	// Use $wpdb->prepare to safely handle the query with proper escaping
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE banner_id = %d",
			intval( $BID )
		),
		ARRAY_A
	);

	if ( ! $row ) {
		// No grid found with the given id so just select the first row.
		$row = $wpdb->get_row(
			"SELECT * FROM {$table_name} LIMIT 1",
			ARRAY_A
		);
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

	$row["G_BGCOLOR"]    = sanitize_hex_color( $row['bgcolor'] ) ?: '';
	$row["AUTO_APPROVE"] = $row['auto_approve'];
	$row["AUTO_PUBLISH"] = $row['auto_publish'];

	$row["G_MAX_ORDERS"] = intval($row['max_orders']);
	$row["G_MAX_BLOCKS"] = intval($row['max_blocks']);
	$row["G_MIN_BLOCKS"] = intval($row['min_blocks']);

	$row["TIME"] = intval($row['time_stamp']);

	return $row;
}

/**
 * Determine the effective background color for a grid container.
 *
 * Prefers the saved banner color, falls back to theme-specific grid colors,
 * and finally the current theme's primary background value.
 *
 * @param int   $BID          Banner/Grid ID.
 * @param array $banner_data  Banner data returned by load_banner_constants().
 *
 * @return string Sanitized hex color (e.g. "#101216") or an empty string when no color should be applied.
 */
function mds_get_grid_background_color( int $BID, array $banner_data ): string {
	$raw_color = $banner_data['G_BGCOLOR'] ?? ( $banner_data['bgcolor'] ?? '' );
	$color     = sanitize_hex_color( $raw_color ) ?: '';

	$theme_mode = Options::get_option( 'theme_mode', 'light' );

	// Allow theme-specific background overrides configured for the grid.
	if ( ( ! $color || strtolower( $color ) === '#ffffff' ) ) {
		$grid_theme_colors = get_option( 'mds_grid_bg_colors_' . $BID );
		if ( is_array( $grid_theme_colors ) ) {
			$theme_color = sanitize_hex_color( $grid_theme_colors[ $theme_mode ] ?? '' ) ?: '';
			if ( $theme_color ) {
				$color = $theme_color;
			}
		}
	}

	if ( ! $color ) {
		$fallback_option  = $theme_mode === 'dark' ? 'dark_main_background' : 'background_color';
		$fallback_default = $theme_mode === 'dark' ? '#101216' : '#ffffff';
		$color            = sanitize_hex_color( Options::get_option( $fallback_option, $fallback_default ) ) ?: '';
	}

	/**
	 * Filter the resolved grid background color before it is used.
	 *
	 * @param string $color        The resolved hex color (may be empty).
	 * @param int    $BID          Grid/Banner ID.
	 * @param array  $banner_data  Full banner data array.
	 * @param string $theme_mode   Active theme mode (light|dark).
	 */
	return apply_filters( 'mds_grid_background_color', $color, $BID, $banner_data, $theme_mode );
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
