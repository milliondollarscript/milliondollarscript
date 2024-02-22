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

use MillionDollarScript\Classes\Config;
use MillionDollarScript\Classes\Currency;
use MillionDollarScript\Classes\Emails;
use MillionDollarScript\Classes\Functions;
use MillionDollarScript\Classes\Language;
use MillionDollarScript\Classes\Mail;

defined( 'ABSPATH' ) or exit;

echo '<style>
    #bubble {
        position: absolute;
        left: 0;
        top: 0;
        visibility: hidden;
        background-color: #FFFFFF;
        border-color: #33CCFF;
        border-style: solid;
        border-width: 1px;
        padding: 5px;
        margin: 0;
        width: 200px;
        filter: revealtrans();
        font-family: Arial, sans-serif;
        font-size: 11px;
    }

    #content {
        padding: 0;
        margin: 0
    }
       
    .mds-preview-pixels {
        cursor: pointer;
    }
</style>
<div onmouseout="hideBubble()" id="bubble">
<span id="content">
</span>
</div>
';
echo '<script>';
require MDS_CORE_PATH . 'include/mouseover_js.inc.php';
echo '</script>';

global $f2;

if ( isset( $_REQUEST['BID'] ) ) {
	$BID = $f2->bid();
} else {
	$BID = 'all';
}

$bid_sql = " AND banner_id=$BID ";
if ( ( $BID == 'all' ) || empty( $BID ) ) {
	$BID     = '';
	$bid_sql = "  ";
}

// whitelist $_REQUEST['app'] value
$Y_or_N = 'N';
if ( isset( $_REQUEST['app'] ) && $_REQUEST['app'] == 'Y' ) {
	$Y_or_N = 'Y';
}

$offset = isset( $_REQUEST['offset'] ) ? intval( $_REQUEST['offset'] ) : 0;

if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'approve' ) {
	$sql = "UPDATE " . MDS_DB_PREFIX . "blocks set approved='Y', published='N' WHERE order_id=" . intval( $_REQUEST['order_id'] ) . " {$bid_sql}";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$sql = "UPDATE " . MDS_DB_PREFIX . "orders set approved='Y', published='N' WHERE order_id=" . intval( $_REQUEST['order_id'] ) . " {$bid_sql}";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	echo "Order Approved.<br>";
}

if ( isset( $_REQUEST['mass_approve'] ) && $_REQUEST['mass_approve'] != '' ) {
	if ( isset( $_REQUEST['orders'] ) && sizeof( $_REQUEST['orders'] ) > 0 ) {

		foreach ( $_REQUEST['orders'] as $order_id ) {
			$sql = "UPDATE " . MDS_DB_PREFIX . "blocks set approved='Y', published='N' WHERE order_id='" . intval( $order_id ) . "' {$bid_sql}";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
			$sql = "UPDATE " . MDS_DB_PREFIX . "orders set approved='Y', published='N' WHERE order_id='" . intval( $order_id ) . "' {$bid_sql}";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		}

		Language::out( 'Orders Approved' );
		echo "<br/>";
	}
}

if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'disapprove' ) {

	$sql = "UPDATE " . MDS_DB_PREFIX . "blocks set approved='N' WHERE order_id='" . intval( $_REQUEST['order_id'] ) . "' {$bid_sql}";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$sql = "UPDATE " . MDS_DB_PREFIX . "orders set approved='N' WHERE order_id='" . intval( $_REQUEST['order_id'] ) . "' {$bid_sql}";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

	Language::out( 'Order Disapproved' );
	echo "<br/>";
}

if ( isset( $_REQUEST['mass_disapprove'] ) && $_REQUEST['mass_disapprove'] != '' ) {
	if ( isset( $_REQUEST['orders'] ) && sizeof( $_REQUEST['orders'] ) > 0 ) {

		foreach ( $_REQUEST['orders'] as $order_id ) {
			$sql = "UPDATE " . MDS_DB_PREFIX . "blocks set approved='N' WHERE order_id=" . intval( $order_id ) . " {$bid_sql}";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
			$sql = "UPDATE " . MDS_DB_PREFIX . "orders set approved='N' WHERE order_id=" . intval( $order_id ) . " {$bid_sql}";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		}

		Language::out( 'Orders Disapproved' );
		echo "<br/>";
	}
}

if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'deny' ) {
    global $wpdb;

    // TODO: Add denied order status.

	$order_id = intval( $_REQUEST['order_id'] );
	$wpdb->update(
		MDS_DB_PREFIX . "blocks",
		[
			'approved' => 'N',
			'status'   => 'denied',
		],
		[ 'order_id' => $order_id ]
	);
	$wpdb->update(
		MDS_DB_PREFIX . "orders",
		[
			'approved' => 'N',
			'status'   => 'denied'
		],
		[ 'order_id' => $order_id ]
	);

	// Send email
	global $wpdb;
	$sql = $wpdb->prepare(
		"SELECT *, t1.banner_id as BID, t1.user_id as UID, t1.ad_id as AID FROM " . MDS_DB_PREFIX . "orders as t1, " . $wpdb->prefix . "users as t2 where t1.user_id=t2.ID AND order_id=%s",
		$order_id
	);
	$row = $wpdb->get_row( $sql, ARRAY_A );

	$user_info = get_userdata( $row['UID'] );
	wp_update_post( [
		'ID'          => $row['ad_id'],
		'post_status' => 'private',
	] );

	$banner_data = load_banner_constants( $row['banner_id'] );
	$block_count = $row['quantity'] / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

	$price = Currency::convert_to_default_currency_formatted( $row['currency'], $row['price'] );

	$search = [
		'%SITE_NAME%',
		'%FIRST_NAME%',
		'%LAST_NAME%',
		'%USER_LOGIN%',
		'%ORDER_ID%',
		'%PIXEL_COUNT%',
		'%BLOCK_COUNT%',
		'%PIXEL_DAYS%',
		'%PRICE%',
		'%SITE_CONTACT_EMAIL%',
		'%SITE_URL%',
	];

	$replace = [
		get_bloginfo( 'name' ),
		$user_info->first_name,
		$user_info->last_name,
		$user_info->user_login,
		$row['order_id'],
		$row['quantity'],
		$block_count,
		$row['days_expire'],
		$price,
		get_bloginfo( 'admin_email' ),
		get_site_url(),
	];

	$subject = Emails::get_email_replace(
		$search,
		$replace,
		'order-denied-subject'
	);

	$message = Emails::get_email_replace(
		$search,
		$replace,
		'order-denied-content'
	);

	$EMAIL_USER_ORDER_DENIED = Config::get( 'EMAIL_USER_ORDER_DENIED' );
	if ( $EMAIL_USER_ORDER_DENIED == 'YES' ) {
		Mail::send( $user_info->user_email, $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 4 );
	}

	// send a copy to admin
	$EMAIL_ADMIN_ORDER_DENIED = Config::get( 'EMAIL_ADMIN_ORDER_DENIED' );
	if ( $EMAIL_ADMIN_ORDER_DENIED == 'YES' ) {
		Mail::send( get_bloginfo( 'admin_email' ), $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 4 );
	}

	Language::out( 'Order Denied' );
	echo "<br/>";
}

if ( isset( $_REQUEST['do_it_now'] ) && $_REQUEST['do_it_now'] == 'true' ) {

	// process all grids
	$sql = "select * from " . MDS_DB_PREFIX . "banners ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	while ( $row = mysqli_fetch_array( $result ) ) {
		echo process_image( $row['banner_id'] );
		publish_image( $row['banner_id'] );
		process_map( $row['banner_id'] );
	}
	unset( $result, $row );
}

if ( isset( $_REQUEST['save_links'] ) && $_REQUEST['save_links'] != '' ) {
	if ( sizeof( $_REQUEST['urls'] ) > 0 ) {
		$i = 0;

		foreach ( $_REQUEST['urls'] as $url ) {
			$sql = "UPDATE " . MDS_DB_PREFIX . "blocks SET url='" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['new_urls'][ $i ] ) . "', alt_text='" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['new_alts'][ $i ] ) . "' WHERE user_id='" . intval( $_REQUEST['user_id'] ) . "' and url='" . mysqli_real_escape_string( $GLOBALS['connection'], $url ) . "' and banner_id='" . $BID . "'  ";
			//echo $sql."<br>";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
			$i ++;
		}
	}
}

if ( isset( $_POST['mds_dest'] ) ) {
	return;
}

?>

<h3><?php Language::out_replace( 'Remember to process your Grid Image(s) <a href="%PROCESS_PIXELS_URL%">here</a>', '%PROCESS_PIXELS_URL%', esc_url( admin_url( 'admin.php?page=mds-process-pixels' ) ) ); ?></h3>

<form name="bidselect" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission">
    <input type="hidden" name="mds_dest" value="approve-pixels">
    <input type="hidden" name="old_order_id" value="<?php //echo $order_id; ?>">
    <input type="hidden" value="<?php echo $Y_or_N; ?>" name="app">
    <label>
		<?php Language::out( 'Select Grid:' ); ?>
        <select name="BID" onchange="this.form.submit()">
            <option value='all'
				<?php if ( $BID == 'all' ) {
					echo 'selected';
				} ?>><?php Language::out( 'Show All' ); ?>
            </option>
			<?php

			$sql = "Select * from " . MDS_DB_PREFIX . "banners ";
			$res = mysqli_query( $GLOBALS['connection'], $sql );

			while ( $row = mysqli_fetch_array( $res ) ) {

				if ( ( $row['banner_id'] == $BID ) && ( isset( $_REQUEST['BID'] ) && $BID != 'all' ) ) {
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

if ( isset( $_REQUEST['edit_links'] ) && $_REQUEST['edit_links'] != '' ) {

	?>
    <h3><?php Language::out( 'Edit Links:' ); ?></h3>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'mds-admin' ); ?>
        <input type="hidden" name="action" value="mds_admin_form_submission">
        <input type="hidden" name="mds_dest" value="approve-pixels">
        <input type="hidden" name="offset" value="<?php echo $offset; ?>">
        <input type="hidden" name="BID" value="<?php echo $BID; ?>">
        <input type="hidden" name="user_id" value="<?php echo intval( $_REQUEST['user_id'] ); ?>">
        <input type="hidden" value="<?php echo $Y_or_N; ?>" name="app">
        <table>
            <tr>
                <td><b><?php Language::out( 'URL' ); ?></b></td>
                <td><b><?php Language::out( 'Alt Text' ); ?></b></td>
            </tr>

			<?php

			$sql      = "SELECT alt_text, url, count(alt_text) AS COUNT, banner_id FROM " . MDS_DB_PREFIX . "blocks WHERE user_id=" . intval( $_REQUEST['user_id'] ) . "  $bid_sql group by url ";
			$m_result = mysqli_query( $GLOBALS['connection'], $sql );

			$i = 0;
			while ( $m_row = mysqli_fetch_array( $m_result ) ) {
				$i ++;
				if ( $m_row['url'] != '' ) {
					echo "<tr><td>
				<input type='hidden' name='urls[]' value='" . esc_url( $m_row['url'] ) . "'>
				<input type='text' name='new_urls[]' size='40' value=\"" . esc_attr( $m_row['url'] ) . "\"></td>
						<td><input name='new_alts[]' type='text' size='80' value=\"" . esc_attr( $m_row['alt_text'] ) . "\"></td></tr>";
				}
			}

			?>

        </table>
        <input type="submit" value="<?php Language::out( 'Save Changes' ); ?>" name="save_links">

    </form>

	<?php
}

$bid_sql2 = " AND " . MDS_DB_PREFIX . "blocks.banner_id=$BID ";
if ( ( $BID == 'all' ) || ( empty( $BID ) ) ) {
	$BID      = '';
	$bid_sql2 = "";
}

/*$sql = "
SELECT Distinct orders.blocks, orders.order_date, orders.order_id, blocks.approved, blocks.status, blocks.user_id, blocks.banner_id, blocks.ad_id, ads.1, users.FirstName, users.LastName, users.Username, users.Email
    FROM ads, blocks, orders, users
    WHERE orders.approved='" . $Y_or_N . "'
      AND orders.user_id=users.ID
      AND orders.order_id=blocks.order_id
      AND blocks.order_id=ads.order_id
      {$bid_sql2}

    ORDER BY orders.order_date
";*/
// $sql = "
// SELECT " . MDS_DB_PREFIX . "orders.blocks, " . MDS_DB_PREFIX . "orders.user_id, " . MDS_DB_PREFIX . "orders.order_date, " . MDS_DB_PREFIX . "orders.order_id, " . MDS_DB_PREFIX . "blocks.approved, " . MDS_DB_PREFIX . "blocks.status, " . MDS_DB_PREFIX . "blocks.user_id, " . MDS_DB_PREFIX . "blocks.banner_id, " . MDS_DB_PREFIX . "blocks.ad_id, " . MDS_DB_PREFIX . "ads.1, " . MDS_DB_PREFIX . "ads.2
//     FROM " . MDS_DB_PREFIX . "ads, " . MDS_DB_PREFIX . "blocks, " . MDS_DB_PREFIX . "orders
//     WHERE " . MDS_DB_PREFIX . "orders.approved='" . $Y_or_N . "'
//       AND " . MDS_DB_PREFIX . "orders.order_id=" . MDS_DB_PREFIX . "blocks.order_id
//       AND " . MDS_DB_PREFIX . "blocks.order_id=" . MDS_DB_PREFIX . "ads.order_id
//       {$bid_sql2}
//     GROUP BY " . MDS_DB_PREFIX . "orders.order_id, " . MDS_DB_PREFIX . "orders.blocks, " . MDS_DB_PREFIX . "orders.order_date, " . MDS_DB_PREFIX . "blocks.approved, " . MDS_DB_PREFIX . "blocks.status, " . MDS_DB_PREFIX . "blocks.user_id, " . MDS_DB_PREFIX . "blocks.banner_id, " . MDS_DB_PREFIX . "blocks.ad_id, " . MDS_DB_PREFIX . "ads.1, " . MDS_DB_PREFIX . "ads.2
//     ORDER BY " . MDS_DB_PREFIX . "orders.order_date
// ";
$sql = "
SELECT " . MDS_DB_PREFIX . "orders.blocks, " . MDS_DB_PREFIX . "orders.user_id, " . MDS_DB_PREFIX . "orders.order_date, " . MDS_DB_PREFIX . "orders.order_id, " . MDS_DB_PREFIX . "blocks.approved, " . MDS_DB_PREFIX . "blocks.status, " . MDS_DB_PREFIX . "blocks.user_id, " . MDS_DB_PREFIX . "blocks.banner_id, " . MDS_DB_PREFIX . "blocks.ad_id
    FROM " . MDS_DB_PREFIX . "blocks, " . MDS_DB_PREFIX . "orders 
    WHERE " . MDS_DB_PREFIX . "orders.approved='" . $Y_or_N . "' 
      AND " . MDS_DB_PREFIX . "orders.order_id=" . MDS_DB_PREFIX . "blocks.order_id 
      AND " . MDS_DB_PREFIX . "orders.status!='denied' 
      {$bid_sql2}
    GROUP BY " . MDS_DB_PREFIX . "orders.order_id, " . MDS_DB_PREFIX . "orders.blocks, " . MDS_DB_PREFIX . "orders.order_date, " . MDS_DB_PREFIX . "blocks.approved, " . MDS_DB_PREFIX . "blocks.status, " . MDS_DB_PREFIX . "blocks.user_id, " . MDS_DB_PREFIX . "blocks.banner_id, " . MDS_DB_PREFIX . "blocks.ad_id
    ORDER BY " . MDS_DB_PREFIX . "orders.order_date
";

$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
$count = mysqli_num_rows( $result );

$records_per_page = 20;
if ( $count > $records_per_page ) {
	mysqli_data_seek( $result, $offset );
}

$pages    = ceil( $count / $records_per_page );
$cur_page = $offset / $records_per_page;
$cur_page ++;

if ( $count > $records_per_page ) {
	// calculate number of pages & current page

	$additional_params = [ 'app' => $Y_or_N ];
	echo Functions::generate_navigation( $cur_page, $count, $records_per_page, $additional_params, admin_url( 'admin.php?page=mds-approve-pixels' ) );
}

// Determine if auto publish should be checked

// If all grids are selected (default) then not checked
$do_it_now_checked = false;
if ( ( isset( $_REQUEST['do_it_now'] ) && $_REQUEST['do_it_now'] == 'true' ) ) {
	// If do_it_now was just checked then check it
	$do_it_now_checked = true;
} else if ( ! empty( $BID ) ) {
	// If a specific grid is selected check if auto publish is enabled for that grid
	$banner_data = load_banner_constants( $BID );
	if ( $banner_data['AUTO_PUBLISH'] == 'Y' ) {
		$do_it_now_checked = true;
	}
}
?>
<form name="form1" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission">
    <input type="hidden" name="mds_dest" value="approve-pixels">
    <input type="hidden" name="offset" value="<?php echo $offset; ?>">
    <input type="hidden" name="BID" value="<?php echo intval( $BID ); ?>">
    <input type="hidden" name="app" value="<?php echo $Y_or_N; ?>">
    <input type="hidden" name="all_go" value="">
    <table style="background:#ffffff;">
        <tr style="background:#ffffff;">
            <td colspan="12">
                With selected: <input type="submit" value='Approve' style="font-size: 9px; background-color: #33FF66 "
                                      onclick="if (!confirmLink(this, '<?php Language::out( 'Approve for all selected, are you sure?' ); ?>')) return false" name='mass_approve'>
                <input type="submit" value='Disapprove' style="font-size: 9px; background-color: #FF6600"
                       onclick="if (!confirmLink(this, '<?php Language::out( 'Disapprove all selected, are you sure?' ); ?>')) return false" name='mass_disapprove'>
                <label>
                    <input type="checkbox" name="do_it_now" <?php if ( $do_it_now_checked ) {
						echo ' checked ';
					} ?> value="true">
                </label> <?php Language::out( 'Process Grid Images immediately after approval / disapproval' ); ?> <br>
            </td>
        </tr>
        <tr>
        <tr style="background:#eeeeee;">
            <td><label>
                    <input type="checkbox" onClick="checkBoxes('orders');"/>
                </label></td>
            <td><b><?php Language::out( 'Order ID' ); ?></b></td>
            <td><b><?php Language::out( 'Order Date' ); ?></b></td>
            <td><b><?php Language::out( 'Customer Name' ); ?></b></td>
            <td><b><?php Language::out( 'Username & ID' ); ?></b></td>
            <td><b><?php Language::out( 'Email' ); ?></b></td>
            <td><b><?php Language::out( 'X,Y' ); ?></b></td>
            <td><b><?php Language::out( 'Grid' ); ?></b></td>
            <td><b><?php Language::out( 'Image' ); ?></b></td>
            <td><b><?php Language::out( 'Link Text(s) & Link URL(s)' ); ?></b></td>
            <td><b><?php Language::out( 'Action' ); ?></b></td>
        </tr>
		<?php

		// TODO: use form editor field keys

		$i = 0;
		while ( ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) && ( $i < $records_per_page ) ) {

			$banner_data = load_banner_constants( $row['banner_id'] );
			$user_info   = get_userdata( $row['user_id'] );

			$blocks = explode( ',', $row['blocks'] );
			$coords = "";
			foreach ( $blocks as $index => $block ) {
				$pos    = get_block_position( $block, $row['banner_id'] );
				$coords .= ( $pos['x'] / $banner_data['block_width'] ) . ',' . ( $pos['y'] / $banner_data['block_height'] );
				if ( $index !== count( $blocks ) - 1 ) {
					$coords .= ' | ';
				}
			}

			$i ++;
			?>
            <tr onmouseover="window.old_bg=jQuery(this).css('background-color');jQuery(this).css('background-color', '#FBFDDB');" onmouseout="jQuery(this).css('background-color', window.old_bg);">
                <td><label>
                        <input type="checkbox" name="orders[]" value="<?php echo intval( $row['order_id'] ); ?>">
                    </label></td>
                <td><span style="font-family: Arial,serif; "><?php echo intval( $row['order_id'] ); ?></span></td>
                <td><span style="font-family: Arial,serif; "><?php echo esc_html( $row['order_date'] ); ?></span></td>
                <td><span style="font-family: Arial,serif; "><?php echo esc_html( $user_info->first_name . " " . $user_info->last_name ); ?></span></td>
                <td><span style="font-family: Arial,serif; "><?php echo esc_html( $user_info->user_login ); ?> (#<?php echo $user_info->ID; ?>)</span></td>
                <td><span style="font-family: Arial,serif; "><?php echo esc_html( $user_info->user_email ); ?></span></td>
                <td style="max-width:200px;max-height:200px;overflow:auto;display:inline-block;"><span style="font-family: Arial,serif;"><?php echo $coords; ?></span></td>
                <td><span style="font-family: Arial,serif; "><?php
						$sql      = "SELECT name from " . MDS_DB_PREFIX . "banners where banner_id=" . intval( $row['banner_id'] );
						$t_result = mysqli_query( $GLOBALS['connection'], $sql );
						$t_row    = mysqli_fetch_array( $t_result );
						echo $t_row['name']; ?></span></td>
                <td><span style="font-family: Arial,serif; "><img
                                src="<?php echo esc_url( \MillionDollarScript\Classes\Utility::get_page_url( 'get-order-image' ) . '?BID=' . $row['banner_id'] . '&aid=' . $row['ad_id'] ); ?>" alt=""/></span>
                </td>
                <td><span style="font-family: Arial,serif; "><?php
						$text = carbon_get_post_meta( $row['ad_id'], MDS_PREFIX . 'text' );
						$url  = carbon_get_post_meta( $row['ad_id'], MDS_PREFIX . 'url' );
						if ( $text != '' ) {
							echo "<span>" . esc_html( $text ) . " - <a href='" . esc_url( $url ) . "' target='_blank' >" . esc_html( $text ) . "</a></span><br>";
						}
						?>
						<a href='<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . intval( $row['ad_id'] ) ) ); ?>'>[<?php Language::out( 'Edit' ); ?>]</a>
                        <!--						<a class="mds-preview-pixels">[--><?php //Language::out( 'View' ); ?><!--]</a>-->
						</span>
                </td>
                <td><span style="font-family: Arial,serif; "><?php
						if ( $row['approved'] == 'N' ) {
							?>
                            <input type="button" style="background-color: #33FF66" value="Approve"
                                   onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>approve-pixels&mds-action=approve&amp;BID=<?php echo intval( $row['banner_id'] ); ?>&amp;user_id=<?php echo $user_info->ID; ?>&amp;order_id=<?php echo intval( $row['order_id'] ); ?>&amp;offset=<?php echo $offset; ?>&amp;app=<?php echo $Y_or_N; ?>&amp;do_it_now='+document.form1.do_it_now.checked">
 							<?php
						}

						if ( $row['approved'] != 'N' ) {
							?>
                            <input type="button" style="" value="Disapprove"
                                   onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>disapprove-pixels&mds-action=disapprove&amp;BID=<?php echo intval( $row['banner_id'] ); ?>&amp;user_id=<?php echo $user_info->ID; ?>&amp;order_id=<?php echo $row['order_id']; ?>&amp;offset=<?php echo $offset; ?>&amp;app=<?php echo $Y_or_N; ?>&amp;do_it_now='+document.form1.do_it_now.checked">
							<?php
						}

						?>
                           <input type="button" style="background-color: #ff3333;color:#fff;" value="Deny"
                                  onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>approve-pixels&mds-action=deny&amp;BID=<?php echo intval( $row['banner_id'] ); ?>&amp;user_id=<?php echo $user_info->ID; ?>&amp;order_id=<?php echo intval( $row['order_id'] ); ?>&amp;offset=<?php echo $offset; ?>&amp;app=<?php echo $Y_or_N; ?>&amp;do_it_now='+document.form1.do_it_now.checked">
	 </span></td>
            </tr>
			<?php
		}
		?>
    </table>
</form>
<?php
if ( $count > $records_per_page ) {
	// calculate number of pages & current page
	$additional_params = [ 'app' => $Y_or_N ];
	echo Functions::generate_navigation( $cur_page, $count, $records_per_page, $additional_params, admin_url( 'admin.php?page=mds-approve-pixels' ) );
}
?>

