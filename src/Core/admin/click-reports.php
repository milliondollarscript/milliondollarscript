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

use MillionDollarScript\Classes\Data\Config;

defined( 'ABSPATH' ) or exit;

$ADVANCED_CLICK_COUNT = Config::get( 'ADVANCED_CLICK_COUNT' );
if ( $ADVANCED_CLICK_COUNT != 'YES' ) {
	die ( "Advanced click tracking not enabled. You will need to enable advanced click tracking in the Main Config" );
}

global $f2;
$BID = $f2->bid();

$sql = "Select * from " . MDS_DB_PREFIX . "banners ";
$res = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
?>

    <h3>Click Reports</h3>
    <!-- Grid Selection Dropdown (moved below title, GET method, preserves filters) -->
    <form method="get" class="grid-select-form" style="margin-bottom:12px;display:inline-block;">
        <input type="hidden" name="page" value="mds-click-reports" />
        <?php
        // Preserve all GET params except BID
        foreach ($_GET as $k => $v) {
            if ($k === 'BID' || $k === 'page') continue;
        ?>
            <input type="hidden" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr($v); ?>" />
        <?php } ?>
        <label for="grid-select" style="font-weight:500;">Select grid:</label>
        <select name="BID" id="grid-select" onchange="this.form.submit()" style="margin-left:8px;margin-right:8px;padding:3px 26px 3px 6px;font-size:1em;">
            <?php
            mysqli_data_seek($res, 0); // Reset pointer in case used
            while ( $row = mysqli_fetch_array( $res ) ) {
                $sel = ( $row['banner_id'] == $BID && $BID != 'all' ) ? 'selected' : '';
            ?>
                <option <?php echo $sel; ?> value="<?php echo intval($row['banner_id']); ?>"><?php echo esc_html($row['name']); ?></option>
            <?php } ?>
        </select>
    </form>
<?php
// Use from_date and to_date from GET/REQUEST, or fallback to current month start and today
$local_time = current_time('timestamp');
if (!empty($_REQUEST['from_date'])) {
    $from = sanitize_text_field($_REQUEST['from_date']);
} else {
    $from = date('Y-m-01', $local_time); // First of this month
}
if (!empty($_REQUEST['to_date'])) {
    $to = sanitize_text_field($_REQUEST['to_date']);
} else {
    $to = date('Y-m-d', $local_time); // Today
}

// Query clicks per date and block
$sql_clicks = "SELECT date, block_id, SUM(clicks) AS clicks FROM " . MDS_DB_PREFIX . "clicks WHERE banner_id = " . intval($BID) . " AND date >= '{$from}' AND date <= '{$to}' GROUP BY date, block_id";
$res_clicks = mysqli_query( $GLOBALS['connection'], $sql_clicks ) or die( mds_sql_error( $sql_clicks ) );
$clicks_by_date_block = [];
$all_block_ids = [];
while ( $row = mysqli_fetch_assoc( $res_clicks ) ) {
    $date = $row['date'];
    $block_id = $row['block_id'];
    $clicks_by_date_block[$date][$block_id] = intval($row['clicks']);
    $all_block_ids[$block_id] = true;
}
// Query views per date and block
$sql_views = "SELECT date, block_id, SUM(views) AS views FROM " . MDS_DB_PREFIX . "views WHERE banner_id = " . intval($BID) . " AND date >= '{$from}' AND date <= '{$to}' GROUP BY date, block_id";
$res_views = mysqli_query( $GLOBALS['connection'], $sql_views ) or die( mds_sql_error( $sql_views ) );
$views_by_date_block = [];
while ( $row = mysqli_fetch_assoc( $res_views ) ) {
    $date = $row['date'];
    $block_id = $row['block_id'];
    $views_by_date_block[$date][$block_id] = intval($row['views']);
    $all_block_ids[$block_id] = true;
}
// Get order_id for each block_id
$block_ids_list = implode(',', array_map('intval', array_keys($all_block_ids)));
$orders_by_block = [];
if ($block_ids_list) {
    $sql_blocks = "SELECT block_id, order_id FROM " . MDS_DB_PREFIX . "blocks WHERE block_id IN ($block_ids_list) AND banner_id = " . intval($BID);
    $res_blocks = mysqli_query( $GLOBALS['connection'], $sql_blocks ) or die( mds_sql_error( $sql_blocks ) );
    while ( $row = mysqli_fetch_assoc( $res_blocks ) ) {
        $orders_by_block[$row['block_id']] = $row['order_id'];
    }
}
// Merge all dates
$all_dates = array_unique(array_merge(array_keys($clicks_by_date_block), array_keys($views_by_date_block)));
sort($all_dates);
$totalclicks = 0;
$totalviews = 0;
?>
    <style>
    .mds-click-report-table {
        margin-top: 18px;
    }
    .mds-click-report-table .wp-list-table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
    }
    .mds-click-report-table .wp-list-table th, .mds-click-report-table .wp-list-table td {
        padding: 8px 10px;
        border-bottom: 1px solid #e1e1e1;
        text-align: left;
        vertical-align: middle;
    }
    .mds-click-report-table .wp-list-table.order-view {
        table-layout: fixed;
    }
    .mds-click-report-table .wp-list-table.order-view th:nth-child(1),
    .mds-click-report-table .wp-list-table.order-view td:nth-child(1) {
        width: 120px;
    }
    .mds-click-report-table .wp-list-table.order-view th:nth-child(2),
    .mds-click-report-table .wp-list-table.order-view td:nth-child(2) {
        width: 100px;
    }
    .mds-click-report-table .wp-list-table.order-view th:nth-child(3),
    .mds-click-report-table .wp-list-table.order-view td:nth-child(3) {
        width: 100px;
    }
    .mds-click-report-table .wp-list-table.order-view th:nth-child(4),
    .mds-click-report-table .wp-list-table.order-view td:nth-child(4) {
        width: 220px;
        word-break: break-word;
    }
    .mds-click-report-table .wp-list-table th {
        font-weight: 600;
        background: #f6f7f7;
    }
    .mds-click-report-table .wp-list-table tr:nth-child(even) td {
        background: #fcfcfc;
    }
    .mds-click-report-table .wp-list-table tr:hover td {
        background: #f1f1f1;
    }
    .mds-click-report-table .wp-list-table .column-order-ids a {
        color: #0073aa;
        text-decoration: none;
    }
    .mds-click-report-table .wp-list-table .column-order-ids a:hover {
        color: #00a0d2;
        text-decoration: underline;
    }
    .mds-click-report-table .order-header {
        font-size: 1.1em;
        margin: 28px 0 10px 0;
        padding: 7px 0 2px 0;
        border-bottom: 2px solid #e1e1e1;
        background: #f9f9f9;
        font-weight: 600;
    }
    .mds-click-report-table .order-block-ids {
        font-size: 0.97em;
        color: #555;
        margin-bottom: 8px;
    }
    .mds-click-report-table .filter-bar {
        margin-bottom: 14px;
        padding: 10px 0 4px 0;
    }
    .mds-click-report-table select, .mds-click-report-table input[type="date"] {
        margin-right: 8px;
        padding: 3px 6px;
        font-size: 1em;
    }
    .mds-click-report-table .view-switcher-bar .view-btn {
        border: none;
        background: #f3f3f3;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.2s;
        outline: none;
        box-shadow: none;
        margin-right: 2px;
    }
    .mds-click-report-table .view-switcher-bar .view-btn.active,
    .mds-click-report-table .view-switcher-bar .view-btn:focus {
        background: #2271b1;
        color: white; /* Make text white for active button */
    }
    .mds-click-report-table .view-switcher-bar .view-btn.active svg rect {
        fill: white !important; /* Make ALL SVG shapes white for active button */
    }
    .mds-click-report-table .view-switcher-bar .view-btn svg rect {
        transition: fill 0.2s;
    }
    </style>
    <?php
    // Determine view type FIRST so buttons can use it for 'active' class
    $user_id = get_current_user_id();
    $requested_view_type = null;

    // --- Logic to determine and save view type ---
    // 1. Check if a specific view was requested (button click)
    if (isset($_REQUEST['view_type']) && in_array($_REQUEST['view_type'], ['order', 'block', 'date'])) {
        $requested_view_type = sanitize_text_field($_REQUEST['view_type']);
        // 2. If requested, save it immediately as the user's preference
        update_user_meta($user_id, 'mds_click_report_view_type', $requested_view_type);
        // 3. Use the requested view for THIS page load
        $view_type = $requested_view_type;
    } else {
        // 4. If no view requested, load the saved preference
        $view_type = get_user_meta($user_id, 'mds_click_report_view_type', true);
        // 5. If no preference saved or invalid, default to 'order'
        if (empty($view_type) || !in_array($view_type, ['order', 'block', 'date'])) {
            $view_type = 'order';
        }
    }
    ?>
    <div class="mds-click-report-table">
    <!-- Filter Bar with modern date pickers, text inputs, and view switcher -->
    <form method="get" class="filter-bar-combined" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:12px;">
        <div class="filter-bar-fields" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <input type="hidden" name="page" value="mds-click-reports" />
            <input type="hidden" name="BID" value="<?php echo esc_attr($BID); ?>" />
            <label for="order_id">Order ID:</label>
            <input type="text" name="order_id" id="order_id" value="<?php echo esc_attr(isset($_GET['order_id']) ? $_GET['order_id'] : ''); ?>" size="7" style="width:80px;" />
            <label for="block_id">Block ID:</label>
            <input type="text" name="block_id" id="block_id" value="<?php echo esc_attr(isset($_GET['block_id']) ? $_GET['block_id'] : ''); ?>" size="7" style="width:80px;" />
            <label for="user">User:</label>
            <input type="text" name="user" id="user" value="<?php echo esc_attr(isset($_GET['user']) ? $_GET['user'] : ''); ?>" size="10" style="width:120px;" placeholder="ID or username" />
            <label for="from_date">From:</label>
            <input type="date" name="from_date" id="from_date" value="<?php echo esc_attr($from); ?>">
            <label for="to_date">To:</label>
            <input type="date" name="to_date" id="to_date" value="<?php echo esc_attr($to); ?>">
            <button type="submit" class="button">Filter</button>
            <button type="button" class="button" onclick="window.location.href='<?php echo esc_url(admin_url('admin.php?page=mds-click-reports&BID=' . $BID)); ?>'">Reset</button>
        </div>
        <div class="view-switcher-bar" style="display:flex;align-items:center;gap:8px;">
            <button type="submit" name="view_type" value="order" class="view-btn<?php if($view_type==='order') echo ' active'; ?>" title="Order View" aria-label="Order View">
                <svg width="20" height="20" viewBox="0 0 20 20" style="vertical-align:middle;"><rect x="2" y="4" width="16" height="4" rx="1.5" fill="#555"/><rect x="2" y="10" width="16" height="4" rx="1.5" fill="#bbb"/></svg>
            </button>
            <button type="submit" name="view_type" value="block" class="view-btn<?php if($view_type==='block') echo ' active'; ?>" title="Block View" aria-label="Block View">
                <svg width="20" height="20" viewBox="0 0 20 20" style="vertical-align:middle;"><rect x="2" y="2" width="6" height="6" rx="1.5" fill="#555"/><rect x="12" y="2" width="6" height="6" rx="1.5" fill="#bbb"/><rect x="2" y="12" width="6" height="6" rx="1.5" fill="#bbb"/><rect x="12" y="12" width="6" height="6" rx="1.5" fill="#bbb"/></svg>
            </button>
            <button type="submit" name="view_type" value="date" class="view-btn<?php if($view_type==='date') echo ' active'; ?>" title="Date View" aria-label="Date View">
                <svg width="20" height="20" viewBox="0 0 20 20" style="vertical-align:middle;"><rect x="3" y="5" width="14" height="12" rx="2" fill="#bbb"/><rect x="3" y="5" width="14" height="3" rx="1.5" fill="#555"/></svg>
            </button>
        </div>
    </form>
<?php
// Sanitize and validate BID
$BID = isset($_REQUEST['BID']) ? intval($_REQUEST['BID']) : 0;
if ( $BID <= 0 ) {
    // If no valid BID from request, try to get the first banner ID from the DB
    $sql_first_banner = "SELECT banner_id FROM " . MDS_DB_PREFIX . "banners ORDER BY banner_id ASC LIMIT 1";
    $res_first_banner = mysqli_query($GLOBALS['connection'], $sql_first_banner);
    if ($res_first_banner && mysqli_num_rows($res_first_banner) > 0) {
        $first_banner_row = mysqli_fetch_assoc($res_first_banner);
        $BID = intval($first_banner_row['banner_id']);
    } else {
        // No banners found in the database at all.
        echo '<div class="error"><p>Error: No banners found in the system. Please create a banner first.</p></div>';
        return; // Exit if no banners exist
    }
}

// Helper to resolve username from user_id
function mds_get_username($user_id) {
    $user = get_userdata($user_id);
    return $user ? $user->user_login : '';
}

// Gather filter params
$selected_order = isset($_GET['order_id']) && $_GET['order_id'] !== '' ? trim($_GET['order_id']) : '';
$selected_block = isset($_GET['block_id']) && $_GET['block_id'] !== '' ? trim($_GET['block_id']) : '';
$selected_user  = isset($_GET['user']) && $_GET['user'] !== '' ? trim($_GET['user']) : '';

// Build filtered block list
$filtered_blocks = [];
foreach ($orders_by_block as $block_id => $order_id) {
    // Fetch user_id for this block
    $sql = "SELECT user_id FROM " . MDS_DB_PREFIX . "blocks WHERE block_id = " . intval($block_id) . " LIMIT 1";
    $res = mysqli_query($GLOBALS['connection'], $sql);
    $user_id = '';
    if ($row = mysqli_fetch_assoc($res)) {
        $user_id = $row['user_id'];
    }
    // Apply block filter
    if ($selected_block && $block_id != $selected_block) continue;
    // Apply order filter
    if ($selected_order && $order_id != $selected_order) continue;
    // Apply user filter (accepts ID or username)
    if ($selected_user) {
        if (is_numeric($selected_user)) {
            if ($user_id != $selected_user) continue;
        } else {
            $username = mds_get_username($user_id);
            if (stripos($username, $selected_user) === false) continue;
        }
    }
    $filtered_blocks[$block_id] = [
        'order_id' => $order_id,
        'user_id'  => $user_id,
    ];
}

// Build groupings for views
$grouped = [];
if ($view_type === 'order') {
    // Group by order
    foreach ($filtered_blocks as $block_id => $info) {
        $order_id = $info['order_id'];
        if (!$order_id) continue;
        if (!isset($grouped[$order_id])) $grouped[$order_id] = [];
        $grouped[$order_id][] = $block_id;
    }
} elseif ($view_type === 'block') {
    // Group by block
    foreach ($filtered_blocks as $block_id => $info) {
        $grouped[$block_id] = [$block_id];
    }
} elseif ($view_type === 'date') {
    // Group by date
    foreach ($all_dates as $date) {
        foreach ($filtered_blocks as $block_id => $info) {
            if (
                isset($clicks_by_date_block[$date][$block_id]) ||
                isset($views_by_date_block[$date][$block_id])
            ) {
                if (!isset($grouped[$date])) $grouped[$date] = [];
                $grouped[$date][] = $block_id;
            }
        }
    }
}

// Pagination setup
$per_page = 20;
$group_keys = array_keys($grouped);
$total_groups = count($group_keys);
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$page_groups = array_slice($group_keys, $offset, $per_page);
// Output pagination controls
if (function_exists('MillionDollarScript\Classes\System\Utility::display_pagination')) {
    \MillionDollarScript\Classes\System\Utility::display_pagination($total_groups, $per_page, $offset, admin_url('admin.php?page=mds-click-reports'));
}
$grand_total_clicks = 0;
$grand_total_views = 0;
// Output sections based on view
foreach ($page_groups as $group_key) {
    if ($view_type === 'order') {
        $order_id = $group_key;
        $block_ids = $grouped[$order_id];
        $order_link = '<a href="' . esc_url(admin_url('admin.php?page=mds-orders&order_id=' . intval($order_id))) . '" class="row-title">Order #' . intval($order_id) . '</a>';
        $block_list = implode(', ', array_map('esc_html', $block_ids));
        $user_id = '';
        if (!empty($block_ids)) {
            $first_block = $block_ids[0];
            $user_id = $filtered_blocks[$first_block]['user_id'];
        }
        $username = $user_id ? mds_get_username($user_id) : '';
        $user_link = $user_id ? '<a href="' . esc_url(admin_url('user-edit.php?user_id=' . intval($user_id))) . '" target="_blank">' . esc_html($username) . '</a>' : '';
        echo '<div class="order-header">' . $order_link . ' | User: ' . $user_link . ' (ID: ' . esc_html($user_id) . ')</div>';
        echo '<div class="order-block-ids">Blocks: ' . $block_list . '</div>';
        echo '<table class="wp-list-table widefat striped order-view"><thead><tr><th style="width: 120px;">Date</th><th style="width: 100px;">Clicks</th><th style="width: 100px;">Views</th><th style="width: 220px;">Block(s)</th></tr></thead><tbody>';
        $order_total_clicks = 0;
        $order_total_views = 0;
        foreach ($all_dates as $date) {
            $date_blocks = array_intersect($block_ids, array_keys($clicks_by_date_block[$date] ?? []) + array_keys($views_by_date_block[$date] ?? []));
            if (!$date_blocks) continue;
            $date_clicks = 0;
            $date_views = 0;
            foreach ($date_blocks as $block_id) {
                $date_clicks += $clicks_by_date_block[$date][$block_id] ?? 0;
                $date_views  += $views_by_date_block[$date][$block_id] ?? 0;
            }
            $order_total_clicks += $date_clicks;
            $order_total_views  += $date_views;
            echo '<tr>';
            echo '<td>' . esc_html(get_date_from_gmt($date)) . '</td>';
            echo '<td>' . esc_html($date_clicks) . '</td>';
            echo '<td>' . esc_html($date_views) . '</td>';
            echo '<td>' . implode(', ', array_map('esc_html', $date_blocks)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody><tfoot><tr><th>Total</th><th>' . esc_html($order_total_clicks) . '</th><th>' . esc_html($order_total_views) . '</th><th></th></tr></tfoot></table>';
        $grand_total_clicks += $order_total_clicks;
        $grand_total_views  += $order_total_views;
    } elseif ($view_type === 'block') {
        $block_id = $group_key;
        $order_id = $filtered_blocks[$block_id]['order_id'];
        $user_id = $filtered_blocks[$block_id]['user_id'];
        $username = $user_id ? mds_get_username($user_id) : '';
        $user_link = $user_id ? '<a href="' . esc_url(admin_url('user-edit.php?user_id=' . intval($user_id))) . '" target="_blank">' . esc_html($username) . '</a>' : '';
        echo '<div class="order-header">Block ' . esc_html($block_id) . ' | Order: <a href="' . esc_url(admin_url('admin.php?page=mds-orders&order_id=' . intval($order_id))) . '">' . intval($order_id) . '</a> | User: ' . $user_link . ' (ID: ' . esc_html($user_id) . ')</div>';
        echo '<table class="wp-list-table widefat striped"><thead><tr><th>Date</th><th>Clicks</th><th>Views</th></tr></thead><tbody>';
        $block_total_clicks = 0;
        $block_total_views = 0;
        foreach ($all_dates as $date) {
            $date_clicks = $clicks_by_date_block[$date][$block_id] ?? 0;
            $date_views  = $views_by_date_block[$date][$block_id] ?? 0;
            if (!$date_clicks && !$date_views) continue;
            $block_total_clicks += $date_clicks;
            $block_total_views  += $date_views;
            echo '<tr>';
            echo '<td>' . esc_html(get_date_from_gmt($date)) . '</td>';
            echo '<td>' . esc_html($date_clicks) . '</td>';
            echo '<td>' . esc_html($date_views) . '</td>';
            echo '</tr>';
        }
        echo '</tbody><tfoot><tr><th>Total</th><th>' . esc_html($block_total_clicks) . '</th><th>' . esc_html($block_total_views) . '</th></tr></tfoot></table>';
        $grand_total_clicks += $block_total_clicks;
        $grand_total_views  += $block_total_views;
    } elseif ($view_type === 'date') {
        $date = $group_key;
        $block_ids = $grouped[$date];
        echo '<div class="order-header">Date: ' . esc_html(get_date_from_gmt($date)) . '</div>';
        echo '<table class="wp-list-table widefat striped"><thead><tr><th>Block ID</th><th>Order</th><th>User</th><th>Clicks</th><th>Views</th></tr></thead><tbody>';
        $date_total_clicks = 0;
        $date_total_views = 0;
        foreach ($block_ids as $block_id) {
            $order_id = $filtered_blocks[$block_id]['order_id'];
            $user_id = $filtered_blocks[$block_id]['user_id'];
            $username = $user_id ? mds_get_username($user_id) : '';
            $user_link = $user_id ? '<a href="' . esc_url(admin_url('user-edit.php?user_id=' . intval($user_id))) . '" target="_blank">' . esc_html($username) . '</a>' : '';
            $clicks = $clicks_by_date_block[$date][$block_id] ?? 0;
            $views  = $views_by_date_block[$date][$block_id] ?? 0;
            $date_total_clicks += $clicks;
            $date_total_views  += $views;
            echo '<tr>';
            echo '<td>' . esc_html($block_id) . '</td>';
            echo '<td><a href="' . esc_url(admin_url('admin.php?page=mds-orders&order_id=' . intval($order_id))) . '">' . intval($order_id) . '</a></td>';
            echo '<td>' . $user_link . ' (ID: ' . esc_html($user_id) . ')</td>';
            echo '<td>' . esc_html($clicks) . '</td>';
            echo '<td>' . esc_html($views) . '</td>';
            echo '</tr>';
        }
        echo '</tbody><tfoot><tr><th colspan="3">Total</th><th>' . esc_html($date_total_clicks) . '</th><th>' . esc_html($date_total_views) . '</th></tr></tfoot></table>';
        $grand_total_clicks += $date_total_clicks;
        $grand_total_views  += $date_total_views;
    }
}
// Output pagination controls again at bottom
if (function_exists('MillionDollarScript\Classes\System\Utility::display_pagination')) {
    \MillionDollarScript\Classes\System\Utility::display_pagination($total_groups, $per_page, $offset, admin_url('admin.php?page=mds-click-reports'));
}
?>
    <p style="margin-top:16px;">
        <strong>Total Clicks:</strong> <?php echo $grand_total_clicks; ?><br/>
        <strong>Total Views:</strong> <?php echo $grand_total_views; ?>
    </p>
    </div>

<?php

// Add inline JS for saving/loading filters
add_action('admin_footer', function() {
    // Only run this JS on the click reports page
    if (isset($_GET['page']) && $_GET['page'] === 'mds-click-reports') {
        ?>
        <script type="text/javascript">
        (function(){
            var form = document.querySelector('.filter-bar-combined');
            if (!form) return; // Exit if form not found
            var viewButtons = form.querySelectorAll('.view-btn');

            // Function to update button visual state
            function updateButtonStyles(activeViewType) {
                 viewButtons.forEach(btn => btn.classList.remove('active'));
                 activeButton.classList.add('active');
             }

             // Attach click event listeners to view buttons
             viewButtons.forEach(function(button) {
                 button.addEventListener('click', function(event) {
                     // No preventDefault needed since we WANT the submit
                     var currentViewType = this.value;
                     updateButtonStyles(currentViewType);
                     // Form submission will handle the actual state change and save
                 });
             });

         })();
         </script>
        <?php
    }
});
?>