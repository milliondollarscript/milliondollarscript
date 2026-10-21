<?php
/**
 * WordPress personal-data export and erasure for the records this plugin owns.
 *
 * @package MillionDollarScript\V3\Privacy
 */

namespace MillionDollarScript\V3\Privacy;

use MillionDollarScript\V3\Support\Component;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Answers a personal-data request for the orders, placement submissions and uploaded
 * artwork this plugin holds.
 *
 * WordPress addresses a request by email. Two shapes of record answer to one: a
 * guest order carries an email directly, and an order placed while signed in carries
 * the user behind that email. Both are matched, so a person who once ordered as a
 * guest and later registered does not get half of their history (see
 * Concerns\PersonalRecordQuery for how they are matched).
 *
 * Extensions own their own data and register their own exporters and erasers, the
 * way twelve of them already do. This component covers what core wrote. Where an
 * extension stored something inside an order (a submitted custom field, for
 * example), core's eraser tells it the order is going away before it does, through
 * the million-dollar-script/privacy/order/erasing action.
 */
final class PersonalDataRequests implements Component
{
    use Concerns\PersonalRecordQuery;
    use Concerns\PersonalRecordExport;
    use Concerns\PersonalRecordErasure;

    public const GROUP_ID = 'million-dollar-script';
    public const GROUP_LABEL = 'Million Dollar Script 3.0';

    /**
     * Records are handled ten at a time, which is what WordPress asks for.
     */
    private const PAGE_SIZE = 10;

    public function register() {
        add_filter('wp_privacy_personal_data_exporters', [$this, 'register_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'register_eraser']);
    }

    /**
     * @param array $exporters
     * @return array
     */
    public function register_exporter($exporters) {
        $exporters[self::GROUP_ID] = [
            'exporter_friendly_name' => __('Million Dollar Script 3.0 orders and placements', 'million-dollar-script'),
            'callback' => [$this, 'export'],
        ];

        return $exporters;
    }

    /**
     * @param array $erasers
     * @return array
     */
    public function register_eraser($erasers) {
        $erasers[self::GROUP_ID] = [
            'eraser_friendly_name' => __('Million Dollar Script 3.0 orders and placements', 'million-dollar-script'),
            'callback' => [$this, 'erase'],
        ];

        return $erasers;
    }
}
