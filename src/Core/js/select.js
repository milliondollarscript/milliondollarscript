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

// Initialize
let first_load = true;
let ajax_queue = [];
let USE_AJAX = MDS_OBJECT.USE_AJAX;
let block_str = MDS_OBJECT.block_str;
let selectedBlocks = block_str !== "" ? block_str.split(",").map(Number) : [];
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

function add_ajax_loader(container) {
	let $ajax_loader = jQuery("<div class='ajax-loader'></div>");
	jQuery(container).append($ajax_loader);
	$ajax_loader
		.css("top", jQuery(container).position().top)
		.css("left", jQuery(container).width() / 2 - $ajax_loader.width() / 2);
}

function remove_ajax_loader() {
	jQuery(".ajax-loader").remove();
}

const messageout = function (message) {
	if (debug) {
		console.log(message);
	} else {
		alert(message);
	}
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

	let id = parseInt(jQuery(this).data("blockid"), 10);

	// Use the same get_block_position function we use in add_block
	// This ensures consistent positioning logic throughout the app
	let pos = get_block_position(id);

	// Apply current scaling and offsets
	this.css({
		// Convert block coordinates to pixels using current scaling
		top: pos.y * BLK_HEIGHT + gridOffsetTop + "px",
		left: pos.x * BLK_WIDTH + gridOffsetLeft + "px",
		width: BLK_WIDTH + "px",
		height: BLK_HEIGHT + "px",
	});

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
		pixel_form.selected_pixels.value = selectedBlocks.join(",");
	}
}

function reserve_block(block_id) {
	if (selectedBlocks.indexOf(block_id) === -1) {
		selectedBlocks.push(parseInt(block_id, 10));

		// remove default value of -1 from array
		let index = selectedBlocks.indexOf(-1);
		if (index > -1) {
			selectedBlocks.splice(index, 1);
		}

		update_order();
	}
}

function unreserve_block(block_id) {
	let index = selectedBlocks.indexOf(block_id);
	if (index > -1) {
		selectedBlocks.splice(index, 1);
		update_order();
	}
}

function add_block(block_id, block_x, block_y) {
	// Calculate position based on block_id grid coordinates
	// The get_block_position function returns block coordinates (not pixels)
	let pos = get_block_position(block_id);

	// Apply current scaling and grid offset to convert to pixels
	let block_left = pos.x * BLK_WIDTH + gridOffsetLeft;
	let block_top = pos.y * BLK_HEIGHT + gridOffsetTop;

	// Create a document fragment
	let fragment = document.createDocumentFragment();

	// Create the block element
	let $new_block = jQuery("<span>", {
		id: "block" + block_id.toString(),
		css: {
			left: block_left + "px",
			top: block_top + "px",
			lineHeight: BLK_HEIGHT + "px",
			fontSize: BLK_HEIGHT + "px",
			width: BLK_WIDTH + "px",
			height: BLK_HEIGHT + "px",
		},
	}).addClass("mds-block");

	// Create the block image element
	let $new_img = jQuery("<img>", {
		alt: "",
		src: MDS_OBJECT.MDS_CORE_URL + "images/selected_block.png",
		css: {
			lineHeight: BLK_HEIGHT + "px",
			fontSize: BLK_HEIGHT + "px",
			width: BLK_WIDTH + "px",
			height: BLK_HEIGHT + "px",
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
	let myblock = document.getElementById("block" + block_id.toString());
	if (myblock !== null) {
		myblock.remove();
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

function is_block_selected(clicked_block) {
	return selectedBlocks.indexOf(clicked_block) !== -1;
}

function get_clicked_blocks(OffsetX, OffsetY, block) {
	let clicked_blocks = [];
	let x;
	let y;

	// Additional blocks if multiple selection radio buttons are selected
	const selectedSize = parseInt(jQuery("#mds-selection-size-value").val(), 10);
	if (selectedSize > 1) {
		for (let i = 0; i < selectedSize; i++) {
			for (let j = 0; j < selectedSize; j++) {
				x = OffsetX + i * BLK_WIDTH;
				y = OffsetY + j * BLK_HEIGHT;
				// Ensure the block is within the grid boundaries
				if (x >= 0 && x < GRD_WIDTH && y >= 0 && y < GRD_HEIGHT) {
					clicked_blocks.push({
						id: get_clicked_block(x, y),
						x: x,
						y: y,
					});
				}
			}
		}
	} else {
		// Actual clicked block
		x = OffsetX;
		y = OffsetY;
		clicked_blocks.push({
			id: block,
			x: x,
			y: y,
		});
	}

	return clicked_blocks;
}

function do_blocks(block, OffsetX, OffsetY, op) {
	let clicked_blocks = get_clicked_blocks(OffsetX, OffsetY, block);
	for (const clicked_block of clicked_blocks) {
		if (op === "add") {
			// add block
			add_block(clicked_block.id, clicked_block.x, clicked_block.y);
		} else if (op === "remove") {
			// remove block
			remove_block(clicked_block.id);
		} else if (op === "invert") {
			// invert block
			invert_block(clicked_block);
		}
	}

	// Bind the mousemove event using event delegation
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
	// pointer.style.visibility = 'hidden';

	change_block_state(offset.x, offset.y);

	return true;
}

function load_order() {
	if (MDS_OBJECT.blocks.length === 0) {
		return;
	}
	for (let i = 0; i < MDS_OBJECT.blocks.length; i++) {
		add_block(
			parseInt(MDS_OBJECT.blocks[i].block_id, 10),
			parseInt(MDS_OBJECT.blocks[i].x, 10) * scaled_width,
			parseInt(MDS_OBJECT.blocks[i].y, 10) * scaled_height,
		);
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

function getOffset(x, y, touch) {
	if (grid == null) {
		// grid may not be loaded yet
		return null;
	}

	let pos = getObjCoords(grid);
	let size = get_pointer_size();

	let offset = {};
	let scrollLeft = 0;
	let scrollTop = 0;

	if (touch) {
		scrollLeft = document.documentElement.scrollLeft;
		scrollTop = document.documentElement.scrollTop;
	}

	offset.x = x - pos.x + scrollLeft;
	offset.y = y - pos.y + scrollTop;

	// drop 1/10 from the OffsetX and OffsetY, eg 612 becomes 610
	// expand to original scale first
	offset.x =
		Math.floor(offset.x / scaled_width / orig.BLK_WIDTH) * orig.BLK_WIDTH;
	offset.y =
		Math.floor(offset.y / scaled_height / orig.BLK_HEIGHT) * orig.BLK_HEIGHT;

	// keep within range
	offset.x = Math.max(
		Math.min(offset.x, GRD_WIDTH - size.width / scaled_width),
		0,
	);
	offset.y = Math.max(
		Math.min(offset.y, GRD_HEIGHT - size.height / scaled_height),
		0,
	);

	// scale back down if necessary
	offset.x = offset.x * scaled_width;
	offset.y = offset.y * scaled_height;

	return offset;
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
	let size = get_pointer_size();
	pointer.style.width = size.width + "px";
	pointer.style.height = size.height + "px";
}

function show_pointer(offset) {
	pointer.style.visibility = "visible";
	pointer.style.display = "block";

	pointer.style.top = offset.y + "px";
	pointer.style.left = offset.x + "px";

	pointer.map_x = offset.x;
	pointer.map_y = offset.y;

	update_pointer_size();

	return true;
}

function formSubmit(event) {
	event.preventDefault();
	event.stopPropagation();

	if ($myblocks.html().trim() === "") {
		messageout(MDS_OBJECT.no_blocks_selected);
		return false;
	}

	if (submitting === false) {
		submitting = true;
		submit_button1.disabled = true;
		let submit1_lang = submit_button1.value;
		submit_button1.value = MDS_OBJECT.WAIT;

		// Wait for ajax queue to finish
		let waitInterval = setInterval(function () {
			if (ajax_queue.length === 0) {
				clearInterval(waitInterval);
				if (pixel_form !== null) {
					// Set selected_pixels hidden input before submit
					let selectedPixelsInput = document.getElementById("selected_pixels");
					if (selectedPixelsInput) {
						selectedPixelsInput.value = selectedBlocks.join(",");
					}
					mds_update_package(jQuery(pixel_form));
					pixel_form.submit();
				}
				// Keep button disabled and showing wait message during redirect
				// The page will redirect after form submission
				// submit_button1.disabled = false;
				// submit_button1.value = submit1_lang;
				// submitting = false;
			}
		}, 1000);
	}
}

function reset_pixels() {
	ajax_queue = [];

	add_ajax_loader(".mds-pixel-wrapper");

	jQuery.ajax({
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
				$myblocks.children().each(function () {
					remove_block(jQuery(this).data("blockid"));
				});
			}
		},
		complete: function () {
			remove_ajax_loader();
		},
	});
}

function rescale_grid() {
	if (grid == null) {
		// grid may not be loaded yet
		return;
	}

	// Get original dimensions from data attributes
	let origWidth =
		parseInt(jQuery(grid).attr("data-original-width"), 10) || orig.GRD_WIDTH;
	let origHeight =
		parseInt(jQuery(grid).attr("data-original-height"), 10) || orig.GRD_HEIGHT;

	// Get current dimensions
	grid_width = jQuery(grid).width();
	grid_height = jQuery(grid).height();

	// Calculate scaling factors based on actual rendered dimensions compared to original dimensions
	scaled_width = grid_width / origWidth;
	scaled_height = grid_height / origHeight;

	// Also call scaleImageMap to ensure grid blocks are positioned properly
	if (typeof scaleImageMap === "function") {
		scaleImageMap();
	}

	// Update block dimensions based on scaling factors
	BLK_WIDTH = orig.BLK_WIDTH * scaled_width;
	BLK_HEIGHT = orig.BLK_HEIGHT * scaled_height;

	// Update grid offsets
	gridOffsetLeft = grid.offsetLeft;
	gridOffsetTop = grid.offsetTop;

	// Update the pointer element
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

function handle_click_events() {
	let clickValid = true;
	let startX, startY;

	// pixels of movement to tolerate set to half of the smallest of the block dimensions
	const threshold = Math.min(BLK_WIDTH, BLK_HEIGHT) / 2;

	jQuery(pixel_container).on("mousedown", function (event) {
		clickValid = true;
		startX = event.originalEvent.pageX;
		startY = event.originalEvent.pageY;
	});

	jQuery(pixel_container).on("mousemove", function (event) {
		if (clickValid) {
			let currentX = event.originalEvent.pageX;
			let currentY = event.originalEvent.pageY;
			// If the mouse has moved more than the threshold, invalidate the click
			if (
				Math.abs(startX - currentX) > threshold ||
				Math.abs(startY - currentY) > threshold
			) {
				clickValid = false;
			}
		}

		let coords = center_block({
			x: event.originalEvent.pageX,
			y: event.originalEvent.pageY,
		});
		let offset = getOffset(coords.x, coords.y);
		if (offset == null) {
			return false;
		}
		show_pointer(offset);
	});

	jQuery(pixel_container).on("click", function (event) {
		event.preventDefault();
		if (clickValid) {
			let coords = center_block({
				x: event.originalEvent.pageX,
				y: event.originalEvent.pageY,
			});
			let offset = getOffset(coords.x, coords.y);
			if (offset == null) {
				return false;
			}
			show_pointer(offset);
			select_pixels(offset);
		}
		clickValid = true;
		return false;
	});
}

function handle_touch_events() {
	// Current zoom level and last-known pan position
	if (window.mds_zoom === undefined) {
		window.mds_zoom = 1;
		window.mds_pan_x = 0;
		window.mds_pan_y = 0;
	}

	// Add Pinch gesture for zooming
	let options = {
		supportedGestures: [Tap, Pinch, Pan],
	};

	let pointerListener = new PointerListener(pixel_container, options);

	// Handle tap gestures for selecting pixels
	pixel_container.addEventListener("tap", function (event) {
		let offset = getOffset(
			event.detail.live.center.x,
			event.detail.live.center.y,
			true,
		);
		if (offset == null) {
			return true;
		}

		show_pointer(offset);
		select_pixels(offset);
	});

	// Handle pinch gestures for zooming
	pixel_container.addEventListener("pinch", function (event) {
		// Prevent default browser actions like page zooming
		event.preventDefault && event.preventDefault();

		// Get the scale value from the pinch event
		const newScale = event.detail.live.scale;
		if (!newScale || newScale <= 0) return;

		// Apply scaling to the grid container
		applyZoom(newScale, event.detail.live.center.x, event.detail.live.center.y);
	});

	// Handle pan gestures for moving around when zoomed in
	pixel_container.addEventListener("pan", function (event) {
		// Only allow panning when zoomed in
		if (window.mds_zoom <= 1) return;

		// Update pan position
		window.mds_pan_x += event.detail.live.deltaX;
		window.mds_pan_y += event.detail.live.deltaY;

		// Apply the transformation
		applyTransform();
	});
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
	OffsetX /= scaled_width;
	OffsetY /= scaled_height;

	let X = OffsetX / orig.BLK_WIDTH;
	let Y = (OffsetY / orig.BLK_HEIGHT) * (orig.GRD_WIDTH / orig.BLK_WIDTH);

	return Math.round(X + Y);
}

function change_block_state(OffsetX, OffsetY) {
	let clicked_block = get_clicked_block(OffsetX, OffsetY);
	let erasing = jQuery("#erase").is(":checked");
	let selection_size = parseInt(pixel_form.elements.selection_size.value, 10);

	let data = {
		erasing: erasing,
		clicked_block: clicked_block,
		OffsetX: OffsetX,
		OffsetY: OffsetY,
		// Use URL object to correctly append params
		url: (() => {
			const url = new URL(MDS_OBJECT.UPDATE_ORDER, window.location.origin);
			url.searchParams.set("selection_size", selection_size);
			url.searchParams.set("user_id", MDS_OBJECT.user_id);
			url.searchParams.set("block_id", clicked_block.toString());
			url.searchParams.set("BID", MDS_OBJECT.BID);
			url.searchParams.set("t", MDS_OBJECT.time);
			url.searchParams.set("erase", erasing);
			// Also add the nonce here for the AJAX request
			url.searchParams.set("_wpnonce", MDS_OBJECT.NONCE);
			return url.toString();
		})(),
	};

	if (is_block_selected(clicked_block)) {
		if (erasing) {
			data.action = "remove";
			do_blocks(clicked_block, OffsetX, OffsetY, "remove");
		} else {
			if (MDS_OBJECT.INVERT_PIXELS === "YES") {
				data.action = "invert";
				do_blocks(clicked_block, OffsetX, OffsetY, "invert");
			} else {
				data.action = "add";
				do_blocks(clicked_block, OffsetX, OffsetY, "add");
			}
		}
	} else {
		if (erasing) {
			data.action = "remove";
			do_blocks(clicked_block, OffsetX, OffsetY, "remove");
		} else {
			if (MDS_OBJECT.INVERT_PIXELS === "YES") {
				data.action = "invert";
				do_blocks(clicked_block, OffsetX, OffsetY, "invert");
			} else {
				data.action = "add";
				do_blocks(clicked_block, OffsetX, OffsetY, "add");
			}
		}
	}

	// Add data to ajax queue
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

jQuery(document).on("ajaxComplete", function (event, xhr, settings) {
	console.log("ajaxComplete");
	const params = new URLSearchParams(settings.data);
	const type = params.get("type");

	// Check if the type parameter is order
	if (type !== "order") {
		return;
	}

	if (first_load) {
		first_load = false;
	} else {
		return;
	}

	grid = document.getElementById("pixelimg");
	let $grid = jQuery(grid);
	$myblocks = jQuery("#blocks");
	total_cost = document.getElementById("total_cost");
	submit_button1 = document.getElementById("submit_button1");
	pointer = document.getElementById("block_pointer");
	pixel_container = document.getElementById("pixel_container");
	pixel_form = document.getElementById("pixel_form");

	rescale_grid();

	if ($grid.length === 0) {
		remove_ajax_loader();
	}

	if (has_touch()) {
		handle_touch_events();
	} else {
		handle_click_events();
	}

	// disable context menu on grid
	jQuery(grid).oncontextmenu = (e) => {
		e.preventDefault();
	};

	let ajax_queue_interval = setInterval(function () {
		if (ajax_queue.length === 0 || ajaxing) {
			return;
		}

		ajaxing = true;

		add_ajax_loader(".mds-pixel-wrapper");

		let data = ajax_queue.shift();

		jQuery.ajax({
			type: "POST",
			url: data.url,
			data: {
				_wpnonce: MDS_OBJECT.NONCE,
				erasing: data.erasing,
				block_id: data.clicked_block,
				selection_size: parseInt(jQuery("#mds-selection-size-value").val(), 10) || 1,
				BID: MDS_OBJECT.BID,
			},
			success: function (response) {
				if (
					response.success !== true ||
					(response.data && response.data.error === "true")
				) {
					switch (data.action) {
						case "invert":
							do_blocks(
								data.clicked_block,
								data.OffsetX,
								data.OffsetY,
								"invert",
							);
							break;
						case "remove":
							if (!data.erasing) {
								do_blocks(
									data.clicked_block,
									data.OffsetX,
									data.OffsetY,
									"add",
								);
							}
							break;
						case "add":
							do_blocks(
								data.clicked_block,
								data.OffsetX,
								data.OffsetY,
								"remove",
							);
							break;
					}

					// Use the correctly nested path for the error message
					messageout(response.data.data.value);
				}

				if (response.data && response.data.type === "order_id") {
					// save order id
					if (pixel_form !== null) {
						pixel_form.order_id.value = parseInt(response.data.data.value, 10);
					}
				}
			},
			fail: function () {
				switch (data.action) {
					case "invert":
						do_blocks(data.clicked_block, data.OffsetX, data.OffsetY, "invert");
						break;
					case "remove":
						do_blocks(data.clicked_block, data.OffsetX, data.OffsetY, "add");
						break;
					case "add":
						do_blocks(data.clicked_block, data.OffsetX, data.OffsetY, "remove");
						break;
				}

				if (jQuery.isPlainObject(data)) {
					messageout("Error: " + JSON.stringify(data));
				} else {
					messageout("Error: " + data);
				}
			},
			complete: function () {
				ajaxing = false;
				remove_ajax_loader();
			},
		});
	}, 100);

	document
		.querySelectorAll('.mds-select-input input[type="checkbox"]')
		.forEach(function (radio) {
			radio.addEventListener("hover", function () {
				this.style.cursor = "pointer";
			});
			radio.addEventListener("change", function () {
				const label = this.nextElementSibling;
				const iconErase = label.querySelector(".icon-erase");
				const iconSelect = label.querySelector(".icon-select");

				if (iconErase && iconSelect) {
					if (this.checked) {
						iconErase.setAttribute("stroke", "#FFFFFF");
						iconSelect.setAttribute("stroke", "none");
					} else {
						iconErase.setAttribute("stroke", "none");
						iconSelect.setAttribute("stroke", "#008000");
					}
				}
			});
		});

	const $pixelimg = jQuery("#pixelimg");
	$pixelimg
		.one("load", function () {
			rescale_grid();
			load_order();
			jQuery(".ajax-loader").remove();
		})
		.each(function () {
			if (this.complete) {
				jQuery(this).trigger("load");
			}
		});
	$pixelimg.on("loadstart", function () {
		add_ajax_loader(".mds-pixel-wrapper");
	});

	function add_ajax_loader(container) {
		let $ajax_loader = jQuery("<div class='ajax-loader'></div>");
		let $container = jQuery(container);
		if ($container.length > 0) {
			$container.append($ajax_loader);
			$ajax_loader
				.css("z-index", "10000")
				.css("top", $container.position().top)
				.css("left", jQuery(container).width() / 2 - $ajax_loader.width() / 2);
		}
	}

	add_ajax_loader(".mds-pixel-wrapper");

	const $mds_selection_size_slider = jQuery("#mds-selection-size-slider");
	const $mds_selection_size_value = jQuery("#mds-selection-size-value");
	const $mds_total_blocks_value = jQuery("#mds-total-blocks-value");

	if ($mds_selection_size_slider.length > 0) {
		$mds_selection_size_slider.on("input", function () {
			const blockSize = parseInt(jQuery(this).val());
			console.log("blockSize", blockSize);
			const adjustedBlockSize = Math.min(
				blockSize,
				Math.min(GRD_WIDTH, GRD_HEIGHT),
			);
			$mds_selection_size_value.val(adjustedBlockSize);
			$mds_total_blocks_value.val(Math.pow(adjustedBlockSize, 2));
			update_pointer_size();
		});

		$mds_selection_size_value.on("input", function () {
			const blockSize = parseInt(jQuery(this).val());
			const adjustedBlockSize = Math.min(
				blockSize,
				Math.min(GRD_WIDTH, GRD_HEIGHT),
			);
			$mds_selection_size_slider.val(adjustedBlockSize);
			$mds_total_blocks_value.val(Math.pow(adjustedBlockSize, 2));
			update_pointer_size();
		});

		let previousTotalBlocks = parseInt($mds_total_blocks_value.val());
		let updateTimer;

		$mds_total_blocks_value.on("input", function () {
			let totalBlocks = parseInt(jQuery(this).val());
			const isIncreasing = totalBlocks > previousTotalBlocks;
			let blockSize;

			if (isIncreasing) {
				// Snap up to the next perfect square
				blockSize = Math.ceil(Math.sqrt(totalBlocks));
			} else {
				// Snap down to the previous perfect square
				blockSize = Math.floor(Math.sqrt(totalBlocks));
			}

			const adjustedBlockSize = Math.min(
				blockSize,
				Math.min(GRD_WIDTH, GRD_HEIGHT),
			);

			// Update the slider and selection size input
			$mds_selection_size_slider.val(adjustedBlockSize);
			$mds_selection_size_value.val(adjustedBlockSize);

			clearTimeout(updateTimer);

			updateTimer = setTimeout(() => {
				totalBlocks = Math.pow(adjustedBlockSize, 2);

				// Set the total blocks value to the adjusted value
				$mds_total_blocks_value.val(totalBlocks);

				// Update previous total blocks for next change
				previousTotalBlocks = totalBlocks;

				update_pointer_size();
			}, 1000);
		});
	}

	jQuery(submit_button1).on("click", function (e) {
		e.preventDefault();
		e.stopPropagation();
		formSubmit(e);
		return false;
	});

	jQuery("#reset_button").on("click", function (e) {
		e.preventDefault();
		e.stopPropagation();
		reset_pixels();
		return false;
	});

	// Add reset zoom button if it doesn't exist yet
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

		resetZoomButton.on("click", function () {
			resetZoom();
		});
	}
});

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
		"transform-origin": "center center",
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
