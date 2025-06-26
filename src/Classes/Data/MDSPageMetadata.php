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

use DateTime;
use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

/**
 * Represents metadata for an MDS page
 */
class MDSPageMetadata {
    
    // Core identification
    public int $id = 0;
    public int $post_id;
    public string $page_type;
    public string $creation_method;
    public ?string $creation_source = null;
    public string $mds_version;
    
    // Content analysis
    public string $content_type;
    public array $shortcode_attributes = [];
    public array $block_attributes = [];
    public array $detected_patterns = [];
    
    // Status and validation
    public string $status = 'active';
    public float $confidence_score = 1.0;
    public ?DateTime $last_validated = null;
    public ?array $validation_errors = null;
    
    // Configuration
    public array $page_config = [];
    public array $display_settings = [];
    public array $integration_settings = [];
    
    // Tracking
    public DateTime $created_at;
    public DateTime $updated_at;
    
    // Valid enum values
    public const CREATION_METHODS = ['wizard', 'manual', 'auto_detected', 'imported', 'api'];
    public const CONTENT_TYPES = ['shortcode', 'block', 'mixed', 'custom'];
    public const STATUSES = ['active', 'inactive', 'orphaned', 'error'];
    
    public const PAGE_TYPES = [
        'grid', 'order', 'write-ad', 'confirm-order', 'payment', 
        'manage', 'thank-you', 'list', 'upload', 'no-orders'
    ];
    
    /**
     * Constructor
     *
     * @param array $data Initial data for the metadata
     */
    public function __construct( array $data = [] ) {
        $this->created_at = new DateTime();
        $this->updated_at = new DateTime();
        
        if ( !empty( $data ) ) {
            $this->fromArray( $data );
        }
    }
    
    /**
     * Populate metadata from array
     *
     * @param array $data
     * @return void
     */
    public function fromArray( array $data ): void {
        $this->id = intval( $data['id'] ?? 0 );
        $this->post_id = intval( $data['post_id'] ?? 0 );
        $this->page_type = sanitize_key( $data['page_type'] ?? '' );
        $this->creation_method = sanitize_key( $data['creation_method'] ?? 'manual' );
        $this->creation_source = !empty( $data['creation_source'] ) ? sanitize_text_field( $data['creation_source'] ) : null;
        $this->mds_version = sanitize_text_field( $data['mds_version'] ?? MDS_VERSION );
        $this->content_type = sanitize_key( $data['content_type'] ?? 'shortcode' );
        $this->status = sanitize_key( $data['status'] ?? 'active' );
        $this->confidence_score = floatval( $data['confidence_score'] ?? 1.0 );
        
        // Handle arrays
        $this->shortcode_attributes = is_array( $data['shortcode_attributes'] ?? null ) ? $data['shortcode_attributes'] : [];
        $this->block_attributes = is_array( $data['block_attributes'] ?? null ) ? $data['block_attributes'] : [];
        $this->detected_patterns = is_array( $data['detected_patterns'] ?? null ) ? $data['detected_patterns'] : [];
        $this->page_config = is_array( $data['page_config'] ?? null ) ? $data['page_config'] : [];
        $this->display_settings = is_array( $data['display_settings'] ?? null ) ? $data['display_settings'] : [];
        $this->integration_settings = is_array( $data['integration_settings'] ?? null ) ? $data['integration_settings'] : [];
        $this->validation_errors = is_array( $data['validation_errors'] ?? null ) ? $data['validation_errors'] : null;
        
        // Handle dates
        if ( !empty( $data['last_validated'] ) ) {
            $this->last_validated = is_string( $data['last_validated'] ) 
                ? DateTime::createFromFormat( 'Y-m-d H:i:s', $data['last_validated'] ) 
                : $data['last_validated'];
        }
        
        if ( !empty( $data['created_at'] ) ) {
            $this->created_at = is_string( $data['created_at'] ) 
                ? DateTime::createFromFormat( 'Y-m-d H:i:s', $data['created_at'] ) 
                : $data['created_at'];
        }
        
        if ( !empty( $data['updated_at'] ) ) {
            $this->updated_at = is_string( $data['updated_at'] ) 
                ? DateTime::createFromFormat( 'Y-m-d H:i:s', $data['updated_at'] ) 
                : $data['updated_at'];
        }
    }
    
    /**
     * Convert metadata to array
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'page_type' => $this->page_type,
            'creation_method' => $this->creation_method,
            'creation_source' => $this->creation_source,
            'mds_version' => $this->mds_version,
            'content_type' => $this->content_type,
            'status' => $this->status,
            'confidence_score' => $this->confidence_score,
            'shortcode_attributes' => $this->shortcode_attributes,
            'block_attributes' => $this->block_attributes,
            'detected_patterns' => $this->detected_patterns,
            'page_config' => $this->page_config,
            'display_settings' => $this->display_settings,
            'integration_settings' => $this->integration_settings,
            'last_validated' => $this->last_validated?->format( 'Y-m-d H:i:s' ),
            'validation_errors' => $this->validation_errors,
            'created_at' => $this->created_at->format( 'Y-m-d H:i:s' ),
            'updated_at' => $this->updated_at->format( 'Y-m-d H:i:s' )
        ];
    }
    
    /**
     * Validate the metadata
     *
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(): array {
        $errors = [];
        
        if ( empty( $this->post_id ) ) {
            $errors[] = Language::get( 'Post ID is required' );
        }
        
        if ( empty( $this->page_type ) || !in_array( $this->page_type, self::PAGE_TYPES ) ) {
            $errors[] = Language::get( 'Valid page type is required' );
        }
        
        if ( empty( $this->creation_method ) || !in_array( $this->creation_method, self::CREATION_METHODS ) ) {
            $errors[] = Language::get( 'Valid creation method is required' );
        }
        
        if ( empty( $this->content_type ) || !in_array( $this->content_type, self::CONTENT_TYPES ) ) {
            $errors[] = Language::get( 'Valid content type is required' );
        }
        
        if ( empty( $this->status ) || !in_array( $this->status, self::STATUSES ) ) {
            $errors[] = Language::get( 'Valid status is required' );
        }
        
        if ( $this->confidence_score < 0 || $this->confidence_score > 1 ) {
            $errors[] = Language::get( 'Confidence score must be between 0 and 1' );
        }
        
        return $errors;
    }
    
    /**
     * Check if metadata is valid
     *
     * @return bool
     */
    public function isValid(): bool {
        return empty( $this->validate() );
    }
    
    /**
     * Update the updated_at timestamp
     *
     * @return void
     */
    public function touch(): void {
        $this->updated_at = new DateTime();
    }
    
    /**
     * Mark as validated
     *
     * @param array|null $errors Validation errors if any
     * @return void
     */
    public function markValidated( ?array $errors = null ): void {
        $this->last_validated = new DateTime();
        $this->validation_errors = $errors;
        $this->touch();
    }
    
    /**
     * Get human-readable page type
     *
     * @return string
     */
    public function getPageTypeLabel(): string {
        return match( $this->page_type ) {
            'grid' => Language::get( 'Grid' ),
            'order' => Language::get( 'Order Pixels' ),
            'write-ad' => Language::get( 'Write Your Ad' ),
            'confirm-order' => Language::get( 'Confirm Order' ),
            'payment' => Language::get( 'Payment' ),
            'manage' => Language::get( 'Manage Pixels' ),
            'thank-you' => Language::get( 'Thank You' ),
            'list' => Language::get( 'List' ),
            'upload' => Language::get( 'Upload' ),
            'no-orders' => Language::get( 'No Orders' ),
            default => ucfirst( str_replace( '-', ' ', $this->page_type ) )
        };
    }
    
    /**
     * Get human-readable creation method
     *
     * @return string
     */
    public function getCreationMethodLabel(): string {
        return match( $this->creation_method ) {
            'wizard' => Language::get( 'Setup Wizard' ),
            'manual' => Language::get( 'Manual Creation' ),
            'auto_detected' => Language::get( 'Auto-Detected' ),
            'imported' => Language::get( 'Imported' ),
            'api' => Language::get( 'API' ),
            default => ucfirst( str_replace( '_', ' ', $this->creation_method ) )
        };
    }
    
    /**
     * Get human-readable content type
     *
     * @return string
     */
    public function getContentTypeLabel(): string {
        return match( $this->content_type ) {
            'shortcode' => Language::get( 'Shortcode' ),
            'block' => Language::get( 'Block' ),
            'mixed' => Language::get( 'Mixed Content' ),
            'custom' => Language::get( 'Custom' ),
            default => ucfirst( $this->content_type )
        };
    }
    
    /**
     * Get status badge class for admin display
     *
     * @return string
     */
    public function getStatusBadgeClass(): string {
        return match( $this->status ) {
            'active' => 'mds-status-active',
            'inactive' => 'mds-status-inactive',
            'orphaned' => 'mds-status-orphaned',
            'error' => 'mds-status-error',
            default => 'mds-status-unknown'
        };
    }
    
    /**
     * Check if page needs validation
     *
     * @param int $max_age_days Maximum age in days before revalidation needed
     * @return bool
     */
    public function needsValidation( int $max_age_days = 7 ): bool {
        if ( $this->last_validated === null ) {
            return true;
        }
        
        $max_age = new DateTime();
        $max_age->modify( "-{$max_age_days} days" );
        
        return $this->last_validated < $max_age;
    }
} 