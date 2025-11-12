/*
 * Million Dollar Script Two - Extensions admin (merged canonical)
 * GPL-3.0
 */

;(function ($) {
	const AJAX_URL = (window.MDS_EXTENSIONS_DATA && MDS_EXTENSIONS_DATA.ajax_url)
		? String(MDS_EXTENSIONS_DATA.ajax_url)
		: (typeof window.ajaxurl === 'string' ? window.ajaxurl : '');
	const TEXT = (window.MDS_EXTENSIONS_DATA && MDS_EXTENSIONS_DATA.text) || {};

	$(function () {
		"use strict";

		// Ensure any notices that accidentally render inside the header are relocated
		(function relocateHeaderNotices() {
			try {
				const $anchor = $('#mds-extensions-notices');
				const $headerNotices = $('#mds-extensions-page .mds-extensions-header .notice');
				if ($headerNotices.length) {
					if ($anchor.length) {
						$headerNotices.detach().appendTo($anchor);
					} else {
						const $avail = $('#mds-extensions-page .mds-extensions-container').first();
						if ($avail.length) {
							$headerNotices.detach().insertBefore($avail);
						}
					}
				}
			} catch (e) { /* noop */ }
		})();

		$('.mds-extension-card').each(function () {
			refreshVersionMeta($(this));
		});

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
			// Fallback: place below the entire header banner, right before the Available Extensions section
			const $avail = $('#mds-extensions-page .mds-extensions-container').first();
			if ($avail.length) {
				$notice.insertBefore($avail);
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

	function setUpdateStatus($context, type, message) {
		const $card = $context.hasClass('mds-extension-card') ? $context : $context.closest('.mds-extension-card');
		if (!$card.length) {
			return;
		}

		const $panel = $card.find('.mds-card-update-panel');
		if (!$panel.length) {
			return;
		}

		const variants = ['success', 'warning', 'error', 'info'];
		const normalized = typeof type === 'string' ? type.toLowerCase() : '';
		const variant = variants.includes(normalized) ? normalized : 'info';
		const text = typeof message === 'string' ? message.trim() : '';
		const $existingStatus = $panel.find('.mds-update-status').first();

		if (!text) {
			if ($existingStatus.length) {
				$existingStatus.remove();
			}
			if ($panel.data('has-update') !== 'true' && $panel.children().length === 0) {
				$panel.removeAttr('data-status-variant').removeClass('mds-update-status-visible').empty();
			} else {
				$panel.removeAttr('data-status-variant').removeClass('mds-update-status-visible');
			}
			return;
		}

		const statusHtml = `<div class="mds-update-status is-${variant}" role="status">${escapeHtml(text)}</div>`;

		if ($existingStatus.length) {
			$existingStatus.replaceWith(statusHtml);
		} else if ($panel.data('has-update') === 'true') {
			$panel.prepend(statusHtml);
		} else {
			$panel.html(statusHtml);
		}

		$panel.attr('data-status-variant', variant).addClass('mds-update-status-visible');
	}

function getText(key, fallback) {
	if (TEXT && Object.prototype.hasOwnProperty.call(TEXT, key)) {
		const value = TEXT[key];
		if (typeof value === 'string' && value !== '') {
			return value;
		}
	}
	return fallback;
}

function escapeHtml(value) {
	return $('<div>').text(value == null ? '' : String(value)).html();
}


	function truthyFlag(val) {
		if (typeof val === 'boolean') {
			return val;
		}
		if (typeof val === 'number') {
			return val !== 0;
		}
		if (typeof val === 'string') {
			const normalized = val.trim().toLowerCase();
			return normalized === 'true' || normalized === '1' || normalized === 'yes';
		}
		return !!val;
	}

	function refreshVersionMeta($card) {
		const $meta = $card.find('.mds-card-version-meta');
		if (!$meta.length) {
			return;
		}

		const installedVersion = String($card.data('installed-version') || '').trim();
		const availableVersion = String($card.data('available-version') || '').trim();
		const updateAvailable = truthyFlag($card.data('update-available'));

		const installedValue = installedVersion
			? `v${installedVersion}`
			: getText('version_not_installed', 'Not installed');
		const availableValue = availableVersion
			? `v${availableVersion}`
			: (installedVersion
				? `v${installedVersion}`
				: getText('version_unknown', 'Unknown'));

		$meta.toggleClass('is-outdated', updateAvailable);
		$meta.find('.mds-version-installed .mds-version-value').text(installedValue);
		$meta.find('.mds-version-available .mds-version-value').text(availableValue);
	}

	function pruneEmptyCadenceTabs($pricing) {
		$pricing.find('.mds-plan-tabs').each(function () {
			const $tabs = $(this);
			const surviving = [];
			$tabs.find('.mds-plan-tab').each(function () {
				const $tab = $(this);
				const key = String($tab.data('plan-tab') || '').trim();
				if (!key) {
					return;
				}
				const selector = '.mds-plan-tabpanel[data-plan-panel="' + key + '"]';
				const $panel = $tabs.find(selector);
				const hasPlans = $panel.length && $panel.find('.mds-plan-card').length > 0;
				if (!hasPlans) {
					$panel.remove();
					$tab.remove();
					return;
				}
				surviving.push({ tab: $tab, key: key });
			});
			if (!surviving.length) {
				$tabs.remove();
				return;
			}
			const hasActive = surviving.some(entry => entry.tab.hasClass('is-active'));
			if (!hasActive) {
				const primary = surviving[0];
				primary.tab.addClass('is-active').attr('aria-selected', 'true');
				$tabs.find('.mds-plan-tabpanel').removeClass('is-active');
				if (primary.key) {
					const selector = '.mds-plan-tabpanel[data-plan-panel="' + primary.key + '"]';
					$tabs.find(selector).addClass('is-active');
				}
			}
		});
	}

	function sanitizeCadenceCards($scope) {
		const $cards = $scope && $scope.length ? $scope : $('.mds-extension-card');
		$cards.each(function () {
			const $card = $(this);
			const isPremium = truthyFlag($card.data('isPremium')) || truthyFlag($card.attr('data-is-premium'));
			$card.find('.mds-card-pricing').each(function () {
				const $pricing = $(this);
				if (!isPremium) {
					$pricing.remove();
					return;
				}
				pruneEmptyCadenceTabs($pricing);
				const hasPlanCards = $pricing.find('.mds-plan-card').length > 0;
				if (!hasPlanCards) {
					$pricing.remove();
				} else {
					$pricing.removeClass('mds-card-pricing--empty');
				}
			});
		});
	}

	sanitizeCadenceCards($('.mds-extension-card'));

	function rowHasLocalPurchase($row) {
		return truthyFlag($row.data('purchased'))
			|| truthyFlag($row.data('purchasedLocally'))
			|| truthyFlag($row.data('hasLocalLicense'));
	}


	function resolveRemoveContext($btn) {
		let slug = $btn.attr('data-extension-slug') || $btn.data('extensionSlug') || '';
		let nonce = $btn.attr('data-nonce') || $btn.data('nonce') || '';

		const $form = $btn.closest('.mds-license-form');
		if ($form.length) {
			slug = slug || $form.data('extension-slug');
			nonce = nonce || $form.find('input[name="nonce"]').val();
		}

		const $inline = $btn.closest('.mds-inline-license');
		if ($inline.length) {
			slug = slug || $inline.data('extension-slug');
			nonce = nonce || $inline.data('licenseNonce');
		}

		if (!slug) {
			const $context = $btn.closest('[data-extension-slug]');
			if ($context.length) {
				slug = $context.data('extension-slug') || '';
			}
		}

		return {
			slug: slug ? String(slug) : '',
			nonce: nonce ? String(nonce) : ''
		};
	}

	function setPlanFeedback($container, message = '', type = '') {
		if (!$container || !$container.length) {
			if (message) {
				showNotice(type === 'error' ? 'error' : 'success', message, type !== 'error');
			}
			return;
		}
		$container.removeClass('is-error is-success');
		if (!message) {
			$container.text('');
			return;
		}
		if (type === 'error') {
			$container.addClass('is-error');
		} else if (type === 'success') {
			$container.addClass('is-success');
		}
		$container.text(message);
	}

	function highlightPlanCard($card, enable) {
		// Toggle the hover/focus highlight class on plan cards
		if (!$card || !$card.length) return;
		$card.toggleClass('is-highlighted', !!enable);
	}

	$(document).on('mouseenter focusin', '.mds-plan-card', function () {
		highlightPlanCard($(this), true);
	});

	$(document).on('mouseleave', '.mds-plan-card', function () {
		highlightPlanCard($(this), false);
	});

	$(document).on('focusout', '.mds-plan-card', function (event) {
		const $card = $(this);
		const related = event.relatedTarget;
		if (related && $card.has(related).length > 0) {
			return;
		}
		highlightPlanCard($card, false);
	});

	$(document).on('click', '.mds-plan-tab', function () {
		const $tab = $(this);
		const key = String($tab.data('plan-tab') || '').trim();
		if (!key) {
			return;
		}
		const $wrapper = $tab.closest('.mds-plan-tabs');
		if (!$wrapper.length) {
			return;
		}
		$wrapper.find('.mds-plan-tab').removeClass('is-active').attr('aria-selected', 'false');
		$tab.addClass('is-active').attr('aria-selected', 'true');
		$wrapper.find('.mds-plan-tabpanel').removeClass('is-active');
		$wrapper.find('.mds-plan-tabpanel[data-plan-panel="' + key + '"]').addClass('is-active');
	});

	// Inline license reveal toggle for card layouts
	$(document).on('click', '.mds-card-license-toggle', function (event) {
		event.preventDefault();
		const $toggle = $(this);
		const isExpanded = $toggle.attr('aria-expanded') === 'true';
		const $card = $toggle.closest('.mds-extension-card');
		const $licensePane = $card.find('.mds-card-license').first();
		if (!$licensePane.length) {
			return;
		}
		if (isExpanded) {
			$licensePane.attr('hidden', true).attr('aria-hidden', 'true').addClass('is-collapsed');
		} else {
			$licensePane.removeAttr('hidden').attr('aria-hidden', 'false').removeClass('is-collapsed');
		}
		$toggle.attr('aria-expanded', isExpanded ? 'false' : 'true');
	});

	// Premium extensions UI gating on page load
	(function gatePremiumInstallUI() {
		try {
			const licenseKey = (window.MDS_EXTENSIONS_DATA && MDS_EXTENSIONS_DATA.license_key)
				? String(MDS_EXTENSIONS_DATA.license_key).trim()
				: '';

			const extServerBase = (
				window.MDS_EXTENSIONS_DATA && MDS_EXTENSIONS_DATA.extension_server_url
					? MDS_EXTENSIONS_DATA.extension_server_url
					: 'https://milliondollarscript.com'
			).replace(/\/+$/, '');

			const premiumSelector = '.mds-extension-card[data-is-premium="true"], table.widefat tbody tr[data-is-premium="true"]';
			const $premiumRows = $(premiumSelector).filter(function () {
				return !$(this).attr('data-plugin-file');
			});

			function annotateBuy($row, text) {
				let $target = $row.find('.mds-card-actions');
				if (!$target.length) {
					$target = $row.find('.mds-purchase-buttons');
				}
				if ($target.length && !$row.find('.mds-license-required-note').length) {
					$('<span class="mds-license-required-note" title="License required" style="margin-left:8px;color:#666;">' + (text || 'License required') + '</span>')
						.appendTo($target);
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

			// If we don't have a license key localized, trust the server-rendered row state
			if (!licenseKey) {
				$premiumRows.each(function () {
					const $row = $(this);
					if (rowHasLocalPurchase($row)) {
						enableInstall($row);
						return;
					}
					const licensed = String($row.data('is-licensed') || '').toLowerCase() === 'true';
					if (licensed) {
						enableInstall($row);
					} else {
						disableInstall($row);
					}
				});
				return;
			}

			const rows = $premiumRows.toArray();

			function next(i) {
				if (i >= rows.length) return;
				const $row = $(rows[i]);
				if (rowHasLocalPurchase($row)) {
					enableInstall($row);
					return next(i + 1);
				}
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
			$(premiumSelector).filter(function () {
				return !$(this).attr('data-plugin-file');
			}).each(function () {
				const $row = $(this);
				if (rowHasLocalPurchase($row)) {
					enableInstall($row);
					return;
				}
				const $btn = $row.find('.mds-install-extension');
				if ($btn.length) {
					$btn.prop('disabled', true)
						.addClass('disabled')
						.attr('aria-disabled', 'true')
						.attr('title', 'License required');
				}
				let $target = $row.find('.mds-card-actions');
				if (!$target.length) {
					$target = $row.find('.mds-purchase-buttons');
				}
				if ($target.length && !$row.find('.mds-license-required-note').length) {
					$target.append('<span class="mds-license-required-note" title="License required" style="margin-left:8px;color:#666;">License required</span>');
				}
			});
		}
	})();

	// Handle check for updates button
	$(document).on("click", ".mds-check-updates", function (e) {
		e.preventDefault();

		const $button = $(this);
		const $card = $button.closest(".mds-extension-card");
		if (!$card.length) {
			return;
		}

		const extensionId = $button.data("extension-id") || $card.data("extension-id");
		const currentVersion = $button.data("current-version") || $card.data("version");
		const pluginFile = $button.data("plugin-file") || $card.data("plugin-file");
		const extensionSlug = $button.data("extension-slug") || $card.data("extension-slug");
		const requiresLicense = truthyFlag($card.data('is-premium'));
		const canCheck = truthyFlag($card.data('can-check-updates')) || !requiresLicense;

		if (!canCheck) {
			const message = getText('update_check_requires_license', 'A valid license is required to check for updates.');
			showNotice('warning', message, false);
			setUpdateStatus($card, 'warning', message);
			return;
		}

		if (!extensionId || !pluginFile) {
			const message = getText("update_check_missing_params", "Unable to check updates for this extension.");
			showNotice("error", message, false);
			setUpdateStatus($card, 'error', message);
			return;
		}

		setButtonLoading($button, getText("checking_updates", "Checking..."));

		$.ajax({
			url: AJAX_URL,
			type: "POST",
			data: {
				action: "mds_check_extension_updates",
				nonce: MDS_EXTENSIONS_DATA.nonce,
				extension_id: extensionId,
				current_version: currentVersion || "",
				plugin_file: pluginFile,
				extension_slug: extensionSlug || "",
				requires_license: requiresLicense ? '1' : '',
			},
			dataType: "json",
			success: function (response) {
				if (response && response.success) {
					handleUpdateResponse(response.data || {}, $card, $button);
				} else {
					const message = response && response.data && response.data.message
						? response.data.message
						: getText("update_check_failed", "Failed to check for updates.");
					showNotice("error", message);
					setUpdateStatus($card, 'error', message);
					resetButton($button, getText("check_updates", "Check for Updates"));
				}
			},
			error: function () {
				const message = getText("update_check_error", "An error occurred while checking for updates.");
				showNotice("error", message);
				setUpdateStatus($card, 'error', message);
				resetButton($button, getText("check_updates", "Check for Updates"));
			},
		});
	});

	// Handle install update button
	$(document).on("click", ".mds-install-update", function (e) {
		e.preventDefault();

		const $button = $(this);
		const $card = $button.closest(".mds-extension-card");
		const extensionId = $button.data("extension-id") || ($card.length ? $card.data("extension-id") : "");
		const extensionSlug = $button.data("extension-slug") || ($card.length ? $card.data("extension-slug") : "");
		const downloadUrl = $button.data("download-url");

		if (!downloadUrl || !extensionId) {
			showNotice("error", getText("missing_update_package", "Missing update package information."));
			return;
		}

		if (!confirm(getText("confirm_install_update", "Install this update now?"))) {
			return;
		}

		setButtonLoading($button, getText("updating_extension", "Updating..."));

		$.ajax({
			url: AJAX_URL,
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
				if (response && response.success) {
					showNotice("success", (response.data && response.data.message) || getText("update_install_success", "Update installed successfully."));
					if (response.data && response.data.reload) {
						setTimeout(() => window.location.reload(), 2000);
					} else {
						resetButton($button, getText("update_install_success_short", "Updated"));
						$button.prop("disabled", true);
					}
				} else {
					showNotice("error", (response && response.data && response.data.message) || getText("update_install_failed", "Failed to install update."));
					resetButton($button, getText("update_now", "Update Now"));
				}
			},
			error: function () {
				showNotice("error", getText("update_install_error", "An error occurred while installing the update."));
				resetButton($button, getText("update_now", "Update Now"));
			},
		});
	});

	// Handle install extension button
	$(document).on("click", ".mds-install-extension", function (e) {
		e.preventDefault();

		const $button = $(this);
		let $context = $button.closest('.mds-extension-card');
		if (!$context.length) {
			$context = $button.closest('tr');
		}
		const extensionId = $button.data("extension-id");
		const extensionSlug = $button.data("extension-slug");

		const isPremiumAttr = $context.data("is-premium");
		const isPremium = isPremiumAttr === true || isPremiumAttr === "true";
		const licensedAttr = String($context.data("is-licensed") || '').toLowerCase() === 'true';
		const hasLocalLicense = truthyFlag($context.data('hasLocalLicense'));
		const purchased = truthyFlag($context.data('purchased'))
			|| truthyFlag($context.data('purchasedLocally'));
		const hasSiteLicense = !!(MDS_EXTENSIONS_DATA.license_key && String(MDS_EXTENSIONS_DATA.license_key).trim());
		const hasLicense = licensedAttr || hasLocalLicense || purchased || hasSiteLicense;

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
			url: AJAX_URL,
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
			url: AJAX_URL,
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
	// Handle the update check response
	function handleUpdateResponse(updateInfo, $card, $button) {
		const $panel = $card.find('.mds-card-update-panel');
		if (!$panel.length) {
			resetButton($button, getText('check_updates', 'Check for Updates'));
			return;
		}

		const latestVersion = updateInfo.latest_version || updateInfo.new_version || updateInfo.version || '';
		const downloadUrl = updateInfo.download_url || updateInfo.package_url || '';
		const extensionId = $card.data('extension-id') || '';
		const extensionSlug = $card.data('extension-slug') || '';
		const pluginFile = $card.data('plugin-file') || '';
		const installedVersion = String($card.data('installed-version') || '').trim();

		if (latestVersion) {
			$card.attr('data-available-version', latestVersion);
		}
		if (downloadUrl) {
			$card.attr('data-update-download', downloadUrl);
		} else {
			$card.removeAttr('data-update-download');
		}

		if (updateInfo.update_available) {
			const changelog = updateInfo.changelog ? `<div class="mds-changelog">${updateInfo.changelog}</div>` : '';
			const installButton = downloadUrl
				? `<button class="button button-primary mds-install-update"
					data-download-url="${escapeHtml(downloadUrl)}"
					data-extension-id="${escapeHtml(extensionId)}"
					data-extension-slug="${escapeHtml(extensionSlug)}"
					data-plugin-file="${escapeHtml(pluginFile)}">${getText('update_now', 'Update Now')}</button>`
				: '';
			const html = `
				<div class="mds-update-available">
					<p><strong>${getText('update_version_available', 'Version')} ${escapeHtml(latestVersion)} ${getText('update_is_available', 'is available!')}</strong></p>
					${changelog}
					${installButton ? `<p>${installButton}</p>` : ''}
				</div>
			`;
			$panel.html(html).attr('data-has-update', 'true');
			$card.attr('data-update-available', 'true');
			resetButton($button, getText('check_again', 'Check Again'));
		} else {
			const latestText = getText('latest_version_installed', 'You have the latest version.');
			$panel.empty().attr('data-has-update', 'false');
			setUpdateStatus($card, null, '');
			showNotice('success', latestText, false);
			$card.attr('data-update-available', 'false');
			if (!latestVersion && installedVersion) {
				$card.attr('data-available-version', installedVersion);
			}
			resetButton($button, getText('check_updates', 'Check for Updates'));
		}

		refreshVersionMeta($card);
		$card.trigger('mds-update-checked');
	}

$(document).on('click', '.mds-cancel-auto-renew', function(e){
	e.preventDefault();

	const $button = $(this);
	if ($button.prop('disabled')) {
		return;
	}

	const slug = $button.data('extension-slug');
	if (!slug) {
		showNotice('error', getText('auto_cancel_missing_slug', 'Unable to cancel auto-renew for this extension.'), false);
		return;
	}

	if (!confirm(getText('confirm_cancel_auto', 'Cancel automatic renewal for this subscription?'))) {
		return;
	}

	setButtonLoading($button, getText('canceling_auto', 'Canceling...'));

	$.post(AJAX_URL, { action: 'mds_cancel_subscription', nonce: MDS_EXTENSIONS_DATA.nonce, extension_slug: slug })
		.done(function(resp){
			if (resp && resp.success) {
				showNotice('success', (resp.data && resp.data.message) || getText('auto_cancel_success', 'Auto-renew canceled.'));
				resetButton($button, getText('auto_canceled', 'Auto-renew canceled'));
				$button.prop('disabled', true);
				const $card = $button.closest('.mds-extension-card');
				if ($card.length) {
					$card.attr('data-auto-renewing', 'false');
					$card.attr('data-auto-renew-cancelled', 'true');
				}
			} else {
				showNotice('error', (resp && resp.data && resp.data.message) || getText('auto_cancel_failed', 'Failed to cancel auto-renew.'), false);
				resetButton($button, getText('cancel_auto_renewal', 'Cancel Auto-renewal'));
			}
		})
		.fail(function(){
			showNotice('error', getText('auto_cancel_error', 'Failed to cancel auto-renew.'), false);
			resetButton($button, getText('cancel_auto_renewal', 'Cancel Auto-renewal'));
		});
});

// Handle purchase extension button
$(document).on('click', '.mds-purchase-extension', function (e) {
	e.preventDefault();

	const $button = $(this);
	if ($button.prop('disabled')) {
		return;
	}

	const extensionId = $button.data('extension-id');
	const nonce = $button.data('nonce');
	const plan = String($button.data('plan') || '').trim();
	const priceId = String($button.data('price-id') || '').trim();

	const $card = $button.closest('.mds-plan-card');
	const $feedback = $card.find('.mds-plan-feedback');

		setPlanFeedback($feedback, '', '');

		if (!extensionId || !nonce) {
			setPlanFeedback($feedback, getText('missing_parameters', 'Unable to start checkout. Please refresh and try again.'), 'error');
			return;
		}

		if (!plan) {
			setPlanFeedback($feedback, getText('missing_plan', 'Please choose a plan to continue.'), 'error');
			return;
		}

		setButtonLoading($button, getText('processing_plan', 'Processing…'));

		$.ajax({
			url: AJAX_URL,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'mds_purchase_extension',
				nonce: nonce,
				extension_id: extensionId,
				plan: plan,
				price_id: priceId,
			},
			success: function (resp) {
				if (resp && resp.success && resp.data && resp.data.checkout_url) {
					setPlanFeedback($feedback, getText('redirecting_checkout', 'Redirecting to checkout…'), 'success');
					window.location.href = resp.data.checkout_url;
					return;
				}
				const fallback = getText('purchase_failed', 'Failed to initiate purchase.');
				const message = (resp && resp.data && resp.data.message) ? resp.data.message : fallback;
				setPlanFeedback($feedback, message, 'error');
				resetButton($button);
				highlightPlanCard($card, false);
			},
			error: function () {
				setPlanFeedback($feedback, getText('purchase_network_error', 'An error occurred while contacting the store. Please try again.'), 'error');
				resetButton($button);
				highlightPlanCard($card, false);
			},
		});
	});

	// Auto-claim license after purchase success
	(function autoClaimAfterPurchase() {
		try {
			const p = (window.MDS_EXTENSIONS_DATA && MDS_EXTENSIONS_DATA.purchase) || {};

			// Handle session expiration
			if (p.status === 'session_expired') {
				const message = p.message || 'Your claim session has expired. Please claim your license manually using the license key from your email.';
				showNotice('warning', message, false);
				return;
			}

			// Handle successful purchase with claim token (token from PHP, NOT from URL)
			if (!p || p.status !== 'success') return;
			const claimToken = String(p.claim_token || '').trim();
			const extSlug = String(p.ext_slug || '').trim();
			if (!claimToken || !extSlug) return;
			const siteId = String(MDS_EXTENSIONS_DATA.site_id || '').trim();
			if (!siteId) return;

			let attempts = 0;
			const maxAttempts = 12; // ~60s total with 5s intervals
			const attemptClaim = () => {
				attempts++;
				if (attempts === 1) {
					showNotice('success', 'Finalizing your purchase (claiming license)...', false);
				}
				$.ajax({
					url: AJAX_URL,
					type: 'POST',
					data: {
						action: 'mds_claim_license',
						nonce: MDS_EXTENSIONS_DATA.nonce,
						extension_slug: extSlug,
						claim_token: claimToken,
						site_id: siteId,
					},
					dataType: 'json',
					success: function (resp) {
						if (resp && resp.success) {
							showNotice('success', 'License claimed. You can now install the extension.');
							setTimeout(() => window.location.replace(window.location.href.replace(/([?&])(purchase|session)=[^&]*/g,'$1').replace(/[?&]$/,'')), 1500);
						} else {
							if (attempts < maxAttempts) {
								setTimeout(attemptClaim, 5000);
							} else {
								const msg = resp && resp.data && resp.data.message ? resp.data.message : 'License claim failed.';
								showNotice('error', msg, false);
							}
						}
					},
					error: function () {
						if (attempts < maxAttempts) {
							setTimeout(attemptClaim, 5000);
						} else {
							showNotice('error', 'An error occurred while claiming the license.', false);
						}
					}
				});
			};
			attemptClaim();
		} catch (e) { /* noop */ }
	})();

	// License activation handler
	$(document).on('click', '.mds-activate-license', function (e) {
		e.preventDefault();
		const $form = $(this).closest('.mds-license-form');
		const $button = $(this);
		const extension_slug = $form.data('extension-slug');
		let license_key = $form.find('input[name=\"license_key\"]').val();
		const nonce = $form.find('input[name="nonce"]').val();

		const proceed = () => {
			setButtonLoading($button, 'Activating...');
			$.post(AJAX_URL, {
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
		};
		if (!license_key) {
			$.post(AJAX_URL, {
				action: 'mds_get_license_plaintext',
				nonce: MDS_EXTENSIONS_DATA.nonce,
				extension_slug: extension_slug,
			}).done(function (resp) {
				if (resp && resp.success && resp.data && resp.data.license_key) {
					license_key = resp.data.license_key;
				}
			}).always(proceed);
		} else {
			proceed();
		}
	});

	// License deactivation handler
	$(document).on('click', '.mds-deactivate-license', function (e) {
		e.preventDefault();
		const $form = $(this).closest('.mds-license-form');
		const $button = $(this);
		const extension_slug = $form.data('extension-slug');
		const nonce = $form.find('input[name="nonce"]').val();

		setButtonLoading($button, 'Deactivating...');

		$.post(AJAX_URL, {
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

	$(document).on('click', '.mds-remove-license, .mds-inline-license-remove', function (e) {
		e.preventDefault();
		const $btn = $(this);

		if (!confirm(getText('confirm_remove_license', 'Remove the stored license for this extension?'))) {
			return;
		}

		const context = resolveRemoveContext($btn);
		const extensionSlug = context.slug;
		const nonce = context.nonce;

		if (!extensionSlug || !nonce) {
			showNotice('error', getText('license_context_missing', 'Unable to locate license context.'), false);
			return;
		}

		const isLinkStyle = $btn.hasClass('button-link') && !$btn.hasClass('button');
		const originalHtml = $btn.html();
		if (isLinkStyle) {
			$btn.prop('disabled', true).text(getText('removing_license', 'Removing…'));
		} else {
			setButtonLoading($btn, getText('removing_license', 'Removing…'));
		}

		$.post(AJAX_URL, {
			action: 'mds_delete_license',
			extension_slug: extensionSlug,
			nonce: nonce,
		})
			.done(function (resp) {
				if (resp && resp.success) {
					showNotice('success', (resp.data && resp.data.message) || getText('license_removed', 'License removed.'));
					setTimeout(() => window.location.reload(), 800);
				} else {
					showNotice('error', (resp && resp.data && resp.data.message) || getText('license_remove_failed', 'Failed to remove license.'), false);
				}
			})
			.fail(function () {
				showNotice('error', getText('license_remove_failed', 'Failed to remove license.'), false);
			})
			.always(function () {
				if (isLinkStyle) {
					$btn.prop('disabled', false).html(originalHtml);
				} else {
					resetButton($btn);
				}
			});
	});

// Inline license activation in Available Extensions
$(document).on('click', '.mds-inline-license-activate', function (e) {
    e.preventDefault();
    const $btn = $(this);
    const $wrap = $btn.closest('.mds-inline-license');
    const slug = $wrap.data('extension-slug');
    const key = String($wrap.find('.mds-inline-license-key').val() || '').trim();
    if (!slug) { showNotice('error', 'Missing extension identifier.', false); return; }
    if (!key) { showNotice('warning', 'Please enter a license key.', false); return; }
    setButtonLoading($btn, getText('saving_license', 'Applying...'));
    $.post(AJAX_URL, {
        action: 'mds_available_activate_license',
        nonce: MDS_EXTENSIONS_DATA.nonce,
        extension_slug: slug,
        license_key: key
    }).done(function(resp){
        if (resp && resp.success) {
            showNotice('success', (resp.data && resp.data.message) || getText('license_saved', 'License saved.'));
            setTimeout(() => window.location.reload(), 800);
        } else {
            showNotice('error', (resp && resp.data && resp.data.message) || getText('license_save_failed', 'Failed to save license.'), false);
            resetButton($btn, getText('apply_license', 'Apply license'));
        }
    }).fail(function(){
        showNotice('error', getText('license_save_failed', 'Failed to save license.'), false);
        resetButton($btn, getText('apply_license', 'Apply license'));
    });
});

// Inline license: eye toggle using dashicons (hidden <-> visibility) placed in actions row
	$(document).on('click', '.mds-inline-license-visibility', function () {
		const $btn = $(this);
		const $wrap = $btn.closest('.mds-inline-license');
		const $input = $wrap.find('.mds-inline-license-key, input[name="license_key"]');
		const $icon = $btn.find('.dashicons');
		const extSlug = $wrap.data('extension-slug');
		if ($input.attr('type') === 'password') {
			// If installed license input is empty, attempt to fetch plaintext for convenience
			if (!$input.val()) {
				$.post(AJAX_URL, {
					action: 'mds_get_license_plaintext',
					nonce: MDS_EXTENSIONS_DATA.nonce,
					extension_slug: extSlug,
				}).done(function (resp) {
					if (resp && resp.success && resp.data && resp.data.license_key) {
						$input.val(resp.data.license_key);
					}
				}).always(function () {
					$input.attr('type', 'text');
					$btn.attr('aria-label', 'Hide');
					$icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
				});
			} else {
				$input.attr('type', 'text');
				$btn.attr('aria-label', 'Hide');
				$icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
			}
		} else {
			$input.attr('type', 'password');
			$btn.attr('aria-label', 'Show');
			$icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
		}
	});

	// Inject per-row Manage License toggles and hide forms by default
	(function initLicenseManageToggles() {
		try {
			$('.mds-license-cell').each(function (idx) {
				const $cell = $(this);
				if ($cell.data('manage-init')) return;
				const $content = $cell.find('.mds-inline-license, .mds-license-form').first();
				if (!$content.length) return;
				const contentId = 'mds-license-content-' + idx;
				if (!$content.parent().hasClass('mds-license-manage-content')) {
					$content.wrap('<div class="mds-license-manage-content" id="'+contentId+'" aria-hidden="true"></div>');
				}
				if (!$cell.find('.mds-manage-license-toggle').length) {
					const $toggle = $('<button type="button" class="button-link mds-manage-license-toggle" aria-expanded="false" aria-controls="'+contentId+'"></button>');
					$toggle.text(getText('manage_license', 'Manage license'));
					$cell.prepend($toggle);
				}
				$cell.attr('data-manage-init', '1');
			});
		} catch (e) { /* noop */ }
	})();

	// Manage license link toggle handler
	$(document).on('click', '.mds-manage-license-toggle', function (e) {
		e.preventDefault();
		const $btn = $(this);
		const target = $btn.attr('aria-controls');
		const $content = $('#'+target);
		const open = $btn.attr('aria-expanded') === 'true';
		if (open) {
			$content.removeClass('is-open').attr('aria-hidden', 'true');
			$btn.attr('aria-expanded', 'false').text(getText('manage_license', 'Manage license'));
		} else {
			$content.addClass('is-open').attr('aria-hidden', 'false');
			$btn.attr('aria-expanded', 'true').text(getText('hide_license', 'Hide'));
		}
	});

	// Inline license: activate
	$(document).on('click', '.mds-inline-license-activate', function () {
		const $wrap = $(this).closest('.mds-inline-license');
		const $input = $wrap.find('.mds-inline-license-key');
		const extSlug = $wrap.data('extension-slug');
		const key = String($input.val() || '').trim();
		if (!key) {
			showNotice('warning', 'Enter a license key first.', false);
			return;
		}
		const $btn = $(this);
		setButtonLoading($btn, 'Validating...');
		$.post(AJAX_URL, {
			action: 'mds_available_activate_license',
			nonce: MDS_EXTENSIONS_DATA.nonce,
			extension_slug: extSlug,
			license_key: key,
		}).done(function (resp) {
			if (resp && resp.success) {
				showNotice('success', resp.data && resp.data.message ? resp.data.message : 'License updated.');
				setTimeout(() => window.location.reload(), 1000);
			} else {
				showNotice('error', (resp && resp.data && resp.data.message) || 'Failed to save license.', false);
			}
		}).fail(function () {
			showNotice('error', 'Failed to save license.', false);
		}).always(function () {
			resetButton($btn, 'Activate');
		});
	});
	});
})(window.jQuery);
