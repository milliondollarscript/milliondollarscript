<?php
/**
 * Per-grid background image settings and public presentation payloads.
 *
 * @package MillionDollarScript\V3\Grid
 */

namespace MillionDollarScript\V3\Grid;

if (!defined('ABSPATH')) {
    exit;
}

final class GridBackground {

    public const MAX_FILE_SIZE = 20971520;

    public static function storage_settings(array $settings) {
        $settings['background_image_id'] = self::attachment_id($settings['background_image_id'] ?? 0);
        $settings['background_image_fit'] = self::choice($settings['background_image_fit'] ?? 'cover', self::fit_options(), 'cover');
        $settings['background_image_position'] = self::choice($settings['background_image_position'] ?? 'center', self::position_options(), 'center');
        $settings['background_image_repeat'] = self::choice($settings['background_image_repeat'] ?? 'no-repeat', self::repeat_options(), 'no-repeat');
        $settings['background_image_opacity'] = self::opacity($settings['background_image_opacity'] ?? 100);

        return $settings;
    }

    public static function public_payload(array $settings) {
        $settings = self::storage_settings($settings);
        $attachment_id = absint($settings['background_image_id'] ?? 0);
        if (!$attachment_id) {
            return [];
        }

        $url = wp_get_attachment_image_url($attachment_id, 'full');
        if (!$url) {
            return [];
        }

        $metadata = wp_get_attachment_metadata($attachment_id);

        return [
            'url' => esc_url_raw($url),
            'width' => max(1, absint(is_array($metadata) ? ($metadata['width'] ?? 0) : 0)),
            'height' => max(1, absint(is_array($metadata) ? ($metadata['height'] ?? 0) : 0)),
            'fit' => $settings['background_image_fit'],
            'position' => $settings['background_image_position'],
            'repeat' => $settings['background_image_repeat'],
            'opacity' => $settings['background_image_opacity'],
        ];
    }

    public static function validate_attachment($attachment_id) {
        $attachment_id = absint($attachment_id);
        if (!$attachment_id) {
            return true;
        }

        if (!wp_attachment_is_image($attachment_id)) {
            return new \WP_Error('mds3_grid_background_not_image', __('Choose an image from the WordPress Media Library.', 'million-dollar-script'));
        }

        $path = get_attached_file($attachment_id);
        $max_size = max(1, (int) \MillionDollarScript\Core\Hooks::apply(
            'million-dollar-script/grid/background/max/file/size',
            self::MAX_FILE_SIZE,
            $attachment_id
        ));
        if ($path && is_file($path) && filesize($path) > $max_size) {
            return new \WP_Error(
                'mds3_grid_background_too_large',
                sprintf(
                    /* translators: %s: maximum file size. */
                    __('Choose a background image no larger than %s.', 'million-dollar-script'),
                    size_format($max_size)
                )
            );
        }

        return true;
    }

    public static function attachment_id($value) {
        $attachment_id = absint($value);

        return $attachment_id && wp_attachment_is_image($attachment_id) ? $attachment_id : 0;
    }

    public static function fit_options() {
        return ['cover', 'contain', 'stretch', 'auto'];
    }

    public static function position_options() {
        return [
            'top-left', 'top', 'top-right',
            'left', 'center', 'right',
            'bottom-left', 'bottom', 'bottom-right',
        ];
    }

    public static function repeat_options() {
        return ['no-repeat', 'repeat', 'repeat-x', 'repeat-y'];
    }

    private static function choice($value, array $allowed, $fallback) {
        $value = sanitize_key((string) $value);

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private static function opacity($value) {
        return max(0, min(100, absint($value)));
    }
}
