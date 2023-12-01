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

?>
<p>
    Grid Shortcode - This is the shortcode that you copy and paste to your page to display the grid.<br />
    Stats Shortcode - Copy and paste into your page to display the stats.<br />
    Note that you can also use blocks to display these on your site. <a target="_blank" href="https://wordpress.org/documentation/article/wordpress-block-editor/">More info on using blocks</a>.
</p>
<table width="100%" border="0" cellSpacing="1" cellPadding="3" bgColor="#d9d9d9">
    <tr bgColor="#eaeaea">
        <td><b>Grid ID</b></td>
        <td><b>Name</b></td>
        <td><b>Grid HTML</b></td>
        <td><b>Stats HTML</b></td>

    </tr>
	<?php
	$result = mysqli_query( $GLOBALS['connection'], "select * FROM " . MDS_DB_PREFIX . "banners" ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {

		?>

        <tr bgcolor="#ffffff">

            <td><font size="2"><?php echo $row['banner_id']; ?></font></td>
            <td><font size="2"><?php echo $row['name']; ?></font></td>
            <td><textarea onfocus="this.select()" rows='3' cols='35'><?php echo get_grid_shortcode( $row['banner_id'] ); ?></textarea></td>
            <td><textarea onfocus="this.select()" rows='3' cols='35'><?php echo get_stats_shortcode( $row['banner_id'] ); ?></textarea></td>

        </tr>
		<?php
	}
	?>
</table>
