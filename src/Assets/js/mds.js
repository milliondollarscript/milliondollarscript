/**
 * Million Dollar Script Two
 *
 * @version 2.3.2
 * @author Ryan Rhode
 * @copyright (C) 2022, Ryan Rhode
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
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
 */

function add_ajax_loader(container) {
	let $ajax_loader = jQuery("<div class='ajax-loader'></div>");
	jQuery(container).append($ajax_loader)
	$ajax_loader.css('top', jQuery(container).position().top).css('left', (jQuery(container).width() / 2) - ($ajax_loader.width() / 2));
}

function remove_ajax_loader() {
	jQuery('.ajax-loader').remove();
}

jQuery(function ($) {
	// MDS iframe display method
	window.mds_iframe = function (mds_type, mds_frame_id, mds_width, mds_height, mds_origin_url) {
		var sendmessage = true;
		var frames = jQuery("#" + mds_frame_id);

		if (frames.length > 0) {
			function adjustframeHeight(frame) {
				if (MDS.users === 'yes' && frame !== undefined && jQuery(frame).hasClass('usersframe')) {
					jQuery(frame).height(560);
				}
				if (frame !== undefined) {
					if (sendmessage) {
						jQuery(frame)[0].contentWindow.postMessage(mds_type + "frameheight", mds_origin_url);
					}
				} else {
					jQuery(frames).each(function () {
						if (sendmessage) {
							jQuery(this)[0].contentWindow.postMessage(mds_type + "frameheight", mds_origin_url);
						}
					});
				}
			}

			function receiveframeMessage(event) {
				const wp_origin = new URL(event.origin);
				const mds_origin = new URL(mds_origin_url);

				if (wp_origin.hostname !== mds_origin.hostname) {
					return;
				}

				if (event.data === mds_type + "width") {
					sendmessage = true;

					jQuery(frames).each(function () {
						var newwidth = jQuery("body").width();
						var origwidth = parseInt(mds_width);

						if (!isNaN(origwidth)) {
							newwidth = Math.min(newwidth, origwidth);
						}

						jQuery(this).width(newwidth);
					});

				} else {
					if (event.data != null && typeof event.data.split === "function") {
						var data = event.data.split(":");
						if (data[0] === mds_type + "frameheight") {
							sendmessage = true;

							jQuery(frames).each(function () {
								var newheight = data[1];
								var origheight = parseInt(mds_height, 10);

								if (!isNaN(origheight)) {
									if (mds_height === "auto") {
										newheight = Math.min(newheight, origheight);
									} else {
										newheight = Math.max(newheight, origheight);
									}
								}

								jQuery(this).height(newheight);
							});

						} else if (data[0] === "iframeunload") {
							sendmessage = false;

							if (mds_height !== "auto") {
								jQuery(frames).each(function () {
									jQuery(this).css("height", mds_height);
								});
							}

						} else if (data[0] === "iframeload") {
							sendmessage = true;
							adjustframeHeight();
						}
					}
				}
			}

			window.addEventListener("message", receiveframeMessage, false);

			jQuery(frames).each(function () {
				jQuery(this).load(function () {
					adjustframeHeight(this);
					window.scrollTo(0, 0);
				});
			});

			jQuery(window).resize(function () {
				adjustframeHeight();
				// jQuery(frames).each(function () {
				// 	jQuery(this).attr("src", jQuery(this).attr("src"));
				// });
			});

			setInterval(function () {
				jQuery(window).resize();
			}, 1000);
		}
	}

	// MDS ajax display method
	window.mds_ajax = function (mds_type, mds_frame_id, mds_align, mds_width, mds_height, mds_origin_url, grid_id) {
		var container = jQuery("#" + mds_frame_id);

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

		add_ajax_loader(container);

		if (container.length > 0) {
			let slash = mds_origin_url.charAt(mds_origin_url.length - 1) === '/' ? '' : '/';

			window.mds_ajax_request = jQuery.ajax({
				url: mds_origin_url + slash + 'ajax.php',
				data: JSON.stringify({
					grid_id: grid_id,
					action: "ajax_" + mds_type
				}),
				type: "POST",
				dataType: "html",
				success: function (data) {
					remove_ajax_loader();
					jQuery(container).html(data);
				},
				error: function (jqXHR, textStatus, errorThrown) {
					// if (errorThrown === "") {
					// 	errorThrown = 'An unknown error occurred. Check that you have WP integration enabled in MDS Main Config and that you have the correct URLs set.';
					// }
					remove_ajax_loader();
					// jQuery(container).html("Error: " + errorThrown);
				}
			});
		}
	}

	// login page functionality
	let $login_page = jQuery("body.login");
	if ($login_page.length > 0) {
		jQuery(document).on('click', 'a', function (event) {
			let target = jQuery(this).attr('target');

			if ('_blank' === target) {
				return true;
			}

			event.preventDefault();
			event.stopPropagation();

			let url = jQuery(this).attr('href');

			if (url !== '#' || url.indexOf('wp_login.php') === -1) {
				parent.location = url;
				return false;
			}

			window.location = url;

			return false;
		});
	}

});
