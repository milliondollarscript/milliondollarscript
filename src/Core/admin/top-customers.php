<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.10
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

defined( 'ABSPATH' ) or exit;

global $f2;
$BID = $f2->bid();

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners ";
$res = mysqli_query( $GLOBALS['connection'], $sql );
?>
    <form name="bidselect" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	    <?php wp_nonce_field( 'mds-admin' ); ?>
        <input type="hidden" name="action" value="mds_admin_form_submission" />
        <input type="hidden" name="mds_dest" value="top-customers" />
        <?php
        /*
        <label>
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
        </label>
        */
        ?>
    </form>
    <br/>
    <p>Here is the list of your top advertisers for the selected grid. <b>To have this list on your own page you can use a block or a shortcode.</b></p>
    <label>
        <textarea style='font-size: 10px;' rows='10' onfocus="this.select()" cols="90%"><?php esc_html_e('[milliondollarscript align="center" width="100%" height="auto" type="list"]'); ?></textarea>
    </label>

    <p>Results:</p>
<?php

echo do_shortcode('[milliondollarscript align="center" width="100%" height="auto" type="list"]');
