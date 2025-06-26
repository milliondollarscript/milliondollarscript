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
            'mds-page-creator',
            MDS_BASE_URL . 'assets/js/admin/page-creator.js',
            [ 'jquery', 'wp-util' ],
            MDS_VERSION,
            true
        );
        
        wp_enqueue_style(
            'mds-page-creator',
            MDS_BASE_URL . 'assets/css/admin/page-creator.css',
            [],
            MDS_VERSION
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
                                
                                <div class="mds-implementation-option">
                                    <label class="mds-radio-card">
                                        <input type="radio" name="implementation_type" value="hybrid" required>
                                        <div class="mds-card-content">
                                            <h3><?php echo esc_html( Language::get( 'Hybrid Implementation' ) ); ?></h3>
                                            <p><?php echo esc_html( Language::get( 'Best of both worlds: Gutenberg blocks with shortcode fallback.' ) ); ?></p>
                                            
                                            <div class="mds-pros-cons">
                                                <div class="mds-pros">
                                                    <strong><?php echo esc_html( Language::get( 'Pros:' ) ); ?></strong>
                                                    <ul>
                                                        <li><?php echo esc_html( Language::get( 'Maximum compatibility' ) ); ?></li>
                                                        <li><?php echo esc_html( Language::get( 'Future-proof' ) ); ?></li>
                                                        <li><?php echo esc_html( Language::get( 'Flexible usage' ) ); ?></li>
                                                    </ul>
                                                </div>
                                                <div class="mds-cons">
                                                    <strong><?php echo esc_html( Language::get( 'Cons:' ) ); ?></strong>
                                                    <ul>
                                                        <li><?php echo esc_html( Language::get( 'Larger file size' ) ); ?></li>
                                                        <li><?php echo esc_html( Language::get( 'More complex setup' ) ); ?></li>
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
                                <?php echo esc_html( Language::get( 'Choose which MDS pages you want to create. Required pages are pre-selected.' ) ); ?>
                            </p>
                            
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
                        
                        <!-- Step 4: Preview and Create -->
                        <div class="mds-creator-step inactive" data-step="4">
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
            </div>
            
            <!-- Results Panel -->
            <div id="mds-results-panel" class="mds-results-panel" style="display: none;">
                <h3><?php echo esc_html( Language::get( 'Creation Results' ) ); ?></h3>
                <div id="mds-results-content">
                    <!-- Results content loaded via AJAX -->
                </div>
            </div>
        </div>
        <?php
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
                'required' => true
            ],
            'order' => [
                'label' => Language::get( 'Order Page' ),
                'description' => Language::get( 'Page for ordering pixels' ),
                'icon' => 'dashicons-cart',
                'required' => true
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
            'configurations' => $_POST['configurations'] ?? []
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
        
        $result = $this->enhanced_creator->createPages( $form_data );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        } else {
            wp_send_json_success( $result );
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
                <fieldset>
                    <?php foreach ( $field_config['options'] ?? [] as $value => $label ): ?>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( $field_name_attr ); ?>[]" 
                                   value="<?php echo esc_attr( $value ); ?>">
                            <?php echo esc_html( $label ); ?>
                        </label><br>
                    <?php endforeach; ?>
                </fieldset>
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
                
            default:
                ?>
                <input type="text" id="<?php echo esc_attr( $field_id ); ?>" 
                       name="<?php echo esc_attr( $field_name_attr ); ?>"
                       value="<?php echo esc_attr( $default_value ); ?>"
                       class="regular-text">
                <?php
        }
    }
}