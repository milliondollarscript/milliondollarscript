<?php
/**
 * WP-CLI fixture: which copy of an ad's picture a grid is told to draw.
 *
 * Why this exists: the customer site that prompted this has 2,446 blocks across seven grids, and
 * their report asked for a small copy, a medium one and the full picture to be sent separately so
 * the grid draws the small one and the popup opens the original. The grid payload did send one
 * picture per ad - the original, always. On a site whose uploads came over from the older plugin
 * those are often several times larger than any cell they are drawn in, and a page with a few
 * thousand live ads then fetches a large file per ad.
 *
 * What this pins down:
 *   1. a picture that has smaller copies gets one, sized for the cell, and it is not the original;
 *   2. the original is still described alongside it, unchanged - this is about drawing, not about
 *      what a site keeps or what someone sees when they open the ad;
 *   3. an upload with no smaller copies gets the original and nothing else, because a migrated
 *      media library is allowed to be like that and the ad still has to appear;
 *   4. a cell bigger than the picture gets the picture;
 *   5. a caller that does not name a size gets the old answer, so nothing changes for a caller
 *      that has not asked.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/placement-image-variant-fixture.php
 */

use MillionDollarScript\V3\Media\OriginalImage;

if (!defined('ABSPATH')) {
    echo "This fixture must run inside a WordPress root (wp eval-file).\n";
    exit(1);
}

global $wpdb;

if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
    echo "placement image variant fixture skipped: this site has no GD to make a picture with.\n";
    return;
}

$fails = [];
$check = static function ($ok, $message) use (&$fails) {
    if (!$ok) {
        $fails[] = $message;
    }
};

/**
 * A real upload at a real size, with whatever smaller copies this WordPress would normally make.
 * Deliberately bigger than any grid cell so the difference is not a rounding error.
 */
$make_upload = static function ($file_name, $width, $height, $make_sizes) {
    $image = imagecreatetruecolor($width, $height);
    $fill = imagecolorallocate($image, 40, 90, 140);
    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $fill);
    $upload = wp_upload_bits($file_name, null, '');
    if (empty($upload['file'])) {
        throw new RuntimeException('Could not reserve a path for the fixture picture.');
    }
    imagejpeg($image, $upload['file'], 88);
    imagedestroy($image);

    $attachment_id = wp_insert_attachment([
        'post_title' => 'MDS3 fixture ad picture',
        'post_mime_type' => 'image/jpeg',
        'post_status' => 'inherit',
    ], $upload['file']);
    if (is_wp_error($attachment_id)) {
        throw new RuntimeException('Could not create the fixture attachment.');
    }

    if ($make_sizes) {
        $metadata = wp_generate_attachment_metadata(absint($attachment_id), $upload['file']);
        wp_update_attachment_metadata(absint($attachment_id), $metadata);
    }

    return absint($attachment_id);
};

$resolver = new OriginalImage();
$made = [];

$sized_id = $make_upload('mds3-variant-fixture-large.jpg', 1600, 1200, true);
$made[] = $sized_id;
$bare_id = $make_upload('mds3-variant-fixture-bare.jpg', 1600, 1200, false);
$made[] = $bare_id;

$cleanup = static function () use ($made) {
    foreach ($made as $attachment_id) {
        wp_delete_attachment(absint($attachment_id), true);
    }
};

try {
    $original = $resolver->resolve($sized_id);
    if (!$original || empty($original['url'])) {
        throw new RuntimeException('The fixture picture could not be resolved at all.');
    }
    $check(1600 === absint($original['width']), 'the fixture picture is not the size it was made at: ' . var_export($original['width'], true));

    // 1 and 2. A cell the size of an ad on a real grid gets a copy sized for it, and the
    // original stays in the answer.
    $for_cell = $resolver->resolve($sized_id, 200, 200);
    $check(!empty($for_cell['display']['url']), 'a 200px cell was offered the full picture with no smaller copy');
    $check(($for_cell['display']['url'] ?? '') !== ($for_cell['url'] ?? ''), 'the copy offered for a small cell was the original');
    $check(($for_cell['url'] ?? '') === ($original['url'] ?? ''), 'the original stopped being described once a smaller copy existed');
    $check(absint($for_cell['display']['width'] ?? 0) < 1600, 'the copy offered is not smaller than the original: ' . var_export($for_cell['display'] ?? null, true));
    $check(!empty($for_cell['display']['width']) && !empty($for_cell['display']['height']), 'the copy offered does not say its own size, which the page needs to keep the picture aligned');

    // 4. A cell bigger than the picture gets the picture; there is nothing smaller to offer.
    $for_big_cell = $resolver->resolve($sized_id, 4000, 4000);
    $check(!isset($for_big_cell['display']), 'a cell larger than the picture was offered something smaller than the picture');
    $check(($for_big_cell['url'] ?? '') === ($original['url'] ?? ''), 'an oversized cell stopped describing the original');

    // 5. A caller that names no size is answered exactly as before.
    $unasked = $resolver->resolve($sized_id);
    $check(is_array($unasked) && !isset($unasked['display']), 'a caller that asked for no size was handed a display copy anyway');
    $check(($unasked['url'] ?? '') === ($original['url'] ?? ''), 'a caller that asked for no size got a different answer than before');

    // 3. An upload with no smaller copies - what a migrated library often holds - keeps the original.
    $bare = $resolver->resolve($bare_id);
    $bare_cell = $resolver->resolve($bare_id, 200, 200);
    $check(!isset($bare['display']) && !isset($bare_cell['display']), 'a picture with no smaller copies was offered a copy that does not exist');
    $check(!empty($bare['url']) && ($bare['url'] ?? '') === ($bare_cell['url'] ?? ''), 'a picture with no smaller copies changed its own answer depending on the cell');
    $check(!empty($bare_cell['url']), 'the ad with no smaller copies was left with nothing to draw');
} catch (Throwable $e) {
    $cleanup();
    echo 'placement image variant fixture errored: ' . $e->getMessage() . "\n";
    exit(1);
}

$cleanup();

if ($fails) {
    echo "placement image variant fixture FAILED\n";
    foreach ($fails as $fail) {
        echo ' - ' . $fail . "\n";
    }
    exit(1);
}

echo "placement image variant fixture passed.\n";
