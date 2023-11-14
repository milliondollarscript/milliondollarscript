<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.5
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

/**
 * Lists packages for advertiser to choose.
 *
 * @param $banner_id
 * @param string $selected
 * @param bool $selection_ability
 *
 * @return void
 */
function display_package_options_table( $banner_id, string $selected = '', bool $selection_ability = false ): void {
	global $wpdb;

	$sql          = "SELECT * FROM " . MDS_DB_PREFIX . "packages WHERE banner_id=%s ORDER BY price";
	$prepared_sql = $wpdb->prepare( $sql, intval( $banner_id ) );
	$results      = $wpdb->get_results( $prepared_sql, ARRAY_A );

	if ( empty( $results ) ) {
		return;
	}
	?>

    <div class='fancy-heading'><?php Language::out( 'Price Options' ); ?></div>
    <p>
		<?php
		if ( $selection_ability ) {
			Language::out( 'Please select your preferred package from the following list:' );
		} else {
			Language::out( 'Here are the available price options for this grid:' );
		}
		?>&nbsp;
    </p>
    <div class="mds-package-options">
        <div class="mds-package-options-heading">
			<?php
			if ( $selection_ability ) {
				?>
                <div class="mds-radio-cell"></div>
				<?php
			}
			?>
            <div><?php Language::out( 'Price' ); ?></div>
            <div><?php Language::out( 'Expires' ); ?></div>
            <div><?php Language::out( 'Max Orders' ); ?></div>
        </div>
		<?php

		$first_sel = false;

		foreach ( $results as $row ) {
			?>
            <div class="mds-package-options-row">
				<?php
				if ( $selected != '' ) {
					if ( $row['package_id'] == $selected ) {
						$sel = " checked ";
					} else {
						$sel = '';
					}
				} else {
					// make sure the first item is selected by default.
					if ( ! $first_sel ) {
						$sel       = 'checked';
						$first_sel = true;
					} else {
						$sel = '';
					}
				}

				if ( $selection_ability ) {
					?>
                    <div class="mds-radio-cell">
                        <input <?php echo $sel; ?> type="radio" id="P<?php echo $row['package_id']; ?>" name="pack" value="<?php echo $row['package_id']; ?>"/>
                    </div>
					<?php
				}
				?>
                <div>
                    <label for="P<?php echo $row['package_id']; ?>"><?php if ( $row['price'] == 0 ) {
							Language::out( 'free' );
						} else {
							echo convert_to_default_currency_formatted( $row['currency'], $row['price'], true );
							$banner_data = load_banner_constants( $banner_id );
							echo " " . Language::get_replace( '/ %NUM_PIXELS% pixels', '%NUM_PIXELS%', $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
						} ?><?php ?></label>
                </div>
                <div>
					<?php

					if ( $row['days_expire'] == '0' ) {
						Language::out( 'Never' );
					} else {
						Language::out_replace( 'Expires in %DAYS_EXPIRE% days.', '%DAYS_EXPIRE%', $row['days_expire'] );
					}

					?>
                </div>
                <div>
					<?php if ( $row['max_orders'] == '0' ) {
						Language::out( 'Unlimited' );
					} else {
						echo $row['max_orders'];
					}
					?>
                </div>
            </div>
			<?php
		}
		?>
    </div>
	<?php
}

/**
 * Returns:
 *
 * $pack['max_orders']
 * $pack['price']
 * $pack['currency']
 * $pack['days_expire']
 *
 * @param $package_id
 *
 * @return array|void
 */
function get_package( $package_id ) {

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "packages where package_id='" . intval( $package_id ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$row = mysqli_fetch_array( $result );

	$pack['max_orders']  = $row['max_orders'];
	$pack['price']       = $row['price'];
	$pack['currency']    = $row['currency'];
	$pack['days_expire'] = $row['days_expire'];

	return $pack;
}

/**
 * Returns true or false if the user can select this package
 * looks at user's previous orders to determine how many times
 * the package was ordered, and compress it with max_orders
 *
 * @param $user_id
 * @param $package_id
 *
 * @return bool|void
 */
function can_user_get_package( $user_id, $package_id ) {

	$sql = "SELECT max_orders, banner_id FROM " . MDS_DB_PREFIX . "packages WHERE package_id='" . intval( $package_id ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$p_row = mysqli_fetch_array( $result );
	if ( $p_row['max_orders'] == 0 ) {
		return true;
	}

	// count the orders the user made for this package
	$sql = "SELECT count(*) AS order_count, banner_id FROM " . MDS_DB_PREFIX . "orders WHERE status <> 'deleted' AND status <> 'new' AND package_id='" . intval( $package_id ) . "' AND user_id='" . intval( $user_id ) . "' GROUP BY user_id, banner_id LIMIT 1";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$u_row = mysqli_fetch_array( $result );

	if ( $u_row['order_count'] < $p_row['max_orders'] ) {

		return true;
	} else {
		return false;
	}
}

/**
 * Checks the grid for packages and returns the result
 *
 * @param int $banner_id
 *
 * @return object|bool|array|null
 */
function banner_get_packages( int $banner_id ): object|bool|array|null {
	global $f2, $wpdb;
	$banner_id = $f2->bid( $banner_id );

	$table_name = MDS_DB_PREFIX . 'packages';
	$sql        = $wpdb->prepare( "SELECT * FROM $table_name WHERE banner_id=%d", $banner_id );
	$result     = $wpdb->get_results( $sql, ARRAY_A );

	if ( count( $result ) > 0 ) {
		return $result;
	}

	return false;
}

function get_default_package( $banner_id ) {
	global $f2, $wpdb;
	$banner_id = $f2->bid( $banner_id );

	$table_name = MDS_DB_PREFIX . "packages";
	$sql        = $wpdb->prepare( "SELECT package_id FROM $table_name WHERE banner_id = %d AND is_default = 'Y'", $banner_id );
	$result     = $wpdb->get_results( $sql );

	return ! empty( $result ) ? $result[0]->package_id : false;
}
