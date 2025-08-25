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

namespace MillionDollarScript\Classes\Admin;

use WP_List_Table;
use MillionDollarScript\Classes\Data\MDSPageMetadataManager;
use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

// Load WP_List_Table if not already loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for MDS pages
 */
class MDSPageListTable extends WP_List_Table {
    
    private MDSPageMetadataManager $metadata_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct( [
            'singular' => 'mds_page',
            'plural' => 'mds_pages',
            'ajax' => true
        ] );
        
        $this->metadata_manager = MDSPageMetadataManager::getInstance();
    }
    
    /**
     * Get columns
     *
     * @return array
     */
    public function get_columns(): array {
        return [
            'cb' => '<input type="checkbox" />',
            'title' => Language::get( 'Title' ),
            'page_type' => Language::get( 'Page Type' ),
            'implementation' => Language::get( 'Implementation' ),
            'status' => Language::get( 'Status' ),
            'actions' => Language::get( 'Actions' )
        ];
    }
    
    /**
     * Get sortable columns
     *
     * @return array
     */
    public function get_sortable_columns(): array {
        return [
            'title' => [ 'title', false ],
            'page_type' => [ 'page_type', false ],
            'implementation' => [ 'implementation', false ],
            'status' => [ 'status', false ]
        ];
    }
    
    /**
     * Get bulk actions
     *
     * @return array
     */
    public function get_bulk_actions(): array {
        return [
            'scan' => Language::get( 'Scan Pages' ),
            'repair' => Language::get( 'Repair Pages' ),
            'activate' => Language::get( 'Activate' ),
            'deactivate' => Language::get( 'Deactivate' ),
            'remove_from_list' => Language::get( 'Remove from MDS Management' ),
            'delete' => Language::get( 'Delete Page' )
        ];
    }
    
    /**
     * Prepare items for display
     *
     * @return void
     */
    public function prepare_items(): void {
        $per_page = $this->get_items_per_page( 'mds_pages_per_page', 20 );
        $current_page = $this->get_pagenum();
        
        // Get filters
        $filters = [
            'page_type' => sanitize_key( $_GET['page_type'] ?? '' ),
            'status' => sanitize_key( $_GET['status'] ?? '' ),
            'implementation_type' => sanitize_key( $_GET['implementation_type'] ?? '' ),
            'search' => sanitize_text_field( $_GET['search'] ?? '' )
        ];
        
        // Get sorting
        $orderby = sanitize_key( $_GET['orderby'] ?? 'title' );
        $order = sanitize_key( $_GET['order'] ?? 'asc' );
        
        // Get data
        $data = $this->get_page_data( $filters, $orderby, $order );
        
        // Pagination
        $total_items = count( $data );
        $data = array_slice( $data, ( $current_page - 1 ) * $per_page, $per_page );
        
        $this->items = $data;
        
        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil( $total_items / $per_page )
        ] );
        
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns()
        ];
    }
    
    /**
     * Get page data
     *
     * @param array $filters
     * @param string $orderby
     * @param string $order
     * @return array
     */
    private function get_page_data( array $filters, string $orderby, string $order ): array {
        $pages = $this->metadata_manager->getAllPages();
        $data = [];
        
        foreach ( $pages as $metadata ) {
            $post = get_post( $metadata->post_id );
            if ( !$post ) {
                continue;
            }
            
            $item = [
                'ID' => $metadata->post_id,
                'title' => $post->post_title,
                'page_type' => $metadata->page_type,
                'implementation' => $metadata->content_type,
                'status' => $metadata->status,
                'last_scan' => $metadata->last_validated ? $metadata->last_validated->format( 'Y-m-d H:i:s' ) : null,
                'confidence' => $metadata->confidence_score,
                'post_status' => $post->post_status,
                'post_url' => get_permalink( $metadata->post_id ),
                'edit_url' => get_edit_post_link( $metadata->post_id ),
                'metadata' => $metadata
            ];
            
            // Apply filters
            if ( !empty( $filters['page_type'] ) && $item['page_type'] !== $filters['page_type'] ) {
                continue;
            }
            
            if ( !empty( $filters['implementation_type'] ) && $item['implementation'] !== $filters['implementation_type'] ) {
                continue;
            }
            
            if ( !empty( $filters['status'] ) ) {
                if ( $filters['status'] === 'active' && $item['status'] !== 'active' ) {
                    continue;
                }
                if ( $filters['status'] === 'inactive' && $item['status'] !== 'inactive' ) {
                    continue;
                }
                if ( $filters['status'] === 'needs_attention' && !in_array( $item['status'], [ 'needs_repair', 'validation_failed', 'missing_content' ] ) ) {
                    continue;
                }
            }
            
            if ( !empty( $filters['search'] ) ) {
                $search_term = strtolower( $filters['search'] );
                if ( strpos( strtolower( $item['title'] ), $search_term ) === false &&
                     strpos( strtolower( $item['page_type'] ), $search_term ) === false ) {
                    continue;
                }
            }
            
            $data[] = $item;
        }
        
        // Sort data
        usort( $data, function( $a, $b ) use ( $orderby, $order ) {
            $result = 0;
            
            switch ( $orderby ) {
                case 'title':
                    $result = strcmp( $a['title'], $b['title'] );
                    break;
                case 'page_type':
                    $result = strcmp( $a['page_type'], $b['page_type'] );
                    break;
                case 'implementation':
                    $result = strcmp( $a['implementation'], $b['implementation'] );
                    break;
                case 'status':
                    $result = strcmp( $a['status'], $b['status'] );
                    break;
                default:
                    $result = strcmp( $a['title'], $b['title'] );
            }
            
            return $order === 'desc' ? -$result : $result;
        } );
        
        return $data;
    }
    
    /**
     * Render checkbox column
     *
     * @param array $item
     * @return string
     */
    public function column_cb( $item ): string {
        return sprintf(
            '<input type="checkbox" name="page_ids[]" value="%s" />',
            $item['ID']
        );
    }
    
    /**
     * Render title column
     *
     * @param array $item
     * @return string
     */
    public function column_title( $item ): string {
        $title = esc_html( $item['title'] );
        
        // Row actions - Edit, View, and Delete in title column
        $actions = [
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url( $item['edit_url'] ),
                esc_html( Language::get( 'Edit' ) )
            ),
            'view' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url( $item['post_url'] ),
                esc_html( Language::get( 'View' ) )
            ),
            'delete' => sprintf(
                '<a href="#" class="mds-delete-page submitdelete" data-page-id="%d" style="color: #b32d2e;">%s</a>',
                intval( $item['ID'] ),
                esc_html( Language::get( 'Delete' ) )
            )
        ];
        
        return $title . $this->row_actions( $actions );
    }
    
    /**
     * Render page type column
     *
     * @param array $item
     * @return string
     */
    public function column_page_type( $item ): string {
        $type_labels = [
            'grid' => Language::get( 'Pixel Grid' ),
            'order' => Language::get( 'Order Page' ),
            'write-ad' => Language::get( 'Write Advertisement' ),
            'confirm-order' => Language::get( 'Order Confirmation' ),
            'payment' => Language::get( 'Payment Processing' ),
            'manage' => Language::get( 'Manage Ads' ),
            'thank-you' => Language::get( 'Thank You' ),
            'list' => Language::get( 'Advertiser List' ),
            'upload' => Language::get( 'File Upload' ),
            'no-orders' => Language::get( 'No Orders' )
        ];
        
        // Check if it's a predefined type
        if ( isset( $type_labels[$item['page_type']] ) ) {
            $label = $type_labels[$item['page_type']];
        } else {
            // For custom types, format them nicely
            $label = $this->formatCustomPageType( $item['page_type'] );
        }
        
        return sprintf(
            '<span class="mds-page-type mds-page-type-%s">%s</span>',
            esc_attr( $item['page_type'] ),
            esc_html( $label )
        );
    }
    
    /**
     * Format custom page type for display
     *
     * @param string $page_type
     * @return string
     */
    private function formatCustomPageType( string $page_type ): string {
        // Convert "platform-leaderboard" to "Platform Leaderboard"
        $formatted = str_replace( ['-', '_'], ' ', $page_type );
        $formatted = ucwords( $formatted );
        
        return $formatted;
    }
    
    /**
     * Render implementation column
     *
     * @param array $item
     * @return string
     */
    public function column_implementation( $item ): string {
        $implementation_labels = [
            'shortcode' => Language::get( 'Shortcode' ),
            'block' => Language::get( 'Block' ),
            'unknown' => Language::get( 'Unknown' )
        ];
        
        $label = $implementation_labels[$item['implementation']] ?? $item['implementation'];
        
        return sprintf(
            '<span class="mds-implementation mds-implementation-%s">%s</span>',
            esc_attr( $item['implementation'] ),
            esc_html( $label )
        );
    }
    
    /**
     * Render status column
     *
     * @param array $item
     * @return string
     */
    public function column_status( $item ): string {
        $status_labels = [
            'active' => Language::get( 'Active' ),
            'inactive' => Language::get( 'Inactive' ),
            'needs_repair' => Language::get( 'Needs Repair' ),
            'validation_failed' => Language::get( 'Validation Failed' ),
            'missing_content' => Language::get( 'Missing Content' ),
            'unknown' => Language::get( 'Unknown' )
        ];
        
        $label = $status_labels[$item['status']] ?? $item['status'];
        
        $status_class = 'mds-status-' . $item['status'];
        $has_error = false;
        
        if ( in_array( $item['status'], [ 'needs_repair', 'validation_failed', 'missing_content' ] ) ) {
            $status_class .= ' mds-status-warning';
            $has_error = true;
        } elseif ( $item['status'] === 'active' ) {
            $status_class .= ' mds-status-success';
        }
        
        $status_html = sprintf(
            '<span class="mds-status %s">%s</span>',
            esc_attr( $status_class ),
            esc_html( $label )
        );
        
        // Add help link for error statuses that links to error scanning functionality
        if ( $has_error ) {
            $help_link = sprintf(
                ' <a href="#" class="mds-status-help-link" data-page-id="%d" title="%s">
                    <span class="dashicons dashicons-editor-help"></span>
                </a>',
                $item['ID'],
                esc_attr( Language::get( 'Click to scan for specific errors and get repair suggestions for this page' ) )
            );
            $status_html .= $help_link;
        }
        
        return $status_html;
    }
    
    /**
     * Render last scan column
     *
     * @param array $item
     * @return string
     */
    public function column_last_scan( $item ): string {
        if ( !$item['last_scan'] ) {
            return '<span class="mds-never-scanned">' . esc_html( Language::get( 'Never' ) ) . '</span>';
        }
        
        $time_diff = human_time_diff( strtotime( $item['last_scan'] ), current_time( 'timestamp' ) );
        
        return sprintf(
            '<span class="mds-last-scan" title="%s">%s %s</span>',
            esc_attr( $item['last_scan'] ),
            esc_html( $time_diff ),
            esc_html( Language::get( 'ago' ) )
        );
    }
    
    /**
     * Render confidence column
     *
     * @param array $item
     * @return string
     */
    public function column_confidence( $item ): string {
        $confidence = $item['confidence'];
        $percentage = number_format( $confidence * 100, 1 );
        
        $confidence_class = 'mds-confidence';
        if ( $confidence >= 0.8 ) {
            $confidence_class .= ' mds-confidence-high';
        } elseif ( $confidence >= 0.6 ) {
            $confidence_class .= ' mds-confidence-medium';
        } else {
            $confidence_class .= ' mds-confidence-low';
        }
        
        return sprintf(
            '<span class="%s">
                <span class="mds-confidence-bar">
                    <span class="mds-confidence-fill" style="width: %s%%"></span>
                </span>
                <span class="mds-confidence-text">%s%%</span>
            </span>',
            esc_attr( $confidence_class ),
            esc_attr( $percentage ),
            esc_html( $percentage )
        );
    }
    
    /**
     * Render actions column
     *
     * @param array $item
     * @return string
     */
    public function column_actions( $item ): string {
        $actions = [];
        
        
        $actions[] = sprintf(
            '<button type="button" class="button button-small mds-view-details" data-page-id="%d" title="%s">
                <span class="dashicons dashicons-visibility"></span>
            </button>',
            $item['ID'],
            esc_attr( Language::get( 'View Details' ) )
        );
        
        $actions[] = sprintf(
            '<button type="button" class="button button-small mds-scan-page" data-page-id="%d" title="%s">
                <span class="dashicons dashicons-search"></span>
            </button>',
            $item['ID'],
            esc_attr( Language::get( 'Scan Page' ) )
        );
        
        // Add activate/deactivate toggle based on current status
        $is_active = ( $item['status'] === 'active' );
        $toggle_action = $is_active ? 'deactivate' : 'activate';
        $toggle_title = $is_active ? Language::get( 'Deactivate Page' ) : Language::get( 'Activate Page' );
        $toggle_icon = $is_active ? 'dashicons-no' : 'dashicons-yes-alt';
        $toggle_class = $is_active ? 'mds-deactivate-page' : 'mds-activate-page';
        
        $actions[] = sprintf(
            '<button type="button" class="button button-small %s" data-page-id="%d" data-action="%s" title="%s">
                <span class="dashicons %s"></span>
            </button>',
            esc_attr( $toggle_class ),
            $item['ID'],
            esc_attr( $toggle_action ),
            esc_attr( $toggle_title ),
            esc_attr( $toggle_icon )
        );
        
        if ( in_array( $item['status'], [ 'needs_repair', 'validation_failed', 'missing_content' ] ) ) {
            $actions[] = sprintf(
                '<button type="button" class="button button-small mds-repair-page" data-page-id="%d" title="%s">
                    <span class="dashicons dashicons-admin-tools"></span>
                </button>',
                $item['ID'],
                esc_attr( Language::get( 'Repair Page' ) )
            );
        }
        
        $actions[] = sprintf(
            '<button type="button" class="button button-small mds-configure-page" data-page-id="%d" title="%s">
                <span class="dashicons dashicons-admin-settings"></span>
            </button>',
            $item['ID'],
            esc_attr( Language::get( 'Configure' ) )
        );
        
        return '<div class="mds-action-buttons">' . implode( ' ', $actions ) . '</div>';
    }
    
    /**
     * Default column renderer
     *
     * @param array $item
     * @param string $column_name
     * @return string
     */
    public function column_default( $item, $column_name ): string {
        return $item[$column_name] ?? '';
    }
    
    /**
     * Display when no items found
     *
     * @return void
     */
    public function no_items(): void {
        echo esc_html( Language::get( 'No MDS pages found.' ) );
    }
    
    /**
     * Get views (status filter links)
     *
     * @return array
     */
    protected function get_views(): array {
        $views = [];
        $current_status = $_GET['status'] ?? '';
        
        // Get counts
        $counts = $this->get_status_counts();
        
        // All pages
        $class = empty( $current_status ) ? 'current' : '';
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url( admin_url( 'admin.php?page=mds-manage-pages' ) ),
            $class,
            esc_html( Language::get( 'All' ) ),
            $counts['total']
        );
        
        // Active pages
        if ( $counts['active'] > 0 ) {
            $class = $current_status === 'active' ? 'current' : '';
            $views['active'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url( add_query_arg( 'status', 'active', admin_url( 'admin.php?page=mds-manage-pages' ) ) ),
                $class,
                esc_html( Language::get( 'Active' ) ),
                $counts['active']
            );
        }
        
        // Inactive pages
        if ( $counts['inactive'] > 0 ) {
            $class = $current_status === 'inactive' ? 'current' : '';
            $views['inactive'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url( add_query_arg( 'status', 'inactive', admin_url( 'admin.php?page=mds-manage-pages' ) ) ),
                $class,
                esc_html( Language::get( 'Inactive' ) ),
                $counts['inactive']
            );
        }
        
        // Needs attention
        if ( $counts['needs_attention'] > 0 ) {
            $class = $current_status === 'needs_attention' ? 'current' : '';
            $views['needs_attention'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url( add_query_arg( 'status', 'needs_attention', admin_url( 'admin.php?page=mds-manage-pages' ) ) ),
                $class,
                esc_html( Language::get( 'Needs Attention' ) ),
                $counts['needs_attention']
            );
        }
        
        return $views;
    }
    
    /**
     * Get status counts
     *
     * @return array
     */
    private function get_status_counts(): array {
        $pages = $this->metadata_manager->getAllPages();
        $counts = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'needs_attention' => 0
        ];
        
        foreach ( $pages as $metadata ) {
            $counts['total']++;
            
            $status = $metadata->status;
            
            if ( $status === 'active' ) {
                $counts['active']++;
            } elseif ( $status === 'inactive' ) {
                $counts['inactive']++;
            } elseif ( in_array( $status, [ 'needs_repair', 'validation_failed', 'missing_content' ] ) ) {
                $counts['needs_attention']++;
            }
        }
        
        return $counts;
    }
} 