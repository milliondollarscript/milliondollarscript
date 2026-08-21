# Million Dollar Script 2 Migration

Million Dollar Script can inspect an older Million Dollar Script 2 installation and import compatible data into the current data model. The old plugin is not deactivated unless you choose that action.

## Before Importing

- Back up the database and uploaded files.
- Keep the older plugin files available until the migration is verified.
- Confirm the source table prefix on the Migration page.
- Run a dry run first and read the warnings.
- Do not run multiple migration imports against the same site at the same time.

## What The Import Covers

The importer is designed to bring forward core grid data, packages, price rules, orders, blocks, placements, settings, and detected page mappings when matching data exists in the older installation. It also maps recognized order-email enable flags and templates, the WooCommerce preference, order terms and expiration dates, uploaded placement artwork, grid background images and opacity, popup text, destination URLs, compatible custom-field values, and MDS Pixel page identities.

Imported records keep a migration map so later steps can resolve old IDs to new records. Grid, order, placement, and page relationships use those mapped identities instead of assuming old and new database numbers match. Large imports run in resumable batches and can be paused from the Migration page.

Paid order inventory is reconciled with the linked legacy block rows when an older saved block list is incomplete. Every supported record is imported once or listed in the verification report with an anonymous source identifier and a reason it was skipped or needs review.

Pages created by the current Million Dollar Script installation and pages tied to another source grid are excluded from legacy page detection. Pages with explicit legacy grid IDs keep the matching migrated grid. A page without an explicit grid uses a grid from the same migration source. When an older page is converted, its original title and content are saved in post metadata before the shortcode is replaced.

Legacy `mds-pixel` posts map to lightweight MDS 3.0 advertiser-page proxies without executing the old theme template. Their current slug and `_wp_old_slug` history are preserved, and the bounded MDS 2 base history is recognized for exact redirects. The proxies remain drafts while individual advertiser pages are disabled. After review, enable the feature and run **Synchronize advertiser pages**. If the theme contains `mds-pixel/single-mds-pixel.php`, copy the presentation you still need into `million-dollar-script/single-advertiser.php`; MDS 3.0 deliberately never executes the old template because it may depend on MDS 2 globals and private data.

### Custom Fields And Metadata

Active field definitions found in the older `mds_custom_fields` table are used to identify and sanitize values stored on legacy ad posts. Those values are copied into the imported order's `mds_fields` metadata, including the label, type, value, and formatted value. Other non-core ad metadata is preserved under `legacy_ad_post_meta`; routine WordPress editor and embed metadata is excluded.

Field definitions themselves are not silently added to the paid Fields extension. Recreate or import the fields you still want to collect on new orders, retaining the same field keys when existing migrated values must continue to display. Custom code can adjust migration through `million-dollar-script/migration/legacy/mds/field/definitions`, `million-dollar-script/migration/legacy/mds/fields`, `million-dollar-script/migration/legacy/ad/metadata`, and `million-dollar-script/migration/legacy/order/metadata`.

## After Importing

Review the verification report and then check:

- Imported, skipped, repaired, and warning totals. Investigate every skip or warning before relying on the migrated site.
- Grid dimensions, block size, pricing, currency, and availability.
- Imported orders and their statuses.
- Published placements and uploaded images.
- Standard page mappings.
- Email settings and message content.
- Legacy custom fields and any theme or plugin code that reads them.
- Individual advertiser page visibility, old exact URLs, canonical tags, robots behavior, and sitemap inclusion.

Test at least one order from each meaningful legacy status and payment path. A legacy WooCommerce preference is retained, but the runtime uses it only while the WooCommerce Checkout extension is registered and ready. Otherwise checkout falls back to standalone without discarding the saved preference.

## Keeping The Older Plugin

It is usually safest to leave the older plugin installed but inactive until the new site has been reviewed. Deactivate it only after you are comfortable that the migrated grid, orders, pages, and uploads are correct.

Million Dollar Script does not provide a one-click rollback for a completed database import. Interrupted imports can be run again to reconnect incomplete relationships and recreate missing migration-map entries without duplicating orders or placements. Reverting the whole migration requires restoring the database and uploads backup taken before import. Do not treat the saved original page content as a complete site rollback.

## Developer Notes

The current plugin retains compatibility constants and selected legacy hooks for extensions and custom code. Code that reads old database tables or `_milliondollarscript_<field_key>` ad-post metadata directly should be updated to read current order metadata, repositories, REST API payloads, shortcodes, or documented hooks.
