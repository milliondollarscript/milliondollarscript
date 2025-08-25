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

use wpdb;
use WP_Error;
use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

/**
 * Repository for MDS Page Metadata database operations
 */
class MDSPageMetadataRepository {
    
    private wpdb $wpdb;
    private string $table_name;
    private string $config_table_name;
    private string $detection_log_table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'mds_page_metadata';
        $this->config_table_name = $wpdb->prefix . 'mds_page_config';
        $this->detection_log_table_name = $wpdb->prefix . 'mds_detection_log';
    }
    
    /**
     * Create database tables
     *
     * @return bool|WP_Error
     */
    public function createTables() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Main metadata table - dbDelta compatible format
        $sql_metadata = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            page_type VARCHAR(50) NOT NULL,
            creation_method VARCHAR(20) NOT NULL,
            creation_source VARCHAR(100) DEFAULT NULL,
            mds_version VARCHAR(20) NOT NULL,
            content_type VARCHAR(20) NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            confidence_score DECIMAL(3,2) DEFAULT 1.00,
            shortcode_attributes LONGTEXT DEFAULT NULL,
            block_attributes LONGTEXT DEFAULT NULL,
            detected_patterns LONGTEXT DEFAULT NULL,
            page_config LONGTEXT DEFAULT NULL,
            display_settings LONGTEXT DEFAULT NULL,
            integration_settings LONGTEXT DEFAULT NULL,
            last_validated DATETIME DEFAULT NULL,
            validation_errors LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_post_id (post_id),
            KEY idx_page_type (page_type),
            KEY idx_creation_method (creation_method),
            KEY idx_content_type (content_type),
            KEY idx_status (status),
            KEY idx_last_validated (last_validated)
        ) $charset_collate;";
        
        // Configuration table - dbDelta compatible format
        $sql_config = "CREATE TABLE {$this->config_table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            config_key VARCHAR(100) NOT NULL,
            config_value LONGTEXT NOT NULL,
            config_type VARCHAR(20) DEFAULT 'string',
            is_encrypted TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_post_config (post_id, config_key),
            KEY idx_config_key (config_key),
            KEY idx_config_type (config_type)
        ) $charset_collate;";
        
        // Detection log table - dbDelta compatible format
        $sql_detection = "CREATE TABLE {$this->detection_log_table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            detection_type VARCHAR(50) NOT NULL,
            detection_method VARCHAR(100) NOT NULL,
            confidence_score DECIMAL(3,2) NOT NULL,
            detection_data LONGTEXT DEFAULT NULL,
            result VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_detection_type (detection_type),
            KEY idx_result (result),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        $result1 = dbDelta( $sql_metadata );
        $result2 = dbDelta( $sql_config );
        $result3 = dbDelta( $sql_detection );
        
        // Check for errors
        if ( $this->wpdb->last_error ) {
            return new WP_Error( 'db_error', $this->wpdb->last_error );
        }
        
        return true;
    }
    
    /**
     * Save metadata to database
     *
     * @param MDSPageMetadata $metadata
     * @return bool|WP_Error
     */
    public function save( MDSPageMetadata $metadata ) {
        // Check if tables exist before attempting save
        if ( !$this->tablesExist() ) {
            error_log( "MDS Repository: Metadata tables do not exist, cannot save" );
            return new WP_Error( 'tables_missing', 'Metadata tables do not exist' );
        }
        
        // Validate metadata
        $validation_errors = $metadata->validate();
        if ( !empty( $validation_errors ) ) {
            return new WP_Error( 'validation_error', implode( ', ', $validation_errors ) );
        }
        
        $data = [
            'post_id' => $metadata->post_id,
            'page_type' => $metadata->page_type,
            'creation_method' => $metadata->creation_method,
            'creation_source' => $metadata->creation_source,
            'mds_version' => $metadata->mds_version,
            'content_type' => $metadata->content_type,
            'status' => $metadata->status,
            'confidence_score' => $metadata->confidence_score,
            'shortcode_attributes' => !empty( $metadata->shortcode_attributes ) ? wp_json_encode( $metadata->shortcode_attributes ) : null,
            'block_attributes' => !empty( $metadata->block_attributes ) ? wp_json_encode( $metadata->block_attributes ) : null,
            'detected_patterns' => !empty( $metadata->detected_patterns ) ? wp_json_encode( $metadata->detected_patterns ) : null,
            'page_config' => !empty( $metadata->page_config ) ? wp_json_encode( $metadata->page_config ) : null,
            'display_settings' => !empty( $metadata->display_settings ) ? wp_json_encode( $metadata->display_settings ) : null,
            'integration_settings' => !empty( $metadata->integration_settings ) ? wp_json_encode( $metadata->integration_settings ) : null,
            'last_validated' => $metadata->last_validated?->format( 'Y-m-d H:i:s' ),
            'validation_errors' => $metadata->validation_errors ? wp_json_encode( $metadata->validation_errors ) : null,
            'updated_at' => $metadata->updated_at->format( 'Y-m-d H:i:s' )
        ];
        
        $formats = [
            '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f',
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        ];
        
        if ( $metadata->id > 0 ) {
            // Update existing record
            $result = $this->wpdb->update(
                $this->table_name,
                $data,
                [ 'id' => $metadata->id ],
                $formats,
                [ '%d' ]
            );
        } else {
            // Insert new record
            $data['created_at'] = $metadata->created_at->format( 'Y-m-d H:i:s' );
            $formats[] = '%s';
            
            $result = $this->wpdb->insert(
                $this->table_name,
                $data,
                $formats
            );
            
            if ( $result !== false ) {
                $metadata->id = $this->wpdb->insert_id;
            }
        }
        
        if ( $result === false ) {
            $error_msg = $this->wpdb->last_error ?: Language::get( 'Database operation failed' );
            error_log( 'MDS Metadata Save Error: ' . $error_msg );
            return new WP_Error( 'db_error', $error_msg );
        }
        
        return true;
    }
    
    /**
     * Find metadata by post ID
     *
     * @param int $post_id
     * @return MDSPageMetadata|null
     */
    public function findByPostId( int $post_id ): ?MDSPageMetadata {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE post_id = %d",
            $post_id
        );
        
        $row = $this->wpdb->get_row( $sql, ARRAY_A );
        
        if ( !$row ) {
            return null;
        }
        
        return $this->rowToMetadata( $row );
    }
    
    /**
     * Find metadata by ID
     *
     * @param int $id
     * @return MDSPageMetadata|null
     */
    public function findById( int $id ): ?MDSPageMetadata {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        );
        
        $row = $this->wpdb->get_row( $sql, ARRAY_A );
        
        if ( !$row ) {
            return null;
        }
        
        return $this->rowToMetadata( $row );
    }
    
    /**
     * Find pages by type
     *
     * @param string $page_type
     * @param string $status
     * @return array
     */
    public function findByType( string $page_type, string $status = 'active' ): array {
        return $this->findAll( [
            'page_type' => $page_type,
            'status' => $status
        ] );
    }
    
    /**
     * Find first page by type
     *
     * @param string $page_type
     * @param string $status
     * @return MDSPageMetadata|null
     */
    public function findFirstByType( string $page_type, string $status = 'active' ): ?MDSPageMetadata {
        // Check if tables exist before querying
        if ( !$this->tablesExist() ) {
            return null;
        }
        
        try {
            $results = $this->findAll( [
                'page_type' => $page_type,
                'status' => $status
            ], 1 );
            
            $metadata = !empty( $results ) ? $results[0] : null;
            
            // If we found metadata but with active status, verify the post still exists
            if ( $metadata && $status === 'active' ) {
                $post_status = get_post_status( $metadata->post_id );
                if ( $post_status === false ) {
                    // Post doesn't exist anymore, try to find any metadata for this type regardless of status
                    $backup_results = $this->findAll( [
                        'page_type' => $page_type
                    ], 1 );
                    
                    if ( !empty( $backup_results ) ) {
                        $backup_metadata = $backup_results[0];
                        $backup_post_status = get_post_status( $backup_metadata->post_id );
                        if ( $backup_post_status !== false ) {
                            return $backup_metadata;
                        }
                    }
                    
                    // No valid metadata found
                    return null;
                }
            }
            
            return $metadata;
        } catch ( \Exception $e ) {
            error_log( 'MDS Repository findFirstByType error: ' . $e->getMessage() );
            return null;
        }
    }
    
    /**
     * Find all metadata with optional filters
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findAll( array $filters = [], int $limit = 0, int $offset = 0 ): array {
        $where_clauses = [];
        $where_values = [];
        
        if ( !empty( $filters['page_type'] ) ) {
            $where_clauses[] = 'page_type = %s';
            $where_values[] = $filters['page_type'];
        }
        
        if ( !empty( $filters['creation_method'] ) ) {
            $where_clauses[] = 'creation_method = %s';
            $where_values[] = $filters['creation_method'];
        }
        
        if ( !empty( $filters['content_type'] ) ) {
            $where_clauses[] = 'content_type = %s';
            $where_values[] = $filters['content_type'];
        }
        
        if ( !empty( $filters['status'] ) ) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $filters['status'];
        }
        
        if ( isset( $filters['needs_validation'] ) && $filters['needs_validation'] ) {
            $days_ago = intval( $filters['validation_max_age'] ?? 7 );
            $where_clauses[] = '(last_validated IS NULL OR last_validated < DATE_SUB(NOW(), INTERVAL %d DAY))';
            $where_values[] = $days_ago;
        }
        
        $sql = "SELECT * FROM {$this->table_name}";
        
        if ( !empty( $where_clauses ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where_clauses );
        }
        
        $sql .= ' ORDER BY created_at DESC';
        
        if ( $limit > 0 ) {
            $sql .= ' LIMIT %d';
            $where_values[] = $limit;
            
            if ( $offset > 0 ) {
                $sql .= ' OFFSET %d';
                $where_values[] = $offset;
            }
        }
        
        if ( !empty( $where_values ) ) {
            $sql = $this->wpdb->prepare( $sql, $where_values );
        }
        
        $rows = $this->wpdb->get_results( $sql, ARRAY_A );
        
        $metadata_objects = [];
        foreach ( $rows as $row ) {
            $metadata_objects[] = $this->rowToMetadata( $row );
        }
        
        return $metadata_objects;
    }
    
    /**
     * Count metadata records with optional filters
     *
     * @param array $filters
     * @return int
     */
    public function count( array $filters = [] ): int {
        $where_clauses = [];
        $where_values = [];
        
        if ( !empty( $filters['page_type'] ) ) {
            $where_clauses[] = 'page_type = %s';
            $where_values[] = $filters['page_type'];
        }
        
        if ( !empty( $filters['status'] ) ) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $filters['status'];
        }
        
        $sql = "SELECT COUNT(*) FROM {$this->table_name}";
        
        if ( !empty( $where_clauses ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where_clauses );
        }
        
        if ( !empty( $where_values ) ) {
            $sql = $this->wpdb->prepare( $sql, $where_values );
        }
        
        return intval( $this->wpdb->get_var( $sql ) );
    }
    
    /**
     * Delete metadata by post ID
     *
     * @param int $post_id
     * @return bool|WP_Error
     */
    public function deleteByPostId( int $post_id ) {
        $result = $this->wpdb->delete(
            $this->table_name,
            [ 'post_id' => $post_id ],
            [ '%d' ]
        );
        
        if ( $result === false ) {
            return new WP_Error( 'db_error', $this->wpdb->last_error ?: Language::get( 'Database operation failed' ) );
        }
        
        return true;
    }
    
    /**
     * Delete metadata by ID
     *
     * @param int $id
     * @return bool|WP_Error
     */
    public function deleteById( int $id ) {
        $result = $this->wpdb->delete(
            $this->table_name,
            [ 'id' => $id ],
            [ '%d' ]
        );
        
        if ( $result === false ) {
            return new WP_Error( 'db_error', $this->wpdb->last_error ?: Language::get( 'Database operation failed' ) );
        }
        
        return true;
    }
    
    /**
     * Get statistics about MDS pages
     *
     * @return array
     */
    public function getStatistics(): array {
        $sql = "
            SELECT 
                COUNT(*) as total_pages,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_pages,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_pages,
                SUM(CASE WHEN status = 'orphaned' THEN 1 ELSE 0 END) as orphaned_pages,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error_pages,
                SUM(CASE WHEN content_type = 'shortcode' THEN 1 ELSE 0 END) as shortcode_pages,
                SUM(CASE WHEN content_type = 'block' THEN 1 ELSE 0 END) as block_pages,
                SUM(CASE WHEN content_type = 'mixed' THEN 1 ELSE 0 END) as mixed_pages,
                SUM(CASE WHEN creation_method = 'wizard' THEN 1 ELSE 0 END) as wizard_created,
                SUM(CASE WHEN creation_method = 'auto_detected' THEN 1 ELSE 0 END) as auto_detected,
                AVG(confidence_score) as avg_confidence,
                SUM(CASE WHEN last_validated IS NULL OR last_validated < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as needs_validation
            FROM {$this->table_name}
        ";
        
        $stats = $this->wpdb->get_row( $sql, ARRAY_A );
        
        // Convert to integers and format
        foreach ( $stats as $key => $value ) {
            if ( $key === 'avg_confidence' ) {
                $stats[$key] = round( floatval( $value ), 2 );
            } else {
                $stats[$key] = intval( $value );
            }
        }
        
        return $stats;
    }
    
    /**
     * Log detection attempt
     *
     * @param int $post_id
     * @param string $detection_type
     * @param string $detection_method
     * @param float $confidence_score
     * @param array $detection_data
     * @param string $result
     * @return bool|WP_Error
     */
    public function logDetection( int $post_id, string $detection_type, string $detection_method, float $confidence_score, array $detection_data, string $result ) {
        $data = [
            'post_id' => $post_id,
            'detection_type' => $detection_type,
            'detection_method' => $detection_method,
            'confidence_score' => $confidence_score,
            'detection_data' => wp_json_encode( $detection_data ),
            'result' => $result
        ];
        
        $result = $this->wpdb->insert(
            $this->detection_log_table_name,
            $data,
            [ '%d', '%s', '%s', '%f', '%s', '%s' ]
        );
        
        if ( $result === false ) {
            return new WP_Error( 'db_error', $this->wpdb->last_error ?: Language::get( 'Database operation failed' ) );
        }
        
        return true;
    }
    
    /**
     * Convert database row to MDSPageMetadata object
     *
     * @param array $row
     * @return MDSPageMetadata
     */
    private function rowToMetadata( array $row ): MDSPageMetadata {
        // Decode JSON fields
        $row['shortcode_attributes'] = !empty( $row['shortcode_attributes'] ) ? json_decode( $row['shortcode_attributes'], true ) : [];
        $row['block_attributes'] = !empty( $row['block_attributes'] ) ? json_decode( $row['block_attributes'], true ) : [];
        $row['detected_patterns'] = !empty( $row['detected_patterns'] ) ? json_decode( $row['detected_patterns'], true ) : [];
        $row['page_config'] = !empty( $row['page_config'] ) ? json_decode( $row['page_config'], true ) : [];
        $row['display_settings'] = !empty( $row['display_settings'] ) ? json_decode( $row['display_settings'], true ) : [];
        $row['integration_settings'] = !empty( $row['integration_settings'] ) ? json_decode( $row['integration_settings'], true ) : [];
        $row['validation_errors'] = !empty( $row['validation_errors'] ) ? json_decode( $row['validation_errors'], true ) : null;
        
        return new MDSPageMetadata( $row );
    }
    
    /**
     * Check if tables exist
     *
     * @return bool
     */
    public function tablesExist(): bool {
        // Check all three tables
        $tables_to_check = [
            $this->table_name,
            $this->config_table_name,
            $this->detection_log_table_name
        ];
        
        foreach ( $tables_to_check as $table ) {
            $table_exists = $this->wpdb->get_var( $this->wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            ) );
            
            if ( $table_exists !== $table ) {
                return false;
            }
        }
        
        return true;
    }
} 