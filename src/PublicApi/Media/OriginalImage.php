<?php
/**
 * Stable original-image resolver.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Media;

if (!defined('ABSPATH')) {
    exit;
}

final class OriginalImage {

    public static function resolve($attachment_id): array {
        $resolved = (new \MillionDollarScript\V3\Media\OriginalImage())->resolve(absint($attachment_id));
        return is_array($resolved) ? $resolved : [];
    }
}
