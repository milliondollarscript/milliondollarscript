<?php
/**
 * MDS2 order item and placement import steps.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration\Concerns;

use MillionDollarScript\V3\Media\AdvertiserPageManager;
use MillionDollarScript\V3\Media\AdvertiserPageTitle;
use MillionDollarScript\V3\Media\AdvertiserPageUrls;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

trait ImportsLegacyOrderItemsAndPlacements {

    private function import_order_items() {
        $orders_table = $this->source->table('orders');
        if (!DB::table_exists($orders_table)) {
            return 0;
        }

        $count = 0;
        foreach ($this->legacy_rows('orders', ['order_id']) as $order) {
            $count += $this->import_order_items_row($order);
        }

        return $count;
    }

    private function import_order_items_row(array $order) {
        global $wpdb;

        $count = 0;
        $legacy_order_id = absint($order['order_id'] ?? 0);
        $target_order_id = $this->map->target_id($this->source->source_prefix(), 'order', $legacy_order_id, 'order');
        if (!$target_order_id) {
            $this->record_migration_skip('order_items', $legacy_order_id, __('No mapped target order exists.', 'million-dollar-script'));
            return 0;
        }

        $legacy_block_ids = $this->legacy_order_block_ids($order);
        if (!$legacy_block_ids) {
            $this->record_migration_skip('order_items', $legacy_order_id, __('No source block inventory could be reconciled; existing imported items were preserved.', 'million-dollar-script'));
            return 0;
        }

        $wpdb->delete(DB::table('order_items'), ['order_id' => $target_order_id]);
        $order['__reconciled_block_count'] = count($legacy_block_ids);
        $unit_price = $legacy_block_ids ? (float) ($order['price'] ?? 0) / max(1, count($legacy_block_ids)) : (float) ($order['price'] ?? 0);

        foreach ($legacy_block_ids as $legacy_block_id) {
            $target_block_id = $this->ensure_order_block($order, $legacy_block_id);
            if (!$target_block_id) {
                continue;
            }

            $block = $this->target_block($target_block_id);
            if (!$block) {
                continue;
            }

            $wpdb->insert(DB::table('order_items'), [
                'order_id' => $target_order_id,
                'grid_id' => absint($block['grid_id'] ?? 0),
                'block_id' => $target_block_id,
                'placement_id' => null,
                'item_type' => 'block',
                'quantity' => 1,
                'unit_price' => (float) ($block['price_override'] ?? $unit_price),
                'total' => (float) ($block['price_override'] ?? $unit_price),
                'metadata' => wp_json_encode([
                    'legacy_source' => 'mds2',
                    'legacy_order_id' => $legacy_order_id,
                    'legacy_block_id' => $legacy_block_id,
                    'x' => absint($block['x'] ?? 0),
                    'y' => absint($block['y'] ?? 0),
                    'width' => absint($block['width'] ?? 0),
                    'height' => absint($block['height'] ?? 0),
                ]),
                'created_at' => current_time('mysql', true),
            ]);

            if ($wpdb->insert_id) {
                $count++;
            }
        }

        return $count;
    }

    private function import_placements() {
        $orders_table = $this->source->table('orders');
        if (!DB::table_exists($orders_table)) {
            return 0;
        }

        $count = 0;
        foreach ($this->legacy_rows('orders', ['order_id']) as $order) {
            if ($this->import_placement_row($order)) {
                $count++;
            }
        }

        return $count;
    }

    private function import_placement_row(array $order) {
        global $wpdb;

        $legacy_order_id = absint($order['order_id'] ?? 0);
        $target_order_id = $this->map->target_id($this->source->source_prefix(), 'order', $legacy_order_id, 'order');
        if (!$target_order_id) {
            $this->record_migration_skip('placement', $legacy_order_id, __('No mapped target order exists.', 'million-dollar-script'));
            return 0;
        }

        $attachment_id = $this->attachment_for_order($order);
        if (!$attachment_id) {
            if ($this->order_has_flattened_image_data($legacy_order_id)) {
                $this->warnings[] = 'Order ' . $legacy_order_id . ' has flattened block image_data but no original attachment/file could be found.';
            }
            $this->record_migration_skip('placement', $legacy_order_id, __('No recoverable original image or attachment was found.', 'million-dollar-script'));
            return 0;
        }

        $rect = (new OrderRepository())->item_rect($target_order_id);
        if (!$rect) {
            $this->record_migration_skip('placement', $legacy_order_id, __('No reconciled order-item rectangle was available.', 'million-dollar-script'));
            return 0;
        }

        $link = $this->first_order_block_value($legacy_order_id, 'url');
        $alt = $this->first_order_block_value($legacy_order_id, 'alt_text');
        $ad_meta = $this->ad_meta(absint($order['ad_id'] ?? 0));
        $popup_text = (string) ($ad_meta['text'] ?? '');
        // MDS2 copied the ad popup text into blocks.alt_text, so a legacy
        // block alt is only a real description when it is not that same text;
        // the built-in popup layout renders both fields as separate lines.
        $alt_text = sanitize_text_field(wp_strip_all_tags((string) $alt));
        if ($alt_text !== '' && $alt_text === trim(wp_strip_all_tags($popup_text ?: $alt))) {
            $alt_text = '';
        }
        $legacy_ad_id = absint($order['ad_id'] ?? 0);
        $legacy_post = $legacy_ad_id && 'mds-pixel' === get_post_type($legacy_ad_id) ? get_post($legacy_ad_id) : null;
        $title_resolution = AdvertiserPageTitle::resolve([
            'id' => $legacy_order_id,
            'alt_text' => $alt_text,
            'popup_text' => $popup_text,
        ], $legacy_post);
        if (!empty($title_resolution['normalized']) && '' === trim(wp_strip_all_tags($popup_text)) && is_object($legacy_post)) {
            // Some MDS2 sites stored the full public description only in the
            // pixel-page title. Keep that content available while deriving a
            // concise heading independently.
            $popup_text = wp_kses_post((string) ($legacy_post->post_title ?? ''));
        }
        $payload = [
            'grid_id' => absint($rect['grid_id'] ?? 0),
            'block_id' => !empty($rect['block_id']) ? absint($rect['block_id']) : null,
            'order_id' => $target_order_id,
            'user_id' => !empty($order['user_id']) ? absint($order['user_id']) : null,
            'attachment_id' => $attachment_id,
            'x' => absint($rect['x'] ?? 0),
            'y' => absint($rect['y'] ?? 0),
            'width' => max(1, absint($rect['width'] ?? 1)),
            'height' => max(1, absint($rect['height'] ?? 1)),
            'fit_mode' => 'cover',
            'link_url' => esc_url_raw($link ?: ($ad_meta['url'] ?? '')),
            'alt_text' => $alt_text,
            'popup_text' => wp_kses_post($popup_text ?: $alt),
            'status' => self::placement_status($order['status'] ?? ''),
            'sort_order' => 0,
            'updated_at' => current_time('mysql', true),
        ];

        $target_id = $this->map->target_id($this->source->source_prefix(), 'placement', $legacy_order_id, 'placement');
        if (!$target_id) {
            $target_id = absint($wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id = %d ORDER BY id ASC LIMIT 1',
                $target_order_id
            )));
            if ($target_id) {
                $this->record_migration_repair('placement', $legacy_order_id, __('Recovered an existing placement whose migration-map row was missing.', 'million-dollar-script'));
            }
        }
        if ($target_id && $this->target_exists('placements', $target_id)) {
            $wpdb->update(DB::table('placements'), $payload, ['id' => $target_id]);
        } else {
            $payload['created_at'] = current_time('mysql', true);
            $wpdb->insert(DB::table('placements'), $payload);
            $target_id = absint($wpdb->insert_id);
        }

        if ($target_id) {
            $this->map->remember($this->source->source_prefix(), 'placement', $legacy_order_id, 'placement', $target_id, ['legacy_ad_id' => absint($order['ad_id'] ?? 0)]);
            $args = ['source_prefix' => $this->source->source_prefix()];
            if ($legacy_ad_id && 'mds-pixel' === get_post_type($legacy_ad_id)) {
                $page_map = $this->map->get($this->source->source_prefix(), 'mds-pixel', $legacy_ad_id, 'advertiser_page');
                $mapped_page_id = absint($page_map['mds3_id'] ?? 0);
                $claimed_placement_id = $mapped_page_id ? absint(get_post_meta($mapped_page_id, '_million_dollar_script_placement_id', true)) : 0;
                if ($claimed_placement_id && $claimed_placement_id !== $target_id && $this->target_exists('placements', $claimed_placement_id)) {
                    $this->record_migration_repair('advertiser_page', $legacy_order_id, __('A shared legacy advertiser URL remains attached to its first surviving placement; this historical order did not take ownership of that URL.', 'million-dollar-script'));
                } else {
                    $args['legacy_post_id'] = $legacy_ad_id;
                    AdvertiserPageUrls::import_legacy_base_history();
                    if (!empty($title_resolution['normalized'])) {
                        $this->record_migration_repair(
                            'advertiser_page',
                            $legacy_ad_id,
                            __('Normalized an oversized legacy advertiser title. Review the derived heading and retained description.', 'million-dollar-script')
                        );
                    }
                }
            }
            (new AdvertiserPageManager())->synchronize($target_id, $args);
        }

        return $target_id;
    }
}
