<?php
/**
 * Finding the records that answer to one email address.
 *
 * @package MillionDollarScript\V3\Privacy
 */

namespace MillionDollarScript\V3\Privacy\Concerns;

use MillionDollarScript\V3\Orders\OrderRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A request arrives as an email address, and this plugin's records answer to it in
 * two ways: an order placed without signing in stores the address itself, and an
 * order placed while signed in stores the user. Matching only one of the two would
 * hand back half of a person's history, so both are matched.
 *
 * The matching itself is OrderRepository's own owner filter - the same one the
 * admin order list uses - rather than a second query written here.
 */
trait PersonalRecordQuery
{
    /**
     * One page of the orders that belong to this address, oldest first.
     *
     * @return array<int,array>
     */
    private function matched_orders(string $email, int $page): array
    {
        $owner = $this->owner_args($email);
        if (null === $owner) {
            return [];
        }

        return (new OrderRepository())->query(array_merge($owner, [
            'limit' => self::PAGE_SIZE,
            'offset' => (max(1, $page) - 1) * self::PAGE_SIZE,
            'orderby' => 'id',
            'order' => 'asc',
        ]));
    }

    /**
     * How many orders answer to this address right now.
     */
    private function matched_count(string $email): int
    {
        $owner = $this->owner_args($email);

        return null === $owner ? 0 : (new OrderRepository())->count($owner);
    }

    /**
     * The repository filter for "ordered by this address, or by the account with
     * this address". An unusable address matches nothing, which is also the answer
     * for a request that was typed by hand.
     *
     * @return array|null
     */
    private function owner_args(string $email): ?array
    {
        $email = sanitize_email($email);
        if ('' === $email) {
            return null;
        }

        $args = ['owner_email' => $email];
        $user = get_user_by('email', $email);
        if ($user) {
            $args['owner_user_id'] = (int) $user->ID;
        }

        return $args;
    }
}
