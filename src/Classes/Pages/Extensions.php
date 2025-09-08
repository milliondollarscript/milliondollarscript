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
     * Initializes the Extensions class.
     */
    public static function init(): void {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [self::class, 'register_ajax_handlers']);
        add_action('init', [self::class, 'init_extension_updaters']);
        // Fallback notice will be printed inline within render() for proper placement

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
            30
        );

        if ( $hook_suffix ) {
            $registered = true;
            add_action( "admin_print_styles-$hook_suffix", [ self::class, 'enqueue_assets' ] );
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
        
        // Try to get available extensions from server
        $available_extensions = [];
        $extension_server_error = null;
        
        try {
            $available_extensions = self::fetch_available_extensions();
        } catch (\Exception $e) {
            $extension_server_error = $e->getMessage();
        }
        
        // Add nonce for security
        $nonce = wp_create_nonce( 'mds_extensions_nonce' );
        
        // Build catalog map keyed by normalized Plugin Name for quick lookups
        $catalogByName = [];
        if (!empty($available_extensions) && is_array($available_extensions)) {
            $catalogByName = self::build_catalog_by_name($available_extensions);
        }
        
        ?>
        <div class="wrap mds-extensions-page" id="mds-extensions-page">
            <div class="mds-extensions-header">
                <h1><?php echo esc_html( Language::get('Million Dollar Script Extensions') ); ?></h1>
                <p class="mds-extensions-subtitle"><?php echo esc_html( Language::get('Supercharge your pixel advertising with powerful extensions') ); ?></p>
            </div>

            <!-- In-page notices anchor: All notices render below header, above content -->
            <div id="mds-extensions-notices" class="mds-extensions-notices" aria-live="polite" aria-atomic="true">
            <?php
            // Show purchase/fallback notices positioned below the header, above the Available Extensions section
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
            
            <!-- Available Extensions Section -->
            <?php if (!empty($available_extensions)) : ?>
            <div class="mds-extensions-container">
                <h2><?php echo esc_html( Language::get('Available Extensions') ); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html( Language::get('Extension') ); ?></th>
                            <th><?php echo esc_html( Language::get('Version') ); ?></th>
            <th><?php echo esc_html( Language::get('Type') ); ?></th>
                            <th><?php echo esc_html( Language::get('License') ); ?></th>
                            <th><?php echo esc_html( Language::get('Action') ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $available_extensions as $extension ) : 
                            // $extension object now contains 'is_installed', 'is_active', and 'slug' 
                            // from fetch_available_extensions(). The old check below is no longer primary.
                            $is_licensed = self::is_extension_licensed($extension['slug'] ?? '');
                        ?>
                            <tr data-extension-id="<?php echo esc_attr( $extension['id'] ); ?>" data-version="<?php echo esc_attr( $extension['version'] ); ?>" data-is-premium="<?php echo ($extension['isPremium'] ?? false) ? 'true' : 'false'; ?>" data-is-licensed="<?php echo $is_licensed ? 'true' : 'false'; ?>"<?php if (isset($extension['is_installed']) && $extension['is_installed'] && !empty($extension['installed_plugin_file'])) { echo ' data-plugin-file="' . esc_attr($extension['installed_plugin_file']) . '"'; } ?>>
                                <td class="mds-extension-name">
                                    <strong><?php echo esc_html( $extension['name'] ); ?></strong>
                                    <?php if (!empty($extension['description'])) : ?>
                                        <br><span class="description"><?php echo esc_html( wp_trim_words($extension['description'], 20) ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="mds-extension-version">
                                    <?php echo esc_html( $extension['version'] ); ?>
                                </td>
                                <td class="mds-extension-type">
                                    <?php if ($extension['isPremium'] ?? false) : ?>
                                        <span class="mds-type-premium"><?php echo esc_html( Language::get('Premium') ); ?></span>
                                    <?php else : ?>
                                        <span class="mds-type-free"><?php echo esc_html( Language::get('Free') ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="mds-license-cell">
                                    <?php if (($extension['isPremium'] ?? false)) : ?>
                                        <div class="mds-inline-license" data-extension-slug="<?php echo esc_attr($extension['slug']); ?>">
                                            <input type="password" class="regular-text mds-inline-license-key" placeholder="<?php echo esc_attr( Language::get('Enter license key') ); ?>">
                                            <button type="button" class="button mds-inline-license-eye" title="<?php echo esc_attr( Language::get('Show/Hide') ); ?>">👁</button>
                                            <button type="button" class="button button-secondary mds-inline-license-activate" data-nonce="<?php echo esc_attr($nonce); ?>"><?php echo esc_html( Language::get('Activate') ); ?></button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="mds-action-cell">
                                <?php if ($extension['is_installed']) : ?>
                                    <?php if ($extension['is_active']) : ?>
                                        <span class="button button-secondary mds-ext-active" disabled="disabled">
                                            <?php echo esc_html( Language::get('Active') ); ?>
                                        </span>
                                    <?php else : ?>
                                        <button class="button button-secondary mds-activate-extension"
                                                data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                                data-extension-slug="<?php echo esc_attr( $extension['slug'] ); ?>">
                                            <?php echo esc_html( Language::get('Activate') ); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php else :
                                    $is_premium = $extension['isPremium'] ?? false;
                                    $has_purchase_links = !empty(array_filter(array_values($extension['purchase_links'] ?? [])));
                                    $purchase_any = !empty($extension['purchase']['anyAvailable']);
                                    $can_purchase = !empty($extension['can_purchase']);
                                    $any_purchase = $has_purchase_links || $purchase_any || $can_purchase;

                                    if ($is_premium) {
                                        if ($is_licensed) {
                                            // Premium and licensed: Show Install button
                                            ?>
                                            <button class="button button-primary mds-install-extension"
                                                    data-nonce="<?php echo esc_attr($nonce); ?>"
                                                    data-extension-id="<?php echo esc_attr($extension['id']); ?>"
                                                    data-extension-slug="<?php echo esc_attr($extension['slug']); ?>">
                                                <?php echo esc_html(Language::get('Install')); ?>
                                            </button>
                                            <?php
                                        } elseif ($any_purchase) {
                                            // Premium, not licensed, with purchase availability: Show Purchase button
                                            $plan_attr = '';
                                            if (!empty($extension['purchase_links']['one_time'])) {
                                                $plan_attr = ' data-plan="one_time"';
                                            }
                                            echo '<button class="button button-primary mds-purchase-extension" data-extension-id="' . esc_attr($extension['id']) . '" data-nonce="' . esc_attr($nonce) . '"' . $plan_attr . '>' . esc_html(Language::get('Purchase')) . '</button>';
                                            if ($extension_server_error) {
                                                echo ' <a class="button" href="' . esc_url( admin_url('plugin-install.php?tab=upload') ) . '">' . esc_html( Language::get('Upload ZIP') ) . '</a>';
                                            }
                                        } else {
                                            // Premium, not licensed, no purchase availability: Unavailable
                                            ?>
                                            <button class="button button-secondary" disabled="disabled">
                                                <?php echo esc_html(Language::get('Unavailable')); ?>
                                            </button>
                                            <?php
                                        }
                                    } else {
                                        // Free extension: Show Install button
                                        ?>
                                        <button class="button button-primary mds-install-extension"
                                                data-nonce="<?php echo esc_attr($nonce); ?>"
                                                data-extension-id="<?php echo esc_attr($extension['id']); ?>"
                                                data-extension-slug="<?php echo esc_attr($extension['slug']); ?>">
                                            <?php echo esc_html(Language::get('Install')); ?>
                                        </button>
                                        <?php
                                    }
                                endif;
                                ?>
                            </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Installed Extensions Section -->
            <div class="mds-extensions-container">
                <h2><?php echo esc_html( Language::get('Installed Extensions') ); ?></h2>
                
                <?php if ( empty( $installed_extensions ) ) : ?>
                    <div class="notice notice-info">
                        <p><?php echo esc_html( Language::get('No extensions installed.') ); ?></p>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html( Language::get('Extension') ); ?></th>
                                <th><?php echo esc_html( Language::get('Version') ); ?></th>
                                <th><?php echo esc_html( Language::get('Status') ); ?></th>
                                <th><?php echo esc_html( Language::get('License') ); ?></th>
                                <th><?php echo esc_html( Language::get('Update') ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $installed_extensions as $extension ) : ?>
                                <tr data-extension-id="<?php echo esc_attr( $extension['id'] ); ?>" data-version="<?php echo esc_attr( $extension['version'] ); ?>" data-plugin-file="<?php echo esc_attr( $extension['plugin_file'] ); ?>">
                                    <td class="mds-extension-name">
                                        <strong><?php echo esc_html( $extension['name'] ); ?></strong>
                                        <?php if (!empty($extension['description'])) : ?>
                                            <br><span class="description"><?php echo esc_html( wp_trim_words($extension['description'], 20) ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="mds-extension-version">
                                        <?php echo esc_html( $extension['version'] ); ?>
                                    </td>
                                    <td class="mds-extension-status">
                                        <?php if ( $extension['active'] ) : ?>
                                            <span class="mds-status-active"><?php echo esc_html( Language::get('Active') ); ?></span>
                                        <?php else : ?>
                                            <span class="mds-status-inactive"><?php echo esc_html( Language::get('Inactive') ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="mds-license-cell">
                                        <?php
                                        // Default to hiding license UI unless catalog says this is premium
                                        $hide_license_ui = true;
                                        if (!empty($catalogByName)) {
                                            $installed_name_key = self::normalize_extension_name($extension['name'] ?? '');
                                            if ($installed_name_key !== '' && isset($catalogByName[$installed_name_key])) {
                                                $matched = $catalogByName[$installed_name_key];
                                                if (is_array($matched) && array_key_exists('isPremium', $matched)) {
                                                    // Show license UI only for premium
                                                    $hide_license_ui = ($matched['isPremium'] === false);
                                                }
                                            }
                                        }
                                        if (!$hide_license_ui) {
                                            self::render_license_form($extension);
                                        }
                                        ?>
                                    </td>
                                    <td class="mds-update-cell">
                                        <button class="button mds-check-updates"
                                                data-nonce="<?php echo esc_attr( $nonce ); ?>">
                                            <?php echo esc_html( Language::get('Check for Updates') ); ?>
                                        </button>
                                        <div class="mds-update-info"></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="mds-extension-actions">
                        <button type="button" class="button button-primary mds-check-all-updates">
                            <?php echo esc_html( Language::get('Check All for Updates') ); ?>
                        </button>
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
                $filtered[] = $item;
                continue;
            }
            // If another entry uses the same label "MDS Extensions" or "Extensions" but a different slug, drop it
            if ($slug !== 'mds-extensions' && ($title === 'mds extensions' || $title === 'extensions')) {
                continue;
            }
            $filtered[] = $item;
        }
        $submenu[$parent] = $filtered;
    }
    
    /**
     * Fetch available extensions from the extension server
     * 
     * @return array List of available extensions
     * @throws \Exception If the API request fails
     */
    protected static function fetch_available_extensions(): array {
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
                // New: pass through purchase availability and normalized links for WP UI
                'purchase'     => [
                    'anyAvailable' => (bool) ( $extension['purchase']['anyAvailable'] ?? ( $purchase_links['one_time'] || $purchase_links['monthly'] || $purchase_links['yearly'] || $purchase_links['default'] ) ),
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

        // error_log('MDS_DEBUG: fetch_available_extensions results: ' . print_r($transformed_extensions, true));
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

        $extension_id = sanitize_text_field($_POST['extension_id'] ?? '');
        
        if (empty($extension_id)) {
            wp_send_json_error(['message' => Language::get('Extension ID is required.')]);
        }

        try {
            $user_configured_url = Options::get_option('extension_server_url', 'http://extension-server-dev:3000');
            // Fetch a license key if available for premium downloads (decrypt if needed)
            $license_key = '';
            if (!empty($extension_slug)) {
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

            $abs = function($url) use ($extension_server_url) {
                if (!is_string($url) || $url === '') {
                    return null;
                }
                if (preg_match('#^https?://#i', $url)) {
                    return $url;
                }
                $base = is_string($extension_server_url) && $extension_server_url !== '' ? rtrim($extension_server_url, '/') : 'http://localhost:15346';
                return $base . '/' . ltrim($url, '/');
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
                        $license_key = (string)$lic->license_key;
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

        if (empty($extension_id)) {
            wp_send_json_error(['message' => Language::get('Extension ID is required.')], 400);
            return;
        }

        // Constrain plan to allowed values; optional
        $allowed_plans = ['one_time', 'monthly', 'yearly'];
        $plan = in_array($plan_raw, $allowed_plans, true) ? $plan_raw : null;

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

            // Plan key mapping and selection priority
            $planKeyMap = [
                'one_time' => 'oneTime',
                'monthly'  => 'monthly',
                'yearly'   => 'yearly',
            ];

            $selected = null;
            $planKey = $plan && isset($planKeyMap[$plan]) ? $planKeyMap[$plan] : null;

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
            $abs = function ($url) use ($working_base) {
                if (!is_string($url) || $url === '') {
                    return null;
                }
                if (preg_match('#^https?://#i', $url)) {
                    return $url;
                }
                $base = is_string($working_base) && $working_base !== '' ? rtrim($working_base, '/') : 'http://localhost:15346';
                return $base . '/' . ltrim($url, '/');
            };

            $absolute = $abs($selected);
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
     * Render the license form for an extension.
     *
     * @param array $extension
     */
    protected static function render_license_form( array $extension ): void {
        $license_manager = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
        $license = $license_manager->get_license( $extension['id'] );
        $nonce = wp_create_nonce( 'mds_license_nonce_' . $extension['id'] );
        ?>
        <form class="mds-license-form" data-extension-slug="<?php echo esc_attr( $extension['slug'] ?? $extension['id'] ); ?>">
            <input type="hidden" name="extension_slug" value="<?php echo esc_attr( $extension['slug'] ?? $extension['id'] ); ?>">
            <input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">

            <?php if ( $license && $license->status === 'active' ) : ?>
                <span class="mds-license-status-active"><?php echo esc_html( Language::get('Active') ); ?></span>
                <button type="button" class="button button-secondary mds-deactivate-license">
                    <?php echo esc_html( Language::get('Deactivate') ); ?>
                </button>
            <?php else : ?>
                <input type="text" name="license_key" placeholder="<?php echo esc_attr( Language::get('Enter license key') ); ?>" value="<?php echo esc_attr( $license ? $license->license_key : '' ); ?>">
                <button type="button" class="button button-primary mds-activate-license">
                    <?php echo esc_html( Language::get('Activate') ); ?>
                </button>
            <?php endif; ?>
        </form>
    <?php }

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
            $expiresAt  = is_array($licensePayload) ? ($licensePayload['expiresAt'] ?? ($licensePayload['expires_at'] ?? '')) : '';

            if (empty($licenseKey)) {
                throw new \Exception('No license key returned from claim endpoint.');
            }

            // Store in local DB
            $license_manager = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
            $license = $license_manager->get_license( $extension_slug );
            if ( $license ) {
                $license_manager->update_license( (int)$license->id, [
                    'license_key' => $licenseKey,
                    'status'      => 'active',
                    'expires_at'  => $expiresAt,
                ] );
            } else {
                $license_manager->add_license( $extension_slug, $licenseKey );
                $license = $license_manager->get_license( $extension_slug );
                if ($license) {
                    $license_manager->update_license( (int)$license->id, [
                        'status'     => 'active',
                        'expires_at' => $expiresAt,
                    ] );
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

        // Attempt validation via extension server; on failure store as pending locally
        $valid = false;
        $expires_at = null;
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
                $probe = rtrim($base, '/') . '/api/public/extensions';
                $res = wp_remote_get($probe, [ 'timeout' => 10, 'sslverify' => !\MillionDollarScript\Classes\System\Utility::is_development_environment() ]);
                if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200) {
                    $working_base = $base;
                    break;
                }
            }
            if ($working_base) {
                $validate_url = rtrim($working_base, '/') . '/api/public/validate';
                $body = wp_json_encode([
                    'licenseKey' => $license_key,
                    'productIdentifier' => $extension_slug,
                ]);
                $resp = wp_remote_post($validate_url, [
                    'timeout' => 20,
                    'headers' => [ 'Content-Type' => 'application/json' ],
                    'sslverify' => !\MillionDollarScript\Classes\System\Utility::is_development_environment(),
                    'body'    => $body,
                ]);
                if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                    $json = json_decode(wp_remote_retrieve_body($resp), true);
                    $valid = is_array($json) && (!empty($json['valid']));
                    if ($valid && isset($json['license']['expires_at'])) {
                        $expires_at = $json['license']['expires_at'];
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

        if ( $existing ) {
            $license_manager->update_license( (int)$existing->id, [
                'license_key' => $enc,
                'status'      => $valid ? 'active' : 'inactive',
                'expires_at'  => $expires_at,
            ] );
        } else {
            $license_manager->add_license( $extension_slug, $enc );
            $license = $license_manager->get_license( $extension_slug );
            if ($license) {
                $license_manager->update_license( (int)$license->id, [
                    'status'     => $valid ? 'active' : 'inactive',
                    'expires_at' => $expires_at,
                ] );
            }
        }

        if ($valid) {
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
        check_ajax_referer( 'mds_license_nonce_' . $_POST['extension_slug'], 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Permission denied.' ) ] );
        }

        $extension_slug = sanitize_text_field( $_POST['extension_slug'] );
        $license_key    = sanitize_text_field( $_POST['license_key'] );

        if ( empty( $extension_slug ) || empty( $license_key ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Missing required fields.' ) ] );
        }

        $result = \MillionDollarScript\Classes\Extension\API::activate_license( $license_key, $extension_slug );

        if ( $result['success'] ) {
            $license_manager = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
            $license = $license_manager->get_license( $extension_slug );

            if ( $license ) {
                $license_manager->update_license( $license->id, [
                    'license_key' => $license_key,
                    'status'      => 'active',
                    'expires_at'  => $result['license']['expires_at'],
                ] );
            } else {
                $license_manager->add_license( $extension_slug, $license_key );
                $license = $license_manager->get_license( $extension_slug );
                $license_manager->update_license( $license->id, [
                    'status'      => 'active',
                    'expires_at'  => $result['license']['expires_at'],
                ] );
            }

            wp_send_json_success( [ 'message' => $result['message'] ] );
        } else {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }
    }

    /**
     * AJAX handler for deactivating a license.
     */
    public static function ajax_deactivate_license(): void {
        check_ajax_referer( 'mds_license_nonce_' . $_POST['extension_slug'], 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Permission denied.' ) ] );
        }

        $extension_slug = sanitize_text_field( $_POST['extension_slug'] );

        if ( empty( $extension_slug ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Missing required fields.' ) ] );
        }

        $license_manager = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
        $license         = $license_manager->get_license( $extension_slug );

        if ( ! $license ) {
            wp_send_json_error( [ 'message' => Language::get( 'License not found.' ) ] );
        }

        $result = \MillionDollarScript\Classes\Extension\API::deactivate_license( $license->license_key, $extension_slug );

        if ( $result['success'] ) {
            $license_manager->update_license( $license->id, [ 'status' => 'inactive' ] );
            wp_send_json_success( [ 'message' => $result['message'] ] );
        } else {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }
    }
    
        /**
         * Check if an extension is licensed.
         *
         * @param string $extension_id The ID of the extension.
         * @return bool True if the extension has an active license, false otherwise.
         */
        protected static function is_extension_licensed(string $extension_slug): bool {
            // If the license manager class doesn't exist, we can't check.
            if (!class_exists('\MillionDollarScript\Classes\Extension\MDS_License_Manager')) {
                return false;
            }
    
            try {
                $license_manager = new \MillionDollarScript\Classes\Extension\MDS_License_Manager();
                $license = $license_manager->get_license($extension_slug);
    
                return $license && $license->status === 'active';
            } catch (\Exception $e) {
                // If the table doesn't exist or another DB error occurs, assume not licensed.
                error_log('Error checking license status for extension ' . $extension_slug . ': ' . $e->getMessage());
                return false;
            }
        }
}
