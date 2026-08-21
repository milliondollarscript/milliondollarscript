<?php
/**
 * Stable placement scheduling contract for trusted extensions.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Media;

use MillionDollarScript\V3\Media\PlacementSchedulingService;

if (!defined('ABSPATH')) {
    exit;
}

final class PlacementSchedules {
    /** @return array{items:array,total:int,limit:int,offset:int} */
    public static function query(array $args = []): array {
        return (new PlacementSchedulingService())->query($args);
    }

    /** @return array|\WP_Error|null */
    public static function find($placement_id) {
        return (new PlacementSchedulingService())->find(absint($placement_id));
    }

    /** @return true|\WP_Error */
    public static function preflight($placement_id, $activate = true) {
        return (new PlacementSchedulingService())->preflight(absint($placement_id), (bool) $activate);
    }

    /** @return array|\WP_Error */
    public static function set_visible($placement_id, $visible, array $context = []) {
        return (new PlacementSchedulingService())->set_visible(absint($placement_id), (bool) $visible, $context);
    }
}
