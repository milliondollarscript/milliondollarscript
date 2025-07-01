/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2025, Ryan Rhode
 * @license     https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *    Million Dollar Script
 *    Pixels to Profit: Ignite Your Revolution
 *    https://milliondollarscript.com/
 *
 */

jQuery(document).ready(function($) {
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
                    'aria-label': mdsPageManagement.strings.close
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
                    $('<h4>').text(mdsPageManagement.strings.details),
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
                }).text(mdsPageManagement.strings.ok)
            );

            content.append(header, body, footer);
            modal.append(overlay, content);

            return modal;
        },

        show: function(type, title, message, details = null) {
            this.hide();
            
            const modal = this.create(type, title, message, details);
            $('body').append(modal);
            
            modal.fadeIn(300);
            modal.find('.mds-modal-content').addClass('mds-modal-show');

            // Close handlers
            modal.find('.mds-modal-close, .mds-modal-overlay').on('click', function() {
                NotificationModal.hide();
            });

            // ESC key handler
            $(document).on('keydown.mds-modal', function(e) {
                if (e.keyCode === 27) {
                    NotificationModal.hide();
                }
            });
        },

        hide: function() {
            const modal = $('#mds-notification-modal');
            if (modal.length) {
                modal.find('.mds-modal-content').removeClass('mds-modal-show');
                modal.fadeOut(300, function() {
                    modal.remove();
                });
            }
            $(document).off('keydown.mds-modal');
        }
    };

    // Progress modal system
    const ProgressModal = {
        show: function(title, message = '') {
            this.hide();

            const modal = $('<div>', {
                'class': 'mds-progress-modal',
                'id': 'mds-progress-modal'
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
                $('<h3>').text(title)
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
                $('<p>', {
                    'class': 'mds-progress-message'
                }).text(message || mdsPageManagement.strings.processing)
            );

            content.append(header, body);
            modal.append(overlay, content);
            $('body').append(modal);

            modal.fadeIn(300);
            modal.find('.mds-modal-content').addClass('mds-modal-show');

            return modal;
        },

        updateMessage: function(message) {
            $('#mds-progress-modal .mds-progress-message').text(message);
        },

        hide: function() {
            const modal = $('#mds-progress-modal');
            if (modal.length) {
                modal.find('.mds-modal-content').removeClass('mds-modal-show');
                modal.fadeOut(300, function() {
                    modal.remove();
                });
            }
        }
    };

    // Bulk actions handler
    $('#doaction, #doaction2').on('click', function(e) {
        e.preventDefault();

        const action = $(this).attr('id') === 'doaction' ? 
            $('#bulk-action-selector-top').val() : 
            $('#bulk-action-selector-bottom').val();

        if (action === '-1') {
            NotificationModal.show('warning', 
                mdsPageManagement.strings.no_action_selected,
                mdsPageManagement.strings.please_select_action
            );
            return;
        }

        const checkedItems = $('input[name="page_ids[]"]:checked');
        if (checkedItems.length === 0) {
            NotificationModal.show('warning',
                mdsPageManagement.strings.no_items_selected,
                mdsPageManagement.strings.please_select_items
            );
            return;
        }

        const pageIds = checkedItems.map(function() {
            return $(this).val();
        }).get();

        // Confirmation for delete action
        if (action === 'delete') {
            const confirmMessage = pageIds.length === 1 ? 
                mdsPageManagement.strings.confirm_delete_single :
                mdsPageManagement.strings.confirm_delete_multiple.replace('%d', pageIds.length);

            if (!confirm(confirmMessage)) {
                return;
            }
        }

        processBulkAction(action, pageIds);
    });

    // Process bulk action via AJAX
    function processBulkAction(action, pageIds) {
        const actionText = getActionText(action);
        ProgressModal.show(
            mdsPageManagement.strings.processing_bulk_action,
            actionText + '...'
        );

        $.ajax({
            url: mdsPageManagement.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mds_bulk_page_action',
                action_type: action,
                page_ids: pageIds,
                nonce: mdsPageManagement.nonce
            },
            success: function(response) {
                ProgressModal.hide();

                if (response.success) {
                    const data = response.data;
                    const hasErrors = data.error_count > 0;
                    
                    if (hasErrors) {
                        NotificationModal.show('warning',
                            mdsPageManagement.strings.bulk_action_completed_with_errors,
                            data.message,
                            data.errors
                        );
                    } else {
                        NotificationModal.show('success',
                            mdsPageManagement.strings.bulk_action_completed,
                            data.message
                        );
                    }

                    // Reload page after successful bulk action
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    NotificationModal.show('error',
                        mdsPageManagement.strings.bulk_action_failed,
                        response.data || mdsPageManagement.strings.unknown_error
                    );
                }
            },
            error: function(xhr, status, error) {
                ProgressModal.hide();
                NotificationModal.show('error',
                    mdsPageManagement.strings.ajax_error,
                    error || mdsPageManagement.strings.unknown_error
                );
            }
        });
    }

    // Get action text for display
    function getActionText(action) {
        const actionTexts = {
            'scan': mdsPageManagement.strings.scanning_pages,
            'repair': mdsPageManagement.strings.repairing_pages,
            'delete': mdsPageManagement.strings.deleting_pages,
            'activate': mdsPageManagement.strings.activating_pages,
            'deactivate': mdsPageManagement.strings.deactivating_pages
        };
        return actionTexts[action] || mdsPageManagement.strings.processing;
    }

    // Individual action buttons
    $(document).on('click', '.mds-view-details', function(e) {
        e.preventDefault();
        const pageId = $(this).data('page-id');
        showPageDetails(pageId);
    });

    $(document).on('click', '.mds-scan-page', function(e) {
        e.preventDefault();
        const pageId = $(this).data('page-id');
        scanSinglePage(pageId);
    });

    $(document).on('click', '.mds-repair-page', function(e) {
        e.preventDefault();
        const pageId = $(this).data('page-id');
        repairSinglePage(pageId);
    });

    $(document).on('click', '.mds-configure-page', function(e) {
        e.preventDefault();
        const pageId = $(this).data('page-id');
        configurePageModal(pageId);
    });

    // Show page details in modal
    function showPageDetails(pageId) {
        const modal = $('<div>', {
            title: mdsPageManagement.strings.page_details,
            id: 'mds-page-details-modal'
        });

        modal.dialog({
            modal: true,
            width: 600,
            height: 500,
            resizable: true,
            close: function() {
                $(this).dialog('destroy').remove();
            }
        });

        modal.html('<div class="loading">' + mdsPageManagement.strings.loading + '</div>');

        $.ajax({
            url: mdsPageManagement.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mds_get_page_details',
                page_id: pageId,
                nonce: mdsPageManagement.nonce
            },
            success: function(response) {
                if (response.success) {
                    modal.html(response.data.html);
                } else {
                    modal.html('<div class="error">' + (response.data || mdsPageManagement.strings.failed_to_load) + '</div>');
                }
            },
            error: function() {
                modal.html('<div class="error">' + mdsPageManagement.strings.ajax_error + '</div>');
            }
        });
    }

    // Scan single page
    function scanSinglePage(pageId) {
        const button = $('.mds-scan-page[data-page-id="' + pageId + '"]');
        const originalText = button.html();
        
        button.prop('disabled', true).html('<span class="mds-spinner"></span>');

        $.ajax({
            url: mdsPageManagement.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mds_scan_page',
                page_id: pageId,
                nonce: mdsPageManagement.nonce
            },
            success: function(response) {
                button.prop('disabled', false).html(originalText);
                
                if (response.success) {
                    NotificationModal.show('success',
                        mdsPageManagement.strings.scan_completed,
                        response.data.message
                    );
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    NotificationModal.show('error',
                        mdsPageManagement.strings.scan_failed,
                        response.data || mdsPageManagement.strings.unknown_error
                    );
                }
            },
            error: function() {
                button.prop('disabled', false).html(originalText);
                NotificationModal.show('error',
                    mdsPageManagement.strings.ajax_error,
                    mdsPageManagement.strings.scan_request_failed
                );
            }
        });
    }

    // Repair single page
    function repairSinglePage(pageId) {
        const button = $('.mds-repair-page[data-page-id="' + pageId + '"]');
        const originalText = button.html();
        
        button.prop('disabled', true).html('<span class="mds-spinner"></span>');

        $.ajax({
            url: mdsPageManagement.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mds_repair_page',
                page_id: pageId,
                nonce: mdsPageManagement.nonce
            },
            success: function(response) {
                button.prop('disabled', false).html(originalText);
                
                if (response.success) {
                    NotificationModal.show('success',
                        mdsPageManagement.strings.repair_completed,
                        response.data.message
                    );
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    NotificationModal.show('error',
                        mdsPageManagement.strings.repair_failed,
                        response.data || mdsPageManagement.strings.unknown_error
                    );
                }
            },
            error: function() {
                button.prop('disabled', false).html(originalText);
                NotificationModal.show('error',
                    mdsPageManagement.strings.ajax_error,
                    mdsPageManagement.strings.repair_request_failed
                );
            }
        });
    }

    // Configure page modal
    function configurePageModal(pageId) {
        const modal = $('<div>', {
            title: mdsPageManagement.strings.page_configuration,
            id: 'mds-page-config-modal'
        });

        modal.dialog({
            modal: true,
            width: 500,
            height: 400,
            resizable: true,
            close: function() {
                $(this).dialog('destroy').remove();
            }
        });

        modal.html('<div class="loading">' + mdsPageManagement.strings.loading + '</div>');

        $.ajax({
            url: mdsPageManagement.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mds_get_page_config',
                page_id: pageId,
                nonce: mdsPageManagement.nonce
            },
            success: function(response) {
                if (response.success) {
                    modal.html(response.data.html);
                    
                    // Handle config form submission
                    modal.find('#mds-page-config-form').on('submit', function(e) {
                        e.preventDefault();
                        const formData = $(this).serialize() + '&page_id=' + pageId + '&nonce=' + mdsPageManagement.nonce;
                        
                        $.ajax({
                            url: mdsPageManagement.ajaxUrl,
                            type: 'POST',
                            data: 'action=mds_save_page_config&' + formData,
                            success: function(response) {
                                if (response.success) {
                                    modal.dialog('close');
                                    NotificationModal.show('success',
                                        mdsPageManagement.strings.configuration_saved,
                                        response.data.message
                                    );
                                    setTimeout(function() {
                                        location.reload();
                                    }, 1500);
                                } else {
                                    NotificationModal.show('error',
                                        mdsPageManagement.strings.configuration_failed,
                                        response.data || mdsPageManagement.strings.unknown_error
                                    );
                                }
                            },
                            error: function() {
                                NotificationModal.show('error',
                                    mdsPageManagement.strings.ajax_error,
                                    mdsPageManagement.strings.configuration_request_failed
                                );
                            }
                        });
                    });
                } else {
                    modal.html('<div class="error">' + (response.data || mdsPageManagement.strings.failed_to_load) + '</div>');
                }
            },
            error: function() {
                modal.html('<div class="error">' + mdsPageManagement.strings.ajax_error + '</div>');
            }
        });
    }

    // Status help links
    $(document).on('click', '.mds-status-help-link', function(e) {
        e.preventDefault();
        const pageId = $(this).data('page-id');
        showPageErrorHelp(pageId);
    });

    // Show page error help
    function showPageErrorHelp(pageId) {
        ProgressModal.show(
            mdsPageManagement.strings.scanning_errors,
            mdsPageManagement.strings.analyzing_page_errors
        );

        $.ajax({
            url: mdsPageManagement.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mds_scan_page_errors',
                page_id: pageId,
                nonce: mdsPageManagement.nonce
            },
            success: function(response) {
                ProgressModal.hide();
                
                if (response.success) {
                    const data = response.data;
                    let message = data.message;
                    let details = data.errors || [];
                    
                    NotificationModal.show('info',
                        mdsPageManagement.strings.page_error_analysis,
                        message,
                        details
                    );
                } else {
                    NotificationModal.show('error',
                        mdsPageManagement.strings.error_scan_failed,
                        response.data || mdsPageManagement.strings.unknown_error
                    );
                }
            },
            error: function() {
                ProgressModal.hide();
                NotificationModal.show('error',
                    mdsPageManagement.strings.ajax_error,
                    mdsPageManagement.strings.error_scan_request_failed
                );
            }
        });
    }

    // Utility functions for WordPress compatibility
    window.mdsPageManagement = window.mdsPageManagement || {};
    window.mdsPageManagement.showNotification = NotificationModal.show;
    window.mdsPageManagement.showProgress = ProgressModal.show;
    window.mdsPageManagement.hideProgress = ProgressModal.hide;
});