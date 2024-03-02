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

$column_list = array(); // column structure, global variable to cache the column info
$column_info = array();

function echo_ad_list_data() {

	global $column_list, $column_info, $cur_offset, $order_str;

	if ( isset( $_REQUEST['order_by'] ) && $_REQUEST['order_by'] != '' ) {

		$ord = isset( $_REQUEST['ord'] ) ? $_REQUEST['ord'] : 'asc';
		if ( $ord == 'asc' ) {
			$ord = 'desc';
		} else if ( $ord == 'desc' ) {
			$ord = 'asc';
		} else {
			$ord = 'desc';
		}

		$order_str = "&order_by=" . $_REQUEST['order_by'] . "&ord=" . $ord;
	}

	foreach ( $column_list as $template_tag ) {

		//$val = $val.$template_tag;
		if ( ( $column_info[ $template_tag ]['admin'] == 'Y' ) ) {
			continue; // do not render this column
		}

		if ( $column_info[ $template_tag ]['trunc'] > 0 ) {
			$val = Utility::truncate_html_str( '', $column_info[ $template_tag ]['trunc'], $trunc_str_len );
		}

		// process the value depending on what kind of template tag it was given.
		if ( $template_tag == 'DATE' ) {

			// the last date modified
			$init_date = strtotime( Utility::trim_date( $val ) );

			// now
			$dst_date = current_time('timestamp');

			if ( ! $init_date ) {
				$days = "x";
			} else {
				$diff = $dst_date - $init_date;
				$days = floor( $diff / 60 / 60 / 24 );
			}
			//echo $days;
			$FORMATTED_DATE = get_date_from_gmt( $val );
			$val            = $FORMATTED_DATE . "<br>";

			if ( $days == 0 ) {
				$val = $val . '<span class="today">' . Language::get( 'Today!' ) . '</span>';
			} else if ( ( $days > 0 ) && ( $days < 2 ) ) {
				$val = $val . '<span class="days_ago">' . $days . " " . Language::get( 'Day ago' ) . "</span>";
			} else if ( ( $days > 1 ) && ( $days < 8 ) ) {
				$val = $val . '<span class="days_ago">' . $days . " " . Language::get( 'Days ago' ) . "</span>";
			} else if ( ( $days >= 8 ) ) {
				$val = $val . '<span class="days_ago2">' . $days . " " . Language::get( 'Days ago' ) . "</span>";
			}
		}

		if ( $column_info[ $template_tag ]['is_bold'] == 'Y' ) {
			$b1 = "<b>";
			$b2 = "</b>";
		} else {
			$b1 = '';
			$b2 = '';
		}

		if ( $column_info[ $template_tag ]['clean'] == 'Y' ) { // fix up punctuation spacing
			$val = preg_replace( '/ *(,|\.|\?|!|\/|\\\) */i', '$1 ', $val );
		}

		if ( $column_info[ $template_tag ]['link'] == 'Y' ) { // Render as a Link to the record?
			$val   = '<a href="?aid=&amp;offset=' . $cur_offset . $order_str . '"></a>';
		}
		?>
        <div <?php if ( $column_info[ $template_tag ]['no_wrap'] == 'Y' ) {
			echo ' nowrap ';
		} ?>>
			<?php echo $b1 . $val . $b2; ?>
        </div>
		<?php
	}
}
