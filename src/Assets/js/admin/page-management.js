/**
 * MDS Page Management Interface JavaScript
 */

(function($) {
    'use strict';

    // Modal notification system
    const NotificationModal = {
        create: function(type, title, message, details = null) {
            const modal = $('<div>', {
                'class': 'mds-notification-modal',
                'id': 'mds-notification-modal'
            });

            const overlay = $('<div>', {
                'class': 'mds-modal-overlay'
            });

            const content = $('<div>', {
                'class': `mds-modal-content mds-${type}`
            });

            const header = $('<div>', {
                'class': 'mds-modal-header'
            }).append(
                $('<h3>').text(title),
                $('<button>', {
                    'class': 'mds-modal-close',
                    'type': 'button',
                    'aria-label': 'Close'
                }).html('&times;')
            );

            const body = $('<div>', {
                'class': 'mds-modal-body'
            }).append(
                $('<div>', {
                    'class': `mds-notification-icon mds-${type}-icon`
                }),
                $('<div>', {
                    'class': 'mds-notification-content'
                }).append(
                    $('<p>').html(message)
                )
            );

            if (details && details.length > 0) {
                const detailsSection = $('<div>', {
                    'class': 'mds-notification-details'
                }).append(
                    $('<h4>').text('Details'),
                    $('<ul>')
                );

                details.forEach(function(detail) {
                    detailsSection.find('ul').append($('<li>').text(detail));
                });

                body.find('.mds-notification-content').append(detailsSection);
            }

            const footer = $('<div>', {
                'class': 'mds-modal-footer'
            }).append(
                $('<button>', {
                    'class': 'button button-primary mds-modal-close',
                    'type': 'button'
                }).text('OK')
            );

            content.append(header, body, footer);
            modal.append(overlay, content);

            return modal;
        },

        show: function(type, title, message, details = null, callback = null) {
            this.hide();
            
            const modal = this.create(type, title, message, details);
            $('body').append(modal);
            
            modal.fadeIn(300);
            modal.find('.mds-modal-content').addClass('mds-modal-show');

            // Close handlers
            modal.find('.mds-modal-close').on('click', function() {
                NotificationModal.hide();
                if (callback && typeof callback === 'function') {
                    callback();
                }
            });

            // Overlay click - close without callback
            modal.find('.mds-modal-overlay').on('click', function() {
                NotificationModal.hide();
            });

            // ESC key handler - close without callback
            $(document).on('keydown.mds-modal', function(e) {
                if (e.keyCode === 27) {
                    NotificationModal.hide();
                }
            });
        },

        hide: function() {
            // Remove both notification and confirmation modals
            const modals = $('#mds-notification-modal, #mds-confirmation-modal');
            if (modals.length) {
                modals.find('.mds-modal-content').removeClass('mds-modal-show');
                modals.fadeOut(300, function() {
                    modals.remove();
                });
            }
            $(document).off('keydown.mds-modal');
        },

        // Show confirmation modal with custom buttons
        showConfirmation: function(title, message, onConfirm, onCancel = null) {
            this.hide();
            
            const modal = $('<div>', {
                'class': 'mds-notification-modal',
                'id': 'mds-confirmation-modal'
            });

            const overlay = $('<div>', {
                'class': 'mds-modal-overlay'
            });

            const content = $('<div>', {
                'class': 'mds-modal-content mds-info'
            });

            const header = $('<div>', {
                'class': 'mds-modal-header'
            }).append(
                $('<h3>').text(title),
                $('<button>', {
                    'class': 'mds-modal-close',
                    'type': 'button',
                    'aria-label': 'Close'
                }).html('&times;')
            );

            const body = $('<div>', {
                'class': 'mds-modal-body'
            }).append(
                $('<div>', {
                    'class': 'mds-notification-icon mds-info-icon'
                }),
                $('<div>', {
                    'class': 'mds-notification-content'
                }).append(
                    $('<p>').html(message)
                )
            );

            const footer = $('<div>', {
                'class': 'mds-modal-footer'
            }).append(
                $('<button>', {
                    'class': 'button mds-modal-cancel',
                    'type': 'button'
                }).text('Cancel'),
                $('<button>', {
                    'class': 'button button-primary mds-modal-confirm',
                    'type': 'button'
                }).text('OK')
            );

            content.append(header, body, footer);
            modal.append(overlay, content);
            $('body').append(modal);
            
            modal.fadeIn(300);
            modal.find('.mds-modal-content').addClass('mds-modal-show');

            // Event handlers
            modal.find('.mds-modal-close, .mds-modal-cancel, .mds-modal-overlay').on('click', function() {
                NotificationModal.hide();
                if (onCancel && typeof onCancel === 'function') {
                    onCancel();
                }
            });

            modal.find('.mds-modal-confirm').on('click', function() {
                NotificationModal.hide();
                if (onConfirm && typeof onConfirm === 'function') {
                    onConfirm();
                }
            });

            // ESC key handler
            $(document).on('keydown.mds-modal', function(e) {
                if (e.keyCode === 27) {
                    NotificationModal.hide();
                    if (onCancel && typeof onCancel === 'function') {
                        onCancel();
                    }
                }
            });
        }
    };

    // Main page management object
    const MDSPageManagement = {
        
        // Configuration
        config: {
            ajaxUrl: mdsPageManagement.ajaxUrl,
            nonce: mdsPageManagement.nonce,
            strings: mdsPageManagement.strings
        },
        
        // Progress tracking
        progressInterval: null,
        currentProgress: 0,
        
        // Operation state tracking
        operationState: {
            isRunning: false,
            operationType: null,
            startTime: null
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
            $(document).on('click', '.mds-delete-page', this.deletePage.bind(this));
            $(document).on('click', '.mds-activate-page', this.activatePage.bind(this));
            $(document).on('click', '.mds-deactivate-page', this.deactivatePage.bind(this));

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
            if (mdsPageManagement.autoRefresh) {
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

        // Operation state management
        startOperation: function(operationType) {
            if (this.operationState.isRunning) {
                this.showNotice('warning', `Another operation (${this.operationState.operationType}) is already running. Please wait for it to complete.`);
                return false;
            }
            
            this.operationState.isRunning = true;
            this.operationState.operationType = operationType;
            this.operationState.startTime = Date.now();
            
            return true;
        },
        
        endOperation: function() {
            this.operationState.isRunning = false;
            this.operationState.operationType = null;
            this.operationState.startTime = null;
        },
        
        isOperationRunning: function() {
            return this.operationState.isRunning;
        },

        validateAllPages: function(e) {
            e.preventDefault();
            
            if (!this.startOperation('Validate All Pages')) {
                return;
            }
            
            this.showProgress('Validating all pages...', 0);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_validate_pages_ajax',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.hideProgress();
                        const results = response.data;
                        const message = `Validation completed: ${results.validated} pages checked, ${results.valid} valid, ${results.invalid} invalid, ${results.orphaned} orphaned.`;
                        NotificationModal.show('success', 'Validation Complete', message, null, function() {
                            window.location.reload();
                        });
                    } else {
                        MDSPageManagement.hideProgress();
                        MDSPageManagement.showNotice('error', response.data.message || 'An error occurred during validation.');
                    }
                },
                error: function() {
                    MDSPageManagement.hideProgress();
                    MDSPageManagement.showNotice('error', 'An error occurred while validating pages.');
                },
                complete: function () {
                    MDSPageManagement.endOperation();
                }
            });
        },

        // Scan all pages with chunked processing
        scanAllPages: function(e) {
            e.preventDefault();
            
            // Check if another operation is already running
            if (!this.startOperation('Scan All Pages')) {
                return;
            }
            
            const confirmMessage = this.config.strings.confirm_scan_all || 
                'Are you sure you want to scan all pages? This will check all published pages for MDS content and may take some time.';
            
            NotificationModal.showConfirmation(
                'Confirm Scan All Pages',
                confirmMessage,
                () => {
                    this.startChunkedScan();
                },
                () => {
                    // If user cancels, end the operation
                    this.endOperation();
                }
            );
        },

        // Start chunked scanning process
        startChunkedScan: function() {
            this.showProgress('Initializing scan...', 0);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_scan_all_pages_start',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MDSPageManagement.processScanBatches(response.data);
                    } else {
                        MDSPageManagement.showNotice('error', response.data || 'Failed to initialize scan');
                        MDSPageManagement.hideProgress();
                        MDSPageManagement.endOperation();
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while initializing scan.');
                    MDSPageManagement.hideProgress();
                    MDSPageManagement.endOperation();
                }
            });
        },

        // Process scan batches recursively
        processScanBatches: function(scanData) {
            const { scan_id, total_pages, total_batches } = scanData;
            let currentBatch = 0;

            const processBatch = () => {
                if (currentBatch >= total_batches) {
                    this.completeScan(scan_id, total_pages);
                    return;
                }

                const message = `Scanning pages...<br>(${(currentBatch * 10) + 1}-${Math.min((currentBatch + 1) * 10, total_pages)} of ${total_pages})`;
                this.updateProgress(message, this.currentProgress);

                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'mds_scan_all_pages_batch',
                        scan_id: scan_id,
                        batch_number: currentBatch,
                        nonce: this.config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            currentBatch++;
                            
                            const realMessage = `Processed ${data.processed_pages} of ${data.total_pages} pages<br>(${data.found_pages} MDS pages found)`;
                            MDSPageManagement.updateProgress(realMessage, data.progress_percentage);
                            
                            if (data.is_complete) {
                                MDSPageManagement.completeScan(scan_id, data.total_pages, data.found_pages);
                            } else {
                                setTimeout(processBatch, 200);
                            }
                        } else {
                            MDSPageManagement.showNotice('error', response.data || 'Batch processing failed');
                            MDSPageManagement.hideProgress();
                            MDSPageManagement.endOperation();
                        }
                    },
                    error: function() {
                        MDSPageManagement.showNotice('error', `Error processing batch ${currentBatch + 1}`);
                        MDSPageManagement.hideProgress();
                        MDSPageManagement.endOperation();
                    }
                });
            };

            processBatch();
        },

        // Complete the scan and show results
        completeScan: function(scanId, totalPages, foundPages) {
            this.updateProgress('Scan completed!', 100);
            
            setTimeout(() => {
                this.hideProgress();
                this.endOperation();
                
                const message = foundPages !== undefined ? 
                    `Scan completed! Processed ${totalPages} pages and found ${foundPages} MDS pages.` :
                    `Scan completed! Processed ${totalPages} pages.`;
                
                NotificationModal.show('success', 'Scan Complete', message, null, function() {
                    MDSPageManagement.refreshData();
                });
            }, 4000);
        },

        // Repair all pages
        repairAllPages: function(e) {
            e.preventDefault();
            
            const confirmMessage = this.config.strings.confirm_repair_all || 
                'Are you sure you want to repair all pages with issues?';
            
            NotificationModal.showConfirmation(
                'Confirm Repair All',
                confirmMessage,
                () => {
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
                                const title = 'Repair Complete';
                                NotificationModal.show('success', title, response.data.message, null, function() {
                                    MDSPageManagement.refreshData();
                                });
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
                }
            );
        },

        // Export data
        exportData: function(e) {
            e.preventDefault();
            
            const format = $(e.target).data('format') || 'json';
            const includeContent = $(e.target).data('include-content') || false;
            
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
            
            const params = new URLSearchParams(formData);
            params.forEach((value, key) => {
                if (value) {
                    currentUrl.searchParams.set(key, value);
                } else {
                    currentUrl.searchParams.delete(key);
                }
            });
            
            window.location.href = currentUrl.toString();
        },

        // Clear filters
        clearFilters: function(e) {
            e.preventDefault();
            
            $('#mds-filter-form')[0].reset();
            
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
                        // Check if we should offer removal for non-MDS pages
                        if (response.data.offer_removal) {
                            const title = 'Page is Not an MDS Page';
                            const message = response.data.message + '<br><br>Would you like to remove this page from MDS management? The WordPress page will not be deleted.';
                            
                            NotificationModal.showConfirmation(
                                title,
                                message,
                                () => {
                                    // User confirmed removal
                                    MDSPageManagement.removePageFromList(response.data.page_id, response.data.page_title);
                                },
                                () => {
                                    // User cancelled - just refresh to show current state
                                    window.location.reload();
                                }
                            );
                        } else {
                            // Normal scan success
                            const title = 'Page Scan Complete';
                            NotificationModal.show('success', title, response.data.message, null, function() {
                                window.location.reload();
                            });
                        }
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
            
            const confirmMessage = this.config.strings.confirm_repair_page || 'Are you sure you want to repair this page?';
            
            NotificationModal.showConfirmation(
                'Confirm Repair',
                confirmMessage,
                () => {
                    // User confirmed
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
                        // Show success modal and refresh page after user dismisses it
                        const title = 'Page Repair Complete';
                        NotificationModal.show('success', title, response.data.message);
                        // Refresh after a delay to allow user to read the message
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
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
                () => {
                    // User cancelled - do nothing
                }
            );
        },

        // Delete page
        deletePage: function(e) {
            e.preventDefault();
            
            const pageId = $(e.target).closest('[data-page-id]').data('page-id');
            const pageTitle = $(e.target).closest('tr').find('.row-title').text();
            
            const confirmMessage = `Are you sure you want to delete "${pageTitle}"? This action cannot be undone.`;
            
            NotificationModal.showConfirmation(
                'Confirm Delete',
                confirmMessage,
                () => {
                    // User confirmed
                    this.showProgress('Deleting page...', 0);
                    
                    $.ajax({
                        url: this.config.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'mds_delete_page',
                            page_id: pageId,
                            nonce: this.config.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                NotificationModal.show('success', 'Page Deleted', response.data.message, null, function() {
                                    window.location.reload();
                                });
                            } else {
                                MDSPageManagement.showNotice('error', response.data.message);
                            }
                        },
                        error: function() {
                            MDSPageManagement.showNotice('error', 'An error occurred while deleting the page.');
                        },
                        complete: function() {
                            MDSPageManagement.hideProgress();
                        }
                    });
                },
                () => {
                    // User cancelled - do nothing
                }
            );
        },

        // Activate page
        activatePage: function(e) {
            e.preventDefault();
            
            const pageId = $(e.target).closest('[data-page-id]').data('page-id');
            const $button = $(e.target).closest('button');
            
            $button.prop('disabled', true).html('<span class="mds-spinner"></span>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_activate_page',
                    page_id: pageId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        NotificationModal.show('success', 'Page Activated', response.data.message, null, function() {
                            window.location.reload();
                        });
                    } else {
                        MDSPageManagement.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while activating the page.');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span>');
                }
            });
        },

        // Deactivate page
        deactivatePage: function(e) {
            e.preventDefault();
            
            const pageId = $(e.target).closest('[data-page-id]').data('page-id');
            const $button = $(e.target).closest('button');
            
            $button.prop('disabled', true).html('<span class="mds-spinner"></span>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_deactivate_page',
                    page_id: pageId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        NotificationModal.show('success', 'Page Deactivated', response.data.message, null, function() {
                            window.location.reload();
                        });
                    } else {
                        MDSPageManagement.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while deactivating the page.');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-minus"></span>');
                }
            });
        },

        // Remove page from MDS management (not delete the WordPress page)
        removePageFromList: function(pageId, pageTitleOrCallback, callback) {
            // Handle different parameter combinations for backward compatibility
            let pageTitle, onComplete;
            
            if (typeof pageTitleOrCallback === 'function') {
                // Called with (pageId, callback)
                pageTitle = null;
                onComplete = pageTitleOrCallback;
            } else {
                // Called with (pageId, pageTitle) or (pageId, pageTitle, callback)
                pageTitle = pageTitleOrCallback;
                onComplete = callback;
            }
            
            this.showProgress('Removing from MDS management...', 0);
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_bulk_page_action',
                    action_type: 'remove_from_list',
                    page_ids: [pageId],
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Use the page title from the response if not provided
                        const finalPageTitle = pageTitle || `Page ID ${pageId}`;
                        
                        // If we have a callback, call it directly without showing a modal
                        if (onComplete && typeof onComplete === 'function') {
                            onComplete();
                        } else {
                            // Show success modal if no callback provided
                            NotificationModal.show('success', 'Removed from Management', 
                                `"${finalPageTitle}" has been removed from MDS management. The WordPress page was not deleted.`, 
                                null, function() {
                                    window.location.reload();
                                });
                        }
                    } else {
                        MDSPageManagement.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    MDSPageManagement.showNotice('error', 'An error occurred while removing the page from management.');
                },
                complete: function() {
                    MDSPageManagement.hideProgress();
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
            
            const confirmMessage = `Are you sure you want to ${action} ${selectedPages.length} page(s)?`;
            
            NotificationModal.showConfirmation(
                'Confirm Bulk Action',
                confirmMessage,
                () => {
                    // User confirmed
                    this.showProgress(`Processing ${action}...`, 0);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mds_bulk_page_action',
                    action_type: action,
                    page_ids: selectedPages,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success modal and refresh page after user dismisses it
                        const title = 'Bulk Action Complete';
                        NotificationModal.show('success', title, response.data.message);
                        // Refresh after a delay to allow user to read the message
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
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
                () => {
                    // User cancelled - do nothing
                }
            );
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
                
                NotificationModal.showConfirmation(
                    'Confirm Fix Error',
                    'Are you sure you want to fix this error?',
                    () => {
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
                    },
                    () => {
                        // User cancelled - do nothing
                    }
                );
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
                NotificationModal.showConfirmation(
                    'Confirm Implementation Change',
                    'Changing the implementation type will convert the page content. Are you sure you want to continue?',
                    () => {
                        this.performSavePageConfig(formData);
                    },
                    () => {
                        // User cancelled - do nothing
                    }
                );
                return;
            }
            
            this.performSavePageConfig(formData);
        },

        // Perform the actual save page config
        performSavePageConfig: function(formData) {
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData + '&action=mds_save_page_config&nonce=' + this.config.nonce + '&convert_implementation=1',
                success: function(response) {
                    if (response.success) {
                        // Show success modal and refresh page after user dismisses it
                        const title = 'Configuration Saved';
                        NotificationModal.show('success', title, response.data.message);
                        $('#mds-page-config-modal').dialog('close');
                        // Refresh after a delay to allow user to read the message
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
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

        // Show progress indicator with simulated progress
        showProgress: function(message, percentage = 0) {
            // Only reset progress tracking when starting a completely new operation (modal doesn't exist)
            if (percentage === 0 && !$('#mds-progress-indicator').length) {
                this.currentProgress = 0;
            }
            
            if (!$('#mds-progress-indicator').length) {
                const modal = $('<div>', {
                    'class': 'mds-notification-modal',
                    'id': 'mds-progress-indicator'
                });

                const overlay = $('<div>', {
                    'class': 'mds-modal-overlay'
                });

                const content = $('<div>', {
                    'class': 'mds-modal-content mds-progress-content'
                });

                const header = $('<div>', {
                    'class': 'mds-modal-header'
                }).append(
                    $('<h3>').text('Processing...')
                );

                const body = $('<div>', {
                    'class': 'mds-modal-body'
                }).append(
                    $('<div>', {
                        'class': 'mds-progress-spinner'
                    }).append(
                        $('<div>', {
                            'class': 'mds-spinner'
                        })
                    ),
                    $('<div>', {
                        'class': 'mds-progress'
                    }).append(
                        $('<div>', {
                            'class': 'mds-progress-bar',
                            'id': 'mds-progress-bar',
                            'style': `width: ${Math.max(percentage, 10)}%`
                        }).text(Math.round(percentage) + '%')
                    ),
                    $('<p>', {
                        'class': 'mds-progress-message',
                        'id': 'mds-progress-message'
                    }).text(message)
                );

                content.append(header, body);
                modal.append(overlay, content);
                $('body').append(modal);
            }
            
            // Use monotonic progress for display - never go backwards
            const effectivePercentage = Math.max(percentage, this.currentProgress);
            this.currentProgress = effectivePercentage;
            
            const displayPercentage = Math.max(effectivePercentage, 10);
            $('#mds-progress-message').html(message);
            $('#mds-progress-bar').css('width', displayPercentage + '%').text(Math.round(effectivePercentage) + '%');
            
            const modal = $('#mds-progress-indicator');
            modal.fadeIn(300);
            modal.find('.mds-modal-content').addClass('mds-modal-show');
            
            // Start simulated progress only if we're truly at the beginning
            if (percentage === 0 && this.currentProgress === 0) {
                this.startSimulatedProgress();
            }
        },

        // Update progress (monotonic - never goes backwards)
        updateProgress: function(message, percentage) {
            // Stop any running simulated progress immediately when real progress comes in
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
                this.progressInterval = null;
            }
            
            // Ensure progress never goes backwards
            const newProgress = Math.max(percentage, this.currentProgress);
            this.currentProgress = newProgress;
            
            const displayPercentage = Math.max(newProgress, 10); // Ensure minimum width for visibility
            $('#mds-progress-message').html(message);
            $('#mds-progress-bar').css('width', displayPercentage + '%').text(Math.round(newProgress) + '%');
        },

        // Start simulated progress animation
        startSimulatedProgress: function() {
            // Clear any existing intervals
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
            }
            
            let currentProgress = Math.max(this.currentProgress, 10); // Start from current progress or 10%, whichever is higher
            this.progressInterval = setInterval(() => {
                // Check if progress has been taken over by real updates
                if (!this.progressInterval) {
                    return;
                }
                
                // Simulate realistic progress increments
                const increment = Math.random() * 3 + 1; // Reduced increment between 1-4%
                currentProgress = Math.min(currentProgress + increment, 75); // Cap at 75% to leave room for real progress
                
                // Update both the visual progress and our tracking variable
                this.currentProgress = currentProgress;
                
                const $progressBar = $('#mds-progress-bar');
                if ($progressBar.length) {
                    $progressBar.css('width', currentProgress + '%').text(Math.round(currentProgress) + '%');
                }
                
                // Stop if we've reached the cap
                if (currentProgress >= 75) {
                    clearInterval(this.progressInterval);
                    this.progressInterval = null;
                }
            }, 300 + Math.random() * 200); // Slower timing between 300-500ms
        },

        // Hide progress indicator
        hideProgress: function() {
            // Clear any running progress intervals
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
                this.progressInterval = null;
            }
            
            // Reset progress tracking
            this.currentProgress = 0;
            
            const modal = $('#mds-progress-indicator');
            if (modal.length) {
                modal.find('.mds-modal-content').removeClass('mds-modal-show');
                modal.fadeOut(300, function() {
                    modal.remove();
                });
            }
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
                        // Handle structured error response with detailed error information
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
                        
                        // Use modal notification with details
                        NotificationModal.show('error', 'Scan Error', errorMessage, errorDetails.length > 0 ? errorDetails : null);
                    }
                },
                error: function(xhr, status, error) {
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
                    
                    NotificationModal.show('error', 'Connection Error', errorMessage, errorDetails.length > 0 ? errorDetails : null);
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
            
            NotificationModal.showConfirmation(
                'Confirm Fix All Errors',
                'Are you sure you want to fix all auto-fixable errors? This will modify page content.',
                () => {
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
                () => {
                    // User cancelled - do nothing
                }
            );
        },

        // Reset page statuses
        resetPageStatuses: function(e) {
            e.preventDefault();
            
            NotificationModal.showConfirmation(
                'Confirm Reset Page Statuses',
                'Are you sure you want to reset all page statuses? This will re-validate all pages and update their statuses based on current validation rules.',
                () => {
                    const $button = $(e.target).closest('button');
                    const $errorProgress = $('#mds-error-progress');
                    const $progressText = $('#mds-error-progress-text');
                    
                    // Add spinner to button
                    this.setButtonLoading($button, true);
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
                            // Reset button spinner
                            MDSPageManagement.setButtonLoading($button, false);
                            $button.prop('disabled', false);
                            $errorProgress.hide();
                        }
                    });
                },
                () => {
                    // User cancelled - do nothing
                }
            );
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
                // Fallback notification modal if custom modal not available
                const errorDetails = `
                    <strong>Page:</strong> ${error.page_title}<br>
                    <strong>Type:</strong> ${error.type}<br>
                    <strong>Description:</strong> ${error.description}
                `;
                NotificationModal.show('info', 'Error Details', errorDetails);
            }
        },

        // Fix single error
        fixSingleError: function(e) {
            const error = JSON.parse($(e.target).attr('data-error'));
            const $button = $(e.target);
            
            NotificationModal.showConfirmation(
                'Confirm Fix Error',
                `Are you sure you want to fix this error on "${error.page_title}"?`,
                () => {
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
                () => {
                    // User cancelled - do nothing
                }
            );
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
                const errorList = errors.map(e => `• ${e.message}: ${e.description}`).join('<br>');
                NotificationModal.show('warning', 'Page Errors Found', errorList);
            }
        },

        // Fix single page error
        fixSinglePageError: function(e) {
            const pageId = $(e.target).data('page-id');
            const error = JSON.parse($(e.target).attr('data-error'));
            const $button = $(e.target);
            
            NotificationModal.showConfirmation(
                'Confirm Fix Error',
                `Fix this error: "${error.message}"?`,
                () => {
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
                () => {
                    // User cancelled - do nothing
                }
            );
        },

        // Show validation results with removal options
        showValidationResults: function(results) {
            let modalBody = `
                <div class="mds-validation-results">
                    <div class="mds-validation-summary">
                        <p><strong>Validation Summary:</strong></p>
                        <ul>
                            <li>Pages checked: ${results.total_checked}</li>
                            <li>Still valid: ${results.still_valid}</li>
                            <li>Invalid pages: ${results.invalid_pages.length}</li>
                        </ul>
                    </div>
            `;
            
            if (results.invalid_pages.length > 0) {
                modalBody += `
                    <div class="mds-invalid-pages">
                        <h4>Pages that no longer appear to be MDS pages:</h4>
                        <div class="mds-invalid-pages-list">
                `;
                
                results.invalid_pages.forEach(function(page) {
                    modalBody += `
                        <div class="mds-invalid-page-item" data-page-id="${page.id}">
                            <div class="mds-invalid-page-info">
                                <strong>${page.title}</strong>
                                <div class="mds-invalid-page-reason">${page.reason}</div>
                            </div>
                            <div class="mds-invalid-page-actions">
                                <button type="button" class="button button-small mds-remove-invalid-page" data-page-id="${page.id}">
                                    <span class="dashicons dashicons-trash"></span>
                                    Remove from MDS Management
                                </button>
                            </div>
                        </div>
                    `;
                });
                
                modalBody += `
                        </div>
                        <div class="mds-validation-bulk-actions">
                            <button type="button" class="button button-primary" id="mds-remove-all-invalid">
                                <span class="dashicons dashicons-trash"></span>
                                Remove All Invalid Pages
                            </button>
                            <button type="button" class="button button-secondary" id="mds-validation-refresh">
                                <span class="dashicons dashicons-update"></span>
                                Refresh Page
                            </button>
                        </div>
                    </div>
                `;
            } else {
                modalBody += `
                    <div class="mds-validation-no-invalid">
                        <h4>All pages are valid!</h4>
                        <p>All pages in MDS management still contain proper MDS content and are functioning correctly.</p>
                    </div>
                `;
            }
            
            if (results.errors.length > 0) {
                modalBody += `
                    <div class="mds-validation-errors">
                        <h4>Errors during validation:</h4>
                        <ul>
                `;
                results.errors.forEach(function(error) {
                    modalBody += `<li>Page ID ${error.page_id}: ${error.error}</li>`;
                });
                modalBody += `
                        </ul>
                    </div>
                `;
            }
            
            modalBody += '</div>';
            
            // Create custom modal for validation results
            const modal = $('<div>', {
                'class': 'mds-notification-modal',
                'id': 'mds-validation-results-modal'
            });

            const overlay = $('<div>', {
                'class': 'mds-modal-overlay'
            });

            const content = $('<div>', {
                'class': 'mds-modal-content mds-validation-modal'
            });

            const header = $('<div>', {
                'class': 'mds-modal-header'
            }).append(
                $('<h3>').text('Validation Results'),
                $('<button>', {
                    'class': 'mds-modal-close',
                    'type': 'button',
                    'aria-label': 'Close'
                }).html('&times;')
            );

            const body = $('<div>', {
                'class': 'mds-modal-body'
            }).html(modalBody);

            content.append(header, body);
            modal.append(overlay, content);
            $('body').append(modal);
            
            modal.fadeIn(300);
            modal.find('.mds-modal-content').addClass('mds-modal-show');

            // Handle close
            modal.find('.mds-modal-close, .mds-modal-overlay').on('click', function() {
                modal.fadeOut(300, function() {
                    modal.remove();
                    window.location.reload();
                });
            });
            
            // Handle individual page removal
            modal.on('click', '.mds-remove-invalid-page', function() {
                const pageId = $(this).data('page-id');
                const $button = $(this);
                const $item = $button.closest('.mds-invalid-page-item');
                const pageTitle = $item.find('strong').text();
                
                // Temporarily hide the validation modal to prevent z-index issues
                modal.hide();
                
                NotificationModal.showConfirmation(
                    'Remove from MDS Management',
                    `Are you sure you want to remove "${pageTitle}" from MDS management? This will not delete the WordPress page.`,
                    () => {
                        // User confirmed - show validation modal again and start removal
                        modal.show();
                        $button.prop('disabled', true).html('<span class="dashicons dashicons-trash"></span> Removing...');
                        
                        MDSPageManagement.removePageFromList(pageId, function() {
                            $item.fadeOut(function() {
                                // Check if this was the last invalid page
                                const remainingItems = modal.find('.mds-invalid-page-item:visible').length;
                                if (remainingItems === 0) {
                                    // Close validation modal and show success
                                    modal.fadeOut(300, function() {
                                        modal.remove();
                                        NotificationModal.show('success', 'All Invalid Pages Removed', 
                                            'All invalid pages have been removed from MDS management.', 
                                            null, function() {
                                                window.location.reload();
                                            });
                                    });
                                } else {
                                    // Reset button state
                                    $button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Remove from MDS Management');
                                }
                            });
                        });
                    },
                    () => {
                        // User cancelled - show validation modal again
                        modal.show();
                    }
                );
            });
            
            // Handle bulk removal
            modal.on('click', '#mds-remove-all-invalid', function() {
                const $button = $(this);
                const pageIds = results.invalid_pages.map(p => p.id);
                
                // Temporarily hide the validation modal to prevent z-index issues
                modal.hide();
                
                NotificationModal.showConfirmation(
                    'Remove All Invalid Pages',
                    `Are you sure you want to remove all ${pageIds.length} invalid pages from MDS management? This will not delete the WordPress pages.`,
                    () => {
                        // User confirmed - start removal process
                        modal.show(); // Show validation modal again
                        $button.prop('disabled', true).text('Removing...');
                        
                        // Remove pages one by one
                        let completed = 0;
                        pageIds.forEach(function(pageId) {
                            MDSPageManagement.removePageFromList(pageId, function() {
                                completed++;
                                if (completed === pageIds.length) {
                                    modal.fadeOut(300, function() {
                                        modal.remove();
                                        NotificationModal.show('success', 'Bulk Removal Complete', 
                                            `${pageIds.length} pages have been removed from MDS management.`, 
                                            null, function() {
                                                window.location.reload();
                                            });
                                    });
                                }
                            });
                        });
                    },
                    () => {
                        // User cancelled - show validation modal again
                        modal.show();
                    }
                );
            });
            
            // Handle refresh button
            modal.on('click', '#mds-validation-refresh', function() {
                modal.fadeOut(300, function() {
                    modal.remove();
                    window.location.reload();
                });
            });
        },

        // Show notification
        showNotice: function(type, message) {
            // Use the modal system instead of inline notices
            const title = type === 'error' ? 'Error' : type === 'success' ? 'Success' : type === 'warning' ? 'Warning' : 'Information';
            NotificationModal.show(type, title, message);
        },

        /**
         * Set button loading state with spinning icon
         *
         * @param {jQuery} $button - The button element
         * @param {boolean} loading - Whether to set loading state or restore normal state
         */
        setButtonLoading: function($button, loading) {
            const $icon = $button.find('.dashicons');
            
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
                }
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof mdsPageManagement !== 'undefined' && mdsPageManagement) {
            MDSPageManagement.init();
        } else {
            console.error('MDS Page Management: Localization object not found. Please ensure the page management interface is properly loaded.');
        }
    });

    // Make it globally available
    window.MDSPageManagement = MDSPageManagement;

})(jQuery);