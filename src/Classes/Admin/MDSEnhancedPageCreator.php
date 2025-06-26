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

use WP_Error;
use MillionDollarScript\Classes\Data\MDSPageMetadataManager;
use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

/**
 * Enhanced page creator with user choices and templates
 */
class MDSEnhancedPageCreator {
    
    private MDSPageMetadataManager $metadata_manager;
    
    // Page templates for different implementation types
    private array $page_templates = [
        'shortcode' => [
            'grid' => [
                'title' => 'Pixel Grid',
                'content' => '[milliondollarscript type="grid" display="grid" grid_size="100x100"]',
                'description' => 'Classic shortcode-based pixel grid display'
            ],
            'order' => [
                'title' => 'Order Pixels',
                'content' => '[milliondollarscript type="order" display="order"]',
                'description' => 'Shortcode-based order form for pixel purchases'
            ],
            'write-ad' => [
                'title' => 'Write Advertisement',
                'content' => '[milliondollarscript type="write-ad" display="write-ad"]',
                'description' => 'Shortcode-based advertisement creation form'
            ]
        ],
        'block' => [
            'grid' => [
                'title' => 'Pixel Grid (Gutenberg)',
                'content' => '<!-- wp:milliondollarscript/grid-block {"pageType":"grid","gridSize":"100x100"} /-->',
                'description' => 'Modern Gutenberg block-based pixel grid'
            ],
            'order' => [
                'title' => 'Order Pixels (Gutenberg)',
                'content' => '<!-- wp:milliondollarscript/order-block {"pageType":"order"} /-->',
                'description' => 'Modern Gutenberg block-based order form'
            ],
            'write-ad' => [
                'title' => 'Write Advertisement (Gutenberg)',
                'content' => '<!-- wp:milliondollarscript/write-ad-block {"pageType":"write-ad"} /-->',
                'description' => 'Modern Gutenberg block-based ad creation'
            ]
        ],
        'hybrid' => [
            'grid' => [
                'title' => 'Pixel Grid (Hybrid)',
                'content' => '<!-- wp:milliondollarscript/grid-block {"pageType":"grid","fallback":"shortcode"} /-->' . "\n\n" . 
                           '[milliondollarscript type="grid" display="grid"]',
                'description' => 'Hybrid implementation with block and shortcode fallback'
            ]
        ]
    ];
    
    // Configuration options for different page types
    private array $page_configurations = [
        'grid' => [
            'grid_size' => [
                'type' => 'select',
                'label' => 'Grid Size',
                'options' => [
                    '100x100' => '100x100 pixels (10,000 total)',
                    '50x50' => '50x50 pixels (2,500 total)',
                    '200x50' => '200x50 pixels (10,000 total)',
                    'custom' => 'Custom size'
                ],
                'default' => '100x100'
            ],
            'pixel_price' => [
                'type' => 'number',
                'label' => 'Price per Pixel ($)',
                'default' => 1.00,
                'min' => 0.01,
                'step' => 0.01
            ],
            'show_available_count' => [
                'type' => 'checkbox',
                'label' => 'Show available pixel count',
                'default' => true
            ],
            'enable_hover_preview' => [
                'type' => 'checkbox',
                'label' => 'Enable hover preview',
                'default' => true
            ]
        ],
        'order' => [
            'payment_methods' => [
                'type' => 'multiselect',
                'label' => 'Payment Methods',
                'options' => [
                    'paypal' => 'PayPal',
                    'stripe' => 'Stripe (Credit Cards)',
                    'bank_transfer' => 'Bank Transfer',
                    'crypto' => 'Cryptocurrency'
                ],
                'default' => ['paypal', 'stripe']
            ],
            'require_account' => [
                'type' => 'checkbox',
                'label' => 'Require user account',
                'default' => false
            ],
            'min_order_amount' => [
                'type' => 'number',
                'label' => 'Minimum order amount ($)',
                'default' => 10.00,
                'min' => 1.00,
                'step' => 1.00
            ]
        ],
        'write-ad' => [
            'max_ad_length' => [
                'type' => 'number',
                'label' => 'Maximum ad content length',
                'default' => 500,
                'min' => 50,
                'step' => 10
            ],
            'allow_html' => [
                'type' => 'checkbox',
                'label' => 'Allow HTML in ad content',
                'default' => false
            ],
            'require_moderation' => [
                'type' => 'checkbox',
                'label' => 'Require ad moderation',
                'default' => true
            ]
        ]
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->metadata_manager = MDSPageMetadataManager::getInstance();
    }
    
    /**
     * Get available page creation options
     *
     * @return array
     */
    public function getPageCreationOptions(): array {
        return [
            'implementation_types' => [
                'shortcode' => [
                    'label' => 'Shortcode Implementation',
                    'description' => 'Classic shortcode-based pages (compatible with all themes)',
                    'pros' => [
                        'Universal theme compatibility',
                        'Lightweight and fast',
                        'Easy to customize with hooks',
                        'Works in any content area'
                    ],
                    'cons' => [
                        'Less visual editing',
                        'Requires shortcode knowledge for customization'
                    ]
                ],
                'block' => [
                    'label' => 'Gutenberg Blocks',
                    'description' => 'Modern block-based pages (requires Gutenberg)',
                    'pros' => [
                        'Visual editing interface',
                        'Better content management',
                        'Future-proof WordPress standard',
                        'Rich customization options'
                    ],
                    'cons' => [
                        'Requires Gutenberg support',
                        'May not work with all themes'
                    ]
                ],
                'hybrid' => [
                    'label' => 'Hybrid Implementation',
                    'description' => 'Blocks with shortcode fallback (best of both worlds)',
                    'pros' => [
                        'Maximum compatibility',
                        'Visual editing when supported',
                        'Automatic fallback',
                        'Future-proof'
                    ],
                    'cons' => [
                        'Slightly larger page size',
                        'More complex structure'
                    ]
                ]
            ],
            'page_types' => $this->getAvailablePageTypes(),
            'templates' => $this->page_templates,
            'configurations' => $this->page_configurations
        ];
    }
    
    /**
     * Get available page types with descriptions
     *
     * @return array
     */
    public function getAvailablePageTypes(): array {
        return [
            'grid' => [
                'label' => 'Pixel Grid',
                'description' => 'Main advertising grid where users can purchase pixels',
                'icon' => 'grid-view',
                'required' => true
            ],
            'order' => [
                'label' => 'Order Page',
                'description' => 'Purchase form for buying pixels and placing ads',
                'icon' => 'cart',
                'required' => true
            ],
            'write-ad' => [
                'label' => 'Write Advertisement',
                'description' => 'Form for creating advertisement content',
                'icon' => 'edit',
                'required' => true
            ],
            'confirm-order' => [
                'label' => 'Order Confirmation',
                'description' => 'Confirmation page after order submission',
                'icon' => 'yes-alt',
                'required' => false
            ],
            'payment' => [
                'label' => 'Payment Processing',
                'description' => 'Payment gateway integration page',
                'icon' => 'money-alt',
                'required' => false
            ],
            'manage' => [
                'label' => 'Manage Ads',
                'description' => 'User dashboard for managing advertisements',
                'icon' => 'admin-users',
                'required' => false
            ],
            'thank-you' => [
                'label' => 'Thank You',
                'description' => 'Success page after completed purchase',
                'icon' => 'heart',
                'required' => false
            ],
            'list' => [
                'label' => 'Advertiser List',
                'description' => 'Directory of all advertisers',
                'icon' => 'list-view',
                'required' => false
            ],
            'upload' => [
                'label' => 'File Upload',
                'description' => 'Page for uploading advertisement images',
                'icon' => 'upload',
                'required' => false
            ],
            'no-orders' => [
                'label' => 'No Orders',
                'description' => 'Empty state when user has no orders',
                'icon' => 'info',
                'required' => false
            ]
        ];
    }
    
    /**
     * Create pages based on user selections
     *
     * @param array $selections User's page creation selections
     * @return array Creation results
     */
    public function createPagesFromSelections( array $selections ): array {
        $results = [
            'created_pages' => [],
            'updated_pages' => [],
            'errors' => [],
            'total_created' => 0,
            'total_updated' => 0,
            'total_errors' => 0
        ];
        
        // Validate selections
        $validation_result = $this->validateSelections( $selections );
        if ( is_wp_error( $validation_result ) ) {
            $results['errors'][] = $validation_result->get_error_message();
            return $results;
        }
        
        $implementation_type = $selections['implementation_type'] ?? 'shortcode';
        $selected_pages = $selections['page_types'] ?? [];
        $configurations = $selections['configurations'] ?? [];
        $create_mode = $selections['create_mode'] ?? 'create_new'; // create_new, update_existing, skip_existing
        
        foreach ( $selected_pages as $page_type ) {
            try {
                $page_result = $this->createSinglePage(
                    $page_type,
                    $implementation_type,
                    $configurations[$page_type] ?? [],
                    $create_mode
                );
                
                if ( is_wp_error( $page_result ) ) {
                    $results['errors'][] = sprintf(
                        Language::get( 'Failed to create %s page: %s' ),
                        $page_type,
                        $page_result->get_error_message()
                    );
                    $results['total_errors']++;
                } else {
                    if ( $page_result['action'] === 'created' ) {
                        $results['created_pages'][] = $page_result;
                        $results['total_created']++;
                    } else {
                        $results['updated_pages'][] = $page_result;
                        $results['total_updated']++;
                    }
                }
                
            } catch ( Exception $e ) {
                $results['errors'][] = sprintf(
                    Language::get( 'Exception creating %s page: %s' ),
                    $page_type,
                    $e->getMessage()
                );
                $results['total_errors']++;
            }
        }
        
        // Update WordPress options with new page assignments
        $this->updatePageAssignments( $results['created_pages'], $results['updated_pages'] );
        
        return $results;
    }
    
    /**
     * Create a single page
     *
     * @param string $page_type
     * @param string $implementation_type
     * @param array $configuration
     * @param string $create_mode
     * @return array|WP_Error
     */
    private function createSinglePage( string $page_type, string $implementation_type, array $configuration, string $create_mode ) {
        // Check if page already exists
        $existing_page_id = $this->getExistingPageId( $page_type );
        
        if ( $existing_page_id && $create_mode === 'skip_existing' ) {
            return [
                'action' => 'skipped',
                'page_type' => $page_type,
                'page_id' => $existing_page_id,
                'message' => Language::get( 'Page already exists, skipped' )
            ];
        }
        
        // Get template
        $template = $this->getPageTemplate( $page_type, $implementation_type );
        if ( !$template ) {
            return new WP_Error( 'template_not_found', Language::get( 'Template not found for page type' ) );
        }
        
        // Generate page content
        $content = $this->generatePageContent( $template, $configuration );
        $title = $this->generatePageTitle( $page_type, $configuration );
        
        // Create or update page
        $page_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
            'meta_input' => [
                '_mds_page_type' => $page_type,
                '_mds_implementation_type' => $implementation_type,
                '_mds_configuration' => wp_json_encode( $configuration ),
                '_mds_created_by' => 'enhanced_creator',
                '_mds_created_at' => current_time( 'mysql' )
            ]
        ];
        
        if ( $existing_page_id && $create_mode === 'update_existing' ) {
            $page_data['ID'] = $existing_page_id;
            $page_id = wp_update_post( $page_data );
            $action = 'updated';
        } else {
            $page_id = wp_insert_post( $page_data );
            $action = 'created';
        }
        
        if ( is_wp_error( $page_id ) ) {
            return $page_id;
        }
        
        // Create metadata
        $metadata_result = $this->metadata_manager->createOrUpdateMetadata(
            $page_id,
            $page_type,
            'enhanced_creator',
            [
                'content_type' => $implementation_type,
                'page_config' => $configuration,
                'creation_source' => 'enhanced_page_creator'
            ]
        );
        
        if ( is_wp_error( $metadata_result ) ) {
            // Page was created but metadata failed - log warning but don't fail
            error_log( 'MDS: Failed to create metadata for page ' . $page_id . ': ' . $metadata_result->get_error_message() );
        }
        
        return [
            'action' => $action,
            'page_type' => $page_type,
            'page_id' => $page_id,
            'page_title' => $title,
            'page_url' => get_permalink( $page_id ),
            'implementation_type' => $implementation_type,
            'configuration' => $configuration
        ];
    }
    
    /**
     * Get existing page ID for page type
     *
     * @param string $page_type
     * @return int|null
     */
    private function getExistingPageId( string $page_type ): ?int {
        $option_key = $this->getOptionKeyForPageType( $page_type );
        if ( $option_key ) {
            $page_id = get_option( $option_key );
            if ( $page_id && get_post( $page_id ) ) {
                return intval( $page_id );
            }
        }
        
        return null;
    }
    
    /**
     * Get option key for page type
     *
     * @param string $page_type
     * @return string|null
     */
    private function getOptionKeyForPageType( string $page_type ): ?string {
        $mapping = [
            'grid' => '_mds_grid-page',
            'order' => '_mds_users-order-page',
            'write-ad' => '_mds_users-write-ad-page',
            'confirm-order' => '_mds_users-confirm-order-page',
            'payment' => '_mds_users-payment-page',
            'manage' => '_mds_users-manage-page',
            'thank-you' => '_mds_users-thank-you-page',
            'list' => '_mds_users-list-page',
            'upload' => '_mds_users-upload-page',
            'no-orders' => '_mds_users-no-orders-page'
        ];
        
        return $mapping[$page_type] ?? null;
    }
    
    /**
     * Get page template
     *
     * @param string $page_type
     * @param string $implementation_type
     * @return array|null
     */
    private function getPageTemplate( string $page_type, string $implementation_type ): ?array {
        return $this->page_templates[$implementation_type][$page_type] ?? null;
    }
    
    /**
     * Generate page content from template and configuration
     *
     * @param array $template
     * @param array $configuration
     * @return string
     */
    private function generatePageContent( array $template, array $configuration ): string {
        $content = $template['content'];
        
        // Replace configuration placeholders
        foreach ( $configuration as $key => $value ) {
            $placeholder = '{' . $key . '}';
            if ( is_array( $value ) ) {
                $value = implode( ',', $value );
            }
            $content = str_replace( $placeholder, $value, $content );
        }
        
        // Add configuration as HTML comments for reference
        if ( !empty( $configuration ) ) {
            $config_comment = '<!-- MDS Configuration: ' . wp_json_encode( $configuration ) . ' -->';
            $content = $config_comment . "\n\n" . $content;
        }
        
        return $content;
    }
    
    /**
     * Generate page title
     *
     * @param string $page_type
     * @param array $configuration
     * @return string
     */
    private function generatePageTitle( string $page_type, array $configuration ): string {
        $page_types = $this->getAvailablePageTypes();
        $base_title = $page_types[$page_type]['label'] ?? ucfirst( str_replace( '-', ' ', $page_type ) );
        
        // Customize title based on configuration
        if ( !empty( $configuration['custom_title'] ) ) {
            return sanitize_text_field( $configuration['custom_title'] );
        }
        
        return $base_title;
    }
    
    /**
     * Validate user selections
     *
     * @param array $selections
     * @return bool|WP_Error
     */
    private function validateSelections( array $selections ): bool|WP_Error {
        // Check implementation type
        if ( empty( $selections['implementation_type'] ) ) {
            return new WP_Error( 'missing_implementation_type', Language::get( 'Implementation type is required' ) );
        }
        
        $valid_types = [ 'shortcode', 'block', 'hybrid' ];
        if ( !in_array( $selections['implementation_type'], $valid_types ) ) {
            return new WP_Error( 'invalid_implementation_type', Language::get( 'Invalid implementation type' ) );
        }
        
        // Check page types
        if ( empty( $selections['page_types'] ) || !is_array( $selections['page_types'] ) ) {
            return new WP_Error( 'missing_page_types', Language::get( 'At least one page type must be selected' ) );
        }
        
        $available_types = array_keys( $this->getAvailablePageTypes() );
        foreach ( $selections['page_types'] as $page_type ) {
            if ( !in_array( $page_type, $available_types ) ) {
                return new WP_Error( 'invalid_page_type', sprintf( Language::get( 'Invalid page type: %s' ), $page_type ) );
            }
        }
        
        // Validate configurations
        if ( !empty( $selections['configurations'] ) ) {
            foreach ( $selections['configurations'] as $page_type => $config ) {
                $validation_result = $this->validatePageConfiguration( $page_type, $config );
                if ( is_wp_error( $validation_result ) ) {
                    return $validation_result;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Validate page configuration
     *
     * @param string $page_type
     * @param array $configuration
     * @return bool|WP_Error
     */
    private function validatePageConfiguration( string $page_type, array $configuration ): bool|WP_Error {
        $config_schema = $this->page_configurations[$page_type] ?? [];
        
        foreach ( $configuration as $key => $value ) {
            if ( !isset( $config_schema[$key] ) ) {
                continue; // Allow unknown configuration keys
            }
            
            $field_config = $config_schema[$key];
            
            // Type validation
            switch ( $field_config['type'] ) {
                case 'number':
                    if ( !is_numeric( $value ) ) {
                        return new WP_Error( 'invalid_number', sprintf( Language::get( 'Invalid number for %s' ), $key ) );
                    }
                    
                    if ( isset( $field_config['min'] ) && $value < $field_config['min'] ) {
                        return new WP_Error( 'number_too_small', sprintf( Language::get( 'Value for %s is too small' ), $key ) );
                    }
                    
                    if ( isset( $field_config['max'] ) && $value > $field_config['max'] ) {
                        return new WP_Error( 'number_too_large', sprintf( Language::get( 'Value for %s is too large' ), $key ) );
                    }
                    break;
                    
                case 'select':
                    if ( !isset( $field_config['options'][$value] ) ) {
                        return new WP_Error( 'invalid_option', sprintf( Language::get( 'Invalid option for %s' ), $key ) );
                    }
                    break;
                    
                case 'multiselect':
                    if ( !is_array( $value ) ) {
                        return new WP_Error( 'invalid_multiselect', sprintf( Language::get( 'Invalid multiselect for %s' ), $key ) );
                    }
                    
                    foreach ( $value as $option ) {
                        if ( !isset( $field_config['options'][$option] ) ) {
                            return new WP_Error( 'invalid_multiselect_option', sprintf( Language::get( 'Invalid option %s for %s' ), $option, $key ) );
                        }
                    }
                    break;
                    
                case 'checkbox':
                    // Convert to boolean
                    $configuration[$key] = (bool) $value;
                    break;
            }
        }
        
        return true;
    }
    
    /**
     * Update WordPress page assignments
     *
     * @param array $created_pages
     * @param array $updated_pages
     * @return void
     */
    private function updatePageAssignments( array $created_pages, array $updated_pages ): void {
        $all_pages = array_merge( $created_pages, $updated_pages );
        
        foreach ( $all_pages as $page_info ) {
            $option_key = $this->getOptionKeyForPageType( $page_info['page_type'] );
            if ( $option_key ) {
                update_option( $option_key, $page_info['page_id'] );
            }
        }
    }
    
    /**
     * Get page creation preview
     *
     * @param array $selections
     * @return array
     */
    public function getCreationPreview( array $selections ): array {
        $preview = [
            'pages_to_create' => [],
            'estimated_time' => 0,
            'warnings' => [],
            'recommendations' => []
        ];
        
        $implementation_type = $selections['implementation_type'] ?? 'shortcode';
        $selected_pages = $selections['page_types'] ?? [];
        $configurations = $selections['configurations'] ?? [];
        
        foreach ( $selected_pages as $page_type ) {
            $existing_page_id = $this->getExistingPageId( $page_type );
            $template = $this->getPageTemplate( $page_type, $implementation_type );
            
            $page_preview = [
                'page_type' => $page_type,
                'title' => $this->generatePageTitle( $page_type, $configurations[$page_type] ?? [] ),
                'implementation_type' => $implementation_type,
                'template_description' => $template['description'] ?? '',
                'existing_page' => $existing_page_id ? get_permalink( $existing_page_id ) : null,
                'configuration' => $configurations[$page_type] ?? []
            ];
            
            $preview['pages_to_create'][] = $page_preview;
            
            // Add warnings for existing pages
            if ( $existing_page_id ) {
                $preview['warnings'][] = sprintf(
                    Language::get( 'Page %s already exists and will be updated/skipped based on your settings' ),
                    $page_type
                );
            }
        }
        
        // Estimate creation time (rough calculation)
        $preview['estimated_time'] = count( $selected_pages ) * 2; // 2 seconds per page
        
        // Add recommendations
        if ( $implementation_type === 'block' && !function_exists( 'has_blocks' ) ) {
            $preview['recommendations'][] = Language::get( 'Consider using shortcode implementation for better compatibility with your WordPress version' );
        }
        
        if ( in_array( 'grid', $selected_pages ) && !in_array( 'order', $selected_pages ) ) {
            $preview['recommendations'][] = Language::get( 'Consider creating an Order page to allow pixel purchases' );
        }
        
        return $preview;
    }
} 