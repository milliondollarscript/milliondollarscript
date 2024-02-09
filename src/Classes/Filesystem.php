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

namespace MillionDollarScript\Classes;

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

			/** @var $wp_filesystem \WP_Filesystem_Direct */
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

			/** @var $wp_filesystem \WP_Filesystem_Direct */
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

			/** @var $wp_filesystem \WP_Filesystem_Direct */
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
			global $wp_filesystem;
			$fileList = $wp_filesystem->dirlist( $dir );
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

}