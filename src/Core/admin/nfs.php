<?php
/*
 * @package       mds
 * @copyright     (C) Copyright 2022 Ryan Rhode, All rights reserved.
 * @author        Ryan Rhode, ryan@milliondollarscript.com
 * @version       2022-01-30 17:07:25 EST
 * @license       This program is free software; you can redistribute it and/or modify
 *        it under the terms of the GNU General Public License as published by
 *        the Free Software Foundation; either version 3 of the License, or
 *        (at your option) any later version.
 *
 *        This program is distributed in the hope that it will be useful,
 *        but WITHOUT ANY WARRANTY; without even the implied warranty of
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *        GNU General Public License for more details.
 *
 *        You should have received a copy of the GNU General Public License along
 *        with this program;  If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 *
 *  * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *        Million Dollar Script
 *        A pixel script for selling pixels on your website.
 *
 *        For instructions see README.txt
 *
 *        Visit our website for FAQs, documentation, a list team members,
 *        to post any bugs or feature requests, and a community forum:
 *        https://milliondollarscript.com/
 *
 */

require_once __DIR__ . "/../include/init.php";
require( 'admin_common.php' );

@ini_set( 'max_execution_time', 10000 );
@ini_set( 'max_input_vars', 10002 );

global $f2;

$BID = $f2->bid();

$banner_data = load_banner_constants( $BID );

$imagine = new Imagine\Gd\Imagine();

// load blocks
$block_size  = new Imagine\Image\Box( $banner_data['BLK_WIDTH'], $banner_data['BLK_HEIGHT'] );
$palette     = new Imagine\Image\Palette\RGB();
$color       = $palette->color( '#000', 0 );
$zero_point  = new Imagine\Image\Point( 0, 0 );
$blank_block = $imagine->create( $block_size, $color );

$default_nfs_block = $blank_block->copy();
$tmp_block         = $imagine->load( $banner_data['USR_NFS_BLOCK'] );
$tmp_block->resize( $block_size );
$default_nfs_block->paste( $tmp_block, $zero_point );
$data = base64_encode( $default_nfs_block->get( "png", array( 'png_compression_level' => 9 ) ) );

if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'save' ) {
	//$sql = "delete from blocks where status='nfs' AND banner_id=$BID ";
	//mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);

	if ( isset( $_POST['addnfs'] ) && ( ! empty( $_POST['addnfs'] || $_POST['addnfs'] == 0 ) ) ) {
		$addnfs = json_decode( html_entity_decode( stripslashes( $_POST['addnfs'] ) ) );
	} else {
		unset( $addnfs );
	}

	if ( isset( $_POST['remnfs'] ) && ( ! empty( $_POST['remnfs'] ) || $_POST['remnfs'] == 0 ) ) {
		$remnfs = json_decode( html_entity_decode( stripslashes( $_POST['remnfs'] ) ) );
	} else {
		unset( $remnfs );
	}

	$cell = $x = $y = 0;
	for ( $i = 0; $i < $banner_data['G_HEIGHT']; $i ++ ) {
		$x = 0;
		for ( $j = 0; $j < $banner_data['G_WIDTH']; $j ++ ) {
			if ( isset( $addnfs ) && in_array( $cell, $addnfs ) ) {
				$sql = "REPLACE INTO " . MDS_DB_PREFIX . "blocks (block_id, status, x, y, image_data, banner_id, click_count, alt_text) VALUES ({$cell}, 'nfs', {$x}, {$y}, '{$data}', {$BID}, 0, '')";
				mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
			}
			if ( isset( $remnfs ) && in_array( $cell, $remnfs ) ) {
				$sql = "DELETE FROM " . MDS_DB_PREFIX . "blocks WHERE status='nfs' AND banner_id={$BID} AND block_id={$cell}";
				mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
			}
			$x = $x + $banner_data['BLK_WIDTH'];
			$cell ++;
		}
		$y = $y + $banner_data['BLK_HEIGHT'];
	}
	echo "Success!";
	exit();
} else if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'reset' ) {
	$sql = "DELETE FROM " . MDS_DB_PREFIX . "blocks WHERE status='nfs' AND banner_id=$BID";
	mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
	echo "Success!";
	exit();
}

$grid_img = \MillionDollarScript\Classes\Utility::get_upload_url() . 'grids/grid' . $BID;
if ( OUTPUT_JPEG == 'Y' ) {
	$grid_img .= '.jpg';
} else if ( OUTPUT_JPEG == 'N' ) {
	$grid_img .= '.png';
} else if ( ( OUTPUT_JPEG == 'GIF' ) ) {
	$grid_img .= '.gif';
}

?>
<script>
	$(function () {
		if (!window.mds_admin_loading) {
			window.mds_admin_loading = true;
		} else {
			return;
		}

		let addnfs = [];
		let remnfs = [];

		const $document = $(document);
		const $grid = $('.grid');

		$('<img/>').attr('src', '<?php echo $grid_img; ?>').on('load', function () {
			$(this).remove();
			$('.loading').remove();
			$grid.css('background-image', 'url("<?php echo $grid_img; ?>")');
			window.mds_admin_loading = false;
		});

		function processBlock(block) {
			let $block = $(block);
			let blockid;
			if ($block.hasClass("nfs")) {
				blockid = $block.attr("data-block");
				addnfs.push(blockid);
				let index = remnfs.indexOf(blockid);
				if (index !== -1) {
					remnfs.splice(index, 1);
				}
			} else if ($block.hasClass("free")) {
				blockid = $block.attr("data-block");
				remnfs.push(blockid);
				let index = addnfs.indexOf(blockid);
				if (index !== -1) {
					addnfs.splice(index, 1);
				}
			}
		}

		function toggleBlock(el) {
			let $block = $(el);
			if ($block.hasClass('nfs')) {
				$block.removeClass('nfs');
			} else {
				$block.addClass('nfs');
			}
			if ($block.hasClass('free')) {
				$block.removeClass('free');
			} else {
				$block.addClass('free');
			}
		}

		// https://github.com/Simonwep/selection
		const selection = new SelectionArea({
			selectables: ['span.block'],
			startareas: ['.grid'],
			boundaries: ['.grid'],
		});

		selection.off('beforestart').off('move').off('stop');
		selection.on('beforestart', e => {
			for (const el of e.store.stored) {
				$(el).removeClass('selected');
			}
			selection.clearSelection(true);

			// }).on('beforedrag', e => {
			// }).on('start', e => {

		}).on('move', e => {
			for (const el of e.store.changed.added) {
				$(el).addClass('selected');
			}

			for (const el of e.store.changed.removed) {
				$(el).removeClass('selected');
			}

		}).on('stop', e => {
			let els = e.store.selected;
			for (const el of els) {
				toggleBlock(el);
				processBlock(el);
			}

		});

		// selection object must be destroyed when the page is loaded
		$(window).off('mds_admin_page_loaded');
		$(window).on('mds_admin_page_loaded', function () {
			selection.destroy();
		});

		let $save = $('.save');
		let $reset = $('.reset');

		let submitting = false;
		$document.off('click', '.save');
		$document.on('click', '.save', function (e) {
			e.preventDefault();
			e.stopPropagation();
			$grid.append('<img class="loading" src="../images/ajax-loader.gif" alt="" />');
			$save.prop('disabled', true);
			$reset.prop('disabled', true);

			if (!submitting) {
				submitting = true;

				$.post("nfs.php", {
					BID: <?php echo $BID; ?>,
					action: "save",
					addnfs: JSON.stringify(addnfs),
					remnfs: JSON.stringify(remnfs)
				}).done(function (data) {
					$('.loading').hide(function () {
						$(this).remove();
						$('<span class="message">' + data + '</span>').insertAfter('.save').fadeOut(10000, function () {
							$(this).remove();
						});
					});
					$save.prop('disabled', false);
					$reset.prop('disabled', false);
					submitting = false;
				});
			}

			return false;
		});

		function confirm_dialog(title, message) {
			$('<div id="confirm-dialog">' + message + '</div>').appendTo('body');
			$('#confirm-dialog').dialog({
				title: title,
				modal: true,
				width: 'auto',
				resizable: false,
				position: {my: "center top", at: "center top+1%", of: window},
				buttons: {
					Yes: function () {

						$grid.append('<img class="loading" src="../images/ajax-loader.gif" alt="" />');

						$.post("nfs.php", {
							BID: <?php echo $BID; ?>,
							action: "reset"
						}).done(function (data) {
							$('.loading').hide(function () {
								$(this).remove();

								$('<span class="message">' + data + '</span>').insertAfter('.reset').fadeOut(10000, function () {
									$(this).remove();
								});
							});

							$save.prop('disabled', false);
							$reset.prop('disabled', false);

							$(".block.nfs,.block.selected").removeClass("selected").removeClass("nfs").addClass("free");

							selection.clearSelection();
						});

						$(this).dialog("close");
					},
					No: function () {
						$(this).dialog("close");

						$save.prop('disabled', false);
						$reset.prop('disabled', false);
					}
				},
				close: function () {
					$(this).remove();

					$save.prop('disabled', false);
					$reset.prop('disabled', false);
				}
			});
		}

		$document.off('click', '.reset');
		$document.on('click', '.reset', function (e) {
			e.preventDefault();
			e.stopPropagation();
			$save.prop('disabled', true);
			$reset.prop('disabled', true);

			confirm_dialog('Reset?', 'This will unselect all Not For Sale blocks. Are you sure you want to do this?');

			return false;
		});
	});
</script>
<style>
    <?php
	    $grid_background = "";
		if(file_exists(BASE_PATH . "/" . BANNER_DIR . "main$BID.png")) {
			$grid_background = 'background: url("' . $grid_img . '") no-repeat center center;
			';
	    }
	?>
	.grid {
		position: relative;
    <?php echo $grid_background; ?> background-size: contain;
		z-index: 0;
		width: <?php echo $banner_data['G_WIDTH']*$banner_data['BLK_WIDTH']; ?>px;
		height: <?php echo $banner_data['G_HEIGHT']*$banner_data['BLK_HEIGHT']; ?>px;
		user-select: none;
	}

	.block_row {
		clear: both;
		display: block;
	}

	.block {
		white-space: nowrap;
		width: <?php echo $banner_data['BLK_WIDTH']; ?>px;
		height: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
		float: left;
		opacity: 0.5;
		filter: alpha(opacity=50);
		background-size: contain !important;
	}

	.sold {
		background: url("../images/sold_block.png") no-repeat;
	}

	.reserved {
		background: url("../images/reserved_block.png") no-repeat;
	}

	.nfs {
		background: url('data:image/png;base64,<?php echo $data; ?>') no-repeat;
		cursor: pointer;
	}

	.ordered {
		background: url("../images/ordered_block.png") no-repeat;
	}

	.ordered {
		background: url("../images/ordered_block.png") no-repeat;
	}

	.onorder {
		background: url("../images/not_for_sale_block.png") no-repeat;
	}

	.free {
		background: url("../images/block.png") no-repeat;
		cursor: pointer;
	}

	.loading {
		width: 32px;
		height: 32px;
		position: absolute;
		top: 5%;
		left: calc(50% - 16px);
		z-index: 10000;
	}

	.selection-area {
		background: rgba(254, 202, 64, 0.33);
		border: 1px solid rgba(135, 110, 42, 0.33);
	}

	.selected {
		outline: 1px solid rgba(0, 0, 0, 0.45);
	}
</style>

<div class="outer_box">
    <p>
        Here you can mark blocks to be not for sale. You can drag to select an area. Click 'Save' when done.
    </p>
    (Note: If you have a background image, the image is blended in using the browser's built-in filter - your alpha channel is ignored on this page)
    <hr>
	<?php
	$sql = "Select * from " . MDS_DB_PREFIX . "banners ";
	$res = mysqli_query( $GLOBALS['connection'], $sql );
	?>
    <form name="bidselect" method="post" action="nfs.php">
        <label>
            Select grid:
            <select name="BID" onchange="mds_submit(this)">
                <option></option>
				<?php
				while ( $row = mysqli_fetch_array( $res ) ) {
					if ( ( $row['banner_id'] == $BID ) && ( $BID != 'all' ) ) {
						$sel = 'selected';
					} else {
						$sel = '';
					}
					echo '
                <option
                ' . $sel . ' value=' . $row['banner_id'] . '>' . $row['name'] . '</option>';
				}
				?>
            </select>
        </label>
    </form>
    <hr>
	<?php
	if ( $BID != '' ) {
	$sql    = "show columns from " . MDS_DB_PREFIX . "blocks ";
	$result = mysqli_query( $GLOBALS['connection'], $sql );
	while ( $row = mysqli_fetch_array( $result ) ) {
		if ( $row['Field'] == 'status' ) {
			if ( strpos( $row['Type'], 'nfs' ) == 0 ) {
				$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "blocks` CHANGE `status` `status` SET( 'reserved', 'sold', 'free', 'ordered', 'nfs' ) NOT NULL ";
				mysqli_query( $GLOBALS['connection'], $sql ) or die ( "<p><b>CANNOT UPGRADE YOUR DATABASE!<br>Please run the follwoing query manually from PhpMyAdmin:</b><br>$sql<br>" );
			}
		}
	}
	$blocks = [];
	$sql    = "SELECT `block_id`, `status`, `user_id` FROM `" . MDS_DB_PREFIX . "blocks` WHERE `banner_id`=" . intval( $BID );
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	while ( $row = mysqli_fetch_array( $result ) ) {
		$blocks[ $row["block_id"] ] = $row['status'];
	}
	?>
    <div class="container">
        <input class="save" type="submit" value='Save Not for Sale'/>
        <input class="reset" type="submit" value='Reset'/>
        <div class="grid">
            <img class="loading" src="../images/ajax-loader.gif" alt=""/>
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
<?php } ?>
