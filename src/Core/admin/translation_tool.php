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

ini_set( "session.use_trans_sid", false );

require_once __DIR__ . "/../include/init.php";

require( 'admin_common.php' );

$label = array();

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "lang WHERE lang_code='" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['target_lang'] ) . "' ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
$row = mysqli_fetch_array( $result );

$lang_filename = $row['lang_filename'];
$lang_name     = $row['name'];
echo "lang filename: $lang_filename ";

require( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/english_default.php" );
$source_label = $label; // default english labels

$dest_label = "";
if ( file_exists( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/" . $lang_filename ) ) {
	require( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/" . $lang_filename );
	$dest_label = $label; // dest labels
}

if ( isset( $_REQUEST['save'] ) && $_REQUEST['save'] != '' ) {
	$out = "<?php\n";
	$out .= 'global $label;' . "\n";
	foreach ( $source_label as $key => $val ) {
		// replace wrongly escaped double quotes
		$_REQUEST[ $key ] = str_replace( '\\"', '"', $_REQUEST[ $key ] );

		// add slashes
		$value = filter_var( $_REQUEST[ $key ], FILTER_SANITIZE_ADD_SLASHES );

		// replace wrongly placed multiple slashes with a single slash to fix any corrupt language files
		$_REQUEST[ $key ] = preg_replace( '/\\\\{2,}/', '\\', $_REQUEST[ $key ] );

		// format for output
		$out .= "\$label['$key']='" . $value . "'; \n";

		// save value to destination
		$dest_label[ $key ] = $value;
	}
	$out     .= "?>\n";
	$handler = fopen( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/" . $lang_filename, "w" );
	fputs( $handler, $out );
}

?>

<h3>
    Language Translation tool.</h3>
<b>IMPORTANT:</b> Backup your language files before using this tool! This tool will overwrite any code in the target file with machine-generated code.<br>
<pre>
INSTRUCTIONS

1. The strings on the left are the original English strings. 
The strings on the right are for you to edit.
2. Clicking any of the Save buttons saves all the fields in the from.
You may click these at any time to Save the entire form.
3. Some fields have variables such as %SITE_NAME%. These variables get substituted.
Check the original string on the left to see what variables are available.
4. HTML is allowed.
5. If you want to use symbols such as &gt; &lt; or &amp;,
be sure to write them as HTML entities: &amp;gt; &amp;lt; and &amp;amp;
</pre>
<?php

if ( ! is_writeable( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/" . $lang_filename ) ) {
	print ( "<span style='color:red'><b>Warning:</b></span> The file " . \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/" . $lang_filename . " is not writable. You must give it write premissions for changes to take effect. You may set back to read-only permissions after saving changes.<br>" );
}

?>
<form method="POST" name="form1" action="translation_tool.php">

    <input type="hidden" name="target_lang" value="<?php echo $_REQUEST['target_lang'] ?>">

    <table style="margin:0 auto;width:calc(100% - 205px);border:none;padding:3px;background:#d9d9d9;">
        <tr style="background:#eaeaea">
            <td><b>Source Language: English (Factory standard english_default.php)</b><br><br></td>
            <td><b>Target Language: <?php echo $lang_name; ?> (<?php echo $lang_filename; ?>)</b><br><br></td>
        </tr>

		<?php

		$i        = 0;
		$bg_color = "";
		foreach ( $source_label as $key => $val ) {
			$i ++;

			$val = stripslashes( $val );
			if ( $bg_color == "#ffffff" ) {
				$bg_color = "#FFFFff";
			} else {
				$bg_color = "#ffffff";
			}

			?>
            <tr style="background:#E8E8E8">
                <td colspan="2"><small><b><?php echo $key; ?></b></small><br>
                    <span style="font-size: 10px; white-space: normal;"><?php $str = highlight_string( "<?php " . ( $val ) . " ?>", true ); ?><span>
                </td>

            </tr>
            <tr style="background:<?php echo $bg_color; ?>">
                <td style="vertical-align: top;max-width:500px;"><?php
					if ( strpos( $key, 'email_temp' ) ) {
						echo "<pre>" . htmlentities( $val ) . "</pre><br>";
					} else {
						echo "" . htmlentities( $val ) . "<br>";
					}
					?></td>
                <td style="vertical-align: top;max-width:500px;">
	                <textarea
                            style="font-family: Arial,sans-serif; font-size: 12px;"
                            cols="90"
                            rows="15"
                            name='<?php echo $key ?>'
                    ><?php
		                // replace wrongly placed multiple slashes with a single slash to fix any corrupt language files
		                $text = preg_replace( '/\\\\{2,}/', '\\', $dest_label[ $key ] );

		                $text = stripslashes( $text );
		                echo $text;
		                ?></textarea>
                </td>
            </tr>
			<?php
			if ( $i > 5 ) {

				echo "<tr style='background:#BDD5E6'><td></td><td><input type='submit' name='save' value='Save'></td></tr>";
				$i = 0;
			}
		}

		if ( $i > 0 ) {
			?>
            <tr style='background:#BDD5E6'>
                <td></td>
                <td><input type='submit' name='save' value='Save'></td>
            </tr>
			<?php
		}
		?>
    </table>
</form>
