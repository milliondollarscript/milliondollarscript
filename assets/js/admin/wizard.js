/**
 * Million Dollar Script Setup Wizard JavaScript
 * Handles AJAX interactions for the wizard steps and visual effects
 */

(function($) {
    'use strict';
    
    // Handle document ready
    $(document).ready(function() {
        // Initialize AJAX functionality
        initCreatePagesButton();
        initSaveSettingsButton();
        
        // Initialize fire cursor effect
        initFireCursorEffect();
        
        // Add step index attributes for animation sequencing
        initStepIndicators();
    });
    
    /**
     * Initialize the Create Pages button functionality
     */
    function initCreatePagesButton() {
        var $button = $('#mds-create-pages-button');
        var $status = $('#mds-create-pages-status');
        
        if (!$button.length) {
            return;
        }
        
        $button.on('click', function(e) {
            e.preventDefault();
            
            // Show processing indicator
            $button.addClass('updating-message').prop('disabled', true);
            $status.removeClass('mds-status-success mds-status-error')
                   .html('<p>' + mdsWizard.strings.creating_pages + '</p>');
            
            // Make AJAX request to create pages
            $.ajax({
                url: mdsWizard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mds_wizard_create_pages',
                    nonce: mdsWizard.nonce
                },
                success: function(response) {
                    $button.removeClass('updating-message').prop('disabled', false);
                    
                    if (response.success) {
                        $status.addClass('mds-status-success')
                               .html('<p>' + mdsWizard.strings.success + '</p>');
                        
                        // After successful page creation, redirect to next step
                        setTimeout(function() {
                            window.location.href = window.location.href.replace('step=pages', 'step=settings');
                        }, 1500);
                    } else {
                        $status.addClass('mds-status-error')
                               .html('<p>' + mdsWizard.strings.error + ' ' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $button.removeClass('updating-message').prop('disabled', false);
                    $status.addClass('mds-status-error')
                           .html('<p>' + mdsWizard.strings.error + ' ' + 'Could not create pages. Please try again or create them manually from the Options page.' + '</p>');
                }
            });
        });
    }
    
    /**
     * Initialize the Save Settings button functionality
     */
    function initSaveSettingsButton() {
        var $button = $('#mds-save-settings-button');
        var $form = $('#mds-wizard-settings-form');
        var $status = $('#mds-settings-status');
        
        if (!$button.length || !$form.length) {
            return;
        }
        
        $button.on('click', function(e) {
            e.preventDefault();
            
            // Show processing indicator
            $button.addClass('updating-message').prop('disabled', true);
            $status.removeClass('mds-status-success mds-status-error')
                   .html('<p>' + mdsWizard.strings.saving_settings + '</p>');
            
            // Get form data
            var formData = {
                action: 'mds_wizard_save_settings',
                nonce: mdsWizard.nonce,
                pixel_selection_method: $form.find('select[name="pixel_selection_method"]').val(),
                price_per_pixel: $form.find('input[name="price_per_pixel"]').val(),
                grid_width: $form.find('input[name="grid_width"]').val(),
                grid_height: $form.find('input[name="grid_height"]').val()
            };
            
            // Make AJAX request to save settings
            $.ajax({
                url: mdsWizard.ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    $button.removeClass('updating-message').prop('disabled', false);
                    
                    if (response.success) {
                        $status.addClass('mds-status-success')
                               .html('<p>' + mdsWizard.strings.success + ' ' + response.data.message + '</p>');
                        
                        // After successful settings save, redirect to next step
                        setTimeout(function() {
                            window.location.href = window.location.href.replace('step=settings', 'step=complete');
                        }, 1500);
                    } else {
                        $status.addClass('mds-status-error')
                               .html('<p>' + mdsWizard.strings.error + ' ' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $button.removeClass('updating-message').prop('disabled', false);
                    $status.addClass('mds-status-error')
                           .html('<p>' + mdsWizard.strings.error + ' ' + 'Could not save settings. Please try again or configure them manually from the Options page.' + '</p>');
                }
            });
        });
    }
    
    /**
     * Initialize the fire cursor effect that follows the mouse
     * in the header area
     */
    function initFireCursorEffect() {
        // Create fire cursor element if not exists
        if ($('#mds-wizard-fire-cursor').length === 0) {
            $('body').append('<div id="mds-wizard-fire-cursor"></div>');
        }
        
        var $fireHeader = $('.mds-wizard-fire-header');
        var $fireCursor = $('#mds-wizard-fire-cursor');
        
        if (!$fireHeader.length) {
            return;
        }
        
        // Store cursor position for smooth animation
        var currentPosition = { x: 0, y: 0 };
        var targetPosition = { x: 0, y: 0 };
        var isHovering = false;
        var easeFactor = 0.1; // Lower = smoother but slower
        
        // Helper function to smoothly interpolate between positions
        function lerp(start, end, t) {
            return start * (1 - t) + end * t;
        }
        
        // Track mouse position when over the fire header
        $fireHeader.on('mouseenter', function() {
            isHovering = true;
            $fireCursor.css('opacity', 1);
        }).on('mouseleave', function() {
            isHovering = false;
            $fireCursor.css('opacity', 0);
        }).on('mousemove', function(e) {
            var rect = this.getBoundingClientRect();
            targetPosition.x = e.pageX - rect.left - window.scrollX;
            targetPosition.y = e.pageY - rect.top - window.scrollY;
        });
        
        // Animation function to update cursor position
        function updateFirePosition() {
            if (isHovering) {
                currentPosition.x = lerp(currentPosition.x, targetPosition.x, easeFactor);
                currentPosition.y = lerp(currentPosition.y, targetPosition.y, easeFactor);
                
                $fireCursor.css({
                    left: currentPosition.x,
                    top: currentPosition.y
                });
            }
            
            requestAnimationFrame(updateFirePosition);
        }
        
        // Start the animation loop
        updateFirePosition();
    }
    
    /**
     * Initialize step indicators for proper animation sequencing
     */
    function initStepIndicators() {
        var $steps = $('.step-indicator li');
        
        if (!$steps.length) {
            return;
        }
        
        // Add step index for CSS animation sequencing
        $steps.each(function(index) {
            $(this).css('--step-index', index);
        });
    }
    
    /**
     * Create minified version (will be handled by VSCode task)
     */
    
})(jQuery);
