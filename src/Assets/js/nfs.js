/*
 * Million Dollar Script Two
 *
 * @version     2.5.3
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

jQuery(document).ready(function($){
	let addnfs = [];
	let remnfs = [];

	const $document = $(document);
	let $grid = $('.grid');

	let grid_bg = $grid.css("background-image");
	let grid_bg_url = grid_bg.replace(/^url\(["']?/, '').replace(/["']?\)$/, '');

	let grid_img = $('<img alt="" src=""/>').attr('src', grid_bg_url);

	let $nfs_covered_image = $(document.createDocumentFragment());

	function nfs_covered_image() {
		$grid = $(document).find('.grid');
		if ($grid.data('covered') === false) {
			return;
		}

		$nfs_covered_image = $('<div>', {
			id: 'nfs-covered-image',
			class: 'nfs-covered',
		});

		$grid.append($nfs_covered_image);
	}

	function nfs_covered_image_size() {

		const blocks = selection.getSelection();

		if (blocks.length === 0) {
			return;
		}

		if (!$nfs_covered_image) {
			$nfs_covered_image = $('.nfs-covered-image');
		}

		let width = $grid.data('width');
		let height = $grid.data('height');

		$nfs_covered_image.width(blocks[blocks.length - 1].offsetLeft - blocks[0].offsetLeft + width + 'px');
		$nfs_covered_image.height(blocks[blocks.length - 1].offsetTop - blocks[0].offsetTop + height + 'px');

		$nfs_covered_image.css('position', 'absolute');
		$nfs_covered_image.css('left', blocks[0].offsetLeft + 'px');
		$nfs_covered_image.css('top', blocks[0].offsetTop + 'px');
	}

	function mds_nfs_refresh(html, css, message, dfd) {
		if (css !== undefined && css.length > 0) {
			$(document).find('#mds-admin-css-inline-css').html(css);
		}
		$(document).find('.admin-container').replaceWith(html).promise().done(function () {
			$(document).find('.loading').hide(function () {
				$(this).remove();
				if (message) {
					$('<span class="message">' + message + '</span>').insertBefore('.grid').fadeOut(10000, function () {
						$(this).remove();
					});
				}
				dfd.resolve("success");
			});
		});
	}

	function mds_nfs_error(error) {
		$document.scrollTop(0);
		$('<span class="message">' + MDS.lang.error + " " + error + '</span>').insertBefore('.grid').fadeOut(10000, function () {
			$(this).remove();
		});
	}

	function handleResponse(response) {
		let dfd = $.Deferred();
		try {
			if (response.success === true) {
				mds_nfs_refresh(response.data.html, response.data.css, MDS.lang.success, dfd);
			} else {
				mds_nfs_error(response.data[0].message);
				dfd.reject("error");
			}
		} catch (e) {
			console.log(e);
			mds_nfs_error(MDS.lang.error);
			dfd.reject("error");
		}

		return dfd.promise();
	}

	grid_img.off('load');
	grid_img.on('load', function () {
		$(this).remove();
		$('.loading').remove();
	}).each(function () {
		if (this.complete) {
			$(this).trigger('load');
		}
	});

	function processBlock(block) {
		let $block = $(block);
		let blockid;
		if ($block.hasClass("nfs")) {
			blockid = $block.attr("data-block");
			addnfs.push(blockid);
			let index = remnfs.indexOf(blockid);
			if (index !== -1) {
				remnfs.splice(index, 1);
			}
		} else if ($block.hasClass("free")) {
			blockid = $block.attr("data-block");
			remnfs.push(blockid);
			let index = addnfs.indexOf(blockid);
			if (index !== -1) {
				addnfs.splice(index, 1);
			}
		}
	}

	function toggleBlock(el) {
		let $block = $(el);
		if ($block.hasClass('nfs')) {
			$block.removeClass('nfs');
			$block.addClass('free');
		} else {
			$block.removeClass('free');
			$block.addClass('nfs');
		}
	}

	function getSelectionArea() {
		// https://github.com/Simonwep/selection
		return new SelectionArea({
			selectables: ['span.block'],
			startareas: ['.grid'],
			boundaries: ['.grid'],
		});
	}

	let selection = getSelectionArea();

	$(window).off('unload');
	$(window).on('unload', function () {
		selection.destroy();
	});

	selection.off('beforestart');
	selection.off('move');
	selection.off('stop');
	selection.on('beforestart', e => {
		for (const el of e.store.stored) {
			$(el).removeClass('selected');
		}
		selection.clearSelection(true);
		if ($nfs_covered_image.length > 0) {
			$nfs_covered_image.remove();
			$nfs_covered_image = null;
		}

		nfs_covered_image();

		// }).on('beforedrag', e => {
		// }).on('start', e => {

	}).on('move', e => {
		for (const el of e.store.changed.added) {
			$(el).addClass('selected');
		}

		for (const el of e.store.changed.removed) {
			$(el).removeClass('selected');
		}

		if (!$nfs_covered_image) {
			$nfs_covered_image = $('.nfs-covered-image');
		}

		if (e.store.selected.length > 0) {
			$nfs_covered_image.width(e.store.selected[e.store.selected.length - 1].offsetLeft - e.store.selected[0].offsetLeft + 10 + 'px');
			$nfs_covered_image.height(e.store.selected[e.store.selected.length - 1].offsetTop - e.store.selected[0].offsetTop + 10 + 'px');
		}
	}).on('stop', e => {
		let els = e.store.selected;
		for (const el of els) {
			toggleBlock(el);
			processBlock(el);
		}

		nfs_covered_image_size();
	});

	let $save = $('.save');
	let $reset = $('.reset');

	let submitting = false;
	$document.off('click', '.save');
	$document.on('click', '.save', function (e) {
		e.preventDefault();
		e.stopPropagation();
		$grid.append('<img class="loading" src="' + MDS.MDS_BASE_URL + 'src/Assets/images/ajax-loader.gif" alt="" />');
		$save.prop('disabled', true);
		$reset.prop('disabled', true);

		if (!submitting) {
			submitting = true;

			$.post(MDS.ajaxurl, {
				action: "mds_nfs_save",
				mds_nfs_nonce: MDS.mds_nfs_nonce,
				BID: $('#mds-nfs-grid').val(),
				addnfs: JSON.stringify(addnfs),
				remnfs: JSON.stringify(remnfs),
				dataType: "json",
			}).always(function (data) {
				handleResponse(data).then(function (response) {
					if (response !== "success") {
						console.log(response);
					}
				});
				submitting = false;
				$('.loading').hide(function () {
					$(this).remove();
				});
				$save.prop('disabled', false);
				$reset.prop('disabled', false);
			});
		}

		return false;
	});

	function confirm_dialog(title, message) {
		$('<div id="confirm-dialog">' + message + '</div>').appendTo('.outer_box');
		$('#confirm-dialog').dialog({
			title: title,
			modal: true,
			width: 'auto',
			resizable: false,
			position: {my: "center top", at: "center top+1%", of: $('.outer_box')},
			buttons: {
				Yes: function () {

					$grid.append('<img class="loading" src="' + MDS.MDS_BASE_URL + 'src/Assets/images/ajax-loader.gif" alt="" />');

					$.post(MDS.ajaxurl, {
						action: "mds_nfs_reset",
						mds_nfs_nonce: MDS.mds_nfs_nonce,
						BID: $('#mds-nfs-grid').val(),
						dataType: "json",
					}).done(function (data) {
						selection.clearSelection();

						handleResponse(data).then(function (response) {
							if (response !== "success") {
								console.log(response);
							}
						});

					}).fail(function (data) {
						handleResponse(data).then(function (response) {
							if (response !== "success") {
								console.log(response);
							}
						});
					});

					$(this).dialog("close");
				},
				No: function () {
					$(this).dialog("close");

					$save.prop('disabled', false);
					$reset.prop('disabled', false);
				}
			},
			close: function () {
				$(this).remove();

				$save.prop('disabled', false);
				$reset.prop('disabled', false);
			}
		});
	}

	$document.off('click', '.reset');
	$document.on('click', '.reset', function (e) {
		e.preventDefault();
		e.stopPropagation();
		$save.prop('disabled', true);
		$reset.prop('disabled', true);

		confirm_dialog(MDS.lang.reset, MDS.lang.reset_message);

		return false;
	});

	function mds_nfs_form_submit(formData, $form, options) {
		$form.find('input').attr('disabled', true);
		return true;
	}

	function mds_nfs_form_submit_success(responseText, statusText, xhr, $form) {
		$document.scrollTop(0);

		// selection.destroy();
		selection.clearSelection();

		// $form.find('input').attr('disabled', false);

		// let order_image_preview = jQuery('#order_image_preview');
		// if (order_image_preview.length > 0) {
		// 	let t = new Date();
		// 	let src = order_image_preview.attr('src');
		// 	order_image_preview.attr('src', src + t);
		// }

		handleResponse(responseText).then(function (response) {
			if (response === "success") {
				console.log(response);
				// selection = getSelectionArea();
			}
		});
	}

	$document.on('change', '#mds-nfs-grid', function () {
		const form = $(this).closest('form');

		$(form).ajaxSubmit({
			target: ".outer_box",
			type: 'POST',
			url: MDS.adminpost,
			delegation: true,
			beforeSubmit: mds_nfs_form_submit,
			success: mds_nfs_form_submit_success,
			data: {
				action: 'mds_nfs_grid',
				mds_nfs_nonce: MDS.mds_nfs_nonce,
			},
			error: function (jqXHR, textStatus, errorThrown) {
				// Handle any errors.
				mds_nfs_error(errorThrown);
			},
		});
	}, null, 'json');

});