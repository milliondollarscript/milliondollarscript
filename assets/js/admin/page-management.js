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
            // Quick action buttons
            $('.mds-scan-all').on('click', this.scanAllPages.bind(this));
            $('.mds-repair-all').on('click', this.repairAllPages.bind(this));
            $('.mds-export-data').on('click', this.exportData.bind(this));
            $('.mds-refresh-data').on('click', this.refreshData.bind(this));

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
            // Create modal container if it doesn't exist
            if (!$('#mds-modal-container').length) {
                $('body').append('<div id="mds-modal-container"></div>');
            }
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
            
            window.location.href = this.config.ajaxUrl + '?' + $.param({
                action: 'mds_export_pages',
                format: format,
                nonce: this.config.nonce
            });
        },

        // Refresh data
        refreshData: function(e) {
            if (e) e.preventDefault();
            
            this.showProgress('Refreshing data...', 0);
            
            // Refresh the page list
            window.location.reload();
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
            const modalHtml = `
                <div class="mds-modal" id="mds-page-details-modal">
                    <div class="mds-modal-content">
                        <div class="mds-modal-header">
                            <h2 class="mds-modal-title">Page Details: ${data.title}</h2>
                            <button class="mds-modal-close">&times;</button>
                        </div>
                        <div class="mds-modal-body">
                            <div class="mds-page-details">
                                <div class="mds-detail-section">
                                    <h4>Basic Information</h4>
                                    <ul class="mds-detail-list">
                                        <li><span class="mds-detail-label">Page Type:</span> <span class="mds-detail-value">${data.page_type}</span></li>
                                        <li><span class="mds-detail-label">Implementation:</span> <span class="mds-detail-value">${data.implementation}</span></li>
                                        <li><span class="mds-detail-label">Status:</span> <span class="mds-detail-value">${data.status}</span></li>
                                        <li><span class="mds-detail-label">Confidence:</span> <span class="mds-detail-value">${Math.round(data.confidence * 100)}%</span></li>
                                    </ul>
                                </div>
                                <div class="mds-detail-section">
                                    <h4>Scan Information</h4>
                                    <ul class="mds-detail-list">
                                        <li><span class="mds-detail-label">Last Scan:</span> <span class="mds-detail-value">${data.last_scan || 'Never'}</span></li>
                                        <li><span class="mds-detail-label">Created:</span> <span class="mds-detail-value">${data.created_date}</span></li>
                                        <li><span class="mds-detail-label">Modified:</span> <span class="mds-detail-value">${data.modified_date}</span></li>
                                    </ul>
                                </div>
                            </div>
                            ${data.content_analysis ? `
                                <div class="mds-detail-section">
                                    <h4>Content Analysis</h4>
                                    <pre>${JSON.stringify(data.content_analysis, null, 2)}</pre>
                                </div>
                            ` : ''}
                        </div>
                        <div class="mds-modal-footer">
                            <button class="button button-secondary mds-modal-close">Close</button>
                            <a href="${data.edit_url}" class="button button-primary">Edit Page</a>
                        </div>
                    </div>
                </div>
            `;
            
            $('#mds-modal-container').html(modalHtml);
            $('#mds-page-details-modal').show();
        },

        // Show configuration modal
        showConfigurationModal: function(data) {
            const modalHtml = `
                <div class="mds-modal" id="mds-page-config-modal">
                    <div class="mds-modal-content">
                        <div class="mds-modal-header">
                            <h2 class="mds-modal-title">Configure Page: ${data.title}</h2>
                            <button class="mds-modal-close">&times;</button>
                        </div>
                        <div class="mds-modal-body">
                            <form id="mds-page-config-form">
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
                                                ${this.getImplementationOptions(data.implementation_type)}
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
                        </div>
                        <div class="mds-modal-footer">
                            <button class="button button-secondary mds-modal-close">Cancel</button>
                            <button class="button button-primary" onclick="MDSPageManagement.savePageConfig()">Save Configuration</button>
                        </div>
                    </div>
                </div>
            `;
            
            $('#mds-modal-container').html(modalHtml);
            $('#mds-page-config-modal').show();
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
                'block': 'Block',
                'hybrid': 'Hybrid'
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
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData + '&action=mds_save_page_config&nonce=' + this.config.nonce,
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.showNotice('success', response.data.message);
                        MDSPageManagement.closeModal();
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
            $('.mds-modal').hide();
            $('#mds-modal-container').empty();
        },

        // Show progress indicator
        showProgress: function(message, percentage) {
            if (!$('#mds-progress-indicator').length) {
                $('body').append(`
                    <div id="mds-progress-indicator" class="mds-modal">
                        <div class="mds-modal-content" style="max-width: 400px;">
                            <div class="mds-modal-body">
                                <div class="mds-loading">
                                    <div class="mds-spinner"></div>
                                    <span id="mds-progress-message">${message}</span>
                                </div>
                                <div class="mds-progress">
                                    <div class="mds-progress-bar" id="mds-progress-bar" style="width: ${percentage}%">
                                        ${percentage}%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
            }
            
            $('#mds-progress-message').text(message);
            $('#mds-progress-bar').css('width', percentage + '%').text(percentage + '%');
            $('#mds-progress-indicator').show();
        },

        // Update progress
        updateProgress: function(message, percentage) {
            $('#mds-progress-message').text(message);
            $('#mds-progress-bar').css('width', percentage + '%').text(percentage + '%');
        },

        // Hide progress indicator
        hideProgress: function() {
            $('#mds-progress-indicator').hide();
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