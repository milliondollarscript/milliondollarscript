<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.7
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

defined( 'ABSPATH' ) or exit;

/** @noinspection PhpUnused */

class _2_5_7 {

	public function upgrade( $version ): void {
		if ( version_compare( $version, '2.5.7', '<' ) ) {

			// Loop through each WooCommerce product
			$products = wc_get_products( array( 'status' => 'publish' ) );
			foreach ( $products as $product ) {
				$meta_values = get_post_custom( $product->get_id() );

				// Check for _milliondollarscript meta values set to yes
				if ( isset( $meta_values['_milliondollarscript'] ) && $meta_values['_milliondollarscript'][0] === 'yes' ) {

					// Loop through each variation and mark them as downloadable
					$variations = $product->get_available_variations();
					foreach ( $variations as $variation ) {
						$variation_id      = $variation['variation_id'];
						$variation_product = wc_get_product( $variation_id );
						$variation_product->set_downloadable( true );
						$variation_product->save();
					}
				}
			}
		}
	}
}
