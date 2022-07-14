<?php

/**
 * Million Dollar Script Two
 *
 * @version 2.3.5
 * @author Ryan Rhode
 * @copyright (C) 2022, Ryan Rhode
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
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
 */

namespace MillionDollarScript\Classes;

defined( 'ABSPATH' ) or exit;

class MillionDollarScript {
	public static function menu() {
		$handle = \add_menu_page( 'MillionDollarScript', 'Million Dollar Script', 'manage_options', 'MillionDollarScript', array( __CLASS__, 'html' ), 'dashicons-grid-view' );

		// Add styles for admin page
		add_action( 'admin_print_styles-' . $handle, array( __CLASS__, 'styles' ) );
	}

	public static function styles() {
		wp_register_style( 'MillionDollarScriptStyles', MDS_BASE_URL . 'src/Assets/css/styles.css' );
	}

	public static function html() {
		?>
        <h1><?php _e( 'Million Dollar Script Two - Fully embedded into a WordPress plugin.', 'milliondollarscript' ); ?></h1>
		<?php
		if ( intval( get_option( 'milliondollarscript_redirect3', false ) ) === wp_get_current_user()->ID ) {
			delete_option( 'milliondollarscript_redirect3' );
			delete_option( 'milliondollarscript-two-installing' );
			add_option( 'milliondollarscript-two-installed', true );
			?>
            <p>
				<?php _e( 'Upgrade complete!', 'milliondollarscript' ); ?>
            </p>
			<?php
		}
		?>
        <p><?php _e( 'For more information', 'milliondollarscript' ); ?> <a href="https://milliondollarscript.com/new-wordpress-integration-plugin/" target="_blank"><?php _e( 'click here', 'milliondollarscript' ); ?></a>.</p>
        <p><?php _e( 'Current status: ', 'milliondollarscript' ); ?><strong><?php
				$installed  = get_option( 'milliondollarscript-two-installed', false );
				$installing = get_option( 'milliondollarscript-two-installing', false );
				if ( $installed && ! $installing ) {
					_e( 'Installed', 'milliondollarscript' );
				} else if ( ! $installed && $installing ) {
					_e( 'Installing', 'milliondollarscript' );
				} else if ( $installed && $installing ) {
					_e( 'Installed and installing', 'milliondollarscript' );
				} else if ( ! $installed && ! $installing ) {
					_e( 'Not installed', 'milliondollarscript' );
				}
				?></strong></p>
		<?php
	}

}
