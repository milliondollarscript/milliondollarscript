<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.5
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
use MillionDollarScript\Classes\Language;
use MillionDollarScript\Classes\Utility;

defined( 'ABSPATH' ) or exit;

// MillionDollarScript header.php

$logged_in = is_user_logged_in();

// error_log( var_export( debug_backtrace(), true ) );
?>
<div class="mds-container<?php echo $logged_in ? ' logged-in' : ''; ?>">
    <div class="outer">
        <div class="inner">
			<?php
			if ( $logged_in ) {
				?>
                <div class="users-menu-bar">
                    <div class="mds-menu-hamburger" onclick="mdsToggleMenu()">☰</div>
                    <ul class="mds-users-menu" id="mds-users-menu">
						<?php
						// check if user has permission to access this page
						if ( mds_check_permission( "mds_my_account" ) ) {
							?>
                            <li>
                                <a href="<?php echo \esc_url( Utility::get_page_url( 'account' ) ); ?>"><?php Language::out( 'My Account' ); ?></a>
                            </li>
							<?php
						}
						?>
						<?php
						// check if user has permission to access this page
						if ( mds_check_permission( "mds_order_pixels" ) ) {
							?>
                            <li>
                                <a href="<?php echo \esc_url( Utility::get_page_url( 'order' ) ); ?>"><?php Language::out( 'Order Pixels' ); ?></a>
                            </li>
							<?php
						}
						?>
						<?php
						// check if user has permission to access this page
						if ( mds_check_permission( "mds_manage_pixels" ) ) {
							?>
                            <li>
                                <a href="<?php echo \esc_url( Utility::get_page_url( 'manage' ) ); ?>"><?php Language::out( 'Manage Pixels' ); ?></a>
                            </li>
							<?php
						}
						?>
						<?php
						// check if user has permission to access this page
						if ( mds_check_permission( "mds_order_history" ) ) {

							// DISPLAY_ORDER_HISTORY
							$order_history_link = "";
							if ( Config::get( 'DISPLAY_ORDER_HISTORY' ) == "YES" ) {
								?>
                                <li>
                                    <a href="<?php echo \esc_url( Utility::get_page_url( 'history' ) ); ?>"><?php Language::out( 'Order History' ); ?></a>
                                </li>
								<?php
							}
						}
						?>
						<?php
						// check if user has permission to access this page
						if ( mds_check_permission( "mds_logout" ) ) {
							?>
                            <li>
                                <a target="_top" href="<?php echo \esc_url( wp_logout_url() ); ?>"><?php Language::out( 'Logout' ); ?></a>
                            </li>
							<?php
						}
						?>
                    </ul>
                </div>

				<?php
			}
			?>
