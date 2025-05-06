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

defined( 'ABSPATH' ) or exit;

/**
 * Class Filesystem
 * @package MillionDollarScript\Classes
 */
class Filesystem {

	private string $url;

	/**
	 * Filesystem constructor.
	 *
	 * @param string $url
	 */
	public function __construct( string $url = '' ) {
		$this->url = $url;
	}

	/**
	 *
	 * @param $folder
	 *
	 * @param $delete boolean
	 *
	 * @return boolean|\WP_Error
	 */
	public function make_folder( $folder, bool $delete = false ): \WP_Error|bool {
		if ( $this->get_filesystem() ) {

			/**
			 * @var \WP_Filesystem_Base $wp_filesystem
			 * @psalm-suppress UndefinedGlobalVariable Using WordPress global
			 */
			global $wp_filesystem;

			// optionally delete the folder
			if ( $delete ) {
				if ( $wp_filesystem->is_dir( $folder ) ) {
					$wp_filesystem->delete( $folder, true );
				}
			}

			// if the folder doesn't exist create it
			if ( ! $wp_filesystem->is_dir( $folder ) ) {
				if ( ! $wp_filesystem->mkdir( $folder, FS_CHMOD_DIR ) ) {
					return new \WP_Error( 'mkdir_failed_copy_dir', __( 'Could not create directory.' ), $folder );
				}
			}

			return true;
		} else {
			return false;
		}
	}

	/**
	 *
	 * @param $folder
	 *
	 * @return boolean|\WP_Error
	 */
	public function delete_folder( $folder ): \WP_Error|bool {
		if ( $this->get_filesystem() ) {

			/**
			 * @var \WP_Filesystem_Base $wp_filesystem
			 * @psalm-suppress UndefinedGlobalVariable Using WordPress global
			 */
			global $wp_filesystem;

			// delete the folder
			if ( $wp_filesystem->is_dir( $folder ) ) {
				$wp_filesystem->delete( $folder, true );
			}

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Copy a file.
	 *
	 * @param $src
	 * @param $dest
	 * @param bool $overwrite
	 *
	 * @return bool
	 */
	public function copy( $src, $dest, bool $overwrite = false ): bool {
		if ( $this->get_filesystem() ) {

			/**
			 * @var \WP_Filesystem_Base $wp_filesystem
			 * @psalm-suppress UndefinedGlobalVariable Using WordPress global
			 */
			global $wp_filesystem;

			// copy src to dest
			return $wp_filesystem->copy( $src, $dest, $overwrite );
		} else {
			return false;
		}
	}

	/**
	 * Get a list of files in the specified directory that match the filter.
	 *
	 * @param string $dir
	 * @param string $filter
	 *
	 * @return array If a matching file is found, its name is returned. If no matching files are found, an empty string is returned.
	 */
	public function list_files( string $dir, string $filter = '' ): array {
		$files = [];
		if ( $this->get_filesystem() ) {
			/**
			 * @var \WP_Filesystem_Base $wp_filesystem
			 * @psalm-suppress UndefinedGlobalVariable Using WordPress global
			 */
			global $wp_filesystem;
			
			// Get directory listing
			$fileList = $wp_filesystem->dirlist( $dir );
			
			// Filter by name if filter is provided
			foreach ( $fileList as $filename => $fileinfo ) {
				if ( $filter === '' || str_contains( $filename, $filter ) ) {
					$files[] = esc_html( $dir . $filename );
				}
			}
		}

		return $files;
	}

	/**
	 * Get credentials and try to get a WP_filesystem
	 *
	 * @return bool
	 */
	public function get_filesystem(): bool {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		// request filesystem credentials
		if ( false === ( $creds = \request_filesystem_credentials( $this->url ) ) ) {
			return false;
		}

		// get filesystem with credentials
		if ( ! \WP_Filesystem( $creds ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Delete a file using WordPress filesystem
	 *
	 * @param string $file_path Path to the file to delete
	 *
	 * @return bool True if delete was successful or file doesn't exist, false otherwise
	 */
	public function delete_file( string $file_path ): bool {
		if ( $this->get_filesystem() ) {
			/**
			 * @var \WP_Filesystem_Base $wp_filesystem
			 * @psalm-suppress UndefinedGlobalVariable Using WordPress global
			 */
			global $wp_filesystem;

			// Check if file exists before attempting deletion
			if ( ! $wp_filesystem->exists( $file_path ) ) {
				/**
				 * File doesn't exist, consider it a success
				 */
				return true;
			}

			// Delete the file using WordPress filesystem
			return $wp_filesystem->delete( $file_path, false );
		}

		return false;
	}

	/**
	 * Check if a file or directory exists using WordPress filesystem.
	 *
	 * @param string $path Path to check.
	 * @return bool True if exists, false otherwise or on error.
	 */
	public function file_exists( string $path ): bool {
		if ( $this->get_filesystem() ) {
			/** @var \WP_Filesystem_Base $wp_filesystem */
			global $wp_filesystem;
			return $wp_filesystem->exists( $path );
		}
		return false;
	}

	/**
	 * Check if a file is readable using WordPress filesystem.
	 *
	 * @param string $path Path to the file.
	 * @return bool True if readable, false otherwise or on error.
	 */
	public function is_readable( string $path ): bool {
		if ( $this->get_filesystem() ) {
			/** @var \WP_Filesystem_Base $wp_filesystem */
			global $wp_filesystem;
			return $wp_filesystem->is_readable( $path );
		}
		return false;
	}

	/**
	 * Get the contents of a file using WordPress filesystem.
	 *
	 * @param string $path Path to the file.
	 * @return string|false File contents on success, false on failure.
	 */
	public function get_contents( string $path ) {
		if ( $this->get_filesystem() ) {
			/** @var \WP_Filesystem_Base $wp_filesystem */
			global $wp_filesystem;
			// Ensure the file exists and is readable first
			if ( $wp_filesystem->exists( $path ) && $wp_filesystem->is_readable( $path ) ) {
				return $wp_filesystem->get_contents( $path );
			}
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
		if ( $this->get_filesystem() ) {
			/** @var \WP_Filesystem_Base $wp_filesystem */
			global $wp_filesystem;

			// Ensure the directory exists and is writable
			$dir = dirname( $path );
			if ( ! $wp_filesystem->is_dir( $dir ) ) {
				// Attempt to create the directory if it doesn't exist
				if ( ! $wp_filesystem->mkdir( $dir, FS_CHMOD_DIR ) ) {
					Logs::log( 'MDS Filesystem::append_contents - Could not create directory: ' . $dir );
					return false;
				}
			} elseif ( ! $wp_filesystem->is_writable( $dir ) ) {
				Logs::log( 'MDS Filesystem::append_contents - Directory not writable: ' . $dir );
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
				Logs::log( 'MDS Filesystem::append_contents - Exception appending to file ' . $path . ': ' . $e->getMessage() );
				return false;
			}
		}
		return false;
	}
}