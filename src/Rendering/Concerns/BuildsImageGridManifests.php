<?php
/**
 * ImageGrid preflight and manifest builders.
 *
 * @package MillionDollarScript\V3\Rendering
 */

namespace MillionDollarScript\V3\Rendering\Concerns;

use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\OriginalImage;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Rendering\Estimate;

if (!defined('ABSPATH')) {
    exit;
}

trait BuildsImageGridManifests {

    public function preflight($grid_id) {
        $grid = (new GridRepository())->find($grid_id);
        if (!$grid) {
            return new \WP_Error('mds3_grid_not_found', __('Grid not found.', 'million-dollar-script'), ['status' => 404]);
        }

        $placements = (new PlacementRepository())->for_grid($grid->id(), ['active', 'pending']);
        $sources = [];
        $source_payloads = [];
        $resolver = new OriginalImage();

        foreach ($placements as $placement) {
            $source = $resolver->resolve($placement['attachment_id'] ?? 0);
            if (!$source) {
                continue;
            }

            $sources[] = [
                'width' => absint($source['width'] ?? 0),
                'height' => absint($source['height'] ?? 0),
            ];
            $source_payloads[] = [
                'placement_id' => absint($placement['id'] ?? 0),
                'attachment_id' => absint($placement['attachment_id'] ?? 0),
                'width' => absint($source['width'] ?? 0),
                'height' => absint($source['height'] ?? 0),
                'megapixels' => (float) ($source['megapixels'] ?? 0),
                'mime_type' => sanitize_text_field($source['mime_type'] ?? ''),
            ];
        }

        $estimate = Estimate::grid($grid->to_array(), $sources, 256, $this->levels_for_grid($grid->to_array()));
        $quota = Estimate::quota($estimate, $this->quota());

        return [
            'grid_id' => $grid->id(),
            'provider' => $this->should_use_remote($estimate) ? 'imagegrid' : 'local',
            'estimate' => $estimate,
            'quota' => $quota,
            'sources' => $source_payloads,
            'account_url' => self::ACCOUNT_URL,
            'api_url_configured' => '' !== $this->api_url(),
            'remote_enabled' => $this->remote_enabled(),
        ];
    }

    public function manifest(array $grid, array $placements) {
        $resolver = new OriginalImage();
        $assets = [];

        foreach ($placements as $placement) {
            $source = $resolver->resolve($placement['attachment_id'] ?? 0);
            if (!$source) {
                continue;
            }

            $assets[] = [
                'placement_id' => absint($placement['id'] ?? 0),
                'attachment_id' => absint($placement['attachment_id'] ?? 0),
                'source_url' => esc_url_raw((string) ($source['url'] ?? '')),
                'source_width' => absint($source['width'] ?? 0),
                'source_height' => absint($source['height'] ?? 0),
                'x' => absint($placement['x'] ?? 0),
                'y' => absint($placement['y'] ?? 0),
                'width' => absint($placement['width'] ?? 0),
                'height' => absint($placement['height'] ?? 0),
                'fit_mode' => sanitize_key($placement['fit_mode'] ?? 'cover'),
                'crop' => [
                    'x' => isset($placement['crop_x']) ? (float) $placement['crop_x'] : null,
                    'y' => isset($placement['crop_y']) ? (float) $placement['crop_y'] : null,
                    'width' => isset($placement['crop_width']) ? (float) $placement['crop_width'] : null,
                    'height' => isset($placement['crop_height']) ? (float) $placement['crop_height'] : null,
                ],
                'focal' => [
                    'x' => isset($placement['focal_x']) ? (float) $placement['focal_x'] : null,
                    'y' => isset($placement['focal_y']) ? (float) $placement['focal_y'] : null,
                ],
            ];
        }

        return [
            'operation' => 'grid_render',
            'grid' => [
                'id' => absint($grid['id'] ?? 0),
                'width' => absint($grid['width'] ?? 0),
                'height' => absint($grid['height'] ?? 0),
                'block_width' => absint($grid['block_width'] ?? 0),
                'block_height' => absint($grid['block_height'] ?? 0),
            ],
            'assets' => $assets,
            'tile_size' => 256,
            'base_grid' => true,
            'detail_tiles' => true,
        ];
    }

    private function levels_for_grid(array $grid) {
        $max = max(absint($grid['width'] ?? 1), absint($grid['height'] ?? 1));

        return $max > 1 ? max(0, (int) ceil(log($max, 2))) : 0;
    }
}
