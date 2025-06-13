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
            totalSteps: 4,
            selectedPageTypes: [],
            previewData: null
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
            
            // Preview button
            $('#mds-preview-creation').on('click', this.handlePreviewCreation.bind(this));
            
            // Create pages button
            $('#mds-create-pages').on('click', this.handleCreatePages.bind(this));
            
            // Form submission
            $('#mds-page-creator-form').on('submit', this.handleFormSubmit.bind(this));
            
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
        },
        
        /**
         * Handle implementation type change
         */
        handleImplementationTypeChange: function(e) {
            const implementationType = $(e.target).val();
            
            // Show/hide relevant information
            this.updateImplementationInfo(implementationType);
            
            // Update preview if available
            if (this.config.previewData) {
                this.updatePreview();
            }
        },
        
        /**
         * Handle page type selection change
         */
        handlePageTypeChange: function(e) {
            this.updateSelectedPageTypes();
            this.loadPageConfigurations();
            
            // Enable/disable create button
            this.updateCreateButton();
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
                        $('#mds-create-pages').prop('disabled', false);
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
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            $('#mds-create-pages').prop('disabled', true).text(mdsPageCreator.strings.creating);
            
            const data = {
                action: 'mds_create_pages',
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
                .fail(() => {
                    this.showError('Network error creating pages');
                })
                .always(() => {
                    $('#mds-create-pages').prop('disabled', false).text('Create Pages');
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
            formData.create_mode = $('input[name="create_mode"]:checked').val();
            
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
            html += '<thead><tr><th>Page Type</th><th>Title</th><th>Implementation</th><th>Status</th></tr></thead>';
            html += '<tbody>';
            
            previewData.pages_to_create.forEach(page => {
                html += '<tr>';
                html += `<td>${page.page_type}</td>`;
                html += `<td>${page.title}</td>`;
                html += `<td>${page.implementation_type}</td>`;
                html += `<td>${page.existing_page ? 'Will update existing' : 'Will create new'}</td>`;
                html += '</tr>';
            });
            
            html += '</tbody>';
            html += '</table>';
            html += '</div>';
            
            $('#mds-preview-content').html(html);
            $('#mds-creation-preview').show();
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
            $('#mds-creation-results').show();
            
            // Scroll to results
            $('html, body').animate({
                scrollTop: $('#mds-creation-results').offset().top
            }, 500);
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
            
            $('#mds-preview-creation').prop('disabled', !(hasSelection && hasImplementationType));
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            const notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
            $('.mds-create-pages h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notice.fadeOut();
            }, 5000);
        },
        
        /**
         * Show success message
         */
        showSuccess: function(message) {
            const notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
            $('.mds-create-pages h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notice.fadeOut();
            }, 5000);
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
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.mds-create-pages').length > 0) {
            MDSPageCreator.init();
        }
    });
    
})(jQuery); 