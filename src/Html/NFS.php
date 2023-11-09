<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.4
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

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use MillionDollarScript\Classes\Utility;

defined( 'ABSPATH' ) or exit;

@ini_set( 'max_execution_time', 10000 );
@ini_set( 'max_input_vars', 10002 );

global $wpdb, $f2;

$BID = $f2->bid();

$banner_data = load_banner_constants( $BID );

?>
    <div class="outer_box">
    <p>
        Here you can mark blocks to be not for sale. You can drag to select an area. Click 'Save' when done.
    </p>
    (Note: If you have a background image, the image is blended in using the browser's built-in filter - your alpha channel is ignored on this page)
    <hr>
<?php

$results = $wpdb->get_results( "SELECT * FROM `" . MDS_DB_PREFIX . "banners`", ARRAY_A );
?>
    <form name="bidselect" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'mds_nfs_grid', 'mds_nfs_nonce' ); ?>
        <input type="hidden" name="action" value="mds_nfs_grid"/>
        <label>
            Select grid:
            <!--            <select id="mds-nfs-grid" name="BID" onchange="mds_submit(this)">-->
            <select id="mds-nfs-grid" name="BID">
                <option></option>
				<?php
				foreach ( $results as $result ) {
					if ( ( $result['banner_id'] == $BID ) && ( $BID != 'all' ) ) {
						$sel = 'selected';
					} else {
						$sel = '';
					}
					echo '
                        <option
                        ' . $sel . ' value=' . $result['banner_id'] . '>' . $result['name'] . '</option>';
				}
				?>
            </select>
        </label>
    </form>
    <hr>
<?php
if ( $BID != '' ) {
	$blocks  = [];
	$sql     = $wpdb->prepare( "SELECT `block_id`, `status`, `user_id` FROM `" . MDS_DB_PREFIX . "blocks` WHERE `banner_id`=%d",
		$BID
	);
	$results = $wpdb->get_results( $sql, ARRAY_A );
	foreach ( $results as $result ) {
		$blocks[ $result["block_id"] ] = $result['status'];
	}

	$grid_img = Utility::get_upload_url() . 'grids/grid' . $BID;

	$grid_ext = \MillionDollarScript\Classes\Utility::get_file_extension();

	$grid_img .= '.' . $grid_ext;

	$grid_file = Utility::get_upload_path() . 'grids/grid' . $BID . '.' . $grid_ext;

	if ( ! file_exists( $grid_file ) ) {
		process_image( $BID );
		publish_image( $BID );
		process_map( $BID );
	}

	// Add modification time
	$grid_img .= "?v=" . filemtime( $grid_file );

	$grid_background = "";
	if ( file_exists( $grid_file ) ) {
		$grid_background = "background: url('$grid_img') no-repeat center center;";
	}

	$imagine = new Imagine();

	// load blocks
	$block_size  = new Box( $banner_data['BLK_WIDTH'], $banner_data['BLK_HEIGHT'] );
	$palette     = new RGB();
	$color       = $palette->color( '#000', 0 );
	$zero_point  = new Point( 0, 0 );
	$blank_block = $imagine->create( $block_size, $color );

	$usr_nfs_block = $imagine->load( $banner_data['USR_NFS_BLOCK'] );

	$data_covered = ' data-covered="false"';
	if ( $banner_data['NFS_COVERED'] == "N" ) {
		$default_nfs_block = $blank_block->copy();
		$usr_nfs_block->resize( $block_size );
		$default_nfs_block->paste( $usr_nfs_block, $zero_point );
		$nfs_block_data = base64_encode( $default_nfs_block->get( "png", array( 'png_compression_level' => 9 ) ) );
		?>
        <style>
			.nfs {
				background: url('data:image/png;base64,<?php echo $nfs_block_data; ?>') no-repeat;
				cursor: pointer;
			}
        </style>
		<?php
	} else {
		$nfs_block      = $imagine->load( $banner_data['NFS_BLOCK'] );
		$nfs_block_data = base64_encode( $nfs_block->get( "png", array( 'png_compression_level' => 9 ) ) );

		$data_covered = ' data-covered="true" data-width="' . $banner_data['BLK_WIDTH'] . '" data-height="' . $banner_data['BLK_HEIGHT'] . '"';
		?>
        <style>
			.selection-area,
			.nfs-covered {
				background: url('data:image/png;base64,<?php echo $nfs_block_data; ?>') no-repeat center/contain;
				cursor: pointer;
			}

			.nfs {
				cursor: pointer;
			}
        </style>
		<?php
	}
	?>
    <div class="container">
        <input class="save" type="submit" value='Save Not for Sale'/>
        <input class="reset" type="submit" value='Reset'/>
        <div class="grid" style="<?php echo esc_attr( $grid_background ); ?>"<?php echo $data_covered; ?>>
            <img class="loading" src="<?php echo MDS_BASE_URL . 'src/Assets/images/ajax-loader.gif'; ?>" alt=""/>
			<?php
			$cell = "0";
			for ( $i = 0; $i < $banner_data['G_HEIGHT']; $i ++ ) {
				echo "<div class='block_row'>";
				for ( $j = 0; $j < $banner_data['G_WIDTH']; $j ++ ) {
					if ( isset( $blocks[ $cell ] ) ) {
						switch ( $blocks[ $cell ] ) {
							case 'sold':
								echo '<span class="block sold" data-block="' . $cell . '"></span>';
								break;
							case 'reserved':
								echo '<span class="block reserved" data-block="' . $cell . '"></span>';
								break;
							case 'nfs':
								echo '<span class="block nfs" data-block="' . $cell . '"></span>';
								break;
							case 'ordered':
								echo '<span class="block ordered" data-block="' . $cell . '"></span>';
								break;
							case 'onorder':
								echo '<span class="block onorder" data-block="' . $cell . '"></span>';
								break;
							case 'free':
							case '':
								echo '<span class="block free" data-block="' . $cell . '"></span>';
						}
					} else {
						echo '<span class="block free" data-block="' . $cell . '"></span>';
					}
					$cell ++;
				}
				echo '</div>
                                ';
			}
			?>
        </div>
        <input class="save" type="submit" value='Save Not for Sale'/>
    </div>
    </div>
	<?php
}