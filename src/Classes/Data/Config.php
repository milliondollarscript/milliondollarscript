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
	 * Default config values.
	 *
	 * @return array
	 */
	public static function defaults(): array {

		require_once realpath( MDS_CORE_PATH . 'include/version.php' );
		$build_date = get_mds_build_date();

		return [
			'DEBUG'                       => [ 'value' => 0, 'type' => 'd' ],
			'MDS_LOG'                     => [ 'value' => 0, 'type' => 'd' ],
			'MDS_LOG_FILE'                => [ 'value' => MDS_CORE_PATH . '.mds.log', 'type' => 's' ],
			'BUILD_DATE'                  => [ 'value' => $build_date, 'type' => 's' ],
			'VERSION_INFO'                => [ 'value' => MDS_VERSION, 'type' => 's' ],
			'MDS_RESIZE'                  => [ 'value' => 'YES', 'type' => 's' ],
			'OUTPUT_JPEG'                 => [ 'value' => 'N', 'type' => 's' ],
			'JPEG_QUALITY'                => [ 'value' => '75', 'type' => 's' ],
			'INTERLACE_SWITCH'            => [ 'value' => 'YES', 'type' => 's' ],
			'DISPLAY_PIXEL_BACKGROUND'    => [ 'value' => 'NO', 'type' => 's' ],
			'EMAIL_USER_ORDER_CONFIRMED'  => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_ADMIN_ORDER_CONFIRMED' => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_USER_ORDER_COMPLETED'  => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_ADMIN_ORDER_COMPLETED' => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_USER_ORDER_PENDED'     => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_ADMIN_ORDER_PENDED'    => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_USER_ORDER_EXPIRED'    => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_ADMIN_ORDER_EXPIRED'   => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_USER_ORDER_DENIED'     => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_ADMIN_ORDER_DENIED'    => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_ADMIN_PUBLISH_NOTIFY'  => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_USER_EXPIRE_WARNING'   => [ 'value' => '', 'type' => 's' ],
			'EMAILS_DAYS_KEEP'            => [ 'value' => '30', 'type' => 's' ],
			'MINUTES_RENEW'               => [ 'value' => '10080', 'type' => 's' ],
			'MINUTES_CONFIRMED'           => [ 'value' => '10080', 'type' => 's' ],
			'MINUTES_UNCONFIRMED'         => [ 'value' => '60', 'type' => 's' ],
			'MINUTES_CANCEL'              => [ 'value' => '4320', 'type' => 's' ],
			'ENABLE_MOUSEOVER'            => [ 'value' => 'POPUP', 'type' => 's' ],
			'TOOLTIP_TRIGGER'             => [ 'value' => 'click', 'type' => 's' ],
			'ENABLE_CLOAKING'             => [ 'value' => 'YES', 'type' => 's' ],
			'VALIDATE_LINK'               => [ 'value' => 'NO', 'type' => 's' ],
			'ADVANCED_CLICK_COUNT'        => [ 'value' => 'YES', 'type' => 's' ],
			'ADVANCED_VIEW_COUNT'         => [ 'value' => 'YES', 'type' => 's' ],
			'EMAILS_PER_BATCH'            => [ 'value' => '12', 'type' => 's' ],
			'EMAILS_MAX_RETRY'            => [ 'value' => '15', 'type' => 's' ],
			'EMAILS_ERROR_WAIT'           => [ 'value' => '20', 'type' => 's' ],
			'USE_AJAX'                    => [ 'value' => 'SIMPLE', 'type' => 's' ],
			'MEMORY_LIMIT'                => [ 'value' => '256M', 'type' => 's' ],
			'REDIRECT_SWITCH'             => [ 'value' => 'NO', 'type' => 's' ],
			'REDIRECT_URL'                => [ 'value' => 'https://www.example.com', 'type' => 's' ],
			'MDS_AGRESSIVE_CACHE'         => [ 'value' => 'NO', 'type' => 's' ],
			'BLOCK_SELECTION_MODE'        => [ 'value' => 'YES', 'type' => 's' ],
			'STATS_DISPLAY_MODE'          => [ 'value' => 'PIXELS', 'type' => 's' ],
			'INVERT_PIXELS'               => [ 'value' => 'YES', 'type' => 's' ],
			'LAST_EXPIRE_RUN'             => [ 'value' => 1138243912, 'type' => 'd' ],
		];
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
		if ( is_array( self::$config ) && isset( self::$config[ $key ] ) ) {
			if ( $format ) {
				return self::format( self::$config[ $key ] );
			}

			return self::$config[ $key ];
		}

		$sql = "SELECT `val` FROM `" . self::$table_name . "` WHERE `config_key` = %s";

		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( $sql, [ $key ] ) );

		if ( $format && isset( $value ) ) {
			return self::format( $value );
		}

		if ( ! isset( $value ) ) {
			$defaults = self::defaults();

			return $defaults[ $key ]['value'];
		}

		return $value;
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
		global $wpdb;

		$wpdb->update(
			MDS_DB_PREFIX . "config",
			[
				'val' => $value,
			],
			[
				'config_key' => $key,
			],
			[
				'%' . $type,
			],
			[ '%s' ]
		);
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
	 * Get associative array of config key => values from the database.
	 *
	 * @return array
	 */
	public static function get_results_assoc(): array {
		global $wpdb;

		// Make sure we get new values
		$wpdb->flush();

		$results = $wpdb->get_results( "SELECT `config_key`, `val` FROM `" . self::$table_name . "`", ARRAY_A );

		$assoc_array = [];
		foreach ( $results as $row ) {
			$assoc_array[ $row['config_key'] ] = $row['val'];
		}

		return $assoc_array;
	}

	/**
	 * Verify and repair the config table to ensure it has the correct structure.
	 * This will check for and fix known issues with the table structure.
	 * 
	 * @return boolean True if the table is verified and ready to use, false if there are unfixable problems.
	 */
	public static function verify_and_repair_config_table(): bool {
		global $wpdb;
		$table_name = self::$table_name;
		
		// Check if table exists using WordPress's built-in method
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
		error_log("MDS Config::verify_and_repair_config_table - Config table '{$table_name}' exists: " . ($table_exists ? 'yes' : 'no'));
		
		if (!$table_exists) {
			// Table doesn't exist, it will be created when needed
			return true;
		}
		
		// Check table structure
		$table_structure = $wpdb->get_results("DESCRIBE {$table_name}");
		if (empty($table_structure)) {
			error_log("MDS Config::verify_and_repair_config_table - Could not retrieve table structure for '{$table_name}'");
			return false;
		}
		
		// Check if required columns exist and have correct structure
		$has_config_key = false;
		$has_val = false;
		$config_key_is_primary = false;
		
		foreach ($table_structure as $column) {
			if ($column->Field === 'config_key') {
				$has_config_key = true;
				if ($column->Key === 'PRI') {
					$config_key_is_primary = true;
				}
			}
			if ($column->Field === 'val') {
				$has_val = true;
			}
		}
		
		error_log("MDS Config::verify_and_repair_config_table - Table structure check: config_key exists: " . ($has_config_key ? 'yes' : 'no') . 
			", val exists: " . ($has_val ? 'yes' : 'no') . 
			", config_key is primary: " . ($config_key_is_primary ? 'yes' : 'no'));
		
		// If structure is incorrect, try to fix it
		if (!$has_config_key || !$has_val || !$config_key_is_primary) {
			// Severe structural issues - back up and recreate the table
			try {
				// Back up existing data if possible
				$backup_data = [];
				if ($has_config_key && $has_val) {
					$rows = $wpdb->get_results("SELECT * FROM {$table_name} WHERE config_key IS NOT NULL AND config_key != '' AND TRIM(config_key) != ''");
					foreach ($rows as $row) {
						if (!empty($row->config_key)) {
							$backup_data[$row->config_key] = $row->val;
						}
					}
					error_log("MDS Config::verify_and_repair_config_table - Backed up " . count($backup_data) . " valid config entries");
				}
				
				// Drop and recreate the table
				error_log("MDS Config::verify_and_repair_config_table - Dropping and recreating config table due to structural issues");
				$wpdb->query("DROP TABLE IF EXISTS {$table_name}");
				
				$charset_collate = $wpdb->get_charset_collate();
				$sql = "CREATE TABLE {$table_name} (
					config_key varchar(255) NOT NULL,
					val text NOT NULL,
					PRIMARY KEY  (config_key)
				) {$charset_collate};";
				
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta($sql);
				
				// Restore backed up data if we have any
				if (!empty($backup_data)) {
					foreach ($backup_data as $key => $value) {
						if (!empty($key)) {
							$wpdb->insert(
								$table_name,
								[
									'config_key' => $key,
									'val'        => $value,
								],
								['%s', '%s']
							);
						}
					}
					error_log("MDS Config::verify_and_repair_config_table - Restored " . count($backup_data) . " config entries");
				}
				
				return true;
			} catch (\Exception $e) {
				error_log("MDS Config::verify_and_repair_config_table - Failed to repair table: " . $e->getMessage());
				return false;
			}
		}
		
		// Check for and fix any problematic rows
		try {
			// Look for empty or NULL config_key entries
			$wpdb->query("DELETE FROM `{$table_name}` WHERE `config_key` IS NULL OR `config_key` = '' OR TRIM(`config_key`) = ''");
			
			// Look for invisible characters that might be causing problems
			$suspicious_rows = $wpdb->get_results("SELECT * FROM `{$table_name}` WHERE LENGTH(`config_key`) = 0 OR LENGTH(TRIM(`config_key`)) = 0");
			if (!empty($suspicious_rows)) {
				error_log("MDS Config::verify_and_repair_config_table - Found " . count($suspicious_rows) . " suspicious rows with potentially invisible characters");
				
				// Try to delete them
				foreach ($suspicious_rows as $row) {
					$id = $row->config_key;
					$wpdb->query($wpdb->prepare("DELETE FROM `{$table_name}` WHERE `config_key` = %s", $id));
					error_log("MDS Config::verify_and_repair_config_table - Deleted suspicious config_key: '" . bin2hex($id) . "'");
				}
			}
			
			return true;
		} catch (\Exception $e) {
			error_log("MDS Config::verify_and_repair_config_table - Error cleaning problematic rows: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Load the config from the database.
	 *
	 * @return array
	 */
	public static function load(): array {
		global $wpdb;
		$table_name = self::$table_name;

		// If self::$config exists and is not empty, return it
		if ( isset( self::$config ) && is_array( self::$config ) && ! empty( self::$config ) ) {
			return self::$config;
		}

		$config = [];

		// Load defaults
		$defaults = self::defaults();
		
		// Verify and repair the config table if needed
		$table_verified = self::verify_and_repair_config_table();
		error_log("MDS Config::load - Config table verification: " . ($table_verified ? 'successful' : 'failed'));
		
		// Check if config table exists after verification/repair
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
		
		if ($table_exists) {
			// Fetch current config from database 
			$config = self::get_results_assoc();
			
			// Handle the case where there might still be an empty key causing problems
			$has_duplicate_entry_issue = false;
			
			// Update database with any new defaults
			foreach ( $defaults as $key => $default ) {
				// Skip if key is empty or only whitespace
				if (empty($key) || trim($key) === '') {
					error_log("MDS Config::load - Skipping empty key in defaults");
					continue;
				}

				error_log("MDS Config::load - Checking default key: '{$key}'");

				// Check if this key already exists in the config
				if (!array_key_exists($key, $config)) {
					// If we already had an issue with duplicates, skip this insert
					if ($has_duplicate_entry_issue) {
						continue;
					}
					
					error_log("MDS Config::load - Inserting default key: '{$key}' with value: '" . $default['value'] . "'");
					
					try {
						$result = $wpdb->insert(
							$table_name,
							[
								'config_key' => $key,
								'val'        => $default['value'],
							],
							[
								'%s',
								'%' . $default['type'],
							]
						);

						if ($result === false) {
							$error = $wpdb->last_error;
							error_log("MDS Config::load - Failed to insert default key '{$key}': " . $error);
							
							// Check if this is a duplicate entry error for empty key
							if (strpos($error, "Duplicate entry ''" ) !== false) {
								$has_duplicate_entry_issue = true;
								error_log("MDS Config::load - Detected duplicate entry issue with empty key. Taking drastic measures...");
								
								// Emergency clean: back up valid data and recreate table
								$valid_data = [];
								try {
									// Get all non-empty keys
									$rows = $wpdb->get_results("SELECT * FROM {$table_name} WHERE LENGTH(config_key) > 0");
									
									foreach ($rows as $row) {
										$valid_data[$row->config_key] = $row->val;
									}
									
									error_log("MDS Config::load - Emergency: Backed up " . count($valid_data) . " valid config entries");
									
									// Drop and recreate config table
									$wpdb->query("DROP TABLE IF EXISTS {$table_name}");
									
									// Recreate table
									$charset_collate = $wpdb->get_charset_collate();
									$sql = "CREATE TABLE {$table_name} (
										config_key varchar(255) NOT NULL,
										val text NOT NULL,
										PRIMARY KEY  (config_key)
									) {$charset_collate};";
									
									require_once ABSPATH . 'wp-admin/includes/upgrade.php';
									dbDelta($sql);
									
									// Restore valid data
									foreach ($valid_data as $key => $value) {
										$wpdb->insert(
											$table_name,
											[
												'config_key' => $key,
												'val'        => $value,
											],
											['%s', '%s']
										);
									}
									error_log("MDS Config::load - Emergency: Restored " . count($valid_data) . " valid config entries");
									
									// Try inserting the current key again
									$result = $wpdb->insert(
										$table_name,
										[
											'config_key' => $key,
											'val'        => $default['value'],
										],
										[
											'%s',
											'%' . $default['type'],
										]
									);
									
									error_log("MDS Config::load - Emergency: Recreated table and restored data. Insert result: " . ($result !== false ? 'success' : 'failed: ' . $wpdb->last_error));
								} catch (\Exception $e) {
									error_log("MDS Config::load - Emergency repair failed: " . $e->getMessage());
								}
							}
						}
					} catch (\Exception $e) {
						error_log("MDS Config::load - Exception inserting default key '{$key}': " . $e->getMessage());
					}
				}
			}

			// Reload config from database to include new defaults
			$config = self::get_results_assoc();
		} else {
			// No config table yet so use defaults
			foreach ( $defaults as $key => $default ) {
				// Skip empty keys
				if (empty($key) || trim($key) === '') {
					continue;
				}
				
				$value = '';
				$type  = $default['type'];
				if ( $type === 'd' ) {
					$value = intval( $default['value'] );
				} elseif ( $type === 's' ) {
					$value = sanitize_text_field( $default['value'] );
				}
				$config[ $key ] = $value;
			}
		}

		// Save config to self::$config
		self::$config = $config;

		// Define config
		foreach ( $config as $key => $value ) {
			if ( ! defined( $key ) && isset( $defaults[ $key ] ) ) {
				$type = $defaults[ $key ]['type'];
				if ( $type === 'd' ) {
					$value = intval( $value );
				} elseif ( $type === 's' ) {
					$value = sanitize_text_field( $value );
				}
				define( $key, $value );
			}
		}

		// Return final config
		return self::$config;
	}

	/**
	 * Get the type of the config key.
	 *
	 * @param string $key The config key to get the type of.
	 *
	 * @return string
	 */
	public static function getType( string $key ): string {
		$defaults = self::defaults();

		return $defaults[ $key ]['type'];
	}

	/**
	 * Delete a record from the database table based on the provided key.
	 *
	 * @param string $key The key of the record to be deleted.
	 *
	 * @return void
	 * @throws void
	 */
	public static function delete( string $key ): void {
		global $wpdb;
		$wpdb->delete( self::$table_name, [ 'config_key' => $key ] );
	}
}