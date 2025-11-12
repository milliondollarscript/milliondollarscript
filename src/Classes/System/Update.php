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
		
		// Skip update checks if disabled
		if ( $updates === 'no' ) {
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
		
		// Create VCS API shim for extension server
		$vcs_api = CorePluginUpdateVcsApi::create( $extension_server_url, $updates );
		
		if ( $vcs_api === null ) {
			// Fallback: Plugin Update Checker library not available
			Logs::log(
				'Plugin Update Checker library not available for core plugin updates',
				'error'
			);
			return;
		}
		
		// Directly instantiate the VCS plugin update checker (bypass factory)
		$checker_class = '\\YahnisElsts\\PluginUpdateChecker\\v5p6\\Vcs\\PluginUpdateChecker';
		
		if ( ! class_exists( $checker_class ) ) {
			Logs::log(
				'VCS PluginUpdateChecker class not available',
				'error'
			);
			return;
		}
		
		$MDSUpdateChecker = new $checker_class(
			$vcs_api,              // VCS API object
			MDS_BASE_FILE,         // Plugin file path
			'milliondollarscript-two',  // Slug
			12,                    // Check period (hours)
			'',                    // Option name (auto-generated)
			''                     // MU plugin file (none)
		);
	}
}