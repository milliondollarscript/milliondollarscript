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

if ( ! is_logged_in() ) {
	do_logout();
	mds_start_session();
}

require_once BASE_PATH . "/html/header.php";

$target_page = $_REQUEST['target_page'];

if ( $target_page == '' ) {
	$target_page = 'select.php';
} else if ( $target_page != "index.php" && $target_page != "confirm_order.php" ) {
	$target_page = "index.php";
}

if ( WP_ENABLED == 'NO' || ( WP_ENABLED == 'YES' && WP_USERS_ENABLED == 'NO' ) ) {

	?>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td width="35" height="26">&nbsp;</td>
            <td height="26" valign="bottom">
                <center><img alt="" src="<?php echo htmlentities( stripslashes( SITE_LOGO_URL ) ); ?>"/> <br/>
                    <h3><?php
						$label["advertiser_logging_in"] = str_replace( "%SITE_NAME%", SITE_NAME, $label["advertiser_logging_in"] );
						echo $label["advertiser_logging_in"]; ?> </h3></center>
            </td>
        </tr>
        <tr>
            <td width="35">&nbsp;</td>
            <td><span>
			<?php
			if ( do_login() ) {
				echo "<script>window.location = '$target_page'</script>";
				$ok = str_replace( "%username%", $_SESSION['MDS_Username'], $label['advertiser_login_success2'] );
				$ok = str_replace( "%firstname%", $_SESSION['MDS_FirstName'], $ok );
				$ok = str_replace( "%lastname%", $_SESSION['MDS_LastName'], $ok );
				$ok = str_replace( "%target_page%", $target_page, $ok );
				echo "<div align='center' >" . $ok . "</div>";
			}
			?>
		</span></td>
            <td width="35">&nbsp;</td>
        </tr>
        <tr>
            <td width="35" height="26">&nbsp;</td>
            <td height="26"></td>
            <td width="35" height="26">&nbsp;</td>
        </tr>
    </table>
	<?php
}

require_once BASE_PATH . "/html/footer.php";
