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

namespace MillionDollarScript\Classes\Forms;

use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\Orders\Steps;
use MillionDollarScript\Classes\System\Functions;
use MillionDollarScript\Classes\System\Logs;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Data\Options;

defined( 'ABSPATH' ) or exit;

/**
 * Forms for Million Dollar Script
 */
class Forms {
	public function __construct() {
		add_action( 'admin_post_nopriv_mds_form_submission', [ __CLASS__, 'mds_form_submission' ] );
		add_action( 'admin_post_mds_form_submission', [ __CLASS__, 'mds_form_submission' ] );
		add_action( 'admin_post_mds_admin_form_submission', [ __CLASS__, 'mds_admin_form_submission' ] );
	}

	/**
	 * Process file upload for order-pixels page
	 * 
	 * @return array Upload result with success status and any error messages
	 */
	private static function process_order_pixels_upload(): array {
		global $f2;
		
		$result = [
			'success' => false,
			'error' => '',
			'messages' => ''
		];
		
		// Validate file upload
		if ( ! isset( $_FILES['graphic'] ) || $_FILES['graphic']['tmp_name'] == '' ) {
			$result['error'] = Language::get( 'No file was uploaded. Please select an image file.' );
			return $result;
		}
		
		// Check for upload errors
		if ( $_FILES['graphic']['error'] !== UPLOAD_ERR_OK ) {
			switch ( $_FILES['graphic']['error'] ) {
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					$result['error'] = Language::get( 'The uploaded file is too large.' );
					break;
				case UPLOAD_ERR_PARTIAL:
					$result['error'] = Language::get( 'The file was only partially uploaded. Please try again.' );
					break;
				case UPLOAD_ERR_NO_TMP_DIR:
					$result['error'] = Language::get( 'Missing temporary folder. Please contact the administrator.' );
					break;
				case UPLOAD_ERR_CANT_WRITE:
					$result['error'] = Language::get( 'Failed to write file to disk. Please contact the administrator.' );
					break;
				case UPLOAD_ERR_EXTENSION:
					$result['error'] = Language::get( 'File upload stopped by extension. Please contact the administrator.' );
					break;
				default:
					$result['error'] = Language::get( 'Unknown upload error. Please try again.' );
					break;
			}
			return $result;
		}
		
		// Get required data
		$BID = $f2->bid();
		$banner_data = load_banner_constants( $BID );
		$uploaddir = Utility::get_upload_path() . "images/";
		
		// Ensure upload directory exists and is writable
		if ( ! is_dir( $uploaddir ) ) {
			if ( ! wp_mkdir_p( $uploaddir ) ) {
				$result['error'] = Language::get( 'Upload directory could not be created. Please contact the administrator.' );
				return $result;
			}
		}
		
		if ( ! is_writable( $uploaddir ) ) {
			$result['error'] = Language::get( 'Upload directory is not writable. Please contact the administrator.' );
			return $result;
		}
		
		// Comprehensive security validation
		$validation = \MillionDollarScript\Classes\System\FileValidator::validate_image_upload( $_FILES['graphic'] );
		
		if ( ! $validation['valid'] ) {
			$result['error'] = $validation['error'];
			
			// Log security violation attempt
			Logs::log( 'MDS Security: Invalid upload attempt by user ' . get_current_user_id() . ': ' . $validation['error'] );
			
			return $result;
		}
		
		// Get safe file extension for processing
		$file_parts = pathinfo( $_FILES['graphic']['name'] );
		$ext = strtolower( $file_parts['extension'] );
		
		// Clean up old temp files (24 hours)
		if ( $dh = opendir( $uploaddir ) ) {
			while ( ( $file = readdir( $dh ) ) !== false ) {
				if ( $file === '.' || $file === '..' ) {
					continue;
				}
				
				$file_path = $uploaddir . $file;
				if ( ! is_file( $file_path ) ) {
					continue;
				}
				
				$elapsed_time = 60 * 60 * 24; // 24 hours
				$stat = stat( $file_path );
				if ( $stat && $stat['mtime'] < ( time() - $elapsed_time ) ) {
					if ( str_contains( $file, 'tmp_' . Orders::get_current_order_id() ) ) {
						unlink( $file_path );
					}
				}
			}
			closedir( $dh );
		}
		
		// Generate secure filename and use secure upload directory
		$secure_filename = \MillionDollarScript\Classes\System\FileValidator::generate_secure_filename( $_FILES['graphic']['name'], 'tmp_' . Orders::get_current_order_id() . '_' );
		$secure_uploaddir = \MillionDollarScript\Classes\System\FileValidator::get_secure_upload_path( 'images' );
		$uploadfile = $secure_uploaddir . $secure_filename;
		
		if ( ! move_uploaded_file( $_FILES['graphic']['tmp_name'], $uploadfile ) ) {
			$result['error'] = Language::get( 'Upload failed: Could not process the uploaded file. Please try again.' );
			return $result;
		}
		
		// Set memory limit for image processing
		Utility::setMemoryLimit( $uploadfile );
		
		// Get image dimensions
		$size = getimagesize( $uploadfile );
		if ( ! $size ) {
			unlink( $uploadfile );
			$result['error'] = Language::get( 'Invalid image file. Please upload a valid image.' );
			return $result;
		}
		
		// Validate minimum image dimensions
		if ( $size[0] < 1 || $size[1] < 1 ) {
			unlink( $uploadfile );
			$result['error'] = Language::get( 'Image dimensions are too small. Please upload a larger image.' );
			return $result;
		}
		
		// Calculate required size and pixel count
		$reqsize = Utility::get_required_size( $size[0], $size[1], $banner_data );
		$pixel_count = $reqsize[0] * $reqsize[1];
		$block_size = $pixel_count / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
		
		// Handle auto-resize if enabled
		if ( Options::get_option( 'resize' ) == 'YES' ) {
			$rescale = [];
			if ( ( $block_size > $banner_data['G_MAX_BLOCKS'] ) && ( $banner_data['G_MAX_BLOCKS'] > 0 ) ) {
				$rescale['x'] = min( $banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_WIDTH'], $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'], $reqsize[0] );
				$rescale['y'] = min( $banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_HEIGHT'], $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'], $reqsize[1] );
			} else if ( ( $block_size < $banner_data['G_MIN_BLOCKS'] ) && ( $banner_data['G_MIN_BLOCKS'] > 0 ) ) {
				$rescale['x'] = min( $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_WIDTH'], $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'], $reqsize[0] );
				$rescale['y'] = min( $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_HEIGHT'], $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'], $reqsize[1] );
			}
			
			// Resize image if needed
			if ( isset( $rescale['x'] ) ) {
				try {
					if ( class_exists( 'Imagick' ) ) {
						$imagine = new \Imagine\Imagick\Imagine();
					} else if ( function_exists( 'gd_info' ) ) {
						$imagine = new \Imagine\Gd\Imagine();
					} else {
						$result['error'] = Language::get( 'Image processing library not available. Please contact the administrator.' );
						unlink( $uploadfile );
						return $result;
					}
					
					$image = $imagine->open( $uploadfile );
					$resize = new \Imagine\Image\Box( $rescale['x'], $rescale['y'] );
					$image->resize( $resize );
					
					$fileinfo = pathinfo( $uploadfile );
					$newname = ( $fileinfo['dirname'] ? $fileinfo['dirname'] . DIRECTORY_SEPARATOR : '' ) . $fileinfo['filename'] . '.png';
					$image->save( $newname );
					
					// Update dimensions after resize
					$size[0] = $rescale['x'];
					$size[1] = $rescale['y'];
					$reqsize[0] = $rescale['x'];
					$reqsize[1] = $rescale['y'];
					$pixel_count = $reqsize[0] * $reqsize[1];
					$block_size = $pixel_count / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
				} catch ( \Exception $e ) {
					$result['error'] = Language::get( 'Image resize failed: ' ) . $e->getMessage();
					unlink( $uploadfile );
					return $result;
				}
			}
		} else {
			// Check size limits when auto-resize is disabled
			if ( ( $block_size > $banner_data['G_MAX_BLOCKS'] ) && ( $banner_data['G_MAX_BLOCKS'] > 0 ) ) {
				$limit = $banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'];
				$result['error'] = Language::get_replace(
					'Sorry, the uploaded image is too big. This image has %COUNT% pixels... A limit of %MAX_PIXELS% pixels per order is set.',
					[ '%MAX_PIXELS%', '%COUNT%' ],
					[ $limit, $pixel_count ]
				);
				unlink( $uploadfile );
				return $result;
			} else if ( ( $block_size < $banner_data['G_MIN_BLOCKS'] ) && ( $banner_data['G_MIN_BLOCKS'] > 0 ) ) {
				$limit = $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'];
				$result['error'] = Language::get_replace(
					'Sorry, you are required to upload an image with at least %MIN_PIXELS% pixels. This image only has %COUNT% pixels...',
					[ '%MIN_PIXELS%', '%COUNT%' ],
					[ $limit, $pixel_count ]
				);
				unlink( $uploadfile );
				return $result;
			}
		}
		
		// After all security validations pass, copy the file to the expected standard location
		// This ensures compatibility with existing code that expects files in the standard images directory
		$standard_upload_dir = Utility::get_upload_path() . "images/";
		$standard_filename = 'tmp_' . Orders::get_current_order_id() . '.png';
		$standard_file_path = $standard_upload_dir . $standard_filename;
		
		// Ensure the standard directory exists
		if ( ! file_exists( $standard_upload_dir ) ) {
			wp_mkdir_p( $standard_upload_dir );
		}
		
		// Copy the validated file to the standard location expected by the rest of the system
		if ( ! copy( $uploadfile, $standard_file_path ) ) {
			$result['error'] = Language::get( 'Failed to prepare uploaded file for processing. Please try again.' );
			// Clean up the secure file
			unlink( $uploadfile );
			return $result;
		}
		
		// Success - file is now available in both secure and standard locations
		$result['success'] = true;
		$result['file_path'] = $standard_file_path;
		return $result;
	}

	/**
	 * Handle specific forms based on the form_id parameter.
	 *
	 * @return void
	 */
	public static function mds_form_submission(): void {
		require_once MDS_CORE_PATH . 'include/ads.inc.php';
		Functions::verify_nonce( 'mds-form' );

		if ( ! isset( $_POST['mds_dest'] ) || ! is_user_logged_in() ) {
			Utility::redirect();
		}

		global $mds_error;

		$mds_dest = $_POST['mds_dest'];

		$params = [];

		switch ( $mds_dest ) {
			case 'banner_order':
			case 'order':
			case 'select':
				// Check if this is a file upload from order-pixels page
				if ( isset( $_FILES['graphic'] ) && $_FILES['graphic']['tmp_name'] != '' ) {
					// Process the file upload
					$upload_result = self::process_order_pixels_upload();
					
					// Set up parameters for redirect back to order-pixels page
					$package           = isset( $_REQUEST['package'] ) ? intval( $_REQUEST['package'] ) : null;
					$order_id          = isset( $_REQUEST['order_id'] ) ? intval( $_REQUEST['order_id'] ) : 0;
					$params['package'] = $package;
					$params['order_id'] = $order_id;
					
					// Add upload result to parameters
					if ( ! $upload_result['success'] ) {
						$params['upload_error'] = urlencode( $upload_result['error'] );
					} else {
						$params['upload_success'] = '1';
						$order_id = isset( $_REQUEST['order_id'] ) ? intval( $_REQUEST['order_id'] ) : 0;
						$image_data = file_get_contents( $upload_result['file_path'] );
						\insert_ad_data( $order_id, false, $image_data );
					}
					
					// Redirect to order page to display results
					$mds_dest = 'order';
				} else {
					// Normal order/select flow without file upload
					$mds_dest          = 'order';
					$package           = isset( $_REQUEST['package'] ) ? intval( $_REQUEST['package'] ) : null;
					$params['package'] = $package;
					Orders::order_screen();
				}
				break;
			case 'banner_select':
				$mds_dest = 'order';
				Orders::order_screen();

				$old_order_id            = isset( $_REQUEST['old_order_id'] ) ? intval( $_REQUEST['old_order_id'] ) : 0;
				$banner_change           = isset( $_REQUEST['banner_change'] ) ? intval( $_REQUEST['banner_change'] ) : 0;
				$params['old_order_id']  = $old_order_id;
				$params['banner_change'] = $banner_change;

				break;
			case 'banner_upload':
				$mds_dest = 'upload';
				require_once MDS_CORE_PATH . 'users/upload.php';
				break;
			case 'order-pixels':
				// This case is now handled by the 'order' case above when file upload is detected
				$mds_dest = 'order';
				break;
			case 'publish':
			case 'banner_publish':
			case 'manage':
				$mds_dest = 'manage';
				require_once MDS_CORE_PATH . 'users/manage.php';
				$params['mds-action'] = 'manage';
				$params['aid']        = intval( $_REQUEST['aid'] );
				break;
			case 'upload':
				// Check if a file was just uploaded
				$upload_processed = isset( $_FILES['graphic'] ) && $_FILES['graphic']['tmp_name'] != '';
				
				global $f2;
				$BID         = $f2->bid();
				$banner_data = load_banner_constants( $BID );

				$min_size = $banner_data['G_MIN_BLOCKS'] ? intval( $banner_data['G_MIN_BLOCKS'] ) : 1;
				$max_size = $banner_data['G_MAX_BLOCKS'] ? intval( $banner_data['G_MAX_BLOCKS'] ) : intval( $banner_data['G_WIDTH'] * $banner_data['G_HEIGHT'] );

				$select                    = isset( $_REQUEST['select'] ) ? intval( $_REQUEST['select'] ) : 0;
				$package                   = isset( $_REQUEST['package'] ) ? intval( $_REQUEST['package'] ) : null;
				$order_id                  = isset( $_REQUEST['order_id'] ) ? intval( $_REQUEST['order_id'] ) : 0;
				$selection_size            = isset( $_REQUEST['selection_size'] ) && $_REQUEST['selection_size'] >= $min_size && $_REQUEST['selection_size'] <= $max_size ? intval( $_REQUEST['selection_size'] ) : $min_size;
				$params['select']          = $select;
				$params['package']         = $package;
				$params['order_id']        = $order_id;
				$params['selection_size']  = $selection_size;
				
				// If a file was uploaded, process it and redirect back to the upload page
				if ( $upload_processed ) {
					// Process the file upload
					$upload_result = self::process_order_pixels_upload();
					
					// Add upload result to parameters
					if ( ! $upload_result['success'] ) {
						$params['upload_error'] = urlencode( $upload_result['error'] );
					} else {
						$params['upload_success'] = '1';
						$order_id = isset( $_REQUEST['order_id'] ) ? intval( $_REQUEST['order_id'] ) : 0;
						$image_data = file_get_contents( $upload_result['file_path'] );
						\insert_ad_data( $order_id, false, $image_data );
					}
				}
				
				// Always redirect to the upload page
				break;
			case 'write-ad':
				require_once MDS_CORE_PATH . 'users/write_ad.php';

				$package           = isset( $_REQUEST['package'] ) ? intval( $_REQUEST['package'] ) : null;
				$params['package'] = $package;

				break;
			case 'confirm_order':
				require_once MDS_CORE_PATH . 'users/confirm_order.php';
				break;
		}

		if ( ! empty( $mds_error ) ) {
			$params['mds_error'] = urlencode( $mds_error );
		}

		// Update the current step
		Steps::update_step( $mds_dest );

		if ( isset( $_REQUEST['BID'] ) ) {
			$params['BID'] = intval( $_REQUEST['BID'] );
		}

		$page_url = Utility::get_page_url( $mds_dest );

		// Ensure we have a valid page URL
		if ( empty( $page_url ) ) {
			// Log the error for debugging
			Logs::log( 'MDS Forms: Failed to generate page URL for destination: ' . $mds_dest );

			// Try to create a basic endpoint URL as final fallback
			$endpoint = Options::get_option( 'endpoint', 'milliondollarscript' );
			if ( get_option( 'permalink_structure' ) ) {
				$page_url = trailingslashit( home_url( "/{$endpoint}/{$mds_dest}" ) );
			} else {
				$page_url = add_query_arg( $endpoint, $mds_dest, home_url( '/' ) );
			}

			// If still empty, fallback to home page
			if ( empty( $page_url ) ) {
				$page_url = home_url();
				Logs::log( 'MDS Forms: All URL generation methods failed, falling back to homepage' );
			}
		}

		// Attempt redirect with comprehensive error handling
		try {
			Utility::redirect( $page_url, $params );
		} catch ( \Exception $e ) {
			// If redirect fails, try a direct WordPress redirect
			Logs::log( 'MDS Forms: Utility::redirect failed: ' . $e->getMessage() );

			// Build URL with parameters manually
			if ( ! empty( $params ) ) {
				$page_url = add_query_arg( $params, $page_url );
			}

			// Clear any output buffers to prevent "headers already sent" errors
			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}

			// Final fallback redirect
			wp_safe_redirect( $page_url );
			exit;
		}
	}

	/**
	 * Handle specific admin forms based on the form_id parameter.
	 *
	 * @return void
	 */
	public static function mds_admin_form_submission(): void {
		// Determine the correct nonce action and name based on the submitted form
		$nonce_action = 'mds-admin'; // Default action for package management forms
		$nonce_name   = '_wpnonce';  // Default nonce field name

		// Verify the nonce using the determined action and name. This will wp_die on failure.
		check_admin_referer( $nonce_action, $nonce_name );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( Language::get( 'Sorry, you are not allowed to access this page.' ) );
		}

		if ( ! isset( $_POST['mds_dest'] ) ) {
			Utility::redirect();
		}

		$params = [];

		if ( isset( $_REQUEST['BID'] ) ) {
			$params['BID'] = intval( $_REQUEST['BID'] );
		}

		$mds_dest = $_POST['mds_dest'];

		switch ( $mds_dest ) {
			case 'manage-grids':
				require_once MDS_CORE_PATH . 'admin/manage-grids.php';
				break;
			case 'approve-pixels':
				// require_once MDS_CORE_PATH . 'admin/approve-pixels.php'; // Removed to prevent output during POST handling
				break;
			case 'edit-template':
				require_once MDS_CORE_PATH . 'admin/edit-template.php';
				break;
			case 'backgrounds':
				// Handle background image upload
				if ( isset( $_FILES['blend_image'] ) && isset( $_FILES['blend_image']['tmp_name'] ) && $_FILES['blend_image']['tmp_name'] != '' && $_FILES['blend_image']['error'] === UPLOAD_ERR_OK ) {
					$BID = isset( $_POST['BID'] ) ? intval( $_POST['BID'] ) : 0; // Get BID from POST

					// Check MIME type
					$finfo = finfo_open( FILEINFO_MIME_TYPE );
					$mime_type = finfo_file( $finfo, $_FILES['blend_image']['tmp_name'] );
					finfo_close( $finfo );

					$allowed_mime_types = [ 'image/png', 'image/jpeg', 'image/gif' ];

					if ( ! in_array( $mime_type, $allowed_mime_types ) ) {
						// Add error message? For now, just redirect.
						$params['upload_error'] = 'invalid_type'; // Changed error code
					} else {
						// Generate a safe filename using the BID and the original extension
						$extension = pathinfo($_FILES['blend_image']['name'], PATHINFO_EXTENSION);
						$new_filename = "background{$BID}." . strtolower($extension);
						$upload_path = Utility::get_upload_path() . "grids/";

						// Remove any existing background file for this grid (regardless of extension)
						$existing_files = glob($upload_path . "background{$BID}.*");
						if ($existing_files) {
							foreach ($existing_files as $existing_file) {
								unlink($existing_file);
							}
						}

						if ( move_uploaded_file( $_FILES['blend_image']['tmp_name'], $upload_path . $new_filename ) ) {
							$params['upload_success'] = 'true';
						} else {
							$params['upload_error'] = 'failed_move';
						}
					}
				} 
				// Save opacity setting if submitted (regardless of upload)
				if ( isset( $_POST['background_opacity'] ) ) {
					$BID = isset( $_POST['BID'] ) ? intval( $_POST['BID'] ) : 0;
					$opacity = intval( $_POST['background_opacity'] );
					// Clamp value between 0 and 100
					$opacity = max( 0, min( 100, $opacity ) ); 
					if ( update_option( 'mds_background_opacity_' . $BID, $opacity ) ) {
						$params['opacity_saved'] = 'true'; // Add feedback param
					} else {
						// Optional: Add error if update_option failed, though it usually returns false only if value is unchanged.
					}
				}

				// We don't need to require the file here anymore, just redirect back.
				// require_once MDS_CORE_PATH . 'admin/backgrounds.php';
				break;
			case 'clear-orders':
				require_once MDS_CORE_PATH . 'admin/clear-orders.php';
				$params['clear_orders'] = 'true';
				break;
			case 'map-of-orders':
				require_once MDS_CORE_PATH . 'admin/map-of-orders.php';
				break;
			case 'map-iframe':
				require_once MDS_CORE_PATH . 'admin/map-iframe.php';
				break;
			case 'outgoing-email':
				require_once MDS_CORE_PATH . 'admin/outgoing-email.php';
				$q_to_add            = isset( $_REQUEST['q_to_add'] ) ? sanitize_text_field( $_REQUEST['q_to_add'] ) : '';
				$q_to_name           = isset( $_REQUEST['q_to_name'] ) ? sanitize_text_field( $_REQUEST['q_to_name'] ) : '';
				$q_subj              = isset( $_REQUEST['q_subj'] ) ? sanitize_text_field( $_REQUEST['q_subj'] ) : '';
				$q_msg               = isset( $_REQUEST['q_msg'] ) ? sanitize_text_field( $_REQUEST['q_msg'] ) : '';
				$q_status            = isset( $_REQUEST['q_status'] ) ? sanitize_text_field( $_REQUEST['q_status'] ) : '';
				$q_type              = isset( $_REQUEST['q_type'] ) ? sanitize_text_field( $_REQUEST['q_type'] ) : '';
				$params['q_to_add']  = $q_to_add;
				$params['q_to_name'] = $q_to_name;
				$params['q_subj']    = $q_subj;
				$params['q_msg']     = $q_msg;
				$params['q_status']  = $q_status;
				$params['q_type']    = $q_type;
				break;
			case 'orders':
				require_once MDS_CORE_PATH . 'admin/orders.php';
				break;
			case 'orders-cancelled':
				require_once MDS_CORE_PATH . 'admin/orders-cancelled.php';
				break;
			case 'orders-completed':
				require_once MDS_CORE_PATH . 'admin/orders-completed.php';
				break;
			case 'orders-deleted':
				require_once MDS_CORE_PATH . 'admin/orders-deleted.php';
				break;
			case 'orders-expired':
				require_once MDS_CORE_PATH . 'admin/orders-expired.php';
				break;
			case 'orders-denied':
				require_once MDS_CORE_PATH . 'admin/orders-denied.php';
				break;
			case 'orders-reserved':
				require_once MDS_CORE_PATH . 'admin/orders-reserved.php';
				break;
			case 'orders-waiting':
				require_once MDS_CORE_PATH . 'admin/orders-waiting.php';
				break;
			case 'packages':
				// Ensure the specific package action nonce is valid
				$bid_for_nonce = isset($_POST['BID']) ? intval($_POST['BID']) : 0;
				check_admin_referer('mds-package-edit-' . $bid_for_nonce, 'mds_package_nonce');

				// Check if the required action is set
				if (isset($_POST['mds-action']) && $_POST['mds-action'] === 'mds-save-package') {
					global $wpdb;
					$table_name = MDS_DB_PREFIX . 'packages';

					// Sanitize and prepare data
					$package_id = isset($_POST['package_id']) ? intval($_POST['package_id']) : 0;
					$banner_id = isset($_POST['BID']) ? intval($_POST['BID']) : 0;
					$description = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : '';
					$price = isset($_POST['price']) ? sanitize_text_field($_POST['price']) : '0'; // Keep as string for number_format later if needed
					$currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : 'USD';
					$days_expire = isset($_POST['days_expire']) ? intval($_POST['days_expire']) : 0;
					$max_orders = isset($_POST['max_orders']) ? intval($_POST['max_orders']) : 0;

					// Basic validation (add more as needed)
					if (empty($description) || $banner_id <= 0 || !is_numeric($price) || $days_expire < 0 || $max_orders < 0) {
						$params['package_save_error'] = 'invalid_data';
						wp_die(Language::get('Invalid package data submitted. Please check the fields and try again.')); // Or redirect with error
					} else {
						$data = [
							'banner_id' => $banner_id,
							'description' => $description,
							'price' => $price, // Store raw numeric string
							'currency' => $currency,
							'days_expire' => $days_expire,
							'max_orders' => $max_orders,
						];
						$format = ['%d', '%s', '%s', '%s', '%d', '%d']; // Format for $wpdb->prepare

						if ($package_id > 0) {
							// Update existing package
							$where = ['package_id' => $package_id];
							$where_format = ['%d'];
							$result = $wpdb->update($table_name, $data, $where, $format, $where_format);
						} else {
							// Insert new package
							$result = $wpdb->insert($table_name, $data, $format);
						}

						if ($result === false) {
							// Database error
							$params['package_save_error'] = 'db_error';
                            Logs::log('MDS Package Save Error: ' . $wpdb->last_error);
						} elseif ($result > 0) {
							// Success (rows affected > 0)
							$params['package_save_success'] = 'true';
						} else {
							// No rows affected (update with same data)
							$params['package_save_notice'] = 'no_change';
						}
					}
				} else {
					// Handle other package actions if needed (e.g., delete)
                    $params['package_action_error'] = 'unknown_action';
				}
				// No need to require a file, the redirect below handles it.
				break;
			case 'price-zones':
				require_once MDS_CORE_PATH . 'admin/price-zones.php';
				break;
			case 'process-pixels':
				$process               = isset( $_REQUEST['process'] ) ? intval( $_REQUEST['process'] ) : 0;
				$banner_list           = isset( $_REQUEST['banner_list'] ) && is_array( $_REQUEST['banner_list'] ) ? $_REQUEST['banner_list'] : [];
				$params['process']     = '1';
				$params['banner_list'] = [];
				foreach ( $_REQUEST['banner_list'] as $key => $banner_id ) {
					$params['banner_list'][ intval( $key ) ] = ( $banner_id == 'all' ? 'all' : intval( $banner_id ) );
				}
				break;
		}

		if ( in_array( $mds_dest, [
				'orders-cancelled',
				'orders-completed',
				'orders-deleted',
				'orders-expired',
				'orders-denied',
				'orders-reserved',
				'orders-waiting'
			] ) && isset( $_REQUEST['search'] ) ) {
			// Orders
			$q_aday               = isset( $_REQUEST['q_aday'] ) ? intval( $_REQUEST['q_aday'] ) : 0;
			$q_amon               = isset( $_REQUEST['q_amon'] ) ? intval( $_REQUEST['q_amon'] ) : 0;
			$q_ayear              = isset( $_REQUEST['q_ayear'] ) ? intval( $_REQUEST['q_ayear'] ) : 0;
			$q_name               = isset( $_REQUEST['q_name'] ) ? sanitize_text_field( $_REQUEST['q_name'] ) : '';
			$q_type               = isset( $_REQUEST['q_type'] ) ? sanitize_text_field( $_REQUEST['q_type'] ) : '';
			$q_username           = isset( $_REQUEST['q_username'] ) ? sanitize_text_field( $_REQUEST['q_username'] ) : '';
			$q_email              = isset( $_REQUEST['q_email'] ) && filter_var( $_REQUEST['q_email'], FILTER_VALIDATE_EMAIL ) ? $_REQUEST['q_email'] : '';
			$search               = isset( $_REQUEST['search'] ) ? sanitize_text_field( $_REQUEST['search'] ) : '';
			$params['q_name']     = $q_name;
			$params['q_type']     = $q_type;
			$params['q_username'] = $q_username;
			$params['q_email']    = $q_email;
			$params['q_aday']     = $q_aday;
			$params['q_amon']     = $q_amon;
			$params['q_ayear']    = $q_ayear;
			$params['search']     = $search;
		} else if ( $mds_dest == 'transaction-log' ) {
			// Transaction log
			$from_year            = isset( $_REQUEST['from_year'] ) ? intval( $_REQUEST['from_year'] ) : date( "Y" );
			$from_month           = isset( $_REQUEST['from_month'] ) ? intval( $_REQUEST['from_month'] ) : date( "n" );
			$from_day             = isset( $_REQUEST['from_day'] ) ? intval( $_REQUEST['from_day'] ) : date( "j" );
			$to_year              = isset( $_REQUEST['to_year'] ) ? intval( $_REQUEST['to_year'] ) : date( "Y" );
			$to_month             = isset( $_REQUEST['to_month'] ) ? intval( $_REQUEST['to_month'] ) : date( "n" );
			$to_day               = isset( $_REQUEST['to_day'] ) ? intval( $_REQUEST['to_day'] ) : date( "j" );
			$params['from_year']  = $from_year;
			$params['from_month'] = $from_month;
			$params['from_day']   = $from_day;
			$params['to_year']    = $to_year;
			$params['to_month']   = $to_month;
			$params['to_day']     = $to_day;
		} else if ( $mds_dest == 'clicks' ) {
			// Click reports (legacy or other usage)
			$from_year            = isset( $_REQUEST['from_year'] ) ? intval( $_REQUEST['from_year'] ) : date( "Y" );
			$from_month           = isset( $_REQUEST['from_month'] ) ? intval( $_REQUEST['from_month'] ) : date( "n" );
			$from_day             = isset( $_REQUEST['from_day'] ) ? intval( $_REQUEST['from_day'] ) : date( "j" );
			$to_year              = isset( $_REQUEST['to_year'] ) ? intval( $_REQUEST['to_year'] ) : date( "Y" );
			$to_month             = isset( $_REQUEST['to_month'] ) ? intval( $_REQUEST['to_month'] ) : date( "n" );
			$to_day               = isset( $_REQUEST['to_day'] ) ? intval( $_REQUEST['to_day'] ) : date( "j" );
			$params['from_year']  = $from_year;
			$params['from_month'] = $from_month;
			$params['from_day']   = $from_day;
			$params['to_year']    = $to_year;
			$params['to_month']   = $to_month;
			$params['to_day']     = $to_day;
		} else if ( $mds_dest == 'click-reports' ) {
			// Click Reports form submission
			$from_year            = isset( $_REQUEST['from_year'] ) ? intval( $_REQUEST['from_year'] ) : date( "Y" );
			$from_month           = isset( $_REQUEST['from_month'] ) ? intval( $_REQUEST['from_month'] ) : date( "n" );
			$from_day             = isset( $_REQUEST['from_day'] ) ? intval( $_REQUEST['from_day'] ) : date( "j" );
			$to_year              = isset( $_REQUEST['to_year'] ) ? intval( $_REQUEST['to_year'] ) : date( "Y" );
			$to_month             = isset( $_REQUEST['to_month'] ) ? intval( $_REQUEST['to_month'] ) : date( "n" );
			$to_day               = isset( $_REQUEST['to_day'] ) ? intval( $_REQUEST['to_day'] ) : date( "j" );
			$params['from_year']  = $from_year;
			$params['from_month'] = $from_month;
			$params['from_day']   = $from_day;
			$params['to_year']    = $to_year;
			$params['to_month']   = $to_month;
			$params['to_day']     = $to_day;
			if ( isset( $_REQUEST['BID'] ) ) {
				$params['BID'] = intval( $_REQUEST['BID'] );
			}
		}
		// For admin form submissions, we should redirect to admin pages, not frontend pages
		$page_url = admin_url( 'admin.php?page=mds-' . $mds_dest );

		// Add any parameters to the redirect URL
		if ( ! empty( $params ) ) {
			$page_url = add_query_arg( $params, $page_url );
		}

		// Redirect the user
		wp_safe_redirect( $page_url );
		exit;

	}

	public static function display_banner_selecton_form( $BID, $order_id, $res, $mds_dest ): void {

		// validate mds_dest
		$valid_forms = [ 'order', 'select', 'publish', 'upload' ];
		if ( ! in_array( $mds_dest, $valid_forms, true ) ) {
			exit;
		}

		?>
        <form name="bidselect" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'mds-form' ); ?>
            <input type="hidden" name="action" value="mds_form_submission">
            <input type="hidden" name="mds_dest" value="banner_<?php echo $mds_dest; ?>">
            <input type="hidden" name="old_order_id" value="<?php echo intval( $order_id ); ?>">
            <input type="hidden" name="banner_change" value="1">
            <label>
                <select name="BID" style="font-size: 14px;">
					<?php
					foreach ( $res as $row ) {
						if ( $row['enabled'] == 'N' ) {
							continue;
						}
						if ( $row['banner_id'] == $BID ) {
							$sel = 'selected';
						} else {
							$sel = '';
						}
						echo '<option ' . $sel . ' value=' . $row['banner_id'] . '>' . $row['name'] . '</option>';
					}
					?>
                </select>
            </label>
            <input type="submit" name="submit" value="<?php echo esc_attr( Language::get( 'Select Grid' ) ); ?>">
        </form>
		<?php
	}

}