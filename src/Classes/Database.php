<?php

/**
 * Million Dollar Script Two
 *
 * @version 2.3.6
 * @author Ryan Rhode
 * @copyright (C) 2022, Ryan Rhode
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
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
 */

namespace MillionDollarScript\Classes;

/**
 * Database Upgrades
 */
class Database {
	public function __construct() {
		$this->connect();
	}

	public function __wakeup() {
		$this->connect();
	}

	/**
	 * Returns all MDS database tables without prefixes.
	 *
	 * @return string[]
	 */
	public static function get_mds_tables() {
		return [
			'ads',
			'banners',
			'categories',
			'form_fields',
			'form_field_translations',
			'form_lists',
			'temp_orders',
			'blocks',
			'clicks',
			'views',
			'config',
			'currencies',
			'lang',
			'orders',
			'packages',
			'prices',
			'transactions',
			'users',
			'mail_queue',
			'codes',
			'codes_translations'
		];
	}

	/**
	 * Initialize the database connection and store in the global space.
	 *
	 * @return void
	 */
	public function connect() {
		if ( isset( $GLOBALS['connection'] ) ) {
			return;
		}

		require_once __DIR__ . '/Mdsdb.php';
		$conn                  = new Mdsdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$GLOBALS['connection'] = $conn->get_dbh();
	}

	/**
	 * Checks if a database table exists.
	 *
	 * @param $table_name
	 *
	 * @return false
	 */
	public function table_exists( $table_name ) {
		global $wpdb;

		$result = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
		$wpdb->flush();

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
	public function mds_sql_installed() {
		return $this->table_exists( MDS_DB_PREFIX . 'config' );
	}

	/**
	 * Checks if config table exists. In version 8 of the database the table prefixes were changed to wp_mds_
	 *
	 * @return bool
	 */
	public function mds_sql_pre8_installed() {
		return $this->table_exists( 'config' );
	}

	/**
	 * Get database version
	 *
	 * @return int
	 */
	public function get_dbver() {
		$pre8_installed = $this->mds_sql_pre8_installed();

		if ( ! $this->mds_sql_installed() && ! $pre8_installed ) {
			return 0;
		}

		global $wpdb;

		$prefix = ( $pre8_installed ) ? '' : MDS_DB_PREFIX;

		$sql    = "SELECT `val` FROM `" . $prefix . "config` WHERE `key`='dbver';";
		$result = $wpdb->get_var( $sql );
		if ( $wpdb->num_rows == 0 ) {
			$wpdb->flush();

			// add database version config value
			$sql = "INSERT INTO " . $prefix . "config(`key`, `val`) VALUES('dbver', 1);";
			$wpdb->query( $sql );
			$version = 1;
		} else {
			$version = intval( $result );
		}

		return $version;
	}

	/**
	 * Increment database version by 1 or set to given value.
	 *
	 * @param int $version
	 */
	public function up_dbver( int $version = 0 ) {
		global $wpdb;

		$prefix = ( $this->mds_sql_pre8_installed() ) ? '' : MDS_DB_PREFIX;

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
				'key' => 'dbver'
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

		$sql   = "SELECT `val` FROM `" . $prefix . "config` WHERE `key`='dbver';";
		$dbver = $wpdb->get_var( $sql );

		return intval( $dbver );
	}

	/**
	 * Checks if the database requires an upgrade.
	 *
	 * @return bool
	 */
	public function requires_upgrade( $version = - 1 ) {
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
	 * Performs any necessary upgrades on the database.
	 *
	 * @return bool
	 */
	public function upgrade() {
		global $wpdb;

		require_once MDS_CORE_PATH . 'include/version.php';

		$version = $this->get_dbver();

		if ( ! $this->requires_upgrade( $version ) ) {
			return false;
		}

		$charset_collate = $wpdb->get_charset_collate();

		if ( $version <= 1 ) {

			// add views table
			$sql = "CREATE TABLE IF NOT EXISTS `views` (
            `banner_id` INT NOT NULL ,
            `block_id` INT NOT NULL ,
            `user_id` INT NOT NULL ,
            `date` date default '1970-01-01',
            `views` INT NOT NULL ,
            PRIMARY KEY ( `banner_id` , `block_id` ,  `date` )
        ) $charset_collate;";
			$wpdb->query( $sql );

			// add view_count column to blocks table
			$sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = 'blocks' AND column_name = 'view_count'";
			if ( empty( $wpdb->get_results( $sql ) ) ) {
				$sql = "ALTER TABLE `blocks` ADD COLUMN `view_count` INT NOT NULL AFTER `click_count`;";
				$wpdb->query( $sql );
			}

			$version = $this->up_dbver( 2 );
		}

		if ( $version <= 2 ) {
			// Change block_info column to LONGTEXT
			$sql = "ALTER TABLE `temp_orders` MODIFY `block_info` LONGTEXT CHARACTER SET $wpdb->charset NOT NULL;";
			$wpdb->query( $sql );

			$version = $this->up_dbver( 3 );
		}

		if ( $version <= 3 ) {

			// Add config variables to config database table

			// Arrays of queries sorted by variable types for preparing for database.
			$queries = [

				'MDS_LOG_FILE'                => "INSERT INTO `config` VALUES ('MDS_LOG_FILE', %s);",
				'VERSION_INFO'                => "INSERT INTO `config` VALUES ('VERSION_INFO', %s);",
				'BASE_HTTP_PATH'              => "INSERT INTO `config` VALUES ('BASE_HTTP_PATH', %s);",
				'BASE_PATH'                   => "INSERT INTO `config` VALUES ('BASE_PATH', %s);",
				'SERVER_PATH_TO_ADMIN'        => "INSERT INTO `config` VALUES ('SERVER_PATH_TO_ADMIN', %s);",
				'UPLOAD_PATH'                 => "INSERT INTO `config` VALUES ('UPLOAD_PATH', %s);",
				'UPLOAD_HTTP_PATH'            => "INSERT INTO `config` VALUES ('UPLOAD_HTTP_PATH', %s);",
				'SITE_CONTACT_EMAIL'          => "INSERT INTO `config` VALUES ('SITE_CONTACT_EMAIL', %s);",
				'SITE_LOGO_URL'               => "INSERT INTO `config` VALUES ('SITE_LOGO_URL', %s);",
				'SITE_NAME'                   => "INSERT INTO `config` VALUES ('SITE_NAME', %s);",
				'SITE_SLOGAN'                 => "INSERT INTO `config` VALUES ('SITE_SLOGAN', %s);",
				'MDS_RESIZE'                  => "INSERT INTO `config` VALUES ('MDS_RESIZE', %s);",
				'ADMIN_PASSWORD'              => "INSERT INTO `config` VALUES ('ADMIN_PASSWORD', %s);",
				'DATE_FORMAT'                 => "INSERT INTO `config` VALUES ('DATE_FORMAT', %s);",
				'GMT_DIF'                     => "INSERT INTO `config` VALUES ('GMT_DIF', %s);",
				'DATE_INPUT_SEQ'              => "INSERT INTO `config` VALUES ('DATE_INPUT_SEQ', %s);",
				'OUTPUT_JPEG'                 => "INSERT INTO `config` VALUES ('OUTPUT_JPEG', %s);",
				'INTERLACE_SWITCH'            => "INSERT INTO `config` VALUES ('INTERLACE_SWITCH', %s);",
				'BANNER_DIR'                  => "INSERT INTO `config` VALUES ('BANNER_DIR', %s);",
				'DISPLAY_PIXEL_BACKGROUND'    => "INSERT INTO `config` VALUES ('DISPLAY_PIXEL_BACKGROUND', %s);",
				'EMAIL_USER_ORDER_CONFIRMED'  => "INSERT INTO `config` VALUES ('EMAIL_USER_ORDER_CONFIRMED', %s);",
				'EMAIL_ADMIN_ORDER_CONFIRMED' => "INSERT INTO `config` VALUES ('EMAIL_ADMIN_ORDER_CONFIRMED', %s);",
				'EMAIL_USER_ORDER_COMPLETED'  => "INSERT INTO `config` VALUES ('EMAIL_USER_ORDER_COMPLETED', %s);",
				'EMAIL_ADMIN_ORDER_COMPLETED' => "INSERT INTO `config` VALUES ('EMAIL_ADMIN_ORDER_COMPLETED', %s);",
				'EMAIL_USER_ORDER_PENDED'     => "INSERT INTO `config` VALUES ('EMAIL_USER_ORDER_PENDED', %s);",
				'EMAIL_ADMIN_ORDER_PENDED'    => "INSERT INTO `config` VALUES ('EMAIL_ADMIN_ORDER_PENDED', %s);",
				'EMAIL_USER_ORDER_EXPIRED'    => "INSERT INTO `config` VALUES ('EMAIL_USER_ORDER_EXPIRED', %s);",
				'EMAIL_ADMIN_ORDER_EXPIRED'   => "INSERT INTO `config` VALUES ('EMAIL_ADMIN_ORDER_EXPIRED', %s);",
				'EM_NEEDS_ACTIVATION'         => "INSERT INTO `config` VALUES ('EM_NEEDS_ACTIVATION', %s);",
				'EMAIL_ADMIN_ACTIVATION'      => "INSERT INTO `config` VALUES ('EMAIL_ADMIN_ACTIVATION', %s);",
				'EMAIL_ADMIN_PUBLISH_NOTIFY'  => "INSERT INTO `config` VALUES ('EMAIL_ADMIN_PUBLISH_NOTIFY', %s);",
				'EMAIL_USER_EXPIRE_WARNING'   => "INSERT INTO `config` VALUES ('EMAIL_USER_EXPIRE_WARNING', %s);",
				'ENABLE_MOUSEOVER'            => "INSERT INTO `config` VALUES ('ENABLE_MOUSEOVER', %s);",
				'ENABLE_CLOAKING'             => "INSERT INTO `config` VALUES ('ENABLE_CLOAKING', %s);",
				'VALIDATE_LINK'               => "INSERT INTO `config` VALUES ('VALIDATE_LINK', %s);",
				'ADVANCED_CLICK_COUNT'        => "INSERT INTO `config` VALUES ('ADVANCED_CLICK_COUNT', %s);",
				'ADVANCED_VIEW_COUNT'         => "INSERT INTO `config` VALUES ('ADVANCED_VIEW_COUNT', %s);",
				'USE_SMTP'                    => "INSERT INTO `config` VALUES ('USE_SMTP', %s);",
				'EMAIL_SMTP_SERVER'           => "INSERT INTO `config` VALUES ('EMAIL_SMTP_SERVER', %s);",
				'EMAIL_SMTP_USER'             => "INSERT INTO `config` VALUES ('EMAIL_SMTP_USER', %s);",
				'EMAIL_SMTP_PASS'             => "INSERT INTO `config` VALUES ('EMAIL_SMTP_PASS', %s);",
				'EMAIL_SMTP_AUTH_HOST'        => "INSERT INTO `config` VALUES ('EMAIL_SMTP_AUTH_HOST', %s);",
				'EMAIL_POP_SERVER'            => "INSERT INTO `config` VALUES ('EMAIL_POP_SERVER', %s);",
				'EMAIL_POP_BEFORE_SMTP'       => "INSERT INTO `config` VALUES ('EMAIL_POP_BEFORE_SMTP', %s);",
				'EMAIL_DEBUG'                 => "INSERT INTO `config` VALUES ('EMAIL_DEBUG', %s);",
				'USE_AJAX'                    => "INSERT INTO `config` VALUES ('USE_AJAX', %s);",
				'MEMORY_LIMIT'                => "INSERT INTO `config` VALUES ('MEMORY_LIMIT', %s);",
				'REDIRECT_SWITCH'             => "INSERT INTO `config` VALUES ('REDIRECT_SWITCH', %s);",
				'REDIRECT_URL'                => "INSERT INTO `config` VALUES ('REDIRECT_URL', %s);",
				'MDS_AGRESSIVE_CACHE'         => "INSERT INTO `config` VALUES ('MDS_AGRESSIVE_CACHE', %s);",
				'BLOCK_SELECTION_MODE'        => "INSERT INTO `config` VALUES ('BLOCK_SELECTION_MODE', %s);",
				'WP_ENABLED'                  => "INSERT INTO `config` VALUES ('WP_ENABLED', %s);",
				'WP_URL'                      => "INSERT INTO `config` VALUES ('WP_URL', %s);",
				'WP_PATH'                     => "INSERT INTO `config` VALUES ('WP_PATH', %s);",
				'WP_USERS_ENABLED'            => "INSERT INTO `config` VALUES ('WP_USERS_ENABLED', %s);",
				'WP_ADMIN_ENABLED'            => "INSERT INTO `config` VALUES ('WP_ADMIN_ENABLED', %s);",
				'WP_USE_MAIL'                 => "INSERT INTO `config` VALUES ('WP_USE_MAIL', %s);",

				'DEBUG'               => "INSERT INTO `config` VALUES ('DEBUG', %d);",
				'MDS_LOG'             => "INSERT INTO `config` VALUES ('MDS_LOG', %d);",
				'JPEG_QUALITY'        => "INSERT INTO `config` VALUES ('JPEG_QUALITY', %d);",
				'EMAILS_DAYS_KEEP'    => "INSERT INTO `config` VALUES ('EMAILS_DAYS_KEEP', %d);",
				'DAYS_RENEW'          => "INSERT INTO `config` VALUES ('DAYS_RENEW', %d);",
				'DAYS_CONFIRMED'      => "INSERT INTO `config` VALUES ('DAYS_CONFIRMED', %d);",
				'MINUTES_UNCONFIRMED' => "INSERT INTO `config` VALUES ('MINUTES_UNCONFIRMED', %d);",
				'DAYS_CANCEL'         => "INSERT INTO `config` VALUES ('DAYS_CANCEL', %d);",
				'SMTP_PORT'           => "INSERT INTO `config` VALUES ('SMTP_PORT', %d);",
				'POP3_PORT'           => "INSERT INTO `config` VALUES ('POP3_PORT', %d);",
				'EMAIL_TLS'           => "INSERT INTO `config` VALUES ('EMAIL_TLS', %d);",
				'EMAILS_PER_BATCH'    => "INSERT INTO `config` VALUES ('EMAILS_PER_BATCH', %d);",
				'EMAILS_MAX_RETRY'    => "INSERT INTO `config` VALUES ('EMAILS_MAX_RETRY', %d);",
				'EMAILS_ERROR_WAIT'   => "INSERT INTO `config` VALUES ('EMAILS_ERROR_WAIT', %d);",
				'ERROR_REPORTING'     => "INSERT INTO `config` VALUES ('ERROR_REPORTING', %d);"
			];

			// For compatibility with older versions
			if ( ! defined( 'VERSION_INFO' ) ) {
				// Check if path database option exists.
				$mds_path = \MillionDollarScript\Classes\Options::get_mds_path();
				if ( $mds_path != false ) {
					// Found an existing path option so try to load the config file from there.
					if ( file_exists( $mds_path . 'config.php' ) ) {
						require_once $mds_path . 'config.php';
					}
				}
			}

			// If no config values are loaded yet try some more places.
			if ( ! defined( 'VERSION_INFO' ) ) {
				// Look for config.php files
				if ( file_exists( MDS_BASE_PATH . 'config.php' ) ) {
					// Check plugin root path
					require_once MDS_BASE_PATH . 'config.php';
				} else if ( file_exists( MDS_CORE_PATH . 'config.php' ) ) {
					// Check MDS core path
					require_once MDS_CORE_PATH . 'config.php';
				}
			}

			foreach ( $queries as $key => $query ) {
				$wpdb->get_results( "SELECT `key` FROM `config` WHERE `key` = '" . $key . "'" );
				if ( $wpdb->num_rows > 0 ) {
					$wpdb->flush();
					continue;
				}
				$wpdb->flush();

				// Update certain variables from older versions
				if ( $key == 'BASE_HTTP_PATH' ) {
					$var = MDS_CORE_URL;
				} else if ( $key == 'BASE_PATH' ) {
					$var = untrailingslashit( realpath( MDS_CORE_PATH ) );
				} else if ( $key == 'SERVER_PATH_TO_ADMIN' ) {
					$var = trailingslashit( realpath( MDS_CORE_PATH . 'admin' ) );
				} else if ( $key == 'UPLOAD_PATH' ) {
					$var = \MillionDollarScript\Classes\Utility::get_upload_path();
				} else if ( $key == 'UPLOAD_HTTP_PATH' ) {
					$var = \MillionDollarScript\Classes\Utility::get_upload_url();
				} else if ( $key == 'WP_ENABLED' ) {
					$var = 'YES';
				} else if ( $key == 'WP_URL' ) {
					$var = esc_sql( untrailingslashit( get_site_url() ) );
				} else if ( $key == 'WP_PATH' ) {
					$var = esc_sql( wp_normalize_path( untrailingslashit( ABSPATH ) ) );
				} else if ( $key == 'WP_USERS_ENABLED' ) {
					$var = 'YES';
				} else if ( $key == 'WP_ADMIN_ENABLED' ) {
					$var = 'YES';
				} else if ( $key == 'WP_USE_MAIL' ) {
					$var = 'YES';
				} else if ( $key == 'MINUTES_UNCONFIRMED' ) {
					$var = '60';
				} else {
					if ( defined( $key ) ) {
						$var = constant( $key );
					} else {
						$var = null;
					}
				}

				if ( isset( $var ) ) {
					$wpdb->query(
						$wpdb->prepare( $query, [ $var ] )
					);
					$wpdb->flush();
				}
			}

			$version = $this->up_dbver( 4 );
		}

		if ( $version <= 4 ) {

			// add missing view_count column to users table
			$sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = 'users' AND column_name = 'view_count'";
			if ( empty( $wpdb->get_results( $sql ) ) ) {
				$sql = "ALTER TABLE `users` ADD COLUMN `view_count` INT NOT NULL default '0' AFTER `click_count`;";
				$wpdb->query( $sql );
			}

			$version = $this->up_dbver( 5 );
		}

		if ( $version <= 5 ) {

			// modify blocks.view_count column to have a default value
			$sql = "ALTER TABLE `blocks` MODIFY COLUMN `view_count` INT NOT NULL default '0';";
			$wpdb->query( $sql );

			// modify blocks.click_count column to have a default value
			$sql = "ALTER TABLE `blocks` MODIFY COLUMN `click_count` INT NOT NULL default '0';";
			$wpdb->query( $sql );

			$version = $this->up_dbver( 6 );
		}

		if ( $version <= 8 ) {

			// Plugin has been renamed so there will be new paths.

			//	Note: DO NOT update logo

			$val = esc_sql( MDS_CORE_URL );
			$sql = "UPDATE `config` SET `val` = '$val' WHERE `config`.`key` = 'BASE_HTTP_PATH'; ";
			$wpdb->query( $sql );

			$val = esc_sql( untrailingslashit( realpath( MDS_CORE_PATH ) ) );
			$sql = "UPDATE `config` SET `val` = '$val' WHERE `config`.`key` = 'BASE_PATH'; ";
			$wpdb->query( $sql );

			$val = esc_sql( trailingslashit( realpath( MDS_CORE_PATH . 'admin' ) ) );
			$sql = "UPDATE `config` SET `val` = '$val' WHERE `config`.`key` = 'SERVER_PATH_TO_ADMIN'; ";
			$wpdb->query( $sql );

			$val = esc_sql( \MillionDollarScript\Classes\Utility::get_upload_path() );
			$sql = "UPDATE `config` SET `val` = '$val' WHERE `config`.`key` = 'UPLOAD_PATH'; ";
			$wpdb->query( $sql );

			$val = esc_sql( \MillionDollarScript\Classes\Utility::get_upload_url() );
			$sql = "UPDATE `config` SET `val` = '$val' WHERE `config`.`key` = 'UPLOAD_HTTP_PATH'; ";
			$wpdb->query( $sql );

			$sql = "INSERT INTO `config` (
				`key`,
				`val`
			)
    	    VALUES(
				'TIME_FORMAT',
				'h:i:s A'
			)";
			$wpdb->query( $sql );

			update_option( '_' . \MillionDollarScript\Classes\Options::prefix . 'path', MDS_CORE_PATH );

			// Add prefixes to database tables
			$tables = self::get_mds_tables();

			foreach ( $tables as $table ) {
				$wpdb->query(
					"ALTER TABLE " . $table . " RENAME " . MDS_DB_PREFIX . $table . ";"
				);
			}

			$version = $this->up_dbver( 8 );
		}

		if ( $version <= 8 ) {
			// Adjust currency to 10 decimal places
			$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "currencies` MODIFY COLUMN `rate` DECIMAL(20,10) NOT NULL DEFAULT '1.0000000000';";
			$wpdb->query( $sql );

			$version = $this->up_dbver( 9 );
		}

		if ( $version <= 9 ) {
			// Update initial version info that shows before config is saved the first time
			$sql = "UPDATE `" . MDS_DB_PREFIX . "config` SET `val`='2.3.0' WHERE `key`='VERSION_INFO'";

			$wpdb->query( $sql );

			$version = $this->up_dbver( 10 );
		}

		if ( $version <= 10 ) {

			// Add BUILD_DATE to config
			$sql = "INSERT INTO `" . MDS_DB_PREFIX . "config` VALUES ('BUILD_DATE', '" . get_mds_build_date() . "');";
			$wpdb->query( $sql );

			// Add STATS_DISPLAY_MODE option
			$sql = "INSERT INTO `" . MDS_DB_PREFIX . "config` VALUES ('STATS_DISPLAY_MODE', 'PIXELS');";
			$wpdb->query( $sql );

			$version = $this->up_dbver( 11 );
		}

		if ( $version <= 11 ) {

			// Add DISPLAY_ORDER_HISTORY option
			$sql = "INSERT INTO `" . MDS_DB_PREFIX . "config` VALUES ('DISPLAY_ORDER_HISTORY', 'YES');";
			$wpdb->query( $sql );

			$version = $this->up_dbver( 12 );
		}

		if ( $version <= 12 ) {

			// Update config key DAYS_RENEW to MINUTES_RENEW
			$sql = "UPDATE `" . MDS_DB_PREFIX . "config` SET `key`='MINUTES_RENEW' WHERE `key`='DAYS_RENEW';";
			$wpdb->query( $sql );

			// Update config value for DAYS_RENEW to MINUTES_RENEW
			$sql = "UPDATE `" . MDS_DB_PREFIX . "config` c SET `val`=`val`*1440 WHERE `key`='MINUTES_RENEW';";
			$wpdb->query( $sql );

			// Update config key DAYS_CONFIRMED to MINUTES_CONFIRMED
			$sql = "UPDATE `" . MDS_DB_PREFIX . "config` SET `key`='MINUTES_CONFIRMED' WHERE `key`='DAYS_CONFIRMED';";
			$wpdb->query( $sql );

			// Update config value for DAYS_CONFIRMED to MINUTES_CONFIRMED
			$sql = "UPDATE `" . MDS_DB_PREFIX . "config` SET `val`=`val`*1440 WHERE `key`='MINUTES_CONFIRMED';";
			$wpdb->query( $sql );

			// Update config key DAYS_CANCEL to MINUTES_CANCEL
			$sql = "UPDATE `" . MDS_DB_PREFIX . "config` SET `key`='MINUTES_CANCEL' WHERE `key`='DAYS_CANCEL';";
			$wpdb->query( $sql );

			// Update config value for DAYS_CANCEL to MINUTES_CANCEL
			$sql = "UPDATE `" . MDS_DB_PREFIX . "config` SET `val`=`val`*1440 WHERE `key`='MINUTES_CANCEL';";
			$wpdb->query( $sql );

			// Add cancelled status for blocks
			$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "blocks` CHANGE `status` `status` SET('cancelled','reserved','sold','free','ordered','nfs');";
			$wpdb->query( $sql );

			// Add INVERT_PIXELS option
			$sql = "INSERT INTO `" . MDS_DB_PREFIX . "config` VALUES ('INVERT_PIXELS', 'YES');";
			$wpdb->query( $sql );

			$version = $this->up_dbver( 13 );
		}

		if ( $version <= 13 ) {

			// Update language table for language direction
			$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "lang` ADD COLUMN `lang_dir` CHAR(3) NOT NULL default 'ltr' AFTER `lang_code`;";
			$wpdb->query( $sql );

			$version = $this->up_dbver( 14 );
		}

		if ( $version <= 14 ) {
			add_action( 'wp_loaded', function () {
				if ( Functions::migrate_product() != null ) {
					$this->up_dbver( 15 );
				}
			} );
		}

		if ( $version <= 15 ) {

			// Add nfs_covered column to banners table
			$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "banners` ADD COLUMN `nfs_covered` CHAR(1) NOT NULL default 'N' AFTER `auto_approve`;";
			$wpdb->query( $sql );

			$version = $this->up_dbver( 16 );
		}

		if ( $version <= 16 ) {

			// Store banner data from database into an array for later use.
			// $sql     = "SELECT `banner_id`,`nfs_block`, `usr_nfs_block` FROM `" . MDS_DB_PREFIX . "banners`";
			// $results = $wpdb->get_results( $sql );
			// $banners = [];
			// foreach ( $results as $result ) {
			// 	// Decode block data while storing to the array
			// 	$banners[$result->banner_id] = [
			// 		'nfs_block'     => base64_decode( $result->nfs_block ),
			// 		'usr_nfs_block' => base64_decode( $result->usr_nfs_block ),
			// 	];
			// }

			// Change nfs_block and usr_nfs_block to LONGBLOB
			$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "banners` CHANGE `nfs_block` `nfs_block` LONGBLOB NOT NULL, CHANGE `usr_nfs_block` `usr_nfs_block` LONGBLOB NOT NULL;";
			$wpdb->query( $sql );

			// Update block data for each banner
			// foreach ( $banners as $banner_id => $data ) {
			// 	$wpdb->update(
			// 		MDS_DB_PREFIX . 'banners',
			// 		[
			// 			'nfs_block'     => $data['nfs_block'],
			// 			'usr_nfs_block' => $data['usr_nfs_block'],
			// 		],
			// 		[
			// 			'banner_id' => $banner_id
			// 		],
			// 		[
			// 			'%s',
			// 			'%s'
			// 		],
			// 		[
			// 			'%d'
			// 		]
			// 	);
			// }

			$version = $this->up_dbver( 17 );
		}

		if($version <= 17) {

			// Add nfs_covered column to banners table
			$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "banners` ADD COLUMN `enabled` CHAR(1) NOT NULL default 'Y' AFTER `nfs_covered`;";
			$wpdb->query( $sql );

			$version = $this->up_dbver( 18 );
		}

		// Update version info
		$wpdb->update(
			MDS_DB_PREFIX . 'config',
			[
				'val' => get_mds_version(),
			],
			[
				'key' => 'VERSION_INFO'
			],
			[
				'%s',
			],
			[
				'%s'
			]
		);

		// TODO: remember to update the DB version in /milliondollarscript-two.php

		return true;
	}
}