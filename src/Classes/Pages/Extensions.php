<?php

/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2025, Ryan Rhode
 * @license     https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *    Million Dollar Script
 *    Pixels to Profit: Ignite Your Revolution
 *    https://milliondollarscript.com/
 *
 */

namespace MillionDollarScript\Classes\Pages;

// Include WordPress plugin API
if (!function_exists('get_plugin_data')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

// The plugin-update-checker is already loaded by the main plugin
// We'll use the classes directly

use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Extension\ExtensionUpdater;
use MillionDollarScript\Classes\Extension\MDS_License_Manager;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\System\Utility;

/**
 * Handles the Million Dollar Script Extensions admin submenu page.
 */
class Extensions {

    /**
     * The slug for the admin page.
     */
    public const ADMIN_EXTENSIONS_PAGE_SLUG = 'milliondollarscript_extensions';

    /**
     * Normalize plan keys for consistent comparisons.
     */
    private static function normalize_plan_key(string $plan): string {
        $plan = strtolower(trim($plan));
        if ($plan === '') {
            return '';
        }
        $plan = preg_replace('/[^a-z0-9]+/', '_', $plan);
        return trim($plan, '_');
    }

    private static function canonicalize_plan_key(string $plan): string {
        $normalized = self::normalize_plan_key($plan);
        if ($normalized === '') {
            return '';
        }

        $direct = [
            'monthly'      => 'monthly',
            'yearly'       => 'yearly',
            'annual'       => 'yearly',
            'annually'     => 'yearly',
            'one_time'     => 'one_time',
            'lifetime'     => 'one_time',
            'subscription' => 'subscription',
            'recurring'    => 'subscription',
        ];
        if (isset($direct[$normalized])) {
            return $direct[$normalized];
        }

        $segments = array_values(array_filter(
            explode('_', $normalized),
            static function ($segment) {
                return $segment !== '';
            }
        ));

        if (empty($segments)) {
            return $normalized;
        }

        $allowedExtras = ['plan', 'plans', 'subscription', 'subscriptions', 'sub', 'subs', 'default', 'pricing', 'price', 'billing', 'renew', 'auto', 'standard'];

        $isSubset = static function (array $segments, array $base, array $extras): bool {
            $allowed = array_merge($base, $extras);
            foreach ($segments as $segment) {
                if (!in_array($segment, $allowed, true)) {
                    return false;
                }
            }
            return true;
        };

        if (in_array('monthly', $segments, true) && $isSubset($segments, ['monthly'], $allowedExtras)) {
            return 'monthly';
        }

        if ((in_array('yearly', $segments, true) || in_array('annual', $segments, true) || in_array('annually', $segments, true))
            && $isSubset($segments, ['yearly', 'annual', 'annually'], $allowedExtras)) {
            return 'yearly';
        }

        if ((in_array('one', $segments, true) && in_array('time', $segments, true)) || in_array('lifetime', $segments, true)) {
            if ($isSubset($segments, ['one', 'time', 'lifetime'], $allowedExtras)) {
                return 'one_time';
            }
        }

        if ((in_array('subscription', $segments, true) || in_array('recurring', $segments, true))
            && $isSubset($segments, ['subscription', 'recurring'], $allowedExtras)) {
            return 'subscription';
        }

        return $normalized;
    }

    private static function guess_plan_key_from_string(string $value): string {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        if (strpos($value, 'one_time') !== false || (strpos($value, 'one') !== false && strpos($value, 'time') !== false) || strpos($value, 'lifetime') !== false) {
            return 'one_time';
        }
        if (strpos($value, 'year') !== false || strpos($value, 'annual') !== false) {
            return 'yearly';
        }
        if (strpos($value, 'month') !== false || strpos($value, 'monthly') !== false) {
            return 'monthly';
        }
        if (strpos($value, 'subscription') !== false || strpos($value, 'recurring') !== false) {
            return 'subscription';
        }

        return self::normalize_plan_key($value);
    }

    /**
     * Stripe plan identifiers supported by the Extension Server metadata.
     */
    private const STRIPE_PRICE_PLANS = ['monthly', 'yearly', 'one_time'];

    /**
     * Metadata keys that store the fallback price ID for each plan.
     */
    private const STRIPE_PRICE_FALLBACK_KEYS = [
        'one_time' => 'stripe_price_one_time',
        'monthly'  => 'stripe_price_monthly',
        'yearly'   => 'stripe_price_yearly',
    ];

    /**
     * Attempt to coerce remote metadata fragments into arrays by repeatedly
     * handling serialized or JSON-encoded payloads.
     */
    private static function decode_metadata_fragment($value)
    {
        $attempts = 0;

        while (is_string($value) && $attempts < 5) {
            $attempts++;

            $maybe_unserialized = maybe_unserialize($value);
            if ($maybe_unserialized !== $value) {
                $value = $maybe_unserialized;
                continue;
            }

            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                $value = $decoded;
                continue;
            }

            break;
        }

        if ($value instanceof \stdClass) {
            $value = json_decode(wp_json_encode($value), true) ?: [];
        }

        return $value;
    }

    private static function extract_stripe_price_blocks($value): array
    {
        $value = self::decode_metadata_fragment($value);

        if (!is_array($value)) {
            return [];
        }

        if (isset($value['stripe_prices']) && is_array($value['stripe_prices'])) {
            $candidate = $value['stripe_prices'];
            $nonEmpty = false;
            foreach ($candidate as $entries) {
                $decoded = self::decode_metadata_fragment($entries);
                if (is_array($decoded) && !empty($decoded)) {
                    $nonEmpty = true;
                    break;
                }
                if (is_string($decoded) && trim($decoded) !== '') {
                    $nonEmpty = true;
                    break;
                }
            }
            if ($nonEmpty) {
                return $candidate;
            }
        }

        foreach ($value as $entry) {
            $candidate = self::extract_stripe_price_blocks($entry);
            if (!empty($candidate)) {
                return $candidate;
            }
        }

        return [];
    }

    /**
     * Initializes the Extensions class.
     */
    public static function init(): void {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [self::class, 'register_ajax_handlers']);
        add_action('init', [self::class, 'init_extension_updaters']);
        // Fallback notice will be printed inline within render() for proper placement
        add_action('admin_init', [self::class, 'schedule_license_cron']);
        add_action('mds_check_license_expirations', [self::class, 'license_expiry_cron']);

        // Default Purchase URL filter for admin UI if not provided elsewhere
        add_filter('mds_license_purchase_url', function($url) {
            if (!empty($url)) {
                return $url;
            }
            $base = Options::get_option('extension_server_url', 'http://localhost:15346');
            return rtrim($base, '/') . '/store';
        });
    }

    /**
     * Registers the Extensions submenu item.
     */
    public static function menu(): void {
        // Guard against accidental double-registration (duplicate submenu entries)
        static $registered = false;
        if ($registered) {
            return;
        }

        $hook_suffix = add_submenu_page(
            'milliondollarscript',
            Language::get('Million Dollar Script Extensions'),
            Language::get('Extensions'),
            'manage_options',
            'mds-extensions',
            [__CLASS__, 'render'],
            99
        );

        if ( $hook_suffix ) {
            $registered = true;
            add_action( "admin_print_styles-$hook_suffix", [ self::class, 'enqueue_assets' ] );
            add_action( 'admin_head', [ self::class, 'print_admin_menu_styles' ] );
        }
        // Late hook to normalize/deduplicate submenu entries
        add_action( 'admin_menu', [ self::class, 'dedupe_submenu' ], 999 );
    }
    
    /**
     * Register AJAX handlers. This must be called early during WordPress initialization.
     */
    public static function register_ajax_handlers(): void {
        // Register AJAX actions
        add_action( 'wp_ajax_mds_fetch_extension', [ self::class, 'ajax_fetch_extension' ] );
        add_action( 'wp_ajax_mds_fetch_extensions', [ self::class, 'ajax_fetch_extensions' ] );
        add_action( 'wp_ajax_mds_check_extension_updates', [ self::class, 'ajax_check_extension_updates' ] );
        add_action( 'wp_ajax_mds_install_extension_update', [ __CLASS__, 'ajax_install_extension_update' ] );
        add_action( 'wp_ajax_mds_activate_extension', [ __CLASS__, 'ajax_activate_extension' ] );
        add_action( 'wp_ajax_mds_install_extension', [ self::class, 'ajax_install_extension' ] );
        add_action( 'wp_ajax_mds_purchase_extension', [ self::class, 'ajax_purchase_extension' ] );
        add_action( 'wp_ajax_mds_claim_license', [ self::class, 'ajax_claim_license' ] );
        add_action( 'wp_ajax_mds_available_activate_license', [ self::class, 'ajax_available_activate_license' ] );
        add_action( 'wp_ajax_mds_get_license_plaintext', [ self::class, 'ajax_get_license_plaintext' ] );
        add_action( 'wp_ajax_mds_activate_license', [ self::class, 'ajax_activate_license' ] );
        add_action( 'wp_ajax_mds_deactivate_license', [ self::class, 'ajax_deactivate_license' ] );
        add_action( 'wp_ajax_mds_delete_license', [ self::class, 'ajax_delete_license' ] );
        // Subscription cancellation
        add_action( 'wp_ajax_mds_cancel_subscription', [ self::class, 'ajax_cancel_subscription' ] );
    }
    
    /**
     * AJAX handler for checking extension updates.
     */
    public static function ajax_check_extension_updates(): void {
        check_ajax_referer('mds_extensions_nonce', 'nonce');
        
        if (!current_user_can('update_plugins')) {
            wp_send_json_error(['message' => Language::get('You do not have permission to check for updates.')]);
        }
        
        $extension_id = sanitize_text_field($_POST['extension_id'] ?? '');
        $current_version = sanitize_text_field($_POST['current_version'] ?? '');
        $plugin_file = sanitize_text_field($_POST['plugin_file'] ?? '');
        
        if (empty($extension_id) || empty($current_version) || empty($plugin_file)) {
            wp_send_json_error(['message' => Language::get('Missing required parameters.')]);
        }
        
        try {
            $updater = new ExtensionUpdater();
            $update = $updater->checkForUpdate($extension_id, $current_version, $plugin_file);
            
            if ($update && is_object($update)) {
                wp_send_json_success([
                    'update_available' => true,
                    'new_version' => $update->version ?? '',
                    'package_url' => $update->download_url ?? '',
                    'requires' => $update->requires ?? '',
                    'requires_php' => $update->requires_php ?? '',
                    'tested' => $update->tested ?? ''
                ]);
            } else {
                wp_send_json_success(['update_available' => false]);
            }
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log('Error checking for extension updates: ' . $e->getMessage());
            wp_send_json_error(['message' => Language::get('An error occurred while checking for updates.')]);
        }
    }
    
    /**
     * Get all installed MDS extensions
     * 
     * @return array<
     *     array{
     *         id: string,
     *         name: string,
     *         version: string,
     *         plugin_file: string,
     *         plugin_uri: string,
     *         description: string,
     *         author: string,
     *         author_uri: string,
     *         text_domain: string,
     *         domain_path: string,
     *         network: bool,
     *         requires_wp: string,
     *         requires_php: string,
     *         update: bool,
     *         update_info: mixed,
     *         active: bool
     *     }
     * > List of installed extensions with their details
     */
    protected static function get_installed_extensions(): array {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $extensions = [];
        
        try {
            // Get all installed plugins
            $all_plugins = function_exists('get_plugins') ? get_plugins() : [];
            
            // Get active plugins
            $active_plugins = function_exists('get_option') ? 
                (array) get_option('active_plugins', []) : [];
            
            // If this is a multisite, include network active plugins
            if (is_multisite() && function_exists('get_site_option')) {
                $network_active_plugins = (array) get_site_option('active_sitewide_plugins', []);
                $active_plugins = array_merge($active_plugins, array_keys($network_active_plugins));
            }
            
            foreach ($all_plugins as $plugin_file => $plugin_data) {
                // Skip if not an MDS extension (starts with 'mds-' or has 'mds' in text domain)
                $is_mds_extension = strpos($plugin_file, 'mds-') === 0 || 
                                  (isset($plugin_data['TextDomain']) && 
                                   strpos($plugin_data['TextDomain'], 'mds') !== false);
                
                if (!$is_mds_extension) {
                    continue;
                }
                
                // Determine if the plugin is active
                $is_active = in_array($plugin_file, $active_plugins, true) || 
                            (is_multisite() && isset($network_active_plugins[$plugin_file]));
                
                // Get the extension ID (prefer text domain, fall back to plugin slug)
                $plugin_slug = dirname($plugin_file);
                $extension_id = !empty($plugin_data['TextDomain']) ? 
                    $plugin_data['TextDomain'] : 
                    ($plugin_slug !== '.' ? $plugin_slug : basename($plugin_file, '.php'));
                
                $extensions[] = [
                    'id' => $extension_id,
                    'name' => $plugin_data['Name'] ?? '',
                    'version' => $plugin_data['Version'] ?? '1.0.0',
                    'plugin_file' => $plugin_file,
                    'plugin_uri' => $plugin_data['PluginURI'] ?? '',
                    'description' => $plugin_data['Description'] ?? '',
                    'author' => $plugin_data['Author'] ?? '',
                    'author_uri' => $plugin_data['AuthorURI'] ?? '',
                    'text_domain' => $plugin_data['TextDomain'] ?? '',
                    'domain_path' => $plugin_data['DomainPath'] ?? '',
                    'network' => $plugin_data['Network'] ?? false,
                    'requires_wp' => $plugin_data['RequiresWP'] ?? '',
                    'requires_php' => $plugin_data['RequiresPHP'] ?? '',
                    'update' => false,
                    'update_info' => null,
                    'active' => $is_active,
                ];
            }
        } catch (\Exception $e) {
            // Log the error but don't break the admin UI
            if (function_exists('error_log')) {
                error_log('Error getting installed extensions: ' . $e->getMessage());
            }
        }
        
        return $extensions;
    }
    
    /**
     * Initialize the extension updater for all installed extensions
     */
    public static function init_extension_updaters(): void {
        if (!current_user_can('update_plugins')) {
            return;
        }
        
        // Only initialize if we're in the admin area
        if (!is_admin()) {
            return;
        }
        
        $extensions = self::get_installed_extensions();
        
        // Don't proceed if we don't have any extensions
        if (empty($extensions)) {
            return;
        }
        
        $updater = new ExtensionUpdater();
        
        foreach ($extensions as $extension) {
            try {
                if (!empty($extension['id']) && !empty($extension['version']) && !empty($extension['plugin_file'])) {
                    $updater->initUpdater(
                        $extension['plugin_file'],
                        $extension['id'],
                        $extension['version']
                    );
                }
            } catch (\Exception $e) {
                // Log the error but don't prevent other extensions from initializing
                error_log(sprintf(
                    'Error initializing updater for extension %s: %s',
                    $extension['id'] ?? 'unknown',
                    $e->getMessage()
                ));
                continue;
            }
        }
    }
    
    /**
     * AJAX handler for installing extension updates.
     */
    /**
     * AJAX handler to activate an extension.
     */
    public static function ajax_activate_extension(): void {
        check_ajax_referer('mds_extensions_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => Language::get('You do not have sufficient permissions to perform this action.')]);
            return;
        }

        $slug = isset($_POST['extension_slug']) ? sanitize_text_field(wp_unslash($_POST['extension_slug'])) : '';

        if (empty($slug)) {
            wp_send_json_error(['message' => Language::get('Extension slug is missing.')]);
            return;
        }

        $plugin_file = self::find_plugin_file_by_slug($slug);

        if (!$plugin_file) {
            wp_send_json_error(['message' => sprintf(Language::get('Could not find plugin file for slug: %s'), $slug)]);
            return;
        }

        // Check if already active to prevent errors and provide a specific message
        if (is_plugin_active($plugin_file)) {
            wp_send_json_success([
                'message' => Language::get('Extension is already active.'),
                'reload' => true // Or false, if no UI change is strictly needed beyond button state
            ]);
            return;
        }

        $result = activate_plugin($plugin_file);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        // activate_plugin() returns null on success, or a WP_Error on failure.
        wp_send_json_success([
            'message' => Language::get('Extension activated successfully.'),
            'reload' => true
        ]);
    }

    public static function ajax_install_extension_update(): void {
        check_ajax_referer( 'mds_extensions_nonce', 'nonce' );
        
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'You do not have permission to install updates.' ) ] );
        }
        
        $extension_id = sanitize_text_field( $_POST['extension_id'] ?? '' );
        $download_url = esc_url_raw( $_POST['download_url'] ?? '' );
        
        if ( empty( $extension_id ) || empty( $download_url ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Missing required parameters.' ) ] );
        }
        
        try {
            $result = self::install_extension_update( $extension_id, $download_url );
            wp_send_json_success( $result );
        } catch ( \Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }
    
    /**
     * Install an extension update.
     *
     * @param string $extension_id The extension ID.
     * @param string $download_url The download URL for the update.
     * @return array Result of the update.
     * @throws \Exception If the update fails.
     */
    public static function install_extension_update( string $extension_id, string $download_url ): array {
        // Include required WordPress files
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        
        // Get the license key for authentication
        $license_key = ''; // This is deprecated
        
        // Set up the upgrader
        $upgrader = new \Plugin_Upgrader( new \Plugin_Upgrader_Skin( [ 'plugin' => $extension_id ] ) );
        
        // Add authentication headers
        add_filter( 'http_request_args', function( $args, $url ) use ( $download_url, $license_key ) {
            if ( strpos( $url, $download_url ) === 0 ) {
                $args['headers']['x-license-key'] = $license_key;
            }
            return $args;
        }, 10, 2 );
        
        // Install the update
        $result = $upgrader->install( $download_url, [ 'overwrite_package' => true ] );
        
        if ( is_wp_error( $result ) ) {
            throw new \Exception( $result->get_error_message() );
        }
        
        // Clear plugin cache
        wp_clean_plugins_cache();
        
        return [
            'success' => true,
            'message' => Language::get( 'Extension updated successfully.' ),
            'reload' => true
        ];
    }

    /**
     * Renders the Extensions page content and handles form submissions.
     */
    public static function render(): void {
        // Check if the user has the required capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html( Language::get('You do not have sufficient permissions to access this page.') ) );
        }

        // Display settings errors/notices
        settings_errors('mds_extensions_notices');
        
        // Get installed extensions
        $installed_extensions = self::get_installed_extensions();

        // Preload license data for downstream UI decisions
        $license_map = self::get_license_snapshot_map();
        $license_lookup = self::build_license_lookup_index($license_map);

        // Try to get available extensions from server
        $available_extensions = [];
        $extension_server_error = null;

        try {
            $available_extensions = self::fetch_available_extensions($license_map);
        } catch (\Exception $e) {
            $extension_server_error = $e->getMessage();
        }

        // Merge installed extensions into the catalog so everything renders from a unified grid
        $display_extensions = is_array($available_extensions) ? $available_extensions : [];

        $available_by_slug = [];
        $available_by_normalized = [];
        $available_by_plugin = [];

        foreach ($display_extensions as $index => $extension) {
            if (!is_array($extension)) {
                continue;
            }

            $slug = isset($extension['slug']) ? (string) $extension['slug'] : '';
            if ($slug !== '') {
                $available_by_slug[$slug] = $index;
                $normalized_slug = self::normalize_extension_name($slug);
                if ($normalized_slug !== '') {
                    $available_by_normalized[$normalized_slug] = $index;
                }
            }

            $plugin_name = isset($extension['pluginName']) ? (string) $extension['pluginName'] : '';
            if ($plugin_name !== '') {
                $normalized_name = self::normalize_extension_name($plugin_name);
                if ($normalized_name !== '') {
                    $available_by_normalized[$normalized_name] = $index;
                }
            }

            if (!empty($extension['name'])) {
                $normalized_name = self::normalize_extension_name((string) $extension['name']);
                if ($normalized_name !== '') {
                    $available_by_normalized[$normalized_name] = $index;
                }
            }

            if (!empty($extension['installed_plugin_file'])) {
                $plugin_file = (string) $extension['installed_plugin_file'];
                if ($plugin_file !== '') {
                    $available_by_plugin[$plugin_file] = $index;
                }
            }
        }

        foreach ($installed_extensions as $installed) {
            if (!is_array($installed)) {
                continue;
            }

            $plugin_file = isset($installed['plugin_file']) ? (string) $installed['plugin_file'] : '';
            $matched_index = null;

            if ($plugin_file !== '' && isset($available_by_plugin[$plugin_file])) {
                $matched_index = $available_by_plugin[$plugin_file];
            }

            $normalized_candidates = [];
            $slug_candidates = [];

            if (isset($installed['text_domain'])) {
                $text_domain = (string) $installed['text_domain'];
                if ($text_domain !== '') {
                    $slug_candidates[] = sanitize_title($text_domain);
                    $normalized_candidates[] = self::normalize_extension_name($text_domain);
                }
            }

            if (isset($installed['id'])) {
                $id_value = (string) $installed['id'];
                if ($id_value !== '') {
                    $slug_candidates[] = sanitize_title($id_value);
                    $normalized_candidates[] = self::normalize_extension_name($id_value);
                }
            }

            if (isset($installed['name'])) {
                $name_value = (string) $installed['name'];
                if ($name_value !== '') {
                    $slug_candidates[] = sanitize_title($name_value);
                    $normalized_candidates[] = self::normalize_extension_name($name_value);
                }
            }

            if ($plugin_file !== '') {
                $plugin_dir = dirname($plugin_file);
                if ($plugin_dir !== '' && $plugin_dir !== '.') {
                    $slug_candidates[] = sanitize_title($plugin_dir);
                    $normalized_candidates[] = self::normalize_extension_name($plugin_dir);
                }
                $plugin_basename = basename($plugin_file, '.php');
                if ($plugin_basename !== '') {
                    $slug_candidates[] = sanitize_title($plugin_basename);
                    $normalized_candidates[] = self::normalize_extension_name($plugin_basename);
                }
            }

            $slug_candidates = array_values(array_filter(array_unique($slug_candidates)));
            $normalized_candidates = array_values(array_filter(array_unique($normalized_candidates)));

            if ($matched_index === null) {
                foreach ($slug_candidates as $candidate) {
                    if ($candidate !== '' && isset($available_by_slug[$candidate])) {
                        $matched_index = $available_by_slug[$candidate];
                        break;
                    }
                }
            }

            if ($matched_index === null) {
                foreach ($normalized_candidates as $candidate) {
                    if ($candidate !== '' && isset($available_by_normalized[$candidate])) {
                        $matched_index = $available_by_normalized[$candidate];
                        break;
                    }
                }
            }

            if ($matched_index !== null && isset($display_extensions[$matched_index])) {
                $display_extensions[$matched_index]['installed_plugin_file'] = $plugin_file;
                $display_extensions[$matched_index]['is_installed'] = true;
                $display_extensions[$matched_index]['is_active'] = !empty($installed['active']);
                if (empty($display_extensions[$matched_index]['version']) && !empty($installed['version'])) {
                    $display_extensions[$matched_index]['version'] = $installed['version'];
                }
                continue;
            }

            $synthetic_candidates = [];
            if (!empty($installed['text_domain'])) {
                $synthetic_candidates[] = (string) $installed['text_domain'];
            }
            if (!empty($installed['id'])) {
                $synthetic_candidates[] = (string) $installed['id'];
            }
            if (!empty($installed['name'])) {
                $synthetic_candidates[] = (string) $installed['name'];
            }
            if ($plugin_file !== '') {
                $plugin_dir = dirname($plugin_file);
                if ($plugin_dir !== '' && $plugin_dir !== '.') {
                    $synthetic_candidates[] = $plugin_dir;
                }
                $synthetic_candidates[] = basename($plugin_file, '.php');
            }
            $synthetic_candidates = array_values(array_filter(array_unique($synthetic_candidates)));

            $slug_value = '';
            foreach ($synthetic_candidates as $candidate) {
                $candidate_slug = sanitize_title($candidate);
                if ($candidate_slug !== '') {
                    $slug_value = $candidate_slug;
                    break;
                }
            }
            if ($slug_value === '') {
                $slug_value = sanitize_title('mds-' . md5($plugin_file . ($installed['name'] ?? uniqid('ext', true))));
            }

            $license_snapshot = self::find_license_snapshot($license_lookup, $synthetic_candidates);

            $display_extensions[] = [
                'id'                    => $installed['id'] ?? $slug_value,
                'name'                  => $installed['name'] ?? $slug_value,
                'description'           => $installed['description'] ?? '',
                'version'               => $installed['version'] ?? '',
                'slug'                  => $slug_value,
                'pluginName'            => $installed['name'] ?? '',
                'isPremium'             => $license_snapshot !== null,
                'is_installed'          => true,
                'is_active'             => !empty($installed['active']),
                'installed_plugin_file' => $plugin_file,
                'purchased_locally'     => $license_snapshot !== null,
                'local_license'         => $license_snapshot,
                'is_licensed'           => $license_snapshot !== null ? self::is_active_license_status($license_snapshot['status'] ?? '') : false,
                'metadata'              => [],
                'pricing_overview'      => [],
                'purchase'              => ['anyAvailable' => false, 'options' => []],
                'purchase_links'        => [
                    'one_time' => null,
                    'monthly'  => null,
                    'yearly'   => null,
                    'default'  => null,
                    'options'  => [],
                ],
            ];

            $new_index = count($display_extensions) - 1;
            $available_by_slug[$slug_value] = $new_index;
            $available_by_normalized[self::normalize_extension_name($slug_value)] = $new_index;
            if ($plugin_file !== '') {
                $available_by_plugin[$plugin_file] = $new_index;
            }
        }

        // Add nonce for security
        $nonce = wp_create_nonce( 'mds_extensions_nonce' );

        ?>
        <div class="wrap mds-extensions-page" id="mds-extensions-page">
            <div class="mds-extensions-header">
                <h1><?php echo esc_html( Language::get('Million Dollar Script Extensions') ); ?></h1>
                <p class="mds-extensions-subtitle"><?php echo esc_html( Language::get('Supercharge your pixel advertising with powerful extensions') ); ?></p>
            </div>

            <!-- In-page notices anchor: All notices render below header, above content -->
            <div id="mds-extensions-notices" class="mds-extensions-notices" aria-live="polite" aria-atomic="true">
            <?php
            // Show purchase/fallback notices positioned below the header, above the extensions grid
            self::print_license_expiry_notices();
            $purchase = isset($_GET['purchase']) ? sanitize_text_field($_GET['purchase']) : '';
            $ext_param = isset($_GET['ext']) ? sanitize_text_field($_GET['ext']) : '';
            if ($purchase === 'success' && $ext_param !== '') : ?>
                <div class="notice notice-success">
                    <p><?php echo esc_html( Language::get('Purchase received. Activation will be available once payment clears. You may need to refresh in a minute.') ); ?></p>
                </div>
            <?php elseif ($purchase === 'cancel') : ?>
                <div class="notice notice-warning">
                    <p><?php echo esc_html( Language::get('Purchase was cancelled.') ); ?></p>
                </div>
            <?php endif; ?>
            <?php 
            // Print fallback notice inline
            self::print_server_fallback_notice();
            if ($extension_server_error) : ?>
                <div class="notice notice-warning">
                    <p><?php echo esc_html( Language::get('Could not connect to extension server: ') . $extension_server_error ); ?></p>
                    <p><?php echo esc_html( Language::get('You can only manage installed extensions at this time.') ); ?></p>
                </div>
            <?php endif; ?>
            </div>
            
            <div class="mds-extensions-container">
                <?php if (!empty($display_extensions)) : ?>
                <div class="mds-available-grid">
                    <?php
                    foreach ($display_extensions as $extension) {
                        echo self::render_available_extension_card($extension, $nonce, $extension_server_error);
                    }
                    ?>
                </div>
                <?php else : ?>
                    <div class="notice notice-info">
                        <p><?php echo esc_html( Language::get('No extensions installed.') ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue JS and CSS assets for the Extensions page.
     */
    public static function enqueue_assets(): void {
        // This is already called within the render method which checks if we're on the right page
        // No need for additional checks here

        // Prefer unminified script during development to ensure latest JS changes take effect
        $script_path = MDS_BASE_PATH . 'src/Assets/js/admin-extensions.js';
        $script_url = MDS_BASE_URL . 'src/Assets/js/admin-extensions.js';
        $style_path = MDS_BASE_PATH . 'src/Assets/css/admin/extensions_enhanced.css';
        $style_url = MDS_BASE_URL . 'src/Assets/css/admin/extensions_enhanced.css';

        $script_handle = 'mds-admin-extensions-js';
        $style_handle = 'mds-admin-extensions-css';

        if ( file_exists( $script_path ) ) {
            wp_enqueue_script(
                $script_handle,
                $script_url,
                [ 'jquery', 'wp-util' ], // wp-util includes wp.ajax
                filemtime( $script_path ),
                true
            );
            
            // Create the data to be passed to JavaScript
            $purchase = isset($_GET['purchase']) ? sanitize_text_field($_GET['purchase']) : '';
            $ext_param = isset($_GET['ext']) ? sanitize_text_field($_GET['ext']) : '';
            $ext_slug_param = isset($_GET['extSlug']) ? sanitize_text_field($_GET['extSlug']) : '';
            $claim_token_param = isset($_GET['claimToken']) ? sanitize_text_field($_GET['claimToken']) : '';
            $site_id = home_url();

            $extensions_data = [
                'ajax_url'    => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'mds_extensions_nonce' ),
                'license_key' => '',
                'settings_url'=> admin_url('admin.php?page=milliondollarscript_options#system'),
                'extension_server_url' => Options::get_option('extension_server_url', 'http://localhost:15346'),
                'extension_server_public_url' => Options::get_option('mds_extension_server_public_url', 'http://localhost:15346'),
                'site_id'     => $site_id,
                'purchase'    => [
                    'status'   => $purchase,
                    'ext_id'   => $ext_param,
                    'ext_slug' => $ext_slug_param,
                    'claim_token' => $claim_token_param,
                ],
                'text'        => [
                    'manage_license'       => Language::get('Manage license'),
                    'hide_license'         => Language::get('Hide'),
                    'processing_plan'       => Language::get('Processing...'),
                    'redirecting_checkout'  => Language::get('Redirecting to checkout...'),
                    'purchase_failed'       => Language::get('Failed to initiate purchase.'),
                    'purchase_network_error'=> Language::get('An error occurred while contacting the store. Please try again.'),
                    'missing_plan'          => Language::get('Please choose a plan to continue.'),
                    'missing_parameters'    => Language::get('Unable to start checkout. Please refresh and try again.'),
                    'confirm_remove_license'=> Language::get('Remove the stored license for this extension?'),
                    'license_context_missing'=> Language::get('Unable to locate license context.'),
                    'license_removed'       => Language::get('License removed.'),
                    'license_remove_failed' => Language::get('Failed to remove license.'),
                    'removing_license'      => Language::get('Removing…'),
                ]
            ];
            wp_localize_script( $script_handle, 'MDS_EXTENSIONS_DATA', $extensions_data );
        }

        if ( file_exists( $style_path ) ) {
            wp_enqueue_style(
                $style_handle,
                $style_url,
                [],
                filemtime( $style_path )
            );
        }
    }

    /**
     * Schedule daily cron for license expiry checks.
     */
    public static function schedule_license_cron(): void {
        if (!wp_next_scheduled('mds_check_license_expirations')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'mds_check_license_expirations');
        }
    }

    /**
     * Cron callback: compute license expiry warnings and store transient.
     */
    public static function license_expiry_cron(): void {
        try {
            $lm = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
            $rows = method_exists($lm,'get_all_licenses') ? $lm->get_all_licenses() : [];
            $warn = [];
            $now = time();
            foreach ($rows as $r) {
                $status = isset($r->status) ? (string)$r->status : '';
                $exp    = isset($r->expires_at) ? (string)$r->expires_at : '';
                $slug   = property_exists($r,'plugin_name') && !empty($r->plugin_name) ? (string)$r->plugin_name : (property_exists($r,'extension_slug') ? (string)$r->extension_slug : '');
                if ($status !== 'active' || $exp === '' || $slug === '') continue;
                $ts = strtotime($exp);
                if ($ts && $ts > $now) {
                    $days = (int) floor(($ts - $now) / DAY_IN_SECONDS);
                    if ($days <= 14) {
                        $warn[] = [ 'slug' => $slug, 'expires_at' => $exp, 'days' => $days ];
                    }
                }
            }
            if (!empty($warn)) {
                set_transient('mds_license_expiry_warnings', $warn, DAY_IN_SECONDS);
            } else {
                delete_transient('mds_license_expiry_warnings');
            }
        } catch (\Throwable $t) { /* ignore */ }
    }

    /**
     * Print any license expiry warnings.
     */
    public static function print_license_expiry_notices(): void {
        if (!current_user_can('manage_options')) return;
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if ($page !== 'mds-extensions') return;
        $warn = get_transient('mds_license_expiry_warnings');
        if (!is_array($warn) || empty($warn)) return;
        echo '<div class="notice notice-warning">';
        echo '<p>' . esc_html( Language::get('Some licenses are expiring soon:') ) . '</p>';
        echo '<ul style="margin-left:20px;">';
        foreach ($warn as $w) {
            $slug = isset($w['slug']) ? (string)$w['slug'] : '';
            $days = isset($w['days']) ? (int)$w['days'] : 0;
            $exp  = isset($w['expires_at']) ? (string)$w['expires_at'] : '';
            echo '<li><strong>' . esc_html($slug) . '</strong>: ' . esc_html($days . ' ' . Language::get('days remaining')) . ' (' . esc_html($exp) . ')</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    /**
     * Print a top-level admin notice about missing license key on the Extensions page.
     * Renders in the standard WP notices area for consistent placement and clickability.
     */
    public static function print_missing_license_notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if ( $page !== 'mds-extensions' ) {
            return;
        }
        if (self::site_has_any_active_license()) {
            return;
        }
        $settings_url = admin_url('admin.php?page=milliondollarscript_options#system');
        $link_label   = Language::get('Go to System tab');
        if ( empty( $link_label ) ) {
            $link_label = 'Go to System tab';
        }
        $message_text = Language::get('Some extensions require a valid license. Enter your license key at Million Dollar Script → Options → System → License Key.');
        ?>
        <div class="notice notice-warning is-dismissible mds-license-notice-top">
            <p>
                <?php echo esc_html( $message_text ); ?>
                <a class="mds-go-to-system" href="<?php echo esc_url( $settings_url ); ?>">
                    <?php echo esc_html( $link_label ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Inline printer for missing license notice to guarantee placement before header.
     */
    public static function print_missing_license_notice_inline(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if (self::site_has_any_active_license()) {
            return;
        }
        $settings_url = admin_url('admin.php?page=milliondollarscript_options#system');
        $link_label   = Language::get('Go to System tab');
        if ( empty( $link_label ) ) {
            $link_label = 'Go to System tab';
        }
        $message_text = Language::get('Some extensions require a valid license. Enter your license key at Million Dollar Script → Options → System → License Key.');
        ?>
        <div class="notice notice-warning is-dismissible mds-license-notice-top">
            <p>
                <?php echo esc_html( $message_text ); ?>
                <a class="mds-go-to-system" href="<?php echo esc_url( $settings_url ); ?>">
                    <?php echo esc_html( $link_label ); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Print a fallback notice when the plugin had to use localhost for the Extension Server.
     * This runs in the standard WP admin_notices area and is scoped to the Extensions page.
     */
    public static function print_server_fallback_notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if ( $page !== 'mds-extensions' ) {
            return;
        }
        $resolved_url = get_transient('mds_ext_server_fallback_notice');
        if ( ! $resolved_url ) {
            return;
        }
        delete_transient('mds_ext_server_fallback_notice');
        $settings_url = admin_url('admin.php?page=milliondollarscript_options#system');
        ?>
        <div class="notice notice-warning mds-ext-server-fallback">
            <p><?php echo esc_html( Language::get('Connected to the Extension Server using a fallback URL.') ); ?></p>
            <p>
                <?php echo esc_html( Language::get('Update your Extension Server URL in Million Dollar Script → Options → System to:') ); ?>
                <code><?php echo esc_html( is_string($resolved_url) && !empty($resolved_url) ? (string)$resolved_url : 'http://localhost:15346' ); ?></code>.
                <a class="mds-go-to-system" href="<?php echo esc_url( $settings_url ); ?>">
                    <?php echo esc_html( Language::get('Open System tab') ); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Normalize/deduplicate the Million Dollar Script submenu to ensure only one "MDS Extensions" entry.
     */
    public static function dedupe_submenu(): void {
        global $submenu;
        $parent = 'milliondollarscript';
        if (!isset($submenu[$parent]) || !is_array($submenu[$parent])) {
            return;
        }
        $filtered = [];
        $seenMdsExt = false;
        $extensionsItem = null;
        foreach ($submenu[$parent] as $item) {
            $slug = isset($item[2]) ? (string) $item[2] : '';
            // Prefer index 3 (page title) per WP structure; fall back to index 0 (menu title)
            $rawTitle = '';
            if (isset($item[3]) && is_string($item[3])) {
                $rawTitle = $item[3];
            } elseif (isset($item[0]) && is_string($item[0])) {
                $rawTitle = $item[0];
            }
            $title = strtolower(trim(wp_strip_all_tags($rawTitle)));

            if ($slug === 'mds-extensions') {
                if ($seenMdsExt) {
                    // Drop any additional entries with the exact slug
                    continue;
                }
                $seenMdsExt = true;
                $extensionsItem = $item;
                continue;
            }
            // If another entry uses the same label "MDS Extensions" or "Extensions" but a different slug, drop it
            if ($slug !== 'mds-extensions' && ($title === 'mds extensions' || $title === 'extensions')) {
                continue;
            }
            $filtered[] = $item;
        }
        if ($extensionsItem !== null) {
            $filtered[] = $extensionsItem;
        }
        $submenu[$parent] = $filtered;
    }

    /**
     * Inject admin menu styling to highlight the Extensions entry.
     */
    public static function print_admin_menu_styles(): void {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        ?>
        <style id="mds-extensions-admin-menu-button">
            #adminmenu #toplevel_page_milliondollarscript ul.wp-submenu a[href*="page=mds-extensions"] {
                display: block;
                margin: 8px 12px 12px;
                padding: 10px 16px;
                border-radius: 6px;
                background: #ff6b35;
                color: #1d2327;
                font-weight: 600;
                font-size: 14px;
                line-height: 1.4;
                text-align: center;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
                transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
                text-decoration: none;
                white-space: normal;
            }

            #adminmenu #toplevel_page_milliondollarscript ul.wp-submenu li.current a[href*="page=mds-extensions"] {
                background: #ff8a45;
                color: #1d2327;
                box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
            }

            #adminmenu #toplevel_page_milliondollarscript ul.wp-submenu a[href*="page=mds-extensions"]:hover,
            #adminmenu #toplevel_page_milliondollarscript ul.wp-submenu a[href*="page=mds-extensions"]:focus,
            #adminmenu #toplevel_page_milliondollarscript ul.wp-submenu a[href*="page=mds-extensions"]:active {
                background: #00c896 !important;
                color: #1d2327 !important;
                box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2) !important;
                transform: translateY(-1px);
            }

            #adminmenu #toplevel_page_milliondollarscript ul.wp-submenu a[href*="page=mds-extensions"]:focus-visible {
                outline: 2px solid #1d2327;
                outline-offset: 0;
            }
        </style>
        <?php
    }
    
    /**
     * Fetch available extensions from the extension server
     * 
     * @return array List of available extensions
     * @throws \Exception If the API request fails
     */
    protected static function fetch_available_extensions(?array $license_map = null): array {
        $user_configured_url = Options::get_option('extension_server_url', 'http://host.docker.internal:15346');
        $license_key = ''; // This is deprecated

        $args = [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent'   => 'MDS-WordPress-Plugin/' . MDS_VERSION,
            ],
            'sslverify' => !Utility::is_development_environment(),
        ];

        if (!empty($license_key)) {
            $args['headers']['x-license-key'] = $license_key;
        }

        $candidates = [
            rtrim((string)$user_configured_url, '/'),
            'http://extension-server:3000',
            'http://extension-server-dev:3000',
            'http://host.docker.internal:15346',
            'http://localhost:15346',
        ];

        $response = null;
        $working_base = null;
        $errors = [];

        foreach ($candidates as $base) {
            if (empty($base)) {
                continue;
            }
            $api_url = rtrim($base, '/') . '/api/extensions';
            $res = wp_remote_get($api_url, $args);

            if (is_wp_error($res)) {
                $errors[] = $base . ' => ' . $res->get_error_message();
                continue;
            }

            $code = wp_remote_retrieve_response_code($res);
            if ($code === 200) {
                $response = $res;
                $working_base = $base;
                break;
            } else {
                $errors[] = $base . ' => HTTP ' . $code;
            }
        }

        if (!$response) {
            throw new \Exception('Failed to connect to extension server. Tried: ' . implode('; ', $errors));
        }

        if (!empty($working_base) && rtrim((string)$working_base, '/') !== rtrim((string)$user_configured_url, '/')) {
            set_transient('mds_ext_server_fallback_notice', $working_base, MINUTE_IN_SECONDS * 5);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            throw new \Exception('Extension server returned error: ' . $response_code);
        }

        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from extension server');
        }

        if (isset($data['success']) && !$data['success']) {
            throw new \Exception($data['error'] ?? 'Unknown error from extension server');
        }

        $extensions = $data;
        if (isset($data['data'])) {
            $extensions = $data['data'];
        } elseif (isset($data['extensions'])) {
            $extensions = $data['extensions'];
        }

        $transformed_extensions = [];
        foreach ($extensions as $extension) {
            // Normalize purchase links (server may return relative /store/checkout or absolute URLs)
            $purchase_links = [
                'one_time' => null,
                'monthly'  => null,
                'yearly'   => null,
                'default'  => null,
                'options'  => [],
            ];
            $server_links = [];
            if (isset($extension['purchase']) && is_array($extension['purchase']) && isset($extension['purchase']['links']) && is_array($extension['purchase']['links'])) {
                $server_links = $extension['purchase']['links'];
            }

            $abs = function($url) use ($working_base) {
                if (!is_string($url) || $url === '') {
                    return null;
                }
                if (preg_match('#^https?://#i', $url)) {
                    return $url;
                }
                $base = is_string($working_base) && $working_base !== '' ? rtrim($working_base, '/') : 'http://localhost:15346';
                return $base . '/' . ltrim($url, '/');
            };

            if (!empty($server_links)) {
                $purchase_links['one_time'] = isset($server_links['oneTime']) ? $abs($server_links['oneTime']) : null;
                $purchase_links['monthly']  = isset($server_links['monthly']) ? $abs($server_links['monthly']) : null;
                $purchase_links['yearly']   = isset($server_links['yearly']) ? $abs($server_links['yearly']) : null;
                $purchase_links['default']  = isset($server_links['default']) ? $abs($server_links['default']) : null;
                if (isset($server_links['options']) && is_array($server_links['options'])) {
                    foreach ($server_links['options'] as $planKey => $optionList) {
                        if (!is_array($optionList)) {
                            continue;
                        }
                        $normalizedOptions = [];
                        foreach ($optionList as $option) {
                            if (!is_array($option)) {
                                continue;
                            }
                            $checkout = isset($option['checkout']) ? $abs($option['checkout']) : null;
                            $normalizedOptions[] = [
                                'priceId' => isset($option['priceId']) ? (string) $option['priceId'] : '',
                                'label'   => isset($option['label']) ? (string) $option['label'] : '',
                                'status'  => isset($option['status']) ? (string) $option['status'] : '',
                                'default' => !empty($option['default']),
                                'checkout'=> $checkout,
                            ];
                        }
                        if (!empty($normalizedOptions)) {
                            $purchase_links['options'][$planKey] = $normalizedOptions;
                        }
                    }
                }
            }

            $normalized_meta = self::normalize_extension_metadata($extension['metadata'] ?? []);

            $pricing_overview = [];
            if (!empty($extension['pricing_overview']) && is_array($extension['pricing_overview'])) {
                $pricing_overview = $extension['pricing_overview'];
            } elseif (!empty($extension['pricingOverview']) && is_array($extension['pricingOverview'])) {
                $pricing_overview = $extension['pricingOverview'];
            }

            $transformed_extensions[] = [
                'id'           => $extension['id'] ?? '',
                'name'         => $extension['name'] ?? '',
                'pluginName'   => $extension['plugin_name'] ?? ($extension['pluginName'] ?? ($extension['name'] ?? '')),
                'version'      => $extension['version'] ?? '',
                'description'  => $extension['description'] ?? '',
                'isPremium'    => $extension['is_premium'] ?? false,
                'file_name'    => $extension['file_name'] ?? '',
                'file_path'    => $extension['file_path'] ?? '',
                'created_at'   => $extension['created_at'] ?? '',
                'updated_at'   => $extension['updated_at'] ?? '',
                'changelog'    => $extension['changelog'] ?? '',
                'requires'     => $extension['requires'] ?? '',
                'requires_php' => $extension['requires_php'] ?? '',
                'tested'       => $extension['tested'] ?? '',
                'metadata'     => $normalized_meta,
                'pricing_overview' => $pricing_overview,
                // New: pass through purchase availability and normalized links for WP UI
                'purchase'     => [
                    'anyAvailable' => (bool) ( $extension['purchase']['anyAvailable'] ?? ( $purchase_links['one_time'] || $purchase_links['monthly'] || $purchase_links['yearly'] || $purchase_links['default'] ) ),
                    'options'      => $purchase_links['options'],
                ],
                'purchase_links' => $purchase_links,
                'can_purchase'   => (bool) ( $extension['purchase']['anyAvailable'] ?? ( $purchase_links['one_time'] || $purchase_links['monthly'] || $purchase_links['yearly'] || $purchase_links['default'] ) ),
            ];
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $installed_plugins = get_plugins();
        $active_plugins_option = get_option( 'active_plugins', [] );
        $active_plugins = is_array($active_plugins_option) ? $active_plugins_option : [];

        foreach ($transformed_extensions as $key => $extension) {
            $is_installed = false;
            $is_active = false;
            $installed_plugin_file = null;

            $plugin_slug_from_server = '';
            if (!empty($extension['name'])) {
                $plugin_slug_from_server = sanitize_title($extension['name']);
            } else {
                $plugin_slug_from_server = 'mds_ext_' . uniqid();
            }

            $transformed_extensions[$key]['slug'] = $plugin_slug_from_server;

            if (!empty($plugin_slug_from_server)) {
                foreach ($installed_plugins as $plugin_file => $plugin_data) {
                    $current_plugin_dir = dirname($plugin_file);
                    $current_plugin_basename = basename($plugin_file, '.php');
                    // error_log("[MDS DEBUG] Checking: Server Slug ('{$plugin_slug_from_server}') vs Installed Plugin File ('{$plugin_file}'), Dir ('{$current_plugin_dir}'), Basename ('{$current_plugin_basename}')");

                    if ($current_plugin_dir === $plugin_slug_from_server && $current_plugin_dir !== '.') {
                        // error_log("[MDS DEBUG] Match on directory: {$plugin_slug_from_server}");
                        $is_installed = true;
                        $installed_plugin_file = $plugin_file;
                        break;
                    }
                    if ($current_plugin_basename === $plugin_slug_from_server && $current_plugin_dir === '.') {
                        // error_log("[MDS DEBUG] Match on basename (single file plugin): {$plugin_slug_from_server}");
                        $is_installed = true;
                        $installed_plugin_file = $plugin_file;
                        break;
                    }
                }
            }

            if ($is_installed && $installed_plugin_file) {
                $is_active = in_array($installed_plugin_file, $active_plugins, true);
            }

            $transformed_extensions[$key]['is_installed'] = $is_installed;
            $transformed_extensions[$key]['is_active'] = $is_active;
        }

        $license_map = is_array($license_map) ? $license_map : self::get_license_snapshot_map();
        $license_lookup = self::build_license_lookup_index($license_map);

        if (!empty($transformed_extensions)) {
            foreach ($transformed_extensions as &$extension) {
                $slug_candidates = [];

                $slug = isset($extension['slug']) ? (string) $extension['slug'] : '';
                if ($slug !== '') {
                    $slug_candidates[] = $slug;
                }

                if (!empty($extension['pluginName'])) {
                    $slug_candidates[] = (string) $extension['pluginName'];
                }

                if (!empty($extension['name'])) {
                    $slug_candidates[] = (string) $extension['name'];
                }

                $snapshot = self::find_license_snapshot($license_lookup, $slug_candidates);

                if ($snapshot !== null) {
                    $extension['purchased_locally'] = true;
                    $extension['local_license'] = $snapshot;
                    $extension['is_licensed'] = self::is_active_license_status($snapshot['status'] ?? '');
                } else {
                    $extension['purchased_locally'] = false;
                    $extension['local_license'] = null;
                    $extension['is_licensed'] = false;
                }
            }
            unset($extension);
        }

        return $transformed_extensions;
    }

    /**
     * Get the browser-facing base URL for the Extension Server.
     * Prefers a configurable public URL; falls back to localhost:15346 for dev.
     */
    private static function get_browser_extension_server_base_url(): string {
        $public = \MillionDollarScript\Classes\Data\Options::get_option('mds_extension_server_public_url', '');
        if (is_string($public) && $public !== '') {
            return rtrim($public, '/');
        }
        return 'http://localhost:15346';
    }

    /**
     * Normalize a checkout URL for browser redirection.
     * - Prefix relative URLs with the browser base
     * - Rewrite internal Docker host/port to the public base in dev
     * - Preserve absolute public URLs
     */
    private static function normalize_public_checkout_url(string $url): string {
        if (!is_string($url) || $url === '') {
            return $url;
        }
        // If relative path, prefix with browser base
        if ($url[0] === '/') {
            return rtrim(self::get_browser_extension_server_base_url(), '/') . $url;
        }
        $parsed = wp_parse_url($url);
        if (is_array($parsed) && !empty($parsed['host'])) {
            $host = strtolower($parsed['host']);
            $port = isset($parsed['port']) ? (int) $parsed['port'] : null;
            // Map internal dev host/port to the browser-facing base
            if ($host === 'extension-server-dev' || $host === 'extension-server' || $port === 3000) {
                $publicBase = rtrim(self::get_browser_extension_server_base_url(), '/');
                $path     = isset($parsed['path']) && is_string($parsed['path']) ? $parsed['path'] : '';
                $query    = isset($parsed['query']) && $parsed['query'] !== '' ? ('?' . $parsed['query']) : '';
                $fragment = isset($parsed['fragment']) && $parsed['fragment'] !== '' ? ('#' . $parsed['fragment']) : '';
                return $publicBase . $path . $query . $fragment;
            }
        }
        return $url;
    }
    
    /**
     * Normalize plugin/extension names for catalog matching.
     * Trims and lowercases the input, stripping tags for safety.
     */
    private static function normalize_extension_name(?string $name): string {
        if (!is_string($name)) {
            return '';
        }
        $clean = trim(wp_strip_all_tags($name));
        if ($clean === '') {
            return '';
        }
        return strtolower($clean);
    }

    /**
     * Decode and normalize extension metadata returned by the extension server.
     */
    private static function normalize_extension_metadata($metadata): array {
        $result = [];

        $merge = static function (array $source) use (&$result): void {
            $result = self::merge_metadata_arrays($result, $source);
        };

        $process = static function ($value) use (&$result, &$process, $merge): void {
            if ($value === null || $value === '') {
                return;
            }

            $value = self::decode_metadata_fragment($value);

            if ($value instanceof \stdClass) {
                $value = json_decode(wp_json_encode($value), true) ?: [];
            }

            if (is_array($value)) {
                $is_assoc = array_keys($value) !== range(0, count($value) - 1);
                if ($is_assoc) {
                    $merge($value);
                    foreach ($value as $entry) {
                        $process($entry);
                    }
                } else {
                    foreach ($value as $entry) {
                        $process($entry);
                    }
                }
                return;
            }
        };

        $process($metadata);

        $result = self::normalize_stripe_pricing_metadata($result, $metadata);

        return self::normalize_plan_features_metadata($result);
    }

    /**
     * Recursively merge associative metadata arrays while preserving list-like values.
     */
    private static function merge_metadata_arrays(array $base, array $incoming): array {
        foreach ($incoming as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
                $baseIsList = array_keys($base[$key]) === range(0, count($base[$key]) - 1);
                $incomingIsList = array_keys($value) === range(0, count($value) - 1);

                if ($baseIsList && $incomingIsList) {
                    $base[$key] = array_values(array_merge($base[$key], $value));
                } else {
                    $base[$key] = self::merge_metadata_arrays($base[$key], $value);
                }
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Normalizes Stripe plan pricing metadata to mirror the Extension Server helpers.
     */
    private static function normalize_stripe_pricing_metadata(array $meta, $raw_source = null): array {
        $structure = [];
        $source_raw = $meta['stripe_prices'] ?? [];
        $source = [];
        $legacyLookup = [];

        $collectLegacyIds = static function ($value) use (&$legacyLookup): void {
            $decoded = self::decode_metadata_fragment($value);
            if (!is_array($decoded)) {
                return;
            }
            $list = array_keys($decoded) === range(0, count($decoded) - 1) ? $decoded : array_values($decoded);
            foreach ($list as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $priceId = isset($entry['price_id']) ? (string) $entry['price_id'] : '';
                if ($priceId === '') {
                    continue;
                }
                $legacyLookup[$priceId] = true;
            }
        };

        $source_raw = self::decode_metadata_fragment($source_raw);
        if (!is_array($source_raw) || empty(array_filter($source_raw))) {
            $fallback_source = $raw_source ?? [];
            $source_raw = self::extract_stripe_price_blocks($fallback_source);
            $source_raw = self::decode_metadata_fragment($source_raw);
        }

        if (isset($meta['stripe_legacy_prices'])) {
            $collectLegacyIds($meta['stripe_legacy_prices']);
        }
        if (isset($meta['stripe_removed_prices'])) {
            $collectLegacyIds($meta['stripe_removed_prices']);
        }

        if (is_array($source_raw)) {
            foreach ($source_raw as $plan => $entries) {
                $source[self::canonicalize_plan_key((string) $plan)] = $entries;
            }

            if (isset($source_raw['stripe_legacy_prices'])) {
                $collectLegacyIds($source_raw['stripe_legacy_prices']);
            }
            if (isset($source_raw['stripe_removed_prices'])) {
                $collectLegacyIds($source_raw['stripe_removed_prices']);
            }
        }

        foreach (self::STRIPE_PRICE_PLANS as $plan) {
            $structure[$plan] = [];
            if (isset($source[$plan])) {
                $entries = self::decode_metadata_fragment($source[$plan]);
                if (!is_array($entries)) {
                    $entries = [$entries];
                }
                foreach ($entries as $rawEntry) {
                    $entry = self::create_stripe_price_entry(self::decode_metadata_fragment($rawEntry));
                    if ($entry !== null) {
                        $priceId = $entry['price_id'] ?? '';
                        if ($priceId !== '' && isset($legacyLookup[$priceId])) {
                            $entry['status'] = 'legacy';
                            $entry['default'] = false;
                        }
                        self::push_stripe_price_entry($structure[$plan], $entry);
                    }
                }
            }
        }

        foreach (self::STRIPE_PRICE_PLANS as $plan) {
            $fallbackKey = self::STRIPE_PRICE_FALLBACK_KEYS[$plan] ?? null;
            if ($fallbackKey && isset($meta[$fallbackKey]) && is_string($meta[$fallbackKey])) {
                $fallbackId = trim($meta[$fallbackKey]);
                if ($fallbackId !== '') {
                    $status = isset($legacyLookup[$fallbackId]) ? 'legacy' : 'active';
                    self::push_stripe_price_entry($structure[$plan], [
                        'price_id'   => $fallbackId,
                        'label'      => 'Default',
                        'status'     => $status,
                        'default'    => true,
                        'created_at' => gmdate('c'),
                        'metadata'   => [],
                    ]);
                }
            }

            foreach ($structure[$plan] as &$entry) {
                $priceId = isset($entry['price_id']) ? (string) $entry['price_id'] : '';
                if ($priceId !== '' && isset($legacyLookup[$priceId])) {
                    $entry['status'] = 'legacy';
                    $entry['default'] = false;
                }
            }
            unset($entry);

            self::ensure_stripe_price_default($structure[$plan]);
            self::sort_stripe_price_entries($structure[$plan]);
        }

        $meta['stripe_prices'] = $structure;

        foreach (self::STRIPE_PRICE_PLANS as $plan) {
            $fallbackKey = self::STRIPE_PRICE_FALLBACK_KEYS[$plan] ?? null;
            if (!$fallbackKey) {
                continue;
            }

            $fallbackId = '';
            foreach ($structure[$plan] as $entry) {
                if (!empty($entry['default']) && (!isset($entry['status']) || $entry['status'] !== 'hidden')) {
                    $fallbackId = (string) $entry['price_id'];
                    break;
                }
            }

            if ($fallbackId === '' && isset($structure[$plan][0]['price_id'])) {
                $fallbackId = (string) $structure[$plan][0]['price_id'];
            }

            if ($fallbackId !== '') {
                $meta[$fallbackKey] = $fallbackId;
            } elseif (isset($meta[$fallbackKey])) {
                unset($meta[$fallbackKey]);
            }
        }

        return $meta;
    }

    /**
     * Convert a raw Stripe price entry into the normalized structure used across the UI.
     */
    private static function create_stripe_price_entry($raw): ?array {
        $raw = self::decode_metadata_fragment($raw);

        if ($raw instanceof \stdClass) {
            $raw = json_decode(wp_json_encode($raw), true);
        }

        if (!is_array($raw)) {
            return null;
        }

        $priceId = '';
        foreach ([
            $raw['price_id'] ?? null,
            $raw['priceId'] ?? null,
            $raw['id'] ?? null,
        ] as $candidate) {
            if (is_string($candidate)) {
                $candidate = trim($candidate);
                if ($candidate !== '') {
                    $priceId = $candidate;
                    break;
                }
            }
        }

        if ($priceId === '') {
            return null;
        }

        $label = '';
        foreach ([
            $raw['label'] ?? null,
            $raw['nickname'] ?? null,
            $raw['name'] ?? null,
        ] as $candidate) {
            if (is_string($candidate)) {
                $candidate = trim($candidate);
                if ($candidate !== '') {
                    $label = $candidate;
                    break;
                }
            }
        }
        if ($label === '') {
            $label = 'Default';
        }

        $status = '';
        if (isset($raw['status'])) {
            $status = strtolower((string) $raw['status']);
        } elseif (!empty($raw['hidden'])) {
            $status = 'hidden';
        }
        if (!in_array($status, ['active', 'hidden', 'legacy'], true)) {
            $status = 'active';
        }

        $createdAt = '';
        foreach ([
            $raw['created_at'] ?? null,
            $raw['createdAt'] ?? null,
            $raw['created'] ?? null,
        ] as $candidate) {
            if ($candidate instanceof \DateTimeInterface) {
                $createdAt = $candidate->format('c');
                break;
            }
            if (is_numeric($candidate)) {
                $createdAt = gmdate('c', (int) $candidate);
                break;
            }
            if (is_string($candidate)) {
                $candidate = trim($candidate);
                if ($candidate !== '') {
                    $timestamp = strtotime($candidate);
                    $createdAt = $timestamp ? gmdate('c', $timestamp) : $candidate;
                    break;
                }
            }
        }
        if ($createdAt === '') {
            $createdAt = gmdate('c');
        }

        $metadata = [];
        if (isset($raw['metadata'])) {
            $metaValue = $raw['metadata'];
            if (is_string($metaValue)) {
                $unserialized = maybe_unserialize($metaValue);
                if ($unserialized !== $metaValue) {
                    $metaValue = $unserialized;
                } else {
                    $decoded = json_decode($metaValue, true);
                    if (is_array($decoded)) {
                        $metaValue = $decoded;
                    }
                }
            } elseif ($metaValue instanceof \stdClass) {
                $metaValue = json_decode(wp_json_encode($metaValue), true);
            }

            if (is_array($metaValue)) {
                $metadata = $metaValue;
            }
        }

        return [
            'price_id'   => $priceId,
            'label'      => $label,
            'status'     => $status,
            'default'    => !empty($raw['default']),
            'created_at' => $createdAt,
            'metadata'   => $metadata,
        ];
    }

    /**
     * Merge an entry into the plan list, keeping metadata up to date.
     */
    private static function push_stripe_price_entry(array &$list, array $entry): void {
        $priceId = isset($entry['price_id']) ? (string) $entry['price_id'] : '';
        if ($priceId === '') {
            return;
        }

        foreach ($list as $index => $existing) {
            if ((string) ($existing['price_id'] ?? '') !== $priceId) {
                continue;
            }

            $merged = $existing;
            $merged['label'] = isset($entry['label']) && $entry['label'] !== '' ? $entry['label'] : ($existing['label'] ?? '');
            $merged['status'] = isset($entry['status']) ? $entry['status'] : ($existing['status'] ?? 'active');
            $merged['default'] = !empty($entry['default']) || !empty($existing['default']);
            $merged['created_at'] = $entry['created_at'] ?? ($existing['created_at'] ?? gmdate('c'));

            $existingMeta = is_array($existing['metadata'] ?? null) ? $existing['metadata'] : [];
            $incomingMeta = is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [];
            $merged['metadata'] = self::merge_metadata_arrays($existingMeta, $incomingMeta);

            $list[$index] = $merged;
            return;
        }

        if (!isset($entry['metadata']) || !is_array($entry['metadata'])) {
            $entry['metadata'] = [];
        }

        $entry['default'] = !empty($entry['default']);
        $list[] = $entry;
    }

    /**
     * Ensure the plan list always has a single default entry promoted to active status.
     */
    private static function ensure_stripe_price_default(array &$list): void {
        if (empty($list)) {
            return;
        }

        $defaultIndex = null;
        foreach ($list as $index => &$entry) {
            $isDefault = !empty($entry['default']);
            if ($isDefault && $defaultIndex === null) {
                $defaultIndex = $index;
                $entry['default'] = true;
                if (($entry['status'] ?? 'active') === 'hidden') {
                    $entry['status'] = 'active';
                }
            } elseif ($isDefault) {
                $entry['default'] = false;
            } else {
                $entry['default'] = false;
            }
        }
        unset($entry);

        if ($defaultIndex !== null) {
            return;
        }

        foreach ($list as $index => &$entry) {
            if (($entry['status'] ?? 'active') === 'active') {
                $entry['default'] = true;
                $defaultIndex = $index;
                break;
            }
        }
        unset($entry);

        if ($defaultIndex === null) {
            $list[0]['default'] = true;
            if (($list[0]['status'] ?? 'active') === 'hidden') {
                $list[0]['status'] = 'active';
            }
        }
    }

    /**
     * Sort entries so the default appears first, followed by active, hidden, and legacy tiers.
     */
    private static function sort_stripe_price_entries(array &$list): void {
        if (count($list) <= 1) {
            return;
        }

        usort($list, static function (array $a, array $b): int {
            $aDefault = !empty($a['default']);
            $bDefault = !empty($b['default']);
            if ($aDefault !== $bDefault) {
                return $aDefault ? -1 : 1;
            }

            $order = ['active' => 0, 'hidden' => 1, 'legacy' => 2];
            $aRank = $order[$a['status'] ?? 'active'] ?? 3;
            $bRank = $order[$b['status'] ?? 'active'] ?? 3;
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }

            $aTime = self::determine_price_entry_timestamp($a);
            $bTime = self::determine_price_entry_timestamp($b);
            if ($aTime === $bTime) {
                return 0;
            }

            return $aTime > $bTime ? -1 : 1;
        });
    }

    /**
     * Resolve a comparable timestamp for ordering price entries.
     */
    private static function determine_price_entry_timestamp(array $entry): int {
        $candidates = [
            $entry['created_at'] ?? null,
            $entry['createdAt'] ?? null,
        ];

        $metadata = is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [];
        if (isset($metadata['created_at'])) {
            $candidates[] = $metadata['created_at'];
        }
        if (isset($metadata['createdAt'])) {
            $candidates[] = $metadata['createdAt'];
        }

        foreach ($candidates as $candidate) {
            if ($candidate instanceof \DateTimeInterface) {
                return $candidate->getTimestamp();
            }
            if (is_numeric($candidate)) {
                return (int) $candidate;
            }
            if (is_string($candidate)) {
                $candidate = trim($candidate);
                if ($candidate === '') {
                    continue;
                }
                $timestamp = strtotime($candidate);
                if ($timestamp) {
                    return $timestamp;
                }
            }
        }

        return 0;
    }

    /**
     * Build feature arrays (`plan_features_array`) while preserving the original HTML payload.
     */
    private static function normalize_plan_features_metadata(array $meta): array {
        if (isset($meta['release']) && is_array($meta['release'])) {
            $release_meta = $meta['release'];
            if (array_key_exists('show_sale_price', $release_meta)) {
                $meta['show_sale_price'] = (bool) $release_meta['show_sale_price'];
            }
        }

        $collectStringMap = static function ($source) {
            $result = [];
            if (!is_array($source)) {
                return $result;
            }
            foreach ($source as $key => $value) {
                if (!is_string($key) || $key === '') {
                    continue;
                }
                if (is_array($value)) {
                    $value = implode("\n", array_map('trim', array_filter($value, static function ($item) {
                        return is_string($item) && trim($item) !== '';
                    })));
                }
                if (!is_string($value)) {
                    continue;
                }
                $trimmed = trim($value);
                if ($trimmed === '') {
                    continue;
                }
                $result[$key] = $trimmed;
            }
            return $result;
        };

        $planFeatureMap = [];
        foreach (['plan_feature_map', 'planFeatureMap'] as $mapKey) {
            if (isset($meta[$mapKey])) {
                $planFeatureMap = array_replace($planFeatureMap, $collectStringMap($meta[$mapKey]));
            }
        }
        if (isset($meta['release']['plan_feature_map'])) {
            $planFeatureMap = array_replace(
                $planFeatureMap,
                $collectStringMap($meta['release']['plan_feature_map'])
            );
        }

        $planFeaturesByPlan = [];
        foreach (['plan_features', 'planFeatures'] as $planKey) {
            if (isset($meta[$planKey])) {
                $planFeaturesByPlan = array_replace($planFeaturesByPlan, $collectStringMap($meta[$planKey]));
            }
        }
        if (isset($meta['release']['plan_features'])) {
            $planFeaturesByPlan = array_replace(
                $planFeaturesByPlan,
                $collectStringMap($meta['release']['plan_features'])
            );
        }

        $stripePrices = isset($meta['stripe_prices']) && is_array($meta['stripe_prices'])
            ? $meta['stripe_prices']
            : [];

        // Promote plan-level features to map using default price IDs when no map is present.
        if (empty($planFeatureMap) && !empty($planFeaturesByPlan)) {
            foreach ($stripePrices as $planKey => $entries) {
                if (!is_array($entries)) {
                    continue;
                }
                $defaultEntry = null;
                foreach ($entries as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    if (!isset($entry['price_id'])) {
                        continue;
                    }
                    if (!empty($entry['default'])) {
                        $defaultEntry = $entry;
                        break;
                    }
                    if ($defaultEntry === null) {
                        $defaultEntry = $entry;
                    }
                }
                if ($defaultEntry && isset($defaultEntry['price_id'], $planFeaturesByPlan[$planKey])) {
                    $planFeatureMap[(string) $defaultEntry['price_id']] = $planFeaturesByPlan[$planKey];
                }
            }
        }

        // Populate plan-level features from map if missing.
        if (!empty($planFeatureMap) && is_array($stripePrices)) {
            foreach ($stripePrices as $planKey => $entries) {
                if (!is_array($entries) || isset($planFeaturesByPlan[$planKey])) {
                    continue;
                }
                foreach ($entries as $entry) {
                    if (!is_array($entry) || empty($entry['price_id'])) {
                        continue;
                    }
                    $priceId = (string) $entry['price_id'];
                    if (isset($planFeatureMap[$priceId])) {
                        $planFeaturesByPlan[$planKey] = $planFeatureMap[$priceId];
                        break;
                    }
                }
            }
        }

        $planFeaturesArray = [];
        foreach ($planFeaturesByPlan as $plan => $value) {
            $items = self::parse_plan_features_to_array($value);
            if (!empty($items)) {
                $planFeaturesArray[$plan] = $items;
            }
        }

        $planFeaturesArrayByPrice = [];
        foreach ($planFeatureMap as $priceId => $value) {
            $items = self::parse_plan_features_to_array($value);
            if (!empty($items)) {
                $planFeaturesArrayByPrice[$priceId] = $items;
            }
        }

        if (!empty($planFeatureMap)) {
            $meta['plan_feature_map'] = $planFeatureMap;
        } else {
            unset($meta['plan_feature_map']);
        }

        if (!empty($planFeaturesByPlan)) {
            $meta['plan_features'] = $planFeaturesByPlan;
        } else {
            unset($meta['plan_features']);
        }

        if (!empty($planFeaturesArray)) {
            $meta['plan_features_array'] = $planFeaturesArray;
        } else {
            unset($meta['plan_features_array']);
        }

        if (!empty($planFeaturesArrayByPrice)) {
            $meta['plan_features_array_by_price'] = $planFeaturesArrayByPrice;
        } else {
            unset($meta['plan_features_array_by_price']);
        }

        return $meta;
    }

    /**
     * Convert HTML or newline-delimited plan features into an array of bullet points.
     */
    private static function get_plan_feature_allowed_tags(): array
    {
        return [
            'strong' => [],
            'em'     => [],
            'b'      => [],
            'i'      => [],
            'u'      => [],
            'code'   => [],
            'br'     => [],
        ];
    }

    /**
     * Normalize simple inline span/font styling to semantic tags we allow.
     */
    private static function normalize_plan_feature_styles(string $value): string
    {
        if (stripos($value, '<span') === false && stripos($value, '<font') === false) {
            return $value;
        }

        $pattern = '/<(span|font)\b([^>]*)>(.*?)<\/\1>/is';

        $normalized = preg_replace_callback($pattern, function (array $matches) {
            $attributes = $matches[2] ?? '';
            $content = $matches[3] ?? '';

            $style = '';
            if (preg_match('/style\s*=\s*("|")(.*?)\1/i', $attributes, $styleMatch)) {
                $style = strtolower($styleMatch[2]);
            }

            $isBold = $style !== '' && preg_match('/font-weight\s*:\s*(bold|[6-9]00)/i', $style);
            $isItalic = $style !== '' && preg_match('/font-style\s*:\s*italic/i', $style);

            $content = self::normalize_plan_feature_styles($content);

            if ($isBold && $isItalic) {
                return '<strong><em>' . $content . '</em></strong>';
            }
            if ($isBold) {
                return '<strong>' . $content . '</strong>';
            }
            if ($isItalic) {
                return '<em>' . $content . '</em>';
            }

            return $matches[0];
        }, $value);

        return $normalized === null ? $value : $normalized;
    }

    private static function sanitize_plan_feature_markup(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = self::normalize_plan_feature_styles($value);

        $sanitized = wp_kses($value, self::get_plan_feature_allowed_tags());

        return trim($sanitized);
    }

    private static function parse_plan_features_to_array(string $value): array {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $items = [];
        $allowedTags = self::get_plan_feature_allowed_tags();

        if (strpos($value, '<') !== false) {
            if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $value, $matches) && isset($matches[1])) {
                foreach ($matches[1] as $fragment) {
                    $text = self::sanitize_plan_feature_markup($fragment);
                    if ($text !== '') {
                        $items[] = $text;
                    }
                }
            }

            if (!empty($items)) {
                return $items;
            }

            $value = wp_kses($value, $allowedTags);
        }

        $lines = preg_split('/[\r\n]+/', $value);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $text = self::sanitize_plan_feature_markup($line);
                if ($text !== '') {
                    $items[] = $text;
                }
            }
        }

        return $items;
    }

    /**
     * Decode a license metadata payload retrieved from wp_mds_extension_licenses.
     */
    private static function normalize_license_metadata($metadata): array {
        if ($metadata === null || $metadata === '') {
            return [];
        }

        if (is_string($metadata)) {
            $unserialized = maybe_unserialize($metadata);
            if ($unserialized !== $metadata) {
                return self::normalize_license_metadata($unserialized);
            }

            $decoded = json_decode($metadata, true);
            return is_array($decoded) ? $decoded : [];
        }

        if ($metadata instanceof \stdClass) {
            return json_decode(wp_json_encode($metadata), true) ?: [];
        }

        if (is_array($metadata)) {
            return $metadata;
        }

        return [];
    }

    /**
     * Merge remote payload data into a license update array while preserving metadata.
     *
     * @param object|null $existing Existing license row, if any.
     * @param array<string,mixed> $base_update
     * @param array<string,mixed>|null $payload Remote payload to merge
     * @param string|null $fallback_expires Optional fallback expiry value
     * @return array<string,mixed>
     */
    private static function compose_license_update_payload($existing, array $base_update, ?array $payload, ?string $fallback_expires = null): array {
        $existing_metadata = [];
        if (is_object($existing)) {
            $existing_metadata = MDS_License_Manager::decode_metadata_value($existing->metadata ?? null);
        }

        $prepared = is_array($payload)
            ? MDS_License_Manager::prepare_payload_metadata($payload, $existing_metadata)
            : ['metadata' => $existing_metadata];

        if (empty($prepared['metadata'])) {
            $prepared['metadata'] = $existing_metadata;
        }

        if (empty($prepared['expires_at']) && is_string($fallback_expires) && $fallback_expires !== '') {
            $fallback = MDS_License_Manager::prepare_payload_metadata(
                ['expires_at' => $fallback_expires],
                $prepared['metadata']
            );
            if (!empty($fallback['expires_at'])) {
                $prepared['expires_at'] = $fallback['expires_at'];
                $prepared['metadata'] = $fallback['metadata'];
            }
        }

        if (!empty($prepared['metadata'])) {
            $encoded = wp_json_encode($prepared['metadata']);
            if (is_string($encoded)) {
                $base_update['metadata'] = $encoded;
            }
        }

        if (!empty($prepared['expires_at'])) {
            $base_update['expires_at'] = $prepared['expires_at'];
        }

        if (!isset($base_update['status'])) {
            $payload_status = self::extract_license_status_from_payload($payload ?? null);
            if ($payload_status !== null) {
                $base_update['status'] = $payload_status;
            }
        }

        return $base_update;
    }

    private static function extract_license_status_from_payload($payload): ?string {
        if (!is_array($payload)) {
            return null;
        }

        $candidates = [];

        if (isset($payload['status']) && is_string($payload['status'])) {
            $candidates[] = $payload['status'];
        }

        if (isset($payload['license']) && is_array($payload['license'])) {
            $license = $payload['license'];
            if (isset($license['status']) && is_string($license['status'])) {
                $candidates[] = $license['status'];
            }
        }

        if (isset($payload['subscription']) && is_array($payload['subscription'])) {
            $subscription = $payload['subscription'];
            if (isset($subscription['status']) && is_string($subscription['status'])) {
                $candidates[] = $subscription['status'];
            }
        }

        foreach ($candidates as $candidate) {
            $normalized = strtolower(trim($candidate));
            if ($normalized === '') {
                continue;
            }

            if (in_array($normalized, ['active', 'valid', 'paid', 'trialing', 'grace'], true)) {
                return 'active';
            }

            if (in_array($normalized, ['inactive', 'expired', 'cancelled', 'canceled', 'past_due', 'unpaid'], true)) {
                return 'inactive';
            }

            return $normalized;
        }

        return null;
    }

    private static function should_enrich_license_snapshot(string $slug, array $snapshot): bool {
        if ($slug === '' || empty($snapshot)) {
            return false;
        }

        if (self::recently_failed_license_enrichment($slug)) {
            return false;
        }

        $renews = isset($snapshot['renews_at']) ? trim((string) $snapshot['renews_at']) : '';
        $expires = isset($snapshot['expires_at']) ? trim((string) $snapshot['expires_at']) : '';

        $hasRenewal = ($renews !== '' && $renews !== '0000-00-00 00:00:00');
        $hasExpiration = ($expires !== '' && $expires !== '0000-00-00 00:00:00');

        $status_value = is_string($snapshot['status'] ?? null) ? strtolower(trim((string) $snapshot['status'])) : '';
        $needs_status_refresh = $status_value === '' || !self::is_active_license_status($status_value);

        if ($hasRenewal || $hasExpiration) {
            return $needs_status_refresh;
        }

        if ($needs_status_refresh) {
            return true;
        }

        $looks_subscription = self::license_suggests_subscription($snapshot);

        if (!$looks_subscription) {
            if (in_array($status_value, ['active', 'valid', 'enabled', 'paid'], true)) {
                $looks_subscription = true;
            }
        }

        return $looks_subscription;
    }

    private static function attempt_remote_license_enrichment(string $slug, $existing_row) {
        if (!is_object($existing_row)) {
            return null;
        }

        $payload = self::fetch_remote_license_payload($slug, $existing_row);
        if (!is_array($payload) || empty($payload)) {
            self::record_license_enrichment_failure($slug);
            return null;
        }

        $fallback_expires = '';
        if (isset($payload['license']) && is_array($payload['license'])) {
            $fallback_expires = (string) ($payload['license']['expires_at'] ?? ($payload['license']['expiresAt'] ?? ''));
        }
        if ($fallback_expires === '' && isset($payload['expires_at'])) {
            $fallback_expires = (string) $payload['expires_at'];
        }
        if ($fallback_expires === '' && isset($payload['expiresAt'])) {
            $fallback_expires = (string) $payload['expiresAt'];
        }
        if ($fallback_expires === '' && isset($payload['subscription']) && is_array($payload['subscription'])) {
            $fallback_expires = (string) ($payload['subscription']['current_period_end'] ?? ($payload['subscription']['currentPeriodEnd'] ?? ''));
        }

        $update_data = self::compose_license_update_payload(
            $existing_row,
            [],
            $payload,
            $fallback_expires !== '' ? $fallback_expires : null
        );

        if (empty($update_data)) {
            self::record_license_enrichment_failure($slug);
            return null;
        }

        try {
            $lm = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
            $lm->update_license((int) $existing_row->id, $update_data);
            $refreshed = $lm->get_license($slug);
            if ($refreshed) {
                delete_transient(self::license_enrichment_failure_key($slug));
            }
            return $refreshed ?: null;
        } catch (\Throwable $t) {
            self::record_license_enrichment_failure($slug);
            return null;
        }
    }

    private static function fetch_remote_license_payload(string $slug, $license_row): ?array {
        if (!is_object($license_row) || empty($license_row->license_key)) {
            return null;
        }

        $plaintext = \MillionDollarScript\Classes\Extension\LicenseCrypto::decryptFromCompact((string) $license_row->license_key);
        if ($plaintext === '') {
            $plaintext = (string) $license_row->license_key;
        }

        if ($plaintext === '') {
            return null;
        }

        $base = self::resolve_extension_server_base();
        if ($base === null) {
            return null;
        }

        $response = wp_remote_post(rtrim($base, '/') . '/api/public/subscriptions/get', [
            'timeout' => 20,
            'sslverify' => !Utility::is_development_environment(),
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode([
                'licenseKey' => $plaintext,
                'productIdentifier' => $slug,
            ]),
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($code !== 200 || !is_array($json)) {
            return null;
        }

        if (isset($json['data']) && is_array($json['data'])) {
            return $json['data'];
        }

        return $json;
    }

    private static function resolve_extension_server_base(): ?string {
        static $cached_base = null;
        if ($cached_base !== null) {
            return $cached_base;
        }

        $user_configured_url = Options::get_option('extension_server_url', 'http://extension-server-dev:3000');
        $candidates = [
            rtrim((string) $user_configured_url, '/'),
            'http://extension-server:3000',
            'http://extension-server-dev:3000',
            'http://host.docker.internal:15346',
            'http://localhost:15346',
        ];

        $probes = [
            '/api/public/ping',
            '/api/extensions',
            '/health',
        ];

        foreach ($candidates as $base) {
            if (empty($base)) {
                continue;
            }

            foreach ($probes as $probe) {
                $url = rtrim($base, '/') . $probe;
                $response = wp_remote_get($url, [
                    'timeout'   => 10,
                    'sslverify' => !Utility::is_development_environment(),
                ]);

                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $cached_base = rtrim($base, '/');
                    return $cached_base;
                }
            }
        }

        return null;
    }

    private static function recently_failed_license_enrichment(string $slug): bool {
        return get_transient(self::license_enrichment_failure_key($slug)) !== false;
    }

    private static function record_license_enrichment_failure(string $slug): void {
        set_transient(self::license_enrichment_failure_key($slug), 1, MINUTE_IN_SECONDS * 15);
    }

    private static function license_enrichment_failure_key(string $slug): string {
        return 'mds_license_enrich_fail_' . md5($slug);
    }

    private static function format_timezone_suffix(int $timestamp): string {
        try {
            $timezone = wp_timezone();
        } catch (\Throwable $t) {
            $timezone = new \DateTimeZone('UTC');
        }

        if (!$timezone instanceof \DateTimeZone) {
            $timezone = new \DateTimeZone('UTC');
        }

        $date = new \DateTime('@' . $timestamp);
        $date->setTimezone($timezone);

        $timezone_string = wp_timezone_string();
        $offset = $date->format('P');
        $abbreviation = $date->format('T');

        if ($timezone_string === '') {
            if ($offset === '+00:00') {
                return '(UTC)';
            }

            return '(UTC' . $offset . ')';
        }

        if ($timezone_string === 'UTC' || strtoupper($timezone_string) === 'UTC') {
            return '(UTC)';
        }

        $label = $timezone_string;
        if ($abbreviation !== ''
            && strtoupper($abbreviation) !== 'GMT'
            && strtoupper($abbreviation) !== 'UTC'
            && stripos($label, $abbreviation) === false
        ) {
            $label .= ' ' . $abbreviation;
        }

        if ($offset === '+00:00') {
            if (stripos($label, 'UTC') !== false) {
                return '(' . $label . ')';
            }

            return sprintf('(%s, UTC%s)', $label, $offset);
        }

        return sprintf('(%s, UTC%s)', $label, $offset);
    }

    private static function is_active_license_status($status): bool {
        if (!is_string($status) || $status === '') {
            return false;
        }

        $normalized = strtolower(trim($status));

        return in_array($normalized, ['active', 'valid', 'enabled', 'paid'], true);
    }

    private static function build_license_snapshot_from_row($row): array {
        if (!is_object($row)) {
            return [];
        }

        $metadata = self::normalize_license_metadata($row->metadata ?? null);

        $renews_at = '';
        if (!empty($metadata['renews_at'])) {
            $renews_at = (string) $metadata['renews_at'];
        } elseif (!empty($metadata['renewsAt'])) {
            $renews_at = (string) $metadata['renewsAt'];
        } elseif (!empty($row->renews_at)) {
            $renews_at = (string) $row->renews_at;
        }

        $plan_hint = '';
        if (!empty($metadata['plan'])) {
            $plan_hint = self::canonicalize_plan_key((string) $metadata['plan']);
        } elseif (!empty($metadata['price_plan'])) {
            $plan_hint = self::canonicalize_plan_key((string) $metadata['price_plan']);
        } elseif (!empty($metadata['recurring'])) {
            $plan_hint = self::canonicalize_plan_key((string) $metadata['recurring']);
        }

        return [
            'id'         => isset($row->id) ? (int) $row->id : 0,
            'status'     => isset($row->status) ? (string) $row->status : '',
            'expires_at' => isset($row->expires_at) ? (string) $row->expires_at : '',
            'metadata'   => $metadata,
            'renews_at'  => $renews_at,
            'plan_hint'  => $plan_hint,
        ];
    }

    private static function refresh_license_snapshot(string $slug, array $fallback = []): array {
        if ($slug === '') {
            return $fallback;
        }

        try {
            $lm = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
            $row = $lm->get_license($slug);
            if ($row) {
                $snapshot = self::build_license_snapshot_from_row($row);

                if (self::should_enrich_license_snapshot($slug, $snapshot)) {
                    $enriched = self::attempt_remote_license_enrichment($slug, $row);
                    if ($enriched) {
                        $snapshot = self::build_license_snapshot_from_row($enriched);
                    }
                }

                return $snapshot;
            }
            return [];
        } catch (\Throwable $t) {
            return $fallback;
        }
    }

    private static function get_license_snapshot_map(): array {
        if (!class_exists('MillionDollarScript\\Classes\\Extension\\MDS_License_Manager')) {
            return [];
        }

        try {
            $map = [];
            $lm = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
            $licenses = method_exists($lm, 'get_all_licenses') ? $lm->get_all_licenses() : [];

            if (!empty($licenses)) {
                foreach ($licenses as $row) {
                    if (!is_object($row)) {
                        continue;
                    }

                    $slug_column = null;
                    if (property_exists($row, 'plugin_name') && !empty($row->plugin_name)) {
                        $slug_column = 'plugin_name';
                    } elseif (property_exists($row, 'extension_slug') && !empty($row->extension_slug)) {
                        $slug_column = 'extension_slug';
                    }

                    if ($slug_column === null) {
                        continue;
                    }

                    $slug_value = (string) $row->{$slug_column};
                    if ($slug_value === '') {
                        continue;
                    }

                    $snapshot = self::build_license_snapshot_from_row($row);
                    if (empty($snapshot)) {
                        continue;
                    }

                    $map[$slug_value] = $snapshot;
                }
            }

            return $map;
        } catch (\Throwable $t) {
            return [];
        }
    }

    private static function build_license_lookup_index(array $license_map): array {
        $index = [
            'raw'        => [],
            'slug'       => [],
            'normalized' => [],
        ];

        foreach ($license_map as $key => $snapshot) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $raw_key = strtolower($key);
            if ($raw_key !== '' && !isset($index['raw'][$raw_key])) {
                $index['raw'][$raw_key] = $snapshot;
            }

            $slug_key = sanitize_title($key);
            if ($slug_key !== '' && !isset($index['slug'][$slug_key])) {
                $index['slug'][$slug_key] = $snapshot;
            }

            $normalized = self::normalize_extension_name($key);
            if ($normalized !== '' && !isset($index['normalized'][$normalized])) {
                $index['normalized'][$normalized] = $snapshot;
            }
        }

        return $index;
    }

    private static function find_license_snapshot(array $license_lookup, array $candidates): ?array {
        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            $raw = strtolower($candidate);
            if ($raw !== '' && isset($license_lookup['raw'][$raw])) {
                return $license_lookup['raw'][$raw];
            }

            $slug = sanitize_title($candidate);
            if ($slug !== '' && isset($license_lookup['slug'][$slug])) {
                return $license_lookup['slug'][$slug];
            }

            $normalized = self::normalize_extension_name($candidate);
            if ($normalized !== '' && isset($license_lookup['normalized'][$normalized])) {
                return $license_lookup['normalized'][$normalized];
            }
        }

        return null;
    }

    private static function license_is_auto_renewing(?array $license): bool {
        if (!is_array($license) || empty($license)) {
            return false;
        }

        $status = isset($license['status']) ? strtolower((string) $license['status']) : '';
        if ($status !== '' && in_array($status, ['cancelled', 'canceled', 'expired', 'inactive', 'disabled'], true)) {
            return false;
        }

        $renews_at = isset($license['renews_at']) ? (string) $license['renews_at'] : '';
        if ($renews_at !== '') {
            return true;
        }

        $plan_hint = isset($license['plan_hint']) ? self::canonicalize_plan_key((string) $license['plan_hint']) : '';
        if ($plan_hint !== '' && !in_array($plan_hint, ['one_time', 'lifetime', 'lifetime_access', 'lifetime-license'], true)) {
            return true;
        }

        $metadata = isset($license['metadata']) && is_array($license['metadata']) ? $license['metadata'] : [];
        $metadata_plan = '';
        if (isset($metadata['plan'])) {
            $metadata_plan = self::canonicalize_plan_key((string) $metadata['plan']);
        } elseif (isset($metadata['price_plan'])) {
            $metadata_plan = self::canonicalize_plan_key((string) $metadata['price_plan']);
        } elseif (isset($metadata['recurring'])) {
            $metadata_plan = self::canonicalize_plan_key((string) $metadata['recurring']);
        }

        if ($metadata_plan !== '' && !in_array($metadata_plan, ['one_time', 'lifetime', 'lifetime_access', 'lifetime-license'], true)) {
            return true;
        }

        $flag_keys = ['auto_renew', 'autoRenew', 'is_recurring', 'recurring', 'subscription'];
        foreach ($flag_keys as $flag_key) {
            if (!array_key_exists($flag_key, $metadata)) {
                continue;
            }
            $value = $metadata[$flag_key];
            if (is_bool($value)) {
                if ($value) {
                    return true;
                }
                continue;
            }
            if (is_numeric($value)) {
                if ((int) $value === 1) {
                    return true;
                }
                continue;
            }
            if (is_string($value)) {
                $normalized = strtolower(trim($value));
                if (in_array($normalized, ['1', 'true', 'yes', 'active', 'enabled'], true)) {
                    return true;
                }
                if (in_array($normalized, ['monthly', 'yearly', 'annual', 'subscription', 'recurring'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function format_license_renewal_display(?string $renews_at, ?string $expires_at, string $plan_hint): string {
        $renews_at = is_string($renews_at) ? trim($renews_at) : '';
        $expires_at = is_string($expires_at) ? trim($expires_at) : '';

        $original_hint = strtolower($plan_hint);
        $normalized_hint = self::canonicalize_plan_key($plan_hint);

        $plan_is_subscription = in_array($normalized_hint, ['monthly', 'yearly', 'subscription'], true);
        if (!$plan_is_subscription && $original_hint !== '') {
            if (strpos($original_hint, 'month') !== false
                || strpos($original_hint, 'year') !== false
                || strpos($original_hint, 'subsc') !== false
                || strpos($original_hint, 'recurr') !== false) {
                $plan_is_subscription = true;
            }
        }
        if ($normalized_hint === '' && $original_hint === '') {
            $plan_is_subscription = true; // default when plan hint unknown
        }

        if ($renews_at !== '') {
            $timestamp = false;
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $renews_at)) {
                $timestamp = strtotime($renews_at . ' UTC');
            }
            if ($timestamp === false) {
                $timestamp = strtotime($renews_at);
            }
            if ($timestamp && $timestamp > 0) {
                $format = trim((string) get_option('date_format') . ' ' . (string) get_option('time_format'));
                if ($format === '') {
                    $format = 'Y-m-d H:i';
                }
                $display = date_i18n($format, $timestamp);
                $timezone_suffix = self::format_timezone_suffix($timestamp);
                if ($timezone_suffix !== '') {
                    $display .= ' ' . $timezone_suffix;
                }
                return $display;
            }
            return $plan_is_subscription
                ? Language::get('Renews automatically')
                : Language::get('Lifetime access');
        }

        if ($expires_at !== '') {
            $timestamp = false;
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $expires_at)) {
                $timestamp = strtotime($expires_at . ' UTC');
            }
            if ($timestamp === false) {
                $timestamp = strtotime($expires_at);
            }
            if ($timestamp && $timestamp > 0) {
                $format = trim((string) get_option('date_format') . ' ' . (string) get_option('time_format'));
                if ($format === '') {
                    $format = 'Y-m-d H:i';
                }
                $display = date_i18n($format, $timestamp);
                $timezone_suffix = self::format_timezone_suffix($timestamp);
                if ($timezone_suffix !== '') {
                    $display .= ' ' . $timezone_suffix;
                }
                return $display;
            }
            return $plan_is_subscription
                ? Language::get('Renews automatically')
                : Language::get('Lifetime access');
        }

        return $plan_is_subscription ? Language::get('Renews automatically') : '';
    }

    /**
     * Build a map of catalog entries keyed by normalized pluginName (fallback to name).
     * Only used for UI decisions; does not affect server or database logic.
     */
    private static function build_catalog_by_name(array $available_extensions): array {
        $map = [];
        foreach ($available_extensions as $item) {
            if (!is_array($item)) {
                continue;
            }
            $candidate = $item['pluginName'] ?? ($item['name'] ?? '');
            $key = self::normalize_extension_name(is_string($candidate) ? $candidate : '');
            if ($key !== '') {
                $map[$key] = $item;
            }
        }
        return $map;
    }

    private static function render_available_extension_card(array $extension, string $nonce, ?string $extension_server_error = null): string {
        $is_premium   = !empty($extension['isPremium']);
        $is_installed = !empty($extension['is_installed']);
        $is_active    = !empty($extension['is_active']);
        $slug         = (string) ($extension['slug'] ?? '');

        $local_license = is_array($extension['local_license'] ?? null) ? $extension['local_license'] : [];
        $local_status = $local_license['status'] ?? '';
        $is_licensed = self::is_active_license_status($local_status) || !empty($extension['is_licensed']);

        $license_nonce = $slug !== '' ? wp_create_nonce('mds_license_nonce_' . $slug) : wp_create_nonce('mds_extensions_nonce');

        $should_check_remote = $is_premium && $slug !== '' && !empty($local_license);
        if ($should_check_remote) {
            $is_licensed = self::is_extension_licensed($slug, self::is_active_license_status($local_status));
            $local_license = self::refresh_license_snapshot($slug, $local_license);
            $local_status = $local_license['status'] ?? '';
        }

        // Keep extension snapshot aligned with the latest license evaluation so downstream helpers stay consistent.
        $extension['local_license'] = $local_license;
        $extension['is_licensed'] = $is_licensed;
        if (empty($local_license) && !empty($extension['purchased_locally'])) {
            $extension['purchased_locally'] = false;
        }

        $has_local_license = !empty($local_license);
        $was_purchased = !empty($extension['purchased_locally']) || $has_local_license;
        $auto_renews = self::license_is_auto_renewing($local_license);
        $can_cancel_auto = $was_purchased && $slug !== '' && $auto_renews;
        $can_check_updates = $is_installed && !empty($extension['installed_plugin_file']);

        $card_classes = ['mds-extension-card'];
        $card_classes[] = $is_premium ? 'mds-extension-card--premium' : 'mds-extension-card--free';
        if ($is_installed) {
            $card_classes[] = 'mds-extension-card--installed';
        }
        if ($is_active) {
            $card_classes[] = 'mds-extension-card--active';
        }
        if (!empty($extension['purchased_locally'])) {
            $card_classes[] = 'mds-extension-card--purchased';
        }

        $data_attrs = [
            'data-extension-id'   => $extension['id'] ?? '',
            'data-extension-slug' => $slug,
            'data-version'        => $extension['version'] ?? '',
            'data-is-premium'     => $is_premium ? 'true' : 'false',
            'data-is-licensed'    => $is_licensed ? 'true' : 'false',
            'data-has-local-license' => $has_local_license ? 'true' : 'false',
            'data-purchased'      => $was_purchased ? 'true' : 'false',
            'data-auto-renewing'  => $can_cancel_auto ? 'true' : 'false',
        ];
        if ($is_installed && !empty($extension['installed_plugin_file'])) {
            $data_attrs['data-plugin-file'] = $extension['installed_plugin_file'];
        }

        $chips = [];
        if (!empty($extension['purchased_locally'])) {
            $chips[] = '<span class="mds-license-chip mds-license-chip--purchased">' . esc_html(Language::get('Purchased locally')) . '</span>';
        }

        if ($is_premium) {
            $status_raw = $local_license['status'] ?? '';
            $status = is_string($status_raw) ? strtolower($status_raw) : '';
            if ($status !== '') {
                $chip_class = 'mds-license-chip';
                if (self::is_active_license_status($status_raw)) {
                    $chip_class .= ' mds-license-chip--active';
                } else {
                    $chip_class .= ' mds-license-chip--attention';
                }
                $chips[] = '<span class="' . esc_attr($chip_class) . '">' . esc_html(Language::get('License status: ') . ucfirst($status)) . '</span>';
            }
        }

        $renew_text = '';
        if (!empty($local_license)) {
            $renew_text = self::format_license_renewal_display(
                $local_license['renews_at'] ?? null,
                $local_license['expires_at'] ?? null,
                isset($local_license['plan_hint']) ? (string) $local_license['plan_hint'] : ''
            );
        }
        if ($renew_text !== '') {
            $chips[] = '<span class="mds-license-chip mds-license-chip--renewal">' . esc_html(Language::get('Renews: ') . $renew_text) . '</span>';
        }

        $summary_html = implode('', $chips);

        $license_panel_id = '';
        $license_input_id = '';
        if ($is_premium) {
            $normalized_slug = $slug !== '' ? sanitize_title($slug) : sanitize_title(uniqid('mds')); // ensure unique id
            $license_panel_id = 'mds-card-license-' . $normalized_slug;
            $license_input_id = $license_panel_id . '-key';
        }

        $pricing_overview = is_array($extension['pricing_overview'] ?? null) ? $extension['pricing_overview'] : [];
        $purchase_links   = is_array($extension['purchase_links'] ?? null) ? $extension['purchase_links'] : [];
        $metadata         = is_array($extension['metadata'] ?? null) ? $extension['metadata'] : [];

        $current_plan_key = self::determine_current_plan_key($local_license, $extension, $metadata);
        $plan_relations   = self::extract_plan_relations($metadata);

        $pricing_html = self::render_pricing_overview_html(
            $pricing_overview,
            $purchase_links,
            $metadata,
            (string) ($extension['id'] ?? ''),
            $nonce,
            $is_premium,
            $current_plan_key,
            $plan_relations
        );

        if ($pricing_html === '' && $is_premium) {
            $pricing_html = '<div class="mds-card-pricing mds-card-pricing--empty">' . esc_html(Language::get('Pricing information will appear once Stripe plans are configured.')) . '</div>';
        }

        $action_primary = '';
        $action_secondary = '';

        $can_install_after_purchase = $was_purchased && !$is_installed;

        if ($is_installed) {
            if ($is_active) {
                $action_primary = '<span class="button button-secondary mds-ext-active" disabled="disabled">' . esc_html(Language::get('Active')) . '</span>';
            } else {
                $action_primary = '<button class="button button-secondary mds-activate-extension" data-nonce="' . esc_attr($nonce) . '" data-extension-slug="' . esc_attr($slug) . '">' . esc_html(Language::get('Activate')) . '</button>';
            }
        } else {
            if ($is_premium) {
                if ($is_licensed || $can_install_after_purchase) {
                    $action_primary = '<button class="button button-primary mds-install-extension" data-nonce="' . esc_attr($nonce) . '" data-extension-id="' . esc_attr($extension['id'] ?? '') . '" data-extension-slug="' . esc_attr($slug) . '">' . esc_html(Language::get('Install')) . '</button>';
                } elseif ($extension_server_error) {
                    $action_secondary = '<a class="button" href="' . esc_url(admin_url('plugin-install.php?tab=upload')) . '">' . esc_html(Language::get('Upload ZIP')) . '</a>';
                }
            } else {
                $action_primary = '<button class="button button-primary mds-install-extension" data-nonce="' . esc_attr($nonce) . '" data-extension-id="' . esc_attr($extension['id'] ?? '') . '" data-extension-slug="' . esc_attr($slug) . '">' . esc_html(Language::get('Install')) . '</button>';
            }
        }

        $header_action_parts = [];

        $has_action_buttons = ($action_primary !== '' || $action_secondary !== '');
        if ($has_action_buttons) {
            $buttons_markup  = '<div class="mds-card-header-buttons">';
            $buttons_markup .= $action_primary ? $action_primary : '';
            $buttons_markup .= $action_secondary ? $action_secondary : '';
            $buttons_markup .= '</div>';
            $header_action_parts[] = $buttons_markup;
        }

        if ($can_check_updates) {
            $update_button  = '<button type="button" class="button button-secondary mds-check-updates"';
            $update_button .= ' data-nonce="' . esc_attr($nonce) . '"';
            $update_button .= ' data-extension-id="' . esc_attr($extension['id'] ?? '') . '"';
            $update_button .= ' data-extension-slug="' . esc_attr($slug) . '"';
            $update_button .= ' data-plugin-file="' . esc_attr($extension['installed_plugin_file'] ?? '') . '"';
            $update_button .= ' data-current-version="' . esc_attr($extension['version'] ?? '') . '">';
            $update_button .= esc_html(Language::get('Check for Updates'));
            $update_button .= '</button>';

            $update_status = '<div class="mds-check-updates-status" aria-live="polite" aria-hidden="true"></div>';

            $header_action_parts[] = '<div class="mds-card-header-updates">' . $update_button . $update_status . '</div>';
        }

        if ($is_premium) {
            ob_start();
            ?>
            <div class="mds-card-license-trigger">
                <div class="mds-card-header-buttons mds-card-license-buttons">
                    <button type="button" class="button button-secondary mds-card-license-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr($license_panel_id); ?>">
                        <?php echo esc_html(Language::get('Manage license')); ?>
                    </button>
                </div>
                <div id="<?php echo esc_attr($license_panel_id); ?>"
                     class="mds-card-license mds-card-license-popover is-collapsed"
                     aria-hidden="true"
                     hidden>
                    <div class="mds-inline-license"
                         data-extension-slug="<?php echo esc_attr($slug); ?>"
                         data-license-nonce="<?php echo esc_attr($license_nonce); ?>">
                        <div class="mds-inline-license-row">
                            <input type="password"
                                   id="<?php echo esc_attr($license_input_id !== '' ? $license_input_id : 'mds-license-key-' . sanitize_title(uniqid('mds'))); ?>"
                                   name="license_key"
                                   class="regular-text mds-inline-license-key"
                                   placeholder="<?php echo esc_attr(Language::get('Enter license key')); ?>">
                        </div>
                        <div class="mds-inline-license-actions">
                            <button type="button"
                                    class="button button-secondary mds-inline-license-activate"
                                    data-nonce="<?php echo esc_attr($nonce); ?>">
                                <?php echo esc_html(Language::get('Activate')); ?>
                            </button>
                            <button type="button"
                                    class="button-link mds-visibility-toggle mds-inline-license-visibility"
                                    aria-label="<?php echo esc_attr(Language::get('Show license key')); ?>">
                                <span class="dashicons dashicons-hidden"></span>
                            </button>
                            <?php if ($has_local_license) : ?>
                                <button type="button"
                                        class="button-link mds-remove-license mds-inline-license-remove"
                                        data-extension-slug="<?php echo esc_attr($slug); ?>"
                                        data-nonce="<?php echo esc_attr($license_nonce); ?>">
                                    <?php echo esc_html(Language::get('Remove')); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($can_cancel_auto) : ?>
                        <div class="mds-card-license-actions">
                            <button type="button"
                                    class="button button-link mds-cancel-auto-renew"
                                    data-extension-slug="<?php echo esc_attr($slug); ?>">
                                <?php echo esc_html(Language::get('Cancel Auto-renewal')); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $header_action_parts[] = ob_get_clean();
        }

        $header_actions_html = implode('', $header_action_parts);
        $show_footer_actions = !$has_action_buttons;

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $card_classes)); ?>"<?php foreach ($data_attrs as $attr => $value) { if ($value === '' || $value === null) { continue; } echo ' ' . $attr . '="' . esc_attr($value) . '"'; } ?>>
            <div class="mds-card-header">
                <div class="mds-card-header-top">
                    <div class="mds-card-title">
                        <?php if ($is_premium) : ?>
                            <span class="mds-chip mds-chip--premium"><?php echo esc_html(Language::get('Premium')); ?></span>
                        <?php else : ?>
                            <span class="mds-chip mds-chip--free"><?php echo esc_html(Language::get('Free')); ?></span>
                        <?php endif; ?>
                        <h3 class="mds-card-title-heading">
                            <span class="mds-card-title-name"><?php echo esc_html($extension['name'] ?? ''); ?></span>
                            <?php if (!empty($extension['version'])) : ?>
                                <span class="mds-card-title-version">v<?php echo esc_html($extension['version']); ?></span>
                            <?php endif; ?>
                        </h3>
                    </div>

                    <?php if ($header_actions_html !== '') : ?>
                        <div class="mds-card-header-actions">
                            <?php echo $header_actions_html; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($summary_html !== '') : ?>
                    <div class="mds-card-summary">
                        <?php echo wp_kses_post($summary_html); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($extension['description'])) : ?>
                    <p class="mds-card-description"><?php echo esc_html(wp_trim_words((string) $extension['description'], 28)); ?></p>
                <?php endif; ?>
            </div>

            <div class="mds-card-body">
                <?php if ($pricing_html !== '') : ?>
                    <?php echo $pricing_html; ?>
                <?php elseif ($is_premium) : ?>
                    <div class="mds-card-pricing mds-card-pricing--empty"><?php echo esc_html(Language::get('Pricing information will appear once Stripe plans are configured.')); ?></div>
                <?php endif; ?>

                <?php if (!$is_premium) : ?>
                    <div class="mds-card-license">
                        <span class="mds-license-chip mds-license-chip--free"><?php echo esc_html(Language::get('No license required')); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($can_check_updates) : ?>
                    <div class="mds-card-update-panel" aria-live="polite"></div>
                <?php endif; ?>

                <?php if ($show_footer_actions && ($action_primary !== '' || $action_secondary !== '')) : ?>
                    <div class="mds-card-actions">
                        <?php echo $action_primary ? $action_primary : ''; ?>
                        <?php echo $action_secondary ? $action_secondary : ''; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function render_pricing_overview_html(
        array $pricing_overview,
        array $purchase_links,
        array $metadata,
        string $extension_id,
        string $nonce,
        bool $is_premium,
        ?string $current_plan_key,
        array $plan_relations
    ): string {
        $metadata = is_array($metadata) ? $metadata : [];
        $planOrder = self::determine_plan_order($pricing_overview, $metadata);
        $normalizedPlanOrder = self::build_plan_index_map($planOrder, true);
        $planGroups = self::build_plan_groups($metadata, $pricing_overview, $purchase_links, $planOrder);

        if (!empty($planGroups)) {
            return self::render_plan_groups_markup(
                $planGroups,
                $extension_id,
                $nonce,
                $current_plan_key,
                $plan_relations,
                $normalizedPlanOrder
            );
        }

        // Fallback to legacy rendering when no Stripe price entries are available.
        $cards = [];
        $metadataPrices = isset($metadata['stripe_prices']) && is_array($metadata['stripe_prices']) ? $metadata['stripe_prices'] : [];

        foreach ($planOrder as $planKey) {
            $planKey = (string) $planKey;
            if ($planKey === '') {
                continue;
            }

            $entries = isset($metadataPrices[$planKey]) && is_array($metadataPrices[$planKey]) ? $metadataPrices[$planKey] : [];
            if (!empty($entries)) {
                $card = self::render_plan_card_from_entries(
                    $planKey,
                    $entries,
                    $metadata,
                    $pricing_overview,
                    $purchase_links,
                    $extension_id,
                    $nonce,
                    $current_plan_key,
                    $plan_relations,
                    $normalizedPlanOrder
                );
                if ($card !== '') {
                    $cards[] = $card;
                }
                continue;
            }

            if (isset($pricing_overview['plans'][$planKey]) && is_array($pricing_overview['plans'][$planKey])) {
                $card = self::render_legacy_plan_card(
                    $planKey,
                    $pricing_overview['plans'][$planKey],
                    $purchase_links,
                    $extension_id,
                    $nonce,
                    $current_plan_key,
                    $plan_relations,
                    $normalizedPlanOrder
                );
                if ($card !== '') {
                    $cards[] = $card;
                }
            }
        }

        if (empty($cards) && isset($pricing_overview['plans']) && is_array($pricing_overview['plans'])) {
            foreach ($pricing_overview['plans'] as $planKey => $planData) {
                if (!is_array($planData)) {
                    continue;
                }
                $card = self::render_legacy_plan_card(
                    (string) $planKey,
                    $planData,
                    $purchase_links,
                    $extension_id,
                    $nonce,
                    $current_plan_key,
                    $plan_relations,
                    $normalizedPlanOrder
                );
                if ($card !== '') {
                    $cards[] = $card;
                }
            }
        }

        $cards = array_filter($cards);

        if (empty($cards)) {
            $fallback = self::render_minimal_plan_cards($current_plan_key, $plan_relations, $normalizedPlanOrder, $extension_id, $nonce);
            if (empty($fallback)) {
                return '';
            }
            return '<div class="mds-card-pricing">' . implode('', $fallback) . '</div>';
        }

        return '<div class="mds-card-pricing">' . implode('', $cards) . '</div>';
    }

    private static function build_plan_groups(
        array $metadata,
        array $pricing_overview,
        array $purchase_links,
        array $plan_order
    ): array {
        $stripe_prices = isset($metadata['stripe_prices']) && is_array($metadata['stripe_prices'])
            ? $metadata['stripe_prices']
            : [];
        $options = isset($purchase_links['options']) && is_array($purchase_links['options'])
            ? $purchase_links['options']
            : [];

        $groups = [];
        $seen_plans = [];

        $appendGroup = static function (array $group, array &$groups, array &$seen_plans): void {
            if (empty($group['entries'])) {
                return;
            }
            if (!in_array($group['key'], $seen_plans, true)) {
                $seen_plans[] = $group['key'];
                $groups[] = $group;
            }
        };

        foreach ($plan_order as $plan_key_raw) {
            $plan_key = self::canonicalize_plan_key((string) $plan_key_raw);
            if ($plan_key === '' || !isset($stripe_prices[$plan_key]) || !is_array($stripe_prices[$plan_key])) {
                continue;
            }
            $entries = self::build_plan_group_entries(
                $plan_key,
                $stripe_prices[$plan_key],
                $metadata,
                $pricing_overview,
                $options[$plan_key] ?? []
            );
            $appendGroup([
                'key'     => $plan_key,
                'label'   => self::get_plan_label($plan_key),
                'entries' => $entries,
            ], $groups, $seen_plans);
        }

        // Include any remaining plan keys not covered by plan_order.
        foreach ($stripe_prices as $plan_key => $price_entries) {
            $plan_key_canonical = self::canonicalize_plan_key((string) $plan_key);
            if ($plan_key_canonical === '' || in_array($plan_key_canonical, $seen_plans, true)) {
                continue;
            }
            if (!is_array($price_entries)) {
                continue;
            }
            $entries = self::build_plan_group_entries(
                $plan_key_canonical,
                $price_entries,
                $metadata,
                $pricing_overview,
                $options[$plan_key_canonical] ?? []
            );
            $appendGroup([
                'key'     => $plan_key_canonical,
                'label'   => self::get_plan_label($plan_key_canonical),
                'entries' => $entries,
            ], $groups, $seen_plans);
        }

        if (!empty($groups)) {
            return $groups;
        }

        return self::build_plan_groups_from_pricing_overview(
            $metadata,
            $pricing_overview,
            $purchase_links,
            $plan_order
        );
    }

    private static function build_plan_group_entries(
        string $plan_key,
        array $price_entries,
        array $metadata,
        array $pricing_overview,
        array $purchase_options
    ): array {
        $entries = [];
        $sale_enabled = self::is_sale_display_enabled($metadata, $plan_key);
        $default_entry = self::select_default_price_entry($price_entries);
        $default_price_text = ($default_entry !== null) ? self::format_price_entry_amount($default_entry) : null;
        $sale_context = null;
        if ($sale_enabled && $default_entry && $default_price_text !== null && $default_price_text !== '') {
            $sale_context = self::resolve_plan_sale_context(
                $metadata,
                $plan_key,
                $default_entry,
                $default_price_text,
                $price_entries
            );
        }
        if ($sale_enabled && $sale_context === null) {
            $sale_enabled = false;
        }

        foreach ($price_entries as $entry) {
            if (!is_array($entry) || empty($entry['price_id'])) {
                continue;
            }
            $price_id = (string) $entry['price_id'];
            $base_price_id = $price_id;
            $status = isset($entry['status']) ? strtolower((string) $entry['status']) : 'active';
            $skip_render = false;
            $label = isset($entry['label']) && is_string($entry['label']) && trim($entry['label']) !== ''
                ? trim($entry['label'])
                : self::get_plan_label($plan_key);
            $amount = self::format_price_entry_amount($entry);

            $compare_price = null;
            $badge_label = '';

            if ($sale_enabled && $sale_context !== null && !empty($entry['default'])) {
                if (!empty($sale_context['price_id'])) {
                    $price_id = (string) $sale_context['price_id'];
                }
                if (!empty($sale_context['sale_price'])) {
                    $amount = $sale_context['sale_price'];
                }
                if (!empty($sale_context['compare_price'])) {
                    $compare_price = $sale_context['compare_price'];
                }
                if ($badge_label === '' && !empty($sale_context['badge_label'])) {
                    $badge_label = $sale_context['badge_label'];
                }
            } elseif ($sale_enabled && $sale_context !== null && $sale_context['base_price_id'] !== '' && $sale_context['base_price_id'] === $price_id) {
                $skip_render = true;
            }

            $features = self::extract_plan_features($metadata, $pricing_overview, $plan_key, $base_price_id);

            $purchase_option = [];
            if (!empty($purchase_options) && is_array($purchase_options)) {
                foreach ($purchase_options as $option) {
                    if (isset($option['priceId']) && (string) $option['priceId'] === $price_id) {
                        $purchase_option = $option;
                        break;
                    }
                }
            }

            if ($status === 'legacy') {
                $skip_render = true;
            }

            if ($status === 'hidden' && empty($entry['default'])) {
                $skip_render = true;
            }

            if ($skip_render) {
                continue;
            }

            $entries[] = [
                'plan_key'      => $plan_key,
                'plan_label'    => self::get_plan_label($plan_key),
                'price_id'      => $price_id,
                'label'         => $label,
                'amount'        => $amount,
                'compare_price' => $compare_price,
                'badge_label'   => $badge_label,
                'status'        => $status,
                'is_default'    => !empty($entry['default']),
                'features'      => $features,
                'purchase'      => $purchase_option,
            ];
        }

        return $entries;
    }

    private static function build_plan_groups_from_pricing_overview(
        array $metadata,
        array $pricing_overview,
        array $purchase_links,
        array $plan_order
    ): array {
        $plans = isset($pricing_overview['plans']) && is_array($pricing_overview['plans'])
            ? $pricing_overview['plans']
            : [];

        $options = [];
        if (isset($purchase_links['options']) && is_array($purchase_links['options'])) {
            foreach ($purchase_links['options'] as $option_key => $option_list) {
                $normalized_key = self::canonicalize_plan_key((string) $option_key);
                if ($normalized_key === '') {
                    continue;
                }
                $options[$normalized_key] = is_array($option_list) ? $option_list : [];
            }
        }

        $groups = [];
        $seen = [];

        $plan_candidates = self::determine_fallback_plan_keys(
            $plan_order,
            $plans,
            $options
        );

        foreach ($plan_candidates as $plan_key) {
            if ($plan_key === '' || isset($seen[$plan_key])) {
                continue;
            }
            $plan_lookup = self::locate_pricing_plan_data($plans, $plan_key);
            $plan_data = $plan_lookup['data'];
            $plan_source_key = $plan_lookup['source_key'];
            $entry = self::build_pricing_overview_fallback_entry(
                $plan_key,
                $plan_data,
                $plan_source_key,
                $metadata,
                $pricing_overview,
                $options[$plan_key] ?? []
            );
            if ($entry !== null) {
                $groups[] = [
                    'key'     => $plan_key,
                    'label'   => self::get_plan_label($plan_key),
                    'entries' => [$entry],
                ];
                $seen[$plan_key] = true;
            }
        }

        return $groups;
    }

    private static function determine_fallback_plan_keys(array $plan_order, array $plans, array $options): array
    {
        $candidates = [];

        foreach ($plan_order as $plan) {
            $normalized = self::canonicalize_plan_key((string) $plan);
            if ($normalized !== '') {
                $candidates[] = $normalized;
            }
        }

        foreach ($plans as $plan_key => $_) {
            $normalized = self::canonicalize_plan_key((string) $plan_key);
            if ($normalized !== '') {
                $candidates[] = $normalized;
            }
        }

        foreach ($options as $plan_key => $_) {
            $normalized = self::canonicalize_plan_key((string) $plan_key);
            if ($normalized !== '') {
                $candidates[] = $normalized;
            }
        }

        if (empty($candidates)) {
            $candidates = self::STRIPE_PRICE_PLANS;
        }

        $unique = [];
        foreach ($candidates as $plan) {
            if ($plan === '' || isset($unique[$plan])) {
                continue;
            }
            $unique[$plan] = true;
        }

        return array_keys($unique);
    }

    private static function build_pricing_overview_fallback_entry(
        string $plan_key,
        ?array $plan_data,
        string $plan_source_key,
        array $metadata,
        array $pricing_overview,
        array $plan_options
    ): ?array {
        $plan_data = is_array($plan_data) ? $plan_data : [];

        $price_id = self::resolve_plan_price_id_fallback($plan_key, $metadata, $plan_data, $plan_options);
        if ($price_id === '') {
            $price_id = 'plan-' . $plan_key;
        }

        $pricing = self::resolve_plan_pricing_from_overview($plan_data, $metadata, $plan_key);

        $features = self::extract_features_from_pricing_plan($plan_data);
        if (empty($features)) {
            $features = self::extract_plan_features($metadata, $pricing_overview, $plan_key, $price_id);
        }

        if ($pricing['amount'] === '' && empty($features)) {
            $features = [Language::get('Pricing details unavailable for this plan right now.')];
        }

        $purchase_option = self::resolve_purchase_option_for_entry($plan_options, $price_id);
        $label = self::resolve_plan_entry_label($plan_key, $plan_data, $purchase_option);

        return [
            'plan_key'      => $plan_key,
            'plan_label'    => self::get_plan_label($plan_key),
            'price_id'      => $price_id,
            'label'         => $label,
            'amount'        => $pricing['amount'],
            'compare_price' => $pricing['compare'],
            'badge_label'   => $pricing['badge'],
            'status'        => 'active',
            'is_default'    => true,
            'features'      => $features,
            'purchase'      => $purchase_option,
        ];
    }

    private static function locate_pricing_plan_data(array $plans, string $plan_key): array
    {
        if (isset($plans[$plan_key]) && is_array($plans[$plan_key])) {
            return [
                'data'       => $plans[$plan_key],
                'source_key' => $plan_key,
            ];
        }

        foreach ($plans as $candidate_key => $plan_data) {
            if (self::canonicalize_plan_key((string) $candidate_key) === $plan_key && is_array($plan_data)) {
                return [
                    'data'       => $plan_data,
                    'source_key' => (string) $candidate_key,
                ];
            }
        }

        return [
            'data'       => [],
            'source_key' => '',
        ];
    }

    private static function resolve_plan_pricing_from_overview(array $plan_data, array $metadata, string $plan_key): array
    {
        $result = [
            'amount' => '',
            'compare' => '',
            'badge' => '',
        ];

        $sale_data = isset($plan_data['sale']) && is_array($plan_data['sale']) ? $plan_data['sale'] : [];
        $default_data = isset($plan_data['default']) && is_array($plan_data['default']) ? $plan_data['default'] : [];

        $sale_active = !empty($sale_data['is_active']) || !empty($sale_data['isActive']);
        $sale_amount = $sale_active ? self::format_pricing_overview_amount($sale_data) : '';
        $default_amount = self::format_pricing_overview_amount($default_data);

        if ($sale_amount !== '' && $sale_amount !== $default_amount) {
            $result['amount'] = $sale_amount;
            $result['compare'] = $default_amount;
            $label = '';
            if (isset($sale_data['label']) && is_string($sale_data['label'])) {
                $label = trim($sale_data['label']);
            } elseif (isset($sale_data['badge']) && is_string($sale_data['badge'])) {
                $label = trim($sale_data['badge']);
            }
            if ($label === '') {
                $label = Language::get('Sale');
            }
            $result['badge'] = $label;
        } elseif ($default_amount !== '') {
            $result['amount'] = $default_amount;
        }

        return $result;
    }

    private static function extract_features_from_pricing_plan(array $plan_data): array
    {
        $features = [];

        $ingest = function ($value) use (&$features): void {
            if (is_array($value)) {
                $isList = array_keys($value) === range(0, count($value) - 1);
                if ($isList) {
                    foreach ($value as $item) {
                        if (!is_string($item)) {
                            continue;
                        }
                        $item = trim($item);
                        if ($item !== '') {
                            $features[] = sanitize_text_field($item);
                        }
                    }
                } else {
                    foreach ($value as $entry) {
                        if (is_string($entry)) {
                            $items = self::parse_plan_features_to_array($entry);
                            if (!empty($items)) {
                                $features = array_merge($features, $items);
                            }
                        }
                    }
                }
                return;
            }

            if (is_string($value)) {
                $items = self::parse_plan_features_to_array($value);
                if (!empty($items)) {
                    $features = array_merge($features, $items);
                }
            }
        };

        if (isset($plan_data['features'])) {
            $ingest($plan_data['features']);
        }

        if (isset($plan_data['default']) && is_array($plan_data['default'])) {
            if (isset($plan_data['default']['features'])) {
                $ingest($plan_data['default']['features']);
            }
        }

        foreach (['feature_summary', 'featureSummary', 'feature_details', 'featureDetails'] as $key) {
            if (isset($plan_data[$key])) {
                $ingest($plan_data[$key]);
            }
        }

        if (empty($features) && isset($plan_data['summary']) && is_string($plan_data['summary'])) {
            $items = self::parse_plan_features_to_array($plan_data['summary']);
            if (!empty($items)) {
                $features = array_merge($features, $items);
            }
        }

        $features = array_values(array_filter(array_map('trim', $features), static function ($item) {
            return $item !== '';
        }));

        return $features;
    }

    private static function format_pricing_overview_amount(?array $price): string
    {
        if (!is_array($price)) {
            return '';
        }

        if (isset($price['formatted']) && is_string($price['formatted'])) {
            $formatted = trim($price['formatted']);
            if ($formatted !== '') {
                return $formatted;
            }
        }

        $amount = null;
        foreach (['amount', 'amount_value', 'amountValue'] as $key) {
            if (isset($price[$key]) && is_numeric($price[$key])) {
                $amount = (float) $price[$key];
                break;
            }
        }
        if ($amount === null) {
            foreach (['amount_cents', 'amountCents'] as $key) {
                if (isset($price[$key]) && is_numeric($price[$key])) {
                    $amount = (float) $price[$key];
                    $amount = $amount >= 100 ? $amount / 100 : $amount;
                    break;
                }
            }
        }

        $currency = '';
        foreach (['currency', 'currency_code', 'currencyCode'] as $key) {
            if (isset($price[$key]) && is_string($price[$key])) {
                $currency = (string) $price[$key];
                break;
            }
        }

        if ($amount !== null) {
            return self::format_currency_amount($amount, $currency);
        }

        if (isset($price['label']) && is_string($price['label'])) {
            $label = trim($price['label']);
            if ($label !== '') {
                return $label;
            }
        }

        return '';
    }

    private static function resolve_plan_price_id_fallback(
        string $plan_key,
        array $metadata,
        array $plan_data,
        array $plan_options
    ): string {
        $candidates = [];

        $fallback_key = self::STRIPE_PRICE_FALLBACK_KEYS[$plan_key] ?? null;
        if ($fallback_key && isset($metadata[$fallback_key]) && is_string($metadata[$fallback_key])) {
            $candidates[] = (string) $metadata[$fallback_key];
        }

        $candidates = array_merge($candidates, self::collect_price_ids_from_plan_data($plan_data));

        foreach ($plan_options as $option) {
            if (isset($option['priceId']) && is_string($option['priceId'])) {
                $candidate = trim($option['priceId']);
                if ($candidate !== '') {
                    $candidates[] = $candidate;
                }
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private static function collect_price_ids_from_plan_data(array $plan_data): array
    {
        $results = [];

        $extract = static function ($source) use (&$results): void {
            if (!is_array($source)) {
                return;
            }
            foreach (['price_id', 'priceId', 'id', 'stripe_price', 'stripePrice', 'default_price', 'defaultPrice'] as $key) {
                if (isset($source[$key]) && is_string($source[$key])) {
                    $candidate = trim($source[$key]);
                    if ($candidate !== '' && !in_array($candidate, $results, true)) {
                        $results[] = $candidate;
                    }
                }
            }
        };

        $extract($plan_data);

        foreach (['default', 'sale', 'base', 'price'] as $key) {
            if (isset($plan_data[$key]) && is_array($plan_data[$key])) {
                $extract($plan_data[$key]);
            }
        }

        foreach (['tiers', 'prices', 'options'] as $key) {
            if (isset($plan_data[$key]) && is_array($plan_data[$key])) {
                foreach ($plan_data[$key] as $entry) {
                    if (is_array($entry)) {
                        $extract($entry);
                    }
                }
            }
        }

        return $results;
    }

    private static function resolve_purchase_option_for_entry(array $plan_options, string $price_id): array
    {
        foreach ($plan_options as $option) {
            if (isset($option['priceId']) && (string) $option['priceId'] === $price_id) {
                return is_array($option) ? $option : [];
            }
        }

        foreach ($plan_options as $option) {
            if (!empty($option['default'])) {
                return is_array($option) ? $option : [];
            }
        }

        return isset($plan_options[0]) && is_array($plan_options[0]) ? $plan_options[0] : [];
    }

    private static function resolve_plan_entry_label(string $plan_key, array $plan_data, array $purchase_option): string
    {
        $candidates = [];

        foreach (['label', 'title', 'name'] as $key) {
            if (isset($plan_data[$key]) && is_string($plan_data[$key])) {
                $value = trim($plan_data[$key]);
                if ($value !== '') {
                    $candidates[] = $value;
                }
            }
        }

        foreach (['label', 'name'] as $key) {
            if (isset($plan_data['default'][$key]) && is_string($plan_data['default'][$key])) {
                $value = trim($plan_data['default'][$key]);
                if ($value !== '') {
                    $candidates[] = $value;
                }
            }
        }

        if (!empty($purchase_option['label']) && is_string($purchase_option['label'])) {
            $value = trim($purchase_option['label']);
            if ($value !== '') {
                $candidates[] = $value;
            }
        }

        foreach ($candidates as $value) {
            if (strcasecmp($value, 'default') !== 0) {
                return $value;
            }
        }

        return self::get_plan_label($plan_key);
    }

    private static function render_plan_groups_markup(
        array $plan_groups,
        string $extension_id,
        string $nonce,
        ?string $current_plan_key,
        array $plan_relations,
        array $plan_order
    ): string {
        if (empty($plan_groups)) {
            return '';
        }

        $current_plan_canonical = self::canonicalize_plan_key($current_plan_key ?? '');
        $active_index = 0;
        foreach ($plan_groups as $index => $group) {
            if ($group['key'] === $current_plan_canonical) {
                $active_index = $index;
                break;
            }
        }

        $tabs = [];
        $panels = [];

        foreach ($plan_groups as $index => $group) {
            $is_active = $index === $active_index;
            $tab_classes = 'mds-plan-tab' . ($is_active ? ' is-active' : '');
            $aria_selected = $is_active ? 'true' : 'false';
            $tabs[] = '<button type="button" class="' . esc_attr($tab_classes) . '" data-plan-tab="' . esc_attr($group['key']) . '" role="tab" aria-selected="' . esc_attr($aria_selected) . '">' . esc_html($group['label']) . '</button>';

            $panel_classes = 'mds-plan-tabpanel' . ($is_active ? ' is-active' : '');
            $cards = [];
            foreach ($group['entries'] as $entry) {
                $cards[] = self::render_plan_entry_card(
                    $entry,
                    $extension_id,
                    $nonce,
                    $current_plan_key,
                    $plan_relations,
                    $plan_order
                );
            }
            $panel_body = !empty($cards)
                ? implode('', $cards)
                : '<div class="mds-plan-features-empty">' . esc_html(Language::get('No plans available for this billing cadence.')) . '</div>';
            $panels[] = '<section class="' . esc_attr($panel_classes) . '" data-plan-panel="' . esc_attr($group['key']) . '" role="tabpanel">' . $panel_body . '</section>';
        }

        return '<div class="mds-plan-tabs">'
            . '<div class="mds-plan-tablist" role="tablist">' . implode('', $tabs) . '</div>'
            . '<div class="mds-plan-tabpanels">' . implode('', $panels) . '</div>'
            . '</div>';
    }

    private static function render_plan_entry_card(
        array $entry,
        string $extension_id,
        string $nonce,
        ?string $current_plan_key,
        array $plan_relations,
        array $plan_order
    ): string {
        $plan_key = isset($entry['plan_key']) ? self::canonicalize_plan_key((string) $entry['plan_key']) : '';
        $price_id = isset($entry['price_id']) ? (string) $entry['price_id'] : '';
        if ($plan_key === '' || $price_id === '') {
            return '';
        }

        $label = isset($entry['label']) ? (string) $entry['label'] : self::get_plan_label($plan_key);
        $plan_label = isset($entry['plan_label']) ? (string) $entry['plan_label'] : self::get_plan_label($plan_key);
        $amount = isset($entry['amount']) ? (string) $entry['amount'] : '';
        $compare_price = isset($entry['compare_price']) ? (string) $entry['compare_price'] : '';
        $badge_label = isset($entry['badge_label']) ? (string) $entry['badge_label'] : '';
        $status = isset($entry['status']) ? strtolower((string) $entry['status']) : 'active';
        $is_default = !empty($entry['is_default']);
        $features = isset($entry['features']) && is_array($entry['features']) ? $entry['features'] : [];

        $card_classes = ['mds-plan-card'];
        if ($is_default) {
            $card_classes[] = 'is-highlighted';
        }
        if ($status === 'hidden') {
            $card_classes[] = 'is-muted';
        }

        $pricing_html = '';
        if ($amount !== '') {
            $pricing_html .= '<div class="mds-plan-pricing">';
            if ($compare_price !== '' && $compare_price !== $amount) {
                $pricing_html .= '<span class="mds-plan-price mds-plan-price--sale">' . esc_html($amount) . '</span>';
                $pricing_html .= '<span class="mds-plan-price mds-plan-price--compare">' . esc_html($compare_price) . '</span>';
            } else {
                $pricing_html .= '<span class="mds-plan-price">' . esc_html($amount) . '</span>';
            }
            if ($badge_label !== '') {
                $pricing_html .= '<span class="mds-plan-badge">' . esc_html($badge_label) . '</span>';
            }
            $pricing_html .= '</div>';
        }

        $status_badges = [];
        if ($is_default) {
            $status_badges[] = '<span class="mds-plan-chip mds-plan-chip--primary">' . esc_html(Language::get('Default')) . '</span>';
        }
        if ($status === 'hidden') {
            $status_badges[] = '<span class="mds-plan-chip">' . esc_html(Language::get('Hidden')) . '</span>';
        } elseif ($status === 'legacy') {
            $status_badges[] = '<span class="mds-plan-chip">' . esc_html(Language::get('Legacy')) . '</span>';
        }
        $status_html = '';
        if (!empty($status_badges)) {
            $status_html = '<div class="mds-plan-chipset">' . implode('', $status_badges) . '</div>';
        }

        $features_html = '';
        if (!empty($features)) {
            $features_html .= '<ul class="mds-plan-features">';
            foreach ($features as $feature) {
                $feature_markup = self::sanitize_plan_feature_markup((string) $feature);
                if ($feature_markup !== '') {
                    $features_html .= '<li>' . $feature_markup . '</li>';
                }
            }
            $features_html .= '</ul>';
        }

        $action_markup = self::render_plan_action_markup(
            $plan_key,
            $extension_id,
            $nonce,
            $current_plan_key,
            $plan_relations,
            $plan_order,
            $price_id,
            $label
        );

        $card_body = '<div class="' . esc_attr(implode(' ', $card_classes)) . '" data-plan="' . esc_attr($plan_key) . '" data-price-id="' . esc_attr($price_id) . '">';
        $card_body .= '<header class="mds-plan-card__header">'
            . '<div class="mds-plan-card__heading">'
            . '<span class="mds-plan-card__plan">' . esc_html($plan_label) . '</span>'
            . '<h4 class="mds-plan-title">' . esc_html($label) . '</h4>'
            . '</div>'
            . $status_html
            . '</header>';
        $card_body .= $pricing_html;
        $card_body .= $features_html !== '' ? $features_html : '';
        $card_body .= '<div class="mds-plan-feedback" aria-live="polite"></div>';
        $card_body .= '<div class="mds-plan-actions">' . $action_markup . '</div>';
        $card_body .= '</div>';

        return $card_body;
    }

    /**
     * Determine the order plans should render in, defaulting to the marketing layout.
     */
    private static function determine_plan_order(array $pricing_overview, array $metadata): array {
        $order = [];

        if (isset($metadata['plan_order']) && is_array($metadata['plan_order'])) {
            foreach ($metadata['plan_order'] as $plan) {
                if (is_string($plan) && $plan !== '') {
                    $order[] = $plan;
                }
            }
        } elseif (isset($pricing_overview['plan_order']) && is_array($pricing_overview['plan_order'])) {
            foreach ($pricing_overview['plan_order'] as $plan) {
                if (is_string($plan) && $plan !== '') {
                    $order[] = $plan;
                }
            }
        }

        $order = array_values(array_unique(array_merge($order, self::STRIPE_PRICE_PLANS)));

        return $order;
    }

    /**
     * Return a canonicalised plan order for the supplied extension context.
     *
     * @param array $extension
     * @param array $metadata
     */
    private static function determine_canonical_plan_order(array $extension, array $metadata): array {
        $pricing_overview = is_array($extension['pricing_overview'] ?? null) ? $extension['pricing_overview'] : [];
        $planOrder = self::determine_plan_order($pricing_overview, $metadata);

        $canonical = [];
        foreach ($planOrder as $plan) {
            if (!is_string($plan) || $plan === '') {
                continue;
            }
            $normalized = self::canonicalize_plan_key($plan);
            if ($normalized === '') {
                continue;
            }
            if (!in_array($normalized, $canonical, true)) {
                $canonical[] = $normalized;
            }
        }

        return $canonical;
    }

    /**
     * Render a plan card using Stripe metadata entries.
     */
    private static function render_plan_card_from_entries(
        string $plan_key,
        array $entries,
        array $metadata,
        array $pricing_overview,
        array $purchase_links,
        string $extension_id,
        string $nonce,
        ?string $current_plan_key,
        array $plan_relations,
        array $plan_order
    ): string {
        $defaultEntry = self::select_default_price_entry($entries);
        if (!$defaultEntry) {
            return '';
        }

        $defaultPrice = self::format_price_entry_amount($defaultEntry);
        if ($defaultPrice === null || $defaultPrice === '') {
            return '';
        }

        $saleEnabled = self::is_sale_display_enabled($metadata, $plan_key);
        $saleContext = null;
        if ($saleEnabled) {
            $saleContext = self::resolve_plan_sale_context($metadata, $plan_key, $defaultEntry, $defaultPrice, $entries);
            if ($saleContext === null) {
                $saleEnabled = false;
            }
        }
        $comparePrice = null;
        $badgeLabel = '';
        if ($saleEnabled && $saleContext !== null) {
            if (!empty($saleContext['compare_price'])) {
                $comparePrice = $saleContext['compare_price'];
            }
            if (!empty($saleContext['sale_price'])) {
                $defaultPrice = $saleContext['sale_price'];
            }
            if (!empty($saleContext['badge_label'])) {
                $badgeLabel = $saleContext['badge_label'];
            }
        }
        $tiers = self::build_plan_tiers($entries, $defaultEntry);
        $features = self::extract_plan_features($metadata, $pricing_overview, $plan_key, null);

        $pricingHtml = '<div class="mds-plan-pricing">';
        if ($comparePrice !== null && $comparePrice !== '') {
            $pricingHtml .= '<span class="mds-plan-price mds-plan-price--sale">' . esc_html($defaultPrice) . '</span>';
            $pricingHtml .= '<span class="mds-plan-price mds-plan-price--compare">' . esc_html($comparePrice) . '</span>';
        } else {
            $pricingHtml .= '<span class="mds-plan-price">' . esc_html($defaultPrice) . '</span>';
        }
        if ($badgeLabel !== '') {
            $pricingHtml .= '<span class="mds-plan-badge">' . esc_html($badgeLabel) . '</span>';
        }
        $pricingHtml .= '</div>';

        $tiersHtml = '';
        if (!empty($tiers)) {
            $tiersHtml .= '<ul class="mds-plan-tiers">';
            foreach ($tiers as $tier) {
                $tiersHtml .= '<li><span class="mds-plan-tier-label">' . esc_html($tier['label']) . '</span><span class="mds-plan-tier-price">' . esc_html($tier['price']) . '</span></li>';
            }
            $tiersHtml .= '</ul>';
        }

        $featuresHtml = '';
        if (!empty($features)) {
            $featuresHtml .= '<ul class="mds-plan-features">';
            foreach ($features as $feature) {
                $featuresHtml .= '<li>' . esc_html($feature) . '</li>';
            }
            $featuresHtml .= '</ul>';
        }

        $button = self::render_plan_action_markup(
            $plan_key,
            $extension_id,
            $nonce,
            $current_plan_key,
            $plan_relations,
            $plan_order,
            '',
            self::get_plan_label($plan_key)
        );
        if ($button === '') {
            return '';
        }

        return '<div class="mds-plan-card" data-plan="' . esc_attr($plan_key) . '">' .
            '<h4 class="mds-plan-title">' . esc_html(self::get_plan_label($plan_key)) . '</h4>' .
            $pricingHtml .
            $tiersHtml .
            $featuresHtml .
            '<div class="mds-plan-feedback" aria-live="polite"></div>' .
            '<div class="mds-plan-actions">' . $button . '</div>' .
            '</div>';
    }

    /**
     * Determine if sale pricing elements should display for a given plan.
     */
    private static function is_sale_display_enabled(array $metadata, string $plan_key): bool {
        if (isset($metadata['show_sale_price'])) {
            return (bool) $metadata['show_sale_price'];
        }

        if (isset($metadata['release']) && is_array($metadata['release'])) {
            $releaseMeta = $metadata['release'];
            if (array_key_exists('show_sale_price', $releaseMeta)) {
                return (bool) $releaseMeta['show_sale_price'];
            }
        }

        return false;
    }

    /**
     * Legacy fallback for older pricing_overview payloads.
     */
    private static function render_legacy_plan_card(
        string $plan_key,
        array $plan_data,
        array $purchase_links,
        string $extension_id,
        string $nonce,
        ?string $current_plan_key,
        array $plan_relations,
        array $plan_order
    ): string {
        $default_display = '';
        if (!empty($plan_data['sale']['is_active']) && !empty($plan_data['sale']['formatted'])) {
            $sale_price = $plan_data['sale']['formatted'];
            $sale_label = $plan_data['sale']['label'] ?? '';
            $compare = $plan_data['default']['formatted'] ?? '';
            $default_display .= '<span class="mds-plan-price mds-plan-price--sale">' . esc_html($sale_price) . '</span>';
            if ($compare) {
                $default_display .= '<span class="mds-plan-price mds-plan-price--compare">' . esc_html($compare) . '</span>';
            }
            if ($sale_label) {
                $default_display .= '<span class="mds-plan-badge">' . esc_html($sale_label) . '</span>';
            }
        } elseif (!empty($plan_data['default']['formatted'])) {
            $default_display .= '<span class="mds-plan-price">' . esc_html($plan_data['default']['formatted']) . '</span>';
        }

        $feature_list = '';
        if (!empty($plan_data['features']) && is_array($plan_data['features'])) {
            $items = array_map('sanitize_text_field', array_filter($plan_data['features']));
            if (!empty($items)) {
                $feature_list = '<ul class="mds-plan-features">';
                foreach ($items as $item) {
                    $feature_list .= '<li>' . esc_html($item) . '</li>';
                }
                $feature_list .= '</ul>';
            }
        }

        if ($default_display === '' && $feature_list === '') {
            return '';
        }

        $button = self::render_plan_action_markup(
            $plan_key,
            $extension_id,
            $nonce,
            $current_plan_key,
            $plan_relations,
            $plan_order,
            '',
            self::get_plan_label($plan_key)
        );
        if ($button === '') {
            return '';
        }

        return '<div class="mds-plan-card" data-plan="' . esc_attr($plan_key) . '">' .
            '<h4 class="mds-plan-title">' . esc_html(self::get_plan_label($plan_key)) . '</h4>' .
            ($default_display !== '' ? '<div class="mds-plan-pricing">' . $default_display . '</div>' : '') .
            $feature_list .
            '<div class="mds-plan-feedback" aria-live="polite"></div>' .
            '<div class="mds-plan-actions">' . $button . '</div>' .
            '</div>';
    }

    private static function determine_current_plan_key(array $local_license, array $extension, array $metadata): string {
        $has_active_license = self::is_active_license_status($local_license['status'] ?? '');
        if (!$has_active_license && !empty($extension['is_licensed'])) {
            $has_active_license = true;
        }

        if (!$has_active_license) {
            return '';
        }

        $planCandidates = [];
        $priceCandidates = [];

        $collectPlan = static function ($value) use (&$planCandidates): void {
            if (is_array($value)) {
                foreach ($value as $entry) {
                    if (is_string($entry)) {
                        $normalized = self::guess_plan_key_from_string($entry);
                        if ($normalized !== '') {
                            $planCandidates[] = $normalized;
                        }
                    }
                }
                return;
            }

            if (is_string($value)) {
                $normalized = self::guess_plan_key_from_string($value);
                if ($normalized !== '') {
                    $planCandidates[] = $normalized;
                }
            }
        };

        $collectPrice = static function ($value) use (&$priceCandidates): void {
            $append = static function (string $candidate) use (&$priceCandidates): void {
                $candidate = strtolower(trim($candidate));
                if ($candidate !== '' && !in_array($candidate, $priceCandidates, true)) {
                    $priceCandidates[] = $candidate;
                }
            };

            if (is_array($value)) {
                foreach ($value as $entry) {
                    if (is_string($entry)) {
                        $append($entry);
                    }
                }
                return;
            }

            if (is_string($value)) {
                $append($value);
            }
        };

        $planKeys = ['plan_hint', 'plan', 'plan_key', 'price_plan', 'recurring'];
        $priceKeys = ['price_id', 'priceId', 'stripe_price', 'stripe_price_id', 'stripePrice', 'stripePriceId', 'price'];

        foreach ($planKeys as $key) {
            if (isset($local_license[$key])) {
                $collectPlan($local_license[$key]);
            }
        }
        foreach ($priceKeys as $key) {
            if (isset($local_license[$key])) {
                $collectPrice($local_license[$key]);
            }
        }

        if (isset($local_license['metadata']) && is_array($local_license['metadata'])) {
            foreach ($planKeys as $key) {
                if (isset($local_license['metadata'][$key])) {
                    $collectPlan($local_license['metadata'][$key]);
                }
            }
            foreach ($priceKeys as $key) {
                if (isset($local_license['metadata'][$key])) {
                    $collectPrice($local_license['metadata'][$key]);
                }
            }
        }

        foreach (['current_plan', 'currentPlan', 'active_plan', 'plan_key', 'plan'] as $key) {
            if (isset($extension[$key])) {
                $collectPlan($extension[$key]);
            }
        }

        foreach (['current_plan', 'currentPlan', 'active_plan', 'plan_key', 'plan'] as $key) {
            if (isset($metadata[$key])) {
                $collectPlan($metadata[$key]);
            }
        }

        foreach (['price_id', 'priceId', 'stripe_price', 'stripe_price_id', 'stripePrice', 'stripePriceId', 'price'] as $key) {
            if (isset($metadata[$key])) {
                $collectPrice($metadata[$key]);
            }
        }

        if (!empty($planCandidates)) {
            $firstCandidate = $planCandidates[0];
            if ($firstCandidate === 'subscription') {
                $subscriptionPlans = self::gather_subscription_plan_candidates($metadata, $extension);
                $refined = array_values(array_filter($subscriptionPlans, static function ($plan) {
                    return $plan !== 'subscription';
                }));
                if (count($refined) === 1) {
                    return $refined[0];
                }

                $defaultPlan = '';
                if (isset($metadata['default_plan']) && is_string($metadata['default_plan'])) {
                    $defaultPlan = self::canonicalize_plan_key($metadata['default_plan']);
                } elseif (isset($metadata['defaultPlan']) && is_string($metadata['defaultPlan'])) {
                    $defaultPlan = self::canonicalize_plan_key($metadata['defaultPlan']);
                } elseif (isset($extension['metadata']['default_plan']) && is_string($extension['metadata']['default_plan'])) {
                    $defaultPlan = self::canonicalize_plan_key($extension['metadata']['default_plan']);
                } elseif (isset($extension['metadata']['defaultPlan']) && is_string($extension['metadata']['defaultPlan'])) {
                    $defaultPlan = self::canonicalize_plan_key($extension['metadata']['defaultPlan']);
                }
                if ($defaultPlan !== '' && in_array($defaultPlan, $refined, true)) {
                    return $defaultPlan;
                }

                if (!empty($subscriptionPlans)) {
                    foreach ($subscriptionPlans as $plan) {
                        if ($plan !== 'subscription') {
                            return $plan;
                        }
                    }
                }
            }

            return $firstCandidate;
        }

        if (!empty($priceCandidates)) {
            $matched = self::find_plan_by_price_ids($priceCandidates, $metadata, $extension);
            if ($matched !== '') {
                return $matched;
            }
        }

        $planOrder = self::determine_canonical_plan_order($extension, $metadata);
        if (self::license_suggests_subscription($local_license)) {
            $subscriptionPlans = self::gather_subscription_plan_candidates($metadata, $extension);
            $subscriptionPlans = array_values(array_filter($subscriptionPlans, static function ($plan) {
                return $plan !== '';
            }));

            foreach ($subscriptionPlans as $plan) {
                if ($plan !== 'subscription') {
                    return $plan;
                }
            }

            foreach ($planOrder as $candidate) {
                if (in_array($candidate, ['monthly', 'yearly'], true)) {
                    return $candidate;
                }
            }

            if (in_array('subscription', $subscriptionPlans, true)) {
                return 'subscription';
            }
        }

        if (self::license_suggests_one_time($local_license)) {
            foreach ($planOrder as $candidate) {
                if ($candidate === 'one_time') {
                    return $candidate;
                }
            }
        }

        return '';
    }

    private static function gather_subscription_plan_candidates(array $metadata, array $extension): array {
        $candidates = [];

        $collect = function ($value) use (&$candidates): void {
            if (is_array($value)) {
                foreach ($value as $entry) {
                    if (!is_string($entry)) {
                        continue;
                    }
                    $canonical = self::canonicalize_plan_key($entry);
                    if ($canonical === '' || !in_array($canonical, ['monthly', 'yearly', 'subscription'], true)) {
                        continue;
                    }
                    $candidates[$canonical] = true;
                }
                return;
            }

            if (!is_string($value)) {
                return;
            }

            $canonical = self::canonicalize_plan_key($value);
            if ($canonical === '' || !in_array($canonical, ['monthly', 'yearly', 'subscription'], true)) {
                return;
            }
            $candidates[$canonical] = true;
        };

        foreach (['plan_order', 'available_plans'] as $key) {
            if (isset($metadata[$key])) {
                $collect($metadata[$key]);
            } elseif (isset($extension[$key])) {
                $collect($extension[$key]);
            }
        }

        if (isset($metadata['plan_relations']) && is_array($metadata['plan_relations'])) {
            $collect(array_keys($metadata['plan_relations']));
        }
        if (isset($extension['metadata']['plan_relations']) && is_array($extension['metadata']['plan_relations'])) {
            $collect(array_keys($extension['metadata']['plan_relations']));
        }

        if (isset($metadata['stripe_prices']) && is_array($metadata['stripe_prices'])) {
            $collect(array_keys($metadata['stripe_prices']));
        } elseif (isset($extension['metadata']['stripe_prices']) && is_array($extension['metadata']['stripe_prices'])) {
            $collect(array_keys($extension['metadata']['stripe_prices']));
        }

        if (isset($metadata['pricing_overview']['plans']) && is_array($metadata['pricing_overview']['plans'])) {
            $collect(array_keys($metadata['pricing_overview']['plans']));
        } elseif (isset($extension['pricing_overview']['plans']) && is_array($extension['pricing_overview']['plans'])) {
            $collect(array_keys($extension['pricing_overview']['plans']));
        }

        return array_keys($candidates);
    }

    private static function license_suggests_subscription(array $license): bool {
        $fields = [
            $license['renews_at'] ?? null,
            $license['expires_at'] ?? null,
        ];

        if (isset($license['metadata']) && is_array($license['metadata'])) {
            $metadata = $license['metadata'];
            foreach (['renews_at', 'renewsAt', 'billing_period_end', 'billingPeriodEnd', 'stripe_subscription_id', 'subscription_id'] as $key) {
                if (isset($metadata[$key])) {
                    $fields[] = $metadata[$key];
                }
            }
            if (isset($metadata['stripe_checkout_mode']) && is_string($metadata['stripe_checkout_mode'])) {
                $mode = strtolower($metadata['stripe_checkout_mode']);
                if (in_array($mode, ['subscription', 'recurring'], true)) {
                    return true;
                }
            }
        }

        foreach ($fields as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private static function license_suggests_one_time(array $license): bool {
        if (self::license_suggests_subscription($license)) {
            return false;
        }

        $metadata = isset($license['metadata']) && is_array($license['metadata']) ? $license['metadata'] : [];
        $planCandidates = [];
        foreach (['plan', 'plan_hint', 'planKey', 'price_plan', 'recurring'] as $key) {
            if (isset($metadata[$key]) && is_string($metadata[$key])) {
                $planCandidates[] = $metadata[$key];
            }
        }

        foreach ($planCandidates as $candidate) {
            if (self::canonicalize_plan_key($candidate) === 'one_time') {
                return true;
            }
        }

        return true;
    }

    private static function extract_plan_relations(array $metadata): array {
        $source = [];
        foreach (['plan_relations', 'planRelations', 'plan_relation', 'planRelation'] as $key) {
            if (isset($metadata[$key]) && is_array($metadata[$key])) {
                $source = $metadata[$key];
                break;
            }
        }

        $relations = [];
        foreach ($source as $plan => $relation) {
            $planKey = self::canonicalize_plan_key((string) $plan);
            if ($planKey === '' || !is_array($relation)) {
                continue;
            }

            $relations[$planKey] = [
                'upgrade_to'   => self::normalize_plan_list($relation['upgrade_to'] ?? $relation['upgradeTo'] ?? $relation['upgrade'] ?? []),
                'downgrade_to' => self::normalize_plan_list($relation['downgrade_to'] ?? $relation['downgradeTo'] ?? $relation['downgrade'] ?? []),
                'switch_to'    => self::normalize_plan_list($relation['switch_to'] ?? $relation['switchTo'] ?? []),
                'allow'        => self::normalize_plan_list($relation['allow'] ?? []),
                'disallow'     => self::normalize_plan_list($relation['disallow'] ?? []),
                'locked'       => !empty($relation['locked']),
            ];
        }

        return $relations;
    }

    private static function normalize_plan_list($value): array {
        $items = [];
        if (is_string($value)) {
            $items[] = $value;
        } elseif (is_array($value)) {
            $items = $value;
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }
            $plan = self::canonicalize_plan_key($item);
            if ($plan !== '' && !in_array($plan, $normalized, true)) {
                $normalized[] = $plan;
            }
        }
        return $normalized;
    }

    private static function build_plan_index_map(array $plan_order, bool $return_list = false): array {
        $map = [];
        $list = [];
        foreach ($plan_order as $plan) {
            $normalized = self::canonicalize_plan_key((string) $plan);
            if ($normalized === '' || isset($map[$normalized])) {
                continue;
            }
            $map[$normalized] = count($list);
            $list[] = $normalized;
        }

        return $return_list ? $list : $map;
    }

    private static function render_plan_action_markup(
        string $plan_key,
        string $extension_id,
        string $nonce,
        ?string $current_plan_key,
        array $plan_relations,
        array $plan_order,
        string $price_id = '',
        string $display_label = ''
    ): string {
        $normalizedPlan = self::canonicalize_plan_key($plan_key);
        if ($normalizedPlan === '') {
            return '';
        }

        $current = self::canonicalize_plan_key($current_plan_key ?? '');
        if ($current !== '' && $normalizedPlan === $current) {
            $indicator = self::get_current_plan_indicator_label($current, $plan_key);
            return '<span class="mds-plan-current" aria-disabled="true">' . esc_html($indicator) . '</span>';
        }

        if ($current !== '') {
            if (!self::is_plan_transition_allowed($current, $normalizedPlan, $plan_relations, $plan_order)) {
                return '<span class="mds-plan-locked" aria-disabled="true">' . esc_html(Language::get('Not available')) . '</span>';
            }

            $direction = self::compare_plan_positions($current, $normalizedPlan, $plan_order);
            $labelKey = $normalizedPlan !== '' ? $normalizedPlan : $plan_key;
            $base_label = self::get_plan_label($labelKey);
            if ($display_label !== '' && strcasecmp($display_label, $base_label) !== 0) {
                $plan_label = sprintf('%s (%s)', $display_label, $base_label);
            } else {
                $plan_label = $display_label !== '' ? $display_label : $base_label;
            }
            if ($direction > 0) {
                $text = sprintf(Language::get('Upgrade to %s'), $plan_label);
            } elseif ($direction < 0) {
                $text = sprintf(Language::get('Downgrade to %s'), $plan_label);
            } else {
                $text = sprintf(Language::get('Switch to %s'), $plan_label);
            }
        } else {
            $labelKey = $normalizedPlan !== '' ? $normalizedPlan : $plan_key;
            $base_label = self::get_plan_label($labelKey);
            $plan_label = $display_label !== '' ? $display_label : $base_label;
            if ($display_label !== '' && strcasecmp($display_label, $base_label) !== 0) {
                $plan_label = sprintf('%s (%s)', $display_label, $base_label);
            }
            $text = sprintf(Language::get('Choose %s'), $plan_label);
        }

        return '<button class="button button-secondary mds-purchase-extension" data-extension-id="' . esc_attr($extension_id) . '" data-nonce="' . esc_attr($nonce) . '" data-plan="' . esc_attr($plan_key) . '" data-price-id="' . esc_attr($price_id) . '">' . esc_html($text) . '</button>';
    }

    private static function is_plan_transition_allowed(string $current, string $target, array $plan_relations, array $plan_order): bool {
        $current = self::canonicalize_plan_key($current);
        $target = self::canonicalize_plan_key($target);

        if ($current === '' || $target === '' || $current === $target) {
            return false;
        }

        if (isset($plan_relations[$current])) {
            $rel = $plan_relations[$current];
            if (!empty($rel['locked'])) {
                return false;
            }
            if (!empty($rel['disallow']) && in_array($target, $rel['disallow'], true)) {
                return false;
            }
            $allowed = array_unique(array_merge($rel['upgrade_to'], $rel['downgrade_to'], $rel['switch_to'], $rel['allow']));
            if (!empty($allowed)) {
                return in_array($target, $allowed, true);
            }
        }

        $indexMap = self::build_plan_index_map($plan_order);
        $currentIndex = $indexMap[$current] ?? null;
        $targetIndex = $indexMap[$target] ?? null;
        if ($currentIndex === null || $targetIndex === null) {
            return true;
        }

        return $targetIndex > $currentIndex;
    }

    private static function compare_plan_positions(string $current, string $target, array $plan_order): int {
        $current = self::canonicalize_plan_key($current);
        $target = self::canonicalize_plan_key($target);

        $indexMap = self::build_plan_index_map($plan_order);
        $currentIndex = $indexMap[$current] ?? null;
        $targetIndex = $indexMap[$target] ?? null;
        if ($currentIndex === null || $targetIndex === null) {
            return 0;
        }

        return $targetIndex <=> $currentIndex;
    }

    private static function render_minimal_plan_cards(
        ?string $current_plan_key,
        array $plan_relations,
        array $plan_order,
        string $extension_id,
        string $nonce
    ): array {
        $planSet = [];
        foreach ($plan_order as $plan) {
            $canonical = self::canonicalize_plan_key((string) $plan);
            if ($canonical === '') {
                continue;
            }
            $planSet[$canonical] = $canonical;
        }

        if (empty($planSet)) {
            foreach (array_keys($plan_relations) as $plan) {
                $canonical = self::canonicalize_plan_key((string) $plan);
                if ($canonical === '') {
                    continue;
                }
                $planSet[$canonical] = $canonical;
            }
        }

        if ($current_plan_key !== null && $current_plan_key !== '') {
            $canonicalCurrent = self::canonicalize_plan_key($current_plan_key);
            if ($canonicalCurrent !== '') {
                $planSet[$canonicalCurrent] = $canonicalCurrent;
            }
        }

        if (empty($planSet)) {
            return [];
        }

        $plans = array_values($planSet);

        $cards = [];
        foreach ($plans as $plan) {
            $planKey = (string) $plan;
            $action = self::render_plan_action_markup(
                $planKey,
                $extension_id,
                $nonce,
                $current_plan_key,
                $plan_relations,
                $plan_order,
                '',
                self::get_plan_label($planKey)
            );
            if ($action === '') {
                continue;
            }

            $label = self::get_plan_label(self::canonicalize_plan_key($planKey) ?: $planKey);
            $message = ($current_plan_key && self::canonicalize_plan_key($planKey) === self::canonicalize_plan_key($current_plan_key))
                ? Language::get('Current plan pricing information is unavailable at the moment.')
                : Language::get('Pricing details unavailable for this plan right now.');

            $cards[] = '<div class="mds-plan-card" data-plan="' . esc_attr($planKey) . '">' .
                '<h4 class="mds-plan-title">' . esc_html($label) . '</h4>' .
                '<p class="mds-plan-placeholder">' . esc_html($message) . '</p>' .
                '<div class="mds-plan-feedback" aria-live="polite"></div>' .
                '<div class="mds-plan-actions">' . $action . '</div>' .
                '</div>';
        }

        return $cards;
    }

    private static function find_plan_by_price_ids(array $price_candidates, array $metadata, array $extension): string {
        if (empty($price_candidates)) {
            return '';
        }

        $price_candidates = array_map(static function ($value) {
            return strtolower(trim((string) $value));
        }, $price_candidates);
        $price_candidates = array_values(array_filter(array_unique($price_candidates), static function ($value) {
            return $value !== '';
        }));

        if (empty($price_candidates)) {
            return '';
        }

        $metadata_prices = [];

        if (isset($metadata['stripe_prices']) && is_array($metadata['stripe_prices'])) {
            $metadata_prices = $metadata['stripe_prices'];
        } elseif (isset($extension['metadata']['stripe_prices']) && is_array($extension['metadata']['stripe_prices'])) {
            $metadata_prices = $extension['metadata']['stripe_prices'];
        }

        if (!empty($metadata_prices)) {
            foreach ($metadata_prices as $planKey => $entries) {
                if (!is_array($entries)) {
                    continue;
                }
                foreach ($entries as $entry) {
                    $ids = self::extract_price_id_candidates($entry);
                    if (!empty($ids) && array_intersect($ids, $price_candidates)) {
                        return self::canonicalize_plan_key((string) $planKey);
                    }
                }
            }
        }

        // Fallback: inspect pricing_overview legacy structures if available.
        $pricing = [];
        if (isset($extension['pricing_overview']) && is_array($extension['pricing_overview'])) {
            $pricing = $extension['pricing_overview'];
        } elseif (isset($metadata['pricing_overview']) && is_array($metadata['pricing_overview'])) {
            $pricing = $metadata['pricing_overview'];
        }

        if (!empty($pricing['plans']) && is_array($pricing['plans'])) {
            foreach ($pricing['plans'] as $planKey => $planData) {
                if (!is_array($planData)) {
                    continue;
                }
                $ids = [];
                foreach (['price_id', 'priceId', 'stripe_price', 'stripe_price_id', 'stripePrice', 'stripePriceId', 'default_price', 'defaultPrice'] as $key) {
                    if (isset($planData[$key])) {
                        $extracted = self::extract_price_id_candidates($planData[$key]);
                        if (!empty($extracted)) {
                            $ids = array_merge($ids, $extracted);
                        }
                    }
                }
                if (!empty($ids) && array_intersect($ids, $price_candidates)) {
                    return self::canonicalize_plan_key((string) $planKey);
                }
            }
        }

        return '';
    }

    /**
     * @param mixed $entry
     * @return array<int, string>
     */
    private static function extract_price_id_candidates($entry): array {
        $candidates = [];

        $append = static function ($value) use (&$candidates): void {
            if (!is_string($value)) {
                return;
            }
            $value = strtolower(trim($value));
            if ($value !== '' && !in_array($value, $candidates, true)) {
                $candidates[] = $value;
            }
        };

        if (is_string($entry)) {
            $append($entry);
        } elseif (is_array($entry)) {
            foreach (['price_id', 'priceId', 'stripe_price', 'stripe_price_id', 'stripePrice', 'stripePriceId', 'id', 'default_price', 'defaultPrice'] as $key) {
                if (isset($entry[$key])) {
                    $append($entry[$key]);
                }
            }
            if (isset($entry['metadata']) && is_array($entry['metadata'])) {
                foreach (['price_id', 'priceId', 'stripe_price', 'stripe_price_id', 'stripePrice', 'stripePriceId', 'id'] as $key) {
                    if (isset($entry['metadata'][$key])) {
                        $append($entry['metadata'][$key]);
                    }
                }
            }
        }

        return $candidates;
    }

    private static function site_has_any_active_license(): bool {
        try {
            $manager = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
            $licenses = method_exists($manager, 'get_all_licenses') ? $manager->get_all_licenses() : [];
            if (empty($licenses)) {
                return false;
            }
            foreach ($licenses as $license) {
                $status = isset($license->status) ? strtolower((string) $license->status) : '';
                if (in_array($status, ['active', 'valid', 'enabled', 'paid'], true)) {
                    return true;
                }
            }
        } catch (\Throwable $t) {
            return false;
        }

        return false;
    }

    /**
     * Resolve a translated plan title.
     */
    private static function get_plan_label(string $plan_key): string {
        $labels = [
            'monthly'  => Language::get('Monthly subscription'),
            'yearly'   => Language::get('Yearly subscription'),
            'one_time' => Language::get('One-time purchase'),
        ];

        return $labels[$plan_key] ?? ucfirst(str_replace('_', ' ', $plan_key));
    }

    private static function get_current_plan_indicator_label(string $normalized_plan_key, string $fallback_plan = ''): string {
        $normalized = self::canonicalize_plan_key($normalized_plan_key);
        if ($normalized === 'one_time') {
            return Language::get('Purchased');
        }

        if (in_array($normalized, ['monthly', 'yearly', 'subscription'], true)) {
            return Language::get('Currently subscribed');
        }

        if ($fallback_plan !== '') {
            $guessed = self::guess_plan_key_from_string($fallback_plan);
            if ($guessed === 'one_time') {
                return Language::get('Purchased');
            }
            if (in_array($guessed, ['monthly', 'yearly', 'subscription'], true)) {
                return Language::get('Currently subscribed');
            }
        }

        return Language::get('Current plan');
    }

    /**
     * Select the default entry from a Stripe plan list.
     */
    private static function select_default_price_entry(array $entries): ?array {
        foreach ($entries as $entry) {
            if (!empty($entry['default']) && strtolower((string) ($entry['status'] ?? 'active')) !== 'legacy') {
                return $entry;
            }
        }

        foreach ($entries as $entry) {
            if (!empty($entry['default'])) {
                return $entry;
            }
        }

        foreach ($entries as $entry) {
            if (strtolower((string) ($entry['status'] ?? 'active')) !== 'legacy') {
                return $entry;
            }
        }

        return $entries[0] ?? null;
    }

    /**
     * Fetch the plan_sales map from metadata (prefer release scope).
     */
    private static function normalize_plan_sale_entries($value): array
    {
        if ($value instanceof \stdClass) {
            $value = json_decode(wp_json_encode($value), true);
        }

        if (!is_array($value)) {
            return [];
        }

        $isList = array_keys($value) === range(0, count($value) - 1);
        if ($isList) {
            $entries = [];
            foreach ($value as $item) {
                $entries = array_merge($entries, self::normalize_plan_sale_entries($item));
            }
            return $entries;
        }

        return [$value];
    }

    private static function get_plan_sales_map(array $metadata): array
    {
        $maps = [];

        if (isset($metadata['release']) && is_array($metadata['release'])
            && isset($metadata['release']['plan_sales']) && is_array($metadata['release']['plan_sales'])
        ) {
            $maps[] = $metadata['release']['plan_sales'];
        }

        if (isset($metadata['plan_sales']) && is_array($metadata['plan_sales'])) {
            $maps[] = $metadata['plan_sales'];
        }

        $result = [];
        foreach ($maps as $map) {
            foreach ($map as $key => $sale) {
                if (!is_string($key) || $key === '') {
                    continue;
                }
                $planKey = self::canonicalize_plan_key($key);
                if ($planKey === '') {
                    continue;
                }
                $entries = self::normalize_plan_sale_entries($sale);
                if (empty($entries)) {
                    continue;
                }
                if (!isset($result[$planKey])) {
                    $result[$planKey] = [];
                }
                $result[$planKey] = array_merge($result[$planKey], $entries);
            }
        }

        return $result;
    }

    private static function parse_plan_sale_boundary($value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            $timestamp = strtotime($value);
            return $timestamp ? $timestamp : null;
        }
        return null;
    }

    private static function is_plan_sale_active(array $sale): bool
    {
        $now = function_exists('current_time') ? (int) current_time('timestamp', true) : time();

        $start = self::parse_plan_sale_boundary($sale['starts_at'] ?? ($sale['startsAt'] ?? null));
        if ($start !== null && $now < $start) {
            return false;
        }

        $end = self::parse_plan_sale_boundary($sale['ends_at'] ?? ($sale['endsAt'] ?? null));
        if ($end !== null && $now > $end) {
            return false;
        }

        return true;
    }

    private static function get_plan_sale_amount_value(array $sale): ?float
    {
        if (isset($sale['amount_cents'])) {
            $cents = $sale['amount_cents'];
            if (is_numeric($cents)) {
                $amount = (float) $cents / 100;
                return $amount > 0 ? $amount : null;
            }
        }

        foreach (['amount', 'price'] as $key) {
            if (!isset($sale[$key])) {
                continue;
            }
            $value = $sale[$key];
            if (is_numeric($value)) {
                $amount = (float) $value;
                return $amount > 0 ? $amount : null;
            }
            if (is_string($value)) {
                $clean = trim($value);
                if ($clean === '') {
                    continue;
                }
                $normalized = preg_replace('/[^0-9,\.\-]/', '', $clean);
                if ($normalized === '' || $normalized === '-' || $normalized === '.') {
                    continue;
                }
                if (strpos($normalized, ',') !== false && strpos($normalized, '.') === false) {
                    $normalized = str_replace(',', '.', $normalized);
                } else {
                    $normalized = str_replace(',', '', $normalized);
                }
                $amount = (float) $normalized;
                if ($amount > 0) {
                    return $amount;
                }
            }
        }

        return null;
    }

    private static function resolve_plan_sale_context(
        array $metadata,
        string $plan_key,
        array $defaultEntry,
        string $defaultPrice,
        array $price_entries
    ): ?array
    {
        $planSales = self::get_plan_sales_map($metadata);
        if (empty($planSales[$plan_key]) || !is_array($planSales[$plan_key])) {
            return null;
        }

        $selectedSale = null;
        $selectedStart = null;

        foreach ($planSales[$plan_key] as $saleCandidate) {
            if (!is_array($saleCandidate)) {
                continue;
            }
            $saleAmount = self::get_plan_sale_amount_value($saleCandidate);
            if ($saleAmount === null || $saleAmount <= 0) {
                continue;
            }
            if (!self::is_plan_sale_active($saleCandidate)) {
                continue;
            }
            $startBoundary = self::parse_plan_sale_boundary($saleCandidate['starts_at'] ?? ($saleCandidate['startsAt'] ?? null));
            $start = $startBoundary ?? PHP_INT_MIN;
            if ($selectedSale === null || $start > $selectedStart) {
                $selectedSale = $saleCandidate;
                $selectedStart = $start;
            }
        }

        if ($selectedSale === null) {
            return null;
        }

        $saleAmount = self::get_plan_sale_amount_value($selectedSale);
        if ($saleAmount === null || $saleAmount <= 0) {
            return null;
        }

        $derivedDefault = self::derive_price_amount($defaultEntry);
        $currency = '';
        $originalAmount = null;
        if (is_array($derivedDefault)) {
            if (!empty($derivedDefault['currency'])) {
                $currency = (string) $derivedDefault['currency'];
            }
            if (isset($derivedDefault['amount'])) {
                $originalAmount = (float) $derivedDefault['amount'];
            }
        }

        $saleMapping = [];
        if (
            isset($metadata['sale_price_map']) &&
            is_array($metadata['sale_price_map']) &&
            isset($metadata['sale_price_map'][$plan_key]) &&
            is_array($metadata['sale_price_map'][$plan_key])
        ) {
            $saleMapping = $metadata['sale_price_map'][$plan_key];
        }

        $defaultEntryMeta = is_array($defaultEntry['metadata'] ?? null)
            ? $defaultEntry['metadata']
            : [];
        if (
            empty($saleMapping) &&
            !empty($defaultEntryMeta['base_price_id']) &&
            is_string($defaultEntryMeta['base_price_id'])
        ) {
            $saleMapping = [
                'price_id'      => (string) ($defaultEntry['price_id'] ?? ''),
                'base_price_id' => (string) $defaultEntryMeta['base_price_id'],
            ];
        }

        $baseEntry = null;
        if (!empty($saleMapping['base_price_id'])) {
            foreach ($price_entries as $entry) {
                if ((string) ($entry['price_id'] ?? '') === (string) $saleMapping['base_price_id']) {
                    $baseEntry = $entry;
                    break;
                }
            }
        }

        $baseDerived = $baseEntry ? self::derive_price_amount($baseEntry) : null;
        $defaultDerived = self::derive_price_amount($defaultEntry);
        $derivedSource = $baseDerived ?? $defaultDerived;

        $currency = '';
        $originalAmount = null;
        if (is_array($derivedSource)) {
            if (!empty($derivedSource['currency'])) {
                $currency = (string) $derivedSource['currency'];
            }
            if (isset($derivedSource['amount'])) {
                $originalAmount = (float) $derivedSource['amount'];
            }
        }

        if ($baseDerived === null && $defaultDerived && $originalAmount === null && isset($defaultDerived['amount'])) {
            $originalAmount = (float) $defaultDerived['amount'];
        }

        if ($baseDerived !== null && $originalAmount !== null && abs($originalAmount - $saleAmount) < 0.0001) {
            return null;
        }

        $salePrice = self::format_currency_amount($saleAmount, $currency);
        if ($salePrice === '') {
            return null;
        }

        $badge = '';
        if (isset($selectedSale['label'])) {
            $badge = sanitize_text_field((string) $selectedSale['label']);
        }
        if ($badge === '') {
            $badge = Language::get('Sale');
        }

        $comparePriceText = '';
        if ($baseEntry) {
            $formattedBase = self::format_price_entry_amount($baseEntry);
            if ($formattedBase !== null && $formattedBase !== '') {
                $comparePriceText = $formattedBase;
            }
        }
        if ($comparePriceText === '') {
            $comparePriceText = $defaultPrice;
        }

        return [
            'sale_price'    => $salePrice,
            'compare_price' => $comparePriceText,
            'badge_label'   => $badge,
            'price_id'      => isset($saleMapping['price_id']) ? (string) $saleMapping['price_id'] : '',
            'base_price_id' => isset($saleMapping['base_price_id']) ? (string) $saleMapping['base_price_id'] : '',
        ];
    }

    /**
     * Build additional tier listings for active, non-default entries.
     *
     * @return array<int, array{label:string, price:string}>
     */
    private static function build_plan_tiers(array $entries, array $defaultEntry): array {
        $tiers = [];
        $defaultId = (string) ($defaultEntry['price_id'] ?? '');

        foreach ($entries as $entry) {
            if ((string) ($entry['price_id'] ?? '') === $defaultId) {
                continue;
            }
            $status = strtolower((string) ($entry['status'] ?? 'active'));
            if ($status !== 'active') {
                continue;
            }
            $amount = self::format_price_entry_amount($entry);
            if ($amount === null || $amount === '') {
                continue;
            }
            $label = trim((string) ($entry['label'] ?? ''));
            if ($label === '' || strcasecmp($label, 'default') === 0) {
                $label = Language::get('Additional tier');
            }
            $tiers[] = [
                'label' => $label,
                'price' => $amount,
            ];
        }

        return $tiers;
    }

    /**
     * Retrieve a sanitized list of plan features from metadata or legacy payloads.
     *
     * @return array<int, string>
     */
    private static function extract_plan_features(array $metadata, array $pricing_overview, string $plan_key, ?string $price_id = null): array {
        if ($price_id !== null
            && isset($metadata['plan_features_array_by_price'][$price_id])
            && is_array($metadata['plan_features_array_by_price'][$price_id])
        ) {
            $items = array_map('trim', $metadata['plan_features_array_by_price'][$price_id]);
            return array_values(array_filter($items, static function ($item) {
                return $item !== '';
            }));
        }

        if (isset($metadata['plan_features_array'][$plan_key]) && is_array($metadata['plan_features_array'][$plan_key])) {
            $items = array_map('trim', $metadata['plan_features_array'][$plan_key]);
            return array_values(array_filter($items, static function ($item) {
                return $item !== '';
            }));
        }

        if ($price_id !== null
            && isset($metadata['plan_feature_map'][$price_id])
            && is_string($metadata['plan_feature_map'][$price_id])
        ) {
            return self::parse_plan_features_to_array($metadata['plan_feature_map'][$price_id]);
        }

        if (isset($metadata['plan_features'][$plan_key]) && is_string($metadata['plan_features'][$plan_key])) {
            return self::parse_plan_features_to_array($metadata['plan_features'][$plan_key]);
        }

        if (isset($pricing_overview['plans'][$plan_key]['features']) && is_array($pricing_overview['plans'][$plan_key]['features'])) {
            $items = [];
            foreach ($pricing_overview['plans'][$plan_key]['features'] as $feature) {
                if (is_string($feature)) {
                    $feature = trim($feature);
                    if ($feature !== '') {
                        $items[] = sanitize_text_field($feature);
                    }
                }
            }
            return $items;
        }

        return [];
    }

    /**
     * Extract a badge-friendly label from a price entry.
     */
    private static function get_entry_badge_label(array $entry): string {
        $label = isset($entry['label']) ? trim((string) $entry['label']) : '';
        if ($label === '' || strcasecmp($label, 'default') === 0) {
            return '';
        }
        return $label;
    }

    /**
     * Format a price entry amount into a human-readable string.
     */
    private static function format_price_entry_amount(array $entry): ?string {
        $derived = self::derive_price_amount($entry);
        if ($derived === null) {
            return null;
        }

        if (isset($derived['formatted']) && $derived['formatted'] !== '') {
            return $derived['formatted'];
        }

        if (!isset($derived['amount'])) {
            return null;
        }

        $currency = isset($derived['currency']) ? (string) $derived['currency'] : '';
        return self::format_currency_amount((float) $derived['amount'], $currency);
    }

    /**
     * Pull out the numeric amount and currency data from a price entry.
     *
     * @return array{amount?:float, currency?:string, formatted?:string}|null
     */
    private static function derive_price_amount(array $entry): ?array {
        $metadata = is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [];

        $currency = '';
        if (isset($metadata['currency']) && is_string($metadata['currency'])) {
            $currency = strtoupper($metadata['currency']);
        } elseif (isset($entry['currency']) && is_string($entry['currency'])) {
            $currency = strtoupper($entry['currency']);
        }

        $candidates = [
            $metadata['amount_cents'] ?? null,
            $metadata['amount'] ?? null,
            $metadata['unit_amount'] ?? null,
            $metadata['unitAmount'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }
            if (is_numeric($candidate)) {
                $numeric = (float) $candidate;
                $asString = is_string($candidate) ? $candidate : (string) $candidate;
                $hasDecimals = strpos($asString, '.') !== false || fmod($numeric, 1.0) !== 0.0;

                $amount = $hasDecimals ? $numeric : ($numeric >= 100 ? ($numeric / 100) : $numeric);

                return [
                    'amount'   => round($amount, 2),
                    'currency' => $currency,
                ];
            }
        }

        if (isset($metadata['formatted']) && is_string($metadata['formatted'])) {
            return [
                'formatted' => trim($metadata['formatted']),
                'currency'  => $currency,
            ];
        }

        return null;
    }

    /**
     * Format a numeric amount using WooCommerce (if available) or fallback logic.
     */
    private static function format_currency_amount(float $amount, string $currency): string {
        $normalizedCurrency = $currency !== '' ? strtoupper($currency) : (string) Options::get_option('currency', 'USD');

        if (function_exists('wc_price')) {
            $formatted = wc_price($amount, ['currency' => $normalizedCurrency]);
            if (is_string($formatted)) {
                return trim(wp_strip_all_tags($formatted));
            }
        }

        $symbol = '';
        if (function_exists('get_woocommerce_currency_symbol')) {
            $symbol = get_woocommerce_currency_symbol($normalizedCurrency);
        }
        if ($symbol === '') {
            $symbol = (string) Options::get_option('currency-symbol', '$');
        }

        $number = number_format($amount, 2, '.', ',');

        if ($symbol !== '') {
            return $symbol . $number;
        }

        return $normalizedCurrency . ' ' . $number;
    }
    
    // --- AJAX Handlers ---

    /**
     * AJAX handler for fetching all extensions.
     */
    public static function ajax_fetch_extensions(): void {
        // Verify nonce for security
        check_ajax_referer( 'mds_extensions_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get('Permission denied.') ], 403 );
        }

        try {
            $available_extensions = self::fetch_available_extensions();
            wp_send_json_success(['extensions' => $available_extensions]);
        } catch (\Exception $e) {
            error_log('Error fetching extensions: ' . $e->getMessage());
            wp_send_json_error(['message' => Language::get('Failed to fetch extensions from server.')]);
        }
    }

    /**
     * AJAX handler for fetching single extension.
     */
    public static function ajax_fetch_extension(): void {
        // Verify nonce for security
        check_ajax_referer( 'mds_extensions_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get('Permission denied.') ], 403 );
        }

        $extension_id  = sanitize_text_field($_POST['extension_id'] ?? '');
        $extension_slug = sanitize_text_field($_POST['extension_slug'] ?? '');

        if (empty($extension_id)) {
            wp_send_json_error(['message' => Language::get('Extension ID is required.')]);
        }

        try {
            $user_configured_url = Options::get_option('extension_server_url', 'http://extension-server-dev:3000');
            // Fetch a license key if available for premium downloads (decrypt if needed)
            $license_key = '';
            if ($extension_slug !== '') {
                if (class_exists('MillionDollarScript\\Classes\\Extension\\MDS_License_Manager')) {
                    $lm = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
                    $lic = $lm->get_license($extension_slug);
                    if ($lic && !empty($lic->license_key)) {
                        $pt = \MillionDollarScript\Classes\Extension\LicenseCrypto::decryptFromCompact($lic->license_key);
                        if (!empty($pt) && ($lic->status === 'active')) {
                            $license_key = $pt;
                        }
                    }
                }
            }
            
            $args = [
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'MDS-WordPress-Plugin/' . MDS_VERSION,
                ],
                'sslverify' => !Utility::is_development_environment(),
            ];
            
            if (!empty($license_key)) {
                $args['headers']['x-license-key'] = $license_key;
            }
            
            // Resolve a working base using same candidate logic as fetch_available_extensions()
            $candidates = [
                rtrim((string)$user_configured_url, '/'),
                'http://extension-server:3000',
                'http://extension-server-dev:3000',
                'http://host.docker.internal:15346',
                'http://localhost:15346',
            ];
            
            $response = null;
            $working_base = null;
            foreach ($candidates as $base) {
                if (empty($base)) { continue; }
                $api_url = rtrim($base, '/') . '/api/extensions/' . urlencode($extension_id);
                $res = wp_remote_get($api_url, $args);
                if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200) {
                    $response = $res;
                    $working_base = $base;
                    break;
                }
            }
            
            if (!$response) {
                throw new \Exception('Failed to connect to extension server for extension detail.');
            }
            
            if (is_wp_error($response)) {
                throw new \Exception('Failed to connect to extension server: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code !== 200) {
                if ($response_code === 404) {
                    wp_send_json_error(['message' => Language::get('Extension not found.')]);
                } else {
                    throw new \Exception('Extension server returned error: ' . $response_code);
                }
            }
            
            $data = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from extension server');
            }

            // Normalize detail shape and purchase metadata
            $detail = [];
            if (is_array($data)) {
                if (isset($data['data']) && is_array($data['data'])) {
                    $detail = $data['data'];
                } elseif (isset($data['extension']) && is_array($data['extension'])) {
                    $detail = $data['extension'];
                } else {
                    $detail = $data;
                }
            }

            // Normalize purchase links (to snake_case) and anyAvailable flag
            $purchase_links = [
                'one_time' => null,
                'monthly'  => null,
                'yearly'   => null,
                'default'  => null,
            ];
            $server_links = [];
            if (isset($detail['purchase']) && is_array($detail['purchase']) && isset($detail['purchase']['links']) && is_array($detail['purchase']['links'])) {
                $server_links = $detail['purchase']['links'];
            }

            $abs = static function($url) use ($working_base, $user_configured_url) {
                if (!is_string($url) || $url === '') {
                    return null;
                }
                if (preg_match('#^https?://#i', $url)) {
                    return $url;
                }
                $candidate_base = is_string($working_base) && $working_base !== '' ? rtrim($working_base, '/') : '';
                if ($candidate_base === '' && is_string($user_configured_url) && $user_configured_url !== '') {
                    $candidate_base = rtrim($user_configured_url, '/');
                }
                if ($candidate_base === '') {
                    $candidate_base = 'http://localhost:15346';
                }
                return rtrim($candidate_base, '/') . '/' . ltrim($url, '/');
            };

            if (!empty($server_links)) {
                $purchase_links['one_time'] = isset($server_links['oneTime']) ? $abs($server_links['oneTime']) : null;
                $purchase_links['monthly']  = isset($server_links['monthly']) ? $abs($server_links['monthly']) : null;
                $purchase_links['yearly']   = isset($server_links['yearly']) ? $abs($server_links['yearly']) : null;
                $purchase_links['default']  = isset($server_links['default']) ? $abs($server_links['default']) : null;
            }

            $any_available = (bool) ( $detail['purchase']['anyAvailable'] ?? ( $purchase_links['one_time'] || $purchase_links['monthly'] || $purchase_links['yearly'] || $purchase_links['default'] ) );

            $detail['purchase'] = [
                'anyAvailable' => $any_available,
            ];
            $detail['purchase_links'] = $purchase_links;
            $detail['can_purchase'] = $any_available;
            
            wp_send_json_success(['extension' => $detail]);
            
        } catch (\Exception $e) {
            error_log('Error fetching extension: ' . $e->getMessage());
            wp_send_json_error(['message' => Language::get('Failed to fetch extension from server.')]);
        }
    }
    
    /**
     * AJAX handler for installing extension from server.
     */
    public static function ajax_install_extension(): void {
        // Verify nonce for security
        check_ajax_referer( 'mds_extensions_nonce', 'nonce' );

        if ( ! current_user_can( 'install_plugins' ) ) {
            wp_send_json_error( [ 'message' => Language::get('You do not have permission to install extensions.') ] );
        }

        $extension_id = sanitize_text_field($_POST['extension_id'] ?? ''); // UUID
        // The extension_slug is sent from the frontend, derived from the extension's name.
    // We trust the frontend to send a slug that was originally determined by sanitize_title(extension_name).
    $extension_slug = sanitize_text_field($_POST['extension_slug'] ?? '');
    if (empty($extension_slug)) {
        wp_send_json_error(['message' => Language::get('Extension slug is required.')]);
        return; // Ensure execution stops
    }
    // No need to re-sanitize here if we trust the source (which is our own fetch_available_extensions via JS)

        if (empty($extension_id)) {
            wp_send_json_error(['message' => Language::get('Extension ID (UUID) is required.')]);
        }

        try {
            // Default to the service name and internal port for Docker environments.
            // The user can override this in MDS settings if their setup is different.
            $user_configured_url = Options::get_option('extension_server_url', 'http://extension-server-dev:3000');
            // Fetch a license key if available for premium downloads
            $license_key = '';
            if (!empty($extension_slug)) {
                if (class_exists('MillionDollarScript\\Classes\\Extension\\MDS_License_Manager')) {
                    $lm = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
                    $lic = $lm->get_license($extension_slug);
                    if ($lic && !empty($lic->license_key) && ($lic->status === 'active')) {
                        $pt = \MillionDollarScript\Classes\Extension\LicenseCrypto::decryptFromCompact((string)$lic->license_key);
                        if (!empty($pt)) {
                            $license_key = $pt;
                        }
                    }
                }
            }

            error_log('[MDS Extension Install] Extension ID: ' . $extension_id);
            error_log('[MDS Extension Install] Retrieved Extension Server URL: ' . $user_configured_url);
            error_log('[MDS Extension Install] Retrieved License Key: ' . (empty($license_key) ? 'EMPTY' : $license_key));
            
            // Resolve a working base using same candidate logic as fetch_available_extensions()
            $candidates = [
                rtrim((string)$user_configured_url, '/'),
                'http://extension-server:3000',
                'http://extension-server-dev:3000',
                'http://host.docker.internal:15346',
                'http://localhost:15346',
            ];
            $working_base = null;
            foreach ($candidates as $base) {
                if (empty($base)) { continue; }
                // Try /api/public/ping first
                $probe = rtrim($base, '/') . '/api/public/ping';
                $probe_res = wp_remote_get($probe, [ 'timeout' => 10, 'sslverify' => !Utility::is_development_environment() ]);
                $code = !is_wp_error($probe_res) ? wp_remote_retrieve_response_code($probe_res) : 0;
                if ($code === 200) {
                    $working_base = $base;
                    break;
                }
                // Fallback to /health
                $probe2 = rtrim($base, '/') . '/health';
                $probe2_res = wp_remote_get($probe2, [ 'timeout' => 10, 'sslverify' => !Utility::is_development_environment() ]);
                $code2 = !is_wp_error($probe2_res) ? wp_remote_retrieve_response_code($probe2_res) : 0;
                if ($code2 === 200) {
                    $working_base = $base;
                    break;
                }
                // Fallback to a simple GET on /api/extensions
                $probe3 = rtrim($base, '/') . '/api/extensions';
                $probe3_res = wp_remote_get($probe3, [ 'timeout' => 10, 'sslverify' => !Utility::is_development_environment() ]);
                $code3 = !is_wp_error($probe3_res) ? wp_remote_retrieve_response_code($probe3_res) : 0;
                if ($code3 === 200) {
                    $working_base = $base;
                    break;
                }
            }
            if (!$working_base) {
                throw new \Exception(Language::get('Extension server unreachable.'));
            }
            
            $download_url = rtrim($working_base, '/') . '/api/extensions/' . urlencode($extension_id) . '/download';
            
            if (!empty($license_key)) {
                $download_url .= '?licenseKey=' . urlencode($license_key);
            }

            error_log('[MDS Extension Install] Final Download URL: ' . $download_url);
            
            $result = self::install_extension_from_url($extension_id, $extension_slug, $download_url, $license_key);
            wp_send_json_success($result);
            
        } catch (\Exception $e) {
            error_log('Error installing extension: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
/**
 * Find the main plugin file by its slug (directory name).
 *
 * @param string $slug The plugin slug.
 * @return string|null The path to the main plugin file relative to the plugins directory, or null if not found.
 */
protected static function find_plugin_file_by_slug(string $slug): ?string {
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $installed_plugins = get_plugins();
    foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
        // Check if the plugin directory matches the slug
        if ( dirname( $plugin_file ) === $slug ) {
            return $plugin_file;
        }
        // Fallback check if the plugin file itself (without .php) matches the slug,
        // e.g., for single-file plugins where slug might be 'my-plugin' and file 'my-plugin.php'
        if (basename( $plugin_file, '.php') === $slug && dirname( $plugin_file ) === '.') {
             return $plugin_file;
        }
    }
    return null;
}

    /**
     * Install an extension from a download URL.
     *
     * @param string $extension_id The extension ID.
     * @param string $download_url The download URL for the extension.
     * @return array Result of the installation.
     * @throws \Exception If the installation fails.
     */
    public static function install_extension_from_url( string $extension_id, string $extension_slug, string $download_url, string $license_key = '' ): array {
    // DIAGNOSTIC: Check for ZipArchive class
    if ( ! class_exists( 'ZipArchive' ) ) {
        $zip_error_msg = Language::get('PHP ZipArchive extension is missing on the server. This is required to install extensions. Please ask your hosting provider or system administrator to install/enable it.');
        error_log('[MDS Extension Install] CRITICAL_PREREQUISITE_FAIL: ZipArchive class not found. ' . $zip_error_msg);
        throw new \Exception($zip_error_msg);
    } else {
        error_log('[MDS Extension Install] DIAGNOSTIC: ZipArchive class found and available.');
    }

    // DIAGNOSTIC: Attempt to initialize WP_Filesystem to check for direct write access
    // This is more for debugging insight; WP_Upgrader will do its own fs_connect.
    if ( ! function_exists( 'request_filesystem_credentials' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php'; // Ensure file.php is loaded for request_filesystem_credentials
    }
    ob_start(); // Prevent request_filesystem_credentials from outputting HTML in AJAX
    $creds = request_filesystem_credentials( admin_url(), '', false, false, null );
    $credentials_form_output = ob_get_clean();
    if (!empty($credentials_form_output)) {
        // This indicates request_filesystem_credentials tried to output a form.
        error_log('[MDS Extension Install] DIAGNOSTIC: request_filesystem_credentials generated output (unexpected in AJAX): ' . $credentials_form_output);
    }

    if ( false === $creds ) {
        error_log('[MDS Extension Install] DIAGNOSTIC: request_filesystem_credentials returned false. This might mean direct filesystem access is not configured, or credentials would be required if it were an interactive session.');
        // Not necessarily fatal here, as WP_Upgrader might still attempt 'direct' method.
    } elseif ( ! WP_Filesystem( $creds ) ) {
        error_log('[MDS Extension Install] DIAGNOSTIC: WP_Filesystem() call failed after getting credentials. Filesystem could not be initialized. Check filesystem permissions and WordPress configuration.');
        // This is a strong indicator of a problem. Consider throwing an exception if direct access is expected to work.
        // throw new \Exception(Language::get('Could not initialize WordPress filesystem. Check permissions.'));
    } else {
        global $wp_filesystem;
        if ($wp_filesystem instanceof \WP_Filesystem_Direct) {
            error_log('[MDS Extension Install] DIAGNOSTIC: WP_Filesystem initialized successfully using "direct" method via preliminary check.');
        } elseif (isset($wp_filesystem) && is_object($wp_filesystem) && property_exists($wp_filesystem, 'method')) {
            error_log('[MDS Extension Install] DIAGNOSTIC: WP_Filesystem initialized successfully via preliminary check using method: ' . $wp_filesystem->method);
        } else {
            error_log('[MDS Extension Install] DIAGNOSTIC: WP_Filesystem initialized via preliminary check, but method is unknown or $wp_filesystem is not as expected.');
        }
    }
    // END DIAGNOSTIC WP_Filesystem check

    // Include required WordPress files (some might have been included by WP_Filesystem check already)
    if ( ! class_exists( 'WP_Upgrader' ) ) {  require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php'; }

    require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php'; 
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    
    // Get the license key for authentication (passed in)
    $license_key = is_string($license_key) ? $license_key : '';
    
    // Set up the upgrader
    $upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() ); 
    
    // Add authentication headers
    add_filter( 'http_request_args', function( $args, $url ) use ( $download_url, $license_key ) {
        if ( strpos( $url, $download_url ) === 0 ) {
            if (!empty($license_key)) {
                $args['headers']['x-license-key'] = $license_key;
            }
            $args['sslverify'] = !Utility::is_development_environment();
        }
        return $args;
    }, 10, 2 );
    
    // DEBUG: Attempt direct wp_remote_get to see raw response
    error_log('[MDS Extension Install] Attempting direct wp_remote_get for: ' . $download_url);
    $current_request_args_filter = null;
    foreach ($GLOBALS['wp_filter']['http_request_args'] as $priority => $filters) {
        foreach ($filters as $filter_name => $filter_details) {
            if (is_array($filter_details['function']) && $filter_details['function'][0] instanceof \Closure) {
                // This is likely our closure, but we can't be 100% sure without more complex reflection.
                // For simplicity, we'll assume it's the one we want to use or that headers are already set.
                // A more robust solution might involve removing and re-adding the filter specifically.
            }
        }
    }

    $direct_get_args = [
        'timeout'   => 60, 
        'sslverify' => false, // Explicitly false, though already set by the filter
    ];
    // If we have a license key, ensure it's in headers for the direct call
    if (!empty($license_key)) {
        $direct_get_args['headers']['x-license-key'] = $license_key;
    }

    $direct_response = wp_remote_get($download_url, $direct_get_args);

    if (is_wp_error($direct_response)) {
        error_log('[MDS Extension Install] wp_remote_get ERROR: ' . $direct_response->get_error_message());
    } else {
        $direct_response_code = wp_remote_retrieve_response_code($direct_response);
        $direct_response_headers = wp_remote_retrieve_headers($direct_response);
        $direct_response_body_length = strlen(wp_remote_retrieve_body($direct_response));
        error_log('[MDS Extension Install] wp_remote_get SUCCESS. Code: ' . $direct_response_code . '. Body Length: ' . $direct_response_body_length);
        
        // Log specific headers
        $content_type_header = wp_remote_retrieve_header($direct_response, 'content-type');
        error_log('[MDS Extension Install] wp_remote_get Content-Type Header: ' . ($content_type_header ?: 'NOT FOUND'));
        $content_disposition_header = wp_remote_retrieve_header($direct_response, 'content-disposition');
        error_log('[MDS Extension Install] wp_remote_get Content-Disposition Header: ' . ($content_disposition_header ?: 'NOT FOUND'));
    }
    // END DEBUG

    // Retrieve the package data from the successful wp_remote_get response
    // $direct_response is from the wp_remote_get call a few lines above.
    $package_data = wp_remote_retrieve_body($direct_response);
    if (empty($package_data)) {
        $response_code_for_empty_body = wp_remote_retrieve_response_code($direct_response);
        throw new \Exception(Language::get('Failed to retrieve package data from download URL. Response code: ') . $response_code_for_empty_body);
    }

    // Create a temporary file for the package
    // $extension_slug is passed as an argument to this method.
    $temp_filename = wp_tempnam($extension_slug . '.zip'); // wp_tempnam creates a file in WP's temp dir
    if (!$temp_filename) {
        throw new \Exception(Language::get('Could not create temporary file for extension package.'));
    }

    // $wp_filesystem should have been initialized earlier in this method by init_wp_filesystem_direct().
    // If $wp_filesystem is not an object here, init_wp_filesystem_direct() would have thrown or returned false.
    if (empty($wp_filesystem) || !is_object($wp_filesystem)) {
        if ($wp_filesystem && $wp_filesystem->exists($temp_filename)) { // Attempt cleanup if temp file was created
             $wp_filesystem->delete($temp_filename);
        }
        throw new \Exception(Language::get('WP Filesystem not available for writing temporary package.'));
    }

    $install_package_path = $temp_filename; // Path to be used for installation
    error_log('[MDS Extension Install] Attempting to write package to temporary file: ' . $install_package_path);

    // Set the plugin slug hint for the upgrader skin to ensure correct destination directory name.
    // $extension_slug is the sanitized slug, e.g., "hello-world".
    if (isset($upgrader->skin) && is_object($upgrader->skin)) {
        $upgrader->skin->plugin_info = ['slug' => $extension_slug];
        error_log('[MDS Extension Install] Set $upgrader->skin->plugin_info slug to: ' . $extension_slug);
    } else {
        error_log('[MDS Extension Install] WARNING: $upgrader->skin is not set or not an object before setting plugin_info. This might lead to incorrect destination path.');
    }

    try {
        // Write the package data to the temporary file
        if (!$wp_filesystem->put_contents($install_package_path, $package_data)) {
            throw new \Exception(Language::get('Could not write package data to temporary file: ') . $install_package_path);
        }
        error_log('[MDS Extension Install] Package data successfully written to temporary file: ' . $install_package_path);

        // Install the extension from the local temporary file
        $result = $upgrader->install($install_package_path); // Pass local file path

        // --- Post-install directory name correction ---
        // NEW DETAILED LOGGING FOR RENAME BLOCK ENTRY AND STATE
        error_log('[MDS Extension Install] RENAME_BLOCK_CHECK: Just after $upgrader->install(). Current $result: ' . print_r($result, true));
        error_log('[MDS Extension Install] RENAME_BLOCK_CHECK: Current $upgrader->skin->result directly after install: ' . print_r($upgrader->skin->result ?? 'not set', true));

        if ($result === true || $result === 1) { // Check for successful installation
            error_log('[MDS Extension Install] RENAME_BLOCK: Entered. Result is: ' . print_r($result, true));
            error_log('[MDS Extension Install] RENAME_BLOCK: Current skin->result (inside if $result is true/1): ' . print_r($upgrader->skin->result ?? 'not set', true));
            if (isset($upgrader->skin->result['destination'], $upgrader->skin->result['destination_name']) && is_string($upgrader->skin->result['destination']) && is_string($upgrader->skin->result['destination_name'])) {
                $actual_destination_path = rtrim($upgrader->skin->result['destination'], '/'); // Full path like /wp-content/plugins/slug-random
                $actual_destination_name = $upgrader->skin->result['destination_name']; // Just slug-random

                $expected_destination_name = $extension_slug; // e.g., "hello-world"
                // Ensure $wp_filesystem is available and is an object
                if (empty($wp_filesystem) || !is_object($wp_filesystem)) {
                     error_log('[MDS Extension Install] ERROR: WP_Filesystem not available for directory rename operation.');
                     $result = new \WP_Error('filesystem_unavailable', Language::get('WP Filesystem not available for post-installation directory rename.'));
                } else {
                    $expected_destination_path = rtrim($wp_filesystem->wp_plugins_dir(), '/') . '/' . $expected_destination_name;

                    if ($actual_destination_name !== $expected_destination_name) {
                        error_log("[MDS Extension Install] Destination name mismatch. Actual: '$actual_destination_name' ('$actual_destination_path'), Expected: '$expected_destination_name' ('$expected_destination_path'). Attempting rename.");
                        
                        if ($wp_filesystem->exists($expected_destination_path)) {
                            error_log("[MDS Extension Install] WARNING: Expected destination path '$expected_destination_path' already exists. Deleting it before rename.");
                            // Attempt to delete if it's an old version or remnant. This can be risky.
                            // Consider if this should be a hard error or a configurable behavior.
                            if (!$wp_filesystem->delete($expected_destination_path, true)) {
                                error_log("[MDS Extension Install] ERROR: Failed to delete existing directory at expected path '$expected_destination_path'. Cannot proceed with rename.");
                                $result = new \WP_Error('delete_existing_failed', Language::get('Failed to delete existing directory at expected plugin path.'));
                            }
                        }
                        
                        // Proceed with move only if the previous step didn't set $result to WP_Error
                        if (!is_wp_error($result)) {
                            if ($wp_filesystem->move($actual_destination_path, $expected_destination_path, true)) { // true for overwrite
                                error_log("[MDS Extension Install] Successfully renamed '$actual_destination_path' to '$expected_destination_path'.");
                                // Update skin result to reflect the new path
                                $upgrader->skin->result['destination'] = $expected_destination_path . '/';
                                $upgrader->skin->result['destination_name'] = $expected_destination_name;
                                if (isset($upgrader->skin->result['remote_destination'])) {
                                     $upgrader->skin->result['remote_destination'] = $expected_destination_path . '/';
                                }
                            } else {
                                error_log("[MDS Extension Install] ERROR: Failed to rename '$actual_destination_path' to '$expected_destination_path'. Error details: " . print_r($wp_filesystem->errors, true));
                                $result = new \WP_Error('rename_failed', Language::get('Failed to correct plugin directory name after installation.'));
                            }
                        }
                    } else {
                         error_log("[MDS Extension Install] Destination name matches expected: '$expected_destination_name'. No rename needed.");
                    }
                }
            } else {
                error_log('[MDS Extension Install] RENAME_BLOCK: skin->result not set as expected (destination or destination_name missing/invalid). Skin result was: ' . print_r($upgrader->skin->result ?? 'not set', true));
                // Potentially treat as an error if these fields are essential for verification
                // $result = new \WP_Error('skin_result_missing', Language::get('Upgrader skin result incomplete after installation.'));
            }
        } else {
            error_log('[MDS Extension Install] RENAME_BLOCK: Not entered. Actual $result value was: ' . print_r($result, true));
        }
        // --- End Post-install directory name correction ---

    } finally {
        // Ensure the temporary file is deleted
        if ($wp_filesystem->exists($install_package_path)) {
            if ($wp_filesystem->delete($install_package_path)) {
                error_log('[MDS Extension Install] Temporary file deleted: ' . $install_package_path);
            } else {
                error_log('[MDS Extension Install] WARNING: Failed to delete temporary file: ' . $install_package_path);
            }
        }
    }

    // --- BEGIN NEW DIAGNOSTIC LOGGING ---
    error_log('[MDS Extension Install] DIAGNOSTIC: $upgrader->install() returned: ' . print_r($result, true));

    if (isset($upgrader->skin->result)) {
        error_log('[MDS Extension Install] DIAGNOSTIC: $upgrader->skin->result: ' . print_r($upgrader->skin->result, true));
    } else {
        error_log('[MDS Extension Install] DIAGNOSTIC: $upgrader->skin->result is NOT SET.');
    }

    // Safely access $upgrader->skin->plugin_info using reflection
    if (isset($upgrader->skin)) {
        $skin_object = $upgrader->skin;
        $skin_class_name = get_class($skin_object);
        try {
            $plugin_info_property = new \ReflectionProperty($skin_class_name, 'plugin_info');
            if ($plugin_info_property->isPublic()) {
                $plugin_info_value = $plugin_info_property->getValue($skin_object);
                error_log('[MDS Extension Install] DIAGNOSTIC: $upgrader->skin->plugin_info (public): ' . print_r($plugin_info_value, true));
            } else {
                $plugin_info_property->setAccessible(true);
                $plugin_info_value = $plugin_info_property->getValue($skin_object);
                error_log('[MDS Extension Install] DIAGNOSTIC: $upgrader->skin->plugin_info (made accessible): ' . print_r($plugin_info_value, true));
                $plugin_info_property->setAccessible(false); // Revert accessibility
            }
        } catch (\ReflectionException $e) {
            error_log('[MDS Extension Install] DIAGNOSTIC: ReflectionException for $upgrader->skin->plugin_info on class ' . $skin_class_name . ': ' . $e->getMessage());
        }
    } else {
        error_log('[MDS Extension Install] DIAGNOSTIC: $upgrader->skin is NOT SET.');
    }
    // --- END NEW DIAGNOSTIC LOGGING ---

    // Enhanced diagnostic logging for skin errors and messages
    $all_skin_error_messages = []; // Collect all error messages here
    $item_specific_wp_error = null;

    // Try to get item-specific WP_Error from WP_Ajax_Upgrader_Skin
    if ($upgrader->skin instanceof \WP_Ajax_Upgrader_Skin) {
        /** @var \WP_Ajax_Upgrader_Skin $ajax_skin_instance */
        $ajax_skin_instance = $upgrader->skin; // Assign to a new variable after type check

        if (method_exists($ajax_skin_instance, 'get_item_errors')) {
            $item_specific_wp_error = $ajax_skin_instance->get_item_errors(); // Call on the new variable

            if (is_wp_error($item_specific_wp_error)) { // Check if the result is a WP_Error
                /** @var \WP_Error $confirmed_wp_error */
                $confirmed_wp_error = $item_specific_wp_error;
                if (!empty($confirmed_wp_error->get_error_codes())) {
                    error_log('[MDS Extension Install] Upgrader Skin Item WP_Error Codes: ' . esc_html(implode(', ', $confirmed_wp_error->get_error_codes())));
                    foreach ($confirmed_wp_error->get_error_messages() as $message) {
                        error_log('[MDS Extension Install] Upgrader Skin Item WP_Error Message: ' . esc_html($message));
                        $all_skin_error_messages[] = $message;
                    }
                    $error_data = $confirmed_wp_error->get_error_data();
                    if (!empty($error_data)) {
                        error_log('[MDS Extension Install] Upgrader Skin Item WP_Error Data: ' . esc_html(print_r($error_data, true)));
                    }
                } else {
                    error_log('[MDS Extension Install] Upgrader Skin Item WP_Error object (from get_item_errors()) was empty (no error codes).');
                }
            } else {
                error_log('[MDS Extension Install] Call to $ajax_skin_instance->get_item_errors() did not return a WP_Error object, or it was null. Actual type/value: ' . esc_html(gettype($item_specific_wp_error)));
            }
        } else {
            error_log('[MDS Extension Install] DIAGNOSTIC: Skin IS WP_Ajax_Upgrader_Skin but method get_item_errors() does NOT exist. Skin class: ' . esc_html(get_class($ajax_skin_instance)));
        }
    } else {
        error_log('[MDS Extension Install] Skin is not an instance of WP_Ajax_Upgrader_Skin, cannot call get_item_errors(). Skin type: ' . esc_html(get_class($upgrader->skin)));
    }

    // Check public 'errors' array from WP_Upgrader_Skin (parent class)
    /** @var \WP_Upgrader_Skin $generic_skin_for_errors_prop */
    $generic_skin_for_errors_prop = $upgrader->skin;
    if (isset($generic_skin_for_errors_prop->errors) && !empty($generic_skin_for_errors_prop->errors)) {
        error_log('[MDS Extension Install] Upgrader Skin Public Errors Array (from WP_Upgrader_Skin):');
        foreach ((array) $generic_skin_for_errors_prop->errors as $error_key => $error_value) {
            $message_to_log = is_array($error_value) ? implode('; ', $error_value) : $error_value;
            error_log('  - [' . esc_html((string)$error_key) . '] ' . esc_html($message_to_log));
            if (is_string($message_to_log) && !in_array($message_to_log, $all_skin_error_messages, true)) {
                 $all_skin_error_messages[] = $message_to_log;
            }
        }
    } else {
        error_log('[MDS Extension Install] Upgrader Skin Public Errors Array (from WP_Upgrader_Skin) is empty or not set.');
    }
    
    // General messages from the skin are in the public 'messages' property
    /** @var \WP_Upgrader_Skin $generic_skin_for_messages_prop */
    $generic_skin_for_messages_prop = $upgrader->skin;
    if (isset($generic_skin_for_messages_prop->messages) && !empty($generic_skin_for_messages_prop->messages)) {
        error_log('[MDS Extension Install] Upgrader Skin General Messages: ' . esc_html(implode('; ', $generic_skin_for_messages_prop->messages)));
    } else {
        error_log('[MDS Extension Install] Upgrader Skin reported no general messages.');
    }
    // End enhanced diagnostic logging
    
    // Clear plugin cache regardless of immediate success/failure of install()
    // as it might have partially unpacked files or left traces.
    wp_clean_plugins_cache(true); // Pass true for aggressive cache clearing

    // New error handling logic
    if ($result !== true) { // Covers WP_Error, null, false, or anything else not strictly true
        $final_error_message = Language::get('Installation failed: ');

        if (is_wp_error($result)) {
            $final_error_message .= $result->get_error_message();
        } elseif (!empty($all_skin_error_messages)) {
            // $all_skin_error_messages is already populated with strings.
            $unique_messages = array_unique($all_skin_error_messages);
            $final_error_message .= implode('; ', $unique_messages);
        } else {
            // This case means $result was not true (e.g. null/false), not WP_Error, and skin messages were empty.
            $final_error_message .= Language::get('Upgrader returned an unexpected result and no specific errors were reported by the skin.');
            // Adding the raw $result for more debug info in this specific unexpected case.
            $final_error_message .= ' (Raw upgrader result: ' . print_r($result, true) . ')';
        }
        throw new \Exception($final_error_message);
    }
    // If we reach here, $result must have been strictly true.
    // Proceed with finding and activating the plugin.
    
    // Verify installation by finding the plugin file
    $plugin_file = self::find_plugin_file_by_slug($extension_slug);
    
    if ( ! $plugin_file ) {
        // Check skin for specific messages if file not found
        /** @var \WP_Upgrader_Skin $skin_for_messages_prop_fallback */
        $skin_for_messages_prop_fallback = $upgrader->skin;
        $skin_messages = []; // Default to empty array

        // Safely access messages property, checking for existence and public visibility
        if (is_object($skin_for_messages_prop_fallback) && property_exists($skin_for_messages_prop_fallback, 'messages')) {
            try {
                $reflection = new \ReflectionProperty(get_class($skin_for_messages_prop_fallback), 'messages');
                if ($reflection->isPublic()) {
                    $skin_messages = $skin_for_messages_prop_fallback->messages;
                } else {
                    error_log('[MDS Extension Install] Fallback Check: Property "messages" on skin object (' . esc_html(get_class($skin_for_messages_prop_fallback)) . ') is not public.');
                }
            } catch (\ReflectionException $e) {
                error_log('[MDS Extension Install] Fallback Check: ReflectionException for "messages" property on skin object (' . esc_html(get_class($skin_for_messages_prop_fallback)) . '): ' . esc_html($e->getMessage()));
            }
        } elseif (is_object($skin_for_messages_prop_fallback)) {
            error_log('[MDS Extension Install] Fallback Check: Property "messages" does not exist on skin object (' . esc_html(get_class($skin_for_messages_prop_fallback)) . ').');
        } else {
            error_log('[MDS Extension Install] Fallback Check: Skin object is not an object when trying to access "messages".');
        }
        // More specific error message for easier identification in logs
        $error_msg = Language::get('POST_INSTALL_ERROR: Installation seemed to succeed, but the plugin file could not be located using slug: ') . $extension_slug;
        if (!empty($skin_messages)) {
            $error_msg .= ' Upgrader messages: ' . implode('; ', $skin_messages);
        }
        error_log('[MDS Extension Install] CRITICAL FAILURE: ' . $error_msg);
        throw new \Exception($error_msg);
    }
    
    error_log('[MDS Extension Install] Plugin file found: ' . $plugin_file . '. Attempting activation.');
    // Attempt to activate the plugin
    if (apply_filters('mds_auto_activate_extension_on_install', true, $extension_id, $plugin_file)) {
        $activate_result = activate_plugin( $plugin_file );
        
        if ( is_wp_error( $activate_result ) ) {
            throw new \Exception( Language::get( 'Extension installed but failed to activate: ' ) . $activate_result->get_error_message() );
        }
    }
    
    return [
        'success' => true,
        'message' => Language::get( 'Extension installed and activated successfully.' ),
        'reload' => true
    ];
}
    public static function ajax_purchase_extension(): void {
        check_ajax_referer('mds_extensions_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => Language::get('You do not have sufficient permissions to perform this action.')], 403);
            return;
        }

        $extension_id = isset($_POST['extension_id']) ? sanitize_text_field(wp_unslash($_POST['extension_id'])) : '';
        $plan_raw     = isset($_POST['plan']) ? sanitize_text_field(wp_unslash($_POST['plan'])) : '';
        $price_raw    = isset($_POST['price_id']) ? sanitize_text_field(wp_unslash($_POST['price_id'])) : '';

        if (empty($extension_id)) {
            wp_send_json_error(['message' => Language::get('Extension ID is required.')], 400);
            return;
        }

        // Constrain plan to allowed values; optional
        $allowed_plans = ['one_time', 'monthly', 'yearly'];
        $plan = in_array($plan_raw, $allowed_plans, true) ? $plan_raw : null;
        $price_id = $price_raw !== '' ? $price_raw : null;

        try {
            // Reuse base resolution pattern from fetch_available_extensions()
            $user_configured_url = Options::get_option('extension_server_url', 'http://host.docker.internal:15346');

            $args = [
                'timeout' => 15, // short timeout
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent'   => 'MDS-WordPress-Plugin/' . MDS_VERSION,
                ],
                'sslverify' => !Utility::is_development_environment(),
            ];

            $candidates = [
                rtrim((string)$user_configured_url, '/'),
                'http://extension-server:3000',
                'http://extension-server-dev:3000',
                'http://host.docker.internal:15346',
                'http://localhost:15346',
            ];

            $response = null;
            $working_base = null;
            $errors = [];

            foreach ($candidates as $base) {
                if (empty($base)) {
                    continue;
                }
                $api_url = rtrim($base, '/') . '/api/extensions/' . urlencode($extension_id);
                $res = wp_remote_get($api_url, $args);

                if (is_wp_error($res)) {
                    $errors[] = $base . ' => ' . $res->get_error_message();
                    continue;
                }

                $code = wp_remote_retrieve_response_code($res);
                if ($code === 200) {
                    $response = $res;
                    $working_base = $base;
                    break;
                } else {
                    $errors[] = $base . ' => HTTP ' . $code;
                }
            }

            if (!$response || !$working_base) {
                wp_send_json_error(['message' => Language::get('Purchase unavailable for this extension.')], 400);
            }

            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(['message' => Language::get('Invalid response from extension server.')], 400);
            }

            // Handle common server response shapes
            $detail = [];
            if (is_array($data)) {
                if (isset($data['data']) && is_array($data['data'])) {
                    $detail = $data['data'];
                } elseif (isset($data['extension']) && is_array($data['extension'])) {
                    $detail = $data['extension'];
                } else {
                    $detail = $data;
                }
            }

            $links = [];
            if (isset($detail['purchase']) && is_array($detail['purchase']) && isset($detail['purchase']['links']) && is_array($detail['purchase']['links'])) {
                $links = $detail['purchase']['links'];
            }

            if (empty($links)) {
                wp_send_json_error(['message' => Language::get('Purchase unavailable for this extension.')], 400);
            }

            $abs = static function ($url, $base) {
                if (!is_string($url) || $url === '') {
                    return null;
                }
                if (preg_match('#^https?://#i', $url)) {
                    return $url;
                }
                $prefix = is_string($base) && $base !== '' ? rtrim($base, '/') : 'http://localhost:15346';
                return $prefix . '/' . ltrim($url, '/');
            };

            // Plan key mapping and selection priority
            $planKeyMap = [
                'one_time' => 'oneTime',
                'monthly'  => 'monthly',
                'yearly'   => 'yearly',
            ];

            $selected = null;
            $planKey = $plan && isset($planKeyMap[$plan]) ? $planKeyMap[$plan] : null;

            if ($price_id && $planKey && isset($links['options'][$plan]) && is_array($links['options'][$plan])) {
                foreach ($links['options'][$plan] as $option) {
                    if (!is_array($option)) {
                        continue;
                    }
                    if (isset($option['priceId']) && (string) $option['priceId'] === $price_id) {
                        if (!empty($option['checkout'])) {
                            $selected = $abs($option['checkout'], $working_base);
                        }
                        break;
                    }
                }
            }

            if ($planKey && !empty($links[$planKey]) && is_string($links[$planKey])) {
                $selected = $links[$planKey];
            }
            if (!$selected) {
                $selected = $links['oneTime'] ?? null;
            }
            if (!$selected) {
                $selected = $links['monthly'] ?? null;
            }
            if (!$selected) {
                $selected = $links['yearly'] ?? null;
            }
            if (!$selected) {
                $selected = $links['default'] ?? null;
            }

            if (empty($selected) || !is_string($selected)) {
                wp_send_json_error(['message' => Language::get('Purchase unavailable for this extension.')], 400);
            }

            // Normalize to absolute URL
            $absolute = $abs($selected, $working_base);
            if (!$absolute) {
                wp_send_json_error(['message' => Language::get('Purchase unavailable for this extension.')], 400);
            }

            $checkout_url = self::normalize_public_checkout_url($absolute);

            // Derive a stable site ID and a one-time claim token
            $site_id = home_url();
            $claim_token = bin2hex(random_bytes(16));

            // Compute extension slug for later claim
            $ext_slug_for_claim = '';
            if (!empty($detail['name']) && is_string($detail['name'])) {
                $ext_slug_for_claim = sanitize_title($detail['name']);
            }

            // Append return URLs so Stripe redirects back to the WP admin Extensions page
            $success_url = add_query_arg(
                [
                    'page'     => 'mds-extensions',
                    'purchase' => 'success',
                    'ext'      => $extension_id,
                    'extSlug'  => $ext_slug_for_claim,
                    'claimToken' => $claim_token,
                ],
                admin_url('admin.php')
            );
            $cancel_url = add_query_arg(
                [
                    'page'     => 'mds-extensions',
                    'purchase' => 'cancel',
                    'ext'      => $extension_id,
                ],
                admin_url('admin.php')
            );
            $checkout_url = add_query_arg(
                [
                    'successUrl' => rawurlencode($success_url),
                    'cancelUrl'  => rawurlencode($cancel_url),
                    // Pass-through metadata for Stripe checkout session
                    'siteId'     => rawurlencode($site_id),
                    'claimToken' => rawurlencode($claim_token),
                    'extensionSlug' => rawurlencode($ext_slug_for_claim),
                ],
                $checkout_url
            );

            wp_send_json_success(['checkout_url' => $checkout_url], 200);
        } catch (\Exception $e) {
            // Keep logs minimal; do not expose internal details
            wp_send_json_error(['message' => Language::get('Purchase unavailable for this extension.')], 400);
        }
    }

    /**
     * AJAX handler for activating a license.
     */
    public static function ajax_claim_license(): void {
        // Reuse nonce for the Extensions page
        check_ajax_referer( 'mds_extensions_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Permission denied.' ) ], 403 );
        }

        $extension_slug = sanitize_text_field( $_POST['extension_slug'] ?? '' );
        $claim_token    = sanitize_text_field( $_POST['claim_token'] ?? '' );
        $site_id        = sanitize_text_field( $_POST['site_id'] ?? '' );

        if ( empty( $extension_slug ) || empty( $claim_token ) || empty( $site_id ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Missing required fields.' ) ] );
        }

        try {
            // Resolve working base for server-to-server call
            $user_configured_url = Options::get_option('extension_server_url', 'http://extension-server-dev:3000');
            $candidates = [
                rtrim((string)$user_configured_url, '/'),
                'http://extension-server:3000',
                'http://extension-server-dev:3000',
                'http://host.docker.internal:15346',
                'http://localhost:15346',
            ];
            $working_base = null;
            foreach ($candidates as $base) {
                if (empty($base)) { continue; }
                $probe = rtrim($base, '/') . '/api/extensions';
                $res = wp_remote_get($probe, [ 'timeout' => 10, 'sslverify' => !Utility::is_development_environment() ]);
                if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200) {
                    $working_base = $base;
                    break;
                }
            }
            if (!$working_base) {
                throw new \Exception('Extension server unreachable.');
            }

            $claim_url = rtrim($working_base, '/') . '/api/public/licenses/claim';
            $response = wp_remote_post( $claim_url, [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'timeout' => 30,
                'sslverify' => !Utility::is_development_environment(),
                'body'    => wp_json_encode([
                    'extensionSlug' => $extension_slug,
                    'siteId'        => $site_id,
                    'claimToken'    => $claim_token,
                ]),
            ]);

            if ( is_wp_error( $response ) ) {
                throw new \Exception( $response->get_error_message() );
            }
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $json = json_decode($body, true);
            if ($code !== 200) {
                $msg = is_array($json) && isset($json['message']) ? (string)$json['message'] : 'Claim failed.';
                throw new \Exception($msg);
            }

            // Extract license payload
            $licensePayload = $json['license'] ?? ($json['data']['license'] ?? $json);
            $licenseKey = is_array($licensePayload) ? ($licensePayload['licenseKey'] ?? ($licensePayload['license_key'] ?? '')) : '';
            $fallbackExpires = is_array($licensePayload) ? ($licensePayload['expiresAt'] ?? ($licensePayload['expires_at'] ?? '')) : '';
            $licenseKey = is_string($licenseKey) ? sanitize_text_field($licenseKey) : '';
            $fallbackExpires = is_string($fallbackExpires) ? trim((string) $fallbackExpires) : '';

            if (empty($licenseKey)) {
                throw new \Exception('No license key returned from claim endpoint.');
            }

            // Store in local DB (encrypt before persisting)
            $license_manager = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
            $license = $license_manager->get_license( $extension_slug );
            $encrypted_key = \MillionDollarScript\Classes\Extension\LicenseCrypto::encryptToCompact( $licenseKey );
            if ( $license ) {
                $update_data = self::compose_license_update_payload(
                    $license,
                    [
                        'license_key' => $encrypted_key,
                        'status'      => 'active',
                    ],
                    is_array($licensePayload) ? $licensePayload : null,
                    $fallbackExpires
                );

                $license_manager->update_license( (int) $license->id, $update_data );
            } else {
                $license_manager->add_license( $extension_slug, $encrypted_key );
                $license = $license_manager->get_license( $extension_slug );
                if ($license) {
                    $update_data = self::compose_license_update_payload(
                        $license,
                        [
                            'license_key' => $encrypted_key,
                            'status'      => 'active',
                        ],
                        is_array($licensePayload) ? $licensePayload : null,
                        $fallbackExpires
                    );

                    $license_manager->update_license( (int) $license->id, $update_data );
                }
            }

            wp_send_json_success( [ 'message' => Language::get('License claimed and saved.'), 'license_key' => $licenseKey ] );
        } catch (\Exception $e) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

        public static function ajax_available_activate_license(): void {
        check_ajax_referer( 'mds_extensions_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Permission denied.' ) ] );
        }

        $extension_slug = sanitize_text_field( $_POST['extension_slug'] ?? '' );
        $license_key    = sanitize_text_field( $_POST['license_key'] ?? '' );

        if ( empty( $extension_slug ) || empty( $license_key ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Missing required fields.' ) ] );
        }

        // Attempt activation via extension server; on failure store as pending locally
        $activated = false;
        $fallback_expires = '';
        $remote_payload = null;
        try {
            $user_configured_url = \MillionDollarScript\Classes\Data\Options::get_option('extension_server_url', 'http://extension-server-dev:3000');
            $candidates = [
                rtrim((string)$user_configured_url, '/'),
                'http://extension-server:3000',
                'http://extension-server-dev:3000',
                'http://host.docker.internal:15346',
                'http://localhost:15346',
            ];
            $working_base = null;
            foreach ($candidates as $base) {
                if (empty($base)) { continue; }
                $probe = rtrim($base, '/') . '/api/public/ping';
                $res = wp_remote_get($probe, [ 'timeout' => 10, 'sslverify' => !\MillionDollarScript\Classes\System\Utility::is_development_environment() ]);
                if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200) {
                    $working_base = $base;
                    break;
                }
            }
            if ($working_base) {
                $activate_url = rtrim($working_base, '/') . '/api/public/activate';
                $body = wp_json_encode([
                    'licenseKey' => $license_key,
                    'productIdentifier' => $extension_slug,
                ]);
                $resp = wp_remote_post($activate_url, [
                    'timeout' => 20,
                    'headers' => [ 'Content-Type' => 'application/json' ],
                    'sslverify' => !\MillionDollarScript\Classes\System\Utility::is_development_environment(),
                    'body'    => $body,
                ]);
                if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                    $json = json_decode(wp_remote_retrieve_body($resp), true);
                    $activated = is_array($json) && (!empty($json['success']) || !empty($json['activated']) || !empty($json['valid']));
                    if (is_array($json)) {
                        $remote_payload = $json['license'] ?? ($json['data']['license'] ?? null);
                        if (isset($json['license']['expires_at'])) { $fallback_expires = (string) $json['license']['expires_at']; }
                        elseif (isset($json['license']['expiresAt'])) { $fallback_expires = (string) $json['license']['expiresAt']; }
                        elseif (isset($json['expires_at'])) { $fallback_expires = (string) $json['expires_at']; }
                        elseif (isset($json['expiresAt'])) { $fallback_expires = (string) $json['expiresAt']; }
                    }
                }
            }
        } catch (\Exception $e) {
            // ignore; will store pending
        }

        // Encrypt and store locally
        $license_manager = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
        $existing = $license_manager->get_license( $extension_slug );
        $enc = \MillionDollarScript\Classes\Extension\LicenseCrypto::encryptToCompact($license_key);

        $fallback_expires = is_string($fallback_expires) ? trim($fallback_expires) : '';

        if ( $existing ) {
            $update_data = self::compose_license_update_payload(
                $existing,
                [
                    'license_key' => $enc,
                    'status'      => $activated ? 'active' : 'inactive',
                ],
                is_array($remote_payload) ? $remote_payload : null,
                $fallback_expires
            );

            $license_manager->update_license( (int) $existing->id, $update_data );
        } else {
            $license_manager->add_license( $extension_slug, $enc );
            $license = $license_manager->get_license( $extension_slug );
            if ($license) {
                $update_data = self::compose_license_update_payload(
                    $license,
                    [
                        'status'      => $activated ? 'active' : 'inactive',
                        'license_key' => $enc,
                    ],
                    is_array($remote_payload) ? $remote_payload : null,
                    $fallback_expires
                );

                $license_manager->update_license( (int) $license->id, $update_data );
            }
        }

        if ($activated) {
            wp_send_json_success(['message' => Language::get('License activated and stored.')]);
        } else {
            // Queue pending validation via cron by recording in an option
            $pending = get_option('mds_pending_license_validations', []);
            if (!is_array($pending)) { $pending = []; }
            $pending[$extension_slug] = [ 'stored_at' => time(), 'attempts' => 0 ];
            update_option('mds_pending_license_validations', $pending, false);
            wp_send_json_success(['message' => Language::get('License saved (pending validation).')]);
        }
    }

    public static function ajax_get_license_plaintext(): void {
        check_ajax_referer( 'mds_extensions_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Permission denied.' ) ] );
        }
        $extension_slug = sanitize_text_field( $_POST['extension_slug'] ?? '' );
        if (!$extension_slug) {
            wp_send_json_error(['message' => Language::get('Missing extension slug.')]);
        }
        $license_manager = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
        $license = $license_manager->get_license( $extension_slug );
        if (!$license) {
            wp_send_json_error(['message' => Language::get('No license found for this extension.')]);
        }
        $pt = \MillionDollarScript\Classes\Extension\LicenseCrypto::decryptFromCompact($license->license_key);
        if ($pt === '') {
            wp_send_json_error(['message' => Language::get('Could not decrypt license key.')]);
        }
        wp_send_json_success(['license_key' => $pt]);
    }

    public static function ajax_activate_license(): void {
        $extension_slug = sanitize_text_field( $_POST['extension_slug'] ?? '' );
        check_ajax_referer( 'mds_license_nonce_' . $extension_slug, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Permission denied.' ) ] );
        }

        $license_key = sanitize_text_field( $_POST['license_key'] ?? '' );

        if ( empty( $extension_slug ) || empty( $license_key ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Missing required fields.' ) ] );
        }

        // Activate via Extension Server public endpoint, then store locally
        try {
            $activation = \MillionDollarScript\Classes\Extension\API::activate_license( $license_key, $extension_slug );
            $ok = is_array($activation) && (!empty($activation['success']) || !empty($activation['activated']) || !empty($activation['valid']));
            $fallback_expires = '';
            $remote_payload = null;
            if (is_array($activation)) {
                $remote_payload = $activation['license'] ?? ($activation['data']['license'] ?? null);
                if (isset($activation['license']['expires_at'])) { $fallback_expires = (string) $activation['license']['expires_at']; }
                elseif (isset($activation['license']['expiresAt'])) { $fallback_expires = (string) $activation['license']['expiresAt']; }
                elseif (isset($activation['expires_at'])) { $fallback_expires = (string) $activation['expires_at']; }
                elseif (isset($activation['expiresAt'])) { $fallback_expires = (string) $activation['expiresAt']; }
            }

            if (!$ok) {
                $msg = is_array($activation) && isset($activation['message']) ? (string)$activation['message'] : Language::get('Activation failed.');
                wp_send_json_error([ 'message' => $msg ]);
            }

            $license_manager = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
            $existing = $license_manager->get_license( $extension_slug );
            $enc_key = \MillionDollarScript\Classes\Extension\LicenseCrypto::encryptToCompact($license_key);

            $fallback_expires = is_string($fallback_expires) ? trim($fallback_expires) : '';

            if ( $existing ) {
                $update_data = self::compose_license_update_payload(
                    $existing,
                    [
                        'license_key' => $enc_key,
                        'status'      => 'active',
                    ],
                    is_array($remote_payload) ? $remote_payload : null,
                    $fallback_expires
                );

                $license_manager->update_license( (int) $existing->id, $update_data );
            } else {
                $license_manager->add_license( $extension_slug, $enc_key );
                $license = $license_manager->get_license( $extension_slug );
                if ($license) {
                    $update_data = self::compose_license_update_payload(
                        $license,
                        [
                            'status'      => 'active',
                            'license_key' => $enc_key,
                        ],
                        is_array($remote_payload) ? $remote_payload : null,
                        $fallback_expires
                    );

                    $license_manager->update_license( (int) $license->id, $update_data );
                }
            }

            wp_send_json_success( [ 'message' => Language::get('License activated and stored.') ] );
        } catch (\Exception $e) {
            wp_send_json_error([ 'message' => $e->getMessage() ]);
        }
    }

    /**
     * AJAX handler for deactivating a license.
     */
    public static function ajax_deactivate_license(): void {
        $extension_slug = sanitize_text_field( $_POST['extension_slug'] ?? '' );
        check_ajax_referer( 'mds_license_nonce_' . $extension_slug, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Permission denied.' ) ] );
        }

        if ( empty( $extension_slug ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Missing required fields.' ) ] );
        }

        $license_manager = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
        $license         = $license_manager->get_license( $extension_slug );

        if ( ! $license ) {
            wp_send_json_error( [ 'message' => Language::get( 'License not found.' ) ] );
        }

        // decrypt locally stored license key before calling remote API
        $plaintext_key = \MillionDollarScript\Classes\Extension\LicenseCrypto::decryptFromCompact( (string) $license->license_key );
        if ($plaintext_key === '') {
            $plaintext_key = (string) $license->license_key;
        }

        if ($plaintext_key === '') {
            wp_send_json_error( [ 'message' => Language::get( 'Stored license key is empty.' ) ] );
        }

        $result = \MillionDollarScript\Classes\Extension\API::deactivate_license( $plaintext_key, $extension_slug );

        if ( $result['success'] ) {
            $license_manager->update_license( $license->id, [ 'status' => 'inactive' ] );
            wp_send_json_success( [ 'message' => $result['message'] ] );
        } else {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }
    }

    /**
     * AJAX handler for removing a stored license entry.
     */
    public static function ajax_delete_license(): void {
        $extension_slug = sanitize_text_field( $_POST['extension_slug'] ?? '' );
        check_ajax_referer( 'mds_license_nonce_' . $extension_slug, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Permission denied.' ) ] );
        }

        if ( $extension_slug === '' ) {
            wp_send_json_error( [ 'message' => Language::get( 'Missing required fields.' ) ] );
        }

        $license_manager = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
        $license         = $license_manager->get_license( $extension_slug );

        if ( ! $license ) {
            wp_send_json_success( [ 'message' => Language::get( 'License removed.' ) ] );
        }

        $plaintext_key = \MillionDollarScript\Classes\Extension\LicenseCrypto::decryptFromCompact( (string) $license->license_key );
        if ( $plaintext_key === '' ) {
            $plaintext_key = (string) $license->license_key;
        }

        if ( $plaintext_key !== '' ) {
            try {
                \MillionDollarScript\Classes\Extension\API::deactivate_license( $plaintext_key, $extension_slug );
            } catch ( \Exception $e ) {
                // Ignore remote errors; removing the local record should still proceed.
            }
        }

        $license_manager->delete_license( (int) $license->id );
        delete_transient( 'mds_license_check_' . $extension_slug );

        wp_send_json_success( [ 'message' => Language::get( 'License removed.' ) ] );
    }
    
        /**
         * Check if an extension is licensed.
         *
         * @param string $extension_id The ID of the extension.
         * @return bool True if the extension has an active license, false otherwise.
         */
        protected static function is_extension_licensed(string $extension_slug, bool $force_refresh = false): bool {
            // If the license manager class doesn't exist, we can't check.
            if (!class_exists('\MillionDollarScript\Classes\Extension\MDS_License_Manager')) {
                return false;
            }

            try {
                $license_manager = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
                return $license_manager->is_extension_licensed($extension_slug, $force_refresh);
            } catch (\Exception $e) {
                // If the table doesn't exist or another DB error occurs, assume not licensed.
                error_log('Error checking license status for extension ' . $extension_slug . ': ' . $e->getMessage());
                return false;
            }
        }

        public static function ajax_cancel_subscription(): void {
            check_ajax_referer( 'mds_extensions_nonce', 'nonce' );
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => Language::get('Permission denied.')]);
            }
            $slug = sanitize_text_field($_POST['extension_slug'] ?? '');
            if ($slug === '') { wp_send_json_error(['message' => Language::get('Missing fields.')]); }
            $lm = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
            $lic = $lm->get_license($slug);
            if (!$lic || empty($lic->license_key)) { wp_send_json_error(['message' => Language::get('No license found for this extension.')]); }
            $key = \MillionDollarScript\Classes\Extension\LicenseCrypto::decryptFromCompact((string)$lic->license_key);
            if ($key === '') { wp_send_json_error(['message' => Language::get('Could not decrypt license key.')]); }
            $user_configured_url = Options::get_option('extension_server_url', 'http://extension-server-dev:3000');
            $candidates = [ rtrim((string)$user_configured_url, '/'), 'http://extension-server:3000', 'http://extension-server-dev:3000', 'http://host.docker.internal:15346', 'http://localhost:15346' ];
            $base = null; foreach ($candidates as $b) { if (!$b) continue; $probe = rtrim($b,'/') . '/api/public/ping'; $res = wp_remote_get($probe, [ 'timeout' => 10, 'sslverify' => !Utility::is_development_environment() ]); if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200) { $base = $b; break; } }
            if (!$base) { wp_send_json_error(['message' => Language::get('Extension server unreachable.')]); }
            $url = rtrim($base,'/') . '/api/public/subscriptions/cancel';
            $resp = wp_remote_post($url, [ 'timeout' => 20, 'sslverify' => !Utility::is_development_environment(), 'headers' => [ 'Content-Type' => 'application/json' ], 'body' => wp_json_encode(['licenseKey' => $key, 'productIdentifier' => $slug]) ]);
            if (is_wp_error($resp)) { wp_send_json_error(['message' => $resp->get_error_message()]); }
            $code = wp_remote_retrieve_response_code($resp); $body = wp_remote_retrieve_body($resp); $json = json_decode($body, true);
            if ($code !== 200 || !is_array($json)) { wp_send_json_error(['message' => Language::get('Failed to cancel subscription.')]); }
            wp_send_json_success(['message' => $json['message'] ?? Language::get('Auto-renew canceled.')]);
        }
}
