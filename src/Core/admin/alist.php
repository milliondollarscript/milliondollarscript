<?php
/*
 * @package       mds
 * @copyright     (C) Copyright 2022 Ryan Rhode, All rights reserved.
 * @author        Ryan Rhode, ryan@milliondollarscript.com
 * @version       2022-01-30 17:07:25 EST
 * @license       This program is free software; you can redistribute it and/or modify
 *        it under the terms of the GNU General Public License as published by
 *        the Free Software Foundation; either version 3 of the License, or
 *        (at your option) any later version.
 *
 *        This program is distributed in the hope that it will be useful,
 *        but WITHOUT ANY WARRANTY; without even the implied warranty of
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *        GNU General Public License for more details.
 *
 *        You should have received a copy of the GNU General Public License along
 *        with this program;  If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 *
 *  * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *        Million Dollar Script
 *        A pixel script for selling pixels on your website.
 *
 *        For instructions see README.txt
 *
 *        Visit our website for FAQs, documentation, a list team members,
 *        to post any bugs or feature requests, and a community forum:
 *        https://milliondollarscript.com/
 *
 */

require_once __DIR__ . "/../include/init.php";

require( 'admin_common.php' );
require_once( "../include/ads.inc.php" );

global $f2, $label;

$BID = $f2->bid();

$banner_data = load_banner_constants( $BID );

$error = '';

if ( isset( $_REQUEST['aid'] ) && is_numeric( $_REQUEST['aid'] ) ) {

	$gd_info = @gd_info();
	$gif_support = '';
	$jpeg_support = '';
	$png_support = '';
	if ( isset( $gd_info['GIF Read Support'] ) && ! empty( $gd_info['GIF Read Support'] ) ) {
		$gif_support = "GIF";
	}
	if ( isset( $gd_info['JPEG Support'] ) && ! empty( $gd_info['JPEG Support'] ) || (isset( $gd_info['JPG Support'] ) && ! empty( $gd_info['JPG Support'] )) ) {
		$jpeg_support = "JPG";
	}
	if ( isset( $gd_info['PNG Support'] ) && ! empty( $gd_info['PNG Support'] ) ) {
		$png_support = "PNG";
	}

	$prams = load_ad_values( $_REQUEST['aid'] );

	// pre-check for failure
	if ( $prams['user_id'] == "" ) {
		die( "Either the user id for this ad doesn't exist or this ad doesn't exist." );
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
    <div class="fancy_heading" width="85%"><?php echo $label['adv_pub_editad_head']; ?></div>
    <p><?php echo $label['adv_pub_editad_desc']; ?> </p>
    <p><b><?php echo $label['adv_pub_yourpix']; ?></b></p>
    <table border=0 bgcolor='#d9d9d9' cellspacing="1" cellpadding="5">
        <tr bgcolor="#ffffff">
            <td valign="top"><b><?php echo $label['adv_pub_piximg']; ?></b><br>
                <center>
					<?php
					if ( $_REQUEST['aid'] != '' ) {
						?><img id="order_image_preview" src="get_order_image.php?BID=<?php echo $BID; ?>&aid=<?php echo $_REQUEST['aid']; ?>" border=1><?php
					} else {
						?><img id="order_image_preview" src="get_order_image.php?BID=<?php echo $BID; ?>&block_id=<?php echo $_REQUEST['block_id']; ?>" border=1><?php
					} ?>
                </center>
            </td>
            <td valign="top"><b><?php echo $label['adv_pub_pixinfo']; ?></b><br><?php

				$label['adv_pub_pixcount'] = str_replace( '%SIZE_X%', $size['x'], $label['adv_pub_pixcount'] );
				$label['adv_pub_pixcount'] = str_replace( '%SIZE_Y%', $size['y'], $label['adv_pub_pixcount'] );
				$label['adv_pub_pixcount'] = str_replace( '%PIXEL_COUNT%', $pixels, $label['adv_pub_pixcount'] );
				echo $label['adv_pub_pixcount'];
				?><br></td>
            <td valign="top"><b><?php echo $label['adv_pub_pixchng']; ?></b><br><?php
				$label['adv_pub_pixtochng'] = str_replace( '%SIZE_X%', $size['x'], $label['adv_pub_pixtochng'] );
				$label['adv_pub_pixtochng'] = str_replace( '%SIZE_Y%', $size['y'], $label['adv_pub_pixtochng'] );
				echo $label['adv_pub_pixtochng'];
				?>
                <form name="change" enctype="multipart/form-data" method="post" action="alist.php">
                    <input type="file" name='pixels'><br>
                    <input type="hidden" name="aid" value="<?php echo $_REQUEST['aid']; ?>">
                    <input type="submit" name="change_pixels" value="<?php echo $label['adv_pub_pixupload']; ?>"></form><?php if ( $error ) {
					echo "<font color='red'>" . $error . "</font>";
					$error = '';
				} ?>
                <font size='1'><?php echo $label['advertiser_publish_supp_formats']; ?><?php echo "$gif_support $jpeg_support $png_support"; ?></font>
            </td>
        </tr>
    </table>

    <p><b><?php echo $label['adv_pub_edityourad']; ?></b></p>
	<?php

	if ( $_REQUEST['save'] != "" ) {
		// saving

		$error = validate_ad_data( 1 );
		if ( $error != '' ) {
			// we have an error
			display_ad_form( 1, "user", '' );
		} else {
			insert_ad_data( true ); // admin mode
			$prams = load_ad_values( $_REQUEST['aid'] );
			update_blocks_with_ad( $_REQUEST['aid'], $prams['user_id'] );
			display_ad_form( 1, "user", $prams );
			// disapprove the pixels because the ad was modified..

			if ( $banner_data['AUTO_APPROVE'] != 'Y' ) {
				// to be approved by the admin
				disapprove_modified_order( $prams['order_id'], $BID );
			}

			if ( $banner_data['AUTO_PUBLISH'] == 'Y' ) {
				process_image( $BID );
				publish_image( $BID );
				process_map( $BID );
			}
			echo 'Ad Saved. <a href="alist.php?BID=' . $prams['banner_id'] . '">&lt;&lt; Go to the Ad List</a>';
			echo "<hr>";
		}
	} else {

		$prams = load_ad_values( $_REQUEST['aid'] );
		display_ad_form( 1, "user", $prams );
	}
	$prams  = load_ad_values( $_REQUEST['aid'] );
	$sql    = "select * FROM " . MDS_DB_PREFIX . "users where ID='" . intval( $prams['user_id'] ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql );
	$u_row  = mysqli_fetch_array( $result );

	$b_row = load_banner_row( $prams['banner_id'] );
	?>

    <h3>Additional Info</h3>
    <b>Customer:</b><?php echo $u_row['LastName'] . ', ' . $u_row['FirstName']; ?><BR>
    <b>Order #:</b><?php echo $prams['order_id']; ?><br>
    <b>Grid:</b><a href='ordersmap.php?banner_id=<?php echo $prams['banner_id']; ?>'><?php echo $prams['banner_id'] . " - " . $b_row['name']; ?></a>

	<?php
	echo '<hr>';
} else {

	// select banner id
	$BID = $f2->bid();

	$sql = "Select * from " . MDS_DB_PREFIX . "banners ";
	$res = mysqli_query( $GLOBALS['connection'], $sql );
	?>

    <form name="bidselect" method="post" action="alist.php">

        Select grid: <select name="BID" onchange="mds_submit(this)">
            <option></option>
			<?php
			while ( $row = mysqli_fetch_array( $res ) ) {

				if ( ( $row['banner_id'] == $BID ) && ( $BID != 'all' ) ) {
					$sel = 'selected';
				} else {
					$sel = '';
				}
				echo '<option ' . $sel . ' value=' . $row['banner_id'] . '>' . $row['name'] . '</option>';
			}
			?>
        </select>
    </form>
    <hr>
	<?php
}

$offset = isset( $_REQUEST['offset'] ) ? intval( $_REQUEST['offset'] ) : 0;

$count = list_ads( true, $offset );
