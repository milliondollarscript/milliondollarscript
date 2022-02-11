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

$BID = 1; # Banner ID. Change this later & allow users to select multiple banners
$sql = "select * from " . MDS_DB_PREFIX . "banners where banner_id='$BID'";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error($sql) );
$b_row = mysqli_fetch_array( $result );
if ( $_REQUEST['order_id'] ) {
	$_SESSION['MDS_order_id'] = $_REQUEST['order_id'];
}
process_login();
require_once BASE_PATH . "/html/header.php";
?>
    <p>
		<?php echo $label['advertiser_pay_navmap']; ?>
    </p>
    <h3><?php echo $label['advertiser_pay_sel_method']; ?></h3>
<?php

if ( $_REQUEST['order_id'] != '' ) {

	$order_id = $_REQUEST['order_id'];
} else {
	$order_id = $_SESSION['MDS_order_id'];
}

$sql = "SELECT * from " . MDS_DB_PREFIX . "orders where order_id=" . intval( $order_id );
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error($sql) );
$order_row = mysqli_fetch_array( $result );

// Proceess confirmation
if ( $_REQUEST['action'] == 'confirm' ) {

	// move temp order to confirmed order

	confirm_order( $_SESSION['MDS_ID'], $order_id );
}

echo "<h2>Total: " . $order_row['price'] . " " . $order_row['currency'] . "</h2>";

include BASE_PATH . '/payment/payment_manager.php';

payment_option_list( $order_id );

require_once BASE_PATH . "/html/footer.php";
