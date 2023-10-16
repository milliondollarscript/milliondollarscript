<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.3
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

use MillionDollarScript\Classes\Language;

defined( 'ABSPATH' ) or exit;

if ( isset( $_REQUEST['clear_orders'] ) && $_REQUEST['clear_orders'] == 'true' ) {
    \MillionDollarScript\Classes\Utility::clear_orders();
	$done = Language::get( "Done!" );
}

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
	Language::out( 'You might want to run a backup before doing this. Click the Delete button below if you wish to proceed.</p>' ); ?>
    <input type="submit" name="submit" value="Delete" onclick="if (!confirmLink(this, '<?php Language::out( 'WARNING: This will completely delete all ads, blocks, and orders including any orders in progress. This cannot be undone!' ); ?> <?php Language::out( 'You might want to run a backup before doing this. Are you sure?' ); ?>')) return false"/>
</form>
