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

use MillionDollarScript\Classes\Config;
use MillionDollarScript\Classes\Language;
use MillionDollarScript\Classes\Utility;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

require_once( __DIR__ . "/../include/ads.inc.php" );

global $f2;

$BID = $f2->bid();

$advanced_order = Config::get( 'USE_AJAX' ) == 'YES';

function display_edit_order_button( $order_id ) {
	global $BID;
	?>
    <input type='button' class='mds-button mds-edit' value="<?php echo esc_attr( Language::get( 'Edit Order' ) ); ?>" onclick="window.location='<?php echo Utility::get_page_url( 'order' ); ?>?&amp;BID=<?php echo $BID; ?>&amp;order_id=<?php echo $order_id; ?>'">
	<?php
}

update_temp_order_timestamp();

$sql = "select * from " . MDS_DB_PREFIX . "orders where order_id='" . mysqli_real_escape_string( $GLOBALS['connection'], get_current_order_id() ) . "' ";
$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

// check if we have pixels...
if ( mysqli_num_rows( $order_result ) == 0 ) {
	\MillionDollarScript\Classes\Utility::redirect( Utility::get_page_url( 'no-orders' ) );
}

$order_row = mysqli_fetch_array( $order_result );

// get the banner ID
global $BID;
$BID = $order_row['banner_id'];

$banner_data = load_banner_constants( $BID );

/* Login -> Select pixels -> Write ad -> Confirm order */
if ( ! is_user_logged_in() ) {
	mds_wp_login_check();
} else {
	// The user is singed in

	$has_packages = banner_get_packages( $BID );

	require_once MDS_CORE_PATH . "html/header.php";

	show_nav_status( 3 );

	$cannot_get_package = false;

	if ( $has_packages && isset( $_REQUEST['pack'] ) && $_REQUEST['pack'] != '' ) {
		// has packages, and a package was selected...

		// check to make sure this advertiser can order this package

		if ( can_user_get_package( get_current_user_id(), $_REQUEST['pack'] ) ) {

			$sql = "SELECT quantity FROM " . MDS_DB_PREFIX . "orders WHERE order_id='" . mysqli_real_escape_string( $GLOBALS['connection'], get_current_order_id() ) . "'";
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
			$row      = mysqli_fetch_array( $result );
			$quantity = $row['quantity'];

			$block_count = $quantity / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

			// Now update the order (overwrite the total & days_expire with the package)
			$pack  = get_package( $_REQUEST['pack'] );
			$total = $pack['price'] * $block_count;

			// convert & round off
			$total = convert_to_default_currency( $pack['currency'], $total );

			$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET package_id='" . intval( $_REQUEST['pack'] ) . "', price='" . floatval( $total ) . "',  days_expire='" . intval( $pack['days_expire'] ) . "', currency='" . mysqli_real_escape_string( $GLOBALS['connection'], get_default_currency() ) . "' WHERE order_id='" . mysqli_real_escape_string( $GLOBALS['connection'], get_current_order_id() ) . "'";
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
	if ( ( $has_packages ) && ( isset( $_REQUEST['pack'] ) && $_REQUEST['pack'] == '' ) ) {
		?>
        <form method="post" name="confirm-order" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'mds-form' ); ?>
            <input type="hidden" name="action" value="mds_form_submission">
            <input type="hidden" name="mds_dest" value="confirm-order">
            <input type="hidden" name="selected_pixels" value="<?php echo htmlspecialchars( $_REQUEST['selected_pixels'] ); ?>">
            <input type="hidden" name="order_id" value="<?php echo intval( $_REQUEST['order_id'] ); ?>">
            <input type="hidden" name="BID" value="<?php echo $BID; ?>">
			<?php
			display_package_options_table( $BID, $_REQUEST['pack'], true );
			?>
            <input class='big_button' type='button' value='<?php echo esc_attr( Language::get( '<< Previous' ) ); ?>' onclick='window.location="<?php echo esc_url( Utility::get_page_url( 'write-ad' ) ); ?>?BID=<?php echo intval( $BID ); ?>&amp;aid=<?php echo intval( $order_row['ad_id'] ); ?>"'>
            <input class='big_button' type='submit' value='<?php echo esc_attr( Language::get( 'Next >>' ) ); ?>'>
        </form>
		<?php
		if ( $cannot_get_package ) {

			$sql = "SELECT * from " . MDS_DB_PREFIX . "packages where package_id='" . intval( $selected_pack ) . "'";
			$p_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
			$p_row     = mysqli_fetch_array( $p_result );
			$p_max_ord = $p_row['max_orders'];

			Language::out_replace( '<p><span style="color:red">Error: Cannot place order. This price option is limited to %MAX_ORDERS% per customer.</span><br/>Please select another option, or check your <a href="%HISTORY_URL%">Order History.</a></p>', [ '%MAX_ORDERS%', '%HISTORY_URL%' ], [ $p_row['max_orders'], Utility::get_page_url( 'history' ) ] );
		}
	} else {
		display_order( get_current_order_id(), $BID );

		?>
        <div class="mds-button-container">
		<?php

		display_edit_order_button( get_current_order_id() );

		if ( ! can_user_order( $banner_data, get_current_user_id(), ( $_REQUEST['pack'] ?? 0 ) ) ) {
			// one more check before continue

			if ( ! $p_max_ord ) {
				$max = $banner_data['G_MAX_ORDERS'];
			} else {
				$max = $p_max_ord;
			}

			Language::out_replace( '<p><span style="color:red">Error: Cannot place order. This price option is limited to %MAX_ORDERS% per customer.</span><br/>Please select another option, or check your <a href="%HISTORY_URL%">Order History.</a></p>', [ '%MAX_ORDERS%', '%HISTORY_URL%' ], [ $max, Utility::get_page_url( 'history' ) ] );
		} else {
			$privileged = carbon_get_user_meta( get_current_user_id(), 'privileged' );

			if ( \MillionDollarScript\Classes\Options::get_option( 'confirm-orders' ) == 'yes' ) {
				// Confirm order page buttons
				if ( ( $order_row['price'] == 0 ) || ( $privileged == '1' ) ) {
					// go straight to publish...
					if ( $advanced_order ) {
						?>
                        <input type='button' class='mds-button mds-complete' value="<?php echo esc_attr( Language::get( 'Complete Order' ) ); ?>" onclick="window.location='<?php echo esc_url( Utility::get_page_url( 'manage' ) ); ?>?mds-action=complete&order_id=<?php echo intval( $order_row['order_id'] ); ?>&BID=<?php echo $BID; ?>&order_id=<?php echo intval( $order_row['order_id'] ); ?>'">
						<?php
					} else {
						?>
                        <input type='button' class='mds-button mds-complete' value="<?php echo esc_attr( Language::get( 'Complete Order' ) ); ?>" onclick="window.location='<?php echo esc_url( Utility::get_page_url( 'manage' ) ); ?>?mds-action=complete&BID=<?php echo $BID; ?>&order_id=<?php echo get_current_order_id(); ?>'">
						<?php
					}
				} else {
					// go to payment
					if ( $advanced_order ) {
						?>
                        <input type='button' class='mds-button mds-complete' value="<?php echo esc_attr( Language::get( 'Confirm & Pay' ) ); ?>" onclick="window.location='<?php echo esc_url( Utility::get_page_url( 'payment' ) ); ?>?mds-action=confirm&order_id=<?php echo intval( $order_row['order_id'] ); ?>&BID=<?php echo $BID; ?>'">
						<?php
					} else {
						?>
                        <input type='button' class='mds-button mds-complete' value="<?php echo esc_attr( Language::get( 'Confirm & Pay' ) ); ?>" onclick="window.location='<?php echo esc_url( Utility::get_page_url( 'checkout' ) ); ?>?mds-action=confirm&BID=<?php echo $BID; ?>'">
						<?php
					}
				}
				?>
                </div>
				<?php
			} else {
				if ( ( $order_row['price'] == 0 ) || ( $privileged == '1' ) ) {
					// go straight to publish...
					if ( $advanced_order ) {
						wp_safe_redirect( Utility::get_page_url( 'manage' ) . '?mds-action=complete&BID=' . $BID . '&order_id=' . $order_row['order_id'] );
					} else {
						wp_safe_redirect( Utility::get_page_url( 'manage' ) . '?mds-action=complete&BID=' . $BID . '&order_id=' . get_current_order_id() );
					}
				} else {
					// go to payment
					if ( $advanced_order ) {
						wp_safe_redirect( Utility::get_page_url( 'payment' ) . '?mds-action=confirm&order_id=' . $order_row['order_id'] . '&BID=' . $BID );
					} else {
						wp_safe_redirect( Utility::get_page_url( 'checkout' ) . '?mds-action=confirm&BID=' . $BID . '&order_id=' . get_current_order_id() );
					}
				}
				exit;
			}
		}
		?>
		<?php
	}
}

require_once MDS_CORE_PATH . "html/footer.php";
