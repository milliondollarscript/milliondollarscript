<?php
/**
 * MDS2 migration dry-run inventory.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration;

if (!defined('ABSPATH')) {
    exit;
}

final class DryRun {

    public function report($source_prefix = '') {
        $source = new LegacySource($source_prefix);
        $pages = $source->pages_report();

        return [
            'mode' => 'dry_run',
            'source_prefix' => $source->source_prefix(),
            'will_drop_mds2_tables' => false,
            'tables' => $source->table_report(),
            'options' => $source->options_report(),
            'pages' => $pages,
            'users_media' => $source->source_counts(),
            'target' => $source->target_report(),
            'warnings' => $this->warnings($source, $pages),
        ];
    }

    private function warnings(LegacySource $source, array $pages) {
        $warnings = [];
        $tables = $source->table_report();

        if (empty($tables['banners']['exists'])) {
            $warnings[] = __('No Million Dollar Script 2 banners table was found for this prefix.', 'million-dollar-script');
        }

        if (empty($tables['blocks']['exists'])) {
            $warnings[] = __('No Million Dollar Script 2 blocks table was found for this prefix.', 'million-dollar-script');
        }

        if (empty($pages['count'])) {
            $warnings[] = __('No Million Dollar Script 2 page-wizard pages were detected. The importer can still migrate grids and orders.', 'million-dollar-script');
        }

        return $warnings;
    }
}
