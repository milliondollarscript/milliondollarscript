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

use MillionDollarScript\Classes\System\Logs;

defined( 'ABSPATH' ) or exit;

/** @noinspection PhpUnused */

class _2_6_0 {

    /**
     * Upgrade to version 2.6.0
     *
     * This upgrade creates the `wp_mds_extension_licenses` table for the per-extension licensing system.
     *
     * @param string $version Current database version
     *
     * @return void
     */
    public function upgrade( $version ): void {
        if ( version_compare( $version, '2.6.0', '<' ) ) {

            Logs::log( 'MDS Upgrade _2_6_0 starting - Creating extension licenses table.' );

            try {
                $this->create_extension_licenses_table();
                Logs::log( 'MDS Upgrade _2_6_0 completed successfully.' );
            } catch ( \Exception $e ) {
                Logs::log( 'MDS Upgrade _2_6_0 failed: ' . $e->getMessage() );
                throw $e;
            }
        }
    }

    /**
     * Create the extension licenses table.
     *
     * @return void
     * @throws \Exception
     */
    private function create_extension_licenses_table(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mds_extension_licenses';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            plugin_name VARCHAR(255) NOT NULL,
            license_key VARCHAR(128) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            seats INT UNSIGNED NOT NULL DEFAULT 1,
            email VARCHAR(255) NOT NULL,
            external_id VARCHAR(128) NULL,
            customer_id VARCHAR(128) NULL,
            order_id VARCHAR(128) NULL,
            expires_at DATETIME NULL,
            metadata LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY license_key (license_key),
            UNIQUE KEY external_id (external_id),
            KEY plugin_name (plugin_name)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        if ( $wpdb->last_error ) {
            throw new \Exception( "Error creating table $table_name: " . $wpdb->last_error );
        }
    }
}