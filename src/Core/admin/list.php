<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.9
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

defined( 'ABSPATH' ) or exit;

require_once MDS_CORE_PATH . "include/ads.inc.php";

global $f2;

$BID = $f2->bid();

$banner_data = load_banner_constants( $BID );

$error = '';

if ( isset( $_REQUEST['aid'] ) && is_numeric( $_REQUEST['aid'] ) ) {

	$gd_info      = @gd_info();
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

	$prams = load_ad_values( $_REQUEST['aid'] );

	// pre-check for failure
	if ( $prams['user_id'] == "" ) {
		die( Language::get( "Either the user id for this ad doesn't exist or this ad doesn't exist." ) );
	}

	$banner_data = load_banner_constants( $prams['banner_id'] );

	$sql = "SELECT * from " . MDS_DB_PREFIX . "ads as t1, " . MDS_DB_PREFIX . "orders as t2 where t1.ad_id=t2.ad_id AND t1.user_id=" . intval( $prams['user_id'] ) . " and t1.banner_id='" . intval( $prams['banner_id'] ) . "' and t1.ad_id='" . intval( $prams['ad_id'] ) . "' AND t1.order_id=t2.order_id ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	$row      = mysqli_fetch_array( $result );
	$order_id = $row['order_id'];
	$blocks   = explode( ',', $row['blocks'] );

	$size   = get_pixel_image_size( $row['order_id'] );
	$pixels = $size['x'] * $size['y'];

	upload_changed_pixels( $order_id, $BID, $size, $banner_data );

	// Ad forms:
	?>
    <p>
    <div class="fancy-heading"><?php Language::out( 'Edit your Ad / Change your pixels' ); ?></div>
    <p><?php Language::out( 'Here you can edit your ad or change your pixels.' ); ?> </p>
    <p><b><?php Language::out( 'Your Pixels:' ); ?></b></p>
    <table>
        <tr>
            <td><b><?php Language::out( 'Pixels' ); ?></b><br>
                <div style="text-align: center;">
					<?php
					if ( $_REQUEST['aid'] != '' ) {
						?><img id="order_image_preview"
                               src="<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>get-order-image&amp;BID=<?php echo $BID; ?>&amp;aid=<?php echo intval( $_REQUEST['aid'] ); ?>"
                               border=1><?php
					} else {
						?><img id="order_image_preview"
                               src="<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>get-order-image&amp;BID=<?php echo $BID; ?>&amp;block_id=<?php echo intval( $_REQUEST['block_id'] ); ?>"
                               border=1><?php
					} ?>
                </div>
            </td>
            <td><b><?php Language::out( 'Pixel Info' ); ?></b><br><?php
				Language::out_replace(
					'%PIXEL_COUNT% pixels<br>(%SIZE_X% wide,  %SIZE_Y% high)',
					[ '%SIZE_X%', '%SIZE_Y%', '%PIXEL_COUNT%' ],
					[ $size['x'], $size['y'], $pixels ]
				);
				?><br></td>
            <td style="max-width:200px;"><b>Blocks</b><br><?php
				echo str_replace( ',', ', ', $row['blocks'] );
				?><br></td>
            <td><b><?php Language::out( 'Change Pixels' ); ?></b><br><?php
				Language::out_replace(
					'To change these pixels, select an image %SIZE_X% pixels wide & %SIZE_Y% pixels high and click \'Upload\'',
					[ '%SIZE_X%', '%SIZE_Y%' ],
					[ $size['x'], $size['y'] ]
				);
				?>
                <form name="change" enctype="multipart/form-data" method="post"
                      action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'mds-admin' ); ?>
                    <input type="hidden" name="action" value="mds_admin_form_submission"/>
                    <input type="hidden" name="mds_dest" value="list"/>

                    <input type="file" name='pixels'><br>
                    <input type="hidden" name="aid" value="<?php echo intval( $_REQUEST['aid'] ); ?>">
                    <input type="submit" name="change_pixels"
                           value="<?php echo esc_attr( Language::get( 'Upload' ) ); ?>"></form><?php if ( $error ) {
					echo "<span style=\"color: red; \">" . $error . "</span>";
					$error = '';
				} ?>
                <span><?php Language::out( 'Supported formats:' ); ?><?php echo "$gif_support $jpeg_support $png_support"; ?></span>
            </td>
        </tr>
    </table>

    <p><b><?php Language::out( 'Edit Your Ad:' ); ?></b></p>
	<?php

	if ( ! empty( $_REQUEST['save'] ) ) {

		// saving
		// admin mode
		insert_ad_data( $order_id, true );
		$prams = load_ad_values( $_REQUEST['aid'] );

		display_ad_form( 1, "user", $prams );
		// disapprove the pixels because the ad was modified...

		if ( $banner_data['AUTO_APPROVE'] != 'Y' ) {
			// to be approved by the admin
			disapprove_modified_order( $prams['order_id'], $BID );
		}

		if ( $banner_data['AUTO_PUBLISH'] == 'Y' ) {
			process_image( $BID );
			publish_image( $BID );
			process_map( $BID );
		}

		Language::out_replace(
			'Ad Saved. <a href="%LIST_URL%">&lt;&lt; Go to the Ad List</a>',
			'%LIST_URL%',
			esc_url( admin_url( 'admin.php?page=mds-list&BID=' . $prams['banner_id'] ) )
		);
		?>
        <hr>
		<?php
	} else {

		$prams = load_ad_values( $_REQUEST['aid'] );
		display_ad_form( 1, "user", $prams );
	}
	$prams = load_ad_values( $_REQUEST['aid'] );

	$user_info = get_userdata( intval( $prams['user_id'] ) );

	$b_row = load_banner_row( $prams['banner_id'] );

	Language::out( '<h3>Additional Info</h3>' );
	?>


    <b><?php Language::out( 'Customer:' ); ?></b><?php echo esc_html( $user_info->last_name ) . ', ' . esc_html( $user_info->first_name ); ?>
    <BR>
    <b><?php Language::out( 'Order #:' ); ?></b><?php echo $prams['order_id']; ?><br>
    <b><?php Language::out( 'Grid:' ); ?></b><a
            href='<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>map-of-orders&amp;banner_id=<?php echo intval( $prams['banner_id'] ); ?>'><?php echo esc_html( $prams['banner_id'] . " - " . $b_row['name'] ); ?></a>

	<?php
	echo '<hr>';
} else {

	// select banner id
	$BID = $f2->bid();

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners ";
	$res = mysqli_query( $GLOBALS['connection'], $sql );
	?>

    <form name="bidselect" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'mds-admin' ); ?>
        <input type="hidden" name="action" value="mds_admin_form_submission"/>
        <input type="hidden" name="mds_dest" value="list"/>

		<?php Language::out( 'Select grid:' ); ?>
        <label>
            <select name="BID" onchange="this.form.submit()">
				<?php
				while ( $row = mysqli_fetch_array( $res ) ) {

					if ( ( $row['banner_id'] == $BID ) && ( $BID != 'all' ) ) {
						$sel = 'selected';
					} else {
						$sel = '';
					}
					echo '<option ' . $sel . ' value=' . intval( $row['banner_id'] ) . '>' . esc_html( $row['name'] ) . '</option>';
				}
				?>
            </select>
        </label>
    </form>
    <hr>
	<?php
}

$offset = isset( $_REQUEST['offset'] ) ? intval( $_REQUEST['offset'] ) : 0;

$count = list_ads( true, $offset );
