<?php
/**
 * MDS2 media and attachment lookup helpers.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration\Concerns;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

trait ResolvesLegacyMedia {
    private function attachment_for_order(array $order) {
        $ad_id = absint($order['ad_id'] ?? 0);
        $attachment_id = $this->attachment_from_ad($ad_id);
        if ($attachment_id) {
            return $attachment_id;
        }

        $file = $this->first_order_block_value(absint($order['order_id'] ?? 0), 'file_name');

        return $file ? $this->attachment_from_path($file) : 0;
    }

    private function attachment_from_ad($ad_id) {
        $ad_id = absint($ad_id);
        if (!$ad_id || !get_post($ad_id)) {
            return 0;
        }

        foreach (['_milliondollarscript_image', 'milliondollarscript_image', '_mds_image', 'mds_image', '_thumbnail_id'] as $key) {
            $value = get_post_meta($ad_id, $key, true);
            $attachment_id = $this->attachment_id_from_value($value);
            if ($attachment_id) {
                return $attachment_id;
            }
        }

        return 0;
    }

    private function attachment_id_from_value($value) {
        if (is_array($value)) {
            foreach ($value as $item) {
                $attachment_id = $this->attachment_id_from_value($item);
                if ($attachment_id) {
                    return $attachment_id;
                }
            }

            return 0;
        }

        if (is_numeric($value) && wp_attachment_is_image(absint($value))) {
            return absint($value);
        }

        if (is_string($value) && preg_match('#^https?://#', $value)) {
            $attachment_id = attachment_url_to_postid($value);

            return $attachment_id && wp_attachment_is_image($attachment_id) ? absint($attachment_id) : 0;
        }

        if (is_string($value) && '' !== trim($value)) {
            return $this->attachment_from_path($value);
        }

        return 0;
    }

    private function attachment_from_path($path) {
        global $wpdb;

        $path = trim((string) $path);
        if (!$path) {
            return 0;
        }

        if (preg_match('#^https?://#', $path)) {
            $attachment_id = attachment_url_to_postid($path);

            return $attachment_id && wp_attachment_is_image($attachment_id) ? absint($attachment_id) : 0;
        }

        $upload = wp_upload_dir();
        $normalized_path = $this->normalize_legacy_media_path($path);
        $relative_upload_path = $this->relative_legacy_upload_path($normalized_path, $upload);
        $candidates = [];
        if ($this->legacy_path_is_absolute($normalized_path)) {
            $candidates[] = $normalized_path;
        }

        if ($relative_upload_path) {
            $candidates[] = trailingslashit($upload['basedir']) . $relative_upload_path;
        } elseif (!$this->legacy_path_is_absolute($normalized_path)) {
            $candidates[] = trailingslashit($upload['basedir']) . ltrim($normalized_path, '/');
        } else {
            $candidates[] = trailingslashit($upload['basedir']) . 'milliondollarscript/images/' . basename($normalized_path);
        }
        $candidates[] = trailingslashit($upload['basedir']) . 'milliondollarscript/images/' . basename($normalized_path);
        if (!$this->legacy_path_is_absolute($normalized_path) && !$relative_upload_path) {
            $candidates[] = trailingslashit($upload['basedir']) . 'milliondollarscript/' . ltrim($normalized_path, '/');
        }

        foreach (array_unique($candidates) as $candidate) {
            $candidate = $this->normalize_legacy_media_path($candidate);
            if (!is_readable($candidate)) {
                continue;
            }

            $relative = $this->relative_legacy_upload_path($candidate, $upload);
            if (!$relative) {
                continue;
            }

            $attachment_id = absint($wpdb->get_var(
                $wpdb->prepare(
                    'SELECT post_id FROM ' . DB::ident($wpdb->postmeta) . ' WHERE meta_key = %s AND meta_value = %s LIMIT 1',
                    '_wp_attached_file',
                    $relative
                )
            ));

            if ($attachment_id && wp_attachment_is_image($attachment_id)) {
                return $attachment_id;
            }

            $registered = $this->register_attachment($candidate, $relative);
            if ($registered) {
                return $registered;
            }
        }

        return 0;
    }

    private function normalize_legacy_media_path($path) {
        $path = (string) $path;

        return function_exists('wp_normalize_path') ? wp_normalize_path($path) : str_replace('\\', '/', $path);
    }

    private function legacy_path_is_absolute($path) {
        $path = $this->normalize_legacy_media_path($path);

        return str_starts_with($path, '/') || (bool) preg_match('/^[A-Za-z]:\//', $path);
    }

    private function relative_legacy_upload_path($path, array $upload) {
        $path = $this->normalize_legacy_media_path($path);
        $base = trailingslashit($this->normalize_legacy_media_path($upload['basedir'] ?? ''));
        if ($base && str_starts_with($path, $base)) {
            return ltrim(substr($path, strlen($base)), '/');
        }

        if (preg_match('#(?:^|/)wp-content/uploads/(.+)$#i', $path, $matches)) {
            return ltrim($matches[1], '/');
        }

        return '';
    }

    private function register_attachment($path, $relative) {
        $filetype = wp_check_filetype($path);
        if (empty($filetype['type']) || 0 !== strpos($filetype['type'], 'image/')) {
            return 0;
        }

        $attachment_id = wp_insert_attachment([
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name(pathinfo($path, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        ], $path);

        if (is_wp_error($attachment_id) || !$attachment_id) {
            return 0;
        }

        update_post_meta($attachment_id, '_wp_attached_file', $relative);

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $path);
        if (is_array($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        return absint($attachment_id);
    }

    private function first_order_block_value($legacy_order_id, $field) {
        global $wpdb;

        $table = $this->source->table('blocks');
        if (!DB::table_exists($table)) {
            return '';
        }

        $field = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $field));
        if (!$field) {
            return '';
        }

        return (string) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT ' . DB::ident($field) . ' FROM ' . DB::ident($table) . ' WHERE order_id = %d AND ' . DB::ident($field) . " <> '' ORDER BY block_id ASC LIMIT 1",
                absint($legacy_order_id)
            )
        );
    }

    private function order_has_flattened_image_data($legacy_order_id) {
        global $wpdb;

        $table = $this->source->table('blocks');
        if (!DB::table_exists($table)) {
            return false;
        }

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT block_id FROM ' . DB::ident($table) . " WHERE order_id = %d AND image_data <> '' LIMIT 1",
                absint($legacy_order_id)
            )
        );
    }

    private function ad_meta($ad_id) {
        $ad_id = absint($ad_id);
        if (!$ad_id) {
            return [];
        }

        return [
            'url' => (string) (get_post_meta($ad_id, '_milliondollarscript_url', true) ?: get_post_meta($ad_id, 'milliondollarscript_url', true)),
            'text' => (string) (get_post_meta($ad_id, '_milliondollarscript_text', true) ?: get_post_meta($ad_id, 'milliondollarscript_text', true)),
        ];
    }

}
