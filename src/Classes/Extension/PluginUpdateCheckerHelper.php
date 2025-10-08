<?php

namespace MillionDollarScript\Classes\Extension;

use MillionDollarScript\Classes\System\Utility;

/**
 * Helper class to manage plugin update checker integration.
 *
 * Supports both the legacy pre-namespaced PUC classes (v5.5) and the modern
 * namespaced release (v5.6+).
 */
class PluginUpdateCheckerHelper {
    private const LEGACY_CHECKER_CLASS = 'Puc_v5p5_Vcs_PluginUpdateChecker';
    private const LEGACY_API_CLASS = 'Puc_v5p5_Vcs_Api';
    private const MODERN_CHECKER_CLASS = '\YahnisElsts\PluginUpdateChecker\v5p6\Vcs\PluginUpdateChecker';
    private const MODERN_API_CLASS = '\YahnisElsts\PluginUpdateChecker\v5p6\Vcs\Api';

    /**
     * Determine if we can use the plugin update checker library.
     */
    public static function init(): bool {
        if (class_exists(self::LEGACY_CHECKER_CLASS) && class_exists(self::LEGACY_API_CLASS)) {
            return true;
        }

        if (class_exists(self::MODERN_CHECKER_CLASS) && class_exists(self::MODERN_API_CLASS)) {
            return true;
        }

        return false;
    }

    /**
     * Build an update checker instance for the supplied extension.
     */
    public static function createUpdateChecker($api, string $pluginFile, string $slug = '', int $checkPeriod = 12, string $optionName = ''): ?object {
        if (!self::init() || $api === null) {
            return null;
        }

        if (class_exists(self::LEGACY_CHECKER_CLASS)) {
            return new \Puc_v5p5_Vcs_PluginUpdateChecker($api, $pluginFile, $slug, $checkPeriod, $optionName);
        }

        if (class_exists(self::MODERN_CHECKER_CLASS)) {
            return new \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\PluginUpdateChecker(
                $api,
                $pluginFile,
                $slug,
                $checkPeriod,
                $optionName
            );
        }

        return null;
    }

    /**
     * Create the VCS API shim that proxies requests to the extension server.
     */
    public static function createVcsApi(string $serverUrl, string $extensionId, string $currentVersion, string $licenseKey, string $pluginFile = ''): ?object {
        if (!self::init()) {
            return null;
        }

        $serverUrl = rtrim($serverUrl, '/');
        $pluginSlug = self::determinePluginSlug($extensionId, $pluginFile);

        if (class_exists(self::LEGACY_API_CLASS)) {
            return new class($serverUrl, $extensionId, $currentVersion, $licenseKey, $pluginFile, $pluginSlug) extends \Puc_v5p5_Vcs_Api {
                private $extensionId;
                private $currentVersion;
                private $licenseKey;
                private $pluginFile;
                private $pluginSlug;

                public function __construct(string $serverUrl, string $extensionId, string $currentVersion, string $licenseKey, string $pluginFile, string $pluginSlug) {
                    parent::__construct($serverUrl);
                    $this->extensionId = $extensionId;
                    $this->currentVersion = $currentVersion;
                    $this->licenseKey = $licenseKey;
                    $this->pluginFile = $pluginFile;
                    $this->pluginSlug = $pluginSlug;
                }

                public function getUpdate() {
                    return $this->checkForUpdate();
                }

                public function checkForUpdate() {
                    return PluginUpdateCheckerHelper::requestUpdate(
                        $this->repositoryUrl,
                        $this->extensionId,
                        $this->currentVersion,
                        $this->licenseKey,
                        $this->pluginFile,
                        $this->pluginSlug
                    );
                }

                protected function getUpdateDetectionStrategies($configBranch) {
                    return [];
                }

                public function getBranch($branchName) {
                    return null;
                }

                public function getTag($tagName) {
                    return null;
                }

                public function getLatestTag() {
                    return null;
                }

                public function getRemoteFile($path, $ref = 'master') {
                    return null;
                }

                public function getLatestCommitTime($ref) {
                    return null;
                }
            };
        }

        if (class_exists(self::MODERN_API_CLASS)) {
            return new class($serverUrl, $extensionId, $currentVersion, $licenseKey, $pluginFile, $pluginSlug) extends \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\Api {
                private $serverUrl;
                private $extensionId;
                private $currentVersion;
                private $licenseKey;
                private $pluginFile;
                private $pluginSlug;

                public function __construct(string $serverUrl, string $extensionId, string $currentVersion, string $licenseKey, string $pluginFile, string $pluginSlug) {
                    parent::__construct($serverUrl);
                    $this->serverUrl = $serverUrl;
                    $this->extensionId = $extensionId;
                    $this->currentVersion = $currentVersion;
                    $this->licenseKey = $licenseKey;
                    $this->pluginFile = $pluginFile;
                    $this->pluginSlug = $pluginSlug;
                }

                public function getUpdate() {
                    return $this->checkForUpdate();
                }

                public function checkForUpdate() {
                    return PluginUpdateCheckerHelper::requestUpdate(
                        $this->serverUrl,
                        $this->extensionId,
                        $this->currentVersion,
                        $this->licenseKey,
                        $this->pluginFile,
                        $this->pluginSlug
                    );
                }

                protected function getUpdateDetectionStrategies($configBranch) {
                    return [];
                }

                public function getBranch($branchName) {
                    return null;
                }

                public function getTag($tagName) {
                    return null;
                }

                public function getLatestTag() {
                    return null;
                }

                public function getRemoteFile($path, $ref = 'master') {
                    return null;
                }

                public function getLatestCommitTime($ref) {
                    return null;
                }
            };
        }

        return null;
    }

    /**
     * Proxy the extension server update endpoint and adapt the response for PUC.
     */
    public static function requestUpdate(string $serverUrl, string $extensionId, string $currentVersion, string $licenseKey, string $pluginFile, string $pluginSlug): ?\stdClass {
        $sslverify = !Utility::is_development_environment();

        $request_body = [
            'extension_id' => $extensionId,
            'current_version' => $currentVersion,
        ];

        $response = wp_remote_post(
            $serverUrl . '/api/public/v1/extensions/check-update',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-license-key' => $licenseKey,
                    'User-Agent' => self::buildUserAgent(),
                ],
                'body' => wp_json_encode($request_body),
                'timeout' => 30,
                'sslverify' => $sslverify,
            ]
        );

        if (is_wp_error($response)) {
            error_log('Extension update check failed: ' . $response->get_error_message());
            return null;
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $bodyString = wp_remote_retrieve_body($response);

        if ($responseCode !== 200) {
            error_log(sprintf(
                'Extension update check failed (%s) url=%s body=%s response=%s',
                $responseCode,
                $serverUrl,
                wp_json_encode($request_body),
                $bodyString
            ));
            return null;
        }

        $body = json_decode($bodyString);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Extension update check failed: Invalid JSON response');
            return null;
        }

        if (empty($body->success)) {
            error_log('Extension update check failed: ' . ($body->error ?? 'Unknown error'));
            return null;
        }

        if (empty($body->data->update_available)) {
            return null;
        }

        $pluginFile = $pluginFile !== '' ? $pluginFile : $extensionId;
        if (!function_exists('plugin_basename')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $pluginBasename = plugin_basename($pluginFile);

        $update = new \stdClass();
        $newVersion = $body->data->new_version ?? '';
        $update->version = $newVersion;
        $update->new_version = $newVersion;
        $update->download_url = $body->data->package_url ?? '';
        $update->package = $update->download_url;
        $update->slug = $pluginSlug !== '' ? $pluginSlug : $extensionId;
        $update->plugin = $pluginBasename;

        if (!empty($body->data->requires)) {
            $update->requires = $body->data->requires;
        }
        if (!empty($body->data->requires_php)) {
            $update->requires_php = $body->data->requires_php;
        }
        if (!empty($body->data->tested)) {
            $update->tested = $body->data->tested;
        }
        if (!empty($body->data->sections)) {
            $update->sections = (array) $body->data->sections;
        }
        if (!empty($body->data->banners)) {
            $update->banners = (array) $body->data->banners;
        }

        return $update;
    }

    /**
     * Determine the slug used by the plugin update checker.
     */
    private static function determinePluginSlug(string $extensionId, string $pluginFile): string {
        if ($pluginFile !== '') {
            $directory = dirname($pluginFile);
            if ($directory !== '.' && $directory !== '') {
                return sanitize_title($directory);
            }
            $basename = basename($pluginFile, '.php');
            if ($basename !== '') {
                return sanitize_title($basename);
            }
        }

        if ($extensionId !== '') {
            return sanitize_title($extensionId);
        }

        return '';
    }

    /**
     * Compose the User-Agent header sent to the extension server.
     */
    private static function buildUserAgent(): string {
        global $wp_version;
        $pluginVersion = defined('MDS_VERSION') ? MDS_VERSION : '1.0.0';

        return sprintf(
            'MDS/%s (WordPress/%s; %s)',
            $pluginVersion,
            $wp_version,
            home_url()
        );
    }
}
