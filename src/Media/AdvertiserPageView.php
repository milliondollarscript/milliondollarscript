<?php
/**
 * Privacy-safe advertiser page view model and renderer.
 *
 * @package MillionDollarScript\V3\Media
 */

namespace MillionDollarScript\V3\Media;

use MillionDollarScript\V3\Grid\GridPostType;
use MillionDollarScript\V3\Grid\PopupText;

if (!defined('ABSPATH')) {
    exit;
}

final class AdvertiserPageView {

    public function model($placement_id) {
        $manager = new AdvertiserPageManager();
        $placement = $manager->public_source(absint($placement_id));
        if (!$placement || !$manager->is_public_source($placement) || !AdvertiserPageUrls::enabled()) {
            return null;
        }

        $image = wp_get_attachment_image_src(absint($placement['attachment_id'] ?? 0), 'large');
        $settings = AdvertiserPageUrls::settings();
        $model = [
            'placement_id' => absint($placement['id'] ?? 0),
            'title' => sanitize_text_field(get_the_title(absint($placement['public_post_id'] ?? 0))),
            'alt_text' => sanitize_text_field((string) ($placement['alt_text'] ?? '')),
            'popup_text_html' => PopupText::html((string) ($placement['popup_text'] ?? ''), $settings),
            'advertiser_url' => esc_url_raw((string) ($placement['link_url'] ?? '')),
            'advertiser_link_target' => '_self' === ($settings['advertiser-page-link-target'] ?? '_blank') ? '_self' : '_blank',
            'image' => $image ? [
                'url' => esc_url_raw((string) ($image[0] ?? '')),
                'width' => absint($image[1] ?? 0),
                'height' => absint($image[2] ?? 0),
            ] : [],
            'grid' => [
                'id' => absint($placement['grid_id'] ?? 0),
                'title' => sanitize_text_field((string) ($placement['grid_title'] ?? '')),
                'url' => esc_url_raw(GridPostType::page_url(absint($placement['grid_id'] ?? 0))),
            ],
            'placement' => [
                'x' => absint($placement['x'] ?? 0),
                'y' => absint($placement['y'] ?? 0),
                'width' => absint($placement['width'] ?? 0),
                'height' => absint($placement['height'] ?? 0),
            ],
        ];

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/advertiser/page/view-model', $model, absint($placement_id));
    }

    public function render($placement_id) {
        $advertiser_page = $this->model($placement_id);
        if (!is_array($advertiser_page)) {
            return '';
        }

        $template = MILLION_DOLLAR_SCRIPT_PATH . 'templates/frontend/advertiser-page.php';
        ob_start();
        include $template;

        return (string) ob_get_clean();
    }
}
