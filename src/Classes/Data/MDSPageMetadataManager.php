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
     */
    public static function getInstance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
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
        return $this->repository->tablesExist();
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
                
            } catch ( Exception $e ) {
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
                    $validation_errors = $this->validatePageContent( $metadata );
                    
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
                
            } catch ( Exception $e ) {
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
        $has_mds_content = false;
        
        if ( has_shortcode( $content, 'milliondollarscript' ) ) {
            $has_mds_content = true;
        }
        
        if ( has_blocks( $content ) ) {
            $blocks = parse_blocks( $content );
            foreach ( $blocks as $block ) {
                if ( $block['blockName'] === 'milliondollarscript/mds-block' ) {
                    $has_mds_content = true;
                    break;
                }
            }
        }
        
        if ( !$has_mds_content ) {
            $errors[] = Language::get( 'Page no longer contains MDS content' );
        }
        
        // Validate page type specific requirements
        $errors = array_merge( $errors, $this->validatePageTypeRequirements( $metadata ) );
        
        return $errors;
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
} 