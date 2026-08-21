<?php
/**
 * Minimal compatibility layer for MDS-owned extensions.
 *
 * New extension code should use the MillionDollarScript public facades.
 *
 * @package MillionDollarScript
 */

namespace MDS\Core {
    if (!defined('ABSPATH')) {
        exit;
    }

    final class Grid {
        private ?\MillionDollarScript\Core\Grid $grid = null;

        public function get_all() {
            $grids = \MillionDollarScript\Core\Grids::all();
            $wrapped = [];
            foreach ($grids as $grid) {
                $copy = new self();
                $copy->grid = $grid;
                $wrapped[] = $copy;
            }

            return $wrapped;
        }

        public function get($id) {
            $found = \MillionDollarScript\Core\Grids::find($id);
            if (!$found) {
                return false;
            }

            $copy = new self();
            $copy->grid = $found;
            return $copy;
        }

        public function get_id() {
            return $this->grid ? $this->grid->id() : 0;
        }

        public function get_width() {
            return $this->grid ? (int) $this->grid->get('width') : 0;
        }

        public function get_height() {
            return $this->grid ? (int) $this->grid->get('height') : 0;
        }

        public function get_block_width() {
            return $this->grid ? (int) $this->grid->get('block_width') : 1;
        }

        public function get_block_height() {
            return $this->grid ? (int) $this->grid->get('block_height') : 1;
        }

        public function get_title() {
            return $this->grid ? (string) $this->grid->get('title') : '';
        }

        public function get_description() {
            return $this->grid ? (string) $this->grid->get('description') : '';
        }

        public function to_array() {
            return $this->grid ? $this->grid->to_array() : [];
        }
    }

    final class ExtensionServerClient {
        private string $base_url;

        public function __construct($base_url = '') {
            $settings = get_option('mds3_settings', []);
            $settings = is_array($settings) ? $settings : [];
            $this->base_url = $base_url ? rtrim((string) $base_url, '/') : \MillionDollarScript\V3\Extensions\ExtensionServer::base_url($settings);
        }

        public function list_extensions() {
            $catalog = new \MillionDollarScript\V3\Extensions\ExtensionCatalog();
            return $catalog->available();
        }
    }
}

namespace {
    if (!defined('ABSPATH')) {
        exit;
    }

    if (!function_exists('mds_get_option')) {
        function mds_get_option($key, $default = null) {
            $settings = get_option('mds3_settings', []);
            if (is_array($settings) && array_key_exists($key, $settings)) {
                return $settings[$key];
            }

            $aliases = [
                'delete-data' => 'delete_data_on_uninstall',
                'woocommerce' => 'payment_provider',
                'use_woocommerce_integration' => 'payment_provider',
            ];
            if (is_array($settings) && isset($aliases[$key]) && array_key_exists($aliases[$key], $settings)) {
                if (in_array($key, ['woocommerce', 'use_woocommerce_integration'], true)) {
                    return 'woocommerce' === (string) $settings[$aliases[$key]] ? 'yes' : 'no';
                }
                return $settings[$aliases[$key]];
            }

            if (class_exists('\MillionDollarScript\Core\Settings')) {
                $defaults = \MillionDollarScript\Core\Settings::defaults();
                if (array_key_exists($key, $defaults)) {
                    return $defaults[$key];
                }
                if (isset($aliases[$key]) && array_key_exists($aliases[$key], $defaults)) {
                    if (in_array($key, ['woocommerce', 'use_woocommerce_integration'], true)) {
                        return 'woocommerce' === (string) $defaults[$aliases[$key]] ? 'yes' : 'no';
                    }
                    return $defaults[$aliases[$key]];
                }
            }

            $legacy_map = [
                'mds_grid_background_color' => '#ffffff',
                'mds_grid_line_color' => '#999999',
                'mds_grid_line_width' => 1,
                'mds_grid_show_lines' => true,
                'mds_grid_use_debug_tiles' => false,
                'mds_require_adjacent_blocks' => false,
                'mds_grid_default_height' => '1000px',
            ];

            return array_key_exists($key, $legacy_map) ? $legacy_map[$key] : $default;
        }
    }

    if (!function_exists('mds_initialize')) {
        function mds_initialize() {
            \MillionDollarScript\V3\Plugin::instance()->boot();
        }
    }
}

namespace MDS\Core\Extensions {
    if (!defined('ABSPATH')) {
        exit;
    }

    final class Manager {
        private static ?Manager $instance = null;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public static function register($extension_data) {
            return \MillionDollarScript\Extensions\Registry::register((array) $extension_data);
        }
    }
}

namespace MDS\Media {
    if (!defined('ABSPATH')) {
        exit;
    }

    final class OriginalAttachmentResolver {
        public function resolve($attachment_id) {
            return \MillionDollarScript\Media\OriginalImage::resolve($attachment_id);
        }
    }
}

namespace MDS\Rendering {
    if (!defined('ABSPATH')) {
        exit;
    }

    final class RenderEstimate {
        public static function for_grid(array $grid, array $source_images = [], $tile_size = 256, $max_level = 0) {
            return \MillionDollarScript\Rendering\Estimate::grid($grid, $source_images, $tile_size, $max_level);
        }

        public static function compare_to_quota(array $estimate, array $quota) {
            return \MillionDollarScript\Rendering\Estimate::quota($estimate, $quota);
        }
    }
}
