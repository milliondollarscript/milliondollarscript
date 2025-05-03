// Million Dollar Script - Admin Logs JS
jQuery(document).ready(function($) {
    var $logViewer = $('#mds-log-viewer');
    var $spinner = $('#mds-log-spinner');
    var $toggleLoggingCheckbox = $('#mds-toggle-logging');
    var $toggleLiveUpdateCheckbox = $('#mds-toggle-live-update');
    var $clearLogButton = $('#mds-clear-log');

    var liveUpdateInterval = null;
    var lastLogSize = 0; // For tracking new entries later

    // Function to show spinner
    function showSpinner() {
        $spinner.addClass('is-active');
    }

    // Function to hide spinner
    function hideSpinner() {
        $spinner.removeClass('is-active');
    }

    // Function to display notices (using WordPress's notice system)
    // type: 'success', 'error', 'warning', 'info'
    function showNotice(message, type) {
        // We might need a dedicated notice area if settings_errors() isn't updated via AJAX
        var noticeHtml = '<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
        // Try prepending to the main wrap, might need adjustment
        $('#mds-logs-page h1').after(noticeHtml);
        // Handle dismissal
        $(document).on('click', '#mds-logs-page .notice-dismiss', function() {
            $(this).closest('.notice').remove();
        });
    }

    // Function to render log entries as HTML
    function renderLogEntries(entries) {
        var html = '';
        if (!entries || entries.length === 0) {
            return ''; // No entries to render
        }

        // Simple list format for now
        html += '<ul class="mds-log-entries">';
        entries.forEach(function(entry) {
            html += '<li class="mds-log-entry mds-log-level-' + (entry.level ? entry.level.toLowerCase() : 'raw') + '">';
            html += '<span class="mds-log-timestamp">[' + (entry.timestamp || 'No timestamp') + ']</span> ';
            html += '<span class="mds-log-level">[' + (entry.level || 'RAW') + ']</span> ';
            html += '<span class="mds-log-message">' + (entry.message || '') + '</span>';
            if (entry.count && entry.count > 1) {
                html += ' <span class="mds-log-count">(x' + entry.count + ')</span>';
            }
            html += '</li>';
        });
        html += '</ul>';
        return html;
    }

    // Fetch initial log content
    function fetchLogEntries(incremental = false) {
        var ajaxData = {
            action: 'mds_fetch_log_entries',
            nonce: mdsLogsData.nonce
        };

        if (incremental) {
            ajaxData.last_size = lastLogSize;
        }

        showSpinner();
        $.post(mdsLogsData.ajax_url, ajaxData, function(response) {
            hideSpinner();
            if (response.success) {
                lastLogSize = response.data.size; // Always update the size

                if (incremental) {
                    if (response.data.entries && response.data.entries.length > 0) {
                        // Append new entries HTML
                        var newHtml = renderLogEntries(response.data.entries);
                        $logViewer.append(newHtml);
                        // Scroll to bottom if live update is active
                        if ($toggleLiveUpdateCheckbox.is(':checked')) {
                            $logViewer.scrollTop($logViewer[0].scrollHeight);
                        }
                    } // else: no new content, do nothing
                } else {
                    // Full fetch: Replace content
                    if (response.data.entries && response.data.entries.length > 0) {
                        var fullHtml = renderLogEntries(response.data.entries);
                        $logViewer.html(fullHtml); // Replace with HTML
                    } else {
                        // Log file is empty or contains no parsable entries
                        $logViewer.html('<p>' + (response.data.message || mdsLogsData.text.log_empty) + '</p>');
                    }
                }
            } else {
                showNotice(response.data.message || mdsLogsData.text.error_occurred, 'error');
                $logViewer.html('<p>' + mdsLogsData.text.error_loading + '</p>');
            }
        }).fail(function() {
            hideSpinner();
            showNotice(mdsLogsData.text.error_occurred, 'error');
            $logViewer.html('<p>' + mdsLogsData.text.error_loading + '</p>');
        });
    }

    // --- Event Listeners ---

    // Toggle Logging Checkbox
    $toggleLoggingCheckbox.on('change', function() {
        var isEnabled = $(this).is(':checked');
        showSpinner();
        $.post(mdsLogsData.ajax_url, {
            action: 'mds_toggle_logging',
            nonce: mdsLogsData.nonce,
            enabled: isEnabled
        }, function(response) {
            hideSpinner();
            if (response.success) {
                showNotice(response.data.message, 'success');
            } else {
                showNotice(response.data.message || mdsLogsData.text.error_occurred, 'error');
                // Revert checkbox state on failure
                $toggleLoggingCheckbox.prop('checked', !isEnabled);
            }
        }).fail(function() {
            hideSpinner();
            showNotice(mdsLogsData.text.error_occurred, 'error');
            $toggleLoggingCheckbox.prop('checked', !isEnabled);
        });
    });

    // Clear Log Button
    $clearLogButton.on('click', function() {
        if (confirm(mdsLogsData.text.confirm_clear)) {
            showSpinner();
            $.post(mdsLogsData.ajax_url, {
                action: 'mds_clear_log',
                nonce: mdsLogsData.nonce
            }, function(response) {
                hideSpinner();
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    $logViewer.html('<p>' + mdsLogsData.text.log_cleared_viewer + '</p>'); // Update viewer
                    lastLogSize = 0; // Reset size tracker
                } else {
                    showNotice(response.data.message || mdsLogsData.text.error_occurred, 'error');
                }
            }).fail(function() {
                hideSpinner();
                showNotice(mdsLogsData.text.error_occurred, 'error');
            });
        }
    });

    // Toggle Live Update Checkbox
    $toggleLiveUpdateCheckbox.on('change', function() {
        if ($(this).is(':checked')) {
            // Start polling
            fetchLogEntries(true); // Fetch immediately
            liveUpdateInterval = setInterval(function() {
                fetchLogEntries(true);
            }, 5000); // Poll every 5 seconds
            showNotice(mdsLogsData.text.live_update_enabled, 'info');
        } else {
            // Stop polling
            if (liveUpdateInterval) {
                clearInterval(liveUpdateInterval);
                liveUpdateInterval = null;
            }
            showNotice(mdsLogsData.text.live_update_disabled, 'info');
        }
    });

    // Initial Load
    fetchLogEntries();
});
