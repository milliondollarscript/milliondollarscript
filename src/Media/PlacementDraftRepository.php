<?php
/**
 * Server-side draft image storage for placement forms.
 *
 * @package MillionDollarScript\V3\Media
 */

namespace MillionDollarScript\V3\Media;

use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.
// phpcs:disable WordPress.Security.NonceVerification.Missing -- This repository is called only after the grid AJAX handler verifies its request nonce and order key.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Upload arrays are validated by UploadValidator before media_handle_upload().

final class PlacementDraftRepository {

    public const ORDER_META_KEY = 'placement_image_draft';

    private const ATTACHMENT_ORDER_ID = '_mds3_placement_draft_order_id';
    private const ATTACHMENT_TOKEN = '_mds3_placement_draft_token';
    private const ATTACHMENT_EXPIRES_AT = '_mds3_placement_draft_expires_at';
    private const ATTACHMENT_GRID_ID = '_mds3_placement_draft_grid_id';
    private const TTL = 259200;

    public function current(array $order) {
        $draft = $this->order_draft($order);
        if (!$this->valid_for_order($order, $draft)) {
            return null;
        }

        return $draft;
    }

    public function upload(OrderRepository $orders, array $order, array $rect, $field_name, array $settings) {
        if (empty($_FILES[$field_name]) || !is_array($_FILES[$field_name])) {
            return new \WP_Error('mds3_draft_upload_missing', __('Choose an image before uploading.', 'million-dollar-script'));
        }

        $file = $_FILES[$field_name];
        $filename = sanitize_file_name($file['name'] ?? '');
        $upload_check = (new UploadValidator())->validate($file, $filename, $settings);
        if (is_wp_error($upload_check)) {
            return $upload_check;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload($field_name, 0, [
            'post_title' => $this->attachment_title($filename, $order),
        ], [
            'test_form' => false,
            'mimes' => $this->allowed_mimes(),
        ]);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $previous = $this->current($order);
        $draft = $this->draft_record(absint($attachment_id), $order, $rect);
        $this->mark_attachment(absint($attachment_id), $draft);
        $updated = $this->save_order_draft($orders, $order, $draft);

        if (is_wp_error($updated)) {
            $this->delete_attachment_if_draft(absint($attachment_id), absint($order['id'] ?? 0), $draft['token']);
            return $updated;
        }

        if ($previous && absint($previous['attachment_id'] ?? 0) !== absint($attachment_id)) {
            $this->delete_attachment_if_draft(
                absint($previous['attachment_id'] ?? 0),
                absint($order['id'] ?? 0),
                (string) ($previous['token'] ?? '')
            );
        }

        return $draft;
    }

    public function remove(OrderRepository $orders, array $order, $token = '') {
        $draft = $this->current($order);
        if (!$draft) {
            return null;
        }

        $token = sanitize_text_field((string) $token);
        if ($token && !hash_equals((string) ($draft['token'] ?? ''), $token)) {
            return new \WP_Error('mds3_draft_token_mismatch', __('Draft image could not be verified.', 'million-dollar-script'));
        }

        $this->delete_attachment_if_draft(
            absint($draft['attachment_id'] ?? 0),
            absint($order['id'] ?? 0),
            (string) ($draft['token'] ?? '')
        );

        $updated = $this->save_order_draft($orders, $order, null);
        if (is_wp_error($updated)) {
            return $updated;
        }

        return $draft;
    }

    public function consume(OrderRepository $orders, array $order, $attachment_id = 0, $token = '') {
        $draft = $this->current($order);
        if (!$draft) {
            return null;
        }

        $attachment_id = absint($attachment_id);
        $token = sanitize_text_field((string) $token);
        if ($attachment_id && absint($draft['attachment_id'] ?? 0) !== $attachment_id) {
            return new \WP_Error('mds3_draft_attachment_mismatch', __('Draft image could not be verified.', 'million-dollar-script'));
        }
        if ($token && !hash_equals((string) ($draft['token'] ?? ''), $token)) {
            return new \WP_Error('mds3_draft_token_mismatch', __('Draft image could not be verified.', 'million-dollar-script'));
        }

        $this->clear_attachment_marks(absint($draft['attachment_id'] ?? 0));
        $updated = $this->save_order_draft($orders, $order, null);
        if (is_wp_error($updated)) {
            return $updated;
        }

        return $draft;
    }

    public function payload(array $draft) {
        $attachment_id = absint($draft['attachment_id'] ?? 0);
        $source = (new OriginalImage())->resolve($attachment_id);

        if (!$attachment_id || !$source) {
            return null;
        }

        return [
            'attachment_id' => $attachment_id,
            'token' => sanitize_text_field((string) ($draft['token'] ?? '')),
            'order_id' => absint($draft['order_id'] ?? 0),
            'grid_id' => absint($draft['grid_id'] ?? 0),
            'block_id' => absint($draft['block_id'] ?? 0),
            'x' => absint($draft['x'] ?? 0),
            'y' => absint($draft['y'] ?? 0),
            'width' => max(1, absint($draft['width'] ?? 1)),
            'height' => max(1, absint($draft['height'] ?? 1)),
            'mask' => $this->mask_payload(absint($draft['order_id'] ?? 0)),
            'uploaded_at' => sanitize_text_field((string) ($draft['uploaded_at'] ?? '')),
            'expires_at' => sanitize_text_field((string) ($draft['expires_at'] ?? '')),
            'source' => [
                'url' => esc_url_raw((string) ($source['url'] ?? '')),
                'width' => absint($source['width'] ?? 0),
                'height' => absint($source['height'] ?? 0),
                'megapixels' => (float) ($source['megapixels'] ?? 0),
                'mime_type' => sanitize_text_field((string) ($source['mime_type'] ?? '')),
            ],
        ];
    }

    public function cleanup_stale($limit = 100) {
        $ids = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => max(1, min(500, absint($limit))),
            'meta_query' => [
                [
                    'key' => self::ATTACHMENT_EXPIRES_AT,
                    'value' => time(),
                    'compare' => '<=',
                    'type' => 'NUMERIC',
                ],
            ],
        ]);

        $deleted = 0;
        foreach (array_map('absint', is_array($ids) ? $ids : []) as $attachment_id) {
            if ($this->attachment_used_by_placement($attachment_id)) {
                $this->clear_attachment_marks($attachment_id);
                continue;
            }

            if (wp_delete_attachment($attachment_id, true)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function draft_record($attachment_id, array $order, array $rect) {
        return [
            'attachment_id' => absint($attachment_id),
            'token' => wp_generate_uuid4(),
            'order_id' => absint($order['id'] ?? 0),
            'grid_id' => absint($rect['grid_id'] ?? 0),
            'block_id' => absint($rect['block_id'] ?? 0),
            'x' => absint($rect['x'] ?? 0),
            'y' => absint($rect['y'] ?? 0),
            'width' => max(1, absint($rect['width'] ?? 1)),
            'height' => max(1, absint($rect['height'] ?? 1)),
            'uploaded_at' => gmdate('Y-m-d H:i:s'),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + self::TTL),
        ];
    }

    private function mark_attachment($attachment_id, array $draft) {
        update_post_meta($attachment_id, self::ATTACHMENT_ORDER_ID, absint($draft['order_id'] ?? 0));
        update_post_meta($attachment_id, self::ATTACHMENT_TOKEN, sanitize_text_field((string) ($draft['token'] ?? '')));
        update_post_meta($attachment_id, self::ATTACHMENT_GRID_ID, absint($draft['grid_id'] ?? 0));
        update_post_meta($attachment_id, self::ATTACHMENT_EXPIRES_AT, time() + self::TTL);
    }

    private function clear_attachment_marks($attachment_id) {
        $attachment_id = absint($attachment_id);
        if (!$attachment_id) {
            return;
        }

        delete_post_meta($attachment_id, self::ATTACHMENT_ORDER_ID);
        delete_post_meta($attachment_id, self::ATTACHMENT_TOKEN);
        delete_post_meta($attachment_id, self::ATTACHMENT_GRID_ID);
        delete_post_meta($attachment_id, self::ATTACHMENT_EXPIRES_AT);
    }

    private function delete_attachment_if_draft($attachment_id, $order_id, $token) {
        $attachment_id = absint($attachment_id);
        if (!$attachment_id || $this->attachment_used_by_placement($attachment_id)) {
            $this->clear_attachment_marks($attachment_id);
            return false;
        }

        $marked_order = absint(get_post_meta($attachment_id, self::ATTACHMENT_ORDER_ID, true));
        $marked_token = (string) get_post_meta($attachment_id, self::ATTACHMENT_TOKEN, true);
        if ($marked_order !== absint($order_id) || !$marked_token || !hash_equals($marked_token, (string) $token)) {
            return false;
        }

        return (bool) wp_delete_attachment($attachment_id, true);
    }

    private function attachment_used_by_placement($attachment_id) {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DB::ident(DB::table('placements')) . ' WHERE attachment_id = %d',
                absint($attachment_id)
            )
        ) > 0;
    }

    private function save_order_draft(OrderRepository $orders, array $order, ?array $draft) {
        $order_id = absint($order['id'] ?? 0);
        $current = $orders->find($order_id) ?: $order;
        $metadata = $this->metadata($current);

        if ($draft) {
            $metadata[self::ORDER_META_KEY] = $draft;
        } else {
            unset($metadata[self::ORDER_META_KEY]);
        }

        return $orders->update($order_id, ['metadata' => $metadata]);
    }

    private function order_draft(array $order) {
        $metadata = $this->metadata($order);
        $draft = $metadata[self::ORDER_META_KEY] ?? null;

        return is_array($draft) ? $draft : null;
    }

    private function valid_for_order(array $order, $draft) {
        if (!is_array($draft)) {
            return false;
        }

        $order_id = absint($order['id'] ?? 0);
        $attachment_id = absint($draft['attachment_id'] ?? 0);
        $token = sanitize_text_field((string) ($draft['token'] ?? ''));
        if (!$order_id || !$attachment_id || !$token || absint($draft['order_id'] ?? 0) !== $order_id) {
            return false;
        }

        $expires_at = strtotime((string) ($draft['expires_at'] ?? ''));
        if ($expires_at && $expires_at <= time()) {
            return false;
        }

        if ('attachment' !== get_post_type($attachment_id) || !wp_attachment_is_image($attachment_id)) {
            return false;
        }

        $marked_order = absint(get_post_meta($attachment_id, self::ATTACHMENT_ORDER_ID, true));
        $marked_token = (string) get_post_meta($attachment_id, self::ATTACHMENT_TOKEN, true);

        return $marked_order === $order_id && $marked_token && hash_equals($marked_token, $token);
    }

    private function metadata(array $order) {
        $metadata = json_decode((string) ($order['metadata'] ?? ''), true);

        return is_array($metadata) ? $metadata : [];
    }

    private function mask_payload($order_id) {
        $mask = [];
        foreach ((new OrderRepository())->items($order_id) as $item) {
            $metadata = json_decode((string) ($item['metadata'] ?? ''), true);
            $metadata = is_array($metadata) ? $metadata : [];
            $x = array_key_exists('x', $metadata) ? absint($metadata['x']) : null;
            $y = array_key_exists('y', $metadata) ? absint($metadata['y']) : null;
            $width = absint($metadata['width'] ?? 0);
            $height = absint($metadata['height'] ?? 0);

            if (null === $x || null === $y || !$width || !$height) {
                continue;
            }

            $mask[] = [
                'x' => $x,
                'y' => $y,
                'width' => $width,
                'height' => $height,
            ];
        }

        return $mask;
    }

    private function attachment_title($filename, array $order) {
        $title = $filename ? pathinfo($filename, PATHINFO_FILENAME) : '';
        $order_id = absint($order['id'] ?? 0);

        /* translators: %d: order ID. */
        return sanitize_text_field($title ?: sprintf(__('Order %d draft image', 'million-dollar-script'), $order_id));
    }

    private function allowed_mimes() {
        return [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
    }
}
