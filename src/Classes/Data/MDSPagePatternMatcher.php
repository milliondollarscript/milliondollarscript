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

defined( 'ABSPATH' ) or exit;

/**
 * Advanced pattern matcher for MDS page detection
 */
class MDSPagePatternMatcher {
    
    // Pattern weights for scoring
    private array $pattern_weights = [
        'shortcode_exact' => 0.9,
        'shortcode_legacy' => 0.7,
        'block_exact' => 0.9,
        'block_legacy' => 0.7,
        'css_class' => 0.6,
        'content_keyword' => 0.3,
        'title_keyword' => 0.4,
        'url_pattern' => 0.5,
        'meta_data' => 0.8,
        'option_assignment' => 0.95,
        'form_structure' => 0.7,
        'javascript_pattern' => 0.6
    ];
    
    // Advanced pattern definitions
    private array $advanced_patterns = [
        'grid' => [
            'html_structures' => [
                '<div class="pixel-grid">',
                '<div class="mds-grid">',
                '<table class="pixel-table">',
                'data-pixel-id=',
                'data-grid-position='
            ],
            'javascript_patterns' => [
                'pixelGrid',
                'MDSGrid',
                'pixel_click',
                'grid_interaction'
            ],
            'form_patterns' => [
                'name="pixel_id"',
                'name="grid_position"',
                'action="pixel_purchase"'
            ],
            'url_indicators' => [
                '/grid',
                '/pixels',
                '/advertising'
            ]
        ],
        'order' => [
            'html_structures' => [
                '<form class="order-form">',
                '<div class="payment-section">',
                'name="order_total"',
                'name="payment_method"'
            ],
            'javascript_patterns' => [
                'orderForm',
                'paymentProcess',
                'order_submit',
                'payment_validation'
            ],
            'form_patterns' => [
                'name="customer_email"',
                'name="billing_address"',
                'type="submit" value="Order"'
            ],
            'url_indicators' => [
                '/order',
                '/purchase',
                '/buy',
                '/checkout'
            ]
        ],
        'write-ad' => [
            'html_structures' => [
                '<form class="ad-form">',
                '<textarea name="ad_content">',
                'name="ad_title"',
                'name="ad_url"'
            ],
            'javascript_patterns' => [
                'adForm',
                'ad_preview',
                'content_validation'
            ],
            'form_patterns' => [
                'name="ad_content"',
                'name="ad_title"',
                'name="target_url"'
            ],
            'url_indicators' => [
                '/write-ad',
                '/create-ad',
                '/ad-content'
            ]
        ]
    ];
    
    /**
     * Analyze page content with advanced pattern matching
     *
     * @param int $post_id
     * @param string $content
     * @param string $title
     * @return array
     */
    public function analyzePagePatterns( int $post_id, string $content, string $title ): array {
        $analysis = [
            'patterns_found' => [],
            'confidence_scores' => [],
            'page_type_scores' => [],
            'total_confidence' => 0.0,
            'suggested_page_type' => null
        ];
        
        // Analyze each page type
        foreach ( $this->advanced_patterns as $page_type => $patterns ) {
            $type_score = $this->analyzePageTypePatterns( $post_id, $content, $title, $page_type, $patterns );
            $analysis['page_type_scores'][$page_type] = $type_score;
            
            if ( $type_score['total_score'] > $analysis['total_confidence'] ) {
                $analysis['total_confidence'] = $type_score['total_score'];
                $analysis['suggested_page_type'] = $page_type;
            }
            
            // Merge patterns found
            $analysis['patterns_found'] = array_merge( $analysis['patterns_found'], $type_score['patterns'] );
        }
        
        // Analyze general MDS patterns
        $general_patterns = $this->analyzeGeneralMDSPatterns( $content, $title );
        $analysis['patterns_found'] = array_merge( $analysis['patterns_found'], $general_patterns );
        
        // Calculate final confidence
        $analysis['total_confidence'] = min( $analysis['total_confidence'], 1.0 );
        
        return $analysis;
    }
    
    /**
     * Analyze patterns for specific page type
     *
     * @param int $post_id
     * @param string $content
     * @param string $title
     * @param string $page_type
     * @param array $patterns
     * @return array
     */
    private function analyzePageTypePatterns( int $post_id, string $content, string $title, string $page_type, array $patterns ): array {
        $result = [
            'page_type' => $page_type,
            'total_score' => 0.0,
            'patterns' => [],
            'pattern_scores' => []
        ];
        
        // Check HTML structures
        if ( !empty( $patterns['html_structures'] ) ) {
            foreach ( $patterns['html_structures'] as $structure ) {
                if ( stripos( $content, $structure ) !== false ) {
                    $score = $this->pattern_weights['css_class'];
                    $result['total_score'] += $score;
                    $result['patterns'][] = [
                        'type' => 'html_structure',
                        'pattern' => $structure,
                        'score' => $score
                    ];
                }
            }
        }
        
        // Check JavaScript patterns
        if ( !empty( $patterns['javascript_patterns'] ) ) {
            foreach ( $patterns['javascript_patterns'] as $js_pattern ) {
                if ( stripos( $content, $js_pattern ) !== false ) {
                    $score = $this->pattern_weights['javascript_pattern'];
                    $result['total_score'] += $score;
                    $result['patterns'][] = [
                        'type' => 'javascript_pattern',
                        'pattern' => $js_pattern,
                        'score' => $score
                    ];
                }
            }
        }
        
        // Check form patterns
        if ( !empty( $patterns['form_patterns'] ) ) {
            foreach ( $patterns['form_patterns'] as $form_pattern ) {
                if ( stripos( $content, $form_pattern ) !== false ) {
                    $score = $this->pattern_weights['form_structure'];
                    $result['total_score'] += $score;
                    $result['patterns'][] = [
                        'type' => 'form_pattern',
                        'pattern' => $form_pattern,
                        'score' => $score
                    ];
                }
            }
        }
        
        // Check URL indicators
        if ( !empty( $patterns['url_indicators'] ) ) {
            $post_url = get_permalink( $post_id );
            foreach ( $patterns['url_indicators'] as $url_indicator ) {
                if ( stripos( $post_url, $url_indicator ) !== false ) {
                    $score = $this->pattern_weights['url_pattern'];
                    $result['total_score'] += $score;
                    $result['patterns'][] = [
                        'type' => 'url_indicator',
                        'pattern' => $url_indicator,
                        'score' => $score
                    ];
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Analyze general MDS patterns
     *
     * @param string $content
     * @param string $title
     * @return array
     */
    private function analyzeGeneralMDSPatterns( string $content, string $title ): array {
        $patterns = [];
        
        // Check for MDS-specific CSS classes
        $mds_classes = [
            'mds-container',
            'mds-wrapper',
            'milliondollarscript',
            'pixel-script',
            'mds-plugin'
        ];
        
        foreach ( $mds_classes as $class ) {
            if ( stripos( $content, $class ) !== false ) {
                $patterns[] = [
                    'type' => 'mds_css_class',
                    'pattern' => $class,
                    'score' => $this->pattern_weights['css_class']
                ];
            }
        }
        
        // Check for MDS-specific JavaScript
        $mds_js_patterns = [
            'MDS_AJAX_URL',
            'mds_nonce',
            'milliondollarscript.js',
            'mds-script'
        ];
        
        foreach ( $mds_js_patterns as $js_pattern ) {
            if ( stripos( $content, $js_pattern ) !== false ) {
                $patterns[] = [
                    'type' => 'mds_javascript',
                    'pattern' => $js_pattern,
                    'score' => $this->pattern_weights['javascript_pattern']
                ];
            }
        }
        
        // Check for MDS-specific meta tags
        $mds_meta_patterns = [
            'name="mds-page-type"',
            'name="mds-version"',
            'property="mds:page"'
        ];
        
        foreach ( $mds_meta_patterns as $meta_pattern ) {
            if ( stripos( $content, $meta_pattern ) !== false ) {
                $patterns[] = [
                    'type' => 'mds_meta_tag',
                    'pattern' => $meta_pattern,
                    'score' => $this->pattern_weights['meta_data']
                ];
            }
        }
        
        return $patterns;
    }
    
    /**
     * Analyze content structure for MDS indicators
     *
     * @param string $content
     * @return array
     */
    public function analyzeContentStructure( string $content ): array {
        $structure_analysis = [
            'has_forms' => false,
            'has_tables' => false,
            'has_grids' => false,
            'has_payment_fields' => false,
            'has_file_uploads' => false,
            'form_count' => 0,
            'input_types' => [],
            'css_frameworks' => []
        ];
        
        // Check for forms
        $form_count = preg_match_all( '/<form[^>]*>/i', $content );
        $structure_analysis['form_count'] = $form_count;
        $structure_analysis['has_forms'] = $form_count > 0;
        
        // Check for tables
        $structure_analysis['has_tables'] = stripos( $content, '<table' ) !== false;
        
        // Check for grid structures
        $grid_patterns = [ 'display: grid', 'grid-template', 'css-grid', 'flex-grid' ];
        foreach ( $grid_patterns as $pattern ) {
            if ( stripos( $content, $pattern ) !== false ) {
                $structure_analysis['has_grids'] = true;
                break;
            }
        }
        
        // Check for payment-related fields
        $payment_patterns = [
            'type="email"',
            'name="billing"',
            'name="payment"',
            'class="payment"',
            'paypal',
            'stripe'
        ];
        
        foreach ( $payment_patterns as $pattern ) {
            if ( stripos( $content, $pattern ) !== false ) {
                $structure_analysis['has_payment_fields'] = true;
                break;
            }
        }
        
        // Check for file uploads
        $structure_analysis['has_file_uploads'] = stripos( $content, 'type="file"' ) !== false;
        
        // Analyze input types
        preg_match_all( '/type="([^"]+)"/i', $content, $matches );
        if ( !empty( $matches[1] ) ) {
            $structure_analysis['input_types'] = array_unique( $matches[1] );
        }
        
        // Check for CSS frameworks
        $frameworks = [ 'bootstrap', 'foundation', 'bulma', 'tailwind' ];
        foreach ( $frameworks as $framework ) {
            if ( stripos( $content, $framework ) !== false ) {
                $structure_analysis['css_frameworks'][] = $framework;
            }
        }
        
        return $structure_analysis;
    }
    
    /**
     * Calculate semantic similarity between content and page type
     *
     * @param string $content
     * @param string $page_type
     * @return float
     */
    public function calculateSemanticSimilarity( string $content, string $page_type ): float {
        $page_type_keywords = $this->getPageTypeKeywords( $page_type );
        $content_words = $this->extractContentWords( $content );
        
        $matches = 0;
        $total_keywords = count( $page_type_keywords );
        
        foreach ( $page_type_keywords as $keyword ) {
            if ( in_array( strtolower( $keyword ), $content_words ) ) {
                $matches++;
            }
        }
        
        return $total_keywords > 0 ? $matches / $total_keywords : 0.0;
    }
    
    /**
     * Get keywords associated with page type
     *
     * @param string $page_type
     * @return array
     */
    private function getPageTypeKeywords( string $page_type ): array {
        $keywords = [
            'grid' => [ 'pixel', 'grid', 'advertising', 'space', 'buy', 'purchase', 'ad' ],
            'order' => [ 'order', 'purchase', 'buy', 'payment', 'checkout', 'billing', 'total' ],
            'write-ad' => [ 'write', 'create', 'ad', 'advertisement', 'content', 'title', 'url' ],
            'confirm-order' => [ 'confirm', 'confirmation', 'verify', 'order', 'details' ],
            'payment' => [ 'payment', 'pay', 'paypal', 'stripe', 'credit', 'card', 'billing' ],
            'manage' => [ 'manage', 'dashboard', 'account', 'profile', 'settings', 'my' ],
            'thank-you' => [ 'thank', 'thanks', 'success', 'complete', 'finished', 'done' ],
            'list' => [ 'list', 'directory', 'browse', 'view', 'all', 'advertisers' ],
            'upload' => [ 'upload', 'file', 'image', 'banner', 'select', 'choose' ],
            'no-orders' => [ 'no', 'empty', 'none', 'nothing', 'orders', 'purchases' ]
        ];
        
        return $keywords[$page_type] ?? [];
    }
    
    /**
     * Extract meaningful words from content
     *
     * @param string $content
     * @return array
     */
    private function extractContentWords( string $content ): array {
        // Remove HTML tags
        $text = strip_tags( $content );
        
        // Convert to lowercase and split into words
        $words = preg_split( '/\s+/', strtolower( $text ) );
        
        // Remove common stop words
        $stop_words = [ 'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should' ];
        
        $filtered_words = array_filter( $words, function( $word ) use ( $stop_words ) {
            return strlen( $word ) > 2 && !in_array( $word, $stop_words );
        } );
        
        return array_values( $filtered_words );
    }
    
    /**
     * Update pattern weights based on detection results
     *
     * @param array $detection_results
     * @return void
     */
    public function updatePatternWeights( array $detection_results ): void {
        // This could implement machine learning-like weight adjustment
        // based on successful detection patterns
        
        foreach ( $detection_results as $result ) {
            if ( $result['accuracy'] > 0.9 ) {
                // Increase weights for successful patterns
                foreach ( $result['patterns'] as $pattern ) {
                    $pattern_type = $pattern['type'];
                    if ( isset( $this->pattern_weights[$pattern_type] ) ) {
                        $this->pattern_weights[$pattern_type] = min( 1.0, $this->pattern_weights[$pattern_type] * 1.05 );
                    }
                }
            }
        }
        
        // Save updated weights
        update_option( 'mds_pattern_weights', $this->pattern_weights );
    }
    
    /**
     * Load pattern weights from database
     *
     * @return void
     */
    public function loadPatternWeights(): void {
        $saved_weights = get_option( 'mds_pattern_weights', [] );
        
        if ( !empty( $saved_weights ) ) {
            $this->pattern_weights = array_merge( $this->pattern_weights, $saved_weights );
        }
    }
} 