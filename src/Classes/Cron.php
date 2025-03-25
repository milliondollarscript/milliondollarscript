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

namespace MillionDollarScript\Classes;

defined( 'ABSPATH' ) or exit;

/**
 * Stub for Cron class.
 *
 * Necessary for upgrades to be able to run without errors from previous version due to class refactoring.
 *
 */
class Cron {
	public static function add_minute( $schedules ): mixed {
		return System\Cron::add_minute( $schedules );
	}

	public static function schedule_cron(): void {
		System\Cron::schedule_cron();
	}

	public static function clear_cron(): void {
		System\Cron::clear_cron();
	}

	public static function minute(): void {
		System\Cron::minute();
	}

	public static function clean_temp_files(): void {
		System\Cron::clean_temp_files();
	}
}