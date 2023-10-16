// wp.data.dispatch( 'core/annotations' ).addAnnotation( {
// 	source: 'my-annotations-plugin',
// 	blockClientId: wp.data.select( 'core/block-editor' ).getBlockOrder()[ 0 ],
// 	richTextIdentifier: 'content',
// 	range: {
// 		start: 50,
// 		end: 100,
// 	},
// } );

jQuery(document).on('carbonFields.apiLoaded', function (e, api) {
	console.log('carbonFields.apiLoaded');
	console.log(e, api);
});
(function () {
	console.log('jQuery loaded');

	const {addAction} = window.cf.hooks;

	addAction('carbon-fields.init', 'carbon-fields/blocks', () => {
		console.log('carbon fields blocks loaded');

		const {select} = window.cf.vendor['@wordpress/data'];
		console.log('select', select);

		const metaboxes = select('carbon-fields/metaboxes');
		console.log('metaboxes', metaboxes);

		console.log('getCachedResolvers', metaboxes.getCachedResolvers());
		console.log('getComplexGroupValues', metaboxes.getComplexGroupValues());
		console.log('getContainerById', metaboxes.getContainerById());
		console.log('getContainers', metaboxes.getContainers());
		console.log('getFieldById', metaboxes.getFieldById());
		console.log('getFields', metaboxes.getFields());
		console.log('getFieldsByContainerId', metaboxes.getFieldsByContainerId());
		console.log('getIsResolving', metaboxes.getIsResolving());
		console.log('getResolutionError', metaboxes.getResolutionError());
		console.log('getResolutionState', metaboxes.getResolutionState());
		console.log('hasFinishedResolution', metaboxes.hasFinishedResolution());
		console.log('hasResolvingSelectors', metaboxes.hasResolvingSelectors());
		console.log('hasStartedResolution', metaboxes.hasStartedResolution());
		console.log('isDirty', metaboxes.isDirty());
		console.log('isFieldUpdated', metaboxes.isFieldUpdated());
		console.log('isResolving', metaboxes.isResolving());
		console.log('isSavingLocked', metaboxes.isSavingLocked());

		const fields = metaboxes.getFieldsByContainerId('carbon-fields/million-dollar-script');
		console.log(fields);

		const typeField = fields.find((field) => field.base_name === MDS.MDS_PREFIX + 'type');
		console.log(typeField);
	});
})();

jQuery(document).ready(function(){
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
					$width.trigger('change');
					$height.trigger('change');
					// const onChangeBorderWidth = newBorderWidth => {
					// 	props.setAttributes( { borderWidth: newBorderWidth.target.value })
					// }
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