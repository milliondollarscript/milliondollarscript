=== Million Dollar Script ===
Contributors: Ryan Rhode, Adam Malinowski, and the entire community.
Donate link: https://milliondollarscript.com
Tags: million dollar script,mds,pixels,advertising,pixel ads
Requires at least: 6.3
Tested up to: 6.3.1
Stable tag: 2.5.1
Requires PHP: 8.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Million Dollar Script enables you to sell pixel-based advertising space on your website for an interactive user experience.

== Description ==

Million Dollar Script enables you to sell pixel-based advertising space on your website for an interactive user experience.

Visit the [Million Dollar Script WordPress Plugin](https://milliondollarscript.com/million-dollar-script-wordpress-plugin/) page on the website for additional documentation.

== Changelog ==

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

= TODO =
* Fix being able to select blocks in multi-block selection when not clicking the block. Something to do with $cannot_sel from select.php?
* In advanced mode, if you select "reset" it will change the order to "reserved" and you can't clear this from the admin panel.
* Handle packages on simple mode order screen.
* Handle png extension when auto resize is off.
* Fix wrong currency displaying after image upload.
* Fix simple pixel selection method max blocks setting not working right.
* Fix editing existing order not saving properly
* Mobile responsiveness
* Allow optional fields
* Fix image not resizing
* Fix new order being started sometimes when it shouldn't be yet.
* Optimize Map of Orders
* Visit all the pages without parameters and add correct error handling. Example /milliondollarscript/confirm-order/ or /milliondollarscript/payment/
* Do something with Top Clicks page.
* Fix manage screen grid: Red blocks have no pixels yet. Click on them to upload your pixels
* Fix default capabilities option not loading.
* Large image not going to edge of grid when ordering.
* Disable selection mode if there is only one block allowed in the selected grid
* Get rid of all the extra code that isn't used anymore
* Verify nonces are all working for forms, etc. Maybe move the verify to the top for frontend.
* Make WooCommerce orders completed when MDS orders are set to completed.
* Fix list page not showing image.
* Update translation.
* Test order expiration, deletion, cancellation, etc.
* Fix public grid using user Grid Block setting.

= Next release =

These are things that can wait until the next release or are ongoing but won't be done until next release.

* Completely migrate the Main Config admin options into Carbon Fields. Replace Config::getTypes with getType to make it easier to get the type of a specific string.
* Implement some way to not change the width/height if they aren't defaults in admin-block.js.
* Rewrite language file creation to scan files for Language functions instead of using gettext
* Possible to change only part of the grid that changed when an image is add/updated.
* Fix WooCommerce order quantity doubling if you refresh the page.
* Add support for list to use individual grids or all.
* Replace html/header.php and html/footer.php with proper WP functionality.
* Add Expiration warnings. Check old EXPR.php
* Allow order without logging in.
* Fix clicking orders on grid in Manage Grids not working on scaled down grid.
* Use WP functions for other things.
* Convert all queries to wpdb.
* Add loading to any grids in users screen.
* Debug out of memory issue while ordering.
* Handle refunds in MDS transaction-log
* Avoid image processing
  * Store all original images with max size option
  * Store versions of images scaled to fit their order area
  * Stream scaled images in one request to the grid on load
  * Possibly instead just process in the background with a worker thread.