<?php
/**
 * Removing the records this plugin holds when a person asks for their data.
 *
 * @package MillionDollarScript\V3\Privacy
 */

namespace MillionDollarScript\V3\Privacy\Concerns;

use MillionDollarScript\V3\Media\AdvertiserPageManager;
use MillionDollarScript\V3\Media\PlacementDraftRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\OrderRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * An erasure here removes the order, its lines, the placement it produced, the
 * advertiser page that ad was given, and the picture the person uploaded - which is
 * what they asked for when they asked to be forgotten.
 *
 * Three things are deliberately not removed:
 *
 * - The row that records a migrated import (migration_map). It is the site's note
 *   that a given old record was already carried over. Removing it would let a later
 *   migration re-create the ad this person just had deleted.
 * - Anything an extension collected through its own fields into its own tables; each
 *   extension answers for its own data, and this one tells it the order is going
 *   first, through the million-dollar-script/privacy/order/erasing action.
 * - An uploaded image that some other placement is still using. Deleting a shared
 *   file would break an ad that belongs to someone else.
 *
 * A site that must keep an order - for accounting, or because a court asked - can
 * take it out of the list the filter is handed. The request is then reported as
 * partly retained rather than silently unfinished.
 */
trait PersonalRecordErasure
{
    /**
     * @param string $email
     * @param int $page
     * @return array
     */
    public function erase($email, $page = 1)
    {
        $email = (string) $email;

        // Rows are always taken from the first page: this deletes what it finds, so
        // asking for page two after removing page one would skip records.
        $orders = $this->matched_orders($email, 1);
        $found = [];
        foreach ($orders as $order) {
            $order_id = absint($order['id'] ?? 0);
            if ($order_id) {
                $found[$order_id] = $order;
            }
        }

        if (!$found) {
            return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
        }

        // The filter is handed the orders that would go and returns the ones this
        // site is willing to remove; whatever it drops stays.
        $allowed = apply_filters('million-dollar-script/privacy/erase/order_ids', array_keys($found), $email);
        $allowed = array_values(array_unique(array_filter(array_map('absint', is_array($allowed) ? $allowed : []))));
        $deleting = array_values(array_intersect(array_keys($found), $allowed));
        $retained = count($found) - count($deleting);

        $counts = ['orders' => 0, 'placements' => 0, 'pages' => 0, 'uploads' => 0];
        foreach ($deleting as $order_id) {
            $counts = $this->erase_order($found[$order_id], $email, $counts);
        }

        $messages = [];
        if ($counts['orders'] || $counts['placements']) {
            $messages[] = sprintf(
                /* translators: 1: orders removed, 2: placements removed, 3: uploads removed */
                _n(
                    'Removed %1$d order, %2$d placement and %3$d uploaded image.',
                    'Removed %1$d orders, %2$d placements and %3$d uploaded images.',
                    $counts['orders'],
                    'million-dollar-script'
                ),
                $counts['orders'],
                $counts['placements'],
                $counts['uploads']
            );
        }
        if ($retained > 0) {
            $messages[] = sprintf(
                /* translators: %d: orders this site asked to keep */
                _n('%d order was kept by this site’s own rule and is not removed.', '%d orders were kept by this site’s own rule and are not removed.', $retained, 'million-dollar-script'),
                $retained
            );
        }

        return [
            'items_removed' => $counts['orders'] > 0,
            'items_retained' => $retained > 0,
            'messages' => $messages,
            'done' => $this->matched_count($email) === 0,
        ];
    }

    /**
     * One order, from the outside in: tell anything that depends on it, remove the
     * advertiser pages, remove the placements, then the order and its lines, and
     * last the uploads - which need the placements gone to know they are unused.
     *
     * @param array $order
     * @param string $email
     * @param array $counts
     * @return array
     */    private function erase_order(array $order, string $email, array $counts): array
    {
        $order_id = absint($order['id'] ?? 0);

        /**
         * Lets an extension that stored something against this order finish its own
         * business, or export what it holds, before the order disappears.
         */
        do_action('million-dollar-script/privacy/order/erasing', $order, $email);

        $placements = (new PlacementRepository())->for_order($order_id);
        $pages = new AdvertiserPageManager();
        $uploads = (new PlacementDraftRepository())->uploads_for_order($order_id);

        foreach ($placements as $placement) {
            $post_id = absint($placement['public_post_id'] ?? 0);
            if ($post_id && AdvertiserPageManager::POST_TYPE === get_post_type($post_id)) {
                if (wp_delete_post($post_id, true)) {
                    ++$counts['pages'];
                }
            }
            $uploads[] = absint($placement['attachment_id'] ?? 0);
        }

        $counts['placements'] += (new PlacementRepository())->delete_permanently(array_map(static function ($placement) {
            return absint($placement['id'] ?? 0);
        }, $placements));

        $removed = (new OrderRepository())->delete_permanently([$order_id]);
        $counts['orders'] += $removed['orders'];

        $still_used = (new PlacementRepository())->attachments_still_used($uploads);
        foreach (array_diff(array_unique(array_filter($uploads)), $still_used) as $attachment_id) {
            if (wp_delete_attachment($attachment_id, true)) {
                ++$counts['uploads'];
            }
        }

        return $counts;
    }
}
