# Changelog

## 2.6.51 - 2026-02-09
## 2.6.51


### Chore

- bump version to 2.6.51 and update tested up to 6.9.1


## 2.6.48 - 2026-02-09
## 2.6.48-alpha.1


### Fixed

- audit remediation P0-P1 critical and security fixes


## 2.6.47-alpha.1


### Fixed

- add json_decode error handling, remove @ini_set suppression, replace deprecated current_time('timestamp')


## 2.6.46-alpha.1


### Fixed

- sanitize $_SERVER values, remove addslashes/unlink, add ini_set checks


## 2.6.44-alpha.1


### Fixed

- audit legacy admin files — XSS, SQL injection, info disclosure, perf


## 2.6.43-alpha.1


### Fixed

- correct grid placement offset and skip adjacency check in simple mode

- compatibility page — version display, script loading, button UI

- persist license-claimed notice after post-Stripe redirect

- use generic translated message for nonce failures in manage-grids

- security hardening, debug cleanup, and style polish


## 2.6.38-alpha.1


### Fixed

- remove duplicate 2.6.37-alpha.1 entries and update git-cliff config


## 2.6.37-alpha.1


### Fixed

- improve error messaging when extension server is unavailable


## 2.6.36-alpha.1


### Added

- add optional anonymous version tracking with user control


### Chore

- Update ESLint configuration with single quotes and `wp` global, re-minify `mds.min.

- finalize 2.x updates (composer keywords, linting, gitignore)


## 2.6.48-alpha.1 - 2026-02-09



### Fixed

- audit remediation P0-P1 critical and security fixes


## 2.6.47-alpha.1 - 2026-02-08



### Fixed

- add json_decode error handling, remove @ini_set suppression, replace deprecated current_time('timestamp')


## 2.6.46-alpha.1 - 2026-02-08



### Fixed

- sanitize $_SERVER values, remove addslashes/unlink, add ini_set checks


## 2.6.44-alpha.1 - 2026-02-08


### Security

- fix reflected XSS in orders search form inputs and URL query params
- fix stored XSS in banner name dropdowns across 5 admin files
- fix stored XSS in unescaped banner data outputs (packages, price-zones)
- fix stored XSS via unescaped price in inline JS confirm dialog
- fix unescaped $BID in hidden form fields (backgrounds, confirm_order)
- harden check.php SQL queries with $wpdb->prepare()
- replace die() with wp_die() to prevent DB info disclosure (map-of-orders)

### Fixed

- fix date selector days 25/26 appearing out of order in orders admin page

### Changed

- pre-fetch banners and pixel positions to eliminate N+1 queries in orders loop
- remove duplicate banner query in packages admin page
- remove dead mouseover_js.inc.php (replaced by Tippy.js)
- remove legacy IE5/IE6 ActiveXObject code
- remove @ error suppression on ini_set() calls
- wrap global functions in function_exists() guards


## 2.6.43-alpha.1


### Fixed

- correct grid placement offset and skip adjacency check in simple mode

- compatibility page — version display, script loading, button UI

- persist license-claimed notice after post-Stripe redirect

- use generic translated message for nonce failures in manage-grids

- security hardening, debug cleanup, and style polish


## 2.6.38-alpha.1


### Fixed

- remove duplicate 2.6.37-alpha.1 entries and update git-cliff config


## 2.6.37-alpha.1


### Fixed

- improve error messaging when extension server is unavailable


## 2.6.36-alpha.1


### Added

- add optional anonymous version tracking with user control


### Chore

- Update ESLint configuration with single quotes and `wp` global, re-minify `mds.min.

- finalize 2.x updates (composer keywords, linting, gitignore)


## 2.6.32


### Fixed

- harden metadata parsing and admin image access


## 2.6.30


### CI

- handle workflow_dispatch release lookup

- fix changelog regex escaping

- make changelog parser more tolerant


### Documentation

- update changelog for 2.6.29


### Fixed

- restrict pointer image endpoint access


## 2.6.29


### CI

- checkout release tag before extracting changelog


### Chore

- bump version to 2.6.29

- pin working tree to 2.6.28 for 2.6.28 release

- keep working tree at 2.6.28


## 2.6.28


### Chore

- prepare release 2.6.28 with changelog in package


## 2.6.27


### Chore

- align version to 2.6.26


## 2.6.26


### Chore

- re-release 2.6.25 to include changelog in package


## 2.6.24


### Changed

- improve tippy instance management for multiple tooltips

- replace internal changelog page with external link

- redesign update architecture with direct HTTP communication


### Chore

- update composer autoload for new classes and version upgrades


### Documentation

- convert plain text URLs to markdown links in readme


### Fixed

- ensure links always have valid href


## 2.6.15


### Fixed

- fix GitHub release notes workflow and restore changelog


## 2.6.13


### Added

- refactor menu system with improved mobile navigation and script consolidation

- CHANGELOG.md now only will update for new releases to prevent duplication.

- enhance menu system with mobile support and styling improvements

- update extension server URLs to production and implement new update channel system


### Changed

- replace hardcoded dashboard menu with registry system


### Fixed

- resolve block pointer offset on window resize in simple pixel selection

- improve session expiration UX and URL cleanup

- fix update checker endpoint and purchase flow for Docker networking

- ensure ImageMap initialization after image load


## 2.6.4


### Chore

- update extension server URL defaults to production


## 2.6.2


### Added

- update plan action strings with parameter placeholders

- separate products from extensions - add dedicated namespaces

- add client portal shortcut

- reorganize admin menu structure and improve navigation UX

- add path validation and sanitization to Filesystem class

- add toggle for portal account syncing

- add wordpress parity snapshot capture

- align defaults with go extension server

- unify extensions grid with inline updates

- render sale pricing badge with metadata fallback

- render cadence tabs on extensions page

- enforce pixel permalink length filters

- show plan details and allow license removal

- rebuild catalog UI with pricing and license data

- refresh advertiser list layout

- add resilient grid loading feedback

- tighten order flow and auto-advance uploads

- replace Register button with translated link under form

- add configurable WooCommerce login redirect option

- improve Manage modal clarity; switch activation to /api/public/activate; add inline activation in Available; indicate current plan in UI

- inline license column with encryption-at-rest, eye toggle, and activation\n\n- Add AES-256-GCM compact encryption helper for license keys\n- Add License column in Available Extensions with eye toggle and Activate button\n- New AJAX: mds_available_activate_license, mds_get_license_plaintext\n- Decrypt key before premium download; store pending validations when server unreachable\n- Upload ZIP fallback link when server is unreachable

- auto-claim license after Stripe purchase; add claim endpoint and purchase metadata\n\n- Add mds_claim_license AJAX handler to claim license from server and persist locally\n- Pass claimToken + siteId to checkout URL; include extSlug + claimToken in successUrl for post-return claim\n- Localize purchase state, site_id, and public server URL to JS\n- Auto-claim in admin-extensions.js on purchase success and refresh UI\n- Improve server probe in install flow: fallback to /health and /api/extensions when /api/public/ping is missing

- auto-set Account/Register/Login to WC My Account when enabling integration

- configurable MDS Pixels base and slug pattern; add migration tool with 301 redirects and legacy base support; correct search behavior

- add per-grid blocks layering toggle with overlay images and per-grid light/dark background colors; optimize opacity blending

- rectangle-only validation, distinct error codes, and immediate UI updates

- add purchase flow and per‑extension licensing

- add enhanced notice system and premium gating

- add banner name and grid ID columns to ApprovePixels page

- add max_blocks resizing logic

- enhance image upload and processing functionality

- Improve page validation and UI

- add helper to normalize Imagine save options

- Introduce MDS hooks system for extensibility

- Enhance theme management and layout consistency across interfaces

- Enhance extensibility of ads management and user management pages

- Enhance color management functionality and improve UI styles

- Enhance confirmLink functionality and improve order cancellation process

- Add page management and creation system.

- Complete page management system implementation

- WIP - page management system

- Add enhanced styles and functionality for the extensions page

- Enhance wp-admin login page styling with dark mode support and custom logo integration

- Refactor CSS variables and button styles for improved theme integration

- Add foundation and component CSS variables for dark and light themes

- Add CSS style overrides for WooCommerce buttons and price elements

- Enhance theme customization with new color options for light and dark modes

- Add grid image generation and switching for dark mode

- Implement dark mode feature with theme switching and customizable colors

- Add filter for extending area data values in map rendering

- Added mds_popup_custom_replacements filter for popup template

- Consolidate extension modifications and JS updates

- update Wizard header with dynamic fire effect and reposition admin notices

- add admin page info and hosting promo to setup steps

- Add invert-pixels and stats-display-mode options to Grid settings

- enhance migration process for config values in 2.5.12.0 upgrade

- migrate remaining settings from config_form.php

- Persist Click Report view type and fix styling

- Improve admin block UX with AJAX spinner and error handling


### Build

- refresh extension assets bundle

- sync extension ui assets


### Changed

- remove unused FormFields import

- remove manage subscription controls

- centralize pixel selection persistence

- Enhance validation logic for migrated pages and improve shortcode detection

- Migrate database interactions to $wpdb for improved security and performance

- Update form submission handling to keep submit button disabled during redirect

- Simplify pixel processing logic and improve error handling

- Simplify file handling in upgrade script

- Replace direct database queries on old config table.

- NFS page

- Consolidate mouse handling to native listeners

- Replace direct SQL config table access with WordPress options for LAST_EXPIRE_RUN

- Replace config table usage with WordPress options for database version tracking

- Adjust settings structure and labels in Options class

- update Forms and Orders classes to use Options instead of Config

- update system classes for Options integration

- remove deprecated config_form.php file

- remove deprecated main-config.php file

- remove legacy config code from admin files

- deprecate old Config class as part of Options migration

- move email settings from Options to Emails class


### Chore

- merge dev into extensions

- sync workspace state

- sync workspace session

- sync session context

- sync workspace state

- session checkpoint

- update translation template

- update POT

- sync pot file

- merge dev into extensions

- trim noisy debug output

- sync dev with origin

- merge dev into extensions and resolve conflicts

- merge dev into extensions

- update language files

- Update readme.txt for new Selection Adjacency Mode option

- remove tracked .githooks; use local .git/hooks instead

- Update version numbers to 2.5.13.0 in plugin files

- Enhance page management functionality

- Remove debug script for MDS metadata tables (not used anywhere)

- Update version numbers to 2.5.12.130

- Auto-update version to 2.5.12.86

- Update version to 2.5.12.85 after page management completion

- Update vendor libs

- Update version numbers to 2.5.12.114 in plugin and readme files

- Update version numbers to 2.5.12.110 in plugin and readme files

- Update version numbers to 2.5.12.105 in plugin and readme files

- Bump version numbers to 2.5.12.101 in plugin and readme files

- Update development status in composer files

- Update vendor libs.

- Update vendor libs.

- Update version numbers (bypass hooks)

- Update version numbers post-hook run

- Update version numbers after JS re-minify

- Enhance extension management

- Update language file


### Documentation

- add products integration documentation and session logs

- update tested up to version

- document MDS Pixels permalinks and migration workflow

- enhance extensions UI and clean up submenu handling

- switch tooltip colors to CSS vars


### Fixed

- harden tooltip data parsing

- refresh grid map include after generation

- improve plan features HTML rendering and service handling

- Add Manage Pages and Create Pages to Admin menu in Dashboard.

- hide non-extension submenus without blocking access

- Add important to some styles for the mds-ext-active button.

- enforce image MIME allowlist and header casing; remove @unserialize; sanitize grid dimensions

- harden error rendering; add admin-only handler; safe unserialize for block_info

- sanitize popup text listings

- sanitize popup text listings

- default to CAD currency

- preserve stripe amount metadata in catalog

- persist wordpress update transient

- restore default update snapshot handler

- normalise update downloads for proxied hosts

- restore plugin update flow parity

- block update checks without license

- show friendly plan labels on extensions page

- prevent empty pricing cards without amounts

- show latest version check as success notice

- suppress legacy plan entries in extensions UI

- show renewal timezone and refresh license status

- honor site license during premium installs

- show install button after purchasing extension

- honour sale mapping when sale amount matches default

- resync license state and repair removal handler

- restore submenu styling and license actions

- toggle popup rich text safely on display

- ensure pixel post titles follow popup text

- show all grids on advertiser list

- prevent fatal when order lookup fails

- normalize subscription plan detection

- encrypt claimed license storage

- Indentation

- preserve uploaded grid images

- restore mds pixel single layout

- clarify manual auto-complete handling

- keep pixel search results consistent without template

- restore adjacent block validation

- guard missing update checker dependencies

- hide trashed pages in manage view

- prevent WooCommerce redirect on MDS endpoints

- resolve PHP parse error by correcting type hints in checkout handlers

- restore Not For Sale admin page

- stabilize advanced loader handling

- enforce upload limits for pixel images

- align spinner handling across grid pages

- prevent SQL syntax error during block selection

- Clear Orders now verifies nonce on POST, reliably deletes associated WC orders, and shows notices below the MDS menu only

- resolve PHP parse error by correcting type hints in checkout handlers

- persist MDS-WC mapping during checkout so Stripe auto-complete adds order note and completes MDS order

- show cancelled blocks as reserved and block selection; add changelog entry

- use canvas overlay with integer partitions to remove seams; immediate loader on mousedown; batch client-side rendering; restore pointer and slider sync; reduce flicker

- use public activate/deactivate endpoints; honor dev sslverify; improve connectivity for Premium Test flows

- resolve PHP parse error (extra PHP open tag) and complete UI/UX changes (purchased license UI, manage modal, renewal notices, decryption before validation/install)

- define  before use and correct data-plugin-file attribute quoting\n\n- Initialize  per row before rendering attributes\n- Fix HTML attribute quoting for data-plugin-file to avoid escaping issues

- retry auto-claim for webhook delay; trust row licensed state when no key

- correct license checks by slug; pass license key on install; stable install gating\n\n- Use extension slug (not server id) to check local license and render buttons\n- Add data-is-licensed to rows; JS gating trusts row state if no global key\n- Fetch license key from DB and send as x-license-key for downloads\n- Include extensionSlug in checkout URL to help server map purchases\n- Keep auto-claim flow in place (claimToken/siteId)

- relocate any header-injected notices into below-header anchor via JS

- render server-side notices inside below-header anchor and adjust JS fallback\n\n- Wrap purchase/cancel/fallback notices inside #mds-extensions-notices\n- JS fallback now inserts notices before Available Extensions section

- resolve extension server base using container alias and host fallbacks

- anchor notices below header and render JS notices in-page

- prevent duplicate Manage Pixels and fresh-install migration notice; defer auto-create until wizard-created pages; improve uninstall cleanup; shorten readme lines

- prevent link-color bleed by scoping styles to MDS container and removing body-level mds-container\n\n- Scope dynamic CSS variables and rules to .mds-container (and login)\n- Stop adding mds-container to <body> by default; add legacy opt-in filter\n  (mds_enable_legacy_body_classes)\n- Load dynamic CSS later (priority 100) to reliably win tie-specificity\n\nChangelog: added user-friendly note under 2.5.13 about the change and how to opt-in to legacy behavior

- use ARRAY_A in get_results for banners query to fix grid selection in advanced mode

- fix mass complete and cancel order handling

- handle zero banner ID and enable All Grids

- Fix deleting grids causing an error. Improve WooCommerce integration safety and function calls.

- Fix approve pixels not working.

- fix global $wpdb reference preventing grid modifications

- correct order completion flow and email fetch, update language file

- replace mysqli with $wpdb in admin and orders

- handle upload redirect and JS form submission

- harden order selection and reset handling

- correct light mode tooltip background defaults

- update shortcode defaults and titles

- Enhance page creator and error resolution functionality

- Update SQL query to exclude 'cancelled' status in block retrieval

- Update order status checks to include 'cancelled' state

- Resolve order flow issues and prevent duplicate pixel posts

- Prevent double encoding of HTML entities in translation functions

- Update max image size default value and enhance grid image responsiveness on some themes

- Update max image size default value and enhance grid image responsiveness on some themes

- Update max image size default value and enhance grid image responsiveness

- Use namespace for OPTION_NAME_WIZARD_COMPLETE

- File upload in simple pixel selection method.

- Allow selecting first block.

- Update mouseover option retrieval to use Options class

- Removed duplicate option for Order Published email

- Corrected retrieval of max-popup-size option to use the Options class directly

- Removed duplicate option for Order Published email

- Corrected retrieval of max-popup-size option to use the Options class directly

- Update mouseover option retrieval to use Options class

- Fixed file upload processing for order-pixels

- Allow selecting first block.

- Stop deleting data on deactivation.

- Re-minify JS files with updated Bun command

- Stop deleting data on deactivation.

- Update JS minification on extensions branch

- WooCommerce and permission conditional logic checks on Carbon Fields

- Approve pixels.

- add missing file header to Logs.php

- Add version.php back to prevent an update error because WP seems to run the old code still after updating for some reason.

- More dashboard spark fixes that were missed.

- Fixed auto-resizing

- Fixed Complete Order button on Confirm screen when price per block is 0 or user is privileged to go to the Payment page.

- Dashboard spark effects

- ensure images properly fill block space with auto-resize enabled

- Prevent link navigation when tippy tooltip is triggered by click

- Prevent Carbon Fields fatal error during activation

- update log-enable option field name for consistency

- update checkbox option handling in core files and Form classes

- update checkbox option handling in Payment and WooCommerce classes

- update checkbox option handling in Pages and Web classes

- update checkbox option handling in System classes

- update log-enable option handling for proper toggle state

- update admin logs JavaScript for improved debugging

- implement correct default values for Options class settings


### Other

- soften cancel auto-renew controls

- refine update status display

- enhance update status readability

- refresh check updates button styling

- resolve conflicts preferring dev updates for core files and minified assets; remove vendor/imagine composer.json per dev; keep extensions features intact

- refine selection size slider styling

- Simplify CSS styles for body and headings in mds.css

- Enhance login form button and link styles for improved user experience

- Refine button and link styles for improved UI consistency

- Update link colors for improved accessibility

- Adjust login form padding and max-width for improved layout

- Update Tippy tooltip dimensions and styling

- Add hover effects for success and confirmation buttons

- Update textarea color to use !important for consistency in styling

- Enhance slider styling for selection size with custom appearance

- Set line-height on grid-inner element to 1 to ensure proper grid height.

- Resolve merge conflicts from dev branch stash

- Use global 'auto-resize' option for conditional image sizing in output_grid and get_order_image

- Implement conditional image resizing based on 'image_auto_resize' banner setting

- Prevent basename deprecation and correct Config::get usage in Cron

- Rearrange styles to fix the default font color of links affecting certain buttons.

- Change Config::get return type to mixed

- Update version, translation template, and changelog

- Consistent option handling and bugfixes across core and system classes

- Normalize Options::get_option default values and comparison for booleans

- Adapt Logs and LanguageScanner to Filesystem changes

- Correct boolean conversion in upgrade logic (_2_5_12_0.php) to return 'no' for false values

- Update click-reports.php for improved click reporting and admin UI consistency

- Update WooCommerce functions and options for improved integration and settings handling

- Update Users class for improved user management and reliability

- Update System classes (Bootstrap, Filesystem, Functions, Logs, Utility) for improved core functionality and maintainability

- Update Logs and Wizard pages for improved logging and setup flow

- Update Admin, Config, and Options classes for improved settings management and data consistency

- Improve selection and pixel UI; add Styles.php for dynamic CSS; update select.js and related PHP for enhanced block selection and UI integration

- Update plugin to v2.5.12.47, fix Stable tag updater, and add new assets/js/css

- Improve readme update logic in version script

- Correct Stable tag format in readme.txt

- Update plugin metadata and documentation in main file and readme

- Remove obsolete/deprecated files (wizard assets, robots.txt, LICENSE.txt)

- Update Core user/admin, grids, pointer, backgrounds, output, and JS assets for improved logic and bugfixes

- Update core classes (Admin, Orders, Forms, Payment, System, WooCommerce, Utility, Debug) for improved maintainability and bug fixes

- Upgrade database migration logic for 2.5.12.0, migrate unmapped configs to Logs class

- Update Composer autoload and vendor files for dependency changes

- Update grid dimensions and price in DB from wizard

- Add Style options for customizing admin colors

- update color scheme and remove step animation

- Centralize page creation logic in Utility::create_mds_page

- Add animated ember effects to wizard fire cursor

- Align wizard page creation with main options page logic

- Correct Logs page slug and update admin menus

- Centralize and improve plugin build date logic

- Use readme.txt "Last updated" as fallback for build date

- Replace BUILD_DATE option with last_updated from PUC

- Resolved AJAX handler registration issue in log viewer

- Implement AJAX log viewer with live updates

- Migrate logging from Config to Options API and WP_DEBUG

- Add daily cron task to clean up old log files

- Resolved database upgrade issues from 2.3.5 by reordering installation steps

- Properly resolve 'headers already sent' errors in admin pages using output buffering and proper form handling

- Update minified JavaScript with tooltip fixes for multiple grids

- Tooltip AJAX content not loading on second grid by improving data attribute handling and removing global tippy instance

- Update admin notice in Utility class to use Notices class for consistency

- Resolve 'headers already sent' errors in process-pixels.php and clear-orders.php by proper handling of form submissions through admin-post.php and using the Notices class for admin messages

- Add dedicated CSS fixes to address tooltip black bar rendering artifacts in different browsers

- Address black bar visual artifact in tooltips by updating tooltip positioning and display options

- Additional tooltip improvements - remove visual artifacts and ensure tooltips work on all grids

- Grid tooltips and multiple grids issues - Prevent immediate redirect on first click and use unique map IDs for each grid

- Auto-approved images not being published automatically when grid has auto-approve and auto-publish enabled

- Improve reliability and handling of pixel selection and upload forms. Hidden fields for selected pixels are now consistently managed in both JS and PHP. UI and workflow are more robust for users.

- Commit auto-updated version and metadata files after previous changes. All uncommitted files are now staged and committed as per workflow.

- Improved Manage Pixels info layout for better responsiveness, equal-height columns, and scalable images. Removed duplicate CSS and ensured mobile stacking is full-width.

- Add inline image map scaling for proper area alignment on Manage Pixels page

- Initialize responsive image map on Manage Pixels page

- Resolve nonce error on Packages grid select

- Ensure package settings save correctly and remove debug logs

- Improve image upload handling and dimension validation

- Resolve fatal error and incorrect dimensions on block type change

- Remove target blank from WC refund link

- Integrate WC refund link into Transaction Log

- Use Language class for WC refund translations

- Ignore URL BID parameter on Manage page when dropdown option is disabled

- Ensure Manage Pixels grid dropdown respects admin option

- Add grid selection dropdown to Manage Pixels page

- Add grid dropdown to Manage Pixels page

- Remove debug code and duplicate notice in Packages admin

- Resolve package saving issues and refactor Packages admin page

- Make uncovered blocks transparent when uploading smaller image

- Prevent crop errors for non-multiple image dimensions

- Corrected click reports form routing and reset logic. Ensured click reports display and reset as expected.

- Reorganized admin menu for clarity. Moved Transaction Log to Reports, removed redundant and nested items, grouped pixel actions, and improved navigation labels. See changelog for details.

- Update 'Approved Pixels' text labels for consistency

- Simplify approve-pixels page, remove URL/Title editing

- Improve Approve Pixels page UI/UX

- Use correct 'order_date' column in approve-pixels ORDER BY clause

- Use correct 'status' column name in approve-pixels queries

- Use 'ostatus' column name in approve-pixels queries

- Add error logging to approve-pixels order query

- Only filter by banner_id in approve-pixels if BID > 0

- Ensure $total_orders is int in approve-pixels to prevent TypeError

- Correct database column names in Utility and approve-pixels

- Implement grid_dropdown and display_pagination in Utility class

- Use Utility class methods in approve-pixels.php

- Standardize formatting and header in approve-pixels.php

- Clean up approve-pixels page and fix dynamic title

- Background image handling and admin form submission errors

- WC order status sync using post meta

- Restore reliable block extraction in upload_changed_pixels by cropping and pasting blocks, avoiding Imagine negative offset errors. Update readme to reflect this.

- Add background image opacity slider and JPG/GIF support

- Handle background uploads correctly and prevent Imagick fatal error

- Move Language and Transliteration settings to new Language tab

- Implement Cyrillic-to-Latin slug transliteration

- Correct URL generation on Manage Pixels grid click

- Enforce order locking by disabling pixel grid clicks

- Add user-friendly changelog entries for recent fixes

- Resolve AJAX URL construction and JSON parsing issues in block selection

- remove stray '+' prefixes from 2.5.10 entries

- append user-facing entries for 2.5.10

- add entry for GD resource annotations in output_grid

- precompute dims, direct GD imagecopy, refactored loops

- dynamic driver selection and remove obsolete Imagick comments


### Performance

- bulk transactional selection; reliable order updates


### Security

- Resolve admin grid selection redirect issue and security check failure on Packages page


