<?php
/*
 * @package       mds
 * @copyright     (C) Copyright 2022 Ryan Rhode, All rights reserved.
 * @author        Ryan Rhode, ryan@milliondollarscript.com
 * @version       2022-01-30 17:07:25 EST
 * @license       This program is free software; you can redistribute it and/or modify
 *        it under the terms of the GNU General Public License as published by
 *        the Free Software Foundation; either version 3 of the License, or
 *        (at your option) any later version.
 *
 *        This program is distributed in the hope that it will be useful,
 *        but WITHOUT ANY WARRANTY; without even the implied warranty of
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *        GNU General Public License for more details.
 *
 *        You should have received a copy of the GNU General Public License along
 *        with this program;  If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 *
 *  * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *        Million Dollar Script
 *        A pixel script for selling pixels on your website.
 *
 *        For instructions see README.txt
 *
 *        Visit our website for FAQs, documentation, a list team members,
 *        to post any bugs or feature requests, and a community forum:
 *        https://milliondollarscript.com/
 *
 */

require_once __DIR__ . "/../include/init.php";

require( 'admin_common.php' );

echo '<style type="text/css">
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
</style>
<div onmouseout="hideBubble()" id="bubble">
<span id="content">
</span>
</div>
';
echo '<script>';
require( BASE_PATH . '/include/mouseover_js.inc.php' );
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

if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'approve' ) {
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

		echo "Orders Approved.<br>";
	}
}

if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'disapprove' ) {

	$sql = "UPDATE " . MDS_DB_PREFIX . "blocks set approved='N' WHERE order_id='" . intval( $_REQUEST['order_id'] ) . "' {$bid_sql}";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$sql = "UPDATE " . MDS_DB_PREFIX . "orders set approved='N' WHERE order_id='" . intval( $_REQUEST['order_id'] ) . "' {$bid_sql}";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	echo "Order Disapproved.<br>";
}

if ( isset( $_REQUEST['mass_disapprove'] ) && $_REQUEST['mass_disapprove'] != '' ) {
	if ( isset( $_REQUEST['orders'] ) && sizeof( $_REQUEST['orders'] ) > 0 ) {

		foreach ( $_REQUEST['orders'] as $order_id ) {
			$sql = "UPDATE " . MDS_DB_PREFIX . "blocks set approved='N' WHERE order_id=" . intval( $order_id ) . " {$bid_sql}";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
			$sql = "UPDATE " . MDS_DB_PREFIX . "orders set approved='N' WHERE order_id=" . intval( $order_id ) . " {$bid_sql}";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		}

		echo "Orders Disapproved.<br>";
	}
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
}

?>

<h3>Remember to process your Grid Image(s) <a href="process.php">here</a></h3>

<form name="bidselect" method="post" action="approve.php">
    <input type="hidden" name="old_order_id" value="<?php //echo $order_id; ?>">
    <input type="hidden" value="<?php echo $_REQUEST['app']; ?>" name="app">
    <label>
        Select Grid:
        <select name="BID" onchange="mds_submit(this)">
            <option value='all'
				<?php if ( $BID == 'all' ) {
					echo 'selected';
				} ?>>Show All
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

if ( isset( $_REQUEST['edit_links'] ) && $_REQUEST['edit_links'] != '' ) {

	?>
    <h3>Edit Links:</h3>
    <form method="post" action="approve.php">
        <input type="hidden" name="offset" value="<?php echo $_REQUEST['offset']; ?>">
        <input type="hidden" name="BID" value="<?php echo $BID; ?>">
        <input type="hidden" name="user_id" value="<?php echo $_REQUEST['user_id']; ?>">
        <input type="hidden" value="<?php echo $_REQUEST['app']; ?>" name="app">
        <table>
            <tr>
                <td><b>URL</b></td>
                <td><b>Alt Text</b></td>
            </tr>

			<?php

			$sql      = "SELECT alt_text, url, count(alt_text) AS COUNT, banner_id FROM " . MDS_DB_PREFIX . "blocks WHERE user_id=" . intval( $_REQUEST['user_id'] ) . "  $bid_sql group by url ";
			$m_result = mysqli_query( $GLOBALS['connection'], $sql );

			$i = 0;
			while ( $m_row = mysqli_fetch_array( $m_result ) ) {
				$i ++;
				if ( $m_row['url'] != '' ) {
					echo "<tr><td>
				<input type='hidden' name='urls[]' value='" . htmlspecialchars( $m_row['url'] ) . "'>
				<input type='text' name='new_urls[]' size='40' value=\"" . escape_html( $m_row['url'] ) . "\"></td>
						<td><input name='new_alts[]' type='text' size='80' value=\"" . escape_html( $m_row['alt_text'] ) . "\"></td></tr>";
				}
			}

			?>

        </table>
        <input type="submit" value="Save Changes" name="save_links">

    </form>

	<?php
}

$bid_sql2 = " AND " . MDS_DB_PREFIX . "blocks.banner_id=$BID ";
if ( ( $BID == 'all' ) || ( empty( $BID ) ) ) {
	$BID      = '';
	$bid_sql2 = "";
}

// whitelist $_REQUEST['app'] value
$Y_or_N = 'N';
if ( isset( $_REQUEST['app'] ) ) {
	if ( $_REQUEST['app'] == 'Y' ) {
		$Y_or_N = 'Y';
	}
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
$sql = "
SELECT " . MDS_DB_PREFIX . "orders.blocks, " . MDS_DB_PREFIX . "orders.order_date, " . MDS_DB_PREFIX . "orders.order_id, " . MDS_DB_PREFIX . "blocks.approved, " . MDS_DB_PREFIX . "blocks.status, " . MDS_DB_PREFIX . "blocks.user_id, " . MDS_DB_PREFIX . "blocks.banner_id, " . MDS_DB_PREFIX . "blocks.ad_id, " . MDS_DB_PREFIX . "ads.1, " . MDS_DB_PREFIX . "ads.2, " . MDS_DB_PREFIX . "users.FirstName, " . MDS_DB_PREFIX . "users.LastName, " . MDS_DB_PREFIX . "users.Username, " . MDS_DB_PREFIX . "users.Email 
    FROM " . MDS_DB_PREFIX . "ads, " . MDS_DB_PREFIX . "blocks, " . MDS_DB_PREFIX . "orders, " . MDS_DB_PREFIX . "users 
    WHERE " . MDS_DB_PREFIX . "orders.approved='" . $Y_or_N . "' 
      AND " . MDS_DB_PREFIX . "orders.user_id=" . MDS_DB_PREFIX . "users.ID 
      AND " . MDS_DB_PREFIX . "orders.order_id=" . MDS_DB_PREFIX . "blocks.order_id 
      AND " . MDS_DB_PREFIX . "blocks.order_id=" . MDS_DB_PREFIX . "ads.order_id 
      {$bid_sql2}
    GROUP BY " . MDS_DB_PREFIX . "orders.order_id, " . MDS_DB_PREFIX . "orders.blocks, " . MDS_DB_PREFIX . "orders.order_date, " . MDS_DB_PREFIX . "blocks.approved, " . MDS_DB_PREFIX . "blocks.status, " . MDS_DB_PREFIX . "blocks.user_id, " . MDS_DB_PREFIX . "blocks.banner_id, " . MDS_DB_PREFIX . "blocks.ad_id, " . MDS_DB_PREFIX . "ads.1, " . MDS_DB_PREFIX . "ads.2, " . MDS_DB_PREFIX . "users.FirstName, " . MDS_DB_PREFIX . "users.LastName, " . MDS_DB_PREFIX . "users.Username, " . MDS_DB_PREFIX . "users.Email 
    ORDER BY " . MDS_DB_PREFIX . "orders.order_date
";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
$count = mysqli_num_rows( $result );

$offset = isset( $_REQUEST['offset'] ) ? intval( $_REQUEST['offset'] ) : 0;

$records_per_page = 20;
if ( $count > $records_per_page ) {
	mysqli_data_seek( $result, $offset );
}

$pages    = ceil( $count / $records_per_page );
$cur_page = $offset / $records_per_page;
$cur_page ++;

if ( $count > $records_per_page ) {
	// calculate number of pages & current page

	echo "<center>";
	global $label;
	$label["navigation_page"] = str_replace( "%CUR_PAGE%", $cur_page, $label["navigation_page"] );
	$label["navigation_page"] = str_replace( "%PAGES%", $pages, $label["navigation_page"] );

	$q_string = "&app=" . $_REQUEST['app'];
	$nav      = nav_pages_struct( $q_string, $count, $records_per_page );
	$LINKS    = 40;
	render_nav_pages( $nav, $LINKS, $q_string );
	echo "</center>";
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
<form name="form1" method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>">
    <input type="hidden" name="offset" value="<?php echo $offset; ?>">
    <input type="hidden" name="BID" value="<?php echo $BID; ?>">
    <input type="hidden" name="app" value="<?php echo $_REQUEST['app']; ?>">
    <input type="hidden" name="all_go" value="">
    <table width="100%" cellSpacing="1" cellPadding="3" align="center" bgColor="#d9d9d9" border="0">
        <tr>
            <td colspan="12">
                With selected: <input type="submit" value='Approve' style="font-size: 9px; background-color: #33FF66 " onclick="if (!confirmLink(this, 'Approve for all selected, are you sure?')) return false" name='mass_approve'>
                <input type="submit" value='Disapprove' style="font-size: 9px; background-color: #FF6600" onclick="if (!confirmLink(this, 'Disapprove all selected, are you sure?')) return false" name='mass_disapprove'>
                <input type="checkbox" name="do_it_now" <?php if ( $do_it_now_checked ) {
					echo ' checked ';
				} ?> value="true"> Process Grid Images immediately after approval / disapproval <br>
            </td>
        </tr>
        <tr>
        <tr>
            <td><input type="checkbox" onClick="checkBoxes('orders');"></td>
            <td><b>Order ID</b></td>
            <td><b>Order Date</b></td>
            <td><b>Customer Name</b></td>
            <td><b>Username & ID</b></td>
            <td><b>Email</b></td>
            <td><b>X,Y</b></td>
            <td><b>Grid</b></td>
            <td><b>Image</b></td>
            <td><b>Link Text(s) & Link URL(s)</b></td>
            <td><b>Action</b></td>
        </tr>
		<?php

		// TODO: use form editor field keys

		$i = 0;
		while ( ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) && ( $i < $records_per_page ) ) {

			$banner_data = load_banner_constants( $row['banner_id'] );

			$blocks = explode( ',', $row['blocks'] );
			$coords = "";
			foreach ( $blocks as $block ) {
				$pos    = get_block_position( $block, $row['banner_id'] );
				$coords .= ( $pos['x'] / $banner_data['block_width'] ) . ',' . ( $pos['y'] / $banner_data['block_height'] ) . "<br />\n";
			}

			$i ++;
			?>
            <tr onmouseover="old_bg=this.getAttribute('bgcolor');this.setAttribute('bgcolor', '#FBFDDB', 0);" onmouseout="this.setAttribute('bgcolor', old_bg, 0);" bgColor="#ffffff">
                <td><input type="checkbox" name="orders[]" value="<?php echo $row['order_id']; ?>"></td>
                <td><span style="font-family: Arial,serif; font-size: x-small; "><?php echo $row['order_id']; ?></span></td>
                <td><span style="font-family: Arial,serif; font-size: x-small; "><?php echo $row['order_date']; ?></span></td>
                <td><span style="font-family: Arial,serif; font-size: x-small; "><?php echo $row['FirstName'] . " " . $row['LastName']; ?></span></td>
                <td><span style="font-family: Arial,serif; font-size: x-small; "><?php echo $row['Username']; ?> (#<?php echo $row['user_id']; ?>)</span></td>
                <td><span style="font-family: Arial,serif; font-size: x-small; "><?php echo $row['Email']; ?></span></td>
                <td><span style="font-family: Arial,serif; font-size: x-small; "><?php echo $coords; ?></span></td>
                <td><span style="font-family: Arial,serif; font-size: x-small; "><?php
						$sql      = "SELECT name from " . MDS_DB_PREFIX . "banners where banner_id=" . intval( $row['banner_id'] );
						$t_result = mysqli_query( $GLOBALS['connection'], $sql );
						$t_row    = mysqli_fetch_array( $t_result );
						echo $t_row['name']; ?></span></td>
                <td><span style="font-family: Arial,serif; font-size: x-small; "><img src="get_order_image.php?BID=<?php echo $row['banner_id']; ?>&aid=<?php echo $row['ad_id']; ?>" alt=""/></span></td>
                <td><span style="font-family: Arial,serif; font-size: x-small; "><?php

						if ( $row['2'] != '' ) {
							$js_str = " onmousemove=\"sB(event,'" . htmlspecialchars( str_replace( "'", "\'", ( $row['1'] ) ) ) . "',this, " . $row['ad_id'] . ")\" onmouseout=\"hI()\" ";

							echo "<span style=\"font-size: xx-small; \">" . $row['2'] . " - <a $js_str href='" . $row['2'] . "' target='_blank' >" . $row['1'] . "</a></span><br>";
						}

						echo "<a target='_blank' href='show_map.php?user_id=" . $row['user_id'] . "&BID=" . $row['banner_id'] . "'>[View Pixels...]</a>";
						?></span>
                </td>
                <td><span style="font-family: Arial,serif; font-size: x-small; "><?php
						if ( $row['approved'] == 'N' ) {
							?>
                            <input type="button" style="font-size: 9px; background-color: #33FF66" value="Approve" onclick="mds_load_page('approve.php?action=approve&amp;BID=<?php echo $row['banner_id']; ?>&amp;user_id=<?php echo $row['user_id']; ?>&amp;order_id=<?php echo $row['order_id']; ?>&amp;offset=<?php $_REQUEST['offset']; ?>&amp;app=<?php echo $_REQUEST['app']; ?>&amp;do_it_now='+document.form1.do_it_now.checked, true)"><?php
						}

						if ( $row['approved'] != 'N' ) {
							?>
                            <input type="button" style="font-size: 9px;" value="Disapprove" onclick="mds_load_page('approve.php?action=disapprove&amp;BID=<?php echo $row['banner_id']; ?>&amp;user_id=<?php echo $row['user_id']; ?>&amp;order_id=<?php echo $row['order_id']; ?>&amp;offset=<?php $_REQUEST['offset']; ?>&amp;app=<?php echo $_REQUEST['app']; ?>&amp;do_it_now='+document.form1.do_it_now.checked, true)"><?php
						}

						?>
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
	echo "<center>";
	$label["navigation_page"] = str_replace( "%CUR_PAGE%", $cur_page, $label["navigation_page"] );
	$label["navigation_page"] = str_replace( "%PAGES%", $pages, $label["navigation_page"] );
	//	echo "<span > ".$label["navigation_page"]."</span> ";
	$nav   = nav_pages_struct( $q_string, $count, $records_per_page );
	$LINKS = 40;
	render_nav_pages( $nav, $LINKS, $q_string );
	echo "</center>";
}
?>

