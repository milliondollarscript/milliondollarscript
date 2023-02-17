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

function mds_form_submit(formData, $form, options) {
	$form.find('input').attr('disabled', true);
	return true;
}

function mds_form_submit_success(responseText, statusText, xhr, $form) {
	jQuery(document).scrollTop(0);

	// let url = $form.attr('action');
	//
	// if (url === "") {
	// 	url = window.location.hash.substr(1);
	// }
	//window.location.hash = '#' + url;

	$form.find('input').attr('disabled', false);

	let order_image_preview = jQuery('#order_image_preview');
	if (order_image_preview.length > 0) {
		let t = new Date();
		let src = order_image_preview.attr('src');
		order_image_preview.attr('src', src + t);
	}
}

function confirmLink(theLink, theConfirmMsg) {
	if (theConfirmMsg === '') {
		// mds_load_page(theLink.href, true);
		return false;
	}

	let is_confirmed = confirm(theConfirmMsg + '\n');
	if (is_confirmed) {
		let link = theLink.href;
		if (link == null) {
			link = jQuery(theLink).data('link');
		}
		if (link == null) {
			return true;
		}

		link += '&is_js_confirmed=1';
		// mds_load_page(link, true);
	}

	return false;
}

function checkBoxes(name) {
	jQuery('input[name="' + name + '[]"]').trigger('click');
}

function mds_submit(el) {
	let form = jQuery(el).closest('form');

	jQuery(form).ajaxSubmit({
		target: ".admin-content",
		type: 'post',
		delegation: true,
		beforeSubmit: mds_form_submit,
		success: mds_form_submit_success,
	});
	return false;
}
