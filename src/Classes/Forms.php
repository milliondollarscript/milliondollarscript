<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.4
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
		if ( ! isset( $_POST['mds_dest'] ) ) {
			Utility::redirect();
		}

		$mds_dest = $_POST['mds_dest'];

		$params = '';

		switch ( $mds_dest ) {
			case 'banner_order':
			case 'order':
			case 'select':
				$mds_dest = 'order';
				\MillionDollarScript\Classes\Functions::order_screen();
				break;
			case 'banner_select':
				$mds_dest = 'order';
				\MillionDollarScript\Classes\Functions::order_screen();

				$old_order_id  = isset( $_REQUEST['old_order_id'] ) ? intval( $_REQUEST['old_order_id'] ) : 0;
				$banner_change = isset( $_REQUEST['banner_change'] ) ? intval( $_REQUEST['banner_change'] ) : 0;
				$params        .= '&old_order_id=' . $old_order_id . '&banner_change=' . $banner_change;

				break;
			case 'banner_publish':
				$mds_dest = 'publish';
				require_once MDS_CORE_PATH . 'users/publish.php';
				break;
			case 'banner_upload':
				$mds_dest = 'upload';
				require_once MDS_CORE_PATH . 'users/upload.php';
				break;
			case 'order-pixels':
				require_once MDS_CORE_PATH . 'users/order-pixels.php';
				break;
			case 'publish':
				require_once MDS_CORE_PATH . 'users/publish.php';
				break;
			case 'upload':
				\MillionDollarScript\Classes\Functions::verify_nonce( 'mds-form' );
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

				$select     = isset( $_REQUEST['select'] ) ? intval( $_REQUEST['select'] ) : 0;
				$package    = isset( $_REQUEST['package'] ) ? intval( $_REQUEST['package'] ) : null;
				$order_id   = isset( $_REQUEST['order_id'] ) ? intval( $_REQUEST['order_id'] ) : 0;
				$selection_size = isset( $_REQUEST['selection_size'] ) && $_REQUEST['selection_size'] >= $min_size && $_REQUEST['selection_size'] <= $max_size ? intval( $_REQUEST['selection_size'] ) : $min_size;
				$params     .= '&select=' . $select . '&package=' . $package . '&selected_pixels=' . $selected_pixels . '&order_id=' . $order_id . '&selection_size=' . $selection_size;

				break;
			case 'write-ad':
				require_once MDS_CORE_PATH . 'users/write_ad.php';
				break;
			case 'confirm_order':
				require_once MDS_CORE_PATH . 'users/confirm_order.php';
				break;
		}

		if ( isset( $_REQUEST['BID'] ) ) {
			Utility::redirect( Utility::get_page_url( $mds_dest ) . '?BID=' . intval( $_REQUEST['BID'] ) . $params );
		} else {
			Utility::redirect( Utility::get_page_url( $mds_dest ) . $params );
		}
	}

	/**
	 * Handle specific admin forms based on the form_id parameter.
	 *
	 * @return void
	 */
	public static function mds_admin_form_submission(): void {
		\MillionDollarScript\Classes\Functions::verify_nonce( 'mds-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( Language::get( 'Sorry, you are not allowed to access this page.' ) );
		}
		if ( ! isset( $_POST['mds_dest'] ) ) {
			Utility::redirect();
		}

		$params = '';

		if ( isset( $_REQUEST['BID'] ) ) {
			$params = '&BID=' . intval( $_REQUEST['BID'] );
		}

		$mds_dest = $_POST['mds_dest'];

		if ( $mds_dest == 'manage-grids' ) {
			require_once MDS_CORE_PATH . 'admin/manage-grids.php';
		} else if ( $mds_dest == 'list2' ) {
			require_once MDS_CORE_PATH . 'admin/list2.php';
		} else if ( $mds_dest == 'approve-pixels' ) {
			require_once MDS_CORE_PATH . 'admin/approve-pixels.php';
		} else if ( $mds_dest == 'edit-template' ) {
			require_once MDS_CORE_PATH . 'admin/edit-template.php';
		} else if ( $mds_dest == 'backgrounds' ) {
			require_once MDS_CORE_PATH . 'admin/backgrounds.php';
		} else if ( $mds_dest == 'clear-orders' ) {
			require_once MDS_CORE_PATH . 'admin/clear-orders.php';
			$params .= '&clear_orders=true';
			// } else if ( $mds_dest == 'clicks' ) {
			// 	require_once MDS_CORE_PATH . 'admin/clicks.php';
		} else if ( $mds_dest == 'main-config' ) {
			$params .= '&mds-config-updated=true';
			require_once MDS_CORE_PATH . 'admin/main-config.php';
		} else if ( $mds_dest == 'map-of-orders' ) {
			require_once MDS_CORE_PATH . 'admin/map-of-orders.php';
		} else if ( $mds_dest == 'map-iframe' ) {
			require_once MDS_CORE_PATH . 'admin/map-iframe.php';
		} else if ( $mds_dest == 'outgoing-email' ) {
			require_once MDS_CORE_PATH . 'admin/outgoing-email.php';
			$q_to_add  = isset( $_REQUEST['q_to_add'] ) ? sanitize_text_field( $_REQUEST['q_to_add'] ) : '';
			$q_to_name = isset( $_REQUEST['q_to_name'] ) ? sanitize_text_field( $_REQUEST['q_to_name'] ) : '';
			$q_subj    = isset( $_REQUEST['q_subj'] ) ? sanitize_text_field( $_REQUEST['q_subj'] ) : '';
			$q_msg     = isset( $_REQUEST['q_msg'] ) ? sanitize_text_field( $_REQUEST['q_msg'] ) : '';
			$q_status  = isset( $_REQUEST['q_status'] ) ? sanitize_text_field( $_REQUEST['q_status'] ) : '';
			$q_type    = isset( $_REQUEST['q_type'] ) ? sanitize_text_field( $_REQUEST['q_type'] ) : '';
			$params    .= '&q_to_add=' . $q_to_add . '&q_to_name=' . $q_to_name . '&q_subj=' . $q_subj . '&q_msg=' . $q_msg . '&q_status=' . $q_status . '&q_type=' . $q_type;
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
		} else if ( $mds_dest == 'orders-reserved' ) {
			require_once MDS_CORE_PATH . 'admin/orders-reserved.php';
		} else if ( $mds_dest == 'orders-waiting' ) {
			require_once MDS_CORE_PATH . 'admin/orders-waiting.php';
		} else if ( $mds_dest == 'packages' ) {
			require_once MDS_CORE_PATH . 'admin/packages.php';
		} else if ( $mds_dest == 'price-zones' ) {
			require_once MDS_CORE_PATH . 'admin/price-zones.php';
		} else if ( $mds_dest == 'process-pixels' ) {
			$process     = isset( $_REQUEST['process'] ) ? intval( $_REQUEST['process'] ) : 0;
			$banner_list = isset( $_REQUEST['banner_list'] ) && is_array( $_REQUEST['banner_list'] ) ? $_REQUEST['banner_list'] : [];
			$params      .= '&process=1';
			foreach ( $_REQUEST['banner_list'] as $key => $banner_id ) {
				$params .= '&banner_list[' . intval( $key ) . ']=' . ( $banner_id == 'all' ? 'all' : intval( $banner_id ) );
			}
			require_once MDS_CORE_PATH . 'admin/process-pixels.php';
		}

		if ( in_array( $mds_dest, [ 'orders-cancelled', 'orders-completed', 'orders-deleted', 'orders-expired', 'orders-reserved', 'orders-waiting' ] ) && isset( $_REQUEST['search'] ) ) {
			// Orders
			$q_aday     = isset( $_REQUEST['q_aday'] ) ? intval( $_REQUEST['q_aday'] ) : 0;
			$q_amon     = isset( $_REQUEST['q_amon'] ) ? intval( $_REQUEST['q_amon'] ) : 0;
			$q_ayear    = isset( $_REQUEST['q_ayear'] ) ? intval( $_REQUEST['q_ayear'] ) : 0;
			$q_name     = isset( $_REQUEST['q_name'] ) ? sanitize_text_field( $_REQUEST['q_name'] ) : '';
			$q_username = isset( $_REQUEST['q_username'] ) ? sanitize_text_field( $_REQUEST['q_username'] ) : '';
			$q_email    = isset( $_REQUEST['q_email'] ) && filter_var( $_REQUEST['q_email'], FILTER_VALIDATE_EMAIL ) ? $_REQUEST['q_email'] : '';
			$search     = isset( $_REQUEST['search'] ) ? sanitize_text_field( $_REQUEST['search'] ) : '';
			$params     = "&q_name=$q_name&q_username=$q_username&q_email=$q_email&q_aday=$q_aday&q_amon=$q_amon&q_ayear=$q_ayear&search=$search";
		} else if ( $mds_dest == 'transaction-log' ) {
			// Transaction log
			$from_year  = isset( $_REQUEST['from_year'] ) ? intval( $_REQUEST['from_year'] ) : date( "Y" );
			$from_month = isset( $_REQUEST['from_month'] ) ? intval( $_REQUEST['from_month'] ) : date( "n" );
			$from_day   = isset( $_REQUEST['from_day'] ) ? intval( $_REQUEST['from_day'] ) : date( "j" );
			$to_year    = isset( $_REQUEST['to_year'] ) ? intval( $_REQUEST['to_year'] ) : date( "Y" );
			$to_month   = isset( $_REQUEST['to_month'] ) ? intval( $_REQUEST['to_month'] ) : date( "n" );
			$to_day     = isset( $_REQUEST['to_day'] ) ? intval( $_REQUEST['to_day'] ) : date( "j" );
			$params     .= "&from_year=$from_year&from_month=$from_month&from_day=$from_day&to_year=$to_year&to_month=$to_month&to_day=$to_day";
		} else if ( $mds_dest == 'clicks' ) {
			// Click reports
			$from_year  = isset( $_REQUEST['from_year'] ) ? intval( $_REQUEST['from_year'] ) : date( "Y" );
			$from_month = isset( $_REQUEST['from_month'] ) ? intval( $_REQUEST['from_month'] ) : date( "n" );
			$from_day   = isset( $_REQUEST['from_day'] ) ? intval( $_REQUEST['from_day'] ) : date( "j" );
			$to_year    = isset( $_REQUEST['to_year'] ) ? intval( $_REQUEST['to_year'] ) : date( "Y" );
			$to_month   = isset( $_REQUEST['to_month'] ) ? intval( $_REQUEST['to_month'] ) : date( "n" );
			$to_day     = isset( $_REQUEST['to_day'] ) ? intval( $_REQUEST['to_day'] ) : date( "j" );
			$params     .= "&from_year=$from_year&from_month=$from_month&from_day=$from_day&to_year=$to_year&to_month=$to_month&to_day=$to_day";
		}

		Utility::redirect( admin_url( 'admin.php?page=mds-' . $mds_dest . $params ) );
	}

}