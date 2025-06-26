<?php

namespace MillionDollarScript\Classes\Services;

use MillionDollarScript\Classes\Data\Options;

/**
 * Grid Image Generator Service
 * 
 * Dynamically generates grid images based on theme color settings
 */
class GridImageGenerator {

	/**
	 * Generate all required grid images for the current theme
	 * 
	 * @param string $theme_mode 'light' or 'dark'
	 * @param int $block_width Block width in pixels (default: 10)  
	 * @param int $block_height Block height in pixels (default: 10)
	 * @return array Associative array of image names and their base64 data
	 */
	public static function generate_theme_images( string $theme_mode = 'dark', int $block_width = 10, int $block_height = 10 ): array {
		if ( $theme_mode !== 'dark' ) {
			// For light mode, return the original static images
			return \MillionDollarScript\Classes\Data\DarkModeImages::get_light_mode_images();
		}

		// Get dark mode color settings
		$colors = self::get_dark_mode_colors();
		
		$images = [];
		
		// Generate grid_block (secondary background + tertiary border)
		$images['grid_block'] = self::generate_grid_block( $colors['secondary'], $colors['tertiary'], $block_width, $block_height );
		
		// Generate usr_grid_block (same as grid_block)
		$images['usr_grid_block'] = $images['grid_block'];
		
		// Generate nfs_block (solid tertiary color)
		$images['nfs_block'] = self::generate_nfs_block( $colors['tertiary'], $block_width, $block_height );
		
		// Generate usr_nfs_block (same as nfs_block)
		$images['usr_nfs_block'] = $images['nfs_block'];
		
		// Generate usr_ord_block (variation for ordered blocks)
		$images['usr_ord_block'] = self::generate_ord_block( $colors['tertiary'], $colors['secondary'], $block_width, $block_height );
		
		// Generate tile (12x tiled grid_block: 120x120 pixels)
		$images['tile'] = self::generate_tile_image( $colors['secondary'], $colors['tertiary'], $block_width, $block_height );
		
		// Include other blocks that don't change
		$static_images = \MillionDollarScript\Classes\Data\DarkModeImages::get_dark_mode_images();
		$images['usr_res_block'] = $static_images['usr_res_block'];
		$images['usr_sel_block'] = $static_images['usr_sel_block']; 
		$images['usr_sol_block'] = $static_images['usr_sol_block'];
		
		return $images;
	}

	/**
	 * Get dark mode color settings from Options
	 * 
	 * @return array Associative array of color values
	 */
	private static function get_dark_mode_colors(): array {
		return [
			'primary'   => Options::get_option( 'dark_main_background', '#101216' ),
			'secondary' => Options::get_option( 'dark_secondary_background', '#14181c' ),
			'tertiary'  => Options::get_option( 'dark_tertiary_background', '#1d2024' ),
			'text'      => Options::get_option( 'dark_text_primary', '#ffffff' ),
			'border'    => Options::get_option( 'dark_border_color', '#1a1c21' ),
		];
	}

	/**
	 * Generate grid block image with secondary background and tertiary border
	 * 
	 * @param string $bg_color Background color (hex)
	 * @param string $border_color Border color (hex)
	 * @param int $width Image width
	 * @param int $height Image height
	 * @return string Base64 encoded PNG image
	 */
	private static function generate_grid_block( string $bg_color, string $border_color, int $width, int $height ): string {
		$image = imagecreate( $width, $height );
		
		// Convert hex colors to RGB
		$bg_rgb = self::hex_to_rgb( $bg_color );
		$border_rgb = self::hex_to_rgb( $border_color );
		
		// Allocate colors
		$bg = imagecolorallocate( $image, $bg_rgb[0], $bg_rgb[1], $bg_rgb[2] );
		$border = imagecolorallocate( $image, $border_rgb[0], $border_rgb[1], $border_rgb[2] );
		
		// Fill background
		imagefill( $image, 0, 0, $bg );
		
		// Draw 1px border on top and left
		imageline( $image, 0, 0, $width - 1, 0, $border ); // Top
		imageline( $image, 0, 0, 0, $height - 1, $border ); // Left
		
		return self::image_to_base64( $image );
	}

	/**
	 * Generate NFS block image with solid tertiary color
	 * 
	 * @param string $color Solid color (hex)
	 * @param int $width Image width
	 * @param int $height Image height
	 * @return string Base64 encoded PNG image
	 */
	private static function generate_nfs_block( string $color, int $width, int $height ): string {
		$image = imagecreate( $width, $height );
		
		// Convert hex color to RGB
		$rgb = self::hex_to_rgb( $color );
		
		// Allocate color
		$solid_color = imagecolorallocate( $image, $rgb[0], $rgb[1], $rgb[2] );
		
		// Fill with solid color
		imagefill( $image, 0, 0, $solid_color );
		
		return self::image_to_base64( $image );
	}

	/**
	 * Generate ordered block image (variation for usr_ord_block)
	 * 
	 * @param string $primary_color Primary color (hex)
	 * @param string $secondary_color Secondary color (hex) 
	 * @param int $width Image width
	 * @param int $height Image height
	 * @return string Base64 encoded PNG image
	 */
	private static function generate_ord_block( string $primary_color, string $secondary_color, int $width, int $height ): string {
		$image = imagecreate( $width, $height );
		
		// Convert hex colors to RGB
		$primary_rgb = self::hex_to_rgb( $primary_color );
		$secondary_rgb = self::hex_to_rgb( $secondary_color );
		
		// Allocate colors
		$primary = imagecolorallocate( $image, $primary_rgb[0], $primary_rgb[1], $primary_rgb[2] );
		$secondary = imagecolorallocate( $image, $secondary_rgb[0], $secondary_rgb[1], $secondary_rgb[2] );
		
		// Fill background with primary
		imagefill( $image, 0, 0, $primary );
		
		// Add diagonal pattern or dots for distinction
		for ( $x = 0; $x < $width; $x += 2 ) {
			for ( $y = 0; $y < $height; $y += 2 ) {
				if ( ( $x + $y ) % 4 === 0 ) {
					imagesetpixel( $image, $x, $y, $secondary );
				}
			}
		}
		
		return self::image_to_base64( $image );
	}

	/**
	 * Generate tile image (12x12 tiled grid blocks for 120x120 total)
	 * 
	 * @param string $bg_color Background color (hex)
	 * @param string $border_color Border color (hex)
	 * @param int $block_width Individual block width
	 * @param int $block_height Individual block height
	 * @return string Base64 encoded PNG image
	 */
	private static function generate_tile_image( string $bg_color, string $border_color, int $block_width, int $block_height ): string {
		$tiles_x = 12;
		$tiles_y = 12;
		$total_width = $tiles_x * $block_width;
		$total_height = $tiles_y * $block_height;
		
		$image = imagecreate( $total_width, $total_height );
		
		// Convert hex colors to RGB
		$bg_rgb = self::hex_to_rgb( $bg_color );
		$border_rgb = self::hex_to_rgb( $border_color );
		
		// Allocate colors
		$bg = imagecolorallocate( $image, $bg_rgb[0], $bg_rgb[1], $bg_rgb[2] );
		$border = imagecolorallocate( $image, $border_rgb[0], $border_rgb[1], $border_rgb[2] );
		
		// Fill background
		imagefill( $image, 0, 0, $bg );
		
		// Draw grid pattern
		for ( $x = 0; $x < $tiles_x; $x++ ) {
			for ( $y = 0; $y < $tiles_y; $y++ ) {
				$start_x = $x * $block_width;
				$start_y = $y * $block_height;
				
				// Draw top border for each tile
				imageline( $image, $start_x, $start_y, $start_x + $block_width - 1, $start_y, $border );
				// Draw left border for each tile  
				imageline( $image, $start_x, $start_y, $start_x, $start_y + $block_height - 1, $border );
			}
		}
		
		return self::image_to_base64( $image );
	}

	/**
	 * Convert hex color to RGB array
	 * 
	 * @param string $hex Hex color (e.g., '#1d2024' or '1d2024')
	 * @return array RGB values [r, g, b]
	 */
	private static function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		
		if ( strlen( $hex ) === 3 ) {
			$hex = str_repeat( substr( $hex, 0, 1 ), 2 ) .
			       str_repeat( substr( $hex, 1, 1 ), 2 ) .
			       str_repeat( substr( $hex, 2, 1 ), 2 );
		}
		
		return [
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) )
		];
	}

	/**
	 * Convert GD image resource to base64 PNG string
	 * 
	 * @param resource $image GD image resource
	 * @return string Base64 encoded PNG
	 */
	private static function image_to_base64( $image ): string {
		ob_start();
		imagepng( $image );
		$image_data = ob_get_contents();
		ob_end_clean();
		imagedestroy( $image );
		
		return base64_encode( $image_data );
	}

	/**
	 * Check if GD library is available
	 * 
	 * @return bool True if GD is available
	 */
	public static function is_gd_available(): bool {
		return extension_loaded( 'gd' ) && function_exists( 'imagecreate' );
	}

	/**
	 * Get fallback images if GD is not available
	 * 
	 * @param string $theme_mode Theme mode
	 * @return array Static fallback images
	 */
	public static function get_fallback_images( string $theme_mode ): array {
		if ( $theme_mode === 'dark' ) {
			return \MillionDollarScript\Classes\Data\DarkModeImages::get_dark_mode_images();
		}
		
		return \MillionDollarScript\Classes\Data\DarkModeImages::get_light_mode_images();
	}
} 