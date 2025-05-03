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

use MillionDollarScript\Classes\Data\DatabaseStatic;
use MillionDollarScript\Classes\Data\Options;

defined( 'ABSPATH' ) or exit;

/** @noinspection PhpUnused */

class _2_5_12_0 {

    /**
     * Upgrade the database schema to version 2.5.12.0
     * 
     * This upgrade migrates configuration settings from the wp_mds_config table
     * to WordPress options using Carbon Fields.
     *
     * @param string $version Current database version
     * 
     * @return void
     */
    public function upgrade( $version ): void {
        if ( version_compare( $version, '2.5.12.0', '<' ) ) {
            global $wpdb;
            
            // Check if the config table exists
            $table_name = MDS_DB_PREFIX . 'config';
            
            if (DatabaseStatic::table_exists($table_name)) {
                // Get all config values from the table
                $results = $wpdb->get_results("SELECT `config_key`, `val` FROM `{$table_name}`", ARRAY_A);
                
                if ($results && count($results) > 0) {
                    $this->migrate_config_to_options($results);
                }
            }
        }
    }
    
    /**
     * Migrate configuration values from the table to WordPress options
     * 
     * @param array $config_values Array of config values from the table
     * 
     * @return void
     */
    private function migrate_config_to_options(array $config_values): void {
        // Map of config_key values to their new option names and tabs
        $config_map = [
            // General configuration
            'URL_ENABLED' => ['option' => 'url-enabled', 'tab' => 'general'],
            'ENABLE_MOUSEOVER' => ['option' => 'enable-mouseover', 'tab' => 'general'],
            'ENABLE_GRID_HOME_PAGE' => ['option' => 'enable-grid-home-page', 'tab' => 'general'],
            'CURRENCY' => ['option' => 'currency', 'tab' => 'general'],
            'CURRENCY_SYMBOL' => ['option' => 'currency-symbol', 'tab' => 'general'],
            'CURRENCY_POSITION' => ['option' => 'currency-position', 'tab' => 'general'],
            'DECIMAL_POINT' => ['option' => 'decimal-point', 'tab' => 'general'],
            'THOUSANDS_SEP' => ['option' => 'thousands-separator', 'tab' => 'general'],
            'DECIMAL_PLACES' => ['option' => 'decimal-places', 'tab' => 'general'],
            'DATE_FORMAT' => ['option' => 'date-format', 'tab' => 'general'],
            'TIME_FORMAT' => ['option' => 'time-format', 'tab' => 'general'],
            'GMT_DIF' => ['option' => 'gmt-difference', 'tab' => 'general'],
            
            // Display options
            'ENABLE_CLOAKING' => ['option' => 'enable-cloaking', 'tab' => 'display'],
            'INVERT_PIXELS' => ['option' => 'invert-pixels', 'tab' => 'display'],
            'ADVANCED_CLICK_COUNT' => ['option' => 'advanced-click-count', 'tab' => 'display'],
            'LINK_TARGET' => ['option' => 'link-target', 'tab' => 'display'],
            'NO_FOLLOW' => ['option' => 'no-follow', 'tab' => 'display'],
            
            // Email configurations
            'EMAIL_ADMIN_PAYMENT_NOTIFY' => ['option' => 'email-admin-payment-notify', 'tab' => 'email'],
            'EMAIL_USER_ORDER_COMPLETED' => ['option' => 'email-user-order-completed', 'tab' => 'email'],
            'EMAIL_USER_ORDER_PENDING' => ['option' => 'email-user-order-pending', 'tab' => 'email'],
            'EMAIL_ADMIN_ORDER_COMPLETED' => ['option' => 'email-admin-order-completed', 'tab' => 'email'],
            'EMAIL_ADMIN_ORDER_PENDING' => ['option' => 'email-admin-order-pending', 'tab' => 'email'],
            
            // System settings - keep as separate WP options not in Carbon Fields
            'dbver' => ['option' => MDS_PREFIX . 'db-version', 'tab' => 'system', 'wp_option' => true],
            'BUILD_DATE' => ['option' => MDS_PREFIX . 'build-date', 'tab' => 'system', 'wp_option' => true],
        ];
        
        // Process each config value
        foreach ($config_values as $config) {
            $key = $config['config_key'];
            $value = $config['val'];
            
            // Check if this config key is in our map
            if (isset($config_map[$key])) {
                $map_data = $config_map[$key];
                
                // For system-level options that shouldn't be in Carbon Fields
                if (isset($map_data['wp_option']) && $map_data['wp_option']) {
                    update_option($map_data['option'], $value);
                    continue;
                }
                
                // For options to be stored in Carbon Fields
                // Convert value types as needed
                $converted_value = $this->convert_value_type($key, $value);
                
                // Use WordPress update_option with Carbon Fields naming convention
                update_option('_' . MDS_PREFIX . $map_data['option'], $converted_value);
            } else {
                // For any unmapped config values, store them in a legacy section
                // to ensure no configuration is lost
                
                // Create a sanitized option name from the config key
                $option_name = sanitize_key(strtolower(str_replace('_', '-', $key)));
                
                // Store it with a legacy prefix for later identification
                update_option('_' . MDS_PREFIX . 'legacy-' . $option_name, $value);
                
                // Log migration of unmapped config for reference
                error_log("MDS Migration: Unmapped config key '{$key}' stored as legacy option");
            }
        }
    }
    
    /**
     * Convert values to appropriate types based on the config key
     * 
     * @param string $key The config key
     * @param mixed $value The value to convert
     * 
     * @return mixed Converted value
     */
    private function convert_value_type(string $key, $value) {
        // Boolean values
        $boolean_keys = [
            'URL_ENABLED', 'ENABLE_MOUSEOVER', 'ENABLE_GRID_HOME_PAGE', 
            'ENABLE_CLOAKING', 'INVERT_PIXELS', 'ADVANCED_CLICK_COUNT',
            'NO_FOLLOW', 'EMAIL_ADMIN_PAYMENT_NOTIFY', 'EMAIL_USER_ORDER_COMPLETED',
            'EMAIL_USER_ORDER_PENDING', 'EMAIL_ADMIN_ORDER_COMPLETED', 
            'EMAIL_ADMIN_ORDER_PENDING'
        ];
        
        // Integer values
        $integer_keys = [
            'DECIMAL_PLACES', 'GMT_DIF'
        ];
        
        // Convert based on key type
        if (in_array($key, $boolean_keys)) {
            return in_array(strtoupper($value), ['YES', 'Y', '1', 'TRUE']);
        } elseif (in_array($key, $integer_keys)) {
            return intval($value);
        }
        
        // Default is to return as is (string)
        return $value;
    }
}
