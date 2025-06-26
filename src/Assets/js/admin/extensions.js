/**
 * Extensions Page JavaScript
 */

(function($) {
    'use strict';

    const ExtensionsPage = {
        
        init: function() {
            this.bindEvents();
            this.initTabs();
        },

        bindEvents: function() {
            // Tab switching
            $('.nav-tab').on('click', this.handleTabClick);
            
            // Extension actions
            $(document).on('click', '.install-extension', this.handleInstallExtension);
            $(document).on('click', '.activate-extension', this.handleActivateExtension);
            $(document).on('click', '.deactivate-extension', this.handleDeactivateExtension);
            $(document).on('click', '.update-extension', this.handleUpdateExtension);
            
            // Check for updates button
            $('#mds-check-updates-btn').on('click', this.handleCheckUpdates);
        },

        initTabs: function() {
            // Show the first tab by default
            const hash = window.location.hash;
            if (hash && $(hash + '-extensions').length) {
                this.showTab(hash.replace('#', ''));
            } else {
                this.showTab('available');
            }
        },

        handleTabClick: function(e) {
            e.preventDefault();
            const tab = $(this).attr('href').replace('#', '');
            ExtensionsPage.showTab(tab);
            
            // Update URL hash
            if (history.pushState) {
                history.pushState(null, null, '#' + tab);
            }
        },

        showTab: function(tab) {
            // Update nav tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $('.nav-tab[href="#' + tab + '"]').addClass('nav-tab-active');
            
            // Show/hide content
            $('.tab-content').removeClass('active');
            $('#' + tab + '-extensions').addClass('active');
        },

        handleInstallExtension: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const extensionId = $button.data('extension-id');
            const extensionName = $button.data('extension-name');
            const isPremium = $button.data('is-premium') === 1;
            
            if (!confirm(mdsExtensions.strings.confirm_install)) {
                return;
            }
            
            ExtensionsPage.setButtonLoading($button, mdsExtensions.strings.installing);
            
            $.ajax({
                url: mdsExtensions.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mds_extensions_install',
                    nonce: mdsExtensions.nonce,
                    extension_id: extensionId,
                    extension_name: extensionName,
                    is_premium: isPremium ? '1' : ''
                },
                success: function(response) {
                    if (response.success) {
                        ExtensionsPage.showNotice(response.data.message || mdsExtensions.strings.extension_installed, 'success');
                        ExtensionsPage.refreshExtensions();
                    } else {
                        ExtensionsPage.showNotice(response.data.message || 'Installation failed', 'error');
                    }
                },
                error: function() {
                    ExtensionsPage.showNotice('Installation failed', 'error');
                },
                complete: function() {
                    ExtensionsPage.setButtonLoading($button, false);
                }
            });
        },

        handleActivateExtension: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const extensionFile = $button.data('extension-file');
            
            ExtensionsPage.setButtonLoading($button, mdsExtensions.strings.activating);
            
            $.ajax({
                url: mdsExtensions.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mds_extensions_activate',
                    nonce: mdsExtensions.nonce,
                    extension_file: extensionFile
                },
                success: function(response) {
                    if (response.success) {
                        ExtensionsPage.showNotice(response.data.message || mdsExtensions.strings.extension_activated, 'success');
                        ExtensionsPage.refreshInstalledExtensions();
                    } else {
                        ExtensionsPage.showNotice(response.data.message || 'Activation failed', 'error');
                    }
                },
                error: function() {
                    ExtensionsPage.showNotice('Activation failed', 'error');
                },
                complete: function() {
                    ExtensionsPage.setButtonLoading($button, false);
                }
            });
        },

        handleDeactivateExtension: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const extensionFile = $button.data('extension-file');
            
            ExtensionsPage.setButtonLoading($button, mdsExtensions.strings.deactivating);
            
            $.ajax({
                url: mdsExtensions.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mds_extensions_deactivate',
                    nonce: mdsExtensions.nonce,
                    extension_file: extensionFile
                },
                success: function(response) {
                    if (response.success) {
                        ExtensionsPage.showNotice(response.data.message || mdsExtensions.strings.extension_deactivated, 'success');
                        ExtensionsPage.refreshInstalledExtensions();
                    } else {
                        ExtensionsPage.showNotice(response.data.message || 'Deactivation failed', 'error');
                    }
                },
                error: function() {
                    ExtensionsPage.showNotice('Deactivation failed', 'error');
                },
                complete: function() {
                    ExtensionsPage.setButtonLoading($button, false);
                }
            });
        },

        handleUpdateExtension: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const extensionId = $button.data('extension-id');
            const extensionName = $button.data('extension-name');
            
            if (!confirm(mdsExtensions.strings.confirm_update)) {
                return;
            }
            
            ExtensionsPage.setButtonLoading($button, mdsExtensions.strings.updating);
            
            $.ajax({
                url: mdsExtensions.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mds_extensions_update',
                    nonce: mdsExtensions.nonce,
                    extension_id: extensionId,
                    extension_name: extensionName
                },
                success: function(response) {
                    if (response.success) {
                        ExtensionsPage.showNotice(response.data.message || mdsExtensions.strings.extension_updated, 'success');
                        ExtensionsPage.refreshExtensions();
                    } else {
                        ExtensionsPage.showNotice(response.data.message || 'Update failed', 'error');
                    }
                },
                error: function() {
                    ExtensionsPage.showNotice('Update failed', 'error');
                },
                complete: function() {
                    ExtensionsPage.setButtonLoading($button, false);
                }
            });
        },

        handleCheckUpdates: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            ExtensionsPage.setButtonLoading($button, mdsExtensions.strings.checking_updates);
            
            $.ajax({
                url: mdsExtensions.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mds_extensions_check_updates',
                    nonce: mdsExtensions.nonce
                },
                success: function(response) {
                    if (response.success) {
                        ExtensionsPage.showNotice(response.data.message, 'success');
                        if (response.data.updates_available > 0) {
                            ExtensionsPage.refreshExtensions();
                        }
                    } else {
                        ExtensionsPage.showNotice(response.data.message || 'Check failed', 'error');
                    }
                },
                error: function() {
                    ExtensionsPage.showNotice('Check for updates failed', 'error');
                },
                complete: function() {
                    ExtensionsPage.setButtonLoading($button, false);
                }
            });
        },

        refreshExtensions: function() {
            $.ajax({
                url: mdsExtensions.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mds_extensions_refresh',
                    nonce: mdsExtensions.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the page to show updated data
                        window.location.reload();
                    }
                }
            });
        },

        refreshInstalledExtensions: function() {
            // For now, just reload the page
            // In the future, we could update just the installed extensions section
            window.location.reload();
        },

        setButtonLoading: function($button, loadingText) {
            if (loadingText) {
                $button.data('original-text', $button.text());
                $button.text(loadingText);
                $button.prop('disabled', true);
                $button.closest('.extension-card').addClass('loading');
            } else {
                $button.text($button.data('original-text') || $button.text());
                $button.prop('disabled', false);
                $button.closest('.extension-card').removeClass('loading');
            }
        },

        showNotice: function(message, type) {
            type = type || 'info';
            
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('#mds-extensions-notices').empty().append($notice);
            
            // Auto-dismiss success notices after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 5000);
            }
            
            // Scroll to top to show the notice
            $('html, body').animate({
                scrollTop: $('.wrap').offset().top - 50
            }, 300);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ExtensionsPage.init();
    });

})(jQuery);