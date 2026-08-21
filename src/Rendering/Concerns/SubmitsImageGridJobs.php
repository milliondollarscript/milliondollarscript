<?php
/**
 * ImageGrid render job submission and polling.
 *
 * @package MillionDollarScript\V3\Rendering
 */

namespace MillionDollarScript\V3\Rendering\Concerns;

use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Rendering\RenderJobRepository;

if (!defined('ABSPATH')) {
    exit;
}

trait SubmitsImageGridJobs {

    public function submit($grid_id) {
        $preflight = $this->preflight($grid_id);
        if (is_wp_error($preflight)) {
            return $preflight;
        }

        $grid = (new GridRepository())->find($grid_id);
        if (!$grid) {
            return new \WP_Error('mds3_grid_not_found', __('Grid not found.', 'million-dollar-script'), ['status' => 404]);
        }

        $manifest = $this->manifest($grid->to_array(), (new PlacementRepository())->for_grid($grid->id(), ['active', 'pending']));

        if (empty($preflight['quota']['ok'])) {
            $message = sprintf(
                /* translators: %s: quota key */
                __('ImageGrid submission is above the configured limit for %s. Reduce the workload or review ImageGrid limits before retrying.', 'million-dollar-script'),
                (string) ($preflight['quota']['failing_key'] ?? 'quota')
            );

            return 'imagegrid' === ($preflight['provider'] ?? 'local')
                ? $this->failed_remote_job($grid->id(), $preflight, $message)
                : $this->local_job($grid->id(), $manifest, $preflight, $message);
        }

        if ('local' === ($preflight['provider'] ?? 'local')) {
            return $this->local_job($grid->id(), $manifest, $preflight);
        }

        $filtered = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/imagegrid/submit/render', null, $manifest, $preflight);
        if (is_wp_error($filtered)) {
            return $this->failed_remote_job($grid->id(), $preflight, $filtered->get_error_message(), $filtered->get_error_code());
        }
        if (is_array($filtered)) {
            $job_id = (new RenderJobRepository())->create([
                'grid_id' => $grid->id(),
                'provider' => 'imagegrid',
                'remote_job_id' => sanitize_text_field($filtered['remote_job_id'] ?? $filtered['job_id'] ?? ''),
                'remote_tileset_id' => sanitize_text_field($filtered['remote_tileset_id'] ?? $filtered['tileset_id'] ?? ''),
                'status' => sanitize_key($filtered['status'] ?? 'pending'),
                'estimate' => $preflight['estimate'],
                'result' => $filtered,
            ]);

            return is_wp_error($job_id) ? $job_id : (new RenderJobRepository())->find($job_id);
        }

        $remote = $this->submit_remote($grid->id(), $manifest, $preflight);

        return is_wp_error($remote)
            ? $this->failed_remote_job($grid->id(), $preflight, $remote->get_error_message(), $remote->get_error_code())
            : $remote;
    }

    public function poll($job_id) {
        $repo = new RenderJobRepository();
        $job = $repo->find($job_id);
        if (!$job) {
            return new \WP_Error('mds3_render_job_not_found', __('Render job not found.', 'million-dollar-script'), ['status' => 404]);
        }

        if ('imagegrid' !== ($job['provider'] ?? '') || empty($job['remote_job_id'])) {
            return $job;
        }

        $filtered = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/imagegrid/poll/render', null, $job);
        if (is_wp_error($filtered)) {
            return $job;
        }
        if (is_array($filtered)) {
            $result = is_array($filtered['result'] ?? null) ? $filtered['result'] : $filtered;

            return $repo->update($job['id'], [
                'status' => sanitize_key($filtered['status'] ?? $job['status']),
                'remote_tileset_id' => sanitize_text_field($filtered['remote_tileset_id'] ?? $filtered['tileset_id'] ?? $job['remote_tileset_id']),
                'result' => $result,
                'error_message' => sanitize_text_field($filtered['error_message'] ?? $filtered['error'] ?? ''),
            ]);
        }

        $allow_core_fallback = (bool) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/imagegrid/allow/core/poll/fallback', false, $job);
        if (!$allow_core_fallback) {
            return $job;
        }

        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $api_url = $this->api_url($settings);
        $api_key = $this->api_key($settings);
        if (!$api_url || !$api_key) {
            return $job;
        }

        $response = wp_remote_get($api_url . '/v1/jobs/' . rawurlencode((string) $job['remote_job_id']), [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) < 200 || (int) wp_remote_retrieve_response_code($response) >= 300) {
            return $job;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return $job;
        }

        $result = is_array($body['result'] ?? null) ? $body['result'] : [];

        return $repo->update($job['id'], [
            'status' => sanitize_key($body['status'] ?? $job['status']),
            'remote_tileset_id' => sanitize_text_field($result['tileset_id'] ?? $body['tileset_id'] ?? $body['remote_tileset_id'] ?? $job['remote_tileset_id']),
            'result' => $body,
            'error_message' => sanitize_text_field($body['error'] ?? $result['error'] ?? ''),
        ]);
    }

    private function submit_remote($grid_id, array $manifest, array $preflight) {
        $allow_core_fallback = (bool) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/imagegrid/allow/core/remote/fallback', false, $manifest, $preflight);
        if (!$allow_core_fallback) {
            return new \WP_Error(
                'mds3_imagegrid_extension_unavailable',
                __('Remote ImageGrid rendering is handled by the ImageGrid extension.', 'million-dollar-script'),
                ['preflight' => $preflight]
            );
        }

        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $api_url = $this->api_url($settings);
        $api_key = $this->api_key($settings);

        if (!$api_url || !$api_key) {
            return new \WP_Error('mds3_imagegrid_not_configured', __('ImageGrid API is not configured.', 'million-dollar-script'), ['preflight' => $preflight]);
        }

        $payload = $this->core_remote_payload($manifest, $preflight);
        $payload = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/imagegrid/core/remote/payload', $payload, $manifest, $preflight);
        if (!is_array($payload)) {
            return new \WP_Error('mds3_imagegrid_invalid_payload', __('ImageGrid render payload is invalid.', 'million-dollar-script'), ['preflight' => $preflight]);
        }

        $response = wp_remote_post($api_url . '/v1/jobs', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300 || !is_array($body)) {
            return new \WP_Error('mds3_imagegrid_submit_failed', __('ImageGrid render submission failed.', 'million-dollar-script'), ['status' => $code]);
        }

        $result = is_array($body['result'] ?? null) ? $body['result'] : [];

        $job_id = (new RenderJobRepository())->create([
            'grid_id' => $grid_id,
            'provider' => 'imagegrid',
            'remote_job_id' => sanitize_text_field($body['job_id'] ?? $body['id'] ?? ''),
            'remote_tileset_id' => sanitize_text_field($result['tileset_id'] ?? $body['tileset_id'] ?? ''),
            'status' => sanitize_key($body['status'] ?? 'pending'),
            'estimate' => $preflight['estimate'],
            'result' => $body,
        ]);

        return is_wp_error($job_id) ? $job_id : (new RenderJobRepository())->find($job_id);
    }

    private function core_remote_payload(array $manifest, array $preflight) {
        unset($preflight);

        $grid = is_array($manifest['grid'] ?? null) ? $manifest['grid'] : [];

        return [
            'operation' => 'grid_render',
            'options' => [
                'width' => max(1, absint($grid['width'] ?? 0)),
                'height' => max(1, absint($grid['height'] ?? 0)),
                'block_width' => max(1, absint($grid['block_width'] ?? 0)),
                'block_height' => max(1, absint($grid['block_height'] ?? 0)),
                'tile_size' => max(64, absint($manifest['tile_size'] ?? 256)),
                'format' => 'png',
                'strategy' => 'deepzoom',
            ],
        ];
    }

    private function local_job($grid_id, array $manifest, array $preflight, $fallback_reason = '') {
        $result = [
            'mode' => 'local_canvas',
            'manifest' => $manifest,
        ];

        if ('' !== (string) $fallback_reason) {
            $result['fallback_reason'] = sanitize_text_field((string) $fallback_reason);
        }

        $job_id = (new RenderJobRepository())->create([
            'grid_id' => absint($grid_id),
            'provider' => 'local',
            'status' => 'ready',
            'estimate' => is_array($preflight['estimate'] ?? null) ? $preflight['estimate'] : [],
            'result' => $result,
            'error_message' => '',
        ]);

        return is_wp_error($job_id) ? $job_id : (new RenderJobRepository())->find($job_id);
    }

    private function failed_remote_job($grid_id, array $preflight, $message, $code = '') {
        $result = [
            'mode' => 'remote_required',
            'failure_code' => sanitize_key((string) $code),
            'preflight' => $preflight,
        ];

        $job_id = (new RenderJobRepository())->create([
            'grid_id' => absint($grid_id),
            'provider' => 'imagegrid',
            'status' => 'failed',
            'estimate' => is_array($preflight['estimate'] ?? null) ? $preflight['estimate'] : [],
            'result' => $result,
            'error_message' => sanitize_text_field((string) $message),
        ]);

        return is_wp_error($job_id) ? $job_id : (new RenderJobRepository())->find($job_id);
    }
}
