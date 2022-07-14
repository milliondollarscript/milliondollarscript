<?php

/**
 * Million Dollar Script Two
 *
 * @version 2.3.4
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

class Admin {
	public static function menu() {
		$handle = \add_submenu_page( 'MillionDollarScript', 'Million Dollar Script Admin', 'Admin', 'manage_options', 'MillionDollarScript_Admin', array( __CLASS__, 'html' ), 2 );

		// Add styles for admin page
		add_action( 'admin_print_styles-' . $handle, array( __CLASS__, 'styles' ) );
	}

	public static function styles() {
		wp_register_style( 'MillionDollarScriptStyles', MDS_BASE_URL . 'src/Assets/css/styles.css' );
	}

	public static function html() {
		$mds_admin_url = MDS_CORE_URL . 'admin/';

		?>
        <iframe id="mds-admin-frame" class="mds-admin-frame" style="display: block; margin: 0 auto; width: 100%; height: 5000px;" width="100%" height="auto" src="<?php echo esc_url( $mds_admin_url ); ?>" data-origwidth="100%" data-origheight="auto"></iframe>
		<?php

	}

}
