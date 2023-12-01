<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.6
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

if ( isset( $_POST['action'] ) && $_POST['action'] == 'mds_admin_form_submission' ) {
	return;
}

ini_set( 'max_execution_time', 10000 );

global $f2;
$BID = $f2->bid();

$banner_data = load_banner_constants( $BID );

?>
    The following screen shows a map of all the orders made on a grid. Move your mouse over the blocks to find who owns the order. Click on the block to manage the order.<br>
    Red blocks are on order (Status can be: 'reserved', 'ordered', 'sold'), Green blocks are currently selected (Status can be: 'new')
<?php

$sql = "Select * from " . MDS_DB_PREFIX . "banners ";
$res = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
?>

    <form name="bidselect" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'mds-admin' ); ?>
        <input type="hidden" name="action" value="mds_admin_form_submission"/>
        <input type="hidden" name="mds_dest" value="map-of-orders"/>

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
/*  <iframe width="<?php echo( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ); ?>" height="<?php echo( ( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ) + 50 ); ?>" frameborder=0 marginwidth=0 marginheight=0 VSPACE=0 HSPACE=0 SCROLLING=no src="<?php echo esc_url( admin_url( 'admin.php?page=mds-map-iframe&BID=' . $BID ) ); ?>"></iframe> */
?>
<?php
require_once MDS_CORE_PATH . "admin/map-iframe.php";
?>