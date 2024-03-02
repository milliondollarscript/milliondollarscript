<?php

/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2024, Ryan Rhode
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

defined( 'ABSPATH' ) or exit;

/** @noinspection PhpUnused */

class _2_5_10 {

	public function upgrade( $version ): void {
		if ( version_compare( $version, '2.5.10', '<' ) ) {

			// Delete some removed config options.
			Options::delete( 'DATE_FORMAT' );
			Options::delete( 'TIME_FORMAT' );
			Options::delete( 'GMT_DIF' );
			Options::delete( 'DATE_INPUT_SEQ' );

			// Convert all dates and times to use the WP timezone setting instead of GMT.
			global $wpdb;

			// banners table.
			$table_name = MDS_DB_PREFIX . 'banners';

			$results = $wpdb->get_results( "SELECT * FROM {$table_name}", ARRAY_A );

			foreach ( $results as $row ) {
				// Convert the GMT date/time fields to WordPress time
				$publish_date = get_date_from_gmt( $row['publish_date'] );
				$date_updated = get_date_from_gmt( $row['date_updated'] );

				// Convert the Unix timestamp to a date string, adjust it to WordPress time, then convert back to a Unix timestamp
				$time_stamp = strtotime( get_date_from_gmt( date( 'Y-m-d H:i:s', $row['time_stamp'] ) ) );

				// Update the record in the database
				$wpdb->update(
					$table_name,
					array(
						'publish_date' => $publish_date,
						'date_updated' => $date_updated,
						'time_stamp'   => $time_stamp,
					),
					array( 'banner_id' => $row['banner_id'] ),
					array(
						'%s',    // publish_date
						'%s',    // date_updated
						'%d'     // time_stamp
					),
					array( '%d' )
				);
			}

			// clicks table.
			$table_name = MDS_DB_PREFIX . 'clicks';

			$results = $wpdb->get_results( "SELECT * FROM {$table_name}", ARRAY_A );

			foreach ( $results as $row ) {
				// Convert the GMT date field to WordPress time
				$date = get_date_from_gmt( $row['date'] );

				// Update the record in the database
				$wpdb->update(
					$table_name,
					array(
						'date' => $date,
					),
					array(
						'banner_id' => $row['banner_id'],
						'block_id'  => $row['block_id'],
						'date'      => $row['date']
					),
					array(
						'%s',    // date
					),
					array(
						'%d',    // banner_id
						'%d',    // block_id
						'%s'     // date
					)
				);
			}

			// mail_queue table.
			$table_name = MDS_DB_PREFIX . 'mail_queue';

			$results = $wpdb->get_results( "SELECT * FROM {$table_name}", ARRAY_A );

			foreach ( $results as $row ) {
				// Convert the GMT date/time fields to WordPress time
				$mail_date  = get_date_from_gmt( $row['mail_date'] );
				$date_stamp = get_date_from_gmt( $row['date_stamp'] );

				// Update the record in the database
				$wpdb->update(
					$table_name,
					array(
						'mail_date'  => $mail_date,
						'date_stamp' => $date_stamp,
					),
					array( 'mail_id' => $row['mail_id'] ),
					array(
						'%s',    // mail_date
						'%s'     // date_stamp
					),
					array( '%d' )
				);
			}

			// orders table.
			$table_name = MDS_DB_PREFIX . 'orders';

			$results = $wpdb->get_results( "SELECT * FROM {$table_name}", ARRAY_A );

			foreach ( $results as $row ) {
				// Convert the GMT date/time fields to WordPress time
				$order_date     = get_date_from_gmt( $row['order_date'] );
				$date_published = get_date_from_gmt( $row['date_published'] );
				$date_stamp     = get_date_from_gmt( $row['date_stamp'] );

				// Update the record in the database
				$wpdb->update(
					$table_name,
					array(
						'order_date'     => $order_date,
						'date_published' => $date_published,
						'date_stamp'     => $date_stamp,
					),
					array( 'order_id' => $row['order_id'] ),
					array(
						'%s',    // order_date
						'%s',    // date_published
						'%s'     // date_stamp
					),
					array( '%d' )
				);
			}

			// transactions table.
			$table_name = MDS_DB_PREFIX . 'transactions';

			$results = $wpdb->get_results( "SELECT * FROM {$table_name}", ARRAY_A );

			foreach ( $results as $row ) {
				// Convert the GMT date field to WordPress time
				$date = get_date_from_gmt( $row['date'] );

				// Update the record in the database
				$wpdb->update(
					$table_name,
					array(
						'date' => $date,
					),
					array( 'transaction_id' => $row['transaction_id'] ),
					array(
						'%s',    // date
					),
					array( '%d' )
				);
			}

			// views table.
			$table_name = MDS_DB_PREFIX . 'views';

			$results = $wpdb->get_results( "SELECT * FROM {$table_name}", ARRAY_A );

			foreach ( $results as $row ) {
				// Convert the GMT date field to WordPress time
				$date = get_date_from_gmt( $row['date'] );

				// Update the record in the database
				$wpdb->update(
					$table_name,
					array(
						'date' => $date,
					),
					array(
						'banner_id' => $row['banner_id'],
						'block_id'  => $row['block_id'],
						'date'      => $row['date']
					),
					array(
						'%s',    // date
					),
					array(
						'%d',    // banner_id
						'%d',    // block_id
						'%s'     // date
					)
				);
			}
		}
	}
}
