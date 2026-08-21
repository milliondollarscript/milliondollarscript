<?php
/**
 * Core REST endpoint manifest.
 *
 * @package MillionDollarScript\V3\Rest
 */

namespace MillionDollarScript\V3\Rest\Concerns;

use MillionDollarScript\V3\Extensions\ExtensionRuntime;

if (!defined('ABSPATH')) {
    exit;
}

trait BuildsEndpointManifest {

    public function endpoint_manifest($include_extensions = true) {
        $endpoints = [];

        if ((new ExtensionRuntime())->is_enabled('mds-grid')) {
            $endpoints = array_merge($endpoints, [
                ['id' => 'core-grids-read', 'route' => '/million-dollar-script/v1/grids', 'methods' => ['GET'], 'scope' => 'core.grid.read', 'minimum_security_level' => 'public_read', 'description' => __('List public grids.', 'million-dollar-script')],
                ['id' => 'core-grids-write', 'route' => '/million-dollar-script/v1/grids', 'methods' => ['POST'], 'scope' => 'core.grid.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Create grids.', 'million-dollar-script')],
                ['id' => 'core-grid-read', 'route' => '/million-dollar-script/v1/grids/(?P<id>\\d+)', 'methods' => ['GET'], 'scope' => 'core.grid.read', 'minimum_security_level' => 'public_read', 'description' => __('Read a public grid.', 'million-dollar-script')],
                ['id' => 'core-grid-manage', 'route' => '/million-dollar-script/v1/grids/(?P<id>\\d+)', 'methods' => ['PUT', 'PATCH', 'DELETE'], 'scope' => 'core.grid.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Update or archive grids.', 'million-dollar-script')],
                ['id' => 'core-blocks-read', 'route' => '/million-dollar-script/v1/grids/(?P<id>\\d+)/blocks', 'methods' => ['GET'], 'scope' => 'core.grid.read', 'minimum_security_level' => 'public_read', 'description' => __('List grid blocks.', 'million-dollar-script')],
                ['id' => 'core-availability-manage', 'route' => '/million-dollar-script/v1/grids/(?P<id>\\d+)/availability', 'methods' => ['POST'], 'scope' => 'core.grid.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Update grid availability regions.', 'million-dollar-script')],
                ['id' => 'core-packages-read', 'route' => '/million-dollar-script/v1/grids/(?P<id>\\d+)/packages', 'methods' => ['GET'], 'scope' => 'core.grid.read', 'minimum_security_level' => 'api_key_read', 'description' => __('List grid packages.', 'million-dollar-script')],
                ['id' => 'core-packages-write', 'route' => '/million-dollar-script/v1/grids/(?P<id>\\d+)/packages', 'methods' => ['POST'], 'scope' => 'core.grid.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Create or update grid packages.', 'million-dollar-script')],
                ['id' => 'core-price-rules-read', 'route' => '/million-dollar-script/v1/grids/(?P<id>\\d+)/price-rules', 'methods' => ['GET'], 'scope' => 'core.grid.read', 'minimum_security_level' => 'api_key_read', 'description' => __('List grid price rules.', 'million-dollar-script')],
                ['id' => 'core-price-rules-write', 'route' => '/million-dollar-script/v1/grids/(?P<id>\\d+)/price-rules', 'methods' => ['POST'], 'scope' => 'core.grid.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Create or update grid price rules.', 'million-dollar-script')],
                ['id' => 'core-placements-read', 'route' => '/million-dollar-script/v1/grids/(?P<id>\\d+)/placements', 'methods' => ['GET'], 'scope' => 'core.placement.read', 'minimum_security_level' => 'public_read', 'description' => __('List public placements.', 'million-dollar-script')],
                ['id' => 'core-placements-write', 'route' => '/million-dollar-script/v1/grids/(?P<id>\\d+)/placements', 'methods' => ['POST'], 'scope' => 'core.placement.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Create placements.', 'million-dollar-script')],
                ['id' => 'core-reservations-write', 'route' => '/million-dollar-script/v1/grids/(?P<id>\\d+)/reservations', 'methods' => ['POST'], 'scope' => 'core.order.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Create block reservations.', 'million-dollar-script')],
                ['id' => 'core-orders-manage', 'route' => '/million-dollar-script/v1/orders', 'methods' => ['GET'], 'scope' => 'core.order.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read orders.', 'million-dollar-script')],
                ['id' => 'core-order-read', 'route' => '/million-dollar-script/v1/orders/(?P<id>\\d+)', 'methods' => ['GET'], 'scope' => 'core.order.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read one order.', 'million-dollar-script')],
                ['id' => 'core-orders-write', 'route' => '/million-dollar-script/v1/orders/(?P<id>\\d+)', 'methods' => ['PUT', 'PATCH'], 'scope' => 'core.order.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Update order state.', 'million-dollar-script')],
                ['id' => 'core-order-items-read', 'route' => '/million-dollar-script/v1/orders/(?P<id>\\d+)/items', 'methods' => ['GET'], 'scope' => 'core.order.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read order items.', 'million-dollar-script')],
                ['id' => 'core-render-status-read', 'route' => '/million-dollar-script/v1/grids/(?P<id>\\d+)/render-status', 'methods' => ['GET'], 'scope' => 'core.render.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read grid render status.', 'million-dollar-script')],
                ['id' => 'core-render-preflight-read', 'route' => '/million-dollar-script/v1/grids/(?P<id>\\d+)/render-preflight', 'methods' => ['GET'], 'scope' => 'core.render.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read grid render preflight data.', 'million-dollar-script')],
                ['id' => 'core-render-preflight-write', 'route' => '/million-dollar-script/v1/grids/(?P<id>\\d+)/render-preflight', 'methods' => ['POST'], 'scope' => 'core.render.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Run grid render preflight.', 'million-dollar-script')],
                ['id' => 'core-render-manage', 'route' => '/million-dollar-script/v1/grids/(?P<id>\\d+)/render', 'methods' => ['POST'], 'scope' => 'core.render.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Submit render jobs.', 'million-dollar-script')],
                ['id' => 'core-render-jobs-read', 'route' => '/million-dollar-script/v1/render-jobs/(?P<id>\\d+)', 'methods' => ['GET'], 'scope' => 'core.render.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read render job status.', 'million-dollar-script')],
                ['id' => 'core-migration-dry-run', 'route' => '/million-dollar-script/v1/migration/dry-run', 'methods' => ['POST'], 'scope' => 'core.migration.write', 'minimum_security_level' => 'wp_capability', 'description' => __('Run a Million Dollar Script 2 migration dry run.', 'million-dollar-script')],
                ['id' => 'core-migration-execute', 'route' => '/million-dollar-script/v1/migration/execute', 'methods' => ['POST'], 'scope' => 'core.migration.write', 'minimum_security_level' => 'wp_capability', 'description' => __('Import reviewed Million Dollar Script 2 data.', 'million-dollar-script')],
            ]);

            if ($include_extensions && $this->imagegrid_extension_active()) {
                $endpoints[] = ['id' => 'core-imagegrid-manage', 'route' => '/million-dollar-script/v1/imagegrid/account', 'methods' => ['GET', 'POST'], 'scope' => 'core.render.write', 'minimum_security_level' => 'wp_capability', 'description' => __('Manage ImageGrid account connection checks.', 'million-dollar-script')];
            }
        }

        $endpoints = array_merge($endpoints, [
            ['id' => 'core-extensions-catalog-read', 'route' => '/million-dollar-script/v1/extensions', 'methods' => ['GET'], 'scope' => 'core.extension.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read extension catalog.', 'million-dollar-script')],
            ['id' => 'core-extension-capabilities-read', 'route' => '/million-dollar-script/v1/extensions/capabilities', 'methods' => ['GET'], 'scope' => 'core.extension.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read extension capabilities.', 'million-dollar-script')],
            ['id' => 'core-extension-setup-read', 'route' => '/million-dollar-script/v1/extensions/setup', 'methods' => ['GET'], 'scope' => 'core.extension.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read setup extension selections.', 'million-dollar-script')],
            ['id' => 'core-discovery-read', 'route' => '/million-dollar-script/v1/extensions/discovery', 'methods' => ['GET'], 'scope' => 'core.extension.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read Million Dollar Script endpoint and extension discovery metadata.', 'million-dollar-script')],
            ['id' => 'core-openapi-read', 'route' => '/million-dollar-script/v1/extensions/openapi', 'methods' => ['GET'], 'scope' => 'core.extension.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read the OpenAPI contract for Million Dollar Script and active extensions.', 'million-dollar-script')],
            ['id' => 'core-extensions-read', 'route' => '/million-dollar-script/v1/extensions*', 'methods' => ['GET'], 'scope' => 'core.extension.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read extension catalog and capabilities.', 'million-dollar-script')],
            ['id' => 'core-api-discovery-manage', 'route' => '/million-dollar-script/v1/api/discovery', 'methods' => ['GET'], 'scope' => 'core.api.manage', 'minimum_security_level' => 'wp_capability', 'description' => __('Read administrator API discovery metadata.', 'million-dollar-script')],
            ['id' => 'core-api-keys-manage', 'route' => '/million-dollar-script/v1/api/keys', 'methods' => ['GET', 'POST'], 'scope' => 'core.api.manage', 'minimum_security_level' => 'wp_capability', 'description' => __('Manage API keys.', 'million-dollar-script')],
            ['id' => 'core-api-key-revoke', 'route' => '/million-dollar-script/v1/api/keys/(?P<id>\\d+)', 'methods' => ['DELETE'], 'scope' => 'core.api.manage', 'minimum_security_level' => 'wp_capability', 'description' => __('Revoke an API key.', 'million-dollar-script')],
            ['id' => 'core-api-key-rotate', 'route' => '/million-dollar-script/v1/api/keys/(?P<id>\\d+)/rotate', 'methods' => ['POST'], 'scope' => 'core.api.manage', 'minimum_security_level' => 'wp_capability', 'description' => __('Rotate an API key.', 'million-dollar-script')],
            ['id' => 'core-api-audit-logs-read', 'route' => '/million-dollar-script/v1/api/audit-logs', 'methods' => ['GET'], 'scope' => 'core.api.manage', 'minimum_security_level' => 'wp_capability', 'description' => __('Read API audit logs.', 'million-dollar-script')],
            ['id' => 'core-api-manage', 'route' => '/million-dollar-script/v1/api/*', 'methods' => ['GET', 'POST', 'DELETE'], 'scope' => 'core.api.manage', 'minimum_security_level' => 'wp_capability', 'description' => __('Manage API keys, policies, and audit logs.', 'million-dollar-script')],
        ]);

        if ($include_extensions) {
            $endpoints = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/api/endpoint/manifest', $endpoints);
        }

        return $this->normalize_endpoint_manifest($endpoints);
    }

    /**
     * Normalize first-party and third-party manifest contributions to the public contract.
     */
    public function normalize_endpoint_manifest($endpoints) {
        if (!is_array($endpoints)) {
            return [];
        }

        $allowed_methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
        $allowed_levels = method_exists($this, 'security_levels')
            ? $this->security_levels()
            : ['public_read', 'public_write_nonce', 'api_key_read', 'api_key_write', 'signed_manage_token', 'wp_capability', 'service_signature', 'disabled'];
        $normalized = [];
        $seen_ids = [];

        foreach ($endpoints as $endpoint) {
            if (!is_array($endpoint)) {
                continue;
            }

            $id = sanitize_key((string) ($endpoint['id'] ?? ''));
            $route = trim((string) ($endpoint['route'] ?? ''));
            $scope = strtolower(trim((string) ($endpoint['scope'] ?? '')));
            $description = sanitize_text_field((string) ($endpoint['description'] ?? ''));
            $minimum = sanitize_key((string) ($endpoint['minimum_security_level'] ?? ''));
            $methods = is_array($endpoint['methods'] ?? null) ? $endpoint['methods'] : [];
            $methods = array_values(array_unique(array_filter(array_map(static function ($method) use ($allowed_methods) {
                $method = strtoupper(sanitize_text_field((string) $method));

                return in_array($method, $allowed_methods, true) ? $method : '';
            }, $methods))));

            if (
                '' === $id
                || isset($seen_ids[$id])
                || '' === $route
                || 0 !== strpos($route, '/million-dollar-script/v1/')
                || preg_match('/[\x00-\x1F\x7F]/', $route)
                || strlen($route) > 512
                || empty($methods)
                || '' === $scope
                || !preg_match('/^[a-z0-9*._:-]+$/', $scope)
                || !in_array($minimum, $allowed_levels, true)
                || '' === $description
            ) {
                continue;
            }

            $seen_ids[$id] = true;
            $normalized[] = [
                'id' => $id,
                'route' => $route,
                'methods' => $methods,
                'scope' => $scope,
                'minimum_security_level' => $minimum,
                'description' => $description,
            ];
        }

        return $normalized;
    }

    private function imagegrid_extension_active() {
        return defined('MDS_IMAGEGRID_VERSION')
            || function_exists('\\MillionDollarScript\\Extensions\\ImageGrid\\mds_imagegrid_app_url')
            || function_exists('\\MDS\\Extensions\\ImageGrid\\mds_imagegrid_app_url');
    }
}
