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
use MillionDollarScript\Classes\System\Logs;

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
            // Options page - pages and emails already handled
            
            // Grid tab (display, performance, and grid specific settings)
            'OUTPUT_JPEG'               => ['option' => 'output-jpeg', 'tab' => 'grid'],
            'JPEG_QUALITY'              => ['option' => 'jpeg-quality', 'tab' => 'grid'],
            'INTERLACE_SWITCH'          => ['option' => 'interlace-switch', 'tab' => 'grid'],
            'DISPLAY_PIXEL_BACKGROUND'  => ['option' => 'display-pixel-background', 'tab' => 'grid'],
            'INVERT_PIXELS'             => ['option' => 'invert-pixels', 'tab' => 'grid'],
            'STATS_DISPLAY_MODE'        => ['option' => 'stats-display-mode', 'tab' => 'grid'],
            'MDS_AGRESSIVE_CACHE'       => ['option' => 'aggressive-cache', 'tab' => 'grid'],
            'USE_AJAX'                  => ['option' => 'use-ajax', 'tab' => 'grid'],
            'MDS_RESIZE'                => ['option' => 'resize', 'tab' => 'grid'],
            'BLOCK_SELECTION_MODE'      => ['option' => 'block-selection-mode', 'tab' => 'grid'],
            
            // Popup tab settings
            'ENABLE_MOUSEOVER'          => ['option' => 'enable-mouseover', 'tab' => 'popup'],
            'TOOLTIP_TRIGGER'           => ['option' => 'tooltip-trigger', 'tab' => 'popup'],
            
            // URL & Analytics tab settings
            'ENABLE_CLOAKING'           => ['option' => 'enable-cloaking', 'tab' => 'url-analytics'],
            'VALIDATE_LINK'             => ['option' => 'validate-link', 'tab' => 'url-analytics'],
            'ADVANCED_CLICK_COUNT'      => ['option' => 'advanced-click-count', 'tab' => 'url-analytics'],
            'ADVANCED_VIEW_COUNT'       => ['option' => 'advanced-view-count', 'tab' => 'url-analytics'],
            'REDIRECT_SWITCH'           => ['option' => 'redirect-switch', 'tab' => 'url-analytics'],
            'REDIRECT_URL'              => ['option' => 'redirect-url', 'tab' => 'url-analytics'],
            
            // User Interface tab settings
            'MDS_PIXEL_TEMPLATE'        => ['option' => 'mds-pixel-template', 'tab' => 'user-interface'],
            'EXCLUDE_FROM_SEARCH'       => ['option' => 'exclude-from-search', 'tab' => 'user-interface'],
            'DYNAMIC_ID'                => ['option' => 'dynamic-id', 'tab' => 'user-interface'],
            
            // Checkout & Orders tab settings
            'CHECKOUT_URL'              => ['option' => 'checkout-url', 'tab' => 'checkout-orders'],
            'THANK_YOU_PAGE'            => ['option' => 'thank-you-page', 'tab' => 'checkout-orders'],
            'CONFIRM_ORDERS'            => ['option' => 'confirm-orders', 'tab' => 'checkout-orders'],
            
            // Order Timing tab settings
            'MINUTES_RENEW'             => ['option' => 'minutes-renew', 'tab' => 'order-timing'],
            'MINUTES_CONFIRMED'         => ['option' => 'minutes-confirmed', 'tab' => 'order-timing'],
            'MINUTES_UNCONFIRMED'       => ['option' => 'minutes-unconfirmed', 'tab' => 'order-timing'],
            'MINUTES_CANCEL'            => ['option' => 'minutes-cancel', 'tab' => 'order-timing'],
            
            // Email page toggles
            'EMAIL_USER_ORDER_CONFIRMED'  => ['option' => 'email-user-order-confirmed', 'tab' => 'email'],
            'EMAIL_ADMIN_ORDER_CONFIRMED' => ['option' => 'email-admin-order-confirmed', 'tab' => 'email'],
            'EMAIL_USER_ORDER_COMPLETED'  => ['option' => 'email-user-order-completed', 'tab' => 'email'],
            'EMAIL_ADMIN_ORDER_COMPLETED' => ['option' => 'email-admin-order-completed', 'tab' => 'email'],
            'EMAIL_USER_ORDER_PENDED'     => ['option' => 'email-user-order-pended', 'tab' => 'email'],
            'EMAIL_ADMIN_ORDER_PENDED'    => ['option' => 'email-admin-order-pended', 'tab' => 'email'],
            'EMAIL_USER_ORDER_EXPIRED'    => ['option' => 'email-user-order-expired', 'tab' => 'email'],
            'EMAIL_ADMIN_ORDER_EXPIRED'   => ['option' => 'email-admin-order-expired', 'tab' => 'email'],
            'EMAIL_USER_ORDER_DENIED'     => ['option' => 'email-user-order-denied', 'tab' => 'email'],
            'EMAIL_ADMIN_ORDER_DENIED'    => ['option' => 'email-admin-order-denied', 'tab' => 'email'],
            'EMAIL_ADMIN_PUBLISH_NOTIFY'  => ['option' => 'email-admin-publish-notify', 'tab' => 'email'],
            'EMAIL_USER_EXPIRE_WARNING'   => ['option' => 'email-user-renewal-reminder', 'tab' => 'email'],
            
            // Email Settings tab
            'EMAILS_DAYS_KEEP'          => ['option' => 'emails-days-keep', 'tab' => 'email-settings'],
            'EMAILS_PER_BATCH'           => ['option' => 'emails-per-batch', 'tab' => 'email-settings'],
            'EMAILS_MAX_RETRY'           => ['option' => 'emails-max-retry', 'tab' => 'email-settings'],
            'EMAILS_ERROR_WAIT'          => ['option' => 'emails-error-wait', 'tab' => 'email-settings'],
            
            // System-level stored with update_option
            'dbver'     => ['option' => MDS_PREFIX . 'db-version', 'tab' => 'system', 'wp_option' => true],
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
                    // Store system options directly
                    Options::update_option($map_data['option'], $value);
                    continue;
                }
                
                // For options to be stored in Carbon Fields
                // Convert value types as needed
                $converted_value = $this->convert_value_type($key, $value);
                
                // Store via Carbon Fields API
                Options::update_option($map_data['option'], $converted_value);
            } else {
                // For any unmapped config values, store them in a legacy section
                // to ensure no configuration is lost
                
                // Create a sanitized option name from the config key
                $option_name = sanitize_key(strtolower(str_replace('_', '-', $key)));
                
                // Store legacy values via Carbon Fields
                Options::update_option('legacy-' . $option_name, $value);
                
                // Log migration of unmapped config for reference
                Logs::log("MDS Migration: Unmapped config key '{$key}' stored as legacy option");
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
        // Boolean config keys (YES/NO toggles)
        $boolean_keys = [
            'INTERLACE_SWITCH', 'DISPLAY_PIXEL_BACKGROUND', 'ENABLE_MOUSEOVER',
            'TOOLTIP_TRIGGER', 'ENABLE_CLOAKING', 'VALIDATE_LINK',
            'ADVANCED_CLICK_COUNT', 'ADVANCED_VIEW_COUNT', 'INVERT_PIXELS',
            'MDS_AGRESSIVE_CACHE', 'MDS_RESIZE', 'BLOCK_SELECTION_MODE',
            'REDIRECT_SWITCH',
            'EMAIL_USER_ORDER_CONFIRMED', 'EMAIL_ADMIN_ORDER_CONFIRMED',
            'EMAIL_USER_ORDER_COMPLETED', 'EMAIL_ADMIN_ORDER_COMPLETED',
            'EMAIL_USER_ORDER_PENDED', 'EMAIL_ADMIN_ORDER_PENDED',
            'EMAIL_USER_ORDER_EXPIRED', 'EMAIL_ADMIN_ORDER_EXPIRED',
            'EMAIL_USER_ORDER_DENIED', 'EMAIL_ADMIN_ORDER_DENIED',
            'EMAIL_ADMIN_PUBLISH_NOTIFY', 'EMAIL_USER_EXPIRE_WARNING'
        ];

        // Integer config keys
        $integer_keys = [
            'JPEG_QUALITY', 'MINUTES_RENEW', 'MINUTES_CONFIRMED',
            'MINUTES_UNCONFIRMED', 'MINUTES_CANCEL', 'EMAILS_DAYS_KEEP',
            'EMAILS_PER_BATCH', 'EMAILS_MAX_RETRY', 'EMAILS_ERROR_WAIT'
        ];
        
        // Convert based on key type
        if (in_array($key, $boolean_keys)) {
            // Carbon Fields stores checked checkboxes as 'yes'
            return in_array(strtoupper($value), ['YES', 'Y', '1', 'TRUE']) ? 'yes' : '';
        } elseif (in_array($key, $integer_keys)) {
            return intval($value);
        }
        
        // Default is to return as is (string)
        return $value;
    }
}
