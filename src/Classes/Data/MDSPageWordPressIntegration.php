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

namespace MillionDollarScript\Classes\Data;

use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

/**
 * WordPress integration layer for MDS page metadata system
 */
class MDSPageWordPressIntegration {
    
    private MDSPageMetadataManager $manager;
    private static ?self $instance = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->manager = MDSPageMetadataManager::getInstance();
    }
    
    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Static initialization method for Bootstrap
     *
     * @return void
     */
    public static function initializeStatic(): void {
        $instance = self::getInstance();
        $instance->initialize();
    }
    
    /**
     * Initialize WordPress integration
     *
     * @return void
     */
    public function initialize(): void {
        // Hook into WordPress lifecycle
        register_activation_hook( MDS_BASE_FILE, [ $this, 'onPluginActivation' ] );
        register_deactivation_hook( MDS_BASE_FILE, [ $this, 'onPluginDeactivation' ] );
        
        // Initialize metadata system
        add_action( 'init', [ $this, 'initializeMetadataSystem' ] );
        
        // Add admin menu items
        add_action( 'admin_menu', [ $this, 'addAdminMenus' ] );
        
        // Add AJAX handlers
        add_action( 'wp_ajax_mds_scan_pages', [ $this, 'handleScanPagesAjax' ] );
        add_action( 'wp_ajax_mds_validate_pages', [ $this, 'handleValidatePagesAjax' ] );
        add_action( 'wp_ajax_mds_cleanup_orphaned', [ $this, 'handleCleanupOrphanedAjax' ] );
        
        // Add REST API endpoints
        add_action( 'rest_api_init', [ $this, 'registerRestEndpoints' ] );
        
        // Schedule periodic tasks
        add_action( 'wp', [ $this, 'schedulePeriodicTasks' ] );
        
        // Add post state indicators to WordPress admin
        add_filter( 'display_post_states', [ $this, 'addMDSPostStates' ], 10, 2 );
    }
    
    /**
     * Handle plugin activation
     *
     * @return void
     */
    public function onPluginActivation(): void {
        // Initialize metadata system
        $result = $this->manager->initialize();
        
        if ( is_wp_error( $result ) ) {
            wp_die( 
                sprintf( 
                    Language::get( 'Failed to initialize MDS metadata system: %s' ), 
                    $result->get_error_message() 
                ),
                Language::get( 'Plugin Activation Error' )
            );
        }
        
        // Trigger initial scan
        do_action( 'mds_plugin_activated' );
        
        // Set activation flag
        update_option( 'mds_metadata_system_activated', time() );
    }
    
    /**
     * Handle plugin deactivation
     *
     * @return void
     */
    public function onPluginDeactivation(): void {
        // Clear scheduled tasks
        wp_clear_scheduled_hook( 'mds_validate_pages' );
        wp_clear_scheduled_hook( 'mds_cleanup_orphaned_pages' );
        
        // Set deactivation flag
        update_option( 'mds_metadata_system_deactivated', time() );
    }
    
    /**
     * Initialize metadata system
     *
     * @return void
     */
    public function initializeMetadataSystem(): void {
        $this->manager->initialize();
    }
    
    /**
     * Create database tables for metadata system
     *
     * @return bool|WP_Error
     */
    public function createDatabaseTables() {
        return $this->manager->getRepository()->createTables();
    }
    
    /**
     * Add admin menu items
     *
     * @return void
     */
    public function addAdminMenus(): void {
        add_submenu_page(
            'mds-options',
            Language::get( 'Page Management' ),
            Language::get( 'Page Management' ),
            'manage_options',
            'mds-page-management',
            [ $this, 'renderPageManagementPage' ]
        );
    }
    
    /**
     * Render page management admin page
     *
     * @return void
     */
    public function renderPageManagementPage(): void {
        $statistics = $this->manager->getStatistics();
        $pages = $this->manager->getAllPages( [], 20 );
        
        include MDS_BASE_PATH . 'templates/admin/page-management.php';
    }
    
    /**
     * Handle scan pages AJAX request
     *
     * @return void
     */
    public function handleScanPagesAjax(): void {
        check_ajax_referer( 'mds_admin_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $force_rescan = !empty( $_POST['force_rescan'] );
        $results = $this->manager->scanExistingPages( $force_rescan );
        
        wp_send_json_success( $results );
    }
    
    /**
     * Handle validate pages AJAX request
     *
     * @return void
     */
    public function handleValidatePagesAjax(): void {
        check_ajax_referer( 'mds_admin_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $results = $this->manager->validatePages();
        
        wp_send_json_success( $results );
    }
    
    /**
     * Handle cleanup orphaned pages AJAX request
     *
     * @return void
     */
    public function handleCleanupOrphanedAjax(): void {
        check_ajax_referer( 'mds_admin_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $orphaned_pages = $this->manager->getAllPages( [ 'status' => 'orphaned' ] );
        $cleaned = 0;
        
        foreach ( $orphaned_pages as $metadata ) {
            $result = $this->manager->deleteMetadata( $metadata->post_id );
            if ( !is_wp_error( $result ) ) {
                $cleaned++;
            }
        }
        
        wp_send_json_success( [
            'cleaned' => $cleaned,
            'message' => sprintf( 
                Language::get( 'Cleaned up %d orphaned pages' ), 
                $cleaned 
            )
        ] );
    }
    
    /**
     * Register REST API endpoints
     *
     * @return void
     */
    public function registerRestEndpoints(): void {
        // Get page metadata
        register_rest_route( 'mds/v1', '/pages/(?P<id>\d+)/metadata', [
            'methods' => 'GET',
            'callback' => [ $this, 'getPageMetadataRest' ],
            'permission_callback' => [ $this, 'checkRestPermissions' ],
            'args' => [
                'id' => [
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    }
                ]
            ]
        ] );
        
        // Update page metadata
        register_rest_route( 'mds/v1', '/pages/(?P<id>\d+)/metadata', [
            'methods' => 'POST',
            'callback' => [ $this, 'updatePageMetadataRest' ],
            'permission_callback' => [ $this, 'checkRestPermissions' ],
            'args' => [
                'id' => [
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    }
                ]
            ]
        ] );
        
        // Get all MDS pages
        register_rest_route( 'mds/v1', '/pages', [
            'methods' => 'GET',
            'callback' => [ $this, 'getAllPagesRest' ],
            'permission_callback' => [ $this, 'checkRestPermissions' ]
        ] );
        
        // Scan pages
        register_rest_route( 'mds/v1', '/pages/scan', [
            'methods' => 'POST',
            'callback' => [ $this, 'scanPagesRest' ],
            'permission_callback' => [ $this, 'checkRestPermissions' ]
        ] );
        
        // Validate pages
        register_rest_route( 'mds/v1', '/pages/validate', [
            'methods' => 'POST',
            'callback' => [ $this, 'validatePagesRest' ],
            'permission_callback' => [ $this, 'checkRestPermissions' ]
        ] );
        
        // Get page error details
        register_rest_route( 'mds/v1', '/pages/(?P<id>\d+)/errors', [
            'methods' => 'GET',
            'callback' => [ $this, 'getPageErrorsRest' ],
            'permission_callback' => [ $this, 'checkRestPermissions' ],
            'args' => [
                'id' => [
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    }
                ]
            ]
        ] );
        
        // Fix page errors
        register_rest_route( 'mds/v1', '/pages/(?P<id>\d+)/fix', [
            'methods' => 'POST',
            'callback' => [ $this, 'fixPageErrorsRest' ],
            'permission_callback' => [ $this, 'checkRestPermissions' ],
            'args' => [
                'id' => [
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    }
                ]
            ]
        ] );
    }
    
    /**
     * Check REST API permissions
     *
     * @return bool
     */
    public function checkRestPermissions(): bool {
        return current_user_can( 'manage_options' );
    }
    
    /**
     * Get page metadata via REST API
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getPageMetadataRest( $request ) {
        $post_id = intval( $request['id'] );
        $metadata = $this->manager->getMetadata( $post_id );
        
        if ( !$metadata ) {
            return new WP_Error( 'not_found', Language::get( 'Metadata not found' ), [ 'status' => 404 ] );
        }
        
        return rest_ensure_response( $metadata->toArray() );
    }
    
    /**
     * Update page metadata via REST API
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function updatePageMetadataRest( $request ) {
        $post_id = intval( $request['id'] );
        $data = $request->get_json_params();
        
        if ( empty( $data['page_type'] ) ) {
            return new WP_Error( 'missing_data', Language::get( 'Page type is required' ), [ 'status' => 400 ] );
        }
        
        $result = $this->manager->createOrUpdateMetadata(
            $post_id,
            $data['page_type'],
            $data['creation_method'] ?? 'manual',
            $data
        );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return rest_ensure_response( $result->toArray() );
    }
    
    /**
     * Get all pages via REST API
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getAllPagesRest( $request ) {
        $filters = [];
        $limit = intval( $request->get_param( 'limit' ) ?: 20 );
        $offset = intval( $request->get_param( 'offset' ) ?: 0 );
        
        // Apply filters
        if ( $request->get_param( 'page_type' ) ) {
            $filters['page_type'] = sanitize_key( $request->get_param( 'page_type' ) );
        }
        
        if ( $request->get_param( 'status' ) ) {
            $filters['status'] = sanitize_key( $request->get_param( 'status' ) );
        }
        
        $pages = $this->manager->getAllPages( $filters, $limit, $offset );
        $pages_array = array_map( function( $metadata ) {
            return $metadata->toArray();
        }, $pages );
        
        return rest_ensure_response( $pages_array );
    }
    
    /**
     * Scan pages via REST API
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function scanPagesRest( $request ) {
        $force_rescan = $request->get_param( 'force_rescan' ) === true;
        $results = $this->manager->scanExistingPages( $force_rescan );
        
        return rest_ensure_response( $results );
    }
    
    /**
     * Validate pages via REST API
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function validatePagesRest( $request ) {
        $results = $this->manager->validatePages();
        
        return rest_ensure_response( $results );
    }
    
    /**
     * Schedule periodic tasks
     *
     * @return void
     */
    public function schedulePeriodicTasks(): void {
        // Schedule daily validation
        if ( !wp_next_scheduled( 'mds_validate_pages' ) ) {
            wp_schedule_event( time(), 'daily', 'mds_validate_pages' );
        }
        
        // Schedule weekly cleanup
        if ( !wp_next_scheduled( 'mds_cleanup_orphaned_pages' ) ) {
            wp_schedule_event( time(), 'weekly', 'mds_cleanup_orphaned_pages' );
        }
    }
    
    /**
     * Add MDS post states to WordPress admin pages list
     *
     * @param array $post_states
     * @param WP_Post $post
     * @return array
     */
    public function addMDSPostStates( array $post_states, $post ): array {
        // Only show for pages
        if ( $post->post_type !== 'page' ) {
            return $post_states;
        }
        
        // Check if this page has MDS metadata
        $metadata = $this->manager->getMetadata( $post->ID );
        
        if ( $metadata ) {
            // Get page type labels
            $page_type_labels = [
                'grid' => Language::get( 'Pixel Grid' ),
                'order' => Language::get( 'Order Page' ),
                'write-ad' => Language::get( 'Write Ad' ),
                'confirm-order' => Language::get( 'Order Confirmation' ),
                'payment' => Language::get( 'Payment' ),
                'manage' => Language::get( 'Manage Ads' ),
                'thank-you' => Language::get( 'Thank You' ),
                'list' => Language::get( 'Advertiser List' ),
                'upload' => Language::get( 'File Upload' ),
                'no-orders' => Language::get( 'No Orders' )
            ];
            
            $page_type = $metadata->page_type;
            $label = $page_type_labels[$page_type] ?? ucfirst( str_replace( '-', ' ', $page_type ) );
            
            // Add MDS indicator with page type
            $post_states['mds_page'] = sprintf( 
                '%s: %s', 
                Language::get( 'MDS' ), 
                $label 
            );
            
            // Add status indicator if not active
            if ( $metadata->status !== 'active' ) {
                $status_labels = [
                    'inactive' => Language::get( 'Inactive' ),
                    'needs_repair' => Language::get( 'Needs Repair' ),
                    'validation_failed' => Language::get( 'Validation Failed' ),
                    'missing_content' => Language::get( 'Missing Content' )
                ];
                
                $status = $metadata->status;
                $status_label = $status_labels[$status] ?? ucfirst( str_replace( '_', ' ', $status ) );
                
                $status_with_help = sprintf( 
                    '%s: %s', 
                    Language::get( 'Status' ), 
                    $status_label 
                );
                
                // Add help icon with error details if validation errors exist
                if ( !empty( $metadata->validation_errors ) ) {
                    $error_details = implode( ', ', $metadata->validation_errors );
                    $status_with_help .= sprintf(
                        ' <span class="mds-error-help dashicons dashicons-editor-help" title="%s" data-post-id="%d" style="cursor: pointer; color: #d63638;"></span>',
                        esc_attr( $error_details ),
                        $post->ID
                    );
                }
                
                $post_states['mds_status'] = $status_with_help;
            }
        }
        
        return $post_states;
    }
    
    /**
     * Get page error details via REST API
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getPageErrorsRest( WP_REST_Request $request ) {
        $page_id = intval( $request->get_param( 'id' ) );
        
        if ( ! $page_id ) {
            return new \WP_Error( 'invalid_page_id', Language::get( 'Invalid page ID' ), [ 'status' => 400 ] );
        }
        
        $metadata = $this->manager->getMetadata( $page_id );
        if ( ! $metadata ) {
            return new \WP_Error( 'page_not_found', Language::get( 'Page metadata not found' ), [ 'status' => 404 ] );
        }
        
        $post = get_post( $page_id );
        if ( ! $post ) {
            return new \WP_Error( 'post_not_found', Language::get( 'Page not found' ), [ 'status' => 404 ] );
        }
        
        return new \WP_REST_Response( [
            'success' => true,
            'data' => [
                'page_id' => $page_id,
                'page_title' => $post->post_title,
                'status' => $metadata->status,
                'validation_errors' => $metadata->validation_errors ?? [],
                'confidence_score' => $metadata->confidence_score,
                'page_type' => $metadata->page_type,
                'page_config' => $metadata->page_config,
                'last_validated' => $metadata->last_validated?->format( 'Y-m-d H:i:s' ),
                'suggested_fixes' => $this->generateSuggestedFixes( $metadata )
            ]
        ] );
    }
    
    /**
     * Fix page errors via REST API
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function fixPageErrorsRest( WP_REST_Request $request ) {
        $page_id = intval( $request->get_param( 'id' ) );
        
        if ( ! $page_id ) {
            return new \WP_Error( 'invalid_page_id', Language::get( 'Invalid page ID' ), [ 'status' => 400 ] );
        }
        
        $metadata = $this->manager->getMetadata( $page_id );
        if ( ! $metadata ) {
            return new \WP_Error( 'page_not_found', Language::get( 'Page metadata not found' ), [ 'status' => 404 ] );
        }
        
        // Re-validate the page to get current state
        $validation_result = $this->manager->validatePage( $page_id );
        
        if ( is_wp_error( $validation_result ) ) {
            return $validation_result;
        }
        
        // Apply automatic fixes
        $fixes_applied = $this->applyAutomaticFixes( $metadata );
        
        // Re-validate after fixes
        $this->manager->validatePage( $page_id );
        $updated_metadata = $this->manager->getMetadata( $page_id );
        
        return new \WP_REST_Response( [
            'success' => true,
            'data' => [
                'page_id' => $page_id,
                'fixes_applied' => $fixes_applied,
                'new_status' => $updated_metadata->status,
                'remaining_errors' => $updated_metadata->validation_errors ?? []
            ]
        ] );
    }
    
    /**
     * Generate suggested fixes for page errors
     *
     * @param MDSPageMetadata $metadata
     * @return array
     */
    private function generateSuggestedFixes( MDSPageMetadata $metadata ): array {
        $fixes = [];
        
        if ( empty( $metadata->validation_errors ) ) {
            return $fixes;
        }
        
        foreach ( $metadata->validation_errors as $error ) {
            if ( strpos( $error, 'Grid page missing grid size configuration' ) !== false ) {
                $fixes[] = [
                    'type' => 'config',
                    'title' => Language::get( 'Add Grid Size Configuration' ),
                    'description' => Language::get( 'This grid page needs a grid size configuration to function properly.' ),
                    'action' => 'add_grid_config',
                    'auto_fixable' => true
                ];
            }
            
            if ( strpos( $error, 'Order page missing payment methods configuration' ) !== false ) {
                $fixes[] = [
                    'type' => 'config',
                    'title' => Language::get( 'Add Payment Methods Configuration' ),
                    'description' => Language::get( 'This order page needs payment methods configuration.' ),
                    'action' => 'add_payment_config',
                    'auto_fixable' => true
                ];
            }
            
            if ( strpos( $error, 'No MDS content found' ) !== false ) {
                $fixes[] = [
                    'type' => 'content',
                    'title' => Language::get( 'Add MDS Content' ),
                    'description' => Language::get( 'This page needs MDS shortcode or block content to function.' ),
                    'action' => 'add_content',
                    'auto_fixable' => false
                ];
            }
        }
        
        return $fixes;
    }
    
    /**
     * Apply automatic fixes to page metadata
     *
     * @param MDSPageMetadata $metadata
     * @return array
     */
    private function applyAutomaticFixes( MDSPageMetadata $metadata ): array {
        $fixes_applied = [];
        
        if ( empty( $metadata->validation_errors ) ) {
            return $fixes_applied;
        }
        
        foreach ( $metadata->validation_errors as $error ) {
            if ( strpos( $error, 'Grid page missing grid size configuration' ) !== false ) {
                if ( empty( $metadata->page_config['grid_size'] ) ) {
                    $metadata->page_config['grid_size'] = '100x100';
                    $fixes_applied[] = 'Added default grid size configuration (100x100)';
                }
            }
            
            if ( strpos( $error, 'Order page missing payment methods configuration' ) !== false ) {
                if ( empty( $metadata->page_config['payment_methods'] ) ) {
                    $metadata->page_config['payment_methods'] = ['paypal'];
                    $fixes_applied[] = 'Added default payment methods configuration (PayPal)';
                }
            }
        }
        
        if ( ! empty( $fixes_applied ) ) {
            $metadata->touch();
            $this->manager->getRepository()->save( $metadata );
        }
        
        return $fixes_applied;
    }
    
    /**
     * Get metadata manager instance
     *
     * @return MDSPageMetadataManager
     */
    public function getManager(): MDSPageMetadataManager {
        return $this->manager;
    }
} 