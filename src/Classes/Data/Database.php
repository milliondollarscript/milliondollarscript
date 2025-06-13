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

use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

/**
 * Database Upgrades
 */
class Database {
	public function __construct() {
		$this->connect();
	}

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
			'email_disable_tokens',
			'mail_queue',
			'orders',
			'packages',
			'prices',
			'transactions',
			'views',
		];
	}

	/**
	 * Returns all old MDS database tables without prefixes.
	 *
	 * @return string[]
	 */
	public static function get_old_mds_tables(): array {
		return [
			'ads',
			'categories',
			'codes',
			'codes_translations',
			'currencies',
			'form_fields',
			'form_field_translations',
			'form_lists',
			'lang',
			'temp_orders',
			'users',
		];
	}

	/**
	 * Initialize the database connection and store in the global space.
	 *
	 * @return void
	 */
	public function connect(): void {
		if ( isset( $GLOBALS['connection'] ) ) {
			return;
		}

		require_once __DIR__ . '/Mdsdb.php';
		$conn                  = new Mdsdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$GLOBALS['connection'] = $conn->get_dbh();
	}

	public function __wakeup() {
		$this->connect();
	}

	/**
	 * Performs any necessary upgrades on the database.
	 *
	 * @return bool|string
	 * @throws \Exception
	 */
	public function upgrade(): bool|string {
		$db_version = $this->get_dbver();

		$current_version = DatabaseStatic::convert_version( $db_version );

		// Check for an older version and don't upgrade from them.
		if ( version_compare( $current_version, '1.3', '<' ) ) {
			$error_message = wp_sprintf( Language::get( 'You must upgrade the plugin to 2.3.5 before updating to %s.' ), MDS_VERSION );
			wp_die( $error_message, Language::get( 'Plugin Activation Error' ), array( 'response' => 400 ) );
		}

		if ( ! $this->requires_upgrade( $current_version ) ) {
			return false;
		}

		// Get list of upgrade files.
		$upgrade_files = glob( MDS_BASE_PATH . 'src/Upgrades/*.php' );

		// Sort upgrade files by version.
		usort( $upgrade_files, function ( $a, $b ) {
			$verA = str_replace( '_', '.', basename( $a, '.php' ) );
			$verB = str_replace( '_', '.', basename( $b, '.php' ) );

			return version_compare( $verA, $verB );
		} );

		// Perform upgrades.
		foreach ( $upgrade_files as $upgrade_file ) {

			// Replace underscores with periods in the upgrade file name to get the version.
			$upgrade_version = str_replace( '_', '.', basename( $upgrade_file, '.php' ) );

			// Remove leading periods.
			$upgrade_version = ltrim( $upgrade_version, '.' );

			if ( version_compare( $upgrade_version, $current_version, '>' ) ) {
				$classname = '\\MillionDollarScript\\Upgrades\\' . basename( $upgrade_file, '.php' );

				if ( ! class_exists( $classname ) ) {
					require_once $upgrade_file;
				}

				$upgrade = new $classname();
				$upgrade->upgrade( $current_version );

				// DO NOT Update database version inside the loop
			}
		}

		// Update database version ONCE after all upgrades have run
		self::up_dbver( MDS_DB_VERSION );

		// TODO: remember to update the DB version in /milliondollarscript-two.php

		return MDS_DB_VERSION;
	}

	/**
	 * Get database version
	 *
	 * @return string
	 */
	public function get_dbver(): string {
		// Try to get the version from WordPress options.
		$version = get_option( 'mds_db_version', false );

		if ( false === $version ) {
			// Option doesn't exist, likely first activation or upgrade from very old version.
			// Set the option to the current plugin DB version.
			add_option( 'mds_db_version', MDS_DB_VERSION );
			return MDS_DB_VERSION; // Return current version
		}

		// Return the version stored in options.
		return $version;
	}

	/**
	 * Update database version
	 *
	 * @param string $version The new version number.
	 * @return void
	 */
	public function update_dbver( string $version ): void {
		update_option( 'mds_db_version', $version );
	}

	/**
	 * Initialize database settings
	 *
	 * @return void
	 */
	public function initialize_database_settings(): void {
		// Check if initialization has already been done
		if ( get_option( 'mds_db_initialized' ) ) {
			return;
		}

		// This should only run ONCE during the very first activation.
		// Sets the initial DB version in the options table.
		$this->get_dbver(); // This ensures the mds_db_version option is created if it doesn't exist.

		// Mark initialization as done
		update_option( 'mds_db_initialized', true );
	}

	/**
	 * Checks if wp_mds_config table exists.
	 *
	 * @return bool
	 */
	public function mds_sql_installed(): bool {
		return DatabaseStatic::table_exists( MDS_DB_PREFIX . 'blocks' );
	}

	/**
	 * Checks if the database requires an upgrade.
	 *
	 * @param string $version
	 *
	 * @return bool
	 */
	public function requires_upgrade( string $version = '-1' ): bool {
		if ( $version == '-1' ) {
			$version = $this->get_dbver();
		}

		$version = DatabaseStatic::convert_version( $version );

		// Check for current database version
		if ( $version == MDS_DB_VERSION ) {
			return false;
		}

		return true;
	}

	/**
	 * Increment database version by 1 or set to given value.
	 *
	 * @param string $version
	 *
	 * @return string
	 */
	public function up_dbver( string $version = '0' ): string {
		if ( version_compare( $version, '0', '>' ) ) {
			$val = $version;
		} else {
			$val = MDS_DB_VERSION;
		}

		// Use WordPress options instead of deprecated config table
		update_option( 'mds_db_version', $val );

		return $val;
	}

	/**
	 * Set a value in the configuration table - DEPRECATED.
	 *
	 * @deprecated 2.5.12 Use Options::set() or update_option() instead.
	 *
	 * @param string $key The configuration key.
	 * @param string $val The value to set.
	 * @return void
	 */
	public function set_config( string $key, string $val ): void {
		_deprecated_function( __METHOD__, '2.5.12', 'Options::set() or update_option()' );
		// Only handle the known 'dbver' case for backward compatibility.
		if ( $key === 'dbver' ) {
			update_option( 'mds_db_version', $val );
		} else {
			// Do nothing for other keys, as the original table is gone.
			// error_log('Deprecated set_config called for unknown key: ' . $key);
		}
	}

	/**
	 * Get a value from the configuration table - DEPRECATED.
	 *
	 * @deprecated 2.5.12 Use Options::get() or get_option() instead.
	 *
	 * @param string $key The configuration key.
	 * @param mixed  $default The default value to return if the key is not found.
	 * @return mixed The configuration value or the default.
	 */
	public function get_config( string $key, $default = null ) {
		_deprecated_function( __METHOD__, '2.5.12', 'Options::get() or get_option()' );
		// Only handle the known 'dbver' case for backward compatibility.
		if ( $key === 'dbver' ) {
			return get_option( 'mds_db_version', $default );
		}

		// Return default for any other key, as the original table is gone.
		return $default;
	}
}