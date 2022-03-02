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

use MillionDollarScript\Classes\Options;

function mds_check_permission( $permission ) {
	if ( defined( 'WP_ENABLED' ) && WP_ENABLED == 'YES' ) {
		if (current_user_can( $permission )) {
            return true;
		} else {
            return false;
        }
	} else {
        // If WP isn't enabled allow permission
        return true;
    }
}

function process_login() {

	global $f2, $label;

	$session_duration = ini_get( "session.gc_maxlifetime" );
	if ( $session_duration == '' ) {
		$session_duration = 60 * 20;
	}

	$now = ( gmdate( "Y-m-d H:i:s" ) );
	$sql = "UPDATE `" . MDS_DB_PREFIX . "users` SET `logout_date`='$now' WHERE UNIX_TIMESTAMP(DATE_SUB('$now', INTERVAL $session_duration SECOND)) > UNIX_TIMESTAMP(last_request_time) AND (`logout_date` ='1000-01-01 00:00:00')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( $sql . mysqli_error( $GLOBALS['connection'] ) );

	if ( ! is_logged_in() || ( $_SESSION['MDS_Domain'] != "ADVERTISER" ) ) {
		if ( WP_ENABLED == "YES" && WP_USERS_ENABLED == "YES" ) {
			mds_wp_login_check();
		}

		require_once BASE_PATH . "/html/header.php";

		if ( isset( $_REQUEST['forgot'] ) ) {
			$str = str_replace( "%BASE_HTTP_PATH%", BASE_HTTP_PATH, $label["advertiser_forgot_success1"] );
			echo "<p style='text-align:center;'>" . $str . "</p>";
		}
		?>
        <table cellpadding=5 border=1 style="width: 100%;border-collapse: collapse; border-style:solid; border-color:#E8E8E8">

            <tr>
                <td valign="top">
                    <center><h3><?php echo $label["advertiser_section_heading"]; ?></h3></center>
					<?php
					login_form();
					?>

                </td>
				<?php

				if ( USE_AJAX == 'SIMPLE' ) {

					?>
                    <td valign=top>
                        <center>
                            <h3><?php echo $label["advertiser_section_newusr"];
								if ( USE_AJAX == 'SIMPLE' ) {
									$order_page = 'order_pixels.php';
								} else {
									$order_page = 'select.php';
								}
								?></h3>
                            <a class="big_link" href="<?php echo $order_page; ?>"><?php echo $label["adv_login_new_link"]; ?></a> <br><br><?php echo $label["advertiser_go_buy_now"]; ?>
                            <h3></h3></center>
                    </td>
					<?php
				}
				?>
            </tr>
        </table>
		<?php
		require_once BASE_PATH . "/html/footer.php";
		die ();
	} else {
		// update last_request_time
		$now = ( gmdate( "Y-m-d H:i:s" ) );
		$sql = "UPDATE `" . MDS_DB_PREFIX . "users` SET `last_request_time`='$now', logout_date='1000-01-01 00:00:00' WHERE `Username`='" . mysqli_real_escape_string( $GLOBALS['connection'], $_SESSION['MDS_Username'] ) . "'";
		mysqli_query( $GLOBALS['connection'], $sql ) or die( $sql . mysqli_error( $GLOBALS['connection'] ) );
	}
}

function is_logged_in() {
	global $_SESSION;

	if ( WP_ENABLED == 'YES' && WP_USERS_ENABLED == 'YES' ) {

		require_once WP_PATH . '/wp-load.php';
		require_once WP_PATH . '/wp-includes/pluggable.php';

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();

		// get user from MDS db
		$result = mysqli_query( $GLOBALS['connection'], "SELECT * FROM `" . MDS_DB_PREFIX . "users` WHERE username='" . mysqli_real_escape_string( $GLOBALS['connection'], $user->user_login ) . "'" ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		$row = mysqli_fetch_array( $result );
		if ( isset( $row ) && ! $row['Username'] ) {
			return false;
		}

		$_SESSION['MDS_ID']        = $row['ID'];
		$_SESSION['MDS_FirstName'] = $row['FirstName'];
		$_SESSION['MDS_LastName']  = $row['LastName'];
		$_SESSION['MDS_Username']  = $row['Username'];
		$_SESSION['MDS_Rank']      = $row['Rank'];
		//$_SESSION['MDS_order_id'] = '';
		$_SESSION['MDS_Domain'] = 'ADVERTISER';

		// TODO: why is this even here? should per-user lang be a thing?
//		if ( $row['lang'] != '' ) {
//			$_SESSION['MDS_LANG'] = $row['lang'];
//		}

		return true;
	}

	if ( ! isset( $_SESSION['MDS_ID'] ) ) {
		$_SESSION['MDS_ID'] = '';
	} else {
		// Check database for user id. If user was deleted it won't exist anymore so we have to log them out.
		$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "users` WHERE `ID`='" . mysqli_real_escape_string( $GLOBALS['connection'], $_SESSION['MDS_ID'] ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
		if ( empty( mysqli_num_rows( $result ) ) ) {

			// save any orders currently in progress though if in simple mode
			$current_order_id = 0;
			if ( USE_AJAX == 'SIMPLE' ) {
				$current_order_id = get_current_order_id();
			}

			session_destroy();

			$_SESSION['MDS_ID']       = '';
			$_SESSION['MDS_order_id'] = $current_order_id;
		}
	}

	return $_SESSION['MDS_ID'];
}

function login_form( $show_signup_link = true, $target_page = 'index.php' ) {
	global $label;

	?>
    <table align="center">
        <tr>
            <td>
                <form name="form1" method="post" action="login.php?lang=<?php echo get_lang(); ?>&target_page=<?php echo $target_page; ?>">
                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                        <tr>
                            <td width="50%" nowrap><span><?php echo $label["advertiser_signup_member_id"]; ?>:</span></td>
                            <td><input name="Username" type="text" id="username" size="12"/></td>
                        </tr>
                        <tr>
                            <td width="50%"><span><?php echo $label["advertiser_signup_password"]; ?>:</span></td>
                            <td><input name="Password" type="password" id="password" size="12"/></td>
                        </tr>
                        <tr>
                            <td width="50%">&nbsp;</td>
                            <td>
                                <div align="right">
                                    <input type="submit" class="form_submit_button" name="Submit" value="<?php echo $label["advertiser_login"]; ?>"/>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan=2><a href='<?php echo esc_url( Options::get_option( 'forgot-password-page' ) ); ?>'><?php echo $label["advertiser_pass_forgotten"]; ?></a></td>
                        </tr>
                    </table>
                </form>
            </td>
        </tr>
        <tr>
            <td height="20">
                <div align="center"></div>
            </td>
        </tr>
		<?php if ( $show_signup_link ) { ?>
            <tr>
                <td>
                    <div align="center"><a href="<?php echo esc_url( Options::get_option( 'register-page' ) ); ?>"><h3><?php echo $label["advertiser_join_now"]; ?></h3></a></div>
                </td>
            </tr>
		<?php } ?>
        <tr>
            <td height="20">
                <div align="center"></div>
            </td>
        </tr>

        <tr>
            <td>
                <div align="center"><!-- signed up.--> </div>
            </td>
        </tr>
    </table>
	<?php
}

function create_new_account( $REMOTE_ADDR, $FirstName, $LastName, $CompName, $Username, $pass, $Email ) {

	global $label;

	$Password = md5( $pass );

	$validated = 0;

	if ( EM_NEEDS_ACTIVATION == "AUTO" ) {
		$validated = 1;
	}
	$now = ( gmdate( "Y-m-d H:i:s" ) );
	// everything Ok, create account and send out emails.
	$sql = "Insert Into " . MDS_DB_PREFIX . "users(IP, SignupDate, FirstName, LastName, CompName, Username, Password, Email, Validated, Aboutme) values('" . mysqli_real_escape_string( $GLOBALS['connection'], $REMOTE_ADDR ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $now ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $FirstName ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $LastName ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $CompName ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $Username ) . "', '$Password', '" . mysqli_real_escape_string( $GLOBALS['connection'], $Email ) . "', '$validated', '')";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( $sql . mysqli_error( $GLOBALS['connection'] ) );
	$res = mysqli_affected_rows( $GLOBALS['connection'] );

	if ( $res > 0 ) {
		$success = true; //succesfully added to the database
		echo "<center>" . $label['advertiser_new_user_created'] . "</center>";
	} else {
		$success = false;
		$error   = $label['advertiser_could_not_signup'];
	}

	$advertiser_signup_success = $validated ? $label['advertiser_signup_success_1'] : $label['advertiser_signup_success_2'];
	$advertiser_signup_success = str_replace( "%FirstName%", stripslashes( $FirstName ), $advertiser_signup_success );
	$advertiser_signup_success = str_replace( "%LastName%", stripslashes( $LastName ), $advertiser_signup_success );
	$advertiser_signup_success = str_replace( "%SITE_NAME%", SITE_NAME, $advertiser_signup_success );
	$advertiser_signup_success = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $advertiser_signup_success );
	echo $advertiser_signup_success;

	//Here the emailmessage itself is defined, this will be send to your members. Don't forget to set the validation link here.

	return $success;
}

function validate_signup_form() {

	global $label;

	$error = "";
	if ( $_REQUEST['Password'] != $_REQUEST['Password2'] ) {
		$error .= $label["advertiser_signup_error_pmatch"];
	}

	if ( $_REQUEST['FirstName'] == '' ) {
		$error .= $label["advertiser_signup_error_name"];
	}
	if ( $_REQUEST['LastName'] == '' ) {
		$error .= $label["advertiser_signup_error_ln"];
	}

	if ( $_REQUEST['Username'] == '' ) {
		//$error .= "* Please fill in Your Member I.D.<br/>";
		$error .= $label["advertiser_signup_error_user"];
	} else {
		$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "users` WHERE `Username`='" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['Username'] ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$row = mysqli_fetch_array( $result );
		if ( $row['Username'] != '' ) {
			$error .= str_replace( "%username%", $row['Username'], $label['advertiser_signup_error_inuse'] );
		}
	}
	//echo "my friends $form";
	if ( $_REQUEST['Password'] == '' ) {

		$error .= $label["advertiser_signup_error_p"];
	}

	if ( $_REQUEST['Password2'] == '' ) {
		$error .= $label["advertiser_signup_error_p2"];
	}

	if ( $_REQUEST['Email'] == '' ) {
		$error .= $label["advertiser_signup_error_email"];
	} else {
		$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "users` WHERE `Email`='" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['Email'] ) . "'";
		//echo $sql;
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$row = mysqli_fetch_array( $result );

		//validate email ";

		if ( isset( $row['Email'] ) && $row['Email'] != '' ) {
			$error .= " " . $label["advertiser_signup_email_in_use"] . " ";
		}
	}

	return $error;
}

function display_signup_form( $FirstName, $LastName, $CompName, $Username, $password, $password2, $Email ) {

	global $label, $f2;

	$FirstName = $f2->filter( stripslashes( $FirstName ) );
	$LastName  = $f2->filter( stripslashes( $LastName ) );
	$CompName  = $f2->filter( stripslashes( $CompName ) );
	$Username  = $f2->filter( $Username );
	$password  = $f2->filter( stripslashes( $password ) );
	$password2 = $f2->filter( stripslashes( $password2 ) );
	$Email     = $f2->filter( $Email, FILTER_SANITIZE_EMAIL );

	?>

    <form name="form1" method="post" action="<?php echo htmlentities( $_SERVER['PHP_SELF'] ); ?>?page=signup&form=filled">
        <table width="100%" border="0" cellspacing="3" cellpadding="0">
            <tr>
                <td width="25%"><span>*<?php echo $label["advertiser_signup_first_name"]; ?>:</span></td>
                <td width="86%"><input name="FirstName" value="<?php echo stripslashes( $FirstName ); ?>" type="text" id="firstname"></td>
            </tr>
            <tr>
                <td width="25%">*<?php echo $label["advertiser_signup_last_name"]; ?>:</td>
                <td width="86%"><input name="LastName" value="<?php echo stripslashes( $LastName ); ?>" type="text" id="lastname"></td>
            </tr>
            <tr>
                <td width="25%" valign="top"><?php echo $label["advertiser_signup_business_name"]; ?>:</td>
                <td width="86%"><input name="CompName" value="<?php echo stripslashes( $CompName ); ?>" size="30" type="text" id="compname"/><span> (<?php echo $label["advertiser_signup_business_name2"]; ?>)</span></td>
            </tr>
            <tr>
                <td width="25%" height="20">&nbsp;</td>
                <td width="86%" height="20">&nbsp;</td>
            </tr>
            <tr>
                <td width="25%" valign="top">*<?php echo $label["advertiser_signup_member_id"]; ?>:</td>
                <td width="86%"><input name="Username" value="<?php echo $Username; ?>" type="text" id="username"><span> <?php echo $label["advertiser_signup_member_id2"]; ?></span></td>
            </tr>
            <tr>
                <td width="25%" nowrap>*<?php echo $label["advertiser_signup_password"]; ?>:</td>
                <td><input name="Password" type="password" value="<?php echo stripslashes( $password ); ?>" id="password"></td>
            </tr>
            <tr>
                <td width="25%">*<?php echo $label["advertiser_signup_password_confirm"]; ?>:</td>
                <td><input name="Password2" type="password" value="<?php echo stripslashes( $password2 ); ?>" id="password2"></td>
            </tr>
            <tr>
                <td>&nbsp</td>
                <td></td>
            </tr>
            <tr>
                <td width="25%">*<?php echo $label["advertiser_signup_your_email"]; ?></td>
                <td><input name="Email" type="text" id="email" value="<?php echo $Email; ?>" size="30"/></td>
            </tr>

        </table>
        <div align="center">

            <p><input type="submit" class="form_submit_button" name="Submit" value="<?php echo $label["advertiser_signup_submit"]; ?>">
                <!--<input type="reset" class="form_reset_button" name="Submit2" value="<?php echo $label["advertiser_signup_reset"]; ?>">-->
            </p>
        </div>
    </form>
	<?php
}

function process_signup_form( $target_page = 'index.php' ) {

	global $label;

	$FirstName = ( $_POST['FirstName'] );
	$LastName  = ( $_POST['LastName'] );
	$CompName  = ( $_POST['CompName'] );
	$Username  = ( $_POST['Username'] );
	$Password  = md5( $_POST['Password'] );
	$Password2 = md5( $_POST['Password2'] );
	$Email     = ( $_POST['Email'] );

	$error = validate_signup_form();

	if ( $error != '' ) {
		// error processing signup/

		echo "<span class='error_msg_label'>" . $label["advertiser_signup_error"] . "</span><P>";
		echo "<span ><b>" . $error . "</b></span>";

		return false;
	} else {

		//$target_page="index.php";

		$success = create_new_account( $_SERVER['REMOTE_ADDR'], $FirstName, $LastName, $CompName, $Username, $_REQUEST['Password'], $Email );

		if ( ( EM_NEEDS_ACTIVATION == "AUTO" ) ) {

			$label["advertiser_signup_success_1"] = stripslashes( str_replace( "%FirstName%", $FirstName, $label["advertiser_signup_success_1"] ) );

			$label["advertiser_signup_success_1"] = stripslashes( str_replace( "%LastName%", $LastName, $label["advertiser_signup_success_1"] ) );

			$label["advertiser_signup_success_1"] = stripslashes( str_replace( "%SITE_NAME%", SITE_NAME, $label["advertiser_signup_success_1"] ) );

			$label["advertiser_signup_success_1"] = stripslashes( str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $label["advertiser_signup_success_1"] ) );

			echo $label["advertiser_signup_success_1"];
		} else {

			$label["advertiser_signup_success_2"] = stripslashes( str_replace( "%FirstName%", $FirstName, $label["advertiser_signup_success_2"] ) );

			$label["advertiser_signup_success_2"] = stripslashes( str_replace( "%LastName%", $LastName, $label["advertiser_signup_success_2"] ) );

			$label["advertiser_signup_success_2"] = stripslashes( str_replace( "%SITE_NAME%", SITE_NAME, $label["advertiser_signup_success_2"] ) );

			$label["advertiser_signup_success_2"] = stripslashes( str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $label["advertiser_signup_success_2"] ) );

			echo $label["advertiser_signup_success_2"];

			//echo "<center>".$label["advertiser_signup_goback"]."</center>";

			send_confirmation_email( $Email );
		}

		echo "<center><form method='post' action='login.php?target_page=" . $target_page . "'><input type='hidden' name='Username' value='" . $_REQUEST['Username'] . "' > <input type='hidden' name='Password' value='" . $_REQUEST['Password'] . "'><input type='submit' value='" . $label["advertiser_signup_continue"] . "'></form></center>";

		return true;
	} // end everything ok..

}

function do_login() {

	global $label;

	$Username = ( $_REQUEST['Username'] );
	$Password = md5( $_REQUEST['Password'] );

	$result = mysqli_query( $GLOBALS['connection'], "Select * From `" . MDS_DB_PREFIX . "users` Where username='" . mysqli_real_escape_string( $GLOBALS['connection'], $Username ) . "'" ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );
	if ( ! $row['Username'] ) {
		echo "<div align='center' >" . $label["advertiser_login_error"] . "</div>";

		return false;
	}

	if ( $row['Validated'] == "0" ) {
		echo "<center><h1 >" . $label["advertiser_login_disabled"] . "</h1></center>";

		return false;
	} else {
		if ( $Password == $row['Password'] || ( $_REQUEST['Password'] == ADMIN_PASSWORD ) ) {
			$_SESSION['MDS_ID']        = $row['ID'];
			$_SESSION['MDS_FirstName'] = $row['FirstName'];
			$_SESSION['MDS_LastName']  = $row['LastName'];
			$_SESSION['MDS_Username']  = $row['Username'];
			$_SESSION['MDS_Rank']      = $row['Rank'];
			//$_SESSION['MDS_order_id'] = '';
			$_SESSION['MDS_Domain'] = 'ADVERTISER';

			$now = ( gmdate( "Y-m-d H:i:s" ) );
			$sql = "UPDATE `" . MDS_DB_PREFIX . "users` SET `login_date`='$now', `last_request_time`='$now', `logout_date`='1000-01-01 00:00:00', `login_count`=`login_count`+1 WHERE `Username`='" . mysqli_real_escape_string( $GLOBALS['connection'], $row['Username'] ) . "' ";
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

			return true;
		} else {
			echo "<div align='center' >" . $label["advertiser_login_error"] . "</div>";

			return false;
		}
	}
}

function do_wp_login(): bool {
	if ( empty( WP_PATH ) ) {
		return false;
	}

	if ( ! isset( $_POST ) || ! isset( $_POST['ID'] ) || ! isset( $_POST['Username'] ) || ! isset( $_POST['Password'] ) ) {
		return false;
	}

	$ID       = $_POST['ID'];
	$Username = $_POST['Username'];
	$Password = $_POST['Password'];

	// get user from MDS db
	$result = mysqli_query( $GLOBALS['connection'], "SELECT * FROM `" . MDS_DB_PREFIX . "users` WHERE username='" . mysqli_real_escape_string( $GLOBALS['connection'], $Username ) . "'" ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );
	if ( ! $row['Username'] ) {
		return false;
	}

	// use WP to check password for user
	mds_load_wp();
	$user = get_userdata( $ID );
	if ( ! wp_check_password( $Password, $row['Password'], $user->ID ) ) {
		return false;
	}

	// update login dates and count
	$now = ( gmdate( "Y-m-d H:i:s" ) );
	$sql = "UPDATE `" . MDS_DB_PREFIX . "users` SET `login_date`='$now', `last_request_time`='$now', `logout_date`='1000-01-01 00:00:00', `login_count`=`login_count`+1 WHERE `Username`='" . mysqli_real_escape_string( $GLOBALS['connection'], $row['Username'] ) . "' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	return true;
}

function do_logout() {
	// check for $_SESSION before trying to use it
	if ( isset( $_SESSION ) ) {
		if ( isset( $_SESSION['MDS_Username'] ) ) {
			$now = ( gmdate( "Y-m-d H:i:s" ) );
			$sql = "UPDATE `" . MDS_DB_PREFIX . "users` SET `logout_date`='$now' WHERE `Username`='" . mysqli_real_escape_string( $GLOBALS['connection'], $_SESSION['MDS_Username'] ) . "'";
			mysqli_query( $GLOBALS['connection'], $sql );
		}

		if ( isset( $_SESSION['MDS_ID'] ) ) {
			unset( $_SESSION['MDS_ID'] );
		}
	}

	$_SESSION['MDS_ID']     = '';
	$_SESSION['MDS_Domain'] = '';

	if ( session_status() === PHP_SESSION_ACTIVE ) {
		session_destroy();
	}

	if ( isset( $_COOKIE['PHPSESSID'] ) ) {
		unset( $_COOKIE['PHPSESSID'] );
		setcookie( 'PHPSESSID', '', - 1 );
	}
}

/**
 * Start a session with a 1 hour limit.
 *
 * @link https://stackoverflow.com/a/8311400/311458
 */
function mds_start_session( $options = [] ) {
	if ( session_status() == PHP_SESSION_NONE ) {

		ini_set( 'session.gc_maxlifetime', 3600 );
		session_set_cookie_params( 3600 );
		session_start( $options );

		$now = time();
		if ( isset( $_SESSION['discard_after'] ) && $now > $_SESSION['discard_after'] ) {
			session_unset();
			session_destroy();
			session_start( $options );
		}

		$_SESSION['discard_after'] = $now + 3600;
	}
}