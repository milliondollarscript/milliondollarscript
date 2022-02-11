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
mds_start_session( [
	'name' => 'MDSADMIN_PHPSESSID',
] );

// WP integration
if ( defined( 'WP_ENABLED' ) && WP_ENABLED == 'YES' && WP_ADMIN_ENABLED == 'YES' ) {
	mds_load_wp();

	if ( current_user_can( 'manage_options' ) ) {
		if ( ! defined( 'MAIN_PHP' ) ) {
			define( 'MAIN_PHP', '1' );
		}
		$_SESSION['ADMIN'] = '1';
	} else {
		wp_redirect( wp_login_url() );
		die();
	}
} else {

	if ( ( isset( $_REQUEST['pass'] ) && $_REQUEST['pass'] != '' ) && ( defined( "MAIN_PHP" ) && MAIN_PHP == '1' ) ) {
		if ( stripslashes( $_REQUEST['pass'] ) == ADMIN_PASSWORD ) {
			$_SESSION['ADMIN'] = '1';
		}
	}
	if ( ! isset( $_SESSION['ADMIN'] ) || empty( $_SESSION['ADMIN'] ) ) {
		if ( defined( "MAIN_PHP" ) && MAIN_PHP == '1' ) {
			?>
            Please input admin password:<br>
            <form method='post' action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <input type="password" name='pass'>
                <input type="submit" value="OK">
            </form>
			<?php
		} else {
			echo '<script type="text/javascript">top.location.href = "index.php";</script>';
		}
		die();
	}
}
