<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.3
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
 * Class MillionDollarScript\Classes\Config
 */
class Config {
	private static array $config;
	private static string $table_name = MDS_DB_PREFIX . 'config';

	/**
	 * MillionDollarScript\Classes\Config constructor.
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
			'DATE_FORMAT'                 => [ 'value' => 'Y-M-d', 'type' => 's' ],
			'TIME_FORMAT'                 => [ 'value' => 'h:i:s A', 'type' => 's' ],
			'GMT_DIF'                     => [ 'value' => date_default_timezone_get(), 'type' => 's' ],
			'DATE_INPUT_SEQ'              => [ 'value' => 'YMD', 'type' => 's' ],
			'OUTPUT_JPEG'                 => [ 'value' => 'N', 'type' => 's' ],
			'JPEG_QUALITY'                => [ 'value' => '75', 'type' => 's' ],
			'INTERLACE_SWITCH'            => [ 'value' => 'YES', 'type' => 's' ],
			'BANNER_DIR'                  => [ 'value' => 'pixels/', 'type' => 's' ],
			'DISPLAY_PIXEL_BACKGROUND'    => [ 'value' => 'NO', 'type' => 's' ],
			'EMAIL_USER_ORDER_CONFIRMED'  => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_ADMIN_ORDER_CONFIRMED' => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_USER_ORDER_COMPLETED'  => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_ADMIN_ORDER_COMPLETED' => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_USER_ORDER_PENDED'     => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_ADMIN_ORDER_PENDED'    => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_USER_ORDER_EXPIRED'    => [ 'value' => 'YES', 'type' => 's' ],
			'EMAIL_ADMIN_ORDER_EXPIRED'   => [ 'value' => 'YES', 'type' => 's' ],
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
			'DISPLAY_ORDER_HISTORY'       => [ 'value' => 'YES', 'type' => 's' ],
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
	 * Load the config from the database.
	 *
	 * @return array
	 */
	public static function load(): array {
		// If self::$config exists and is not empty, return it
		if ( isset( self::$config ) && is_array( self::$config ) && ! empty( self::$config ) ) {
			return self::$config;
		}

		global $wpdb;

		$config = [];

		$table_name = self::$table_name;

		// Load defaults
		$defaults = self::defaults();

		// Check if config table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name ) {
			// Config table must already be installed.

			// Fetch current config from database
			$config = self::get_results_assoc();

			// Update database with any new defaults
			foreach ( $defaults as $key => $default ) {
				if ( ! array_key_exists( $key, $config ) ) {
					$wpdb->insert(
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
				}
			}

			// Reload config from database to include new defaults
			$config = self::get_results_assoc();
		} else {
			// No config table yet so use defaults.
			foreach ( $defaults as $key => $default ) {
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
}