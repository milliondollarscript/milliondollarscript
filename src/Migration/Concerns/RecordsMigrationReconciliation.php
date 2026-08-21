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

    private function record_migration_skip($entity, $source_id, $reason) {
        $this->migration_skips[$this->reconciliation_key($entity, $source_id, $reason)] = [
            'entity' => sanitize_key((string) $entity),
            'source_id' => sanitize_text_field((string) $source_id),
            'reason' => sanitize_text_field((string) $reason),
        ];
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
