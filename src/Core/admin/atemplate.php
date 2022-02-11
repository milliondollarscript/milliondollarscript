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

require( "admin_common.php" );
require_once( "../include/ads.inc.php" );

$mode = isset( $_REQUEST['mode'] ) ? $_REQUEST['mode'] : 'view';

?>

    <div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000; "></div>
    <b>[Ads Template] </b><span style="background-color: <?php if ( $mode != 'edit' ) {
	echo "#FFFFff";
} ?>; border-style:outset; padding: 5px;"><a href="aform.php?mode=view">View Form</a></span> <span style="background-color:  <?php if ( $mode == 'edit' && ( ! isset( $_REQUEST['NEW_FIELD'] ) || $_REQUEST['NEW_FIELD'] == '' ) ) {
	echo "#FFFFCC";
} ?>; border-style:outset; padding: 5px;"><a href="aform.php?mode=edit">Edit Fields</a></span> <span style="background-color: <?php if ( $mode == 'edit' && ( isset( $_REQUEST['NEW_FIELD'] ) && $_REQUEST['NEW_FIELD'] != '' ) ) {
	echo "#FFFFCC";
} ?>; border-style:outset; padding: 5px;"><a href="aform.php?NEW_FIELD=YES&mode=edit">New Field</a></span>&nbsp; &nbsp; <span style="background-color: #ffffcc; border-style:outset; padding: 5px;"><a href="atemplate.php">Edit Template</a></span> <span style="background-color: #F2F2F2; border-style:outset; padding: 5px;"><a href="alist2.php">Ad List</a></span>

    <hr>
    Here you can edit the template for the ads. The ads are displayed when a mouse is moved over the pixels. <b>You will need to edit this template after inserting or removing a field on the Ad Form.</b><p>The rules are simple... if you want <b>to display to the value of a field, put two % signs around the field's template tag</b>, like this %TEMPLATE_TAG%. If you want
    <b>to display the field's label, put two $ signs around the field's template tag</b>, like this $TEMPLATE_TAG$. Use normal HTML to format the ad.</p>

    <hr>

<?php

global $AVAILABLE_LANGS;
echo "Current Language: [" . $_SESSION['MDS_LANG'] . "] Select language:";

?>

    <form name="lang_form" action="atemplate.php">
        <input type="hidden" name="field_id" value="<?php echo $field_id ?? 0; ?>"/>
        <input type="hidden" name="mode" value="<?php echo $mode; ?>"/>
        <select name='lang' onChange="mds_submit(this)">
			<?php
			foreach ( $AVAILABLE_LANGS as $key => $val ) {
				$sel = '';
				if ( $key == $_SESSION['MDS_LANG'] ) {
					$sel = " selected ";
				}
				echo "<option $sel value='" . $key . "'>" . $val . "</option>";
			}

			?>

        </select>
    </form>
<?php

$lang_filename = $LANG_FILES[ $_SESSION['MDS_LANG'] ];
if ( ! is_writable( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/$lang_filename" ) ) {
	echo \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/$lang_filename is not writeable. Please give permission for writing to this file before editing the template.<br>";
}

global $label;

if ( isset( $_REQUEST['save'] ) && $_REQUEST['save'] ) {

	// save the file.

	include( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/english_default.php" );
	$source_label = $label; // default english labels
	include( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/" . $lang_filename );
	$dest_label = $label; // dest labels

	$out = "<?php\n";
	foreach ( $source_label as $key => $val ) {
		//$source_label[$key] = addslashes($dest_label[$key]);
		if ( $key == 'mouseover_ad_template' ) {
			$dest_label[ $key ] = stripslashes( $_REQUEST['mouseover_ad_template'] );
		}
		$source_label[ $key ] = str_replace( "'", "\'", $dest_label[ $key ] ); // slash it
		$out                  .= "\$label['$key']='" . $source_label[ $key ] . "'; \n";
	}
	$out .= "?>\n";

	//echo $out;

	$handler = fopen( \MillionDollarScript\Classes\Utility::get_upload_path() . "/languages/" . $lang_filename, "w" );
	fputs( $handler, $out );
	fclose( $handler );
}

?>
    <form method="POST" action="atemplate.php">
        <textarea name='mouseover_ad_template' rows=10 cols=50><?php echo escape_html( stripslashes( $label['mouseover_ad_template'] ) ); ?></textarea><br>
        <input type="submit" name='save' value="Save">
    </form>

    <hr>
    <p>Template Preview:</p>

<?php

foreach ( $ad_tag_to_field_id as $field ) {
	$prams[ $field['field_id'] ]    = 'example_value';
	$prams[ $field['field_label'] ] = 'example_label';
}

//print_r($ad_tag_to_field_id);

echo assign_ad_template( $prams );

?>