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

defined( 'ABSPATH' ) or exit;

global $wpdb, $f2;
$BID = $f2->bid();

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners";
$res = $wpdb->get_results( $sql );

?>
<form name="bidselect" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission"/>
    <input type="hidden" name="mds_dest" value="top-clicks"/>

    <label><?php Language::out( 'Select grid:' ); ?>
        <select name="BID" onchange="this.form.submit()">
            <option value="all" <?php if ( $BID == 'all' ) {
				echo 'selected';
			} ?>><?php Language::out( 'Show All' ); ?>
            </option>
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
    </label>
</form>
<hr>
<p>
	<?php Language::out( 'Here is the list of the top clicks. You may copy and paste this list onto your website.' ); ?>
</p>

<table class="mds-top-clicks-table">
    <tr class="mds-top-clicks-heading">
        <td><?php Language::out( "Advertiser's Link" ); ?></td>
        <td><?php Language::out( "Order ID" ); ?></td>
        <td><?php Language::out( "Clicks" ); ?></td>
        <td><?php Language::out( "Views" ); ?></td>
    </tr>
	<?php

	$sql = "SELECT orders.order_id, blocks.banner_id, blocks.url, blocks.alt_text, SUM(click_count) AS clicksum, SUM(view_count) AS viewsum 
      FROM " . MDS_DB_PREFIX . "orders AS orders 
      JOIN " . MDS_DB_PREFIX . "blocks AS blocks ON orders.order_id = blocks.order_id 
      WHERE blocks.status = 'sold' 
      AND blocks.image_data <> ''";

	if ( $BID != 'all' && $BID != '' ) {
		$sql .= " AND blocks.banner_id = %d";
	}

	$sql .= " GROUP BY orders.order_id, blocks.url, blocks.alt_text
          ORDER BY clicksum DESC";

	if ( $BID != 'all' && $BID != '' ) {
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $BID ) );
	} else {
		$results = $wpdb->get_results( $sql );
	}

	foreach ( $results as $row ) {
		$order_id = intval( $row->order_id );
		?>
        <tr>
            <td>
                <a href='<?php echo esc_url( $row->url ); ?>' target='_blank'><?php echo esc_html( $row->alt_text ); ?></a>
            </td>
            <td>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-orders&order_id=' . $order_id ) ); ?>"><?php echo $order_id; ?></a>
            </td>
            <td>
				<?php echo intval( $row->clicksum ); ?>
            </td>
            <td>
				<?php echo intval( $row->viewsum ); ?>
            </td>
        </tr>
		<?php
	}

	?>
</table>
