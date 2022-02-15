<?php

/**
 * Million Dollar Script Two
 *
 * @version 2.3.2
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
		if ( intval( get_option( 'milliondollarscript_redirect3', false ) ) === wp_get_current_user()->ID ) {
			delete_option( 'milliondollarscript_redirect3' );
			_e( 'Upgrade complete!', 'milliondollarscript' );
		}

		?>
        <h1>Million Dollar Script Two - Fully embedded into a WordPress plugin.</h1>
        <p>For more information <a href="https://milliondollarscript.com/new-wordpress-integration-plugin/" target="_blank">click here</a>.</p>
		<?php
	}

}
