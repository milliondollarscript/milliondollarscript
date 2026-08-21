<?php
/**
 * Original image resolver.
 *
 * @package MillionDollarScript\V3\Media
 */

namespace MillionDollarScript\V3\Media;

if (!defined('ABSPATH')) {
    exit;
}

final class OriginalImage {

    public function resolve($attachment_id) {
        $attachment_id = absint($attachment_id);
        if (!$attachment_id) {
            return null;
        }

        $path = function_exists('wp_get_original_image_path') ? (string) wp_get_original_image_path($attachment_id) : '';
        if (!$path) {
            $path = (string) get_attached_file($attachment_id, true);
        }

        if (!$path || !is_readable($path)) {
            return null;
        }

        $metadata = wp_get_attachment_metadata($attachment_id);
        $width = is_array($metadata) ? absint($metadata['width'] ?? 0) : 0;
        $height = is_array($metadata) ? absint($metadata['height'] ?? 0) : 0;

        $url = function_exists('wp_get_original_image_url') ? (string) wp_get_original_image_url($attachment_id) : '';
        if (!$url) {
            $url = (string) wp_get_attachment_url($attachment_id);
        }

        return [
            'attachment_id' => $attachment_id,
            'path' => $path,
            'url' => $url,
            'mime_type' => (string) get_post_mime_type($attachment_id),
            'width' => $width,
            'height' => $height,
            'megapixels' => ($width > 0 && $height > 0) ? round(($width * $height) / 1000000, 4) : 0,
        ];
    }
}
