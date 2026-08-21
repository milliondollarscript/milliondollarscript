<?php
/**
 * Stable grid queries for extensions.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Core;

use MillionDollarScript\V3\Grid\GridRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class Grids {

    /** @return Grid[] */
    public static function all(): array {
        return self::wrap((new GridRepository())->all());
    }

    /** @return Grid[] */
    public static function active(): array {
        return self::wrap((new GridRepository())->active());
    }

    public static function find($id): ?Grid {
        $grid = (new GridRepository())->find(absint($id));
        return $grid ? new Grid($grid->to_array()) : null;
    }

    public static function first_active(): ?Grid {
        $grid = (new GridRepository())->first_active();
        return $grid ? new Grid($grid->to_array()) : null;
    }

    private static function wrap(array $grids): array {
        $wrapped = [];
        foreach ($grids as $grid) {
            if (is_object($grid) && method_exists($grid, 'to_array')) {
                $wrapped[] = new Grid((array) $grid->to_array());
            }
        }
        return $wrapped;
    }
}
