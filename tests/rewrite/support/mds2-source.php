<?php
/**
 * Realistic Million Dollar Script 2 source data for migration fixtures.
 *
 * The table definitions are copied from the MDS 2 installer
 * (milliondollarscript-two/src/Core/admin/install.php) instead of invented, because
 * the places where real sites differ from a friendly fixture are exactly the
 * column types: status columns are SET(...), blocks.url is VARCHAR(255) and
 * blocks.alt_text is unbounded TEXT that MDS 2 filled with the advertiser's whole
 * ad copy. A fixture with VARCHAR status columns and short alt text cannot
 * reproduce what the client's site did (Vikunja #30).
 *
 * The seeded rows deliberately cover the branches the importer has to handle:
 * several order and block statuses, images reachable through each of the three
 * legacy media paths, an advertiser with no image at all, an order whose ad record
 * is missing, and link values that are a bare domain, prose, or longer than the
 * target column.
 *
 * Usage: require this file from a WP-CLI fixture, then call
 *   mds2_source_create_schema($prefix);
 *   $seed = mds2_source_seed($prefix);   // returns counts and notable ids
 *
 * @package MillionDollarScript\V3\Tests
 */

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Longest string MDS 2 could put in blocks.alt_text through the normal order
 * flow: the advertiser's ad copy with tags stripped. Kept far above the 500
 * characters of MDS 3's placements.alt_text.
 */
function mds2_source_long_ad_copy() {
    return trim(str_repeat(
        'Family-run tile and stone yard on the north side, with yard stock, cut-to-size service and a delivery truck for the weekend project. ',
        12
    ));
}

/**
 * A link that is a valid URL but longer than MDS 3's link_url column.
 */
function mds2_source_oversized_link_url() {
    return 'https://pier9.example.com/tickets?utm_source=mds-banner&utm_medium=pixel&gclid='
        . str_repeat('F', 320)
        . '&utm_campaign=spring_relaunch_' . str_repeat('2', 180);
}

/**
 * Create the MDS 2 tables at $prefix using MDS 2's own schema.
 */
function mds2_source_create_schema($prefix) {
    global $wpdb;

    $charset = $wpdb->get_charset_collate();
    $ident = static function ($suffix) use ($wpdb, $prefix) {
        return DB::ident($prefix . $suffix);
    };

    foreach (['banners', 'blocks', 'clicks', 'views', 'orders', 'packages', 'prices', 'transactions'] as $suffix) {
        $wpdb->query('DROP TABLE IF EXISTS ' . $ident($suffix));
    }

    $wpdb->query('CREATE TABLE ' . $ident('banners') . " (
        banner_id INT NOT NULL AUTO_INCREMENT,
        grid_width INT NOT NULL DEFAULT '0',
        grid_height INT NOT NULL DEFAULT '0',
        days_expire MEDIUMINT(9) DEFAULT '0',
        price_per_block FLOAT NOT NULL DEFAULT '0',
        name VARCHAR(255) NOT NULL DEFAULT '',
        currency CHAR(3) NOT NULL DEFAULT 'CAD',
        publish_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        max_orders INT NOT NULL DEFAULT '0',
        block_width INT NOT NULL DEFAULT '10',
        block_height INT NOT NULL DEFAULT '10',
        grid_block TEXT NOT NULL,
        nfs_block LONGBLOB NOT NULL,
        tile TEXT NOT NULL,
        usr_grid_block TEXT NOT NULL,
        usr_nfs_block LONGBLOB NOT NULL,
        usr_ord_block TEXT NOT NULL,
        usr_res_block TEXT NOT NULL,
        usr_sel_block TEXT NOT NULL,
        usr_sol_block TEXT NOT NULL,
        max_blocks INT NOT NULL DEFAULT '0',
        min_blocks INT NOT NULL DEFAULT '0',
        date_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
        bgcolor VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
        auto_publish CHAR(1) NOT NULL DEFAULT 'N',
        auto_approve CHAR(1) NOT NULL DEFAULT 'N',
        nfs_covered CHAR(1) NOT NULL DEFAULT 'N',
        enabled CHAR(1) NOT NULL DEFAULT 'Y',
        time_stamp INT DEFAULT NULL,
        PRIMARY KEY  (banner_id)
    ) {$charset}");

    // MDS 2 keeps block_id+banner_id as the key and stores pixel x/y derived from
    // the block id (x = (block_id % grid_width) * block_width).
    $wpdb->query('CREATE TABLE ' . $ident('blocks') . " (
        block_id INT NOT NULL DEFAULT '0',
        user_id INT DEFAULT NULL,
        status SET('denied','cancelled','reserved','sold','free','ordered','nfs') NOT NULL DEFAULT '',
        x INT NOT NULL DEFAULT '0',
        y INT NOT NULL DEFAULT '0',
        image_data TEXT NOT NULL,
        url VARCHAR(255) NOT NULL DEFAULT '',
        alt_text TEXT NOT NULL,
        file_name VARCHAR(255) NOT NULL DEFAULT '',
        mime_type VARCHAR(100) NOT NULL DEFAULT '',
        approved SET('Y','N') NOT NULL DEFAULT '',
        published SET('Y','N') NOT NULL DEFAULT '',
        currency CHAR(3) NOT NULL DEFAULT 'CAD',
        order_id INT NOT NULL DEFAULT '0',
        price FLOAT DEFAULT NULL,
        banner_id INT NOT NULL DEFAULT '1',
        ad_id INT NOT NULL DEFAULT '0',
        click_count INT NOT NULL DEFAULT '0',
        view_count INT NOT NULL DEFAULT '0',
        PRIMARY KEY  (block_id,banner_id)
    ) {$charset}");

    $wpdb->query('CREATE TABLE ' . $ident('clicks') . " (
        banner_id INT NOT NULL,
        block_id INT NOT NULL,
        user_id INT NOT NULL,
        date DATE DEFAULT '1970-01-01',
        clicks INT NOT NULL,
        PRIMARY KEY  (banner_id,block_id,date)
    ) {$charset}");

    $wpdb->query('CREATE TABLE ' . $ident('views') . " (
        banner_id INT NOT NULL,
        block_id INT NOT NULL,
        user_id INT NOT NULL,
        date DATE DEFAULT '1970-01-01',
        views INT NOT NULL,
        PRIMARY KEY  (banner_id,block_id,date)
    ) {$charset}");

    $wpdb->query('CREATE TABLE ' . $ident('orders') . " (
        user_id INT NOT NULL DEFAULT '0',
        order_id INT NOT NULL AUTO_INCREMENT,
        blocks TEXT NOT NULL,
        status SET('paid','denied','pending','completed','cancelled','confirmed','new','expired','deleted','renew_wait','renew_paid') NOT NULL DEFAULT '',
        order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        price FLOAT NOT NULL DEFAULT '0',
        quantity INT NOT NULL DEFAULT '0',
        banner_id INT NOT NULL DEFAULT '1',
        currency CHAR(3) NOT NULL DEFAULT 'CAD',
        days_expire INT NOT NULL DEFAULT '0',
        date_published DATETIME DEFAULT CURRENT_TIMESTAMP,
        date_stamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        expiry_notice_sent SET('Y','N') NOT NULL DEFAULT '',
        package_id INT NOT NULL DEFAULT '0',
        ad_id INT DEFAULT NULL,
        approved SET('Y','N') NOT NULL DEFAULT 'N',
        published SET('Y','N') NOT NULL DEFAULT '',
        subscr_status VARCHAR(32) NOT NULL DEFAULT '',
        original_order_id INT DEFAULT NULL,
        previous_order_id INT NOT NULL DEFAULT '0',
        block_info LONGTEXT NOT NULL,
        order_in_progress SET('Y','N') NOT NULL DEFAULT 'N',
        current_step INT NOT NULL DEFAULT '0',
        PRIMARY KEY  (order_id)
    ) {$charset}");

    $wpdb->query('CREATE TABLE ' . $ident('packages') . " (
        banner_id INT NOT NULL DEFAULT '0',
        days_expire INT NOT NULL DEFAULT '0',
        price FLOAT NOT NULL DEFAULT '0',
        currency CHAR(3) NOT NULL DEFAULT '',
        package_id INT NOT NULL AUTO_INCREMENT,
        is_default SET('Y','N') DEFAULT NULL,
        max_orders MEDIUMINT(9) NOT NULL DEFAULT '0',
        description VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY  (package_id)
    ) {$charset}");

    $wpdb->query('CREATE TABLE ' . $ident('prices') . " (
        price_id INT NOT NULL AUTO_INCREMENT,
        banner_id INT NOT NULL DEFAULT '0',
        row_from INT NOT NULL DEFAULT '0',
        row_to INT NOT NULL DEFAULT '0',
        block_id_from INT NOT NULL DEFAULT '0',
        block_id_to INT NOT NULL DEFAULT '0',
        price FLOAT NOT NULL DEFAULT '0',
        currency CHAR(3) NOT NULL DEFAULT '',
        color VARCHAR(50) NOT NULL DEFAULT '',
        col_from INT DEFAULT NULL,
        col_to INT DEFAULT NULL,
        PRIMARY KEY  (price_id)
    ) {$charset}");

    $wpdb->query('CREATE TABLE ' . $ident('transactions') . " (
        transaction_id INT NOT NULL AUTO_INCREMENT,
        order_id INT NOT NULL DEFAULT '0',
        PRIMARY KEY  (transaction_id)
    ) {$charset}");
}

/**
 * Seed one advertiser's MDS 2 records: the mds-pixel post with its Carbon meta,
 * the way MDS 2 wrote blocks.url and blocks.alt_text straight from that meta.
 *
 * @return array The advertiser with its post id.
 */
function mds2_source_advertiser($slug, $title, $url, $text, $attachment_id = 0, $marker = '') {
    $post_id = wp_insert_post([
        'post_type' => 'mds-pixel',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => $slug,
    ]);
    if (is_wp_error($post_id)) {
        throw new RuntimeException('Could not create fixture advertiser: ' . $post_id->get_error_message());
    }
    update_post_meta($post_id, '_milliondollarscript_url', $url);
    update_post_meta($post_id, '_milliondollarscript_text', $text);
    if ($attachment_id) {
        update_post_meta($post_id, '_milliondollarscript_image', absint($attachment_id));
    }
    if ('' !== $marker) {
        // Marks which fixture run owns this record, so cleanup can remove it
        // without ever reaching a real advertiser on the site.
        update_post_meta($post_id, '_mds2_fixture_source', $marker);
    }

    return ['id' => (int) $post_id, 'title' => $title, 'url' => $url, 'text' => $text];
}

/**
 * Delete the advertiser records a seeded dataset created, identified by the marker
 * it was created with.
 */
function mds2_source_delete_advertisers($marker = '') {
    global $wpdb;

    if ('' === $marker) {
        return;
    }

    $post_ids = $wpdb->get_col($wpdb->prepare(
        'SELECT post_id FROM ' . DB::ident($wpdb->postmeta) . ' WHERE meta_key = %s AND meta_value = %s',
        '_mds2_fixture_source',
        $marker
    ));
    foreach ((array) $post_ids as $post_id) {
        wp_delete_post(absint($post_id), true);
    }
}

/**
 * Write the fixture's ad images into the upload directory, the way MDS 2 wrote
 * them: under uploads/milliondollarscript/images/.
 *
 * @param array<int,string> $names
 * @return array<string,string> name => absolute path
 */
function mds2_source_write_images(array $names) {
    $upload = wp_upload_dir();
    $dir = trailingslashit($upload['basedir']) . 'milliondollarscript/images';
    wp_mkdir_p($dir);

    $paths = [];
    foreach ($names as $name) {
        $path = $dir . '/' . $name;
        if (function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor(96, 96);
            $blue = imagecolorallocate($image, 37, 99, 235);
            $green = imagecolorallocate($image, 15, 118, 110);
            $white = imagecolorallocate($image, 255, 255, 255);
            imagefilledrectangle($image, 0, 0, 95, 95, $blue);
            imagefilledrectangle($image, 48, 0, 95, 95, $green);
            imageline($image, 0, 0, 95, 95, $white);
            imagepng($image, $path);
            imagedestroy($image);
        } else {
            file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        }
        if (!file_exists($path)) {
            throw new RuntimeException('Could not write fixture image ' . $name);
        }
        $paths[$name] = $path;
    }

    return $paths;
}

/**
 * An attachment record for a file already in the uploads directory, without
 * moving or re-uploading it - MDS 2 sites have the record and the file, and the
 * media resolver finds one from the other.
 */
function mds2_source_attachment($relative) {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/image.php';

    $upload = wp_upload_dir();
    $absolute = trailingslashit($upload['basedir']) . ltrim((string) $relative, '/');

    $attachment_id = absint($wpdb->get_var($wpdb->prepare(
        'SELECT post_id FROM ' . DB::ident($wpdb->postmeta) . " WHERE meta_key = %s AND meta_value = %s LIMIT 1",
        '_wp_attached_file',
        $relative
    )));
    if ($attachment_id) {
        return $attachment_id;
    }

    $attachment_id = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => sanitize_file_name(basename((string) $relative)),
        'post_status' => 'inherit',
    ], $absolute);
    if (is_wp_error($attachment_id)) {
        throw new RuntimeException('Could not create fixture attachment: ' . $attachment_id->get_error_message());
    }
    $attachment_id = (int) $attachment_id;
    update_post_meta($attachment_id, '_wp_attached_file', $relative);
    $metadata = wp_generate_attachment_metadata($attachment_id, $absolute);
    if (is_array($metadata)) {
        wp_update_attachment_metadata($attachment_id, $metadata);
    }

    return $attachment_id;
}

/**
 * Seed the dataset and return what a consuming fixture needs to assert against.
 *
 * @return array<string,mixed>
 */
function mds2_source_seed($prefix) {
    global $wpdb;

    $grid_id = 1;
    $grid_blocks_wide = 20;
    $grid_rows = 10;
    $block_px_wide = 728;
    $block_px_high = 90;

    $wpdb->insert($prefix . 'banners', [
        'banner_id' => $grid_id,
        'grid_width' => $grid_blocks_wide,
        'grid_height' => $grid_rows,
        'days_expire' => 90,
        'price_per_block' => 4.5,
        'name' => 'Community Ads Grid',
        'currency' => 'CAD',
        'max_orders' => 40,
        'block_width' => $block_px_wide,
        'block_height' => $block_px_high,
        'grid_block' => 'grid',
        'max_blocks' => 8,
        'min_blocks' => 1,
        'bgcolor' => '#ffffff',
        'auto_publish' => 'Y',
        'auto_approve' => 'Y',
        'nfs_covered' => 'N',
        'enabled' => 'Y',
    ]);

    $wpdb->insert($prefix . 'packages', [
        'package_id' => 1, 'banner_id' => $grid_id, 'days_expire' => 90, 'price' => 180,
        'currency' => 'CAD', 'is_default' => 'Y', 'max_orders' => 0, 'description' => 'Leaderboard, 90 days',
    ]);
    $wpdb->insert($prefix . 'packages', [
        'package_id' => 2, 'banner_id' => $grid_id, 'days_expire' => 30, 'price' => 75,
        'currency' => 'CAD', 'is_default' => 'N', 'max_orders' => 5, 'description' => 'Starter strip, 30 days',
    ]);

    // Zone pricing the way MDS 2 stored it: row and column ranges with a colour.
    $wpdb->insert($prefix . 'prices', [
        'price_id' => 1, 'banner_id' => $grid_id, 'row_from' => 0, 'row_to' => 1, 'block_id_from' => 0,
        'block_id_to' => 20, 'price' => 220, 'currency' => 'CAD', 'color' => '#1d7a4f', 'col_from' => 0, 'col_to' => 1,
    ]);
    $wpdb->insert($prefix . 'prices', [
        'price_id' => 2, 'banner_id' => $grid_id, 'row_from' => 2, 'row_to' => 5, 'block_id_from' => 0,
        'block_id_to' => 200, 'price' => 90, 'currency' => 'CAD', 'color' => '#2f6fb8', 'col_from' => null, 'col_to' => null,
    ]);
    $wpdb->insert($prefix . 'prices', [
        'price_id' => 3, 'banner_id' => $grid_id, 'row_from' => 6, 'row_to' => 9, 'block_id_from' => 0,
        'block_id_to' => 200, 'price' => 45, 'currency' => 'CAD', 'color' => '#8a6d3b', 'col_from' => null, 'col_to' => null,
    ]);

    // One image the advertiser record points at directly, and one reachable only
    // through blocks.file_name - the second of the three legacy media paths.
    $attached_relative = 'milliondollarscript/images/mds2-fixture-harbour-lights.png';
    $file_name_relative = 'milliondollarscript/images/ridgeline-cycles.png';
    $images = mds2_source_write_images([basename($attached_relative), basename($file_name_relative)]);
    $attached_id = mds2_source_attachment($attached_relative);
    // No advertiser meta points at this one: the import has to reach it from the
    // block row, which is how older MDS 2 sites were wired together.
    $path_only_id = mds2_source_attachment($file_name_relative);

    $advertisers = [
        // Complete record: image, real link, short copy.
        'harbour' => mds2_source_advertiser(
            'harbour-lights-coffee',
            'Harbour Lights Coffee',
            'https://harbour-lights.example.com/menu',
            '<p>Fresh roasts every morning, two blocks from the pier.</p>',
            $attached_id,
            $prefix
        ),
        // Bare domain in the link, and an ad copy long enough to overflow the
        // target alt_text column - both came from the client site.
        'ridgeline' => mds2_source_advertiser(
            'ridgeline-cycles',
            'Ridgeline Cycles',
            'ridgeline-cycles.ca/service',
            '<p>' . mds2_source_long_ad_copy() . '</p>',
            0,
            $prefix
        ),
        // Ad copy where a link should be, image only as base64 in blocks.
        'northside' => mds2_source_advertiser(
            'northside-tile-stone',
            'Northside Tile and Stone',
            'Follow the spring sale, ask in store',
            '<p>Yard stock, cut-to-size service and a delivery truck.</p>',
            0,
            $prefix
        ),
        // A link that is well formed but longer than MDS 3 stores.
        'pier9' => mds2_source_advertiser(
            'pier-9-ferry',
            'Pier 9 Ferry Tickets',
            mds2_source_oversized_link_url(),
            '<p>Ferries every half hour, tickets at the window or online.</p>',
            0,
            $prefix
        ),
        // Nothing but a name: no link, no image, no copy. The import must say so
        // rather than invent a placement.
        'empty' => mds2_source_advertiser('gallerreyaslavi-studio', 'Gallery Studio', '', '', 0, $prefix),
    ];

    $long_copy = mds2_source_long_ad_copy();

    // status / approved / published combinations MDS 2 really produced.
    $orders = [
        ['order_id' => 1, 'status' => 'paid', 'approved' => 'Y', 'published' => 'Y', 'ad' => 'harbour', 'blocks' => [0, 1], 'package' => 1, 'price' => 360, 'order_date' => '2025-03-14 09:12:00'],
        ['order_id' => 2, 'status' => 'completed', 'approved' => 'Y', 'published' => 'Y', 'ad' => 'ridgeline', 'blocks' => [21], 'package' => 2, 'price' => 75, 'order_date' => '2025-04-02 15:40:00'],
        ['order_id' => 3, 'status' => 'pending', 'approved' => 'N', 'published' => '', 'ad' => 'northside', 'blocks' => [42], 'package' => 2, 'price' => 75, 'order_date' => '2025-05-19 11:05:00'],
        ['order_id' => 4, 'status' => 'expired', 'approved' => 'Y', 'published' => 'Y', 'ad' => 'harbour', 'blocks' => [63], 'package' => 1, 'price' => 180, 'order_date' => '2025-01-08 08:30:00'],
        ['order_id' => 5, 'status' => 'cancelled', 'approved' => 'N', 'published' => '', 'ad' => 'pier9', 'blocks' => [84], 'package' => 2, 'price' => 75, 'order_date' => '2025-06-01 10:00:00'],
        ['order_id' => 6, 'status' => 'denied', 'approved' => 'N', 'published' => '', 'ad' => 'empty', 'blocks' => [105], 'package' => 2, 'price' => 75, 'order_date' => '2025-06-11 12:00:00'],
        ['order_id' => 7, 'status' => 'renew_paid', 'approved' => 'Y', 'published' => 'Y', 'ad' => 'pier9', 'blocks' => [7, 8, 9], 'package' => 1, 'price' => 540, 'order_date' => '2025-07-04 16:25:00'],
        // The client's shape: order rows whose ad_id is 0 or NULL, with the ad
        // information only reachable from the block rows.
        ['order_id' => 8, 'status' => 'paid', 'approved' => 'Y', 'published' => 'Y', 'ad' => null, 'blocks' => [126], 'package' => 2, 'price' => 75, 'order_date' => '2025-08-01 09:00:00'],
        ['order_id' => 9, 'status' => 'paid', 'approved' => 'Y', 'published' => 'Y', 'ad' => 'ridgeline', 'blocks' => [147, 148], 'package' => 1, 'price' => 180, 'order_date' => '2025-08-22 13:45:00'],
    ];

    $ad_ids = [];
    foreach ($orders as $order) {
        $ad_id = $order['ad'] ? (int) $advertisers[$order['ad']]['id'] : 0;
        $ad_ids[$order['order_id']] = $ad_id;
        $wpdb->insert($prefix . 'orders', [
            'user_id' => 1,
            'order_id' => $order['order_id'],
            'blocks' => '[' . implode(',', $order['blocks']) . ']',
            'status' => $order['status'],
            'order_date' => $order['order_date'],
            'price' => $order['price'],
            'quantity' => count($order['blocks']),
            'banner_id' => $grid_id,
            'currency' => 'CAD',
            'days_expire' => 90,
            'date_published' => $order['order_date'],
            'package_id' => $order['package'],
            'ad_id' => $ad_id ? $ad_id : null,
            'approved' => $order['approved'],
            'published' => $order['published'],
            'subscr_status' => 'paid' === $order['status'] ? 'active' : '',
            'original_order_id' => $order['order_id'],
            'block_info' => '',
        ]);
        $wpdb->insert($prefix . 'transactions', ['order_id' => $order['order_id']]);
    }

    // How each block carries its picture decides which legacy media path resolves
    // it: the ad record, a file_name on the block, or base64 in image_data.
    $flattened = base64_encode((string) file_get_contents($images[basename($attached_relative)]));
    $block_rows = [];
    foreach ($orders as $order) {
        $ad_id = $ad_ids[$order['order_id']];
        $ad_text = $order['ad'] ? wp_strip_all_tags($advertisers[$order['ad']]['text']) : '';
        $ad_url = $order['ad'] ? $advertisers[$order['ad']]['url'] : '';
        foreach ($order['blocks'] as $index => $block_id) {
            $block_rows[] = [
                'block_id' => $block_id,
                'order_id' => $order['order_id'],
                'ad_id' => $ad_id,
                'status' => 'paid' === $order['status'] || 'completed' === $order['status'] || 'renew_paid' === $order['status'] ? 'sold' : ('expired' === $order['status'] ? 'cancelled' : 'reserved'),
                'url' => $ad_url,
                'alt_text' => $ad_text,
                'image_data' => 'northside' === $order['ad'] ? $flattened : '',
                'file_name' => 'ridgeline' === $order['ad'] ? $file_name_relative : '',
                'mime_type' => 'ridgeline' === $order['ad'] ? 'image/png' : '',
                'approved' => $order['approved'],
                'published' => $order['published'],
                'price' => $order['price'] / max(1, count($order['blocks'])),
                'clicks' => 40 + 7 * $block_id,
                'views' => 1200 + 31 * $block_id,
            ];
        }
    }
    // Two unsold blocks and an out-for-service block: inventory that must not
    // become a placement or an order item.
    $block_rows[] = ['block_id' => 2, 'order_id' => 0, 'ad_id' => 0, 'status' => 'nfs', 'url' => '', 'alt_text' => '', 'image_data' => '', 'file_name' => '', 'mime_type' => '', 'approved' => '', 'published' => '', 'price' => 0, 'clicks' => 0, 'views' => 0];
    $block_rows[] = ['block_id' => 3, 'order_id' => 0, 'ad_id' => 0, 'status' => 'free', 'url' => '', 'alt_text' => '', 'image_data' => '', 'file_name' => '', 'mime_type' => '', 'approved' => '', 'published' => '', 'price' => 0, 'clicks' => 0, 'views' => 0];

    foreach ($block_rows as $row) {
        $wpdb->insert($prefix . 'blocks', [
            'block_id' => $row['block_id'],
            'banner_id' => $grid_id,
            'user_id' => 1,
            'status' => $row['status'],
            // MDS 2 geometry: pixels, derived from the block id.
            'x' => ($row['block_id'] % $grid_blocks_wide) * $block_px_wide,
            'y' => intdiv($row['block_id'], $grid_blocks_wide) * $block_px_high,
            'image_data' => $row['image_data'],
            'url' => $row['url'],
            'alt_text' => $row['alt_text'],
            'file_name' => $row['file_name'],
            'mime_type' => $row['mime_type'],
            'approved' => $row['approved'],
            'published' => $row['published'],
            'currency' => 'CAD',
            'order_id' => $row['order_id'],
            'price' => $row['price'],
            'ad_id' => $row['ad_id'],
            'click_count' => $row['clicks'],
            'view_count' => $row['views'],
        ]);
        if ($row['clicks'] > 0) {
            $wpdb->insert($prefix . 'clicks', [
                'banner_id' => $grid_id, 'block_id' => $row['block_id'], 'user_id' => 1,
                'date' => '2025-09-01', 'clicks' => $row['clicks'],
            ]);
        }
        if ($row['views'] > 0) {
            $wpdb->insert($prefix . 'views', [
                'banner_id' => $grid_id, 'block_id' => $row['block_id'], 'user_id' => 1,
                'date' => '2025-09-01', 'views' => $row['views'],
            ]);
        }
    }

    return [
        'grid_id' => $grid_id,
        'grid_blocks_wide' => $grid_blocks_wide,
        'block_px_wide' => $block_px_wide,
        'block_px_high' => $block_px_high,
        'advertisers' => $advertisers,
        'ad_ids' => $ad_ids,
        'orders' => $orders,
        'order_count' => count($orders),
        'block_count' => count($block_rows),
        'attached_attachment_id' => (int) $attached_id,
        'path_only_attachment_id' => (int) $path_only_id,
        'created_attachments' => [(int) $attached_id, (int) $path_only_id],
        'created_files' => [$attached_relative, $file_name_relative],
        'image_relative' => $attached_relative,
        'file_name_relative' => $file_name_relative,
        'long_ad_copy' => $long_copy,
        'oversized_link_url' => $advertisers['pier9']['url'],
    ];
}

/**
 * Remove the tables, attachments and upload files this dataset created.
 */
function mds2_source_cleanup($prefix, array $seed) {
    global $wpdb;

    foreach (['banners', 'blocks', 'clicks', 'views', 'orders', 'packages', 'prices', 'transactions'] as $suffix) {
        $wpdb->query('DROP TABLE IF EXISTS ' . DB::ident($prefix . $suffix));
    }

    $upload = wp_upload_dir();
    foreach ((array) ($seed['created_files'] ?? []) as $relative) {
        $path = trailingslashit($upload['basedir']) . ltrim((string) $relative, '/');
        if (is_readable($path)) {
            @unlink($path);
        }
    }
    foreach ((array) ($seed['created_attachments'] ?? []) as $attachment_id) {
        if (absint($attachment_id)) {
            wp_delete_attachment(absint($attachment_id), true);
        }
    }
    mds2_source_delete_advertisers($prefix);
}
