/**
 * MDS Page Management Interface JavaScript
 */

(function($) {
    'use strict';

    // Main page management object
    const MDSPageManagement = {
        
        // Configuration
        config: {
            ajaxUrl: mds_page_management.ajax_url,
            nonce: mds_page_management.nonce,
            strings: mds_page_management.strings
        },

        // Initialize the interface
        init: function() {
            this.bindEvents();
            this.initializeFilters();
            this.initializeModals();
            this.loadInitialData();
        },

        // Bind event handlers
        bindEvents: function() {
            // Quick action buttons (using correct IDs from PHP)
            $('#mds-scan-all-pages').on('click', this.scanAllPages.bind(this));
            $('#mds-repair-all-issues').on('click', this.repairAllPages.bind(this));
            $('#mds-export-page-data').on('click', this.exportData.bind(this));
            $('#mds-refresh-list').on('click', this.refreshData.bind(this));
            
            // Check if methods exist before binding (for debugging)
            if (typeof this.bulkUpdateImplementations === 'function') {
                $('#mds-bulk-update-implementations').on('click', this.bulkUpdateImplementations.bind(this));
            }
            if (typeof this.validateAllPages === 'function') {
                $('#mds-validate-all-pages').on('click', this.validateAllPages.bind(this));
            }
            
            // Error scanning buttons
            $('#mds-scan-errors').on('click', this.scanForErrors.bind(this));
            $('#mds-fix-all-errors').on('click', this.fixAllErrors.bind(this));
            $('#mds-reset-page-statuses').on('click', this.resetPageStatuses.bind(this));
            
            // Status help links
            $(document).on('click', '.mds-status-help-link', this.showPageErrorHelp.bind(this));

            // Filter controls
            $('#mds-filter-form').on('submit', this.applyFilters.bind(this));
            $('.mds-clear-filters').on('click', this.clearFilters.bind(this));

            // List table actions
            $(document).on('click', '.mds-view-details', this.viewPageDetails.bind(this));
            $(document).on('click', '.mds-scan-page', this.scanSinglePage.bind(this));
            $(document).on('click', '.mds-repair-page', this.repairSinglePage.bind(this));
            $(document).on('click', '.mds-configure-page', this.configurePage.bind(this));

            // Bulk actions
            $('#doaction, #doaction2').on('click', this.handleBulkAction.bind(this));

            // Modal controls
            $(document).on('click', '.mds-modal-close', this.closeModal.bind(this));
            $(document).on('click', '.mds-modal', function(e) {
                if (e.target === this) {
                    MDSPageManagement.closeModal();
                }
            });

            // Auto-refresh
            if (mds_page_management.auto_refresh) {
                setInterval(this.refreshStats.bind(this), 30000); // Every 30 seconds
            }
        },

        // Initialize filter functionality
        initializeFilters: function() {
            // Auto-submit on filter change
            $('#mds-page-type-filter, #mds-status-filter, #mds-implementation-filter').on('change', function() {
                $('#mds-filter-form').submit();
            });

            // Search with debounce
            let searchTimeout;
            $('#mds-search-input').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    $('#mds-filter-form').submit();
                }, 500);
            });
        },

        // Initialize modal functionality
        initializeModals: function() {
            // jQuery UI dialogs will be initialized when needed
        },

        // Load initial data
        loadInitialData: function() {
            this.refreshStats();
        },

        // Scan all pages
        scanAllPages: function(e) {
            e.preventDefault();
            
            if (!confirm(this.config.strings.confirm_scan_all)) {
                return;
            }

            this.showProgress('Scanning all pages...', 0);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_scan_all_pages',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.showNotice('success', response.data.message);
                        MDSPageManagement.refreshData();
                    } else {
                        MDSPageManagement.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while scanning pages.');
                },
                complete: function() {
                    MDSPageManagement.hideProgress();
                }
            });
        },

        // Repair all pages
        repairAllPages: function(e) {
            e.preventDefault();
            
            if (!confirm(this.config.strings.confirm_repair_all)) {
                return;
            }

            this.showProgress('Repairing pages...', 0);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_repair_all_pages',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.showNotice('success', response.data.message);
                        MDSPageManagement.refreshData();
                    } else {
                        MDSPageManagement.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while repairing pages.');
                },
                complete: function() {
                    MDSPageManagement.hideProgress();
                }
            });
        },

        // Export data
        exportData: function(e) {
            e.preventDefault();
            
            const format = $(e.target).data('format') || 'json';
            const includeContent = $(e.target).data('include-content') || false;
            
            // Create a temporary form to handle file download
            const form = $('<form>')
                .attr('method', 'POST')
                .attr('action', this.config.ajaxUrl)
                .css('display', 'none');
            
            form.append($('<input>').attr('name', 'action').val('mds_export_page_data'));
            form.append($('<input>').attr('name', 'nonce').val(this.config.nonce));
            form.append($('<input>').attr('name', 'format').val(format));
            form.append($('<input>').attr('name', 'include_content').val(includeContent ? '1' : ''));
            
            $('body').append(form);
            form.submit();
            form.remove();
            
            this.showNotice('success', 'Export started. Download should begin shortly.');
        },

        // Refresh data
        refreshData: function(e) {
            if (e) e.preventDefault();
            
            this.showProgress('Refreshing data...', 0);
            
            // Refresh the page list
            window.location.reload();
        },

        // Bulk update implementations
        bulkUpdateImplementations: function(e) {
            e.preventDefault();
            
            // TODO: Implement bulk implementation update functionality
            this.showNotice('info', 'Bulk Update Implementations feature is coming soon! This will allow you to convert multiple pages between shortcode and block implementations at once.');
        },

        // Validate all pages
        validateAllPages: function(e) {
            e.preventDefault();
            
            // TODO: Implement validation functionality
            this.showNotice('info', 'Validate All Pages feature is coming soon! This will check all pages for proper MDS content and functionality.');
        },

        // Refresh stats only
        refreshStats: function() {
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_get_page_stats',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.updateStats(response.data);
                    }
                }
            });
        },

        // Update statistics display
        updateStats: function(stats) {
            $('.mds-stat-card.total .mds-stat-number').text(stats.total || 0);
            $('.mds-stat-card.active .mds-stat-number').text(stats.active || 0);
            $('.mds-stat-card.needs-attention .mds-stat-number').text(stats.needs_attention || 0);
            $('.mds-stat-card.auto-detected .mds-stat-number').text(stats.auto_detected || 0);
        },

        // Apply filters
        applyFilters: function(e) {
            e.preventDefault();
            
            const formData = $(e.target).serialize();
            const currentUrl = new URL(window.location);
            
            // Update URL parameters
            const params = new URLSearchParams(formData);
            params.forEach((value, key) => {
                if (value) {
                    currentUrl.searchParams.set(key, value);
                } else {
                    currentUrl.searchParams.delete(key);
                }
            });
            
            // Navigate to filtered URL
            window.location.href = currentUrl.toString();
        },

        // Clear filters
        clearFilters: function(e) {
            e.preventDefault();
            
            // Clear form fields
            $('#mds-filter-form')[0].reset();
            
            // Navigate to unfiltered URL
            const currentUrl = new URL(window.location);
            currentUrl.search = '';
            window.location.href = currentUrl.toString();
        },

        // View page details
        viewPageDetails: function(e) {
            e.preventDefault();
            
            const pageId = $(e.target).closest('[data-page-id]').data('page-id');
            
            this.showProgress('Loading page details...', 0);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_get_page_details',
                    page_id: pageId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.showPageDetailsModal(response.data);
                    } else {
                        MDSPageManagement.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while loading page details.');
                },
                complete: function() {
                    MDSPageManagement.hideProgress();
                }
            });
        },

        // Scan single page
        scanSinglePage: function(e) {
            e.preventDefault();
            
            const pageId = $(e.target).closest('[data-page-id]').data('page-id');
            const $button = $(e.target).closest('button');
            
            $button.prop('disabled', true).html('<span class="mds-spinner"></span> Scanning...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_scan_single_page',
                    page_id: pageId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.showNotice('success', response.data.message);
                        // Refresh the row
                        window.location.reload();
                    } else {
                        MDSPageManagement.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while scanning the page.');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span>');
                }
            });
        },

        // Repair single page
        repairSinglePage: function(e) {
            e.preventDefault();
            
            const pageId = $(e.target).closest('[data-page-id]').data('page-id');
            const $button = $(e.target).closest('button');
            
            if (!confirm(this.config.strings.confirm_repair_page)) {
                return;
            }
            
            $button.prop('disabled', true).html('<span class="mds-spinner"></span> Repairing...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_repair_single_page',
                    page_id: pageId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.showNotice('success', response.data.message);
                        // Refresh the row
                        window.location.reload();
                    } else {
                        MDSPageManagement.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while repairing the page.');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools"></span>');
                }
            });
        },

        // Configure page
        configurePage: function(e) {
            e.preventDefault();
            
            const pageId = $(e.target).closest('[data-page-id]').data('page-id');
            
            this.showProgress('Loading configuration...', 0);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_get_page_config',
                    page_id: pageId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.showConfigurationModal(response.data);
                    } else {
                        MDSPageManagement.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while loading configuration.');
                },
                complete: function() {
                    MDSPageManagement.hideProgress();
                }
            });
        },

        // Handle bulk actions
        handleBulkAction: function(e) {
            e.preventDefault();
            
            const action = $(e.target).siblings('select').val();
            const selectedPages = $('input[name="page_ids[]"]:checked').map(function() {
                return this.value;
            }).get();
            
            if (!action || selectedPages.length === 0) {
                this.showNotice('warning', 'Please select pages and an action.');
                return;
            }
            
            if (!confirm(`Are you sure you want to ${action} ${selectedPages.length} page(s)?`)) {
                return;
            }
            
            this.showProgress(`Processing ${action}...`, 0);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_bulk_action',
                    bulk_action: action,
                    page_ids: selectedPages,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.showNotice('success', response.data.message);
                        window.location.reload();
                    } else {
                        MDSPageManagement.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while processing the bulk action.');
                },
                complete: function() {
                    MDSPageManagement.hideProgress();
                }
            });
        },

        // Show page details modal
        showPageDetailsModal: function(data) {
            // Use the server-rendered HTML instead of creating new HTML
            $('#mds-page-details-content').html(data.html);
            
            // Initialize as jQuery UI dialog
            $('#mds-page-details-modal').dialog({
                modal: true,
                width: 800,
                maxHeight: $(window).height() * 0.8,
                resizable: true,
                draggable: true,
                close: function() {
                    $('#mds-page-details-content').empty();
                }
            });
            
            // Bind fix error buttons in the modal
            $('.mds-fix-error-btn').off('click').on('click', function() {
                const pageId = $(this).data('page-id');
                const error = $(this).data('error');
                
                if (!confirm('Are you sure you want to fix this error?')) {
                    return;
                }
                
                const $button = $(this);
                $button.prop('disabled', true).text('Fixing...');
                
                $.ajax({
                    url: MDSPageManagement.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'mds_fix_single_page_error',
                        page_id: pageId,
                        error: JSON.stringify(error),
                        nonce: MDSPageManagement.config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            MDSPageManagement.showNotice('success', 'Error fixed successfully');
                            $button.closest('.mds-error-item').fadeOut();
                            // Refresh the page list to update status
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        } else {
                            MDSPageManagement.showNotice('error', 'Fix failed: ' + response.data);
                        }
                    },
                    error: function() {
                        MDSPageManagement.showNotice('error', 'An error occurred while applying the fix.');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Fix This Error');
                    }
                });
            });
        },

        // Show configuration modal
        showConfigurationModal: function(data) {
            const modalContent = `
                <form id="mds-page-config-form" data-original-implementation="${data.content_type}">
                    <input type="hidden" name="page_id" value="${data.page_id}">
                    <table class="form-table">
                        <tr>
                            <th><label for="page_type">Page Type</label></th>
                            <td>
                                <select name="page_type" id="page_type">
                                    ${this.getPageTypeOptions(data.page_type)}
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="implementation_type">Implementation</label></th>
                            <td>
                                <select name="implementation_type" id="implementation_type">
                                    ${this.getImplementationOptions(data.content_type)}
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="auto_scan">Auto Scan</label></th>
                            <td>
                                <input type="checkbox" name="auto_scan" id="auto_scan" ${data.auto_scan ? 'checked' : ''}>
                                <label for="auto_scan">Automatically scan this page for changes</label>
                            </td>
                        </tr>
                    </table>
                </form>
            `;
            
            $('#mds-page-config-modal').html(modalContent);
            
            // Initialize as jQuery UI dialog
            $('#mds-page-config-modal').dialog({
                modal: true,
                title: 'Configure Page: ' + data.title,
                width: 600,
                maxHeight: $(window).height() * 0.8,
                resizable: true,
                draggable: true,
                buttons: {
                    'Cancel': function() {
                        $(this).dialog('close');
                    },
                    'Save Configuration': function() {
                        MDSPageManagement.savePageConfig();
                    }
                },
                close: function() {
                    $('#mds-page-config-modal').empty();
                }
            });
        },

        // Get page type options HTML
        getPageTypeOptions: function(selected) {
            const types = {
                'grid': 'Pixel Grid',
                'order': 'Order Page',
                'write-ad': 'Write Advertisement',
                'confirm-order': 'Order Confirmation',
                'payment': 'Payment Processing',
                'manage': 'Manage Ads',
                'thank-you': 'Thank You',
                'list': 'Advertiser List',
                'upload': 'File Upload',
                'no-orders': 'No Orders'
            };
            
            let options = '';
            for (const [value, label] of Object.entries(types)) {
                options += `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`;
            }
            return options;
        },

        // Get implementation options HTML
        getImplementationOptions: function(selected) {
            const types = {
                'shortcode': 'Shortcode',
                'block': 'Block'
            };
            
            let options = '';
            for (const [value, label] of Object.entries(types)) {
                options += `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`;
            }
            return options;
        },

        // Save page configuration
        savePageConfig: function() {
            const formData = $('#mds-page-config-form').serialize();
            const originalImplementation = $('#mds-page-config-form').data('original-implementation');
            const newImplementation = $('#implementation_type').val();
            
            // Check if implementation type is changing
            if (originalImplementation && originalImplementation !== newImplementation) {
                if (!confirm('Changing the implementation type will convert the page content. Are you sure you want to continue?')) {
                    return;
                }
            }
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData + '&action=mds_save_page_config&nonce=' + this.config.nonce + '&convert_implementation=1',
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.showNotice('success', response.data.message);
                        $('#mds-page-config-modal').dialog('close');
                        window.location.reload();
                    } else {
                        MDSPageManagement.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while saving configuration.');
                }
            });
        },

        // Close modal
        closeModal: function() {
            if ($('#mds-page-details-modal').hasClass('ui-dialog-content')) {
                $('#mds-page-details-modal').dialog('close');
            }
            if ($('#mds-page-config-modal').hasClass('ui-dialog-content')) {
                $('#mds-page-config-modal').dialog('close');
            }
        },

        // Show progress indicator
        showProgress: function(message, percentage = 0) {
            if (!$('#mds-progress-indicator').length) {
                $('body').append(`
                    <div id="mds-progress-indicator" class="mds-modal">
                        <div class="mds-modal-content">
                            <div class="mds-modal-body">
                                <div class="mds-loading">
                                    <div class="mds-spinner"></div>
                                    <span id="mds-progress-message">${message}</span>
                                </div>
                                <div class="mds-progress">
                                    <div class="mds-progress-bar" id="mds-progress-bar" style="width: ${Math.max(percentage, 10)}%">
                                        ${Math.round(percentage)}%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
            }
            
            const displayPercentage = Math.max(percentage, 10); // Ensure minimum width for visibility
            $('#mds-progress-message').text(message);
            $('#mds-progress-bar').css('width', displayPercentage + '%').text(Math.round(percentage) + '%');
            $('#mds-progress-indicator').show();
        },

        // Update progress
        updateProgress: function(message, percentage) {
            const displayPercentage = Math.max(percentage, 10); // Ensure minimum width for visibility
            $('#mds-progress-message').text(message);
            $('#mds-progress-bar').css('width', displayPercentage + '%').text(Math.round(percentage) + '%');
        },

        // Hide progress indicator
        hideProgress: function() {
            $('#mds-progress-indicator').hide();
        },

        // Scan for errors
        scanForErrors: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $errorResults = $('#mds-error-results');
            const $errorProgress = $('#mds-error-progress');
            const $progressText = $('#mds-error-progress-text');
            const $fixButton = $('#mds-fix-all-errors');
            
            $button.prop('disabled', true);
            $errorProgress.show();
            $progressText.text('Scanning for errors...');
            $errorResults.hide();
            $fixButton.hide();

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_scan_errors',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.displayErrorResults(response.data);
                        if (response.data.auto_fixable > 0) {
                            $fixButton.show().text(`Fix ${response.data.auto_fixable} Auto-Fixable Errors`);
                        }
                    } else {
                        MDSPageManagement.showNotice('error', 'Error scanning failed: ' + response.data);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while scanning for errors.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $errorProgress.hide();
                }
            });
        },

        // Fix all errors
        fixAllErrors: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to fix all auto-fixable errors? This will modify page content.')) {
                return;
            }
            
            const $button = $(e.target);
            const $errorProgress = $('#mds-error-progress');
            const $progressText = $('#mds-error-progress-text');
            
            $button.prop('disabled', true);
            $errorProgress.show();
            $progressText.text('Fixing errors...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_fix_all_errors',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.showNotice('success', response.data.message);
                        // Re-scan to update the error list
                        $('#mds-scan-errors').trigger('click');
                        MDSPageManagement.refreshStats();
                    } else {
                        MDSPageManagement.showNotice('error', 'Error fixing failed: ' + response.data);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while fixing errors.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $errorProgress.hide();
                }
            });
        },

        // Reset page statuses
        resetPageStatuses: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to reset all page statuses? This will re-validate all pages and update their statuses based on current validation rules.')) {
                return;
            }
            
            const $button = $(e.target);
            const $errorProgress = $('#mds-error-progress');
            const $progressText = $('#mds-error-progress-text');
            
            $button.prop('disabled', true);
            $errorProgress.show();
            $progressText.text('Resetting page statuses...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_reset_page_statuses',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.showNotice('success', response.data.message);
                        // Refresh the page to show updated statuses
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        MDSPageManagement.showNotice('error', 'Status reset failed: ' + response.data);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while resetting page statuses.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $errorProgress.hide();
                }
            });
        },

        // Display error results
        displayErrorResults: function(data) {
            const $errorResults = $('#mds-error-results');
            const $errorList = $errorResults.find('.mds-error-list');
            
            if (data.total_errors === 0) {
                $errorList.html('<div class="mds-no-errors"><p>No errors found! All pages appear to be functioning correctly.</p></div>');
            } else {
                let errorHtml = '';
                data.errors.forEach(function(error) {
                    const fixableClass = error.auto_fixable ? 'auto-fixable' : 'manual-fix';
                    const severityClass = error.severity === 'high' ? 'critical' : '';
                    
                    errorHtml += `
                        <div class="mds-error-item ${fixableClass} ${severityClass}" data-page-id="${error.page_id}">
                            <div class="mds-error-item-content">
                                <div class="mds-error-item-title">
                                    <span class="dashicons dashicons-warning"></span>
                                    ${error.page_title} - ${error.message}
                                </div>
                                <div class="mds-error-item-details">
                                    ${error.description}
                                </div>
                                <div class="mds-error-item-meta">
                                    <span class="error-type">Type: ${error.type}</span>
                                    <span class="error-severity">Severity: ${error.severity}</span>
                                    <span class="error-fixable">${error.auto_fixable ? 'Auto-fixable' : 'Manual fix required'}</span>
                                </div>
                            </div>
                            <div class="mds-error-item-actions">
                                <button type="button" class="button button-small mds-view-error-details" data-error='${JSON.stringify(error)}'>
                                    Details
                                </button>
                                ${error.auto_fixable ? '<button type="button" class="button button-primary button-small mds-fix-single-error" data-error=\'' + JSON.stringify(error) + '\'>Fix</button>' : ''}
                            </div>
                        </div>
                    `;
                });
                $errorList.html(errorHtml);
            }
            
            $errorResults.show();
            
            // Bind error detail and fix buttons
            $('.mds-view-error-details').off('click').on('click', this.showErrorDetails.bind(this));
            $('.mds-fix-single-error').off('click').on('click', this.fixSingleError.bind(this));
        },

        // Show error details in modal
        showErrorDetails: function(e) {
            const error = JSON.parse($(e.target).attr('data-error'));
            
            let detailsHtml = `
                <div class="mds-error-detail">
                    <div class="mds-error-detail-label">Page:</div>
                    <div class="mds-error-detail-value">${error.page_title} (ID: ${error.page_id})</div>
                </div>
                <div class="mds-error-detail">
                    <div class="mds-error-detail-label">Error Type:</div>
                    <div class="mds-error-detail-value">${error.type}</div>
                </div>
                <div class="mds-error-detail">
                    <div class="mds-error-detail-label">Severity:</div>
                    <div class="mds-error-detail-value">${error.severity}</div>
                </div>
                <div class="mds-error-detail">
                    <div class="mds-error-detail-label">Description:</div>
                    <div class="mds-error-detail-value">${error.description}</div>
                </div>
            `;
            
            if (error.suggested_fix) {
                detailsHtml += `
                    <div class="mds-suggested-fixes">
                        <h4>Suggested Fix:</h4>
                        <div class="mds-suggested-fix ${error.auto_fixable ? 'mds-auto-fixable' : ''}">
                            <div class="mds-suggested-fix-title">${error.auto_fixable ? 'Automatic Fix Available' : 'Manual Fix Required'}</div>
                            <div class="mds-suggested-fix-description">
                                <code>${error.suggested_fix}</code>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Show in existing modal or create one
            if ($('#mds-error-modal').length) {
                $('#mds-modal-title').text('Error Details');
                $('#mds-modal-body').html(detailsHtml);
                $('#mds-modal-fix').toggle(error.auto_fixable).attr('data-error', JSON.stringify(error));
                $('#mds-error-modal').show();
            } else {
                // Fallback alert if modal not available
                alert('Error Details:\n\nPage: ' + error.page_title + '\nType: ' + error.type + '\nDescription: ' + error.description);
            }
        },

        // Fix single error
        fixSingleError: function(e) {
            const error = JSON.parse($(e.target).attr('data-error'));
            const $button = $(e.target);
            
            if (!confirm(`Are you sure you want to fix this error on "${error.page_title}"?`)) {
                return;
            }
            
            $button.prop('disabled', true).text('Fixing...');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_fix_single_error',
                    error: JSON.stringify(error),
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.showNotice('success', 'Error fixed successfully');
                        // Remove the error from the list
                        $button.closest('.mds-error-item').fadeOut();
                        MDSPageManagement.refreshStats();
                    } else {
                        MDSPageManagement.showNotice('error', 'Fix failed: ' + response.data);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while applying the fix.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Fix');
                }
            });
        },

        // Show page error help
        showPageErrorHelp: function(e) {
            e.preventDefault();
            
            const pageId = $(e.target).closest('[data-page-id]').data('page-id') || 
                          $(e.target).data('page-id');
            
            if (!pageId) {
                this.showNotice('error', 'Unable to identify page for error scanning');
                return;
            }
            
            // First scan this specific page for errors
            this.showProgress('Scanning page for specific errors...', 0);
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_scan_page_errors',
                    page_id: pageId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.errors && response.data.errors.length > 0) {
                            MDSPageManagement.showPageErrorModal(response.data, pageId);
                        } else {
                            MDSPageManagement.showNotice('info', 'No specific errors found for this page. It may have resolved automatically.');
                        }
                    } else {
                        MDSPageManagement.showNotice('error', 'Error scanning failed: ' + response.data);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while scanning the page.');
                },
                complete: function() {
                    MDSPageManagement.hideProgress();
                }
            });
        },

        // Show page-specific error modal
        showPageErrorModal: function(data, pageId) {
            const errors = data.errors;
            const pageName = data.page_title || 'Page ID ' + pageId;
            
            let modalContent = `
                <div class="mds-page-error-help">
                    <h3>Errors found for: ${pageName}</h3>
                    <p>The following issues were detected on this page:</p>
                    <div class="mds-page-errors-list">
            `;
            
            errors.forEach(function(error) {
                const fixableIcon = error.auto_fixable ? 
                    '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>' : 
                    '<span class="dashicons dashicons-warning" style="color: #ffb900;"></span>';
                
                modalContent += `
                    <div class="mds-page-error-item ${error.auto_fixable ? 'auto-fixable' : 'manual-fix'}">
                        <div class="mds-error-header">
                            ${fixableIcon}
                            <strong>${error.message}</strong>
                            <span class="mds-error-severity mds-severity-${error.severity}">${error.severity}</span>
                        </div>
                        <div class="mds-error-description">${error.description}</div>
                        ${error.suggested_fix ? `<div class="mds-error-fix"><strong>Suggested Fix:</strong> <code>${error.suggested_fix}</code></div>` : ''}
                        ${error.auto_fixable ? 
                            `<button type="button" class="button button-primary button-small mds-fix-page-error" data-page-id="${pageId}" data-error='${JSON.stringify(error)}'>Apply Fix</button>` : 
                            '<p><em>This error requires manual intervention.</em></p>'
                        }
                    </div>
                `;
            });
            
            modalContent += `
                    </div>
                    <div class="mds-error-help-actions">
                        <button type="button" class="button button-secondary mds-rescan-page" data-page-id="${pageId}">Re-scan Page</button>
                        <button type="button" class="button mds-view-all-errors">View All Page Errors</button>
                    </div>
                </div>
            `;
            
            // Use jQuery UI dialog if available, otherwise fallback
            if ($.fn.dialog) {
                if ($('#mds-page-error-help-modal').length === 0) {
                    $('body').append('<div id="mds-page-error-help-modal" style="display: none;"></div>');
                }
                
                $('#mds-page-error-help-modal').html(modalContent);
                $('#mds-page-error-help-modal').dialog({
                    title: 'Page Error Details & Solutions',
                    modal: true,
                    width: 700,
                    maxHeight: $(window).height() * 0.8,
                    resizable: true,
                    draggable: true,
                    close: function() {
                        $(this).empty();
                    }
                });
                
                // Bind action buttons
                $('.mds-fix-page-error').on('click', this.fixSinglePageError.bind(this));
                $('.mds-rescan-page').on('click', function() {
                    $('#mds-page-error-help-modal').dialog('close');
                    $(`.mds-status-help-link[data-page-id="${pageId}"]`).trigger('click');
                });
                $('.mds-view-all-errors').on('click', function() {
                    $('#mds-page-error-help-modal').dialog('close');
                    $('#mds-scan-errors').trigger('click');
                    // Scroll to error section
                    setTimeout(function() {
                        $('html, body').animate({
                            scrollTop: $('#mds-error-results').offset().top - 50
                        }, 500);
                    }, 500);
                });
            } else {
                // Fallback for if jQuery UI dialog is not available
                alert('Page Errors Found:\n\n' + errors.map(e => `${e.message}: ${e.description}`).join('\n\n'));
            }
        },

        // Fix single page error
        fixSinglePageError: function(e) {
            const pageId = $(e.target).data('page-id');
            const error = JSON.parse($(e.target).attr('data-error'));
            const $button = $(e.target);
            
            if (!confirm(`Fix this error: "${error.message}"?`)) {
                return;
            }
            
            $button.prop('disabled', true).text('Fixing...');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_fix_single_page_error',
                    page_id: pageId,
                    error: JSON.stringify(error),
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.showNotice('success', 'Error fixed successfully');
                        $button.closest('.mds-page-error-item').fadeOut();
                        MDSPageManagement.refreshStats();
                        // Refresh the page list to update status
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        MDSPageManagement.showNotice('error', 'Fix failed: ' + response.data);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while applying the fix.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Apply Fix');
                }
            });
        },

        // Show notification
        showNotice: function(type, message) {
            const noticeHtml = `
                <div class="mds-notice ${type}">
                    ${message}
                    <button type="button" class="notice-dismiss" onclick="$(this).parent().fadeOut()">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `;
            
            $('.wrap h1').after(noticeHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.mds-notice').fadeOut();
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof mds_page_management !== 'undefined') {
            MDSPageManagement.init();
        }
    });

    // Make it globally available
    window.MDSPageManagement = MDSPageManagement;

})(jQuery); 