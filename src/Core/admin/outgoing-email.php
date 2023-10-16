<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.3
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

use MillionDollarScript\Classes\Config;defined( 'ABSPATH' ) or exit;

ini_set( 'max_execution_time', 500 );

if ( isset( $_REQUEST['mds-action'] ) ) {
	if ( $_REQUEST['mds-action'] == 'delall' ) {

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "mail_queue ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		while ( $row = mysqli_fetch_array( $result ) ) {

			if ( $row['att1_name'] != '' ) {
				unlink( $row['att1_name'] );
			}

			if ( $row['att2_name'] != '' ) {
				unlink( $row['att2_name'] );
			}

			if ( $row['att3_name'] != '' ) {
				unlink( $row['att3_name'] );
			}

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "mail_queue where mail_id='" . intval( $row['mail_id'] ) . "' ";
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		}
	}
	if ( $_REQUEST['mds-action'] == 'delsent' ) {
	$sql = "SELECT * from " . MDS_DB_PREFIX . "mail_queue where `status`='sent' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		while ( $row = mysqli_fetch_array( $result ) ) {

			if ( $row['att1_name'] != '' ) {
				unlink( $row['att1_name'] );
			}

			if ( $row['att2_name'] != '' ) {
				unlink( $row['att2_name'] );
			}

			if ( $row['att3_name'] != '' ) {
				unlink( $row['att3_name'] );
			}

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "mail_queue where mail_id='" . intval( $row['mail_id'] ) . "' ";
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		}
	}
	if ( $_REQUEST['mds-action'] == 'delerror' ) {
	$sql = "SELECT * from " . MDS_DB_PREFIX . "mail_queue where `status`='error' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		while ( $row = mysqli_fetch_array( $result ) ) {

			if ( $row['att1_name'] != '' ) {
				unlink( $row['att1_name'] );
			}

			if ( $row['att2_name'] != '' ) {
				unlink( $row['att2_name'] );
			}

			if ( $row['att3_name'] != '' ) {
				unlink( $row['att3_name'] );
			}

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "mail_queue where mail_id='" . intval( $row['mail_id'] ) . "' ";
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		}
	}
	if ( $_REQUEST['mds-action'] == 'resend' ) {

	$sql = "UPDATE " . MDS_DB_PREFIX . "mail_queue SET status='queued' WHERE mail_id=" . intval( $_REQUEST['mail_id'] );
		mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );

		process_mail_queue( 1, $_REQUEST['mail_id'] );
	}
}

$EMAILS_PER_BATCH = Config::get('EMAILS_PER_BATCH');
if ( $EMAILS_PER_BATCH == '' ) {
	$EMAILS_PER_BATCH = 10;
}

if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'send' ) {
	process_mail_queue( $EMAILS_PER_BATCH );
}

if ( isset( $_POST['mds_dest'] ) ) {
    return;
}

$q_username = isset($_REQUEST['q_username']) && is_string($_REQUEST['q_username']) ? $_REQUEST['q_username'] : '';

$q_to_add = isset($_REQUEST['q_to_add']) && is_string($_REQUEST['q_to_add']) ? $_REQUEST['q_to_add'] : '';
$q_to_name = isset($_REQUEST['q_to_name']) && is_string($_REQUEST['q_to_name']) ? $_REQUEST['q_to_name'] : '';
$q_subj = isset($_REQUEST['q_subj']) && is_string($_REQUEST['q_subj']) ? $_REQUEST['q_subj'] : '';
$q_msg = isset($_REQUEST['q_msg']) && is_string($_REQUEST['q_msg']) ? $_REQUEST['q_msg'] : '';
$q_status = isset($_REQUEST['q_status']) && is_string($_REQUEST['q_status']) ? $_REQUEST['q_status'] : '';
$q_type     = isset($_REQUEST['q_type']) ? intval($_REQUEST['q_type']) : 0;
$search     = isset($_REQUEST['search']) && is_string($_REQUEST['search']) ? $_REQUEST['search'] : '';
$q_string  = "&q_to_add=$q_to_add&q_subj=$q_subj&q_to_name=$q_to_name&q_msg=$q_msg&q_status=$q_status&q_type=$q_type&search=$search";

$sql    = "select count(*) as c from " . MDS_DB_PREFIX . "mail_queue  ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
$row   = mysqli_fetch_array( $result );
$total = $row['c'];

$sql    = "select count(*) as c from " . MDS_DB_PREFIX . "mail_queue where status='queued'  ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
$row    = mysqli_fetch_array( $result );
$queued = $row['c'];

$sql    = "select count(*) as c from " . MDS_DB_PREFIX . "mail_queue where status='sent'  ";
$result = mysqli_query( $GLOBALS['connection'], $sql );
$row    = mysqli_fetch_array( $result );
$sent   = $row['c'];

$sql    = "select count(*) as c from " . MDS_DB_PREFIX . "mail_queue where status='error'  ";
$result = mysqli_query( $GLOBALS['connection'], $sql );
$row    = mysqli_fetch_array( $result );
$error  = $row['c'];

?>
    <b><?php echo $total; ?></b> Total Email(s) |
    <b><?php echo $queued; ?></b> Email(s) on Queue |
    <b><?php echo $sent; ?></b> Email(s) Sent |
    <b><?php echo $error; ?></b> Email(s) Failed<br>
    <input type='button' value="Delete Sent" onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=mds-outgoing-email&mds-action=delsent' . $q_string ) ); ?>'">
    | <input type='button' value="Delete Error" onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=mds-outgoing-email&mds-action=delerror' . $q_string ) ); ?>'">
    | <input type='button' value="Delete All" onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=mds-outgoing-email&mds-action=delall' . $q_string ) ); ?>'">

    <br>

    <hr>
    <form style="margin: 0" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
        <?php wp_nonce_field( 'mds-admin' ); ?>
        <input type="hidden" name="action" value="mds_admin_form_submission" />
        <input type="hidden" name="mds_dest" value="outgoing-email" />
        <input type="hidden" name="search" value="Y" />

    <center>
    <table border="0" cellpadding="2" cellspacing="0" style="border-collapse: collapse" id="AutoNumber2" width="100%">

        <tr>
            <td width="63" bgcolor="#EDF8FC" valign="top">
                <p align="right"><span  style="font-family: Arial, sans-serif; "><b>To Addr</b></td>
            <td width="286" bgcolor="#EDF8FC" valign="top">
                <span style="font-family: Arial, sans-serif; ">
                    <label>
<input type="text" name="q_to_add" size="39" value="<?php echo $q_to_add; ?>"/>
</label></td>
            <td width="71" bgcolor="#EDF8FC" valign="top">
                <p align="right"><b><span  style="font-family: Arial, sans-serif; ">To Name</b></td>
            <td width="299" bgcolor="#EDF8FC" valign="top">

                <label>
<input type="text" name="q_to_name" size="28" value="<?php echo $q_to_name; ?>"/>
</label></td>
        </tr>
        <tr>
            <td width="63" bgcolor="#EDF8FC" valign="top">
                <p align="right"><span  style="font-family: Arial, sans-serif; "><b>Subject</b></td>
            <td width="286" bgcolor="#EDF8FC" valign="top">
                <span style="font-family: Arial, sans-serif; ">
                    <label>
<input type="text" name="q_subj" size="39" value="<?php echo $q_subj; ?>"/>
</label></td>
            <td width="71" bgcolor="#EDF8FC" valign="top">
                <p align="right"><b><span  style="font-family: Arial, sans-serif; ">Message</b></td>
            <td width="299" bgcolor="#EDF8FC" valign="top">

                <label>
<input type="text" name="q_msg" size="28" value="<?php echo $q_msg; ?>"/>
</label></td>
        </tr>
        <tr>
            <td width="63" bgcolor="#EDF8FC" valign="top">
                <p align="right"><span  style="font-family: Arial, sans-serif; "><b>Status</b></td>
            <td width="286" bgcolor="#EDF8FC" valign="top">
                <span style="font-family: Arial, sans-serif; ">
                    <label>
<select name="q_status">
    <option value='' <?php if ( $q_status == '' ) {
        echo ' selected ';
    } ?>></option>
    <option value='queued' <?php if ( $q_status == 'queued' ) {
        echo ' selected ';
    } ?>>queued
    </option>
    <option value='error' <?php if ( $q_status == 'error' ) {
        echo ' selected ';
    } ?>>error
    </option>
    <option value='sent' <?php if ( $q_status == 'sent' ) {
        echo ' selected ';
    } ?>>sent
    </option>
</select>
</label>
                </td>
            <td width="71" bgcolor="#EDF8FC" valign="top">
                <p align="right"><b><span  style="font-family: Arial, sans-serif; ">Type</b></td>
            <td width="299" bgcolor="#EDF8FC" valign="top">
                <label>
<select name="q_type">
    <option value='' <?php if ( $q_type == '' ) {
        echo ' selected ';
    } ?>></option>
    <option value='1' <?php if ( $q_type == '1' ) {
        echo ' selected ';
    } ?>>1 - Complete Order
    </option>
    <option value='2' <?php if ( $q_type == '2' ) {
        echo ' selected ';
    } ?>>2 - Confirm Order
    </option>
    <option value='3' <?php if ( $q_type == '3' ) {
        echo ' selected ';
    } ?>>3 - Pend Order
    </option>
    <option value='4' <?php if ( $q_type == '4' ) {
        echo ' selected ';
    } ?>>4 - Expire Order
    </option>
    <option value='5' <?php if ( $q_type == '5' ) {
        echo ' selected ';
    } ?>>5 - Account Confirmation
    </option>
    <option value='6' <?php if ( $q_type == '6' ) {
        echo ' selected ';
    } ?>>6 - Forgot Pass
    </option>
    <option value='7' <?php if ( $q_type == '7' ) {
        echo ' selected ';
    } ?>>7 - Pixels Published
    </option>
    <option value='8' <?php if ( $q_type == '8' ) {
        echo ' selected ';
    } ?>>8 - Order Renewed
    </option>
</select>
</label>

            </td>
        </tr>
        <tr>
            <td width="731" bgcolor="#EDF8FC" colspan="4">
                <span style="font-family: Arial, sans-serif; "><b>
                        <input type="submit" value="Find Emails" name="B1" style="float: left"><?php if ( $search == 'y' ) { ?>&nbsp; </b><b>[<span style="font-family: Arial, sans-serif; "><a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-outgoing-email' ) ); ?>">Start a New Search</a>]</b><?php } ?></td>
        </tr>
    </table>
<?php
$where_sql = "";
if ( $q_to_add != '' ) {
	$where_sql .= " AND `to_address` like '%" . mysqli_real_escape_string( $GLOBALS['connection'], $q_to_add ) . "%' ";
}

if ( $q_to_name != '' ) {
	$where_sql .= " AND `to_name` like '%" . mysqli_real_escape_string( $GLOBALS['connection'], $q_to_name ) . "%' ";
}

if ( $q_msg != '' ) {
	$where_sql .= " AND `message` like '%" . mysqli_real_escape_string( $GLOBALS['connection'], $q_msg ) . "%' ";
}

if ( $q_subj != '' ) {
	$where_sql .= " AND `subject` like '%" . mysqli_real_escape_string( $GLOBALS['connection'], $q_subj ) . "%' ";
}

if ( !empty($q_type) ) {
	$where_sql .= " AND `template_id` like '" . mysqli_real_escape_string( $GLOBALS['connection'], $q_type ) . "' ";
}

if ( $q_status != '' ) {
	$where_sql .= " AND `status`='" . mysqli_real_escape_string( $GLOBALS['connection'], $q_status ) . "' ";
}

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "mail_queue where 1=1 $where_sql order by mail_date DESC";

$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
$count            = mysqli_num_rows( $result );

$records_per_page = 40;
$offset = isset( $_REQUEST['offset'] ) ? intval( $_REQUEST['offset'] ) : 0;
if ( $count > $records_per_page ) {
	mysqli_data_seek( $result, $offset );
}
if ( $count > $records_per_page ) {
	$pages    = ceil( $count / $records_per_page );
	$cur_page = $offset / $records_per_page;
	$cur_page ++;

	?>
    <div style="text-align: center;"><b><?php echo $count; ?> Emails on Queue returned (<?php echo $pages; ?> pages) </b></div>
	<?php
	echo "<div style=\"text-align: center;\">";
	echo "Page $cur_page of $pages - ";
	$nav   = nav_pages_struct( $q_string, $count, $records_per_page );
	$LINKS = 10;
	render_nav_pages( $nav, $LINKS, $q_string );
	echo "</div>";
}

?>

    <table border="0" cellSpacing="1" cellPadding="3" bgColor="#d9d9d9">
        <tr bgColor="#eaeaea">
            <td><b>Date</b></td>
            <td><b>Type</b></td>
            <td><b>To Addr</b></td>
            <td><b>To Name</b></td>
            <td><b>Fr Addr</b></td>
            <td><b>Fr Name</b></td>
            <td><b>Subj</b></td>
            <td><b>Msg</b></td>
            <td><b>Html Msg</b></td>
            <td><b>Att</b></td>
            <td><b>Status</b></td>
            <td><b>Err</b></td>
            <td><b>Retry</b></td>
            <td><b>Action</b></td>
        </tr>

		<?php

		$i = 0;
		while ( ( $row = mysqli_fetch_array( $result ) ) && ( $i < $records_per_page ) ) {

			$i ++;

			?>

            <tr bgColor="#ffffff">
                <td><?php echo get_date_from_gmt( $row['mail_date'] ); ?></td>
                <td><?php echo $row['template_id']; ?></td>
                <td><?php echo $row['to_address']; ?></td>
                <td><?php echo $row['to_name']; ?></td>
                <td><?php echo $row['from_address']; ?></td>
                <td><?php echo $row['from_name']; ?></td>
                <td><?php echo substr( $row['subject'], 0, 7 ); ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-show-email' ) ); ?>&amp;mail_id=<?php echo intval( $row['mail_id'] ); ?>">...</a></td>
                <td><?php echo substr( $row['message'], 0, 7 ); ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-show-email' ) ); ?>&amp;mail_id=<?php echo intval( $row['mail_id'] ); ?>">...</a></td>
                <td><?php echo substr( $row['html_message'], 0, 7 ); ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-show-email' ) ); ?>&amp;mail_id=<?php echo intval( $row['mail_id'] ); ?>">...</a></td>
                <td><?php echo $row['attachments']; ?></td>
                <td><span  style="color: <?php if ( $row['status'] == 'sent' ) {
						echo 'green';
					} ?>; "><?php echo $row['status']; ?></td>
                <td><?php echo $row['error_msg']; ?></td>
                <td><?php echo $row['retry_count']; ?></td>
                <td><b><a href='<?php echo esc_url( admin_url( 'admin.php?page=mds-outgoing-email&mds-action=resend&mail_id=' . $row['mail_id'] . $q_string ) ); ?>>Resend</a></b></td>
            </tr>

			<?php
		}

		?>

    </table>
<?php
if ( $count > $records_per_page ) {
	$pages    = ceil( $count / $records_per_page );
	$cur_page = $offset / $records_per_page;
	$cur_page ++;

	echo "<div style=\"text-align: center;\">";
	?>

	<?php
	echo "Page $cur_page of $pages - ";
	$nav   = nav_pages_struct( $q_string, $count, $records_per_page );
	$LINKS = 10;
	render_nav_pages( $nav, $LINKS, $q_string );
	echo "</div>";
}

?>