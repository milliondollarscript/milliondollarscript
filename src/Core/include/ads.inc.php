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

use Imagine\Filter\Basic\Autorotate;

require_once __DIR__ . '/lists.inc.php';
require_once __DIR__ . '/dynamic_forms.php';

global $ad_tag_to_field_id, $ad_tag_to_search;
$ad_tag_to_search   = tag_to_search_init( 1 );
$ad_tag_to_field_id = ad_tag_to_field_id_init();

function ad_tag_to_field_id_init() {

	$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "form_fields`, " . MDS_DB_PREFIX . "form_field_translations WHERE " . MDS_DB_PREFIX . "form_fields.field_id = " . MDS_DB_PREFIX . "form_field_translations.field_id AND " . MDS_DB_PREFIX . "form_field_translations.lang='" . get_lang() . "' AND form_id=1 ORDER BY list_sort_order ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	// do a query for each field
	while ( $fields = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {

		$tag_to_field_id[ $fields['template_tag'] ]['field_id']    = $fields['field_id'];
		$tag_to_field_id[ $fields['template_tag'] ]['field_type']  = $fields['field_type'];
		$tag_to_field_id[ $fields['template_tag'] ]['field_label'] = $fields['field_label'];
	}

	$tag_to_field_id["ORDER_ID"]['field_id']    = 'order_id';
	$tag_to_field_id["ORDER_ID"]['field_label'] = 'Order ID';

	$tag_to_field_id["BID"]['field_id']    = 'banner_id';
	$tag_to_field_id["BID"]['field_label'] = 'Grid ID';

	$tag_to_field_id["USER_ID"]['field_id']    = 'user_id';
	$tag_to_field_id["USER_ID"]['field_label'] = 'User ID';

	$tag_to_field_id["AD_ID"]['field_id']    = 'ad_id';
	$tag_to_field_id["AD_ID"]['field_label'] = 'Ad ID';

	$tag_to_field_id["DATE"]['field_id']    = 'ad_date';
	$tag_to_field_id["DATE"]['field_label'] = 'Date';

	return $tag_to_field_id;
}

function load_ad_values( $ad_id ) {

	global $purifier;

	$prams = array();

	$ad_id = intval( $ad_id );

	$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "ads` WHERE ad_id='$ad_id'   ";

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( $sql . mysqli_error( $GLOBALS['connection'] ) );

	if ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {

		$prams['ad_id']     = $ad_id;
		$prams['user_id']   = $row['user_id'];
		$prams['order_id']  = $row['order_id'];
		$prams['banner_id'] = $row['banner_id'];

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "form_fields WHERE form_id=1 AND field_type != 'SEPERATOR' AND field_type != 'BLANK' AND field_type != 'NOTE' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		while ( $fields = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {

			$prams[ $fields['field_id'] ] = $row[ $fields['field_id'] ];

			if ( $fields['field_type'] == 'DATE' ) {
				$date = date_parse( $row[ $fields['field_id'] ] );

				$day   = $date['day'];
				$month = $date['month'];
				$year  = $date['year'];

				if ( ! checkdate( $month, $day, $year ) ) {
					// invalid date so use epoc
					$day   = 1;
					$month = 1;
					$year  = 1970;
				}

				$prams[ $fields['field_id'] ] = "$year-$month-$day";
			} else if ( ( $fields['field_type'] == 'MSELECT' ) || ( $fields['field_type'] == 'CHECK' ) ) {
				if ( is_array( $_REQUEST[ $row['field_id'] ] ) ) {
					$prams[ $fields['field_id'] ] = implode( ",", $_REQUEST[ $fields['field_id'] ] );
				} else {
					$prams[ $fields['field_id'] ] = $_REQUEST[ $fields['field_id'] ];
				}
			} else if ( $fields['field_type'] == 'TEXTAREA' ) {
				$val                          = escape_html( $prams[ $fields['field_id'] ] );
				$val                          = str_replace( "\n", "<br>", $val );
				$prams[ $fields['field_id'] ] = $purifier->purify( $val );
			}
		}

		return $prams;
	} else {
		return false;
	}
}

function assign_ad_template( $prams ) {

	global $label, $prams;

	$str = $label['mouseover_ad_template'];

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "form_fields WHERE form_id='1' AND field_type != 'SEPERATOR' AND field_type != 'BLANK' AND field_type != 'NOTE' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
		if ( $row['field_type'] == 'IMAGE' ) {
			if ( ( file_exists( UPLOAD_PATH . 'images/' . $prams[ $row['field_id'] ] ) ) && ( ! empty( $prams[ $row['field_id'] ] ) ) ) {
				$str = str_replace( '%' . $row['template_tag'] . '%', '<img alt="" src="' . UPLOAD_HTTP_PATH . "images/" . $prams[ $row['field_id'] ] . '" style="max-width:100px;max-height:100px;">', $str );
			} else {
				//$str = str_replace('%'.$row['template_tag'].'%',  '<IMG SRC="'.UPLOAD_HTTP_PATH.'images/no-image.gif" WIDTH="150" HEIGHT="150" BORDER="0" ALT="">', $str);
				$str = str_replace( '%' . $row['template_tag'] . '%', '', $str );
			}
		} else if ( $row['template_tag'] == 'URL' ) {
			$str   = str_replace( 'href="http://%URL%"', 'href="%URL%"', $str );
			$value = get_template_value( $row['template_tag'], 1 );
			$url   = parse_url( $value );
			if ( empty( $url['scheme'] ) ) {
				$value = 'https://' . $value;
			}
			$value = '<a class="pixel-url" href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a>';
			$str   = str_replace( '%' . $row['template_tag'] . '%', $value, $str );
		} else {
			$str = str_replace( '%' . $row['template_tag'] . '%', get_template_value( $row['template_tag'], 1 ), $str );
		}

		$str = str_replace( '$' . $row['template_tag'] . '$', get_template_field_label( $row['template_tag'], 1 ), $str );
	}

	return $str;
}

function display_ad_form( $form_id, $mode, $prams ) {

	global $f2, $label, $error, $BID;

	if ( $prams == '' ) {
		$prams              = array();
		$prams['mode']      = ( isset( $_REQUEST['mode'] ) ? $_REQUEST['mode'] : "" );
		$prams['ad_id']     = ( isset( $_REQUEST['aid'] ) ? $_REQUEST['aid'] : "" );
		$prams['banner_id'] = $BID;
		$prams['user_id']   = ( isset( $_REQUEST['user_id'] ) ? $_REQUEST['user_id'] : "" );

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "form_fields WHERE form_id='" . intval( $form_id ) . "' AND field_type != 'SEPERATOR' AND field_type != 'BLANK' AND field_type != 'NOTE' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {

			if ( $row['field_type'] == 'DATE' ) {
				$day   = $_REQUEST[ $row['field_id'] . "d" ];
				$month = $_REQUEST[ $row['field_id'] . "m" ];
				$year  = $_REQUEST[ $row['field_id'] . "y" ];

				if ( ! checkdate( $month, $day, $year ) ) {
					// invalid date so use epoc
					$day   = 1;
					$month = 1;
					$year  = 1970;
				}

				$prams[ $row['field_id'] ] = "$year-$month-$day";
			} else if ( ( $row['field_type'] == 'MSELECT' ) || ( $row['field_type'] == 'CHECK' ) ) {
				if ( is_array( $_REQUEST[ $row['field_id'] ] ) ) {
					$prams[ $row['field_id'] ] = implode( ",", $_REQUEST[ $row['field_id'] ] );
				} else {
					$prams[ $row['field_id'] ] = $_REQUEST[ $row['field_id'] ];
				}
			} else {
				$prams[ $row['field_id'] ] = stripslashes( isset( $_REQUEST[ $row['field_id'] ] ) ? $_REQUEST[ $row['field_id'] ] : "" );
			}
		}
	}

	$mode      = ( isset( $mode ) && in_array( $mode, array( "edit", "user" ) ) ) ? $mode : "";
	$ad_id     = isset( $prams['ad_id'] ) ? intval( $prams['ad_id'] ) : "";
	$user_id   = isset( $prams['user_id'] ) ? intval( $prams['user_id'] ) : "";
	$order_id  = isset( $prams['order_id'] ) ? intval( $prams['order_id'] ) : "";
	$banner_id = isset( $prams['banner_id'] ) ? intval( $prams['banner_id'] ) : "";
	$action    = strpos( $_SERVER['PHP_SELF'], '/admin/' ) !== false ? basename( $_SERVER['PHP_SELF'] ) : $_SERVER['PHP_SELF'];
	?>
    <form method="POST" action="<?php echo htmlentities( $action ); ?>" name="form1" enctype="multipart/form-data">
        <input type="hidden" name="0" value="placeholder">
        <input type="hidden" name="mode" value="<?php echo $mode; ?>">
        <input type="hidden" name="aid" value="<?php echo $ad_id; ?>">
        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
        <input type="hidden" name="BID" value="<?php echo $banner_id; ?>">

        <div class="flex-container">
			<?php if ( ( $error != '' ) && ( $mode != 'edit' ) ) { ?>
                <div class="error_msg">
					<?php echo "<span class='error_msg_label'>" . $label['ad_save_error'] . "</span><br> <b>" . $error . "</b>"; ?>
                </div>
			<?php } ?>
			<?php
			if ( $mode == "edit" ) {
				echo "[Ad Form]";
			}

			// section 1
			mds_display_form( $form_id, $mode, $prams, 1 );
			?>
            <div class="flex-row">
                <input type="hidden" name="save" id="save101" value="">
				<?php if ( $mode == 'edit' || $mode == 'user' ) { ?>
                    <input<?php echo( $mode == 'edit' ? ' disabled' : '' ); ?> class="mds_save_ad_button form_submit_button big_button" type="submit" name="savebutton" value="<?php echo $label['ad_save_button']; ?>" onClick="save101.value='1';">
				<?php } ?>
            </div>
        </div>
    </form>

	<?php
}

function list_ads( $admin = false, $offset = 0, $list_mode = 'ALL', $user_id = '' ) {

	global $BID, $f2, $label, $tag_to_field_id, $ad_tag_to_field_id, $action;

	$tag_to_field_id  = ad_tag_to_field_id_init();
	$records_per_page = 40;

	// process search result
	$q_string  = "";
	$where_sql = "";
	if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'search' ) {
		$q_string  = generate_q_string( 1 );
		$where_sql = generate_search_sql( 1 );
	}

	$order = ( isset( $_REQUEST['order_by'] ) && $_REQUEST['order_by'] ) ? $_REQUEST['order_by'] : '';

	if ( isset( $_REQUEST['ord'] ) && $_REQUEST['ord'] == 'asc' ) {
		$ord = 'ASC';
	} else if ( isset( $_REQUEST['ord'] ) && $_REQUEST['ord'] == 'desc' ) {
		$ord = 'DESC';
	} else {
		$ord = 'DESC';
	}

	if ( $order == null || $order == '' ) {
		$order = " `ad_date` ";
	} else {
		$order = " `" . mysqli_real_escape_string( $GLOBALS['connection'], $order ) . "` ";
	}

	if ( $list_mode == 'USER' ) {

		if ( ! is_numeric( $user_id ) ) {
			$user_id = $_SESSION['MDS_ID'];
		}

		$sql = "Select *  FROM `" . MDS_DB_PREFIX . "ads`, `" . MDS_DB_PREFIX . "orders` WHERE " . MDS_DB_PREFIX . "ads.ad_id=" . MDS_DB_PREFIX . "orders.ad_id AND " . MDS_DB_PREFIX . "ads.order_id > 0 AND " . MDS_DB_PREFIX . "ads.banner_id='" . intval( $BID ) . "' AND " . MDS_DB_PREFIX . "ads.user_id='" . intval( $user_id ) . "' AND (" . MDS_DB_PREFIX . "orders.status IN ('pending','completed','confirmed','new','expired','renew_wait','renew_paid')) $where_sql ORDER BY $order $ord";
	} else {
		$sql = "Select *  FROM `" . MDS_DB_PREFIX . "ads`, `" . MDS_DB_PREFIX . "orders` WHERE " . MDS_DB_PREFIX . "ads.ad_id=" . MDS_DB_PREFIX . "orders.ad_id AND " . MDS_DB_PREFIX . "ads.banner_id='" . intval( $BID ) . "' and " . MDS_DB_PREFIX . "ads.order_id > 0 AND " . MDS_DB_PREFIX . "orders.status != 'deleted' $where_sql ORDER BY $order $ord";
	}

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	// get the count
	$count = mysqli_num_rows( $result );

	if ( $count > $records_per_page ) {
		mysqli_data_seek( $result, $offset );
	}

	if ( $count > 0 ) {

		if ( $list_mode != 'USER' ) {

			$pages    = ceil( $count / $records_per_page );
			$cur_page = intval( $offset ) / $records_per_page;
			$cur_page ++;

			$label["navigation_page"] = str_replace( "%CUR_PAGE%", $cur_page, $label["navigation_page"] );
			$label["navigation_page"] = str_replace( "%PAGES%", $pages, $label["navigation_page"] );
			echo "<span > " . $label["navigation_page"] . "</span> ";
			$nav   = nav_pages_struct( $q_string, $count, $records_per_page );
			$LINKS = 10;
			render_nav_pages( $nav, $LINKS, $q_string );
		}

		?>
        <table border='0' bgcolor='#d9d9d9' cellspacing="1" cellpadding="5" id="pixels_list">
            <tr bgcolor="#EAEAEA">
				<?php
				if ( $admin == true ) {
					echo '<td class="list_header_cell">&nbsp;</td>';
				}

				if ( $list_mode == 'USER' ) {
					echo '<td class="list_header_cell">&nbsp;</td>';
				}

				echo_list_head_data( 1, $admin );

				if ( ( $list_mode == 'USER' ) || ( $admin ) ) {
					echo '<td class="list_header_cell">' . $label['ads_inc_pixels_col'] . '</td>';
					echo '<td class="list_header_cell">' . $label['ads_inc_expires_col'] . '</td>';
					echo '<td class="list_header_cell" >' . $label['ad_list_status'] . '</td>';
				}

				?>

            </tr>

			<?php
			$i = 0;
			global $prams;
			while ( ( $prams = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) && ( $i < $records_per_page ) ) {

				$i ++;

				?>
                <tr bgcolor="ffffff" onmouseover="old_bg=this.getAttribute('bgcolor');this.setAttribute('bgcolor', '#FBFDDB', 0);" onmouseout="this.setAttribute('bgcolor', old_bg, 0);">

					<?php

					if ( $admin == true ) {
						?>
                        <td class="list_data_cell">
                            <input type="button" style="font-size: 8pt" value="<?php echo $label['ads_inc_edit']; ?>" onClick="mds_load_page('<?php echo htmlspecialchars( $_SERVER['PHP_SELF'] ); ?>?action=edit&amp;aid=<?php echo $prams['ad_id']; ?>', true)">
                        </td>
						<?php
					}

					if ( $list_mode == 'USER' ) {
						?>
                        <td class="list_data_cell">
                            <input type="button" style="font-size: 8pt" value="<?php echo $label['ads_inc_edit']; ?>" onClick="window.location='<?php echo htmlspecialchars( $_SERVER['PHP_SELF'] ); ?>?aid=<?php echo $prams['ad_id']; ?>'">
                        </td>
						<?php
					}

					echo_ad_list_data( $admin );

					if ( ( $list_mode == 'USER' ) || ( $admin ) ) {
						?>
                        <td class="list_data_cell"><img src="get_order_image.php?BID=<?php echo $BID; ?>&amp;aid=<?php echo $prams['ad_id']; ?>"></td>
                        <td>
							<?php
							if ( $prams['days_expire'] > 0 ) {

								if ( $prams['published'] != 'Y' ) {
									$time_start = strtotime( gmdate( 'r' ) );
								} else {
									$time_start = strtotime( $prams['date_published'] . " GMT" );
								}

								$elapsed_time = strtotime( gmdate( 'r' ) ) - $time_start;
								$elapsed_days = floor( $elapsed_time / 60 / 60 );

								$exp_time = ( $prams['days_expire'] * 60 * 60 );

								$exp_time_to_go = $exp_time - $elapsed_time;
								$exp_days_to_go = floor( $exp_time_to_go / 60 / 60 );

								$to_go = elapsedtime( $exp_time_to_go );

								$elapsed = elapsedtime( $elapsed_time );

								if ( $prams['status'] == 'expired' ) {
									$days = "<a href='orders.php'>" . $label['ads_inc_expied_stat'] . "</a>";
								} else if ( $prams['date_published'] == '' ) {
									$days = $label['ads_inc_nyp_stat'];
								} else {
									$days = str_replace( '%ELAPSED%', $elapsed, $label['ads_inc_elapsed_stat'] );
									$days = str_replace( '%TO_GO%', $to_go, $days );
								}
							} else {

								$days = $label['ads_inc_nev_stat'];
							}
							echo $days;
							?>
                        </td>
						<?php
						if ( $prams['published'] == 'Y' ) {
							$pub = $label['ads_inc_pub_stat'];
						} else {
							$pub = $label['ads_inc_npub_stat'];
						}
						if ( $prams['approved'] == 'Y' ) {
							$app = $label['ads_inc_app_stat'] . ', ';
						} else {
							$app = $label['ads_inc_napp_stat'] . ', ';
						}
						?>
                        <td class="list_data_cell"><?php echo $app . $pub; ?></td>
						<?php
					}

					?>

                </tr>
				<?php
			}
			?>
        </table>
		<?php
	} else {

		echo "<center><font size='2' face='Arial'><b>" . $label["ads_not_found"] . ".</b></font></center>";
	}

	return $count;
}

function delete_ads_files( $ad_id ) {

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "form_fields WHERE form_id=1 ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mds_sql_error( $sql ) );

	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {

		$field_id   = $row['field_id'];
		$field_type = $row['field_type'];

		if ( ( $field_type == "FILE" ) ) {

			deleteFile( "ads", "ad_id", $ad_id, $field_id );
		}

		if ( ( $field_type == "IMAGE" ) ) {

			deleteImage( MDS_DB_PREFIX . "ads", "ad_id", $ad_id, $field_id );
		}
	}
}

function delete_ad( $ad_id ) {

	delete_ads_files( $ad_id );

	$sql = "DELETE FROM `" . MDS_DB_PREFIX . "ads` WHERE `ad_id`='" . intval( $ad_id ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mds_sql_error( $sql ) );
}

function generate_ad_id() {

	$sql = "SELECT max(`ad_id`) FROM `" . MDS_DB_PREFIX . "ads`";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
	$row = mysqli_fetch_row( $result );
	$row[0] ++;

	return $row[0];
}

function insert_ad_data() {
	global $f2;

	$admin = false;
	if ( func_num_args() > 0 ) {
		$admin = func_get_arg( 0 ); // admin mode.
	}

	$user_id = $_SESSION['MDS_ID'];
	if ( $user_id == '' ) {
		$user_id = addslashes( session_id() );
	}

	$order_id = ( isset( $_REQUEST['order_id'] ) && ! empty( $_REQUEST['order_id'] ) ) ? $_REQUEST['order_id'] : ( isset( $_SESSION['MDS_order_id'] ) ? $_SESSION['MDS_order_id'] : 0 );
	$BID      = $f2->bid();

	$ad_values = array();

	if ( ! isset( $_REQUEST['aid'] ) || empty( $_REQUEST['aid'] ) ) {

		$ad_id = generate_ad_id();
		$now   = gmdate( "Y-m-d H:i:s" );

		$ad_values = get_sql_values( 1, MDS_DB_PREFIX . "ads", "ad_id", $ad_id, $user_id, 'insert' );
		$values    = $ad_id . ", '" . $user_id . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $now ) . "', " . intval( $order_id ) . ", " . intval( $BID ) . $ad_values['extra_values'];
		$sql       = "REPLACE INTO " . MDS_DB_PREFIX . "ads VALUES (" . $values . ");";
		mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
	} else {

		$ad_id = intval( $_REQUEST['aid'] );

		if ( ! $admin ) {
			// make sure that the logged in user is the owner of this ad.

			if ( ! is_numeric( $_REQUEST['user_id'] ) ) {
				if ( $_REQUEST['user_id'] != session_id() ) {
					return false;
				}
			} else {
				// user is logged in
				$sql = "SELECT user_id FROM `" . MDS_DB_PREFIX . "ads` WHERE ad_id='" . $ad_id . "'";
				$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
				$row = @mysqli_fetch_array( $result );

				if ( $_SESSION['MDS_ID'] !== $row['user_id'] ) {
					// not the owner, hacking attempt!
					return false;
				}
			}
		}

		$now       = gmdate( "Y-m-d H:i:s" );
		$ad_values = get_sql_values( 1, MDS_DB_PREFIX . "ads", "ad_id", $ad_id, $user_id, 'update' );
		$sql       = "UPDATE " . MDS_DB_PREFIX . "ads SET ad_date='$now'" . $ad_values['extra_values'] . " WHERE ad_id='" . $ad_id . "'";
		mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		$f2->write_log( $sql );
	}

	if ( ! empty( $order_id ) ) {

		// update blocks with ad data
		$sql = "SELECT blocks,banner_id FROM " . MDS_DB_PREFIX . "orders WHERE order_id=" . intval( $order_id );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$order_row = mysqli_fetch_array( $result );

		if ( isset( $order_row ) ) {

			$blocks = explode( ',', $order_row['blocks'] );

			foreach ( $blocks as $block ) {
//				$alt_text = mysqli_real_escape_string( $GLOBALS['connection'], $ad_values['1'] );
//				$url      = mysqli_real_escape_string( $GLOBALS['connection'], $ad_values['2'] );

				if ( isset( $ad_values['3'] ) && ! empty( $ad_values['3'] ) ) {
					$filename = mysqli_real_escape_string( $GLOBALS['connection'], $ad_values['3'] );

					$block_id  = intval( $block );
					$banner_id = intval( $order_row['banner_id'] );

					$sql = "UPDATE " . MDS_DB_PREFIX . "blocks SET file_name='{$filename}' WHERE block_id={$block_id} AND banner_id={$banner_id};";
					mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
				}
			}
		}
	}

	return $ad_id;
}

function validate_ad_data( $form_id ) {

	return validate_form_data( 1 );
}

function update_blocks_with_ad( $ad_id, $user_id ) {
	global $prams, $f2;
	$prams = load_ad_values( $ad_id );

	if ( $prams['order_id'] > 0 ) {
		$sql = "UPDATE " . MDS_DB_PREFIX . "blocks SET alt_text='" . mysqli_real_escape_string( $GLOBALS['connection'], get_template_value( 'ALT_TEXT', 1 ) ) . "', url='" . mysqli_real_escape_string( $GLOBALS['connection'], get_template_value( 'URL', 1 ) ) . "'  WHERE order_id='" . intval( $prams['order_id'] ) . "' AND user_id='" . intval( $user_id ) . "' ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$f2->debug( "Updated blocks with ad URL, ALT_TEXT", $sql );
	}
}

function disapprove_modified_order( $order_id, $BID ) {
	$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET approved='N' WHERE order_id='" . intval( $order_id ) . "' AND banner_id='" . intval( $BID ) . "' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$sql = "UPDATE " . MDS_DB_PREFIX . "blocks SET approved='N' WHERE order_id='" . intval( $order_id ) . "' AND banner_id='" . intval( $BID ) . "' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	// send pixel change notification
	if ( EMAIL_ADMIN_PUBLISH_NOTIFY == 'YES' ) {
		send_published_pixels_notification( $_SESSION['MDS_ID'], $BID );
	}
}

function upload_changed_pixels( $order_id, $BID, $size, $banner_data ) {
	global $f2, $label;

	$imagine = new Imagine\Gd\Imagine();

	if ( ( isset( $_REQUEST['change_pixels'] ) && ! empty( $_REQUEST['change_pixels'] ) ) && isset( $_FILES ) ) {
		// a new image was uploaded...

		$uploaddir = \MillionDollarScript\Classes\Utility::get_upload_path() . "grids/";

		$file_parts = pathinfo( $_FILES['pixels']['name'] );
		$ext        = $f2->filter( strtolower( $file_parts['extension'] ) );

		// CHECK THE EXTENSION TO MAKE SURE IT IS ALLOWED
		$ALLOWED_EXT = array( 'jpg', 'jpeg', 'gif', 'png' );

		$error = '';
		if ( ! in_array( $ext, $ALLOWED_EXT ) ) {
			$error = "<b>" . $label['advertiser_file_type_not_supp'] . "</b><br>";
		}

		if ( ! empty( $error ) ) {
			echo $error;
		} else {

			// delete temp_* files older than 24 hours
			$dh = opendir( $uploaddir );
			while ( ( $file = readdir( $dh ) ) !== false ) {

				// 24 hours
				$elapsed_time = 60 * 60 * 24;

				// delete old files
				$stat = stat( $uploaddir . $file );
				if ( $stat[9] < ( time() - $elapsed_time ) ) {
					if ( strpos( $file, 'tmp_' . md5( session_id() ) ) !== false ) {
						unlink( $uploaddir . $file );
					}
				}
			}

			$uploadfile = $uploaddir . "tmp_" . md5( session_id() ) . ".$ext";

			// move the file
			if ( move_uploaded_file( $_FILES['pixels']['tmp_name'], $uploadfile ) ) {
				// File is valid, and was successfully uploaded.
				$tmp_image_file = $uploadfile;

				setMemoryLimit( $uploadfile );

				// check image size
				$img_size = getimagesize( $tmp_image_file );

				// check the size
				if ( ( MDS_RESIZE != 'YES' ) && ( ( $img_size[0] > $size['x'] ) || ( $img_size[1] > $size['y'] ) ) ) {
					$label['adv_pub_sizewrong'] = str_replace( '%SIZE_X%', $size['x'], $label['adv_pub_sizewrong'] );
					$label['adv_pub_sizewrong'] = str_replace( '%SIZE_Y%', $size['y'], $label['adv_pub_sizewrong'] );
					$error                      = $label['adv_pub_sizewrong'] . "<br>";
				}

				if ( ! empty( $error ) ) {
					echo $error;
				} else {
					// size is ok. change the blocks.

					// Imagine some things
					$image      = $imagine->open( $tmp_image_file );
					$block_size = new Imagine\Image\Box( $banner_data['BLK_WIDTH'], $banner_data['BLK_HEIGHT'] );
					$palette    = new Imagine\Image\Palette\RGB();
					$color      = $palette->color( '#000', 0 );

					// Update blocks
					$sql = "SELECT * from " . MDS_DB_PREFIX . "blocks WHERE order_id=" . intval( $order_id );
					$blocks_result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
					while ( $block_row = mysqli_fetch_array( $blocks_result ) ) {

						$high_x = ! isset( $high_x ) ? $block_row['x'] : $high_x;
						$high_y = ! isset( $high_y ) ? $block_row['y'] : $high_y;
						$low_x  = ! isset( $low_x ) ? $block_row['x'] : $low_x;
						$low_y  = ! isset( $low_y ) ? $block_row['y'] : $low_y;

						if ( $block_row['x'] > $high_x ) {
							$high_x = $block_row['x'];
						}

						if ( ! isset( $high_y ) || $block_row['y'] > $high_y ) {
							$high_y = $block_row['y'];
						}

						if ( ! isset( $low_y ) || $block_row['y'] < $low_y ) {
							$low_y = $block_row['y'];
						}

						if ( ! isset( $low_x ) || $block_row['x'] < $low_x ) {
							$low_x = $block_row['x'];
						}
					}

					$high_x = ! isset( $high_x ) ? 0 : $high_x;
					$high_y = ! isset( $high_y ) ? 0 : $high_y;
					$low_x  = ! isset( $low_x ) ? 0 : $low_x;
					$low_y  = ! isset( $low_y ) ? 0 : $low_y;

					$_REQUEST['map_x'] = $high_x;
					$_REQUEST['map_y'] = $high_y;

					// autorotate
					$imagine->setMetadataReader( new \Imagine\Image\Metadata\ExifMetadataReader() );
					$filter = new Imagine\Filter\Transformation();
					$filter->add( new AutoRotate() );
					$filter->apply( $image );

					// resize uploaded image
					if ( MDS_RESIZE == 'YES' ) {
						$resize = new Imagine\Image\Box( $size['x'], $size['y'] );
						$image->resize( $resize );
					}

					// Paste image into selected blocks (AJAX mode allows individual block selection)
					for ( $y = 0; $y < $size['y']; $y += $banner_data['BLK_HEIGHT'] ) {
						for ( $x = 0; $x < $size['x']; $x += $banner_data['BLK_WIDTH'] ) {

							// create new destination image
							$dest = $imagine->create( $block_size, $color );

							// crop a part from the tiled image
							$block = $image->copy();
							$block->crop( new Imagine\Image\Point( $x, $y ), $block_size );

							// paste the block into the destination image
							$dest->paste( $block, new Imagine\Image\Point( 0, 0 ) );

							// save the image as a base64 encoded string
							$image_data = base64_encode( $dest->get( "png", array( 'png_compression_level' => 9 ) ) );

							// some variables
							$map_x     = $x + $low_x;
							$map_y     = $y + $low_y;
							$GRD_WIDTH = $banner_data['BLK_WIDTH'] * $banner_data['G_WIDTH'];
							$cb        = ( ( $map_x ) / $banner_data['BLK_WIDTH'] ) + ( ( $map_y / $banner_data['BLK_HEIGHT'] ) * ( $GRD_WIDTH / $banner_data['BLK_WIDTH'] ) );

							// save to db
							$sql = "UPDATE " . MDS_DB_PREFIX . "blocks SET image_data='" . mysqli_real_escape_string( $GLOBALS['connection'], $image_data ) . "' where block_id=" . intval( $cb ) . " AND banner_id=" . intval( $BID ) . ' AND order_id=' . intval($order_id);
							mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
						}
					}
				}

				unlink( $tmp_image_file );
				unset( $tmp_image_file );

				if ( $banner_data['AUTO_APPROVE'] != 'Y' ) { // to be approved by the admin
					disapprove_modified_order( $order_id, $BID );
				}

				if ( $banner_data['AUTO_PUBLISH'] == 'Y' ) {
					process_image( $BID );
					publish_image( $BID );
					process_map( $BID );
				}
			} else {
				// Possible file upload attack!
				echo $label['pixel_upload_failed'];
			}
		}
	}
}
