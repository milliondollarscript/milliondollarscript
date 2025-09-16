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

use Imagine\Filter\Basic\Autorotate;
use Imagine\Filter\Transformation;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\ManipulatorInterface;
use Imagine\Image\Metadata\ExifMetadataReader;
use MillionDollarScript\Classes\Data\Config;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Email\Emails;
use MillionDollarScript\Classes\Forms\FormFields;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Functions;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\WooCommerce\WooCommerceFunctions;

defined( 'ABSPATH' ) or exit;

require_once __DIR__ . '/lists.inc.php';

function load_ad_values( $ad_id ) {

	$prams = array();

	$ad_id = intval( $ad_id );

	$mds_pixel = get_post( $ad_id );
	if ( ! empty( $mds_pixel ) ) {

		$prams['ad_id']     = $ad_id;
		$prams['user_id']   = $mds_pixel->post_author;
		$prams['order_id']  = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'order' );
		$prams['banner_id'] = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'grid' );
		$prams['text']      = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'text' );
		$prams['url']       = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'url' );
		$prams['image']     = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'image' );

		return $prams;
	} else {
		return false;
	}
}

function assign_ad_template( $prams ) {

	$search  = [];
	$replace = [];

	// image
	$search[] = '%image%';
	$filename = 'tmp_' . $prams['ad_id'] . Utility::get_file_extension();
	if ( file_exists( Utility::get_upload_path() . 'images/' . $filename ) && ! empty( $prams['image'] ) ) {
		$max_image_size = Options::get_option( 'max-image-size', 332 );
		$replace[] = '<img alt="" src="' . Utility::get_upload_url() . "images/" . $filename . '" style="max-width:' . $max_image_size . 'px;max-height:' . $max_image_size . 'px;">';
	} else {
		$replace[] = '';
	}

	// url
	// Replace http with https
	$search[]  = 'href="http://%url%"';
	$replace[] = 'href="%url%"';
	$value     = $prams['url'];
	$url       = parse_url( $value );
	if ( empty( $url['scheme'] ) ) {
		$value = 'https://' . $value;
	}
	$value     = '<a class="mds-popup-url" href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a>';
	$search[]  = '%url%';
	$replace[] = $value;

	// text
	$search[]  = '%text%';
	$replace[] = $prams['text'];

	$search[]  = '$text$';
	$replace[] = $prams['text'];

	// Apply filter for extensions to add custom template replacements
	$replacements = array_combine( $search, $replace );
	$replacements = apply_filters( 'mds_assign_ad_template_replacements', $replacements, $prams );
	
	// Convert back to separate arrays
	$search = array_keys( $replacements );
	$replace = array_values( $replacements );

	return Language::get_replace(
		Options::get_option( 'popup-template' ),
		$search,
		$replace
	);
}

function display_ad_form( $form_id, $mode, $prams, $action = '' ) {

	global $error, $BID;

	if ( $prams == '' ) {
		$prams              = array();
		$prams['ad_id']     = ( isset( $_REQUEST['aid'] ) ? $_REQUEST['aid'] : "" );
		$prams['banner_id'] = $BID;
		$prams['user_id']   = ( isset( $_REQUEST['user_id'] ) ? $_REQUEST['user_id'] : "" );
	}

	$mode      = ( isset( $mode ) && in_array( $mode, array( "edit", "user" ) ) ) ? $mode : "";
	$ad_id     = isset( $prams['ad_id'] ) ? intval( $prams['ad_id'] ) : "";
	$user_id   = isset( $prams['user_id'] ) ? intval( $prams['user_id'] ) : "";
	$order_id  = isset( $prams['order_id'] ) ? intval( $prams['order_id'] ) : Orders::get_current_order_id();
	// Prefer the banner_id from prams, but fall back to current BID (from shortcode/endpoint) or request
	$banner_id = ( isset( $prams['banner_id'] ) && intval( $prams['banner_id'] ) > 0 )
		? intval( $prams['banner_id'] )
		: ( isset( $BID ) && intval( $BID ) > 0
			? intval( $BID )
			: ( isset( $_REQUEST['BID'] ) ? intval( $_REQUEST['BID'] ) : 0 ) );

	if ( is_admin() && $mode == "edit" ) {
		$mds_form_action = 'mds_admin_form_submission';
	} else {
		$mds_form_action = 'mds_form_submission';
	}

	?>
    <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" name="form1"
          enctype="multipart/form-data" id="mds-pixels-form">
		<?php wp_nonce_field( 'mds-form' ); ?>
        <input type="hidden" name="action" value="<?php echo $mds_form_action; ?>">
        <input type="hidden" name="mds_dest" value="write-ad">
        <input type="hidden" name="form_id" value="<?php echo absint( $form_id ); ?>">
        <input type="hidden" name="0" value="placeholder">
        <input type="hidden" name="mode" value="<?php echo $mode; ?>">
        <input type="hidden" name="aid" value="<?php echo $ad_id; ?>">
        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
        <input type="hidden" name="BID" value="<?php echo $banner_id; ?>">

        <div class="flex-container">
			<?php if ( ( $error != '' ) && ( $mode != 'edit' ) ) { ?>
                <div class="error_msg">
					<?php echo "<span class='error_msg_label'>" . Language::get( 'ERROR: Cannot save your ad for the following reasons: ' ) . "</span><br> <b>" . esc_html( $error ) . "</b>"; ?>
                </div>
			<?php } ?>
			<?php
			if ( $mode == "edit" ) {
				echo "[Ad Form]";
			}

			// section 1
			// mds_display_form( $form_id, $mode, $prams, 1 );
			$show_required = FormFields::display_fields();
			?>
            <br/>
            <div class="flex-row">
                <input type="hidden" name="save" id="save101" value="">
				<?php if ( $mode == 'edit' || $mode == 'user' ) { ?>
                    <input<?php echo( $mode == 'edit' ? ' disabled' : '' ); ?>
                            class="mds-button mds_save_ad_button form_submit_button big_button" type="submit" name="savebutton"
                            value="<?php esc_attr_e( Language::get( 'Save Ad' ) ); ?>" onclick="save101.value='1';">
				<?php } ?>
            </div>

			<?php
			if ( $show_required ) {
				echo '<span class="mds-required">' . Language::get( '* Required field' ) . '</span>';
			}
			?>

        </div>
    </form>

	<?php
}

function list_ads( $offset = 0, $user_id = '' ) {

	global $BID, $wpdb, $column_list;

	$records_per_page = 40;

	// process search result
	$additional_params = [];
	if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'search' ) {
		$additional_params['mds-action'] = 'search';
	}

	$order_by = ( isset( $_REQUEST['order_by'] ) && $_REQUEST['order_by'] ) ? $_REQUEST['order_by'] : '';

	if ( isset( $_REQUEST['ord'] ) && $_REQUEST['ord'] == 'asc' ) {
		$ord = 'ASC';
	} else if ( isset( $_REQUEST['ord'] ) && $_REQUEST['ord'] == 'desc' ) {
		$ord = 'DESC';
	} else {
		$ord = 'DESC';
	}

	if ( $order_by == null || $order_by == '' ) {
		$order    = " `ad_date` ";
		$order_by = 'ad_date';
	} else {
		$order = " `" . esc_sql( $order_by ) . "` ";
	}

	if ( ! is_numeric( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	$sql    = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id > 0 AND banner_id=%d AND user_id=%d AND (`status` IN ('paid','denied','pending','completed','expired','renew_wait','renew_paid')) ORDER BY %s %s", $BID, $user_id, $order, $ord );
	$result = $wpdb->get_results( $sql, ARRAY_A );

	$count = count( $result );

	if ( $count > $records_per_page ) {
		$result = array_slice( $result, $offset, $records_per_page );
	}

	if ( $count > 0 ) {

		if ( $count > $records_per_page ) {

			$cur_page = intval( $offset ) / $records_per_page;
			$cur_page ++;

			echo Functions::generate_navigation( $cur_page, $count, $records_per_page, $additional_params, Utility::get_page_url( 'manage' ), $order_by );
		}

		$order_locking = Options::get_option( 'order-locking', 'no' ) == 'yes';

		?>
        <div class="mds-pixels-list">
            <div class="mds-pixels-list-heading">
                <div>&nbsp;</div>
                <div><?php Language::out( 'Pixels' ); ?></div>
                <div><?php Language::out( 'Expires' ); ?></div>
                <div><?php Language::out( 'Status' ); ?></div>
            </div>

			<?php
			$i = 0;
			global $prams;
			foreach ( $result as $prams ) {

				if ( $i >= $records_per_page ) {
					break;
				}
				$i ++;

				?>
                <div class="mds-pixels-list-row">
                    <div>
						<?php
						$order_status = Orders::get_completion_status( $prams['order_id'], $user_id );
						if ( ! $order_locking || ( $prams['status'] == 'denied' && $order_status ) ) {

							?>
                            <input class="mds-button" type="button" value="<?php esc_attr_e( Language::get( 'Edit' ) ); ?>"
                                   onclick="window.location='<?php echo esc_url( Utility::get_page_url( 'manage', [ 'mds-action' => 'manage', 'aid' => $prams['ad_id'], 'json' => 1 ] ) ); ?>'">
							<?php
						}
						?>
                    </div>
					<?php
					$column_list = [];
					echo_ad_list_data();

					?>
                    <div>
                        <img src="<?php echo esc_url( Utility::get_page_url( 'get-order-image', [ 'BID' => $BID, 'aid' => $prams['ad_id'] ] ) ); ?>"
                             alt=""></div>
                    <div>
						<?php
						if ( $prams['days_expire'] > 0 ) {

							if ( $prams['published'] != 'Y' ) {
								$time_start = current_time( 'timestamp' );
							} else {
								$time_start = strtotime( $prams['date_published'] );
							}

							$elapsed_time = current_time( 'timestamp' ) - $time_start;

							$exp_time = ( $prams['days_expire'] * 24 * 60 * 60 );

							$exp_time_to_go = $exp_time - $elapsed_time;

							$to_go = Utility::elapsedtime( $exp_time_to_go );

							$elapsed = Utility::elapsedtime( $elapsed_time );

							if ( $prams['status'] == 'expired' ) {
								$days = "<a href='" . esc_url( Utility::get_page_url( 'payment', [ 'order_id' => $prams['order_id'], 'BID' => $prams['banner_id'], 'renew' => 'true' ] ) ) . "'>" . Language::get( 'Expired!' ) . "</a>";
							} else if ( $prams['date_published'] == '' ) {
								$days = Language::get( 'Not Yet Published' );
							} else {
								$days = Language::get_replace(
									'%ELAPSED% elapsed<br> %TO_GO% to go',
									[ '%ELAPSED%', '%TO_GO%' ],
									[ $elapsed, $to_go ]
								);
							}
						} else {

							$days = Language::get( 'Never' );
						}
						echo $days;
						?>
                    </div>
					<?php
					if ( $prams['published'] == 'Y' ) {
						$pub = Language::get( 'Published' );
					} else {
						$pub = Language::get( 'Not Published' );
					}
					if ( $prams['approved'] == 'Y' ) {
						$app = Language::get( 'Approved' ) . ', ';
					} else {
						$app = Language::get( 'Not Approved' ) . ', ';
					}
					?>
                    <div>
						<?php
						switch ( $prams['status'] ) {
							case 'pending':
								Language::out( 'Pending' );
								break;
							case 'completed':
								Language::out( 'Completed' );
								break;
							case 'cancelled':
								Language::out( 'Cancelled' );
								break;
							case 'confirmed':
								Language::out( 'Confirmed' );
								break;
							case 'new':
								Language::out( 'New' );
								break;
							case 'expired':
								Language::out( 'Expired' );
								break;
							case 'denied':
								Language::out( 'Denied' );
								break;
							case 'deleted':
								Language::out( 'Deleted' );
								break;
							case 'renew_wait':
								Language::out( 'Renew Now' );
								break;
							case 'renew_paid':
								Language::out( 'Renewal Paid' );
								break;
							case 'paid':
								Language::out( 'Paid' );
								break;
							default:
								break;
						}
						echo "<br />";

						echo $app . $pub;
						?>
                    </div>
					<?php

					?>

                </div>
				<?php
			}
			?>
        </div>
		<?php
	} else {
		echo '<div style="text-align: center;"><b>' . Language::get( 'No ads were found.' ) . '</b></div>';
	}

	return $count;
}

function manage_list_ads( $offset = 0, $user_id = '' ) {

	global $BID, $wpdb, $column_list;

	$records_per_page = 40;

	// process search result
	$additional_params = [];
	if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'search' ) {
		$additional_params['mds-action'] = 'search';
	}

	$order_by = ( isset( $_REQUEST['order_by'] ) && $_REQUEST['order_by'] ) ? $_REQUEST['order_by'] : '';

	if ( isset( $_REQUEST['ord'] ) && $_REQUEST['ord'] == 'asc' ) {
		$ord = 'ASC';
	} else if ( isset( $_REQUEST['ord'] ) && $_REQUEST['ord'] == 'desc' ) {
		$ord = 'DESC';
	} else {
		$ord = 'DESC';
	}

	if ( $order_by == null || $order_by == '' ) {
		$order    = " `ad_date` ";
		$order_by = 'ad_date';
	} else {
		$order = " `" . esc_sql( $order_by ) . "` ";
	}

	if ( ! is_numeric( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	$sql    = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id > 0 AND banner_id=%d AND user_id=%d AND `status` NOT IN ('deleted', 'cancelled') ORDER BY %s %s", $BID, $user_id, $order, $ord );
	$result = $wpdb->get_results( $sql, ARRAY_A );

	$count = count( $result );

	if ( $count > $records_per_page ) {
		$result = array_slice( $result, $offset, $records_per_page );
	}

	if ( $count > 0 ) {

		if ( $count > $records_per_page ) {

			$cur_page = intval( $offset ) / $records_per_page;
			$cur_page ++;

			echo Functions::generate_navigation( $cur_page, $count, $records_per_page, $additional_params, Utility::get_page_url( 'manage' ), $order_by );
		}

		$order_locking = Options::get_option( 'order-locking', 'no' ) == 'yes';

		?>
        <div class="mds-pixels-list">
			<?php
			$i = 0;
			global $prams;
			foreach ( $result as $prams ) {

				if ( $i >= $records_per_page ) {
					break;
				}
				$i ++;

				?>
                <div class="mds-manage-row">
                    <div>
						<?php
						$order_status = Orders::get_completion_status( $prams['order_id'], $user_id );
						if ( $prams['status'] == 'confirmed' && !Orders::has_payment( $prams['order_id'] ) && ( Orders::is_order_in_progress( $prams['order_id'] ) || WooCommerceFunctions::is_wc_active() ) ) {
							// Show Pay Now button for confirmed orders that haven't been paid yet
							$pay_args = [ 'order_id' => $prams['order_id'], 'BID' => $prams['banner_id'] ];
							$pay_url = esc_url( Utility::get_page_url( 'payment', $pay_args ) );
							?>
                            <input class="mds-button mds-pay" type="button"
                                   value="<?php esc_attr_e( Language::get( 'Pay Now' ) ); ?>"
                                   onclick="window.location='<?php echo $pay_url; ?>'">
							<?php
						} else if ( $prams['status'] == 'new' && ( ! $order_locking || ( $prams['status'] == 'denied' && $order_status ) ) ) {
							// Show appropriate action buttons for new orders based on current step
							echo Orders::get_header_action_buttons( $prams );
						} else if ( $prams['status'] != 'confirmed' && $prams['status'] != 'new' && ( ! $order_locking || ( $prams['status'] == 'denied' && $order_status ) ) ) {
							// Show Edit button for other non-confirmed orders (expired, denied, etc.)
							?>
                            <input class="mds-button mds-edit-button" type="button"
                                   value="<?php esc_attr_e( Language::get( 'Edit' ) ); ?>"
                                   data-order-id="<?php echo $prams['order_id']; ?>"
                                   data-pixel-id="<?php echo $prams['ad_id']; ?>">
							<?php
						}
						?>
                    </div>
					<?php
					$column_list = [];
					echo_ad_list_data();

					?>
                    <div>
                        <img src="<?php echo esc_url( Utility::get_page_url( 'get-order-image', [ 'BID' => $BID, 'aid' => $prams['ad_id'] ] ) ); ?>" alt=""/>
                    </div>
					<?php
					if ( $prams['published'] == 'Y' ) {
						$pub = 'Published';
					} else {
						$pub = 'Not Published';
					}
					$pub_lang = Language::get( $pub );
					if ( $prams['approved'] == 'Y' ) {
						$app = 'Approved';
					} else {
						$app = 'Not Approved';
					}
					$app_lang = Language::get( $app );

					$status       = str_replace( '_', ' ', ucwords( $prams['status'] ) );
					$status_lang  = Language::get( $status );
					$status_color = Utility::get_order_color( [
						$pub == 'Published',
						$app == 'Approved',
						$status == 'Completed'
					],
						0.9
					);

					?>
                    <?php
                    // Allow extensions to add content to the order header (like platform info)
                    do_action( 'mds_manage_order_header_after', $prams, $user_id );
                    ?>
                    <div class="mds-status-container">
                        <div class='mds-status-indicator' title="<?php echo Language::get( "Status: " ) . esc_html( $pub_lang ) . ', ' . esc_html( $app_lang ) . ', ' . esc_html( $status_lang ); ?>"
                             style='background-color: <?php echo $status_color; ?>'></div>
                    </div>
                </div>
                <div class="mds-pixel-info">
                    <b><?php Language::out( "Status: " ); ?></b><br/>
					<?php echo esc_html( $pub_lang ); ?>, <?php echo esc_html( $app_lang ); ?>, <?php echo esc_html( $status_lang ); ?>

                    <br/>
					<?php Orders::order_details( $prams ); ?>
					<?php
					// Action for adding custom order details
					do_action( 'mds_order_details', $prams );
					
					// Allow extensions to add additional content to order details
					do_action( 'mds_manage_order_content_after', $prams, $user_id );
					?>
                </div>
				<?php
			}
			?>
        </div>
		<?php
	} else {
		echo '<div style="text-align: center;"><b>' . Language::get( 'No ads were found.' ) . '</b></div>';
	}

	return $count;
}

function insert_ad_data( $order_id = 0, $admin = false, $image_data = '' ) {
	global $wpdb;

	$sql          = "SELECT ad_id FROM `" . MDS_DB_PREFIX . "orders` WHERE order_id=%d";
	$prepared_sql = $wpdb->prepare( $sql, intval( $order_id ) );
	$ad_id        = $wpdb->get_var( $prepared_sql );

	if ( empty( $ad_id ) && empty( $_REQUEST['aid'] ) ) {
		$current_user = wp_get_current_user();

		// Insert a new mds-pixel post
		$ad_id = wp_insert_post( [
			'post_title'  => $current_user->user_login,
			'post_status' => 'new',
			'post_type'   => FormFields::$post_type
		] );

		// Update ad id on order
		$sql          = "UPDATE " . MDS_DB_PREFIX . "orders SET ad_id=%d WHERE user_id=%d AND status='new' AND order_id=%d;";
		$prepared_sql = $wpdb->prepare( $sql, $ad_id, $current_user->ID, $order_id );
		$wpdb->query( $prepared_sql );
	} else {

		if ( ! $admin ) {
			// make sure that the logged-in user is the owner of this ad.

			if ( ! is_user_logged_in() ) {
				return false;
			} else {
				// user is logged in
				// Check if the user has an MDS Pixel post.
				$post = FormFields::get_pixel_from_order_id( $order_id );

				if ( ! empty( $post ) ) {
					// The user already has a new post of the given post type, so an order must be in progress
					$ad_id = $post->ID;
				}
			}
		} else {
			$ad_id = intval( $_REQUEST['aid'] ?? $ad_id );
		}
	}

	if ( ! empty( $order_id ) && ! empty( $ad_id ) ) {

		// update blocks with ad data
		$sql          = "SELECT blocks, banner_id FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%d";
		$prepared_sql = $wpdb->prepare( $sql, intval( $order_id ) );
		$order_row    = $wpdb->get_row( $prepared_sql, ARRAY_A );

		if ( isset( $order_row ) ) {
			$blocks = $order_row['blocks'];

			$text          = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'text' );
			$url           = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'url' );
			$attachment_id = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'image' );
			$image_file         = get_attached_file( $attachment_id );

			// If we have image data, process and slice it
			if ( ! empty( $image_data ) ) {
				$imagine = new \Imagine\Gd\Imagine();
				$image   = $imagine->load( $image_data );

				// Get the dimensions of the selected area
				$size = Utility::get_pixel_image_size( $order_id );

				// Get the banner data
				$banner_data = load_banner_constants( $order_row['banner_id'] );

				// Get the block dimensions
				$blkW = $banner_data['BLK_WIDTH'];
				$blkH = $banner_data['BLK_HEIGHT'];

				$target_selection_pixel_width = $size['x'];
				$target_selection_pixel_height = $size['y'];

				$actualWidth = $image->getSize()->getWidth();
				$actualHeight = $image->getSize()->getHeight();

				$MDS_RESIZE = Options::get_option( 'resize', 'YES' );

				if ( $MDS_RESIZE == 'YES' ) {
					// Global resize is ON: Resize (thumbnail) the uploaded image to fit the selection's pixel area.
					// Validate that target dimensions are positive before attempting resize.
					if ($target_selection_pixel_width > 0 && $target_selection_pixel_height > 0) {
						try {
							$resize_box = new Box( $target_selection_pixel_width, $target_selection_pixel_height );
							$image = $image->resize($resize_box);
						} catch (\Exception $e) {
							throw new \Exception('Failed to thumbnail image to fit selection: ' . $e->getMessage());
						}
					}
				} else {
					// Global resize is OFF: Validate that the uploaded image isn't larger than the selection's pixel area.
					// If it is, an error is thrown. Otherwise, the original uploaded image (possibly already converted to PNG) is used.
					if ( $actualWidth > $target_selection_pixel_width || $actualHeight > $target_selection_pixel_height ) {
						$error_message = Language::get_replace(
							'Image too large. You uploaded an image that is %ACTUAL_W%×%ACTUAL_H% pixels, exceeding the selected area of %TARGET_W%×%TARGET_H% pixels.',
							[ '%ACTUAL_W%', '%ACTUAL_H%', '%TARGET_W%', '%TARGET_H%' ],
							[ $actualWidth, $actualHeight, $target_selection_pixel_width, $target_selection_pixel_height ]
						);
						throw new \Exception($error_message);
					}
				}

				// Precompute block dimensions for performance
				if ( $MDS_RESIZE == 'YES' && $size['x'] <= $target_selection_pixel_width && $size['y'] <= $target_selection_pixel_height) {
					$ratio_diff_x = $size['x'] / $target_selection_pixel_width;
					$ratio_diff_y = $size['y'] / $target_selection_pixel_height;
					$blkW         = $banner_data['BLK_WIDTH'] * $ratio_diff_x;
					$blkH         = $banner_data['BLK_HEIGHT'] * $ratio_diff_y;
				} else {
					$blkW         = $banner_data['BLK_WIDTH'];
					$blkH         = $banner_data['BLK_HEIGHT'];
				}

				// Get the block offsets
				$block_offsets = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT block_id, x, y FROM " . MDS_DB_PREFIX . "blocks WHERE order_id=%d AND banner_id=%d",
						$order_id,
						$order_row['banner_id']
					),
					ARRAY_A
				);

				$low_x = $low_y = 0;
				if ( ! empty( $block_offsets ) ) {
					$low_x = $high_x = $block_offsets[0]['x'];
					$low_y = $high_y = $block_offsets[0]['y'];
					foreach ( $block_offsets as $block_row_offset ) {
						if ( $block_row_offset['x'] > $high_x ) {
							$high_x  = $block_row_offset['x'];
						}
						if ( $block_row_offset['x'] < $low_x ) {
							$low_x   = $block_row_offset['x'];
						}
						if ( $block_row_offset['y'] > $high_y ) {
							$high_y  = $block_row_offset['y'];
						}
						if ( $block_row_offset['y'] < $low_y ) {
							$low_y   = $block_row_offset['y'];
						}
					}
				}

				// Create a map of block_id to x,y coordinates
				$block_map = [];
				foreach ( $block_offsets as $block_offset ) {
					$block_map[ $block_offset['block_id'] ] = [
						'x' => $block_offset['x'],
						'y' => $block_offset['y'],
					];
				}

				$block_size   = new Box( $blkW, $blkH );

				// Iterate over the selected blocks and slice the image
				$blocks_str = $wpdb->get_var( $wpdb->prepare( "SELECT blocks FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%d", $order_id ) );
				$selected_blocks = !empty($blocks_str) ? explode( ',', $blocks_str ) : [];
				foreach ( $selected_blocks as $block_id ) {
					// Get the x,y coordinates for this block
					$x = $block_map[ $block_id ]['x'] - $low_x;
					$y = $block_map[ $block_id ]['y'] - $low_y;

					// Crop the image to get the portion for this block
					$block_image = $image->copy()->crop( new Point( $x, $y ), $block_size );

					// Encode the block image data
					$encoded_image_data = base64_encode( $block_image->get( 'png' ) );

					// Update the image_data for this block
					$wpdb->update(
						MDS_DB_PREFIX . 'blocks',
						[ 'image_data' => $encoded_image_data ],
						[ 'block_id' => $block_id, 'banner_id' => $order_row['banner_id'] ],
						[ '%s' ],
						[ '%d', '%d' ]
					);
				}
			}

			$sql          = "UPDATE " . MDS_DB_PREFIX . "blocks SET file_name=%s, url=%s, alt_text=%s, ad_id=%d WHERE FIND_IN_SET(block_id, %s) AND banner_id=%d;";
			$prepared_sql = $wpdb->prepare( $sql, $image_file, $url, $text, $ad_id, $blocks, $order_row['banner_id'] );
			$wpdb->query( $prepared_sql );

			// Update the last modification time
			Orders::set_last_order_modification_time();
		}
	}

	return $ad_id;
}

function disapprove_modified_order( $order_id, $BID ) {

	// Check if order exists first
	$order = Orders::get_order( $order_id );
	if ( empty( $order ) ) {
		return;
	}

	// disapprove the order
	global $wpdb;

	$wpdb->update(
		MDS_DB_PREFIX . "orders",
		[ 'approved' => 'N' ],
		[ 'order_id' => $order_id, 'banner_id' => $BID ],
		[ '%s' ],
		[ '%d', '%d' ]
	);

	$wpdb->update(
		MDS_DB_PREFIX . "blocks",
		[ 'approved' => 'N' ],
		[ 'order_id' => $order_id, 'banner_id' => $BID ],
		[ '%s' ],
		[ '%d', '%d' ]
	);

	// send pixel change notification
	if ( Options::get_option( 'email-admin-publish-notify' ) == 'YES' ) {
		Emails::send_published_pixels_notification( $order->user_id, $order_id );
	}
}

/**
 * Upload the changed pixels.
 *
 * @param $order_id int
 * @param $BID int
 * @param $size array
 * @param $banner_data array
 * @param $file_key string The key in the $_FILES array for the uploaded file (defaults to 'pixels').
 *
 * @return string|bool Returns true on success, or an error message string on failure.
 */
function upload_changed_pixels(
	int    $order_id,
	string $BID,
	array  $size,
	array  $banner_data,
	string $file_key = 'pixels'
): string|bool {
	global $f2;

	// Basic validation: Check if the file key exists in $_FILES
	if ( ! isset( $_FILES[ $file_key ] ) ) {
		return Language::get_replace( 'Invalid file key specified: %KEY%', '%KEY%', $file_key );
	}
	$files = $_FILES[ $file_key ];

	// Check for PHP upload errors first
	$upload_error = $files['error'];
	if ( $upload_error !== UPLOAD_ERR_OK ) {
		// is_uploaded_file failed OR there was an upload error
		// Provide more specific feedback
		$error_message = Language::get('Invalid upload method or upload error.');
		if ($upload_error !== UPLOAD_ERR_OK) {
			$error_message .= ' ' . Language::get_replace('PHP Upload Error Code: %CODE%', '%CODE%', $upload_error);
		}
		throw new \Exception($error_message); // Throw exception instead of returning early
	}

	$imagine = new Imagine();
	try {
		// Validate file existence and readability
		if (!file_exists($files['tmp_name'])) {
			throw new \Exception('Uploaded file does not exist: ' . $files['tmp_name']);
		}

		if (!is_readable($files['tmp_name'])) {
			throw new \Exception('Cannot read uploaded file: ' . $files['tmp_name']);
		}

		// Use comprehensive FileValidator for security
		$validation_result = \MillionDollarScript\Classes\System\FileValidator::validate_image_upload( $files );
		if ( ! $validation_result['valid'] ) {
			// Log security violation attempt
			\MillionDollarScript\Classes\System\Logs::log( 'MDS Security: File upload validation failed in ads.inc.php - ' . $validation_result['error'] . ' - User: ' . get_current_user_id() . ' - File: ' . ( $files['name'] ?? 'unknown' ) );
			throw new \Exception( "<b>" . Language::get( 'File upload failed.' ) . "</b><br />" . esc_html( $validation_result['error'] ) );
		}

		// Generate secure filename and get upload directory
		$uploaddir = Utility::get_upload_path() . "images/";
		$secure_filename = \MillionDollarScript\Classes\System\FileValidator::generate_secure_filename( $files['name'], "tmp_" . $order_id . "_" );
		$uploadfile = $uploaddir . $secure_filename;

		// Ensure upload directory exists and is secure
		if ( ! file_exists( $uploaddir ) ) {
			wp_mkdir_p( $uploaddir );
		}

		// Move uploaded file using WordPress-compliant methods
		if ( ! move_uploaded_file( $files['tmp_name'], $uploadfile ) ) {
			\MillionDollarScript\Classes\System\Logs::log( 'MDS Security: File move failed in ads.inc.php - User: ' . get_current_user_id() . ' - Target: ' . $uploadfile );
			throw new \Exception( Language::get( 'Possible file upload attack or server error during file move.' ) );
		}

		// Log successful upload for security auditing
		\MillionDollarScript\Classes\System\Logs::log( 'MDS Security: Secure file upload completed in ads.inc.php - User: ' . get_current_user_id() . ' - File: ' . $secure_filename );

		// convert to png
		try {
			$image    = $imagine->open( $uploadfile );
			$fileinfo = pathinfo( $uploadfile );
			$newname  = ( $fileinfo['dirname'] ? $fileinfo['dirname'] . DIRECTORY_SEPARATOR : '' ) . $fileinfo['filename'] . '.png';
			$image->save( $newname );
		} catch (\Exception $e) {
			throw new \Exception('Failed to convert image: ' . $e->getMessage());
		}

		// File is valid, and was successfully uploaded.
		$tmp_image_file = $newname;

		Utility::setMemoryLimit( $uploadfile );

		// autorotate
		$imagine->setMetadataReader( new ExifMetadataReader() );
		$filter = new Transformation();
		$filter->add( new AutoRotate() );
		$filter->apply( $image );

		// Determine actual image size from the *converted* PNG file
		$actual_size = getimagesize($newname);
		if ($actual_size === false) {
			// If getimagesize fails on the converted file, something is wrong with it
			throw new \Exception(Language::get( 'Could not read image dimensions after conversion. File might be corrupted.' ));
		}

		// Now proceed with dimension and type checks
		$actualWidth  = $actual_size[0];
		$actualHeight = $actual_size[1];

		// Get the pixel image size
		$pixel_image_size = Utility::get_pixel_image_size($order_id);

		// Calculate the target pixel dimensions of the selected area based on grid selection
		// $pixel_image_size['x'] and $pixel_image_size['y'] are the selection width and height in *pixels*
		$target_selection_pixel_width  = $pixel_image_size['x'];
		$target_selection_pixel_height = $pixel_image_size['y'];

		// Get the global resize setting
		$MDS_RESIZE = Options::get_option( 'resize', 'YES' );

		if ( $MDS_RESIZE == 'YES' ) {
			// Global resize is ON: Resize (thumbnail) the uploaded image to fit the selection's pixel area.
			// Validate that target dimensions are positive before attempting resize.
			if ($target_selection_pixel_width > 0 && $target_selection_pixel_height > 0) {
				try {
					$resize_box = new Box( $target_selection_pixel_width, $target_selection_pixel_height );

					// Using THUMBNAIL_INSET preserves aspect ratio and fits within bounds.
					// The $image object is updated by the thumbnail operation.
					// $image = $image->thumbnail($resize_box, ManipulatorInterface::THUMBNAIL_INSET);
					$image = $image->resize($resize_box);
				} catch (\Exception $e) {
					throw new \Exception('Failed to thumbnail image to fit selection: ' . $e->getMessage());
				}
			}
		} else {
			// Global resize is OFF: Validate that the uploaded image isn't larger than the selection's pixel area.
			// If it is, an error is thrown. Otherwise, the original uploaded image (possibly already converted to PNG) is used.
			if ( $actualWidth > $target_selection_pixel_width || $actualHeight > $target_selection_pixel_height ) {
				$error_message = Language::get_replace(
					'Image too large. You uploaded an image that is %ACTUAL_W%×%ACTUAL_H% pixels, exceeding the selected area of %TARGET_W%×%TARGET_H% pixels.',
					[ '%ACTUAL_W%', '%ACTUAL_H%', '%TARGET_W%', '%TARGET_H%' ],
					[ $actualWidth, $actualHeight, $target_selection_pixel_width, $target_selection_pixel_height ]
				);
				throw new \Exception($error_message);
			}
		}

		// Precompute block dimensions for performance
		if ( $MDS_RESIZE == 'YES' && $size['x'] <= $target_selection_pixel_width && $size['y'] <= $target_selection_pixel_height) {
			$ratio_diff_x = $size['x'] / $target_selection_pixel_width;
			$ratio_diff_y = $size['y'] / $target_selection_pixel_height;
			$blkW         = $banner_data['BLK_WIDTH'] * $ratio_diff_x;
			$blkH         = $banner_data['BLK_HEIGHT'] * $ratio_diff_y;
		} else {
			$blkW         = $banner_data['BLK_WIDTH'];
			$blkH         = $banner_data['BLK_HEIGHT'];
		}

		// Precompute  block dimensions and color for performance
		$block_size   = new Box( $blkW, $blkH );
		$palette    = new \Imagine\Image\Palette\RGB();
		$color      = $palette->color( '#000', 0 );

		// Compute block offsets from database
		global $wpdb;
		$blocks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT x, y FROM " . MDS_DB_PREFIX . "blocks WHERE order_id=%d AND banner_id=%d",
				$order_id,
				$BID
			),
			ARRAY_A
		);
		if ( ! empty( $blocks ) ) {
			$low_x = $high_x = $blocks[0]['x'];
			$low_y = $high_y = $blocks[0]['y'];
			foreach ( $blocks as $block_row ) {
				if ( $block_row['x'] > $high_x ) {
					$high_x  = $block_row['x'];
				}
				if ( $block_row['x'] < $low_x ) {
					$low_x   = $block_row['x'];
				}
				if ( $block_row['y'] > $high_y ) {
					$high_y  = $block_row['y'];
				}
				if ( $block_row['y'] < $low_y ) {
					$low_y   = $block_row['y'];
				}
			}
			$_REQUEST['map_x'] = $high_x;
			$_REQUEST['map_y'] = $high_y;
		} else {
			$low_x = $low_y = 0;
		}

		// Prepare for batch updates
		$updates = [];

		// Create a fully transparent block placeholder
		$transparent_color       = $palette->color('#FFF', 100); // Use any color, alpha 100 = fully transparent
		$transparent_block_image = $imagine->create($block_size, $transparent_color);
		$transparent_image_data  = base64_encode($transparent_block_image->get("png", ['png_compression_level' => 9]));

		// Iterate over the entire selection area, not just the uploaded image area
		$blocksPerRow = $banner_data['G_WIDTH'];
		$selectionWidth  = $pixel_image_size['x'];
		$selectionHeight = $pixel_image_size['y'];

		// Use actual image dimensions for loop limits
		$loopLimitX = $actualWidth;
		$loopLimitY = $actualHeight;

		// Calculate the total width and height of the block selection area
		for ( $y_offset = 0; $y_offset < $selectionHeight; $y_offset += $blkH ) {
			for ( $x_offset = 0; $x_offset < $selectionWidth; $x_offset += $blkW ) {
				// Calculate absolute map coordinates for this block in the grid
				$map_x = $low_x + $x_offset;
				$map_y = $low_y + $y_offset;

				// Calculate the expected block ID based on grid position
				$cb = ($map_x / $blkW) + (($map_y / $blkH) * $blocksPerRow);

				// Determine the relative coordinates within the uploaded image frame
				$img_x = $x_offset;
				$img_y = $y_offset;

				// Check if this block position is covered by the actual uploaded image
				if ( (
					$img_x < $actualWidth || 
					($MDS_RESIZE == 'YES' && $actualWidth <= $target_selection_pixel_width)
				) && (
					$img_y < $actualHeight || 
					($MDS_RESIZE == 'YES' && $actualHeight <= $target_selection_pixel_height)
				) ) {
					// --- This block IS covered by the uploaded image --- 

					// Calculate the size for this specific crop from the uploaded image
					if($MDS_RESIZE == 'YES' && $actualWidth <= $target_selection_pixel_width){
						$cropWidth  = $blkW;
					}else{
						$cropWidth  = min( $blkW, $actualWidth - $img_x );
					}
					if($MDS_RESIZE == 'YES' && $actualHeight <= $target_selection_pixel_height) {
							$cropHeight = $blkH;
					}else{
						$cropHeight = min( $blkH, $actualHeight - $img_y );
					}

					// Ensure dimensions are positive before attempting crop
					if ($cropWidth > 0 && $cropHeight > 0) {
						try {
							$processed_block_image = $image->copy(); 
							$processed_block_image->crop(new Point($img_x, $img_y), new Box($cropWidth, $cropHeight));
				
							if (Options::get_option('resize') == 'YES') {
								// Global Auto-resize IS ON:
								// Resize the cropped segment to fill the standard block dimensions.
								$processed_block_image = $processed_block_image->resize($block_size);
								
								// Ensure the resized image is *exactly* the standard block size.
								// If not, create a new blank canvas (transparent by default) of the standard size 
								// and paste the resized image onto it.
								if ($processed_block_image->getSize()->getWidth() !== $blkW || $processed_block_image->getSize()->getHeight() !== $blkH) {
									$perfect_size_canvas = $imagine->create($block_size); 
									$perfect_size_canvas->paste($processed_block_image, new Point(0, 0));
									$processed_block_image = $perfect_size_canvas; 
								}
							} else {
								// Global Auto-resize IS OFF:
								// The $processed_block_image is currently the (potentially smaller) cropped segment.
								// It needs to be placed on a standard-sized canvas which has the default $color background.
								$standard_canvas_with_bg = $imagine->create($block_size, $color); 
								$standard_canvas_with_bg->paste($processed_block_image, new Point(0,0)); // Paste at top-left
								$processed_block_image = $standard_canvas_with_bg; 
							}
				
							// Get the image data from the now correctly processed and sized block image.
							$current_image_data = base64_encode( $processed_block_image->get( "png", array( 'png_compression_level' => 9 ) ) );
							
						} catch (\Exception $e) {
							throw new \Exception('Failed to process block: ' . $e->getMessage());
						}
					} else {
						// This case might occur if crop dimensions are zero (edge case), use transparent
						$current_image_data = $transparent_image_data;
					}
				} else {
					// --- This block IS NOT covered by the uploaded image, make it transparent ---
					$current_image_data = $transparent_image_data;
				}
				
				// Queue update for this block ID
				$updates[] = [ 'block_id' => intval($cb), 'image_data' => $current_image_data ];

			}
		}

		// apply updates in batch
		if ( ! empty( $updates ) ) {
			$cases = '';
			$ids   = [];
			foreach ( $updates as $u ) {
				$cases .= $wpdb->prepare( "WHEN block_id=%d THEN %s ", $u['block_id'], $u['image_data'] );
				$ids[] = $u['block_id'];
			}
			$ids_list = implode( ',', $ids );
			$sql = "UPDATE " . MDS_DB_PREFIX . "blocks
					SET image_data = CASE {$cases} END
					WHERE order_id=%d
					  AND banner_id=%d
					  AND block_id IN ({$ids_list})";
			$wpdb->query( $wpdb->prepare( $sql, $order_id, $BID ) );
		}

		unset( $tmp_image_file );

		if ( $banner_data['AUTO_APPROVE'] != 'Y' ) {
			// to be approved by the admin
			disapprove_modified_order( $order_id, $BID );
		} else {
			// Auto-approved - mark blocks as approved
			global $wpdb;
			$wpdb->update(
				MDS_DB_PREFIX . "orders",
				[ 'approved' => 'Y' ],
				[ 'order_id' => $order_id, 'banner_id' => $BID ],
				[ '%s' ],
				[ '%d', '%d' ]
			);
			
			$wpdb->update(
				MDS_DB_PREFIX . "blocks",
				[ 'approved' => 'Y' ],
				[ 'order_id' => $order_id, 'banner_id' => $BID ],
				[ '%s' ],
				[ '%d', '%d' ]
			);
		}

		if ( $banner_data['AUTO_PUBLISH'] == 'Y' ) {
			process_image( $BID );
			publish_image( $BID );
			process_map( $BID );
		}

		return true;

	} catch ( \Exception $e ) {
		// Handle any exception that occurred during the file processing/Imagine operations
		// Clean up temporary files if they exist
		if (isset($tmp_image_file) && file_exists($tmp_image_file)) {
			 @unlink($tmp_image_file);
		}
		if (isset($uploadfile) && file_exists($uploadfile)) {
			@unlink($uploadfile);
		}
		// Return an error message string
		return Language::get_replace( 'Image processing failed: %ERROR%', '%ERROR%', $e->getMessage() );
	}
}

/**
 * @param int $length
 *
 * @return string
 */
function generate_unique_code( int $length ): string {
	$characterSet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$randomString = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$randomString .= $characterSet[ random_int( 0, strlen( $characterSet ) - 1 ) ];
	}
	return $randomString;
}
