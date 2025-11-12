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

// Initialize MDS hooks system if not already present
if (typeof window.MDS === 'undefined') {
	window.MDS = {};
}
if (typeof window.MDS.hooks === 'undefined') {
	window.MDS.hooks = {};
}

function mdsIsAbsoluteUrl(url) {
	return typeof url === 'string' && /^([a-z][a-z0-9+\.-]*:)?\/\//i.test(url);
}

function mdsFindPluginBaseUrl() {
	if (typeof window.MDS_PLUGIN_BASE_URL === 'string' && window.MDS_PLUGIN_BASE_URL.length > 0) {
		return window.MDS_PLUGIN_BASE_URL;
	}
	if (typeof window.MDS !== 'undefined' && typeof window.MDS.MDS_BASE_URL === 'string' && window.MDS.MDS_BASE_URL.length > 0) {
		window.MDS_PLUGIN_BASE_URL = window.MDS.MDS_BASE_URL;
		return window.MDS_PLUGIN_BASE_URL;
	}
	var scripts = document.getElementsByTagName('script');
	for (var i = 0; i < scripts.length; i++) {
		var src = scripts[i].getAttribute('src');
		if (!src) {
			continue;
		}
		var match = src.match(/(.*\/milliondollarscript-two\/)(?:src\/Assets\/js\/|src\/Assets\/)/i);
		if (match && match[1]) {
			window.MDS_PLUGIN_BASE_URL = match[1];
			return window.MDS_PLUGIN_BASE_URL;
		}
	}
	return typeof window.MDS_PLUGIN_BASE_URL === 'string' && window.MDS_PLUGIN_BASE_URL.length > 0 ? window.MDS_PLUGIN_BASE_URL : null;
}

function mdsEnsureAbsoluteUrl(url) {
	if (!url) {
		return null;
	}
	if (mdsIsAbsoluteUrl(url)) {
		return url;
	}
	if (url.indexOf('//') === 0) {
		return window.location.protocol + url;
	}
	var pluginBase = mdsFindPluginBaseUrl();
	if (pluginBase) {
		try {
			return new URL(url.replace(/^\//, ''), pluginBase).href;
		} catch (e) {}
	}
	try {
		return new URL(url, window.location.origin + '/').href;
	} catch (e) {
		return null;
	}
}

function mdsResolveLoaderUrl(preferred) {
	var candidates = [];
	if (preferred) {
		candidates.push(preferred);
	}
	if (typeof window.MDS_LOADER_URL === 'string' && window.MDS_LOADER_URL.length > 0) {
		candidates.push(window.MDS_LOADER_URL);
	}
	if (typeof window.MDS_OBJECT !== 'undefined' && typeof window.MDS_OBJECT.loader_url === 'string' && window.MDS_OBJECT.loader_url.length > 0) {
		candidates.push(window.MDS_OBJECT.loader_url);
	}
	if (typeof window.MDS !== 'undefined' && typeof window.MDS.MDS_BASE_URL === 'string' && window.MDS.MDS_BASE_URL.length > 0) {
		candidates.push(window.MDS.MDS_BASE_URL + 'src/Assets/images/ajax-loader.gif');
	}
	var pluginBase = mdsFindPluginBaseUrl();
	if (pluginBase) {
		candidates.push(pluginBase + 'src/Assets/images/ajax-loader.gif');
	}
	for (var i = 0; i < candidates.length; i++) {
		var absolute = mdsEnsureAbsoluteUrl(candidates[i]);
		if (absolute) {
			window.MDS_LOADER_URL = absolute;
			return absolute;
		}
	}
	if (pluginBase) {
		var fallback = mdsEnsureAbsoluteUrl(pluginBase + 'src/Assets/images/ajax-loader.gif');
		if (fallback) {
			window.MDS_LOADER_URL = fallback;
			return fallback;
		}
	}
	return null;
}

window.MDSFindPluginBaseUrl = mdsFindPluginBaseUrl;
window.MDSResolveLoaderUrl = mdsResolveLoaderUrl;

if (typeof window.MDS_LOADER_URL === 'undefined' && typeof window.MDS !== 'undefined' && window.MDS.MDS_BASE_URL) {
	window.MDS_LOADER_URL = window.MDS.MDS_BASE_URL + 'src/Assets/images/ajax-loader.gif';
}

function add_ajax_loader(container) {
	const loaderSrc = mdsResolveLoaderUrl();
	const spinnerMarkup = loaderSrc
		? "<img class='ajax-loader__spinner' src='" + loaderSrc + "' alt='Loading' width='32' height='32'/>"
		: "<span class='ajax-loader__spinner'></span>";
	let $ajax_loader = jQuery(
		"<div class='ajax-loader' role='status' aria-live='polite'>" + spinnerMarkup + "</div>",
	);
	jQuery(container).append($ajax_loader);
}

function remove_ajax_loader(container) {
	if (container) {
		const $containers = container instanceof jQuery ? container : jQuery(container);
		$containers.each(function () {
			jQuery(this).find('.ajax-loader').remove();
		});
	} else {
		jQuery(".ajax-loader").remove();
	}
}

var initialized = false;

// @link https://stackoverflow.com/a/58514043/311458
function defer(toWaitFor, method) {
	if (window[toWaitFor]) {
		method();
	} else {
		setTimeout(function () {
			defer(toWaitFor, method);
		}, 50);
	}
}

function mds_grid(container, bid, width, height) {
	if (jQuery("#" + container).length > 0) {
		return;
	}

	add_ajax_loader("." + container);

	let grid = jQuery("<div class='grid-inner' id='" + container + "'></div>");
	grid.css("width", width).css("height", height);
	jQuery("." + container).append(grid);

	const data = {
		action: "mds_ajax_grid",
		type: "show_grid",
		mds_nonce: MDS.mds_nonce,
		BID: bid,
	};

	jQuery(grid).load(MDS.ajaxurl, data, function (responseText, textStatus) {
		remove_ajax_loader();
		if (textStatus !== "error") {
			mds_init("#theimage", true, MDS.ENABLE_MOUSEOVER !== "NO", false, true);
		}
	});
}

function mds_stats(container, bid, width, height) {
	if (jQuery("#" + container).length > 0) {
		return;
	}

	let stats = jQuery("<div class='stats-inner' id='" + container + "'></div>");
	stats.css("width", width).css("height", height);
	jQuery("." + container).append(stats);

	const data = {
		action: "mds_ajax",
		type: "show_stats",
		mds_nonce: MDS.mds_nonce,
		BID: bid,
	};

	jQuery(stats).load(MDS.ajaxurl, data, function () {
		mds_init("#" + container, false, false, false, false);
	});
}

function mds_list(container, bid, width, height) {
	if (jQuery("#" + container).length > 0) {
		return;
	}

	let list = jQuery("<div class='list-inner' id='" + container + "'></div>");
	list.css("width", width).css("height", height);
	jQuery("." + container).append(list);

	const data = {
		action: "mds_ajax",
		type: "show_list",
		mds_nonce: MDS.mds_nonce,
		BID: bid,
	};

	jQuery(list).load(MDS.ajaxurl, data, function () {
		mds_init("#" + container, false, true, "list", false);
	});
}

function mds_users(container, bid, width, height) {
	if (jQuery("#" + container).length > 0) {
		return;
	}

	let users = jQuery("<div class='users-inner' id='" + container + "'></div>");
	users.css("width", width).css("height", height);
	jQuery("." + container).append(users);

	const data = {
		action: "mds_ajax",
		type: "show_users",
		mds_nonce: MDS.mds_nonce,
		BID: bid,
	};

	jQuery(users).load(MDS.ajaxurl, data, function () {
		mds_init("#" + container, false, true, false, false);
	});
}

function mds_load_ajax() {
	window.mds_shortcode_containers = jQuery("body").find(
		".mds-shortcode-container",
	);

	if (window.mds_shortcode_containers.length === 0) {
		return;
	}

	for (let i = 0; i < window.mds_shortcode_containers.length; i++) {
		const $mds_shortcode_container = jQuery(window.mds_shortcode_containers[i]);
		const mds_data_attr = $mds_shortcode_container.attr("data-mds-params");
		if (mds_data_attr !== undefined) {
			const mds_data = JSON.parse(mds_data_attr);
			if (mds_data) {
				if (window.mds_shortcodes === undefined) {
					window.mds_shortcodes = [];
				}
				if (window.mds_shortcodes[mds_data.container_id] === undefined) {
					window.mds_shortcodes[mds_data.container_id] = true;
					new window.mds_ajax(
						mds_data.type,
						mds_data.container_id,
						mds_data.align,
						mds_data.width,
						mds_data.height,
						mds_data.id,
						mds_data,
					);
				}
			}
		}
	}
}

function updateTippyPosition() {
	if (window.tippy_instance && window.tippy_instance.popperInstance) {
		window.tippy_instance.popperInstance.update().then(() => {});
	}
}

/**
 * Safely parse the data payload stored on an <area> element.
 *
 * Some grids include legacy popup text that contains raw backslashes which
 * aren't valid JSON escape sequences (for example "\WP_Post"). When that
 * happens, jQuery leaves the raw string untouched and our tooltip logic never
 * receives the structured data it expects. This helper falls back to the
 * original attribute value, sanitises unsupported escape sequences, and caches
 * the parsed object for reuse.
 *
 * @param {jQuery} $element area element wrapped in jQuery
 * @returns {object|null} decoded data payload or null if parsing fails
 */
function mdsParseAreaData($element) {
	if (!$element || $element.length === 0) {
		return null;
	}

	let data = $element.data("data");

	if (data && typeof data === "object") {
		return data;
	}

	let raw = "";
	if (typeof data === "string" && data.trim().length > 0) {
		raw = data;
	} else {
		raw = $element.attr("data-data") || "";
	}

	if (!raw || typeof raw !== "string") {
		return null;
	}

	const tryParse = (candidate) => {
		try {
			return JSON.parse(candidate);
		} catch (error) {
			return null;
		}
	};

	let parsed = tryParse(raw);

	if (!parsed) {
		const sanitised = raw.replace(/\\(?!["\\/bfnrtu])/g, "\\\\");
		parsed = tryParse(sanitised);

		if (!parsed) {
			console.error(
				"MDS Tooltip: Unable to parse data-data payload for tooltip",
				{ raw },
			);
			return null;
		}
	}

	if (parsed && typeof parsed === "object") {
		$element.data("data", parsed);
		return parsed;
	}

	return null;
}

function add_tippy() {
	const defaultContent =
		"<div class='ajax-loader' role='status' aria-live='polite'><span class='ajax-loader__spinner' aria-hidden='true'></span></div>";
	const isIOS = /iPhone|iPad|iPod/.test(navigator.platform);

	let delay = 50;
	if (MDS.TOOLTIP_TRIGGER === "mouseenter") {
		delay = 400;
	}

	// Selector for tippy tooltips
	let tooltipSelector = ".mds-container area,.list-link";

	// Reset any previous click handlers on areas before adding new ones
	jQuery("area").off("click.mds-tippy");

	// Use a delegated event handler that's more reliable with dynamically loaded content
	jQuery(document)
		.off("click.mds-tippy", "area")
		.on("click.mds-tippy", "area", function (e) {
			// Ignore non-primary clicks so context menu/middle-click still work as expected
			if (typeof e.button !== "undefined" && e.button !== 0) {
				return true;
			}
			if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
				return true;
			}
			// If no tippy instance or content isn't loaded yet, prevent the default navigation
			if (!this._tippy || !this._tippy._content) {
				e.preventDefault();
				e.stopPropagation();

				// Check for valid data attributes that are needed for tooltips
				const $this = jQuery(this);
				let tippyData = mdsParseAreaData($this);

				// If data attributes are missing, try to extract them from attributes
				if (!tippyData || !tippyData.banner_id || !tippyData.block_id) {
					// Extract from area attributes if available (for backward compatibility)
					const href = $this.attr("href") || "";
					const matches =
						href.match(/bid=(\d+).*?aid=(\d+).*?block_id=(\d+)/i) ||
						href.match(/aid=(\d+).*?bid=(\d+).*?block_id=(\d+)/i);

					if (matches) {
						// Create data object from URL parameters
						tippyData = {
							banner_id: parseInt(matches[1] || matches[2], 10),
							aid: parseInt(matches[2] || matches[1], 10),
							block_id: parseInt(matches[3], 10),
						};

						// Store the data for future use
						$this.data("data", tippyData);
					}
				}

				// If tippy isn't initialized yet on this element, initialize it
				if (!this._tippy && typeof tippy === "function") {
					tippy(this, {
						theme: "light",
						content:
							"<div class='ajax-loader' role='status' aria-live='polite'><span class='ajax-loader__spinner' aria-hidden='true'></span></div>",
						duration: 50,
						delay: MDS.TOOLTIP_TRIGGER === "mouseenter" ? 400 : 50,
						trigger: MDS.TOOLTIP_TRIGGER,
						onTrigger: mdsTippyOnTrigger,
						allowHTML: true,
						followCursor: "initial",
						hideOnClick: true,
						interactive: true,
						maxWidth: parseInt(MDS.MAX_POPUP_SIZE, 10),
						placement: "auto",
						touch: true,
						appendTo: "parent",
						zIndex: 99999,
					});
					this._tippy.show();
				}
				return false;
			}
		});

	// Only initialize tippy if the selector is not empty
	if (tooltipSelector) {
		tippy(tooltipSelector, {
			theme: "light",
			content: defaultContent,
			duration: 50,
			delay: delay,
			trigger: MDS.TOOLTIP_TRIGGER,
			onTrigger: mdsTippyOnTrigger,
			allowHTML: true,
			followCursor: "initial",
			hideOnClick: true,
			interactive: true,
			maxWidth: parseInt(MDS.MAX_POPUP_SIZE, 10),
			placement: "auto",
			touch: true,
			appendTo: "parent",
			zIndex: 99999,
			popperOptions: {
				strategy: "absolute",
				modifiers: [
					{
						name: "offset",
						options: {
							offset: [0, 10],
						},
					},
					{
						name: "flip",
						options: {
							fallbackPlacements: ["bottom", "right"],
						},
					},
					{
						name: "preventOverflow",
						options: {
							altAxis: true,
							tether: false,
							boundary: document,
						},
					},
				],
			},
			// Initialize tooltip instance properties
			onCreate(instance) {
				instance._isFetching = false;
				instance._content = null;
				instance._error = null;

				// Store the instance in a local property instead of overwriting global one
				// This prevents issues with multiple grids where the last one would overwrite others
				instance._initialized = true;
			},
			// Handle tooltip show event - load content via AJAX
			onShow(instance) {
				// Skip if we're already fetching, have content, or encountered an error
				if (instance._isFetching || instance._content || instance._error) {
					return;
				}

				// Special handling for iOS devices
				if (isIOS) {
					jQuery(instance.reference).trigger("click");
				}

				// Mark as fetching to prevent duplicate requests
				instance._isFetching = true;

				// Get the data from the area element
				const $reference = jQuery(instance.reference);
				const data = mdsParseAreaData($reference);

				// Check if we have valid data
				if (!data || !data.banner_id || !data.block_id) {
					const mapName = $reference.closest("map").attr("id");
					console.error(
						"MDS Tooltip: Missing data attributes for tooltip on " + mapName,
					);
					instance.setContent("Error: Missing data for this block");
					instance._error = "missing_data";
					instance._isFetching = false;
					return;
				}

				// Prepare AJAX request data
				const ajax_data = {
					action: "mds_ajax",
					type: "ga",
					mds_nonce: MDS.mds_nonce,
					aid: data.aid || 0,
					bid: data.banner_id,
					block_id: data.block_id,
				};

				// Make the AJAX request to get tooltip content
				jQuery
					.ajax({
						method: "POST",
						url: MDS.ajaxurl,
						data: ajax_data,
						dataType: "html",
						crossDomain: true,
					})
					.done(function (responseData) {
						// Update tooltip with the loaded content
						instance.setContent(responseData);
						instance._content = true;
						
						// Trigger custom event for extensions to modify tooltip content
						if (window.MDS && window.MDS.hooks && window.MDS.hooks.afterTooltipContentLoaded) {
							window.MDS.hooks.afterTooltipContentLoaded({
								instance: instance,
								data: data,
								responseData: responseData
							});
						}
					})
					.fail(function (jqXHR, textStatus, errorThrown) {
						// Handle AJAX failure
						instance._error = errorThrown;
						instance.setContent(`Request failed. ${errorThrown}`);
					})
					.always(function () {
						// Always mark fetching as complete
						instance._isFetching = false;
						// Reload any dynamic content in the tooltip
						mds_load_ajax();
					});
			},
			// Reset tooltip content when it's hidden
			onHidden(instance) {
				instance.setContent(defaultContent);
				instance._content = null;
				instance._error = null;
			},
		});

		// Track touch interactions to improve tooltip behavior
		window.is_touch = false;

		// Detect touch devices
		document.addEventListener("touchstart", function () {
			window.is_touch = true;
		});

		// Hide tooltips on scroll to improve performance
		document.addEventListener("scroll", function () {
			if (!window.is_touch) {
				// Hide all active tooltips on scroll (more efficient than hiding individual ones)
				tippy.hideAll();
			}
		});

		// Update tooltip positions on window resize
		window.addEventListener("resize", function () {
			updateTippyPosition();
		});
	}
}

// Helper function for onTrigger to prevent default link navigation on click
function mdsTippyOnTrigger(instance, event) {
	// Prevent default navigation if the event is a click
	// and 'click' is part of the configured triggers.
	if (
		event.type === "click" &&
		MDS.TOOLTIP_TRIGGER &&
		MDS.TOOLTIP_TRIGGER.includes("click")
	) {
		event.preventDefault();
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
		},
	});
}

function mds_loaded_event(el, scalemap, tippy, type, isgrid) {
	if (window.mds_loaded === true) {
		return;
	}
	window.mds_loaded = true;

	jQuery(document).trigger({
		type: "mds-loaded",
		el: el,
		scalemap: scalemap,
		tippy: tippy,
		mdstype: type,
		isgrid: isgrid,
	});
}

// Handle mds-loaded event
jQuery(document).on("mds-loaded", function (event) {
	// Add a delay before triggering resize, helps ensure rendering is complete
	setTimeout(function () {
		window.dispatchEvent(new Event("resize"));
	}, 150);
});

function mds_load_tippy(tippy, $el, scalemap, type, isgrid) {
	let tooltips_deferred = false; // Renamed to avoid confusion
	if (
		tippy &&
		window.tippy_instance == undefined &&
		MDS.ENABLE_MOUSEOVER !== "NO"
	) {
		tooltips_deferred = true;
		defer("Popper", () => {
			defer("tippy", () => {
				add_tippy();
				// Don't call mds_loaded_event here, let mds_init handle it
			});
		});
	}
	return tooltips_deferred;
}

function mds_handle_mouseenter() {
	const data = mdsParseAreaData(jQuery(this));
	if (data) {
		window.click_data = data;
	}
}

function mds_handle_click() {
	const data = mdsParseAreaData(jQuery(this));
	if (!data) {
		console.error("MDS Tooltip: Click handler missing area data payload.");
		return;
	}

	window.click_data = data;

	if (MDS.ENABLE_MOUSEOVER === "NO") {
		const ajax_data = {
			action: "mds_ajax",
			type: "click",
			mds_nonce: MDS.mds_nonce,
			aid: window.click_data.aid,
			bid: window.click_data.banner_id,
			block_id: window.click_data.block_id,
		};

		jQuery
			.ajax({
				method: "POST",
				url: MDS.ajaxurl,
				data: ajax_data,
				dataType: "html",
				crossDomain: true,
			})
			.done(function () {
				window.open(window.click_data.url, MDS.link_target);
			});
	}
}

function mds_handle_url_click() {
	const $link = jQuery(this);

	const ajax_data = {
		action: "mds_ajax",
		type: "click",
		mds_nonce: MDS.mds_nonce,
		aid: window.click_data.aid,
		bid: window.click_data.banner_id,
		block_id: window.click_data.block_id,
	};

	jQuery
		.ajax({
			method: "POST",
			url: MDS.ajaxurl,
			data: ajax_data,
			dataType: "html",
			crossDomain: true,
		})
		.done(function () {
			window.open($link.attr("href"), MDS.link_target);
		});
}

function setupPublicGridImageRecovery(imageEl) {
	if (!imageEl || imageEl.dataset.mdsFeedbackAttached === '1') {
		return;
	}
	imageEl.dataset.mdsFeedbackAttached = '1';

	const frame = imageEl.closest('.mds-grid-frame');
	if (!frame) {
		return;
	}

	const feedback = frame.querySelector('.mds-grid-feedback');
	const preloader = frame.querySelector('.mds-grid-preloader');
	if (!feedback || !preloader) {
		return;
	}

	const retryButton = feedback.querySelector('.mds-grid-feedback__retry');
	const baseSrc = imageEl.getAttribute('data-grid-src') || imageEl.getAttribute('src') || '';

	const hideFeedback = () => {
		feedback.setAttribute('hidden', 'hidden');
		feedback.classList.remove('is-visible');
	};

	const ensurePreloaderSpinner = () => {
		if (!preloader.children.length) {
			const loaderSrc = preloader.getAttribute('data-loader-src');
			const markup = loaderSrc
				? `<img class="mds-grid-preloader__spinner" src="${loaderSrc}" alt="" aria-hidden="true" width="32" height="32"/>`
				: '<span class="mds-grid-preloader__spinner" aria-hidden="true"></span>';
			preloader.innerHTML = markup;
		}
	};

	const showPreloader = (options = {}) => {
		ensurePreloaderSpinner();
		preloader.removeAttribute('hidden');
		if (options.hideImage !== false) {
			hideImage();
		}
	};

	const hidePreloader = () => {
		preloader.setAttribute('hidden', 'hidden');
		preloader.innerHTML = '';
	};

	const hideImage = () => {
		imageEl.classList.add('mds-grid-image--hidden');
	};

	const showImage = () => {
		imageEl.classList.remove('mds-grid-image--hidden');
	};

	const showFeedback = () => {
		hidePreloader();
		feedback.removeAttribute('hidden');
		feedback.classList.add('is-visible');
		hideImage();
	};

	const handleLoad = () => {
		if (imageEl.naturalWidth === 0 || imageEl.naturalHeight === 0) {
			showFeedback();
			return;
		}
		hidePreloader();
		hideFeedback();
		showImage();
	};

	const handleError = () => {
		showFeedback();
	};

	imageEl.mdsGridControls = {
		showPreloader,
		hidePreloader,
		showFeedback,
		hideFeedback,
		hideImage,
		showImage,
	};

	imageEl.addEventListener('load', handleLoad);
	imageEl.addEventListener('error', handleError);
	imageEl.addEventListener('abort', handleError);

	if (retryButton) {
		retryButton.addEventListener('click', function (event) {
			event.preventDefault();
			hideFeedback();
			showPreloader();
			const separator = baseSrc.indexOf('?') === -1 ? '?' : '&';
			imageEl.src = baseSrc + separator + '_mds_retry=' + Date.now();
		});
	}

	if (imageEl.complete) {
		if (imageEl.naturalWidth === 0 || imageEl.naturalHeight === 0) {
			handleError();
		} else {
			handleLoad();
		}
	} else {
		hideFeedback();
		showPreloader();
	}
}

function mds_init(el, scalemap, tippy, type, isgrid) {

	// Prevent re-initialization on the same element if needed
	const $el = jQuery(el);
	if ($el.data("mds-initialized")) {
		return;
	}
	$el.data("mds-initialized", true);

	if (!el || $el.length === 0) {
		console.error("[MDS ERROR] mds_init called with invalid element:", el);
		return;
	}

	// Check if the element is visible
	if (!$el.is(":visible")) {
		console.warn(
			"[MDS WARN] Element is not visible, delaying initialization:",
			el,
		);
		// Optionally, use an Intersection Observer to initialize when visible
		// For now, just return or attempt delayed init
		// return;
	}

	try {
		const domEl = $el.get(0);
		if (domEl && domEl.tagName === 'IMG') {
			setupPublicGridImageRecovery(domEl);
		}
		// Ensure ImageMap is available
		if (typeof ImageMap !== "function") {
			console.error("[MDS ERROR] ImageMap function is not defined!");
			return;
		}

		// Initialize ImageMap after image loads to ensure proper dimensions
		const initImageMap = function() {
			ImageMap(el, scalemap); // Pass scalemap parameter
			mds_loaded_event($el, scalemap, tippy, type, isgrid);

			// Handle Tippy loading
			const tooltips_deferred = mds_load_tippy(
				tippy,
				$el,
				scalemap,
				type,
				isgrid,
			);
		};

		// Check if image is already loaded
		if (domEl && domEl.tagName === 'IMG') {
			if (domEl.complete && domEl.naturalWidth > 0) {
				// Image already loaded, initialize immediately
				initImageMap();
			} else {
				// Wait for image to load
				$el.on('load', function() {
					initImageMap();
				});
				// Also set error handler to prevent hanging
				$el.on('error', function() {
					console.warn('[MDS WARN] Image failed to load, initializing ImageMap anyway');
					initImageMap();
				});
			}
		} else {
			// Not an image element, initialize immediately
			initImageMap();
		}
	} catch (error) {
		console.error(
			"[MDS ERROR] Error during mds_init:",
			error,
			"for element:",
			el,
		);
	}
}

// MDS ajax display method
window.mds_ajax = function (
	mds_type,
	mds_container_id,
	mds_align,
	mds_width,
	mds_height,
	grid_id,
	options,
) {
	var container = jQuery("#" + mds_container_id);

	container.width(mds_width).height(mds_height).css("max-width", "100%");
	switch (mds_align) {
		case "left":
			container.css("float", "left");
			break;
		case "center":
			container.css("display", "block").css("margin", "0 auto");
			break;
		case "right":
			container.css("float", "right");
			break;
		default:
			break;
	}

	if (container.length > 0) {
		add_ajax_loader(container);

		const opts = options || {};
		const listShowAll = Boolean(opts.list_show_all);
		const explicitId = Boolean(opts.explicit_id);
		let requestBid = typeof grid_id !== "undefined" && grid_id !== null ? grid_id : opts.id;
		if (requestBid === undefined || requestBid === null || requestBid === "") {
			requestBid = 0;
		}
		if (listShowAll && !explicitId) {
			requestBid = 0;
		}

		// strip "json" parameter from URL
		const url = new URL(window.location);
		const params = new URLSearchParams(url.search);
		const jsonParam = params.get("json");
		if (jsonParam === "1") {
			params.delete("json");
			url.search = params.toString();
			window.history.replaceState({}, "", url.toString());
		}

		const requestData = {
			BID: requestBid,
			action: "mds_ajax",
			type: mds_type,
			mds_nonce: MDS.mds_nonce,
			get_params: JSON.stringify(
				Object.fromEntries(new URLSearchParams(window.location.search)),
			),
		};

		if (Object.prototype.hasOwnProperty.call(opts, "id")) {
			requestData.mds_shortcode_id = opts.id;
		}

		if (listShowAll) {
			requestData.mds_list_show_all = "1";
		}

		if (explicitId) {
			requestData.mds_explicit_id = "1";
		}

		window.mds_ajax_request = jQuery.ajax({
			url: MDS.ajaxurl,
			data: requestData,
			type: "POST",
			dataType: "html",
			success: function (data) {
				remove_ajax_loader();

				if (mds_type !== "payment") {
					jQuery(container).html(data);
				}

				if (mds_type === "grid") {
					mds_init(
						"#theimage",
						true,
						MDS.ENABLE_MOUSEOVER !== "NO",
						false,
						true,
					);
					let $el = jQuery("#theimage");
					$el.trigger("load");
				} else if (mds_type === "list") {
					mds_init("#" + mds_container_id, false, true, "list", false);
					let $el = jQuery("#" + mds_container_id);
					$el.trigger("load");
				} else if (mds_type === "payment" || mds_type === "confirm-order") {
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

				wp.hooks.doAction("mds_ajax_complete");
			},
			error: function (jqXHR, textStatus, errorThrown) {
				remove_ajax_loader();
			},
		});
	}
};

jQuery(window).on("load", function () {
	// Initialize the manage page grid if it exists (after other content is loaded)
	let $publishGrid = jQuery("#publish-grid");
	if ($publishGrid.length > 0) {
		// Initialize directly on window.load
		// Scaling (scalemap=true) is handled inside mds_init conditionally now
		mds_init("#publish-grid", true, false, false, true);
	}

	// init the front-end AJAX grids
	mds_load_ajax();
});

// Fallback if script loads after window.load: ensure publish-grid is initialized
jQuery(document).on("load", function () {
	let $publishGrid = jQuery("#publish-grid");
	if ($publishGrid.length > 0) {
		mds_init("#publish-grid", true, false, false, true);
	}
});

// Login page functionality needs to run on document.ready, not window.load
// Keep it separate.
jQuery(document).on("load", function () {
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
	window.mds_shortcode_containers = jQuery("body").find(
		".mds-shortcode-container",
	);

	if (window.mds_shortcode_containers.length === 0) {
		return;
	}

	for (let i = 0; i < window.mds_shortcode_containers.length; i++) {
		const $mds_shortcode_container = jQuery(window.mds_shortcode_containers[i]);
		const mds_data_attr = $mds_shortcode_container.attr("data-mds-params");
		if (mds_data_attr !== undefined) {
			const mds_data = JSON.parse(mds_data_attr);
			if (mds_data) {
				if (window.mds_shortcodes === undefined) {
					window.mds_shortcodes = [];
				}
				if (window.mds_shortcodes[mds_data.container_id] === undefined) {
					window.mds_shortcodes[mds_data.container_id] = true;
					new window.mds_ajax(
						mds_data.type,
						mds_data.container_id,
						mds_data.align,
						mds_data.width,
						mds_data.height,
						mds_data.id,
						mds_data,
					);
				}
			}
		}
	}
}

function get_selected_package() {
	// Check if jQuery is available and the package dropdown exists
	if (typeof jQuery !== "undefined" && jQuery("#pack-grid").length > 0) {
		return jQuery("#pack-grid").val();
	}
	// Default return for simple mode or when no package dropdown exists
	return 0;
}

function mds_update_package($form) {
	let $pack = get_selected_package();
	let $package_input = $form.find('input[name="package"]');
	if ($pack !== 0 && $package_input.length > 0) {
		$package_input.val($pack);
	}
}

jQuery(document).on("ajaxComplete", function (event, xhr, settings) {
	mds_load_ajax();

	jQuery(".mds_upload_image").on("click", function (e) {
		e.preventDefault();
		e.stopPropagation();
		let $el = jQuery(this);
		$el.prop("disabled", true);
		$el.attr("value", MDS.UPLOADING);
		let $form = $el.parent("form");
		mds_update_package($form);
		$form.submit();
		return false;
	});

	jQuery(".mds_pointer_graphic").on("load", function (e) {
		jQuery(".mds_upload_image").prop("disabled", false);
		jQuery(this).attr("value", "Upload");
	});

	jQuery(".mds_save_ad_button").on("click", function () {
		let $el = jQuery(this);
		$el.prop("disabled", true);
		$el.attr("value", MDS.SAVING);
		$el.closest("form").submit();
	});

	jQuery("#mds-complete-button").on("click", function (e) {
		e.preventDefault();
		e.stopPropagation();
		let $el = jQuery(this);
		$el.prop("disabled", true);
		$el.attr("value", MDS.COMPLETING);
		window.location =
			MDS.manageurl +
			"?mds-action=complete&order_id=" +
			$el.data("order-id") +
			"&BID=" +
			$el.data("grid");
		return false;
	});

	jQuery("#mds-confirm-button").on("click", function (e) {
		e.preventDefault();
		e.stopPropagation();
		let $el = jQuery(this);
		$el.prop("disabled", true);
		$el.attr("value", MDS.CONFIRMING);
		window.location =
			MDS.paymenturl +
			"?mds-action=confirm&order_id=" +
			$el.data("order-id") +
			"&BID=" +
			$el.data("grid");
		return false;
	});

	jQuery(".mds-write.big_button").on("click", function (e) {
		e.preventDefault();
		e.stopPropagation();
		let $form = jQuery(this).closest("form");
		mds_update_package($form);
		$form.submit();
		jQuery(this).prop("disabled", true).attr("value", MDS.WAIT);
		return false;
	});

	jQuery(".mds-button").on("click", function () {
		const $btn = jQuery(this);
		if ($btn.is(":submit") && $btn.closest("form").length) {
			return;
		}
		jQuery(".mds-button").prop("disabled", true).attr("value", MDS.WAIT);
	});

	jQuery(".mds-edit-button").on("click", function (e) {
		e.preventDefault();
		e.stopPropagation();
		jQuery(".mds-edit-button").prop("disabled", true).attr("value", MDS.WAIT);

		const $el = jQuery(this);
		const sep = MDS.manageurl.indexOf("?") !== -1 ? "&" : "?";
		window.location =
			MDS.manageurl +
			sep +
			"mds-action=manage&aid=" +
			$el.data("pixel-id") +
			"&json=1";

		return false;
	});

	jQuery(".mds-upload-submit").on("click", function () {
		jQuery(this).attr("value", MDS.UPLOADING).prop("disabled", true);
		jQuery(this)
			.closest("form")
			.append(
				jQuery("<input>").attr({
					type: "hidden",
					name: this.name,
					value: this.value,
				}),
			);
		jQuery(this).closest("form").submit();
		return true;
	});

	jQuery(".mds-pixels-list").accordion({
		header: ".mds-manage-row",
		collapsible: true,
		active: 0,
	});
});

jQuery(document).on("submit", "form", function () {
	const $form = jQuery(this);
	const $buttons = $form.find(".mds-button");
	if ($buttons.length) {
		$buttons.prop("disabled", true);
		$buttons.filter("input").attr("value", MDS.WAIT);
	}
});

function mdsToggleMenu() {
	const menu = document.getElementById("mds-users-menu");
	menu.classList.toggle("mds-show-menu");
}

function confirmLink(theLink, theConfirmMsg) {
	if (theConfirmMsg === "") {
		window.location.href = theLink.href;
		return false;
	}

	const processLink = function() {
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
	};

	if (typeof MDSModalUtility !== 'undefined') {
		MDSModalUtility.confirm(theConfirmMsg, processLink);
	} else {
		let is_confirmed = confirm(theConfirmMsg + "\n");
		if (is_confirmed) {
			processLink();
		}
	}

	return false;
}
