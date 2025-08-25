/*
 * Million Dollar Script Two - Enhanced Extensions Page
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2025, Ryan Rhode
 * @license     https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 */

jQuery(document).ready(function ($) {
	"use strict";

	// Enhanced notice system with better UX
	function showNotice(type, message, autoHide = true) {
		const noticeClass = type === "error" ? "notice-error" : "notice-success";
		const icon = type === "error" ? "❌" : "✅";
		const noticeHtml = `
            <div class="notice ${noticeClass} is-dismissible mds-enhanced-notice">
                <p><span class="mds-notice-icon">${icon}</span> ${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `;

		// Remove existing notices of the same type
		$(`.notice.${noticeClass}`).remove();

		// Add notice after the header and animate in without inline styles
		const $notice = $(noticeHtml);
		$(".mds-extensions-header, .wrap > h1").first().after($notice);
		$notice.hide().slideDown(300);

		// Auto-hide success notices
		if (autoHide && type === "success") {
			setTimeout(() => {
				$(`.notice.${noticeClass}`).slideUp(300, function() {
					$(this).remove();
				});
			}, 5000);
		}

		// Make notice dismissible
		$(document).off('click', '.notice-dismiss').on('click', '.notice-dismiss', function () {
			$(this).closest('.notice').slideUp(300, function() {
				$(this).remove();
			});
		});
	}

	// Enhanced button state management
	function setButtonLoading($button, loadingText) {
		$button.data('original-text', $button.html());
		$button.prop('disabled', true)
			.html(`<span class="mds-spinner"></span> ${loadingText}`)
			.addClass('mds-btn-loading');
	}

	function resetButton($button, text = null) {
		const originalText = text || $button.data('original-text');
		$button.prop('disabled', false)
			.html(originalText)
			.removeClass('mds-btn-loading');
	}

	// Handle check for updates button
	$(document).on("click", ".mds-check-updates", function (e) {
		e.preventDefault();

		const $button = $(this);
		const $row = $button.closest("tr");
		const extensionId = $row.data("extension-id");
		const currentVersion = $row.data("version");
		const pluginFile = $row.data("plugin-file");

		setButtonLoading($button, 'Checking...');

		// Make AJAX request to check for updates
		$.ajax({
			url: MDS_EXTENSIONS_DATA.ajax_url,
			type: "POST",
			data: {
				action: "mds_check_extension_updates",
				nonce: MDS_EXTENSIONS_DATA.nonce,
				extension_id: extensionId,
				current_version: currentVersion,
				plugin_file: pluginFile,
			},
			dataType: "json",
			success: function (response) {
				if (response.success) {
					handleUpdateResponse(response.data, $row);
				} else {
					showNotice("error", response.data.message || "Failed to check for updates.");
					resetButton($button);
				}
			},
			error: function (xhr, status, error) {
				console.error("Error checking for updates:", error);
				showNotice("error", "An error occurred while checking for updates.");
				resetButton($button);
			},
		});
	});

	// Handle install update button
	$(document).on("click", ".mds-install-update", function (e) {
		e.preventDefault();

		const $button = $(this);
		const extensionId = $button.data("extension-id");
		const extensionSlug = $button.data("extension-slug");
		const downloadUrl = $button.data("download-url");

		if (!confirm("🚀 Ready to update? This will enhance your extension with the latest features and improvements!")) {
			return;
		}

		setButtonLoading($button, 'Updating...');

		// Make AJAX request to install update
		$.ajax({
			url: MDS_EXTENSIONS_DATA.ajax_url,
			type: "POST",
			data: {
				action: "mds_install_extension_update",
				nonce: MDS_EXTENSIONS_DATA.nonce,
				extension_id: extensionId,
				extension_slug: extensionSlug,
				download_url: downloadUrl,
			},
			dataType: "json",
			success: function (response) {
				if (response.success) {
					showNotice("success", "🎉 " + (response.data.message || "Update installed successfully!"));
					if (response.data.reload) {
						setTimeout(() => window.location.reload(), 2000);
					}
				} else {
					showNotice("error", response.data.message || "Failed to install update.");
					resetButton($button);
				}
			},
			error: function (xhr, status, error) {
				console.error("Error installing update:", error);
				showNotice("error", "An error occurred while installing the update.");
				resetButton($button);
			},
		});
	});

	// Handle install extension button (enhanced with marketing copy)
	$(document).on("click", ".mds-install-extension", function (e) {
		e.preventDefault();

		const $button = $(this);
		const extensionId = $button.data("extension-id");
		const extensionSlug = $button.data("extension-slug");

		// Enhanced confirmation with marketing psychology
		const confirmMessage = `🚀 Install this extension and start boosting your results today? \n\nThis extension is trusted by thousands of users worldwide! \n\n✅ Free installation \n⚡ Instant activation \n📈 Proven results`;

		if (!confirm(confirmMessage)) {
			return;
		}

		setButtonLoading($button, 'Installing...');

		// Make AJAX request to install extension
		$.ajax({
			url: MDS_EXTENSIONS_DATA.ajax_url,
			type: "POST",
			data: {
				action: "mds_install_extension",
				nonce: MDS_EXTENSIONS_DATA.nonce,
				extension_id: extensionId,
				extension_slug: extensionSlug,
			},
			dataType: "json",
			success: function (response) {
				if (response.success) {
					showNotice("success", "🎉 Awesome! Extension installed and ready to boost your results!");
					
					// Update button
					$button.removeClass('mds-btn-primary').addClass('mds-btn-active')
						.html('<span class="mds-btn-icon">✓</span> Installed');
					
					if (response.data.reload) {
						setTimeout(() => window.location.reload(), 2000);
					}
				} else {
					showNotice("error", response.data.message || "Failed to install extension.");
					resetButton($button);
				}
			},
			error: function (xhr, status, error) {
				console.error("Error installing extension:", error);
				showNotice("error", "An error occurred while installing the extension.");
				resetButton($button);
			},
		});
	});

	// Handle activate extension button
	$(document).on("click", ".mds-activate-extension", function (e) {
		e.preventDefault();

		const $button = $(this);
		const extensionSlug = $button.data("extension-slug");
		const nonce = $button.data("nonce");

		if (!confirm("⚡ Activate this extension and start using its powerful features?")) {
			return;
		}

		setButtonLoading($button, 'Activating...');

		// Make AJAX request to activate extension
		$.ajax({
			url: MDS_EXTENSIONS_DATA.ajax_url,
			type: "POST",
			data: {
				action: "mds_activate_extension",
				nonce: nonce,
				extension_slug: extensionSlug,
			},
			dataType: "json",
			success: function (response) {
				if (response.success) {
					showNotice("success", "🎉 Extension is now active and ready to use!", false);
					
					// Update button state to match server-rendered active badge
					const $active = $('<span class="button button-secondary mds-ext-active" disabled="disabled">Active</span>');
					$button.replaceWith($active);
					
				} else {
					showNotice("error", response.data.message || "Failed to activate extension.");
					resetButton($button);
				}
			},
			error: function (xhr, status, error) {
				console.error("Error activating extension:", error);
				showNotice("error", "An error occurred while activating the extension.");
				resetButton($button);
			},
		});
	});

	// Handle Check All for Updates button
	$(document).on("click", ".mds-check-all-updates", function (e) {
		e.preventDefault();

		const $button = $(this);
		const $rows = $("tr[data-extension-id]");
		let completed = 0;
		let hasUpdates = false;

		setButtonLoading($button, 'Checking All...');

		// Check each extension for updates
		$rows.each(function () {
			const $row = $(this);
			const $updateButton = $row.find(".mds-check-updates");

			// Skip if already checking
			if ($updateButton.prop("disabled")) {
				completed++;
				checkAllComplete();
				return;
			}

			// Simulate click on the check updates button
			$updateButton.trigger("click");

			// Listen for update check completion
			$row.off("mds-update-checked").on("mds-update-checked", function () {
				completed++;
				if ($row.find(".mds-update-available").length) {
					hasUpdates = true;
				}
				checkAllComplete();
			});
		});

		// Check if all updates have been checked
		function checkAllComplete() {
			if (completed >= $rows.length) {
				resetButton($button);

				if (hasUpdates) {
					showNotice("success", "🔄 Update check complete! Some extensions have updates available.");
				} else {
					showNotice("success", "✅ All extensions are up to date!");
				}
			}
		}
	});

	/**
	 * Handle the update check response
	 */
	function handleUpdateResponse(updateInfo, $row) {
		const $updateCell = $row.find(".mds-update-cell");
		const $button = $row.find(".mds-check-updates");

		if (updateInfo.update_available) {
			// Show update available message with enhanced styling
			const html = `
				<div class="mds-update-available">
					<p><strong>🚀 Version ${updateInfo.latest_version} available!</strong></p>
					${updateInfo.changelog ? `<div class="mds-changelog"><strong>What's new:</strong><br>${updateInfo.changelog}</div>` : ""}
					<button class="button button-primary mds-install-update"
							data-download-url="${updateInfo.download_url}"
							data-extension-slug="${updateInfo.extension_slug}"
							data-extension-id="${updateInfo.extension_id || ''}">
						<span class="mds-btn-icon">⬆️</span> Update Now
					</button>
				</div>
			`;
			$updateCell.html(html);
			resetButton($button, "Check Again");
		} else {
			// No updates available
			$updateCell.html('<span class="mds-no-updates">✅ You have the latest version.</span>');
			resetButton($button);
		}

		// Trigger event to notify that update check is complete for this row
		$row.trigger("mds-update-checked");
	}

	console.log('🚀 MDS Extensions enhanced interface loaded successfully!');
});
