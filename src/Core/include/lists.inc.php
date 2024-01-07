<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.9
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

use MillionDollarScript\Classes\Language;

defined( 'ABSPATH' ) or exit;

$column_list = array(); // column structure, global variable to cache the column info
$column_info = array();

/*
Display table heading, initalize column_struct.
*/
function echo_list_head_data( $form_id, $admin ) {

	global $q_string, $column_list, $column_info;

	$ord = isset( $_REQUEST['ord'] ) ? $_REQUEST['ord'] : 'asc';
	if ( $ord == 'asc' ) {
		$ord = 'desc';
	} else if ( $ord == 'desc' ) {
		$ord = 'asc';
	} else {
		$ord = 'desc';
	}

	$colspan = 0;

	$sql    = "SELECT * FROM " . MDS_DB_PREFIX . "form_lists where form_id='" . intval( $form_id ) . "' ORDER BY sort_order ASC ";
	$result = mysqli_query( $GLOBALS['connection'], $sql );
	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
		$colspan ++;
		$column_list[ $row['template_tag'] ]            = $row['template_tag'];
		$column_info[ $row['template_tag'] ]['trunc']   = $row['truncate_length'];
		$column_info[ $row['template_tag'] ]['admin']   = $row['admin'];
		$column_info[ $row['template_tag'] ]['link']    = $row['linked'];
		$column_info[ $row['template_tag'] ]['is_bold'] = $row['is_bold'];
		$column_info[ $row['template_tag'] ]['no_wrap'] = $row['no_wrap'];
		$column_info[ $row['template_tag'] ]['clean']   = $row['clean_format'];
		//$column_info[$row['template_tag']]['is_sortable'] = $row['is_sortable'];
		if ( ( $row['admin'] == 'Y' ) && ( ! $admin ) ) {
			continue; // do not render this column
		}
		?>
        <td class="list_header_cell" <?php if ( $row['template_tag'] == 'POST_SUMMARY' ) {
			echo ' width="100%" ';
		} ?>>
			<?php
			$page = defined( 'MDS_PAGE_NAME' ) ? MDS_PAGE_NAME : '';

			if ( $row['is_sortable'] == 'Y' ) { // show column order by link?
			?><a class="list_header_cell" href='<?php echo esc_url( $page ); ?>?order_by=&amp;ord=<?php echo $ord; ?><?php echo $q_string; ?>'>
				<?php
				}
				if ( $row['is_sortable'] == 'Y' ) { // show column order by link?
				?></a><?php
		}
		?>
        </td>
		<?php
	}
	?>
	<?php

	return $colspan;
}

function echo_ad_list_data( $admin ) {

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
		if ( ( $column_info[ $template_tag ]['admin'] == 'Y' ) && ( ! $admin ) ) {
			continue; // do not render this column
		}

		if ( $column_info[ $template_tag ]['trunc'] > 0 ) {
			$val = truncate_html_str( '', $column_info[ $template_tag ]['trunc'], $trunc_str_len );
		}

		// process the value depending on what kind of template tag it was given.
		if ( $template_tag == 'DATE' ) {

			// the last date modified
			$init_date = strtotime( trim_date( $val ) );

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

// Admin Stuff

function echo_list_head_data_admin( $form_id ) {
	global $column_list;

	$sql    = "SELECT * FROM " . MDS_DB_PREFIX . "form_lists where form_id='" . intval( $form_id ) . "' ORDER BY sort_order ASC ";
	$result = mysqli_query( $GLOBALS['connection'], $sql );
	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
		$column_list[ $row['field_id'] ] = $row['template_tag'];
		?>
        <td class="list_header_cell" nowrap>
			<?php echo '<small>(' . esc_html( $row['sort_order'] ) . ')</small>'; ?>
            <a href='<?php echo esc_url( admin_url( 'admin.php?page=mds-list2&mds-action=edit&column_id=' . intval( $row['column_id'] ) ) ); ?>'>
				<?php echo get_template_field_label( $row['template_tag'], $form_id ); ?></a>
            <a onclick="return confirmLink(this, 'Delete this column from view, are you sure?')"
               href="<?php echo esc_url( admin_url( 'admin.php?page=mds-list2&mds-action=del&column_id=' . intval( $row['column_id'] ) ) ); ?>">
                <img src='images/delete.gif' width="16" height="16" alt='Delete'></a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-list2&mds-action=edit&column_id=' . intval( $row['column_id'] ) ) ); ?>">
                <img src="images/edit.gif" width="16" height="16" alt="Edit">
        </td>
		<?php
	}
}
