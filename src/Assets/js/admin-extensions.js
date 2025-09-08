/*
 * Million Dollar Script Two - Extensions admin (merged canonical)
 * GPL-3.0
 */

jQuery(document).ready(function ($) {
	"use strict";

	// Enhanced notice system with better UX and non-animated injection
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

		$('.mds-enhanced-notice').remove();

		const $notice = $(noticeHtml);
		// Prefer in-page anchor below header to avoid notices appearing inside the header area
		const $anchor = $('#mds-extensions-notices');
		if ($anchor.length) {
			$notice.appendTo($anchor);
		} else {
			// Fallback: place immediately after the extensions header if present, else into wpbody-content
			const $header = $('#mds-extensions-page .mds-extensions-header').first();
			if ($header.length) {
				$notice.insertAfter($header);
			} else {
				$notice.prependTo($('#wpbody-content').first());
			}
		}
		$notice.show();

		if (autoHide && type === "success") {
			setTimeout(() => {
				$('.mds-enhanced-notice.notice.' + noticeClass).remove();
			}, 5000);
		}

		$(document).off('click', '.mds-enhanced-notice .notice-dismiss').on('click', '.mds-enhanced-notice .notice-dismiss', function () {
			$(this).closest('.mds-enhanced-notice').remove();
		});
	}

	// Enhanced button state management
	function setButtonLoading($button, loadingText) {
		$button.data('original-html', $button.html());
		$button.prop('disabled', true)
			.html(`<span class="spinner is-active"></span> ${loadingText}`)
			.addClass('mds-btn-loading');
	}

	function resetButton($button, text = null) {
		const original = text || $button.data('original-html') || $button.html();
		$button.prop('disabled', false)
			.html(original)
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

			if (!licenseKey) {
				$premiumRows.each(function () {
					disableInstall($(this));
				});
				return;
			}

			const rows = $premiumRows.toArray();

			function next(i) {
				if (i >= rows.length) return;
				const $row = $(rows[i]);
				const extensionId = $row.data('extension-id');

				disableInstall($row, 'Validating license...');

				if (!extensionId) {
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
						const valid =
							(json && json.valid === true)
							|| (json && json.data && json.data.valid === true);

						if (valid === true) {
							enableInstall($row);
						} else {
							disableInstall($row);
						}
					})
					.catch(() => {
						disableInstall($row);
					})
					.finally(() => next(i + 1));
			}

			next(0);
		} catch (e) {
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
			error: function () {
				showNotice("error", "An error occurred while checking for updates.");
				resetButton($button);
			},
		});
	});

	// Handle install update button
	$(document).on("click", ".mds-install-update", function (e) {
		e.preventDefault();

		const $button = $(this);
		const $row = $button.closest("tr");
		const extensionId = $button.data("extension-id") || $row.data("extension-id");
		const extensionSlug = $button.data("extension-slug") || '';
		const downloadUrl = $button.data("download-url");

		if (!downloadUrl || !extensionId) {
			showNotice("error", "Missing update package information.");
			return;
		}

		if (!confirm("Install this update now?")) {
			return;
		}

		setButtonLoading($button, 'Updating...');

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
					showNotice("success", response.data.message || "Update installed successfully.");
					if (response.data.reload) {
						setTimeout(() => window.location.reload(), 2000);
					}
				} else {
					showNotice("error", response.data.message || "Failed to install update.");
					resetButton($button);
				}
			},
			error: function () {
				showNotice("error", "An error occurred while installing the update.");
				resetButton($button);
			},
		});
	});

	// Handle install extension button
	$(document).on("click", ".mds-install-extension", function (e) {
		e.preventDefault();

		const $button = $(this);
		const $row = $button.closest("tr");
		const extensionId = $button.data("extension-id");
		const extensionSlug = $button.data("extension-slug");

		const isPremiumAttr = $row.data("is-premium");
		const isPremium = isPremiumAttr === true || isPremiumAttr === "true";
		const hasLicense = !!(MDS_EXTENSIONS_DATA.license_key && String(MDS_EXTENSIONS_DATA.license_key).trim());

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
					showNotice("success", response.data.message || "Extension installed successfully.");
					if (response.data.reload) {
						setTimeout(() => window.location.reload(), 2000);
					}
				} else {
					showNotice("error", response.data.message || "Failed to install extension.");
					resetButton($button);
				}
			},
			error: function () {
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

		if (!confirm(MDS_EXTENSIONS_DATA.i18n?.confirm_activation || "Are you sure you want to activate this extension?")) {
			return;
		}

		setButtonLoading($button, (MDS_EXTENSIONS_DATA.i18n?.activating || 'Activating...'));

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
					showNotice("success", response.data.message || (MDS_EXTENSIONS_DATA.i18n?.activation_success || "Extension activated successfully."));
					if (response.data.reload !== false) {
						setTimeout(() => window.location.reload(), 1500);
					} else {
						const $active = $('<span class="button button-secondary mds-ext-active" disabled="disabled">Active</span>');
						$button.replaceWith($active);
					}
				} else {
					showNotice("error", response.data.message || (MDS_EXTENSIONS_DATA.i18n?.activation_failed || "Failed to activate extension."));
					resetButton($button, MDS_EXTENSIONS_DATA.i18n?.activate || "Activate");
				}
			},
			error: function () {
				showNotice("error", MDS_EXTENSIONS_DATA.i18n?.activation_error || "An error occurred while activating the extension.");
				resetButton($button, MDS_EXTENSIONS_DATA.i18n?.activate || "Activate");
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

		$rows.each(function () {
			const $row = $(this);
			const $updateButton = $row.find(".mds-check-updates");

			if ($updateButton.prop("disabled")) {
				completed++;
				checkAllComplete();
				return;
			}

			$updateButton.trigger("click");

			$row.off("mds-update-checked").on("mds-update-checked", function () {
				completed++;
				if ($row.find(".mds-update-available").length) {
					hasUpdates = true;
				}
				checkAllComplete();
			});
		});

		function checkAllComplete() {
			if (completed >= $rows.length) {
				resetButton($button);

				if (hasUpdates) {
					showNotice("success", "Finished checking for updates. Some extensions have updates available.");
				} else {
					showNotice("success", "All extensions are up to date.");
				}
			}
		}
	});

	// Handle the update check response
	function handleUpdateResponse(updateInfo, $row) {
		const $updateCell = $row.find(".mds-update-cell");
		const $button = $row.find(".mds-check-updates");

		const latestVersion = updateInfo.latest_version || updateInfo.new_version || updateInfo.version || '';
		const downloadUrl = updateInfo.download_url || updateInfo.package_url || '';
		const extensionId = $row.data('extension-id') || '';

		if (updateInfo.update_available) {
			const html = `
				<div class="mds-update-available">
					<p><strong>Version ${latestVersion} is available!</strong></p>
					${updateInfo.changelog ? `<div class="mds-changelog">${updateInfo.changelog}</div>` : ""}
					<p>
						<button class="button button-primary mds-install-update"
								data-download-url="${downloadUrl}"
								data-extension-id="${extensionId}">
							Update Now
						</button>
					</p>
				</div>
			`;
			$updateCell.html(html);
			resetButton($button, "Check Again");
		} else {
			$updateCell.html('<span class="mds-no-updates">You have the latest version.</span>');
			resetButton($button, "Check for Updates");
		}

		$row.trigger("mds-update-checked");
	}

	// Handle purchase extension button
	$(document).on("click", ".mds-purchase-extension", function (e) {
		e.preventDefault();

		const $button = $(this);
		const extensionId = $button.data("extension-id");
		const nonce = $button.data("nonce");

		setButtonLoading($button, 'Redirecting...');

		$.ajax({
			url: MDS_EXTENSIONS_DATA.ajax_url,
			type: "POST",
			data: {
				action: "mds_purchase_extension",
				nonce: nonce,
				extension_id: extensionId,
			},
			dataType: "json",
			success: function (response) {
				if (response.success && response.data && response.data.checkout_url) {
					window.location.href = response.data.checkout_url;
				} else {
					showNotice("error", (response.data && response.data.message) || "Failed to initiate purchase.");
					resetButton($button, "Purchase");
				}
			},
			error: function () {
				showNotice("error", "An error occurred while initiating the purchase.");
				resetButton($button, "Purchase");
			},
		});
	});

	// License activation handler
	$(document).on('click', '.mds-activate-license', function (e) {
		e.preventDefault();
		const $form = $(this).closest('.mds-license-form');
		const $button = $(this);
		const extension_slug = $form.data('extension-slug');
		const license_key = $form.find('input[name="license_key"]').val();
		const nonce = $form.find('input[name="nonce"]').val();

		setButtonLoading($button, 'Activating...');

		$.post(MDS_EXTENSIONS_DATA.ajax_url, {
			action: 'mds_activate_license',
			extension_slug: extension_slug,
			license_key: license_key,
			nonce: nonce,
		})
			.done(function (response) {
				if (response && response.success) {
					location.reload();
				} else {
					const msg = response && response.data && response.data.message ? response.data.message : 'Activation failed.';
					showNotice('error', msg, false);
					resetButton($button, 'Activate');
				}
			})
			.fail(function () {
				showNotice('error', 'An error occurred during activation.', false);
				resetButton($button, 'Activate');
			});
	});

	// License deactivation handler
	$(document).on('click', '.mds-deactivate-license', function (e) {
		e.preventDefault();
		const $form = $(this).closest('.mds-license-form');
		const $button = $(this);
		const extension_slug = $form.data('extension-slug');
		const nonce = $form.find('input[name="nonce"]').val();

		setButtonLoading($button, 'Deactivating...');

		$.post(MDS_EXTENSIONS_DATA.ajax_url, {
			action: 'mds_deactivate_license',
			extension_slug: extension_slug,
			nonce: nonce,
		})
			.done(function (response) {
				if (response && response.success) {
					location.reload();
				} else {
					const msg = response && response.data && response.data.message ? response.data.message : 'Deactivation failed.';
					showNotice('error', msg, false);
					resetButton($button, 'Deactivate');
				}
			})
			.fail(function () {
				showNotice('error', 'An error occurred during deactivation.', false);
				resetButton($button, 'Deactivate');
			});
	});

});