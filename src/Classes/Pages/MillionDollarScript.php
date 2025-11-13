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

namespace MillionDollarScript\Classes\Pages;

use MillionDollarScript\Classes\Data\Database;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

class MillionDollarScript {
	public static function menu(): void {
		$handle = \add_menu_page( 'MillionDollarScript', Language::get( 'Million Dollar Script' ), 'manage_options', 'milliondollarscript', array( __CLASS__, 'html' ), 'dashicons-grid-view', 26 );

		// Add styles for admin page
		add_action( 'admin_print_styles-' . $handle, [ __CLASS__, 'styles' ] );

		// Add scripts
		add_action( 'admin_print_scripts-' . $handle, [ __CLASS__, 'scripts' ] );
	}

	public static function styles(): void {
		wp_enqueue_style( 'MillionDollarScriptStyles', MDS_BASE_URL . 'src/Assets/css/admin.css', [], filemtime( MDS_BASE_PATH . 'src/Assets/css/admin.css' ) );
		// Enqueue shared promo box styles
		wp_enqueue_style( 'mds-promo-boxes-styles', MDS_BASE_URL . 'src/Assets/css/admin/promo-boxes.css', ['MillionDollarScriptStyles'], filemtime( MDS_BASE_PATH . 'src/Assets/css/admin/promo-boxes.css' ) );
	}

	public static function scripts(): void {

		wp_enqueue_script(
			MDS_PREFIX . 'admin-js',
			MDS_BASE_URL . 'src/Assets/js/admin.min.js',
			[ 'jquery', 'hoverIntent' ],
			filemtime( MDS_BASE_PATH . 'src/Assets/js/admin.min.js' ),
			true
		);

		// Load fire.js
		// @link https://gpfault.net/posts/webgl2-particles.txt.html
		wp_enqueue_script( 'fire', MDS_BASE_URL . 'src/Assets/js/fire/fire.min.js', [ MDS_PREFIX . 'admin-js' ], filemtime( MDS_BASE_PATH . 'src/Assets/js/fire/fire.min.js' ), true );
	}

	public static function html(): void {
		require_once MDS_BASE_PATH . 'src/Assets/js/fire/fire.html'
		?>

        <div class="mds-main-page">
            <div class="milliondollarscript-fire">
                <canvas id="milliondollarscript-fire" data-particle-src="<?php echo esc_url( MDS_BASE_URL . 'src/Assets/images/spark.png' ); ?>"></canvas>

				<div class="milliondollarscript-header">
					<img src="<?php echo esc_url( MDS_BASE_URL . 'src/Assets/images/milliondollarscript-transparent.png' ); ?>" class="milliondollarscript-logo" alt="<?php Language::out( 'Million Dollar Script Logo' ); ?>"/>
				</div>

                <?php
                // Render dashboard menu from registry
                echo \MillionDollarScript\Classes\Admin\Menu_Registry::render();
                ?>
            </div>

			<div class="milliondollarscript-dashboard-container">

				<div class="milliondollarscript-description milliondollarscript-section">
					<p><?php Language::out( 'Million Dollar Script enables you to sell pixel-based advertising space on your website for an interactive user experience.' ); ?></p>
					<p><?php Language::out( 'Visit the <a target="_blank" href="https://milliondollarscript.com/million-dollar-script-wordpress-plugin/">Million Dollar Script WordPress Plugin</a> page on the website for additional documentation.' ); ?></p>
					<p><?php Language::out_replace( 'Need to create or configure pages? Use the <a href="%s">Setup Wizard</a> (the wrench icon in the menu above) to get started quickly.', '%s', esc_url( admin_url( 'admin.php?page=milliondollarscript_wizard' ) ) ); ?></p>
					<p><?php Language::out( 'For advanced page management, use <a href="' . esc_url( admin_url( 'admin.php?page=mds-page-management' ) ) . '">Manage Pages</a> or <a href="' . esc_url( admin_url( 'admin.php?page=mds-create-pages' ) ) . '">Create Pages</a> from the System menu.' ); ?></p>
				</div>

				<?php
				// Get times
				$time_gmt    = gmdate( 'Y-m-d H:i:s' );
				$time_server = date( 'Y-m-d H:i:s' );
				$time_local  = '<span id="mdsLocalTime"></span>';
				// Get last updated date
				$last_updated_date = Utility::get_mds_build_date(true);
				$last_updated_text = $last_updated_date ? Language::get_replace('Last Updated: %s', '%s', $last_updated_date) : Language::get('Last Updated: N/A');

				?>
				<div class="milliondollarscript-time milliondollarscript-section">

					<div class="mds-time mds-build-date"><?php echo esc_html( $last_updated_text ); ?></div>
					<div class="mds-time mds-gmt"><?php Language::out_replace('GMT: %s', '%s', esc_html( $time_gmt ) ); ?></div>
					<div class="mds-time mds-server"><?php Language::out_replace('Server: %s', '%s', esc_html( $time_server ) ); ?></div>
					<div class="mds-time mds-local"><?php Language::out_replace('Local: %s', '%s', $time_local); ?></div>
					<script>
						const localTime = new Date();
						const formattedLocalTime = localTime.getFullYear() + '-'
							+ String(localTime.getMonth() + 1).padStart(2, '0') + '-'
							+ String(localTime.getDate()).padStart(2, '0') + ' '
							+ String(localTime.getHours()).padStart(2, '0') + ':'
							+ String(localTime.getMinutes()).padStart(2, '0') + ':'
							+ String(localTime.getSeconds()).padStart(2, '0');

						document.getElementById("mdsLocalTime").innerHTML = formattedLocalTime;
					</script>

				</div>

				<?php

				global $wpdb;

				$count_users = count_users();
				$advertisers = $count_users['total_users'];

				$orders_waiting = $wpdb->get_var( "SELECT COUNT(order_id) FROM " . MDS_DB_PREFIX . "orders WHERE (status ='confirmed' OR status='pending')" );

				$orders_cancelled = $wpdb->get_var( "SELECT COUNT(order_id) FROM " . MDS_DB_PREFIX . "orders WHERE (status ='cancelled')" );

				$orders_denied = $wpdb->get_var( "SELECT COUNT(order_id) FROM " . MDS_DB_PREFIX . "orders WHERE (status ='denied')" );

				$orders_completed = $wpdb->get_var( "SELECT COUNT(order_id) FROM " . MDS_DB_PREFIX . "orders WHERE (status ='completed')" );

				$waiting = $wpdb->get_var( "SELECT COUNT(block_id) FROM " . MDS_DB_PREFIX . "blocks WHERE approved='N' and image_data <> ''" );

				?>
				<div class="milliondollarscript-orders milliondollarscript-section">
					<h3><?php Language::out( 'Orders' ); ?></h3>

					<div>
						<?php echo $advertisers; ?> <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>"><?php Language::out( 'Customer Accounts' ); ?></a>
					</div>
					<div>
						<?php echo $orders_waiting; ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-orders-waiting' ) ); ?>"><?php
							if ( $orders_waiting > 0 ) {
								echo "<b>";
							}
							Language::out( 'Orders Waiting' );
							if ( $orders_waiting > 0 ) {
								echo "</b>";
							}
							?></a>
					</div>
					<div>
						<?php echo $orders_cancelled; ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-orders-cancelled' ) ); ?>"><?php Language::out( 'Orders Cancelled' ); ?></a>
					</div>
					<div>
						<?php echo $orders_denied; ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-orders-denied' ) ); ?>"><?php Language::out( 'Orders Denied' ); ?></a>
					</div>
					<div>
						<?php echo $orders_completed; ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-orders-completed' ) ); ?>"><?php Language::out( 'Orders Completed' ); ?></a>
					</div>
					<div>
						<?php echo $waiting; ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-approve-pixels&show=WA' ) ); ?>"><?php Language::out( 'Pixels Waiting for approval' ); ?></a>
					</div>
				</div>

				<div class="milliondollarscript-credits milliondollarscript-section">
					<h3><?php Language::out( 'Credits' ); ?></h3>
					<p><?php Language::out( 'Ryan Rhode - Developer' ); ?></p>
					<p><?php Language::out( 'Adam Malinowski - Project Founder' ); ?></p>
					<p><?php Language::out( 'And the many members of the community who have contributed bug reports, feature suggestions and fixes.' ); ?></p>
				</div>

				<div class="milliondollarscript-services milliondollarscript-section">

					<div class="mds-promo-box mds-cta-full">
						<h4><?php Language::out('Discover Extensions'); ?></h4>
						<p><?php Language::out('Supercharge Million Dollar Script with powerful add-ons for custom fields, translations, analytics, automation, and more.'); ?></p>
						<p class="promo-cta">
							<a href="<?php echo esc_url( admin_url('admin.php?page=mds-extensions') ); ?>" class="button button-primary"><?php Language::out('Explore Extensions'); ?></a>
						</p>
					</div>

					<div class="mds-promo-box">
						<h4><?php Language::out('Premium Installation Service'); ?></h4>
						<p><?php Language::out('The premium installation service handles full plugin setup and configuration for you.'); ?></p>
						<p class="promo-cta">
							<a href="https://milliondollarscript.com/install-service/" target="_blank" class="button button-primary"><?php Language::out('Learn More About The Installation Service'); ?></a>
						</p>
					</div>
					<div class="mds-promo-box">
						<h4><?php Language::out('Custom Development Services'); ?></h4>
						<p><?php Language::out('Want custom features or integrations?<br>The development team can help.'); ?></p>
						<p class="promo-cta">
							<a href="https://milliondollarscript.com/order-custom-development-services/" target="_blank" class="button button-primary"><?php Language::out('Get Custom Development'); ?></a>
						</p>
					</div>
					<div class="mds-promo-box">
						<h4><?php Language::out('Want Reliable Hosting?'); ?></h4>
						<p><?php Language::out('Hostinger offers fast, affordable hosting for your MDS site.'); ?></p>
						<p class="promo-cta">
							<a href="https://hostinger.com?REFERRALCODE=MILLIONDOLLARS" target="_blank" class="button button-secondary"><?php Language::out('Check out Hostinger Deals'); ?></a>
						</p>
					</div>

				</div>

				<div class="milliondollarscript-version milliondollarscript-section">
					<p>
						<?php
						if ( ! function_exists( 'get_plugin_data' ) ) {
							require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
						}
						$plugin_data    = get_plugin_data( MDS_BASE_FILE );
						$plugin_version = wp_sprintf(
							Language::get( 'Million Dollar Script v%s' ),
							$plugin_data['Version']
						);
						esc_html_e( $plugin_version );
						?>
						<br/>
						<?php
						$mdsdb      = new Database();
						$db_version = wp_sprintf(
							Language::get( 'Database v%s' ),
							$mdsdb->get_dbver()
						);
						esc_html_e( $db_version );
						?>
					</p>
				</div>

				<div class="milliondollarscript-license milliondollarscript-section">
					<p><?php
						$copyright = wp_sprintf(
							Language::get( '&copy; Copyright %s by the authors. Released under the GNU GPL v3.<br>See %s for license information.' ),
							date( 'Y' ),
							'<a href="' . admin_url( 'admin.php?page=mds-license' ) . '">license.txt</a>'
						);
						echo wp_kses( $copyright, array(
							'a' => array(
								'href' => array()
							)
						) );
						?></p>
				</div>
			</div>
        </div>
		<?php
	}
}
