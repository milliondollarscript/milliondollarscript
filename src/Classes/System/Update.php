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

namespace MillionDollarScript\Classes\System;

use MillionDollarScript\Classes\Data\Options;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined( 'ABSPATH' ) or exit;

class Update {
	public static function checker(): void {
		global $MDSUpdateChecker;
		$updates = Options::get_option( 'updates', 'stable' );

		Logs::log(
			sprintf(
				'Core update checker bootstrap (MDS_VERSION=%s, updates=%s)',
				defined( 'MDS_VERSION' ) ? MDS_VERSION : 'unknown',
				$updates
			),
			Logs::LEVEL_DEBUG
		);
		
		// Skip update checks if disabled
		if ( $updates === 'no' ) {
			Logs::log( 'Core update checker skipped because updates option is set to "no".', Logs::LEVEL_DEBUG );
			return;
		}
		
		// Get extension server URL from option or constant
		$extension_server_url = Options::get_option( 'extension_server_url', '' );
		if ( empty( $extension_server_url ) && defined( 'MDS_EXTENSION_SERVER_URL' ) ) {
			$extension_server_url = MDS_EXTENSION_SERVER_URL;
		}
		if ( empty( $extension_server_url ) ) {
			$extension_server_url = 'https://milliondollarscript.com';
		}

		Logs::log(
			sprintf( 'Core update checker using extension server URL: %s', $extension_server_url ),
			Logs::LEVEL_DEBUG
		);
		
		$MDSUpdateChecker = new CorePluginUpdateChecker(
			$extension_server_url,
			MDS_BASE_FILE,
			'milliondollarscript-two',
			$updates
		);

		Logs::log( 'Core update checker successfully instantiated and scheduled.', Logs::LEVEL_DEBUG );
	}
}
