<?php
/**
 * Customer artwork upload validation.
 *
 * @package MillionDollarScript\V3\Media
 */

namespace MillionDollarScript\V3\Media;

use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

final class UploadValidator {

    /**
     * Validate an uploaded image before WordPress stores it.
     *
     * @param array  $file Uploaded file array.
     * @param string $filename Original filename.
     * @param array  $settings MDS3 settings.
     * @return true|\WP_Error
     */
    public function validate(array $file, $filename = '', array $settings = []) {
        $error = absint($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if (UPLOAD_ERR_OK !== $error) {
            return new \WP_Error('mds3_upload_failed', __('Image upload failed. Please choose another file.', 'million-dollar-script'), array('field' => 'image'));
        }

        $size = absint($file['size'] ?? 0);
        if ($size <= 0) {
            return new \WP_Error('mds3_upload_empty', __('Image upload is empty.', 'million-dollar-script'), array('field' => 'image'));
        }

        $max_upload = function_exists('wp_max_upload_size') ? (int) wp_max_upload_size() : 0;
        if ($max_upload > 0 && $size > $max_upload) {
            return new \WP_Error('mds3_upload_too_large', sprintf(
                /* translators: %s: maximum upload size */
                __('Your file is bigger than this site accepts - the limit is %s.', 'million-dollar-script'),
                function_exists('size_format') ? size_format($max_upload) : (string) $max_upload
            ), array('field' => 'image'));
        }

        $tmp_name = (string) ($file['tmp_name'] ?? '');
        $is_uploaded = $tmp_name && is_uploaded_file($tmp_name);
        $is_uploaded = (bool) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/is/uploaded/file', $is_uploaded, $tmp_name, $file);
        if (!$tmp_name || !$is_uploaded) {
            return new \WP_Error('mds3_upload_invalid', __('Image upload could not be verified.', 'million-dollar-script'), array('field' => 'image'));
        }

        $allowed_mimes = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
        $filetype = wp_check_filetype_and_ext($tmp_name, (string) $filename, $allowed_mimes);
        $mime_type = (string) ($filetype['type'] ?? '');
        if (empty($filetype['ext']) || empty($mime_type) || 0 !== strpos($mime_type, 'image/')) {
            return new \WP_Error('mds3_upload_type_invalid', __('Only JPG, PNG, GIF, and WebP uploads are supported.', 'million-dollar-script'), array('field' => 'image'));
        }

        $dimensions = $this->image_dimensions($tmp_name);
        if (is_wp_error($dimensions)) {
            return $dimensions;
        }

        return $this->validate_dimension_limits($dimensions, $settings);
    }

    /**
     * Return uploaded image dimensions.
     *
     * @param string $path File path.
     * @return array|\WP_Error
     */
    private function image_dimensions($path) {
        $size = function_exists('wp_getimagesize') ? wp_getimagesize($path) : @getimagesize($path);
        $width = is_array($size) ? absint($size[0] ?? 0) : 0;
        $height = is_array($size) ? absint($size[1] ?? 0) : 0;

        if (!$width || !$height) {
            return new \WP_Error('mds3_upload_image_invalid', __('Image dimensions could not be read. Please choose another image.', 'million-dollar-script'), array('field' => 'image'));
        }

        return [
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Reject an image that is bigger than the ad it belongs to.
     *
     * The message states the size the site accepts instead of only the limit that was
     * crossed. "The maximum allowed height is 100px" is not something a site owner can
     * act on when the ad is a 640x100 banner and they were watching the width - that is
     * how one file looking fine and the next one failing reads as a limit that moves.
     *
     * @param array $dimensions Image dimensions.
     * @param array $settings MDS3 settings.
     * @return true|\WP_Error
     */
    private function validate_dimension_limits(array $dimensions, array $settings) {
        $limits = self::max_upload_dimensions($settings);
        $width = absint($dimensions['width'] ?? 0);
        $height = absint($dimensions['height'] ?? 0);
        $too_wide = $limits['width'] && $width > $limits['width'];
        $too_tall = $limits['height'] && $height > $limits['height'];

        if (!$too_wide && !$too_tall) {
            return true;
        }

        return new \WP_Error(
            $too_wide ? 'mds3_upload_width_too_large' : 'mds3_upload_height_too_large',
            sprintf(
                /* translators: 1: the image size ads on this site accept, 2: chosen image width in pixels, 3: chosen image height in pixels */
                __('Please choose an image that fits %1$s. The file you chose is %2$d × %3$d px.', 'million-dollar-script'),
                self::size_limit_text($settings),
                $width,
                $height
            ),
            array('field' => 'image')
        );
    }

    /**
     * The image size this site accepts - the same settings validate_dimension_limits()
     * enforces, so a hint built from here cannot disagree with a rejection.
     *
     * @return array Array with "width" and "height" in pixels, zero for unlimited.
     */
    public static function max_upload_dimensions(array $settings = []) {
        $settings = wp_parse_args($settings, SettingsSchema::defaults());

        return array(
            'width' => absint(SettingsSchema::sanitize('max-upload-width', $settings['max-upload-width'] ?? 0)),
            'height' => absint(SettingsSchema::sanitize('max-upload-height', $settings['max-upload-height'] ?? 0)),
        );
    }

    /**
     * Describe that size in words: "640 × 100 px", or a single dimension when only one
     * is limited. Empty string when the site sets no limit, so callers can skip it.
     */
    public static function size_limit_text(array $settings = []) {
        $limits = self::max_upload_dimensions($settings);

        if ($limits['width'] && $limits['height']) {
            /* translators: 1: width in pixels, 2: height in pixels */
            return sprintf(__('%1$d × %2$d px', 'million-dollar-script'), $limits['width'], $limits['height']);
        }

        if ($limits['width']) {
            /* translators: %d: maximum width in pixels */
            return sprintf(__('%d px wide', 'million-dollar-script'), $limits['width']);
        }

        if ($limits['height']) {
            /* translators: %d: maximum height in pixels */
            return sprintf(__('%d px tall', 'million-dollar-script'), $limits['height']);
        }

        return '';
    }

    /**
     * The sentence shown beside the image field before a file is chosen, so the size is
     * not something the customer learns from being refused.
     */
    public static function size_hint_sentence(array $settings = []) {
        $limit = self::size_limit_text($settings);
        if ('' === $limit) {
            return '';
        }

        /* translators: %s: the image size ads on this site accept */
        return sprintf(__('Images need to fit %s.', 'million-dollar-script'), $limit);
    }
}
