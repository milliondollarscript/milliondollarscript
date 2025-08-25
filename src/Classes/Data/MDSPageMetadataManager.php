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

use WP_Error;
use WP_Post;
use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

/**
 * High-level manager for MDS page metadata operations
 */
class MDSPageMetadataManager {
    
    private MDSPageMetadataRepository $repository;
    private MDSPageDetectionEngine $detection_engine;
    private static ?self $instance = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->repository = new MDSPageMetadataRepository();
        $this->detection_engine = new MDSPageDetectionEngine();
    }
    
    /**
     * Get singleton instance
     *
     * @return self
     * @throws \Exception if initialization fails
     */
    public static function getInstance(): self {
        if ( self::$instance === null ) {
            try {
                self::$instance = new self();
            } catch ( \Exception $e ) {
                error_log( 'MDS Metadata Manager: Failed to create instance: ' . $e->getMessage() );
                throw new \Exception( 'Metadata system initialization failed: ' . $e->getMessage() );
            }
        }
        return self::$instance;
    }
    
    /**
     * Safely get singleton instance without throwing exceptions
     *
     * @return self|null
     */
    public static function getInstanceSafely(): ?self {
        try {
            return self::getInstance();
        } catch ( \Exception $e ) {
            error_log( 'MDS Metadata Manager: Safe getInstance failed: ' . $e->getMessage() );
            return null;
        }
    }
    
    /**
     * Initialize the metadata system
     *
     * @return bool|WP_Error
     */
    public function initialize() {
        // Only create database tables if they don't exist
        if ( ! $this->repository->tablesExist() ) {
            $result = $this->repository->createTables();
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }
        
        // Register WordPress hooks
        $this->registerHooks();
        
        return true;
    }
    
    /**
     * Register WordPress hooks
     *
     * @return void
     */
    private function registerHooks(): void {
        // Hook into post save to update metadata
        add_action( 'save_post', [ $this, 'onPostSave' ], 10, 2 );
        
        // Hook into post deletion to clean up metadata
        add_action( 'before_delete_post', [ $this, 'onPostDelete' ] );
        
        // Hook into plugin activation to scan existing pages
        add_action( 'mds_plugin_activated', [ $this, 'scanExistingPages' ] );
        
        // Schedule periodic validation
        add_action( 'mds_validate_pages', [ $this, 'validatePages' ] );
        
        // Add admin notices for orphaned pages
        add_action( 'admin_notices', [ $this, 'showOrphanedPagesNotice' ] );
    }
    
    /**
     * Create or update metadata for a page
     *
     * @param int $post_id
     * @param string $page_type
     * @param string $creation_method
     * @param array $additional_data
     * @return MDSPageMetadata|WP_Error
     */
    public function createOrUpdateMetadata( int $post_id, string $page_type, string $creation_method = 'manual', array $additional_data = [] ) {
        // Validate input parameters
        if ( $post_id <= 0 ) {
            return new WP_Error( 'invalid_post_id', 'Post ID must be greater than 0' );
        }
        
        if ( empty( $page_type ) ) {
            return new WP_Error( 'invalid_page_type', 'Page type cannot be empty' );
        }
        
        // Check if the post actually exists
        $post = get_post( $post_id );
        if ( !$post ) {
            return new WP_Error( 'post_not_found', "Post {$post_id} does not exist" );
        }
        
        // Ensure database tables exist
        if ( !$this->repository->tablesExist() ) {
            $table_creation_result = $this->repository->createTables();
            if ( is_wp_error( $table_creation_result ) ) {
                error_log( "MDS Metadata: Failed to create database tables: " . $table_creation_result->get_error_message() );
                return $table_creation_result;
            }
        }
        
        // Check if metadata already exists
        $existing = $this->repository->findByPostId( $post_id );
        
        if ( $existing ) {
            // Update existing metadata
            $existing->page_type = $page_type;
            $existing->creation_method = $creation_method;
            $existing->touch();
            
            // Merge additional data
            if ( !empty( $additional_data ) ) {
                foreach ( $additional_data as $key => $value ) {
                    if ( property_exists( $existing, $key ) ) {
                        $existing->$key = $value;
                    }
                }
            }
            
            $result = $this->repository->save( $existing );
            if ( is_wp_error( $result ) ) {
                error_log( "MDS Metadata: Repository save FAILED for existing metadata: " . $result->get_error_message() );
                return $result;
            }
            return $existing;
        } else {
            // Create new metadata
            $metadata_data = array_merge( [
                'post_id' => $post_id,
                'page_type' => $page_type,
                'creation_method' => $creation_method,
                'mds_version' => MDS_VERSION,
                'content_type' => 'shortcode' // Default, will be detected
            ], $additional_data );
            
            $metadata = new MDSPageMetadata( $metadata_data );
            
            // Analyze content to determine content type and attributes
            $this->analyzePageContent( $metadata );
            
            $result = $this->repository->save( $metadata );
            if ( is_wp_error( $result ) ) {
                error_log( "MDS Metadata: Repository save FAILED for new metadata: " . $result->get_error_message() );
                return $result;
            }
            return $metadata;
        }
    }
    
    /**
     * Get metadata for a page
     *
     * @param int $post_id
     * @return MDSPageMetadata|null
     */
    public function getMetadata( int $post_id ): ?MDSPageMetadata {
        return $this->repository->findByPostId( $post_id );
    }
    
    /**
     * Check if a page has MDS metadata
     *
     * @param int $post_id
     * @return bool
     */
    public function hasMetadata( int $post_id ): bool {
        return $this->getMetadata( $post_id ) !== null;
    }
    
    /**
     * Update page status
     *
     * @param int $post_id
     * @param string $status Status to set ('active', 'inactive', 'orphaned', 'error')
     * @return bool|WP_Error
     */
    public function updatePageStatus( int $post_id, string $status ) {
        // Validate input parameters
        if ( $post_id <= 0 ) {
            return new WP_Error( 'invalid_post_id', 'Post ID must be greater than 0' );
        }
        
        $valid_statuses = [ 'active', 'inactive', 'orphaned', 'error' ];
        if ( !in_array( $status, $valid_statuses, true ) ) {
            return new WP_Error( 'invalid_status', 'Status must be one of: ' . implode( ', ', $valid_statuses ) );
        }
        
        // Get existing metadata
        $metadata = $this->getMetadata( $post_id );
        if ( !$metadata ) {
            return new WP_Error( 'metadata_not_found', "No metadata found for post ID {$post_id}" );
        }
        
        // Update status
        $metadata->status = $status;
        
        // Save updated metadata
        $result = $this->repository->save( $metadata );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return true;
    }
    
    /**
     * Delete metadata for a page
     *
     * @param int $post_id
     * @return bool|WP_Error
     */
    public function deleteMetadata( int $post_id ) {
        return $this->repository->deleteByPostId( $post_id );
    }
    
    /**
     * Get all MDS pages with metadata
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAllPages( array $filters = [], int $limit = 0, int $offset = 0 ): array {
        return $this->repository->findAll( $filters, $limit, $offset );
    }
    
    /**
     * Get statistics about MDS pages
     *
     * @return array
     */
    public function getStatistics(): array {
        return $this->repository->getStatistics();
    }
    
    /**
     * Check if database is ready (tables exist)
     *
     * @return bool
     */
    public function isDatabaseReady(): bool {
        try {
            return $this->repository->tablesExist();
        } catch ( \Exception $e ) {
            error_log( 'MDS Metadata Manager: Error checking database readiness: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Get the repository instance
     *
     * @return MDSPageMetadataRepository
     */
    public function getRepository(): MDSPageMetadataRepository {
        return $this->repository;
    }
    
    /**
     * Scan existing pages for MDS content
     *
     * @param bool $force_rescan
     * @return array Results of the scan
     */
    public function scanExistingPages( bool $force_rescan = false ): array {
        $results = [
            'scanned' => 0,
            'detected' => 0,
            'updated' => 0,
            'errors' => []
        ];
        
        // Get all published pages
        $pages = get_posts( [
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => $force_rescan ? [] : [
                [
                    'key' => '_mds_scanned',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ] );
        
        foreach ( $pages as $page ) {
            $results['scanned']++;
            
            try {
                // Use detection engine to analyze the page
                $detection_result = $this->detection_engine->detectMDSPage( $page->ID );
                
                if ( $detection_result['is_mds_page'] ) {
                    $results['detected']++;
                    
                    // Create or update metadata
                    $metadata_result = $this->createOrUpdateMetadata(
                        $page->ID,
                        $detection_result['page_type'],
                        'auto_detected',
                        [
                            'confidence_score' => $detection_result['confidence'],
                            'detected_patterns' => $detection_result['patterns'],
                            'content_type' => $detection_result['content_type']
                        ]
                    );
                    
                    if ( !is_wp_error( $metadata_result ) ) {
                        $results['updated']++;
                    } else {
                        $results['errors'][] = sprintf(
                            Language::get( 'Failed to save metadata for page %d: %s' ),
                            $page->ID,
                            $metadata_result->get_error_message()
                        );
                    }
                }
                
                // Mark as scanned
                update_post_meta( $page->ID, '_mds_scanned', time() );
                
            } catch ( \Exception $e ) {
                $results['errors'][] = sprintf(
                    Language::get( 'Error scanning page %d: %s' ),
                    $page->ID,
                    $e->getMessage()
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Validate all MDS pages
     *
     * @return array Validation results
     */
        public function validatePages(): array {
        $results = [
            'validated' => 0,
            'valid' => 0,
            'invalid' => 0,
            'orphaned' => 0,
            'errors' => []
        ];
        
        $all_metadata = $this->repository->findAll();
        
        foreach ( $all_metadata as $metadata ) {
            $results['validated']++;
            
            try {
                // Check if post still exists
                $post = get_post( $metadata->post_id );
                if ( !$post ) {
                    $metadata->status = 'orphaned';
                    $results['orphaned']++;
                } else {
                    // Validate the page content
                    $validation_errors = $this->validatePage( $metadata, $post );
                    
                    if ( empty( $validation_errors ) ) {
                        $metadata->status = 'active';
                        $results['valid']++;
                    } else {
                        $metadata->status = 'error';
                        $metadata->validation_errors = $validation_errors;
                        $results['invalid']++;
                    }
                }
                
                $metadata->markValidated( $metadata->validation_errors );
                $this->repository->save( $metadata );
                
            } catch ( \Exception $e ) {
                $results['errors'][] = sprintf(
                    Language::get( 'Error validating page %d: %s' ),
                    $metadata->post_id,
                    $e->getMessage()
                );
            }
        }
        
        return $results;
    }

    /**
     * Validate a single page and return errors
     *
     * @param object $metadata
     * @param \WP_Post $post
     * @return array
     */
    public function validatePage( $metadata, $post ): array {
        $page_id = $post->ID;
        $page_type = $metadata->page_type;

        // 1. Check for explicit page type meta field. If it exists, the page is considered valid.
        $meta_page_type = get_post_meta( $page_id, '_mds_page_type', true );
        if ( ! empty( $meta_page_type ) ) {
            return []; // Page is explicitly defined, so it's valid.
        }

        // 2. Check if the page is assigned in MDS options. If so, it's valid.
        $option_name = 'mds_' . str_replace( '-', '_', $page_type ) . '_page_id';
        $option_page_id = get_option( $option_name );
        if ( ! empty( $option_page_id ) && (int) $option_page_id === $page_id ) {
            return []; // Page is assigned in options, so it's valid.
        }
        
        // If we are here, the page is not explicitly defined. Fall back to content validation.
        $errors = [];
        $content = $post->post_content;
        
        // Check if this is a migrated page to apply graceful validation
        $is_migrated = $this->isMigratedPage( $metadata );
        
        // Check if this page has custom shortcodes that make it valid
        $has_custom_mds_shortcodes = $this->hasCustomMDSShortcodes( $content );
        
        // Check for missing shortcodes only if expected AND if the page doesn't have custom shortcodes
        if ( $metadata->content_type === 'shortcode' && 
             !has_shortcode( $content, 'milliondollarscript' ) && 
             !has_shortcode( $content, 'mds' ) && 
             !$has_custom_mds_shortcodes ) {
            
            // Only flag as error if this page type actually needs a shortcode
            if ( $this->pageTypeNeedsShortcode( $page_type ) ) {
                // For migrated pages, check for legacy shortcodes
                if ( $is_migrated && $this->hasLegacyMDSContent( $content ) ) {
                    // Skip this error for migrated pages with legacy content
                } else {
                    $errors[] = [
                        'page_id' => $metadata->post_id,
                        'page_title' => $post->post_title,
                        'type' => 'missing_shortcode',
                        'severity' => $is_migrated ? 'medium' : 'high',
                        'message' => Language::get( 'Missing MDS shortcode' ),
                        'description' => Language::get( 'This page should contain an MDS shortcode but none was found' ),
                        'auto_fixable' => true,
                        'suggested_fix' => sprintf( '[milliondollarscript type="%s"]', $page_type )
                    ];
                }
            }
        }
        
        // Check for missing blocks only if expected AND if the page doesn't have custom shortcodes
        if ( $metadata->content_type === 'block' && 
             !has_blocks( $content ) && 
             !$has_custom_mds_shortcodes ) {
            
            // Only flag as error if this page type actually needs a block
            if ( $this->pageTypeNeedsBlock( $page_type ) ) {
                // For migrated pages, be more forgiving
                if ( $is_migrated && $this->hasLegacyMDSContent( $content ) ) {
                    // Skip this error for migrated pages with legacy content
                } else {
                    $errors[] = [
                        'page_id' => $metadata->post_id,
                        'page_title' => $post->post_title,
                        'type' => 'missing_block',
                        'severity' => $is_migrated ? 'medium' : 'high',
                        'message' => Language::get( 'Missing MDS block' ),
                        'description' => Language::get( 'This page should contain an MDS block but none was found' ),
                        'auto_fixable' => true,
                        'suggested_fix' => sprintf( '<!-- wp:milliondollarscript/%s-block /-->', $page_type )
                    ];
                }
            }
        }
        
        // Validate that the detected page type matches the metadata
        $detection_result = $this->detection_engine->detectMDSPage( $post->ID );
        
        if ( $detection_result['is_mds_page'] && 
             $detection_result['page_type'] !== $page_type && 
             $detection_result['confidence'] >= 0.7 ) { // High confidence mismatch
            
            // If the page title contains the page type, we can be more confident
            if ( stripos( $post->post_title, $detection_result['page_type'] ) !== false ) {
                $errors[] = [
                    'page_id' => $metadata->post_id,
                    'page_title' => $post->post_title,
                    'type' => 'incorrect_page_type',
                    'severity' => 'medium',
                    'message' => Language::get( 'Incorrect page type detected' ),
                    'description' => sprintf(
                        Language::get( 'This page is registered as type "%s" but appears to be type "%s".' ),
                        $page_type,
                        $detection_result['page_type']
                    ),
                    'auto_fixable' => true,
                    'suggested_fix' => $detection_result['page_type']
                ];
            }
        }

        // If the page has a valid shortcode or block, but the page type is not detected, it should not be an error
        if ( ( has_shortcode( $content, 'milliondollarscript' ) || has_shortcode( $content, 'mds' ) || has_blocks( $content ) ) && ! $detection_result['is_mds_page'] ) {
            // This is not an error, so we do nothing.
        } else if ( ! $detection_result['is_mds_page'] ) {
            $errors[] = [
                'page_id' => $metadata->post_id,
                'page_title' => $post->post_title,
                'type' => 'not_mds_page',
                'severity' => 'high',
                'message' => Language::get( 'Not an MDS page' ),
                'description' => Language::get( 'This page does not appear to be an MDS page, but it is in the MDS page list.' ),
                'auto_fixable' => false,
                'suggested_fix' => Language::get( 'Remove this page from the MDS page list.' )
            ];
        }
        
        // Allow extensions to add custom validation errors or or filter existing ones
        $errors = apply_filters( 'mds_page_validation_errors', $errors, $metadata, $post );
        
        // Allow extensions to override validation for specific page types
        $skip_validation = apply_filters( 'mds_skip_page_validation', false, $page_type, $metadata, $post );
        if ( $skip_validation ) {
            return [];
        }
        
        return $errors;
    }
    
    /**
     * Check if page type needs a shortcode
     *
     * @param string $page_type
     * @return bool
     */
    private function pageTypeNeedsShortcode( string $page_type ): bool {
        // Core page types that definitely need shortcodes to function
        $shortcode_required = [ 'grid', 'order', 'payment', 'manage' ];
        $core_page_types = ['grid', 'order', 'write-ad', 'confirm-order', 'payment', 'manage', 'thank-you', 'list', 'upload', 'no-orders', 'stats'];
        
        // Only core page types have strict shortcode requirements
        // Custom page types (extensions) can have their own validation logic
        return in_array( $page_type, $shortcode_required ) && in_array( $page_type, $core_page_types );
    }
    
    /**
     * Check if page type needs a block
     *
     * @param string $page_type
     * @return bool
     */
    private function pageTypeNeedsBlock( string $page_type ): bool {
        // Core page types that definitely need blocks to function
        $block_required = [ 'grid', 'order', 'payment', 'manage' ];
        $core_page_types = ['grid', 'order', 'write-ad', 'confirm-order', 'payment', 'manage', 'thank-you', 'list', 'upload', 'no-orders', 'stats'];
        
        // Only core page types have strict block requirements
        // Custom page types (extensions) can have their own validation logic
        return in_array( $page_type, $block_required ) && in_array( $page_type, $core_page_types );
    }
    
    /**
     * Check if content contains legacy MDS content patterns
     *
     * @param string $content
     * @return bool
     */
    private function hasLegacyMDSContent( string $content ): bool {
        $legacy_patterns = [
            'mds-',
            'milliondollarscript',
            'million-dollar-script',
            'pixel-grid',
            'data-mds-',
            'mds_',
            'million_dollar_script'
        ];
        
        foreach ( $legacy_patterns as $pattern ) {
            if ( stripos( $content, $pattern ) !== false ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if content contains custom MDS shortcodes
     *
     * @param string $content
     * @return bool
     */
    private function hasCustomMDSShortcodes( string $content ): bool {
        // Check for custom MDS shortcodes (mds_*) - Enhanced pattern matching
        $custom_mds_patterns = [
            '/\[' . 'mds_([a-zA-Z0-9_-]+)([^\\\]]*)' . '\]/',          // Standard mds_ shortcodes
            '/\[' . 'MDS_([a-zA-Z0-9_-]+)([^\\\]]*)' . '\]/',          // Uppercase variants
            '/\[' . 'milliondollar_([a-zA-Z0-9_-]+)([^\\\]]*)' . '\]/', // Extended forms
            '/\[' . 'million_dollar_([a-zA-Z0-9_-]+)([^\\\]]*)' . '\]/', // Alternative forms
        ];
        
        foreach ( $custom_mds_patterns as $pattern ) {
            if ( preg_match( $pattern, $content ) ) {
                return true;
            }
        }
        
        // Check for extension shortcodes registered through filters
        $extension_shortcodes = apply_filters( 'mds_extension_shortcodes', [] );
        foreach ( $extension_shortcodes as $shortcode ) {
            if ( has_shortcode( $content, $shortcode ) ) {
                return true;
            }
        }
        
        // Allow extensions to register their own custom shortcode validation
        $custom_validation_result = apply_filters( 'mds_has_custom_shortcodes', false, $content );
        if ( $custom_validation_result ) {
            return true;
        }
        
        return false;
    }

    
    /**
     * Handle post save event
     *
     * @param int $post_id
     * @param WP_Post $post
     * @return void
     */
    public function onPostSave( int $post_id, WP_Post $post ): void {
        // Only handle pages
        if ( $post->post_type !== 'page' ) {
            return;
        }
        
        // Check if this page has MDS metadata
        $metadata = $this->getMetadata( $post_id );
        if ( $metadata ) {
            // Re-analyze content and update metadata
            $this->analyzePageContent( $metadata );
            $metadata->touch();
            $this->repository->save( $metadata );
        } else {
            // Check if this page should have MDS metadata
            $detection_result = $this->detection_engine->detectMDSPage( $post_id );
            if ( $detection_result['is_mds_page'] ) {
                $this->createOrUpdateMetadata(
                    $post_id,
                    $detection_result['page_type'],
                    'auto_detected',
                    [
                        'confidence_score' => $detection_result['confidence'],
                        'detected_patterns' => $detection_result['patterns'],
                        'content_type' => $detection_result['content_type']
                    ]
                );
            }
        }
    }
    
    /**
     * Handle post deletion event
     *
     * @param int $post_id
     * @return void
     */
    public function onPostDelete( int $post_id ): void {
        $this->deleteMetadata( $post_id );
    }
    
    /**
     * Show admin notice for orphaned pages
     *
     * @return void
     */
    public function showOrphanedPagesNotice(): void {
        $orphaned_count = $this->repository->count( [ 'status' => 'orphaned' ] );
        
        if ( $orphaned_count > 0 ) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>' . sprintf(
                Language::get( 'There are %d orphaned MDS pages in your database. <a href="%s">Clean them up</a>.' ),
                $orphaned_count,
                admin_url( 'admin.php?page=mds-page-management&action=cleanup' )
            ) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Analyze page content to determine content type and extract attributes
     *
     * @param MDSPageMetadata $metadata
     * @return void
     */
    private function analyzePageContent( MDSPageMetadata $metadata ): void {
        $post = get_post( $metadata->post_id );
        if ( !$post ) {
            return;
        }
        
        $content = $post->post_content;
        $has_shortcode = false;
        $has_block = false;
        $shortcode_attributes = [];
        $block_attributes = [];
        
        // Check for shortcodes
        if ( has_shortcode( $content, 'milliondollarscript' ) ) {
            $has_shortcode = true;
            $pattern = get_shortcode_regex( [ 'milliondollarscript' ] );
            if ( preg_match_all( '/' . $pattern . '/s', $content, $matches ) ) {
                foreach ( $matches[3] as $attrs ) {
                    $parsed_attrs = shortcode_parse_atts( $attrs );
                    if ( $parsed_attrs ) {
                        $shortcode_attributes[] = $parsed_attrs;
                    }
                }
            }
        }
        
        // Check for blocks
        if ( has_blocks( $content ) ) {
            $blocks = parse_blocks( $content );
            foreach ( $blocks as $block ) {
                if ( $block['blockName'] === 'milliondollarscript/mds-block' ) {
                    $has_block = true;
                    $block_attributes[] = $block['attrs'] ?? [];
                }
            }
        }
        
        // Determine content type
        if ( $has_shortcode && $has_block ) {
            $metadata->content_type = 'mixed';
        } elseif ( $has_block ) {
            $metadata->content_type = 'block';
        } elseif ( $has_shortcode ) {
            $metadata->content_type = 'shortcode';
        } else {
            $metadata->content_type = 'custom';
        }
        
        $metadata->shortcode_attributes = $shortcode_attributes;
        $metadata->block_attributes = $block_attributes;
    }
    
    /**
     * Validate page content
     *
     * @param MDSPageMetadata $metadata
     * @return array Validation errors
     */
    private function validatePageContent( MDSPageMetadata $metadata ): array {
        $errors = [];
        $post = get_post( $metadata->post_id );

        if ( !$post ) {
            $errors[] = Language::get( 'Post no longer exists' );
            return $errors;
        }

        // Check if page still contains MDS content
        $content = $post->post_content;
        $has_mds_content = $this->hasMDSContent( $content );

        if ( !$has_mds_content ) {
            $errors[] = Language::get( 'Page no longer contains MDS content' );
        }

        // Validate page type specific requirements
        $errors = array_merge( $errors, $this->validatePageTypeRequirements( $metadata ) );

        return $errors;
    }

    /**
     * Check if content contains MDS-related content
     *
     * @param string $content
     * @param ?MDSPageMetadata $metadata
     * @return bool
     */
    private function hasMDSContent( string $content ): bool {
        // Check for various MDS shortcodes
        $shortcode_patterns = ['milliondollarscript', 'mds', 'million_dollar_script'];
        foreach ( $shortcode_patterns as $shortcode ) {
            if ( has_shortcode( $content, $shortcode ) ) {
                return true;
            }
        }

        // Check for MDS blocks
        if ( has_blocks( $content ) ) {
            $blocks = parse_blocks( $content );
            $mds_blocks = [
                'milliondollarscript/mds-block',
                'mds/grid-block',
                'mds/order-block',
                'mds/stats-block',
                'milliondollarscript/grid',
                'milliondollarscript/display'
            ];

            foreach ( $blocks as $block ) {
                if ( in_array( $block['blockName'], $mds_blocks ) ) {
                    return true;
                }
            }
        }

        // Check for MDS-related patterns in content
        $patterns = [
            'mds-shortcode-container',
            'milliondollarscript-grid',
            'mds-pixel-grid',
            'million-dollar-script',
            'data-mds-params',
            'mds-grid-container'
        ];

        foreach ( $patterns as $pattern ) {
            if ( strpos( $content, $pattern ) !== false ) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Check if a page was migrated from an older version
     *
     * @param MDSPageMetadata $metadata
     * @return bool
     */
    private function isMigratedPage( MDSPageMetadata $metadata ): bool {
        $page_config = $metadata->page_config ?? [];
        return isset( $page_config['migrated_from_option'] ) && $page_config['migrated_from_option'] === true;
    }
    
    /**
     * Apply graceful validation for migrated pages
     *
     * @param MDSPageMetadata $metadata
     * @param array $validation_errors
     * @return array Filtered validation errors
     */
    private function applyGracefulValidation( MDSPageMetadata $metadata, array $validation_errors ): array {
        $filtered_errors = [];
        $post = get_post( $metadata->post_id );
        
        if ( !$post ) {
            return $validation_errors; // Can't be graceful if post doesn't exist
        }
        
        foreach ( $validation_errors as $error ) {
            $should_keep_error = true;
            
            // Be more forgiving for "no MDS content" errors on migrated pages
            if ( $error === Language::get( 'Page no longer contains MDS content' ) ) {
                // For migrated pages, check if they have any MDS-related patterns
                $content = $post->post_content;
                $has_legacy_patterns = false;
                
                // Check for legacy MDS patterns that might not be caught by strict validation
                $legacy_patterns = [
                    'mds-',
                    'milliondollarscript',
                    'million-dollar-script',
                    'pixel-grid',
                    'data-mds-',
                    'mds_'
                ];
                
                foreach ( $legacy_patterns as $pattern ) {
                    if ( stripos( $content, $pattern ) !== false ) {
                        $has_legacy_patterns = true;
                        break;
                    }
                }
                
                // If we found legacy patterns, don't flag as error
                if ( $has_legacy_patterns ) {
                    $should_keep_error = false;
                }
                
                // Also check if the page has the expected option reference
                $page_config = $metadata->page_config ?? [];
                if ( isset( $page_config['option_key'] ) ) {
                    $option_value = get_option( '_mds_' . $page_config['option_key'] );
                    if ( $option_value == $metadata->post_id ) {
                        $should_keep_error = false; // Still referenced by option
                    }
                }
            }
            
            if ( $should_keep_error ) {
                $filtered_errors[] = $error;
            }
        }
        
        return $filtered_errors;
    }
    
    /**
     * Validate page type specific requirements
     *
     * @param MDSPageMetadata $metadata
     * @return array Validation errors
     */
    private function validatePageTypeRequirements( MDSPageMetadata $metadata ): array {
        $errors = [];
        
        // Add page type specific validation logic here
        switch ( $metadata->page_type ) {
            case 'grid':
                // Grid pages should have grid configuration
                if ( empty( $metadata->page_config['grid_size'] ) ) {
                    $errors[] = Language::get( 'Grid page missing grid size configuration' );
                }
                break;
                
            case 'order':
                // Order pages should have payment configuration
                if ( empty( $metadata->page_config['payment_methods'] ) ) {
                    $errors[] = Language::get( 'Order page missing payment methods configuration' );
                }
                break;
                
            // Add more page type validations as needed
        }
        
        return $errors;
    }
    
    /**
     * Get total count of pages with metadata
     *
     * @return int
     */
    public function getTotalPagesWithMetadata(): int {
        return $this->repository->count();
    }
    
    /**
     * Update page type for existing metadata
     *
     * @param int $post_id
     * @param string $page_type
     * @return bool|WP_Error
     */
    public function updatePageType( int $post_id, string $page_type ) {
        $metadata = $this->repository->findByPostId( $post_id );
        if ( !$metadata ) {
            return new WP_Error( 'metadata_not_found', Language::get( 'No metadata found for page' ) );
        }
        
        $metadata->page_type = $page_type;
        $metadata->touch(); // Update the last_validated timestamp
        
        return $this->repository->save( $metadata );
    }
    
    /**
     * Update page configuration for existing metadata
     *
     * @param int $post_id
     * @param array $config
     * @return bool|WP_Error
     */
    public function updatePageConfiguration( int $post_id, array $config ) {
        $metadata = $this->repository->findByPostId( $post_id );
        if ( !$metadata ) {
            return new WP_Error( 'metadata_not_found', Language::get( 'No metadata found for page' ) );
        }
        
        // Merge new configuration with existing
        $metadata->page_config = array_merge( $metadata->page_config, $config );
        $metadata->touch(); // Update the last_validated timestamp
        
        return $this->repository->save( $metadata );
    }
} 