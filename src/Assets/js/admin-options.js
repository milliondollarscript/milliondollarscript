jQuery(document).ready(function ($) {
	const { select, dispatch } = window.cf.vendor["@wordpress/data"];
	const metaboxes = select("carbon-fields/metaboxes");
	const { updateFieldValue } = dispatch("carbon-fields/metaboxes");
	const fields = metaboxes.getFields();

	// Fix for TinyMCE null node error in Carbon Fields
	if (window.tinymce) {
		const originalExecCommand = window.tinymce.Editor.prototype.execCommand;
		window.tinymce.Editor.prototype.execCommand = function(cmd, ui, value, args) {
			try {
				// Check if editor is properly initialized and has valid DOM
				if (!this.getElement() || !this.getElement().offsetParent) {
					return false;
				}
				return originalExecCommand.call(this, cmd, ui, value, args);
			} catch (error) {
				console.warn('TinyMCE execCommand error prevented:', error);
				return false;
			}
		};
	}

	// Dark Mode Theme Switching with Confirmation
	let originalThemeValue = $('input[name="_mds_theme_mode"]:checked').val();
	
	// Watch for theme mode changes
	$('input[name="_mds_theme_mode"]').on('change', function() {
		const newTheme = $(this).val();
		const currentTheme = originalThemeValue;
		
		if (newTheme !== currentTheme) {
			showThemeSwitchConfirmation(currentTheme, newTheme, $(this));
		}
	});
	
	function showThemeSwitchConfirmation(currentTheme, newTheme, radioElement) {
		// Revert radio selection temporarily
		$('input[name="_mds_theme_mode"][value="' + currentTheme + '"]').prop('checked', true);
		
		const confirmMessage = "You are switching from " + currentTheme + " to " + newTheme + " mode.\n" + 
							   "Your current settings will be backed up.\n\n" + 
							   "Continue?";
		
		const processThemeSwitch = function() {
			// User confirmed, apply the change
			radioElement.prop('checked', true);
			originalThemeValue = newTheme;
			
			// Show processing message
			showThemeProcessingMessage(newTheme);
			
			// Trigger form save after theme change
			setTimeout(function() {
				$('#submit').trigger('click');
			}, 100);
		};
		
		if (typeof MDSModalUtility !== 'undefined') {
			MDSModalUtility.showConfirmation('Switch Theme Mode?', confirmMessage, processThemeSwitch);
		} else if (confirm("Switch Theme Mode?\n\n" + confirmMessage)) {
			processThemeSwitch();
		}
	}
	
	function showThemeProcessingMessage(theme) {
		const message = $('<div class="notice notice-info mds-theme-processing">' +
						 '<p><strong>Processing theme change...</strong> Applying ' + theme + ' mode colors and regenerating styles.</p>' +
						 '</div>');
		
		$('.wrap h1').after(message);
		
		// Remove after a few seconds
		setTimeout(function() {
			$('.mds-theme-processing').fadeOut();
		}, 3000);
	}
	
	// Add CSS for theme processing message and button states
	if (!$('#mds-theme-admin-styles').length) {
		$('head').append(`
			<style id="mds-theme-admin-styles">
				.mds-theme-processing {
					animation: pulse 1.5s infinite;
				}
				@keyframes pulse {
					0%, 100% { opacity: 1; }
					50% { opacity: 0.7; }
				}
				
				/* Admin Button States with CSS Variables */
				.mds-admin-button-processing {
					background-color: var(--mds-accessible-warning, #f59e0b) !important;
					color: #ffffff !important;
				}
				
				.mds-admin-button-success {
					background-color: var(--mds-accessible-success, #10b981) !important;
					color: #ffffff !important;
				}
				
				.mds-admin-button-error {
					background-color: var(--mds-accessible-error, #ef4444) !important;
					color: #ffffff !important;
				}
				
				.mds-admin-button-reset {
					background-color: '' !important;
					color: '' !important;
				}
				
				/* Row Highlighting */
				.mds-highlight-row {
					background-color: var(--mds-accessible-warning, #fef3c7) !important;
					color: var(--mds-text-primary, #000) !important;
				}
			</style>
		`);
	}

	const buttons = ["update_language", "create_pages", "delete_pages"];

	for (let i = 0; i < buttons.length; i++) {
		$("#mds_" + buttons[i]).on('click', function (e) {
			e.preventDefault();
			e.stopPropagation();

			const button = $(this);
			button.prop("disabled", true);
			
			// Use CSS class instead of hardcoded color
			button.removeClass('mds-admin-button-success mds-admin-button-error')
				  .addClass('mds-admin-button-processing');

			$.post(
				MDS.ajaxurl,
				{
					action: "mds_" + buttons[i],
					nonce: MDS.nonce,
				},
				function (data) {
					button.prop("disabled", false);
					
					// Remove processing state and apply result state
					button.removeClass('mds-admin-button-processing');
					
					if (data) {
						button.addClass('mds-admin-button-success');
					} else {
						button.addClass('mds-admin-button-error');
					}
					
					setTimeout(function () {
						// Reset to default styling
						button.removeClass('mds-admin-button-success mds-admin-button-error mds-admin-button-processing');
					}, 5000);

					// Update Carbon Fields options.
					if (
						button.attr("id") === "mds_create_pages" ||
						button.attr("id") === "mds_delete_pages"
					) {
						const pages = JSON.parse(MDS.pages);
						for (let page in pages) {
							const option = pages[page]["option"];
							let field = Object.values(fields).find(
								(f) => f.base_name === MDS.MDS_PREFIX + option,
							);
							updateFieldValue(field.id, data[option]);
						}
					}
				},
			);
		});
	}
});
