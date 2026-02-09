<?php
/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2025, Ryan Rhode
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

use MillionDollarScript\Classes\System\Logs;

defined( 'ABSPATH' ) or exit;

if ( isset( $_POST['action'] ) && $_POST['action'] == 'mds_admin_form_submission' ) {
	return;
}

ini_set( 'max_execution_time', 300 );

global $f2, $wpdb;
$BID = $f2->bid();

$banner_data = load_banner_constants( $BID );

?>
    The following screen shows a map of all the orders made on a grid. Move your mouse over the blocks to find who owns the order. Click on the block to manage the order.<br>
    Red blocks are on order (Status can be: 'reserved', 'ordered', 'sold'), Green blocks are currently selected (Status can be: 'new')
<?php

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners";
$res = $wpdb->get_results( $sql );
if ( $wpdb->last_error ) {
	Logs::log( 'Database error in map-of-orders: ' . $wpdb->last_error );
	wp_die( esc_html__( 'A database error occurred.', 'milliondollarscript' ) );
}
?>

    <form name="bidselect" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'mds-admin' ); ?>
        <input type="hidden" name="action" value="mds_admin_form_submission"/>
        <input type="hidden" name="mds_dest" value="map-of-orders"/>

        Select grid: <select name="BID" onchange="this.form.submit()">
			<?php
			foreach ( $res as $row ) {

				if ( ( $row->banner_id == $BID ) && ( $BID != 'all' ) ) {
					$sel = 'selected';
				} else {
					$sel = '';
				}
				echo '<option ' . $sel . ' value="' . esc_attr( $row->banner_id ) . '">' . esc_html( $row->name ) . '</option>';
			}
			?>
        </select>
    </form>
    <hr>

<?php
require_once MDS_CORE_PATH . "admin/map-iframe.php";
?>