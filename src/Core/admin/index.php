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

const MAIN_PHP = '1';

require_once __DIR__ . "/../include/init.php";
require_once __DIR__ . '/admin_common.php';
require_once __DIR__ . '/router.php';

global $f2;

if ( WP_ENABLED == 'YES' ) {
	$mds_site_url = WP_URL;
} else {
	$mds_site_url = BASE_HTTP_PATH;
}

?><!DOCTYPE html>
<html lang="">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <title>Million Dollar Script Administration</title>
    <link rel="stylesheet" type="text/css" href="<?php echo $f2->value( BASE_HTTP_PATH ); ?>admin/css/admin.css?ver=<?php echo filemtime( $f2->value( BASE_PATH ) . "/admin/css/admin.css" ); ?>">
    <script src="<?php echo $f2->value( BASE_HTTP_PATH ); ?>js/third-party/jquery.min.js?ver=<?php echo filemtime( $f2->value( BASE_PATH ) . "/js/third-party/jquery.min.js" ); ?>"></script>
    <script src="<?php echo $f2->value( BASE_HTTP_PATH ); ?>js/third-party/jquery-ui.min.js?ver=<?php echo filemtime( $f2->value( BASE_PATH ) . "/js/third-party/jquery-ui.min.js" ); ?>"></script>
    <script src="<?php echo $f2->value( BASE_HTTP_PATH ); ?>js/third-party/selection.min.js?ver=<?php echo filemtime( $f2->value( BASE_PATH ) . "/js/third-party/selection.min.js" ); ?>"></script>
    <link rel="stylesheet" href="<?php echo $f2->value( BASE_HTTP_PATH ); ?>css/smoothness/jquery-ui.min.css?ver=<?php echo filemtime( $f2->value( BASE_PATH ) . "/css/smoothness/jquery-ui.min.css" ); ?>" type="text/css"/>
    <script src="<?php echo $f2->value( BASE_HTTP_PATH ); ?>js/third-party/jquery.form.min.js?ver=<?php echo filemtime( $f2->value( BASE_PATH ) . "/js/third-party/jquery.form.min.js" ); ?>"></script>

    <script>
		window.mds_data = {
			ajax: '<?php echo BASE_HTTP_PATH; ?>ajax.php',
			mds_site_url: '<?php echo $mds_site_url; ?>',
			BASE_HTTP_PATH: '<?php echo BASE_HTTP_PATH;?>'
		};
    </script>
    <script src="<?php echo $f2->value( BASE_HTTP_PATH ); ?>admin/js/admin.js?ver=<?php echo filemtime( $f2->value( BASE_PATH ) . "/admin/js/admin.js" ); ?>"></script>
</head>
<body>
<div id="mds-top"></div>
<div class="admin-container">
    <div class="admin-menu">
        <img src="<?php echo $f2->value( BASE_HTTP_PATH ); ?>images/logo.gif" alt="Million Dollar Script logo" style="max-width:100%;"/>
        <br>
        <a href="main.php">Main Summary</a><br/>
        <a href="<?php echo $f2->value( $mds_site_url ); ?>" target="_blank">View Site</a><br/>
        <hr>
        <b>Pixel Inventory</b><br/>
        + <a href="inventory.php">Manage Grids</a><br/>
        &nbsp;&nbsp;|- <a href="packs.php">Packages</a><br/>
        &nbsp;&nbsp;|- <a href="price.php">Price Zones</a><br/>
        &nbsp;&nbsp;|- <a href="nfs.php">Not For Sale</a><br/>
        &nbsp;&nbsp;|- <a href="blending.php">Backgrounds</a><br/>
        - <a href="gethtml.php">Get HTML Code</a><br/>

        <hr>
        <b>Advertiser Admin</b><br/>
        - <a href="customers.php">List Advertisers</a><br/>
        <span>Current orders:</span><br>
        - <a href="orders.php?show=WA">Orders: Waiting</a><br/>
        - <a href="orders.php?show=CO">Orders: Completed</a><br/>
        <span>Non-current orders:</span><br>
        - <a href="orders.php?show=EX">Orders: Expired</a><br/>
        - <a href="orders.php?show=CA">Orders: Cancelled</a><br/>
        - <a href="orders.php?show=DE">Orders: Deleted</a><br/>
        <span>Map:</span><br>
        - <a href="ordersmap.php">Map of Orders</a><br/>
        <span>Transactions:</span><br>
        - <a href="transactions.php">Transaction Log</a><br/>
        <hr>
        <b>Pixel Admin</b><br/>
        - <a href="approve.php?app=N">Approve Pixels</a><br/>
        - <a href="approve.php?app=Y">Disapprove Pixels</a><br/>
        - <a href="process.php">Process Pixels</a><br/>
        <hr>
        <b>Report</b><br/>
        - <a href="alist.php">Ad List</a><br/>
        - <a href="list.php">Top Advertisers</a><br/>
        - <a href="email_queue.php">Outgoing Email</a><br/>
        <!--
		- <a href="expr.php">Expiration Reminders</a><br/>
		-->
        <span>Clicks:</span><br>
        - <a href="top.php">Top Clicks</a><br/>
        - <a href="clicks.php">Click Reports</a><br/>
        <hr>
        <b>Configuration</b><br/>
        - <a href="index.php?page=edit-main">Main Config</a><br/>
        - <a href="language.php">Language</a><br/>
        - <a href="currency.php">Currencies</a><br/>
        - <a href="payment.php">Payment Modules</a><br/>
        - <a href="aform.php">Ad Form</a><br/>
        <hr>
        <b>Other</b><br/>
        - <a href="clear_orders.php">Clear Orders</a><br/>
		<?php
		if ( WP_ENABLED == 'NO' || WP_ADMIN_ENABLED == 'NO' ) {
			?>
            <hr>
            <b>Logout</b><br/>
            - <a href="logout.php">Logout</a><br/>
			<?php
		}
		?>
        <hr>
        <b>Info</b><br/>
        - <a href="info.php">System Info</a><br/>
        - <a href="https://milliondollarscript.com" target="_blank">Script Home</a><br/>

        <br/>
        <small>
            Copyright <?php echo date( 'Y' ); ?>, see <a href="../LICENSE.txt">LICENSE.txt</a> for license information.<br/>
            <br/>
            MDS Version: <?php echo MDSConfig::get( 'VERSION_INFO' ); ?>
        </small>
    </div>
    <div class="admin-content"></div>
</div>
</body>
</html>