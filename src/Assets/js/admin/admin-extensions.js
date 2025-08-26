(function ($) {
    'use strict';

    $(function () {
        // Activate license
        $(document).on('click', '.mds-activate-license', function (e) {
            e.preventDefault();
            const form = $(this).closest('.mds-license-form');
            const button = $(this);
            const extension_slug = form.data('extension-slug');
            const license_key = form.find('input[name="license_key"]').val();
            const nonce = form.find('input[name="nonce"]').val();

            button.prop('disabled', true).text('Activating...');

            $.post(ajaxurl, {
                action: 'mds_activate_license',
                extension_slug: extension_slug,
                license_key: license_key,
                nonce: nonce,
            })
                .done(function (response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                        button.prop('disabled', false).text('Activate');
                    }
                })
                .fail(function () {
                    alert('An error occurred.');
                    button.prop('disabled', false).text('Activate');
                });
        });

        // Deactivate license
        $(document).on('click', '.mds-deactivate-license', function (e) {
            e.preventDefault();
            const form = $(this).closest('.mds-license-form');
            const button = $(this);
            const extension_slug = form.data('extension-slug');
            const nonce = form.find('input[name="nonce"]').val();

            button.prop('disabled', true).text('Deactivating...');

            $.post(ajaxurl, {
                action: 'mds_deactivate_license',
                extension_slug: extension_slug,
                nonce: nonce,
            })
                .done(function (response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                        button.prop('disabled', false).text('Deactivate');
                    }
                })
                .fail(function () {
                    alert('An error occurred.');
                    button.prop('disabled', false).text('Deactivate');
                });
        });
    });
})(jQuery);