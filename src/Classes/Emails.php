<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.10
 * @author      Ryan Rhode
 * @copyright   (C) 2024, Ryan Rhode
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

namespace MillionDollarScript\Classes;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

defined( 'ABSPATH' ) or exit;

class Emails {
	/**
	 * Register the fields.
	 *
	 * @link https://docs.carbonfields.net#/containers/theme-options
	 */
	public static function register(): void {

		$fields = [

			Field::make( 'separator', MDS_PREFIX . 'order-confirmed-separator', Language::get( 'Order Confirmed' ) ),

			Field::make( 'text', MDS_PREFIX . 'order-confirmed-subject', Language::get( 'Order Confirmed Subject' ) )
			     ->set_default_value( Language::get( 'Order Confirmed' ) )
			     ->set_help_text( Language::get( 'The subject line of the email sent when an order has been confirmed. Placeholders: %FIRST_NAME%, %LAST_NAME%, %ORDER_ID%, %PIXEL_COUNT%, %PIXEL_DAYS%, %DEADLINE%, %PRICE%, %SITE_CONTACT_EMAIL%, %SITE_NAME%, %SITE_URL%' ) ),

			// Order Confirmed
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

			// Order Completed
			Field::make( 'separator', MDS_PREFIX . 'order-completed-separator', Language::get( 'Order Completed' ) ),

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

			// Order Pending
			Field::make( 'separator', MDS_PREFIX . 'order-pending-separator', Language::get( 'Order Pending' ) ),

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

			// Order Expired
			Field::make( 'separator', MDS_PREFIX . 'order-expired-separator', Language::get( 'Order Expired' ) ),

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
2. Go to \'Order History\'<br />
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

			// Order Completed Renewal
			Field::make( 'separator', MDS_PREFIX . 'order-completed-renewal-separator', Language::get( 'Order Completed Renewal' ) ),

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

			// Order Published
			Field::make( 'separator', MDS_PREFIX . 'order-published-separator', Language::get( 'Order Published' ) ),

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

		];

		$fields = apply_filters( 'mds_email_fields', $fields, MDS_PREFIX );

		Container::make( 'theme_options', MDS_PREFIX . 'emails', Language::get( 'Million Dollar Script Emails' ) )
		         ->set_page_parent( 'milliondollarscript' )
		         ->set_page_file( MDS_PREFIX . 'emails' )
		         ->set_page_menu_title( 'Emails' )
		         ->add_fields( $fields );
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

		$fields = \MillionDollarScript\Classes\FormFields::get_fields();
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
		$fields = \MillionDollarScript\Classes\FormFields::get_fields();
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
}
