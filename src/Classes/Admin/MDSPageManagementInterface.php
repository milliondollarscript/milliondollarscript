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

namespace MillionDollarScript\Classes\Admin;

use WP_List_Table;
use MillionDollarScript\Classes\Data\MDSPageMetadataManager;
use MillionDollarScript\Classes\Data\MDSPageDetectionEngine;
use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

/**
 * Comprehensive page management admin interface
 */
class MDSPageManagementInterface {
    
    private MDSPageMetadataManager $metadata_manager;
    private MDSPageDetectionEngine $detection_engine;
    private MDSPageListTable $list_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->metadata_manager = MDSPageMetadataManager::getInstance();
        $this->detection_engine = new MDSPageDetectionEngine();
        $this->initializeHooks();
    }
    
    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function initializeHooks(): void {
        // Add admin menu
        add_action( 'admin_menu', [ $this, 'addAdminMenu' ] );
        
        // Enqueue scripts and styles
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
        
        // AJAX handlers
        add_action( 'wp_ajax_mds_bulk_page_action', [ $this, 'handleBulkPageAction' ] );
        add_action( 'wp_ajax_mds_update_page_config', [ $this, 'handleUpdatePageConfig' ] );
        add_action( 'wp_ajax_mds_scan_page', [ $this, 'handleScanPage' ] );
        add_action( 'wp_ajax_mds_repair_page', [ $this, 'handleRepairPage' ] );
        add_action( 'wp_ajax_mds_get_page_details', [ $this, 'handleGetPageDetails' ] );
        add_action( 'wp_ajax_mds_export_page_data', [ $this, 'handleExportPageData' ] );
        
        // Handle form submissions
        add_action( 'admin_post_mds_manage_pages', [ $this, 'handleFormSubmission' ] );
        
        // Add screen options
        add_action( 'load-mds_page_mds-page-management', [ $this, 'addScreenOptions' ] );
    }
    
    /**
     * Add admin menu
     *
     * @return void
     */
    public function addAdminMenu(): void {
        $hook = add_submenu_page(
            'milliondollarscript',
            Language::get( 'Manage Pages' ),
            Language::get( 'Manage Pages' ),
            'manage_options',
            'mds-page-management',
            [ $this, 'renderManagePagesPage' ]
        );
        
        // Initialize list table on page load
        add_action( "load-{$hook}", [ $this, 'initializeListTable' ] );
    }
    
    /**
     * Initialize list table
     *
     * @return void
     */
    public function initializeListTable(): void {
        $this->list_table = new MDSPageListTable();
        $this->list_table->prepare_items();
    }
    
    /**
     * Add screen options
     *
     * @return void
     */
    public function addScreenOptions(): void {
        add_screen_option( 'per_page', [
            'label' => Language::get( 'Pages per page' ),
            'default' => 20,
            'option' => 'mds_pages_per_page'
        ] );
        
        // Add help tabs
        $screen = get_current_screen();
        
        $screen->add_help_tab( [
            'id' => 'mds-page-management-overview',
            'title' => Language::get( 'Overview' ),
            'content' => $this->getHelpTabContent( 'overview' )
        ] );
        
        $screen->add_help_tab( [
            'id' => 'mds-page-management-actions',
            'title' => Language::get( 'Actions' ),
            'content' => $this->getHelpTabContent( 'actions' )
        ] );
        
        $screen->add_help_tab( [
            'id' => 'mds-page-management-troubleshooting',
            'title' => Language::get( 'Troubleshooting' ),
            'content' => $this->getHelpTabContent( 'troubleshooting' )
        ] );
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook
     * @return void
     */
    public function enqueueAssets( string $hook ): void {
        if ( strpos( $hook, 'mds-page-management' ) === false ) {
            return;
        }
        
        wp_enqueue_script(
            'mds-page-management',
            MDS_BASE_URL . 'assets/js/admin/page-management.js',
            [ 'jquery', 'wp-util', 'jquery-ui-dialog' ],
            MDS_VERSION,
            true
        );
        
        wp_enqueue_style(
            'mds-page-management',
            MDS_BASE_URL . 'assets/css/admin/page-management.css',
            [ 'wp-jquery-ui-dialog' ],
            MDS_VERSION
        );
        
        wp_localize_script( 'mds-page-management', 'mds_page_management', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'mds_page_management_nonce' ),
            'auto_refresh' => false, // Disable auto-refresh by default
            'strings' => [
                'confirmBulkAction' => Language::get( 'Are you sure you want to perform this action on selected pages?' ),
                'confirmDelete' => Language::get( 'Are you sure you want to delete this page? This action cannot be undone.' ),
                'confirmRepair' => Language::get( 'This will attempt to repair the page. Continue?' ),
                'scanning' => Language::get( 'Scanning page...' ),
                'repairing' => Language::get( 'Repairing page...' ),
                'updating' => Language::get( 'Updating configuration...' ),
                'exporting' => Language::get( 'Exporting data...' ),
                'error' => Language::get( 'An error occurred' ),
                'success' => Language::get( 'Operation completed successfully' ),
                'confirm_scan_all' => Language::get( 'Are you sure you want to scan all pages? This may take a while.' ),
                'confirm_repair_all' => Language::get( 'Are you sure you want to repair all pages with issues?' )
            ]
        ] );
    }
    
    /**
     * Render manage pages admin page
     *
     * @return void
     */
    public function renderManagePagesPage(): void {
        // Handle bulk actions
        if ( isset( $_POST['action'] ) && $_POST['action'] !== '-1' ) {
            $this->processBulkAction();
        }
        
        $stats = $this->getPageStatistics();
        
        ?>
        <div class="wrap mds-manage-pages">
            <h1 class="wp-heading-inline"><?php echo esc_html( Language::get( 'Manage MDS Pages' ) ); ?></h1>
            
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-create-pages' ) ); ?>" class="page-title-action">
                <?php echo esc_html( Language::get( 'Create New Pages' ) ); ?>
            </a>
            
            <hr class="wp-header-end">
            
            <!-- Statistics Dashboard -->
            <div class="mds-page-stats">
                <div class="mds-stats-grid">
                    <div class="mds-stat-card">
                        <div class="mds-stat-number"><?php echo esc_html( $stats['total_pages'] ); ?></div>
                        <div class="mds-stat-label"><?php echo esc_html( Language::get( 'Total MDS Pages' ) ); ?></div>
                    </div>
                    
                    <div class="mds-stat-card">
                        <div class="mds-stat-number"><?php echo esc_html( $stats['active_pages'] ); ?></div>
                        <div class="mds-stat-label"><?php echo esc_html( Language::get( 'Active Pages' ) ); ?></div>
                    </div>
                    
                    <div class="mds-stat-card">
                        <div class="mds-stat-number"><?php echo esc_html( $stats['needs_attention'] ); ?></div>
                        <div class="mds-stat-label"><?php echo esc_html( Language::get( 'Needs Attention' ) ); ?></div>
                    </div>
                    
                    <div class="mds-stat-card">
                        <div class="mds-stat-number"><?php echo esc_html( $stats['auto_detected'] ); ?></div>
                        <div class="mds-stat-label"><?php echo esc_html( Language::get( 'Auto-Detected' ) ); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="mds-quick-actions">
                <h2><?php echo esc_html( Language::get( 'Quick Actions' ) ); ?></h2>
                <div class="mds-action-buttons">
                    <button type="button" id="mds-scan-all-pages" class="button button-secondary">
                        <span class="dashicons dashicons-search"></span>
                        <?php echo esc_html( Language::get( 'Scan All Pages' ) ); ?>
                    </button>
                    
                    <button type="button" id="mds-repair-all-issues" class="button button-secondary">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php echo esc_html( Language::get( 'Repair All Issues' ) ); ?>
                    </button>
                    
                    <button type="button" id="mds-export-page-data" class="button button-secondary">
                        <span class="dashicons dashicons-download"></span>
                        <?php echo esc_html( Language::get( 'Export Page Data' ) ); ?>
                    </button>
                    
                    <button type="button" id="mds-refresh-list" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span>
                        <?php echo esc_html( Language::get( 'Refresh List' ) ); ?>
                    </button>
                </div>
            </div>
            
            <!-- Filters and Search -->
            <div class="mds-page-filters">
                <form method="get" id="mds-filter-form">
                    <input type="hidden" name="page" value="mds-manage-pages">
                    
                    <div class="mds-filter-row">
                        <select name="page_type" id="mds-filter-page-type">
                            <option value=""><?php echo esc_html( Language::get( 'All Page Types' ) ); ?></option>
                            <?php foreach ( $this->getPageTypes() as $type => $label ): ?>
                                <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $_GET['page_type'] ?? '', $type ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="status" id="mds-filter-status">
                            <option value=""><?php echo esc_html( Language::get( 'All Statuses' ) ); ?></option>
                            <option value="active" <?php selected( $_GET['status'] ?? '', 'active' ); ?>><?php echo esc_html( Language::get( 'Active' ) ); ?></option>
                            <option value="inactive" <?php selected( $_GET['status'] ?? '', 'inactive' ); ?>><?php echo esc_html( Language::get( 'Inactive' ) ); ?></option>
                            <option value="needs_attention" <?php selected( $_GET['status'] ?? '', 'needs_attention' ); ?>><?php echo esc_html( Language::get( 'Needs Attention' ) ); ?></option>
                        </select>
                        
                        <select name="implementation_type" id="mds-filter-implementation">
                            <option value=""><?php echo esc_html( Language::get( 'All Implementation Types' ) ); ?></option>
                            <option value="shortcode" <?php selected( $_GET['implementation_type'] ?? '', 'shortcode' ); ?>><?php echo esc_html( Language::get( 'Shortcode' ) ); ?></option>
                            <option value="block" <?php selected( $_GET['implementation_type'] ?? '', 'block' ); ?>><?php echo esc_html( Language::get( 'Block' ) ); ?></option>
                            <option value="hybrid" <?php selected( $_GET['implementation_type'] ?? '', 'hybrid' ); ?>><?php echo esc_html( Language::get( 'Hybrid' ) ); ?></option>
                        </select>
                        
                        <input type="search" name="search" id="mds-search-pages" 
                               placeholder="<?php echo esc_attr( Language::get( 'Search pages...' ) ); ?>"
                               value="<?php echo esc_attr( $_GET['search'] ?? '' ); ?>">
                        
                        <button type="submit" class="button"><?php echo esc_html( Language::get( 'Filter' ) ); ?></button>
                        
                        <?php if ( !empty( $_GET['page_type'] ) || !empty( $_GET['status'] ) || !empty( $_GET['implementation_type'] ) || !empty( $_GET['search'] ) ): ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-manage-pages' ) ); ?>" class="button">
                                <?php echo esc_html( Language::get( 'Clear Filters' ) ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Page List Table -->
            <form method="post" id="mds-pages-form">
                <?php wp_nonce_field( 'mds_page_management_nonce', 'mds_nonce' ); ?>
                <?php $this->list_table->display(); ?>
            </form>
            
            <!-- Page Details Modal -->
            <div id="mds-page-details-modal" title="<?php echo esc_attr( Language::get( 'Page Details' ) ); ?>" style="display: none;">
                <div id="mds-page-details-content">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
            
            <!-- Configuration Modal -->
            <div id="mds-page-config-modal" title="<?php echo esc_attr( Language::get( 'Page Configuration' ) ); ?>" style="display: none;">
                <form id="mds-page-config-form">
                    <div id="mds-page-config-content">
                        <!-- Content loaded via AJAX -->
                    </div>
                    <div class="mds-modal-actions">
                        <button type="submit" class="button button-primary"><?php echo esc_html( Language::get( 'Save Configuration' ) ); ?></button>
                        <button type="button" class="button mds-modal-close"><?php echo esc_html( Language::get( 'Cancel' ) ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get page statistics
     *
     * @return array
     */
    private function getPageStatistics(): array {
        $stats = [
            'total_pages' => 0,
            'active_pages' => 0,
            'needs_attention' => 0,
            'auto_detected' => 0
        ];
        
        $pages = $this->metadata_manager->getAllPages();
        $stats['total_pages'] = count( $pages );
        
        foreach ( $pages as $metadata ) {
            if ( $metadata->status === 'active' ) {
                $stats['active_pages']++;
            }
            
            if ( in_array( $metadata->status, [ 'needs_repair', 'validation_failed', 'missing_content' ] ) ) {
                $stats['needs_attention']++;
            }
            
            if ( $metadata->creation_method === 'auto_detected' ) {
                $stats['auto_detected']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get available page types
     *
     * @return array
     */
    private function getPageTypes(): array {
        return [
            'grid' => Language::get( 'Pixel Grid' ),
            'order' => Language::get( 'Order Page' ),
            'write-ad' => Language::get( 'Write Advertisement' ),
            'confirm-order' => Language::get( 'Order Confirmation' ),
            'payment' => Language::get( 'Payment Processing' ),
            'manage' => Language::get( 'Manage Ads' ),
            'thank-you' => Language::get( 'Thank You' ),
            'list' => Language::get( 'Advertiser List' ),
            'upload' => Language::get( 'File Upload' ),
            'no-orders' => Language::get( 'No Orders' )
        ];
    }
    
    /**
     * Process bulk action
     *
     * @return void
     */
    private function processBulkAction(): void {
        if ( !wp_verify_nonce( $_POST['mds_nonce'] ?? '', 'mds_page_management_nonce' ) ) {
            wp_die( Language::get( 'Security check failed' ) );
        }
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $action = sanitize_key( $_POST['action'] );
        $page_ids = array_map( 'intval', $_POST['page_ids'] ?? [] );
        
        if ( empty( $page_ids ) ) {
            add_settings_error( 'mds_page_management', 'no_pages_selected', Language::get( 'No pages selected' ), 'error' );
            return;
        }
        
        $results = $this->executeBulkAction( $action, $page_ids );
        
        if ( $results['success'] ) {
            add_settings_error( 'mds_page_management', 'bulk_action_success', $results['message'], 'success' );
        } else {
            add_settings_error( 'mds_page_management', 'bulk_action_error', $results['message'], 'error' );
        }
    }
    
    /**
     * Execute bulk action
     *
     * @param string $action
     * @param array $page_ids
     * @return array
     */
    private function executeBulkAction( string $action, array $page_ids ): array {
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        foreach ( $page_ids as $page_id ) {
            try {
                switch ( $action ) {
                    case 'scan':
                        $result = $this->detection_engine->detectMDSPage( $page_id );
                        if ( $result['is_mds_page'] ) {
                            $this->metadata_manager->createOrUpdateMetadata(
                                $page_id,
                                $result['page_type'],
                                'bulk_scan'
                            );
                            $success_count++;
                        }
                        break;
                        
                    case 'repair':
                        $repair_result = $this->repairPage( $page_id );
                        if ( $repair_result['success'] ) {
                            $success_count++;
                        } else {
                            $error_count++;
                            $errors[] = $repair_result['message'];
                        }
                        break;
                        
                    case 'delete':
                        if ( wp_delete_post( $page_id, true ) ) {
                            $this->metadata_manager->deleteMetadata( $page_id );
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                        break;
                        
                    case 'activate':
                        $this->metadata_manager->updatePageStatus( $page_id, 'active' );
                        $success_count++;
                        break;
                        
                    case 'deactivate':
                        $this->metadata_manager->updatePageStatus( $page_id, 'inactive' );
                        $success_count++;
                        break;
                        
                    default:
                        $error_count++;
                        $errors[] = sprintf( Language::get( 'Unknown action: %s' ), $action );
                }
            } catch ( Exception $e ) {
                $error_count++;
                $errors[] = $e->getMessage();
            }
        }
        
        $message = sprintf(
            Language::get( 'Bulk action completed: %d successful, %d errors' ),
            $success_count,
            $error_count
        );
        
        if ( !empty( $errors ) ) {
            $message .= ' ' . Language::get( 'Errors:' ) . ' ' . implode( ', ', array_slice( $errors, 0, 3 ) );
            if ( count( $errors ) > 3 ) {
                $message .= sprintf( Language::get( ' and %d more...' ), count( $errors ) - 3 );
            }
        }
        
        return [
            'success' => $error_count === 0,
            'message' => $message,
            'success_count' => $success_count,
            'error_count' => $error_count,
            'errors' => $errors
        ];
    }
    
    /**
     * Repair a page
     *
     * @param int $page_id
     * @return array
     */
    private function repairPage( int $page_id ): array {
        $post = get_post( $page_id );
        if ( !$post ) {
            return [
                'success' => false,
                'message' => Language::get( 'Page not found' )
            ];
        }
        
        $metadata = $this->metadata_manager->getMetadata( $page_id );
        if ( !$metadata ) {
            return [
                'success' => false,
                'message' => Language::get( 'No metadata found for page' )
            ];
        }
        
        $repairs_made = [];
        
        // Check and repair content
        $content = $post->post_content;
        $page_type = $metadata->getPageType();
        
        // Repair missing shortcodes
        if ( !has_shortcode( $content, 'milliondollarscript' ) && $metadata->getContentType() === 'shortcode' ) {
            $shortcode = sprintf( '[milliondollarscript type="%s"]', $page_type );
            $content = $shortcode . "\n\n" . $content;
            $repairs_made[] = Language::get( 'Added missing shortcode' );
        }
        
        // Repair missing blocks
        if ( !has_blocks( $content ) && $metadata->getContentType() === 'block' ) {
            $block = sprintf( '<!-- wp:milliondollarscript/%s-block /-->', $page_type );
            $content = $block . "\n\n" . $content;
            $repairs_made[] = Language::get( 'Added missing block' );
        }
        
        // Update post if repairs were made
        if ( !empty( $repairs_made ) ) {
            wp_update_post( [
                'ID' => $page_id,
                'post_content' => $content
            ] );
        }
        
        // Update metadata status
        $this->metadata_manager->updatePageStatus( $page_id, 'active' );
        $repairs_made[] = Language::get( 'Updated page status' );
        
        return [
            'success' => true,
            'message' => implode( ', ', $repairs_made ),
            'repairs' => $repairs_made
        ];
    }
    
    /**
     * Handle bulk page action AJAX request
     *
     * @return void
     */
    public function handleBulkPageAction(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $action = sanitize_key( $_POST['action_type'] ?? '' );
        $page_ids = array_map( 'intval', $_POST['page_ids'] ?? [] );
        
        $results = $this->executeBulkAction( $action, $page_ids );
        
        wp_send_json_success( $results );
    }
    
    /**
     * Handle update page config AJAX request
     *
     * @return void
     */
    public function handleUpdatePageConfig(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $page_id = intval( $_POST['page_id'] ?? 0 );
        $config = $_POST['config'] ?? [];
        
        // Sanitize configuration
        $sanitized_config = [];
        foreach ( $config as $key => $value ) {
            $sanitized_config[sanitize_key( $key )] = sanitize_text_field( $value );
        }
        
        $result = $this->metadata_manager->updatePageConfiguration( $page_id, $sanitized_config );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        } else {
            wp_send_json_success( Language::get( 'Configuration updated successfully' ) );
        }
    }
    
    /**
     * Handle scan page AJAX request
     *
     * @return void
     */
    public function handleScanPage(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $page_id = intval( $_POST['page_id'] ?? 0 );
        
        $result = $this->detection_engine->detectMDSPage( $page_id );
        
        if ( $result['is_mds_page'] ) {
            $metadata_result = $this->metadata_manager->createOrUpdateMetadata(
                $page_id,
                $result['page_type'],
                'manual_scan',
                [
                    'confidence_score' => $result['confidence'],
                    'detected_patterns' => $result['patterns']
                ]
            );
            
            if ( is_wp_error( $metadata_result ) ) {
                wp_send_json_error( $metadata_result->get_error_message() );
            } else {
                wp_send_json_success( [
                    'message' => Language::get( 'Page scanned and metadata updated' ),
                    'result' => $result
                ] );
            }
        } else {
            wp_send_json_success( [
                'message' => Language::get( 'Page is not an MDS page' ),
                'result' => $result
            ] );
        }
    }
    
    /**
     * Handle repair page AJAX request
     *
     * @return void
     */
    public function handleRepairPage(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $page_id = intval( $_POST['page_id'] ?? 0 );
        
        $result = $this->repairPage( $page_id );
        
        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }
    
    /**
     * Handle get page details AJAX request
     *
     * @return void
     */
    public function handleGetPageDetails(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $page_id = intval( $_POST['page_id'] ?? 0 );
        
        $post = get_post( $page_id );
        $metadata = $this->metadata_manager->getMetadata( $page_id );
        
        if ( !$post ) {
            wp_send_json_error( Language::get( 'Page not found' ) );
        }
        
        $details = [
            'post' => [
                'title' => $post->post_title,
                'status' => $post->post_status,
                'url' => get_permalink( $page_id ),
                'edit_url' => get_edit_post_link( $page_id ),
                'created' => $post->post_date,
                'modified' => $post->post_modified,
                'content_length' => strlen( $post->post_content )
            ],
            'metadata' => $metadata ? $metadata->toArray() : null,
            'analysis' => $this->detection_engine->detectMDSPage( $page_id )
        ];
        
        wp_send_json_success( [
            'details' => $details,
            'html' => $this->renderPageDetailsHTML( $details )
        ] );
    }
    
    /**
     * Handle export page data AJAX request
     *
     * @return void
     */
    public function handleExportPageData(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $format = sanitize_key( $_POST['format'] ?? 'json' );
        $include_content = !empty( $_POST['include_content'] );
        
        $pages = $this->metadata_manager->getAllPages();
        $export_data = [];
        
        foreach ( $pages as $metadata ) {
            $post = get_post( $metadata->post_id );
            if ( !$post ) {
                continue;
            }
            
            $page_data = [
                'id' => $metadata->post_id,
                'title' => $post->post_title,
                'url' => get_permalink( $metadata->post_id ),
                'status' => $post->post_status,
                'type' => $metadata->page_type,
                'implementation' => $metadata->content_type,
                'created' => $post->post_date,
                'modified' => $post->post_modified
            ];
            
            if ( $include_content ) {
                $page_data['content'] = $post->post_content;
            }
            
            // Add metadata information
            $page_data['metadata'] = [
                'page_type' => $metadata->page_type,
                'content_type' => $metadata->content_type,
                'status' => $metadata->status,
                'creation_method' => $metadata->creation_method,
                'confidence_score' => $metadata->confidence_score,
                'created_at' => $metadata->created_at->format( 'Y-m-d H:i:s' ),
                'updated_at' => $metadata->updated_at->format( 'Y-m-d H:i:s' )
            ];
            
            $export_data[] = $page_data;
        }
        
        $filename = 'mds-pages-export-' . date( 'Y-m-d-H-i-s' );
        
        if ( $format === 'csv' ) {
            $this->exportAsCSV( $export_data, $filename );
        } else {
            $this->exportAsJSON( $export_data, $filename );
        }
    }
    
    /**
     * Export data as JSON
     *
     * @param array $data
     * @param string $filename
     * @return void
     */
    private function exportAsJSON( array $data, string $filename ): void {
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '.json"' );
        
        echo wp_json_encode( $data, JSON_PRETTY_PRINT );
        exit;
    }
    
    /**
     * Export data as CSV
     *
     * @param array $data
     * @param string $filename
     * @return void
     */
    private function exportAsCSV( array $data, string $filename ): void {
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );
        
        $output = fopen( 'php://output', 'w' );
        
        if ( !empty( $data ) ) {
            // Write headers
            fputcsv( $output, array_keys( $data[0] ) );
            
            // Write data
            foreach ( $data as $row ) {
                fputcsv( $output, $row );
            }
        }
        
        fclose( $output );
        exit;
    }
    
    /**
     * Render page details HTML
     *
     * @param array $details
     * @return string
     */
    private function renderPageDetailsHTML( array $details ): string {
        ob_start();
        ?>
        <div class="mds-page-details">
            <div class="mds-details-section">
                <h3><?php echo esc_html( Language::get( 'Page Information' ) ); ?></h3>
                <table class="mds-details-table">
                    <tr>
                        <td><strong><?php echo esc_html( Language::get( 'Title' ) ); ?>:</strong></td>
                        <td><?php echo esc_html( $details['post']['title'] ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html( Language::get( 'Status' ) ); ?>:</strong></td>
                        <td><?php echo esc_html( $details['post']['status'] ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html( Language::get( 'URL' ) ); ?>:</strong></td>
                        <td><a href="<?php echo esc_url( $details['post']['url'] ); ?>" target="_blank"><?php echo esc_html( $details['post']['url'] ); ?></a></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html( Language::get( 'Created' ) ); ?>:</strong></td>
                        <td><?php echo esc_html( $details['post']['created'] ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html( Language::get( 'Modified' ) ); ?>:</strong></td>
                        <td><?php echo esc_html( $details['post']['modified'] ); ?></td>
                    </tr>
                </table>
            </div>
            
            <?php if ( $details['metadata'] ): ?>
                <div class="mds-details-section">
                    <h3><?php echo esc_html( Language::get( 'MDS Metadata' ) ); ?></h3>
                    <table class="mds-details-table">
                        <tr>
                            <td><strong><?php echo esc_html( Language::get( 'Page Type' ) ); ?>:</strong></td>
                            <td><?php echo esc_html( $details['metadata']['page_type'] ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html( Language::get( 'Implementation' ) ); ?>:</strong></td>
                            <td><?php echo esc_html( $details['metadata']['content_type'] ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html( Language::get( 'Creation Method' ) ); ?>:</strong></td>
                            <td><?php echo esc_html( $details['metadata']['creation_method'] ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html( Language::get( 'Status' ) ); ?>:</strong></td>
                            <td><?php echo esc_html( $details['metadata']['status'] ); ?></td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="mds-details-section">
                <h3><?php echo esc_html( Language::get( 'Detection Analysis' ) ); ?></h3>
                <table class="mds-details-table">
                    <tr>
                        <td><strong><?php echo esc_html( Language::get( 'Is MDS Page' ) ); ?>:</strong></td>
                        <td><?php echo $details['analysis']['is_mds_page'] ? esc_html( Language::get( 'Yes' ) ) : esc_html( Language::get( 'No' ) ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html( Language::get( 'Confidence' ) ); ?>:</strong></td>
                        <td><?php echo esc_html( number_format( $details['analysis']['confidence'] * 100, 1 ) . '%' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html( Language::get( 'Detected Type' ) ); ?>:</strong></td>
                        <td><?php echo esc_html( $details['analysis']['page_type'] ?? Language::get( 'Unknown' ) ); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="mds-details-actions">
                <a href="<?php echo esc_url( $details['post']['edit_url'] ); ?>" class="button button-primary" target="_blank">
                    <?php echo esc_html( Language::get( 'Edit Page' ) ); ?>
                </a>
                <a href="<?php echo esc_url( $details['post']['url'] ); ?>" class="button button-secondary" target="_blank">
                    <?php echo esc_html( Language::get( 'View Page' ) ); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get help tab content
     *
     * @param string $tab
     * @return string
     */
    private function getHelpTabContent( string $tab ): string {
        switch ( $tab ) {
            case 'overview':
                return '<p>' . Language::get( 'This page allows you to manage all MDS pages on your site. You can view page status, perform bulk operations, and configure individual pages.' ) . '</p>';
                
            case 'actions':
                return '<p>' . Language::get( 'Available actions include scanning pages for MDS content, repairing broken pages, updating configurations, and exporting page data.' ) . '</p>';
                
            case 'troubleshooting':
                return '<p>' . Language::get( 'If pages are not working correctly, try scanning them first, then use the repair function. Check the page details for more information about any issues.' ) . '</p>';
                
            default:
                return '';
        }
    }
} 