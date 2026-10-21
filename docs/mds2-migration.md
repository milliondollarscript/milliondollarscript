# Million Dollar Script 2 Migration

Million Dollar Script can inspect an older Million Dollar Script 2 installation and import compatible data into the current data model. The old plugin is not deactivated unless you choose that action.

## Before Importing

- Back up the database and uploaded files.
- Keep the older plugin files available until the migration is verified.
- Confirm the source table prefix on the Migration page.
- Run a dry run first and read the warnings. A warning names something to put right before importing; the notes under **About this migration** explain what the migration found or did and ask for nothing. The dry run also reports whether this site's own tables can hold every character the older site contains; a table still stored in the old `utf8` character set drops emoji and some written languages, and the warning names the columns to have converted.
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
- The coverage checks at the bottom of the report: orders that held a cell in the older grid against orders that hold a place now, ads that had an image against ads showing one, and placements that fall outside the grid picture. A run can import every row and still leave part of the older site without a counterpart, and those checks say so.
- **What the database could not store.** A value that does not fit a column, or a character the column's character set cannot hold, is named here by table and column with the count, and each affected record appears under **Reconciliation Details** with the message the database gave — under *Skipped* when the row was not written, under *Repaired* when it was written with text missing. Ask the host to widen the column or convert it to `utf8mb4`, then run the import again: it updates the records it already made, so the corrected values arrive without creating a second copy.
- Grid dimensions, block size, pricing, currency, and availability. Any size carries across at its own dimensions, including non-square blocks and grids that are far from square, so the imported grid is the same shape as the older one. A source grid that recorded no dimensions at all becomes a single-block grid; set its size before opening it for sale.
- Imported orders and their statuses. In the older plugin, *confirmed* is the state a customer's own confirmation produces before payment, so an order left there arrives as **Awaiting payment**: the money is not claimed, the cell stays reserved rather than sold, and the customer can still complete the order. It arrives paid when that site recorded payment — a row in its transactions table, or a WooCommerce order it linked to the ad. The report lists the orders by the state they arrived in and singles out any that came from the confirmation step; mark one paid under Orders when it was settled outside the older plugin.
- Published placements and uploaded images. Where the only copy of an ad picture was the cell-sized one the older grid kept, the report says so: the ad shows the right picture and needs a larger image uploaded to it. The older order record kept a copy of that picture too; when the grid records no longer hold one, the picture is taken from the order record and the report names the block it came from.
- Standard page mappings.
- The review step. **Ask Customers To Confirm Orders** arrives as the older site was actually running it: that version treated the step as on unless it had been turned off, so a site that never set the option arrives with it on and a site that turned it off stays off. A choice made in Million Dollar Script 3 settings is kept through a later migration run, so re-importing does not undo a decision taken here. A new installation starts with it off. The migration report states which of these happened and where to change it. The steps themselves and the pages they use are in [Managing Orders](orders.md).
- Email settings and message content.
- Legacy custom fields and any theme or plugin code that reads them.
- Individual advertiser page visibility, old exact URLs, canonical tags, robots behavior, and sitemap inclusion.

Test at least one order from each meaningful legacy status and payment path. A legacy WooCommerce preference is retained, but the runtime uses it only while the WooCommerce Checkout extension is registered and ready. Otherwise checkout falls back to standalone without discarding the saved preference.

## Running The Import Again

Running the import again is safe. Every record the import created is tracked against its source record, so a second run updates what it already made instead of making a second copy: the same grid, the same orders, the same placements. It also reconnects relationships that were incomplete and can recover a placement that failed the first time once the older data has been corrected — for example an ad whose image was missing from the upload folder and has been put back.

A second run leaves alone anything the new site created by itself. Orders placed on the new site, pages edited after the import, and settings changed since then are not source records, so they are not rewritten.

A full reset is a different operation and is not offered as a control. Starting the migration over from the same older data means removing the grids, blocks, price rules, packages, orders, order items, and placements that belong to that source, together with their migration-map rows, while leaving the older tables and any new-site orders in place. Until a reset control exists, restoring the database and uploads backup taken before the import is the supported way to return to the point before migration.

### An Import That Stopped

Large imports run in bounded batches, and a batch can be cut short by the web host. An import left in that state stays stopped: opening the Migration page does not start it working again, and the screen says so — the run identifier, which step it stopped at and how many records it had read, and that nothing runs until **Process next batch** is chosen. Only the request that came from that control continues the batches, and it stops when the import finishes or the next batch stops.

Where a run kept its place, the progress line reads in steps and records — *Stopped at: Blocks. 214 records read so far.* — because those are the numbers the rest of the report uses. When an older run did not record how many records each step held, the counts are left unstated rather than guessed, and the bar follows the steps instead.

A stopped batch is not retried on its own, because an import is usually interrupted for a reason. The **Keep going on my own if a batch stops with an error** option turns retries on for that run; it is off by default and gives up after three in a row rather than looping. Pause is deliberate and is never overridden.

### A Record Made By An Older Version

A site can hold an import started before the plugin was updated — that is the shape of a real alpha site. Every import record keeps the plugin version that made it, and every report keeps the version that wrote it. When the installed version differs from the recorded one, the Migration screen names the version that made the record, and the Million Dollar Script 2 step in setup says which version ran the import and points at running it again. Records made before this field existed read as unnamed rather than as wrong, and an older record is never rewritten by a newer run.

Re-running on a newer version still does not create a second copy of anything: the records the older run made are recognised as its own and updated. The repairs a run reports are the work that run had to do, so a second run usually names fewer — the first run already did that work. What must not change is the result: the same attached images, the same shortened text, the same coordinates, and no record that turned into nothing. `tests/rewrite/migration-upgrade-rerun-fixture.php` checks that against the shared Million Dollar Script 2 dataset, along with the version being recorded, reported, and shown.

An import that stopped before it finished keeps its place, and continuing it after an update carries on from the step it reached rather than from the beginning. If that saved place was written in a form the installed version does not recognise, the import starts again from its first step instead of guessing at what it means, and the report says so. Starting again does not create a second copy of anything: the records already imported are recognised as its own, so only work is repeated. `tests/rewrite/migration-cross-version-resume-fixture.php` checks a paused import continued by a newer version, and a restarted one, against what a clean import of the same records produces.

### Bringing Older Records Up To A Current Rule

Some releases change what a value should be rather than what it is stored in. Which copy of an ad's picture the import should use is one such rule. A site that imported before the rule existed keeps the answer it was given, because nothing in its records changed — and re-running the whole migration is a bigger question than a site owner can answer without knowing what it would change.

For those cases the Migration screen carries a **Records made under older rules** section. It appears only when there is a rule to offer for that source, or one that has already been read again; a site with nothing outstanding does not see the section at all. Each entry is written in the site owner's terms: what it fixes, when it is worth running, and one action. After a run the entry records the date and how many records were read again from the older site, and its action becomes a deliberate **Read them again** rather than an offer.

A re-check is an ordinary resumable import over a narrower range: it starts at the first step its rule names and uses the same bounded batches, the same continue control, and the same migration map, so it updates the records that source already created and adds none. A source counts as checked only when a run reaches the end — a batch cut short by the host leaves the rule offered again.

`tests/rewrite/migration-recheck-fixture.php` checks the whole path against the shared Million Dollar Script 2 dataset: an ad whose picture file is withheld, the import settling for another copy, the file and its media record put back, and the rule re-resolving the same placement to the picture its own records name. It also checks that only the named steps are walked, that no counts grow, that the checked state is recorded and the offer removed, and that reading the records a second time changes nothing that was already current.


It is usually safest to leave the older plugin installed but inactive until the new site has been reviewed. Deactivate it only after you are comfortable that the migrated grid, orders, pages, and uploads are correct.

Million Dollar Script does not provide a one-click rollback for a completed database import. Interrupted imports can be run again to reconnect incomplete relationships and recreate missing migration-map entries without duplicating orders or placements. Reverting the whole migration requires restoring the database and uploads backup taken before import. Do not treat the saved original page content as a complete site rollback.

## Developer Notes

The current plugin retains compatibility constants and selected legacy hooks for extensions and custom code. Code that reads old database tables or `_milliondollarscript_<field_key>` ad-post metadata directly should be updated to read current order metadata, repositories, REST API payloads, shortcodes, or documented hooks.

### Standing Rules A Newer Version Can Apply

A rule about how a value is derived belongs in `src/Migration/Recheck/`: a class implementing `Recheck` names itself, the release that introduced the rule, what it fixes in a site owner's words, and the import steps that have to be read again for it — validated against `Importer::import_stages()`, so a rule naming a step this version does not have is refused rather than guessed at. `Registry::all()` lists the rules this version knows; extensions add their own through the `million-dollar-script/migration/rechecks` filter. `Runner::start()` begins a job at the first named step and carries the rule's identifier in the job blob, and `Registry::record()` writes the applied state per source prefix in the `mds3_migration_rechecks` option only when that job completes.

The rule text is what the admin reads, so it is written for them: what is wrong, when it is worth running, what changes, and what does not. Internal identifiers, table names, and stage names stay out of it.

A rule that only *would* change something under conditions this build does not produce should not be registered yet: an offer the site cannot verify is an offer that teaches the owner to ignore the section.

### Migration Checkpoints And The Job Format

An unfinished import keeps its progress inside the run report as a job blob: the stage it reached and where inside that stage it stopped. Both are positions in `Importer::JOB_STAGES`, so a blob only means anything against the stage list that wrote it. `Importer::JOB_FORMAT_VERSION` records that format, and **a change to `JOB_STAGES` — reordering, renaming, merging or removing a stage, or changing what a stage's cursor points at — has to bump that constant in the same change.**

A checkpoint written in another format is not re-read under the current list: `Importer::normalize_job_state()` restarts the import from its first stage and the run reports why, because a position in a stage list that no longer applies would otherwise silently skip or repeat a stage. Repeating finished work is safe: every write goes through the migration map, which recognises records it already created. A checkpoint written in the same format is continued where it stopped even when a different build wrote it — unknown stage names in it are dropped, and counts for stages it never recorded are rebuilt from the current list. `tests/rewrite/migration-cross-version-resume-fixture.php` checks both cases and that the result matches what a clean import of the same records produces.
