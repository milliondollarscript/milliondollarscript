<?php
/**
 * Stable customer placement operations for extensions.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Media;

use MillionDollarScript\V3\Media\CustomerPlacementService;
use MillionDollarScript\V3\Media\AdvertiserPageManager;
use MillionDollarScript\V3\Media\AdvertiserPageView;
use MillionDollarScript\V3\Media\PlacementRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class Placements {

    /** @return array|\WP_Error */
    public static function update_for_principal($order_id, $placement_id, array $principal, array $data) {
        return (new CustomerPlacementService())->update(absint($order_id), absint($placement_id), $principal, $data);
    }

    /** @return array|\WP_Error */
    public static function replace_image_for_principal($order_id, $placement_id, array $principal, array $file) {
        return (new CustomerPlacementService())->replace_image(absint($order_id), absint($placement_id), $principal, $file);
    }

    /** Return the public page URL only when the placement is currently public. */
    public static function public_url($placement_id): string {
        return (new AdvertiserPageManager())->public_url(absint($placement_id));
    }

    /** Return the privacy-safe public view model, or null for a private placement. */
    public static function public_view($placement_id): ?array {
        $view = (new AdvertiserPageView())->model(absint($placement_id));

        return is_array($view) ? $view : null;
    }

    /**
     * Return the owning order ID for extension integrations.
     *
     * This deliberately exposes only the relationship identifier. Extensions
     * can request order data through the stable Orders facade without adding
     * private order or customer data to public placement view models.
     */
    public static function order_id($placement_id): int {
        $placement = (new PlacementRepository())->find(absint($placement_id));

        return is_array($placement) ? absint($placement['order_id'] ?? 0) : 0;
    }
}
