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

// MillionDollarScript header.php
require_once __DIR__ . "/../include/init.php";

// Only load headers and assets if not using WP integration and not an ajax call.
$call_state = get_call_state();
if ( $call_state < 2 || $call_state == 4 ) {

mds_header_cache();

if ( WP_ENABLED == "YES" && ! empty( WP_URL ) ) {
	header( "Content-Security-Policy: frame-ancestors 'self' " . WP_URL . "/" );
	header( "X-Frame-Options: allow-from " . WP_URL . "/" );
}

?><!DOCTYPE html>
<html>
<head>
    <title><?php echo SITE_NAME; ?></title>
    <meta name="Description" content="<?php echo SITE_SLOGAN; ?>">
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=10.0, minimum-scale=0.1, user-scalable=yes"/>
    <script src="<?php echo BASE_HTTP_PATH; ?>vendor/components/jquery/jquery.min.js?ver=<?php echo filemtime( BASE_PATH . "/vendor/components/jquery/jquery.min.js" ); ?>"></script>
    <script src="<?php echo BASE_HTTP_PATH; ?>js/third-party/popper.min.js"></script>
    <script src="<?php echo BASE_HTTP_PATH; ?>js/third-party/tippy-bundle.umd.min.js"></script>
    <link rel="stylesheet" type="text/css" href="<?php echo BASE_HTTP_PATH; ?>css/tippy/light.css">

    <script src="<?php echo BASE_HTTP_PATH; ?>js/third-party/image-scale.min.js"></script>
    <script src="<?php echo BASE_HTTP_PATH; ?>js/third-party/image-map.js"></script>
    <script src="<?php echo BASE_HTTP_PATH; ?>js/third-party/hammer.min.js"></script>

    <link rel="stylesheet" type="text/css" href="<?php echo BASE_HTTP_PATH; ?>css/main.css?ver=<?php echo filemtime( BASE_PATH . "/css/main.css" ); ?>">

	<?php
	if ( ! isset( $GLOBALS['mds_js_loaded'] ) ) {
		$GLOBALS['mds_js_loaded'] = true;

		global $f2;
		$BID         = $f2->bid();
		$banner_data = load_banner_constants( $BID );

		$wp_url = '';
		if ( WP_ENABLED == "YES" && ! empty( WP_URL ) ) {
			$wp_url = WP_URL;
		}
		?>
        <script>
			window.mds_data = {
				ajax: '<?php echo BASE_HTTP_PATH; ?>ajax.php',
				wp: '<?php echo $wp_url; ?>',
				winWidth: parseInt('<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>'),
				winHeight: parseInt('<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>'),
				time: '<?php echo time(); ?>',
				BASE_HTTP_PATH: '<?php echo BASE_HTTP_PATH;?>',
				REDIRECT_SWITCH: '<?php echo REDIRECT_SWITCH; ?>',
				REDIRECT_URL: '<?php echo REDIRECT_URL; ?>',
				ENABLE_MOUSEOVER: '<?php echo ENABLE_MOUSEOVER; ?>',
				BID: parseInt('<?php echo $BID; ?>')
			};
        </script>
        <script src="<?php echo BASE_HTTP_PATH; ?>js/mds.js?ver=<?php echo filemtime( BASE_PATH . '/js/mds.js' ); ?>" defer></script>
	<?php if ( $call_state == 4 ) { ?>
        <script>
			$(function () {
				let mds_init_call = function () {
					var load_wait = setInterval(function () {
						if (typeof mds_init == 'function') {
							mds_init(null, null, null, 'iframe', false);
							clearInterval(load_wait);
						}
					}, 100);
				}

				if (window.mds_ajax_request != null) {
					window.mds_ajax_request.done(mds_init_call);
				} else {
					mds_init_call();
				}
			});
        </script>
	<?php } ?>
		<?php
	}
	?>
</head>
<body class="mds-container">
<?php
}

if ( $call_state == 2 || $call_state == 5 ) {
?>
<div class="mds-container">
	<?php
	}
	?>
    <div class="outer">
        <div class="inner">
			<?php if ( $call_state < 3 ) { ?>
                <div class="heading">
					<?php
					$logourl = SITE_LOGO_URL;
					if ( ! empty( $logourl ) ) {
						?>
                        <div class="logo">
                            <a href="<?php echo BASE_HTTP_PATH; ?>index.php">
                                <img src="<?php echo htmlentities( $logourl ); ?>" style="border:0;" alt=""/>
                            </a>
                        </div>
						<?php
					}

					$slogan = SITE_SLOGAN;
					if ( ! empty( $slogan ) ) {
						?>
                        <div class="slogan">
							<?php echo htmlentities( $slogan ); ?>
                        </div>
						<?php
					}
					?>
                    <div class="status_outer">
						<?php
						require_once( BASE_PATH . "/include/mds_ajax.php" );
						$mds_ajax = new Mds_Ajax();
						$mds_ajax->show( 'stats', null, 'stats' );
						?>
                    </div>
                </div>

                <div class="menu-bar">
                    <a href='<?php echo BASE_HTTP_PATH; ?>index.php'>Home</a>
                    <a href='<?php echo BASE_HTTP_PATH; ?>users/'>Buy Pixels</a>
                    <a href='<?php echo BASE_HTTP_PATH; ?>list.php'>Ads List</a>
                </div>
			<?php } ?>

			<?php
			if ( USE_AJAX == 'SIMPLE' ) {
				$order_page = 'order_pixels.php';
			} else {
				$order_page = 'select.php';
			}

			$loggedin = '';
			if ( isset($_SESSION['MDS_ID']) && $_SESSION['MDS_ID'] != '' ) {
				$loggedin = ' logged-in';
				global $label;
				?>
                <div class="users-menu-bar">
                    <a href="<?php echo BASE_HTTP_PATH; ?>users/index.php"><?php echo $label['advertiser_header_nav1']; ?></a>
                    <a href="<?php echo BASE_HTTP_PATH . "users/" . $order_page; ?>"><?php echo $label['advertiser_header_nav2']; ?></a>
                    <a href="<?php echo BASE_HTTP_PATH; ?>users/publish.php"><?php echo $label['advertiser_header_nav3']; ?></a>
                    <a href="<?php echo BASE_HTTP_PATH; ?>users/orders.php"><?php echo $label['advertiser_header_nav4']; ?></a>
                    <a target="_top" href="<?php echo BASE_HTTP_PATH; ?>users/<?php echo ( WP_ENABLED == "YES" && WP_USERS_ENABLED == "YES" ) ? "wp" : ""; ?>logout.php"><?php echo $label['advertiser_header_nav5']; ?></a>
                </div>

			<?php } ?>

            <div class="container<?php echo $loggedin; ?>">
