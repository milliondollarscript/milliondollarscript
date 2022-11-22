<?php
/*
 * @package       mds
 * @copyright     (C) Copyright 2022 Ryan Rhode, All rights reserved.
 * @author        Ryan Rhode, ryan@milliondollarscript.com
 * @version       2022-01-30 17:07:25 EST
 * @license       This program is free software; you can redistribute it and/or modify
 *        it under the terms of the GNU General Public License as published by
 *        the Free Software Foundation; either version 3 of the License, or
 *        (at your option) any later version.
 *
 *        This program is distributed in the hope that it will be useful,
 *        but WITHOUT ANY WARRANTY; without even the implied warranty of
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *        GNU General Public License for more details.
 *
 *        You should have received a copy of the GNU General Public License along
 *        with this program;  If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 *
 *  * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *        Million Dollar Script
 *        A pixel script for selling pixels on your website.
 *
 *        For instructions see README.txt
 *
 *        Visit our website for FAQs, documentation, a list team members,
 *        to post any bugs or feature requests, and a community forum:
 *        https://milliondollarscript.com/
 *
 */

defined( 'ABSPATH' ) or exit;

function install_db() {
	require_once __DIR__ . '/../include/version.php';

	$baseurl = plugins_url( '', realpath( __DIR__ . '/../index.php' ) );

	// https://codex.wordpress.org/Creating_Tables_with_Plugins

	global $wpdb;

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	$charset_collate = $wpdb->get_charset_collate();

	$table_name = MDS_DB_PREFIX . 'ads';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE `$table_name` (
			`ad_id` int NOT NULL auto_increment,
			`user_id` varchar(255) NOT NULL default '0',
			`ad_date` datetime default CURRENT_TIMESTAMP,
			`order_id` int default '0',
			`banner_id` int NOT NULL default '0',
			`1` varchar(255) NOT NULL default '',
			`2` varchar(255) NOT NULL default '',
			`3` varchar(255) NOT NULL default '',
			PRIMARY KEY  (`ad_id`)
		) $charset_collate;";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'banners';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = [];

		$sql[] = "CREATE TABLE `$table_name` (
			`banner_id` int NOT NULL auto_increment,
			`grid_width` int NOT NULL default '0',
			`grid_height` int NOT NULL default '0',
			`days_expire` mediumint(9) default '0',
			`price_per_block` float NOT NULL default '0',
			`name` varchar(255) NOT NULL default '',
			`currency` char(3) NOT NULL default 'USD',
			`publish_date` datetime default CURRENT_TIMESTAMP,
			`max_orders` int NOT NULL default '0',
			`block_width` int NOT NULL default '10',
			`block_height` int NOT NULL default '10',
			`grid_block` text NOT NULL,
			`nfs_block` text NOT NULL,
			`tile` text NOT NULL,
			`usr_grid_block` text NOT NULL,
			`usr_nfs_block` text NOT NULL,
			`usr_ord_block` text NOT NULL,
			`usr_res_block` text NOT NULL,
			`usr_sel_block` text NOT NULL,
			`usr_sol_block` text NOT NULL,
			`max_blocks` int NOT NULL default '0',
			`min_blocks` int NOT NULL default '0',
			`date_updated` datetime default CURRENT_TIMESTAMP,
			`bgcolor` varchar(7) NOT NULL default '#FFFFFF',
			`auto_publish` char(1) NOT NULL default 'N',
			`auto_approve` char(1) NOT NULL default 'N',
			`time_stamp` int default NULL,
			PRIMARY KEY  (`banner_id`)
		) $charset_collate";

		$sql[] = "INSERT INTO `$table_name` (
			`banner_id`,
			`grid_width`,
			`grid_height`,
			`days_expire`,
			`price_per_block`,
			`name`,
			`currency`,
			`publish_date`,
			`max_orders`,
			`block_width`,
			`block_height`,
			`grid_block`,
			`nfs_block`,
			`tile`,
			`usr_grid_block`,
			`usr_nfs_block`,
			`usr_ord_block`,
			`usr_res_block`,
			`usr_sel_block`,
			`usr_sol_block`,
			`max_blocks`,
			`min_blocks`,
			`date_updated`,
			`bgcolor`,
			`auto_publish`,
			`auto_approve`,
			`time_stamp`
		)
		VALUES(
			1,
			100,
			100,
			0,
			100,
			'Million Pixels. (1000x1000)',
			'USD',
			NULL,
			1,
			10,
			10,
			'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAHklEQVR4nGO8cuUKA27AwsDAoK2tjUuaCY/W4SwNAJbvAxP1WmxKAAAAAElFTkSuQmCC',
			'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAFUlEQVR4nGP8//8/A27AhEduBEsDAKXjAxF9kqZqAAAAAElFTkSuQmCC',
			'iVBORw0KGgoAAAANSUhEUgAAAHgAAAB4AQMAAAADqqSRAAAABlBMVEXW19b///9ZVCXjAAAAJklEQVR4nGNgQAP197///Y8gBpw/6r5R9426b9R9o+4bdd8wdB8AiRh20BqKw9IAAAAASUVORK5CYII=',
			'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAHklEQVR4nGO8cuUKA27AwsDAoK2tjUuaCY/W4SwNAJbvAxP1WmxKAAAAAElFTkSuQmCC',
			'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAFUlEQVR4nGP8//8/A27AhEduBEsDAKXjAxF9kqZqAAAAAElFTkSuQmCC',
			'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAFElEQVR4nGP83+DAgBsw4ZEbwdIAJ/sB02xWjpQAAAAASUVORK5CYII=',
			'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAE0lEQVR4nGP8/58BD2DCJzlypQF0BwISHGyJPgAAAABJRU5ErkJggg==',
			'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAE0lEQVR4nGNk+M+ABzDhkxy50gBALQETmXEDiQAAAABJRU5ErkJggg==',
			'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAEklEQVR4nGP8z4APMOGVHbHSAEEsAROxCnMTAAAAAElFTkSuQmCC',
			1,
			1,
			'2007-02-17 10:48:32',
			'#FFFFFF',
			'Y',
			'Y',
			1171775611
		)";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'categories';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE `$table_name` (
			`category_id` int NOT NULL default '0',
			`category_name` varchar(255) NOT NULL default '',
			`parent_category_id` int NOT NULL default '0',
			`obj_count` int NOT NULL default '0',
			`form_id` int NOT NULL default '0',
			`allow_records` set('Y','N') NOT NULL default 'Y',
			`list_order` smallint(6) NOT NULL default '1',
			`search_set` text NOT NULL,
			`seo_fname` varchar(100) default NULL,
			`seo_title` varchar(255) default NULL,
			`seo_desc` varchar(255) default NULL,
			`seo_keys` varchar(255) default NULL,
			PRIMARY KEY  (`category_id`),
			KEY `composite_index` (`parent_category_id`,`category_id`)
		) $charset_collate;";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'form_fields';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql[] = "CREATE TABLE `$table_name` (
			`form_id` int NOT NULL default '0',
			`field_id` int NOT NULL auto_increment,
			`section` tinyint(4) NOT NULL default '1',
			`reg_expr` varchar(255) NOT NULL default '',
			`field_label` varchar(255) NOT NULL default '-noname-',
			`field_type` varchar(255) NOT NULL default 'TEXT',
			`field_sort` tinyint(4) NOT NULL default '0',
			`is_required` set('Y','N') NOT NULL default 'N',
			`display_in_list` set('Y','N') NOT NULL default 'N',
			`is_in_search` set('Y','N') NOT NULL default 'N',
			`error_message` varchar(255) NOT NULL default '',
			`field_init` varchar(255) NOT NULL default '',
			`field_width` tinyint(4) NOT NULL default '20',
			`field_height` tinyint(4) NOT NULL default '0',
			`list_sort_order` tinyint(4) NOT NULL default '0',
			`search_sort_order` tinyint(4) NOT NULL default '0',
			`template_tag` varchar(255) NOT NULL default '',
			`is_hidden` char(1) NOT NULL default '',
			`is_anon` char(1) NOT NULL default '',
			`field_comment` text NOT NULL,
			`category_init_id` int NOT NULL default '0',
			`is_cat_multiple` set('Y','N') NOT NULL default 'N',
			`cat_multiple_rows` tinyint(4) NOT NULL default '1',
			`is_blocked` char(1) NOT NULL default 'N',
			`multiple_sel_all` char(1) NOT NULL default 'N',
			`is_prefill` char(1) NOT NULL default 'N',
			PRIMARY KEY  (`field_id`)
		) $charset_collate;";

		$sql[] = "INSERT INTO `$table_name` (
				`form_id`,
				`field_id`,
				`section`,
				`reg_expr`,
				`field_label`,
				`field_type`,
				`field_sort`,
				`is_required`,
				`display_in_list`,
				`is_in_search`,
				`error_message`,
				`field_init`,
				`field_width`,
				`field_height`,
				`list_sort_order`,
				`search_sort_order`,
				`template_tag`,
				`is_hidden`,
				`is_anon`,
				`field_comment`,
				`category_init_id`,
				`is_cat_multiple`,
				`cat_multiple_rows`,
				`is_blocked`,
				`multiple_sel_all`,
				`is_prefill`
		)
		VALUES(
				1,
				1,
				1,
				'not_empty',
				'Ad Text',
				'TEXT',
				1,
				'Y',
				'',
				'',
				'was not filled in',
				'',
				80,
				0,
				0,
				0,
				'ALT_TEXT',
				'',
				'',
				'',
				0,
				'',
				0,
				'',
				'',
				''
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`form_id`,
				`field_id`,
				`section`,
				`reg_expr`,
				`field_label`,
				`field_type`,
				`field_sort`,
				`is_required`,
				`display_in_list`,
				`is_in_search`,
				`error_message`,
				`field_init`,
				`field_width`,
				`field_height`,
				`list_sort_order`,
				`search_sort_order`,
				`template_tag`,
				`is_hidden`,
				`is_anon`,
				`field_comment`,
				`category_init_id`,
				`is_cat_multiple`,
				`cat_multiple_rows`,
				`is_blocked`,
				`multiple_sel_all`,
				`is_prefill`
		)
        VALUES(
				1,
				2,
				1,
				'url',
				'URL',
				'TEXT',
				2,
				'Y',
				'',
				'',
				'is not valid',
				'',
				80,
				0,
				0,
				0,
				'URL',
				'',
				'',
				'',
				0,
				'',
				0,
				'',
				'',
				''
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`form_id`,
				`field_id`,
				`section`,
				`reg_expr`,
				`field_label`,
				`field_type`,
				`field_sort`,
				`is_required`,
				`display_in_list`,
				`is_in_search`,
				`error_message`,
				`field_init`,
				`field_width`,
				`field_height`,
				`list_sort_order`,
				`search_sort_order`,
				`template_tag`,
				`is_hidden`,
				`is_anon`,
				`field_comment`,
				`category_init_id`,
				`is_cat_multiple`,
				`cat_multiple_rows`,
				`is_blocked`,
				`multiple_sel_all`,
				`is_prefill`
		)
    	VALUES(
				1,
				3,
				1,
				'',
				'Additional Image',
				'IMAGE',
				3,
				'',
				'',
				'',
				'',
				'',
				0,
				0,
				0,
				0,
				'IMAGE',
				'',
				'',
				'(This image will be displayed in a tooltip popup when your blocks are clicked)',
				0,
				'',
				0,
				'',
				'',
				''
		)";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'form_field_translations';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = [];

		$sql[] = "CREATE TABLE `$table_name` (
			`field_id` int NOT NULL default '0',
			`lang` char(2) NOT NULL default '',
			`field_label` text NOT NULL,
			`error_message` varchar(255) NOT NULL default '',
			`field_comment` text NOT NULL,
			PRIMARY KEY  (`field_id`,`lang`),
			KEY `field_id` (`field_id`)
		) $charset_collate;";

		$sql[] = "INSERT INTO `$table_name` (
				`field_id`,
				`lang`,
				`field_label`,
				`error_message`,
				`field_comment`
		)
    	VALUES(
				1,
				'EN',
				'Ad Text',
				'was not filled in',
				''
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`field_id`,
				`lang`,
				`field_label`,
				`error_message`,
				`field_comment`
		)
    	VALUES(
				2,
				'EN',
				'URL',
				'is not valid.',
				''
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`field_id`,
				`lang`,
				`field_label`,
				`error_message`,
				`field_comment`
		)
    	VALUES(
				3,
				'EN',
				'Additional Image',
				'',
				'(This image will be displayed in a tooltip popup when your blocks are clicked)'
		)";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'form_lists';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = [];

		$sql[] = "CREATE TABLE `$table_name` (
			`form_id` int NOT NULL default '0',
			`field_type` varchar(255) NOT NULL default '',
			`sort_order` int NOT NULL default '0',
			`field_id` varchar(255) NOT NULL default '0',
			`template_tag` varchar(255) NOT NULL default '',
			`column_id` int NOT NULL auto_increment,
			`admin` set('Y','N') NOT NULL default '',
			`truncate_length` smallint(4) NOT NULL default '0',
			`linked` set('Y','N') NOT NULL default 'N',
			`clean_format` set('Y','N') NOT NULL default '',
			`is_bold` set('Y','N') NOT NULL default '',
			`is_sortable` set('Y','N') NOT NULL default 'N',
			`no_wrap` set('Y','N') NOT NULL default '',
			PRIMARY KEY  (`column_id`)
		) $charset_collate;";

		$sql[] = "INSERT INTO `$table_name` (
				`form_id`,
				`field_type`,
				`sort_order`,
				`field_id`,
				`template_tag`,
				`column_id`,
				`admin`,
				`truncate_length`,
				`linked`,
				`clean_format`,
				`is_bold`,
				`is_sortable`,
				`no_wrap`
		)
    	VALUES(
				1,
				'TIME',
				1,
				'ad_date',
				'DATE',
				1,
				'N',
				0,
				'N',
				'N',
				'N',
				'Y',
				'N'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`form_id`,
				`field_type`,
				`sort_order`,
				`field_id`,
				`template_tag`,
				`column_id`,
				`admin`,
				`truncate_length`,
				`linked`,
				`clean_format`,
				`is_bold`,
				`is_sortable`,
				`no_wrap`
		)
    	VALUES(
				1,
				'EDITOR',
				2,
				'1',
				'ALT_TEXT',
				2,
				'N',
				0,
				'Y',
				'N',
				'N',
				'Y',
				'N'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`form_id`,
				`field_type`,
				`sort_order`,
				`field_id`,
				`template_tag`,
				`column_id`,
				`admin`,
				`truncate_length`,
				`linked`,
				`clean_format`,
				`is_bold`,
				`is_sortable`,
				`no_wrap`
		)
    	VALUES(
				1,
				'TEXT',
				3,
				'2',
				'URL',
				3,
				'N',
				0,
				'N',
				'N',
				'N',
				'N',
				'N'
		)";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'temp_orders';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE `$table_name` (
			`session_id` varchar(32) NOT NULL default '',
			`blocks` text NOT NULL,
			`order_date` datetime default CURRENT_TIMESTAMP,
			`price` float NOT NULL default '0',
			`quantity` int NOT NULL default '0',
			`banner_id` int NOT NULL default '1',
			`currency` char(3) NOT NULL default 'USD',
			`days_expire` int NOT NULL default '0',
			`date_stamp` datetime default CURRENT_TIMESTAMP,
			`package_id` int NOT NULL default '0',
			`ad_id` int default '0',
			`block_info` LONGTEXT NOT NULL,
			PRIMARY KEY  (`session_id`)
		) $charset_collate;";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'blocks';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE `$table_name` (
			`block_id` int NOT NULL default '0',
			`user_id` int default NULL,
			`status` set('reserved','sold','free','ordered','nfs') NOT NULL default '',
			`x` int NOT NULL default '0',
			`y` int NOT NULL default '0',
			`image_data` text NOT NULL,
			`url` varchar(255) NOT NULL default '',
			`alt_text` text NOT NULL,
			`file_name` varchar(255) NOT NULL default '',
			`mime_type` varchar(100) NOT NULL default '',
			`approved` set('Y','N') NOT NULL default '',
			`published` set('Y','N') NOT NULL default '',
			`currency` char(3) NOT NULL default 'USD',
			`order_id` int NOT NULL default '0',
			`price` float default NULL,
			`banner_id` int NOT NULL default '1',
			`ad_id` int  NOT NULL default '0',
			`click_count` INT NOT NULL,
			`view_count` INT NOT NULL,
			PRIMARY KEY  (`block_id`,`banner_id`)
		) $charset_collate;";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'clicks';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE `$table_name` (
			`banner_id` INT NOT NULL ,
			`block_id` INT NOT NULL ,
			`user_id` INT NOT NULL ,
			`date` date default '1970-01-01',
			`clicks` INT NOT NULL ,
			PRIMARY KEY ( `banner_id` , `block_id` ,  `date` ) 
		) $charset_collate;";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'views';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE `$table_name` (
			`banner_id` INT NOT NULL ,
			`block_id` INT NOT NULL ,
			`user_id` INT NOT NULL ,
			`date` date default '1970-01-01',
			`views` INT NOT NULL ,
			PRIMARY KEY ( `banner_id` , `block_id` ,  `date` ) 
		) $charset_collate;";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'config';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = [];

		$sql[] = "CREATE TABLE `$table_name` (
			`key` varchar(100) NOT NULL default '',
			`val` varchar(255) NOT NULL default '',
			PRIMARY KEY  (`key`)
		) $charset_collate;";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EXPIRE_RUNNING',
				'NO'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'LAST_EXPIRE_RUN',
				'1138243912'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'SELECT_RUNNING',
				'NO'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'dbver',
				" . MDS_DB_VERSION . "
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'MDS_LOG',
				false
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'MDS_LOG_FILE',
				'" . esc_sql( wp_normalize_path( realpath( __DIR__ . '/../' ) ) . '.mds.log' ) . "'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'BUILD_DATE',
				'" . get_mds_build_date() . "'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'VERSION_INFO',
				'" . get_mds_version() . "'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'BASE_HTTP_PATH',
				'" . esc_sql( $baseurl ) . "/'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'BASE_PATH',
				'" . esc_sql( wp_normalize_path( untrailingslashit( realpath( __DIR__ . '/..' ) ) ) ) . "'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'SERVER_PATH_TO_ADMIN',
				'" . esc_sql( wp_normalize_path( trailingslashit( realpath( __DIR__ ) ) ) ) . "'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'UPLOAD_PATH',
		    '" . esc_sql( \MillionDollarScript\Classes\Utility::get_upload_path() ) . "'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'UPLOAD_HTTP_PATH',
				'" . esc_sql( \MillionDollarScript\Classes\Utility::get_upload_url() ) . "'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'SITE_CONTACT_EMAIL',
				'" . wp_get_current_user()->user_email . "'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'SITE_LOGO_URL',
				'" . esc_sql( $baseurl ) . "/images/logo.gif'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'SITE_NAME',
				'Million Dollar Script'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'SITE_SLOGAN',
				'This is the Million Dollar Script Example. 1 pixel = 1 cent'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'MDS_RESIZE',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'ADMIN_PASSWORD',
		    '" . esc_sql( bin2hex( openssl_random_pseudo_bytes( 12 ) ) ) . "'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'DATE_FORMAT',
				'Y-M-d'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'TIME_FORMAT',
				'h:i:s A'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'GMT_DIF',
				'" . esc_sql( date_default_timezone_get() ) . "'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'DATE_INPUT_SEQ',
				'YMD'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'OUTPUT_JPEG',
				'N'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'JPEG_QUALITY',
				'75'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'INTERLACE_SWITCH',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'BANNER_DIR',
				'pixels/'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'DISPLAY_PIXEL_BACKGROUND',
				'NO'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_USER_ORDER_CONFIRMED',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_ADMIN_ORDER_CONFIRMED',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_USER_ORDER_COMPLETED',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_ADMIN_ORDER_COMPLETED',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_USER_ORDER_PENDED',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_ADMIN_ORDER_PENDED',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_USER_ORDER_EXPIRED',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_ADMIN_ORDER_EXPIRED',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EM_NEEDS_ACTIVATION',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_ADMIN_ACTIVATION',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_ADMIN_PUBLISH_NOTIFY',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_USER_EXPIRE_WARNING',
				''
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAILS_DAYS_KEEP',
				'30'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'MINUTES_RENEW',
				'10080'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'MINUTES_CONFIRMED',
				'10080'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'MINUTES_UNCONFIRMED',
				'60'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'MINUTES_CANCEL',
				'4320'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'ENABLE_MOUSEOVER',
				'POPUP'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'ENABLE_CLOAKING',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'VALIDATE_LINK',
				'NO'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'ADVANCED_CLICK_COUNT',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'ADVANCED_VIEW_COUNT',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'USE_SMTP',
				''
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_SMTP_SERVER',
				''
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_SMTP_USER',
				''
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_SMTP_PASS',
				''
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_SMTP_AUTH_HOST',
				''
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'SMTP_PORT',
				'465'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'POP3_PORT',
				'995'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_TLS',
				'1'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_POP_SERVER',
				''
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_POP_BEFORE_SMTP',
				'NO'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAIL_DEBUG',
				'NO'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAILS_PER_BATCH',
				'12'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAILS_MAX_RETRY',
				'15'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'EMAILS_ERROR_WAIT',
				'20'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'USE_AJAX',
				'SIMPLE'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'MEMORY_LIMIT',
				'128M'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'REDIRECT_SWITCH',
				'NO'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'REDIRECT_URL',
				'https://www.example.com'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'MDS_AGRESSIVE_CACHE',
				'NO'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'BLOCK_SELECTION_MODE',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'STATS_DISPLAY_MODE',
				'PIXELS'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'DISPLAY_ORDER_HISTORY',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'INVERT_PIXELS',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'ERROR_REPORTING',
				'" . esc_sql( ( E_ALL & ~E_NOTICE ) ) . "'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'WP_ENABLED',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'WP_URL',
				'" . esc_sql( untrailingslashit( get_site_url() ) ) . "'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'WP_PATH',
		    '" . esc_sql( wp_normalize_path( untrailingslashit( ABSPATH ) ) ) . "'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'WP_USERS_ENABLED',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'WP_ADMIN_ENABLED',
				'YES'
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`key`,
				`val`
		)
    	VALUES(
				'WP_USE_MAIL',
				'YES'
		)";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'currencies';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = [];

		$sql[] = "CREATE TABLE `$table_name` (
			`code` char(3) NOT NULL default '',
			`name` varchar(50) NOT NULL default '',
			`rate` decimal(20,10) NOT NULL default '1.0000000000',
			`is_default` set('Y','N') NOT NULL default 'N',
			`sign` varchar(8) NOT NULL default '',
			`decimal_places` smallint(6) NOT NULL default '0',
			`decimal_point` char(3) NOT NULL default '',
			`thousands_sep` char(3) NOT NULL default '',
			PRIMARY KEY  (`code`)
		) $charset_collate;";

		$sql[] = "INSERT INTO `$table_name` (
				`code`,
				`name`,
				`rate`,
				`is_default`,
				`sign`,
				`decimal_places`,
				`decimal_point`,
				`thousands_sep`
		)
    	VALUES(
				'AUD',
				'Australian Dollar',
				1.5193,
				'N',
				'$',
				2,
				'.',
				','
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`code`,
				`name`,
				`rate`,
				`is_default`,
				`sign`,
				`decimal_places`,
				`decimal_point`,
				`thousands_sep`
		)
    	VALUES(
				'CAD',
				'Canadian Dollar',
				1.3378,
				'N',
				'$',
				2,
				'.',
				','
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`code`,
				`name`,
				`rate`,
				`is_default`,
				`sign`,
				`decimal_places`,
				`decimal_point`,
				`thousands_sep`
		)
    	VALUES(
				'EUR',
				'Euro',
				0.9095,
				'N',
				'€',
				2,
				'.',
				','
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`code`,
				`name`,
				`rate`,
				`is_default`,
				`sign`,
				`decimal_places`,
				`decimal_point`,
				`thousands_sep`
		)
    	VALUES(
				'GBP',
				'British Pound',
				0.7756,
				'N',
				'£',
				2,
				'.',
				','
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`code`,
				`name`,
				`rate`,
				`is_default`,
				`sign`,
				`decimal_places`,
				`decimal_point`,
				`thousands_sep`
		)
    	VALUES(
				'JPY',
				'Japanese Yen',
				109.6790,
				'N',
				'¥',
				0,
				'.',
				','
		)";

		$sql[] = "INSERT INTO `$table_name` (
				`code`,
				`name`,
				`rate`,
				`is_default`,
				`sign`,
				`decimal_places`,
				`decimal_point`,
				`thousands_sep`
		)
    	VALUES(
				'USD',
				'U.S. Dollar',
				1.0000,
				'Y',
				'$',
				2,
				'.',
				','
		)";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'lang';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = [];

		$sql[] = "CREATE TABLE `$table_name` (
			`lang_code` char(2) NOT NULL default '',
			`lang_dir` char(3) NOT NULL default '',
			`lang_filename` varchar(32) NOT NULL default '',
			`lang_image` varchar(32) NOT NULL default '',
			`is_active` set('Y','N') NOT NULL default '',
			`name` varchar(32) NOT NULL default '',
			`charset` varchar(32) NOT NULL default '',
			`image_data` text NOT NULL,
			`mime_type` varchar(255) NOT NULL default '',
			`is_default` char(1) NOT NULL default 'N',
			PRIMARY KEY  (`lang_code`)
		) $charset_collate;";

		$sql[] = "INSERT INTO `$table_name` (
				`lang_code`,
				`lang_dir`,
				`lang_filename`,
				`lang_image`,
				`is_active`,
				`name`,
				`charset`,
				`image_data`,
				`mime_type`,
				`is_default`
		)
    	VALUES(
				'EN',
				'ltr',
				'english.php',
				'english.gif',
				'Y',
				'English',
				'en_US.utf8',
				'R0lGODlhGQARAMQAAAURdBYscgNNfrUOEMkMBdAqE9UTMtItONNUO9w4SdxmaNuObhYuh0Y5lCxVlFJcpqN2ouhfjLCrrOeRmeHKr/Wy3Lje4dPW3PDTz9/q0vXm1ffP7MLt5/f0+AAAAAAAACwAAAAAGQARAAAF02AAMIDDkOgwEF3gukCZIICI1jhFDRmOS4dF50aMVSqEjehFIWQ2kJLUMRoxCCsNzDFBZDCuh1RMpQY6HZYIiOlIYqKy9JZIqHeZTqMWnvoZCgosCkIXDoeIAGJkfmgEB3UHkgp1dYuKVWJXWCsEnp4qAwUcpBwWphapFhoanJ+vKxOysxMRgbcDHRlfeboZF2mvwp+5Eh07YC9naMzNzLmKuggTDy8G19jZ2NAiFB0LBxYuC+TlC7Syai8QGU0TAs7xaNxLDLoDdsPDuS98ABXfQgAAOw==',
				'image/gif',
				'Y'
		)";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'orders';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE `$table_name` (
          `user_id` int NOT NULL default '0',
          `order_id` int NOT NULL auto_increment,
          `blocks` text NOT NULL,
          `status` set('pending','completed','cancelled','confirmed','new','expired','deleted','renew_wait','renew_paid') NOT NULL default '',
          `order_date` datetime default CURRENT_TIMESTAMP,
          `price` float NOT NULL default '0',
          `quantity` int NOT NULL default '0',
          `banner_id` int NOT NULL default '1',
          `currency` char(3) NOT NULL default 'USD',
          `days_expire` int NOT NULL default '0',
          `date_published` datetime default CURRENT_TIMESTAMP,
          `date_stamp` datetime default CURRENT_TIMESTAMP,
          `expiry_notice_sent` set('Y','N') NOT NULL default '',
          `package_id` int NOT NULL default '0',
          `ad_id` int default NULL,
          `approved` set('Y','N') NOT NULL default 'N',
          `published` set('Y','N') NOT NULL default '',
          `subscr_status` varchar(32) NOT NULL default '',
          `original_order_id` int default NULL,
          `previous_order_id` int NOT NULL default '0',
          PRIMARY KEY  (`order_id`)
	) $charset_collate;";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'packages';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE `$table_name` (
          `banner_id` int NOT NULL default '0',
          `days_expire` int NOT NULL default '0',
          `price` float NOT NULL default '0',
          `currency` char(3) NOT NULL default '',
          `package_id` int NOT NULL auto_increment,
          `is_default` set('Y','N') default NULL,
          `max_orders` mediumint(9) NOT NULL default '0',
          `description` varchar(255) NOT NULL default '',
          PRIMARY KEY  (`package_id`)
	) $charset_collate;";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'prices';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE `$table_name` (
			`price_id` int NOT NULL auto_increment,
			`banner_id` int NOT NULL default '0',
			`row_from` int NOT NULL default '0',
			`row_to` int NOT NULL default '0',
			`block_id_from` int NOT NULL default '0',
			`block_id_to` int NOT NULL default '0',
			`price` float NOT NULL default '0',
			`currency` char(3) NOT NULL default '',
			`color` varchar(50) NOT NULL default '',
			col_from int default NULL,
			col_to int default NULL,
			PRIMARY KEY  (`price_id`)
		) $charset_collate;";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'transactions';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE `$table_name` (
			`transaction_id` int NOT NULL auto_increment,
			`date` datetime default CURRENT_TIMESTAMP,
			`order_id` int NOT NULL default '0',
			`type` varchar(32) NOT NULL default '',
			`amount` float NOT NULL default '0',
			`currency` char(3) NOT NULL default '',
			`txn_id` varchar(128) NOT NULL default '',
			`reason` varchar(255) NOT NULL default '',
			`origin` varchar(32) NOT NULL default '',
			PRIMARY KEY  (`transaction_id`)
		) $charset_collate;";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'users';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE `$table_name` (
			`ID` int NOT NULL auto_increment,
			`IP` varchar(50) NOT NULL default '',
			`SignupDate` datetime default CURRENT_TIMESTAMP,
			`FirstName` varchar(50) NOT NULL default '',
			`LastName` varchar(50) NOT NULL default '',
			`Rank` int NOT NULL default '1',
			`Username` varchar(50) NOT NULL default '',
			`Password` varchar(50) NOT NULL default '',
			`Email` varchar(255) NOT NULL default '',
			`Newsletter` int NOT NULL default '1',
			`Notification1` int NOT NULL default '0',
			`Notification2` int NOT NULL default '0',
			`Aboutme` longtext NOT NULL,
			`Validated` int NOT NULL default '0',
			`CompName` varchar(255) NOT NULL default '',
			`login_date` datetime default CURRENT_TIMESTAMP,
			`logout_date` datetime default '1000-01-01 00:00:00',
			`login_count` int NOT NULL default '0',
			`last_request_time` datetime default CURRENT_TIMESTAMP,
			`click_count` int NOT NULL default '0',
			`view_count` int NOT NULL default '0',
			PRIMARY KEY  (`ID`),
			UNIQUE KEY `Username` (`Username`)
		) $charset_collate;";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'mail_queue';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE `$table_name` (
	        `mail_id` int NOT NULL auto_increment,
	        `mail_date` datetime default CURRENT_TIMESTAMP,
	        `to_address` varchar(128) NOT NULL default '',
	        `to_name` varchar(128) NOT NULL default '',
	        `from_address` varchar(128) NOT NULL default '',
	        `from_name` varchar(128) NOT NULL default '',
	        `subject` varchar(255) NOT NULL default '',
	        `message` text NOT NULL,
	        `html_message` text NOT NULL,
	        `attachments` set('Y','N') NOT NULL default '',
	        `status` set('queued','sent','error') NOT NULL default '',
	        `error_msg` varchar(255) NOT NULL default '',
	        `retry_count` smallint(6) NOT NULL default '0',
	        `template_id` int NOT NULL default '0',
	        `att1_name` varchar(128) NOT NULL default '',
	        `att2_name` varchar(128) NOT NULL default '',
	        `att3_name` varchar(128) NOT NULL default '',
	        `date_stamp` datetime default CURRENT_TIMESTAMP,
	        PRIMARY KEY  (`mail_id`)
		) $charset_collate;";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'codes';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE `$table_name` (
			`field_id` varchar(30) NOT NULL default '',
			`code` varchar(5) NOT NULL default '',
			`description` varchar(30) NOT NULL default '',
			PRIMARY KEY  (`field_id`,`code`)
		) $charset_collate;";

		dbDelta( $sql );
		unset( $sql );
	}

	$table_name = MDS_DB_PREFIX . 'codes_translations';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE `$table_name` (
			`field_id` int NOT NULL default '0',
			`code` varchar(10) NOT NULL default '',
			`description` varchar(255) NOT NULL default '',
			`lang` char(2) NOT NULL default '',
			PRIMARY KEY  (`field_id`,`code`,`lang`)
		) $charset_collate;";

		dbDelta( $sql );
		unset( $sql );
	}
}
