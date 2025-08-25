<?php
/*
 * Million Dollar Script Two - Compatibility Admin Template
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2025, Ryan Rhode
 * @license     https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 */

defined( 'ABSPATH' ) or exit;

use MillionDollarScript\Classes\Language\Language;

$compatibility_manager = \MillionDollarScript\Classes\Data\MDSBackwardCompatibilityManager::getInstance();
$status = $compatibility_manager->getCompatibilityStatus();
?>

<div class="wrap">
    <h1><?php echo esc_html( Language::get( 'MDS Backward Compatibility' ) ); ?></h1>
    
    <div class="mds-compatibility-dashboard">
        
        <!-- Status Overview -->
        <div class="mds-compatibility-status">
            <h2><?php echo esc_html( Language::get( 'Compatibility Status' ) ); ?></h2>
            
            <div class="mds-status-cards">
                <div class="mds-status-card">
                    <div class="mds-status-card-icon">
                        <span class="dashicons dashicons-admin-tools"></span>
                    </div>
                    <div class="mds-status-card-content">
                        <h3><?php echo esc_html( Language::get( 'Compatibility Version' ) ); ?></h3>
                        <p class="mds-status-value"><?php echo esc_html( $status['compatibility_version'] ); ?></p>
                    </div>
                </div>
                
                <div class="mds-status-card">
                    <div class="mds-status-card-icon">
                        <span class="dashicons dashicons-migrate"></span>
                    </div>
                    <div class="mds-status-card-content">
                        <h3><?php echo esc_html( Language::get( 'Migration Status' ) ); ?></h3>
                        <p class="mds-status-value">
                            <?php if ( $status['migration_completed'] ): ?>
                                <span class="mds-status-success"><?php echo esc_html( Language::get( 'Completed' ) ); ?></span>
                            <?php else: ?>
                                <span class="mds-status-warning"><?php echo esc_html( Language::get( 'Pending' ) ); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="mds-status-card">
                    <div class="mds-status-card-icon">
                        <span class="dashicons dashicons-admin-page"></span>
                    </div>
                    <div class="mds-status-card-content">
                        <h3><?php echo esc_html( Language::get( 'Total MDS Pages' ) ); ?></h3>
                        <p class="mds-status-value"><?php echo esc_html( $status['total_mds_pages'] ); ?></p>
                    </div>
                </div>
                
                <div class="mds-status-card">
                    <div class="mds-status-card-icon">
                        <span class="dashicons dashicons-database"></span>
                    </div>
                    <div class="mds-status-card-content">
                        <h3><?php echo esc_html( Language::get( 'Pages with Metadata' ) ); ?></h3>
                        <p class="mds-status-value"><?php echo esc_html( $status['pages_with_metadata'] ); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pending Migrations -->
        <?php if ( ! empty( $status['pending_migrations'] ) ): ?>
        <div class="mds-pending-migrations">
            <h2><?php echo esc_html( Language::get( 'Pending Migrations' ) ); ?></h2>
            
            <div class="notice notice-warning">
                <p><?php echo esc_html( Language::get( 'The following migrations are pending and should be completed to ensure full compatibility:' ) ); ?></p>
                <ul>
                    <?php foreach ( $status['pending_migrations'] as $migration ): ?>
                        <li>
                            <?php
                            switch ( $migration ) {
                                case 'initial_migration':
                                    echo esc_html( Language::get( 'Initial page migration to metadata system' ) );
                                    break;
                                case 'metadata_migration':
                                    echo esc_html( Language::get( 'Metadata migration for existing pages' ) );
                                    break;
                                default:
                                    echo esc_html( $migration );
                            }
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'mds_run_migration' ); ?>
                <p>
                    <input type="submit" name="run_migration" class="button button-primary" 
                           value="<?php echo esc_attr( Language::get( 'Run Migration Now' ) ); ?>" />
                    <span class="description">
                        <?php echo esc_html( Language::get( 'This will migrate existing MDS pages to the new metadata system. This process is safe and reversible.' ) ); ?>
                    </span>
                </p>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Migration Results -->
        <?php if ( ! empty( $status['migration_results'] ) ): ?>
        <div class="mds-migration-results">
            <h2><?php echo esc_html( Language::get( 'Last Migration Results' ) ); ?></h2>
            
            <div class="mds-migration-summary">
                <div class="mds-migration-stat">
                    <span class="mds-migration-label"><?php echo esc_html( Language::get( 'Total Pages Processed' ) ); ?>:</span>
                    <span class="mds-migration-value"><?php echo esc_html( $status['migration_results']['total_pages'] ?? 0 ); ?></span>
                </div>
                
                <div class="mds-migration-stat">
                    <span class="mds-migration-label"><?php echo esc_html( Language::get( 'Successfully Migrated' ) ); ?>:</span>
                    <span class="mds-migration-value mds-success"><?php echo esc_html( $status['migration_results']['migrated_pages'] ?? 0 ); ?></span>
                </div>
                
                <div class="mds-migration-stat">
                    <span class="mds-migration-label"><?php echo esc_html( Language::get( 'Skipped (Already Migrated)' ) ); ?>:</span>
                    <span class="mds-migration-value mds-info"><?php echo esc_html( $status['migration_results']['skipped_pages'] ?? 0 ); ?></span>
                </div>
                
                <div class="mds-migration-stat">
                    <span class="mds-migration-label"><?php echo esc_html( Language::get( 'Failed' ) ); ?>:</span>
                    <span class="mds-migration-value mds-error"><?php echo esc_html( $status['migration_results']['failed_pages'] ?? 0 ); ?></span>
                </div>
            </div>
            
            <?php if ( ! empty( $status['migration_results']['errors'] ) ): ?>
            <div class="mds-migration-errors">
                <h3><?php echo esc_html( Language::get( 'Migration Errors' ) ); ?></h3>
                <div class="mds-error-list">
                    <?php foreach ( $status['migration_results']['errors'] as $error ): ?>
                        <div class="mds-error-item">
                            <span class="dashicons dashicons-warning"></span>
                            <?php echo esc_html( $error ); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Compatibility Information -->
        <div class="mds-compatibility-info">
            <h2><?php echo esc_html( Language::get( 'Compatibility Information' ) ); ?></h2>
            
            <div class="mds-info-section">
                <h3><?php echo esc_html( Language::get( 'What is Backward Compatibility?' ) ); ?></h3>
                <p><?php echo esc_html( Language::get( 'The MDS Backward Compatibility system ensures that existing MDS pages continue to work seamlessly after upgrading to the new page management system. It automatically detects and migrates existing pages, shortcodes, and blocks to the new metadata system.' ) ); ?></p>
            </div>
            
            <div class="mds-info-section">
                <h3><?php echo esc_html( Language::get( 'Supported Legacy Features' ) ); ?></h3>
                <ul>
                    <li><?php echo esc_html( Language::get( 'Legacy shortcodes: [mds], [million_dollar_script], [pixel_grid]' ) ); ?></li>
                    <li><?php echo esc_html( Language::get( 'Legacy Gutenberg blocks: mds/grid-block, mds/order-block, etc.' ) ); ?></li>
                    <li><?php echo esc_html( Language::get( 'Existing page option references' ) ); ?></li>
                    <li><?php echo esc_html( Language::get( 'Custom page configurations and settings' ) ); ?></li>
                </ul>
            </div>
            
            <div class="mds-info-section">
                <h3><?php echo esc_html( Language::get( 'Migration Process' ) ); ?></h3>
                <ol>
                    <li><?php echo esc_html( Language::get( 'Scan all pages for MDS content (shortcodes, blocks, references)' ) ); ?></li>
                    <li><?php echo esc_html( Language::get( 'Analyze content and determine page types with confidence scoring' ) ); ?></li>
                    <li><?php echo esc_html( Language::get( 'Create metadata records for detected MDS pages' ) ); ?></li>
                    <li><?php echo esc_html( Language::get( 'Update legacy shortcodes and blocks to current standards' ) ); ?></li>
                    <li><?php echo esc_html( Language::get( 'Preserve all existing functionality and settings' ) ); ?></li>
                </ol>
            </div>
            
            <div class="mds-info-section">
                <h3><?php echo esc_html( Language::get( 'Safety and Reversibility' ) ); ?></h3>
                <p><?php echo esc_html( Language::get( 'The migration process is designed to be safe and non-destructive. Original page content is preserved, and migration notes are added to track changes. If needed, pages can be restored to their original state.' ) ); ?></p>
            </div>
        </div>
        
        <!-- Advanced Actions -->
        <div class="mds-advanced-actions">
            <h2><?php echo esc_html( Language::get( 'Advanced Actions' ) ); ?></h2>
            
            <div class="mds-action-buttons">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-page-management' ) ); ?>" 
                   class="button button-secondary">
                    <?php echo esc_html( Language::get( 'View Page Management' ) ); ?>
                </a>
                
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-page-creator' ) ); ?>" 
                   class="button button-secondary">
                    <?php echo esc_html( Language::get( 'Create New Pages' ) ); ?>
                </a>
                
                <button type="button" class="button button-secondary" id="mds-refresh-status">
                    <?php echo esc_html( Language::get( 'Refresh Status' ) ); ?>
                </button>
                
                <button type="button" class="button button-secondary" id="mds-debug-status">
                    <?php echo esc_html( Language::get( 'Debug System Status' ) ); ?>
                </button>
                
                <button type="button" class="button button-secondary" id="mds-reset-migration" style="color: #d63638;">
                    <?php echo esc_html( Language::get( 'Reset Migration Status' ) ); ?>
                </button>
                
                <?php if ( ! empty( $status['pending_migrations'] ) ): ?>
                <button type="button" class="button button-primary" id="mds-force-migration">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php echo esc_html( Language::get( 'Force Run Migration Now' ) ); ?>
                </button>
                <?php endif; ?>
            </div>
            
            <?php if ( ! empty( $status['pending_migrations'] ) ): ?>
            <div style="margin-top: 15px;">
                <p><strong>Note:</strong> If the regular migration seems stuck due to cron issues, use "Force Run Migration Now" to bypass the cron system and run the migration immediately.</p>
            </div>
            <?php endif; ?>
            
            <div id="mds-migration-progress" style="display: none; margin-top: 15px;">
                <div class="mds-notice mds-notice-info">
                    <p><span class="dashicons dashicons-update-alt" style="animation: rotation 2s infinite linear;"></span> Running migration...</p>
                </div>
            </div>
            
            <div id="mds-debug-output" style="display: none; margin-top: 15px;">
                <div class="mds-notice mds-notice-info">
                    <h3>Debug System Status</h3>
                    <div style="margin-bottom: 10px;">
                        <button type="button" class="button button-secondary" id="mds-copy-debug">Copy to Clipboard</button>
                        <button type="button" class="button button-secondary" id="mds-hide-debug">Hide Debug Info</button>
                    </div>
                    <pre id="mds-debug-content" style="background: #f1f1f1; padding: 15px; border: 1px solid #ddd; border-radius: 4px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; font-family: 'Courier New', monospace; font-size: 12px;"></pre>
                </div>
            </div>
            
            <!-- Error Resolution Section -->
            <div class="mds-error-resolution" style="margin-top: 30px;">
                <h2><?php echo esc_html( Language::get( 'Error Resolution' ) ); ?></h2>
                
                <div class="mds-error-controls">
                    <button type="button" class="button button-secondary" id="mds-scan-errors">
                        <span class="dashicons dashicons-search"></span>
                        <?php echo esc_html( Language::get( 'Scan for Page Errors' ) ); ?>
                    </button>
                    
                    <button type="button" class="button button-primary" id="mds-fix-all-errors" style="display: none;">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php echo esc_html( Language::get( 'Fix All Auto-Fixable Errors' ) ); ?>
                    </button>
                </div>
                
                <div id="mds-error-results" style="display: none; margin-top: 20px;">
                    <div class="mds-error-list"></div>
                </div>
                
                <div id="mds-error-progress" style="display: none; margin-top: 15px;">
                    <div class="mds-notice mds-notice-info">
                        <p><span class="dashicons dashicons-update-alt" style="animation: rotation 2s infinite linear;"></span> <span id="mds-error-progress-text">Scanning for errors...</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Error Details Modal -->
<div id="mds-error-modal" style="display: none;">
    <div class="mds-modal-overlay">
        <div class="mds-modal-content">
            <div class="mds-modal-header">
                <h3 id="mds-modal-title"><?php echo esc_html( Language::get( 'Page Error Details' ) ); ?></h3>
                <button type="button" class="mds-modal-close" id="mds-modal-close">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="mds-modal-body" id="mds-modal-body">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="mds-modal-footer">
                <button type="button" class="button button-secondary" id="mds-modal-cancel">
                    <?php echo esc_html( Language::get( 'Close' ) ); ?>
                </button>
                <button type="button" class="button button-primary" id="mds-modal-fix" style="display: none;">
                    <?php echo esc_html( Language::get( 'Apply Fix' ) ); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.mds-compatibility-dashboard {
    max-width: 1200px;
}

.mds-compatibility-status {
    margin-bottom: 30px;
}

.mds-status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.mds-status-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.mds-status-card-icon {
    margin-right: 15px;
}

.mds-status-card-icon .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
    color: #0073aa;
}

.mds-status-card-content h3 {
    margin: 0 0 5px 0;
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.mds-status-value {
    font-size: 24px;
    font-weight: bold;
    margin: 0;
    color: #333;
}

.mds-status-success {
    color: #46b450;
}

.mds-status-warning {
    color: #ffb900;
}

.mds-status-error {
    color: #dc3232;
}

.mds-pending-migrations,
.mds-migration-results,
.mds-compatibility-info,
.mds-advanced-actions {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.mds-migration-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.mds-migration-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}

.mds-migration-label {
    font-weight: 500;
    color: #666;
}

.mds-migration-value {
    font-weight: bold;
    font-size: 18px;
}

.mds-migration-value.mds-success {
    color: #46b450;
}

.mds-migration-value.mds-info {
    color: #0073aa;
}

.mds-migration-value.mds-error {
    color: #dc3232;
}

.mds-migration-errors {
    margin-top: 20px;
}

.mds-error-list {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    background: #fafafa;
}

.mds-error-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
    padding: 15px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.mds-error-item-content {
    flex: 1;
}

.mds-error-item-title {
    font-weight: bold;
    margin-bottom: 5px;
}

.mds-error-item-details {
    color: #666;
    font-size: 13px;
    margin-bottom: 5px;
}

.mds-error-item-actions {
    display: flex;
    gap: 10px;
}

.mds-error-item.auto-fixable {
    border-left: 4px solid #46b450;
}

.mds-error-item.manual-fix {
    border-left: 4px solid #ffb900;
}

.mds-error-item.critical {
    border-left: 4px solid #dc3232;
}

/* Modal Styles */
.mds-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 160000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.mds-modal-content {
    background: #fff;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.mds-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #ddd;
    background: #f9f9f9;
}

.mds-modal-header h3 {
    margin: 0;
    color: #333;
}

.mds-modal-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #666;
    padding: 5px;
}

.mds-modal-close:hover {
    color: #dc3232;
}

.mds-modal-body {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.mds-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 20px;
    border-top: 1px solid #ddd;
    background: #f9f9f9;
}

.mds-error-detail {
    margin-bottom: 15px;
}

.mds-error-detail-label {
    font-weight: bold;
    margin-bottom: 5px;
    color: #333;
}

.mds-error-detail-value {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 4px;
    border-left: 3px solid #0073aa;
}

.mds-suggested-fixes {
    margin-top: 20px;
}

.mds-suggested-fix {
    background: #f0f8ff;
    border: 1px solid #0073aa;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 10px;
}

.mds-suggested-fix-title {
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 5px;
}

.mds-suggested-fix-description {
    color: #666;
    margin-bottom: 10px;
}

.mds-auto-fixable {
    background: #f0f9f0;
    border-color: #46b450;
}

.mds-auto-fixable .mds-suggested-fix-title {
    color: #46b450;
    border-radius: 3px;
    border-left: 3px solid #dc3232;
}

.mds-error-item .dashicons {
    color: #dc3232;
    margin-right: 8px;
}

.mds-info-section {
    margin-bottom: 20px;
}

.mds-info-section h3 {
    color: #0073aa;
    margin-bottom: 10px;
}

.mds-info-section ul,
.mds-info-section ol {
    margin-left: 20px;
}

.mds-info-section li {
    margin-bottom: 5px;
}

.mds-action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.mds-action-buttons .button {
    min-width: 150px;
}

@media (max-width: 768px) {
    .mds-status-cards {
        grid-template-columns: 1fr;
    }
    
    .mds-migration-summary {
        grid-template-columns: 1fr;
    }
    
    .mds-action-buttons {
        flex-direction: column;
    }
    
    .mds-action-buttons .button {
        width: 100%;
    }
}

/* Custom notice styles to avoid WordPress notice relocation */
.mds-notice {
    background: #fff;
    border-left: 4px solid #fff;
    box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
    margin: 5px 15px 2px;
    padding: 1px 12px;
}

.mds-notice-info {
    border-left-color: #00a0d2;
}

.mds-notice p {
    margin: 0.5em 0;
    padding: 2px;
}

@keyframes rotation {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(359deg);
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Check if pending migrations section should be hidden on page load
    function checkAndHidePendingMigrations() {
        console.log('Checking pending migrations status...');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mds_debug_status',
                nonce: '<?php echo wp_create_nonce( 'mds_debug_status' ); ?>'
            },
            success: function(response) {
                console.log('Debug status response:', response);
                if (response.success && response.data && response.data.pending_migrations) {
                    console.log('Pending migrations:', response.data.pending_migrations);
                    // If no pending migrations, hide the pending migrations section
                    if (response.data.pending_migrations.length === 0) {
                        console.log('No pending migrations - hiding section');
                        $('.mds-pending-migrations').hide();
                        $('.mds-migration-results').show(); // Make sure results show if hidden
                        $('#mds-migration-progress').hide(); // Also hide any progress divs
                    } else {
                        console.log('Still have pending migrations:', response.data.pending_migrations.length);
                    }
                } else {
                    console.log('Invalid response format');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', error);
            }
        });
    }
    
    // Ensure progress and debug divs are hidden on page load
    $('#mds-migration-progress').hide();
    $('#mds-debug-output').hide();
    
    // Run the check immediately on page load
    checkAndHidePendingMigrations();
    
    // Refresh status button
    $('#mds-refresh-status').on('click', function() {
        location.reload();
    });
    
    // Debug status button
    $('#mds-debug-status').on('click', function() {
        var $button = $(this);
        var $output = $('#mds-debug-output');
        var $content = $('#mds-debug-content');
        
        $button.prop('disabled', true).text('Loading...');
        $output.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mds_debug_status',
                nonce: '<?php echo wp_create_nonce( 'mds_debug_status' ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var debugInfo = JSON.stringify(response.data, null, 2);
                    $content.text(debugInfo);
                    $output.show();
                    
                    // Scroll to debug output
                    $('html, body').animate({
                        scrollTop: $output.offset().top - 50
                    }, 500);
                } else {
                    alert('Debug failed: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Debug failed: Network error occurred');
            },
            complete: function() {
                $button.prop('disabled', false).text('Debug System Status');
            }
        });
    });
    
    // Copy debug info to clipboard
    $('#mds-copy-debug').on('click', function() {
        var content = $('#mds-debug-content').text();
        
        // Try to use the modern clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(content).then(function() {
                var $button = $('#mds-copy-debug');
                var originalText = $button.text();
                $button.text('Copied!').addClass('button-primary');
                
                setTimeout(function() {
                    $button.text(originalText).removeClass('button-primary');
                }, 2000);
            });
        } else {
            // Fallback for older browsers
            var textArea = document.createElement('textarea');
            textArea.value = content;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                var $button = $('#mds-copy-debug');
                var originalText = $button.text();
                $button.text('Copied!').addClass('button-primary');
                
                setTimeout(function() {
                    $button.text(originalText).removeClass('button-primary');
                }, 2000);
            } catch (err) {
                alert('Failed to copy to clipboard. Please select the text manually and copy.');
            }
            
            document.body.removeChild(textArea);
        }
    });
    
    // Hide debug output
    $('#mds-hide-debug').on('click', function() {
        $('#mds-debug-output').hide();
    });
    
    // Reset migration button
    $('#mds-reset-migration').on('click', function() {
        if (!confirm('Are you sure you want to reset the migration status? This will clear all metadata and force a fresh migration.')) {
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true).text('Resetting...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mds_reset_migration',
                nonce: '<?php echo wp_create_nonce( 'mds_reset_migration' ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Migration status reset successfully!\n\n' + response.data.message);
                    location.reload();
                } else {
                    alert('Reset failed: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('Reset failed: Network error occurred');
            },
            complete: function() {
                $button.prop('disabled', false).text('Reset Migration Status');
            }
        });
    });
    
    // Force migration button
    $('#mds-force-migration').on('click', function() {
        if (!confirm('Are you sure you want to force run the migration? This will bypass the cron system and run immediately.')) {
            return;
        }
        
        var $button = $(this);
        var $progress = $('#mds-migration-progress');
        
        // Disable button and show progress
        $button.prop('disabled', true).text('Running...');
        $progress.show();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mds_manual_migration',
                nonce: '<?php echo wp_create_nonce( 'mds_manual_migration' ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Migration completed successfully!\n\n' + response.data.message);
                    location.reload();
                } else {
                    alert('Migration failed: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('Migration failed: Network error occurred');
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools"></span> Force Run Migration Now');
                $progress.hide();
            }
        });
    });
    
    // Auto-refresh every 30 seconds if migration is running
    // Check for pending migrations dynamically via AJAX instead of PHP template logic
    function checkMigrationStatus() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mds_debug_status',
                nonce: '<?php echo wp_create_nonce( 'mds_debug_status' ); ?>'
            },
            success: function(response) {
                if (response.success && response.data && response.data.pending_migrations) {
                    // Only auto-refresh if there are actually pending migrations
                    if (response.data.pending_migrations.length > 0) {
                        setTimeout(function() {
                            if (window.location.href.indexOf('page=mds-compatibility') !== -1) {
                                location.reload();
                            }
                        }, 30000);
                    }
                }
            }
        });
    }
    
    // Initial check and then check every 30 seconds
    checkMigrationStatus();
    setInterval(checkMigrationStatus, 30000);
});
</script> 