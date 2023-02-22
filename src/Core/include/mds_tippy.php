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

if ( ENABLE_MOUSEOVER === 'NO' ) {
	return;
}

global $f2;
$BID         = $f2->bid();
$banner_data = load_banner_constants( $BID );
?>
<div class="tooltip-source">
    <img src="<?php echo BASE_HTTP_PATH; ?>images/periods.gif" alt=""/>
</div>
<script>
	const mouseover_box = {
		winWidth: parseInt('<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>', 10),
		winHeight: parseInt('<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>', 10),
		time: '<?php echo time(); ?>',
		BASE_HTTP_PATH: '<?php echo BASE_HTTP_PATH;?>',
		REDIRECT_SWITCH: '<?php echo REDIRECT_SWITCH; ?>',
		REDIRECT_URL: '<?php echo REDIRECT_URL; ?>',
		BID: parseInt('<?php echo $BID; ?>', 10)
	}
	jQuery(document).on('click', 'a.list-link', function (e) {
		e.preventDefault();
		e.stopPropagation();

	});

	jQuery(function () {
		defer('Popper', () => {
			defer('tippy', () => {
				const defaultContent = jQuery('.tooltip-source').html();
				const isIOS = /iPhone|iPad|iPod/.test(navigator.platform);

				let delay = 50;
				if(MDS.TOOLTIP_TRIGGER === 'mouseenter') {
					delay = 400;
				}

				window.tippy_instance = tippy('a.list-link', {
					theme: 'light',
					content: defaultContent,
					duration: 50,
					delay: delay,
					trigger: MDS.TOOLTIP_TRIGGER,
					allowHTML: true,
					followCursor: 'initial',
					hideOnClick: true,
					interactive: true,
					maxWidth: 350,
					placement: 'auto',
					touch: true,
					appendTo: 'parent',
					onCreate(instance) {
						instance._isFetching = false;
						instance._content = null;
						instance._error = null;
						window.tippy_instance = instance;
					},
					onShow(instance) {
						if (instance._isFetching || instance._content || instance._error) {
							return;
						}

						if (isIOS) {
							jQuery(instance.reference).click();
						}

						instance._isFetching = true;

						const data = jQuery(instance.reference).data('data');

						let ajax_data = {
							aid: data.id,
							bid: data.banner_id,
							action: 'ga'
						};

						jQuery.ajax({
							method: 'POST',
							url: window.mds_data.ajax,
							data: ajax_data,
							dataType: 'html',
							crossDomain: true,
						}).done(function (data) {
							instance.setContent(data);
							instance._content = true;
						}).fail(function (jqXHR, textStatus, errorThrown) {
							instance._error = errorThrown;
							instance.setContent(`Request failed. ${errorThrown}`);
						}).always(function () {
							instance._isFetching = false;
						});

					},
					onHidden(instance) {
						instance.setContent(defaultContent);
						instance._content = null;
						instance._error = null;
					}
				});

				window.is_touch = false;

				jQuery(document).on('touchstart', function () {
					window.is_touch = true;
				});

				jQuery(document).on('scroll', function () {
					if (!window.is_touch && window.tippy_instance != null && typeof window.tippy_instance.hide === 'function') {
						window.tippy_instance.hide();
					}
				});
			});
		});
	});

</script>
