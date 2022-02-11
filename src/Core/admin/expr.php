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

// email expiration warnings?

if ( EMAIL_USER_EXPIRE_WARNING == 'YES' ) {

	$now = ( gmdate( "Y-m-d H:i:s" ) );

	//$sql = "SELECT * from orders, banners where status='completed' and orders.banner_id=banners.banner_id AND banners.days_expire <> 0 AND DATE_SUB('$now',INTERVAL banners.days_expire DAY) >= DATE_SUB(orders.date_published, INTERVAL 3 DAY) AND orders.date_published IS NOT NULL AND expiry_notice_sent='N' ";

	$sql = "SELECT * from " . MDS_DB_PREFIX . "orders, " . MDS_DB_PREFIX . "banners where status='completed' and " . MDS_DB_PREFIX . "orders.banner_id=" . MDS_DB_PREFIX . "banners.banner_id AND " . MDS_DB_PREFIX . "banners.days_expire <> 0 AND DATE_SUB('$now',INTERVAL " . MDS_DB_PREFIX . "banners.days_expire+5 DAY) >= " . MDS_DB_PREFIX . "orders.date_published AND " . MDS_DB_PREFIX . "orders.date_published IS NOT NULL AND expiry_notice_sent <> 'Y' ";

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	echo "Advertisers to email: " . mysqli_num_rows( $result );

	?>
    <table width="100%" cellSpacing="1" cellPadding="3" align="center" bgColor="#d9d9d9" border="0">
        <tr>
            <td><b><input type="checkbox" onClick="checkBoxes('orders');"></td>
            <td><b>Order Date</b></td>
            <td><b>Customer Name</b></td>
            <td><b>Username & ID</b></td>
            <td><b>OrderID</b></td>
            <td><b>Grid</b></td>
            <td><b>Quantity</b></td>
            <td><b>Amount</b></td>
            <td><b>Status</b></td>
        </tr>

		<?php
		while ( $row = mysqli_fetch_array( $result ) ) {
			?>
            <tr onmouseover="old_bg=this.getAttribute('bgcolor');this.setAttribute('bgcolor', '#FBFDDB', 0);" onmouseout="this.setAttribute('bgcolor', old_bg, 0);" bgColor="#ffffff">
                <td><input type="checkbox" name="orders[]" value="<?php echo $row['order_id']; ?>"></td>
                <td><?php echo $row['order_date']; ?></td>
                <td><?php echo $row['FirstName'] . " " . $row['LastName']; ?></td>
                <td><?php echo $row['Username']; ?> (#<?php echo $row[ ID ]; ?>)</td>
                <td>#<?php echo $row['order_id']; ?></td>
                <td><?php

					$sql = "select * from " . MDS_DB_PREFIX . "banners where banner_id=" . intval( $row['banner_id'] );
					$b_result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
					$b_row = mysqli_fetch_array( $b_result );

					echo $b_row['name'];

					?></td>
                <td><?php echo $row['quantity']; ?></td>
                <td><?php echo convert_to_default_currency_formatted( $row['currency'], $row['price'] ) ?></td>
                <td><?php echo $label[ $row['status'] ]; ?></td>
            </tr>
			<?php
		}
		?>
    </table>
	<?php
} else {
	echo "Expiration warnings not enabled. You can enable them form Main Config.";
}
