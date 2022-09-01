<?php

/**
 * Million Dollar Script Two
 *
 * @version 2.3.6
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

use Carbon_Fields\Block as CFBlock;
use Carbon_Fields\Field;

defined( 'ABSPATH' ) or exit;

class Block {
	public const prefix = 'milliondollarscript_';

	public static function load() {
//		'id'             => 1,
//		'display_method' => 'ajax',
//		'align'          => 'center',
//		'width'          => '1000px',
//		'height'         => 'auto',
//		'mds'            => $mds_url,
//		'lang'           => 'EN',
//		'type'           => 'grid',
//		'gateway'        => '',
//		'display'        => ''

		CFBlock::make( __( 'Million Dollar Script' ) )
		       ->set_icon( 'grid-view' )
		       ->add_fields( array(
			       // Grid id
			       Field::make( 'text', self::prefix . 'id', __( 'Grid id' ) )
			            ->set_default_value( '1' )
			            ->set_help_text( __( 'Input the MDS grid id here.', 'milliondollarscript' ) ),

			       // Display method
			       Field::make( 'select', self::prefix . 'display_method', __( 'AJAX or iframe' ) )
			            ->set_default_value( 'iframe' )
			            ->set_options( array(
				            'iframe' => 'iframe',
				            'ajax'   => 'AJAX',
			            ) )
			            ->set_help_text( __( 'Display using AJAX or iframe. Note: Currently AJAX only works for grid, list and stats box.', 'milliondollarscript' ) ),

			       // Alignment
			       Field::make( 'select', self::prefix . 'align', __( 'Alignment' ) )
			            ->set_default_value( 'center' )
			            ->set_options( array(
				            'left'   => 'Left',
				            'right'  => 'Right',
				            'center' => 'Center',
			            ) )
			            ->set_help_text( __( 'Align to the left, right or center.', 'milliondollarscript' ) ),

			       // Width
			       Field::make( 'text', self::prefix . 'width', __( 'Width' ) )
			            ->set_default_value( '1000px' )
			            ->set_help_text( __( 'Width of the iframe. Examples: 1000px or 100% or auto', 'milliondollarscript' ) ),

			       // Height
			       Field::make( 'text', self::prefix . 'height', __( 'Height' ) )
			            ->set_default_value( '1000px' )
			            ->set_help_text( __( 'Height of the iframe. Examples: 1000px or 100% or auto', 'milliondollarscript' ) ),

			       // Language code
			       Field::make( 'text', self::prefix . 'lang', __( 'Language code' ) )
			            ->set_default_value( 'EN' )
			            ->set_help_text( __( 'The language code from MDS language settings. Examples: EN, ES, RU, etc.', 'milliondollarscript' ) ),

			       // Type
			       Field::make( 'select', self::prefix . 'type', __( 'Type' ) )
			            ->set_default_value( 'grid' )
			            ->set_options( array(
				            'grid'     => 'Grid',
				            'stats'    => 'Stats box',
				            'list'     => 'Ads List',
				            'users'    => 'Users/Buy Page',
				            'thankyou' => 'Thank You Page',
				            'validate' => 'Validate Account Page',
			            ) )
			            ->set_help_text( __( 'Type of MDS component to load.', 'milliondollarscript' ) ),

			       // Payment gateway
			       Field::make( 'select', self::prefix . 'gateway', __( 'Payment Gateway' ) )
			            ->set_default_value( 'PayPal' )
			            ->set_options( array(
				            '_2CO'         => '2Checkout',
				            'authorizeNet' => 'Authorize.net',
				            'bank'         => 'Bank',
				            'check'        => 'Check / Money Order',
				            'CoinPayments' => 'CoinPayments',
				            'external'     => 'External/Payment/WooCommerce',
				            'PayPal'       => 'PayPal',
				            'VoguePay'     => 'VoguePay',
			            ) )
			            ->set_conditional_logic( array(
				            'relation' => 'AND',
				            array(
					            'field'   => self::prefix . 'type',
					            'value'   => 'thankyou',
					            'compare' => '=',
				            )
			            ) )
			            ->set_help_text( __( 'This value must match the class name/m parameter of the MDS payment module used to pay. You must have a unique thank-you page for each enabled MDS payment module.', 'milliondollarscript' ) ),

			       // Display URL
			       Field::make( 'text', self::prefix . 'display', __( 'Display Link' ) )
			            ->set_default_value( '' )
			            ->set_conditional_logic( array(
				            'relation' => 'AND',
				            array(
					            'field'   => self::prefix . 'display_method',
					            'value'   => 'iframe',
					            'compare' => '=',
				            )
			            ) )
			            ->set_help_text( __( 'The link to start on. Only affects iframe display method. Each Type has different options. These are not currently listed anywhere so if you want to know You can check src/Classes/Shortcode.php in the MDS integration plugin or ask me. For Users/Buy Page the default is /users? but you can change it to /users/select.php?', 'milliondollarscript' ) ),

		       ) )
		       ->set_render_callback( function ( $fields, $attributes, $inner_blocks ) {
			       $defaults = Shortcode::defaults();
			       $values   = array();

			       foreach ( $defaults as $key => $value ) {
				       if ( array_key_exists( self::prefix . $key, $fields ) ) {
					       $values[ $key ] = $fields[ self::prefix . $key ];
				       } else {
					       $values[ $key ] = $defaults[ $key ];
				       }
			       }

			       echo do_shortcode( sprintf( '[milliondollarscript id="%d" display_method="%s" align="%s" width="%s" height="%s" mds="%s" lang="%s" type="%s" gateway="%s" display="%s"/]',
				       $values['id'],
				       $values['display_method'],
				       $values['align'],
				       $values['width'],
				       $values['height'],
				       $values['mds'],
				       $values['lang'],
				       $values['type'],
				       $values['gateway'],
				       $values['display']
			       ) );
		       } );
	}
}
