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

class Debug {
	public static function output( $what, $where = 'screen', $label = '', $encapsulate = true, $type = 'var_dump', $user = 1 ) {
		if ( get_current_user_id() !== $user ) {
			return;
		}

		$output = "";

		if ( $type == 'var_dump' ) {
			ob_start();
			var_dump( $what );
			$output = ob_get_contents();
			ob_end_clean();
		} else if ( $type == 'print_r' ) {
			$output = print_r( $what, true );
		} else if ( $type == 'raw' ) {
			$output = $what;
		}

		if ( ! empty( $label ) ) {
			$output = $label . ": " . $output;
		}

		if ( $encapsulate ) {
			$output = '<pre>' . $output . '</pre>';
		}

		if ( $where == 'screen' ) {
			echo $output;
		} else if ( $where == 'log' ) {
			error_log( $output );
		}
	}
}
