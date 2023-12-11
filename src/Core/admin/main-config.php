<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.7
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

defined( 'ABSPATH' ) or exit;

if ( isset( $_REQUEST['save'] ) && $_REQUEST['save'] != '' ) {
	global $f2, $wpdb;

	$mds_config_defaults = Config::defaults();

	foreach ( $mds_config_defaults as $key => $data ) {
		if ( $data['type'] == 'd' ) {
			if ( isset( $_REQUEST[ $key ] ) ) {
				$value = intval( $_REQUEST[ $key ] );
			} else {
				$value = intval( $data['value'] );
			}
		} else {
			if ( isset( $_REQUEST[ $key ] ) ) {
				$value = sanitize_text_field( $_REQUEST[ $key ] );
			} else {
				$value = sanitize_text_field( $data['value'] );
			}
		}

		$wpdb->update(
			MDS_DB_PREFIX . "config",
			[
				'val' => $value,
			],
			[
				'config_key' => $key,
			],
			[
				'%' . $data['type'],
			],
			[ '%s' ]
		);
	}

	return;
}

if ( isset( $_REQUEST['mds-config-updated'] ) && $_REQUEST['mds-config-updated'] == 'true' ) {
	echo "Config updated!";
}

Language::out_replace( '<h3>Main Configuration</h3>
<p>Options on this page affect the running of the pixel advertising system.</p>
<p><b>Tip:</b> Looking for where to settings for the grid? It is set in "Pixel Inventory" -> <a href="%MANAGE_GRIDS_URL%">Manage Grids</a>. Click on Edit to edit the grid parameters.</p>', '%MANAGE_GRIDS_URL%', esc_url( admin_url( 'admin.php?page=mds-manage-grids' ) ) );

require( __DIR__ . '/config_form.php' );
