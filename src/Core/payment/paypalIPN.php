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

// Update cert/cacert.pem from https://curl.se/docs/caextract.html
// https://curl.se/ca/cacert.pem

require_once __DIR__ . "/../include/init.php";

$_PAYMENT_OBJECTS['PayPal'] = new PayPal;

define( 'PAYPAL_IPN_LOGGING', 'Y' );

function pp_mail_error( $msg ) {
	$date = date( "D, j M Y H:i:s O" );

	$headers = "From: " . SITE_CONTACT_EMAIL . "\r\n";
	$headers .= "Reply-To: " . SITE_CONTACT_EMAIL . "\r\n";
	$headers .= "Return-Path: " . SITE_CONTACT_EMAIL . "\r\n";
	$headers .= "X-Mailer: PHP" . "\r\n";
	$headers .= "Date: $date" . "\r\n";
	$headers .= "X-Sender-IP: " . $_SERVER['REMOTE_ADDR'] . "\r\n";

	$entry_line = "(PayPal error detected) $msg\r\n ";
	$log_fp     = @fopen( "logs.txt", "a" );
	@fputs( $log_fp, $entry_line );
	@fclose( $log_fp );

	@mail( SITE_CONTACT_EMAIL, "Error message from " . SITE_NAME . " Million Dollar Script Paypal IPN script. ", $msg, $headers );
}

function pp_log_entry( $entry_line ) {
	if ( PAYPAL_IPN_LOGGING == 'Y' ) {
		$entry_line = "$entry_line\r\n ";
		$log_fp     = @fopen( "logs.txt", "a" );
		@fputs( $log_fp, $entry_line );
		@fclose( $log_fp );
	}
}

function pp_prefix_order_id( $order_id ) {
	return substr( md5( SITE_NAME ), 1, 5 ) . $order_id;
}

function pp_strip_order_id( $order_id ) {
	return substr( $order_id, 5 );
}

// Listen for IPN calls
if ( isset( $_POST['txn_id'] ) && $_POST['txn_id'] != '' ) {

	// https://github.com/paypal/ipn-code-samples/blob/master/php/example_usage.php
	require_once __DIR__ . '/PayPal/PayPalIPN.php';

	$ipn = new PaypalIPN();

	if ( PAYPAL_SERVER === 'www.sandbox.paypal.com' ) {
		$ipn->useSandbox();
	}

	try {
		$verified = $ipn->verifyIPN();
	} catch ( Exception $e ) {
		error_log( $e->getMessage() );
		$verified = false;
	}

	if ( $verified ) {
		/*
		 * Process IPN
		 * A list of variables is available here:
		 * https://developer.paypal.com/docs/api-basics/notifications/ipn/IPNandPDTVariables/
		 */

		// read the post from PayPal system and add 'cmd'
		$req = 'cmd=_notify-validate';

		foreach ( $_POST as $key => $value ) {
			$value = urlencode( $value );
			$req   .= "&$key=$value";
		}

		$entry_line = "$req";
		pp_log_entry( $entry_line );

		// assign posted variables to local variables
		$item_name      = $_POST['item_name'];
		$item_number    = $_POST['item_number'];
		$payment_status = $_POST['payment_status'];
		$mc_gross       = $_POST['mc_gross'];
		$mc_currency    = $_POST['mc_currency'];
		$payment_type   = $_POST['payment_type'];
		$pending_reason = $_POST['pending_reason'];
		$reason_code    = $_POST['reason_code'];
		$payment_date   = $_POST['payment_date'];
		$txn_id         = $_POST['txn_id'];
		$parent_txn_id  = $_POST['parent_txn_id'];
		$txn_type       = $_POST['txn_type'];
		$receiver_email = strtolower( urldecode( $_POST['receiver_email'] ) );
		$payer_email    = $_POST['payer_email'];

		$item_number = $_POST['item_number'];

		$sql = "select * FROM " . MDS_DB_PREFIX . "orders where order_id='" . intval( $item_number ) . "'";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or pp_mail_error( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$order_row = mysqli_fetch_array( $result );

		// check that receiver_email is your Primary PayPal email
		if ( strcmp( strtolower( PAYPAL_EMAIL ), $receiver_email ) != 0 ) {
			pp_mail_error( "Possible fraud. Error with receiver_email. " . strtolower( PAYPAL_EMAIL ) . " != " . $receiver_email . "\n" );
			pp_log_entry( "Possible fraud. Error with receiver_email. " . strtolower( PAYPAL_EMAIL ) . " != " . $receiver_email );
			$verified = false;
		}

		// check so that transaction id cannot be reused
		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "transactions WHERE txn_id='" . mysqli_real_escape_string( $GLOBALS['connection'], $txn_id ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or pp_mail_error( mysqli_error( $GLOBALS['connection'] ) . $sql );
		if ( mysqli_num_rows( $result ) > 0 ) {
			pp_log_entry( "transaction $txn_id already processed" );
			$verified = false;
		}

		// check that payment_amount/payment_currency are correct
		$amount = convert_to_currency( $order_row['price'], $order_row['currency'], PAYPAL_CURRENCY );

		if ( ( $amount != $mc_gross ) ) {
			pp_log_entry( $order_row['price'] . $order_row['currency'] . "Transaction has incorrect currency. $amount=$mc_gross item_number: $item_number\n" );
			$verified = false;
		}

		if ( $verified ) {

			// transaction came from a button or straight from paypal
			if ( ( $txn_type == 'web_accept' ) || ( $txn_type == '' ) ) {

				switch ( $payment_status ) {
					case "Canceled_Reversal":

						$sql = "select user_id FROM " . MDS_DB_PREFIX . "orders where order_id='" . intval( $item_number ) . "'";
						$result = mysqli_query( $GLOBALS['connection'], $sql ) or pp_mail_error( mysqli_error( $GLOBALS['connection'] ) . $sql );
						$row = mysqli_fetch_array( $result );

						complete_order( $row['user_id'], $item_number );
						debit_transaction( $item_number, $mc_gross, $mc_currency, $txn_id, $reason_code, 'PayPal' );

						break;
					case "Completed":
						// Funds successfully transferred

						$sql = "select user_id FROM " . MDS_DB_PREFIX . "orders where order_id='" . intval( $item_number ) . "'";
						$result = mysqli_query( $GLOBALS['connection'], $sql ) or pp_mail_error( mysqli_error( $GLOBALS['connection'] ) . $sql );
						$row = mysqli_fetch_array( $result );

						complete_order( $row['user_id'], $item_number );
						debit_transaction( $item_number, $mc_gross, $mc_currency, $txn_id, $reason_code, 'PayPal' );

						break;
					case "Denied":
						// denied by merchant

						break;
					case "Failed":
						// only happens when payment is from customers' bank account
						break;
					case "Pending":
						$sql = "select user_id FROM " . MDS_DB_PREFIX . "orders where order_id='" . intval( $item_number ) . "'";
						$result = mysqli_query( $GLOBALS['connection'], $sql ) or pp_mail_error( mysqli_error( $GLOBALS['connection'] ) . $sql );
						$row = mysqli_fetch_array( $result );

						pend_order( $row['user_id'], $item_number );

						break;
					case "Refunded":
					case "Reversed":
						cancel_order( $item_number );
						credit_transaction( $item_number, $mc_gross, $mc_currency, $txn_id, $reason_code, 'PayPal' );

						break;
					default:
						break;
				} // end switch

			} // end web payment

		}// end if VERIFIED == true

	} // end IPN routine

	// Reply with an empty 200 response to indicate to paypal the IPN was received correctly.
	header( "HTTP/1.1 200 OK" );
}

/**
 * Payment Object
 */
class PayPal {

	var $name;
	var $description;
	var $className = "PayPal";

	function __construct() {

		global $label;

		$this->name        = $label['payment_paypal_name'];
		$this->description = $label['payment_paypal_descr'];

		if ( $this->is_installed() ) {

			//$sql = "SELECT * FROM config where `key`='PAYPAL_ENABLED' OR `key`='PAYPAL_EMAIL' OR `key`='PAYPAL_CURRENCY' OR `key`='PAYPAL_BUTTON_URL' OR `key`='PAYPAL_IPN_URL' OR `key`='PAYPAL_RETURN_URL' OR `key`='PAYPAL_CANCEL_RETURN_URL' OR `key`='PAYPAL_PAGE_STYLE' OR `key`='PAYPAL_SERVER' OR `key`='PAYPAL_AUTH_TOKEN' OR `key`='PAYPAL_SUBSCR_MODE' OR `key`='PAYPAL_SUBSCR_BUTTON_URL' ";
			$sql = "SELECT * FROM " . MDS_DB_PREFIX . "config where `key`='PAYPAL_ENABLED' OR `key`='PAYPAL_EMAIL' OR `key`='PAYPAL_CURRENCY' OR `key`='PAYPAL_BUTTON_URL' OR `key`='PAYPAL_IPN_URL' OR `key`='PAYPAL_RETURN_URL' OR `key`='PAYPAL_CANCEL_RETURN_URL' OR `key`='PAYPAL_PAGE_STYLE' OR `key`='PAYPAL_SERVER' ";
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

			while ( $row = mysqli_fetch_array( $result ) ) {
				if ( ! defined( $row['key'] ) ) {
					define( $row['key'], $row['val'] );
				}
			}
		}
	}

	function get_currency() {
		return PAYPAL_CURRENCY;
	}

	function install() {

		echo "Install PayPal..<br>";

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_ENABLED', 'N')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_EMAIL', '')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_CURRENCY', 'USD')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_BUTTON_URL', 'https://www.paypal.com/en_US/i/btn/x-click-but6.gif')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_IPN_URL', '')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_RETURN_URL', '')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_CANCEL_RETURN_URL', '')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_PAGE_STYLE', 'default')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_SERVER', 'www.paypal.com')";
		mysqli_query( $GLOBALS['connection'], $sql );
	}

	function uninstall() {

		echo "Uninstall PayPal..<br>";

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "config where `key`='PAYPAL_ENABLED'";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "config where `key`='PAYPAL_EMAIL'";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "config where `key`='PAYPAL_CURRENCY'";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "config where `key`='PAYPAL_BUTTON_URL'";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "config where `key`='PAYPAL_IPN_URL'";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "config where `key`='PAYPAL_RETURN_URL'";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "config where `key`='PAYPAL_CANCEL_RETURN_URL'";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "config where `key`='PAYPAL_PAGE_STYLE'";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "config where `key`='PAYPAL_SERVER'";
		mysqli_query( $GLOBALS['connection'], $sql );
	}

	function payment_button( $order_id ) {
		global $label;

		$sql = "SELECT * from " . MDS_DB_PREFIX . "orders where order_id='" . intval( $order_id ) . "'";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$order_row = mysqli_fetch_array( $result );

		?>

        <div style="margin:0 auto"><b><?php echo $label['payment_paypal_head']; ?></b></div>

        <form action="https://<?php echo PAYPAL_SERVER; ?>/cgi-bin/webscr" name="form1" method="post" target="_parent">

            <div style="margin:0 auto"><?php echo $label['payment_paypal_accepts']; ?></div>
            <input type="hidden" value="_xclick" name="cmd">
            <input type="hidden" value="<?php echo PAYPAL_EMAIL; ?>" name="business">
            <input type="hidden" value="<?php echo PAYPAL_IPN_URL; ?>" name="notify_url">
            <input type="hidden" value="<?php echo SITE_NAME; ?> Order #<?php echo $order_row['order_id']; ?>" name="item_name">
            <input type="hidden" value="<?php echo PAYPAL_RETURN_URL; ?>" name="return">
            <input type="hidden" value="<?php echo PAYPAL_CANCEL_RETURN_URL; ?>" name="cancel_return"/>
            <input type="hidden" value="<?php echo pp_prefix_order_id( $order_row['order_id'] ); ?>" name="invoice">
            <input type="hidden" value="<?php echo convert_to_currency( $order_row['price'], $order_row['currency'], PAYPAL_CURRENCY ); ?>" name="amount">
            <input type="hidden" value="<?php echo $order_row['order_id']; ?>" name="item_number">
            <input type="hidden" value="<?php echo $order_row['user_id']; ?>" name="custom">
            <input type="hidden" value="<?php echo PAYPAL_PAGE_STYLE; ?>" name="page_style">

            <input type="hidden" value="1" name="no_shipping"/>
            <input type="hidden" value="1" name="no_note"/>
            <input type="hidden" value="<?php echo PAYPAL_CURRENCY; ?>" name="currency_code">
            <p align="center">
                <input target="_parent" type="image" alt="<?php echo $label['payment_paypal_bttn_alt']; ?>" src="<?php echo PAYPAL_BUTTON_URL; ?>" border="0" name="submit">
            </p>
        </form>
		<?php
	}

	function config_form() {

		if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'save' ) {
			$paypal_email             = $_REQUEST['paypal_email'];
			$paypal_server            = $_REQUEST['paypal_server'];
			$paypal_ipn_url           = $_REQUEST['paypal_ipn_url'];
			$paypal_return_url        = $_REQUEST['paypal_return_url'];
			$paypal_cancel_return_url = $_REQUEST['paypal_cancel_return_url'];
			$paypal_page_style        = $_REQUEST['paypal_page_style'];
			$paypal_currency          = $_REQUEST['paypal_currency'];
			$paypal_button_url        = $_REQUEST['paypal_button_url'];
		} else {
			$paypal_email             = PAYPAL_EMAIL;
			$paypal_server            = PAYPAL_SERVER;
			$paypal_ipn_url           = PAYPAL_IPN_URL;
			$paypal_return_url        = PAYPAL_RETURN_URL;
			$paypal_cancel_return_url = PAYPAL_CANCEL_RETURN_URL;
			$paypal_page_style        = PAYPAL_PAGE_STYLE;
			$paypal_currency          = PAYPAL_CURRENCY;
			$paypal_button_url        = PAYPAL_BUTTON_URL;
		}

		// hostname
		$host = $_SERVER['SERVER_NAME'];

		$http_url = $_SERVER['PHP_SELF'];
		$http_url = explode( "/", $http_url );

		// get rid of filename
		array_pop( $http_url );

		// get rid of /admin
		array_pop( $http_url );

		$http_url = implode( "/", $http_url );

		?>
        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <table border="0" cellpadding="5" cellspacing="2" style="border-style:groove" width="100%" bgcolor="#FFFFFF">
                <tr>
                    <td bgcolor="#e6f2ea">PayPal Email address</td>
                    <td bgcolor="#e6f2ea">
                        <input type="text" name="paypal_email" size="33" value="<?php echo $paypal_email; ?>">
                        Note: Ensure that IPN is enabled for this PayPal account.
                    </td>
                </tr>
                <tr>
                    <td bgcolor="#e6f2ea">PayPal Server host</td>
                    <td bgcolor="#e6f2ea">
                        <select name="paypal_server">
                            <option value="www.paypal.com" <?php if ( $paypal_server == 'www.paypal.com' ) {
								echo " selected ";
							} ?> >PayPal [www.paypal.com]
                            </option>
                            <option value="www.sandbox.paypal.com" <?php if ( $paypal_server == 'www.sandbox.paypal.com' ) {
								echo " selected ";
							} ?>>PayPal Sand Box [www.sandbox.paypal.com]
                            </option>
                        </select>
                        Note: If you want to test the paypal IPN functions, you can set the host to PayPal's sand-box server. Set to www.paypal.com once your website goes live)
                    </td>
                </tr>
                <tr>
                    <td bgcolor="#e6f2ea">Paypal IPN URL</td>
                    <td bgcolor="#e6f2ea">
                        <input type="text" name="paypal_ipn_url" size="50" value="<?php echo $paypal_ipn_url; ?>"><br>Recommended: <b>https://<?php echo $host . $http_url . "/payment/paypalIPN.php"; ?></td>
                </tr>
                <tr>
                    <td bgcolor="#e6f2ea">Paypal Return URL</td>
                    <td bgcolor="#e6f2ea">
                        <input type="text" name="paypal_return_url" size="50" value="<?php echo $paypal_return_url; ?>"><br>(recommended: <b>https://<?php echo $host . $http_url . "/users/thanks.php?m=" . $this->className; ?></b> Note: This URL should also be entered as the 'Return URL' on the 'Website Payment prefrences in your PayPal account)
                    </td>
                </tr>
                <tr>
                    <td bgcolor="#e6f2ea">Paypal Cancelled Return URL</td>
                    <td bgcolor="#e6f2ea">
                        <input type="text" name="paypal_cancel_return_url" size="50" value="<?php echo $paypal_cancel_return_url; ?>"><br>(eg. https://<?php echo $host . $http_url . "/users/"; ?>)
                    </td>
                </tr>
                <tr>
                    <td bgcolor="#e6f2ea">Paypal Page Style</td>
                    <td bgcolor="#e6f2ea">
                        <input type="text" name="paypal_page_style" size="50" value="<?php echo $paypal_page_style; ?>"><br>(Your PayPal account's page style. Defined in your paypal account's options.)
                    </td>
                </tr>
                <tr>
                    <td bgcolor="#e6f2ea">Paypal Currency</td>
                    <td bgcolor="#e6f2ea">
                        <select name="paypal_currency">
							<?php
							$result = mysqli_query( $GLOBALS['connection'], "select * FROM " . MDS_DB_PREFIX . "currencies order by name" ) or die ( mysqli_error( $GLOBALS['connection'] ) );
							while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
								?>
                                <option value="<?php echo $row['code']; ?>" <?php if ( $paypal_currency == $row['code'] ) {
									echo " selected ";
								} ?> ><?php echo $row['code']; ?></option>
								<?php
							}
							?>
                        </select>
                        (PayPal accepts a number of currencies, and the local currency amount, if not supported, will be converted during checkout. See <a target="_blank" href="https://developer.paypal.com/docs/classic/api/currency_codes/">here</a> for more information.)
                    </td>
                </tr>

                <td bgcolor="#e6f2ea">Paypal Button Image URL</td>
                <td bgcolor="#e6f2ea">
                    <input type="text" name="paypal_button_url" size="50" value="<?php echo $paypal_button_url; ?>"><br></td>
                </tr>
                <tr>
                    <td bgcolor="#e6f2ea" colspan=2><input type="submit" value="Save"></td>
                </tr>

            </table>
            <input type="hidden" name="pay" value="<?php echo $_REQUEST['pay']; ?>">
            <input type="hidden" name="action" value="save">
        </form>
		<?php
	}

	function save_config() {
		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_EMAIL', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['paypal_email'] ) . "')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_CURRENCY', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['paypal_currency'] ) . "')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_BUTTON_URL', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['paypal_button_url'] ) . "')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_IPN_URL', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['paypal_ipn_url'] ) . "')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_RETURN_URL', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['paypal_return_url'] ) . "')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_CANCEL_RETURN_URL', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['paypal_cancel_return_url'] ) . "')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_PAGE_STYLE', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['paypal_page_style'] ) . "')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('PAYPAL_SERVER', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['paypal_server'] ) . "')";
		mysqli_query( $GLOBALS['connection'], $sql );
	}

	// true or false
	function is_enabled() {
		$sql = "SELECT val from " . MDS_DB_PREFIX . "config where `key`='PAYPAL_ENABLED' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$row = mysqli_fetch_array( $result );
		if ( isset($row['val']) && $row['val'] == 'Y' ) {
			return true;
		} else {
			return false;
		}
	}

	// true or false
	function is_installed() {
		$sql = "SELECT val from " . MDS_DB_PREFIX . "config where `key`='PAYPAL_ENABLED' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
		if ( mysqli_num_rows( $result ) > 0 ) {
			return true;
		} else {
			return false;
		}
	}

	function enable() {
		$sql = "UPDATE " . MDS_DB_PREFIX . "config set val='Y' where `key`='PAYPAL_ENABLED' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
	}

	function disable() {
		$sql = "UPDATE " . MDS_DB_PREFIX . "config set val='N' where `key`='PAYPAL_ENABLED' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
	}

	function process_payment_return() {
	}
}
