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

    /**
     * Resolve an attachment, and the copy to draw when a size to draw it at is named.
     *
     * @param int $attachment_id
     * @param int $draw_width   Size the picture is going to be drawn at, in CSS pixels. Zero keeps
     *                          the answer to the original, exactly as it was before.
     * @param int $draw_height
     */
    public function resolve($attachment_id, $draw_width = 0, $draw_height = 0) {
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

        $resolved = [
            'attachment_id' => $attachment_id,
            'path' => $path,
            'url' => $url,
            'mime_type' => (string) get_post_mime_type($attachment_id),
            'width' => $width,
            'height' => $height,
            'megapixels' => ($width > 0 && $height > 0) ? round(($width * $height) / 1000000, 4) : 0,
        ];

        $display = $this->display_variant($attachment_id, absint($draw_width), absint($draw_height), (array) $metadata, $url);
        if ($display) {
            $resolved['display'] = $display;
        }

        return $resolved;
    }

    /**
     * The smallest copy WordPress has already made that still covers the size it will be drawn at.
     *
     * This is what makes a grid with a few thousand live ads load. The originals on a site that
     * came over from the older plugin are often far larger than any cell they are drawn at, and a
     * page that fetches one per ad is fetching its whole library. Nothing is replaced anywhere a
     * person looks at the ad itself, and a copy is only offered when it is genuinely smaller: a
     * migrated media library can hold uploads with no other copies at all, and then the honest
     * answer is the original, because the ad still has to appear.
     *
     * Copies that were cut to a fixed shape are skipped. A grid draws an ad into the space the
     * advertiser's picture has, and a copy cropped to someone else's proportions would bend it.
     */
    private function display_variant($attachment_id, $draw_width, $draw_height, array $metadata, $original_url) {
        if ($draw_width < 1 || $draw_height < 1 || !function_exists('wp_get_attachment_image_src')) {
            return null;
        }

        $original_width = absint($metadata['width'] ?? 0);
        $original_height = absint($metadata['height'] ?? 0);
        if ($original_width < 1 || $original_height < 1) {
            return null;
        }

        // Room for a high-density screen and for zooming in, without reaching for the original.
        $needed_width = min($original_width, $draw_width * 2);
        $needed_height = min($original_height, $draw_height * 2);
        if ($needed_width >= $original_width && $needed_height >= $original_height) {
            return null;
        }

        $sizes = isset($metadata['sizes']) && is_array($metadata['sizes']) ? $metadata['sizes'] : array();
        $chosen_name = '';
        $chosen_area = 0;
        foreach ($sizes as $name => $size) {
            if (!is_array($size) || !is_string($name) || '' === $name) {
                continue;
            }
            $width = absint($size['width'] ?? 0);
            $height = absint($size['height'] ?? 0);
            if ($width < $needed_width || $height < $needed_height) {
                continue;
            }
            if (!empty($size['crop'])) {
                continue;
            }
            if ($width >= $original_width && $height >= $original_height) {
                continue;
            }
            $area = $width * $height;
            if ('' === $chosen_name || $area < $chosen_area) {
                $chosen_name = $name;
                $chosen_area = $area;
            }
        }

        if ('' === $chosen_name) {
            return null;
        }

        $chosen = wp_get_attachment_image_src($attachment_id, $chosen_name);
        if (!is_array($chosen) || empty($chosen[0])) {
            return null;
        }

        $candidate = (string) $chosen[0];
        if ('' === $candidate || $candidate === $original_url) {
            return null;
        }

        return array(
            'url' => esc_url_raw($candidate),
            'width' => absint($chosen[1] ?? 0),
            'height' => absint($chosen[2] ?? 0),
        );
    }
}
