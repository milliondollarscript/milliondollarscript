# Settings Reference

Use **Million Dollar Script > Settings** for defaults shared by the core plugin. Individual grids and active extensions can add or override settings within their own scope.

## General

General settings choose the standalone currency, display symbol, theme mode, and active payment provider. The standalone currency applies when no payment extension owns checkout. WooCommerce Checkout and other providers can lock currency to the connected store so order totals and symbols remain consistent.

The default payment provider is standalone/manual checkout. A provider appears only after its extension is active and registered with Million Dollar Script.

## URLs And Redirects

Set the account, login, checkout, and thank-you destinations used by the customer flow. The standalone checkout URL can include the documented order placeholders when payment is handled by an external page.

Advertiser link target and URL cloaking affect published placement links. With URL cloaking enabled, grid clicks go directly to the advertiser URL. With it disabled, clicks pass through the Million Dollar Script redirect endpoint first so compatible click handling and analytics can run.

**Enable Individual Advertiser Pages** publishes a WordPress page only for active placements on active grids after the connected order is paid. It is disabled by default. Draft, pending, unpaid, cancelled, expired, and archived records do not have a public page. The page URL base defaults to `mds-pixel` for MDS 2 continuity, while new slugs default to `%placement_id%` so account names are never exposed. Supported slug tokens are `%placement_id%`, `%pixel_id%`, `%order_id%`, `%grid%`, `%title%`, and `%text%`; the old `%username%` and `%display_name%` tokens resolve to an empty value in MDS 3.0.

Changing the base retains the previous 20 bases for exact 301 redirects. Changing the slug pattern does not immediately rewrite live URLs. Use **Preview slug migration**, confirm the permanent change, and apply the bounded migration batches. Each changed post keeps its exact previous slug for WordPress old-slug redirects. **Synchronize advertiser pages** repairs missing proxy pages and continues large runs through bounded WordPress cron batches.

**Exclude Advertiser Pages From Search** adds `noindex`, removes the post type from WordPress search and XML sitemaps, and leaves direct links available. Popup full-page links can be enabled independently, relabeled, and opened in the same or a new tab.

Managed proxy posts update their WordPress modified time and clear the post cache whenever a placement changes. Making a placement non-public changes its proxy to a draft and returns a non-cacheable 404 for stale direct requests. Purge any independent CDN or full-page cache after changing public visibility if that cache does not listen to WordPress post transitions.

## Display And Interaction

These settings control the default public surfaces, text, buttons, grid selection rules, statistics units, optional view controls, and occupied-block popups.

- **Multi-block Selection** allows more than one block in an order.
- **Selection Shape Rule** requires adjacent blocks, a complete rectangle, or permits unrestricted selections.
- **Popup Interaction Method** opens advertiser details on hover or click.
- **Max Popup Size** limits the popup container; **Max Popup Image Size** limits artwork inside it.
- **Show Grid View Controls** is disabled by default because grids open fitted to the complete board.

**Popup Layout Template** controls only the markup rendered when a visitor opens an occupied-block popup. The `%image%`, `%alt_text%`, `%url%`, `%text%`, `%advertiser_page_url%`, and `%advertiser_page_link%` placeholders decide which saved values and actions appear in that layout; adding or removing a placeholder does not add, hide, require, erase, or otherwise change a customer input field. Leave the template blank to use the built-in accessible layout. If a custom template produces no displayable content for a placement, Million Dollar Script falls back to that built-in layout.

Per-grid statistics visibility and block-level overrides can take priority over the shared display default.

## Orders And Uploads

Configure guest ordering, reservation cleanup, placement field requirements, rich popup text, and upload dimensions here.

**Advertiser URL Field** and **Popup Text Field** each support three states:

- **Required** shows the field and rejects a placement when it is blank.
- **Optional** shows the field and accepts a blank value.
- **Hidden** removes the field from customer order, upload, and manage forms. Existing saved placement values remain stored and available to popup layouts, migrations, and administrative workflows.

The same rules apply to browser submissions, customer account edits, and placement creation through the REST API. **Use Rich Text Popup Field** adds a small formatting toolbar while continuing to save only paragraphs, line breaks, bold, and italic formatting. Hiding Popup Text also hides that editor without deleting previously saved text.

**Auto-complete Manual Payments** is disabled by default. Enable it only when unpaid standalone submissions are intentionally allowed to become active without an administrator or payment provider confirming payment.

Order retention values use minutes. A value of `0` disables that cleanup transition; `-1` applies it immediately. Upload width and height values of `0` add no Million Dollar Script limit, although WordPress, PHP, and the hosting provider can still impose upload limits.

## Order Emails

Order Emails control customer and administrator notices for payment requested, paid, renewal paid, expired, denied, published, and renewal reminder events. Message fields use the WordPress visual editor and support the placeholders listed beside each template. See **Order Emails** in Documentation for the full event and placeholder reference.

Messages are sent through `wp_mail()`. Use a WordPress mail delivery or logging plugin when the site needs SMTP authentication, retries, or delivery records.

## System

System settings include the extension server URL, extension portal account preference, anonymous version analytics, uninstall cleanup, update channel, diagnostic logging, language updates, and slug transliteration.

The extension server defaults to `https://milliondollarscript.com`. Use a local URL only in a development environment. Main is the normal update channel; alpha and beta channels can contain unfinished builds.

Leave **Delete Data On Uninstall** disabled unless uninstalling should permanently remove Million Dollar Script tables and core-owned options. It does not remove Million Dollar Script 2 tables or options.

## Rendering

Core shows a rendering prompt when no rendering extension is active. ImageGrid connection details, keys, automatic rendering, plan information, limits, quota warnings, and remote delivery controls belong to the ImageGrid extension and appear only while it is active.

## Import And Export

The **Import / Export** tab exports a versioned JSON settings file and previews changes before import. Import validates field types and creates a backup of the current settings before applying accepted values. Review the preview carefully when moving settings between sites because page URLs, payment providers, extension availability, and currencies can differ.

## Upgrade Compatibility

The **Upgrade Compatibility** view lists retained Million Dollar Script 2 values that remain available for migration, import/export, or custom code but do not control the current runtime. New integrations should use current settings, repositories, blocks, shortcodes, REST endpoints, and documented hooks.

After changing settings, test a logged-out public grid, a reservation, upload/manage access, the configured checkout path, and the expected email events before sending production traffic.
