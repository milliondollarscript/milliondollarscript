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

require_once __DIR__ . "/../include/login_functions.php";
mds_start_session();
require_once __DIR__ . "/../include/init.php";

require_once( "../include/ads.inc.php" );

global $order_page, $label;
if ( USE_AJAX == 'SIMPLE' ) {
	$order_page = 'order_pixels.php';
} else {
	$order_page = 'select.php';
}

function display_edit_order_button( $order_id ) {
	global $BID, $label, $order_page;
	?>
    <input type='button' class='big_button' value="<?php echo $label['advertiser_o_edit_button']; ?>" onclick="window.location='<?php echo $order_page; ?>?&amp;BID=<?php echo $BID; ?>&amp;order_id=<?php echo $order_id; ?>'">
	<?php
}

update_temp_order_timestamp();

$sql = "select * from " . MDS_DB_PREFIX . "temp_orders where session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], get_current_order_id() ) . "' ";
$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

// check if we have pixels...
if ( mysqli_num_rows( $order_result ) == 0 ) {
	require_once BASE_PATH . "/html/header.php";
	?>
    <h1><?php echo $label['no_order_in_progress']; ?></h1>
    <p><?php $label['no_order_in_progress_go_here'] = str_replace( '%ORDER_PAGE%', $order_page, $label['no_order_in_progress_go_here'] );
		echo $label['no_order_in_progress_go_here']; ?></p>

	<?php
	require_once BASE_PATH . "/html/footer.php";
	die();
}

$order_row = mysqli_fetch_array( $order_result );

// get the banner ID
$BID = $order_row['banner_id'];

$banner_data = load_banner_constants( $BID );

/* Login -> Select pixels -> Write ad -> Confirm order */
if ( $_SESSION['MDS_ID'] == '' ) {
	if ( WP_ENABLED == "YES" && WP_USERS_ENABLED == "YES" ) {
        mds_wp_login_check();
    }

	// not logged in..
	require_once BASE_PATH . "/html/header.php";

	?>
    <h3>
		<?php echo $label['not_logged_in']; ?>
    </h3>
    <table cellpadding=5 border=1 style="border-collapse: collapse; border-style:solid; border-color:#D2D2D2">
        <tr>
            <td width="50%" bgcolor='#EBEBEB'>

				<?php

				// process signup form
				if ( $_REQUEST['form'] == "filled" ) {
					$success = process_signup_form( 'confirm_order.php' );
				}

				if ( ! $success ) {
					// Signup form is shown below

					?>
                    <h2><?php echo $label['conirm_signup']; ?></h2>
                    <h3><?php echo $label['confirm_instructions']; ?></h3>
					<?php

					display_signup_form( $_REQUEST['FirstName'], $_REQUEST['LastName'], $_REQUEST['CompName'], $_REQUEST['Username'], $_REQUEST['Password'], $_REQUEST['Password2'], $_REQUEST['Email'] );
				}
				?>
            </td>
            <td valign=top>
                <h2><?php echo $label['confirm_login']; ?></h2>
                <h3><?php echo $label['confirm_member']; ?></h3>
				<?php login_form( false, 'confirm_order.php' ); ?>
            </td>
        </tr>
    </table>
	<?php
} else {
	// The user is singed in

	$has_packages = banner_get_packages( $BID );

	require_once BASE_PATH . "/html/header.php";

	?>
    <p>
		<?php
		show_nav_status( 3 );
		?>
    </p>
	<?php

	$cannot_get_package = false;

	if ( $has_packages && $_REQUEST['pack'] != '' ) {
		// has packages, and a package was selected...

		// check to make sure this advertiser can order this package

		if ( can_user_get_package( $_SESSION['MDS_ID'], $_REQUEST['pack'] ) ) {

			$sql = "SELECT quantity FROM " . MDS_DB_PREFIX . "temp_orders WHERE session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], get_current_order_id() ) . "'";
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
			$row      = mysqli_fetch_array( $result );
			$quantity = $row['quantity'];

			$block_count = $quantity / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

			// Now update the order (overwrite the total & days_expire with the package)
			$pack  = get_package( $_REQUEST['pack'] );
			$total = $pack['price'] * $block_count;

			// convert & round off
			$total = convert_to_default_currency( $pack['currency'], $total );

			$sql = "UPDATE " . MDS_DB_PREFIX . "temp_orders SET package_id='" . intval( $_REQUEST['pack'] ) . "', price='" . floatval( $total ) . "',  days_expire='" . intval( $pack['days_expire'] ) . "', currency='" . mysqli_real_escape_string( $GLOBALS['connection'], get_default_currency() ) . "' WHERE session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], get_current_order_id() ) . "'";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

			$order_row['price']       = $total;
			$order_row['pack']        = $_REQUEST['pack'];
			$order_row['days_expire'] = $pack['days_expire'];
			$order_row['currency']    = get_default_currency();
		} else {
			$selected_pack      = $_REQUEST['pack'];
			$_REQUEST['pack']   = '';
			$cannot_get_package = true;
		}
	}

	$p_max_ord = 0;
	if ( ( $has_packages ) && ( $_REQUEST['pack'] == '' ) ) {
		?>
        <form name="confirm-order" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <input type="hidden" name="selected_pixels" value="<?php echo htmlspecialchars($_REQUEST['selected_pixels']); ?>">
            <input type="hidden" name="order_id" value="<?php echo intval($_REQUEST['order_id']); ?>">
            <input type="hidden" name="BID" value="<?php echo $BID; ?>">
            <?php
            display_package_options_table( $BID, $_REQUEST['pack'], true );
            ?>
            <input class='big_button' type='button' value='<?php echo htmlspecialchars($label['advertiser_pack_prev_button']); ?>' onclick='window.location="write_ad.php?BID=<?php echo intval($BID); ?>&amp;ad_id=<?php echo intval($order_row['ad_id']); ?>"'>
            &nbsp; <input class='big_button' type='submit' value='<?php echo htmlspecialchars($label['advertiser_pack_select_button']); ?>'>
		<form>
		<?php
		if ( $cannot_get_package ) {

			$sql = "SELECT * from " . MDS_DB_PREFIX . "packages where package_id='" . intval( $selected_pack ) . "'";
			$p_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
			$p_row     = mysqli_fetch_array( $p_result );
			$p_max_ord = $p_row['max_orders'];

			$label['pack_cannot_select'] = str_replace( "%MAX_ORDERS%", $p_row['max_orders'], $label['pack_cannot_select'] );

			echo "<p>" . $label['pack_cannot_select'] . "</p>";
		}
	} else {

		display_order( get_current_order_id(), $BID );

		$sql = "select * from " . MDS_DB_PREFIX . "users where ID='" . intval( $_SESSION['MDS_ID'] ) . "'";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$u_row = mysqli_fetch_array( $result );

		display_edit_order_button( 'temp' );

		if ( ! can_user_order( $banner_data, $_SESSION['MDS_ID'], ( isset( $_REQUEST['pack'] ) ? $_REQUEST['pack'] : "" ) ) ) { // one more check before continue

			if ( ! $p_max_ord ) {
				$max = $banner_data['G_MAX_ORDERS'];
			} else {
				$max = $p_max_ord;
			}

			$label['pack_cannot_select'] = str_replace( "%MAX_ORDERS%", $max, $label['pack_cannot_select'] );

			echo "<p>" . $label['advertiser_max_order'] . "</p>";
		} else {

		    // TODO: Add option to auto forward to next page
			if ( ( $order_row['price'] == 0 ) || ( $u_row['Rank'] == 2 ) ) { // go straight to publish...
				?>
                <input type='button' class='big_button' value="<?php echo htmlspecialchars( $label['advertiser_o_completebutton'] ); ?>" onclick="window.location='publish.php?action=complete&BID=<?php echo $BID; ?>&order_id=temp'">
				<?php
			} else {
				// go to payment
				?>
                <input type='button' class='big_button' value="<?php echo htmlspecialchars( $label['advertiser_o_confpay_button'] ); ?>" onclick="window.location='checkout.php?action=confirm&BID=<?php echo $BID; ?>'">
				<?php
			}
		}
		?>
		<?php
	}
}

require_once BASE_PATH . "/html/footer.php";
