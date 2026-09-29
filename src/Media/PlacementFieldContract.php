<?php
/**
 * Shared visibility and validation rules for built-in placement fields.
 *
 * @package MillionDollarScript\V3\Media
 */

namespace MillionDollarScript\V3\Media;

use MillionDollarScript\V3\Grid\PopupText;
use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

final class PlacementFieldContract {
    public const REQUIRED = 'required';
    public const OPTIONAL = 'optional';
    public const HIDDEN = 'hidden';

    public static function url_mode(array $settings) {
        return self::mode('url-optional', $settings['url-optional'] ?? 'no');
    }

    public static function popup_text_mode(array $settings) {
        return self::mode('text-optional', $settings['text-optional'] ?? 'no');
    }

    /**
     * Effective ad text character limit, with the grid-level override taking precedence.
     * A blank grid override inherits the global setting; 0 means no limit.
     */
    public static function text_max_chars(array $settings, array $grid_settings = []) {
        $override = $grid_settings['popup_text_max_chars'] ?? null;
        $value = (null === $override || '' === trim((string) $override))
            ? ($settings['text-max-chars'] ?? 0)
            : $override;

        return max(0, absint($value));
    }

    /**
     * Resolve the popup display limit: a per-grid override wins over the global setting.
     */
    public static function popup_display_max_chars(array $settings, array $grid_settings = []) {
        $override = $grid_settings['popup_display_max_chars'] ?? null;
        $value = (null === $override || '' === trim((string) $override))
            ? ($settings['popup-text-max-chars'] ?? 0)
            : $override;

        return max(0, absint($value));
    }

    /**
     * Resolve what a placement click does: a per-grid override wins over the global setting.
     * A blank grid override inherits the global setting.
     *
     * @return string 'popup' or 'page'
     */
    public static function advertiser_page_click_mode(array $settings, array $grid_settings = []) {
        $override = strtolower(trim((string) ($grid_settings['advertiser_page_click_mode'] ?? '')));
        $value = in_array($override, ['popup', 'page'], true)
            ? $override
            : strtolower(trim((string) ($settings['advertiser-page-click-mode'] ?? 'popup')));

        return 'page' === $value ? 'page' : 'popup';
    }

    /**
     * Whether a paid order's uploaded creative goes live on the grid straight away.
     * The grid toggle defaults to publishing; only an explicit "N" holds the upload
     * for an admin to approve.
     */
    public static function auto_publish(array $grid_settings = []) {
        return 'N' !== strtoupper(trim((string) ($grid_settings['auto_publish'] ?? 'Y')));
    }

    /**
     * Apply grid-level overrides to a global settings array so validation and rendering agree.
     */
    public static function settings_for_grid(array $settings, $grid_id) {
        $grid = absint($grid_id) > 0 ? (new \MillionDollarScript\V3\Grid\GridRepository())->find(absint($grid_id)) : null;
        if (!$grid) {
            $settings['auto-publish'] = true;

            return $settings;
        }

        $settings['text-max-chars'] = (string) self::text_max_chars($settings, $grid->settings());
        $settings['popup-text-max-chars'] = (string) self::popup_display_max_chars($settings, $grid->settings());
        $settings['advertiser-page-click-mode'] = self::advertiser_page_click_mode($settings, $grid->settings());
        $settings['auto-publish'] = self::auto_publish($grid->settings());

        return $settings;
    }

    public static function is_visible($mode) {
        return self::HIDDEN !== $mode;
    }

    public static function is_required($mode) {
        return self::REQUIRED === $mode;
    }

    /** @return array|\WP_Error */
    public static function validate(array $submitted, array $settings, array $existing = []) {
        $url_mode = self::url_mode($settings);
        $popup_text_mode = self::popup_text_mode($settings);
        $link_url = (string) ($existing['link_url'] ?? '');
        $popup_text = (string) ($existing['popup_text'] ?? '');

        if (self::is_visible($url_mode)) {
            $submitted_url = $submitted['link_url'] ?? '';
            if (!is_scalar($submitted_url)) {
                return new \WP_Error('million_dollar_script_url_invalid', __('Enter a valid website URL.', 'million-dollar-script'), ['status' => 400]);
            }
            $raw_url = trim((string) $submitted_url);
            $link_url = self::advertiser_url($raw_url);
            if (self::is_required($url_mode) && '' === $raw_url) {
                return new \WP_Error('million_dollar_script_url_required', __('Enter the advertiser destination URL.', 'million-dollar-script'), ['status' => 400]);
            }
            if ('' !== $raw_url && '' === $link_url) {
                return new \WP_Error('million_dollar_script_url_invalid', __('Enter a valid website URL.', 'million-dollar-script'), ['status' => 400]);
            }
        }

        if (self::is_visible($popup_text_mode)) {
            $submitted_text = $submitted['popup_text'] ?? '';
            if (!is_scalar($submitted_text)) {
                return new \WP_Error('million_dollar_script_popup_invalid', __('Enter valid popup text for this placement.', 'million-dollar-script'), ['status' => 400]);
            }
            $popup_text = PopupText::sanitize($submitted_text, $settings);
            if (self::is_required($popup_text_mode) && '' === PopupText::plain($popup_text)) {
                return new \WP_Error('million_dollar_script_popup_required', __('Enter the popup text for this placement.', 'million-dollar-script'), ['status' => 400]);
            }
            $popup_text_max = self::text_max_chars($settings);
            if ($popup_text_max > 0 && mb_strlen(PopupText::plain($popup_text)) > $popup_text_max) {
                return new \WP_Error(
                    'million_dollar_script_popup_too_long',
                    sprintf(
                        /* translators: %d: maximum number of characters. */
                        __('Ad text must be %d characters or fewer.', 'million-dollar-script'),
                        $popup_text_max
                    ),
                    ['status' => 400]
                );
            }
        }

        $submitted_fit_mode = $submitted['fit_mode'] ?? ($existing['fit_mode'] ?? 'cover');
        $fit_mode = sanitize_key(is_scalar($submitted_fit_mode) ? (string) $submitted_fit_mode : 'cover');
        $submitted_alt_text = $submitted['alt_text'] ?? ($existing['alt_text'] ?? '');

        return [
            'fit_mode' => in_array($fit_mode, ['cover', 'contain'], true) ? $fit_mode : 'cover',
            'link_url' => $link_url,
            'alt_text' => sanitize_text_field(is_scalar($submitted_alt_text) ? (string) $submitted_alt_text : ''),
            'popup_text' => $popup_text,
        ];
    }

    private static function mode($setting_key, $value) {
        $value = SettingsSchema::sanitize($setting_key, $value);
        if ('hidden' === $value) {
            return self::HIDDEN;
        }

        return 'yes' === $value ? self::OPTIONAL : self::REQUIRED;
    }

    public static function advertiser_url($url) {
        $url = trim((string) wp_unslash($url));
        if (!$url) {
            return '';
        }

        while (preg_match('#^(https?://)(https?://)#i', $url)) {
            $url = preg_replace('#^(https?://)(https?://)#i', '$1', $url);
        }

        if (0 === strpos($url, '//')) {
            $url = 'https:' . $url;
        } elseif (!preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $url = esc_url_raw($url, ['http', 'https']);
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $parts = wp_parse_url($url);

        return is_array($parts)
            && !empty($parts['host'])
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                ? $url
                : '';
    }
}
