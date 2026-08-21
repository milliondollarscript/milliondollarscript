<?php
/**
 * Rendering estimates.
 *
 * @package MillionDollarScript\V3\Rendering
 */

namespace MillionDollarScript\V3\Rendering;

if (!defined('ABSPATH')) {
    exit;
}

final class Estimate {

    public static function grid(array $grid, array $sources = [], $tile_size = 256, $levels = 0) {
        $width = max(1, abs((int) ($grid['width'] ?? 1)));
        $height = max(1, abs((int) ($grid['height'] ?? 1)));
        $tile_size = max(64, abs((int) $tile_size));
        $max_dimension = max($width, $height);
        $max_level = max(0, abs((int) $levels));
        if (0 === $max_level && $max_dimension > 1) {
            $max_level = (int) ceil(log($max_dimension, 2));
        }

        $source_mp = 0.0;
        $largest_source_mp = 0.0;
        $largest_source_edge = 0;
        foreach ($sources as $source) {
            $source_width = max(0, abs((int) ($source['width'] ?? 0)));
            $source_height = max(0, abs((int) ($source['height'] ?? 0)));
            $source_megapixels = ($source_width * $source_height) / 1000000;
            $source_mp += $source_megapixels;
            $largest_source_mp = max($largest_source_mp, $source_megapixels);
            $largest_source_edge = max($largest_source_edge, $source_width, $source_height);
        }

        $tiles = 0;
        $pyramid_pixels = 0;
        $tile_levels = [];
        for ($level = 0; $level <= $max_level; $level++) {
            $scale = 2 ** ($max_level - $level);
            $level_width = max(1, (int) ceil($width / $scale));
            $level_height = max(1, (int) ceil($height / $scale));
            $columns = (int) ceil($level_width / $tile_size);
            $rows = (int) ceil($level_height / $tile_size);
            $level_tiles = $columns * $rows;
            $tiles += $level_tiles;
            $pyramid_pixels += $level_width * $level_height;
            $tile_levels[] = [
                'level' => $level,
                'width' => $level_width,
                'height' => $level_height,
                'columns' => $columns,
                'rows' => $rows,
                'tiles' => $level_tiles,
            ];
        }

        $grid_mp = round(($width * $height) / 1000000, 4);
        $grid_render_credits = max(10, (int) ceil($grid_mp * 3));
        $patch_jobs = count($sources);
        $patch_credits = $patch_jobs * 5;

        return [
            'width' => $width,
            'height' => $height,
            'grid_megapixels' => $grid_mp,
            'source_image_count' => $patch_jobs,
            'source_megapixels' => round($source_mp, 4),
            'largest_source_megapixels' => round($largest_source_mp, 4),
            'largest_source_edge_pixels' => $largest_source_edge,
            'tile_size' => $tile_size,
            'min_level' => 0,
            'max_level' => $max_level,
            'tile_level_count' => $max_level + 1,
            'tile_levels' => $tile_levels,
            'tile_estimate' => $tiles,
            'uncompressed_canvas_bytes' => $width * $height * 4,
            'uncompressed_pyramid_bytes' => $pyramid_pixels * 4,
            'storage_estimate_bytes' => $tiles * $tile_size * $tile_size * 4,
            'processing_credits' => $grid_render_credits,
            'estimated_patch_jobs' => $patch_jobs,
            'estimated_patch_processing_credits' => $patch_credits,
            'estimated_workflow_processing_credits' => $grid_render_credits + $patch_credits,
        ];
    }

    public static function quota(array $estimate, array $quota) {
        foreach ($quota as $key => $limit) {
            if (array_key_exists($key, $estimate) && (float) $estimate[$key] > (float) $limit) {
                return [
                    'ok' => false,
                    'failing_key' => (string) $key,
                    'actual' => $estimate[$key],
                    'limit' => $limit,
                ];
            }
        }

        return ['ok' => true, 'failing_key' => ''];
    }
}
