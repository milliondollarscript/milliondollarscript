<?php
/**
 * MDS2 plugin detection and upgrade-choice helpers.
 *
 * @package MillionDollarScript\V3\Setup
 */

namespace MillionDollarScript\V3\Setup;

use MillionDollarScript\V3\Migration\LegacySource;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

final class LegacyPlugin {

    public const CHOICE_OPTION = 'mds3_mds2_upgrade_choice';

    private const KNOWN_PLUGIN_FILES = [
        'milliondollarscript-two/milliondollarscript-two.php',
        'milliondollarscript-two-dev/milliondollarscript-two.php',
        'milliondollarscript-two-main/milliondollarscript-two.php',
        'milliondollarscript/milliondollarscript.php',
        'million-dollar-script-two/milliondollarscript-two.php',
    ];

    private const KNOWN_PLUGIN_DIRS = [
        'milliondollarscript-two',
        'milliondollarscript-two-dev',
        'milliondollarscript-two-main',
        'milliondollarscript',
        'million-dollar-script-two',
    ];

    private const CORE_PLUGIN_NAMES = [
        'million dollar script',
        'million dollar script two',
        'milliondollarscript',
        'milliondollarscript two',
    ];

    /**
     * Return detected MDS2-era plugin installs.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function detected_plugins() {
        self::load_plugin_functions();

        if (!function_exists('get_plugins')) {
            return [];
        }

        $plugins = get_plugins();
        $detected = [];

        foreach ($plugins as $plugin_file => $data) {
            if (!self::is_legacy_plugin($plugin_file, is_array($data) ? $data : [])) {
                continue;
            }

            $detected[] = [
                'plugin_file' => $plugin_file,
                'name' => (string) ($data['Name'] ?? $plugin_file),
                'version' => (string) ($data['Version'] ?? ''),
                'active' => self::is_plugin_active($plugin_file),
                'network_active' => self::is_network_active($plugin_file),
            ];
        }

        return $detected;
    }

    /**
     * Return active MDS2-era plugin installs.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function active_plugins() {
        return array_values(array_filter(self::detected_plugins(), static function ($plugin) {
            return !empty($plugin['active']) || !empty($plugin['network_active']);
        }));
    }

    /**
     * Fast frontend-safe check used to avoid taking over MDS2 shortcode tags
     * while the old plugin is intentionally still active.
     *
     * @return bool
     */
    public static function has_active_legacy_plugin() {
        $active = array_merge(
            (array) get_option('active_plugins', []),
            array_keys((array) get_site_option('active_sitewide_plugins', []))
        );

        foreach ($active as $plugin_file) {
            $plugin_file = (string) $plugin_file;
            if (MILLION_DOLLAR_SCRIPT_BASENAME === $plugin_file) {
                continue;
            }

            if (in_array($plugin_file, self::KNOWN_PLUGIN_FILES, true) || str_contains($plugin_file, 'milliondollarscript-two/')) {
                return true;
            }
        }

        if (class_exists('\MillionDollarScript\Classes\Web\Shortcode', false)) {
            return true;
        }

        return defined('MDS_DB_VERSION') && defined('MDS_BASE_FILE') && (!defined('MILLION_DOLLAR_SCRIPT_FILE') || MDS_BASE_FILE !== MILLION_DOLLAR_SCRIPT_FILE);
    }

    /**
     * Source-data status for the setup wizard.
     *
     * @param string $source_prefix Optional source prefix.
     * @return array<string,mixed>
     */
    public static function source_status($source_prefix = '') {
        $source = new LegacySource($source_prefix);
        $tables = $source->table_report();
        $rows = 0;

        foreach (['banners', 'blocks', 'orders', 'packages', 'prices'] as $table) {
            $rows += absint($tables[$table]['rows'] ?? 0);
        }

        return [
            'source_prefix' => $source->source_prefix(),
            'has_data' => $rows > 0,
            'rows' => $rows,
            'tables' => $tables,
        ];
    }

    /**
     * Pick the most useful source-data status for setup.
     *
     * A previous dry run can leave a stale source prefix in settings. When the
     * default MDS2 prefix has more rows, prefer it so installing MDS3 over a
     * real MDS2 site does not point the first-run wizard at old fixture data.
     *
     * @param string $preferred_prefix Preferred source prefix from settings.
     * @return array<string,mixed>
     */
    public static function source_status_for_setup($preferred_prefix = '') {
        $preferred = self::source_status($preferred_prefix);
        $default = self::source_status('');

        if (
            (string) ($preferred['source_prefix'] ?? '') !== (string) ($default['source_prefix'] ?? '') &&
            !empty($default['has_data']) &&
            absint($default['rows'] ?? 0) > absint($preferred['rows'] ?? 0)
        ) {
            return $default;
        }

        return $preferred;
    }

    /**
     * Deactivate active MDS2-era plugins when the admin explicitly requests it.
     *
     * @return array<string,mixed>
     */
    public static function deactivate_active_plugins() {
        self::load_plugin_functions();

        $active = self::active_plugins();
        $deactivated = [];
        $skipped = [];

        foreach ($active as $plugin) {
            $plugin_file = (string) ($plugin['plugin_file'] ?? '');
            if (!$plugin_file) {
                continue;
            }

            if (!empty($plugin['network_active']) && !current_user_can('manage_network_plugins')) {
                $skipped[] = $plugin_file;
                continue;
            }

            if (function_exists('deactivate_plugins')) {
                deactivate_plugins($plugin_file, false, !empty($plugin['network_active']));
                $deactivated[] = $plugin_file;
            }
        }

        return [
            'deactivated' => $deactivated,
            'skipped' => $skipped,
        ];
    }

    /**
     * Record the user's explicit MDS2 handling choice.
     *
     * @param string $choice Choice key.
     * @return void
     */
    public static function set_choice($choice) {
        update_option(self::CHOICE_OPTION, sanitize_key($choice), false);
    }

    /**
     * Current explicit MDS2 handling choice.
     *
     * @return string
     */
    public static function choice() {
        return sanitize_key((string) get_option(self::CHOICE_OPTION, ''));
    }

    /**
     * Should MDS3 register old shortcode/block aliases on this request?
     *
     * @return bool
     */
    public static function should_register_legacy_embeds() {
        return !self::has_active_legacy_plugin();
    }

    /**
     * Sanitize a source table prefix.
     *
     * @param string $prefix Source prefix.
     * @return string
     */
    public static function sanitize_source_prefix($prefix) {
        global $wpdb;

        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', (string) $prefix);

        return '' !== $prefix ? $prefix : $wpdb->prefix . 'mds_';
    }

    /**
     * Does the expected legacy source contain useful tables?
     *
     * @param string $source_prefix Source prefix.
     * @return bool
     */
    public static function has_source_data($source_prefix = '') {
        $status = self::source_status($source_prefix);

        if (!empty($status['has_data'])) {
            return true;
        }

        $source = new LegacySource($source_prefix);

        return DB::table_exists($source->page_metadata_table()) || DB::table_exists($source->page_config_table());
    }

    /**
     * Include plugin admin helpers when available.
     *
     * @return void
     */
    private static function load_plugin_functions() {
        if (!function_exists('get_plugins') || !function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    /**
     * Identify legacy MDS installs without flagging MDS3 itself.
     *
     * @param string $plugin_file Plugin file.
     * @param array<string,mixed> $data Plugin header data.
     * @return bool
     */
    private static function is_legacy_plugin($plugin_file, array $data) {
        $plugin_file = (string) $plugin_file;
        if (MILLION_DOLLAR_SCRIPT_BASENAME === $plugin_file) {
            return false;
        }

        if (in_array($plugin_file, self::KNOWN_PLUGIN_FILES, true)) {
            return true;
        }

        $directory = sanitize_key((string) dirname($plugin_file));
        $basename = strtolower((string) basename($plugin_file));
        if (in_array($directory, self::KNOWN_PLUGIN_DIRS, true) && in_array($basename, ['milliondollarscript.php', 'milliondollarscript-two.php'], true)) {
            return true;
        }

        $name = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($data['Name'] ?? ''))));
        $text_domain = strtolower((string) ($data['TextDomain'] ?? ''));
        $version = preg_replace('/[^0-9.]/', '', (string) ($data['Version'] ?? ''));
        $looks_like_core = in_array($name, self::CORE_PLUGIN_NAMES, true);

        return $looks_like_core && 'milliondollarscript' === $text_domain && $version && version_compare($version, '3.0.0', '<');
    }

    /**
     * Is a plugin active on this site?
     *
     * @param string $plugin_file Plugin file.
     * @return bool
     */
    private static function is_plugin_active($plugin_file) {
        return function_exists('is_plugin_active') ? is_plugin_active($plugin_file) : in_array($plugin_file, (array) get_option('active_plugins', []), true);
    }

    /**
     * Is a plugin network-active?
     *
     * @param string $plugin_file Plugin file.
     * @return bool
     */
    private static function is_network_active($plugin_file) {
        return function_exists('is_plugin_active_for_network') && is_plugin_active_for_network($plugin_file);
    }
}
