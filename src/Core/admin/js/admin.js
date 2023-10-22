/*
 * Million Dollar Script Two
 *
 * @version     2.5.4
 * @author      Ryan Rhode
 * @copyright   (C) 2023, Ryan Rhode
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
function confirmLink(theLink, theConfirmMsg) {
	if (theConfirmMsg === '') {
		window.location.href=theLink.href;
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
		window.location.href=link;
	}

	return false;
}

function checkBoxes(name) {
	jQuery('input[name="' + name + '[]"]').trigger('click');
}

jQuery(document).ready(function ($) {
	$('.mds-preview-pixels').on('click', function (e) {
		e.preventDefault();
	})
});
