/**
 * Million Dollar Script Two
 *
 * @version 2.3.6
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
		mds_init('#' + container, false, true, false, false);
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

function receiveMessage(event, $el) {
	if (event.origin !== MDS.wp || !initialized) {
		return;
	}

	if ($el && $el.length > 0 && $el.data('scalemap') === true) {
		parent.postMessage('gridwidth', MDS.wp);
		rescale($el);
	}

	switch (event.data) {
		case "thankyouframeheight":
		case "usersframeheight":
		case "listframeheight":
		case "statsframeheight":
		case "validateframeheight":

			event.source.postMessage(event.data + ":" + document.body.clientHeight, event.origin);
			break;
		case "gridheight":
			// readjust width if grid is smaller than body
			if ($el.width() < jQuery('body').width() && $el.width() < $el.data('origWidth')) {
				jQuery('html').height("100%");
				jQuery('body').height("100%");

				$el.width(('body').width());
				$el.height(('body').width());
			}

			// set html and body height to same as grid height
			if (jQuery('body').height() !== $el.height()) {
				jQuery('html').height($el.height());
				jQuery('body').height($el.height());
			}

			event.source.postMessage("gridheight:" + document.body.clientHeight, event.origin);

			break;
		default:
			break;
	}
}

function add_tippy() {
	const defaultContent = "<div class='ajax-loader'></div>";
	const isIOS = /iPhone|iPad|iPod/.test(navigator.platform);

	let delay = 50;
	if(MDS.TOOLTIP_TRIGGER === 'mouseenter') {
		delay = 400;
	}

	window.tippy_instance = tippy('.mds-container area,.list-link', {
		theme: 'light',
		content: defaultContent,
		duration: 50,
		delay: delay,
		trigger: MDS.TOOLTIP_TRIGGER,
		allowHTML: true,
		followCursor: 'initial',
		hideOnClick: true,
		interactive: true,
		maxWidth: 350,
		placement: 'auto',
		touch: true,
		appendTo: 'parent',
		popperOptions: {
			strategy: 'fixed',
			modifiers: [
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
						padding: 40,
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
				aid: data.id,
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
			});

		},
		onHidden(instance) {
			instance.setContent(defaultContent);
			instance._content = null;
			instance._error = null;
		}
	});

	window.is_touch = false;

	jQuery(document).on('touchstart', function () {
		window.is_touch = true;
	});

	jQuery(document).on('scroll', function () {
		if (!window.is_touch && window.tippy_instance != null && typeof window.tippy_instance.hide === 'function') {
			window.tippy_instance.hide();
		}
	});

	jQuery(document).on('click', '.list-link', function (e) {
		e.preventDefault();
		e.stopPropagation();
	});
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
		didScale: function (firstTime, options) {
			rescaling = false;
		}
	});
}

function mds_loaded_event(el, scalemap, tippy, iframe, isgrid) {
	if (window.mds_loaded === true) {
		return;
	}
	window.mds_loaded = true;

	jQuery(document).trigger({
		type: 'mds-loaded',
		el: el,
		scalemap: scalemap,
		tippy: tippy,
		iframe: iframe,
		isgrid: isgrid
	});
}

jQuery(document).on('mds-loaded', function (el, scalemap, tippy, iframe, isgrid) {
	setTimeout(function () {
		window.dispatchEvent(new Event('resize'));
	}, 100);
});

function mds_init(el, scalemap, tippy, type, isgrid) {
	let $el = jQuery(el);
	window.mds_loaded = false;

	if (isgrid && scalemap) {

		let origWidth;
		let origHeight;

		if ($el.length > 0) {
			origWidth = $el.width();
			origHeight = $el.height();

			$el.data('scalemap', scalemap).data('origWidth', origWidth).data('origHeight', origHeight);
		}

		let $elParent = $el;

		// https://github.com/GestiXi/image-scale
		$el.imageScale({
			scale: "best-fit",
			align: "top",
			rescaleOnResize: true,
			didScale: function (firstTime, options) {
				if (MDS.wp !== "") {
					if ($elParent.parent().parent().parent().parent().height() < origHeight) {
						$elParent.parent().parent().parent().parent().width(origWidth);
						$elParent.parent().parent().parent().parent().height(origHeight);
					}
				}

				if ($elParent.parent().height() < origHeight) {
					$elParent.width(origWidth);
					$elParent.height(origHeight);
					$elParent.parent().width(origWidth);
					$elParent.parent().height(origHeight);
					rescale($el);
				}

				if (MDS.wp !== "") {
					$elParent.parent().parent().parent().parent().width($el.width());
					$elParent.parent().parent().parent().parent().height($el.height());
				}

				$elParent.parent().width($el.width());
				$elParent.parent().height($el.height());

				rescaling = false;
			}
		});

		rescale($el);

		// https://github.com/clarketm/image-map
		ImageMap('img[usemap]');
		//$el.imageMap();

		jQuery(window).on('resize', function () {
			rescale($el);
		});
	}

	$el.on('load', function () {
		if (isgrid) {
			if (MDS.wp !== "") {
				$el.parent().parent().parent().parent().css('border-bottom', '1px solid #D4D6D4').css('border-right', '1px solid #D4D6D4');
			} else {
				$el.parent().css('border-bottom', '1px solid #D4D6D4').css('border-right', '1px solid #D4D6D4');
			}

			if (scalemap) {
				rescale($el);
			}

			let tooltips = false;
			if (tippy && window.tippy_instance == undefined && MDS.ENABLE_MOUSEOVER !== 'NO') {
				tooltips = true;
				defer('Popper', () => {
					defer('tippy', () => {
						add_tippy();
						mds_loaded_event($el, scalemap, tippy, type, isgrid);
					});
				});
			}

			jQuery('area').off('click').on('click', function (e) {
				e.preventDefault();
				e.stopPropagation();

				window.click_data = jQuery(this).data('data');

				if (MDS.ENABLE_MOUSEOVER === 'NO') {

					const ajax_data = {
						action: 'mds_ajax',
						type: 'click',
						mds_nonce: MDS.mds_nonce,
						aid: window.click_data.ad_id,
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
						window.open(window.click_data.url, '_self');
					});
				}
			});

			jQuery(document).off('click', '.pixel-url').on('click', '.pixel-url', function (e) {
				e.preventDefault();
				e.stopPropagation();

				const $link = jQuery(this);

				const ajax_data = {
					action: 'mds_ajax',
					type: 'click',
					mds_nonce: MDS.mds_nonce,
					aid: window.click_data.ad_id,
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
					window.open($link.attr('href'), '_self');
				});
			});

			jQuery(document).off('click', '#theimage').on('click', '#theimage', function (e) {
				e.preventDefault();
				e.stopPropagation();
				if (MDS.REDIRECT_SWITCH === 'YES') {
					window.open(MDS.REDIRECT_URL, '_self');
					return false;
				}
			});

			if (!tooltips) {
				mds_loaded_event($el, scalemap, tippy, type, isgrid);
			}

		} else {
			mds_loaded_event($el, scalemap, tippy, type, isgrid);
		}

		remove_ajax_loader();
	});

	if (type === "iframe") {
		jQuery('body').addClass('wp');
		window.top.postMessage('iframeload:html', MDS.wp);
		window.addEventListener("message", function (event) {
			receiveMessage(event, $el);
		}, false);
	}

	initialized = true;
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
			window.mds_ajax_request = jQuery.ajax({
				url: MDS.ajaxurl,
				data: {
					BID: grid_id,
					action: "mds_ajax",
					type: mds_type,
					mds_nonce: MDS.mds_nonce
				},
				type: "POST",
				dataType: "html",
				success: function (data) {
					remove_ajax_loader();
					jQuery(container).html(data);
					mds_init('#theimage', true, MDS.ENABLE_MOUSEOVER !== 'NO', false, true);
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
