<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.3
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

use MillionDollarScript\Classes\Language;
use MillionDollarScript\Classes\Utility;

defined( 'ABSPATH' ) or exit;

@set_time_limit( 260 );

mds_wp_login_check();

require_once MDS_CORE_PATH . "include/ads.inc.php";

// check if user has permission to access this page
if ( ! mds_check_permission( "mds_manage_pixels" ) ) {
	require_once MDS_CORE_PATH . "html/header.php";
	Language::out( "No Access" );
	require_once MDS_CORE_PATH . "html/footer.php";
	exit;
}

require_once MDS_CORE_PATH . "html/header.php";

$gd_info      = gd_info();
$gif_support  = '';
$jpeg_support = '';
$png_support  = '';
if ( isset( $gd_info['GIF Read Support'] ) && ! empty( $gd_info['GIF Read Support'] ) ) {
	$gif_support = "GIF";
}
if ( isset( $gd_info['JPEG Support'] ) && ! empty( $gd_info['JPEG Support'] ) || ( isset( $gd_info['JPG Support'] ) && ! empty( $gd_info['JPG Support'] ) ) ) {
	$jpeg_support = "JPG";
}
if ( isset( $gd_info['PNG Support'] ) && ! empty( $gd_info['PNG Support'] ) ) {
	$png_support = "PNG";
}

global $f2, $wpdb;

$BID = $f2->bid();

$banner_data = load_banner_constants( $BID );

// Entry point for completion of orders which are made by super users or if the order was for free
if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'complete' ) {

	// check if order is $0 & complete it

	if ( isset( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] == get_current_order_id() ) {
		// convert the temp order to an order.

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id='" . intval( get_current_order_id() ) . "' ";
		$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		if ( mysqli_num_rows( $order_result ) == 0 ) {
			// no order id found...
			\MillionDollarScript\Classes\Utility::redirect( 'no-orders' );
		} else if ( $order_row = mysqli_fetch_array( $order_result ) ) {

			$_REQUEST['order_id'] = reserve_pixels_for_temp_order( $order_row );
		} else {

			Language::out( '<h1>Pixel Reservation Not Yet Completed...</h1>' );
			Language::out_replace(
				'<p>We are sorry, it looks like you took too long! Either your session has timed out or the pixels we tried to reserve for you were snapped up by someone else in the meantime! Please go <a href="%ORDER_PAGE%">here</a> and try again.</p>',
				'%ORDER_PAGE%',
				Utility::get_page_url( 'order' )
			);

			require_once MDS_CORE_PATH . "html/footer.php";
			die();
		}
	}

	$sql = "select * from " . MDS_DB_PREFIX . "orders where order_id='" . intval( $_REQUEST['order_id'] ) . "' AND user_id='" . get_current_user_id() . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );

	$privileged = carbon_get_user_meta( get_current_user_id(), 'privileged' );

	if ( ( $row['price'] == 0 ) || ( $privileged == '1' ) ) {
		complete_order( $row['user_id'], $row['order_id'] );
		// no transaction for this order
		Language::out( '<h3>Your order was completed.</h3>' );
	}
	// publish

	if ( $banner_data['AUTO_PUBLISH'] == 'Y' ) {
		process_image( $BID );
		publish_image( $BID );
		process_map( $BID );
	}
}

// Banner Selection form
// Load this form only if more than 1 grid exists with pixels purchased.

$user_id = get_current_user_id();
$sql     = $wpdb->prepare(
	"SELECT DISTINCT b.banner_id, b.name, b.enabled 
    FROM " . MDS_DB_PREFIX . "orders AS o
    INNER JOIN " . MDS_DB_PREFIX . "banners AS b ON o.banner_id = b.banner_id 
    WHERE o.user_id = %d AND (o.status = 'completed' OR o.status = 'expired') 
    ORDER BY b.name", $user_id );

$res = $wpdb->get_results( $sql, ARRAY_A );

if ( count( $res ) > 1 ) {
	?>
    <div class="fancy-heading"><?php Language::out( 'Available Grids' ); ?></div>

	<?php
	Language::out_replace(
		'You own pixels on <b>%GRID_COUNT%</b> different grids served by this website. Select the image which you would like to publish your pixels to:',
		'%GRID_COUNT%',
		count( $res )
	);
	echo '<br />';
	display_banner_selecton_form( $BID, get_current_order_id(), $res, 'publish' );
}

// A block was clicked. Fetch the ad_id and initialize $_REQUEST['ad_id']
// If no ad exists for this block, create it.
$mds_pixel = null;
if ( isset( $_REQUEST['block_id'] ) && ! empty( $_REQUEST['block_id'] ) ) {

	$sql = "SELECT user_id, ad_id, order_id FROM " . MDS_DB_PREFIX . "blocks where banner_id='$BID' AND block_id='" . intval( $_REQUEST['block_id'] ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$blk_row = mysqli_fetch_array( $result );

	if ( ! isset( $blk_row['ad_id'] ) || empty( $blk_row['ad_id'] ) ) { // no ad exists, create a new ad_id
		$_POST[ MDS_PREFIX . 'url' ]  = '';
		$_POST[ MDS_PREFIX . 'text' ] = 'Pixel text';
		$_REQUEST['order_id']         = $blk_row['order_id'];
		$_REQUEST['BID']              = $BID;
		$_REQUEST['user_id']          = get_current_user_id();
		$_REQUEST['aid']              = "";
		$ad_id                        = insert_ad_data();

		$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET ad_id='" . intval( $ad_id ) . "' WHERE order_id='" . intval( $blk_row['order_id'] ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		$sql = "UPDATE " . MDS_DB_PREFIX . "blocks SET ad_id='" . intval( $ad_id ) . "' WHERE order_id='" . intval( $blk_row['order_id'] ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

		$_REQUEST['aid'] = $ad_id;
	} else {
		// initialize $_REQUEST['ad_id']

		// Make sure the mds-pixel exists.
		$mds_pixel = get_post( $blk_row['ad_id'] );

		if ( empty( $mds_pixel ) ) {
			Language::out( 'Unable to find pixels.' );
		} else {
			$_REQUEST['aid'] = $blk_row['ad_id'];
		}

		// bug in previous versions resulted in saving the ad's user_id with a session_id
		// fix user_id here
		// $sql = "UPDATE " . MDS_DB_PREFIX . "ads SET user_id='" . intval( $blk_row['user_id'] ) . "' WHERE order_id='" . intval( $blk_row['order_id'] ) . "' AND user_id <> '" . get_current_user_id() . "' limit 1 ";
		// mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	}
}

// Display ad editing forms if the ad was clicked, or 'Edit' button was pressed.
if ( isset( $_REQUEST['aid'] ) && ! empty( $_REQUEST['aid'] ) ) {

	if ( empty( $mds_pixel ) ) {
		// Make sure the mds-pixel exists.
		$mds_pixel = get_post( $_REQUEST['aid'] );
	}

	if ( empty( $mds_pixel ) ) {
		Language::out( 'Unable to find pixels.' );
	}

	global $wpdb;
	$sql          = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE ad_id=%d AND user_id=%d";
	$prepared_sql = $wpdb->prepare( $sql, intval( $_REQUEST['aid'] ), get_current_user_id(), intval( $BID ) );
	$result = $wpdb->get_results( $prepared_sql, ARRAY_A );

	if ( ! empty( $result ) ) {
		$row = $result[0];
	} else {
		$row = array();
	}

	$order_id = $row['order_id'];
	$blocks   = explode( ',', $row['blocks'] );

	$size   = get_pixel_image_size( $row['order_id'] );
	$pixels = $size['x'] * $size['y'];
	upload_changed_pixels( $order_id, $row['banner_id'], $size, $banner_data );

	// Ad forms:
	?>
    <div class="fancy-heading"><?php Language::out( 'Edit your Ad / Change your pixels' ); ?></div>
	<?php
	Language::out( '<p>Here you can edit your ad or change your pixels.</p>' );
	Language::out( '<p><b>Your Pixels:</b></p>' );
	?>
    <table>
        <tr>
            <td><b><?php Language::out( 'Pixels' ); ?></b><br>
				<?php
				if ( isset( $_REQUEST['aid'] ) && ! empty( $_REQUEST['aid'] ) ) {
					?><img src="<?php echo Utility::get_page_url( 'get-order-image' ); ?>?BID=<?php echo $BID; ?>&aid=<?php echo $_REQUEST['aid']; ?>" alt=""><?php
				} else {
					?><img src="<?php echo Utility::get_page_url( 'get-order-image' ); ?>?BID=<?php echo $BID; ?>&block_id=<?php echo $_REQUEST['block_id']; ?>" alt=""><?php
				} ?>
            </td>
            <td><b><?php Language::out( 'Pixel Info' ); ?></b><br><?php
				Language::out_replace(
					'%PIXEL_COUNT% pixels<br>(%SIZE_X% wide,  %SIZE_Y% high)',
					[ '%SIZE_X%', '%SIZE_Y%', '%PIXEL_COUNT%' ],
					[ $size['x'], $size['y'], $pixels ]
				);
				?><br></td>
            <td><b><?php Language::out( 'Change Pixels' ); ?></b><br><?php
				Language::out_replace(
					'To change these pixels, select an image %SIZE_X% pixels wide & %SIZE_Y% pixels high and click "upload"',
					[ '%SIZE_X%', '%SIZE_Y%' ],
					[ $size['x'], $size['y'] ]
				);
				?>
                <form name="change" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" method="post">
					<?php wp_nonce_field( 'mds-form' ); ?>
                    <input type="hidden" name="action" value="mds_form_submission">
                    <input type="hidden" name="mds_dest" value="publish">
                    <input type="file" name='pixels'><br>
                    <input type="hidden" name="aid" value="<?php echo $_REQUEST['aid']; ?>">
                    <input type="submit" name="change_pixels" value="<?php echo esc_attr( Language::get( 'Upload' ) ); ?>">
                </form>
				<?php Language::out( 'Supported formats:' ); ?><?php echo "$gif_support $jpeg_support $png_support"; ?>
            </td>
        </tr>
    </table>

    <p><b><?php Language::out( 'Edit Your Ad:' ); ?></b></p>
	<?php

	if ( isset( $_REQUEST['save'] ) && ! empty( $_REQUEST['save'] ) ) {
		\MillionDollarScript\Classes\Functions::verify_nonce( 'mds_form' );

		$ad_id = insert_ad_data();

		global $prams;
		$prams = load_ad_values( $ad_id );

		?>
        <div class='ok_msg_label'><?php Language::out( 'Ad Saved' ); ?></div>
		<?php

		$mode = "user";

		display_ad_form( 1, $mode, $prams );

		// disapprove the pixels because the ad was modified..

		if ( $banner_data['AUTO_APPROVE'] != 'Y' ) { // to be approved by the admin
			disapprove_modified_order( $prams['order_id'], $BID );
		}

		if ( $banner_data['AUTO_PUBLISH'] == 'Y' ) {
			process_image( $BID );
			publish_image( $BID );
			process_map( $BID );
			//echo 'published.';
		}

		// send pixel change notification
		if ( \MillionDollarScript\Classes\Config::get( 'EMAIL_ADMIN_PUBLISH_NOTIFY' ) == 'YES' ) {
			send_published_pixels_notification( get_current_user_id(), $BID );
		}
	} else {

		$prams = load_ad_values( $_REQUEST['aid'] );
		display_ad_form( 1, 'user', $prams );
	}

	require_once MDS_CORE_PATH . "html/footer.php";
    return;
}

$offset = isset( $_REQUEST['offset'] ) ? intval( $_REQUEST['offset'] ) : 0;

# List Ads
ob_start();
$count    = list_ads( false, $offset, 'USER' );
$contents = ob_get_contents();
ob_end_clean();

if ( $count > 0 ) {
	?>
    <div class="fancy-heading"><?php Language::out( 'Manage your pixels' ); ?></div>
	<?php
	echo $contents;

	Language::out( 'Your blocks are shown on the grid below.<br />
- Click your block to edit it. Only your blocks are shown.<br />
- <b>Red</b> blocks have no pixels yet. Click on them to upload your pixels.<br />' );

	// inform the user about the approval status of the images.

	$sql = "select * from " . MDS_DB_PREFIX . "orders where user_id='" . get_current_user_id() . "' AND status='completed' and  approved='N' and banner_id='" . intval( $BID ) . "' ";
	$result4 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	if ( mysqli_num_rows( $result4 ) > 0 ) {
		?>
        <div style="border-color:#FF9797; border-style:solid;padding:5px;"><?php Language::out( 'Note: Your pixels are waiting for approval. They will be scheduled to go live once they are approved.' ); ?></div>
		<?php
	} else {

		$sql = "select * from " . MDS_DB_PREFIX . "orders where user_id='" . get_current_user_id() . "' AND status='completed' and  approved='Y' and published='Y' and banner_id='" . intval( $BID ) . "' ";
		//echo $sql;
		$result4 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

		if ( mysqli_num_rows( $result4 ) > 0 ) {
			?>
            <div style="border-color:green;border-style:solid;padding:5px;margin:10px;"><?php Language::out( 'Note: Your pixels are now published and live!' ); ?></div>
			<?php
		} else {

			$sql = "select * from " . MDS_DB_PREFIX . "orders where user_id='" . get_current_user_id() . "' AND status='completed' and  approved='Y' and published='N' and banner_id='" . intval( $BID ) . "' ";

			$result4 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

			if ( mysqli_num_rows( $result4 ) > 0 ) {
				?>
                <div style="border-color:yellow;border-style:solid;padding:5px;"><?php Language::out( 'Your pixels are now approved! They are now waiting for the webmaster to publish them on to the public grid.' ); ?></div>
				<?php
			}
		}
	}

	// Generate the Area map form the current sold blocks.
	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "blocks WHERE user_id='" . get_current_user_id() . "' AND status='sold' and banner_id='" . intval( $BID ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	?>
    <div class="publish-grid">
        <map name="main" id="main">
			<?php
			while ( $row = mysqli_fetch_array( $result ) ) {
				?>
                <area shape="RECT" coords="<?php echo $row['x']; ?>,<?php echo $row['y']; ?>,<?php echo $row['x'] + $banner_data['BLK_WIDTH']; ?>,<?php echo $row['y'] + $banner_data['BLK_HEIGHT']; ?>" href="<?php echo esc_url( Utility::get_page_url( 'manage' ) ); ?>?BID=<?php echo $BID; ?>&amp;block_id=<?php echo( $row['block_id'] ); ?>" title="<?php echo( $row['alt_text'] ); ?>" alt="<?php echo( $row['alt_text'] ); ?>"/>
				<?php
			}
			?>
        </map>
        <img id="publish-grid" src="<?php echo esc_url( Utility::get_page_url( 'show-map' ) ); ?>?BID=<?php echo $BID; ?>&amp;time=<?php echo( time() ); ?>" width="<?php echo( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ); ?>" height="<?php echo( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ); ?>" usemap="#main" alt=""/>
    </div>
	<?php
} else {
	Language::out_replace( 'You have no pixels yet. Go <a href="%ORDER_URL%">here</a> to order pixels.', '%ORDER_URL%', Utility::get_page_url( 'order' ) );
}
require_once MDS_CORE_PATH . "html/footer.php";
