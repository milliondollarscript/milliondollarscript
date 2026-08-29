<?php
/**
 * Activation and uninstall lifecycle.
 *
 * @package MillionDollarScript\V3\Setup
 */

namespace MillionDollarScript\V3\Setup;

use MillionDollarScript\V3\Extensions\ExtensionRuntime;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\AdvertiserPageManager;
use MillionDollarScript\V3\Orders\OrderCleanup;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class Installer {

    /**
     * Ensure schema/defaults exist for already-active installs.
     *
     * @return void
     */
    public static function ensure() {
        self::upgrade_legacy_user_facing_defaults();
        self::upgrade_early_alpha_saved_defaults();
        self::normalize_known_alpha_saved_defaults();
        self::cleanup_extension_legal_page_grid_metadata();

        if (get_option('mds3_db_version') === InstallerSchema::VERSION && InstallerSchema::required_tables_exist()) {
            OrderExpirationBackfill::maybe_schedule();
            return;
        }

        self::create_tables();
        self::set_defaults();
        self::upgrade_early_alpha_saved_defaults();
        self::normalize_known_alpha_saved_defaults();
        self::create_default_grid();
        update_option('mds3_ensure_grid_pages', 'yes', false);
        OrderExpirationBackfill::maybe_schedule();
    }

    /**
     * Activate plugin.
     *
     * @return void
     */
    public static function activate() {
        self::assert_requirements();
        self::create_tables();
        self::set_defaults();
        self::upgrade_early_alpha_saved_defaults();
        self::normalize_known_alpha_saved_defaults();
        add_option('mds3_setup_complete', 'no', '', false);
        set_transient('mds3_setup_redirect', 'yes', 90);
        update_option('mds3_flush_rewrite_rules', 'yes', false);
        OrderCleanup::schedule();
    }

    /**
     * Deactivate plugin.
     *
     * @return void
     */
    public static function deactivate() {
        OrderCleanup::unschedule();
        OrderExpirationBackfill::unschedule();
        flush_rewrite_rules();
    }

    /**
     * Uninstall plugin.
     *
     * Tables are retained by default because this product manages paid orders,
     * media placements, and migration state.
     *
     * @return void
     */
    public static function uninstall() {
        $settings = get_option('mds3_settings', []);
        $delete_data = is_array($settings) ? ($settings['delete_data_on_uninstall'] ?? '') : '';
        if ('' === (string) $delete_data) {
            $delete_data = get_option('mds3_delete_data_on_uninstall', 'no');
        }

        if ('yes' !== SettingsSchema::sanitize('delete_data_on_uninstall', $delete_data)) {
            return;
        }

        self::delete_advertiser_pages();
        InstallerSchema::drop_tables();
        self::delete_runtime_options();
    }

    private static function delete_advertiser_pages() {
        do {
            $post_ids = get_posts([
                'post_type' => AdvertiserPageManager::POST_TYPE,
                'post_status' => 'any',
                'fields' => 'ids',
                'posts_per_page' => 100,
                'no_found_rows' => true,
            ]);
            foreach ($post_ids as $post_id) {
                wp_delete_post(absint($post_id), true);
            }
        } while (!empty($post_ids));
    }

    /**
     * Remove core-owned options and transients without touching MDS2 data.
     *
     * @return void
     */
    private static function delete_runtime_options() {
        global $wpdb;

        $prefixes = [
            'mds3_',
            '_transient_mds3_',
            '_transient_timeout_mds3_',
        ];
        $option_names = [];

        foreach ($prefixes as $prefix) {
            $matches = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $wpdb->esc_like($prefix) . '%'
                )
            );
            $option_names = array_merge($option_names, is_array($matches) ? $matches : []);
        }

        foreach (array_unique($option_names) as $option_name) {
            delete_option((string) $option_name);
        }
    }

    /**
     * Create schema.
     *
     * Kept as a public wrapper because migration code uses it before imports.
     *
     * @return void
     */
    public static function create_tables() {
        InstallerSchema::create_tables();
    }

    /**
     * Set default options.
     *
     * @return void
     */
    public static function set_defaults() {
        $defaults = SettingsSchema::defaults();

        $settings = get_option('mds3_settings', []);
        update_option('mds3_settings', array_merge($defaults, is_array($settings) ? $settings : []), false);
        add_option('mds3_setup_complete', 'no', '', false);
    }

    /**
     * Validate runtime requirements.
     *
     * @return void
     */
    private static function assert_requirements() {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            wp_die(
                esc_html__('Million Dollar Script requires PHP 8.1 or newer.', 'million-dollar-script'),
                esc_html__('Plugin Activation Error', 'million-dollar-script'),
                ['back_link' => true]
            );
        }

        if (version_compare(get_bloginfo('version'), '6.0', '<')) {
            wp_die(
                esc_html__('Million Dollar Script requires WordPress 6.0 or newer.', 'million-dollar-script'),
                esc_html__('Plugin Activation Error', 'million-dollar-script'),
                ['back_link' => true]
            );
        }
    }

    /**
     * Clean up legacy default text that shipped before the public product name pass.
     *
     * @return void
     */
    private static function upgrade_legacy_user_facing_defaults() {
        if (get_option('mds3_upgraded_user_facing_defaults')) {
            return;
        }

        global $wpdb;

        if (DB::table_exists(DB::table('grids'))) {
            $wpdb->update(
                DB::table('grids'),
                ['description' => 'Your first Million Dollar Script advertising grid.'],
                ['description' => 'Your first Million Dollar Script advertising grid.'],
                ['%s'],
                ['%s']
            );
        }

        update_option('mds3_upgraded_user_facing_defaults', MILLION_DOLLAR_SCRIPT_VERSION, false);
    }

    /**
     * Normalize exact saved defaults from early alpha builds.
     *
     * These values shipped before the production defaults were finalized. Only
     * exact known defaults are changed, so user-entered custom settings are
     * preserved.
     *
     * @return void
     */
    private static function upgrade_early_alpha_saved_defaults() {
        if (get_option('mds3_upgraded_early_alpha_saved_defaults')) {
            return;
        }

        $settings = get_option('mds3_settings', []);
        if (!is_array($settings)) {
            update_option('mds3_upgraded_early_alpha_saved_defaults', MILLION_DOLLAR_SCRIPT_VERSION, false);
            return;
        }

        $defaults = SettingsSchema::defaults();
        [$settings, $changed] = self::normalized_alpha_saved_defaults($settings, $defaults, true);

        if ($changed) {
            update_option('mds3_settings', $settings, false);
        }

        update_option('mds3_upgraded_early_alpha_saved_defaults', MILLION_DOLLAR_SCRIPT_VERSION, false);
    }

    /**
     * Re-check exact alpha defaults even when the older one-time marker exists.
     *
     * Early test sites may have already marked the broad alpha-default migration
     * complete before the public Extension Server URL and neutral surface color
     * were finalized. This pass remains narrowly scoped to exact known default
     * values so custom settings are preserved.
     *
     * @return void
     */
    private static function normalize_known_alpha_saved_defaults() {
        $settings = get_option('mds3_settings', []);
        if (!is_array($settings)) {
            update_option('mds3_checked_known_alpha_saved_defaults', MILLION_DOLLAR_SCRIPT_VERSION, false);
            return;
        }

        [$settings, $changed] = self::normalized_alpha_saved_defaults($settings, SettingsSchema::defaults(), false);
        if ($changed) {
            update_option('mds3_settings', $settings, false);
        }

        update_option('mds3_checked_known_alpha_saved_defaults', MILLION_DOLLAR_SCRIPT_VERSION, false);
    }

    /**
     * Return settings with exact known alpha defaults replaced by current defaults.
     *
     * @param array<string,mixed> $settings Existing settings.
     * @param array<string,mixed> $defaults Current default settings.
     * @param bool                $include_order_defaults Whether to normalize early order defaults.
     * @return array{0:array<string,mixed>,1:bool}
     */
    private static function normalized_alpha_saved_defaults(array $settings, array $defaults, $include_order_defaults) {
        $changed = false;

        $old_extension_server_urls = [
            'https://extensions.milliondollarscript.com',
        ];
        if (isset($settings['extension_server_url']) && in_array(rtrim((string) $settings['extension_server_url'], '/'), $old_extension_server_urls, true)) {
            $settings['extension_server_url'] = $defaults['extension_server_url'] ?? 'https://milliondollarscript.com';
            $changed = true;
        }

        $old_secondary_backgrounds = [
            '#dc2626',
            '#ef4444',
            '#ff0000',
            '#f4f4f4',
            'red',
        ];
        $saved_secondary_background = strtolower(trim((string) ($settings['primary_color'] ?? '')));
        if (in_array($saved_secondary_background, $old_secondary_backgrounds, true)) {
            $settings['primary_color'] = $defaults['primary_color'] ?? '#f8fafc';
            $changed = true;
        }

        if ($include_order_defaults && isset($settings['auto-approve']) && 'yes' === strtolower(trim((string) $settings['auto-approve']))) {
            $settings['auto-approve'] = $defaults['auto-approve'] ?? 'no';
            $changed = true;
        }

        return [$settings, $changed];
    }

    /**
     * Remove stale grid-page metadata from extension legal pages.
     *
     * Earlier alpha builds could import keyword-only MDS2 page metadata for
     * legal documents, which made terms/privacy pages render a grid below
     * their normal content.
     *
     * @return void
     */
    private static function cleanup_extension_legal_page_grid_metadata() {
        if (get_option('mds3_cleaned_extension_legal_grid_metadata')) {
            return;
        }

        $page_ids = get_posts([
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'fields' => 'ids',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => '_mds3_extension_legal_document',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        $page_ids = array_values(array_filter(array_map('absint', is_array($page_ids) ? $page_ids : [])));
        if (!$page_ids) {
            update_option('mds3_cleaned_extension_legal_grid_metadata', MILLION_DOLLAR_SCRIPT_VERSION, false);
            return;
        }

        foreach ($page_ids as $page_id) {
            foreach ([
                '_mds3_page_type',
                '_mds3_grid_id',
                '_mds3_migration_source',
                '_mds3_migration_original_content',
                '_mds3_migration_original_title',
            ] as $meta_key) {
                delete_post_meta($page_id, $meta_key);
            }
        }

        global $wpdb;

        if (DB::table_exists(DB::table('pages'))) {
            $placeholders = implode(',', array_fill(0, count($page_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                'DELETE FROM ' . DB::ident(DB::table('pages')) . ' WHERE post_id IN (' . $placeholders . ')',
                $page_ids
            ));
        }

        if (DB::table_exists(DB::table('migration_map'))) {
            $placeholders = implode(',', array_fill(0, count($page_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                'DELETE FROM ' . DB::ident(DB::table('migration_map')) . " WHERE mds3_entity_type = 'page' AND mds3_id IN (" . $placeholders . ')',
                $page_ids
            ));

            $legacy_placeholders = implode(',', array_fill(0, count($page_ids), '%s'));
            $wpdb->query($wpdb->prepare(
                'DELETE FROM ' . DB::ident(DB::table('migration_map')) . " WHERE entity_type = 'page' AND mds3_entity_type = 'page' AND legacy_id IN (" . $legacy_placeholders . ')',
                array_map('strval', $page_ids)
            ));
        }

        update_option('mds3_cleaned_extension_legal_grid_metadata', MILLION_DOLLAR_SCRIPT_VERSION, false);
    }

    /**
     * Create an initial grid so the plugin has a usable first screen.
     *
     * @return void
     */
    private static function create_default_grid() {
        if (!(new ExtensionRuntime())->is_enabled('mds-grid')) {
            return;
        }

        $repo = new GridRepository();
        if ($repo->first_active()) {
            return;
        }

        $settings = get_option('mds3_settings', []);
        $repo->create([
            'title' => 'Main Grid',
            'description' => 'Your first Million Dollar Script advertising grid.',
            'width' => 1000,
            'height' => 1000,
            'block_width' => 10,
            'block_height' => 10,
            'price_per_block' => 1,
            'currency' => $settings['currency'] ?? 'USD',
            'status' => 'active',
        ]);
    }
}
