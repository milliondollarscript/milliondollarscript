<?php
/**
 * Anonymous migration skip and repair reporting.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration\Concerns;

if (!defined('ABSPATH')) {
    exit;
}

trait RecordsMigrationReconciliation {

    /**
     * Statement codes where the database kept less than we sent.
     *
     * Outside strict mode a value that does not fit a column is shortened and the
     * statement answers with a warning (1265); characters a column's character set
     * cannot hold are dropped the same way (1366). Under strict mode the same
     * content fails the statement instead (1406), which the write helpers already
     * report as a rejected row.
     *
     * A method rather than a trait constant: PHP 8.2 is the first version that allows
     * constants in traits, and this plugin runs on older ones.
     */
    private static function database_loss_codes() {
        return [
            1265 => 'shortened',
            1366 => 'dropped characters',
            1264 => 'out of range',
        ];
    }

    /**
     * Counts of values the database refused to store, by table and column.
     *
     * @var array<string, array{table:string,column:string,kind:string,count:int}>
     */
    private array $database_rejections = [];

    private function record_migration_skip($entity, $source_id, $reason) {
        $this->migration_skips[$this->reconciliation_key($entity, $source_id, $reason)] = [
            'entity' => sanitize_key((string) $entity),
            'source_id' => sanitize_text_field((string) $source_id),
            'reason' => sanitize_text_field((string) $reason),
        ];
    }

    /**
     * Inserts a migration row and reports a rejected write instead of dropping it.
     *
     * A rejected row (a value that does not fit a column, most often) used to
     * disappear silently: the run reported no warning and the customer found the
     * missing content later. Returns the new row id, or 0 when the write failed.
     */
    private function migration_insert(string $table, array $payload, string $entity, $source_id): int
    {
        global $wpdb;

        if (false === $wpdb->insert($table, $payload)) {
            $this->record_migration_write_failure($entity, $source_id, $table, 'insert');

            return 0;
        }

        $this->report_lost_database_write($entity, $source_id, $table, 'insert');

        return absint($wpdb->insert_id);
    }

    /**
     * Updates a migration row and reports a rejected write. A no-op update (the
     * row already holds the incoming values) is not a failure.
     */
    private function migration_update(string $table, array $payload, array $where, string $entity, $source_id): bool
    {
        global $wpdb;

        if (false === $wpdb->update($table, $payload, $where)) {
            $this->record_migration_write_failure($entity, $source_id, $table, 'update');

            return false;
        }

        $this->report_lost_database_write($entity, $source_id, $table, 'update');

        return true;
    }

    /**
     * Names the rejected write and the database's own reason, with the legacy id
     * so the customer can find the record in MDS2.
     */
    private function record_migration_write_failure(string $entity, $source_id, string $table, string $action): void
    {
        global $wpdb;

        $error = trim((string) ($wpdb->last_error ?? ''));

        // Prefer the database's own warning; fall back to the message WordPress returns,
        // which names the field instead of the column.
        $column = '';
        foreach ($this->database_statement_warnings() as $warning) {
            $column = $this->warning_column((string) ($warning['message'] ?? ''));
            if ('' !== $column) {
                break;
            }
        }
        if ('' === $column) {
            $column = $this->warning_column($error);
        }
        $this->tally_database_loss($table, $column, 'not stored');

        $this->record_migration_skip($entity, $source_id, sprintf(
            /* translators: 1: insert or update, 2: legacy record id, 3: table name, 4: database error. */
            __('The %1$s row for legacy %2$s could not be written to %3$s and was not imported: %4$s.', 'million-dollar-script'),
            $action,
            (string) $source_id,
            $table,
            '' !== $error ? $error : __('the database rejected the row.', 'million-dollar-script'),
        ));
    }

    /**
     * Ask the database what it did to the statement we just ran.
     *
     * SHOW WARNINGS describes the most recent statement on this connection, so this
     * has to be called immediately after the write, before any other query. Nothing
     * else reports a silent truncation: the statement succeeded, so there is no error
     * to read, and the row now holds less than the older site had.
     */
    private function database_statement_warnings(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results('SHOW WARNINGS', ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        // The server returns Level/Code/Message; keep the keys case-insensitive so the
        // reader does not depend on how this engine spells them.
        $warnings = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row = array_change_key_case($row, CASE_LOWER);
            $warnings[] = [
                'level' => (string) ($row['level'] ?? ''),
                'code' => absint($row['code'] ?? 0),
                'message' => (string) ($row['message'] ?? ''),
            ];
        }

        return $warnings;
    }

    /**
     * Record a write the database accepted but shortened.
     *
     * The row is real, so this is a repair rather than a skip: the customer keeps the
     * record and is told which value is now shorter, in the database's own words.
     */
    private function report_lost_database_write(string $entity, $source_id, string $table, string $action): void
    {
        foreach ($this->database_statement_warnings() as $warning) {
            $code = absint($warning['code'] ?? 0);
            if (!isset(self::database_loss_codes()[$code])) {
                continue;
            }

            $message = trim((string) ($warning['message'] ?? ''));
            $column = $this->warning_column($message);
            $this->record_migration_repair($entity, $source_id, sprintf(
                /* translators: 1: insert or update, 2: legacy record id, 3: table name, 4: column note, 5: database message. */
                __('The %1$s row for legacy %2$s was written to %3$s, but the database kept less than we sent%4$s: %5$s', 'million-dollar-script'),
                $action,
                (string) $source_id,
                $table,
                '' !== $column ? sprintf(' in %s', $column) : '',
                '' !== $message ? $message : __('the value did not fit the column.', 'million-dollar-script')
            ));
            $this->tally_database_loss($table, $column, self::database_loss_codes()[$code]);
        }
    }

    /**
     * The column named in a database message, e.g. "Data too long for column
     * 'alt_text' at row 1". WordPress' own message names the field instead:
     * "Processing the value for the following field failed: alt_text". An unnamed
     * column still counts; it is just less useful. On a translated site the message
     * may be in another language, in which case only the count survives.
     */
    private function warning_column(string $message): string
    {
        if (1 === preg_match("/for column '([^']+)'/", $message, $match)) {
            return sanitize_text_field($match[1]);
        }

        if (1 === preg_match('/following field failed:\s*([A-Za-z0-9_]+)/', $message, $match)) {
            return sanitize_text_field($match[1]);
        }

        return '';
    }

    private function tally_database_loss(string $table, string $column, string $kind): void
    {
        $key = $table . '.' . ('' !== $column ? $column : '?');
        $this->database_rejections[$key] = [
            'table' => $table,
            'column' => $column,
            'kind' => $kind,
            'count' => absint($this->database_rejections[$key]['count'] ?? 0) + 1,
        ];
    }

    /**
     * The run-level line that makes the count visible without reading every record.
     *
     * Without this the totals say "0 warnings" for an import that quietly lost text,
     * which is exactly what the customer reported on the live site.
     */
    private function database_rejection_warning(): string
    {

        if ([] === $this->database_rejections) {
            return '';
        }

        $total = 0;
        $places = [];
        foreach ($this->database_rejections as $entry) {
            $total += absint($entry['count'] ?? 0);
            $places[] = sprintf(
                '%1$s%2$s (%3$d)',
                (string) ($entry['table'] ?? '?'),
                '' !== (string) ($entry['column'] ?? '') ? '.' . $entry['column'] : '',
                absint($entry['count'] ?? 0)
            );
        }

        return sprintf(
            /* translators: 1: number of values, 2: list of table.column (count). */
            _n(
                'The database could not store %1$d value exactly as the older site had it (%2$s). The record is named under Reconciliation Details with the database\'s own reason. Convert the column or the table to a wider type or a full unicode character set, then run the migration again for that record.',
                'The database could not store %1$d values exactly as the older site had them (%2$s). The records are named under Reconciliation Details with the database\'s own reason. Convert the columns or tables to a wider type or a full unicode character set, then run the migration again for those records.',
                $total,
                'million-dollar-script'
            ),
            $total,
            implode(', ', $places)
        );
    }

    /**
     * Add the run-level line once, at the end of a pass.
     *
     * It is deliberately not added while a resumable run is still stepping: the
     * counts change between steps, and a list of half-finished sentences is worse
     * than one clear line when the pass is over. A partial run still names every
     * affected record in the repaired list.
     */
    private function record_database_rejection_warning(): void
    {
        $warning = $this->database_rejection_warning();
        if ('' !== $warning) {
            $this->warnings[] = $warning;
        }
    }

    /**
     * Record something the run did that the site owner should understand, with nothing to
     * fix. An import that started again from its first step, or a step of the older site
     * that does not apply here, is explained - it is not a record needing attention, and it
     * must not be counted with the records that do.
     */
    private function migration_notice(string $message): void {
        $this->notices[] = sanitize_text_field($message);
        $this->notices = array_values(array_unique($this->notices));
    }

    private function record_migration_repair($entity, $source_id, $reason) {
        $this->migration_repairs[$this->reconciliation_key($entity, $source_id, $reason)] = [
            'entity' => sanitize_key((string) $entity),
            'source_id' => sanitize_text_field((string) $source_id),
            'reason' => sanitize_text_field((string) $reason),
        ];
    }

    private function reconciliation_key($entity, $source_id, $reason) {
        return sha1(sanitize_key((string) $entity) . '|' . (string) $source_id . '|' . (string) $reason);
    }

    private function reconciliation_entries($entries) {
        if (!is_array($entries)) {
            return [];
        }

        $normalized = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $entity = sanitize_key((string) ($entry['entity'] ?? 'record'));
            $source_id = sanitize_text_field((string) ($entry['source_id'] ?? ''));
            $reason = sanitize_text_field((string) ($entry['reason'] ?? ''));
            if ('' === $reason) {
                continue;
            }

            $normalized[$this->reconciliation_key($entity, $source_id, $reason)] = [
                'entity' => $entity,
                'source_id' => $source_id,
                'reason' => $reason,
            ];
        }

        return $normalized;
    }
}
