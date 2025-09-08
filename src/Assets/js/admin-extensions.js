/*
 * Million Dollar Script Two - Extensions admin (merged canonical)
 * GPL-3.0
 */

jQuery(document).ready(function ($) {
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

			// If we don't have a license key localized, trust the server-rendered row state
			if (!licenseKey) {
				$premiumRows.each(function () {
					const $row = $(this);
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
		const licensedAttr = String($row.data("is-licensed") || '').toLowerCase() === 'true';
		const hasLicense = licensedAttr || !!(MDS_EXTENSIONS_DATA.license_key && String(MDS_EXTENSIONS_DATA.license_key).trim());

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

// Simple modal factory for plan selection

// Manage subscription modal
function ensureManageModal() {
    if ($('#mds-manage-modal').length) return;
    const html = `
        <div id="mds-manage-modal" class="mds-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="mds-manage-title">
            <div class="mds-modal">
                <header><span id="mds-manage-title">Manage subscription</span></header>
                <div class="mds-modal-body">
                    <div class="mds-manage-content">
                        <p class="mds-manage-loading">Loading…</p>
                        <div class="mds-manage-error" style="color:#b91c1c; display:none;"></div>
                        <div class="mds-manage-details" style="display:none;">
                            <div class="mds-current-plan"></div>
                            <fieldset class="mds-plan-change" style="margin-top:10px;">
                                <legend>Change plan</legend>
                                <div class="mds-plan-options"></div>
                            </fieldset>
                        </div>
                    </div>
                </div>
                <footer>
                    <button type="button" class="button mds-modal-cancel">Close</button>
                    <button type="button" class="button mds-cancel-subscription">Cancel auto-renew</button>
                    <button type="button" class="button button-primary mds-apply-plan" disabled>Apply</button>
                </footer>
            </div>
        </div>`;
    $('body').append(html);
}

$(document).on('click', '.mds-manage-subscription', function(){
    const slug = $(this).data('extension-slug');
    ensureManageModal();
    const $overlay = $('#mds-manage-modal');
    const $loading = $overlay.find('.mds-manage-loading');
    const $error = $overlay.find('.mds-manage-error');
    const $details = $overlay.find('.mds-manage-details');
    const $plans = $overlay.find('.mds-plan-options');
    const $apply = $overlay.find('.mds-apply-plan').prop('disabled', true).data('extension-slug', slug);
    const $cancelBtn = $overlay.find('.mds-cancel-subscription').data('extension-slug', slug);

    $error.hide().text('');
    $details.hide();
    $loading.show();
    $overlay.css('display','flex');

    $.post(MDS_EXTENSIONS_DATA.ajax_url, { action: 'mds_get_subscription', nonce: MDS_EXTENSIONS_DATA.nonce, extension_slug: slug })
        .done(function(resp){
            if (!resp || !resp.success || !resp.data) {
                $error.text((resp && resp.data && resp.data.message) || 'Could not load subscription.').show();
                return;
            }
            const sub = resp.data;
            const current = sub.currentPlan || sub.plan || '';
            const currentDesc = sub.currentPlanDescription || '';
            const options = sub.availablePlans || [];

            // Human-friendly plan description
            let descText = currentDesc;
            if (!descText) {
                const map = { one_time: 'One-time (no renewals)', monthly: 'Monthly subscription', yearly: 'Yearly subscription' };
                descText = map[current] || current || '';
                if ((current === 'monthly' || current === 'yearly') && sub.renewsAt) {
                    try { descText += ' (renews ' + new Date(sub.renewsAt).toLocaleDateString() + ')'; } catch {}
                }
            }
            $overlay.find('.mds-current-plan').text(descText ? ('Current plan: ' + descText) : '');

            if (options.length) {
                const labelMap = { one_time: 'One-time', monthly: 'Monthly subscription', yearly: 'Yearly subscription' };
                const radios = options.map(o => {
                    const isCurrent = o === current;
                    const label = labelMap[o] || o;
                    const badge = isCurrent ? ' <em class=\"mds-current-plan-indicator\">(current)</em>' : '';
                    const checked = isCurrent ? ' checked' : '';
                    const disabled = isCurrent ? ' disabled aria-checked=\"true\"' : '';
                    return `<label style=\"display:flex;align-items:center;gap:8px;margin:6px 0;\"><input type=\"radio\" name=\"mds-manage-plan\" value=\"${o}\"${checked}${disabled}><span>${label}${badge}</span></label>`;
                }).join('');
                $plans.html(radios);
                $plans.off('change','input[name=\"mds-manage-plan\"]').on('change','input[name=\"mds-manage-plan\"]', function(){
                    const chosen = $(this).val();
                    $apply.prop('disabled', !chosen || chosen === current).data('plan', chosen);
                });
            } else {
                $plans.html('<p>No alternative plans available.</p>');
            }
            $details.show();
        })
        .fail(function(){ $error.text('Could not load subscription.').show(); })
        .always(function(){ $loading.hide(); });
});

$(document).on('click', '#mds-manage-modal .mds-modal-cancel', function(){ $('#mds-manage-modal').hide(); });

$(document).on('click', '.mds-apply-plan', function(){
    const $btn = $(this);
    const slug = $btn.data('extension-slug');
    const plan = $btn.data('plan');
    if (!slug || !plan) return;
    $btn.prop('disabled', true).text('Applying…');
    $.post(MDS_EXTENSIONS_DATA.ajax_url, { action: 'mds_change_subscription_plan', nonce: MDS_EXTENSIONS_DATA.nonce, extension_slug: slug, plan: plan })
        .done(function(resp){
            if (resp && resp.success) {
                showNotice('success', (resp.data && resp.data.message) || 'Plan changed.');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showNotice('error', (resp && resp.data && resp.data.message) || 'Failed to change plan.', false);
            }
        })
        .fail(function(){ showNotice('error', 'Failed to change plan.', false); })
        .always(function(){ $btn.prop('disabled', false).text('Apply'); });
});

$(document).on('click', '.mds-cancel-subscription', function(){
    if (!confirm('Cancel automatic renewal for this subscription?')) return;
    const $btn = $(this).prop('disabled', true).text('Canceling…');
    const slug = $btn.data('extension-slug');
    $.post(MDS_EXTENSIONS_DATA.ajax_url, { action: 'mds_cancel_subscription', nonce: MDS_EXTENSIONS_DATA.nonce, extension_slug: slug })
        .done(function(resp){
            if (resp && resp.success) {
                showNotice('success', (resp.data && resp.data.message) || 'Auto-renew canceled.');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showNotice('error', (resp && resp.data && resp.data.message) || 'Failed to cancel.', false);
            }
        })
        .fail(function(){ showNotice('error', 'Failed to cancel.', false); })
        .always(function(){ $btn.prop('disabled', false).text('Cancel auto-renew'); });
});
	function ensurePurchaseModal() {
		if ($('#mds-purchase-modal').length) return;
		const html = `
			<div id="mds-purchase-modal" class="mds-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="mds-purchase-title">
				<div class="mds-modal">
					<header><span id="mds-purchase-title">Select a plan</span></header>
					<div class="mds-modal-body">
						<div class="mds-plan-options"></div>
						<div class="mds-plan-error" style="color:#b91c1c; display:none;"></div>
					</div>
					<footer>
						<button type="button" class="button mds-modal-cancel">Cancel</button>
						<button type="button" class="button button-primary mds-modal-continue" disabled>Continue</button>
					</footer>
				</div>
			</div>`;
		$('body').append(html);
	}
	function openPurchaseModal(extensionId, nonce, initialPlan) {
		ensurePurchaseModal();
		const $overlay = $('#mds-purchase-modal');
		const $body = $overlay.find('.mds-plan-options');
		const $error = $overlay.find('.mds-plan-error');
		const $continue = $overlay.find('.mds-modal-continue');
		$error.hide().text('');
		$continue.prop('disabled', true).data('extension-id', extensionId).data('nonce', nonce).data('plan', '');
		$body.html('<p>Loading plans…</p>');
		$overlay.css('display','flex');
		// Fetch extension details for purchase links
		$.ajax({
			url: MDS_EXTENSIONS_DATA.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: { action: 'mds_fetch_extension', nonce: MDS_EXTENSIONS_DATA.nonce, extension_id: extensionId },
			success: function(resp) {
				if (!resp || !resp.success || !resp.data || !resp.data.extension) {
					$error.text('Could not load plans.').show();
					return;
				}
				const ext = resp.data.extension;
				const links = (ext.purchase_links) || {};
				const options = [];
				if (links.one_time) options.push({key:'one_time', label:'One-time', url:links.one_time});
				if (links.monthly) options.push({key:'monthly', label:'Monthly', url:links.monthly});
				if (links.yearly) options.push({key:'yearly', label:'Yearly', url:links.yearly});
				if (links.default && options.length === 0) options.push({key:'default', label:'Purchase', url:links.default});
				if (options.length === 0) {
					$body.html('<p>No plans available for purchase.</p>');
					return;
				}
				const radios = options.map(o => `
					<label style="display:flex;align-items:center;gap:8px;margin:6px 0;">
						<input type="radio" name="mds-plan" value="${o.key}">
						<span>${o.label}</span>
					</label>`).join('');
				$body.html(radios);
				if (initialPlan) {
					$body.find(`input[value="${initialPlan}"]`).prop('checked', true);
					$continue.prop('disabled', false).data('plan', initialPlan);
				}
				$body.off('change','input[name="mds-plan"]').on('change','input[name="mds-plan"]', function() {
					const plan = $(this).val();
					$continue.prop('disabled', !plan).data('plan', plan);
				});
			},
			error: function(){ $error.text('Could not load plans.').show(); }
		});
	}
	$(document).on('click', '#mds-purchase-modal .mds-modal-cancel', function(){ $('#mds-purchase-modal').hide(); });
	$(document).on('click', '#mds-purchase-modal .mds-modal-continue', function(){
		const $btn = $(this);
		const extId = $btn.data('extension-id');
		const nonce = $btn.data('nonce');
		const plan  = $btn.data('plan');
		if (!extId || !nonce) return;
		$btn.prop('disabled', true).text('Continuing…');
		$.ajax({
			url: MDS_EXTENSIONS_DATA.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: { action: 'mds_purchase_extension', nonce: nonce, extension_id: extId, plan: plan },
			success: function(r){
				if (r && r.success && r.data && r.data.checkout_url) {
					window.location.href = r.data.checkout_url;
				} else {
					$('.mds-plan-error').text((r && r.data && r.data.message) || 'Failed to initiate purchase.').show();
					$btn.prop('disabled', false).text('Continue');
				}
			},
			error: function(){
				$('.mds-plan-error').text('Failed to initiate purchase.').show();
				$btn.prop('disabled', false).text('Continue');
			}
		});
	});

// Handle purchase extension button
	$(document).on("click", ".mds-purchase-extension", function (e) {
		e.preventDefault();

		const $button = $(this);
		const extensionId = $button.data("extension-id");
		const nonce = $button.data("nonce");

		// Open plan selection modal instead of immediate redirect
		openPurchaseModal(extensionId, nonce, $button.data('plan') || null);
	});

	// Auto-claim license after purchase success
	(function autoClaimAfterPurchase() {
		try {
			const p = (window.MDS_EXTENSIONS_DATA && MDS_EXTENSIONS_DATA.purchase) || {};
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
					url: MDS_EXTENSIONS_DATA.ajax_url,
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
							setTimeout(() => window.location.replace(window.location.href.replace(/([?&])purchase=success[^&]*/,'$1')), 1500);
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
		};
		if (!license_key) {
			$.post(MDS_EXTENSIONS_DATA.ajax_url, {
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

// Inline license activation in Available Extensions
$(document).on('click', '.mds-inline-license-activate', function (e) {
    e.preventDefault();
    const $btn = $(this);
    const $wrap = $btn.closest('.mds-inline-license');
    const slug = $wrap.data('extension-slug');
    const key = String($wrap.find('.mds-inline-license-key').val() || '').trim();
    if (!slug) { showNotice('error', 'Missing extension identifier.', false); return; }
    if (!key) { showNotice('warning', 'Please enter a license key.', false); return; }
    setButtonLoading($btn, 'Activating...');
    $.post(MDS_EXTENSIONS_DATA.ajax_url, {
        action: 'mds_available_activate_license',
        nonce: MDS_EXTENSIONS_DATA.nonce,
        extension_slug: slug,
        license_key: key
    }).done(function(resp){
        if (resp && resp.success) {
            showNotice('success', (resp.data && resp.data.message) || 'License activated.');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showNotice('error', (resp && resp.data && resp.data.message) || 'Activation failed.', false);
            resetButton($btn, 'Activate');
        }
    }).fail(function(){
        showNotice('error', 'Activation failed.', false);
        resetButton($btn, 'Activate');
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
				$.post(MDS_EXTENSIONS_DATA.ajax_url, {
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
					const $toggle = $('<button type="button" class="button-link mds-manage-license-toggle" aria-expanded="false" aria-controls="'+contentId+'">'+ (MDS_EXTENSIONS_DATA.text?.manage_license || 'Manage License') +'</button>');
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
			$btn.attr('aria-expanded', 'false').text(MDS_EXTENSIONS_DATA.text?.manage_license || 'Manage License');
		} else {
			$content.addClass('is-open').attr('aria-hidden', 'false');
			$btn.attr('aria-expanded', 'true').text(MDS_EXTENSIONS_DATA.text?.hide_license || 'Hide');
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
		$.post(MDS_EXTENSIONS_DATA.ajax_url, {
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
