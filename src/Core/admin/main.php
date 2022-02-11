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

define( 'MAIN_PHP', '1' );

require_once __DIR__ . "/../include/init.php";
require( 'admin_common.php' );

require_once( "../include/dynamic_forms.php" );

$sql = "SELECT ID FROM " . MDS_DB_PREFIX . "users  ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
$advertisers = mysqli_num_rows( $result );

$sql = "SELECT order_id FROM " . MDS_DB_PREFIX . "orders WHERE (status ='confirmed' OR status='pending')  ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
$orders_waiting = mysqli_num_rows( $result );

$sql = "SELECT order_id FROM " . MDS_DB_PREFIX . "orders WHERE  (status ='cancelled')  ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
$orders_cancelled = mysqli_num_rows( $result );

$sql = "SELECT order_id FROM " . MDS_DB_PREFIX . "orders WHERE (status ='completed')  ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
$orders_completed = mysqli_num_rows( $result );

$sql = "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks where approved='N' and image_data <> '' ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
$waiting = mysqli_num_rows( $result );
?>

    <p><h3>Main Summary</h3></p>
    <div class="mds-time mds-gmt">Current GMT Time: <?php echo gmdate( "Y-m-d H:i:s" ); ?></div>
    <div class="mds-time mds-local">Current Local Time: <?php echo get_local_datetime( gmdate( "Y-m-d H:i:s" ), true ); ?></div>
    <table width="80%" border="0" cellpadding="5" style="border-collapse: collapse">
        <tr>
            <td style="border-bottom-style: solid; border-bottom-width: 1px"><?php echo $advertisers; ?></td>
            <td style="border-bottom-style: solid; border-bottom-width: 1px"><a href="customers.php">Advertiser Accounts</a></td>
        </tr>
        <tr>
            <td style="border-top-style: solid; border-top-width: 1px; border-bottom-style: solid; border-bottom-width: 1px" bgcolor="#FFFFCC">
				<?php echo $orders_waiting; ?></td>
            <td style="border-top-style: solid; border-top-width: 1px; border-bottom-style: solid; border-bottom-width: 1px" bgcolor="#FFFFCC">
                <a href="orders.php?show=WA"><?php if ( $orders_waiting > 0 ) {
						echo "<b>";
					} ?>Orders Waiting<?php if ( $orders_waiting > 0 ) {
						echo "</b>";
					} ?></a></td>
        </tr>
        <tr>
            <td style="border-bottom-style: solid; border-bottom-width: 1px"><?php echo $orders_cancelled; ?></td>
            <td style="border-bottom-style: solid; border-bottom-width: 1px"><a href="orders.php?show=CA">Orders Cancelled</a></td>
        </tr>
        <td style="border-top-style: solid; border-top-width: 1px; border-bottom-style: solid; border-bottom-width: 1px" bgcolor="#FFFFCC">
			<?php echo $orders_completed; ?></td>
        <td style="border-top-style: solid; border-top-width: 1px; border-bottom-style: solid; border-bottom-width: 1px" bgcolor="#FFFFCC">
            <a href="orders.php?show=CO">Orders Completed</a></td>
        </tr>
        <tr>
            <td style="border-bottom-style: solid; border-bottom-width: 1px"><?php echo $waiting; ?></td>
            <td style="border-bottom-style: solid; border-bottom-width: 1px"><a href="approve.php">Pixels Waiting for approval</a></td>
        </tr>
    </table>

<?php

$check_path = BASE_PATH . "/payment/check.php";

if ( file_exists( $check_path ) ) {
	echo "<b><font color='red'>Upgrade reminder: Please delete the file check.php from the payment/ file.</font></b>";
}

?>

    <hr>

<?php
$sql    = "show columns from " . MDS_DB_PREFIX . "blocks ";
$result = mysqli_query( $GLOBALS['connection'], $sql );
while ( $row = mysqli_fetch_array( $result ) ) {

	if ( $row['Field'] == 'status' ) {

		if ( strpos( $row['Type'], 'nfs' ) == 0 ) {

			$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "blocks` CHANGE `status` `status` SET( 'reserved', 'sold', 'free', 'ordered', 'nfs' ) NOT NULL ";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br>$sql<br>" );
		}
	}
}

$sql    = "show columns from " . MDS_DB_PREFIX . "orders ";
$result = mysqli_query( $GLOBALS['connection'], $sql );
while ( $row = mysqli_fetch_array( $result ) ) {

	if ( $row['Field'] == 'status' ) {

//		if ( strpos( $row['Type'], 'expired' ) == 0 ) {

		//	$sql = "ALTER TABLE `orders` CHANGE `status` `status`  set('pending','completed','cancelled','confirmed','new', 'expired') NOT NULL ";
		//	 mysqli_query($GLOBALS['connection'], $sql) or die ("<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error($GLOBALS['connection']) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>".mysqli_error($GLOBALS['connection']));

//		}

		if ( strpos( $row['Type'], 'deleted' ) == 0 ) {

			$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` CHANGE `status` `status`  set('pending','completed','cancelled','confirmed','new', 'expired', 'deleted') NOT NULL ";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" . mysqli_error( $GLOBALS['connection'] ) );
		}
	}
}

function does_field_exist( $table, $field ) {
	$result = mysqli_query( $GLOBALS['connection'], "show columns from `$table`" );
	while ( $row = mysqli_fetch_row( $result ) ) {
		//echo $row[0]." ";
		if ( $row[0] == $field ) {

			return true;
		}
	}

	return false;
}

// compare MySQL version, versions newer than 5.6.5 require a different set of queries
$mysql_server_info = mysqli_get_server_info( $GLOBALS['connection'] );

if ( ! does_field_exist( MDS_DB_PREFIX . "blocks", "published" ) ) {
	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "blocks` ADD `published` SET( 'Y', 'N') NOT NULL ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br>$sql<br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "lang", "lang_code" ) ) {
	$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "lang` (
  `lang_code` char(2) NOT NULL default '',
  `lang_filename` varchar(32) NOT NULL default '',
  `lang_image` varchar(32) NOT NULL default '',
  `is_active` set('Y','N') NOT NULL default '',
  `name` varchar(32) NOT NULL default '',
  `charset` varchar(32) NOT NULL default '',
  `image_data` text NOT NULL,
  `mime_type` varchar(255) NOT NULL default '',
  `is_default` char(1) NOT NULL default 'N',
  PRIMARY KEY  (`lang_code`)) ";

	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );

	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "lang` VALUES ('EN', 'english.php', 'english.gif', 'Y', 'English', '', 'R0lGODlhGQARAMQAAAURdBYscgNNfrUOEMkMBdAqE9UTMtItONNUO9w4SdxmaNuObhYuh0Y5lCxVlFJcpqN2ouhfjLCrrOeRmeHKr/Wy3Lje4dPW3PDTz9/q0vXm1ffP7MLt5/f0+AAAAAAAACwAAAAAGQARAAAF02AAMIDDkOgwEF3gukCZIICI1jhFDRmOS4dF50aMVSqEjehFIWQ2kJLUMRoxCCsNzDFBZDCuh1RMpQY6HZYIiOlIYqKy9JZIqHeZTqMWnvoZCgosCkIXDoeIAGJkfmgEB3UHkgp1dYuKVWJXWCsEnp4qAwUcpBwWphapFhoanJ+vKxOysxMRgbcDHRlfeboZF2mvwp+5Eh07YC9naMzNzLmKuggTDy8G19jZ2NAiFB0LBxYuC+TlC7Syai8QGU0TAs7xaNxLDLoDdsPDuS98ABXfQgAAOw==', 'image/gif', 'Y')";

	mysqli_query( $GLOBALS['connection'], $sql ) or die ();
}

if ( ! does_field_exist( MDS_DB_PREFIX . "banners", "banner_id" ) ) {

	$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "banners` (
		`banner_id` int(11) NOT NULL auto_increment,
		`grid_width` int(11) NOT NULL default '10',
		`grid_height` int(11) NOT NULL default '10',
		`days_expire` mediumint(9) default '0',
		`price_per_block` float NOT NULL default '0',
		`name` VARCHAR( 255 ) NOT NULL, 
		PRIMARY KEY  (`banner_id`)
		) ;";

	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );

	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "banners` VALUES (1, 100, 100, 0, 100, 'Million Pixels (1000x1000)');";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "config", "key" ) ) {

	$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "config` (
		`key` VARCHAR( 255 ) NOT NULL ,
		`val` VARCHAR( 255 ) NOT NULL ,
		PRIMARY KEY ( `key` ) 
		)";

	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}
if ( ! does_field_exist( MDS_DB_PREFIX . "currencies", "code" ) ) {

	$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "currencies` (
		  `code` char(3) NOT NULL default '',
		  `name` varchar(50) NOT NULL default '',
		  `rate` decimal(20,10) NOT NULL default '1.0000000000',
		  `is_default` set('Y','N') NOT NULL default 'N',
		  `sign` varchar(8) NOT NULL default '',
		  `decimal_places` smallint(6) NOT NULL default '0',
		  `decimal_point` char(3) NOT NULL default '',
		  `thousands_sep` char(3) NOT NULL default '',
		  `max_orders` MEDIUMINT NOT NULL,
		  `description` VARCHAR( 255 ) NOT NULL,
		  PRIMARY KEY  (`code`)
		) ";

	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );

	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "currencies` VALUES ('AUD', 'Australian Dollar', 1.3228, 'N', '$', 2, '.', ',')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "currencies` VALUES ('CAD', 'Canadian Dollar', 1.1998, 'N', '$', 2, '.', ',')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "currencies` VALUES ('EUR', 'Euro', 0.8138, 'N', '€', 2, '.', ',')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "currencies` VALUES ('GBP', 'British Pound', 0.5555, 'N', '£', 2, '.', ',')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "currencies` VALUES ('JPY', 'Japanese Yen', 110.1950, 'N', '¥', 0, '.', ',')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "currencies` VALUES ('KRW', 'Korean Won', 1028.8000, 'N', '&#8361;', 0, '.', ',')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "currencies` VALUES ('USD', 'U.S. Dollar', 1.0000, 'Y', '$', 2, '.', ',')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "blocks", "banner_id" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "blocks` ADD `banner_id` INT DEFAULT '1' NOT NULL";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "blocks` DROP PRIMARY KEY , ADD PRIMARY KEY ( `block_id` , `banner_id` )";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "orders", "banner_id" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` ADD `banner_id` INT DEFAULT '1' NOT NULL";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "banners", "currency" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "banners` ADD `currency` CHAR(3) DEFAULT 'USD' NOT NULL";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" . mysqli_error( $GLOBALS['connection'] ) );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "blocks", "currency" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "blocks` ADD `currency` VARCHAR(3) DEFAULT 'USD' NOT NULL";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" . mysqli_error( $GLOBALS['connection'] ) );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "blocks", "price" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "blocks` ADD `price` float  NULL";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "orders", "currency" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` ADD `currency` CHAR(3) DEFAULT 'USD' NOT NULL";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );

	$sql = "select *, " . MDS_DB_PREFIX . "banners.price_per_block AS PPB, " . MDS_DB_PREFIX . "banners.currency BAC, " . MDS_DB_PREFIX . "orders.currency ORC from " . MDS_DB_PREFIX . "orders, " . MDS_DB_PREFIX . "banners where " . MDS_DB_PREFIX . "orders.banner_id=" . MDS_DB_PREFIX . "banners.banner_id ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	while ( $row = mysqli_fetch_array( $result ) ) {

		$blocks = explode( ",", $row['blocks'] );

		foreach ( $blocks as $block_id ) {

			if ( $block_id != '' ) {

				require_once( "../include/currency_functions.php" );

				$sql = "UPDATE " . MDS_DB_PREFIX . "blocks SET price=" . floatval( convert_to_currency( $row['PPB'], $row['BAC'], $row['ORC'] ) ) . " WHERE block_id=" . intval( $block_id );
				mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
			}
		}
	}
}

if ( ! does_field_exist( MDS_DB_PREFIX . "orders", "date_published" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` ADD date_published DATETIME  NULL";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "orders", "date_stamp" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` ADD `date_stamp` DATETIME;";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "orders", "days_expire" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` ADD `days_expire` INT DEFAULT 0;";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

/*if (!does_field_exist("banners", "publish_date")) {

	$sql = "ALTER TABLE `banners` ADD publish_date DATETIME default NULL";
	mysqli_query($GLOBALS['connection'], $sql) or die ("<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error($GLOBALS['connection']) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>");

}*/

if ( ! does_field_exist( MDS_DB_PREFIX . "banners", "time_stamp" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "banners` ADD `time_stamp` INT NOT NULL";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "blocks", "order_id" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "blocks` ADD order_id INT NOT NULL";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );

	$sql    = "select * from " . MDS_DB_PREFIX . "orders ";
	$result = mysqli_query( $GLOBALS['connection'], $sql );
	while ( $row = mysqli_fetch_array( $result ) ) {

		$blocks = explode( ",", $row['blocks'] );

		//print_r ($blocks);

		foreach ( $blocks as $block_id ) {

			if ( $block_id != '' ) {

				$sql = "UPDATE " . MDS_DB_PREFIX . "blocks set order_id=" . intval( $row['order_id'] ) . " WHERE block_id=" . intval( $block_id );
				mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
			}
		}
	}
}

if ( ! does_field_exist( MDS_DB_PREFIX . "transactions", "transaction_id" ) ) {

	if ( version_compare( $mysql_server_info, '5.6.5' ) >= 0 ) {
		$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "transactions` (
		`transaction_id` int(11) NOT NULL auto_increment,
		`date` datetime default CURRENT_TIMESTAMP,
		`order_id` int(11) NOT NULL default '0',
		`type` varchar(32) NOT NULL default '',
		`amount` float NOT NULL default '0',
		`currency` char(3) NOT NULL default '',
		`txn_id` varchar(128) NOT NULL default '',
		`reason` varchar(64) NOT NULL default '',
		`origin` varchar(32) NOT NULL default '',
		PRIMARY KEY  (`transaction_id`))";
	} else {
		$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "transactions` (
		`transaction_id` int(11) NOT NULL auto_increment,
		`date` datetime NOT NULL default '0000-00-00 00:00:00',
		`order_id` int(11) NOT NULL default '0',
		`type` varchar(32) NOT NULL default '',
		`amount` float NOT NULL default '0',
		`currency` char(3) NOT NULL default '',
		`txn_id` varchar(128) NOT NULL default '',
		`reason` varchar(64) NOT NULL default '',
		`origin` varchar(32) NOT NULL default '',
		PRIMARY KEY  (`transaction_id`))";
	}

	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "prices", "price_id" ) ) {

	$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "prices` (
		  `price_id` int(11) NOT NULL auto_increment,
		  `banner_id` int(11) NOT NULL default '0',
		  `row_from` int(11) NOT NULL default '0',
		  `row_to` int(11) NOT NULL default '0',
		  `block_id_from` int(11) NOT NULL default '0',
		  `block_id_to` int(11) NOT NULL default '0',
		  `price` float NOT NULL default '0',
		  `currency` char(3) NOT NULL default '',
		  `color` varchar(50) NOT NULL default '',
		  PRIMARY KEY  (`price_id`)
		)";

	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "mail_queue", 'mail_id' ) ) {
	if ( version_compare( $mysql_server_info, '5.6.5' ) >= 0 ) {
		$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "mail_queue` (
		`mail_id` int(11) NOT NULL auto_increment,
		`mail_date` datetime default CURRENT_TIMESTAMP,
		`to_address` varchar(128) NOT NULL default '',
		`to_name` varchar(128) NOT NULL default '',
		`from_address` varchar(128) NOT NULL default '',
		`from_name` varchar(128) NOT NULL default '',
		`subject` varchar(255) NOT NULL default '',
		`message` text NOT NULL,
		`html_message` text NOT NULL,
		`attachments` set('Y','N') NOT NULL default '',
		`status` set('queued','sent','error') NOT NULL default '',
		`error_msg` varchar(255) NOT NULL default '',
		`retry_count` smallint(6) NOT NULL default '0',
		`template_id` int(11) NOT NULL default '0',
		`att1_name` varchar(128) NOT NULL default '',
		`att2_name` varchar(128) NOT NULL default '',
		`att3_name` varchar(128) NOT NULL default '',
		`date_stamp` datetime default CURRENT_TIMESTAMP,
		PRIMARY KEY  (`mail_id`)) ";
	} else {
		$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "mail_queue` (
		`mail_id` int(11) NOT NULL auto_increment,
		`mail_date` datetime NOT NULL default '0000-00-00 00:00:00',
		`to_address` varchar(128) NOT NULL default '',
		`to_name` varchar(128) NOT NULL default '',
		`from_address` varchar(128) NOT NULL default '',
		`from_name` varchar(128) NOT NULL default '',
		`subject` varchar(255) NOT NULL default '',
		`message` text NOT NULL,
		`html_message` text NOT NULL,
		`attachments` set('Y','N') NOT NULL default '',
		`status` set('queued','sent','error') NOT NULL default '',
		`error_msg` varchar(255) NOT NULL default '',
		`retry_count` smallint(6) NOT NULL default '0',
		`template_id` int(11) NOT NULL default '0',
		`att1_name` varchar(128) NOT NULL default '',
		`att2_name` varchar(128) NOT NULL default '',
		`att3_name` varchar(128) NOT NULL default '',
		`date_stamp` datetime NOT NULL default '0000-00-00 00:00:00',
		PRIMARY KEY  (`mail_id`)) ";
	}

	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "prices", "col_from" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "prices` ADD col_from int(11) default 0";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "prices", "col_to" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "prices` ADD col_to int(11) default 100";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "blocks", "click_count" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "blocks` ADD `click_count` INT NOT NULL ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "orders", "expiry_notice_sent" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` ADD `expiry_notice_sent` SET( 'Y', 'N' ) NOT NULL ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "banners", "max_orders" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "banners` ADD `max_orders` INT(11) NOT NULL DEFAULT 5 ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "orders", "package_id" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` ADD `package_id` INT(11) NOT NULL default 0";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "clicks", "block_id" ) ) {

	if ( version_compare( $mysql_server_info, '5.6.5' ) >= 0 ) {
		$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "clicks` (
			`banner_id` INT NOT NULL ,
			`block_id` INT NOT NULL ,
			`user_id` INT NOT NULL ,
			`date` date NOT NULL default '0000-00-00',
			`clicks` INT NOT NULL ,
			PRIMARY KEY ( `banner_id` , `block_id` ,  `date` ) 
			)";
	} else {

		$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "clicks` (
			`banner_id` INT NOT NULL ,
			`block_id` INT NOT NULL ,
			`user_id` INT NOT NULL ,
			`date` date default '1970-01-01',
			`clicks` INT NOT NULL ,
			PRIMARY KEY ( `banner_id` , `block_id` ,  `date` ) 
			)";
	}

	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "packages", "banner_id" ) ) {
	$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "packages` (
		`banner_id` INT NOT NULL ,
		`days_expire` INT NOT NULL ,
		`price` FLOAT NOT NULL ,
		`currency` VARCHAR( 3 ) NOT NULL ,
		`package_id` INT NOT NULL AUTO_INCREMENT ,
		`is_default` SET ('Y', 'N'),
		`max_orders` mediumint(9) NOT NULL default '0',
		`description` varchar(255) NOT NULL default '',
		PRIMARY KEY ( `package_id` ) 
		)";

	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "packages", "max_orders" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "packages` ADD `max_orders` mediumint(9) NOT NULL default '0'";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "packages", "description" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "packages` ADD `description` varchar(255) NOT NULL default ''";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

//$sql = "drop table form_fields ";
//mysqli_query($GLOBALS['connection'], $sql);

if ( ! does_field_exist( MDS_DB_PREFIX . "form_fields", "field_id" ) ) {

	//echo "create ff<Br>";

	$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "form_fields` (
		`form_id` int(11) NOT NULL default '0',
		`field_id` int(11) NOT NULL auto_increment,
		`section` tinyint(4) NOT NULL default '1',
		`reg_expr` varchar(255) NOT NULL default '',
		`field_label` varchar(255) NOT NULL default '-noname-',
		`field_type` varchar(255) NOT NULL default 'TEXT',
		`field_sort` tinyint(4) NOT NULL default '0',
		`is_required` set('Y','N') NOT NULL default 'N',
		`display_in_list` set('Y','N') NOT NULL default 'N',
		`is_in_search` set('Y','N') NOT NULL default 'N',
		`error_message` varchar(255) NOT NULL default '',
		`field_init` varchar(255) NOT NULL default '',
		`field_width` tinyint(4) NOT NULL default '20',
		`field_height` tinyint(4) NOT NULL default '0',
		`list_sort_order` tinyint(4) NOT NULL default '0',
		`search_sort_order` tinyint(4) NOT NULL default '0',
		`template_tag` varchar(255) NOT NULL default '',
		`is_hidden` char(1) NOT NULL default '',
		`is_anon` char(1) NOT NULL default '',
		`field_comment` text NOT NULL,
		`category_init_id` int(11) NOT NULL default '0',
		`is_cat_multiple` set('Y','N') NOT NULL default 'N',
		`cat_multiple_rows` tinyint(4) NOT NULL default '1',
		`is_blocked` char(1) NOT NULL default 'N',
		`multiple_sel_all` char(1) NOT NULL default 'N',
		`is_prefill` char(1) NOT NULL default 'N',
		PRIMARY KEY  (`field_id`)
		) ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "form_fields` VALUES (1, 1, 1, 'not_empty', 'Ad Text', 'TEXT', 1, 'Y', '', '', 'was not filled in', '', 80, 0, 0, 0, 'ALT_TEXT', '', '', '', 0, '', 0, '', '', '')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "form_fields` VALUES (1, 2, 1, 'url', 'URL', 'TEXT', 2, 'Y', '', '', 'is not valid.', 'https://', 80, 0, 0, 0, 'URL', '', '', '', 0, '', 0, '', '', '')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "form_fields` VALUES (1, 3, 1, '', 'Additional Image', 'IMAGE', 3, '', '', '', '', '', 0, 0, 0, 0, 'IMAGE', '', '', '(This image will be displayed in a tooltip popup when your blocks are clicked)', 0, '', 0, '', '', '')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}
if ( ! does_field_exist( MDS_DB_PREFIX . "form_field_translations", "field_id" ) ) {
	$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "form_field_translations` (
		`field_id` int(11) NOT NULL default '0',
		`lang` char(2) NOT NULL default '',
		`field_label` text NOT NULL,
		`error_message` varchar(255) NOT NULL default '',
		`field_comment` text NOT NULL,
		PRIMARY KEY  (`field_id`,`lang`),
		KEY `field_id` (`field_id`)
		)";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );

	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "form_field_translations` VALUES (1, 'EN', 'Ad Text', 'was not filled in', '')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "form_field_translations` VALUES (2, 'EN', 'URL', 'is not valid.', '')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "form_field_translations` VALUES (3, 'EN', 'Additional Image', '', '(This image will be displayed in a tooltip popup when your blocks are clicked)')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );

	format_field_translation_table( 1 );
}
if ( ! does_field_exist( MDS_DB_PREFIX . "form_lists", "form_id" ) ) {
	$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "form_lists` (
		`form_id` int(11) NOT NULL default '0',
		`field_type` varchar(255) NOT NULL default '',
		`sort_order` int(11) NOT NULL default '0',
		`field_id` varchar(255) NOT NULL default '0',
		`template_tag` varchar(255) NOT NULL default '',
		`column_id` int(11) NOT NULL auto_increment,
		`admin` set('Y','N') NOT NULL default '',
		`truncate_length` smallint(4) NOT NULL default '0',
		`linked` set('Y','N') NOT NULL default 'N',
		`clean_format` set('Y','N') NOT NULL default '',
		`is_bold` set('Y','N') NOT NULL default '',
		`is_sortable` set('Y','N') NOT NULL default 'N',
		`no_wrap` set('Y','N') NOT NULL default '',
		PRIMARY KEY  (`column_id`)
		)  ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );

	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "form_lists` VALUES (1, 'TIME', 1, 'ad_date', 'DATE', 1, 'N', 0, 'N', 'N', 'N', 'Y', 'N')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "form_lists` VALUES (1, 'EDITOR', 2, '1', 'ALT_TEXT', 2, 'N', 0, 'Y', 'N', 'N', 'Y', 'N')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
	$sql = "INSERT INTO `" . MDS_DB_PREFIX . "form_lists` VALUES (1, 'TEXT', 3, '2', 'URL', 3, 'N', 0, 'N', 'N', 'N', 'N', 'N')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "banners", "block_height" ) ) {
	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "banners` ADD `block_width` INT NOT NULL default 10 ,
		ADD `block_height` INT NOT NULL default 10 ,
		ADD `grid_block` TEXT NOT NULL ,
		ADD `nfs_block` TEXT NOT NULL ,
		ADD `tile` TEXT NOT NULL ,
		ADD `usr_grid_block` TEXT NOT NULL ,
		ADD `usr_nfs_block` TEXT NOT NULL ,
		ADD `usr_ord_block` TEXT NOT NULL ,
		ADD `usr_res_block` TEXT NOT NULL ,
		ADD `usr_sel_block` TEXT NOT NULL ,
		ADD `usr_sol_block` TEXT NOT NULL,
		ADD `max_blocks` INT NOT NULL default 10,
		ADD `min_blocks` INT NOT NULL default 0;";

	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "banners", "date_updated" ) ) {

	if ( version_compare( $mysql_server_info, '5.6.5' ) >= 0 ) {
		$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "banners` ADD `date_updated` datetime ";
	} else {
		$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "banners` ADD `date_updated` DATETIME NOT NULL ";
	}

	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "banners", "bgcolor" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "banners` ADD `bgcolor` VARCHAR(7) NOT NULL default '#FFFFFF' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "banners", "auto_publish" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "banners` ADD `auto_publish` CHAR(1) NOT NULL default 'N' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "banners", "auto_approve" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "banners` ADD `auto_approve` CHAR(1) NOT NULL default 'N' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "temp_orders", "session_id" ) ) {

	if ( version_compare( $mysql_server_info, '5.6.5' ) >= 0 ) {
		$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "temp_orders` (
		  `session_id` varchar(32) NOT NULL default '',
		  `blocks` text NOT NULL,
		  `order_date` datetime default CURRENT_TIMESTAMP,
		  `price` float NOT NULL default '0',
		  `quantity` int(11) NOT NULL default '0',
		  `banner_id` int(11) NOT NULL default '1',
		  `currency` char(3) NOT NULL default 'USD',
		  `days_expire` int(11) NOT NULL default '0',
		  `date_stamp` datetime default CURRENT_TIMESTAMP,
		  `package_id` int(11) NOT NULL default '0',
		  `ad_id` int(11) default '0',
			 `block_info` TEXT NOT NULL,
		  PRIMARY KEY  (`session_id`)
		)";
	} else {
		$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "temp_orders` (
		  `session_id` varchar(32) NOT NULL default '',
		  `blocks` text NOT NULL,
		  `order_date` datetime NOT NULL default '0000-00-00 00:00:00',
		  `price` float NOT NULL default '0',
		  `quantity` int(11) NOT NULL default '0',
		  `banner_id` int(11) NOT NULL default '1',
		  `currency` char(3) NOT NULL default 'USD',
		  `days_expire` int(11) NOT NULL default '0',
		  `date_stamp` datetime default NULL,
		  `package_id` int(11) NOT NULL default '0',
		  `ad_id` int(11) default '0',
			 `block_info` TEXT NOT NULL,
		  PRIMARY KEY  (`session_id`)
		)";
	}
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "orders", "ad_id" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` ADD `ad_id` INT";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "orders", "original_order_id" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` ADD `original_order_id` INT";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` CHANGE `status` `status` SET( 'pending', 'completed', 'cancelled', 'confirmed', 'new', 'expired', 'deleted', 'renew_wait', 'renew_paid') NOT NULL ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "orders", "subscr_status" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` ADD `subscr_status` VARCHAR( 32 ) NOT NULL ;";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "blocks", "ad_id" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "blocks` ADD `ad_id` INT(11) NOT NULL default '0'";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "codes", "field_id" ) ) {

	$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "codes` (
				  `field_id` varchar(30) NOT NULL default '',
				  `code` varchar(5) NOT NULL default '',
				  `description` varchar(30) NOT NULL default '',
				  PRIMARY KEY  (`field_id`,`code`)
				)";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "codes_translations", "field_id" ) ) {

	$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "codes_translations` (
  `field_id` int(11) NOT NULL default '0',
  `code` varchar(10) NOT NULL default '',
  `description` varchar(255) NOT NULL default '',
  `lang` char(2) NOT NULL default '',
  PRIMARY KEY  (`field_id`,`code`,`lang`)
)";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "orders", "approved" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` ADD `approved` SET('Y','N') NOT NULL default 'N'";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );

	$sql = "select * from " . MDS_DB_PREFIX . "blocks group by order_id ";
	$res = mysqli_query( $GLOBALS['connection'], $sql );

	while ( $row = mysqli_fetch_array( $res ) ) {
		$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET approved='" . $row['approved'] . "' WHERE order_id='" . $row['order_id'] . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	}
}

if ( ! does_field_exist( MDS_DB_PREFIX . "orders", "published" ) ) {

	$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` ADD `published` set('Y','N') NOT NULL default ''";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );

	$sql = "select * from " . MDS_DB_PREFIX . "blocks group by order_id ";
	$res = mysqli_query( $GLOBALS['connection'], $sql );

	while ( $row = mysqli_fetch_array( $res ) ) {

		$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET published='" . mysqli_real_escape_string( $GLOBALS['connection'], $row['published'] ) . "' WHERE order_id='" . intval( $row['order_id'] ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	}
}

if ( ! does_field_exist( MDS_DB_PREFIX . "categories", "category_id" ) ) {

	$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "categories` (
  `category_id` int(11) NOT NULL default '0',
  `category_name` varchar(255) NOT NULL default '',
  `parent_category_id` int(11) NOT NULL default '0',
  `obj_count` int(11) NOT NULL default '0',
  `form_id` int(11) NOT NULL default '0',
  `allow_records` set('Y','N') NOT NULL default 'Y',
  `list_order` smallint(6) NOT NULL default '1',
  `search_set` text NOT NULL,
  `seo_fname` varchar(100) default NULL,
  `seo_title` varchar(255) default NULL,
  `seo_desc` varchar(255) default NULL,
  `seo_keys` varchar(255) default NULL,
  PRIMARY KEY  (`category_id`),
  KEY `composite_index` (`parent_category_id`,`category_id`))";

	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );
}

if ( ! does_field_exist( MDS_DB_PREFIX . "ads", "ad_id" ) ) {

	require_once( '../include/ads.inc.php' );

	if ( version_compare( $mysql_server_info, '5.6.5' ) >= 0 ) {
		$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "ads` (
			`ad_id` int(11) NOT NULL,
			`user_id` varchar(255) NOT NULL default '0',
			`ad_date` datetime default CURRENT_TIMESTAMP,
			`order_id` int(11) default '0',
			`banner_id` int(11) NOT NULL default '0',
			`1` varchar(255) NOT NULL default '',
			`2` varchar(255) NOT NULL default '',
			`3` varchar(255) NOT NULL default '',
			PRIMARY KEY  (`ad_id`)
			) ";
	} else {
		$sql = "CREATE TABLE `" . MDS_DB_PREFIX . "ads` (
			`ad_id` int(11) NOT NULL,
			`user_id` varchar(255) NOT NULL default '0',
			`ad_date` datetime NOT NULL default '0000-00-00 00:00:00',
			`order_id` int(11) default '0',
			`banner_id` int(11) NOT NULL default '0',
			`1` varchar(255) NOT NULL default '',
			`2` varchar(255) NOT NULL default '',
			`3` varchar(255) NOT NULL default '',
			PRIMARY KEY  (`ad_id`)
			) ";
	}

	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>" . mysqli_error( $GLOBALS['connection'] ) . "<br>Please run the following query manually from PhpMyAdmin:</b><br><pre>$sql</pre><br>" );

	// populate the ads table

	if ( $_SESSION['LANG'] == '' ) {
		$_SESSION['LANG'] = 'EN';
	}

	$sql = "select * from " . MDS_DB_PREFIX . "blocks group by order_id ";
	$res = mysqli_query( $GLOBALS['connection'], $sql );
	//echo $sql."<br>";
	while ( $row = mysqli_fetch_array( $res ) ) {

		$_REQUEST[ $ad_tag_to_field_id['URL']['field_id'] ]      = addslashes( $row['url'] );
		$_REQUEST[ $ad_tag_to_field_id['ALT_TEXT']['field_id'] ] = addslashes( $row['alt_text'] );
		$_REQUEST['order_id']                                    = $row['order_id'];
		$_REQUEST['BID']                                         = $row['banner_id'];
		//$_REQUEST['user_id'] = $row['user_id'];

		$_SESSION['MDS_ID'] = $row['user_id'];
		$ad_id              = insert_ad_data();
		$_SESSION['MDS_ID'] = '';

		// update the new orders and blocks fields.

		$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET ad_id='" . intval( $ad_id ) . "' WHERE order_id='" . intval( $row['order_id'] ) . "' ";

		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		$sql = "UPDATE " . MDS_DB_PREFIX . "blocks SET ad_id='" . intval( $ad_id ) . "' WHERE order_id='" . intval( $row['order_id'] ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	}
}

$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "config` WHERE `key`='DELETE_CHECKED' ";
$res = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
$row = mysqli_fetch_array( $res );
if ( isset( $row ) && $row['val'] == '' ) {
	$sql    = "SELECT * from " . MDS_DB_PREFIX . "orders where status='deleted' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql );
	while ( $order_row = mysqli_fetch_array( $result ) ) {

		if ( $order_row['blocks'] != '' ) {

			$blocks = explode( ",", $order_row['blocks'] );
			foreach ( $blocks as $key => $val ) {
				if ( $val != '' ) {
					$sql = "DELETE FROM " . MDS_DB_PREFIX . "blocks where block_id='" . intval( $val ) . "' and banner_id='" . intval( $order_row['banner_id'] ) . "'";
					mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
				}
			}
		}
	}
	echo "Database Fixed.";

	$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, `val`) VALUES ('DELETE_CHECKED','Y') ";
	mysqli_query( $GLOBALS['connection'], $sql );
}

$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "config` WHERE `key`='EXPIRE_RUNNING' ";
$res = mysqli_query( $GLOBALS['connection'], $sql );
$row = mysqli_fetch_array( $res );
if ( isset( $row ) && $row['val'] == '' ) {
	$sql = "REPLACE INTO `" . MDS_DB_PREFIX . "config` (`key`, `val`) VALUES ('EXPIRE_RUNNING', 'NO') ";
	mysqli_query( $GLOBALS['connection'], $sql );
}

$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "config` WHERE `key`='SELECT_RUNNING' ";
$res = mysqli_query( $GLOBALS['connection'], $sql );
$row = mysqli_fetch_array( $res );
if ( isset( $row ) && $row['val'] == '' ) {
	$sql = "REPLACE INTO `" . MDS_DB_PREFIX . "config` (`key`, `val`) VALUES ('SELECT_RUNNING', 'NO') ";
	mysqli_query( $GLOBALS['connection'], $sql );
}

$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "config` WHERE `key`='MAIL_QUEUE_RUNNING' ";
$res = mysqli_query( $GLOBALS['connection'], $sql );
$row = mysqli_fetch_array( $res );
if ( isset( $row ) && $row['val'] == '' ) {
	$sql = "REPLACE INTO `" . MDS_DB_PREFIX . "config` (`key`, `val`) VALUES ('MAIL_QUEUE_RUNNING', 'NO') ";
	mysqli_query( $GLOBALS['connection'], $sql );
}

$lang_filename = "english.php";

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "lang  ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

$done = 0;
while ( $row = mysqli_fetch_array( $result ) ) {

	$lang_filename = $row['lang_filename'];

	if ( is_writable( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/$lang_filename" ) ) {
		//echo "- lang/$lang_filename file is writeable. (OK)<br>";
		$f1 = stat( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/$lang_filename" );
		$f2 = stat( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/english_default.php" );
		if ( $f2['mtime'] > $f1['mtime'] ) {

			echo "Merging language strings for $lang_filename...";

			include( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/english_default.php" );
			$source_label = $label; // default english labels
			include( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/" . $lang_filename );
			$dest_label = $label; // dest labels

			$out = "<?php\n";
			foreach ( $source_label as $key => $val ) {
				//$source_label[$key] = addslashes($dest_label[$key]);
				$source_label[ $key ] = str_replace( "'", "\'", $dest_label[ $key ] );
				$out                  .= "\$label['$key']='" . $source_label[ $key ] . "'; \n";
			}
			$out .= "?>\n";

			$handler = fopen( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/" . $lang_filename, "w" );
			fputs( $handler, $out );
			fclose( $handler );

			echo " Done.";

			$done = 1;
		}
	} else {
		echo "- " . \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/$lang_filename.php file is not writable. Give write permissions (666) to the file.<br>";
	}
}

if ( $done ) {

	// fix dest file:
	// this replaces the \\\" with " 
	// a bug that was in version 1.6.5 created the broken links..

	include( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/" . $lang_filename );
	$out = "<?php\n";
	foreach ( $label as $key => $val ) {
		$label[ $key ] = str_replace( "'", "\'", $label[ $key ] );
		$label[ $key ] = preg_replace( "/(\\\)+\"/", '"', $label[ $key ] );

		$out .= "\$label['$key']='" . $label[ $key ] . "'; \n";
	}
	$out .= "?>\n";

	$handler = fopen( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/" . $lang_filename, "w" );
	fputs( $handler, $out );
	fclose( $handler );
}

if ( ! is_writable( \MillionDollarScript\Classes\Utility::get_upload_path() ) ) {
	echo "- " . \MillionDollarScript\Classes\Utility::get_upload_path() . " directory is not writable. Give write permissions (777) to the directory.<br>";
}

if ( ! is_writable( \MillionDollarScript\Classes\Utility::get_upload_path() . "/images/" ) ) {
	echo "- " . \MillionDollarScript\Classes\Utility::get_upload_path() . "/images/ directory is not writable. Give write permissions (777) to the directory.<br>";
}

?>