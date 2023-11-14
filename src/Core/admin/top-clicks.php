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

global $f2;
$BID = $f2->bid();

$bid_sql = " AND banner_id=$BID ";

if ( ( $BID == 'all' ) || ( $BID == '' ) ) {
	$BID     = '';
	$bid_sql = "  ";
}

$sql = "Select * from " . MDS_DB_PREFIX . "banners ";
$res = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error($sql) );

?>
<form name="bidselect" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission" />
    <input type="hidden" name="mds_dest" value="top-clicks" />

    Select grid: <select name="BID" onchange="this.form.submit()">
        <option value='all' <?php if ( $BID == 'all' ) {
			echo 'selected';
		} ?>>Show All
        </option>
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
<p>
    Here is the list of the top clicks. You may copy and paste this list onto your website.
</p>

<table width="100%" border="0" cellSpacing="1" cellPadding="3" align="center" bgColor="#d9d9d9">

    <tr>
        <td>
            <font face="arial" size="2"><b>Advertiser's Link</b></font>
        </td>
        <td>
            <font face="arial" size="2"><b>Blocks</b></font>
        </td>
        <td>
            <font face="arial" size="2"><b>Clicks</b></font>
        </td>
        <td>
            <font face="arial" size="2"><b>Views</b></font>
        </td>
    </tr>

	<?php

	//$sql = "SELECT *, DATE_FORMAT(MAX(order_date), '%Y-%c-%d') as max_date, sum(quantity) AS pixels FROM orders where status='completed' $bid_sql GROUP BY user_id, banner_id order by pixels desc ";

	//$sql = "SELECT *, DATE_FORMAT(MAX(order_date), '%Y-%c-%d') as max_date, sum(quantity) AS pixels FROM orders where status='completed' $bid_sql GROUP BY user_id, banner_id order by pixels desc ";

	$sql = "SELECT *, sum(click_count) AS clicksum, sum(view_count) AS viewsum, count(order_id) AS b FROM " . MDS_DB_PREFIX . "blocks WHERE STATUS='sold' AND image_data <> '' $bid_sql GROUP BY order_id, block_id, click_count, view_count ORDER BY clicksum DESC";

	//echo $sql;

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error($sql) );

	while ( $row = mysqli_fetch_array( $result ) ) {

		?>
        <tr bgcolor="#ffffff">
            <td>
                <font face="arial" size="2"><?php

					echo "<a href='" . $row['url'] . "' target='_blank' >" . $row['alt_text'] . "</a>";

					?></font>
            </td>
            <td>
                <font face="arial" size="2"><?php echo $row['b']; ?></font>
            </td>

            <td>
                <font face="arial" size="2"><?php echo $row['clicksum']; ?></font>
            </td>
            <td>
                <font face="arial" size="2"><?php echo $row['viewsum']; ?></font>
            </td>
        </tr>
		<?php
	}

	?>

</table>
