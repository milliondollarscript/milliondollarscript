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

use MillionDollarScript\Classes\Pages\Logs;
use WP_Error;
use MillionDollarScript\Classes\Data\MDSPageMetadataManager;
use MillionDollarScript\Classes\Data\MDSPageDetectionEngine;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\System\Logs as MDSLogs;

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
                'content' => '[milliondollarscript type="grid" display="grid" id="{grid_id}" width="{width}" height="{height}" align="{align}"]',
                'description' => 'Classic shortcode-based pixel grid display'
            ],
            'order' => [
                'title' => 'Order Pixels',
                'content' => '[milliondollarscript type="order" display="order" id="{grid_id}" align="{align}"]',
                'description' => 'Shortcode-based order form for pixel purchases'
            ],
            'write-ad' => [
                'title' => 'Write Content',
                'content' => '[milliondollarscript type="write-ad" display="write-ad" align="{align}"]',
                'description' => 'Shortcode-based content creation form'
            ],
            'confirm-order' => [
                'title' => 'Order Confirmation',
                'content' => '[milliondollarscript type="confirm-order" display="confirm-order" align="{align}"]',
                'description' => 'Shortcode-based order confirmation page'
            ],
            'payment' => [
                'title' => 'Payment Processing',
                'content' => '[milliondollarscript type="payment" display="payment" align="{align}"]',
                'description' => 'Shortcode-based payment processing page'
            ],
            'manage' => [
                'title' => 'Manage Pixels',
                'content' => '[milliondollarscript type="manage" display="manage" align="{align}"]',
                'description' => 'Shortcode-based pixel management page'
            ],
            'thank-you' => [
                'title' => 'Thank You',
                'content' => '[milliondollarscript type="thank-you" display="thank-you" align="{align}"]',
                'description' => 'Shortcode-based thank you page'
            ],
            'list' => [
                'title' => 'Advertiser List',
                'content' => '[milliondollarscript type="list" display="list" align="{align}"]',
                'description' => 'Shortcode-based advertiser list page'
            ],
            'upload' => [
                'title' => 'File Upload',
                'content' => '[milliondollarscript type="upload" display="upload" align="{align}"]',
                'description' => 'Shortcode-based file upload page'
            ],
            'no-orders' => [
                'title' => 'No Orders',
                'content' => '[milliondollarscript type="no-orders" display="no-orders" align="{align}"]',
                'description' => 'Shortcode-based no orders page'
            ],
            'stats' => [
                'title' => 'Stats Box',
                'content' => '[milliondollarscript type="stats" id="{grid_id}" width="150px" height="60px" align="{align}"]',
                'description' => 'Shortcode-based statistics display (150px × 60px)'
            ]
        ],
        'block' => [
            'grid' => [
                'title' => 'Pixel Grid',
                'content' => '<!-- wp:carbon-fields/million-dollar-script {"data":{"milliondollarscript_type":"grid","milliondollarscript_id":"{grid_id}","milliondollarscript_align":"{align}","milliondollarscript_width":"{width}","milliondollarscript_height":"{height}"}} /-->',
                'description' => 'Modern Gutenberg block-based pixel grid'
            ],
            'order' => [
                'title' => 'Order Pixels',
                'content' => '<!-- wp:carbon-fields/million-dollar-script {"data":{"milliondollarscript_type":"order","milliondollarscript_id":"{grid_id}","milliondollarscript_align":"{align}","milliondollarscript_width":"100%","milliondollarscript_height":"auto"}} /-->',
                'description' => 'Modern Gutenberg block-based order form'
            ],
            'write-ad' => [
                'title' => 'Write Content',
                'content' => '<!-- wp:carbon-fields/million-dollar-script {"data":{"milliondollarscript_type":"write-ad","milliondollarscript_id":"{grid_id}","milliondollarscript_align":"{align}","milliondollarscript_width":"100%","milliondollarscript_height":"auto"}} /-->',
                'description' => 'Modern Gutenberg block-based content creation'
            ],
            'confirm-order' => [
                'title' => 'Order Confirmation',
                'content' => '<!-- wp:carbon-fields/million-dollar-script {"data":{"milliondollarscript_type":"confirm-order","milliondollarscript_id":"{grid_id}","milliondollarscript_align":"{align}","milliondollarscript_width":"100%","milliondollarscript_height":"auto"}} /-->',
                'description' => 'Modern Gutenberg block-based confirmation page'
            ],
            'payment' => [
                'title' => 'Payment Processing',
                'content' => '<!-- wp:carbon-fields/million-dollar-script {"data":{"milliondollarscript_type":"payment","milliondollarscript_id":"{grid_id}","milliondollarscript_align":"{align}","milliondollarscript_width":"100%","milliondollarscript_height":"auto"}} /-->',
                'description' => 'Modern Gutenberg block-based payment page'
            ],
            'manage' => [
                'title' => 'Manage Pixels',
                'content' => '<!-- wp:carbon-fields/million-dollar-script {"data":{"milliondollarscript_type":"manage","milliondollarscript_id":"{grid_id}","milliondollarscript_align":"{align}","milliondollarscript_width":"100%","milliondollarscript_height":"auto"}} /-->',
                'description' => 'Modern Gutenberg block-based pixel management page'
            ],
            'thank-you' => [
                'title' => 'Thank You',
                'content' => '<!-- wp:carbon-fields/million-dollar-script {"data":{"milliondollarscript_type":"thank-you","milliondollarscript_id":"{grid_id}","milliondollarscript_align":"{align}","milliondollarscript_width":"100%","milliondollarscript_height":"auto"}} /-->',
                'description' => 'Modern Gutenberg block-based thank you page'
            ],
            'list' => [
                'title' => 'Advertiser List',
                'content' => '<!-- wp:carbon-fields/million-dollar-script {"data":{"milliondollarscript_type":"list","milliondollarscript_id":"{grid_id}","milliondollarscript_align":"{align}","milliondollarscript_width":"100%","milliondollarscript_height":"auto"}} /-->',
                'description' => 'Modern Gutenberg block-based list page'
            ],
            'upload' => [
                'title' => 'File Upload',
                'content' => '<!-- wp:carbon-fields/million-dollar-script {"data":{"milliondollarscript_type":"upload","milliondollarscript_id":"{grid_id}","milliondollarscript_align":"{align}","milliondollarscript_width":"100%","milliondollarscript_height":"auto"}} /-->',
                'description' => 'Modern Gutenberg block-based upload page'
            ],
            'no-orders' => [
                'title' => 'No Orders',
                'content' => '<!-- wp:carbon-fields/million-dollar-script {"data":{"milliondollarscript_type":"no-orders","milliondollarscript_id":"{grid_id}","milliondollarscript_align":"{align}","milliondollarscript_width":"100%","milliondollarscript_height":"auto"}} /-->',
                'description' => 'Modern Gutenberg block-based no orders page'
            ],
            'stats' => [
                'title' => 'Stats Box',
                'content' => '<!-- wp:carbon-fields/million-dollar-script {"data":{"milliondollarscript_type":"stats","milliondollarscript_id":"{grid_id}","milliondollarscript_align":"{align}","milliondollarscript_width":"150px","milliondollarscript_height":"60px"}} /-->',
                'description' => 'Modern Gutenberg block-based statistics display (150px × 60px)'
            ]
        ]
    ];
    
    // Configuration options for different page types
    private array $page_configurations = [
        'grid' => [
            'grid_info' => [
                'type' => 'dynamic_grid_info',
                'label' => 'Grid Configuration',
                'description' => 'Grid dimensions, pricing, and settings from your current grid'
            ],
            'align' => [
                'type' => 'select',
                'label' => 'Alignment',
                'description' => 'How to align the grid on the page',
                'default' => 'center',
                'options' => [
                    'left' => 'Left',
                    'center' => 'Center', 
                    'right' => 'Right'
                ]
            ],
            'include_stats_above' => [
                'type' => 'checkbox',
                'label' => 'Include stats box above grid',
                'description' => 'Adds a stats box (150px × 60px) above the pixel grid on the same page',
                'default' => true
            ]
        ],
        'order' => [
            'align' => [
                'type' => 'select',
                'label' => 'Alignment',
                'description' => 'How to align the order form on the page',
                'default' => 'center',
                'options' => [
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right'
                ]
            ]
        ],
        'write-ad' => [
            'align' => [
                'type' => 'select',
                'label' => 'Alignment',
                'description' => 'How to align the write ad form on the page',
                'default' => 'center',
                'options' => [
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right'
                ]
            ]
        ],
        'confirm-order' => [
            'align' => [
                'type' => 'select',
                'label' => 'Alignment',
                'description' => 'How to align the confirmation form on the page',
                'default' => 'center',
                'options' => [
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right'
                ]
            ]
        ],
        'payment' => [
            'align' => [
                'type' => 'select',
                'label' => 'Alignment',
                'description' => 'How to align the payment form on the page',
                'default' => 'center',
                'options' => [
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right'
                ]
            ]
        ],
        'manage' => [
            'align' => [
                'type' => 'select',
                'label' => 'Alignment',
                'description' => 'How to align the manage interface on the page',
                'default' => 'center',
                'options' => [
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right'
                ]
            ]
        ],
        'thank-you' => [
            'align' => [
                'type' => 'select',
                'label' => 'Alignment',
                'description' => 'How to align the thank you message on the page',
                'default' => 'center',
                'options' => [
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right'
                ]
            ]
        ],
        'list' => [
            'align' => [
                'type' => 'select',
                'label' => 'Alignment',
                'description' => 'How to align the advertiser list on the page',
                'default' => 'center',
                'options' => [
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right'
                ]
            ]
        ],
        'upload' => [
            'align' => [
                'type' => 'select',
                'label' => 'Alignment',
                'description' => 'How to align the upload form on the page',
                'default' => 'center',
                'options' => [
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right'
                ]
            ]
        ],
        'no-orders' => [
            'align' => [
                'type' => 'select',
                'label' => 'Alignment',
                'description' => 'How to align the no orders message on the page',
                'default' => 'center',
                'options' => [
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right'
                ]
            ]
        ],
        'stats' => [
            'align' => [
                'type' => 'select',
                'label' => 'Alignment',
                'description' => 'How to align the stats box on the page',
                'default' => 'center',
                'options' => [
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right'
                ]
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
                'label' => 'Write Content',
                'description' => 'Form for creating content',
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
                'label' => 'Manage Pixels',
                'description' => 'User dashboard for managing pixels',
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
            ],
            'stats' => [
                'label' => 'Statistics Box',
                'description' => 'Displays pixel grid statistics (150px × 60px)',
                'icon' => 'chart-bar',
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
            'total_errors' => 0,
            'debug_info' => []
        ];
        
        try {
            // Log incoming selections for debugging
            
            // Validate selections
            $validation_result = $this->validateSelections( $selections );
            if ( is_wp_error( $validation_result ) ) {
                $error_msg = $validation_result->get_error_message();
                $results['errors'][] = $error_msg;
                return $results;
            }
            
            $implementation_type = $selections['implementation_type'] ?? 'shortcode';
            $selected_pages = $selections['page_types'] ?? [];
            $configurations = $selections['configurations'] ?? [];
            $create_mode = $selections['create_mode'] ?? 'create_new'; // create_new, update_existing, skip_existing
            $selected_grid_id = $selections['selected_grid_id'] ?? 1;
            
            // Inject selected grid ID into all page configurations
            foreach ( $selected_pages as $page_type ) {
                if ( !isset( $configurations[$page_type] ) ) {
                    $configurations[$page_type] = [];
                }
                $configurations[$page_type]['grid_id'] = $selected_grid_id;
            }
            
            
            foreach ( $selected_pages as $page_type ) {
                try {
                    
                    $page_result = $this->createSinglePage(
                        $page_type,
                        $implementation_type,
                        $configurations[$page_type] ?? [],
                        $create_mode
                    );
                    
                    if ( is_wp_error( $page_result ) ) {
                        $error_msg = sprintf(
                            Language::get( 'Failed to create %s page: %s' ),
                            $page_type,
                            $page_result->get_error_message()
                        );
                        $results['errors'][] = $error_msg;
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
                    
                } catch ( \Exception $e ) {
                    $error_msg = sprintf(
                        Language::get( 'Exception creating %s page: %s' ),
                        $page_type,
                        $e->getMessage()
                    );
                    $results['errors'][] = $error_msg;
                    $results['total_errors']++;
                }
            }
            
            // Update WordPress options with new page assignments
            if ( !empty( $results['created_pages'] ) || !empty( $results['updated_pages'] ) ) {
                $this->updatePageAssignments( $results['created_pages'], $results['updated_pages'] );
            }
            
        } catch ( \Exception $e ) {
            $error_msg = 'Fatal error in page creation: ' . $e->getMessage();
            $results['errors'][] = $error_msg;
            $results['total_errors']++;
        }
        
        
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
        $content = $this->generatePageContent( $template, $configuration, $page_type );
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
            MDSLogs::log( 'MDS: Failed to create metadata for page ' . $page_id . ': ' . $metadata_result->get_error_message() );
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
            'no-orders' => '_mds_users-no-orders-page',
            'stats' => '_mds_users-stats-page'
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
     * @param string $page_type
     * @return string
     */
    private function generatePageContent( array $template, array $configuration, string $page_type ): string {
        $content = $template['content'];
        
        
        // Extract grid dimensions from grid_info if available
        if ( isset( $configuration['grid_info'] ) ) {
            $grid_info = $configuration['grid_info'];
            if ( is_string( $grid_info ) ) {
                $grid_data = json_decode( $grid_info, true );
                if ( $grid_data ) {
                    // Add grid dimensions to configuration
                    $configuration['grid_id'] = $grid_data['grid_id'] ?? 1;
                    $configuration['width'] = $grid_data['pixels']['width'] ?? 1000;
                    $configuration['height'] = $grid_data['pixels']['height'] ?? 1000;
                }
            } elseif ( is_array( $grid_info ) ) {
                // Grid info is already decoded
                $configuration['grid_id'] = $grid_info['grid_id'] ?? 1;
                $configuration['width'] = $grid_info['pixels']['width'] ?? 1000;
                $configuration['height'] = $grid_info['pixels']['height'] ?? 1000;
            }
        }
        
        // For grid pages, ensure we have actual dimensions from database if no grid_info
        if ( $page_type === 'grid' && ( !isset( $configuration['width'] ) || !isset( $configuration['height'] ) || $configuration['width'] === '{width}' || $configuration['height'] === '{height}' ) ) {
            $grid_id = $configuration['grid_id'] ?? 1;
            $calculated_dimensions = $this->calculateGridDimensions( $grid_id );
            if ( $calculated_dimensions ) {
                $configuration['width'] = $calculated_dimensions['width'];
                $configuration['height'] = $calculated_dimensions['height'];
            } else {
                // Fallback to defaults if calculation fails
                $configuration['width'] = '1000px';
                $configuration['height'] = '1000px';
            }
        }
        
        // Handle combined grid + stats option - ONLY for grid pages
        if ( $page_type === 'grid' && !empty( $configuration['include_stats_above'] ) ) {
            $grid_id = $configuration['grid_id'] ?? 1;
            $align = $configuration['align'] ?? 'center';
            
            
            // Check for shortcode grid first (more specific pattern)
            if ( str_contains( $content, '[milliondollarscript type="grid"' ) ) {
                // Shortcode implementation - ensure we're adding stats to a grid shortcode
                $stats_shortcode = '[milliondollarscript type="stats" id="' . $grid_id . '" width="150px" height="60px" align="' . $align . '"]';
                $content = $stats_shortcode . "\n\n" . $content;
                
            } elseif ( str_contains( $content, '"milliondollarscript_type":"grid"' ) ) {
                // Gutenberg block implementation - ensure we're adding stats to a grid block
                $stats_block = '<!-- wp:carbon-fields/million-dollar-script {"data":{"milliondollarscript_type":"stats","milliondollarscript_id":"' . $grid_id . '","milliondollarscript_width":"150px","milliondollarscript_height":"60px","milliondollarscript_align":"' . $align . '"}} /-->';
                $content = $stats_block . "\n\n" . $content;
                
            } else {
            }
        }
        
        // Replace configuration placeholders
        foreach ( $configuration as $key => $value ) {
            $placeholder = '{' . $key . '}';
            if ( is_array( $value ) ) {
                $value = implode( ',', $value );
            } elseif ( is_bool( $value ) ) {
                // Don't replace boolean placeholder values in content
                continue;
            }
            $content = str_replace( $placeholder, $value, $content );
        }
        
        // Process Gutenberg blocks to ensure proper attributes
        // Only process if we're creating/updating pages, not during frontend rendering
        if ( str_contains( $content, 'wp:carbon-fields/million-dollar-script' ) && ( is_admin() || defined( 'DOING_AJAX' ) ) ) {
            $content = $this->processGutenbergBlocks( $content, $page_type, $configuration );
        }
        
        // Configuration is stored in page metadata, no need for HTML comments
        
        
        return $content;
    }
    
    /**
     * Initialize block content fallback processing
     */
    public static function initBlockFallback(): void {
        // Run at priority 8 to intercept before do_blocks() runs at priority 9
        add_filter( 'the_content', [ self::class, 'processBlockFallback' ], 8 );
    }
    
    /**
     * Process block content fallback - convert Carbon Fields blocks to shortcodes
     * This ensures blocks work correctly even if Carbon Fields has attribute parsing issues
     *
     * @param string $content
     * @return string
     */
    public static function processBlockFallback( string $content ): string {
        // Only process if content contains our Carbon Fields blocks
        if ( !str_contains( $content, 'wp:carbon-fields/million-dollar-script' ) ) {
            return $content;
        }
        
        
        // Pattern to match Carbon Fields blocks with attributes
        $pattern = '/<!-- wp:carbon-fields\/million-dollar-script\s+({[^}]+})\s*\/-->/';
        
        $content = preg_replace_callback( $pattern, function( $matches ) {
            $attributes_json = $matches[1];
            $attributes = json_decode( $attributes_json, true );
            
            if ( !$attributes ) {
                return $matches[0]; // Return original if can't parse
            }
            
            // Extract shortcode parameters from Carbon Fields attributes
            $shortcode_atts = self::convertBlockAttributesToShortcode( $attributes );
            
            
            // Generate shortcode
            $shortcode = sprintf( 
                '[milliondollarscript id="%d" align="%s" width="%s" height="%s" type="%s"]',
                $shortcode_atts['id'],
                $shortcode_atts['align'],
                $shortcode_atts['width'],
                $shortcode_atts['height'],
                $shortcode_atts['type']
            );
            
            return do_shortcode( $shortcode );
        }, $content );
        
        // Also handle blocks without attributes (use defaults)
        $content = preg_replace_callback( 
            '/<!-- wp:carbon-fields\/million-dollar-script\s*\/-->/',
            function( $matches ) {
                
                // Get page metadata to determine the correct page type
                $page_metadata = self::getCurrentPageMetadata();
                $page_type = 'grid'; // default fallback
                $width = '1000px';
                $height = '1000px';
                
                if ( $page_metadata && isset( $page_metadata['page_type'] ) ) {
                    $page_type = $page_metadata['page_type'];
                    
                    // Set appropriate dimensions based on page type
                    switch ( $page_type ) {
                        case 'stats':
                            $width = '150px';
                            $height = '60px';
                            break;
                        case 'grid':
                            $width = '1000px';
                            $height = '1000px';
                            break;
                        default:
                            // order, write-ad, confirm-order, payment, manage, thank-you, list, upload, no-orders
                            $width = '100%';
                            $height = 'auto';
                            break;
                    }
                }
                
                $shortcode = sprintf( 
                    '[milliondollarscript id="1" align="center" width="%s" height="%s" type="%s"]',
                    $width,
                    $height,
                    $page_type
                );
                
                
                return do_shortcode( $shortcode );
            },
            $content
        );
        
        
        return $content;
    }
    
    /**
     * Convert Carbon Fields block attributes to shortcode attributes
     *
     * @param array $block_attributes
     * @return array
     */
    private static function convertBlockAttributesToShortcode( array $block_attributes ): array {
        // Try to get page metadata for intelligent defaults
        $page_config = self::getCurrentPageMetadata();
        
        // Use page metadata as smart defaults if available
        if ( $page_config && isset( $page_config['configuration'] ) ) {
            $config = $page_config['configuration'];
            $shortcode_atts = [
                'id' => $config['id'] ?? 1,
                'align' => $config['align'] ?? 'center',
                'width' => $config['width'] ?? '1000px',
                'height' => $config['height'] ?? '1000px',
                'type' => $config['type'] ?? 'grid'
            ];
        } else {
            // Fallback to hardcoded defaults
            $shortcode_atts = [
                'id' => 1,
                'align' => 'center',
                'width' => '1000px',
                'height' => '1000px',
                'type' => 'grid'
            ];
        }
        
        // Map Carbon Fields attributes to shortcode attributes (override defaults if present)
        $mapping = [
            'milliondollarscript_id' => 'id',
            'milliondollarscript_align' => 'align',
            'milliondollarscript_width' => 'width',
            'milliondollarscript_height' => 'height',
            'milliondollarscript_type' => 'type'
        ];
        
        foreach ( $mapping as $carbon_field => $shortcode_field ) {
            if ( isset( $block_attributes[$carbon_field] ) && !empty( $block_attributes[$carbon_field] ) ) {
                $shortcode_atts[$shortcode_field] = $block_attributes[$carbon_field];
            }
        }
        
        // Ensure numeric values are properly formatted
        $shortcode_atts['id'] = intval( $shortcode_atts['id'] );
        
        return $shortcode_atts;
    }
    
    /**
     * Get current page metadata for block processing context
     *
     * @return array|null Page metadata or null if not available
     */
    private static function getCurrentPageMetadata(): ?array {
        global $post;
        
        // Try to get current post ID from various contexts
        $post_id = null;
        if ( $post && $post->ID ) {
            $post_id = $post->ID;
        } elseif ( isset( $_GET['post'] ) ) {
            $post_id = intval( $_GET['post'] );
        } elseif ( isset( $_POST['post_ID'] ) ) {
            $post_id = intval( $_POST['post_ID'] );
        }
        
        if ( !$post_id ) {
            return null;
        }
        
        // Get page metadata
        $metadata_manager = MDSPageMetadataManager::getInstance();
        $metadata = $metadata_manager->getMetadata( $post_id );
        
        if ( $metadata ) {
            // Extract grid_id from page_config or shortcode/block attributes
            $grid_id = 1; // default
            if ( isset( $metadata->page_config['grid_id'] ) ) {
                $grid_id = $metadata->page_config['grid_id'];
            } elseif ( isset( $metadata->shortcode_attributes['id'] ) ) {
                $grid_id = $metadata->shortcode_attributes['id'];
            } elseif ( isset( $metadata->block_attributes['milliondollarscript_id'] ) ) {
                $grid_id = $metadata->block_attributes['milliondollarscript_id'];
            }
            
            // Use page_config as the configuration, with fallbacks
            $configuration = $metadata->page_config ?? [];
            
            return [
                'is_mds_page' => true,
                'page_type' => $metadata->page_type,
                'grid_id' => $grid_id,
                'configuration' => $configuration,
                'source' => 'metadata'
            ];
        }
        
        // Fallback: Try to detect from content
        $detection_engine = new MDSPageDetectionEngine();
        $detection_result = $detection_engine->detectMDSPage( $post_id );
        
        if ( $detection_result['is_mds_page'] ) {
            // Extract configuration from detection results
            $config = [
                'id' => 1,
                'align' => 'center',
                'width' => '1000px',
                'height' => '1000px',
                'type' => $detection_result['page_type']
            ];
            
            // Extract from patterns if available
            if ( isset( $detection_result['patterns'] ) && is_array( $detection_result['patterns'] ) ) {
                foreach ( $detection_result['patterns'] as $pattern ) {
                    if ( isset( $pattern['attributes'] ) && is_array( $pattern['attributes'] ) ) {
                        $attrs = $pattern['attributes'];
                        
                        // Map common attributes
                        if ( isset( $attrs['id'] ) ) $config['id'] = intval( $attrs['id'] );
                        if ( isset( $attrs['width'] ) ) $config['width'] = $attrs['width'];
                        if ( isset( $attrs['height'] ) ) $config['height'] = $attrs['height'];
                        if ( isset( $attrs['align'] ) ) $config['align'] = $attrs['align'];
                        if ( isset( $attrs['type'] ) ) $config['type'] = $attrs['type'];
                    }
                }
            }
            
            return [
                'is_mds_page' => true,
                'page_type' => $detection_result['page_type'],
                'configuration' => $config,
                'source' => 'detection'
            ];
        }
        
        return null;
    }
    
    /**
     * Process Gutenberg blocks to ensure proper attributes
     *
     * @param string $content
     * @param string $page_type
     * @param array $configuration
     * @return string
     */
    private function processGutenbergBlocks( string $content, string $page_type, array $configuration ): string {
        $grid_id = $configuration['grid_id'] ?? 1;
        $align = $configuration['align'] ?? 'center';
        
        // Generate the correct attributes for this page type
        $correct_attributes = $this->generateBlockAttributes( $page_type, $configuration, $grid_id, $align );
        
        
        // Parse blocks using WordPress function if available
        if ( function_exists( 'parse_blocks' ) ) {
            $blocks = parse_blocks( $content );
            $modified = false;
            
            foreach ( $blocks as &$block ) {
                if ( $block['blockName'] === 'carbon-fields/million-dollar-script' ) {
                    $old_attrs = $block['attrs'] ?? [];
                    
                    // Carbon Fields expects data in a 'data' wrapper
                    if ( isset( $old_attrs['data'] ) && is_array( $old_attrs['data'] ) ) {
                        $current_data = $old_attrs['data'];
                        
                        // Only replace placeholders or missing values in the data
                        foreach ( $correct_attributes as $key => $value ) {
                            if ( !isset( $current_data[$key] ) || 
                                 ( is_string( $current_data[$key] ) && str_contains( $current_data[$key], '{' ) ) ) {
                                $current_data[$key] = $value;
                            }
                        }
                        
                        $block['attrs'] = [ 'data' => $current_data ];
                    } else {
                        // No existing data structure, create new one
                        $block['attrs'] = [ 'data' => $correct_attributes ];
                    }
                    $modified = true;
                    
                }
            }
            
            if ( $modified && function_exists( 'serialize_blocks' ) ) {
                $new_content = serialize_blocks( $blocks );
                return $new_content;
            }
        }
        
        // Fallback: Use regex pattern matching for block replacement
        $pattern = '/<!-- wp:carbon-fields\/million-dollar-script(?:\s+({[^}]*}))?\s*\/-->/';
        $replacement = '<!-- wp:carbon-fields/million-dollar-script ' . wp_json_encode( $correct_attributes ) . ' /-->';
        
        $new_content = preg_replace_callback( $pattern, function( $matches ) use ( $correct_attributes ) {
            // If there were existing attributes, try to preserve non-mds_ ones
            if ( isset( $matches[1] ) ) {
                $existing_attrs = json_decode( $matches[1], true );
                if ( $existing_attrs ) {
                    // Remove old mds_ attributes
                    $cleaned_attrs = [];
                    foreach ( $existing_attrs as $key => $value ) {
                        if ( !str_starts_with( $key, 'mds_' ) ) {
                            $cleaned_attrs[$key] = $value;
                        }
                    }
                    $merged_attrs = array_merge( $cleaned_attrs, $correct_attributes );
                    return '<!-- wp:carbon-fields/million-dollar-script ' . wp_json_encode( $merged_attrs ) . ' /-->';
                }
            }
            // No existing attributes or couldn't parse them
            return '<!-- wp:carbon-fields/million-dollar-script ' . wp_json_encode( $correct_attributes ) . ' /-->';
        }, $content );
        
        
        return $new_content;
    }
    
    /**
     * Generate Gutenberg block attributes based on page type and configuration
     *
     * @param string $page_type
     * @param array $configuration
     * @param int $grid_id
     * @param string $align
     * @return array
     */
    private function generateBlockAttributes( string $page_type, array $configuration, int $grid_id, string $align ): array {
        // Try to get existing page metadata for more accurate defaults
        $page_metadata = self::getCurrentPageMetadata();
        
        // Use metadata configuration if available, otherwise use provided configuration
        if ( $page_metadata && isset( $page_metadata['configuration'] ) ) {
            $meta_config = $page_metadata['configuration'];
            
            // Merge metadata with provided configuration (provided takes priority)
            $merged_config = array_merge( $meta_config, $configuration );
            
            $base_attributes = [
                'milliondollarscript_type' => $merged_config['type'] ?? $page_type,
                'milliondollarscript_id' => intval( $merged_config['id'] ?? $grid_id ),
                'milliondollarscript_align' => $merged_config['align'] ?? $align
            ];
        } else {
            $base_attributes = [
                'milliondollarscript_type' => $page_type,
                'milliondollarscript_id' => $grid_id,
                'milliondollarscript_align' => $align
            ];
            $merged_config = $configuration;
        }
        
        // Set width and height based on page type and available configuration
        switch ( $base_attributes['milliondollarscript_type'] ) {
            case 'grid':
                // For grid type, try to get actual grid dimensions from database
                $width = $merged_config['width'] ?? null;
                $height = $merged_config['height'] ?? null;
                
                // If no specific dimensions set, try to calculate from grid
                if ( ( !$width || !$height || $width === '0' || $height === '0' ) && $base_attributes['milliondollarscript_id'] ) {
                    $calculated_dimensions = $this->calculateGridDimensions( $base_attributes['milliondollarscript_id'] );
                    if ( $calculated_dimensions ) {
                        $width = $width ?: $calculated_dimensions['width'];
                        $height = $height ?: $calculated_dimensions['height'];
                    }
                }
                
                $base_attributes['milliondollarscript_width'] = $width ?: '1000px';
                $base_attributes['milliondollarscript_height'] = $height ?: '1000px';
                break;
                
            case 'stats':
                $base_attributes['milliondollarscript_width'] = $merged_config['width'] ?? '150px';
                $base_attributes['milliondollarscript_height'] = $merged_config['height'] ?? '60px';
                break;
                
            default:
                // All other page types (order, write-ad, confirm-order, payment, manage, thank-you, list, upload, no-orders)
                $base_attributes['milliondollarscript_width'] = $merged_config['width'] ?? '100%';
                $base_attributes['milliondollarscript_height'] = $merged_config['height'] ?? 'auto';
                break;
        }
        
        return $base_attributes;
    }
    
    /**
     * Calculate grid dimensions from database
     *
     * @param int $grid_id
     * @return array|null Array with width/height or null if not found
     */
    private function calculateGridDimensions( int $grid_id ): ?array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mds_banners';
        $grid = $wpdb->get_row( $wpdb->prepare(
            "SELECT grid_width, grid_height, block_width, block_height FROM {$table_name} WHERE banner_id = %d",
            $grid_id
        ) );
        
        if ( $grid ) {
            $width = $grid->grid_width * $grid->block_width;
            $height = $grid->grid_height * $grid->block_height;
            
            return [
                'width' => $width . 'px',
                'height' => $height . 'px'
            ];
        }
        
        return null;
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
        
        $valid_types = [ 'shortcode', 'block' ];
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
                // Update the selections with the validated/normalized configuration
                $selections['configurations'][$page_type] = $config;
            }
        }
        
        return true;
    }
    
    /**
     * Validate page configuration
     *
     * @param string $page_type
     * @param array &$configuration Configuration array (passed by reference to allow modifications)
     * @return bool|WP_Error
     */
    private function validatePageConfiguration( string $page_type, array &$configuration ): bool|WP_Error {
        $config_schema = $this->page_configurations[$page_type] ?? [];
        
        foreach ( $configuration as $key => $value ) {
            if ( !isset( $config_schema[$key] ) ) {
                continue; // Allow unknown configuration keys
            }
            
            $field_config = $config_schema[$key];
            
            // Type validation
            switch ( $field_config['type'] ) {
                case 'dynamic_grid_info':
                    // Dynamic grid info fields contain JSON data
                    if ( is_string( $value ) && !empty( $value ) ) {
                        // Handle double-escaped JSON from AJAX
                        $value = stripslashes( $value );
                        
                        $decoded = json_decode( $value, true );
                        if ( json_last_error() !== JSON_ERROR_NONE ) {
                            // Try one more time with additional unescaping
                            $value = stripslashes( $value );
                            $decoded = json_decode( $value, true );
                            
                            if ( json_last_error() !== JSON_ERROR_NONE ) {
                                return new WP_Error( 'invalid_grid_data', sprintf( Language::get( 'Invalid grid data for %s: %s' ), $key, json_last_error_msg() ) );
                            }
                        }
                        // Store the decoded data for later use
                        $configuration[$key] = $decoded;
                    } elseif ( is_array( $value ) ) {
                        // Already decoded, validate it has required structure
                        if ( empty( $value ) ) {
                            return new WP_Error( 'empty_grid_data', sprintf( Language::get( 'Grid data for %s is empty' ), $key ) );
                        }
                        $configuration[$key] = $value;
                    } elseif ( empty( $value ) ) {
                        // Empty value is allowed - use defaults
                        $configuration[$key] = [];
                    }
                    break;
                    
                case 'number':
                    if ( !is_numeric( $value ) && !empty( $value ) ) {
                        return new WP_Error( 'invalid_number', sprintf( Language::get( 'Invalid number for %s' ), $key ) );
                    }
                    
                    if ( !empty( $value ) ) {
                        $numValue = floatval( $value );
                        if ( isset( $field_config['min'] ) && $numValue < $field_config['min'] ) {
                            return new WP_Error( 'number_too_small', sprintf( Language::get( 'Value for %s is too small' ), $key ) );
                        }
                        
                        if ( isset( $field_config['max'] ) && $numValue > $field_config['max'] ) {
                            return new WP_Error( 'number_too_large', sprintf( Language::get( 'Value for %s is too large' ), $key ) );
                        }
                        
                        $configuration[$key] = $numValue;
                    }
                    break;
                    
                case 'select':
                    if ( !empty( $value ) && !isset( $field_config['options'][$value] ) ) {
                        return new WP_Error( 'invalid_option', sprintf( Language::get( 'Invalid option for %s' ), $key ) );
                    }
                    break;
                    
                case 'multiselect':
                    if ( !empty( $value ) && !is_array( $value ) ) {
                        return new WP_Error( 'invalid_multiselect', sprintf( Language::get( 'Invalid multiselect for %s' ), $key ) );
                    }
                    
                    if ( is_array( $value ) ) {
                        foreach ( $value as $option ) {
                            if ( !isset( $field_config['options'][$option] ) ) {
                                return new WP_Error( 'invalid_multiselect_option', sprintf( Language::get( 'Invalid option %s for %s' ), $option, $key ) );
                            }
                        }
                    }
                    break;
                    
                case 'checkbox':
                    // Convert to boolean and store the normalized value
                    $configuration[$key] = !empty( $value ) && $value !== '0' && $value !== 'false';
                    break;
                    
                case 'text':
                case 'textarea':
                    // Sanitize text fields
                    if ( is_string( $value ) ) {
                        $configuration[$key] = sanitize_text_field( $value );
                    }
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
        $create_mode = $selections['create_mode'] ?? 'create_new';
        
        foreach ( $selected_pages as $page_type ) {
            $existing_page_id = $this->getExistingPageId( $page_type );
            $template = $this->getPageTemplate( $page_type, $implementation_type );
            
            // Determine status based on create_mode and existing page
            $status = $this->getPageStatus( $existing_page_id, $create_mode );
            
            // Get detailed information about existing page
            $existing_page_data = null;
            if ( $existing_page_id ) {
                $existing_post = get_post( $existing_page_id );
                if ( $existing_post ) {
                    $existing_page_data = [
                        'id' => $existing_page_id,
                        'title' => get_the_title( $existing_page_id ),
                        'url' => get_permalink( $existing_page_id ),
                        'edit_link' => get_edit_post_link( $existing_page_id ),
                        'status' => get_post_status( $existing_page_id ),
                        'date_modified' => get_the_modified_date( 'Y-m-d H:i:s', $existing_page_id ),
                        'author' => get_the_author_meta( 'display_name', $existing_post->post_author )
                    ];
                }
            }
            
            // Get user-friendly label for the page type
            $available_page_types = $this->getAvailablePageTypes();
            $page_label = $available_page_types[$page_type]['label'] ?? ucfirst( str_replace( '-', ' ', $page_type ) );
            
            $page_preview = [
                'page_type' => $page_type,
                'page_type_label' => $page_label,
                'title' => $this->generatePageTitle( $page_type, $configurations[$page_type] ?? [] ),
                'implementation_type' => $implementation_type,
                'template_description' => $template['description'] ?? '',
                'existing_page' => $existing_page_id ? get_permalink( $existing_page_id ) : null,
                'existing_page_data' => $existing_page_data,
                'status' => $status,
                'configuration' => $configurations[$page_type] ?? []
            ];
            
            $preview['pages_to_create'][] = $page_preview;
            
            // Add warnings based on create_mode and existing pages
            if ( $existing_page_id ) {
                // Get user-friendly label for the page type
                $available_page_types = $this->getAvailablePageTypes();
                $page_label = $available_page_types[$page_type]['label'] ?? ucfirst( str_replace( '-', ' ', $page_type ) );
                
                switch ( $create_mode ) {
                    case 'update_existing':
                        $preview['warnings'][] = sprintf(
                            Language::get( 'Page %s already exists and will be updated with new content' ),
                            $page_label
                        );
                        break;
                    case 'skip_existing':
                        $preview['warnings'][] = sprintf(
                            Language::get( 'Page %s already exists and will be skipped' ),
                            $page_label
                        );
                        break;
                    case 'create_new':
                        $preview['warnings'][] = sprintf(
                            Language::get( 'Page %s already exists but a new page will be created with a different name' ),
                            $page_label
                        );
                        break;
                }
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
    
    /**
     * Get page status based on existing page and create mode
     *
     * @param int|null $existing_page_id
     * @param string $create_mode
     * @return string
     */
    private function getPageStatus( $existing_page_id, string $create_mode ): string {
        if ( !$existing_page_id ) {
            return Language::get( 'Will create new' );
        }
        
        switch ( $create_mode ) {
            case 'update_existing':
                return Language::get( 'Will update existing' );
            case 'skip_existing':
                return Language::get( 'Will skip existing' );
            case 'create_new':
            default:
                return Language::get( 'Will create new' );
        }
    }
    
    /**
     * Get page configurations for selected page types
     *
     * @param array $page_types Selected page types
     * @return array Configuration data for each page type
     */
    public function getPageConfigurations( array $page_types ): array {
        $configurations = [];
        
        foreach ( $page_types as $page_type ) {
            if ( isset( $this->page_configurations[$page_type] ) ) {
                $configurations[$page_type] = [
                    'title' => $this->getPageTypeTitle( $page_type ),
                    'fields' => $this->page_configurations[$page_type]
                ];
            }
        }
        
        return $configurations;
    }
    
    /**
     * Get page type title
     *
     * @param string $page_type
     * @return string
     */
    private function getPageTypeTitle( string $page_type ): string {
        $titles = [
            'grid' => Language::get( 'Pixel Grid Configuration' ),
            'order' => Language::get( 'Order Page Configuration' ),
            'write-ad' => Language::get( 'Write Content Configuration' ),
            'confirm-order' => Language::get( 'Order Confirmation Configuration' ),
            'payment' => Language::get( 'Payment Configuration' ),
            'manage' => Language::get( 'Manage Pixels Configuration' ),
            'thank-you' => Language::get( 'Thank You Page Configuration' ),
            'list' => Language::get( 'Pixel List Configuration' ),
            'upload' => Language::get( 'File Upload Configuration' ),
            'no-orders' => Language::get( 'No Orders Configuration' ),
            'stats' => Language::get( 'Statistics Box Configuration' )
        ];
        
        return $titles[$page_type] ?? ucfirst( str_replace( '-', ' ', $page_type ) ) . ' Configuration';
    }
    
    /**
     * Generate creation preview
     *
     * @param array $form_data
     * @return array
     */
    public function generateCreationPreview( array $form_data ): array {
        return $this->getCreationPreview( $form_data );
    }
    
    /**
     * Create pages
     *
     * @param array $form_data
     * @return array|WP_Error
     */
    public function createPages( array $form_data ) {
        return $this->createPagesFromSelections( $form_data );
    }
} 