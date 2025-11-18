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

namespace MillionDollarScript\Classes\System;

use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\System\Filesystem;
use MillionDollarScript\Classes\Data\Config;

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

		if ( ! wp_next_scheduled( 'milliondollarscript_clean_temp_files' ) ) {
			wp_schedule_event( time(), 'hourly', 'milliondollarscript_clean_temp_files' );
		}

		// Add new daily task for cleaning old logs
		if ( ! wp_next_scheduled( 'milliondollarscript_clean_old_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'milliondollarscript_clean_old_logs' );
		}
	}

	/**
	 * Clears the scheduled cron.
	 *
	 * @return void
	 */
	public static function clear_cron(): void {
		wp_clear_scheduled_hook( 'milliondollarscript_cron_minute' );
		wp_clear_scheduled_hook( 'milliondollarscript_clean_temp_files' );
		// Clear the new daily log cleaning task
		wp_clear_scheduled_hook( 'milliondollarscript_clean_old_logs' );
	}

	/**
	 * Tasks that run every minute.
	 *
	 * @return void
	 */
	public static function minute(): void {
		// Expire orders - use direct value from Options
		if ( Options::get_option( 'expire-orders' ) == 'yes' ) {
			Orders::expire_orders();
		}
	}

	public static function clean_temp_files(): void {
		// delete grid#-* files older than 24 hours
		global $wpdb;

		$banners = $wpdb->get_results( "SELECT `banner_id` FROM " . MDS_DB_PREFIX . "banners ORDER BY banner_id", ARRAY_A );

		// Bail early if upload directory is missing to avoid runtime warnings in cron.
		$grid_dir = Utility::get_upload_path() . "grids/";
		if ( is_dir( $grid_dir ) && is_readable( $grid_dir ) ) {
			foreach ( $banners as $banner ) {
				$upload_dir = $grid_dir;
				$dh         = @opendir( $upload_dir );
				if ( ! $dh ) {
					Logs::log( 'clean_temp_files: unable to open grids directory: ' . $upload_dir, Logs::LEVEL_WARNING );
					continue;
				}
				while ( ( $file = readdir( $dh ) ) !== false ) {
					// 24 hours
					$elapsed_time = 60 * 60 * 24;

					// delete old files
					$stat = stat( $upload_dir . $file );
					if ( $stat['mtime'] < ( time() - $elapsed_time ) ) {
						if ( str_starts_with( $file, 'grid' . $banner['banner_id'] . '-' ) ) {
							unlink( $upload_dir . $file );
						}
					}
				}
			}
		} else {
			Logs::log( 'clean_temp_files: grids upload directory missing or unreadable: ' . $grid_dir, Logs::LEVEL_WARNING );
		}

		// delete tmp_* files older than 24 hours
		$upload_dir = Utility::get_upload_path() . "images/";
		if ( ! is_dir( $upload_dir ) || ! is_readable( $upload_dir ) ) {
			Logs::log( 'clean_temp_files: images upload directory missing or unreadable: ' . $upload_dir, Logs::LEVEL_WARNING );
			return;
		}
		$dh         = @opendir( $upload_dir );
		if ( ! $dh ) {
			Logs::log( 'clean_temp_files: unable to open images directory: ' . $upload_dir, Logs::LEVEL_WARNING );
			return;
		}
		while ( ( $file = readdir( $dh ) ) !== false ) {
			// 24 hours
			$elapsed_time = 60 * 60 * 24;

			// delete old files
			$stat = stat( $upload_dir . $file );
			if ( $stat['mtime'] < ( time() - $elapsed_time ) ) {
				if ( str_starts_with( $file, 'tmp_' ) ) {
					unlink( $upload_dir . $file );
				}
			}
		}
	}

	/**
	 * Clean old log files.
	 *
	 * Deletes MDS log files older than a specified number of days (default 30).
	 */
	public static function clean_old_log_files(): void {
		$filesystem = new Filesystem();
		$upload_dir = Utility::get_upload_path();
		$log_basename = Options::get_option( 'mds-log-file' ) ?? 'mds_debug';
		// Ensure the basename doesn't already contain path separators or extension
		$log_basename = preg_replace('/\.log$/i', '', basename( (string) $log_basename )); 

		// Use file_exists as it works for directories too in our Filesystem wrapper
		if ( ! $upload_dir || ! $filesystem->file_exists( $upload_dir ) ) {
			// Log error or return if upload directory doesn't exist
			Logs::log('clean_old_log_files: Upload directory not found or inaccessible: ' . $upload_dir, Logs::LEVEL_ERROR);
			return;
		}

		// Retention period in days (could make this an option later)
		$retention_days = 30; 
		$cutoff_timestamp = strtotime("-$retention_days days");

		// Pattern to match log files, e.g., mds_debug_YYYY-MM-DD.log
		$log_file_pattern = trailingslashit( $upload_dir ) . $log_basename . '_*.log';

		// Use native PHP glob as Filesystem class doesn't have a suitable equivalent
		$log_files = glob( $log_file_pattern );

		if ( $log_files === false || empty( $log_files ) ) {
			// Error during glob or no log files found to clean
			return;
		}

		$deleted_count = 0;
		$error_count = 0;

		foreach ( $log_files as $file_path ) {
			// Extract date from filename, e.g., mds_debug_2025-05-03.log
			if ( preg_match( '/' . preg_quote($log_basename, '/') . '_(\d{4}-\d{2}-\d{2})\.log$/', $file_path, $matches ) ) {
				$file_date_str = $matches[1];
				$file_timestamp = strtotime( $file_date_str );

				if ( $file_timestamp !== false && $file_timestamp < $cutoff_timestamp ) {
					// File is older than retention period, try to delete
					try {
						if ( $filesystem->delete_file( $file_path ) ) {
							$deleted_count++;
						} else {
							$error_count++;
							Logs::log('clean_old_log_files: Failed to delete file: ' . $file_path, Logs::LEVEL_WARNING);
						}
					} catch ( \Exception $e ) {
						$error_count++;
						Logs::log('clean_old_log_files: Exception deleting file ' . $file_path . ': ' . $e->getMessage(), Logs::LEVEL_ERROR);
					}
				}
			}
		}

		if ($deleted_count > 0 || $error_count > 0) {
			Logs::log(sprintf('clean_old_log_files: Completed. Deleted %d files, encountered %d errors.', $deleted_count, $error_count), Logs::LEVEL_INFO);
		}

	}

}
