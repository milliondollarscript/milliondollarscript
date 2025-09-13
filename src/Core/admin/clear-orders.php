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

use MillionDollarScript\Classes\Admin\Notices;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\WooCommerce\WooCommerceFunctions;

defined( 'ABSPATH' ) or exit;


// Handle direct requests (not through admin-post.php) with CSRF protection
// This legacy handler is maintained for backward compatibility but secured
if (isset($_REQUEST['clear_orders']) && $_REQUEST['clear_orders'] == 'true' && !isset($_REQUEST['action'])) {
    // Verify CSRF nonce for security
    if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'mds-admin')) {
        // Log security violation attempt
        \MillionDollarScript\Classes\System\Logs::log( 'MDS Security: CSRF attempt blocked in clear-orders.php direct handler - User: ' . get_current_user_id() );
        wp_die( esc_html__( 'Security check failed. Please try again.', 'milliondollarscript' ), esc_html__( 'Security Error', 'milliondollarscript' ), [ 'response' => 403 ] );
    }
    
    // Verify admin capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        // Log unauthorized access attempt
        \MillionDollarScript\Classes\System\Logs::log( 'MDS Security: Unauthorized clear orders attempt - User: ' . get_current_user_id() );
        wp_die( esc_html__( 'Insufficient permissions.', 'milliondollarscript' ), esc_html__( 'Access Denied', 'milliondollarscript' ), [ 'response' => 403 ] );
    }
    
    // Buffer output to prevent "headers already sent" errors
    ob_start();
    Utility::clear_orders();
    $done = Language::get("Done!");
    ob_end_flush();
    
    // Log successful operation for security auditing
    \MillionDollarScript\Classes\System\Logs::log( 'MDS Security: Clear orders operation completed via direct handler - User: ' . get_current_user_id() );
}

// Register handler for admin-post.php submissions
function mds_clear_orders_admin_handler() {
    // Verify nonce for security
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mds-admin')) {
        wp_die('Security check failed');
    }
    
    // Process the form submission
    if (isset($_POST['clear_orders']) && $_POST['clear_orders'] == 'true') {
        // Clear orders
        Utility::clear_orders();
        
        // Redirect back to the admin page after processing
        wp_redirect(admin_url('admin.php?page=mds-clear-orders&cleared=1'));
        exit;
    }
}

// Register the admin post handler
add_action('admin_post_mds_admin_form_submission', 'mds_clear_orders_admin_handler');

if ( isset( $done ) ) {
	?>
    <h3><?php echo $done; ?></h3>
	<?php
	Language::out_replace( 'Now you should <a href="%PROCESS_PIXELS_URL%">Process Pixels</a> to regenerate your grid.', '%PROCESS_PIXELS_URL%', esc_url( admin_url( 'admin.php?page=mds-process-pixels' ) ) );
}
?>
<h1><?php Language::out( 'Clear Orders' ); ?></h1>
<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission">
    <input type="hidden" name="mds_dest" value="clear-orders">
    <input type="hidden" name="clear_orders" value="true"/>
	<?php
	$warning = Language::get( '<p>WARNING: This will completely delete all ads, blocks, and orders including any orders in progress. This cannot be undone!' );
	echo $warning;
	Language::out( 'You might want to run a backup before doing this. Click the Delete button below if you wish to proceed.</p>' );

	if ( WooCommerceFunctions::is_wc_active() ) {
		?>
        <p>
            <label>
                <input type="checkbox" name="clear_woocommerce_orders" value="true"/>
				<?php Language::out( "Clear associated WooCommerce orders too?" ); ?>
            </label>
        </p>
		<?php
	}
	?>

    <input type="submit" name="submit" value="Delete"
           onclick="return confirm('<?php Language::out( 'WARNING: This will completely delete all ads, blocks, and orders including any orders in progress. This cannot be undone!' ); ?> <?php Language::out( 'You might want to run a backup before doing this. Are you sure?' ); ?>')"/>
</form>
