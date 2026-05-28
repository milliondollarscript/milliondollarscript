<?php

use MillionDollarScript\Classes\Admin\MigrationReadinessReport;

defined( 'ABSPATH' ) or exit;

$report = MigrationReadinessReport::build();
$counts = $report['counts'];
$pages  = $report['pages'];
$flags  = $report['flags'];

if ( ! function_exists( 'mds_migration_readiness_count' ) ) {
	function mds_migration_readiness_count( $value ): string {
		return number_format_i18n( (int) $value );
	}
}

if ( ! function_exists( 'mds_migration_readiness_render_count_map' ) ) {
	function mds_migration_readiness_render_count_map( array $counts ): void {
		if ( empty( $counts ) ) {
			echo '<p>' . esc_html__( 'None found.', 'milliondollarscript' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><tbody>';
		foreach ( $counts as $label => $count ) {
			echo '<tr>';
			echo '<th scope="row">' . esc_html( $label ) . '</th>';
			echo '<td>' . esc_html( mds_migration_readiness_count( $count ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
}

?>

<h1><?php esc_html_e( 'MDS3 Migration Readiness', 'milliondollarscript' ); ?></h1>
<p><?php esc_html_e( 'This report is read-only. It summarizes the current MDS2 data shape for migration planning and does not change tables, options, pages, orders, blocks, or media.', 'milliondollarscript' ); ?></p>

<h2><?php esc_html_e( 'Inventory', 'milliondollarscript' ); ?></h2>
<table class="widefat striped">
	<tbody>
		<tr>
			<th scope="row"><?php esc_html_e( 'Banners', 'milliondollarscript' ); ?></th>
			<td><?php echo esc_html( mds_migration_readiness_count( $counts['banners'] ?? 0 ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Packages', 'milliondollarscript' ); ?></th>
			<td><?php echo esc_html( mds_migration_readiness_count( $counts['packages'] ?? 0 ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Price zones', 'milliondollarscript' ); ?></th>
			<td><?php echo esc_html( mds_migration_readiness_count( $counts['price_zones'] ?? 0 ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'WooCommerce-linked orders', 'milliondollarscript' ); ?></th>
			<td><?php echo esc_html( mds_migration_readiness_count( $counts['woocommerce_linked_orders'] ?? 0 ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Configured pages', 'milliondollarscript' ); ?></th>
			<td><?php echo esc_html( mds_migration_readiness_count( $counts['pages']['configured'] ?? 0 ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Existing pages', 'milliondollarscript' ); ?></th>
			<td><?php echo esc_html( mds_migration_readiness_count( $counts['pages']['existing'] ?? 0 ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Pages with expected shortcode', 'milliondollarscript' ); ?></th>
			<td><?php echo esc_html( mds_migration_readiness_count( $counts['pages']['with_shortcode'] ?? 0 ) ); ?></td>
		</tr>
	</tbody>
</table>

<h2><?php esc_html_e( 'MDS3-Critical Feature Flags', 'milliondollarscript' ); ?></h2>
<table class="widefat striped">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Feature', 'milliondollarscript' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Detected', 'milliondollarscript' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Details', 'milliondollarscript' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $flags as $flag ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( $flag['label'] ); ?></th>
				<td><?php echo ! empty( $flag['active'] ) ? esc_html__( 'Yes', 'milliondollarscript' ) : esc_html__( 'No', 'milliondollarscript' ); ?></td>
				<td><?php echo esc_html( $flag['details'] ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<h2><?php esc_html_e( 'Blocks by Status', 'milliondollarscript' ); ?></h2>
<?php mds_migration_readiness_render_count_map( $counts['block_statuses'] ?? [] ); ?>

<h2><?php esc_html_e( 'Orders by Status', 'milliondollarscript' ); ?></h2>
<?php mds_migration_readiness_render_count_map( $counts['order_statuses'] ?? [] ); ?>

<h2><?php esc_html_e( 'Page Wizard / Shortcode Pages', 'milliondollarscript' ); ?></h2>
<table class="widefat striped">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Page Type', 'milliondollarscript' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Option', 'milliondollarscript' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Page ID', 'milliondollarscript' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Status', 'milliondollarscript' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Expected Shortcode', 'milliondollarscript' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $pages as $page ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( $page['label'] ); ?></th>
				<td><?php echo esc_html( $page['option'] ); ?></td>
				<td><?php echo $page['page_id'] ? esc_html( (string) $page['page_id'] ) : esc_html__( 'Not configured', 'milliondollarscript' ); ?></td>
				<td><?php echo $page['exists'] ? esc_html( $page['status'] ) : esc_html__( 'Missing', 'milliondollarscript' ); ?></td>
				<td><?php echo $page['has_shortcode'] ? esc_html__( 'Yes', 'milliondollarscript' ) : esc_html__( 'No', 'milliondollarscript' ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<h2><?php esc_html_e( 'Media Health', 'milliondollarscript' ); ?></h2>
<table class="widefat striped">
	<tbody>
		<tr>
			<th scope="row"><?php esc_html_e( 'Sold / ordered blocks missing image data', 'milliondollarscript' ); ?></th>
			<td><?php echo esc_html( mds_migration_readiness_count( $counts['media']['missing_block_images'] ?? 0 ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Blocks with invalid encoded image data', 'milliondollarscript' ); ?></th>
			<td><?php echo esc_html( mds_migration_readiness_count( $counts['media']['invalid_block_images'] ?? 0 ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Ad image fields with missing attachments', 'milliondollarscript' ); ?></th>
			<td><?php echo esc_html( mds_migration_readiness_count( $counts['media']['missing_ad_attachments'] ?? 0 ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Ad image fields with unsupported attachment types', 'milliondollarscript' ); ?></th>
			<td><?php echo esc_html( mds_migration_readiness_count( $counts['media']['invalid_ad_attachments'] ?? 0 ) ); ?></td>
		</tr>
	</tbody>
</table>
