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

use MillionDollarScript\Classes\Web\Shortcode;

defined( 'ABSPATH' ) or exit;

/**
 * Legacy Shortcode Handler
 * 
 * Handles backward compatibility for legacy MDS shortcodes by redirecting
 * them to the current shortcode system with proper attribute mapping.
 */
class MDSLegacyShortcodeHandler {
    
    private static ?self $instance = null;
    
    // Legacy shortcode mappings
    private array $legacy_shortcodes = [
        'mds' => 'milliondollarscript',
        'million_dollar_script' => 'milliondollarscript',
        'pixel_grid' => 'milliondollarscript',
        'mds_grid' => 'milliondollarscript',
        'mds_display' => 'milliondollarscript'
    ];
    
    // Attribute mappings for legacy shortcodes
    private array $attribute_mappings = [
        'grid_size' => 'type',
        'display_type' => 'type',
        'banner_id' => 'id',
        'bid' => 'id',
        'alignment' => 'align',
        'w' => 'width',
        'h' => 'height'
    ];
    
    // Default values for legacy shortcodes
    private array $legacy_defaults = [
        'mds' => [
            'type' => 'grid',
            'align' => 'center',
            'width' => '100%',
            'height' => 'auto'
        ],
        'million_dollar_script' => [
            'type' => 'grid',
            'align' => 'center',
            'width' => '100%',
            'height' => 'auto'
        ],
        'pixel_grid' => [
            'type' => 'grid',
            'align' => 'center',
            'width' => '1000px',
            'height' => '1000px'
        ],
        'mds_grid' => [
            'type' => 'grid',
            'align' => 'center',
            'width' => '100%',
            'height' => 'auto'
        ],
        'mds_display' => [
            'type' => 'grid',
            'align' => 'center',
            'width' => '100%',
            'height' => 'auto'
        ]
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
        $this->initializeLegacyShortcodes();
    }
    
    /**
     * Initialize legacy shortcode handlers
     *
     * @return void
     */
    private function initializeLegacyShortcodes(): void {
        // Register legacy shortcode handlers
        foreach ( $this->legacy_shortcodes as $legacy_shortcode => $current_shortcode ) {
            add_shortcode( $legacy_shortcode, [ $this, 'handleLegacyShortcode' ] );
        }
        
        // Hook into shortcode processing to log usage
        add_filter( 'do_shortcode_tag', [ $this, 'logLegacyShortcodeUsage' ], 10, 4 );
    }
    
    /**
     * Handle legacy shortcode
     *
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @param string $tag Shortcode tag
     * @return string
     */
    public function handleLegacyShortcode( $atts, $content = '', $tag = '' ): string {
        // Log legacy shortcode usage
        $this->logLegacyUsage( $tag, $atts );
        
        // Convert legacy attributes to current format
        $converted_atts = $this->convertLegacyAttributes( $tag, $atts );
        
        // Call the current shortcode handler
        return Shortcode::shortcode( $converted_atts );
    }
    
    /**
     * Convert legacy attributes to current format
     *
     * @param string $legacy_tag
     * @param array $legacy_atts
     * @return array
     */
    private function convertLegacyAttributes( string $legacy_tag, array $legacy_atts ): array {
        // Start with defaults for this legacy shortcode
        $defaults = $this->legacy_defaults[$legacy_tag] ?? Shortcode::defaults();
        
        // Parse legacy attributes
        $parsed_atts = is_array( $legacy_atts ) ? $legacy_atts : [];
        
        // Convert attribute names
        $converted_atts = [];
        foreach ( $parsed_atts as $key => $value ) {
            $new_key = $this->attribute_mappings[$key] ?? $key;
            $converted_atts[$new_key] = $value;
        }
        
        // Apply legacy-specific conversions
        $converted_atts = $this->applyLegacySpecificConversions( $legacy_tag, $converted_atts );
        
        // Merge with defaults
        $final_atts = array_merge( $defaults, $converted_atts );
        
        // Validate and sanitize
        $final_atts = $this->validateAndSanitizeAttributes( $final_atts );
        
        return $final_atts;
    }
    
    /**
     * Apply legacy-specific conversions
     *
     * @param string $legacy_tag
     * @param array $atts
     * @return array
     */
    private function applyLegacySpecificConversions( string $legacy_tag, array $atts ): array {
        switch ( $legacy_tag ) {
            case 'mds':
                // MDS shortcode specific conversions
                if ( isset( $atts['display'] ) ) {
                    $atts['type'] = $atts['display'];
                    unset( $atts['display'] );
                }
                break;
                
            case 'million_dollar_script':
                // Million Dollar Script specific conversions
                if ( isset( $atts['mode'] ) ) {
                    $atts['type'] = $atts['mode'];
                    unset( $atts['mode'] );
                }
                break;
                
            case 'pixel_grid':
                // Pixel Grid specific conversions
                $atts['type'] = 'grid'; // Always grid for pixel_grid
                if ( isset( $atts['size'] ) ) {
                    // Convert size format (e.g., "100x100" to width/height)
                    $size_parts = explode( 'x', $atts['size'] );
                    if ( count( $size_parts ) === 2 ) {
                        $atts['width'] = $size_parts[0] . 'px';
                        $atts['height'] = $size_parts[1] . 'px';
                    }
                    unset( $atts['size'] );
                }
                break;
                
            case 'mds_grid':
                // MDS Grid specific conversions
                $atts['type'] = 'grid';
                break;
                
            case 'mds_display':
                // MDS Display specific conversions
                if ( ! isset( $atts['type'] ) ) {
                    $atts['type'] = 'grid';
                }
                break;
        }
        
        return $atts;
    }
    
    /**
     * Validate and sanitize attributes
     *
     * @param array $atts
     * @return array
     */
    private function validateAndSanitizeAttributes( array $atts ): array {
        // Sanitize ID
        if ( isset( $atts['id'] ) ) {
            $atts['id'] = absint( $atts['id'] );
            if ( $atts['id'] <= 0 ) {
                $atts['id'] = 1;
            }
        }
        
        // Validate type
        $valid_types = [ 'grid', 'order', 'write-ad', 'confirm-order', 'payment', 'manage', 'thank-you', 'list', 'upload', 'no-orders', 'stats' ];
        if ( isset( $atts['type'] ) && ! in_array( $atts['type'], $valid_types ) ) {
            $atts['type'] = 'grid';
        }
        
        // Validate align
        $valid_aligns = [ 'left', 'center', 'right', 'none' ];
        if ( isset( $atts['align'] ) && ! in_array( $atts['align'], $valid_aligns ) ) {
            $atts['align'] = 'center';
        }
        
        // Sanitize width and height
        if ( isset( $atts['width'] ) ) {
            $atts['width'] = $this->sanitizeDimension( $atts['width'] );
        }
        if ( isset( $atts['height'] ) ) {
            $atts['height'] = $this->sanitizeDimension( $atts['height'] );
        }
        
        return $atts;
    }
    
    /**
     * Sanitize dimension value
     *
     * @param string $dimension
     * @return string
     */
    private function sanitizeDimension( string $dimension ): string {
        // Remove any potentially harmful characters
        $dimension = sanitize_text_field( $dimension );
        
        // Allow common dimension formats: 100px, 100%, auto, etc.
        if ( preg_match( '/^(auto|\d+(%|px|em|rem|vw|vh)?)$/', $dimension ) ) {
            return $dimension;
        }
        
        // If it's just a number, assume pixels
        if ( is_numeric( $dimension ) ) {
            return $dimension . 'px';
        }
        
        // Default fallback
        return 'auto';
    }
    
    /**
     * Log legacy shortcode usage
     *
     * @param string $tag
     * @param array $atts
     * @return void
     */
    private function logLegacyUsage( string $tag, array $atts ): void {
        // Only log in debug mode or if specifically enabled
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }
        
        $usage_data = [
            'tag' => $tag,
            'attributes' => $atts,
            'timestamp' => current_time( 'mysql' ),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        // Store usage data for analysis
        $existing_usage = get_option( 'mds_legacy_shortcode_usage', [] );
        $existing_usage[] = $usage_data;
        
        // Keep only the last 100 entries to prevent database bloat
        if ( count( $existing_usage ) > 100 ) {
            $existing_usage = array_slice( $existing_usage, -100 );
        }
        
        update_option( 'mds_legacy_shortcode_usage', $existing_usage );
    }
    
    /**
     * Log legacy shortcode usage via filter
     *
     * @param string $output
     * @param string $tag
     * @param array $attr
     * @param array $m
     * @return string
     */
    public function logLegacyShortcodeUsage( $output, $tag, $attr, $m ): string {
        if ( in_array( $tag, array_keys( $this->legacy_shortcodes ) ) ) {
            // Add a comment to the output for debugging
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $output = "<!-- Legacy MDS shortcode [{$tag}] converted to [milliondollarscript] -->" . $output;
            }
        }
        
        return $output;
    }
    
    /**
     * Get legacy shortcode usage statistics
     *
     * @return array
     */
    public function getLegacyUsageStatistics(): array {
        $usage_data = get_option( 'mds_legacy_shortcode_usage', [] );
        
        $stats = [
            'total_usage' => count( $usage_data ),
            'by_shortcode' => [],
            'recent_usage' => [],
            'most_used_attributes' => []
        ];
        
        foreach ( $usage_data as $usage ) {
            $tag = $usage['tag'];
            
            // Count by shortcode
            if ( ! isset( $stats['by_shortcode'][$tag] ) ) {
                $stats['by_shortcode'][$tag] = 0;
            }
            $stats['by_shortcode'][$tag]++;
            
            // Recent usage (last 7 days)
            $usage_time = strtotime( $usage['timestamp'] );
            if ( $usage_time > ( time() - 7 * 24 * 60 * 60 ) ) {
                $stats['recent_usage'][] = $usage;
            }
            
            // Count attributes
            if ( isset( $usage['attributes'] ) && is_array( $usage['attributes'] ) ) {
                foreach ( array_keys( $usage['attributes'] ) as $attr ) {
                    if ( ! isset( $stats['most_used_attributes'][$attr] ) ) {
                        $stats['most_used_attributes'][$attr] = 0;
                    }
                    $stats['most_used_attributes'][$attr]++;
                }
            }
        }
        
        // Sort by usage
        arsort( $stats['by_shortcode'] );
        arsort( $stats['most_used_attributes'] );
        
        return $stats;
    }
    
    /**
     * Clear legacy usage data
     *
     * @return void
     */
    public function clearLegacyUsageData(): void {
        delete_option( 'mds_legacy_shortcode_usage' );
    }
    
    /**
     * Check if a shortcode is legacy
     *
     * @param string $shortcode
     * @return bool
     */
    public function isLegacyShortcode( string $shortcode ): bool {
        return array_key_exists( $shortcode, $this->legacy_shortcodes );
    }
    
    /**
     * Get current shortcode for legacy shortcode
     *
     * @param string $legacy_shortcode
     * @return string
     */
    public function getCurrentShortcode( string $legacy_shortcode ): string {
        return $this->legacy_shortcodes[$legacy_shortcode] ?? $legacy_shortcode;
    }
    
    /**
     * Get all legacy shortcodes
     *
     * @return array
     */
    public function getLegacyShortcodes(): array {
        return array_keys( $this->legacy_shortcodes );
    }
    
    /**
     * Convert legacy shortcode string to current format
     *
     * @param string $shortcode_string
     * @return string
     */
    public function convertLegacyShortcodeString( string $shortcode_string ): string {
        // Pattern to match shortcodes
        $pattern = '/\[([a-zA-Z_]+)([^\]]*)\]/';
        
        return preg_replace_callback( $pattern, function( $matches ) {
            $tag = $matches[1];
            $attrs_string = $matches[2] ?? '';
            
            if ( $this->isLegacyShortcode( $tag ) ) {
                // Parse attributes
                $attrs = shortcode_parse_atts( $attrs_string );
                if ( ! is_array( $attrs ) ) {
                    $attrs = [];
                }
                
                // Convert to current format
                $converted_attrs = $this->convertLegacyAttributes( $tag, $attrs );
                
                // Build new shortcode string
                $new_attrs_string = '';
                foreach ( $converted_attrs as $key => $value ) {
                    $new_attrs_string .= sprintf( ' %s="%s"', $key, esc_attr( $value ) );
                }
                
                return '[milliondollarscript' . $new_attrs_string . ']';
            }
            
            return $matches[0]; // Return unchanged if not legacy
        }, $shortcode_string );
    }
} 