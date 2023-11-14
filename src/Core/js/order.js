let first_load = true;

jQuery(document).off('ajaxComplete').on('ajaxComplete', function (event, xhr, settings) {
	const params = new URLSearchParams(settings.data);
	const type = params.get('type');

	// Check if the type parameter is order
	if (type !== 'order') {
		return;
	}

	if (first_load) {
		first_load = false;
	} else {
		return;
	}

	window.$block_pointer = jQuery('#block_pointer');
	window.$pixelimg = jQuery('#pixelimg');

	if (window.$block_pointer.length === 0 || window.$pixelimg.length === 0) {
		return;
	}

	// Initialize
	let trip_count = 0;
	const block_str = MDS_OBJECT.block_str;
	let low_x = parseInt(MDS_OBJECT.low_x, 10);
	let low_y = parseInt(MDS_OBJECT.low_y, 10);
	let is_moving = MDS_OBJECT.is_moving;
	const blk_width = parseInt(MDS_OBJECT.BLK_WIDTH, 10);
	const blk_height = parseInt(MDS_OBJECT.BLK_HEIGHT, 10);
	const grid_width = parseInt(MDS_OBJECT.grid_width, 10) * blk_width;
	const grid_height = parseInt(MDS_OBJECT.grid_height, 10) * blk_height;
	const user_id = parseInt(MDS_OBJECT.user_id, 10);
	const BID = parseInt(MDS_OBJECT.BID, 10);
	let submit1 = document.getElementById('submit_button1');
	let submit2 = document.getElementById('submit_button2');

	document.form1.selected_pixels.value = block_str;

	function check_selection(OffsetX, OffsetY) {
		// Trip to the database.

		let ajax_data = {
			_wpnonce: MDS_OBJECT.NONCE,
			user_id: user_id,
			map_x: OffsetX,
			map_y: OffsetY,
			block_id: get_clicked_block(),
			BID: BID,
			t: MDS_OBJECT.time
		};

		jQuery.ajax({
			method: 'POST',
			url: MDS_OBJECT.CHECK_SELECTION,
			data: ajax_data,
			dataType: 'json',
		}).done(function (response) {
			if (response && response.type === 'unavailable') {
				alert(response.data.value);
				is_moving = true;
			}
		}).fail(function (jqXHR, textStatus, errorThrown) {
			// console.error("Request failed: " + textStatus + ", " + errorThrown);
		}).always(function () {
			submit1.disabled = false;
			submit2.disabled = false;

			window.$block_pointer.css('cursor', 'pointer');
			window.$pixelimg.css('cursor', 'pointer');
		});

		if (trip_count !== 0) {
			submit1.disabled = true;
			submit2.disabled = true;
			window.$block_pointer.css('cursor', 'wait');
			window.$pixelimg.css('cursor', 'wait');
		}
	}

	function make_selection(event) {
		event.stopPropagation();
		event.preventDefault();

		window.reserving = true;

		window.$block_pointer.css('cursor', 'wait');
		window.$pixelimg.css('cursor', 'wait');
		document.body.style.cursor = 'wait';
		submit1.disabled = true;
		submit2.disabled = true;
		submit1.value = MDS_OBJECT.WAIT;
		submit2.value = MDS_OBJECT.WAIT;
		submit1.style.cursor = 'wait';
		submit2.style.cursor = 'wait';

		let ajax_data = {
			_wpnonce: MDS_OBJECT.NONCE,
			user_id: user_id,
			map_x: window.$block_pointer.map_x,
			map_y: window.$block_pointer.map_y,
			block_id: get_clicked_block(),
			BID: BID,
			t: MDS_OBJECT.time
		};

		jQuery.ajax({
			method: 'POST',
			url: MDS_OBJECT.MAKE_SELECTION,
			data: ajax_data,
			dataType: 'html',
		}).done(function (data) {
			if (data.indexOf('E432') > -1) {
				alert(data);
				window.$block_pointer.css('cursor', 'pointer');
				window.$pixelimg.css('cursor', 'pointer');
				document.body.style.cursor = 'pointer';
				submit1.disabled = false;
				submit2.disabled = false;
				submit1.value = MDS_OBJECT.WRITE;
				submit2.value = MDS_OBJECT.WRITE;
				submit1.style.cursor = 'pointer';
				submit2.style.cursor = 'pointer';
				window.reserving = false;
				is_moving = true;
			} else {
				document.form1.submit();
			}

		}).fail(function (jqXHR, textStatus, errorThrown) {
		}).always(function () {
		});
	}

	function getObjCoords(obj) {
		const rect = obj.getBoundingClientRect();
		const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
		const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
		return {x: rect.left + scrollLeft, y: rect.top + scrollTop};
	}

	function show_pointer(e) {
		if (!is_moving) return;

		const pos = getObjCoords(window.$pixelimg[0]);
		let OffsetX, OffsetY;
		if (e.offsetX !== undefined) {
			OffsetX = e.offsetX;
			OffsetY = e.offsetY;
		} else {
			OffsetX = e.pageX - pos.x;
			OffsetY = e.pageY - pos.y;
		}

		OffsetX = Math.floor(OffsetX / blk_width) * blk_width;
		OffsetY = Math.floor(OffsetY / blk_height) * blk_height;

		if (isNaN(OffsetX) || isNaN(OffsetY) || OffsetX < 0 || OffsetY < 0) {
			return;
		}

		if (window.pointer_height + OffsetY > grid_height) {
		} else {
			window.$block_pointer.css('top', pos.y + OffsetY + 'px');
			window.$block_pointer.map_y = OffsetY;
		}

		if (window.pointer_width + OffsetX > grid_width) {
		} else {
			window.$block_pointer.map_x = pos.x + OffsetX;
			window.$block_pointer.css('left', pos.x + OffsetX + 'px');
		}

		return true;
	}

	function show_pointer2(e) {
		// Function called when mouse is over the actual pointing image
		if (!is_moving) return;

		const pos = getObjCoords(window.$pixelimg[0]);
		const p_pos = getObjCoords(window.$block_pointer[0]);

		let OffsetX, OffsetY, ie;
		if (e.offsetX !== undefined) {
			OffsetX = e.offsetX;
			OffsetY = e.offsetY;
			ie = true;
		} else {
			OffsetX = e.pageX - pos.x;
			OffsetY = e.pageY - pos.y;
			ie = false;
		}

		if (ie) {
			const rel_posx = p_pos.x - pos.x;
			const rel_posy = p_pos.y - pos.y;

			window.$block_pointer.map_x = rel_posx;
			window.$block_pointer.map_y = rel_posy;

			if (isNaN(OffsetX) || isNaN(OffsetY)) {
				return;
			}

			if (OffsetX >= blk_width) {
				// Move the pointer right
				if (rel_posx + window.pointer_width >= grid_width) {
				} else {
					window.$block_pointer.map_x = p_pos.x + blk_width;
					window.$block_pointer.css('left', window.$block_pointer.map_x + 'px');
				}
			}

			if (OffsetY > blk_height) {
				// Move the pointer down

				if (rel_posy + window.pointer_height >= grid_height) {
					//return
				} else {
					window.$block_pointer.map_y = p_pos.y + blk_height;
					window.$block_pointer.css('top', window.$block_pointer.map_y + 'px');
				}
			}

		} else {

			const tOffsetX = Math.floor(OffsetX / blk_width) * blk_width;
			const tOffsetY = Math.floor(OffsetY / blk_height) * blk_height;

			if (isNaN(OffsetX) || isNaN(OffsetY)) {
				return;
			}

			if (OffsetX > tOffsetX) {
				if (window.pointer_width + tOffsetX > grid_width) {
					// Don't move left
				} else {
					window.$block_pointer.map_x = tOffsetX;
					window.$block_pointer.css('left', pos.x + tOffsetX + 'px');
				}
			}

			if (OffsetY > tOffsetY) {
				if (window.pointer_height + tOffsetY > grid_height) {
					// Don't move down
				} else {
					window.$block_pointer.css('top', pos.y + tOffsetY + 'px');
					window.$block_pointer.map_y = tOffsetY;
				}
			}
		}
	}

	function get_clicked_block() {
		let clicked_block = ((window.$block_pointer.map_x) / blk_width) + ((window.$block_pointer.map_y / blk_height) * (grid_width / blk_width));

		if (clicked_block === 0) {
			// convert to string
			clicked_block = "0";
		}
		return clicked_block;
	}

	function do_block_click() {
		if (window.reserving) {
			return;
		}

		trip_count = 1;
		check_selection(window.$block_pointer.map_x, window.$block_pointer.map_y);
		low_x = window.$block_pointer.map_x;
		low_y = window.$block_pointer.map_y;

		is_moving = !is_moving;
	}

	function move_image_to_selection() {
		let pos = getObjCoords(window.$pixelimg[0]);

		window.$block_pointer.css('top', pos.y + low_y + 'px');
		window.$block_pointer.map_y = low_y;

		window.$block_pointer.css('left', pos.x + low_x + 'px');
		window.$block_pointer.map_x = low_x;

		window.$block_pointer.css('visibility', 'visible');
	}

	function add_ajax_loader(container) {
		let $ajax_loader = jQuery("<div class='ajax-loader'></div>");
		jQuery(container).append($ajax_loader);
		$ajax_loader.css('top', jQuery(container).position().top).css('left', (jQuery(container).width() / 2) - ($ajax_loader.width() / 2));
	}

	function remove_ajax_loader() {
		jQuery('.ajax-loader').remove();
	}

	// Rebinding events
	jQuery(document).off('mousemove', '#block_pointer').on('mousemove', '#block_pointer', function (event) {
		show_pointer2(event);
	});

	jQuery(document).off('click', '#block_pointer').on('click', '#block_pointer', function () {
		do_block_click();
	});

	jQuery(document).off('mousemove', '#pixelimg').on('mousemove', '#pixelimg', function (event) {
		show_pointer(event);
	});

	window.$pixelimg.off('load').on('load', function () {
		move_image_to_selection();
		remove_ajax_loader();
	}).each(function () {
		if (this.complete) jQuery(this).trigger('load');
	});

	jQuery(window).on('resize', move_image_to_selection);

	jQuery(document).off('click', '#submit_button1, #submit_button2').on('click', '#submit_button1, #submit_button2', function (event) {
		make_selection(event);
		return false;
	});

	add_ajax_loader(window.$pixelimg.parent());
});