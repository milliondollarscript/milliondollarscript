<?php
/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2025, Ryan Rhode
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

namespace MillionDollarScript\Core\admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\Language\Language;

// Constants
// Max orders to display per page
define( 'MAX', 30 );

// Add CSS Styles
?>
<style>
    /* Style for block coordinates cell */
    .column-block_coords {
        box-sizing: border-box; /* Include padding in width */
        padding-right: 15px; /* Add spacing */
        word-wrap: break-word; /* Legacy */
    }

    /* Style for the content wrapper inside the block coordinates cell */
    .block-coords-wrapper {
        max-width: 150px;
        overflow-x: auto;
        white-space: nowrap;
        word-wrap: break-word; /* Fallback */
    }

    /* Style for URL and Title columns */
    .column-url input[type="text"],
    .column-title input[type="text"] {
         width: 95%; /* Make inputs take most of cell width */
         box-sizing: border-box; /* Include padding/border in width */
    }
    .column-url, .column-title {
        min-width: 150px; /* Give columns minimum width */
    }

    /* Add some margin to action buttons */
    .column-actions form {
        margin: 0 5px 5px 0; /* Adjust margins slightly */
        display: inline-block; /* Keep buttons side-by-side */
    }
    td.column-image img {
        max-width: 50px;
        max-height: 50px;
        width: auto;
        height: auto;
        display: block; /* Prevents extra space below image */
    }

    /* Make table full width and handle layout */
    table.wp-list-table {
        width: 100%;
        table-layout: fixed;
    }

    /* Allow long text to wrap in specific columns */
    table.wp-list-table .column-url,
    table.wp-list-table .column-title,
    table.wp-list-table .column-block_coords td /* Target the cell directly for block coords */
    {
        word-wrap: break-word;
        overflow-wrap: break-word;
        /* Consider adding max-width if needed */
    }

    /* Override external max-width for the main content wrapper on this page */
    .admin-content {
        max-width: none !important; /* Use !important to ensure override */
    }
</style>
<?php

global $f2, $wpdb;

// Get grid ID from POST or GET, default to 1
$BID = $_POST['BID'] ?? $_GET['BID'] ?? 1;
if ( ! is_numeric( $BID ) ) {
	// Default to grid 1 if BID is invalid
	$BID = 1;
}

$bid_sql = ( $BID > 0 ) ? " AND T1.banner_id = " . intval( $BID ) : '';
$bid_update_sql = ( $BID > 0 ) ? " AND banner_id = " . intval( $BID ) : '';
// Handle actions only if an order_id is provided
if ( isset( $_POST['order_id'] ) ) {
	$order_id = intval( $_POST['order_id'] );
} else {
	// Ensure order_id is initialized
	$order_id = 0;
}


// Get the current user's info
$user_info = wp_get_current_user();

// Display dynamic title based on the action
// Default to 'N' if not set
$Y_or_N = sanitize_text_field( $_GET['app'] ?? 'N' );
if ( $Y_or_N !== 'Y' ) {
	$Y_or_N = 'N';
}

// Set title based on filter
$title = ( $Y_or_N == 'N' ) ? Language::get( 'Pixels Awaiting Approval' ) : Language::get( 'Approved Pixels' );

// Process 'approve' action
if ( isset( $_GET['mds-action'] ) && $_GET['mds-action'] == 'approve' ) {
	$order_id = intval( $_GET['order_id'] );
	if ( ! $order_id ) {
		die( 'Invalid order_id' );
	}
	check_admin_referer( 'mds-approve-order_' . $order_id );

	$wpdb->query( $wpdb->prepare(
		"UPDATE " . MDS_DB_PREFIX . "blocks
		SET approved = 'Y'
		WHERE order_id = %d {$bid_update_sql}",
		$order_id
	) );
	$wpdb->query( $wpdb->prepare(
		"UPDATE " . MDS_DB_PREFIX . "orders
		SET approved = 'Y'
		WHERE order_id = %d {$bid_update_sql}",
		$order_id
	) );

	// Optional: Process grid images immediately if checked
	if ( isset( $_GET['do_it_now'] ) && $_GET['do_it_now'] === 'true' ) {
		Utility::process_grid_image( $BID );
	}

	Utility::redirect_to_previous_approve_page();
}

// Process 'mass_approve' action
// Check if bulk action was set to 'approve' (POST) AND the corresponding hidden input is set
if ( ( ( isset( $_POST['action'] ) && $_POST['action'] == 'approve' ) || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'approve' ) ) && isset( $_POST['mass_approve'] ) && $_POST['mass_approve'] != '' ) {
	check_admin_referer( 'mds-admin' );
	if ( isset( $_POST['orders'] ) && is_array($_POST['orders']) && count( $_POST['orders'] ) > 0 ) {

		foreach ( $_POST['orders'] as $order_id ) {
			$order_id = intval( $order_id );
			$wpdb->query( $wpdb->prepare(
				"UPDATE " . MDS_DB_PREFIX . "blocks
				SET approved = 'Y'
				WHERE order_id = %d {$bid_update_sql}",
				$order_id
			) );
			$wpdb->query( $wpdb->prepare(
				"UPDATE " . MDS_DB_PREFIX . "orders
				SET approved = 'Y'
				WHERE order_id = %d {$bid_update_sql}",
				$order_id
			) );
		}
		// Optional: Process grid images immediately if checked
		if ( isset( $_POST['do_it_now'] ) && $_POST['do_it_now'] === 'true' ) {
			Utility::process_grid_image( $BID );
		}
	}
	Utility::redirect_to_previous_approve_page();
}

// Process 'disapprove' action
if ( isset( $_GET['mds-action'] ) && $_GET['mds-action'] == 'disapprove' ) {
	$order_id = intval( $_GET['order_id'] );
	if ( ! $order_id ) {
		die( 'Invalid order_id' );
	}
	check_admin_referer( 'mds-disapprove-order_' . $order_id );

	$wpdb->query( $wpdb->prepare(
		"UPDATE " . MDS_DB_PREFIX . "blocks
		SET approved = 'N'
		WHERE order_id = %d {$bid_update_sql}",
		$order_id
	) );
	$wpdb->query( $wpdb->prepare(
		"UPDATE " . MDS_DB_PREFIX . "orders
		SET approved = 'N'
		WHERE order_id = %d {$bid_update_sql}",
		$order_id
	) );

	// Optional: Process grid images immediately if checked
	if ( isset( $_GET['do_it_now'] ) && $_GET['do_it_now'] === 'true' ) {
		Utility::process_grid_image( $BID );
	}

	Utility::redirect_to_previous_approve_page();
}

// Process 'mass_disapprove' action
// Check if bulk action was set to 'disapprove' (POST) AND the corresponding hidden input is set
if ( ( ( isset( $_POST['action'] ) && $_POST['action'] == 'disapprove' ) || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'disapprove' ) ) && isset( $_POST['mass_disapprove'] ) && $_POST['mass_disapprove'] != '' ) {
	check_admin_referer( 'mds-admin' );
	if ( isset( $_POST['orders'] ) && is_array($_POST['orders']) && count( $_POST['orders'] ) > 0 ) {

		foreach ( $_POST['orders'] as $order_id ) {
			$order_id = intval( $order_id );
			$wpdb->query( $wpdb->prepare(
				"UPDATE " . MDS_DB_PREFIX . "blocks
				SET approved = 'N'
				WHERE order_id = %d {$bid_update_sql}",
				$order_id
			) );
			$wpdb->query( $wpdb->prepare(
				"UPDATE " . MDS_DB_PREFIX . "orders
				SET approved = 'N'
				WHERE order_id = %d {$bid_update_sql}",
				$order_id
			) );
		}
		// Optional: Process grid images immediately if checked
		if ( isset( $_POST['do_it_now'] ) && $_POST['do_it_now'] === 'true' ) {
			Utility::process_grid_image( $BID );
		}
	}
	Utility::redirect_to_previous_approve_page();
}


// Process 'deny' action (set order status to denied)
if ( isset( $_GET['mds-action'] ) && $_GET['mds-action'] == 'deny' ) {
	$order_id = intval( $_GET['order_id'] );
	if ( ! $order_id ) {
		die( 'Invalid order_id' );
	}
	check_admin_referer( 'mds-deny-order_' . $order_id );

	// Set blocks to N
	$wpdb->update(
		MDS_DB_PREFIX . "blocks",
		[ 'approved' => 'N' ],
		[ 'order_id' => $order_id ],
		[ '%s' ], // approved format
		[ '%d' ]  // order_id format
	);
	// Set order status to 'denied'
	$wpdb->update(
		MDS_DB_PREFIX . "orders",
		[ 'status' => 'denied', 'approved' => 'N' ],
		[ 'order_id' => $order_id ],
		[ '%s', '%s' ], // status, approved format
		[ '%d' ]   // order_id format
	);

	// Optional: Process grid images immediately if checked
	if ( isset( $_GET['do_it_now'] ) && $_GET['do_it_now'] === 'true' ) {
		Utility::process_grid_image( $BID );
	}

	Utility::redirect_to_previous_approve_page();
}

// Pagination Setup
$offset = $_GET['offset'] ?? 0;
if ( ! is_numeric( $offset ) ) {
	$offset = 0;
}

// Build WHERE clause for approval status
$where_approved = ( $Y_or_N == 'Y' ) ? 'Y' : 'N';
$sql_where      = $wpdb->prepare( "WHERE T1.approved = %s AND T1.status = 'completed' AND T1.status != 'denied' AND T1.banner_id = %d", $where_approved, $BID );

// Count total orders for pagination, ensuring it's an integer
$total_orders = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT T1.order_id) FROM " . MDS_DB_PREFIX . "orders T1 {$sql_where}" );

// Fetch orders for the current page
$sql = $wpdb->prepare(
	"SELECT T1.*, T2.block_count,
			U.user_login,
			T1.user_id, -- Ensure user_id is explicitly selected if needed later
			T1.ad_id,
			FB.url,
			FB.alt_text AS title,
			TR.type AS pstatus,
			BC.block_coords,
			T1.ad_id
	 FROM " . MDS_DB_PREFIX . "orders T1
	 LEFT JOIN (SELECT order_id, COUNT(*) as block_count FROM " . MDS_DB_PREFIX . "blocks GROUP BY order_id) T2
	 ON T1.order_id = T2.order_id
	 JOIN {$wpdb->users} U ON T1.user_id = U.ID
	 LEFT JOIN (
		SELECT order_id, url, alt_text
		FROM (
			SELECT order_id, url, alt_text, ROW_NUMBER() OVER (PARTITION BY order_id ORDER BY block_id ASC) as rn
			FROM " . MDS_DB_PREFIX . "blocks
		) AS RankedBlocks
		WHERE rn = 1
	 ) FB ON T1.order_id = FB.order_id
	 LEFT JOIN " . MDS_DB_PREFIX . "transactions TR ON T1.order_id = TR.order_id
	 LEFT JOIN (SELECT order_id, GROUP_CONCAT(CONCAT(x, ',', y) SEPARATOR ' | ') AS block_coords FROM " . MDS_DB_PREFIX . "blocks GROUP BY order_id) BC ON T1.order_id = BC.order_id
	 {$sql_where}
	 GROUP BY T1.order_id
	 ORDER BY T1.order_date DESC
	 LIMIT %d, %d",
	$offset,
	MAX
);

$results = $wpdb->get_results( $sql, ARRAY_A );

?>
<style type="text/css">
    a.black:link {
        font-size: 10pt;
        color: black;
    }

    a.black:visited {
        font-size: 10pt;
        color: black;
    }

    a.black:hover {
        font-size: 10pt;
        color: gray;
    }

    a.black:active {
        font-size: 10pt;
        color: black;
    }
</style>

<div class="wrap">
    <h1><?php echo esc_html( $title ); ?></h1>

    <?php
    // Ensure the form action preserves the current filter state (Approved/Disapproved)
    $form_action_url = add_query_arg( 'app', $Y_or_N, admin_url( 'admin.php?page=mds-approve-pixels' ) );
    ?>
    <form name="form1" method="post" action="<?php echo esc_url( $form_action_url ); ?>">
        <?php // Nonce for mass actions ?>
        <?php wp_nonce_field( 'mds-admin' ); ?>
        <input type="hidden" name="page" value="mds-approve-pixels"/>
        <input type="hidden" name="offset" value="<?php echo $offset; ?>"/>
        <input type="hidden" name="app" value="<?php echo $Y_or_N; ?>"/>
        <input type="hidden" name="BID" value="<?php echo $BID; ?>"/>

        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-approve-pixels&app=N&BID=' . $BID ) ); ?>"
               class="black"><?php echo Language::get( 'Pixels Awaiting Approval' ); ?></a>
            |
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-approve-pixels&app=Y&BID=' . $BID ) ); ?>"
               class="black"><?php echo Language::get( 'Approved Pixels' ); ?></a>
        </p>
        <p><?php echo Language::get( 'Grid' ); ?>: <?php Utility::grid_dropdown( $BID ); ?>
            <input type="submit" class="button" value="<?php echo Language::get( 'Change Grid' ); ?>"/>
        </p>

        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text"><?php echo Language::get( 'Select bulk action' ); ?></label>
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1"><?php echo Language::get( 'Bulk Actions' ); ?></option>
                    <option value="approve"><?php echo Language::get( 'Approve' ); ?></option>
                    <option value="disapprove"><?php echo Language::get( 'Disapprove' ); ?></option>
                </select>
                <input type="submit" id="doaction" class="button action" value="<?php echo Language::get( 'Apply' ); ?>">
                <!-- Will be set by JS if approve selected -->
				<input type="hidden" name="mass_approve" value="">
				<!-- Will be set by JS if disapprove selected -->
				<input type="hidden" name="mass_disapprove" value="">
            </div>
            <div class="tablenav-pages">
                <span class="displaying-num"><?php printf( Language::get( '%s items' ), $total_orders ); ?></span>
				<?php Utility::display_pagination( $total_orders, MAX, $offset, "admin.php?page=mds-approve-pixels&app=$Y_or_N&BID=$BID" ); ?>
            </div>
            <br class="clear"/>
        </div>

        <table class="wp-list-table widefat fixed striped table-view-list posts">
            <thead>
            <tr>
                <th scope="col" id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-1"><?php Language::out( 'Select All' ); ?></label><input id="cb-select-all-1" type="checkbox"></th>
                <th scope="col" id="actions" class="manage-column column-actions"><?php echo Language::get( 'Actions' ); ?></th>
                <th scope="col" id="order_id" class="manage-column column-order_id"><?php echo Language::get( 'Order ID' ); ?></th>
                <th scope="col" id="ad_id" class="manage-column column-ad_id"><?php echo Language::get( 'Ad ID' ); ?></th>
                <th scope="col" id="order_date" class="manage-column column-order_date"><?php echo Language::get( 'Order Date' ); ?></th>
                <th scope="col" id="username" class="manage-column column-username"><?php echo Language::get( 'Username' ); ?></th>
                <th scope="col" id="block_count" class="manage-column column-block_count"><?php echo Language::get( 'Block Count' ); ?></th>
                <th scope="col" id="url" class="manage-column column-url"><?php echo Language::get( 'URL' ); ?></th>
                <th scope="col" id="title" class="manage-column column-title"><?php echo Language::get( 'Title' ); ?></th>
                <th scope="col" id="block_coords" class="manage-column"><?php echo Language::get( 'Block Coordinates' ); ?></th>
                <th scope="col" id="image" class="manage-column column-image"><?php echo Language::get( 'Image' ); ?></th>
                <th scope="col" id="status" class="manage-column column-status"><?php echo Language::get( 'Payment Status' ); ?></th>
            </tr>
            </thead>

            <tfoot>
             <tr>
                <th scope="col" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-2"><?php Language::out( 'Select All' ); ?></label><input id="cb-select-all-2" type="checkbox"></th>
                <th scope="col" class="manage-column column-actions"><?php echo Language::get( 'Actions' ); ?></th>
                <th scope="col" class="manage-column column-order_id"><?php echo Language::get( 'Order ID' ); ?></th>
                <th scope="col" class="manage-column column-ad_id"><?php echo Language::get( 'Ad ID' ); ?></th>
                <th scope="col" class="manage-column column-order_date"><?php echo Language::get( 'Order Date' ); ?></th>
                <th scope="col" class="manage-column column-username"><?php echo Language::get( 'Username' ); ?></th>
                <th scope="col" class="manage-column column-block_count"><?php echo Language::get( 'Block Count' ); ?></th>
                <th scope="col" class="manage-column column-url"><?php echo Language::get( 'URL' ); ?></th>
                <th scope="col" class="manage-column column-title"><?php echo Language::get( 'Title' ); ?></th>
                <th scope="col" class="manage-column"><?php echo Language::get( 'Block Coordinates' ); ?></th>
                <th scope="col" class="manage-column column-image"><?php echo Language::get( 'Image' ); ?></th>
                <th scope="col" class="manage-column column-status"><?php echo Language::get( 'Payment Status' ); ?></th>
            </tr>
            </tfoot>

            <tbody id="the-list">
			<?php
			if ( $results ) {
				foreach ( $results as $row ) {
					// Ensure integer
					$order_id = intval( $row['order_id'] );
					?>
                    <tr id="post-<?php echo $order_id; ?>" class="iedit author-self level-0 post-<?php echo $order_id; ?> type-post status-publish format-standard hentry category-uncategorized">
                        <th scope="row" class="check-column">
                            <label class="screen-reader-text" for="cb-select-<?php echo $order_id; ?>">
								<?php printf( Language::get( 'Select %s' ), $row['title'] ?? '' ); ?>
                            </label>
                            <input id="cb-select-<?php echo $order_id; ?>" type="checkbox" name="orders[]" value="<?php echo $order_id; ?>">
                        </th>
                        <td class="column-actions">
                            <?php if ( $row['approved'] == 'N' ) { ?>
                                <?php
								// Construct Approve link URL
								$approve_nonce = wp_create_nonce( 'mds-approve-order_' . $order_id );
								$approve_url = add_query_arg( [
									'page'       => 'mds-approve-pixels',
									'mds-action' => 'approve',
									'order_id'   => $order_id,
									'BID'        => $row['banner_id'], // Pass BID if needed
									'app'        => $Y_or_N, // Keep current filter
									'offset'     => $offset, // Keep current pagination
									'_wpnonce'   => $approve_nonce
								], admin_url( 'admin.php' ) );
								?>
								<a href="<?php echo esc_url( $approve_url ); ?>" class="button button-small" style="background-color: #388E3C; color: #fff;">Approve</a>
                             <?php } ?>
                             <?php if ( $row['approved'] == 'Y' ) { ?>
                                <?php
								// Construct Disapprove link URL
								$disapprove_nonce = wp_create_nonce( 'mds-disapprove-order_' . $order_id );
								$disapprove_url = add_query_arg( [
									'page'       => 'mds-approve-pixels',
									'mds-action' => 'disapprove',
									'order_id'   => $order_id,
									'BID'        => $row['banner_id'],
									'app'        => $Y_or_N,
									'offset'     => $offset,
									'_wpnonce'   => $disapprove_nonce
								], admin_url( 'admin.php' ) );
								?>
								<a href="<?php echo esc_url( $disapprove_url ); ?>" class="button button-small" style="background-color: #E65100; color: #fff;">Disapprove</a>
                             <?php } ?>
                            <?php
							// Construct Deny link URL
							$deny_nonce = wp_create_nonce( 'mds-deny-order_' . $order_id );
							$deny_url = add_query_arg( [
								'page'       => 'mds-approve-pixels',
								'mds-action' => 'deny',
								'order_id'   => $order_id,
								'BID'        => $row['banner_id'],
								'app'        => $Y_or_N,
								'offset'     => $offset,
								'_wpnonce'   => $deny_nonce
							], admin_url( 'admin.php' ) );
							?>
							<a href="<?php echo esc_url( $deny_url ); ?>" class="button button-small" style="background-color: #D32F2F; color:#fff;">Deny</a>
                        </td>
                        <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-orders&order_id=' . $order_id ) ); ?>"><?php echo $order_id; ?></a></td>
                        <td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . intval($row['ad_id']) . '&action=edit' ) ); ?>"><?php echo esc_html( $row['ad_id'] ?? '' ); ?></a></td>
                        <td><?php echo esc_html( $row['order_date'] ); ?></td>
                        <td><a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $row['user_id'] ) ); ?>"><?php echo esc_html( $row['user_login'] ); ?></a></td>
                        <td><?php echo intval( $row['block_count'] ); ?></td>
                        <td class="column-url">
							<?php 
							if ( ! empty( $row['url'] ) ) {
								$url = esc_url( $row['url'] );
								echo '<a href="' . $url . '" target="_blank">' . esc_html( $row['url'] ) . '</a>'; // Display URL as link
							}
							// If URL is empty, output nothing
							?>
                        </td>
                        <td class="column-title"><?php echo esc_html( $row['title'] ?? '' ); ?></td>
                        <td class="column-block_coords"><div class="block-coords-wrapper"><?php echo esc_html( $row['block_coords'] ?? '' ); ?></div></td>
                        <td class="column-image">
                            <?php
                            // Use the old method to get the image URL via ad_id
                            if ( ! empty( $row['ad_id'] ) && ! empty( $row['banner_id'] ) ) {
                                $image_url = esc_url( Utility::get_page_url( 'get-order-image' ) . '?BID=' . $row['banner_id'] . '&aid=' . $row['ad_id'] );
                                ?>
                                <img src="<?php echo $image_url; ?>" width="50" height="50" alt="<?php echo esc_attr( $row['title'] ?? '' ); ?>"/>
                                <?php
                            } else {
                                // Display 'No Image' if ad_id or banner_id is missing
                                echo Language::get( 'No Image' );
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html( $row['pstatus'] ); ?></td>
                    </tr>
					<?php
				}
			} else {
				?>
                <tr>
                    <td colspan="12"><?php echo Language::get( 'No orders found matching your criteria.' ); ?></td>
                </tr>
				<?php
			}
			?>
            </tbody>

        </table>

        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-bottom" class="screen-reader-text"><?php echo Language::get( 'Select bulk action' ); ?></label>
                <select name="action2" id="bulk-action-selector-bottom">
                    <option value="-1"><?php echo Language::get( 'Bulk Actions' ); ?></option>
                    <option value="approve"><?php echo Language::get( 'Approve' ); ?></option>
                    <option value="disapprove"><?php echo Language::get( 'Disapprove' ); ?></option>
                </select>
                <input type="submit" id="doaction2" class="button action" value="<?php echo Language::get( 'Apply' ); ?>">
            </div>

            <div class="tablenav-pages">
                <span class="displaying-num"><?php printf( Language::get( '%s items' ), $total_orders ); ?></span>
				<?php Utility::display_pagination( $total_orders, MAX, $offset, "admin.php?page=mds-approve-pixels&app=$Y_or_N&BID=$BID" ); ?>
            </div>
            <br class="clear"/>
        </div>

    </form>

</div>

<script type="text/javascript">
    // Javascript to handle bulk actions selection
    jQuery(document).ready(function ($) {
        $('#doaction, #doaction2').click(function (e) {
            var selector = $(this).is('#doaction') ? '#bulk-action-selector-top' : '#bulk-action-selector-bottom';
            var action = $(selector).val();
            if (action === 'approve') {
                $('input[name="mass_approve"]').val('Approve Orders');
                $('input[name="mass_disapprove"]').val(''); // Clear other action
            } else if (action === 'disapprove') {
                $('input[name="mass_disapprove"]').val('Disapprove Orders');
                $('input[name="mass_approve"]').val(''); // Clear other action
            } else {
                // If no valid bulk action selected, prevent form submission maybe?
                // Or let WordPress handle the "-1" value. Currently, just clearing.
                $('input[name="mass_approve"]').val('');
                $('input[name="mass_disapprove"]').val('');
            }
            // Add hidden input for do_it_now state for bulk actions
             if ($('input[name="do_it_now"]').is(':checked')) {
                if (!$('input[name="do_it_now_bulk"]').length) {
                     $('<input>').attr({
                        type: 'hidden',
                        name: 'do_it_now',
                        value: 'true'
                    }).appendTo('form[name="form1"]');
                 }
            } else {
                 $('input[name="do_it_now_bulk"]').remove(); // Remove if unchecked
             }

        });
    });
</script>