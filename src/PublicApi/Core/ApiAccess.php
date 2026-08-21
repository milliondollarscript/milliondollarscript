<?php
/**
 * Stable REST authorization facade.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Core;

use MillionDollarScript\V3\Rest\ApiGovernance;
use MillionDollarScript\V3\Rest\ServiceSignatureRegistry;

if (!defined('ABSPATH')) {
    exit;
}

final class ApiAccess {

    /**
     * @return bool|\WP_Error
     */
    public static function authorize($request = null, string $scope = 'core.manage', string $minimum_security_level = 'api_key_write') {
        return (new ApiGovernance())->authorize($request, $scope, $minimum_security_level);
    }

    public static function can_manage(): bool {
        return current_user_can('manage_options');
    }

    /**
     * Register an exact service-signature verifier owned by an extension.
     *
     * The callback receives ServiceSignatureRequest first and WP_REST_Request
     * second. Only a literal true result authorizes the request.
     *
     * @return bool|\WP_Error
     */
    public static function register_service_signature_verifier(
        string $endpoint_id,
        string $scope,
        string $service_id,
        callable $verifier,
        array $versions = ['v1']
    ) {
        return ServiceSignatureRegistry::register($endpoint_id, $scope, $service_id, $verifier, $versions);
    }

    public static function service_identity(\WP_REST_Request $request): ?array {
        return ServiceSignatureRegistry::identity($request);
    }

    public static function service_signature_error(string $category = 'invalid', int $retry_after = 0): \WP_Error {
        return ServiceSignatureRegistry::error($category, $retry_after);
    }
}
