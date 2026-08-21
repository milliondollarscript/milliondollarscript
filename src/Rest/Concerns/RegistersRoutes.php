<?php
/**
 * REST route registration.
 *
 * @package MillionDollarScript\V3\Rest
 */

namespace MillionDollarScript\V3\Rest\Concerns;

if (!defined('ABSPATH')) {
    exit;
}

trait RegistersRoutes {

    public function routes() {
        foreach ($this->rest_namespaces() as $namespace) {
            $this->namespace = $namespace;
            $this->register_routes_for_namespace();
        }

        $this->namespace = \MillionDollarScript\V3\Rest\Api::REST_NAMESPACE;
    }

    private function register_routes_for_namespace(): void {
        if ($this->grid_enabled()) {
            register_rest_route($this->namespace, '/grids', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'grids'],
                    'permission_callback' => [$this, 'can_public_read'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'create_grid'],
                    'permission_callback' => [$this, 'can_manage'],
                ],
            ]);

            register_rest_route($this->namespace, '/grids/(?P<id>\d+)', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'grid'],
                    'permission_callback' => [$this, 'can_public_read'],
                ],
                [
                    'methods' => ['PUT', 'PATCH'],
                    'callback' => [$this, 'update_grid'],
                    'permission_callback' => [$this, 'can_manage'],
                ],
                [
                    'methods' => 'DELETE',
                    'callback' => [$this, 'archive_grid'],
                    'permission_callback' => [$this, 'can_manage'],
                ],
            ]);

            register_rest_route($this->namespace, '/grids/(?P<id>\d+)/blocks', [
                'methods' => 'GET',
                'callback' => [$this, 'blocks'],
                'permission_callback' => [$this, 'can_public_read'],
            ]);

            register_rest_route($this->namespace, '/grids/(?P<id>\d+)/availability', [
                'methods' => 'POST',
                'callback' => [$this, 'update_availability'],
                'permission_callback' => [$this, 'can_manage'],
            ]);

            register_rest_route($this->namespace, '/grids/(?P<id>\d+)/packages', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'packages'],
                    'permission_callback' => [$this, 'can_manage'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'save_package'],
                    'permission_callback' => [$this, 'can_manage'],
                ],
            ]);

            register_rest_route($this->namespace, '/grids/(?P<id>\d+)/price-rules', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'price_rules'],
                    'permission_callback' => [$this, 'can_manage'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'save_price_rule'],
                    'permission_callback' => [$this, 'can_manage'],
                ],
            ]);

            register_rest_route($this->namespace, '/grids/(?P<id>\d+)/placements', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'placements'],
                    'permission_callback' => [$this, 'can_public_read'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'create_placement'],
                    'permission_callback' => [$this, 'can_manage'],
                ],
            ]);

            register_rest_route($this->namespace, '/grids/(?P<id>\d+)/reservations', [
                'methods' => 'POST',
                'callback' => [$this, 'create_reservation'],
                'permission_callback' => [$this, 'can_manage'],
            ]);

            register_rest_route($this->namespace, '/orders', [
                'methods' => 'GET',
                'callback' => [$this, 'orders'],
                'permission_callback' => [$this, 'can_manage'],
            ]);

            register_rest_route($this->namespace, '/orders/(?P<id>\d+)', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'order'],
                    'permission_callback' => [$this, 'can_manage'],
                ],
                [
                    'methods' => ['PUT', 'PATCH'],
                    'callback' => [$this, 'update_order_state'],
                    'permission_callback' => [$this, 'can_manage'],
                ],
            ]);

            register_rest_route($this->namespace, '/orders/(?P<id>\d+)/items', [
                'methods' => 'GET',
                'callback' => [$this, 'order_items'],
                'permission_callback' => [$this, 'can_manage'],
            ]);

            register_rest_route($this->namespace, '/grids/(?P<id>\d+)/render-status', [
                'methods' => 'GET',
                'callback' => [$this, 'render_status'],
                'permission_callback' => [$this, 'can_manage'],
            ]);

            register_rest_route($this->namespace, '/grids/(?P<id>\d+)/render-preflight', [
                'methods' => ['GET', 'POST'],
                'callback' => [$this, 'render_preflight'],
                'permission_callback' => [$this, 'can_manage'],
            ]);

            register_rest_route($this->namespace, '/grids/(?P<id>\d+)/render', [
                'methods' => 'POST',
                'callback' => [$this, 'submit_render'],
                'permission_callback' => [$this, 'can_manage'],
            ]);

            register_rest_route($this->namespace, '/render-jobs/(?P<id>\d+)', [
                'methods' => 'GET',
                'callback' => [$this, 'render_job'],
                'permission_callback' => [$this, 'can_manage'],
            ]);

            register_rest_route($this->namespace, '/migration/dry-run', [
                'methods' => 'POST',
                'callback' => [$this, 'migration_dry_run'],
                'permission_callback' => [$this, 'can_manage'],
            ]);

            register_rest_route($this->namespace, '/migration/execute', [
                'methods' => 'POST',
                'callback' => [$this, 'migration_execute'],
                'permission_callback' => [$this, 'can_manage'],
            ]);
        }

        register_rest_route($this->namespace, '/extensions', [
            'methods' => 'GET',
            'callback' => [$this, 'extensions'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        register_rest_route($this->namespace, '/extensions/capabilities', [
            'methods' => 'GET',
            'callback' => [$this, 'extension_capabilities'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        register_rest_route($this->namespace, '/extensions/setup', [
            'methods' => 'GET',
            'callback' => [$this, 'extension_setup'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        register_rest_route($this->namespace, '/extensions/discovery', [
            'methods' => 'GET',
            'callback' => [$this, 'api_discovery'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        register_rest_route($this->namespace, '/extensions/openapi', [
            'methods' => 'GET',
            'callback' => [$this, 'api_openapi'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        if ($this->grid_enabled() && $this->imagegrid_extension_active()) {
            register_rest_route($this->namespace, '/imagegrid/account', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'imagegrid_settings'],
                    'permission_callback' => [$this, 'can_manage'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'imagegrid_test'],
                    'permission_callback' => [$this, 'can_manage'],
                ],
            ]);
        }

        register_rest_route($this->namespace, '/api/discovery', [
            'methods' => 'GET',
            'callback' => [$this, 'api_discovery'],
            'permission_callback' => [$this, 'can_manage_api'],
        ]);

        register_rest_route($this->namespace, '/api/keys', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'api_keys'],
                'permission_callback' => [$this, 'can_manage_api'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_api_key'],
                'permission_callback' => [$this, 'can_manage_api'],
            ],
        ]);

        register_rest_route($this->namespace, '/api/keys/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'revoke_api_key'],
            'permission_callback' => [$this, 'can_manage_api'],
        ]);

        register_rest_route($this->namespace, '/api/keys/(?P<id>\d+)/rotate', [
            'methods' => 'POST',
            'callback' => [$this, 'rotate_api_key'],
            'permission_callback' => [$this, 'can_manage_api'],
        ]);

        register_rest_route($this->namespace, '/api/audit-logs', [
            'methods' => 'GET',
            'callback' => [$this, 'api_audit_logs'],
            'permission_callback' => [$this, 'can_manage_api'],
        ]);
    }
}
