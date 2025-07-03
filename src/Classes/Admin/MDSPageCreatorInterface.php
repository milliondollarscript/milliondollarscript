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

use Exception;
use MillionDollarScript\Classes\Data\MDSPageMetadataManager;
use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

/**
 * Page creator interface for creating new MDS pages
 */
class MDSPageCreatorInterface {
    
    private MDSEnhancedPageCreator $enhanced_creator;
    private MDSPageMetadataManager $metadata_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->enhanced_creator = new MDSEnhancedPageCreator();
        $this->metadata_manager = MDSPageMetadataManager::getInstance();
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
        add_action( 'wp_ajax_mds_get_page_configuration', [ $this, 'handleGetPageConfiguration' ] );
        add_action( 'wp_ajax_mds_get_creation_preview', [ $this, 'handleGetCreationPreview' ] );
        add_action( 'wp_ajax_mds_create_pages', [ $this, 'handleCreatePages' ] );
        add_action( 'wp_ajax_mds_creator_create_pages', [ $this, 'handleCreatePages' ] );  // Alternative action name
        add_action( 'wp_ajax_mds_get_grid_dimensions', [ $this, 'handleGetGridDimensions' ] );
        
        // Handle form submissions
        add_action( 'admin_post_mds_create_pages', [ $this, 'handleFormSubmission' ] );
        
    }
    
    /**
     * Add admin menu
     *
     * @return void
     */
    public function addAdminMenu(): void {
        add_submenu_page(
            'milliondollarscript',
            Language::get( 'Create Pages' ),
            Language::get( 'Create Pages' ),
            'manage_options',
            'mds-create-pages',
            [ $this, 'renderCreatePagesPage' ]
        );
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook
     * @return void
     */
    public function enqueueAssets( string $hook ): void {
        if ( strpos( $hook, 'mds-create-pages' ) === false ) {
            return;
        }
        
        wp_enqueue_script(
            'mds-modal-utility',
            MDS_BASE_URL . 'src/Assets/js/shared/modal-utility.js',
            [ 'jquery' ],
            filemtime( MDS_BASE_PATH . 'src/Assets/js/shared/modal-utility.js' ),
            true
        );
        
        wp_enqueue_script(
            'mds-page-creator',
            MDS_BASE_URL . 'src/Assets/js/admin/page-creator.min.js',
            [ 'jquery', 'wp-util', 'jquery-ui-dialog', 'mds-modal-utility' ],
            filemtime( MDS_BASE_PATH . 'src/Assets/js/admin/page-creator.min.js' ),
            true
        );
        
        wp_enqueue_style(
            'mds-page-creator',
            MDS_BASE_URL . 'src/Assets/css/admin/page-creator.css',
            [ 'wp-jquery-ui-dialog' ],
            filemtime( MDS_BASE_PATH . 'src/Assets/css/admin/page-creator.css' ),
        );
        
        wp_localize_script( 'mds-page-creator', 'mdsPageCreator', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'mds_page_creator_nonce' ),
            'strings' => [
                'preview' => Language::get( 'Generating preview...' ),
                'creating' => Language::get( 'Creating pages...' ),
                'success' => Language::get( 'Pages created successfully!' ),
                'error' => Language::get( 'An error occurred' ),
                'confirm_create' => Language::get( 'Are you sure you want to create these pages?' ),
                'confirm_update' => Language::get( 'This will update existing pages. Continue?' ),
                'select_page_types' => Language::get( 'Please select at least one page type' ),
                'select_implementation' => Language::get( 'Please select an implementation type' )
            ]
        ] );
        
        // Add inline JavaScript for radio button styling only
        wp_add_inline_script( 'mds-page-creator', '
        jQuery(document).ready(function($) {
            // Handle radio button visual selection
            $("input[name=\'create_mode\']").change(function() {
                $(".mds-create-mode-option").removeClass("selected");
                $(this).closest(".mds-create-mode-option").addClass("selected");
            });
            
            // Initialize first radio button as selected
            $("input[name=\'create_mode\']:checked").closest(".mds-create-mode-option").addClass("selected");
        });
        ' );
    }
    
    /**
     * Render create pages admin page
     *
     * @return void
     */
    public function renderCreatePagesPage(): void {
        ?>
        <div class="wrap mds-create-pages">
            <h1 class="wp-heading-inline"><?php echo esc_html( Language::get( 'Create MDS Pages' ) ); ?></h1>
            
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-page-management' ) ); ?>" class="page-title-action">
                <?php echo esc_html( Language::get( 'Manage Existing Pages' ) ); ?>
            </a>
            
            <hr class="wp-header-end">
            
            <!-- Quick Create Widget -->
            <div class="mds-quick-create">
                <h2><?php echo esc_html( Language::get( 'Quick Setup' ) ); ?></h2>
                <p><?php echo esc_html( Language::get( 'Create a complete set of MDS pages with default settings.' ) ); ?></p>
                <button type="button" id="mds-quick-create-all" class="button button-secondary">
                    <?php echo esc_html( Language::get( 'Create All Standard Pages' ) ); ?>
                </button>
            </div>
            
            <!-- Enhanced Page Creator -->
            <div class="mds-page-creator-container">
                <form id="mds-page-creator-form" method="post">
                    <?php wp_nonce_field( 'mds_page_creator_nonce', 'mds_nonce' ); ?>
                    
                    <div class="mds-creator-form">
                        
                        <!-- Step 1: Implementation Type -->
                        <div class="mds-creator-step active" data-step="1">
                            <h2><?php echo esc_html( Language::get( 'Choose Implementation Type' ) ); ?></h2>
                            <p class="description">
                                <?php echo esc_html( Language::get( 'Select how you want your MDS pages to be implemented on your site.' ) ); ?>
                            </p>
                            
                            <div class="mds-implementation-types">
                                <div class="mds-implementation-option">
                                    <label class="mds-radio-card">
                                        <input type="radio" name="implementation_type" value="shortcode" required>
                                        <div class="mds-card-content">
                                            <h3><?php echo esc_html( Language::get( 'Shortcode Implementation' ) ); ?></h3>
                                            <p><?php echo esc_html( Language::get( 'Traditional WordPress shortcodes. Works with any theme and is easy to customize.' ) ); ?></p>
                                            
                                            <div class="mds-pros-cons">
                                                <div class="mds-pros">
                                                    <strong><?php echo esc_html( Language::get( 'Pros:' ) ); ?></strong>
                                                    <ul>
                                                        <li><?php echo esc_html( Language::get( 'Universal compatibility' ) ); ?></li>
                                                        <li><?php echo esc_html( Language::get( 'Easy to use' ) ); ?></li>
                                                        <li><?php echo esc_html( Language::get( 'Lightweight' ) ); ?></li>
                                                    </ul>
                                                </div>
                                                <div class="mds-cons">
                                                    <strong><?php echo esc_html( Language::get( 'Cons:' ) ); ?></strong>
                                                    <ul>
                                                        <li><?php echo esc_html( Language::get( 'Less visual in editor' ) ); ?></li>
                                                        <li><?php echo esc_html( Language::get( 'Requires shortcode knowledge' ) ); ?></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="mds-implementation-option">
                                    <label class="mds-radio-card">
                                        <input type="radio" name="implementation_type" value="block" required>
                                        <div class="mds-card-content">
                                            <h3><?php echo esc_html( Language::get( 'Gutenberg Blocks' ) ); ?></h3>
                                            <p><?php echo esc_html( Language::get( 'Modern Gutenberg blocks with visual editing and live preview.' ) ); ?></p>
                                            
                                            <div class="mds-pros-cons">
                                                <div class="mds-pros">
                                                    <strong><?php echo esc_html( Language::get( 'Pros:' ) ); ?></strong>
                                                    <ul>
                                                        <li><?php echo esc_html( Language::get( 'Visual editing' ) ); ?></li>
                                                        <li><?php echo esc_html( Language::get( 'Live preview' ) ); ?></li>
                                                        <li><?php echo esc_html( Language::get( 'Modern interface' ) ); ?></li>
                                                    </ul>
                                                </div>
                                                <div class="mds-cons">
                                                    <strong><?php echo esc_html( Language::get( 'Cons:' ) ); ?></strong>
                                                    <ul>
                                                        <li><?php echo esc_html( Language::get( 'Requires Gutenberg' ) ); ?></li>
                                                        <li><?php echo esc_html( Language::get( 'Slightly heavier' ) ); ?></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                
                            </div>
                        </div>
                        
                        <!-- Step 2: Page Type Selection -->
                        <div class="mds-creator-step inactive" data-step="2">
                            <h2><?php echo esc_html( Language::get( 'Select Page Types' ) ); ?></h2>
                            <p class="description">
                                <?php echo esc_html( Language::get( 'Choose which MDS pages you want to create. You can select any combination of pages.' ) ); ?>
                            </p>
                            
                            <!-- Grid Selection -->
                            <div class="mds-grid-selection">
                                <h3><?php echo esc_html( Language::get( 'Select Grid' ) ); ?></h3>
                                <p class="description">
                                    <?php echo esc_html( Language::get( 'Choose which grid you want to create pages for.' ) ); ?>
                                </p>
                                <div class="mds-grid-selector-container">
                                    <select id="mds-grid-selector" name="selected_grid_id">
                                        <?php $this->renderGridOptions(); ?>
                                    </select>
                                    <?php if ( $this->getEnabledGridCount() > 1 ): ?>
                                    <div class="mds-grid-sort-controls">
                                        <button type="button" id="mds-grid-sort-type-btn" class="button button-small" data-sort-by="id" title="<?php echo esc_attr( Language::get( 'Toggle sort by ID or Name' ) ); ?>">
                                            <span class="dashicons dashicons-sort"></span>
                                            <span class="sort-label"><?php echo esc_html( Language::get( 'ID' ) ); ?></span>
                                        </button>
                                        <button type="button" id="mds-grid-sort-direction-btn" class="button button-small" data-sort-direction="asc" title="<?php echo esc_attr( Language::get( 'Toggle sort direction' ) ); ?>">
                                            <span class="sort-direction dashicons dashicons-arrow-up"></span>
                                            <span class="sort-direction-label"><?php echo esc_html( Language::get( 'ASC' ) ); ?></span>
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            
                            <div class="mds-page-types">
                                <?php $this->renderPageTypeOptions(); ?>
                            </div>
                        </div>
                        
                        <!-- Step 3: Page Configuration -->
                        <div class="mds-creator-step inactive" data-step="3">
                            <h2><?php echo esc_html( Language::get( 'Configure Pages' ) ); ?></h2>
                            <p class="description">
                                <?php echo esc_html( Language::get( 'Customize the settings for your selected pages.' ) ); ?>
                            </p>
                            
                            <div id="mds-page-configurations">
                                <!-- Configurations loaded via AJAX -->
                            </div>
                        </div>
                        
                        <!-- Step 4: Existing Pages Handling -->
                        <div class="mds-creator-step inactive" data-step="4">
                            <h2><?php echo esc_html( Language::get( 'Handle Existing Pages' ) ); ?></h2>
                            <p class="description">
                                <?php echo esc_html( Language::get( 'Choose what to do if pages already exist.' ) ); ?>
                            </p>
                            
                            <div class="mds-create-mode-options">
                                <div class="mds-create-mode-option">
                                    <label class="mds-radio-card">
                                        <input type="radio" name="create_mode" value="create_new" checked>
                                        <div class="mds-card-content">
                                            <h3><?php echo esc_html( Language::get( 'Create New Pages' ) ); ?></h3>
                                            <p><?php echo esc_html( Language::get( 'Always create new pages, even if pages with similar names exist.' ) ); ?></p>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="mds-create-mode-option">
                                    <label class="mds-radio-card">
                                        <input type="radio" name="create_mode" value="update_existing">
                                        <div class="mds-card-content">
                                            <h3><?php echo esc_html( Language::get( 'Update Existing Pages' ) ); ?></h3>
                                            <p><?php echo esc_html( Language::get( 'Update existing pages with new content if they already exist.' ) ); ?></p>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="mds-create-mode-option">
                                    <label class="mds-radio-card">
                                        <input type="radio" name="create_mode" value="skip_existing">
                                        <div class="mds-card-content">
                                            <h3><?php echo esc_html( Language::get( 'Skip Existing Pages' ) ); ?></h3>
                                            <p><?php echo esc_html( Language::get( 'Only create pages that do not already exist.' ) ); ?></p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 5: Preview and Create -->
                        <div class="mds-creator-step inactive" data-step="5">
                            <h2><?php echo esc_html( Language::get( 'Review and Create' ) ); ?></h2>
                            
                            <div class="mds-creator-actions">
                                <button type="button" id="mds-preview-creation" class="button button-secondary">
                                    <?php echo esc_html( Language::get( 'Preview Creation' ) ); ?>
                                </button>
                                <button type="submit" id="mds-create-pages" class="button button-primary" disabled>
                                    <?php echo esc_html( Language::get( 'Create Pages' ) ); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Preview Panel -->
            <div id="mds-preview-panel" class="mds-preview-panel" style="display: none;">
                <h3><?php echo esc_html( Language::get( 'Creation Preview' ) ); ?></h3>
                <div id="mds-preview-content">
                    <!-- Preview content loaded via AJAX -->
                </div>
                <div class="mds-preview-actions">
                    <button type="button" id="mds-create-pages-bottom" class="button button-primary" disabled>
                        <?php echo esc_html( Language::get( 'Create Pages' ) ); ?>
                    </button>
                </div>
            </div>
            
            <!-- Results Panel -->
            <div id="mds-results-panel" class="mds-results-panel" style="display: none;">
                <h3><?php echo esc_html( Language::get( 'Creation Results' ) ); ?></h3>
                <div id="mds-results-content">
                    <!-- Results content loaded via AJAX -->
                </div>
            </div>
            
            <!-- Modal Notification System -->
            <div id="mds-notification-modal" class="mds-modal" style="display: none;">
                <div class="mds-modal-overlay"></div>
                <div class="mds-modal-content">
                    <div class="mds-modal-header">
                        <h4 id="mds-modal-title"></h4>
                        <button type="button" class="mds-modal-close" aria-label="<?php echo esc_attr( Language::get( 'Close' ) ); ?>">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                    <div class="mds-modal-body">
                        <div id="mds-modal-icon" class="mds-modal-icon"></div>
                        <div id="mds-modal-message" class="mds-modal-message"></div>
                    </div>
                    <div class="mds-modal-footer">
                        <button type="button" id="mds-modal-ok" class="button button-primary">
                            <?php echo esc_html( Language::get( 'OK' ) ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get count of enabled grids
     *
     * @return int
     */
    private function getEnabledGridCount(): int {
        global $wpdb;
        
        if ( !defined( 'MDS_DB_PREFIX' ) ) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) FROM " . MDS_DB_PREFIX . "banners WHERE enabled = 'Y'";
        $count = $wpdb->get_var( $sql );
        
        if ( $wpdb->last_error ) {
            return 0;
        }
        
        return intval( $count );
    }
    
    /**
     * Render grid options dropdown
     *
     * @return void
     */
    private function renderGridOptions(): void {
        global $wpdb;
        
        if ( !defined( 'MDS_DB_PREFIX' ) ) {
            echo '<option value="">' . esc_html( Language::get( 'Database configuration error' ) ) . '</option>';
            return;
        }
        
        $sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners ORDER BY name";
        $grids = $wpdb->get_results( $sql, ARRAY_A );
        
        if ( $wpdb->last_error ) {
            echo '<option value="">' . esc_html( Language::get( 'Database error loading grids' ) ) . '</option>';
            return;
        }
        
        $found_enabled = false;
        foreach ( $grids as $grid ) {
            // Only show enabled grids (where enabled = 'Y')
            if ( $grid['enabled'] === 'Y' ) {
                $found_enabled = true;
                $grid_title = !empty( $grid['name'] ) ? $grid['name'] : 'Grid #' . $grid['banner_id'];
                $grid_info = sprintf( 
                    '%s (%dx%d blocks)', 
                    $grid_title,
                    $grid['grid_width'] ?? 100,
                    $grid['grid_height'] ?? 100
                );
                echo '<option value="' . esc_attr( $grid['banner_id'] ) . '">' . esc_html( $grid_info ) . '</option>';
            }
        }
        
        if ( !$found_enabled ) {
            echo '<option value="">' . esc_html( Language::get( 'No enabled grids found. Please enable a grid first.' ) ) . '</option>';
        }
    }
    
    /**
     * Render page type options
     *
     * @return void
     */
    private function renderPageTypeOptions(): void {
        $page_types = [
            'grid' => [
                'label' => Language::get( 'Pixel Grid' ),
                'description' => Language::get( 'Main pixel grid display page' ),
                'icon' => 'dashicons-grid-view',
                'required' => false
            ],
            'order' => [
                'label' => Language::get( 'Order Page' ),
                'description' => Language::get( 'Page for ordering pixels' ),
                'icon' => 'dashicons-cart',
                'required' => false
            ],
            'write-ad' => [
                'label' => Language::get( 'Write Advertisement' ),
                'description' => Language::get( 'Page for creating advertisements' ),
                'icon' => 'dashicons-edit',
                'required' => false
            ],
            'confirm-order' => [
                'label' => Language::get( 'Order Confirmation' ),
                'description' => Language::get( 'Order confirmation and review page' ),
                'icon' => 'dashicons-yes-alt',
                'required' => false
            ],
            'payment' => [
                'label' => Language::get( 'Payment Processing' ),
                'description' => Language::get( 'Payment processing page' ),
                'icon' => 'dashicons-money-alt',
                'required' => false
            ],
            'manage' => [
                'label' => Language::get( 'Manage Ads' ),
                'description' => Language::get( 'Page for managing existing ads' ),
                'icon' => 'dashicons-admin-settings',
                'required' => false
            ],
            'thank-you' => [
                'label' => Language::get( 'Thank You' ),
                'description' => Language::get( 'Thank you page after purchase' ),
                'icon' => 'dashicons-heart',
                'required' => false
            ],
            'list' => [
                'label' => Language::get( 'Advertiser List' ),
                'description' => Language::get( 'List of all advertisers' ),
                'icon' => 'dashicons-list-view',
                'required' => false
            ],
            'upload' => [
                'label' => Language::get( 'File Upload' ),
                'description' => Language::get( 'File upload page' ),
                'icon' => 'dashicons-upload',
                'required' => false
            ],
            'no-orders' => [
                'label' => Language::get( 'No Orders' ),
                'description' => Language::get( 'Page shown when no orders exist' ),
                'icon' => 'dashicons-info',
                'required' => false
            ],
            'stats' => [
                'label' => Language::get( 'Stats Box' ),
                'description' => Language::get( 'Displays sold/available pixel statistics (150px × 60px)' ),
                'icon' => 'dashicons-chart-bar',
                'required' => false
            ]
        ];
        
        foreach ( $page_types as $type => $config ):
            $checked = $config['required'] ? 'checked' : '';
            $disabled = $config['required'] ? 'disabled' : '';
        ?>
            <div class="mds-page-type-option <?php echo $config['required'] ? 'required' : ''; ?>">
                <label class="mds-checkbox-card">
                    <input type="checkbox" name="page_types[]" value="<?php echo esc_attr( $type ); ?>" 
                           <?php echo $checked; ?> <?php echo $disabled; ?>>
                    <div class="mds-card-content">
                        <div class="mds-card-header">
                            <span class="dashicons <?php echo esc_attr( $config['icon'] ); ?>"></span>
                            <h3><?php echo esc_html( $config['label'] ); ?></h3>
                            <?php if ( $config['required'] ): ?>
                                <span class="mds-required-badge"><?php echo esc_html( Language::get( 'Required' ) ); ?></span>
                            <?php endif; ?>
                        </div>
                        <p><?php echo esc_html( $config['description'] ); ?></p>
                    </div>
                </label>
            </div>
        <?php
        endforeach;
    }
    
    /**
     * Handle get page configuration AJAX request
     *
     * @return void
     */
    public function handleGetPageConfiguration(): void {
        check_ajax_referer( 'mds_page_creator_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $page_types = array_map( 'sanitize_key', $_POST['page_types'] ?? [] );
        
        $configurations = $this->enhanced_creator->getPageConfigurations( $page_types );
        
        wp_send_json_success( [
            'html' => $this->renderConfigurationHTML( $configurations )
        ] );
    }
    
    /**
     * Handle get creation preview AJAX request
     *
     * @return void
     */
    public function handleGetCreationPreview(): void {
        check_ajax_referer( 'mds_page_creator_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $form_data = [
            'implementation_type' => sanitize_key( $_POST['implementation_type'] ?? '' ),
            'page_types' => array_map( 'sanitize_key', $_POST['page_types'] ?? [] ),
            'configurations' => $_POST['configurations'] ?? [],
            'create_mode' => sanitize_key( $_POST['create_mode'] ?? 'create_new' )
        ];
        
        $preview = $this->enhanced_creator->generateCreationPreview( $form_data );
        
        wp_send_json_success( $preview );
    }
    
    /**
     * Handle create pages AJAX request
     *
     * @return void
     */
    public function handleCreatePages(): void {
        try {
            
            // Debug nonce validation
            $received_nonce = $_POST['nonce'] ?? '';
            $expected_action = 'mds_page_creator_nonce';
            
            check_ajax_referer( 'mds_page_creator_nonce', 'nonce' );
            
            if ( !current_user_can( 'manage_options' ) ) {
                wp_die( Language::get( 'Insufficient permissions' ) );
            }
            
            // Validate and sanitize form data
            $form_data = [
                'implementation_type' => sanitize_key( $_POST['implementation_type'] ?? '' ),
                'page_types' => array_map( 'sanitize_key', $_POST['page_types'] ?? [] ),
                'configurations' => $_POST['configurations'] ?? [],
                'create_mode' => sanitize_key( $_POST['create_mode'] ?? 'create_new' ),
                'selected_grid_id' => intval( $_POST['selected_grid_id'] ?? 1 )
            ];
            
            // Log the incoming request for debugging
            
            // Basic validation
            if ( empty( $form_data['implementation_type'] ) ) {
                wp_send_json_error( Language::get( 'Implementation type is required' ) );
                return;
            }
            
            if ( empty( $form_data['page_types'] ) || !is_array( $form_data['page_types'] ) ) {
                wp_send_json_error( Language::get( 'At least one page type must be selected' ) );
                return;
            }
            
            // Sanitize configurations more thoroughly
            if ( is_array( $form_data['configurations'] ) ) {
                foreach ( $form_data['configurations'] as $page_type => $config ) {
                    if ( is_array( $config ) ) {
                        foreach ( $config as $key => $value ) {
                            // Sanitize based on field type
                            if ( is_string( $value ) ) {
                                $form_data['configurations'][$page_type][$key] = sanitize_text_field( $value );
                            }
                        }
                    }
                }
            }
            
            
            $result = $this->enhanced_creator->createPages( $form_data );
            
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( $result->get_error_message() );
            } else {
                wp_send_json_success( $result );
            }
            
        } catch ( \Exception $e ) {
            $error_msg = 'Creation failed: ' . $e->getMessage();
            wp_send_json_error( $error_msg );
        }
    }
    
    /**
     * Handle form submission
     *
     * @return void
     */
    public function handleFormSubmission(): void {
        if ( !wp_verify_nonce( $_POST['mds_nonce'] ?? '', 'mds_page_creator_nonce' ) ) {
            wp_die( Language::get( 'Security check failed' ) );
        }
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( Language::get( 'Insufficient permissions' ) );
        }
        
        $this->handleCreatePages();
    }
    
    /**
     * Render configuration HTML
     *
     * @param array $configurations
     * @return string
     */
    private function renderConfigurationHTML( array $configurations ): string {
        ob_start();
        
        foreach ( $configurations as $page_type => $config ):
        ?>
            <div class="mds-page-config" data-page-type="<?php echo esc_attr( $page_type ); ?>">
                <h4><?php echo esc_html( $config['title'] ?? ucfirst( $page_type ) ); ?></h4>
                
                <table class="form-table">
                    <?php foreach ( $config['fields'] ?? [] as $field_name => $field_config ): ?>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr( $page_type . '_' . $field_name ); ?>">
                                    <?php echo esc_html( $field_config['label'] ?? ucfirst( $field_name ) ); ?>
                                </label>
                            </th>
                            <td>
                                <?php $this->renderConfigurationField( $page_type, $field_name, $field_config ); ?>
                                <?php if ( !empty( $field_config['description'] ) ): ?>
                                    <p class="description"><?php echo esc_html( $field_config['description'] ); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php
        endforeach;
        
        return ob_get_clean();
    }
    
    /**
     * Render configuration field
     *
     * @param string $page_type
     * @param string $field_name
     * @param array $field_config
     * @return void
     */
    private function renderConfigurationField( string $page_type, string $field_name, array $field_config ): void {
        $field_id = $page_type . '_' . $field_name;
        $field_name_attr = "configurations[{$page_type}][{$field_name}]";
        $default_value = $field_config['default'] ?? '';
        
        switch ( $field_config['type'] ?? 'text' ) {
            case 'select':
                ?>
                <select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name_attr ); ?>">
                    <?php foreach ( $field_config['options'] ?? [] as $value => $label ): ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default_value, $value ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;
                
            case 'checkbox':
                ?>
                <label for="<?php echo esc_attr( $field_id ); ?>">
                    <input type="checkbox" id="<?php echo esc_attr( $field_id ); ?>" 
                           name="<?php echo esc_attr( $field_name_attr ); ?>" 
                           value="1" <?php checked( $default_value, true ); ?>>
                    <?php echo esc_html( $field_config['label'] ?? ucfirst( $field_name ) ); ?>
                </label>
                <?php
                break;
                
            case 'number':
                ?>
                <input type="number" id="<?php echo esc_attr( $field_id ); ?>" 
                       name="<?php echo esc_attr( $field_name_attr ); ?>"
                       value="<?php echo esc_attr( $default_value ); ?>"
                       min="<?php echo esc_attr( $field_config['min'] ?? '' ); ?>"
                       max="<?php echo esc_attr( $field_config['max'] ?? '' ); ?>"
                       step="<?php echo esc_attr( $field_config['step'] ?? '' ); ?>">
                <?php
                break;
                
            case 'textarea':
                ?>
                <textarea id="<?php echo esc_attr( $field_id ); ?>" 
                          name="<?php echo esc_attr( $field_name_attr ); ?>"
                          rows="<?php echo esc_attr( $field_config['rows'] ?? 3 ); ?>"
                          cols="<?php echo esc_attr( $field_config['cols'] ?? 50 ); ?>"><?php echo esc_textarea( $default_value ); ?></textarea>
                <?php
                break;
                
            case 'dynamic_grid_info':
                ?>
                <div id="<?php echo esc_attr( $field_id ); ?>_container" class="mds-grid-info">
                    <div class="mds-loading" style="display: none;">
                        <span class="spinner is-active"></span> Loading grid dimensions...
                    </div>
                    <div class="mds-grid-details" style="display: none;">
                        <div class="mds-grid-summary">
                            <strong id="<?php echo esc_attr( $field_id ); ?>_text">Grid dimensions will be loaded...</strong>
                        </div>
                        <div class="mds-grid-breakdown">
                            <table class="mds-grid-info-table">
                                <tr>
                                    <td><strong>Blocks:</strong></td>
                                    <td><span id="<?php echo esc_attr( $field_id ); ?>_blocks"></span></td>
                                </tr>
                                <tr>
                                    <td><strong>Block Size:</strong></td>
                                    <td><span id="<?php echo esc_attr( $field_id ); ?>_block_size"></span></td>
                                </tr>
                                <tr>
                                    <td><strong>Total Pixels:</strong></td>
                                    <td><span id="<?php echo esc_attr( $field_id ); ?>_pixels"></span></td>
                                </tr>
                                <tr>
                                    <td><strong>Price per Pixel:</strong></td>
                                    <td><span id="<?php echo esc_attr( $field_id ); ?>_price"></span></td>
                                </tr>
                                <tr>
                                    <td><strong>Minimum Order:</strong></td>
                                    <td><span id="<?php echo esc_attr( $field_id ); ?>_min_order"></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <input type="hidden" name="<?php echo esc_attr( $field_name_attr ); ?>" 
                           id="<?php echo esc_attr( $field_id ); ?>_value" value="">
                </div>
                <script>
                jQuery(document).ready(function($) {
                    
                    function loadGridDimensions(gridId) {
                        $('#<?php echo esc_attr( $field_id ); ?>_container .mds-loading').show();
                        $('#<?php echo esc_attr( $field_id ); ?>_container .mds-grid-details').hide();
                        
                        $.post(mdsPageCreator.ajaxUrl, {
                            action: 'mds_get_grid_dimensions',
                            nonce: mdsPageCreator.nonce,
                            grid_id: gridId || $('#mds-grid-selector').val() || 1
                        })
                        .done(function(response) {
                            if (response.success) {
                                var data = response.data;
                                $('#<?php echo esc_attr( $field_id ); ?>_text').text(data.dimensions_text);
                                $('#<?php echo esc_attr( $field_id ); ?>_blocks').text(data.blocks.width + 'x' + data.blocks.height + ' (' + data.blocks.total + ' total)');
                                $('#<?php echo esc_attr( $field_id ); ?>_block_size').text(data.block_size.width + 'x' + data.block_size.height + ' pixels');
                                $('#<?php echo esc_attr( $field_id ); ?>_pixels').text(data.pixels.width + 'x' + data.pixels.height + ' (' + data.pixels.total + ' total)');
                                $('#<?php echo esc_attr( $field_id ); ?>_price').text('$' + data.pricing.pixel_price + ' ' + data.pricing.currency);
                                $('#<?php echo esc_attr( $field_id ); ?>_min_order').text(data.pricing.min_order + ' pixels minimum');
                                $('#<?php echo esc_attr( $field_id ); ?>_value').val(JSON.stringify(data));
                                
                                $('#<?php echo esc_attr( $field_id ); ?>_container .mds-loading').hide();
                                $('#<?php echo esc_attr( $field_id ); ?>_container .mds-grid-details').show();
                            } else {
                                $('#<?php echo esc_attr( $field_id ); ?>_container .mds-loading').hide();
                                $('#<?php echo esc_attr( $field_id ); ?>_text').text('Error loading grid dimensions: ' + (response.data || 'Unknown error'));
                                $('#<?php echo esc_attr( $field_id ); ?>_container .mds-grid-details').show();
                            }
                        })
                        .fail(function() {
                            $('#<?php echo esc_attr( $field_id ); ?>_container .mds-loading').hide();
                            $('#<?php echo esc_attr( $field_id ); ?>_text').text('Network error loading grid dimensions');
                            $('#<?php echo esc_attr( $field_id ); ?>_container .mds-grid-details').show();
                        });
                    }
                    
                    // Load grid dimensions when the field is rendered
                    loadGridDimensions();
                    
                    // Watch for grid selection changes
                    $('#mds-grid-selector').on('change', function() {
                        loadGridDimensions($(this).val());
                    });
                });
                </script>
                <style>
                /* Grid selection and create mode styles are handled by main CSS file */
                .mds-grid-info {
                    padding: 10px;
                    background: #f9f9f9;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }
                .mds-grid-info-table {
                    margin-top: 10px;
                    width: 100%;
                }
                .mds-grid-info-table td {
                    padding: 3px 10px 3px 0;
                }
                .mds-grid-breakdown {
                    margin-top: 10px;
                    font-size: 12px;
                    color: #646970;
                }
                </style>
                <?php
                break;
                
            default:
                ?>
                <input type="text" id="<?php echo esc_attr( $field_id ); ?>" 
                       name="<?php echo esc_attr( $field_name_attr ); ?>"
                       value="<?php echo esc_attr( $default_value ); ?>"
                       class="regular-text">
                <?php
        }
    }
    
    /**
     * Handle get grid dimensions AJAX request
     *
     * @return void
     */
    public function handleGetGridDimensions(): void {
        try {
            check_ajax_referer( 'mds_page_creator_nonce', 'nonce' );
            
            if ( !current_user_can( 'manage_options' ) ) {
                wp_die( Language::get( 'Insufficient permissions' ) );
            }
            
            global $wpdb;
            
            // Check if MDS_DB_PREFIX is defined
            if ( !defined( 'MDS_DB_PREFIX' ) ) {
                wp_send_json_error( Language::get( 'Database configuration error. Please check plugin configuration.' ) );
                return;
            }
            
            $grid_id = intval( $_POST['grid_id'] ?? 1 ); // Default to grid ID 1 if not specified
            
            
            // Get grid data using the same logic from Admin.php
            $table_name = MDS_DB_PREFIX . "banners";
            $sql = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE banner_id=%d", $grid_id );
            $grid = $wpdb->get_row( $sql, ARRAY_A );
            
            if ( $wpdb->last_error ) {
                wp_send_json_error( Language::get( 'Database error retrieving grid information.' ) );
                return;
            }
            
            if ( !$grid ) {
                // Try to get the first available grid if the specified one doesn't exist
                $sql = "SELECT * FROM {$table_name} ORDER BY banner_id ASC LIMIT 1";
                $grid = $wpdb->get_row( $sql, ARRAY_A );
                
                if ( $wpdb->last_error ) {
                    wp_send_json_error( Language::get( 'Database error retrieving grid information.' ) );
                    return;
                }
            }
            
            if ( !$grid ) {
                wp_send_json_error( Language::get( 'No grid found. Please create a grid first.' ) );
                return;
            }
            
            $grid_width = intval( $grid['grid_width'] ?? 100 );
            $grid_height = intval( $grid['grid_height'] ?? 100 );
            $block_width = intval( $grid['block_width'] ?? 10 );
            $block_height = intval( $grid['block_height'] ?? 10 );
            
            $pixel_width = $grid_width * $block_width;
            $pixel_height = $grid_height * $block_height;
            
            // Get pricing information from grid
            $pixel_price = floatval( $grid['pixel_price'] ?? 1.00 );
            $currency = $grid['currency'] ?? 'USD';
            $min_order = intval( $grid['min_order'] ?? 10 );
            
            $response_data = [
                'grid_id' => $grid['banner_id'],
                'grid_title' => $grid['name'] ?? 'Main Grid',
                'blocks' => [
                    'width' => $grid_width,
                    'height' => $grid_height,
                    'total' => $grid_width * $grid_height
                ],
                'block_size' => [
                    'width' => $block_width,
                    'height' => $block_height
                ],
                'pixels' => [
                    'width' => $pixel_width,
                    'height' => $pixel_height,
                    'total' => $pixel_width * $pixel_height
                ],
                'pricing' => [
                    'pixel_price' => $pixel_price,
                    'currency' => $currency,
                    'min_order' => $min_order
                ],
                'dimensions_text' => sprintf( '%dx%d blocks (%dx%d pixels)', $grid_width, $grid_height, $pixel_width, $pixel_height )
            ];
            
            wp_send_json_success( $response_data );
            
        } catch ( \Exception $e ) {
            wp_send_json_error( 'Error retrieving grid dimensions: ' . $e->getMessage() );
        }
    }
}