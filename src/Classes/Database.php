<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.10
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

namespace MillionDollarScript\Classes;

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
			'mail_queue',
			'orders',
			'packages',
			'prices',
			'transactions',
			'views',
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
		global $wpdb;

		require_once MDS_CORE_PATH . 'include/version.php';

		$db_version = $this->get_dbver();

		$current_version = DatabaseStatic::convert_version( $db_version );

		// Change config key column to config_key here since we're going to use it.
		if ( version_compare( MDS_DB_VERSION, $current_version, '>' ) ) {
			$table_name = MDS_DB_PREFIX . "config";
			if ( DatabaseStatic::table_exists( $table_name ) ) {
				$result = $wpdb->get_results(
					"SELECT COLUMN_NAME 
					FROM INFORMATION_SCHEMA.COLUMNS 
					WHERE table_name = '{$table_name}'
			  		AND column_name = 'key'"
				);
				if ( ! empty( $result ) ) {
					$wpdb->query( "ALTER TABLE `{$table_name}` CHANGE `key` `config_key` VARCHAR(100) NOT NULL DEFAULT ''" );
					$wpdb->flush();
				}
			}
		}

		// Check for an older version and don't upgrade from them.
		if ( version_compare( $current_version, '1.3', '<' ) ) {
			$error_message = wp_sprintf( Language::get( 'You must upgrade the plugin to 2.3.5 before updating to %s.' ), MDS_VERSION );
			wp_die( $error_message, Language::get( 'Plugin Activation Error' ), array( 'response' => 400 ) );
		}

		if ( ! $this->requires_upgrade( $current_version ) ) {
			return false;
		}

		// $charset_collate = $wpdb->get_charset_collate();

		// Add BUILD_DATE to config
		// $sql = "UPDATE `" . MDS_DB_PREFIX . "config` SET `val`='" . get_mds_build_date() . "' WHERE `key`='BUILD_DATE';";
		// $wpdb->query( $sql );

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

				// Update database version.
				self::up_dbver( $upgrade_version );
			}
		}

		// TODO: remember to update the DB version in /milliondollarscript-two.php

		return MDS_DB_VERSION;
	}

	/**
	 * Get database version
	 *
	 * @return string
	 */
	public function get_dbver(): string {
		if ( get_option( MDS_PREFIX . 'activation' ) == '1' ) {
			// If plugin is currently being activated check if the config table exists.
			$table_name = MDS_DB_PREFIX . "config";
			if ( ! DatabaseStatic::table_exists( $table_name ) ) {
				// If the config table doesn't exist then this is the first time the plugin has been activated so return the database version of the plugin.
				return MDS_DB_VERSION;
			}
		}

		global $wpdb;

		$sql    = "SELECT `val` FROM `" . MDS_DB_PREFIX . "config` WHERE `config_key`='dbver';";
		$result = $wpdb->get_var( $sql );
		if ( $wpdb->num_rows == 0 ) {

			// add database version config value
			$wpdb->insert(
				MDS_DB_PREFIX . 'config',
				[
					'config_key' => 'dbver',
					'val'        => MDS_DB_VERSION
				],
				[
					'%s',
					'%s'
				]
			);

			$version = MDS_DB_VERSION;
		} else {
			$version = $result;
		}

		return $version;
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
		global $wpdb;

		if ( version_compare( $version, '0', '>' ) ) {
			$val = $version;
		} else {
			$val = MDS_DB_VERSION;
		}

		$wpdb->update(
			MDS_DB_PREFIX . 'config',
			[
				'val' => $val,
			],
			[
				'config_key' => 'dbver'
			],
			[
				'%s',
			],
			[
				'%s'
			]
		);

		if ( $val == $version ) {
			return $version;
		}

		$sql = "SELECT `val` FROM `" . MDS_DB_PREFIX . "config` WHERE `config_key`='dbver';";

		return $wpdb->get_var( $sql );
	}
}