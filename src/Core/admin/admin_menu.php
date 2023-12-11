<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.7
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

?>
<div class="admin-menu">
    <a target="_top" href="<?php echo esc_url( admin_url( 'admin.php?page=milliondollarscript' ) ); ?>"><img src="<?php echo esc_url( MDS_BASE_URL ); ?>src/Assets/images/M-sm.png" alt="<?php Language::out('Million Dollar Script logo'); ?>" class="milliondollarscript-logo-sm"/></a>
    <br>
    <a target="_top" href="<?php echo esc_url( admin_url( 'admin.php?page=milliondollarscript' ) ); ?>"><?php Language::out('Main'); ?></a><br/>
    <a href="<?php echo esc_url( get_site_url() ); ?>" target="_blank"><?php Language::out('View Site'); ?></a><br/>
    <hr>

    <b><?php Language::out('Configuration'); ?></b><br/>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-main-config' ); ?>"><?php Language::out('Main Config'); ?></a><br/>
    <hr>

    <b><?php Language::out('Pixel Inventory'); ?></b><br/>
    + <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-manage-grids' ) ); ?>"><?php Language::out('Manage Grids'); ?></a><br/>
    &nbsp;&nbsp;|- <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-packages' ) ); ?>"><?php Language::out('Packages'); ?></a><br/>
    &nbsp;&nbsp;|- <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-price-zones' ) ); ?>"><?php Language::out('Price Zones'); ?></a><br/>
    &nbsp;&nbsp;|- <a href="<?php echo admin_url( 'admin.php?page=mds-not-for-sale' ); ?>"><?php Language::out('Not For Sale'); ?></a><br/>
    &nbsp;&nbsp;|- <a href="<?php echo admin_url( 'admin.php?page=mds-backgrounds' ); ?>"><?php Language::out('Backgrounds'); ?></a><br/>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-get-shortcodes' ); ?>"><?php Language::out('Get Shortcodes'); ?></a><br/>
    <hr>

    <b><?php Language::out('Customer Admin'); ?></b><br/>
    - <a href="<?php echo admin_url( 'users.php' ); ?>"><?php Language::out('List Customers'); ?></a><br/>
    <span><?php Language::out('Current orders:'); ?></span><br>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-orders-waiting' ); ?>"><?php Language::out('Orders: Waiting'); ?></a><br/>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-orders-completed' ); ?>"><?php Language::out('Orders: Completed'); ?></a><br/>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-orders-reserved' ); ?>"><?php Language::out('Orders: Reserved'); ?></a><br/>
    <span><?php Language::out('Non-current orders:'); ?></span><br>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-orders-expired' ); ?>"><?php Language::out('Orders: Expired'); ?></a><br/>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-orders-cancelled' ); ?>"><?php Language::out('Orders: Cancelled'); ?></a><br/>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-orders-deleted' ); ?>"><?php Language::out('Orders: Deleted'); ?></a><br/>
    <span><?php Language::out('Map:'); ?></span><br>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-map-of-orders' ); ?>"><?php Language::out('Map of Orders'); ?></a><br/>
    <span><?php Language::out('Transactions:'); ?></span><br>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-transaction-log' ); ?>"><?php Language::out('Transaction Log'); ?></a><br/>
    <span><?php Language::out('Other:'); ?></span><br>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-clear-orders' ); ?>"><?php Language::out('Clear Orders'); ?></a><br/>
    <hr>

    <b><?php Language::out('Pixel Admin'); ?></b><br/>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-approve-pixels&app=N' ); ?>"><?php Language::out('Approve Pixels'); ?></a><br/>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-approve-pixels&app=Y' ); ?>"><?php Language::out('Disapprove Pixels'); ?></a><br/>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-process-pixels' ); ?>"><?php Language::out('Process Pixels'); ?></a><br/>
    <hr>

    <b><?php Language::out('Reports'); ?>       </b><br/>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-list' ); ?>"><?php Language::out('List'); ?></a><br/>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-top-customers' ); ?>"><?php Language::out('Top Customers'); ?></a><br/>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-outgoing-email' ); ?>"><?php Language::out('Outgoing Email'); ?></a><br/>
    <span><?php Language::out('Clicks:'); ?></span><br>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-top-clicks' ); ?>"><?php Language::out('Top Clicks'); ?></a><br/>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-click-reports' ); ?>"><?php Language::out('Click Reports'); ?></a><br/>
    <hr>

    <b><?php Language::out('Info'); ?></b><br/>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-system-information' ); ?>"><?php Language::out('System Information'); ?></a><br/>
    - <a href="<?php echo admin_url( 'admin.php?page=mds-license' ); ?>"><?php Language::out('License'); ?></a><br/>
    <br/>
    <a href="https://milliondollarscript.com" target="_blank">MillionDollarScript.com</a><br/>
</div>