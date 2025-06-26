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

@ini_set( 'max_execution_time', 500 );

global $f2;

// Handle processing after redirect from admin-post.php
if ( isset($_REQUEST['process']) && $_REQUEST['process'] == '1' && !isset($_REQUEST['action']) && isset($_REQUEST['banner_list']) ) {
	
	$processing_output = '';
	$banners_processed = 0;
	
	try {
		// Process all selected banners
		if ( isset($_REQUEST['banner_list']) && is_array($_REQUEST['banner_list']) && $_REQUEST['banner_list'][0] == 'all' ) {
			// Process all banners
			$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners";
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
			while ( $row = mysqli_fetch_array( $result ) ) {
				$BID = $row['banner_id'];
				$processing_output .= process_image( $row['banner_id'] );
				publish_image( $row['banner_id'] );
				process_map( $row['banner_id'] );
				$banners_processed++;
			}
		} else if ( isset($_REQUEST['banner_list']) && is_array($_REQUEST['banner_list']) ) {
			// Process selected banners
			foreach ( $_REQUEST['banner_list'] as $key => $banner_id ) {
				if ( $banner_id == 'all' ) continue;
				$BID = intval( $banner_id );
				$processing_output .= process_image( $BID );
				publish_image( $BID );
				process_map( $BID );
				$banners_processed++;
			}
		}
		
	} catch (Exception $e) {
		$processing_error = $e->getMessage();
	}
}

// Display feedback messages after processing
if ( isset($_REQUEST['process']) && $_REQUEST['process'] == '1' && !isset($_REQUEST['action']) ) {
	if ( isset($banners_processed) && $banners_processed > 0 ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . 
			esc_html( Language::get_replace(
				"Successfully processed [COUNT] grid(s).", 
				'[COUNT]', 
				$banners_processed
			) ) . 
			'</p></div>';
		
		// Output any processing messages
		if ( !empty($processing_output) ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . wp_kses_post($processing_output) . '</p></div>';
		}
	}
	
	if ( isset($processing_error) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . 
			esc_html( Language::get("Error processing grids: ") . $processing_error ) . 
			'</p></div>';
	}
}



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

