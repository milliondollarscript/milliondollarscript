<?php
/**
 * MDS2 source discovery and normalization.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration;

use MillionDollarScript\V3\Migration\Concerns\DetectsLegacyPages;
use MillionDollarScript\V3\Migration\Concerns\ReportsLegacySource;

if (!defined('ABSPATH')) {
    exit;
}

final class LegacySource {
    use DetectsLegacyPages;
    use ReportsLegacySource;

    private string $source_prefix;

    private const CORE_TABLES = [
        'banners',
        'blocks',
        'clicks',
        'mail_queue',
        'orders',
        'packages',
        'prices',
        'transactions',
        'views',
        'email_disable_tokens',
        'scan_history',
        'extension_licenses',
        'ads',
        'categories',
        'codes',
        'codes_translations',
        'currencies',
        'form_fields',
        'form_field_translations',
        'form_lists',
        'lang',
        'temp_orders',
        'users',
    ];

    public function __construct($source_prefix = '') {
        global $wpdb;

        $source_prefix = preg_replace('/[^A-Za-z0-9_]/', '', (string) $source_prefix);
        $this->source_prefix = $source_prefix ? $source_prefix : $wpdb->prefix . 'mds_';
    }

    public function source_prefix() {
        return $this->source_prefix;
    }

    public function table($suffix) {
        return $this->source_prefix . preg_replace('/[^a-z0-9_]/', '', strtolower((string) $suffix));
    }

    public function page_metadata_table() {
        global $wpdb;

        return $wpdb->prefix . 'mds_page_metadata';
    }

    public function page_config_table() {
        global $wpdb;

        return $wpdb->prefix . 'mds_page_config';
    }

    public function page_detection_log_table() {
        global $wpdb;

        return $wpdb->prefix . 'mds_detection_log';
    }
}
