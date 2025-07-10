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
 * 
 * Extension Support:
 * - Use 'mds_extension_shortcodes' filter to register custom shortcodes for detection
 * - Use 'mds_shortcode_page_type_map' filter to map shortcode names to page types
 * - Use 'mds_has_custom_shortcodes' filter in validation to mark pages as valid
 * - Use 'mds_skip_page_validation' filter to bypass validation for custom page types
 * - Use 'mds_page_validation_errors' filter to add or modify validation errors
 */
class MDSPageDetectionEngine {
    
    // Detection patterns for different page types
    private array $page_type_patterns = [
        'grid' => [
            'shortcode_attrs' => ['type' => 'grid', 'display' => 'grid'],
            'content_keywords' => ['pixel', 'grid', 'buy pixel', 'advertising space', 'milliondollarscript', 'million dollar script', 'pixel grid', 'ad space', 'advertisement blocks', 'pixelgrid'],
            'title_keywords' => ['grid', 'pixel', 'advertising', 'milliondollarscript'],
            'block_names' => ['milliondollarscript/grid-block', 'carbon-fields/million-dollar-script'],
            'css_classes' => ['mds-grid', 'pixel-grid', 'advertising-grid']
        ],
        'order' => [
            'shortcode_attrs' => ['type' => 'order', 'display' => 'order'],
            'content_keywords' => ['pixel order', 'order pixel', 'buy pixel', 'purchase pixel', 'milliondollarscript order'],
            'title_keywords' => ['order pixel', 'purchase pixel', 'buy pixel'],
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
            'content_keywords' => ['pixel payment', 'pay for pixel', 'milliondollarscript payment', 'advertising payment'],
            'title_keywords' => ['pixel payment', 'pay pixel', 'advertising payment'],
            'block_names' => ['milliondollarscript/payment-block'],
            'css_classes' => ['mds-payment', 'payment-form', 'checkout-form']
        ],
        'manage' => [
            'shortcode_attrs' => ['type' => 'manage', 'display' => 'manage'],
            'content_keywords' => ['manage pixel', 'manage ads', 'pixel dashboard', 'my pixel orders', 'milliondollarscript manage'],
            'title_keywords' => ['manage pixel', 'pixel dashboard', 'manage ads'],
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
        ],
        'stats' => [
            'shortcode_attrs' => ['type' => 'stats', 'display' => 'stats'],
            'content_keywords' => ['stats', 'statistics', 'sold pixels', 'available pixels', 'pixel count', 'stats box'],
            'title_keywords' => ['stats', 'statistics', 'pixel count'],
            'block_names' => ['milliondollarscript/stats-block'],
            'css_classes' => ['mds-stats', 'stats-box', 'pixel-stats']
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
        
        // Check for WooCommerce page exclusions first
        if ( $this->isWooCommercePage( $post_id, $post ) ) {
            return $this->createDetectionResult( false, null, 0.0, 'excluded', [
                [
                    'type' => 'woocommerce_exclusion',
                    'reason' => 'WooCommerce page detected',
                    'page_title' => $post->post_title
                ]
            ] );
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
        ], $post_id );
        
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
        
        // Check for MDS shortcodes with improved detection
        $mds_shortcode_found = false;
        
        // Primary detection using has_shortcode (most reliable when working)
        if ( has_shortcode( $content, 'milliondollarscript' ) ) {
            $mds_shortcode_found = true;
        }
        
        // Fallback: Direct regex search for milliondollarscript shortcode
        if ( !$mds_shortcode_found && preg_match( '/\[milliondollarscript[\s\]]/i', $content ) ) {
            $mds_shortcode_found = true;
        }
        
        if ( $mds_shortcode_found ) {
            $confidence += 0.8; // High confidence for MDS shortcode
            
            // Extract shortcode attributes using WordPress function
            $pattern = get_shortcode_regex( [ 'milliondollarscript' ] );
            if ( preg_match_all( '/' . $pattern . '/s', $content, $matches ) ) {
                foreach ( $matches[3] as $attrs ) {
                    $parsed_attrs = shortcode_parse_atts( $attrs );
                    if ( $parsed_attrs ) {
                        $patterns[] = [
                            'type' => 'shortcode',
                            'shortcode_name' => 'milliondollarscript',
                            'attributes' => $parsed_attrs
                        ];
                        
                        // Determine page type from attributes
                        $detected_type = $this->determinePageTypeFromShortcode( $parsed_attrs );
                        if ( $detected_type ) {
                            // Validate and map legacy page types
                            $detected_type = $this->validateAndMapPageType( $detected_type );
                            if ( $detected_type ) {
                                $page_type = $detected_type;
                                $confidence += 0.1;
                            }
                        }
                    }
                }
            } else {
                // Fallback: Manual regex extraction for edge cases
                if ( preg_match_all( '/\[milliondollarscript([^\]]*)\]/i', $content, $manual_matches ) ) {
                    foreach ( $manual_matches[1] as $attrs_string ) {
                        $parsed_attrs = shortcode_parse_atts( trim( $attrs_string ) );
                        $patterns[] = [
                            'type' => 'shortcode',
                            'shortcode_name' => 'milliondollarscript',
                            'attributes' => $parsed_attrs ?: [],
                            'raw_attributes' => trim( $attrs_string )
                        ];
                        
                        if ( $parsed_attrs ) {
                            $detected_type = $this->determinePageTypeFromShortcode( $parsed_attrs );
                            if ( $detected_type && !$page_type ) {
                                // Validate and map legacy page types
                                $detected_type = $this->validateAndMapPageType( $detected_type );
                                if ( $detected_type ) {
                                    $page_type = $detected_type;
                                    $confidence += 0.1;
                                }
                            }
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
        
        // Check for extension and legacy shortcodes
        $core_shortcodes = [
            // Core MDS shortcodes only
            'mds', 'million_dollar_script', 'pixel_grid', 'milliondollarscript',
            'pixel_advertising', 'ad_grid', 'mds_grid', 'pixel_board',
            'mds_display', 'pixel_display', 'advertisement_grid', 'mds_widget'
        ];
        
        // Allow extensions to register their shortcodes for detection
        $extension_shortcodes = apply_filters( 'mds_extension_shortcodes', $core_shortcodes );
        foreach ( $extension_shortcodes as $shortcode ) {
            if ( has_shortcode( $content, $shortcode ) ) {
                $confidence += 0.7; // Increased confidence for legacy shortcodes
                
                // Extract shortcode attributes for better page type detection
                $pattern = get_shortcode_regex( [ $shortcode ] );
                if ( preg_match_all( '/' . $pattern . '/s', $content, $matches ) ) {
                    foreach ( $matches[3] as $attrs ) {
                        $parsed_attrs = shortcode_parse_atts( $attrs );
                        $patterns[] = [
                            'type' => 'legacy_shortcode',
                            'shortcode' => $shortcode,
                            'attributes' => $parsed_attrs ?: []
                        ];
                        
                        // Try to determine page type from legacy shortcode attributes
                        if ( $parsed_attrs ) {
                            $detected_type = $this->determinePageTypeFromShortcode( $parsed_attrs );
                            if ( $detected_type && !$page_type ) {
                                // Validate and map legacy page types
                                $detected_type = $this->validateAndMapPageType( $detected_type );
                                if ( $detected_type ) {
                                    $page_type = $detected_type;
                                }
                            }
                        }
                    }
                } else {
                    $patterns[] = [
                        'type' => 'legacy_shortcode',
                        'shortcode' => $shortcode
                    ];
                }
                
                // Don't default to 'grid' - let content analysis determine the correct type
                // This prevents incorrectly classifying all unknown shortcodes as 'grid'
            }
        }
        
        // Catch-all: Search for ANY mds_ prefixed shortcodes
        if ( preg_match_all( '/\[mds_([a-zA-Z0-9_-]+)([^\]]*)\]/', $content, $catchall_matches, PREG_SET_ORDER ) ) {
            foreach ( $catchall_matches as $match ) {
                $shortcode_name = strtolower( $match[1] );
                $attributes_string = trim( $match[2] ?? '' );
                
                // Only add if we haven't already detected this shortcode
                $already_detected = false;
                foreach ( $patterns as $pattern ) {
                    if ( isset( $pattern['shortcode_name'] ) && 
                         strpos( $pattern['shortcode_name'], $shortcode_name ) !== false ) {
                        $already_detected = true;
                        break;
                    }
                }
                
                if ( !$already_detected ) {
                    $confidence += 0.6; // Medium confidence for unrecognized MDS shortcodes
                    $parsed_attrs = [];
                    if ( !empty( $attributes_string ) ) {
                        $parsed_attrs = shortcode_parse_atts( $attributes_string );
                    }
                    
                    $patterns[] = [
                        'type' => 'mds_extension_shortcode',
                        'shortcode_name' => 'mds_' . $shortcode_name,
                        'attributes' => $parsed_attrs ?: [],
                        'custom_type' => $this->extractPageTypeFromShortcodeName( $shortcode_name )
                    ];
                    
                    // Set page type if not already set
                    if ( !$page_type ) {
                        $page_type = $this->extractPageTypeFromShortcodeName( $shortcode_name );
                    }
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
        
        // Check for Carbon Fields blocks in HTML comments (most common format)
        // Updated regex to properly capture nested JSON objects
        if ( preg_match_all( '/<!-- wp:carbon-fields\/million-dollar-script\s+({.*?})\s*(?:\/-->|-->)/', $content, $carbon_matches ) ) {
            foreach ( $carbon_matches[1] as $json_attrs ) {
                $block_attrs = json_decode( $json_attrs, true );
                if ( $block_attrs ) {
                    $confidence += 0.9; // Very high confidence for Carbon Fields blocks
                    $detected_type = null;
                    
                    // Check multiple possible attribute names for type
                    // First check in nested 'data' object (Carbon Fields format)
                    if ( isset( $block_attrs['data']['milliondollarscript_type'] ) ) {
                        $detected_type = $block_attrs['data']['milliondollarscript_type'];
                    } elseif ( isset( $block_attrs['milliondollarscript_type'] ) ) {
                        $detected_type = $block_attrs['milliondollarscript_type'];
                    } elseif ( isset( $block_attrs['type'] ) ) {
                        $detected_type = $block_attrs['type'];
                    } elseif ( isset( $block_attrs['pageType'] ) ) {
                        $detected_type = $block_attrs['pageType'];
                    } elseif ( isset( $block_attrs['data']['type'] ) ) {
                        $detected_type = $block_attrs['data']['type'];
                    }
                    
                    $patterns[] = [
                        'type' => 'carbon_fields_block',
                        'block_name' => 'carbon-fields/million-dollar-script',
                        'attributes' => $block_attrs
                    ];
                    
                    if ( $detected_type ) {
                        // Validate and map legacy page types
                        $detected_type = $this->validateAndMapPageType( $detected_type );
                        if ( $detected_type ) {
                            $page_type = $detected_type;
                            $confidence += 0.1;
                        }
                    }
                }
            }
        }
        
        // Fallback: Parse WordPress blocks normally
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
            
            // Check for MDS blocks - Enhanced detection patterns
            $mds_block_patterns = [
                'milliondollarscript/', 
                'carbon-fields/million-dollar-script',
                'mds/',
                'pixel-grid/',
                'mds-blocks/',
                'million-dollar-script/',
                'carbon-fields/mds-'
            ];
            
            $is_mds_block = false;
            foreach ( $mds_block_patterns as $pattern ) {
                if ( strpos( $block['blockName'], $pattern ) === 0 || $block['blockName'] === $pattern ) {
                    $is_mds_block = true;
                    break;
                }
            }
            
            if ( $is_mds_block ) {
                $confidence += 0.8; // High confidence for MDS blocks
                $patterns[] = [
                    'type' => 'block',
                    'block_name' => $block['blockName'],
                    'attributes' => $block['attrs'] ?? []
                ];
                
                // Determine page type from block
                $detected_type = $this->determinePageTypeFromBlock( $block );
                if ( $detected_type ) {
                    // Validate and map legacy page types
                    $detected_type = $this->validateAndMapPageType( $detected_type );
                    if ( $detected_type ) {
                        $page_type = $detected_type;
                        $confidence += 0.1;
                    }
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
     * @param int $post_id
     * @return array
     */
    private function combineDetectionResults( array $results, int $post_id ): array {
        $total_confidence = 0.0;
        $page_types = [];
        $all_patterns = [];
        $content_type = 'custom';
        
        // Collect all results with enhanced tracking
        $page_type_sources = [];
        foreach ( $results as $result ) {
            if ( $result['confidence'] > 0 ) {
                $total_confidence += $result['confidence'];
                $all_patterns = array_merge( $all_patterns, $result['patterns'] );
                
                if ( $result['page_type'] ) {
                    $page_types[] = $result['page_type'];
                    // Track the source and confidence of each page type detection
                    $page_type_sources[] = [
                        'type' => $result['page_type'],
                        'method' => $result['method'],
                        'confidence' => $result['confidence'],
                        'patterns' => $result['patterns']
                    ];
                }
            }
        }
        
        // Determine final confidence (use maximum confidence from any detection method, not average)
        $final_confidence = 0.0;
        foreach ( $results as $result ) {
            if ( $result['confidence'] > $final_confidence ) {
                $final_confidence = $result['confidence'];
            }
        }
        $final_confidence = min( $final_confidence, 1.0 );
        
        // Determine page type with enhanced priority system (detection quality over core/extension bias)
        $final_page_type = null;
        if ( !empty( $page_type_sources ) ) {
            $final_page_type = $this->determinePageTypeByQuality( $page_type_sources );
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
        
        // Debug logging for detection results
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $debug_info = [
                'post_id' => $post_id,
                'final_confidence' => $final_confidence,
                'min_confidence' => $this->min_confidence,
                'is_mds_page' => $is_mds_page,
                'page_type' => $final_page_type,
                'method_results' => array_map( function( $result ) {
                    return [
                        'method' => $result['method'] ?? 'unknown',
                        'confidence' => $result['confidence'],
                        'page_type' => $result['page_type']
                    ];
                }, $results )
            ];
        }
        
        return $this->createDetectionResult(
            $is_mds_page,
            $final_page_type,
            $final_confidence,
            $content_type,
            $all_patterns
        );
    }
    
    /**
     * Determine page type by detection quality and specificity
     * Prioritizes explicit detection over implicit detection
     *
     * @param array $page_type_sources Array of page type detections with source info
     * @return string|null
     */
    private function determinePageTypeByQuality( array $page_type_sources ): ?string {
        // Define detection quality priorities (higher = better)
        $method_priorities = [
            'option_assignment' => 100, // Highest: Explicit page assignment
            'meta' => 90,              // High: Stored metadata
            'blocks' => 85,            // High: Block detection (primary content)
            'shortcode' => 80,         // Medium-High: Shortcode detection
            'content' => 10            // Lowest: Content keyword analysis
        ];
        
        $core_page_types = ['grid', 'order', 'write-ad', 'confirm-order', 'payment', 'manage', 'thank-you', 'list', 'upload', 'no-orders', 'stats'];
        
        // CRITICAL: Grid block priority - if a grid block is detected, it must take precedence
        // This ensures grid blocks always function correctly in mixed content scenarios
        $grid_block_detected = false;
        foreach ( $page_type_sources as $source ) {
            if ( $source['method'] === 'blocks' && $source['type'] === 'grid' ) {
                foreach ( $source['patterns'] as $pattern ) {
                    if ( $pattern['type'] === 'block' && 
                         isset( $pattern['block_name'] ) && 
                         $pattern['block_name'] === 'carbon-fields/million-dollar-script' ) {
                        $grid_block_detected = true;
                        break 2; // Break out of both loops
                    }
                }
            }
        }
        
        // If grid block detected, return 'grid' immediately - this is non-negotiable
        if ( $grid_block_detected ) {
            return 'grid';
        }
        
        // Enhanced mixed content detection: check if we have both core and custom content
        $has_core_content = false;
        $has_custom_content = false;
        $custom_shortcode_types = [];
        
        foreach ( $page_type_sources as $source ) {
            if ( in_array( $source['type'], $core_page_types ) ) {
                $has_core_content = true;
            } else {
                $has_custom_content = true;
                $custom_shortcode_types[] = $source['type'];
            }
        }
        
        // Score each detection by quality
        $scored_detections = [];
        foreach ( $page_type_sources as $source ) {
            $method = $source['method'];
            $type = $source['type'];
            $confidence = $source['confidence'];
            
            // Calculate quality score: method priority + confidence + pattern specificity
            $method_score = $method_priorities[$method] ?? 0;
            $pattern_score = $this->calculatePatternSpecificity( $source['patterns'], $type );
            
            // Apply mixed content boost for core page types from blocks
            $mixed_content_boost = 0;
            if ( in_array( $type, $core_page_types ) && $method === 'blocks' ) {
                if ( $has_custom_content ) {
                    $mixed_content_boost = 50; // Strong boost for core block detection in mixed content
                }
            }
            
            // NEW: Apply boost for custom shortcodes when they are the primary content
            // This ensures custom shortcodes get proper recognition when they're the main page content
            $custom_shortcode_boost = 0;
            if ( !in_array( $type, $core_page_types ) && $method === 'shortcode' ) {
                // Check if this is a high-confidence custom shortcode detection
                foreach ( $source['patterns'] as $pattern ) {
                    if ( $pattern['type'] === 'shortcode' && 
                         isset( $pattern['shortcode_name'] ) && 
                         strpos( $pattern['shortcode_name'], 'mds_' ) === 0 ) {
                        $custom_shortcode_boost = 40; // Good boost for custom MDS shortcodes
                        break;
                    }
                }
                
                // Additional boost if there's no conflicting core content
                if ( !$has_core_content ) {
                    $custom_shortcode_boost += 30;
                }
            }
            
            $total_score = $method_score + ($confidence * 10) + $pattern_score + $mixed_content_boost + $custom_shortcode_boost;
            
            $scored_detections[] = [
                'type' => $type,
                'score' => $total_score,
                'method' => $method,
                'confidence' => $confidence,
                'boost' => $mixed_content_boost + $custom_shortcode_boost,
                'is_core' => in_array( $type, $core_page_types ),
                'is_custom' => !in_array( $type, $core_page_types )
            ];
        }
        
        // Sort by score (highest first) and return the best detection
        usort( $scored_detections, function( $a, $b ) {
            return $b['score'] <=> $a['score'];
        });
        
        return $scored_detections[0]['type'] ?? null;
    }
    
    /**
     * Calculate pattern specificity score for a page type
     *
     * @param array $patterns Detection patterns
     * @param string $page_type Detected page type
     * @return float Specificity score
     */
    private function calculatePatternSpecificity( array $patterns, string $page_type ): float {
        $specificity_score = 0;
        $core_page_types = ['grid', 'order', 'write-ad', 'confirm-order', 'payment', 'manage', 'thank-you', 'list', 'upload', 'no-orders', 'stats'];
        $is_custom_type = !in_array( $page_type, $core_page_types );
        
        foreach ( $patterns as $pattern ) {
            // Explicit shortcode type attributes are most specific
            if ( $pattern['type'] === 'shortcode' && isset( $pattern['attributes']['type'] ) ) {
                $specificity_score += 50;
            }
            // Grid blocks (Carbon Fields) are highly specific when they're the primary content
            elseif ( $pattern['type'] === 'block' && isset( $pattern['block_name'] ) && 
                     $pattern['block_name'] === 'carbon-fields/million-dollar-script' && 
                     in_array( $page_type, $core_page_types ) ) {
                $specificity_score += 45; // Higher than extension shortcodes for mixed content
            }
            // Custom MDS shortcodes are highly specific when they match the detected type
            elseif ( $pattern['type'] === 'shortcode' && isset( $pattern['shortcode_name'] ) && 
                     strpos( $pattern['shortcode_name'], 'mds_' ) === 0 && $is_custom_type ) {
                // Extract the shortcode type from the name (e.g., "mds_platform_leaderboard" -> "platform-leaderboard")
                $shortcode_type = str_replace( 'mds_', '', $pattern['shortcode_name'] );
                $shortcode_type = str_replace( '_', '-', $shortcode_type );
                
                if ( $shortcode_type === $page_type ) {
                    $specificity_score += 55; // Very high specificity for exact matches
                } else {
                    $specificity_score += 35; // High specificity for custom MDS shortcodes
                }
            }
            // Shortcode name mappings are highly specific
            elseif ( isset( $pattern['shortcode_name'] ) && strpos( $pattern['shortcode_name'], 'mds_' ) === 0 ) {
                $specificity_score += 30;
            }
            // Block type attributes are specific
            elseif ( $pattern['type'] === 'block' && isset( $pattern['attributes'] ) ) {
                $specificity_score += 25;
            }
            // Legacy shortcodes are moderately specific
            elseif ( $pattern['type'] === 'legacy_shortcode' ) {
                $specificity_score += 15;
            }
            // Extension shortcodes from registered extensions
            elseif ( $pattern['type'] === 'mds_extension_shortcode' && $is_custom_type ) {
                $specificity_score += 40; // Good specificity for extension shortcodes
            }
            // Content keywords are least specific
            elseif ( $pattern['type'] === 'content_keyword' || $pattern['type'] === 'title_keyword' ) {
                $specificity_score += 1;
            }
        }
        
        return $specificity_score;
    }
    
    /**
     * Determine page type from shortcode attributes
     *
     * @param array $attributes
     * @return string|null
     */
    private function determinePageTypeFromShortcode( array $attributes ): ?string {
        // Check for explicit type attribute (preferred)
        if ( !empty( $attributes['type'] ) ) {
            return $attributes['type'];
        }
        
        // Check for view attribute (legacy format - map to correct types)
        if ( !empty( $attributes['view'] ) ) {
            $view_value = $attributes['view'];
            
            // Map legacy view values to correct page types
            $view_type_map = [
                'order' => 'order',
                'manage' => 'manage',
                'pixels' => 'manage',  // "pixels" view should be "manage" type
                'checkout' => 'confirm-order',  // "checkout" view should be "confirm-order" type
                'grid' => 'grid',
            ];
            
            return $view_type_map[$view_value] ?? $view_value;
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
        // Core shortcode mappings (extensions should use hooks to add their own)
        $shortcode_map = [];
        
        // Allow extensions to register their shortcode mappings
        $shortcode_map = apply_filters( 'mds_shortcode_page_type_map', $shortcode_map );
        
        // Check if we have a specific mapping
        if ( isset( $shortcode_map[$shortcode_name] ) ) {
            return $shortcode_map[$shortcode_name];
        }
        
        // Fallback: Convert underscores to hyphens and return
        return str_replace( '_', '-', $shortcode_name );
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
            
            // Check in nested 'data' object first (Carbon Fields format)
            if ( !empty( $attrs['data']['milliondollarscript_type'] ) ) {
                return $attrs['data']['milliondollarscript_type'];
            }
            if ( !empty( $attrs['data']['type'] ) ) {
                return $attrs['data']['type'];
            }
            if ( !empty( $attrs['data']['pageType'] ) ) {
                return $attrs['data']['pageType'];
            }
            
            // Fallback to top-level attributes
            if ( !empty( $attrs['type'] ) ) {
                return $attrs['type'];
            }
            if ( !empty( $attrs['pageType'] ) ) {
                return $attrs['pageType'];
            }
            if ( !empty( $attrs['milliondollarscript_type'] ) ) {
                return $attrs['milliondollarscript_type'];
            }
            
            // Default to 'grid' for Carbon Fields MDS blocks when no type is specified
            // This ensures grid blocks without attributes are properly detected as grid type
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
    
    /**
     * Check if a page is a WooCommerce page that should be excluded
     *
     * @param int $post_id
     * @param \WP_Post $post
     * @return bool
     */
    private function isWooCommercePage( int $post_id, \WP_Post $post ): bool {
        // Only check if WooCommerce is active
        if ( !function_exists( 'is_woocommerce' ) || !class_exists( 'WooCommerce' ) ) {
            return false;
        }
        
        // Check if it's a standard WooCommerce page by ID
        $wc_page_ids = [
            wc_get_page_id( 'cart' ),
            wc_get_page_id( 'checkout' ),
            wc_get_page_id( 'myaccount' ),
            wc_get_page_id( 'shop' )
        ];
        
        if ( in_array( $post_id, $wc_page_ids, true ) ) {
            return true;
        }
        
        // Check for WooCommerce shortcodes
        $wc_shortcodes = [
            'woocommerce_cart', 'woocommerce_checkout', 'woocommerce_my_account',
            'woocommerce_order_tracking', 'shop_messages', 'woocommerce_checkout_order_review'
        ];
        
        foreach ( $wc_shortcodes as $shortcode ) {
            if ( has_shortcode( $post->post_content, $shortcode ) ) {
                return true;
            }
        }
        
        // Check for WooCommerce blocks
        if ( has_blocks( $post->post_content ) ) {
            $blocks = parse_blocks( $post->post_content );
            if ( $this->hasWooCommerceBlocks( $blocks ) ) {
                return true;
            }
        }
        
        // Check page titles that clearly indicate WooCommerce pages
        $wc_titles = [
            'cart', 'checkout', 'my account', 'shop', 'store', 
            'basket', 'shopping cart', 'order received', 'order tracking'
        ];
        
        $title_lower = strtolower( $post->post_title );
        foreach ( $wc_titles as $wc_title ) {
            if ( $title_lower === $wc_title || strpos( $title_lower, $wc_title ) === 0 ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if blocks contain WooCommerce blocks
     *
     * @param array $blocks
     * @return bool
     */
    private function hasWooCommerceBlocks( array $blocks ): bool {
        foreach ( $blocks as $block ) {
            if ( empty( $block['blockName'] ) ) {
                continue;
            }
            
            // Check for WooCommerce block patterns
            if ( strpos( $block['blockName'], 'woocommerce/' ) === 0 ) {
                return true;
            }
            
            // Check inner blocks recursively
            if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
                if ( $this->hasWooCommerceBlocks( $block['innerBlocks'] ) ) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Validate and map legacy page types to current valid types
     *
     * @param string $page_type
     * @return string|null Valid page type or null if invalid
     */
    private function validateAndMapPageType( string $page_type ): ?string {
        // Valid page types (based on the patterns defined at the top of the class)
        $valid_types = array_keys( $this->page_type_patterns );
        
        // If it's already valid, return it
        if ( in_array( $page_type, $valid_types, true ) ) {
            return $page_type;
        }
        
        // Legacy page type mappings
        $legacy_mappings = [
            'users' => 'manage',           // Legacy "users" view should be "manage"
            'checkout' => 'confirm-order', // Legacy "checkout" view should be "confirm-order"
            'pixels' => 'manage',          // Legacy "pixels" view should be "manage"
            'user_dashboard' => 'manage',  // Legacy user dashboard
            'pixel_management' => 'manage' // Legacy pixel management
        ];
        
        // Check if it's a mappable legacy type
        if ( isset( $legacy_mappings[$page_type] ) ) {
            return $legacy_mappings[$page_type];
        }
        
        // If it's not valid and not mappable, return null
        return null;
    }
} 