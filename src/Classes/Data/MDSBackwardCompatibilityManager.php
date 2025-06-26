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
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

/**
 * Backward Compatibility Manager
 * 
 * Manages all aspects of backward compatibility for the MDS page management system,
 * ensuring seamless upgrades and continued functionality for existing installations.
 */
class MDSBackwardCompatibilityManager {
    
    private static ?self $instance = null;
    private MDSPageMetadataManager $metadata_manager;
    private MDSPageDetectionEngine $detection_engine;
    private MDSPageActivationScanner $activation_scanner;
    
    // Compatibility version tracking
    private const COMPATIBILITY_VERSION = '1.0.0';
    private const METADATA_SYSTEM_VERSION = '2.5.12.85';
    
    // Legacy shortcode patterns
    private array $legacy_shortcode_patterns = [
        'milliondollarscript',
        'mds',
        'million_dollar_script',
        'pixel_grid',
        'mds_grid',
        'mds_display'
    ];
    
    // Legacy block patterns
    private array $legacy_block_patterns = [
        'mds/grid-block',
        'mds/order-block',
        'mds/stats-block',
        'mds/display-block',
        'milliondollarscript/grid',
        'milliondollarscript/display',
        'milliondollarscript/order',
        'milliondollarscript/stats'
    ];
    
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
     * Constructor
     */
    private function __construct() {
        $this->metadata_manager = MDSPageMetadataManager::getInstance();
        $this->detection_engine = new MDSPageDetectionEngine();
        $this->activation_scanner = new MDSPageActivationScanner();
        
        $this->initializeCompatibilitySystem();
    }
    
    /**
     * Initialize the backward compatibility system
     *
     * @return void
     */
    private function initializeCompatibilitySystem(): void {
        // Hook into WordPress initialization
        add_action( 'init', [ $this, 'checkCompatibilityRequirements' ], 5 );
        
        // Handle admin compatibility - use init hook instead of admin_init for better reliability
        if ( is_admin() ) {
            add_action( 'init', [ $this, 'handleAdminCompatibility' ], 10 );
            add_action( 'wp_ajax_mds_manual_migration', [ $this, 'handleManualMigration' ] );
        }
        
        // Hook into plugin activation/upgrade
        add_action( 'mds_plugin_activated', [ $this, 'handlePluginActivation' ] );
        add_action( 'mds_plugin_upgraded', [ $this, 'handlePluginUpgrade' ] );
        
        // Legacy shortcode compatibility
        add_filter( 'mds_shortcode_compatibility', [ $this, 'handleLegacyShortcodes' ], 10, 2 );
        
        // Legacy block compatibility
        add_filter( 'mds_block_compatibility', [ $this, 'handleLegacyBlocks' ], 10, 2 );
        
        // Content migration hooks
        add_action( 'save_post', [ $this, 'handlePostSave' ], 10, 2 );
        add_action( 'wp_insert_post', [ $this, 'handlePostInsert' ], 10, 2 );
    }
    
    /**
     * Check compatibility requirements
     *
     * @return void
     */
    public function checkCompatibilityRequirements(): void {
        $compatibility_version = get_option( 'mds_compatibility_version', '0.0.0' );
        
        if ( version_compare( $compatibility_version, self::COMPATIBILITY_VERSION, '<' ) ) {
            $this->performCompatibilityUpgrade( $compatibility_version );
        }
    }
    
    /**
     * Handle admin-specific compatibility tasks
     *
     * @return void
     */
    public function handleAdminCompatibility(): void {
        if ( ! is_admin() ) {
            return;
        }
        
        // Always add compatibility admin menu
        add_action( 'admin_menu', [ $this, 'addCompatibilityAdminMenu' ], 20 );
        
        // Check for pending migrations and show notice if needed
        $pending_migrations = $this->getPendingMigrations();
        if ( ! empty( $pending_migrations ) ) {
            add_action( 'admin_notices', [ $this, 'showMigrationNotice' ] );
        }
    }
    
    /**
     * Handle plugin activation
     *
     * @return void
     */
    public function handlePluginActivation(): void {
        // Perform initial compatibility scan
        $this->performInitialCompatibilityScan();
        
        // Set compatibility version
        update_option( 'mds_compatibility_version', self::COMPATIBILITY_VERSION );
        
        // Schedule background migration if needed
        $this->scheduleBackgroundMigration();
    }
    
    /**
     * Handle plugin upgrade
     *
     * @param string $old_version
     * @return void
     */
    public function handlePluginUpgrade( string $old_version ): void {
        // Perform upgrade-specific compatibility tasks
        $this->performUpgradeCompatibility( $old_version );
        
        // Update compatibility version
        update_option( 'mds_compatibility_version', self::COMPATIBILITY_VERSION );
    }
    
    /**
     * Perform compatibility upgrade
     *
     * @param string $from_version
     * @return void
     */
    private function performCompatibilityUpgrade( string $from_version ): void {
        // Version-specific compatibility upgrades
        if ( version_compare( $from_version, '1.0.0', '<' ) ) {
            $this->upgradeToVersion100();
        }
        
        // Update compatibility version
        update_option( 'mds_compatibility_version', self::COMPATIBILITY_VERSION );
    }
    
    /**
     * Upgrade to version 1.0.0 compatibility
     *
     * @return void
     */
    private function upgradeToVersion100(): void {
        // Migrate existing pages to metadata system
        $this->migrateExistingPagesToMetadataSystem();
        
        // Update legacy shortcodes
        $this->updateLegacyShortcodes();
        
        // Migrate legacy blocks
        $this->migrateLegacyBlocks();
        
        // Update page options
        $this->migratePageOptions();
    }
    
    /**
     * Migrate existing pages to metadata system
     *
     * @return array Migration results
     */
    public function migrateExistingPagesToMetadataSystem(): array {
        $results = [
            'total_pages' => 0,
            'migrated_pages' => 0,
            'skipped_pages' => 0,
            'failed_pages' => 0,
            'errors' => []
        ];
        
        // Get all pages that might be MDS pages
        $potential_mds_pages = $this->findPotentialMDSPages();
        $results['total_pages'] = count( $potential_mds_pages );
        
        foreach ( $potential_mds_pages as $page_id ) {
            try {
                // Check if already has metadata
                if ( $this->metadata_manager->hasMetadata( $page_id ) ) {
                    $results['skipped_pages']++;
                    continue;
                }
                
                // Detect page type and create metadata
                $detection_result = $this->detection_engine->detectMDSPage( $page_id );
                
                if ( $detection_result['is_mds_page'] && $detection_result['confidence'] >= 0.6 ) {
                    $metadata_data = $this->buildMetadataFromDetection( $page_id, $detection_result );
                    
                    $result = $this->metadata_manager->createOrUpdateMetadata( $page_id, $detection_result['page_type'], 'migration', $metadata_data );
                    
                    if ( ! is_wp_error( $result ) ) {
                        $results['migrated_pages']++;
                    } else {
                        $results['failed_pages']++;
                        $results['errors'][] = sprintf(
                            'Page %d: %s',
                            $page_id,
                            $result->get_error_message()
                        );
                    }
                } else {
                    $results['skipped_pages']++;
                }
            } catch ( \Exception $e ) {
                $results['failed_pages']++;
                $results['errors'][] = sprintf(
                    'Page %d: %s',
                    $page_id,
                    $e->getMessage()
                );
            }
        }
        
        // Store migration results
        update_option( 'mds_migration_results', $results );
        
        return $results;
    }
    
    /**
     * Find potential MDS pages
     *
     * @return array Array of page IDs
     */
    private function findPotentialMDSPages(): array {
        global $wpdb;
        
        $page_ids = [];
        
        // Find pages with MDS shortcodes
        $shortcode_patterns = implode( '|', $this->legacy_shortcode_patterns );
        $shortcode_query = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'page' 
             AND post_status IN ('publish', 'private', 'draft')
             AND post_content REGEXP %s",
            '\[(' . $shortcode_patterns . ')([^\]]*)\]'
        );
        
        $shortcode_pages = $wpdb->get_col( $shortcode_query );
        $page_ids = array_merge( $page_ids, $shortcode_pages );
        
        // Find pages with MDS blocks
        $block_patterns = implode( '|', $this->legacy_block_patterns );
        $block_query = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'page' 
             AND post_status IN ('publish', 'private', 'draft')
             AND post_content LIKE %s",
            '%<!-- wp:(' . $block_patterns . ')%'
        );
        
        $block_pages = $wpdb->get_col( $block_query );
        $page_ids = array_merge( $page_ids, $block_pages );
        
        // Find pages referenced in MDS options
        $mds_page_options = [
            '_' . MDS_PREFIX . 'grid-page',
            '_' . MDS_PREFIX . 'order-page',
            '_' . MDS_PREFIX . 'write-ad-page',
            '_' . MDS_PREFIX . 'confirm-order-page',
            '_' . MDS_PREFIX . 'payment-page',
            '_' . MDS_PREFIX . 'manage-page',
            '_' . MDS_PREFIX . 'thank-you-page',
            '_' . MDS_PREFIX . 'list-page',
            '_' . MDS_PREFIX . 'upload-page',
            '_' . MDS_PREFIX . 'no-orders-page'
        ];
        
        foreach ( $mds_page_options as $option ) {
            $page_id = get_option( $option );
            if ( $page_id && get_post( $page_id ) ) {
                $page_ids[] = $page_id;
            }
        }
        
        // Remove duplicates and invalid IDs
        $page_ids = array_unique( array_filter( $page_ids ) );
        
        return $page_ids;
    }
    
    /**
     * Build metadata from detection result
     *
     * @param int $page_id
     * @param array $detection_result
     * @return array
     */
    private function buildMetadataFromDetection( int $page_id, array $detection_result ): array {
        $post = get_post( $page_id );
        
        return [
            'page_type' => $detection_result['page_type'] ?? 'unknown',
            'creation_method' => 'migration',
            'creation_source' => 'backward_compatibility_v' . self::COMPATIBILITY_VERSION,
            'mds_version' => MDS_VERSION,
            'content_type' => $detection_result['content_type'] ?? 'unknown',
            'status' => $this->determinePageStatus( $post ),
            'confidence_score' => $detection_result['confidence'] ?? 0.5,
            'shortcode_attributes' => $detection_result['shortcode_attributes'] ?? [],
            'block_attributes' => $detection_result['block_attributes'] ?? [],
            'content_analysis' => $detection_result['content_analysis'] ?? [],
            'page_config' => [
                'migrated_from' => 'legacy_system',
                'migration_date' => current_time( 'mysql' ),
                'original_content_type' => $detection_result['content_type'] ?? 'unknown'
            ],
            'display_settings' => $this->extractDisplaySettings( $detection_result ),
            'integration_settings' => [
                'theme_name' => wp_get_theme()->get( 'Name' ),
                'migration_notes' => 'Migrated from legacy MDS system'
            ]
        ];
    }
    
    /**
     * Determine page status from post
     *
     * @param \WP_Post $post
     * @return string
     */
    private function determinePageStatus( \WP_Post $post ): string {
        switch ( $post->post_status ) {
            case 'publish':
                return 'active';
            case 'draft':
            case 'private':
                return 'inactive';
            default:
                return 'unknown';
        }
    }
    
    /**
     * Extract display settings from detection result
     *
     * @param array $detection_result
     * @return array
     */
    private function extractDisplaySettings( array $detection_result ): array {
        $settings = [];
        
        // Extract from shortcode attributes
        if ( ! empty( $detection_result['shortcode_attributes'] ) ) {
            $attrs = $detection_result['shortcode_attributes'];
            
            if ( isset( $attrs['align'] ) ) {
                $settings['align'] = $attrs['align'];
            }
            if ( isset( $attrs['width'] ) ) {
                $settings['width'] = $attrs['width'];
            }
            if ( isset( $attrs['height'] ) ) {
                $settings['height'] = $attrs['height'];
            }
        }
        
        // Extract from block attributes
        if ( ! empty( $detection_result['block_attributes'] ) ) {
            $attrs = $detection_result['block_attributes'];
            
            if ( isset( $attrs['align'] ) ) {
                $settings['align'] = $attrs['align'];
            }
            if ( isset( $attrs['width'] ) ) {
                $settings['width'] = $attrs['width'];
            }
            if ( isset( $attrs['height'] ) ) {
                $settings['height'] = $attrs['height'];
            }
        }
        
        return $settings;
    }
    
    /**
     * Update legacy shortcodes
     *
     * @return array Update results
     */
    public function updateLegacyShortcodes(): array {
        global $wpdb;
        
        $results = [
            'total_updates' => 0,
            'successful_updates' => 0,
            'failed_updates' => 0,
            'updated_pages' => 0,
            'errors' => []
        ];
        
        // Find posts with legacy shortcodes
        $legacy_patterns = [
            'mds' => 'milliondollarscript',
            'million_dollar_script' => 'milliondollarscript',
            'pixel_grid' => 'milliondollarscript',
            'mds_grid' => 'milliondollarscript',
            'mds_display' => 'milliondollarscript'
        ];
        
        foreach ( $legacy_patterns as $old_shortcode => $new_shortcode ) {
            $posts = $wpdb->get_results( $wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts} 
                 WHERE post_content LIKE %s 
                 AND post_type IN ('page', 'post')",
                '%[' . $old_shortcode . '%'
            ) );
            
            foreach ( $posts as $post ) {
                $results['total_updates']++;
                
                try {
                    $updated_content = $this->updateShortcodeInContent( 
                        $post->post_content, 
                        $old_shortcode, 
                        $new_shortcode 
                    );
                    
                    $update_result = wp_update_post( [
                        'ID' => $post->ID,
                        'post_content' => $updated_content
                    ] );
                    
                    if ( $update_result && ! is_wp_error( $update_result ) ) {
                        $results['successful_updates']++;
                        $results['updated_pages']++;
                        
                        // Add migration note to post meta
                        add_post_meta( $post->ID, '_mds_shortcode_migrated', [
                            'from' => $old_shortcode,
                            'to' => $new_shortcode,
                            'date' => current_time( 'mysql' )
                        ] );
                    } else {
                        $results['failed_updates']++;
                        $error_message = is_wp_error( $update_result ) ? 
                            $update_result->get_error_message() : 
                            'Unknown error';
                        $results['errors'][] = sprintf(
                            'Post %d: %s',
                            $post->ID,
                            $error_message
                        );
                    }
                } catch ( \Exception $e ) {
                    $results['failed_updates']++;
                    $results['errors'][] = sprintf(
                        'Post %d: %s',
                        $post->ID,
                        $e->getMessage()
                    );
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Update shortcode in content
     *
     * @param string $content
     * @param string $old_shortcode
     * @param string $new_shortcode
     * @return string
     */
    private function updateShortcodeInContent( string $content, string $old_shortcode, string $new_shortcode ): string {
        // Pattern to match the old shortcode with attributes
        $pattern = '/\[' . preg_quote( $old_shortcode, '/' ) . '([^\]]*)\]/';
        
        return preg_replace_callback( $pattern, function( $matches ) use ( $new_shortcode ) {
            $attributes = isset( $matches[1] ) ? $matches[1] : '';
            
            // Parse and normalize attributes
            $parsed_attrs = shortcode_parse_atts( $attributes );
            if ( ! is_array( $parsed_attrs ) ) {
                $parsed_attrs = [];
            }
            
            // Ensure required attributes are present
            $default_attrs = [
                'type' => 'grid',
                'align' => 'center',
                'width' => '100%',
                'height' => 'auto'
            ];
            
            $parsed_attrs = array_merge( $default_attrs, $parsed_attrs );
            
            // Build new shortcode
            $attr_string = '';
            foreach ( $parsed_attrs as $key => $value ) {
                $attr_string .= sprintf( ' %s="%s"', $key, esc_attr( $value ) );
            }
            
            return '[' . $new_shortcode . $attr_string . ']';
        }, $content );
    }
    
    /**
     * Migrate legacy blocks
     *
     * @return array Migration results
     */
    public function migrateLegacyBlocks(): array {
        global $wpdb;
        
        $results = [
            'total_blocks' => 0,
            'migrated_blocks' => 0,
            'failed_blocks' => 0,
            'errors' => []
        ];
        
        // Find posts with legacy blocks
        $posts = $wpdb->get_results(
            "SELECT ID, post_content FROM {$wpdb->posts} 
             WHERE post_content LIKE '%<!-- wp:mds/%' 
             OR post_content LIKE '%<!-- wp:milliondollarscript/%'
             AND post_type IN ('page', 'post')"
        );
        
        foreach ( $posts as $post ) {
            try {
                $blocks = parse_blocks( $post->post_content );
                $updated_blocks = $this->updateLegacyBlocksInArray( $blocks );
                
                if ( $updated_blocks !== $blocks ) {
                    $updated_content = serialize_blocks( $updated_blocks );
                    
                    $update_result = wp_update_post( [
                        'ID' => $post->ID,
                        'post_content' => $updated_content
                    ] );
                    
                    if ( $update_result && ! is_wp_error( $update_result ) ) {
                        $results['migrated_blocks']++;
                        
                        // Add migration note
                        add_post_meta( $post->ID, '_mds_blocks_migrated', [
                            'date' => current_time( 'mysql' ),
                            'version' => self::COMPATIBILITY_VERSION
                        ] );
                    } else {
                        $results['failed_blocks']++;
                        $error_message = is_wp_error( $update_result ) ? 
                            $update_result->get_error_message() : 
                            'Unknown error';
                        $results['errors'][] = sprintf(
                            'Post %d: %s',
                            $post->ID,
                            $error_message
                        );
                    }
                }
            } catch ( \Exception $e ) {
                $results['failed_blocks']++;
                $results['errors'][] = sprintf(
                    'Post %d: %s',
                    $post->ID,
                    $e->getMessage()
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Update legacy blocks in blocks array
     *
     * @param array $blocks
     * @return array
     */
    private function updateLegacyBlocksInArray( array $blocks ): array {
        $updated = false;
        
        foreach ( $blocks as &$block ) {
            if ( isset( $block['blockName'] ) && in_array( $block['blockName'], $this->legacy_block_patterns ) ) {
                // Update block name to current standard
                $new_block_name = $this->mapLegacyBlockName( $block['blockName'] );
                if ( $new_block_name !== $block['blockName'] ) {
                    $block['blockName'] = $new_block_name;
                    $updated = true;
                }
                
                // Update block attributes if needed
                if ( isset( $block['attrs'] ) ) {
                    $updated_attrs = $this->updateLegacyBlockAttributes( $block['attrs'] );
                    if ( $updated_attrs !== $block['attrs'] ) {
                        $block['attrs'] = $updated_attrs;
                        $updated = true;
                    }
                }
            }
            
            // Recursively update inner blocks
            if ( ! empty( $block['innerBlocks'] ) ) {
                $updated_inner = $this->updateLegacyBlocksInArray( $block['innerBlocks'] );
                if ( $updated_inner !== $block['innerBlocks'] ) {
                    $block['innerBlocks'] = $updated_inner;
                    $updated = true;
                }
            }
        }
        
        return $updated ? $blocks : $blocks;
    }
    
    /**
     * Map legacy block name to current standard
     *
     * @param string $legacy_name
     * @return string
     */
    private function mapLegacyBlockName( string $legacy_name ): string {
        $mapping = [
            'mds/grid-block' => 'milliondollarscript/grid',
            'mds/order-block' => 'milliondollarscript/order',
            'mds/stats-block' => 'milliondollarscript/stats',
            'mds/display-block' => 'milliondollarscript/display'
        ];
        
        return $mapping[$legacy_name] ?? $legacy_name;
    }
    
    /**
     * Update legacy block attributes
     *
     * @param array $attrs
     * @return array
     */
    private function updateLegacyBlockAttributes( array $attrs ): array {
        // Normalize attribute names
        $attr_mapping = [
            'gridSize' => 'grid_size',
            'pageType' => 'page_type',
            'displayType' => 'display_type'
        ];
        
        $updated_attrs = [];
        foreach ( $attrs as $key => $value ) {
            $new_key = $attr_mapping[$key] ?? $key;
            $updated_attrs[$new_key] = $value;
        }
        
        // Ensure required attributes
        if ( ! isset( $updated_attrs['type'] ) && isset( $updated_attrs['page_type'] ) ) {
            $updated_attrs['type'] = $updated_attrs['page_type'];
        }
        
        return $updated_attrs;
    }
    
    /**
     * Migrate page options
     *
     * @return array Migration results
     */
    public function migratePageOptions(): array {
        $results = [
            'total_options' => 0,
            'migrated_options' => 0,
            'failed_options' => 0,
            'errors' => []
        ];
        
        $page_option_mapping = [
            'grid-page' => 'grid',
            'order-page' => 'order',
            'write-ad-page' => 'write-ad',
            'confirm-order-page' => 'confirm-order',
            'payment-page' => 'payment',
            'manage-page' => 'manage',
            'thank-you-page' => 'thank-you',
            'list-page' => 'list',
            'upload-page' => 'upload',
            'no-orders-page' => 'no-orders'
        ];
        
        foreach ( $page_option_mapping as $option_key => $page_type ) {
            $results['total_options']++;
            
            try {
                $page_id = get_option( '_' . MDS_PREFIX . $option_key );
                
                if ( $page_id && get_post( $page_id ) ) {
                    // Ensure metadata exists for this page
                    if ( ! $this->metadata_manager->hasMetadata( $page_id ) ) {
                        // Create metadata for this page
                        $metadata_data = [
                            'page_type' => $page_type,
                            'creation_method' => 'option_migration',
                            'creation_source' => 'page_option_' . $option_key,
                            'mds_version' => MDS_VERSION,
                            'content_type' => 'unknown',
                            'status' => 'active',
                            'confidence_score' => 0.8,
                            'page_config' => [
                                'option_key' => $option_key,
                                'migrated_from' => 'page_option',
                                'migration_date' => current_time( 'mysql' )
                            ]
                        ];
                        
                        $result = $this->metadata_manager->createOrUpdateMetadata( $page_id, $page_type, 'migration', $metadata_data );
                        
                        if ( ! is_wp_error( $result ) ) {
                            $results['migrated_options']++;
                        } else {
                            $results['failed_options']++;
                            $results['errors'][] = sprintf(
                                'Option %s (Page %d): %s',
                                $option_key,
                                $page_id,
                                $result->get_error_message()
                            );
                        }
                    } else {
                        $results['migrated_options']++;
                    }
                } else {
                    // Option exists but page doesn't - clean up
                    delete_option( '_' . MDS_PREFIX . $option_key );
                    $results['migrated_options']++;
                }
            } catch ( \Exception $e ) {
                $results['failed_options']++;
                $results['errors'][] = sprintf(
                    'Option %s: %s',
                    $option_key,
                    $e->getMessage()
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Handle legacy shortcodes
     *
     * @param mixed $result
     * @param array $atts
     * @return mixed
     */
    public function handleLegacyShortcodes( $result, array $atts ) {
        // This filter allows legacy shortcodes to be processed
        // The actual shortcode handling is done in the main shortcode system
        return $result;
    }
    
    /**
     * Handle legacy blocks
     *
     * @param mixed $result
     * @param array $attributes
     * @return mixed
     */
    public function handleLegacyBlocks( $result, array $attributes ) {
        // This filter allows legacy blocks to be processed
        // The actual block handling is done in the main block system
        return $result;
    }
    
    /**
     * Handle post save for compatibility
     *
     * @param int $post_id
     * @param \WP_Post $post
     * @return void
     */
    public function handlePostSave( int $post_id, \WP_Post $post ): void {
        if ( $post->post_type !== 'page' ) {
            return;
        }
        
        // Check if this page might be an MDS page
        $detection_result = $this->detection_engine->detectMDSPage( $post_id );
        
        if ( $detection_result['is_mds_page'] ) {
            // Update or create metadata
            if ( $this->metadata_manager->hasMetadata( $post_id ) ) {
                $this->metadata_manager->createOrUpdateMetadata( $post_id, $detection_result['page_type'], 'auto_scan', [] );
            } else {
                $metadata_data = $this->buildMetadataFromDetection( $post_id, $detection_result );
                $this->metadata_manager->createOrUpdateMetadata( $post_id, $detection_result['page_type'], 'auto_scan', $metadata_data );
            }
        }
    }
    
    /**
     * Handle post insert for compatibility
     *
     * @param int $post_id
     * @param \WP_Post $post
     * @return void
     */
    public function handlePostInsert( int $post_id, \WP_Post $post ): void {
        // Same logic as handlePostSave for new posts
        $this->handlePostSave( $post_id, $post );
    }
    
    /**
     * Get pending migrations
     *
     * @return array
     */
    private function getPendingMigrations(): array {
        $pending = [];
        
        // Check if initial migration is complete
        $migration_results = get_option( 'mds_migration_results' );
        if ( ! $migration_results ) {
            $pending[] = 'initial_migration';
        }
        
        // Check for pages that need metadata
        $pages_without_metadata = $this->getPagesWithoutMetadata();
        if ( ! empty( $pages_without_metadata ) ) {
            $pending[] = 'metadata_migration';
        }
        
        return $pending;
    }
    
    /**
     * Get pages without metadata
     *
     * @return array
     */
    private function getPagesWithoutMetadata(): array {
        $potential_pages = $this->findPotentialMDSPages();
        $pages_without_metadata = [];
        
        foreach ( $potential_pages as $page_id ) {
            if ( ! $this->metadata_manager->hasMetadata( $page_id ) ) {
                $pages_without_metadata[] = $page_id;
            }
        }
        
        return $pages_without_metadata;
    }
    
    /**
     * Show migration notice
     *
     * @return void
     */
    public function showMigrationNotice(): void {
        $pending_migrations = $this->getPendingMigrations();
        
        if ( empty( $pending_migrations ) ) {
            return;
        }
        
        $message = Language::get( 'MDS Page Management System has detected pages that need to be migrated to the new metadata system.' );
        $action_url = admin_url( 'admin.php?page=mds-compatibility' );
        
        printf(
            '<div class="notice notice-warning is-dismissible">
                <p>%s</p>
                <p>
                    <a href="%s" class="button button-primary">%s</a>
                    <a href="#" class="button button-secondary mds-dismiss-migration-notice">%s</a>
                </p>
            </div>',
            esc_html( $message ),
            esc_url( $action_url ),
            esc_html( Language::get( 'Run Migration' ) ),
            esc_html( Language::get( 'Dismiss' ) )
        );
    }
    
    /**
     * Add compatibility admin menu
     *
     * @return void
     */
    public function addCompatibilityAdminMenu(): void {
        error_log( 'MDS Compatibility: Adding admin menu' );
        
        $hook = add_submenu_page(
            'milliondollarscript',
            Language::get( 'Compatibility' ),
            Language::get( 'Compatibility' ),
            'manage_options',
            'mds-compatibility',
            [ $this, 'renderCompatibilityPage' ]
        );
        
        if ( $hook ) {
            error_log( 'MDS Compatibility: Admin menu added successfully with hook: ' . $hook );
        } else {
            error_log( 'MDS Compatibility: Failed to add admin menu' );
        }
    }
    
    /**
     * Render compatibility admin page
     *
     * @return void
     */
    public function renderCompatibilityPage(): void {
        // Debug logging
        error_log( 'MDS Compatibility Page: renderCompatibilityPage called' );
        
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( 'MDS Compatibility Page: User lacks manage_options capability' );
            wp_die( __( 'Sorry, you are not allowed to access this page.' ) );
        }
        
        error_log( 'MDS Compatibility Page: User has manage_options capability' );
        
        if ( isset( $_POST['run_migration'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'mds_run_migration' ) ) {
            error_log( 'MDS Compatibility Page: Running migration' );
            $this->runManualMigration();
        }
        
        $migration_results = get_option( 'mds_migration_results', [] );
        $pending_migrations = $this->getPendingMigrations();
        
        error_log( 'MDS Compatibility Page: Including template' );
        
        // Check if template file exists before including
        $template_path = MDS_BASE_PATH . 'src/Templates/admin/compatibility.php';
        if ( ! file_exists( $template_path ) ) {
            error_log( 'MDS Compatibility Page: Template file not found at ' . $template_path );
            wp_die( 'Template file not found: ' . $template_path );
        }
        
        include $template_path;
    }
    
    /**
     * Run manual migration
     *
     * @return void
     */
    private function runManualMigration(): void {
        $results = $this->migrateExistingPagesToMetadataSystem();
        
        add_action( 'admin_notices', function() use ( $results ) {
            printf(
                '<div class="notice notice-success is-dismissible">
                    <p>%s</p>
                    <ul>
                        <li>%s: %d</li>
                        <li>%s: %d</li>
                        <li>%s: %d</li>
                        <li>%s: %d</li>
                    </ul>
                </div>',
                esc_html( Language::get( 'Migration completed successfully!' ) ),
                esc_html( Language::get( 'Total pages' ) ),
                $results['total_pages'],
                esc_html( Language::get( 'Migrated pages' ) ),
                $results['migrated_pages'],
                esc_html( Language::get( 'Skipped pages' ) ),
                $results['skipped_pages'],
                esc_html( Language::get( 'Failed pages' ) ),
                $results['failed_pages']
            );
        } );
    }
    
    /**
     * Force run all pending migrations immediately (bypass cron)
     *
     * @return array Migration results
     */
    public function forceRunAllMigrations(): array {
        $results = [
            'migrations_run' => 0,
            'pages_migrated' => 0,
            'errors' => []
        ];
        
        try {
            // Run page metadata migration
            $page_results = $this->migrateExistingPagesToMetadataSystem();
            $results['pages_migrated'] = $page_results['migrated_pages'];
            $results['migrations_run']++;
            
            // Run shortcode updates
            $shortcode_results = $this->updateLegacyShortcodes();
            if ( $shortcode_results['updated_pages'] > 0 ) {
                $results['migrations_run']++;
            }
            
            // Run block migrations
            $block_results = $this->migrateLegacyBlocks();
            if ( $block_results['updated_pages'] > 0 ) {
                $results['migrations_run']++;
            }
            
            // Run page options migration
            $options_results = $this->migratePageOptions();
            if ( $options_results['migrated_options'] > 0 ) {
                $results['migrations_run']++;
            }
            
            // Clear any pending migration flags
            delete_option( 'mds_pending_migrations' );
            update_option( 'mds_migration_completed', time() );
            
            // Store results for display
            update_option( 'mds_migration_results', [
                'completed_at' => current_time( 'mysql' ),
                'total_pages' => $page_results['total_pages'],
                'migrated_pages' => $page_results['migrated_pages'],
                'skipped_pages' => $page_results['skipped_pages'],
                'failed_pages' => $page_results['failed_pages'],
                'updated_shortcodes' => $shortcode_results['updated_pages'] ?? 0,
                'updated_blocks' => $block_results['updated_pages'] ?? 0,
                'migrated_options' => $options_results['migrated_options'] ?? 0
            ] );
            
        } catch ( Exception $e ) {
            $results['errors'][] = $e->getMessage();
            error_log( 'MDS Force Migration Error: ' . $e->getMessage() );
        }
        
        return $results;
    }
    
    /**
     * AJAX handler for manual migration
     *
     * @return void
     */
    public function handleManualMigration(): void {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'mds_manual_migration' ) ) {
            wp_die( 'Security check failed' );
        }
        
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        // Run migrations
        $results = $this->forceRunAllMigrations();
        
        // Return JSON response
        wp_send_json_success( [
            'message' => sprintf(
                'Migration completed! %d migrations run, %d pages migrated.',
                $results['migrations_run'],
                $results['pages_migrated']
            ),
            'results' => $results
        ] );
    }
    
    /**
     * Perform initial compatibility scan
     *
     * @return void
     */
    private function performInitialCompatibilityScan(): void {
        // Use the activation scanner to detect existing pages
        $this->activation_scanner->performActivationScan();
    }
    
    /**
     * Schedule background migration
     *
     * @return void
     */
    private function scheduleBackgroundMigration(): void {
        if ( ! wp_next_scheduled( 'mds_background_migration' ) ) {
            wp_schedule_single_event( time() + 60, 'mds_background_migration' );
        }
    }
    
    /**
     * Perform upgrade compatibility
     *
     * @param string $old_version
     * @return void
     */
    private function performUpgradeCompatibility( string $old_version ): void {
        // Version-specific upgrade compatibility
        if ( version_compare( $old_version, '2.5.12.0', '<' ) ) {
            // Major upgrade - run full migration
            $this->migrateExistingPagesToMetadataSystem();
        }
        
        // Always update shortcodes and blocks on upgrade
        $this->updateLegacyShortcodes();
        $this->migrateLegacyBlocks();
    }
    
    /**
     * Get compatibility status
     *
     * @return array
     */
    public function getCompatibilityStatus(): array {
        $migration_results = get_option( 'mds_migration_results', [] );
        $pending_migrations = $this->getPendingMigrations();
        
        return [
            'compatibility_version' => get_option( 'mds_compatibility_version', '0.0.0' ),
            'migration_completed' => ! empty( $migration_results ),
            'pending_migrations' => $pending_migrations,
            'migration_results' => $migration_results,
            'total_mds_pages' => count( $this->findPotentialMDSPages() ),
            'pages_with_metadata' => $this->metadata_manager->getTotalPagesWithMetadata()
        ];
    }
}
