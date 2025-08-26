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
		const noticeClass = type === "error" ? "notice-error" : (type === "warning" ? "notice-warning" : "notice-success");
		const icon = type === "error" ? "❌" : (type === "warning" ? "⚠️" : "✅");
		const noticeHtml = `
	           <div class="notice ${noticeClass} is-dismissible mds-enhanced-notice">
	               <p><span class="mds-notice-icon">${icon}</span> ${message}</p>
	               <button type="button" class="notice-dismiss">
	                   <span class="screen-reader-text">Dismiss this notice.</span>
	               </button>
	           </div>
	       `;

		// Remove only notices created by this script to avoid interfering with WP admin_notices
		$('.mds-enhanced-notice').remove();

		// Inject notice into the standard WP notices area (top of .wrap) without animations to avoid reflow
		const $notice = $(noticeHtml);
		const $wrap = $('.wrap').first();
		if ($wrap.length) { $notice.prependTo($wrap); } else { $notice.prependTo($('#wpbody-content').first()); }
		$notice.show();

		// Auto-hide success notices
		if (autoHide && type === "success") {
			setTimeout(() => {
				$('.mds-enhanced-notice.notice.' + noticeClass).remove();
			}, 5000);
		}

		// Make notice dismissible - scope to our enhanced notice only and avoid animations
		$(document).off('click', '.mds-enhanced-notice .notice-dismiss').on('click', '.mds-enhanced-notice .notice-dismiss', function () {
			$(this).closest('.mds-enhanced-notice').remove();
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

// Premium extensions UI gating on page load
(function gatePremiumInstallUI() {
	try {
		const licenseKey = (window.MDS_EXTENSIONS_DATA && MDS_EXTENSIONS_DATA.license_key)
			? String(MDS_EXTENSIONS_DATA.license_key).trim()
			: '';

		const extServerBase = (
			window.MDS_EXTENSIONS_DATA && MDS_EXTENSIONS_DATA.extension_server_url
				? MDS_EXTENSIONS_DATA.extension_server_url
				: 'http://localhost:15346'
		).replace(/\/+$/, '');

		// Target: premium, not-installed rows only
		const $premiumRows = $('table.widefat tbody tr[data-is-premium="true"]').filter(function () {
			return !$(this).attr('data-plugin-file');
		});

		function annotateBuy($row, text) {
			const $purchase = $row.find('.mds-purchase-buttons');
			if ($purchase.length && !$row.find('.mds-license-required-note').length) {
				$('<span class="mds-license-required-note" title="License required" style="margin-left:8px;color:#666;">' + (text || 'License required') + '</span>')
					.appendTo($purchase);
			}
		}

		function disableInstall($row, title) {
			const $btn = $row.find('.mds-install-extension');
			if (!$btn.length) return;
			$btn.prop('disabled', true)
				.addClass('disabled')
				.attr('aria-disabled', 'true')
				.attr('title', title || 'License required');
			annotateBuy($row, 'License required');
		}

		function enableInstall($row) {
			const $btn = $row.find('.mds-install-extension');
			if (!$btn.length) return;
			$btn.prop('disabled', false)
				.removeClass('disabled')
				.removeAttr('aria-disabled')
				.removeAttr('title');
			$row.find('.mds-license-required-note').remove();
		}

		if (!$premiumRows.length) return;

		// If no license at all, disable Install for all premium not-installed rows
		if (!licenseKey) {
			$premiumRows.each(function () {
				disableInstall($(this));
			});
			return;
		}

		// With a license present, validate sequentially per premium row to avoid bursts
		const rows = $premiumRows.toArray();

		function next(i) {
			if (i >= rows.length) return;
			const $row = $(rows[i]);
			const extensionId = $row.data('extension-id');

			// Conservative default: keep disabled while checking
			disableInstall($row, 'Validating license...');

			if (!extensionId) {
				// Missing ID; keep disabled
				return next(i + 1);
			}

			const url = extServerBase
				+ '/api/public/licenses/lookup?key='
				+ encodeURIComponent(licenseKey)
				+ '&extensionId='
				+ encodeURIComponent(String(extensionId));

			fetch(url, { method: 'GET', credentials: 'omit' })
				.then(res => res.ok ? res.json() : Promise.reject(new Error('HTTP ' + res.status)))
				.then(json => {
					// Accept either { valid: true } or { data: { valid: true } }
					const valid =
						(json && json.valid === true)
						|| (json && json.data && json.data.valid === true);

					if (valid === true) {
						enableInstall($row);
					} else {
						// Keep disabled and annotated
						disableInstall($row);
					}
				})
				.catch(() => {
					// Graceful failure: keep conservative (disabled)
					disableInstall($row);
				})
				.finally(() => next(i + 1));
		}

		next(0);
	} catch (e) {
		// On any unexpected error, conservatively disable premium not-installed rows
		$('table.widefat tbody tr[data-is-premium="true"]').filter(function () {
			return !$(this).attr('data-plugin-file');
		}).each(function () {
			const $row = $(this);
			const $btn = $row.find('.mds-install-extension');
			if ($btn.length) {
				$btn.prop('disabled', true)
					.addClass('disabled')
					.attr('aria-disabled', 'true')
					.attr('title', 'License required');
			}
			const $purchase = $row.find('.mds-purchase-buttons');
			if ($purchase.length && !$row.find('.mds-license-required-note').length) {
				$purchase.append('<span class="mds-license-required-note" title="License required" style="margin-left:8px;color:#666;">License required</span>');
			}
		});
	}
})();
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

	// Handle install extension button (no alert/confirm; gate premium installs)
	$(document).on("click", ".mds-install-extension", function (e) {
		e.preventDefault();

		const $button = $(this);
		const $row = $button.closest("tr");
		const extensionId = $button.data("extension-id");
		const extensionSlug = $button.data("extension-slug");

		// Determine if the extension is premium using the row attribute
		const isPremiumAttr = $row.data("is-premium");
		const isPremium = isPremiumAttr === true || isPremiumAttr === "true";

		// Check license presence from localized data
		const hasLicense = !!(MDS_EXTENSIONS_DATA.license_key && String(MDS_EXTENSIONS_DATA.license_key).trim());

		// Gate premium installs when license is missing
		if (isPremium && !hasLicense) {
			const settingsUrl = MDS_EXTENSIONS_DATA.settings_url || '#';
			showNotice(
				"warning",
				`This extension requires a valid license. Enter your license key at Million Dollar Script → Options → System → License Key. <a href="${settingsUrl}">Open System tab</a>`,
				false
			);
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
