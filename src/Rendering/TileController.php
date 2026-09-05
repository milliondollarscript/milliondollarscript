<?php
/**
 * Local tile endpoint.
 *
 * @package MillionDollarScript\V3\Rendering
 */

namespace MillionDollarScript\V3\Rendering;

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Support\Component;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class TileController implements Component {

    public function register() {
        add_action('wp_ajax_mds3_tile', [$this, 'tile']);
        add_action('wp_ajax_nopriv_mds3_tile', [$this, 'tile']);
    }

    public function tile() {
        $query = wp_unslash($_GET);
        $grid_id = $this->query_absint($query, 'grid_id');
        $z = $this->query_tile_coordinate($query, 'z');
        $tile_x = $this->query_tile_coordinate($query, 'x');
        $tile_y = $this->query_tile_coordinate($query, 'y');
        $format = $this->query_format($query);
        $cache_key = $this->query_cache_key($query, 'v');

        $grid = (new GridRepository())->find($grid_id);
        if (!$grid || (!current_user_can('manage_options') && 'active' !== sanitize_key((string) $grid->get('status', '')))) {
            $this->png_error('grid not found');
        }

        if (!$cache_key) {
            $cache_key = self::cache_key($grid);
        }

        if (null === $z || null === $tile_x || null === $tile_y || !self::tile_coordinate_in_bounds($grid, $z, $tile_x, $tile_y)) {
            $this->png_error('tile out of bounds');
        }

        $remote = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/grid/remote/tile/request', null, $grid_id, $z, $tile_x, $tile_y);
        if ($this->proxy_remote($remote, $grid, $z, $tile_x, $tile_y, $cache_key, $format)) {
            exit;
        }

        if (!function_exists('imagecreatetruecolor')) {
            $this->png_error('gd missing');
        }

        $body = $this->local_tile_body($grid, $grid_id);
        if ('' === $body) {
            $this->png_error('tile unavailable');
        }

        $local_cache_key = self::cache_key($grid);
        $this->cache_tile_body($grid, $z, $tile_x, $tile_y, $local_cache_key, 'png', $body);

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=' . DAY_IN_SECONDS);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw PNG bytes cannot be escaped without corrupting the image response.
        echo $body;
        exit;
    }

    public static function cache_key($grid) {
        global $wpdb;

        $grid_id = is_object($grid) && method_exists($grid, 'id') ? absint($grid->id()) : 0;
        if (!$grid_id) {
            return 'grid';
        }

        $block_summary = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT COUNT(*) total, MAX(updated_at) updated_at FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id = %d',
                $grid_id
            ),
            ARRAY_A
        );

        $seed = [
            'grid_id' => $grid_id,
            'width' => absint($grid->get('width', 0)),
            'height' => absint($grid->get('height', 0)),
            'block_width' => absint($grid->get('block_width', 0)),
            'block_height' => absint($grid->get('block_height', 0)),
            'status' => sanitize_key((string) $grid->get('status', '')),
            'grid_updated_at' => sanitize_text_field((string) $grid->get('updated_at', '')),
            'blocks_total' => absint($block_summary['total'] ?? 0),
            'blocks_updated_at' => sanitize_text_field((string) ($block_summary['updated_at'] ?? '')),
        ];

        return substr(hash('sha256', wp_json_encode($seed)), 0, 20);
    }

    /**
     * Move a grid's cached tiles from one cache key directory to another.
     *
     * Used when rotating cache keys: the browser fetches the new tile URLs
     * and the server serves the existing files instead of regenerating them.
     *
     * @param int    $grid_id Grid ID.
     * @param string $old_key Previous cache key directory name.
     * @param string $new_key New cache key directory name.
     * @return bool True when files were moved.
     */
    public static function move_tile_cache($grid_id, $old_key, $new_key): bool {
        $grid_id = absint($grid_id);
        $old_key = sanitize_key((string) $old_key);
        $new_key = sanitize_key((string) $new_key);
        if (!$grid_id || !$old_key || !$new_key || $old_key === $new_key) {
            return false;
        }

        $upload = wp_upload_dir();
        if (!empty($upload['error']) || empty($upload['basedir'])) {
            return false;
        }

        $grid_dir = trailingslashit($upload['basedir']) . 'mds3-tiles/grid-' . $grid_id;
        $from = $grid_dir . '/' . $old_key;
        $to = $grid_dir . '/' . $new_key;
        if (!is_dir($from) || is_dir($to)) {
            return false;
        }

        return (bool) @rename($from, $to);
    }

    /**
     * Delete every locally cached grid tile (all grids, all cache keys).
     *
     * Tiles regenerate on the next public request.
     *
     * @return int Number of grid cache directories removed.
     */
    public static function clear_tile_cache(): int {
        $upload = wp_upload_dir();
        if (!empty($upload['error']) || empty($upload['basedir'])) {
            return 0;
        }

        $root = trailingslashit($upload['basedir']) . 'mds3-tiles';
        if (!is_dir($root)) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        $filesystem = new \WP_Filesystem_Direct(null);

        $removed = 0;
        foreach (scandir($root) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            if (is_dir(trailingslashit($root) . $entry) && $filesystem->delete(trailingslashit($root) . $entry, 'd', true)) {
                $removed++;
            }
        }
        $filesystem->delete($root, 'd', true);

        return $removed;
    }

    public static function public_tile_url_template($grid, $cache_key = '', $format = 'png') {
        $base = self::public_tile_base($grid, $cache_key);
        if (!$base) {
            return '';
        }

        return trailingslashit($base['url']) . '{z}/{x}/{y}.' . self::normalize_format($format);
    }

    public static function has_public_tile_cache($grid, $cache_key = '') {
        $base = self::public_tile_base($grid, $cache_key);

        return $base && is_dir($base['dir']);
    }

    public static function public_tile_cache_coverage($grid, $cache_key = '', $format = 'png', $tile_size = 256, $max_level = 0, $min_level = 0) {
        $base = self::public_tile_base($grid, $cache_key);
        $format = self::normalize_format($format);
        $tile_size = max(64, absint($tile_size));
        $max_level = max(0, absint($max_level));
        $max_level = $max_level ?: self::max_level_for_grid($grid, $tile_size);
        $min_level = min(max(0, absint($min_level)), $max_level);
        $listed_limit = (int) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/tile/cache/coverage/list/limit', 5000, $grid);
        $listed_limit = max(0, min(20000, $listed_limit));
        $tiles = [];
        $level_counts = [];
        $tile_count = 0;
        $expected_tile_count = 0;

        if (!$base || !is_dir($base['dir'])) {
            return [
                'strategy' => 'listed',
                'complete' => false,
                'minLevel' => $min_level,
                'maxLevel' => $max_level,
                'completeLevels' => [],
                'missingLevels' => range($min_level, $max_level),
                'tiles' => [],
                'tileCount' => 0,
                'listedTileCount' => 0,
                'expectedTileCount' => 0,
                'fullyListed' => true,
            ];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base['dir'], \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            if (strtolower((string) $file->getExtension()) !== $format) {
                continue;
            }

            $relative = ltrim(str_replace($base['dir'], '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $parts = explode(DIRECTORY_SEPARATOR, $relative);
            if (3 !== count($parts)) {
                continue;
            }

            $z = self::numeric_path_segment($parts[0]);
            $x = self::numeric_path_segment($parts[1]);
            $y = self::numeric_path_segment(pathinfo($parts[2], PATHINFO_FILENAME));
            if (null === $z || null === $x || null === $y) {
                continue;
            }

            $key = $z . '/' . $x . '/' . $y;
            $tile_count++;
            $level_counts[$z] = absint($level_counts[$z] ?? 0) + 1;
            if (count($tiles) < $listed_limit) {
                $tiles[] = $key;
            }
        }

        $complete_levels = [];
        $missing_levels = [];
        for ($level = $min_level; $level <= $max_level; $level++) {
            $expected = self::expected_tile_count_for_level($grid, absint($level), $max_level, $tile_size);
            $count = absint($level_counts[$level] ?? 0);
            $expected_tile_count += $expected;

            if ($expected > 0 && $count >= $expected) {
                $complete_levels[] = absint($level);
            } else {
                $missing_levels[] = absint($level);
            }
        }

        sort($complete_levels);
        sort($missing_levels);

        return [
            'strategy' => 'listed',
            'complete' => empty($missing_levels) && $expected_tile_count > 0,
            'minLevel' => $min_level,
            'maxLevel' => $max_level,
            'completeLevels' => $complete_levels,
            'missingLevels' => $missing_levels,
            'tiles' => $tiles,
            'tileCount' => $tile_count,
            'listedTileCount' => count($tiles),
            'expectedTileCount' => $expected_tile_count,
            'fullyListed' => $tile_count <= $listed_limit,
        ];
    }

    public static function max_level_for_grid($grid, $tile_size = 256) {
        $width = max(1, absint(is_object($grid) && method_exists($grid, 'get') ? $grid->get('width', 1) : 1));
        $height = max(1, absint(is_object($grid) && method_exists($grid, 'get') ? $grid->get('height', 1) : 1));
        $tile_size = max(1, absint($tile_size));
        $max_dimension = max($width, $height);

        if ($max_dimension <= $tile_size) {
            return 0;
        }

        return max(0, (int) ceil(log($max_dimension / $tile_size, 2)));
    }

    public static function tile_dimensions_for_level($grid, $level, $max_level = 0, $tile_size = 256) {
        $width = max(1, absint(is_object($grid) && method_exists($grid, 'get') ? $grid->get('width', 1) : 1));
        $height = max(1, absint(is_object($grid) && method_exists($grid, 'get') ? $grid->get('height', 1) : 1));
        $tile_size = max(1, absint($tile_size));
        $max_level = max(0, absint($max_level));
        if (!$max_level) {
            $max_level = self::max_level_for_grid($grid, $tile_size);
        }

        $level = max(0, min(absint($level), $max_level));
        $max_dimension = max($width, $height);
        $highest_resolution = $max_dimension > 0 ? $max_dimension / ($tile_size * pow(2, $max_level)) : 1;
        $highest_resolution = is_finite($highest_resolution) && $highest_resolution > 0 ? $highest_resolution : 1;
        $resolution = $highest_resolution * pow(2, $max_level - $level);
        $world_tile_size = max(1, $tile_size * $resolution);
        $columns = max(1, (int) ceil($width / $world_tile_size));
        $rows = max(1, (int) ceil($height / $world_tile_size));

        return [
            'columns' => $columns,
            'rows' => $rows,
            'count' => $columns * $rows,
            'level' => $level,
            'max_level' => $max_level,
        ];
    }

    private function local_tile_body($grid, $grid_id) {
        $tile_size = 256;
        $image = imagecreatetruecolor($tile_size, $tile_size);
        $bg = imagecolorallocate($image, 255, 255, 255);
        $line = imagecolorallocate($image, 226, 232, 240);
        $sold = imagecolorallocate($image, 37, 99, 235);
        $reserved = imagecolorallocate($image, 217, 119, 6);

        imagefilledrectangle($image, 0, 0, $tile_size, $tile_size, $bg);

        $geometry = $grid->geometry();
        $cols = min($geometry->columns(), 64);
        $rows = min($geometry->rows(), 64);
        $cell = max(2, (int) floor($tile_size / max($cols, $rows)));

        for ($x = 0; $x <= $cols; $x++) {
            imageline($image, $x * $cell, 0, $x * $cell, $rows * $cell, $line);
        }
        for ($y = 0; $y <= $rows; $y++) {
            imageline($image, 0, $y * $cell, $cols * $cell, $y * $cell, $line);
        }

        foreach ((new BlockRepository())->for_grid($grid_id) as $block) {
            $col = (int) floor(((int) $block['x']) / max(1, (int) $grid->get('block_width')));
            $row = (int) floor(((int) $block['y']) / max(1, (int) $grid->get('block_height')));
            if ($row >= $rows || $col >= $cols) {
                continue;
            }

            $color = 'reserved' === $block['status'] ? $reserved : $sold;
            imagefilledrectangle($image, $col * $cell + 1, $row * $cell + 1, ($col + 1) * $cell - 1, ($row + 1) * $cell - 1, $color);
        }

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    private function proxy_remote($request, $grid, $z, $x, $y, $cache_key, $format) {
        if (!is_array($request) || empty($request['url'])) {
            return false;
        }

        $url = esc_url_raw((string) $request['url']);
        if (!$url || !$this->valid_remote_url($url)) {
            return false;
        }

        $request_args = [
            'timeout' => max(1, min(60, absint($request['timeout'] ?? 20))),
            'redirection' => 0,
            'headers' => is_array($request['headers'] ?? null) ? $request['headers'] : [],
        ];
        $response = $this->is_local_remote_url($url)
            ? wp_remote_get($url, $request_args)
            : wp_safe_remote_get($url, $request_args);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) < 200 || (int) wp_remote_retrieve_response_code($response) >= 300) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if ('' === $body) {
            return false;
        }

        $content_type = $this->image_content_type(wp_remote_retrieve_header($response, 'content-type') ?: 'image/png');
        if (!$content_type) {
            return false;
        }

        $cache_format = $this->format_from_content_type($content_type) ?: $format;
        $this->cache_tile_body($grid, $z, $x, $y, $cache_key, $cache_format, $body);

        header('Content-Type: ' . $content_type);
        $cache_ttl = min(DAY_IN_SECONDS, max(0, absint($request['cache_ttl'] ?? DAY_IN_SECONDS)));
        header('Cache-Control: public, max-age=' . $cache_ttl);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated remote image bytes cannot be escaped without corrupting the response.
        echo $body;

        return true;
    }

    private function valid_remote_url($url) {
        $parts = wp_parse_url((string) $url);
        if (empty($parts['scheme']) || empty($parts['host']) || !in_array($parts['scheme'], ['http', 'https'], true)) {
            return false;
        }

        if ($this->is_local_remote_url($url)) {
            return in_array(wp_get_environment_type(), ['local', 'development'], true);
        }

        return (bool) wp_http_validate_url((string) $url);
    }

    private function is_local_remote_url($url) {
        $parts = wp_parse_url((string) $url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return in_array($host, ['localhost', '127.0.0.1', '::1', 'host.docker.internal'], true);
    }

    private function image_content_type($content_type) {
        $content_type = strtolower(trim((string) $content_type));
        $content_type = strtok($content_type, ';') ?: $content_type;
        $content_type = trim($content_type);

        if (!$content_type || !in_array($content_type, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return '';
        }

        return function_exists('sanitize_mime_type') ? sanitize_mime_type($content_type) : sanitize_text_field($content_type);
    }

    private function format_from_content_type($content_type) {
        $content_type = strtolower((string) $content_type);

        if (false !== strpos($content_type, 'webp')) {
            return 'webp';
        }
        if (false !== strpos($content_type, 'jpeg') || false !== strpos($content_type, 'jpg')) {
            return 'jpeg';
        }
        if (false !== strpos($content_type, 'png')) {
            return 'png';
        }

        return '';
    }

    private function query_absint(array $query, $key) {
        if (!array_key_exists($key, $query) || !is_scalar($query[$key])) {
            return 0;
        }

        return absint($query[$key]);
    }

    private function query_format(array $query) {
        if (!array_key_exists('format', $query) || !is_scalar($query['format'])) {
            return 'png';
        }

        return self::normalize_format($query['format']);
    }

    private function query_cache_key(array $query, $key) {
        if (!array_key_exists($key, $query) || !is_scalar($query[$key])) {
            return '';
        }

        return sanitize_key((string) $query[$key]);
    }

    private static function normalize_format($format) {
        $format = strtolower(sanitize_key((string) $format));
        if ('jpg' === $format) {
            $format = 'jpeg';
        }

        return in_array($format, ['png', 'jpeg', 'webp'], true) ? $format : 'png';
    }

    private static function numeric_path_segment($value) {
        $value = (string) $value;

        return preg_match('/^\d+$/', $value) ? absint($value) : null;
    }

    private static function expected_tile_count_for_level($grid, $level, $max_level, $tile_size) {
        return absint(self::tile_dimensions_for_level($grid, $level, $max_level, $tile_size)['count'] ?? 0);
    }

    private static function tile_coordinate_in_bounds($grid, $level, $x, $y) {
        $metadata = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/grid/remote/tile/metadata', [], $grid->id(), $grid);
        $metadata = is_array($metadata) ? $metadata : [];
        if ('deepzoom' === sanitize_key((string) ($metadata['level_scheme'] ?? ''))) {
            $tile_size = max(64, absint($metadata['tile_size'] ?? 256));
            $width = max(1, absint($grid->get('width', 1)));
            $height = max(1, absint($grid->get('height', 1)));
            $max_dimension = max($width, $height);
            $max_level = absint($metadata['max_level'] ?? 0);
            if (!$max_level && $max_dimension > 1) {
                $max_level = max(0, (int) ceil(log($max_dimension, 2)));
            }
            $min_level = min(absint($metadata['min_level'] ?? 0), $max_level);
            if ($level < $min_level || $level > $max_level) {
                return false;
            }

            $resolution = pow(2, $max_level - $level);
            $level_width = max(1, (int) ceil($width / $resolution));
            $level_height = max(1, (int) ceil($height / $resolution));
            $columns = max(1, (int) ceil($level_width / $tile_size));
            $rows = max(1, (int) ceil($level_height / $tile_size));

            return $x >= 0 && $y >= 0 && $x < $columns && $y < $rows;
        }

        $max_level = self::max_level_for_grid($grid, 256);
        if ($level < 0 || $level > $max_level) {
            return false;
        }

        $dimensions = self::tile_dimensions_for_level($grid, $level, $max_level, 256);

        return $x >= 0 && $y >= 0 && $x < absint($dimensions['columns'] ?? 0) && $y < absint($dimensions['rows'] ?? 0);
    }

    private static function public_tile_base($grid, $cache_key = '') {
        $grid_id = is_object($grid) && method_exists($grid, 'id') ? absint($grid->id()) : 0;
        if (!$grid_id) {
            return null;
        }

        $cache_key = sanitize_key($cache_key ?: self::cache_key($grid));
        if (!$cache_key) {
            return null;
        }

        $upload = wp_upload_dir();
        if (!empty($upload['error']) || empty($upload['basedir']) || empty($upload['baseurl'])) {
            return null;
        }

        $relative = 'mds3-tiles/grid-' . $grid_id . '/' . $cache_key;

        return [
            'dir' => trailingslashit($upload['basedir']) . $relative,
            'url' => trailingslashit($upload['baseurl']) . $relative,
        ];
    }

    private function cache_tile_body($grid, $z, $x, $y, $cache_key, $format, $body) {
        if ('active' !== sanitize_key((string) $grid->get('status', '')) || '' === (string) $body) {
            return;
        }

        $z = absint($z);
        $x = absint($x);
        $y = absint($y);
        $format = self::normalize_format($format);
        $base = self::public_tile_base($grid, $cache_key);
        if (!$base) {
            return;
        }

        $dir = trailingslashit($base['dir']) . $z . '/' . $x;
        if (!wp_mkdir_p($dir)) {
            return;
        }

        $file = trailingslashit($dir) . $y . '.' . $format;
        $tmp = $file . '.' . wp_generate_uuid4() . '.tmp';
        if (false === file_put_contents($tmp, $body, LOCK_EX)) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        $filesystem = new \WP_Filesystem_Direct(null);
        if (!$filesystem->move($tmp, $file, true)) {
            $filesystem->delete($tmp, false, 'f');
        }
    }

    private function png_error($message) {
        status_header(404);
        if (!function_exists('imagecreatetruecolor')) {
            exit;
        }

        $image = imagecreatetruecolor(256, 256);
        $bg = imagecolorallocate($image, 255, 255, 255);
        $fg = imagecolorallocate($image, 185, 28, 28);
        imagefilledrectangle($image, 0, 0, 256, 256, $bg);
        imagestring($image, 3, 12, 120, (string) $message, $fg);
        header('Content-Type: image/png');
        header('Cache-Control: no-cache');
        imagepng($image);
        imagedestroy($image);
        exit;
    }

    private function query_tile_coordinate(array $query, $key) {
        if (!array_key_exists($key, $query) || !is_scalar($query[$key])) {
            return null;
        }

        $value = trim((string) $query[$key]);
        if (!preg_match('/^\d+$/', $value)) {
            return null;
        }

        return absint($value);
    }
}
