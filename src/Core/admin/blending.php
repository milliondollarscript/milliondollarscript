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

ini_set( 'max_execution_time', 6000 );

require_once __DIR__ . "/../include/init.php";

require( 'admin_common.php' );

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

if ( isset($_FILES['blend_image']) && isset($_FILES['blend_image']['tmp_name']) && $_FILES['blend_image']['tmp_name'] != '' ) {

	$temp = explode( ".", $_FILES['blend_image']['name'] );
	if ( array_pop( $temp ) != 'png' ) {
		echo "<p><font color='red'><b>Error: the image must be a PNG file</b></font></p>";
	} else {

		echo "moving.. " . $_FILES['blend_image']['tmp_name'] . " to:" . \MillionDollarScript\Classes\Utility::get_upload_path() . "grids/background$BID.png" . "<br>";

		move_uploaded_file( $_FILES['blend_image']['tmp_name'], \MillionDollarScript\Classes\Utility::get_upload_path() . "grids/background$BID.png" );
	}
}

if ( isset($_REQUEST['action']) && $_REQUEST['action'] == 'delete' ) {

	if ( file_exists( \MillionDollarScript\Classes\Utility::get_upload_path() . "grids/background$BID.png" ) ) {
		unlink( \MillionDollarScript\Classes\Utility::get_upload_path() . "grids/background$BID.png" );
	}
	//print_r ($_REQUEST);

}

?>

Image Blending - Allows you to specify an image to blend in with your grid in the background. <br>
(This functionality requires GD 2.0.1 or later)<br>
- Upload PNG true color image<br>
- The image must have an alpha channel (Eg. PNG image created with Photoshop with blending options set).<br>
- See https://milliondollarscript.com/admin/temp/background.png as an example of an image with an alpha channel set to 50%.<br>
- <a href="https://milliondollarscript.com/article/alpha-blending-tutorial/" target="_blank">See the tutorial</a> to get an idea how to create background images using Photoshop.
<hr>
<?php
$sql = "Select * from " . MDS_DB_PREFIX . "banners ";
$res = mysqli_query( $GLOBALS['connection'], $sql );
?>

<form name="bidselect" method="post" action="blending.php">

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
</form>
<hr>
Upload <b>True-color PNG Image</b> to blend:
<br>
<form enctype="multipart/form-data" method="post" action="blending.php">
    <input type="file" name="blend_image">
    <input type="submit" value="Upload"> (Maximum upload size possible:<?php echo nice_format( ini_get( 'upload_max_filesize' ) ); ?>)<br>
    <input type="hidden" name="BID" value="<?php echo $BID; ?>">
</form>
<input type="button" value="Delete - Disable Blending" onclick="if (!confirmLink(this, 'Delete background image, are you sure')) return false;" data-link="blending.php?action=delete&BID=<?php echo $BID; ?>">
<p>
	<?php

	?>
    Selected Grid:<br>
    <img src="preview_blend.php?time=<?php echo time(); ?>&BID=<?php echo $BID ?>">
</p>
