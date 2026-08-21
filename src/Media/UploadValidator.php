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
            return new \WP_Error('mds3_upload_failed', __('Image upload failed. Please choose another file.', 'million-dollar-script'));
        }

        $size = absint($file['size'] ?? 0);
        if ($size <= 0) {
            return new \WP_Error('mds3_upload_empty', __('Image upload is empty.', 'million-dollar-script'));
        }

        $max_upload = function_exists('wp_max_upload_size') ? (int) wp_max_upload_size() : 0;
        if ($max_upload > 0 && $size > $max_upload) {
            return new \WP_Error('mds3_upload_too_large', sprintf(
                /* translators: %s: maximum upload size */
                __('Image is larger than the maximum upload size of %s.', 'million-dollar-script'),
                function_exists('size_format') ? size_format($max_upload) : (string) $max_upload
            ));
        }

        $tmp_name = (string) ($file['tmp_name'] ?? '');
        $is_uploaded = $tmp_name && is_uploaded_file($tmp_name);
        $is_uploaded = (bool) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/is/uploaded/file', $is_uploaded, $tmp_name, $file);
        if (!$tmp_name || !$is_uploaded) {
            return new \WP_Error('mds3_upload_invalid', __('Image upload could not be verified.', 'million-dollar-script'));
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
            return new \WP_Error('mds3_upload_type_invalid', __('Only JPG, PNG, GIF, and WebP uploads are supported.', 'million-dollar-script'));
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
            return new \WP_Error('mds3_upload_image_invalid', __('Image dimensions could not be read. Please choose another image.', 'million-dollar-script'));
        }

        return [
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Enforce migrated MDS2 max upload dimensions.
     *
     * @param array $dimensions Image dimensions.
     * @param array $settings MDS3 settings.
     * @return true|\WP_Error
     */
    private function validate_dimension_limits(array $dimensions, array $settings) {
        $settings = wp_parse_args($settings, SettingsSchema::defaults());
        $max_width = absint(SettingsSchema::sanitize('max-upload-width', $settings['max-upload-width'] ?? 0));
        $max_height = absint(SettingsSchema::sanitize('max-upload-height', $settings['max-upload-height'] ?? 0));
        $width = absint($dimensions['width'] ?? 0);
        $height = absint($dimensions['height'] ?? 0);

        if ($max_width && $width > $max_width) {
            return new \WP_Error('mds3_upload_width_too_large', sprintf(
                /* translators: 1: uploaded width in pixels, 2: maximum width in pixels */
                __('Image width is %1$dpx. The maximum allowed width is %2$dpx.', 'million-dollar-script'),
                $width,
                $max_width
            ));
        }

        if ($max_height && $height > $max_height) {
            return new \WP_Error('mds3_upload_height_too_large', sprintf(
                /* translators: 1: uploaded height in pixels, 2: maximum height in pixels */
                __('Image height is %1$dpx. The maximum allowed height is %2$dpx.', 'million-dollar-script'),
                $height,
                $max_height
            ));
        }

        return true;
    }
}
