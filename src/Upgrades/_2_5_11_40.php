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
use MillionDollarScript\Classes\System\Filesystem;

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
     * Delete the mu-plugins/milliondollarscript-cookies.php file using WordPress filesystem API
     * This is done as the last step in the upgrade process and may log users out
     * 
     * @return void
     */
    private function schedule_cookies_file_deletion(): void {
        /**
         * Determine file path for the cookies file that needs to be deleted
         */
        $mu_plugins_dir = WPMU_PLUGIN_DIR;
        $cookies_file = $mu_plugins_dir . '/milliondollarscript-cookies.php';
        
        /**
         * Add admin notice to warn users about potential logout
         */
        $notice = Language::get('The Million Dollar Script cookies file has been removed or disabled. ');
        $notice .= Language::get('You may be logged out when the page next refreshes. ');
        $notice .= Language::get('This is necessary for proper plugin functionality.');
        
        Notices::add_notice($notice, 'warning');
        
        /**
         * Log deletion attempt
         */
        error_log('Attempting to delete cookies file via WordPress filesystem: ' . $cookies_file);
        
        /**
         * Use WordPress filesystem API to delete the file
         * This handles all file permission issues automatically
         */
        $fs = new Filesystem(admin_url());
        $deleted = $fs->delete_file($cookies_file);
        
        /**
         * Log the result and provide admin notice if needed
         */
        if ($deleted) {
            error_log('Successfully deleted or verified absence of cookies file via WordPress filesystem');
        } else {
            error_log('CRITICAL: Failed to delete cookies file via WordPress filesystem: ' . $cookies_file);
            
            /**
             * Fallback strategy: Try to empty the file if deletion failed
             */
            $success = false;
            
            /**
             * Get filesystem access before attempting file operations
             */
            if ($fs->get_filesystem()) {
                global $wp_filesystem;
                
                /**
                 * If file exists, try to empty it with disabled PHP code
                 */
                if ($wp_filesystem->exists($cookies_file)) {
                    if ($wp_filesystem->put_contents($cookies_file, '<?php // File disabled by MDS Upgrade', FS_CHMOD_FILE)) {
                        error_log('Successfully disabled cookies file by emptying it');
                        $success = true;
                    }
                }
            }
            
            /**
             * If all attempts failed, notify administrator for manual action
             */
            if (!$success) {
                $manual_notice = Language::get('Failed to automatically delete the required cookies file. ');
                $manual_notice .= Language::get('Please manually delete this file to avoid login issues: ');
                $manual_notice .= '<code>' . esc_html($cookies_file) . '</code>';
                
                Notices::add_notice($manual_notice, 'error');
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
            $key_column_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$table_name}` LIKE 'key'");
            $config_key_column_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$table_name}` LIKE 'config_key'");
            
            if ($key_column_exists && !$config_key_column_exists) {
                // Handle the case where 'key' exists but 'config_key' doesn't
                $this->convert_key_to_config_key($table_name);
            } else if (!$key_column_exists && !$config_key_column_exists) {
                // If neither column exists, create config_key as PRIMARY KEY
                $this->create_config_key_column($table_name);
            } else if ($config_key_column_exists) {
                // If config_key already exists, fix any issues
                $this->fix_config_key_issues($table_name);
            }
        }
    }
    
    /**
     * Convert 'key' column to 'config_key' and make it PRIMARY KEY
     * 
     * @param string $table_name The table name
     * @return void
     */
    private function convert_key_to_config_key(string $table_name): void {
        global $wpdb;
        
        try {
            // 1. First, backup important config values
            $important_keys = ['dbver', 'BUILD_DATE', 'VERSION_INFO'];
            $backup_values = [];
            
            foreach ($important_keys as $key) {
                $value = $wpdb->get_var($wpdb->prepare("SELECT `val` FROM `{$table_name}` WHERE `key` = %s", $key));
                if ($value !== null) {
                    $backup_values[$key] = $value;
                }
            }
            
            // 2. Drop PRIMARY KEY if it exists
            $primary_exists = $wpdb->get_var("SHOW KEYS FROM `{$table_name}` WHERE Key_name = 'PRIMARY'");
            if ($primary_exists) {
                $wpdb->query("ALTER TABLE `{$table_name}` DROP PRIMARY KEY");
                $wpdb->flush();
            }
            
            // 3. Rename the column from 'key' to 'config_key'
            $wpdb->query("ALTER TABLE `{$table_name}` CHANGE `key` `config_key` VARCHAR(100) NOT NULL DEFAULT ''");
            $wpdb->flush();
            
            // 4. Delete all empty config_key values
            $wpdb->query("DELETE FROM `{$table_name}` WHERE `config_key` = ''");
            $wpdb->flush();
            
            // 5. Add PRIMARY KEY constraint
            $wpdb->query("ALTER TABLE `{$table_name}` ADD PRIMARY KEY (`config_key`)");
            $wpdb->flush();
        } catch (\Exception $e) {
            error_log('Error converting key to config_key: ' . $e->getMessage());
            
            // If there was an error, try more drastic measures - recreate table
            $this->rebuild_config_table($table_name, $backup_values ?? []);
        }
    }
    
    /**
     * Create the config_key column as PRIMARY KEY
     * 
     * @param string $table_name The table name
     * @return void
     */
    private function create_config_key_column(string $table_name): void {
        global $wpdb;
        
        try {
            // Add the config_key column
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `config_key` VARCHAR(100) NOT NULL DEFAULT '' FIRST");
            $wpdb->flush();
            
            // Add PRIMARY KEY constraint
            $wpdb->query("ALTER TABLE `{$table_name}` ADD PRIMARY KEY (`config_key`)");
            $wpdb->flush();
        } catch (\Exception $e) {
            error_log('Error creating config_key column: ' . $e->getMessage());
        }
    }
    
    /**
     * Fix issues with the config_key column
     * 
     * @param string $table_name The table name
     * @return void
     */
    private function fix_config_key_issues(string $table_name): void {
        global $wpdb;
        
        // 1. First, backup important config values
        $important_keys = ['dbver', 'BUILD_DATE', 'VERSION_INFO'];
        $backup_values = [];
        
        foreach ($important_keys as $key) {
            $value = $wpdb->get_var($wpdb->prepare("SELECT `val` FROM `{$table_name}` WHERE `config_key` = %s", $key));
            if ($value !== null) {
                $backup_values[$key] = $value;
            }
        }
        
        try {
            // 2. Make sure config_key is NOT NULL
            $column_info = $wpdb->get_row("SHOW COLUMNS FROM `{$table_name}` WHERE Field = 'config_key'");
            if ($column_info && $column_info->Null === 'YES') {
                $wpdb->query("ALTER TABLE `{$table_name}` MODIFY `config_key` VARCHAR(100) NOT NULL DEFAULT ''");
                $wpdb->flush();
            }
            
            // 3. Delete all empty config_key values
            $wpdb->query("DELETE FROM `{$table_name}` WHERE `config_key` = ''");
            $wpdb->flush();
            
            // 4. Fix PRIMARY KEY constraint if needed
            $primary_exists = $wpdb->get_var("SHOW KEYS FROM `{$table_name}` WHERE Key_name = 'PRIMARY'");
            if (!$primary_exists) {
                $wpdb->query("ALTER TABLE `{$table_name}` ADD PRIMARY KEY (`config_key`)");
                $wpdb->flush();
            }
            
            // 5. Check if we still have issues with empty config_key values
            $empty_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}` WHERE `config_key` = ''");
            if ($empty_count > 0) {
                // Drop PRIMARY KEY to allow updating the empty values
                $primary_exists = $wpdb->get_var("SHOW KEYS FROM `{$table_name}` WHERE Key_name = 'PRIMARY'");
                if ($primary_exists) {
                    $wpdb->query("ALTER TABLE `{$table_name}` DROP PRIMARY KEY");
                    $wpdb->flush();
                }
                
                // Try to assign unique names to empty config_key rows
                $empty_rows = $wpdb->get_results("SELECT * FROM `{$table_name}` WHERE `config_key` = ''");
                if (is_array($empty_rows)) {
                    $counter = 1;
                    foreach ($empty_rows as $row) {
                        $wpdb->update(
                            $table_name,
                            ['config_key' => 'temp_key_' . $counter],
                            ['config_key' => ''],
                            ['%s'],
                            ['%s']
                        );
                        $counter++;
                    }
                }
                
                // Add PRIMARY KEY back
                $wpdb->query("ALTER TABLE `{$table_name}` ADD PRIMARY KEY (`config_key`)");
                $wpdb->flush();
                
                // If we still have issues, try rebuilding the table
                $empty_count_after = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}` WHERE `config_key` = ''");
                if ($empty_count_after > 0) {
                    $this->rebuild_config_table($table_name, $backup_values);
                }
            }
        } catch (\Exception $e) {
            error_log('Error fixing config_key issues: ' . $e->getMessage());
            // Rebuild the table as a last resort
            $this->rebuild_config_table($table_name, $backup_values);
        }
    }
    
    /**
     * Completely rebuild the config table with correct structure
     * 
     * @param string $table_name The table name
     * @param array $backup_values Important values to preserve
     * @return void
     */
    private function rebuild_config_table(string $table_name, array $backup_values): void {
        global $wpdb;
        
        try {
            // Create temporary table with correct structure
            $temp_table = $table_name . '_temp';
            $wpdb->query("CREATE TABLE IF NOT EXISTS `{$temp_table}` (
                `config_key` VARCHAR(100) NOT NULL,
                `val` TEXT NOT NULL,
                PRIMARY KEY (`config_key`)
            )");
            $wpdb->flush();
            
            // Try to copy data from original table, excluding empty keys
            $original_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
            if ($original_exists) {
                $config_key_field = $wpdb->get_var("SHOW COLUMNS FROM `{$table_name}` LIKE 'config_key'");
                $key_field = $wpdb->get_var("SHOW COLUMNS FROM `{$table_name}` LIKE 'key'");
                
                if ($config_key_field) {
                    // Copy using config_key if it exists
                    $wpdb->query("INSERT IGNORE INTO `{$temp_table}` 
                        SELECT `config_key`, `val` FROM `{$table_name}` 
                        WHERE `config_key` != ''");
                } else if ($key_field) {
                    // Copy using key if config_key doesn't exist
                    $wpdb->query("INSERT IGNORE INTO `{$temp_table}` 
                        SELECT `key` AS `config_key`, `val` FROM `{$table_name}` 
                        WHERE `key` != ''");
                }
            }
            
            // Drop original table
            $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");
            
            // Rename temp table to original
            $wpdb->query("RENAME TABLE `{$temp_table}` TO `{$table_name}`");
            
            // Restore important values if they were lost
            foreach ($backup_values as $key => $value) {
                $exists = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `{$table_name}` WHERE `config_key` = %s", 
                    $key
                ));
                
                if ($exists == 0) {
                    $wpdb->insert(
                        $table_name,
                        [
                            'config_key' => $key,
                            'val' => $value
                        ],
                        ['%s', '%s']
                    );
                }
            }
            
            error_log('Successfully rebuilt config table with correct structure');
        } catch (\Exception $rebuild_error) {
            error_log('Failed to rebuild config table: ' . $rebuild_error->getMessage());
        }
    }
}
