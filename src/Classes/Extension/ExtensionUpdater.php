<?php

namespace MillionDollarScript\Classes\Extension;

use MillionDollarScript\Classes\Data\Options;

use stdClass;

class ExtensionUpdater {
    /**
     * @var string The extension server URL
     */
    private $extensionServerUrl;

    /**
     * @var string The license key for the extension server
     */
    private $licenseKey;

    /**
     * @var array Cache of update check results
     */
    private $updateCache = [];

    /**
     * ExtensionUpdater constructor.
     * 
     * @param string $extensionServerUrl The base URL of the extension server
     * @param string $licenseKey The license key for the extension server
     */
    public function __construct(string $extensionServerUrl = '', string $licenseKey = '') {
        $this->extensionServerUrl = $extensionServerUrl ?: Options::get_option( 'extension_server_url', 'https://extensions.milliondollarscript.com' );
        $this->licenseKey = $licenseKey ?: Options::get_option('license_key', '');
    }

    /**
     * Initialize the extension updater for a specific plugin
     * 
     * @param string $pluginFile The main plugin file
     * @param string $extensionId The extension ID
     * @param string $currentVersion The current version of the extension
     * @return object|null The update checker instance or null on failure
     */
    public function initUpdater(string $pluginFile, string $extensionId, string $currentVersion): ?object {
        // Use our helper to create the update checker
        $vcsApi = PluginUpdateCheckerHelper::createVcsApi(
            $this->extensionServerUrl,
            $extensionId,
            $currentVersion,
            $this->licenseKey
        );
        
        $updateChecker = PluginUpdateCheckerHelper::createUpdateChecker(
            $vcsApi,
            $pluginFile,
            $extensionId
        );
        
        if (!$updateChecker) {
            return null;
        }
        // Set authentication headers for download
        $updateChecker->setAuthentication([
            'headers' => [
                'x-license-key' => $this->licenseKey,
            ]
        ]);

        // Set authentication headers for download
        $updateChecker->setAuthentication([
            'headers' => [
                'x-license-key' => $this->licenseKey,
            ]
        ]);

        // Set how often to check for updates (in hours)
        $updateChecker->setCheckPeriod(12);
        
        return $updateChecker;
    }

    /**
     * Check for updates for all installed extensions
     * 
     * @param array $extensions Array of installed extensions with their details
     * @return array Array of updates
     */
    public function checkAllUpdates(array $extensions): array {
        $updates = [];
        
        // Check if the plugin update checker is available
        if (!PluginUpdateCheckerHelper::init()) {
            return $updates;
        }
        
        foreach ($extensions as $extension) {
            if (empty($extension['id']) || empty($extension['version']) || empty($extension['plugin_file'])) {
                continue;
            }
            
            $update = $this->checkForUpdate(
                $extension['id'],
                $extension['version'],
                $extension['plugin_file']
            );
            
            if ($update) {
                $updates[] = $update;
            }
        }
        
        return $updates;
    }

    /**
     * Check for updates for a single extension
     * 
     * @param string $extensionId The extension ID
     * @param string $currentVersion The current version
     * @param string $pluginFile The plugin file
     * @return object|null Update information or null if no update is available or on error
     */
    public function checkForUpdate(string $extensionId, string $currentVersion, string $pluginFile): ?object {
        // Check if the plugin update checker is available
        if (!PluginUpdateCheckerHelper::init()) {
            error_log("Extension update check failed: Plugin update checker not available");
            return null;
        }
        
        // Validate extension server configuration
        if (empty($this->extensionServerUrl)) {
            error_log("Extension update check failed: Extension server URL not configured");
            return null;
        }
        
        $cacheKey = md5($extensionId . $currentVersion);
        
        if (isset($this->updateCache[$cacheKey])) {
            return $this->updateCache[$cacheKey];
        }
        
        try {
            $updateChecker = $this->initUpdater($pluginFile, $extensionId, $currentVersion);
            
            // If we couldn't initialize the updater, return null
            if (!$updateChecker) {
                error_log("Extension update check failed: Could not initialize updater for extension {$extensionId}");
                return null;
            }
            
            $update = $updateChecker->checkForUpdates();
            
            // Only cache valid updates
            if ($update) {
                $this->updateCache[$cacheKey] = $update;
                error_log("Extension update found for {$extensionId}: version {$update->version}");
            } else {
                error_log("No extension update available for {$extensionId} (current: {$currentVersion})");
            }
            
            return $update;
            
        } catch (\Exception $e) {
            error_log("Extension update check failed for {$extensionId}: " . $e->getMessage());
            return null;
        }
    }
}
