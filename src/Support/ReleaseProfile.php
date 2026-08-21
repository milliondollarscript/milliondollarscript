<?php
/**
 * Build-time release profile behavior.
 *
 * @package MillionDollarScript\V3\Support
 */

namespace MillionDollarScript\V3\Support;

if (!defined('ABSPATH')) {
    exit;
}

final class ReleaseProfile implements Component {

    public const PRIVATE_ALPHA_DEV = 'private-alpha-dev';

    /**
     * Register release-profile notices.
     *
     * @return void
     */
    public function register() {
        if (self::is_private_alpha_dev()) {
            add_action('admin_notices', [$this, 'private_alpha_notice']);
        }
    }

    /**
     * Return the package's build-time release profile.
     *
     * @return string
     */
    public static function id() {
        if (!defined('MILLION_DOLLAR_SCRIPT_RELEASE_PROFILE')) {
            return '';
        }

        return strtolower(trim((string) MILLION_DOLLAR_SCRIPT_RELEASE_PROFILE));
    }

    /**
     * Return whether this is the invited dev-connected private alpha.
     *
     * @return bool
     */
    public static function is_private_alpha_dev() {
        return self::PRIVATE_ALPHA_DEV === self::id();
    }

    /**
     * Apply a build-time update channel when one is configured.
     *
     * @param string $stored_channel Stored site setting.
     * @return string
     */
    public static function update_channel($stored_channel = 'main') {
        $channel = defined('MILLION_DOLLAR_SCRIPT_DEFAULT_UPDATE_CHANNEL')
            ? strtolower(trim((string) MILLION_DOLLAR_SCRIPT_DEFAULT_UPDATE_CHANNEL))
            : '';

        return in_array($channel, ['main', 'beta', 'alpha'], true)
            ? $channel
            : (string) $stored_channel;
    }

    /**
     * Make the private test build unmistakable in WordPress admin.
     *
     * @return void
     */
    public function private_alpha_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-warning"><p><strong>';
        esc_html_e('Million Dollar Script private pre-alpha', 'million-dollar-script');
        echo '</strong> &mdash; ';
        esc_html_e('This invited test build uses the development service and alpha updates. Do not use it on a production site.', 'million-dollar-script');
        echo '</p></div>';
    }
}
