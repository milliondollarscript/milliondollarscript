<?php
/*
 * Million Dollar Script Two - Account Page
 *
 * Shows license status from the Extension Server and a Manage Subscription link.
 */

namespace MillionDollarScript\Classes\Pages;

use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\System\Utility;

defined('ABSPATH') or exit;

class Account
{
    public const SLUG = 'mds-account';

    public static function menu(): void
    {
        // Add under main MDS menu
        add_submenu_page(
            'milliondollarscript',
            Language::get('Account'),
            Language::get('Account'),
            'manage_options',
            self::SLUG,
            [self::class, 'render'],
            35
        );
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html(Language::get('You do not have sufficient permissions to access this page.')));
        }

        // Notices at top
        settings_errors('mds_account_notices');

        $license_key = Options::get_option('license_key', '');
        $configured_base = Options::get_option('extension_server_url', 'http://host.docker.internal:15346');
        $admin_email = get_option('admin_email', '');

        $resolved_base = null;
        $lookup = null;
        $error = null;

        if (empty($license_key)) {
            add_settings_error(
                'mds_account_notices',
                'mds_missing_license',
                Language::get('No license key found. Enter your license key at Million Dollar Script → Options → System → License Key.'),
                'warning'
            );
        } else {
            try {
                $candidates = [
                    rtrim((string)$configured_base, '/'),
                    'http://extension-server:3000',
                    'http://extension-server-dev:3000',
                    'http://host.docker.internal:15346',
                    'http://localhost:15346',
                ];

                $args = [
                    'timeout' => 20,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'User-Agent' => 'MDS-WordPress-Plugin/' . (defined('MDS_VERSION') ? MDS_VERSION : 'dev'),
                    ],
                    'sslverify' => !Utility::is_development_environment(),
                ];

                foreach ($candidates as $base) {
                    if (empty($base)) {
                        continue;
                    }
                    $url = rtrim($base, '/') . '/api/public/licenses/lookup?key=' . rawurlencode($license_key);
                    $res = wp_remote_get($url, $args);
                    if (is_wp_error($res)) {
                        $error = $res->get_error_message();
                        continue;
                    }
                    $code = (int) wp_remote_retrieve_response_code($res);
                    if ($code === 200) {
                        $body = wp_remote_retrieve_body($res);
                        $data = json_decode($body, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                            $lookup = $data;
                            $resolved_base = $base;
                            break;
                        } else {
                            $error = 'Invalid JSON from server';
                        }
                    } else {
                        $error = 'HTTP ' . $code;
                    }
                }

                if (!$resolved_base) {
                    add_settings_error(
                        'mds_account_notices',
                        'mds_server_unreachable',
                        sprintf(
                            /* translators: %s is the error text */
                            esc_html(Language::get('Could not contact Extension Server. Last error: %s')),
                            esc_html((string)$error)
                        ),
                        'error'
                    );
                } elseif ($resolved_base && rtrim((string)$resolved_base, '/') !== rtrim((string)$configured_base, '/')) {
                    // Suggest updating base URL
                    set_transient('mds_ext_server_fallback_notice', $resolved_base, MINUTE_IN_SECONDS * 5);
                }
            } catch (\Throwable $e) {
                add_settings_error(
                    'mds_account_notices',
                    'mds_lookup_exception',
                    esc_html(Language::get('License lookup failed: ') . $e->getMessage()),
                    'error'
                );
            }
        }

        $portal_url = '';
        if ($resolved_base && !empty($admin_email)) {
            $portal_url = rtrim((string)$resolved_base, '/') . '/account?email=' . rawurlencode($admin_email);
        } elseif (!empty($configured_base) && !empty($admin_email)) {
            $portal_url = rtrim((string)$configured_base, '/') . '/account?email=' . rawurlencode($admin_email);
        }

        $license_found = is_array($lookup) && isset($lookup['success']) && $lookup['success'] === true && !empty($lookup['found']);
        $license_valid = $license_found ? (bool)($lookup['valid'] ?? false) : false;
        $license = $license_found ? ($lookup['license'] ?? []) : [];

        $status = $license['status'] ?? 'unknown';
        $expires = $license['expires_at'] ?? null;

        ?>
        <div class="wrap mds-account-page">
            <h1><?php echo esc_html(Language::get('Account')); ?></h1>

            <div class="mds-account-card" style="margin-top:14px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:16px;max-width:800px;">
                <h2 style="margin-top:0;"><?php echo esc_html(Language::get('License Status')); ?></h2>

                <?php if (empty($license_key)) : ?>
                    <p class="description">
                        <?php echo esc_html(Language::get('Add your license key on the System tab to enable premium downloads and updates.')); ?>
                    </p>
                    <p>
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=milliondollarscript_options#system')); ?>">
                            <?php echo esc_html(Language::get('Open System tab')); ?>
                        </a>
                    </p>
                <?php else : ?>
                    <?php if ($license_found) : ?>
                        <table class="widefat striped" style="max-width:600px;">
                            <tbody>
                            <tr>
                                <th style="width:200px;"><?php echo esc_html(Language::get('Status')); ?></th>
                                <td>
                                    <?php
                                    $status_text = ucfirst((string)$status);
                                    $color = $license_valid ? '#00a32a' : '#d63638';
                                    ?>
                                    <span style="display:inline-block;padding:2px 8px;border-radius:12px;background:<?php echo $license_valid ? '#e7f7ee' : '#fde7e9'; ?>;color:<?php echo esc_attr($color); ?>;">
                                        <?php echo esc_html($status_text . ($license_valid ? ' (Valid)' : ' (Invalid)')); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html(Language::get('License Key (masked)')); ?></th>
                                <td>
                                    <code><?php echo esc_html(is_string($license['key'] ?? '') ? (string)$license['key'] : '••••'); ?></code>
                                </td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html(Language::get('Expires At')); ?></th>
                                <td>
                                    <?php
                                    if ($expires) {
                                        $ts = strtotime((string)$expires);
                                        echo esc_html($ts ? gmdate('Y-m-d H:i:s \U\T\C', $ts) : (string)$expires);
                                    } else {
                                        echo esc_html(Language::get('Never'));
                                    }
                                    ?>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p>
                            <?php echo esc_html(Language::get('No license details found for the configured license key.')); ?>
                        </p>
                        <?php if (!empty($error)) : ?>
                            <p class="description"><?php echo esc_html(Language::get('Error: ') . $error); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div style="margin-top:16px;">
                        <?php if (!empty($portal_url)) : ?>
                            <a class="button button-primary" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($portal_url); ?>">
                                <?php echo esc_html(Language::get('Manage Subscription')); ?>
                            </a>
                        <?php else : ?>
                            <span class="description">
                                <?php echo esc_html(Language::get('Customer portal is not configured. Ensure your Extension Server URL is set on the System tab.')); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}