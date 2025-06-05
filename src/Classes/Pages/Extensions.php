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
        add_action( 'wp_ajax_mds_install_extension_update', [ self::class, 'ajax_install_extension_update' ] );
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
        $license_key = get_option( 'mds_license_key', '' );
        
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
        $extensions = [];
        
        // Get all plugins in the wp-content/plugins/mds-extensions directory
        $extensions_dir = WP_CONTENT_DIR . '/mds-extensions';
        
        if (is_dir($extensions_dir)) {
            $plugin_files = [];
            $plugins_dir = @opendir($extensions_dir);
            
            if ($plugins_dir) {
                while (($file = readdir($plugins_dir)) !== false) {
                    if ('.' === substr($file, 0, 1)) {
                        continue;
                    }
                    
                    // Look for main plugin file (plugin-name/plugin-name.php)
                    $plugin_dir = $extensions_dir . '/' . $file;
                    if (is_dir($plugin_dir) && file_exists($plugin_dir . '/' . $file . '.php')) {
                        $plugin_files[] = 'mds-extensions/' . $file . '/' . $file . '.php';
                    }
                }
                closedir($plugins_dir);
            }
            
            // Get plugin data for each extension
            foreach ($plugin_files as $plugin_file) {
                $plugin_data = get_plugin_data(WP_CONTENT_DIR . '/' . $plugin_file);
                
                if (!empty($plugin_data['Name'])) {
                    $extensions[] = [
                        'id' => $plugin_file,
                        'name' => $plugin_data['Name'],
                        'version' => $plugin_data['Version'],
                        'description' => $plugin_data['Description'],
                        'author' => $plugin_data['Author'],
                        'file' => $plugin_file,
                        'active' => is_plugin_active($plugin_file)
                    ];
                }
            }
        }
        
        // Add nonce for security
        $nonce = wp_create_nonce( 'mds_extensions_nonce' );
        
        ?>
        <div class="wrap" id="mds-extensions-page">
            <h1><?php echo esc_html( Language::get('Million Dollar Script Extensions') ); ?></h1>
            
            <div class="mds-extensions-container">
                <h2><?php echo esc_html( Language::get('Installed Extensions') ); ?></h2>
                
                <?php if ( empty( $extensions ) ) : ?>
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
                            <?php foreach ( $extensions as $extension ) : ?>
                                <tr data-extension-id="<?php echo esc_attr( $extension['id'] ); ?>" data-version="<?php echo esc_attr( $extension['version'] ); ?>">
                                    <td class="mds-extension-name">
                                        <strong><?php echo esc_html( $extension['name'] ); ?></strong>
                                    </td>
                                    <td class="mds-extension-version">
                                        <?php echo esc_html( $extension['version'] ); ?>
                                    </td>
                                    <td class="mds-extension-status">
                                        <?php if ( is_plugin_active( $extension['file'] ) ) : ?>
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
                <?php endif; ?>
                
                <div class="mds-extension-actions">
                    <button type="button" class="button button-primary mds-check-all-updates">
                        <?php echo esc_html( Language::get('Check All for Updates') ); ?>
                    </button>
                </div>
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
                }
                .mds-status-inactive {
                    color: #d63638;
                }
                .mds-update-cell {
                    min-width: 200px;
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
                'nonce'    => wp_create_nonce( 'mds_extensions_actions' ),
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

    // --- AJAX Handlers ---

    /**
     * AJAX handler for fetching all extensions.
     */
    public static function ajax_fetch_all_extensions(): void {
        // Verify nonce for security
        check_ajax_referer( 'mds_extensions_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get('Permission denied.') ], 403 );
        }

		// Send AJAX request for extensions
		$result = false;

        // If update was successful or the value was already set
        if ($result) {
            $output = "";
            wp_send_json_success(['message' => $output]);
        } else {
            wp_send_json_error(['message' => Language::get('Failed to fetch extensions.')]);
        }
    }

    /**
     * AJAX handler for fetching single extension.
     */
    public static function ajax_fetch_extension(): void {
        // Verify nonce for security
        check_ajax_referer( 'mds_extensions_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get('Permission denied.') ], 403 );
        }

		// Send AJAX request for single extension
		$result = false;

        // If update was successful or the value was already set
        if ($result) {
            $output = "";
            wp_send_json_success(['message' => $output]);
        } else {
            wp_send_json_error(['message' => Language::get('Failed to fetch extension.')]);
        }
    }

}
