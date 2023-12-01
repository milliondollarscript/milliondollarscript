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
use MillionDollarScript\Classes\FormFields;

defined( 'ABSPATH' ) or exit;

if ( ! empty( $_POST['search'] ) ) {
	return;
}

@set_time_limit( 180 );

global $wpdb;

$oid = 0;
if ( isset( $_REQUEST['mass_complete'] ) && $_REQUEST['mass_complete'] != '' ) {

	foreach ( $_REQUEST['orders'] as $oid ) {

		$sql = "SELECT * from " . MDS_DB_PREFIX . "orders where order_id=" . intval( $oid );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		$order_row = mysqli_fetch_array( $result );

		if ( $order_row['status'] != 'completed' ) {
			complete_order( $order_row['user_id'], $oid );
			debit_transaction( $order_row['user_id'], $order_row['price'], $order_row['currency'], $order_row['order_id'], 'complete', 'Admin' );
		}
	}

	if ( ! isset( $_REQUEST['page'] ) ) {
		return;
	}
}

if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'complete' ) {

	$sql = "SELECT * from " . MDS_DB_PREFIX . "orders where order_id=" . intval( $_REQUEST['order_id'] );
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
	$order_row = mysqli_fetch_array( $result );

	complete_order( $_REQUEST['user_id'], $_REQUEST['order_id'] );
	debit_transaction( $_REQUEST['order_id'], $order_row['price'], $order_row['currency'], $order_row['order_id'], 'complete', 'Admin' );

	if ( ! isset( $_REQUEST['page'] ) ) {
		return;
	}
}

if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'cancel' ) {
	cancel_order( $_REQUEST['order_id'] );

	if ( ! isset( $_REQUEST['page'] ) ) {
		return;
	}
}

if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'unreserve' ) {
	unreserve_block( $_REQUEST['block_id'], $_REQUEST['banner_id'] );

	if ( ! isset( $_REQUEST['page'] ) ) {
		return;
	}
}

if ( isset( $_REQUEST['mass_cancel'] ) && $_REQUEST['mass_cancel'] != '' ) {
	foreach ( $_REQUEST['orders'] as $oid ) {

		//echo "$order_id ";
		cancel_order( $oid );
	}

	if ( ! isset( $_REQUEST['page'] ) ) {
		return;
	}
}

if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'delete' ) {

	delete_order( $_REQUEST['order_id'] );

	if ( ! isset( $_REQUEST['page'] ) ) {
		return;
	}
}

if ( isset( $_REQUEST['mass_delete'] ) && $_REQUEST['mass_delete'] != '' ) {

	foreach ( $_REQUEST['orders'] as $oid ) {
		delete_order( $oid );
	}

	if ( ! isset( $_REQUEST['page'] ) ) {
		return;
	}
}

$q_aday     = isset( $_REQUEST['q_aday'] ) ? intval( $_REQUEST['q_aday'] ) : 0;
$q_amon     = isset( $_REQUEST['q_amon'] ) ? intval( $_REQUEST['q_amon'] ) : 0;
$q_ayear    = isset( $_REQUEST['q_ayear'] ) ? intval( $_REQUEST['q_ayear'] ) : 0;
$q_name     = isset( $_REQUEST['q_name'] ) && is_string( $_REQUEST['q_name'] ) ? $_REQUEST['q_name'] : '';
$q_username = isset( $_REQUEST['q_username'] ) && is_string( $_REQUEST['q_username'] ) ? $_REQUEST['q_username'] : '';
$q_resumes  = isset( $_REQUEST['q_resumes'] ) ? filter_var( $_REQUEST['q_resumes'], FILTER_VALIDATE_BOOLEAN ) : false;
$q_news     = isset( $_REQUEST['q_news'] ) ? filter_var( $_REQUEST['q_news'], FILTER_VALIDATE_BOOLEAN ) : false;
$q_email    = isset( $_REQUEST['q_email'] ) && filter_var( $_REQUEST['q_email'], FILTER_VALIDATE_EMAIL ) ? $_REQUEST['q_email'] : '';
$q_company  = isset( $_REQUEST['q_company'] ) && is_string( $_REQUEST['q_company'] ) ? $_REQUEST['q_company'] : '';
$search     = isset( $_REQUEST['search'] ) && is_string( $_REQUEST['search'] ) ? $_REQUEST['search'] : '';

$valid_show  = [ 'reserved', 'waiting', 'completed', 'expired', 'cancelled', 'deleted' ];
$show        = isset( $_REQUEST['show'] ) && in_array( $_REQUEST['show'], $valid_show ) ? $_REQUEST['show'] : '';
$show_suffix = ! empty( $show ) ? '-' . $show : '';

$q_string = "&q_name=$q_name&q_username=$q_username&q_email=$q_email&q_aday=$q_aday&q_amon=$q_amon&q_ayear=$q_ayear&search=$search";

$where_sql = "";
$date_link = "";
unset( $sql );
if ( $show == 'reserved' ) {
	$sql       = "SELECT * FROM " . MDS_DB_PREFIX . "blocks as t1, " . $wpdb->users . " as t2 where t1.user_id=t2.ID AND status='reserved' ORDER BY t1.block_id DESC";
	$date_link = "&show=reserved";
} else if ( $show == 'waiting' ) {
	$where_sql = " AND (status ='confirmed' OR status='pending') ";
	$date_link = "&show=waiting";
} else if ( $show == 'cancelled' ) {
	$where_sql = " AND (status ='cancelled') ";
	$date_link = "&show=cancelled";
} else if ( $show == 'expired' ) {
	$where_sql = " AND (status ='expired') ";
	$date_link = "&show=expired";
} else if ( $show == 'deleted' ) {
	$where_sql = " AND (status ='deleted') ";
	$date_link = "&show=deleted";
} else if ( $show == 'completed' ) {
	$where_sql = " AND status ='completed' ";
}

if ( $q_name != '' ) {
	$list = preg_split( "/[\s,]+/", $q_name );
	$or1  = array();
	$or2  = array();

	foreach ( $list as $name_part ) {
		$or1[] = $wpdb->prepare( " (um1.meta_key = 'first_name' AND um1.meta_value LIKE %s) ", '%' . $wpdb->esc_like( $name_part ) . '%' );
		$or2[] = $wpdb->prepare( " (um2.meta_key = 'last_name' AND um2.meta_value LIKE %s) ", '%' . $wpdb->esc_like( $name_part ) . '%' );
	}

	$where_sql .= " AND (" . implode( ' OR ', $or1 ) . " OR " . implode( ' OR ', $or2 ) . ")";
}

if ( $q_username != '' ) {
	$list = preg_split( "/[\s,]+/", $q_username );
	$or   = array();

	foreach ( $list as $username_part ) {
		$or[] = $wpdb->prepare( " (t2.user_login LIKE %s) ", '%' . $wpdb->esc_like( $username_part ) . '%' );
	}

	$where_sql .= " AND (" . implode( ' OR ', $or ) . ")";
}

if ( isset( $_REQUEST['user_id'] ) && $_REQUEST['user_id'] != '' ) {
	$user_id   = intval( $_REQUEST['user_id'] );
	$where_sql .= $wpdb->prepare( " AND t1.user_id = %d", $user_id );
}

if ( ! isset( $sql ) ) {
	$sql = "SELECT t1.*, t2.*, um1.meta_value as FirstName, um2.meta_value as LastName, t2.user_login as Username
        FROM " . MDS_DB_PREFIX . "orders as t1
        INNER JOIN " . $wpdb->users . " as t2 ON t1.user_id = t2.ID
        LEFT JOIN " . $wpdb->usermeta . " as um1 ON t2.ID = um1.user_id AND um1.meta_key = 'first_name'
        LEFT JOIN " . $wpdb->usermeta . " as um2 ON t2.ID = um2.user_id AND um2.meta_key = 'last_name'
        WHERE 1=1 $where_sql
        ORDER BY t1.order_date DESC";
}

// Pagination related variables
$records_per_page = 40;
$offset           = isset( $_REQUEST['offset'] ) ? intval( $_REQUEST['offset'] ) : 0;

// Get total number of records without pagination
$count_query   = "SELECT COUNT(*) as total_records FROM ({$sql}) as sub";
$total_records = $wpdb->get_var( $count_query );
$pages         = ceil( $total_records / $records_per_page );

// Adding 'LIMIT' clause to the SQL query for pagination
$sql .= $wpdb->prepare( " LIMIT %d, %d", $offset, $records_per_page );

// Execute the paginated query
$paginated_results = $wpdb->get_results( $sql, ARRAY_A );

?>

<form style="margin: 0" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission"/>
    <input type="hidden" name="mds_dest" value="orders<?php echo $show_suffix; ?>"/>
    <input type="hidden" name="search" value="search"/>

    <input type="hidden" value="<?php echo $show; ?>" name="show">
    <table border="0" cellpadding="2" cellspacing="0" style="border-collapse: collapse" id="AutoNumber2" width="100%">
        <tr>
            <td width="63" bgcolor="#EDF8FC" valign="top">
                <p><b>Name</b></p>
            </td>
            <td width="286" bgcolor="#EDF8FC" valign="top">
                <input type="text" name="q_name" size="39" value="<?php echo $q_name; ?>"/></td>
            <td width="71" bgcolor="#EDF8FC" valign="top">
                <p align="right"><b>Username</b></td>
            <td width="299" bgcolor="#EDF8FC" valign="top">
                <input type="text" name="q_username" size="28" value="<?php echo $q_username; ?>"/></td>
        </tr>
        <tr>
            <td width="63" bgcolor="#EDF8FC" valign="top">
                <p align="right"><b>Date From:</b></td>
            <td width="286" bgcolor="#EDF8FC" valign="top">
                <select name="q_aday">
                    <option></option>
                    <option <?php if ( $q_aday == '1' ) {
						echo ' selected ';
					} ?> >1
                    </option>
                    <option <?php if ( $q_aday == '2' ) {
						echo ' selected ';
					} ?> >2
                    </option>
                    <option <?php if ( $q_aday == '3' ) {
						echo ' selected ';
					} ?> >3
                    </option>
                    <option <?php if ( $q_aday == '4' ) {
						echo ' selected ';
					} ?> >4
                    </option>
                    <option <?php if ( $q_aday == '5' ) {
						echo ' selected ';
					} ?> >5
                    </option>
                    <option <?php if ( $q_aday == '6' ) {
						echo ' selected ';
					} ?> >6
                    </option>
                    <option <?php if ( $q_aday == '7' ) {
						echo ' selected ';
					} ?>>7
                    </option>
                    <option <?php if ( $q_aday == '8' ) {
						echo ' selected ';
					} ?>>8
                    </option>
                    <option <?php if ( $q_aday == '9' ) {
						echo ' selected ';
					} ?> >9
                    </option>
                    <option <?php if ( $q_aday == '25' ) {
						echo ' selected ';
					} ?> >25
                    </option>
                    <option <?php if ( $q_aday == '26' ) {
						echo ' selected ';
					} ?> >26
                    </option>
                    <option <?php if ( $q_aday == '10' ) {
						echo ' selected ';
					} ?> >10
                    </option>
                    <option <?php if ( $q_aday == '11' ) {
						echo ' selected ';
					} ?> > 11
                    </option>
                    <option <?php if ( $q_aday == '12' ) {
						echo ' selected ';
					} ?> >12
                    </option>
                    <option <?php if ( $q_aday == '13' ) {
						echo ' selected ';
					} ?> >13
                    </option>
                    <option <?php if ( $q_aday == '14' ) {
						echo ' selected ';
					} ?> >14
                    </option>
                    <option <?php if ( $q_aday == '15' ) {
						echo ' selected ';
					} ?> >15
                    </option>
                    <option <?php if ( $q_aday == '16' ) {
						echo ' selected ';
					} ?> >16
                    </option>
                    <option <?php if ( $q_aday == '17' ) {
						echo ' selected ';
					} ?> >17
                    </option>
                    <option <?php if ( $q_aday == '18' ) {
						echo ' selected ';
					} ?> >18
                    </option>
                    <option <?php if ( $q_aday == '19' ) {
						echo ' selected ';
					} ?> >19
                    </option>
                    <option <?php if ( $q_aday == '20' ) {
						echo ' selected ';
					} ?> >20
                    </option>
                    <option <?php if ( $q_aday == '21' ) {
						echo ' selected ';
					} ?> >21
                    </option>
                    <option <?php if ( $q_aday == '22' ) {
						echo ' selected ';
					} ?> >22
                    </option>
                    <option <?php if ( $q_aday == '23' ) {
						echo ' selected ';
					} ?> >23
                    </option>
                    <option <?php if ( $q_aday == '24' ) {
						echo ' selected ';
					} ?> >24
                    </option>
                    <option <?php if ( $q_aday == '27' ) {
						echo ' selected ';
					} ?> >27
                    </option>
                    <option <?php if ( $q_aday == '28' ) {
						echo ' selected ';
					} ?> >28
                    </option>
                    <option <?php if ( $q_aday == '29' ) {
						echo ' selected ';
					} ?> >29
                    </option>
                    <option <?php if ( $q_aday == '30' ) {
						echo ' selected ';
					} ?> >30
                    </option>
                    <option <?php if ( $q_aday == '31' ) {
						echo ' selected ';
					} ?> >31
                    </option>
                </select>
                <select name="q_amon">
                    <option></option>
                    <option <?php if ( $q_amon == '1' ) {
						echo ' selected ';
					} ?> value="1">Jan
                    </option>
                    <option <?php if ( $q_amon == '2' ) {
						echo ' selected ';
					} ?> value="2">Feb
                    </option>
                    <option <?php if ( $q_amon == '3' ) {
						echo ' selected ';
					} ?> value="3">Mar
                    </option>
                    <option <?php if ( $q_amon == '4' ) {
						echo ' selected ';
					} ?> value="4">Apr
                    </option>
                    <option <?php if ( $q_amon == '5' ) {
						echo ' selected ';
					} ?> value="5">May
                    </option>
                    <option <?php if ( $q_amon == '6' ) {
						echo ' selected ';
					} ?> value="6">Jun
                    </option>
                    <option <?php if ( $q_amon == '7' ) {
						echo ' selected ';
					} ?> value="7">Jul
                    </option>
                    <option <?php if ( $q_amon == '8' ) {
						echo ' selected ';
					} ?> value="8">Aug
                    </option>
                    <option <?php if ( $q_amon == '9' ) {
						echo ' selected ';
					} ?> value="9">Sep
                    </option>
                    <option <?php if ( $q_amon == '10' ) {
						echo ' selected ';
					} ?> value="10">Oct
                    </option>
                    <option <?php if ( $q_amon == '11' ) {
						echo ' selected ';
					} ?> value="11">Nov
                    </option>
                    <option <?php if ( $q_amon == '12' ) {
						echo ' selected ';
					} ?> value="12">Dec
                    </option>
                </select>
                <input type="text" name="q_ayear" size="4" value="<?php echo $q_ayear; ?>"/>
            </td>
            <td width="71" bgcolor="#EDF8FC" valign="top"></td>
            <td width="299" bgcolor="#EDF8FC" valign="top"></td>
        </tr>
        <tr>
            <td width="731" bgcolor="#EDF8FC" colspan="4">
                <b><input type="submit" value="Find" name="B1" style="float: left"><?php if ( $search == 'search' ) { ?>
                    &nbsp; </b><b>[<a
                            href="<?php echo esc_url( admin_url( 'admin.php?page=mds-orders' ) ); ?>&show=<?php echo $show; ?>">Start
                        a New Search</a>]</b><?php } ?>
            </td>
        </tr>
    </table>
</form>

<?php

switch ( $show ) {
	case 'reserved':
		echo '<p>Showing reserved blocks.</p>';
		break;
	case 'waiting':
		echo '<p>Showing new orders waiting.</p>';
		break;
	case 'completed':
		echo '<p>Showing completed orders.</p>';
		break;
	case 'expired':
		echo '<p>Showing expired orders.</p>';
		break;
	case 'cancelled':
		echo '<p>Showing cancelled orders. Note: Blocks are kept reserved for cancelled orders. Delete the order to free the blocks.</p>';
		break;
	case 'deleted':
		echo '<p>Showing deleted orders.</p>';
		break;
}

if ( isset( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] != '' ) {
	echo '<h3>*** Highlighting order #' . esc_html( $_REQUEST['order_id'] ) . '.</h3> ';
}

?>

<form style="margin: 0;" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" name="form1">
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission"/>
    <input type="hidden" name="mds_dest" value="orders"/>
    <input type="hidden" name="offset" value="<?php echo $offset; ?>"/>
    <input type="hidden" name="q_name" value="<?php echo esc_attr( $q_name ); ?>">
    <input type="hidden" name="q_username" value="<?php echo esc_attr( $q_username ); ?>">
    <input type="hidden" name="q_news" value="<?php echo esc_attr( $q_news ); ?>">
    <input type="hidden" name="q_resumes" value="<?php echo esc_attr( $q_resumes ); ?>">
    <input type="hidden" name="q_email" value="<?php echo esc_attr( $q_email ); ?>">
    <input type="hidden" name="q_aday" value="<?php echo esc_attr( $q_aday ); ?>">
    <input type="hidden" name="q_amon" value="<?php echo esc_attr( $q_amon ); ?>">
    <input type="hidden" name="q_ayear" value="<?php echo esc_attr( $q_ayear ); ?>">
    <input type="hidden" name="q_company" value="<?php echo esc_attr( $q_company ); ?>">
    <input type="hidden" name="search" value="<?php echo esc_attr( $search ); ?>">

    <input type="hidden" name="show" value="<?php echo esc_attr( $show ); ?>">
    <input type="hidden" name="offset" value="<?php echo $offset; ?>">
    <div style="text-align: center;"><b><?php echo $total_records; ?> Orders Returned (<?php echo $pages; ?> pages) </b>
    </div>
	<?php
	// Calculate current page
	$cur_page = $offset / $records_per_page + 1;

	if ( $total_records > $records_per_page ) {
		// calculate number of pages & current page
		$q_string .= "&show=" . $show;
		$nav      = nav_pages_struct( $q_string, $total_records, $records_per_page );
		$LINKS    = 40;
		render_nav_pages( $nav, $LINKS, $q_string );
	}
	?>
    <table width="100%" cellSpacing="1" cellPadding="3" align="center" bgColor="#d9d9d9" border="0">
        <tr>
            <td colspan="12"> <?php if ( $show != 'DE' ) { ?>
                    With selected:
					<?php
					if ( $show != 'RE' ) {
						?>
                        <input type="submit" value='Complete'
                               onclick="if (!confirmLink(this, 'Complete for all selected, are you sure?')) return false"
                               name='mass_complete'>
						<?php
					}
					if ( $show != 'CA' ) {
						?>
                        | <input type="submit" value='Cancel' name='mass_cancel'
                                 onclick="if (!confirmLink(this, 'Cancel for all selected, are you sure?')) return false">
						<?php
					}
					if ( $show == 'CA' ) {
						?>
                        | <input type="submit" value='Delete' name='mass_delete'
                                 onclick="if (!confirmLink(this, 'Delete for all selected, are you sure?')) return false">
						<?php
					}
				} ?></td>
        </tr>
        <tr bgcolor="#eaeaea">
            <td><input type="checkbox" onClick="checkBoxes('orders');"></td>
            <td><b>Order Date</b></td>
            <td><b>Customer Name</b></td>
            <td><b>Username & ID</b></td>
            <td><b>OrderID</b></td>
            <td><b>AdID</b></td>
            <td><b>Grid</b></td>
            <td><b>Quantity</b></td>
            <td><b>Amount</b></td>
            <td><b>Status</b></td>
        </tr>
		<?php
		$pixel_per_page      = get_user_option( 'edit_mds-pixel_per_page' );
		$posts_per_page      = $pixel_per_page && $pixel_per_page > 0 ? intval( $pixel_per_page ) : 20;
		$mds_pixel_post_type = \MillionDollarScript\Classes\FormFields::$post_type;
		$post_statuses       = array_keys( FormFields::get_statuses() );

		$i = 0;

		// Loop through each of the pages of records
		foreach ( $paginated_results as $row ) {
			$i ++;

			$post_id = intval( $row['ad_id'] );

			$page_number = 1;
			$position    = 0;

			// Loop through MDS Pixel posts in batches to find the correct post
			while ( true ) {
				$args = array(
					'post_type'        => $mds_pixel_post_type,
					'posts_per_page'   => $posts_per_page,
					'fields'           => 'ids',
					'orderby'          => 'date',
					'order'            => 'DESC',
					'suppress_filters' => true,
					'post_status'      => $post_statuses,
					'paged'            => $page_number,
				);

				$query = get_posts( $args );

				// Loop through each post in this batch
				foreach ( $query as $query_post_id ) {
					$position ++;

					if ( $query_post_id === $post_id ) {
						// Match found so save the link and break out of the loops
						$page_number = ceil( $position / $posts_per_page );
						$pixel_link  = admin_url( 'edit.php?post_type=' . $mds_pixel_post_type . '&paged=' . $page_number . '&post_id=' . $post_id );
						break 2;
					}
				}

				// No posts found so break out of the loop
				if ( count( $query ) < $posts_per_page ) {
					break;
				}

				$page_number ++;
			}

			if ( ! isset( $pixel_link ) ) {
				$pixel_link = admin_url( 'edit.php?post_type=' . $mds_pixel_post_type );
			}

			?>
            <tr onmouseover="old_bg=this.getAttribute('bgcolor');this.setAttribute('bgcolor', '#FBFDDB', 0);"
                onmouseout="this.setAttribute('bgcolor', old_bg, 0);"
                bgColor="<?php if ( ( $_REQUEST['order_id'] ?? '' ) == $row['order_id'] ) {
				    echo '#FFFF99';
			    } else {
				    echo '#ffffff';
			    } ?>">
                <td><input type="checkbox" name="orders[]" value="<?php echo $row['order_id']; ?>"></td>
                <td><?php echo get_date_from_gmt( $row['order_date'] ); ?></td>
                <td><?php echo esc_html( $row['FirstName'] . " " . $row['LastName'] ); ?></td>
                <td><?php echo esc_html( $row['Username'] ); ?> (<a
                            href='<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $row['ID'] ) ); ?>'>#<?php echo intval( $row['ID'] ); ?></a>)
                </td>
                <td>#<?php echo intval( $row['order_id'] ); ?></td>
                <td><?php if ( ! empty( $row['ad_id'] ) ) { ?><a href='<?php echo esc_url( $pixel_link ); ?>'>
                        #<?php echo intval( $row['ad_id'] ); ?></a><?php } ?></td>
                <td><?php

					$sql = "select * from " . MDS_DB_PREFIX . "banners where banner_id=" . $row['banner_id'];
					$b_result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
					$b_row = mysqli_fetch_array( $b_result );

					if ( $b_row ) {
						echo esc_html( $b_row['name'] );
					}

					?></td>
                <td><?php echo intval( $row['quantity'] ); ?></td>
                <td><?php echo esc_html( Currency::convert_to_default_currency_formatted( $row['currency'], $row['price'] ) ); ?></td>
                <td><?php echo esc_html( $row['status'] ); ?><br>
					<?php
					$refunded = false;
					if ( $row['status'] == 'cancelled' ) {
						$sql = "select * from " . MDS_DB_PREFIX . "transactions where type='CREDIT' and order_id=" . intval( $row['order_id'] );
						$r1 = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
						if ( mysqli_num_rows( $r1 ) > 0 ) {
							$refunded = true;
							echo "(Refunded)";
						}
					}
					if ( $show == 'RE' ) {
						?>
                        <input type="button"
                               style="font-size: 9px;"
                               value="Cancel"
                               onclick="if (!confirmLink(this, 'Unreserve block <?php echo intval( $row['block_id'] ); ?>, are you sure?')) return false;"
                               data-link="<?php echo esc_url(
							       admin_url(
								       'admin.php?page=mds-orders&mds-action=unreserve&user_id=' . intval( $row['ID'] ) . '&block_id=' . intval( $row['block_id'] ) . '&banner_id=' . intval( $row['banner_id'] ) . '&order_id=' . intval( $row['order_id'] ) . $date_link . $q_string . '&show=' . $show
							       )
						       ); ?>">
					<?php } else {
						if ( ( $row['status'] != 'completed' ) && ( $row['status'] != 'deleted' ) && ! $refunded ) {
							?>
                            <input type="button"
                                   style="font-size: 9px;"
                                   value="Complete"
                                   onclick="if (!confirmLink(this, 'Payment from <?php echo esc_attr( $row['user_nicename'] ); ?> to be completed. Order for <?php echo $row['price']; ?> will be credited to their account.\n ** Are you sure? **')) return false;"
                                   data-link="<?php echo esc_url(
								       admin_url(
									       'admin.php?page=mds-orders&mds-action=complete&user_id=' . intval( $row['ID'] ) . '&order_id=' . intval( $row['order_id'] . $date_link . $q_string . "&show=" . $show )
								       )
							       ); ?>">
							<?php
						}
						if ( $row['status'] == 'cancelled' ) {
							?>
                            <input type="button"
                                   style="font-size: 9px;"
                                   value="Delete"
                                   onclick="if (!confirmLink(this, 'Delete the order from <?php echo esc_attr( $row['LastName'] ) . ", " . esc_attr( $row['FirstName'] ); ?>, are you sure?')) return false;"
                                   data-link="<?php echo esc_url(
								       admin_url(
									       'admin.php?page=mds-orders&mds-action=delete&order_id=' . intval( $row['order_id'] . $date_link . $q_string . "&show=" . $show )
								       )
							       ); ?>">
							<?php
						} else if ( $row['status'] == 'deleted' ) {

						} else {
							?>
                            <input type="button"
                                   style="font-size: 9px;"
                                   value="Cancel"
                                   onclick="if (!confirmLink(this, 'Cancel the order from <?php echo esc_attr( $row['LastName'] ) . ", " . esc_attr( $row['FirstName'] ); ?>, are you sure?')) return false;"
                                   data-link="<?php echo esc_url(
								       admin_url(
									       'admin.php?page=mds-orders&mds-action=cancel&user_id=' . intval( $row['ID'] ) . '&order_id=' . intval( $row['order_id'] . $date_link . $q_string . "&show=" . $show )
								       )
							       ); ?>">
							<?php
						}
					}
					?>
                </td>
            </tr>
			<?php
		}
		?>
    </table>
</form>
