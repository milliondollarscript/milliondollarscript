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

use WP_Query;
use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

/**
 * Scanner for detecting existing MDS pages on plugin activation
 */
class MDSPageActivationScanner {
    
    private MDSPageDetectionEngine $detection_engine;
    private MDSPageMetadataManager $metadata_manager;
    private MDSPageMetadataRepository $repository;
    private MDSPageScanHistoryRepository $scan_history;
    
    // Scan configuration
    private int $batch_size = 50;
    private int $max_execution_time = 30;
    private float $min_confidence_threshold = 0.6;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->detection_engine = new MDSPageDetectionEngine();
        $this->metadata_manager = MDSPageMetadataManager::getInstance();
        $this->repository = new MDSPageMetadataRepository();
        $this->scan_history = new MDSPageScanHistoryRepository();
    }
    
    /**
     * Perform full activation scan
     *
     * @param bool $force_rescan Force rescan of already scanned pages
     * @return array Scan results
     */
    public function performActivationScan( bool $force_rescan = false ): array {
        $start_time = time();
        $results = [
            'scan_started' => current_time( 'mysql' ),
            'total_pages' => 0,
            'scanned_pages' => 0,
            'detected_pages' => 0,
            'updated_pages' => 0,
            'skipped_pages' => 0,
            'error_pages' => 0,
            'execution_time' => 0,
            'memory_usage' => 0,
            'errors' => [],
            'detected_page_types' => [],
            'confidence_distribution' => [],
            'scan_completed' => false
        ];
        
        try {
            // Get all pages to scan
            $pages_to_scan = $this->getPagesToScan( $force_rescan );
            $results['total_pages'] = count( $pages_to_scan );
            
            if ( empty( $pages_to_scan ) ) {
                $results['scan_completed'] = true;
                return $results;
            }
            
            // Process pages in batches
            $batches = array_chunk( $pages_to_scan, $this->batch_size );
            
            foreach ( $batches as $batch_index => $batch ) {
                // Check execution time limit
                if ( ( time() - $start_time ) > $this->max_execution_time ) {
                    $results['errors'][] = Language::get( 'Scan stopped due to execution time limit' );
                    break;
                }
                
                $batch_results = $this->processBatch( $batch, $batch_index + 1, count( $batches ) );
                
                // Merge batch results
                $results['scanned_pages'] += $batch_results['scanned'];
                $results['detected_pages'] += $batch_results['detected'];
                $results['updated_pages'] += $batch_results['updated'];
                $results['skipped_pages'] += $batch_results['skipped'];
                $results['error_pages'] += $batch_results['errors'];
                $results['errors'] = array_merge( $results['errors'], $batch_results['error_messages'] );
                
                // Merge page type counts
                foreach ( $batch_results['page_types'] as $type => $count ) {
                    $results['detected_page_types'][$type] = ( $results['detected_page_types'][$type] ?? 0 ) + $count;
                }
                
                // Merge confidence distribution
                foreach ( $batch_results['confidence_levels'] as $level => $count ) {
                    $results['confidence_distribution'][$level] = ( $results['confidence_distribution'][$level] ?? 0 ) + $count;
                }
                
                // Memory management
                if ( function_exists( 'wp_suspend_cache_addition' ) ) {
                    wp_suspend_cache_addition( true );
                }
            }
            
            $results['scan_completed'] = true;
            
        } catch ( Exception $e ) {
            $results['errors'][] = sprintf(
                Language::get( 'Scan failed with exception: %s' ),
                $e->getMessage()
            );
        }
        
        // Final statistics
        $results['execution_time'] = time() - $start_time;
        $results['memory_usage'] = memory_get_peak_usage( true );
        $results['scan_ended'] = current_time( 'mysql' );
        
        // Store scan results
        $this->storeScanResults( $results );
        
        // Save to scan history
        $this->scan_history->saveScanResults( array_merge( $results, [
            'scan_type' => 'activation_scan',
            'confidence_threshold' => $this->min_confidence_threshold,
            'batch_size' => $this->batch_size
        ] ) );
        
        return $results;
    }
    
    /**
     * Get pages that need to be scanned
     *
     * @param bool $force_rescan
     * @return array
     */
    private function getPagesToScan( bool $force_rescan ): array {
        $query_args = [
            'post_type' => 'page',
            'post_status' => [ 'publish', 'private', 'draft' ],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ];
        
        // If not forcing rescan, exclude already scanned pages
        if ( !$force_rescan ) {
            $query_args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => '_mds_scanned',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_mds_scan_version',
                    'value' => MDS_VERSION,
                    'compare' => '!='
                ]
            ];
        }
        
        $query = new WP_Query( $query_args );
        return $query->posts;
    }
    
    /**
     * Process a batch of pages
     *
     * @param array $page_ids
     * @param int $batch_number
     * @param int $total_batches
     * @return array
     */
    private function processBatch( array $page_ids, int $batch_number, int $total_batches ): array {
        $batch_results = [
            'scanned' => 0,
            'detected' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_messages' => [],
            'page_types' => [],
            'confidence_levels' => [
                'high' => 0,    // 0.8+
                'medium' => 0,  // 0.6-0.79
                'low' => 0      // 0.4-0.59
            ]
        ];
        
        foreach ( $page_ids as $page_id ) {
            try {
                $batch_results['scanned']++;
                
                // Skip if page already has metadata (unless forcing rescan)
                if ( $this->metadata_manager->hasMetadata( $page_id ) ) {
                    $batch_results['skipped']++;
                    $this->markPageScanned( $page_id );
                    continue;
                }
                
                // Perform detection
                $detection_result = $this->detection_engine->detectMDSPage( $page_id );
                
                // Log detection attempt
                $this->repository->logDetection(
                    $page_id,
                    'activation_scan',
                    'multi_method_detection',
                    $detection_result['confidence'],
                    $detection_result,
                    $detection_result['is_mds_page'] ? 'detected' : 'not_detected'
                );
                
                if ( $detection_result['is_mds_page'] && 
                     $detection_result['confidence'] >= $this->min_confidence_threshold ) {
                    
                    $batch_results['detected']++;
                    
                    // Create metadata
                    $metadata_result = $this->metadata_manager->createOrUpdateMetadata(
                        $page_id,
                        $detection_result['page_type'] ?? 'grid',
                        'auto_detected',
                        [
                            'confidence_score' => $detection_result['confidence'],
                            'detected_patterns' => $detection_result['patterns'],
                            'content_type' => $detection_result['content_type'],
                            'creation_source' => 'activation_scan'
                        ]
                    );
                    
                    if ( !is_wp_error( $metadata_result ) ) {
                        $batch_results['updated']++;
                        
                        // Track page type
                        $page_type = $detection_result['page_type'] ?? 'unknown';
                        $batch_results['page_types'][$page_type] = ( $batch_results['page_types'][$page_type] ?? 0 ) + 1;
                        
                        // Track confidence level
                        $confidence = $detection_result['confidence'];
                        if ( $confidence >= 0.8 ) {
                            $batch_results['confidence_levels']['high']++;
                        } elseif ( $confidence >= 0.6 ) {
                            $batch_results['confidence_levels']['medium']++;
                        } else {
                            $batch_results['confidence_levels']['low']++;
                        }
                        
                    } else {
                        $batch_results['errors']++;
                        $batch_results['error_messages'][] = sprintf(
                            Language::get( 'Failed to create metadata for page %d: %s' ),
                            $page_id,
                            $metadata_result->get_error_message()
                        );
                    }
                }
                
                // Mark page as scanned
                $this->markPageScanned( $page_id );
                
            } catch ( Exception $e ) {
                $batch_results['errors']++;
                $batch_results['error_messages'][] = sprintf(
                    Language::get( 'Error processing page %d: %s' ),
                    $page_id,
                    $e->getMessage()
                );
            }
        }
        
        // Update progress
        $this->updateScanProgress( $batch_number, $total_batches, $batch_results );
        
        return $batch_results;
    }
    
    /**
     * Mark page as scanned
     *
     * @param int $page_id
     * @return void
     */
    private function markPageScanned( int $page_id ): void {
        update_post_meta( $page_id, '_mds_scanned', time() );
        update_post_meta( $page_id, '_mds_scan_version', MDS_VERSION );
    }
    
    /**
     * Update scan progress
     *
     * @param int $current_batch
     * @param int $total_batches
     * @param array $batch_results
     * @return void
     */
    private function updateScanProgress( int $current_batch, int $total_batches, array $batch_results ): void {
        $progress = [
            'current_batch' => $current_batch,
            'total_batches' => $total_batches,
            'progress_percentage' => round( ( $current_batch / $total_batches ) * 100, 2 ),
            'last_batch_results' => $batch_results,
            'updated_at' => current_time( 'mysql' )
        ];
        
        update_option( 'mds_scan_progress', $progress );
        
        // Trigger progress hook for real-time updates
        do_action( 'mds_scan_progress_updated', $progress );
    }
    
    /**
     * Store scan results
     *
     * @param array $results
     * @return void
     */
    private function storeScanResults( array $results ): void {
        // Store in options table
        update_option( 'mds_last_scan_results', $results );
        update_option( 'mds_last_scan_time', time() );
        
        // Store in custom table for history
        global $wpdb;
        $table_name = $wpdb->prefix . 'mds_scan_history';
        
        $wpdb->insert(
            $table_name,
            [
                'scan_type' => 'activation_scan',
                'total_pages' => $results['total_pages'],
                'scanned_pages' => $results['scanned_pages'],
                'detected_pages' => $results['detected_pages'],
                'updated_pages' => $results['updated_pages'],
                'error_pages' => $results['error_pages'],
                'execution_time' => $results['execution_time'],
                'memory_usage' => $results['memory_usage'],
                'results_data' => wp_json_encode( $results ),
                'created_at' => current_time( 'mysql' )
            ],
            [
                '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s'
            ]
        );
    }
    
    /**
     * Get scan progress
     *
     * @return array|null
     */
    public function getScanProgress(): ?array {
        return get_option( 'mds_scan_progress', null );
    }
    
    /**
     * Get last scan results
     *
     * @return array|null
     */
    public function getLastScanResults(): ?array {
        return get_option( 'mds_last_scan_results', null );
    }
    
    /**
     * Check if scan is currently running
     *
     * @return bool
     */
    public function isScanRunning(): bool {
        $progress = $this->getScanProgress();
        
        if ( !$progress ) {
            return false;
        }
        
        // Check if scan was updated recently (within 5 minutes)
        $last_update = strtotime( $progress['updated_at'] );
        return ( time() - $last_update ) < 300;
    }
    
    /**
     * Cancel running scan
     *
     * @return bool
     */
    public function cancelScan(): bool {
        delete_option( 'mds_scan_progress' );
        
        // Set cancellation flag
        update_option( 'mds_scan_cancelled', time() );
        
        return true;
    }
    
    /**
     * Perform targeted scan for specific page types
     *
     * @param array $page_types
     * @return array
     */
    public function performTargetedScan( array $page_types ): array {
        $results = [
            'target_types' => $page_types,
            'scanned' => 0,
            'detected' => 0,
            'updated' => 0,
            'errors' => []
        ];
        
        // Get pages that might match the target types
        $candidate_pages = $this->getCandidatePages( $page_types );
        
        foreach ( $candidate_pages as $page_id ) {
            try {
                $results['scanned']++;
                
                $detection_result = $this->detection_engine->detectMDSPage( $page_id );
                
                if ( $detection_result['is_mds_page'] && 
                     in_array( $detection_result['page_type'], $page_types ) ) {
                    
                    $results['detected']++;
                    
                    $metadata_result = $this->metadata_manager->createOrUpdateMetadata(
                        $page_id,
                        $detection_result['page_type'],
                        'targeted_scan',
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
                            Language::get( 'Failed to update page %d: %s' ),
                            $page_id,
                            $metadata_result->get_error_message()
                        );
                    }
                }
                
            } catch ( Exception $e ) {
                $results['errors'][] = sprintf(
                    Language::get( 'Error scanning page %d: %s' ),
                    $page_id,
                    $e->getMessage()
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Get candidate pages for targeted scan
     *
     * @param array $page_types
     * @return array
     */
    private function getCandidatePages( array $page_types ): array {
        $page_ids = [];
        
        // Search by title keywords
        foreach ( $page_types as $page_type ) {
            $keywords = $this->getKeywordsForPageType( $page_type );
            
            foreach ( $keywords as $keyword ) {
                $query = new WP_Query( [
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    's' => $keyword,
                    'fields' => 'ids',
                    'posts_per_page' => 50
                ] );
                
                $page_ids = array_merge( $page_ids, $query->posts );
            }
        }
        
        return array_unique( $page_ids );
    }
    
    /**
     * Get keywords for page type
     *
     * @param string $page_type
     * @return array
     */
    private function getKeywordsForPageType( string $page_type ): array {
        $keyword_map = [
            'grid' => [ 'grid', 'pixel', 'advertising' ],
            'order' => [ 'order', 'purchase', 'buy' ],
            'write-ad' => [ 'write', 'ad', 'create' ],
            'confirm-order' => [ 'confirm', 'confirmation' ],
            'payment' => [ 'payment', 'pay', 'checkout' ],
            'manage' => [ 'manage', 'dashboard', 'account' ],
            'thank-you' => [ 'thank', 'success', 'complete' ],
            'list' => [ 'list', 'directory' ],
            'upload' => [ 'upload', 'file' ],
            'no-orders' => [ 'no orders', 'empty' ]
        ];
        
        return $keyword_map[$page_type] ?? [];
    }
    
    /**
     * Set batch size for processing
     *
     * @param int $size
     * @return void
     */
    public function setBatchSize( int $size ): void {
        $this->batch_size = max( 1, min( 100, $size ) );
    }
    
    /**
     * Set maximum execution time
     *
     * @param int $seconds
     * @return void
     */
    public function setMaxExecutionTime( int $seconds ): void {
        $this->max_execution_time = max( 10, min( 300, $seconds ) );
    }
    
    /**
     * Set minimum confidence threshold
     *
     * @param float $threshold
     * @return void
     */
    public function setMinConfidenceThreshold( float $threshold ): void {
        $this->min_confidence_threshold = max( 0.1, min( 1.0, $threshold ) );
    }
} 