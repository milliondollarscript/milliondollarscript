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
    }

    /**
     * Registers the Extensions submenu item.
     */
    public static function menu(): void {
        $hook_suffix = add_menu_page(
            Language::get('Million Dollar Script Extensions'),
            Language::get('MDS Extensions'),
            'manage_options',
            'mds-extensions',
            [__CLASS__, 'render'],
            'dashicons-admin-plugins',
            30
        );

        // Enqueue scripts and styles only on the extensions page
        if ( $hook_suffix ) {
            add_action( "admin_print_styles-$hook_suffix", [ self::class, 'enqueue_assets' ] );
        }
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
        $license_key = Options::get_option( 'license_key', '' );
        
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
        
        ?>
        <div class="wrap" id="mds-extensions-page">
            <h1><?php echo esc_html( Language::get('Million Dollar Script Extensions') ); ?></h1>
            
            <?php if ($extension_server_error) : ?>
                <div class="notice notice-warning">
                    <p><?php echo esc_html( Language::get('Could not connect to extension server: ') . $extension_server_error ); ?></p>
                    <p><?php echo esc_html( Language::get('You can only manage installed extensions at this time.') ); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Available Extensions Section -->
            <?php if (!empty($available_extensions)) : ?>
            <div class="mds-extensions-container">
                <h2><?php echo esc_html( Language::get('Available Extensions') ); ?></h2>
                
                <?php error_log('MDS_DEBUG: render method - available_extensions before table generation: ' . print_r($available_extensions, true)); ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html( Language::get('Extension') ); ?></th>
                            <th><?php echo esc_html( Language::get('Version') ); ?></th>
                            <th><?php echo esc_html( Language::get('Type') ); ?></th>
                            <th><?php echo esc_html( Language::get('Action') ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $available_extensions as $extension ) : 
                            // $extension object now contains 'is_installed', 'is_active', and 'slug' 
                            // from fetch_available_extensions(). The old check below is no longer primary.
                        ?>
                            <tr data-extension-id="<?php echo esc_attr( $extension['id'] ); ?>" data-version="<?php echo esc_attr( $extension['version'] ); ?>"<?php if (isset($extension['is_installed']) && $extension['is_installed'] && !empty($extension['installed_plugin_file'])) { echo ' data-plugin-file="' . esc_attr($extension['installed_plugin_file']) . '"'; } ?>>
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
                                <td class="mds-action-cell">
                                <?php if ($extension['is_installed']) : ?>
                                    <?php if ($extension['is_active']) : ?>
                                        <span class="button button-secondary mds-ext-active" disabled="disabled" style="opacity: 0.7; cursor: default;">
                                            <?php echo esc_html( Language::get('Active') ); ?>
                                        </span>
                                    <?php else : ?>
                                        <button class="button button-secondary mds-activate-extension" 
                                                data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                                data-extension-slug="<?php echo esc_attr( $extension['slug'] ); ?>">
                                            <?php echo esc_html( Language::get('Activate') ); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <button class="button button-primary mds-install-extension" 
                                            data-nonce="<?php echo esc_attr( $nonce ); ?>" 
                                            data-extension-id="<?php echo esc_attr( $extension['id'] ); /* UUID from server */ ?>" 
                                            data-extension-slug="<?php echo esc_attr( $extension['slug'] ); /* Slug derived/from server */ ?>">
                                        <?php echo esc_html( Language::get('Install') ); ?>
                                    </button>
                                <?php endif; ?>
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
            
            <style>
                .mds-extensions-container {
                    margin-top: 20px;
                }
                .mds-update-available {
                    margin: 5px 0;
                }
                .mds-changelog {
                    background: #f9f9f9;
                    border-left: 4px solid #2271b1;
                    padding: 5px 10px;
                    margin: 5px 0;
                    font-size: 13px;
                }
                .mds-status-active {
                    color: #00a32a;
                    font-weight: bold;
                }
                .mds-status-inactive {
                    color: #d63638;
                }
                .mds-status-installed {
                    color: #00a32a;
                    font-weight: bold;
                }
                .mds-update-cell {
                    min-width: 200px;
                }
                .mds-action-cell {
                    min-width: 120px;
                }
                .mds-type-premium {
                    color: #d63638;
                    font-weight: bold;
                }
                .mds-type-free {
                    color: #00a32a;
                    font-weight: bold;
                }
                .mds-update-available-text {
                    color: #d63638;
                    font-size: 12px;
                    font-style: italic;
                }
                .description {
                    color: #666;
                    font-size: 13px;
                    font-style: italic;
                }
                .mds-extension-name strong {
                    display: block;
                    margin-bottom: 4px;
                }
            </style>
        </div>
        <?php
    }

    /**
     * Enqueue JS and CSS assets for the Extensions page.
     */
    public static function enqueue_assets(): void {
        // This is already called within the render method which checks if we're on the right page
        // No need for additional checks here

        $script_path = MDS_BASE_PATH . 'src/Assets/js/admin-extensions.min.js';
        $script_url = MDS_BASE_URL . 'src/Assets/js/admin-extensions.min.js';
        $style_path = MDS_BASE_PATH . 'src/Assets/css/admin-extensions.css';
        $style_url = MDS_BASE_URL . 'src/Assets/css/admin-extensions.css';

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
            $extensions_data = [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'mds_extensions_nonce' ),
                'text'     => [
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
     * Fetch available extensions from the extension server
     * 
     * @return array List of available extensions
     * @throws \Exception If the API request fails
     */
    protected static function fetch_available_extensions(): array {
        $extension_server_url = Options::get_option('extension_server_url', 'http://host.docker.internal:15346');
        $license_key = Options::get_option('license_key', '');
        
        $api_url = rtrim($extension_server_url, '/') . '/api/extensions';
        
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
        
        $response = wp_remote_get($api_url, $args);
        
        if (is_wp_error($response)) {
            throw new \Exception('Failed to connect to extension server: ' . $response->get_error_message());
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
        
        // Handle different response formats
        if (isset($data['success']) && !$data['success']) {
            throw new \Exception($data['error'] ?? 'Unknown error from extension server');
        }
        
        // Extract extensions from response (handle different response formats)
        $extensions = $data;
        if (isset($data['data'])) {
            $extensions = $data['data'];
        } elseif (isset($data['extensions'])) {
            $extensions = $data['extensions'];
        }
        
        // Transform extension data to match expected format
        $transformed_extensions = [];
        foreach ($extensions as $extension) {
            $transformed_extensions[] = [
                'id' => $extension['id'] ?? '',
                'name' => $extension['name'] ?? '',
                'version' => $extension['version'] ?? '',
                'description' => $extension['description'] ?? '',
                'isPremium' => $extension['is_premium'] ?? false,
                'file_name' => $extension['file_name'] ?? '',
                'file_path' => $extension['file_path'] ?? '',
                'created_at' => $extension['created_at'] ?? '',
                'updated_at' => $extension['updated_at'] ?? '',
                'changelog' => $extension['changelog'] ?? '',
                'requires' => $extension['requires'] ?? '',
                'requires_php' => $extension['requires_php'] ?? '',
                'tested' => $extension['tested'] ?? '',
            ];
        }
        
        // Get all installed plugins to check status
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $installed_plugins = get_plugins();
    // Get active plugins. Ensure it's an array.
    $active_plugins_option = get_option( 'active_plugins', [] );
    $active_plugins = is_array($active_plugins_option) ? $active_plugins_option : [];

    // Augment available extensions with installed/active status
    foreach ($transformed_extensions as $key => $extension) {
        $is_installed = false;
        $is_active = false;
        $installed_plugin_file = null;

        // The extension slug is consistently derived from the extension's 'name'.
    // This ensures uniformity in how slugs are determined for checking status and during installation.
    $plugin_slug_from_server = '';
    if (!empty($extension['name'])) {
        $plugin_slug_from_server = sanitize_title($extension['name']);
    } else {
        // As a last resort, generate a unique slug if the name is empty, though this is unlikely.
        $plugin_slug_from_server = 'mds_ext_' . uniqid();
    }

    // Store this definitive slug back into the extension data for consistent use
    $transformed_extensions[$key]['slug'] = $plugin_slug_from_server;

    if (!empty($plugin_slug_from_server)) {
            foreach ($installed_plugins as $plugin_file => $plugin_data) {
                $current_plugin_dir = dirname($plugin_file);
                $current_plugin_basename = basename($plugin_file, '.php');
                error_log("[MDS DEBUG] Checking: Server Slug ('{$plugin_slug_from_server}') vs Installed Plugin File ('{$plugin_file}'), Dir ('{$current_plugin_dir}'), Basename ('{$current_plugin_basename}')");

                // Check if the directory name matches the slug (for plugins in a folder)
                if ($current_plugin_dir === $plugin_slug_from_server && $current_plugin_dir !== '.') {
                    error_log("[MDS DEBUG] Match on directory: {$plugin_slug_from_server}");
                    $is_installed = true;
                    $installed_plugin_file = $plugin_file;
                    break; // Found the plugin
                }
                // Check for single-file plugins where filename (without .php) is the slug
                if ($current_plugin_basename === $plugin_slug_from_server && $current_plugin_dir === '.') {
                    error_log("[MDS DEBUG] Match on basename (single file plugin): {$plugin_slug_from_server}");
                    $is_installed = true;
                    $installed_plugin_file = $plugin_file;
                    break; // Found the plugin
                }
            }
        }

        if ($is_installed && $installed_plugin_file) {
            $is_active = in_array($installed_plugin_file, $active_plugins, true);
        }

        $transformed_extensions[$key]['is_installed'] = $is_installed;
        $transformed_extensions[$key]['is_active'] = $is_active;
    }

    error_log('MDS_DEBUG: fetch_available_extensions results: ' . print_r($transformed_extensions, true));
    return $transformed_extensions;
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
            $extension_server_url = Options::get_option('extension_server_url', 'http://host.docker.internal:15346');
            $license_key = Options::get_option('license_key', '');
            
            $api_url = rtrim($extension_server_url, '/') . '/api/extensions/' . urlencode($extension_id);
            
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
            
            $response = wp_remote_get($api_url, $args);
            
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
            
            wp_send_json_success(['extension' => $data]);
            
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
            $extension_server_url = Options::get_option('extension_server_url', 'http://dev:3000');
            $license_key = Options::get_option('license_key', '');

            error_log('[MDS Extension Install] Extension ID: ' . $extension_id);
            error_log('[MDS Extension Install] Retrieved Extension Server URL: ' . $extension_server_url);
            error_log('[MDS Extension Install] Retrieved License Key: ' . (empty($license_key) ? 'EMPTY' : $license_key));
            
            $download_url = rtrim($extension_server_url, '/') . '/api/extensions/' . urlencode($extension_id) . '/download';
            
            if (!empty($license_key)) {
                $download_url .= '?licenseKey=' . urlencode($license_key);
            }

            error_log('[MDS Extension Install] Final Download URL: ' . $download_url);
            
            $result = self::install_extension_from_url($extension_id, $extension_slug, $download_url);
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
    public static function install_extension_from_url( string $extension_id, string $extension_slug, string $download_url ): array {
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
    
    // Get the license key for authentication
    $license_key = Options::get_option('license_key', '');
    
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


}
