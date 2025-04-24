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

use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

// --- BEGIN NEW DELETE HANDLING ---
if ( isset( $_GET['mds-action'] ) && $_GET['mds-action'] === 'delete' && isset( $_GET['BID'] ) ) {
	$BID = intval( $_GET['BID'] );
	// Verify nonce 
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'mds_delete_background_' . $BID ) ) {
		wp_die( Language::get( 'Security check failed.' ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( Language::get( 'Sorry, you are not allowed to perform this action.' ) );
	}

	$params = ['page' => 'mds-backgrounds', 'BID' => $BID]; // Params for redirect

	if ($BID > 0) {
		$upload_path = Utility::get_upload_path() . "grids/";
		$files_to_delete = glob($upload_path . "background{$BID}.*");
		$deleted = false;
		$found = false;

		if ($files_to_delete) {
			$found = true;
			foreach ($files_to_delete as $file_to_delete) {
				if ( is_writable( $file_to_delete ) && unlink( $file_to_delete ) ) {
					$deleted = true; // Mark as deleted if at least one succeeds
				} else {
					$params['delete_error'] = 'failed_unlink';
					// Log the error for debugging
					error_log("MDS Error: Failed to unlink background file: " . $file_to_delete);
					break; // Stop if one fails
				}
			}
		}

		if ($found && $deleted && !isset($params['delete_error'])) {
			$params['delete_success'] = 'true';
			// Also delete the opacity option when image is deleted
			delete_option( 'mds_background_opacity_' . $BID );
		} else if (!$found) {
			$params['delete_error'] = 'not_found';
		}
		// If delete failed but file was found, 'failed_unlink' is already set
	} else {
		$params['delete_error'] = 'invalid_bid';
	}

	// Redirect back to the same page without the action param, but with feedback
	wp_safe_redirect( add_query_arg( $params, admin_url( 'admin.php' ) ) );
	exit;
}
// --- END NEW DELETE HANDLING ---

ini_set( 'max_execution_time', 6000 );

global $f2;
$BID = $f2->bid();

// Display feedback messages based on redirect parameters
if ( isset( $_GET['upload_success'] ) ) {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( Language::get('Background image uploaded successfully.') ) . '</p></div>';
}
if ( isset( $_GET['upload_error'] ) ) {
	$error_message = Language::get('Error uploading background image.');
	if ( $_GET['upload_error'] === 'not_png' ) { 
		$error_message = Language::get('Error: the image must be a PNG file.');
	} elseif ( $_GET['upload_error'] === 'invalid_type' ) { 
		$error_message = Language::get('Error: Invalid file type. Please upload a PNG, JPG, or GIF image.');
	} elseif ( $_GET['upload_error'] === 'failed_move' ) {
		$error_message = Language::get('Error: Could not save the uploaded file.');
	}
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_message ) . '</p></div>';
}
if ( isset( $_GET['delete_success'] ) ) {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( Language::get('Background image deleted successfully.') ) . '</p></div>';
}
if ( isset( $_GET['delete_error'] ) ) {
	$error_message = Language::get('Error deleting background image.');
	if ( $_GET['delete_error'] === 'failed_unlink' ) {
		$error_message = Language::get('Error: Could not delete the file.');
	} elseif ( $_GET['delete_error'] === 'not_found' ) {
		$error_message = Language::get('Error: File not found.');
	} elseif ( $_GET['delete_error'] === 'invalid_bid' ) {
		$error_message = Language::get('Error: Invalid grid ID.');
	}
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_message ) . '</p></div>';
}
// Add message for opacity save
if ( isset( $_GET['opacity_saved'] ) ) {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( Language::get('Background opacity saved successfully.') ) . '</p></div>';
}

function nice_format( $val ) {
	$val  = trim( $val );
	$last = strtolower( $val[ strlen( $val ) - 1 ] );
	switch ( $last ) {
		// The 'G' modifier is available since PHP 5.1.0
		case 'g':
			$val = substr( $val, 0, 1 );
			$val .= ' Gigabytes';

			break;
		case 'm':
			$val = substr( $val, 0, 1 );
			$val .= ' Megabytes';

			break;
		case 'k':
			$val = substr( $val, 0, 1 );
			$val .= ' Kilobytes';
			break;
		default:
			$val .= ' Bytes';
			break;
	}

	return $val;
}

?>
<h2><?php Language::out( 'Image Blending' ); ?></h2>
<?php 
Language::out( 'Allows you to specify an image to blend in with your grid in the background.<br />
<ul>
<li>Upload a PNG, JPG, or GIF image.</li>
<li>Use the slider below to control the opacity (transparency) of the background image.</li>
</ul>' );
?>
<h3><?php Language::out_replace( 'Remember to process your Grid Image(s) <a href="%PROCESS_PIXELS_URL%">here</a>', '%PROCESS_PIXELS_URL%', esc_url( admin_url( 'admin.php?page=mds-process-pixels' ) ) ); ?></h3>
<hr/>
<?php
$sql = "Select * from " . MDS_DB_PREFIX . "banners ";
$res = mysqli_query( $GLOBALS['connection'], $sql );
?>

<form name="bidselect" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission">
    <input type="hidden" name="mds_dest" value="backgrounds">

	<?php Language::out( 'Select grid:' ); ?> <select name="BID" onchange="this.form.submit()">
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
<?php Language::out( 'Upload <b>Image (PNG, JPG, GIF)</b> to blend:' ); ?>
<br>
<form enctype="multipart/form-data" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission">
    <input type="hidden" name="mds_dest" value="backgrounds">

	<input type="file" name="blend_image">
	<input type="submit" value="Upload"> <?php Language::out( '(Maximum upload size possible:' ); ?> <?php echo nice_format( ini_get( 'upload_max_filesize' ) ); ?>)<br>
	<input type="hidden" name="BID" value="<?php echo $BID; ?>">

	<hr>
	<?php
	// Get current opacity value, default to 100 if not set
	$current_opacity = get_option( 'mds_background_opacity_' . $BID, 100 );
	?>
	<label for="background_opacity"><?php Language::out( 'Background Opacity:' ); ?></label>
	<input type="range" id="background_opacity" name="background_opacity" min="0" max="100" value="<?php echo esc_attr( $current_opacity ); ?>" style="vertical-align: middle;">
	<span id="background_opacity_value" style="display: inline-block; min-width: 3em; text-align: right; vertical-align: middle;"><?php echo esc_html( $current_opacity ); ?>%</span>
	<p><input type="submit" value="<?php Language::out( 'Save Opacity' ); ?>"></p> 

</form>

<script type="text/javascript">
	document.addEventListener('DOMContentLoaded', function() {
		const slider = document.getElementById('background_opacity');
		const display = document.getElementById('background_opacity_value');
		if (slider && display) {
			slider.addEventListener('input', function() {
				display.textContent = slider.value + '%';
			});
		}
	});
</script>

<?php
$delete_nonce = wp_create_nonce( 'mds_delete_background_' . $BID );
$delete_url = add_query_arg( [
    'page' => 'mds-backgrounds',
    'mds-action' => 'delete',
    'BID' => $BID,
    '_wpnonce' => $delete_nonce
], admin_url( 'admin.php' ) );
?>
<input type="button" value="Delete - Disable Blending" onclick="if (!confirm('Delete background image, are you sure?')) return false; window.location.href='<?php echo esc_url( $delete_url ); ?>';" >
<p>
	<?php
	$mds_admin_ajax_nonce = wp_create_nonce( 'mds_admin_ajax_nonce' );
	?>
	<?php Language::out( 'Selected Grid:' ); ?><br/>
    <img src="<?php echo esc_url( admin_url( 'admin-ajax.php?action=mds_admin_ajax&mds_admin_ajax_nonce=' . $mds_admin_ajax_nonce ) ); ?>&amp;mds-ajax=preview-blend&amp;time=<?php echo time(); ?>&amp;BID=<?php echo $BID ?>">
</p>
