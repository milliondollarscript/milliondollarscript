/*
 * @package       mds
 * @copyright     (C) Copyright 2022 Ryan Rhode, All rights reserved.
 * @author        Ryan Rhode, ryan@milliondollarscript.com
 * @version       2022-01-30 17:07:25 EST
 * @license       This program is free software; you can redistribute it and/or modify
 *        it under the terms of the GNU General Public License as published by
 *        the Free Software Foundation; either version 3 of the License, or
 *        (at your option) any later version.
 *
 *        This program is distributed in the hope that it will be useful,
 *        but WITHOUT ANY WARRANTY; without even the implied warranty of
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *        GNU General Public License for more details.
 *
 *        You should have received a copy of the GNU General Public License along
 *        with this program;  If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 *
 *  * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *        Million Dollar Script
 *        A pixel script for selling pixels on your website.
 *
 *        For instructions see README.txt
 *
 *        Visit our website for FAQs, documentation, a list team members,
 *        to post any bugs or feature requests, and a community forum:
 *        https://milliondollarscript.com/
 *
 */

jQuery(function () {
	jQuery('.mds_upload_image').on('click', function (e) {
		let $el = jQuery(this);
		$el.prop('disabled', true);
		$el.attr('value', 'Uploading...');
		$el.parent('form').submit();
	});

	jQuery('.mds_pointer_graphic').on('load', function (e) {
		jQuery('.mds_upload_image').prop('disabled', false);
		jQuery(this).attr('value', 'Upload');
	});

	jQuery('.mds_save_ad_button').on('click', function () {
		let $el = jQuery(this);
		$el.prop('disabled', true);
		$el.attr('value', 'Saving...');
		$el.closest('form').submit();
	});

	jQuery('#mds-complete-button').on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		let $el = jQuery(this);
		$el.prop('disabled', true);
		$el.attr('value', 'Completing...');
		window.location=MDS.BASE_HTTP_PATH + 'users/publish.php?action=complete&order_id=' + $el.data('order-id') + '&BID=' + $el.data('grid');
		return false;
	});

	jQuery('#mds-confirm-button').on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		let $el = jQuery(this);
		$el.prop('disabled', true);
		$el.attr('value', 'Confirming...');
		window.location=MDS.BASE_HTTP_PATH + 'users/payment.php?action=confirm&order_id=' + $el.data('order-id') + '&BID=' + $el.data('grid');
		return false;
	});
});