/**
 * Toggle all checkboxes with a given name attribute
 * @param {string} name - The name attribute of checkboxes to toggle (without the [] suffix)
 */
function checkBoxes(name) {
	jQuery('input[name="' + name + '[]"]').trigger('click');
}

jQuery(document).ready(function () {
	let $fire_container = jQuery("div.milliondollarscript-fire");
	if ($fire_container.length > 0) {
		if (typeof main === "function") {
			main();
		} else {
			console.warn("window.main is undefined; skipping WebGL init.");
		}

		let $fire = jQuery("#milliondollarscript-fire");

		$fire.css("display", "none");

		$fire_container
			.on("mouseenter", function () {
				$fire.stop(true, true).fadeIn();
			})
			.on("mouseleave", function () {
				$fire.stop(true, true).fadeOut();
			});

		let currentPosition = { x: 0, y: 0 };
		let targetPosition = { x: 0, y: 0 };
		let easeFactor = 0.05;

		function lerp(start, end, t) {
			return start * (1 - t) + end * t;
		}

		$fire_container.on("mousemove", function (e) {
			const rect = this.getBoundingClientRect();
			const x = e.pageX - rect.left - window.scrollX;
			const y = e.pageY - rect.top - window.scrollY;
			targetPosition.x = x;
			targetPosition.y = y;
		});

		function updateFirePosition() {
			currentPosition.x = lerp(currentPosition.x, targetPosition.x, easeFactor);
			currentPosition.y = lerp(currentPosition.y, targetPosition.y, easeFactor);

			$fire.css({
				left: currentPosition.x - $fire.width() / 2,
				top: currentPosition.y - $fire.height() / 2,
			});

			requestAnimationFrame(updateFirePosition);
		}

		updateFirePosition();

		jQuery(window).resize(function () {
			// Remove any inline height style to let CSS media queries control the height
			$fire_container.css("height", "");

			const width = $fire_container.width();
			const height = $fire_container.height();

			// Set the canvas width and height to match the new dimensions
			$fire.attr("width", width);
			$fire.attr("height", height);

			// Also update the WebGL viewport to match the new dimensions
			const webgl_context = $fire[0].getContext("webgl2");
			if (webgl_context != null) {
				webgl_context.viewport(0, 0, width, height);
			}
		});
		jQuery(window).trigger("resize");
	}

	const urlParams = new URLSearchParams(window.location.search);
	const postType = urlParams.get("post_type");
	const postId = urlParams.get("post_id");
	// TODO: make dynamic call passed through localize
	if (postType === "mds-pixel" && postId) {
		const row = jQuery("#post-" + postId);
		row.addClass("mds-highlight-row");
		row[0].scrollIntoView();
	}

	// Mobile menu toggle functionality
	const menuToggle = document.querySelector('.milliondollarscript-menu-toggle');
	const menu = document.querySelector('ul.milliondollarscript-menu');

	if (menuToggle && menu) {
		// Toggle main menu
		menuToggle.addEventListener('click', function() {
			const isExpanded = this.getAttribute('aria-expanded') === 'true';
			this.setAttribute('aria-expanded', !isExpanded);
			menu.classList.toggle('menu-open');
		});

		// Add has-submenu class and hover/click handlers for items with submenus
		const menuItems = menu.querySelectorAll('li');
		menuItems.forEach(item => {
			const submenu = item.querySelector(':scope > ul');
			if (submenu) {
				item.classList.add('has-submenu');

				// Desktop hover behavior using hoverIntent
				if (typeof jQuery !== 'undefined' && jQuery.fn.hoverIntent) {
					jQuery(item).hoverIntent({
						over: function() {
							if (window.innerWidth > 750) {
								jQuery(this).addClass('submenu-open');
							}
						},
						out: function() {
							if (window.innerWidth > 750) {
								jQuery(this).removeClass('submenu-open');
							}
						},
						timeout: 200
					});
				}

				// Click handler for both mobile and desktop
				const link = item.querySelector(':scope > a');
				if (link) {
					link.addEventListener('click', function(e) {
						if (window.innerWidth <= 750) {
							// Mobile: toggle submenu, allow navigation on second click
							if (this.getAttribute('href') && this.getAttribute('href') !== '#' && item.classList.contains('submenu-open')) {
								return true;
							}
							e.preventDefault();
							e.stopPropagation();
							item.classList.toggle('submenu-open');
						} else {
							// Desktop: toggle submenu on click (for touch devices or preference)
							// Only prevent default if there's no href or it's just a hash
							const href = this.getAttribute('href');
							if (!href || href === '#' || href === '') {
								e.preventDefault();
								e.stopPropagation();

								// Toggle this submenu
								const wasOpen = item.classList.contains('submenu-open');

								// Close all other submenus at the same level
								const siblings = Array.from(item.parentElement.children);
								siblings.forEach(sibling => {
									if (sibling !== item) {
										sibling.classList.remove('submenu-open');
									}
								});

								// Toggle current submenu
								item.classList.toggle('submenu-open', !wasOpen);
							}
						}
					});
				}
			}
		});

		// Close menu when clicking outside
		document.addEventListener('click', function(e) {
			if (window.innerWidth <= 750 && menu.classList.contains('menu-open')) {
				if (!e.target.closest('.milliondollarscript-menu-wrapper')) {
					menuToggle.setAttribute('aria-expanded', 'false');
					menu.classList.remove('menu-open');
				}
			} else if (window.innerWidth > 750) {
				// Desktop: close all submenus when clicking outside
				if (!e.target.closest('ul.milliondollarscript-menu')) {
					menuItems.forEach(item => {
						item.classList.remove('submenu-open');
					});
				}
			}
		});

		// Reset submenu states on window resize
		window.addEventListener('resize', function() {
			if (window.innerWidth > 750) {
				menuToggle.setAttribute('aria-expanded', 'false');
				menu.classList.remove('menu-open');
				menuItems.forEach(item => {
					item.classList.remove('submenu-open');
				});
			}
		});
	}
});
