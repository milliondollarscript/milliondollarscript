<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.1
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
	 * @return bool|int
	 * @throws \Exception
	 */
	public function upgrade(): bool|int {
		global $wpdb;

		// Change config key column to config_key here since we're going to use it.
		$table_name = MDS_DB_PREFIX . "config";
		if ( $this->table_exists( $table_name ) ) {
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

		require_once MDS_CORE_PATH . 'include/version.php';

		$version = $this->get_dbver();

		// Check for an older version and don't upgrade from them.
		if ( $version < 13 ) {
			$error_message = wp_sprintf( Language::get( 'You must upgrade the plugin to 2.3.5 before updating to %s.' ), MDS_VERSION );
			wp_die( $error_message, Language::get( 'Plugin Activation Error' ), array( 'response' => 400 ) );
		}

		if ( ! $this->requires_upgrade( $version ) ) {
			return false;
		}

		if ( $version < 25 ) {

			// Convert Rank to Privileged Users
			$users_map  = [];
			$table_name = MDS_DB_PREFIX . "users";
			if ( $this->table_exists( $table_name ) ) {
				$results = $wpdb->get_results( "SELECT * FROM " . $table_name, ARRAY_A );
				if ( ! empty( $results ) ) {
					foreach ( $results as $row ) {

						// Get WP user by email.
						$user = get_user_by( 'email', $row['Email'] );
						if ( $user ) {

							// Get user id.
							$user_id = $user->ID;

							// Save user id in users map.
							$users_map[ intval( $row['ID'] ) ] = $user_id;

							// Set privileged users.
							if ( $row['Rank'] == '2' ) {
								update_user_meta( $user_id, '_' . MDS_PREFIX . 'privileged', '1' );
							}
						}
					}
				}
			}

			// TODO: Add a button/option to delete old unused tables in the admin.
			// TODO: Add a way to migrate massive amounts of records which could be useful for other things.

			// Convert ads table to mds-pixel post type
			$table_name = MDS_DB_PREFIX . "ads";
			if ( $this->table_exists( $table_name ) ) {
				$results = $wpdb->get_results( "SELECT * FROM " . $table_name, ARRAY_A );

				if ( ! empty( $results ) ) {
					foreach ( $results as $row ) {

						// If the user doesn't exist in the users map skip it.
						if ( ! isset( $users_map[ $row['user_id'] ] ) ) {
							continue;
						}

						// Get order status
						$order_status = $wpdb->get_var(
							$wpdb->prepare(
								"SELECT `status` FROM `" . MDS_DB_PREFIX . "orders` WHERE order_id = %d",
								$row['order_id']
							)
						);

						// Check if post exists before inserting it.
						$post_title  = 'MDS Pixel - ' . $row['ad_id'];
						$post_exists = post_exists( $post_title );
						if ( ! $post_exists ) {

							// Insert image attachment
							$attachment_id = 0;
							// TODO: Add more checks for the field type to make sure it's the default image field.
							if ( ! empty( $row['3'] ) ) {
								$image_file = Utility::get_upload_path() . "images/" . $row['3'];
								if ( file_exists( $image_file ) ) {
									$filetype      = wp_check_filetype( $image_file );
									$mime_type     = $filetype['type'];
									$attachment    = [
										'post_mime_type' => $mime_type,
										'post_title'     => basename( $image_file ),
										'post_content'   => '',
										'post_status'    => 'inherit'
									];
									$attachment_id = wp_insert_attachment( $attachment, $image_file );
									$attach_data   = wp_generate_attachment_metadata( $attachment_id, $image_file );
									wp_update_attachment_metadata( $attachment_id, $attach_data );
								}
							}

							// Insert mds-pixel post.
							$post_content = [
								'post_title'  => $post_title,
								'post_type'   => 'mds-pixel',
								'post_status' => $order_status,
								'post_date'   => $row['ad_date'],
								'post_author' => $users_map[ $row['user_id'] ],
								'meta_input'  => [
									'_milliondollarscript_order' => $row['order_id'],
									'_milliondollarscript_grid'  => $row['banner_id'],
									'_milliondollarscript_text'  => $row['1'],
									'_milliondollarscript_url'   => $row['2'],
									'_milliondollarscript_image' => empty( $attachment_id ) ? '' : $attachment_id
								]
							];
							wp_insert_post( $post_content );
						}
					}
				}
			}
		}

		// TODO: remember to update the DB version in /milliondollarscript-two.php

		return MDS_DB_VERSION;
	}

	/**
	 * Get database version
	 *
	 * @return int
	 */
	public function get_dbver(): int {
		if ( get_option( MDS_PREFIX . 'activation' ) == '1' ) {
			// If plugin is currently being activated check if the config table exists.
			$table_name = MDS_DB_PREFIX . "config";
			if ( ! $this->table_exists( $table_name ) ) {
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