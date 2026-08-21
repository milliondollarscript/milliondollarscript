<?php
/**
 * Stable rendering estimate facade.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Rendering;

if (!defined('ABSPATH')) {
    exit;
}

final class Estimate {

    public static function grid(array $grid, array $source_images = [], $tile_size = 256, $max_level = 0): array {
        return \MillionDollarScript\V3\Rendering\Estimate::grid($grid, $source_images, $tile_size, $max_level);
    }

    public static function quota(array $estimate, array $quota): array {
        return \MillionDollarScript\V3\Rendering\Estimate::quota($estimate, $quota);
    }
}
