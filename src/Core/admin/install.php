<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.9
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

defined( 'ABSPATH' ) or exit;

function install_db(): void {

	// https://codex.wordpress.org/Creating_Tables_with_Plugins

	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	$tables = [
		'banners'      => MDS_DB_PREFIX . 'banners',
		'blocks'       => MDS_DB_PREFIX . 'blocks',
		'clicks'       => MDS_DB_PREFIX . 'clicks',
		'config'       => MDS_DB_PREFIX . 'config',
		'mail_queue'   => MDS_DB_PREFIX . 'mail_queue',
		'orders'       => MDS_DB_PREFIX . 'orders',
		'packages'     => MDS_DB_PREFIX . 'packages',
		'prices'       => MDS_DB_PREFIX . 'prices',
		'transactions' => MDS_DB_PREFIX . 'transactions',
		'views'        => MDS_DB_PREFIX . 'views',
	];

	$insert_banners = false;

	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tables['banners']}'" ) != $tables['banners'] ) {
		$insert_banners = true;
	}

	// Banners table
	dbDelta( "CREATE TABLE `{$tables['banners']}` (
    `banner_id` INT NOT NULL AUTO_INCREMENT,
    `grid_width` INT NOT NULL DEFAULT '0',
    `grid_height` INT NOT NULL DEFAULT '0',
    `days_expire` MEDIUMINT(9) DEFAULT '0',
    `price_per_block` FLOAT NOT NULL DEFAULT '0',
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `publish_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `max_orders` INT NOT NULL DEFAULT '0',
    `block_width` INT NOT NULL DEFAULT '10',
    `block_height` INT NOT NULL DEFAULT '10',
    `grid_block` TEXT NOT NULL,
    `nfs_block` LONGBLOB NOT NULL,
    `tile` TEXT NOT NULL,
    `usr_grid_block` TEXT NOT NULL,
    `usr_nfs_block` LONGBLOB NOT NULL,
    `usr_ord_block` TEXT NOT NULL,
    `usr_res_block` TEXT NOT NULL,
    `usr_sel_block` TEXT NOT NULL,
    `usr_sol_block` TEXT NOT NULL,
    `max_blocks` INT NOT NULL DEFAULT '0',
    `min_blocks` INT NOT NULL DEFAULT '0',
    `date_updated` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `bgcolor` VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
    `auto_publish` CHAR(1) NOT NULL DEFAULT 'N',
    `auto_approve` CHAR(1) NOT NULL DEFAULT 'N',
    `nfs_covered` CHAR(1) NOT NULL DEFAULT 'N',
    `enabled` CHAR(1) NOT NULL DEFAULT 'Y',
    `time_stamp` INT DEFAULT NULL,
    PRIMARY KEY  (`banner_id`)
) $charset_collate;" );

	// Blocks table
	dbDelta( "CREATE TABLE `{$tables['blocks']}` (
    `block_id` INT NOT NULL DEFAULT '0',
    `user_id` INT DEFAULT NULL,
    `status` SET('cancelled','reserved','sold','free','ordered','nfs') NOT NULL DEFAULT '',
    `x` INT NOT NULL DEFAULT '0',
    `y` INT NOT NULL DEFAULT '0',
    `image_data` TEXT NOT NULL,
    `url` VARCHAR(255) NOT NULL DEFAULT '',
    `alt_text` TEXT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL DEFAULT '',
    `mime_type` VARCHAR(100) NOT NULL DEFAULT '',
    `approved` SET('Y','N') NOT NULL DEFAULT '',
    `published` SET('Y','N') NOT NULL DEFAULT '',
    `currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `order_id` INT NOT NULL DEFAULT '0',
    `price` FLOAT DEFAULT NULL,
    `banner_id` INT NOT NULL DEFAULT '1',
    `ad_id` INT NOT NULL DEFAULT '0',
    `click_count` INT NOT NULL DEFAULT '0',
    `view_count` INT NOT NULL DEFAULT '0',
    PRIMARY KEY  (`block_id`,`banner_id`)
) $charset_collate;" );

	// Clicks table
	dbDelta( "CREATE TABLE `{$tables['clicks']}` (
    `banner_id` INT NOT NULL,
    `block_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `date` DATE DEFAULT '1970-01-01',
    `clicks` INT NOT NULL,
    PRIMARY KEY  (`banner_id`,`block_id`,`date`)
) $charset_collate;" );

	// Config table
	dbDelta( "CREATE TABLE `{$tables['config']}` (
    `config_key` VARCHAR(100) NOT NULL DEFAULT '',
    `val` VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY  (`config_key`)
) $charset_collate;" );

	// Mail queue table
	dbDelta( "CREATE TABLE `{$tables['mail_queue']}` (
    `mail_id` INT NOT NULL AUTO_INCREMENT,
    `mail_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `to_address` VARCHAR(128) NOT NULL DEFAULT '',
    `to_name` VARCHAR(128) NOT NULL DEFAULT '',
    `from_address` VARCHAR(128) NOT NULL DEFAULT '',
    `from_name` VARCHAR(128) NOT NULL DEFAULT '',
    `subject` VARCHAR(255) NOT NULL DEFAULT '',
    `message` TEXT NOT NULL,
    `html_message` TEXT NOT NULL,
    `attachments` SET('Y','N') NOT NULL DEFAULT '',
    `status` SET('queued','sent','error') NOT NULL DEFAULT '',
    `error_msg` VARCHAR(255) NOT NULL DEFAULT '',
    `retry_count` SMALLINT(6) NOT NULL DEFAULT '0',
    `template_id` INT NOT NULL DEFAULT '0',
    `att1_name` VARCHAR(128) NOT NULL DEFAULT '',
    `att2_name` VARCHAR(128) NOT NULL DEFAULT '',
    `att3_name` VARCHAR(128) NOT NULL DEFAULT '',
    `date_stamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (`mail_id`)
) $charset_collate;" );

	// Orders table
	// Note: For more info on statuses see \MillionDollarScript\Classes\FormFields::get_statuses
	dbDelta( "CREATE TABLE `{$tables['orders']}` (
    `user_id` INT NOT NULL DEFAULT '0',
    `order_id` INT NOT NULL AUTO_INCREMENT,
    `blocks` TEXT NOT NULL,
    `status` SET('pending','completed','cancelled','confirmed','new','expired','deleted','renew_wait','renew_paid') NOT NULL DEFAULT '',
    `order_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `price` FLOAT NOT NULL DEFAULT '0',
    `quantity` INT NOT NULL DEFAULT '0',
    `banner_id` INT NOT NULL DEFAULT '1',
    `currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `days_expire` INT NOT NULL DEFAULT '0',
    `date_published` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `date_stamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expiry_notice_sent` SET('Y','N') NOT NULL DEFAULT '',
    `package_id` INT NOT NULL DEFAULT '0',
    `ad_id` INT DEFAULT NULL,
    `approved` SET('Y','N') NOT NULL DEFAULT 'N',
    `published` SET('Y','N') NOT NULL DEFAULT '',
    `subscr_status` VARCHAR(32) NOT NULL DEFAULT '',
    `original_order_id` INT DEFAULT NULL,
    `previous_order_id` INT NOT NULL DEFAULT '0',
    `block_info` LONGTEXT NOT NULL,
    `order_in_progress` SET('Y', 'N') NOT NULL DEFAULT 'N',
    `current_step` INT NOT NULL DEFAULT '0',
    PRIMARY KEY  (`order_id`)
) $charset_collate;" );

	// Packages table
	dbDelta( "CREATE TABLE `{$tables['packages']}` (
    `banner_id` INT NOT NULL DEFAULT '0',
    `days_expire` INT NOT NULL DEFAULT '0',
    `price` FLOAT NOT NULL DEFAULT '0',
    `currency` CHAR(3) NOT NULL DEFAULT '',
    `package_id` INT NOT NULL AUTO_INCREMENT,
    `is_default` SET('Y','N') DEFAULT NULL,
    `max_orders` MEDIUMINT(9) NOT NULL DEFAULT '0',
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY  (`package_id`)
) $charset_collate;" );

	// Prices table
	dbDelta( "CREATE TABLE `{$tables['prices']}` (
    `price_id` INT NOT NULL AUTO_INCREMENT,
    `banner_id` INT NOT NULL DEFAULT '0',
    `row_from` INT NOT NULL DEFAULT '0',
    `row_to` INT NOT NULL DEFAULT '0',
    `block_id_from` INT NOT NULL DEFAULT '0',
    `block_id_to` INT NOT NULL DEFAULT '0',
    `price` FLOAT NOT NULL DEFAULT '0',
    `currency` CHAR(3) NOT NULL DEFAULT '',
    `color` VARCHAR(50) NOT NULL DEFAULT '',
    `col_from` INT DEFAULT NULL,
    `col_to` INT DEFAULT NULL,
    PRIMARY KEY  (`price_id`)
) $charset_collate;" );

	// Transactions table
	dbDelta( "CREATE TABLE `{$tables['transactions']}` (
    `transaction_id` INT NOT NULL AUTO_INCREMENT,
    `date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `order_id` INT NOT NULL DEFAULT '0',
    `type` VARCHAR(32) NOT NULL DEFAULT '',
    `amount` FLOAT NOT NULL DEFAULT '0',
    `currency` CHAR(3) NOT NULL DEFAULT '',
    `txn_id` VARCHAR(128) NOT NULL DEFAULT '',
    `reason` VARCHAR(255) NOT NULL DEFAULT '',
    `origin` VARCHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY  (`transaction_id`)
) $charset_collate;" );

	// Views table
	dbDelta( "CREATE TABLE `{$tables['views']}` (
    `banner_id` INT NOT NULL,
    `block_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `date` DATE DEFAULT '1970-01-01',
    `views` INT NOT NULL,
    PRIMARY KEY  (`banner_id`,`block_id`,`date`) 
) $charset_collate;" );

	// Insert default grid
	if ( $insert_banners ) {
		$wpdb->insert(
			$tables['banners'],
			[
				'banner_id'       => 1,
				'grid_width'      => 100,
				'grid_height'     => 100,
				'days_expire'     => 0,
				'price_per_block' => 100,
				'name'            => 'Million Pixels (1000x1000)',
				'currency'        => 'USD',
				'publish_date'    => null,
				'max_orders'      => 1,
				'block_width'     => 10,
				'block_height'    => 10,
				'grid_block'      => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAHklEQVR4nGO8cuUKA27AwsDAoK2tjUuaCY/W4SwNAJbvAxP1WmxKAAAAAElFTkSuQmCC',
				'nfs_block'       => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAFUlEQVR4nGP8//8/A27AhEduBEsDAKXjAxF9kqZqAAAAAElFTkSuQmCC',
				'tile'            => 'iVBORw0KGgoAAAANSUhEUgAAAHgAAAB4AQMAAAADqqSRAAAABlBMVEXW19b///9ZVCXjAAAAJklEQVR4nGNgQAP197///Y8gBpw/6r5R9426b9R9o+4bdd8wdB8AiRh20BqKw9IAAAAASUVORK5CYII=',
				'usr_grid_block'  => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAHklEQVR4nGO8cuUKA27AwsDAoK2tjUuaCY/W4SwNAJbvAxP1WmxKAAAAAElFTkSuQmCC',
				'usr_nfs_block'   => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAFUlEQVR4nGP8//8/A27AhEduBEsDAKXjAxF9kqZqAAAAAElFTkSuQmCC',
				'usr_ord_block'   => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAFElEQVR4nGP83+DAgBsw4ZEbwdIAJ/sB02xWjpQAAAAASUVORK5CYII=',
				'usr_res_block'   => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAE0lEQVR4nGP8/58BD2DCJzlypQF0BwISHGyJPgAAAABJRU5ErkJggg==',
				'usr_sel_block'   => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAE0lEQVR4nGNk+M+ABzDhkxy50gBALQETmXEDiQAAAABJRU5ErkJggg==',
				'usr_sol_block'   => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAEklEQVR4nGP8z4APMOGVHbHSAEEsAROxCnMTAAAAAElFTkSuQmCC',
				'max_blocks'      => 0,
				'min_blocks'      => 1,
				'date_updated'    => date( 'Y-m-d H:i:s' ),
				'bgcolor'         => '#FFFFFF',
				'auto_publish'    => 'Y',
				'auto_approve'    => 'Y',
				'nfs_covered'     => 'N',
				'time_stamp'      => 1171775611
			],
			[
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				null,
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d'

			]
		);
	}

	// Insert default config

	// Load defaults
	$defaults = \MillionDollarScript\Classes\Config::defaults();

	// Fetch current config from database
	$config = \MillionDollarScript\Classes\Config::get_results_assoc();

	// Update database with new defaults
	foreach ( $defaults as $key => $default ) {
		if ( ! array_key_exists( $key, $config ) ) {
			$wpdb->insert(
				$tables['config'],
				array(
					'config_key' => $key,
					'val'        => $default['value'],
				),
				array(
					'%s',
					'%' . $default['type'],
				)
			);
		}
	}
}
