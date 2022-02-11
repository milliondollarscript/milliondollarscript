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

if ( ! isset( $_REQUEST['field_id'] ) ) {
	echo "Select the code group that you would like to edit:<p>";
	echo "Ad Form:";

	$sql = "select * FROM `" . MDS_DB_PREFIX . "form_fields` WHERE form_id='1' AND (field_type='CHECK' OR field_type='RADIO' OR field_type='SELECT' OR field_type='MSELECT' ) ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	if ( mysqli_num_rows( $result ) == 0 ) {
		echo " (0 codes)";
	}

	echo "<ul>";

	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
		format_codes_translation_table( $row['field_id'] );
		?>
        <li><a href="" onclick="window.open('maintain_codes.php?field_id=<?php echo intval( $row['field_id'] ) ?>', '', 'toolbar=no,scrollbars=yes,location=no,statusbar=no,menubar=no,resizable=1,width=400,height=500,left = 150,top = 150');return false;"><?php echo htmlspecialchars( $row['field_label'], ENT_QUOTES ); ?></a></li>
		<?php
	}

	echo "</ul>";
	exit;
}

$field_id    = intval( $_REQUEST['field_id'] );
$code        = $_REQUEST['code'];
$description = $_REQUEST['description'];
$modify      = $_REQUEST['modify'];

function can_delete_code( $field_id, $code ) {

	$tables = array( MDS_DB_PREFIX . "ads" );
	foreach ( $tables as $table ) {

		$sql  = "SHOW COLUMNS FROM " . $table;
		$cols = mysqli_query( $GLOBALS['connection'], $sql );

		while ( $c_row = mysqli_fetch_row( $cols ) ) {
			if ( $c_row[0] == $field_id ) {

				$sql    = "SELECT * FROM " . $table . " WHERE `" . intval( $field_id ) . "` like '%" . mysqli_real_escape_string( $GLOBALS['connection'], $code ) . "%' ";
				$result = mysqli_query( $GLOBALS['connection'], $sql );
				if ( mysqli_num_rows( $result ) == 0 ) {
					return true;
				} else {
					return false;
				}
			}
		}
	}

	return false;
}

global $f2;
echo $f2->get_doc(); ?>

</head>

<body>
<?php

if ( $_REQUEST['action'] == 'delete' ) {

	$field_id = $_REQUEST['field_id'];
	$code     = $_REQUEST['code'];

	$sql = "DELETE from `" . MDS_DB_PREFIX . "codes` where `field_id`=" . intval( $field_id ) . " AND `code`='" . mysqli_real_escape_string( $GLOBALS['connection'], $code ) . "' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	$sql = "DELETE from `" . MDS_DB_PREFIX . "codes_translations` where `field_id`=" . intval( $field_id ) . " AND `code`='" . mysqli_real_escape_string( $GLOBALS['connection'], $code ) . "' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
}

if ( $_REQUEST['do_change'] != '' ) {

	echo 'Changing id...';

	///$new_description = 'ok';

//	print_r($_REQUEST);

	$error = validate_code( $_REQUEST['field_id'], $_REQUEST['new_code_id'], 'ok' );

	if ( $error == '' ) {
		change_code_id( $_REQUEST['field_id'], $_REQUEST['code'], $_REQUEST['new_code_id'] );
		echo 'ok<br>';
	} else {

		?>

        <b><span style="color:#ff0000">ERROR:</span> Cannot save new code because:</b><br>
		<?php
		echo $error . "<br>";

		$_REQUEST['action'] = 'change';
	}
}

if ( $_REQUEST['action'] == 'change' ) {

	?>

    You can change the code id. Since the options are sorted by code id, you can get the option list to sort in a particular order by changing the code id. <b>Note: changing the code id can be a large / risky operation if you have many records in the database. This is because the script needs to update each record individually. Please be patient and do not close this window until the process is complete.</b>

    <form method='post' action='maintain_codes.php'>

        <input type="hidden" name="field_id" value="<?php echo intval( $field_id ); ?>">
        <input type="hidden" name="code" value="<?php echo htmlspecialchars( $code, ENT_QUOTES ); ?>"/>
        Current code id: <?php echo htmlspecialchars( $code, ENT_QUOTES ); ?><br>
        Enter new code id: <label>
            <input type="text" name="new_code_id" value="">
        </label><br>
        <input type='submit' value='Change' name='do_change'>
    </form>
    <hr>
	<?php
}

global $AVAILABLE_LANGS;
echo "Current Language: [" . htmlspecialchars( $_SESSION['MDS_LANG'], ENT_QUOTES ) . "] Select language:";
?>
<form name="lang_form">
    <input type="hidden" name="field_id" value="<?php echo intval( $field_id ); ?>"/>
    <input type="hidden" name="mode" value="<?php echo htmlspecialchars( $modify, ENT_QUOTES ); ?>"/>
    <select name='lang' onChange="document.lang_form.submit()">
		<?php
		foreach ( $AVAILABLE_LANGS as $key => $val ) {
			$sel = '';
			if ( $key == $_SESSION['MDS_LANG'] ) {
				$sel = " selected ";
			}
			echo "<option $sel value='" . htmlspecialchars( $key, ENT_QUOTES ) . "'>" . htmlspecialchars( $val, ENT_QUOTES ) . "</option>";
		}

		?>

    </select>
</form>

<?php

#echo "Field:[".$field_id."] Code[".$code."] Descr[".$description."]";
#echo "New Code[".$new_code."] new Descr[".$new_description."]";
?>

<form method="POST" action="maintain_codes.php">

    <p>
    <table border="1">
        <tr>
            <td><b>Code</b></td>
            <td><b>Description</b></td>
            <td></td>
        </tr>
		<?php

		if ( $modify == "yes" ) {

			modify_code( $field_id, $code, $description );
			$code = '';
			?>

			<?php
		}

		function validate_code( $field_id, $new_code, $new_description ) {
			$error = "";
			if ( $new_code == '' ) {
				$error .= "- Code is blank<br>";
			}
			if ( $new_description == '' ) {
				$error .= "- Description is blank<br>";
			}

			if ( $new_code != '' ) {

				$sql = "SELECT * from " . MDS_DB_PREFIX . "codes where field_id=" . intval( $field_id ) . " AND code like '%" . mysqli_real_escape_string( $GLOBALS['connection'], $new_code ) . "%' ";
				$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

				if ( mysqli_num_rows( $result ) > 0 ) {
					$error .= "- The new Code is too similar to an already existing code. Please try to come up with a different code.<br>";
				}
			}

			return $error;
		}

		if ( $_REQUEST['new_code'] != '' ) {

			$error = validate_code( $field_id, $_REQUEST['new_code'], $_REQUEST['new_description'] );
			if ( $error == '' ) {
				insert_code( $field_id, $_REQUEST['new_code'], $_REQUEST['new_description'] );
				$_REQUEST['new_code']        = '';
				$_REQUEST['new_description'] = '';

				?>

				<?php
			} else {
				?>
                <b><font color="#ff0000">ERROR:</font> Cannot save new code because:</b><br>
				<?php
				echo $error;
			}
		}

		if ( $_SESSION['MDS_LANG'] == '' ) {
			$sql = "SELECT `code`, `description` FROM `" . MDS_DB_PREFIX . "codes` WHERE field_id='" . intval( $field_id ) . "'";
		} else {
			$sql = "SELECT `code`, `description` FROM `" . MDS_DB_PREFIX . "codes_translations` WHERE field_id='" . intval( $field_id ) . "' AND `lang`='" . get_lang() . "' ";
		}

		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( $sql . mysqli_error( $GLOBALS['connection'] ) );

		while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {

			if ( $code == $row['code'] ) {
				echo '<tr bgcolor="FFFFCC">' . "\n";
			} else {
				echo "<tr>\n";
			}

			echo "<td>\n";

			echo '<a href="maintain_codes.php?field_id=' . intval( $field_id ) . '&code=' . urlencode( $row['code'] ) . '">' . "\n";
			echo htmlspecialchars( $row['code'], ENT_QUOTES );
			echo '</a>' . "\n";
			echo "</td>\n";
			echo "<td>\n";
			if ( $code == $row['code'] ) {
				echo '<input name="description" type="text" size="30" value="' . htmlspecialchars( $row['description'], ENT_QUOTES ) . '">';
				echo '<input name="modify" type="hidden" value="yes">';
				echo '<input name="code" type="hidden" value="' . htmlspecialchars( $row['code'], ENT_QUOTES ) . '">';
				echo '<input name="field_id" type="hidden" value="' . intval( $field_id ) . '">';
			} else {
				echo $row['description'];
			}
			echo "</td>\n";
			echo "<td>\n";
			$disabled = "";
			$n        = "";
			if ( ! can_delete_code( $field_id, $row['code'] ) ) {
				$disabled = " disabled ";
				$n        = "*";
			}
			echo '<input type="button" onclick="window.location=\'maintain_codes.php?action=delete&field_id=' . intval( $field_id ) . '&code=' . urlencode( $row['code'] ) . '\'" name="" value="Delete" ' . $disabled . ' >' . $n;
			echo '&nbsp;<input type="button" onclick="window.location=\'maintain_codes.php?action=change&field_id=' . intval( $field_id ) . '&code=' . urlencode( $row['code'] ) . '\'" name="" value="Change id" >';
			echo "</td>\n";

			echo "</tr>\n";
		}
		?>
        <tr>
            <td><input name="new_code" type="text" size="4" value="<?php echo htmlspecialchars( $_REQUEST['new_code'], ENT_QUOTES ); ?>"></td>
            <td><input name="new_description" type="text" value="<?php echo htmlspecialchars( $_REQUEST['new_description'], ENT_QUOTES ); ?>" size="30">
				<?php
				if ( $field_id != '' ) {
					echo '<input name="field_id" type="hidden" value="' . intval( $field_id ) . '">';
				}
				?>

            </td>
            <td>&lt;-new code</td>
        <tr>
        <tr>
            <td colspan="2">
                <input type="submit" name="save" value="Save">
            </td>
        </tr>

    </table>
</form>

<?php

?>

<center><input type="button" name="" value="Close" onclick="window.opener.location.reload();window.close()"></center>

<i>* = Cannot delete because Code is in use by a record. Delete / alter the record(s) before deleting the Code.</i>
</body>

</html>

