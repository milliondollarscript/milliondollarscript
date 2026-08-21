<?php
/**
 * PHP memory requirement reporting.
 *
 * @package MillionDollarScript\V3\Support
 */

namespace MillionDollarScript\V3\Support;

if (!defined('ABSPATH')) {
    exit;
}

final class MemoryStatus implements Component {

    public const MINIMUM_BYTES = 268435456;

    /**
     * Register the WordPress Site Health test.
     *
     * @return void
     */
    public function register() {
        add_filter('site_status_tests', [$this, 'site_health_tests']);
    }

    /**
     * Add the Million Dollar Script PHP memory test.
     *
     * @param array $tests Site Health tests.
     * @return array
     */
    public function site_health_tests($tests) {
        $tests = is_array($tests) ? $tests : [];
        $tests['direct']['million-dollar-script-php-memory'] = [
            'label' => __('Million Dollar Script PHP memory', 'million-dollar-script'),
            'test' => [$this, 'site_health_result'],
        ];

        return $tests;
    }

    /**
     * Return the current Site Health result.
     *
     * @return array
     */
    public function site_health_result() {
        $status = self::status();
        $meets_minimum = !empty($status['meets_minimum']);

        return [
            'label' => $meets_minimum
                ? __('PHP memory meets the Million Dollar Script requirement', 'million-dollar-script')
                : __('PHP memory is below the Million Dollar Script requirement', 'million-dollar-script'),
            'status' => $meets_minimum ? 'good' : 'critical',
            'badge' => [
                'label' => __('Performance', 'million-dollar-script'),
                'color' => 'blue',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                esc_html(sprintf(
                    /* translators: 1: effective PHP memory limit, 2: required PHP memory limit. */
                    __('This site provides %1$s of PHP memory. Million Dollar Script requires at least %2$s for supported operation with checkout and extensions.', 'million-dollar-script'),
                    (string) $status['effective_label'],
                    (string) $status['minimum_label']
                ))
            ),
            'actions' => $meets_minimum ? '' : sprintf(
                '<p><a href="%1$s">%2$s</a></p>',
                esc_url(self::troubleshooting_url()),
                esc_html__('Review memory troubleshooting', 'million-dollar-script')
            ),
            'test' => 'million-dollar-script-php-memory',
        ];
    }

    /**
     * Return normalized memory status for admin presentation.
     *
     * @return array
     */
    public static function status() {
        $raw_limit = trim((string) ini_get('memory_limit'));
        $limit_bytes = self::bytes($raw_limit);
        $unlimited = $limit_bytes < 0;
        $meets_minimum = $unlimited || $limit_bytes >= self::MINIMUM_BYTES;

        return [
            'effective_bytes' => $limit_bytes,
            'effective_label' => $unlimited
                ? __('Unlimited', 'million-dollar-script')
                : self::format_bytes($limit_bytes),
            'meets_minimum' => $meets_minimum,
            'minimum_bytes' => self::MINIMUM_BYTES,
            'minimum_label' => self::format_bytes(self::MINIMUM_BYTES),
            'raw' => $raw_limit,
        ];
    }

    /**
     * Convert a PHP shorthand memory value to bytes.
     *
     * @param string $value Memory value.
     * @return int
     */
    public static function bytes($value) {
        $value = trim((string) $value);
        if ('' === $value) {
            return 0;
        }
        if ('-1' === $value) {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $bytes = (float) $value;
        if ('g' === $unit) {
            $bytes *= 1024;
            $unit = 'm';
        }
        if ('m' === $unit) {
            $bytes *= 1024;
            $unit = 'k';
        }
        if ('k' === $unit) {
            $bytes *= 1024;
        }

        return max(0, (int) round($bytes));
    }

    /**
     * Return the bundled troubleshooting guide URL.
     *
     * @return string
     */
    public static function troubleshooting_url() {
        return add_query_arg([
            'page' => 'mds3-docs',
            'doc' => 'million-dollar-script:troubleshooting',
        ], admin_url('admin.php'));
    }

    /**
     * Format bytes without depending on a specific locale separator.
     *
     * @param int $bytes Byte count.
     * @return string
     */
    private static function format_bytes($bytes) {
        $megabytes = max(0, (int) round(((int) $bytes) / 1048576));

        return sprintf(
            /* translators: %s: memory size in megabytes. */
            __('%s MB', 'million-dollar-script'),
            number_format_i18n($megabytes)
        );
    }
}
