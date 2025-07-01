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

use WP_Post;
use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

/**
 * Engine for detecting MDS pages automatically
 */
class MDSPageDetectionEngine {
    
    // Detection patterns for different page types
    private array $page_type_patterns = [
        'grid' => [
            'shortcode_attrs' => ['type' => 'grid', 'display' => 'grid'],
            'content_keywords' => ['pixel', 'grid', 'buy pixel', 'advertising space'],
            'title_keywords' => ['grid', 'pixel', 'advertising'],
            'block_names' => ['milliondollarscript/grid-block', 'carbon-fields/million-dollar-script'],
            'css_classes' => ['mds-grid', 'pixel-grid', 'advertising-grid']
        ],
        'order' => [
            'shortcode_attrs' => ['type' => 'order', 'display' => 'order'],
            'content_keywords' => ['order', 'purchase', 'buy', 'payment', 'checkout'],
            'title_keywords' => ['order', 'purchase', 'buy'],
            'block_names' => ['milliondollarscript/order-block'],
            'css_classes' => ['mds-order', 'order-form', 'purchase-form']
        ],
        'write-ad' => [
            'shortcode_attrs' => ['type' => 'write-ad', 'display' => 'write-ad'],
            'content_keywords' => ['write ad', 'create ad', 'advertisement', 'ad content'],
            'title_keywords' => ['write', 'create', 'ad', 'advertisement'],
            'block_names' => ['milliondollarscript/write-ad-block'],
            'css_classes' => ['mds-write-ad', 'ad-form', 'write-advertisement']
        ],
        'confirm-order' => [
            'shortcode_attrs' => ['type' => 'confirm-order', 'display' => 'confirm-order'],
            'content_keywords' => ['confirm', 'confirmation', 'order confirmation', 'verify order'],
            'title_keywords' => ['confirm', 'confirmation', 'verify'],
            'block_names' => ['milliondollarscript/confirm-order-block'],
            'css_classes' => ['mds-confirm-order', 'order-confirmation']
        ],
        'payment' => [
            'shortcode_attrs' => ['type' => 'payment', 'display' => 'payment'],
            'content_keywords' => ['payment', 'pay', 'paypal', 'stripe', 'credit card'],
            'title_keywords' => ['payment', 'pay', 'checkout'],
            'block_names' => ['milliondollarscript/payment-block'],
            'css_classes' => ['mds-payment', 'payment-form', 'checkout-form']
        ],
        'manage' => [
            'shortcode_attrs' => ['type' => 'manage', 'display' => 'manage'],
            'content_keywords' => ['manage', 'dashboard', 'account', 'my orders', 'my ads'],
            'title_keywords' => ['manage', 'dashboard', 'account', 'my'],
            'block_names' => ['milliondollarscript/manage-block'],
            'css_classes' => ['mds-manage', 'user-dashboard', 'account-management']
        ],
        'thank-you' => [
            'shortcode_attrs' => ['type' => 'thank-you', 'display' => 'thank-you'],
            'content_keywords' => ['thank you', 'thanks', 'success', 'completed', 'order complete'],
            'title_keywords' => ['thank', 'thanks', 'success', 'complete'],
            'block_names' => ['milliondollarscript/thank-you-block'],
            'css_classes' => ['mds-thank-you', 'success-page', 'order-success']
        ],
        'list' => [
            'shortcode_attrs' => ['type' => 'list', 'display' => 'list'],
            'content_keywords' => ['list', 'directory', 'advertisers', 'ads list'],
            'title_keywords' => ['list', 'directory', 'advertisers'],
            'block_names' => ['milliondollarscript/list-block'],
            'css_classes' => ['mds-list', 'ads-list', 'advertiser-list']
        ],
        'upload' => [
            'shortcode_attrs' => ['type' => 'upload', 'display' => 'upload'],
            'content_keywords' => ['upload', 'file upload', 'image upload', 'banner upload'],
            'title_keywords' => ['upload', 'file', 'image', 'banner'],
            'block_names' => ['milliondollarscript/upload-block'],
            'css_classes' => ['mds-upload', 'file-upload', 'image-upload']
        ],
        'no-orders' => [
            'shortcode_attrs' => ['type' => 'no-orders', 'display' => 'no-orders'],
            'content_keywords' => ['no orders', 'empty', 'no purchases', 'no ads'],
            'title_keywords' => ['no orders', 'empty', 'none'],
            'block_names' => ['milliondollarscript/no-orders-block'],
            'css_classes' => ['mds-no-orders', 'empty-state', 'no-content']
        ]
    ];
    
    // Minimum confidence threshold for detection
    private float $min_confidence = 0.3;
    
    /**
     * Detect if a page is an MDS page and determine its type
     *
     * @param int $post_id
     * @return array Detection result
     */
    public function detectMDSPage( int $post_id ): array {
        $post = get_post( $post_id );
        if ( !$post ) {
            return $this->createDetectionResult( false );
        }
        
        $detection_data = [
            'post_id' => $post_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'slug' => $post->post_name
        ];
        
        // Run detection methods
        $shortcode_result = $this->detectByShortcode( $detection_data );
        $block_result = $this->detectByBlocks( $detection_data );
        $content_result = $this->detectByContent( $detection_data );
        $meta_result = $this->detectByMeta( $post_id );
        $option_result = $this->detectByOptionAssignment( $post_id );
        
        // Combine results and calculate confidence
        $combined_result = $this->combineDetectionResults( [
            $shortcode_result,
            $block_result,
            $content_result,
            $meta_result,
            $option_result
        ] );
        
        return $combined_result;
    }
    
    /**
     * Detect MDS page by shortcode analysis
     *
     * @param array $detection_data
     * @return array
     */
    private function detectByShortcode( array $detection_data ): array {
        $content = $detection_data['content'];
        $patterns = [];
        $confidence = 0.0;
        $page_type = null;
        
        // Check for MDS shortcodes
        if ( has_shortcode( $content, 'milliondollarscript' ) ) {
            $confidence += 0.8; // High confidence for MDS shortcode
            
            // Extract shortcode attributes
            $pattern = get_shortcode_regex( [ 'milliondollarscript' ] );
            if ( preg_match_all( '/' . $pattern . '/s', $content, $matches ) ) {
                foreach ( $matches[3] as $attrs ) {
                    $parsed_attrs = shortcode_parse_atts( $attrs );
                    if ( $parsed_attrs ) {
                        $patterns[] = [
                            'type' => 'shortcode',
                            'attributes' => $parsed_attrs
                        ];
                        
                        // Determine page type from attributes
                        $detected_type = $this->determinePageTypeFromShortcode( $parsed_attrs );
                        if ( $detected_type ) {
                            $page_type = $detected_type;
                            $confidence += 0.1;
                        }
                    }
                }
            }
        }
        
        // Check for custom MDS shortcodes (mds_*) - Enhanced pattern matching
        $custom_mds_patterns = [
            '/\[mds_([a-zA-Z0-9_-]+)([^\]]*)\]/',          // Standard mds_ shortcodes
            '/\[MDS_([a-zA-Z0-9_-]+)([^\]]*)\]/',          // Uppercase variants
            '/\[milliondollar_([a-zA-Z0-9_-]+)([^\]]*)\]/', // Extended forms
            '/\[million_dollar_([a-zA-Z0-9_-]+)([^\]]*)\]/', // Alternative forms
        ];
        
        foreach ( $custom_mds_patterns as $pattern ) {
            if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
                foreach ( $matches as $match ) {
                    $shortcode_name = strtolower( $match[1] ); // Normalize to lowercase
                    $attributes_string = trim( $match[2] ?? '' );
                    $confidence += 0.9; // High confidence for custom MDS shortcodes
                
                // Parse shortcode attributes properly
                $parsed_attrs = [];
                if ( !empty( $attributes_string ) ) {
                    $parsed_attrs = shortcode_parse_atts( $attributes_string );
                }
                
                $patterns[] = [
                    'type' => 'shortcode',
                    'shortcode_name' => 'mds_' . $shortcode_name,
                    'attributes' => $parsed_attrs,
                    'custom_type' => $this->extractPageTypeFromShortcodeName( $shortcode_name )
                ];
                
                // Set page type based on shortcode name
                if ( !$page_type ) {
                    $page_type = $this->extractPageTypeFromShortcodeName( $shortcode_name );
                }
                }
            }
        }
        
        // Check for legacy shortcodes - Expanded list
        $legacy_shortcodes = [ 
            'mds', 'million_dollar_script', 'pixel_grid', 'milliondollarscript',
            'pixel_advertising', 'ad_grid', 'mds_grid', 'pixel_board'
        ];
        foreach ( $legacy_shortcodes as $shortcode ) {
            if ( has_shortcode( $content, $shortcode ) ) {
                $confidence += 0.7; // Increased confidence for legacy shortcodes
                $patterns[] = [
                    'type' => 'legacy_shortcode',
                    'shortcode' => $shortcode
                ];
                
                // Set generic page type for legacy shortcodes if not already set
                if ( !$page_type ) {
                    $page_type = 'grid'; // Default for legacy shortcodes
                }
            }
        }
        
        return [
            'method' => 'shortcode',
            'confidence' => min( $confidence, 1.0 ),
            'page_type' => $page_type,
            'patterns' => $patterns
        ];
    }
    
    /**
     * Detect MDS page by block analysis
     *
     * @param array $detection_data
     * @return array
     */
    private function detectByBlocks( array $detection_data ): array {
        $content = $detection_data['content'];
        $patterns = [];
        $confidence = 0.0;
        $page_type = null;
        
        if ( has_blocks( $content ) ) {
            $blocks = parse_blocks( $content );
            $this->processBlocksRecursively( $blocks, $patterns, $confidence, $page_type );
        }
        
        return [
            'method' => 'blocks',
            'confidence' => min( $confidence, 1.0 ),
            'page_type' => $page_type,
            'patterns' => $patterns
        ];
    }

    private function processBlocksRecursively( array $blocks, array &$patterns, float &$confidence, &$page_type ): void {
        foreach ( $blocks as $block ) {
            if ( empty( $block['blockName'] ) ) {
                continue;
            }
            
            // Check for MDS blocks
            if ( strpos( $block['blockName'], 'milliondollarscript/' ) === 0 || $block['blockName'] === 'carbon-fields/million-dollar-script' ) {
                $confidence += 0.8; // High confidence for MDS blocks
                $patterns[] = [
                    'type' => 'block',
                    'block_name' => $block['blockName'],
                    'attributes' => $block['attrs'] ?? []
                ];
                
                // Determine page type from block
                $detected_type = $this->determinePageTypeFromBlock( $block );
                if ( $detected_type ) {
                    $page_type = $detected_type;
                    $confidence += 0.1;
                }
            }
            
            // Check for legacy blocks with improved pattern matching
            $legacy_blocks = [ 'mds/', 'pixel-grid/', 'million-dollar-script/', 'carbon-fields/mds-', 'mds-' ];
            foreach ( $legacy_blocks as $prefix ) {
                if ( strpos( $block['blockName'], $prefix ) === 0 ) {
                    $confidence += 0.7; // Increased from 0.6 for legacy blocks
                    $patterns[] = [
                        'type' => 'legacy_block',
                        'block_name' => $block['blockName'],
                        'attributes' => $block['attrs'] ?? []
                    ];
                }
            }
            
            // Check for custom MDS block patterns
            if ( preg_match( '/(?:mds|million.?dollar.?script|pixel.?grid)/i', $block['blockName'] ) ) {
                $confidence += 0.6;
                $patterns[] = [
                    'type' => 'custom_block',
                    'block_name' => $block['blockName'],
                    'attributes' => $block['attrs'] ?? []
                ];
            }
            
            // Recursively process inner blocks
            if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
                $this->processBlocksRecursively( $block['innerBlocks'], $patterns, $confidence, $page_type );
            }
        }
    }
    
    /**
     * Detect MDS page by content analysis
     *
     * @param array $detection_data
     * @return array
     */
    private function detectByContent( array $detection_data ): array {
        $content = strtolower( $detection_data['content'] );
        $title = strtolower( $detection_data['title'] );
        $patterns = [];
        $confidence = 0.0;
        $page_type = null;
        $best_match_score = 0.0;
        
        // Check for general MDS patterns first
        $general_mds_patterns = [
            'million dollar script' => 0.3,
            'milliondollarscript' => 0.3,
            'pixel advertising' => 0.25,
            'ad pixel' => 0.2,
            'purchase pixel' => 0.3,
            'advertise here' => 0.15,
            'buy pixels' => 0.25,
            'pixel grid' => 0.3,
        ];
        
        foreach ( $general_mds_patterns as $pattern => $score ) {
            if ( strpos( $content, $pattern ) !== false || strpos( $title, $pattern ) !== false ) {
                $confidence += $score;
                $patterns[] = [
                    'type' => 'general_mds_pattern',
                    'pattern' => $pattern,
                    'score' => $score
                ];
            }
        }
        
        // Analyze content for each page type
        foreach ( $this->page_type_patterns as $type => $type_patterns ) {
            $type_score = 0.0;
            $type_matches = [];
            
            // Check content keywords
            foreach ( $type_patterns['content_keywords'] as $keyword ) {
                if ( strpos( $content, strtolower( $keyword ) ) !== false ) {
                    $type_score += 0.15; // Increased from 0.1
                    $type_matches[] = [
                        'type' => 'content_keyword',
                        'keyword' => $keyword
                    ];
                }
            }
            
            // Check title keywords
            foreach ( $type_patterns['title_keywords'] as $keyword ) {
                if ( strpos( $title, strtolower( $keyword ) ) !== false ) {
                    $type_score += 0.25; // Increased from 0.15 - Title keywords are more significant
                    $type_matches[] = [
                        'type' => 'title_keyword',
                        'keyword' => $keyword
                    ];
                }
            }
            
            // Check CSS classes
            foreach ( $type_patterns['css_classes'] as $css_class ) {
                if ( strpos( $content, $css_class ) !== false ) {
                    $type_score += 0.3; // Increased from 0.2 - CSS classes are strong indicators
                    $type_matches[] = [
                        'type' => 'css_class',
                        'class' => $css_class
                    ];
                }
            }
            
            // If this type has the highest score, use it
            if ( $type_score > $best_match_score ) {
                $best_match_score = $type_score;
                $page_type = $type;
                // Combine general patterns with type-specific patterns
                $patterns = array_merge( $patterns, $type_matches );
            }
        }
        
        // Final confidence is combination of general MDS patterns + best type match
        $final_confidence = $confidence + $best_match_score;
        $confidence = min( $final_confidence, 0.9 ); // Increased max confidence for content analysis
        
        return [
            'method' => 'content',
            'confidence' => $confidence,
            'page_type' => $page_type,
            'patterns' => $patterns
        ];
    }
    
    /**
     * Detect MDS page by post meta analysis
     *
     * @param int $post_id
     * @return array
     */
    private function detectByMeta( int $post_id ): array {
        $patterns = [];
        $confidence = 0.0;
        $page_type = null;
        
        // Check for MDS-specific meta keys
        $mds_meta_keys = [
            '_mds_page_type',
            '_mds_creation_method',
            '_mds_content_type',
            '_mds_status',
            '_mds_confidence_score',
            '_mds_last_validated'
        ];
        
        foreach ( $mds_meta_keys as $meta_key ) {
            $meta_value = get_post_meta( $post_id, $meta_key, true );
            if ( !empty( $meta_value ) ) {
                $confidence += 0.1;
                $patterns[] = [
                    'type' => 'post_meta',
                    'key' => $meta_key,
                    'value' => $meta_value
                ];
                
                if ( $meta_key === '_mds_page_type' ) {
                    $page_type = $meta_value;
                    $confidence += 0.2; // Page type meta is strong indicator
                }
            }
        }
        
        return [
            'method' => 'meta',
            'confidence' => min( $confidence, 0.8 ),
            'page_type' => $page_type,
            'patterns' => $patterns
        ];
    }
    
    /**
     * Detect MDS page by option assignment
     *
     * @param int $post_id
     * @return array
     */
    private function detectByOptionAssignment( int $post_id ): array {
        $patterns = [];
        $confidence = 0.0;
        $page_type = null;
        
        // Check if this page is assigned to any MDS options
        $option_mappings = [
            '_mds_grid-page' => 'grid',
            '_mds_users-order-page' => 'order',
            '_mds_users-write-ad-page' => 'write-ad',
            '_mds_users-confirm-order-page' => 'confirm-order',
            '_mds_users-payment-page' => 'payment',
            '_mds_users-manage-page' => 'manage',
            '_mds_users-thank-you-page' => 'thank-you',
            '_mds_users-list-page' => 'list',
            '_mds_users-upload-page' => 'upload',
            '_mds_users-no-orders-page' => 'no-orders'
        ];
        
        foreach ( $option_mappings as $option_key => $type ) {
            $assigned_page_id = get_option( $option_key );
            if ( $assigned_page_id == $post_id ) {
                $confidence = 0.9; // Very high confidence for option assignment
                $page_type = $type;
                $patterns[] = [
                    'type' => 'option_assignment',
                    'option_key' => $option_key,
                    'page_type' => $type
                ];
                break; // Only one option assignment per page
            }
        }
        
        return [
            'method' => 'option_assignment',
            'confidence' => $confidence,
            'page_type' => $page_type,
            'patterns' => $patterns
        ];
    }
    
    /**
     * Combine multiple detection results
     *
     * @param array $results
     * @return array
     */
    private function combineDetectionResults( array $results ): array {
        $total_confidence = 0.0;
        $page_types = [];
        $all_patterns = [];
        $content_type = 'custom';
        
        // Collect all results
        foreach ( $results as $result ) {
            if ( $result['confidence'] > 0 ) {
                $total_confidence += $result['confidence'];
                $all_patterns = array_merge( $all_patterns, $result['patterns'] );
                
                if ( $result['page_type'] ) {
                    $page_types[] = $result['page_type'];
                }
            }
        }
        
        // Determine final confidence (weighted average, capped at 1.0)
        $final_confidence = min( $total_confidence / count( $results ), 1.0 );
        
        // Determine page type (most common, or highest confidence method)
        $final_page_type = null;
        if ( !empty( $page_types ) ) {
            $page_type_counts = array_count_values( $page_types );
            $final_page_type = array_key_first( $page_type_counts );
        }
        
        // Determine content type based on patterns
        $has_shortcode = false;
        $has_block = false;
        
        foreach ( $all_patterns as $pattern ) {
            if ( $pattern['type'] === 'shortcode' || $pattern['type'] === 'legacy_shortcode' ) {
                $has_shortcode = true;
            }
            if ( $pattern['type'] === 'block' || $pattern['type'] === 'legacy_block' ) {
                $has_block = true;
            }
        }
        
        if ( $has_shortcode && $has_block ) {
            $content_type = 'mixed';
        } elseif ( $has_block ) {
            $content_type = 'block';
        } elseif ( $has_shortcode ) {
            $content_type = 'shortcode';
        }
        
        $is_mds_page = $final_confidence >= $this->min_confidence;
        
        return $this->createDetectionResult(
            $is_mds_page,
            $final_page_type,
            $final_confidence,
            $content_type,
            $all_patterns
        );
    }
    
    /**
     * Determine page type from shortcode attributes
     *
     * @param array $attributes
     * @return string|null
     */
    private function determinePageTypeFromShortcode( array $attributes ): ?string {
        // Check for explicit type attribute
        if ( !empty( $attributes['type'] ) ) {
            return $attributes['type'];
        }
        
        // Check for display attribute
        if ( !empty( $attributes['display'] ) ) {
            return $attributes['display'];
        }
        
        // Check for page attribute
        if ( !empty( $attributes['page'] ) ) {
            return $attributes['page'];
        }
        
        return null;
    }
    
    /**
     * Extract page type from custom shortcode name
     *
     * @param string $shortcode_name e.g., "platform_leaderboard"
     * @return string
     */
    private function extractPageTypeFromShortcodeName( string $shortcode_name ): string {
        // Convert "platform_leaderboard" to "platform-leaderboard" for consistency
        $type = str_replace( '_', '-', $shortcode_name );
        
        return $type;
    }
    
    /**
     * Determine page type from block
     *
     * @param array $block
     * @return string|null
     */
    private function determinePageTypeFromBlock( array $block ): ?string {
        $block_name = $block['blockName'];
        
        // Handle Carbon Fields MDS block
        if ( $block_name === 'carbon-fields/million-dollar-script' ) {
            // For Carbon Fields blocks, check the block attributes for type information
            $attrs = $block['attrs'] ?? [];
            if ( !empty( $attrs['type'] ) ) {
                return $attrs['type'];
            }
            if ( !empty( $attrs['pageType'] ) ) {
                return $attrs['pageType'];
            }
            // Default to 'grid' for Carbon Fields MDS blocks if no specific type is found
            return 'grid';
        }
        
        // Extract type from block name
        if ( preg_match( '/milliondollarscript\/(.+)-block$/', $block_name, $matches ) ) {
            return $matches[1];
        }
        
        // Check block attributes
        $attrs = $block['attrs'] ?? [];
        if ( !empty( $attrs['type'] ) ) {
            return $attrs['type'];
        }
        
        if ( !empty( $attrs['pageType'] ) ) {
            return $attrs['pageType'];
        }
        
        return null;
    }
    
    /**
     * Create standardized detection result
     *
     * @param bool $is_mds_page
     * @param string|null $page_type
     * @param float $confidence
     * @param string $content_type
     * @param array $patterns
     * @return array
     */
    private function createDetectionResult( 
        bool $is_mds_page, 
        ?string $page_type = null, 
        float $confidence = 0.0, 
        string $content_type = 'custom', 
        array $patterns = [] 
    ): array {
        return [
            'is_mds_page' => $is_mds_page,
            'page_type' => $page_type,
            'confidence' => $confidence,
            'content_type' => $content_type,
            'patterns' => $patterns,
            'detected_at' => current_time( 'mysql' )
        ];
    }
    
    /**
     * Set minimum confidence threshold
     *
     * @param float $threshold
     * @return void
     */
    public function setMinConfidence( float $threshold ): void {
        $this->min_confidence = max( 0.0, min( 1.0, $threshold ) );
    }
    
    /**
     * Get minimum confidence threshold
     *
     * @return float
     */
    public function getMinConfidence(): float {
        return $this->min_confidence;
    }
    
    /**
     * Batch detect multiple pages
     *
     * @param array $post_ids
     * @return array
     */
    public function batchDetect( array $post_ids ): array {
        $results = [];
        
        foreach ( $post_ids as $post_id ) {
            $results[$post_id] = $this->detectMDSPage( $post_id );
        }
        
        return $results;
    }
} 