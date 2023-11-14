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

use MillionDollarScript\Classes\Functions;
use MillionDollarScript\Classes\Language;
use MillionDollarScript\Classes\Utility;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

require_once MDS_CORE_PATH . "html/header.php";

global $f2;

$BID = $f2->bid();

$banner_data  = load_banner_constants( $BID );
$has_packages = banner_get_packages( $BID );

$cannot_get_package = false;

if ( $has_packages && $_REQUEST['pack'] != '' ) {

	\MillionDollarScript\Classes\Functions::verify_nonce( 'mds_packages' );

	// check to make sure this advertiser can order this package
	if ( can_user_get_package( get_current_user_id(), $_REQUEST['pack'] ) ) {

		$sql = "SELECT quantity FROM " . MDS_DB_PREFIX . "orders WHERE order_id='" . intval( $_REQUEST['order_id'] ) . "'";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		$row      = mysqli_fetch_array( $result );
		$quantity = $row['quantity'];

		$block_count = $quantity / ( $banner_data['block_width'] * $banner_data['block_height'] );

		// Now update the order (overwrite the total & days_expire with the package)
		$pack  = get_package( $_REQUEST['pack'] );
		$total = $pack['price'] * $block_count;

		// convert & round off
		$total = convert_to_default_currency( $pack['currency'], $total );

		$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET package_id='" . intval( $_REQUEST['pack'] ) . "', price='" . floatval( $total ) . "',  days_expire='" . intval( $pack['days_expire'] ) . "', currency='" . mysqli_real_escape_string( $GLOBALS['connection'], get_default_currency() ) . "' WHERE order_id='" . intval( get_current_order_id() ) . "'";

		mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
	} else {
		$selected_pack      = $_REQUEST['pack'];
		$_REQUEST['pack']   = '';
		$cannot_get_package = true;
	}

	return;
}

// check to make sure MIN_BLOCKS were selected.
$sql = "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE user_id='" . get_current_user_id() . "' AND status='reserved' AND banner_id='$BID' ";
$res = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
$count             = mysqli_num_rows( $res );
$not_enough_blocks = $count < $banner_data['G_MIN_BLOCKS'];

?>

<?php
Language::out_replace(
	'<p>1. <a href="%ORDER_URL%?BID=%BID%">Select Your pixels</a> -> 2. <b>Image Upload</b> -> 3. Write Your Ad -> 4. Confirm Order -> 5. Payment</p>',
	[
		'%BID%',
		'%ORDER_URL%',
	],
	[
		$BID,
		Utility::get_page_url( 'order' ),
	],
);

$sql = "SELECT * from " . MDS_DB_PREFIX . "orders where order_id='" . intval( get_current_order_id() ) . "' and banner_id='$BID'";

$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
$order_row = mysqli_fetch_array( $result );

function display_edit_order_button( $order_id ) {
	global $BID;
	?>
    <input type='button' value="<?php echo esc_attr( Language::get( 'Edit Order' ) ); ?>" Onclick="window.location='<?php echo esc_url( Utility::get_page_url( 'order' ) ); ?>?&jEditOrder=true&BID=<?php echo $BID; ?>&order_id=<?php echo $order_id; ?>'">
	<?php
}

if ( ( $order_row['order_id'] == '' ) || ( ( $order_row['quantity'] == '0' ) ) ) {
	Language::out_replace(
		'<h3>You have no pixels selected on order! Please <a href="%ORDER_URL%?BID=%BID%">select some pixels here</a></h3>',
		[ '%ORDER_URL%', '%BID%' ],
		[ Utility::get_page_url( 'order' ), $BID ]
	);
} else if ( $not_enough_blocks ) {
	Functions::not_enough_blocks( get_current_order_id(), $banner_data['G_MIN_BLOCKS'] );
} else {

	if ( ( $has_packages ) && ( $_REQUEST['pack'] == '' ) ) {

		$sanitized_input = MillionDollarScript\Classes\Functions::sanitize_array( $_REQUEST['selected_pixels'] );

		// Explode the input string into an array and remove empty elements
		$numbers = array_filter( explode( ',', $sanitized_input ), function ( $value ) {
			return trim( $value ) !== '';
		} );

		// Trim whitespace from each number
		$numbers = array_map( 'trim', $numbers );

		// Check if all elements are numeric
		$selected_pixels = "";
		if ( array_reduce( $numbers, function ( $carry, $item ) {
			return $carry && is_numeric( $item );
		}, true ) ) {
			// Now you can process the array of numbers
			// ...
			$selected_pixels = implode( ',', $numbers );
		} else {
			// The input is not valid; handle the error
			// ...
			//
			error_log( "No pixels selected." );
		}
		?>
        <form method='post' action='<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>'>";
			<?php wp_nonce_field( 'mds_packages' ); ?>
            <input type="hidden" name="action" value="mds_form_submission">
            <input type="hidden" name="mds_dest" value="order">
            <input type="hidden" name="selected_pixels" value="<?php echo esc_attr( $selected_pixels ); ?>">
            <input type="hidden" name="order_id" value="<?php echo absint( $_REQUEST['order_id'] ); ?>">
            <input type="hidden" name="BID" value="<?php echo $BID; ?>">
			<?php display_package_options_table( $BID, $_REQUEST['pack'], true ); ?>
            <input type='button' value='<?php echo esc_attr( Language::get( '<< Previous' ) ); ?>' onclick='window.location="<?php echo Utility::get_page_url( 'order' ); ?>?&jEditOrder=true&BID=<?php echo $BID; ?>&order_id=<?php echo $order_row['order_id']; ?>"'>
            <input type='submit' value='<?php echo esc_attr( Language::get( 'Next >>' ) ); ?>'>
        </form>

		<?php
		if ( $cannot_get_package ) {

			$sql = "SELECT * from " . MDS_DB_PREFIX . "packages where package_id='" . intval( $selected_pack ) . "'";
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
			$row = mysqli_fetch_array( $result );

			Language::out_replace(
				'<p><span style="color:red">Error: Cannot place order. This price option is limited to %MAX_ORDERS% per customer.</span><br/>Please select another option, or check your <a href="%HISTORY_URL%">Order History.</a></p>',
				[ '%MAX_ORDERS%', '%HISTORY_URL%' ],
				[ $row['max_orders'], Utility::get_page_url( 'history' ) ]
			);
		}
	} else {
		display_order( get_current_order_id(), $BID );

		display_edit_order_button( $order_row['order_id'] );

		$privileged = carbon_get_user_meta( get_current_user_id(), 'privileged' );

		if ( ( $order_row['price'] == 0 ) || ( $privileged == '1' ) ) {
			?>
            <input id="mds-complete-button" type='button' value="<?php echo esc_attr( Language::get( 'Complete Order >>' ) ); ?>" data-order-id="<?php echo $order_row['order_id']; ?>" data-grid="<?php echo $BID; ?>">
			<?php
		} else {

			?>
            <input id="mds-confirm-button" type='button' value="<?php echo esc_attr( Language::get( 'Confirm & Pay >>' ) ); ?>" data-order-id="<?php echo $order_row['order_id']; ?>" data-grid="<?php echo $BID; ?>">
            <hr>
			<?php
		}
	}
}

require_once MDS_CORE_PATH . "html/footer.php";
