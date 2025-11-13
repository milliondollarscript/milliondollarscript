<?php

namespace MillionDollarScript\Classes\Pages;

use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\Language\Language;
use function wp_strip_all_tags;

class ApprovePixels {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
    }

    public function register_page() {
        $hook_suffix = add_submenu_page(
            'mds-hidden',
            'Approve Pixels',
            'Approve Pixels',
            'manage_options',
            'mds-approve-pixels',
            [ $this, 'render_page' ]
        );

        add_action( 'admin_print_styles-' . $hook_suffix, [ $this, 'styles' ] );
    }

    public function styles() {
        wp_enqueue_style( 'MillionDollarScriptStyles', MDS_BASE_URL . 'src/Assets/css/admin.css', [], filemtime( MDS_BASE_PATH . 'src/Assets/css/admin.css' ) );
    }

    public function handle_actions() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'mds-approve-pixels' ) {
            return;
        }

        global $wpdb;

        $BID = $_POST['BID'] ?? $_GET['BID'] ?? 1;
        if ( ! is_numeric( $BID ) ) {
            $BID = 1;
        }

        // Process 'approve' action
        if ( isset( $_GET['mds-action'] ) && $_GET['mds-action'] == 'approve' ) {
            $order_id = intval( $_GET['order_id'] );
            if ( ! $order_id ) {
                die( 'Invalid order_id' );
            }
            check_admin_referer( 'mds-approve-order_' . $order_id );

            if ( $BID > 0 ) {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE " . MDS_DB_PREFIX . "blocks
					SET approved = 'Y'
					WHERE order_id = %d AND banner_id = %d",
                    $order_id, $BID
                ) );
            } else {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE " . MDS_DB_PREFIX . "blocks
					SET approved = 'Y'
					WHERE order_id = %d",
                    $order_id
                ) );
            }
            if ( $BID > 0 ) {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE " . MDS_DB_PREFIX . "orders
					SET approved = 'Y'
					WHERE order_id = %d AND banner_id = %d",
                    $order_id, $BID
                ) );
            } else {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE " . MDS_DB_PREFIX . "orders
					SET approved = 'Y'
					WHERE order_id = %d",
                    $order_id
                ) );
            }

            if ( isset( $_GET['do_it_now'] ) && $_GET['do_it_now'] === 'true' ) {
                Utility::process_grid_image( $BID );
            }

            Utility::redirect_to_previous_approve_page();
        }

        // Process 'mass_approve' action
        if ( ( ( isset( $_POST['action'] ) && $_POST['action'] == 'approve' ) || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'approve' ) ) && isset( $_POST['mass_approve'] ) && $_POST['mass_approve'] != '' ) {
            check_admin_referer( 'mds-admin' );
            if ( isset( $_POST['orders'] ) && is_array($_POST['orders']) && count( $_POST['orders'] ) > 0 ) {

                foreach ( $_POST['orders'] as $order_id ) {
                    $order_id = intval( $order_id );
                    if ( $BID > 0 ) {
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE " . MDS_DB_PREFIX . "blocks
							SET approved = 'Y'
							WHERE order_id = %d AND banner_id = %d",
                            $order_id, $BID
                        ) );
                    } else {
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE " . MDS_DB_PREFIX . "blocks
							SET approved = 'Y'
							WHERE order_id = %d",
                            $order_id
                        ) );
                    }
                    if ( $BID > 0 ) {
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE " . MDS_DB_PREFIX . "orders
							SET approved = 'Y'
							WHERE order_id = %d AND banner_id = %d",
                            $order_id, $BID
                        ) );
                    } else {
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE " . MDS_DB_PREFIX . "orders
							SET approved = 'Y'
							WHERE order_id = %d",
                            $order_id
                        ) );
                    }
                }
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

            if ( $BID > 0 ) {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE " . MDS_DB_PREFIX . "blocks
					SET approved = 'N'
					WHERE order_id = %d AND banner_id = %d",
                    $order_id, $BID
                ) );
            } else {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE " . MDS_DB_PREFIX . "blocks
					SET approved = 'N'
					WHERE order_id = %d",
                    $order_id
                ) );
            }
            if ( $BID > 0 ) {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE " . MDS_DB_PREFIX . "orders
					SET approved = 'N'
					WHERE order_id = %d AND banner_id = %d",
                    $order_id, $BID
                ) );
            } else {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE " . MDS_DB_PREFIX . "orders
					SET approved = 'N'
					WHERE order_id = %d",
                    $order_id
                ) );
            }

            if ( isset( $_GET['do_it_now'] ) && $_GET['do_it_now'] === 'true' ) {
                Utility::process_grid_image( $BID );
            }

            Utility::redirect_to_previous_approve_page();
        }

        // Process 'mass_disapprove' action
        if ( ( ( isset( $_POST['action'] ) && $_POST['action'] == 'disapprove' ) || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'disapprove' ) ) && isset( $_POST['mass_disapprove'] ) && $_POST['mass_disapprove'] != '' ) {
            check_admin_referer( 'mds-admin' );
            if ( isset( $_POST['orders'] ) && is_array($_POST['orders']) && count( $_POST['orders'] ) > 0 ) {

                foreach ( $_POST['orders'] as $order_id ) {
                    $order_id = intval( $order_id );
                    if ( $BID > 0 ) {
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE " . MDS_DB_PREFIX . "blocks
							SET approved = 'N'
							WHERE order_id = %d AND banner_id = %d",
                            $order_id, $BID
                        ) );
                    } else {
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE " . MDS_DB_PREFIX . "blocks
							SET approved = 'N'
							WHERE order_id = %d",
                            $order_id
                        ) );
                    }
                    if ( $BID > 0 ) {
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE " . MDS_DB_PREFIX . "orders
							SET approved = 'N'
							WHERE order_id = %d AND banner_id = %d",
                            $order_id, $BID
                        ) );
                    } else {
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE " . MDS_DB_PREFIX . "orders
							SET approved = 'N'
							WHERE order_id = %d",
                            $order_id
                        ) );
                    }
                }
                if ( isset( $_POST['do_it_now'] ) && $_POST['do_it_now'] === 'true' ) {
                    Utility::process_grid_image( $BID );
                }
            }
            Utility::redirect_to_previous_approve_page();
        }

        // Process 'deny' action
        if ( isset( $_GET['mds-action'] ) && $_GET['mds-action'] == 'deny' ) {
            $order_id = intval( $_GET['order_id'] );
            if ( ! $order_id ) {
                die( 'Invalid order_id' );
            }
            check_admin_referer( 'mds-deny-order_' . $order_id );

            $wpdb->update(
                MDS_DB_PREFIX . "blocks",
                [ 'approved' => 'N' ],
                [ 'order_id' => $order_id ],
                [ '%s' ],
                [ '%d' ]
            );
            $wpdb->update(
                MDS_DB_PREFIX . "orders",
                [ 'status' => 'denied', 'approved' => 'N' ],
                [ 'order_id' => $order_id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );

            if ( isset( $_GET['do_it_now'] ) && $_GET['do_it_now'] === 'true' ) {
                Utility::process_grid_image( $BID );
            }

            Utility::redirect_to_previous_approve_page();
        }
    }

    public function render_page() {
        global $wpdb;

        $BID = $_POST['BID'] ?? $_GET['BID'] ?? 1;
        if ( ! is_numeric( $BID ) ) {
            $BID = 1;
        }

        $Y_or_N = sanitize_text_field( $_GET['app'] ?? 'N' );
        if ( $Y_or_N !== 'Y' ) {
            $Y_or_N = 'N';
        }

        $title = ( $Y_or_N == 'N' ) ? Language::get( 'Pixels Awaiting Approval' ) : Language::get( 'Approved Pixels' );

        $offset = $_GET['offset'] ?? 0;
        if ( ! is_numeric( $offset ) ) {
            $offset = 0;
        }

        $where_approved = ( $Y_or_N == 'Y' ) ? 'Y' : 'N';
        if ($BID == 0) {
            $sql_where      = $wpdb->prepare( "WHERE T1.approved = %s AND T1.status = 'completed' AND T1.status != 'denied'", $where_approved );
        } else {
            $sql_where      = $wpdb->prepare( "WHERE T1.approved = %s AND T1.status = 'completed' AND T1.status != 'denied' AND T1.banner_id = %d", $where_approved, $BID );
        }

        $total_orders = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT T1.order_id) FROM " . MDS_DB_PREFIX . "orders T1 {$sql_where}" );

        $sql = $wpdb->prepare(
            "SELECT T1.*, T2.block_count,
				U.user_login,
				T1.user_id,
				T1.ad_id,
				FB.url,
				FB.alt_text AS title,
				TR.type AS pstatus,
				BC.block_coords,
				T1.ad_id,
       			(SELECT name FROM " . MDS_DB_PREFIX . "banners WHERE banner_id = T1.banner_id) as banner_name
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
            30 // MAX constant is not available here, so we use the value directly
        );

        $results = $wpdb->get_results( $sql, ARRAY_A );

        ?>
        <div class="admin-container">
            <?php echo \MillionDollarScript\Classes\Admin\Menu_Registry::render(); ?>
            <div class="admin-content">
                <div class="admin-content-inner">
                    <div class="wrap">
                        <h1><?php echo esc_html( $title ); ?></h1>

                        <p class="mds-instructions"><?php echo Language::get( 'Here you can approve or disapprove pixels that have been ordered by your users.' ); ?></p>

                        <h2 class="nav-tab-wrapper">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-approve-pixels&app=N&BID=' . $BID ) ); ?>" class="nav-tab <?php echo ( $Y_or_N === 'N' ) ? 'nav-tab-active' : ''; ?>"><?php echo Language::get( 'Pixels Awaiting Approval' ); ?></a>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-approve-pixels&app=Y&BID=' . $BID ) ); ?>" class="nav-tab <?php echo ( $Y_or_N === 'Y' ) ? 'nav-tab-active' : ''; ?>"><?php echo Language::get( 'Approved Pixels' ); ?></a>
                        </h2>

                        <form name="form1" method="post" action="<?php echo esc_url( add_query_arg( 'app', $Y_or_N, admin_url( 'admin.php?page=mds-approve-pixels' ) ) ); ?>">
                            <?php wp_nonce_field( 'mds-admin' ); ?>
                            <input type="hidden" name="page" value="mds-approve-pixels"/>
                            <input type="hidden" name="offset" value="<?php echo $offset; ?>"/>
                            <input type="hidden" name="app" value="<?php echo $Y_or_N; ?>"/>
                            <input type="hidden" name="BID" value="<?php echo $BID; ?>"/>

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
                                    <input type="hidden" name="mass_approve" value="">
                                    <input type="hidden" name="mass_disapprove" value="">
                                </div>
                                <div class="tablenav-pages">
                                    <span class="displaying-num"><?php printf( Language::get( '%s items' ), $total_orders ); ?></span>
                                    <?php Utility::display_pagination( $total_orders, 30, $offset, "admin.php?page=mds-approve-pixels&app=$Y_or_N&BID=$BID" ); ?>
                                </div>
                                <br class="clear"/>
                            </div>

                            <table class="wp-list-table widefat fixed striped table-view-list posts">
                                <thead>
                                <tr>
                                    <th scope="col" id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-1"><?php Language::out( 'Select All' ); ?></label><input id="cb-select-all-1" type="checkbox"></th>
                                    <th scope="col" id="actions" class="manage-column column-actions"><?php echo Language::get( 'Actions' ); ?></th>
                                    <th scope="col" id="grid_id" class="manage-column column-grid_id"><?php echo Language::get( 'Grid ID' ); ?></th>
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
                                    <th scope="col" class="manage-column column-grid_id"><?php echo Language::get( 'Grid ID' ); ?></th>
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
                                        $order_id    = intval( $row['order_id'] );
                                        $title_plain = wp_strip_all_tags( $row['title'] ?? '' );
                                        $title_plain = trim( $title_plain );
                                        ?>
                                        <tr id="post-<?php echo $order_id; ?>" class="iedit author-self level-0 post-<?php echo $order_id; ?> type-post status-publish format-standard hentry category-uncategorized">
                                            <th scope="row" class="check-column">
                                                <label class="screen-reader-text" for="cb-select-<?php echo $order_id; ?>">
                                                    <?php printf( esc_html( Language::get( 'Select %s' ) ), esc_html( $title_plain ) ); ?>
                                                </label>
                                                <input id="cb-select-<?php echo $order_id; ?>" type="checkbox" name="orders[]" value="<?php echo $order_id; ?>">
                                            </th>
                                            <td class="column-actions">
                                                <?php if ( $row['approved'] == 'N' ) { ?>
                                                    <?php
                                                    $approve_nonce = wp_create_nonce( 'mds-approve-order_' . $order_id );
                                                    $approve_url = add_query_arg( [
                                                        'page'       => 'mds-approve-pixels',
                                                        'mds-action' => 'approve',
                                                        'order_id'   => $order_id,
                                                        'BID'        => $row['banner_id'],
                                                        'app'        => $Y_or_N,
                                                        'offset'     => $offset,
                                                        '_wpnonce'   => $approve_nonce
                                                    ], admin_url( 'admin.php' ) );
                                                    ?>
                                                    <a href="<?php echo esc_url( $approve_url ); ?>" class="button button-small mds-admin-action-approve">Approve</a>
                                                 <?php } ?>
                                                 <?php if ( $row['approved'] == 'Y' ) { ?>
                                                    <?php
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
                                                    <a href="<?php echo esc_url( $disapprove_url ); ?>" class="button button-small mds-admin-action-disapprove">Disapprove</a>
                                                 <?php } ?>
                                                <?php
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
                                                <a href="<?php echo esc_url( $deny_url ); ?>" class="button button-small mds-admin-action-deny">Deny</a>
                                            </td>
                                            <td>
                                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-manage-grids&mds-action=edit&BID=' . $row['banner_id'] ) ); ?>" title="<?php echo esc_attr( $row['banner_name'] ); ?>">
                                                    <?php echo esc_html( $row['banner_id'] ); ?>
                                                </a>
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
                                                    echo '<a href="' . $url . '" target="_blank">' . esc_html( $row['url'] ) . '</a>';
                                                }
                                                ?>
                                            </td>
                                            <td class="column-title"><?php echo esc_html( $title_plain ); ?></td>
                                            <td class="column-block_coords"><div class="block-coords-wrapper"><?php echo esc_html( $row['block_coords'] ?? '' ); ?></div></td>
                                            <td class="column-image">
                                                <?php
                                                if ( ! empty( $row['ad_id'] ) && ! empty( $row['banner_id'] ) ) {
                                                    $image_url = esc_url( Utility::get_page_url( 'get-order-image' ) . '?BID=' . $row['banner_id'] . '&aid=' . $row['ad_id'] );
                                                    ?>
                                                    <img src="<?php echo $image_url; ?>" width="50" height="50" alt="<?php echo esc_attr( $title_plain ); ?>"/>
                                                    <?php
                                                } else {
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
                                        <td colspan="13"><?php echo Language::get( 'No orders found matching your criteria.' ); ?></td>
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
                                    <?php Utility::display_pagination( $total_orders, 30, $offset, "admin.php?page=mds-approve-pixels&app=$Y_or_N&BID=$BID" ); ?>
                                </div>
                                <br class="clear"/>
                            </div>

                        </form>

                    </div>
                </div>
            </div>
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
                        $('input[name="mass_approve"]').val('');
                        $('input[name="mass_disapprove"]').val('');
                    }
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
        <?php
    }
}
