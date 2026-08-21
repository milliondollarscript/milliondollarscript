<?php
/**
 * API key, policy, route label, and audit-log panels.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Support\Template;

if (!defined('ABSPATH')) {
    exit;
}

trait RendersApiPanels {

    private function api_key_scope_options(array $endpoints) {
        $allowed_levels = ['public_read', 'public_write_nonce', 'api_key_read', 'api_key_write'];
        $labels = [
            'core.extension.read' => __('View extension information', 'million-dollar-script'),
            'core.grid.read' => __('View grids and inventory', 'million-dollar-script'),
            'core.grid.write' => __('Manage grids and inventory', 'million-dollar-script'),
            'core.order.read' => __('View orders', 'million-dollar-script'),
            'core.order.write' => __('Manage orders and reservations', 'million-dollar-script'),
            'core.placement.read' => __('View placements', 'million-dollar-script'),
            'core.placement.write' => __('Manage placements', 'million-dollar-script'),
            'core.render.read' => __('View rendering status', 'million-dollar-script'),
            'core.render.write' => __('Manage rendering jobs', 'million-dollar-script'),
        ];
        $options = [];

        foreach ($endpoints as $endpoint) {
            if (!is_array($endpoint) || !in_array(sanitize_key((string) ($endpoint['minimum_security_level'] ?? '')), $allowed_levels, true)) {
                continue;
            }

            $scope = strtolower(trim((string) ($endpoint['scope'] ?? '')));
            $scope = preg_replace('/[^a-z0-9*._:-]/', '', $scope);
            if (!$scope) {
                continue;
            }

            if (!isset($options[$scope])) {
                $options[$scope] = [
                    'description' => '',
                    'label' => $labels[$scope] ?? '',
                    'scope' => $scope,
                ];
            }

            if (!empty($endpoint['description'])) {
                $description = sanitize_text_field((string) $endpoint['description']);
                if ('' === $options[$scope]['label']) {
                    $options[$scope]['label'] = rtrim($description, '.');
                } elseif ('' === $options[$scope]['description']) {
                    $options[$scope]['description'] = $description;
                }
            }
        }

        foreach ($options as $scope => &$option) {
            if ('' === $option['label']) {
                $option['label'] = ucwords(str_replace(['.', '_', '-'], ' ', $scope));
            }
        }
        unset($option);

        ksort($options, SORT_NATURAL);

        /**
         * Filters selectable scopes shown when an administrator creates an API key.
         *
         * @param array $options   Scope options keyed by scope name.
         * @param array $endpoints Effective endpoint manifest.
         */
        $options = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/api/key/scope/options', $options, $endpoints);

        return is_array($options) ? array_values($options) : [];
    }

    private function api_keys_table(array $keys) {
        Template::display('admin/partials/api-keys-table.php', [
            'keys' => $keys,
        ], $this);
    }

    private function api_policies_table(array $endpoints, array $levels) {
        Template::display('admin/partials/api-policies-table.php', [
            'endpoints' => $endpoints,
            'levels' => $levels,
        ], $this);
    }

    private function api_security_level_label($level) {
        $labels = [
            'public_read' => __('Public read', 'million-dollar-script'),
            'public_write_nonce' => __('Browser write with REST nonce', 'million-dollar-script'),
            'api_key_read' => __('API key read', 'million-dollar-script'),
            'api_key_write' => __('API key write', 'million-dollar-script'),
            'signed_manage_token' => __('Signed manage token (endpoint managed)', 'million-dollar-script'),
            'wp_capability' => __('WordPress administrator', 'million-dollar-script'),
            'service_signature' => __('Trusted service signature or WordPress administrator', 'million-dollar-script'),
            'disabled' => __('Disabled', 'million-dollar-script'),
        ];

        return $labels[$level] ?? $level;
    }

    private function api_security_level_is_weaker($level, $minimum) {
        $rank = [
            'public_read' => 10,
            'public_write_nonce' => 20,
            'api_key_read' => 30,
            'api_key_write' => 40,
            'signed_manage_token' => 50,
            'service_signature' => 60,
            'wp_capability' => 70,
            'disabled' => 100,
        ];

        $level = sanitize_key((string) $level);
        $minimum = sanitize_key((string) $minimum);

        return ($rank[$level] ?? 40) < ($rank[$minimum] ?? 40);
    }

    private function api_route_label($route) {
        $route = '/' . ltrim((string) $route, '/');
        $route = preg_replace_callback('/\(\?P<([A-Za-z_][A-Za-z0-9_]*)>[^)]+\)/', static function ($matches) {
            return '{' . sanitize_key((string) $matches[1]) . '}';
        }, $route);
        $route = str_replace('/*', '/{path}', (string) $route);
        $route = preg_replace('#(?<!/)\*#', '/{path}', $route);

        return preg_replace('#/+#', '/', $route);
    }

    private function api_audit_table(array $logs) {
        Template::display('admin/partials/api-audit-table.php', [
            'logs' => $logs,
        ], $this);
    }
}
