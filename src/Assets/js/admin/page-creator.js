/**
 * MDS Enhanced Page Creator JavaScript
 * Handles the interactive functionality for creating MDS pages
 */

(function($) {
    'use strict';
    
    const MDSPageCreator = {
        
        // Configuration
        config: {
            currentStep: 1,
            totalSteps: 5,
            selectedPageTypes: [],
            previewData: null,
            configurationLoadTimeout: null
        },
        
        /**
         * Initialize the page creator
         */
        init: function() {
            this.bindEvents();
            this.initializeSteps();
            this.loadInitialState();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Implementation type selection
            $(document).on('change', 'input[name="implementation_type"]', this.handleImplementationTypeChange.bind(this));
            
            // Page type selection
            $(document).on('change', 'input[name="page_types[]"]', this.handlePageTypeChange.bind(this));
            
            // Create mode selection
            $(document).on('change', 'input[name="create_mode"]', this.handleCreateModeChange.bind(this));
            
            // Quick create all pages button
            $('#mds-quick-create-all').on('click', this.handleQuickCreateAll.bind(this));
            
            // Grid sort buttons
            $('#mds-grid-sort-type-btn').on('click', this.handleGridSortType.bind(this));
            $('#mds-grid-sort-direction-btn').on('click', this.handleGridSortDirection.bind(this));
            
            // Preview button
            $('#mds-preview-creation').on('click', this.handlePreviewCreation.bind(this));
            
            // Create pages buttons (both top and bottom)
            $('#mds-create-pages, #mds-create-pages-bottom').on('click', this.handleCreatePages.bind(this));
            
            // Form submission
            $('#mds-page-creator-form').on('submit', this.handleFormSubmit.bind(this));
            
            // Modal close events
            $(document).on('click', '.mds-modal-close, #mds-modal-ok', this.hideModal.bind(this));
            $(document).on('click', '.mds-modal-overlay', this.hideModal.bind(this));
            $(document).on('keydown', this.handleModalKeydown.bind(this));
            
            // Step navigation (if implemented)
            $(document).on('click', '.mds-step-nav', this.handleStepNavigation.bind(this));
        },
        
        /**
         * Initialize step display
         */
        initializeSteps: function() {
            $('.mds-creator-step').each(function(index) {
                if (index === 0) {
                    $(this).addClass('active');
                } else {
                    $(this).addClass('inactive');
                }
            });
        },
        
        /**
         * Load initial state
         */
        loadInitialState: function() {
            // Load required page types
            this.updateSelectedPageTypes();
            this.loadPageConfigurations();
            
            // Initialize create mode selection
            this.initializeCreateModeSelection();
            
            // Initialize grid sorting (sort by ID ascending by default)
            this.performGridSort();
        },
        
        /**
         * Handle implementation type change
         */
        handleImplementationTypeChange: function(e) {
            const implementationType = $(e.target).val();
            
            // Show/hide relevant information
            this.updateImplementationInfo(implementationType);
            
            // Update create button state
            this.updateCreateButton();
            
            // Update preview if available
            if (this.config.previewData && $('#mds-preview-panel').is(':visible')) {
                this.handlePreviewCreation(e);
            }
        },
        
        /**
         * Handle page type selection change
         */
        handlePageTypeChange: function(e) {
            this.updateSelectedPageTypes();
            
            // Throttle configuration loading to prevent rapid fire AJAX calls
            if (this.config.configurationLoadTimeout) {
                clearTimeout(this.config.configurationLoadTimeout);
            }
            
            this.config.configurationLoadTimeout = setTimeout(() => {
                this.loadPageConfigurations();
            }, 300);
            
            // Enable/disable create button immediately
            this.updateCreateButton();
        },
        
        /**
         * Handle create mode selection change
         */
        handleCreateModeChange: function(e) {
            const createMode = $(e.target).val();
            
            // Add visual feedback for selected mode
            $('.mds-create-mode-option').removeClass('selected');
            $(e.target).closest('.mds-create-mode-option').addClass('selected');
            
            // If preview is already showing, regenerate it with new create mode
            if (this.config.previewData && $('#mds-preview-panel').is(':visible')) {
                this.handlePreviewCreation(e);
            }
        },
        
        /**
         * Initialize create mode selection display
         */
        initializeCreateModeSelection: function() {
            const checkedCreateMode = $('input[name="create_mode"]:checked');
            if (checkedCreateMode.length > 0) {
                $('.mds-create-mode-option').removeClass('selected');
                checkedCreateMode.closest('.mds-create-mode-option').addClass('selected');
            }
        },
        
        /**
         * Handle quick create all pages button
         */
        handleQuickCreateAll: function(e) {
            e.preventDefault();
            
            // Step 1: Select Gutenberg Blocks implementation
            $('input[name="implementation_type"][value="block"]').prop('checked', true).trigger('change');
            
            // Step 2: Select all page types
            $('input[name="page_types[]"]').prop('checked', true).trigger('change');
            
            // Step 3: Auto-advance through steps to Review section
            setTimeout(() => {
                this.goToStep(5); // Go to Review and Create step
                
                // Scroll to the Review section
                const reviewStep = $('.mds-creator-step[data-step="5"]');
                if (reviewStep.length > 0) {
                    $('html, body').animate({
                        scrollTop: reviewStep.offset().top - 100
                    }, 800);
                }
                
                // Start blinking the Preview button
                this.startPreviewButtonBlink();
            }, 500);
        },
        
        /**
         * Handle grid sort type button click (ID/Name toggle)
         */
        handleGridSortType: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            if ($btn.length === 0) {
                return; // Grid sort controls not present
            }
            
            const currentSortBy = $btn.data('sort-by');
            const newSortBy = currentSortBy === 'id' ? 'name' : 'id';
            
            // Update button state
            $btn.data('sort-by', newSortBy);
            
            // Update button visual state
            const $label = $btn.find('.sort-label');
            $label.text(newSortBy.toUpperCase());
            
            // Add active state briefly
            $btn.addClass('active');
            setTimeout(() => {
                $btn.removeClass('active');
            }, 200);
            
            // Sort the dropdown options
            this.performGridSort();
        },
        
        /**
         * Handle grid sort direction button click (Asc/Desc toggle)
         */
        handleGridSortDirection: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            if ($btn.length === 0) {
                return; // Grid sort controls not present
            }
            
            const currentDirection = $btn.data('sort-direction');
            const newDirection = currentDirection === 'asc' ? 'desc' : 'asc';
            
            // Update button state
            $btn.data('sort-direction', newDirection);
            
            // Update button visual state
            const $arrow = $btn.find('.sort-direction');
            const $label = $btn.find('.sort-direction-label');
            
            if (newDirection === 'asc') {
                $arrow.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
                $label.text('ASC');
            } else {
                $arrow.removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
                $label.text('DESC');
            }
            
            // Add active state briefly
            $btn.addClass('active');
            setTimeout(() => {
                $btn.removeClass('active');
            }, 200);
            
            // Sort the dropdown options
            this.performGridSort();
        },
        
        /**
         * Perform grid sort based on current button states
         */
        performGridSort: function() {
            const $sortTypeBtn = $('#mds-grid-sort-type-btn');
            const $sortDirectionBtn = $('#mds-grid-sort-direction-btn');
            
            // Check if sort controls exist
            if ($sortTypeBtn.length === 0 || $sortDirectionBtn.length === 0) {
                return; // Grid sort controls not present, skip sorting
            }
            
            const sortBy = $sortTypeBtn.data('sort-by');
            const direction = $sortDirectionBtn.data('sort-direction');
            this.sortGridOptions(sortBy, direction);
        },
        
        /**
         * Sort grid dropdown options
         */
        sortGridOptions: function(sortBy, direction) {
            const $select = $('#mds-grid-selector');
            if ($select.length === 0) {
                return;
            }
            
            const $allOptions = $select.find('option');
            const selectedValue = $select.val();
            
            // Find options that have values (actual grid options)
            const $gridOptions = $allOptions.filter(function() {
                return $(this).val() && $(this).val() !== '';
            });
            
            if ($gridOptions.length === 0) {
                return;
            }
            
            // Convert to array for sorting
            const optionsArray = $gridOptions.toArray();
            
            optionsArray.sort((a, b) => {
                let valueA, valueB;
                
                if (sortBy === 'id') {
                    // Sort by the value (grid ID)
                    valueA = parseInt($(a).val()) || 0;
                    valueB = parseInt($(b).val()) || 0;
                } else { // name
                    // Sort by the display text (grid name)
                    valueA = $(a).text().toLowerCase();
                    valueB = $(b).text().toLowerCase();
                }
                
                let comparison = 0;
                if (valueA > valueB) {
                    comparison = 1;
                } else if (valueA < valueB) {
                    comparison = -1;
                }
                
                return direction === 'asc' ? comparison : -comparison;
            });
            
            // Remove all grid options (keep any empty/placeholder options)
            $gridOptions.remove();
            
            // Add sorted options back
            optionsArray.forEach(option => {
                $select.append(option);
            });
            
            // Restore selected value if it still exists
            if (selectedValue && $select.find(`option[value="${selectedValue}"]`).length > 0) {
                $select.val(selectedValue);
            }
        },
        
        /**
         * Update selected page types array
         */
        updateSelectedPageTypes: function() {
            this.config.selectedPageTypes = [];
            $('input[name="page_types[]"]:checked').each((index, element) => {
                this.config.selectedPageTypes.push($(element).val());
            });
        },
        
        /**
         * Load page configurations dynamically
         */
        loadPageConfigurations: function() {
            if (this.config.selectedPageTypes.length === 0) {
                $('#mds-page-configurations').empty();
                return;
            }
            
            const data = {
                action: 'mds_get_page_configuration',
                nonce: mdsPageCreator.nonce,
                page_types: this.config.selectedPageTypes
            };
            
            $('#mds-page-configurations').html('<div class="mds-loading">Loading configurations...</div>');
            
            $.post(mdsPageCreator.ajaxUrl, data)
                .done((response) => {
                    if (response.success) {
                        $('#mds-page-configurations').html(response.data.html);
                        this.initializeConfigurationFields();
                    } else {
                        this.showError('Failed to load page configurations');
                    }
                })
                .fail(() => {
                    this.showError('Network error loading configurations');
                });
        },
        
        /**
         * Initialize configuration fields
         */
        initializeConfigurationFields: function() {
            // Initialize any special field types
            $('.mds-page-config select').each(function() {
                // Add any select2 or other enhancements here
            });
            
            // Add field validation
            $('.mds-page-config input[type="number"]').on('change', function() {
                const min = parseFloat($(this).attr('min'));
                const max = parseFloat($(this).attr('max'));
                const value = parseFloat($(this).val());
                
                if (!isNaN(min) && value < min) {
                    $(this).val(min);
                }
                if (!isNaN(max) && value > max) {
                    $(this).val(max);
                }
            });
        },
        
        /**
         * Handle preview creation
         */
        handlePreviewCreation: function(e) {
            e.preventDefault();
            
            // Stop blinking when clicked
            this.stopPreviewButtonBlink();
            
            const formData = this.getFormData();
            if (!this.validateFormData(formData)) {
                return;
            }
            
            $('#mds-preview-creation').prop('disabled', true).text(mdsPageCreator.strings.preview);
            
            const data = {
                action: 'mds_get_creation_preview',
                nonce: mdsPageCreator.nonce,
                ...formData
            };
            
            $.post(mdsPageCreator.ajaxUrl, data)
                .done((response) => {
                    if (response.success) {
                        this.config.previewData = response.data;
                        this.displayPreview(response.data);
                        $('#mds-create-pages, #mds-create-pages-bottom').prop('disabled', false);
                    } else {
                        this.showError(response.data || 'Preview generation failed');
                    }
                })
                .fail(() => {
                    this.showError('Network error generating preview');
                })
                .always(() => {
                    $('#mds-preview-creation').prop('disabled', false).text('Preview Creation');
                });
        },
        
        /**
         * Handle create pages
         */
        handleCreatePages: function(e) {
            e.preventDefault();
            
            const formData = this.getFormData();
            if (!this.validateFormData(formData)) {
                return;
            }
            
            // Confirmation dialog
            let confirmMessage = mdsPageCreator.strings.confirm_create;
            if (formData.create_mode === 'update_existing') {
                confirmMessage = mdsPageCreator.strings.confirm_update;
            }
            
            if (typeof MDSModalUtility !== 'undefined') {
                MDSModalUtility.confirm(confirmMessage, () => {
                    this.processCreatePages(formData);
                });
                return;
            } else if (!confirm(confirmMessage)) {
                return;
            }
            
            this.processCreatePages(formData);
        },
        
        /**
         * Process page creation after confirmation
         */
        processCreatePages: function(formData) {
            $('#mds-create-pages, #mds-create-pages-bottom').prop('disabled', true).text(mdsPageCreator.strings.creating);
            
            const data = {
                action: 'mds_creator_create_pages',  // Using alternative action name
                nonce: mdsPageCreator.nonce,
                ...formData
            };
            
            $.post(mdsPageCreator.ajaxUrl, data)
                .done((response) => {
                    if (response.success) {
                        this.displayResults(response.data);
                        this.showSuccess(mdsPageCreator.strings.success);
                    } else {
                        this.showError(response.data || 'Page creation failed');
                    }
                })
                .fail((xhr, status, error) => {
                    this.showError('Network error creating pages: ' + error);
                })
                .always(() => {
                    $('#mds-create-pages, #mds-create-pages-bottom').prop('disabled', false).text('Create Pages');
                });
        },
        
        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            this.handleCreatePages(e);
        },
        
        /**
         * Get form data
         */
        getFormData: function() {
            const formData = {};
            
            // Implementation type
            formData.implementation_type = $('input[name="implementation_type"]:checked').val();
            
            // Page types
            formData.page_types = [];
            $('input[name="page_types[]"]:checked').each(function() {
                formData.page_types.push($(this).val());
            });
            
            // Create mode
            formData.create_mode = $('input[name="create_mode"]:checked').val() || 'create_new';
            
            // Selected grid ID
            formData.selected_grid_id = $('#mds-grid-selector').val();
            
            // Configurations
            formData.configurations = {};
            $('.mds-page-config').each(function() {
                const pageType = $(this).data('page-type');
                formData.configurations[pageType] = {};
                
                $(this).find('input, select, textarea').each(function() {
                    const name = $(this).attr('name');
                    if (name && name.includes(`[${pageType}]`)) {
                        const fieldName = name.match(/\[([^\]]+)\]$/)[1];
                        
                        if ($(this).attr('type') === 'checkbox') {
                            if (name.includes('[]')) {
                                // Multi-checkbox
                                if (!formData.configurations[pageType][fieldName]) {
                                    formData.configurations[pageType][fieldName] = [];
                                }
                                if ($(this).is(':checked')) {
                                    formData.configurations[pageType][fieldName].push($(this).val());
                                }
                            } else {
                                // Single checkbox
                                formData.configurations[pageType][fieldName] = $(this).is(':checked');
                            }
                        } else {
                            formData.configurations[pageType][fieldName] = $(this).val();
                        }
                    }
                });
            });
            
            return formData;
        },
        
        /**
         * Validate form data
         */
        validateFormData: function(formData) {
            if (!formData.implementation_type) {
                this.showError('Please select an implementation type');
                return false;
            }
            
            if (!formData.page_types || formData.page_types.length === 0) {
                this.showError('Please select at least one page type');
                return false;
            }
            
            return true;
        },
        
        /**
         * Display preview
         */
        displayPreview: function(previewData) {
            let html = '<div class="mds-preview-summary">';
            html += `<p><strong>Pages to create:</strong> ${previewData.pages_to_create.length}</p>`;
            html += `<p><strong>Estimated time:</strong> ${previewData.estimated_time} seconds</p>`;
            html += '</div>';
            
            if (previewData.warnings && previewData.warnings.length > 0) {
                html += '<div class="mds-preview-warnings">';
                html += '<h4>Warnings:</h4>';
                html += '<ul>';
                previewData.warnings.forEach(warning => {
                    html += `<li>${warning}</li>`;
                });
                html += '</ul>';
                html += '</div>';
            }
            
            if (previewData.recommendations && previewData.recommendations.length > 0) {
                html += '<div class="mds-preview-recommendations">';
                html += '<h4>Recommendations:</h4>';
                html += '<ul>';
                previewData.recommendations.forEach(recommendation => {
                    html += `<li>${recommendation}</li>`;
                });
                html += '</ul>';
                html += '</div>';
            }
            
            html += '<div class="mds-preview-pages">';
            html += '<h4>Pages to be created:</h4>';
            html += '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr><th>Page Type</th><th>Title</th><th>Implementation</th><th>Status</th><th>Existing Page Details</th></tr></thead>';
            html += '<tbody>';
            
            previewData.pages_to_create.forEach(page => {
                html += '<tr>';
                html += `<td><strong>${page.page_type}</strong></td>`;
                html += `<td>${page.title}</td>`;
                html += `<td>${page.implementation_type}</td>`;
                html += `<td>${page.status || (page.existing_page ? 'Will update existing' : 'Will create new')}</td>`;
                
                // Enhanced existing page details column
                let existingPageInfo = '';
                if (page.existing_page_data) {
                    const epd = page.existing_page_data;
                    existingPageInfo += `<div class="mds-existing-page-info">`;
                    existingPageInfo += `<div><strong>ID:</strong> ${epd.id}</div>`;
                    existingPageInfo += `<div><strong>Title:</strong> "${epd.title}"</div>`;
                    existingPageInfo += `<div><strong>Status:</strong> ${epd.status}</div>`;
                    if (epd.date_modified) {
                        existingPageInfo += `<div><strong>Modified:</strong> ${epd.date_modified}</div>`;
                    }
                    if (epd.author) {
                        existingPageInfo += `<div><strong>Author:</strong> ${epd.author}</div>`;
                    }
                    existingPageInfo += `<div class="mds-page-links">`;
                    if (epd.url) {
                        existingPageInfo += `<a href="${epd.url}" target="_blank" class="button button-small">View Page</a> `;
                    }
                    if (epd.edit_link) {
                        existingPageInfo += `<a href="${epd.edit_link}" target="_blank" class="button button-small">Edit Page</a>`;
                    }
                    existingPageInfo += `</div>`;
                    existingPageInfo += `</div>`;
                } else {
                    existingPageInfo = '<em>No existing page</em>';
                }
                
                html += `<td>${existingPageInfo}</td>`;
                html += '</tr>';
            });
            
            html += '</tbody>';
            html += '</table>';
            html += '</div>';
            
            $('#mds-preview-content').html(html);
            $('#mds-preview-panel').show();
            
            // Scroll to preview panel
            if ($('#mds-preview-panel').length > 0) {
                $('html, body').animate({
                    scrollTop: $('#mds-preview-panel').offset().top - 50
                }, 600);
            }
        },
        
        /**
         * Display results
         */
        displayResults: function(results) {
            let html = '<div class="mds-results-summary">';
            html += `<p><strong>Created:</strong> ${results.total_created} pages</p>`;
            html += `<p><strong>Updated:</strong> ${results.total_updated} pages</p>`;
            html += `<p><strong>Errors:</strong> ${results.total_errors}</p>`;
            html += '</div>';
            
            if (results.created_pages && results.created_pages.length > 0) {
                html += '<div class="mds-results-created">';
                html += '<h4>Created Pages:</h4>';
                html += '<ul>';
                results.created_pages.forEach(page => {
                    html += `<li><a href="${page.page_url}" target="_blank">${page.page_title}</a> (${page.page_type})</li>`;
                });
                html += '</ul>';
                html += '</div>';
            }
            
            if (results.updated_pages && results.updated_pages.length > 0) {
                html += '<div class="mds-results-updated">';
                html += '<h4>Updated Pages:</h4>';
                html += '<ul>';
                results.updated_pages.forEach(page => {
                    html += `<li><a href="${page.page_url}" target="_blank">${page.page_title}</a> (${page.page_type})</li>`;
                });
                html += '</ul>';
                html += '</div>';
            }
            
            if (results.errors && results.errors.length > 0) {
                html += '<div class="mds-results-errors">';
                html += '<h4>Errors:</h4>';
                html += '<ul>';
                results.errors.forEach(error => {
                    html += `<li class="error">${error}</li>`;
                });
                html += '</ul>';
                html += '</div>';
            }
            
            $('#mds-results-content').html(html);
            $('#mds-results-panel').show();
            
            // Scroll to results if element exists
            if ($('#mds-results-panel').length > 0) {
                $('html, body').animate({
                    scrollTop: $('#mds-results-panel').offset().top
                }, 500);
            }
        },
        
        /**
         * Update implementation info
         */
        updateImplementationInfo: function(implementationType) {
            // Add visual feedback for selected implementation type
            $('.mds-implementation-option').removeClass('selected');
            $(`input[value="${implementationType}"]`).closest('.mds-implementation-option').addClass('selected');
        },
        
        /**
         * Update create button state
         */
        updateCreateButton: function() {
            const hasSelection = $('input[name="page_types[]"]:checked').length > 0;
            const hasImplementationType = $('input[name="implementation_type"]:checked').length > 0;
            
            const isEnabled = hasSelection && hasImplementationType;
            $('#mds-preview-creation').prop('disabled', !isEnabled);
            
            // Start blinking when button becomes available
            if (isEnabled && !$('#mds-preview-creation').hasClass('blinking')) {
                this.startPreviewButtonBlink();
            }
        },
        
        /**
         * Start blinking animation for Preview button
         */
        startPreviewButtonBlink: function() {
            const $previewBtn = $('#mds-preview-creation');
            if (!$previewBtn.prop('disabled') && !$previewBtn.hasClass('blinking')) {
                $previewBtn.addClass('blinking');
            }
        },
        
        /**
         * Stop blinking animation for Preview button
         */
        stopPreviewButtonBlink: function() {
            $('#mds-preview-creation').removeClass('blinking');
        },
        
        /**
         * Show modal notification
         */
        showModal: function(type, title, message, callback) {
            const modal = $('#mds-notification-modal');
            const icon = $('#mds-modal-icon');
            const titleEl = $('#mds-modal-title');
            const messageEl = $('#mds-modal-message');
            
            // Set icon and type
            icon.removeClass('error success warning info').addClass(type);
            
            // Set icon content based on type
            const icons = {
                'error': '✕',
                'success': '✓', 
                'warning': '!',
                'info': 'i'
            };
            icon.attr('data-icon', icons[type] || 'i');
            
            // Set content
            titleEl.text(title);
            messageEl.html(message);
            
            // Show modal with animation
            modal.fadeIn(200);
            
            // Store callback for later use
            modal.data('callback', callback);
            
            // Focus the OK button for accessibility
            setTimeout(() => {
                $('#mds-modal-ok').focus();
            }, 250);
        },
        
        /**
         * Hide modal notification
         */
        hideModal: function() {
            const modal = $('#mds-notification-modal');
            const callback = modal.data('callback');
            
            modal.fadeOut(200);
            
            // Execute callback if provided
            if (typeof callback === 'function') {
                setTimeout(callback, 200);
            }
        },
        
        /**
         * Show error message
         */
        showError: function(message, callback) {
            if (typeof MDSModalUtility !== 'undefined') {
                MDSModalUtility.error(message, callback);
            } else {
                this.showModal('error', 'Error', message, callback);
            }
        },
        
        /**
         * Show success message
         */
        showSuccess: function(message, callback) {
            if (typeof MDSModalUtility !== 'undefined') {
                MDSModalUtility.success(message, callback);
            } else {
                this.showModal('success', 'Success', message, callback);
            }
        },
        
        /**
         * Show warning message
         */
        showWarning: function(message, callback) {
            if (typeof MDSModalUtility !== 'undefined') {
                MDSModalUtility.warning(message, callback);
            } else {
                this.showModal('warning', 'Warning', message, callback);
            }
        },
        
        /**
         * Show info message
         */
        showInfo: function(message, callback) {
            if (typeof MDSModalUtility !== 'undefined') {
                MDSModalUtility.info(message, callback);
            } else {
                this.showModal('info', 'Information', message, callback);
            }
        },
        
        /**
         * Handle keyboard events for modal
         */
        handleModalKeydown: function(e) {
            const modal = $('#mds-notification-modal');
            if (modal.is(':visible')) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    this.hideModal();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    this.hideModal();
                }
            }
        },
        
        /**
         * Handle step navigation (if implemented)
         */
        handleStepNavigation: function(e) {
            e.preventDefault();
            const targetStep = $(e.target).data('step');
            this.goToStep(targetStep);
        },
        
        /**
         * Navigate to specific step
         */
        goToStep: function(stepNumber) {
            if (stepNumber < 1 || stepNumber > this.config.totalSteps) {
                return;
            }
            
            // Hide all steps
            $('.mds-creator-step').removeClass('active').addClass('inactive');
            
            // Show target step
            $(`.mds-creator-step[data-step="${stepNumber}"]`).removeClass('inactive').addClass('active');
            
            this.config.currentStep = stepNumber;
        },
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.mds-create-pages').length > 0) {
            MDSPageCreator.init();
        }
    });
    
})(jQuery); 