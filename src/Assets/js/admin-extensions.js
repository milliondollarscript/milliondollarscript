/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2025, Ryan Rhode
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

jQuery(document).ready(function ($) {
	"use strict";

	// Handle check for updates button
	$(document).on("click", ".mds-check-updates", function (e) {
		e.preventDefault();

		const $button = $(this);
		const $row = $button.closest("tr");
		const extensionId = $row.data("extension-id");
		const currentVersion = $row.data("version");
		const pluginFile = $row.data("plugin-file"); // Added this line

		// Disable button and show spinner
		$button
			.prop("disabled", true)
			.html('<span class="spinner is-active"></span> Checking...');

		// Make AJAX request to check for updates
		$.ajax({
			url: MDS_EXTENSIONS_DATA.ajax_url,
			type: "POST",
			data: {
				action: "mds_check_extension_updates",
				nonce: MDS_EXTENSIONS_DATA.nonce,
				extension_id: extensionId,
				current_version: currentVersion,
				plugin_file: pluginFile, // Added this line
			},
			dataType: "json",
			success: function (response) {
				if (response.success) {
					handleUpdateResponse(response.data, $row);
				} else {
					showNotice(
						"error",
						response.data.message || "Failed to check for updates.",
					);
					resetButton($button, "Check for Updates");
				}
			},
			error: function (xhr, status, error) {
				console.error("Error checking for updates:", error);
				showNotice("error", "An error occurred while checking for updates.");
				resetButton($button, "Check for Updates");
			},
		});
	});

	// Handle install update button
	$(document).on("click", ".mds-install-update", function (e) {
		e.preventDefault();

		const $button = $(this);
		const $row = $button.closest("tr");
		const extensionId = $button.data("extension-id"); // This is UUID
		const extensionSlug = $button.data("extension-slug"); // This is the slug
		const downloadUrl = $button.data("download-url");

		if (!confirm("Are you sure you want to install this update?")) {
			return;
		}

		// Disable button and show spinner
		$button
			.prop("disabled", true)
			.html('<span class="spinner is-active"></span> Updating...');

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
					showNotice(
						"success",
						response.data.message || "Update installed successfully.",
					);
					if (response.data.reload) {
						setTimeout(() => window.location.reload(), 1500);
					}
				} else {
					showNotice(
						"error",
						response.data.message || "Failed to install update.",
					);
					resetButton($button, "Install Update");
				}
			},
			error: function (xhr, status, error) {
				console.error("Error installing update:", error);
				showNotice("error", "An error occurred while installing the update.");
				resetButton($button, "Install Update");
			},
		});
	});

	/**
	 * Handle the update check response
	 */
	function handleUpdateResponse(updateInfo, $row) {
		const $updateCell = $row.find(".mds-update-cell");
		const $button = $row.find(".mds-check-updates");

		if (updateInfo.update_available) {
			// Show update available message with version and changelog
			let html = `
                <div class="mds-update-available">
                    <p><strong>Version ${updateInfo.latest_version} is available!</strong></p>
                    ${updateInfo.changelog ? `<div class="mds-changelog">${updateInfo.changelog}</div>` : ""}
                    <p>
                        <button class="button button-primary mds-install-update"
                                data-download-url="${updateInfo.download_url}"
                                data-extension-slug="${updateInfo.extension_slug}">
                            Update Now
                        </button>
                    </p>
                </div>
            `;
			$updateCell.html(html);
			resetButton($button, "Check Again");
		} else {
			// No updates available
			$updateCell.html(
				'<span class="mds-no-updates">You have the latest version.</span>',
			);
			resetButton($button, "Check for Updates");
		}

		// Trigger event to notify that update check is complete for this row
		$row.trigger("mds-update-checked");
	}

	/**
	 * Show a notice message
	 */
	function showNotice(type, message) {
		const noticeClass = type === "error" ? "notice-error" : "notice-success";
		const noticeHtml = `
            <div class="notice ${noticeClass} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `;

		// Add notice after the h1 heading
		$(".wrap > h1").after(noticeHtml);

		// Make notice dismissible
		$(document).on("click", ".notice-dismiss", function () {
			$(this)
				.closest(".notice")
				.fadeOut(200, function () {
					$(this).remove();
				});
		});
	}

	/**
	 * Reset a button to its original state
	 */
	function resetButton($button, text) {
		$button.prop("disabled", false).html(text);
	}

	// Handle Check All for Updates button
	$(document).on("click", ".mds-check-all-updates", function (e) {
		e.preventDefault();

		const $button = $(this);
		const $rows = $("tr[data-extension-id]");
		let completed = 0;
		let hasUpdates = false;

		// Disable button and show spinner
		$button
			.prop("disabled", true)
			.html('<span class="spinner is-active"></span> Checking All...');

		// Check each extension for updates
		$rows.each(function () {
			const $row = $(this);
			const extensionId = $row.data("extension-id");
			const currentVersion = $row.data("version");
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
				resetButton($button, "Check All for Updates");

				if (hasUpdates) {
					showNotice(
						"success",
						"Finished checking for updates. Some extensions have updates available.",
					);
				} else {
					showNotice("success", "All extensions are up to date.");
				}
			}
		}
	});

	// Handle install extension button
	$(document).on("click", ".mds-install-extension", function (e) {
		e.preventDefault();

		const $button = $(this);
		const extensionId = $button.data("extension-id"); // This is UUID
		const extensionSlug = $button.data("extension-slug"); // This is the slug

		if (!confirm("Are you sure you want to install this extension?")) {
			return;
		}

		// Disable button and show spinner
		$button
			.prop("disabled", true)
			.html('<span class="spinner is-active"></span> Installing...');

		// Make AJAX request to install extension
		$.ajax({
			url: MDS_EXTENSIONS_DATA.ajax_url,
			type: "POST",
			data: {
				action: "mds_install_extension",
				nonce: MDS_EXTENSIONS_DATA.nonce,
				extension_id: extensionId,      // UUID
				extension_slug: extensionSlug,  // Slug
			},
			dataType: "json",
			success: function (response) {
				if (response.success) {
					showNotice(
						"success",
						response.data.message || "Extension installed successfully.",
					);
					if (response.data.reload) {
						setTimeout(() => window.location.reload(), 1500);
					}
				} else {
					showNotice(
						"error",
						response.data.message || "Failed to install extension.",
					);
					resetButton($button, "Install");
				}
			},
			error: function (xhr, status, error) {
				console.error("Error installing extension:", error);
				showNotice("error", "An error occurred while installing the extension.");
				resetButton($button, "Install");
			},
		});
	});

	// Handle activate extension button
	$(document).on("click", ".mds-activate-extension", function (e) {
		e.preventDefault();

		const $button = $(this);
		const extensionSlug = $button.data("extension-slug"); // Slug
		const nonce = $button.data("nonce"); // Nonce from the button

		if (!confirm(MDS_EXTENSIONS_DATA.i18n?.confirm_activation || "Are you sure you want to activate this extension?")) {
			return;
		}

		// Disable button and show spinner
		$button
			.prop("disabled", true)
			.html('<span class="spinner is-active"></span> ' + (MDS_EXTENSIONS_DATA.i18n?.activating || 'Activating...'));

		// Make AJAX request to activate extension
		$.ajax({
			url: MDS_EXTENSIONS_DATA.ajax_url,
			type: "POST",
			data: {
				action: "mds_activate_extension", // New AJAX action
				nonce: nonce, // Use nonce from the button
				extension_slug: extensionSlug,
			},
			dataType: "json",
			success: function (response) {
				if (response.success) {
					showNotice(
						"success",
						response.data.message || (MDS_EXTENSIONS_DATA.i18n?.activation_success || "Extension activated successfully.")
					);
					// Reload the page to reflect changes (e.g., button state, active plugins list)
					if (response.data.reload !== false) { // Allow explicit no-reload
						setTimeout(() => window.location.reload(), 1500);
					} else {
                        // If no reload, manually update button (though reload is generally better)
                        $button.removeClass('mds-activate-extension button-secondary')
                               .addClass('mds-ext-active button-disabled') // Assuming button-disabled or similar class
                               .text(MDS_EXTENSIONS_DATA.i18n?.active || 'Active')
                               .prop('disabled', true);
                    }
				} else {
					showNotice(
						"error",
						response.data.message || (MDS_EXTENSIONS_DATA.i18n?.activation_failed || "Failed to activate extension.")
					);
					resetButton($button, MDS_EXTENSIONS_DATA.i18n?.activate || "Activate");
				}
			},
			error: function (xhr, status, error) {
				console.error("Error activating extension:", error);
				showNotice("error", MDS_EXTENSIONS_DATA.i18n?.activation_error || "An error occurred while activating the extension.");
				resetButton($button, MDS_EXTENSIONS_DATA.i18n?.activate || "Activate");
			},
		});
	});

});
