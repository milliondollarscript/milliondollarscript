<?php
/*
 * @package       mds
 * @copyright     (C) Copyright 2022 Ryan Rhode, All rights reserved.
 * @author        Ryan Rhode, ryan@milliondollarscript.com
 * @version       2022-06-09 11:21:07 EDT
 * @license       This program is free software; you can redistribute it and/or modify
 *        it under the terms of the GNU General Public License as published by
 *        the Free Software Foundation; either version 3 of the License, or
 *        (at your option) any later version.
 *
 *        This program is distributed in the hope that it will be useful,
 *        but WITHOUT ANY WARRANTY; without even the implied warranty of
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *        GNU General Public License for more details.
 *
 *        You should have received a copy of the GNU General Public License along
 *        with this program;  If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 *
 *  * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *        Million Dollar Script
 *        A pixel script for selling pixels on your website.
 *
 *        For instructions see README.txt
 *
 *        Visit our website for FAQs, documentation, a list team members,
 *        to post any bugs or feature requests, and a community forum:
 *        https://milliondollarscript.com/
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
	public static function add_minute( $schedules ) {
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
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( 'milliondollarscript_cron_minute' ) ) {
			wp_schedule_event( time(), 'everyminute', 'milliondollarscript_cron_minute' );
		}
	}

	/**
	 * Clears the scheduled cron.
	 *
	 * @return void
	 */
	public static function clear_cron() {
		wp_clear_scheduled_hook( 'milliondollarscript_cron_minute' );
	}

	/**
	 * Tasks that run every minute.
	 *
	 * @return void
	 */
	public static function minute() {
		// Expire orders
		if ( ! defined( 'NO_HOUSE_KEEP' ) || NO_HOUSE_KEEP != 'YES' ) {
			$mdspath = Options::get_mds_path();
			require_once $mdspath . "include/init.php";
			\expire_orders();
		}
	}
}