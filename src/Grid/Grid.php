<?php
/**
 * Grid entity.
 *
 * @package MillionDollarScript\V3\Grid
 */

namespace MillionDollarScript\V3\Grid;

if (!defined('ABSPATH')) {
    exit;
}

final class Grid {

    private array $data;

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function id() {
        return absint($this->data['id'] ?? 0);
    }

    public function get($key, $default = null) {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    public function settings() {
        $settings = $this->get('settings', []);
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        return is_array($settings) ? $settings : [];
    }

    public function to_array() {
        $geometry = $this->geometry();
        $data = $this->data;
        $settings = $this->settings();
        $data['id'] = $this->id();
        $data['settings'] = $settings;
        $data['renderer_mode'] = GridRepository::normalize_renderer_mode($settings['renderer_mode'] ?? 'auto');
        $data['background_image'] = GridBackground::public_payload($settings);
        $data['virtual_blocks'] = [
            'rows' => $geometry->rows(),
            'columns' => $geometry->columns(),
            'total' => $geometry->total_blocks(),
        ];

        return $data;
    }

    public function geometry() {
        return new Geometry(
            $this->get('width', 1000),
            $this->get('height', 1000),
            $this->get('block_width', 10),
            $this->get('block_height', 10)
        );
    }
}
