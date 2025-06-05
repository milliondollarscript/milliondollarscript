let first_load = true;

jQuery(document).on("ajaxComplete", function (event, xhr, settings) {
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

	window.$block_pointer = jQuery("#block_pointer");
	window.$pixelimg = jQuery("#pixelimg");

	if (window.$block_pointer.length === 0 || window.$pixelimg.length === 0) {
		return;
	}

	// Initialize pointer dimensions from MDS_GRID_DATA if available
	if (window.MDS_OBJECT && window.MDS_OBJECT.grid_data) {
		const gridData = window.MDS_OBJECT.grid_data;

		// Store original dimensions as data attributes
		window.$block_pointer.attr("data-original-width", gridData.pointer_width);
		window.$block_pointer.attr("data-original-height", gridData.pointer_height);

		// Set initial size based on the data from PHP
		window.$block_pointer.css({
			width: gridData.pointer_width + "px",
			height: gridData.pointer_height + "px",
		});

		// Log debug information
		if (gridData.debug_mode) {
			console.log("Grid dimensions:", {
				width_px: gridData.orig_width_px,
				height_px: gridData.orig_height_px,
				width_blocks: gridData.grid_width_blocks,
				height_blocks: gridData.grid_height_blocks,
				block_width: gridData.block_width,
				block_height: gridData.block_height,
			});
		}
	}

	// Initialize variables
	let trip_count = 0;
	const block_str = MDS_OBJECT.block_str;
	let low_x = parseInt(MDS_OBJECT.low_x, 10);
	let low_y = parseInt(MDS_OBJECT.low_y, 10);
	let is_moving = MDS_OBJECT.is_moving;

	// Set global block dimensions
	window.blk_width = parseInt(MDS_OBJECT.BLK_WIDTH, 10);
	window.blk_height = parseInt(MDS_OBJECT.BLK_HEIGHT, 10);

	// Set global grid dimensions
	window.grid_width = parseInt(MDS_OBJECT.grid_width, 10) * window.blk_width;
	window.grid_height = parseInt(MDS_OBJECT.grid_height, 10) * window.blk_height;

	// Set pointer dimensions
	window.pointer_width = window.blk_width;
	window.pointer_height = window.blk_height;

	const user_id = parseInt(MDS_OBJECT.user_id, 10);
	const BID = parseInt(MDS_OBJECT.BID, 10);
	let submit1 = document.getElementById("submit_button1");
	let submit2 = document.getElementById("submit_button2");

	document.form1.selected_pixels.value = block_str;

	function check_selection() {
		// Server-side check of block availability

		// Get the pointer position
		const pointerOffset = window.$block_pointer.position();

		// Get block ID using our helper function
		const blockId = get_clicked_block();

		// Get grid and pointer dimensions
		const gridData = MDS_OBJECT.grid_data || {};
		const gridWidthBlocks = parseInt(
			MDS_OBJECT.grid_width || gridData.grid_width_blocks,
		);
		const currentImgWidth = window.$pixelimg.width();
		const originalImgWidth = parseInt(
			gridData.orig_width_px || gridWidthBlocks * gridData.block_width,
		);

		// Calculate scale factor between current and original grid size
		const scaleFactor = originalImgWidth / currentImgWidth;

		// Calculate scaled coordinates (convert from current scale to original scale)
		const scaledX = Math.round(pointerOffset.left * scaleFactor);
		const scaledY = Math.round(pointerOffset.top * scaleFactor);

		// Calculate block coordinates from block ID (for reference/logging)
		const blockY = Math.floor(blockId / gridWidthBlocks);
		const blockX = blockId % gridWidthBlocks;

		let ajax_data = {
			_wpnonce: MDS_OBJECT.NONCE,
			user_id: user_id,
			map_x: scaledX,
			map_y: scaledY,
			block_id: blockId,
			BID: BID,
			t: MDS_OBJECT.time,
		};

		jQuery
			.ajax({
				method: "POST",
				url: MDS_OBJECT.CHECK_SELECTION,
				data: ajax_data,
				dataType: "json",
			})
			.done(function (response) {
				// Handle the response
				if (response) {
					// Error responses
					if (
						response.type === "unavailable" ||
						response.type === "no_orders" ||
						response.type === "no_permission"
					) {
						alert(response.data.value);
						is_moving = true;
					}
					// Success responses
					else if (response.type === "available") {
						// Space is available, do nothing special here - selection is allowed
						console.log("Blocks are available for selection");
					}
				}
			})
			.fail(function (jqXHR, textStatus, errorThrown) {
				console.error("Selection check failed:", textStatus, errorThrown);
			})
			.always(function () {
				submit1.disabled = false;
				submit2.disabled = false;

				window.$block_pointer.css("cursor", "pointer");
				window.$pixelimg.css("cursor", "pointer");
			});

		if (trip_count !== 0) {
			submit1.disabled = true;
			submit2.disabled = true;
			window.$block_pointer.css("cursor", "wait");
			window.$pixelimg.css("cursor", "wait");
		}
	}

	function make_selection(event) {
		event.stopPropagation();
		event.preventDefault();

		window.reserving = true;

		window.$block_pointer.css("cursor", "wait");
		window.$pixelimg.css("cursor", "wait");
		document.body.style.cursor = "wait";
		submit1.disabled = true;
		submit2.disabled = true;
		submit1.value = MDS_OBJECT.WAIT;
		submit2.value = MDS_OBJECT.WAIT;
		submit1.style.cursor = "wait";
		submit2.style.cursor = "wait";

		// Get pointer position from its current position
		const pointerOffset = window.$block_pointer.position();

		// Get grid dimensions for proper scaling
		const gridData = MDS_OBJECT.grid_data || {};
		const gridWidthBlocks = parseInt(
			MDS_OBJECT.grid_width || gridData.grid_width_blocks,
		);
		const currentImgWidth = window.$pixelimg.width();
		const originalImgWidth = parseInt(
			gridData.orig_width_px || gridWidthBlocks * gridData.block_width,
		);

		// Calculate scale factor to convert current coordinates to original scale
		const scaleFactor = originalImgWidth / currentImgWidth;

		// Scale coordinates to match the original grid size
		const scaledX = Math.round(pointerOffset.left * scaleFactor);
		const scaledY = Math.round(pointerOffset.top * scaleFactor);

		// Prepare AJAX data with scaled coordinates
		let ajax_data = {
			mds_nonce: MDS_OBJECT.mds_nonce,
			action: "mds_ajax",
			type: "make-selection",
			user_id: user_id,
			map_x: scaledX,
			map_y: scaledY,
			block_id: get_clicked_block(),
			BID: BID,
			t: MDS_OBJECT.time,
			package: get_selected_package(),
		};

		// Process selection via AJAX
		jQuery
			.ajax({
				method: "POST",
				url: MDS_OBJECT.ajaxurl,
				data: ajax_data,
				dataType: "json",
			})
			.done(function (response) {
				// Process server response
				if (response) {
					if (
						response.type === "unavailable" ||
						response.type === "max_orders"
					) {
						alert(response.data.value);

						// Reset all UI elements
						window.$block_pointer.css("cursor", "pointer");
						window.$pixelimg.css("cursor", "pointer");
						document.body.style.cursor = "pointer";
						submit1.disabled = false;
						submit2.disabled = false;
						submit1.value = MDS_OBJECT.WRITE;
						submit2.value = MDS_OBJECT.WRITE;
						submit1.style.cursor = "pointer";
						submit2.style.cursor = "pointer";
						window.reserving = false;
						is_moving = true;
					} else if (response.redirect) {
						window.location.href = response.redirect;
					} else {
						mds_update_package(jQuery(document.form1));
						document.form1.submit();
					}
				}
			})
			.fail(function (jqXHR, textStatus, errorThrown) {
				// Log errors in case of AJAX failure
				console.error("Selection request failed:", textStatus, errorThrown);

				// Reset UI state
				window.$block_pointer.css("cursor", "pointer");
				window.$pixelimg.css("cursor", "pointer");
				document.body.style.cursor = "pointer";
				submit1.disabled = false;
				submit2.disabled = false;
				submit1.value = MDS_OBJECT.WRITE;
				submit2.value = MDS_OBJECT.WRITE;
				submit1.style.cursor = "pointer";
				submit2.style.cursor = "pointer";
				window.reserving = false;
			});
	}

	function getObjCoords(obj) {
		const offset = obj.offset();
		const x = offset.left;
		const y = offset.top;
		return { x, y };
	}

	// This function handles scaling the image map and pointer when the window resizes
	function scaleImageMap() {
		// Get the image element
		const img = jQuery("#pixelimg");
		if (!img.length) return;

		// Get grid dimensions from MDS_OBJECT
		const gridData = MDS_OBJECT.grid_data || {};
		const gridWidthBlocks = parseInt(
			gridData.grid_width_blocks || MDS_OBJECT.grid_width,
		);
		const gridHeightBlocks = parseInt(
			gridData.grid_height_blocks || MDS_OBJECT.grid_height,
		);

		// Ensure we have valid grid dimensions
		if (!gridWidthBlocks || !gridHeightBlocks) {
			console.error("Invalid grid dimensions in scaleImageMap");
			return;
		}

		// Get current image dimensions
		const currentWidth = img.width();
		const currentHeight = img.height();

		// Calculate block size
		const blockWidth = currentWidth / gridWidthBlocks;
		const blockHeight = currentHeight / gridHeightBlocks;

		// Store block dimensions and grid size for other functions
		window.blk_width = blockWidth;
		window.blk_height = blockHeight;
		window.grid_width = currentWidth;
		window.grid_height = currentHeight;
		window.grid_width_in_blocks = gridWidthBlocks;
		window.grid_height_in_blocks = gridHeightBlocks;

		// Handle pointer if it exists
		if (window.$block_pointer) {
			// Get original pointer dimensions from data attributes
			const origWidth =
				parseInt(window.$block_pointer.attr("data-original-width")) ||
				gridData.pointer_width ||
				blockWidth * 4;
			const origHeight =
				parseInt(window.$block_pointer.attr("data-original-height")) ||
				gridData.pointer_height ||
				blockHeight * 4;

			// Store original dimensions if not already stored
			if (!window.$block_pointer.data("orig-width")) {
				window.$block_pointer.data({
					"orig-width": origWidth,
					"orig-height": origHeight,
				});
			}

			// Get original grid size from grid data
			const origGridWidth =
				gridData.orig_width_px || gridWidthBlocks * gridData.block_width;

			// Calculate the current scale factor based on the relationship between
			// original grid size and current grid size
			const scaleFactor = currentWidth / origGridWidth;

			// Scale the pointer proportionally to the grid scaling
			const scaledWidth = origWidth * scaleFactor;
			const scaledHeight = origHeight * scaleFactor;

			// Apply dimensions to pointer
			window.$block_pointer.css({
				width: scaledWidth + "px",
				height: scaledHeight + "px",
			});

			// Store for other functions
			window.pointer_width = scaledWidth;
			window.pointer_height = scaledHeight;

			// Update pointer position if it's already positioned
			if (window.$block_pointer.data("block_x") !== undefined) {
				// Get stored block coordinates
				const blockX = parseInt(window.$block_pointer.data("block_x"));
				const blockY = parseInt(window.$block_pointer.data("block_y"));

				// Get actual pointer dimensions
				const pointerWidth = window.$block_pointer.outerWidth(true);
				const pointerHeight = window.$block_pointer.outerHeight(true);

				// Convert block position to pixel position
				let pixelX = blockX * blockWidth;
				let pixelY = blockY * blockHeight;

				// Ensure pointer stays within boundaries
				// Calculate the maximum valid position
				const maxX = currentWidth - pointerWidth;
				const maxY = currentHeight - pointerHeight;

				// Apply constraints
				pixelX = Math.min(Math.max(0, pixelX), maxX);
				pixelY = Math.min(Math.max(0, pixelY), maxY);

				// Position the pointer
				window.$block_pointer.css({
					left: pixelX + "px",
					top: pixelY + "px",
				});

				// Calculate the block position based on pixel position
				const safeBlockX = Math.round(pixelX / blockWidth);
				const safeBlockY = Math.round(pixelY / blockHeight);

				// Store coordinates for other functions
				window.$block_pointer.data({
					map_x: pixelX,
					map_y: pixelY,
					block_x: safeBlockX,
					block_y: safeBlockY,
				});

				// Store globally
				window.mds_x = pixelX;
				window.mds_y = pixelY;

				// Debug logging
				if (gridData.debug_mode) {
					console.log("ScaleImageMap:", {
						grid: {
							blocks: gridWidthBlocks + "x" + gridHeightBlocks,
							pixels: currentWidth + "x" + currentHeight,
						},
						block: { width: blockWidth, height: blockHeight },
						pointer: {
							original: origWidth + "x" + origHeight,
							actual: pointerWidth + "x" + pointerHeight,
						},
						position: {
							pixel: pixelX + "," + pixelY,
							block: safeBlockX + "," + safeBlockY,
							max: maxX + "," + maxY,
						},
					});
				}
			}
		}
	}

	// Create a debounced resize handler
	let resizeTimer;
	window.addEventListener("resize", function () {
		// Clear the previous timer
		clearTimeout(resizeTimer);
		// Set a new timer
		resizeTimer = setTimeout(function () {
			scaleImageMap();
		}, 100);
	});

	// Handle image load events
	jQuery(document).ready(function () {
		const pixelimg = document.getElementById("pixelimg");
		if (pixelimg) {
			pixelimg.addEventListener("load", function () {
				scaleImageMap();
			});
		}

		// Initialize on document ready
		setTimeout(function () {
			scaleImageMap();
		}, 100);
	});

	// Show the pointer at the mouse position, snapped to grid and constrained to boundaries
	function show_pointer(e) {
		// Only proceed if we're in moving mode
		if (!is_moving) return;

		// Get the image container
		const img = jQuery("#pixelimg");
		if (!img.length) return;

		// Get grid dimensions from MDS_OBJECT
		const gridData = MDS_OBJECT.grid_data || {};
		const gridWidthBlocks = parseInt(
			gridData.grid_width_blocks || MDS_OBJECT.grid_width,
		);
		const gridHeightBlocks = parseInt(
			gridData.grid_height_blocks || MDS_OBJECT.grid_height,
		);

		// Ensure we have valid grid dimensions
		if (!gridWidthBlocks || !gridHeightBlocks) {
			console.error("Invalid grid dimensions");
			return false;
		}

		// Get current image and pointer dimensions
		const imgWidth = img.width();
		const imgHeight = img.height();
		const imgOffset = img.offset();
		const pointer = window.$block_pointer;

		// Calculate block dimensions based on current image size
		const blockWidth = imgWidth / gridWidthBlocks;
		const blockHeight = imgHeight / gridHeightBlocks;

		// Store dimensions as globals for other functions
		window.grid_width = imgWidth;
		window.grid_height = imgHeight;
		window.grid_width_in_blocks = gridWidthBlocks;
		window.grid_height_in_blocks = gridHeightBlocks;
		window.blk_width = blockWidth;
		window.blk_height = blockHeight;

		// Get mouse position relative to the image
		const mouseX = e.pageX - imgOffset.left;
		const mouseY = e.pageY - imgOffset.top;

		// Constrain mouse position within image boundaries
		const boundedMouseX = Math.max(0, Math.min(mouseX, imgWidth - 1));
		const boundedMouseY = Math.max(0, Math.min(mouseY, imgHeight - 1));

		// Get the current pointer dimensions
		const pointerWidth = pointer.outerWidth(true);
		const pointerHeight = pointer.outerHeight(true);

		// Calculate the block that the mouse is over
		let blockX = Math.floor(boundedMouseX / blockWidth);
		let blockY = Math.floor(boundedMouseY / blockHeight);

		// Convert block position to pixel position
		let pixelX = blockX * blockWidth;
		let pixelY = blockY * blockHeight;

		// Calculate the maximum valid position
		const maxX = imgWidth - pointerWidth;
		const maxY = imgHeight - pointerHeight;

		// Prevent pointer from going beyond the grid boundaries
		pixelX = Math.min(pixelX, maxX);
		pixelY = Math.min(pixelY, maxY);

		// Position the pointer
		pointer.css({
			left: pixelX + "px",
			top: pixelY + "px",
		});

		// Update the block position based on the constrained pixel position
		blockX = Math.round(pixelX / blockWidth);
		blockY = Math.round(pixelY / blockHeight);

		// Store position data for other functions
		pointer.data({
			map_x: pixelX,
			map_y: pixelY,
			block_x: blockX,
			block_y: blockY,
		});

		// Store position in global variables
		window.mds_x = pixelX;
		window.mds_y = pixelY;

		// Show debug information when in debug mode
		if (gridData.debug_mode) {
			console.log("Pointer:", {
				grid: {
					blocks: gridWidthBlocks + "x" + gridHeightBlocks,
					pixels: imgWidth + "x" + imgHeight,
				},
				block: { width: blockWidth, height: blockHeight },
				pointer: { size: pointerWidth + "x" + pointerHeight },
				position: {
					pixel: pixelX + "," + pixelY,
					block: blockX + "," + blockY,
					max: maxX + "," + maxY,
				},
			});
		}

		return true;
	}

	// Convert pointer position to a block ID
	function get_clicked_block() {
		// Get the grid dimensions from MDS_OBJECT
		const gridData = MDS_OBJECT.grid_data || {};
		const gridWidthBlocks = parseInt(
			MDS_OBJECT.grid_width || gridData.grid_width_blocks,
		);
		const gridHeightBlocks = parseInt(
			MDS_OBJECT.grid_height || gridData.grid_height_blocks,
		);

		// Verify we have valid grid dimensions
		if (!gridWidthBlocks || !gridHeightBlocks) {
			console.error("Grid dimensions not available");
			return -1;
		}

		// Use the center point of the pointer for selection
		if (window.$block_pointer) {
			// Get pointer position and dimensions
			const pointerOffset = window.$block_pointer.offset();
			const imgOffset = window.$pixelimg.offset();
			const pointerWidth = window.$block_pointer.outerWidth();
			const pointerHeight = window.$block_pointer.outerHeight();

			// Calculate the center point of the pointer relative to the image
			const centerX = pointerOffset.left - imgOffset.left + pointerWidth / 2;
			const centerY = pointerOffset.top - imgOffset.top + pointerHeight / 2;

			// Get current image dimensions and calculate block size
			const imgWidth = window.$pixelimg.width();
			const imgHeight = window.$pixelimg.height();
			const blockWidth = imgWidth / gridWidthBlocks;
			const blockHeight = imgHeight / gridHeightBlocks;

			// Calculate which block contains the center point
			const blockX = Math.floor(centerX / blockWidth);
			const blockY = Math.floor(centerY / blockHeight);

			// Verify coordinates are valid
			if (
				blockX < 0 ||
				blockY < 0 ||
				blockX >= gridWidthBlocks ||
				blockY >= gridHeightBlocks
			) {
				console.error("Invalid block coordinates:", blockX, blockY);
				return -1;
			}

			// Debug logging
			if (gridData.debug_mode) {
				console.log("Selection:", {
					pointer: {
						left: pointerOffset.left,
						top: pointerOffset.top,
						width: pointerWidth,
						height: pointerHeight,
					},
					center: { x: centerX, y: centerY },
					block: {
						x: blockX,
						y: blockY,
						width: blockWidth,
						height: blockHeight,
					},
					grid: { width: gridWidthBlocks, height: gridHeightBlocks },
				});
			}

			// Calculate block ID from grid coordinates
			return blockY * gridWidthBlocks + blockX;
		}

		return -1;
	}

	function do_block_click() {
		if (window.reserving) {
			return;
		}

		trip_count = 1;
		check_selection();
		low_x = window.$block_pointer.position().left;
		low_y = window.$block_pointer.position().top;

		is_moving = !is_moving;
	}

	function move_image_to_selection() {
		window.$block_pointer.css({
			top: low_y + "px",
			left: low_x + "px",
			visibility: "visible",
		});

		window.$block_pointer.data({
			map_y: low_y,
			map_x: low_x,
		});
	}

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

	// Rebinding events
	jQuery(document)
		.off("click", "#pixelimg")
		.on("click", "#pixelimg", function () {
			event.preventDefault();
			event.stopPropagation();
			do_block_click();
		});

	let isOverBlockPointer = false;

	jQuery(document)
		.off("mouseenter", "#block_pointer")
		.on("mouseenter", "#block_pointer", function () {
			isOverBlockPointer = true;
		});

	jQuery(document)
		.off("mouseleave", "#block_pointer")
		.on("mouseleave", "#block_pointer", function () {
			isOverBlockPointer = false;
		});

	jQuery(document)
		.off("mousemove", "#pixelimg")
		.on("mousemove", "#pixelimg", function (event) {
			if (!isOverBlockPointer) {
				show_pointer(event);
			}
		});

	jQuery(document)
		.off("mousemove", "#block_pointer")
		.on("mousemove", "#block_pointer", function (event) {
			show_pointer(event);
		});

	window.$pixelimg
		.off("load")
		.on("load", function () {
			move_image_to_selection();
			remove_ajax_loader();
		})
		.each(function () {
			if (this.complete) jQuery(this).trigger("load");
		});

	jQuery(window).on("resize", move_image_to_selection);

	jQuery(document)
		.off("click", "#submit_button1, #submit_button2")
		.on("click", "#submit_button1, #submit_button2", function (event) {
			let $el = jQuery(this);
			$el.prop("disabled", true);
			$el.attr("value", MDS_OBJECT.WAIT);
			make_selection(event);
			return false;
		});

	jQuery(".mds_pointer_graphic")
		.off("load")
		.on("load", function () {
			jQuery(".mds_upload_image").prop("disabled", false);
			jQuery(this).attr("value", "Upload");
		});

	add_ajax_loader(window.$pixelimg.parent());
});
