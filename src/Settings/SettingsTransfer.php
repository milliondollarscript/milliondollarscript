<?php
/**
 * Settings import/export helpers.
 *
 * @package MillionDollarScript\V3\Settings
 */

namespace MillionDollarScript\V3\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsTransfer {

    private const SCHEMA_VERSION = 1;
    private const PREVIEW_TTL = 1800;
    private const PREVIEW_PREFIX = 'mds3_settings_import_preview_';
    private const BACKUPS_OPTION = 'mds3_settings_import_backups';

    public function export_payload(array $settings, array $fields) {
        return [
            'package' => 'million-dollar-script',
            'type' => 'settings',
            'schema_version' => self::SCHEMA_VERSION,
            'plugin_version' => defined('MILLION_DOLLAR_SCRIPT_VERSION') ? MILLION_DOLLAR_SCRIPT_VERSION : '',
            'generated_at' => gmdate('c'),
            'settings' => $this->export_settings($settings, $fields),
        ];
    }

    public function decode_uploaded_file(array $file) {
        $error = absint($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if (UPLOAD_ERR_OK !== $error) {
            return new \WP_Error('mds3_settings_import_upload_failed', __('Choose a settings export JSON file.', 'million-dollar-script'));
        }

        $tmp_name = (string) ($file['tmp_name'] ?? '');
        $name = sanitize_file_name((string) ($file['name'] ?? ''));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!$tmp_name || !is_uploaded_file($tmp_name) || 'json' !== $extension) {
            return new \WP_Error('mds3_settings_import_invalid_file', __('Upload a valid Million Dollar Script settings JSON file.', 'million-dollar-script'));
        }

        $size = absint($file['size'] ?? 0);
        if ($size > 1048576) {
            return new \WP_Error('mds3_settings_import_too_large', __('Settings import files must be 1 MB or smaller.', 'million-dollar-script'));
        }

        $contents = file_get_contents($tmp_name); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if (false === $contents || '' === trim((string) $contents)) {
            return new \WP_Error('mds3_settings_import_empty', __('The settings import file is empty.', 'million-dollar-script'));
        }

        $payload = json_decode((string) $contents, true);
        if (!is_array($payload) || JSON_ERROR_NONE !== json_last_error()) {
            return new \WP_Error('mds3_settings_import_json_invalid', __('The settings import file is not valid JSON.', 'million-dollar-script'));
        }

        return $payload;
    }

    public function preview(array $payload, array $current, array $fields) {
        $settings = $this->payload_settings($payload);
        if (is_wp_error($settings)) {
            return $settings;
        }

        $schema_version = absint($payload['schema_version'] ?? self::SCHEMA_VERSION);
        if ($schema_version > self::SCHEMA_VERSION) {
            return new \WP_Error('mds3_settings_import_newer_schema', __('This settings file was created by a newer import format.', 'million-dollar-script'));
        }

        $next = [];
        $unknown = [];
        $rejected = [];
        foreach ($settings as $raw_key => $raw_value) {
            $key = sanitize_key((string) $raw_key);
            if (!$key || !isset($fields[$key])) {
                $unknown[] = (string) $raw_key;
                continue;
            }

            $normalized = $this->normalize_value($key, $raw_value, $fields[$key]);
            if (is_wp_error($normalized)) {
                $rejected[] = [
                    'key' => $key,
                    'label' => $this->field_label($key, $fields[$key]),
                    'message' => $normalized->get_error_message(),
                ];
                continue;
            }

            $next[$key] = $normalized;
        }

        if (!$next && !$unknown && !$rejected) {
            return new \WP_Error('mds3_settings_import_no_settings', __('The settings import file does not contain any settings.', 'million-dollar-script'));
        }

        return [
            'payload_meta' => [
                'plugin_version' => sanitize_text_field((string) ($payload['plugin_version'] ?? '')),
                'generated_at' => sanitize_text_field((string) ($payload['generated_at'] ?? '')),
            ],
            'settings' => $next,
            'changes' => $this->changes($next, $current, $fields),
            'unknown' => array_values(array_unique(array_map('sanitize_text_field', $unknown))),
            'rejected' => $rejected,
            'submitted_count' => count($settings),
            'importable_count' => count($next),
        ];
    }

    public function preview_with_effective_settings(array $preview, array $effective_settings, array $current, array $fields) {
        $settings = is_array($preview['settings'] ?? null) ? $preview['settings'] : [];
        foreach (array_keys($settings) as $key) {
            if (array_key_exists($key, $effective_settings)) {
                $settings[$key] = $effective_settings[$key];
            }
        }

        $preview['settings'] = $settings;
        $preview['changes'] = $this->changes($settings, $current, $fields);
        $preview['importable_count'] = count($settings);

        return $preview;
    }

    public function save_preview(array $preview, $user_id = 0) {
        $user_id = absint($user_id ?: get_current_user_id());
        if (!$user_id) {
            return false;
        }

        return set_transient($this->preview_key($user_id), $preview, self::PREVIEW_TTL);
    }

    public function preview_for_user($user_id = 0) {
        $user_id = absint($user_id ?: get_current_user_id());
        if (!$user_id) {
            return null;
        }

        $preview = get_transient($this->preview_key($user_id));

        return is_array($preview) ? $preview : null;
    }

    public function clear_preview($user_id = 0) {
        $user_id = absint($user_id ?: get_current_user_id());
        if ($user_id) {
            delete_transient($this->preview_key($user_id));
        }
    }

    public function backup_current(array $settings, $user_id = 0) {
        $backup = [
            'created_at' => gmdate('c'),
            'user_id' => absint($user_id ?: get_current_user_id()),
            'settings' => $settings,
        ];

        $backups = get_option(self::BACKUPS_OPTION, []);
        $backups = is_array($backups) ? array_values($backups) : [];
        array_unshift($backups, $backup);
        $backups = array_slice($backups, 0, 5);
        update_option(self::BACKUPS_OPTION, $backups, false);
        update_option('mds3_settings_last_import_backup', $backup, false);

        return $backup;
    }

    private function export_settings(array $settings, array $fields) {
        $export = [];
        foreach ($fields as $key => $field) {
            $key = sanitize_key((string) $key);
            if (!$key) {
                continue;
            }

            $export[$key] = $settings[$key] ?? ($field['default'] ?? '');
        }

        return $export;
    }

    private function changes(array $settings, array $current, array $fields) {
        $changes = [];
        foreach ($settings as $key => $value) {
            if (!isset($fields[$key])) {
                continue;
            }

            $current_value = $current[$key] ?? ($fields[$key]['default'] ?? '');
            if ($this->comparable_value($current_value) === $this->comparable_value($value)) {
                continue;
            }

            $changes[] = [
                'key' => $key,
                'label' => $this->field_label($key, $fields[$key]),
                'current' => $this->preview_value($current_value),
                'next' => $this->preview_value($value),
            ];
        }

        return $changes;
    }

    private function payload_settings(array $payload) {
        if (isset($payload['settings']) && is_array($payload['settings'])) {
            return $payload['settings'];
        }

        $looks_like_settings = false;
        foreach (array_keys($payload) as $key) {
            if (is_string($key) && false !== strpos($key, '-')) {
                $looks_like_settings = true;
                break;
            }
        }

        if ($looks_like_settings) {
            return $payload;
        }

        return new \WP_Error('mds3_settings_import_missing_settings', __('The settings import file does not contain a settings object.', 'million-dollar-script'));
    }

    private function normalize_value($key, $value, array $field) {
        if (is_array($value) || is_object($value)) {
            return new \WP_Error('mds3_settings_import_value_not_scalar', __('This setting has an unsupported value type.', 'million-dollar-script'));
        }

        $type = (string) ($field['type'] ?? 'text');
        if (in_array($type, ['number', 'image'], true) && null !== $value && '' !== (string) $value && !is_numeric($value)) {
            return new \WP_Error('mds3_settings_import_number_invalid', __('Expected a number.', 'million-dollar-script'));
        }

        if ('color' === $type && '' !== (string) $value && !preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value)) {
            return new \WP_Error('mds3_settings_import_color_invalid', __('Expected a six-digit hex color.', 'million-dollar-script'));
        }

        if ('select' === $type && !$this->select_value_allowed($key, (string) $value, $field)) {
            return new \WP_Error('mds3_settings_import_select_invalid', __('This value is not available on this site.', 'million-dollar-script'));
        }

        return SettingsSchema::sanitize($key, $value);
    }

    private function select_value_allowed($key, $value, array $field) {
        $sanitized = SettingsSchema::sanitize($key, $value);
        $values = [];
        foreach (($field['options'] ?? []) as $option_value => $option_label) {
            $values[] = is_int($option_value) ? (string) $option_label : (string) $option_value;
        }

        $values = array_map('strval', $values);
        if (!in_array((string) $sanitized, $values, true)) {
            return false;
        }

        if (in_array((string) $value, $values, true)) {
            return true;
        }

        $default = (string) ($field['default'] ?? '');
        $raw = strtolower(trim((string) $value));
        if ((string) $sanitized !== $default || strtolower($default) === $raw) {
            return true;
        }

        return $this->select_alias_allowed($key, $raw, (string) $sanitized);
    }

    private function select_alias_allowed($key, $raw, $sanitized) {
        if (in_array($sanitized, ['yes', '1'], true) && in_array($raw, ['yes', 'true', 'on', 'enabled', '1'], true)) {
            return true;
        }

        if (in_array($sanitized, ['no', '0'], true) && in_array($raw, ['no', 'false', 'off', 'disabled', '0'], true)) {
            return true;
        }

        $aliases = [
            'payment_provider' => [
                'standalone' => ['0', 'no', 'false', 'off', 'disabled', 'standalone', 'standalone checkout', 'manual'],
                'woocommerce' => ['1', 'yes', 'true', 'on', 'enabled', 'woocommerce', 'woocommerce checkout'],
            ],
            'updates' => [
                'main' => ['main', 'stable', 'release', 'production'],
                'alpha' => ['alpha', 'dev', 'development', 'nightly'],
            ],
            'block-selection-mode' => [
                'YES' => ['yes', 'true', 'on', 'enabled', 'advanced', 'blocks'],
                'NO' => ['no', 'false', 'off', 'disabled', 'simple', 'single'],
            ],
            'selection-adjacency-mode' => [
                'ADJACENT' => ['adjacent', 'contiguous', 'strict'],
                'RECTANGLE' => ['rectangle', 'rectangular', 'block', 'blocks', 'square'],
                'NONE' => ['none', 'unrestricted', 'no', 'off', 'false'],
            ],
            'stats-display-mode' => [
                'PIXELS' => ['pixels', 'pixel', 'full', 'none'],
                'BLOCKS' => ['blocks', 'block', 'basic'],
            ],
        ];

        return in_array($raw, $aliases[$key][$sanitized] ?? [], true);
    }

    private function field_label($key, array $field) {
        $label = trim((string) ($field['label'] ?? ''));

        return '' !== $label ? $label : $key;
    }

    private function preview_value($value) {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = wp_strip_all_tags((string) $value);
        $value = trim(preg_replace('/\s+/', ' ', $value));

        if ('' === $value) {
            return __('Empty', 'million-dollar-script');
        }

        if (function_exists('mb_substr') && function_exists('mb_strlen') && mb_strlen($value) > 120) {
            return mb_substr($value, 0, 117) . '...';
        }

        return strlen($value) > 120 ? substr($value, 0, 117) . '...' : $value;
    }

    private function comparable_value($value) {
        return html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private function preview_key($user_id) {
        return self::PREVIEW_PREFIX . absint($user_id);
    }
}
