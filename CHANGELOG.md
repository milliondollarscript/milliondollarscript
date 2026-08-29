## 3.0.0-alpha.3 - 2026-08-29

### Chore

- regenerate language template for current code

### Fixed

- close unterminated branch in demo seed fixture
- anchor multi-block first click to the clicked cell with hover preview

## 3.0.0-alpha.2 - 2026-08-29

### Build

- adopt git-cliff release-notes pipeline from MDS 2.0

### Fixed

- throttle public write AJAX per IP
- auto-reload once when the page nonce expires on grid load

### Added

- move selection-size control to the actions row
- add selection-size control for multi-block ordering
- leave modified MDS2 pages unchanged with replace/create-new opt-ins
- manage list pagination and theme-aware panel headings
- customer pixel management with per-pixel details and owner-scoped manage links

### Other

- Fix mds3-setup slowness: fail-fast extension-server docs + catalog when server is down
- Populate the default block navigation with the MDS starter menu during setup
- Stop auto-creating the Main Grid grid and page on plugin activation
- Consolidate MDS2 migration entry points into one clear path
- Fix MDS2 migration status mapping so confirmed orders stay live

# Changelog

## 3.0.0-alpha.1 - 2026-08-21

- Declared compatibility with WordPress 7.1 after activation, REST, starter-site, grid-order, and WooCommerce checkout validation in the Playground 3.1.50 runtime.
- Extended the existing anonymous core update telemetry with a versioned, bounded snapshot of official extension versions and active states, with opt-out enforcement and no arbitrary plugin inventory.
- Declared compatibility with WordPress 7.0.4 after focused activation, REST, starter-site, media, grid-order, and WooCommerce checkout validation for the narrowly scoped core security release.
- Added a stable extension support facade for resolving the configured extension-server URL without coupling extensions to internal settings or release-profile constants.
- Added stable cursor pagination for the default Orders admin flow and opt-in cursor pagination for customer-order API consumers, while preserving legacy numbered-page compatibility.
- Normalized order expiration timestamps into an indexed column with a bounded, cron-driven existing-site backfill and an exact compatibility query until backfill completion.
- Combined and briefly cached order overview aggregations, with write invalidation and expiry so a persistent object cache is never required for correctness.
- Expanded deterministic high-volume coverage for real order repository, overview, customer-order, expiration, cursor, and cache-hit paths through 500,000 orders.


- Added a package-scoped private pre-alpha profile that connects invited test builds to the development catalog, package, update, account, and licensed-document services without changing ordinary release defaults.
- Added a persistent WordPress admin warning for development-connected private pre-alpha builds and forced those builds to use the alpha core update channel.
- Kept active extensions active after direct catalog updates while still reporting dependency or activation failures safely.


- Rebuilt Million Dollar Script as a modular WordPress plugin with a clean runtime under the `MDS3\` namespace, compatibility constants for legacy extension checks, and a safer API-first extension surface.
- Added Million Dollar Script 2 migration dry runs and imports for grids, pages, orders, packages, price rules, inventory, media originals, settings, custom ad post meta, and Fields values.
- Improved legacy media migration for WAMP/Windows paths that use backslashes or absolute `wp-content/uploads` paths.
- Added standalone checkout and WooCommerce checkout extension compatibility, including guest billing email preservation, existing Woo order reuse checks, customer manage links, and precise failed/refunded/cancelled payment synchronization.
- Added explicit guest-order handling in the frontend grid flow: guest checkout collects a customer email, disabled guest ordering shows a sign-in action, and server-side reservations enforce the setting.
- Added Million Dollar Script 2-compatible order timing cleanup for reserved, pending-payment, expired, cancelled, failed, refunded, and denied orders, including retained audit records, paid-term expiry, renewal checkout, and automatic inventory release after the renewal window.
- Added Million Dollar Script 2-compatible order expiration emails, renewal reminder settings, non-duplicated cleanup notices, and migration of legacy paid-order term metadata.
- Made order expiration, renewal reminder, and order-list queries compatible with WordPress's SQLite database layer as well as MySQL, including Playground database detection and ordered summary aggregation without logged fallback-query errors.
- Improved setup with a final completion action, direct WooCommerce Checkout installation and activation, provider/scroll/focus continuity across dependency reloads, accessible dismissible result toasts, clear selected-capability confirmation, default payment routing, live gateway/page/HTTPS readiness, and an extension-owned review screen that remains accessible before setup is complete.
- Added an explicit optional starter-site setup choice that safely prepares editable Blog, Contact, and About pages plus classic or block-theme navigation, while preserving existing content, front-page choices, and menu assignments.
- Added a two-level uninstall cleanup policy: the global deletion setting remains the parent control, while a collapsed extension list lets administrators include or exclude each registered extension; unresolved policy state always preserves data.
- Prevented grid viewers from capturing wheel scrolling until deliberately focused, added an accessible interaction cue and Escape release, and allowed placement popups to escape clipped grid containers.
- Added uploaded placement images to grid popups, including `%image%` replacement for custom popover HTML and respect for the popup max image size setting.
- Enforced migrated max upload width and height settings before customer artwork is stored, while preserving WordPress original-image handling for accepted uploads.
- Added ImageGrid extension hooks for quota preflight, render manifest submission, remote tile metadata, tile proxying, deferred patch rendering, and local fallback.
- Hardened authenticated remote tile proxying so public hosts use WordPress safe HTTP validation and redirects cannot forward service credentials to another origin.
- Added runtime support for migrated packages, price zones, selection shape rules, unavailable regions, max-order limits, and order-specific manage/thank-you pages.
- Added REST API keys, scoped API discovery, public reservation checkout payloads, and extension metadata/dependency surfaces.
- Implemented fail-closed, extension-owned `service_signature` verifier registration with a versioned HMAC request envelope, privacy-safe authorization audits, and OpenAPI metadata.
- Added provider-neutral recurring-payment adapters, commerce-source lifecycle contracts, and explicit subscription-plan selection while preserving one-time checkout as the default.
- Added accountless private tester access for approved extension packages and online documentation, with scoped entitlements, site limits, installation binding, development-site policy, expiry, and immediate revocation.
- Added Complete Extension Pack discovery and licensing, protected member downloads and documentation, individual-over-pack-over-tester access precedence, and a stable extension licensing facade.
- Normalized extension endpoint manifests before policy and OpenAPI discovery, rejecting nested, duplicate, incomplete, out-of-namespace, or unknown-security entries.
- Improved admin and frontend UX across setup, settings, extensions, grids, orders, responsive mobile views, and accessibility-focused interactions.
- Kept the complete MDS admin canvas, headings, descriptions, tabs, and extension notices legible and thematically consistent in forced and system dark modes.
- Established the selected MDS admin theme on the opening HTML element so dark and system-dark screens paint the correct canvas before the body is parsed.
- Applied dark form surfaces and native control icons consistently to date, datetime, email, search, telephone, and time inputs used by core and extension admin pages.
