<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.2
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

use MillionDollarScript\Classes\Language;

defined( 'ABSPATH' ) or exit;

ini_set( 'max_execution_time', 6000 );

global $f2;
$BID = $f2->bid();

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

if ( isset( $_FILES['blend_image'] ) && isset( $_FILES['blend_image']['tmp_name'] ) && $_FILES['blend_image']['tmp_name'] != '' ) {

	$temp = explode( ".", $_FILES['blend_image']['name'] );
	if ( array_pop( $temp ) != 'png' ) {
		?>
        <p><span style="color: red; "><b><?php Language::out( 'Error: the image must be a PNG file' ); ?></b></span></p>
		<?php
	} else {

		move_uploaded_file( $_FILES['blend_image']['tmp_name'], \MillionDollarScript\Classes\Utility::get_upload_path() . "grids/background$BID.png" );
	}
}

if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'delete' ) {
	$filename = \MillionDollarScript\Classes\Utility::get_upload_path() . "grids/background$BID.png";
	if ( file_exists( $filename ) ) {
		unlink( $filename );
	}
}

if ( isset( $_POST['mds_dest'] ) ) {
	return;
}

Language::out_replace( 'Image Blending - Allows you to specify an image to blend in with your grid in the background.<br />
(This functionality requires GD 2.0.1 or later)<br />
- Upload PNG true color image<br />
- The image must have an alpha channel (Eg. PNG image created with Photoshop with blending options set).<br />
- See <a href="%BACKGROUND_URL%" target="_blank">background.png</a> as an example of an image with an alpha channel set to 50%.<br />
- <a href="https://milliondollarscript.com/documentation/alpha-blending-tutorial/" target="_blank">See the tutorial</a> to get an idea how to create background images using Photoshop.
', '%BACKGROUND_URL%',
	MDS_BASE_URL . 'src/Assets/images/background.png' );
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
<?php Language::out( 'Upload <b>True-color PNG Image</b> to blend:' ); ?>
<br>
<form enctype="multipart/form-data" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission">
    <input type="hidden" name="mds_dest" value="backgrounds">

    <input type="file" name="blend_image">
    <input type="submit" value="Upload"> <?php Language::out( '(Maximum upload size possible:' ); ?> <?php echo nice_format( ini_get( 'upload_max_filesize' ) ); ?>)<br>
    <input type="hidden" name="BID" value="<?php echo $BID; ?>">
</form>
<input type="button" value="Delete - Disable Blending" onclick="if (!confirmLink(this, 'Delete background image, are you sure')) return false;" data-link="<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>backgrounds&amp;mds-action=delete&amp;BID=<?php echo $BID; ?>">
<p>
	<?php
	$mds_admin_ajax_nonce = wp_create_nonce( 'mds_admin_ajax_nonce' );
	?>
	<?php Language::out( 'Selected Grid:' ); ?><br/>
    <img src="<?php echo esc_url( admin_url( 'admin-ajax.php?action=mds_admin_ajax&mds_admin_ajax_nonce=' . $mds_admin_ajax_nonce ) ); ?>&amp;mds-ajax=preview-blend&amp;time=<?php echo time(); ?>&amp;BID=<?php echo $BID ?>">
</p>
