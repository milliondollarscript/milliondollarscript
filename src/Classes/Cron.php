<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.5
 * @author      Ryan Rhode
 * @copyright   (C) 2023, Ryan Rhode
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

namespace MillionDollarScript\Classes;

defined( 'ABSPATH' ) or exit;

class Cron {

	/**
	 * Add everyminute cron schedule.
	 *
	 * @param $schedules
	 *
	 * @return mixed
	 */
	public static function add_minute( $schedules ): mixed {
		$schedules['everyminute'] = array(
			'interval' => 60,
			'display'  => __( 'Once Every Minute' )
		);

		return $schedules;
	}

	/**
	 * Schedule MDS crons.
	 *
	 * @return void
	 */
	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( 'milliondollarscript_cron_minute' ) ) {
			wp_schedule_event( time(), 'everyminute', 'milliondollarscript_cron_minute' );
		}
	}

	/**
	 * Clears the scheduled cron.
	 *
	 * @return void
	 */
	public static function clear_cron(): void {
		wp_clear_scheduled_hook( 'milliondollarscript_cron_minute' );
	}

	/**
	 * Tasks that run every minute.
	 *
	 * @return void
	 */
	public static function minute(): void {
		// Expire orders
		if ( Options::get_option( 'expire-orders' ) == 'yes' ) {
			\expire_orders();
		}
	}
}