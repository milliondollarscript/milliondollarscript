=== Million Dollar Script ===
Contributors: Ryan Rhode, Adam Malinowski, and the entire community.
Donate link: https://milliondollarscript.com
Tags: million dollar script,mds,pixels,advertising,pixel ads
Requires at least: 6.4
Tested up to: 6.5.2
Stable tag: 2.5.10.110
Requires PHP: 8.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Million Dollar Script enables you to sell pixel-based advertising space on your website for an interactive user experience.

== Description ==

Million Dollar Script enables you to sell pixel-based advertising space on your website for an interactive user experience.

Visit the [Million Dollar Script WordPress Plugin](https://milliondollarscript.com/million-dollar-script-wordpress-plugin/) page on the website for additional documentation.

== Changelog ==

= 2.5.10.x =
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
