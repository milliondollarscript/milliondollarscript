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
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\Admin\Notices;
use MillionDollarScript\Classes\Language\Language;

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
	 * Handle specific forms based on the form_id parameter.
	 *
	 * @return void
	 */
	public static function mds_form_submission(): void {
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
				$mds_dest          = 'order';
				$package           = isset( $_REQUEST['package'] ) ? intval( $_REQUEST['package'] ) : null;
				$params['package'] = $package;
				Orders::order_screen();
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
				require_once MDS_CORE_PATH . 'users/order-pixels.php';
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

				require_once MDS_CORE_PATH . 'users/upload.php';

				global $f2;
				$BID         = $f2->bid();
				$banner_data = load_banner_constants( $BID );

				$min_size = $banner_data['G_MIN_BLOCKS'] ? intval( $banner_data['G_MIN_BLOCKS'] ) : 1;
				$max_size = $banner_data['G_MAX_BLOCKS'] ? intval( $banner_data['G_MAX_BLOCKS'] ) : intval( $banner_data['G_WIDTH'] * $banner_data['G_HEIGHT'] );

				$selected_pixels = null;
				if ( isset( $_REQUEST['selected_pixels'] ) ) {
					$selected_pixels = explode( ',', $_REQUEST['selected_pixels'] );
					$selected_array  = [];
					foreach ( $selected_pixels as $selected_pixel ) {
						$selected_array[] = intval( $selected_pixel );
					}
					$selected_pixels = implode( ',', $selected_array );
				}

				$select                    = isset( $_REQUEST['select'] ) ? intval( $_REQUEST['select'] ) : 0;
				$package                   = isset( $_REQUEST['package'] ) ? intval( $_REQUEST['package'] ) : null;
				$order_id                  = isset( $_REQUEST['order_id'] ) ? intval( $_REQUEST['order_id'] ) : 0;
				$selection_size            = isset( $_REQUEST['selection_size'] ) && $_REQUEST['selection_size'] >= $min_size && $_REQUEST['selection_size'] <= $max_size ? intval( $_REQUEST['selection_size'] ) : $min_size;
				$params['select']          = $select;
				$params['package']         = $package;
				$params['selected_pixels'] = $selected_pixels;
				$params['order_id']        = $order_id;
				$params['selection_size']  = $selection_size;

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

		Utility::redirect( $page_url, $params );
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
			case 'main-config':
				$params['mds-config-updated'] = 'true';
				require_once MDS_CORE_PATH . 'admin/main-config.php';
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
                            error_log('MDS Package Save Error: ' . $wpdb->last_error);
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
				require_once MDS_CORE_PATH . 'admin/process-pixels.php';
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
		$params['page'] = 'mds-' . $mds_dest;

		// Construct the redirect URL
		$redirect_url = admin_url( 'admin.php?page=mds-' . $mds_dest );

		// Add any parameters to the redirect URL
		if ( ! empty( $params ) ) {
			$redirect_url = add_query_arg( $params, $redirect_url );
		}

		// Redirect the user
		wp_safe_redirect( $redirect_url );
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