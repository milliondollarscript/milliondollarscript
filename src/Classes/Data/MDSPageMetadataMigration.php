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
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

/**
 * Migration class for adding metadata to existing MDS pages
 */
class MDSPageMetadataMigration {
    
    private MDSPageMetadataManager $metadata_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->metadata_manager = MDSPageMetadataManager::getInstance();
    }
    
    /**
     * Run the migration for existing installations
     *
     * @return array Migration results
     */
    public function migrate(): array {
        $results = [
            'migrated' => 0,
            'skipped' => 0,
            'errors' => [],
            'pages' => []
        ];
        
        // Get all MDS page definitions
        $page_definitions = Utility::get_pages();
        
        foreach ( $page_definitions as $page_type => $data ) {
            $option_key = $data['option'];
            $page_id = get_option( '_mds_' . $option_key );
            
            if ( empty( $page_id ) ) {
                continue; // No page assigned to this option
            }
            
            $post = get_post( $page_id );
            if ( !$post ) {
                $results['errors'][] = sprintf(
                    Language::get( 'Page ID %d for %s does not exist' ),
                    $page_id,
                    $page_type
                );
                continue;
            }
            
            // Check if metadata already exists
            if ( $this->metadata_manager->hasMetadata( $page_id ) ) {
                $results['skipped']++;
                $results['pages'][] = [
                    'page_id' => $page_id,
                    'page_type' => $page_type,
                    'title' => $post->post_title,
                    'status' => 'skipped',
                    'reason' => 'Metadata already exists'
                ];
                continue;
            }
            
            // Migrate this page
            $migration_result = $this->migratePage( $page_id, $page_type, $data, $post );
            
            if ( is_wp_error( $migration_result ) ) {
                $results['errors'][] = sprintf(
                    Language::get( 'Failed to migrate page %d (%s): %s' ),
                    $page_id,
                    $page_type,
                    $migration_result->get_error_message()
                );
                $results['pages'][] = [
                    'page_id' => $page_id,
                    'page_type' => $page_type,
                    'title' => $post->post_title,
                    'status' => 'error',
                    'reason' => $migration_result->get_error_message()
                ];
            } else {
                $results['migrated']++;
                $results['pages'][] = [
                    'page_id' => $page_id,
                    'page_type' => $page_type,
                    'title' => $post->post_title,
                    'status' => 'migrated',
                    'confidence' => $migration_result['confidence']
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Migrate a single page
     *
     * @param int $page_id
     * @param string $page_type
     * @param array $page_data
     * @param WP_Post $post
     * @return array|WP_Error
     */
    private function migratePage( int $page_id, string $page_type, array $page_data, WP_Post $post ) {
        // Analyze the page content to determine content type and extract attributes
        $content_analysis = $this->analyzePageContent( $post->post_content );
        
        // Determine creation method based on available information
        $creation_method = $this->inferCreationMethod( $post, $content_analysis );
        
        // Build metadata
        $metadata_data = [
            'page_type' => $page_type,
            'creation_method' => $creation_method,
            'creation_source' => 'migration_v' . MDS_VERSION,
            'mds_version' => MDS_VERSION,
            'content_type' => $content_analysis['content_type'],
            'status' => 'active',
            'confidence_score' => $content_analysis['confidence'],
            'shortcode_attributes' => $content_analysis['shortcode_attributes'],
            'block_attributes' => $content_analysis['block_attributes'],
            'detected_patterns' => $content_analysis['patterns'],
            'page_config' => [
                'option_key' => $page_data['option'],
                'migrated_from_option' => true
            ],
            'display_settings' => $content_analysis['display_settings'],
            'integration_settings' => $this->getThemeIntegrationSettings( $page_id )
        ];
        
        // Create metadata
        $result = $this->metadata_manager->createMetadata( $page_id, $metadata_data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return [
            'confidence' => $content_analysis['confidence'],
            'content_type' => $content_analysis['content_type'],
            'creation_method' => $creation_method
        ];
    }
    
    /**
     * Analyze page content to extract MDS-related information
     *
     * @param string $content
     * @return array
     */
    private function analyzePageContent( string $content ): array {
        $analysis = [
            'content_type' => 'custom',
            'confidence' => 0.5,
            'shortcode_attributes' => [],
            'block_attributes' => [],
            'patterns' => [],
            'display_settings' => []
        ];
        
        // Check for shortcodes
        $shortcode_patterns = [
            '/\[milliondollarscript([^\]]*)\]/',
            '/\[mds([^\]]*)\]/',
            '/\[million_dollar_script([^\]]*)\]/'
        ];
        
        foreach ( $shortcode_patterns as $pattern ) {
            if ( preg_match( $pattern, $content, $matches ) ) {
                $analysis['content_type'] = 'shortcode';
                $analysis['confidence'] = 1.0;
                $analysis['patterns'][] = 'shortcode_found';
                
                // Parse shortcode attributes
                if ( !empty( $matches[1] ) ) {
                    $attributes = $this->parseShortcodeAttributes( $matches[1] );
                    $analysis['shortcode_attributes'] = $attributes;
                    
                    // Extract display settings
                    if ( isset( $attributes['align'] ) ) {
                        $analysis['display_settings']['align'] = $attributes['align'];
                    }
                    if ( isset( $attributes['width'] ) ) {
                        $analysis['display_settings']['width'] = $attributes['width'];
                    }
                    if ( isset( $attributes['height'] ) ) {
                        $analysis['display_settings']['height'] = $attributes['height'];
                    }
                }
                break;
            }
        }
        
        // Check for blocks if no shortcode found
        if ( $analysis['content_type'] === 'custom' ) {
            $blocks = parse_blocks( $content );
            $mds_blocks = [
                'mds/grid-block',
                'mds/order-block',
                'mds/stats-block',
                'milliondollarscript/grid',
                'milliondollarscript/display'
            ];
            
            foreach ( $blocks as $block ) {
                if ( in_array( $block['blockName'], $mds_blocks ) ) {
                    $analysis['content_type'] = 'block';
                    $analysis['confidence'] = 1.0;
                    $analysis['patterns'][] = 'block_found';
                    $analysis['block_attributes'] = $block['attrs'] ?? [];
                    break;
                }
            }
        }
        
        // Check for mixed content
        if ( $analysis['content_type'] === 'shortcode' ) {
            $blocks = parse_blocks( $content );
            foreach ( $blocks as $block ) {
                if ( in_array( $block['blockName'], [
                    'mds/grid-block', 'mds/order-block', 'mds/stats-block',
                    'milliondollarscript/grid', 'milliondollarscript/display'
                ] ) ) {
                    $analysis['content_type'] = 'mixed';
                    $analysis['patterns'][] = 'mixed_content';
                    break;
                }
            }
        }
        
        // Check for other MDS patterns
        $content_patterns = [
            'mds-shortcode-container' => 0.7,
            'milliondollarscript-grid' => 0.8,
            'mds-pixel-grid' => 0.8,
            'million-dollar-script' => 0.6,
            'data-mds-params' => 0.9
        ];
        
        foreach ( $content_patterns as $pattern => $confidence ) {
            if ( strpos( $content, $pattern ) !== false ) {
                $analysis['patterns'][] = $pattern;
                if ( $analysis['content_type'] === 'custom' ) {
                    $analysis['confidence'] = max( $analysis['confidence'], $confidence );
                }
            }
        }
        
        return $analysis;
    }
    
    /**
     * Parse shortcode attributes from attribute string
     *
     * @param string $attr_string
     * @return array
     */
    private function parseShortcodeAttributes( string $attr_string ): array {
        $attributes = [];
        
        // Remove leading/trailing whitespace
        $attr_string = trim( $attr_string );
        
        // Parse attributes using WordPress shortcode_parse_atts function
        $parsed = shortcode_parse_atts( $attr_string );
        
        if ( is_array( $parsed ) ) {
            $attributes = $parsed;
        }
        
        return $attributes;
    }
    
    /**
     * Infer creation method based on available information
     *
     * @param WP_Post $post
     * @param array $content_analysis
     * @return string
     */
    private function inferCreationMethod( WP_Post $post, array $content_analysis ): string {
        // Check if post was created recently (within last hour) - might be wizard
        $created_time = strtotime( $post->post_date );
        $current_time = time();
        
        if ( ( $current_time - $created_time ) < 3600 ) {
            return 'wizard';
        }
        
        // Check for specific patterns that indicate creation method
        if ( in_array( 'shortcode_found', $content_analysis['patterns'] ) ) {
            // Standard shortcode format suggests wizard or manual creation
            $shortcode_attrs = $content_analysis['shortcode_attributes'];
            
            // Check for default wizard values
            if ( isset( $shortcode_attrs['align'] ) && $shortcode_attrs['align'] === 'center' &&
                 isset( $shortcode_attrs['width'] ) && $shortcode_attrs['width'] === '100%' ) {
                return 'wizard';
            }
        }
        
        // Default to auto-detected for existing pages
        return 'auto_detected';
    }
    
    /**
     * Get theme integration settings for a page
     *
     * @param int $page_id
     * @return array
     */
    private function getThemeIntegrationSettings( int $page_id ): array {
        $settings = [];
        
        // Get current theme
        $current_theme = wp_get_theme();
        $settings['theme_name'] = $current_theme->get( 'Name' );
        
        // Check for Divi theme settings
        $divi_layout = get_post_meta( $page_id, '_et_pb_page_layout', true );
        if ( !empty( $divi_layout ) ) {
            $settings['divi_layout'] = $divi_layout;
        }
        
        // Check for other theme-specific meta
        $template = get_post_meta( $page_id, '_wp_page_template', true );
        if ( !empty( $template ) && $template !== 'default' ) {
            $settings['page_template'] = $template;
        }
        
        return $settings;
    }
    
    /**
     * Check if migration is needed
     *
     * @return bool
     */
    public function isMigrationNeeded(): bool {
        $page_definitions = Utility::get_pages();
        
        foreach ( $page_definitions as $page_type => $data ) {
            $option_key = $data['option'];
            $page_id = get_option( '_mds_' . $option_key );
            
            if ( !empty( $page_id ) && get_post( $page_id ) ) {
                if ( !$this->metadata_manager->hasMetadata( $page_id ) ) {
                    return true; // Found a page without metadata
                }
            }
        }
        
        return false; // All pages have metadata or no pages exist
    }
    
    /**
     * Get migration status
     *
     * @return array
     */
    public function getMigrationStatus(): array {
        $page_definitions = Utility::get_pages();
        $status = [
            'total_pages' => 0,
            'pages_with_metadata' => 0,
            'pages_without_metadata' => 0,
            'missing_pages' => 0,
            'pages' => []
        ];
        
        foreach ( $page_definitions as $page_type => $data ) {
            $option_key = $data['option'];
            $page_id = get_option( '_mds_' . $option_key );
            
            if ( empty( $page_id ) ) {
                $status['missing_pages']++;
                $status['pages'][] = [
                    'page_type' => $page_type,
                    'option_key' => $option_key,
                    'status' => 'missing',
                    'page_id' => null
                ];
                continue;
            }
            
            $post = get_post( $page_id );
            if ( !$post ) {
                $status['missing_pages']++;
                $status['pages'][] = [
                    'page_type' => $page_type,
                    'option_key' => $option_key,
                    'status' => 'invalid',
                    'page_id' => $page_id
                ];
                continue;
            }
            
            $status['total_pages']++;
            
            if ( $this->metadata_manager->hasMetadata( $page_id ) ) {
                $status['pages_with_metadata']++;
                $status['pages'][] = [
                    'page_type' => $page_type,
                    'option_key' => $option_key,
                    'status' => 'has_metadata',
                    'page_id' => $page_id,
                    'title' => $post->post_title
                ];
            } else {
                $status['pages_without_metadata']++;
                $status['pages'][] = [
                    'page_type' => $page_type,
                    'option_key' => $option_key,
                    'status' => 'needs_metadata',
                    'page_id' => $page_id,
                    'title' => $post->post_title
                ];
            }
        }
        
        return $status;
    }
} 