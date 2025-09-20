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

use MillionDollarScript\Classes\Ajax\Ajax;
use MillionDollarScript\Classes\System\Utility;

Utility::get_header();

global $post;
$post_id = $post instanceof WP_Post ? $post->ID : 0;

$popup_markup = '';
if ( $post_id ) {
	$popup_markup = Ajax::get_ad( $post_id, true );
}

$popup_markup = apply_filters( 'mds_pixel_single_popup_markup', $popup_markup, $post_id, $post );

?>
    <div class="mds-pixel-content" data-mds-pixel-id="<?php echo esc_attr( $post_id ); ?>">
        <?php do_action( 'mds_pixel_single_before_content', $post ); ?>

        <?php
        do_action( 'mds_pixel_single_before_popup', $post, $popup_markup, $post_id );

        if ( ! empty( $popup_markup ) ) {
            echo '<div class="mds-pixel-preview mds-pixel-preview-inline">';

            // Mirror tooltip output while still letting developers filter the markup.
            echo apply_filters( 'mds_pixel_single_popup_html', $popup_markup, $post_id, $post );

            echo '</div>';
        } else {
            do_action( 'mds_pixel_single_empty', $post, $post_id );
        }

        do_action( 'mds_pixel_single_after_popup', $post, $popup_markup, $post_id );
        ?>

        <?php do_action( 'mds_pixel_single_after_content', $post, $popup_markup, $post_id ); ?>
    </div>
<?php

Utility::get_footer();
