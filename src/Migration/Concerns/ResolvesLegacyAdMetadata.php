<?php
/**
 * MDS2 ad post metadata and custom field migration helpers.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration\Concerns;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

trait ResolvesLegacyAdMetadata {
    private array $legacy_mds_field_definitions = [];
    private bool $legacy_mds_field_definitions_loaded = false;

    private function legacy_ad_metadata($ad_id) {
        $ad_id = absint($ad_id);
        if (!$ad_id || !get_post($ad_id)) {
            return [];
        }

        $skip_keys = array_fill_keys($this->legacy_ad_core_meta_keys(), true);
        foreach (array_keys($this->legacy_mds_field_definitions()) as $field_key) {
            foreach ($this->legacy_mds_field_meta_keys($field_key) as $meta_key) {
                $skip_keys[$meta_key] = true;
            }
        }

        $metadata = [
            'mds_fields' => $this->legacy_mds_fields_for_ad($ad_id),
            'legacy_ad_post_meta' => $this->legacy_ad_post_meta($ad_id, $skip_keys),
        ];

        $metadata = array_filter($metadata, static function ($value) {
            return [] !== $value && null !== $value;
        });

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/migration/legacy/ad/metadata', $metadata, $ad_id);
    }

    private function legacy_ad_core_meta_keys() {
        return [
            '_edit_last',
            '_edit_lock',
            '_mds_image',
            'mds_image',
            '_thumbnail_id',
            '_milliondollarscript_grid',
            'milliondollarscript_grid',
            '_milliondollarscript_image',
            'milliondollarscript_image',
            '_milliondollarscript_order',
            'milliondollarscript_order',
            '_milliondollarscript_text',
            'milliondollarscript_text',
            '_milliondollarscript_url',
            'milliondollarscript_url',
        ];
    }

    private function legacy_mds_field_definitions() {
        global $wpdb;

        if ($this->legacy_mds_field_definitions_loaded) {
            return $this->legacy_mds_field_definitions;
        }

        $this->legacy_mds_field_definitions_loaded = true;
        $table = $wpdb->prefix . 'mds_custom_fields';
        if (!DB::table_exists($table)) {
            return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/migration/legacy/mds/field/definitions', [], $table);
        }

        $rows = $wpdb->get_results(
            'SELECT field_key, field_label, field_type FROM ' . DB::ident($table) . ' WHERE is_active = 1 ORDER BY field_order ASC, id ASC',
            ARRAY_A
        );

        foreach ($rows as $row) {
            $field_key = sanitize_key((string) ($row['field_key'] ?? ''));
            if ('' === $field_key) {
                continue;
            }

            $this->legacy_mds_field_definitions[$field_key] = [
                'field_key' => $field_key,
                'field_label' => sanitize_text_field((string) ($row['field_label'] ?? $field_key)),
                'field_type' => sanitize_key((string) ($row['field_type'] ?? 'text')) ?: 'text',
            ];
        }

        $this->legacy_mds_field_definitions = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/migration/legacy/mds/field/definitions', $this->legacy_mds_field_definitions, $table);

        return $this->legacy_mds_field_definitions;
    }

    private function legacy_mds_fields_for_ad($ad_id) {
        $fields = [];

        foreach ($this->legacy_mds_field_definitions() as $field_key => $field) {
            $value = null;
            $found = false;
            foreach ($this->legacy_mds_field_meta_keys($field_key) as $meta_key) {
                if (!metadata_exists('post', $ad_id, $meta_key)) {
                    continue;
                }

                $value = get_post_meta($ad_id, $meta_key, true);
                $found = true;
                break;
            }

            if (!$found || '' === $value || [] === $value) {
                continue;
            }

            $value = $this->sanitize_legacy_mds_field_value($value, $field);
            if ('' === $value || [] === $value) {
                continue;
            }

            $fields[$field_key] = [
                'label' => sanitize_text_field((string) ($field['field_label'] ?? $field_key)),
                'type' => sanitize_key((string) ($field['field_type'] ?? 'text')) ?: 'text',
                'value' => $value,
                'formatted_value' => $this->format_legacy_mds_field_value($value, $field),
            ];
        }

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/migration/legacy/mds/fields', $fields, $ad_id);
    }

    private function legacy_mds_field_meta_keys($field_key) {
        $field_key = sanitize_key((string) $field_key);
        if ('' === $field_key) {
            return [];
        }

        $prefix = defined('MDS_PREFIX') ? MDS_PREFIX : 'milliondollarscript_';

        return array_values(array_unique([
            '_' . $prefix . $field_key,
            $prefix . $field_key,
            '_' . $field_key,
            $field_key,
        ]));
    }

    private function legacy_ad_post_meta($ad_id, array $skip_keys) {
        $meta = get_post_meta($ad_id);
        if (!is_array($meta)) {
            return [];
        }

        $preserved = [];
        foreach ($meta as $meta_key => $values) {
            $meta_key = sanitize_text_field((string) $meta_key);
            if ('' === $meta_key || isset($skip_keys[$meta_key]) || $this->is_ignored_legacy_ad_meta_key($meta_key)) {
                continue;
            }

            $value = $this->legacy_ad_meta_value($values);
            if ('' === $value || [] === $value) {
                continue;
            }

            $preserved[$meta_key] = $this->normalize_legacy_value($value);
        }

        return $preserved;
    }

    private function is_ignored_legacy_ad_meta_key($meta_key) {
        foreach (['_wp_', '_oembed_', '_carbon_'] as $prefix) {
            if (str_starts_with($meta_key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function legacy_ad_meta_value($values) {
        if (!is_array($values)) {
            return maybe_unserialize($values);
        }

        $normalized = [];
        foreach ($values as $value) {
            $normalized[] = maybe_unserialize($value);
        }

        return 1 === count($normalized) ? $normalized[0] : $normalized;
    }

    private function sanitize_legacy_mds_field_value($value, array $field) {
        $type = sanitize_key((string) ($field['field_type'] ?? 'text'));

        if (is_array($value)) {
            $values = [];
            foreach ($value as $key => $item) {
                $values[$key] = $this->sanitize_legacy_mds_field_value($item, $field);
            }

            return $values;
        }

        switch ($type) {
            case 'email':
                return sanitize_email((string) $value);
            case 'url':
                return esc_url_raw((string) $value);
            case 'textarea':
                return sanitize_textarea_field((string) $value);
            case 'number':
                return (float) $value;
            case 'file':
            case 'image':
                return absint($value);
            case 'color':
                return sanitize_hex_color((string) $value) ?: '';
            default:
                return sanitize_text_field((string) $value);
        }
    }

    private function format_legacy_mds_field_value($value, array $field) {
        if (is_array($value)) {
            return implode(', ', array_map('sanitize_text_field', $value));
        }

        if (in_array(sanitize_key((string) ($field['field_type'] ?? '')), ['file', 'image'], true)) {
            $url = wp_get_attachment_url(absint($value));
            if ($url) {
                return esc_url_raw($url);
            }
        }

        return wp_strip_all_tags((string) $value);
    }
}
