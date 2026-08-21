<?php
/**
 * WP-CLI ImageGrid integration fixture for the MDS3 rewrite.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/imagegrid-fixture.php
 */

use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Rendering\ImageGridService;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\MillionDollarScript\\Extensions\\ImageGrid\\Main')) {
    throw new RuntimeException('ImageGrid fixture requires the mds-imagegrid extension to be active.');
}

if (!class_exists('MDS3_ImageGrid_Fixture_Client')) {
    class MDS3_ImageGrid_Fixture_Client {
        public const BASE_JOB = '11111111-1111-4111-8111-111111111111';
        public const BASE_TILESET = '22222222-2222-4222-8222-222222222222';
        public const PATCH_JOB = '33333333-3333-4333-8333-333333333333';
        public const PATCH_TILESET = '44444444-4444-4444-8444-444444444444';
        public const ASSET_ID = '55555555-5555-4555-8555-555555555555';

        public $manifest_asset_count = 0;
        public $patches_created = 0;
        public $tile_requests = 0;
        public $manifest_idempotency_keys = [];
        public $patch_idempotency_keys = [];
        public $fail_manifest_once = false;

        public function get_api_url() {
            return 'https://api.imagegrid.test';
        }

        public function ready() {
            return ['ok' => true];
        }

        public function account() {
            return [
                'account' => [
                    'name' => 'Fixture Account',
                    'slug' => 'fixture',
                ],
                'features' => [
                    'cdn_delivery' => true,
                    'mds3_cdn' => true,
                ],
                'entitlements' => [
                    'cdn_delivery' => [
                        'enabled' => true,
                        'feature_code' => 'cdn_delivery',
                        'status' => 'active',
                    ],
                    'mds3_cdn' => [
                        'enabled' => true,
                        'feature_code' => 'cdn_delivery',
                        'status' => 'active',
                    ],
                ],
                'usage' => [
                    'processing_credits' => 1,
                ],
            ];
        }

        public function create_manifest_render_job(array $manifest, array $preflight = [], $idempotency_key = '') {
            unset($preflight);

            $this->manifest_asset_count = count((array) ($manifest['assets'] ?? []));
            $this->manifest_idempotency_keys[] = (string) $idempotency_key;
            if ($this->fail_manifest_once) {
                $this->fail_manifest_once = false;
                return new WP_Error('http_request_failed', 'ImageGrid fixture simulated a lost response.');
            }

            return [
                'id' => self::BASE_JOB,
                'operation' => 'grid_render',
                'status' => 'processing',
            ];
        }

        public function get_job($job_id) {
            if (self::PATCH_JOB === (string) $job_id) {
                return [
                    'id' => self::PATCH_JOB,
                    'operation' => 'tile_patch',
                    'status' => 'ready',
                    'result' => $this->tileset_result(self::PATCH_TILESET),
                ];
            }

            return [
                'id' => self::BASE_JOB,
                'operation' => 'grid_render',
                'status' => 'ready',
                'result' => $this->tileset_result(self::BASE_TILESET),
            ];
        }

        public function prepare_manifest_asset_patches(array $manifest) {
            $assets = array_values(array_filter((array) ($manifest['assets'] ?? []), 'is_array'));
            if (!$assets) {
                return new WP_Error('mds3_imagegrid_fixture_missing_assets', 'ImageGrid fixture expected at least one manifest asset.');
            }

            $patches = [];
            foreach ($assets as $asset) {
                $patches[] = [
                    'type' => 'asset',
                    'asset_id' => self::ASSET_ID,
                    'x' => absint($asset['x'] ?? 0),
                    'y' => absint($asset['y'] ?? 0),
                    'width' => max(1, absint($asset['width'] ?? 0)),
                    'height' => max(1, absint($asset['height'] ?? 0)),
                    'fit' => sanitize_key((string) ($asset['fit_mode'] ?? 'cover')),
                ];
            }

            return $patches;
        }

        public function create_tile_patch_job($tileset_id, array $patches, $idempotency_key = '') {
            if (self::BASE_TILESET !== (string) $tileset_id) {
                return new WP_Error('mds3_imagegrid_fixture_wrong_tileset', 'ImageGrid fixture received an unexpected base tileset.');
            }

            $this->patches_created += count($patches);
            $this->patch_idempotency_keys[] = (string) $idempotency_key;

            return [
                'id' => self::PATCH_JOB,
                'operation' => 'tile_patch',
                'status' => 'processing',
            ];
        }

        public function get_tileset_tile_request($tileset_id, $z, $x, $y, $format = 'png') {
            $this->tile_requests++;

            return [
                'url' => sprintf(
                    'https://api.imagegrid.test/v1/tilesets/%s/xyz/%d/%d/%d.%s',
                    rawurlencode((string) $tileset_id),
                    absint($z),
                    absint($x),
                    absint($y),
                    sanitize_key((string) $format) ?: 'png'
                ),
                'headers' => [
                    'Authorization' => 'Bearer fixture-key',
                    'X-API-Key' => 'fixture-key',
                ],
                'timeout' => 20,
                'cache_ttl' => 300,
            ];
        }

        private function tileset_result($tileset_id) {
            $template = sprintf(
                'https://cdn.imagegrid.test/cdn/tilesets/%s/v/7/xyz/{z}/{x}/{y}.{format}',
                rawurlencode((string) $tileset_id)
            );

            return [
                'tileset_id' => $tileset_id,
                'format' => 'png',
                'tile_size' => 256,
                'min_level' => 0,
                'max_level' => 9,
                'total_tiles' => 4,
                'public_tile_url_template' => $template,
                'tile_url_template' => $template,
            ];
        }
    }
}

if (!class_exists('MDS3_ImageGrid_Fixture_Grid')) {
    class MDS3_ImageGrid_Fixture_Grid {
        public function to_array() {
            return [
                'id' => 0,
                'width' => 640,
                'height' => 320,
                'block_width' => 32,
                'block_height' => 32,
                'settings' => [],
            ];
        }
    }
}

function mds3_imagegrid_fixture_snapshot_options(array $keys) {
    global $wpdb;

    $snapshot = [];
    foreach ($keys as $key) {
        $exists = null !== $wpdb->get_var($wpdb->prepare(
            'SELECT option_name FROM ' . DB::ident($wpdb->options) . ' WHERE option_name = %s LIMIT 1',
            $key
        ));

        $snapshot[$key] = [
            'exists' => $exists,
            'value' => $exists ? get_option($key) : null,
        ];
    }

    return $snapshot;
}

function mds3_imagegrid_fixture_restore_options(array $snapshot) {
    foreach ($snapshot as $key => $item) {
        if (!empty($item['exists'])) {
            update_option($key, $item['value'], false);
        } else {
            delete_option($key);
        }
    }
}

function mds3_imagegrid_fixture_create_attachment() {
    if (!function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('ImageGrid fixture requires GD to create a fixture attachment.');
    }

    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        throw new RuntimeException('Could not resolve uploads directory: ' . $uploads['error']);
    }

    $path = trailingslashit($uploads['path']) . 'mds3-imagegrid-fixture.png';
    $image = imagecreatetruecolor(32, 32);
    $background = imagecolorallocate($image, 14, 116, 144);
    imagefilledrectangle($image, 0, 0, 32, 32, $background);
    imagepng($image, $path);
    imagedestroy($image);

    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => 'MDS 3.0 ImageGrid Fixture',
        'post_status' => 'inherit',
    ], $path);

    if (is_wp_error($attachment_id) || !$attachment_id) {
        throw new RuntimeException('Could not create ImageGrid fixture attachment.');
    }

    $metadata = wp_generate_attachment_metadata($attachment_id, $path);
    wp_update_attachment_metadata($attachment_id, $metadata);

    return absint($attachment_id);
}

$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$admin) {
    throw new RuntimeException('ImageGrid fixture requires an administrator user.');
}
wp_set_current_user(absint($admin[0]));

$option_keys = [
    'mds_imagegrid_enabled',
    'mds_imagegrid_api_url',
    'mds_imagegrid_api_key',
    'mds_imagegrid_local_render_threshold_megapixels',
    'mds_imagegrid_quota_grid_megapixels',
    'mds_imagegrid_quota_source_megapixels',
    'mds_imagegrid_quota_tile_estimate',
    'mds_imagegrid_quota_storage_bytes',
    'mds_imagegrid_quota_processing_credits',
    'mds_imagegrid_last_account',
];

$snapshot = mds3_imagegrid_fixture_snapshot_options($option_keys);
$grid_id = 0;
$placement_id = 0;
$attachment_id = 0;
$client = new MDS3_ImageGrid_Fixture_Client();
$http_probe = null;
$http_probe_filter = null;

add_filter('mds_imagegrid_client', static function ($current = null) use ($client) {
    unset($current);

    return $client;
}, 1);

try {
    $http_probe_filter = static function ($preempt, $args, $url) use (&$http_probe) {
        unset($preempt);

        if ('http://host.docker.internal:18082/v1/jobs' !== $url) {
            return false;
        }

        $http_probe = $args;

        return [
            'headers' => [],
            'body' => wp_json_encode([
                'id' => MDS3_ImageGrid_Fixture_Client::BASE_JOB,
                'operation' => 'grid_render',
                'status' => 'queued',
            ]),
            'response' => [
                'code' => 201,
                'message' => 'Created',
            ],
            'cookies' => [],
            'filename' => null,
        ];
    };
    add_filter('pre_http_request', $http_probe_filter, 10, 3);

    $transport_client = new \MillionDollarScript\Extensions\ImageGrid\API_Client('http://host.docker.internal:18082', 'fixture-key');
    $transport_response = $transport_client->create_grid_render_job(new MDS3_ImageGrid_Fixture_Grid(), [], 'mds-grid-transport-fixture');
    remove_filter('pre_http_request', $http_probe_filter, 10);
    $http_probe_filter = null;

    if (is_wp_error($transport_response)) {
        throw new RuntimeException('ImageGrid transport probe failed: ' . $transport_response->get_error_message());
    }
    $transport_payload = json_decode((string) ($http_probe['body'] ?? ''), true);
    $transport_options = is_array($transport_payload['options'] ?? null) ? $transport_payload['options'] : [];
    if (
        0 !== absint($transport_options['min_level'] ?? -1) ||
        10 !== absint($transport_options['max_level'] ?? 0) ||
        'deepzoom' !== ($transport_options['strategy'] ?? '') ||
        'Bearer fixture-key' !== ($http_probe['headers']['Authorization'] ?? '') ||
        'mds-grid-transport-fixture' !== ($http_probe['headers']['Idempotency-Key'] ?? '') ||
        0 !== (int) ($http_probe['redirection'] ?? -1) ||
        !array_key_exists('reject_unsafe_urls', $http_probe) ||
        false !== (bool) $http_probe['reject_unsafe_urls']
    ) {
        throw new RuntimeException('ImageGrid transport did not use the secure full-resolution Deep Zoom contract.');
    }

    $invalid_idempotency = $transport_client->create_grid_render_job(new MDS3_ImageGrid_Fixture_Grid(), [], 'bad key');
    if (!is_wp_error($invalid_idempotency) || 'mds_imagegrid_invalid_idempotency_key' !== $invalid_idempotency->get_error_code()) {
        throw new RuntimeException('ImageGrid transport did not reject an invalid idempotency key before submission.');
    }

    $transport_tile = $transport_client->get_tileset_tile_request(MDS3_ImageGrid_Fixture_Client::BASE_TILESET, 0, 0, 0);
    if (is_wp_error($transport_tile) || 0 !== (int) ($transport_tile['redirection'] ?? -1)) {
        throw new RuntimeException('ImageGrid tile transport allowed redirects.');
    }
    if (
        !\MillionDollarScript\Extensions\ImageGrid\API_Client::is_allowed_url('http://host.docker.internal:18082') ||
        \MillionDollarScript\Extensions\ImageGrid\API_Client::is_allowed_url('http://user:password@host.docker.internal:18082') ||
        \MillionDollarScript\Extensions\ImageGrid\API_Client::is_allowed_url('http://host.docker.internal:18082?target=other') ||
        \MillionDollarScript\Extensions\ImageGrid\API_Client::is_allowed_url('http://host.docker.internal:18082#fragment')
    ) {
        throw new RuntimeException('ImageGrid service URL policy did not enforce the local secure transport boundary.');
    }

    update_option('mds_imagegrid_enabled', true, false);
    update_option('mds_imagegrid_api_url', 'https://api.imagegrid.test', false);
    update_option('mds_imagegrid_api_key', 'fixture-key', false);
    update_option('mds_imagegrid_local_render_threshold_megapixels', 0, false);
    update_option('mds_imagegrid_quota_grid_megapixels', 1000, false);
    update_option('mds_imagegrid_quota_source_megapixels', 1000, false);
    update_option('mds_imagegrid_quota_tile_estimate', 100000, false);
    update_option('mds_imagegrid_quota_storage_bytes', 10737418240, false);
    update_option('mds_imagegrid_quota_processing_credits', 100000, false);
    update_option('mds_imagegrid_last_account', $client->account(), false);

    update_option('mds_imagegrid_api_url', 'http://host.docker.internal:18082', false);
    $diagnostic_endpoints = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/network/diagnostic/endpoints', []);
    update_option('mds_imagegrid_api_url', 'https://api.imagegrid.test', false);
    $diagnostic_ids = wp_list_pluck(is_array($diagnostic_endpoints) ? $diagnostic_endpoints : [], 'id');
    if (!in_array('imagegrid-health', $diagnostic_ids, true) || !in_array('imagegrid-readiness', $diagnostic_ids, true)) {
        throw new RuntimeException('ImageGrid did not register independent health and readiness diagnostics.');
    }

    if (
        !\MillionDollarScript\Extensions\ImageGrid\SubmissionCoordinator::transport_error_is_ambiguous(new WP_Error('http_request_failed', 'Connection reset.'))
        || !\MillionDollarScript\Extensions\ImageGrid\SubmissionCoordinator::transport_error_is_ambiguous(new WP_Error('mds_imagegrid_http_500', 'Server error.', ['status' => 500]))
        || \MillionDollarScript\Extensions\ImageGrid\SubmissionCoordinator::transport_error_is_ambiguous(new WP_Error('mds_imagegrid_http_422', 'Validation failed.', ['status' => 422]))
    ) {
        throw new RuntimeException('ImageGrid retry classification did not preserve only ambiguous submission keys.');
    }

    $grid = (new GridRepository())->create([
        'title' => 'ImageGrid Fixture Grid',
        'width' => 512,
        'height' => 512,
        'block_width' => 16,
        'block_height' => 16,
        'price_per_block' => 1,
        'currency' => 'USD',
        'status' => 'active',
    ]);
    if (is_wp_error($grid)) {
        throw new RuntimeException('Could not create ImageGrid fixture grid: ' . $grid->get_error_message());
    }
    $grid_id = $grid->id();

    $attachment_id = mds3_imagegrid_fixture_create_attachment();
    $placement_id = (new PlacementRepository())->create([
        'grid_id' => $grid_id,
        'attachment_id' => $attachment_id,
        'x' => 32,
        'y' => 48,
        'width' => 128,
        'height' => 96,
        'fit_mode' => 'cover',
        'link_url' => 'https://example.test/imagegrid',
        'alt_text' => 'ImageGrid fixture',
        'status' => 'active',
    ]);
    if (is_wp_error($placement_id)) {
        throw new RuntimeException('Could not create ImageGrid fixture placement: ' . $placement_id->get_error_message());
    }

    $service = new ImageGridService();
    $preflight = $service->preflight($grid_id);
    if (is_wp_error($preflight)) {
        throw new RuntimeException('ImageGrid preflight failed: ' . $preflight->get_error_message());
    }
    if ('imagegrid' !== ($preflight['provider'] ?? '')) {
        throw new RuntimeException('ImageGrid fixture did not select the remote provider.');
    }
    if (empty($preflight['sources'])) {
        throw new RuntimeException('ImageGrid preflight did not include placement sources.');
    }

    $job = $service->submit($grid_id);
    if (is_wp_error($job)) {
        throw new RuntimeException('ImageGrid submit failed: ' . $job->get_error_message());
    }
    if ('imagegrid' !== ($job['provider'] ?? '') || MDS3_ImageGrid_Fixture_Client::BASE_JOB !== ($job['remote_job_id'] ?? '')) {
        throw new RuntimeException('ImageGrid submit did not create a remote render job.');
    }
    if (1 !== $client->manifest_asset_count) {
        throw new RuntimeException('ImageGrid manifest did not carry the fixture placement asset.');
    }
    if (
        1 !== count($client->manifest_idempotency_keys)
        || 0 !== strpos($client->manifest_idempotency_keys[0], 'mds-grid-' . $grid_id . '-')
    ) {
        throw new RuntimeException('ImageGrid render submission did not include a stable per-grid idempotency key.');
    }

    $first_poll = $service->poll($job['id']);
    if (is_wp_error($first_poll)) {
        throw new RuntimeException('ImageGrid first poll failed: ' . $first_poll->get_error_message());
    }
    if ('rendering' !== ($first_poll['status'] ?? '')) {
        throw new RuntimeException('ImageGrid first poll did not start the asset patch job.');
    }
    if (1 !== $client->patches_created) {
        throw new RuntimeException('ImageGrid asset patch job was not created.');
    }
    if (
        1 !== count($client->patch_idempotency_keys)
        || 0 !== strpos($client->patch_idempotency_keys[0], 'mds-patch-')
    ) {
        throw new RuntimeException('ImageGrid patch submission did not include a deterministic idempotency key.');
    }

    $state = \MillionDollarScript\Extensions\ImageGrid\Main::get_grid_state($grid_id);
    if (MDS3_ImageGrid_Fixture_Client::PATCH_JOB !== ($state['patch_job_id'] ?? '') || empty($state['patch_queue_prepared'])) {
        throw new RuntimeException('ImageGrid patch state was not persisted.');
    }

    $second_poll = $service->poll($job['id']);
    if (is_wp_error($second_poll)) {
        throw new RuntimeException('ImageGrid second poll failed: ' . $second_poll->get_error_message());
    }
    if ('ready' !== ($second_poll['status'] ?? '') || MDS3_ImageGrid_Fixture_Client::PATCH_TILESET !== ($second_poll['remote_tileset_id'] ?? '')) {
        throw new RuntimeException('ImageGrid patch completion did not expose the final tileset.');
    }

    $state = \MillionDollarScript\Extensions\ImageGrid\Main::get_grid_state($grid_id);
    if ('ready' !== ($state['status'] ?? '') || MDS3_ImageGrid_Fixture_Client::PATCH_TILESET !== ($state['tileset_id'] ?? '')) {
        throw new RuntimeException('ImageGrid ready state was not stored for tile proxying.');
    }

    update_option('mds_imagegrid_last_account', ['features' => ['mds3_cdn' => false]], false);
    if (\MillionDollarScript\Core\Hooks::apply('million-dollar-script/grid/has/remote/tiles', false, $grid_id)) {
        throw new RuntimeException('ImageGrid advertised remote tiles without CDN Delivery access.');
    }

    update_option('mds_imagegrid_last_account', $client->account(), false);
    if (!\MillionDollarScript\Core\Hooks::apply('million-dollar-script/grid/has/remote/tiles', false, $grid_id)) {
        throw new RuntimeException('ImageGrid did not advertise remote tiles to MDS3 grid state.');
    }

    $metadata = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/grid/remote/tile/metadata', [], $grid_id, $grid);
    if (
        empty($metadata['remote']) ||
        256 !== absint($metadata['tile_size'] ?? 0) ||
        9 !== absint($metadata['max_level'] ?? 0) ||
        'deepzoom' !== ($metadata['level_scheme'] ?? '')
    ) {
        throw new RuntimeException('ImageGrid tile metadata was incomplete.');
    }
    if (
        empty($metadata['url_template']) ||
        false === strpos((string) $metadata['url_template'], MDS3_ImageGrid_Fixture_Client::PATCH_TILESET) ||
        false === strpos((string) $metadata['url_template'], '/cdn/tilesets/') ||
        false === strpos((string) $metadata['url_template'], '/v/7/xyz/') ||
        false === strpos((string) $metadata['url_template'], '{format}')
    ) {
        throw new RuntimeException('ImageGrid direct tile URL template was not preserved in tile metadata.');
    }

    $tile_payload_method = new ReflectionMethod(\MillionDollarScript\V3\Grid\GridAjax::class, 'tile_payload');
    $tile_payload_method->setAccessible(true);
    $tile_payload = $tile_payload_method->invoke(new \MillionDollarScript\V3\Grid\GridAjax(), $grid);
    if (
        'template' !== ($tile_payload['directMode'] ?? '') ||
        empty($tile_payload['direct']) ||
        'deepzoom' !== ($tile_payload['levelScheme'] ?? '') ||
        false === strpos((string) ($tile_payload['url'] ?? ''), '{format}') ||
        false === strpos((string) ($tile_payload['url'] ?? ''), MDS3_ImageGrid_Fixture_Client::PATCH_TILESET)
    ) {
        throw new RuntimeException('MDS core did not expose the ImageGrid public tile URL template as the direct tile payload.');
    }

    $tile_request = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/grid/remote/tile/request', null, $grid_id, 0, 0, 0);
    if (!is_array($tile_request) || false === strpos((string) ($tile_request['url'] ?? ''), MDS3_ImageGrid_Fixture_Client::PATCH_TILESET)) {
        throw new RuntimeException('ImageGrid did not provide a remote tile proxy request.');
    }
    if (empty($tile_request['headers']['Authorization']) || 1 !== $client->tile_requests) {
        throw new RuntimeException('ImageGrid remote tile proxy headers were incomplete.');
    }

    $bounds_method = new ReflectionMethod(\MillionDollarScript\V3\Rendering\TileController::class, 'tile_coordinate_in_bounds');
    $bounds_method->setAccessible(true);
    if (
        !$bounds_method->invoke(null, $grid, 9, 1, 1) ||
        $bounds_method->invoke(null, $grid, 9, 2, 1) ||
        !$bounds_method->invoke(null, $grid, 0, 0, 0) ||
        $bounds_method->invoke(null, $grid, 10, 0, 0)
    ) {
        throw new RuntimeException('ImageGrid proxy bounds did not follow the standard Deep Zoom pyramid.');
    }

    $client->fail_manifest_once = true;
    $ambiguous_job = $service->submit($grid_id);
    if (
        is_wp_error($ambiguous_job)
        || 'failed' !== ($ambiguous_job['status'] ?? '')
        || 'imagegrid' !== ($ambiguous_job['provider'] ?? '')
        || 'remote_required' !== ($ambiguous_job['result']['mode'] ?? '')
    ) {
        throw new RuntimeException('An ambiguous remote submission did not fail safely without local rendering.');
    }
    $retry_key = $client->manifest_idempotency_keys[count($client->manifest_idempotency_keys) - 1] ?? '';
    $retried_job = $service->submit($grid_id);
    $replayed_key = $client->manifest_idempotency_keys[count($client->manifest_idempotency_keys) - 1] ?? '';
    if (
        is_wp_error($retried_job)
        || MDS3_ImageGrid_Fixture_Client::BASE_JOB !== ($retried_job['remote_job_id'] ?? '')
        || '' === $retry_key
        || $retry_key !== $replayed_key
    ) {
        throw new RuntimeException('ImageGrid did not reuse the same idempotency key after an ambiguous transport failure.');
    }

    $background_previous = (new GridRepository())->find($grid_id);
    $background_grid = (new GridRepository())->update($grid_id, [
        'background_image_id' => $attachment_id,
        'background_image_fit' => 'cover',
        'background_image_position' => 'center',
        'background_image_repeat' => 'no-repeat',
        'background_image_opacity' => 75,
    ]);
    if (is_wp_error($background_grid) || !$background_grid) {
        throw new RuntimeException('Could not add the ImageGrid background-image fixture.');
    }

    \MillionDollarScript\Core\Hooks::do(
        'million-dollar-script/admin/grid/saved',
        $background_grid,
        ['background_image_id' => $attachment_id],
        $background_previous,
        'update'
    );

    $background_state = \MillionDollarScript\Extensions\ImageGrid\Main::get_grid_state($grid_id);
    if (
        'stale' !== ($background_state['status'] ?? '')
        || !empty($background_state['tileset_id'])
        || !empty($background_state['base_tileset_id'])
    ) {
        throw new RuntimeException('Changing the grid background did not invalidate hosted ImageGrid output.');
    }
    if (\MillionDollarScript\Core\Hooks::apply('million-dollar-script/grid/has/remote/tiles', false, $grid_id)) {
        throw new RuntimeException('ImageGrid advertised remote tiles while local background-image composition was required.');
    }

    $background_tile_request = \MillionDollarScript\Core\Hooks::apply(
        'million-dollar-script/grid/remote/tile/request',
        null,
        $grid_id,
        0,
        0,
        0
    );
    if (null !== $background_tile_request) {
        throw new RuntimeException('ImageGrid provided a remote tile request while a local grid background was active.');
    }

    $background_submission = \MillionDollarScript\Extensions\ImageGrid\Main::submit_grid_render($grid_id, $background_grid);
    if (
        !is_wp_error($background_submission)
        || 'mds_imagegrid_background_local_only' !== $background_submission->get_error_code()
    ) {
        throw new RuntimeException('ImageGrid did not reject an unsupported hosted background-image submission safely.');
    }

    echo wp_json_encode([
        'grid_id' => $grid_id,
        'render_job_id' => absint($job['id']),
        'remote_job_id' => $job['remote_job_id'],
        'tileset_id' => $state['tileset_id'],
        'patches' => $client->patches_created,
        'tile_proxy' => true,
    ]) . PHP_EOL;
} finally {
    global $wpdb;

    if ($http_probe_filter) {
        remove_filter('pre_http_request', $http_probe_filter, 10);
    }

    if ($grid_id) {
        $wpdb->delete(DB::table('render_jobs'), ['grid_id' => $grid_id]);
        $wpdb->delete(DB::table('placements'), ['grid_id' => $grid_id]);
        delete_option('mds_imagegrid_grid_' . absint($grid_id));
        (new GridRepository())->delete($grid_id);
    }
    if ($placement_id && !$grid_id) {
        $wpdb->delete(DB::table('placements'), ['id' => $placement_id]);
    }
    if ($attachment_id) {
        wp_delete_attachment($attachment_id, true);
    }

    mds3_imagegrid_fixture_restore_options($snapshot);
}
