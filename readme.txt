=== Million Dollar Script ===
Tags: advertising, pixel grid, monetization, ecommerce, woocommerce
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 3.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Sell pixel-grid advertising from WordPress with responsive grids, visual ordering, standalone checkout, and migration tools.

== Description ==

Million Dollar Script turns a WordPress site into a visual advertising marketplace. Create one or more pixel grids, set prices and availability, accept artwork and advertiser links, and manage orders from a focused WordPress dashboard.

Visitors can view large boards with responsive fit, pan, and zoom controls. On ordering pages they select adjacent blocks, preview artwork in place, save their progress, and continue through the configured checkout flow.

= Core features =

* Responsive pixel grids with aligned artwork, block selection, pan, zoom, and fit controls.
* Multiple grids with searchable grid selection on public ordering and account pages.
* Packages, price zones, unavailable regions, reservation timing, and placement terms.
* Secure image uploads, in-grid previews, advertiser URLs, alt text, and popup text.
* Standalone manual checkout for sites that do not use WooCommerce.
* Optional WooCommerce checkout through a separately installed adapter.
* Order, placement, renewal, expiration, and customer email management.
* Scoped REST API keys for approved integrations.
* Dry-run migration from Million Dollar Script 2 with verification reports.
* Optional extensions for custom fields, hosted rendering, reporting, and other capabilities.

Million Dollar Script works locally without an external account. Optional services and extensions are described under External Services.

== Requirements ==

* WordPress 6.0 or newer.
* PHP 8.1 or newer.
* MySQL 5.7 or MariaDB 10.4 or newer.
* A theme with a content area wide enough for the grid experience you want to provide.

WooCommerce is optional. Install the WooCommerce Checkout extension when orders should use WooCommerce gateways and customer accounts.

== Installation ==

1. Install and activate Million Dollar Script from the WordPress Plugins screen.
2. Open **Million Dollar Script > Setup**.
3. Choose standalone checkout or install and activate the WooCommerce adapter.
4. Create the recommended grid, ordering, upload, account, and legal pages.
5. Optionally prepare starter Blog, Contact, and About pages and site navigation.
6. Review the generated pages and grid settings before opening ordering to visitors.

The initial public grid page is view-only. The separate Order Pixels page is interactive, so visitors do not begin an order by accidentally clicking the public display.

== Upgrade from Million Dollar Script 2 ==

Back up the database and site files before migration. Million Dollar Script 3.0 can run beside Million Dollar Script 2 while the imported site is reviewed, and the importer does not delete legacy tables.

1. Install and activate Million Dollar Script 3.0 without deactivating Million Dollar Script 2.
2. Open **Million Dollar Script > Migration** and run a dry run.
3. Review detected tables, pages, grids, packages, prices, orders, media, settings, and warnings.
4. Run the import only after the dry run is understood.
5. Verify public grids, ordering, uploads, account pages, prices, checkout, and email settings.
6. Keep the backup and Million Dollar Script 2 data until the migrated site is accepted.

The importer preserves recognized custom-field values in order metadata. Unmapped legacy ad metadata is retained under `legacy_ad_post_meta` for custom integrations. Legacy email templates, order timing, upload limits, and common Unix or Windows media paths are migrated when detected.

== Checkout ==

= Standalone checkout =

Standalone mode keeps orders in Million Dollar Script for manual review. An optional checkout URL can hand customers to another payment page using supported order, amount, currency, quantity, grid, and selection placeholders.

Auto-completion of manual payments is disabled by default. Administrators should mark an order paid only after confirming payment.

= WooCommerce checkout =

The separate WooCommerce adapter creates linked WooCommerce orders and synchronizes paid, failed, refunded, and cancelled states. It also supports customer account management and renewal payment links when the WooCommerce order remains payable.

== Large grids and ImageGrid ==

Core renders grids locally and exposes completed local tiles through a direct public cache. Large grids start fitted to their available page area and remain navigable with pan, zoom, and fit controls.

ImageGrid is optional and extension-owned. When installed and connected, its extension can submit demanding render jobs, display plan quota, and deliver signed versioned tiles directly from the ImageGrid CDN when the connected plan includes CDN access. Core keeps a local fallback when the service is unavailable.

== External Services ==

Million Dollar Script core works locally without an external account.

Core does not set tracking cookies. It uses browser local storage for an administrator menu preference and recoverable order details. Order references and form drafts expire after seven days; visitors can restore or dismiss a saved draft, and expired records are removed automatically.

The WordPress.org edition does not request the remote extension catalog and does not install or update extensions from an external server. Its Extensions screen links to `https://milliondollarscript.com/catalog`, where administrators can choose whether to browse separately installed products.

When an administrator explicitly activates or deactivates an extension license, claims a purchase, connects tester access, or opens licensed documentation, Million Dollar Script sends the extension slug, plugin version, site URL, a random installation identifier, and the supplied license or access credential to `https://milliondollarscript.com`. The service uses this data to validate access and return license state or licensed documentation. No such request occurs until the administrator takes one of those actions.

The direct-download edition can also request the catalog and update information from the same service when an administrator opens Million Dollar Script dashboard or extension screens. Direct-download packages may retrieve approved core or extension ZIPs after an administrator initiates installation or an update.

Unless disabled under Million Dollar Script > Settings > System, direct-download core update checks include the MDS generation and version, WordPress and PHP versions, update channel, and the slug, version, and active state of first-party Million Dollar Script extensions. The service stores a keyed hash of the normalized site address, keeps only the current extension snapshot, reports aggregate counts, and removes installation analytics after 365 days without a check. Other plugins, customer data, content, and credentials are not sent. Disabling anonymous version analytics omits this data without disabling updates.

Service terms: https://milliondollarscript.com/terms-conditions/

Privacy policy: https://milliondollarscript.com/privacy-policy/

ImageGrid is not contacted by core. Installing and connecting the separate ImageGrid extension sends account credentials, grid rendering input, and job metadata to the ImageGrid service according to that extension's settings and privacy disclosure.

== Frequently Asked Questions ==

= Do I need WooCommerce? =

No. Standalone mode supports reservations, uploads, manual payment review, and an optional external checkout URL. Use the separate WooCommerce adapter when you want WooCommerce gateways, orders, and customer accounts.

= Can I have more than one grid? =

Yes. Public ordering and account pages support multiple grids with searchable, paginated selection.

= Can I migrate from Million Dollar Script 2? =

Yes. Run the migration dry run first, keep a current backup, and verify the imported site before deactivating the older plugin.

= Does Million Dollar Script send email directly? =

Messages use WordPress `wp_mail()`. SMTP, delivery logging, and retry behavior can be supplied by a WordPress mail plugin.

= Why are premium extensions not installed from this plugin? =

The WordPress.org edition uses native WordPress installation paths. Install directory-hosted extensions through **Plugins > Add New** and upload premium ZIPs supplied with a purchase through **Plugins > Add Plugin > Upload Plugin**.

= Does the core plugin require ImageGrid? =

No. Local rendering is included. ImageGrid is optional for hosted processing and CDN delivery.

== Screenshots ==

1. Setup guides the administrator through grid, payment, page, and extension choices.
2. A responsive view-only advertising grid fitted to its page content area.
3. Interactive block ordering with selection guidance and artwork preview.
4. Grid and order administration with filters, status, statistics, and placement maps.
5. Migration dry run with source detection, warnings, and verification details.
6. Directory-safe extension management with installed tools and extension discovery.

== Support ==

Review the built-in **Million Dollar Script > Documentation** pages first. For current support options, account help, and commercial extension assistance, visit https://milliondollarscript.com/contact/.

When reporting a problem, include the WordPress and PHP versions, Million Dollar Script version, active payment provider, relevant grid or order ID, and a sanitized copy of the displayed error. Do not send license keys, API keys, passwords, or customer personal data.

== Changelog ==

Full release notes are published on the website at https://milliondollarscript.com/changelog/.

== Upgrade Notice ==

= 3.0.0 =

Back up the database and site files before migrating from Million Dollar Script 2. Run the migration dry run and verify the imported site before deactivating the older plugin.
