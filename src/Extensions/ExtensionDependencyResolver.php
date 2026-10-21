<?php
/**
 * Extension dependency and capability checks.
 *
 * @package MillionDollarScript\V3\Extensions
 */

namespace MillionDollarScript\V3\Extensions;

if (!defined('ABSPATH')) {
    exit;
}

final class ExtensionDependencyResolver {

    public const DEFAULT_SECURITY_LEVEL = 'wp_capability';

    public function enrich_item(array $item, array $metadata = []) {
        $item['provides'] = $this->list_value($metadata['provides'] ?? $item['provides'] ?? []);
        $item['requires'] = $this->list_value($metadata['requires'] ?? $item['requires'] ?? []);
        $item['recommends'] = $this->list_value($metadata['recommends'] ?? $item['recommends'] ?? []);
        $item['conflicts'] = $this->list_value($metadata['conflicts'] ?? $item['conflicts'] ?? []);
        $item['llm_safe_actions'] = $this->list_value($metadata['llm_safe_actions'] ?? $item['llm_safe_actions'] ?? []);
        $item['setup_default'] = $this->truthy($metadata['setup_default'] ?? $item['setup_default'] ?? false);
        $item['requires_service'] = $this->truthy($metadata['requires_service'] ?? $item['requires_service'] ?? false);
        $item['license_required'] = $this->truthy($metadata['license_required'] ?? $metadata['is_premium'] ?? $item['license_required'] ?? false);
        $item['setup_category'] = sanitize_key((string) ($metadata['setup_category'] ?? $item['setup_category'] ?? 'extensions'));
        $item['minimum_security_level'] = $this->security_level($metadata['minimum_security_level'] ?? $item['minimum_security_level'] ?? self::DEFAULT_SECURITY_LEVEL);
        $item['api_manifest'] = esc_url_raw((string) ($metadata['api_manifest'] ?? $item['api_manifest'] ?? ''));
        $item['icon'] = sanitize_text_field((string) ($metadata['icon'] ?? $metadata['admin_icon'] ?? $item['icon'] ?? ''));
        $item['accent_color'] = $this->hex_color($metadata['accent_color'] ?? $metadata['color'] ?? $item['accent_color'] ?? $item['color'] ?? '');

        return $item;
    }

    public function installed_with_metadata(array $items) {
        foreach ($items as $index => $item) {
            $items[$index] = $this->enrich_item($item, $this->installed_headers((string) ($item['plugin_file'] ?? '')));
        }

        return $items;
    }

    public function available_with_metadata(array $items) {
        foreach ($items as $index => $item) {
            $items[$index] = $this->enrich_item($item, $item);
        }

        return $items;
    }

    public function activation_error(array $item, array $installed = null) {
        $installed = null === $installed ? (new ExtensionCatalog())->installed() : $installed;
        $missing = $this->missing_requirements($item, $installed);
        if ($missing) {
            $name = $this->extension_label($item);
            $needed = $this->requirement_labels($missing, $installed);
            $needed_list = implode(', ', $needed);

            if (1 === count($needed)) {
                return new \WP_Error('mds3_extension_missing_dependencies', sprintf(
                    /* translators: 1: the extension being switched on, 2: the extension it depends on. */
                    __('%1$s needs %2$s switched on first. Switch that one on, then try again.', 'million-dollar-script'),
                    $name,
                    $needed_list
                ));
            }

            return new \WP_Error('mds3_extension_missing_dependencies', sprintf(
                /* translators: 1: the extension being switched on, 2: list of the extensions it depends on. */
                __('%1$s needs these switched on first: %2$s. Switch them on, then try again.', 'million-dollar-script'),
                $name,
                $needed_list
            ));
        }

        $conflicts = $this->active_conflicts($item, $installed);
        if ($conflicts) {
            return new \WP_Error('mds3_extension_conflict', sprintf(
                /* translators: %s: conflicting extensions */
                __('Conflicting active extension: %s', 'million-dollar-script'),
                implode(', ', $conflicts)
            ));
        }

        return null;
    }

    public function deactivation_error(array $item, array $installed = null) {
        $installed = null === $installed ? (new ExtensionCatalog())->installed() : $installed;
        $provided = $this->provided_by($item);
        if (!$provided) {
            return null;
        }

        $blocked_by = [];
        $plugin_file = (string) ($item['plugin_file'] ?? '');
        foreach ($installed as $candidate) {
            if (empty($candidate['active']) || $plugin_file === (string) ($candidate['plugin_file'] ?? '')) {
                continue;
            }

            foreach ($this->list_value($candidate['requires'] ?? []) as $requirement) {
                if (!in_array($requirement, $provided, true)) {
                    continue;
                }
                if ($this->is_requirement_satisfied_without($requirement, $plugin_file, $installed)) {
                    continue;
                }
                $blocked_by[] = $this->extension_label($candidate);
            }
        }

        $blocked_by = array_values(array_unique(array_filter($blocked_by)));
        if (!$blocked_by) {
            return null;
        }

        return new \WP_Error('mds3_extension_required_by_active_extension', sprintf(
            /* translators: %s: active dependent extension names */
            __('This extension is required by active extension(s): %s', 'million-dollar-script'),
            implode(', ', $blocked_by)
        ));
    }

    public function missing_requirements(array $item, array $installed = null) {
        $installed = null === $installed ? (new ExtensionCatalog())->installed() : $installed;
        $missing = [];
        foreach ($this->list_value($item['requires'] ?? []) as $requirement) {
            if (!$this->is_requirement_satisfied($requirement, $installed)) {
                $missing[] = $requirement;
            }
        }

        return array_values(array_unique($missing));
    }

    public function active_conflicts(array $item, array $installed = null) {
        $installed = null === $installed ? (new ExtensionCatalog())->installed() : $installed;
        $item_conflicts = $this->list_value($item['conflicts'] ?? []);
        $item_provides = $this->provided_by($item);
        $conflicts = [];

        foreach ($installed as $candidate) {
            if (empty($candidate['active']) || (string) ($candidate['plugin_file'] ?? '') === (string) ($item['plugin_file'] ?? '')) {
                continue;
            }

            $candidate_provides = $this->provided_by($candidate);
            $candidate_conflicts = $this->list_value($candidate['conflicts'] ?? []);
            if (array_intersect($item_conflicts, $candidate_provides) || array_intersect($candidate_conflicts, $item_provides)) {
                $conflicts[] = (string) ($candidate['name'] ?? $candidate['slug'] ?? '');
            }
        }

        return array_values(array_unique(array_filter($conflicts)));
    }

    public function active_capabilities(array $installed = null) {
        $installed = null === $installed ? (new ExtensionCatalog())->installed() : $installed;
        $capabilities = $this->core_capabilities();

        foreach ($installed as $item) {
            if (empty($item['active'])) {
                continue;
            }

            $capabilities = array_merge($capabilities, $this->provided_by($item));
        }

        return array_values(array_unique(array_filter($capabilities)));
    }

    /**
     * Name a dependency the way the owner sees it, so the message says which extension to switch on.
     *
     * A requirement is written as a capability, which is why the previous message read
     * "Missing required extension capability: mds-seo-basic" on a live site — the owner could not
     * tell which card that named. The installed catalogue carries the display title.
     *
     * @param array<int,string> $requirements
     * @param array<int,array<string,mixed>> $installed
     * @return array<int,string>
     */
    private function requirement_labels(array $requirements, array $installed) {
        $labels = [];

        foreach ($requirements as $requirement) {
            $capability = $this->capability((string) $requirement);
            $label = '';

            foreach ($installed as $candidate) {
                if (in_array($capability, $this->provided_by((array) $candidate), true)) {
                    $label = $this->extension_label((array) $candidate);
                    if ('' !== $label) {
                        break;
                    }
                }
            }

            $labels[] = '' !== $label ? $label : (string) $capability;
        }

        return array_values(array_unique(array_filter($labels, static function ($label) {
            return '' !== trim((string) $label);
        })));
    }

    public function provided_by(array $item) {
        $provided = $this->list_value($item['provides'] ?? []);
        $slug = $this->capability((string) ($item['slug'] ?? ''));
        if ($slug) {
            $provided[] = $slug;
        }

        return array_values(array_unique(array_filter($provided)));
    }

    private function is_requirement_satisfied($requirement, array $installed) {
        $requirement = $this->capability($requirement);
        if (!$requirement) {
            return true;
        }

        if (in_array($requirement, $this->core_capabilities(), true)) {
            return true;
        }

        foreach ($installed as $item) {
            if (!empty($item['active']) && in_array($requirement, $this->provided_by($item), true)) {
                return true;
            }
        }

        return false;
    }

    private function is_requirement_satisfied_without($requirement, $excluded_plugin_file, array $installed) {
        $requirement = $this->capability($requirement);
        if (!$requirement) {
            return true;
        }

        if (in_array($requirement, $this->core_capabilities(), true)) {
            return true;
        }

        foreach ($installed as $item) {
            if ((string) ($item['plugin_file'] ?? '') === (string) $excluded_plugin_file || empty($item['active'])) {
                continue;
            }
            if (in_array($requirement, $this->provided_by($item), true)) {
                return true;
            }
        }

        return false;
    }

    private function installed_headers($plugin_file) {
        if (!$plugin_file || !defined('WP_PLUGIN_DIR') || !function_exists('get_file_data')) {
            return [];
        }

        $path = trailingslashit(WP_PLUGIN_DIR) . plugin_basename($plugin_file);
        if (!is_readable($path)) {
            return [];
        }

        $headers = get_file_data($path, [
            'provides' => 'MDS Provides',
            'requires' => 'MDS Requires',
            'recommends' => 'MDS Recommends',
            'conflicts' => 'MDS Conflicts',
            'setup_default' => 'MDS Setup Default',
            'setup_category' => 'MDS Setup Category',
            'license_required' => 'MDS License Required',
            'is_premium' => 'MDS Premium',
            'requires_service' => 'MDS Requires Service',
            'api_manifest' => 'MDS API Manifest',
            'minimum_security_level' => 'MDS Minimum Security Level',
            'llm_safe_actions' => 'MDS LLM Safe Actions',
            'icon' => 'MDS Icon',
            'accent_color' => 'MDS Accent Color',
        ], 'plugin');

        return array_filter($headers, static function ($value) {
            return '' !== trim((string) $value);
        });
    }

    private function list_value($value) {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value);
        }
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $entry) {
            if (is_array($entry) && isset($entry['id'])) {
                $entry = $entry['id'];
            }
            if (!is_scalar($entry)) {
                continue;
            }
            $capability = $this->capability((string) $entry);
            if ($capability) {
                $items[] = $capability;
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * The extension name as the Extensions screen shows it. A bare slug in a notice
     * reads like a fault code, and the catalogue keeps the real name under "name".
     */
    private function extension_label(array $item) {
        foreach (array('name', 'title') as $key) {
            $label = trim((string) ($item[$key] ?? ''));
            if ('' !== $label) {
                // Same form the Extensions screen uses: the brand prefix is not part of the name
                // a site owner reads back.
                $short = trim(preg_replace('/^Million Dollar Script(?:\s*-\s*|\s+)/i', '', $label));
                return '' !== $short ? $short : $label;
            }
        }

        return (string) ($item['slug'] ?? '');
    }

    private function capability($value) {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9._:-]/', '', $value);

        return (string) $value;
    }

    private function truthy($value) {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'on'], true);
    }

    private function security_level($value) {
        $value = sanitize_key((string) $value);
        $allowed = [
            'public_read',
            'public_write_nonce',
            'signed_manage_token',
            'api_key_read',
            'api_key_write',
            'wp_capability',
            'service_signature',
            'disabled',
        ];

        return in_array($value, $allowed, true) ? $value : self::DEFAULT_SECURITY_LEVEL;
    }

    private function hex_color($value) {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }

        if (function_exists('sanitize_hex_color')) {
            return sanitize_hex_color($value) ?: '';
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : '';
    }

    public function core_capabilities() {
        $capabilities = [
            'platform.core',
            'api.governance',
            'million-dollar-script',
            'mds-core',
        ];

        if ((new ExtensionRuntime())->is_enabled('mds-grid')) {
            $capabilities[] = 'inventory.grid';
        }

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/core/extension/capabilities', $capabilities);
    }
}
