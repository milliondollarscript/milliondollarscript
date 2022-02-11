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

ini_set( 'max_execution_time', 500 );
require_once __DIR__ . "/../include/init.php";
require( 'admin_common.php' );

global $f2;

if ( isset($_REQUEST['process']) && $_REQUEST['process'] == '1' ) {

	if ( ( $_REQUEST['banner_list'][0] ) == 'all' ) {
		// process all
		$sql = "select * from " . MDS_DB_PREFIX . "banners ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		while ( $row = mysqli_fetch_array( $result ) ) {
			$BID = $row['banner_id'];
			echo process_image( $row['banner_id'] );
			publish_image( $row['banner_id'] );
			process_map( $row['banner_id'] );
		}
	} else {
		// process selected

		foreach ( $_REQUEST['banner_list'] as $key => $banner_id ) {
			# Banner ID.
			$BID = $banner_id;

			echo process_image( $banner_id );
			publish_image( $banner_id );
			process_map( $banner_id );
		}
	}

	echo "<br>Finished.<hr>";
}

// Process images

if ( ! is_writable( \MillionDollarScript\Classes\Utility::get_upload_path() . "grids/" ) ) {
	echo "<b>Warning:</b> The script does not have permission write to " . \MillionDollarScript\Classes\Utility::get_upload_path() . "grids/ or the directory does not exist <br>";
}
$BANNER_PATH = get_banner_dir();
if ( ! is_writable( $BANNER_PATH ) ) {
	echo "<b>Warning:</b> The script does not have permission write to " . $BANNER_PATH . " or the directory does not exist<br>";
}

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders where approved='N' and status='completed' ";
$r = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
$result = mysqli_fetch_array( $r );
$c      = mysqli_num_rows( $r );

if ( $c > 0 ) {
	echo "<h3>Note: There are/is $c pixel ads waiting to be approved. <a href='approve.php'>Approve pixel ads here.</a></h3>";
}

?>
<p>
    Here you can process the images. This is where the script gets all the user's approved pixels, and merges it into a single image. It automatically publishes the final grid into the <?php echo $BANNER_PATH; ?> directory where the grid images are served from. Click the button below after approving pixels.
</p>
<form method='post' action='<?php echo $f2->value(BASE_HTTP_PATH); ?>admin/process.php'>
    <input value='1' name="process" type="hidden"/>
    <select name="banner_list[]" multiple size='3'>
        <option value="all" selected>Process All</option>
		<?php

		$sql = "Select * from " . MDS_DB_PREFIX . "banners";
		$res = mysqli_query( $GLOBALS['connection'], $sql );

		while ( $row = mysqli_fetch_array( $res ) ) {
			echo '<option value="' . $row['banner_id'] . '">#' . $row['banner_id'] . ' - ' . $row["name"] . '</option>' . "\n";
		}
		?>
    </select><br/>
    <input type="submit" name='submit' value="Process Grids(s)"/>
</form>

