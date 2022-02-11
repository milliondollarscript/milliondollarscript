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

if ( isset( $_REQUEST['clear_orders'] ) && $_REQUEST['clear_orders'] == 'true' ) {
	$sql = "delete from " . MDS_DB_PREFIX . "ads";
	mysqli_query( $GLOBALS['connection'], $sql );

	$sql = "delete from " . MDS_DB_PREFIX . "blocks";
	mysqli_query( $GLOBALS['connection'], $sql );

	$sql = "delete from " . MDS_DB_PREFIX . "orders";
	mysqli_query( $GLOBALS['connection'], $sql );

	$sql = "delete from " . MDS_DB_PREFIX . "temp_orders";
	mysqli_query( $GLOBALS['connection'], $sql );

	// TODO: delete uploaded files too

	$done = "Done!";
}

if ( isset( $done ) ) {
    ?>
	<h3><?php echo $done; ?></h3>
    Now you should <a href="process.php">Process Pixels</a> to regenerate your grid.
	<?php
}
?>
<h1>Clear Orders</h1>
<form action="clear_orders.php" method="post">
	<input type="hidden" name="clear_orders" value="true"/>
    <p>WARNING: This will completely delete all ads, blocks, and orders including any orders in progress. This cannot be undone! You might want to run a backup before doing this. Click the Delete button below if you wish to proceed.</p>
	<input type="submit" name="submit" value="Delete"/>
</form>
