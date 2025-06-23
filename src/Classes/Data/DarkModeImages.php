<?php

namespace MillionDollarScript\Classes\Data;

/**
 * Dark Mode Images Data Storage
 * 
 * Contains base64 encoded dark mode versions of grid images
 * that are automatically generated based on dark mode color settings.
 */
class DarkModeImages {

	/**
	 * Get base64 encoded dark mode images
	 * 
	 * @return array Associative array of image names and their base64 data
	 */
	public static function get_dark_mode_images(): array {
		return [
			'grid_block'      => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAAXNSR0IB2cksfwAAAARnQU1BAACxjwv8YQUAAAAgY0hSTQAAeiYAAICEAAD6AAAAgOgAAHUwAADqYAAAOpgAABdwnLpRPAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAB5JREFUGNNjlFVQYcANWBgYGL7/+IFLmokBLxi20gCflwNeTOag1AAAAABJRU5ErkJggg==',
			'nfs_block'       => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAAXNSR0IB2cksfwAAAARnQU1BAACxjwv8YQUAAAAgY0hSTQAAeiYAAICEAAD6AAAAgOgAAHUwAADqYAAAOpgAABdwnLpRPAAAAAlwSFlzAAALEwAACxMBAJqcGAAAABVJREFUGNNjlFVQYcANmBjwgpEqDQCB/wB12HJ9WgAAAABJRU5ErkJggg==',
			'tile'            => 'iVBORw0KGgoAAAANSUhEUgAAAHgAAAB4CAMAAAAOusbgAAAAAXNSR0IB2cksfwAAAARnQU1BAACxjwv8YQUAAAAgY0hSTQAAeiYAAICEAAD6AAAAgOgAAHUwAADqYAAAOpgAABdwnLpRPAAAAAZQTFRFHSAkFBgceYtwXQAAAAlwSFlzAAALEwAACxMBAJqcGAAAAGBJREFUaN7t1LENAAAERUH2X1plBUROpbviJy9i7bJv+AODwQ9g5QKDwcqlXGAwWLlsDAaDlQsMBiuXcoHBYOWyMRgMVi4bg8HKpVxgMFi5bAwGg5XLxmCwcikXGAy+CRfINy2R3HbKsgAAAABJRU5ErkJggg==',
			'usr_grid_block'  => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAAXNSR0IB2cksfwAAAARnQU1BAACxjwv8YQUAAAAgY0hSTQAAeiYAAICEAAD6AAAAgOgAAHUwAADqYAAAOpgAABdwnLpRPAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAB5JREFUGNNjlFVQYcANWBgYGL7/+IFLmokBLxi20gCflwNeTOag1AAAAABJRU5ErkJggg==',
			'usr_nfs_block'   => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAAXNSR0IB2cksfwAAAARnQU1BAACxjwv8YQUAAAAgY0hSTQAAeiYAAICEAAD6AAAAgOgAAHUwAADqYAAAOpgAABdwnLpRPAAAAAlwSFlzAAALEwAACxMBAJqcGAAAABVJREFUGNNjlFVQYcANmBjwgpEqDQCB/wB12HJ9WgAAAABJRU5ErkJggg==',
			'usr_ord_block'   => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAFElEQVR4nGP83+DAgBsw4ZEbwdIAJ/sB02xWjpQAAAAASUVORK5CYII=',
			'usr_res_block'   => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAE0lEQVR4nGP8/58BD2DCJzlypQF0BwISHGyJPgAAAABJRU5ErkJggg==',
			'usr_sel_block'   => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAE0lEQVR4nGNk+M+ABzDhkxy50gBALQETmXEDiQAAAABJRU5ErkJggg==',
			'usr_sol_block'   => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAEklEQVR4nGP8z4APMOGVHbHSAEEsAROxCnMTAAAAAElFTkSuQmCC',
		];
	}

	/**
	 * Get original light mode images from install.php
	 * 
	 * @return array Associative array of original image names and their base64 data
	 */
	public static function get_light_mode_images(): array {
		return [
			'grid_block'      => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAHklEQVR4nGO8cuUKA27AwsDAoK2tjUuaCY/W4SwNAJbvAxP1WmxKAAAAAElFTkSuQmCC',
			'nfs_block'       => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAFUlEQVR4nGP8//8/A27AhEduBEsDAKXjAxF9kqZqAAAAAElFTkSuQmCC',
			'tile'            => 'iVBORw0KGgoAAAANSUhEUgAAAHgAAAB4AQMAAAADqqSRAAAABlBMVEXW19b///9ZVCXjAAAAJklEQVR4nGNgQAP197///Y8gBpw/6r5R9426b9R9o+4bdd8wdB8AiRh20BqKw9IAAAAASUVORK5CYII=',
			'usr_grid_block'  => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAHklEQVR4nGO8cuUKA27AwsDAoK2tjUuaCY/W4SwNAJbvAxP1WmxKAAAAAElFTkSuQmCC',
			'usr_nfs_block'   => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAFUlEQVR4nGP8//8/A27AhEduBEsDAKXjAxF9kqZqAAAAAElFTkSuQmCC',
			'usr_ord_block'   => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAFElEQVR4nGP83+DAgBsw4ZEbwdIAJ/sB02xWjpQAAAAASUVORK5CYII=',
			'usr_res_block'   => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAE0lEQVR4nGP8/58BD2DCJzlypQF0BwISHGyJPgAAAABJRU5ErkJggg==',
			'usr_sel_block'   => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAE0lEQVR4nGNk+M+ABzDhkxy50gBALQETmXEDiQAAAABJRU5ErkJggg==',
			'usr_sol_block'   => 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAEklEQVR4nGP8z4APMOGVHbHSAEEsAROxCnMTAAAAAElFTkSuQmCC',
		];
	}

	/**
	 * Get the list of images that should be automatically updated for dark mode
	 * 
	 * @return array List of image names that require dark mode updates
	 */
	public static function get_updateable_images(): array {
		return [
			'grid_block',
			'nfs_block', 
			'tile',
			'usr_grid_block',
			'usr_nfs_block',
			'usr_ord_block'
		];
	}

	/**
	 * Get images by theme mode
	 * 
	 * @param string $theme_mode 'light' or 'dark'
	 * @return array Associative array of image names and their base64 data
	 */
	public static function get_images_by_theme( string $theme_mode ): array {
		if ( $theme_mode === 'dark' ) {
			return self::get_dark_mode_images();
		}
		
		return self::get_light_mode_images();
	}

	/**
	 * Check if an image should be updated for dark mode
	 * 
	 * @param string $image_name The name of the image to check
	 * @return bool True if the image should be updated for dark mode
	 */
	public static function should_update_for_dark_mode( string $image_name ): bool {
		return in_array( $image_name, self::get_updateable_images(), true );
	}
} 