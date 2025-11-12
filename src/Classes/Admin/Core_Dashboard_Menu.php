<?php
/**
 * Core_Dashboard_Menu class
 *
 * Registers core plugin menu items in the dashboard menu.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Classes\Admin;

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
		// Setup Wizard.
		Menu_Registry::register(
			array(
				'slug'     => 'milliondollarscript_wizard',
				'title'    => 'Setup Wizard',
				'url'      => admin_url( 'admin.php?page=milliondollarscript_wizard' ),
				'position' => 1,
			)
		);

		// Admin - top level.
		Menu_Registry::register(
			array(
				'slug'     => 'milliondollarscript_admin',
				'title'    => 'Admin',
				'url'      => admin_url( 'admin.php?page=milliondollarscript_admin' ),
				'position' => 2,
			)
		);

		// Admin > Pixel Management (parent group).
		Menu_Registry::register(
			array(
				'slug'     => 'pixel-management',
				'title'    => 'Pixel Management',
				'url'      => null,
				'parent'   => 'milliondollarscript_admin',
				'position' => 1,
			)
		);

		// Admin > Pixel Management > Grids.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-manage-grids',
				'title'    => 'Grids',
				'url'      => admin_url( 'admin.php?page=mds-manage-grids' ),
				'parent'   => 'pixel-management',
				'position' => 1,
			)
		);

		// Admin > Pixel Management > Packages.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-packages',
				'title'    => 'Packages',
				'url'      => admin_url( 'admin.php?page=mds-packages' ),
				'parent'   => 'pixel-management',
				'position' => 2,
			)
		);

		// Admin > Pixel Management > Price Zones.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-price-zones',
				'title'    => 'Price Zones',
				'url'      => admin_url( 'admin.php?page=mds-price-zones' ),
				'parent'   => 'pixel-management',
				'position' => 3,
			)
		);

		// Admin > Pixel Management > Not For Sale.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-not-for-sale',
				'title'    => 'Not For Sale',
				'url'      => admin_url( 'admin.php?page=mds-not-for-sale' ),
				'parent'   => 'pixel-management',
				'position' => 4,
			)
		);

		// Admin > Pixel Management > Backgrounds.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-backgrounds',
				'title'    => 'Backgrounds',
				'url'      => admin_url( 'admin.php?page=mds-backgrounds' ),
				'parent'   => 'pixel-management',
				'position' => 5,
			)
		);

		// Admin > Pixel Management > Shortcodes.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-get-shortcodes',
				'title'    => 'Shortcodes',
				'url'      => admin_url( 'admin.php?page=mds-get-shortcodes' ),
				'parent'   => 'pixel-management',
				'position' => 6,
			)
		);

		// Admin > Pixel Management > Process Pixels.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-process-pixels',
				'title'    => 'Process Pixels',
				'url'      => admin_url( 'admin.php?page=mds-process-pixels' ),
				'parent'   => 'pixel-management',
				'position' => 7,
			)
		);

		// Admin > Orders (parent group).
		Menu_Registry::register(
			array(
				'slug'     => 'orders',
				'title'    => 'Orders',
				'url'      => null,
				'parent'   => 'milliondollarscript_admin',
				'position' => 2,
			)
		);

		// Admin > Orders > Waiting.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-orders-waiting',
				'title'    => 'Waiting',
				'url'      => admin_url( 'admin.php?page=mds-orders-waiting' ),
				'parent'   => 'orders',
				'position' => 1,
			)
		);

		// Admin > Orders > Completed.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-orders-completed',
				'title'    => 'Completed',
				'url'      => admin_url( 'admin.php?page=mds-orders-completed' ),
				'parent'   => 'orders',
				'position' => 2,
			)
		);

		// Admin > Orders > Reserved.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-orders-reserved',
				'title'    => 'Reserved',
				'url'      => admin_url( 'admin.php?page=mds-orders-reserved' ),
				'parent'   => 'orders',
				'position' => 3,
			)
		);

		// Admin > Orders > Expired.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-orders-expired',
				'title'    => 'Expired',
				'url'      => admin_url( 'admin.php?page=mds-orders-expired' ),
				'parent'   => 'orders',
				'position' => 4,
			)
		);

		// Admin > Orders > Denied.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-orders-denied',
				'title'    => 'Denied',
				'url'      => admin_url( 'admin.php?page=mds-orders-denied' ),
				'parent'   => 'orders',
				'position' => 5,
			)
		);

		// Admin > Orders > Cancelled.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-orders-cancelled',
				'title'    => 'Cancelled',
				'url'      => admin_url( 'admin.php?page=mds-orders-cancelled' ),
				'parent'   => 'orders',
				'position' => 6,
			)
		);

		// Admin > Orders > Deleted.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-orders-deleted',
				'title'    => 'Deleted',
				'url'      => admin_url( 'admin.php?page=mds-orders-deleted' ),
				'parent'   => 'orders',
				'position' => 7,
			)
		);

		// Admin > Orders > Map.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-map-of-orders',
				'title'    => 'Map',
				'url'      => admin_url( 'admin.php?page=mds-map-of-orders' ),
				'parent'   => 'orders',
				'position' => 8,
			)
		);

		// Admin > Orders > Clear Orders.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-clear-orders',
				'title'    => 'Clear Orders',
				'url'      => admin_url( 'admin.php?page=mds-clear-orders' ),
				'parent'   => 'orders',
				'position' => 9,
			)
		);

		// Admin > Pixel Approval (parent group).
		Menu_Registry::register(
			array(
				'slug'     => 'pixel-approval',
				'title'    => 'Pixel Approval',
				'url'      => null,
				'parent'   => 'milliondollarscript_admin',
				'position' => 3,
			)
		);

		// Admin > Pixel Approval > Awaiting Approval.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-approve-pixels-awaiting',
				'title'    => 'Awaiting Approval',
				'url'      => admin_url( 'admin.php?page=mds-approve-pixels&app=N' ),
				'parent'   => 'pixel-approval',
				'position' => 1,
			)
		);

		// Admin > Pixel Approval > Approved.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-approve-pixels-approved',
				'title'    => 'Approved',
				'url'      => admin_url( 'admin.php?page=mds-approve-pixels&app=Y' ),
				'parent'   => 'pixel-approval',
				'position' => 2,
			)
		);

		// Admin > Reports (parent group).
		Menu_Registry::register(
			array(
				'slug'     => 'reports',
				'title'    => 'Reports',
				'url'      => null,
				'parent'   => 'milliondollarscript_admin',
				'position' => 4,
			)
		);

		// Admin > Reports > Transaction Log.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-transaction-log',
				'title'    => 'Transaction Log',
				'url'      => admin_url( 'admin.php?page=mds-transaction-log' ),
				'parent'   => 'reports',
				'position' => 1,
			)
		);

		// Admin > Reports > Top Customers.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-top-customers',
				'title'    => 'Top Customers',
				'url'      => admin_url( 'admin.php?page=mds-top-customers' ),
				'parent'   => 'reports',
				'position' => 2,
			)
		);

		// Admin > Reports > Outgoing Email.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-outgoing-email',
				'title'    => 'Outgoing Email',
				'url'      => admin_url( 'admin.php?page=mds-outgoing-email' ),
				'parent'   => 'reports',
				'position' => 3,
			)
		);

		// Admin > Reports > Clicks (parent group).
		Menu_Registry::register(
			array(
				'slug'     => 'clicks',
				'title'    => 'Clicks',
				'url'      => null,
				'parent'   => 'reports',
				'position' => 4,
			)
		);

		// Admin > Reports > Clicks > Top Clicks.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-top-clicks',
				'title'    => 'Top Clicks',
				'url'      => admin_url( 'admin.php?page=mds-top-clicks' ),
				'parent'   => 'clicks',
				'position' => 1,
			)
		);

		// Admin > Reports > Clicks > Click Reports.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-click-reports',
				'title'    => 'Click Reports',
				'url'      => admin_url( 'admin.php?page=mds-click-reports' ),
				'parent'   => 'clicks',
				'position' => 2,
			)
		);

		// Admin > System (parent group).
		Menu_Registry::register(
			array(
				'slug'     => 'system',
				'title'    => 'System',
				'url'      => null,
				'parent'   => 'milliondollarscript_admin',
				'position' => 5,
			)
		);

		// Admin > System > System Information.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-system-information',
				'title'    => 'System Information',
				'url'      => admin_url( 'admin.php?page=mds-system-information' ),
				'parent'   => 'system',
				'position' => 1,
			)
		);

		// Admin > System > License.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-license',
				'title'    => 'License',
				'url'      => admin_url( 'admin.php?page=mds-license' ),
				'parent'   => 'system',
				'position' => 2,
			)
		);

		// Admin > Manage Pages.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-page-management',
				'title'    => 'Manage Pages',
				'url'      => admin_url( 'admin.php?page=mds-page-management' ),
				'parent'   => 'milliondollarscript_admin',
				'position' => 6,
			)
		);

		// Admin > Create Pages.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-create-pages',
				'title'    => 'Create Pages',
				'url'      => admin_url( 'admin.php?page=mds-create-pages' ),
				'parent'   => 'milliondollarscript_admin',
				'position' => 7,
			)
		);

		// Admin > Compatibility.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-compatibility',
				'title'    => 'Compatibility',
				'url'      => admin_url( 'admin.php?page=mds-compatibility' ),
				'parent'   => 'milliondollarscript_admin',
				'position' => 8,
			)
		);

		// Options - top level.
		Menu_Registry::register(
			array(
				'slug'     => 'milliondollarscript_options',
				'title'    => 'Options',
				'url'      => admin_url( 'admin.php?page=milliondollarscript_options' ),
				'position' => 3,
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

		// Changelog.
		Menu_Registry::register(
			array(
				'slug'     => 'milliondollarscript_changelog',
				'title'    => 'Changelog',
				'url'      => admin_url( 'admin.php?page=milliondollarscript_changelog' ),
				'position' => 5,
			)
		);

		// Logs.
		Menu_Registry::register(
			array(
				'slug'     => 'milliondollarscript_logs',
				'title'    => 'Logs',
				'url'      => admin_url( 'admin.php?page=milliondollarscript_logs' ),
				'position' => 6,
			)
		);

		// Extensions - top level.
		Menu_Registry::register(
			array(
				'slug'     => 'mds-extensions',
				'title'    => 'Extensions',
				'url'      => admin_url( 'admin.php?page=mds-extensions' ),
				'position' => 7,
			)
		);

		// View Site.
		Menu_Registry::register(
			array(
				'slug'     => 'view-site',
				'title'    => 'View Site',
				'url'      => home_url( '/' ),
				'position' => 8,
			)
		);

		// MDS (external link).
		Menu_Registry::register(
			array(
				'slug'     => 'mds-external',
				'title'    => 'MDS',
				'url'      => 'https://milliondollarscript.com/',
				'position' => 9,
				'target'   => '_blank',
			)
		);
	}
}
