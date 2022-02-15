<?php

/**
 * Million Dollar Script Two
 *
 * @version 2.3.2
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

class Utility {

	/**
	 * Base path to plugin with trailing slash.
	 *
	 * @var string|null
	 */
	public static $base_path = null;

	/**
	 * Base URL to plugin with trailing slash.
	 *
	 * @var string|null
	 */
	public static $base_url = null;

	/**
	 * Attempt to stop PHP timeout
	 */
	public static function stop_timeout() {
		echo str_repeat( " ", 1024 );
		flush();
	}

	/**
	 * Get the base absolute path to the plugin with trailing slash.
	 *
	 * @return string
	 */
	public static function get_plugin_base_path(): string {
		if ( null == self::$base_path ) {
			self::$base_path = plugin_dir_path( MDS_BASE_FILE );
		}

		return self::$base_path;
	}

	/**
	 * Get the base absolute URL to the plugin with trailing slash.
	 *
	 * @return string
	 */
	public static function get_plugin_base_url(): string {
		if ( null == self::$base_url ) {
			self::$base_url = plugin_dir_url( MDS_BASE_FILE );
		}

		return self::$base_url;
	}

	/**
	 * Get MDS upload directory. Creates it if it doesn't exist already.
	 *
	 * @return string|null
	 */
	public static function get_upload_path(): ?string {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['basedir'] ) ) {

			// default to WP uploads folder/milliondollarscript
			$path = $upload_dir['basedir'] . '/milliondollarscript';

			// apply filter to allow modifying the upload path
			$path = apply_filters( 'mds_upload_path', $path );

			// normalize path
			$path = trailingslashit( wp_normalize_path( $path ) );

			// if path doesn't exist
			if ( ! file_exists( $path ) ) {
				// create the path
				wp_mkdir_p( $path );

				// create subfolders
				wp_mkdir_p( $path . 'grids' );
				wp_mkdir_p( $path . 'images' );
				wp_mkdir_p( $path . 'languages' );

				// copy default no-image file
				$src  = MDS_BASE_PATH . 'src/Core/upload_files/images/no-image.gif';
				$dest = $path . 'images/no-image.gif';
				if ( ! file_exists( $dest ) ) {
					$url        = wp_nonce_url( 'wp-admin/plugins.php', 'mds_filesystem_nonce' );
					$filesystem = new Filesystem( $url );
					if ( ! $filesystem->copy( $src, $dest ) ) {
						error_log( wp_sprintf(
							__( 'Error copying file. From: %s To: %s', 'milliondollarscript' ),
							$src,
							$dest
						) );
					}
				}

				// copy english_default.php
				$src  = MDS_BASE_PATH . 'src/Core/lang/english_default.php';
				$dest = $path . 'languages/english_default.php';
				if ( ! file_exists( $dest ) ) {
					$url        = wp_nonce_url( 'wp-admin/plugins.php', 'mds_filesystem_nonce' );
					$filesystem = new Filesystem( $url );
					if ( ! $filesystem->copy( $src, $dest ) ) {
						error_log( wp_sprintf(
							__( 'Error copying file. From: %s To: %s', 'milliondollarscript' ),
							$src,
							$dest
						) );
					}
				}

				// copy english.php
				$src  = MDS_BASE_PATH . 'src/Core/lang/english_default.php';
				$dest = $path . 'languages/english.php';
				if ( ! file_exists( $dest ) ) {
					$url        = wp_nonce_url( 'wp-admin/plugins.php', 'mds_filesystem_nonce' );
					$filesystem = new Filesystem( $url );
					if ( ! $filesystem->copy( $src, $dest ) ) {
						error_log( wp_sprintf(
							__( 'Error copying file. From: %s To: %s', 'milliondollarscript' ),
							$src,
							$dest
						) );
					}
				}
			}

			return trailingslashit( realpath( $path ) );
		} else {
			return null;
		}
	}

	/**
	 * Get MDS upload URL.
	 *
	 * @return string|null
	 */
	public static function get_upload_url(): ?string {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['baseurl'] ) ) {

			// default to WP uploads folder/milliondollarscript
			$url = $upload_dir['baseurl'] . '/milliondollarscript';

			// apply filter to allow modifying the upload url
			$url = apply_filters( 'mds_upload_url', $url );

			// validate URL
			$url = wp_http_validate_url( $url );

			return trailingslashit( $url );
		} else {
			return null;
		}
	}

	/**
	 * Get the time a file was last modified.
	 *
	 * @param string $filename
	 *
	 * @return integer
	 */
	public static function modified( $filename ) {
		if ( file_exists( $filename ) ) {
			return intval( filemtime( $filename ) );
		} else {
			return 0;
		}
	}

	/**
	 * Get an attachment ID given a URL.
	 * @link https://wpscholar.com/blog/get-attachment-id-from-wp-image-url/
	 *
	 * @param string $url
	 *
	 * @return int Attachment ID on success, 0 on failure
	 */
	public static function get_attachment_id( $url ) {
		$attachment_id = 0;
		$dir           = wp_upload_dir();
		// Is URL in uploads directory?
		if ( false !== strpos( $url, $dir['baseurl'] . '/' ) ) {
			$file       = basename( $url );
			$query_args = array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'fields'      => 'ids',
				'meta_query'  => array(
					array(
						'value'   => $file,
						'compare' => 'LIKE',
						'key'     => '_wp_attachment_metadata',
					),
				)
			);
			$query      = new \WP_Query( $query_args );
			if ( $query->have_posts() ) {
				foreach ( $query->posts as $post_id ) {
					$meta                = wp_get_attachment_metadata( $post_id );
					$original_file       = basename( $meta['file'] );
					$cropped_image_files = wp_list_pluck( $meta['sizes'], 'file' );
					if ( $original_file === $file || in_array( $file, $cropped_image_files ) ) {
						$attachment_id = $post_id;
						break;
					}
				}
			}
		}

		return $attachment_id;
	}

	public static function delete_uploads_folder() {
		$path = self::get_upload_path();
		if ( is_dir( $path ) ) {
			$url        = wp_nonce_url( 'wp-admin/plugins.php', 'mds_filesystem_nonce' );
			$filesystem = new Filesystem( $url );
			if ( ! $filesystem->delete_folder( $path ) ) {
				error_log( wp_sprintf(
					__( 'Error deleting folder: %s', 'milliondollarscript' ),
					$path
				) );
			}
		}
	}

	// TODO: fix images on non-fresh install. Options in database and image files have to be removed on deactivation or searched and connected.
//	public static function upload_default_images() {
//		$upload_dir = trailingslashit( Utility::get_upload_path() );
//
//		require_once ABSPATH . 'wp-admin/includes/image.php';
//
//		$image_files = [
//			'grid_background_tile'    => 'background.gif',
//			'grid_block_public'       => 'grid-block.png',
//			'grid_nfs_block_public'   => 'not-for-sale-block.png',
//			'grid_block_ordering'     => 'ordering-grid-block.png',
//			'grid_nfs_block_ordering' => 'ordering-not-for-sale-block.png',
//			'grid_ordered_block'      => 'ordered-block.png',
//			'grid_reserved_block'     => 'reserved-block.png',
//			'grid_selected_block'     => 'selected-block.png',
//			'grid_sold_block'         => 'sold-block.png'
//		];
//
//		foreach ( $image_files as $key => $filename ) {
//
//			$image_url  = \MillionDollarScript::core()->assets()->getAssetUrl( 'images/' . $filename );
//			$image_path = str_replace( self::get_plugin_base_url(), self::get_plugin_base_path(), $image_url );
//
//			$path_filename = $upload_dir . $filename;
//
//			$filetype = wp_check_filetype( $filename );
//
//			// Don't add again if the file exists already.
//			if ( ! file_exists( $path_filename ) ) {
//
//				$url        = wp_nonce_url( 'wp-admin/plugins.php', 'mds_filesystem_nonce' );
//				$filesystem = new Filesystem( $url );
//				if ( ! $filesystem->copy( $image_path, $path_filename ) ) {
//					// File didn't copy properly.
//					error_log( wp_sprintf(
//						__( 'Error copying file. From: %s To: %s', 'million_dollar_script' ),
//						$image_path,
//						$path_filename
//					) );
//
//					continue;
//				}
//			}
//
//			$attachment    = [
//				'guid'           => $image_url,
//				'post_mime_type' => $filetype['type'],
//				'post_title'     => $filename,
//				'post_content'   => '',
//				'post_status'    => 'inherit',
//			];
//			$attachment_id = wp_insert_attachment( $attachment, $path_filename );
//
//			if ( ! is_wp_error( $attachment_id ) ) {
//				$get_size = getimagesize( $image_path );
//
//				$data = [
//					'ID'     => wp_generate_attachment_metadata( $attachment_id, $path_filename ),
//					'width'  => $get_size[0],
//					'height' => $get_size[1]
//				];
//
//				wp_update_attachment_metadata( $attachment_id, $data );
//
//				// update default options
//				add_option( '_' . Options::prefix . $key, $attachment_id, null, 'no' );
//			}
//		}
//	}
}
