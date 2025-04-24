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
use Imagine\Image\Metadata\ExifMetadataReader;
use MillionDollarScript\Classes\Data\Config;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Email\Emails;
use MillionDollarScript\Classes\Forms\FormFields;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Functions;
use MillionDollarScript\Classes\System\Utility;

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
		$replace[] = '<img alt="" src="' . Utility::get_upload_url() . "images/" . $filename . '" style="max-width:100px;max-height:100px;">';
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
	$banner_id = isset( $prams['banner_id'] ) ? intval( $prams['banner_id'] ) : "";

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
		$order = " `" . mysqli_real_escape_string( $GLOBALS['connection'], $order_by ) . "` ";
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

		$order_locking = Options::get_option( 'order-locking', false );

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
		$order = " `" . mysqli_real_escape_string( $GLOBALS['connection'], $order_by ) . "` ";
	}

	if ( ! is_numeric( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	$sql    = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id > 0 AND banner_id=%d AND user_id=%d AND `status` != 'deleted' ORDER BY %s %s", $BID, $user_id, $order, $ord );
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

		$order_locking = Options::get_option( 'order-locking', false );

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
						if ( ! $order_locking || ( $prams['status'] == 'denied' && $order_status ) ) {

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

function insert_ad_data( $order_id = 0, $admin = false ) {
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
			$image         = get_attached_file( $attachment_id );

			$sql          = "UPDATE " . MDS_DB_PREFIX . "blocks SET file_name=%s, url=%s, alt_text=%s, ad_id=%d WHERE FIND_IN_SET(block_id, %s) AND banner_id=%d;";
			$prepared_sql = $wpdb->prepare( $sql, $image, $url, $text, $ad_id, $blocks, $order_row['banner_id'] );
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
	if ( Config::get( 'EMAIL_ADMIN_PUBLISH_NOTIFY' ) == 'YES' ) {
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
 *
 * @return bool
 */
function upload_changed_pixels( int $order_id, int $BID, array $size, array $banner_data ): bool {
	global $f2;

	$imagine = new Imagine\Gd\Imagine();

	if ( ( ! empty( $_REQUEST['change_pixels'] ) ) && isset( $_FILES ) ) {
		// a new image was uploaded...

		$uploaddir = Utility::get_upload_path() . "images/";
		$files     = $_FILES['pixels'] ?? ( $_FILES['graphic'] ?? '' );

		if ( empty( $files['tmp_name'] ) ) {
			echo "<b>" . Language::get( 'No file was uploaded.' ) . "</b><br />";

			return false;
		}

		$filename   = sanitize_file_name( $files['name'] );
		$file_parts = pathinfo( $filename );

		// Check if the 'extension' key exists
		if ( isset( $file_parts['extension'] ) ) {
			$ext = $f2->filter( strtolower( $file_parts['extension'] ) );
		} else {
			$ext = '';
		}

		$mime_type          = mime_content_type( $files['tmp_name'] );
		$allowed_file_types = [ 'image/png', 'image/jpeg', 'image/gif' ];
		if ( ! in_array( $mime_type, $allowed_file_types ) ) {
			$error = "<b>" . Language::get( 'File type not supported.' ) . "</b><br />";
		}

		if ( ! empty( $error ) ) {
			echo $error;

			return false;
		} else {

			$uploadfile = $uploaddir . "tmp_" . $order_id . ".$ext";

			// move the file
			if ( move_uploaded_file( $files['tmp_name'], $uploadfile ) ) {
				// convert to png
				$image    = $imagine->open( $uploadfile );
				$fileinfo = pathinfo( $uploadfile );
				$newname  = ( $fileinfo['dirname'] ? $fileinfo['dirname'] . DIRECTORY_SEPARATOR : '' ) . $fileinfo['filename'] . '.png';
				$image->save( $newname );

				// File is valid, and was successfully uploaded.
				$tmp_image_file = $newname;

				Utility::setMemoryLimit( $uploadfile );

				// autorotate
				$imagine->setMetadataReader( new ExifMetadataReader() );
				$filter = new Imagine\Filter\Transformation();
				$filter->add( new AutoRotate() );
				$filter->apply( $image );

				// Determine actual image size after potential rotation/processing
				$actualSize = $image->getSize();
				$actualWidth = $actualSize->getWidth();
				$actualHeight = $actualSize->getHeight();

				// determine whether to resize and validate dimensions when disabled
				$MDS_RESIZE = Config::get( 'MDS_RESIZE' );
				if ( $MDS_RESIZE !== 'YES' ) {
					$img_size = $image->getSize();
					if ( $img_size->getWidth() > $size['x'] || $img_size->getHeight() > $size['y'] ) {
						echo Language::get_replace(
							'The size of the uploaded image is incorrect. It needs to be %SIZE_X% wide and %SIZE_Y% high (or less)',
							[ '%SIZE_X%', '%SIZE_Y%' ],
							[ $size['x'], $size['y'] ]
						) . "<br>";
						return false;
					}
				}
				if ( $MDS_RESIZE === 'YES' ) {
					$resize = new Imagine\Image\Box( $size['x'], $size['y'] );
					$image->resize( $resize );
				}

				// Precompute block dimensions and color for performance
				$block_size = new Imagine\Image\Box( $banner_data['BLK_WIDTH'], $banner_data['BLK_HEIGHT'] );
				$palette    = new Imagine\Image\Palette\RGB();
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
							$high_x = $block_row['x'];
						}
						if ( $block_row['x'] < $low_x ) {
							$low_x = $block_row['x'];
						}
						if ( $block_row['y'] > $high_y ) {
							$high_y = $block_row['y'];
						}
						if ( $block_row['y'] < $low_y ) {
							$low_y = $block_row['y'];
						}
					}
					$_REQUEST['map_x'] = $high_x;
					$_REQUEST['map_y'] = $high_y;
				} else {
					$low_x = $low_y = 0;
				}

				// Prepare for batch updates
				$updates = [];

				// Paste image into selected blocks (AJAX mode allows individual block selection)
				$blkW = $banner_data['BLK_WIDTH'];
				$blkH = $banner_data['BLK_HEIGHT'];
				$blocksPerRow = $banner_data['G_WIDTH'];
				$sizeX = $size['x'];
				$sizeY = $size['y'];
				for ( $y = 0; $y < $actualHeight; $y += $blkH ) {
					for ( $x = 0; $x < $actualWidth; $x += $blkW ) {

						// Calculate the size for this specific crop, ensuring it stays within bounds
						$cropWidth = min( $blkW, $actualWidth - $x );
						$cropHeight = min( $blkH, $actualHeight - $y );

						// Ensure dimensions are positive before attempting crop
						if ($cropWidth <= 0 || $cropHeight <= 0) {
							continue; // Skip this iteration if dimensions are invalid
						}

						$current_block_size = new Imagine\Image\Box($cropWidth, $cropHeight);
						$start_point = new Imagine\Image\Point($x, $y);

						// Create new destination image (always use standard block size)
						$dest = $imagine->create( $block_size, $color );

						// Crop the calculated part from the source image copy
						$block = $image->copy();
						$block->crop($start_point, $current_block_size);

						// Paste the (potentially smaller) cropped block into the top-left of the full-size destination image
						$dest->paste( $block, new Imagine\Image\Point( 0, 0 ) );

						// Save the image as a base64 encoded string
						$image_data = base64_encode( $dest->get( "png", array( 'png_compression_level' => 9 ) ) );

						// Calculate block coordinates based on loop variables and database offset
						$map_x     = $x + $low_x;
						$map_y     = $y + $low_y;
						$cb        = ( $map_x / $blkW ) + ( ( $map_y / $blkH ) * $blocksPerRow );

						// Queue for batch update (ensure block_id calculation is robust if needed)
						// Note: The calculation of $cb assumes a full grid based on $blocksPerRow.
						// If the uploaded image is smaller than the selected area ($sizeX, $sizeY), this might
						// assign data to incorrect block IDs if not all blocks are covered by the loop.
						// However, the original code also used $x and $y based on $sizeX/$sizeY for this,
						// so the behavior is preserved, but might need review depending on desired handling
						// of partially covered selections.
						$updates[] = [ 'block_id' => intval($cb), 'image_data' => $image_data ];
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
				}

				if ( $banner_data['AUTO_PUBLISH'] == 'Y' ) {
					process_image( $BID );
					publish_image( $BID );
					process_map( $BID );
				}
			}

			// Possible file upload attack!
			Language::out( 'Upload failed. Please try again, or try a different file.' );
		}
	}
	return true;
}
