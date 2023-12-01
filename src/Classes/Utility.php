<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.6
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

class Utility {

	/**
	 * Base path to plugin with trailing slash.
	 *
	 * @var string|null
	 */
	public static ?string $base_path = null;

	/**
	 * Base URL to plugin with trailing slash.
	 *
	 * @var string|null
	 */
	public static ?string $base_url = null;

	/**
	 * Attempt to stop PHP timeout
	 */
	public static function stop_timeout(): void {
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
			$path = wp_normalize_path( trailingslashit( $path ) );

			// if path doesn't exist
			if ( ! file_exists( $path ) ) {
				// create the path
				wp_mkdir_p( $path );

				// create subfolders
				wp_mkdir_p( $path . 'grids' );
				wp_mkdir_p( $path . 'images' );

				// copy default no-image file
				$src  = MDS_BASE_PATH . 'src/Assets/images/no-image.gif';
				$dest = wp_normalize_path( $path . 'images/no-image.gif' );
				if ( ! file_exists( $dest ) ) {
					$url        = wp_nonce_url( 'wp-admin/plugins.php', 'mds_filesystem_nonce' );
					$filesystem = new Filesystem( $url );
					if ( ! $filesystem->copy( $src, $dest ) ) {
						error_log( wp_sprintf(
							Language::get( 'Error copying file. From: %s To: %s' ),
							$src,
							$dest
						) );
					}
				}
			}

			return wp_normalize_path( trailingslashit( realpath( $path ) ) );
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
	public static function modified( string $filename ): int {
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
	public static function get_attachment_id( string $url ): int {
		$attachment_id = 0;
		$dir           = wp_upload_dir();
		// Is URL in uploads directory?
		if ( str_contains( $url, $dir['baseurl'] . '/' ) ) {
			$file = basename( $url );
			wp_reset_postdata();
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

	public static function delete_uploads_folder(): void {
		$path = self::get_upload_path();
		if ( is_dir( $path ) ) {
			$url        = wp_nonce_url( 'wp-admin/plugins.php', 'mds_filesystem_nonce' );
			$filesystem = new Filesystem( $url );
			if ( ! $filesystem->delete_folder( $path ) ) {
				error_log( wp_sprintf(
					Language::get( 'Error deleting folder: %s' ),
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

	/**
	 * Get the URL to the grid image.
	 *
	 * @return string
	 */
	public static function get_grid_img(): string {
		global $f2;

		$BID = $f2->bid();

		$grid_img = Utility::get_upload_url() . 'grids/grid' . $BID;

		$grid_ext = Utility::get_file_extension();

		$grid_img .= $grid_ext;

		$grid_file = Utility::get_upload_path() . 'grids/grid' . $BID . '.' . $grid_ext;

		// Add modification time
		$grid_img .= "?v=" . filemtime( $grid_file );

		return $grid_img;
	}

	/**
	 * Deletes all blocks, orders, user meta data related to MDS orders, and mds-pixel posts and associated attachments.
	 *
	 * // TODO: delete matching WooCommerce orders?
	 *
	 * @return void
	 */
	public static function clear_orders(): void {
		global $wpdb;

		$table_name = MDS_DB_PREFIX . 'blocks';
		$exists     = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );

		if ( ! $exists ) {
			return;
		}

		// Delete all blocks and orders.
		$wpdb->query( "DELETE FROM " . MDS_DB_PREFIX . "blocks WHERE `status` != 'nfs'" );
		/** @noinspection SqlWithoutWhere */
		$wpdb->query( "DELETE FROM " . MDS_DB_PREFIX . "orders" );

		// Delete any orders in progress from user meta.
		$users = get_users();
		foreach ( $users as $user ) {
			delete_user_meta( $user->ID, MDS_PREFIX . 'current_order_id' );
			delete_user_meta( $user->ID, MDS_PREFIX . 'click_count' );
			delete_user_meta( $user->ID, MDS_PREFIX . 'view_count' );
			delete_user_meta( $user->ID, '_' . MDS_PREFIX . 'privileged' );
		}

		// Delete mds-pixel posts and attachments.
		$args = [
			'post_type'   => \MillionDollarScript\Classes\FormFields::$post_type,
			'post_status' => 'any',
			'numberposts' => - 1,
		];

		$custom_posts = get_posts( $args );

		foreach ( $custom_posts as $post ) {
			$attachments = get_attached_media( '', $post->ID );
			foreach ( $attachments as $attachment ) {
				$fields = \MillionDollarScript\Classes\FormFields::get_fields();
				foreach ( $fields as $field ) {
					if ( $field->get_type() === 'image' ) {
						$attachment_id = carbon_get_post_meta( $post->ID, $field->get_base_name() );
						if ( ! empty( $attachment_id ) ) {
							wp_delete_attachment( $attachment_id, true );
						}
					}
				}
			}

			wp_delete_post( $post->ID, true );
		}

		$wpdb->flush();
	}

	public static function get_file_extension(): string {
		$OUTPUT_JPEG = Config::get( 'OUTPUT_JPEG' );

		$ext = '';
		if ( $OUTPUT_JPEG == 'Y' ) {
			$ext = "jpg";
		} else if ( $OUTPUT_JPEG == 'N' ) {
			$ext = 'png';
		} else if ( $OUTPUT_JPEG == 'GIF' ) {
			$ext = 'gif';
		}

		return $ext;
	}

	/**
	 * Redirect to the previous page or fallback to home if no referrer.
	 * TODO: implement too many redirects protection
	 *
	 * @param string $dest
	 * @param array $args
	 *
	 * @return void
	 */
	public static function redirect( string $dest = '', array $args = [] ): void {
		if ( ! empty( $args ) ) {
			$dest .= ( ! str_contains( $dest, '?' ) ? '?' : '&' ) . http_build_query( $args );
		}

		if ( ! empty( $dest ) ) {
			wp_safe_redirect( $dest );
			exit;
		}

		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			wp_safe_redirect( $_SERVER['HTTP_REFERER'] );
			exit;
		}

		wp_safe_redirect( home_url() );
		exit;
	}

	/**
	 * Get an MDS page URL.
	 *
	 * @param $page_name
	 *
	 * @return string|null
	 */
	public static function get_page_url( $page_name ): ?string {
		$pages = self::get_pages();

		if ( ! isset( $pages[ $page_name ] ) ) {
			// Non-pages like AJAX or API requests, etc.
			$MDS_ENDPOINT = Options::get_option( 'endpoint', 'milliondollarscript' );

			return trailingslashit( home_url( "/" . $MDS_ENDPOINT . "/{$page_name}" ) );
		}

		// Actual pages
		$page_id = Options::get_option( $pages[ $page_name ]['option'] );

		return get_permalink( $page_id );
	}

	/**
	 * Get the header. Checks if theme is a block theme or standard theme.
	 *
	 * @return void
	 */
	public static function get_header(): void {
		// TODO: find some way to make FSE themes work with this
		get_header();
		if ( wp_is_block_theme() ) {
			block_header_area();
		}
	}

	/**
	 * Get the footer. Checks if theme is a block theme or standard theme.
	 *
	 * @return void
	 */
	public static function get_footer(): void {
		// TODO: find some way to make FSE themes work with this
		if ( wp_is_block_theme() ) {
			block_template_part( 'footer' );
		}
		get_footer();
	}

	public static function get_pages(): array {
		$pages = [
			'grid'          => [
				'option' => 'grid-page',
				'title'  => Language::get( 'Grid' ),
				'width'  => '1000px',
				'height' => '1000px',
			],
			'account'       => [
				'option' => 'users-home-page',
				'title'  => Language::get( 'My Account' )
			],
			'order'         => [
				'option' => 'users-order-page',
				'title'  => Language::get( 'Order Pixels' )
			],
			'write-ad'      => [
				'option' => 'users-write-ad-page',
				'title'  => Language::get( 'Write Your Ad' )
			],
			'confirm-order' => [
				'option' => 'users-confirm-order-page',
				'title'  => Language::get( 'Confirm Order' )
			],
			'checkout'      => [
				'option' => 'users-checkout-page',
				'title'  => Language::get( 'Checkout' )
			],
			'payment'       => [
				'option' => 'users-payment-page',
				'title'  => Language::get( 'Payment' )
			],
			'manage'        => [
				'option' => 'users-manage-page',
				'title'  => Language::get( 'Manage Pixels' )
			],
			'history'       => [
				'option' => 'users-history-page',
				'title'  => Language::get( 'Order History' )
			],
			'publish'       => [
				'option' => 'users-publish-page',
				'title'  => Language::get( 'Publish' )
			],
			'thank-you'     => [
				'option' => 'users-thank-you-page',
				'title'  => Language::get( 'Thank-You!' )
			],
			'list'          => [
				'option' => 'users-list-page',
				'title'  => Language::get( 'List' )
			],
			'upload'        => [
				'option' => 'users-upload-page',
				'title'  => Language::get( 'Upload' )
			],
			'no-orders'     => [
				'option' => 'users-no-orders-page',
				'title'  => Language::get( 'No Orders' )
			],
		];

		return apply_filters( 'mds_pages', $pages );
	}
}
