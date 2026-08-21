<?php
/**
 * Extension REST endpoints and payload helpers.
 *
 * @package MillionDollarScript\V3\Rest
 */

namespace MillionDollarScript\V3\Rest\Concerns;

use MillionDollarScript\V3\Extensions\ExtensionCatalog;
use MillionDollarScript\V3\Extensions\ExtensionDependencyResolver;
use MillionDollarScript\V3\Extensions\ExtensionSetup;
use MillionDollarScript\V3\Rendering\ImageGridService;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesExtensionEndpoints {

    public function extensions() {
        return (new ExtensionCatalog())->catalog();
    }

    public function extension_capabilities() {
        $catalog = (new ExtensionCatalog())->catalog();
        $resolver = new ExtensionDependencyResolver();

        return [
            'core' => $resolver->core_capabilities(),
            'active' => $resolver->active_capabilities($catalog['installed'] ?? []),
            'installed' => array_map([$this, 'extension_discovery_payload'], $catalog['installed'] ?? []),
            'available' => array_map([$this, 'extension_discovery_payload'], $catalog['available'] ?? []),
        ];
    }

    public function extension_setup() {
        $setup = new ExtensionSetup();
        $choices = $setup->choices();
        $selected = $setup->selected_slugs($choices);
        $plan = $setup->selection_plan($selected, $choices);

        return [
            'selected' => $plan['selected'],
            'locked' => $plan['locked'],
            'auto_selected' => $plan['auto_selected'],
            'errors' => $plan['errors'],
            'skipped' => $plan['skipped'],
            'choices' => array_map([$this, 'extension_discovery_payload'], $choices),
        ];
    }

    public function imagegrid_settings() {
        return (new ImageGridService())->settings();
    }

    public function imagegrid_test() {
        return (new ImageGridService())->test_connection();
    }

    private function extension_discovery_payload(array $item) {
        return [
            'slug' => sanitize_key((string) ($item['slug'] ?? '')),
            'name' => sanitize_text_field((string) ($item['name'] ?? '')),
            'description' => sanitize_text_field((string) ($item['description'] ?? '')),
            'version' => sanitize_text_field((string) ($item['version'] ?? '')),
            'source' => sanitize_key((string) ($item['source'] ?? '')),
            'installed' => !empty($item['installed']) || 'installed' === ($item['source'] ?? ''),
            'active' => !empty($item['active']),
            'bundled' => !empty($item['bundled']),
            'locked' => !empty($item['locked']),
            'setup_default' => !empty($item['setup_default']),
            'setup_category' => sanitize_key((string) ($item['setup_category'] ?? 'extensions')),
            'license_required' => !empty($item['license_required']),
            'requires_service' => !empty($item['requires_service']),
            'provides' => array_values(array_map('sanitize_text_field', (array) ($item['provides'] ?? []))),
            'requires' => array_values(array_map('sanitize_text_field', (array) ($item['requires'] ?? []))),
            'recommends' => array_values(array_map('sanitize_text_field', (array) ($item['recommends'] ?? []))),
            'conflicts' => array_values(array_map('sanitize_text_field', (array) ($item['conflicts'] ?? []))),
            'minimum_security_level' => sanitize_key((string) ($item['minimum_security_level'] ?? ExtensionDependencyResolver::DEFAULT_SECURITY_LEVEL)),
            'api_manifest' => esc_url_raw((string) ($item['api_manifest'] ?? '')),
            'llm_safe_actions' => array_values(array_map('sanitize_text_field', (array) ($item['llm_safe_actions'] ?? []))),
        ];
    }
}
