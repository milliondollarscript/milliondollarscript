<?php
/**
 * Canonical MDS3 settings plus MDS2 upgrade-compatible settings.
 *
 * @package MillionDollarScript\V3\Settings
 */

namespace MillionDollarScript\V3\Settings;

use MillionDollarScript\V3\Support\ReleaseProfile;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsSchema {
    public const CLASSIFICATION_ACTIVE = 'active';
    public const CLASSIFICATION_COMPATIBILITY = 'compatibility';
    public const CLASSIFICATION_DEFERRED = 'deferred';
    public const CLASSIFICATION_EXTENSION_OWNED = 'extension-owned';

    public static function groups() {
        return [
            __('General', 'million-dollar-script') => [
                ['key' => 'currency', 'label' => __('Default Currency', 'million-dollar-script'), 'type' => 'text', 'default' => 'USD', 'description' => __('Used for standalone/manual checkout. WooCommerce or another payment provider can lock this to the store currency.', 'million-dollar-script')],
                ['key' => 'currency-symbol', 'label' => __('Currency Symbol', 'million-dollar-script'), 'type' => 'text', 'default' => '$', 'description' => __('Used with the default currency when no payment provider controls display currency.', 'million-dollar-script')],
                ['key' => 'endpoint', 'label' => __('Legacy Public Endpoint Slug', 'million-dollar-script'), 'type' => 'text', 'default' => 'milliondollarscript', 'description' => __('Preserved for older Million Dollar Script 2 integrations. Current sites use pages, shortcodes, blocks, AJAX, and REST endpoints instead.', 'million-dollar-script')],
                ['key' => 'theme_mode', 'label' => __('Theme Mode', 'million-dollar-script'), 'type' => 'select', 'default' => 'light', 'options' => ['system' => __('System', 'million-dollar-script'), 'light' => __('Light', 'million-dollar-script'), 'dark' => __('Dark', 'million-dollar-script')]],
                ['key' => 'payment_provider', 'label' => __('Payment Provider', 'million-dollar-script'), 'type' => 'select', 'default' => 'standalone', 'options' => self::payment_provider_options(), 'description' => __('Extensions register available checkout adapters here. The core plugin keeps checkout routing provider-neutral.', 'million-dollar-script'), 'docs' => 'setup'],
            ],
            __('URLs & Redirects', 'million-dollar-script') => [
                ['key' => 'account-page', 'label' => __('Account Page URL', 'million-dollar-script'), 'type' => 'url', 'default' => ''],
                ['key' => 'register-page', 'label' => __('Register Page URL', 'million-dollar-script'), 'type' => 'url', 'default' => ''],
                ['key' => 'login-page', 'label' => __('Login Page URL', 'million-dollar-script'), 'type' => 'url', 'default' => ''],
                ['key' => 'forgot-password-page', 'label' => __('Forgot Password Page URL', 'million-dollar-script'), 'type' => 'url', 'default' => ''],
                ['key' => 'checkout-url', 'label' => __('Checkout URL', 'million-dollar-script'), 'type' => 'url', 'default' => ''],
                ['key' => 'thank-you-page', 'label' => __('Thank You Page URL', 'million-dollar-script'), 'type' => 'url', 'default' => ''],
                ['key' => 'confirm-orders', 'label' => __('Confirm Orders Page', 'million-dollar-script'), 'type' => 'select', 'default' => 'yes', 'options' => ['yes', 'no']],
                ['key' => 'mds-pixel-template', 'label' => __('Enable Individual Advertiser Pages', 'million-dollar-script'), 'type' => 'select', 'default' => 'no', 'options' => ['yes', 'no'], 'description' => __('Publishes a stable page for each active placement. Draft, unpaid, cancelled, expired, and archived placements remain private.', 'million-dollar-script')],
                ['key' => 'exclude-from-search', 'label' => __('Exclude Advertiser Pages From Search', 'million-dollar-script'), 'type' => 'select', 'default' => 'no', 'options' => ['yes', 'no'], 'description' => __('Adds noindex and removes advertiser pages from WordPress search and XML sitemaps while leaving direct links available.', 'million-dollar-script')],
                ['key' => 'mds-pixel-base', 'label' => __('Advertiser Page URL Base', 'million-dollar-script'), 'type' => 'text', 'default' => 'mds-pixel', 'description' => __('The first URL segment, for example mds-pixel in /mds-pixel/example/. Previous bases are retained for exact permanent redirects.', 'million-dollar-script')],
                ['key' => 'mds-pixel-slug-structure', 'label' => __('Advertiser Page Slug Pattern', 'million-dollar-script'), 'type' => 'text', 'default' => '%placement_id%', 'description' => __('Supports %placement_id%, %pixel_id%, %order_id%, %grid%, %title%, and %text%. Account-name tokens from Million Dollar Script 2 resolve to blank for privacy.', 'million-dollar-script')],
                ['key' => 'advertiser-page-popup-link', 'label' => __('Link Popups To Advertiser Pages', 'million-dollar-script'), 'type' => 'select', 'default' => 'yes', 'options' => ['yes', 'no'], 'description' => __('Adds a configurable full-page link to the built-in occupied-block popup. This does not change customer input fields.', 'million-dollar-script')],
                ['key' => 'advertiser-page-popup-label', 'label' => __('Advertiser Page Link Label', 'million-dollar-script'), 'type' => 'text', 'default' => __('View advertiser page', 'million-dollar-script')],
                ['key' => 'advertiser-page-link-target', 'label' => __('Advertiser Page Destination Target', 'million-dollar-script'), 'type' => 'select', 'default' => '_self', 'options' => ['_self' => __('Same tab', 'million-dollar-script'), '_blank' => __('New tab', 'million-dollar-script')]],
                ['key' => 'enable-cloaking', 'label' => __('URL Cloaking', 'million-dollar-script'), 'type' => 'select', 'default' => 'YES', 'options' => ['YES', 'NO']],
                ['key' => 'validate-link', 'label' => __('Validate Advertiser URLs', 'million-dollar-script'), 'type' => 'select', 'default' => 'NO', 'options' => ['YES', 'NO']],
                ['key' => 'redirect-switch', 'label' => __('Redirect Available Blocks', 'million-dollar-script'), 'type' => 'select', 'default' => 'NO', 'options' => ['YES', 'NO']],
                ['key' => 'redirect-url', 'label' => __('Available Block Redirect URL', 'million-dollar-script'), 'type' => 'url', 'default' => 'https://www.example.com'],
                ['key' => 'link-target', 'label' => __('Advertiser Link Target', 'million-dollar-script'), 'type' => 'select', 'default' => '_blank', 'options' => ['_blank', '_self']],
            ],
            __('Display & Interaction', 'million-dollar-script') => [
                ['key' => 'background_color', 'label' => __('Primary Background Color', 'million-dollar-script'), 'type' => 'color', 'default' => '#ffffff'],
                ['key' => 'primary_color', 'label' => __('Secondary Background Color', 'million-dollar-script'), 'type' => 'color', 'default' => '#f8fafc'],
                ['key' => 'text_color', 'label' => __('Primary Text Color', 'million-dollar-script'), 'type' => 'color', 'default' => '#111827'],
                ['key' => 'button-color', 'label' => __('Primary Button Background', 'million-dollar-script'), 'type' => 'color', 'default' => '#2563eb'],
                ['key' => 'button_text_color', 'label' => __('Primary Button Text', 'million-dollar-script'), 'type' => 'color', 'default' => '#ffffff'],
                ['key' => 'display-pixel-background', 'label' => __('Display Pixel Background', 'million-dollar-script'), 'type' => 'select', 'default' => 'yes', 'options' => ['yes', 'no']],
                ['key' => 'use-ajax', 'label' => __('Pixel Selection Method', 'million-dollar-script'), 'type' => 'select', 'default' => 'yes', 'options' => ['yes', 'no']],
                ['key' => 'block-selection-mode', 'label' => __('Multi-block Selection', 'million-dollar-script'), 'type' => 'select', 'default' => 'YES', 'options' => ['YES' => __('Enabled', 'million-dollar-script'), 'NO' => __('Disabled', 'million-dollar-script')]],
                ['key' => 'selection-adjacency-mode', 'label' => __('Selection Shape Rule', 'million-dollar-script'), 'type' => 'select', 'default' => 'ADJACENT', 'options' => ['ADJACENT' => __('Adjacency required', 'million-dollar-script'), 'RECTANGLE' => __('Square or rectangle required', 'million-dollar-script'), 'NONE' => __('None - blocks can be anywhere', 'million-dollar-script')]],
                ['key' => 'show_uploaded_image_in_advanced_mode', 'label' => __('Show Uploaded Image In Advanced Mode', 'million-dollar-script'), 'type' => 'select', 'default' => 'yes', 'options' => ['yes', 'no']],
                ['key' => 'resize', 'label' => __('Resize Uploaded Pixels Automatically', 'million-dollar-script'), 'type' => 'select', 'default' => 'yes', 'options' => ['yes', 'no']],
                ['key' => 'manage-pixels-grid-dropdown', 'label' => __('Show Grid Dropdown On Manage Pixels', 'million-dollar-script'), 'type' => 'select', 'default' => 'yes', 'options' => ['yes', 'no']],
                ['key' => 'invert-pixels', 'label' => __('Invert Pixel Selection', 'million-dollar-script'), 'type' => 'select', 'default' => 'no', 'options' => ['yes', 'no']],
                ['key' => 'stats-display-mode', 'label' => __('Stats Display Mode', 'million-dollar-script'), 'type' => 'select', 'default' => 'PIXELS', 'options' => ['PIXELS' => __('Pixels - show sold/available pixels', 'million-dollar-script'), 'BLOCKS' => __('Blocks - show sold/available blocks', 'million-dollar-script')]],
                ['key' => 'show-grid-view-controls', 'label' => __('Show Grid View Controls', 'million-dollar-script'), 'type' => 'select', 'default' => 'no', 'options' => ['yes', 'no'], 'description' => __('Adds optional View all and View images buttons below public grids. Grids fit the full board by default.', 'million-dollar-script')],
                ['key' => 'enable-mouseover', 'label' => __('Show Block Popup', 'million-dollar-script'), 'type' => 'select', 'default' => 'yes', 'options' => ['yes', 'no']],
                ['key' => 'tooltip-trigger', 'label' => __('Popup Interaction Method', 'million-dollar-script'), 'type' => 'select', 'default' => 'mouseenter', 'options' => ['mouseenter', 'click']],
                ['key' => 'max-popup-size', 'label' => __('Max Popup Size', 'million-dollar-script'), 'type' => 'number', 'default' => 320],
                ['key' => 'max-image-size', 'label' => __('Max Popup Image Size', 'million-dollar-script'), 'type' => 'number', 'default' => 300],
                [
                    'key' => 'popup-template',
                    'label' => __('Popup Layout Template', 'million-dollar-script'),
                    'type' => 'textarea',
                    'default' => '',
                    'wide' => true,
                    /* translators: listed percent-delimited values are literal popup template placeholders. */
                    'description' => __('Optional HTML layout for occupied block popups. Supports %text%, %url%, %image%, %alt_text%, %advertiser_page_url%, and %advertiser_page_link%. This changes presentation only; it does not add, remove, or require customer input fields. Leave blank to use the built-in layout.', 'million-dollar-script'),
                ],
            ],
            __('Login', 'million-dollar-script') => [
                ['key' => 'login-redirect', 'label' => __('Login Redirect URL', 'million-dollar-script'), 'type' => 'url', 'default' => ''],
                ['key' => 'logout-redirect', 'label' => __('Logout Redirect URL', 'million-dollar-script'), 'type' => 'url', 'default' => ''],
                ['key' => 'login-header-image', 'label' => __('Login Header Image', 'million-dollar-script'), 'type' => 'image', 'default' => 0],
                ['key' => 'login-header-text', 'label' => __('Login Header Text', 'million-dollar-script'), 'type' => 'text', 'default' => ''],
            ],
            __('Orders & Uploads', 'million-dollar-script') => [
                ['key' => 'accounts-optional', 'label' => __('Allow Guest Orders', 'million-dollar-script'), 'type' => 'select', 'default' => 'yes', 'options' => ['yes', 'no']],
                ['key' => 'expire-orders', 'label' => __('Expire Orders', 'million-dollar-script'), 'type' => 'select', 'default' => 'yes', 'options' => ['yes', 'no'], 'description' => __('When enabled, Million Dollar Script cleans up unpaid, expired, cancelled, or denied orders after the timing rules below.', 'million-dollar-script')],
                ['key' => 'auto-approve', 'label' => __('Auto-complete Manual Payments', 'million-dollar-script'), 'type' => 'select', 'default' => 'no', 'options' => ['yes', 'no'], 'description' => __('Leave this disabled unless you intentionally trust manual checkout reservations to become active without a gateway payment confirmation.', 'million-dollar-script')],
                ['key' => 'order-locking', 'label' => __('Order Locking', 'million-dollar-script'), 'type' => 'select', 'default' => 'yes', 'options' => ['yes', 'no']],
                ['key' => 'minutes-renew', 'label' => __('Minutes To Keep Expired Orders', 'million-dollar-script'), 'type' => 'number', 'default' => 43200, 'description' => __('Use -1 for immediate cleanup after an order expires, or 0 to retain expired order records.', 'million-dollar-script')],
                ['key' => 'minutes-confirmed', 'label' => __('Minutes To Keep Confirmed Orders', 'million-dollar-script'), 'type' => 'number', 'default' => 43200, 'description' => __('Applies to paid orders after their placement duration ends.', 'million-dollar-script')],
                ['key' => 'minutes-unconfirmed', 'label' => __('Minutes To Keep Unconfirmed Orders', 'million-dollar-script'), 'type' => 'number', 'default' => 60, 'description' => __('Controls how long unpaid reservations remain available for customers to finish checkout.', 'million-dollar-script')],
                ['key' => 'minutes-cancel', 'label' => __('Minutes To Keep Cancelled Orders', 'million-dollar-script'), 'type' => 'number', 'default' => 43200, 'description' => __('Use this retention window for cancelled or denied orders kept for admin reference.', 'million-dollar-script')],
                [
                    'key' => 'text-optional',
                    'label' => __('Popup Text Field', 'million-dollar-script'),
                    'type' => 'select',
                    'default' => 'no',
                    'options' => [
                        'no' => __('Required', 'million-dollar-script'),
                        'yes' => __('Optional', 'million-dollar-script'),
                        'hidden' => __('Hidden', 'million-dollar-script'),
                    ],
                    'description' => __('Controls whether customers must enter popup text, may leave it blank, or do not see the field. Hiding it preserves text already saved on existing placements.', 'million-dollar-script'),
                ],
                ['key' => 'popup-rich-text', 'label' => __('Use Rich Text Popup Field', 'million-dollar-script'), 'type' => 'select', 'default' => 'no', 'options' => ['yes', 'no'], 'description' => __('Adds a simple formatting toolbar for customer popup text. Only basic tags are saved: paragraphs, line breaks, bold, and italic.', 'million-dollar-script')],
                [
                    'key' => 'url-optional',
                    'label' => __('Advertiser URL Field', 'million-dollar-script'),
                    'type' => 'select',
                    'default' => 'no',
                    'options' => [
                        'no' => __('Required', 'million-dollar-script'),
                        'yes' => __('Optional', 'million-dollar-script'),
                        'hidden' => __('Hidden', 'million-dollar-script'),
                    ],
                    'description' => __('Controls whether customers must enter a destination URL, may leave it blank, or do not see the field. Hiding it preserves URLs already saved on existing placements.', 'million-dollar-script'),
                ],
                ['key' => 'image-optional', 'label' => __('Make Image Optional', 'million-dollar-script'), 'type' => 'select', 'default' => 'no', 'options' => ['yes', 'no']],
                ['key' => 'max-upload-width', 'label' => __('Max Upload Width', 'million-dollar-script'), 'type' => 'number', 'default' => 0, 'description' => __('Maximum uploaded artwork width in pixels. Use 0 for no explicit plugin limit; the server upload limit still applies.', 'million-dollar-script')],
                ['key' => 'max-upload-height', 'label' => __('Max Upload Height', 'million-dollar-script'), 'type' => 'number', 'default' => 0, 'description' => __('Maximum uploaded artwork height in pixels. Use 0 for no explicit plugin limit; smaller limits are safer on shared hosting.', 'million-dollar-script')],
            ],
            __('Order Emails', 'million-dollar-script') => self::order_email_fields(),
            __('System', 'million-dollar-script') => [
                ['key' => 'extension_server_url', 'label' => __('Extension Server URL', 'million-dollar-script'), 'type' => 'url', 'default' => 'https://milliondollarscript.com', 'description' => __('Used for extension catalog, license, and update checks. Local development can point this to the local extension server.', 'million-dollar-script'), 'docs' => 'troubleshooting'],
                ['key' => 'extension_portal_auto_accounts', 'label' => __('Create Extension Portal Accounts', 'million-dollar-script'), 'type' => 'select', 'default' => 'no', 'options' => ['yes', 'no'], 'description' => __('Leave disabled unless the extension server is configured to provision portal users for this site.', 'million-dollar-script')],
                ['key' => 'disable_version_analytics', 'label' => __('Disable Anonymous Version Analytics', 'million-dollar-script'), 'type' => 'select', 'default' => 'no', 'options' => ['yes', 'no']],
                ['key' => 'delete_data_on_uninstall', 'label' => __('Delete Data On Uninstall', 'million-dollar-script'), 'type' => 'select', 'default' => 'no', 'options' => ['yes', 'no']],
                ['key' => 'updates', 'label' => __('Plugin Updates Channel', 'million-dollar-script'), 'type' => 'select', 'default' => ReleaseProfile::update_channel('main'), 'options' => ['main', 'beta', 'alpha']],
                ['key' => 'log-enable', 'label' => __('Enable Logging', 'million-dollar-script'), 'type' => 'select', 'default' => 'no', 'options' => ['yes', 'no']],
                ['key' => 'update-language', 'label' => __('Update Language Files', 'million-dollar-script'), 'type' => 'select', 'default' => 'yes', 'options' => ['yes', 'no']],
                ['key' => 'transliterate-slugs', 'label' => __('Transliterate Slugs', 'million-dollar-script'), 'type' => 'select', 'default' => 'yes', 'options' => ['yes', 'no']],
            ],
            __('Rendering', 'million-dollar-script') => [],
        ];
    }

    public static function field_classification($key) {
        $key = sanitize_key((string) $key);
        $classifications = self::field_classifications();

        return $classifications[$key] ?? self::CLASSIFICATION_ACTIVE;
    }

    public static function is_admin_visible($key) {
        return self::CLASSIFICATION_ACTIVE === self::field_classification($key);
    }

    public static function hidden_admin_fields() {
        $fields = [];
        foreach (self::fields() as $key => $field) {
            if (self::is_admin_visible($key)) {
                continue;
            }

            $classification = self::field_classification($key);
            $field['classification'] = $classification;
            $field['classification_label'] = self::classification_label($classification);
            $field['classification_note'] = self::classification_note($classification);
            $fields[$key] = $field;
        }

        return $fields;
    }

    public static function classification_label($classification) {
        $labels = [
            self::CLASSIFICATION_ACTIVE => __('Active setting', 'million-dollar-script'),
            self::CLASSIFICATION_COMPATIBILITY => __('Compatibility only', 'million-dollar-script'),
            self::CLASSIFICATION_DEFERRED => __('Planned setting', 'million-dollar-script'),
            self::CLASSIFICATION_EXTENSION_OWNED => __('Extension-owned', 'million-dollar-script'),
        ];

        return $labels[$classification] ?? $labels[self::CLASSIFICATION_ACTIVE];
    }

    public static function classification_note($classification) {
        $notes = [
            self::CLASSIFICATION_ACTIVE => __('This setting is used by the current Million Dollar Script runtime.', 'million-dollar-script'),
            self::CLASSIFICATION_COMPATIBILITY => __('This value is kept for Million Dollar Script 2 migration, import/export, and custom code compatibility, but it is not shown as a live control in the main settings form.', 'million-dollar-script'),
            self::CLASSIFICATION_DEFERRED => __('This setting is reserved for planned functionality and is preserved during import/export, but it is not shown as a live control until the feature is implemented.', 'million-dollar-script'),
            self::CLASSIFICATION_EXTENSION_OWNED => __('This setting belongs to an extension and should be managed by that extension when it is active.', 'million-dollar-script'),
        ];

        return $notes[$classification] ?? $notes[self::CLASSIFICATION_ACTIVE];
    }

    private static function field_classifications() {
        return [
            'endpoint' => self::CLASSIFICATION_COMPATIBILITY,
            'register-page' => self::CLASSIFICATION_COMPATIBILITY,
            'forgot-password-page' => self::CLASSIFICATION_COMPATIBILITY,
            'confirm-orders' => self::CLASSIFICATION_COMPATIBILITY,
            'display-pixel-background' => self::CLASSIFICATION_COMPATIBILITY,
            'use-ajax' => self::CLASSIFICATION_COMPATIBILITY,
            'show_uploaded_image_in_advanced_mode' => self::CLASSIFICATION_COMPATIBILITY,
            'resize' => self::CLASSIFICATION_DEFERRED,
            'manage-pixels-grid-dropdown' => self::CLASSIFICATION_COMPATIBILITY,
            'invert-pixels' => self::CLASSIFICATION_COMPATIBILITY,
            'validate-link' => self::CLASSIFICATION_DEFERRED,
            'redirect-switch' => self::CLASSIFICATION_DEFERRED,
            'redirect-url' => self::CLASSIFICATION_DEFERRED,
            'order-locking' => self::CLASSIFICATION_DEFERRED,
            'image-optional' => self::CLASSIFICATION_DEFERRED,
            'login-redirect' => self::CLASSIFICATION_COMPATIBILITY,
            'logout-redirect' => self::CLASSIFICATION_COMPATIBILITY,
            'login-header-image' => self::CLASSIFICATION_COMPATIBILITY,
            'login-header-text' => self::CLASSIFICATION_COMPATIBILITY,
            'extension_portal_auto_accounts' => self::CLASSIFICATION_DEFERRED,
            'log-enable' => self::CLASSIFICATION_DEFERRED,
            'update-language' => self::CLASSIFICATION_COMPATIBILITY,
            'transliterate-slugs' => self::CLASSIFICATION_COMPATIBILITY,
        ];
    }

    private static function order_email_fields() {
        return [
            self::email_toggle('email-user-order-confirmed', __('Email Customers When Payment Is Requested', 'million-dollar-script'), __('Payment Requested', 'million-dollar-script')),
            self::email_toggle('email-admin-order-confirmed', __('Email Admin When Payment Is Requested', 'million-dollar-script'), __('Payment Requested', 'million-dollar-script')),
            self::email_subject('order-confirmed-subject', __('Payment Requested Subject', 'million-dollar-script'), __('Payment Requested', 'million-dollar-script'), __('Payment Requested', 'million-dollar-script')),
            self::email_content('order-confirmed-content', __('Payment Requested Message', 'million-dollar-script'), __('Payment Requested', 'million-dollar-script'), self::default_order_confirmed_content()),

            self::email_toggle('email-user-order-completed', __('Email Customers When Orders Are Paid', 'million-dollar-script'), __('Order Paid', 'million-dollar-script')),
            self::email_toggle('email-admin-order-completed', __('Email Admin When Orders Are Paid', 'million-dollar-script'), __('Order Paid', 'million-dollar-script')),
            self::email_subject('order-completed-subject', __('Order Paid Subject', 'million-dollar-script'), __('Order Paid', 'million-dollar-script'), __('Order Paid', 'million-dollar-script')),
            self::email_content('order-completed-content', __('Order Paid Message', 'million-dollar-script'), __('Order Paid', 'million-dollar-script'), self::default_order_completed_content()),

            self::email_toggle('email-user-order-completed-renewal', __('Email Customers When Renewals Are Paid', 'million-dollar-script'), __('Renewal Paid', 'million-dollar-script')),
            self::email_toggle('email-admin-order-completed-renewal', __('Email Admin When Renewals Are Paid', 'million-dollar-script'), __('Renewal Paid', 'million-dollar-script')),
            self::email_subject('order-completed-renewal-subject', __('Renewal Paid Subject', 'million-dollar-script'), __('Renewal Paid', 'million-dollar-script'), __('Renewal Paid', 'million-dollar-script')),
            self::email_content('order-completed-renewal-content', __('Renewal Paid Message', 'million-dollar-script'), __('Renewal Paid', 'million-dollar-script'), self::default_order_completed_renewal_content()),

            self::email_toggle('email-user-order-expired', __('Email Customers When Orders Expire', 'million-dollar-script'), __('Order Expired', 'million-dollar-script')),
            self::email_toggle('email-admin-order-expired', __('Email Admin When Orders Expire', 'million-dollar-script'), __('Order Expired', 'million-dollar-script')),
            self::email_subject('order-expired-subject', __('Order Expired Subject', 'million-dollar-script'), __('Order Expired', 'million-dollar-script'), __('Order Expired', 'million-dollar-script')),
            self::email_content('order-expired-content', __('Order Expired Message', 'million-dollar-script'), __('Order Expired', 'million-dollar-script'), self::default_order_expired_content()),

            self::email_toggle('email-user-order-denied', __('Email Customers When Orders Are Denied', 'million-dollar-script'), __('Order Denied', 'million-dollar-script')),
            self::email_toggle('email-admin-order-denied', __('Email Admin When Orders Are Denied', 'million-dollar-script'), __('Order Denied', 'million-dollar-script')),
            self::email_subject('order-denied-subject', __('Order Denied Subject', 'million-dollar-script'), __('Order Denied', 'million-dollar-script'), __('Order Denied', 'million-dollar-script')),
            self::email_content('order-denied-content', __('Order Denied Message', 'million-dollar-script'), __('Order Denied', 'million-dollar-script'), self::default_order_denied_content()),

            ['key' => 'email-admin-publish-notify', 'label' => __('Email Admin When Placements Are Published', 'million-dollar-script'), 'type' => 'select', 'default' => 'no', 'options' => ['yes', 'no'], 'section' => __('Placement Published', 'million-dollar-script'), 'docs' => 'emails'],
            self::email_subject('order-published-subject', __('Placement Published Subject', 'million-dollar-script'), __('Placement Published', 'million-dollar-script'), __('Placement Published', 'million-dollar-script')),
            self::email_content('order-published-content', __('Placement Published Message', 'million-dollar-script'), __('Placement Published', 'million-dollar-script'), self::default_order_published_content()),

            self::email_toggle('email-user-renewal-reminder', __('Email Customers Renewal Reminders', 'million-dollar-script'), __('Renewal Reminders', 'million-dollar-script')),
            self::email_toggle('email-admin-renewal-reminder', __('Email Admin Renewal Reminders', 'million-dollar-script'), __('Renewal Reminders', 'million-dollar-script')),
            ['key' => 'renewal-reminder-days-1', 'label' => __('First Renewal Reminder Days', 'million-dollar-script'), 'type' => 'number', 'default' => 7, 'section' => __('Renewal Reminders', 'million-dollar-script'), 'docs' => 'emails'],
            ['key' => 'renewal-reminder-days-2', 'label' => __('Second Renewal Reminder Days', 'million-dollar-script'), 'type' => 'number', 'default' => 3, 'section' => __('Renewal Reminders', 'million-dollar-script'), 'docs' => 'emails'],
            ['key' => 'renewal-reminder-days-3', 'label' => __('Third Renewal Reminder Days', 'million-dollar-script'), 'type' => 'number', 'default' => 1, 'section' => __('Renewal Reminders', 'million-dollar-script'), 'docs' => 'emails'],
            self::email_subject('renewal-reminder-subject', __('Renewal Reminder Subject', 'million-dollar-script'), __('Renewal Reminders', 'million-dollar-script'), __('Your Order Will Expire Soon', 'million-dollar-script')),
            self::email_content('renewal-reminder-content', __('Renewal Reminder Message', 'million-dollar-script'), __('Renewal Reminders', 'million-dollar-script'), self::default_renewal_reminder_content()),
        ];
    }

    private static function email_toggle($key, $label, $section) {
        return ['key' => $key, 'label' => $label, 'type' => 'select', 'default' => 'yes', 'options' => ['yes', 'no'], 'section' => $section, 'description' => __('Controls whether this email is sent through direct WordPress mail.', 'million-dollar-script'), 'docs' => 'emails'];
    }

    private static function email_subject($key, $label, $section, $default) {
        return ['key' => $key, 'label' => $label, 'type' => 'text', 'default' => $default, 'section' => $section, 'docs' => 'emails'];
    }

    private static function email_content($key, $label, $section, $default) {
        return ['key' => $key, 'label' => $label, 'type' => 'editor', 'default' => $default, 'section' => $section, 'wide' => true, 'docs' => 'emails'];
    }

    public static function fields() {
        $fields = [];
        foreach (self::groups() as $group) {
            foreach ($group as $field) {
                $fields[$field['key']] = $field;
            }
        }
        return $fields;
    }

    public static function payment_provider_options() {
        $options = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/payment/provider/options', [
            'standalone' => __('Standalone/manual checkout', 'million-dollar-script'),
        ]);

        return is_array($options) ? $options : ['standalone' => __('Standalone/manual checkout', 'million-dollar-script')];
    }

    public static function defaults() {
        $defaults = [];
        foreach (self::fields() as $key => $field) {
            $defaults[$key] = $field['default'] ?? '';
        }

        return $defaults;
    }

    public static function help($key) {
        $help = [
            'currency' => __('Three-letter currency code used for standalone orders. When a payment provider owns currency, this follows the provider store currency.', 'million-dollar-script'),
            'currency-symbol' => __('Currency symbol shown in Million Dollar Script screens. When a payment provider owns currency, this follows the provider store symbol.', 'million-dollar-script'),
            'endpoint' => __('Legacy endpoint slug preserved for older custom integrations. Current Million Dollar Script pages and API routes do not use this value.', 'million-dollar-script'),
            'theme_mode' => __('Controls whether Million Dollar Script frontend views and admin screens follow the visitor system theme or force a light/dark style.', 'million-dollar-script'),
            'payment_provider' => __('Selects the checkout adapter used by Million Dollar Script and monetization extensions. Payment providers are installed as extensions.', 'million-dollar-script'),
            'account-page' => __('Page URL customers use to view their Million Dollar Script account area.', 'million-dollar-script'),
            'register-page' => __('Page URL customers use to create an account.', 'million-dollar-script'),
            'login-page' => __('Page URL customers use to log in.', 'million-dollar-script'),
            'forgot-password-page' => __('Page URL customers use to reset a password.', 'million-dollar-script'),
            /* translators: %AMOUNT%, %CURRENCY%, %QUANTITY%, %ORDERID, %USERID%, %GRID%, and %PIXELID% are literal checkout URL placeholders. */
            'checkout-url' => __('Standalone checkout URL used when no payment provider is handling payment. Supports legacy placeholder tokens: %AMOUNT%, %CURRENCY%, %QUANTITY%, %ORDERID, %USERID%, %GRID%, and %PIXELID%.', 'million-dollar-script'),
            'thank-you-page' => __('Page URL shown after checkout or order confirmation.', 'million-dollar-script'),
            'confirm-orders' => __('Requires customers to confirm an order before it moves forward.', 'million-dollar-script'),
            'mds-pixel-template' => __('Publishes privacy-safe individual pages only for active advertiser placements.', 'million-dollar-script'),
            'exclude-from-search' => __('Controls advertiser-page indexing, WordPress search, and XML sitemap inclusion.', 'million-dollar-script'),
            'mds-pixel-base' => __('Controls the public advertiser-page base and retains bounded redirect history.', 'million-dollar-script'),
            'mds-pixel-slug-structure' => __('Builds advertiser page slugs without exposing WordPress account names.', 'million-dollar-script'),
            'advertiser-page-popup-link' => __('Adds a full-page action to built-in placement popups when public advertiser pages are enabled.', 'million-dollar-script'),
            'advertiser-page-popup-label' => __('Text shown for the advertiser-page action in built-in popups.', 'million-dollar-script'),
            'advertiser-page-link-target' => __('Controls whether advertiser page destinations open in the same tab or a new tab.', 'million-dollar-script'),
            'enable-cloaking' => __('Choose Yes to send grid clicks directly to advertiser URLs. Choose No to route clicks through the Million Dollar Script redirect endpoint so click handling and compatible analytics can run first.', 'million-dollar-script'),
            'validate-link' => __('Checks advertiser URLs before accepting them.', 'million-dollar-script'),
            'redirect-switch' => __('Sends visitors who click available grid blocks to the configured redirect URL.', 'million-dollar-script'),
            'redirect-url' => __('Destination URL for available grid blocks when redirects are enabled.', 'million-dollar-script'),
            'link-target' => __('Browser target used when visitors open advertiser links.', 'million-dollar-script'),
            'background_color' => __('Main background color for Million Dollar Script frontend views.', 'million-dollar-script'),
            'primary_color' => __('Secondary surface color used by Million Dollar Script frontend panels and controls.', 'million-dollar-script'),
            'text_color' => __('Primary text color used by Million Dollar Script frontend views.', 'million-dollar-script'),
            'button-color' => __('Background color for primary Million Dollar Script buttons.', 'million-dollar-script'),
            'button_text_color' => __('Text color for primary Million Dollar Script buttons.', 'million-dollar-script'),
            'display-pixel-background' => __('Shows the configured background behind grid pixels.', 'million-dollar-script'),
            'use-ajax' => __('Uses AJAX for block selection instead of full page refreshes.', 'million-dollar-script'),
            'show_uploaded_image_in_advanced_mode' => __('Shows the customer upload preview while advanced placement controls are open.', 'million-dollar-script'),
            'resize' => __('Automatically resizes uploaded images to fit the purchased block area.', 'million-dollar-script'),
            'manage-pixels-grid-dropdown' => __('Shows a grid selector on customer manage-pixels screens.', 'million-dollar-script'),
            'invert-pixels' => __('Inverts the selection behavior used by legacy manage-pixels screens.', 'million-dollar-script'),
            'stats-display-mode' => __('Controls whether stats boxes show sold/available inventory as pixels or sellable blocks.', 'million-dollar-script'),
            'show-grid-view-controls' => __('Shows optional View all and View images buttons below public grids. Leave disabled for the simpler full-grid default.', 'million-dollar-script'),
            'enable-mouseover' => __('Shows advertiser details in a popup over occupied blocks.', 'million-dollar-script'),
            'tooltip-trigger' => __('Controls whether block popups open on hover or click.', 'million-dollar-script'),
            'max-popup-size' => __('Maximum popup width in pixels.', 'million-dollar-script'),
            'max-image-size' => __('Maximum popup image width in pixels.', 'million-dollar-script'),
            'popup-template' => __('Controls the rendered layout of occupied block popups. Template placeholders do not add, remove, require, or erase customer input fields.', 'million-dollar-script'),
            'login-redirect' => __('URL customers are sent to after login.', 'million-dollar-script'),
            'logout-redirect' => __('URL customers are sent to after logout.', 'million-dollar-script'),
            'login-header-image' => __('WordPress media image shown above Million Dollar Script login forms.', 'million-dollar-script'),
            'login-header-text' => __('Short text shown near the Million Dollar Script login header image.', 'million-dollar-script'),
            'accounts-optional' => __('Allows customers to place orders without creating a WordPress account.', 'million-dollar-script'),
            'expire-orders' => __('Expires unpaid or stale orders according to the timing settings below.', 'million-dollar-script'),
            'auto-approve' => __('Automatically completes standalone/manual orders after upload when no checkout URL or gateway webhook will confirm payment.', 'million-dollar-script'),
            'order-locking' => __('Temporarily locks selected blocks while checkout is pending.', 'million-dollar-script'),
            'minutes-renew' => __('Minutes to keep expired orders before cancelling them. Use 0 to leave expired orders unchanged, or -1 to cancel immediately.', 'million-dollar-script'),
            'minutes-confirmed' => __('Minutes to keep orders awaiting payment before expiring them and releasing reserved blocks. Use 0 to disable, or -1 for immediate cleanup.', 'million-dollar-script'),
            'minutes-unconfirmed' => __('Minutes to keep reserved orders before expiring them and releasing reserved blocks. Use 0 to disable, or -1 for immediate cleanup.', 'million-dollar-script'),
            'minutes-cancel' => __('Minutes to keep cancelled, failed, refunded, or denied orders before marking them deleted. Records remain in the database for audit. Use 0 to disable, or -1 for immediate cleanup.', 'million-dollar-script'),
            'text-optional' => __('Controls whether the built-in popup text field is required, optional, or hidden on customer forms.', 'million-dollar-script'),
            'popup-rich-text' => __('Uses a simple rich text editor for customer popup copy while saving only basic safe formatting.', 'million-dollar-script'),
            'url-optional' => __('Controls whether the built-in advertiser destination URL field is required, optional, or hidden on customer forms.', 'million-dollar-script'),
            'image-optional' => __('Allows customers to submit an order without uploading artwork immediately.', 'million-dollar-script'),
            'max-upload-width' => __('Maximum uploaded artwork width in pixels. Use 0 for no explicit plugin limit; the server upload limit still applies.', 'million-dollar-script'),
            'max-upload-height' => __('Maximum uploaded artwork height in pixels. Use 0 for no explicit plugin limit; smaller limits are safer on shared hosting.', 'million-dollar-script'),
            'email-user-order-confirmed' => __('Sends customers a notice when an order moves to payment requested or checkout pending.', 'million-dollar-script'),
            'email-admin-order-confirmed' => __('Sends the site administrator a copy of payment requested notices.', 'million-dollar-script'),
            'order-confirmed-subject' => __('Subject used for payment requested notices. Supports %ORDER_ID%, %SITE_NAME%, %CUSTOMER_EMAIL%, and related order placeholders.', 'million-dollar-script'),
            'order-confirmed-content' => __('Message used for payment requested notices. Supports %ORDER_ID%, %PIXEL_COUNT%, %PRICE%, %STATUS%, %MANAGE_URL%, %SITE_NAME%, %SITE_URL%, and customer placeholders.', 'million-dollar-script'),
            'email-user-order-completed' => __('Sends customers a notice when an order is paid and active.', 'million-dollar-script'),
            'email-admin-order-completed' => __('Sends the site administrator a copy of paid order notices.', 'million-dollar-script'),
            'order-completed-subject' => __('Subject used for paid order notices. Supports %ORDER_ID%, %SITE_NAME%, %CUSTOMER_EMAIL%, and related order placeholders.', 'million-dollar-script'),
            'order-completed-content' => __('Message used for paid order notices. Supports %ORDER_ID%, %PIXEL_COUNT%, %PRICE%, %STATUS%, %MANAGE_URL%, %SITE_NAME%, %SITE_URL%, and customer placeholders.', 'million-dollar-script'),
            'email-user-order-completed-renewal' => __('Sends customers a notice when a renewal payment completes.', 'million-dollar-script'),
            'email-admin-order-completed-renewal' => __('Sends the site administrator a copy of renewal paid notices.', 'million-dollar-script'),
            'order-completed-renewal-subject' => __('Subject used for renewal paid notices. Supports %ORDER_ID%, %SITE_NAME%, %CUSTOMER_EMAIL%, and related order placeholders.', 'million-dollar-script'),
            'order-completed-renewal-content' => __('Message used for renewal paid notices. Supports %ORDER_ID%, %PIXEL_COUNT%, %PRICE%, %STATUS%, %MANAGE_URL%, %SITE_NAME%, %SITE_URL%, and customer placeholders.', 'million-dollar-script'),
            'email-user-order-expired' => __('Sends customers a notice when a paid placement expires or an unpaid reservation is released by cleanup.', 'million-dollar-script'),
            'email-admin-order-expired' => __('Sends the site administrator a copy of order expiration and cleanup notices.', 'million-dollar-script'),
            'order-expired-subject' => __('Subject used for order expiration notices. Supports %ORDER_ID%, %SITE_NAME%, %CUSTOMER_EMAIL%, and related order placeholders.', 'million-dollar-script'),
            'order-expired-content' => __('Message used for order expiration notices. Supports %ORDER_ID%, %PIXEL_COUNT%, %PRICE%, %STATUS%, %MANAGE_URL%, %SITE_NAME%, %SITE_URL%, and customer placeholders.', 'million-dollar-script'),
            'email-user-order-denied' => __('Sends customers a notice when an order is denied by an administrator or integration.', 'million-dollar-script'),
            'email-admin-order-denied' => __('Sends the site administrator a copy of order denied notices.', 'million-dollar-script'),
            'order-denied-subject' => __('Subject used for order denied notices. Supports %ORDER_ID%, %SITE_NAME%, %CUSTOMER_EMAIL%, and related order placeholders.', 'million-dollar-script'),
            'order-denied-content' => __('Message used for order denied notices. Supports %ORDER_ID%, %PIXEL_COUNT%, %PRICE%, %STATUS%, %MANAGE_URL%, %SITE_NAME%, %SITE_URL%, and customer placeholders.', 'million-dollar-script'),
            'email-admin-publish-notify' => __('Sends the site administrator a notice when an active placement is saved or updated.', 'million-dollar-script'),
            /* translators: %ORDER_ID%, %GRID_NAME%, %PLACEMENT_URL%, and %MANAGE_URL% are literal email placeholders. */
            'order-published-subject' => __('Subject used for placement published notices. Supports %ORDER_ID%, %GRID_NAME%, %PLACEMENT_URL%, %MANAGE_URL%, and related order placeholders.', 'million-dollar-script'),
            /* translators: %ORDER_ID%, %GRID_NAME%, %PLACEMENT_URL%, %PLACEMENT_ALT%, %MANAGE_URL%, and %SITE_NAME% are literal email placeholders. */
            'order-published-content' => __('Message used for placement published notices. Supports %ORDER_ID%, %GRID_NAME%, %PLACEMENT_URL%, %PLACEMENT_ALT%, %MANAGE_URL%, %SITE_NAME%, and customer placeholders.', 'million-dollar-script'),
            'email-user-renewal-reminder' => __('Sends customers a reminder before a paid placement reaches its expiration date.', 'million-dollar-script'),
            'email-admin-renewal-reminder' => __('Sends the site administrator a copy of renewal reminder notices.', 'million-dollar-script'),
            'renewal-reminder-days-1' => __('Number of days before expiration for the first reminder. Use 0 to disable this reminder.', 'million-dollar-script'),
            'renewal-reminder-days-2' => __('Number of days before expiration for the second reminder. Use 0 to disable this reminder.', 'million-dollar-script'),
            'renewal-reminder-days-3' => __('Number of days before expiration for the third reminder. Use 0 to disable this reminder.', 'million-dollar-script'),
            'renewal-reminder-subject' => __('Subject used for renewal reminder notices. Supports %DAYS_LEFT%, %ORDER_ID%, %SITE_NAME%, and related order placeholders.', 'million-dollar-script'),
            /* translators: %DAYS_LEFT%, %EXPIRES_AT%, %MANAGE_URL%, %PRICE%, and %SITE_NAME% are literal email placeholders. */
            'renewal-reminder-content' => __('Message used for renewal reminder notices. Supports %DAYS_LEFT%, %EXPIRES_AT%, %MANAGE_URL%, %PRICE%, %SITE_NAME%, and related order placeholders.', 'million-dollar-script'),
            'block-selection-mode' => __('Enables customers to select more than one block per order.', 'million-dollar-script'),
            'selection-adjacency-mode' => __('Controls whether customers must choose connected blocks, a complete square or rectangle, or any unrestricted set of blocks.', 'million-dollar-script'),
            'extension_server_url' => __('Base URL used by the Million Dollar Script extension catalog and license services.', 'million-dollar-script'),
            'extension_portal_auto_accounts' => __('Creates extension portal accounts automatically when the site connects to Million Dollar Script services.', 'million-dollar-script'),
            'disable_version_analytics' => __('Stops the existing update check from reporting the MDS, WordPress, PHP, and official extension versions and active states with a hashed site identifier. Other plugins, customer data, content, and credentials are not sent.', 'million-dollar-script'),
            'delete_data_on_uninstall' => __('Deletes Million Dollar Script tables and options when the plugin is uninstalled.', 'million-dollar-script'),
            'updates' => __('Selects the update channel used for Million Dollar Script plugin updates.', 'million-dollar-script'),
            'log-enable' => __('Enables Million Dollar Script diagnostic logging.', 'million-dollar-script'),
            'update-language' => __('Allows Million Dollar Script language files to be updated automatically.', 'million-dollar-script'),
            'transliterate-slugs' => __('Converts non-Latin characters in generated slugs when possible.', 'million-dollar-script'),
        ];

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/settings/help', $help[$key] ?? __('Controls this Million Dollar Script setting.', 'million-dollar-script'), $key);
    }

    public static function sanitize($key, $value) {
        $field = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/settings/field/schema', self::fields()[$key] ?? ['type' => 'text'], $key);
        $field = is_array($field) ? $field : ['type' => 'text'];
        $type = $field['type'] ?? 'text';

        if ('number' === $type || 'image' === $type) {
            if ('number' === $type && in_array($key, self::signed_integer_keys(), true)) {
                return max(-1, (int) wp_unslash($value));
            }

            return absint($value);
        }

        if ('color' === $type) {
            $value = (string) wp_unslash($value);
            $color = function_exists('sanitize_hex_color') ? sanitize_hex_color($value) : self::sanitize_hex($value);

            return $color ?: (string) ($field['default'] ?? '');
        }

        if ('url' === $type) {
            return esc_url_raw(wp_unslash($value));
        }

        if ('textarea' === $type || 'editor' === $type) {
            return wp_kses_post(wp_unslash($value));
        }

        if ('select' === $type) {
            $value = self::normalize_select_value($key, (string) sanitize_text_field(wp_unslash($value)));
            if ('payment_provider' === $key) {
                return sanitize_key($value) ?: 'standalone';
            }
            $options = self::option_values($field);
            if (in_array('1', $options, true) && in_array('0', $options, true)) {
                $lower = strtolower($value);
                if (in_array($lower, ['yes', 'true', 'on', 'enabled'], true)) {
                    $value = '1';
                } elseif (in_array($lower, ['no', 'false', 'off', 'disabled'], true)) {
                    $value = '0';
                }
            } elseif (in_array('yes', $options, true) && in_array('no', $options, true)) {
                $lower = strtolower($value);
                if (in_array($lower, ['yes', 'true', 'on', 'enabled'], true)) {
                    $value = 'yes';
                } elseif (in_array($lower, ['no', 'false', 'off', 'disabled'], true)) {
                    $value = 'no';
                }
            }
            return in_array($value, $options, true) ? $value : (string) ($field['default'] ?? '');
        }

        if ('currency' === $key) {
            return strtoupper(substr(sanitize_text_field(wp_unslash($value)), 0, 3));
        }

        if ('mds-pixel-base' === $key) {
            return sanitize_title(wp_unslash($value)) ?: 'mds-pixel';
        }

        if ('mds-pixel-slug-structure' === $key) {
            $pattern = trim(sanitize_text_field(wp_unslash($value)));

            return '' !== $pattern ? substr($pattern, 0, 180) : '%placement_id%';
        }

        if ('advertiser-page-popup-label' === $key) {
            return substr(sanitize_text_field(wp_unslash($value)), 0, 80);
        }

        if ('endpoint' === $key) {
            return sanitize_title(wp_unslash($value)) ?: 'milliondollarscript';
        }

        return sanitize_text_field(wp_unslash($value));
    }

    private static function sanitize_hex($value) {
        $value = trim((string) $value);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : '';
    }

    private static function option_values(array $field) {
        $values = [];
        foreach (($field['options'] ?? []) as $key => $label) {
            $values[] = is_int($key) ? (string) $label : (string) $key;
        }

        return array_map('strval', $values);
    }

    private static function signed_integer_keys() {
        return [
            'minutes-renew',
            'minutes-confirmed',
            'minutes-unconfirmed',
            'minutes-cancel',
        ];
    }

    private static function normalize_select_value($key, $value) {
        $value = (string) $value;
        $lower = strtolower($value);

        if ('payment_provider' === $key) {
            if (in_array($lower, ['1', 'yes', 'true', 'on', 'enabled', 'woocommerce', 'woocommerce checkout'], true)) {
                return 'woocommerce';
            }
            if (in_array($lower, ['0', 'no', 'false', 'off', 'disabled', 'standalone', 'standalone checkout', 'manual'], true)) {
                return 'standalone';
            }
        }

        if ('block-selection-mode' === $key) {
            if (in_array($lower, ['yes', 'true', 'on', 'enabled', 'advanced', 'blocks'], true)) {
                return 'YES';
            }
            if (in_array($lower, ['no', 'false', 'off', 'disabled', 'simple', 'single'], true)) {
                return 'NO';
            }

            return strtoupper($value);
        }

        if ('selection-adjacency-mode' === $key) {
            if (in_array($lower, ['adjacent', 'contiguous', 'strict'], true)) {
                return 'ADJACENT';
            }
            if (in_array($lower, ['rectangle', 'rectangular', 'block', 'blocks', 'square'], true)) {
                return 'RECTANGLE';
            }
            if (in_array($lower, ['none', 'unrestricted', 'no', 'off', 'false'], true)) {
                return 'NONE';
            }

            return strtoupper($value);
        }

        if ('stats-display-mode' === $key) {
            if (in_array($lower, ['blocks', 'block', 'basic'], true)) {
                return 'BLOCKS';
            }
            if (in_array($lower, ['pixels', 'pixel', 'full', 'none'], true)) {
                return 'PIXELS';
            }

            return strtoupper($value);
        }

        if ('updates' === $key) {
            if (in_array($lower, ['main', 'stable', 'release', 'production'], true)) {
                return 'main';
            }
            if (in_array($lower, ['alpha', 'dev', 'development', 'nightly'], true)) {
                return 'alpha';
            }
        }

        return $value;
    }

    public static function aliases($key) {
        $aliases = [
            '_' . 'milliondollarscript_' . $key,
            'milliondollarscript_' . $key,
            '_' . 'mds_' . $key,
            'mds_' . $key,
            strtoupper(str_replace('-', '_', $key)),
        ];

        $legacy_email_aliases = [
            'email-user-order-confirmed' => ['EMAIL_USER_ORDER_CONFIRMED', 'EMAIL_USER_ORDER_PENDED'],
            'email-admin-order-confirmed' => ['EMAIL_ADMIN_ORDER_CONFIRMED', 'EMAIL_ADMIN_ORDER_PENDED'],
            'email-user-order-completed' => ['EMAIL_USER_ORDER_COMPLETED'],
            'email-admin-order-completed' => ['EMAIL_ADMIN_ORDER_COMPLETED'],
            'email-user-order-completed-renewal' => ['email-user-order-renewal', 'milliondollarscript_email-user-order-renewal', '_milliondollarscript_email-user-order-renewal', 'EMAIL_USER_ORDER_COMPLETED_RENEWAL', 'EMAIL_USER_ORDER_RENEWAL'],
            'email-admin-order-completed-renewal' => ['email-admin-order-renewal', 'milliondollarscript_email-admin-order-renewal', '_milliondollarscript_email-admin-order-renewal', 'EMAIL_ADMIN_ORDER_COMPLETED_RENEWAL', 'EMAIL_ADMIN_ORDER_RENEWAL'],
            'email-user-order-expired' => ['EMAIL_USER_ORDER_EXPIRED'],
            'email-admin-order-expired' => ['EMAIL_ADMIN_ORDER_EXPIRED'],
            'email-user-order-denied' => ['EMAIL_USER_ORDER_DENIED'],
            'email-admin-order-denied' => ['EMAIL_ADMIN_ORDER_DENIED'],
            'email-admin-publish-notify' => ['EMAIL_ADMIN_PUBLISH_NOTIFY'],
            'email-user-renewal-reminder' => ['EMAIL_USER_EXPIRE_WARNING'],
            'email-admin-renewal-reminder' => ['EMAIL_ADMIN_EXPIRE_WARNING', 'EMAIL_ADMIN_RENEWAL_REMINDER'],
            'renewal-reminder-days-1' => ['RENEWAL_REMINDER_DAYS_1'],
            'renewal-reminder-days-2' => ['RENEWAL_REMINDER_DAYS_2'],
            'renewal-reminder-days-3' => ['RENEWAL_REMINDER_DAYS_3'],
            'order-confirmed-subject' => ['order-pending-subject', 'milliondollarscript_order-pending-subject', '_milliondollarscript_order-pending-subject', 'ORDER_PENDING_SUBJECT'],
            'order-confirmed-content' => ['order-pending-content', 'milliondollarscript_order-pending-content', '_milliondollarscript_order-pending-content', 'ORDER_PENDING_CONTENT'],
            'order-completed-renewal-subject' => ['order-renewal-subject', 'milliondollarscript_order-renewal-subject', '_milliondollarscript_order-renewal-subject', 'ORDER_RENEWAL_SUBJECT'],
            'order-completed-renewal-content' => ['order-renewal-content', 'milliondollarscript_order-renewal-content', '_milliondollarscript_order-renewal-content', 'ORDER_RENEWAL_CONTENT'],
        ];
        foreach ($legacy_email_aliases[$key] ?? [] as $alias) {
            $aliases[] = $alias;
        }

        if ('payment_provider' === $key) {
            $aliases[] = 'woocommerce_enabled';
            $aliases[] = 'mds_use_woocommerce_integration';
            $aliases[] = 'milliondollarscript_use_woocommerce_integration';
            $aliases[] = '_milliondollarscript_use_woocommerce_integration';
        }

        if ('delete_data_on_uninstall' === $key) {
            $aliases[] = 'milliondollarscript_delete-data';
            $aliases[] = '_milliondollarscript_delete-data';
        }

        return array_values(array_unique($aliases));
    }

    public static function map_legacy_options(array $legacy_options, array $existing = []) {
        $mapped = [];
        foreach (self::fields() as $key => $field) {
            $value = null;
            foreach (self::aliases($key) as $alias) {
                if (array_key_exists($alias, $legacy_options) && '' !== $legacy_options[$alias] && null !== $legacy_options[$alias]) {
                    $value = $legacy_options[$alias];
                    break;
                }
            }

            if (null === $value && array_key_exists($key, $existing)) {
                $value = $existing[$key];
            }

            if (null === $value) {
                $value = $field['default'] ?? '';
            }

            $mapped[$key] = self::sanitize($key, $value);
        }
        return $mapped;
    }

    private static function default_order_confirmed_content() {
        return __(
            '<p>Your Million Dollar Script order on %SITE_NAME% is ready for payment.</p><p>Order #%ORDER_ID%<br>Pixels: %PIXEL_COUNT%<br>Price: %PRICE%<br>Status: %STATUS%</p><p>You can finish payment or manage the order here: <a href="%MANAGE_URL%">%MANAGE_URL%</a></p><p>Contact %SITE_CONTACT_EMAIL% if you have questions.</p>',
            'million-dollar-script'
        );
    }

    private static function default_order_completed_content() {
        return __(
            '<p>Your Million Dollar Script order on %SITE_NAME% is paid and active.</p><p>Order #%ORDER_ID%<br>Pixels: %PIXEL_COUNT%<br>Price: %PRICE%<br>Status: %STATUS%</p><p>You can manage your placement here: <a href="%MANAGE_URL%">%MANAGE_URL%</a></p><p>Contact %SITE_CONTACT_EMAIL% if you have questions.</p>',
            'million-dollar-script'
        );
    }

    private static function default_order_completed_renewal_content() {
        return __(
            '<p>Your Million Dollar Script renewal on %SITE_NAME% is paid and active.</p><p>Order #%ORDER_ID%<br>Pixels: %PIXEL_COUNT%<br>Renewal price: %PRICE%<br>Status: %STATUS%</p><p>You can manage your placement here: <a href="%MANAGE_URL%">%MANAGE_URL%</a></p><p>Contact %SITE_CONTACT_EMAIL% if you have questions.</p>',
            'million-dollar-script'
        );
    }

    private static function default_order_expired_content() {
        return __(
            '<p>Your Million Dollar Script order on %SITE_NAME% has expired.</p><p>Order #%ORDER_ID%<br>Pixels: %PIXEL_COUNT%<br>Price: %PRICE%<br>Status: %STATUS%</p><p>You can review the order here: <a href="%MANAGE_URL%">%MANAGE_URL%</a></p><p>Contact %SITE_CONTACT_EMAIL% if you have questions.</p>',
            'million-dollar-script'
        );
    }

    private static function default_order_denied_content() {
        return __(
            '<p>Your Million Dollar Script order on %SITE_NAME% was denied.</p><p>Order #%ORDER_ID%<br>Pixels: %PIXEL_COUNT%<br>Price: %PRICE%<br>Status: %STATUS%</p><p>You can review the order here: <a href="%MANAGE_URL%">%MANAGE_URL%</a></p><p>Contact %SITE_CONTACT_EMAIL% if you have questions.</p>',
            'million-dollar-script'
        );
    }

    private static function default_order_published_content() {
        /* translators: %SITE_NAME%, %GRID_NAME%, %ORDER_ID%, %PLACEMENT_URL%, and %MANAGE_URL% are literal email placeholders replaced before sending. */
        return __(
            '<p>A Million Dollar Script placement was published on %SITE_NAME%.</p><p>Grid: %GRID_NAME%<br>Order #%ORDER_ID%<br>Advertiser URL: %PLACEMENT_URL%</p><p>Review the order here: <a href="%MANAGE_URL%">%MANAGE_URL%</a></p>',
            'million-dollar-script'
        );
    }

    private static function default_renewal_reminder_content() {
        /* translators: %SITE_NAME%, %DAYS_LEFT%, %ORDER_ID%, %PIXEL_COUNT%, %EXPIRES_AT%, %PRICE%, %MANAGE_URL%, and %SITE_CONTACT_EMAIL% are literal email placeholders replaced before sending. */
        return __(
            '<p>Your Million Dollar Script order on %SITE_NAME% will expire in %DAYS_LEFT% days.</p><p>Order #%ORDER_ID%<br>Pixels: %PIXEL_COUNT%<br>Expires: %EXPIRES_AT%<br>Renewal price: %PRICE%</p><p>You can renew or manage the order here: <a href="%MANAGE_URL%">%MANAGE_URL%</a></p><p>Contact %SITE_CONTACT_EMAIL% if you have questions.</p>',
            'million-dollar-script'
        );
    }
}
