<?php
/**
 * REST API policy and key authorization.
 *
 * @package MillionDollarScript\V3\Rest
 */

namespace MillionDollarScript\V3\Rest;

use MillionDollarScript\V3\Rest\Concerns\AuthorizesApiRequests;
use MillionDollarScript\V3\Rest\Concerns\BuildsEndpointManifest;
use MillionDollarScript\V3\Rest\Concerns\BuildsOpenApiDocument;
use MillionDollarScript\V3\Rest\Concerns\ManagesApiPolicies;

if (!defined('ABSPATH')) {
    exit;
}

final class ApiGovernance {
    use AuthorizesApiRequests;
    use BuildsEndpointManifest;
    use BuildsOpenApiDocument;
    use ManagesApiPolicies;

    public const POLICIES_OPTION = 'mds3_api_endpoint_policies';

    private const LEVELS = [
        'public_read' => 10,
        'public_write_nonce' => 20,
        'api_key_read' => 30,
        'api_key_write' => 40,
        'signed_manage_token' => 50,
        'service_signature' => 60,
        'wp_capability' => 70,
        'disabled' => 100,
    ];
}
