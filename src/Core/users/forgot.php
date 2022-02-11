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

$submit = $_REQUEST['submit'];
$email  = $_REQUEST['email'];
?>
<?php echo $f2->get_doc();

require_once BASE_PATH . "/html/header.php";

?>
    <div style='text-align:center;'>
        <h3><?php echo $label["advertiser_forgot_title"]; ?></h3>
        <form method="post">
            <label><?php echo $label["advertiser_forgot_enter_email"] ?>:
                <input type="text" name="email" size="30"/>
            </label>
            <input class="form_submit_button" type="submit" name="submit" value="<?php echo $label["advertiser_forgot_submit"]; ?>">

        </form>
    </div>
<?php

function make_password() {
	$pass = "";
	while ( strlen( $pass ) < 10 ) {
		$pass .= chr( rand( 97, 122 ) );
	}

	return $pass;
}

if ( $email != '' ) {

	$sql    = "select * from " . MDS_DB_PREFIX . "users where `Email`='" . mysqli_real_escape_string( $GLOBALS['connection'], $email ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error($sql) );
	$row    = mysqli_fetch_array( $result );

	if ( $row['Email'] != '' ) {

		if ( $row['Validated'] == '0' ) {
			$label["advertiser_forgot_error1"] = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $label["advertiser_forgot_error1"] );
			echo "<div style='text-align:center;'>" . $label["advertiser_forgot_error1"] . "</div>";
		} else {
			$pass    = make_password();
			$md5pass = md5( $pass );
			$sql     = "update `" . MDS_DB_PREFIX . "users` SET `Password`='$md5pass' where `ID`='" . mysqli_real_escape_string( $GLOBALS['connection'], $row['ID'] ) . "'";
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error($sql) );

			$to        = trim( $row['Email'] );
			$from      = trim( SITE_CONTACT_EMAIL );
			$form_name = trim( SITE_NAME );

			$subject = $label['advertiser_forgot_subject'];
			$subject = str_replace( "%SITE_NAME%", SITE_NAME, $subject );
			//$subject = str_replace( "%MEMBERID%", trim( $row['Username'] ), $subject );

			$message = $label["forget_pass_email_template"];
			$message = str_replace( "%FNAME%", $row['FirstName'], $message );
			$message = str_replace( "%LNAME%", $row['LastName'], $message );
			$message = str_replace( "%MEMBERID%", $row['Username'], $message );
			$message = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $message );
			$message = str_replace( "%SITE_NAME%", SITE_NAME, $message );
			if ( WP_ENABLED == "YES" && ! empty( WP_URL ) ) {
				$message = str_replace( "%SITE_URL%", WP_URL, $message );
			} else {
				$message = str_replace( "%SITE_URL%", BASE_HTTP_PATH, $message );
			}
			$message = str_replace( "%PASSWORD%", $pass, $message );

			$html_msg = $label["forget_pass_email_template_html"];
			$html_msg = str_replace( "%FNAME%", $row['FirstName'], $html_msg );
			$html_msg = str_replace( "%LNAME%", $row['LastName'], $html_msg );
			$html_msg = str_replace( "%MEMBERID%", $row['Username'], $html_msg );
			$html_msg = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $html_msg );
			$html_msg = str_replace( "%SITE_NAME%", SITE_NAME, $html_msg );
			if ( WP_ENABLED == "YES" && ! empty( WP_URL ) ) {
				$html_msg = str_replace( "%SITE_URL%", WP_URL, $html_msg );
			} else {
				$html_msg = str_replace( "%SITE_URL%", BASE_HTTP_PATH, $html_msg );
			}
			$html_msg = str_replace( "%PASSWORD%", $pass, $html_msg );

			if ( USE_SMTP == 'YES' ) {
				$mail_id = queue_mail( $to, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_msg, 6 );
				process_mail_queue( 2, $mail_id );
			} else {
				send_email( $to, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_msg, 6 );
			}

			$str = str_replace( "%BASE_HTTP_PATH%", BASE_HTTP_PATH, $label["advertiser_forgot_success1"] );

			echo "<p style='text-align:center;'>" . $str . "</p>";
		}
	} else {
		echo "<div style='text-align:center;'>" . $label["advertiser_forgot_email_notfound"] . "</div>";
	}
}

if ( WP_ENABLED == "yes" && ! empty( WP_URL ) ) {
	?>
    <h3 style='text-align:center;'><a href="<?php echo WP_URL; ?>" target="_top"><?php echo $label["advertiser_forgot_go_back"]; ?></a></h3>
	<?php
} else {
	?>
    <h3 style='text-align:center;'><a href="../"><?php echo $label["advertiser_forgot_go_back"]; ?></a></h3>
	<?php
}

require_once BASE_PATH . "/html/footer.php";
