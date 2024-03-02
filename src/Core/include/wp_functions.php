<?php
/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2024, Ryan Rhode
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

use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

function mds_load_wp() {
	require_once ABSPATH . '/wp-load.php';
	require_once ABSPATH . '/wp-includes/pluggable.php';
}

function mds_wp_login_check(): void {
	// Redirect to WP login page if not logged in

	mds_load_wp();

	$doredirect = false;

	if ( ! is_user_logged_in() ) {

		// Check if MDS WP plugin Options class exists
		if ( class_exists( '\MillionDollarScript\Classes\Data\Options' ) ) {

			// Get the login page options
			$login_page    = \MillionDollarScript\Classes\Data\Options::get_option( 'login-page' );
			$login_target  = \MillionDollarScript\Classes\Data\Options::get_option( 'login-target' );
			$register_page = \MillionDollarScript\Classes\Data\Options::get_option( 'register-page' );

			$loginpage_host = parse_url( $login_page, PHP_URL_HOST );
			$siteurl_host   = parse_url( get_site_url(), PHP_URL_HOST );

			// If on the same domain or it doesn't contain the default wp-login.php and is a valid URL
			if ( ( $loginpage_host == $siteurl_host || ! str_contains( $login_page, 'wp-login.php' ) ) && wp_http_validate_url( $login_page ) ) {

				// If not in preview or customizer
				if ( ! is_preview() && ! is_customize_preview() ) {

					// escape the URL
					$loginhref = esc_url( $login_page );

					$login_redirect_url  = \MillionDollarScript\Classes\Data\Options::get_option( 'login-redirect' );
					$woocommerce_enabled = \MillionDollarScript\Classes\Data\Options::get_option( 'woocommerce' );

					if ( $woocommerce_enabled ) {
						$loginhref .= ( str_contains( $loginhref, '?' ) ? '&' : '?' ) . 'redirect_to=' . esc_url_raw( $login_redirect_url );
					}

					$loginhref .= ( str_contains( $loginhref, '?' ) ? '&' : '?' ) . 'redirect=' . esc_url_raw( $login_redirect_url );

					if ( $login_target == 'current' ) {
						?>
                        <div class="mds-login-form">
							<?php
							// Login form
							wp_login_form( [
								'echo'     => true,
								'redirect' => $login_redirect_url,
							] );

							// Register button
							if ( get_option( 'users_can_register' ) ) {
								?>
                                <div class="mds-register-btn-container" style="display:none;"><a href="<?php echo esc_url( $register_page ); ?>" class="mds-register-link"><?php Language::out( 'Register' ); ?></a></div>
								<?php
							}
							?>
                        </div>
                        <script>
	                        jQuery(document).ready(function($){
								const registerBtn = $('.mds-register-btn-container');
								if (registerBtn.length) {
									const submitBtn = $('#loginform .button[name="wp-submit"]');
									const containerWidth = submitBtn.parent().width();
									const targetWidth = containerWidth * 0.50 - 5;
									submitBtn.animate({width: targetWidth}, 500);
									setTimeout(function() {
										registerBtn.insertAfter(submitBtn);
										registerBtn.css({
											width: '0',
											display: 'inline-block',
											verticalAlign: 'top',
											overflow: 'hidden'
										});
										registerBtn.animate({width: targetWidth}, 500, function() {
											registerBtn.css('overflow', 'visible');
										});
									}, 200);
								}
							});
                        </script>
						<?php
						exit;
					} else {
						// do a javascript redirect in the parent frame if it exists, otherwise just redirect in the current window
						echo '
					<script>
					if (parent.frames.length > 0) {
						parent.location.href = "' . $loginhref . '";
					} else {
						window.location.href = "' . $loginhref . '";
					}
					</script>
					';
						exit;
					}
				}
			} else {
				// if the url isn't valid redirect to the default login
				$doredirect = true;
			}
		} else {
			// if the class doesn't exist then the plugin may not be installed/activated so redirect to the default login
			$doredirect = true;
		}

		// redirect to the default login and then back to the users page
		if ( $doredirect ) {
			wp_redirect( wp_login_url( Utility::get_page_url( 'manage' ) ) );
			exit;
		}
	}
}