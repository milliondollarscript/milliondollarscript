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

use MillionDollarScript\Classes\Admin\Notices;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

// Handle admin-post.php form submissions separately to prevent header issues
if (isset($_GET['processed']) && $_GET['processed'] == '1') {
    // This is a return visit after processing
    Notices::add_notice(Language::get("Grid processing completed successfully."), 'success');
}

ini_set( 'max_execution_time', 500 );

global $f2;

// Handle form submission for processing pixels
// This runs during page load, not after a form submission through admin-post.php
if ( isset($_REQUEST['process']) && $_REQUEST['process'] == '1' && !isset($_REQUEST['action']) ) {

	// Buffer output to prevent "headers already sent" errors
	ob_start();

	// Process all selected banners
	if ( ( $_REQUEST['banner_list'][0] ) == 'all' ) {
		// Process all banners
		$sql = "select * from " . MDS_DB_PREFIX . "banners ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		while ( $row = mysqli_fetch_array( $result ) ) {
			$BID = $row['banner_id'];
			echo process_image( $row['banner_id'] );
			publish_image( $row['banner_id'] );
			process_map( $row['banner_id'] );
		}
	} else {
		// Process selected banners

		foreach ( $_REQUEST['banner_list'] as $key => $banner_id ) {
			# Banner ID.
			$BID = $banner_id;

			echo process_image( $banner_id );
			publish_image( $banner_id );
			process_map( $banner_id );
		}
	}

	echo "<br>Finished.<hr>";
	ob_end_flush();
}

// This function is called by admin-post.php to handle form submissions
function mds_process_pixels_admin_handler() {
	// Verify nonce for security
	if ( !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mds-admin') ) {
		wp_die('Security check failed');
	}

	// Process the form submission
	if ( isset($_POST['process']) && $_POST['process'] == '1' ) {
		// Redirect back to the admin page after processing
		$redirect_url = admin_url('admin.php?page=mds-process-pixels&processed=1');

		// If there are banner_list parameters, add them to the redirect URL
		if ( isset($_POST['banner_list']) && is_array($_POST['banner_list']) ) {
			$redirect_url .= '&process=1';
			foreach ( $_POST['banner_list'] as $banner_id ) {
				$redirect_url .= '&banner_list[]=' . urlencode($banner_id);
			}
		}

		// Perform the redirect
		wp_redirect($redirect_url);
		exit;
	}
}

// Register the admin post handler
add_action('admin_post_mds_admin_form_submission', 'mds_process_pixels_admin_handler');

// Process images

if ( ! is_writable( Utility::get_upload_path() . "grids/" ) ) {
	echo "<b>Warning:</b> The script does not have permission write to " . Utility::get_upload_path() . "grids/ or the directory does not exist <br>";
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
	echo "<h3>Note: There are/is $c pixel ads waiting to be approved. <a href='" . esc_url( admin_url( 'admin.php?page=mds-approve-pixels' ) ) . "'>Approve pixel ads here.</a></h3>";
}

?>
<p>
    Here you can process the images. This is where the script gets all the user's approved pixels, and merges it into a single image. It automatically publishes the final grid into the <?php echo $BANNER_PATH; ?> directory where the grid images are served from. Click the button below after approving pixels.
</p>
<form method='post' action='<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>'>
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission" />
    <input type="hidden" name="mds_dest" value="process-pixels" />

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

