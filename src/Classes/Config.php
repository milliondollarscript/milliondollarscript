<?php

/**
 * Million Dollar Script Two
 *
 * @version 2.3.2
 * @author Ryan Rhode
 * @copyright (C) 2022, Ryan Rhode
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
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
 */

namespace MillionDollarScript\Classes;

defined( 'ABSPATH' ) or exit;

class Config {

	/**
	 * Get a value from the MDS config.php file.
	 *
	 * @param $key
	 *
	 * @return mixed|null null if users integration is disabled or config.php wasn't found at the path in the options or value wasn't found.
	 */
	public static function get( $key ) {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			exit( __( 'Error retrieving MDS config value for ' . esc_html( $key ) . '.', 'milliondollarscript' ) );
		}

		return $wpdb->get_var( $wpdb->prepare( "SELECT `val` FROM `" . MDS_DB_PREFIX . "config` WHERE `key` = %s", $key ) );
	}
}


