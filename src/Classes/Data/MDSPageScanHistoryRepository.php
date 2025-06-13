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

defined( 'ABSPATH' ) or exit;

/**
 * Repository for managing scan history data
 */
class MDSPageScanHistoryRepository {
    
    private wpdb $wpdb;
    private string $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'mds_scan_history';
    }
    
    /**
     * Create scan history table
     *
     * @return bool|WP_Error
     */
    public function createTable() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            scan_type ENUM('activation_scan', 'manual_scan', 'targeted_scan', 'scheduled_scan') NOT NULL,
            total_pages INT(11) NOT NULL DEFAULT 0,
            scanned_pages INT(11) NOT NULL DEFAULT 0,
            detected_pages INT(11) NOT NULL DEFAULT 0,
            updated_pages INT(11) NOT NULL DEFAULT 0,
            error_pages INT(11) NOT NULL DEFAULT 0,
            execution_time INT(11) NOT NULL DEFAULT 0,
            memory_usage BIGINT(20) NOT NULL DEFAULT 0,
            confidence_threshold DECIMAL(3,2) DEFAULT 0.60,
            batch_size INT(11) DEFAULT 50,
            results_data LONGTEXT DEFAULT NULL,
            scan_status ENUM('running', 'completed', 'cancelled', 'failed') DEFAULT 'completed',
            started_by VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME DEFAULT NULL,
            
            PRIMARY KEY (id),
            KEY idx_scan_type (scan_type),
            KEY idx_scan_status (scan_status),
            KEY idx_created_at (created_at),
            KEY idx_completed_at (completed_at)
        ) $charset_collate;";
        
        $result = dbDelta( $sql );
        
        if ( $this->wpdb->last_error ) {
            return new WP_Error( 'db_error', $this->wpdb->last_error );
        }
        
        return true;
    }
    
    /**
     * Save scan results
     *
     * @param array $scan_data
     * @return int|WP_Error Scan ID or error
     */
    public function saveScanResults( array $scan_data ) {
        $data = [
            'scan_type' => sanitize_key( $scan_data['scan_type'] ?? 'manual_scan' ),
            'total_pages' => intval( $scan_data['total_pages'] ?? 0 ),
            'scanned_pages' => intval( $scan_data['scanned_pages'] ?? 0 ),
            'detected_pages' => intval( $scan_data['detected_pages'] ?? 0 ),
            'updated_pages' => intval( $scan_data['updated_pages'] ?? 0 ),
            'error_pages' => intval( $scan_data['error_pages'] ?? 0 ),
            'execution_time' => intval( $scan_data['execution_time'] ?? 0 ),
            'memory_usage' => intval( $scan_data['memory_usage'] ?? 0 ),
            'confidence_threshold' => floatval( $scan_data['confidence_threshold'] ?? 0.6 ),
            'batch_size' => intval( $scan_data['batch_size'] ?? 50 ),
            'results_data' => wp_json_encode( $scan_data ),
            'scan_status' => sanitize_key( $scan_data['scan_status'] ?? 'completed' ),
            'started_by' => sanitize_text_field( $scan_data['started_by'] ?? get_current_user_id() ),
            'completed_at' => $scan_data['completed_at'] ?? current_time( 'mysql' )
        ];
        
        $formats = [
            '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%d', '%s', '%s', '%s', '%s'
        ];
        
        $result = $this->wpdb->insert( $this->table_name, $data, $formats );
        
        if ( $result === false ) {
            return new WP_Error( 'db_error', $this->wpdb->last_error );
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Get scan history with pagination
     *
     * @param int $limit
     * @param int $offset
     * @param array $filters
     * @return array
     */
    public function getScanHistory( int $limit = 20, int $offset = 0, array $filters = [] ): array {
        $where_clauses = [];
        $where_values = [];
        
        // Apply filters
        if ( !empty( $filters['scan_type'] ) ) {
            $where_clauses[] = 'scan_type = %s';
            $where_values[] = $filters['scan_type'];
        }
        
        if ( !empty( $filters['scan_status'] ) ) {
            $where_clauses[] = 'scan_status = %s';
            $where_values[] = $filters['scan_status'];
        }
        
        if ( !empty( $filters['date_from'] ) ) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if ( !empty( $filters['date_to'] ) ) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        $where_sql = '';
        if ( !empty( $where_clauses ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
        }
        
        $sql = "SELECT * FROM {$this->table_name} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        if ( !empty( $where_values ) ) {
            $sql = $this->wpdb->prepare( $sql, $where_values );
        }
        
        $results = $this->wpdb->get_results( $sql, ARRAY_A );
        
        // Decode JSON data
        foreach ( $results as &$result ) {
            if ( !empty( $result['results_data'] ) ) {
                $result['results_data'] = json_decode( $result['results_data'], true );
            }
        }
        
        return $results;
    }
    
    /**
     * Get scan by ID
     *
     * @param int $scan_id
     * @return array|null
     */
    public function getScanById( int $scan_id ): ?array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $scan_id
        );
        
        $result = $this->wpdb->get_row( $sql, ARRAY_A );
        
        if ( $result && !empty( $result['results_data'] ) ) {
            $result['results_data'] = json_decode( $result['results_data'], true );
        }
        
        return $result;
    }
    
    /**
     * Get latest scan of specific type
     *
     * @param string $scan_type
     * @return array|null
     */
    public function getLatestScan( string $scan_type = null ): ?array {
        $where_sql = '';
        $values = [];
        
        if ( $scan_type ) {
            $where_sql = 'WHERE scan_type = %s';
            $values[] = $scan_type;
        }
        
        $sql = "SELECT * FROM {$this->table_name} {$where_sql} ORDER BY created_at DESC LIMIT 1";
        
        if ( !empty( $values ) ) {
            $sql = $this->wpdb->prepare( $sql, $values );
        }
        
        $result = $this->wpdb->get_row( $sql, ARRAY_A );
        
        if ( $result && !empty( $result['results_data'] ) ) {
            $result['results_data'] = json_decode( $result['results_data'], true );
        }
        
        return $result;
    }
    
    /**
     * Get scan statistics
     *
     * @param array $filters
     * @return array
     */
    public function getScanStatistics( array $filters = [] ): array {
        $where_clauses = [];
        $where_values = [];
        
        // Apply filters
        if ( !empty( $filters['date_from'] ) ) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if ( !empty( $filters['date_to'] ) ) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        $where_sql = '';
        if ( !empty( $where_clauses ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
        }
        
        $sql = "SELECT 
            COUNT(*) as total_scans,
            SUM(total_pages) as total_pages_scanned,
            SUM(detected_pages) as total_pages_detected,
            SUM(updated_pages) as total_pages_updated,
            SUM(error_pages) as total_errors,
            AVG(execution_time) as avg_execution_time,
            AVG(memory_usage) as avg_memory_usage,
            scan_type,
            COUNT(*) as scans_by_type
        FROM {$this->table_name} {$where_sql}
        GROUP BY scan_type";
        
        if ( !empty( $where_values ) ) {
            $sql = $this->wpdb->prepare( $sql, $where_values );
        }
        
        $results = $this->wpdb->get_results( $sql, ARRAY_A );
        
        // Get overall statistics
        $overall_sql = "SELECT 
            COUNT(*) as total_scans,
            SUM(total_pages) as total_pages_scanned,
            SUM(detected_pages) as total_pages_detected,
            SUM(updated_pages) as total_pages_updated,
            SUM(error_pages) as total_errors,
            AVG(execution_time) as avg_execution_time,
            AVG(memory_usage) as avg_memory_usage
        FROM {$this->table_name} {$where_sql}";
        
        if ( !empty( $where_values ) ) {
            $overall_sql = $this->wpdb->prepare( $overall_sql, $where_values );
        }
        
        $overall = $this->wpdb->get_row( $overall_sql, ARRAY_A );
        
        return [
            'overall' => $overall,
            'by_type' => $results
        ];
    }
    
    /**
     * Delete old scan records
     *
     * @param int $days_to_keep
     * @return int Number of deleted records
     */
    public function cleanupOldScans( int $days_to_keep = 30 ): int {
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days_to_keep} days" ) );
        
        $sql = $this->wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < %s",
            $cutoff_date
        );
        
        $result = $this->wpdb->query( $sql );
        
        return $result !== false ? $result : 0;
    }
    
    /**
     * Update scan status
     *
     * @param int $scan_id
     * @param string $status
     * @return bool
     */
    public function updateScanStatus( int $scan_id, string $status ): bool {
        $result = $this->wpdb->update(
            $this->table_name,
            [
                'scan_status' => $status,
                'completed_at' => current_time( 'mysql' )
            ],
            [ 'id' => $scan_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        
        return $result !== false;
    }
    
    /**
     * Check if table exists
     *
     * @return bool
     */
    public function tableExists(): bool {
        $table_name = $this->table_name;
        $sql = $this->wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        );
        
        return $this->wpdb->get_var( $sql ) === $table_name;
    }
    
    /**
     * Get scan performance metrics
     *
     * @param int $limit
     * @return array
     */
    public function getPerformanceMetrics( int $limit = 10 ): array {
        $sql = $this->wpdb->prepare(
            "SELECT 
                scan_type,
                total_pages,
                execution_time,
                memory_usage,
                (detected_pages / total_pages * 100) as detection_rate,
                created_at
            FROM {$this->table_name} 
            WHERE scan_status = 'completed' 
            ORDER BY created_at DESC 
            LIMIT %d",
            $limit
        );
        
        return $this->wpdb->get_results( $sql, ARRAY_A );
    }
} 