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

defined( 'ABSPATH' ) or exit;

/** @noinspection PhpUnused */

class _2_5_14_0 {

    public function upgrade( $version ): void {
        if ( version_compare( $version, '2.5.14.0', '<' ) ) {
            global $wpdb;

            // Add recommended indexes to improve selection performance
            $table_blocks = MDS_DB_PREFIX . 'blocks';

            if ( DatabaseStatic::table_exists( $table_blocks ) ) {
                // idx_user_banner_status (user_id, banner_id, status)
                $has_idx1 = (bool) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(1) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = %s AND index_name = 'idx_user_banner_status'",
                    $table_blocks
                ) );
                if ( ! $has_idx1 ) {
                    $wpdb->query( "CREATE INDEX idx_user_banner_status ON `{$table_blocks}` (user_id, banner_id, status)" );
                }

                // idx_order_banner (order_id, banner_id)
                $has_idx2 = (bool) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(1) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = %s AND index_name = 'idx_order_banner'",
                    $table_blocks
                ) );
                if ( ! $has_idx2 ) {
                    $wpdb->query( "CREATE INDEX idx_order_banner ON `{$table_blocks}` (order_id, banner_id)" );
                }
            }
        }
    }
}
