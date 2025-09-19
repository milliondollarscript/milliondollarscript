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

let debug = false;

// Centered modal utility (blocking until OK)
(function initMdsModal(){
    if (document.getElementById('mds-modal-style')) return;
    const css = `
    #mds-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 100000; display: none; align-items: center; justify-content: center; padding: 16px; }
    #mds-modal { max-width: 520px; width: 100%; border-radius: 8px; box-shadow: 0 8px 28px rgba(0,0,0,0.35); background: var(--mds-bg-secondary, #333); color: var(--mds-text-primary, #fff); border: 1px solid var(--mds-border-color, rgba(255,255,255,0.1)); }
    #mds-modal .mds-modal-body { padding: 18px 20px; font-size: 15px; line-height: 1.45; }
    #mds-modal .mds-modal-actions { padding: 12px 16px 16px; display: flex; justify-content: flex-end; gap: 10px; }
    #mds-modal .mds-btn { cursor: pointer; padding: 8px 14px; border-radius: 6px; border: none; font-size: 14px; }
    #mds-modal .mds-btn-ok { background: var(--mds-btn-primary-bg, #0073aa); color: var(--mds-btn-primary-text, #ffffff); }
    #mds-modal .mds-btn-ok:focus { outline: 2px solid var(--mds-border-color, rgba(255,255,255,0.4)); outline-offset: 2px; }
    `;
    const style = document.createElement('style');
    style.id = 'mds-modal-style';
    style.textContent = css;
    document.head.appendChild(style);
    const overlay = document.createElement('div');
    overlay.id = 'mds-modal-overlay';
    overlay.innerHTML = `
      <div id="mds-modal" role="dialog" aria-modal="true" aria-labelledby="mds-modal-title">
        <div class="mds-modal-body" id="mds-modal-message"></div>
        <div class="mds-modal-actions">
          <button type="button" class="mds-btn mds-btn-ok" id="mds-modal-ok">OK</button>
        </div>
      </div>`;
    document.body.appendChild(overlay);
    overlay.addEventListener('click', (e) => {
      if (e.target.id === 'mds-modal-overlay') {
        // Click outside does nothing, to emulate blocking alert
      }
    });
    document.getElementById('mds-modal-ok').addEventListener('click', () => {
      overlay.style.display = 'none';
      const okBtn = document.getElementById('mds-modal-ok');
      if (okBtn) okBtn.blur();
    });
})();

function mds_show_modal(msg) {
    const overlay = document.getElementById('mds-modal-overlay');
    const message = document.getElementById('mds-modal-message');
    if (!overlay || !message) return alert(String(msg));
    message.textContent = String(msg);
    overlay.style.display = 'flex';
    // focus OK for keyboard users
    setTimeout(() => {
      const okBtn = document.getElementById('mds-modal-ok');
      if (okBtn) okBtn.focus();
    }, 0);
}

// Initialize
let first_load = true;
let ajax_queue = [];
let currentAjaxRequest = null;
let suppressLoaderRemoval = false;
let USE_AJAX = MDS_OBJECT.USE_AJAX;
let block_str = MDS_OBJECT.block_str;
function normalizeBlockId(value) {
	const parsed = Number(value);
	if (!Number.isFinite(parsed) || parsed < 0) {
		return null;
	}
	return Math.floor(parsed);
}

function addBlockToSelection(value) {
	const normalized = normalizeBlockId(value);
	if (normalized === null) {
		return;
	}
	if (!selectedBlocks.includes(normalized)) {
		selectedBlocks.push(normalized);
	}
}

function serializeSelectedBlocks() {
	return selectedBlocks
		.filter((id) => Number.isFinite(id) && id >= 0)
		.join(",");
}

function selectionIncludes(value) {
	const normalized = normalizeBlockId(value);
	if (normalized === null) {
		return false;
	}
	return selectedBlocks.includes(normalized);
}

function resetSubmitState(originalLabel) {
	submitting = false;
	if (submit_button1) {
		submit_button1.disabled = false;
		submit_button1.value = originalLabel;
	}
}

function validateSelectionAjax() {
	const ajaxUrl = MDS_OBJECT.ajaxurl || window.ajaxurl || (window.MDS && window.MDS.ajaxurl) || '';
	const payload = {
		action: 'mds_ajax',
		type: 'validate-selection',
		mds_nonce: MDS_OBJECT.mds_nonce,
		BID: MDS_OBJECT.BID,
		order_id: MDS_OBJECT.order_id || '',
		mode: MDS_OBJECT.selection_adjacency_mode || 'ADJACENT',
		blocks: serializeSelectedBlocks()
	};

	return jQuery.ajax({
		type: 'POST',
		url: ajaxUrl || MDS_OBJECT.UPDATE_ORDER,
		dataType: 'json',
		data: payload
	});
}

function finalizeFormSubmission(originalLabel, normalizedValue) {
	const normalized = typeof normalizedValue === 'string' ? normalizedValue : serializeSelectedBlocks();
	const selectedPixelsInput = document.getElementById('selected_pixels');
	if (selectedPixelsInput) {
		selectedPixelsInput.value = normalized;
	}
	mds_update_package(jQuery(pixel_form));

	let waitInterval = setInterval(function () {
		if (ajax_queue.length === 0) {
			clearInterval(waitInterval);
			if (pixel_form !== null) {
				const selectedPixelsInputInner = document.getElementById('selected_pixels');
				if (selectedPixelsInputInner) {
					selectedPixelsInputInner.value = normalized;
				}
				mds_update_package(jQuery(pixel_form));
				pixel_form.submit();
			}
		}
	}, 1000);
}

let selectedBlocks = block_str !== ""
	? Array.from(new Set(
		block_str
			.split(",")
			.map(normalizeBlockId)
			.filter((id) => id !== null)
	))
	: [];
let selecting = false;
let ajaxing = false;
let submitting = false;

let grid_width = MDS_OBJECT.grid_width;
let grid_height = MDS_OBJECT.grid_height;

let BLK_WIDTH = MDS_OBJECT.BLK_WIDTH;
let BLK_HEIGHT = MDS_OBJECT.BLK_HEIGHT;
let G_MAX_BLOCKS = MDS_OBJECT.G_MAX_BLOCKS;
let G_MIN_BLOCKS = MDS_OBJECT.G_MIN_BLOCKS;

let GRD_WIDTH = BLK_WIDTH * grid_width;
let GRD_HEIGHT = BLK_HEIGHT * grid_height;

let orig = {
	grid_width: grid_width,
	grid_height: grid_height,
	BLK_WIDTH: BLK_WIDTH,
	BLK_HEIGHT: BLK_HEIGHT,
	GRD_WIDTH: GRD_WIDTH,
	GRD_HEIGHT: GRD_HEIGHT,
};

let scaled_width = 1;
let scaled_height = 1;

let $myblocks;
let total_cost;
let grid;
let gridOffsetLeft;
let gridOffsetTop;
let submit_button1;
let pointer;
let pixel_container;
let pixel_form;
let blocksCanvas;
let blocksCtx;

function selectFindPluginBaseUrl() {
	if (typeof window.MDSFindPluginBaseUrl === 'function') {
		var base = window.MDSFindPluginBaseUrl();
		if (base) {
			return base;
		}
	}
	if (typeof window.MDS_PLUGIN_BASE_URL === 'string' && window.MDS_PLUGIN_BASE_URL.length > 0) {
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

function selectEnsureAbsoluteUrl(url, pluginBase) {
	if (!url) {
		return null;
	}
	if (/^([a-z][a-z0-9+\.-]*:)?\/\//i.test(url)) {
		return url;
	}
	if (url.indexOf('//') === 0) {
		return window.location.protocol + url;
	}
	var base = pluginBase || selectFindPluginBaseUrl();
	if (base) {
		try {
			return new URL(url.replace(/^\//, ''), base).href;
		} catch (e) {}
	}
	try {
		return new URL(url, window.location.origin + '/').href;
	} catch (e) {
		return null;
	}
}

function selectResolveLoaderUrl(preferred) {
	if (typeof window.MDSResolveLoaderUrl === 'function') {
		var resolved = window.MDSResolveLoaderUrl(preferred);
		if (resolved) {
			return resolved;
		}
	}
	var base = selectFindPluginBaseUrl();
	var candidate = preferred || window.MDS_LOADER_URL || (typeof window.MDS !== 'undefined' && typeof window.MDS.MDS_BASE_URL === 'string'
		? window.MDS.MDS_BASE_URL + 'src/Assets/images/ajax-loader.gif'
		: null);
	candidate = selectEnsureAbsoluteUrl(candidate, base);
	if (candidate) {
		window.MDS_LOADER_URL = candidate;
		return candidate;
	}
	if (base) {
		var fallback = selectEnsureAbsoluteUrl(base + 'src/Assets/images/ajax-loader.gif', base);
		if (fallback) {
			window.MDS_LOADER_URL = fallback;
			return fallback;
		}
	}
	return null;
}

function findGridPreloader(pixelGrid) {
	if (!pixelGrid) {
		return null;
	}
	const $frame = jQuery(pixelGrid).closest('.mds-grid-frame');
	if ($frame.length) {
		const $preloader = $frame.find('.mds-grid-preloader').first();
		if ($preloader.length) {
			return $preloader;
		}
	}
	return null;
}

function setupGridImageFeedback(pixelGrid, observer) {
	if (!pixelGrid || pixelGrid.dataset.mdsInitialGridSetup === '1') {
		return;
	}
	pixelGrid.dataset.mdsInitialGridSetup = '1';

	const $frame = jQuery(pixelGrid).closest('.mds-grid-frame');
	if (!$frame.length) {
		return;
	}
	const preloader = $frame.find('.mds-grid-preloader');
	const feedbackEl = $frame.find('.mds-grid-feedback').get(0);
	if (!preloader.length || !feedbackEl) {
		return;
	}

	const retryButton = feedbackEl.querySelector('.mds-grid-feedback__retry');
	const baseSrc = pixelGrid.getAttribute('data-grid-src') || pixelGrid.getAttribute('src') || '';
	const pointerEl = document.getElementById('block_pointer');

	const hidePointer = () => {
		if (pointerEl) {
			pointerEl.style.visibility = 'hidden';
		}
	};

	const showPointer = () => {
		if (pointerEl) {
			pointerEl.style.visibility = 'visible';
		}
	};

	const hideImage = () => {
		pixelGrid.classList.add('mds-grid-image--hidden');
	};

	const showImage = () => {
		pixelGrid.classList.remove('mds-grid-image--hidden');
	};

	const hideFeedback = () => {
		feedbackEl.setAttribute('hidden', 'hidden');
		feedbackEl.classList.remove('is-visible');
	};

	const hidePreloader = () => {
		if (!preloader.length) {
			return;
		}
		remove_ajax_loader(preloader);
		preloader.attr('hidden', 'hidden');
		preloader.empty();
		preloader.removeData('mdsPreloaderActive');
	};

	const ensurePreloaderSpinner = () => {
		if (!preloader.length) {
			return;
		}
		if (!preloader.children().length) {
			const loaderSrc = preloader.attr('data-loader-src');
			if (loaderSrc) {
				preloader.html(`<img class="mds-grid-preloader__spinner" src="${loaderSrc}" alt="" aria-hidden="true" width="32" height="32"/>`);
			} else {
				preloader.html('<span class="mds-grid-preloader__spinner" aria-hidden="true"></span>');
			}
		}
	};

	const showPreloader = (options = {}) => {
		if (!preloader.length) {
			return;
		}
		ensurePreloaderSpinner();
		preloader.removeAttr('hidden');
		preloader.data('mdsPreloaderActive', true);
		if (options.hideImage !== false) {
			hideImage();
		}
		if (options.hidePointer !== false) {
			hidePointer();
		}
	};

	const showFeedback = () => {
		hidePreloader();
		feedbackEl.removeAttribute('hidden');
		feedbackEl.classList.add('is-visible');
		hideImage();
		hidePointer();
	};

	const finalizeSuccess = () => {
		hidePreloader();
		hideFeedback();
		showImage();
		showPointer();
		if (gridInitialized) {
			return;
		}
		gridInitialized = true;
		initializeGrid();
		if (observer && typeof observer.disconnect === 'function') {
			observer.disconnect();
		}
	};

	const finalizeError = () => {
		hidePreloader();
		gridInitialized = false;
		showFeedback();
	};

	pixelGrid.mdsGridControls = {
		showPreloader,
		hidePreloader,
		showFeedback,
		hideFeedback,
		hidePointer,
		showPointer,
		hideImage,
		showImage,
	};

	pixelGrid.addEventListener('load', () => {
		if (pixelGrid.naturalWidth === 0 || pixelGrid.naturalHeight === 0) {
			finalizeError();
			return;
		}
		finalizeSuccess();
	});

	pixelGrid.addEventListener('error', finalizeError);
	pixelGrid.addEventListener('abort', finalizeError);

	if (retryButton) {
		retryButton.addEventListener('click', (event) => {
			event.preventDefault();
			hideFeedback();
			gridInitialized = false;
			show_initial_grid_loader(pixelGrid);
			showPreloader();
			const separator = baseSrc.indexOf('?') === -1 ? '?' : '&';
			pixelGrid.src = baseSrc + separator + '_mds_retry=' + Date.now();
		});
	}

	if (pixelGrid.complete) {
		if (pixelGrid.naturalWidth === 0 || pixelGrid.naturalHeight === 0) {
			finalizeError();
		} else {
			finalizeSuccess();
		}
	} else {
		hideFeedback();
		showPreloader();
	}
}

function show_initial_grid_loader(pixelGrid) {
	const preloader = findGridPreloader(pixelGrid);
	if (!preloader || !preloader.length || preloader.data('mdsPreloaderActive')) {
		return;
	}
	const resolvedLoaderUrl = selectResolveLoaderUrl(preloader.attr('data-loader-src'));
	if (resolvedLoaderUrl) {
		preloader.attr('data-loader-src', resolvedLoaderUrl);
		window.MDS_LOADER_URL = resolvedLoaderUrl;
	}
	if (!preloader.children().length) {
		const loaderSrc = resolvedLoaderUrl || preloader.attr('data-loader-src');
		const markup = loaderSrc
			? `<img class="mds-grid-preloader__spinner" src="${loaderSrc}" alt="" aria-hidden="true" width="32" height="32"/>`
			: '<span class="mds-grid-preloader__spinner" aria-hidden="true"></span>';
		preloader.html(markup);
	}
	preloader.removeAttr('hidden');
	preloader.data('mdsPreloaderActive', true);
	if (pixelGrid) {
		pixelGrid.classList.add('mds-grid-image--hidden');
	}
	const pointer = document.getElementById('block_pointer');
	if (pointer) {
		pointer.style.visibility = 'hidden';
	}
}

function add_ajax_loader(container, options = {}) {
	const $containers = container instanceof jQuery ? container : jQuery(container);
	if (!$containers || !$containers.length) {
		return;
	}
	const fallbackHeight = options.fallbackHeight || 0;
	$containers.each(function () {
		const $target = jQuery(this);
		if ($target.children(".ajax-loader").length > 0) {
			return;
		}
		if (fallbackHeight && !$target.data("mdsLoaderMinHeight")) {
			$target.data("mdsLoaderMinHeight", $target.css("min-height") || "");
			$target.css("min-height", fallbackHeight + "px");
		}
		var loaderSrc = selectResolveLoaderUrl(options.loaderUrl || $target.attr("data-loader-src"));
		const spinnerMarkup = loaderSrc
			? "<img class=\"ajax-loader__spinner\" src=\"" + loaderSrc + "\" alt=\"Loading\" width=\"32\" height=\"32\"/>"
			: "<span class=\"ajax-loader__spinner\"></span>";
		const $ajax_loader = jQuery(
			"<div class='ajax-loader' role='status' aria-live='polite'>" + spinnerMarkup + "</div>",
		);
		$target.append($ajax_loader);
		if (!$target.attr("data-loader-src") && loaderSrc) {
			$target.attr("data-loader-src", loaderSrc);
		}
	});
}

function remove_ajax_loader(container) {
	if (container) {
		const $containers = container instanceof jQuery ? container : jQuery(container);
		$containers.each(function () {
			const $target = jQuery(this);
			$target.find(".ajax-loader").remove();
			const stored = $target.data("mdsLoaderMinHeight");
			if (stored !== undefined) {
				$target.css("min-height", stored);
				$target.removeData("mdsLoaderMinHeight");
			}
		});
	} else {
		jQuery(".ajax-loader").each(function () {
			const $parent = jQuery(this).parent();
			jQuery(this).remove();
			const stored = $parent.data("mdsLoaderMinHeight");
			if (stored !== undefined) {
				$parent.css("min-height", stored);
				$parent.removeData("mdsLoaderMinHeight");
			}
		});
	}
}

// --- Canvas overlay helpers ---
function setupBlocksCanvas() {
	blocksCanvas = document.getElementById('blocks_canvas');
	if (!blocksCanvas || !grid) return;
	const part = window.MDS_GRID_PARTITION;
	if (!part) return;
	// Device pixel ratio aware sizing for crisp lines
	const dpr = window.devicePixelRatio || 1;
	blocksCanvas.style.width = part.preW + 'px';
	blocksCanvas.style.height = part.preH + 'px';
	blocksCanvas.width = Math.round(part.preW * dpr);
	blocksCanvas.height = Math.round(part.preH * dpr);
	blocksCtx = blocksCanvas.getContext('2d');
	blocksCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
	blocksCtx.imageSmoothingEnabled = false;
}

let MDS_SEL_IMG = null;
function preloadSelectionImage(url, cb) {
	MDS_SEL_IMG = new Image();
	MDS_SEL_IMG.onload = () => cb && cb();
	MDS_SEL_IMG.onerror = () => { MDS_SEL_IMG = null; cb && cb(); };
	MDS_SEL_IMG.src = url;
}

function renderSelectionCanvas() {
	if (!blocksCanvas || !blocksCtx) return;
	const part = window.MDS_GRID_PARTITION;
	if (!part) return;
	// Clear
	blocksCtx.clearRect(0, 0, part.preW, part.preH);
	const useImage = !!MDS_SEL_IMG;
	if (!useImage) {
		blocksCtx.fillStyle = 'rgba(0, 192, 0, 0.35)';
	}
	// Draw each selected block as an exact cell rect or image
	for (let i = 0; i < selectedBlocks.length; i++) {
		const id = selectedBlocks[i];
		const x = id % part.cols;
		const y = Math.floor(id / part.cols);
		if (x < 0 || x >= part.cols || y < 0 || y >= part.rows) continue;
		const left = part.colLefts[x];
		const top = part.rowTops[y];
		const w = part.colWidths[x];
		const h = part.rowHeights[y];
		if (useImage) {
			blocksCtx.drawImage(MDS_SEL_IMG, left, top, w, h);
		} else {
			blocksCtx.fillRect(left, top, w, h);
		}
	}
}

const messageout = function (message) {
	mds_show_modal(String(message));
};

jQuery.fn.rescaleStyles = function () {
	this.css({
		width: BLK_WIDTH + "px",
		height: BLK_HEIGHT + "px",
		"line-height": BLK_HEIGHT + "px",
		"font-size": BLK_HEIGHT + "px",
	});

	return this;
};

jQuery.fn.repositionStyles = function () {
	if (this.attr("id") === undefined) {
		return this;
	}
	const part = window.MDS_GRID_PARTITION;
	let id = parseInt(jQuery(this).data("blockid"), 10);
	let pos = get_block_position(id);
	if (part) {
		this.css({
			top: part.rowTops[pos.y] + "px",
			left: part.colLefts[pos.x] + "px",
			width: part.colWidths[pos.x] + "px",
			height: part.rowHeights[pos.y] + "px",
		});
		// Ensure child image fills cell exactly
		this.find('img').css({
			width: part.colWidths[pos.x] + "px",
			height: part.rowHeights[pos.y] + "px",
		});
	} else {
		this.css({
			top: pos.y * BLK_HEIGHT + gridOffsetTop + "px",
			left: pos.x * BLK_WIDTH + gridOffsetLeft + "px",
			width: BLK_WIDTH + "px",
			height: BLK_HEIGHT + "px",
		});
	}
	return this;
};

function has_touch() {
	try {
		document.createEvent("TouchEvent");
		return true;
	} catch (e) {
		return false;
	}
}

function update_order() {
	if (selectedBlocks.length > 0) {
		pixel_form.selected_pixels.value = serializeSelectedBlocks();
	}
}

function reserve_block(block_id) {
	const normalizedId = normalizeBlockId(block_id);
	if (normalizedId === null) {
		return;
	}
	if (!selectedBlocks.includes(normalizedId)) {
		addBlockToSelection(normalizedId);

		// remove default value of -1 from array
		let index = selectedBlocks.indexOf(-1);
		if (index > -1) {
			selectedBlocks.splice(index, 1);
		}

		update_order();
	} else {
	}
}

function unreserve_block(block_id) {
	const normalizedId = normalizeBlockId(block_id);
	if (normalizedId === null) {
		return;
	}
	let index = selectedBlocks.indexOf(normalizedId);
	if (index > -1) {
		selectedBlocks.splice(index, 1);
		update_order();
	} else {
	}
}

function add_block(block_id, block_x, block_y) {
	// Canvas mode: update selection set and draw
	if (document.getElementById('blocks_canvas')) {
		if (!selectionIncludes(block_id)) {
			reserve_block(block_id);
			renderSelectionCanvas();
		}
		return;
	}
	// Legacy DOM overlays fallback
	// Avoid duplicates
	if (document.getElementById("block" + block_id.toString())) {
		return;
	}
	// Calculate position based on block_id grid coordinates
	let pos = get_block_position(block_id);
	const part = window.MDS_GRID_PARTITION;
	let block_left = part ? part.colLefts[pos.x] : (pos.x * BLK_WIDTH + gridOffsetLeft);
	let block_top = part ? part.rowTops[pos.y] : (pos.y * BLK_HEIGHT + gridOffsetTop);
	let block_w = part ? part.colWidths[pos.x] : BLK_WIDTH;
	let block_h = part ? part.rowHeights[pos.y] : BLK_HEIGHT;

	// Create a document fragment
	let fragment = document.createDocumentFragment();

	// Create the block element
	let $new_block = jQuery("<span>", {
		id: "block" + block_id.toString(),
		css: {
			left: block_left + "px",
			top: block_top + "px",
			lineHeight: block_h + "px",
			fontSize: block_h + "px",
			width: block_w + "px",
			height: block_h + "px",
		},
	}).addClass("mds-block");

	// Create the block image element
	let $new_img = jQuery("<img>", {
		alt: "",
		src: MDS_OBJECT.MDS_CORE_URL + "images/selected_block.png",
		css: {
			lineHeight: block_h + "px",
			fontSize: block_h + "px",
			width: block_w + "px",
			height: block_h + "px",
		},
	});

	// Append the block image to the block element
	$new_block.append($new_img);

	// Add the block element to the document fragment
	fragment.appendChild($new_block[0]);

	// Append the document fragment to the DOM
	$myblocks.append(fragment);

	// Store the block ID as data on the block element
	$new_block.data("blockid", block_id);

	reserve_block(block_id);
}

function remove_block(block_id) {
	// Canvas mode
	if (document.getElementById('blocks_canvas')) {
		unreserve_block(block_id);
		renderSelectionCanvas();
		return;
	}
	// Legacy DOM fallback
	let myblock = document.getElementById("block" + block_id.toString());
	if (myblock !== null) {
		myblock.remove();
	} else {
	}

	unreserve_block(block_id);
}

function invert_block(clicked_block) {
	let myblock = document.getElementById("block" + clicked_block.id.toString());
	if (myblock !== null) {
		remove_block(clicked_block.id);
	} else {
		add_block(clicked_block.id, clicked_block.x, clicked_block.y);
	}
}

function is_block_selected(clicked_blocks) {
	// If clicked_blocks is not an array, make it one for consistent checking
	if (!Array.isArray(clicked_blocks)) {
		clicked_blocks = [{ id: clicked_blocks }];
	}

	// Check if any of the clicked blocks are already in the selectedBlocks array
	return clicked_blocks.some((block) => selectionIncludes(block.id));
}

function get_clicked_blocks(OffsetX, OffsetY, block) {
	const part = window.MDS_GRID_PARTITION;
	let clicked_blocks = [];
	const sel = mds_getSelectionSize();
	// Anchor col/row from block id (consistent with server mapping)
	const pos = get_block_position(block);
	let col = pos.x;
	let row = pos.y;
	// Clamp selection footprint
	col = Math.max(0, Math.min(col, part.cols - sel));
	row = Math.max(0, Math.min(row, part.rows - sel));
	for (let j = 0; j < sel; j++) {
		for (let i = 0; i < sel; i++) {
			const cc = col + i;
			const rr = row + j;
			if (cc >= 0 && cc < part.cols && rr >= 0 && rr < part.rows) {
				const id = rr * part.cols + cc;
				clicked_blocks.push({ id, x: part.colLefts[cc], y: part.rowTops[rr] });
			}
		}
	}
	return clicked_blocks;
}

function do_blocks(blocks, op) {
	// Ensure blocks is an array
	if (!Array.isArray(blocks)) {
		blocks = [blocks];
	}

	// Fast path for canvas overlay: batch update and single render
	if (document.getElementById('blocks_canvas')) {
		if (op === 'add') {
			for (const block of blocks) {
				const id = typeof block === 'object' ? block.id : block;
				if (!selectionIncludes(id)) addBlockToSelection(id);
			}
			renderSelectionCanvas();
			return;
		} else if (op === 'remove') {
			const toRemove = new Set(
				blocks
					.map((b) => (typeof b === 'object' ? b.id : b))
					.map(normalizeBlockId)
					.filter((id) => id !== null)
			);
			selectedBlocks = selectedBlocks.filter((id) => !toRemove.has(id));
			renderSelectionCanvas();
			return;
		} else if (op === 'invert') {
			for (const block of blocks) {
				const id = typeof block === 'object' ? block.id : block;
			const normalizedId = normalizeBlockId(id);
			if (normalizedId === null) {
				continue;
			}
			const idx = selectedBlocks.indexOf(normalizedId);
			if (idx === -1) {
				selectedBlocks.push(normalizedId);
			} else {
				selectedBlocks.splice(idx, 1);
			}
			}
			renderSelectionCanvas();
			return;
		}
	}

	for (const block of blocks) {
		// block can be just an ID (number) or an object with id, x, y
		const block_id = typeof block === 'object' ? block.id : block;
		const block_x = typeof block === 'object' ? block.x : null;
		const block_y = typeof block === 'object' ? block.y : null;

		if (op === "add") {
			add_block(block_id, block_x, block_y);
		} else if (op === "remove") {
			remove_block(block_id);
		} else if (op === "invert") {
			invert_block({ id: block_id, x: block_x, y: block_y });
		}
	}

	// Bind the mousemove event using event delegation (legacy overlays)
	if (!has_touch()) {
		$myblocks.on("mousemove", ".mds-block", function (event) {
			let offset = getOffset(
				event.originalEvent.pageX,
				event.originalEvent.pageY,
			);
			if (offset == null) {
				return false;
			}
			show_pointer(offset);
		});
	}
}

function select_pixels(offset) {
	// if (selecting) {
	// 	return false;
	// }
	// selecting = true;
	//
	// // cannot select while AJAX is in action
	// if (submit_button1.disabled) {
	// 	return false;
	// }
	//
	if (!pointer) {
		pointer = document.getElementById("block_pointer");
		if (!pointer) {
			return false; // Exit if pointer cannot be found
		}
	}
	// pointer.style.visibility = "hidden";

	change_block_state(offset.x, offset.y);

	return true;
}

function load_order() {
	if (MDS_OBJECT.blocks.length === 0) {
		return;
	}

	// Use the more detailed MDS_OBJECT.blocks array to draw the initial state
	MDS_OBJECT.blocks.forEach(function (block) {
		add_block(parseInt(block.block_id, 10));
	});

	// If using canvas, render once at end
	if (document.getElementById('blocks_canvas')) {
		renderSelectionCanvas();
	}

	if (pixel_form !== null) {
		pixel_form.addEventListener("submit", formSubmit);
	}
}

function getObjCoords(obj) {
	var rect = obj.getBoundingClientRect();
	var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
	var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
	return { x: rect.left + scrollLeft, y: rect.top + scrollTop };
}

// --- Grid partition helpers (integer pixel boundaries) ---
function mds_partition(size, count) {
    const base = Math.floor(size / count);
    const remainder = size % count;
    const widths = new Array(count).fill(base);
    for (let i = 0; i < remainder; i++) widths[i] += 1;
    const edges = new Array(count + 1);
    edges[0] = 0;
    for (let i = 0; i < count; i++) edges[i + 1] = edges[i] + widths[i];
    return { widths, edges };
}

function computeGridPartitions() {
    if (!grid) return;
    const cols = parseInt(MDS_OBJECT.grid_width, 10) || 1;
    const rows = parseInt(MDS_OBJECT.grid_height, 10) || 1;
    const preW = jQuery(grid).width();
    const preH = jQuery(grid).height();
    const colsPart = mds_partition(preW, cols);
    const rowsPart = mds_partition(preH, rows);
    window.MDS_GRID_PARTITION = {
        cols,
        rows,
        preW,
        preH,
        colWidths: colsPart.widths,
        colLefts: colsPart.edges,
        rowHeights: rowsPart.widths,
        rowTops: rowsPart.edges,
    };
}

function mds_pageToPre(x, y) {
    const rect = grid.getBoundingClientRect();
    const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft || 0;
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
    const relX = x - (rect.left + scrollLeft);
    const relY = y - (rect.top + scrollTop);
    const part = window.MDS_GRID_PARTITION;
    const scaleX = part ? (part.preW / rect.width) : 1;
    const scaleY = part ? (part.preH / rect.height) : 1;
    return { preX: relX * scaleX, preY: relY * scaleY };
}

function mds_findIndexFromPre(preCoord, edges, count) {
    // binary search into edges array such that edges[i] <= preCoord < edges[i+1]
    let lo = 0, hi = count;
    while (lo + 1 < hi) {
        const mid = (lo + hi) >> 1;
        if (preCoord >= edges[mid]) lo = mid; else hi = mid;
    }
    if (lo < 0) return 0;
    if (lo >= count) return count - 1;
    return lo;
}

function mds_sumRange(arr, start, len) {
    let s = 0;
    for (let i = 0; i < len; i++) s += arr[start + i] || 0;
    return s;
}

function mds_getSelectionSize() {
    const n = parseInt(jQuery("#mds-selection-size-value").val(), 10) || 1;
    const part = window.MDS_GRID_PARTITION;
    if (!part) return n;
    return Math.max(1, Math.min(n, Math.min(part.cols, part.rows)));
}

function getOffset(x, y, touch) {
	if (grid == null) {
		// grid may not be loaded yet
		return null;
	}

	const part = window.MDS_GRID_PARTITION;
	if (!part) {
		// Fallback: ensure partitions exist
		computeGridPartitions();
	}
	const p = window.MDS_GRID_PARTITION;
	const rect = grid.getBoundingClientRect();
	// Convert to pre-transform coordinates
	const pre = mds_pageToPre(x, y);
	let col = mds_findIndexFromPre(pre.preX, p.colLefts, p.cols);
	let row = mds_findIndexFromPre(pre.preY, p.rowTops, p.rows);
	let sel = mds_getSelectionSize();
	// Clamp so selection fits
	col = Math.max(0, Math.min(col, p.cols - sel));
	row = Math.max(0, Math.min(row, p.rows - sel));
	// Return top-left in pre-transform pixels
	return { x: p.colLefts[col], y: p.rowTops[row] };
}

function get_pointer_size() {
	let size = {};

	const $mds_selection_size_slider = jQuery("#mds-selection-size-slider");
	const $mds_selection_size_value = jQuery("#mds-selection-size-value");
	const $mds_total_blocks_value = jQuery("#mds-total-blocks-value");

	if ($mds_selection_size_slider.attr("type") === "hidden") {
		let selectedSize = parseInt($mds_selection_size_value.val(), 10);
		const maxBlockSize = Math.max(GRD_WIDTH, GRD_HEIGHT);

		// Calculate the number of blocks left to select
		let blocksLeft = G_MAX_BLOCKS - selectedBlocks.length;

		// Calculate the square root of the number of blocks left to select
		let sqrtBlocksLeft = Math.floor(Math.sqrt(blocksLeft));

		// If the selected size is 0, set it to the original maximum square size
		if (selectedSize === 0) {
			selectedSize = Math.floor(Math.sqrt(G_MAX_BLOCKS));
		}

		// Cap the selected size to the maximum block size and the square root of the number of blocks left to select
		let cappedSize = Math.min(selectedSize, maxBlockSize, sqrtBlocksLeft);

		// If there are no blocks left to select, set cappedSize to 1
		if (blocksLeft === 0) {
			cappedSize = 1;
		}

		// Try to enlarge the area if possible
		while ((cappedSize + 1) * (cappedSize + 1) <= blocksLeft) {
			cappedSize++;
			blocksLeft = blocksLeft - cappedSize * cappedSize;
		}

		size.width = BLK_WIDTH * cappedSize;
		size.height = BLK_HEIGHT * cappedSize;

		// Update the field values
		$mds_selection_size_slider.val(cappedSize);
		$mds_selection_size_value.val(cappedSize);
		$mds_total_blocks_value.val(cappedSize * cappedSize);
	} else {
		const selectedSize = parseInt(
			jQuery("#mds-selection-size-value").val(),
			10,
		);
		const maxBlockSize = Math.max(GRD_WIDTH, GRD_HEIGHT);

		// Cap the selected size to the maximum block size
		const cappedSize = Math.min(selectedSize, maxBlockSize);

		size.width = BLK_WIDTH * cappedSize;
		size.height = BLK_HEIGHT * cappedSize;
	}

	return size;
}

function update_pointer_size() {
	const p = window.MDS_GRID_PARTITION;
	if (!p || !pointer) return;
	const sel = mds_getSelectionSize();
	// Determine current anchor block from pointer.map_x/map_y
	let col = mds_findIndexFromPre(pointer.map_x || 0, p.colLefts, p.cols);
	let row = mds_findIndexFromPre(pointer.map_y || 0, p.rowTops, p.rows);
	col = Math.max(0, Math.min(col, p.cols - sel));
	row = Math.max(0, Math.min(row, p.rows - sel));
	const w = mds_sumRange(p.colWidths, col, sel);
	const h = mds_sumRange(p.rowHeights, row, sel);
	pointer.style.width = w + "px";
	pointer.style.height = h + "px";
}

function show_pointer(offset) {
	// Ensure pointer is valid before using it
	if (!pointer) {
		pointer = document.getElementById("block_pointer");
		if (!pointer) {
			return false; // Exit if pointer cannot be found
		}
	}
	if (pointer.style.visibility !== "visible") {
		pointer.style.visibility = "visible";
	}
	if (pointer.style.display !== "block") {
		pointer.style.display = "block";
	}

	pointer.style.top = offset.y + "px";
	pointer.style.left = offset.x + "px";

	pointer.map_x = offset.x;
	pointer.map_y = offset.y;

	// Size pointer to exactly cover N×N cells
	update_pointer_size();

	return true;
}

function jsCheckContiguous(blockIds) {
	if (!Array.isArray(blockIds) || blockIds.length <= 1) {
		return true;
	}

	const cols = parseInt(grid_width, 10);
	if (!cols || cols <= 0) {
		return true;
	}

	const normalized = blockIds.map(Number).filter(Number.isFinite);
	if (!normalized.length) {
		return true;
	}

	const set = new Set(normalized);
	const visited = new Set();
	const queue = [normalized[0]];
	visited.add(normalized[0]);

	while (queue.length) {
		const current = queue.shift();
		const neighbors = [];

		const up = current - cols;
		if (up >= 0) {
			neighbors.push(up);
		}

		const down = current + cols;
		neighbors.push(down);

		if (current % cols !== 0) {
			neighbors.push(current - 1);
		}
		if (current % cols !== cols - 1) {
			neighbors.push(current + 1);
		}

		for (const neighbor of neighbors) {
			if (set.has(neighbor) && !visited.has(neighbor)) {
				visited.add(neighbor);
				queue.push(neighbor);
			}
		}
	}

	return visited.size === set.size;
}

function jsCheckRectangle(blockIds) {
	if (!Array.isArray(blockIds) || blockIds.length <= 1) return true;
	const cols = parseInt(MDS_OBJECT.grid_width, 10) || 1;
	const set = new Set(blockIds.map(n => parseInt(n, 10)));
	const xs = new Set();
	const ys = new Set();
	for (const id of set) {
		const x = id % cols;
		const y = Math.floor(id / cols);
		xs.add(x); ys.add(y);
	}
	const xArr = Array.from(xs).sort((a,b)=>a-b);
	const yArr = Array.from(ys).sort((a,b)=>a-b);
	for (let i=1;i<xArr.length;i++){ if (xArr[i] !== xArr[i-1] + 1) return false; }
	for (let j=1;j<yArr.length;j++){ if (yArr[j] !== yArr[j-1] + 1) return false; }
	const area = xArr.length * yArr.length;
	if (area !== set.size) return false;
	for (const y of yArr) {
		for (const x of xArr) {
			const id = y * cols + x;
			if (!set.has(id)) return false;
		}
	}
	return true;
}

function formSubmit(event) {
	// Prevent default to handle form submission manually
	event.preventDefault();
	event.stopPropagation();

	// Canvas mode and legacy: rely on selectedBlocks
	if (!Array.isArray(selectedBlocks) || selectedBlocks.length === 0) {
		messageout(MDS_OBJECT.no_blocks_selected);
		return false;
	}

	if (submitting === false) {
		submitting = true;
		submit_button1.disabled = true;
		let submit1_lang = submit_button1.value;
		submit_button1.value = MDS_OBJECT.WAIT;

// Client-side submit-time validation (min blocks and rectangle if enabled)
				const minBlocks = parseInt(MDS_OBJECT.G_MIN_BLOCKS, 10) || 0;
				const selMode = (MDS_OBJECT.selection_adjacency_mode || 'ADJACENT');
				if (minBlocks > 0 && selectedBlocks.length < minBlocks) {
					messageout('You must select at least ' + minBlocks + ' blocks.');
					resetSubmitState(submit1_lang);
					return false;
				}
				if (selMode === 'RECTANGLE' && !jsCheckRectangle(selectedBlocks)) {
					messageout(MDS_OBJECT.rectangle_required || 'Selection must form a rectangle or square.');
					resetSubmitState(submit1_lang);
					return false;
				}


		validateSelectionAjax()
			.done(function (response) {
				if (response && response.success) {
					let normalized = null;
					if (response.data && typeof response.data.normalized === 'string') {
						normalized = response.data.normalized;
						if (normalized === '') {
							selectedBlocks = [];
						} else {
							selectedBlocks = Array.from(new Set(normalized.split(',')
								.map(normalizeBlockId)
								.filter(function (id) { return id !== null; })));
						}
					}
					finalizeFormSubmission(submit1_lang, normalized);
				} else {
					const messages = response && response.data && Array.isArray(response.data.messages) ? response.data.messages : [];
					const message = messages.length ? messages.join(' ') : (MDS_OBJECT.not_adjacent || 'You must select a block adjacent to another one.');
					messageout(message);
					resetSubmitState(submit1_lang);
				}
			})
			.fail(function (jqXHR, textStatus) {
				let failureMessage = null;
				if (jqXHR && jqXHR.responseJSON) {
					if (Array.isArray(jqXHR.responseJSON.messages) && jqXHR.responseJSON.messages.length) {
						failureMessage = jqXHR.responseJSON.messages.join(' ');
					} else if (jqXHR.responseJSON.data && Array.isArray(jqXHR.responseJSON.data.messages) && jqXHR.responseJSON.data.messages.length) {
						failureMessage = jqXHR.responseJSON.data.messages.join(' ');
					}
				}
				if (!failureMessage) {
					failureMessage = 'Request failed: ' + (textStatus || 'unknown error');
				}
				messageout(failureMessage);
				resetSubmitState(submit1_lang);
			});

		return false;
	} else {
	}
}

function reset_pixels() {
	const pixelGrid = document.getElementById('pixelimg');
	const controls = pixelGrid ? pixelGrid.mdsGridControls : null;
	const fallbackContainer = jQuery('.mds-pixel-wrapper');
	const usingFallback = !controls;

	ajax_queue = [];
	if (currentAjaxRequest && typeof currentAjaxRequest.abort === 'function') {
		suppressLoaderRemoval = true;
		currentAjaxRequest.abort();
	}
	ajaxing = false;

	if (fallbackContainer && fallbackContainer.length) {
		add_ajax_loader(fallbackContainer);
	} else {
		add_ajax_loader('.mds-pixel-wrapper');
	}

	if (controls) {
		if (typeof controls.hideFeedback === 'function') {
			controls.hideFeedback();
		}
		if (typeof controls.showPreloader === 'function') {
			controls.showPreloader({ hideImage: false });
		}
	}

	currentAjaxRequest = jQuery.ajax({
		type: "POST",
		url: MDS_OBJECT.UPDATE_ORDER,
		data: {
			reset: "true",
			action: "reset",
			_wpnonce: MDS_OBJECT.NONCE,
			BID: MDS_OBJECT.BID,
		},
		success: function (data) {
			if (data.success === true && data.data && data.data.type === "removed") {
				// Clear selection set and redraw (canvas mode)
				selectedBlocks = [];
				const input = document.getElementById('selected_pixels');
				if (input) input.value = '';
				renderSelectionCanvas();
			} else {
			}
		},
		error: function (xhr, status, error) {
		},
		complete: function (jqXHR, textStatus) {
			if (currentAjaxRequest === jqXHR) {
				currentAjaxRequest = null;
			}
			if (textStatus === 'abort' && suppressLoaderRemoval) {
				suppressLoaderRemoval = false;
				return;
			}
			if (!usingFallback && controls) {
				if (typeof controls.hidePreloader === 'function') {
					controls.hidePreloader();
				}
				if (typeof controls.hideFeedback === 'function') {
					controls.hideFeedback();
				}
				if (typeof controls.showImage === 'function') {
					controls.showImage();
				}
				if (typeof controls.showPointer === 'function') {
					controls.showPointer();
				}
			}
			remove_ajax_loader(fallbackContainer);
			suppressLoaderRemoval = false;
		},
	});
}

function rescale_grid() {
	if (grid == null) {
		// grid may not be loaded yet
		return;
	}

	// Element's rendered rect (for screen mapping) and layout width/height (pre-transform)
	const rect = grid.getBoundingClientRect();

	// Get original dimensions from data attributes (pixels at 1x/original)
	let origWidth =
		parseInt(jQuery(grid).attr("data-original-width"), 10) || orig.GRD_WIDTH;
	let origHeight =
		parseInt(jQuery(grid).attr("data-original-height"), 10) || orig.GRD_HEIGHT;

	// Use layout width/height (pre-transform) to derive base block size
	const preW = jQuery(grid).width();
	const preH = jQuery(grid).height();
	grid_width = preW;
	grid_height = preH;

	// Calculate scaling factors based on layout size compared to original dimensions (pre-transform)
	scaled_width = preW / origWidth;
	scaled_height = preH / origHeight;

	// Build integer partitions for current layout size
	computeGridPartitions();

	// Also call scaleImageMap to ensure grid blocks are positioned properly
	if (typeof scaleImageMap === "function") {
		scaleImageMap();
	}

	// Resize canvas overlay to match new layout size and redraw
	setupBlocksCanvas();
	renderSelectionCanvas();

	// Update block dimensions based on scaling factors (legacy average)
	BLK_WIDTH = orig.BLK_WIDTH * scaled_width;
	BLK_HEIGHT = orig.BLK_HEIGHT * scaled_height;

	// Overlays are positioned relative to the wrapper (0,0); no extra offsets needed
	gridOffsetLeft = 0;
	gridOffsetTop = 0;

	// Update the pointer element (will be resized precisely in update_pointer_size)
	jQuery(pointer).rescaleStyles();

	// Store current selected blocks before clearing
	let currentBlocks = selectedBlocks.slice(); // Use selectedBlocks instead of undefined blocks

	// Always clear and rebuild all blocks for consistent positioning
	$myblocks.empty();

	// Rebuild all blocks from scratch
	if (currentBlocks.length > 0) {
		currentBlocks.forEach(function (blockId) {
			// Directly add blocks using their ID (coordinates will be recalculated)
			add_block(blockId);
		});
	}

	// Call scaleImageMap if it exists (will be defined in the global scope if needed)
	if (window.scaleImageMap && typeof window.scaleImageMap === "function") {
		window.scaleImageMap();
	}
}

function center_block(coords) {
	let size = get_pointer_size();
	coords.x -= size.width / 2 - BLK_WIDTH / 2;
	coords.y -= size.height / 2 - BLK_HEIGHT / 2;
	return coords;
}


/**
 * @return {boolean}
 */
function IsNumeric(str) {
	let ValidChars = "0123456789";
	let IsNumber = true;
	let Char;

	for (let i = 0; i < str.length && IsNumber === true; i++) {
		Char = str.charAt(i);
		if (ValidChars.indexOf(Char) === -1) {
			IsNumber = false;
		}
	}
	return IsNumber;
}

function get_block_position(block_id) {
	// Convert block_id to grid coordinates
	const grid_width_in_blocks = Math.floor(orig.GRD_WIDTH / orig.BLK_WIDTH);

	// Calculate x,y coordinates (in number of blocks, not pixels)
	const x_blocks = block_id % grid_width_in_blocks;
	const y_blocks = Math.floor(block_id / grid_width_in_blocks);

	// Return position in terms of block units
	return {
		x: x_blocks,
		y: y_blocks,
	};
}

function get_clicked_block(OffsetX, OffsetY) {
    const p = window.MDS_GRID_PARTITION;
    if (!p) computeGridPartitions();
    const part = window.MDS_GRID_PARTITION;
    const col = mds_findIndexFromPre(OffsetX, part.colLefts, part.cols);
    const row = mds_findIndexFromPre(OffsetY, part.rowTops, part.rows);
    return row * part.cols + col;
}

function change_block_state(OffsetX, OffsetY) {
    const single_clicked_block = get_clicked_block(OffsetX, OffsetY);
    const selection_size = parseInt(jQuery("#mds-selection-size-value").val(), 10) || 1;
    const all_blocks_in_selection = get_clicked_blocks(OffsetX, OffsetY, single_clicked_block);
    const is_erase_mode = jQuery('#erase').is(':checked');

    let blocks_to_add = [];
    let blocks_to_remove = [];
    let action = 'add'; // Default action

	if (is_erase_mode) {
		action = 'remove';
		// In erase mode, we only want to remove blocks that are currently selected.
		all_blocks_in_selection.forEach(block => {
			if (selectionIncludes(block.id)) {
				blocks_to_remove.push(block.id);
			}
		});
	} else if (MDS_OBJECT.INVERT_PIXELS === "YES") {
		action = 'invert';
		// Invert logic for both single and area selection (XOR)
		all_blocks_in_selection.forEach(block => {
			if (selectionIncludes(block.id)) {
				blocks_to_remove.push(block.id);
			} else {
				blocks_to_add.push(block.id);
			}
		});
	} else {
		// Standard mode: add all blocks in the selection, avoiding duplicates.
		action = 'add';
		all_blocks_in_selection.forEach(block => {
			if (!selectionIncludes(block.id)) {
				blocks_to_add.push(block.id);
			}
		});
	}

    // Visually update blocks immediately for better UX
blocks_to_add.forEach(id => { add_block(id); });
    blocks_to_remove.forEach(id => { remove_block(id); });

    // Only proceed with AJAX if there's an actual change
    if (blocks_to_add.length === 0 && blocks_to_remove.length === 0) {
        return; // No changes, no need to call server
    }

    const url = new URL(MDS_OBJECT.UPDATE_ORDER, window.location.origin);
    url.searchParams.set("selection_size", selection_size);
    url.searchParams.set("user_id", MDS_OBJECT.user_id);
    url.searchParams.set("block_id", single_clicked_block.toString());
    url.searchParams.set("BID", MDS_OBJECT.BID);
    url.searchParams.set("t", MDS_OBJECT.time);
    url.searchParams.set("_wpnonce", MDS_OBJECT.NONCE);
    url.searchParams.set('action', action);

    let data = {
        action: action,
        clicked_block: single_clicked_block,
        OffsetX: OffsetX,
        OffsetY: OffsetY,
        blocks_to_add: blocks_to_add,
        blocks_to_remove: blocks_to_remove,
        original_add: blocks_to_add, // Preserve original state for revert
        original_remove: blocks_to_remove, // Preserve original state for revert
        url: url.toString(),
    };

    ajax_queue.push(data);
}

function implode(myArray) {
	let str = "";
	let comma = "";

	for (let i in myArray) {
		if (myArray.hasOwnProperty(i)) {
			str = str + comma + myArray[i];
		}
		comma = ",";
	}

	return str;
}

// Use a debounced resize handler to prevent too many rescale operations
let resizeTimer;
window.addEventListener("resize", function () {
	// Clear the timeout if it exists
	clearTimeout(resizeTimer);
	// Set a new timeout
	resizeTimer = setTimeout(function () {
		// Call the rescale function which handles rebuilding blocks
		rescale_grid();
	}, 100);
});

function initializeGrid() {
	grid = document.getElementById("pixelimg");
	$myblocks = jQuery("#blocks");
	total_cost = document.getElementById("total_cost");
	submit_button1 = document.getElementById("submit_button1");
	pointer = document.getElementById("block_pointer");
	pixel_container = document.getElementById("pixel_container");
	pixel_form = document.getElementById("pixel_form");
	blocksCanvas = document.getElementById('blocks_canvas');

	// Defensive checks for all critical elements (allow canvas mode without #blocks)
	if (!grid || !pixel_container || !pointer) {
		return;
	}

	const preloader = findGridPreloader(grid);
	if (preloader && preloader.length) {
		remove_ajax_loader(preloader);
		preloader.hide();
		preloader.removeData('mdsPreloaderActive');
	}
	const $wrapperInit = jQuery(grid).closest('.mds-pixel-wrapper');
	if ($wrapperInit.length) {
		remove_ajax_loader($wrapperInit);
		$wrapperInit.removeData('mdsPreloaderActive');
	}

	const directFeedbackWrapper = grid ? grid.parentElement : null;
	let feedback = directFeedbackWrapper ? directFeedbackWrapper.querySelector('.mds-grid-feedback') : null;
	if (!feedback && grid) {
		const feedbackContainer = grid.closest('.mds-container');
		feedback = feedbackContainer ? feedbackContainer.querySelector('.mds-grid-feedback') : document.querySelector('.mds-grid-feedback');
	} else if (!feedback) {
		feedback = document.querySelector('.mds-grid-feedback');
	}
	if (feedback) {
		feedback.setAttribute('hidden', '');
		feedback.classList.remove('is-visible');
	}

	// Initial setup
	rescale_grid();
	setupBlocksCanvas();
	// Preload selection image (optional); fallback to fill color
	preloadSelectionImage((MDS_OBJECT && MDS_OBJECT.MDS_CORE_URL ? (MDS_OBJECT.MDS_CORE_URL + 'images/selected_block.png') : ''), function(){
		renderSelectionCanvas();
	});
	load_order();

	// --- Event Listeners ---
	let clickValid = true;
	let startX, startY;
	const threshold = Math.min(BLK_WIDTH, BLK_HEIGHT) / 2;

	// Mouse Events (for desktop)
	jQuery(pixel_container)
		.on("mousedown", function (event) {
			// Ignore non-primary button presses (right/middle click)
			const button = event.which || (event.originalEvent && event.originalEvent.button);
			if (typeof button !== "undefined" && button !== 1) {
				clickValid = false;
				return;
			}
			clickValid = true;
			startX = event.originalEvent.pageX;
			startY = event.originalEvent.pageY;
		})
		.on("mousemove", function (event) {
			if (clickValid) {
				let currentX = event.originalEvent.pageX;
				let currentY = event.originalEvent.pageY;
				if (Math.abs(startX - currentX) > threshold || Math.abs(startY - currentY) > threshold) {
					clickValid = false;
				}
			}
			let coords = center_block({
				x: event.originalEvent.pageX,
				y: event.originalEvent.pageY
			});
			let offset = getOffset(coords.x, coords.y);
			if (offset == null) return false;
			show_pointer(offset);
		})
		.on("click", function (event) {
			event.preventDefault();
			if (clickValid) {
				let coords = center_block({
					x: event.originalEvent.pageX,
					y: event.originalEvent.pageY
				});
				let offset = getOffset(coords.x, coords.y);
				if (offset == null) return false;
				show_pointer(offset);
				select_pixels(offset);
			}
			clickValid = true; // Reset for next click
			return false;
		});

	// Touch Events (for mobile)
	if (has_touch()) {
		let pointerListener = new PointerListener(pixel_container, {
			supportedGestures: [Tap, Pinch, Pan]
		});

		pixel_container.addEventListener("tap", function (event) {
			let offset = getOffset(event.detail.live.center.x, event.detail.live.center.y, true);
			if (offset == null) return true;
			show_pointer(offset);
			select_pixels(offset);
		});

		pixel_container.addEventListener("pinch", function (event) {
			event.preventDefault && event.preventDefault();
			const newScale = event.detail.live.scale;
			if (!newScale || newScale <= 0) return;
			applyZoom(newScale, event.detail.live.center.x, event.detail.live.center.y);
		});

		pixel_container.addEventListener("pan", function (event) {
			if (window.mds_zoom <= 1) return;
			window.mds_pan_x += event.detail.live.deltaX;
			window.mds_pan_y += event.detail.live.deltaY;
			applyTransform();
		});
	}


	// Disable context menu on grid
	jQuery(grid).on("contextmenu", (e) => {
		e.preventDefault();
	});

	// Set up form submission and reset buttons
	if (submit_button1) {
		jQuery(submit_button1).on("click", formSubmit);
	}
	jQuery("#reset_button").on("click", reset_pixels);


	// AJAX queue processor
	setInterval(function () {
		if (ajax_queue.length === 0 || ajaxing) {
			return;
		}
		ajaxing = true;
		const pixelGrid = document.getElementById('pixelimg');
		const controls = pixelGrid ? pixelGrid.mdsGridControls : null;
		const fallbackContainer = jQuery(".mds-pixel-wrapper");
		const usingFallback = !controls;
		if (fallbackContainer && fallbackContainer.length) {
			add_ajax_loader(fallbackContainer);
		}
		if (controls) {
			if (typeof controls.hideFeedback === 'function') {
				controls.hideFeedback();
			}
			if (typeof controls.showPreloader === 'function') {
				controls.showPreloader({ hideImage: false });
			}
		}
		let data = ajax_queue.shift();
		currentAjaxRequest = jQuery.ajax({
			type: "POST",
			url: data.url,
			data: {
				_wpnonce: MDS_OBJECT.NONCE,
				action: data.action,
				block_id: data.clicked_block,
				selection_size: parseInt(jQuery("#mds-selection-size-value").val(), 10) || 1,
				BID: MDS_OBJECT.BID,
				blocks_to_add: data.blocks_to_add.join(','),
				blocks_to_remove: data.blocks_to_remove.join(',')
			},
			success: function (response) {
				if (response.success === true) {
					if (response.data && response.data.data) {
						// Only apply differences beyond what we already optimistically updated
						const srv = response.data.data;
						if (srv.added && Array.isArray(srv.added) && srv.added.length > 0) {
							const extraAdds = srv.added.filter(id => !(data.original_add || []).includes(id));
							if (extraAdds.length) do_blocks(extraAdds, "add");
						}
						if (srv.removed && Array.isArray(srv.removed) && srv.removed.length > 0) {
							const extraRemoves = srv.removed.filter(id => !(data.original_remove || []).includes(id));
							if (extraRemoves.length) do_blocks(extraRemoves, "remove");
						}
						if (srv.type === "order_id" && pixel_form) {
							pixel_form.order_id.value = parseInt(srv.value, 10);
						}
					}
				} else {
					// Revert UI changes on failure
					if (data.action === 'invert') {
						do_blocks(data.original_add, "remove");
						do_blocks(data.original_remove, "add");
					} else {
						do_blocks(data.original_add, "remove");
					}
					if (response.data && response.data.data && response.data.data.value) {
						messageout(response.data.data.value);
					} else {
						messageout("An unknown error occurred.");
					}
				}
			},
		error: function (xhr, status, error) {
			if (status === 'abort') {
				if (suppressLoaderRemoval) {
					suppressLoaderRemoval = false;
				}
				return;
			}
				if (data.action === 'invert') {
					do_blocks(data.original_add, "remove");
					do_blocks(data.original_remove, "add");
				} else {
					do_blocks(data.original_add, "remove");
				}
				// Prefer server-provided message when available (WordPress wp_send_json_error wraps it)
				let serverMsg = null;
				if (xhr && xhr.responseJSON && xhr.responseJSON.data) {
					if (xhr.responseJSON.data.data && xhr.responseJSON.data.data.value) {
						serverMsg = xhr.responseJSON.data.data.value;
					} else if (xhr.responseJSON.data.value) {
						serverMsg = xhr.responseJSON.data.value;
					}
				}
				if (serverMsg) {
					messageout(serverMsg);
				} else {
					messageout("Request failed: " + status);
				}
			},
			complete: function (jqXHR, textStatus) {
				if (currentAjaxRequest === jqXHR) {
					currentAjaxRequest = null;
				}
				ajaxing = false;
				if (textStatus === 'abort' && suppressLoaderRemoval) {
					suppressLoaderRemoval = false;
					return;
				}
				// Only remove loader when queue is fully drained
				if (ajax_queue.length === 0) {
					if (!usingFallback && controls) {
						if (typeof controls.hidePreloader === 'function') {
							controls.hidePreloader();
						}
						if (typeof controls.hideFeedback === 'function') {
							controls.hideFeedback();
						}
						if (typeof controls.showImage === 'function') {
							controls.showImage();
						}
						if (typeof controls.showPointer === 'function') {
							controls.showPointer();
						}
					}
					remove_ajax_loader(fallbackContainer);
				}
			}
		});
	}, 100);

	// Size selection slider and input logic
	const $mds_selection_size_slider = jQuery("#mds-selection-size-slider");
	const $mds_selection_size_value = jQuery("#mds-selection-size-value");
	const $mds_total_blocks_value = jQuery("#mds-total-blocks-value");

	if ($mds_selection_size_slider.length > 0) {
		$mds_selection_size_slider.on("input", function () {
			const blockSize = parseInt(jQuery(this).val());
			const adjustedBlockSize = Math.min(blockSize, Math.min(GRD_WIDTH, GRD_HEIGHT));
			$mds_selection_size_value.val(adjustedBlockSize);
			$mds_total_blocks_value.val(Math.pow(adjustedBlockSize, 2));
			update_pointer_size();
		});

		$mds_selection_size_value.on("input", function () {
			const blockSize = parseInt(jQuery(this).val());
			const adjustedBlockSize = Math.min(blockSize, Math.min(GRD_WIDTH, GRD_HEIGHT));
			$mds_selection_size_slider.val(adjustedBlockSize);
			$mds_total_blocks_value.val(Math.pow(adjustedBlockSize, 2));
			update_pointer_size();
		});

		let previousTotalBlocks = parseInt($mds_total_blocks_value.val());
		let updateTimer;
		$mds_total_blocks_value.on("input", function () {
			let totalBlocks = parseInt(jQuery(this).val());
			const isIncreasing = totalBlocks > previousTotalBlocks;
			let blockSize = isIncreasing ? Math.ceil(Math.sqrt(totalBlocks)) : Math.floor(Math.sqrt(totalBlocks));
			const adjustedBlockSize = Math.min(blockSize, Math.min(GRD_WIDTH, GRD_HEIGHT));
			$mds_selection_size_slider.val(adjustedBlockSize);
			$mds_selection_size_value.val(adjustedBlockSize);
			clearTimeout(updateTimer);
			updateTimer = setTimeout(() => {
				totalBlocks = Math.pow(adjustedBlockSize, 2);
				$mds_total_blocks_value.val(totalBlocks);
				previousTotalBlocks = totalBlocks;
				update_pointer_size();
			}, 1000);
		});
	}

	// Reset zoom button
	if (jQuery("#reset_zoom_button").length === 0) {
		const resetZoomButton = jQuery("<button>", {
			id: "reset_zoom_button",
			class: "mds-reset-zoom",
			text: "Reset Zoom",
			css: {
				position: "absolute",
				right: "10px",
				top: "10px",
				"z-index": "1000",
				padding: "5px 10px",
				background: "rgba(0,0,0,0.5)",
				color: "white",
				border: "none",
				"border-radius": "4px",
				display: "none",
				cursor: "pointer",
			},
		});
		jQuery(".mds-pixel-wrapper").append(resetZoomButton);
		resetZoomButton.on("click", resetZoom);
	}
}

// --- New Robust Initialization Logic ---
var gridInitialized = false;

// Use MutationObserver to reliably detect when the grid is added to the DOM
const observerTarget = document.body;
const observerConfig = {
	childList: true,
	subtree: true
};

const mutationObserver = new MutationObserver((mutationsList, observer) => {
	if (gridInitialized) {
		return;
	}

	for (const mutation of mutationsList) {
		if (mutation.type === 'childList') {
			const pixelGrid = document.getElementById('pixelimg');
			if (pixelGrid) {
				show_initial_grid_loader(pixelGrid);
				setupGridImageFeedback(pixelGrid, observer);
				break;
			}
		}
	}
});

// Start observing the document body for changes
mutationObserver.observe(observerTarget, observerConfig);

const existingPixelGrid = document.getElementById('pixelimg');
if (existingPixelGrid) {
	show_initial_grid_loader(existingPixelGrid);
	setupGridImageFeedback(existingPixelGrid, mutationObserver);
}

// Function to apply zoom to the grid container
function applyZoom(scaleChange, centerX, centerY) {
	// We limit the max zoom to 3x for usability
	const maxZoom = 3;

	// Calculate new zoom level
	const oldZoom = window.mds_zoom || 1;
	const newZoom = Math.min(maxZoom, oldZoom * scaleChange);

	// Don't allow zooming out below 1x (original size)
	if (newZoom < 1) {
		resetZoom();
		return;
	}

	// Store the new zoom level
	window.mds_zoom = newZoom;

	// Show the reset zoom button when zoomed in
	if (newZoom > 1) {
		jQuery("#reset_zoom_button").show();
	} else {
		jQuery("#reset_zoom_button").hide();
	}

	// Apply the transformation
	applyTransform();
}

// Function to apply the current transform (zoom and pan)
function applyTransform() {
	const wrapper = jQuery(".mds-pixel-wrapper");
	if (!wrapper.length) return;

	// Apply transform with current zoom and pan values
	const transform = `scale(${window.mds_zoom}) translate(${window.mds_pan_x / window.mds_zoom}px, ${window.mds_pan_y / window.mds_zoom}px)`;

	// Apply transformation to wrapper
	wrapper.css({
		transform: transform,
		"transform-origin": "top left",
		transition: "transform 0.1s",
	});

	// Also rescale grid to ensure coordinates are updated
	rescale_grid();
}

// Function to reset zoom to original size
function resetZoom() {
	// Reset zoom and pan values
	window.mds_zoom = 1;
	window.mds_pan_x = 0;
	window.mds_pan_y = 0;

	// Reset transformation
	const wrapper = jQuery(".mds-pixel-wrapper");
	wrapper.css({
		transform: "none",
		transition: "transform 0.3s",
	});

	// Hide reset zoom button
	jQuery("#reset_zoom_button").hide();

	// Ensure coordinates are updated
	rescale_grid();
}

// Function for scaling image map coordinates based on current image dimensions
function scaleImageMap() {
	// Get the image element
	var img = document.getElementById("pixelimg");
	if (!img) return;

	// Get original dimensions from data attributes or MDS_OBJECT
	var origWidth =
		parseInt(img.getAttribute("data-original-width"), 10) ||
		(MDS_OBJECT.grid_data && MDS_OBJECT.grid_data.orig_width_px) ||
		MDS_OBJECT.grid_width * MDS_OBJECT.BLK_WIDTH;

	var origHeight =
		parseInt(img.getAttribute("data-original-height"), 10) ||
		(MDS_OBJECT.grid_data && MDS_OBJECT.grid_data.orig_height_px) ||
		MDS_OBJECT.grid_height * MDS_OBJECT.BLK_HEIGHT;

	// Get current dimensions
	var currentWidth = img.clientWidth || img.offsetWidth;
	var currentHeight = img.clientHeight || img.offsetHeight;

	// Calculate scaling factors
	var scaleX = currentWidth / origWidth;
	var scaleY = currentHeight / origHeight;

	// Scale the grid blocks positioning if necessary
	var blocks = document.querySelectorAll(".gridblock");
	if (blocks && blocks.length > 0) {
		for (var i = 0; i < blocks.length; i++) {
			var block = blocks[i];
			var x = parseInt(block.getAttribute("data-x"), 10) || 0;
			var y = parseInt(block.getAttribute("data-y"), 10) || 0;

			if (x && y) {
				var scaledX = Math.round(x * scaleX);
				var scaledY = Math.round(y * scaleY);
				block.style.left = scaledX + "px";
				block.style.top = scaledY + "px";
			}
		}
	}

	// Scale the pointer position if needed
	if (
		window.mds_pointer_x !== undefined &&
		window.mds_pointer_y !== undefined
	) {
		var scaledX = Math.round(window.mds_pointer_x * scaleX);
		var scaledY = Math.round(window.mds_pointer_y * scaleY);
		var pointer = document.getElementById("block_pointer");
		if (pointer) {
			pointer.style.left = scaledX + "px";
			pointer.style.top = scaledY + "px";
		}
	}
}

// Make the function globally available for other functions
window.scaleImageMap = scaleImageMap;
