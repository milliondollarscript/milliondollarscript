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

Language::out( 'Image Blending - Allows you to specify an image to blend in with your grid in the background.<br />
- Upload a PNG, JPG, or GIF image.<br />
- Use the slider below to control the opacity (transparency) of the background image.<br />
' );
?>
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

<input type="button" value="Delete - Disable Blending" onclick="if (!confirmLink(this, 'Delete background image, are you sure')) return false;" data-link="<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>backgrounds&amp;mds-action=delete&amp;BID=<?php echo $BID; ?>">
<p>
	<?php
	$mds_admin_ajax_nonce = wp_create_nonce( 'mds_admin_ajax_nonce' );
	?>
	<?php Language::out( 'Selected Grid:' ); ?><br/>
    <img src="<?php echo esc_url( admin_url( 'admin-ajax.php?action=mds_admin_ajax&mds_admin_ajax_nonce=' . $mds_admin_ajax_nonce ) ); ?>&amp;mds-ajax=preview-blend&amp;time=<?php echo time(); ?>&amp;BID=<?php echo $BID ?>">
</p>
