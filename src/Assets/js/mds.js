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

// Million Dollar Script Two JavaScript

function add_ajax_loader(container) {
	let $ajax_loader = jQuery("<div class='ajax-loader'></div>");
	jQuery(container).append($ajax_loader)
	$ajax_loader.css('top', jQuery(container).position().top).css('left', (jQuery(container).width() / 2) - ($ajax_loader.width() / 2));
}

function remove_ajax_loader() {
	jQuery('.ajax-loader').remove();
}

var initialized = false;

// @link https://stackoverflow.com/a/58514043/311458
function defer(toWaitFor, method) {
	if (window[toWaitFor]) {
		method();
	} else {
		setTimeout(function () {
			defer(toWaitFor, method)
		}, 50);
	}
}

function mds_grid(container, bid, width, height) {
	if (jQuery('#' + container).length > 0) {
		return;
	}

	add_ajax_loader('.' + container);

	let grid = jQuery("<div class='grid-inner' id='" + container + "'></div>");
	grid.css('width', width).css('height', height);
	jQuery('.' + container).append(grid);

	const data = {
		action: 'mds_ajax_grid',
		type: 'show_grid',
		mds_nonce: MDS.mds_nonce,
		BID: bid,
	};

	jQuery(grid).load(MDS.ajaxurl, data, function () {
		mds_init('#theimage', true, MDS.ENABLE_MOUSEOVER !== 'NO', false, true);
	});
}

function mds_stats(container, bid, width, height) {
	if (jQuery('#' + container).length > 0) {
		return;
	}

	let stats = jQuery("<div class='stats-inner' id='" + container + "'></div>");
	stats.css('width', width).css('height', height);
	jQuery('.' + container).append(stats);

	const data = {
		action: 'mds_ajax',
		type: 'show_stats',
		mds_nonce: MDS.mds_nonce,
		BID: bid
	};

	jQuery(stats).load(MDS.ajaxurl, data, function () {
		mds_init('#' + container, false, false, false, false);
	});
}

function mds_list(container, bid, width, height) {
	if (jQuery('#' + container).length > 0) {
		return;
	}

	let list = jQuery("<div class='list-inner' id='" + container + "'></div>");
	list.css('width', width).css('height', height);
	jQuery('.' + container).append(list);

	const data = {
		action: 'mds_ajax',
		type: 'show_list',
		mds_nonce: MDS.mds_nonce,
		BID: bid
	};

	jQuery(list).load(MDS.ajaxurl, data, function () {
		mds_init('#' + container, false, true, 'list', false);
	});
}

function mds_users(container, bid, width, height) {
	if (jQuery('#' + container).length > 0) {
		return;
	}

	let users = jQuery("<div class='users-inner' id='" + container + "'></div>");
	users.css('width', width).css('height', height);
	jQuery('.' + container).append(users);

	const data = {
		action: 'mds_ajax',
		type: 'show_users',
		mds_nonce: MDS.mds_nonce,
		BID: bid
	};

	jQuery(users).load(MDS.ajaxurl, data, function () {
		mds_init('#' + container, false, true, false, false);
	});
}

function mds_load_ajax() {
	window.mds_shortcode_containers = jQuery('body').find('.mds-shortcode-container');

	if (window.mds_shortcode_containers.length === 0) {
		return;
	}

	for (let i = 0; i < window.mds_shortcode_containers.length; i++) {
		const $mds_shortcode_container = jQuery(window.mds_shortcode_containers[i]);
		const mds_data_attr = $mds_shortcode_container.attr('data-mds-params');
		if (mds_data_attr !== undefined) {
			const mds_data = JSON.parse(mds_data_attr);
			if (mds_data) {
				if (window.mds_shortcodes === undefined) {
					window.mds_shortcodes = [];
				}
				if (window.mds_shortcodes[mds_data.container_id] === undefined) {
					window.mds_shortcodes[mds_data.container_id] = true;
					new window.mds_ajax(mds_data.type, mds_data.container_id, mds_data.align, mds_data.width, mds_data.height, mds_data.id);
				}
			}
		}
	}
}

function updateTippyPosition() {
	if (window.tippy_instance && window.tippy_instance.popperInstance) {
		window.tippy_instance.popperInstance.update().then(() => {
		});
	}
}

function add_tippy() {
	const defaultContent = "<div class='ajax-loader'></div>";
	const isIOS = /iPhone|iPad|iPod/.test(navigator.platform);

	let delay = 50;
	if (MDS.TOOLTIP_TRIGGER === 'mouseenter') {
		delay = 400;
	}

	// Conditionally select areas based on page context
	let tooltipSelector = '.mds-container area,.list-link';
	if (jQuery('body').hasClass('mds-page-manage')) {
		// On the manage page, don't attach tooltips to areas
		tooltipSelector = '.list-link';
	}

	// Only initialize tippy if the selector is not empty
	if (tooltipSelector) {
		tippy(tooltipSelector, {
			theme: 'light',
			content: defaultContent,
			duration: 50,
			delay: delay,
			trigger: MDS.TOOLTIP_TRIGGER,
			allowHTML: true,
			followCursor: 'initial',
			hideOnClick: true,
			interactive: true,
			maxWidth: parseInt(MDS.MAX_POPUP_SIZE, 10),
			placement: 'auto',
			touch: true,
			appendTo: 'parent',
			zIndex: 99999,
			popperOptions: {
				strategy: 'absolute',
				modifiers: [
					{
						name: 'offset',
						options: {
							offset: [0, 10],
						},
					},
					{
						name: 'flip',
						options: {
							fallbackPlacements: ['bottom', 'right'],
						},
					},
					{
						name: 'preventOverflow',
						options: {
							altAxis: true,
							tether: false,
							boundary: document,
						},
					},
				],
			},
			onCreate(instance) {
				instance._isFetching = false;
				instance._content = null;
				instance._error = null;
				window.tippy_instance = instance;
			},
			onShow(instance) {
				if (instance._isFetching || instance._content || instance._error) {
					return;
				}

				if (isIOS) {
					jQuery(instance.reference).trigger('click');
				}

				instance._isFetching = true;

				const data = jQuery(instance.reference).data('data');

				const ajax_data = {
					action: 'mds_ajax',
					type: 'ga',
					mds_nonce: MDS.mds_nonce,
					aid: data.aid,
					bid: data.banner_id,
					block_id: data.block_id,
				};

				jQuery.ajax({
					method: 'POST',
					url: MDS.ajaxurl,
					data: ajax_data,
					dataType: 'html',
					crossDomain: true,
				}).done(function (data) {
					instance.setContent(data);
					instance._content = true;
				}).fail(function (jqXHR, textStatus, errorThrown) {
					instance._error = errorThrown;
					instance.setContent(`Request failed. ${errorThrown}`);
				}).always(function () {
					instance._isFetching = false;
					mds_load_ajax();
				});

			},
			onHidden(instance) {
				instance.setContent(defaultContent);
				instance._content = null;
				instance._error = null;
			}
		});

		window.is_touch = false;

		document.addEventListener('touchstart', function () {
			window.is_touch = true;
		});

		document.addEventListener('scroll', function () {
			if (!window.is_touch && window.tippy_instance != null && typeof window.tippy_instance.hide === 'function') {
				window.tippy_instance.hide();
			}
		});

		window.addEventListener('resize', function () {
			updateTippyPosition();
		});
	}
}

let rescaling = false;

function rescale($el) {

	if (rescaling) {
		return;
	}

	rescaling = true;

	// https://github.com/GestiXi/image-scale
	$el.imageScale({
		scale: "best-fit",
		align: "top",
		rescaleOnResize: true,
		hideParentOverflow: false,
		didScale: function (firstTime, options) {
			rescaling = false;
			updateTippyPosition();
		}
	});
}

function mds_loaded_event(el, scalemap, tippy, type, isgrid) {
	if (window.mds_loaded === true) {
		return;
	}
	window.mds_loaded = true;

	jQuery(document).trigger({
		type: 'mds-loaded',
		el: el,
		scalemap: scalemap,
		tippy: tippy,
		mdstype: type,
		isgrid: isgrid
	});
}

// Handle mds-loaded event
jQuery(document).on('mds-loaded', function (event) {
	// Add a delay before triggering resize, helps ensure rendering is complete
	setTimeout(function () {
		window.dispatchEvent(new Event('resize'));
	}, 150);
});

function mds_load_tippy(tippy, $el, scalemap, type, isgrid) {
	let tooltips_deferred = false; // Renamed to avoid confusion
	if (tippy && window.tippy_instance == undefined && MDS.ENABLE_MOUSEOVER !== 'NO') {
		console.log('[MDS DEBUG] Loading Tippy for element:', $el);
		tooltips_deferred = true;
		defer('Popper', () => {
			defer('tippy', () => {
				console.log('[MDS DEBUG] Tippy deferred, adding now.');
				add_tippy();
				// Don't call mds_loaded_event here, let mds_init handle it
			});
		});
	}
	return tooltips_deferred;
}

function mds_handle_mouseenter() {
	window.click_data = jQuery(this).data('data');
}

function mds_handle_click() {
	window.click_data = jQuery(this).data('data');

	if (MDS.ENABLE_MOUSEOVER === 'NO') {

		const ajax_data = {
			action: 'mds_ajax',
			type: 'click',
			mds_nonce: MDS.mds_nonce,
			aid: window.click_data.aid,
			bid: window.click_data.banner_id,
			block_id: window.click_data.block_id,
		};

		jQuery.ajax({
			method: 'POST',
			url: MDS.ajaxurl,
			data: ajax_data,
			dataType: 'html',
			crossDomain: true,
		}).done(function () {
			window.open(window.click_data.url, MDS.link_target);
		});
	}
}

function mds_handle_url_click() {
	const $link = jQuery(this);

	const ajax_data = {
		action: 'mds_ajax',
		type: 'click',
		mds_nonce: MDS.mds_nonce,
		aid: window.click_data.aid,
		bid: window.click_data.banner_id,
		block_id: window.click_data.block_id,
	};

	jQuery.ajax({
		method: 'POST',
		url: MDS.ajaxurl,
		data: ajax_data,
		dataType: 'html',
		crossDomain: true,
	}).done(function () {
		window.open($link.attr('href'), MDS.link_target);
	});
}

function mds_init(el, scalemap, tippy, type, isgrid) {
	console.log('[MDS DEBUG] mds_init called with:', {el, scalemap, tippy, type, isgrid});

	// Prevent re-initialization on the same element if needed
	const $el = jQuery(el);
	if ($el.data('mds-initialized')) {
		console.log('[MDS DEBUG] Element already initialized:', el);
		return;
	}
	$el.data('mds-initialized', true);

	if (!el || $el.length === 0) {
		console.error('[MDS ERROR] mds_init called with invalid element:', el);
		return;
	}

	// Add body class if it's the manage page - This seems redundant if done in PHP?
	// Check if this logic is still necessary here.
	/*
	if (type === 'manage') {
		jQuery('body').addClass('mds-page-manage');
		console.log('[MDS DEBUG] Added mds-page-manage body class.');
	}
	*/

	// Check if the element is visible
	if (!$el.is(':visible')) {
		console.warn('[MDS WARN] Element is not visible, delaying initialization:', el);
		// Optionally, use an Intersection Observer to initialize when visible
		// For now, just return or attempt delayed init
		// return;
	}

	try {
		console.log('[MDS DEBUG] Initializing ImageMap for element:', el);
		// Ensure ImageMap is available
		if (typeof ImageMap !== 'function') {
			console.error('[MDS ERROR] ImageMap function is not defined!');
			return;
		}
		ImageMap(el, scalemap); // Pass scalemap parameter
		console.log('[MDS DEBUG] ImageMap initialized for element:', el);

		mds_loaded_event($el, scalemap, tippy, type, isgrid);

		// Handle Tippy loading
		const tooltips_deferred = mds_load_tippy(tippy, $el, scalemap, type, isgrid);

	} catch (error) {
		console.error('[MDS ERROR] Error during mds_init:', error, 'for element:', el);
	}
}



// MDS ajax display method
window.mds_ajax = function (mds_type, mds_container_id, mds_align, mds_width, mds_height, grid_id) {
	var container = jQuery("#" + mds_container_id);

	container.width(mds_width).height(mds_height).css('max-width', '100%');
	switch (mds_align) {
		case "left":
			container.css('float', 'left');
			break;
		case "center":
			container.css('display', 'block').css('margin', '0 auto');
			break;
		case "right":
			container.css('float', 'right');
			break;
		default:
			break;
	}

	if (container.length > 0) {
		add_ajax_loader(container);

		// strip "json" parameter from URL
		const url = new URL(window.location);
		const params = new URLSearchParams(url.search);
		const jsonParam = params.get('json');
		if (jsonParam === '1') {
			params.delete('json');
			url.search = params.toString();
			window.history.replaceState({}, '', url.toString());
		}

		window.mds_ajax_request = jQuery.ajax({
			url: MDS.ajaxurl,
			data: {
				BID: grid_id,
				action: "mds_ajax",
				type: mds_type,
				mds_nonce: MDS.mds_nonce,
				get_params: JSON.stringify(Object.fromEntries(new URLSearchParams(window.location.search)))
			},
			type: "POST",
			dataType: "html",
			success: function (data) {
				remove_ajax_loader();

				if (mds_type !== 'payment') {
					jQuery(container).html(data);
				}

				if (mds_type === 'grid') {
					mds_init('#theimage', true, MDS.ENABLE_MOUSEOVER !== 'NO', false, true);
					let $el = jQuery('#theimage');
					$el.trigger('load');
				} else if (mds_type === 'list') {
					mds_init('#' + mds_container_id, false, true, 'list', false);
					let $el = jQuery('#' + mds_container_id);
					$el.trigger('load');
				} else if (mds_type === 'payment' || mds_type === 'confirm-order') {
					try {
						let parsed = JSON.parse(data);
						if (parsed.success === true) {
							if (parsed.data) {
								if (parsed.data.redirect) {
									jQuery(container).html(parsed.data.message);
									window.location = parsed.data.redirect;
								} else {
									jQuery(container).html(parsed.data);
								}
							}
						} else if (parsed.success === false) {
							if (parsed.data) {
								if (parsed.data.message) {
									jQuery(container).html(parsed.data.message);
								} else {
									jQuery(container).html(parsed.data);
								}
							}
						} else {
							console.log("Unknown data: ", parsed);
						}
					} catch (e) {
						jQuery(container).html(data);
					}
				} else {
					try {
						let parsed = JSON.parse(data);
						if (parsed.success === true) {
							if (parsed.data) {
								if (parsed.data.redirect) {
									jQuery(container).html(parsed.data.message);
									window.location = parsed.data.redirect;
								} else {
									jQuery(container).html(parsed.data);
								}
							}
						} else if (parsed.success === false) {
							if (parsed.data) {
								if (parsed.data.message) {
									jQuery(container).html(parsed.data.message);
								} else {
									jQuery(container).html(parsed.data);
								}
							}
						} else {
							console.log("Unknown data: ", parsed);
						}
					} catch (e) {
						jQuery(container).html(data);
					}
				}

				wp.hooks.doAction('mds_ajax_complete');
			},
			error: function (jqXHR, textStatus, errorThrown) {
				remove_ajax_loader();
			}
		});
	}
}

jQuery(window).on('load', function () {
	// Initialize the manage page grid if it exists (after other content is loaded)
	let $publishGrid = jQuery('#publish-grid');
	if ($publishGrid.length > 0) {
		// Initialize directly on window.load
		// Scaling (scalemap=true) is handled inside mds_init conditionally now
		mds_init('#publish-grid', true, false, false, true);
	}

	// init the front-end AJAX grids
	mds_load_ajax();
});

// Fallback if script loads after window.load: ensure publish-grid is initialized
jQuery(document).on('load', function () {
	let $publishGrid = jQuery('#publish-grid');
	if ($publishGrid.length > 0) {
		console.log('[MDS DEBUG] document.ready init publish-grid');
		mds_init('#publish-grid', true, false, false, true);
	}
});

// Login page functionality needs to run on document.ready, not window.load
// Keep it separate.
jQuery(document).on('load', function () {
	// login page functionality
	let $login_page = jQuery("body.login");
	if ($login_page.length > 0) {
		let $user_login = jQuery("#user_login");
		if ($user_login.length > 0) {
			$user_login.attr("placeholder", "Username or Email Address");
		}
	}
});

// Ajax loader
function mds_load_ajax() {
	window.mds_shortcode_containers = jQuery('body').find('.mds-shortcode-container');

	if (window.mds_shortcode_containers.length === 0) {
		return;
	}

	for (let i = 0; i < window.mds_shortcode_containers.length; i++) {
		const $mds_shortcode_container = jQuery(window.mds_shortcode_containers[i]);
		const mds_data_attr = $mds_shortcode_container.attr('data-mds-params');
		if (mds_data_attr !== undefined) {
			const mds_data = JSON.parse(mds_data_attr);
			if (mds_data) {
				if (window.mds_shortcodes === undefined) {
					window.mds_shortcodes = [];
				}
				if (window.mds_shortcodes[mds_data.container_id] === undefined) {
					window.mds_shortcodes[mds_data.container_id] = true;
					new window.mds_ajax(mds_data.type, mds_data.container_id, mds_data.align, mds_data.width, mds_data.height, mds_data.id);
				}
			}
		}
	}
}

function mds_update_package($form) {
	let $pack = get_selected_package();
	let $package_input = $form.find('input[name="package"]');
	if ($pack !== 0 && $package_input.length > 0) {
		$package_input.val($pack);
	}
}

jQuery(document).on('ajaxComplete', function (event, xhr, settings) {
	mds_load_ajax();

	jQuery('.mds_upload_image').on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		let $el = jQuery(this);
		$el.prop('disabled', true);
		$el.attr('value', MDS.UPLOADING);
		let $form = $el.parent('form');
		mds_update_package($form);
		$form.submit();
		return false;
	});

	jQuery('.mds_pointer_graphic').on('load', function (e) {
		jQuery('.mds_upload_image').prop('disabled', false);
		jQuery(this).attr('value', 'Upload');
	});

	jQuery('.mds_save_ad_button').on('click', function () {
		let $el = jQuery(this);
		$el.prop('disabled', true);
		$el.attr('value', MDS.SAVING);
		$el.closest('form').submit();
	});

	jQuery('#mds-complete-button').on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		let $el = jQuery(this);
		$el.prop('disabled', true);
		$el.attr('value', MDS.COMPLETING);
		window.location = MDS.manageurl + '?mds-action=complete&order_id=' + $el.data('order-id') + '&BID=' + $el.data('grid');
		return false;
	});

	jQuery('#mds-confirm-button').on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		let $el = jQuery(this);
		$el.prop('disabled', true);
		$el.attr('value', MDS.CONFIRMING);
		window.location = MDS.paymenturl + '?mds-action=confirm&order_id=' + $el.data('order-id') + '&BID=' + $el.data('grid');
		return false;
	});

	jQuery('.mds-write.big_button').on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		let $form = jQuery(this).closest('form');
		mds_update_package($form);
		$form.submit();
		jQuery(this).prop('disabled', true).attr('value', MDS.WAIT);
		return false;
	});

	jQuery('.mds-button').on('click', function () {
		jQuery('.mds-button').prop('disabled', true).attr('value', MDS.WAIT);
	});

	jQuery('.mds-edit-button').on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		jQuery('.mds-edit-button').prop('disabled', true).attr('value', MDS.WAIT);

		const $el = jQuery(this);
		const sep = MDS.manageurl.indexOf('?') !== -1 ? '&' : '?';
		window.location = MDS.manageurl + sep + 'mds-action=manage&aid=' + $el.data('pixel-id') + '&json=1';

		return false;
	});

	jQuery('.mds-upload-submit').on('click', function () {
		jQuery(this).attr('value', MDS.UPLOADING).prop('disabled', true);
		jQuery(this).closest('form').append(jQuery('<input>').attr({
			type: 'hidden',
			name: this.name,
			value: this.value
		}));
		jQuery(this).closest('form').submit();
		return true;
	});

	jQuery('.mds-pixels-list').accordion({
		header: ".mds-manage-row",
		collapsible: true,
		active: 0,
	});
});

function mdsToggleMenu() {
	const menu = document.getElementById("mds-users-menu");
	menu.classList.toggle('mds-show-menu');
}

function confirmLink(theLink, theConfirmMsg) {

	if (theConfirmMsg === '') {
		return true;
	}

	const is_confirmed = confirm(theConfirmMsg + '\n');
	if (is_confirmed) {
		theLink.href += '&is_js_confirmed=1';
	}

	return is_confirmed;
}
