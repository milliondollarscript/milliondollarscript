/*
 * Million Dollar Script Two
 *
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
function confirmLink(theLink, theConfirmMsg) {
	if (theConfirmMsg === '') {
		window.location.href = theLink.href;
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
		window.location.href = link;
	}

	return false;
}

function checkBoxes(name) {
	jQuery('input[name="' + name + '[]"]').trigger('click');
}

jQuery(document).ready(function ($) {
	const $mds_admin_menu = $(".mds-admin-menu");

	$('a[href="#"]').on('click', function (e) {
		e.preventDefault();
	});

	$('.mds-preview-pixels').on('click', function (e) {
		e.preventDefault();
	})

	function showSubmenu() {
		$(this).children('ul').slideDown(100);
		$(this).children('a').addClass('mds-active-submenu');
	}

	function hideSubmenu() {
		$(this).children('ul').slideUp(100);
		$(this).children('a').removeClass('mds-active-submenu');
	}

	$(".mds-admin-menu li").hoverIntent({
		over: showSubmenu,
		out: hideSubmenu,
		timeout: 400,
	});

	function toggleSubmenu($this) {
		const $parent = $this.parent();
		const $menu = $parent.children('ul');

		if ($this.hasClass('mds-active-submenu')) {
			if (!$menu.is(':animated')) {
				$menu.slideUp(100);
				$this.removeClass('mds-active-submenu');
			}
		} else {
			$parent.siblings().find('ul:visible').slideUp(100);
			$parent.siblings().find('a.mds-active-submenu').removeClass('mds-active-submenu');

			if (!$menu.is(':animated')) {
				$menu.slideDown(100);
				$this.addClass('mds-active-submenu');
			}
		}
	}

	$(document).on("click", 'a', function (e) {
		toggleSubmenu($(this));
	});

	const $mds_menu_toggle = $(".mds-menu-toggle");

	updateMenuDisplay();

	$(window).on('resize', updateMenuDisplay);

	$mds_menu_toggle.on("click", function (e) {
		e.preventDefault();
		$mds_admin_menu.slideToggle();
	});

	$mds_admin_menu.find('a').keypress(function (event) {
		if (event.which === 13) {
			console.log("Enter key was pressed");
			toggleSubmenu($(this));
		}
	});

	function updateMenuDisplay() {
		if ($(window).width() > 999) {
			$mds_admin_menu.css('display', '').addClass('mds-desktop-menu');
			$mds_menu_toggle.hide();
		} else {
			$mds_admin_menu.removeClass('mds-desktop-menu');
			if ($mds_admin_menu.is(":visible")) {
				$mds_admin_menu.hide();
			}
			$mds_menu_toggle.show();
		}
	}
});
