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

use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\Orders\Steps;
use MillionDollarScript\Classes\System\Functions;
use MillionDollarScript\Classes\System\Utility;

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
		Functions::verify_nonce( 'mds-admin' );

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

		if ( $mds_dest == 'manage-grids' ) {
			require_once MDS_CORE_PATH . 'admin/manage-grids.php';
		} else if ( $mds_dest == 'approve-pixels' ) {
			require_once MDS_CORE_PATH . 'admin/approve-pixels.php';
		} else if ( $mds_dest == 'edit-template' ) {
			require_once MDS_CORE_PATH . 'admin/edit-template.php';
		} else if ( $mds_dest == 'backgrounds' ) {
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
			// Handle background image deletion
			else if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'delete' ) {
				$BID = isset( $_REQUEST['BID'] ) ? intval( $_REQUEST['BID'] ) : 0; // Get BID from REQUEST
				$upload_path = Utility::get_upload_path() . "grids/";
				$files_to_delete = glob($upload_path . "background{$BID}.*");
				$deleted = false;
				$found = false;

				if ($files_to_delete) {
					$found = true;
					foreach ($files_to_delete as $file_to_delete) {
						if ( unlink( $file_to_delete ) ) {
							$deleted = true; // Mark as deleted if at least one succeeds
						} else {
							$params['delete_error'] = 'failed_unlink'; 
							break; // Stop if one fails
						}
					}
				}

				if ($found && $deleted && !isset($params['delete_error'])) {
					$params['delete_success'] = 'true';
					// Also delete the opacity option when image is deleted
					delete_option( 'mds_background_opacity_' . $BID );
				} else if (!$found) {
					$params['delete_error'] = 'not_found';
				}
			}

			// Save opacity setting if submitted (regardless of upload/delete)
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
		} else if ( $mds_dest == 'clear-orders' ) {
			require_once MDS_CORE_PATH . 'admin/clear-orders.php';
			$params['clear_orders'] = 'true';
			// } else if ( $mds_dest == 'clicks' ) {
			// 	require_once MDS_CORE_PATH . 'admin/clicks.php';
		} else if ( $mds_dest == 'main-config' ) {
			$params['mds-config-updated'] = 'true';
			require_once MDS_CORE_PATH . 'admin/main-config.php';
		} else if ( $mds_dest == 'map-of-orders' ) {
			require_once MDS_CORE_PATH . 'admin/map-of-orders.php';
		} else if ( $mds_dest == 'map-iframe' ) {
			require_once MDS_CORE_PATH . 'admin/map-iframe.php';
		} else if ( $mds_dest == 'outgoing-email' ) {
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
		} else if ( $mds_dest == 'orders' ) {
			require_once MDS_CORE_PATH . 'admin/orders.php';
		} else if ( $mds_dest == 'orders-cancelled' ) {
			require_once MDS_CORE_PATH . 'admin/orders-cancelled.php';
		} else if ( $mds_dest == 'orders-completed' ) {
			require_once MDS_CORE_PATH . 'admin/orders-completed.php';
		} else if ( $mds_dest == 'orders-deleted' ) {
			require_once MDS_CORE_PATH . 'admin/orders-deleted.php';
		} else if ( $mds_dest == 'orders-expired' ) {
			require_once MDS_CORE_PATH . 'admin/orders-expired.php';
		} else if ( $mds_dest == 'orders-denied' ) {
			require_once MDS_CORE_PATH . 'admin/orders-denied.php';
		} else if ( $mds_dest == 'orders-reserved' ) {
			require_once MDS_CORE_PATH . 'admin/orders-reserved.php';
		} else if ( $mds_dest == 'orders-waiting' ) {
			require_once MDS_CORE_PATH . 'admin/orders-waiting.php';
		} else if ( $mds_dest == 'packages' ) {
			require_once MDS_CORE_PATH . 'admin/packages.php';
		} else if ( $mds_dest == 'price-zones' ) {
			require_once MDS_CORE_PATH . 'admin/price-zones.php';
		} else if ( $mds_dest == 'process-pixels' ) {
			$process               = isset( $_REQUEST['process'] ) ? intval( $_REQUEST['process'] ) : 0;
			$banner_list           = isset( $_REQUEST['banner_list'] ) && is_array( $_REQUEST['banner_list'] ) ? $_REQUEST['banner_list'] : [];
			$params['process']     = '1';
			$params['banner_list'] = [];
			foreach ( $_REQUEST['banner_list'] as $key => $banner_id ) {
				$params['banner_list'][ intval( $key ) ] = ( $banner_id == 'all' ? 'all' : intval( $banner_id ) );
			}
			require_once MDS_CORE_PATH . 'admin/process-pixels.php';
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
			// Click reports
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