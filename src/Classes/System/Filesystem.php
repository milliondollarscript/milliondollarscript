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

use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

/**
 * Class Filesystem
 *
 * Secure filesystem operations wrapper for WordPress Filesystem API.
 * Provides path validation and sanitization to prevent directory traversal attacks.
 *
 * @package MillionDollarScript\Classes
 */
class Filesystem {

	private string $url;

	/**
	 * WordPress Filesystem object.
	 *
	 * @var \WP_Filesystem_Base|null
	 */
	private static $static_wp_filesystem = null;

	/**
	 * Allowed base directories for filesystem operations.
	 * All operations are restricted to these directories and their subdirectories.
	 *
	 * @var array<string>
	 */
	private static array $allowed_base_paths = [];

	/**
	 * Initialize allowed base paths.
	 * Should be called during plugin initialization.
	 *
	 * @return void
	 */
	public static function init_allowed_paths(): void {
		$upload_dir = wp_upload_dir();

		self::$allowed_base_paths = [
			// WordPress uploads directory
			$upload_dir['basedir'],
			// MDS-specific upload directory
			Utility::get_upload_path(),
			// WordPress content directory (for extensions, themes, etc.)
			WP_CONTENT_DIR,
			// Temporary directory
			get_temp_dir(),
		];

		// Remove any empty or duplicate paths
		self::$allowed_base_paths = array_filter( array_unique( self::$allowed_base_paths ) );
	}

	/**
	 * Add an allowed base path for filesystem operations.
	 * Useful for allowing operations in plugin-specific directories.
	 *
	 * @param string $path The path to allow.
	 * @return bool True if added successfully, false otherwise.
	 */
	public static function add_allowed_path( string $path ): bool {
		$real_path = realpath( $path );

		if ( $real_path === false ) {
			Logs::log( 'Cannot add allowed path (does not exist): ' . $path, Logs::LEVEL_WARNING );
			return false;
		}

		if ( ! in_array( $real_path, self::$allowed_base_paths, true ) ) {
			self::$allowed_base_paths[] = $real_path;
		}

		return true;
	}

	/**
	 * Validate that a path is within allowed directories.
	 * Prevents directory traversal attacks.
	 *
	 * @param string $path The path to validate.
	 * @return bool True if path is valid and within allowed directories, false otherwise.
	 */
	private static function validate_path( string $path ): bool {
		// Initialize allowed paths if not already done
		if ( empty( self::$allowed_base_paths ) ) {
			self::init_allowed_paths();
		}

		// Get the real path (resolves .., ., symlinks, etc.)
		$real_path = realpath( dirname( $path ) );

		// If realpath returns false, the directory doesn't exist
		// We'll allow this for creation operations, but validate the intended parent
		if ( $real_path === false ) {
			// Try parent directory
			$parent = dirname( $path );
			if ( $parent === $path || $parent === '.' ) {
				// Can't go higher, path is invalid
				Logs::log( 'Invalid path (no valid parent directory): ' . $path, Logs::LEVEL_WARNING );
				return false;
			}
			return self::validate_path( $parent );
		}

		// Check if the real path starts with any allowed base path
		foreach ( self::$allowed_base_paths as $allowed_path ) {
			$allowed_real = realpath( $allowed_path );
			if ( $allowed_real !== false && str_starts_with( $real_path, $allowed_real ) ) {
				return true;
			}
		}

		// Path is not within any allowed directory
		Logs::log( 'Path traversal attempt blocked: ' . $path, Logs::LEVEL_ERROR );
		return false;
	}

	/**
	 * Sanitize a filename to prevent path traversal.
	 * Removes any directory separators and null bytes.
	 *
	 * @param string $filename The filename to sanitize.
	 * @return string The sanitized filename.
	 */
	public static function sanitize_filename( string $filename ): string {
		// Use WordPress sanitize_file_name
		$sanitized = sanitize_file_name( $filename );

		// Additional security: remove any remaining path separators
		$sanitized = str_replace( [ '/', '\\', "\0" ], '', $sanitized );

		// Remove leading dots (hidden files in Unix)
		$sanitized = ltrim( $sanitized, '.' );

		return $sanitized;
	}

	/**
	 * Validate file type based on MIME type and extension.
	 *
	 * @param string $file_path Path to the file.
	 * @param array<string> $allowed_types Allowed MIME types (e.g., ['image/jpeg', 'image/png']).
	 * @param array<string> $allowed_extensions Allowed extensions (e.g., ['jpg', 'png']).
	 * @return bool True if file type is valid, false otherwise.
	 */
	public static function validate_file_type( string $file_path, array $allowed_types = [], array $allowed_extensions = [] ): bool {
		// Check extension
		if ( ! empty( $allowed_extensions ) ) {
			$file_extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
			if ( ! in_array( $file_extension, $allowed_extensions, true ) ) {
				Logs::log( 'File extension not allowed: ' . $file_extension, Logs::LEVEL_WARNING );
				return false;
			}
		}

		// Check MIME type
		if ( ! empty( $allowed_types ) ) {
			$wp_filetype = wp_check_filetype( $file_path );
			if ( ! in_array( $wp_filetype['type'], $allowed_types, true ) ) {
				Logs::log( 'File MIME type not allowed: ' . $wp_filetype['type'], Logs::LEVEL_WARNING );
				return false;
			}
		}

		return true;
	}

	/**
	 * Initialize and ensure the WordPress Filesystem API is available.
	 *
	 * @return bool True if the filesystem is initialized and available, false otherwise.
	 */
	private static function init_wp_filesystem(): bool {
		global $wp_filesystem;

		// If it's already initialized and is the correct object, nothing to do.
		if ( $wp_filesystem instanceof \WP_Filesystem_Base ) {
			self::$static_wp_filesystem = $wp_filesystem; // Cache it statically
			return true;
		}

		// If our static cache has it, re-assign to global (e.g. if global was unset somehow)
		if ( self::$static_wp_filesystem instanceof \WP_Filesystem_Base ) {
			$wp_filesystem = self::$static_wp_filesystem;
			return true;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() ) {
			// Filesystem credentials are not available or WP_Filesystem() failed.
			Logs::log( 'WP_Filesystem could not be initialized.', Logs::LEVEL_ERROR );
			return false;
		}

		// WP_Filesystem() sets the global $wp_filesystem object.
		// Cache it statically for subsequent calls within the same request.
		self::$static_wp_filesystem = $wp_filesystem;
		return true;
	}

	/**
	 * Filesystem constructor.
	 *
	 * @param string $url Optional URL parameter (legacy).
	 */
	public function __construct( string $url = '' ) {
		$this->url = $url;
	}

	/**
	 * Create a directory.
	 *
	 * @param string $folder Path to the folder to create.
	 * @param bool $delete Whether to delete the folder first if it exists.
	 *
	 * @return bool|\WP_Error True on success, false or WP_Error on failure.
	 */
	public function make_folder( string $folder, bool $delete = false ): \WP_Error|bool {
		// Validate path
		if ( ! self::validate_path( $folder ) ) {
			return new \WP_Error(
				'invalid_path',
				Language::get( 'Invalid path: directory is outside allowed locations.' ),
				$folder
			);
		}

		if ( ! self::init_wp_filesystem() ) {
			return false;
		}

		/**
		 * @var \WP_Filesystem_Base $wp_filesystem
		 * @psalm-suppress UndefinedGlobalVariable Using WordPress global
		 */
		global $wp_filesystem;

		// Optionally delete the folder
		if ( $delete ) {
			if ( $wp_filesystem->is_dir( $folder ) ) {
				$wp_filesystem->delete( $folder, true );
			}
		}

		// If the folder doesn't exist create it
		if ( ! $wp_filesystem->is_dir( $folder ) ) {
			if ( ! $wp_filesystem->mkdir( $folder, FS_CHMOD_DIR ) ) {
				return new \WP_Error(
					'mkdir_failed',
					Language::get( 'Could not create directory.' ),
					$folder
				);
			}
		}

		return true;
	}

	/**
	 * Delete a directory.
	 *
	 * @param string $folder Path to the folder to delete.
	 *
	 * @return bool|\WP_Error True on success, false or WP_Error on failure.
	 */
	public function delete_folder( string $folder ): \WP_Error|bool {
		// Validate path
		if ( ! self::validate_path( $folder ) ) {
			return new \WP_Error(
				'invalid_path',
				Language::get( 'Invalid path: directory is outside allowed locations.' ),
				$folder
			);
		}

		if ( ! self::init_wp_filesystem() ) {
			return false;
		}

		/**
		 * @var \WP_Filesystem_Base $wp_filesystem
		 * @psalm-suppress UndefinedGlobalVariable Using WordPress global
		 */
		global $wp_filesystem;

		// Delete the folder
		if ( $wp_filesystem->is_dir( $folder ) ) {
			if ( ! $wp_filesystem->delete( $folder, true ) ) {
				return new \WP_Error(
					'delete_failed',
					Language::get( 'Could not delete directory.' ),
					$folder
				);
			}
		}

		return true;
	}

	/**
	 * Copy a file.
	 *
	 * @param string $src Source file path.
	 * @param string $dest Destination file path.
	 * @param bool $overwrite Whether to overwrite existing file.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function copy( string $src, string $dest, bool $overwrite = false ): bool {
		// Validate both paths
		if ( ! self::validate_path( $src ) ) {
			Logs::log( 'Copy failed: invalid source path - ' . $src, Logs::LEVEL_ERROR );
			return false;
		}

		if ( ! self::validate_path( $dest ) ) {
			Logs::log( 'Copy failed: invalid destination path - ' . $dest, Logs::LEVEL_ERROR );
			return false;
		}

		if ( ! self::init_wp_filesystem() ) {
			return false;
		}

		/**
		 * @var \WP_Filesystem_Base $wp_filesystem
		 * @psalm-suppress UndefinedGlobalVariable Using WordPress global
		 */
		global $wp_filesystem;

		// Verify source exists
		if ( ! $wp_filesystem->exists( $src ) ) {
			Logs::log( 'Copy failed: source file does not exist - ' . $src, Logs::LEVEL_ERROR );
			return false;
		}

		// Copy src to dest
		return $wp_filesystem->copy( $src, $dest, $overwrite );
	}

	/**
	 * Get a list of files in the specified directory that match the filter.
	 *
	 * @param string $dir Directory path to list.
	 * @param string $filter Optional filter to match filenames.
	 *
	 * @return array<string> Array of file paths. Empty array on error or no matches.
	 */
	public function list_files( string $dir, string $filter = '' ): array {
		// Validate path
		if ( ! self::validate_path( $dir ) ) {
			Logs::log( 'List files failed: invalid directory path - ' . $dir, Logs::LEVEL_ERROR );
			return [];
		}

		$files = [];
		if ( ! self::init_wp_filesystem() ) {
			return $files;
		}

		/**
		 * @var \WP_Filesystem_Base $wp_filesystem
		 * @psalm-suppress UndefinedGlobalVariable Using WordPress global
		 */
		global $wp_filesystem;

		// Ensure directory ends with trailing slash
		$dir = trailingslashit( $dir );

		// Get directory listing
		$fileList = $wp_filesystem->dirlist( $dir );

		if ( ! is_array( $fileList ) ) {
			return $files;
		}

		// Filter by name if filter is provided
		foreach ( $fileList as $filename => $fileinfo ) {
			// Sanitize filename to prevent any path traversal
			$safe_filename = self::sanitize_filename( $filename );

			// Apply filter if provided
			if ( $filter === '' || str_contains( $safe_filename, $filter ) ) {
				// Don't use esc_html on paths - just sanitize the filename
				$files[] = $dir . $safe_filename;
			}
		}

		return $files;
	}

	/**
	 * Check if a file or directory exists using WordPress filesystem.
	 *
	 * @param string $path Path to check.
	 * @return bool True if exists, false otherwise or on error.
	 */
	public static function file_exists( string $path ): bool {
		// Validate path
		if ( ! self::validate_path( $path ) ) {
			return false;
		}

		if ( ! self::init_wp_filesystem() ) {
			return false;
		}

		/** @var \WP_Filesystem_Base $wp_filesystem */
		global $wp_filesystem;
		return $wp_filesystem->exists( $path );
	}

	/**
	 * Delete a file using WordPress filesystem.
	 *
	 * @param string $file_path Path to the file to delete.
	 *
	 * @return bool True if delete was successful or file doesn't exist, false otherwise.
	 */
	public function delete_file( string $file_path ): bool {
		// Validate path
		if ( ! self::validate_path( $file_path ) ) {
			Logs::log( 'Delete file failed: invalid file path - ' . $file_path, Logs::LEVEL_ERROR );
			return false;
		}

		if ( ! self::init_wp_filesystem() ) {
			return false;
		}

		/**
		 * @var \WP_Filesystem_Base $wp_filesystem
		 * @psalm-suppress UndefinedGlobalVariable Using WordPress global
		 */
		global $wp_filesystem;

		// Check if file exists
		if ( ! $wp_filesystem->exists( $file_path ) ) {
			// File doesn't exist, consider it a success
			return true;
		}

		// Delete the file using WordPress filesystem
		return $wp_filesystem->delete( $file_path, false );
	}

	/**
	 * Check if a file is readable using WordPress filesystem.
	 *
	 * @param string $path Path to the file.
	 * @return bool True if readable, false otherwise or on error.
	 */
	public function is_readable( string $path ): bool {
		// Validate path
		if ( ! self::validate_path( $path ) ) {
			return false;
		}

		if ( ! self::init_wp_filesystem() ) {
			return false;
		}

		/** @var \WP_Filesystem_Base $wp_filesystem */
		global $wp_filesystem;
		return $wp_filesystem->is_readable( $path );
	}

	/**
	 * Get the contents of a file using WordPress filesystem.
	 *
	 * @param string $path Path to the file.
	 * @return string|false File contents on success, false on failure.
	 */
	public function get_contents( string $path ) {
		// Validate path
		if ( ! self::validate_path( $path ) ) {
			Logs::log( 'Get contents failed: invalid file path - ' . $path, Logs::LEVEL_ERROR );
			return false;
		}

		if ( ! self::init_wp_filesystem() ) {
			return false;
		}

		/** @var \WP_Filesystem_Base $wp_filesystem */
		global $wp_filesystem;

		// Ensure the file exists and is readable first
		if ( $wp_filesystem->exists( $path ) && $wp_filesystem->is_readable( $path ) ) {
			return $wp_filesystem->get_contents( $path );
		}

		return false;
	}

	/**
	 * Append content to a file.
	 * Uses WP_Filesystem to check permissions/existence, but may fallback to native
	 * file_put_contents for the actual append operation due to WP_Filesystem limitations.
	 *
	 * @param string $path Path to the file.
	 * @param string $data The data to append.
	 * @return bool True on success, false on failure.
	 */
	public function append_contents( string $path, string $data ): bool {
		// Validate path
		if ( ! self::validate_path( $path ) ) {
			Logs::log( 'Append contents failed: invalid file path - ' . $path, Logs::LEVEL_ERROR );
			return false;
		}

		if ( ! self::init_wp_filesystem() ) {
			return false;
		}

		/** @var \WP_Filesystem_Base $wp_filesystem */
		global $wp_filesystem;

		// Ensure the directory exists and is writable
		$dir = dirname( $path );
		if ( ! $wp_filesystem->is_dir( $dir ) ) {
			// Attempt to create the directory if it doesn't exist
			if ( ! $wp_filesystem->mkdir( $dir, FS_CHMOD_DIR ) ) {
				Logs::log( 'MDS Filesystem::append_contents - Could not create directory: ' . $dir, Logs::LEVEL_ERROR );
				return false;
			}
		} elseif ( ! $wp_filesystem->is_writable( $dir ) ) {
			Logs::log( 'MDS Filesystem::append_contents - Directory not writable: ' . $dir, Logs::LEVEL_ERROR );
			return false;
		}

		/**
		 * WP_Filesystem doesn't have a native append. We use it to check/ensure
		 * writability, then use native file_put_contents with append flag.
		 */
		try {
			/**
			 * The $wp_filesystem context should ensure permissions are okay.
			 */
			$result = file_put_contents( $path, $data, FILE_APPEND | LOCK_EX );
			return $result !== false;
		} catch ( \Exception $e ) {
			Logs::log( 'MDS Filesystem::append_contents - Exception appending to file ' . $path . ': ' . $e->getMessage(), Logs::LEVEL_ERROR );
			return false;
		}
	}

	/**
	 * Write content to a file, overwriting if it exists.
	 *
	 * @param string $path Path to the file.
	 * @param string $data The data to write.
	 * @return bool True on success, false on failure.
	 */
	public static function put_contents( string $path, string $data ): bool {
		// Validate path
		if ( ! self::validate_path( $path ) ) {
			Logs::log( 'Put contents failed: invalid file path - ' . $path, Logs::LEVEL_ERROR );
			return false;
		}

		if ( ! self::init_wp_filesystem() ) {
			Logs::log( 'Filesystem could not be initialized for put_contents.', Logs::LEVEL_ERROR );
			return false;
		}

		/** @var \WP_Filesystem_Base $wp_filesystem */
		global $wp_filesystem; // $wp_filesystem is initialized by init_wp_filesystem()

		$directory = dirname( $path );

		// Ensure the directory exists and is writable
		if ( ! $wp_filesystem->is_dir( $directory ) ) {
			// Attempt to create the directory if it doesn't exist
			if ( ! $wp_filesystem->mkdir( $directory, FS_CHMOD_DIR ) ) {
				Logs::log( 'MDS Filesystem::put_contents - Could not create directory: ' . $directory, Logs::LEVEL_ERROR );
				return false;
			}
		} elseif ( ! $wp_filesystem->is_writable( $directory ) ) {
			Logs::log( 'MDS Filesystem::put_contents - Directory not writable: ' . $directory, Logs::LEVEL_ERROR );
			return false;
		}

		// Write the file
		return $wp_filesystem->put_contents( $path, $data, FS_CHMOD_FILE );
	}
}
