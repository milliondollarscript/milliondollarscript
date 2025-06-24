=== Million Dollar Script ===
Contributors: Ryan Rhode, Adam Malinowski, and the entire community.
Donate link: https://milliondollarscript.com
Tags: million dollar script,mds,pixels,advertising,pixel ads
Requires at least: 6.7
Tested up to: 6.8
Stable tag: 2.5.12.106
Requires PHP: 8.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Million Dollar Script enables you to sell pixel-based advertising space on your website for an interactive user experience.

== Description ==

Million Dollar Script enables you to sell pixel-based advertising space on your website for an interactive user experience.

Visit the [Million Dollar Script WordPress Plugin](https://milliondollarscript.com/million-dollar-script-wordpress-plugin/) page on the website for additional documentation.

== Changelog ==

= 2.5.12 =
* Feature: Major improvements to pixel selection and upload UI for a smoother, more intuitive experience. Block selection and grid interaction are now more reliable, especially with multiple grids on the same page.
* Feature: Added dynamic CSS generation for improved style customization.
* Fix: Moved all the Admin Settings into the Options page for more consistent and reliable configuration.
* Fix: Enhanced logging and setup wizard flow for better usability.
* Refactor: Core system classes updated for improved stability and maintainability.
* Fix: Improved user management reliability.
* Fix: Improved WooCommerce integration and settings handling.
* Fix: Click reports page updated for better admin reporting and UI consistency.
* Fix: Changed all the checkboxes in the Options to radio buttons.
* Fix: Fixed Complete Order button on Confirm screen when price per block is 0 or user is privileged to go to the Payment page.
* Fix: Fixed uploading a new pixel image that was smaller than the original didn't resize properly when auto-resize is on.
* Fix: Approve pixels.
* Fix: WooCommerce and permission conditional logic checks on Carbon Fields.
* Feature: Add mds_popup_custom_replacements filter for custom popup template replacements.
* Fix: Fixed Max Image Size option in popup template.
* Feature: Add mds_pixel_area_data_values filter for extensions to add additional data.
* Feature: Add dark mode/light mode selection option.
* Feature: Add dark mode/light mode grid image generation and switching.

= 2.5.11 =
* Add WooCommerce refund integration.
* Fix: Corrected PHP fatal error during AJAX block type updates in the editor.
* Fix: Improved image upload handling, error messages, and resolved dimension validation issues during pixel ordering and management.
* Fix: Improved responsiveness of the pixel grid on the Manage Pixels page for better interaction on smaller screens.
* Fix: Improved pixel selection accuracy, ensuring that the selection tool properly aligns with grid boundaries at all screen sizes.
* Fix: Enhanced mobile compatibility for block selection, ensuring proper functionality on touch devices and when zooming.
* Fix: Fixed issues with multi-block selections where some blocks were not being properly identified.
* Improved the Manage Pixels info layout: columns are now equal height, mobile stacking is full-width, and images can scale to any size for a cleaner, more responsive experience.
* Improved the pixel selection and upload experience for users, making the process more reliable and intuitive.
* Fix: Auto-approved images not being published automatically when grid has auto-approve and auto-publish enabled.
* Fix: Clicking blocks on grid doesn't popup tippy tooltip - blocks were redirecting immediately instead of showing tooltip first.
* Fix: When multiple grids are displayed on the same page, clicks on the second grid would trigger actions on the first grid.
* Fix: Removed visual artifact (black bar) that sometimes appeared when showing tooltips.
* Fix: Improved tooltip handling for multiple grids to ensure tooltips work consistently on all grids.
* Fix: Resolved 'headers already sent' errors in process-pixels.php and clear-orders.php by proper handling of form submissions through admin-post.php.
* Fix: Improved consistency of admin notices by using the Notices class throughout the plugin.
* Fix: Tooltip AJAX content not loading on second grid when multiple grids are displayed on the same page.
* Fix: Resolved database upgrade issues when upgrading from version 2.3.5, preventing errors related to missing columns.
* Fixed PHP 8.2 deprecation notice for dynamic property creation.
* Fixed issue where the "Delete" button on the Blocked IPs page could delete the wrong entry if the table wasn't sorted by ID.
* Improved CSS loading logic to prevent potential conflicts or redundant loading.
* Added a new daily cron task to automatically delete debug log files older than 30 days.
* Fix: Ensured the "Show Sold Pixels" filter works correctly on the user-facing Manage Pixels page.
* Fix: Corrected issue where updating pixel link/URL didn't reflect immediately on the grid.
* Fix: Resolved PHP warning related to array key in Shortcode class.
* Fix: Added check for `wp_doing_cron` before enqueuing assets.
* Fix: Removed deprecated `wp_filter_content_tags` usage.
* Fix: Ensured `order_id` is consistently available in the `mds_order_created` action hook.
* Chore: Added daily cron task to delete log files older than 30 days.
* Refactor: Updated logging system to use Options API and hardcoded filename, removing Config dependency.
* Feature: Added AJAX-powered controls to the Logs page for enabling/disabling logging and clearing the log without page reloads.
* Feature: Implemented live log updates (optional toggle) and log entry consolidation (showing latest timestamp and count) on the Logs page.
* Fix: Allow selecting first block.
* Fix: File upload in simple pixel selection method.

= 2.5.10 =
* Fix Payment page redirects and other redirects, also implement more AJAX messages as well as AJAX redirects.
* Fix block selection issues.
* More optimizations and fixes while ordering.
* Fix orders auto-completing when they shouldn't.
* Add mds_reset_order_progress filter.
* Fix hover popup interaction.
* Use WP date and time functions.
* Fix wrong order id being used in some cases.
* Fix reset order progress.
* Fix disapprove pixels not working.
* Fix pagination.
* Fix warning: Undefined array key "blocks".
* Fix when auto-approve is off it isn't unpublishing the order when a user edits it.
* Reorganized and simplified the admin menu: clearer section names, removed redundant links, grouped pixel actions together, and made navigation more intuitive.
* Fix Upload button in order details not working correctly.
* Fix grid currency setting.
* Use Currency Symbol option.
* Update Instructions in advanced mode to output a legend with the block images instead of text descriptions.
* Make the cancel button also clear the WC cart.
* Fix Order Published notifications were using Order Completed Renewal content.
* Fix an incompatibility when activating the Elementor plugin.
* Fix missing mail length processing.
* Fix confirmation when only the first block on the grid is selected.
* Fix cancelling an order doesn't remove its blocks.
* Fix order not loading properly if clicking the back button in the browser or visiting a previous page in the process.
* Improve order handling when orders time out or are removed while ordering.
* Fix MINUTES_CONFIRMED option not working correctly.
* Fix blocks not being disapproved automatically.
* Fix unable to edit block 0 clicking it on the Manage Pixels screen.
* Fix payment page message when WooCommerce integration is disabled.
* Fix confirm order redirect when confirm page is disabled.
* Make order pending when confirming with WooCommerce integration disabled.
* Fix PHP Fatal error: Uncaught Error: Cannot use object of type stdClass as array in ...\src\Core\include\functions.php:226
* Delete orders instead of expiring them for confirmed but not paid orders timeout.
* Improve time calculation for order expiration.
* Fix order renewal payment handling.
* Improve Approve Pixels admin page UI: Added Actions column, linked Order/Ad IDs, linked Username column (replaced Name/Email), adjusted styling for inputs, buttons, images, and block coordinates.
* Add order validation and custom error handling in WooCommerce.
* Fix blocks not always the right colour on the order pixels grid by stopping reserved blocks from pulling from orders table.
* Replace MDS Admin List page with link to MDS Pixels post type.
* Process pixels when disapproving when a user edits their order.
* Add more columns to MDS Pixels.
* Fix MDS Pixel URLs not registering due to post type not being registered first in upgrade operations. Also flush rewrite rules on deactivation.
* Fix PHP Warning: Undefined array key "renew" in payment.php
* Don't process the shortcode on admin pages.
* Prevent double-clicking on most buttons.
* Ensure only one order can be in progress at a time for a user.
* Add a new option to lock editing of orders once they are approved or completed. If this option is enabled in the settings, users will be prevented from modifying their orders post approval or completion.
* Add the ability for admins to deny orders on the Approve/Disapprove screens. Includes email notification and a new email option to configure it.
* Add paid order status.
* Add tabs to Email page.
* Add horizontal admin menu that works on mobile.
* Fix timeout when confirmed but not paid.
* Set orders as not in progress when expiring, cancelling and deleting.
* Fix order being marked private when expiring.
* Replace Order History and Publish pages with new Manage screen.
* Remove user menu.
* Remove options for DISPLAY_ORDER_HISTORY, users-history-page, users-publish-page, and users-home-page.
* Added mds_header_container and mds_footer_container filters for header and footer html.
* Fix clicking images in grid not editing the order.
* Fix temporary upgrade mechanism causing an error.
* Update vendor libraries.
* Update language file.
* Fix error with the get_order_id_from_ad_id function.
* Don't send the Order Confirmed email when the order is completed, only send the Order Completed email.
* When Create Pages button is clicked it will now automatically set the Page Layout to No Sidebar if using Divi theme.
* The Change Pixels upload will not return to the Manage Pixels page anymore.
* Approving a denied/pending order will notify the user.
* Make mass operations not send emails to prevent spam issues.
* Fix tooltip display on mobile when zoomed in.
* Remove some old unused code.
* Add paid orders to Orders Waiting page.
* Add support for shortcodes in the Popup Template option.
* Add mds-status-edited action that runs when a WooCommerce order status is manually edited if it contains the MDS product.
* Add mds_order_expiry filter for altering the expiration date shown in the MDS Pixels page.
* Fix expiration date displayed on MDS Pixels page to show Never if Days to Expire is set to 0 on the grid.
* Add a function to get the WC order id from the MDS order id to the Orders class.
* Add mds_woocommerce_product_price filter to adjust the price.
* Remove mds_my_account and mds_logout capabilities since they are no longer used.
* Add mds_shortcode action useful for adding custom MDS shortcode extensions.
* Add mds_block_types filter useful for modifying types on the MDS block.
* Open accordion on first item by default on the Manage Pixels page.
* Add mds_order_details action for adding custom order details inside the accordions on the Manage Pixels page.
* Fix packages.
* Fix Currency and Currency Symbol options not loading when WooCommerce isn't active.
* Add Order Pixels button to the Manage Pixels page.
* Change mds_modify_search_query filter to an action.
* Change mds_form_save filter to an action.
* Fix mds_field_output_before filter.
* Fix mds_field_output_after filter.
* Add mds_form_field_required filter.
* Add mds_form_field_display action.
* Fix mds_posts_search filter.
* Call FormFields::save function when fields are saved.
* Add check for MEMORY_LIMIT before using it.
* Add some missing options to 'Delete data on uninstall' deletion.
* Fix migration from 2.3.5.
* Fix privileged users.
* Add a 1-second delay to the Blocks input box on the Select Pixels screen before automatically snapping it to a squareable value.
* Fix erasing blocks only erasing the first block when size is larger than 1.
* Fix multiple grids not working properly in advanced pixel selection method.
* Remove Admin option "Output processed images to:".
* Allow title attributes on anchor tags and alt attributes on img tags in the sanitizer.
* Add null check for $order_row in confirm_order.php.
* Add null check for $package in make_selection.php and set price to 0 if it is null.
* Add null check to $banner_id in Orders.php and try to get it if it is null.
* Add filters mds_dest_select and mds_dest_order_pixels for customizing the first order process steps (will add more in the future).
* Remove user_id check on insert_ad_data function.
* Execute the mds_ajax_complete WP hook in JavaScript whenever an MDS AJAX request completes successfully.
* Do the mds_order_completed action when an order is completed.
* Fix Forms URL redirects when they already have parameters.
* Rewrite adjacent block selection in advanced pixel selection method.
* Fix NFS not working properly when "Show the NFS image on every NFS block" is selected.
* Fix MDS Pixel posts not being set as completed or published causing the order to not be shown automatically on the grid.
* Fix errors not outputting correctly at times.
* Remove Order again button.
* Use add_query_arg for URL redirect parameters.
* Set default dimensions on shortcode from the database if they weren't entered.
* Add a checkbox to the Clear Orders screen to clear associated WooCommerce orders too.
* Add Changelog page.
* Don't check NFS block size when set to Show the NFS image on every NFS block.
* Convert more database calls to use $wpdb.
* Fix order completion logic to properly handle manual completions and respect auto-approve settings.
* Fix headers sent warnings by clearing output buffers before redirects.
* Optimize upload_changed_pixels handling: use batched database updates.
* Optimize output grid rendering: precompute block dimensions, dynamic driver selection, and direct GD imagecopy.
* Fix issues selecting pixel blocks, particularly when WordPress permalinks are set to 'Plain'.
* Improved error messages shown when trying to select a block that is already reserved or sold.
* Fix clicking pixel blocks on the Manage Pixels page.
* Add option to transliterate Cyrillic titles to Latin for cleaner URL slugs.
* Reorganize plugin settings: add new 'Language' tab for related options.
* Fix fatal error in grid output when using Imagick caused by incorrect call to GD-specific function.
* Fix background image uploads/deletions in admin by handling them via the standard WordPress admin_post action hook.
* Improve compatibility with different server image processing setups.
* Improve reliability of background image uploads and deletions.
* Add opacity slider for grid background images.
* Allow JPG and GIF images (in addition to PNG) for grid backgrounds.
* Improve admin block UX: Add AJAX loading spinner to width/height fields and provide visual error feedback (tooltip/highlight) for invalid grid IDs.
* Fix WooCommerce order status not updating when MDS order is completed.
* Improve robustness of grid background image opacity handling when Imagick extension is not available.
* Fix: Corrected an issue where background image transparency (opacity) might not apply correctly.
* Fix: Resolved problem with the background image delete button not functioning.
* Fix: Addressed errors that could occur on some admin pages after submitting forms.
* Cleanup and fix Click Reports page.
* Fixed error during image upload on manage pixels page if auto-resize is off and image dimensions were not exact multiples of block size.
* Make blocks transparent if uploading a smaller image than the selected area during pixel management.
* Improved Packages admin page: Packages are now saved correctly and the page uses the standard WordPress admin table layout for easier management.
* Fix: Corrected admin grid selection redirect and security check issue on Packages page.
* Fix: Resolved issue where admin grid selection could fail security check and redirect incorrectly.
* Feat: Added an option to show a grid selection dropdown on the user-facing Manage Pixels page.
* Feat: Added grid selection dropdown to the Manage Pixels page (configurable in MDS Options).
* Fix: Resolve 'Link expired' error when changing the selected grid on the Packages admin page.

= 2.5.9 =
* Fix another issue that could cause plugin database migrations to not run.
* Add a "snapshot" branch to replace the "dev" branch. The dev branch will now be used for ongoing development while the new snapshot branch will be used as the dev branch was before. For lightly tested, periodic snapshots of the dev branch when new features or fixes are ready for wider testing.
* Update vendor libraries.
* Fix PHP Warning:  Undefined array key "blocks" in src/Classes/Functions.php on line 168

= 2.5.8 =
* Fix JSON output in error message when selecting a block that is already reserved.
* Fix plugin database migrations not running.
* Add option to exclude MDS Pixel pages from search results.
* Fix mail string sizes being too large to fit in the database.

= 2.5.7 =
* Add an option to the WooCommerce tab to Auto-complete Orders.
* Refactor some WooCommerce related functions to their own WooCommerceFunctions class.
* Make checkout page redirect without using JS.
* Add more validation to confirm order page. Includes a mds-confirm-order-validation filter.
* Add user verification to order retrieval.
* Add redirect in auto-approve orders.
* Use payment page for both advanced and simple order methods.
* Remove MDS checkout page.
* Add additional MDS page types to the block.
* Fix new order being started sometimes when it shouldn't be yet.
* Refine how orders are handled for non-automated order methods like bank, COD, money order, etc.
* Fix Options > Popup Template adding extra br tags when no visual editor is present.
* Fix uploaded image not being reset on new order.
* Allow users to cancel and delete confirmed but not paid orders.
* Update buttons on Order History page based on current step in the order process.
* Update language.

= 2.5.6 =
* Fix fatal error on activation when too low of PHP version was detected.
* Force quantity when adding new item to cart.
* Fix order operations not redirecting properly in MDS admin.
* Refactor currency functions to their own Currency class.
* Add an option for Currency to use when WooCommerce isn't enabled.
* Fix cancel button links on Order History page.
* Fix order page in simple pixel selection method on certain themes that have relative positioning in some page containers.
* Add single-mds-pixel page template to output MDS Pixels in their own pages.
* Also added an option to enable them as they are disabled by default.
* Fix incorrect fetching of url field on list page.
* Fix user uploading new image redirecting to admin-post.php.
* Update language.
* Add cron schedule for cleaning temp upload files.
* Add upgrader_process_complete hook to upgrade database, reset cron and flush permalinks on plugin update.
* Fix user uploading new image not saving properly.

= 2.5.5 =
* Fix having the stats box on the page at the same time as ordering would break the ordering process.
* Implemented a more robust language scanner system using the nikic/PHP-Parser library.
* Change Top Clicks page in Admin to use orders instead of blocks and implement translation for it.
* Update language.

= 2.5.4 =
* Fix NFS page not working due to missing $.
* Update vendor libs.
* Fix infinite no-orders redirect. Thank-you Peter!
* Add automatic page creator and delete buttons with multiple new fields for the new pages.
* Add pages with a shortcode instead of dynamic routes in order to fix header and footer on block themes.
* Add options to Fields tab for making Popup Text, URL and Image field optional.
* Fix advanced pixel selection method not working properly.
* Fix order confirmation when WooCommerce is disabled.
* Add preview to order details on confirm page.
* Fix Manage button not linking to the order to edit.
* Fix editing orders in frontend and admin.
* Optimize block selection speed in advanced pixel selection method.
* Update language.

= 2.5.3 =
* Fix uploaded image preview size in advanced pixel selection method.
* Add Update Language button to automatically add any missing entries to the language file.
* Refactor Language class functions to move content to first arg.
* Update language.
* Add some missing Language to options.
* Add option to allow changing /milliondollarscript endpoint.
* Fix wrong path to admin-options.min.js and remove unnecessary slash from MDS_CORE_PATH in various places.
* Fix new dbver insert.
* Add additional checks to db upgrades.
* Fix list page not showing ad.
* Fix default Popup Template using outdated HTML.
* Fix automatically update width and height attributes of block when the type changes.

= 2.5.2 =
* On dynamic-id check for not empty post id instead of not null.
* Fix deleted orders still showing as reserved during ordering.
* Fix jQuery load event not firing.
* Fix product migration from older versions.
* Add some missing database upgrades.
* Move database upgrades to their own files.
* Add thank-you page option to redirect to after an order is completed.
* Add user adjustable block selection size in advanced pixel selection method.
* Fix being able to select blocks in multi-block selection when not clicking the block.
* Fix still only allowing png files to upload sometimes.
* Fix uploaded image not appearing the proper size.
* Rename $menus to prevent conflict with another global.
* Language file updates.

= 2.5.1 =
* Fix loading of block styles properly.
* Finish implementing updating from MDS 2.3.5.
* Found a way to at least load the menu on dynamic pages for block themes.

= 2.5.0 =
* Fix more warnings, notices, etc.
* Don't load popper and tippy if tooltips are disabled.
* Fix links not working when tooltips are off.
* Convert all language to WP language. Use a plugin like Loco Translate to make changes to the language.
* Remove translation tool.
* Add mds_options_fields and mds_options_save filters for options.
* Update the updater.
* Use jQuery libraries from WordPress.
* Add missing parseInt radix parameter in various places.
* Change AJAX return values to JSON encoded values.
* Add AJAX queue when selecting pixels during ordering process.
* Adjust image file upload width and add filter to only select images.
* Add DOING_AJAX constant when doing AJAX request.
* Fix Not For Sale page not loading.
* Separate WooCommerce options with new filters.
* Update vendor libraries.
* Fix install config errors.
* Fix automatically created MDS product shown on shop page and search.
* Fix when order expires due to unconfirmed order timeout remove from WC cart.
* Fix list page on small screens.
* Rewrite email system to use WP mail functions.
* Add Emails screen to configure email contents.
* Integrate admin directly into WP.
* Remove payment modules.
* Remove currency editor.
* Remove extra config options from main config.
* Remove language.
* Remove form editor UI.
* Convert form fields to use Carbon Fields for text, URL, and image form.
* Add option to enable/disable order expiration via cron.
* Add AJAX for users/buy pixels page.
* Remove iframe completely.
* Remove fields from shortcode and block: lang, display, display_method
* Convert users to WP users.
* Make PNG file optional on grid background image.
* Add user role check to users page and option for allowing specific role(s)
* Remove guest checkout for now since it didn't work properly.
* New main plugin page in WP admin.
* Fix login redirect URL not working all the time.
* Add privileged user meta to WP users screen.
* Add clicks and views count to WP users list screen.
* Add login form.
* Add tabs to options page.
* Options fields filter renamed to mds_options and uses tabs array formatted like 'Tab title' =\> \[fields\]
* Update width and height in the block automatically.
* Fix deprecated usort notice.
* Select and refresh not loading selected blocks properly
* Fix new order being removed too early.
* Fix email and options require being saved before using, defaults don't work in some cases.
* Check if order id is owned by the user first before setting order id on user meta
* Fix multiple consecutive orders not uploading the image (hard to reproduce)
* Fix only PNGs uploading.
* Fix error $el.imageScale is not a function
* Fix MDS order id going to wrong WooCommerce order (may have fixed, have to test) May have been because the order in MDS was not complete and so the session variable for mds_order_id was set to the wrong one when they resumed and not set back or reset Then maybe went to the other one with the wrong session id set and saved that to the WC order
* Fix NFS page.
* Fix email content in Outgoing Email
* Fix MDS order id going to wrong WooCommerce order
* Fix Divi page edit breaking MDS block
* Fix display_reset_link X click not cancelling on Manage Grids.
* Test translation.
* Organize Options page with tabs or something
* Fix NFS page height.
* Fix block loading.
* Fix Attempt to read property "ID" on null in src\Classes\FormFields.php on line 149
* Fix being able to overlap existing orders in advanced pixel selection mode.

## Upgrade Notice ##
