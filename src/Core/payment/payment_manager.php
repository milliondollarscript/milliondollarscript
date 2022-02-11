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

$_PAYMENT_OBJECTS = array();
$PAYMENT_PATH     = BASE_PATH . "/payment/";

$dh = opendir( $PAYMENT_PATH );

while ( ( $file = readdir( $dh ) ) !== false ) {
	if ( ( $file != '.' ) && ( $file != '..' ) && ( strpos( $file, ".php" ) > 0 ) && ( $file != "payment_manager.php" ) ) {
		if ( file_exists( $PAYMENT_PATH . $file ) ) {
			require_once $PAYMENT_PATH . $file;
		}
	}
}

closedir( $dh );

if ( isset($_REQUEST['pay']) && isset( $_REQUEST['action'] ) ) {

	if ( $_REQUEST['action'] == 'save' ) {
		$obj = $_PAYMENT_OBJECTS[ $_REQUEST['pay'] ];
		$obj->save_config();
	}

	if ( $_REQUEST['action'] == 'install' ) {

		$obj = $_PAYMENT_OBJECTS[ $_REQUEST['pay'] ];
		$obj->install();
		// reload object
		$_PAYMENT_OBJECTS[ $_REQUEST['pay'] ] = new $_REQUEST['pay'];
	}

	if ( $_REQUEST['action'] == 'uninstall' ) {

		$obj = $_PAYMENT_OBJECTS[ $_REQUEST['pay'] ];
		$obj->uninstall();
	}

	if ( $_REQUEST['action'] == 'enable' ) {

		$obj = $_PAYMENT_OBJECTS[ $_REQUEST['pay'] ];
		$obj->enable();
	}

	if ( $_REQUEST['action'] == 'disable' ) {

		$obj = $_PAYMENT_OBJECTS[ $_REQUEST['pay'] ];
		$obj->disable();
	}
}

function list_avalable_payments() {

	global $_PAYMENT_OBJECTS;

	?>
    <table border="0">
        <tr>
            <td valign="top">
                <table border="0" cellSpacing="1" cellPadding="3" bgColor="#d9d9d9" width="400">
                    <tr bgColor="#eaeaea">
                        <td><b><font size="2">Payment Module</b></font></td>
                        <td><b><font size="2">Description</b></font></td>
                        <td><b><font size="2">Status</b></font></td>
                        <td><b><font size="2">&nbsp;</b></font></td>

                    </tr>
					<?php

					foreach ( $_PAYMENT_OBJECTS as $obj_key => $obj ) {

						?>

                        <tr <?php if ( isset($_REQUEST['pay']) && $obj_key == $_REQUEST['pay'] ) {
							echo ' bgColor="#FFFF99" ';
						} else {
							echo ' bgColor="#ffffff" ';
						} ?> onmouseover="old_bg=this.getAttribute('bgcolor');this.setAttribute('bgcolor', '#FBFDDB', 0);" onmouseout="this.setAttribute('bgcolor', old_bg, 0);">
                            <td><font size="2"><a href="<?php echo $_SERVER['PHP_SELF']; ?>?pay=<?php echo $obj_key; ?>"><?php echo $obj->name; ?></a></font></td>
                            <td><font size="2"><?php echo $obj->description; ?></font></td>
                            <td><font size="2"><?php

									if ( ! $obj->is_installed() ) {
										echo "<font color='red'>Not Installed</font>";
									} else {

										if ( $obj->is_enabled() ) {
											echo "<font color='green'>Enabled</font>";
										} else {
											echo "<font color='red'>Not Enabled</font>";
										}
									}

									?></font></td>
                            <td nowrap><font size="2"><?php

									if ( isset($_REQUEST['pay']) && $obj_key == $_REQUEST['pay'] ) {
										if ( ! $obj->is_installed() ) {
											echo "<input type='button' style='font-size: 10px;' value='Install' onclick=\"if (!confirmLink(this, 'Install, are you sure?')) return false;\" data-link='" . $_SERVER['PHP_SELF'] . "?pay=" . $obj_key . "&action=install'>";
										} else {

											if ( $obj->is_enabled() ) {
												//	echo "Enabled";
												echo "<input type='button' style='font-size: 10px;' value='Disable' onclick=\"if (!confirmLink(this, 'Disable, are you sure?')) return false;\" data-link='" . $_SERVER['PHP_SELF'] . "?pay=" . $obj_key . "&action=disable'>";
											} else {
												//echo "Not Enabled";
												echo "<input style='font-size: 10px;' type='button' value='Enable' onclick=\"if (!confirmLink(this, 'Enable, are you sure?')) return false;\" data-link='" . $_SERVER['PHP_SELF'] . "?pay=" . $obj_key . "&action=enable'>";
											}

											echo " &nbsp; <input style='font-size: 10px;' type='button' value='Uninstall' onclick=\" if (!confirmLink(this, 'Uninstall, are you sure?')) return false;\" data-link='" . $_SERVER['PHP_SELF'] . "?pay=" . $obj_key . "&action=uninstall'>";
										}

//										if ( $obj->is_installed() ) {
											//	$obj->config_form();
//										}
									} else {

										if ( $obj->is_installed() ) {
											echo "<input style='font-size: 10px;' type='button' value='Configure' onclick=\"mds_load_page('" . $_SERVER['PHP_SELF'] . "?pay=" . $obj_key . "', true)\">";
										} else {

											echo "<input type='button' style='font-size: 10px;' value='Install' onclick=\"if (!confirmLink(this, 'Install, are you sure?')) return false;\" data-link='" . $_SERVER['PHP_SELF'] . "?pay=" . $obj_key . "&action=install'\">";
										}
									}

									?></font></td>

                        </tr>

						<?php
					}

					?>
                </table>

            </td>
            <td valign="top">
				<?php

				if ( isset($_REQUEST['pay']) && $_REQUEST['pay'] != '' ) {
					if ( $_PAYMENT_OBJECTS[ $_REQUEST['pay'] ]->is_installed() ) {
						$_PAYMENT_OBJECTS[ $_REQUEST['pay'] ]->config_form();
					}
				}

				?>
            </td>
        </tr>
    </table>
	<?php
}

function payment_option_list( $order_id ) {

	global $_PAYMENT_OBJECTS, $label;

	?>
    <table style="border:none;padding:3px;background:#c0c0c0;width:95%;margin:0 auto;">
        <tr style="background:#d9d9d9;">
            <td><b><?php echo $label['payment_mab_btt']; ?></b></td>
            <td><b><?php echo $label['payment_man_pt']; ?></b></td>
            <td><b><?php echo $label['payment_man_descr']; ?></b></td>
        </tr>
		<?php
		foreach ( $_PAYMENT_OBJECTS

		as $obj_key => $obj ) {

		if ( $obj->is_enabled() ){ ?>
        <tr style="background:#E9E9E9;" onmouseover="old_bg=this.getAttribute('bgcolor');this.setAttribute('bgcolor', '#FBFDDB', 0);" onmouseout="this.setAttribute('bgcolor', old_bg, 0);">
            <td style="margin:5px;"><?php echo $obj->payment_button( $order_id ); ?></td>
            <td><?php echo $obj->name; ?></td>
            <td><?php echo $obj->description; ?></td>
        <tr>
			<?php
			}
			} ?>
    </table>

	<?php
}

// Called from thanks.php page.
// The call is then delegated to the specific class process_payment_return() function
function process_payment_return( $className ) {

	global $_PAYMENT_OBJECTS;

	$obj = $_PAYMENT_OBJECTS[ $className ];

	if ( isset( $obj ) ) {

		$obj->process_payment_return();
	} else {
		echo "Warning: payment_manager.php detected that the return URL is incorrect for this payment method.";
	}
}

function payment_mail_error( $msg, $module ) {

	$date = date( "D, j M Y H:i:s O" );

	$headers = "From: " . SITE_CONTACT_EMAIL . "\r\n";
	$headers .= "Reply-To: " . SITE_CONTACT_EMAIL . "\r\n";
	$headers .= "Return-Path: " . SITE_CONTACT_EMAIL . "\r\n";
	$headers .= "X-Mailer: PHP" . "\r\n";
	$headers .= "Date: $date" . "\r\n";
	$headers .= "X-Sender-IP: " . $_SERVER['REMOTE_ADDR'] . "\r\n";

	@mail( SITE_CONTACT_EMAIL, "Error message from " . SITE_NAME . " {$module} payment module. ", $msg, $headers );
}

function payment_log_entry( $entry_line ) {
	$entry_line = "Payment:$entry_line\r\n ";
	error_log( $entry_line );
}
