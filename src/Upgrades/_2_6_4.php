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

use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\System\Logs;

defined( 'ABSPATH' ) or exit;

/** @noinspection PhpUnused */

class _2_6_4 {

    /**
     * Upgrade to version 2.6.4
     *
     * This upgrade migrates the extension server URL from localhost/development URLs
     * to the production URL (https://milliondollarscript.com) for existing installations.
     *
     * @param string $version Current database version
     *
     * @return void
     */
    public function upgrade( $version ): void {
        if ( version_compare( $version, '2.6.4', '<' ) ) {

            Logs::log( 'MDS Upgrade _2_6_4 starting - Migrating extension server URL.' );

            try {
                $this->migrate_extension_server_url();
                Logs::log( 'MDS Upgrade _2_6_4 completed successfully.' );
            } catch ( \Exception $e ) {
                Logs::log( 'MDS Upgrade _2_6_4 failed: ' . $e->getMessage() );
                // Don't throw - this is non-critical, just log the error
            }
        }
    }

    /**
     * Migrate extension server URL from localhost/development to production.
     *
     * @return void
     */
    private function migrate_extension_server_url(): void {
        $current_url = Options::get_option( 'extension_server_url', '' );

        // List of development URLs to migrate away from
        $dev_urls = [
            'http://localhost:3030',
            'http://localhost:3000',
            'http://localhost:8080',
            'http://127.0.0.1:3030',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:8080',
            'http://extension-server-go:3030',
            'http://extension-server-go:8080',
            'https://extensions.milliondollarscript.com',
        ];

        // Check if current URL is a development URL or empty
        if ( empty( $current_url ) || in_array( $current_url, $dev_urls, true ) ) {
            carbon_set_theme_option( MDS_PREFIX . 'extension_server_url', 'https://milliondollarscript.com' );
            Logs::log( "Extension server URL migrated from '{$current_url}' to 'https://milliondollarscript.com'" );
        } else {
            Logs::log( "Extension server URL not migrated - already set to custom value: '{$current_url}'" );
        }
    }
}
