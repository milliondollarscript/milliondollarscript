<?php
/**
 * Compatibility-friendly stable attachment resolver.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Media;

if (!defined('ABSPATH')) {
    exit;
}

final class OriginalAttachmentResolver {

    public function resolve($attachment_id): array {
        return OriginalImage::resolve($attachment_id);
    }
}
