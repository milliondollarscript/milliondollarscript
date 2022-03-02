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

const submit_options = {
	target: ".admin-content",
	type: 'post',
	delegation: true,
	beforeSubmit: mds_form_submit,
	success: mds_form_submit_success,
};

function scroll_to_top() {
	jQuery("#mds-top")[0].scrollIntoView();
	document.body.scrollLeft -= 20;
	document.body.scrollTop -= 20;
}

function mds_load_page(page, force) {
	if (!window.mds_admin_loading) {
		window.mds_admin_loading = true;
	} else {
		return;
	}

	// remove hashtag from page
	if (window.location.hash !== "" && (page === undefined || (window.location.hash !== page && force !== true))) {
		page = window.location.hash.substring(1);
	}

	jQuery(".admin-content").load(page, function () {
		scroll_to_top();
		window.mds_admin_loading = false;
	});
}

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
		mds_load_page(theLink.href, true);
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
		mds_load_page(link, true);
	}

	return false;
}

function checkBoxes(name) {
	jQuery('input[name="' + name + '[]"]').trigger('click');
}

function mds_submit(el) {
	let form = jQuery(el).closest('form');
	jQuery(form).ajaxSubmit(submit_options);
}

jQuery(function () {
	window.mds_admin_loading = false;

	jQuery(window).on('mds_admin_page_loaded', function() {
		window.mds_admin_loading = false;
	});

	let admin_content = jQuery(".admin-content");

	let startpage = "main.php";
	mds_load_page(startpage);

	jQuery(document).on('click', 'a', function (event) {
		let target = jQuery(this).attr('target');

		if ('_blank' === target) {
			return true;
		}

		event.preventDefault();
		event.stopPropagation();

		let url = jQuery(this).attr('href');

		if (['_parent', '_top'].indexOf(target) !== -1) {
			window.location.href = url;
			return false;
		}

		if (url.startsWith("http")) {
			window.location = url;
			return false;
		}

		window.mds_admin_loading = true;
		if (url.endsWith('.txt')) {
			admin_content.html('<embed style="width:100%;height:100%;" src="' + url + '" />');
		} else {
			admin_content.load(url, function (response, status) {
				if (status === "success") {
					scroll_to_top();
					window.location.hash = '#' + url;
					jQuery(window).trigger('mds_admin_page_loaded');
				}
			});
		}

		return false;
	});

	jQuery(this).ajaxForm(submit_options);

	jQuery(window).on('popstate', function () {
		jQuery(function () {
			mds_load_page(window.location);
		});
	});

});
