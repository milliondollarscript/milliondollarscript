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

namespace MillionDollarScript\Upgrades;

use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\System\Logs;

defined( 'ABSPATH' ) or exit;

/** @noinspection PhpUnused */

class _2_6_5 {

	/**
	 * Upgrade to version 2.6.5
	 *
	 * This upgrade migrates the plugin update channel settings from old branch names
	 * to the new release channel names:
	 * - 'main' → 'stable'
	 * - 'snapshot' → 'beta'
	 * - 'dev' → 'alpha'
	 * - 'disabled' → 'disabled' (unchanged)
	 * Default value: 'stable' for any installations without this option set
	 *
	 * @param string $version Current database version
	 *
	 * @return void
	 */
	public function upgrade( $version ): void {
		if ( version_compare( $version, '2.6.5', '<' ) ) {

			Logs::log( 'MDS Upgrade _2_6_5 starting - Migrating plugin update channel settings.' );

			try {
				$this->migrate_update_channel_settings();
				Logs::log( 'MDS Upgrade _2_6_5 completed successfully.' );
			} catch ( \Exception $e ) {
				Logs::log( 'MDS Upgrade _2_6_5 failed: ' . $e->getMessage() );
				// Don't throw - this is non-critical, just log the error
			}
		}
	}

	/**
	 * Migrate update channel settings from old branch names to new release channel names.
	 *
	 * @return void
	 */
	private function migrate_update_channel_settings(): void {
		$current_channel = Options::get_option( 'updates', 'stable' );

		// Map old values to new values
		$migration_map = [
			'main'     => 'stable',
			'snapshot' => 'beta',
			'dev'      => 'alpha',
			'disabled' => 'disabled',
		];

		// Handle null or empty values - set default
		if ( empty( $current_channel ) || is_null( $current_channel ) ) {
			Options::update_option( 'updates', 'stable' );
			Logs::log( "Plugin update channel set to default 'stable' (was empty/null)" );
			return;
		}

		// Check if current channel needs migration
		if ( isset( $migration_map[ $current_channel ] ) ) {
			$new_channel = $migration_map[ $current_channel ];
			
			// Only update if the value actually changed
			if ( $new_channel !== $current_channel ) {
				Options::update_option( 'updates', $new_channel );
				Logs::log( "Plugin update channel migrated from '{$current_channel}' to '{$new_channel}'" );
			} else {
				Logs::log( "Plugin update channel already correct: '{$current_channel}'" );
			}
		} else {
			// Unexpected value - log warning but set to default stable
			Logs::log( "Plugin update channel had unexpected value '{$current_channel}' - setting to default 'stable'" );
			Options::update_option( 'updates', 'stable' );
		}
	}
}