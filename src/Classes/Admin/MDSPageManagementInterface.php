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
use MillionDollarScript\Classes\System\Logs;

defined( 'ABSPATH' ) or exit;

/**
 * Comprehensive page management admin interface
 */
class MDSPageManagementInterface {
    
    private MDSPageMetadataManager $metadata_manager;
    private MDSPageDetectionEngine $detection_engine;
    private ?MDSPageListTable $list_table = null;
    
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
        add_action( 'wp_ajax_mds_scan_single_page', [ $this, 'handleScanPage' ] );
        add_action( 'wp_ajax_mds_repair_page', [ $this, 'handleRepairPage' ] );
        add_action( 'wp_ajax_mds_activate_page', [ $this, 'handleActivatePage' ] );
        add_action( 'wp_ajax_mds_deactivate_page', [ $this, 'handleDeactivatePage' ] );
        add_action( 'wp_ajax_mds_delete_page', [ $this, 'handleDeletePage' ] );
        add_action( 'wp_ajax_mds_get_page_details', [ $this, 'handleGetPageDetails' ] );
        add_action( 'wp_ajax_mds_export_page_data', [ $this, 'handleExportPageData' ] );
        
        // Quick action AJAX handlers
        add_action( 'wp_ajax_mds_scan_all_pages', [ $this, 'handleScanAllPages' ] );
        add_action( 'wp_ajax_mds_scan_all_pages_start', [ $this, 'handleScanAllPagesStart' ] );
        add_action( 'wp_ajax_mds_scan_all_pages_batch', [ $this, 'handleScanAllPagesBatch' ] );
        add_action( 'wp_ajax_mds_scan_all_pages_status', [ $this, 'handleScanAllPagesStatus' ] );
        add_action( 'wp_ajax_mds_repair_all_pages', [ $this, 'handleRepairAllPages' ] );
        add_action( 'wp_ajax_mds_scan_errors', [ $this, 'handleScanErrors' ] );
        add_action( 'wp_ajax_mds_fix_all_errors', [ $this, 'handleFixAllErrors' ] );
        add_action( 'wp_ajax_mds_get_page_stats', [ $this, 'handleGetPageStats' ] );
        add_action( 'wp_ajax_mds_scan_page_errors', [ $this, 'handleScanPageErrors' ] );
        add_action( 'wp_ajax_mds_fix_single_page_error', [ $this, 'handleFixSinglePageError' ] );
        add_action( 'wp_ajax_mds_get_page_config', [ $this, 'handleGetPageConfig' ] );
        add_action( 'wp_ajax_mds_save_page_config', [ $this, 'handleSavePageConfig' ] );
        add_action( 'wp_ajax_mds_reset_page_statuses', [ $this, 'handleResetPageStatuses' ] );
        add_action( 'wp_ajax_mds_validate_all_pages', [ $this, 'handleValidateAllPages' ] );
        
        // Handle form submissions
        add_action( 'admin_post_mds_manage_pages', [ $this, 'handleFormSubmission' ] );
        
        // Add diagnostic AJAX handler
        add_action( 'wp_ajax_mds_diagnose_metadata_system', [ $this, 'handleDiagnoseMetadataSystem' ] );
        
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
        try {
            $this->list_table = new MDSPageListTable();
            $this->list_table->prepare_items();
        } catch ( \Exception $e ) {
            Logs::log( 'list_table_init_error', 'Failed to initialize list table', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ] );
            $this->list_table = null;
        }
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
            MDS_BASE_URL . 'src/Assets/js/admin/page-management.min.js',
            [ 'jquery', 'wp-util', 'jquery-ui-dialog' ],
            filemtime( MDS_BASE_PATH . 'src/Assets/js/admin/page-management.min.js' ),
            true
        );
        
        wp_enqueue_script(
            'mds-error-resolution',
            MDS_BASE_URL . 'src/Assets/js/admin/error-resolution.min.js',
            [ 'mds-page-management' ],
            filemtime( MDS_BASE_PATH . 'src/Assets/js/admin/error-resolution.min.js' ),
            true
        );
        
        wp_enqueue_style(
            'mds-page-management',
            MDS_BASE_URL . 'src/Assets/css/admin/page-management.css',
            [ 'wp-jquery-ui-dialog' ],
            filemtime( MDS_BASE_PATH . 'src/Assets/css/admin/page-management.css' ),
        );
        
        wp_localize_script( 'mds-page-management', 'mdsPageManagement', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'mds_page_management_nonce' ),
            'autoRefresh' => false,
            'strings' => [
                'close' => Language::get( 'Close' ),
                'ok' => Language::get( 'OK' ),
                'details' => Language::get( 'Details' ),
                'loading' => Language::get( 'Loading...' ),
                'processing' => Language::get( 'Processing...' ),
                'no_action_selected' => Language::get( 'No Action Selected' ),
                'please_select_action' => Language::get( 'Please select an action to perform.' ),
                'no_items_selected' => Language::get( 'No Items Selected' ),
                'please_select_items' => Language::get( 'Please select one or more items to perform this action on.' ),
                'confirm_delete_single' => Language::get( 'Are you sure you want to delete this page? This action cannot be undone.' ),
                'confirm_delete_multiple' => Language::get( 'Are you sure you want to delete %d pages? This action cannot be undone.' ),
                'processing_bulk_action' => Language::get( 'Processing Bulk Action' ),
                'scanning_pages' => Language::get( 'Scanning pages' ),
                'repairing_pages' => Language::get( 'Repairing pages' ),
                'deleting_pages' => Language::get( 'Deleting pages' ),
                'activating_pages' => Language::get( 'Activating pages' ),
                'deactivating_pages' => Language::get( 'Deactivating pages' ),
                'bulk_action_completed' => Language::get( 'Bulk Action Completed' ),
                'bulk_action_completed_with_errors' => Language::get( 'Bulk Action Completed with Errors' ),
                'bulk_action_failed' => Language::get( 'Bulk Action Failed' ),
                'unknown_error' => Language::get( 'An unknown error occurred.' ),
                'ajax_error' => Language::get( 'AJAX Error' ),
                'page_details' => Language::get( 'Page Details' ),
                'failed_to_load' => Language::get( 'Failed to load content.' ),
                'scan_completed' => Language::get( 'Scan Completed' ),
                'scan_failed' => Language::get( 'Scan Failed' ),
                'scan_request_failed' => Language::get( 'Failed to send scan request.' ),
                'repair_completed' => Language::get( 'Repair Completed' ),
                'repair_failed' => Language::get( 'Repair Failed' ),
                'repair_request_failed' => Language::get( 'Failed to send repair request.' ),
                'page_configuration' => Language::get( 'Page Configuration' ),
                'configuration_saved' => Language::get( 'Configuration Saved' ),
                'configuration_failed' => Language::get( 'Configuration Failed' ),
                'configuration_request_failed' => Language::get( 'Failed to send configuration request.' ),
                'scanning_errors' => Language::get( 'Scanning for Errors' ),
                'analyzing_page_errors' => Language::get( 'Analyzing page for errors...' ),
                'page_error_analysis' => Language::get( 'Page Error Analysis' ),
                'error_scan_failed' => Language::get( 'Error Scan Failed' ),
                'error_scan_request_failed' => Language::get( 'Failed to send error scan request.' ),
                'confirm_delete' => Language::get( 'Confirm Delete' ),
                'cancel' => Language::get( 'Cancel' ),
                'delete' => Language::get( 'Delete' ),
                'action_completed' => Language::get( 'Action Completed' ),
                'action_failed' => Language::get( 'Action Failed' )
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
                    
                    <button type="button" id="mds-bulk-update-implementations" class="button button-secondary">
                        <span class="dashicons dashicons-editor-code"></span>
                        <?php echo esc_html( Language::get( 'Bulk Update Implementations' ) ); ?>
                    </button>
                    
                    <button type="button" id="mds-validate-all-pages" class="button button-secondary">
                        <span class="dashicons dashicons-yes"></span>
                        <?php echo esc_html( Language::get( 'Validate All Pages' ) ); ?>
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
                <?php if ( $this->list_table ): ?>
                    <?php $this->list_table->display(); ?>
                <?php else: ?>
                    <div class="notice notice-error">
                        <p><?php echo esc_html( Language::get( 'List table initialization failed. Please refresh the page.' ) ); ?></p>
                    </div>
                <?php endif; ?>
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
            
            <!-- Error Resolution Section -->
            <div class="mds-error-resolution" style="margin-top: 30px;">
                <h2><?php echo esc_html( Language::get( 'Error Resolution' ) ); ?></h2>
                
                <div class="mds-error-controls">
                    <button type="button" class="button button-secondary" id="mds-scan-errors">
                        <span class="dashicons dashicons-search"></span>
                        <?php echo esc_html( Language::get( 'Scan for Page Errors' ) ); ?>
                    </button>
                    
                    <button type="button" id="mds-repair-all-issues" class="button button-secondary">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php echo esc_html( Language::get( 'Repair All Issues' ) ); ?>
                    </button>
                    
                    <button type="button" class="button button-primary" id="mds-fix-all-errors" style="display: none;">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php echo esc_html( Language::get( 'Fix All Auto-Fixable Errors' ) ); ?>
                    </button>
                    
                    <button type="button" class="button button-secondary" id="mds-reset-page-statuses">
                        <span class="dashicons dashicons-update"></span>
                        <?php echo esc_html( Language::get( 'Reset Page Statuses' ) ); ?>
                    </button>
                    
                    <button type="button" id="mds-export-page-data" class="button button-secondary">
                        <span class="dashicons dashicons-download"></span>
                        <?php echo esc_html( Language::get( 'Export Page Data' ) ); ?>
                    </button>
                </div>
                
                <div id="mds-error-results" style="display: none; margin-top: 20px;">
                    <div class="mds-error-list"></div>
                </div>
                
                <div id="mds-error-progress" style="display: none; margin-top: 15px;">
                    <!-- Progress now handled by modal system -->
                </div>
            </div>
        </div>
        
        <!-- Include error styles and animations -->
        <style>
        .mds-error-resolution {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .mds-error-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .mds-error-controls .button .dashicons {
            vertical-align: middle;
            margin-top: -2px;
            line-height: 1;
        }
        
        .mds-error-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            background: #fafafa;
        }
        
        .mds-error-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .mds-error-item-content {
            flex: 1;
        }
        
        .mds-error-item-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .mds-error-item-details {
            color: #666;
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .mds-error-item-meta {
            font-size: 12px;
            color: #888;
        }
        
        .mds-error-item-meta span {
            margin-right: 15px;
        }
        
        .mds-error-item-actions {
            display: flex;
            gap: 10px;
        }
        
        .mds-error-item.auto-fixable {
            border-left: 4px solid #46b450;
        }
        
        .mds-error-item.manual-fix {
            border-left: 4px solid #ffb900;
        }
        
        .mds-error-item.critical {
            border-left: 4px solid #dc3232;
        }
        
        .mds-no-errors {
            text-align: center;
            padding: 40px;
            color: #46b450;
        }
        
        .mds-notice {
            background: #fff;
            border-left: 4px solid #fff;
            box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
            margin: 5px 15px 2px;
            padding: 1px 12px;
        }
        
        .mds-notice.mds-notice-info {
            border-left-color: #00a0d2;
        }
        
        .mds-notice p {
            margin: 0.5em 0;
            padding: 2px;
        }
        
        @keyframes rotation {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(359deg);
            }
        }
        
        /* Status help link styles */
        .mds-status-help-link {
            text-decoration: none;
            color: #666;
            margin-left: 5px;
        }
        
        .mds-status-help-link:hover {
            color: #0073aa;
        }
        
        .mds-status-help-link .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
            vertical-align: middle;
        }
        
        /* Page error help modal styles */
        .mds-page-error-help h3 {
            margin-top: 0;
            color: #333;
        }
        
        .mds-page-errors-list {
            max-height: 400px;
            overflow-y: auto;
            margin: 15px 0;
        }
        
        .mds-page-error-item {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
        }
        
        .mds-page-error-item.auto-fixable {
            border-left: 4px solid #46b450;
            background: #f0f9f0;
        }
        
        .mds-page-error-item.manual-fix {
            border-left: 4px solid #ffb900;
            background: #fffbf0;
        }
        
        .mds-error-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        
        .mds-error-severity {
            background: #666;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            text-transform: uppercase;
            margin-left: auto;
        }
        
        .mds-error-severity.mds-severity-high {
            background: #dc3232;
        }
        
        .mds-error-severity.mds-severity-medium {
            background: #ffb900;
        }
        
        .mds-error-severity.mds-severity-low {
            background: #46b450;
        }
        
        .mds-error-description {
            color: #666;
            margin-bottom: 10px;
            line-height: 1.4;
        }
        
        .mds-error-fix {
            background: #e7f3ff;
            border: 1px solid #c3d9ff;
            padding: 10px;
            border-radius: 3px;
            margin: 10px 0;
        }
        
        .mds-error-fix code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            border: 1px solid #ddd;
        }
        
        .mds-error-help-actions {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            display: flex;
            gap: 10px;
        }
        
        /* Fix modal text styling issues - ensure text is properly visible */
        .ui-dialog .mds-page-details,
        .ui-dialog .mds-page-details *,
        #mds-page-details-modal,
        #mds-page-details-modal *,
        #mds-page-config-modal,
        #mds-page-config-modal *,
        #mds-page-error-help-modal,
        #mds-page-error-help-modal * {
            color: #333 !important;
        }
        
        .ui-dialog .mds-page-details h3,
        .ui-dialog .mds-page-details strong,
        #mds-page-details-modal h3,
        #mds-page-details-modal strong,
        #mds-page-config-modal h3,
        #mds-page-config-modal strong,
        #mds-page-error-help-modal h3,
        #mds-page-error-help-modal strong {
            color: #23282d !important;
        }
        
        .ui-dialog .mds-page-details a,
        #mds-page-details-modal a,
        #mds-page-config-modal a,
        #mds-page-error-help-modal a {
            color: #0073aa !important;
        }
        
        /* Force white text on primary buttons in modal */
        .ui-dialog .mds-details-actions .button-primary,
        .ui-dialog .mds-details-actions .button-primary *,
        .ui-dialog .mds-details-actions a.button-primary,
        .ui-dialog .mds-details-actions a.button-primary *,
        #mds-page-details-modal .mds-details-actions .button-primary,
        #mds-page-details-modal .mds-details-actions .button-primary *,
        #mds-page-details-modal .mds-details-actions a.button-primary,
        #mds-page-details-modal .mds-details-actions a.button-primary * {
            color: #ffffff !important;
            text-shadow: none !important;
        }
        
        .ui-dialog .mds-details-actions .button-primary:hover,
        .ui-dialog .mds-details-actions .button-primary:hover *,
        .ui-dialog .mds-details-actions a.button-primary:hover,
        .ui-dialog .mds-details-actions a.button-primary:hover *,
        #mds-page-details-modal .mds-details-actions .button-primary:hover,
        #mds-page-details-modal .mds-details-actions .button-primary:hover *,
        #mds-page-details-modal .mds-details-actions a.button-primary:hover,
        #mds-page-details-modal .mds-details-actions a.button-primary:hover * {
            color: #ffffff !important;
            text-shadow: none !important;
        }
        
        .ui-dialog .mds-page-details table,
        #mds-page-details-modal table,
        #mds-page-config-modal table,
        #mds-page-error-help-modal table {
            background: #fff;
        }
        
        .ui-dialog .mds-page-details td,
        .ui-dialog .mds-page-details th,
        #mds-page-details-modal td,
        #mds-page-details-modal th,
        #mds-page-config-modal td,
        #mds-page-config-modal th,
        #mds-page-error-help-modal td,
        #mds-page-error-help-modal th {
            background: #fff !important;
            color: #333 !important;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .mds-details-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        
        .mds-details-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        
        .mds-details-table td:first-child {
            width: 30%;
            font-weight: 500;
        }
        
        .mds-details-section {
            margin-bottom: 20px;
            background: #fff;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .mds-details-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #23282d !important;
            font-size: 16px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
        }
        
        .mds-details-actions {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            display: block;
        }
        
        .mds-details-actions .button {
            display: inline-block !important;
            padding: 6px 12px !important;
            height: 28px !important;
            line-height: 16px !important;
            text-decoration: none !important;
            font-size: 13px !important;
            border-radius: 3px !important;
            cursor: pointer !important;
            margin-right: 8px !important;
            margin-bottom: 5px !important;
            vertical-align: middle !important;
            min-height: auto !important;
            max-height: 28px !important;
            box-sizing: border-box !important;
            align-items: center !important;
            justify-content: center !important;
            flex: none !important;
        }
        
        .mds-details-actions .button-primary,
        .mds-details-actions .button-primary:link,
        .mds-details-actions .button-primary:visited,
        .mds-details-actions .button-primary:active,
        .mds-details-actions a.button-primary,
        .mds-details-actions a.button-primary:link,
        .mds-details-actions a.button-primary:visited,
        .mds-details-actions a.button-primary:active {
            background: #2271b1 !important;
            border: 1px solid #2271b1 !important;
            color: #ffffff !important;
            font-weight: 400 !important;
            text-shadow: none !important;
        }
        
        .mds-details-actions .button-primary:hover,
        .mds-details-actions .button-primary:focus,
        .mds-details-actions a.button-primary:hover,
        .mds-details-actions a.button-primary:focus {
            background: #135e96 !important;
            border-color: #135e96 !important;
            color: #ffffff !important;
            text-shadow: none !important;
        }
        
        .mds-details-actions .button-secondary {
            background: #f6f7f7 !important;
            border: 1px solid #dcdcde !important;
            color: #2c3338 !important;
            font-weight: 400 !important;
        }
        
        .mds-details-actions .button-secondary:hover,
        .mds-details-actions .button-secondary:focus {
            background: #f0f0f1 !important;
            border-color: #8c8f94 !important;
            color: #2c3338 !important;
        }
        
        /* Error section specific styling */
        .mds-error-section {
            border-left: 4px solid #dc3232;
            background: #fef7f7 !important;
        }
        
        .mds-error-section h3 {
            color: #dc3232 !important;
            display: flex;
            align-items: center;
        }
        
        .mds-error-count {
            background: #dc3232;
            color: #fff !important;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .mds-page-errors .mds-error-item {
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 4px;
        }
        
        .mds-page-errors .mds-error-header .dashicons {
            margin-right: 8px;
        }
        
        .mds-page-errors .mds-error-header .dashicons-yes-alt {
            color: #46b450;
        }
        
        .mds-page-errors .mds-error-header .dashicons-warning {
            color: #ffb900;
        }
        
        .mds-error-actions {
            margin-top: 10px;
        }
        
        .mds-manual-fix-note {
            margin-top: 10px;
            font-style: italic;
            color: #666 !important;
        }
        
        /* Fix progress bar styling - make text readable */
        .mds-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 160000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .mds-modal-content {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .mds-modal-body {
            text-align: center;
        }
        
        .mds-loading {
            margin-bottom: 15px;
        }
        
        .mds-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #0073aa;
            border-radius: 50%;
            animation: rotation 2s infinite linear;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        .mds-progress {
            width: 300px;
            height: 25px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
            border: 1px solid #ddd;
        }
        
        .mds-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
            color: #fff !important;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 13px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
            transition: width 0.3s ease;
            min-width: 80px;
            border-radius: 3px;
            position: relative;
        }
        
        .mds-progress-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 3px;
        }
        
        #mds-progress-message {
            color: #333 !important;
            font-weight: 500;
            margin-bottom: 10px;
            display: block;
        }
        
        /* Progress indicator overlay */
        #mds-progress-indicator {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 160000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #mds-progress-indicator .mds-modal-content {
            background: #fff;
            border-radius: 8px;
            padding: 30px;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        </style>
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
                        $post = get_post( $page_id );
                        if ( !$post ) {
                            $error_count++;
                            $errors[] = sprintf( Language::get( 'Post with ID %d does not exist' ), $page_id );
                            break;
                        }
                        
                        if ( !current_user_can( 'delete_post', $page_id ) ) {
                            $error_count++;
                            $errors[] = sprintf( Language::get( 'Insufficient permissions to delete post: %s (ID: %d)' ), $post->post_title, $page_id );
                            break;
                        }
                        
                        if ( wp_delete_post( $page_id, true ) ) {
                            $this->metadata_manager->deleteMetadata( $page_id );
                            $success_count++;
                        } else {
                            $error_count++;
                            $errors[] = sprintf( Language::get( 'Failed to delete post: %s (ID: %d)' ), $post->post_title, $page_id );
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
                        
                    case 'remove_from_list':
                        $post = get_post( $page_id );
                        if ( !$post ) {
                            $error_count++;
                            $errors[] = sprintf( Language::get( 'Post with ID %d does not exist' ), $page_id );
                            break;
                        }
                        
                        // Only remove metadata, keep the WordPress page
                        $delete_result = $this->metadata_manager->deleteMetadata( $page_id );
                        if ( is_wp_error( $delete_result ) ) {
                            $error_count++;
                            $errors[] = sprintf( Language::get( 'Failed to remove %s from MDS management: %s' ), $post->post_title, $delete_result->get_error_message() );
                        } else {
                            $success_count++;
                        }
                        break;
                        
                    default:
                        $error_count++;
                        $errors[] = sprintf( Language::get( 'Unknown action: %s' ), $action );
                }
            } catch ( \Exception $e ) {
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
        
        // Check and repair page type first (before content repairs)
        $current_page_type = $metadata->page_type;
        
        try {
            $detection_engine = new \MillionDollarScript\Classes\Data\MDSPageDetectionEngine();
            $detection_result = $detection_engine->detectMDSPage( $page_id );
            
            if ( $detection_result['is_mds_page'] && $detection_result['page_type'] && 
                 $detection_result['page_type'] !== $current_page_type &&
                 $detection_result['confidence'] >= 0.7 ) { // Use same confidence threshold as detection
                
                // Update the page type in metadata
                $update_result = $this->metadata_manager->updatePageType( $page_id, $detection_result['page_type'] );
                
                if ( is_wp_error( $update_result ) ) {
                    Logs::log( "Failed to update page type for page {$page_id}: " . $update_result->get_error_message(), Logs::LEVEL_ERROR );
                    $repairs_made[] = sprintf( 
                        Language::get( 'Failed to correct page type: %s' ), 
                        $update_result->get_error_message()
                    );
                    $page_type = $current_page_type; // Keep original type
                } else {
                    $repairs_made[] = sprintf( 
                        Language::get( 'Corrected page type from "%s" to "%s"' ), 
                        $current_page_type, 
                        $detection_result['page_type'] 
                    );
                    // Use the corrected page type for subsequent repairs
                    $page_type = $detection_result['page_type'];
                    $metadata->page_type = $page_type; // Update local object
                    
                    Logs::log( "Successfully corrected page type for page {$page_id}: '{$current_page_type}' -> '{$detection_result['page_type']}'", Logs::LEVEL_INFO );
                }
            } else {
                $page_type = $current_page_type;
                Logs::log( "No page type correction needed for page {$page_id}", Logs::LEVEL_DEBUG );
            }
        } catch ( \Exception $e ) {
            Logs::log( "Error during page type detection/repair for page {$page_id}: " . $e->getMessage(), Logs::LEVEL_ERROR );
            $repairs_made[] = sprintf( 
                Language::get( 'Page type detection failed: %s' ), 
                $e->getMessage()
            );
            $page_type = $current_page_type; // Keep original type
        }
        
        // Check and repair content
        $content = $post->post_content;
        
        // Repair missing shortcodes
        if ( !has_shortcode( $content, 'milliondollarscript' ) && $metadata->content_type === 'shortcode' ) {
            $shortcode = sprintf( '[milliondollarscript type="%s"]', $page_type );
            $content = $shortcode . "\n\n" . $content;
            $repairs_made[] = Language::get( 'Added missing shortcode' );
        }
        
        // Repair missing blocks
        if ( !has_blocks( $content ) && $metadata->content_type === 'block' ) {
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
        try {
            if ( !check_ajax_referer( 'mds_page_management_nonce', 'nonce', false ) ) {
                wp_send_json_error( [
                    'message' => Language::get( 'Security check failed. Please refresh the page and try again.' ),
                    'code' => 'nonce_verification_failed'
                ] );
                return;
            }
            
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [
                    'message' => Language::get( 'Insufficient permissions' ),
                    'code' => 'insufficient_permissions'
                ] );
                return;
            }
            
            $action = sanitize_key( $_POST['action_type'] ?? '' );
            $page_ids = array_map( 'intval', $_POST['page_ids'] ?? [] );
            
            if ( empty( $action ) ) {
                wp_send_json_error( [
                    'message' => Language::get( 'No action specified' ),
                    'code' => 'missing_action'
                ] );
                return;
            }
            
            if ( empty( $page_ids ) ) {
                wp_send_json_error( [
                    'message' => Language::get( 'No pages selected' ),
                    'code' => 'missing_page_ids'
                ] );
                return;
            }
            
            $allowed_actions = [ 'scan', 'repair', 'delete', 'activate', 'deactivate', 'remove_from_list' ];
            if ( !in_array( $action, $allowed_actions, true ) ) {
                wp_send_json_error( [
                    'message' => sprintf( Language::get( 'Invalid action: %s' ), $action ),
                    'code' => 'invalid_action'
                ] );
                return;
            }
            
            $results = $this->executeBulkAction( $action, $page_ids );
            
            wp_send_json_success( $results );
            
        } catch ( \Exception $e ) {
            Logs::log( 'bulk_action_error', 'Failed to process bulk action', [
                'error' => $e->getMessage(),
                'action' => $action ?? 'unknown',
                'page_ids' => $page_ids ?? []
            ] );
            
            wp_send_json_error( [
                'message' => Language::get( 'An error occurred while processing the bulk action.' ),
                'code' => 'execution_error'
            ] );
        }
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
        if ( !check_ajax_referer( 'mds_page_management_nonce', 'nonce', false ) ) {
            wp_send_json_error( [
                'message' => Language::get( 'Security check failed. Please refresh the page and try again.' ),
                'code' => 'nonce_verification_failed'
            ] );
            return;
        }
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [
                'message' => Language::get( 'Insufficient permissions' ),
                'code' => 'insufficient_permissions'
            ] );
            return;
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
            // Page is NOT an MDS page, but it's in our management list
            // Offer to remove it from the list
            $post = get_post( $page_id );
            $post_title = $post ? $post->post_title : "Unknown Page";
            
            wp_send_json_success( [
                'message' => sprintf( Language::get( 'Page "%s" is not an MDS page' ), $post_title ),
                'result' => $result,
                'offer_removal' => true,
                'page_id' => $page_id,
                'page_title' => $post_title
            ] );
        }
    }
    
    /**
     * Handle repair page AJAX request
     *
     * @return void
     */
    public function handleRepairPage(): void {
        if ( !check_ajax_referer( 'mds_page_management_nonce', 'nonce', false ) ) {
            wp_send_json_error( [
                'message' => Language::get( 'Security check failed. Please refresh the page and try again.' ),
                'code' => 'nonce_verification_failed'
            ] );
            return;
        }
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [
                'message' => Language::get( 'Insufficient permissions' ),
                'code' => 'insufficient_permissions'
            ] );
            return;
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
     * Handle activate page AJAX request
     *
     * @return void
     */
    public function handleActivatePage(): void {
        try {
            if ( !check_ajax_referer( 'mds_page_management_nonce', 'nonce', false ) ) {
                wp_send_json_error( [
                    'message' => Language::get( 'Security check failed. Please refresh the page and try again.' ),
                    'code' => 'nonce_verification_failed'
                ] );
                return;
            }
            
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [
                    'message' => Language::get( 'Insufficient permissions' ),
                    'code' => 'insufficient_permissions'
                ] );
                return;
            }
            
            $page_id = intval( $_POST['page_id'] ?? 0 );
            
            if ( empty( $page_id ) ) {
                wp_send_json_error( [
                    'message' => Language::get( 'No page ID provided' ),
                    'code' => 'missing_page_id'
                ] );
                return;
            }
            
            $result = $this->executeBulkAction( 'activate', [ $page_id ] );
            
            wp_send_json_success( $result );
            
        } catch ( \Exception $e ) {
            wp_send_json_error( [
                'message' => Language::get( 'An error occurred while activating the page.' ),
                'code' => 'execution_error'
            ] );
        }
    }
    
    /**
     * Handle deactivate page AJAX request
     *
     * @return void
     */
    public function handleDeactivatePage(): void {
        try {
            if ( !check_ajax_referer( 'mds_page_management_nonce', 'nonce', false ) ) {
                wp_send_json_error( [
                    'message' => Language::get( 'Security check failed. Please refresh the page and try again.' ),
                    'code' => 'nonce_verification_failed'
                ] );
                return;
            }
            
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [
                    'message' => Language::get( 'Insufficient permissions' ),
                    'code' => 'insufficient_permissions'
                ] );
                return;
            }
            
            $page_id = intval( $_POST['page_id'] ?? 0 );
            
            if ( empty( $page_id ) ) {
                wp_send_json_error( [
                    'message' => Language::get( 'No page ID provided' ),
                    'code' => 'missing_page_id'
                ] );
                return;
            }
            
            $result = $this->executeBulkAction( 'deactivate', [ $page_id ] );
            
            wp_send_json_success( $result );
            
        } catch ( \Exception $e ) {
            wp_send_json_error( [
                'message' => Language::get( 'An error occurred while deactivating the page.' ),
                'code' => 'execution_error'
            ] );
        }
    }
    
    /**
     * Handle delete page AJAX request
     *
     * @return void
     */
    public function handleDeletePage(): void {
        try {
            if ( !check_ajax_referer( 'mds_page_management_nonce', 'nonce', false ) ) {
                wp_send_json_error( [
                    'message' => Language::get( 'Security check failed. Please refresh the page and try again.' ),
                    'code' => 'nonce_verification_failed'
                ] );
                return;
            }
            
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [
                    'message' => Language::get( 'Insufficient permissions' ),
                    'code' => 'insufficient_permissions'
                ] );
                return;
            }
            
            $page_id = intval( $_POST['page_id'] ?? 0 );
            
            if ( empty( $page_id ) ) {
                wp_send_json_error( [
                    'message' => Language::get( 'No page ID provided' ),
                    'code' => 'missing_page_id'
                ] );
                return;
            }
            
            $result = $this->executeBulkAction( 'delete', [ $page_id ] );
            
            wp_send_json_success( $result );
            
        } catch ( \Exception $e ) {
            wp_send_json_error( [
                'message' => Language::get( 'An error occurred while deleting the page.' ),
                'code' => 'execution_error'
            ] );
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
        
        // Check for page errors - always check if metadata exists, regardless of status
        $page_errors = [];
        if ( $metadata ) {
            $page_errors = $this->validatePage( $metadata, $post );
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
            'analysis' => $this->detection_engine->detectMDSPage( $page_id ),
            'errors' => $page_errors
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
                        <?php if ( isset( $details['metadata']['confidence_score'] ) ): ?>
                        <tr>
                            <td><strong><?php echo esc_html( Language::get( 'Confidence Score' ) ); ?>:</strong></td>
                            <td><?php echo esc_html( number_format( $details['metadata']['confidence_score'] * 100, 1 ) . '%' ); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ( isset( $details['metadata']['last_validated'] ) ): ?>
                        <tr>
                            <td><strong><?php echo esc_html( Language::get( 'Last Scan' ) ); ?>:</strong></td>
                            <td>
                                <?php 
                                if ( $details['metadata']['last_validated'] ) {
                                    $last_scan = is_string( $details['metadata']['last_validated'] ) 
                                        ? $details['metadata']['last_validated'] 
                                        : $details['metadata']['last_validated']->format( 'Y-m-d H:i:s' );
                                    echo esc_html( $last_scan );
                                } else {
                                    echo esc_html( Language::get( 'Never' ) );
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endif; ?>
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
                        <td><strong><?php echo esc_html( Language::get( 'Detection Confidence' ) ); ?>:</strong></td>
                        <td><?php echo esc_html( number_format( $details['analysis']['confidence'] * 100, 1 ) . '%' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html( Language::get( 'Detected Type' ) ); ?>:</strong></td>
                        <td><?php echo esc_html( $details['analysis']['page_type'] ?? Language::get( 'Unknown' ) ); ?></td>
                    </tr>
                </table>
            </div>
            
            <?php if ( !empty( $details['errors'] ) ): ?>
                <div class="mds-details-section mds-error-section">
                    <h3>
                        <span class="dashicons dashicons-warning" style="color: #dc3232; margin-right: 8px;"></span>
                        <?php echo esc_html( Language::get( 'Page Errors' ) ); ?>
                        <span class="mds-error-count">(<?php echo count( $details['errors'] ); ?>)</span>
                    </h3>
                    <div class="mds-page-errors">
                        <?php foreach ( $details['errors'] as $error ): ?>
                            <div class="mds-error-item <?php echo $error['auto_fixable'] ? 'auto-fixable' : 'manual-fix'; ?>">
                                <div class="mds-error-header">
                                    <span class="dashicons <?php echo $error['auto_fixable'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                                    <strong><?php echo esc_html( $error['message'] ); ?></strong>
                                    <span class="mds-error-severity mds-severity-<?php echo esc_attr( $error['severity'] ); ?>">
                                        <?php echo esc_html( strtoupper( $error['severity'] ) ); ?>
                                    </span>
                                </div>
                                <div class="mds-error-description">
                                    <?php echo esc_html( $error['description'] ); ?>
                                </div>
                                <?php if ( $error['suggested_fix'] ): ?>
                                    <div class="mds-error-fix">
                                        <strong><?php echo esc_html( Language::get( 'Suggested Fix:' ) ); ?></strong>
                                        <code><?php echo esc_html( $error['suggested_fix'] ); ?></code>
                                    </div>
                                <?php endif; ?>
                                <?php if ( $error['auto_fixable'] ): ?>
                                    <div class="mds-error-actions">
                                        <button type="button" class="button button-primary button-small mds-fix-error-btn" 
                                                data-page-id="<?php echo esc_attr( $error['page_id'] ); ?>"
                                                data-error="<?php echo esc_attr( wp_json_encode( $error ) ); ?>">
                                            <?php echo esc_html( Language::get( 'Fix This Error' ) ); ?>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <p class="mds-manual-fix-note">
                                        <em><?php echo esc_html( Language::get( 'This error requires manual intervention.' ) ); ?></em>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
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
    
    /**
     * Handle scan all pages AJAX request
     *
     * @return void
     */
    public function handleScanAllPages(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $posts = get_posts( [
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ] );
        
        $results = $this->executeBulkAction( 'scan', $posts );
        
        if ( $results['success'] ) {
            wp_send_json_success( [
                'message' => sprintf(
                    Language::get( 'Scan completed: %d pages processed, %d MDS pages found' ),
                    count( $posts ),
                    $results['success_count']
                ),
                'stats' => $this->getPageStatistics()
            ] );
        } else {
            wp_send_json_error( $results['message'] );
        }
    }

    public function handleScanAllPagesStart(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $posts = get_posts( [
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ] );
        
        $total_pages = count( $posts );
        $batch_size = 10; // Process 10 pages per batch
        $total_batches = ceil( $total_pages / $batch_size );
        
        // Store scan progress in transient
        $scan_id = 'mds_scan_' . wp_generate_uuid4();
        $scan_data = [
            'page_ids' => $posts,
            'total_pages' => $total_pages,
            'batch_size' => $batch_size,
            'total_batches' => $total_batches,
            'current_batch' => 0,
            'processed_pages' => 0,
            'found_pages' => 0,
            'errors' => [],
            'start_time' => current_time( 'timestamp' ),
            'status' => 'running'
        ];
        
        set_transient( $scan_id, $scan_data, 3600 ); // 1 hour expiry
        
        wp_send_json_success( [
            'scan_id' => $scan_id,
            'total_pages' => $total_pages,
            'total_batches' => $total_batches,
            'batch_size' => $batch_size
        ] );
    }

    public function handleScanAllPagesBatch(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $scan_id = sanitize_text_field( $_POST['scan_id'] ?? '' );
        $batch_number = intval( $_POST['batch_number'] ?? 0 );
        
        if ( empty( $scan_id ) ) {
            wp_send_json_error( Language::get( 'Invalid scan ID' ) );
        }
        
        $scan_data = get_transient( $scan_id );
        if ( !$scan_data ) {
            wp_send_json_error( Language::get( 'Scan data not found or expired' ) );
        }
        
        // Calculate batch pages
        $start_index = $batch_number * $scan_data['batch_size'];
        $batch_pages = array_slice( $scan_data['page_ids'], $start_index, $scan_data['batch_size'] );
        
        if ( empty( $batch_pages ) ) {
            wp_send_json_error( Language::get( 'No pages to process in this batch' ) );
        }
        
        // Process this batch
        $batch_results = [];
        $found_in_batch = 0;
        
        foreach ( $batch_pages as $page_id ) {
            try {
                $detection_result = $this->detection_engine->detectMDSPage( $page_id );
                
                if ( $detection_result && isset( $detection_result['is_mds_page'] ) && $detection_result['is_mds_page'] ) {
                    $page_type = $detection_result['page_type'] ?? 'unknown';
                    
                    // Ensure page_type is always a string
                    if ( is_array( $page_type ) ) {
                        $page_type = is_string( $page_type[0] ?? null ) ? $page_type[0] : 'unknown';
                        Logs::log( "Warning: page_type was array for page {$page_id}, using first element: {$page_type}", Logs::LEVEL_WARNING );
                    } elseif ( !is_string( $page_type ) ) {
                        $page_type = 'unknown';
                        Logs::log( "Warning: page_type was not string for page {$page_id}, defaulting to 'unknown'", Logs::LEVEL_WARNING );
                    }
                    
                    $creation_method = 'auto-detected';
                    $additional_data = $detection_result;
                    
                    // Ensure database is ready before saving
                    if ( !$this->metadata_manager->isDatabaseReady() ) {
                        error_log( "MDS Scan: Database not ready for page {$page_id}, attempting to initialize..." );
                        $init_result = $this->metadata_manager->initialize();
                        if ( is_wp_error( $init_result ) ) {
                            error_log( "MDS Scan: Database initialization failed for page {$page_id}: " . $init_result->get_error_message() );
                            throw new Exception( 'Failed to initialize metadata database: ' . $init_result->get_error_message() );
                        }
                        error_log( "MDS Scan: Database initialization successful for page {$page_id}" );
                    }
                    
                    // Log the save attempt
                    error_log( "MDS Scan: Attempting to save metadata for page {$page_id} with type '{$page_type}' and method '{$creation_method}'" );
                    error_log( "MDS Scan: Additional data for page {$page_id}: " . wp_json_encode( $additional_data ) );
                    
                    $metadata_result = $this->metadata_manager->createOrUpdateMetadata( $page_id, $page_type, $creation_method, $additional_data );
                    
                    // Log the save result
                    if ( is_wp_error( $metadata_result ) ) {
                        error_log( "MDS Scan: Metadata save FAILED for page {$page_id}: " . $metadata_result->get_error_message() );
                    } else {
                        error_log( "MDS Scan: Metadata save SUCCESS for page {$page_id}" );
                    }
                    
                    // Check if metadata creation was successful
                    if ( is_wp_error( $metadata_result ) ) {
                        $error_message = 'Failed to save metadata for page ' . $page_id . ': ' . $metadata_result->get_error_message();
                        Logs::log( $error_message, Logs::LEVEL_ERROR );
                        $scan_data['errors'][] = [
                            'page_id' => $page_id,
                            'error' => $error_message
                        ];
                        $batch_results[] = [
                            'page_id' => $page_id,
                            'found' => true,
                            'saved' => false,
                            'error' => $error_message,
                            'confidence' => $detection_result['confidence'] ?? 0.0,
                            'page_type' => $page_type
                        ];
                    } else {
                        $found_in_batch++;
                        
                        // Clear post cache to ensure the post is available immediately
                        clean_post_cache( $page_id );
                        wp_cache_delete( $page_id, 'posts' );
                        
                        $batch_results[] = [
                            'page_id' => $page_id,
                            'found' => true,
                            'saved' => true,
                            'confidence' => $detection_result['confidence'] ?? 0.0,
                            'page_type' => $page_type
                        ];
                        
                        // Log successful save for debugging
                        Logs::log( "Successfully saved metadata for page {$page_id} with type '{$page_type}'", Logs::LEVEL_INFO );
                    }
                } else {
                    $batch_results[] = [
                        'page_id' => $page_id,
                        'found' => false
                    ];
                }
            } catch ( Exception $e ) {
                $scan_data['errors'][] = [
                    'page_id' => $page_id,
                    'error' => $e->getMessage()
                ];
                $batch_results[] = [
                    'page_id' => $page_id,
                    'found' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Update scan progress
        $scan_data['current_batch'] = $batch_number + 1;
        $scan_data['processed_pages'] += count( $batch_pages );
        $scan_data['found_pages'] += $found_in_batch;
        
        // Check if scan is complete
        $is_complete = $scan_data['current_batch'] >= $scan_data['total_batches'];
        if ( $is_complete ) {
            $scan_data['status'] = 'completed';
            $scan_data['end_time'] = current_time( 'timestamp' );
            
            // Force database synchronization to ensure all writes are committed
            global $wpdb;
            $wpdb->flush();
            
            // Add delay and cache clearing to ensure database commits are fully processed
            // This prevents race condition when page refreshes too quickly
            if ( $found_in_batch > 0 || $scan_data['found_pages'] > 0 ) {
                // Clear any WordPress caches that might prevent new pages from appearing
                if ( function_exists( 'wp_cache_flush' ) ) {
                    wp_cache_flush();
                }
                
                // Clear object cache
                if ( function_exists( 'wp_cache_flush_group' ) ) {
                    wp_cache_flush_group( 'mds_pages' );
                }
                
                // Clear page-related caches that might affect the management interface
                wp_cache_delete( 'all_page_ids', 'posts' );
                wp_cache_delete( 'get_pages', 'posts' );
                
                // Clear any persistent object cache
                if ( function_exists( 'wp_cache_flush_runtime' ) ) {
                    wp_cache_flush_runtime();
                }
                
                usleep( 1500000 ); // 1.5 second delay when pages were found (increased for better reliability)
            }
            
            // Log completion for debugging
            Logs::log( "Scan completed: found {$scan_data['found_pages']} MDS pages out of {$scan_data['total_pages']} total pages", Logs::LEVEL_INFO );
            
            // Validate that saved pages are actually in database
            if ( $scan_data['found_pages'] > 0 ) {
                $actual_count = $this->metadata_manager->getStatistics()['total_pages'] ?? 0;
                if ( $actual_count !== $scan_data['found_pages'] ) {
                    Logs::log( "Warning: Scan found {$scan_data['found_pages']} pages but database contains {$actual_count} metadata records", Logs::LEVEL_WARNING );
                }
            }
        }
        
        set_transient( $scan_id, $scan_data, 3600 );
        
        wp_send_json_success( [
            'batch_number' => $batch_number,
            'processed_pages' => $scan_data['processed_pages'],
            'total_pages' => $scan_data['total_pages'],
            'found_pages' => $scan_data['found_pages'],
            'found_in_batch' => $found_in_batch,
            'is_complete' => $is_complete,
            'batch_results' => $batch_results,
            'progress_percentage' => round( ( $scan_data['processed_pages'] / $scan_data['total_pages'] ) * 100, 1 )
        ] );
    }

    public function handleScanAllPagesStatus(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $scan_id = sanitize_text_field( $_POST['scan_id'] ?? '' );
        
        if ( empty( $scan_id ) ) {
            wp_send_json_error( Language::get( 'Invalid scan ID' ) );
        }
        
        $scan_data = get_transient( $scan_id );
        if ( !$scan_data ) {
            wp_send_json_error( Language::get( 'Scan data not found or expired' ) );
        }
        
        wp_send_json_success( [
            'status' => $scan_data['status'],
            'processed_pages' => $scan_data['processed_pages'],
            'total_pages' => $scan_data['total_pages'],
            'found_pages' => $scan_data['found_pages'],
            'current_batch' => $scan_data['current_batch'],
            'total_batches' => $scan_data['total_batches'],
            'errors' => $scan_data['errors'],
            'progress_percentage' => round( ( $scan_data['processed_pages'] / $scan_data['total_pages'] ) * 100, 1 )
        ] );
    }
    
    /**
     * Handle repair all pages AJAX request
     *
     * @return void
     */
    public function handleRepairAllPages(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        // Use the same logic as handleFixAllErrors to avoid duplication
        $page_errors = $this->scanForPageErrors();
        $compatibility_errors = $this->runCompatibilityErrorScan();
        
        $total_fixes = 0;
        $total_errors = 0;
        $fix_messages = [];
        
        // Apply compatibility fixes first (includes page type fixes)
        if ( !empty( $compatibility_errors['auto_fixable'] ) ) {
            foreach ( $compatibility_errors['auto_fixable'] as $error ) {
                $fix_result = $this->applyCompatibilityFix( $error );
                if ( $fix_result['success'] ) {
                    $total_fixes++;
                } else {
                    $total_errors++;
                }
            }
            $fix_messages[] = sprintf( 
                Language::get( 'Compatibility fixes: %d applied' ), 
                count( $compatibility_errors['auto_fixable'] ) 
            );
        }
        
        // Apply page content error fixes
        $auto_fixable_page_errors = array_filter( $page_errors, function( $error ) {
            return $error['auto_fixable'] ?? false;
        } );
        
        if ( !empty( $auto_fixable_page_errors ) ) {
            foreach ( $auto_fixable_page_errors as $error ) {
                $fix_result = $this->applyErrorFix( $error );
                if ( $fix_result['success'] ) {
                    $total_fixes++;
                } else {
                    $total_errors++;
                }
            }
            $fix_messages[] = sprintf( 
                Language::get( 'Content fixes: %d applied' ), 
                count( $auto_fixable_page_errors ) 
            );
        }
        
        // Finally, run standard repair on any remaining pages with status issues
        $all_pages = $this->metadata_manager->getAllPages();
        $pages_with_issues = array_filter( $all_pages, function( $metadata ) {
            return in_array( $metadata->status, [ 'needs_repair', 'validation_failed', 'missing_content' ] );
        } );
        
        if ( !empty( $pages_with_issues ) ) {
            $page_ids = array_map( function( $metadata ) {
                return $metadata->post_id;
            }, $pages_with_issues );
            
            $results = $this->executeBulkAction( 'repair', $page_ids );
            if ( $results['success'] ) {
                $total_fixes += $results['success_count'];
                $total_errors += $results['error_count'];
                $fix_messages[] = sprintf( 
                    Language::get( 'Status repairs: %d pages processed' ), 
                    $results['success_count'] 
                );
            }
        }
        
        if ( $total_fixes === 0 && $total_errors === 0 ) {
            wp_send_json_success( [
                'message' => Language::get( 'No issues found to repair. All pages appear to be functioning correctly.' ),
                'stats' => $this->getPageStatistics()
            ] );
            return;
        }
        
        $message = sprintf(
            Language::get( 'Repair completed: %d total fixes applied, %d errors encountered' ),
            $total_fixes,
            $total_errors
        );
        
        if ( !empty( $fix_messages ) ) {
            $message .= ' | ' . implode( ' | ', $fix_messages );
        }
        
        wp_send_json_success( [
            'message' => $message,
            'total_fixes' => $total_fixes,
            'total_errors' => $total_errors,
            'stats' => $this->getPageStatistics()
        ] );
    }
    
    /**
     * Handle scan for errors AJAX request
     *
     * @return void
     */
    public function handleScanErrors(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        try {
            Logs::log( 'Starting error scan process', Logs::LEVEL_INFO );
            
            // Get both page errors and compatibility errors
            $page_errors = $this->scanForPageErrors();
            Logs::log( 'Found ' . count( $page_errors ) . ' page content errors', Logs::LEVEL_INFO );
            
            $compatibility_errors = $this->runCompatibilityErrorScan();
            Logs::log( 'Found ' . count( $compatibility_errors['auto_fixable'] ?? [] ) . ' compatibility errors', Logs::LEVEL_INFO );
            
            // Combine all errors into a single array
            $all_errors = array_merge( $page_errors, $compatibility_errors['auto_fixable'] ?? [] );
            
            wp_send_json_success( [
                'errors' => $all_errors,
                'total_errors' => count( $all_errors ),
                'auto_fixable' => count( array_filter( $all_errors, function( $error ) {
                    return $error['auto_fixable'] ?? false;
                } ) )
            ] );
        } catch ( \Exception $e ) {
            Logs::log( 'Error scanning for page errors: ' . $e->getMessage(), Logs::LEVEL_ERROR );
            wp_send_json_error( [
                'message' => Language::get( 'Error scanning failed: ' ) . $e->getMessage(),
                'debug_info' => [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ] );
        }
    }
    
    /**
     * Handle fix all errors AJAX request
     *
     * @return void
     */
    public function handleFixAllErrors(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        // Get both page errors and compatibility errors
        $page_errors = $this->scanForPageErrors();
        $compatibility_errors = $this->runCompatibilityErrorScan();
        
        $fixed_count = 0;
        $failed_count = 0;
        
        // Fix compatibility errors first (includes page type fixes)
        foreach ( $compatibility_errors['auto_fixable'] ?? [] as $error ) {
            $fix_result = $this->applyCompatibilityFix( $error );
            if ( $fix_result['success'] ) {
                $fixed_count++;
            } else {
                $failed_count++;
            }
        }
        
        // Then fix regular page errors
        $auto_fixable_page_errors = array_filter( $page_errors, function( $error ) {
            return $error['auto_fixable'] ?? false;
        } );
        
        foreach ( $auto_fixable_page_errors as $error ) {
            $fix_result = $this->applyErrorFix( $error );
            if ( $fix_result['success'] ) {
                $fixed_count++;
            } else {
                $failed_count++;
            }
        }
        
        $message = sprintf(
            Language::get( 'Error fixing completed: %d errors fixed, %d failed' ),
            $fixed_count,
            $failed_count
        );
        
        wp_send_json_success( [
            'message' => $message,
            'fixed_count' => $fixed_count,
            'failed_count' => $failed_count,
            'stats' => $this->getPageStatistics()
        ] );
    }
    
    /**
     * Handle get page stats AJAX request
     *
     * @return void
     */
    public function handleGetPageStats(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        wp_send_json_success( $this->getPageStatistics() );
    }
    
    /**
     * Scan for page errors
     *
     * @return array
     */
    private function scanForPageErrors(): array {
        $errors = [];
        $pages = $this->metadata_manager->getAllPages();
        
        foreach ( $pages as $metadata ) {
            $post = get_post( $metadata->post_id );
            if ( !$post ) {
                continue;
            }
            
            // Check for content-based errors (missing shortcodes, blocks, etc.)
            $page_errors = $this->validatePage( $metadata, $post );
            if ( !empty( $page_errors ) ) {
                $errors = array_merge( $errors, $page_errors );
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate a single page and return errors
     *
     * @param object $metadata
     * @param \WP_Post $post
     * @return array
     */
    private function validatePage( $metadata, $post ): array {
        $errors = [];
        $content = $post->post_content;
        $page_type = $metadata->page_type;
        
        // Check for missing shortcodes only if expected
        if ( $metadata->content_type === 'shortcode' && !has_shortcode( $content, 'milliondollarscript' ) && !has_shortcode( $content, 'mds' ) ) {
            // Only flag as error if this page type actually needs a shortcode
            if ( $this->pageTypeNeedsShortcode( $page_type ) ) {
                $errors[] = [
                    'page_id' => $metadata->post_id,
                    'page_title' => $post->post_title,
                    'type' => 'missing_shortcode',
                    'severity' => 'high',
                    'message' => Language::get( 'Missing MDS shortcode' ),
                    'description' => Language::get( 'This page should contain an MDS shortcode but none was found' ),
                    'auto_fixable' => true,
                    'suggested_fix' => sprintf( '[milliondollarscript type="%s"]', $page_type )
                ];
            }
        }
        
        // Check for missing blocks only if expected
        if ( $metadata->content_type === 'block' && !has_blocks( $content ) ) {
            // Only flag as error if this page type actually needs a block
            if ( $this->pageTypeNeedsBlock( $page_type ) ) {
                $errors[] = [
                    'page_id' => $metadata->post_id,
                    'page_title' => $post->post_title,
                    'type' => 'missing_block',
                    'severity' => 'high',
                    'message' => Language::get( 'Missing MDS block' ),
                    'description' => Language::get( 'This page should contain an MDS block but none was found' ),
                    'auto_fixable' => true,
                    'suggested_fix' => sprintf( '<!-- wp:milliondollarscript/%s-block /-->', $page_type )
                ];
            }
        }
        
        // Skip page type detection comparison for now as it's causing false positives
        // The detection engine might be incorrectly identifying page types
        
        // Note: Removed minimal content validation as MDS pages often just contain shortcodes
        // that load content via AJAX, so having only a shortcode is perfectly normal
        
        return $errors;
    }
    
    /**
     * Check if page type needs a shortcode
     *
     * @param string $page_type
     * @return bool
     */
    private function pageTypeNeedsShortcode( string $page_type ): bool {
        // Page types that definitely need shortcodes to function
        $shortcode_required = [ 'grid', 'order', 'payment', 'manage' ];
        return in_array( $page_type, $shortcode_required );
    }
    
    /**
     * Check if page type needs a block
     *
     * @param string $page_type
     * @return bool
     */
    private function pageTypeNeedsBlock( string $page_type ): bool {
        // Similar to shortcode requirements
        $block_required = [ 'grid', 'order', 'payment', 'manage' ];
        return in_array( $page_type, $block_required );
    }
    
    
    /**
     * Apply a fix for a specific error
     *
     * @param array $error
     * @return array
     */
    private function applyErrorFix( array $error ): array {
        if ( !$error['auto_fixable'] ) {
            return [
                'success' => false,
                'message' => Language::get( 'Error is not auto-fixable' )
            ];
        }
        
        $post = get_post( $error['page_id'] );
        if ( !$post ) {
            return [
                'success' => false,
                'message' => Language::get( 'Page not found' )
            ];
        }
        
        $content = $post->post_content;
        
        switch ( $error['type'] ) {
            case 'missing_shortcode':
                $content = $error['suggested_fix'] . "\n\n" . $content;
                break;
                
            case 'missing_block':
                $content = $error['suggested_fix'] . "\n\n" . $content;
                break;
                
            case 'incorrect_page_type':
                try {
                    $page_type_to_set = $error['detected_type'] ?? $error['suggested_fix'];
                    $result = $this->metadata_manager->updatePageType( $error['page_id'], $page_type_to_set );
                    
                    if ( is_wp_error( $result ) ) {
                        Logs::log( 'Failed to update page type for page ' . $error['page_id'] . ': ' . $result->get_error_message(), Logs::LEVEL_ERROR );
                        return [
                            'success' => false,
                            'message' => Language::get( 'Failed to update page type: ' ) . $result->get_error_message()
                        ];
                    }
                    
                    Logs::log( 'Successfully updated page type for page ' . $error['page_id'] . ' to ' . $page_type_to_set, Logs::LEVEL_INFO );
                    return [
                        'success' => true,
                        'message' => sprintf( 
                            Language::get( 'Page type updated to "%s"' ), 
                            $page_type_to_set 
                        )
                    ];
                } catch ( \Exception $e ) {
                    Logs::log( 'Exception updating page type for page ' . $error['page_id'] . ': ' . $e->getMessage(), Logs::LEVEL_ERROR );
                    return [
                        'success' => false,
                        'message' => Language::get( 'Error updating page type: ' ) . $e->getMessage()
                    ];
                }
                
            default:
                return [
                    'success' => false,
                    'message' => Language::get( 'Unknown error type' )
                ];
        }
        
        $update_result = wp_update_post( [
            'ID' => $error['page_id'],
            'post_content' => $content
        ] );
        
        if ( is_wp_error( $update_result ) ) {
            return [
                'success' => false,
                'message' => $update_result->get_error_message()
            ];
        }
        
        return [
            'success' => true,
            'message' => Language::get( 'Error fixed successfully' )
        ];
    }
    
    /**
     * Run compatibility error scan (similar to compatibility page)
     *
     * @return array
     */
    private function runCompatibilityErrorScan(): array {
        $compatibility_manager = \MillionDollarScript\Classes\Data\MDSBackwardCompatibilityManager::getInstance();
        
        // Get migration status and check for compatibility issues
        $status = $compatibility_manager->getCompatibilityStatus();
        $errors = [
            'auto_fixable' => [],
            'manual_fix' => [],
            'total' => 0
        ];
        
        // Check for pending migrations
        if ( !empty( $status['pending_migrations'] ) ) {
            foreach ( $status['pending_migrations'] as $migration ) {
                $errors['auto_fixable'][] = [
                    'type' => 'pending_migration',
                    'migration' => $migration,
                    'severity' => 'high',
                    'message' => Language::get( 'Pending migration found' ),
                    'description' => sprintf( Language::get( 'Migration %s is pending and should be run' ), $migration ),
                    'auto_fixable' => true
                ];
            }
        }
        
        // Check for pages without metadata
        $pages_without_metadata = $status['total_mds_pages'] - $status['pages_with_metadata'];
        if ( $pages_without_metadata > 0 ) {
            $errors['auto_fixable'][] = [
                'type' => 'missing_metadata',
                'count' => $pages_without_metadata,
                'severity' => 'medium',
                'message' => Language::get( 'Pages missing metadata' ),
                'description' => sprintf( Language::get( '%d MDS pages are missing metadata records' ), $pages_without_metadata ),
                'auto_fixable' => true
            ];
        }
        
        // Check for pages with incorrect page types
        $pages_with_wrong_types = $this->checkForIncorrectPageTypes();
        if ( !empty( $pages_with_wrong_types ) ) {
            foreach ( $pages_with_wrong_types as $page_error ) {
                $errors['auto_fixable'][] = [
                    'type' => 'incorrect_page_type',
                    'page_id' => $page_error['page_id'],
                    'page_title' => $page_error['page_title'],
                    'current_type' => $page_error['current_type'],
                    'detected_type' => $page_error['detected_type'],
                    'severity' => 'medium',
                    'message' => Language::get( 'Incorrect page type detected' ),
                    'description' => sprintf( 
                        Language::get( 'Page "%s" is classified as "%s" but should be "%s"' ),
                        $page_error['page_title'],
                        $page_error['current_type'],
                        $page_error['detected_type']
                    ),
                    'auto_fixable' => true
                ];
            }
        }
        
        $errors['total'] = count( $errors['auto_fixable'] ) + count( $errors['manual_fix'] );
        
        return $errors;
    }
    
    /**
     * Check for pages with incorrect page types
     *
     * @return array
     */
    private function checkForIncorrectPageTypes(): array {
        $pages_with_wrong_types = [];
        
        try {
            $detection_engine = new \MillionDollarScript\Classes\Data\MDSPageDetectionEngine();
            
            // Get all pages with metadata
            $all_pages = $this->metadata_manager->getAllPages();
            Logs::log( 'Checking page types for ' . count( $all_pages ) . ' pages with metadata', Logs::LEVEL_INFO );
            
            if ( empty( $all_pages ) ) {
                Logs::log( 'No pages with metadata found for page type checking', Logs::LEVEL_WARNING );
                return [];
            }
        } catch ( \Exception $e ) {
            Logs::log( 'Error initializing page type checking: ' . $e->getMessage(), Logs::LEVEL_ERROR );
            return [];
        }
        
        foreach ( $all_pages as $metadata ) {
            $page_id = $metadata->post_id;
            $current_type = $metadata->page_type;
            
            // Skip if no current type set
            if ( empty( $current_type ) ) {
                Logs::log( "Skipping page {$page_id} - no current type set", Logs::LEVEL_DEBUG );
                continue;
            }
            
            try {
                // Run detection on the page
                $detection_result = $detection_engine->detectMDSPage( $page_id );
                
                Logs::log( "Page {$page_id}: current='{$current_type}', detected='{$detection_result['page_type']}', confidence={$detection_result['confidence']}", Logs::LEVEL_DEBUG );
                
                // Check if detected type differs from current type
                if ( $detection_result['is_mds_page'] && 
                     !empty( $detection_result['page_type'] ) && 
                     $detection_result['page_type'] !== $current_type &&
                     $detection_result['confidence'] >= 0.7 ) { // Only flag high-confidence mismatches
                    
                    $post = get_post( $page_id );
                    if ( $post ) {
                        $pages_with_wrong_types[] = [
                            'page_id' => $page_id,
                            'page_title' => $post->post_title,
                            'current_type' => $current_type,
                            'detected_type' => $detection_result['page_type'],
                            'confidence' => $detection_result['confidence']
                        ];
                        
                        Logs::log( "Found page type mismatch for page {$page_id} ('{$post->post_title}'): '{$current_type}' -> '{$detection_result['page_type']}'", Logs::LEVEL_INFO );
                    }
                } else {
                    // Log why the page wasn't flagged as having type errors
                    if ( !$detection_result['is_mds_page'] ) {
                        Logs::log( "Page {$page_id} not detected as MDS page, skipping type check", Logs::LEVEL_DEBUG );
                    } else if ( empty( $detection_result['page_type'] ) ) {
                        Logs::log( "Page {$page_id} has no detected page type, skipping", Logs::LEVEL_DEBUG );
                    } else if ( $detection_result['page_type'] === $current_type ) {
                        Logs::log( "Page {$page_id} page type matches ('{$current_type}'), no error", Logs::LEVEL_DEBUG );
                    } else if ( $detection_result['confidence'] < 0.7 ) {
                        Logs::log( "Page {$page_id} page type mismatch but low confidence ({$detection_result['confidence']}) - not flagging", Logs::LEVEL_DEBUG );
                    }
                }
            } catch ( \Exception $e ) {
                Logs::log( "Error detecting page type for page {$page_id}: " . $e->getMessage(), Logs::LEVEL_ERROR );
            }
        }
        
        Logs::log( 'Found ' . count( $pages_with_wrong_types ) . ' pages with incorrect page types', Logs::LEVEL_INFO );
        return $pages_with_wrong_types;
    }
    
    /**
     * Apply compatibility fix
     *
     * @param array $error
     * @return array
     */
    private function applyCompatibilityFix( array $error ): array {
        $compatibility_manager = \MillionDollarScript\Classes\Data\MDSBackwardCompatibilityManager::getInstance();
        
        switch ( $error['type'] ) {
            case 'pending_migration':
                try {
                    // Run the migration using the existing method
                    $result = $compatibility_manager->migrateExistingPagesToMetadataSystem();
                    if ( $result && $result['migrated_pages'] > 0 ) {
                        return [
                            'success' => true,
                            'message' => sprintf( Language::get( 'Migration completed: %d pages migrated' ), $result['migrated_pages'] )
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => Language::get( 'Migration completed but no new pages were migrated' )
                        ];
                    }
                } catch ( \Exception $e ) {
                    return [
                        'success' => false,
                        'message' => sprintf( Language::get( 'Migration failed: %s' ), $e->getMessage() )
                    ];
                }
                
            case 'missing_metadata':
                try {
                    // Run metadata creation for missing pages using the existing migration method
                    $result = $compatibility_manager->migrateExistingPagesToMetadataSystem();
                    if ( $result && $result['migrated_pages'] > 0 ) {
                        return [
                            'success' => true,
                            'message' => sprintf( Language::get( 'Created metadata for %d pages' ), $result['migrated_pages'] )
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => Language::get( 'No new metadata was created - pages may already have metadata' )
                        ];
                    }
                } catch ( \Exception $e ) {
                    return [
                        'success' => false,
                        'message' => Language::get( 'Failed to create missing metadata: ' ) . $e->getMessage()
                    ];
                }
                
            case 'incorrect_page_type':
                try {
                    $page_id = $error['page_id'] ?? 0;
                    if ( !$page_id ) {
                        return [
                            'success' => false,
                            'message' => Language::get( 'No page ID provided for page type correction' )
                        ];
                    }
                    
                    // Use existing repair functionality to fix page type
                    $repair_result = $this->repairPage( $page_id );
                    
                    if ( $repair_result['success'] ) {
                        return [
                            'success' => true,
                            'message' => $repair_result['message'] ?? Language::get( 'Page type corrected successfully' )
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => $repair_result['message'] ?? Language::get( 'Failed to correct page type' )
                        ];
                    }
                } catch ( \Exception $e ) {
                    return [
                        'success' => false,
                        'message' => sprintf( Language::get( 'Error correcting page type: %s' ), $e->getMessage() )
                    ];
                }
                
            default:
                return [
                    'success' => false,
                    'message' => Language::get( 'Unknown compatibility error type' )
                ];
        }
    }
    
    /**
     * Handle scan page errors AJAX request
     *
     * @return void
     */
    public function handleScanPageErrors(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $page_id = intval( $_POST['page_id'] ?? 0 );
        
        if ( !$page_id ) {
            wp_send_json_error( Language::get( 'Invalid page ID' ) );
        }
        
        $post = get_post( $page_id );
        if ( !$post ) {
            wp_send_json_error( Language::get( 'Page not found' ) );
        }
        
        $metadata = $this->metadata_manager->getMetadata( $page_id );
        if ( !$metadata ) {
            wp_send_json_error( Language::get( 'No metadata found for this page' ) );
        }
        
        $errors = $this->validatePage( $metadata, $post );
        
        wp_send_json_success( [
            'errors' => $errors,
            'page_title' => $post->post_title,
            'page_id' => $page_id,
            'total_errors' => count( $errors )
        ] );
    }
    
    /**
     * Handle fix single page error AJAX request
     *
     * @return void
     */
    public function handleFixSinglePageError(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $page_id = intval( $_POST['page_id'] ?? 0 );
        $error_json = $_POST['error'] ?? '';
        
        if ( !$page_id ) {
            wp_send_json_error( Language::get( 'Invalid page ID' ) );
        }
        
        if ( empty( $error_json ) ) {
            wp_send_json_error( Language::get( 'No error data provided' ) );
        }
        
        $error = json_decode( stripslashes( $error_json ), true );
        if ( !$error ) {
            wp_send_json_error( Language::get( 'Invalid error data format' ) );
        }
        
        // Override the page_id in the error to match the request
        $error['page_id'] = $page_id;
        
        $result = $this->applyErrorFix( $error );
        
        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }
    
    /**
     * Handle get page config AJAX request
     *
     * @return void
     */
    public function handleGetPageConfig(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $page_id = intval( $_POST['page_id'] ?? 0 );
        
        if ( !$page_id ) {
            wp_send_json_error( Language::get( 'Invalid page ID' ) );
        }
        
        $post = get_post( $page_id );
        $metadata = $this->metadata_manager->getMetadata( $page_id );
        
        if ( !$post ) {
            wp_send_json_error( Language::get( 'Page not found' ) );
        }
        
        if ( !$metadata ) {
            wp_send_json_error( Language::get( 'Page metadata not found. Please scan the page first.' ) );
        }
        
        $config_data = [
            'page_id' => $page_id,
            'title' => $post->post_title,
            'page_type' => $metadata->page_type,
            'content_type' => $metadata->content_type,
            'status' => $metadata->status,
            'creation_method' => $metadata->creation_method,
            'confidence_score' => $metadata->confidence_score,
            'auto_scan' => $metadata->auto_scan ?? false,
            'page_config' => $metadata->page_config ?? []
        ];
        
        wp_send_json_success( $config_data );
    }
    
    /**
     * Handle save page config AJAX request
     *
     * @return void
     */
    public function handleSavePageConfig(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $page_id = intval( $_POST['page_id'] ?? 0 );
        $page_type = sanitize_key( $_POST['page_type'] ?? '' );
        $implementation_type = sanitize_key( $_POST['implementation_type'] ?? '' );
        $auto_scan = !empty( $_POST['auto_scan'] );
        
        if ( !$page_id ) {
            wp_send_json_error( Language::get( 'Invalid page ID' ) );
        }
        
        $metadata = $this->metadata_manager->getMetadata( $page_id );
        
        if ( !$metadata ) {
            wp_send_json_error( Language::get( 'Page metadata not found' ) );
        }
        
        // Handle implementation conversion if requested
        $convert_implementation = !empty( $_POST['convert_implementation'] );
        $original_implementation = $metadata->content_type;
        
        if ( $convert_implementation && $original_implementation !== $implementation_type ) {
            $conversion_result = $this->convertPageImplementation( $page_id, $original_implementation, $implementation_type, $page_type );
            
            if ( is_wp_error( $conversion_result ) ) {
                wp_send_json_error( $conversion_result->get_error_message() );
            }
        }
        
        // Update metadata
        $metadata->page_type = $page_type;
        $metadata->content_type = $implementation_type;
        $metadata->auto_scan = $auto_scan;
        $metadata->touch();
        
        $result = $this->metadata_manager->getRepository()->save( $metadata );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        
        wp_send_json_success( [
            'message' => Language::get( 'Page configuration saved successfully' ),
            'config' => [
                'page_type' => $metadata->page_type,
                'content_type' => $metadata->content_type,
                'auto_scan' => $metadata->auto_scan
            ]
        ] );
    }
    
    /**
     * Convert page implementation between shortcode and block
     *
     * @param int $page_id
     * @param string $from_type
     * @param string $to_type
     * @param string $page_type
     * @return bool|\WP_Error
     */
    private function convertPageImplementation( int $page_id, string $from_type, string $to_type, string $page_type ) {
        $post = get_post( $page_id );
        if ( !$post ) {
            return new \WP_Error( 'post_not_found', Language::get( 'Post not found' ) );
        }
        
        $content = $post->post_content;
        $new_content = $content;
        
        // Log conversion attempt
        Logs::log( "MDS Conversion: Converting page {$page_id} from {$from_type} to {$to_type} (page_type: {$page_type})" );
        Logs::log( "MDS Conversion: Original content length: " . strlen( $content ) );
        
        // Get grid dimensions if this is a grid page
        $grid_dimensions = $this->getGridDimensions( $page_type );
        
        if ( $from_type === 'shortcode' && $to_type === 'block' ) {
            // Convert shortcode to block
            $new_content = $this->convertShortcodeToBlock( $content, $page_type, $grid_dimensions );
        } elseif ( $from_type === 'block' && $to_type === 'shortcode' ) {
            // Convert block to shortcode
            $new_content = $this->convertBlockToShortcode( $content, $page_type, $grid_dimensions );
        } elseif ( $from_type === 'custom' ) {
            // Handle custom to shortcode/block conversion
            if ( $to_type === 'shortcode' ) {
                $new_content = $this->convertShortcodeToBlock( $content, $page_type, $grid_dimensions );
                $new_content = $this->convertBlockToShortcode( $new_content, $page_type, $grid_dimensions );
            } elseif ( $to_type === 'block' ) {
                $new_content = $this->convertShortcodeToBlock( $content, $page_type, $grid_dimensions );
            }
        }
        
        Logs::log( "MDS Conversion: New content length: " . strlen( $new_content ) );
        Logs::log( "MDS Conversion: Content changed: " . ( $content !== $new_content ? 'YES' : 'NO' ) );
        
        // Only update if content actually changed
        if ( $content !== $new_content ) {
            // Update post content
            $update_result = wp_update_post( [
                'ID' => $page_id,
                'post_content' => $new_content
            ] );
            
            if ( is_wp_error( $update_result ) ) {
                return $update_result;
            }
            
            Logs::log( "MDS Conversion: Content updated successfully" );
        } else {
            Logs::log( "MDS Conversion: No changes made to content" );
        }
        
        return true;
    }
    
    /**
     * Convert shortcode to block format
     *
     * @param string $content
     * @param string $page_type
     * @param array $grid_dimensions
     * @return string
     */
    private function convertShortcodeToBlock( string $content, string $page_type, array $grid_dimensions ): string {
        // Pattern to match only core MDS shortcodes
        $pattern = '/\[milliondollarscript([^\]]*)\]/';
        
        return preg_replace_callback( $pattern, function( $matches ) use ( $page_type, $grid_dimensions ) {
            $attributes = shortcode_parse_atts( $matches[1] );
            
            // Create configuration JSON
            $config = [
                'grid_info' => json_encode( [
                    'grid_id' => $attributes['id'] ?? '1',
                    'grid_title' => 'Grid ' . ( $attributes['id'] ?? '1' ),
                    'blocks' => $grid_dimensions['blocks'] ?? ['width' => 100, 'height' => 100, 'total' => 10000],
                    'block_size' => $grid_dimensions['block_size'] ?? ['width' => 10, 'height' => 10],
                    'pixels' => $grid_dimensions['pixels'] ?? ['width' => 1000, 'height' => 1000, 'total' => 1000000],
                    'pricing' => $grid_dimensions['pricing'] ?? ['pixel_price' => 1, 'currency' => 'CAD', 'min_order' => 10],
                    'dimensions_text' => $grid_dimensions['dimensions_text'] ?? '100x100 blocks (1000x1000 pixels)'
                ] ),
                'align' => $attributes['align'] ?? 'center',
                'grid_id' => intval( $attributes['id'] ?? 1 )
            ];
            
            $config_comment = '<!-- MDS Configuration: ' . wp_json_encode( $config ) . ' -->';
            
            return "<p>{$config_comment}</p>\n\n<!-- wp:carbon-fields/million-dollar-script /-->";
        }, $content );
    }
    
    /**
     * Convert block to shortcode format
     *
     * @param string $content
     * @param string $page_type
     * @param array $grid_dimensions
     * @return string
     */
    private function convertBlockToShortcode( string $content, string $page_type, array $grid_dimensions ): string {
        // Remove MDS Configuration comments and extract data
        $config_pattern = '/<!-- MDS Configuration: (.*?) -->/s';
        $config_data = [];
        
        if ( preg_match( $config_pattern, $content, $matches ) ) {
            $config_data = json_decode( $matches[1], true ) ?: [];
        }
        
        // Remove configuration comments and their wrapper paragraphs
        $content = preg_replace( '/\s*<p>\s*<!-- MDS Configuration: .*? -->\s*<\/p>\s*/s', '', $content );
        $content = preg_replace( $config_pattern, '', $content );
        
        // Replace different types of MDS blocks with shortcodes
        $block_patterns = [
            '/<!-- wp:carbon-fields\/million-dollar-script \/-->/',  // Self-closing block
            '/<!-- wp:carbon-fields\/million-dollar-script -->.*?<!-- \/wp:carbon-fields\/million-dollar-script -->/s'  // Block with content
        ];
        
        foreach ( $block_patterns as $block_pattern ) {
            $content = preg_replace_callback( $block_pattern, function( $matches ) use ( $page_type, $grid_dimensions, $config_data ) {
                $grid_id = $config_data['grid_id'] ?? 1;
                $align = $config_data['align'] ?? 'center';
                
                // Use proper width/height instead of display parameter
                $width = $grid_dimensions['width'] ?? '1000px';
                $height = $grid_dimensions['height'] ?? '1000px';
                
                return "[milliondollarscript id=\"{$grid_id}\" align=\"{$align}\" width=\"{$width}\" height=\"{$height}\" type=\"{$page_type}\"]";
            }, $content );
        }
        
        return $content;
    }
    
    /**
     * Get grid dimensions for a specific page type
     *
     * @param string $page_type
     * @return array
     */
    private function getGridDimensions( string $page_type ): array {
        // Default dimensions for different page types
        $defaults = [
            'grid' => [
                'width' => '1000px',
                'height' => '1000px',
                'blocks' => ['width' => 100, 'height' => 100, 'total' => 10000],
                'block_size' => ['width' => 10, 'height' => 10],
                'pixels' => ['width' => 1000, 'height' => 1000, 'total' => 1000000],
                'pricing' => ['pixel_price' => 1, 'currency' => 'CAD', 'min_order' => 10],
                'dimensions_text' => '100x100 blocks (1000x1000 pixels)'
            ],
            'stats' => [
                'width' => '150px',
                'height' => '60px'
            ]
        ];
        
        // For non-grid types, use 100% width and auto height
        if ( $page_type !== 'grid' && $page_type !== 'stats' ) {
            return [
                'width' => '100%',
                'height' => 'auto'
            ];
        }
        
        return $defaults[$page_type] ?? $defaults['grid'];
    }
    
    /**
     * Handle reset page statuses AJAX request
     *
     * @return void
     */
    public function handleResetPageStatuses(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $pages = $this->metadata_manager->getAllPages();
        $updated_count = 0;
        $errors = [];
        
        foreach ( $pages as $metadata ) {
            $post = get_post( $metadata->post_id );
            if ( !$post ) {
                continue;
            }
            
            // Re-validate the page and set proper status
            $page_errors = $this->validatePage( $metadata, $post );
            
            $new_status = 'active';
            if ( !empty( $page_errors ) ) {
                $new_status = 'needs_repair';
            }
            
            // Only update if status changed
            if ( $metadata->status !== $new_status ) {
                $metadata->status = $new_status;
                $metadata->validation_errors = $page_errors;
                $metadata->touch();
                
                $result = $this->metadata_manager->getRepository()->save( $metadata );
                if ( is_wp_error( $result ) ) {
                    $errors[] = sprintf(
                        Language::get( 'Failed to update status for page %d: %s' ),
                        $metadata->post_id,
                        $result->get_error_message()
                    );
                } else {
                    $updated_count++;
                }
            }
        }
        
        $message = sprintf(
            Language::get( 'Page status reset completed: %d pages updated' ),
            $updated_count
        );
        
        if ( !empty( $errors ) ) {
            $message .= ' | Errors: ' . implode( ', ', $errors );
        }
        
        wp_send_json_success( [
            'message' => $message,
            'updated_count' => $updated_count,
            'errors' => $errors,
            'stats' => $this->getPageStatistics()
        ] );
    }

    /**
     * Handle diagnose metadata system AJAX request
     *
     * @return void
     */
    public function handleDiagnoseMetadataSystem(): void {
        check_ajax_referer( 'mds_page_management_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $diagnosis = [];
        
        // Check if database is ready
        $db_ready = $this->metadata_manager->isDatabaseReady();
        $diagnosis['database_ready'] = $db_ready;
        
        if ( !$db_ready ) {
            // Attempt to initialize
            $init_result = $this->metadata_manager->initialize();
            $diagnosis['initialization_attempted'] = true;
            $diagnosis['initialization_result'] = is_wp_error( $init_result ) ? $init_result->get_error_message() : 'success';
            $diagnosis['database_ready_after_init'] = $this->metadata_manager->isDatabaseReady();
        }
        
        // Get statistics
        if ( $this->metadata_manager->isDatabaseReady() ) {
            $stats = $this->metadata_manager->getStatistics();
            $diagnosis['current_metadata_count'] = $stats['total_pages'] ?? 0;
            $diagnosis['statistics'] = $stats;
        }
        
        // Check WordPress database errors
        global $wpdb;
        $diagnosis['last_db_error'] = $wpdb->last_error ?: 'none';
        
        // Test a simple detection
        $sample_pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => 1,
            'fields' => 'ids'
        ]);
        
        if ( $sample_pages ) {
            $test_page_id = $sample_pages[0];
            try {
                $detection_result = $this->detection_engine->detectMDSPage( $test_page_id );
                $diagnosis['test_detection'] = [
                    'page_id' => $test_page_id,
                    'is_mds_page' => $detection_result['is_mds_page'] ?? false,
                    'confidence' => $detection_result['confidence'] ?? 0.0,
                    'page_type' => $detection_result['page_type'] ?? null
                ];
            } catch ( Exception $e ) {
                $diagnosis['test_detection_error'] = $e->getMessage();
            }
        }
        
        wp_send_json_success( [
            'diagnosis' => $diagnosis,
            'timestamp' => current_time( 'mysql' )
        ] );
    }
    
    /**
     * Handle validate all pages AJAX request
     * Scans all MDS pages to find those that are no longer MDS pages and offers removal
     *
     * @return void
     */
    public function handleValidateAllPages(): void {
        try {
            if ( !check_ajax_referer( 'mds_page_management_nonce', 'nonce', false ) ) {
                wp_send_json_error( [
                    'message' => Language::get( 'Security check failed. Please refresh the page and try again.' ),
                    'code' => 'nonce_verification_failed'
                ] );
                return;
            }
            
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [
                    'message' => Language::get( 'Insufficient permissions' ),
                    'code' => 'insufficient_permissions'
                ] );
                return;
            }
            
            // Get all pages currently in MDS management
            $managed_pages = $this->metadata_manager->getAllPages();
            
            $validation_results = [
                'total_checked' => 0,
                'still_valid' => 0,
                'invalid_pages' => [],
                'errors' => []
            ];
            
            foreach ( $managed_pages as $metadata ) {
                $validation_results['total_checked']++;
                
                try {
                    // Check if page still exists
                    $post = get_post( $metadata->post_id );
                    if ( !$post ) {
                        $validation_results['invalid_pages'][] = [
                            'id' => $metadata->post_id,
                            'title' => '(Post no longer exists)',
                            'reason' => 'Post has been deleted from WordPress',
                            'action' => 'remove_metadata'
                        ];
                        continue;
                    }
                    
                    // Re-scan the page to see if it's still an MDS page
                    $detection_result = $this->detection_engine->detectMDSPage( $metadata->post_id );
                    
                    if ( !$detection_result['is_mds_page'] || $detection_result['confidence'] < 0.3 ) {
                        $validation_results['invalid_pages'][] = [
                            'id' => $metadata->post_id,
                            'title' => $post->post_title,
                            'reason' => 'Page no longer contains MDS content (confidence: ' . round( $detection_result['confidence'] * 100, 1 ) . '%)',
                            'action' => 'remove_from_management',
                            'current_type' => $metadata->page_type,
                            'detected_type' => $detection_result['page_type'] ?? 'none'
                        ];
                    } else {
                        $validation_results['still_valid']++;
                        
                        // Update metadata if page type has changed
                        if ( $detection_result['page_type'] !== $metadata->page_type ) {
                            $this->metadata_manager->createOrUpdateMetadata(
                                $metadata->post_id,
                                $detection_result['page_type'],
                                'validate_all_updated'
                            );
                        }
                    }
                    
                } catch ( \Exception $e ) {
                    $validation_results['errors'][] = [
                        'page_id' => $metadata->post_id,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $message = sprintf(
                Language::get( 'Validation completed: %d pages checked, %d still valid, %d invalid pages found' ),
                $validation_results['total_checked'],
                $validation_results['still_valid'],
                count( $validation_results['invalid_pages'] )
            );
            
            wp_send_json_success( [
                'message' => $message,
                'results' => $validation_results,
                'stats' => $this->getPageStatistics()
            ] );
            
        } catch ( \Exception $e ) {
            wp_send_json_error( [
                'message' => Language::get( 'An error occurred during validation: ' ) . $e->getMessage(),
                'code' => 'validation_error'
            ] );
        }
    }
} 