<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.6
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
use MillionDollarScript\Classes\Currency;
use MillionDollarScript\Classes\Emails;
use MillionDollarScript\Classes\Language;
use MillionDollarScript\Classes\Mail;
use MillionDollarScript\Classes\Utility;

defined( 'ABSPATH' ) or exit;

require_once( 'area_map_functions.php' );
require_once( 'package_functions.php' );
require_once( 'banner_functions.php' );
require_once( 'image_functions.php' );

// Written for having magic quotes enabled
// function unfck( $v ) {
// 	return is_array( $v ) ? array_map( 'unfck', $v ) : addslashes( $v );
// }

// function unfck_gpc() {
// 	foreach ( array( 'POST', 'GET', 'REQUEST', 'COOKIE' ) as $gpc ) {
// 		$GLOBALS["_$gpc"] = array_map( 'unfck', $GLOBALS["_$gpc"] );
// 	}
// }

function expire_orders(): void {
	global $wpdb;

	$now       = current_time( 'mysql' );
	$unix_time = time();

	// get the time of last run
	$last_expire_run = Config::get( 'LAST_EXPIRE_RUN' );

	if ( @mysqli_affected_rows( $GLOBALS['connection'] ) == 0 ) {

		// make sure it cannot be locked for more than 30 secs
		// This is in case the process fails inside the lock
		// and does not release it.

		if ( $unix_time > $last_expire_run + 30 ) {
			// release the lock

			// update timestamp
			$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`config_key`, `val`) VALUES ('LAST_EXPIRE_RUN', '$unix_time')  ";
			@mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		}

		// this function is already executing in another process.
		return;
	}

	// Delete New Orders that have been around too long
	// $session_duration = intval( ini_get( "session.gc_maxlifetime" ) );
	//
	// $sql = "SELECT order_id, order_date FROM `" . MDS_DB_PREFIX . "orders` WHERE `status`='new' AND DATE_SUB('$now', INTERVAL $session_duration SECOND) >= order_date";
	// $result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
	// while ( $row = @mysqli_fetch_array( $result ) ) {
	// 	delete_temp_order( $row['order_id'] );
	// }

	$affected_BIDs = array();

	// COMPLETED Orders
	$sql    = $wpdb->prepare(
		"SELECT *, " . MDS_DB_PREFIX . "banners.banner_id as BID 
    FROM " . MDS_DB_PREFIX . "orders, " . MDS_DB_PREFIX . "banners 
    WHERE `status` = 'completed' 
    AND " . MDS_DB_PREFIX . "orders.banner_id = " . MDS_DB_PREFIX . "banners.banner_id 
    AND " . MDS_DB_PREFIX . "orders.days_expire <> 0 
    AND DATE_SUB(%s, INTERVAL " . MDS_DB_PREFIX . "orders.days_expire DAY) >= " . MDS_DB_PREFIX . "orders.date_published 
    AND " . MDS_DB_PREFIX . "orders.date_published IS NOT NULL",
		$now
	);
	$result = $wpdb->get_results( $sql, ARRAY_A );

	foreach ( $result as $row ) {
		$affected_BIDs[] = $row['BID'];
		// $expire_days     = intval( $row['days_expire'] );
		// $order_date      = $row['order_date'];
		// $date_published  = $row['date_published'];
		//
		// $order_date_timestamp     = strtotime( $order_date );
		// $date_published_timestamp = strtotime( $date_published );
		//
		// // Calculate the difference in days
		// $diff_days = floor( ( $order_date_timestamp - $date_published_timestamp ) / DAY_IN_SECONDS );
		// error_log('$diff_days: ' . var_export($diff_days, true));
		//
		// if ( $diff_days > $expire_days ) {
		// 	error_log( 'expire1' );
		expire_order( $row['order_id'] );
		// }
	}

	if ( sizeof( $affected_BIDs ) > 0 ) {
		foreach ( $affected_BIDs as $myBID ) {
			$b_row = load_banner_row( $myBID );
			if ( $b_row['auto_publish'] == 'Y' ) {
				process_image( $myBID );
				publish_image( $myBID );
				process_map( $myBID );
			}
		}
	}
	process_paid_renew_orders();
	unset( $affected_BIDs );

	// unconfirmed Orders
	$MINUTES_UNCONFIRMED = Config::get( 'MINUTES_UNCONFIRMED' );
	if ( $MINUTES_UNCONFIRMED != 0 ) {

		$sql = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE `status`=%s", 'new' );
		if ( $MINUTES_UNCONFIRMED != - 1 ) {
			$sql .= $wpdb->prepare( " AND DATE_SUB(%s, INTERVAL %d MINUTE) >= date_stamp AND date_stamp IS NOT NULL", $now, intval( $MINUTES_UNCONFIRMED ) );
		}
		$results = $wpdb->get_results( $sql, ARRAY_A );

		foreach ( $results as $row ) {

			// Double-check the date before deleting
			// $order_date = strtotime( $row['order_date'] );
			// $order_date = date( 'Y-m-d H:i:s', $order_date );
			// $diff       = strtotime( $order_date ) - strtotime( $now );
			// if ( $diff > intval( $MINUTES_UNCONFIRMED ) ) {
			delete_order( $row['order_id'] );

			// Now really delete the order.
			$sql = $wpdb->prepare( "DELETE FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d", intval( $row['order_id'] ) );
			$wpdb->query( $sql );
			global $f2;
			$f2->debug( "Deleted unconfirmed order - " . $sql );
			// }
		}
	}

	// unpaid Orders
	$MINUTES_CONFIRMED = Config::get( 'MINUTES_CONFIRMED' );
	if ( $MINUTES_CONFIRMED != 0 ) {

		$additional_condition = "";
		if ( $MINUTES_CONFIRMED != - 1 ) {
			$additional_condition = $wpdb->prepare( " AND DATE_SUB(%s, INTERVAL %d MINUTE) >= date_stamp AND date_stamp IS NOT NULL", $now, intval( $MINUTES_CONFIRMED ) );
		}

		$sql     = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE `status` = 'confirmed' %s", $additional_condition );
		$results = $wpdb->get_results( $sql, ARRAY_A );

		foreach ( $results as $row ) {

			// $order_date = strtotime( $row['order_date'] );
			// $order_date = date( 'Y-m-d H:i:s', $order_date );
			// $diff       = strtotime( $order_date ) - strtotime( $now );
			// if ( $diff > intval( $MINUTES_CONFIRMED ) ) {
			expire_order( $row['order_id'] );
			// }
		}
	}

	// EXPIRED Orders -> Cancel
	$MINUTES_RENEW = Config::get( 'MINUTES_RENEW' );
	if ( $MINUTES_RENEW != 0 ) {

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE `status`='expired'";
		if ( $MINUTES_RENEW != - 1 ) {
			$sql .= " AND DATE_SUB('$now',INTERVAL " . intval( $MINUTES_RENEW ) . " MINUTE) >= date_stamp AND date_stamp IS NOT NULL";
		}

		$result = @mysqli_query( $GLOBALS['connection'], $sql );

		while ( $row = @mysqli_fetch_array( $result ) ) {
			// $order_date = strtotime( $row['order_date'] );
			// $order_date = date( 'Y-m-d H:i:s', $order_date );
			// $diff       = strtotime( $order_date ) - strtotime( $now );
			// if ( $diff > intval( $MINUTES_RENEW ) ) {
			cancel_order( $row['order_id'] );
			// }
		}
	}

	// Cancelled Orders -> Delete
	$MINUTES_CANCEL = Config::get( 'MINUTES_CANCEL' );
	if ( $MINUTES_CANCEL != 0 ) {

		$MINUTES_CANCEL = intval( $MINUTES_CANCEL );
		$sql            = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE `status`='cancelled' AND DATE_SUB(%s, INTERVAL %d MINUTE) >= date_stamp AND date_stamp IS NOT NULL", $now, $MINUTES_CANCEL );
		$results        = $wpdb->get_results( $sql );
		foreach ( $results as $row ) {
			// $order_date = strtotime( $row['order_date'] );
			// $order_date = date( 'Y-m-d H:i:s', $order_date );
			// $diff       = strtotime( $order_date ) - strtotime( $now );
			// if ( $diff > $MINUTES_CANCEL ) {
			delete_order( $row['order_id'] );
			// }
		}
	}

	// update last run time stamp

	// update timestamp
	$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`config_key`, `val`) VALUES ('LAST_EXPIRE_RUN', '$unix_time')  ";
	@mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
}

function delete_temp_order( $sid, $delete_ad = true ) {

	$sid = mysqli_real_escape_string( $GLOBALS['connection'], $sid );

	$sql = "select * from " . MDS_DB_PREFIX . "orders where order_id='" . $sid . "' ";
	$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$order_row = mysqli_fetch_array( $order_result );

	//$sql = "DELETE FROM blocks WHERE session_id='".$sid."' ";
	//mysqli_query($GLOBALS['connection'], $sql) ;

	$sql = "DELETE FROM " . MDS_DB_PREFIX . "orders WHERE order_id='" . $sid . "' ";
	mysqli_query( $GLOBALS['connection'], $sql );

	if ( $delete_ad && isset( $order_row['ad_id'] ) ) {
		wp_delete_post( $order_row['ad_id'] );
	}

	// delete the temp order image... and block info...

	$f = get_tmp_img_name();
	if ( file_exists( $f ) ) {
		unlink( $f );
	}

	// reset session order id
	delete_current_order_id();
}

/*

Type:  CREDIT (subtract)

$txn_id = transaction id from 3rd party payment system

$reson = any reason such as chargeback, refund etc..

$origin = paypal, stormpay, admin, etc

$order_id = the corresponding order id.

*/

function credit_transaction( $order_id, $amount, $currency, $txn_id, $reason, $origin ) {

	$type = "CREDIT";

	$date = ( gmdate( "Y-m-d H:i:s" ) );

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "transactions where txn_id='" . mysqli_real_escape_string( $GLOBALS['connection'], $txn_id ) . "' and `type`='CREDIT' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
	if ( mysqli_num_rows( $result ) != 0 ) {
		return; // there already is a credit for this txn_id
	}

	// check to make sure that there is a debit for this transaction

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "transactions where txn_id='" . mysqli_real_escape_string( $GLOBALS['connection'], $txn_id ) . "' and `type`='DEBIT' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
	if ( mysqli_num_rows( $result ) > 0 ) {

		$sql = "INSERT INTO " . MDS_DB_PREFIX . "transactions (`txn_id`, `date`, `order_id`, `type`, `amount`, `currency`, `reason`, `origin`) VALUES('" . mysqli_real_escape_string( $GLOBALS['connection'], $txn_id ) . "', '$date', '" . intval( $order_id ) . "', '$type', '" . floatval( $amount ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $currency ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $reason ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $origin ) . "')";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mds_sql_error( $sql ) );
	}
}

/*

Type: DEBIT (add)

$txn_id = transaction id from 3rd party payment system

$reson = any reason such as chargeback, refund etc..

$origin = paypal, stormpay, admin, etc

$order_id = the corresponding order id.

*/

function debit_transaction( $order_id, $amount, $currency, $txn_id, $reason, $origin ) {

	$type = "DEBIT";
	$date = ( gmdate( "Y-m-d H:i:s" ) );
	// check to make sure that there is no debit for this transaction already

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "transactions where txn_id='" . mysqli_real_escape_string( $GLOBALS['connection'], $txn_id ) . "' and `type`='DEBIT' AND order_id=" . intval( $order_id );
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
	if ( mysqli_fetch_array( $result ) == 0 ) {
		$sql = "INSERT INTO " . MDS_DB_PREFIX . "transactions (`txn_id`, `date`, `order_id`, `type`, `amount`, `currency`, `reason`, `origin`) VALUES('" . mysqli_real_escape_string( $GLOBALS['connection'], $txn_id ) . "', '$date', '" . intval( $order_id ) . "', '$type', '" . floatval( $amount ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $currency ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $reason ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $origin ) . "')";

		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	}
}

function complete_order( $user_id, $order_id ) {
	confirm_order( $user_id, $order_id );

	$sql = "SELECT * from " . MDS_DB_PREFIX . "orders where order_id='" . intval( $order_id ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$order_row = mysqli_fetch_array( $result );

	if ( $order_row['status'] != 'completed' ) {
		$now = ( gmdate( "Y-m-d H:i:s" ) );

		$sql = "UPDATE " . MDS_DB_PREFIX . "orders set status='completed', date_published=NULL, date_stamp='$now' WHERE order_id='" . intval( $order_id ) . "'";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		// Update mds-pixel post status
		wp_update_post( [
			'ID'          => $order_row['ad_id'],
			'post_status' => 'completed',
		] );

		// insert a transaction

		// mark pixels as sold.

		$sql = "SELECT * from " . MDS_DB_PREFIX . "orders where order_id='" . intval( $order_id ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$order_row = mysqli_fetch_array( $result );

		if ( strpos( $order_row['blocks'], "," ) !== false ) {
			$blocks = explode( ",", $order_row['blocks'] );
		} else {
			$blocks = array( 0 => $order_row['blocks'] );
		}
		foreach ( $blocks as $key => $val ) {
			$sql = "UPDATE " . MDS_DB_PREFIX . "blocks set status='sold' where block_id='" . intval( $val ) . "' and banner_id=" . intval( $order_row['banner_id'] );
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		}

		$user_info = get_userdata( intval( $user_id ) );

		if ( $order_row['days_expire'] == 0 ) {
			$order_row['days_expire'] = Language::get( 'Never' );
		}

		$banner_data = load_banner_constants( $order_row['banner_id'] );
		$block_count = $order_row['quantity'] / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

		$price = Currency::convert_to_default_currency_formatted( $order_row['currency'], $order_row['price'] );

		$search = [
			'%SITE_NAME%',
			'%FIRST_NAME%',
			'%LAST_NAME%',
			'%USER_LOGIN%',
			'%ORDER_ID%',
			'%PIXEL_COUNT%',
			'%BLOCK_COUNT%',
			'%PIXEL_DAYS%',
			'%PRICE%',
			'%SITE_CONTACT_EMAIL%',
			'%SITE_URL%',
		];

		$replace = [
			get_bloginfo( 'name' ),
			$user_info->first_name,
			$user_info->last_name,
			$user_info->user_login,
			$order_row['order_id'],
			$order_row['quantity'],
			$block_count,
			$order_row['days_expire'],
			$price,
			get_bloginfo( 'admin_email' ),
			get_site_url(),
		];

		$subject = Emails::get_email_replace(
			$search,
			$replace,
			'order-completed-subject'
		);

		$message = Emails::get_email_replace(
			$search,
			$replace,
			'order-completed-content'
		);

		if ( Config::get( 'EMAIL_USER_ORDER_COMPLETED' ) == 'YES' ) {
			Mail::send( $user_info->user_email, $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 1 );
		}

		// send a copy to admin
		if ( Config::get( 'EMAIL_ADMIN_ORDER_COMPLETED' ) == 'YES' ) {
			Mail::send( get_bloginfo( 'admin_email' ), $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 1 );
		}

		// process the grid, if auto_publish is on

		$b_row = load_banner_row( $order_row['banner_id'] );

		if ( $b_row['auto_publish'] == 'Y' ) {
			process_image( $order_row['banner_id'] );
			publish_image( $order_row['banner_id'] );
			process_map( $order_row['banner_id'] );
		}
	}
}

function confirm_order( $user_id, $order_id ) {
	global $wpdb;

	$sql = "SELECT *, t1.blocks as BLK, t1.ad_id as AID FROM " . MDS_DB_PREFIX . "orders as t1, " . $wpdb->prefix . "users as t2 where t1.user_id=t2.ID AND order_id='" . intval( $order_id ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$row = mysqli_fetch_array( $result );

	if ( $row['status'] != 'confirmed' ) {
		$user_info = get_userdata( $row['ID'] );

		// Update mds-pixel post status
		wp_update_post( [
			'ID'          => $row['AID'],
			'post_status' => 'confirmed',
		] );

		$now = ( gmdate( "Y-m-d H:i:s" ) );

		$sql = "UPDATE " . MDS_DB_PREFIX . "orders set status='confirmed', date_stamp='$now' WHERE order_id='" . intval( $order_id ) . "' ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		$sql = "UPDATE " . MDS_DB_PREFIX . "blocks set status='ordered' WHERE order_id='" . intval( $order_id ) . "' and banner_id='" . intval( $row['banner_id'] ) . "'";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		delete_current_order_id();

		if ( $row['days_expire'] == 0 ) {
			$row['days_expire'] = Language::get( 'Never' );
		}

		$banner_data = load_banner_constants( $row['banner_id'] );
		$block_count = $row['quantity'] / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

		$price = Currency::convert_to_default_currency_formatted( $row['currency'], $row['price'] );

		$search = [
			'%SITE_NAME%',
			'%FIRST_NAME%',
			'%LAST_NAME%',
			'%USER_LOGIN%',
			'%ORDER_ID%',
			'%PIXEL_COUNT%',
			'%BLOCK_COUNT%',
			'%PIXEL_DAYS%',
			'%DEADLINE%',
			'%PRICE%',
			'%SITE_CONTACT_EMAIL%',
			'%SITE_URL%',
		];

		$replace = [
			get_bloginfo( 'name' ),
			$user_info->first_name,
			$user_info->last_name,
			$user_info->user_login,
			$row['order_id'],
			$row['quantity'],
			$block_count,
			$row['days_expire'],
			Config::get( 'MINUTES_CONFIRMED' ),
			$price,
			get_bloginfo( 'admin_email' ),
			get_site_url(),
		];

		$subject = Emails::get_email_replace(
			$search,
			$replace,
			'order-confirmed-subject'
		);

		$message = Emails::get_email_replace(
			$search,
			$replace,
			'order-confirmed-content'
		);

		if ( Config::get( 'EMAIL_USER_ORDER_CONFIRMED' ) == 'YES' ) {
			Mail::send( $user_info->user_email, $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 2 );
		}

		// send a copy to admin
		if ( Config::get( 'EMAIL_ADMIN_ORDER_CONFIRMED' ) == 'YES' ) {
			Mail::send( get_bloginfo( 'admin_email' ), $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 2 );
		}
	}
}

function pend_order( $user_id, $order_id ) {
	global $wpdb;
	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders as t1, " . $wpdb->prefix . "users as t2 where t1.user_id=t2.ID AND t1.user_id='" . intval( $user_id ) . "' AND order_id='" . intval( $order_id ) . "' ";

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$row = mysqli_fetch_array( $result );

	if ( $row['status'] != 'pending' ) {
		$user_info = get_userdata( $row['ID'] );

		// Update mds-pixel post status
		wp_update_post( [
			'ID'          => $row['ad_id'],
			'post_status' => 'pending',
		] );

		$now = ( gmdate( "Y-m-d H:i:s" ) );

		$sql = "UPDATE " . MDS_DB_PREFIX . "orders set status='pending', date_stamp='$now' WHERE order_id='" . intval( $order_id ) . "' ";
		//echo $sql;
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		$blocks = explode( ',', $row['blocks'] );
		//echo $order_row['blocks'];
		foreach ( $blocks as $key => $val ) {

			$sql = "UPDATE " . MDS_DB_PREFIX . "blocks set status='ordered' WHERE block_id='" . intval( $val ) . "' and banner_id='" . intval( $row['banner_id'] ) . "'";
			//echo $sql;
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		}

		if ( $row['days_expire'] == 0 ) {
			$row['days_expire'] = Language::get( 'Never' );
		}

		$banner_data = load_banner_constants( $row['banner_id'] );
		$block_count = $row['quantity'] / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

		$price = Currency::convert_to_default_currency_formatted( $row['currency'], $row['price'] );

		$search = [
			'%SITE_NAME%',
			'%FIRST_NAME%',
			'%LAST_NAME%',
			'%USER_LOGIN%',
			'%ORDER_ID%',
			'%PIXEL_COUNT%',
			'%BLOCK_COUNT%',
			'%PIXEL_DAYS%',
			'%PRICE%',
			'%SITE_CONTACT_EMAIL%',
			'%SITE_URL%',
		];

		$replace = [
			get_bloginfo( 'name' ),
			$user_info->first_name,
			$user_info->last_name,
			$user_info->user_login,
			$row['order_id'],
			$row['quantity'],
			$block_count,
			$row['days_expire'],
			$price,
			get_bloginfo( 'admin_email' ),
			get_site_url(),
		];

		$message = Emails::get_email_replace(
			$search,
			$replace,
			'order-pending-content'
		);

		$subject = Emails::get_email_replace(
			$search,
			$replace,
			'order-pending-subject'
		);

		if ( Config::get( 'EMAIL_USER_ORDER_PENDED' ) == 'YES' ) {
			Mail::send( $user_info->user_email, $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 3 );
		}

		// send a copy to admin
		if ( Config::get( 'EMAIL_ADMIN_ORDER_PENDED' ) == 'YES' ) {
			Mail::send( get_bloginfo( 'admin_email' ), $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 3 );
		}
	}
}

function expire_order( $order_id ) {
	global $wpdb;
	$sql = "SELECT *, t1.banner_id as BID, t1.user_id as UID, t1.ad_id as AID FROM " . MDS_DB_PREFIX . "orders as t1, " . $wpdb->prefix . "users as t2 where t1.user_id=t2.ID AND  order_id='" . intval( $order_id ) . "' ";
	//echo "$sql<br>";
	//days_expire

	//func_mail_error($sql." expire order");
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );

	if ( ( $row['status'] != 'expired' ) || ( $row['status'] != 'pending' ) ) {
		$user_info = get_userdata( $row['ID'] );

		// Update mds-pixel post status
		wp_update_post( [
			'ID'          => $row['AID'],
			'post_status' => 'expired',
		] );

		$now = ( gmdate( "Y-m-d H:i:s" ) );

		$sql = "UPDATE " . MDS_DB_PREFIX . "orders set status='expired', date_stamp='$now' WHERE order_id='" . intval( $order_id ) . "' ";
		//echo "$sql<br>";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		$sql = "UPDATE " . MDS_DB_PREFIX . "blocks set status='ordered', `approved`='N' WHERE order_id='" . intval( $order_id ) . "' and banner_id='" . intval( $row['BID'] ) . "'";
		//echo "$sql<br>";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql . " (expire order)" );

		// update approve status on orders.
		$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET `approved`='N' WHERE order_id='" . intval( $order_id ) . "'";
		//echo "$sql<br>";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql . " (expire order)" );

		// Set mds-pixel as pending
		wp_update_post( [
			'ID'          => $row['ad_id'],
			'post_status' => 'private',
		] );

		if ( $row['status'] == 'new' ) {
			// do not send email
			return;
		}

		if ( $row['days_expire'] == 0 ) {
			$row['days_expire'] = Language::get( 'Never' );
		}

		$banner_data = load_banner_constants( $row['banner_id'] );
		$block_count = $row['quantity'] / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

		$price = Currency::convert_to_default_currency_formatted( $row['currency'], $row['price'] );

		$search = [
			'%SITE_NAME%',
			'%FIRST_NAME%',
			'%LAST_NAME%',
			'%USER_LOGIN%',
			'%ORDER_ID%',
			'%PIXEL_COUNT%',
			'%BLOCK_COUNT%',
			'%PIXEL_DAYS%',
			'%PRICE%',
			'%SITE_CONTACT_EMAIL%',
			'%SITE_URL%',
		];

		$replace = [
			get_bloginfo( 'name' ),
			$user_info->first_name,
			$user_info->last_name,
			$user_info->user_login,
			$row['order_id'],
			$row['quantity'],
			$block_count,
			$row['days_expire'],
			$price,
			get_bloginfo( 'admin_email' ),
			get_site_url(),
		];

		$message = Emails::get_email_replace(
			$search,
			$replace,
			'order-expired-content'
		);

		$subject = Emails::get_email_replace(
			$search,
			$replace,
			'order-expired-subject'
		);

		$EMAIL_USER_ORDER_EXPIRED = Config::get( 'EMAIL_USER_ORDER_EXPIRED' );
		if ( $EMAIL_USER_ORDER_EXPIRED == 'YES' ) {
			Mail::send( $user_info->user_email, $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 4 );
		}

		// send a copy to admin
		$EMAIL_ADMIN_ORDER_EXPIRED = Config::get( 'EMAIL_ADMIN_ORDER_EXPIRED' );
		if ( $EMAIL_ADMIN_ORDER_EXPIRED == 'YES' ) {
			Mail::send( get_bloginfo( 'admin_email' ), $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 4 );
		}
	}
}

function delete_order( $order_id ): void {

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders where order_id='" . intval( $order_id ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$order_row = mysqli_fetch_array( $result );

	if ( $order_row['status'] != 'deleted' ) {

		// Update mds-pixel post status
		wp_update_post( [
			'ID'          => $order_row['ad_id'],
			'post_status' => 'deleted',
		] );

		$now = ( gmdate( "Y-m-d H:i:s" ) );

		$sql = "UPDATE " . MDS_DB_PREFIX . "orders set status='deleted', date_stamp='$now' WHERE order_id='" . intval( $order_id ) . "'";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		// DELETE BLOCKS

		if ( $order_row['blocks'] != '' ) {

			$sql = "DELETE FROM " . MDS_DB_PREFIX . "blocks where order_id='" . intval( $order_id ) . "' and banner_id=" . intval( $order_row['banner_id'] );
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		}

		// DELETE ADS
		wp_delete_post( $order_row['ad_id'] );
	}
}

function cancel_order( $order_id ) {

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders where order_id='" . intval( $order_id ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );
	//echo $sql."<br>";
	if ( $row['status'] != 'cancelled' ) {

		// Update mds-pixel post status
		wp_update_post( [
			'ID'          => $row['ad_id'],
			'post_status' => 'cancelled',
		] );

		$now = ( gmdate( "Y-m-d H:i:s" ) );

		$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET `status`='cancelled', date_stamp='$now', approved='N' WHERE order_id='" . intval( $order_id ) . "'";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		$sql = "UPDATE " . MDS_DB_PREFIX . "blocks SET `status`='cancelled', `approved`='N' WHERE order_id='" . intval( $order_id ) . "' AND banner_id='" . intval( $row['banner_id'] ) . "'";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql . " (cancel order) " );
	}

	// process the grid, if auto_publish is on
	$b_row = load_banner_row( $row['banner_id'] );
	if ( $b_row['auto_publish'] == 'Y' ) {
		process_image( $row['banner_id'] );
		publish_image( $row['banner_id'] );
		process_map( $row['banner_id'] );
	}
}

function unreserve_block( $block_id, $banner_id ) {
	$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "blocks` WHERE `block_id`='" . intval( $block_id ) . "' AND `banner_id`='" . intval( $banner_id ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
		if ( $row['status'] == 'reserved' ) {
			$sql = "DELETE FROM `" . MDS_DB_PREFIX . "blocks` WHERE `status`='reserved' AND `block_id`='" . intval( $block_id ) . "' AND `banner_id`='" . $row['banner_id'] . "'";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql . " (cancel order) " );
		}
	}

	// process the grid, if auto_publish is on
	$b_row = load_banner_row( $row['banner_id'] );
	if ( $b_row['auto_publish'] == 'Y' ) {
		process_image( $row['banner_id'] );
		publish_image( $row['banner_id'] );
		process_map( $row['banner_id'] );
	}
}

// is the renewal order already paid?
// (Orders can be paid and cont be completed until the previous order expires)
/*function is_renew_order_paid( $original_order_id ) {

	$sql = "SELECT * from " . MDS_DB_PREFIX . "orders WHERE original_order_id='" . intval( $original_order_id ) . "' AND status='renew_paid' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	if ( mysqli_num_rows( $result ) > 0 ) {
		return true;
	} else {
		return false;
	}
}*/

// returns $order_id of the 'renew_wait' order
// only one 'renew_wait' wait order allowed for each $original_order_id
// and there must be no 'renew_paid' orders
/*function allocate_renew_order( $original_order_id ) {

	# if no waiting renew order, insert a new one
	$now = ( gmdate( "Y-m-d H:i:s" ) );

	if ( is_renew_order_paid( $original_order_id ) ) { // cannot allocate a renew_wait, this order was already paid and waiting to be completed.
		return false;
	}
	// are there any
	// renew_wait orders?
	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE original_order_id='" . intval( $original_order_id ) . "' and status='renew_wait' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	if ( ( $row = mysqli_fetch_array( $result ) ) == false ) {
		// copy the original order to create a new renew_wait order
		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id='" . intval( $original_order_id ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$row = mysqli_fetch_array( $result );

		$sql = "INSERT INTO " . MDS_DB_PREFIX . "orders (user_id, order_id, blocks, status, order_date, price, quantity, banner_id, currency, days_expire, date_stamp, approved, original_order_id) VALUES ('" . intval( $row['user_id'] ) . "', '', '" . intval( $row['blocks'] ) . "', 'renew_wait', NOW(), '" . floatval( $row['price'] ) . "', '" . intval( $row['quantity'] ) . "', '" . intval( $row['banner_id'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $row['currency'] ) . "', " . intval( $row['days_expire'] ) . ", '$now', '" . mysqli_real_escape_string( $GLOBALS['connection'], $row['approved'] ) . "', '" . intval( $original_order_id ) . "') ";

		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$order_id = mysqli_insert_id( $GLOBALS['connection'] );

		return $order_id;
	} else {
		return $row['order_id'];
	}
}*/

// payment had been completed.
// allocate renew_wait, set it to renew_paid
/*function pay_renew_order( $original_order_id ) {

	$wait_order_id = allocate_renew_order( $original_order_id );
	if ( $wait_order_id !== false ) {
		$sql = "UPDATE " . MDS_DB_PREFIX . "orders set status='renew_paid' WHERE order_id='" . intval( $wait_order_id ) . "' and status='renew_wait' ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	}

	if ( mysqli_affected_rows( $GLOBALS['connection'] ) > 0 ) {
		return true;
		# this order will now wait until the old one expires so it can be completed
	} else {
		return false;
	}
}*/

function process_paid_renew_orders() {
	//Complete: Only expired orders that have status as 'renew_paid'

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE status='renew_paid' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	while ( $row = mysqli_fetch_array( $result ) ) {
		// if expired
		complete_renew_order( $row['order_id'] );
	}
}

function complete_renew_order( $order_id ) {
	$sql = "SELECT * from " . MDS_DB_PREFIX . "orders where order_id='" . intval( $order_id ) . "' and status='renew_paid' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$order_row = mysqli_fetch_array( $result );

	if ( $order_row['status'] != 'completed' ) {

		// Update mds-pixel post status
		wp_update_post( [
			'ID'          => $order_row['ad_id'],
			'post_status' => 'completed',
		] );

		$now = ( gmdate( "Y-m-d H:i:s" ) );

		$sql = "UPDATE " . MDS_DB_PREFIX . "orders set status='completed', date_published=NULL, date_stamp='$now' WHERE order_id=" . intval( $order_id );
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		// update pixel's order_id

		$sql = "UPDATE " . MDS_DB_PREFIX . "blocks SET order_id='" . intval( $order_row['order_id'] ) . "' WHERE order_id='" . intval( $order_row['original_order_id'] ) . "' AND banner_id='" . intval( $order_row['banner_id'] ) . "' ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		// update ads' order id
		carbon_set_post_meta( $order_row['ad_id'], 'order', intval( $order_row['order_id'] ) );

		// mark pixels as sold.

		$sql = "SELECT * from " . MDS_DB_PREFIX . "orders where order_id='" . intval( $order_id ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$order_row = mysqli_fetch_array( $result );

		if ( strpos( $order_row['blocks'], "," ) !== false ) {
			$blocks = explode( ",", $order_row['blocks'] );
		} else {
			$blocks = array( 0 => $order_row['blocks'] );
		}
		foreach ( $blocks as $key => $val ) {
			$sql = "UPDATE " . MDS_DB_PREFIX . "blocks set status='sold' where block_id='" . intval( $val ) . "' and banner_id=" . intval( $order_row['banner_id'] );
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		}

		$user_info = get_userdata( intval( $order_row['user_id'] ) );

		if ( $order_row['days_expire'] == 0 ) {
			$order_row['days_expire'] = Language::get( 'Never' );
		}

		$banner_data = load_banner_constants( $order_row['banner_id'] );
		$block_count = $order_row['quantity'] / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

		$price = Currency::convert_to_default_currency_formatted( $order_row['currency'], $order_row['price'] );

		$search = [
			'%SITE_NAME%',
			'%FIRST_NAME%',
			'%LAST_NAME%',
			'%USER_LOGIN%',
			'%ORDER_ID%',
			'%ORIGINAL_ORDER_ID%',
			'%PIXEL_COUNT%',
			'%BLOCK_COUNT%',
			'%PIXEL_DAYS%',
			'%PRICE%',
			'%SITE_CONTACT_EMAIL%',
			'%SITE_URL%',
		];

		$replace = [
			get_bloginfo( 'name' ),
			$user_info->first_name,
			$user_info->last_name,
			$user_info->user_login,
			$order_row['order_id'],
			$order_row['original_order_id'],
			$order_row['quantity'],
			$block_count,
			$order_row['days_expire'],
			$price,
			get_bloginfo( 'admin_email' ),
			get_site_url(),
		];

		$message = Emails::get_email_replace(
			$search,
			$replace,
			'order-completed-renewal-content'
		);

		$subject = Emails::get_email_replace(
			$search,
			$replace,
			'order-completed-renewal-subject'
		);

		$EMAIL_USER_ORDER_COMPLETED = Config::get( 'EMAIL_USER_ORDER_COMPLETED' );
		if ( $EMAIL_USER_ORDER_COMPLETED == 'YES' ) {
			Mail::send( $user_info->user_email, $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 1 );
		}

		// send a copy to admin
		$EMAIL_ADMIN_ORDER_COMPLETED = Config::get( 'EMAIL_ADMIN_ORDER_COMPLETED' );
		if ( $EMAIL_ADMIN_ORDER_COMPLETED == 'YES' ) {
			Mail::send( get_bloginfo( 'admin_email' ), $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 1 );
		}

		// process the grid, if auto_publish is on

		$b_row = load_banner_row( $order_row['banner_id'] );

		if ( $b_row['auto_publish'] == 'Y' ) {
			process_image( $order_row['banner_id'] );
			publish_image( $order_row['banner_id'] );
			process_map( $order_row['banner_id'] );
		}
	}
}

function send_published_pixels_notification( $user_id, $BID ) {

	$sql = "SELECT * from " . MDS_DB_PREFIX . "banners where banner_id='" . intval( $BID ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$b_row = mysqli_fetch_array( $result );

	$user_info = get_userdata( intval( $user_id ) );

	$sql = "SELECT  url, alt_text FROM " . MDS_DB_PREFIX . "blocks where user_id='" . intval( $user_id ) . "' AND banner_id='" . intval( $BID ) . "' GROUP by url, alt_text ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$url_list = '';
	while ( $row = mysqli_fetch_array( $result ) ) {
		$url_list .= $row['url'] . " - " . $row['alt_text'] . "\n";
	}

	$view_url = get_admin_url();

	$search = [
		'%SITE_NAME%',
		'%GRID_NAME%',
		'%FIRST_NAME%',
		'%LAST_NAME%',
		'%USER_LOGIN%',
		'%URL_LIST%',
		'%VIEW_URL%',
	];

	$replace = [
		get_bloginfo( 'name' ),
		$b_row['name'],
		$user_info->first_name,
		$user_info->last_name,
		$user_info->user_login,
		$url_list,
		$view_url,
	];

	$message = Emails::get_email_replace(
		$search,
		$replace,
		'order-completed-renewal-content'
	);

	$subject = Emails::get_email_replace(
		$search,
		$replace,
		'order-completed-renewal-subject'
	);

	Mail::send( get_bloginfo( 'admin_email' ), $subject, $message, 'Admin', get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 7 );
}

function display_order( $order_id, $BID ): void {
	global $wpdb;

	$BID   = intval( $BID );
	$sql   = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "banners WHERE banner_id=%d", $BID );
	$b_row = $wpdb->get_row( $sql, ARRAY_A );

	if ( ! $b_row ) {
		echo '<div class="error">' . Language::get( 'Error: Grid not found' ) . '</div>';

		return;
	}

	$sql       = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%d", intval( $order_id ) );
	$order_row = $wpdb->get_row( $sql, ARRAY_A );

	?>
    <div class="mds-order-details">
        <div>
            <b><?php Language::out( 'Preview:' ); ?></b>
			<?php \MillionDollarScript\Classes\Ajax::get_ad( $order_row['ad_id'] ); ?>
        </div>
		<?php if ( isset( $order_row['order_id'] ) && $order_row['order_id'] != '' ) { ?>
            <div>
                <b><?php Language::out( 'Order ID:' ); ?></b>
				<?php echo intval( $order_row['order_id'] ); ?>
            </div>
		<?php } ?>
        <div>
            <b><?php Language::out( 'Date:' ); ?></b>
			<?php echo esc_html( $order_row['order_date'] ); ?>
        </div>
        <div>
            <b><?php Language::out( 'Grid Name:' ); ?></b>
			<?php echo esc_html( $b_row['name'] ); ?>
        </div>
        <div>
            <b><?php Language::out( 'Quantity:' ); ?></b>
			<?php
			$STATS_DISPLAY_MODE = Config::get( 'STATS_DISPLAY_MODE' );
			if ( $STATS_DISPLAY_MODE == "PIXELS" ) {
				echo intval( $order_row['quantity'] );
				echo " " . Language::get( 'pixels' );
			} else {
				echo intval( $order_row['quantity'] / ( $b_row['block_width'] * $b_row['block_height'] ) );
				echo " " . Language::get( 'blocks' );
			}
			?>
        </div>
        <div>
            <b><?php Language::out( 'Expires:' ); ?></b>
			<?php
			if ( $order_row['days_expire'] == 0 ) {
				Language::out( 'Never' );
			} else {
				Language::out_replace( 'In %DAYS_EXPIRE% days from date of publishment', '%DAYS_EXPIRE%', $order_row['days_expire'] );
			}
			?>
        </div>
        <div>
            <b><?php Language::out( 'Price:' ); ?></b>
			<?php echo esc_html( Currency::convert_to_default_currency_formatted( $order_row['currency'], $order_row['price'] ) ); ?>
        </div>
		<?php if ( isset( $order_row['order_id'] ) && $order_row['order_id'] != '' ) { ?>
            <div>
                <b><?php Language::out( 'Status:' ); ?></b>
				<?php echo esc_html( ucfirst( $order_row['status'] ) ); ?>
            </div>
		<?php } ?>
    </div>

	<?php
}

function display_banner_selecton_form( $BID, $order_id, $res, $mds_dest ): void {

	// validate mds_dest
	$valid_forms = [ 'order', 'select', 'publish', 'upload' ];
	if ( ! in_array( $mds_dest, $valid_forms, true ) ) {
		exit;
	}

	?>
    <form name="bidselect" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'mds-form' ); ?>
        <input type="hidden" name="action" value="mds_form_submission">
        <input type="hidden" name="mds_dest" value="banner_<?php echo $mds_dest; ?>">
        <input type="hidden" name="old_order_id" value="<?php echo intval( $order_id ); ?>">
        <input type="hidden" name="banner_change" value="1">
        <label>
            <select name="BID" style="font-size: 14px;">
				<?php
				foreach ( $res as $row ) {
					if ( $row['enabled'] == 'N' ) {
						continue;
					}
					if ( $row['banner_id'] == $BID ) {
						$sel = 'selected';
					} else {
						$sel = '';
					}
					echo '<option ' . $sel . ' value=' . $row['banner_id'] . '>' . $row['name'] . '</option>';
				}
				?>
            </select>
        </label>
        <input type="submit" name="submit" value="<?php echo esc_attr( Language::get( 'Select Grid' ) ); ?>">
    </form>
	<?php
}

// function move_uploaded_image( $img_key ) {
//
// 	$img_name = $_FILES[ $img_key ]['name'];
//
// 	$temp  = explode( '.', $img_name );
// 	$ext   = array_pop( $temp );
// 	$fname = array_pop( $temp );
//
// 	$img_name = preg_replace( '/[^\w]+/', "", $fname );
// 	$img_name = $img_name . "." . $ext;
//
// 	$img_tmp = $_FILES[ $img_key ]['tmp_name'];
//
// 	$t = time();
//
// 	$new_name = \MillionDollarScript\Classes\Utility::get_upload_path() . "grids/" . $t . "$img_name";
//
// 	move_uploaded_file( $img_tmp, $new_name );
// 	chmod( $new_name, 0666 );
//
// 	return $new_name;
// }

function nav_pages_struct( $q_string, $count, $REC_PER_PAGE ) {

	global $BID;

	$nav = array(
		'prev'         => '',
		'cur_page'     => 1,
		'pages_after'  => array(),
		'pages_before' => array(),
		'next'         => '',
	);

	$offset   = isset( $_REQUEST["offset"] ) ? intval( $_REQUEST["offset"] ) : 0;
	$show_emp = isset( $_REQUEST["show_emp"] ) ? $_REQUEST["show_emp"] : '';

	if ( $show_emp != '' ) {
		$show_emp = "&show_emp=" . $show_emp;
	}

	$cat = isset( $_REQUEST["cat"] ) ? $_REQUEST["cat"] : '';
	if ( $cat != '' ) {
		$cat = ( "&cat=$cat" );
	}

	$order_by = isset( $_REQUEST["order_by"] ) ? $_REQUEST["order_by"] : '';
	if ( $order_by != '' ) {
		$order_by = "&order_by=" . $order_by;
	}

	$cur_page = $offset / $REC_PER_PAGE;
	$cur_page ++;

	// estimate number of pages.
	$pages = ceil( $count / $REC_PER_PAGE );
	if ( $pages == 1 ) {
		return $nav;
	}

	$off  = 0;
	$p    = 1;
	$prev = $offset - $REC_PER_PAGE;
	$next = $offset + $REC_PER_PAGE;

	$b = '&BID=' . $BID;

	if ( $prev === 0 ) {
		$prev = '';
	}

	if ( $prev > - 1 ) {
		$nav['prev'] = "<a href='" . esc_attr( "?offset=" . $prev . $q_string . $show_emp . $cat . $order_by . $b ) . "'>" . Language::get( '<-Previous' ) . "</a> ";
	}

	for ( $i = 0; $i < $count; $i = $i + $REC_PER_PAGE ) {
		if ( $p == $cur_page ) {
			$nav['cur_page'] = $p;
		} else {
			if ( $off === 0 ) {
				$off = '';
			}

			if ( $nav['cur_page'] != '' ) {
				$nav['pages_after'][ $p ] = $off;
			} else {
				$nav['pages_before'][ $p ] = $off;
			}
		}

		$p ++;

		$off = intval( $off ) + $REC_PER_PAGE;
	}

	if ( $next < $count ) {
		$nav['next'] = " | <a  href='" . esc_attr( "?offset=" . $next . $q_string . $show_emp . $cat . $order_by . $b ) . "'> " . Language::get( 'Next ->' ) . "</a>";
	}

	return $nav;
}

function render_nav_pages( &$nav_pages_struct, $LINKS, $q_string = '' ) {

	global $BID;

	$page = isset( $_SERVER['PHP_SELF'] ) ? $_SERVER['PHP_SELF'] : '';

	$offset   = isset( $_REQUEST["offset"] ) ? intval( $_REQUEST["offset"] ) : 0;
	$show_emp = isset( $_REQUEST["show_emp"] ) ? $_REQUEST["show_emp"] : '';

	if ( $show_emp != '' ) {
		$show_emp = "&show_emp=" . $show_emp;
	}

	$cat = isset( $_REQUEST["cat"] ) ? $_REQUEST["cat"] : '';
	if ( $cat != '' ) {
		$cat = ( "&cat=$cat" );
	}

	$order_by = isset( $_REQUEST["order_by"] ) ? $_REQUEST["order_by"] : '';
	if ( $order_by != '' ) {
		$order_by = "&order_by=" . $order_by;
	}

	if ( $nav_pages_struct['cur_page'] > $LINKS - 1 ) {
		$LINKS  = round( $LINKS / 2 ) * 2;
		$NLINKS = $LINKS;
	} else {
		$NLINKS = $LINKS - $nav_pages_struct['cur_page'];
	}

	$b = '&BID=' . $BID;

	echo $nav_pages_struct['prev'];
	$b_count = isset( $nav_pages_struct['pages_before'] ) ? count( $nav_pages_struct['pages_before'] ) : 0;
	$pipe    = "";

	for ( $i = $b_count - $LINKS; $i <= $b_count; $i ++ ) {
		if ( $i > 0 ) {
			//echo " <a href='?offset=".$nav['pages_before'][$i]."'>".$i."</a></b>";
			echo " | <a  href='" . esc_attr( $page . "?offset=" . $nav_pages_struct['pages_before'][ $i ] . $q_string . $show_emp . $cat . $order_by . $b ) . "'>" . $i . "</a>";
			$pipe = "|";
		}
	}

	echo " $pipe <b>" . $nav_pages_struct['cur_page'] . " </b>  ";
	$a_count = isset( $nav_pages_struct['pages_after'] ) ? count( $nav_pages_struct['pages_after'] ) : 0;

	if ( $a_count > 0 ) {
		$i = 0;

		foreach ( $nav_pages_struct['pages_after'] as $key => $pa ) {
			$i ++;
			if ( $i > $NLINKS ) {
				break;
			}

			//echo " <a href='?offset=".$pa."'>".$key."</a>";
			echo " | <a  href='" . esc_attr( $page . "?offset=" . $pa . $q_string . $show_emp . $cat . $order_by . $b ) . "'>" . $key . "</a>  ";
		}
	}

	echo $nav_pages_struct['next'];
}

function select_block( $clicked_block, $banner_data, $size, $user_id ) {

	$BID = $banner_data['banner_id'];

	// Check if max_orders < order count
	if ( ! can_user_order( $banner_data, $user_id ) ) {
		// order count > max orders
		return Language::get_replace( '<b><span style="color:red">Cannot place pixels on order.</span> You have reached the order limit for this grid. Please review your <a href="%HISTORY_URL%">Order History.</a></b>', '%HISTORY_URL%', esc_url( Utility::get_page_url( 'history' ) ) );
	}

	if ( ! function_exists( 'load_ad_values' ) ) {
		require_once( MDS_CORE_PATH . "include/ads.inc.php" );
	}

	$blocks         = array();
	$clicked_blocks = array();
	$return_val     = "";

	$sql = "SELECT status, user_id, ad_id FROM " . MDS_DB_PREFIX . "blocks WHERE block_id=" . intval( $clicked_block ) . " AND banner_id=" . intval( $BID );
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$blocksrow = mysqli_fetch_array( $result );

	if ( ( $blocksrow == null || $blocksrow['status'] == '' ) || ( ( $blocksrow['status'] == 'reserved' ) && ( $blocksrow['user_id'] == get_current_user_id() ) ) ) {

		$orderid = get_current_order_id();

		// put block on order
		$sql = "SELECT blocks,status,ad_id,order_id FROM " . MDS_DB_PREFIX . "orders WHERE user_id=" . get_current_user_id() . " AND order_id=" . $orderid . " AND banner_id=" . intval( $BID ) . " AND status!='deleted'";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		$ordersrow = mysqli_fetch_array( $result );
		if ( isset( $ordersrow ) && $ordersrow['blocks'] != '' ) {
			$blocks = explode( ",", $ordersrow['blocks'] );
			array_walk( $blocks, 'intval' );
		}

		// $blocks2 will be modified based on deselections
		$blocks2 = $blocks;

		$is_adjacent = false;

		if ( $_REQUEST['erase'] == "true" ) {
			if ( ( $block = array_search( $clicked_block, $blocks2 ) ) !== false ) {
				// deselect
				unset( $blocks2[ $block ] );
				$is_adjacent = true;
			}
		} else if ( ! empty( $size ) ) {
			// take multi-selection blocks and deselecting blocks into account
			$invert = Config::get( 'INVERT_PIXELS' ) === 'YES';

			$pos            = get_block_position( $clicked_block, $BID );
			$blocks_per_row = $banner_data['G_WIDTH'];
			$blocks_per_col = $banner_data['G_HEIGHT'];
			$max_x_px       = $blocks_per_row * $banner_data['BLK_WIDTH'];
			$max_y_px       = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];
			$max_blocks     = $banner_data['G_MAX_BLOCKS'] == 0 ? $blocks_per_row * $blocks_per_col : $banner_data['G_MAX_BLOCKS'];
			$max_size       = min( floor( sqrt( ( $max_blocks ) ) ), $size );

			for ( $y = 0; $y < $max_size; $y ++ ) {
				$y_pos = $pos['y'] + ( $y % $blocks_per_col ) * $banner_data['BLK_HEIGHT'];
				for ( $x = 0; $x < $max_size; $x ++ ) {
					$x_pos = $pos['x'] + ( $x % $blocks_per_row ) * $banner_data['BLK_WIDTH'];

					if ( $x_pos <= $max_x_px && $y_pos <= $max_y_px ) {
						$clicked_block = get_block_id_from_position( $x_pos, $y_pos, $BID );

						if ( $invert && ( $block = array_search( $clicked_block, $blocks2 ) ) !== false ) {
							// deselect
							unset( $blocks2[ $block ] );
						} else {
							// select
							$clicked_blocks[] = $clicked_block;
						}
					}
				}
			}
		}

		// max x and y values
		$max_x = $banner_data['G_WIDTH'];
		$max_y = $banner_data['G_HEIGHT'];

		// check for adjacent blocks if more than one block exists
		if ( sizeof( $blocks ) > 0 && sizeof( $clicked_blocks ) > 0 ) {
			$id = 0;

			for ( $y = 0; $y < $max_y; $y ++ ) {
				for ( $x = 0; $x < $max_x; $x ++ ) {
					if ( in_array( $id, $blocks ) ) {
						// check all clicked blocks at once for adjacency
						foreach ( $clicked_blocks as $clicked_block2 ) {
							// top
							if ( $id - $max_x == $clicked_block2 && $y >= 0 ) {
								$is_adjacent = true;
							}

							// bottom
							if ( $id + $max_x == $clicked_block2 && $y <= $max_y ) {
								$is_adjacent = true;
							}

							// left
							if ( $id - 1 == $clicked_block2 && $x >= 0 ) {
								$is_adjacent = true;
							}

							// right
							if ( $id + 1 == $clicked_block2 && $x <= $max_x ) {
								$is_adjacent = true;
							}
						}
					}

					$id ++;
				}
			}
		} else {
			// only one block so pretend it's adjacent
			$is_adjacent = true;
		}

		// remove $clicked_blocks if not found adjacent to another block
		if ( ! $is_adjacent ) {
			$clicked_blocks = array();
			$return_val     = [
				"error" => "true",
				"type"  => "not_adjacent",
				"data"  => [
					"value" => Language::get( 'You must select a block adjacent to another one.' ),
				]
			];
		}

		// merge blocks
		$new_blocks = array_unique( array_merge( $blocks2, $clicked_blocks ) );

		// check max blocks
		$max_selected = false;
		if ( $banner_data['G_MAX_BLOCKS'] > 0 ) {
			if ( sizeof( $new_blocks ) > $banner_data['G_MAX_BLOCKS'] ) {
				$max_selected = true;
				$return_val   = [
					"error" => "true",
					"type"  => "max_blocks_selected",
					"data"  => [
						"value" => Language::get_replace( 'Maximum blocks selected. (%MAX_BLOCKS% allowed per order)', '%MAX_BLOCKS%', $banner_data['G_MAX_BLOCKS'] ),
					]
				];
			}
		}

		$order_blocks = implode( ",", $new_blocks );

		// don't overwrite existing blocks from other orders
		$existing_blocks = false;
		if ( count( $new_blocks ) > 1 ) {
			// single block is already handled, only handle multiple here
			$sql = "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE order_id != " . $orderid . " AND block_id IN(" . $order_blocks . ") AND banner_id=" . intval( $BID );
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
			$num_rows = mysqli_num_rows( $result );
			if ( $num_rows > 0 ) {
				$return_val = [
					"error" => "true",
					"type"  => "advertiser_sel_sold_error",
					"data"  => [
						"value" => Language::get_replace( 'Sorry, cannot select block %BLOCK_ID% because it is on order / sold!', "%BLOCK_ID% ", '' ),
					]
				];

				$existing_blocks = true;
			}
		}

		// if conditions are met then select new blocks
		if ( ! $max_selected && $is_adjacent && ! $existing_blocks && ! empty( $order_blocks ) ) {

			$price      = $total = 0;
			$num_blocks = sizeof( $new_blocks );
			$quantity   = ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] ) * $num_blocks;
			$now        = gmdate( "Y-m-d H:i:s" );

			$orderid = 0;
			if ( isset( $ordersrow['order_id'] ) ) {
				$orderid = intval( $ordersrow['order_id'] );
			}

			$adid = 0;
			if ( isset( $ordersrow['ad_id'] ) ) {
				$adid = intval( $ordersrow['ad_id'] );
			}

			// One last check to make sure we aren't replacing existing blocks from other orders
			$sql = "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE order_id != " . $orderid . " AND block_id IN(" . $order_blocks . ") AND banner_id=" . intval( $BID );
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
			$num_rows = mysqli_num_rows( $result );
			if ( $num_rows > 0 ) {
				return [
					"error" => "true",
					"type"  => "advertiser_sel_sold_error",
					"data"  => [
						"value" => Language::get_replace( 'Sorry, cannot select block %BLOCK_ID% because it is on order / sold!', "%BLOCK_ID% ", '' ),
					]
				];
			}

			$sql = "REPLACE INTO " . MDS_DB_PREFIX . "orders (user_id, order_id, blocks, status, order_date, price, quantity, banner_id, currency, days_expire, date_stamp, ad_id, approved) VALUES (" . get_current_user_id() . ", " . $orderid . ", '" . mysqli_real_escape_string( $GLOBALS['connection'], $order_blocks ) . "', 'new', NOW(), " . floatval( $price ) . ", " . intval( $quantity ) . ", " . intval( $BID ) . ", '" . mysqli_real_escape_string( $GLOBALS['connection'], Currency::get_default_currency() ) . "', " . intval( $banner_data['DAYS_EXPIRE'] ) . ", '$now', " . $adid . ", '" . mysqli_real_escape_string( $GLOBALS['connection'], $banner_data['AUTO_APPROVE'] ) . "') ";

			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
			$order_id = mysqli_insert_id( $GLOBALS['connection'] );
			set_current_order_id( $order_id );

			$return_val = [
				"error" => "false",
				"type"  => "order_id",
				"data"  => [
					"value" => intval( $order_id ),
				]
			];

			$sql = "DELETE FROM " . MDS_DB_PREFIX . "blocks WHERE user_id=" . get_current_user_id() . " AND status='reserved' AND banner_id='" . intval( $BID ) . "' ";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

			$cell = 0;

			$blocks_y = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];
			$blocks_x = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];

			for ( $y = 0; $y < $blocks_y; $y += $banner_data['BLK_HEIGHT'] ) {
				for ( $x = 0; $x < $blocks_x; $x += $banner_data['BLK_WIDTH'] ) {
					if ( in_array( $cell, $new_blocks ) ) {
						$price = get_zone_price( $BID, $y, $x );

						// reserve block
						$sql = "REPLACE INTO `" . MDS_DB_PREFIX . "blocks` ( `block_id` , `user_id` , `status` , `x` , `y` , `image_data` , `url` , `alt_text`, `approved`, `banner_id`, `ad_id`, `currency`, `price`, `order_id`, `click_count`, `view_count`) VALUES (" . intval( $cell ) . ",  " . get_current_user_id() . " , 'reserved' , " . intval( $x ) . " , " . intval( $y ) . " , '' , '' , '', '" . mysqli_real_escape_string( $GLOBALS['connection'], $banner_data['AUTO_APPROVE'] ) . "', " . intval( $BID ) . ", " . $adid . ", '" . mysqli_real_escape_string( $GLOBALS['connection'], Currency::get_default_currency() ) . "', " . floatval( $price ) . ", " . intval( $order_id ) . ", 0, 0)";
						mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

						$total += $price;
					}
					$cell ++;
				}
			}

			// update price
			$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET price='" . floatval( $total ) . "' WHERE order_id='" . intval( $order_id ) . "'";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

			$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET original_order_id='" . intval( $order_id ) . "' WHERE order_id='" . intval( $order_id ) . "'";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

			// check that we have ad_id, if not then create an ad for this order.
			if ( $adid == 0 ) {
				$_REQUEST['order_id'] = $order_id;
				$_REQUEST['BID']      = $BID;
				$_REQUEST['user_id']  = get_current_user_id();
			}
		}
	} else {

		if ( $blocksrow['status'] == 'nfs' ) {
			$return_val = [
				"error" => "true",
				"type"  => "advertiser_sel_nfs_error",
				"data"  => [
					"value" => Language::get( 'Sorry, cannot select this block of pixels because it is not for sale!' ),
				]
			];
		} else {
			$return_val = [
				"error" => "true",
				"type"  => "advertiser_sel_sold_error",
				"data"  => [
					"value" => Language::get_replace( 'Sorry, cannot select block %BLOCK_ID% because it is on order / sold!', "%BLOCK_ID%", $clicked_block ),
				]
			];
		}
	}

	return $return_val;
}

/*

The new 'Easy' pixel selection method (since 2.0)
- Reserve pixels
Takes the temp_order and converts it to an order.
Allocates pixels in the blocks table, returning order_id
shows an error if pixels were not reserved.

*/

function reserve_pixels_for_temp_order( $temp_order_row ) {
	global $wpdb;

	$banner_data = load_banner_constants( $temp_order_row['banner_id'] );

	// check if the user can get the order
	if ( ! can_user_order( $banner_data, get_current_user_id(), intval( $temp_order_row['package_id'] ) ) ) {
		Language::out( 'You do not have permission to reserve pixels for this order.' );

		return false;
	}

	require_once( __DIR__ . '/../include/ads.inc.php' );

	// Session may have expired if they waited too long so tell them to start over, even though we might still have the file it doesn't match the current session id anymore.
	$block_info       = array();
	$current_order_id = get_current_order_id();
	$sql              = $wpdb->prepare( "SELECT block_info FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%s", $current_order_id );
	$row              = $wpdb->get_row( $sql, ARRAY_A );

	if ( ! empty( $row ) ) {
		$block_info = unserialize( $row['block_info'] );
	}

	$in_str = $temp_order_row['blocks'];

	// Validate $in_str is comma separated integers.
	if ( ! preg_match( '/^(\d+(,\s*\d+)*)?$/', $in_str ) ) {
		return false;
	}

	$banner_id = intval( $temp_order_row['banner_id'] );
	$sql       = $wpdb->prepare( "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE banner_id=%d AND block_id IN($in_str)", $banner_id );
	$result    = $wpdb->get_results( $sql );

	if ( ! empty( $result ) ) {
		// the pixels are not available!
		return false;
	}

	// approval status, default is N
	$banner_row = load_banner_row( $temp_order_row['banner_id'] );
	$approved   = $banner_row['auto_approve'];

	$now = ( gmdate( "Y-m-d H:i:s" ) );

	$sql = $wpdb->prepare(
		"UPDATE " . MDS_DB_PREFIX . "orders
        SET user_id = %d,
            blocks = %s,
            status = 'new',
            order_date = %s,
            price = %f,
            quantity = %d,
            banner_id = %d,
            currency = %s,
            days_expire = %d,
            date_stamp = %s,
            package_id = %d,
            ad_id = %d,
            approved = %s
        WHERE order_id = %d",
		get_current_user_id(),
		$in_str,
		$now,
		floatval( $temp_order_row['price'] ),
		intval( $temp_order_row['quantity'] ),
		intval( $temp_order_row['banner_id'] ),
		Currency::get_default_currency(),
		intval( $temp_order_row['days_expire'] ),
		$now,
		intval( $temp_order_row['package_id'] ),
		intval( $temp_order_row['ad_id'] ),
		$approved,
		get_current_order_id()
	);
	$wpdb->query( $sql );
	$order_id = get_current_order_id();

	global $f2;
	$f2->debug( "Changed temp order to a real order - " . $sql );

	$sql = $wpdb->prepare( "UPDATE " . MDS_DB_PREFIX . "orders SET original_order_id=%d WHERE order_id=%d", $order_id, $order_id );
	$wpdb->query( $sql );

	$url      = carbon_get_post_meta( $temp_order_row['ad_id'], MDS_PREFIX . 'url' );
	$alt_text = carbon_get_post_meta( $temp_order_row['ad_id'], MDS_PREFIX . 'text' );

	if ( is_array( $block_info ) ) {
		foreach ( $block_info as $key => $block ) {
			$sql = $wpdb->prepare( "REPLACE INTO `" . MDS_DB_PREFIX . "blocks` ( `block_id`, `user_id`, `status`, `x`, `y`, `image_data`, `url`, `alt_text`, `approved`, `banner_id`, `currency`, `price`, `order_id`, `ad_id`, `click_count`, `view_count`) VALUES (%d, %d, 'reserved', %d, %d, %s, %s, %s, %s, %d, %s, %f, %d, %d, 0, 0)",
				intval( $key ),
				get_current_user_id(),
				intval( $block['map_x'] ),
				intval( $block['map_y'] ),
				$block['image_data'],
				$url,
				$alt_text,
				$approved,
				intval( $temp_order_row['banner_id'] ),
				Currency::get_default_currency(),
				floatval( $block['price'] ),
				intval( $order_id ),
				intval( $temp_order_row['ad_id'] )
			);
			$wpdb->query( $sql );

			global $f2;
			$f2->debug( "Updated block - " . $sql );
		}
	}

	//delete_temp_order( get_current_order_id(), false );

	// false = do not delete the ad...

	return $order_id;
}

function get_block_position( $block_id, $banner_id ) {

	$cell     = "0";
	$ret['x'] = 0;
	$ret['y'] = 0;

	$banner_data = load_banner_constants( $banner_id );

	for ( $i = 0; $i < $banner_data['G_HEIGHT']; $i ++ ) {
		for ( $j = 0; $j < $banner_data['G_WIDTH']; $j ++ ) {
			if ( $block_id == $cell ) {
				$ret['x'] = $j * $banner_data['BLK_WIDTH'];
				$ret['y'] = $i * $banner_data['BLK_HEIGHT'];

				return $ret;
			}
			$cell ++;
		}
	}

	return $ret;
}

function get_block_id_from_position( $x, $y, $banner_id ) {
	$id          = 0;
	$banner_data = load_banner_constants( $banner_id );
	$max_y       = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];
	$max_x       = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];

	for ( $y2 = 0; $y2 < $max_y; $y2 += $banner_data['BLK_HEIGHT'] ) {
		for ( $x2 = 0; $x2 < $max_x; $x2 += $banner_data['BLK_WIDTH'] ) {
			if ( $x == $x2 && $y == $y2 ) {
				return $id;
			}
			$id ++;
		}
	}

	return $id;
}

function is_block_free( $block_id, $banner_id ) {

	$sql = "SELECT * from " . MDS_DB_PREFIX . "blocks where block_id='" . intval( $block_id ) . "' AND banner_id='" . intval( $banner_id ) . "' ";
	//echo "$sql<br>";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	if ( mysqli_num_rows( $result ) == 0 ) {

		return true;
	} else {

		return false;
	}
}

// Move 1 block
// - changes the x y of a block
// - updates the order's blocks column
// *** assuming that the grid constants were loaded!
function move_block( $block_from, $block_to, $banner_id ) {

	# reserve block_to
	if ( ! is_block_free( $block_to, $banner_id ) ) {
		echo "<span style=\"color: red; \">Cannot move the block - the space chosen is not empty!</span><br/>";

		return false;
	}

	#load block_from
	$sql = "SELECT * from " . MDS_DB_PREFIX . "blocks where block_id=" . intval( $block_from ) . " AND banner_id=" . intval( $banner_id );
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$source_block = mysqli_fetch_array( $result );

	// get the position and check range, do not move if out of range

	$pos = get_block_position( $block_to, $banner_id );

	$x = $pos['x'];
	$y = $pos['y'];

	$banner_data = load_banner_constants( $banner_id );

	if ( ( $x === '' ) || ( $x > ( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ) ) || $x < 0 ) {
		echo "<b>x is $x</b><br>";

		return false;
	}

	if ( ( $y === '' ) || ( $y > ( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ) ) || $y < 0 ) {
		echo "<b>y is $y</b><br>";

		return false;
	}

	$sql = "REPLACE INTO `" . MDS_DB_PREFIX . "blocks` ( `block_id` , `user_id` , `status` , `x` , `y` , `image_data` , `url` , `alt_text`, `file_name`, `mime_type`,  `approved`, `published`, `banner_id`, `currency`, `price`, `order_id`, `click_count`, `view_count`, `ad_id`) VALUES (" . intval( $block_to ) . ",  " . intval( $source_block['user_id'] ) . " , '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['status'] ) . "' , " . intval( $x ) . " , " . intval( $y ) . " , '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['image_data'] ) . "' , '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['url'] ) . "' , '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['alt_text'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['file_name'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['mime_type'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['approved'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['published'] ) . "', " . intval( $banner_id ) . ", '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['currency'] ) . "', " . floatval( $source_block['price'] ) . ", " . intval( $source_block['order_id'] ) . ", " . intval( $source_block['click_count'] ) . ", " . intval( $source_block['view_count'] ) . ", " . intval( $source_block['ad_id'] ) . ")";

	global $f2;
	$f2->debug( "Moved Block - " . $sql );

	mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	# delete 'from' block
	$sql = "DELETE from " . MDS_DB_PREFIX . "blocks WHERE block_id='" . intval( $block_from ) . "' AND banner_id='" . intval( $banner_id ) . "' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	$f2->debug( "Deleted block_from - " . $sql );

	// Update the order record
	$sql = "SELECT * from " . MDS_DB_PREFIX . "orders WHERE order_id='" . intval( $source_block['order_id'] ) . "' AND banner_id='" . intval( $banner_id ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$order_row  = mysqli_fetch_array( $result );
	$blocks     = array();
	$new_blocks = array();
	$blocks     = explode( ',', $order_row['blocks'] );
	foreach ( $blocks as $item ) {
		if ( $block_from == $item ) {
			$item = $block_to;
		}
		$new_blocks[] = intval( $item );
	}

	$sql_blocks = implode( ',', $new_blocks );

	$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET blocks='" . $sql_blocks . "' WHERE order_id=" . intval( $source_block['order_id'] );
	# update the customer's order
	mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	$f2->debug( "Updated order - " . $sql );

	return true;
}

function move_order( $block_from, $block_to, $banner_id ) {

	//move_block($block_from, $block_to, $banner_id);

	// get the block_to x,y
	$pos  = get_block_position( $block_to, $banner_id );
	$to_x = $pos['x'];
	$to_y = $pos['y'];

	// we need to work out block_from, get the block with the lowest x and y

	$min_max = get_blocks_min_max( $block_from, $banner_id );
	$from_x  = $min_max['low_x'];
	$from_y  = $min_max['low_y'];

	// get the position move's difference

	$dx = ( $to_x - $from_x );
	$dy = ( $to_y - $from_y );

	// get the order

	$sql = "SELECT * from " . MDS_DB_PREFIX . "blocks where block_id=" . intval( $block_from ) . " AND banner_id=" . intval( $banner_id );
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$source_block = mysqli_fetch_array( $result );

	$sql = "SELECT * from " . MDS_DB_PREFIX . "blocks WHERE order_id=" . intval( $source_block['order_id'] ) . " AND banner_id=" . intval( $banner_id );
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	$banner_data = load_banner_constants( $banner_id );

	$grid_width = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];

	while ( $block_row = mysqli_fetch_array( $result ) ) { // check each block to make sure we can move it.

		$block_to = ( ( $block_row['x'] + $dx ) / $banner_data['BLK_WIDTH'] ) + ( ( ( $block_row['y'] + $dy ) / $banner_data['BLK_HEIGHT'] ) * ( $grid_width / $banner_data['BLK_WIDTH'] ) );

		if ( ! is_block_free( $block_to, $banner_id ) ) {
			Language::out( '<span style="color: red;">Cannot move the order - the space chosen is not empty!</span><br/>' );

			return false;
		}
	}

	mysqli_data_seek( $result, 0 );

	while ( $block_row = mysqli_fetch_array( $result ) ) {

		$block_from = ( ( $block_row['x'] ) / $banner_data['BLK_WIDTH'] ) + ( ( $block_row['y'] / $banner_data['BLK_HEIGHT'] ) * ( $grid_width / $banner_data['BLK_WIDTH'] ) );
		$block_to   = ( ( $block_row['x'] + $dx ) / $banner_data['BLK_WIDTH'] ) + ( ( ( $block_row['y'] + $dy ) / $banner_data['BLK_HEIGHT'] ) * ( $grid_width / $banner_data['BLK_WIDTH'] ) );

		move_block( $block_from, $block_to, $banner_id );
	}

	return true;
}

/*
function get_required_size($x, $y) - assuming the grid constants were initialized
$x and $y are the current size
*/
function get_required_size( $x, $y, $banner_data ): array {

	$block_width  = $banner_data['BLK_WIDTH'];
	$block_height = $banner_data['BLK_HEIGHT'];

	$size[0] = $x;
	$size[1] = $y;

	$mod = ( $x % $block_width );

	if ( $mod > 0 ) {
		// width does not fit
		$size[0] = $x + ( $block_width - $mod );
	}

	$mod = ( $y % $block_height );

	if ( $mod > 0 ) {
		// height does not fit
		$size[1] = $y + ( $block_height - $mod );
	}

	return $size;
}

// If $user_id is null then return for all banners
function get_clicks_for_today( $BID, $user_id = 0 ) {

	$date = gmDate( 'Y' ) . "-" . gmDate( 'm' ) . "-" . gmDate( 'd' );

	$sql = "SELECT *, SUM(clicks) AS clk FROM `" . MDS_DB_PREFIX . "clicks` where banner_id='" . intval( $BID ) . "' AND `date`='$date' GROUP BY banner_id, block_id, user_id, date";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );

	if ( $row == null ) {
		return 0;
	}

	return $row['clk'];
}

/**
 * If $BID is null then return for all banners
 *
 * @param string $BID
 *
 * @return int|mixed|void
 */
function get_clicks_for_banner( string $BID = '' ) {

	$sql = "SELECT *, SUM(clicks) AS clk FROM `" . MDS_DB_PREFIX . "clicks` where banner_id='" . intval( $BID ) . "'  GROUP BY banner_id, block_id, user_id, date";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );

	if ( $row == null ) {
		return 0;
	}

	return $row['clk'];
}

/**
 * First check to see if the banner has packages. If it does
 * then check how many orders the user had.
 *
 * @param $banner_data
 * @param $user_id
 * @param int $package_id
 *
 * @return bool|void|null
 */
function can_user_order( $banner_data, $user_id, int $package_id = 0 ) {
	// check rank
	$privileged = carbon_get_user_meta( get_current_user_id(), 'privileged' );
	if ( $privileged == '1' ) {
		return true;
	}

	$BID = $banner_data['BANNER_ID'];

	if ( banner_get_packages( $BID ) ) { // if user has package, check if the user can order this package
		if ( $package_id == 0 ) { // don't know the package id, assume true.

			return true;
		} else {

			return can_user_get_package( $user_id, $package_id );
		}
	} else {

		// check against the banner. (Banner has no packages)
		if ( ( $banner_data['G_MAX_ORDERS'] > 0 ) ) {

			$sql = "SELECT order_id FROM " . MDS_DB_PREFIX . "orders where `banner_id`='" . intval( $BID ) . "' and `status` <> 'deleted' and `status` <> 'new' AND user_id='" . intval( $user_id ) . "'";

			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
			$count = mysqli_num_rows( $result );
			if ( $count >= $banner_data['G_MAX_ORDERS'] ) {
				return false;
			} else {
				return true;
			}
		} else {
			return true; // can make unlimited orders
		}
	}
}

function get_blocks_min_max( $block_id, $banner_id ) {

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "blocks where block_id=" . intval( $block_id ) . " and banner_id=" . intval( $banner_id );

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );

	$sql = "select * from " . MDS_DB_PREFIX . "blocks where order_id=" . intval( $row['order_id'] );
	$result3 = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	// find high x, y & low x, y
	// low x,y is the top corner, high x,y is the bottom corner

	$high_x = $high_y = $low_x = $low_y = "";
	while ( $block_row = mysqli_fetch_array( $result3 ) ) {

		if ( $high_x == '' ) {
			$high_x = $block_row['x'];
			$high_y = $block_row['y'];
			$low_x  = $block_row['x'];
			$low_y  = $block_row['y'];
		}

		if ( $block_row['x'] > $high_x ) {
			$high_x = $block_row['x'];
		}

		if ( $block_row['y'] > $high_y ) {
			$high_y = $block_row['y'];
		}

		if ( $block_row['y'] < $low_y ) {
			$low_y = $block_row['y'];
		}

		if ( $block_row['x'] < $low_x ) {
			$low_x = $block_row['x'];
		}
	}

	$ret           = array();
	$ret['high_x'] = $high_x;
	$ret['high_y'] = $high_y;
	$ret['low_x']  = $low_x;
	$ret['low_y']  = $low_y;

	return $ret;
}

function get_definition( $field_type ): string {
	// compare MySQL version, versions newer than 5.6.5 require a different set of queries
	$mysql_server_info = mysqli_get_server_info( $GLOBALS['connection'] );

	switch ( $field_type ) {
		case "TEXT":
			return "VARCHAR( 255 ) NOT NULL ";
		case "SEPERATOR":
			break;
		case "EDITOR":
			return "TEXT NOT NULL ";
		case "CATEGORY":
			return "INT(11) NOT NULL ";
		case "DATE":
		case "DATE_CAL":
			if ( version_compare( $mysql_server_info, '5.6.5' ) >= 0 ) {
				return "DATETIME NOT NULL default '1000-01-01 00:00:00'";
			} else {
				return "DATETIME NOT NULL ";
			}
		case "FILE":
			return "VARCHAR( 255 ) NOT NULL ";
		case "MIME":
			return "VARCHAR( 255 ) NOT NULL ";
		case "BLANK":
			break;
		case "NOTE":
			return "VARCHAR( 255 ) NOT NULL ";
		case "CHECK":
			return "VARCHAR( 255 ) NOT NULL ";
		case "IMAGE":
			return "VARCHAR( 255 ) NOT NULL ";
		case "RADIO":
			return "VARCHAR( 255 ) NOT NULL ";
		case "SELECT":
			return "VARCHAR( 255 ) NOT NULL ";
		case "MSELECT":
			return "VARCHAR( 255 ) NOT NULL ";
		case "TEXTAREA":
			return "TEXT NOT NULL ";
		default:
			return "VARCHAR( 255 ) NOT NULL ";
	}

	return "";
}

function deleteImage( $table_name, $object_name, $object_id, $field_id ) {

	$sql = "SELECT `" . mysqli_real_escape_string( $GLOBALS['connection'], $field_id ) . "` FROM `" . mysqli_real_escape_string( $GLOBALS['connection'], $table_name ) . "` WHERE `" . mysqli_real_escape_string( $GLOBALS['connection'], $object_name ) . "`='" . intval( $object_id ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$row = mysqli_fetch_array( $result, MYSQLI_ASSOC );
	if ( isset( $row ) && $row[ $field_id ] != '' ) {
		// delete the original
		@unlink( MDS_CORE_URL . "images/" . $row[ $field_id ] );
		// delete the thumb
		//@unlink (IMG_PATH."thumbs/".$row[$field_id]);
		//echo "<br><b>unlnkthis[".IMG_PATH."thumbs/$new_name]</b><br>";
	}
}

function deleteFile( $table_name, $object_name, $object_id, $field_id ) {

	$sql = "SELECT `" . mysqli_real_escape_string( $GLOBALS['connection'], $field_id ) . "` FROM `" . MDS_DB_PREFIX . mysqli_real_escape_string( $GLOBALS['connection'], $table_name ) . "` WHERE `" . mysqli_real_escape_string( $GLOBALS['connection'], $object_name ) . "`='" . intval( $object_id ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result, MYSQLI_ASSOC );
	if ( $row[ $field_id ] != '' ) {
		// delete the original
		@unlink( MDS_CORE_URL . "docs/" . $row[ $field_id ] );
		// delete the thumb
		// unlink (FILE_PATH."thumbs/".$row[$field_id]);
		//echo "<br><b>unlnkthis[".IMG_PATH."thumbs/$new_name]</b><br>";
	}
}

function get_tmp_img_name(): string {
	$uploaddir = Utility::get_upload_path() . "images/";
	$filter    = "tmp_" . get_current_order_id() . '.png';
	$file      = $uploaddir . $filter;

	if ( file_exists( $file ) ) {
		return $file;
	}

	return "";
}

function update_temp_order_timestamp(): void {
	$now = ( gmdate( "Y-m-d H:i:s" ) );
	$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET order_date='$now' WHERE order_id='" . mysqli_real_escape_string( $GLOBALS['connection'], get_current_order_id() ) . "' ";
	mysqli_query( $GLOBALS['connection'], $sql );
}

function show_nav_status( $page_id ): void {

	echo '<div class="mds-nav-status">';

	switch ( $page_id ) {
		case 1:
			Language::out( '<b>Upload Your pixels</b> -> Write Your Ad -> Confirm -> Payment -> Thank you!' );
			break;
		case 2:
			Language::out( 'Upload Your pixels -> <b>Write Your Ad</b> -> Confirm -> Payment -> Thank you!' );
			break;
		case 3:
			Language::out( 'Upload Your pixels -> Write Your Ad -> <b>Confirm</b> -> Payment -> Thank you!' );
			break;
	}

	echo "</div>";
}

function trim_date( $gmdate ) {
	preg_match( "/(\d+-\d+-\d+).+/", $gmdate, $m );

	return $m[1];
}

// function truncate_html_str
// truncate a string encoded with htmlentities eg &nbsp; is counted as 1 character
// Limitation: does not work with well if the string contains html tags, (but does it's best to deal with them).
function truncate_html_str( $s, $MAX_LENGTH, &$trunc_str_len ) {

	$trunc_str_len = 0;

	if ( func_num_args() > 3 ) {
		$add_ellipsis = func_get_arg( 3 );
	} else {
		$add_ellipsis = true;
	}

	if ( func_num_args() > 4 ) {
		$with_tags = func_get_arg( 4 );
	} else {
		$with_tags = false;
	}

	if ( $with_tags ) {
		$tag_expr = "|<[^>]+>";
	}

	$offset          = 0;
	$character_count = 0;
	# match a character, or characters encoded as html entity
	# treat each match as a single character
	#
	$str = "";
	while ( ( preg_match( '/(&#?[0-9A-z]+;' . $tag_expr . '|.|\n)/', $s, $maches, PREG_OFFSET_CAPTURE, $offset ) && ( $character_count < $MAX_LENGTH ) ) ) {
		$offset += strlen( $maches[0][0] );
		$character_count ++;
		$str .= $maches[0][0];
	}
	if ( ( $character_count == $MAX_LENGTH ) && ( $add_ellipsis ) ) {
		$str = $str . "...";
	}
	$trunc_str_len = $character_count;

	return $str;
}

function get_pixel_image_size( $order_id ) {

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "blocks WHERE order_id=" . intval( $order_id );
	$result3 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

	// find high x, y & low x, y
	// low x,y is the top corner, high x,y is the bottom corner
	$BID = 1;
	while ( $block_row = mysqli_fetch_array( $result3 ) ) {

		$high_x = ! isset( $high_x ) ? $block_row['x'] : $high_x;
		$high_y = ! isset( $high_y ) ? $block_row['y'] : $high_y;
		$low_x  = ! isset( $low_x ) ? $block_row['x'] : $low_x;
		$low_y  = ! isset( $low_y ) ? $block_row['y'] : $low_y;

		if ( $block_row['x'] > $high_x ) {
			$high_x = $block_row['x'];
		}

		if ( $block_row['y'] > $high_y ) {
			$high_y = $block_row['y'];
		}

		if ( $block_row['y'] < $low_y ) {
			$low_y = $block_row['y'];
		}

		if ( $block_row['x'] < $low_x ) {
			$low_x = $block_row['x'];
		}

		$BID = $block_row['banner_id'];
	}

	$high_x = ! isset( $high_x ) ? 0 : $high_x;
	$high_y = ! isset( $high_y ) ? 0 : $high_y;
	$low_x  = ! isset( $low_x ) ? 0 : $low_x;
	$low_y  = ! isset( $low_y ) ? 0 : $low_y;

	$banner_data = load_banner_constants( $BID );

	$size['x'] = ( $high_x + $banner_data['BLK_WIDTH'] ) - $low_x;
	$size['y'] = ( $high_y + $banner_data['BLK_HEIGHT'] ) - $low_y;

	return $size;
}

function elapsedtime( $sec ) {
	$days = floor( $sec / 86400 );
	$hrs  = floor( ( $sec % 86400 ) / 3600 );
	$mins = floor( ( $sec % 3600 ) / 60 );

	$tstring = "";
	if ( $days > 0 ) {
		$tstring .= $days . "d, ";
	}
	if ( $hrs > 0 ) {
		$tstring .= $hrs . "h, ";
	}
	$tstring .= $mins . "m";

	return $tstring;
}

function powered_by_mds() {
	?>
    <div style="font-size:xx-small; text-align:center">Powered By <a target="_blank" style="font-size:7pt;color:black" href="https://milliondollarscript.com/">Million Dollar Script</a></div>
	<?php
}

// catch errors
function shutdown() {
	$isError = false;
	if ( $error = error_get_last() ) {
		switch ( $error['type'] ) {
			case E_ERROR:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
				$isError = true;
				break;
		}
	}

	if ( $isError ) {
		echo "Script execution halted ({$error['message']})";

		// php memory error
		if ( substr_count( $error['message'], 'Allowed memory size' ) ) {
			echo "<br />Try increasing your PHP memory limit and restarting the web server.";
		}
	} else {
		echo "Script completed";
	}
}

/**
 * Convert an array memory string values to integer values
 *
 * @param array $Sizes
 *
 * @return int
 */
function convertMemoryToBytes( $Sizes ) {
	for ( $x = 0; $x < count( $Sizes ); $x ++ ) {
		$Last  = strtolower( $Sizes[ $x ][ strlen( $Sizes[ $x ] ) - 1 ] );
		$limit = intval( $Sizes[ $x ] );
		if ( $Last == 'k' ) {
			$limit *= 1024;
		} else if ( $Last == 'm' ) {
			$limit *= 1024;
			$limit *= 1024;
		} else if ( $Last == 'g' ) {
			$limit *= 1024;
			$limit *= 1024;
			$limit *= 1024;
		} else if ( $Last == 't' ) {
			$limit *= 1024;
			$limit *= 1024;
			$limit *= 1024;
			$limit *= 1024;
		}
		$Sizes[ $x ] = $limit;
	}

	return max( $Sizes );
}

/**
 * Attempt to increase the Memory Limit based on image size.
 *
 * @link https://alvarotrigo.com/blog/watch-beauty-and-the-beast-2017-full-movie-online-streaming-online-and-download/
 *
 * @param string $filename
 */
function setMemoryLimit( $filename ) {
	if ( ! file_exists( $filename ) ) {
		error_log( "setMemoryLimit error, file not found: " . $filename );

		return;
	}

	//this might take time, so we limit the maximum execution time to 50 seconds
	set_time_limit( 50 );

	//initializing variables
	$maxMemoryUsage = convertMemoryToBytes( array( "512M" ) );
	$currentLimit   = convertMemoryToBytes( array( ini_get( 'memory_limit' ) ) );
	$currentUsage   = memory_get_usage();

	//getting the image width and height
	list( $width, $height ) = getimagesize( $filename );

	//calculating the needed memory
	$size = intval( $currentUsage ) + $currentLimit + ( floor( $width * $height * 4 * 1.5 + 1048576 ) );

	// make sure memory limit is within range
	$MEMORY_LIMIT = Config::get( 'MEMORY_LIMIT' );
	$size         = min( max( $size, $MEMORY_LIMIT ), $maxMemoryUsage );

	//updating the default value
	ini_set( 'memory_limit', $size );
}

/**
 * Function to validate email addresses
 *
 * @link https://stackoverflow.com/a/42969643/311458
 *
 * @param $email
 *
 * @return bool
 */
function validate_mail( $email ): bool {
	$emailB = filter_var( $email, FILTER_SANITIZE_EMAIL );

	if ( filter_var( $emailB, FILTER_VALIDATE_EMAIL ) === false || $emailB != $email ) {
		return false;
	}

	return true;
}

function delete_current_order_id(): void {
	$user_id = get_current_user_id();
	if ( $user_id > 0 ) {
		delete_user_meta( $user_id, MDS_PREFIX . 'current_order_id' );
	}
}

function set_current_order_id( $order_id = null ): void {
	global $wpdb;

	$user_id = get_current_user_id();
	if ( $user_id > 0 ) {
		if ( is_null( $order_id ) ) {
			// Add new order to database
			$wpdb->insert( MDS_DB_PREFIX . "orders", [
				'user_id' => $user_id,
				'status'  => 'new',
			] );
			$order_id = $wpdb->insert_id;
		} else {
			// Check if order id is owned by the user first
			$order = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM " . MDS_DB_PREFIX . "orders WHERE user_id =%d AND order_id=%d", $user_id, $order_id ) );
			if ( empty( $order ) ) {
				return;
			}
		}

		// Update user meta with current order id
		update_user_meta( $user_id, MDS_PREFIX . 'current_order_id', $order_id );
	}
}

function get_current_order_id( $get_grid = true ) {
	global $wpdb, $f2;

	if ( $get_grid ) {
		$BID = $f2->bid();
	}

	$user_id = get_current_user_id();

	if ( $user_id > 0 ) {
		$order_id = get_user_meta( $user_id, MDS_PREFIX . 'current_order_id', true );
		if ( empty( $order_id ) ) {
			$sql = $wpdb->prepare(
				"SELECT * from " . MDS_DB_PREFIX . "orders WHERE user_id=%d AND status='new' AND banner_id=%d",
				$user_id,
				$BID
			);

			$order_result = $wpdb->get_results( $sql, ARRAY_A );

			if ( ! empty( $order_result ) ) {
				$order_row = $order_result[0];

				if ( $order_row['user_id'] != '' && (int) $order_row['user_id'] !== $user_id ) {
					die( Language::get( 'You do not own this order!' ) );
				}
			} else {
				// Insert new order to use id of
				$wpdb->insert( MDS_DB_PREFIX . "orders", [
					'user_id'   => $user_id,
					'status'    => 'new',
					'banner_id' => $BID
				] );
				$order_id = $wpdb->insert_id;
				set_current_order_id( $order_id );
			}
		}

		return $order_id;
	} else {
		return null;
	}
}

/**
 * Check available pixels
 *
 * @param $in_str
 *
 * @return bool
 */
function check_pixels( $in_str ): bool {

	global $f2, $wpdb;

	// cannot reserve pixels if user isn't logged in
	mds_wp_login_check();

	$BID = $f2->bid();

	// check if it is free
	$available = true;

	$in_array = explode( ',', $in_str );
	$in_array = array_map( 'intval', $in_array );
	$in_str   = implode( ',', $in_array );
	$sql      = $wpdb->prepare( "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE banner_id=%d AND block_id IN($in_str)", $BID );
	$wpdb->get_results( $sql );

	if ( $wpdb->num_rows > 0 ) {
		echo json_encode( [
			"error" => "true",
			"type"  => "unavailable",
			"data"  => [
				"value" => Language::get( 'This space is not available! Please try to place your pixels in a different area.' ) . " (E432)",
			]
		] );

		$available = false;
	}

	// TODO: optimize or remove this but make sure blocks are added so they get checked above.
	if ( $available ) {
		$sql    = $wpdb->prepare( "SELECT blocks FROM " . MDS_DB_PREFIX . "orders WHERE banner_id=%d AND order_id != %s", $BID, get_current_order_id() );
		$result = $wpdb->get_results( $sql );

		$selected = explode( ",", $in_str );
		foreach ( $result as $row ) {
			$entries = explode( ",", $row->blocks );
			if ( ! empty( array_intersect( $entries, $selected ) ) ) {
				echo json_encode( [
					"error" => "true",
					"type"  => "unavailable",
					"data"  => [
						"value" => Language::get( 'This space is not available! Please try to place your pixels in a different area.' ) . " (E433)",
					]
				] );
				$available = false;
				break;
			}
		}
	}

	return $available;
}
