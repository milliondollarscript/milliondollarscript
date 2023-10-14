<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.2
 * @author      Ryan Rhode
 * @copyright   (C) 2023, Ryan Rhode
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

use Carbon_Fields\Block as CFBlock;

defined( 'ABSPATH' ) or exit;

class Block {

	private static \Carbon_Fields\Container\Block_Container $cfblock;

	public static function load(): void {
		$mdsfields     = new BlockFields();
		self::$cfblock = CFBlock::make( Language::get( 'Million Dollar Script' ) )
		                        ->set_icon( 'grid-view' )
		                        ->set_category( 'embed' )
		                        ->set_keywords( [ 'mds', 'million', 'dollar', 'script', 'pixel' ] )
		                        ->set_mode( 'both' )
		                        ->add_fields( $mdsfields->get_fields() )
		                        ->set_render_callback( function ( $fields, $attributes, $inner_blocks ) use ( $mdsfields ) {
			                        $mdsfields->display( $fields );
		                        } );
	}

	public static function register_style(): void {
		wp_register_style( MDS_PREFIX . 'admin-block-css', MDS_BASE_URL . 'src/Assets/css/mds.css', array(), filemtime( MDS_BASE_PATH . 'src/Assets/css/mds.css' ) );
		self::$cfblock->set_editor_style( MDS_PREFIX . 'admin-block-css' );
	}
}
