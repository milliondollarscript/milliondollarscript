<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.5
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

defined( 'ABSPATH' ) or exit;

require_once( '../include/ads.inc.php' );

$mode = isset( $_REQUEST['mode'] ) ? $_REQUEST['mode'] : 'view';

?>

<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000; "></div>
<b>[Ads List]</b><span style="background-color: <?php if ( $mode != 'edit' ) {
	echo "#F2F2F2";
} ?>; border-style:outset; padding: 5px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-form-fields&?mode=view')); ?>">View Form</a></span> <span style="background-color:  <?php if ( $mode == 'edit' && ( ! isset( $_REQUEST['NEW_FIELD'] ) || $_REQUEST['NEW_FIELD'] == '' ) ) {
	echo "#FFFFCC";
} ?>; border-style:outset; padding: 5px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-form-fields&mode=edit') ); ?>">Edit Fields</a></span> <span style="background-color: <?php if ( $mode == 'edit' && ( isset( $_REQUEST['NEW_FIELD'] ) && $_REQUEST['NEW_FIELD'] != '' ) ) {
	echo "#FFFFCC";
} else {
	echo "#F2F2F2";
} ?> ; border-style:outset; padding: 5px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-form-fields&NEW_FIELD=YES&mode=edit') ); ?>">New Field</a></span> &nbsp; &nbsp; <span style="background-color: #FFFFCC; border-style:outset; padding: 5px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-list2' ) ); ?>">Ads List</a></span>

<hr>

<?php
$col_row = [];
if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'del' ) {

	$sql    = "DELETE FROM " . MDS_DB_PREFIX . "form_lists WHERE column_id='" . intval( $_REQUEST['column_id'] ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql );
}

if ( isset( $_REQUEST['column_id'] ) && $_REQUEST['column_id'] != '' ) {
	$sql     = "SELECT * FROM " . MDS_DB_PREFIX . "form_lists WHERE column_id='" . intval( $_REQUEST['column_id'] ) . "' ";
	$result  = mysqli_query( $GLOBALS['connection'], $sql );
	$col_row = mysqli_fetch_array( $result );
}

if ( isset( $_REQUEST['save_col'] ) && $_REQUEST['save_col'] != '' ) {

	$error = '';
	if ( $_REQUEST['field_id'] == '' ) {
		$error = "Did not select a field ";
	}

	if ( ! is_numeric( $_REQUEST['sort_order'] ) ) {
		$error .= "'Sort order' must be a number. <br>";
	}

	if ( ! is_numeric( $_REQUEST['truncate_length'] ) ) {
		$error .= "'Truncate' must be a number. <br>";
	}

	if ( is_numeric( $_REQUEST['field_id'] ) ) {

		$sql       = "SELECT * from " . MDS_DB_PREFIX . "form_fields WHERE form_id=1 AND field_id='" . intval( $_REQUEST['field_id'] ) . "'  ";
		$result    = mysqli_query( $GLOBALS['connection'], $sql );
		$field_row = mysqli_fetch_array( $result );
	} else {

		$field_row['field_type'] = 'TEXT'; // default storage type.
		$field_row['field_id']   = $_REQUEST['field_id'];

		switch ( $_REQUEST['field_id'] ) {

			case 'ad_date':
				$field_row['template_tag'] = 'DATE';
				$field_row['field_type']   = 'TIME';
				break;
			case 'ad_id':
				$field_row['template_tag'] = 'AD_ID';
				break;
			case 'user_id':
				$field_row['template_tag'] = 'USER_ID';
				break;
			case 'order_id':
				$field_row['template_tag'] = 'ORDER_ID';
				break;
			case 'banner_id':
				$field_row['template_tag'] = 'BID';
				break;
		}
	}

	if ( $field_row['template_tag'] == '' ) { // need to fix the template tag!

		$field_row['template_tag'] = generate_template_tag( 1 );

		// update form field

		$sql = "UPDATE " . MDS_DB_PREFIX . "form_fields SET `template_tag`='" . mysqli_real_escape_string( $GLOBALS['connection'], $field_row['template_tag'] ) . "' WHERE form_id=1 AND field_id='" . intval( $_REQUEST['field_id'] ) . "'";
		mysqli_query( $GLOBALS['connection'], $sql );
	}

	if ( $_REQUEST['admin_only'] == '' ) {
		$_REQUEST['admin_only'] = 'N';
	}

	if ( $_REQUEST['linked'] == '' ) {
		$_REQUEST['linked'] = 'N';
	}

	$sql = "REPLACE INTO " . MDS_DB_PREFIX . "form_lists (`column_id`, `template_tag`, `field_id`, `sort_order`, `field_type`, `form_id`, `admin`, `truncate_length`, `linked`, `clean_format`, `is_bold`, `no_wrap`, `is_sortable`) VALUES ('" . intval( $_REQUEST['column_id'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $field_row['template_tag'] ) . "', '" . intval( $field_row['field_id'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['sort_order'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $field_row['field_type'] ) . "', '1', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['admin_only'] ) . "', '" . intval( $_REQUEST['truncate_length'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['linked'] ) . "',  '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['clean_format'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['is_bold'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['no_wrap'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['is_sortable'] ) . "')";

	if ( $error == '' ) {
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		echo "Column Updated.<br>";
	} else {
		echo "<span style=\"color: red; \">Cannot save due to the following errors:</span><br>";
		echo $error;
	}

	// load new values

	$sql     = "SELECT * FROM " . MDS_DB_PREFIX . "form_lists WHERE column_id='" . intval( $_REQUEST['column_id'] ) . "' ";
	$result  = mysqli_query( $GLOBALS['connection'], $sql );
	$col_row = mysqli_fetch_array( $result );
}

if ( isset( $col_row['column_id'] ) && $col_row['column_id'] != '' ) {
?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=list2' ) ); ?>">+ Add new column</a>
<?php
}

?>
<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission">
    <input type="hidden" name="mds_dest" value="list2">

    <input type="hidden" name="form_id" value="1">
    <input type="hidden" name="column_id" value="<?php echo $col_row['column_id'] ?? ''; ?>">
    <table border=1>
        <tr>
            <td colspan="2">
				<?php
				if ( isset( $col_row['column_id'] ) && $col_row['column_id'] == '' ) {
					?>
                    <b>Add a new column to the list</b>
					<?php
				} else {
					?>
                    <b>Edit column</b>

					<?php
				}

				?>
            </td>
        </tr>
        <tr>
            <td>Column</td>
            <td><select name="field_id" size=4>

					<?php

					field_type_option_list( 1, $col_row['field_id'] ?? 0 );

					?>
                </select></td>
        </tr>

		<?php

		if ( isset( $_REQUEST['column_id'] ) && $_REQUEST['column_id'] == '' ) { // get the last sort order

			$sql = "SELECT max(sort_order) FROM " . MDS_DB_PREFIX . "form_lists WHERE field_id=1 GROUP BY column_id ";
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
			$row        = mysqli_fetch_row( $result );
			$sort_order = $row[0];
		}

		?>

        <tr>
            <td>Order</td>
            <td><input type="text" name="sort_order" size="1" value="<?php echo $col_row['sort_order'] ?? 0; ?>">(1=first, 2=2nd, etc.)</td>
        </tr>
        <tr>
            <td>Linked?</td>
            <td><input <?php if ( isset( $col_row['linked'] ) && $col_row['linked'] != 'Y' ) {
					echo ' checked ';
				} ?> type="radio" name="linked" value='N'>No / <input <?php if ( ! isset( $col_row['linked'] ) || $col_row['linked'] == 'Y' ) {
					echo ' checked ';
				} ?> type="radio" name="linked" value='Y'> Yes - link to view full record

        </tr>
        <tr>
            <td>Admin Only?</td>
            <td><input <?php if ( isset( $col_row['admin'] ) && $col_row['admin'] != 'Y' ) {
					echo ' checked ';
				} ?> type="radio" name="admin_only" value='N'>No / <input <?php if ( ! isset( $col_row['admin'] ) || $col_row['admin'] == 'Y' ) {
					echo ' checked ';
				} ?> type="radio" name="admin_only" value='Y'> Yes

        </tr>
        <tr>
            <td>Clean format?</td>
            <td><input <?php if ( isset( $col_row['clean_format'] ) && $col_row['clean_format'] != 'Y' ) {
					echo ' checked ';
				} ?> type="radio" name="clean_format" value='N'>No / <input <?php if ( ! isset( $col_row['clean_format'] ) || $col_row['clean_format'] == 'Y' ) {
					echo ' checked ';
				} ?> type="radio" name="clean_format" value='Y'> Yes - Clean punctuation. Eg. if someone writes A,B,C the system will change to A, B, C

        </tr>
        <tr>
            <td>Is sortable?</td>
            <td><input <?php if ( isset( $col_row['is_sortable'] ) && $col_row['is_sortable'] != 'Y' ) {
					echo ' checked ';
				} ?> type="radio" name="is_sortable" value='N'>No / <input <?php if ( ! isset( $col_row['is_sortable'] ) || $col_row['is_sortable'] == 'Y' ) {
					echo ' checked ';
				} ?> type="radio" name="is_sortable" value='Y'> Yes - users can sort the records by this coulum, when clicked.

        </tr>
        <tr>
            <td>Is in Bold?</td>
            <td><input <?php if ( isset( $col_row['is_bold'] ) && $col_row['is_bold'] != 'Y' ) {
					echo ' checked ';
				} ?> type="radio" name="is_bold" value='N'>No / <input <?php if ( ! isset( $col_row['is_bold'] ) || $col_row['is_bold'] == 'Y' ) {
					echo ' checked ';
				} ?> type="radio" name="is_bold" value='Y'> Yes

        </tr>
        <tr>
            <td>No Wrap?</td>
            <td><input <?php if ( isset( $col_row['no_wrap'] ) && $col_row['no_wrap'] != 'Y' ) {
					echo ' checked ';
				} ?> type="radio" name="no_wrap" value='N'>No / <input <?php if ( ! isset( $col_row['no_wrap'] ) || $col_row['no_wrap'] == 'Y' ) {
					echo ' checked ';
				} ?> type="radio" name="no_wrap" value='Y'> Yes

        </tr>
        <tr>
            <td>Truncate (cut) to:</td>
            <td><input type="text" name="truncate_length" size="2" value='<?php if ( ! isset( $col_row['truncate_length'] ) || $col_row['truncate_length'] == '' ) {
					$col_row['truncate_length'] = '0';
				}
				echo $col_row['truncate_length']; ?>' size=''> characters. (0 = do not truncate)

        </tr>
        <tr>
            <td colspan="2"><input type="submit" name="save_col" value="Save"></td>
        </tr>

    </table>

</form>

<hr>
Here are the columns that will appear on the ad list:
<table border='0' width="99%" id='resumelist' cellspacing="1" cellpadding="5" align="center">
	<?php
	global $tag_to_field_id;
	//$tag_to_field_id = ad_tag_to_field_id_init();
	echo_list_head_data_admin( 1 );

	?>
</table>

