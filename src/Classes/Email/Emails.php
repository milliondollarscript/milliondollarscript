<?php

/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2025, Ryan Rhode
 * @license     https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *    Million Dollar Script
 *    Pixels to Profit: Ignite Your Revolution
 *    https://milliondollarscript.com/
 *
 */

namespace MillionDollarScript\Classes\Email;

use Carbon_Fields\Container;
use Carbon_Fields\Field;
use MillionDollarScript\Classes\Forms\FormFields;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;

defined( 'ABSPATH' ) or exit;

class Emails {
	private static array $tabs;

	/**
	 * Register the fields.
	 *
	 * @link https://docs.carbonfields.net#/containers/theme-options
	 */
	public static function register(): void {

		self::$tabs = [
			// Order Confirmed
			Language::get( 'Order Confirmed' )         => [
				// Enable notifications for Order Confirmed
				Field::make( 'checkbox', MDS_PREFIX . 'email-user-order-confirmed', Language::get( 'Email Advertiser when an order is Confirmed?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then an email will be sent to the advertiser when an order is confirmed.' ) ),
				Field::make( 'checkbox', MDS_PREFIX . 'email-admin-order-confirmed', Language::get( 'Email Admin when an order is Confirmed?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then an email will be sent to the site admin when an order is confirmed.' ) ),
				Field::make( 'text', MDS_PREFIX . 'order-confirmed-subject', Language::get( 'Order Confirmed Subject' ) )
				     ->set_default_value( Language::get( 'Order Confirmed' ) )
				     ->set_help_text( Language::get( 'The subject line of the email sent when an order has been confirmed. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %DEADLINE%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

				Field::make( 'rich_text', MDS_PREFIX . 'order-confirmed-content', Language::get( 'Order Confirmed Content' ) )
				     ->set_settings( array( 'media_buttons' => false ) )
				     ->set_default_value( Language::get( 'Dear %FIRST_NAME% %LAST_NAME%,<br />
<br />
You have successfully placed an order at %SITE_NAME%.<br />
<br />
========================<br />
ORDER DETAILS<br />
=========================<br />
Order ID: #%ORDER_ID%<br />
Pixels: %PIXEL_COUNT%<br />
Days: %PIXEL_DAYS%<br />
Price: %PRICE%<br />
Status: Confirmed<br />
--------------------------<br />
<br />
Your order will be Completed as soon as the payment is cleared.<br />
<br />
Once your order is Completed, log into your account and<br />
upload your pixels and link.<br />
<br />
Feel free to contact %SITE_CONTACT_EMAIL% if you have any questions / problems.<br /> 
<br />
Thank you!<br />
<br />
%SITE_NAME% team.<br />
%SITE_URL%<br />
<br />
Note: This is an automated email.<br />
' ) )
				     ->set_help_text( Language::get( 'The body of the email sent when an order has been confirmed. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %DEADLINE%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),
			],

			// Order Completed
			Language::get( 'Order Completed' )         => [
				// Enable notifications for Order Completed
				Field::make( 'checkbox', MDS_PREFIX . 'email-user-order-completed', Language::get( 'Email Advertiser when an order is Completed?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then an email will be sent to the advertiser when an order is completed.' ) ),
				Field::make( 'checkbox', MDS_PREFIX . 'email-admin-order-completed', Language::get( 'Email Admin when an order is Completed?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then an email will be sent to the site admin when an order is completed.' ) ),
				Field::make( 'text', MDS_PREFIX . 'order-completed-subject', Language::get( 'Order Completed Subject' ) )
				     ->set_default_value( Language::get( 'Order Completed' ) )
				     ->set_help_text( Language::get( 'The subject line of the email sent when an order has been confirmed. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %DEADLINE%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

				Field::make( 'rich_text', MDS_PREFIX . 'order-completed-content', Language::get( 'Order Completed' ) )
				     ->set_settings( array( 'media_buttons' => false ) )
				     ->set_default_value( Language::get( 'RE: Order Status Change Notification.<br />
<br />
Dear %FIRST_NAME% %LAST_NAME%,<br />
<br />
Your order was completed on %SITE_NAME%.<br />
<br />
========================<br />
ORDER DETAILS<br />
=========================<br />
Order ID: #%ORDER_ID%<br />
Pixels: %PIXEL_COUNT%<br />
Days: %PIXEL_DAYS%<br />
Price: %PRICE%<br />
Status: Completed<br />
--------------------------.<br />
<br />
Please Log into your account and<br />
upload your pixels and link.<br />
<br />
Feel free to contact %SITE_CONTACT_EMAIL% if you have any questions / problems.<br /> 
<br />
Thank you!<br />
<br />
%SITE_NAME% team.<br />
%SITE_URL%<br />
<br />
Note: This is an automated email.<br />
' ) )
				     ->set_help_text( Language::get( 'This email is sent when an order has been completed. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

			],

			// Order Pending
			Language::get( 'Order Pending' )           => [
				// Enable notifications for Order Pending
				Field::make( 'checkbox', MDS_PREFIX . 'email-user-order-pended', Language::get( 'Email Advertiser when an order is Pended?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then an email will be sent to the advertiser when an order is pending.' ) ),
				Field::make( 'checkbox', MDS_PREFIX . 'email-admin-order-pended', Language::get( 'Email Admin when an order is Pended?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then an email will be sent to the site admin when an order is pending.' ) ),
				Field::make( 'text', MDS_PREFIX . 'order-pending-subject', Language::get( 'Order Pending Subject' ) )
				     ->set_default_value( Language::get( 'Order Pending' ) )
				     ->set_help_text( Language::get( 'The subject line of the email sent when an order is pending. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %DEADLINE%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

				Field::make( 'rich_text', MDS_PREFIX . 'order-pending-content', Language::get( 'Order Pending Content' ) )
				     ->set_settings( array( 'media_buttons' => false ) )
				     ->set_default_value( Language::get( 'RE: Order Status Change Notification.<br />
<br />
Dear %FNAME% %LNAME%,<br />
<br />
Your order status changed to \'Pending\' on %SITE_NAME%.<br />
<br />
This means that you payment was recived, and the funds<br />
are clearing. Once the funds are cleared, you will be<br />
able to manage your pixels.<br />
<br />
========================<br />
ORDER DETAILS<br />
=========================<br />
Order ID: #%ORDER_ID%<br />
Pixels: %PIXEL_COUNT%<br />
Days: %PIXEL_DAYS%<br />
Price: %PRICE%<br />
Status: Pending<br />
--------------------------.<br />
<br />
Feel free to contact %SITE_CONTACT_EMAIL% if you have any questions / problems.<br /> 
<br />
Thank you!<br />
<br />
%SITE_NAME% team.<br />
%SITE_URL%<br />
<br />
Note: This is an automated email.<br />
' ) )
				     ->set_help_text( Language::get( 'This email is sent when an order is pending. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

			],

			// Order Expired
			Language::get( 'Order Expired' )           => [
				// Enable notifications for Order Expired
				Field::make( 'checkbox', MDS_PREFIX . 'email-user-order-expired', Language::get( 'Email Advertiser when an order is Expired?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then an email will be sent to the advertiser when an order is expired.' ) ),
				Field::make( 'checkbox', MDS_PREFIX . 'email-admin-order-expired', Language::get( 'Email Admin when an order is Expired?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then an email will be sent to the site admin when an order is expired.' ) ),
				Field::make( 'text', MDS_PREFIX . 'order-expired-subject', Language::get( 'Order Expired Subject' ) )
				     ->set_default_value( Language::get( 'Order Expired' ) )
				     ->set_help_text( Language::get( 'The subject line of the email sent when an order is expired. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %DEADLINE%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

				Field::make( 'rich_text', MDS_PREFIX . 'order-expired-content', Language::get( 'Order Expired Content' ) )
				     ->set_settings( array( 'media_buttons' => false ) )
				     ->set_default_value( Language::get( 'RE: Order Status Change Notification.<br />
<br />
Dear %FIRST_NAME% %LAST_NAME%,<br />
<br />
Your order status changed to \'Expired\' on %SITE_NAME%.<br />
<br />
This means that your pixels have expired and will<br /> 
no longer be show when the grid is next updated.<br />
You may renew your order, Here is how to do it:<br />
<br />
1. Log in to your account<br />
<br />
2. Go to \'Manage Pixels\'<br />
<br />
3. Click on \'Renew\' on the Order shown as \'expired\'.<br />
<br />
4. Complete the payment<br />
<br />
========================<br />
ORDER DETAILS<br />
=========================<br />
Order ID: #%ORDER_ID%<br />
Pixels: %PIXEL_COUNT%<br />
Days: %PIXEL_DAYS%<br />
Price: %PRICE%<br />
Status: Expired<br />
--------------------------.<br />
<br />
Feel free to contact %SITE_CONTACT_EMAIL% if you have any questions / problems.<br /> 
<br />
Thank you!<br />
<br />
%SITE_NAME% team.<br />
%SITE_URL%<br />
<br />
Note: This is an automated email.<br />
' ) )
				     ->set_help_text( Language::get( 'This email is sent when an order is expired. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

			],

			// Order Completed Renewal
			Language::get( 'Order Completed Renewal' ) => [

				// Enable notifications for Order Completed Renewal
				Field::make( 'checkbox', MDS_PREFIX . 'email-user-order-completed-renewal', Language::get( 'Email Advertiser when a renewal is completed?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then an email will be sent to the advertiser when an order renewal is completed.' ) ),
				Field::make( 'checkbox', MDS_PREFIX . 'email-admin-order-completed-renewal', Language::get( 'Email Admin when a renewal is completed?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then an email will be sent to the site admin when an order renewal is completed.' ) ),
				Field::make( 'text', MDS_PREFIX . 'order-completed-renewal-subject', Language::get( 'Order Completed Renewal Subject' ) )
				     ->set_default_value( Language::get( 'Order Completed Renewal' ) )
				     ->set_help_text( Language::get( 'The subject line of the email sent when an order has completed renewal. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %ORIGINAL_ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %DEADLINE%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

				Field::make( 'rich_text', MDS_PREFIX . 'order-completed-renewal-content', Language::get( 'Order Completed Renewal Content' ) )
				     ->set_settings( array( 'media_buttons' => false ) )
				     ->set_default_value( Language::get( 'RE: Order Status Change Notification.<br />
<br />
Dear %FNAME% %LNAME%,<br />
<br />
Your order was renewed on %SITE_NAME%.<br />
<br />
========================<br />
ORDER DETAILS<br />
=========================<br />
Order ID: #%ORDER_ID% (Carried over from %ORIGINAL_ORDER_ID%)<br />
Pixels: %PIXEL_COUNT%<br />
Days: %PIXEL_DAYS%<br />
Price: %PRICE%<br />
Status: Completed<br />
--------------------------.<br />
<br />
Please Log into your account and<br />
upload your pixels and link.<br />
<br />
Feel free to contact %SITE_CONTACT_EMAIL% if you have any questions / problems.<br /> 
<br />
Thank you!<br />
<br />
%SITE_NAME% team.<br />
%SITE_URL%<br />
<br />
Note: This is an automated email.<br />
' ) )
				     ->set_help_text( Language::get( 'This email is sent when an order has completed renewal. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %ORIGINAL_ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

			],

			// Order Published
			Language::get( 'Order Published' )         => [

				Field::make( 'text', MDS_PREFIX . 'order-published-subject', Language::get( 'Order Published Subject' ) )
				     ->set_default_value( Language::get( 'Order Published' ) )
				     ->set_help_text( Language::get_replace( 'The subject line of the email sent to the site admin when an order is published. Placeholders: %PLACEHOLDERS%', [ '%PLACEHOLDERS%' ], self::get_order_published_placeholders_text() ) ),

				Field::make( 'rich_text', MDS_PREFIX . 'order-published-content', Language::get( 'Order Published Content' ) )
				     ->set_settings( array( 'media_buttons' => false ) )
				     ->set_default_value( Language::get( 'New pixels published on %SITE_NAME%!<br />
<br />
Grid: %GRID_NAME%<br />
Username: %USER_LOGIN%<br />
<br />
URLS:<br />
%URL_LIST%<br />
<br />
- To view and approve the pixels, please visit here:<br />
<a href=\'%VIEW_URL%\'>Click Here...</a><br />
' ) )
				     ->set_help_text( Language::get_replace( 'This email is sent to the site admin when an order is published. Placeholders: %PLACEHOLDERS%', [ '%PLACEHOLDERS%' ], self::get_order_published_placeholders_text() ) ),

			],

			// Order Denied
			Language::get( 'Order Denied' )            => [
				// Enable notifications for Order Denied
				Field::make( 'checkbox', MDS_PREFIX . 'email-user-order-denied', Language::get( 'Email Advertiser when an order is Denied?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then an email will be sent to the advertiser when an order is denied.' ) ),
				Field::make( 'checkbox', MDS_PREFIX . 'email-admin-order-denied', Language::get( 'Email Admin when an order is Denied?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then an email will be sent to the site admin when an order is denied.' ) ),
				Field::make( 'text', MDS_PREFIX . 'order-denied-subject', Language::get( 'Order Denied Subject' ) )
				     ->set_default_value( Language::get( 'Order Denied' ) )
				     ->set_help_text( Language::get( 'The subject line of the email sent when an order is denied. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %DEADLINE%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

				Field::make( 'rich_text', MDS_PREFIX . 'order-denied-content', Language::get( 'Order Denied Content' ) )
				     ->set_settings( array( 'media_buttons' => false ) )
				     ->set_default_value( Language::get( 'RE: Order Status Change Notification.<br />
<br />
Dear %FIRST_NAME% %LAST_NAME%,<br />
<br />
Your order status changed to \'Denied\' on %SITE_NAME%.<br />
<br />
This means that your pixels have been denied and will
no longer be show on the grid.<br />
<br />
Please log in and update your order:<br />
<br />
1. Log in to your account<br />
<br />
2. Go to \'Manage Pixels\'<br />
<br />
3. Click on \'Manage\' on the Order shown as \'Denied\'.<br />
<br />
4. Complete the payment<br />
<br />
========================<br />
ORDER DETAILS<br />
=========================<br />
Order ID: #%ORDER_ID%<br />
Pixels: %PIXEL_COUNT%<br />
Days: %PIXEL_DAYS%<br />
Price: %PRICE%<br />
Status: Denied<br />
--------------------------.<br />
<br />
Feel free to contact %SITE_CONTACT_EMAIL% if you have any questions / problems.<br /> 
<br />
Thank you!<br />
<br />
%SITE_NAME% team.<br />
%SITE_URL%<br />
<br />
Note: This is an automated email.<br />
' ) )
				     ->set_help_text( Language::get( 'This email is sent when an order is denied. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

			],

			// Order Renewal
			Language::get( 'Order Renewal' )           => [
				// Enable notifications for Order Renewal
				Field::make( 'checkbox', MDS_PREFIX . 'email-user-order-renewal', Language::get( 'Email Advertiser when an order is Renewed?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then an email will be sent to the advertiser when an order is renewed.' ) ),
				Field::make( 'checkbox', MDS_PREFIX . 'email-admin-order-renewal', Language::get( 'Email Admin when an order is Renewed?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then an email will be sent to the site admin when an order is renewed.' ) ),
				Field::make( 'text', MDS_PREFIX . 'order-renewal-subject', Language::get( 'Order Renewal Subject' ) )
				     ->set_default_value( Language::get( 'Order Renewed' ) )
				     ->set_help_text( Language::get( 'The subject line of the email sent when an order is renewed. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %DEADLINE%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

				Field::make( 'rich_text', MDS_PREFIX . 'order-renewal-content', Language::get( 'Order Renewal Content' ) )
				     ->set_settings( array( 'media_buttons' => false ) )
				     ->set_default_value( Language::get( 'RE: Order Renewal Notification.<br />
<br />
Dear %FIRST_NAME% %LAST_NAME%,<br />
<br />
Your order has been successfully renewed on %SITE_NAME%.<br />
<br />
Thank you for continuing to advertise with us. Your pixels will remain displayed on the grid for the renewed period.
<br />
========================<br />
ORDER DETAILS<br />
=========================<br />
Order ID: #%ORDER_ID%<br />
Pixels: %PIXEL_COUNT%<br />
Days: %PIXEL_DAYS%<br />
Price: %PRICE%<br />
Status: Renewed<br />
--------------------------.<br />
<br />
Feel free to contact %SITE_CONTACT_EMAIL% if you have any questions / problems.<br /> 
<br />
Thank you!<br />
<br />
%SITE_NAME% team.<br />
%SITE_URL%<br />
<br />
Note: This is an automated email.<br />
' ) )
				     ->set_help_text( Language::get( 'This email is sent when an order is renewed. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

			],

			// Renewal Reminder
			Language::get( 'Renewal Reminder' )         => [
				// Enable notifications for Renewal Reminder
				Field::make( 'checkbox', MDS_PREFIX . 'email-user-renewal-reminder', Language::get( 'Send renewal reminder to Advertiser?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then reminder emails will be sent to the advertiser before their order expires.' ) ),
				Field::make( 'checkbox', MDS_PREFIX . 'email-admin-renewal-reminder', Language::get( 'Send renewal reminder to Admin?' ) )
				     ->set_default_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then reminder emails will be sent to the site admin before an order expires.' ) ),
				     
				// Days before expiration to send reminders
				Field::make( 'text', MDS_PREFIX . 'renewal-reminder-days-1', Language::get( 'Days before expiration to send first reminder' ) )
				     ->set_attribute( 'type', 'number' )
				     ->set_attribute( 'min', '1' )
				     ->set_default_value( '7' )
				     ->set_help_text( Language::get( 'Number of days before expiration to send the first reminder email.' ) ),

				Field::make( 'text', MDS_PREFIX . 'renewal-reminder-days-2', Language::get( 'Days before expiration to send second reminder' ) )
				     ->set_attribute( 'type', 'number' )
				     ->set_attribute( 'min', '1' )
				     ->set_default_value( '3' )
				     ->set_help_text( Language::get( 'Number of days before expiration to send the second reminder email. Leave empty to disable.' ) ),

				Field::make( 'text', MDS_PREFIX . 'renewal-reminder-days-3', Language::get( 'Days before expiration to send third reminder' ) )
				     ->set_attribute( 'type', 'number' )
				     ->set_attribute( 'min', '1' )
				     ->set_default_value( '1' )
				     ->set_help_text( Language::get( 'Number of days before expiration to send the third reminder email. Leave empty to disable.' ) ),
				     
				Field::make( 'text', MDS_PREFIX . 'renewal-reminder-subject', Language::get( 'Renewal Reminder Subject' ) )
				     ->set_default_value( Language::get( 'Your Order Will Expire Soon' ) )
				     ->set_help_text( Language::get( 'The subject line of the reminder email. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %DAYS_LEFT%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

				Field::make( 'rich_text', MDS_PREFIX . 'renewal-reminder-content', Language::get( 'Renewal Reminder Content' ) )
					->set_settings( array( 'media_buttons' => false ) )
					->set_default_value( Language::get( 'RE: Order Expiration Reminder.<br />
<br />
Dear %FIRST_NAME% %LAST_NAME%,<br />
<br />
This is a friendly reminder that your order will expire in %DAYS_LEFT% days on %SITE_NAME%.<br />
<br />
To ensure your pixels remain visible on our grid, please log in to your account and renew your order before it expires.
<br />
========================<br />
ORDER DETAILS<br />
=========================<br />
Order ID: #%ORDER_ID%<br />
Pixels: %PIXEL_COUNT%<br />
Days remaining: %DAYS_LEFT%<br />
Price for renewal: %PRICE%<br />
Status: Active (Expiring Soon)<br />
--------------------------.<br />
<br />
To renew your order, simply visit your account dashboard and click the "Renew" button next to your order.<br />
<br />
Feel free to contact %SITE_CONTACT_EMAIL% if you have any questions / problems.<br /> 
<br />
Thank you!<br />
<br />
%SITE_NAME% team.<br />
%SITE_URL%<br />
<br />
Note: This is an automated email.<br />
' ) )
				     ->set_help_text( Language::get( 'This email is sent to remind users before their order expires. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %DAYS_LEFT%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

			],

			// Email Settings
			Language::get( 'Email Settings' )          => [
				// Emails per Batch
				Field::make( 'text', MDS_PREFIX . 'emails-per-batch', Language::get( 'Emails per Batch' ) )
					 ->set_attribute( 'type', 'number' )
					 ->set_default_value( '12' )
					 ->set_help_text( Language::get( 'Send a maximum number of emails per batch.' ) ),

				// Emails Max Retry
				Field::make( 'text', MDS_PREFIX . 'emails-max-retry', Language::get( 'Emails Max Retry' ) )
					 ->set_attribute( 'type', 'number' )
					 ->set_default_value( '15' )
					 ->set_help_text( Language::get( 'On error, retry this number of times before giving up.' ) ),

				// Emails Error Wait (minutes)
				Field::make( 'text', MDS_PREFIX . 'emails-error-wait', Language::get( 'Email Error Wait (minutes)' ) )
					 ->set_attribute( 'type', 'number' )
					 ->set_default_value( '20' )
					 ->set_help_text( Language::get( 'On error, wait at least this many minutes before retry.' ) ),

				// Keep sent emails for X days
				Field::make( 'text', MDS_PREFIX . 'emails-days-keep', Language::get( 'Keep Sent Emails (days)' ) )
					 ->set_attribute( 'type', 'number' )
					 ->set_default_value( '30' )
					 ->set_help_text( Language::get( 'Number of days to keep sent emails in the database. Enter 0 to keep forever.' ) ),
			],

			// Order Published
			Language::get( 'Order Published' )         => [
				// Enable notifications for Order Published
				Field::make( 'checkbox', MDS_PREFIX . 'email-admin-publish-notify', Language::get( 'Pixels Modified: Send a notification email to Admin?' ) )
				     ->set_default_value( '' )
				     ->set_option_value( 'yes' )
				     ->set_help_text( Language::get( 'If checked then a notification email will be sent to the site admin when pixels are published.' ) ),
				Field::make( 'text', MDS_PREFIX . 'order-published-subject', Language::get( 'Order Published Subject' ) )
				     ->set_default_value( Language::get( 'Order Published' ) )
				     ->set_help_text( Language::get_replace( 'The subject line of the email sent to the site admin when an order is published. Placeholders: %PLACEHOLDERS%', [ '%PLACEHOLDERS%' ], self::get_order_published_placeholders_text() ) ),

				Field::make( 'rich_text', MDS_PREFIX . 'order-published-content', Language::get( 'Order Published Content' ) )
				     ->set_settings( array( 'media_buttons' => false ) )
				     ->set_default_value( Language::get( 'New pixels published on %SITE_NAME%!<br />
<br />
Grid: %GRID_NAME%<br />
Username: %USER_LOGIN%<br />
<br />
URLS:<br />
%URL_LIST%<br />
<br />
- To view and approve the pixels, please visit here:<br />
<a href=\'%VIEW_URL%\'>Click Here...</a><br />
' ) )
				     ->set_help_text( Language::get_replace( 'This email is sent to the site admin when an order is published. Placeholders: %PLACEHOLDERS%', [ '%PLACEHOLDERS%' ], self::get_order_published_placeholders_text() ) ),

			],

		];

		self::$tabs = apply_filters( 'mds_email_fields', self::$tabs, MDS_PREFIX );

		$container = Container::make( 'theme_options', MDS_PREFIX . 'emails', Language::get( 'Million Dollar Script Emails' ) )
		                      ->set_page_parent( 'milliondollarscript' )
		                      ->set_page_file( MDS_PREFIX . 'emails' )
		                      ->set_page_menu_title( 'Emails' );

		foreach ( self::$tabs as $title => $fields ) {
			$container->add_tab( Language::get( $title ), $fields );
		}
	}

	/**
	 * Search and replace string.
	 * Note: Replacements may be sanitized beforehand, however emails are sanitized when sent.
	 *
	 * @param $search array|string String or array to search for.
	 * @param $replace array|string String or array to replace with.
	 * @param $string string String to translate and replace.
	 *
	 * @return array|string|null
	 */
	public static function replace( array|string $search, array|string $replace, string $string ): array|string|null {
		return str_replace( $search, $replace, $string );
	}

	/**
	 * Get the email and run the replace function on it.
	 * Note: Replacements may be sanitized beforehand, however emails are sanitized when sent.
	 *
	 * @param array|string $search
	 * @param array|string $replace
	 * @param string $field
	 *
	 * @return string
	 */
	public static function get_email_replace( array|string $search, array|string $replace, string $field ): string {
		return self::replace( $search, $replace, self::get_email( $field ) );
	}

	
	/**
	 * Load Carbon Fields
	 */
	public static function load(): void {
		\Carbon_Fields\Carbon_Fields::boot();
	}

	/**
	 * Hook into Carbon Fields filter to sanitize some fields on save.
	 *
	 * @param $field \Carbon_Fields\Field\Field
	 *
	 * @return \Carbon_Fields\Field\Field
	 */
	public static function save( \Carbon_Fields\Field\Field $field ): \Carbon_Fields\Field\Field {
		$name = str_replace( '_' . MDS_PREFIX, '', $field->get_name() );

		return apply_filters( 'mds_email_save', $field, $name );
	}

	/**
	 * Get options from database.
	 *
	 * @param $name
	 * @param bool $carbon_fields
	 * @param string $container_id
	 * @param null $default
	 *
	 * @return mixed
	 */
	public static function get_email( $name, bool $carbon_fields = true, string $container_id = 'emails', $default = null ): mixed {
		if ( $carbon_fields && function_exists( '\carbon_get_theme_option' ) ) {
			return apply_filters( 'the_content', \carbon_get_theme_option( MDS_PREFIX . $name ) );
		}

		$n = '_' . MDS_PREFIX . $name;
		$c = '_' . MDS_PREFIX . $container_id;

		$opts = get_option( $n, $default );
		$val  = $opts;

		if ( 'all' == $name ) {
			$val = $opts;
		} elseif ( is_array( $opts ) && array_key_exists( $c, $opts ) ) {
			$val = $opts[ $c ];
		}

		return apply_filters( 'the_content', $val );
	}

	/**
	 * Get search placeholders for the published notification email.
	 *
	 * @return array
	 */
	public static function get_search_for_published_notification(): array {
		$search = [
			'%SITE_NAME%',
			'%GRID_NAME%',
			'%FIRST_NAME%',
			'%LAST_NAME%',
			'%USER_LOGIN%',
			'%URL_LIST%',
			'%VIEW_URL%',
			'%ORDER_ID%',
		];

		$fields = FormFields::get_fields();
		foreach ( $fields as $field ) {
			// Get the key without prefix and value from the field.
			$field_name = $field->get_base_name();
			$key        = str_replace( MDS_PREFIX, '', $field_name );

			// Update search array with the keys.
			$search[] = '%' . strtoupper( $key ) . '%';
		}

		return $search;
	}

	/**
	 * Get replace values for the published notification email.
	 *
	 * @param int $user_id
	 * @param int $ad_id
	 * @param int $order_id
	 *
	 * @return array
	 */
	public static function get_replace_for_published_notification( int $user_id, int $order_id, int $ad_id ): array {
		$user_info = get_userdata( $user_id );

		global $wpdb;

		$grid = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'grid' );
		$text = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'text' );
		$url  = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'url' );

		$sql   = $wpdb->prepare( "SELECT `name` from " . MDS_DB_PREFIX . "banners where banner_id=%d", $grid );
		$b_row = $wpdb->get_row( $sql, ARRAY_A );
		$name  = '';
		if ( ! empty( $b_row ) ) {
			$name = $b_row['name'];
		}

		$url_list = '';
		if ( $text != null && $url != null ) {
			$url_list .= sanitize_email( $url ) . " - " . sanitize_email( $text ) . "\n";
		}

		$view_url = get_admin_url();

		$replace = [
			get_bloginfo( 'name' ),
			$name,
			$user_info->first_name,
			$user_info->last_name,
			$user_info->user_login,
			$url_list,
			$view_url,
			$order_id,
		];

		$values = [];
		$fields = FormFields::get_fields();
		foreach ( $fields as $field ) {
			// Get the key without prefix and value from the field.
			$field_name     = $field->get_base_name();
			$key            = str_replace( MDS_PREFIX, '', $field_name );
			$values[ $key ] = carbon_get_post_meta( $ad_id, $field_name );

			// Update replace array with the values.
			$replace[] = $values[ $key ];
		}

		return $replace;
	}

	/**
	 * Get order published placeholders text.
	 *
	 * @return string
	 */
	public static function get_order_published_placeholders_text(): string {
		$search = Emails::get_search_for_published_notification();

		// Remove unnecessary hidden fields.
		$search = array_diff( $search, [ '%ORDER%', '%GRID%', '%URL_LIST%' ] );

		return implode( ', ', $search );
	}

	public static function send_published_pixels_notification( $user_id, $order_id ) {

		// Get the post ID from the order ID
		$ad_id = Orders::get_ad_id_from_order_id( $order_id );
		if ( $ad_id == null ) {
			return;
		}

		$search  = Emails::get_search_for_published_notification();
		$replace = Emails::get_replace_for_published_notification( $user_id, $order_id, $ad_id );

		$message = Emails::get_email_replace(
			$search,
			$replace,
			'order-published-content'
		);

		$subject = Emails::get_email_replace(
			$search,
			$replace,
			'order-published-subject'
		);

		// Send email to admin if enabled
		if ( self::get_email( 'email-admin-publish-notify' ) === 'yes' ) {
			Mail::send( get_bloginfo( 'admin_email' ), $subject, $message, 'Admin', get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 7 );
		}
	}

	/**
	 * Function to validate email addresses
	 *
	 * @link https://stackoverflow.com/a/42969643/311458
	 *
	 * @param $email
	 *
	 * @return bool
	 */
	public static function validate_mail( $email ): bool {
		$emailB = filter_var( $email, FILTER_SANITIZE_EMAIL );

		if ( filter_var( $emailB, FILTER_VALIDATE_EMAIL ) === false || $emailB != $email ) {
			return false;
		}

		return true;
	}

	/**
	 * Send renewal reminder emails for orders that are about to expire
	 *
	 * This method should be called by a cron job to check for orders that 
	 * are nearing expiration and send reminder emails based on the configured
	 * days before expiration.
	 *
	 * @return void
	 */
	public static function send_renewal_reminders(): void {
		global $wpdb;
		
		// Check if renewal reminders are enabled
		if ( self::get_email( 'email-user-renewal-reminder' ) !== 'yes' && 
		     self::get_email( 'email-admin-renewal-reminder' ) !== 'yes' ) {
			return;
		}
		
		// Get configured days for reminders
		$reminder_days = [];
		for ( $i = 1; $i <= 3; $i++ ) {
			$days = (int) self::get_email( 'renewal-reminder-days-' . $i );
			if ( $days > 0 ) {
				$reminder_days[] = $days;
			}
		}
		
		// No reminder days configured, nothing to do
		if ( empty( $reminder_days ) ) {
			return;
		}
		
		$now = current_time( 'mysql' );
		$timezone = get_option( 'timezone_string' );
		
		// For each configured reminder day
		foreach ( $reminder_days as $days_before ) {
			// Find orders that will expire in exactly $days_before days
			$sql = $wpdb->prepare(
				"SELECT o.*, u.* FROM " . MDS_DB_PREFIX . "orders o 
				JOIN " . $wpdb->prefix . "users u ON o.user_id = u.ID 
				WHERE o.status = 'completed' 
				AND o.published = 'Y' 
				AND o.date_published IS NOT NULL 
				AND DATE_ADD(o.date_published, INTERVAL o.days_expire DAY) = DATE_ADD(%s, INTERVAL %d DAY)",
				$now,
				$days_before
			);
			
			$expiring_orders = $wpdb->get_results( $sql );
			
			if ( ! empty( $expiring_orders ) ) {
				foreach ( $expiring_orders as $order ) {
					// Get order details for email
					$order_id = $order->order_id;
					$user_id = $order->user_id;
					$user_email = $order->user_email;
					$first_name = $order->first_name ?? '';
					$last_name = $order->last_name ?? '';
					
					// Create the date object for the expiration date
					$date_published = new \DateTime( $order->date_published, new \DateTimeZone( $timezone ) );
					$date_published->add( new \DateInterval( 'P' . $order->days_expire . 'D' ) );
					
					// Get price information
					$price = '$' . number_format( $order->price, 2 );
					$block_count = $order->block_count ?? 0;
					
					// Build placeholders for the email
					$search = [
						'%SITE_NAME%',
						'%FIRST_NAME%',
						'%LAST_NAME%',
						'%USERNAME%',
						'%ORDER_ID%',
						'%PIXEL_COUNT%',
						'%BLOCK_COUNT%',
						'%PIXEL_DAYS%',
						'%DAYS_LEFT%',
						'%PRICE%',
						'%SITE_CONTACT_EMAIL%',
						'%SITE_URL%',
					];
					
					$replace = [
						get_bloginfo( 'name' ),
						$first_name,
						$last_name,
						$order->user_login,
						$order_id,
						$order->quantity,
						$block_count,
						$order->days_expire,
						$days_before,
						$price,
						get_bloginfo( 'admin_email' ),
						get_site_url(),
					];
					
					// Get the email content and subject
					$subject = self::get_email_replace(
						$search,
						$replace,
						'renewal-reminder-subject'
					);
					
					$message = self::get_email_replace(
						$search,
						$replace,
						'renewal-reminder-content'
					);
					
					// Send email to user if enabled
					if ( self::get_email( 'email-user-renewal-reminder' ) === 'yes' ) {
						Mail::send( $user_email, $subject, $message, $first_name . " " . $last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 8 );
					}
					
					// Send email to admin if enabled
					if ( self::get_email( 'email-admin-renewal-reminder' ) === 'yes' ) {
						Mail::send( get_bloginfo( 'admin_email' ), $subject, $message, $first_name . " " . $last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 8 );
					}
				}
			}
		}
	}

}
