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

	// Fix Carbon Fields color picker reset button to use default values
	function fixColorPickerResetButtons() {
		// Wait for Carbon Fields to be fully initialized
		setTimeout(function() {
			console.log('🔧 Setting up Carbon Fields color picker reset functionality...');
			
			let setupCount = 0;
			
			// Find all reset buttons and set up functionality
			$('.cf-color__reset').each(function() {
				const resetButton = $(this);
				const colorContainer = resetButton.closest('.cf-color');
				
				// Get the hidden input which contains the field ID and name
				const hiddenInput = colorContainer.find('input[type="hidden"]');
				const fieldId = hiddenInput.attr('id');
				const rawFieldName = hiddenInput.attr('name');
				
				if (!fieldId || !rawFieldName) {
					console.log('⚠️ Skipping reset button - no field ID or name found');
					return;
				}
				
				// Extract clean field name from Carbon Fields wrapper syntax
				let cleanFieldName = null;
				if (rawFieldName.includes('carbon_fields_compact_input[_')) {
					cleanFieldName = rawFieldName
						.replace('carbon_fields_compact_input[_', '')
						.replace(/\].*$/, '');
				}
				
				// Find the field in Carbon Fields store using the field ID (most reliable)
				let field = Object.values(fields).find(f => f.id === fieldId);
				
				// Fallback: try to find by base_name if ID matching fails
				if (!field && cleanFieldName) {
					field = Object.values(fields).find(f => f.base_name === cleanFieldName);
				}
				
				if (!field || !field.default_value) {
					console.log('⚠️ Skipping reset button - field not found or no default value:', {
						fieldId: fieldId,
						cleanFieldName: cleanFieldName,
						hasField: !!field,
						hasDefault: field ? !!field.default_value : false
					});
					return;
				}
				
				console.log('✅ Setting up reset for field:', field.base_name, 'default:', field.default_value);
				
				// Enable the reset button (it might be disabled)
				resetButton.prop('disabled', false);
				
				// Override the reset button click handler
				resetButton.off('click').on('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					
					console.log('🔄 Resetting field:', field.base_name, 'to default:', field.default_value);
					
					// Update Carbon Fields store
					updateFieldValue(field.id, field.default_value);
					
					// Update the hidden input
					hiddenInput.val(field.default_value).trigger('change');
					
					// Update the color preview
					const colorPreview = colorContainer.find('.cf-color__preview');
					if (colorPreview.length) {
						colorPreview.css('background-color', field.default_value);
					}
					
					// Update any text inputs (for manual color entry)
					const textInput = colorContainer.find('input[type="text"]');
					if (textInput.length) {
						textInput.val(field.default_value);
					}
					
					console.log('✅ Reset complete for:', field.base_name);
				});
				
				setupCount++;
			});
			
			console.log(`🎉 Reset functionality set up for ${setupCount} color fields`);
		}, 2000);
	}

	// Initialize color picker fixes
	fixColorPickerResetButtons();
	
	// Re-run after any field changes (for dynamically added fields) using MutationObserver
	const observer = new MutationObserver(function(mutations) {
		let shouldRefix = false;
		mutations.forEach(function(mutation) {
			if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
				// Check if any color field was added
				for (let node of mutation.addedNodes) {
					if (node.nodeType === 1 && (
						node.classList && node.classList.contains('cf-color') ||
						node.querySelector && node.querySelector('.cf-color')
					)) {
						shouldRefix = true;
						break;
					}
				}
			}
		});
		
		if (shouldRefix) {
			setTimeout(fixColorPickerResetButtons, 100);
		}
	});
	
	// Start observing
	observer.observe(document.body, {
		childList: true,
		subtree: true
	});

	// Dark Mode Theme Switching with Confirmation
	let originalThemeValue = $('input[name="milliondollarscript_theme_mode"]:checked').val();
	
	// Watch for theme mode changes
	$('input[name="milliondollarscript_theme_mode"]').on('change', function() {
		const newTheme = $(this).val();
		const currentTheme = originalThemeValue;
		
		if (newTheme !== currentTheme) {
			showThemeSwitchConfirmation(currentTheme, newTheme, $(this));
		}
	});
	
	function showThemeSwitchConfirmation(currentTheme, newTheme, radioElement) {
		// Revert radio selection temporarily
		$('input[name="milliondollarscript_theme_mode"][value="' + currentTheme + '"]').prop('checked', true);
		
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

	// Color Export/Import Functionality
	function getColorFields() {
		// Filter for color fields that belong to MDS (milliondollarscript_)
		return Object.values(fields).filter(field => 
			field.type === 'color' && 
			field.base_name && 
			field.base_name.startsWith('milliondollarscript_')
		);
	}

	// Export colors functionality
	$('#mds_export_colors').on('click', function(e) {
		e.preventDefault();
		const button = $(this);
		button.prop('disabled', true).text('Exporting...');

		// Debug theme mode detection
		console.log('🔍 Debugging theme mode detection...');
		
		// Test different selectors for theme mode
		const selectors = [
			'input[name="milliondollarscript_theme_mode"]:checked',
			'input[name="carbon_fields_compact_input[_milliondollarscript_theme_mode]"]:checked',
			'input[name*="theme_mode"]:checked'
		];
		
		let theme_mode = null;
		selectors.forEach(selector => {
			const element = $(selector);
			const value = element.val();
			console.log(`Selector "${selector}": found ${element.length} elements, value: ${value}`);
			if (value && !theme_mode) {
				theme_mode = value;
			}
		});
		
		// Try to get theme mode from Carbon Fields store
		if (!theme_mode) {
			const themeField = Object.values(fields).find(f => f.base_name === 'milliondollarscript_theme_mode');
			if (themeField) {
				theme_mode = themeField.value;
				console.log('Got theme mode from Carbon Fields store:', theme_mode);
			}
		}
		
		// Fallback to checking which theme mode fields are visible (conditional logic)
		if (!theme_mode) {
			const darkFields = $('.cf-color input[name*="dark"]');
			const lightFields = $('.cf-color input[name*="background_color"]');
			
			if (darkFields.closest('.cf-color').is(':visible')) {
				theme_mode = 'dark';
				console.log('Detected dark mode from visible dark fields');
			} else if (lightFields.closest('.cf-color').is(':visible')) {
				theme_mode = 'light';
				console.log('Detected light mode from visible light fields');
			}
		}
		
		console.log('Final theme mode:', theme_mode);

		const colorFields = getColorFields();
		const exportData = {
			version: '1.0',
			exported_at: new Date().toISOString(),
			theme_mode: theme_mode || 'unknown',
			colors: {}
		};

		colorFields.forEach(field => {
			exportData.colors[field.base_name] = field.value || field.default_value || '';
		});

		// Create download
		const dataStr = JSON.stringify(exportData, null, 2);
		const dataBlob = new Blob([dataStr], {type: 'application/json'});
		const url = URL.createObjectURL(dataBlob);
		
		const downloadLink = document.createElement('a');
		downloadLink.href = url;
		downloadLink.download = `mds-colors-${exportData.theme_mode}-${new Date().toISOString().split('T')[0]}.json`;
		document.body.appendChild(downloadLink);
		downloadLink.click();
		document.body.removeChild(downloadLink);
		URL.revokeObjectURL(url);

		button.prop('disabled', false).html('<span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Export Colors');
	});

	// Import file selection
	$('#mds_import_file').on('change', function() {
		const file = this.files[0];
		$('#mds_import_colors').prop('disabled', !file);
	});

	// Import colors functionality
	$('#mds_import_colors').on('click', function(e) {
		e.preventDefault();
		const file = $('#mds_import_file')[0].files[0];
		if (!file) return;

		const button = $(this);
		button.prop('disabled', true).text('Importing...');

		const reader = new FileReader();
		reader.onload = function(e) {
			try {
				const importData = JSON.parse(e.target.result);
				
				if (!importData.colors) {
					throw new Error('Invalid file format');
				}

				// Import colors
				Object.entries(importData.colors).forEach(([fieldName, value]) => {
					const field = Object.values(fields).find(f => f.base_name === fieldName);
					if (field && value) {
						updateFieldValue(field.id, value);
					}
				});

				// Update theme mode if different
				if (importData.theme_mode) {
					$(`input[name="milliondollarscript_theme_mode"][value="${importData.theme_mode}"]`).prop('checked', true);
				}

				alert('Colors imported successfully! Click Save Changes to apply.');
				
			} catch (error) {
				alert('Error importing file: ' + error.message);
			}
			
			button.prop('disabled', false).html('<span class="dashicons dashicons-upload" style="vertical-align: middle;"></span> Import Colors');
			$('#mds_import_file').val('');
		};
		reader.readAsText(file);
	});

	// Reset to defaults functionality
	$('#mds_reset_colors').on('click', function(e) {
		e.preventDefault();
		
		console.log('🔄 Reset to Defaults button clicked');
		
		// Check if button exists and is found
		const button = $(this);
		console.log('Button found:', button.length > 0);
		
		if (!confirm('Are you sure you want to reset all colors to their default values? This cannot be undone.')) {
			return;
		}

		button.prop('disabled', true).text('Resetting...');

		const colorFields = getColorFields();
		console.log('🎨 Found color fields for reset:', colorFields.length);
		console.log('Color fields:', colorFields.map(f => ({ base_name: f.base_name, default_value: f.default_value })));
		
		let resetCount = 0;
		let skippedCount = 0;
		
		colorFields.forEach(field => {
			if (field.default_value && field.default_value !== '') {
				console.log('✅ Resetting field:', field.base_name, 'from:', field.value, 'to:', field.default_value);
				
				// Use the same mechanism that works for individual resets
				updateFieldValue(field.id, field.default_value);
				
				// Also update any visible UI elements for this field
				const hiddenInput = $(`input[type="hidden"][id="${field.id}"]`);
				if (hiddenInput.length) {
					hiddenInput.val(field.default_value).trigger('change');
					
					// Update the color preview for this field
					const colorContainer = hiddenInput.closest('.cf-color');
					const colorPreview = colorContainer.find('.cf-color__preview');
					if (colorPreview.length) {
						colorPreview.css('background-color', field.default_value);
					}
				}
				
				resetCount++;
			} else {
				console.log('⚠️ Skipping field with no default value:', field.base_name, 'default was:', field.default_value);
				skippedCount++;
			}
		});

		console.log(`🎉 Reset complete: ${resetCount} fields reset, ${skippedCount} skipped`);
		alert(`Reset ${resetCount} color fields to defaults! Click Save Changes to apply.`);
		button.prop('disabled', false).html('<span class="dashicons dashicons-update" style="vertical-align: middle;"></span> Reset to Defaults');
	});

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

// === MDS Options Assist: Auto-open System tab and highlight License Key when hash=#system ===
jQuery(document).ready(function ($) {
  function ensureAssistStyles() {
    if (!$('#mds-options-assist-styles').length) {
      $('head').append(`
        <style id="mds-options-assist-styles">
          .mds-highlight-field {
            outline: 3px solid #f59e0b !important;
            box-shadow: 0 0 0 3px rgba(245,158,11,.3) !important;
            transition: outline-color .3s ease;
          }
          .mds-highlight-field input[type="text"] {
            background: #fff7ed !important;
          }
        </style>
      `);
    }
  }

  function clickSystemTab() {
    // Try multiple selectors Carbon Fields might use for tabs
    const candidates = [
      '.cf-container__tabs button, .cf-container__tabs a',
      '.cf-tabs__nav button, .cf-tabs__nav a',
      '.carbon-theme-options button, .carbon-theme-options a',
      '.wrap .cf-container button, .wrap .cf-container a'
    ];
    for (const sel of candidates) {
      const $els = $(sel).filter(function () {
        const txt = ($(this).text() || '').trim().toLowerCase();
        return txt === 'system';
      });
      if ($els.length) {
        $els.first().trigger('click');
        return true;
      }
    }
    return false;
  }

  function findLicenseInput() {
    // Prefer compact input name used by Carbon Fields
    let $input = $('input[name*="_milliondollarscript_license_key"]');
    if (!$input.length) {
      // Fallbacks: by label text or generic search
      const $label = $('label, .cf-field__label').filter(function () {
        return ($(this).text() || '').trim().toLowerCase() === 'license key';
      }).first();
      if ($label.length) {
        $input = $label.closest('.cf-field').find('input[type="text"]').first();
      }
      if (!$input.length) {
        $input = $('input[type="text"]').filter(function () {
          return (this.name || '').toLowerCase().includes('license_key');
        }).first();
      }
    }
    return $input;
  }

  function highlightLicenseField() {
    const $input = findLicenseInput();
    if ($input && $input.length) {
      const $field = $input.closest('.cf-field').length ? $input.closest('.cf-field') : $input;
      $field.addClass('mds-highlight-field');
      $input.focus();
      // Scroll into view
      try { $field[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
      setTimeout(() => $field.removeClass('mds-highlight-field'), 4000);
    }
  }

  function openSystemTabAndHighlight() {
    ensureAssistStyles();
    // Carbon Fields initializes asynchronously; retry a few times
    let attempts = 0;
    const maxAttempts = 15;
    const timer = setInterval(() => {
      attempts++;
      const clicked = clickSystemTab();
      if (clicked || attempts >= maxAttempts) {
        clearInterval(timer);
        setTimeout(highlightLicenseField, 200);
      }
    }, 200);
  }

  // Trigger when arriving with #system hash
  if (window.location.hash && window.location.hash.toLowerCase() === '#system') {
    openSystemTabAndHighlight();
  }

  // Also support dynamic navigation to #system without full reload
  $(window).on('hashchange', function () {
    if (window.location.hash && window.location.hash.toLowerCase() === '#system') {
      openSystemTabAndHighlight();
    }
  });
});