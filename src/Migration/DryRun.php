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
        $database = $source->database_capability_report();

        return [
            'mode' => 'dry_run',
            'source_prefix' => $source->source_prefix(),
            'will_drop_mds2_tables' => false,
            'tables' => $source->table_report(),
            'options' => $source->options_report(),
            'pages' => $pages,
            'users_media' => $source->source_counts(),
            'target' => $source->target_report(),
            'database' => $database,
            'warnings' => $this->warnings($source, $database),
            'notices' => $this->notices($pages),
        ];
    }

    /**
     * Something the site is not doing wrong. The older site may have no page-wizard pages at
     * all and still be a complete, ordinary Million Dollar Script 2 site, so this belongs
     * with the explanation of the run rather than with the things to fix.
     */
    private function notices(array $pages) {
        $notices = [];

        if (empty($pages['count'])) {
            $notices[] = __('No Million Dollar Script 2 page-wizard pages were detected. The importer can still migrate grids and orders.', 'million-dollar-script');
        }

        return $notices;
    }

    private function warnings(LegacySource $source, array $database) {
        // What the destination cannot hold leads the list: it is the thing to fix first.
        $warnings = $this->capability_warnings($database);
        $tables = $source->table_report();

        if (empty($tables['banners']['exists'])) {
            $warnings[] = __('No Million Dollar Script 2 banners table was found for this prefix.', 'million-dollar-script');
        }

        if (empty($tables['blocks']['exists'])) {
            $warnings[] = __('No Million Dollar Script 2 blocks table was found for this prefix.', 'million-dollar-script');
        }

        return $warnings;
    }

    /**
     * Notices for a destination that cannot hold what the older site has.
     *
     * Kept apart from the rest of the dry run so the sentence can be tested against a
     * table in any character set, instead of only against the site's own tables.
     */
    private function capability_warnings(array $database) {
        $warnings = [];
        $unsupported = is_array($database['tables_without_4_byte_support'] ?? null) ? $database['tables_without_4_byte_support'] : [];
        if ([] !== $unsupported) {
            $places = [];
            foreach ($unsupported as $table) {
                $columns = (array) ($database['tables'][$table]['columns_without_4_byte_support'] ?? []);
                if ([] !== $columns) {
                    foreach (array_slice($columns, 0, 8, true) as $column => $charset) {
                        $places[] = sprintf('%1$s.%2$s (%3$s)', $table, $column, (string) $charset);
                    }
                    if (count($columns) > 8) {
                        $places[] = sprintf('%1$s and %2$d more columns', $table, count($columns) - 8);
                    }
                    continue;
                }
                $places[] = sprintf('%1$s (%2$s)', $table, (string) ($database['tables'][$table]['charset'] ?? '?'));
            }
            $warnings[] = sprintf(
                /* translators: 1: list of tables and columns with their character sets. */
                __('Some of this site\'s tables store text in a character set that cannot hold every character an ad can contain — emoji and some written languages are left out. Affected: %1$s. Ask your host to convert those columns to utf8mb4 before importing, or the database will drop those characters and the migration will report each one.', 'million-dollar-script'),
                implode(', ', $places)
            );
        }

        if (false === ($database['connection_stores_4_byte_characters'] ?? true)) {
            $warnings[] = sprintf(
                /* translators: %s: the character set this connection uses. */
                __('This database connection uses %s, so characters outside it are removed before they reach the tables. Ask the host to connect with utf8mb4.', 'million-dollar-script'),
                (string) ($database['connection']['character_set_connection'] ?? '?')
            );
        }

        return $warnings;
    }
}
