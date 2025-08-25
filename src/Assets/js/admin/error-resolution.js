/**
 * MDS Error Resolution JavaScript
 * Handles error scanning, display, and resolution functionality
 */

(function($) {
    'use strict';

    const ErrorResolution = {
        modal: null,
        currentPageId: null,

        init: function() {
            this.bindEvents();
            this.setupModal();
            this.bindPageListErrorHelpers();
        },

        bindEvents: function() {
            // Bind to error resolution buttons (works on both compatibility page and page management)
            $('#mds-scan-errors').on('click.error-resolution', this.scanForErrors.bind(this));
            $('#mds-repair-all-issues').on('click.error-resolution', this.fixAllErrors.bind(this));
            $('#mds-fix-all-errors').on('click.error-resolution', this.fixAllErrors.bind(this));
            
            // Modal events
            $(document).on('click', '#mds-modal-close, #mds-modal-cancel', this.closeModal.bind(this));
            $(document).on('click', '#mds-modal-fix', this.applyModalFix.bind(this));
            
            // Error item actions
            $(document).on('click', '.mds-error-view-details', this.viewErrorDetails.bind(this));
            $(document).on('click', '.mds-error-fix-single', this.fixSingleError.bind(this));
            
            // Close modal on overlay click
            $(document).on('click', '.mds-modal-overlay', function(e) {
                if (e.target === this) {
                    ErrorResolution.closeModal();
                }
            });
        },

        setupModal: function() {
            this.modal = $('#mds-error-modal');
        },

        bindPageListErrorHelpers: function() {
            // Bind error help icons in page list
            $(document).on('click', '.mds-error-help', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const postId = $(this).data('post-id');
                if (postId) {
                    ErrorResolution.showErrorDetails(postId);
                }
            });
        },

        scanForErrors: function(e) {
            e.preventDefault();
            
            const $button = $(e.target).closest('button');
            const $results = $('#mds-error-results');
            
            // Show progress modal instead of inline notice
            if (window.mdsPageManagement && window.mdsPageManagement.showProgress) {
                window.mdsPageManagement.showProgress('Scanning for Page Errors', 'Analyzing pages for validation errors...');
            }
            $results.hide();
            
            // Set button to loading state with spinning icon
            this.setButtonLoading($button, true);
            $button.prop('disabled', true);
            
            // Make AJAX call using WordPress admin-ajax.php
            $.ajax({
                url: window.ajaxurl || '/wp-admin/admin-ajax.php',
                method: 'POST',
                data: {
                    action: 'mds_scan_errors',
                    nonce: window.mdsPageManagement ? window.mdsPageManagement.nonce : ''
                }
            }).done(function(response) {
                if (response.success && response.data) {
                    // Extract the errors array from the response data
                    const errorPages = response.data.errors || response.data;
                    ErrorResolution.displayErrorResults(errorPages);
                } else {
                    // Use modal notification instead of inline error
                    let errorMessage = 'Scan failed';
                    let errorDetails = [];
                    
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage += ': ' + response.data;
                        } else if (response.data.message) {
                            errorMessage += ': ' + response.data.message;
                            
                            // Add debug information if available
                            if (response.data.debug_info) {
                                if (response.data.debug_info.error) {
                                    errorDetails.push('Error: ' + response.data.debug_info.error);
                                }
                                if (response.data.debug_info.file && response.data.debug_info.line) {
                                    errorDetails.push('Location: ' + response.data.debug_info.file + ':' + response.data.debug_info.line);
                                }
                            }
                        } else if (response.data.error) {
                            errorMessage += ': ' + response.data.error;
                        } else {
                            errorMessage += ': Unable to complete scan';
                        }
                    } else {
                        errorMessage += ': No specific error information available';
                    }
                    
                    // Use NotificationModal if available, otherwise fallback to showError
                    if (window.NotificationModal) {
                        window.NotificationModal.show('error', 'Scan Error', errorMessage, errorDetails.length > 0 ? errorDetails : null);
                    } else {
                        ErrorResolution.showError(errorMessage);
                    }
                }
            }).fail(function(xhr, status, error) {
                // Provide more detailed error information for AJAX failures
                let errorMessage = 'Unable to connect to server';
                let errorDetails = [];
                
                if (xhr.status) {
                    errorDetails.push('HTTP Status: ' + xhr.status);
                }
                if (status && status !== 'error') {
                    errorDetails.push('Status: ' + status);
                }
                if (error) {
                    errorDetails.push('Error: ' + error);
                }
                
                // Use NotificationModal if available, otherwise fallback to showError
                if (window.NotificationModal) {
                    window.NotificationModal.show('error', 'Connection Error', errorMessage, errorDetails.length > 0 ? errorDetails : null);
                } else {
                    ErrorResolution.showError(errorMessage);
                }
            }).always(function() {
                if (window.mdsPageManagement && window.mdsPageManagement.hideProgress) {
                    window.mdsPageManagement.hideProgress();
                }
                // Reset button to normal state
                ErrorResolution.setButtonLoading($button, false);
                $button.prop('disabled', false);
            });
        },

        displayErrorResults: function(errorPages) {
            const $results = $('#mds-error-results');
            const $errorList = $('.mds-error-list');
            const $fixAllButton = $('#mds-fix-all-errors');
            
            // Ensure errorPages is an array
            if (!errorPages || !Array.isArray(errorPages) || errorPages.length === 0) {
                $errorList.html('<div class="mds-no-errors"><p>No pages with errors found!</p></div>');
                $fixAllButton.hide();
                // Show success modal instead of inline notice
                if (window.mdsPageManagement && window.mdsPageManagement.showNotification) {
                    window.mdsPageManagement.showNotification('success', 'Scan Complete', 'No pages with errors found! All pages appear to be functioning correctly.');
                }
            } else {
                let html = '';
                let autoFixableCount = 0;
                
                errorPages.forEach(function(page) {
                    const hasAutoFix = page.suggested_fixes && page.suggested_fixes.some(fix => fix.auto_fixable);
                    if (hasAutoFix) autoFixableCount++;
                    
                    const errorClass = hasAutoFix ? 'auto-fixable' : 'manual-fix';
                    const errorSummary = page.validation_errors ? page.validation_errors.slice(0, 2).join(', ') : 'Unknown error';
                    
                    html += `
                        <div class="mds-error-item ${errorClass}" data-page-id="${page.page_id}">
                            <div class="mds-error-item-content">
                                <div class="mds-error-item-title">${page.page_title} (ID: ${page.page_id})</div>
                                <div class="mds-error-item-details">
                                    <strong>Page Type:</strong> ${page.page_type || 'Unknown'}<br>
                                    <strong>Errors:</strong> ${errorSummary}
                                </div>
                            </div>
                            <div class="mds-error-item-actions">
                                <button type="button" class="button button-secondary mds-error-view-details" 
                                        data-page-id="${page.page_id}">
                                    View Details
                                </button>
                                ${hasAutoFix ? `
                                <button type="button" class="button button-primary mds-error-fix-single" 
                                        data-page-id="${page.page_id}">
                                    Fix Now
                                </button>` : ''}
                            </div>
                        </div>
                    `;
                });
                
                $errorList.html(html);
                
                if (autoFixableCount > 0) {
                    $fixAllButton.show().find('span:last').text(`Fix All Auto-Fixable Errors (${autoFixableCount})`);
                } else {
                    $fixAllButton.hide();
                }
            }
            
            $results.show();
        },

        showErrorDetails: function(pageId) {
            this.currentPageId = pageId;
            
            // Load error details via AJAX
            $.ajax({
                url: `/wp-json/mds/v1/pages/${pageId}/errors`,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                }
            }).done(function(response) {
                if (response.success && response.data) {
                    ErrorResolution.populateModal(response.data);
                    ErrorResolution.openModal();
                } else {
                    ErrorResolution.showError('Failed to load error details: ' + (response.message || 'Unknown error'));
                }
            }).fail(function(xhr) {
                ErrorResolution.showError('Error loading page details: ' + xhr.statusText);
            });
        },

        populateModal: function(pageData) {
            const $modalTitle = $('#mds-modal-title');
            const $modalBody = $('#mds-modal-body');
            const $modalFix = $('#mds-modal-fix');
            
            $modalTitle.text(`Error Details: ${pageData.page_title}`);
            
            let html = `
                <div class="mds-error-detail">
                    <div class="mds-error-detail-label">Page Information</div>
                    <div class="mds-error-detail-value">
                        <strong>ID:</strong> ${pageData.page_id}<br>
                        <strong>Type:</strong> ${pageData.page_type || 'Unknown'}<br>
                        <strong>Status:</strong> ${pageData.status}<br>
                        <strong>Confidence Score:</strong> ${pageData.confidence_score}<br>
                        <strong>Last Validated:</strong> ${pageData.last_validated || 'Never'}
                    </div>
                </div>
            `;
            
            if (pageData.validation_errors && pageData.validation_errors.length > 0) {
                html += `
                    <div class="mds-error-detail">
                        <div class="mds-error-detail-label">Validation Errors</div>
                        <div class="mds-error-detail-value">
                            ${pageData.validation_errors.map(error => `• ${error}`).join('<br>')}
                        </div>
                    </div>
                `;
            }
            
            if (pageData.suggested_fixes && pageData.suggested_fixes.length > 0) {
                html += '<div class="mds-suggested-fixes">';
                html += '<div class="mds-error-detail-label">Suggested Fixes</div>';
                
                pageData.suggested_fixes.forEach(function(fix) {
                    const fixClass = fix.auto_fixable ? 'mds-auto-fixable' : '';
                    html += `
                        <div class="mds-suggested-fix ${fixClass}">
                            <div class="mds-suggested-fix-title">
                                ${fix.title}
                                ${fix.auto_fixable ? ' (Auto-fixable)' : ' (Manual)'}
                            </div>
                            <div class="mds-suggested-fix-description">${fix.description}</div>
                        </div>
                    `;
                });
                
                html += '</div>';
                
                // Show fix button if there are auto-fixable errors
                const hasAutoFix = pageData.suggested_fixes.some(fix => fix.auto_fixable);
                if (hasAutoFix) {
                    $modalFix.show().data('page-id', pageData.page_id);
                } else {
                    $modalFix.hide();
                }
            } else {
                $modalFix.hide();
            }
            
            $modalBody.html(html);
        },

        viewErrorDetails: function(e) {
            e.preventDefault();
            const pageId = $(e.target).data('page-id');
            this.showErrorDetails(pageId);
        },

        fixSingleError: function(e) {
            e.preventDefault();
            const pageId = $(e.target).data('page-id');
            const $button = $(e.target);
            
            this.applyFix(pageId, $button);
        },

        applyModalFix: function(e) {
            e.preventDefault();
            const pageId = $('#mds-modal-fix').data('page-id');
            const $button = $('#mds-modal-fix');
            
            this.applyFix(pageId, $button, true);
        },

        applyFix: function(pageId, $button, closeModal = false) {
            const originalText = $button.text();
            
            // Set button to loading state with spinning icon
            this.setButtonLoading($button, true);
            $button.prop('disabled', true).text('Applying fixes...');
            
            $.ajax({
                url: `/wp-json/mds/v1/pages/${pageId}/fix`,
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                }
            }).done(function(response) {
                if (response.success && response.data) {
                    const fixes = response.data.fixes_applied;
                    const newStatus = response.data.new_status;
                    
                    if (fixes && fixes.length > 0) {
                        ErrorResolution.showSuccess(`Applied ${fixes.length} fix(es). New status: ${newStatus}`);
                        
                        // Update the error item in the list
                        const $errorItem = $(`.mds-error-item[data-page-id="${pageId}"]`);
                        if (newStatus === 'active') {
                            $errorItem.fadeOut();
                        } else {
                            // Refresh the item by reloading details
                            ErrorResolution.refreshErrorItem(pageId);
                        }
                        
                        if (closeModal) {
                            setTimeout(() => ErrorResolution.closeModal(), 1500);
                        }
                    } else {
                        ErrorResolution.showError('No fixes were applied. The page may already be fixed.');
                    }
                } else {
                    ErrorResolution.showError('Failed to apply fixes: ' + (response.message || 'Unknown error'));
                }
            }).fail(function(xhr) {
                ErrorResolution.showError('Error applying fixes: ' + xhr.statusText);
            }).always(function() {
                // Reset button to normal state
                ErrorResolution.setButtonLoading($button, false);
                $button.prop('disabled', false).text(originalText);
            });
        },

        fixAllErrors: function(e) {
            e.preventDefault();
            
            const $button = $(e.target).closest('button');
            const $errorItems = $('.mds-error-item.auto-fixable');
            const pageIds = $errorItems.map(function() {
                return $(this).data('page-id');
            }).get();
            
            if (pageIds.length === 0) {
                this.showError('No auto-fixable errors found.');
                return;
            }
            
            // Set button to loading state with spinning icon
            this.setButtonLoading($button, true);
            $button.prop('disabled', true).text('Fixing all errors...');
            
            // Fix errors one by one
            this.fixErrorsBatch(pageIds, 0, $button);
        },

        fixErrorsBatch: function(pageIds, index, $button) {
            if (index >= pageIds.length) {
                // Reset button to normal state
                this.setButtonLoading($button, false);
                $button.prop('disabled', false).text('Fix All Auto-Fixable Errors');
                this.showSuccess('Completed fixing all auto-fixable errors!');
                // Rescan for remaining errors
                setTimeout(() => $('#mds-scan-errors').click(), 1000);
                return;
            }
            
            const pageId = pageIds[index];
            const $errorItem = $(`.mds-error-item[data-page-id="${pageId}"]`);
            
            $errorItem.css('opacity', '0.5');
            
            $.ajax({
                url: `/wp-json/mds/v1/pages/${pageId}/fix`,
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                }
            }).done(function(response) {
                if (response.success && response.data.new_status === 'active') {
                    $errorItem.fadeOut();
                } else {
                    $errorItem.css('opacity', '1');
                }
            }).fail(function() {
                $errorItem.css('opacity', '1');
            }).always(function() {
                // Continue with next item
                setTimeout(() => {
                    ErrorResolution.fixErrorsBatch(pageIds, index + 1, $button);
                }, 300);
            });
        },

        refreshErrorItem: function(pageId) {
            // Reload error details for a specific item
            $.ajax({
                url: `/wp-json/mds/v1/pages/${pageId}/errors`,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                }
            }).done(function(response) {
                if (response.success && response.data) {
                    // Update the error item display
                    const $errorItem = $(`.mds-error-item[data-page-id="${pageId}"]`);
                    const page = response.data;
                    const hasAutoFix = page.suggested_fixes && page.suggested_fixes.some(fix => fix.auto_fixable);
                    const errorClass = hasAutoFix ? 'auto-fixable' : 'manual-fix';
                    const errorSummary = page.validation_errors ? page.validation_errors.slice(0, 2).join(', ') : 'Unknown error';
                    
                    $errorItem.removeClass('auto-fixable manual-fix').addClass(errorClass);
                    $errorItem.find('.mds-error-item-details').html(`
                        <strong>Page Type:</strong> ${page.page_type || 'Unknown'}<br>
                        <strong>Errors:</strong> ${errorSummary}
                    `);
                }
            });
        },

        openModal: function() {
            this.modal.show();
            $('body').addClass('modal-open');
        },

        closeModal: function() {
            this.modal.hide();
            $('body').removeClass('modal-open');
            this.currentPageId = null;
        },

        showError: function(message) {
            this.showNotice(message, 'error');
        },

        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        showNotice: function(message, type = 'info') {
            // Use modal system if available, otherwise fallback to inline notice
            if (window.mdsPageManagement && window.mdsPageManagement.showNotification) {
                const title = type === 'error' ? 'Error' : type === 'success' ? 'Success' : 'Information';
                window.mdsPageManagement.showNotification(type, title, message);
            } else {
                // Fallback to inline notice
                const $notice = $(`
                    <div class="notice notice-${type} is-dismissible" style="margin: 10px 0;">
                        <p>${message}</p>
                        <button type="button" class="notice-dismiss">
                            <span class="screen-reader-text">Dismiss this notice.</span>
                        </button>
                    </div>
                `);
                
                $('.wrap h1').after($notice);
                
                // Auto-dismiss after 5 seconds
                setTimeout(() => $notice.fadeOut(), 5000);
                
                // Handle manual dismiss
                $notice.on('click', '.notice-dismiss', function() {
                    $notice.fadeOut();
                });
            }
        },

        /**
         * Set button loading state with spinning icon
         *
         * @param {jQuery} $button - The button element
         * @param {boolean} loading - Whether to set loading state or restore normal state
         */
        setButtonLoading: function($button, loading) {
            const $icon = $button.find('.dashicons');
            
            if ($icon.length === 0) {
                return;
            }
            
            if (loading) {
                // Store original icon class for restoration
                if (!$icon.data('original-class')) {
                    $icon.data('original-class', $icon.attr('class'));
                }
                // Change to spinning update icon
                $icon.removeClass().addClass('dashicons dashicons-update-alt').css({
                    'animation': 'rotation 2s infinite linear'
                });
            } else {
                // Restore original icon
                const originalClass = $icon.data('original-class');
                if (originalClass) {
                    $icon.removeClass().attr('class', originalClass).css('animation', '');
                    $icon.removeData('original-class');
                } else {
                    // Fallback: remove spinner and animation if no original class stored
                    $icon.removeClass('dashicons-update-alt').css('animation', '');
                    if (!$icon.hasClass('dashicons')) {
                        $icon.addClass('dashicons');
                    }
                    // Add default icon if none present
                    if (!$icon.attr('class').includes('dashicons-') || $icon.hasClass('dashicons-update-alt')) {
                        $icon.addClass('dashicons-admin-tools');
                    }
                }
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ErrorResolution.init();
    });

    // Export for global access
    window.MDSErrorResolution = ErrorResolution;

})(jQuery);