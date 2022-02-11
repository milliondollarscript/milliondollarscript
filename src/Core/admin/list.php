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

require_once __DIR__ . "/../include/init.php";
require( 'admin_common.php' );

global $f2;
$BID = $f2->bid();

$bid_sql = " AND banner_id=$BID ";

if ( ( $BID == 'all' ) || ( $BID == '' ) ) {
	$BID     = '';
	$bid_sql = "  ";
}

$sql = "Select * from " . MDS_DB_PREFIX . "banners ";
$res = mysqli_query( $GLOBALS['connection'], $sql );
?>
    <form name="bidselect" method="post" action="list.php">
        <label>
            Select grid: <select name="BID" onchange="mds_submit(this)">

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
    </form>
    <br/>
    <p>Here is the list of your top advertisers for the selected grid. <b>To have this list on your own page, copy and paste the following HTML/JavaScript code.</b></p>
<?php
require_once realpath( __DIR__ . "/../include/mds_ajax.php" );
global $load_mds_js;
$load_mds_js = true;
$mds_ajax = new Mds_Ajax();
ob_start();
$mds_ajax->show( 'list', $BID, 'list' );
$output = ob_get_contents();
?>
    <textarea style='font-size: 10px;' rows='10' onfocus="this.select()" cols="90%"><?php echo htmlentities( $output ); ?></textarea>

    <p>You can also use PHP:</p>
    <textarea style='font-size: 10px;' rows='10' onfocus="this.select()" cols="90%"><?php echo htmlentities( '<?php
require_once( BASE_PATH . "/include/mds_ajax.php" );
$mds_ajax = new Mds_Ajax();
$mds_ajax->show( \'list\', $BID, \'list\' );
' ); ?></textarea>

    <p>Results:</p>
<?php

echo $output;
