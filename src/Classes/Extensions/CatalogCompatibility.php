<?php

namespace MillionDollarScript\Classes\Extensions;

/**
 * Adds legacy Million Dollar Script 2 compatibility hints to extension-server requests.
 */
class CatalogCompatibility {

    /**
     * Query parameters understood by mixed-generation extension servers.
     */
    public static function query_args(): array {
        return [
            'platform'       => 'mds2',
            'mds_generation' => '2',
            'core'           => 'milliondollarscript-two',
            'core_version'   => self::core_version(),
        ];
    }

    /**
     * Append compatibility query parameters to a URL.
     */
    public static function append_query(string $url, array $extra = []): string {
        return add_query_arg(array_merge(self::query_args(), $extra), $url);
    }

    /**
     * Add compatibility fields to a JSON request body.
     */
    public static function request_body(array $body): array {
        return array_merge($body, self::query_args());
    }

    /**
     * Resolve the MDS2 package version without relying on the shared MDS_VERSION constant.
     */
    private static function core_version(): string {
        $plugin_file = dirname(__DIR__, 3) . '/milliondollarscript-two.php';
        if (is_readable($plugin_file)) {
            $header = (string) file_get_contents($plugin_file, false, null, 0, 8192);
            if (preg_match('/^[ \t*#@\\/]*Version:\\s*([^\\r\\n]+)/mi', $header, $matches)) {
                return trim((string) $matches[1]);
            }
        }

        $composer = dirname(__DIR__, 3) . '/composer.json';
        if (is_readable($composer)) {
            $data = json_decode((string) file_get_contents($composer), true);
            if (is_array($data) && !empty($data['version']) && is_scalar($data['version'])) {
                return (string) $data['version'];
            }
        }

        if (defined('MDS_VERSION') && !defined('MDS3_VERSION')) {
            return (string) MDS_VERSION;
        }

        return '';
    }
}
