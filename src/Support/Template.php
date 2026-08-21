<?php
/**
 * Small PHP template renderer for MDS3.
 *
 * @package MillionDollarScript\V3\Support
 */

namespace MillionDollarScript\V3\Support;

if (!defined('ABSPATH')) {
    exit;
}

final class Template {

    /**
     * Render a template directly to the response.
     *
     * Template files own context-appropriate escaping at each output point.
     * Keeping the final markup output here gives static analysis one reviewed
     * trust boundary instead of requiring suppressions at every call site.
     *
     * @param string      $template Relative template path below templates/.
     * @param array       $data     Template variables.
     * @param object|null $scope    Optional object scope for the include.
     * @return void
     */
    public static function display($template, array $data = [], $scope = null) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Included templates escape values according to their HTML context.
        echo self::render($template, $data, $scope);
    }

    /**
     * Render a template from the plugin templates directory.
     *
     * Templates are plain PHP and must escape values at the output point with
     * WordPress escaping helpers. The optional scope keeps legacy private admin
     * helpers callable while large render methods are moved into templates.
     *
     * @param string      $template Relative template path below templates/.
     * @param array       $data     Template variables.
     * @param object|null $scope    Optional object scope for the include.
     * @return string
     */
    public static function render($template, array $data = [], $scope = null) {
        $path = self::path($template);
        if (!$path) {
            return '';
        }

        $renderer = function ($template_path, array $template_data) {
            extract($template_data, EXTR_SKIP);
            include $template_path;
        };

        if (is_object($scope)) {
            $bound_renderer = \Closure::bind($renderer, $scope, get_class($scope));
            if ($bound_renderer instanceof \Closure) {
                $renderer = $bound_renderer;
            }
        }

        ob_start();
        $renderer($path, $data);

        return (string) ob_get_clean();
    }

    /**
     * Resolve a template path safely.
     *
     * @param string $template Relative template path.
     * @return string
     */
    private static function path($template) {
        $template = ltrim(str_replace('\\', '/', (string) $template), '/');
        if (!$template || false !== strpos($template, '..')) {
            return '';
        }

        $base = trailingslashit(MILLION_DOLLAR_SCRIPT_PATH . 'templates');
        $base_real = realpath($base);
        if (!$base_real) {
            return '';
        }

        $base_real = trailingslashit($base_real);
        $path = $base . $template;
        $real = realpath($path);

        if (!$real || 0 !== strpos($real, $base_real)) {
            return '';
        }

        return is_readable($real) ? $real : '';
    }
}
