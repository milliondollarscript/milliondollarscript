/**
 * MDS Shared Modal Utility
 * 
 * A reusable modal system for replacing browser alerts and confirms
 * with styled modals throughout the MDS admin interface.
 */

(function($) {
    'use strict';

    // Shared Modal Utility
    const MDSModalUtility = {
        // Counter for unique modal IDs
        modalCounter: 0,

        /**
         * Create a modal HTML structure
         * @param {string} type - Modal type (success, error, warning, info)
         * @param {string} title - Modal title
         * @param {string} message - Modal message
         * @param {Array|null} details - Optional details list
         * @param {boolean} isConfirmation - Whether this is a confirmation modal
         * @returns {jQuery} Modal element
         */
        create: function(type, title, message, details = null, isConfirmation = false) {
            this.modalCounter++;
            const modalId = `mds-modal-${this.modalCounter}`;
            
            const modal = $('<div>', {
                'class': 'mds-notification-modal',
                'id': modalId
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

            // Add details if provided
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

            // Create footer with appropriate buttons
            const footer = $('<div>', {
                'class': 'mds-modal-footer'
            });

            if (isConfirmation) {
                footer.append(
                    $('<button>', {
                        'class': 'button mds-modal-cancel',
                        'type': 'button'
                    }).text('Cancel'),
                    $('<button>', {
                        'class': 'button button-primary mds-modal-confirm',
                        'type': 'button'
                    }).text('OK')
                );
            } else {
                footer.append(
                    $('<button>', {
                        'class': 'button button-primary mds-modal-close',
                        'type': 'button'
                    }).text('OK')
                );
            }

            content.append(header, body, footer);
            modal.append(overlay, content);

            return modal;
        },

        /**
         * Show a notification modal
         * @param {string} type - Modal type (success, error, warning, info)
         * @param {string} title - Modal title
         * @param {string} message - Modal message
         * @param {Array|null} details - Optional details list
         * @param {Function|null} callback - Callback function when closed
         */
        show: function(type, title, message, details = null, callback = null) {
            this.hideAll();
            
            const modal = this.create(type, title, message, details, false);
            $('body').append(modal);
            
            modal.addClass('show').fadeIn(300);
            modal.find('.mds-modal-content').addClass('mds-modal-show');

            // Close handlers
            modal.find('.mds-modal-close').on('click', function() {
                MDSModalUtility.hide(modal);
                if (callback && typeof callback === 'function') {
                    callback();
                }
            });

            // Overlay click - close without callback
            modal.find('.mds-modal-overlay').on('click', function() {
                MDSModalUtility.hide(modal);
            });

            // ESC key handler - close without callback
            $(document).on(`keydown.mds-modal-${modal.attr('id')}`, function(e) {
                if (e.keyCode === 27) {
                    MDSModalUtility.hide(modal);
                }
            });

            return modal;
        },

        /**
         * Show a confirmation modal
         * @param {string} title - Modal title
         * @param {string} message - Modal message
         * @param {Function} onConfirm - Callback when confirmed
         * @param {Function|null} onCancel - Callback when cancelled
         * @param {string} confirmText - Text for confirm button (default: 'OK')
         * @param {string} cancelText - Text for cancel button (default: 'Cancel')
         */
        showConfirmation: function(title, message, onConfirm, onCancel = null, confirmText = 'OK', cancelText = 'Cancel') {
            this.hideAll();
            
            const modal = this.create('info', title, message, null, true);
            
            // Update button text
            modal.find('.mds-modal-confirm').text(confirmText);
            modal.find('.mds-modal-cancel').text(cancelText);
            
            $('body').append(modal);
            
            modal.addClass('show').fadeIn(300);
            modal.find('.mds-modal-content').addClass('mds-modal-show');

            // Event handlers
            modal.find('.mds-modal-close, .mds-modal-cancel, .mds-modal-overlay').on('click', function() {
                MDSModalUtility.hide(modal);
                if (onCancel && typeof onCancel === 'function') {
                    onCancel();
                }
            });

            modal.find('.mds-modal-confirm').on('click', function() {
                MDSModalUtility.hide(modal);
                if (onConfirm && typeof onConfirm === 'function') {
                    onConfirm();
                }
            });

            // ESC key handler
            $(document).on(`keydown.mds-modal-${modal.attr('id')}`, function(e) {
                if (e.keyCode === 27) {
                    MDSModalUtility.hide(modal);
                    if (onCancel && typeof onCancel === 'function') {
                        onCancel();
                    }
                }
            });

            return modal;
        },

        /**
         * Hide a specific modal
         * @param {jQuery} modal - Modal element to hide
         */
        hide: function(modal) {
            if (modal && modal.length) {
                const modalId = modal.attr('id');
                $(document).off(`keydown.mds-modal-${modalId}`);
                
                modal.find('.mds-modal-content').removeClass('mds-modal-show');
                modal.removeClass('show').fadeOut(300, function() {
                    modal.remove();
                });
            }
        },

        /**
         * Hide all MDS modals
         */
        hideAll: function() {
            const modals = $('.mds-notification-modal');
            if (modals.length) {
                modals.each(function() {
                    const modal = $(this);
                    const modalId = modal.attr('id');
                    $(document).off(`keydown.mds-modal-${modalId}`);
                });
                
                modals.find('.mds-modal-content').removeClass('mds-modal-show');
                modals.removeClass('show').fadeOut(300, function() {
                    modals.remove();
                });
            }
        },

        /**
         * Convenience method to show success modal
         * @param {string} message - Success message
         * @param {Function|null} callback - Callback when closed
         */
        success: function(message, callback = null) {
            return this.show('success', 'Success', message, null, callback);
        },

        /**
         * Convenience method to show error modal
         * @param {string} message - Error message
         * @param {Function|null} callback - Callback when closed
         */
        error: function(message, callback = null) {
            return this.show('error', 'Error', message, null, callback);
        },

        /**
         * Convenience method to show warning modal
         * @param {string} message - Warning message
         * @param {Function|null} callback - Callback when closed
         */
        warning: function(message, callback = null) {
            return this.show('warning', 'Warning', message, null, callback);
        },

        /**
         * Convenience method to show info modal
         * @param {string} message - Info message
         * @param {Function|null} callback - Callback when closed
         */
        info: function(message, callback = null) {
            return this.show('info', 'Information', message, null, callback);
        },

        /**
         * Replace browser alert() function
         * @param {string} message - Alert message
         * @param {Function|null} callback - Callback when closed
         */
        alert: function(message, callback = null) {
            return this.show('info', 'Alert', message, null, callback);
        },

        /**
         * Replace browser confirm() function
         * @param {string} message - Confirmation message
         * @param {Function} onConfirm - Callback when confirmed
         * @param {Function|null} onCancel - Callback when cancelled
         */
        confirm: function(message, onConfirm, onCancel = null) {
            return this.showConfirmation('Confirm', message, onConfirm, onCancel, 'OK', 'Cancel');
        }
    };

    // Make it globally available
    window.MDSModalUtility = MDSModalUtility;

    // Also make it available as a jQuery plugin
    if ($ && $.fn) {
        $.mdsModal = MDSModalUtility;
    }

})(jQuery);