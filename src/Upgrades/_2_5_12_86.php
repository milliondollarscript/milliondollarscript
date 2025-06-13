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

namespace MillionDollarScript\Upgrades;

use MillionDollarScript\Classes\Data\MDSBackwardCompatibilityManager;
use MillionDollarScript\Classes\Data\MDSLegacyShortcodeHandler;
use MillionDollarScript\Classes\Data\MDSPageMetadataManager;
use MillionDollarScript\Classes\Data\MDSPageWordPressIntegration;
use MillionDollarScript\Classes\System\Logs;

defined( 'ABSPATH' ) or exit;

/** @noinspection PhpUnused */

class _2_5_12_86 {

    /**
     * Upgrade to version 2.5.12.86
     * 
     * This upgrade initializes the MDS Page Management System with backward compatibility:
     * - Initializes the metadata system database tables
     * - Sets up backward compatibility for existing installations
     * - Migrates existing pages to the new metadata system
     * - Initializes legacy shortcode handlers
     *
     * @param string $version Current database version
     * 
     * @return void
     */
    public function upgrade( $version ): void {
        if ( version_compare( $version, '2.5.12.86', '<' ) ) {
            
            Logs::log( 'MDS Upgrade _2_5_12_86 starting - Initializing Page Management System.' );
            
            try {
                // 1. Initialize metadata system database tables
                $this->initializeMetadataSystem();
                
                // 2. Initialize WordPress integration
                $this->initializeWordPressIntegration();
                
                // 3. Initialize legacy shortcode handlers
                $this->initializeLegacyShortcodeHandlers();
                
                // 4. Initialize backward compatibility system
                $this->initializeBackwardCompatibility();
                
                // 5. Perform initial migration if this is an upgrade from older version
                if ( version_compare( $version, '2.5.12.0', '<' ) ) {
                    $this->performInitialMigration();
                }
                
                // 6. Set up background tasks
                $this->setupBackgroundTasks();
                
                Logs::log( 'MDS Upgrade _2_5_12_86 completed successfully.' );
                
            } catch ( \Exception $e ) {
                Logs::log( 'MDS Upgrade _2_5_12_86 failed: ' . $e->getMessage() );
                throw $e;
            }
        }
    }
    
    /**
     * Initialize the metadata system database tables
     *
     * @return void
     */
    private function initializeMetadataSystem(): void {
        Logs::log( 'Initializing MDS metadata system database tables.' );
        
        // Initialize WordPress integration which creates the tables
        $wp_integration = new MDSPageWordPressIntegration();
        $wp_integration->createDatabaseTables();
        
        // Verify tables were created
        $metadata_manager = MDSPageMetadataManager::getInstance();
        if ( ! $metadata_manager->isDatabaseReady() ) {
            throw new \Exception( 'Failed to create metadata system database tables' );
        }
        
        Logs::log( 'MDS metadata system database tables created successfully.' );
    }
    
    /**
     * Initialize WordPress integration
     *
     * @return void
     */
    private function initializeWordPressIntegration(): void {
        Logs::log( 'Initializing MDS WordPress integration.' );
        
        // Initialize WordPress hooks and integration
        $wp_integration = new MDSPageWordPressIntegration();
        $wp_integration->initialize();
        
        Logs::log( 'MDS WordPress integration initialized.' );
    }
    
    /**
     * Initialize legacy shortcode handlers
     *
     * @return void
     */
    private function initializeLegacyShortcodeHandlers(): void {
        Logs::log( 'Initializing MDS legacy shortcode handlers.' );
        
        // Initialize legacy shortcode handler
        $legacy_handler = MDSLegacyShortcodeHandler::getInstance();
        
        // Clear any existing legacy usage data to start fresh
        $legacy_handler->clearLegacyUsageData();
        
        Logs::log( 'MDS legacy shortcode handlers initialized.' );
    }
    
    /**
     * Initialize backward compatibility system
     *
     * @return void
     */
    private function initializeBackwardCompatibility(): void {
        Logs::log( 'Initializing MDS backward compatibility system.' );
        
        // Initialize backward compatibility manager
        $compatibility_manager = MDSBackwardCompatibilityManager::getInstance();
        
        // Trigger plugin activation event for compatibility system
        do_action( 'mds_plugin_activated' );
        
        Logs::log( 'MDS backward compatibility system initialized.' );
    }
    
    /**
     * Perform initial migration for upgrades from older versions
     *
     * @return void
     */
    private function performInitialMigration(): void {
        Logs::log( 'Performing initial migration for existing MDS installation.' );
        
        $compatibility_manager = MDSBackwardCompatibilityManager::getInstance();
        
        // Perform migration in background to avoid timeout
        if ( ! wp_next_scheduled( 'mds_initial_migration' ) ) {
            wp_schedule_single_event( time() + 30, 'mds_initial_migration' );
        }
        
        // Add hook for the background migration
        add_action( 'mds_initial_migration', function() use ( $compatibility_manager ) {
            try {
                $results = $compatibility_manager->migrateExistingPagesToMetadataSystem();
                Logs::log( sprintf( 
                    'Initial migration completed: %d total, %d migrated, %d skipped, %d failed',
                    $results['total_pages'],
                    $results['migrated_pages'],
                    $results['skipped_pages'],
                    $results['failed_pages']
                ) );
            } catch ( \Exception $e ) {
                Logs::log( 'Initial migration failed: ' . $e->getMessage() );
            }
        } );
        
        Logs::log( 'Initial migration scheduled for background execution.' );
    }
    
    /**
     * Set up background tasks
     *
     * @return void
     */
    private function setupBackgroundTasks(): void {
        Logs::log( 'Setting up MDS background tasks.' );
        
        // Schedule background migration hook if not already scheduled
        if ( ! wp_next_scheduled( 'mds_background_migration' ) ) {
            add_action( 'mds_background_migration', function() {
                $compatibility_manager = MDSBackwardCompatibilityManager::getInstance();
                $compatibility_manager->migrateExistingPagesToMetadataSystem();
            } );
        }
        
        // Set up version tracking
        update_option( 'mds_page_management_version', '2.5.12.86' );
        update_option( 'mds_page_management_initialized', current_time( 'mysql' ) );
        
        Logs::log( 'MDS background tasks configured.' );
    }
    
    /**
     * Verify upgrade completion
     *
     * @return bool
     */
    private function verifyUpgradeCompletion(): bool {
        try {
            // Check if metadata system is ready
            $metadata_manager = MDSPageMetadataManager::getInstance();
            if ( ! $metadata_manager->isDatabaseReady() ) {
                return false;
            }
            
            // Check if compatibility system is initialized
            $compatibility_version = get_option( 'mds_compatibility_version' );
            if ( ! $compatibility_version ) {
                return false;
            }
            
            // Check if legacy shortcode handlers are working
            $legacy_handler = MDSLegacyShortcodeHandler::getInstance();
            $legacy_shortcodes = $legacy_handler->getLegacyShortcodes();
            if ( empty( $legacy_shortcodes ) ) {
                return false;
            }
            
            return true;
            
        } catch ( \Exception $e ) {
            Logs::log( 'Upgrade verification failed: ' . $e->getMessage() );
            return false;
        }
    }
} 