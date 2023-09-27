<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.0
 * @author      Ryan Rhode
 * @copyright   (C) 2023, Ryan Rhode
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
	 * @return bool
	 */
	public function upgrade(): bool {
		global $wpdb;

		require_once MDS_CORE_PATH . 'include/version.php';

		$version = $this->get_dbver();

		if ( ! $this->requires_upgrade( $version ) ) {
			return false;
		}

		if ( $version < 25 ) {

			// Convert Rank to Privileged Users
			$table_name = MDS_DB_PREFIX . "users";
			if ( $this->table_exists( $table_name ) ) {
				$results = $wpdb->get_results( "SELECT * FROM " . $table_name, ARRAY_A );

				if ( ! empty( $results ) ) {
					foreach ( $results as $row ) {
						if ( $row['Rank'] == '2' ) {
							update_user_meta( $row['ID'], '_' . MDS_PREFIX . 'privileged', '1' );
						}
					}
				}
			}

			// TODO: this did not work for some reason
			// Change config key column to config_key to prevent dbDelta conflicts.
			$table_name = MDS_DB_PREFIX . "config";
			if ( $this->table_exists( $table_name ) ) {
				$result = $wpdb->get_results(
					"SELECT COLUMN_NAME 
	FROM INFORMATION_SCHEMA.COLUMNS 
	WHERE table_name = '{$table_name}' 
	AND column_name = 'key'"
				);
				if ( ! empty( $result ) ) {
					$wpdb->query( "ALTER TABLE {$table_name} CHANGE `key` `config_key` VARCHAR(100) NOT NULL DEFAULT ''" );
				}
			}

			// TODO: delete extra tables or leave them?

			// Convert ads table to mds-pixel post type
			$table_name = MDS_DB_PREFIX . "ads";
			if ( $this->table_exists( $table_name ) ) {
				$results = $wpdb->get_results( "SELECT * FROM " . $table_name, ARRAY_A );

				if ( ! empty( $results ) ) {
					foreach ( $results as $row ) {

						// Get order status
						$order_status = $wpdb->get_var(
							$wpdb->prepare(
								"SELECT order_id from %s WHERE order_id=%d",
								MDS_DB_PREFIX . "orders",
								$row['order_id']
							)
						);

						// Insert post
						$post_content = array(
							'post_title'  => 'MDS Pixel - ' . $row['ad_id'],
							'post_type'   => 'mds-pixel',
							'post_status' => $order_status,
							'meta_input'  => array(
								'_mds_user_id'   => $row['user_id'],
								'_mds_ad_date'   => $row['ad_date'],
								'_mds_order_id'  => $row['order_id'],
								'_mds_banner_id' => $row['banner_id'],
								'_mds_1'         => $row['1'],
								'_mds_2'         => $row['2'],
								'_mds_3'         => $row['3']
							)
						);
						wp_insert_post( $post_content );
					}
				}
			}
		}

		// TODO: remember to update the DB version in /milliondollarscript-two.php

		return $version;
	}

	/**
	 * Get database version
	 *
	 * @return int
	 */
	public function get_dbver(): int {
		if ( get_option( MDS_PREFIX . 'activation' ) == '1' ) {
			return MDS_DB_VERSION;
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
					'%d'
				]
			);

			$version = MDS_DB_VERSION;
		} else {
			$version = intval( $result );
		}

		return $version;
	}

	/**
	 * Checks if a database table exists.
	 *
	 * @param $table_name
	 *
	 * @return true
	 */
	public function table_exists( $table_name ): bool {
		global $wpdb;

		$result = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );

		if ( $result != $table_name ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if wp_mds_config table exists.
	 *
	 * @return bool
	 */
	public function mds_sql_installed(): bool {
		return $this->table_exists( MDS_DB_PREFIX . 'blocks' );
	}

	/**
	 * Checks if the database requires an upgrade.
	 *
	 * @param int $version
	 *
	 * @return bool
	 */
	public function requires_upgrade( int $version = - 1 ): bool {
		if ( $version == - 1 ) {
			$version = $this->get_dbver();
		}

		// Check for current database version
		if ( $version > 0 && $version == MDS_DB_VERSION ) {
			return false;
		}

		return true;
	}

	/**
	 * Increment database version by 1 or set to given value.
	 *
	 * @param int $version
	 *
	 * @return int
	 */
	public function up_dbver( int $version = 0 ): int {
		global $wpdb;

		if ( $version > 0 ) {
			$val = $version;
		} else {
			$val = 1;
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
				'%d',
			],
			[
				'%s'
			]
		);

		if ( $version > 0 ) {
			return $version;
		}

		$sql   = "SELECT `val` FROM `" . MDS_DB_PREFIX . "config` WHERE `config_key`='dbver';";
		$dbver = $wpdb->get_var( $sql );

		return intval( $dbver );
	}
}