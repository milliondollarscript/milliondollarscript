<?php
/*
 * @package       mds
 * @copyright     (C) Copyright 2022 Ryan Rhode, All rights reserved.
 * @author        Ryan Rhode, ryan@milliondollarscript.com
 * @version       2022-01-30 17:07:25 EST
 * @license       This program is free software; you can redistribute it and/or modify
 *        it under the terms of the GNU General Public License as published by
 *        the Free Software Foundation; either version 3 of the License, or
 *        (at your option) any later version.
 *
 *        This program is distributed in the hope that it will be useful,
 *        but WITHOUT ANY WARRANTY; without even the implied warranty of
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *        GNU General Public License for more details.
 *
 *        You should have received a copy of the GNU General Public License along
 *        with this program;  If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 *
 *  * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *        Million Dollar Script
 *        A pixel script for selling pixels on your website.
 *
 *        For instructions see README.txt
 *
 *        Visit our website for FAQs, documentation, a list team members,
 *        to post any bugs or feature requests, and a community forum:
 *        https://milliondollarscript.com/
 *
 */

ini_set( 'max_execution_time', 10000 );
require_once __DIR__ . "/../include/init.php";
require( 'admin_common.php' );

global $f2;
$BID = $f2->bid();

$banner_data = load_banner_constants( $BID );

?>
    The following screen shows a map of all the orders made on a grid. Move your mouse over the blocks to find who owns the order. Click on the block to manage the order.<br>
    Red blocks are on order (Status can be: 'reserved', 'ordered', 'sold'), Green blocks are currently selected (Status can be: 'new')
    </span>
<?php

$sql = "Select * from " . MDS_DB_PREFIX . "banners ";
$res = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error($sql) );
?>

    <form name="bidselect" method="post" action="ordersmap.php">

        Select grid: <select name="BID" onchange="mds_submit(this)">
            <option></option>
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

echo "<iframe width=\"" . ( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ) . "\" height=\"" . ( ( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ) + 50 ) . "\" frameborder=0 marginwidth=0 marginheight=0 VSPACE=0 HSPACE=0 SCROLLING=no  src=\"" . "map_iframe.php?BID=$BID\"></iframe>";

?>