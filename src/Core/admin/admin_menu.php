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

use MillionDollarScript\Classes\Forms\FormFields;
use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

?>
<button class="mds-menu-toggle" aria-controls="main-menu" aria-expanded="false">
    <svg width="30" height="30" viewBox="0 0 100 70" fill="#f0f0f1">
        <rect width="100" height="10"></rect>
        <rect y="30" width="100" height="10"></rect>
        <rect y="60" width="100" height="10"></rect>
    </svg>
</button>
<ul class="mds-admin-menu">
    <!-- Dashboard / Logo -->
    <li>
        <a target="_top" href="<?php echo esc_url( admin_url( 'admin.php?page=milliondollarscript' ) ); ?>">
            <img width="40px" src="<?php echo esc_url( MDS_BASE_URL ); ?>src/Assets/images/M-sm.png"
                 alt="<?php Language::out( 'Million Dollar Script logo' ); ?>"
                 class="milliondollarscript-logo-sm"/>
        </a>
    </li>
    <!-- View Site -->
    <li>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank"><?php Language::out( 'View Site' ); ?></a>
    </li>
    <!-- Pixel Management -->
    <li>
        <a href="#"><?php Language::out( 'Pixel Management' ); ?></a>
        <ul>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-manage-grids' ) ); ?>"><?php Language::out( 'Grids' ); ?></a>
            </li>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-packages' ) ); ?>"><?php Language::out( 'Packages' ); ?></a>
            </li>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-price-zones' ) ); ?>"><?php Language::out( 'Price Zones' ); ?></a>
            </li>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-not-for-sale' ); ?>"><?php Language::out( 'Not For Sale' ); ?></a>
            </li>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-backgrounds' ); ?>"><?php Language::out( 'Backgrounds' ); ?></a>
            </li>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-get-shortcodes' ); ?>"><?php Language::out( 'Shortcodes' ); ?></a>
            </li>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-process-pixels' ); ?>"><?php Language::out( 'Process Pixels' ); ?></a>
            </li>
        </ul>
    </li>
    <!-- Orders -->
    <li>
        <a href="#"><?php Language::out( 'Orders' ); ?></a>
        <ul>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-orders-waiting' ); ?>"><?php Language::out( 'Waiting' ); ?></a>
            </li>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-orders-completed' ); ?>"><?php Language::out( 'Completed' ); ?></a>
            </li>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-orders-reserved' ); ?>"><?php Language::out( 'Reserved' ); ?></a>
            </li>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-orders-expired' ); ?>"><?php Language::out( 'Expired' ); ?></a>
            </li>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-orders-denied' ); ?>"><?php Language::out( 'Denied' ); ?></a>
            </li>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-orders-cancelled' ); ?>"><?php Language::out( 'Cancelled' ); ?></a>
            </li>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-orders-deleted' ); ?>"><?php Language::out( 'Deleted' ); ?></a>
            </li>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-map-of-orders' ); ?>"><?php Language::out( 'Map' ); ?></a>
            </li>

            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-clear-orders' ); ?>"><?php Language::out( 'Clear Orders' ); ?></a>
            </li>
        </ul>
    </li>
    <!-- Pixel Approval -->
    <li>
        <a href="#"><?php Language::out( 'Pixel Approval' ); ?></a>
        <ul>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-approve-pixels&app=N' ); ?>"><?php Language::out( 'Awaiting Approval' ); ?></a>
            </li>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-approve-pixels&app=Y' ); ?>"><?php Language::out( 'Approved' ); ?></a>
            </li>
        </ul>
    </li>
    <!-- Reports -->
    <li>
        <a href="#"><?php Language::out( 'Reports' ); ?></a>
        <ul>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-transaction-log' ); ?>"><?php Language::out( 'Transaction Log' ); ?></a>
            </li>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-top-customers' ); ?>"><?php Language::out( 'Top Customers' ); ?></a>
            </li>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-outgoing-email' ); ?>"><?php Language::out( 'Outgoing Email' ); ?></a>
            </li>
            <li>
                <a href="#"><?php Language::out( 'Clicks' ); ?></a>
                <ul>
                    <li>
                        <a href="<?php echo admin_url( 'admin.php?page=mds-top-clicks' ); ?>"><?php Language::out( 'Top Clicks' ); ?></a>
                    </li>
                    <li>
                        <a href="<?php echo admin_url( 'admin.php?page=mds-click-reports' ); ?>"><?php Language::out( 'Click Reports' ); ?></a>
                    </li>
                </ul>
            </li>
        </ul>
    </li>
    <!-- System -->
    <li>
        <a href="#"><?php Language::out( 'System' ); ?></a>
        <ul>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-system-information' ); ?>"><?php Language::out( 'System Information' ); ?></a>
            </li>
            <li>
                <a href="<?php echo admin_url( 'admin.php?page=mds-license' ); ?>"><?php Language::out( 'License' ); ?></a>
            </li>
        </ul>
    </li>
    <!-- Help -->
    <li>
        <a href="https://milliondollarscript.com" target="_blank"><?php Language::out( 'Help' ); ?></a>
    </li>
</ul>
