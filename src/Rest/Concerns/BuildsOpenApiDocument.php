<?php
/**
 * REST API discovery and OpenAPI document builders.
 *
 * @package MillionDollarScript\V3\Rest
 */

namespace MillionDollarScript\V3\Rest\Concerns;

use MillionDollarScript\V3\Extensions\ExtensionCatalog;
use MillionDollarScript\V3\Extensions\ExtensionDependencyResolver;
use MillionDollarScript\V3\Rest\Api;

if (!defined('ABSPATH')) {
    exit;
}

trait BuildsOpenApiDocument {

    public function discovery() {
        $catalog = (new ExtensionCatalog())->catalog();
        $resolver = new ExtensionDependencyResolver();

        return [
            'namespace' => Api::REST_NAMESPACE,
            'openapi_url' => $this->route_url('extensions/openapi'),
            'security_levels' => array_keys(self::LEVELS),
            'active_capabilities' => $resolver->active_capabilities($catalog['installed'] ?? []),
            'endpoints' => $this->effective_manifest(),
            'extension_manifests' => $this->extension_manifests($catalog),
        ];
    }

    public function openapi($include_extensions = true) {
        $catalog = $include_extensions ? (new ExtensionCatalog())->catalog() : [];
        $document = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => __('Million Dollar Script API', 'million-dollar-script'),
                'version' => defined('MILLION_DOLLAR_SCRIPT_VERSION') ? MILLION_DOLLAR_SCRIPT_VERSION : '3.0.0',
                'description' => $include_extensions
                    ? __('Discovery contract for Million Dollar Script core and extension APIs.', 'million-dollar-script')
                    : __('Static contract for the Million Dollar Script core API.', 'million-dollar-script'),
            ],
            'servers' => [
                ['url' => $this->server_url()],
            ],
            'paths' => [],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-Million-Dollar-Script-API-Key',
                    ],
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                    'WpCookieNonce' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-WP-Nonce',
                    ],
                    'ServiceSignatureV1' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-MDS-Signature',
                        'description' => __('HMAC-SHA256 request signature using the Million Dollar Script v1 service-signature contract.', 'million-dollar-script'),
                    ],
                ],
                'schemas' => $this->openapi_schemas(),
            ],
            'x-mds' => [
                'namespace' => Api::REST_NAMESPACE,
                'security_levels' => array_keys(self::LEVELS),
                'extension_manifests' => $this->extension_manifests($catalog),
                'service_signature' => [
                    'version' => 'v1',
                    'algorithm' => 'HMAC-SHA256',
                    'clock_skew_seconds' => 300,
                    'canonical_fields' => ['version', 'service_id', 'method', 'route', 'timestamp', 'nonce', 'body_sha256', 'idempotency_key'],
                    'canonical_route' => '/million-dollar-script/v1/...',
                    'required_headers' => [
                        'X-MDS-Service-Id',
                        'X-MDS-Signature-Version',
                        'X-MDS-Timestamp',
                        'X-MDS-Nonce',
                        'X-MDS-Content-SHA256',
                        'X-MDS-Signature',
                    ],
                ],
            ],
        ];

        foreach ($this->effective_manifest($include_extensions) as $endpoint) {
            $route = (string) ($endpoint['route'] ?? '');
            if (false !== strpos($route, '*')) {
                continue;
            }
            $path = $this->openapi_path($route);
            if (!$path) {
                continue;
            }

            foreach ((array) ($endpoint['methods'] ?? []) as $method) {
                $document['paths'][$path][strtolower((string) $method)] = $this->openapi_operation($endpoint, (string) $method, $route, $path);
            }
        }

        ksort($document['paths']);

        return $include_extensions
            ? \MillionDollarScript\Core\Hooks::apply('million-dollar-script/api/openapi/document', $document, $this)
            : $document;
    }

    private function extension_manifests(array $catalog) {
        $manifests = [];
        foreach (array_merge($catalog['installed'] ?? [], $catalog['available'] ?? []) as $item) {
            if (empty($item['api_manifest'])) {
                continue;
            }

            $manifests[] = [
                'slug' => sanitize_key((string) ($item['slug'] ?? '')),
                'name' => sanitize_text_field((string) ($item['name'] ?? '')),
                'active' => !empty($item['active']),
                'minimum_security_level' => sanitize_key((string) ($item['minimum_security_level'] ?? ExtensionDependencyResolver::DEFAULT_SECURITY_LEVEL)),
                'api_manifest' => esc_url_raw((string) $item['api_manifest']),
                'llm_safe_actions' => array_values(array_map('sanitize_text_field', (array) ($item['llm_safe_actions'] ?? []))),
            ];
        }

        return $manifests;
    }

    private function openapi_operation(array $endpoint, $method, $route, $path) {
        $method = strtoupper((string) $method);
        $security_level = $this->security_level((string) ($endpoint['security_level'] ?? $endpoint['minimum_security_level'] ?? 'api_key_write'));
        $operation = [
            'operationId' => $this->openapi_operation_id((string) ($endpoint['id'] ?? 'endpoint'), $method),
            'summary' => sanitize_text_field((string) ($endpoint['description'] ?? '')),
            'tags' => [$this->openapi_tag($path)],
            'parameters' => $this->openapi_path_parameters($route),
            'responses' => [
                '200' => [
                    'description' => __('Successful response.', 'million-dollar-script'),
                    'content' => [
                        'application/json' => [
                            'schema' => $this->openapi_response_schema((string) ($endpoint['id'] ?? ''), $method),
                        ],
                    ],
                ],
                '400' => $this->openapi_error_response(__('The request is invalid.', 'million-dollar-script')),
                '401' => $this->openapi_error_response(__('Authentication is required.', 'million-dollar-script')),
                '403' => $this->openapi_error_response(__('The authenticated actor is not allowed to use this endpoint.', 'million-dollar-script')),
                '404' => $this->openapi_error_response(__('The requested resource was not found.', 'million-dollar-script')),
                '429' => $this->openapi_error_response(__('The API key rate limit was exceeded.', 'million-dollar-script')),
            ],
            'security' => $this->openapi_security($security_level),
            'x-mds-endpoint-id' => sanitize_key((string) ($endpoint['id'] ?? '')),
            'x-mds-scope' => sanitize_text_field((string) ($endpoint['scope'] ?? '')),
            'x-mds-minimum-security-level' => $this->security_level((string) ($endpoint['minimum_security_level'] ?? 'api_key_write')),
            'x-mds-security-level' => $security_level,
        ];

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $request_schema = $this->openapi_request_schema((string) ($endpoint['id'] ?? ''), $method);
            if ($request_schema) {
                $operation['requestBody'] = [
                    'required' => false,
                    'content' => [
                        'application/json' => [
                            'schema' => $request_schema,
                        ],
                    ],
                ];
            }
        }

        if ('service_signature' === $security_level) {
            $operation['parameters'] = array_merge($operation['parameters'], $this->openapi_service_signature_parameters());
        }

        if ('core-orders-manage' === (string) ($endpoint['id'] ?? '') && 'GET' === $method) {
            $operation['parameters'][] = [
                'name' => 'per_page',
                'in' => 'query',
                'required' => false,
                'description' => __('Number of recent orders to return.', 'million-dollar-script'),
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
            ];
        }

        return $operation;
    }

    private function openapi_service_signature_parameters(): array {
        $headers = [
            ['X-MDS-Service-Id', __('Opaque service credential identifier.', 'million-dollar-script'), ['type' => 'string', 'minLength' => 8, 'maxLength' => 128]],
            ['X-MDS-Signature-Version', __('Service-signature protocol version.', 'million-dollar-script'), ['type' => 'string', 'enum' => ['v1']]],
            ['X-MDS-Timestamp', __('Unix timestamp in seconds within the allowed clock-skew window.', 'million-dollar-script'), ['type' => 'string', 'pattern' => '^[0-9]{10}$']],
            ['X-MDS-Nonce', __('Unpadded base64url encoding of 32 cryptographically random bytes.', 'million-dollar-script'), ['type' => 'string', 'pattern' => '^[A-Za-z0-9_-]{43}$']],
            ['X-MDS-Content-SHA256', __('Lowercase SHA-256 hash of the exact raw request body.', 'million-dollar-script'), ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$']],
            ['X-MDS-Signature', __('Lowercase hexadecimal HMAC-SHA256 signature.', 'million-dollar-script'), ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$']],
            ['X-Idempotency-Key', __('Optional request idempotency key; when present it is included in the v1 canonical string.', 'million-dollar-script'), ['type' => 'string', 'minLength' => 8, 'maxLength' => 128]],
        ];

        return array_map(static function ($header) {
            return [
                'name' => $header[0],
                'in' => 'header',
                'required' => 'X-Idempotency-Key' !== $header[0],
                'description' => $header[1],
                'schema' => $header[2],
            ];
        }, $headers);
    }

    private function openapi_schemas() {
        return [
            'Error' => [
                'type' => 'object',
                'required' => ['code', 'message', 'data'],
                'properties' => [
                    'code' => ['type' => 'string'],
                    'message' => ['type' => 'string'],
                    'data' => [
                        'type' => 'object',
                        'properties' => ['status' => ['type' => 'integer']],
                        'additionalProperties' => true,
                    ],
                ],
            ],
            'Grid' => [
                'type' => 'object',
                'required' => ['id', 'slug', 'title', 'width', 'height', 'block_width', 'block_height', 'status'],
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'slug' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'width' => ['type' => 'integer', 'minimum' => 1],
                    'height' => ['type' => 'integer', 'minimum' => 1],
                    'block_width' => ['type' => 'integer', 'minimum' => 1],
                    'block_height' => ['type' => 'integer', 'minimum' => 1],
                    'price_per_block' => ['type' => 'number', 'minimum' => 0],
                    'currency' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'renderer_mode' => ['type' => 'string'],
                    'virtual_blocks' => [
                        'type' => 'object',
                        'properties' => [
                            'rows' => ['type' => 'integer'],
                            'columns' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                        ],
                    ],
                ],
                'additionalProperties' => true,
            ],
            'GridWrite' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'example' => 'Main Grid'],
                    'slug' => ['type' => 'string', 'example' => 'main-grid'],
                    'description' => ['type' => 'string'],
                    'width' => ['type' => 'integer', 'minimum' => 1, 'example' => 1000],
                    'height' => ['type' => 'integer', 'minimum' => 1, 'example' => 1000],
                    'block_width' => ['type' => 'integer', 'minimum' => 1, 'example' => 10],
                    'block_height' => ['type' => 'integer', 'minimum' => 1, 'example' => 10],
                    'price_per_block' => ['type' => 'number', 'minimum' => 0, 'example' => 1],
                    'currency' => ['type' => 'string', 'example' => 'USD'],
                    'status' => ['type' => 'string', 'enum' => ['active', 'paused', 'archived']],
                    'settings' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'additionalProperties' => false,
            ],
            'Block' => [
                'type' => 'object',
                'required' => ['id', 'grid_id', 'x', 'y', 'width', 'height', 'status'],
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'grid_id' => ['type' => 'integer'],
                    'x' => ['type' => 'integer', 'minimum' => 0],
                    'y' => ['type' => 'integer', 'minimum' => 0],
                    'width' => ['type' => 'integer', 'minimum' => 1],
                    'height' => ['type' => 'integer', 'minimum' => 1],
                    'status' => ['type' => 'string'],
                ],
            ],
            'Placement' => [
                'type' => 'object',
                'required' => ['id', 'grid_id', 'x', 'y', 'width', 'height', 'status'],
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'grid_id' => ['type' => 'integer'],
                    'block_id' => ['type' => 'integer'],
                    'attachment_id' => ['type' => 'integer'],
                    'x' => ['type' => 'integer'],
                    'y' => ['type' => 'integer'],
                    'width' => ['type' => 'integer'],
                    'height' => ['type' => 'integer'],
                    'fit_mode' => ['type' => 'string', 'enum' => ['cover', 'contain']],
                    'link_url' => ['type' => 'string', 'format' => 'uri'],
                    'alt_text' => ['type' => 'string'],
                    'popup_text' => ['type' => 'string'],
                    'advertiser_page_url' => ['type' => 'string', 'format' => 'uri', 'description' => __('Present only while individual advertiser pages are enabled and this placement is public.', 'million-dollar-script')],
                    'status' => ['type' => 'string'],
                    'source' => ['type' => 'object', 'additionalProperties' => true],
                    'mask' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Rectangle']],
                ],
                'additionalProperties' => true,
            ],
            'Rectangle' => [
                'type' => 'object',
                'required' => ['x', 'y', 'width', 'height'],
                'properties' => [
                    'x' => ['type' => 'integer', 'minimum' => 0],
                    'y' => ['type' => 'integer', 'minimum' => 0],
                    'width' => ['type' => 'integer', 'minimum' => 1],
                    'height' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
            'BlockCoordinate' => [
                'type' => 'object',
                'required' => ['row', 'col'],
                'properties' => [
                    'row' => ['type' => 'integer', 'minimum' => 0, 'example' => 0],
                    'col' => ['type' => 'integer', 'minimum' => 0, 'example' => 0],
                ],
                'additionalProperties' => false,
            ],
            'AvailabilityUpdate' => [
                'type' => 'object',
                'required' => ['row_from', 'row_to', 'col_from', 'col_to', 'status'],
                'properties' => [
                    'row_from' => ['type' => 'integer', 'minimum' => 0, 'example' => 0],
                    'row_to' => ['type' => 'integer', 'minimum' => 0, 'example' => 4],
                    'col_from' => ['type' => 'integer', 'minimum' => 0, 'example' => 0],
                    'col_to' => ['type' => 'integer', 'minimum' => 0, 'example' => 4],
                    'status' => ['type' => 'string', 'enum' => ['available', 'unavailable']],
                    'note' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ],
            'PackageWrite' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'minimum' => 1],
                    'title' => ['type' => 'string', 'example' => '30-day placement'],
                    'description' => ['type' => 'string'],
                    'duration_days' => ['type' => 'integer', 'minimum' => 0, 'example' => 30],
                    'price' => ['type' => 'number', 'minimum' => 0, 'example' => 99],
                    'currency' => ['type' => 'string', 'example' => 'USD'],
                    'max_orders' => ['type' => 'integer', 'minimum' => 0],
                    'is_default' => ['type' => 'boolean'],
                    'status' => ['type' => 'string', 'enum' => ['active', 'paused', 'archived']],
                    'metadata' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'additionalProperties' => false,
            ],
            'PriceRuleWrite' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'minimum' => 1],
                    'row_from' => ['type' => ['integer', 'null'], 'minimum' => 0],
                    'row_to' => ['type' => ['integer', 'null'], 'minimum' => 0],
                    'col_from' => ['type' => ['integer', 'null'], 'minimum' => 0],
                    'col_to' => ['type' => ['integer', 'null'], 'minimum' => 0],
                    'block_id_from' => ['type' => ['integer', 'null'], 'minimum' => 0],
                    'block_id_to' => ['type' => ['integer', 'null'], 'minimum' => 0],
                    'price' => ['type' => 'number', 'minimum' => 0, 'example' => 2.5],
                    'currency' => ['type' => 'string', 'example' => 'USD'],
                    'color' => ['type' => 'string', 'example' => '#2563eb'],
                    'status' => ['type' => 'string', 'enum' => ['active', 'paused', 'archived']],
                    'metadata' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'additionalProperties' => false,
            ],
            'PlacementCreate' => [
                'type' => 'object',
                'description' => __('Creates a placement using the site-wide built-in placement field contract. Advertiser URL and popup text values are required, optional, or ignored when hidden according to the current Orders & Uploads settings.', 'million-dollar-script'),
                'required' => ['attachment_id'],
                'properties' => [
                    'attachment_id' => ['type' => 'integer', 'minimum' => 1],
                    'block_id' => ['type' => 'integer', 'minimum' => 1],
                    'order_id' => ['type' => 'integer', 'minimum' => 1],
                    'user_id' => ['type' => 'integer', 'minimum' => 1],
                    'x' => ['type' => 'integer', 'minimum' => 0],
                    'y' => ['type' => 'integer', 'minimum' => 0],
                    'width' => ['type' => 'integer', 'minimum' => 1],
                    'height' => ['type' => 'integer', 'minimum' => 1],
                    'fit_mode' => ['type' => 'string', 'enum' => ['cover', 'contain']],
                    'link_url' => ['type' => 'string', 'format' => 'uri', 'description' => __('Required or optional according to the Advertiser URL Field setting. Ignored when that field is hidden.', 'million-dollar-script')],
                    'alt_text' => ['type' => 'string'],
                    'popup_text' => ['type' => 'string', 'description' => __('Required or optional according to the Popup Text Field setting. Ignored when that field is hidden.', 'million-dollar-script')],
                    'status' => ['type' => 'string', 'enum' => ['pending', 'active', 'cancelled', 'archived']],
                    'sort_order' => ['type' => 'integer'],
                ],
                'additionalProperties' => false,
            ],
            'ReservationRequest' => [
                'type' => 'object',
                'required' => ['blocks'],
                'properties' => [
                    'blocks' => ['type' => 'array', 'minItems' => 1, 'items' => ['$ref' => '#/components/schemas/BlockCoordinate']],
                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'buyer@example.com'],
                    'user_id' => ['type' => 'integer', 'minimum' => 0],
                    'package_id' => ['type' => 'integer', 'minimum' => 0],
                    'subscription_plan_id' => ['type' => 'integer', 'minimum' => 0],
                    'metadata' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'additionalProperties' => false,
            ],
            'Order' => [
                'type' => 'object',
                'required' => ['id', 'status'],
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'user_id' => ['type' => 'integer'],
                    'status' => ['type' => 'string'],
                    'currency' => ['type' => 'string'],
                    'subtotal' => ['type' => 'number'],
                    'total' => ['type' => 'number'],
                    'metadata' => ['type' => 'object', 'additionalProperties' => true],
                    'items' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                    'placement_rect' => ['$ref' => '#/components/schemas/Rectangle'],
                ],
                'additionalProperties' => true,
            ],
            'OrderStatusUpdate' => [
                'type' => 'object',
                'required' => ['status'],
                'properties' => [
                    'status' => [
                        'type' => 'string',
                        'enum' => ['reserved', 'pending_payment', 'paid', 'cancelled', 'failed', 'refunded', 'expired', 'denied', 'deleted'],
                    ],
                ],
            ],
            'ApiKeyCreate' => [
                'type' => 'object',
                'required' => ['name', 'scopes'],
                'properties' => [
                    'name' => ['type' => 'string'],
                    'scopes' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'rate_limit_per_hour' => ['type' => 'integer', 'minimum' => 1, 'default' => 120],
                ],
            ],
            'MigrationRequest' => [
                'type' => 'object',
                'properties' => [
                    'source_prefix' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9_]*$', 'example' => 'wp_mds_'],
                ],
                'additionalProperties' => false,
            ],
        ];
    }

    private function openapi_request_schema($endpoint_id, $method) {
        $endpoint_id = sanitize_key((string) $endpoint_id);
        $method = strtoupper((string) $method);

        if (in_array($endpoint_id, ['core-api-key-rotate', 'core-render-manage', 'core-render-preflight-write'], true)) {
            return null;
        }

        if ('core-reservations-write' === $endpoint_id) {
            return ['$ref' => '#/components/schemas/ReservationRequest'];
        }
        if ('core-orders-write' === $endpoint_id) {
            return ['$ref' => '#/components/schemas/OrderStatusUpdate'];
        }
        if ('core-api-keys-manage' === $endpoint_id && 'POST' === $method) {
            return ['$ref' => '#/components/schemas/ApiKeyCreate'];
        }

        $schemas = [
            'core-grids-write' => 'GridWrite',
            'core-grid-manage' => 'GridWrite',
            'core-availability-manage' => 'AvailabilityUpdate',
            'core-packages-write' => 'PackageWrite',
            'core-price-rules-write' => 'PriceRuleWrite',
            'core-placements-write' => 'PlacementCreate',
            'core-migration-dry-run' => 'MigrationRequest',
            'core-migration-execute' => 'MigrationRequest',
        ];
        if (isset($schemas[$endpoint_id])) {
            return ['$ref' => '#/components/schemas/' . $schemas[$endpoint_id]];
        }

        return ['type' => 'object', 'additionalProperties' => true];
    }

    private function openapi_response_schema($endpoint_id, $method) {
        $endpoint_id = sanitize_key((string) $endpoint_id);
        $method = strtoupper((string) $method);
        $array_refs = [
            'core-grids-read' => 'Grid',
            'core-blocks-read' => 'Block',
            'core-placements-read' => 'Placement',
            'core-orders-manage' => 'Order',
        ];
        if (isset($array_refs[$endpoint_id]) && 'GET' === $method) {
            return ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/' . $array_refs[$endpoint_id]]];
        }

        $object_refs = [
            'core-grids-write' => 'Grid',
            'core-grid-read' => 'Grid',
            'core-grid-manage' => 'Grid',
            'core-order-read' => 'Order',
            'core-orders-write' => 'Order',
        ];
        if (isset($object_refs[$endpoint_id])) {
            return ['$ref' => '#/components/schemas/' . $object_refs[$endpoint_id]];
        }

        return ['type' => 'object', 'additionalProperties' => true];
    }

    private function openapi_error_response($description) {
        return [
            'description' => sanitize_text_field((string) $description),
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/Error'],
                ],
            ],
        ];
    }

    private function openapi_security($security_level) {
        $security_level = $this->security_level($security_level);

        if ('public_read' === $security_level) {
            return [];
        }

        if (in_array($security_level, ['api_key_read', 'api_key_write'], true)) {
            return [
                ['ApiKeyAuth' => []],
                ['BearerAuth' => []],
            ];
        }

        if ('service_signature' === $security_level) {
            return [
                ['WpCookieNonce' => []],
                ['ServiceSignatureV1' => []],
            ];
        }

        return [
            ['WpCookieNonce' => []],
        ];
    }

    private function openapi_path($route) {
        $path = '/' . ltrim((string) $route, '/');
        $path = preg_replace('#^/' . preg_quote(Api::REST_NAMESPACE, '#') . '#', '', $path);
        $path = preg_replace_callback('/\(\?P<([A-Za-z_][A-Za-z0-9_]*)>[^)]+\)/', static function ($matches) {
            return '{' . $matches[1] . '}';
        }, $path);
        $path = str_replace('/*', '/{path}', (string) $path);
        $path = str_replace('*', '/{path}', $path);
        $path = preg_replace('#/+#', '/', $path);

        return $path ? $path : '/';
    }

    private function openapi_path_parameters($route) {
        $parameters = [];
        preg_match_all('/\(\?P<([A-Za-z_][A-Za-z0-9_]*)>([^)]+)\)/', (string) $route, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $parameters[] = [
                'name' => sanitize_key((string) $match[1]),
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => false !== strpos((string) $match[2], '\\d') ? 'integer' : 'string',
                ],
            ];
        }

        if (false !== strpos((string) $route, '*')) {
            $parameters[] = [
                'name' => 'path',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
                'style' => 'simple',
                'explode' => false,
            ];
        }

        return $parameters;
    }

    private function openapi_operation_id($id, $method) {
        $parts = preg_split('/[^a-z0-9]+/', sanitize_key((string) $id . '-' . strtolower((string) $method)));
        $first = array_shift($parts);

        return (string) $first . implode('', array_map('ucfirst', $parts));
    }

    private function openapi_tag($path) {
        $parts = explode('/', trim((string) $path, '/'));
        $tag = sanitize_key((string) ($parts[0] ?? 'core'));

        return $tag ? ucwords(str_replace(['-', '_'], ' ', $tag)) : 'Core';
    }

    private function server_url() {
        return function_exists('rest_url') ? rtrim((string) rest_url(Api::REST_NAMESPACE), '/') : '/wp-json/' . Api::REST_NAMESPACE;
    }

    private function route_url($path) {
        $route = Api::REST_NAMESPACE . '/' . ltrim((string) $path, '/');

        return function_exists('rest_url') ? rest_url($route) : '/wp-json/' . $route;
    }
}
