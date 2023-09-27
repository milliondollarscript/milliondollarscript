jQuery(function ($) {

	let $changed = null;

	// TODO: Implement some way to not change the width/height if they aren't defaults.
	// Add a new field to lock the width/height. Should it be one field for both width/height or one lock each?
	function update_fields() {
		const $fields = $changed.closest('.cf-block__fields');
		const $id = $fields.find( $('input[name="' + MDS.MDS_PREFIX + 'id"]'));
		const $width = $fields.find($('input[name="' + MDS.MDS_PREFIX + 'width"]'));
		const $height = $fields.find($('input[name="' + MDS.MDS_PREFIX + 'height"]'));
		const $type = $fields.find($('select[name="' + MDS.MDS_PREFIX + 'type"]'));

		let payload = {
			id: $id.val(),
			type: $type.val(),
			width: $width.val(),
			height: $height.val()
		};

		jQuery.ajax({
			url: MDS.ajaxurl,
			data: {
				action: "mds_admin_ajax",
				payload: payload,
				mds_admin_ajax_nonce: MDS.mds_admin_ajax_nonce
			},
			type: "POST",
			dataType: "json",
			success: function (response) {
				if (response.success) {
					$width.val(response.data.width);
					$height.val(response.data.height);
				}
			}
		});
	}

	// When the type select box is changed, update the fields.
	$(document).on('change', 'select[name="milliondollarscript_type"]', function () {
		$changed = jQuery(this);
		update_fields();
	});

	// When the grid id is changed, wait a second, then update the fields.
	let timer = null;
	$(document).on('change', 'input[name="milliondollarscript_id"]', function () {
		$changed = jQuery(this);
		clearTimeout(timer);
		timer = setTimeout(function () {
			update_fields();
		}, 1000)
	});
});