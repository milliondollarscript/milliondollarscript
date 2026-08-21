<?php
/**
 * REST API.
 *
 * @package MillionDollarScript\V3\Rest
 */

namespace MillionDollarScript\V3\Rest;

use MillionDollarScript\V3\Extensions\ExtensionRuntime;
use MillionDollarScript\V3\Rest\Concerns\HandlesApiGovernanceEndpoints;
use MillionDollarScript\V3\Rest\Concerns\HandlesExtensionEndpoints;
use MillionDollarScript\V3\Rest\Concerns\HandlesGridEndpoints;
use MillionDollarScript\V3\Rest\Concerns\HandlesOrderEndpoints;
use MillionDollarScript\V3\Rest\Concerns\HandlesRenderingAndMigrationEndpoints;
use MillionDollarScript\V3\Rest\Concerns\RegistersRoutes;
use MillionDollarScript\V3\Support\Component;

if (!defined('ABSPATH')) {
    exit;
}

final class Api implements Component {
    use HandlesApiGovernanceEndpoints;
    use HandlesExtensionEndpoints;
    use HandlesGridEndpoints;
    use HandlesOrderEndpoints;
    use HandlesRenderingAndMigrationEndpoints;
    use RegistersRoutes;

    public const REST_NAMESPACE = 'million-dollar-script/v1';

    private string $namespace = self::REST_NAMESPACE;

    public function register() {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function rest_namespaces(): array {
        return [self::REST_NAMESPACE];
    }

    public function can_manage($request = null) {
        return (new ApiGovernance())->authorize($request, 'core.manage', 'api_key_write');
    }

    public function can_public_read($request = null) {
        return (new ApiGovernance())->authorize($request, 'core.read', 'public_read');
    }

    public function can_manage_api($request = null) {
        return current_user_can('manage_options');
    }

    private function grid_enabled() {
        return (new ExtensionRuntime())->is_enabled('mds-grid');
    }

    private function imagegrid_extension_active() {
        return defined('MDS_IMAGEGRID_VERSION')
            || function_exists('\\MillionDollarScript\\Extensions\\ImageGrid\\mds_imagegrid_app_url')
            || function_exists('\\MDS\\Extensions\\ImageGrid\\mds_imagegrid_app_url');
    }
}
