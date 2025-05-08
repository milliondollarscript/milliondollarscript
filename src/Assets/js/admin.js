jQuery(document).ready(function () {
	let $fire_container = jQuery('div.milliondollarscript-fire');
	if ($fire_container.length > 0) {
		if (typeof main === 'function') {
			main();
		} else {
			console.warn('window.main is undefined; skipping WebGL init.');
		}

		let $fire = jQuery('#milliondollarscript-fire');

		$fire.css('display', 'none');

		$fire_container.on('mouseenter', function () {
			$fire.stop(true, true).fadeIn();
		}).on('mouseleave', function () {
			$fire.stop(true, true).fadeOut();
		});

		let currentPosition = {x: 0, y: 0};
		let targetPosition = {x: 0, y: 0};
		let easeFactor = 0.05;

		function lerp(start, end, t) {
			return start * (1 - t) + end * t;
		}

		$fire_container.on('mousemove', function (e) {
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
				'left': currentPosition.x - $fire.width() / 2,
				'top': currentPosition.y - $fire.height() / 2
			});

			requestAnimationFrame(updateFirePosition);
		}

		updateFirePosition();

		jQuery(window).resize(function () {
			const width = $fire_container.width();
			const height = $fire_container.height();

			// Set the canvas width and height to match the new dimensions
			$fire.attr('width', width);
			$fire.attr('height', height);

			// Also update the WebGL viewport to match the new dimensions
			const webgl_context = $fire[0].getContext("webgl2");
			if (webgl_context != null) {
				webgl_context.viewport(0, 0, width, height);
			}
		});
		jQuery(window).trigger('resize');
	}

	const urlParams = new URLSearchParams(window.location.search);
	const postType = urlParams.get('post_type');
	const postId = urlParams.get('post_id');
	// TODO: make dynamic call passed through localize
	if (postType === 'mds-pixel' && postId) {
		const row = jQuery('#post-' + postId);
		row.css('background-color', '#fffd99');
		row[0].scrollIntoView();
	}
});
