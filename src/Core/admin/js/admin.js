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
function confirmLink(theLink, theConfirmMsg) {
	if (theConfirmMsg === '') {
		window.location.href = theLink.href;
		return false;
	}

	let is_confirmed = confirm(theConfirmMsg + '\n');
	if (is_confirmed) {
		let link = theLink.href;
		// Check if href is empty, null, or just a hash (#)
		if (link == null || link === '' || link.endsWith('#')) {
			link = jQuery(theLink).data('link');
		}
		if (link == null || link === '') {
			return true;
		}

		// Properly append query parameter
		if (link.includes('?')) {
			link += '&is_js_confirmed=1';
		} else {
			link += '?is_js_confirmed=1';
		}
		window.location.href = link;
	}

	return false;
}

function checkBoxes(name) {
	jQuery('input[name="' + name + '[]"]').trigger('click');
}

jQuery(document).ready(function ($) {
	const $mds_admin_menu = $(".mds-admin-menu");
	const MENU_ITEM_SELECTOR = ".mds-admin-menu li, .milliondollarscript-menu li";
	const MENU_LINK_SELECTOR = ".mds-admin-menu li > a, .milliondollarscript-menu li > a";
	const HOVER_OPEN_DELAY = 140;
	const HOVER_CLOSE_DELAY = 400;
	const SUBMENU_ANIMATION_DURATION = 140;
	let isDesktop = false;

	$('a[href="#"]').on('click', function (e) {
		e.preventDefault();
	});

	$('.mds-preview-pixels').on('click', function (e) {
		e.preventDefault();
	})

	function clearOpenTimer($li) {
		const timer = $li.data('mdsHoverOpenTimer');
		if (timer) {
			clearTimeout(timer);
			$li.removeData('mdsHoverOpenTimer');
		}
	}

	function clearCloseTimer($li) {
		const timer = $li.data('mdsHoverCloseTimer');
		if (timer) {
			clearTimeout(timer);
			$li.removeData('mdsHoverCloseTimer');
		}
	}

	function clearIntentTimers($li) {
		clearOpenTimer($li);
		clearCloseTimer($li);

		// Clean up legacy key used by previous implementation.
		const legacyTimer = $li.data('mds-hover-timeout');
		if (legacyTimer) {
			clearTimeout(legacyTimer);
			$li.removeData('mds-hover-timeout');
		}
	}

	function closeSubmenu($li, options = {}) {
		const $submenu = $li.children('ul');
		if ($submenu.length === 0) {
			return;
		}

		clearIntentTimers($li);

		const speed = options.immediate ? 0 : SUBMENU_ANIMATION_DURATION;
		$submenu.stop(true, true).slideUp(speed);
		$li.children('a').removeClass('mds-active-submenu');

		// Reset any nested items to avoid lingering active states.
		$submenu.find('a.mds-active-submenu').removeClass('mds-active-submenu');
		$submenu.find('ul').hide();
	}

	function closeSiblingSubmenus($li) {
		$li.siblings('li').each(function () {
			closeSubmenu($(this), { immediate: true });
		});
	}

	function openSubmenu($li, options = {}) {
		const $submenu = $li.children('ul');
		if ($submenu.length === 0) {
			return;
		}

		if (!options.skipSiblingClose) {
			closeSiblingSubmenus($li);
		}

		clearIntentTimers($li);

		const speed = options.immediate ? 0 : SUBMENU_ANIMATION_DURATION;
		$submenu.stop(true, true).slideDown(speed);
		$li.children('a').addClass('mds-active-submenu');
	}

	function handleHoverOver() {
		const $li = $(this);
		if (!isDesktop || $li.children('ul').length === 0) {
			return;
		}

		clearCloseTimer($li);
		clearOpenTimer($li);

		const timer = setTimeout(function () {
			openSubmenu($li);
		}, HOVER_OPEN_DELAY);

		$li.data('mdsHoverOpenTimer', timer);
	}

	function handleHoverOut() {
		const $li = $(this);
		if (!isDesktop || $li.children('ul').length === 0) {
			return;
		}

		clearOpenTimer($li);
		clearCloseTimer($li);

		const timer = setTimeout(function () {
			closeSubmenu($li);
		}, HOVER_CLOSE_DELAY);

		$li.data('mdsHoverCloseTimer', timer);
	}

	function toggleSubmenu($link) {
		const $parent = $link.parent();
		const $submenu = $parent.children('ul');

		if ($submenu.length === 0) {
			return;
		}

		clearIntentTimers($parent);

		const isActive = $link.hasClass('mds-active-submenu') || $submenu.is(':visible');

		if (isActive) {
			closeSubmenu($parent);
		} else {
			openSubmenu($parent, { immediate: true });
		}
	}

	// Click handler - works on both mobile and desktop
	$(document).on("click", MENU_LINK_SELECTOR, function (e) {
		const $link = $(this);
		const $parent = $link.parent();

		// Only handle if this link has a submenu
		if ($parent.children('ul').length > 0) {
			if (!isDesktop) {
				e.preventDefault();
				toggleSubmenu($link);
			}
		}
	});

	$(document).on('focus', MENU_LINK_SELECTOR, function () {
		const $link = $(this);
		const $parent = $link.parent();

		if ($parent.children('ul').length === 0) {
			closeSiblingSubmenus($parent);
			return;
		}

		clearIntentTimers($parent);
		openSubmenu($parent, { immediate: true });
	});

	$(document).on('focusout', MENU_ITEM_SELECTOR, function () {
		const $item = $(this);

		setTimeout(function () {
			if (!$item.is(':focus-within') && !$item.is(':hover')) {
				closeSubmenu($item);
			}
		}, 0);
	});

	$(document).on('mouseenter.mdsMenuHover', MENU_ITEM_SELECTOR, handleHoverOver);
	$(document).on('mouseleave.mdsMenuHover', MENU_ITEM_SELECTOR, handleHoverOut);

	const $mds_menu_toggle = $(".mds-menu-toggle");

	updateMenuDisplay();

	$(window).on('resize', updateMenuDisplay);

	$mds_menu_toggle.on("click", function (e) {
		e.preventDefault();
		$mds_admin_menu.slideToggle();
	});

	function clearAllTimers() {
		$(MENU_ITEM_SELECTOR).each(function () {
			clearIntentTimers($(this));
		});
	}

	function closeAllMenus(options = {}) {
		$(MENU_ITEM_SELECTOR).each(function () {
			closeSubmenu($(this), options);
		});
	}

	function updateMenuDisplay() {
		isDesktop = $(window).width() > 999;

		if (isDesktop) {
			$mds_admin_menu.css('display', '').addClass('mds-desktop-menu');
			$mds_menu_toggle.hide();
		} else {
			$mds_admin_menu.removeClass('mds-desktop-menu');
			if ($mds_admin_menu.is(":visible")) {
				$mds_admin_menu.hide();
			}
			$mds_menu_toggle.show();

			clearAllTimers();
			closeAllMenus({ immediate: true });
		}
	}

	clearAllTimers();
});
