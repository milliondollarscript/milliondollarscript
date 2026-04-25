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

use MillionDollarScript\Classes\Admin\Notices;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\System\Logs;
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

if ( @ini_set( 'max_execution_time', 300 ) === false ) {
	Logs::log( 'MDS Process Pixels: Failed to set max_execution_time — ini_set may be disabled.' );
}

require_once MDS_CORE_PATH . 'include/output_grid.php';

global $f2;
global $wpdb;

$banners = $wpdb->get_results(
	"SELECT * FROM " . MDS_DB_PREFIX . "banners",
	ARRAY_A
);

$banner_by_id          = [];
$large_grid_estimates = [];
foreach ( $banners as $banner ) {
	$banner_id                   = intval( $banner['banner_id'] ?? 0 );
	$banner_by_id[ $banner_id ]  = $banner;
	$estimate                    = mds_grid_render_estimate( $banner );
	if ( ! empty( $estimate['warn'] ) ) {
		$large_grid_estimates[ $banner_id ] = [
			'banner'   => $banner,
			'estimate' => $estimate,
		];
	}
}

$is_process_request = isset( $_REQUEST['process'] ) && $_REQUEST['process'] == '1' && ! isset( $_REQUEST['action'] ) && isset( $_REQUEST['banner_list'] );
$selected_banner_ids = [];

if ( $is_process_request && isset( $_REQUEST['banner_list'] ) && is_array( $_REQUEST['banner_list'] ) ) {
	$banner_list = array_map(
		static function ( $banner_id ): string {
			return is_scalar( $banner_id ) ? sanitize_text_field( wp_unslash( (string) $banner_id ) ) : '';
		},
		$_REQUEST['banner_list']
	);

	if ( in_array( 'all', $banner_list, true ) ) {
		$selected_banner_ids = array_keys( $banner_by_id );
	} else {
		foreach ( $banner_list as $banner_id ) {
			$banner_id = intval( $banner_id );
			if ( $banner_id > 0 && isset( $banner_by_id[ $banner_id ] ) ) {
				$selected_banner_ids[] = $banner_id;
			}
		}
	}
}

$selected_banner_ids           = array_values( array_unique( $selected_banner_ids ) );
$selected_large_grid_estimates = array_intersect_key( $large_grid_estimates, array_flip( $selected_banner_ids ) );
$large_grid_confirmation       = $_REQUEST['confirm_large_grids'] ?? '';
$large_grid_confirmation       = is_scalar( $large_grid_confirmation ) ? sanitize_text_field( wp_unslash( (string) $large_grid_confirmation ) ) : '';
$large_grid_confirmed          = '1' === $large_grid_confirmation;
$can_process_grids             = ! $is_process_request || empty( $selected_large_grid_estimates ) || $large_grid_confirmed;

// Handle processing after redirect from admin-post.php
if ( $is_process_request && ! $can_process_grids ) {
	$processing_warning = Language::get( 'Grid regeneration was not started. Review the large-grid warning below and confirm before processing these grids.' );
}

if ( $is_process_request && $can_process_grids ) {

	$processing_output = '';
	$banners_processed = 0;

	try {
		foreach ( $selected_banner_ids as $BID ) {
			$processing_output .= process_image( $BID );
			publish_image( $BID );
			process_map( $BID );
			$banners_processed++;
		}

	} catch ( Exception $e ) {
		$processing_error = $e->getMessage();
	}
}

// Display feedback messages after processing
if ( isset( $_REQUEST['process'] ) && $_REQUEST['process'] == '1' && ! isset( $_REQUEST['action'] ) ) {
	if ( isset( $banners_processed ) && $banners_processed > 0 ) {
		echo '<div class="notice notice-success is-dismissible"><p>' .
			esc_html( Language::get_replace(
				"Successfully processed [COUNT] grid(s).",
				'[COUNT]',
				$banners_processed
			) ) .
			'</p></div>';

		// Output any processing messages
		if ( ! empty( $processing_output ) ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . wp_kses_post( $processing_output ) . '</p></div>';
		}
	}

	if ( isset( $processing_warning ) ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' .
			esc_html( $processing_warning ) .
			'</p></div>';
	}

	if ( isset( $processing_error ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' .
			esc_html( Language::get( "Error processing grids: " ) . $processing_error ) .
			'</p></div>';
	}
}



// Process images

if ( ! is_writable( Utility::get_upload_path() . "grids/" ) ) {
	echo "<b>Warning:</b> The script does not have permission write to " . Utility::get_upload_path() . "grids/ or the directory does not exist <br>";
}
$BANNER_PATH = get_banner_dir();
if ( ! is_writable( $BANNER_PATH ) ) {
	echo "<b>Warning:</b> The script does not have permission write to " . $BANNER_PATH . " or the directory does not exist<br>";
}

$unapproved_orders = $wpdb->get_results(
	"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE approved = 'N' AND status = 'completed'",
	ARRAY_A
);
$c = count( $unapproved_orders );

if ( $c > 0 ) {
	echo "<h3>Note: There are/is $c pixel ads waiting to be approved. <a href='" . esc_url( admin_url( 'admin.php?page=mds-approve-pixels' ) ) . "'>Approve pixel ads here.</a></h3>";
}

if ( ! empty( $large_grid_estimates ) ) {
	echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Large grid rendering warning:', 'milliondollarscript' ) . '</strong> ' . esc_html__( 'One or more grids may require significant memory or processing time during regeneration.', 'milliondollarscript' ) . '</p><ul>';
	foreach ( $large_grid_estimates as $item ) {
		$banner   = $item['banner'];
		$estimate = $item['estimate'];
		echo '<li>';
		echo esc_html(
			sprintf(
				'#%d - %s: %s cells, %dx%d px, estimated memory %s',
				(int) $banner['banner_id'],
				(string) $banner['name'],
				number_format_i18n( (int) $estimate['cells'] ),
				(int) $estimate['width'],
				(int) $estimate['height'],
				mds_format_bytes( (int) $estimate['estimated_bytes'] )
			)
		);
		echo '</li>';
	}
	echo '</ul></div>';
}

?>
<p>
    Here you can process the images. This is where the script gets all the user's approved pixels, and merges it into a single image. It automatically publishes the final grid into the <?php echo $BANNER_PATH; ?> directory where the grid images are served from. Click the button below after approving pixels.
</p>
<form method='post' action='<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>'>
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission" />
    <input type="hidden" name="mds_dest" value="process-pixels" />

	<input value='1' name="process" type="hidden"/>
	<select name="banner_list[]" multiple size='3'>
        <option value="all" selected>Process All</option>
		<?php

		foreach ( $banners as $row ) {
			echo '<option value="' . esc_attr( $row['banner_id'] ) . '">#' . esc_html( $row['banner_id'] ) . ' - ' . esc_html( $row["name"] ) . '</option>' . "\n";
		}
		?>
	</select><br/>
	<?php if ( ! empty( $large_grid_estimates ) ) : ?>
		<label>
			<input type="checkbox" name="confirm_large_grids" value="1"/>
			<?php esc_html_e( 'Process large grids despite the memory warning.', 'milliondollarscript' ); ?>
		</label><br/>
	<?php endif; ?>
	<input type="submit" name='submit' value="Process Grids(s)"/>
</form>
