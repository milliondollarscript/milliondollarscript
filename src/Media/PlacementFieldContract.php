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
