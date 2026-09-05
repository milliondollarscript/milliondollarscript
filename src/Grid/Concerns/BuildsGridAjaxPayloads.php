<?php
/**
 * Frontend AJAX payload builders.
 *
 * @package MillionDollarScript\V3\Grid
 */

namespace MillionDollarScript\V3\Grid\Concerns;

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Grid\Grid;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Grid\GridStats;
use MillionDollarScript\V3\Grid\PopupText;
use MillionDollarScript\V3\Media\OriginalImage;
use MillionDollarScript\V3\Media\PlacementFieldContract;
use MillionDollarScript\V3\Media\AdvertiserPageManager;
use MillionDollarScript\V3\Media\AdvertiserPageUrls;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

trait BuildsGridAjaxPayloads {

    private function settings() {
        $settings = get_option('mds3_settings', []);
        return wp_parse_args(is_array($settings) ? $settings : [], SettingsSchema::defaults());
    }

    private function interaction_payload(array $settings) {
        $target = (string) ($settings['link-target'] ?? '_blank');
        if (!in_array($target, ['_blank', '_self'], true)) {
            $target = '_blank';
        }

        $direct_links = 'YES' === strtoupper((string) ($settings['enable-cloaking'] ?? 'YES'));

        return [
            'link_target' => $target,
            'enable_cloaking' => $direct_links ? 'YES' : 'NO',
            'click_mode' => $direct_links ? 'direct' : 'redirect',
            'enable_mouseover' => SettingsSchema::sanitize('enable-mouseover', $settings['enable-mouseover'] ?? 'yes'),
            'tooltip_trigger' => SettingsSchema::sanitize('tooltip-trigger', $settings['tooltip-trigger'] ?? 'mouseenter'),
            'max_popup_size' => absint(SettingsSchema::sanitize('max-popup-size', $settings['max-popup-size'] ?? 320)),
            'max_image_size' => absint(SettingsSchema::sanitize('max-image-size', $settings['max-image-size'] ?? 300)),
        ];
    }

    private function selection_payload(array $settings, Grid $grid) {
        $grid_settings = $grid->settings();
        $accounts_optional = SettingsSchema::sanitize('accounts-optional', $settings['accounts-optional'] ?? 'yes');
        $user_logged_in = is_user_logged_in();

        return [
            'block_selection_mode' => SettingsSchema::sanitize('block-selection-mode', $settings['block-selection-mode'] ?? 'YES'),
            'selection_adjacency_mode' => SettingsSchema::sanitize('selection-adjacency-mode', $settings['selection-adjacency-mode'] ?? 'ADJACENT'),
            'accounts_optional' => $accounts_optional,
            'guest_email_required' => !$user_logged_in && 'yes' === $accounts_optional,
            'login_url' => esc_url_raw(wp_login_url(wp_get_referer() ?: home_url('/'))),
            'user_logged_in' => $user_logged_in,
            'min_blocks' => max(1, absint($grid_settings['min_blocks'] ?? 1)),
            'max_blocks' => absint($grid_settings['max_blocks'] ?? 0),
        ];
    }

    private function upload_payload(array $settings) {
        $url_mode = PlacementFieldContract::url_mode($settings);
        $text_mode = PlacementFieldContract::popup_text_mode($settings);

        return [
            'url_required' => PlacementFieldContract::is_required($url_mode),
            'url_visible' => PlacementFieldContract::is_visible($url_mode),
            'text_required' => PlacementFieldContract::is_required($text_mode),
            'text_visible' => PlacementFieldContract::is_visible($text_mode),
            'max_width' => absint(SettingsSchema::sanitize('max-upload-width', $settings['max-upload-width'] ?? 0)),
            'max_height' => absint(SettingsSchema::sanitize('max-upload-height', $settings['max-upload-height'] ?? 0)),
        ];
    }

    private function public_stats_payload(Grid $grid, array $settings) {
        $grid_settings = $grid->settings();
        if ('N' === strtoupper((string) ($grid_settings['show_public_stats'] ?? 'Y'))) {
            return ['visible' => false];
        }

        $stats = (new GridStats())->public_inventory($grid, $settings);
        $stats['visible'] = true;

        return $stats;
    }

    private function block_payload(array $block) {
        return [
            'id' => absint($block['id'] ?? 0),
            'grid_id' => absint($block['grid_id'] ?? 0),
            'x' => absint($block['x'] ?? 0),
            'y' => absint($block['y'] ?? 0),
            'width' => absint($block['width'] ?? 0),
            'height' => absint($block['height'] ?? 0),
            'status' => sanitize_key($block['status'] ?? 'available'),
            'price_override' => isset($block['price_override']) && '' !== (string) $block['price_override'] ? (float) $block['price_override'] : null,
        ];
    }

    private function grid_payload(Grid $grid) {
        $geometry = $grid->geometry();

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/public/grid/payload', [
            'id' => $grid->id(),
            'slug' => sanitize_title((string) $grid->get('slug', '')),
            'title' => sanitize_text_field((string) $grid->get('title', '')),
            'description' => wp_kses_post((string) $grid->get('description', '')),
            'width' => absint($grid->get('width', 0)),
            'height' => absint($grid->get('height', 0)),
            'block_width' => absint($grid->get('block_width', 0)),
            'block_height' => absint($grid->get('block_height', 0)),
            'price_per_block' => (float) $grid->get('price_per_block', 0),
            'currency' => sanitize_text_field((string) $grid->get('currency', '')),
            'status' => sanitize_key((string) $grid->get('status', '')),
            'renderer_mode' => GridRepository::normalize_renderer_mode($grid->settings()['renderer_mode'] ?? 'auto'),
            'background_image' => \MillionDollarScript\V3\Grid\GridBackground::public_payload($grid->settings()),
            'virtual_blocks' => [
                'rows' => $geometry->rows(),
                'columns' => $geometry->columns(),
                'total' => $geometry->total_blocks(),
            ],
        ], $grid);
    }

    private function include_runtime_block(array $block) {
        if ('unavailable' !== sanitize_key($block['status'] ?? 'available')) {
            return true;
        }

        $metadata = json_decode((string) ($block['metadata'] ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];

        return empty($metadata['availability_region_id']);
    }

    private function availability_region_payload(array $region) {
        return [
            'id' => sanitize_key((string) ($region['id'] ?? '')),
            'row_from' => absint($region['row_from'] ?? 0),
            'row_to' => absint($region['row_to'] ?? 0),
            'col_from' => absint($region['col_from'] ?? 0),
            'col_to' => absint($region['col_to'] ?? 0),
            'count' => absint($region['count'] ?? 0),
            'virtual' => !empty($region['virtual']),
        ];
    }

    private function placement_payload(array $placement, array $settings = [], $mask = null, $order = null, array $legacy_page_urls = []) {
        $source = (new OriginalImage())->resolve($placement['attachment_id'] ?? 0);
        $manage_url = $this->placement_manage_url($placement, $order);
        $link_url = PlacementFieldContract::advertiser_url($placement['link_url'] ?? '');
        $popup_text = (string) ($placement['popup_text'] ?? '');
        $popup_text_html = $this->popup_text_html($popup_text, $settings);
        $placement_id = absint($placement['id'] ?? 0);
        $advertiser_page_url = ($placement_id && $this->popup_page_link_enabled($settings))
            ? ((new AdvertiserPageManager())->public_url($placement_id) ?: (string) ($legacy_page_urls[$placement_id] ?? ''))
            : '';

        $payload = [
            'id' => absint($placement['id'] ?? 0),
            'grid_id' => absint($placement['grid_id'] ?? 0),
            'block_id' => absint($placement['block_id'] ?? 0),
            'order_id' => absint($placement['order_id'] ?? 0),
            'attachment_id' => absint($placement['attachment_id'] ?? 0),
            'x' => absint($placement['x'] ?? 0),
            'y' => absint($placement['y'] ?? 0),
            'width' => absint($placement['width'] ?? 0),
            'height' => absint($placement['height'] ?? 0),
            'mask' => is_array($mask) ? $mask : $this->placement_mask_payload(absint($placement['order_id'] ?? 0)),
            'fit_mode' => sanitize_key($placement['fit_mode'] ?? 'cover'),
            'link_url' => $link_url,
            'click_url' => $this->placement_click_url($placement, $settings, $link_url),
            'alt_text' => sanitize_text_field($placement['alt_text'] ?? ''),
            'popup_text' => PopupText::plain($popup_text),
            'popup_text_html' => $popup_text_html,
            'advertiser_page_url' => esc_url_raw($advertiser_page_url),
            'advertiser_page_label' => sanitize_text_field((string) ($settings['advertiser-page-popup-label'] ?? __('View advertiser page', 'million-dollar-script'))),
            'advertiser_page_target' => '_blank' === ($settings['advertiser-page-link-target'] ?? '_self') ? '_blank' : '_self',
            'status' => sanitize_key($placement['status'] ?? 'pending'),
            'manage_url' => $manage_url,
            'source' => [
                'url' => $source['url'] ?? '',
                'width' => absint($source['width'] ?? 0),
                'height' => absint($source['height'] ?? 0),
                'megapixels' => (float) ($source['megapixels'] ?? 0),
                'mime_type' => sanitize_text_field($source['mime_type'] ?? ''),
            ],
        ];
        $payload['popover_html'] = $this->popover_html($payload, $settings);

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/placement/payload', $payload, $placement, $settings);
    }

    private function popup_page_link_enabled(array $settings) {
        return 'yes' === SettingsSchema::sanitize('advertiser-page-popup-link', $settings['advertiser-page-popup-link'] ?? 'yes');
    }

    /**
     * Legacy MDS2 pixel page URLs, used while MDS 3.0 advertiser pages are off.
     *
     * @param array $settings Resolved MDS 3.0 settings.
     * @param array $placement_ids MDS 3.0 placement IDs.
     * @return array<int,string> Placement ID to public legacy URL.
     */
    private function legacy_popup_page_urls(array $settings, array $placement_ids) {
        if (!$this->popup_page_link_enabled($settings) || AdvertiserPageUrls::enabled($settings)) {
            return [];
        }

        return (new AdvertiserPageManager())->legacy_public_urls($placement_ids);
    }

    private function placement_manage_url(array $placement, ?array $order) {
        $user_id = is_array($order) ? absint($order['user_id'] ?? 0) : 0;
        if (!$user_id) {
            $user_id = absint($placement['user_id'] ?? 0);
        }
        if (!$user_id || $user_id !== get_current_user_id()) {
            return '';
        }

        $order_id = absint($order['id'] ?? ($placement['order_id'] ?? 0));
        $order_key = (string) ($order['order_key'] ?? '');
        if (!$order_id || '' === $order_key) {
            return '';
        }

        $page_id = absint(get_option('mds3_page_upload_id', 0));
        $url = $page_id ? get_permalink($page_id) : '';
        if (!$url) {
            return '';
        }

        return esc_url_raw(add_query_arg([
            'mds3_order_id' => $order_id,
            'mds3_order_key' => $order_key,
        ], $url));
    }

    private function popup_text_html($popup_text, array $settings) {
        $popup_text = (string) $popup_text;
        if ('' === trim($popup_text)) {
            return '';
        }

        return PopupText::html($popup_text, $settings);
    }

    private function popover_html(array $payload, array $settings) {
        $template = SettingsSchema::sanitize('popup-template', $settings['popup-template'] ?? '');
        if ('' === trim((string) $template)) {
            return '';
        }

        $alt_text = (string) ($payload['alt_text'] ?? '');
        $replacements = [
            '%title%' => '' !== trim($alt_text)
                ? esc_html($alt_text)
                : (string) ($payload['popup_text_html'] ?? ''),
            '%text%' => (string) ($payload['popup_text_html'] ?? ''),
            '%url%' => esc_html((string) ($payload['link_url'] ?? '')),
            '%alt_text%' => esc_html($alt_text),
            '%advertiser_page_url%' => esc_url((string) ($payload['advertiser_page_url'] ?? '')),
            '%advertiser_page_link%' => !empty($payload['advertiser_page_url'])
                ? '<a class="mds3-popover-page-link" href="' . esc_url((string) $payload['advertiser_page_url']) . '" target="' . esc_attr((string) ($payload['advertiser_page_target'] ?? '_self')) . '">' . esc_html((string) ($payload['advertiser_page_label'] ?? '')) . '</a>'
                : '',
        ];

        $html = strtr((string) $template, $replacements);
        $has_image = false !== stripos($html, '%image%') && !empty($payload['source']['url']);
        $meaningful_text = trim(wp_strip_all_tags(str_ireplace('%image%', '', $html)));
        if (!$has_image && '' === $meaningful_text) {
            return '';
        }

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/placement/popover/html', wp_kses_post($html), $payload, $settings);
    }

    private function placement_mask_payload($order_id) {
        if (!$order_id) {
            return [];
        }

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

    private function placement_click_url(array $placement, array $settings, $link_url) {
        if (!$link_url) {
            return '';
        }

        if ('YES' === strtoupper((string) ($settings['enable-cloaking'] ?? 'YES'))) {
            return $link_url;
        }

        return add_query_arg([
            'action' => 'mds3_click',
            'placement_id' => absint($placement['id'] ?? 0),
        ], admin_url('admin-ajax.php'));
    }
}
