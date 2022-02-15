<?php

/**
 * Million Dollar Script
 *
 * @version 2.3.2
 * @author Ryan Rhode
 * @copyright (C) 2022, Ryan Rhode
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
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
 */

namespace MillionDollarScript\Classes;

/**
 * Class Filesystem
 * @package MillionDollarScript\Classes
 */
class Filesystem {

	private $url;

	/**
	 * Filesystem constructor.
	 *
	 * @param $url
	 */
	public function __construct( $url = '' ) {
		$this->url = $url;
	}

	/**
	 * Get credentials and try to get a WP_filesystem
	 *
	 * @return bool
	 */
	private function get_filesystem() {

		// request filesystem credentials
		if ( false === ( $creds = request_filesystem_credentials( $this->url ) ) ) {
			return false;
		}

		// get filesystem with credentials
		if ( ! WP_Filesystem( $creds ) ) {
			return false;
		}

		return true;
	}

	/**
	 *
	 * @param $folder
	 *
	 * @param $delete boolean
	 *
	 * @return boolean|\WP_Error
	 */
	public function make_folder( $folder, $delete = false ) {
		$this->get_filesystem();

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
	}

	/**
	 *
	 * @param $folder
	 *
	 * @return boolean|\WP_Error
	 */
	public function delete_folder( $folder ) {
		$this->get_filesystem();

		/** @var $wp_filesystem \WP_Filesystem_Direct */
		global $wp_filesystem;

		// delete the folder
		if ( $wp_filesystem->is_dir( $folder ) ) {
			$wp_filesystem->delete( $folder, true );
		}

		return true;
	}

	/**
	 * Copy a file.
	 *
	 * @param $src
	 * @param $dest
	 * @param false $overwrite
	 *
	 * @return bool
	 */
	public function copy( $src, $dest, $overwrite = false ) {
		$this->get_filesystem();

		/** @var $wp_filesystem \WP_Filesystem_Direct */
		global $wp_filesystem;

		// copy src to dest
		return $wp_filesystem->copy( $src, $dest, $overwrite );
	}

}