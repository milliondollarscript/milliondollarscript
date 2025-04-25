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

namespace MillionDollarScript\Classes\Admin;

defined( 'ABSPATH' ) or exit;

/**
 * Handles storing and displaying admin notices.
 */
class Notices {

	private static $transient_key = 'mds_admin_notices';

	/**
	 * Add a notice to be displayed on the next admin screen load.
	 *
	 * @param string $message The message to display.
	 * @param string $type    The type of notice (e.g., 'success', 'error', 'warning', 'info'). Default 'info'.
	 */
	public static function add_notice( string $message, string $type = 'info' ): void {
		$notices = get_transient( self::$transient_key );
		if ( ! is_array( $notices ) ) {
			$notices = [];
		}
		$notices[] = [
			'message' => $message,
			'type'    => $type,
		];
		set_transient( self::$transient_key, $notices, 60 ); // Store for 60 seconds
	}

	/**
	 * Display stored admin notices and clear them.
	 * Hooked to 'admin_notices'.
	 */
	public static function display_notices(): void {
		$notices = get_transient( self::$transient_key );

		if ( is_array( $notices ) && ! empty( $notices ) ) {
			foreach ( $notices as $notice ) {
				$message = $notice['message'] ?? '';
				$type    = $notice['type'] ?? 'info';
				// Ensure type is one of the allowed classes
				if ( ! in_array( $type, [ 'success', 'error', 'warning', 'info' ], true ) ) {
					$type = 'info';
				}
				printf(
					'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
					esc_attr( $type ),
					wp_kses_post( $message ) // Allow basic HTML in messages
				);
			}
			// Clear the notices after displaying
			delete_transient( self::$transient_key );
		}
	}
}
