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

@set_time_limit( 180 );
require_once __DIR__ . "/../include/init.php";
require( 'admin_common.php' );

$oid = 0;
if ( isset( $_REQUEST['mass_complete'] ) && $_REQUEST['mass_complete'] != '' ) {

	foreach ( $_REQUEST['orders'] as $oid ) {

		$sql = "SELECT * from " . MDS_DB_PREFIX . "orders where order_id=" . intval( $oid );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		$order_row = mysqli_fetch_array( $result );

		if ( $order_row['status'] != 'completed' ) {
			complete_order( $order_row['user_id'], $oid );
			debit_transaction( $order_row['user_id'], $order_row['price'], $order_row['currency'], $order_row['order_id'], 'complete', 'Admin' );
		}
	}
}

if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'complete' ) {

	$sql = "SELECT * from " . MDS_DB_PREFIX . "orders where order_id=" . intval( $_REQUEST['order_id'] );
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
	$order_row = mysqli_fetch_array( $result );

	complete_order( $_REQUEST['user_id'], $_REQUEST['order_id'] );
	debit_transaction( $_REQUEST['order_id'], $order_row['price'], $order_row['currency'], $order_row['order_id'], 'complete', 'Admin' );
	echo "Order completed.";
}

if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'cancel' ) {
	cancel_order( $_REQUEST['order_id'] );
	echo "Order cancelled.";
}

if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'unreserve' ) {
	unreserve_block( $_REQUEST['block_id'], $_REQUEST['banner_id'] );
	echo "Order unreserved.";
}

if ( isset( $_REQUEST['mass_cancel'] ) && $_REQUEST['mass_cancel'] != '' ) {

	echo "cancelling...";

	foreach ( $_REQUEST['orders'] as $oid ) {

		//echo "$order_id ";
		cancel_order( $oid );
	}
}

if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'delete' ) {

	delete_order( $_REQUEST['order_id'] );

	echo "Order deleted.";
}

if ( isset( $_REQUEST['mass_delete'] ) && $_REQUEST['mass_delete'] != '' ) {

	foreach ( $_REQUEST['orders'] as $oid ) {
		delete_order( $oid );
	}
}

$q_aday     = $_REQUEST['q_aday'] ?? '';
$q_amon     = $_REQUEST['q_amon'] ?? '';
$q_ayear    = $_REQUEST['q_ayear'] ?? '';
$q_name     = $_REQUEST['q_name'] ?? '';
$q_username = $_REQUEST['q_username'] ?? '';
$q_resumes  = $_REQUEST['q_resumes'] ?? '';
$q_news     = $_REQUEST['q_news'] ?? '';
$q_email    = $_REQUEST['q_email'] ?? '';
$q_company  = $_REQUEST['q_company'] ?? '';
$search     = $_REQUEST['search'] ?? '';
$show       = $_REQUEST['show'] ?? '';

$q_string = "&q_name=$q_name&q_username=$q_username&q_email=$q_email&q_aday=$q_aday&q_amon=$q_amon&q_ayear=$q_ayear&search=$search";
?>

<form style="margin: 0" action="orders.php?search=search" method="post">
    <input type="hidden" value="<?php echo $show; ?>" name="show">
    <table border="0" cellpadding="2" cellspacing="0" style="border-collapse: collapse" id="AutoNumber2" width="100%">
        <tr>
            <td width="63" bgcolor="#EDF8FC" valign="top">
                <p><b>Name</b></p>
            </td>
            <td width="286" bgcolor="#EDF8FC" valign="top">
                <input type="text" name="q_name" size="39" value="<?php echo $q_name; ?>"/></td>
            <td width="71" bgcolor="#EDF8FC" valign="top">
                <p align="right"><b>Username</b></td>
            <td width="299" bgcolor="#EDF8FC" valign="top">
                <input type="text" name="q_username" size="28" value="<?php echo $q_username; ?>"/></td>
        </tr>
        <tr>
            <td width="63" bgcolor="#EDF8FC" valign="top">
                <p align="right"><b>Date From:</b></td>
            <td width="286" bgcolor="#EDF8FC" valign="top">
                <select name="q_aday">
                    <option></option>
                    <option <?php if ( $q_aday == '01' ) {
						echo ' selected ';
					} ?> >1
                    </option>
                    <option <?php if ( $q_aday == '02' ) {
						echo ' selected ';
					} ?> >2
                    </option>
                    <option <?php if ( $q_aday == '03' ) {
						echo ' selected ';
					} ?> >3
                    </option>
                    <option <?php if ( $q_aday == '04' ) {
						echo ' selected ';
					} ?> >4
                    </option>
                    <option <?php if ( $q_aday == '05' ) {
						echo ' selected ';
					} ?> >5
                    </option>
                    <option <?php if ( $q_aday == '06' ) {
						echo ' selected ';
					} ?> >6
                    </option>
                    <option <?php if ( $q_aday == '07' ) {
						echo ' selected ';
					} ?>>7
                    </option>
                    <option <?php if ( $q_aday == '08' ) {
						echo ' selected ';
					} ?>>8
                    </option>
                    <option <?php if ( $q_aday == '09' ) {
						echo ' selected ';
					} ?> >9
                    </option>
                    <option <?php if ( $q_aday == '25' ) {
						echo ' selected ';
					} ?> >25
                    </option>
                    <option <?php if ( $q_aday == '26' ) {
						echo ' selected ';
					} ?> >26
                    </option>
                    <option <?php if ( $q_aday == '10' ) {
						echo ' selected ';
					} ?> >10
                    </option>
                    <option <?php if ( $q_aday == '11' ) {
						echo ' selected ';
					} ?> > 11
                    </option>
                    <option <?php if ( $q_aday == '12' ) {
						echo ' selected ';
					} ?> >12
                    </option>
                    <option <?php if ( $q_aday == '13' ) {
						echo ' selected ';
					} ?> >13
                    </option>
                    <option <?php if ( $q_aday == '14' ) {
						echo ' selected ';
					} ?> >14
                    </option>
                    <option <?php if ( $q_aday == '15' ) {
						echo ' selected ';
					} ?> >15
                    </option>
                    <option <?php if ( $q_aday == '16' ) {
						echo ' selected ';
					} ?> >16
                    </option>
                    <option <?php if ( $q_aday == '17' ) {
						echo ' selected ';
					} ?> >17
                    </option>
                    <option <?php if ( $q_aday == '18' ) {
						echo ' selected ';
					} ?> >18
                    </option>
                    <option <?php if ( $q_aday == '19' ) {
						echo ' selected ';
					} ?> >19
                    </option>
                    <option <?php if ( $q_aday == '20' ) {
						echo ' selected ';
					} ?> >20
                    </option>
                    <option <?php if ( $q_aday == '21' ) {
						echo ' selected ';
					} ?> >21
                    </option>
                    <option <?php if ( $q_aday == '22' ) {
						echo ' selected ';
					} ?> >22
                    </option>
                    <option <?php if ( $q_aday == '23' ) {
						echo ' selected ';
					} ?> >23
                    </option>
                    <option <?php if ( $q_aday == '24' ) {
						echo ' selected ';
					} ?> >24
                    </option>
                    <option <?php if ( $q_aday == '27' ) {
						echo ' selected ';
					} ?> >27
                    </option>
                    <option <?php if ( $q_aday == '28' ) {
						echo ' selected ';
					} ?> >28
                    </option>
                    <option <?php if ( $q_aday == '29' ) {
						echo ' selected ';
					} ?> >29
                    </option>
                    <option <?php if ( $q_aday == '30' ) {
						echo ' selected ';
					} ?> >30
                    </option>
                    <option <?php if ( $q_aday == '31' ) {
						echo ' selected ';
					} ?> >31
                    </option>
                </select>
                <select name="q_amon">
                    <option></option>
                    <option <?php if ( $q_amon == '01' ) {
						echo ' selected ';
					} ?> value="1">Jan
                    </option>
                    <option <?php if ( $q_amon == '02' ) {
						echo ' selected ';
					} ?> value="2">Feb
                    </option>
                    <option <?php if ( $q_amon == '03' ) {
						echo ' selected ';
					} ?> value="3">Mar
                    </option>
                    <option <?php if ( $q_amon == '04' ) {
						echo ' selected ';
					} ?> value="4">Apr
                    </option>
                    <option <?php if ( $q_amon == '05' ) {
						echo ' selected ';
					} ?> value="5">May
                    </option>
                    <option <?php if ( $q_amon == '06' ) {
						echo ' selected ';
					} ?> value="6">Jun
                    </option>
                    <option <?php if ( $q_amon == '07' ) {
						echo ' selected ';
					} ?> value="7">Jul
                    </option>
                    <option <?php if ( $q_amon == '08' ) {
						echo ' selected ';
					} ?> value="8">Aug
                    </option>
                    <option <?php if ( $q_amon == '09' ) {
						echo ' selected ';
					} ?> value="9">Sep
                    </option>
                    <option <?php if ( $q_amon == '10' ) {
						echo ' selected ';
					} ?> value="10">Oct
                    </option>
                    <option <?php if ( $q_amon == '11' ) {
						echo ' selected ';
					} ?> value="11">Nov
                    </option>
                    <option <?php if ( $q_amon == '12' ) {
						echo ' selected ';
					} ?> value="12">Dec
                    </option>
                </select>
                <input type="text" name="q_ayear" size="4" value="<?php echo $q_ayear; ?>"/>
            </td>
            <td width="71" bgcolor="#EDF8FC" valign="top"></td>
            <td width="299" bgcolor="#EDF8FC" valign="top"></td>
        </tr>
        <tr>
            <td width="731" bgcolor="#EDF8FC" colspan="4">
                <b><input type="submit" value="Find" name="B1" style="float: left"><?php if ( $search == 'search' ) { ?>&nbsp; </b><b>[<a href="<?php echo $_SERVER['PHP_SELF'] ?>?show=<?php echo $show; ?>">Start a New Search</a>]</b><?php } ?>
            </td>
        </tr>
    </table>
</form>

<?php
$where_sql = "";
$date_link = "";
unset( $sql );
if ( $show == 'RE' ) {
	$sql       = "SELECT * FROM " . MDS_DB_PREFIX . "blocks as t1, " . MDS_DB_PREFIX . "users as t2 where t1.user_id=t2.ID AND status='reserved' ORDER BY t1.block_id DESC";
	$date_link = "&show=RE";
} else if ( $show == 'WA' ) {
	$where_sql = " AND (status ='confirmed' OR status='pending') ";
	$date_link = "&show=WA";
} else if ( $show == 'CA' ) {
	$where_sql = " AND (status ='cancelled') ";
	$date_link = "&show=CA";
} else if ( $show == 'EX' ) {
	$where_sql = " AND (status ='expired') ";
	$date_link = "&show=EX";
} else if ( $show == 'DE' ) {
	$where_sql = " AND (status ='deleted') ";
	$date_link = "&show=DE";
} else if ( $show == 'CO' ) {
	$where_sql = " AND status ='completed' ";
}

switch ( $show ) {
	case 'RE':
		echo '<p>Showing reserved blocks.</p>';
		break;
	case 'WA':
		echo '<p>Showing new orders waiting</p>';
		break;
	case 'CO':
		echo '<p>Showing completed orders</p>';
		break;
	case 'EX':
		echo '<p>Showing expired orders.</p>';
		break;
	case 'CA':
		echo '<p>Showing cancelled orders. Note: Blocks are kept reserved for cancelled orders. Delete the order to free the blocks.</p>';
		break;
	case 'DE':
		echo '<p>Showing deleted orders.</p>';
		break;
}

if ( $q_name != '' ) {
	$list = preg_split( "/[\s,]+/", $q_name );
	$or1  = '';
	$or2  = '';
	for ( $i = 1; $i < sizeof( $list ); $i ++ ) {
		$or1 .= " OR (`FirstName` like '%" . $list[ $i ] . "%')";
		$or2 .= " OR (`LastName` like '%" . $list[ $i ] . "%')";
	}
	$where_sql .= " AND (((`FirstName` like '%$list[0]%') $or1) OR ((`LastName` like '%$list[0]%') $or2))";
}

if ( $q_username != '' ) {
	$q_username = trim( $q_username );
	$list       = preg_split( "/[\s,]+/", $q_username );
	$or         = '';
	for ( $i = 1; $i < sizeof( $list ); $i ++ ) {
		$or .= " OR (`Username` like '%" . mysqli_real_escape_string( $GLOBALS['connection'], $list[ $i ] ) . "%')";
	}
	$where_sql .= " AND ((`Username` like '%" . mysqli_real_escape_string( $GLOBALS['connection'], $list[0] ) . "%') $or)";
}

if ( isset( $_REQUEST['user_id'] ) && $_REQUEST['user_id'] != '' ) {

	$where_sql .= " AND t1.user_id=" . intval( $_REQUEST['user_id'] );
}

if ( isset( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] != '' ) {
	echo '<h3>*** Highlighting order #' . esc_html($_REQUEST['order_id']) . '.</h3> ';
}

if ( ! isset( $sql ) ) {
	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders as t1, " . MDS_DB_PREFIX . "users as t2 where t1.user_id=t2.ID $where_sql ORDER BY t1.order_date DESC  ";
}

//echo $sql;

$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
$count = mysqli_num_rows( $result );

$records_per_page = 40;

$offset = isset( $_REQUEST['offset'] ) ? intval( $_REQUEST['offset'] ) : 0;

if ( $count > $records_per_page ) {
	mysqli_data_seek( $result, $offset );
}

$pages    = ceil( $count / $records_per_page );
$cur_page = $offset / $records_per_page;
$cur_page ++;

global $label;

?>

<form style="margin: 0;" method="post" action="orders.php?offset=<?php echo esc_attr( $offset . $q_string ); ?>" name="form1">
    <input type="hidden" name="show" value="<?php echo $show; ?>">
    <input type="hidden" name="offset" value="<?php echo $offset; ?>">
    <div style="text-align: center;"><b><?php echo mysqli_num_rows( $result ); ?> Orders Returned (<?php echo $pages; ?> pages) </b></div>
	<?php
	if ( $count > $records_per_page ) {
		// calculate number of pages & current page
		$q_string .= "&show=" . $show;

		$label["navigation_page"] = str_replace( "%CUR_PAGE%", $cur_page, $label["navigation_page"] );
		$label["navigation_page"] = str_replace( "%PAGES%", $pages, $label["navigation_page"] );
		//	echo "<span > ".$label["navigation_page"]."</span> ";
		$nav   = nav_pages_struct( $q_string, $count, $records_per_page );
		$LINKS = 40;
		render_nav_pages( $nav, $LINKS, $q_string );
	}
	?>
    <table width="100%" cellSpacing="1" cellPadding="3" align="center" bgColor="#d9d9d9" border="0">
        <tr>
            <td colspan="12"> <?php if ( $show != 'DE' ) { ?>
                    With selected:
					<?php
					if ( $show != 'RE' ) {
						?>
                        <input type="submit" value='Complete' onclick="if (!confirmLink(this, 'Complete for all selected, are you sure?')) return false" name='mass_complete'>
						<?php
					}
					if ( $show != 'CA' ) {
						?>
                        | <input type="submit" value='Cancel' name='mass_cancel' onclick="if (!confirmLink(this, 'Cancel for all selected, are you sure?')) return false">
						<?php
					}
					if ( $show == 'CA' ) {
						?>
                        | <input type="submit" value='Delete' name='mass_delete' onclick="if (!confirmLink(this, 'Delete for all selected, are you sure?')) return false">
						<?php
					}
				} ?></td>
        </tr>
        <tr bgcolor="#eaeaea">
            <td><input type="checkbox" onClick="checkBoxes('orders');"></td>
            <td><b>Order Date</b></td>
            <td><b>Customer Name</b></td>
            <td><b>Username & ID</b></td>
            <td><b>OrderID</b></td>
            <td><b>AdID</b></td>
            <td><b>Grid</b></td>
            <td><b>Quantity</b></td>
            <td><b>Amount</b></td>
            <td><b>Status</b></td>
        </tr>
		<?php
		$i = 0;
		while ( ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) && ( $i < $records_per_page ) ) {
			$i ++;

			?>
            <tr onmouseover="old_bg=this.getAttribute('bgcolor');this.setAttribute('bgcolor', '#FBFDDB', 0);" onmouseout="this.setAttribute('bgcolor', old_bg, 0);" bgColor="<?php if ( ( $_REQUEST['order_id'] ?? '' ) == $row['order_id'] ) {
				echo '#FFFF99';
			} else {
				echo '#ffffff';
			} ?>">
                <td><input type="checkbox" name="orders[]" value="<?php echo $row['order_id']; ?>"></td>
                <td><?php echo get_local_datetime( $row['order_date'], true ); ?></td>
                <td><?php echo escape_html( $row['FirstName'] . " " . $row['LastName'] ); ?></td>
                <td><?php echo $row['Username']; ?> (<a href='edit.php?user_id=<?php echo $row['ID']; ?>'>#<?php echo $row['ID']; ?></a>)</td>
                <td>#<?php echo $row['order_id']; ?></td>
                <td><a href='alist.php?aid=<?php echo $row['ad_id']; ?>&order_id=<?php echo $row['order_id']; ?>&BID=<?php echo $row['banner_id']; ?>'>#<?php echo $row['ad_id']; ?></a></td>
                <td><?php

					$sql = "select * from " . MDS_DB_PREFIX . "banners where banner_id=" . $row['banner_id'];
					$b_result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
					$b_row = mysqli_fetch_array( $b_result );

					echo $b_row['name'];

					?></td>
                <td><?php echo $row['quantity']; ?></td>
                <td><?php echo convert_to_default_currency_formatted( $row['currency'], $row['price'] ) ?></td>
                <td><?php echo $row['status']; ?><br>
					<?php
					$refunded = false;
					if ( $row['status'] == 'cancelled' ) {
						$sql = "select * from " . MDS_DB_PREFIX . "transactions where type='CREDIT' and order_id=" . intval( $row['order_id'] );
						$r1 = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
						if ( mysqli_num_rows( $r1 ) > 0 ) {
							$refunded = true;
							echo "(Refunded)";
						}
					}
					if ( $show == 'RE' ) {
						?>
                        <input type="button" style="font-size: 9px;" value="Cancel" onclick="if (!confirmLink(this, 'Unreserve block <?php echo $row['block_id']; ?>, are you sure?')) return false;" data-link="orders.php?action=unreserve&user_id=<?php echo $row['ID'] ?>&block_id=<?php echo $row['block_id'] ?>&banner_id=<?php echo $row['banner_id'] ?>&order_id=<?php echo esc_attr( $row['order_id'] . $date_link . $q_string . "&show=" . $show ); ?>">
					<?php } else {
						if ( ( $row['status'] != 'completed' ) && ( $row['status'] != 'deleted' ) && ! $refunded ) {
							?>
                            <input type="button" style="font-size: 9px;" value="Complete" onclick="if (!confirmLink(this, 'Payment from <?php echo str_replace( "'", "\\'", escape_html( $row['LastName'] ) ) . ", " . str_replace( "'", "\\'", escape_html( $row['FirstName'] ) ); ?> to be completed. Order for <?php echo $row['price']; ?> will be credited to their account.\n ** Are you sure? **')) return false;" data-link="orders.php?action=complete&user_id=<?php echo esc_attr( $row['ID'] ) ?>&order_id=<?php echo esc_attr( $row['order_id'] . $date_link . $q_string . "&show=" . $show ); ?>"> /
							<?php
						}
						if ( $row['status'] == 'cancelled' ) {
							?>
                            <input type="button" style="font-size: 9px;" value="Delete" onclick="if (!confirmLink(this, 'Delete the order from <?php echo str_replace( "'", "\\'", escape_html( $row['LastName'] ) ) . ", " . str_replace( "'", "\\'", escape_html( $row['FirstName'] ) ); ?>, are you sure?')) return false;" data-link="orders.php?action=delete&order_id=<?php echo esc_attr( $row['order_id'] . $date_link . $q_string . "&show=" . $show ); ?>">
							<?php
						} else if ( $row['status'] == 'deleted' ) {

						} else {
							?>
                            <input type="button" style="font-size: 9px;" value="Cancel" onclick="if (!confirmLink(this, 'Cancel the order from <?php echo str_replace( "'", "\\'", escape_html( $row['LastName'] ) ) . ", " . str_replace( "'", "\\'", escape_html( $row['FirstName'] ) ); ?>, are you sure?')) return false;" data-link="orders.php?action=cancel&user_id=<?php echo $row['ID'] ?>&order_id=<?php echo esc_attr( $row['order_id'] . $date_link . $q_string . "&show=" . $show ); ?>">
							<?php
						}
					}
					?>
                </td>
            </tr>
			<?php
		}
		?>
    </table>
</form>
