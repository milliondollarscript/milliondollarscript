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

$_PAYMENT_OBJECTS['woocommerce'] = new MdsWooCommercePaymentModule;

class MdsWooCommercePaymentModule {

	var $name = "WooCommerce";
	var $description = "Use WooCommerce integration";
	var $className = "MdsWooCommercePaymentModule";

	function __construct() {
		require_once __DIR__ . "/../include/init.php";

		if ( $this->is_installed() ) {

			$sql = "SELECT * FROM " . MDS_DB_PREFIX . "config WHERE 
                           `key`='WOOCOMMERCE_ENABLED' OR 
                           `key`='WOOCOMMERCE_URL' OR 
                           `key`='WOOCOMMERCE_AUTO_APPROVE' OR 
                           `key`='WOOCOMMERCE_BUTTON_TEXT' OR
                           `key`='WOOCOMMERCE_BUTTON_IMAGE' OR
                           `key`='WOOCOMMERCE_REDIRECT'
                           ";
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

			while ( $row = mysqli_fetch_array( $result ) ) {
				$val = isset( $row['val'] ) ? $row['val'] : "";
				if ( ! defined( $row['key'] ) ) {
					define( $row['key'], $val );
				}
			}
		}
	}

	function install() {
		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('WOOCOMMERCE_ENABLED', '')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('WOOCOMMERCE_URL', '')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('WOOCOMMERCE_AUTO_APPROVE', '')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('WOOCOMMERCE_BUTTON_TEXT', '')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('WOOCOMMERCE_BUTTON_IMAGE', '')";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('WOOCOMMERCE_REDIRECT', '')";
		mysqli_query( $GLOBALS['connection'], $sql );
	}

	function uninstall() {
		$sql = "DELETE FROM " . MDS_DB_PREFIX . "config WHERE `key`='WOOCOMMERCE_ENABLED'";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "config WHERE `key`='WOOCOMMERCE_URL'";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "config WHERE `key`='WOOCOMMERCE_AUTO_APPROVE'";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "config WHERE `key`='WOOCOMMERCE_BUTTON_TEXT'";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "config WHERE `key`='WOOCOMMERCE_BUTTON_IMAGE'";
		mysqli_query( $GLOBALS['connection'], $sql );

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "config WHERE `key`='WOOCOMMERCE_REDIRECT'";
		mysqli_query( $GLOBALS['connection'], $sql );
	}

	function payment_button( $order_id ) {
		if ( WOOCOMMERCE_REDIRECT == "yes" ) {
			self::process_payment_return();
		} else {

			$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id=" . intval( $order_id );
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
			$order_row = mysqli_fetch_array( $result );

			?>
            <div style="text-align: center;">
                <input type="image" src="<?php echo htmlspecialchars( WOOCOMMERCE_BUTTON_IMAGE, ENT_QUOTES ); ?>" alt="<?php echo htmlspecialchars( WOOCOMMERCE_BUTTON_TEXT, ENT_QUOTES ); ?>" value="<?php echo htmlspecialchars( WOOCOMMERCE_BUTTON_TEXT, ENT_QUOTES ); ?>" onclick="window.location='<?php echo htmlspecialchars( BASE_HTTP_PATH . "users/thanks.php?m=" . $this->className . "&order_id=" . $order_row['order_id'], ENT_QUOTES ); ?>'">
            </div>

			<?php
		}
	}

	function config_form() {

		if ( isset($_REQUEST['action']) && $_REQUEST['action'] == 'save' ) {
			$woocommerce_enabled      = $_REQUEST['woocommerce_enabled'];
			$woocommerce_url          = $_REQUEST['woocommerce_url'];
			$woocommerce_auto_approve = $_REQUEST['woocommerce_auto_approve'];
			$woocommerce_button_text  = $_REQUEST['woocommerce_button_text'];
			$woocommerce_button_image = $_REQUEST['woocommerce_button_image'];
			$woocommerce_redirect     = $_REQUEST['woocommerce_redirect'];
		} else {
			$woocommerce_enabled      = WOOCOMMERCE_ENABLED;
			$woocommerce_url          = WOOCOMMERCE_URL;
			$woocommerce_auto_approve = WOOCOMMERCE_AUTO_APPROVE;
			$woocommerce_button_text  = WOOCOMMERCE_BUTTON_TEXT;
			$woocommerce_button_image = WOOCOMMERCE_BUTTON_IMAGE;
			$woocommerce_redirect     = WOOCOMMERCE_REDIRECT;
		}

		?>
        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <table border="0" cellpadding="5" cellspacing="2" style="border-style:groove" width="100%" bgcolor="#FFFFFF">

                <tr>
                    <td colspan="2" bgcolor="#e6f2ea">
                        <span style="font-family: Verdana,serif; font-size: xx-small; "><b>WooCommerce Payment Settings</b></span>
                    </td>
                </tr>
                <tr>
                    <td width="20%" bgcolor="#e6f2ea">
                        <span style="font-family: Verdana,serif; font-size: xx-small; ">WooCommerce Checkout URL</span></td>
                    <td bgcolor="#e6f2ea"><span style="font-family: Verdana,serif; font-size: xx-small; ">
                            <input type="text" name="woocommerce_url" value="<?php echo htmlspecialchars( $woocommerce_url, ENT_QUOTES ); ?>"></span>
                        <div>Scenario: https://example.com/checkout/?add-to-cart=##&quantity=%QUANTITY% - Make a product in WooCommerce that is Virtual and set to the price of a single block. ## is your product id.</div>
                        <div>%AMOUNT% will be replaced with the order amount.</div>
                        <div>%CURRENCY% will be replaced with the order currency.</div>
                        <div>%QUANTITY% will be replaced with the number of blocks ordered.</div>
                    </td>
                </tr>
                <tr>
                    <td bgcolor="#e6f2ea">
                        <span style="font-family: Verdana,serif; font-size: xx-small; ">Auto-approve?</span></td>
                    <td bgcolor="#e6f2ea">
                        <span style="font-family: Verdana,serif; font-size: xx-small; ">
                            <select name="woocommerce_auto_approve">
                                <option value="yes"<?php if ( $woocommerce_auto_approve == "yes" ) {
	                                echo 'selected="selected"';
                                } ?>>Yes</option>
                                <option value="no"<?php if ( $woocommerce_auto_approve == "no" ) {
	                                echo 'selected="selected"';
                                } ?>>No</option>
                            </select>
                        </span>
                        <div>Setting to Yes will automatically approve orders before payments are verified by an admin.</div>
                    </td>
                </tr>
                <tr>
                    <td width="20%" bgcolor="#e6f2ea">
                        <span style="font-family: Verdana,serif; font-size: xx-small; ">Button text</span></td>
                    <td bgcolor="#e6f2ea"><span style="font-family: Verdana,serif; font-size: xx-small; ">
                        <input type="text" name="woocommerce_button_text" value="<?php echo htmlspecialchars( $woocommerce_button_text, ENT_QUOTES ); ?>"></span>
                    </td>
                </tr>
                <tr>
                    <td width="20%" bgcolor="#e6f2ea">
                        <span style="font-family: Verdana,serif; font-size: xx-small; ">Button image</span></td>
                    <td bgcolor="#e6f2ea"><span style="font-family: Verdana,serif; font-size: xx-small; ">
                        <input type="text" name="woocommerce_button_image" value="<?php echo htmlspecialchars( $woocommerce_button_image, ENT_QUOTES ); ?>"></span>
                    </td>
                </tr>
                <tr>
                    <td bgcolor="#e6f2ea">
                        <span style="font-family: Verdana,serif; font-size: xx-small; ">Auto-redirect?</span></td>
                    <td bgcolor="#e6f2ea">
                        <span style="font-family: Verdana,serif; font-size: xx-small; ">
                            <select name="woocommerce_redirect">
                                <option value="yes"<?php if ( $woocommerce_redirect == "yes" ) {
	                                echo 'selected="selected"';
                                } ?>>Yes</option>
                                <option value="no"<?php if ( $woocommerce_redirect == "no" ) {
	                                echo 'selected="selected"';
                                } ?>>No</option>
                            </select>
                        </span>
                        <div>Setting to Yes will cause users to automatically redirect to the URL entered above instead of having to click the button.</div>
                    </td>
                </tr>
                <tr>

                    <td bgcolor="#e6f2ea" colspan=2>
                        <span style="font-family: Verdana,serif; font-size: xx-small; "><input type="submit" value="Save"></span>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="pay" value="<?php echo htmlspecialchars( $_REQUEST['pay'], ENT_QUOTES ); ?>">
            <input type="hidden" name="action" value="save">

        </form>

		<?php
	}

	function save_config() {

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('WOOCOMMERCE_URL', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['woocommerce_url'] ) . "')";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('WOOCOMMERCE_AUTO_APPROVE', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['woocommerce_auto_approve'] ) . "')";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('WOOCOMMERCE_BUTTON_TEXT', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['woocommerce_button_text'] ) . "')";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('WOOCOMMERCE_BUTTON_IMAGE', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['woocommerce_button_image'] ) . "')";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "config (`key`, val) VALUES ('WOOCOMMERCE_REDIRECT', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['woocommerce_redirect'] ) . "')";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	}

	// true or false
	function is_enabled() {

		$sql = "SELECT val FROM `" . MDS_DB_PREFIX . "config` WHERE `key`='WOOCOMMERCE_ENABLED' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$row = mysqli_fetch_array( $result );
		if ( $row['val'] == 'Y' ) {
			return true;
		} else {
			return false;
		}
	}

	function is_installed() {

		$sql = "SELECT val FROM " . MDS_DB_PREFIX . "config WHERE `key`='WOOCOMMERCE_ENABLED' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
		//$row = mysqli_fetch_array($result);

		if ( mysqli_num_rows( $result ) > 0 ) {
			return true;
		} else {
			return false;
		}
	}

	function enable() {

		$sql = "UPDATE " . MDS_DB_PREFIX . "config SET val='Y' WHERE `key`='WOOCOMMERCE_ENABLED' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
	}

	function disable() {

		$sql = "UPDATE " . MDS_DB_PREFIX . "config SET val='N' WHERE `key`='WOOCOMMERCE_ENABLED' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
	}

	function process_payment_return() {
		global $f2;

		if ( ( $_REQUEST['order_id'] != '' ) ) {

			if ( $_SESSION['MDS_ID'] == '' ) {

				echo "Error: You must be logged in to view this page";
			} else {

				$order_id = intval( $_REQUEST['order_id'] );

				// Save order id in cookie for later
				$_COOKIE['mds_order_id'] = $order_id;

				$url = $f2->filter( WOOCOMMERCE_URL );

				$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id=" . $order_id;
				$result = mysqli_query( $GLOBALS['connection'], $sql ) or payment_mail_error( mysqli_error( $GLOBALS['connection'] ) . $sql, 'woocommerce' );
				$row = mysqli_fetch_array( $result );

				if ( WOOCOMMERCE_AUTO_APPROVE == "yes" ) {
					complete_order( $row['user_id'], $order_id );
					debit_transaction( $order_id, $row['price'], $row['currency'], 'WooCommerce', $url, 'WooCommerce' );
				}

				$banner_data = load_banner_constants( $row['banner_id'] );
				$quantity    = intval( $row['quantity'] ) / intval( $banner_data['block_width'] ) / intval( $banner_data['block_height'] );

				$dest = str_replace( '%AMOUNT%', urlencode( $row['price'] ), $url );
				$dest = str_replace( '%CURRENCY%', urlencode( $row['currency'] ), $dest );
				$dest = str_replace( '%QUANTITY%', urlencode( $quantity ), $dest );
				if(strpos($dest, '?') !== false) {
					$dest = $dest . '&mdsid=' . $order_id;
				} else {
					$dest = $dest . '?mdsid=' . $order_id;
				}

				if ( WOOCOMMERCE_REDIRECT == "yes" ) {
					echo "<span style='color:#000'>Please wait, redirecting...</span>";
					echo "<script>top.window.location = '$dest'</script>";
					exit;
				}
			}
		}
	}

	function complete_order( $order_id ) {
		global $f2;

		$url = $f2->filter( WOOCOMMERCE_URL );

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id='" . intval( $order_id ) . "'";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or payment_mail_error( mysqli_error( $GLOBALS['connection'] ) . $sql, 'woocommerce' );
		$row = mysqli_fetch_array( $result );

		complete_order( $row['user_id'], $order_id );
		debit_transaction( $order_id, $row['price'], $row['currency'], 'WooCommerce', substr($url, 0, 64), 'WooCommerce' );
	}

	function get_quantity( $order_id ) {
		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id='" . intval( $order_id ) . "'";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or payment_mail_error( mysqli_error( $GLOBALS['connection'] ) . $sql, 'woocommerce' );
		$row = mysqli_fetch_array( $result );

		return $row['quantity'];
	}

}

?>