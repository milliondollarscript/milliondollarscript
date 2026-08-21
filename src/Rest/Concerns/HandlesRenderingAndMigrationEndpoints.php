<?php
/**
 * Rendering and migration REST endpoints.
 *
 * @package MillionDollarScript\V3\Rest
 */

namespace MillionDollarScript\V3\Rest\Concerns;

use MillionDollarScript\V3\Migration\DryRun;
use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Rendering\ImageGridService;
use MillionDollarScript\V3\Rendering\RenderJobRepository;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesRenderingAndMigrationEndpoints {

    public function render_status(\WP_REST_Request $request) {
        return [
            'grid_id' => absint($request['id']),
            'latest' => (new RenderJobRepository())->latest_for_grid($request['id']),
        ];
    }

    public function render_preflight(\WP_REST_Request $request) {
        return (new ImageGridService())->preflight($request['id']);
    }

    public function submit_render(\WP_REST_Request $request) {
        return (new ImageGridService())->submit($request['id']);
    }

    public function render_job(\WP_REST_Request $request) {
        return (new ImageGridService())->poll($request['id']);
    }

    public function migration_dry_run(\WP_REST_Request $request) {
        $params = $request->get_json_params() ?: [];

        return (new DryRun())->report($this->source_prefix($params['source_prefix'] ?? ''));
    }

    public function migration_execute(\WP_REST_Request $request) {
        $params = $request->get_json_params() ?: [];

        return (new Importer())->import($this->source_prefix($params['source_prefix'] ?? ''));
    }

    private function source_prefix($prefix) {
        if (!is_scalar($prefix)) {
            return '';
        }

        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', (string) $prefix);

        return '' !== $prefix ? $prefix : '';
    }
}
