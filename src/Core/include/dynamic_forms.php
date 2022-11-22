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

require_once( "code_functions.php" );

global $f2;

function format_field_translation_table( $form_id ) {
	global $AVAILABLE_LANGS;
	$form_id = intval( $form_id );

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "form_fields WHERE `form_id`=$form_id ";
	$f_result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
	while ( $f_row = mysqli_fetch_array( $f_result, MYSQLI_ASSOC ) ) {

		$field_id = intval( $f_row['field_id'] );
		foreach ( $AVAILABLE_LANGS as $key => $val ) {
			$key = preg_replace( '/[^a-zA-Z0-9]/', '', $key );
			$sql = "SELECT " . MDS_DB_PREFIX . "form_field_translations.field_id, " . MDS_DB_PREFIX . "form_field_translations.field_label, lang FROM " . MDS_DB_PREFIX . "form_field_translations, " . MDS_DB_PREFIX . "form_fields WHERE " . MDS_DB_PREFIX . "form_field_translations.field_id=" . MDS_DB_PREFIX . "form_fields.field_id AND " . MDS_DB_PREFIX . "form_field_translations.field_id='" . $field_id . "' AND lang='$key' ";
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
			if ( mysqli_num_rows( $result ) == 0 ) {
				$sql = "INSERT INTO `" . MDS_DB_PREFIX . "form_field_translations` (`field_id`, `lang`, `field_label`, `error_message`, `field_comment`)
VALUES ('" . $field_id . "', '" . $key . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $f_row['field_label'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $f_row['error_message'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $f_row['field_comment'] ) . "') 
ON DUPLICATE KEY UPDATE 
`field_id` = '" . $field_id . "',
`lang` = '" . $key . "',
`field_label` = '" . mysqli_real_escape_string( $GLOBALS['connection'], $f_row['field_label'] ) . "',
`error_message` = '" . mysqli_real_escape_string( $GLOBALS['connection'], $f_row['error_message'] ) . "',
 `field_comment` = '" . mysqli_real_escape_string( $GLOBALS['connection'], $f_row['field_comment'] ) . "';";
				mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
			}
		}
	}
}

function get_template_field_id( $tmpl, $form_id ) {
	//global $tag_to_field_id;
	$tag_to_field_id = get_tag_to_field_id( $form_id );

	return $tag_to_field_id[ $tmpl ]['field_id'];
}

function get_template_value( $tmpl, $form_id, $row = null ) {
	global $prams, $purifier;

	$error = '<b>Configuration error: Failed to bind the "' . $purifier->purify( $tmpl ) . '" template tag. Tag not defined.</b> <br> ';

	$tag_to_field_id = get_tag_to_field_id( $form_id );

	if ( func_num_args() > 2 ) {
		$admin = func_get_arg( 2 );
	}

	if ( ! isset( $tag_to_field_id[ $tmpl ] ) || ! isset( $tag_to_field_id[ $tmpl ]['field_id'] ) ) {
		echo $error;

		return "";
	}

	$field_id = $tag_to_field_id[ $tmpl ]['field_id'];

	if ( ! isset( $prams[ $field_id ] ) ) {
		echo $error;

		return "";
	}

	$val = $prams[ $field_id ];

	if ( isset( $tag_to_field_id[ $tmpl ] ) && isset( $tag_to_field_id[ $tmpl ]['field_type'] ) ) {
		switch ( $tag_to_field_id[ $tmpl ]['field_type'] ) {
			case "RADIO":
				$val = getCodeDescription( $field_id, $val );
				break;
			case "SELECT":
				$val = getCodeDescription( $field_id, $val );
				break;
			case "MSELECT":
			case "CHECK":
				$vals = explode( ",", $val );
				$str  = $comma = "";
				foreach ( $vals as $v ) {
					$str   .= $comma . getCodeDescription( $field_id, $v );
					$comma = ", ";
				}
				$val = $str;
				break;
			case "DATE":
			case "DATE_CAL":

				if ( $val != '0000-00-00 00:00:00' ) {
					$val = get_local_datetime( $val . " GMT" );
					$val = get_formatted_date( $val );
				} else {
					$val = '';
				}
				break;
			case "TIME":
				$val = get_local_datetime( $val . " GMT" ); // the time is always stored as GMT

				break;
			case "TEXT":
				$val = str_replace( "<", "&lt;", $val ); // block html tags in text fields
				$val = str_replace( ">", "&gt;", $val );
				//$val = htmlentities($val);
				break;
		}
	}

	if ( $field_id == '' ) {
		echo $error;
	}

	return $purifier->purify( $val );
}

function get_template_field_label( $tmpl, $form_id ) {
	//global $prams;
	global $tag_to_field_id;

	$tag_to_field_id = get_tag_to_field_id( $form_id );

	$field_label = $tag_to_field_id[ $tmpl ]['field_label'];

	return $field_label;
}

function generate_q_string( $form_id ) {
	global $f2;

	if ( $_REQUEST['action'] == '' ) {
		return false;
	}

	global $tag_to_search;
	$tag_to_search = get_tag_to_search( $form_id );

	$q_string = "&action=search";

	foreach ( $tag_to_search as $key => $val ) {
		if ( is_array( $_REQUEST[ $val['field_id'] ] ) ) {

			$q_string .= ( "&" . $val['field_id'] . "[]=" . implode( ",", $_REQUEST[ $val['field_id'] ] ) );
		} else {
			$q_string .= ( "&" . $val['field_id'] . "=" . $_REQUEST[ $val['field_id'] ] );
		}
	}

	return $q_string;
}

function echo_order_arrows( $row ) {

	echo '
    <div align="left" style="margin: 0">
        <table align="left" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <a href="' . htmlentities( $_SERVER['PHP_SELF'] ) . '?mode=edit&action=move_up&field_id=' . $row['field_id'] . '&field_sort=' . $row['field_sort'] . '&section=' . $row['section'] . '">
                        <IMG SRC="images/sortup.gif" WIDTH="9" align="top" HEIGHT="13" BORDER="0" ALT="Move Up">
                    </a>
                </td>
            </tr>
            <tr>
                <td>
                    <a href="' . htmlentities( $_SERVER['PHP_SELF'] ) . '?mode=edit&action=move_down&field_id=' . $row['field_id'] . '&field_sort=' . $row['field_sort'] . '&section=' . $row['section'] . '">
                        <IMG SRC="images/sortdown.gif" WIDTH="9" HEIGHT="13" BORDER="0" ALT="Move Down">
                    </a>
                </td>
            </tr>
        </table>
    </div>
    ';
}

function mds_display_form( $form_id, $mode, $prams, $section ) {
	global $f2, $label, $admin, $purifier;

	// filter vars
	$form_id    = intval( $form_id );
	$field_id   = isset( $_REQUEST['field_id'] ) ? intval( $_REQUEST['field_id'] ) : null;
	$section    = intval( $section );
	$is_hidden  = isset( $_REQUEST['is_hidden'] ) ? $f2->filter( $_REQUEST['is_hidden'] ) : null;
	$is_blocked = isset( $_REQUEST['is_blocked'] ) ? $f2->filter( $_REQUEST['is_blocked'] ) : null;

	$dont_break_table = true;
	if ( func_num_args() > 4 ) {
		$dont_break_table = func_get_arg( 4 );
	}

	$section = preg_replace( '/[^a-zA-Z0-9]/', '', $section );
	$form_id = intval( $form_id );

	$sql = "SELECT " . MDS_DB_PREFIX . "form_field_translations.field_label, " . MDS_DB_PREFIX . "form_fields.*, " . MDS_DB_PREFIX . "form_field_translations.field_comment FROM " . MDS_DB_PREFIX . "form_fields, " . MDS_DB_PREFIX . "form_field_translations WHERE " . MDS_DB_PREFIX . "form_fields.field_id=" . MDS_DB_PREFIX . "form_field_translations.field_id AND lang='" . get_lang() . "' AND section='$section' AND form_id='$form_id' order by field_sort  ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

	if ( ! $dont_break_table ) {
		?>
        <div id="dynamic_form" class="flex-container">
		<?php
	}

	$i = 0;

	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
		$i ++;

		if ( ( $mode == 'edit' || $mode == 'user' ) && ( isset( $field_id ) && $field_id == $row['field_id'] ) ) {
			$bg_selected = ' style="background-color: #FFFFCC;" ';
		} else {
			$bg_selected = '';
		}

		// load init value...
		if ( isset( $prams[ $row['field_id'] ] ) && $prams[ $row['field_id'] ] == '' ) {
			$prams[ $row['field_id'] ] = $row['field_init'];
		}

		if ( ( $row['is_hidden'] == "Y" ) && ( $mode == "view" ) && ! $admin ) {
			# Hidden Fields, do not appear on website (view mode)

		} else if ( $row['field_type'] == "SEPERATOR" ) {
			?>
            <div class="flex-row" <?php echo $bg_selected; ?>>
                <div class="dynamic_form_seperator flex-cell">
                    <b><?php if ( $mode == 'edit' ) {
							echo_order_arrows( $row );
							echo '<a href="' . htmlentities( $_SERVER['PHP_SELF'] ) . '?field_id=' . $row['field_id'] . '&mode=edit"><IMG SRC="../admin/images/edit.gif" WIDTH="16" HEIGHT="16" align="middle" BORDER="0" ALT="-"> ';
						}
						echo $row['field_label']; ?><?php if ( $mode == 'edit' ) {
							echo '</a>';
						} ?></b>
                </div>
            </div>
			<?php
		} else if ( $row['field_type'] == "IMAGE" ) {

			?>
            <div class="flex-row">
                <div class="dynamic_form_2_col_field flex-cell" <?php echo $bg_selected; ?> >
					<?php if ( $mode == 'edit' ) {
						echo_order_arrows( $row );
						echo '<a href="' . htmlentities( $_SERVER['PHP_SELF'] ) . '?field_id=' . $row['field_id'] . '&mode=edit"><IMG SRC="../admin/images/edit.gif" WIDTH="16" HEIGHT="16" align="middle" BORDER="0" ALT="-">';
					}
					echo "<span class='dynamic_form_image_label'>" . $row['field_label'] . "</span><br>";
					if ( $mode == 'edit' ) {
						echo '</a>';
					}
					if ( ( $mode == 'edit' ) && is_reserved_template_tag( $row['template_tag'] ) ) {
						$alt = get_reserved_tag_description( $row['template_tag'] );
						?>
                        <a href="" onclick="alert('<?php echo htmlentities( $alt ); ?>');return false;">
                            <IMG SRC="../admin/images/reserved.gif" WIDTH="13" HEIGHT="13" BORDER="0" ALT="<?php echo $alt; ?>">
                        </a>
						<?php
					}

					if ( $prams[ $row['field_id'] ] != '' ) {
						if ( ( isset( $is_hidden ) && $is_hidden == 'Y' ) || ( ( isset( $is_blocked ) && $is_blocked == 'Y' ) ) ) {
							echo $prams[ $row['field_id'] ];
						} else {

							if ( isset( $_REQUEST[ 'del_image' . $row['field_id'] ] ) && $_REQUEST[ 'del_image' . $row['field_id'] ] != '' ) {
								unlink( UPLOAD_PATH . 'images/' . $prams[ $row['field_id'] ] );
								//@unlink (UPLOAD_PATH.''.$prams[$row['field_id']]);
							}
							if ( file_exists( UPLOAD_PATH . 'images/' . $prams[ $row['field_id'] ] ) ) {

								?>
                                <img alt="" src="<?php echo UPLOAD_HTTP_PATH . 'images/' . $prams[ $row['field_id'] ]; ?>">
							<?php }
                            /*else {
								echo '<IMG SRC="' . UPLOAD_HTTP_PATH . 'images/no-image.gif" WIDTH="150" HEIGHT="150" BORDER="0" ALT="">';
							}*/
						}
					}
//					} else {
//						echo '<IMG SRC="' . UPLOAD_HTTP_PATH . 'images/no-image.gif" WIDTH="150" HEIGHT="150" BORDER="0" ALT="">';
//					}

					if ( ( $mode == 'edit' || $mode == 'user' ) ) {

						// delete image button
						if ( file_exists( UPLOAD_PATH . 'images/' . $prams[ $row['field_id'] ] ) && ( $prams[ $row['field_id'] ] != '' ) ) {

							$image_field_id = $row['field_id'];

							echo "<br><input type='hidden' name='del_image" . $row['field_id'] . "' value=''><input type='button' value='" . $label['delete_image_button'] . "' onclick='document.form1.del_image" . $row['field_id'] . ".value=\"" . $image_field_id . "\"; document.form1.submit()'><br>";
						} else {// upload image form
							echo "<br>" . $label['upload_image'] . '<br> ' . form_image_field( $row['field_id'], $prams[ $row['field_id'] ] );
							if ( $row['field_comment'] != '' ) {
								echo " <br>" . $row['field_comment'] . "";
							}
						}
					}

					?>
                </div>
            </div>

			<?php
		} else if ( $row['field_type'] == "FILE" ) {

			?>

            <div class="flex-row">
                <div class="dynamic_form_2_col_field flex-cell" <?php echo $bg_selected; ?> >
				<span>
				<?php
				if ( $mode == 'edit' ) {
					echo_order_arrows( $row );
					echo '<a href="' . htmlentities( $_SERVER['PHP_SELF'] ) . '?field_id=' . $row['field_id'] . '&mode=edit"><IMG SRC="../admin/images/edit.gif" WIDTH="16" HEIGHT="16" align="middle" BORDER="0" ALT="-">';
				}
				//if ($mode !='view') {
				echo "<span class=\"dynamic_form_image_label\" >" . $row['field_label'] . "</span><br>";
				//}
				if ( $mode == 'edit' ) {
					echo '</a>';
				}
				if ( ( $mode == 'edit' ) && is_reserved_template_tag( $row['template_tag'] ) ) {
					$alt = get_reserved_tag_description( $row['template_tag'] );
					?>
                    <a href="" onclick="alert('<?php echo htmlentities( $alt ); ?>');return false;">
					<IMG SRC="../admin/images/reserved.gif" WIDTH="13" HEIGHT="13" BORDER="0" ALT="<?php echo $alt; ?>">
					</a>
					<?php
				}
				if ( $_REQUEST[ 'del_file' . $row['field_id'] ] != '' ) {
					@unlink( UPLOAD_PATH . 'docs/' . $prams[ $row['field_id'] ] );
					//@unlink (IMG_PATH.''.$prams[$row['field_id']]);
				}

				if ( ( $prams[ $row['field_id'] ] != '' ) && ( file_exists( UPLOAD_PATH . '/docs' . $prams[ $row['field_id'] ] ) ) ) { ?>
                    <a href="<?php echo UPLOAD_HTTP_PATH . '' . $prams[ $row['field_id'] ]; ?>">
					<IMG alt="" src="../images/file.gif" width="20" height="20" border="0" alt="">
						<?php echo $prams[ $row['field_id'] ]; ?>
                    </a> - <?php echo filesize( UPLOAD_PATH . "" . $prams[ $row['field_id'] ] ); ?><?php echo $label['bytes'] . "<br>"; ?>
				<?php } else if ( $mode == 'view' ) {

					echo '<i>' . $label['no_file_uploaded'] . '</i>';
				}
				if ( $mode == 'edit' ) {

					if ( file_exists( UPLOAD_PATH . '/docs/' . $prams[ $row['field_id'] ] ) && ( $prams[ $row['field_id'] ] != '' ) ) {

						//if ($mode != 'view') {

						$image_field_id = $row['field_id'];

						echo "<br><input type='hidden' name='del_file" . $row['field_id'] . "' value=''><input type='button' value='" . $label['delete_file_button'] . "' onclick='document.form1.del_file" . $row['field_id'] . ".value=\"" . $image_field_id . "\"; document.form1.submit()'><br>";
						//}

					} else {
						echo $label['upload_file'] . " " . form_file_field( $row['field_id'], $prams[ $row['field_id'] ] );
						if ( $row['field_comment'] != '' ) {
							echo " <br>" . $row['field_comment'] . "";
						}
					}
				} ?>
					</span>
                </div>
            </div>

			<?php
		} else if ( $row['field_type'] == "NOTE" ) {

			if ( $mode == 'view' ) {

			} else {

				?>
                <div class="flex-row">
                    <div class="dynamic_form_2_col_field flex-cell" <?php echo $bg_selected; ?> ><span class="dynamic_form_note_label"><?php if ( $mode == 'edit' ) {
								echo_order_arrows( $row );
								echo '<a href="' . htmlentities( $_SERVER['PHP_SELF'] ) . '?field_id=' . $row['field_id'] . '&mode=edit"><IMG SRC="../admin/images/edit.gif" WIDTH="16" HEIGHT="16" align="middle" BORDER="0" ALT="-"> ';
							}
							echo $row['field_label']; ?><?php if ( $mode == 'edit' ) {
								echo '</a>';
							}

							if ( ( $mode == 'edit' ) && is_reserved_template_tag( $row['template_tag'] ) ) {
								$alt = get_reserved_tag_description( $row['template_tag'] );
								?>
                                <a href="" onclick="alert('<?php echo htmlentities( $alt ); ?>');return false;">
					<IMG SRC="../admin/images/reserved.gif" WIDTH="13" HEIGHT="13" BORDER="0" ALT="<?php echo $alt; ?>">
					</a>
								<?php
							}
							?></span>
                    </div>
                </div>
				<?php
			}
		} else if ( $row['field_type'] == "MIME" ) {
			// do nothing. It is an extra field for FILE type fields..

		} else {

			if ( $row['field_label'] == '' ) {
				$row['field_label'] = '&nbsp;&nbsp';
			}

			?>
            <div class="flex-row">
                <div class="dynamic_form_field flex-cell" <?php echo $bg_selected; ?>>
					<?php
					if ( $mode == 'edit' ) {
						echo_order_arrows( $row );
						echo '<a href="' . htmlentities( $_SERVER['PHP_SELF'] ) . '?field_id=' . $row['field_id'] . '&mode=edit"><IMG SRC="../admin/images/edit.gif" WIDTH="16" HEIGHT="16" align="middle" BORDER="0" ALT="-">';
					}
					echo $row['field_label'];
					if ( $mode == 'edit' ) {
						echo '</a>';
					}
					if ( $row['is_required'] == 'Y' && $mode != 'view' && $mode == 'user' ) {
						echo "<FONT SIZE='4' COLOR='#FF0000'><b>*</B></FONT>";
					}

					// avoid triggering mod_security by not posting http:// in the form fields
					//					if ( strtolower( $row['field_label'] ) == "url" ) {
					//						echo "<span class=\"httplabel\">http(s)://</span>";
					//					}

					if ( ( $mode == 'edit' ) && is_reserved_template_tag( $row['template_tag'] ) ) {
						$alt = get_reserved_tag_description( $row['template_tag'] );
						?>
                        <a href="" onclick="alert('<?php echo htmlentities( $alt ); ?>');return false;">

                            <IMG SRC="../admin/images/reserved.gif" WIDTH="13" HEIGHT="13" BORDER="0" ALT="<?php echo $alt; ?>">

                        </a>

						<?php
					}

					?><?php if ( ( $mode == 'edit' ) && ( $row['field_type'] == 'BLANK' ) ) {
						echo '<a href="' . htmlentities( $_SERVER['PHP_SELF'] ) . '?field_id=' . $row['field_id'] . '&mode=edit">[]</a>';
					} ?></div>
                <div class="dynamic_form_value flex-cell" <?php echo $bg_selected; ?> >

					<?php

					if ( ( isset( $is_hidden ) && $is_hidden == 'Y' ) || ( ( isset( $is_blocked ) && $is_blocked == 'Y' ) ) ) {
						echo $prams[ $row['field_id'] ]; // display blocked field message

					} else {

						switch ( $row['field_type'] ) {
							case "TEXT":
								if ( $mode == 'view' ) {
									$val = $prams[ $row['field_id'] ];
									$val = escape_html( $val );
									echo $purifier->purify( $val );
								} else {
									$textvalue = "";
									if ( isset( $row['field_id'] ) && isset( $prams[ $row['field_id'] ] ) ) {
										if ( ! empty( $prams[ $row['field_id'] ] ) ) {
											$textvalue = $purifier->purify( $prams[ $row['field_id'] ] );
										}
									}

									echo form_text_field( $row['field_id'], $textvalue, $row['field_width'] );
									if ( $row['field_comment'] != '' ) {
										echo " " . $purifier->purify( $row['field_comment'] ) . "";
									}
								}
								break;
							case "SEPERATOR":
								break;
							case "EDITOR":
								$val = $prams[ $row['field_id'] ];

								if ( $mode == 'view' ) {
									echo $purifier->purify( $val );
								} else {
									if ( $row['field_comment'] != '' ) {
										echo $purifier->purify( $row['field_comment'] ) . "<br>";
									}
									echo form_editor_field( $row['field_id'], $purifier->purify( $val ), $row['field_width'], $row['field_height'] );
								}
								break;
							case "TEXTAREA":
								if ( $mode == 'view' ) {
									//$val = process_for_html_output ($prams[$row['field_id']]);
									$val = escape_html( $prams[ $row['field_id'] ] );
									$val = str_replace( "\n", "<br>", $val );
									echo $purifier->purify( $val );
								} else {
									if ( $row['field_comment'] != '' ) {
										echo $purifier->purify( $row['field_comment'] ) . "<br>";
									}
									echo form_textarea_field( $row['field_id'], $purifier->purify( $prams[ $row['field_id'] ] ), $row['field_width'], $row['field_height'] );
								}
								break;
							case "DATE":
							case "DATE_CAL":
								if ( $mode == 'view' ) {
									if ( $is_blocked == 'Y' ) { // output a string
										echo $purifier->purify( $prams[ $row['field_id'] ] );
									} else { // output a date
										if ( $prams[ $row['field_id'] ] != '0000-00-00 00:00:00' ) {
											echo $purifier->purify( get_formatted_date( $prams[ $row['field_id'] ] ) );
										} else {
											echo "";
										}
									}
								} else {

									if ( $row['field_type'] == 'DATE' ) { // traditional date input
										preg_match( "/(\d+)-(\d+)-(\d+)/", $prams[ $row['field_id'] ], $m );
										// Year - Month - Day (database output format)
										$year  = $m[1];
										$day   = $m[3];
										$month = $m[2];

										form_date_field( $row['field_id'], $day, $month, $year, "dynamic_form_date_style" );
									} else { // scw input

										?>
                                        <input name="<?php echo $row['field_id']; ?>" onclick="scwShow(this,this);" size="10" onfocus="scwShow(this,this);" type="text" value="<?php echo trim_date( $prams[ $row['field_id'] ] );
										?>">

										<?php
									}

									if ( $row['field_comment'] != '' ) {
										echo " " . $purifier->purify( $row['field_comment'] ) . "";
									}
								}
								break;

							case "SELECT":
								if ( $mode == 'view' ) {
									echo $purifier->purify( getCodeDescription( $row['field_id'], $prams[ $row['field_id'] ] ) );
								} else {
									form_select_field( $row['field_id'], $prams[ $row['field_id'] ] );
								}
								if ( $mode == 'edit' ) {
									?>
                                    <a href=""
                                       onclick="window.open('maintain_codes.php?field_id=<?php echo $row['field_id']; ?>', '', 'toolbar=no,scrollbars=yes,location=no,statusbar=no,menubar=no,resizable=1,width=400,height=500,left = 50,top = 50');return false;"> [Edit Options]
                                    </a>

									<?php
								}
								break;
							case "RADIO":

								if ( $mode == 'view' ) {
									echo $purifier->purify( getCodeDescription( $row['field_id'], $prams[ $row['field_id'] ] ) );
								} else {
									form_radio_field( $row['field_id'], $prams[ $row['field_id'] ] );
								}
								if ( $mode == 'edit' ) {
									?>
                                    <a href=""
                                       onclick="window.open('maintain_codes.php?field_id=<?php echo $row['field_id']; ?>', '', 'toolbar=no,scrollbars=yes,location=no,statusbar=no,menubar=no,resizable=1,width=400,height=500,left = 50,top = 50');return false;"> [Edit Options]
                                    </a>

									<?php
								}
								break;

							case "CHECK":
								form_checkbox_field( $row['field_id'], $prams[ $row['field_id'] ], $mode );
								if ( $mode == 'edit' ) {
									?>
                                    <a href=""
                                       onclick="window.open('maintain_codes.php?field_id=<?php echo $row['field_id']; ?>', '', 'toolbar=no,scrollbars=yes,location=no,statusbar=no,menubar=no,resizable=1,width=400,height=500,left = 50,top = 50');return false;"> [Edit Options]
                                    </a>

									<?php
								}
								break;
							case "MSELECT":
								form_mselect_field( $row['field_id'], $prams[ $row['field_id'] ], $row['field_height'], $mode );
								if ( $mode == 'edit' ) {
									?>
                                    <a href=""
                                       onclick="window.open('maintain_codes.php?field_id=<?php echo $row['field_id']; ?>', '', 'toolbar=no,scrollbars=yes,location=no,statusbar=no,menubar=no,resizable=1,width=400,height=500,left = 50,top = 50');return false;"> [Edit Options]
                                    </a>

									<?php
								}
								break;
							case "NOTE":
								break;
							case "MIME":
								break;
							case "BLANK":
								echo "&nbsp;";
								break;
						}
					}
					?>
                </div>
            </div>
			<?php
		}
	}

	if ( ! $dont_break_table ) {
		?>
        </div>
		<?php
	}
}

function mds_delete_field( $field_id ) {

	$field_id = intval( $field_id );

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "form_fields WHERE  field_id='" . $field_id . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result, MYSQLI_ASSOC );

	// delete codes
	if ( ( $row['field_type'] == 'CHECK' ) || ( $row['field_type'] == 'RADIO' ) || ( $row['field_type'] == 'MSELECT' ) ) {
		$sql = "DELETE FROM " . MDS_DB_PREFIX . "codes where field_id='$field_id' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
	}
	// delete the field and any translations
	$sql = "DELETE FROM `" . MDS_DB_PREFIX . "form_fields` WHERE field_id='$field_id' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

	$sql = "DELETE FROM `" . MDS_DB_PREFIX . "form_field_translations` WHERE field_id='$field_id' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

	$sql = "DELETE FROM `" . MDS_DB_PREFIX . "form_lists` WHERE field_id='$field_id'  ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

	$_REQUEST['mode'] = 'edit'; // interface stays in edit mode

}

function mds_save_field( $error, $NEW_FIELD ) {
	global $f2;

	// filter vars
	$form_id           = intval( $_REQUEST['form_id'] ?? 1 );
	$field_id          = intval( $_REQUEST['field_id'] ?? 0 );
	$section           = intval( $_REQUEST['section'] ?? 0 );
	$reg_expr          = $f2->filter( $_REQUEST['reg_expr'] ?? '' );
	$field_label       = $f2->filter( $_REQUEST['field_label'] ?? '' );
	$field_type        = $f2->filter( $_REQUEST['field_type'] ?? '' );
	$field_sort        = intval( $_REQUEST['field_sort'] ?? 0 );
	$is_required       = $f2->filter( $_REQUEST['is_required'] ?? 'N', "YN" );
	$display_in_list   = $f2->filter( $_REQUEST['display_in_list'] ?? 'N', "YN" );
	$is_in_search      = $f2->filter( $_REQUEST['is_in_search'] ?? 'N', "YN" );
	$error_message     = $f2->filter( $_REQUEST['error_message'] ?? '' );
	$field_init        = $f2->filter( $_REQUEST['field_init'] ?? '' );
	$field_width       = intval( $_REQUEST['field_width'] ?? 0 );
	$field_height      = intval( $_REQUEST['field_height'] ?? 0 );
	$list_sort_order   = intval( $_REQUEST['list_sort_order'] ?? 0 );
	$search_sort_order = intval( $_REQUEST['search_sort_order'] ?? 0 );
	$template_tag      = $f2->filter( $_REQUEST['template_tag'] ?? '' );
	$is_hidden         = $f2->filter( $_REQUEST['is_hidden'] ?? '' );
	$is_anon           = $f2->filter( $_REQUEST['is_anon'] ?? '' );
	$field_comment     = $f2->filter( $_REQUEST['field_comment'] ?? '' );
	$category_init_id  = intval( $_REQUEST['category_init_id'] ?? 0 );
	$is_cat_multiple   = $f2->filter( $_REQUEST['is_cat_multiple'] ?? 'N', "YN" );
	$cat_multiple_rows = intval( $_REQUEST['cat_multiple_rows'] ?? 0 );
	$is_blocked        = $f2->filter( $_REQUEST['is_blocked'] ?? '' );
	$multiple_sel_all  = $f2->filter( $_REQUEST['multiple_sel_all'] ?? '' );
	$is_prefill        = $f2->filter( $_REQUEST['is_prefill'] ?? '' );

	if ( $NEW_FIELD == "YES" ) {

		$sql = "INSERT INTO `" . MDS_DB_PREFIX . "form_fields` ( `form_id` , `field_id` , `reg_expr` , `field_label` , `field_type` , `field_sort` , `is_required` , `display_in_list` , `error_message` , `field_init`, `field_width`, `field_height`, `is_in_search`, `list_sort_order`, `search_sort_order`, `template_tag`, `section`, `is_hidden`, `is_anon`, `field_comment`, `category_init_id`, `is_cat_multiple`, `cat_multiple_rows`, `is_blocked`, `multiple_sel_all`) 
        VALUES (
            '$form_id',
            NULL,
            '" . mysqli_real_escape_string( $GLOBALS['connection'], $reg_expr ) . "',
            '" . mysqli_real_escape_string( $GLOBALS['connection'], $field_label ) . "',
            '" . mysqli_real_escape_string( $GLOBALS['connection'], $field_type ) . "',
            '$field_sort',
            '" . mysqli_real_escape_string( $GLOBALS['connection'], $is_required ) . "',
            '" . mysqli_real_escape_string( $GLOBALS['connection'], $display_in_list ) . "',
            '" . mysqli_real_escape_string( $GLOBALS['connection'], $error_message ) . "',
            '" . mysqli_real_escape_string( $GLOBALS['connection'], $field_init ) . "',
            '$field_width',
            '$field_height',
            '$is_in_search',
            '$list_sort_order',
            '$search_sort_order',
            '" . mysqli_real_escape_string( $GLOBALS['connection'], $template_tag ) . "',
            '" . mysqli_real_escape_string( $GLOBALS['connection'], $section ) . "',
            '" . mysqli_real_escape_string( $GLOBALS['connection'], $is_hidden ) . "',
            '" . mysqli_real_escape_string( $GLOBALS['connection'], $is_anon ) . "',
            '" . mysqli_real_escape_string( $GLOBALS['connection'], $field_comment ) . "',
            '$category_init_id',
            '" . mysqli_real_escape_string( $GLOBALS['connection'], $is_cat_multiple ) . "',
            '$cat_multiple_rows',
            '" . mysqli_real_escape_string( $GLOBALS['connection'], $is_blocked ) . "',
            '" . mysqli_real_escape_string( $GLOBALS['connection'], $multiple_sel_all ) . "'
        )";
	} else {

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "form_fields WHERE field_id='" . $field_id . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
		$row = mysqli_fetch_array( $result );

//		if ( $row['field_type'] != $field_type ) {
		//echo "Cannot change this field type...";
//		}

		$tt = "";
		if ( ( is_reserved_template_tag( $template_tag ) ) && ( true ) ) {
			// do not update template tag

		} else if ( $template_tag != '' ) {
			$tt = "`template_tag` = '" . mysqli_real_escape_string( $GLOBALS['connection'], $template_tag ) . "',";
		}

		$sql = "UPDATE `" . MDS_DB_PREFIX . "form_fields` SET `form_id`=" . $form_id . "," . "`field_id`=" . $field_id . "," . "`section`=" . $section . "," . "`reg_expr`='" . mysqli_real_escape_string( $GLOBALS['connection'], $reg_expr ) . "'," . "`field_label`='" . mysqli_real_escape_string( $GLOBALS['connection'], $field_label ) . "'," . "`field_type`='" . mysqli_real_escape_string( $GLOBALS['connection'], $field_type ) . "'," . "`field_sort`='" . $field_sort . "'," . "`is_required`='" . mysqli_real_escape_string( $GLOBALS['connection'], $is_required ) . "'," . "`display_in_list`='" . mysqli_real_escape_string( $GLOBALS['connection'], $display_in_list ) . "'," . "`is_in_search`='" . mysqli_real_escape_string( $GLOBALS['connection'], $is_in_search ) . "'," . "`error_message`='" . mysqli_real_escape_string( $GLOBALS['connection'], $error_message ) . "'," . "`field_init`='" . mysqli_real_escape_string( $GLOBALS['connection'], $field_init ) . "'," . "`field_width`=" . $field_width . "," . "`field_height`=" . $field_height . "," . "`list_sort_order`='" . $list_sort_order . "'," . "`search_sort_order`='" . $search_sort_order . "'," . $tt . "`is_hidden`='" . mysqli_real_escape_string( $GLOBALS['connection'], $is_hidden ) . "'," . "`is_anon`='" . mysqli_real_escape_string( $GLOBALS['connection'], $is_anon ) . "'," . "`field_comment`='" . mysqli_real_escape_string( $GLOBALS['connection'], $field_comment ) . "'," . "`category_init_id`=" . $category_init_id . "," . "`is_cat_multiple`='" . mysqli_real_escape_string( $GLOBALS['connection'], $is_cat_multiple ) . "'," . "`cat_multiple_rows`=" . $cat_multiple_rows . "," . "`is_blocked`='" . mysqli_real_escape_string( $GLOBALS['connection'], $is_blocked ) . "'," . "`multiple_sel_all`='" . mysqli_real_escape_string( $GLOBALS['connection'], $multiple_sel_all ) . "'," . "`is_prefill`='" . mysqli_real_escape_string( $GLOBALS['connection'], $is_prefill ) . "' " . "WHERE `field_id` = " . $field_id . ";";
		//}

		if ( $sql != '' ) {
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
		}
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
		// update translations

		$sql = "INSERT INTO `" . MDS_DB_PREFIX . "form_field_translations` (`field_id`, `lang`, `field_label`, `error_message`, `field_comment`)
VALUES ('" . $field_id . "', '" . get_lang() . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $field_label ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $error_message ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $field_comment ) . "') 
ON DUPLICATE KEY UPDATE 
`field_id` = '" . $field_id . "',
`lang` = '" . get_lang() . "',
`field_label` = '" . mysqli_real_escape_string( $GLOBALS['connection'], $field_label ) . "',
`error_message` = '" . mysqli_real_escape_string( $GLOBALS['connection'], $error_message ) . "',
 `field_comment` = '" . mysqli_real_escape_string( $GLOBALS['connection'], $field_comment ) . "';";

		mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

		// update template tag on the form_lists

		if ( $template_tag != '' ) { // sometimes template tag can be blank (reserved tags)

			$sql = "UPDATE " . MDS_DB_PREFIX . "form_lists SET `template_tag`='" . mysqli_real_escape_string( $GLOBALS['connection'], $template_tag ) . "' WHERE `field_id`='" . $field_id . "'";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
		}
	}

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

	if ( ( $field_type == 'RADIO' ) || ( $field_type == 'CHECK' ) || ( $field_type == 'MSELECT' ) || ( $field_type == 'SELECT' ) ) {
		//echo 'formatting field..<br>';
		if ( $NEW_FIELD == 'YES' ) {
			$_REQUEST['field_id'] = mysqli_insert_id( $GLOBALS['connection'] );
		}
		format_codes_translation_table( $_REQUEST['field_id'] );
	}

	if ( $NEW_FIELD == 'YES' ) {
		$field_id = mysqli_insert_id( $GLOBALS['connection'] );
	}

	$_REQUEST['mode'] = 'edit';
	global $NEW_FIELD;
	$NEW_FIELD = 'NO';

	return $field_id;
}

function validate_field_form() {
	global $f2;

	foreach ( $_POST as $key => $val ) {
		$_POST[ $key ] = trim( $val );
	}

	// filter vars
	$form_id           = intval( $_POST['form_id'] ?? 1 );
	$field_id          = intval( $_POST['field_id'] ?? 0 );
	$section           = intval( $_POST['section'] ?? 0 );
	$reg_expr          = $f2->filter( $_POST['reg_expr'] ?? '' );
	$field_label       = $f2->filter( $_POST['field_label'] ?? '' );
	$field_type        = $f2->filter( $_POST['field_type'] ?? '' );
	$field_sort        = intval( $_POST['field_sort'] ?? 0 );
	$is_required       = $f2->filter( $_POST['is_required'] ?? 'N', "YN" );
	$display_in_list   = $f2->filter( $_POST['display_in_list'] ?? 'N', "YN" );
	$is_in_search      = $f2->filter( $_POST['is_in_search'] ?? 'N', "YN" );
	$error_message     = $f2->filter( $_POST['error_message'] ?? '' );
	$field_init        = $f2->filter( $_POST['field_init'] ?? '' );
	$field_width       = intval( $_POST['field_width'] ?? 0 );
	$field_height      = intval( $_POST['field_height'] ?? 0 );
	$list_sort_order   = intval( $_POST['list_sort_order'] ?? 0 );
	$search_sort_order = intval( $_POST['search_sort_order'] ?? 0 );
	$template_tag      = $f2->filter( $_POST['template_tag'] ?? '' );
	$is_hidden         = $f2->filter( $_POST['is_hidden'] ?? '' );
	$is_anon           = $f2->filter( $_POST['is_anon'] ?? '' );
	$field_comment     = $f2->filter( $_POST['field_comment'] ?? '' );
	$category_init_id  = intval( $_POST['category_init_id'] ?? 0 );
	$is_cat_multiple   = $f2->filter( $_POST['is_cat_multiple'] ?? 'N', "YN" );
	$cat_multiple_rows = intval( $_POST['cat_multiple_rows'] ?? 0 );
	$is_blocked        = $f2->filter( $_POST['is_blocked'] ?? '' );
	$multiple_sel_all  = $f2->filter( $_POST['multiple_sel_all'] ?? '' );
	$is_prefill        = $f2->filter( $_POST['is_prefill'] ?? '' );

	$error = "";
//	if ( $field_label == '' ) {
	//$error .= "<FONT SIZE='' COLOR='#000000'><b>- Label is blank.</B></FONT><br>";
//	}

	if ( $field_type == '' ) {
		$error .= "<FONT SIZE='' COLOR='#000000'><b>- Type of field is not selected.</B></FONT><br>";
	}

	if ( $is_required == 'Y' && $reg_expr == '' ) {
		$error .= "<FONT SIZE='' COLOR='#000000'><b>- The field is required, but 'Type of Check' was not selected.</B></FONT><br>";
	}

	if ( $is_required == 'Y' && $error_message == '' ) {
		$error .= "<FONT SIZE='' COLOR='#000000'><b>- The field is required, but 'Error message' was not filled in.</B></FONT><br>";
	}

	if ( is_reserved_template_tag( $template_tag ) ) {

		$error        .= "<FONT SIZE='' COLOR='#000000'><b>- Template Tag name is reserved by the system. Please choose a different template tag name.</B></FONT><br>";
		$template_tag = "";
	}

	if ( ( $template_tag == '' ) && ( ! is_reserved_field( $field_id ) ) ) {
		$error .= "<FONT SIZE='' COLOR='#000000'><b>- Template Tag is blank.</B></FONT><br>";
	}

	if ( $template_tag != '' ) {

		// check template tag for duplicates...

		$f_id_sql = "";
		if ( $field_id != '' ) {
			$f_id_sql = "AND field_id != '" . $field_id . "' ";
		}

		$sql = "select field_id from " . MDS_DB_PREFIX . "form_fields where template_tag='" . mysqli_real_escape_string( $GLOBALS['connection'], $template_tag ) . "' and form_id='" . $form_id . "' $f_id_sql  ";
		//echo $sql;
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
		if ( mysqli_num_rows( $result ) > 0 ) {
			$error .= "<FONT SIZE='' COLOR='#000000'><b>- Template Tag is already in use. Please try a different name.</B></FONT><br>";
		}

		$f_id_sql = '';
	}

	if ( $field_id != '' ) {
		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "form_fields WHERE field_id='" . $field_id . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
		$row = mysqli_fetch_array( $result );

		if ( isset( $row['field_type'] ) && get_definition( $row['field_type'] ) != get_definition( $field_type ) ) {
			$error .= "<FONT SIZE='' COLOR='#000000'><b>- Cannot change this field type to '" . $field_type . "' because database types are incompatible. If you would like to continue anyway, please check the check box field below the 'Save' button.</b>";

			$_REQUEST['allow_anyway'] = 'true';

			if ( ( ( $row['field_type'] != 'SEPERATOR' ) && ( ( $row['field_type'] != 'NOTE' ) ) && ( ( $row['field_type'] != 'BLANK' ) ) ) ) {

				switch ( $_REQUEST['form_id'] ) {

					case "1":
						//$sql = "ALTER TABLE `ads` CHANGE `".$_REQUEST['field_id']."` `".$_REQUEST['field_id']."` ".get_definition($_REQUEST['field_type']);
						//ALTER TABLE `ads` ADD `6` TEXT NOT NULL
						//You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near ' ADD `6` TEXT NOT NULL' at line 1
						$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "ads` ADD '" . intval( $_REQUEST['field_id'] ) . "' " . mysqli_real_escape_string( $GLOBALS['connection'], get_definition( $_REQUEST['field_type'] ) );
						break;
				}
			}

			if ( isset( $_REQUEST['do_alter'] ) && $_REQUEST['do_alter'] != '' ) {

				//@mysqli_query($GLOBALS['connection'], $sql);
				$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

				$_REQUEST['allow_anyway'] = '';
				$error                    = "";
				$_REQUEST['do_alter']     = "";
			}
		}
	}

	return $error;
}

function validate_form_data( $form_id ) {

	global $label, $purifier;

	if ( ! defined( 'MAX_UPLOAD_BYTES' ) ) {
		define( 'MAX_UPLOAD_BYTES', _GetMaxAllowedUploadSize() );
	}

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "form_fields, " . MDS_DB_PREFIX . "form_field_translations WHERE " . MDS_DB_PREFIX . "form_fields.field_id=" . MDS_DB_PREFIX . "form_field_translations.field_id AND " . MDS_DB_PREFIX . "form_field_translations.lang='" . get_lang() . "' AND form_id='" . intval( $form_id ) . "' AND field_type != 'SEPERATOR' AND field_type != 'BLANK' AND field_type != 'NOTE' order by field_sort";

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

	$error = "";
	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {

		if ( ( $row['field_type'] == 'TEXT' ) || ( $row['field_type'] == 'TEXTAREA' ) || ( $row['field_type'] == 'EDITOR' ) ) {
			if ( check_for_bad_words( $_POST[ $row['field_id'] ] ) ) {
				$error .= $row['field_label'] . " - " . $label['bad_words_not_accept'] . "<br>";
			}
		}

		if ( defined( "BREAK_LONG_WORDS" ) && BREAK_LONG_WORDS == 'YES' ) {

			if ( ( $row['field_type'] == 'TEXT' ) || ( $row['field_type'] == 'TEXTAREA' ) ) {
				// HTML not allowed
				$_POST[ $row['field_id'] ] = trim( break_long_words( $_POST[ $row['field_id'] ], false ) );
			}

			if ( ( $row['field_type'] == 'EDITOR' ) ) {

				// field where limited HTML is allowed
				$_POST[ $row['field_id'] ] = trim( break_long_words( $_POST[ $row['field_id'] ], true ) );
			}
		}

		// clean the data..
		if ( ( $row['field_type'] == 'EDITOR' ) || ( $row['field_type'] == 'TEXTAREA' ) ) {
			$_POST[ $row['field_id'] ] = $purifier->purify( $_POST[ $row['field_id'] ] );
		}

		if ( ( ( $row['field_type'] == 'FILE' ) || ( $row['field_type'] == 'IMAGE' ) ) && ( isset($_FILES[ $row['field_id'] ]) && $_FILES[ $row['field_id'] ]['name'] != '' ) ) {

			$a   = explode( ".", $_FILES[ $row['field_id'] ]['name'] );
			$ext = array_pop( $a );

			if ( ! is_filetype_allowed( $_FILES[ $row['field_id'] ]['name'] ) && ( $row['field_type'] == 'FILE' ) ) {

				$label['vaild_file_ext_error'] = str_replace( "%EXT_LIST%", ALLOWED_EXT, $label['vaild_file_ext_error'] );
				$label['vaild_file_ext_error'] = str_replace( "%EXT%", $ext, $label['vaild_file_ext_error'] );
				$error                         .= $label['vaild_file_ext_error'] . "<br>";
			}

			if ( ! is_imagetype_allowed( $_FILES[ $row['field_id'] ]['name'] ) && ( $row['field_type'] == 'IMAGE' ) ) {
				$label['vaild_image_ext_error'] = str_replace( "%EXT_LIST%", ALLOWED_IMG, $label['vaild_image_ext_error'] );
				$label['vaild_image_ext_error'] = str_replace( "%EXT%", $ext, $label['vaild_image_ext_error'] );
				$error                          .= $label['vaild_image_ext_error'] . "<br>";
			}
			if ( ini_get( "safe_mode" ) === false ) {
				if ( filesize( $_FILES[ $row['field_id'] ]['tmp_name'] ) > MAX_UPLOAD_BYTES ) {
					$label['valid_file_size_error'] = str_replace( "%FILE_NAME%", $_FILES[ $row['field_id'] ]['name'], $label['valid_file_size_error'] );
					$error                          .= $label['valid_file_size_error'] . "<br>";
				}
			}
			//echo "filesize: ".filesize($_FILES[$row['field_id']]['tmp_name']);

		}

		if ( $row['is_required'] == 'Y' ) {

			if ( ( $row['field_type'] == 'DATE' ) || ( ( $row['field_type'] == 'DATE_CAL' ) ) ) {
				$row['reg_expr'] = "date"; // default to date check

			}

			//if ($row['field_type']=='TEXT') {
			//$_POST[$row['field_id']] =  htmlspecialchars ($_POST[$row['field_id']]); // escape html...
			//}

			switch ( $row['reg_expr'] ) {
				case "not_empty":
					if ( $row['field_type'] == 'FILE' || $row['field_type'] == 'IMAGE' ) {
						// check if an image was saved already first by checking for the delete input
						$del_image_key = 'del_image' . $row['field_id'];
						if ( isset( $_POST[ $del_image_key ] ) ) {
							break;
						}

						// check if file name is empty or image size is 0
						if ( $_FILES[ $row['field_id'] ]['name'] == '' || $_FILES[ $row['field_id'] ]['size'] == 0 ) {
							$error .= " - '" . $row['field_label'] . "' " . $row['error_message'] . "<br>";
						}
					} else if ( trim( $_POST[ $row['field_id'] ] == '' ) ) {
						$error .= " - '" . $row['field_label'] . "' " . $row['error_message'] . "<br>";
					}
					break;
				case "email":
					if ( ! validate_mail( trim( $_POST[ $row['field_id'] ] ) ) ) {
						$error .= " - '" . $row['field_label'] . "' " . $row['error_message'] . "<br>";
					}
					break;
				case "date":

					if ( $row['field_type'] == 'DATE' ) {

						$day   = $_POST[ $row['field_id'] . "d" ];
						$month = $_POST[ $row['field_id'] . "m" ];
						$year  = $_POST[ $row['field_id'] . "y" ];
					} else {

						$ts    = strtotime( $row['field_id'] . " GMT" );
						$day   = date( 'd', $ts );
						$month = date( 'm', $ts );
						$year  = date( 'y', $ts );
					}
					//$date_str = "$year-$month-$day"; // ISO 8601
					//echo $date_str." *".strtotime($date_str)."*<Br>";
					if ( ! @checkdate( $month, $day, $year ) ) {
						$error .= " - '" . $row['field_label'] . "' " . $row['error_message'] . "<br>";
					}

					break;
				case 'url':

					// Check for http or https
					$url = parse_url( $_POST[ $row['field_id'] ] );
					if ( empty( $url['scheme'] ) ) {
						$_POST[ $row['field_id'] ] = 'https://' . $_POST[ $row['field_id'] ];
					}

					if ( ( $_POST[ $row['field_id'] ] == '' ) || ( ( $_POST[ $row['field_id'] ] == 'http://' ) ) ) {
						$error .= " - '" . $row['field_label'] . "' " . $row['error_message'] . "<br>";
					} else if ( VALIDATE_LINK == 'YES' ) {
						//$handle = fopen($_POST[url], "r");

						$url_arr = explode( "/", $_POST[ $row['field_id'] ] );
						$host    = array_shift( $url_arr );
						$host    = array_shift( $url_arr );
						$host    = array_shift( $url_arr );
						$fp      = @fsockopen( $host, 80, $errno, $errstr, 30 );
						if ( ! $fp ) {
							//$error .= "<b>- Cannot connect to host in the URL. ($errstr)</b><br>";

						} else {
							$path = implode( "/", $url_arr );
							$out  = "GET /$path HTTP/1.1\r\n";
							$out  .= "Host: $host\r\n";
							$out  .= "Connection: Close\r\n\r\n";

							fwrite( $fp, $out );

							$str = fgets( $fp );
							if ( strpos( $str, "404" ) || strpos( $str, "401" ) || strpos( $str, "403" ) ) {

								$error .= "- '" . $row['field_label'] . "' <b>" . $label['advertiser_publish_bad_url'] . "</b><br>";
							}

							fclose( $fp );
						}
					}
					break;

				default:
					if ( trim( $_POST[ $row['field_id'] ] == '' ) ) {
						$error .= " - '" . $row['field_label'] . "' " . $row['error_message'] . "<br>";
					}
					break;
			}
		}
	}

	return $error;
}

function field_form( $NEW_FIELD, $prams, $form_id ) {
	global $f2;

	$field_id = intval( $_REQUEST['field_id'] ?? 0 );

	if ( ( ( ! isset( $_REQUEST['save'] ) || $_REQUEST['save'] == '' ) && ( isset( $_REQUEST['field_id'] ) && $_REQUEST['field_id'] != '' ) ) && ( ! isset( $prams['error'] ) || $prams['error'] == '' ) ) {

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "form_fields, " . MDS_DB_PREFIX . "form_field_translations WHERE " . MDS_DB_PREFIX . "form_fields.field_id=" . MDS_DB_PREFIX . "form_field_translations.field_id AND lang='" . get_lang() . "' AND " . MDS_DB_PREFIX . "form_fields.field_id='" . $field_id . "'";

		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
		$prams = mysqli_fetch_array( $result, MYSQLI_ASSOC );
	} else {
		$prams['field_id']          = intval( $_REQUEST['field_id'] ?? 0 );
		$prams['form_id']           = intval( $_REQUEST['form_id'] ?? 1 );
		$prams['field_label']       = $f2->filter( $_REQUEST['field_label'] ?? '' );
		$prams['field_sort']        = intval( $_REQUEST['field_sort'] ?? 0 );
		$prams['field_type']        = $f2->filter( $_REQUEST['field_type'] ?? '' );
		$prams['is_required']       = $f2->filter( $_REQUEST['is_required'] ?? 'N', "YN" );
		$prams['display_in_list']   = $f2->filter( $_REQUEST['display_in_list'] ?? 'N', "YN" );
		$prams['reg_expr']          = $f2->filter( $_REQUEST['reg_expr'] ?? '' );
		$prams['error_message']     = $f2->filter( $_REQUEST['error_message'] ?? '' );
		$prams['field_init']        = $f2->filter( $_REQUEST['field_init'] ?? '' );
		$prams['field_width']       = intval( $_REQUEST['field_width'] ?? 0 );
		$prams['field_height']      = intval( $_REQUEST['field_height'] ?? 0 );
		$prams['is_in_search']      = $f2->filter( $_REQUEST['is_in_search'] ?? 'N', "YN" );
		$prams['template_tag']      = $f2->filter( $_REQUEST['template_tag'] ?? '' );
		$prams['section']           = intval( $_REQUEST['section'] ?? 0 );
		$prams['list_sort_order']   = intval( $_REQUEST['list_sort_order'] ?? 0 );
		$prams['search_sort_order'] = intval( $_REQUEST['search_sort_order'] ?? 0 );
		$prams['field_comment']     = $f2->filter( $_REQUEST['field_comment'] ?? '' );
		$prams['is_hidden']         = $f2->filter( $_REQUEST['is_hidden'] ?? '' );
		$prams['is_anon']           = $f2->filter( $_REQUEST['is_anon'] ?? '' );
		$prams['is_blocked']        = $f2->filter( $_REQUEST['is_blocked'] ?? '' );
		$prams['is_prefill']        = $f2->filter( $_REQUEST['is_prefill'] ?? '' );
		$prams['multiple_sel_all']  = $f2->filter( $_REQUEST['multiple_sel_all'] ?? '' );
		$prams['category_init_id']  = intval( $_REQUEST['category_init_id'] ?? 0 );
		$prams['is_cat_multiple']   = $f2->filter( $_REQUEST['is_cat_multiple'] ?? 'N', "YN" );
		$prams['cat_multiple_rows'] = intval( $_REQUEST['cat_multiple_rows'] ?? 0 );
	}

	?>
	<?php

	if ( $prams['template_tag'] == '' ) {

		// try to get template tag from the database (It could be blank because it was reserved)

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "form_fields, " . MDS_DB_PREFIX . "form_field_translations WHERE " . MDS_DB_PREFIX . "form_fields.field_id=" . MDS_DB_PREFIX . "form_field_translations.field_id AND lang='" . get_lang() . "' AND " . MDS_DB_PREFIX . "form_fields.field_id='" . $field_id . "'";

		$temp_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
		$temp_row = mysqli_fetch_array( $temp_result, MYSQLI_ASSOC );

		$prams['template_tag'] = $temp_row['template_tag'] ?? '';
	}

	$disabled = "";
	if ( is_reserved_template_tag( $prams['template_tag'] ) ) {
		$disabled = " disabled ";
	}

	?>

    <form method="POST" name="form2" action="<?php echo htmlentities( $_SERVER['PHP_SELF'] ); ?>">
        <input type="hidden" name="form_id" value="<?php echo $form_id; ?>"/>
        <input type="hidden" name="NEW_FIELD" value="<?php echo $_REQUEST['NEW_FIELD'] ?? ''; ?>"/>
        <input type="hidden" name="field_id" value="<?php echo $prams['field_id']; ?>"/>
        <input type="hidden" name="mode" value="<?php echo $_REQUEST['mode'] ?>"/>
        <table border="0" cellSpacing="1" cellPadding="3" bgColor="#d9d9d9">
            <tr>
                <td colspan="2"><?php if ( $NEW_FIELD == 'NO' ) {
						echo "<font face='Arial' size='2'><b>[EDIT FIELD]</b></font>";
					} else {
						echo "<font face='Arial' size='2'><b>[ADD NEW FIELD]</b></font>";
					} ?>
                    <br><input class="form_submit_button" type="submit" value="Save" name="save"><?php if ( $NEW_FIELD == 'NO' ) { ?>
                        <input type="submit" value="Delete" name="delete" onClick="return confirmLink(this, 'Delete this field, are you sure?')"><?php }
					if ( isset( $_REQUEST['allow_anyway'] ) && $_REQUEST['allow_anyway'] != '' ) {

						echo "<br><input type='checkbox' name='do_alter'><font color='red'>Change the field's Database Type</font> (This will delete any previous data stored in the field)";
					}
					?></td>
            </tr>
            <tr bgColor="#ffffff">
                <td><font face="Arial" size="2"><b>Field label</b></font></td>
                <td>
                    <input type="text" name="field_label" size="27" value="<?php echo $prams['field_label']; ?>"/></td>
            </tr>
            <tr bgcolor="#ffffff">
                <td><font face="Arial" size="2"><b>Type<font color="#ff0000">*</font></b></font></td>
                <td><select size="5" name="field_type">
                        <option value="BLANK" <?php if ( $prams['field_type'] == 'BLANK' ) {
							echo " selected ";
						} ?>>Blank Space
                        </option>
                        <option value="CHECK" <?php if ( $prams['field_type'] == 'CHECK' ) {
							echo " selected ";
						} ?>>Check Boxes
                        </option>

                        <option value="DATE" <?php if ( $prams['field_type'] == 'DATE' ) {
							echo " selected ";
						} ?>>Date
                        </option>
                        <!--<option value="DATE_CAL" <?php if ( $prams['field_type'] == 'DATE_CAL' ) {
							echo " selected ";
						} ?>>Date - Calendar</option>-->
                        <!--<option value="FILE" <?php if ( $prams['field_type'] == 'FILE' ) {
							echo " selected ";
						} ?>>File</option>-->
                        <option value="IMAGE" <?php if ( $prams['field_type'] == 'IMAGE' ) {
							echo " selected ";
						} ?>>Image
                        </option>
						<?php

						//if ($form_id==1) {  // HTML editor is for job posts only.

						?>
                        <option value="EDITOR" <?php if ( $prams['field_type'] == 'EDITOR' ) {
							echo " selected ";
						} ?> >HTML Editor
                        </option>
						<?php

						//	}

						?>
                        <option value="MSELECT" <?php if ( $prams['field_type'] == 'MSELECT' ) {
							echo " selected ";
						} ?>>Multiple Select
                        </option>
                        <option value="NOTE" <?php if ( $prams['field_type'] == 'NOTE' ) {
							echo " selected ";
						} ?>>Note
                        </option>
                        <option value="RADIO" <?php if ( $prams['field_type'] == 'RADIO' ) {
							echo " selected ";
						} ?>>Radio Buttons
                        </option>
                        <option value="SEPERATOR" <?php if ( $prams['field_type'] == 'SEPERATOR' ) {
							echo " selected ";
						} ?> >Seperator
                        </option>
                        <option value="SELECT" <?php if ( $prams['field_type'] == 'SELECT' ) {
							echo " selected ";
						} ?>>Single Select
                        </option>
						<?php

						if ( $form_id == 2 ) {  // skill matrix is for resumes only.

							?>
                            <!--<option value="SKILL_MATRIX" <?php if ( $prams['field_type'] == 'SKILL_MATRIX' ) {
								echo " selected ";
							} ?>>Skill Matrix</option>-->

							<?php
						}

						?>
                        <option value="TEXTAREA" <?php if ( $prams['field_type'] == 'TEXTAREA' ) {
							echo " selected ";
						} ?> >Text Editor
                        </option>
                        <option value="TEXT" <?php if ( $prams['field_type'] == 'TEXT' ) {
							echo " selected ";
						} ?> >Text Field
                        </option>
                    </select></td>
            </tr>
            <tr bgcolor="#ffffff">
                <td><font face="Arial" size="2"><b>Initial Value</b></font></td>
                <td>
                    <input type="text" name="field_init" value="<?php echo $prams['field_init']; ?>" size="3"/><font size='2'> (Default value for text fields, can be left blank.) </font>
                </td>
            </tr>
            <tr bgcolor="#ffffff">
                <td><font face="Arial" size="2"><b>Sort order<font color="#ff0000"><b>*</b></font></b></font></td>
                <td>
                    <input type="text" name="field_sort" value="<?php echo $prams['field_sort']; ?>" size="3"/><font size='2'> (1=first, 2=2nd, etc) </font>
                    <input type="hidden" name="section" value='1'>
                </td>
            </tr>
            <tr bgColor="#eaeaea">
                <td colspan="2">Validation (only required fields are validated)</td>
            </tr>
            <tr bgcolor="#ffffff">
                <td><font face="Arial" size="2"><b>Is Required?</b></font></td>
                <td><input type="checkbox" name="is_required" value="Y" <?php if ( $prams['is_required'] == 'Y' ) {
						echo " checked ";
					} ?>></td>
            </tr>
            <tr bgcolor="#ffffff">
                <td><font face="Arial" size="2"><b>Type of check</b></font></td>
                <td>
                    <select name="reg_expr">
                        <option value="" <?php if ( $prams['reg_expr'] == '' ) {
							echo " selected ";
						} ?>>[Select]
                        </option>
                        <option value="not_empty" <?php if ( $prams['reg_expr'] == 'not_empty' ) {
							echo " selected ";
						} ?> >Must not be empty
                        </option>
                        <option value="email" <?php if ( $prams['reg_expr'] == 'email' ) {
							echo " selected ";
						} ?> >Valid Email
                        </option>
                        <option value="date" <?php if ( $prams['reg_expr'] == 'date' ) {
							echo " selected ";
						} ?> >Valid Date
                        </option>
                        <option value="url" <?php if ( $prams['reg_expr'] == 'url' ) {
							echo " selected ";
						} ?> >Valid URL
                        </option>
                    </select>
                </td>
            </tr>
            <tr bgcolor="#ffffff">
                <td><font face="Arial" size="2"><b>Error message</b></font><font color="#ff0000"><b>*</b></font></td>
                <td>
                    <input type="text" name="error_message" size="27" value="<?php echo $prams['error_message']; ?>"/>(The reason for the error. Eg:
                    <i>was not filled in</i> or <i>was invalid</i> for email.)
                </td>
            </tr>
            <tr bgColor="#eaeaea">
                <td colspan="2">Display</td>
            </tr>
            <!-- tr bgcolor="#ffffff">
    <td><font face="Arial" size="2"><b>Display in list?</b></font></td>
    <td><input type="checkbox" name="display_in_list" value="Y" <?php if ( $prams['display_in_list'] == 'Y' ) {
				echo " checked ";
			} ?>  >
	<font face="Arial" size="2">Column Order:</font><input type="text" name="list_sort_order" value="<?php echo $prams['list_sort_order']; ?>" size="2"></td>
  </tr -->
            <!--
  <tr bgcolor="#ffffff">
    <td><font face="Arial" size="2"><b>Is on search form?</b></font></td>
    <td><input type="checkbox" name="is_in_search" value="Y" <?php if ( $prams['is_in_search'] == 'Y' ) {
				echo " checked ";
			} ?>  >
	<font face="Arial" size="2">Sort Order:</font><input type="text" name="search_sort_order" value="<?php echo $prams['search_sort_order']; ?>" size="2"/>(1=first)</td>
  </tr>
  -->
            <tr bgcolor="#ffffff">
                <td><font face="Arial" size="2"><b>Template Tag <font color="#ff0000"><b>*</b></font></b></font></td>
                <td>

                    <input type="text" name="template_tag" <?php echo $disabled; ?> size="20" value="<?php echo $prams['template_tag']; ?>"> (a unique identifier for this field)
                </td>
            </tr>
            <tr bgColor="#eaeaea">
                <td colspan="2">Parameters</td>
            </tr>
            <tr bgcolor="#ffffff">
                <td><font face="Arial" size="2"><b>Width</b></font></td>
                <td>
                    <input type="text" name="field_width" size="3" value="<?php echo $prams['field_width']; ?>"/></td>
            </tr>
            <tr bgcolor="#ffffff">
                <td><font face="Arial" size="2"><b>Height</b></font></td>
                <td>
                    <input type="text" name="field_height" size="3" value="<?php echo $prams['field_height']; ?>"/><font size='2'>(for textareas or multiple selects)</font>
                </td>
            </tr>
            <!--
  <tr bgcolor="#ffffff">
    <td><font face="Arial" size="2"><b>Is hidden from website?</b></font>
  </td>
    <td><input type="checkbox" name="is_hidden" <?php if ( $prams['is_hidden'] == 'Y' ) {
				echo " checked ";
			} ?> value="Y"><font size='2'>Is hidden from website. Only Administrators can view this field.</font></td>
  </tr>
  <?php if ( $form_id == 2 ) { // only resumes ?>
  <tr bgcolor="#ffffff">
    <td><font face="Arial" size="2"><b>Can be anonymous?</b></font>
  </td>
    <td><input type="checkbox" name="is_anon" <?php if ( $prams['is_anon'] == 'Y' ) {
				echo " checked ";
			} ?> value="Y"><font size='2'>(Can be anonymous on resumes. If this feature is enabled, users can hide this field and reveal after responding to Employer's request.)</font></td>
  </tr>
  <?php } ?>
  <?php if ( $form_id == 2 ) { // only resumes ?>
  <tr bgcolor="#ffffff">
    <td><font face="Arial" size="2"><b>Is blocked?</b></font>
  </td>
    <td><input type="checkbox" name="is_blocked" <?php if ( $prams['is_blocked'] == 'Y' ) {
				echo " checked ";
			} ?> value="Y"><font size='2'>(Can be subjected to blocking. Blocking options are set in Main Config.  )</font></td>
  </tr>
  <?php } ?>
  <?php if ( $form_id == 1 ) { // only job posts can be pre-filled ?>
   <tr bgcolor="#ffffff">
    <td><font face="Arial" size="2"><b>Pre-fill?</b></font>
  </td>
    <td><input type="checkbox" name="is_prefill" <?php if ( $prams['is_prefill'] == 'Y' ) {
				echo " checked ";
			} ?> value="Y"><font size='2'>(Attempt to pre-fill the field with data from the previous record)</font></td>
  </tr>
  -->
			<?php } ?>
            <tr bgcolor="#ffffff">
                <td><font face="Arial" size="2"><b>Field Comment</b></font>
                </td>
                <td>
                    <input type="text" name="field_comment" value="<?php echo $prams['field_comment']; ?>"/><font size='2'>(Comment to be displayed next to the field, like the one you are reading now.)</font>
                </td>
            </tr>
        </table>
        <input class="form_submit_button" type="submit" value="Save" name="save">
    </form>

	<?php
}

function form_text_field( $field_name, $field_value, $width ) {

	$value = "";
	if ( ! empty( $field_value ) ) {
		$value = $field_value;
	}

	return '<input class="dynamic_form_text_style" type="text" AUTOCOMPLETE="OFF" name="' . htmlspecialchars( $field_name, ENT_QUOTES ) . '" value="' . htmlspecialchars( $value, ENT_QUOTES ) . '" size="' . intval( $width ) . '" >';
}

function form_file_field( $field_name, $field_value ) {
	return '<input class="dynamic_form_text_style" type="file" name="' . htmlspecialchars( $field_name, ENT_QUOTES ) . '"   >';
}

function form_image_field( $field_name, $field_value ) {
	return '<input class="dynamic_form_text_style" type="file" name="' . htmlspecialchars( $field_name, ENT_QUOTES ) . '" >';
}

function form_editor_field( $field_name, $field_value, $width, $height ) {

	if ( ! $height ) {
		$height = 25;
	}
	if ( ! $width ) {
		$width = 30;
	}

	$html = '<script type="text/javascript" src="../vendor/ckeditor/ckeditor/ckeditor.js"></script>';
	$html .= '	<div id="alerts">
		<noscript>
			<p>
				<strong>CKEditor requires JavaScript to run</strong>. In a browser with no JavaScript
				support, like yours, you should still see the contents (HTML data) and you should
				be able to edit it normally, without a rich editor interface.
			</p>
		</noscript>
	</div>
';
	$html .= '			<textarea cols="' . $width . '" id="' . $field_name . '" name="' . $field_name . '" rows="' . $height . '">' . htmlspecialchars( $field_value, ENT_QUOTES ) . '</textarea>';
	$html .= '			<script type="text/javascript">
			//<![CDATA[

				CKEDITOR.replace( \'' . $field_name . '\',
				{
                     language: "' . strtolower( get_lang() ) . '"
                });

			//]]>
			</script>
';

	return $html;
}

function form_textarea_field( $field_name, $field_value, $width, $height ) {
	return '<textarea  name="' . $field_name . '" cols="' . $width . '" rows="' . $height . '">' . htmlspecialchars( $field_value, ENT_QUOTES ) . '</textarea>';
}

function form_date_field( $field_name, $day, $month, $year ) {

	global $label;

	$class = "";
	if ( func_num_args() > 4 ) {
		$class = func_get_arg( 4 );
	}

	if ( ! defined( 'DATE_INPUT_SEQ' ) ) {
		define( 'DATE_INPUT_SEQ', 'YMD' );
	}

	$sequence = DATE_INPUT_SEQ;

	?>

    <table>
        <tr>
            <td>
				<?php

				while ( $widget = substr( $sequence, 0, 1 ) ) {

					switch ( $widget ) {

						case 'Y':
							echo '<input type="text" class="' . htmlspecialchars( $class, ENT_QUOTES ) . '" name="' . htmlspecialchars( $field_name, ENT_QUOTES ) . 'y" size="4" value="' . intval( $year ) . '"/>';
							break;

						case 'M':
							$output = '<select name="' . htmlspecialchars( $field_name, ENT_QUOTES ) . 'm" class="' . htmlspecialchars( $class, ENT_QUOTES ) . '">
                                <option value=""></option>';

							for ( $x = 1; $x <= 12; $x ++ ) {
								$output .= '<option ' . ( ( $month == '0' . $x ) ? 'selected ' : '' ) . 'value="0' . $x . '">' . htmlspecialchars( $label[ 'sel_month_' . $x ], ENT_QUOTES ) . '</option>';
							}

							$output .= '</select>';

							echo $output;

							break;

						case 'D':
							$output = '<select name="' . htmlspecialchars( $field_name, ENT_QUOTES ) . 'd" class="' . htmlspecialchars( $class, ENT_QUOTES ) . '">
                                <option value=""></option>';

							for ( $x = 1; $x <= 31; $x ++ ) {
								$output .= '<option ' . ( ( $day == '0' . $x ) ? 'selected ' : '' ) . 'value="0' . $x . '">' . $x . '</option>';
							}

							$output .= '</select>';

							echo $output;

							break;
					}

					$sequence = substr( $sequence, 1 );
				}

				?>

            </td>
        </tr>
    </table>

	<?php
}

function form_select_field( $field_id, $selected ) {

	global $label;

	$field_id = intval( $field_id );

	if ( $_SESSION['MDS_LANG'] != '' ) {

		$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "codes_translations` WHERE `field_id`='$field_id' and lang='" . get_lang() . "' ";
	} else {
		$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "codes` WHERE `field_id`='$field_id' ";
	}

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

	$output = '<select name="' . htmlspecialchars( $field_id, ENT_QUOTES ) . '">';
	$output .= '<option value="">' . htmlspecialchars( $label['sel_box_select'], ENT_QUOTES ) . '</option>';
	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {

		if ( $row["code"] == $selected ) {
			$checked = ' selected ';
		} else {
			$checked = '';
		}

		$output .= '<option ' . $checked . ' value="' . htmlspecialchars( $row["code"], ENT_QUOTES ) . '">';
		$output .= htmlspecialchars( $row["description"], ENT_QUOTES );
		$output .= '</option>';
	}
	$output .= "</select>";

	echo $output;
}

function form_radio_field( $field_id, $selected ) {
	$field_id = intval( $field_id );

	if ( $_SESSION['MDS_LANG'] != '' ) {

		$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "codes_translations` WHERE `field_id`='$field_id' and lang='" . get_lang() . "' ";
	} else {
		$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "codes` WHERE `field_id`='$field_id' ";
	}

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

	$output = '';
	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {

		if ( $row["code"] == $selected ) {
			$checked = ' checked ';
		} else {
			$checked = '';
		}

		$output .= '<input class="dynamic_form_radio_style" ' . $checked . ' id="id' . htmlspecialchars( $field_id . $row['code'], ENT_QUOTES ) . '" type="radio" name="' . $field_id . '" value="' . htmlspecialchars( $row['code'], ENT_QUOTES ) . '">';
		$output .= '<label for="id' . htmlspecialchars( $field_id . $row["code"], ENT_QUOTES ) . '">' . htmlspecialchars( $row["description"], ENT_QUOTES ) . '</label> <br>';
	}

	echo $output;
}

function form_checkbox_field( $field_id, $selected, $mode ) {

	$field_id = intval( $field_id );
	$mode     = strtolower( $mode );

	if ( $_SESSION['MDS_LANG'] != '' ) {

		$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "codes_translations` WHERE `field_id`='$field_id' and lang='" . get_lang() . "' ";
	} else {
		$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "codes` WHERE `field_id`='$field_id' ";
	}

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
	$checked_codes = explode( ",", $selected );

	$comma  = "";
	$output = "";
	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
		if ( in_array( $row["code"], $checked_codes ) ) {
			$checked = " checked ";
		} else {
			$checked = "";
		}

		if ( ( $mode == 'view' ) && ( $checked != '' ) ) {
			//$disabled = " disabled  ";
			$output .= $comma . htmlspecialchars( $row["description"], ENT_QUOTES );
			$comma  = ", ";
		} else if ( ( $mode != 'view' ) ) {
			$disabled = "";

			$output .= '<input class="dynamic_form_checkbox_style" id="id' . htmlspecialchars( $field_id . $row['code'], ENT_QUOTES ) . '" type="checkbox" ' . $checked . $disabled . ' name="' . htmlspecialchars( $field_id, ENT_QUOTES ) . '[]" value="' . htmlspecialchars( $row['code'], ENT_QUOTES ) . '">';
			$output .= '<label for="id' . htmlspecialchars( $field_id . $row["code"], ENT_QUOTES ) . '">' . htmlspecialchars( $row["description"], ENT_QUOTES ) . '</label> <br>';
		}
	}

	echo $output;
}

function form_mselect_field( $field_id, $selected, $size, $mode ) {

	$field_id = intval( $field_id );

	if ( $_SESSION['MDS_LANG'] != '' ) {

		$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "codes_translations` WHERE `field_id`='$field_id' and lang='" . get_lang() . "' ";
	} else {
		$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "codes` WHERE `field_id`='$field_id' ";
	}

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

	$selected_codes = explode( ",", $selected );

	$output = "";
	if ( $mode == 'view' ) {
		require_once( "code_functions.php" );
		$comma = '';
		foreach ( $selected_codes as $code ) {
			$output .= $comma . htmlspecialchars( getCodeDescription( $field_id, $code ), ENT_QUOTES );
			$comma  = ', ';
		}
	} else {

		echo "<select name='" . htmlspecialchars( $field_id, ENT_QUOTES ) . "[]' multiple size='" . intval( $size ) . "' >";
		while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {

			if ( in_array( $row['code'], $selected_codes ) ) {
				$checked = " selected ";
			} else {
				$checked = "";
			}

//			if ( $mode == 'view' ) {
			//$disabled = " disabled  ";
//			} else {
//				$disabled = "";
//			}

			$output .= "<option " . $checked . " value='" . htmlspecialchars( $row['code'], ENT_QUOTES ) . "'>" . htmlspecialchars( $row['description'], ENT_QUOTES ) . "</option>";
		}

		$output .= "</select>";
	}

	echo $output;
}

// Not just get..() anymore , but also saves / deletes images, and updates the skills matrix fields..
function get_sql_values( $form_id, $table_name, $object_name, $object_id, $user_id, $op ) {
	$form_id = intval( $form_id );

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "form_fields WHERE form_id='$form_id' AND field_type != 'SEPERATOR' AND field_type != 'BLANK' AND field_type != 'NOTE'  ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

	$ret                 = array();
	$ret['extra_values'] = '';
	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {

		if ( isset( $_POST[ $row['field_id'] ] ) || isset($_FILES[ $row['field_id'] ]) ) {

            switch ( $row['field_type'] ) {

                case "IMAGE":
                    if ( isset($_FILES[ $row['field_id'] ]) && $_FILES[ $row['field_id'] ]['name'] != '' ) {
                        $file_name                 = saveImage( $row['field_id'] );
                        $_POST[ $row['field_id'] ] = $file_name;
                        // delete the old image
                        if ( $object_id != '' ) {
                            deleteImage( $table_name, $object_name, $object_id, $row['field_id'] );
                        }
                        $ret[ $row['field_id'] ] = $_POST[ $row['field_id'] ];
                        if ( $op == "update" ) {
                            $ret['extra_values'] .= ", `" . $row['field_id'] . "`='" . mysqli_real_escape_string( $GLOBALS['connection'], $file_name ) . "'";
                        } else if ( $op == "insert" ) {
                            $ret['extra_values'] .= ", '" . mysqli_real_escape_string( $GLOBALS['connection'], $_POST[ $row['field_id'] ] ) . "'";
                        }
                    } else {
                        $ret[ $row['field_id'] ] = '';
                        if ( $op == "insert" ) {
                            $ret['extra_values'] .= ", ''";
                        }
                    }
                    break;
                case "FILE":
                    if ( $_FILES[ $row['field_id'] ]['name'] != '' ) {
                        $file_name = saveFile( $row['field_id'] );
                        $mime_type = $_FILES[ $row['field_id'] ]['type'];
                        if ( $op == "insert" ) {
                            $_POST[ $row['field_id'] ] = $file_name;
                        }
                        // delete the old image
                        if ( $object_id != '' ) {
                            deleteFile( $table_name, $object_name, $object_id, $row['field_id'] );
                        }
                        $ret[ $row['field_id'] ] = $_POST[ $row['field_id'] ];
                        if ( $op == "update" ) {
                            $ret['extra_values'] .= ", `" . $row['field_id'] . "`='" . mysqli_real_escape_string( $GLOBALS['connection'], $file_name ) . "'";
                        } else if ( $op == "insert" ) {
                            $ret['extra_values'] .= ", '" . mysqli_real_escape_string( $GLOBALS['connection'], $_POST[ $row['field_id'] ] ) . "'";
                        }
                    } else {
                        $ret[ $row['field_id'] ] = '';
                        if ( $op == "insert" ) {
                            $ret['extra_values'] .= ", ''";
                        }
                    }
                    break;
                case "DATE":
                    $day   = $_POST[ $row['field_id'] . "d" ];
                    $month = $_POST[ $row['field_id'] . "m" ];
                    $year  = $_POST[ $row['field_id'] . "y" ];

                    if ( ! checkdate( $month, $day, $year ) ) {
                        // invalid date so use epoc
                        $day   = 1;
                        $month = 1;
                        $year  = 1970;
                    }

                    $_POST[ $row['field_id'] ] = $year . "-" . $month . "-" . $day;
                    $ret[ $row['field_id'] ]   = $_POST[ $row['field_id'] ];
                    if ( $op == "update" ) {
                        $ret['extra_values'] .= ", `" . $row['field_id'] . "`='" . mysqli_real_escape_string( $GLOBALS['connection'], $_POST[ $row['field_id'] ] ) . "'";
                    } else if ( $op == "insert" ) {
                        $ret['extra_values'] .= ",'" . mysqli_real_escape_string( $GLOBALS['connection'], $_POST[ $row['field_id'] ] ) . "'";
                    }
                    break;
                case "DATE_CAL":
                    $temp_time               = strtotime( $_POST[ $row['field_id'] ] . " GMT" );
                    $day                     = date( 'd', $temp_time );
                    $month                   = date( 'm', $temp_time );
                    $year                    = date( 'y', $temp_time );
                    $ret[ $row['field_id'] ] = $year . "-" . $month . "-" . $day;
                    if ( $op == "update" ) {
                        $ret['extra_values'] .= ", `" . $row['field_id'] . "`='" . mysqli_real_escape_string( $GLOBALS['connection'], $year . "-" . $month . "-" . $day ) . "'";
                    } else if ( $op == "insert" ) {
                        $ret['extra_values'] .= ", '" . mysqli_real_escape_string( $GLOBALS['connection'], $year . "-" . $month . "-" . $day ) . "'";
                    }
                    break;
                case "CHECK":

                    $selected_codes = array();
                    $selected_codes = $_POST[ $row['field_id'] ]; // the field comes in as an array
                    $tmp            = "";
                    $comma          = "";
                    for ( $i = 0; $i < sizeof( $selected_codes ); $i ++ ) {
                        if ( $i > 0 ) {
                            $comma = ',';
                        }
                        $tmp .= $comma . $selected_codes[ $i ] . "";
                    }

                    $_POST[ $row['field_id'] ] = $tmp;
                    $ret[ $row['field_id'] ]   = $_POST[ $row['field_id'] ];
                    if ( $op == "update" ) {
                        $ret['extra_values'] .= ", `" . $row['field_id'] . "`='" . mysqli_real_escape_string( $GLOBALS['connection'], $_POST[ $row['field_id'] ] ) . "'";
                    } else if ( $op == "insert" ) {
                        $ret['extra_values'] .= ", '" . mysqli_real_escape_string( $GLOBALS['connection'], $_POST[ $row['field_id'] ] ) . "'";
                    }
                    $ret['extra_values'] .= ", `" . $row['field_id'] . "`='" . mysqli_real_escape_string( $GLOBALS['connection'], $_POST[ $row['field_id'] ] ) . "'";
                    break;

                case "MSELECT":
                    $selected_codes = array();
                    $selected_codes = $_POST[ $row['field_id'] ]; // the field comes in as an array
                    $tmp            = "";
                    $comma          = "";
                    for ( $i = 0; $i < sizeof( $selected_codes ); $i ++ ) {
                        if ( $i > 0 ) {
                            $comma = ',';
                        }
                        $tmp .= $comma . $selected_codes[ $i ] . "";
                    }

                    $_POST[ $row['field_id'] ] = $tmp;
                    $ret[ $row['field_id'] ]   = $_POST[ $row['field_id'] ];
                    if ( $op == "update" ) {
                        $ret['extra_values'] .= ", `" . $row['field_id'] . "`='" . mysqli_real_escape_string( $GLOBALS['connection'], $_POST[ $row['field_id'] ] ) . "'";
                    } else if ( $op == "insert" ) {
                        $ret['extra_values'] .= ", '" . mysqli_real_escape_string( $GLOBALS['connection'], $_POST[ $row['field_id'] ] ) . "'";
                    }
                    break;
                case "TEXT":
                    if ( $op == "update" ) {
                        $ret['extra_values'] .= ", `" . $row['field_id'] . "`='" . mysqli_real_escape_string( $GLOBALS['connection'], html_entity_decode( $_POST[ $row['field_id'] ] ) ) . "'";
                    } else if ( $op == "insert" ) {
                        $ret['extra_values'] .= ", '" . mysqli_real_escape_string( $GLOBALS['connection'], $_POST[ $row['field_id'] ] ) . "'";
                    }
                    break;
                default:
                    $ret[ $row['field_id'] ] = $_POST[ $row['field_id'] ];
                    if ( $op == "update" ) {
                        $ret['extra_values'] .= ", `" . $row['field_id'] . "`='" . mysqli_real_escape_string( $GLOBALS['connection'], $_POST[ $row['field_id'] ] ) . "'";
                    } else if ( $op == "insert" ) {
                        $ret['extra_values'] .= ", '" . mysqli_real_escape_string( $GLOBALS['connection'], $_POST[ $row['field_id'] ] ) . "'";
                    }
                    break;
            }
        } else {
            // Add extra_values for any other queries
			if ( $op == "update" ) {
				$ret['extra_values'] .= ", `" . $row['field_id'] . "`=''";
			} else if ( $op == "insert" ) {
				$ret['extra_values'] .= ", ''";
			}
        }
	}

	return $ret;
}

# Load in the search values.

function tag_to_search_init( $form_id ) {
	global $f2;

	$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "form_fields`, `" . MDS_DB_PREFIX . "form_field_translations` WHERE " . MDS_DB_PREFIX . "form_fields.field_id=" . MDS_DB_PREFIX . "form_field_translations.field_id AND " . MDS_DB_PREFIX . "form_fields.form_id='" . intval( $form_id ) . "' AND is_in_search ='Y' AND " . MDS_DB_PREFIX . "form_field_translations.lang='" . get_lang() . "' ORDER BY search_sort_order";
	//echo $sql;

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
	# do a query for each field
	$tag_to_search = array();
	while ( $fields = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {

		//$form_data = $row[]
		$tag_to_search[ $fields['template_tag'] ]['field_id']          = $fields['field_id'];
		$tag_to_search[ $fields['template_tag'] ]['field_type']        = $fields['field_type'];
		$tag_to_search[ $fields['template_tag'] ]['field_label']       = $fields['field_label'];
		$tag_to_search[ $fields['template_tag'] ]['field_init']        = $fields['field_init'];
		$tag_to_search[ $fields['template_tag'] ]['category_init_id']  = $fields['category_init_id'];
		$tag_to_search[ $fields['template_tag'] ]['field_height']      = $fields['field_height'];
		$tag_to_search[ $fields['template_tag'] ]['is_cat_multiple']   = $fields['is_cat_multiple'];
		$tag_to_search[ $fields['template_tag'] ]['cat_multiple_rows'] = $fields['cat_multiple_rows'];
		$tag_to_search[ $fields['template_tag'] ]['multiple_sel_all']  = $fields['multiple_sel_all'];
	}

	return $tag_to_search;
}

// get the already initalized struct
function get_tag_to_search( $form_id ) {

	//global $tag_to_search;

	switch ( $form_id ) {
		case 1:
			global $ad_tag_to_search;
			$tag_to_search = &$ad_tag_to_search;
			break;
	}

	return $tag_to_search;
}

// get the already initalized structure
function get_tag_to_field_id( $form_id ) {

	$tag_to_field_id = null;
	switch ( $form_id ) {
		case 1:
			global $ad_tag_to_field_id;
			$tag_to_field_id = &$ad_tag_to_field_id;
			break;
	}

	return $tag_to_field_id;
}

function generate_search_sql( $form_id ) {

	global $f2, $action, $tag_to_search;
	$tag_to_search = get_tag_to_search( $form_id );

	if ( func_num_args() > 1 ) {
		$_SEARCH_INPUT = func_get_arg( 1 ); // get search input passed as argument

	} else {
		$_SEARCH_INPUT = $_REQUEST; // get the search input that was posted
	}

	global $label; // from the languages file.
	$where_sql = $or = "";

	if ( $_SEARCH_INPUT['action'] == 'search' ) {

		//print_r ($tag_to_search);

		foreach ( $tag_to_search as $key => $val ) {
			$name = $tag_to_search[ $key ]['field_id'];

			$where_sql = $or = "";
			switch ( $tag_to_search[ $key ]['field_type'] ) {

				case 'CHECK':
					$tmp   = '';
					$comma = '';
					## process all possible options
					$sql = "SELECT * from " . MDS_DB_PREFIX . "codes where field_id='" . mysqli_real_escape_string( $GLOBALS['connection'], $name ) . "' ";
					$code_result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

					//echo $sql;
					$i = 0;
					while ( $code = mysqli_fetch_array( $code_result, MYSQLI_ASSOC ) ) {
						$val = $code['field_id'] . "-" . $code['code'];
						if ( $_SEARCH_INPUT[ $val ] != '' ) {
							if ( $i > 0 ) {
								$comma = 'OR';
							}
							$tmp .= $comma . " `$name` LIKE '%" . $code['code'] . "%' ";
							$i ++;
						}
					}
					//$_POST[$row['field_id']] = $tmp;
					if ( $i > 0 ) {
						$where_sql .= "  AND (" . $tmp . ") ";
					}

					break;

				case 'MSELECT':
					$tmp            = '';
					$comma          = '';
					$selected_codes = array();
					$selected_codes = $_SEARCH_INPUT[ $name ];
					for ( $i = 0; $i < sizeof( $selected_codes ); $i ++ ) {
						if ( $i > 0 ) {
							$comma = 'OR';
						}
						$tmp .= $comma . " `$name` LIKE '%" . $selected_codes[ $i ] . "%' ";
					}

					if ( $i > 0 ) {
						$where_sql .= "  AND (" . $tmp . ") ";
					}

					break;

				case 'SKILL_MATRIX':

					if ( trim( $_SEARCH_INPUT[ $name . 'name' ] ) != '' ) {

						if ( ! is_numeric( $_SEARCH_INPUT[ $name . 'rating' ] ) ) {
							$_SEARCH_INPUT[ $name . 'rating' ] = '0';
						}
						if ( ! is_numeric( $_SEARCH_INPUT[ $name . 'years' ] ) ) {
							$_SEARCH_INPUT[ $name . 'years' ] = '0';
						}

						$where_sql .= " AND t2.name LIKE '" . trim( $_SEARCH_INPUT[ $name . 'name' ] ) . "' AND t2.years >= " . $_SEARCH_INPUT[ $name . 'years' ] . " AND t2.rating >= " . $_SEARCH_INPUT[ $name . 'rating' ] . " ";
					}

					break;

				case 'DATE':
					$day                    = $_REQUEST[ $name . "d" ];
					$month                  = $_REQUEST[ $name . "m" ];
					$year                   = $_REQUEST[ $name . "y" ];
					$_SEARCH_INPUT[ $name ] = "$year-$month-$day";
				case 'DATE_CAL':
					$value     = $_SEARCH_INPUT[ $name ];
					$where_sql .= " AND (`$name` >= '$value') ";
					break;

				default:
					$value = $_SEARCH_INPUT[ $name ];
					if ( $value != '' ) {
						$list = preg_split( "/[\s,]+/", $value );
						//print_r ($list);
						for ( $i = 1; $i < sizeof( $list ); $i ++ ) {
							$or .= " AND (`$name` like '%" . $list[ $i ] . "%')  ";
						}
						$where_sql .= " AND ((`$name` like '%" . $list[0] . "%')  $or)";
					}
			}
		}
	}

	return $where_sql;

	?>
	<?php
}

function is_reserved_field( $field_id ) {
	global $f2;

	if ( $field_id == false ) {
		return $field_id;
	}

	$field_id = intval( $field_id );

	$sql = "SELECT * from `" . MDS_DB_PREFIX . "form_fields` WHERE field_id='$field_id' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result, MYSQLI_ASSOC );

	if ( is_reserved_template_tag( $row['template_tag'] ) ) {
		return true;
	}

	return false;
}

function is_reserved_template_tag( $str ) {
//return false;
	switch ( $str ) {

		case "ALT_TEXT":
			return true;
		case "URL":
			return true;

		default:
			return false;
	}
}

function get_reserved_tag_description( $str ) {

	switch ( $str ) {

		case "ALT_TEXT":
			return 'reserved by the system (Default Ad text, used for the alt text)';
		case "URL":
			return 'reserved by the system (url when pixel is clicked)';
		default:
			return false;
	}
}

function build_sort_fields( $form_id, $section ) {
	global $f2;

	$form_id = intval( $form_id );
	$section = intval( $section );

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "form_fields where `form_id`='$form_id' and section='$section' order by field_sort ASC";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
	$order = 1;
	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {

		//$fields[$row['field_id']] = $row[''];
		$field_id = intval( $row['field_id'] );
		$sql      = "UPDATE " . MDS_DB_PREFIX . "form_fields SET `field_sort`='$order' WHERE form_id='$form_id' AND field_id='" . $field_id . "' ";

		//echo $sql." ".$row['field_label']."(".$row['field_sort'].")<br>";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
		$order ++;
	}
}

function move_field_up( $form_id, $field_id ) {

	$field = mds_get_field( $form_id, $field_id );

	$section = $field['section'];

	# get current order
	$now_order = $field['field_sort']; //get_field_order ($form_id, $field_id);
	$new_order = $now_order - 1;

	if ( $new_order == 0 ) {
		return; // already the top field
	}

	// top goes to bottom
	$sql = "UPDATE " . MDS_DB_PREFIX . "form_fields SET `field_sort`=field_sort+1 WHERE form_id='$form_id' AND field_sort='" . $new_order . "' AND `section`='$section' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

	// field_id moves up
	$sql = "UPDATE " . MDS_DB_PREFIX . "form_fields SET `field_sort`=$new_order WHERE form_id='$form_id' AND field_id='" . $field_id . "' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
}

function move_field_down( $form_id, $field_id ) {

	$form_id = intval( $form_id );

	$field = mds_get_field( $form_id, $field_id );

	$section = intval( $field['section'] );

	# get current order
	$now_order = intval( $field['field_sort'] ); //get_field_order ($form_id, $field_id);
	$new_order = $now_order + 1;

	$sql = "SELECT max(field_sort) as the_max from " . MDS_DB_PREFIX . "form_fields where form_id='$form_id' AND section='$section'  ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

	$row = mysqli_fetch_array( $result, MYSQLI_ASSOC );
	//echo "the max:".$row['the_max']." new oreer".$new_order;
	if ( $new_order > $row['the_max'] ) {
//echo $sql."<br>";
//echo "the max:".$row['the_max']." ".$new_order;
		return; //already at the bottom
	}

	// bottom goes to top
	$sql = "UPDATE " . MDS_DB_PREFIX . "form_fields SET `field_sort`=field_sort-1 WHERE form_id='$form_id' AND field_sort='" . $new_order . "' AND `section`='$section' ";
	//echo $sql."<br>";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

	// field_id moves up
	$sql = "UPDATE " . MDS_DB_PREFIX . "form_fields SET `field_sort`=$new_order WHERE form_id='$form_id' AND field_id='" . $field_id . "' ";
	//echo $sql."<br>";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
}

function get_field_order( $form_id, $field_id ) {

	$sql = "SELECT * from " . MDS_DB_PREFIX . "form_fields where `form_id`='" . intval( $form_id ) . "' AND field_id='" . intval( $field_id ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result, MYSQLI_ASSOC );

	return $row['field_sort'];
}

function mds_get_field( $form_id, $field_id ) {

	$form_id  = intval( $form_id );
	$field_id = intval( $field_id );

	$sql = "SELECT * from " . MDS_DB_PREFIX . "form_fields where `form_id`='$form_id' AND field_id='$field_id' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

	return mysqli_fetch_array( $result, MYSQLI_ASSOC );
}

function is_table_unsaved( $tname ) {

	$tname = MDS_DB_PREFIX . "ads";
	$cols  = $fields = array();

	// load cols
	$sql = " show columns from $tname ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
	while ( $row = mysqli_fetch_row( $result ) ) {
		if ( preg_match( "/^\d+$/", $row[0] ) ) {
			$cols[ $row[0] ] = $row[0];
		}
	}

	if ( $tname == MDS_DB_PREFIX . "ads" ) {
		$form_id = 1;
	} else {
		return true;
	}

	// load fields
	$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "form_fields` where form_id=$form_id AND field_type != 'BLANK' AND field_type !='SEPERATOR' AND field_type !='NOTE'  ";
	//echo $sql;
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );

	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
		$fields[ $row['field_id'] ] = $row['field_id'];
	}

	// check table

	foreach ( $cols as $c ) {
		if ( $fields[ $c ] == '' ) {
			return $c;
		}
	}

	// check fields

	foreach ( $fields as $f ) {
		if ( $cols[ $f ] == '' ) {
			return $f;
		}
	}

	return false;
}

function generate_template_tag( $form_id ) {
	// generate a random template tag. This help to fix older versions of the JB where some fields did not have a template tag...

	$form_id = intval( $form_id );

	// generate a tag.
	$template_tag = '';
	while ( strlen( $template_tag ) < 4 ) {
		$template_tag .= chr( rand( 97, 122 ) );
	}

	$unique = false;

	$sql = "select field_id from " . MDS_DB_PREFIX . "form_fields where template_tag='$template_tag' and form_id='$form_id' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( "SQL:" . $sql . "<br />ERROR: " . mysqli_error( $GLOBALS['connection'] ) );
	if ( mysqli_num_rows( $result ) == 0 ) {
		$unique = true;
	}

	// check if it is unique

	if ( $unique ) {

		return $template_tag;
	} else {
		return generate_template_tag( $form_id ); // try again
	}
}

function check_for_bad_words( $data ) {
	$found_bad = false;

	if ( ! defined( "BAD_WORD_FILTER" ) || ! defined( "BAD_WORDS" ) ) {
		return false;
	}

	if ( BAD_WORD_FILTER != 'YES' ) {
		return false;
	}

	$bad_words = trim( BAD_WORDS );
	if ( strlen( $bad_words ) == 0 ) {
		return false;
	}

	$baddies = preg_split( "/[\s,]+/", BAD_WORDS );

	foreach ( $baddies as $bad ) {
		if ( preg_match( "/\b$bad\b/", $data ) ) {
			$found_bad = true;
		}
	}

	return $found_bad;
}

?>