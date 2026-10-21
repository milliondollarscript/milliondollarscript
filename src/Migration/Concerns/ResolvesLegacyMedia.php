<?php
/**
 * MDS2 media and attachment lookup helpers.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration\Concerns;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

trait ResolvesLegacyMedia {
    // The ad image meta keys live as IMAGE_META_KEYS on the class that composes
    // this trait together with ReportsMigrationCoverage, so the importer and the
    // coverage check read the same list. (A trait cannot hold a constant before
    // PHP 8.2, and the plugin supports PHP 7.4.)

    private function attachment_for_order(array $order) {
        $ad_id = absint($order['ad_id'] ?? 0);
        $attachment_id = $this->attachment_from_ad($ad_id);
        if (!$attachment_id && $ad_id) {
            $discovered = $this->attachment_from_ad_image_field($ad_id);
            if ($discovered) {
                $attachment_id = (int) $discovered['attachment_id'];
                $this->record_migration_repair('placement', absint($order['order_id'] ?? 0), sprintf('The ad picture was taken from the "%1$s" field on the older ad record. That older plugin names the field after the form on the site that collected it, so the name is not fixed - if this is not the right picture, upload the intended one on this placement.', $discovered['meta_key']));
            }
        }
        if ($attachment_id) {
            return $attachment_id;
        }

        $file = $this->first_order_block_value(absint($order['order_id'] ?? 0), 'file_name');
        if ($file && ($attachment_id = $this->attachment_from_path($file))) {
            return $attachment_id;
        }

        $left_behind = $this->legacy_order_upload_name(absint($order['order_id'] ?? 0));
        if ($left_behind && ($attachment_id = $this->attachment_from_path($left_behind))) {
            $this->record_migration_repair(
                'placement',
                absint($order['order_id'] ?? 0),
                sprintf(
                    /* translators: %s: file name the older plugin had kept. */
                    __('The ad picture was taken from the file the older plugin had kept for this order (%s), recognised by the order number in its name. The older records did not say which file belonged to this ad, so this one was recognised from its name only.', 'million-dollar-script'),
                    $left_behind
                )
            );
            return $attachment_id;
        }

        $grid = $this->legacy_grids[absint($order['banner_id'] ?? 0)] ?? [];

        $attachment_id = $this->attachment_from_flattened_image_data(
            absint($order['order_id'] ?? 0),
            absint($grid['block_width'] ?? 0),
            absint($grid['block_height'] ?? 0)
        );
        if ($attachment_id) {
            return $attachment_id;
        }

        // The block rows held nothing. The order row itself may still hold the
        // selection the customer made: Million Dollar Script 2 wrote its normalised
        // block payload - coordinates and the image - into orders.block_info on every
        // selection, and an order whose block rows were never written (a partial
        // write, an interrupted order flow) has its picture only there.
        return $this->attachment_from_order_block_info(
            absint($order['order_id'] ?? 0),
            absint($grid['block_width'] ?? 0),
            absint($grid['block_height'] ?? 0)
        );
    }

    /**
     * The folders this version of the older plugin wrote ad pictures into.
     *
     * Both are read from the older plugin's own code, not guessed: Core/include/ads.inc.php
     * moves an ad upload to Utility::get_upload_path() . 'images/', and the form upload flow
     * (src/Classes/Forms/Forms.php) uses FileValidator::get_secure_upload_path('images'), a
     * folder protected by its own .htaccess. get_upload_path() starts at
     * uploads/milliondollarscript and passes it through the mds_upload_path filter, which a
     * site can use to keep its ad files elsewhere - so the filtered value is asked for when
     * the older plugin is loaded, and used when it still sits inside uploads (a folder outside
     * uploads cannot be named as a path relative to it).
     *
     * Returns a list of ['relative' => path under uploads/, 'absolute' => folder on disk].
     */
    private function legacy_image_directories() {
        $upload = wp_get_upload_dir();
        $basedir = trailingslashit((string) ($upload['basedir'] ?? ''));
        if ('' === $basedir) {
            return [];
        }

        $roots = [[trailingslashit($basedir) . 'milliondollarscript/', 'milliondollarscript/']];
        if (has_filter('mds_upload_path')) {
            $filtered = (string) apply_filters('mds_upload_path', trailingslashit($basedir) . 'milliondollarscript');
            $filtered = '' === $filtered ? '' : trailingslashit(wp_normalize_path($filtered));
            if ('' !== $filtered && 0 === strpos($filtered, $basedir)) {
                array_unshift($roots, [$filtered, substr($filtered, strlen($basedir))]);
            }
        }

        $directories = [];
        foreach ($roots as [$root, $relative]) {
            $directories[] = ['absolute' => $root . 'images/', 'relative' => $relative . 'images/'];
        }
        $directories[] = ['absolute' => $basedir . 'mds-secure/images/', 'relative' => 'mds-secure/images/'];

        return $directories;
    }

    /**
     * The file the older plugin kept for this order, if its folder still holds it.
     *
     * MDS2 named an ad upload tmp_<order id>_<timestamp>_<random>.<ext> -
     * FileValidator::generate_secure_filename() called from Core/include/ads.inc.php with the
     * order id as the prefix - and on real sites that file is often the only copy of the
     * picture that survives: the customer copy has blocks.file_name empty on all 2,446 rows,
     * so the recorded-path lookup above has nothing to follow and the import would otherwise
     * settle for the cell-size copy MDS2 kept inside its block rows, which is what makes an ad
     * soft when it is shown larger than one cell. The order number in the name is the link,
     * and it is one MDS2 wrote itself.
     *
     * Where an order was uploaded more than once the newest file is the one that site was
     * showing, whichever of the two folders it is in. Returns a path relative to uploads/, or
     * '' when there is nothing to find.
     */
    private function legacy_order_upload_name($legacy_order_id) {
        $legacy_order_id = absint($legacy_order_id);
        if (!$legacy_order_id) {
            return '';
        }

        $candidates = [];
        foreach ($this->legacy_image_directories() as $directory) {
            $found = glob($directory['absolute'] . 'tmp_' . $legacy_order_id . '_*');
            foreach ((array) $found as $file) {
                if (!is_file($file) || !is_readable($file) || 0 >= (int) filesize($file)) {
                    continue;
                }
                $candidates[] = [
                    'name' => $directory['relative'] . basename($file),
                    'mtime' => (int) filemtime($file),
                    'size' => (int) filesize($file),
                ];
            }
        }
        if (!$candidates) {
            return '';
        }
        usort(
            $candidates,
            static function (array $first, array $second) {
                if ($first['mtime'] !== $second['mtime']) {
                    return $second['mtime'] <=> $first['mtime'];
                }

                return $second['size'] <=> $first['size'];
            }
        );

        return $candidates[0]['name'];
    }

    /**
     * Last-resort media recovery for MDS2 blocks.
     *
     * MDS2 stored the ad image itself as base64 in `blocks.image_data` (see the legacy
     * GridImageGenerator / get-order-image.php). When neither the ad post meta nor a
     * resolvable block file_name points at media, decode that payload and register it
     * as an attachment so the placement keeps its original image.
     *
     * That payload is a crop at cell size, not the ad's own file: the old grid
     * generator cut the uploaded image to one cell per block. So this recovers a
     * picture that exists and is right in content, and says so when it is no
     * larger than a cell - an ad shown across several cells will be soft, and the
     * owner should learn that from the report instead of from the site.
     */
    private function attachment_from_flattened_image_data($legacy_order_id, $cell_width = 0, $cell_height = 0) {
        global $wpdb;

        $legacy_order_id = absint($legacy_order_id);
        $table = $this->source->table('blocks');
        if (!DB::table_exists($table)) {
            return 0;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT image_data, block_id FROM ' . DB::ident($table) . " WHERE order_id = %d AND image_data <> '' ORDER BY block_id ASC LIMIT 1",
                $legacy_order_id
            ),
            ARRAY_A
        );
        if (!$row) {
            return 0;
        }

        $binary = base64_decode((string) $row['image_data'], true);
        if (false === $binary) {
            return 0;
        }
        $info = @getimagesizefromstring($binary);
        if (!is_array($info)) {
            return 0;
        }

        $upload = wp_upload_dir();
        if (is_wp_error($upload)) {
            return 0;
        }
        $directory = trailingslashit($upload['basedir']) . 'milliondollarscript/migrated';
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            return 0;
        }
        $extension = 'image/jpeg' === $info['mime'] ? 'jpg' : ('image/gif' === $info['mime'] ? 'gif' : 'png');
        $target = $directory . '/order-' . $legacy_order_id . '-block-' . absint($row['block_id']) . '.' . $extension;
        if (!file_exists($target) && false === file_put_contents($target, $binary)) {
            return 0;
        }

        // Reuses the existing path/attachment resolution (idempotent on re-import).
        $attachment_id = $this->attachment_from_path($target);
        if ($attachment_id) {
            $this->record_cell_size_copy($legacy_order_id, absint($info[0] ?? 0), absint($info[1] ?? 0), absint($cell_width), absint($cell_height));
        }

        return $attachment_id;
    }

    /**
     * Report a picture that could only be recovered at cell size.
     *
     * Recorded as a substitution, not a warning or a skip: the ad kept its image,
     * and what changed is the quality of what was available to keep.
     */
    private function record_cell_size_copy($legacy_order_id, $width, $height, $cell_width, $cell_height, string $where = ''): void {
        if (!$cell_width || !$cell_height || $width > $cell_width || $height > $cell_height) {
            return;
        }

        $this->cell_size_copies[absint($legacy_order_id)] = max($width, $height);

        $this->record_migration_repair('placement', $legacy_order_id, sprintf(
            'The ad picture was restored from a copy kept at cell size (%1$d by %2$d pixels), which is all Million Dollar Script 2 stored for this block.%3$s It is the right picture, and it will look soft where the ad is shown larger than one cell; upload a larger image on the placement to replace it.',
            (int) $width,
            (int) $height,
            '' === $where ? '' : ' ' . $where
        ));
    }

    /**
     * Read the block payload the order row kept, and take its picture if it has one.
     *
     * This is the last media source there is, and it is read defensively: the column
     * is a plain text field written by an older plugin over several versions, so the
     * same idea appears as a PHP-serialized map (what the customer dumps hold), as
     * JSON, or empty. Rows that parse as neither are ignored rather than guessed at.
     *
     * Only the picture is taken from here. Block rows still come from the older
     * blocks table - making blocks out of this payload would leave the grid inventory
     * and the coverage counts disagreeing about how many cells an ad held.
     */
    private function attachment_from_order_block_info($legacy_order_id, $cell_width = 0, $cell_height = 0) {
        global $wpdb;

        $legacy_order_id = absint($legacy_order_id);
        $orders_table = $this->source->table('orders');
        if (!DB::table_exists($orders_table)) {
            return 0;
        }

        $stored = (string) $wpdb->get_var(
            $wpdb->prepare('SELECT block_info FROM ' . DB::ident($orders_table) . ' WHERE order_id = %d LIMIT 1', $legacy_order_id)
        );
        if ('' === trim($stored)) {
            return 0;
        }

        $payload = $this->parse_legacy_block_payload($stored);
        // An unreadable payload is not reported here: when nothing else found a
        // picture either, the run already says so once, and a second line about how
        // the older column was encoded would be a guess about its format.
        if ([] === $payload) {
            return 0;
        }

        foreach ($payload as $block_key => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $encoded = trim((string) ($entry['image_data'] ?? ''));
            if ('' === $encoded) {
                continue;
            }
            $binary = base64_decode($encoded, true);
            if (false === $binary || '' === $binary) {
                continue;
            }
            $info = @getimagesizefromstring($binary);
            if (!is_array($info)) {
                continue;
            }

            $upload = wp_upload_dir();
            if (is_wp_error($upload)) {
                return 0;
            }
            $directory = trailingslashit($upload['basedir']) . 'milliondollarscript/migrated';
            if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
                return 0;
            }
            $extension = 'image/jpeg' === $info['mime'] ? 'jpg' : ('image/gif' === $info['mime'] ? 'gif' : 'png');
            $target = $directory . '/order-' . $legacy_order_id . '-block-' . absint($block_key) . '.' . $extension;
            if (!file_exists($target) && false === file_put_contents($target, $binary)) {
                return 0;
            }

            $attachment_id = $this->attachment_from_path($target);
            if ($attachment_id) {
                $this->record_cell_size_copy(
                    $legacy_order_id,
                    absint($info[0] ?? 0),
                    absint($info[1] ?? 0),
                    absint($cell_width),
                    absint($cell_height),
                    sprintf(
                        /* translators: 1: block number in the older order record. */
                        __('It was kept in the order record for block %d, not in the grid records.', 'million-dollar-script'),
                        absint($block_key)
                    )
                );
            }

            return $attachment_id;
        }

        return 0;
    }

    /**
     * Parse an older order's block payload without assuming how it was stored.
     *
     * Returns the map keyed by block id, or an empty array when the text is not a
     * readable map. An empty payload is not a failure - most orders simply have none.
     */
    private function parse_legacy_block_payload(string $stored): array {
        $stored = trim($stored);
        if ('' === $stored) {
            return [];
        }

        $decoded = null;
        if (0 === strpos($stored, 'a:') || false !== strpos($stored, ';a:')) {
            $decoded = @unserialize($stored, ['allowed_classes' => false]);
        }
        if (!is_array($decoded) && ('{' === $stored[0] || '[' === $stored[0])) {
            $decoded = json_decode($stored, true);
        }
        if (is_array($decoded)) {
            return $decoded;
        }

        // A single cell saved as a bare payload, or an older encoding we can still
        // read the image out of. Take the image rather than lose the picture.
        if (1 === preg_match('/"image_data";s:\d+:"([A-Za-z0-9+\/=]{16,})"/', $stored, $match)) {
            return [0 => ['image_data' => $match[1]]];
        }

        return [];
    }

    /**
     * Find a picture on the older ad record when it is not under a known key.
     *
     * The older plugin writes that record through its generic form system, so the field
     * holding a picture is named after the form field on that site - there is no fixed key
     * to carry across. Prefer a field whose own name says what it is, and take an unnamed
     * field only when the record offers exactly one image-shaped value. Whichever is used is
     * named in the report, so a field that means something else is visible rather than
     * quietly pasted onto the ad.
     *
     * @return array<string, mixed> attachment_id and meta_key, or an empty array
     */
    private function attachment_from_ad_image_field($ad_id) {
        $ad_id = absint($ad_id);
        $all_meta = $ad_id ? get_post_meta($ad_id) : [];
        if (!is_array($all_meta) || !$all_meta) {
            return [];
        }

        $named = [];
        $unnamed = [];
        foreach (array_keys($all_meta) as $key) {
            $key = (string) $key;
            // Underscored keys belong to WordPress or to the known list above.
            if ('' === $key || 0 === strpos($key, '_') || in_array($key, self::IMAGE_META_KEYS, true)) {
                continue;
            }
            $attachment_id = 0;
            foreach ((array) get_post_meta($ad_id, $key) as $value) {
                $attachment_id = $this->attachment_id_from_value($value);
                if ($attachment_id) {
                    break;
                }
            }
            if (!$attachment_id) {
                continue;
            }
            if (preg_match('/image|banner|photo|graphic|picture|thumbnail|artwork|upload|file/i', $key)) {
                $named[] = ['meta_key' => $key, 'attachment_id' => $attachment_id];
            } else {
                $unnamed[] = ['meta_key' => $key, 'attachment_id' => $attachment_id];
            }
        }

        if ($named) {
            usort($named, function (array $first, array $second) {
                return strcmp($first['meta_key'], $second['meta_key']);
            });

            return $named[0];
        }

        return 1 === count($unnamed) ? $unnamed[0] : [];
    }

    private function attachment_from_ad($ad_id) {
        $ad_id = absint($ad_id);
        if (!$ad_id || !get_post($ad_id)) {
            return 0;
        }

        foreach (self::IMAGE_META_KEYS as $key) {
            $value = get_post_meta($ad_id, $key, true);
            $attachment_id = $this->attachment_id_from_value($value);
            if ($attachment_id) {
                return $attachment_id;
            }
        }

        return 0;
    }

    private function attachment_id_from_value($value) {
        if (is_array($value)) {
            foreach ($value as $item) {
                $attachment_id = $this->attachment_id_from_value($item);
                if ($attachment_id) {
                    return $attachment_id;
                }
            }

            return 0;
        }

        if (is_numeric($value) && wp_attachment_is_image(absint($value))) {
            return absint($value);
        }

        if (is_string($value) && preg_match('#^https?://#', $value)) {
            $attachment_id = attachment_url_to_postid($value);

            return $attachment_id && wp_attachment_is_image($attachment_id) ? absint($attachment_id) : 0;
        }

        if (is_string($value) && '' !== trim($value)) {
            return $this->attachment_from_path($value);
        }

        return 0;
    }

    private function attachment_from_path($path) {
        global $wpdb;

        $path = trim((string) $path);
        if (!$path) {
            return 0;
        }

        if (preg_match('#^https?://#', $path)) {
            $attachment_id = attachment_url_to_postid($path);

            return $attachment_id && wp_attachment_is_image($attachment_id) ? absint($attachment_id) : 0;
        }

        $upload = wp_upload_dir();
        $normalized_path = $this->normalize_legacy_media_path($path);
        $relative_upload_path = $this->relative_legacy_upload_path($normalized_path, $upload);
        $candidates = [];
        if ($this->legacy_path_is_absolute($normalized_path)) {
            $candidates[] = $normalized_path;
        }

        if ($relative_upload_path) {
            $candidates[] = trailingslashit($upload['basedir']) . $relative_upload_path;
        } elseif (!$this->legacy_path_is_absolute($normalized_path)) {
            $candidates[] = trailingslashit($upload['basedir']) . ltrim($normalized_path, '/');
        } else {
            $candidates[] = trailingslashit($upload['basedir']) . 'milliondollarscript/images/' . basename($normalized_path);
        }
        // A recorded file name is often only a name. The older plugin kept ad pictures in its
        // own images folder - uploads/milliondollarscript/images/, or the folder protected by
        // its own .htaccess when the site collected the picture through a form - so look in
        // each of those before giving up on a file that is really there.
        foreach ($this->legacy_image_directories() as $image_directory) {
            $candidates[] = $image_directory['absolute'] . basename($normalized_path);
        }
        if (!$this->legacy_path_is_absolute($normalized_path) && !$relative_upload_path) {
            $candidates[] = trailingslashit($upload['basedir']) . 'milliondollarscript/' . ltrim($normalized_path, '/');
        }

        foreach (array_unique($candidates) as $candidate) {
            $candidate = $this->normalize_legacy_media_path($candidate);
            if (!is_readable($candidate)) {
                continue;
            }

            $relative = $this->relative_legacy_upload_path($candidate, $upload);
            if (!$relative) {
                continue;
            }

            $attachment_id = absint($wpdb->get_var(
                $wpdb->prepare(
                    'SELECT post_id FROM ' . DB::ident($wpdb->postmeta) . ' WHERE meta_key = %s AND meta_value = %s LIMIT 1',
                    '_wp_attached_file',
                    $relative
                )
            ));

            if ($attachment_id && wp_attachment_is_image($attachment_id)) {
                return $attachment_id;
            }

            $registered = $this->register_attachment($candidate, $relative);
            if ($registered) {
                return $registered;
            }
        }

        return 0;
    }

    private function normalize_legacy_media_path($path) {
        $path = (string) $path;

        return function_exists('wp_normalize_path') ? wp_normalize_path($path) : str_replace('\\', '/', $path);
    }

    private function legacy_path_is_absolute($path) {
        $path = $this->normalize_legacy_media_path($path);

        return str_starts_with($path, '/') || (bool) preg_match('/^[A-Za-z]:\//', $path);
    }

    private function relative_legacy_upload_path($path, array $upload) {
        $path = $this->normalize_legacy_media_path($path);
        $base = trailingslashit($this->normalize_legacy_media_path($upload['basedir'] ?? ''));
        if ($base && str_starts_with($path, $base)) {
            return ltrim(substr($path, strlen($base)), '/');
        }

        if (preg_match('#(?:^|/)wp-content/uploads/(.+)$#i', $path, $matches)) {
            return ltrim($matches[1], '/');
        }

        return '';
    }

    private function register_attachment($path, $relative) {
        $filetype = wp_check_filetype($path);
        if (empty($filetype['type']) || 0 !== strpos($filetype['type'], 'image/')) {
            return 0;
        }

        $attachment_id = wp_insert_attachment([
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name(pathinfo($path, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        ], $path);

        if (is_wp_error($attachment_id) || !$attachment_id) {
            return 0;
        }

        update_post_meta($attachment_id, '_wp_attached_file', $relative);

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $path);
        if (is_array($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        return absint($attachment_id);
    }

    private function first_order_block_value($legacy_order_id, $field) {
        global $wpdb;

        $table = $this->source->table('blocks');
        if (!DB::table_exists($table)) {
            return '';
        }

        $field = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $field));
        if (!$field) {
            return '';
        }

        return (string) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT ' . DB::ident($field) . ' FROM ' . DB::ident($table) . ' WHERE order_id = %d AND ' . DB::ident($field) . " <> '' ORDER BY block_id ASC LIMIT 1",
                absint($legacy_order_id)
            )
        );
    }

    private function order_has_flattened_image_data($legacy_order_id) {
        global $wpdb;

        $table = $this->source->table('blocks');
        if (!DB::table_exists($table)) {
            return false;
        }

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT block_id FROM ' . DB::ident($table) . " WHERE order_id = %d AND image_data <> '' LIMIT 1",
                absint($legacy_order_id)
            )
        );
    }

    private function ad_meta($ad_id) {
        $ad_id = absint($ad_id);
        if (!$ad_id) {
            return [];
        }

        return [
            'url' => (string) (get_post_meta($ad_id, '_milliondollarscript_url', true) ?: get_post_meta($ad_id, 'milliondollarscript_url', true)),
            'text' => (string) (get_post_meta($ad_id, '_milliondollarscript_text', true) ?: get_post_meta($ad_id, 'milliondollarscript_text', true)),
        ];
    }

}
