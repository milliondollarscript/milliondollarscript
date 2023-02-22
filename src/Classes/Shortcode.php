<?php

/**
 * Million Dollar Script Two
 *
 * @version 2.3.6
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

class Shortcode {
	public static function defaults() {
		return array(
			'id'             => 1,
			'display_method' => 'ajax',
			'align'          => 'center',
			'width'          => '1000px',
			'height'         => 'auto',
			'mds'            => Config::get( 'BASE_HTTP_PATH' ),
			'lang'           => 'EN',
			'type'           => 'grid',
			'gateway'        => '',
			'display'        => ''
		);
	}

	/**
	 * Add milliondollarscript shortcode
	 *
	 * Example usage:
	 * Grid: [milliondollarscript id="1" display_method="ajax" align="center" width="1000px" height="1000px" lang="EN" type="grid" display="display_map.php?"]
	 * Users: [milliondollarscript id="1" display_method="iframe" align="center" width="100%" height="auto" lang="EN" type="users" display="users/?"]
	 * Users: [milliondollarscript id="1" display_method="ajax" align="center" width="100%" height="auto" lang="EN" type="users" display="users/?"]
	 * List: [milliondollarscript id="1" display_method="ajax" align="center" width="100%" height="auto" lang="EN" type="list" display="list.php?"]
	 * Stats: [milliondollarscript id="1" display_method="ajax" align="center" width="150px" height="60px" lang="EN" type="stats" display="display_stats.php?"]
	 * Thankyou: [milliondollarscript id="1" display_method="iframe" align="center" width="100%" height="auto" lang="EN" type="thankyou" gateway="PayPal" display="users/thanks.php?m=%PROVIDER%&"]
	 * Validate: [milliondollarscript id="1" display_method="iframe" align="center" width="100%" height="auto" lang="EN" type="validate" display="users/validate.php?email=%EMAIL%&code=%CODE%&"]
	 *
	 * @param $atts
	 *
	 * @return string
	 */
	public static function shortcode( $atts ) {
		global $mds_shortcodes;

		$mds_shortcodes ++;

		$atts = shortcode_atts(
			self::defaults(),
			$atts,
			'milliondollarscript'
		);

		if ( isset( $_REQUEST['lang'] ) && ! empty( $_REQUEST['lang'] ) ) {
			$lang = preg_replace( '/[^\w]/', '', $_REQUEST['lang'] );
		} else {
			$lang = preg_replace( '/[^\w]/', '', $atts['lang'] );
		}

		$display   = "display_map.php?";
		$classname = "gridframe";

		if ( $atts['type'] == "grid" ) {
			if ( ! empty( $atts['display'] ) ) {
				$display = $atts['display'];
			} else {
				$display = "display_map.php?";
			}
			$classname = "gridframe";
		} else if ( $atts['type'] == "validate" ) {
			if ( ! empty( $atts['display'] ) ) {
				$display = str_replace( [ '%EMAIL%', '%CODE%' ], [ urlencode( $_REQUEST['email'] ), urlencode( $_REQUEST['code'] ) ], $atts['display'] );
			} else {
				$display = "users/validate.php?email=" . urlencode( $_REQUEST['email'] ) . "&code=" . urlencode( $_REQUEST['code'] ) . "&";
			}
			$classname = "validateframe";
		} else if ( $atts['type'] == "thankyou" ) {
			$provider = preg_replace( '/[^\w]/', '', $atts['gateway'] );
			if ( ! empty( $atts['display'] ) ) {
				$display = str_replace( '%PROVIDER%', $provider, $atts['display'] );
			} else {
				$display = "users/thanks.php?m=" . $provider . "&";
			}
			$classname = "thankyouframe";
		} else if ( $atts['type'] == "stats" ) {
			if ( ! empty( $atts['display'] ) ) {
				$display = $atts['display'];
			} else {
				$display = "display_stats.php?";
			}
			$classname = "statsframe";
		} else if ( $atts['type'] == "list" ) {
			if ( ! empty( $atts['display'] ) ) {
				$display = $atts['display'];
			} else {
				$display = "list.php?";
			}
			$classname = "adslistframe";
		} else if ( $atts['type'] == "users" ) {
			if ( ! empty( $atts['display'] ) ) {
				$display = $atts['display'];
			} else {
				$display = "users/?";
			}
			$classname = "usersframe";
		}

		$frame_id = $classname . $mds_shortcodes;

		$align = "";
		if ( $atts['align'] == "left" ) {
			$align = "float:left;";
		} else if ( $atts['align'] == "center" ) {
			$align = "display:block;margin:0px auto;";
		} else if ( $atts['align'] == "right" ) {
			$align = "float:right;";
		}

		// valid CSS units
		$css_units = array(
			'cm',
			'mm',
			'Q',
			'in',
			'pc',
			'pt',
			'px',
			'em',
			'ex',
			'ch',
			'rem',
			'lh',
			'vw',
			'vh',
			'vmin',
			'vmax',
			'%',
		);

		// validate width
		if ( $atts['width'] == 'auto' ) {
			$w = esc_attr( $atts['width'] );
		} else {
			$width_str = preg_replace( "/[0-9]/", "", $atts['width'] );
			if ( in_array( $width_str, $css_units ) ) {
				$w = esc_attr( $atts['width'] );
			} else {
				$w = "1000px";
			}
		}

		// set width variables
		$widthcss = 'width:' . $w . ';';
		$widthatt = 'width="' . $w . '"';

		// validate height
		if ( $atts['height'] == 'auto' ) {
			$h = 'auto';
		} else {
			$height_str = preg_replace( "/[0-9]/", "", $atts['height'] );
			if ( in_array( $height_str, $css_units ) ) {
				$h = esc_attr( $atts['height'] );
			} else {
				$h = "1000px";
			}
		}

		// set height variables
		$heightcss = 'height:' . $h . ';';
		$heightatt = 'height="' . $h . '"';

		// compile variables to pass to javascript
		$mds_frame = array(
			'mds_display_method' => $atts['display_method'],
			'mds_type'           => $atts['type'],
			'mds_frame_id'       => $frame_id,
			'mds_width'          => $atts['width'],
			'mds_height'         => $atts['height'],
			'mds_origin_url'     => $atts['mds'],
		);

		// escape javascript variables
		if ( array_walk( $mds_frame, 'esc_js' ) ) {
			if ( $atts['display_method'] == "ajax" ) {
				// ajax display method
				$js = "
				jQuery(function () {
					new window.mds_ajax('{$mds_frame['mds_type']}', '{$mds_frame['mds_frame_id']}', '{$atts['align']}', '{$mds_frame['mds_width']}', '{$mds_frame['mds_height']}', '{$mds_frame['mds_origin_url']}', {$atts['id']});
				});
				";
			} else {
				// iframe display method
				$js = "
				jQuery(function () {
					new window.mds_iframe('{$mds_frame['mds_type']}', '{$mds_frame['mds_frame_id']}', '{$mds_frame['mds_width']}', '{$mds_frame['mds_height']}', '{$mds_frame['mds_origin_url']}');
				});
				";
			}

			wp_register_script( 'mds', MDS_BASE_URL . 'src/Assets/js/mds.js', [ 'jquery' ], filemtime( MDS_BASE_PATH . 'src/Assets/js/mds.js' ), true );

			// add inline function call to each MDS iframe
			wp_add_inline_script( 'mds', $js );
		}

		if ( $atts['display_method'] == 'ajax' ) {
			return '<div id="' . esc_attr( $frame_id ) . '"></div>';
		} else if ( $atts['display_method'] == 'iframe' ) {

			return '
		<iframe id="' . esc_attr( $frame_id ) . '" class="' . esc_attr( $classname ) . '" style="' . esc_attr( $align . $widthcss . $heightcss ) . '" ' . $widthatt . ' ' . $heightatt . ' src="' . esc_url( trailingslashit( $atts['mds'] ) . $display . 'BID=' . intval( $atts['id'] ) . '&lang=' . $lang . '&iframe_call=true' ) . '"></iframe>
	';
		}

		return '';
	}
}
