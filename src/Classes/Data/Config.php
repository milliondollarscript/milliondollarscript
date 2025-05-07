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

use MillionDollarScript\Classes\Data\Options;

defined( 'ABSPATH' ) or exit;

/**
 * Class MillionDollarScript\Classes\Data\Config
 */
class Config {
	private static array $config;
	private static string $table_name = MDS_DB_PREFIX . 'config';

	/**
	 * MillionDollarScript\Classes\Data\Config constructor.
	 */
	public function __construct() {
	}

	/**
	 * Get config value from the database.
	 *
	 * @param $key
	 * @param bool $format
	 *
	 * @return object|false|string
	 */
	public static function get( $key, bool $format = false ): object|false|string {
		// Fetch via Options with default
		$option_name = str_replace('mds-', '', str_replace('_', '-', strtolower($key)));
		return Options::get_option( $option_name );
	}

	/**
	 * Sets a config value.
	 *
	 * @param int|string $value
	 * @param int|string $key
	 * @param string $type
	 *
	 * @return void
	 */
	public static function set( int|string $value, int|string $key, string $type = 's' ): void {
		// Update via Options
		$option_name = str_replace('_', '-', strtolower($key));
		Options::update_option($option_name, (string)$value);
	}

	/**
	 * @param $value
	 *
	 * @return string
	 */
	public static function format( $value ): string {
		global $f2;

		return $f2->value( $value, false );
	}

	/**
	 * Delete a record from the database table based on the provided key.
	 *
	 * @param string $key The key of the record to be deleted.
	 *
	 * @return void
	 * @throws void
	 */
	public static function delete( string $key ): bool {
		// Delete via Options
		$option_name = str_replace('_', '-', strtolower($key));
		return Options::delete($option_name);
	}
}