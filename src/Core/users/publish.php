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

@set_time_limit( 260 );
require_once __DIR__ . "/../include/login_functions.php";
mds_start_session();
require_once __DIR__ . "/../include/init.php";
require_once BASE_PATH . "/include/ads.inc.php";

process_login();

// check if user has permission to access this page
if ( ! mds_check_permission( "mds_manage_pixels" ) ) {
	require_once BASE_PATH . "/html/header.php";
	_e( "No Access", 'milliondollarscript' );
	require_once BASE_PATH . "/html/footer.php";
	exit;
}

require_once BASE_PATH . "/html/header.php";

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

global $f2, $label;

$BID = $f2->bid();

$banner_data = load_banner_constants( $BID );

$sql = "select * from " . MDS_DB_PREFIX . "users where ID='" . intval( $_SESSION['MDS_ID'] ) . "'";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
$user_row = mysqli_fetch_array( $result );

// Entry point for completion of orders which are made by super users or if the order was for free
if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'complete' ) {

	// check if order is $0 & complete it

	if ( isset( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] == 'temp' ) {
		// convert the temp order to an order.

		$sql = "select * from " . MDS_DB_PREFIX . "temp_orders where session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], get_current_order_id() ) . "' ";
		$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		if ( mysqli_num_rows( $order_result ) == 0 ) { // no order id found...

			if ( USE_AJAX == 'SIMPLE' ) {
				$order_page = 'order_pixels.php';
			} else {
				$order_page = 'select.php';
			}
			?>
            <h1><?php echo $label['no_order_in_progress']; ?></h1>
            <p><?php $label['no_order_in_progress_go_here'] = str_replace( '%ORDER_PAGE%', $order_page, $label['no_order_in_progress_go_here'] );
				echo $label['no_order_in_progress_go_here']; ?></p>
			<?php
			require_once BASE_PATH . "/html/footer.php";
			die();
		} else if ( $order_row = mysqli_fetch_array( $order_result ) ) {

			$_REQUEST['order_id'] = reserve_pixels_for_temp_order( $order_row );
		} else {

			?>
            <h1><?php echo $label['sorry_head']; ?></h1>
            <p><?php
				if ( USE_AJAX == 'SIMPLE' ) {
					$order_page = 'order_pixels.php';
				} else {
					$order_page = 'select.php';
				}
				$label['sorry_head2'] = str_replace( '%ORDER_PAGE%', $order_page, $label['sorry_head2'] );
				echo $label['sorry_head2']; ?></p>
			<?php
			require_once BASE_PATH . "/html/footer.php";
			die();
		}
	}

	$sql = "select * from " . MDS_DB_PREFIX . "orders where order_id='" . intval( $_REQUEST['order_id'] ) . "' AND user_id='" . intval( $_SESSION['MDS_ID'] ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );
	if ( ( $row['price'] == 0 ) || ( $user_row['Rank'] == 2 ) ) {
		complete_order( $row['user_id'], $row['order_id'] );
		// no transaction for this order
		echo "<h3>" . $label['advertiser_publish_free_order'] . "</h3>";
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

$sql = "select DISTINCT " . MDS_DB_PREFIX . "banners.banner_id, " . MDS_DB_PREFIX . "banners.name, " . MDS_DB_PREFIX . "banners.enabled FROM " . MDS_DB_PREFIX . "orders, " . MDS_DB_PREFIX . "banners where " . MDS_DB_PREFIX . "orders.banner_id=" . MDS_DB_PREFIX . "banners.banner_id  AND user_id=" . intval( $_SESSION['MDS_ID'] ) . " and (" . MDS_DB_PREFIX . "orders.status='completed' or " . MDS_DB_PREFIX . "orders.status='expired') group by " . MDS_DB_PREFIX . "orders.banner_id, " . MDS_DB_PREFIX . "orders.order_id, " . MDS_DB_PREFIX . "banners.banner_id order by `name`";

$res = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );

if ( mysqli_num_rows( $res ) > 1 ) {
	?>
    <div class="fancy_heading" width="85%"><?php echo $label['advertiser_publish_pixinv_head']; ?></div>

	<?php
	$label['advertiser_publish_select_init2'] = str_replace( "%GRID_COUNT%", mysqli_num_rows( $res ), $label['advertiser_publish_select_init2'] );
	echo $label['advertiser_publish_select_init2'];
	echo '<br />';
	display_banner_selecton_form( $BID, $_SESSION['MDS_order_id'], $res );
}

// A block was clicked. Fetch the ad_id and initialize $_REQUEST['ad_id']
// If no ad exists for this block, create it.

if ( isset( $_REQUEST['block_id'] ) && ! empty( $_REQUEST['block_id'] ) ) {

	global $ad_tag_to_field_id;

	$sql = "SELECT user_id, ad_id, order_id FROM " . MDS_DB_PREFIX . "blocks where banner_id='$BID' AND block_id='" . intval( $_REQUEST['block_id'] ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$blk_row = mysqli_fetch_array( $result );

	if ( ! isset( $blk_row['ad_id'] ) || empty( $blk_row['ad_id'] ) ) { // no ad exists, create a new ad_id
		$_POST[ $ad_tag_to_field_id['URL']['field_id'] ]      = '';
		$_POST[ $ad_tag_to_field_id['ALT_TEXT']['field_id'] ] = 'ad text';
		$_REQUEST['order_id']                                 = $blk_row['order_id'];
		$_REQUEST['BID']                                      = $BID;
		$_REQUEST['user_id']                                  = $_SESSION['MDS_ID'];
		$_REQUEST['aid']                                      = "";
		$ad_id                                                = insert_ad_data();

		$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET ad_id='" . intval( $ad_id ) . "' WHERE order_id='" . intval( $blk_row['order_id'] ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		$sql = "UPDATE " . MDS_DB_PREFIX . "blocks SET ad_id='" . intval( $ad_id ) . "' WHERE order_id='" . intval( $blk_row['order_id'] ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

		$_REQUEST['aid'] = $ad_id;
	} else {
		// initialize $_REQUEST['ad_id']

		// make sure the ad exists..

		$sql = "select * from " . MDS_DB_PREFIX . "ads where ad_id='" . intval( $blk_row['ad_id'] ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		//echo $sql;
		if ( mysqli_num_rows( $result ) == 0 ) {
			echo "No ad exists..";
			$_POST[ $ad_tag_to_field_id['URL']['field_id'] ]      = '';
			$_POST[ $ad_tag_to_field_id['ALT_TEXT']['field_id'] ] = 'ad text';
			$_REQUEST['order_id']                                 = $blk_row['order_id'];
			$_REQUEST['BID']                                      = $BID;
			$_REQUEST['user_id']                                  = $_SESSION['MDS_ID'];
			$ad_id                                                = insert_ad_data();

			$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET ad_id='" . intval( $ad_id ) . "' WHERE order_id='" . intval( $blk_row['order_id'] ) . "' ";
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
			$sql = "UPDATE " . MDS_DB_PREFIX . "blocks SET ad_id='" . intval( $ad_id ) . "' WHERE order_id='" . intval( $blk_row['order_id'] ) . "' ";
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

			$_REQUEST['aid'] = $ad_id;
		} else {

			$_REQUEST['aid'] = $blk_row['ad_id'];
		}

		// bug in previous versions resulted in saving the ad's user_id with a session_id
		// fix user_id here
		$sql = "UPDATE " . MDS_DB_PREFIX . "ads SET user_id='" . intval( $blk_row['user_id'] ) . "' WHERE order_id='" . intval( $blk_row['order_id'] ) . "' AND user_id <> '" . intval( $_SESSION['MDS_ID'] ) . "' limit 1 ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	}
}

// Display ad editing forms if the ad was clicked, or 'Edit' button was pressed.
if ( isset( $_REQUEST['aid'] ) && ! empty( $_REQUEST['aid'] ) ) {
	$sql = "SELECT * from " . MDS_DB_PREFIX . "ads as t1, " . MDS_DB_PREFIX . "orders as t2 where t1.ad_id=t2.ad_id AND t1.user_id=" . intval( $_SESSION['MDS_ID'] ) . " and t1.banner_id=" . intval( $BID ) . " and t1.ad_id=" . intval( $_REQUEST['aid'] ) . " AND t1.order_id=t2.order_id ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	$row      = mysqli_fetch_array( $result );
	$order_id = $row['order_id'];
	$blocks   = explode( ',', $row['blocks'] );

	$size   = get_pixel_image_size( $row['order_id'] );
	$pixels = $size['x'] * $size['y'];
	upload_changed_pixels( $order_id, $BID, $size, $banner_data );

	// Ad forms:
	?>
    <div class="fancy_heading" width="85%"><?php echo $label['adv_pub_editad_head']; ?></div>
    <p><?php echo $label['adv_pub_editad_desc']; ?> </p>
    <p><b><?php echo $label['adv_pub_yourpix']; ?></b></p>
    <table border=0 bgcolor='#d9d9d9' cellspacing="1" cellpadding="5">
        <tr bgcolor="#ffffff">
            <td valign="top"><b><?php echo $label['adv_pub_piximg']; ?></b><br>
                <center>
					<?php
					if ( isset( $_REQUEST['aid'] ) && ! empty( $_REQUEST['aid'] ) ) {
						?><img src="get_order_image.php?BID=<?php echo $BID; ?>&aid=<?php echo $_REQUEST['aid']; ?>" border=1><?php
					} else {
						?><img src="get_order_image.php?BID=<?php echo $BID; ?>&block_id=<?php echo $_REQUEST['block_id']; ?>" border=1><?php
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
                <form name="change" enctype="multipart/form-data" method="post">
                    <input type="file" name='pixels'><br>
                    <input type="hidden" name="aid" value="<?php echo $_REQUEST['aid']; ?>">
                    <input type="submit" name="change_pixels" value="<?php echo $label['adv_pub_pixupload']; ?>">
                </form>
                <font size='1'><?php echo $label['advertiser_publish_supp_formats']; ?><?php echo "$gif_support $jpeg_support $png_support"; ?></font>
            </td>
        </tr>
    </table>

    <p><b><?php echo $label['adv_pub_edityourad']; ?></b></p>
	<?php

	if ( isset( $_REQUEST['save'] ) && ! empty( $_REQUEST['save'] ) ) { // saving

		$error = validate_ad_data( 1 );
		if ( $error != '' ) { // we have an error
			$mode = "user";
			//display_ad_intro();
			display_ad_form( 1, $mode, '' );
		} else {

			$ad_id = insert_ad_data();
			update_blocks_with_ad( $ad_id, $_SESSION['MDS_ID'] );

			global $prams;
			$prams = load_ad_values( $ad_id );

			?>
            <div class='ok_msg_label'><?php echo $label['adv_pub_adsaved']; ?></div>
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
			if ( EMAIL_ADMIN_PUBLISH_NOTIFY == 'YES' ) {
				send_published_pixels_notification( $_SESSION['MDS_ID'], $BID );
			}
		}
	} else {

		$prams = load_ad_values( $_REQUEST['aid'] );
		display_ad_form( 1, 'user', $prams );
	}
} # end of ad forms

$offset = isset( $_REQUEST['offset'] ) ? intval( $_REQUEST['offset'] ) : 0;

# List Ads
ob_start();
$count    = list_ads( false, $offset, 'USER' );
$contents = ob_get_contents();
ob_end_clean();

if ( $count > 0 ) {
	?>
    <div class="fancy_heading"><?php echo $label['adv_pub_yourads']; ?></div>
	<?php
	echo $contents;
	?>
	<?php
}

?>
    <div class="fancy_heading"><?php echo $label['advertiser_publish_head']; ?></div>
<?php echo $label['advertiser_publish_instructions2']; ?>

<?php

// inform the user about the approval status of the images.

$sql = "select * from " . MDS_DB_PREFIX . "orders where user_id='" . intval( $_SESSION['MDS_ID'] ) . "' AND status='completed' and  approved='N' and banner_id='" . intval( $BID ) . "' ";
$result4 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

if ( mysqli_num_rows( $result4 ) > 0 ) {
	?>
    <div style="border-color:#FF9797; border-style:solid;padding:5px;"><?php echo $label['advertiser_publish_pixwait']; ?></div>
	<?php
} else {

	$sql = "select * from " . MDS_DB_PREFIX . "orders where user_id='" . intval( $_SESSION['MDS_ID'] ) . "' AND status='completed' and  approved='Y' and published='Y' and banner_id='" . intval( $BID ) . "' ";
	//echo $sql;
	$result4 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	if ( mysqli_num_rows( $result4 ) > 0 ) {
		?>
        <div style="border-color:green;border-style:solid;padding:5px;margin:10px;"><?php echo $label['advertiser_publish_published']; ?></div>
		<?php
	} else {

		$sql = "select * from " . MDS_DB_PREFIX . "orders where user_id='" . intval( $_SESSION['MDS_ID'] ) . "' AND status='completed' and  approved='Y' and published='N' and banner_id='" . intval( $BID ) . "' ";

		$result4 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

		if ( mysqli_num_rows( $result4 ) > 0 ) {
			?>
            <div style="border-color:yellow;border-style:solid;padding:5px;"><?php echo $label['advertiser_publish_waiting']; ?></div>
			<?php
		}
	}
}

// Generate the Area map form the current sold blocks.
$sql = "SELECT * FROM " . MDS_DB_PREFIX . "blocks WHERE user_id='" . intval( $_SESSION['MDS_ID'] ) . "' AND status='sold' and banner_id='" . intval( $BID ) . "' ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
?>
    <div class="publish-grid">
        <map name="main" id="main">
			<?php
			while ( $row = mysqli_fetch_array( $result ) ) {
				?>
                <area shape="RECT" coords="<?php echo $row['x']; ?>,<?php echo $row['y']; ?>,<?php echo $row['x'] + $banner_data['BLK_WIDTH']; ?>,<?php echo $row['y'] + $banner_data['BLK_HEIGHT']; ?>" href="publish.php?BID=<?php echo $BID; ?>&amp;block_id=<?php echo( $row['block_id'] ); ?>" title="<?php echo( $row['alt_text'] ); ?>" alt="<?php echo( $row['alt_text'] ); ?>"/>
				<?php
			}
			?>
        </map>
        <img id="publish-grid" src="show_map.php?BID=<?php echo $BID; ?>&amp;time=<?php echo( time() ); ?>" width="<?php echo( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ); ?>" height="<?php echo( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ); ?>" border="0" usemap="#main"/>
    </div>
<?php
require_once BASE_PATH . "/html/footer.php";
