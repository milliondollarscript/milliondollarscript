<?php

declare(strict_types=1);

$pluginRoot = dirname( __DIR__, 2 );
$failures   = [];

function assert_hpos_true( bool $condition, string $label ): void {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $label;
	}
}

$bootstrap = (string) file_get_contents( $pluginRoot . '/milliondollarscript-two.php' );
$commerce  = (string) file_get_contents( $pluginRoot . '/src/Classes/WooCommerce/WooCommerce.php' );
$functions = (string) file_get_contents( $pluginRoot . '/src/Classes/WooCommerce/WooCommerceFunctions.php' );
$refunds   = (string) file_get_contents( $pluginRoot . '/src/Classes/WooCommerce/Refunds.php' );
$orders    = (string) file_get_contents( $pluginRoot . '/src/Classes/Orders/Orders.php' );
$adminOrders = (string) file_get_contents( $pluginRoot . '/src/Core/admin/orders.php' );
$transactions = (string) file_get_contents( $pluginRoot . '/src/Core/admin/transaction-log.php' );
$readiness = (string) file_get_contents( $pluginRoot . '/src/Classes/Admin/MigrationReadinessReport.php' );

assert_hpos_true(
	str_contains( $bootstrap, "FeaturesUtil::declare_compatibility(\n\t\t\t'custom_order_tables',\n\t\t\tMDS_BASE_FILE,\n\t\t\ttrue" ),
	'MDS2 must declare WooCommerce custom order table compatibility against its main plugin file'
);

assert_hpos_true(
	! preg_match( '/(?:get|update)_post_meta\(\s*\$(?:id|order_id)\b/', $commerce ),
	'WooCommerce order metadata in the integration must not use WordPress post-meta functions'
);
assert_hpos_true(
	! preg_match( '/(?:get|update)_post_meta\(\s*\$(?:id|order_id)\b/', $functions ),
	'WooCommerce order metadata in shared helpers must not use WordPress post-meta functions'
);
assert_hpos_true(
	! preg_match( '/(?:get|update)_post_meta\(\s*\$(?:id|order_id)\b/', $refunds ),
	'WooCommerce order metadata in refund handling must not use WordPress post-meta functions'
);

assert_hpos_true(
	str_contains( $commerce, "\$order->update_meta_data( 'mds_order_id', \$mds_order_id )" ),
	'checkout mapping must be written through the WooCommerce order object'
);
assert_hpos_true(
	str_contains( $functions, "\$order->get_meta( 'mds_order_id', true )" ),
	'order completion and quantity checks must read mappings through the WooCommerce order object'
);
assert_hpos_true(
	str_contains( $refunds, "\$order->get_meta( 'mds_order_id', true )" ),
	'refund synchronization must read mappings through the WooCommerce order object'
);
assert_hpos_true(
	str_contains( $orders, "'meta_key'     => 'mds_order_id'" ) && str_contains( $orders, "'meta_compare' => 'EXISTS'" ),
	'MDS-to-WooCommerce reverse lookups must use the supported order query API'
);
assert_hpos_true(
	! str_contains( $adminOrders, 'SELECT post_id FROM {$wpdb->postmeta}' ),
	'MDS order administration must not resolve WooCommerce orders through postmeta SQL'
);
assert_hpos_true(
	! str_contains( $transactions, "LEFT JOIN " . '$wpdb->prefix' . " . \"postmeta" ),
	'transaction administration must not join WooCommerce order metadata through postmeta SQL'
);
assert_hpos_true(
	str_contains( $readiness, 'Orders::count_linked_wc_orders()' ),
	'migration readiness must count linked WooCommerce orders through the active data store'
);

if ( ! empty( $failures ) ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo 'All WooCommerce HPOS compatibility tests passed.' . PHP_EOL;
