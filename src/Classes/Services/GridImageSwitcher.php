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

namespace MillionDollarScript\Classes\Services;

defined( 'ABSPATH' ) or exit;

use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Data\DarkModeImages;
use MillionDollarScript\Classes\Services\GridImageGenerator;
use MillionDollarScript\Classes\System\Logs;

/**
 * Grid Image Switcher Service
 * 
 * Handles automatic switching of grid images when theme mode changes
 */
class GridImageSwitcher {

	/**
	 * Initialize the grid image switching system
	 * Hook into the existing theme change process
	 */
	public static function init(): void {
		// Hook into the existing theme change system
		add_action( 'mds_theme_changed', [ __CLASS__, 'handle_theme_change' ], 10, 2 );
		
		// Also hook into the Carbon Fields save action to catch theme changes
		add_action( 'carbon_fields_container_theme_settings_saved', [ __CLASS__, 'handle_container_save' ] );
	}

	/**
	 * Handle Carbon Fields container save to detect theme changes
	 */
	public static function handle_container_save(): void {
		// Check if theme mode changed and if grid automation is enabled
		$auto_update = Options::get_option( 'auto_update_grid_images', 'no' );
		if ( $auto_update !== 'yes' ) {
			return;
		}

		$current_theme = Options::get_option( 'theme_mode', 'light' );
		$previous_theme = get_transient( 'mds_previous_theme_mode' );
		
		if ( $current_theme !== $previous_theme ) {
			self::switch_grid_images( $current_theme );
			set_transient( 'mds_previous_theme_mode', $current_theme, DAY_IN_SECONDS );
		}
	}

	/**
	 * Handle theme change event
	 * 
	 * @param string $new_theme New theme mode
	 * @param string $old_theme Previous theme mode
	 */
	public static function handle_theme_change( string $new_theme, string $old_theme ): void {
		// Check if grid image automation is enabled
		$auto_update = Options::get_option( 'auto_update_grid_images', 'no' );
		if ( $auto_update !== 'yes' ) {
			return;
		}

		self::switch_grid_images( $new_theme );
	}

	/**
	 * Switch grid images for the specified theme
	 * 
	 * @param string $theme_mode Theme mode ('light' or 'dark')
	 * @return array Result of the operation
	 */
	public static function switch_grid_images( string $theme_mode ): array {
		try {
			// Check if images are already in the correct state
			$current_image_state = self::get_current_image_state();
			if ( $current_image_state === $theme_mode ) {
				return [
					'status' => 'no_change',
					'message' => "Grid images are already optimized for {$theme_mode} mode"
				];
			}

			// Create backup before switching
			$backup_created = self::backup_grid_images( $theme_mode );
			if ( ! $backup_created ) {
				Logs::log( 'MDS Grid Image Switch: Failed to create backup before switching' );
			}

			// Get all banners that need image updates
			$banners = self::get_all_banners();
			
			if ( empty( $banners ) ) {
				return [
					'status' => 'error',
					'message' => 'No banners found to update'
				];
			}

			// Generate or get appropriate images for the theme
			$images = self::get_theme_images( $theme_mode );
			
			if ( empty( $images ) ) {
				return [
					'status' => 'error', 
					'message' => 'Failed to generate theme images'
				];
			}

			// Update all banners with new images
			$updated_count = 0;
			$failed_updates = [];

			foreach ( $banners as $banner ) {
				$result = self::update_banner_images( $banner->banner_id, $images );
				if ( $result ) {
					$updated_count++;
				} else {
					$failed_updates[] = $banner->banner_id;
				}
			}

			// Update the current image state
			if ( $updated_count > 0 ) {
				self::set_current_image_state( $theme_mode );
			}

			// Log the operation
			Logs::log( "MDS Grid Image Switch: Updated {$updated_count} banners to {$theme_mode} mode" );

			if ( empty( $failed_updates ) ) {
				return [
					'status' => 'success',
					'message' => "Successfully updated {$updated_count} banners to {$theme_mode} mode",
					'updated_count' => $updated_count,
					'backup_created' => $backup_created
				];
			} else {
				return [
					'status' => 'partial_success',
					'message' => "Updated {$updated_count} banners, failed to update " . count( $failed_updates ) . " banners",
					'updated_count' => $updated_count,
					'failed_banners' => $failed_updates,
					'backup_created' => $backup_created
				];
			}

		} catch ( \Exception $e ) {
			Logs::log( 'MDS Grid Image Switch Error: ' . $e->getMessage() );
			return [
				'status' => 'error',
				'message' => 'An error occurred while switching grid images: ' . $e->getMessage()
			];
		}
	}

	/**
	 * Get all banners from the database
	 * 
	 * @return array Array of banner objects
	 */
	private static function get_all_banners(): array {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'mds_banners';
		
		return $wpdb->get_results( "SELECT banner_id, block_width, block_height FROM {$table_name}" );
	}

	/**
	 * Get theme-appropriate images
	 * 
	 * @param string $theme_mode Theme mode ('light' or 'dark')
	 * @return array Associative array of image data
	 */
	private static function get_theme_images( string $theme_mode ): array {
		// Try dynamic generation first if GD is available and we're in dark mode
		if ( $theme_mode === 'dark' && GridImageGenerator::is_gd_available() ) {
			try {
				return GridImageGenerator::generate_theme_images( $theme_mode );
			} catch ( \Exception $e ) {
				Logs::log( 'MDS Dynamic Image Generation Failed: ' . $e->getMessage() );
				// Fall back to static images
			}
		}

		// Use static images as fallback or for light mode
		return DarkModeImages::get_images_by_theme( $theme_mode );
	}

	/**
	 * Update a banner's images in the database
	 * 
	 * @param int $banner_id Banner ID to update
	 * @param array $images Associative array of image data
	 * @return bool True if successful, false otherwise
	 */
	private static function update_banner_images( int $banner_id, array $images ): bool {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'mds_banners';
		
		// Only update the images that should be updated for dark mode
		$updateable_images = DarkModeImages::get_updateable_images();
		$update_data = [];
		
		foreach ( $updateable_images as $image_name ) {
			if ( isset( $images[ $image_name ] ) ) {
				$update_data[ $image_name ] = $images[ $image_name ];
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table_name,
			$update_data,
			[ 'banner_id' => $banner_id ],
			array_fill( 0, count( $update_data ), '%s' ), // All images are strings
			[ '%d' ] // banner_id is integer
		);

		return $result !== false;
	}

	/**
	 * Backup current grid images before switching
	 * 
	 * @param string $theme_mode Current theme mode
	 * @return bool True if backup successful
	 */
	public static function backup_grid_images( string $theme_mode ): bool {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'mds_banners';
		$backup_key = 'mds_grid_images_backup_' . $theme_mode . '_' . time();
		
		// Get current images for all banners
		$banners = $wpdb->get_results( "
			SELECT banner_id, grid_block, nfs_block, tile, usr_grid_block, usr_nfs_block, usr_ord_block 
			FROM {$table_name}
		" );

		if ( empty( $banners ) ) {
			return false;
		}

		// Store backup as WordPress option
		return update_option( $backup_key, $banners );
	}

	/**
	 * Restore grid images from backup
	 * 
	 * @param string $backup_key Backup key to restore from
	 * @return array Result of the restore operation
	 */
	public static function restore_grid_images( string $backup_key ): array {
		$backup_data = get_option( $backup_key );
		
		if ( ! $backup_data ) {
			return [
				'status' => 'error',
				'message' => 'Backup data not found'
			];
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'mds_banners';
		$restored_count = 0;

		foreach ( $backup_data as $banner_data ) {
			$result = $wpdb->update(
				$table_name,
				[
					'grid_block' => $banner_data->grid_block,
					'nfs_block' => $banner_data->nfs_block,
					'tile' => $banner_data->tile,
					'usr_grid_block' => $banner_data->usr_grid_block,
					'usr_nfs_block' => $banner_data->usr_nfs_block,
					'usr_ord_block' => $banner_data->usr_ord_block,
				],
				[ 'banner_id' => $banner_data->banner_id ],
				[ '%s', '%s', '%s', '%s', '%s', '%s' ],
				[ '%d' ]
			);

			if ( $result !== false ) {
				$restored_count++;
			}
		}

		return [
			'status' => 'success',
			'message' => "Restored {$restored_count} banners from backup",
			'restored_count' => $restored_count
		];
	}

	/**
	 * Get available backup keys
	 * 
	 * @return array Array of backup keys with timestamps
	 */
	public static function get_available_backups(): array {
		global $wpdb;
		
		$results = $wpdb->get_results( "
			SELECT option_name, CHAR_LENGTH(option_value) as size
			FROM {$wpdb->options} 
			WHERE option_name LIKE 'mds_grid_images_backup_%'
			ORDER BY option_name DESC
		" );

		$backups = [];
		foreach ( $results as $result ) {
			if ( preg_match( '/mds_grid_images_backup_(.+)_(\d+)/', $result->option_name, $matches ) ) {
				$backups[] = [
					'key' => $result->option_name,
					'theme' => $matches[1],
					'timestamp' => (int) $matches[2],
					'date' => date( 'Y-m-d H:i:s', (int) $matches[2] ),
					'size' => $result->size
				];
			}
		}

		return $backups;
	}

	/**
	 * Clean up old backup files (older than 30 days)
	 * 
	 * @return int Number of backups cleaned up
	 */
	public static function cleanup_old_backups(): int {
		$backups = self::get_available_backups();
		$cutoff = time() - ( 30 * DAY_IN_SECONDS );
		$cleaned_count = 0;

		foreach ( $backups as $backup ) {
			if ( $backup['timestamp'] < $cutoff ) {
				if ( delete_option( $backup['key'] ) ) {
					$cleaned_count++;
				}
			}
		}

		return $cleaned_count;
	}

	/**
	 * Get the current image state (which theme the images are optimized for)
	 * 
	 * @return string 'light', 'dark', or 'unknown'
	 */
	private static function get_current_image_state(): string {
		$state = get_option( 'mds_current_image_state', 'unknown' );
		
		// If state is unknown, try to detect current state based on current theme
		if ( $state === 'unknown' ) {
			$current_theme = Options::get_option( 'theme_mode', 'light' );
			// For existing installations, assume images match current theme
			self::set_current_image_state( $current_theme );
			return $current_theme;
		}
		
		return $state;
	}

	/**
	 * Set the current image state
	 * 
	 * @param string $theme_mode Theme mode the images are optimized for
	 * @return bool True if successful
	 */
	private static function set_current_image_state( string $theme_mode ): bool {
		return update_option( 'mds_current_image_state', $theme_mode );
	}

	/**
	 * Reset image state tracking (useful for debugging or manual override)
	 * 
	 * @return bool True if successful
	 */
	public static function reset_image_state(): bool {
		return delete_option( 'mds_current_image_state' );
	}
} 