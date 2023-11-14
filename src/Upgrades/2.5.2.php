<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.5
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

use MillionDollarScript\Classes\DatabaseStatic;
use MillionDollarScript\Classes\Utility;

defined( 'ABSPATH' ) or exit;

/** @noinspection PhpUnused */

class _2_5_2 {

	public function upgrade( $version ): void {
		global $wpdb;

		if ( version_compare( $version, '2.5.2', '<' ) ) {

			// modify blocks.view_count column to have a default value
			$sql    = "SHOW COLUMNS FROM `" . MDS_DB_PREFIX . "blocks` LIKE 'view_count';";
			$result = $wpdb->get_results( $sql );
			if ( empty( $result ) || $result[0]->Type !== 'int(11)' || $result[0]->Null !== 'NO' || $result[0]->Default !== '0' ) {
				$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "blocks` MODIFY COLUMN `view_count` INT NOT NULL default '0';";
				$wpdb->query( $sql );
			}

			// modify blocks.click_count column to have a default value
			$sql    = "SHOW COLUMNS FROM `" . MDS_DB_PREFIX . "blocks` LIKE 'click_count';";
			$result = $wpdb->get_results( $sql );
			if ( empty( $result ) || $result[0]->Type !== 'int(11)' || $result[0]->Null !== 'NO' || $result[0]->Default !== '0' ) {
				$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "blocks` MODIFY COLUMN `click_count` INT NOT NULL default '0';";
				$wpdb->query( $sql );
			}

			// Add cancelled status for blocks
			$sql    = "SHOW COLUMNS FROM `" . MDS_DB_PREFIX . "blocks` LIKE 'status';";
			$result = $wpdb->get_results( $sql );
			if ( empty( $result ) || $result[0]->Type !== "enum('cancelled','reserved','sold','free','ordered','nfs')" ) {
				$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "blocks` CHANGE `status` `status` SET('cancelled','reserved','sold','free','ordered','nfs');";
				$wpdb->query( $sql );
			}

			// Add nfs_covered column to banners table
			$sql    = "SHOW COLUMNS FROM `" . MDS_DB_PREFIX . "banners` LIKE 'nfs_covered';";
			$result = $wpdb->get_results( $sql );
			if ( ! $result ) {
				$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "banners` ADD COLUMN `nfs_covered` CHAR(1) NOT NULL default 'N' AFTER `auto_approve`;";
				$wpdb->query( $sql );
			}

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
			$sql               = "SHOW COLUMNS FROM `" . MDS_DB_PREFIX . "banners` LIKE 'nfs_block';";
			$resultNfsBlock    = $wpdb->get_results( $sql );
			$sql               = "SHOW COLUMNS FROM `" . MDS_DB_PREFIX . "banners` LIKE 'usr_nfs_block';";
			$resultUsrNfsBlock = $wpdb->get_results( $sql );
			if ( empty( $resultNfsBlock ) || empty( $resultUsrNfsBlock ) || $resultNfsBlock[0]->Type !== 'longblob' || $resultUsrNfsBlock[0]->Type !== 'longblob' || $resultNfsBlock[0]->Null !== 'NO' || $resultUsrNfsBlock[0]->Null !== 'NO' ) {
				$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "banners` CHANGE `nfs_block` `nfs_block` LONGBLOB NOT NULL, CHANGE `usr_nfs_block` `usr_nfs_block` LONGBLOB NOT NULL;";
				$wpdb->query( $sql );
			}

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

			// Add nfs_covered column to banners table
			$sql    = "SHOW COLUMNS FROM `" . MDS_DB_PREFIX . "banners` LIKE 'enabled';";
			$result = $wpdb->get_results( $sql );
			if ( empty( $result ) ) {
				$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "banners` ADD COLUMN `enabled` CHAR(1) NOT NULL default 'Y' AFTER `nfs_covered`;";
				$wpdb->query( $sql );
			}

			// Add nfs_covered column to banners table
			$sql           = "SELECT COUNT(*) FROM `" . MDS_DB_PREFIX . "config` WHERE `config_key` = 'TOOLTIP_TRIGGER';";
			$existingCount = $wpdb->get_var( $sql );
			if ( $existingCount == 0 ) {
				$sql = "INSERT INTO `" . MDS_DB_PREFIX . "config` (
					`config_key`,
					`val`
			)
	        VALUES(
					'TOOLTIP_TRIGGER',
					'click'
			)";
				$wpdb->query( $sql );
			}

			// Convert Rank to Privileged Users
			$users_map  = [];
			$table_name = MDS_DB_PREFIX . "users";
			if ( DatabaseStatic::table_exists( $table_name ) ) {
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

							$meta_key = '_' . MDS_PREFIX . 'privileged';

							// Set privileged users.
							if ( $row['Rank'] == '2' && ! metadata_exists( 'user', $user_id, $meta_key ) ) {
								update_user_meta( $user_id, $meta_key, '1' );
							}
						}
					}
				}
			}

			// TODO: Add a button/option to delete old unused tables in the admin.
			// TODO: Add a way to migrate massive amounts of records which could be useful for other things.

			// Convert ads table to mds-pixel post type
			$table_name = MDS_DB_PREFIX . "ads";
			if ( DatabaseStatic::table_exists( $table_name ) ) {
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
	}
}
