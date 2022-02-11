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

do_logout();

require_once BASE_PATH . "/html/header.php";

global $label;
?>

    <div class="logout-container">
		<?php
		if ( WP_ENABLED == "YES" && ! empty( WP_URL ) ) {
		?>
        <h3><?php echo $label['advertiser_logout_ok']; ?></h3> <a target="_top" href="<?php echo urlencode( WP_URL ); ?>">
			<?php
		} else {
			?>
            <img alt="" src="<?php echo htmlentities( stripslashes( SITE_LOGO_URL ) ); ?>"/> <br/>
            <h3><?php echo $label['advertiser_logout_ok']; ?></h3> <a href="../"><?php
		}

		$label["advertiser_logout_home"] = str_replace( "%SITE_NAME%", SITE_NAME, $label["advertiser_logout_home"] );
		echo $label['advertiser_logout_home']; ?></a>
    </div>
<?php
require_once BASE_PATH . "/html/footer.php";
