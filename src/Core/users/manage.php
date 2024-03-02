<?php
/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2024, Ryan Rhode
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

use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Functions;
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

require_once MDS_CORE_PATH . "include/ads.inc.php";

// check if user has permission to access this page
if ( ! mds_check_permission( "mds_manage_pixels" ) ) {
	require_once MDS_CORE_PATH . "html/header.php";
	Language::out( "No Access" );
	require_once MDS_CORE_PATH . "html/footer.php";
	exit;
}

$wrap = false;
if ( empty( $_REQUEST['change_pixels'] ) && empty( $_FILES ) && empty( $_REQUEST['json'] ) ) {
	$wrap = true;
}

if ( $wrap ) {
	require_once MDS_CORE_PATH . "html/header.php";
}

$gd_info      = gd_info();
$gif_support  = '';
$jpeg_support = '';
$png_support  = '';
if ( ! empty( $gd_info['GIF Read Support'] ) ) {
	$gif_support = "GIF";
}
if ( ! empty( $gd_info['JPEG Support'] ) || ( ! empty( $gd_info['JPG Support'] ) ) ) {
	$jpeg_support = "JPG";
}
if ( ! empty( $gd_info['PNG Support'] ) ) {
	$png_support = "PNG";
}

global $BID, $f2, $wpdb;

$BID = $f2->bid();

$banner_data = load_banner_constants( $BID );
$user_id     = get_current_user_id();

if ( isset( $_REQUEST['cancel'] ) && $_REQUEST['cancel'] == 'yes' && isset( $_REQUEST['order_id'] ) ) {
	$order_id = intval( $_REQUEST['order_id'] );
	Orders::cancel( $order_id, $user_id );
}

// Entry point for completion of orders
if ( isset( $_REQUEST['mds-action'] ) ) {
	if ( $_REQUEST['mds-action'] == 'complete' ) {
		Orders::complete( $banner_data['AUTO_PUBLISH'], $BID );

	} else if ( isset( $_REQUEST['aid'] ) ) {

		$order_id = Orders::get_order_id_from_ad_id( intval( $_REQUEST['aid'] ) );
		if ( Options::get_option( 'order-locking', false ) ) {
			// Order locking is enabled so check if the order is approved or completed before allowing the user to save the ad.

			$sql = $wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d AND user_id = %d",
				$order_id,
				get_current_user_id()
			);

			$row               = $wpdb->get_row( $sql, ARRAY_A );
			$completion_status = Orders::get_completion_status( $order_id, $user_id );
			if ( $completion_status && $row['status'] !== 'denied' ) {
				// User should never get here, so we will just redirect them to the manage page.
				Utility::redirect( Utility::get_page_url( 'manage' ) );
			}
		}
	}
}

// A block was clicked. Fetch the ad_id and initialize $_REQUEST['ad_id']
// If no ad exists for this block, create it.
$mds_pixel = null;
if ( ! empty( $_REQUEST['block_id'] ) || ( isset( $_REQUEST['block_id'] ) && $_REQUEST['block_id'] != 0 ) ) {

	$BID      = intval( $BID );
	$block_id = intval( $_REQUEST['block_id'] );

	$sql     = $wpdb->prepare( "SELECT `user_id`, `ad_id`, `order_id` FROM " . MDS_DB_PREFIX . "blocks WHERE `banner_id`=%d AND `block_id`=%d", $BID, $block_id );
	$blk_row = $wpdb->get_row( $sql, ARRAY_A );

	if ( empty( $blk_row['ad_id'] ) ) {
		// no ad exists, create a new ad_id
		$_POST[ MDS_PREFIX . 'url' ]  = '';
		$_POST[ MDS_PREFIX . 'text' ] = 'Pixel text';
		$_REQUEST['order_id']         = $blk_row['order_id'];
		$_REQUEST['BID']              = $BID;
		$_REQUEST['user_id']          = get_current_user_id();
		$_REQUEST['aid']              = "";
		$ad_id                        = insert_ad_data( $blk_row['order_id'] );

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
if ( ( ( isset( $_REQUEST['mds_dest'] ) && $_REQUEST['mds_dest'] == 'manage' ) || isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'manage' ) && ! empty( $_REQUEST['aid'] ) ) {
	$ad_id = intval( $_REQUEST['aid'] );
	Functions::manage_pixel( $ad_id, $mds_pixel, $banner_data, $BID, $gif_support, $jpeg_support, $png_support );

	return;
}

$offset = isset( $_REQUEST['offset'] ) ? intval( $_REQUEST['offset'] ) : 0;

# List Ads
ob_start();
$count    = manage_list_ads( $offset );
$contents = ob_get_contents();
ob_end_clean();

if ( $count > 0 ) {
	?>
    <div class="fancy-heading"><?php Language::out( 'Manage your pixels' ); ?></div>
	<?php
	$sql   = $wpdb->prepare( "SELECT grid_width,grid_height, block_width, block_height, bgcolor, time_stamp FROM " . MDS_DB_PREFIX . "banners WHERE (banner_id = %d)", $BID );
	$b_row = $wpdb->get_row( $sql, ARRAY_A );

	$b_row['block_width']  = ( ! empty( $b_row['block_width'] ) ) ? $b_row['block_width'] : 10;
	$b_row['block_height'] = ( ! empty( $b_row['block_height'] ) ) ? $b_row['block_height'] : 10;

	$user_id = get_current_user_id();

	$sql    = $wpdb->prepare( "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE (user_id = %d) AND (status = 'sold')", $user_id );
	$result = $wpdb->get_results( $sql );
	$pixels = count( $result ) * ( $b_row['block_width'] * $b_row['block_height'] );

	$sql     = $wpdb->prepare( "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE (user_id = %d) AND (status = 'ordered')", $user_id );
	$result  = $wpdb->get_results( $sql );
	$ordered = count( $result ) * ( $b_row['block_width'] * $b_row['block_height'] );

	// Replacements for custom page links in WP
	$order_page   = Utility::get_page_url( 'order' );
	$manage_page  = Utility::get_page_url( 'manage' );
	$account_page = \MillionDollarScript\Classes\Data\Options::get_option( 'account-page' );

	Language::out_replace(
		'<p>Here you can manage your pixels.<p>',
		[
			'%PIXEL_COUNT%',
			'%BLOCK_COUNT%',
			'%MANAGE_URL%',
		],
		[
			$pixels,
			( $pixels / ( $b_row['block_width'] * $b_row['block_height'] ) ),
			Utility::get_page_url( 'manage' ),
		]
	);

	Language::out_replace(
		'<p>You own %PIXEL_COUNT% blocks.</p>',
		[
			'%PIXEL_COUNT%',
			'%BLOCK_COUNT%',
			'%MANAGE_URL%',
		],
		[
			$pixels,
			( $pixels / ( $b_row['block_width'] * $b_row['block_height'] ) ),
			Utility::get_page_url( 'manage' ),
		]
	);

	Language::out_replace(
		'<p>You have %PIXEL_ORD_COUNT% pixels on order (%BLOCK_ORD_COUNT% blocks). <a href="%ORDER_URL%">Order Pixels</a></p>',
		[
			'%PIXEL_ORD_COUNT%',
			'%BLOCK_ORD_COUNT%',
			'%ORDER_URL%',
		],
		[
			$ordered,
			( $ordered / ( $b_row['block_width'] * $b_row['block_height'] ) ),
			$order_page,
		]
	);

	Language::out_replace( '<p>Your pixels were clicked %CLICK_COUNT% times.</p>', '%CLICK_COUNT%', number_format( intval( get_user_meta( $user_id, MDS_PREFIX . 'click_count', true ) ) ) );
	Language::out_replace( '<p>Your pixels were viewed %VIEW_COUNT% times.</p>', '%VIEW_COUNT%', number_format( intval( get_user_meta( $user_id, MDS_PREFIX . 'view_count', true ) ) ) );

	echo $contents;

	// inform the user about the approval status of the images.

	$sql = "select * from " . MDS_DB_PREFIX . "orders where user_id='" . get_current_user_id() . "' AND status='completed' and  approved='N' and banner_id='" . intval( $BID ) . "' ";
	$result4 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	if ( mysqli_num_rows( $result4 ) > 0 ) {
		?>
        <div class="mds-error-message"><?php Language::out( 'Note: Your pixels are waiting for approval. They will be scheduled to go live once they are approved.' ); ?></div>
		<?php
	} else {

		$sql = "select * from " . MDS_DB_PREFIX . "orders where user_id='" . get_current_user_id() . "' AND status='completed' and  approved='Y' and published='Y' and banner_id='" . intval( $BID ) . "' ";
		//echo $sql;
		$result4 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

		if ( mysqli_num_rows( $result4 ) > 0 ) {
			?>
            <div class="mds-success-message"><?php Language::out( 'Note: Your pixels are now published and live!' ); ?></div>
			<?php
		} else {

			$sql = "select * from " . MDS_DB_PREFIX . "orders where user_id='" . get_current_user_id() . "' AND status='completed' and  approved='Y' and published='N' and banner_id='" . intval( $BID ) . "' ";

			$result4 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

			if ( mysqli_num_rows( $result4 ) > 0 ) {
				?>
                <div class="mds-caution-message"><?php Language::out( 'Your pixels are now approved! They are now waiting for the webmaster to publish them on to the public grid.' ); ?></div>
				<?php
			}
		}
	}
	?>

    <div class="mds-info-message">
		<?php
		Language::out( '"Completed" - Orders where the transaction was successfully completed.<br />
"Confirmed" - Orders confirmed by you, but the transaction has not been completed.<br />
"Pending" - Orders confirmed by you, but the transaction has not been approved.<br />
"Denied" - Denied by the administrator.<br />
"Cancelled" - Orders that have been cancelled.<br />
"Expired" - Pixels were expired after the specified term. You can renew this order.<br />' );
		?>
    </div>

    <div class="mds-info-message">
		<?php
		Language::out( 'Your blocks are shown on the grid below. Only your blocks are shown.<br />
- Click your block to edit it.<br />
- <b>Red</b> blocks have no pixels yet. Click on them to upload your pixels.' );
		?>
    </div>
	<?php
	// Generate the Area map form the current sold blocks.
	$sql = $wpdb->prepare(
		"SELECT b.* FROM " . MDS_DB_PREFIX . "blocks AS b
    INNER JOIN " . MDS_DB_PREFIX . "orders AS o ON b.order_id = o.order_id
    WHERE b.user_id=%d AND b.status IN ('sold','ordered','denied') AND b.banner_id=%d
    AND o.status NOT IN ('confirmed', 'new', 'deleted')",
		get_current_user_id(),
		intval( $BID )
	);

	$results = $wpdb->get_results( $sql, ARRAY_A );
	if ( ! empty( $results ) ) { ?>
        <div class="publish-grid">
            <map name="main" id="main">
				<?php
				foreach ( $results as $row ) {

					// Sanitize alt_text
					$text = sanitize_text_field( $row['alt_text'] );

					// Prepare coordinates
					$coords = sprintf(
						"%d,%d,%d,%d",
						$row['x'],
						$row['y'],
						$row['x'] + $banner_data['BLK_WIDTH'],
						$row['y'] + $banner_data['BLK_HEIGHT']
					);

					// Prepare href
					$href = esc_url(
						add_query_arg(
							[ 'mds-action' => 'manage', 'aid' => $row['ad_id'] ],
							Utility::get_page_url( 'manage' )
						)
					);

					// Output area
					printf(
						'<area shape="RECT" coords="%s" href="%s" title="%s" alt="%s"/>',
						$coords,
						$href,
						esc_attr( $text ),
						esc_attr( $text )
					);

				}
				?>
            </map>
			<?php
			// Prepare src
			$src = esc_url(
				add_query_arg(
					[ 'BID' => $BID, 'time' => time() ],
					Utility::get_page_url( 'show-map' )
				)
			);

			// Prepare width and height
			$width  = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];
			$height = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];

			// Output img
			printf(
				'<img id="publish-grid" src="%s" width="%d" height="%d" usemap="#main" alt=""/>',
				$src,
				$width,
				$height
			);
			?>
        </div>
	<?php }
} else {
	Language::out_replace( 'You have no pixels yet. Go <a href="%ORDER_URL%">here</a> to order pixels.', '%ORDER_URL%', Utility::get_page_url( 'order' ) );
}
require_once MDS_CORE_PATH . "html/footer.php";
