<?php
/**
 * Sparse grid geometry.
 *
 * @package MillionDollarScript\V3\Grid
 */

namespace MillionDollarScript\V3\Grid;

if (!defined('ABSPATH')) {
    exit;
}

final class Geometry {

    private int $width;
    private int $height;
    private int $block_width;
    private int $block_height;

    public function __construct($width, $height, $block_width, $block_height) {
        $this->width = max(1, abs((int) $width));
        $this->height = max(1, abs((int) $height));
        $this->block_width = max(1, abs((int) $block_width));
        $this->block_height = max(1, abs((int) $block_height));
    }

    public function columns() {
        return max(1, intdiv($this->width, $this->block_width));
    }

    public function rows() {
        return max(1, intdiv($this->height, $this->block_height));
    }

    public function total_blocks() {
        return $this->columns() * $this->rows();
    }

    public function contains($row, $col) {
        $row = (int) $row;
        $col = (int) $col;

        return $row >= 0 && $col >= 0 && $row < $this->rows() && $col < $this->columns();
    }

    public function rect($row, $col) {
        if (!$this->contains($row, $col)) {
            return null;
        }

        return [
            'x' => (int) $col * $this->block_width,
            'y' => (int) $row * $this->block_height,
            'width' => $this->block_width,
            'height' => $this->block_height,
        ];
    }

    public function coordinate_from_pixel($x, $y) {
        $x = (int) $x;
        $y = (int) $y;

        if ($x < 0 || $y < 0 || $x >= $this->width || $y >= $this->height) {
            return null;
        }

        $row = intdiv($y, $this->block_height);
        $col = intdiv($x, $this->block_width);

        return $this->contains($row, $col) ? ['row' => $row, 'col' => $col] : null;
    }
}
