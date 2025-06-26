<?php

namespace MillionDollarScript\Classes\System;

use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\System\Logs;

/**
 * Tests connection and functionality with the extension server.
 */
class ExtensionServerTest {

    /**
     * Test extension server connectivity and basic functionality.
     */
    public static function run_connectivity_test(): array {
        $results = [
            'server_reachable' => false,
            'health_check' => false,
            'api_authenticated' => false,
            'extensions_loaded' => false,
            'license_validation' => false,
            'errors' => [],
            'server_info' => [],
        ];

        // Get server configuration
        $default_server_url = defined('WP_DEBUG') && WP_DEBUG 
            ? 'http://host.docker.internal:15346'
            : 'https://extensions.milliondollarscript.com';
        $server_url = Options::get_option('extension_server_url', $default_server_url);
        $api_key = Options::get_option('extension_server_api_key', '');
        $license_key = Options::get_option('extension_license_key', '');

        $results['server_info']['url'] = $server_url;
        $results['server_info']['has_api_key'] = !empty($api_key);
        $results['server_info']['has_license_key'] = !empty($license_key);

        // Test 1: Basic connectivity
        try {
            $response = wp_remote_get($server_url . '/health', [
                'timeout' => 10,
                'sslverify' => strpos($server_url, 'localhost') === false,
            ]);

            if (!is_wp_error($response)) {
                $results['server_reachable'] = true;
                $status_code = wp_remote_retrieve_response_code($response);
                
                if ($status_code === 200) {
                    $results['health_check'] = true;
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    $results['server_info']['health_response'] = $body;
                } else {
                    $results['errors'][] = "Health check returned status: $status_code";
                }
            } else {
                $results['errors'][] = "Health check failed: " . $response->get_error_message();
            }
        } catch (Exception $e) {
            $results['errors'][] = "Health check exception: " . $e->getMessage();
        }

        // Test 2: API Authentication (if API key is configured)
        if (!empty($api_key)) {
            try {
                $response = wp_remote_get($server_url . '/api/admin/extensions', [
                    'timeout' => 10,
                    'sslverify' => strpos($server_url, 'localhost') === false,
                    'headers' => [
                        'X-API-Key' => $api_key,
                        'Accept' => 'application/json',
                    ],
                ]);

                if (!is_wp_error($response)) {
                    $status_code = wp_remote_retrieve_response_code($response);
                    if ($status_code === 200) {
                        $results['api_authenticated'] = true;
                    } else {
                        $results['errors'][] = "API authentication failed with status: $status_code";
                    }
                } else {
                    $results['errors'][] = "API authentication request failed: " . $response->get_error_message();
                }
            } catch (Exception $e) {
                $results['errors'][] = "API authentication exception: " . $e->getMessage();
            }
        }

        // Test 3: Extension Loading
        try {
            $response = wp_remote_get($server_url . '/api/extensions', [
                'timeout' => 10,
                'sslverify' => strpos($server_url, 'localhost') === false,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                if ($status_code === 200) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    if (isset($body['success']) && $body['success'] && isset($body['data'])) {
                        $results['extensions_loaded'] = true;
                        $results['server_info']['extension_count'] = count($body['data']);
                    } else {
                        $results['errors'][] = "Invalid extension API response format";
                    }
                } else {
                    $results['errors'][] = "Extension API returned status: $status_code";
                }
            } else {
                $results['errors'][] = "Extension API request failed: " . $response->get_error_message();
            }
        } catch (Exception $e) {
            $results['errors'][] = "Extension loading exception: " . $e->getMessage();
        }

        // Test 4: License Validation (if license key is configured)
        if (!empty($license_key)) {
            try {
                $response = wp_remote_post($server_url . '/api/validate', [
                    'timeout' => 10,
                    'sslverify' => strpos($server_url, 'localhost') === false,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'body' => json_encode([
                        'licenseKey' => $license_key,
                        'productIdentifier' => 'test_validation',
                    ]),
                ]);

                if (!is_wp_error($response)) {
                    $status_code = wp_remote_retrieve_response_code($response);
                    if ($status_code === 200) {
                        $body = json_decode(wp_remote_retrieve_body($response), true);
                        if (isset($body['success'])) {
                            $results['license_validation'] = true;
                            $results['server_info']['license_valid'] = $body['valid'] ?? false;
                        } else {
                            $results['errors'][] = "Invalid license validation response format";
                        }
                    } else {
                        $results['errors'][] = "License validation returned status: $status_code";
                    }
                } else {
                    $results['errors'][] = "License validation request failed: " . $response->get_error_message();
                }
            } catch (Exception $e) {
                $results['errors'][] = "License validation exception: " . $e->getMessage();
            }
        }

        // Overall success
        $results['overall_success'] = $results['server_reachable'] && 
                                     $results['health_check'] && 
                                     $results['extensions_loaded'] &&
                                     (empty($api_key) || $results['api_authenticated']);

        return $results;
    }

    /**
     * Display test results in WordPress admin.
     */
    public static function display_test_results(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        $results = self::run_connectivity_test();
        
        echo '<div class="wrap">';
        echo '<h1>Extension Server Connectivity Test</h1>';
        
        if ($results['overall_success']) {
            echo '<div class="notice notice-success"><p><strong>✅ All tests passed!</strong> Extension server is working correctly.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p><strong>❌ Some tests failed.</strong> Please check the details below.</p></div>';
        }

        echo '<div class="card">';
        echo '<h2>Test Results</h2>';
        echo '<table class="widefat">';
        
        $tests = [
            'server_reachable' => 'Server Reachable',
            'health_check' => 'Health Check',
            'api_authenticated' => 'API Authentication',
            'extensions_loaded' => 'Extensions Loading',
            'license_validation' => 'License Validation',
        ];

        foreach ($tests as $key => $label) {
            $status = $results[$key] ? '✅ Pass' : '❌ Fail';
            $class = $results[$key] ? 'success' : 'error';
            echo "<tr><td><strong>$label</strong></td><td class='$class'>$status</td></tr>";
        }
        
        echo '</table>';
        echo '</div>';

        if (!empty($results['server_info'])) {
            echo '<div class="card">';
            echo '<h2>Server Information</h2>';
            echo '<table class="widefat">';
            foreach ($results['server_info'] as $key => $value) {
                $display_value = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : (string)$value;
                echo "<tr><td><strong>" . ucfirst(str_replace('_', ' ', $key)) . "</strong></td><td>" . esc_html($display_value) . "</td></tr>";
            }
            echo '</table>';
            echo '</div>';
        }

        if (!empty($results['errors'])) {
            echo '<div class="card">';
            echo '<h2>Error Details</h2>';
            echo '<ul>';
            foreach ($results['errors'] as $error) {
                echo '<li style="color: #d63638;">' . esc_html($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Add test page to admin menu.
     */
    public static function add_admin_menu(): void {
        add_submenu_page(
            'milliondollarscript',
            'Extension Server Test',
            'Server Test',
            'manage_options',
            'mds_server_test',
            [self::class, 'display_test_results']
        );
    }
}