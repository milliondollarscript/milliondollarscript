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

namespace MillionDollarScript\Classes\Data;

use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

/**
 * Static Database Functions
 */
class DatabaseStatic {

	/**
	 * Returns all MDS database tables without prefixes.
	 *
	 * @return string[]
	 */
	public static function get_mds_tables(): array {
		return [
			'banners',
			'blocks',
			'clicks',
			'config',
			'mail_queue',
			'orders',
			'packages',
			'prices',
			'transactions',
			'views',
		];
	}

	/**
	 * Converts old database version format to the new version format.
	 * Old versions of the dbver were integers like 1,10,13 and the new format is a string like '2.5.2'.
	 *
	 * @param string $version
	 *
	 * @return string
	 */
	public static function convert_version( string $version ): string {
		if ( str_contains( $version, '.' ) ) {
			return $version;
		} else {
			return implode( '.', str_split( $version ) );
		}
	}

	/**
	 * Checks if a database table exists.
	 *
	 * @param $table_name
	 *
	 * @return true
	 */
	public static function table_exists( $table_name ): bool {
		global $wpdb;

		$result = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );

		if ( $result != $table_name ) {
			return false;
		}

		return true;
	}

	public static function update_paths(): void {
		global $wpdb;

		// Plugin has been renamed so there will be new paths.

		//	Note: DO NOT update logo

		$wpdb->update(
			'config',
			array( 'val' => MDS_CORE_URL ),
			array( 'key' => 'BASE_HTTP_PATH' ),
			array( '%s' ),
			array( '%s' )
		);

		$wpdb->update(
			'config',
			array( 'val' => untrailingslashit( realpath( MDS_CORE_PATH ) ) ),
			array( 'key' => 'BASE_PATH' ),
			array( '%s' ),
			array( '%s' )
		);

		$wpdb->update(
			'config',
			array( 'val' => trailingslashit( realpath( MDS_CORE_PATH . 'admin' ) ) ),
			array( 'key' => 'SERVER_PATH_TO_ADMIN' ),
			array( '%s' ),
			array( '%s' )
		);

		$wpdb->update(
			'config',
			array( 'val' => Utility::get_upload_path() ),
			array( 'key' => 'UPLOAD_PATH' ),
			array( '%s' ),
			array( '%s' )
		);

		$wpdb->update(
			'config',
			array( 'val' => Utility::get_upload_url() ),
			array( 'key' => 'UPLOAD_HTTP_PATH' ),
			array( '%s' ),
			array( '%s' )
		);

		update_option( '_' . MDS_DB_PREFIX . 'path', MDS_CORE_PATH );
	}
}