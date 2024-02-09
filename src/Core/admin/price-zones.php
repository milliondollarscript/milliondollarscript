<?php
/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2024, Ryan Rhode
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

use MillionDollarScript\Classes\Currency;

defined( 'ABSPATH' ) or exit;

global $BID, $f2, $banner_data;
$BID         = $f2->bid();
$banner_data = load_banner_constants( $BID );

function validate_input() {

	global $BID, $banner_data;

	$error = "";
	if ( trim( $_REQUEST['row_from'] ) == '' ) {
		$error .= "<b>- 'Start from Row' code is blank</b><br>";
	}
	if ( trim( $_REQUEST['row_to'] ) == '' ) {
		$error .= "<b>- 'End at Row' is blank</b><br>";
	}

	if ( trim( $_REQUEST['col_from'] ) == '' ) {
		$error .= "<b>- 'Start from Col' code is blank</b><br>";
	}
	if ( trim( $_REQUEST['col_to'] ) == '' ) {
		$error .= "<b>- 'End at Col' is blank</b><br>";
	}

	if ( trim( $_REQUEST['color'] ) == '' ) {
		$error .= "<b>- 'Color' not selected</b><br>";
	}

	if ( $error == '' ) {
		if ( ! is_numeric( $_REQUEST['row_from'] ) ) {
			$error .= "<b>- 'Start from Row' must be a number</b><br>";
		}

		if ( ! is_numeric( $_REQUEST['row_to'] ) ) {
			$error .= "<b>- 'End at Row' must be a number</b><br>";
		}

		if ( $error == '' ) {
			if ( $_REQUEST['row_from'] > $_REQUEST['row_to'] ) {
				$error .= "<b>- 'Start from Row' is larger than 'End at Row'</b><br>";
			} else if ( ( $_REQUEST['row_from'] < 1 ) || ( $_REQUEST['row_to'] > $banner_data['G_HEIGHT'] ) ) {
				$error .= "<b>- The rows specified are out of range! (The current grid has " . $banner_data['G_HEIGHT'] . " rows)</b><br>";
			} else {
				// check database..
				if ( $_REQUEST['submit'] != '' ) {
					$and_price = "";
					if ( $_REQUEST['price_id'] != '' ) {
						$and_price = "and price_id <>" . intval( $_REQUEST['price_id'] );
					}

					$sql = "SELECT * FROM " . MDS_DB_PREFIX . "prices where row_from <= " . intval( $_REQUEST['row_to'] ) . " AND row_to >=" . intval( $_REQUEST['row_from'] ) . " AND col_from <= " . intval( $_REQUEST['col_to'] ) . " AND col_to >=" . intval( $_REQUEST['col_from'] ) . " $and_price AND banner_id=" . intval( $BID );
					$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );

					if ( mysqli_num_rows( $result ) > 0 ) {
						$error .= "<b> - Cannot create: Price zones cannot overlap other price zones!</b><br>";
					}
				}
			}

			if ( $_REQUEST['col_from'] > $_REQUEST['col_to'] ) {
				$error .= "<b>- 'Start from Column' is larger than 'End at Column'</b><br>";
			} else if ( ( $_REQUEST['col_from'] < 1 ) || ( $_REQUEST['col_to'] > $banner_data['G_WIDTH'] ) ) {
				$error .= "<b>- The columns specified are out of range! (The current grid has " . $banner_data['G_WIDTH'] . " columns)</b><br>";
			}
		}
	}

	if ( trim( $_REQUEST['price'] ) == '' ) {
		$error .= "<b>- Price is blank</b><br>";
	}

	if ( trim( $_REQUEST['currency'] ) == '' ) {
		$error .= "<b>- Currency is blank</b><br>";
	}

	return $error;
}

if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'delete' ) {
	$sql = "DELETE FROM " . MDS_DB_PREFIX . "prices WHERE price_id='" . intval( $_REQUEST['price_id'] ) . "' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
}

if ( isset( $_REQUEST['submit'] ) && $_REQUEST['submit'] != '' ) {
	$error = validate_input();

	if ( $error != '' ) {
		echo "<p>";
		echo "<span style='color:red;'>Error: cannot save due to the following errors:</span><br>";
		echo "<span style='color:red;'>$error</span>";
		echo "</p>";
	} else {
		// calculate block id..
		$_REQUEST['block_id_from'] = ( $_REQUEST['row_from'] - 1 ) * $banner_data['G_WIDTH'];
		$_REQUEST['block_id_to']   = ( ( ( $_REQUEST['row_to'] ) * $banner_data['G_WIDTH'] ) - 1 );

		$sql = "REPLACE INTO " . MDS_DB_PREFIX . "prices(price_id, banner_id, row_from, row_to, col_from, col_to, block_id_from, block_id_to, price, currency, color) VALUES ('" . intval( $_REQUEST['price_id'] ) . "', '" . intval( $BID ) . "', '" . intval( $_REQUEST['row_from'] ) . "', '" . intval( $_REQUEST['row_to'] ) . "', '" . intval( $_REQUEST['col_from'] ) . "', '" . intval( $_REQUEST['col_to'] ) . "', '" . intval( $_REQUEST['block_id_from'] ) . "', '" . intval( $_REQUEST['block_id_to'] ) . "', '" . floatval( $_REQUEST['price'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['currency'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['color'] ) . "') ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );

		$_REQUEST['new']        = '';
		$_REQUEST['mds-action'] = '';
	}

	return;
}

// Catch the form submission.
if ( isset( $_REQUEST['action'] ) ) {
	if ( $_REQUEST['action'] == 'mds_admin_form_submission' ) {
		return;
	}
}

// TODO: separate above from below

?>

<p>
    <b>Price Zones:</b> Here you can add different price zones to the grid. This feature allows you to make some regions of the grid more expensive than others.<br/><i>Careful: Packages disregard Price Zones, i.e. if a grid has packages, the Price Zones will be ignored for that grid.</i></p>
<hr>
<?php
$sql = "Select * from " . MDS_DB_PREFIX . "banners ";
$res = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
?>
<form name="bidselect" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission"/>
    <input type="hidden" name="mds_dest" value="price-zones"/>
    <label>
        Select grid:
        <select name="BID" onchange="this.form.submit()">
			<?php
			while ( $row = mysqli_fetch_array( $res ) ) {

				if ( ( $row['banner_id'] == $BID ) && ( $BID != 'all' ) ) {
					$sel = 'selected';
				} else {
					$sel = '';
				}
				echo '
            <option
            ' . $sel . ' value=' . $row['banner_id'] . '>' . $row['name'] . '</option>';
			}
			?>
        </select>
    </label>
</form>
<?php

if ( $BID != '' ) {
	?>
    <hr>
    <b>Grid ID:</b> <?php echo $BID; ?><br>
    <b>Grid Name:</b> <?php echo $banner_data['G_NAME']; ?><br>
    <b>Default Price per block:</b> <?php echo $banner_data['G_PRICE']; ?><br>

    <input type="button" style="background-color:#66FF33" value="New Price Zone..." onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>price-zones&amp;new=1&amp;BID=<?php echo $BID; ?>'"><br>

    Listing rows that are marked as custom price.<br>
	<?php

	$sql = "select * FROM " . MDS_DB_PREFIX . "prices  where banner_id=" . intval( $BID );
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
	if ( mysqli_num_rows( $result ) > 0 ) {
		?>

        <table width="800" cellSpacing="1" cellPadding="3" bgColor="#d9d9d9" border="0">
            <tr>
                <td><b><span style="font-family: Arial, Helvetica, sans-serif;">Grid ID</span></b></td>
                <td><b><span style="font-family: Arial, Helvetica, sans-serif;">Color</span></b></td>
                <td><b><span style="font-family: Arial, Helvetica, sans-serif;">Row<br>- From</span></b></td>
                <td><b><span style="font-family: Arial, Helvetica, sans-serif;">Row<br>- To</span></b></td>
                <td><b><span style="font-family: Arial, Helvetica, sans-serif;">Column<br>- From</span></b></td>
                <td><b><span style="font-family: Arial, Helvetica, sans-serif;">Column<br>- To</span></b></td>
                <td><b><span style="font-family: Arial, Helvetica, sans-serif;">Price<br>per block</span></b></td>
                <td><b><span style="font-family: Arial, Helvetica, sans-serif;">Currency</span></b></td>
                <td><b><span style="font-family: Arial, Helvetica, sans-serif;">Action</span></b></td>
            </tr>

			<?php
			while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
				?>

                <tr bgcolor="#ffffff">
                    <td><span style="font-family: Arial, Helvetica, sans-serif;"><?php echo $row['banner_id']; ?></span></td>
                    <td bgcolor="<?php if ( $row['color'] == 'yellow' ) {
						echo '#FFFF00';
					} else if ( $row['color'] == 'cyan' ) {
						echo '#00FFFF';
					} else if ( $row['color'] == 'magenta' ) {
						echo '#FF00FF';
					} ?>"><span style="font-family: Arial, Helvetica, sans-serif;"><?php
							echo $row['color'];

							?>
                        </span></td>
                    <td><span style="font-family: Arial, Helvetica, sans-serif;"><?php echo $row['row_from']; ?></span></td>
                    <td><span style="font-family: Arial, Helvetica, sans-serif;"><?php echo $row['row_to']; ?></span></td>
                    <td><span style="font-family: Arial, Helvetica, sans-serif;"><?php echo $row['col_from']; ?></span></td>
                    <td><span style="font-family: Arial, Helvetica, sans-serif;"><?php echo $row['col_to']; ?></span></td>
                    <td><span style="font-family: Arial, Helvetica, sans-serif;"><?php echo $row['price']; ?></span></td>
                    <td><span style="font-family: Arial, Helvetica, sans-serif;"><?php echo $row['currency']; ?></span></td>
                    <td nowrap>
                        <span style="font-family: Arial, Helvetica, sans-serif;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>price-zones&amp;price_id=<?php echo $row['price_id']; ?>&amp;BID=<?php echo $BID; ?>&mds-action=edit">Edit</a> | <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>price-zones&amp;price_id=<?php echo $row['price_id']; ?>&BID=<?php echo $BID; ?>&amp;mds-action=delete" onclick="return confirmLink(this, 'Delete, are you sure?');">Delete</a></span>
                    </td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
	} else {
		echo "There are no custom price zones for this grid.<br>";
	}

	if ( isset( $_REQUEST['new'] ) && $_REQUEST['new'] == '1' ) {
		echo "<h4>Add Price Zone:</h4>";
	}

	if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'edit' ) {
		echo "<h4>Edit Price Zone:</h4>";

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "prices WHERE `price_id`='" . intval( $_REQUEST['price_id'] ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		$row = mysqli_fetch_array( $result );

		if ( ! isset( $error ) || $error == '' ) {
			$_REQUEST['color']    = $row['color'];
			$_REQUEST['price_id'] = $row['price_id'];
			$_REQUEST['row_from'] = $row['row_from'];
			$_REQUEST['row_to']   = $row['row_to'];
			$_REQUEST['col_from'] = $row['col_from'];
			$_REQUEST['col_to']   = $row['col_to'];
			$_REQUEST['price']    = $row['price'];
			$_REQUEST['currency'] = $row['currency'];
		}
	}

	if ( ( isset( $_REQUEST['new'] ) && $_REQUEST['new'] != '' ) || ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'edit' ) ) {
		if ( isset( $_REQUEST['col_from'] ) && $_REQUEST['col_from'] == '' ) {
			$_REQUEST['col_from'] = 1;
		}

		if ( isset( $_REQUEST['col_to'] ) && $_REQUEST['col_to'] == '' ) {
			$_REQUEST['col_to'] = $banner_data['G_HEIGHT'];
		}
		?>
        <form action='<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>' method="post">
			<?php wp_nonce_field( 'mds-admin' ); ?>
            <input type="hidden" name="action" value="mds_admin_form_submission"/>
            <input type="hidden" name="mds_dest" value="price-zones"/>

            <input type="hidden" value="<?php echo intval( $row['price_id'] ?? '' ); ?>" name="price_id">
            <input type="hidden" value="<?php echo intval( $_REQUEST['new'] ?? '' ); ?>" name="new">
            <input type="hidden" value="<?php echo $f2->filter( $_REQUEST['mds-action'] ?? '' ); ?>" name="mds-action">
            <input type="hidden" value="<?php echo $BID; ?>" name="BID">
            <table border="0" cellSpacing="1" cellPadding="3" bgColor="#d9d9d9">
                <tr bgcolor="#ffffff">
                    <td><span>Color :</span></td>
                    <td>
                        <select name="color">
                            <option value="">[Select]</option>
                            <option value="yellow" <?php if ( isset( $_REQUEST['color'] ) && $_REQUEST['color'] == 'yellow' ) {
								echo ' selected ';
							} ?> style="background-color: #FFFF00">Yellow
                            </option>
                            <option value="cyan" <?php if ( isset( $_REQUEST['color'] ) && $_REQUEST['color'] == 'cyan' ) {
								echo ' selected ';
							} ?> style="background-color: #00FFFF">Cyan
                            </option>
                            <option value="magenta" <?php if ( isset( $_REQUEST['color'] ) && $_REQUEST['color'] == 'magenta' ) {
								echo ' selected ';
							} ?> style="background-color: #FF00FF">Magenta
                            </option>
                            <option value="white" <?php if ( isset( $_REQUEST['color'] ) && $_REQUEST['color'] == 'white' ) {
								echo ' selected ';
							} ?> style="background-color: #ffffff">White
                            </option>
                        </select>

                    </td>
                </tr>
                <tr bgcolor="#ffffff">
                    <td><span>Start from Row :</span></td>
                    <td><input size="2" type="text" name="row_from" value="<?php echo intval( $_REQUEST['row_from'] ?? 0 ); ?>"> eg. 1</td>
                </tr>
                <tr bgcolor="#ffffff">
                    <td><span>End at Row:</span></td>
                    <td><input size="2" type="text" name="row_to" value="<?php echo intval( $_REQUEST['row_to'] ?? 0 ); ?>"> eg. 25</td>
                </tr>
                <tr bgcolor="#ffffff">
                    <td><span>Start from Column :</span></td>
                    <td><input size="2" type="text" name="col_from" value="<?php echo intval( $_REQUEST['col_from'] ?? 0 ); ?>"> eg. 1</td>
                </tr>
                <tr bgcolor="#ffffff">
                    <td><span>End at Column:</span></td>
                    <td><input size="2" type="text" name="col_to" value="<?php echo intval( $_REQUEST['col_to'] ?? 0 ); ?>"> eg. 25</td>
                </tr>
                <tr bgcolor="#ffffff">
                    <td><span>Price Per Block:</span></td>
                    <td><input size="5" type="text" name="price" value="<?php echo floatval( $_REQUEST['price'] ?? 0 ); ?>">Price per block (<?php echo $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT']; ?> pixels). Enter a decimal</td>
                </tr>
                <tr bgcolor="#ffffff">
                    <td><span>Currency:</span></td>
                    <td><select size="1" name="currency"><?php Currency::currency_option_list( $_REQUEST['currency'] ?? '' ); ?>The price's currency</td>
                </tr>

            </table>
            <input type="submit" name="submit" value="Submit">
        </form>
		<?php
	}

	$mds_admin_ajax_nonce = wp_create_nonce( 'mds_admin_ajax_nonce' );
	?>
    <br/>
    <img src="<?php echo esc_url( admin_url( 'admin-ajax.php?action=mds_admin_ajax&mds_admin_ajax_nonce=' . $mds_admin_ajax_nonce ) ); ?>&amp;mds-ajax=show-price-zone&amp;BID=<?php echo $BID; ?>&amp;time=<?php echo( time() ); ?>" width="<?php echo( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ); ?>" height="<?php echo( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ); ?>" border="0" usemap="#main"/>
	<?php
	show_price_area( $BID );
}
?>
