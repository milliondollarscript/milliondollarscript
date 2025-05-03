<?php

namespace MillionDollarScript\Classes\Pages;

use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\System\Logs as SystemLogs;
use MillionDollarScript\Classes\System\Utility; // Added for path/url

/**
 * Handles the Million Dollar Script Logs admin submenu page.
 */
class Logs {

    /**
     * The slug for the admin page.
     */
    const ADMIN_LOGS_PAGE_SLUG = 'mds-logs';

    /**
     * Registers the Logs submenu item.
     */
    public static function menu(): void {
        $hook_suffix = add_submenu_page(
            'milliondollarscript',
            Language::get('MDS Logs'),
            Language::get('Logs'),
            'manage_options',
            'mds-logs',
            [self::class, 'render'],
            90
        );

        // Enqueue scripts and styles only on the Logs page
        if ( $hook_suffix ) {
            add_action( 'admin_print_styles-' . $hook_suffix, [ self::class, 'enqueue_assets' ] );
        }

        // Register AJAX actions
        add_action( 'wp_ajax_mds_toggle_logging', [ self::class, 'ajax_toggle_logging' ] );
        add_action( 'wp_ajax_mds_clear_log', [ self::class, 'ajax_clear_log' ] );
        add_action( 'wp_ajax_mds_fetch_log_entries', [ self::class, 'ajax_fetch_log_entries' ] );
    }

    /**
     * Renders the Logs page content and handles form submissions.
     */
    public static function render(): void {
        // Check if the user has the required capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html( Language::get('You do not have sufficient permissions to access this page.') ) );
        }

        // Display settings errors/notices
        settings_errors('mds_logs_notices');

        // Get current log status and content
        $logging_enabled = Options::get_option( 'log_enable', 'no' ) === 'yes';
        $log_file_path   = SystemLogs::get_log_file_path();

        ?>
        <div class="wrap" id="mds-logs-page">
            <h1><?php echo esc_html( Language::get('Million Dollar Script Logs') ); ?></h1>

            <h2><?php echo esc_html( Language::get('Log Settings') ); ?></h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php echo esc_html( Language::get('Enable Logging') ); ?></th>
                    <td>
                        <label class="mds-switch">
                            <input type="checkbox" id="mds-toggle-logging" <?php checked( $logging_enabled ); ?>>
                            <span class="mds-slider mds-round"></span>
                        </label>
                        <p class="description">
                            <?php echo esc_html( Language::get('Enable this to record debug information to the log file. Useful for troubleshooting.') ); ?>
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php echo esc_html( Language::get('Enable Live Update') ); ?></th>
                    <td>
                        <label class="mds-switch">
                            <input type="checkbox" id="mds-toggle-live-update">
                            <span class="mds-slider mds-round"></span>
                        </label>
                        <p class="description">
                            <?php echo esc_html( Language::get('Automatically refresh the log view below with new entries every few seconds.') ); ?>
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php echo esc_html( Language::get('Log File Path') ); ?></th>
                    <td>
                        <code><?php echo esc_html( $log_file_path ? $log_file_path : Language::get('Could not determine log path.') ); ?></code>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php echo esc_html( Language::get('Manage Log File') ); ?></th>
                    <td>
                        <button type="button" id="mds-clear-log" class="button button-secondary">
                            <?php echo esc_html( Language::get('Clear Log File') ); ?>
                        </button>
                        <p class="description">
                            <?php echo esc_html( Language::get('Deletes the current log file. A new file will be created when the next log event occurs.') ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2><?php echo esc_html( Language::get('Log Content') ); ?></h2>
            <div id="mds-log-viewer-controls">
                <!-- Pagination etc. will go here -->
                <span id="mds-log-spinner" class="spinner"></span>
            </div>
            <div id="mds-log-viewer" style="height: 400px; overflow-y: scroll; background: #f9f9f9; border: 1px solid #ccc; padding: 10px; font-family: monospace; white-space: pre-wrap;">
                <?php echo esc_html( Language::get('Loading log entries...') ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue JS and CSS assets for the Logs page.
     */
    public static function enqueue_assets(): void {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, self::ADMIN_LOGS_PAGE_SLUG ) === false ) {
            return;
        }

        $script_path = MDS_BASE_PATH . 'src/Assets/js/admin-logs.js';
        $script_url = MDS_BASE_URL . 'src/Assets/js/admin-logs.js';
        $style_path = MDS_BASE_PATH . 'src/Assets/css/admin-logs.css';
        $style_url = MDS_BASE_URL . 'src/Assets/css/admin-logs.css';

        if ( file_exists( $script_path ) ) {
            wp_enqueue_script(
                MDS_PREFIX . 'admin-logs-js',
                $script_url,
                [ 'jquery', 'wp-util' ], // wp-util includes wp.ajax
                filemtime( $script_path ),
                true
            );
            wp_localize_script( MDS_PREFIX . 'admin-logs-js', 'mdsLogsData', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'mds_log_ajax_nonce' ),
                'initial_log_state' => Options::get_option( 'log_enable', 'no' ) === 'yes', // Pass initial state
                'text'     => [
                    'confirm_clear'         => Language::get('Are you sure you want to clear the log file? This action cannot be undone.'),
                    'error_occurred'        => Language::get('An AJAX error occurred. Please check your browser console or server logs.'),
                    'error_loading'         => Language::get('Error loading logs.'),
                    'live_update_enabled'   => Language::get('Live log update enabled.'),
                    'live_update_disabled'  => Language::get('Live log update disabled.'),
                    'log_cleared'           => Language::get('Log file cleared successfully.'), // For notices
                    'log_cleared_viewer'    => Language::get('Log file has been cleared.'), // For the viewer area
                    'log_empty'             => Language::get('Log file is empty.'),
                    'log_toggled_on'        => Language::get('Logging enabled.'),
                    'log_toggled_off'       => Language::get('Logging disabled.'),
                ]
            ] );
        }

        if ( file_exists( $style_path ) ) {
            wp_enqueue_style(
                MDS_PREFIX . 'admin-logs-css',
                $style_url,
                [],
                filemtime( $style_path )
            );
        }
    }

    // --- AJAX Handlers ---

    /**
     * AJAX handler for toggling the mds_log_enable option.
     */
    public static function ajax_toggle_logging(): void {
        check_ajax_referer( 'mds_log_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get('Permission denied.') ], 403 );
        }

        $enabled = isset( $_POST['enabled'] ) && $_POST['enabled'] === 'true';
        Options::update_option( 'log_enable', $enabled );

        $message = $enabled ? Language::get('Logging enabled.') : Language::get('Logging disabled.');
        if ( Options::get_option( 'log_enable' ) === $enabled ) {
            // No change needed, still consider it a success from the user's perspective
            wp_send_json_success( [ 'message' => $enabled ? Language::get('Logging is already enabled.') : Language::get('Logging is already disabled.') ] );
        } else {
            wp_send_json_success( [ 'message' => $message ] );
        }
    }

    /**
     * AJAX handler for clearing the log file.
     */
    public static function ajax_clear_log(): void {
        check_ajax_referer( 'mds_log_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get('Permission denied.') ], 403 );
        }

        if ( SystemLogs::clear_log() ) {
            wp_send_json_success( [ 'message' => Language::get('Log file cleared successfully.') ] );
        } else {
            wp_send_json_error( [ 'message' => Language::get('Could not clear log file. Check file permissions or if the file exists.') ] );
        }
    }

    /**
     * AJAX handler for fetching log entries.
     */
    public static function ajax_fetch_log_entries(): void {
        check_ajax_referer( 'mds_log_ajax_nonce', 'nonce' );

        $last_size = isset($_POST['last_size']) ? absint($_POST['last_size']) : 0;
        $log_file_path = SystemLogs::get_log_file_path();
        $current_size = 0;
        $is_incremental = $last_size > 0;

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get('Permission denied.') ], 403 );
        }

        if ( ! $log_file_path || ! file_exists( $log_file_path ) ) {
            // File doesn't exist
            wp_send_json_success( [ 
                'content' => '', 
                'message' => Language::get('Log file is empty or does not exist.'),
                'size' => 0,
                'entries' => [], // For later
            ] );
        }

        // Clear file status cache to get accurate size
        clearstatcache( true, $log_file_path );
        $current_size = filesize( $log_file_path );

        if ( $current_size === 0 ) {
            // File exists but is empty
            wp_send_json_success( [ 
                'content' => '', 
                'message' => Language::get('Log file is empty.'),
                'size' => 0,
                'entries' => [], // For later
            ] );
        }

        if ( $last_size >= $current_size ) {
            // No new content or file shrank (log rotation?)
            // If file shrank, we should probably re-read the whole thing on the next full fetch.
            // For now, just send back empty content for incremental.
            wp_send_json_success( [ 
                'content' => '', 
                'message' => '', // No message needed here, just no new content
                'size' => $current_size, 
                'entries' => [], // For later
            ] ); 
        }

        // Read the appropriate content (full or incremental)
        $handle = fopen( $log_file_path, 'r' );
        if ( ! $handle ) {
            wp_send_json_error( [ 'message' => Language::get('Could not open log file for reading.') ] );
        }

        if ( $is_incremental ) {
            fseek( $handle, $last_size );
        }

        $raw_content = '';
        while ( ! feof( $handle ) ) {
            $raw_content .= fread( $handle, 8192 ); // Read in chunks
        }
        fclose( $handle );

        // Parse the lines
        $lines = explode( "\n", trim( $raw_content ) );
        $parsed_entries = [];
        // Regex to capture timestamp, level, and message
        // Example: [2023-10-27 15:30:00] [INFO] Log message here
        $regex = '/^\[([\d\-]+ [\d\:]+)\] \[([A-Z]+)\] (.*)$/';

        foreach ( $lines as $line ) {
            if ( empty( $line ) ) {
                continue;
            }
            if ( preg_match( $regex, $line, $matches ) ) {
                $parsed_entries[] = [
                    'timestamp' => trim( $matches[1] ),
                    'level'     => trim( $matches[2] ),
                    'message'   => trim( $matches[3] ), // Keep original message for grouping
                    'count'     => 1, // Initial count
                ];
            } else {
                // Handle lines that don't match the format (e.g., stack traces)
                // For now, add them as raw INFO level entries
                $parsed_entries[] = [
                    'timestamp' => gmdate('Y-m-d H:i:s'), // Use current time as fallback
                    'level'     => 'RAW',
                    'message'   => $line, // Keep raw line
                    'count'     => 1,
                ];
            }
        }

        if ( ! $is_incremental && count( $parsed_entries ) > 0 ) {
            // Full fetch: Consolidate entries
            $consolidated = [];
            foreach ( $parsed_entries as $entry ) {
                $key = $entry['level'] . '::' . $entry['message'];
                if ( isset( $consolidated[ $key ] ) ) {
                    $consolidated[ $key ]['count']++;
                    // Update timestamp if this one is later (should be, as we read sequentially)
                    $consolidated[ $key ]['timestamp'] = $entry['timestamp'];
                } else {
                    $consolidated[ $key ] = $entry;
                }
            }
            $entries = array_values( $consolidated ); // Re-index array
            // Optional: Sort consolidated entries by timestamp descending? For now, keep parse order.
        } else {
            // Incremental fetch: Return only newly parsed entries
            $entries = $parsed_entries;
        }

        wp_send_json_success( [ 
            'content' => '', // No longer sending raw content
            'size' => $current_size, 
            'message' => '', // Clear any previous message if sending content
            'entries' => $entries // Placeholder
        ] );
    }
}
