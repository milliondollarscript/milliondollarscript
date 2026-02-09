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

use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

// Find and delete orphaned blocks (blocks with no matching order) in a single query.
global $wpdb;

$table_blocks = MDS_DB_PREFIX . 'blocks';
$table_orders = MDS_DB_PREFIX . 'orders';

// First, report which blocks will be deleted.
$orphans = $wpdb->get_results(
	"SELECT b.block_id FROM `{$table_blocks}` b " .
	"LEFT JOIN `{$table_orders}` o ON o.order_id = b.order_id AND o.banner_id = b.banner_id " .
	"WHERE b.status <> 'nfs' AND o.order_id IS NULL",
	ARRAY_A
);

if ( ! empty( $orphans ) ) {
	foreach ( $orphans as $orphan ) {
		echo "Deleting block #" . intval( $orphan['block_id'] ) . "<br>";
	}

	// Delete all orphaned blocks in a single query.
	$wpdb->query(
		"DELETE b FROM `{$table_blocks}` b " .
		"LEFT JOIN `{$table_orders}` o ON o.order_id = b.order_id AND o.banner_id = b.banner_id " .
		"WHERE b.status <> 'nfs' AND o.order_id IS NULL"
	);
}

Language::out( "Check Completed." );
