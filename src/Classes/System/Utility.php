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

use MillionDollarScript\Classes\Data\Config;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Forms;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;

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

		return get_permalink( $pages[ $page_name ]['page_id'] );
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
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
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

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "blocks WHERE order_id=" . intval( $order_id );
		$result3 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		// find high x, y & low x, y
		// low x,y is the top corner, high x,y is the bottom corner
		$BID = 1;
		while ( $block_row = mysqli_fetch_array( $result3 ) ) {

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
			error_log( "setMemoryLimit error, file not found: " . $filename );

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
		$MEMORY_LIMIT = Config::get( 'MEMORY_LIMIT' );
		$size         = min( max( $size, $MEMORY_LIMIT ), $maxMemoryUsage );

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

		// check if it is free
		$available = true;

		$selected = explode( ",", $in_str );
		$in_array = array_map( 'intval', $selected );
		$in_str   = implode( ',', $in_array );
		$sql      = $wpdb->prepare( "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE banner_id=%d AND block_id IN($in_str) AND order_id != %d", $BID, $order_id );
		$wpdb->get_results( $sql );

		if ( $wpdb->num_rows > 0 ) {
			echo json_encode( [
				"error" => "true",
				"type"  => "unavailable",
				"data"  => [
					"value" => Language::get( 'This space is not available! Please try to place your pixels in a different area.' ) . " (E432)",
				]
			] );

			$available = false;
		}

		// TODO: optimize or remove this but make sure blocks are added so they get checked above.
		if ( $available ) {
			$sql    = $wpdb->prepare( "SELECT blocks FROM " . MDS_DB_PREFIX . "orders WHERE banner_id=%d AND order_id != %d AND status != 'deleted'", $BID, $order_id );
			$result = $wpdb->get_results( $sql );

			foreach ( $result as $row ) {
				$entries = explode( ",", $row->blocks );
				if ( ! empty( array_intersect( $entries, $selected ) ) ) {
					echo json_encode( [
						"error" => "true",
						"type"  => "unavailable",
						"data"  => [
							"value" => Language::get( 'This space is not available! Please try to place your pixels in a different area.' ) . " (E433)",
						]
					] );
					$available = false;
					break;
				}
			}
		}

		return $available;
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
}
