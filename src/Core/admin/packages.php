<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.6
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

use MillionDollarScript\Classes\Currency;

defined( 'ABSPATH' ) or exit;

global $f2;
$BID = $f2->bid();
?>

    <p>
        Packages: Here you can add different price / expiry / max orders combinations to your grids called 'Packages'. Packages added to a grid will overwrite the grid's default price, expiry & max orders settings. After selecting pixels from a grid, the user will choose which package they want. Once the package is selected, the script will calculate the final price for the order.
        <i>Careful: Packages disregard Price Zones, i.e. if a grid has packages, then the Price Zones will be ignored for that grid.</i></p>
    <hr>
<?php
$sql = "Select * from " . MDS_DB_PREFIX . "banners ";
$res = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
?>

    <form name="bidselect" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	    <?php wp_nonce_field( 'mds-admin' ); ?>
        <input type="hidden" name="action" value="mds_admin_form_submission" />
        <input type="hidden" name="mds_dest" value="packages" />

        Select grid: <select name="BID" onchange="this.form.submit()">
            <option></option>
			<?php
			while ( $row = mysqli_fetch_array( $res ) ) {

				if ( ( $row['banner_id'] == $BID ) && ( $BID != 'all' ) ) {
					$sel = 'selected';
				} else {
					$sel = '';
				}
				echo '<option ' . $sel . ' value=' . $row['banner_id'] . '>' . $row['name'] . '</option>';
			}
			?>
        </select>
    </form>
<?php

if ( $BID != '' ) {
	$banner_data = load_banner_constants( $BID );
	?>
    <hr>

    <b>Grid ID:</b> <?php echo $BID; ?><br>
    <b>Grid Name</b>: <?php echo $banner_data['G_NAME']; ?><br>
    <b>Default Price per 100:</b> <?php echo $banner_data['G_PRICE']; ?><br>

    <input type="button" style="background-color:#66FF33" value="New Package..." onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>packages&amp;new=1&amp;BID=<?php echo $BID; ?>'"><br>

    Listing rows that are marked as custom price.<br>

	<?php

	function validate_input() {

		$error = "";
		if ( trim( $_REQUEST['price'] ) == '' ) {
			$error .= "<b>- Price is blank</b><br>";
		} else if ( ! is_numeric( $_REQUEST['price'] ) ) {
			$error .= "<b>- Price must be a number.</b><br>";
		}

		if ( trim( $_REQUEST['description'] ) == '' ) {
			$error .= "<b>- Description is blank</b><br>";
		}

		if ( trim( $_REQUEST['currency'] ) == '' ) {
			$error .= "<b>- Currency is blank</b><br>";
		}

		if ( trim( $_REQUEST['max_orders'] ) == '' ) {
			$error .= "<b>- Max orders is blank</b><br>";
		} else if ( ! is_numeric( $_REQUEST['max_orders'] ) ) {
			$error .= "<b>- Max orders must be a number</b><br>";
		}

		if ( trim( $_REQUEST['days_expire'] ) == '' ) {
			$error .= "<b>- Days to expire is blank</b><br>";
		} else if ( ! is_numeric( $_REQUEST['days_expire'] ) ) {
			$error .= "<b>- Days to expire must be a number.</b><br>";
		}

		return $error;
	}

	if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'delete' ) {

		$sql    = "SELECT * FROM " . MDS_DB_PREFIX . "orders where package_id='" . intval( $_REQUEST['package_id'] ) . "'";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		if ( ( mysqli_num_rows( $result ) > 0 ) && ( $_REQUEST['really'] == '' ) ) {
            ?>
			<span style='color:red'>Cannot delete package: This package is a part of another order</span> (<a href='<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>packages&amp;BID=<?php echo $BID; ?>&amp;package_id=<?php echo $_REQUEST['package_id']; ?>&amp;mds-action=delete&amp;really=yes'>Click here to delete anyway</a>)
            <?php
		} else {

			$sql = "DELETE FROM " . MDS_DB_PREFIX . "packages WHERE package_id='" . intval( $_REQUEST['package_id'] ) . "' ";
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		}
	}

	function set_to_default( $package_id ) {

		global $BID;

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "packages where is_default='Y' and banner_id=" . intval( $BID );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		$row         = mysqli_fetch_array( $result );
		$old_default = $row['package_id'] ?? '';

		$sql = "UPDATE " . MDS_DB_PREFIX . "packages SET is_default='N' WHERE banner_id=" . intval( $BID );

		mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		$sql = "UPDATE " . MDS_DB_PREFIX . "packages SET is_default='Y' WHERE package_id='" . intval( $package_id ) . "' AND banner_id=" . intval( $BID );
		mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );

		if ( $old_default == '' ) {

			// update previous orders which are blank, to the default.
			// in the 1.7.0 database, all orders must have packages

			$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET package_id=" . intval( $package_id ) . " WHERE package_id=0 AND banner_id=" . intval( $BID );
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		}
	}

	if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'default' ) {
		set_to_default( $_REQUEST['package_id'] );
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

			$_REQUEST['block_id_from'] = ( ( $_REQUEST['row_from'] ?? 0 ) - 1 ) * $banner_data['G_WIDTH'];
			$_REQUEST['block_id_to']   = ( ( ( $_REQUEST['row_to'] ?? 0 ) * $banner_data['G_HEIGHT'] ) - 1 );

			$sql = "REPLACE INTO " . MDS_DB_PREFIX . "packages(package_id, banner_id, price, currency, days_expire,  max_orders, description, is_default) VALUES ('" . intval( $_REQUEST['package_id'] ) . "', '" . intval( $BID ) . "', '" . floatval( $_REQUEST['price'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['currency'] ) . "', '" . intval( $_REQUEST['days_expire'] ) . "',  '" . intval( $_REQUEST['max_orders'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['description'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['is_default'] ) . "')";

			//echo $sql;

			mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
			$id = mysqli_insert_id( $GLOBALS['connection'] );

			//print_r ($_REQUEST);

			// if no default package exists, set the last inserted banner to default

			if ( isset( $_REQUEST['new'] ) && $_REQUEST['new'] == '1' && ! get_default_package( $BID ) ) {
				set_to_default( $id );
			}

			$_REQUEST['new']    = '';
			$_REQUEST['mds-action'] = '';
		}
	}

	?>

	<?php

	$result = mysqli_query( $GLOBALS['connection'], "select * FROM " . MDS_DB_PREFIX . "packages  where banner_id=" . intval( $BID ) ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	if ( mysqli_num_rows( $result ) > 0 ) {
		?>

        <table width="800" cellSpacing="1" cellPadding="3" bgColor="#d9d9d9" border="0">
            <tr>
                <td><b><font face="Arial" size="2">Package ID</font></b></td>
                <td><b><font face="Arial" size="2">Description</font></b></td>
                <td><b><font face="Arial" size="2">Days Expire</font></b></td>
                <td><b><font face="Arial" size="2">Price</font></b></td>
                <td><b><font face="Arial" size="2">Currency</font></b></td>
                <td><b><font face="Arial" size="2">Max Orders</font></b></td>
                <td><b><font face="Arial" size="2">Default</font></b></td>
                <td><b><font face="Arial" size="2">Action</font></b></td>
            </tr>
			<?php
			while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
				?>

                <tr bgcolor="#ffffff">

                    <td><font face="Arial" size="2"><?php echo $row['package_id']; ?></font></td>
                    <td><font face="Arial" size="2"><?php echo $row['description']; ?></font></td>
                    <td><font face="Arial" size="2"><?php if ( $row['days_expire'] == 0 ) {
								echo 'unlimited';
							} else {
								echo $row['days_expire'];
							} ?></font></td>
                    <td><font face="Arial" size="2"><?php echo $row['price']; ?></font></td>
                    <td><font face="Arial" size="2"><?php echo $row['currency']; ?></font></td>
                    <td><font face="Arial" size="2"><?php if ( $row['max_orders'] == 0 ) {
								echo 'unlimited';
							} else {
								echo $row['max_orders'];
							} ?></font></td>
                    <td><font face="Arial" size="2"><?php echo $row['is_default']; ?></font></td>

                    <td nowrap><font face="Arial" size="2"><a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>packages&amp;package_id=<?php echo $row['package_id']; ?>&BID=<?php echo $BID; ?>&mds-action=edit">Edit</a> <?php if ( $row['is_default'] != 'Y' ) { ?>| <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>packages&amp;package_id=<?php echo $row['package_id']; ?>&BID=<?php echo $BID; ?>&mds-action=default">Set Default</a><?php } ?> |
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>packages&amp;package_id=<?php echo $row['package_id']; ?>&BID=<?php echo $BID; ?>&mds-action=delete" onclick="return confirmLink(this, 'Delete, are you sure?');">Delete</a></font></td>

                </tr>

				<?php
			}
			?>
        </table>

		<?php
	} else {
		echo "There are no packages for this grid.<br>";
	}

	?>

	<?php

	if ( isset( $_REQUEST['new'] ) && $_REQUEST['new'] == '1' ) {
		echo "<h4>New Package:</h4>";
	}
	if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'edit' ) {
		echo "<h4>Edit Package:</h4>";

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "packages WHERE `package_id`='" . intval( $_REQUEST['package_id'] ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		$row = mysqli_fetch_array( $result );

		if ( ! isset( $error ) || $error == '' ) {
			$_REQUEST['BID']         = $row['banner_id'];
			$_REQUEST['package_id']  = $row['package_id'];
			$_REQUEST['days_expire'] = $row['days_expire'];
			$_REQUEST['price']       = $row['price'];
			$_REQUEST['currency']    = $row['currency'];
			$_REQUEST['description'] = $row['description'];
			$_REQUEST['max_orders']  = $row['max_orders'];
			$_REQUEST['is_default']  = $row['is_default'];
		}
	}

	if ( ( isset( $_REQUEST['new'] ) && $_REQUEST['new'] != '' ) || ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'edit' ) ) {

		?>
        <form action='<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>' method="post">
	        <?php wp_nonce_field( 'mds-admin' ); ?>
            <input type="hidden" name="action" value="mds_admin_form_submission" />
            <input type="hidden" name="mds_dest" value="packages" />

            <input type="hidden" value="<?php echo $row['package_id'] ?? ''; ?>" name="package_id">
            <input type="hidden" value="<?php echo $_REQUEST['new'] ?? ''; ?>" name="new">
            <input type="hidden" value="<?php echo $_REQUEST['mds-action'] ?? ''; ?>" name="mds-action">
            <input type="hidden" value="<?php echo $_REQUEST['is_default'] ?? ''; ?>" name="is_default">
            <input type="hidden" value="<?php echo $BID; ?>" name="BID">
            <table border="0" cellSpacing="1" cellPadding="3" bgColor="#d9d9d9">

                <tr bgcolor="#ffffff">
                    <td><font size="2">Name:</font></td>
                    <td><input size="15" type="text" name="description" value="<?php echo $_REQUEST['description'] ?? ''; ?>">Enter a descriptive name for the package. Eg, "$30 for 100 days."</td>
                </tr>
                <tr bgcolor="#ffffff">
                    <td><font size="2">Price Per Block:</font></td>
                    <td><input size="5" type="text" name="price" value="<?php echo $_REQUEST['price'] ?? ''; ?>">Price per block (<?php echo( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] ); ?> pixels). Enter a decimal</td>
                </tr>
                <tr bgcolor="#ffffff">
                    <td><font size="2">Currency:</font></td>
                    <td><select size="1" name="currency"><?php Currency::currency_option_list( $_REQUEST['currency'] ?? '' ); ?>The price's currency</td>
                </tr>
                <tr bgcolor="#ffffff">
                    <td><font size="2">Days to expire:</font></td>
                    <td><input size="5" type="text" name="days_expire" value="<?php echo $_REQUEST['days_expire'] ?? ''; ?>">How many days? (Enter 0 to use the grid's default)</td>
                </tr>
                <tr bgcolor="#ffffff">
                    <td><font size="2">Maximum orders:</font></td>
                    <td><input size="5" type="text" name="max_orders" value="<?php echo $_REQUEST['max_orders'] ?? ''; ?>">How many times can this pacakge be ordered? (Enter 0 for unlimited)</td>
                </tr>

            </table>
            <input type="submit" name="submit" value="Submit">
        </form>

		<?php
	}
}
