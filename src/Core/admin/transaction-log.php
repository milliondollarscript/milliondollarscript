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

use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\Payment\Currency;
use MillionDollarScript\Classes\Payment\Payment;
use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

global $f2, $wpdb;
$BID = $f2->bid();

$bid_sql = " AND banner_id=$BID ";

if ( ( $BID == 'all' ) || ( $BID == '' ) ) {
	$BID     = '';
	$bid_sql = "  ";
}

/*
// Old refund logic - Replaced by WooCommerce integration
if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'refund' ) {

	$t_id = $_REQUEST['transaction_id'];

	$sql = $wpdb->prepare(
		"SELECT * FROM " . MDS_DB_PREFIX . "transactions
    INNER JOIN " . MDS_DB_PREFIX . "orders ON " . MDS_DB_PREFIX . "transactions.order_id = " . MDS_DB_PREFIX . "orders.order_id
    INNER JOIN " . $wpdb->prefix . "users ON " . MDS_DB_PREFIX . "orders.user_id = " . $wpdb->prefix . "users.ID
    WHERE " . MDS_DB_PREFIX . "transactions.transaction_id = %d",
		intval( $t_id )
	);

	$row = $wpdb->get_row( $sql, ARRAY_A );

	if ( $row['status'] != 'completed' ) {
		// check that there's no other refund...
		$sql = $wpdb->prepare(
			"SELECT * FROM " . MDS_DB_PREFIX . "transactions WHERE txn_id = %s AND type = 'CREDIT'",
			$row['txn_id']
		);
		$r   = $wpdb->get_results( $sql, ARRAY_A );
		if ( count( $r ) == 0 ) {
			// do the refund
			Orders::cancel_order( $row['order_id'] );
			Payment::credit_transaction( $row['order_id'], $row['price'], $row['currency'], $row['txn_id'], 'Refund', 'Admin' );
		} else {

			echo "<b>Error: A refund was already found on this system for this order..</b><br>";
		}
	} else {
		echo "<b>Error: The system can only refund orders that are completed, please cancel the order first</b><br>";
	}
	// can only refund completed orders..

}
*/

?>
<p>
    The transaction log helps you manage the money transfers. Note: Refunds are processed through the payment gateway that was used.
</p>
<?php

// calculate the balance
$sql = "SELECT SUM(amount) as mysum, type, currency from " . MDS_DB_PREFIX . "transactions group by type, currency";

$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
$debits = $credits = 0;
while ( $row = mysqli_fetch_array( $result ) ) {

	if ( $row['type'] == 'CREDIT' ) {
		$credits = $credits + Currency::convert_to_default_currency( $row['currency'], $row['mysum'] );
	}

	if ( $row['type'] == 'DEBIT' ) {
		$debits = $debits + Currency::convert_to_default_currency( $row['currency'], $row['mysum'] );
	}
}

$bal = $debits - $credits;

$local_date = current_time( 'mysql' );
$local_time = current_time( 'timestamp' );

if ( ! isset( $_REQUEST['from_day'] ) || $_REQUEST['from_day'] == '' ) {
	$_REQUEST['from_day'] = "1";
}
if ( ! isset( $_REQUEST['from_month'] ) || $_REQUEST['from_month'] == '' ) {
	$_REQUEST['from_month'] = date( "m", $local_time );
}
if ( ! isset( $_REQUEST['from_year'] ) || $_REQUEST['from_year'] == '' ) {
	$_REQUEST['from_year'] = date( 'Y', $local_time );
}

if ( ! isset( $_REQUEST['to_day'] ) || $_REQUEST['to_day'] == '' ) {
	$_REQUEST['to_day'] = date( 'd', $local_time );
}
if ( ! isset( $_REQUEST['to_month'] ) || $_REQUEST['to_month'] == '' ) {
	$_REQUEST['to_month'] = date( 'm', $local_time );
}
if ( ! isset( $_REQUEST['to_year'] ) || $_REQUEST['to_year'] == '' ) {
	$_REQUEST['to_year'] = date( 'Y', $local_time );
}
?>

<h3>Transactions</h3>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission"/>
    <input type="hidden" name="mds_dest" value="transaction-log"/>
    From y/m/d:

    <select name="from_year">
        <option value=''></option>
		<?php
		for ( $i = 2005; $i <= date( "Y" ); $i ++ ) {
			if ( isset( $_REQUEST['from_year'] ) && $_REQUEST['from_year'] == $i ) {
				$sel = " selected ";
			} else {
				$sel = " ";
			}
			echo "<option value='$i' $sel>$i</option>";
		}
		?>
    </select>

    <select name="from_month">
        <option value=''></option>
		<?php
		for ( $i = 1; $i <= 12; $i ++ ) {
			if ( isset( $_REQUEST['from_month'] ) && $_REQUEST['from_month'] == $i ) {
				$sel = " selected ";
			} else {
				$sel = " ";
			}
			echo "<option value='$i' $sel >$i</option>";
		}
		?>
    </select>

    <select name="from_day">
        <option value=''></option>
		<?php
		for ( $i = 1; $i <= 31; $i ++ ) {
			if ( isset( $_REQUEST['from_day'] ) && $_REQUEST['from_day'] == $i ) {
				$sel = " selected ";
			} else {
				$sel = " ";
			}
			echo "<option value='$i' $sel >$i</option>";
		}
		?>
    </select>

    To y/m/d:

    <select name="to_year">
        <option value=''></option>
		<?php
		for ( $i = 2005; $i <= date( "Y" ); $i ++ ) {
			if ( isset( $_REQUEST['to_year'] ) && $_REQUEST['to_year'] == $i ) {
				$sel = " selected ";
			} else {
				$sel = " ";
			}
			echo "<option value='$i' $sel>$i</option>";
		}

		if ( isset( $_REQUEST['select_date'] ) && $_REQUEST['select_date'] != '' ) {

			$status = $_REQUEST['status'] ?? '';

			$date_link =

				"&from_day=" . $_REQUEST['from_day'] . "&from_month=" . $_REQUEST['from_month'] . "&from_year=" . $_REQUEST['from_year'] . "&to_day=" . $_REQUEST['to_day'] . "&to_month=" . $_REQUEST['to_month'] . "&to_year=" . $_REQUEST['to_year'] . "&status=" . $status . "&select_date=1";
		}
		?>
    </select>

    <select name="to_month">
        <option value=''></option>
		<?php
		for ( $i = 1; $i <= 12; $i ++ ) {
			if ( isset( $_REQUEST['to_month'] ) && $_REQUEST['to_month'] == $i ) {
				$sel = " selected ";
			} else {
				$sel = " ";
			}
			echo "<option value='$i' $sel >$i</option>";
		}
		?>
    </select>

    <select name="to_day">
        <option value=''></option>
		<?php
		for ( $i = 1; $i <= 31; $i ++ ) {
			if ( isset( $_REQUEST['to_day'] ) && $_REQUEST['to_day'] == $i ) {
				$sel = " selected ";
			} else {
				$sel = " ";
			}
			echo "<option value='$i' $sel >$i</option>";
		}
		?>
    </select>
    <input type="submit" name="select_date" value="Go"> &nbsp; &nbsp; &nbsp;
    <input type="button" name="select_date" value="Reset" onclick='window.location.href="<?php echo esc_url( admin_url( 'admin.php?page=mds-transaction-log' ) ); ?>"'>
</form><p>
	<?php

	$three_months_ago = mktime( 0, 0, 0, date( 'd' ), date( 'm' ) - 3, date( "Y" ) );
	$q_from_day       = 1;// (int)date ("d", $three_months_ago);
	$q_from_month     = (int) date( "m", $three_months_ago );
	$q_from_year      = (int) date( "Y", $three_months_ago );

	?>
<p>
    Balance: <?php echo $bal; ?><br>
</p>
<table width="100%" border="0" cellSpacing="1" cellPadding="3" align="center" bgColor="#d9d9d9">

    <tr bgcolor="#eaeaea">
        <td>
            <span style="font-family: arial,sans-serif;"><b>Date</b></span>
        </td>
        <td>
            <span style="font-family: arial,sans-serif;"><b>Order ID</b></span>
        </td>
        <td>
            <span style="font-family: arial,sans-serif;"><b>Origin</b></span>
        </td>
        <td>
            <span style="font-family: arial,sans-serif;"><b>Reason / Status</b></span>
        </td>
        <td>
            <span style="font-family: arial,sans-serif;"><b>Amount</b></span>
        </td>
        <td>
            <span style="font-family: arial,sans-serif;"><b>Type</b></span>
        </td>
        <td>
            <span style="font-family: arial,sans-serif;"><b>Action</b></span>
        </td>
    </tr>

	<?php
	$from_date = intval( $_REQUEST['from_year'] ) . "-" . intval( $_REQUEST['from_month'] ) . "-" . intval( $_REQUEST['from_day'] ) . " 00:00:00";
	$to_date   = intval( $_REQUEST['to_year'] ) . "-" . intval( $_REQUEST['to_month'] ) . "-" . intval( $_REQUEST['to_day'] ) . " 23:59:59";

	$where_date = " (`date` >= STR_TO_DATE('$from_date', '%Y-%m-%d') AND `date` <= STR_TO_DATE('$to_date', '%Y-%m-%d') ) ";

	$sql = "SELECT t.*, o.*, u.*, pm.post_id as wc_order_id 
	        FROM " . MDS_DB_PREFIX . "transactions t 
	        JOIN " . MDS_DB_PREFIX . "orders o ON t.order_id = o.order_id 
	        JOIN " . $wpdb->prefix . "users u ON o.user_id = u.ID 
	        LEFT JOIN " . $wpdb->prefix . "postmeta pm ON t.order_id = pm.meta_value AND pm.meta_key = 'mds_order_id' 
	        WHERE $where_date 
	        ORDER BY t.date DESC";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	while ( $row = mysqli_fetch_array( $result ) ) {
		$user_info      = get_userdata( $row['ID'] );
		$wc_order_id    = $row['wc_order_id'] ?? null;
		$show_refund_link = false;

		// Check if associated WC order exists and is potentially refundable
		if ( $wc_order_id && class_exists('WooCommerce') ) {
			$order = wc_get_order( $wc_order_id );
			if ( $order && $order->is_paid() && $order->get_remaining_refund_amount() > 0 ) {
				// Check gateway support
				$payment_gateways = WC()->payment_gateways->payment_gateways();
				$gateway_id = $order->get_payment_method();
				$gateway = $payment_gateways[ $gateway_id ] ?? null;
				if ( $gateway && is_array( $gateway->supports ) && in_array( 'refunds', $gateway->supports, true ) ) {
					$show_refund_link = true;
					$refund_link_url = get_edit_post_link( $wc_order_id );
				}
			}
		}
		?>
        <tr bgcolor="#ffffff">
            <td>
                <span style="font-family: arial,sans-serif;"><?php echo $row['date']; ?></span>
            </td>
            <td>
                <span style="font-family: arial,sans-serif;"><?php echo $row['order_id']; ?> (<?php echo $user_info->last_name . ", " . $user_info->first_name; ?>)</span>
            </td>
            <td>
                <span style="font-family: arial,sans-serif;"><?php echo $row['origin']; ?></span>
            </td>
            <td>
                <span style="font-family: arial,sans-serif;"><?php echo $row['reason']; ?></span>
            </td>
            <td>
                <span style="font-family: arial,sans-serif;"><?php echo Currency::convert_to_default_currency( $row['currency'], $row['amount'] ); ?></span>
            </td>
            <td>
                <span style="font-family: arial,sans-serif;"><?php if ( $row['type'] == 'DEBIT' ) {
		                echo '<span style="color: green; ">';
	                } else {
		                echo '<span style="color: red; ">';
	                }
	                echo $row['type'] . '</font>'; ?></span>
            </td>
            <td>
                <span style="font-family: arial,sans-serif;">
					<?php
					if ( $show_refund_link && $refund_link_url ) {
						printf(
							'<a href="%s" target="_blank">%s</a>',
							esc_url( $refund_link_url ),
							Language::get( 'Manage Refund (WooCommerce)' )
						);
					} else {
						echo '&mdash;'; // No action available
					}
					?>
				</span>
            </td>
        </tr>
		<?php

	}

	?>

</table>
