<?php
/**
 * Stable grid value object.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class Grid {
    private array $data;

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function id(): int {
        return absint($this->data['id'] ?? 0);
    }

    public function get(string $key, $default = null) {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    public function settings(): array {
        $settings = $this->data['settings'] ?? [];
        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }
        return is_array($settings) ? $settings : [];
    }

    public function to_array(): array {
        return $this->data;
    }
}
