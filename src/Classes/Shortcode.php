<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.0
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

namespace MillionDollarScript\Classes;

defined( 'ABSPATH' ) or exit;

class Shortcode {
	public static function defaults(): array {
		return array(
			'id'     => 1,
			//'display_method' => 'ajax',
			'align'  => 'center',
			'width'  => '1000px',
			'height' => 'auto',
			// 'mds'     => Config::get( 'MDS_CORE_URL' ),
			'lang'   => 'EN',
			'type'   => 'grid',
			// 'gateway' => '',
			// 'display'        => ''
		);
	}

	public static function get_classname( $type ): string {
		$classname = "gridframe";

		if ( $type == "stats" ) {
			$classname = "statsframe";
		} else if ( $type == "list" ) {
			$classname = "listframe";
		} else if ( $type == "users" ) {
			$classname = "usersframe";
		}

		return $classname;
	}

	/**
	 * Add milliondollarscript shortcode
	 *
	 * Example usage:
	 * Grid: [milliondollarscript id="1" align="center" width="1000px" height="1000px" type="grid"]
	 * Users: [milliondollarscript id="1" align="center" width="100%" height="auto" type="users"]
	 * List: [milliondollarscript id="1" align="center" width="100%" height="auto" type="list"]
	 * Stats: [milliondollarscript id="1" align="center" width="150px" height="60px" type="stats"]
	 *
	 * @param $atts
	 *
	 * @return string
	 */
	public static function shortcode( $atts ): string {
		global $mds_shortcodes;

		$mds_shortcodes ++;

		$atts = shortcode_atts(
			self::defaults(),
			$atts,
			'milliondollarscript'
		);

		$classname = self::get_classname( $atts['type'] );

		$container_id = $classname . $mds_shortcodes;

		// compile variables to pass to javascript
		$mds_params = array(
			'mds_type'         => $atts['type'],
			'mds_container_id' => $container_id,
			'mds_width'        => $atts['width'],
			'mds_height'       => $atts['height'],
		);

		// escape javascript variables
		if ( array_walk( $mds_params, 'esc_js' ) ) {
			// ajax display method
			$js = "
				jQuery(function () {
					new window.mds_ajax('{$mds_params['mds_type']}', '{$mds_params['mds_container_id']}', '{$atts['align']}', '{$mds_params['mds_width']}', '{$mds_params['mds_height']}', {$atts['id']});
				});
				";

			// load scripts
			Functions::register_scripts();
			Functions::enqueue_scripts();

			// add inline function call to each MDS iframe
			wp_add_inline_script( 'mds', $js );
		}

		return '<div id="' . esc_attr( $container_id ) . '"></div>';
	}
}
