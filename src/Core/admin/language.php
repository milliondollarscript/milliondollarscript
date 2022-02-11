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
require_once( '../include/dynamic_forms.php' );
require_once( '../include/code_functions.php' );

function validate_input() {
	$error = '';
	if ( ! isset( $_REQUEST['lang_code'] ) || $_REQUEST['lang_code'] == '' ) {
		$error .= "- Language Code is blank <br>";
	}
	if ( ! isset( $_REQUEST['lang_filename'] ) || $_REQUEST['lang_filename'] == '' ) {
		$error .= "- No language file selected <br>";
	}
	if ( ( ! isset( $_FILES['lang_image']['name'] ) || $_FILES['lang_image']['name'] == '' ) && ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] != 'edit' ) ) {
		$error .= "- No image uploaded <br>";
	}
	if ( ! isset( $_REQUEST['name'] ) || $_REQUEST['name'] == '' ) {
		$error .= "- Language name is blank<br>";
	}

	return $error;
}

if ( isset( $_REQUEST['action'] ) ) {
	if ( $_REQUEST['action'] == 'activate' ) {

		$sql = "UPDATE " . MDS_DB_PREFIX . "lang set is_active='Y' where lang_code='" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['code'] ) . "' ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	}

	if ( $_REQUEST['action'] == 'deactivate' ) {

	$sql = "UPDATE " . MDS_DB_PREFIX . "lang set is_active='N' where lang_code='" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['code'] ) . "' ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	}

	if ( $_REQUEST['action'] == 'default' ) {

	$sql = "UPDATE " . MDS_DB_PREFIX . "lang set is_default='N' ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	$sql = "UPDATE " . MDS_DB_PREFIX . "lang set is_default='Y' where lang_code='" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['code'] ) . "' ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	}

	if ( $_REQUEST['action'] == 'delete' ) {

		$sql = "DELETE FROM " . MDS_DB_PREFIX . "lang WHERE lang_code='" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['code'] ) . "' ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	}
}

if ( isset( $_REQUEST['submit'] ) && $_REQUEST['submit'] != '' ) {

	$error = validate_input();

	if ( $error == '' ) {
		$image_sql  = '';
		$data       = '';
		$image_file = '';
		$mime_type  = '';
		if ( isset( $_FILES['lang_image']['tmp_name'] ) && $_FILES['lang_image']['tmp_name'] != '' ) {
			$data       = base64_encode( fread( fopen( $_FILES['lang_image']['tmp_name'], "r" ), $_FILES['lang_image']['size'] ) );
			$image_file = $_FILES['lang_image']['name'];
			$mime_type  = $_FILES['lang_image']['type'];

			$image_sql = "image_data='$data', mime_type='$mime_type', lang_image='$image_file',";
		}

		// Check image file for length
		if ( strlen( $image_file ) > 32 ) {
			echo '<strong>Sorry, that image is too large! You have to use a tiny image.</strong><br /><br />';
		} else {

			if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'edit' ) {
				$sql = "UPDATE `" . MDS_DB_PREFIX . "lang` SET name='" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['name'] ) . "', " . mysqli_real_escape_string( $GLOBALS['connection'], $image_sql ) . " lang_filename='" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['lang_filename'] ) . "' WHERE `lang_code`='" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['lang_code'] ) . "' ";
			} else {
				$sql = "INSERT INTO `" . MDS_DB_PREFIX . "lang` ( `lang_code` , `lang_filename` , `lang_image` , `is_active` , `name` , `image_data`, `mime_type`, `is_default`, `charset` ) VALUES ('" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['lang_code'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['lang_filename'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $image_file ) . "', 'Y', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['name'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $data ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $mime_type ) . "', 'N', '')";
			}
			//echo "Temp file is: ".$_FILES['lang_image']['tmp_name']."<br>";
			//echo "$sql";

			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		}

		$_REQUEST['new']  = '';
		$_REQUEST['edit'] = '';

		// reload available langs..

		global $AVAILABLE_LANGS;
		global $LANG_FILES;
		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "lang ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
			$AVAILABLE_LANGS [ $row['lang_code'] ] = $row['name'];
			$LANG_FILES [ $row['lang_code'] ]      = $row['lang_filename'];
		}

		// update category translations
		// (copy English to new lang)
		//format_cat_translation_table (0);

		// update code translations
		// (copy English to new lang)

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "form_fields WHERE `field_type`='RADIO' or `field_type`='CHECK' or `field_type`='MSELECT' or `field_type`='SELECT'  ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
			format_codes_translation_table( $row['field_id'] );
		}

		// update forms
		// (copy English to new lang)

		format_field_translation_table( 1 );
	}
}

?>

    <table border="0" cellSpacing="1" cellPadding="3" bgColor="#d9d9d9">
        <tr bgColor="#eaeaea">
            <td><b><font size="2">Language</b></font></td>
            <td><b><font size="2">Code</b></font></td>
            <td><b><font size="2">File</b></font></td>
            <td><b><font size="2">Image</b></font></td>
            <td><b><font size="2">Active</b></font></td>
            <td><b><font size="2">Tool</b></font></td>
        </tr>
		<?php
		$result = mysqli_query( $GLOBALS['connection'], "select * FROM " . MDS_DB_PREFIX . "lang " ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {

			?>

            <tr bgcolor="#ffffff">

                <td><font size="2"><?php echo $row['name']; ?></font></td>
                <td><font size="2"><?php echo $row['lang_code']; ?></font></td>
                <td><font size="2"><?php echo $row['lang_filename']; ?></font></td>
                <td><font size="2"><img alt="<?php echo $row['lang_code']; ?>" src="lang_image.php?code=<?php echo $row['lang_code']; ?>"/></font></td>
                <td><font size="2">  <?php if ( $row['is_active'] == 'Y' ) { ?><IMG SRC="images/active.gif" WIDTH="16" HEIGHT="16" BORDER="0" ALT=""><?php } else { ?><IMG SRC="images/notactive.gif" WIDTH="16" HEIGHT="16" BORDER="0" ALT=""><?php }; ?> <?php if ( $row['is_active'] != 'Y' ) { ?> [<a href="language.php?action=activate&code=<?php echo $row['lang_code']; ?>">Activate</a>] <?php }
						if ( $row['is_active'] == 'Y' ) { ?> [<a href="language.php?action=deactivate&code=<?php echo $row['lang_code']; ?>">Deactivate</a>] <?php } ?>[<a href="language.php?action=edit&code=<?php echo $row['lang_code']; ?>">Edit</a>] <?php if ( $row['is_default'] != 'Y' ) { ?> [
                            <a onclick=" return confirmLink(this, 'Delete, are you sure?') " href="language.php?action=delete&code=<?php echo $row['lang_code']; ?>">Delete</a>] <?php } ?>
                        [<a href="language.php?action=default&code=<?php echo $row['lang_code']; ?>"><?php if ( $row['is_default'] == 'N' ) {
								echo "Set Default";
							} ?></a> <?php if ( $row['is_default'] == 'Y' ) {
							echo "Default";
						}; ?>]
                    </font></td>
                <td>
                    <font size="2"><a href="translation_tool.php?target_lang=<?php echo $row['lang_code']; ?>">Translation / Editing Tool</a>

                </td>

            </tr>

			<?php
		}
		?>
    </table>
    <input type="button" value="New Language..." onclick="mds_load_page('language.php?new=1', true)">
    <p>
        Note: Before adding a new language, please copy english_default.php and name this file to the language of your choice. Eg copy english_default.php to spanish.php.
        <br />These files are located at <?php echo esc_html(\MillionDollarScript\Classes\Utility::get_upload_path() . "languages"); ?>
    </p>
    <p>
        Please modify the langauge files from the web using the editing tool above.
    </p>
    <hr>
<?php

if ( isset( $error ) && $error != '' ) {
	echo "<b><font color='red'>ERROR:</font></b> Cannot save langauge into database.<br>";
	echo $error;
}

function lang_file_options() {
	$dh = opendir( \MillionDollarScript\Classes\Utility::get_upload_path() . "languages" );
	while ( ( $file = readdir( $dh ) ) !== false ) {
		if ( isset( $_REQUEST['lang_filename'] ) && $_REQUEST['lang_filename'] == $file ) {
			$sel = " selected ";
		} else {
			$sel = "";
		}
		if ( ( $file != '.' ) && ( $file != '..' ) && ( $file != 'lang.php' ) && ( $file != 'english_default.php' ) && ( $file != 'index.html' ) ) {
			echo "<option value='$file' $sel>$file</option>\n";
		}
	}
	closedir( $dh );
}

$disabled = "";
if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'edit' ) {

	$code = $_REQUEST['code'] ?? ( $_REQUEST['lang_code'] ?? '' );

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "lang WHERE `lang_code`='" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['code'] ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result, MYSQLI_ASSOC );

	$_REQUEST['name']          = $row['name'];
	$_REQUEST['lang_code']     = $row['lang_code'];
	$_REQUEST['lang_filename'] = $row["lang_filename"];
	//$_REQUEST['lang_image'] = "lang_image";
	//$_REQUEST['charset'] = $row["charset"];

	$disabled = " disabled ";
}

?>

    <form enctype="multipart/form-data" method="post" action="language.php">
<?php

if ( isset( $_REQUEST['new'] ) && $_REQUEST['new'] == '1' ) {
	echo "<h4>New Language:</h4>";
	//echo "<p>Note: Make sure that you create a file for your new language in the /lang directory.</p>";
}
if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'edit' ) {
	echo "<h4>Edit Language:</h4>";
}

if ( ( isset( $_REQUEST['new'] ) && $_REQUEST['new'] != '' ) || ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'edit' ) ) {

	$action    = $_REQUEST['action'] ?? '';
	$lang_code = $_REQUEST['lang_code'] ?? '';
	$name      = $_REQUEST['name'] ?? '';
	?>
    <input type="hidden" value="<?php echo htmlspecialchars( $action ); ?>" name="action">
    <input type="hidden" value="<?php echo htmlspecialchars( $lang_code ); ?>" name="lang_code">
    <table border="0" cellSpacing="1" cellPadding="3" bgColor="#d9d9d9">
        <tr bgcolor="#ffffff">
            <td><font size="2">Language Name:</font></td>
            <td><input size="30" type="text" name="name" value="<?php echo htmlspecialchars( $name ); ?>"/> eg. English</td>
        </tr>
        <tr bgcolor="#ffffff">
            <td><font size="2">Language Code:</font></td>
            <td><input <?php echo $disabled; ?> size="2" type="text" name="lang_code" value="<?php echo htmlspecialchars( $lang_code ); ?>"/> eg. EN</td>
        </tr>
        <tr bgcolor="#ffffff">
            <td><font size="2">Language File:</font></td>
            <td><select name="lang_filename">
                    <option></option><?php lang_file_options(); ?></td>
        </tr>
        <tr bgcolor="#ffffff">
            <td><font size="2">Image:</font></td>
            <td><input size="15" type="file" name="lang_image" value=""></td>
        </tr>
		<?php
		/*
<tr bgcolor="#ffffff" ><td><font size="2">Parameter for setlocale() function:</font></td><td><input size="15" type="text" name="charset" value="<?php echo $_REQUEST['charset']; ?>"><font size="2">(List of valid locale strings for windows can be found at: <a href="http://msdn.microsoft.com/library/default.asp?url=/library/en-us/vclib/html/_crt_language_strings.asp" target="new_">http://msdn.microsoft.com/library/default.asp?url=/library/en-us/vclib/html/_crt_language_strings.asp</a>. Documentation of setlocale function available at: <a href="http://php.net/setlocale">http://php.net/setlocale</a> Leave this field blank if unsure.)</a></td></tr>
		*/
		?>
    </table>
    <input type="submit" name="submit" value="Submit">
    </form>
	<?php
}
