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

use MillionDollarScript\Classes\Config;

defined( 'ABSPATH' ) or exit;

$ADVANCED_CLICK_COUNT = Config::get( 'ADVANCED_CLICK_COUNT' );
if ( $ADVANCED_CLICK_COUNT != 'YES' ) {
	die ( "Advanced click tracking not enabled. You will need to enable advanced click tracking in the Main Config" );
}

global $f2;
$BID = $f2->bid();

$sql = "Select * from " . MDS_DB_PREFIX . "banners ";
$res = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
?>

    <form name="bidselect" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	    <?php wp_nonce_field( 'mds-admin' ); ?>
        <input type="hidden" name="action" value="mds_admin_form_submission">
        <input type="hidden" name="mds_dest" value="clicks">

        Select grid: <select name="BID" onchange="this.form.submit()">
			<?php
			while ( $row = mysqli_fetch_array( $res ) ) {

				if ( ( $row['banner_id'] == $BID ) && ( $BID != 'all' ) ) {
					$sel = 'selected';
				} else {
					$sel = '';
				}
				echo '<option ' . $sel . ' value=' . $row['banner_id'] . '>' . $row['name'] . '</option>';
			}
			?>
        </select>
    </form>
    <hr>
<?php

$local_date = ( gmdate( "Y-m-d H:i:s" ) );
$local_time = strtotime( $local_date );

if ( ! isset( $_REQUEST['from_day'] ) || $_REQUEST['from_day'] == '' ) {
	$_REQUEST['from_day'] = "1";
}
if ( ! isset( $_REQUEST['from_month'] ) || $_REQUEST['from_month'] == '' ) {
	$_REQUEST['from_month'] = date( "m", $local_time );
}
if ( ! isset( $_REQUEST['from_year'] ) || $_REQUEST['from_year'] == '' ) {
	$_REQUEST['from_year'] = date( 'Y', $local_time );
}

if ( ! isset( $_REQUEST['to_day'] ) || $_REQUEST['to_day'] == '' ) {
	$_REQUEST['to_day'] = date( 'd', $local_time );
}
if ( ! isset( $_REQUEST['to_month'] ) || $_REQUEST['to_month'] == '' ) {
	$_REQUEST['to_month'] = date( 'm', $local_time );
}
if ( ! isset( $_REQUEST['to_year'] ) || $_REQUEST['to_year'] == '' ) {
	$_REQUEST['to_year'] = date( 'Y', $local_time );
}
?>

    <h3>Click Report</h3>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	    <?php wp_nonce_field( 'mds-admin' ); ?>
        <input type="hidden" name="action" value="mds_admin_form_submission">
        <input type="hidden" name="mds_dest" value="clicks">

        From y/m/d:
        <select name="from_year">
            <option value=''></option>
			<?php
			for ( $i = 2005; $i <= date( "Y" ); $i ++ ) {
				if ( $_REQUEST['from_year'] == $i ) {
					$sel = " selected ";
				} else {
					$sel = " ";
				}
				echo "<option value='$i' $sel>$i</option>";
			}
			?>
        </select>

        <select name="from_month">
            <option value=''></option>
			<?php
			for ( $i = 1; $i <= 12; $i ++ ) {
				if ( $_REQUEST['from_month'] == $i ) {
					$sel = " selected ";
				} else {
					$sel = " ";
				}
				echo "<option value='$i' $sel >$i</option>";
			}
			?>
        </select>
        <select name="from_day">
            <option value=''></option>
			<?php
			for ( $i = 1; $i <= 31; $i ++ ) {
				if ( $_REQUEST['from_day'] == $i ) {
					$sel = " selected ";
				} else {
					$sel = " ";
				}
				echo "<option value='$i' $sel >$i</option>";
			}
			?>
        </select>

        To y/m/d:

        <select name="to_year">
            <option value=''></option>
			<?php
			for ( $i = 2005; $i <= date( "Y" ); $i ++ ) {
				if ( $_REQUEST['to_year'] == $i ) {
					$sel = " selected ";
				} else {
					$sel = " ";
				}
				echo "<option value='$i' $sel>$i</option>";
			}
			?>
        </select>

        <select name="to_month">
            <option value=''></option>
			<?php
			for ( $i = 1; $i <= 12; $i ++ ) {
				if ( $_REQUEST['to_month'] == $i ) {
					$sel = " selected ";
				} else {
					$sel = " ";
				}
				echo "<option value='$i' $sel >$i</option>";
			}
			?>
        </select>

        <select name="to_day">
            <option value=''></option>
			<?php
			for ( $i = 1; $i <= 31; $i ++ ) {
				if ( $_REQUEST['to_day'] == $i ) {
					$sel = " selected ";
				} else {
					$sel = " ";
				}
				echo "<option value='$i' $sel >$i</option>";
			}
			?>
        </select>

        <input type="hidden" name="BID" value="<?php echo $BID ?>">
        <input type="submit" name="select_date" value="Go">
        <input type="button" name="select_date" value="Reset" onclick='window.location.href="<?php echo esc_url( admin_url( 'admin.php?page=mds-clicks' ) ); ?>"'>
    </form><p>

	<?php

	$from = intval( $_REQUEST['from_year'] ) . "-" . intval( $_REQUEST['from_month'] ) . "-" . intval( $_REQUEST['from_day'] );

	$to = intval( $_REQUEST['to_year'] ) . "-" . intval( $_REQUEST['to_month'] ) . "-" . intval( $_REQUEST['to_day'] );

	$sql = "SELECT *, SUM(t1.clicks) as CLICKSUM, SUM(t2.views) as VIEWSUM FROM " . MDS_DB_PREFIX . "clicks t1 INNER JOIN " . MDS_DB_PREFIX . "views t2 ON t1.banner_id = t2.banner_id WHERE t1.banner_id=" . intval( $BID ) . " AND t1.date >= '{$from}' AND t1.date <= '{$to}' GROUP BY t1.date, t1.clicks, t2.views, t1.block_id, t2.block_id, t2.user_id, t2.date";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );

	?>

    <p>
        Showing Report for grid:<?php echo $BID; ?>
    </p>
    <table border="1">

        <tr>
            <td><b>Date</b></td>
            <td><b>Clicks</b></td>
            <td><b>Views</b></td>
        </tr>

		<?php
		$totalclicks = 0;
		$totalviews  = 0;
		if ( mysqli_num_rows( $result ) > 0 ) {

			while ( $row = mysqli_fetch_array( $result ) ) {

				?>
                <tr>
                    <td><?php echo get_date_from_gmt( $row['date'] ) ?></td>
                    <td><?php echo $row['CLICKSUM'] ?></td>
                    <td><?php echo $row['VIEWSUM'] ?></td>

                </tr>

				<?php
				$totalclicks = $totalclicks + $row['CLICKSUM'];
				$totalviews  = $totalviews + $row['VIEWSUM'];
			}
		}

		?>
    </table>

    Total Clicks: <?php echo $totalclicks; ?><br/>
    Total Views: <?php echo $totalviews; ?>