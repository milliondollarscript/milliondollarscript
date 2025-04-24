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
if ( isset( $_REQUEST['order_id'] ) ) {
	$order_id = intval( $_REQUEST['order_id'] );
} else {
	// Ensure order_id is initialized
	$order_id = 0;
}


// Get the current user's info
$user_info = wp_get_current_user();

// Display dynamic title based on the action
// Default to 'N' if not set
$Y_or_N = $_GET['app'] ?? 'N';
if ( $Y_or_N == 'Y' ) {
	$title = Language::get( 'Previously Approved Pixels' );
} else {
	$title = Language::get( 'Pixels Awaiting Approval' );
}

// Process 'approve' action
if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'approve' ) {

	$order_id = intval( $_REQUEST['order_id'] );
	if ( ! $order_id ) {
		die( 'Invalid order_id' );
	}
	// Nonce verification added
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
	if ( isset( $_REQUEST['do_it_now'] ) && $_REQUEST['do_it_now'] === 'true' ) {
		Utility::process_grid_image( $BID );
	}

	Utility::redirect_to_previous_approve_page();
}

// Process 'mass_approve' action
if ( isset( $_REQUEST['mass_approve'] ) && $_REQUEST['mass_approve'] != '' ) {
	// Nonce verification added
	check_admin_referer( 'mds-admin' );
	if ( isset( $_REQUEST['orders'] ) && sizeof( $_REQUEST['orders'] ) > 0 ) {

		foreach ( $_REQUEST['orders'] as $order_id ) {
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
		if ( isset( $_REQUEST['do_it_now'] ) && $_REQUEST['do_it_now'] === 'true' ) {
			Utility::process_grid_image( $BID );
		}
	}
	Utility::redirect_to_previous_approve_page();
}

// Process 'disapprove' action
if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'disapprove' ) {

	$order_id = intval( $_REQUEST['order_id'] );
	if ( ! $order_id ) {
		die( 'Invalid order_id' );
	}
	// Nonce verification added
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
	if ( isset( $_REQUEST['do_it_now'] ) && $_REQUEST['do_it_now'] === 'true' ) {
		Utility::process_grid_image( $BID );
	}

	Utility::redirect_to_previous_approve_page();
}

// Process 'mass_disapprove' action
if ( isset( $_REQUEST['mass_disapprove'] ) && $_REQUEST['mass_disapprove'] != '' ) {
	// Nonce verification added
	check_admin_referer( 'mds-admin' );
	if ( isset( $_REQUEST['orders'] ) && sizeof( $_REQUEST['orders'] ) > 0 ) {

		foreach ( $_REQUEST['orders'] as $order_id ) {
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
		if ( isset( $_REQUEST['do_it_now'] ) && $_REQUEST['do_it_now'] === 'true' ) {
			Utility::process_grid_image( $BID );
		}
	}
	Utility::redirect_to_previous_approve_page();
}


// Process 'deny' action (set order status to denied)
if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'deny' ) {
	$order_id = intval( $_REQUEST['order_id'] );
	if ( ! $order_id ) {
		die( 'Invalid order_id' );
	}
	// Nonce verification added
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
	if ( isset( $_REQUEST['do_it_now'] ) && $_REQUEST['do_it_now'] === 'true' ) {
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
$sql_where      = $wpdb->prepare( "WHERE T1.approved = %s AND T1.status != 'denied' AND T1.banner_id = %d", $where_approved, $BID );

// Count total orders for pagination, ensuring it's an integer
$total_orders = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT T1.order_id) FROM " . MDS_DB_PREFIX . "orders T1 {$sql_where}" );
error_log('[MDS Debug] Approve Pixels - Total Orders Query: ' . $wpdb->last_query);
error_log('[MDS Debug] Approve Pixels - Total Orders Count: ' . $total_orders);

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

error_log('[MDS Debug] Approve Pixels - Fetch Orders SQL: ' . $sql);
$results = $wpdb->get_results( $sql, ARRAY_A );
error_log('[MDS Debug] Approve Pixels - Fetch Results: ' . print_r($results, true));

// Process 'save_links' action
if ( isset( $_REQUEST['save_links'] ) && $_REQUEST['save_links'] != '' ) {
	// Nonce verification added
	check_admin_referer( 'mds-admin' );
	if ( sizeof( $_REQUEST['urls'] ) > 0 ) {
		$i = 0;

		foreach ( $_REQUEST['urls'] as $key => $value ) {
			$order_id = intval( $key );
			$url      = esc_url_raw( $value );
			$title    = sanitize_text_field( $_REQUEST['titles'][ $key ] );

			if ( $order_id > 0 ) {
				$wpdb->update(
					MDS_DB_PREFIX . "orders",
					[ 'url' => $url, 'title' => $title ],
					[ 'order_id' => $order_id ],
					[ '%s', '%s' ],
					[ '%d' ]
				);
			}
			$i ++;
		}
	}
	// Optional: Process grid images immediately if checked
	if ( isset( $_REQUEST['do_it_now'] ) && $_REQUEST['do_it_now'] === 'true' ) {
		Utility::process_grid_image( $BID );
	}
	Utility::redirect_to_previous_approve_page();
}
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

    <form name="form1" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=mds-approve-pixels' ) ); ?>">
		<?php // Nonce for mass actions ?>
		<?php wp_nonce_field( 'mds-admin' ); ?>
        <input type="hidden" name="page" value="mds-approve-pixels"/>
        <input type="hidden" name="offset" value="<?php echo $offset; ?>"/>
        <input type="hidden" name="app" value="<?php echo $Y_or_N; ?>"/>
        <input type="hidden" name="BID" value="<?php echo $BID; ?>"/>

        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-approve-pixels&app=N&BID=' . $BID ) ); ?>"
               class="black"><?php echo Language::get( 'Awaiting Approval' ); ?></a>
            |
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-approve-pixels&app=Y&BID=' . $BID ) ); ?>"
               class="black"><?php echo Language::get( 'Approved' ); ?></a>
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
            <div class="alignleft actions">
				<input type="submit" class="button" name="save_links" value="<?php echo Language::get( 'Save Changes' ); ?>"/>
				&nbsp; <!-- Add some space -->
				<label for="do_it_now_top">
					<input type="checkbox" name="do_it_now" id="do_it_now_top" value="true"/> <?php echo Language::get( 'Update Grid Image After Saving' ); ?>
				</label>
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
                                <form method="POST" action="<?php echo esc_url( admin_url( 'admin.php?page=mds-approve-pixels' ) ); ?>">
                                    <?php wp_nonce_field( 'mds-approve-order_' . $order_id ); ?>
                                    <input type="hidden" name="mds-action" value="approve">
                                    <input type="hidden" name="BID" value="<?php echo intval( $row['banner_id'] ); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                    <input type="hidden" name="offset" value="<?php echo $offset; ?>">
                                    <input type="hidden" name="app" value="<?php echo $Y_or_N; ?>">
                                    <input type="hidden" name="do_it_now" value="">
                                    <input type="submit" class="button button-small" style="background-color: #388E3C; color: #fff;" value="Approve">
                                </form>
                            <?php } ?>
                            <?php if ( $row['approved'] == 'Y' ) { ?>
                                <form method="POST" action="<?php echo esc_url( admin_url( 'admin.php?page=mds-approve-pixels' ) ); ?>">
                                    <?php wp_nonce_field( 'mds-disapprove-order_' . $order_id ); ?>
                                    <input type="hidden" name="mds-action" value="disapprove">
                                    <input type="hidden" name="BID" value="<?php echo intval( $row['banner_id'] ); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                    <input type="hidden" name="offset" value="<?php echo $offset; ?>">
                                    <input type="hidden" name="app" value="<?php echo $Y_or_N; ?>">
                                    <input type="hidden" name="do_it_now" value="">
                                    <input type="submit" class="button button-small" style="background-color: #E65100; color: #fff;" value="Disapprove">
                                </form>
                            <?php } ?>
                            <form method="POST" action="<?php echo esc_url( admin_url( 'admin.php?page=mds-approve-pixels' ) ); ?>">
                                <?php wp_nonce_field( 'mds-deny-order_' . $order_id ); ?>
                                <input type="hidden" name="mds-action" value="deny">
                                <input type="hidden" name="BID" value="<?php echo intval( $row['banner_id'] ); ?>">
                                <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                <input type="hidden" name="offset" value="<?php echo $offset; ?>">
                                <input type="hidden" name="app" value="<?php echo $Y_or_N; ?>">
                                <input type="hidden" name="do_it_now" value="">
                                <input type="submit" class="button button-small" style="background-color: #D32F2F; color:#fff;" value="Deny">
                            </form>
                        </td>
                        <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-orders&order_id=' . $order_id ) ); ?>"><?php echo $order_id; ?></a></td>
                        <td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . intval($row['ad_id']) . '&action=edit' ) ); ?>"><?php echo esc_html( $row['ad_id'] ?? '' ); ?></a></td>
                        <td><?php echo esc_html( $row['order_date'] ); ?></td>
                        <td><a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $row['user_id'] ) ); ?>"><?php echo esc_html( $row['user_login'] ); ?></a></td>
                        <td><?php echo intval( $row['block_count'] ); ?></td>
                        <td class="column-url"><input type="text" name="urls[<?php echo $order_id; ?>]" value="<?php echo esc_url( $row['url'] ?? '' ); ?>"/></td>
                        <td class="column-title"><input type="text" name="titles[<?php echo $order_id; ?>]" value="<?php echo esc_attr( $row['title'] ?? '' ); ?>"/></td>
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