<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.6
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

use Carbon_Fields\Field;

defined( 'ABSPATH' ) or exit;

class BlockFields {

	protected static array $fields;
	// private static int $counter = 0;

	public static function get_fields(): array {
		$fields = [

			// Block preview shown when adding a block in WP admin
			Field::make( 'block_preview', MDS_PREFIX . 'preview', Language::get( 'Block Preview' ) )
			     ->set_html( self::get_preview_html() ),

			// Grid id
			Field::make( 'text', MDS_PREFIX . 'id', Language::get( 'Grid id' ) )
			     ->set_default_value( '1' )
			     ->set_attribute( 'type', 'number' )
			     ->set_help_text( Language::get( 'Input the MDS grid id here.' ) ),

			// Alignment
			Field::make( 'select', MDS_PREFIX . 'align', Language::get( 'Alignment' ) )
			     ->set_default_value( 'center' )
			     ->set_options( array(
				     'left'   => 'Left',
				     'right'  => 'Right',
				     'center' => 'Center',
			     ) )
			     ->set_help_text( Language::get( 'Align to the left, right or center.' ) ),

			// Width
			Field::make( 'text', MDS_PREFIX . 'width', Language::get( 'Width' ) )
			     ->set_default_value( '1000px' )
			     ->set_help_text( Language::get( 'Width of the iframe. Examples: 1000px or 100% or auto' ) ),

			// Height
			Field::make( 'text', MDS_PREFIX . 'height', Language::get( 'Height' ) )
			     ->set_default_value( '1000px' )
			     ->set_help_text( Language::get( 'Height of the iframe. Examples: 1000px or 100% or auto' ) ),

			// Type
			Field::make( 'select', MDS_PREFIX . 'type', Language::get( 'Type' ) )
			     ->set_default_value( 'grid' )
			     ->set_options( array(
				     'grid'     => 'Grid',
				     'stats'    => 'Stats box',
				     'list'     => 'Ads List',
				     'users'    => 'Users/Buy Page',
			     ) )
			     ->set_help_text( Language::get( 'Type of MDS component to load.' ) ),
		];

		self::$fields = apply_filters( 'mds_block_fields', $fields, MDS_PREFIX );

		return self::$fields;
	}

	public static function display( $fields ): void {
		$defaults = Shortcode::defaults();
		$values   = array();

		foreach ( $defaults as $key => $value ) {
			if ( array_key_exists( MDS_PREFIX . $key, $fields ) ) {
				$values[ $key ] = $fields[ MDS_PREFIX . $key ];
			} else {
				$values[ $key ] = $value;
			}
		}

		// echo do_shortcode( sprintf( '[milliondollarscript id="%d" display_method="%s" align="%s" width="%s" height="%s" mds="%s" lang="%s" type="%s" gateway="%s" display="%s"/]',
		echo do_shortcode( sprintf( '[milliondollarscript id="%d" align="%s" width="%s" height="%s" type="%s"/]',
			$values['id'],
			$values['align'],
			$values['width'],
			$values['height'],
			$values['type'],
		) );
	}

	/**
	 * Returns the HTML for the block preview field.
	 *
	 * @return bool|string
	 */
	private static function get_preview_html(): bool|string {
        return "<img src='" . MDS_CORE_URL . "images/bg-main.gif' />";
	}

	// TODO: Add preview for Show Preview eye icon on block
	/*	self::$counter ++;

		ob_start();
		$ob_level = ob_get_level();

		$output = '';

		// TODO: this is duplicated in some other places
		// TODO: esc_js or something required?
		if ( ! isset( $GLOBALS['mds_js_loaded'] ) ) {
			$GLOBALS['mds_js_loaded'] = true;

			global $f2;
			$BID         = $f2->bid();
			$banner_data = load_banner_constants( $BID );

			$tooltips = \MillionDollarScript\Classes\Config::get( 'ENABLE_MOUSEOVER' );

			?>
			<script>
				const MDSTEST = 1;
				console.log("TEST1");
				jQuery(function () {
					if (window.mds_js_loaded !== true) {
						window.mds_js_loaded = true;

						<?php if($tooltips == 'POPUP') { ?>
						jQuery('<link/>', {rel: 'stylesheet', href: '<?php echo MDS_CORE_URL; ?>css/tippy/light.css'}).appendTo('head');
						<?php } ?>
						jQuery('<link/>', {rel: 'stylesheet', href: '<?php echo MDS_BASE_URL; ?>src/Assets/css/mds.css?ver=<?php echo filemtime( MDS_BASE_PATH . "src/Assets/css/mds.css" ); ?>'}).appendTo('head');

						<?php if($tooltips == 'POPUP') { ?>
						jQuery.getScript('<?php echo MDS_CORE_URL; ?>js/third-party/popper.js', function () {
							jQuery.getScript('<?php echo MDS_CORE_URL; ?>js/third-party/tippy-bundle.umd.js', function () {
								<?php } ?>
								jQuery.getScript('<?php echo MDS_CORE_URL; ?>js/third-party/image-scale.min.js', function () {
									jQuery.getScript('<?php echo MDS_CORE_URL; ?>js/third-party/image-map.js', function () {
										jQuery.getScript('<?php echo MDS_CORE_URL; ?>js/third-party/contact.nomodule.min.js', function () {
											window.MDS = {
												ajax: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
												publishurl: '<?php echo \MillionDollarScript\Classes\Functions::get_page_url( 'manage' ); ?>',
												paymenturl: '<?php echo \MillionDollarScript\Classes\Functions::get_page_url( 'payment' ); ?>',
												wp: '<?php echo get_site_url(); ?>',
												winWidth: parseInt('<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>', 10),
												winHeight: parseInt('<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>', 10),
												time: '<?php echo time(); ?>',
												MDS_CORE_URL: '<?php echo MDS_CORE_URL;?>',
												REDIRECT_SWITCH: '<?php echo \MillionDollarScript\Classes\Config::get( 'REDIRECT_SWITCH' ); ?>',
												REDIRECT_URL: '<?php echo \MillionDollarScript\Classes\Config::get( 'REDIRECT_URL' ); ?>',
												ENABLE_MOUSEOVER: '<?php echo \MillionDollarScript\Classes\Config::get( 'ENABLE_MOUSEOVER' ); ?>',
												BID: parseInt('<?php echo $BID; ?>', 10),
												MDS_PREFIX: '<?php echo MDS_PREFIX; ?>',
											};
											jQuery.getScript('<?php echo MDS_CORE_URL; ?>js/mds.js?ver=<?php echo filemtime( MDS_CORE_PATH . 'js/mds.js' ); ?>', function () {
											});
										});
									});
								});
								<?php if($tooltips == 'POPUP') { ?>
							});
						});
						<?php } ?>

					}
				});
			</script>
			<?php
			while ( ob_get_level() > $ob_level ) {
				ob_end_clean();
			}

			$output .= ob_get_clean();
		}

		ob_start();
		$ob_level = ob_get_level();

		?>
		<script id="mds-fields-script-<?php echo self::$counter; ?>">
			jQuery(function () {
				console.log("MDSTEST", MDSTEST);

				new window.mds_ajax(
					undefined,
					undefined,
					undefined,
					undefined,
					undefined,
					undefined,
					parseInt(<?php echo self::$counter; ?>, 10),
				);
			});
		</script>
		<?php

		while ( ob_get_level() > $ob_level ) {
			ob_end_clean();
		}

		$output .= ob_get_clean();


	return $output;*/

}