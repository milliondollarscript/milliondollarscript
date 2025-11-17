<?php
/**
 * Core_Dashboard_Menu class
 *
 * Registers core plugin menu items in the dashboard menu.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Classes\Admin;

use MillionDollarScript\Classes\Data\Options;

/**
 * Core_Dashboard_Menu class
 *
 * Handles registration of all core plugin dashboard menu items.
 */
class Core_Dashboard_Menu {

	/**
	 * Register core menu items
	 *
	 * Hooked into 'mds_register_dashboard_menu'.
	 */
	public static function register(): void {
		// 0. Dashboard.
		Menu_Registry::register(
			array(
				'slug'      => 'milliondollarscript',
				'title'     => 'Dashboard',
				'url'       => admin_url( 'admin.php?page=milliondollarscript' ),
				'position'  => 0,
				'icon'      => 'dashicons-dashboard',
				'icon_only' => true,
			)
		);

		// 1. Setup Wizard (icon-only).
		Menu_Registry::register(
			array(
				'slug'      => 'milliondollarscript_wizard',
				'title'     => 'Setup Wizard',
				'url'       => admin_url( 'admin.php?page=milliondollarscript_wizard' ),
				'position'  => 1,
				'icon'      => 'dashicons-admin-tools',
				'icon_only' => true,
			)
		);

		// 2. View Site (icon-only).
		Menu_Registry::register(
			array(
				'slug'      => 'view-site',
				'title'     => 'View Site',
				'url'       => home_url( '/' ),
				'position'  => 2,
				'icon'      => 'dashicons-external',
				'icon_only' => true,
			)
		);

		// 3. MDS Website (icon-only, external link).
		Menu_Registry::register(
			array(
				'slug'      => 'mds-external',
				'title'     => 'MDS Website',
				'url'       => 'https://milliondollarscript.com/',
				'position'  => 3,
				'target'    => '_blank',
				'icon'      => 'dashicons-admin-site-alt3',
				'icon_only' => true,
			)
		);

		// 4. Pixel Management (top-level parent).
		Menu_Registry::register(
			array(
				'slug'     => 'pixel-management',
				'title'    => 'Pixel Management',
				'url'      => null,
				'position' => 4,
			)
		);

		// Pixel Management > Grids.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-manage-grids',
				'title'    => 'Grids',
				'url'      => admin_url( 'admin.php?page=mds-manage-grids' ),
				'parent'   => 'pixel-management',
				'position' => 1,
			)
		);

		// Pixel Management > Packages.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-packages',
				'title'    => 'Packages',
				'url'      => admin_url( 'admin.php?page=mds-packages' ),
				'parent'   => 'pixel-management',
				'position' => 2,
			)
		);

		// Pixel Management > Price Zones.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-price-zones',
				'title'    => 'Price Zones',
				'url'      => admin_url( 'admin.php?page=mds-price-zones' ),
				'parent'   => 'pixel-management',
				'position' => 3,
			)
		);

		// Pixel Management > Not For Sale.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-not-for-sale',
				'title'    => 'Not For Sale',
				'url'      => admin_url( 'admin.php?page=mds-not-for-sale' ),
				'parent'   => 'pixel-management',
				'position' => 4,
			)
		);

		// Separator.
		Menu_Registry::register_separator( 'pm-sep-1', 'pixel-management', 5 );

		// Pixel Management > Backgrounds.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-backgrounds',
				'title'    => 'Backgrounds',
				'url'      => admin_url( 'admin.php?page=mds-backgrounds' ),
				'parent'   => 'pixel-management',
				'position' => 6,
			)
		);

		// Pixel Management > Shortcodes.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-get-shortcodes',
				'title'    => 'Shortcodes',
				'url'      => admin_url( 'admin.php?page=mds-get-shortcodes' ),
				'parent'   => 'pixel-management',
				'position' => 7,
			)
		);

		// Pixel Management > Process Pixels.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-process-pixels',
				'title'    => 'Process Pixels',
				'url'      => admin_url( 'admin.php?page=mds-process-pixels' ),
				'parent'   => 'pixel-management',
				'position' => 8,
			)
		);

		// 5. Orders (top-level parent).
		Menu_Registry::register(
			array(
				'slug'     => 'orders',
				'title'    => 'Orders',
				'url'      => null,
				'position' => 5,
			)
		);

		// Orders > Waiting.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-orders-waiting',
				'title'    => 'Waiting',
				'url'      => admin_url( 'admin.php?page=mds-orders-waiting' ),
				'parent'   => 'orders',
				'position' => 1,
			)
		);

		// Orders > Completed.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-orders-completed',
				'title'    => 'Completed',
				'url'      => admin_url( 'admin.php?page=mds-orders-completed' ),
				'parent'   => 'orders',
				'position' => 2,
			)
		);

		// Orders > Reserved.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-orders-reserved',
				'title'    => 'Reserved',
				'url'      => admin_url( 'admin.php?page=mds-orders-reserved' ),
				'parent'   => 'orders',
				'position' => 3,
			)
		);

		// Orders > Expired.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-orders-expired',
				'title'    => 'Expired',
				'url'      => admin_url( 'admin.php?page=mds-orders-expired' ),
				'parent'   => 'orders',
				'position' => 4,
			)
		);

		// Separator.
		Menu_Registry::register_separator( 'orders-sep-1', 'orders', 5 );

		// Orders > Denied.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-orders-denied',
				'title'    => 'Denied',
				'url'      => admin_url( 'admin.php?page=mds-orders-denied' ),
				'parent'   => 'orders',
				'position' => 6,
			)
		);

		// Orders > Cancelled.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-orders-cancelled',
				'title'    => 'Cancelled',
				'url'      => admin_url( 'admin.php?page=mds-orders-cancelled' ),
				'parent'   => 'orders',
				'position' => 7,
			)
		);

		// Orders > Deleted.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-orders-deleted',
				'title'    => 'Deleted',
				'url'      => admin_url( 'admin.php?page=mds-orders-deleted' ),
				'parent'   => 'orders',
				'position' => 8,
			)
		);

		// Separator.
		Menu_Registry::register_separator( 'orders-sep-2', 'orders', 9 );

		// Orders > Map.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-map-of-orders',
				'title'    => 'Map',
				'url'      => admin_url( 'admin.php?page=mds-map-of-orders' ),
				'parent'   => 'orders',
				'position' => 10,
			)
		);

		// Orders > Clear Orders.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-clear-orders',
				'title'    => 'Clear Orders',
				'url'      => admin_url( 'admin.php?page=mds-clear-orders' ),
				'parent'   => 'orders',
				'position' => 11,
			)
		);

		// 6. Pixel Approval (top-level parent).
		Menu_Registry::register(
			array(
				'slug'     => 'pixel-approval',
				'title'    => 'Pixel Approval',
				'url'      => null,
				'position' => 6,
			)
		);

		// Pixel Approval > Awaiting Approval.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-approve-pixels-awaiting',
				'title'    => 'Awaiting Approval',
				'url'      => admin_url( 'admin.php?page=mds-approve-pixels&app=N' ),
				'parent'   => 'pixel-approval',
				'position' => 1,
			)
		);

		// Pixel Approval > Approved.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-approve-pixels-approved',
				'title'    => 'Approved',
				'url'      => admin_url( 'admin.php?page=mds-approve-pixels&app=Y' ),
				'parent'   => 'pixel-approval',
				'position' => 2,
			)
		);

		// 7. Reports (top-level parent).
		Menu_Registry::register(
			array(
				'slug'     => 'reports',
				'title'    => 'Reports',
				'url'      => null,
				'position' => 7,
			)
		);

		// Reports > Transaction Log.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-transaction-log',
				'title'    => 'Transaction Log',
				'url'      => admin_url( 'admin.php?page=mds-transaction-log' ),
				'parent'   => 'reports',
				'position' => 1,
			)
		);

		// Reports > Top Customers.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-top-customers',
				'title'    => 'Top Customers',
				'url'      => admin_url( 'admin.php?page=mds-top-customers' ),
				'parent'   => 'reports',
				'position' => 2,
			)
		);

		// Reports > Outgoing Email.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-outgoing-email',
				'title'    => 'Outgoing Email',
				'url'      => admin_url( 'admin.php?page=mds-outgoing-email' ),
				'parent'   => 'reports',
				'position' => 3,
			)
		);

		// Separator.
		Menu_Registry::register_separator( 'reports-sep-1', 'reports', 4 );

		// Reports > Clicks (parent group).
		Menu_Registry::register(
			array(
				'slug'     => 'clicks',
				'title'    => 'Clicks',
				'url'      => null,
				'parent'   => 'reports',
				'position' => 5,
			)
		);

		// Reports > Clicks > Top Clicks.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-top-clicks',
				'title'    => 'Top Clicks',
				'url'      => admin_url( 'admin.php?page=mds-top-clicks' ),
				'parent'   => 'clicks',
				'position' => 1,
			)
		);

		// Reports > Clicks > Click Reports.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-click-reports',
				'title'    => 'Click Reports',
				'url'      => admin_url( 'admin.php?page=mds-click-reports' ),
				'parent'   => 'clicks',
				'position' => 2,
			)
		);

		// 8. Options (top-level).
		Menu_Registry::register(
			array(
				'slug'     => 'milliondollarscript_options',
				'title'    => 'Options',
				'url'      => admin_url( 'admin.php?page=milliondollarscript_options' ),
				'position' => 8,
			)
		);

		// Options > Emails.
		Menu_Registry::register(
			array(
				'slug'     => 'milliondollarscript_emails',
				'title'    => 'Emails',
				'url'      => admin_url( 'admin.php?page=milliondollarscript_emails' ),
				'parent'   => 'milliondollarscript_options',
				'position' => 1,
			)
		);

		// 9. System (top-level parent).
		Menu_Registry::register(
			array(
				'slug'     => 'system',
				'title'    => 'System',
				'url'      => null,
				'position' => 9,
			)
		);

		// System > System Information.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-system-information',
				'title'    => 'System Information',
				'url'      => admin_url( 'admin.php?page=mds-system-information' ),
				'parent'   => 'system',
				'position' => 1,
			)
		);

		// System > License.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-license',
				'title'    => 'License',
				'url'      => admin_url( 'admin.php?page=mds-license' ),
				'parent'   => 'system',
				'position' => 2,
			)
		);

		// Separator.
		Menu_Registry::register_separator( 'system-sep-1', 'system', 3 );

		// System > Manage Pages.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-page-management',
				'title'    => 'Manage Pages',
				'url'      => admin_url( 'admin.php?page=mds-page-management' ),
				'parent'   => 'system',
				'position' => 4,
			)
		);

		// System > Create Pages.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-create-pages',
				'title'    => 'Create Pages',
				'url'      => admin_url( 'admin.php?page=mds-create-pages' ),
				'parent'   => 'system',
				'position' => 5,
			)
		);

		// System > Compatibility.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-compatibility',
				'title'    => 'Compatibility',
				'url'      => admin_url( 'admin.php?page=mds-compatibility' ),
				'parent'   => 'system',
				'position' => 6,
			)
		);

		// Separator.
		Menu_Registry::register_separator( 'system-sep-2', 'system', 7 );

		// System > Logs.
		Menu_Registry::register(
			array(
				'slug'     => 'milliondollarscript_logs',
				'title'    => 'Logs',
				'url'      => admin_url( 'admin.php?page=milliondollarscript_logs' ),
				'parent'   => 'system',
				'position' => 8,
			)
		);

		// System > Changelog (external link to extension server).
		$extension_server = rtrim( (string) Options::get_option( 'extension_server_url', 'https://milliondollarscript.com' ), '/' );
		Menu_Registry::register(
			array(
				'slug'     => 'milliondollarscript_changelog_external',
				'title'    => 'Changelog',
				'url'      => $extension_server . '/changelog',
				'target'   => '_blank',
				'parent'   => 'system',
				'position' => 9,
			)
		);

		// 10. Extensions (top-level).
		Menu_Registry::register(
			array(
				'slug'     => 'mds-extensions',
				'title'    => 'Extensions',
				'url'      => admin_url( 'admin.php?page=mds-extensions' ),
				'position' => 10,
			)
		);
	}
}
