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

use MillionDollarScript\Classes\Admin\Notices;
use MillionDollarScript\Classes\Data\Database;
use MillionDollarScript\Classes\Data\DatabaseStatic;
use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

/** @noinspection PhpUnused */

class _2_5_11_40 {

    /**
     * Upgrade the database schema to version 2.5.11.40
     * 
     * This upgrade adds missing columns that were causing errors when upgrading from 2.3.5:
     * - Fixes the 'key' to 'config_key' column in wp_mds_config table (prioritized)
     * - Adds 'enabled' column to wp_mds_banners table
     * - Adds 'order_in_progress' column to wp_mds_orders table
     *
     * @param string $version Current database version
     * 
     * @return void
     */
    public function upgrade( $version ): void {
        if ( version_compare( $version, '2.5.11.40', '<' ) ) {
            global $wpdb;
            
            // 1. PRIORITY: Fix config_key column issue first
            // This is critical because other parts of the code rely on this column
            $this->ensure_config_key_column();
            
            // 2. Add 'enabled' column to banners table if it doesn't exist
            $table_name = MDS_DB_PREFIX . 'banners';
            if (DatabaseStatic::table_exists($table_name)) {
                $column_exists = $wpdb->get_var( "SHOW COLUMNS FROM `{$table_name}` LIKE 'enabled'" );
                
                if ( ! $column_exists ) {
                    $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN `enabled` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `banner_id`" );
                    $wpdb->flush();
                }
            }
            
            // 3. Add 'order_in_progress' column to orders table if it doesn't exist
            $table_name = MDS_DB_PREFIX . 'orders';
            if (DatabaseStatic::table_exists($table_name)) {
                $column_exists = $wpdb->get_var( "SHOW COLUMNS FROM `{$table_name}` LIKE 'order_in_progress'" );
                
                if ( ! $column_exists ) {
                    $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN `order_in_progress` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `user_id`" );
                    $wpdb->flush();
                }
            }
            
            // 4. Schedule the deletion of mu-plugins/milliondollarscript-cookies.php file
            // This is done as the last step to avoid logging users out during the upgrade
            $this->schedule_cookies_file_deletion();
        }
    }
    
    /**
     * Schedule deletion of mu-plugins/milliondollarscript-cookies.php file
     * This needs to happen asynchronously via cron to avoid logging users out during the upgrade
     * 
     * @return void
     */
    private function schedule_cookies_file_deletion(): void {
        // Check if the cookies file exists
        $mu_plugins_dir = WPMU_PLUGIN_DIR;
        $cookies_file = $mu_plugins_dir . '/milliondollarscript-cookies.php';
        
        if (file_exists($cookies_file)) {
            // Schedule the deletion for 5 minutes later via cron
            if (!wp_next_scheduled('mds_delete_cookies_file')) {
                wp_schedule_single_event(time() + 300, 'mds_delete_cookies_file');
                
                // Add an admin notice to warn about logout
                $notice = Language::get('The Million Dollar Script cookies file will be removed in 5 minutes. ');
                $notice .= Language::get('This will log all users out, but is necessary for proper plugin functionality. ');
                $notice .= Language::get('Please save your work and log back in after 5 minutes.');
                
                Notices::add_notice($notice, 'warning');
            }
            
            // Add the action hook for handling the deletion
            add_action('mds_delete_cookies_file', array($this, 'delete_cookies_file'));
        }
    }
    
    /**
     * Delete the mu-plugins/milliondollarscript-cookies.php file
     * This is called by the scheduled cron job
     * 
     * @return void
     */
    public function delete_cookies_file(): void {
        $mu_plugins_dir = WPMU_PLUGIN_DIR;
        $cookies_file = $mu_plugins_dir . '/milliondollarscript-cookies.php';
        
        if (file_exists($cookies_file)) {
            // Try to delete the file
            if (@unlink($cookies_file)) {
                // Log the successful deletion
                error_log('Million Dollar Script cookies file deleted successfully');
            } else {
                // Log the failure to delete
                error_log('Failed to delete Million Dollar Script cookies file: ' . $cookies_file);
                
                // Add admin notice about manual deletion
                $notice = Language::get('Failed to automatically delete the Million Dollar Script cookies file. ');
                $notice .= Language::get('Please manually delete this file: ') . '<code>' . esc_html($cookies_file) . '</code>';
                
                Notices::add_notice($notice, 'error');
            }
        }
    }
    
    /**
     * More thorough method to ensure config_key column exists instead of 'key'
     * 
     * @return void
     */
    private function ensure_config_key_column(): void {
        global $wpdb;
        $table_name = MDS_DB_PREFIX . 'config';
        
        // Check if table exists first
        if (DatabaseStatic::table_exists($table_name)) {
            // Direct approach to check for 'key' column and rename it
            $key_column_exists = $wpdb->get_var( "SHOW COLUMNS FROM `{$table_name}` LIKE 'key'" );
            $config_key_column_exists = $wpdb->get_var( "SHOW COLUMNS FROM `{$table_name}` LIKE 'config_key'" );
            
            if ($key_column_exists && !$config_key_column_exists) {
                // First remove any existing primary key
                $primary_exists = $wpdb->get_var("SHOW KEYS FROM `{$table_name}` WHERE Key_name = 'PRIMARY'" );
                if ($primary_exists) {
                    $wpdb->query("ALTER TABLE `{$table_name}` DROP PRIMARY KEY");
                    $wpdb->flush();
                }
                
                // Rename the column from 'key' to 'config_key' and make it PRIMARY KEY
                $wpdb->query( "ALTER TABLE `{$table_name}` CHANGE `key` `config_key` VARCHAR(100) NOT NULL DEFAULT ''" );
                $wpdb->query( "ALTER TABLE `{$table_name}` ADD PRIMARY KEY (`config_key`)" );
                $wpdb->flush();
            } else if (!$key_column_exists && !$config_key_column_exists) {
                // If neither column exists, create config_key as PRIMARY KEY
                $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN `config_key` VARCHAR(100) NOT NULL DEFAULT '' FIRST" );
                $wpdb->query( "ALTER TABLE `{$table_name}` ADD PRIMARY KEY (`config_key`)" );
                $wpdb->flush();
            } else if ($config_key_column_exists) {
                // Make sure config_key is PRIMARY KEY and NOT NULL
                
                // First check if config_key allows NULL
                $column_info = $wpdb->get_row("SHOW COLUMNS FROM `{$table_name}` WHERE Field = 'config_key'");
                if ($column_info && $column_info->Null === 'YES') {
                    $wpdb->query( "ALTER TABLE `{$table_name}` MODIFY `config_key` VARCHAR(100) NOT NULL DEFAULT ''" );
                    $wpdb->flush();
                }
                
                // Then check if it's PRIMARY KEY
                $primary_exists = $wpdb->get_var("SHOW KEYS FROM `{$table_name}` WHERE Key_name = 'PRIMARY'" );
                if (!$primary_exists) {
                    $wpdb->query( "ALTER TABLE `{$table_name}` ADD PRIMARY KEY (`config_key`)" );
                    $wpdb->flush();
                }
                
                // Remove any duplicate rows or empty config_key values
                $wpdb->query("DELETE FROM `{$table_name}` WHERE `config_key` = ''");
                $wpdb->flush();
            }
        }
    }
}
