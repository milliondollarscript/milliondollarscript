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

use MillionDollarScript\Classes\Utility;

require_once __DIR__ . "/../include/init.php";
require( 'admin_common.php' );

global $f2;
$BID = $f2->bid();

if ( isset( $_REQUEST['reset_image'] ) && $_REQUEST['reset_image'] != '' ) {
	$default = get_default_image( $_REQUEST['reset_image'] );
	$sql     = "UPDATE " . MDS_DB_PREFIX . "banners SET `" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['reset_image'] ) . "`='" . mysqli_real_escape_string( $GLOBALS['connection'], $default ) . "' WHERE banner_id='" . $BID . "' ";
	mysqli_query( $GLOBALS['connection'], $sql );
}

function display_reset_link( $BID, $image_name ) {
	if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'edit' ) {
		?>
        <a class="inventory-reset-link" title="Reset to default" onclick="if (! confirmLink(this, 'Reset this image to deafult, are you sure?')) return false;" href='inventory.php?action=edit&BID=<?php echo $BID; ?>&reset_image=<?php echo urlencode( $image_name ); ?>'>x</a>
		<?php
	}
}

function is_allowed_grid_file( $image_name ): bool {
	$ALLOWED_EXT = 'png';
	$parts       = explode( '.', $_FILES[ $image_name ]['name'] );
	$ext         = strtolower( array_pop( $parts ) );
	$ext_list    = preg_split( "/[\s,]+/i", ( $ALLOWED_EXT ) );
	if ( ! in_array( $ext, $ext_list ) ) {
		return false;
	} else {
		return true;
	}
}

function validate_input(): string {

	$error = "";

	if ( isset( $_REQUEST['name'] ) && $_REQUEST['name'] == '' ) {
		$error .= "- Grid name not filled in<br>";
	}

	if ( isset( $_REQUEST['grid_width'] ) && $_REQUEST['grid_width'] == '' ) {
		$error .= "- Grid Width not filled in<br>";
	}

	if ( isset( $_REQUEST['grid_height'] ) && $_REQUEST['grid_height'] == '' ) {
		$error .= "- Grid Height not filled in<br>";
	}

	if ( isset( $_REQUEST['days_expire'] ) && $_REQUEST['days_expire'] == '' ) {
		$error .= "- Days Expire not filled in<br>";
	}

	if ( isset( $_REQUEST['max_orders'] ) && $_REQUEST['max_orders'] == '' ) {
		$error .= "- Max orders per customer not filled in<br>";
	}

	if ( isset( $_REQUEST['price_per_block'] ) && $_REQUEST['price_per_block'] == '' ) {
		$error .= "- Price per Block  not filled in<br>";
	}

	if ( isset( $_REQUEST['currency'] ) && $_REQUEST['currency'] == '' ) {
		$error .= "- Currency not filled in<br>";
	}

	if ( isset( $_REQUEST['block_width'] ) && ! is_numeric( $_REQUEST['block_width'] ) ) {
		$error .= "- Block width is not valid<br>";
	}

	if ( isset( $_REQUEST['block_height'] ) && ! is_numeric( $_REQUEST['block_height'] ) ) {
		$error .= "- Block height is not valid<br>";
	}

	if ( isset( $_REQUEST['max_blocks'] ) && ! is_numeric( $_REQUEST['max_blocks'] ) ) {
		$error .= "- Max Blocks is not valid<br>";
	}

	if ( isset( $_REQUEST['min_blocks'] ) && ! is_numeric( $_REQUEST['min_blocks'] ) ) {
		$error .= "- Min Blocks is not valid<br>";
	}

	if ( isset( $_FILES['grid_block'] ) && $_FILES['grid_block']['tmp_name'] != '' ) {
		if ( ! is_allowed_grid_file( 'grid_block' ) ) {
			$error .= "- Grid Block must be a valid PNG file.<br>";
		}
	}

	if ( isset( $_FILES['nfs_block'] ) && $_FILES['nfs_block']['tmp_name'] != '' ) {
		if ( ! is_allowed_grid_file( 'nfs_block' ) ) {
			$error .= "- Not For Sale Block must be a valid PNG file.<br>";
		}
	}

	if ( isset( $_FILES['usr_grid_block'] ) && $_FILES['usr_grid_block']['tmp_name'] != '' ) {
		if ( ! is_allowed_grid_file( 'usr_grid_block' ) ) {
			$error .= "- Not For Sale Block must be a valid PNG file.<br>";
		}
	}

	if ( isset( $_FILES['usr_nfs_block'] ) && $_FILES['usr_nfs_block']['tmp_name'] != '' ) {
		if ( ! is_allowed_grid_file( 'usr_nfs_block' ) ) {
			$error .= "- User's Not For Sale Block must be a valid PNG file.<br>";
		}
	}

	if ( isset( $_FILES['usr_ord_block'] ) && $_FILES['usr_ord_block']['tmp_name'] != '' ) {
		if ( ! is_allowed_grid_file( 'usr_ord_block' ) ) {
			$error .= "- User's Ordered Block must be a valid PNG file.<br>";
		}
	}

	if ( isset( $_FILES['usr_res_block'] ) && $_FILES['usr_res_block']['tmp_name'] != '' ) {
		if ( ! is_allowed_grid_file( 'usr_res_block' ) ) {
			$error .= "- User's Reserved Block must be a valid PNG file.<br>";
		}
	}

	if ( isset( $_FILES['usr_sol_block'] ) && $_FILES['usr_sol_block']['tmp_name'] != '' ) {
		if ( ! is_allowed_grid_file( 'usr_sol_block' ) ) {
			$error .= "- User's Sold Block must be a valid PNG file.<br>";
		}
	}

	return $error;
}

function is_default(): bool {
	if ( isset( $_REQUEST['BID'] ) && $_REQUEST['BID'] == 1 ) {
		return true;
	}

	return false;
}

if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'delete' ) {
	if ( is_default() ) {
		echo "<b>Cannot delete</b> - This is the default grid!<br>";
	} else {

		// check orders..

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders where status <> 'deleted' and banner_id=" . $BID;
		//echo $sql;
		$res = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		if ( mysqli_num_rows( $res ) == 0 ) {

			$sql = "DELETE FROM " . MDS_DB_PREFIX . "blocks WHERE banner_id='" . $BID . "' ";
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );

			$sql = "DELETE FROM " . MDS_DB_PREFIX . "prices WHERE banner_id='" . $BID . "' ";
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );

			$sql = "DELETE FROM " . MDS_DB_PREFIX . "banners WHERE banner_id='" . $BID . "' ";
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );

			// DELETE ADS
			$sql = "select * FROM " . MDS_DB_PREFIX . "ads where banner_id='" . $BID . "' ";
			$res2 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
			while ( $row2 = mysqli_fetch_array( $res2 ) ) {

				delete_ads_files( $row2['ad_id'] );
				$sql = "DELETE from " . MDS_DB_PREFIX . "ads where ad_id='" . intval( $row2['ad_id'] ) . "' ";
				mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
			}

			@unlink( Utility::get_upload_path() . "grids/grid" . $BID . ".jpg" );
			@unlink( Utility::get_upload_path() . "grids/grid" . $BID . ".png" );
			@unlink( Utility::get_upload_path() . "grids/background" . $BID . ".png" );
		} else {
			echo "<b>Cannot delete</b> - this grid contains some orders in the database.<br>";
		}
	}
}

function get_banner_image_data( $b_row, $image_name ): string {
	$uploaddir = Utility::get_upload_path() . "grids/";
	if ( isset( $_FILES ) && isset( $_FILES[ $image_name ] ) && isset( $_FILES[ $image_name ]['tmp_name'] ) && $_FILES[ $image_name ]['tmp_name'] ) {
		// a new image was uploaded
		$uploadfile = $uploaddir . md5( session_id() ) . $image_name . $_FILES[ $image_name ]['name'];
		move_uploaded_file( $_FILES[ $image_name ]['tmp_name'], $uploadfile );
		$fh       = fopen( $uploadfile, 'rb' );
		$contents = fread( $fh, filesize( $uploadfile ) );
		fclose( $fh );
		$contents = addslashes( base64_encode( $contents ) );
		unlink( $uploadfile );
	} else if ( isset( $b_row[ $image_name ] ) && $b_row[ $image_name ] != '' ) {
		// use the old image
		$contents = addslashes( ( $b_row[ $image_name ] ) );
	} else {
		$contents = addslashes( get_default_image( $image_name ) );
	}

	return $contents;
}

function get_banner_image_sql_values( $BID ) {
	$row = "";
	// get banner
	if ( $BID ) {
		$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "banners` WHERE `banner_id`='" . intval( $BID ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$row = mysqli_fetch_array( $result );
	}

	return ", '" . get_banner_image_data( $row, 'grid_block' ) . "' , '" . get_banner_image_data( $row, 'nfs_block' ) . "', '" . get_banner_image_data( $row, 'tile' ) . "', '" . get_banner_image_data( $row, 'usr_grid_block' ) . "', '" . get_banner_image_data( $row, 'usr_nfs_block' ) . "', '" . get_banner_image_data( $row, 'usr_ord_block' ) . "', '" . get_banner_image_data( $row, 'usr_res_block' ) . "', '" . get_banner_image_data( $row, 'usr_sel_block' ) . "', '" . get_banner_image_data( $row, 'usr_sol_block' ) . "'";
}

function validate_block_size( $image_name, $BID ): bool {
	if ( ! $BID ) {
		// new grid...
		return true;
	}

	$block_w = $_REQUEST['block_width'];
	$block_h = $_REQUEST['block_height'];

	$sql    = "SELECT * FROM " . MDS_DB_PREFIX . "banners where banner_id=" . intval( $BID );
	$result = mysqli_query( $GLOBALS['connection'], $sql );
	$b_row  = mysqli_fetch_array( $result );

	if ( $b_row[ $image_name ] == '' ) {
		// no data, assume that the default image will be loaded..
		return true;
	}

	$imagine = new Imagine\Gd\Imagine();

	$img = $imagine->load( base64_decode( $b_row[ $image_name ] ) );

	$temp_file = Utility::get_upload_path() . "temp_block" . md5( session_id() ) . ".png";
	$img->save( $temp_file );
	$size = $img->getSize();

	unlink( $temp_file );

	if ( $size->getWidth() != $block_w ) {
		return false;
	}

	if ( $size->getHeight() != $block_h ) {
		return false;
	}

	return true;
}

if ( isset( $_REQUEST['submit'] ) && $_REQUEST['submit'] != '' ) {

	$error = validate_input();

	if ( $error != '' ) {

		echo "Error: cannot save due to the following errors:<br>";
		echo $error;
	} else {
		$image_sql_fields = ', grid_block, nfs_block, tile, usr_grid_block, usr_nfs_block, usr_ord_block, usr_res_block, usr_sel_block, usr_sol_block ';
		$image_sql_values = get_banner_image_sql_values( $BID );
		$now              = ( gmdate( "Y-m-d H:i:s" ) );

		if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'new' ) {
			$sql = "INSERT INTO `" . MDS_DB_PREFIX . "banners` ( `banner_id` , `grid_width` , `grid_height` , `days_expire` , `price_per_block`, `name`, `currency`, `max_orders`, `block_width`, `block_height`, `max_blocks`, `min_blocks`, `date_updated`, `bgcolor`, `auto_publish`, `auto_approve` $image_sql_fields ) VALUES (NULL, '" . intval( $_REQUEST['grid_width'] ) . "', '" . intval( $_REQUEST['grid_height'] ) . "', '" . intval( $_REQUEST['days_expire'] ) . "', '" . floatval( $_REQUEST['price_per_block'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['name'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['currency'] ) . "', '" . intval( $_REQUEST['max_orders'] ) . "', '" . intval( $_REQUEST['block_width'] ) . "', '" . intval( $_REQUEST['block_height'] ) . "', '" . intval( $_REQUEST['max_blocks'] ) . "', '" . intval( $_REQUEST['min_blocks'] ) . "', '" . $now . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['bgcolor'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['auto_publish'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['auto_approve'] ) . "' $image_sql_values);";
		} else {
			$sql = "REPLACE INTO `" . MDS_DB_PREFIX . "banners` ( `banner_id` , `grid_width` , `grid_height` , `days_expire` , `price_per_block`, `name`, `currency`, `max_orders`, `block_width`, `block_height`, `max_blocks`, `min_blocks`, `date_updated`, `bgcolor`, `auto_publish`, `auto_approve` $image_sql_fields ) VALUES ('" . $BID . "', '" . intval( $_REQUEST['grid_width'] ) . "', '" . intval( $_REQUEST['grid_height'] ) . "', '" . intval( $_REQUEST['days_expire'] ) . "', '" . floatval( $_REQUEST['price_per_block'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['name'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['currency'] ) . "', '" . intval( $_REQUEST['max_orders'] ) . "', '" . intval( $_REQUEST['block_width'] ) . "', '" . intval( $_REQUEST['block_height'] ) . "', '" . intval( $_REQUEST['max_blocks'] ) . "', '" . intval( $_REQUEST['min_blocks'] ) . "', '" . $now . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['bgcolor'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['auto_publish'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['auto_approve'] ) . "' $image_sql_values);";
		}

		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

		$BID = mysqli_insert_id( $GLOBALS['connection'] );

		// TODO: Add individual order expiry dates
		$sql = "UPDATE `" . MDS_DB_PREFIX . "orders` SET days_expire=" . intval( $_REQUEST['days_expire'] ) . " WHERE banner_id=" . $BID;
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

		$_REQUEST['new'] = '';
	}
}

?>
<?php if ( ( isset( $_REQUEST['new'] ) && $_REQUEST['new'] == '' ) && ( ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == '' ) ) ) { ?>
    Here you can manage your grid(s):
    <ul>
        <li>Set the expiry of the pixels</li>
        <li>Set the maximum allowed orders per grid</li>
        <li>Set the default price of the pixels</li>
        <li>Set the grid width</li>
        <li>Create and delete new Grids</li>
    </ul>

<?php } ?>
<input type="button" style="background-color:#66FF33" value="New Grid..." onclick="mds_load_page('inventory.php?action=new&new=1', true)"><br>

<?php

if ( isset( $_REQUEST['new'] ) && $_REQUEST['new'] == '1' ) {
	echo "<h4>New Grid</h4>";
}
if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'edit' ) {
	echo "<h4>Edit Grid</h4>";

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners WHERE `banner_id`='" . $BID . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$row                         = mysqli_fetch_array( $result );
	$_REQUEST['BID']             = $row['banner_id'];
	$_REQUEST['grid_width']      = $row['grid_width'];
	$_REQUEST['grid_height']     = $row['grid_height'];
	$_REQUEST['days_expire']     = $row['days_expire'];
	$_REQUEST['max_orders']      = $row['max_orders'];
	$_REQUEST['price_per_block'] = $row['price_per_block'];
	$_REQUEST['name']            = $row['name'];
	$_REQUEST['currency']        = $row['currency'];
	$_REQUEST['block_width']     = $row['block_width'];
	$_REQUEST['block_height']    = $row['block_height'];
	$_REQUEST['max_blocks']      = $row['max_blocks'];
	$_REQUEST['min_blocks']      = $row['min_blocks'];
	$_REQUEST['bgcolor']         = $row['bgcolor'];
	$_REQUEST['auto_approve']    = $row['auto_approve'];
	$_REQUEST['auto_publish']    = $row['auto_publish'];
}

if ( ( isset( $_REQUEST['new'] ) && $_REQUEST['new'] != '' ) || ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'edit' ) ) {

	function not_valid_grid_option( $option ) {
		return ( ! isset( $_REQUEST[ $option ] ) || ! $_REQUEST[ $option ] ) && ( isset( $_REQUEST[ $option ] ) && $_REQUEST[ $option ] != "0" );
	}

	if ( not_valid_grid_option( 'grid_width' ) ) {
		$_REQUEST['grid_width'] = 100;
	}

	if ( not_valid_grid_option( 'grid_height' ) ) {
		$_REQUEST['grid_height'] = 100;
	}

	if ( not_valid_grid_option( 'price_per_block' ) ) {
		$_REQUEST['price_per_block'] = 1;
	}

	if ( not_valid_grid_option( 'currency' ) ) {
		$_REQUEST['currency'] = get_default_currency();
	}

	if ( not_valid_grid_option( 'days_expire' ) ) {
		$_REQUEST['days_expire'] = '0';
	}

	if ( not_valid_grid_option( 'max_orders' ) ) {
		$_REQUEST['max_orders'] = '1';
	}

	if ( not_valid_grid_option( 'max_blocks' ) ) {
		$_REQUEST['max_blocks'] = '1';
	}

	if ( not_valid_grid_option( 'min_blocks' ) ) {
		$_REQUEST['min_blocks'] = '1';
	}

	if ( not_valid_grid_option( 'auto_approve' ) ) {
		$_REQUEST['auto_approve'] = 'N';
	}

	if ( not_valid_grid_option( 'auto_publish' ) ) {
		$_REQUEST['auto_publish'] = 'Y';
	}

	if ( not_valid_grid_option( 'block_width' ) ) {
		$_REQUEST['block_width'] = 10;
	}

	if ( not_valid_grid_option( 'block_height' ) ) {
		$_REQUEST['block_height'] = 10;
	}
	$size_error_msg = "Error: Invalid size! Must be " . htmlspecialchars( $_REQUEST['block_width'] ) . "x" . htmlspecialchars( $_REQUEST['block_height'] );

	?>
    <form enctype="multipart/form-data" action='inventory.php' method="post">
        <input type="hidden" value="<?php echo( isset( $_REQUEST['new'] ) ? htmlspecialchars( $_REQUEST['new'] ) : "" ); ?>" name="new">
        <input type="hidden" value="<?php echo( isset( $_REQUEST['edit'] ) ? htmlspecialchars( $_REQUEST['edit'] ) : "" ); ?>" name="edit">
        <input type="hidden" value="<?php echo( isset( $_REQUEST['action'] ) ? htmlspecialchars( $_REQUEST['action'] ) : "" ); ?>" name="action">
        <input type="hidden" value="<?php echo( isset( $_REQUEST['BID'] ) ? $BID : "" ); ?>" name="BID">
        <input type="hidden" value="<?php echo( isset( $_REQUEST['edit_anyway'] ) ? htmlspecialchars( $_REQUEST['edit_anyway'] ) : "" ); ?>" name="edit_anyway">

        <input class="inventory-save" type="submit" name="submit" value="Save Grid Settings">

        <div class="inventory-container">
            <div class="inventory-column">
                <div class="inventory-entry">
                    <div class="inventory-title">Grid Name</div>
                    <div class="inventory-content">
                        <label>
                            <input id="inventory-grid-name" autofocus tabindex="0" size="30" type="text" name="name" value="<?php echo( isset( $_REQUEST['name'] ) ? htmlspecialchars( $_REQUEST['name'] ) : "" ); ?>"/>
                            <script>
								jQuery(function () {
									let grid_name = jQuery("#inventory-grid-name");
									if (grid_name.val().length === 0) {
										grid_name.focus();
									}
								});
                            </script>
                        </label> eg. My Million Pixel Grid
                    </div>
                </div>
                <div class="inventory-entry">
					<?php

					$sql   = "SELECT * FROM " . MDS_DB_PREFIX . "blocks where banner_id=" . $BID . " AND status <> 'nfs' limit 1 ";
					$b_res = mysqli_query( $GLOBALS['connection'], $sql );

					if ( isset( $row ) && isset( $row['banner_id'] ) && $row['banner_id'] != '' && mysqli_num_rows( $b_res ) > 0 ) {
						$locked = true;
					} else {
						$locked = false;
					}

					if ( isset( $_REQUEST['edit_anyway'] ) && $_REQUEST['edit_anyway'] != '' ) {
						$locked = false;
					}

					?>
                    <div class="inventory-title">Grid Width</div>
                    <div class="inventory-content">
						<?php
						$disabled = "";
						if ( ! $locked ) {
							?>
                            <label>
                                <input <?php echo $disabled; ?> size="2" type="text" name="grid_width" value="<?php echo htmlspecialchars( $_REQUEST['grid_width'] ); ?>"/>
                            </label> Measured in blocks (default block size is 10x10 pixels)
						<?php } else {
							echo "<b>" . htmlspecialchars( $_REQUEST['grid_width'] );
							echo "<input type='hidden' value='" . ( isset( $row ) ? ( $row['grid_width'] ?? '' ) : '' ) . "' name='grid_width'> Blocks.</b> Note: Cannot change width because the grid is in use by an advertiser. [<a href='inventory.php?action=edit&BID=" . $BID . "&edit_anyway=1'>Edit Anyway</a>]";
						}
						?>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Grid Height</div>
                    <div class="inventory-content">
						<?php

						if ( ! $locked ) {
							?>
                            <label>
                                <input <?php echo $disabled; ?> size="2" type="text" name="grid_height" value="<?php echo htmlspecialchars( $_REQUEST['grid_height'] ); ?>"/>
                            </label> Measured in blocks (default block size is 10x10 pixels)
						<?php } else {
							echo "<b>" . htmlspecialchars( $_REQUEST['grid_height'] );
							echo "<input type='hidden' value='" . ( isset( $row ) ? ( $row['grid_height'] ?? '' ) : '' ) . "' name='grid_height'> Blocks.</b>  Note: Cannot change height because the grid is in use by an advertiser. [<a href='inventory.php?action=edit&BID=" . $BID . "&edit_anyway=1'>Edit Anyway</a>]";
						}
						?>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Price per block</div>
                    <div class="inventory-content">
                        <label>
                            <input size="1" type="text" name="price_per_block" value="<?php echo htmlspecialchars( $_REQUEST['price_per_block'] ); ?>"/>
                        </label>(How much for 1 block of pixels?)
                    </div>
                    <div class="inventory-title">Currency</div>
                    <div class="inventory-content">
                        <label>
                            <select name="currency">
								<?php
								currency_option_list( $_REQUEST['currency'] );

								?>
                            </select>
                        </label>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Days to Expire</div>
                    <div class="inventory-content">
                        <label>
                            <input <?php echo $disabled; ?> size="1" type="text" name="days_expire" value="<?php echo htmlspecialchars( $_REQUEST['days_expire'] ); ?>"/>
                        </label>(How many days until pixels expire? Enter 0 for unlimited.)
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Max orders Per Customer</div>
                    <div class="inventory-content">
                        <label>
                            <input <?php echo $disabled; ?> size="1" type="text" name="max_orders" value="<?php echo htmlspecialchars( $_REQUEST['max_orders'] ); ?>"/>
                        </label>(How many orders per 1 customer? Enter 0 for unlimited.)<br>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Max blocks</div>
                    <div class="inventory-content">
                        <label>
                            <input size="1" type="text" name="max_blocks" value="<?php echo htmlspecialchars( $_REQUEST['max_blocks'] ); ?>"/>
                        </label>(Maximum amount of blocks the customer is allowerd to purchase? Enter 0 for unlimited.)<br>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Min blocks</div>
                    <div class="inventory-content">
                        <label>
                            <input size="1" type="text" name="min_blocks" value="<?php echo htmlspecialchars( $_REQUEST['min_blocks'] ); ?>"/>
                        </label>(Minumum amount of blocks the customer has to purchase per order? Enter 1 or 0 for no limit.)<br>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Approve Automatically?</div>
                    <div class="inventory-content">
                        <label>
                            <input type="radio" name="auto_approve" value="Y" <?php if ( $_REQUEST['auto_approve'] == 'Y' ) {
								echo " checked ";
							} ?> >
                        </label>Yes. Approve all pixels automatically as they are submitted.<br>
                        <label>
                            <input type="radio" name="auto_approve" value="N" <?php if ( $_REQUEST['auto_approve'] == 'N' ) {
								echo " checked ";
							} ?> >
                        </label>No, approve manually from the Admin.<br>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Publish Automatically?</div>
                    <div class="inventory-content">
                        <label>
                            <input type="radio" name="auto_publish" value="Y" <?php if ( $_REQUEST['auto_publish'] == 'Y' ) {
								echo " checked ";
							} ?> >
                        </label>Yes. Process the grid image(s) automatically, every time when the pixels are approved, expired or dis-apprived.<br>
                        <label>
                            <input type="radio" name="auto_publish" value="N" <?php if ( $_REQUEST['auto_publish'] == 'N' ) {
								echo " checked ";
							} ?> >
                        </label>No, Process manually from the admin<br>
                    </div>
                </div>
            </div>
            <div class="inventory-column">
                <div class="inventory-section-title">Block Configuration</div>
                <div class="inventory-entry">
                    <div class="inventory-title">Block Size</div>
                    <div class="inventory-content">
                        <label>
                            <input type="text" name="block_width" size="2" style="font-size: 18pt" value="<?php echo intval( $_REQUEST['block_width'] ); ?>">
                        </label>
                        &nbsp;X&nbsp;
                        <label>
                            <input type="text" name="block_height" size="2" style="font-size: 18pt" value="<?php echo intval( $_REQUEST['block_height'] ); ?>">
                        </label>
                        <br/>(Width X Height, default is 10x10 in pixels)
                    </div>
                </div>
                <div class="inventory-section-title">Block Graphics - Displayed on the public Grid</div>
                <div class="inventory-entry">
                    <div class="inventory-title">Grid Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'grid_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php display_reset_link( $BID, 'grid_block' ); ?>
                        <img src="get_block_image.php?t=<?php echo time(); ?>&BID=<?php echo $BID; ?>&image_name=grid_block" alt=""/>
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="grid_block" size="10"/>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Not For Sale Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'nfs_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php display_reset_link( $BID, 'nfs_block' ); ?>
                        <img src="get_block_image.php?t=<?php echo time(); ?>&BID=<?php echo $BID; ?>&image_name=nfs_block" alt=""/>
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="nfs_block" size="10"/>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Background Tile</div>
                    <div class="inventory-content">
						<?php display_reset_link( $BID, 'tile' ); ?>
						<?php
						$banner_data = load_banner_constants( $BID );
						$bgstyle     = "";
						if ( ! empty( $banner_data['G_BGCOLOR'] ) ) {
							$bgstyle = ' style="background-color:' . $banner_data['G_BGCOLOR'] . ';"';
						}
						?>
                        <img<?php echo $bgstyle; ?> src="get_block_image.php?t=<?php echo time(); ?>&BID=<?php echo $BID; ?>&image_name=tile" alt=""/>
                        <input type="file" name="tile" size="10"/>
                        <br/>(This tile is used the fill the space behind the grid image. The tile will be seen before the grid image is loaded.)
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Background Color</div>
                    <div class="inventory-content">
                        <label>
                            <input type='text' name='bgcolor' size='7' value='<?php echo htmlspecialchars( $_REQUEST['bgcolor'] ); ?>'/>
                        </label> eg. #ffffff
                    </div>
                </div>
                <div class="inventory-section-title">
                    Block Graphics - Displayed on the ordering Grid
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Grid Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'usr_grid_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php display_reset_link( $BID, 'usr_grid_block' ); ?>
                        <img src="get_block_image.php?t=<?php echo time(); ?>&BID=<?php echo $BID; ?>&image_name=usr_grid_block" alt="">
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="usr_grid_block" size="10">
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Not For Sale Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'usr_nfs_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php display_reset_link( $BID, 'usr_nfs_block' ); ?>
                        <img src="get_block_image.php?t=<?php echo time(); ?>&BID=<?php echo $BID; ?>&image_name=usr_nfs_block" alt="">
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="usr_nfs_block" size="10">
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Ordered Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'usr_ord_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php display_reset_link( $BID, 'usr_ord_block' ); ?>
                        <img src="get_block_image.php?t=<?php echo time(); ?>&BID=<?php echo $BID; ?>&image_name=usr_ord_block" alt="">
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="usr_ord_block" size="10">
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Reserved Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'usr_res_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php display_reset_link( $BID, 'usr_res_block' ); ?>
                        <img src="get_block_image.php?t=<?php echo time(); ?>&BID=<?php echo $BID; ?>&image_name=usr_res_block" alt="">
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="usr_res_block" size="10">
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Selected Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'usr_sel_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php display_reset_link( $BID, 'usr_sel_block' ); ?>
                        <img src="get_block_image.php?t=<?php echo time(); ?>&BID=<?php echo $BID; ?>&image_name=usr_sel_block" alt="">
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="usr_sel_block" size="10">
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Sold Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'usr_sol_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php display_reset_link( $BID, 'usr_sol_block' ); ?>
                        <img src="get_block_image.php?t=<?php echo time(); ?>&BID=<?php echo $BID; ?>&image_name=usr_sol_block" alt="">
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="usr_sol_block" size="10">
                    </div>
                </div>
            </div>
        </div>

        <input class="inventory-save" type="submit" name="submit" value="Save Grid Settings">
    </form>
    <hr>
	<?php

	if ( $locked ) {
		?>
        Note: The Grid Width and Grid Height fields are locked because this image has some pixels on order / sold.
		<?php
	}
}

function render_offer( $price, $currency, $max_orders, $days_expire ) {
	?>

	<?php

	?>
    <small>Days:</small> <b><?php if ( $days_expire > 0 ) {
			echo $days_expire;
		} else {
			echo "unlimited";
		} ?></b> <small>Max Ord</small>: <b><?php if ( $max_orders > 0 ) {
			echo $max_orders;
		} else {
			echo "unlimited";
		} ?></b> <small>Price/100</small>: <b><?php echo $price; ?><?php echo $currency; ?></b><br>

	<?php
}

?>
<br/>
Note: A grid with 100 rows and 100 columns is a million pixels. Setting this to a larger value may affect the memory & performance of the script.<br/>
<br/><br/>

<div class="inventory2-container">
    <div class="inventory2-title">
        Action
    </div>
    <div class="inventory2-title">
        Grid ID
    </div>
    <div class="inventory2-title">
        Name
    </div>
    <div class="inventory2-title">
        Grid Width
    </div>
    <div class="inventory2-title">
        Grid Height
    </div>
    <div class="inventory2-title">
        Offer
    </div>
    <div class="inventory2-title">
        Today's Clicks
    </div>
    <div class="inventory2-title">
        Total Clicks
    </div>

	<?php
	$result = mysqli_query( $GLOBALS['connection'], "select * FROM " . MDS_DB_PREFIX . "banners" ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
		?>
        <div class="inventory2-content">
            <a href='inventory.php?action=edit&BID=<?php echo $row['banner_id']; ?>'>Edit</a> <a href="packs.php?BID=<?php echo $row['banner_id']; ?>"> Packages</a>
			<?php
			if ( $row['banner_id'] != '1' ) {
				?>
                <a href='inventory.php?action=delete&BID=<?php echo $row['banner_id']; ?>'>Delete</a>
				<?php
			}
			?>
        </div>
        <div class="inventory2-content">
			<?php echo $row['banner_id']; ?>
        </div>
        <div class="inventory2-content">
			<?php echo $row['name']; ?>
        </div>
        <div class="inventory2-content">
			<?php echo $row['grid_width']; ?> blocks
        </div>
        <div class="inventory2-content">
			<?php echo $row['grid_height']; ?> blocks
        </div>
        <div class="inventory2-content">
			<?php
			$banner_packages = banner_get_packages( $row['banner_id'] );
			if ( ! $banner_packages ) {
				// render the default offer
				render_offer( $row['price_per_block'], $row['currency'], $row['max_orders'], $row['days_expire'] );
			} else {
				while ( $p_row = mysqli_fetch_array( $banner_packages ) ) {
					render_offer( $p_row['price'], $p_row['currency'], $p_row['max_orders'], $p_row['days_expire'] );
				}
			}
			?>
        </div>
        <div class="inventory2-content">
			<?php echo get_clicks_for_today( $row['banner_id'] ); ?>
        </div>
        <div class="inventory2-content">
			<?php echo get_clicks_for_banner( $row['banner_id'] ); ?>
        </div>
		<?php
	}
	?>
</div>
