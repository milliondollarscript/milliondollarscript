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

namespace MillionDollarScript\Classes\System;

use MillionDollarScript\Classes\Admin\Notices;

use MillionDollarScript\Classes\Data\Config;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Forms;
use MillionDollarScript\Classes\Forms\FormFields;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Logs;
use MillionDollarScript\Classes\System\Filesystem;

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
						Logs::log( wp_sprintf(
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
				Logs::log( wp_sprintf(
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
//					Logs::log( wp_sprintf(
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

		$clear_woocommerce_orders = $_REQUEST['clear_woocommerce_orders'] ?? false;
		if ( $clear_woocommerce_orders !== false ) {
			if ( \MillionDollarScript\Classes\WooCommerce\WooCommerceFunctions::is_wc_active() ) {
				$order_ids = $wpdb->get_col( "SELECT order_id FROM " . MDS_DB_PREFIX . "orders" );
				foreach ( $order_ids as $order_id ) {
					$wc_order_id = Orders::get_wc_order_id_from_mds_order_id( $order_id );
					$order       = wc_get_order( $wc_order_id );
					if ( $order ) {
						$order->delete( true );
					}
				}
			}
		}

		// Delete all blocks and orders.
		$wpdb->query( "DELETE FROM " . MDS_DB_PREFIX . "blocks WHERE `status` != 'nfs'" );
		/** @noinspection SqlWithoutWhere */
		$wpdb->query( "DELETE FROM " . MDS_DB_PREFIX . "orders" );

		Orders::set_last_order_modification_time();

		// Delete any orders in progress from user meta.
		$users = get_users();
		foreach ( $users as $user ) {
			$user_id = $user->ID;
			delete_user_meta( $user_id, MDS_PREFIX . 'click_count' );
			delete_user_meta( $user_id, MDS_PREFIX . 'view_count' );

			Orders::reset_order_progress( $user_id );
		}

		// Delete mds-pixel posts and attachments.
		$args = [
			'post_type'   => Forms\FormFields::$post_type,
			'post_status' => 'any',
			'numberposts' => - 1,
		];

		$custom_posts = get_posts( $args );

		foreach ( $custom_posts as $post ) {
			$attachments = get_attached_media( '', $post->ID );
			foreach ( $attachments as $attachment ) {
				$fields = Forms\FormFields::get_fields();
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
		// clear any prior output to avoid "Headers already sent" warnings
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		if ( ! empty( $args ) ) {
			$dest = add_query_arg( $args, $dest );
		}

		if ( ! empty( $dest ) ) {

			if ( wp_doing_ajax() ) {
				wp_send_json_success( [
					'redirect' => $dest,
					'message'  => Language::get( 'Please wait...' ),
				] );
			}

			wp_safe_redirect( $dest );
			exit;
		}

		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {

			$location     = wp_sanitize_redirect( $_SERVER['HTTP_REFERER'] );
			$fallback_url = apply_filters( 'wp_safe_redirect_fallback', admin_url(), 302 );
			$location     = wp_validate_redirect( $location, $fallback_url );

			if ( wp_doing_ajax() ) {
				wp_send_json_success( [
					'redirect' => $location,
					'message'  => Language::get( 'Please wait...' ),
				] );
			}

			wp_safe_redirect( $_SERVER['HTTP_REFERER'] );
			exit;
		}


		if ( wp_doing_ajax() ) {
			wp_send_json_success( [
				'redirect' => home_url(),
				'message'  => Language::get( 'Please wait...' ),
			] );
		}

		wp_safe_redirect( home_url() );
		exit;
	}

	/**
	 * Safely redirects to a specified admin URL and terminates script execution.
	 *
	 * @param string $location The path or URL to redirect to.
	 * @param int    $status   The HTTP status code to use for the redirect. Default 302.
	 * @return void
	 */
	public static function redirect_admin( string $location, int $status = 302 ): void {
		wp_safe_redirect( $location, $status );
		exit;
	}

	/**
	 * Get an MDS page URL or API endpoint URL, with optional query args.
	 *
	 * @param string $page_name
	 * @param array  $args Optional query args to append
	 * @return string|null
	 */
	public static function get_page_url( string $page_name, array $args = [] ): ?string {
		$pages = self::get_pages();
		$url = false;
		
		// Try to get page-based URL first
		if ( isset( $pages[ $page_name ] ) && ! empty( $pages[ $page_name ]['page_id'] ) ) {
			$page_id = $pages[ $page_name ]['page_id'];
			// Validate that the page actually exists
			if ( get_post_status( $page_id ) !== false ) {
				$url = get_permalink( $page_id );
			} else {
				Logs::log( "MDS: Page ID {$page_id} for '{$page_name}' does not exist or is invalid" );
			}
		} else {
			$page_id = isset( $pages[ $page_name ]['page_id'] ) ? $pages[ $page_name ]['page_id'] : 'not set';
			Logs::log( "MDS: No valid page found for '{$page_name}', page_id: {$page_id}" );
		}
		
		// If page-based URL failed and this is a critical page, try to auto-create it
		if ( empty( $url ) && in_array( $page_name, [ 'order', 'grid', 'manage' ] ) ) {
			$created_page_id = self::ensure_page_exists( $page_name );
			if ( $created_page_id ) {
				$url = get_permalink( $created_page_id );
				// Clear the cached pages to reflect the new page
				global $mds_pages;
				$mds_pages = null;
			}
		}
		
		// Fallback to endpoint URL if page-based URL still failed
		if ( empty( $url ) ) {
			$endpoint = Options::get_option( 'endpoint', 'milliondollarscript' );
			if ( get_option( 'permalink_structure' ) ) {
				$url = trailingslashit( home_url( "/{$endpoint}/{$page_name}" ) );
			} else {
				$url = add_query_arg( $endpoint, $page_name, home_url( '/' ) );
			}
			Logs::log( "MDS: Using endpoint-based URL for '{$page_name}': {$url}" );
		}
		
		// append additional args if provided
		if ( $args && ! empty( $url ) ) {
			$url = add_query_arg( $args, $url );
		}
		
		return $url;
	}

	/**
	 * Ensure a specific page exists, creating it if necessary.
	 *
	 * @param string $page_name The page name (order, grid, etc.)
	 * @return int|false The page ID if successful, false otherwise
	 */
	private static function ensure_page_exists( string $page_name ): int|false {
		// Map page names to their option names and content
		$page_configs = [
			'order' => [
				'option_name' => 'users-order-page',
				'title' => Language::get( 'Order Pixels' ),
				'content' => '[milliondollarscript view="order"]'
			],
			'grid' => [
				'option_name' => 'grid-page',
				'title' => Language::get( 'Million Dollar Script Grid' ),
				'content' => '[milliondollarscript]'
			],
			'manage' => [
				'option_name' => 'users-manage-page',
				'title' => Language::get( 'Manage Pixels' ),
				'content' => '[milliondollarscript view="manage"]'
			]
		];
		
		if ( ! isset( $page_configs[ $page_name ] ) ) {
			return false;
		}
		
		$config = $page_configs[ $page_name ];
		
		// Check if page already exists but wasn't found (maybe option is wrong)
		$existing_page = get_page_by_title( $config['title'] );
		if ( $existing_page ) {
			// Update the option with the correct page ID
			Options::update_option( $config['option_name'], $existing_page->ID );
			return $existing_page->ID;
		}
		
		// Create the page
		$page_data = [
			'post_title'   => $config['title'],
			'post_content' => $config['content'],
			'post_status'  => 'publish',
			'post_type'    => 'page',
		];
		
		$page_id = wp_insert_post( $page_data );
		
		if ( is_wp_error( $page_id ) || ! $page_id ) {
			Logs::log( 'MDS: Failed to create page for ' . $page_name . ': ' . ( is_wp_error( $page_id ) ? $page_id->get_error_message() : 'Unknown error' ) );
			return false;
		}
		
		// Save the page ID in options
		Options::update_option( $config['option_name'], $page_id );
		
		Logs::log( 'MDS: Auto-created missing page for ' . $page_name . ' with ID: ' . $page_id );
		
		return $page_id;
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

	public static function get_page_ids(): array {
		global $mds_page_ids;

		if ( ! empty( $mds_page_ids ) ) {
			return $mds_page_ids;
		}

		$pagesArray = [
			'grid-page',
			'users-order-page',
			'users-write-ad-page',
			'users-confirm-order-page',
			'users-payment-page',
			'users-manage-page',
			'users-thank-you-page',
			'users-list-page',
			'users-upload-page',
			'users-no-orders-page',
		];

		foreach ( $pagesArray as $page ) {
			$mds_page_ids[ $page ] = Options::get_option( $page );
		}

		return apply_filters( 'mds_page_ids', $mds_page_ids );
	}

	public static function get_pages(): array {
		global $mds_pages;

		if ( ! empty( $mds_pages ) ) {
			return $mds_pages;
		}

		$page_ids  = self::get_page_ids();
		$mds_pages = [
			'grid'          => [
				'option'  => 'grid-page',
				'title'   => Language::get( 'Grid' ),
				'width'   => '1000px',
				'height'  => '1000px',
				'page_id' => $page_ids['grid-page'],
			],
			'order'         => [
				'option'  => 'users-order-page',
				'title'   => Language::get( 'Order Pixels' ),
				'page_id' => $page_ids['users-order-page'],
			],
			'write-ad'      => [
				'option'  => 'users-write-ad-page',
				'title'   => Language::get( 'Write Your Ad' ),
				'page_id' => $page_ids['users-write-ad-page'],
			],
			'confirm-order' => [
				'option'  => 'users-confirm-order-page',
				'title'   => Language::get( 'Confirm Order' ),
				'page_id' => $page_ids['users-confirm-order-page'],
			],
			'payment'       => [
				'option'  => 'users-payment-page',
				'title'   => Language::get( 'Payment' ),
				'page_id' => $page_ids['users-payment-page'],
			],
			'manage'        => [
				'option'  => 'users-manage-page',
				'title'   => Language::get( 'Manage Pixels' ),
				'page_id' => $page_ids['users-manage-page'],
			],
			'thank-you'     => [
				'option'  => 'users-thank-you-page',
				'title'   => Language::get( 'Thank-You!' ),
				'page_id' => $page_ids['users-thank-you-page'],
			],
			'list'          => [
				'option'  => 'users-list-page',
				'title'   => Language::get( 'List' ),
				'page_id' => $page_ids['users-list-page'],
			],
			'upload'        => [
				'option'  => 'users-upload-page',
				'title'   => Language::get( 'Upload' ),
				'page_id' => $page_ids['users-upload-page'],
			],
			'no-orders'     => [
				'option'  => 'users-no-orders-page',
				'title'   => Language::get( 'No Orders' ),
				'page_id' => $page_ids['users-no-orders-page'],
			],
		];

		return apply_filters( 'mds_pages', $mds_pages );
	}

	/**
	 * Get any error messages.
	 * WARNING: This is not sanitized. Do not output directly to the browser.
	 *
	 * @return string|null
	 */
	public static function get_error(): ?string {
		$error = null;

		if ( isset( $_GET['mds_error'] ) ) {
			$error = urldecode( $_GET['mds_error'] );
		} else if ( isset( $_POST['mds_error'] ) ) {
			$error = urldecode( $_POST['mds_error'] );
		}

		return $error;
	}

	/**
	 * Check if the current WP page has the endpoint at the start of the URL path.
	 *
	 * @return bool
	 */
	public static function has_endpoint(): bool {
		$MDS_ENDPOINT = Options::get_option( 'endpoint', 'milliondollarscript' );

		// Get the current URL
		$current_url = home_url( add_query_arg( null, null ) );

		// Parse the URL and get the path
		$path = parse_url( $current_url, PHP_URL_PATH );

		// Remove the leading slash if it exists
		if ( str_starts_with( $path, '/' ) ) {
			$path = substr( $path, 1 );
		}

		// Check if the path starts with the MDS endpoint
		return str_starts_with( $path, $MDS_ENDPOINT );
	}

	/**
	 * Check if the current WP page has the endpoint at the start of the URL path or is an AJAX request to determine if messages should be output.
	 *
	 * @return bool
	 */
	public static function has_endpoint_or_ajax(): bool {
		if ( wp_doing_ajax() ) {
			return true;
		}

		return self::has_endpoint();
	}

	/**
	 * Check if the current page is an MDS page (endpoint or created WP page).
	 *
	 * @return bool
	 */
	public static function is_mds_page(): bool {
		// Allow frontend AJAX via the mds_ajax action only
		if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'mds_ajax' === $_REQUEST['action'] ) {
			return true;
		}

		// Match endpoint URL patterns
		if ( self::has_endpoint() ) {
			return true;
		}

		// Match singular pages created via get_pages()
		if ( is_singular() ) {
			global $post;
			$page_ids = self::get_page_ids();
			if ( in_array( $post->ID, array_values($page_ids), false ) ) {
				return true;
			}
		}

		return false;
	}

	public static function output_message( $data = [] ): void {
		if ( wp_doing_ajax() ) {
			if ( isset( $data['success'] ) ) {
				if ( $data['success'] === false ) {
					wp_send_json_error( $data['message'] );
				} else {
					wp_send_json_success( $data['message'] );
				}
			}
		} else {
			echo $data['message'];
		}

	}

	/**
	 * Get order color based on an array of boolean values.
	 *
	 * @param array $values
	 * @param float $transparency A value of 1 will be opaque, a value of 0 will be transparent.
	 *
	 * @return string
	 */
	public static function get_order_color( array $values, float $transparency = 1.0 ): string {
		$count = count( $values );

		if ( $count == 0 ) {
			// Return an empty string if the input array is empty
			return '';
		}

		$trueCount = array_sum( $values );

		if ( $trueCount == $count ) {
			// All values are true, return green
			$color = 'rgba(0, 255, 0, ' . $transparency . ')';
		} elseif ( $trueCount == 0 ) {
			// All values are false, return red
			$color = 'rgba(255, 0, 0, ' . $transparency . ')';
		} else {
			// Some values are true and some are false, return orange
			$color = 'rgba(255, 130, 0, ' . $transparency . ')';
		}

		return $color;
	}

	public static function get_required_size( $x, $y, $banner_data ): array {

		$block_width  = $banner_data['BLK_WIDTH'];
		$block_height = $banner_data['BLK_HEIGHT'];

		$size[0] = $x;
		$size[1] = $y;

		$mod = ( $x % $block_width );

		if ( $mod > 0 ) {
			// width does not fit
			$size[0] = $x + ( $block_width - $mod );
		}

		$mod = ( $y % $block_height );

		if ( $mod > 0 ) {
			// height does not fit
			$size[1] = $y + ( $block_height - $mod );
		}

		return $size;
	}

	public static function get_clicks_for_today( $BID, $user_id = 0 ) {

		$date = current_time( 'Y-m-d' );

		$sql = "SELECT *, SUM(clicks) AS clk FROM `" . MDS_DB_PREFIX . "clicks` where banner_id='" . intval( $BID ) . "' AND `date`='$date' GROUP BY banner_id, block_id, user_id, date";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$row = mysqli_fetch_array( $result );

		if ( $row == null ) {
			return 0;
		}

		return $row['clk'];
	}

	/**
	 * If $BID is null then return for all banners
	 *
	 * @param string $BID
	 *
	 * @return int|mixed|void
	 */
	public static function get_clicks_for_banner( string $BID = '' ) {

		$sql = "SELECT *, SUM(clicks) AS clk FROM `" . MDS_DB_PREFIX . "clicks` where banner_id='" . intval( $BID ) . "'  GROUP BY banner_id, block_id, user_id, date";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		$row = mysqli_fetch_array( $result );

		if ( $row == null ) {
			return 0;
		}

		return $row['clk'];
	}

	public static function show_nav_status( $page_id ): void {

		echo '<div class="mds-nav-status">';

		switch ( $page_id ) {
			case 1:
				Language::out( '<b>Upload Your pixels</b> -> Write Your Ad -> Confirm -> Payment -> Thank you!' );
				break;
			case 2:
				Language::out( 'Upload Your pixels -> <b>Write Your Ad</b> -> Confirm -> Payment -> Thank you!' );
				break;
			case 3:
				Language::out( 'Upload Your pixels -> Write Your Ad -> <b>Confirm</b> -> Payment -> Thank you!' );
				break;
		}

		echo "</div>";
	}

	public static function trim_date( $gmdate ) {
		preg_match( "/(\d+-\d+-\d+).+/", $gmdate, $m );

		return $m[1];
	}

	public static function truncate_html_str( $s, $MAX_LENGTH, &$trunc_str_len ) {

		$trunc_str_len = 0;

		if ( func_num_args() > 3 ) {
			$add_ellipsis = func_get_arg( 3 );
		} else {
			$add_ellipsis = true;
		}

		if ( func_num_args() > 4 ) {
			$with_tags = func_get_arg( 4 );
		} else {
			$with_tags = false;
		}

		if ( $with_tags ) {
			$tag_expr = "|<[^>]+>";
		}

		$offset          = 0;
		$character_count = 0;
		# match a character, or characters encoded as html entity
		# treat each match as a single character
		#
		$str = "";
		while ( ( preg_match( '/(&#?[0-9A-z]+;' . $tag_expr . '|.|\n)/', $s, $maches, PREG_OFFSET_CAPTURE, $offset ) && ( $character_count < $MAX_LENGTH ) ) ) {
			$offset += strlen( $maches[0][0] );
			$character_count ++;
			$str .= $maches[0][0];
		}
		if ( ( $character_count == $MAX_LENGTH ) && ( $add_ellipsis ) ) {
			$str = $str . "...";
		}
		$trunc_str_len = $character_count;

		return $str;
	}

	public static function get_pixel_image_size( $order_id ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "blocks WHERE order_id=%d",
				$order_id
			),
			ARRAY_A
		);

		// find high x, y & low x, y
		// low x,y is the top corner, high x,y is the bottom corner
		$BID = 1;
		foreach ( $results as $block_row ) {

			$high_x = ! isset( $high_x ) ? $block_row['x'] : $high_x;
			$high_y = ! isset( $high_y ) ? $block_row['y'] : $high_y;
			$low_x  = ! isset( $low_x ) ? $block_row['x'] : $low_x;
			$low_y  = ! isset( $low_y ) ? $block_row['y'] : $low_y;

			if ( $block_row['x'] > $high_x ) {
				$high_x = $block_row['x'];
			}

			if ( $block_row['y'] > $high_y ) {
				$high_y = $block_row['y'];
			}

			if ( $block_row['y'] < $low_y ) {
				$low_y = $block_row['y'];
			}

			if ( $block_row['x'] < $low_x ) {
				$low_x = $block_row['x'];
			}

			$BID = $block_row['banner_id'];
		}

		$high_x = ! isset( $high_x ) ? 0 : $high_x;
		$high_y = ! isset( $high_y ) ? 0 : $high_y;
		$low_x  = ! isset( $low_x ) ? 0 : $low_x;
		$low_y  = ! isset( $low_y ) ? 0 : $low_y;

		$banner_data = load_banner_constants( $BID );

		$size['x'] = ( $high_x + $banner_data['BLK_WIDTH'] ) - $low_x;
		$size['y'] = ( $high_y + $banner_data['BLK_HEIGHT'] ) - $low_y;

		return $size;
	}

	public static function elapsedtime( $sec ) {
		$days = floor( $sec / 86400 );
		$hrs  = floor( ( $sec % 86400 ) / 3600 );
		$mins = floor( ( $sec % 3600 ) / 60 );

		$tstring = "";
		if ( $days > 0 ) {
			$tstring .= $days . "d, ";
		}
		if ( $hrs > 0 ) {
			$tstring .= $hrs . "h, ";
		}
		$tstring .= $mins . "m";

		return $tstring;
	}

	public static function powered_by_mds() {
		?>
        <div class="powered-by-mds">
            Powered By <a target="_blank" href="https://milliondollarscript.com/">Million Dollar Script</a>
        </div>
		<?php
	}

	public static function shutdown() {
		$isError = false;
		if ( $error = error_get_last() ) {
			switch ( $error['type'] ) {
				case E_ERROR:
				case E_CORE_ERROR:
				case E_COMPILE_ERROR:
				case E_USER_ERROR:
					$isError = true;
					break;
			}
		}

		if ( $isError ) {
			echo "Script execution halted ({$error['message']})";

			// php memory error
			if ( substr_count( $error['message'], 'Allowed memory size' ) ) {
				echo "<br />Try increasing your PHP memory limit and restarting the web server.";
			}
		} else {
			echo "Script completed";
		}
	}

	/**
	 * Convert an array memory string values to integer values
	 *
	 * @param array $Sizes
	 *
	 * @return int
	 */
	public static function convertMemoryToBytes( $Sizes ) {
		for ( $x = 0; $x < count( $Sizes ); $x ++ ) {
			$Last  = strtolower( $Sizes[ $x ][ strlen( $Sizes[ $x ] ) - 1 ] );
			$limit = intval( $Sizes[ $x ] );
			if ( $Last == 'k' ) {
				$limit *= 1024;
			} else if ( $Last == 'm' ) {
				$limit *= 1024;
				$limit *= 1024;
			} else if ( $Last == 'g' ) {
				$limit *= 1024;
				$limit *= 1024;
				$limit *= 1024;
			} else if ( $Last == 't' ) {
				$limit *= 1024;
				$limit *= 1024;
				$limit *= 1024;
				$limit *= 1024;
			}
			$Sizes[ $x ] = $limit;
		}

		return max( $Sizes );
	}

	/**
	 * Attempt to increase the Memory Limit based on image size.
	 *
	 * @link https://alvarotrigo.com/blog/watch-beauty-and-the-beast-2017-full-movie-online-streaming-online-and-download/
	 *
	 * @param string $filename
	 */
	public static function setMemoryLimit( $filename ) {
		if ( ! file_exists( $filename ) ) {
			Logs::log( "setMemoryLimit error, file not found: " . $filename );

			return;
		}

		//this might take time, so we limit the maximum execution time to 50 seconds
		set_time_limit( 50 );

		//initializing variables
		$maxMemoryUsage = Utility::convertMemoryToBytes( array( "512M" ) );
		$currentLimit   = Utility::convertMemoryToBytes( array( ini_get( 'memory_limit' ) ) );
		$currentUsage   = memory_get_usage();

		//getting the image width and height
		list( $width, $height ) = getimagesize( $filename );

		//calculating the needed memory
		$size = intval( $currentUsage ) + $currentLimit + ( floor( $width * $height * 4 * 1.5 + 1048576 ) );

		// make sure memory limit is within range
		$size = min( $size, $maxMemoryUsage );

		//updating the default value
		ini_set( 'memory_limit', $size );
	}

	/**
	 * Check available pixels
	 *
	 * @param $in_str
	 *
	 * @return bool
	 */
	public static function check_pixels( $in_str ): bool {

		global $f2, $wpdb;

		// cannot reserve pixels if user isn't logged in
		mds_wp_login_check();

		$BID      = $f2->bid();
		$order_id = Orders::get_current_order_id();

		// Make sure input is properly formatted
		$selected = explode( ",", $in_str );
		$in_array = array_map( 'intval', $selected );
		$in_str   = implode( ',', $in_array );

        // Only proceed if we have blocks to check
		// and not the first block which starts at 0
        if (empty($in_str) && $in_str !== '0') {
            echo json_encode( [
                "error" => "true",
                "type"  => "unavailable",
                "data"  => [
                    "value" => Language::get( 'No blocks selected for checking.' ) . " (E431)",
                ]
            ] );
            return false;
        }

		// First check: direct blocks table query
		$sql      = $wpdb->prepare( "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE banner_id=%d AND block_id IN($in_str) AND order_id != %d", $BID, $order_id );
		$results = $wpdb->get_results( $sql );

		if ( $wpdb->num_rows > 0 ) {

            
			echo json_encode( [
				"error" => "true",
				"type"  => "unavailable",
				"data"  => [
					"value" => Language::get( 'This space is not available! Please try to place your pixels in a different area.' ) . " (E432)",
				]
			] );

			return false;
		}

		// Second check: orders table for overlapping blocks
		$sql    = $wpdb->prepare( "SELECT blocks FROM " . MDS_DB_PREFIX . "orders WHERE banner_id=%d AND order_id != %d AND status != 'deleted'", $BID, $order_id );
		$result = $wpdb->get_results( $sql );
        


		foreach ( $result as $row ) {
			if (empty($row->blocks)) continue;
            
			$entries = explode( ",", $row->blocks );
			$entries = array_map('intval', $entries); // Ensure we're comparing integers
			
			$intersection = array_intersect($entries, $in_array);
			if (!empty($intersection)) {

                
				echo json_encode( [
					"error" => "true",
					"type"  => "unavailable",
					"data"  => [
						"value" => Language::get( 'This space is not available! Please try to place your pixels in a different area.' ) . " (E433)",
					]
				] );
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if MDS should run. This checks if it is an admin request. It also checks REST requests too, such as when updating a page.
	 *
	 * @return bool
	 */
	public static function should_stop(): bool {

		if ( is_admin() ) {
			return true;
		}

		// Detect REST requests for WP
		if ( defined( 'REST_REQUEST' ) && str_contains( $_SERVER['REQUEST_URI'], '/wp-json/wp' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Process the grid image, map, and publish the changes.
	 *
	 * This function acts as a wrapper for processing and publishing grid updates.
	 * It calls process_image, publish_image, and process_map sequentially.
	 * Assumes the necessary include files (image_functions.php, map_functions.php) are loaded.
	 *
	 * @since 2.5.11
	 * @param int $banner_id The ID of the banner/grid to process.
	 * @return void
	 */
	public static function process_grid_image( int $banner_id ): void {
		// Ensure functions are available (might require includes elsewhere)
		if ( function_exists( 'process_image' ) && function_exists( 'publish_image' ) && function_exists( 'process_map' ) ) {
			// Process the image file for the grid
			process_image( $banner_id );
			// Publish the updated grid image
			publish_image( $banner_id );
			// Process the image map for the grid
			process_map( $banner_id );
		} else {
			// Log an error or handle the case where functions are missing
			// Logs::log('MDS Error: Required image processing functions not found in Utility::process_grid_image.');
			// Trigger a user notice using the Notices class
			if ( current_user_can( 'manage_options' ) ) {
				// Use the Notices class for consistent handling of admin notices
				Notices::add_notice(
					esc_html__( 'Million Dollar Script Error: Could not process grid image. Required functions are missing.', 'milliondollarscript' ),
					'error'
				);
			}
		}
	}

	/**
	 * Redirects the user back to the previous page within the Approve Pixels admin screen.
	 *
	 * Attempts to use the HTTP referer to determine the exact previous URL,
	 * including query parameters like grid ID (BID), approval status (app), and pagination offset.
	 * Falls back to the main Approve Pixels page if the referer is not available or invalid.
	 *
	 * @since 2.5.11
	 * @return void Terminates script execution after redirect.
	 */
	public static function redirect_to_previous_approve_page(): void {
		$referer = wp_get_referer();
		// Default fallback URL to the main approve pixels page
		$redirect_url = admin_url( 'admin.php?page=mds-approve-pixels' );

		// Try to use the referer if it looks like the approve pixels page
		if ( $referer && strpos( $referer, 'page=mds-approve-pixels' ) !== false ) {
			$redirect_url = $referer;
		} else {
			// If referer is not helpful, build a basic URL (might lose pagination state)
			// Attempt to get BID and app status from the current request
			$bid = isset( $_REQUEST['BID'] ) ? (int) $_REQUEST['BID'] : 0;
			$app = isset( $_REQUEST['app'] ) ? sanitize_key( $_REQUEST['app'] ) : ''; // Default to showing both or based on context

			$args = ['page' => 'mds-approve-pixels'];
			if ( $bid > 0 ) {
				$args['BID'] = $bid;
			}
			if ( ! empty( $app ) ) {
				$args['app'] = $app;
			}
			$redirect_url = add_query_arg( $args, admin_url( 'admin.php' ) );
		}

		// Perform the redirect using WordPress standard function
		wp_safe_redirect( $redirect_url );
		// Ensure script stops execution immediately after setting the redirect header
		exit;
	}

	/**
	 * Outputs an HTML dropdown (<select>) of available grids.
	 *
	 * Queries the database for all banners/grids and generates <option> tags.
	 * Marks the grid matching the current BID as selected.
	 *
	 * @since 2.5.11
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 * @param int $current_bid The ID of the currently selected grid (optional).
	 * @return void Outputs HTML directly.
	 */
	public static function grid_dropdown( int $current_bid = 0 ): void {
		global $wpdb;
		$grids = $wpdb->get_results( "SELECT banner_id, name FROM " . MDS_DB_PREFIX . "banners ORDER BY name ASC" );

		echo '<select name="BID" id="mds-grid-dropdown">';
		if ( empty( $grids ) ) {
			echo '<option value="0">' . esc_html__( 'No grids found', 'milliondollarscript' ) . '</option>';
		} else {
			// Add an option for 'All Grids' or similar if needed, depending on context
			// echo '<option value="0">' . esc_html__('All Grids', 'milliondollarscript') . '</option>';
			foreach ( $grids as $grid ) {
				$selected = selected( $current_bid, $grid->banner_id, false );
				echo '<option value="' . esc_attr( $grid->banner_id ) . '"' . $selected . '>' . esc_html( $grid->name ) . ' (ID: ' . esc_html( $grid->banner_id ) . ')</option>';
			}
		}
		echo '</select>';
	}

	/**
	 * Displays pagination links for admin tables or lists.
	 *
	 * Based on the WordPress core pagination display, adapted for MDS usage.
	 *
	 * @since 2.5.11
	 * @param int    $total_items Total number of items to paginate.
	 * @param int    $per_page    Number of items to display per page.
	 * @param int    $offset      The current offset (number of items skipped).
	 * @param string $base_url    The base URL for pagination links. Should include necessary query parameters except for 'offset'.
	 * @return void Outputs HTML directly.
	 */
	public static function display_pagination( int $total_items, int $per_page, int $offset, string $base_url ): void {
		if ( $total_items <= $per_page ) {
			// No pagination needed
			return;
		}

		$total_pages = ceil( $total_items / $per_page );
		$current_page = floor( $offset / $per_page ) + 1;

		// Ensure base_url has a query string separator if needed
		$base_url = strpos($base_url, '?') === false ? $base_url . '?' : $base_url;
		// Ensure base_url ends with '&' if it already has query params
        if (strpos($base_url, '?') !== false && substr($base_url, -1) !== '&' && substr($base_url, -1) !== '?') {
            $base_url .= '&';
        }

		$page_links = paginate_links( [
			'base'      => $base_url . 'offset=%#%', // Use offset directly
			'format'    => '', // Use 'offset' in base, so format is empty
			'total'     => $total_pages,
			'current'   => $current_page,
			'add_args'  => false, // Already included in base_url
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
			'type'      => 'plain', // Output plain links
			// We need to calculate offset based on page number for paginate_links
			// This requires overriding how paginate_links generates the URLs.
			// Let's generate links manually for offset-based pagination.
		] );

		// Manual link generation for offset
		echo '<span class="pagination-links">';

		// First page link
		if ( $current_page > 1 ) {
			echo '<a class="first-page button" href="' . esc_url( $base_url . 'offset=0' ) . '"><span aria-hidden="true">«</span></a> '; // First
		} else {
			echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span> '; // First
		}

		// Previous page link
		if ( $current_page > 1 ) {
			$prev_offset = ( $current_page - 2 ) * $per_page;
			echo '<a class="prev-page button" href="' . esc_url( $base_url . 'offset=' . $prev_offset ) . '"><span aria-hidden="true">‹</span></a> '; // Previous
		} else {
			echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span> '; // Previous
		}

		// Page number input
		echo '<span class="paging-input">';
		echo '<label for="current-page-selector" class="screen-reader-text">' . esc_html__( 'Current Page', 'milliondollarscript' ) . '</label>';
		echo '<input class="current-page" id="current-page-selector" type="text" name="paged" value="' . esc_attr( $current_page ) . '" size="' . strlen( (string) $total_pages ) . '" aria-describedby="table-paging" />';
		echo '<span class="tablenav-paging-text"> ' . sprintf( esc_html__( 'of %s', 'milliondollarscript' ), '<span class="total-pages">' . esc_html( $total_pages ) . '</span>' ) . '</span>';
		echo '</span>';

		// Next page link
		if ( $current_page < $total_pages ) {
			$next_offset = $current_page * $per_page;
			echo ' <a class="next-page button" href="' . esc_url( $base_url . 'offset=' . $next_offset ) . '"><span aria-hidden="true">›</span></a>'; // Next
		} else {
			// Next
			echo ' <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
		}

		// Last page link
		if ( $current_page < $total_pages ) {
			$last_offset = ( $total_pages - 1 ) * $per_page;
			echo ' <a class="last-page button" href="' . esc_url( $base_url . 'offset=' . $last_offset ) . '"><span aria-hidden="true">»</span></a>'; // Last
		} else {
			// Last
			echo ' <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
		}

		// .pagination-links
		echo '</span>';
	}

	/**
	 * Retrieves the build date of the plugin.
	 *
	 * @param bool $format Whether to return the date in a specific format.
	 * @return bool|int|string The build date in the specified format, or false if not found.
	 */
	public static function get_mds_build_date( $format = false ): bool|int|string {
		global $MDSUpdateChecker;
	
		$timestamp = false;
	
		if ( $MDSUpdateChecker ) {
			$update = $MDSUpdateChecker->getUpdate();
			if ( $update && ! empty( $update->last_updated ) ) {
				// The last_updated field is usually a string like '2023-10-27 15:30:00'
				$timestamp = strtotime( $update->last_updated );
			}
		}
	
		// Fallback 1: Try parsing the local readme.txt
		if ( ! $timestamp ) {
			// Assumes readme.txt is in the plugin root
			$readme_path = dirname( MDS_CORE_PATH ) . '/readme.txt';
			if ( is_readable( $readme_path ) ) {
				$readme_content = file_get_contents( $readme_path );
				if ( $readme_content && preg_match( '/^Last updated:\s*(.*)$/im', $readme_content, $matches ) ) {
					$timestamp = strtotime( trim( $matches[1] ) );
				}
			}
		}
	
		// Fallback 2: File modification time of this file
		if ( ! $timestamp ) {
			$timestamp = filemtime( __FILE__ );
		}
	
		if ( $format && $timestamp ) {
			return date( 'Y-m-d H:i:s', $timestamp );
		} elseif ( $timestamp ) {
			return $timestamp;
		}
	
		// Return false if we couldn't get a date
		return false;
	}
	
	/**
	 * Creates a single MDS page with standard shortcode and metadata.
	 *
	 * @param string $page_type The type identifier for the page (e.g., 'grid', 'order').
	 * @param array  $data      The page data array from get_pages() (must include 'option', 'title').
	 * 
	 * @return int|\WP_Error The created page ID on success, WP_Error on failure.
	 */
	public static function create_mds_page( string $page_type, array $data ) {
		if ( empty( $data['option'] ) || empty( $data['title'] ) ) {
			return new \WP_Error( 'missing_data', Language::get( 'Missing required page data (option or title).' ) );
		}

		$id     = $data['id'] ?? 1;
		$align  = $data['align'] ?? 'center';
		$width  = $data['width'] ?? '100%';
		$height = $data['height'] ?? 'auto';
		$title  = $data['title'];
		$option = $data['option'];

		// Construct the standard shortcode
		$shortcode = '[milliondollarscript id="%d" align="%s" width="%s" height="%s" type="%s"]';
		$content   = sprintf( $shortcode, $id, $align, $width, $height, $page_type );

		$page_details = [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
		];

		$page_id = wp_insert_post( $page_details, true ); // Pass true for WP_Error on failure

		if ( is_wp_error( $page_id ) ) {
			return $page_id; // Return the error object
		}

		// Add Divi theme compatibility meta
		$current_theme = wp_get_theme();
		if ( 'Divi' === $current_theme->get( 'Name' ) || ( $current_theme->parent() && 'Divi' === $current_theme->parent()->get( 'Name' ) ) ) {
			update_post_meta( $page_id, '_et_pb_page_layout', 'et_no_sidebar' );
		}

		// Update the option using the standard WP function with underscore prefix
		update_option( '_' . MDS_PREFIX . $option, $page_id );

		// Store post modified time
		$modified_time = get_post_modified_time( 'Y-m-d H:i:s', false, $page_id );
		update_post_meta( $page_id, '_modified_time', $modified_time );

		// Create MDS page metadata
		try {
			$metadata_manager = \MillionDollarScript\Classes\Data\MDSPageMetadataManager::getInstance();
			
			$metadata_data = [
				'page_type' => $page_type,
				'creation_method' => 'wizard',
				'creation_source' => 'setup_wizard_v' . MDS_VERSION,
				'mds_version' => MDS_VERSION,
				'content_type' => 'shortcode',
				'status' => 'active',
				'confidence_score' => 1.0,
				'shortcode_attributes' => [
					'id' => $id,
					'align' => $align,
					'width' => $width,
					'height' => $height,
					'type' => $page_type
				],
				'page_config' => [
					'option_key' => $option,
					'shortcode_template' => $shortcode
				],
				'display_settings' => [
					'align' => $align,
					'width' => $width,
					'height' => $height
				],
				'integration_settings' => [
					'theme_name' => $current_theme->get( 'Name' ),
					'divi_layout' => ( 'Divi' === $current_theme->get( 'Name' ) || ( $current_theme->parent() && 'Divi' === $current_theme->parent()->get( 'Name' ) ) ) ? 'et_no_sidebar' : null
				]
			];
			
			$metadata_result = $metadata_manager->createMetadata( $page_id, $metadata_data );
			
			if ( is_wp_error( $metadata_result ) ) {
				// Log the error but don't fail page creation
				Logs::log( 'MDS Page Metadata creation failed for page ' . $page_id . ': ' . $metadata_result->get_error_message() );
			}
		} catch ( \Exception $e ) {
			// Log the error but don't fail page creation
			Logs::log( 'MDS Page Metadata creation exception for page ' . $page_id . ': ' . $e->getMessage() );
		}

		return $page_id; // Return the successfully created page ID
	}

	/**
	 * Check if the Million Dollar Script custom post type exists.
	 */
	public static function check_cpt_exists() {
		return post_type_exists( FormFields::$post_type );
	}

	/**
	 * Send "Insufficient permissions!" json error and wp_die.
	 */
	public static function no_perms(): void {
		$error = new \WP_Error( '403', Language::get( "Insufficient permissions!" ) );
		wp_send_json_error( $error );
		wp_die();
	}

    /**
     * Check if we're in a development environment
     * 
     * @return bool True if development environment
     */
    protected static function is_development_environment(): bool {
        $extension_server_url = Options::get_option('extension_server_url', 'http://host.docker.internal:15346');
        return strpos($extension_server_url, 'localhost') !== false || 
               strpos($extension_server_url, '127.0.0.1') !== false ||
               strpos($extension_server_url, 'host.docker.internal') !== false ||
               strpos($extension_server_url, 'http://') === 0;
    }
}
