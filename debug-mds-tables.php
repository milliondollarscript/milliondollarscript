<?php
/**
 * Debug script to check MDS metadata tables and scan functionality
 * 
 * Run this file from WordPress admin to diagnose the scanning issue
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

use MillionDollarScript\Classes\Data\MDSPageMetadataManager;
use MillionDollarScript\Classes\Data\MDSPageMetadataRepository;
use MillionDollarScript\Classes\Data\MDSPageDetectionEngine;

echo "<h1>MDS Metadata System Debug</h1>\n";
echo "<style>body{font-family: Arial, sans-serif;} .error{color: red;} .success{color: green;} .info{color: blue;}</style>\n";

// 1. Check if tables exist
echo "<h2>1. Database Tables Check</h2>\n";
$repository = new MDSPageMetadataRepository();
$tables_exist = $repository->tablesExist();

if ($tables_exist) {
    echo "<p class='success'>✓ All MDS metadata tables exist</p>\n";
} else {
    echo "<p class='error'>✗ MDS metadata tables are missing</p>\n";
    echo "<p class='info'>Attempting to create tables...</p>\n";
    
    $create_result = $repository->createTables();
    if (is_wp_error($create_result)) {
        echo "<p class='error'>✗ Failed to create tables: " . $create_result->get_error_message() . "</p>\n";
    } else {
        echo "<p class='success'>✓ Tables created successfully</p>\n";
        $tables_exist = true;
    }
}

// 2. Check table contents
if ($tables_exist) {
    echo "<h2>2. Table Contents</h2>\n";
    global $wpdb;
    
    $metadata_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mds_page_metadata");
    $config_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mds_page_config");
    $detection_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mds_detection_log");
    
    echo "<p>Metadata records: <strong>$metadata_count</strong></p>\n";
    echo "<p>Config records: <strong>$config_count</strong></p>\n";
    echo "<p>Detection log records: <strong>$detection_count</strong></p>\n";
    
    if ($metadata_count > 0) {
        echo "<h3>Recent Metadata Records:</h3>\n";
        $recent_records = $wpdb->get_results("
            SELECT pm.post_id, pm.page_type, pm.creation_method, pm.status, pm.confidence_score, pm.created_at, p.post_title
            FROM {$wpdb->prefix}mds_page_metadata pm
            LEFT JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
            ORDER BY pm.created_at DESC 
            LIMIT 5
        ");
        
        if ($recent_records) {
            echo "<table border='1' cellpadding='5'>\n";
            echo "<tr><th>Post ID</th><th>Title</th><th>Page Type</th><th>Method</th><th>Status</th><th>Confidence</th><th>Created</th></tr>\n";
            foreach ($recent_records as $record) {
                echo "<tr>\n";
                echo "<td>{$record->post_id}</td>\n";
                echo "<td>" . ($record->post_title ?: 'Unknown') . "</td>\n";
                echo "<td>{$record->page_type}</td>\n";
                echo "<td>{$record->creation_method}</td>\n";
                echo "<td>{$record->status}</td>\n";
                echo "<td>{$record->confidence_score}</td>\n";
                echo "<td>{$record->created_at}</td>\n";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
    }
}

// 3. Test detection on a sample page
echo "<h2>3. Detection Engine Test</h2>\n";
$sample_pages = get_posts([
    'post_type' => 'page',
    'post_status' => 'publish',
    'numberposts' => 3,
    'fields' => 'ids'
]);

if ($sample_pages) {
    $detection_engine = new MDSPageDetectionEngine();
    
    foreach ($sample_pages as $page_id) {
        $page = get_post($page_id);
        echo "<h4>Testing Page: {$page->post_title} (ID: $page_id)</h4>\n";
        
        $detection_result = $detection_engine->detectMDSPage($page_id);
        
        echo "<p><strong>Is MDS Page:</strong> " . ($detection_result['is_mds_page'] ? 'Yes' : 'No') . "</p>\n";
        echo "<p><strong>Confidence:</strong> " . $detection_result['confidence'] . "</p>\n";
        echo "<p><strong>Page Type:</strong> " . ($detection_result['page_type'] ?: 'None') . "</p>\n";
        echo "<p><strong>Content Type:</strong> " . $detection_result['content_type'] . "</p>\n";
        
        if (!empty($detection_result['patterns'])) {
            echo "<p><strong>Detected Patterns:</strong></p>\n";
            echo "<ul>\n";
            foreach ($detection_result['patterns'] as $pattern) {
                echo "<li>{$pattern['type']}: " . json_encode($pattern) . "</li>\n";
            }
            echo "</ul>\n";
        }
        
        echo "<hr>\n";
    }
} else {
    echo "<p class='error'>No published pages found to test</p>\n";
}

// 4. Test metadata manager
echo "<h2>4. Metadata Manager Test</h2>\n";
try {
    $manager = MDSPageMetadataManager::getInstance();
    $db_ready = $manager->isDatabaseReady();
    
    if ($db_ready) {
        echo "<p class='success'>✓ Metadata manager database is ready</p>\n";
        
        $stats = $manager->getStatistics();
        echo "<h3>Statistics:</h3>\n";
        echo "<ul>\n";
        foreach ($stats as $key => $value) {
            echo "<li><strong>$key:</strong> $value</li>\n";
        }
        echo "</ul>\n";
        
    } else {
        echo "<p class='error'>✗ Metadata manager database is not ready</p>\n";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Error testing metadata manager: " . $e->getMessage() . "</p>\n";
}

// 5. Check WordPress database errors
echo "<h2>5. WordPress Database Status</h2>\n";
global $wpdb;
if ($wpdb->last_error) {
    echo "<p class='error'>Last Database Error: " . $wpdb->last_error . "</p>\n";
} else {
    echo "<p class='success'>✓ No recent database errors</p>\n";
}

echo "<p class='info'><strong>Debug completed.</strong> If tables are missing or detection fails, this indicates the root cause of the scanning issue.</p>\n";