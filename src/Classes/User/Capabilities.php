<?php

/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2024, Ryan Rhode
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

namespace MillionDollarScript\Classes\User;

use MillionDollarScript\Classes\Data\Options;

defined( 'ABSPATH' ) or exit;

class Capabilities {

	public static bool $permissions_enabled;

	public static function __callStatic( $name, $arguments ) {
		if ( $name == 'enabled' ) {
			if ( ! isset( self::$permissions_enabled ) ) {
				self::$permissions_enabled = Options::get_option( 'permissions', false );
			}

			return self::enabled( $arguments[0] );
		}

		return null;
	}

	/**
	 * Get array of capabilities.
	 */
	public static function get(): array {
		return [
			'mds_my_account'    => 'mds_my_account',
			'mds_order_pixels'  => 'mds_order_pixels',
			'mds_manage_pixels' => 'mds_manage_pixels',
			'mds_logout'        => 'mds_logout',
		];
	}

	/**
	 * Check if permissions are enabled for this capability.
	 *
	 * @param $capability string
	 *
	 * @return bool
	 */
	private static function enabled( string $capability ): bool {

		if ( ! isset( self::$permissions_enabled ) || ! self::$permissions_enabled ) {
			return false;
		}

		$capabilities = Options::get_option( 'capabilities', null, true );

		if ( isset( $capabilities ) && $capabilities !== false && in_array( $capability, $capabilities ) ) {
			return true;
		}

		return false;
	}
}
