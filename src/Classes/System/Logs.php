<?php

namespace MillionDollarScript\Classes\System;

use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\System\Filesystem;

defined( 'ABSPATH' ) or exit;

/**
 * Class Logs
 * Handles logging operations for the Million Dollar Script plugin.
 */
class Logs {

	const LEVEL_INFO    = 'INFO';
	const LEVEL_WARNING = 'WARNING';
	const LEVEL_ERROR   = 'ERROR';
	const LEVEL_FATAL   = 'FATAL';
	/**
	 * Only logged if DEBUG is also true
	 */
	const LEVEL_DEBUG   = 'DEBUG';

	/**
	 * Get the full path to the log file.
	 *
	 * Constructs the path using the hardcoded filename and the MDS uploads directory.
	 *
	 * @return string|null The full path to the log file, or null on error.
	 */
	public static function get_log_file_path(): ?string {
		$upload_path = Utility::get_upload_path();
		// Hardcoded base filename
		$log_basename = 'mds_debug';

		if ( empty( $upload_path ) || empty( $log_basename ) ) {
			// Cannot determine path, maybe log an error to PHP's error log?
			// For now, return null to prevent logging.
			return null;
		}

		// Ensure the basename doesn't already contain path separators
		$log_basename = basename( $log_basename );

		// Append date stamp and extension
		$date_stamp = date( 'Y-m-d' );
		$log_filename = sprintf(
			'%s_%s.log',
			// Remove existing .log if present in basename
			preg_replace('/\.log$/i', '', $log_basename),
			$date_stamp
		);

		// Construct the full path
		$full_log_path = trailingslashit( $upload_path ) . $log_filename;

		// Return the dynamically generated path
		return $full_log_path;
	}

	/**
	 * Log a message to the MDS log file.
	 *
	 * @param string $message The message to log
	 * @param string $level   The log level
	 *
	 * @return bool True if message was logged, false otherwise
	 */
	public static function log( string $message, string $level = self::LEVEL_INFO ): bool {
		// Check if logging is enabled using Options class - for checkboxes, value is directly usable as boolean
		if ( Options::get_option( 'log-enable', 'no' ) !== 'yes' ) {
			return false;
		}

		$log_file = self::get_log_file_path();

		if ( $log_file === null ) {
			// Could not determine log file path
			return false;
		}

		/**
		 * Example: 2025-05-03 14:45:00 UTC
		 */
		$timestamp = date( 'Y-m-d H:i:s T' );
		$log_entry = sprintf( "[%s] [%s] %s\n", $timestamp, strtoupper( $level ), $message );

		// Emit to PHP error log in development for quick inspection (guarded by constant).
		if ( defined( 'MDS_DEV_LOG' ) && MDS_DEV_LOG ) {
			error_log( '[MDS] ' . strtoupper( $level ) . ' ' . $message );
		}

		// Append to the log file using Filesystem class
		$filesystem = new Filesystem();
		try {
			return $filesystem->append_contents( $log_file, $log_entry );
		} catch ( \Exception $e ) {
			// Log error writing to log file to PHP's error log as a fallback
			error_log( 'MDS Logs::log - Exception writing to log file ' . $log_file . ': ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Clear the MDS log file.
	 *
	 * @return bool True if the file was cleared or didn't exist, false on failure.
	 */
	public static function clear_log(): bool {
		$log_file = self::get_log_file_path();

		if ( $log_file === null ) {
			// Cannot determine log file path
			return false;
		}

		$filesystem = new Filesystem();

		if ( Filesystem::file_exists( $log_file ) ) {
			try {
				// Attempt to delete the file using Filesystem class
				if ( $filesystem->delete_file( $log_file ) ) {
					// Optionally, log the clearing action itself (if logging is enabled)
					self::log( 'Log file cleared.', self::LEVEL_INFO );
					return true;
				} else {
					// Log failure to PHP error log
					error_log( 'MDS Logs::clear_log - Failed to delete log file via Filesystem: ' . $log_file );
					return false;
				}
			} catch ( \Exception $e ) {
				error_log( 'MDS Logs::clear_log - Exception deleting log file ' . $log_file . ': ' . $e->getMessage() );
				return false;
			}
		} else {
			// File doesn't exist, so it's effectively cleared
			return true;
		}
	}

	/**
	 * Get the content of the log file.
	 *
	 * @param int $lines Optional. Number of lines to retrieve from the end of the file. 0 means all lines.
	 * @return string|false The log content, or false on failure or if the file doesn't exist.
	 */
	public static function get_log_content( int $lines = 0 ) {
		$log_file = self::get_log_file_path();
		$filesystem = new Filesystem();

		if ( $log_file === null || ! Filesystem::file_exists( $log_file ) || ! $filesystem->is_readable( $log_file ) ) {
			return false;
		}

		try {
			// Read content using Filesystem class
			$content = $filesystem->get_contents( $log_file );

			if ($content === false) {
				/**
				 * Filesystem::get_contents failed
				 */
				return false;
			}

			if ( $lines > 0 ) {
				// Basic implementation: Get all lines and return the last $lines
				$all_lines = explode( "\n", trim( $content ) );
				$last_lines = array_slice( $all_lines, -$lines );
				return implode( "\n", $last_lines );
			} else {
				// Return the entire content
				return $content;
			}
		} catch ( \Exception $e ) {
			error_log( 'MDS Logs::get_log_content - Exception reading log file ' . $log_file . ': ' . $e->getMessage() );
			return false;
		}
	}

	// Methods for searching logs will be added later.

}
