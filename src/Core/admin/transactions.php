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

$BID = $f2->bid();

$bid_sql = " AND banner_id=$BID ";

if ( ( $BID == 'all' ) || ( $BID == '' ) ) {
	$BID     = '';
	$bid_sql = "  ";
}

if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'refund' ) {

	$t_id = $_REQUEST['transaction_id'];

	$sql = "SELECT * from " . MDS_DB_PREFIX . "transactions, " . MDS_DB_PREFIX . "orders, " . MDS_DB_PREFIX . "users where " . MDS_DB_PREFIX . "transactions.order_id=" . MDS_DB_PREFIX . "orders.order_id AND " . MDS_DB_PREFIX . "orders.user_id=" . MDS_DB_PREFIX . "users.ID and " . MDS_DB_PREFIX . "transactions.transaction_id=" . intval( $t_id );

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );

	if ( $row['status'] != 'completed' ) {
		// check that there's no other refund...
		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "transactions where txn_id='" . mysqli_real_escape_string($GLOBALS['connection'], $row['txn_id']) . "' AND type='CREDIT' ";
		$r = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		if ( mysqli_num_rows( $r ) == 0 ) {
			// do the refund
			cancel_order( $row['order_id'] );
			credit_transaction( $row['order_id'], $row['price'], $row['currency'], $row['txn_id'], 'Refund', 'Admin' );
		} else {

			echo "<b>Error: A refund was already found on this system for this order..</b><br>";
		}
	} else {

		echo $row['status'];

		echo "<b>Error: The system can only refund orders that are completed, please cancel the order first</b><br>";
	}
	// can only refund completed orders..

}

?>
<p>
    The transaction log helps you manage the money transfers. Note: Refunds are processed with PayPal or the payment gateway that was used. If you issued a refund that does not automatically report refunds to the script, you can issue your refunds here.
</p>
<?php

// calculate the balance
$sql = "SELECT SUM(amount) as mysum, type, currency from " . MDS_DB_PREFIX . "transactions group by type, currency";

$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
$debits = $credits = 0;
while ( $row = mysqli_fetch_array( $result ) ) {

	if ( $row['type'] == 'CREDIT' ) {
		$credits = $credits + convert_to_default_currency( $row['currency'], $row['mysum'] );
	}

	if ( $row['type'] == 'DEBIT' ) {
		$debits = $debits + convert_to_default_currency( $row['currency'], $row['mysum'] );
	}
}

$bal = $debits - $credits;

$local_date = ( gmdate( "Y-m-d H:i:s" ) );
$local_time = strtotime( $local_date );

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
<form method="GET" action="transactions.php">
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
    <input type="button" name="select_date" value="Reset" onclick='mds_load_page("transactions.php", true)'>
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
            <font face="arial" size="2"><b>Date</b></font>
        </td>
        <td>
            <font face="arial" size="2"><b>Order ID</b></font>
        </td>
        <td>
            <font face="arial" size="2"><b>Origin</b></font>
        </td>
        <td>
            <font face="arial" size="2"><b>Reason / Status</b></font>
        </td>
        <td>
            <font face="arial" size="2"><b>Amount</b></font>
        </td>
        <td>
            <font face="arial" size="2"><b>Type</b></font>
        </td>
        <td>
            <font face="arial" size="2"><b>Action</b></font>
        </td>
    </tr>

	<?php
	$from_date = intval( $_REQUEST['from_year'] ) . "-" . intval( $_REQUEST['from_month'] ) . "-" . intval( $_REQUEST['from_day'] ) . " 00:00:00";
	$to_date   = intval( $_REQUEST['to_year'] ) . "-" . intval( $_REQUEST['to_month'] ) . "-" . intval( $_REQUEST['to_day'] ) . " 23:59:59";

	$where_date = " (`date` >= STR_TO_DATE('$from_date', '%Y-%m-%d') AND `date` <= STR_TO_DATE('$to_date', '%Y-%m-%d') ) ";

	$sql = "SELECT * from " . MDS_DB_PREFIX . "transactions, " . MDS_DB_PREFIX . "orders, " . MDS_DB_PREFIX . "users where $where_date AND " . MDS_DB_PREFIX . "transactions.order_id=" . MDS_DB_PREFIX . "orders.order_id AND " . MDS_DB_PREFIX . "orders.user_id=" . MDS_DB_PREFIX . "users.ID order by " . MDS_DB_PREFIX . "transactions.date desc ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	//echo $sql;
	while ( $row = mysqli_fetch_array( $result ) ) {

		?>
        <tr bgcolor="#ffffff">
            <td>
                <font face="arial" size="1"><?php echo $row['date']; ?></font>
            </td>
            <td>
                <font face="arial" size="1"><?php echo $row['order_id']; ?> (<?php echo $row['LastName'] . ", " . $row['FirstName']; ?>)</font>
            </td>
            <td>
                <font face="arial" size="1"><?php echo $row['origin']; ?></font>
            </td>
            <td>
                <font face="arial" size="1"><?php echo $row['reason']; ?></font>
            </td>
            <td>
                <font face="arial" size="1"><?php echo convert_to_default_currency( $row['currency'], $row['amount'] ); ?></font>
            </td>
            <td>
                <font face="arial" size="1"><?php if ( $row['type'] == 'DEBIT' ) {
						echo '<font color="green">';
					} else {
						echo '<font color="red">';
					}
					echo $row['type'] . '</font>'; ?></font>
            </td>
            <td>
                <font face="arial" size="1"><?php if ( $row['type'] == 'DEBIT' ) {
						; ?><input type="button" value="Refund" onclick="if (!confirmLink(this, 'Refund, are you sure??')) return false;" data-link="transactions.php?action=refund&transaction_id=<?php echo $row['transaction_id']; ?>"><?php } ?></font>
            </td>
        </tr>
		<?php
	}

	?>

</table>
