<?php

namespace MillionDollarScript\Classes\Extension;

/**
 * Helper class to manage plugin update checker integration
 */
class PluginUpdateCheckerHelper {
    /**
     * Initialize the plugin update checker
     * 
     * @return bool True if the plugin update checker was initialized successfully
     */
    public static function init(): bool {
        // The plugin-update-checker is already loaded by the main plugin
        // Just check if the required classes exist
        return class_exists('Puc_v5p5_Vcs_PluginUpdateChecker') && 
               class_exists('Puc_v5p5_Vcs_Api');
    }
    
    /**
     * Create a new plugin update checker instance
     * 
     * @param mixed $api The API instance
     * @param string $pluginFile The plugin file
     * @param string $slug The plugin slug
     * @param int $checkPeriod How often to check for updates (in hours)
     * @param string $optionName Where to store update info
     * @return object|null The update checker instance or null on failure
     */
    public static function createUpdateChecker($api, string $pluginFile, string $slug = '', int $checkPeriod = 12, string $optionName = ''): ?object {
        if (!self::init()) {
            return null;
        }
        
        // Use the global namespace to access the class
        $class = 'Puc_v5p5_Vcs_PluginUpdateChecker';
        return new $class($api, $pluginFile, $slug, $checkPeriod, $optionName);
    }
    
    /**
     * Create a new VCS API instance
     * 
     * @param string $serverUrl The server URL
     * @param string $extensionId The extension ID
     * @param string $currentVersion The current version
     * @param string $licenseKey The license key
     * @return object|null The VCS API instance or null on failure
     */
    public static function createVcsApi(string $serverUrl, string $extensionId, string $currentVersion, string $licenseKey): ?object {
        if (!self::init()) {
            return null;
        }
        
        // Define the anonymous class that extends Puc_v5p5_Vcs_Api
        $vcsApiClass = get_class(new class() extends \stdClass {});
        
        // Create a new class that extends Puc_v5p5_Vcs_Api
        $vcsApiClass = get_class(new class($serverUrl, $extensionId, $currentVersion, $licenseKey) {
            private $serverUrl;
            private $extensionId;
            private $currentVersion;
            private $licenseKey;

            public function __construct($serverUrl, $extensionId, $currentVersion, $licenseKey) {
                $this->serverUrl = rtrim($serverUrl, '/');
                $this->extensionId = $extensionId;
                $this->currentVersion = $currentVersion;
                $this->licenseKey = $licenseKey;
            }

            public function getUpdate() {
                return $this->checkForUpdate();
            }

            public function checkForUpdate() {
                // Determine SSL verification based on environment
                $sslverify = !$this->isDevelopmentEnvironment();
                
                $response = wp_remote_post(
                    $this->serverUrl . '/api/extensions/check-update',
                    [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'x-license-key' => $this->licenseKey,
                            'User-Agent' => $this->getUserAgent(),
                        ],
                        'body' => wp_json_encode([
                            'extension_id' => $this->extensionId,
                            'current_version' => $this->currentVersion,
                        ]),
                        'timeout' => 30,
                        'sslverify' => $sslverify,
                    ]
                );

                if (is_wp_error($response)) {
                    error_log('Extension update check failed: ' . $response->get_error_message());
                    return null;
                }

                $response_code = wp_remote_retrieve_response_code($response);
                $body_string = wp_remote_retrieve_body($response);
                
                if ($response_code !== 200) {
                    error_log("Extension update check failed with HTTP {$response_code}: {$body_string}");
                    return null;
                }

                $body = json_decode($body_string);
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

                $update = new \stdClass();
                $update->version = $body->data->new_version;
                $update->download_url = $body->data->package_url;
                $update->slug = $this->extensionId;
                $update->plugin = plugin_basename($this->extensionId);
                
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
                    $update->sections = (array)$body->data->sections;
                }
                if (!empty($body->data->banners)) {
                    $update->banners = (array)$body->data->banners;
                }

                return $update;
            }

            private function isDevelopmentEnvironment() {
                return strpos($this->serverUrl, 'localhost') !== false || 
                       strpos($this->serverUrl, '127.0.0.1') !== false ||
                       defined('WP_DEBUG') && WP_DEBUG;
            }

            private function getUserAgent() {
                global $wp_version;
                $plugin_version = defined('MDS_VERSION') ? MDS_VERSION : '1.0.0';
                return sprintf(
                    'MDS/%s (WordPress/%s; %s)',
                    $plugin_version,
                    $wp_version,
                    home_url()
                );
            }
        });
        
        // Create a new instance of the anonymous class
        return new $vcsApiClass($serverUrl, $extensionId, $currentVersion, $licenseKey);
    }
}
