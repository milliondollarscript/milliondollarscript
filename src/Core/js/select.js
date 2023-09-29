/*
 * Million Dollar Script Two
 *
 * @version     2.5.1
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

let debug = false;

// Initialize
let USE_AJAX = select.USE_AJAX;
let block_str = select.block_str;
let selectedBlocks = block_str !== '-1' ? block_str.split(',').map(Number) : [];
let selecting = false;
let ajaxing = false;
let submitting = false;

let grid_width = select.grid_width;
let grid_height = select.grid_height;

let BLK_WIDTH = select.BLK_WIDTH;
let BLK_HEIGHT = select.BLK_HEIGHT;

let GRD_WIDTH = BLK_WIDTH * grid_width;
let GRD_HEIGHT = BLK_HEIGHT * grid_height;

let orig = {
	grid_width: grid_width,
	grid_height: grid_height,
	BLK_WIDTH: BLK_WIDTH,
	BLK_HEIGHT: BLK_HEIGHT,
	GRD_WIDTH: GRD_WIDTH,
	GRD_HEIGHT: GRD_HEIGHT
};

let scaled_width = 1;
let scaled_height = 1;

let myblocks;
let total_cost;
let grid;
let submit_button1;
let pointer;
let pixel_container;

function add_ajax_loader(container) {
	let $ajax_loader = jQuery("<div class='ajax-loader'></div>");
	jQuery(container).append($ajax_loader)
	$ajax_loader.css('top', jQuery(container).position().top).css('left', (jQuery(container).width() / 2) - ($ajax_loader.width() / 2));
}

function remove_ajax_loader() {
	jQuery('.ajax-loader').remove();
}

const messageout = function (message) {
	if (debug) {
		console.log(message);
	} else {
		alert(message);
	}
}

jQuery.fn.rescaleStyles = function () {
	this.css({
		'width': BLK_WIDTH + 'px',
		'height': BLK_HEIGHT + 'px',
		'line-height': BLK_HEIGHT + 'px',
		'font-size': BLK_HEIGHT + 'px',
	});

	return this;
};

jQuery.fn.repositionStyles = function () {
	if (this.attr('id') === undefined) {
		return this;
	}

	let id = parseInt(jQuery(this).data('blockid'), 10);
	let pos = get_block_position(id);

	this.css({
		'top': ((pos.y * scaled_height) + grid.offsetTop) + 'px',
		'left': ((pos.x * scaled_width) + grid.offsetLeft) + 'px'
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

document.querySelectorAll('.mds-select-radio input[type="radio"]').forEach(function (radio) {
	radio.addEventListener('hover', function () {
		this.style.cursor = 'pointer';
	});
	radio.addEventListener('change', function () {
		const label = this.nextElementSibling;
		const iconErase = label.querySelector('.icon-erase');
		const iconSelect = label.querySelector('.icon-select');

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

window.onload = function () {
	grid = document.getElementById("pixelimg");
	let $grid = jQuery(grid);
	myblocks = document.getElementById('blocks');
	total_cost = document.getElementById('total_cost');
	submit_button1 = document.getElementById('submit_button1');
	pointer = document.getElementById('block_pointer');
	pixel_container = document.getElementById('pixel_container');

	load_order();

	if ($grid.length === 0) {
		remove_ajax_loader();
	}

	window.onresize = rescale_grid;

	if (has_touch()) {
		handle_touch_events();
	} else {
		handle_click_events();
	}

	rescale_grid();

	// disable context menu on grid
	jQuery(grid).oncontextmenu = (e) => {
		e.preventDefault();
	}
};

let grid_interval;

function on_grid_load() {
	jQuery("#pixelimg").one("load", function () {
		jQuery('.ajax-loader').remove();
		clearInterval(grid_interval);
	}).each(function () {
		if (this.complete) {
			jQuery(this).trigger('load');
		}
	});
	jQuery("#pixelimg").on("loadstart", function () {
		add_ajax_loader(".mds-pixel-wrapper");
	});
}

grid_interval = setInterval(on_grid_load, 100);

(function ($) {
	function add_ajax_loader(container) {
		let $ajax_loader = $("<div class='ajax-loader'></div>");
		$(container).append($ajax_loader)
		$ajax_loader.css('top', $(container).position().top).css('left', ($(container).width() / 2) - ($ajax_loader.width() / 2));
	}

	add_ajax_loader('.mds-container');
})(jQuery);

function load_order() {

	for (let i = 0; i < select.blocks.length; i++) {
		add_block(parseInt(select.blocks[i].block_id, 10), parseInt(select.blocks[i].x, 10) * scaled_width, parseInt(select.blocks[i].y, 10) * scaled_height, true);
	}

	const pixel_form = document.getElementById('pixel_form');
	if (pixel_form !== null) {
		pixel_form.addEventListener('submit', formSubmit);
	}
}

function update_order() {
	if (selectedBlocks.length > 0) {
		document.pixel_form.selected_pixels.value = selectedBlocks.join(',');
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

function add_block(block_id, block_x, block_y, loading) {

	let block_left;
	let block_top;

	// grid clicked
	if (block_x == null || block_y == null) {
		block_left = pointer.map_x + grid.offsetLeft;
		block_top = pointer.map_y + grid.offsetTop;

	} else if (loading !== true) {
		block_left = block_x + grid.offsetLeft;
		block_top = block_y + grid.offsetTop;
	}

	// block element
	let $new_block = jQuery('<span>');
	jQuery(myblocks).append($new_block);

	$new_block.attr('id', 'block' + block_id.toString());

	$new_block.css({
		'left': block_left + 'px',
		'top': block_top + 'px',
		'line-height': BLK_HEIGHT + 'px',
		'font-size': BLK_HEIGHT + 'px',
		'width': BLK_WIDTH + 'px',
		'height': BLK_HEIGHT + 'px',
	});

	if (!has_touch()) {
		$new_block.on('mousemove', function ($event) {
			let offset = getOffset($event.originalEvent.pageX, $event.originalEvent.pageY);
			if (offset == null) {
				return false;
			}

			show_pointer(offset);
		});
	}

	jQuery($new_block).data('blockid', block_id);

	// block image
	let $new_img = jQuery('<img alt="" src="">');
	$new_block.append($new_img);

	$new_img.attr('src', select.MDS_CORE_URL + 'images/selected_block.png');

	$new_img.css({
		'line-height': BLK_HEIGHT + 'px',
		'font-size': BLK_HEIGHT + 'px',
		'width': BLK_WIDTH + 'px',
		'height': BLK_HEIGHT + 'px',
	});

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

	// actual clicked block
	x = OffsetX;
	y = OffsetY;
	clicked_blocks.push({
		id: block,
		x: x,
		y: y
	});

	// additional blocks if multiple selection radio buttons are selected
	const sel4 = document.getElementById('sel4');
	if (sel4 !== null && sel4.checked) {
		// select 4 - 4x4

		x = OffsetX + BLK_WIDTH;
		y = OffsetY;
		clicked_blocks.push({
			id: get_clicked_block(x, y),
			x: x,
			y: y
		});

		x = OffsetX;
		y = OffsetY + BLK_HEIGHT;
		clicked_blocks.push({
			id: get_clicked_block(x, y),
			x: x,
			y: y
		});

		x = OffsetX + BLK_WIDTH;
		y = OffsetY + BLK_HEIGHT;
		clicked_blocks.push({
			id: get_clicked_block(x, y),
			x: x,
			y: y
		});

	} else {
		// select 6 - 3x2

		const sel6 = document.getElementById('sel6');
		if (sel6 !== null && sel6.checked) {

			x = OffsetX + BLK_WIDTH;
			y = OffsetY;
			clicked_blocks.push({
				id: get_clicked_block(x, y),
				x: x,
				y: y
			});

			x = OffsetX + (BLK_WIDTH * 2);
			y = OffsetY;
			clicked_blocks.push({
				id: get_clicked_block(x, y),
				x: x,
				y: y
			});

			x = OffsetX;
			y = OffsetY + BLK_HEIGHT;
			clicked_blocks.push({
				id: get_clicked_block(x, y),
				x: x,
				y: y
			});

			x = OffsetX + BLK_WIDTH;
			y = OffsetY + BLK_HEIGHT;
			clicked_blocks.push({
				id: get_clicked_block(x, y),
				x: x,
				y: y
			});

			x = OffsetX + (BLK_WIDTH * 2);
			y = OffsetY + BLK_HEIGHT;
			clicked_blocks.push({
				id: get_clicked_block(x, y),
				x: x,
				y: y
			});
		}
	}
	return clicked_blocks;
}

function do_blocks(block, OffsetX, OffsetY, op) {
	let clicked_blocks = get_clicked_blocks(OffsetX, OffsetY, block);
	for (const clicked_block of clicked_blocks) {
		if (op === 'add') {
			// add block
			add_block(clicked_block.id, clicked_block.x, clicked_block.y);
		} else if (op === 'remove') {
			// remove block
			remove_block(clicked_block.id);
		} else if (op === 'invert') {
			// invert block
			invert_block(clicked_block);
		}
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

	let cell = 0;
	let ret = {};
	ret.x = 0;
	ret.y = 0;

	for (let i = 0; i < orig.GRD_HEIGHT; i += orig.BLK_HEIGHT) {
		for (let j = 0; j < orig.GRD_WIDTH; j += orig.BLK_WIDTH) {
			if (block_id === cell) {
				return {x: j, y: i};
			}
			cell++;
		}
	}

	return ret;
}

function get_clicked_block(OffsetX, OffsetY) {

	OffsetX /= scaled_width;
	OffsetY /= scaled_height;

	let X = (OffsetX / orig.BLK_WIDTH);
	let Y = (OffsetY / orig.BLK_HEIGHT) * (orig.GRD_WIDTH / orig.BLK_WIDTH);

	return Math.round(X + Y);
}

let ajax_queue = [];

let ajax_queue_interval = setInterval(function () {
	if (ajax_queue.length === 0 || ajaxing) {
		return;
	}

	ajaxing = true;

	add_ajax_loader(".mds-pixel-wrapper");

	let data = ajax_queue.shift();

	jQuery.ajax({
		type: 'POST',
		url: data.url,
		data: {
			_wpnonce: select.NONCE,
		},
		success: function (response) {
			let parsed = JSON.parse(response);
			if (parsed.error === 'true') {
				switch (data.action) {
					case 'invert':
						do_blocks(data.clicked_block, data.OffsetX, data.OffsetY, 'invert');
						break;
					case 'remove':
						do_blocks(data.clicked_block, data.OffsetX, data.OffsetY, 'add');
						break;
					case 'add':
						do_blocks(data.clicked_block, data.OffsetX, data.OffsetY, 'remove');
						break;
				}

				messageout(parsed.data.value);
			}

			if (parsed.type === 'order_id') {
				// save order id
				document.pixel_form.order_id.value = parseInt(parsed.data.value, 10);
			}
		},
		fail: function () {
			switch (data.action) {
				case 'invert':
					do_blocks(data.clicked_block, data.OffsetX, data.OffsetY, 'invert');
					break;
				case 'remove':
					do_blocks(data.clicked_block, data.OffsetX, data.OffsetY, 'add');
					break;
				case 'add':
					do_blocks(data.clicked_block, data.OffsetX, data.OffsetY, 'remove');
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
		}
	});

}, 100);

function change_block_state(OffsetX, OffsetY) {
	let clicked_block = get_clicked_block(OffsetX, OffsetY);
	let erasing = jQuery('#erase').is(':checked');

	let data = {
		'erasing': erasing,
		'clicked_block': clicked_block,
		'OffsetX': OffsetX,
		'OffsetY': OffsetY,
		'url': select.UPDATE_ORDER + "?sel_mode=" + document.getElementsByName('pixel_form')[0].elements.sel_mode.value + "&user_id=" + select.user_id + "&block_id=" + clicked_block.toString() + "&BID=" + select.BID + "&t=" + select.time,
	};

	if (is_block_selected(clicked_block)) {
		if (erasing) {
			data.action = 'remove';
			do_blocks(clicked_block, OffsetX, OffsetY, 'remove');
		} else {
			if (select.INVERT_PIXELS === 'YES') {
				data.action = 'invert';
				do_blocks(clicked_block, OffsetX, OffsetY, 'invert');
			} else {
				data.action = 'add';
				do_blocks(clicked_block, OffsetX, OffsetY, 'add');
			}
		}
	} else {
		if (erasing) {
			data.action = 'remove';
			do_blocks(clicked_block, OffsetX, OffsetY, 'remove');
		} else {
			if (select.INVERT_PIXELS === 'YES') {
				data.action = 'invert';
				do_blocks(clicked_block, OffsetX, OffsetY, 'invert');
			} else {
				data.action = 'add';
				do_blocks(clicked_block, OffsetX, OffsetY, 'add');
			}
		}
	}

	// Add data to ajax queue
	ajax_queue.push(data);

}

function implode(myArray) {

	let str = '';
	let comma = '';

	for (let i in myArray) {
		if (myArray.hasOwnProperty(i)) {
			str = str + comma + myArray[i];
		}
		comma = ',';
	}

	return str;
}

function getObjCoords(obj) {
	var rect = obj.getBoundingClientRect();
	var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
	var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
	return {x: rect.left + scrollLeft, y: rect.top + scrollTop};
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
	offset.x = Math.floor((offset.x / scaled_width) / orig.BLK_WIDTH) * orig.BLK_WIDTH;
	offset.y = Math.floor((offset.y / scaled_height) / orig.BLK_HEIGHT) * orig.BLK_HEIGHT;

	// keep within range
	offset.x = Math.max(Math.min(offset.x, GRD_WIDTH - (size.width / scaled_width)), 0);
	offset.y = Math.max(Math.min(offset.y, GRD_HEIGHT - (size.height / scaled_height)), 0);

	// scale back down if necessary
	offset.x = offset.x * scaled_width;
	offset.y = offset.y * scaled_height;

	return offset;
}

function get_pointer_size() {
	let size = {};

	const sel4 = document.getElementById('sel4');
	if (sel4 !== null && sel4.checked) {
		size.width = BLK_WIDTH * 2;
		size.height = BLK_HEIGHT * 2;

	} else {
		const sel6 = document.getElementById('sel6');
		if (sel6 !== null && sel6.checked) {
			size.width = BLK_WIDTH * 3;
			size.height = BLK_HEIGHT * 2;
		} else {
			size.width = BLK_WIDTH;
			size.height = BLK_HEIGHT;
		}
	}

	return size;
}

function show_pointer(offset) {
	pointer.style.visibility = 'visible';
	pointer.style.display = 'block';

	pointer.style.top = offset.y + "px";
	pointer.style.left = offset.x + "px";

	pointer.map_x = offset.x;
	pointer.map_y = offset.y;

	let size = get_pointer_size();
	pointer.style.width = size.width + "px";
	pointer.style.height = size.height + "px";

	return true;
}

function formSubmit(event) {
	event.preventDefault();
	event.stopPropagation();

	if (myblocks.innerHTML.trim() === '') {
		messageout(select.no_blocks_selected);
		return false;
	} else {

		if (submitting === false) {

			submit_button1.disabled = true;

			let submit1_lang = submit_button1.value;

			submit_button1.value = select.WAIT;

			// Wait for ajax queue to finish
			let waitInterval = setInterval(function () {
				if (ajax_queue.length === 0) {
					clearInterval(waitInterval);
					document.pixel_form.submit();

					submit_button1.disabled = false;
					submit_button1.value = submit1_lang;

					submitting = false;
				}
			}, 1000);

		}
	}
}

function reset_pixels() {
	ajax_queue = [];

	add_ajax_loader(".mds-pixel-wrapper");

	jQuery.ajax({
		type: "POST",
		url: select.UPDATE_ORDER,
		data: {
			reset: true,
			action: 'reset',
			_wpnonce: select.NONCE
		},
		success: function (data) {
			let parsed = JSON.parse(data);
			if (parsed.type === "removed") {
				jQuery(myblocks).children().each(function () {
					remove_block(jQuery(this).data('blockid'));
				});
			}
		},
		complete: function () {
			remove_ajax_loader();
		}
	});
}

function rescale_grid() {

	grid_width = jQuery(grid).width();
	grid_height = jQuery(grid).height();

	scaled_width = grid_width / orig.GRD_WIDTH;
	scaled_height = grid_height / orig.GRD_HEIGHT;

	BLK_WIDTH = orig.BLK_WIDTH * scaled_width;
	BLK_HEIGHT = orig.BLK_HEIGHT * scaled_height;

	jQuery(pointer).rescaleStyles();
	jQuery(myblocks).find('*').each(function () {
		jQuery(this).rescaleStyles().repositionStyles();
	});
}

function center_block(coords) {
	let size = get_pointer_size();
	coords.x -= (size.width / 2) - (BLK_WIDTH / 2);
	coords.y -= (size.height / 2) - (BLK_HEIGHT / 2);
	return coords;
}

function handle_click_events() {
	let click = false;
	jQuery(pixel_container).on('mousedown', function () {
		click = true;
	});

	jQuery(pixel_container).on('mousemove', function (event) {
		let coords = center_block({
			x: event.originalEvent.pageX,
			y: event.originalEvent.pageY
		});
		let offset = getOffset(coords.x, coords.y);
		if (offset == null) {
			return false;
		}

		show_pointer(offset);
		click = false;
	});

	jQuery(pixel_container).on('click', function (event) {
		event.preventDefault();

		if (click) {
			click = false;

			let coords = center_block({
				x: event.originalEvent.pageX,
				y: event.originalEvent.pageY
			});
			let offset = getOffset(coords.x, coords.y);
			if (offset == null) {
				return false;
			}

			show_pointer(offset);
			select_pixels(offset);
		}

		return false;
	});
}

function handle_touch_events() {
	let options = {
		"supportedGestures": [Tap]
	};
	let pointerListener = new PointerListener(pixel_container, options);
	pixel_container.addEventListener("tap", function (event) {
		let offset = getOffset(event.detail.live.center.x, event.detail.live.center.y, true);
		if (offset == null) {
			return true;
		}

		show_pointer(offset);
		select_pixels(offset);
	});
}
