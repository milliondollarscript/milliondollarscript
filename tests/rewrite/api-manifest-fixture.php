<?php
/**
 * Validate the active REST endpoint manifest against registered WordPress routes.
 *
 * Run with:
 * ./scripts/wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/api-manifest-fixture.php
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\MillionDollarScript\V3\\Rest\\ApiGovernance')) {
    fwrite(STDERR, "Million Dollar Script API governance is unavailable.\n");
    exit(1);
}

$failure = '';

try {
    $governance = new \MillionDollarScript\V3\Rest\ApiGovernance();
    $malformed_test = $governance->normalize_endpoint_manifest([
        [
            'id' => 'fixture-valid',
            'route' => '/million-dollar-script/v1/fixture-valid',
            'methods' => ['GET'],
            'scope' => 'fixture.read',
            'minimum_security_level' => 'api_key_read',
            'description' => 'Read fixture data.',
        ],
        [
            'extension' => 'legacy-package',
            'endpoints' => [['method' => 'GET', 'path' => '/legacy']],
        ],
        [
            'id' => 'fixture-invalid-level',
            'route' => '/million-dollar-script/v1/fixture-invalid-level',
            'methods' => ['GET'],
            'scope' => 'fixture.read',
            'minimum_security_level' => 'manage_options',
            'description' => 'Invalid security vocabulary.',
        ],
    ]);
    if (1 !== count($malformed_test) || 'fixture-valid' !== ($malformed_test[0]['id'] ?? '')) {
        throw new RuntimeException('Core accepted a nested or invalid endpoint manifest entry.');
    }

    $manifest = $governance->endpoint_manifest();
    $allowed_levels = $governance->security_levels();
    $allowed_methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
    $ids = [];
    $operations = [];
    foreach ($manifest as $endpoint) {
        $required = ['id', 'route', 'methods', 'scope', 'minimum_security_level', 'description'];
        if (!is_array($endpoint) || array_diff($required, array_keys($endpoint))) {
            throw new RuntimeException('An active extension contributed a non-flat endpoint manifest entry.');
        }
        if (count($required) !== count($endpoint)) {
            throw new RuntimeException('An endpoint manifest entry contains unsupported package-shaped metadata.');
        }

        $id = (string) $endpoint['id'];
        if (isset($ids[$id])) {
            throw new RuntimeException('Duplicate endpoint ID: ' . $id);
        }
        $ids[$id] = true;

        if (0 !== strpos((string) $endpoint['route'], '/million-dollar-script/v1/')) {
            throw new RuntimeException('Endpoint route is outside /million-dollar-script/v1: ' . $id);
        }
        if (!in_array((string) $endpoint['minimum_security_level'], $allowed_levels, true)) {
            throw new RuntimeException('Endpoint uses an unknown security level: ' . $id);
        }
        foreach ((array) $endpoint['methods'] as $method) {
            if (!in_array((string) $method, $allowed_methods, true)) {
                throw new RuntimeException('Endpoint uses an unsupported method: ' . $id);
            }
            $operation_key = (string) $endpoint['route'] . '|' . (string) $method;
            if (isset($operations[$operation_key])) {
                throw new RuntimeException('Duplicate route and method in endpoint manifest: ' . $operation_key);
            }
            $operations[$operation_key] = $id;
        }
    }

    $expected_security = [
        'bounty-engine-boards-read' => 'public_read',
        'bounty-engine-bounties-read' => 'public_read',
        'bounty-engine-bounties-create' => 'wp_capability',
        'local-money-map-boards-read' => 'public_read',
        'local-money-map-spots-read' => 'public_read',
        'local-money-map-spots-create' => 'wp_capability',
        'support-passport-badges-read' => 'public_read',
        'support-passport-profile-read' => 'signed_manage_token',
        'support-passport-profile-write' => 'signed_manage_token',
        'support-passport-badge-grant' => 'wp_capability',
        'support-passport-proof-read' => 'public_read',
        'support-passport-privacy-delete' => 'signed_manage_token',
        'revenue-agent-context-read' => 'wp_capability',
        'revenue-agent-drafts-read' => 'wp_capability',
        'revenue-agent-drafts-create' => 'wp_capability',
        'revenue-agent-drafts-approve' => 'wp_capability',
    ];
    $manifest_by_id = [];
    foreach ($manifest as $endpoint) {
        $manifest_by_id[$endpoint['id']] = $endpoint;
    }
    foreach ($expected_security as $id => $level) {
        $extension_class = '';
        if (str_starts_with($id, 'bounty-engine-')) {
            $extension_class = '\\MDS\\Extensions\\BountyEngine\\Main';
        } elseif (str_starts_with($id, 'local-money-map-')) {
            $extension_class = '\\MDS\\Extensions\\LocalMoneyMap\\Main';
        } elseif (str_starts_with($id, 'support-passport-')) {
            $extension_class = '\\MDS\\Extensions\\SupportPassport\\Main';
        } elseif (str_starts_with($id, 'revenue-agent-')) {
            $extension_class = '\\MDS\\Extensions\\RevenueAgent\\Main';
        }

        if ($extension_class && class_exists($extension_class)) {
            if (!isset($manifest_by_id[$id])) {
                throw new RuntimeException('Active extension endpoint is missing from discovery: ' . $id);
            }
            if ($level !== (string) $manifest_by_id[$id]['minimum_security_level']) {
                throw new RuntimeException('Active extension endpoint has the wrong security minimum: ' . $id);
            }
        }
    }

    $server = rest_get_server();
    $routes = $server->get_routes();
    foreach ($manifest as $endpoint) {
        $route = (string) $endpoint['route'];
        if (str_contains($route, '*')) {
            continue;
        }
        if (empty($routes[$route])) {
            throw new RuntimeException('Manifest route is not registered with WordPress: ' . $route);
        }

        $registered_methods = [];
        foreach ($routes[$route] as $handler) {
            foreach ((array) ($handler['methods'] ?? []) as $method => $enabled) {
                if ($enabled) {
                    $registered_methods[] = strtoupper((string) $method);
                }
            }
        }
        foreach ((array) $endpoint['methods'] as $method) {
            if (!in_array((string) $method, $registered_methods, true)) {
                throw new RuntimeException('Manifest method is not registered for ' . $route . ': ' . $method);
            }
        }
    }

    $openapi = $governance->openapi();
    $reservation_coordinate_ref = $openapi['components']['schemas']['ReservationRequest']['properties']['blocks']['items']['$ref'] ?? '';
    if ('#/components/schemas/BlockCoordinate' !== $reservation_coordinate_ref) {
        throw new RuntimeException('Reservation OpenAPI input does not use row and column block coordinates.');
    }
    foreach (['/api/keys/{id}/rotate', '/grids/{id}/render', '/grids/{id}/render-preflight'] as $bodyless_path) {
        if (isset($openapi['paths'][$bodyless_path]['post']['requestBody'])) {
            throw new RuntimeException('OpenAPI publishes an unused request body for ' . $bodyless_path);
        }
    }
    foreach (['/migration/dry-run', '/migration/execute'] as $migration_path) {
        if (empty($openapi['paths'][$migration_path]['post'])) {
            throw new RuntimeException('Migration endpoint is missing from OpenAPI: ' . $migration_path);
        }
    }
    foreach (array_keys((array) ($openapi['paths'] ?? [])) as $openapi_path) {
        if (str_contains((string) $openapi_path, '{path}')) {
            throw new RuntimeException('OpenAPI exposed a wildcard policy fallback as a callable route: ' . $openapi_path);
        }
    }
    $expected_openapi = [
        '/bounty-engine/boards' => 'get',
        '/local-money-map/boards/{id}/spots' => 'get',
        '/support-passport/profile' => 'post',
        '/support-passport/proofs/{token}' => 'get',
        '/revenue-agent/drafts/{id}/approve' => 'post',
    ];
    foreach ($expected_openapi as $path => $method) {
        $prefix = strtok(ltrim($path, '/'), '/');
        $class_by_prefix = [
            'bounty-engine' => '\\MDS\\Extensions\\BountyEngine\\Main',
            'local-money-map' => '\\MDS\\Extensions\\LocalMoneyMap\\Main',
            'support-passport' => '\\MDS\\Extensions\\SupportPassport\\Main',
            'revenue-agent' => '\\MDS\\Extensions\\RevenueAgent\\Main',
        ];
        if (class_exists($class_by_prefix[$prefix] ?? '') && empty($openapi['paths'][$path][$method])) {
            throw new RuntimeException('Active extension endpoint is missing from OpenAPI: ' . $path);
        }
    }

    $core_openapi = $governance->openapi(false);
    if (!empty($core_openapi['x-mds']['extension_manifests'])) {
        throw new RuntimeException('Static core OpenAPI contains environment-specific extension manifests.');
    }
    foreach (array_keys((array) ($core_openapi['paths'] ?? [])) as $path) {
        if (preg_match('#^/(?:bounty-engine|cooperative|imagegrid|launch-wall|local-money-map|missions|revenue-agent|sponsorboard|support-passport|time-capsule)(?:/|$)#', (string) $path)) {
            throw new RuntimeException('Static core OpenAPI contains an extension-owned route: ' . $path);
        }
    }

    if (class_exists('\\MDS\\Extensions\\BountyEngine\\Repository')) {
        $public = \MDS\Extensions\BountyEngine\Repository::public_board_payload([
            'id' => 1,
            'title' => 'Public board',
            'contact_email' => 'private@example.com',
            'retention_days' => 365,
            'payment_mode' => 'provider',
        ]);
        if (isset($public['contact_email']) || isset($public['retention_days']) || isset($public['payment_mode'])) {
            throw new RuntimeException('Bounty Engine public API payload exposes private board data.');
        }
    }

    if (class_exists('\\MDS\\Extensions\\LocalMoneyMap\\Repository')) {
        $public = \MDS\Extensions\LocalMoneyMap\Repository::public_spot_payload([
            'id' => 1,
            'title' => 'Public spot',
            'requester_email' => 'private@example.com',
            'payment_status' => 'paid',
            'amount' => 100,
        ]);
        if (isset($public['requester_email']) || isset($public['payment_status']) || isset($public['amount'])) {
            throw new RuntimeException('Local Money Map public API payload exposes private spot data.');
        }
    }
} catch (Throwable $error) {
    $failure = $error->getMessage();
}

if ($failure) {
    fwrite(STDERR, 'API manifest fixture failed: ' . $failure . "\n");
    exit(1);
}

echo "Success: API manifest fixture passed.\n";
