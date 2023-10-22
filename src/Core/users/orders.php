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

use MillionDollarScript\Classes\Language;
use MillionDollarScript\Classes\Utility;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

if ( \MillionDollarScript\Classes\Config::get('DISPLAY_ORDER_HISTORY') !== "YES" ) {
	exit;
}

// check if user has permission to access this page
if ( ! mds_check_permission( "mds_order_history" ) ) {
	require_once MDS_CORE_PATH . "html/header.php";
	Language::out( "No Access" );
	require_once MDS_CORE_PATH . "html/footer.php";
	exit;
}

require_once MDS_CORE_PATH . "html/header.php";

global $wpdb;

if ( isset( $_REQUEST['cancel'] ) && $_REQUEST['cancel'] == 'yes' && isset( $_REQUEST['order_id'] ) ) {
	if ( $_REQUEST['order_id'] == get_current_order_id() ) {

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE user_id='" . get_current_user_id() . "' AND order_id='" . intval(get_current_order_id() ) . "'";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		if ( mysqli_num_rows( $result ) > 0 ) {
			$row = mysqli_fetch_assoc( $result );

			// delete associated ad
			wp_delete_post(intval( $row['ad_id'] ));

			// delete associated temp order
			$sql = "DELETE FROM " . MDS_DB_PREFIX . "orders WHERE order_id='" . intval( get_current_order_id() ) . "'";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

			// delete associated uploaded image
			$imagefile = get_tmp_img_name();
			if ( file_exists( $imagefile ) ) {
				unlink( $imagefile );
			}

			// if deleted order is the current order unset current order id
			if ( $_REQUEST['order_id'] == get_current_order_id() ) {
				delete_current_order_id();
			}
		}
	} else {
		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE user_id='" . get_current_user_id() . "' AND order_id='" . intval( $_REQUEST['order_id'] ) . "'";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		if ( mysqli_num_rows( $result ) > 0 ) {
			delete_order( intval( $_REQUEST['order_id'] ) );

			// if deleted order is the current order unset current order id
			if ( $_REQUEST['order_id'] == get_current_order_id() ) {
				delete_current_order_id();
			}
		}
	}
}

?>

    <script>

		function confirmLink(theLink, theConfirmMsg) {

			if (theConfirmMsg === '') {
				return true;
			}

			var is_confirmed = confirm(theConfirmMsg + '\n');
			if (is_confirmed) {
				theLink.href += '&is_js_confirmed=1';
			}

			return is_confirmed;
		} // end of the 'confirmLink()' function

    </script>

    <div class="fancy-heading"><?php Language::out( 'Order History' ); ?></div>

<?php

$orders = array();

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders AS t1, " . $wpdb->prefix . "users AS t2 WHERE t1.user_id=t2.ID AND t1.user_id='" . get_current_user_id() . "' ORDER BY t1.order_date DESC ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
while ( $row = mysqli_fetch_array( $result ) ) {
	$orders[] = $row;
}

// Sort by order_date from both orders and temp_orders
function date_sort( $a, $b ): int {
	$dateA = strtotime( $a['order_date'] );
	$dateB = strtotime( $b['order_date'] );

	if ($dateA == $dateB) {
		return 0;
	} elseif ($dateA < $dateB) {
		return -1;
	} else {
		return 1;
	}
}

usort( $orders, "date_sort" );

?>

    <table class="mds-order-history">
        <thead>
        <tr>
            <td><?php Language::out( 'Order Date' ); ?></td>
            <td><?php Language::out( 'OrderID' ); ?></td>
            <td><?php Language::out( 'Quantity' ) ?></td>
            <td><?php Language::out( 'Grid' ); ?></td>
            <td><?php Language::out( 'Amount' ); ?></td>
            <td><?php Language::out( 'Status' ); ?></td>
        </tr>
        </thead>
		<?php

		if ( count( $orders ) == 0 ) {
			echo '<td colspan="7">' . Language::get( 'No orders found.' ) . ' </td>';
		} else {

			foreach ( $orders as $order ) {
				?>
                <tr>
                <td><?php echo get_date_from_gmt( $order['order_date'] ); ?></td>
                <td><?php echo isset( $order['order_id'] ) ? '#' . $order['order_id'] : Language::get( 'In progress' ); ?></td>
                <td><?php echo $order['quantity']; ?></td>
                <td><?php

					$sql = "select * from " . MDS_DB_PREFIX . "banners where banner_id=" . intval( $order['banner_id'] );
					$b_result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
					$b_row = mysqli_fetch_array( $b_result );

					if ( $b_row ) {
						echo $b_row['name'];
					}

					?></td>
                <td><?php echo convert_to_default_currency_formatted( $order['currency'], $order['price'] ); ?></td>
                <td><?php
				if ( isset( $order['status'] ) ) {
					switch ( $order['status'] ) {
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
						case 'deleted':
							Language::out( 'Deleted' );
							break;
						case 'renew_wait':
							Language::out( 'Renew Now' );
							break;
						case 'renew_paid':
							Language::out( 'Renewal Paid' );
							break;
						default:
							break;
					}
					echo "<br />";

					$temp_var = '';
					if ( \MillionDollarScript\Classes\Config::get('USE_AJAX') == 'SIMPLE' ) {
						$temp_var = '&order_id=' . $order['order_id'];
					}

					switch ( $order['status'] ) {
						case "new":
							echo Language::get( 'In progress' ) . '<br>';
							echo "<a class='mds-button mds-complete' href='" . Utility::get_page_url( 'confirm-order' ) . "?BID=" . $order['banner_id'] . "$temp_var'>" . Language::get( 'Confirm Now' ) . "</a>";
							echo "<br><input class='mds-button mds-cancel' type='button' value='" . esc_attr( Language::get( 'Cancel' ) ) . "' onclick='if (!confirmLink(this, \"" . Language::get( 'Cancel, are you sure?' ) . "\")) return false; window.location=\"orders.php?cancel=yes&order_id=" . $order['order_id'] . "\"' >";
							break;
						case "confirmed":
							echo "<a class='mds-button mds-complete' href='" . Utility::get_page_url( 'payment' ) . "?order_id=" . $order['order_id'] . "&BID=" . $order['banner_id'] . "'>" . Language::get( 'Pay Now' ) . "</a>";
							break;
						case "completed":
							echo "<a class='mds-button mds-complete' href='" . Utility::get_page_url( 'manage' ) . "?order_id=" . $order['order_id'] . "&BID=" . $order['banner_id'] . "'>" . Language::get( 'Manage' ) . "</a>";

							if ( $order['days_expire'] > 0 ) {

								if ( $order['published'] != 'Y' ) {
									$time_start = strtotime( gmdate( 'r' ) );
								} else {
									$time_start = strtotime( $order['date_published'] . " GMT" );
								}

								$elapsed_time = strtotime( gmdate( 'r' ) ) - $time_start;
								$elapsed_days = floor( $elapsed_time / 60 / 60 );

								$exp_time = ( $order['days_expire'] * 60 * 60 );

								$exp_time_to_go = $exp_time - $elapsed_time;
								$exp_days_to_go = floor( $exp_time_to_go / 60 / 60 );

								$to_go = elapsedtime( $exp_time_to_go );

								$elapsed = elapsedtime( $elapsed_time );

								if ( $order['date_published'] != '' ) {
									echo "<br>Expires in: " . $to_go;
								}
							}

							break;
						case "expired":

							$time_expired = strtotime( $order['date_stamp'] );

							$time_when_cancel = $time_expired + ( \MillionDollarScript\Classes\Config::get('MINUTES_RENEW') * 60 );

							$days = floor( ( $time_when_cancel - time() ) / 60 );

							// check to see if there is a renew_wait or renew_paid order

							$sql   = "select order_id from " . MDS_DB_PREFIX . "orders where (status = 'renew_paid' OR status = 'renew_wait') AND original_order_id='" . intval( $order['original_order_id'] ) . "' ";
							$res_c = mysqli_query( $GLOBALS['connection'], $sql );
							if ( mysqli_num_rows( $res_c ) == 0 ) {
								echo "<a class='mds-button mds-order-renew' href='" . Utility::get_page_url( 'payment' ) . "?order_id=" . $order['order_id'] . "&BID=" . $order['banner_id'] . "'>" . Language::get_replace( 'Renew Now! %DAYS_TO_RENEW% days left to renew', '%DAYS_TO_RENEW%', $days ) . "</a>";
							}
							break;
						case "pending":
						case "cancelled":
							break;
					}
				} else {
					$temp_var = '&order_id=' . $order['order_id'];
					echo Language::get( 'In progress' ) . '<br>';
					echo "<a href='" . Utility::get_page_url( 'order' ) . "?BID={$order['banner_id']}{$temp_var}'>" . Language::get( 'Confirm now' ) . "</a>";
					echo "<br><input class='mds-button mds-cancel' type='button' value='" . esc_attr( Language::get( 'Cancel' ) ) . "' onclick='if (!confirmLink(this, \"" . Language::get( 'Cancel, are you sure?' ) . "}\")) return false; window.location=\"orders.php?cancel=yes{$temp_var}\"' >";
				}
			}
			?></td>
            </tr>

			<?php
		}
		?>

    </table>

    <div class="mds-order-explain">
		<?php
		Language::out( '"Completed" Orders - Orders where the transaction was successfully completed.<br />
"Confirmed" Orders - Orders confirmed by you, but the transaction has not been completed.<br />
"Pending" Orders - Orders confirmed by you, but the transaction has not been approved.<br />
"Cancelled" Orders - Cancelled by the administrator.<br />
"Expired" Orders - Pixels were expired after the specified term. You can renew this order.<br />' );
		?>
    </div>

<?php

require_once MDS_CORE_PATH . "html/footer.php";

?>