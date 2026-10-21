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

            $item_id = $this->migration_insert(DB::table('order_items'), [
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
            ], 'order_item', $legacy_order_id . ':' . $legacy_block_id);

            if ($item_id) {
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

        $this->release_deleted_page_links();

        $count = 0;
        foreach ($this->legacy_rows('orders', ['order_id']) as $order) {
            if ($this->import_placement_row($order)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Whether a migrated creative comes out showing.
     *
     * The older plugin kept the money question and the showing question apart on purpose.
     * mds.orders.status is payment. mds.orders.approved is the site owner's review
     * decision, seeded from the grid's AUTO_APPROVE when the blocks were reserved
     * (Classes/Orders/Blocks.php:333), and the customer copy said so in plain words:
     * "Your order has been received and is pending approval. Your order will be published
     * as soon as the payment is cleared." A PAID order could legitimately sit unapproved.
     *
     * This plugin asks the same question one way - a placement is active, or pending until
     * someone publishes it - so the paid path can no longer answer it from payment alone.
     * Where the older site positively recorded that it was holding a record, the imported
     * placement stays pending: a site does not start showing work its owner was reviewing,
     * and the record lands in a queue with a control to publish it.
     *
     * Only a recorded no holds a record. mds.orders.published is deliberately NOT a gate:
     * it is written as 'N' whenever an order is created (Classes/Orders/Orders.php:843) and
     * a paid ad on an older site may simply never have had that flag flipped. An advertiser
     * who paid and then watched their ad disappear after an update is the worse of the two
     * mistakes, so the unapproved flag holds and the published flag is only ever reported.
     */
    private function placement_import_status(array $order, $legacy_order_id) {
        $status = self::placement_status($order['status'] ?? '', $this->legacy_order_paid((int) ($order['order_id'] ?? 0), (int) ($order['ad_id'] ?? 0)));
        if ('active' !== $status || !$this->legacy_record_is_held($order)) {
            return $status;
        }

        $this->record_migration_repair('placement', $legacy_order_id, __('Held for review: the older site recorded this order as not approved.', 'million-dollar-script'));

        return 'pending';
    }

    /**
     * True only when the source row says no. An empty column is not a no - older installs
     * left it unset, and treating unset as a refusal would hide paid ads.
     */
    private function legacy_record_is_held(array $order): bool
    {
        $approved = strtoupper(trim((string) ($order['approved'] ?? '')));

        return in_array($approved, ['N', 'NO', '0'], true);
    }

    /**
     * Free advertiser-page ids that no longer name a page.
     *
     * A migration attempt that stopped partway can leave placements pointing at
     * pages that its cleanup then removed. The dead ids stay claimed, so when a
     * later run creates a page and WordPress hands out that same post id, the
     * unique link rejects it and the ad is left with no page at all: on the
     * customer's site that cost 35 of 101 ads their page (Vikunja #30).
     */
    private function release_deleted_page_links() {
        global $wpdb;

        $page_type = \MillionDollarScript\V3\Media\AdvertiserPageManager::POST_TYPE;
        $count = (int) $wpdb->query(
            'UPDATE ' . DB::table('placements') . ' p LEFT JOIN ' . $wpdb->posts . ' o ON o.ID = p.public_post_id'
            . " SET p.public_post_id = NULL WHERE p.public_post_id IS NOT NULL AND (o.ID IS NULL OR o.post_type NOT IN ('" . $page_type . "', 'mds-pixel'))"
        );
        if ($count) {
            $this->record_migration_repair(
                'placement',
                'page-links',
                sprintf(_n(
                    '%s page link pointed at a page that no longer exists; it was cleared so a page could be created again.',
                    '%s page links pointed at pages that no longer exist; they were cleared so pages could be created again.',
                    $count,
                    'million-dollar-script'
                ), number_format_i18n($count))
            );
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
        // The full ad copy always goes to popup_text (longtext); alt_text is only
        // ever a short description, so the two are prepared separately.
        $popup_text_value = wp_kses_post($popup_text ?: $alt);
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
            'link_url' => $this->placement_link_url($link, (string) ($ad_meta['url'] ?? ''), $legacy_order_id),
            'alt_text' => $this->placement_alt_text($alt_text, wp_strip_all_tags($popup_text_value), (string) ($title_resolution['title'] ?? ''), $legacy_order_id),
            'popup_text' => $popup_text_value,
            'status' => $this->placement_import_status($order, $legacy_order_id),
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
            if (!$this->migration_update(DB::table('placements'), $payload, ['id' => $target_id], 'placement', $legacy_order_id)) {
                return 0;
            }
        } else {
            $payload['created_at'] = current_time('mysql', true);
            $target_id = $this->migration_insert(DB::table('placements'), $payload, 'placement', $legacy_order_id);
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

    /**
     * A usable link for the placement, from the legacy advertiser-link column and
     * then from the ad record.
     *
     * MDS2 stores whatever the site owner typed into that field, so a value that is
     * not a URL (ad copy, a note, a file path) is reported and left out rather than
     * stored as a broken link - or, once it overflowed the link column, taking the
     * whole placement with it. A bare domain keeps working by gaining a scheme, which
     * is also reported.
     *
     * The 500-character limit is the link_url column in src/Setup/InstallerSchema.php;
     * it is handled here rather than by widening the column, because a value that long
     * is not a usable link.
     */
    private function placement_link_url($legacy_value, string $ad_meta_url, $legacy_order_id): string
    {
        $limit = 500;
        foreach ([(string) $legacy_value, $ad_meta_url] as $candidate) {
            $candidate = trim((string) $candidate);
            if ('' === $candidate) {
                continue;
            }

            if (
                !preg_match('#^[a-z][a-z0-9+.\-]*://#i', $candidate)
                && preg_match('~^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}([/?#]|$)~i', $candidate)
            ) {
                $this->record_migration_repair('placement', $legacy_order_id, sprintf('The advertiser link "%s" had no protocol, so https:// was added.', self::migration_text_excerpt($candidate)));
                $candidate = 'https://' . $candidate;
            }

            $url = esc_url_raw($candidate);
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                if (strlen($url) > $limit) {
                    $this->record_migration_repair('placement', $legacy_order_id, sprintf('The advertiser link is %d characters, longer than the link field allows, so the link was not imported and the placement was.', strlen($url)));

                    return '';
                }

                return $url;
            }

            $this->record_migration_repair('placement', $legacy_order_id, sprintf('The advertiser link "%s" is not a link, so it was not imported.', self::migration_text_excerpt($candidate)));
        }

        return '';
    }

    /**
     * Short alt text that fits the alt column.
     *
     * MDS2 copied the entire ad text into blocks.alt_text, which both overflowed the
     * column (failing the insert and losing the placement outright, 39 of them on one
     * migrated site) and put paragraphs into an image alt attribute. The full copy
     * stays in popup_text; an oversized alt is replaced with the same concise heading
     * the advertiser page uses, and the substitution is reported.
     *
     * The 500-character limit is the alt_text column in src/Setup/InstallerSchema.php.
     * The column is deliberately not widened: alt_text is surfaced as an image alt and
     * as the advertiser page heading, so multi-paragraph ad prose does not belong
     * there - the full copy already has a home in popup_text (longtext).
     */
    private function placement_alt_text(string $alt_text, string $popup_text, string $heading, $legacy_order_id): string
    {
        $limit = 500;
        if ('' === $alt_text || self::migration_text_length($alt_text) <= $limit) {
            return $alt_text;
        }

        $short = trim($heading);
        if ('' === $short) {
            $short = trim(wp_strip_all_tags($popup_text));
        }
        if (self::migration_text_length($short) > $limit) {
            $short = (function_exists('mb_substr') ? mb_substr($short, 0, $limit - 1) : substr($short, 0, $limit - 1)) . '…';
        }

        $this->record_migration_repair('placement', $legacy_order_id, sprintf(
            'The alt text held the full ad text (%d characters), which does not fit the alt field. The complete copy was kept as the popup text and the alt was shortened to %d characters.',
            self::migration_text_length($alt_text),
            self::migration_text_length($short),
        ));

        return $short;
    }

    private static function migration_text_length(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }

    /**
     * A value quoted in the run report stays short enough to read in a list.
     */
    private static function migration_text_excerpt(string $text): string
    {
        return self::migration_text_length($text) <= 80 ? $text : (function_exists('mb_substr') ? mb_substr($text, 0, 79) : substr($text, 0, 79)) . '…';
    }
}
