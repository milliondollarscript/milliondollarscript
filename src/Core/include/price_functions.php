<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.4
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

$price_table = array();

function load_price_zones( $banner_id ) {

	global $price_table;

	if ( ! isset( $price_table ) || ! is_array( $price_table ) ) {
		$price_table = array();
	}

	// check if price table is loaded already
	if ( isset( $price_table[0] ) ) {
		if ( $price_table[0] == 1 ) {
			return;
		}
	}

	$banner_data = load_banner_constants( $banner_id );

	$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "prices` where `banner_id`='" . intval( $banner_id ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$key = 1;
	while ( $row = mysqli_fetch_array( $result ) ) {
		$price_table[ $key ]['row_from'] = $row['row_from'] * $banner_data['BLK_HEIGHT'];
		$price_table[ $key ]['row_to']   = $row['row_to'] * $banner_data['BLK_HEIGHT'];
		$price_table[ $key ]['col_from'] = $row['col_from'] * $banner_data['BLK_WIDTH'];
		$price_table[ $key ]['col_to']   = $row['col_to'] * $banner_data['BLK_WIDTH'];
		$price_table[ $key ]['color']    = $row['color'];
		$price_table[ $key ]['price']    = $row['price'];
		$price_table[ $key ]['currency'] = $row['currency'];
		$key ++;
	}

	// loaded is stored in key 0
	$price_table[0] = 1;
}

function get_zone_color( $banner_id, $row, $col ) {
	global $price_table;

	if ( ! isset( $price_table[0] ) ) {
		load_price_zones( $banner_id );
	}

	$banner_data = load_banner_constants( $banner_id );

	$row += $banner_data['BLK_HEIGHT'];
	$col += $banner_data['BLK_WIDTH'];

	foreach ( $price_table as $key => $val ) {
		// Don't try to process the "loaded" key which is stored at the 0 index of the price table array.
		if ( $key == 0 ) {
			continue;
		}

		if ( ( ( $val['row_from'] <= $row ) && ( $val['row_to'] >= $row ) ) && ( ( $val['col_from'] <= $col ) && ( $val['col_to'] >= $col ) ) ) {
			return $val['color'];
		}
	}

	return "";
}

function get_block_price( $banner_id, $block_id ) {
	// Returns as default currency.

	// get co-ords of the block

	$sql = "SELECT `x`, `y` FROM `" . MDS_DB_PREFIX . "blocks` WHERE `block_id`='" . intval( $block_id ) . "' AND `banner_id`='" . intval( $banner_id ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$block_row = mysqli_fetch_array( $result );

	$row = $block_row['x'];
	$col = $block_row['y'];

	$banner_data = load_banner_constants( $banner_id );

	return get_zone_price( $banner_id, $row, $col );
}

function get_zone_price( $banner_id, $row, $col ) {
	global $price_table;

	$banner_data = load_banner_constants( $banner_id );

	// Adjust for the off by 1
	$row += $banner_data['BLK_HEIGHT'];
	$col += $banner_data['BLK_WIDTH'];

	if ( isset( $price_table[0] ) ) {
		if ( $price_table[0] != 1 ) {
			load_price_zones( $banner_id );
		}
	} else {
		load_price_zones( $banner_id );
	}

	if ( is_array( $price_table ) && count( $price_table ) > 1 ) {
		foreach ( $price_table as $key => $val ) {
			if ( $key == 0 ) {
				continue;
			}
			if ( ( ( $val['row_from'] <= $row ) && ( $val['row_to'] >= $row ) ) && ( ( $val['col_from'] <= $col ) && ( $val['col_to'] >= $col ) ) ) {
				return convert_to_default_currency( $val['currency'], $val['price'] );
			}
		}
	}

	// If not found then get the default price per block for the grid
	$sql = "SELECT `price_per_block` AS `price`, `currency` FROM `" . MDS_DB_PREFIX . "banners` WHERE `banner_id`='" . intval( $banner_id ) . "' ";
	$result2 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$block_row = mysqli_fetch_array( $result2 );

	return convert_to_default_currency( $block_row['currency'], $block_row['price'] );
}

function show_price_area( $banner_id ) {

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "prices where banner_id='" . intval( $banner_id ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	?>

    <map name="prices" id="prices">

		<?php

		while ( $row = mysqli_fetch_array( $result ) ) {
			$row['row_from'] = $row['row_from'] - 1;
			$row['row_to']   = $row['row_to'] - 1;

			$row['col_from'] = $row['col_from'] - 1;
			$row['col_to']   = $row['col_to'] - 1;

			// the x and y coordinates of the upper left and lower right corner
			?>

            <area shape="RECT" coords="<?php echo $row['col_from'] * 10; ?>,<?php echo $row['row_from'] * 10; ?>,<?php echo ( $row['col_to'] * 10 ) + 10; ?>,<?php echo ( $row['row_to'] * 10 ) + 10; ?>" href="" title="<?php echo htmlspecialchars( $row['price'] ); ?>" alt="<?php echo htmlspecialchars( $row['price'] ); ?>" onclick="return false; " target="_blank"/>

			<?php
		}

		?>

    </map>
	<?php
}

function display_price_table( $banner_id ) {

	if ( banner_get_packages( $banner_id ) ) {
		// cannot have custom price zones, this banner has packages.
		return;
	}

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "prices where banner_id='" . intval( $banner_id ) . "' order by row_from";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	if ( mysqli_num_rows( $result ) > 0 ) {
		?>
        <div class='fancy-heading'><?php Language::out( 'Price Table' ); ?></div>
        <p>
			<?php Language::out( 'The following table shows the different price regions for the selected grid.' ); ?>&nbsp;
        </p>
        <table border="0" cellSpacing="1" cellPadding="3" bgColor="#d9d9d9" width="50%">
            <tr>
                <td><b><span style="font-family: Arial,serif; font-size: x-small; "><?php
			                $banner_data = load_banner_constants( $banner_id );
			                Language::out_replace( 'Price / %NUM_PIXELS% pixels', '%NUM_PIXELS%', $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
                            ?></span></b></td>
                <td><b><span style="font-family: Aria,serif; font-size: x-small; "><?php Language::out( 'Color' ); ?></span></b></td>
                <td><b><span style="font-family: Aria,serif; font-size: x-small; "><?php Language::out( 'From row' ); ?></span></b></td>
                <td><b><span style="font-family: Aria,serif; font-size: x-small; "><?php Language::out( 'To row' ); ?></span></b></td>
                <td><b><span style="font-family: Aria,serif; font-size: x-small; "><?php Language::out( 'From column' ); ?></span></b></td>
                <td><b><span style="font-family: Aria,serif; font-size: x-small; "><?php Language::out( 'To column' ); ?></span></b></td>

            </tr>

			<?php
			while ( $row = mysqli_fetch_array( $result ) ) {
				?>
                <tr bgcolor="#ffffff">
                    <td><span style="font-family: Aria,serif; font-size: x-small; "><?php if ( $row['price'] == 0 ) {
								Language::out( 'free' );
							} else {
								echo convert_to_default_currency_formatted( $row['currency'], $row['price'], true );
							} ?></span></td>
                    <td bgcolor="<?php if ( $row['color'] == 'yellow' ) {
						echo '#FFFF00';
					} else if ( $row['color'] == 'cyan' ) {
						echo '#00FFFF';
					} else if ( $row['color'] == 'magenta' ) {
						echo '#FF00FF';
					} ?>"><span style="font-family: Aria,serif; font-size: x-small; "><?php

							echo $row['color'];

							?>

                        </span></td>
                    <td><span style="font-family: Aria,serif; font-size: x-small; "><?php echo $row['row_from']; ?></span></td>
                    <td><span style="font-family: Aria,serif; font-size: x-small; "><?php echo $row['row_to']; ?></span></td>
                    <td><span style="font-family: Aria,serif; font-size: x-small; "><?php echo $row['col_from']; ?></span></td>
                    <td><span style="font-family: Aria,serif; font-size: x-small; "><?php echo $row['col_to']; ?></span></td>

                </tr>
				<?php
			}

			?>

        </table>

		<?php
	}
}

// return's the order's price in default currency
function calculate_price( $banner_id, $blocks_str ) {

	if ( $blocks_str == '' ) {
		return 0;
	}

	$blocks = explode( ",", $blocks_str );
	$price  = 0;
	foreach ( $blocks as $block_id ) {
		$price += get_block_price( $banner_id, $block_id );
	}

	return $price;
}

?>