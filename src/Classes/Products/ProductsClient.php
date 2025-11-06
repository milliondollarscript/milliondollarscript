<?php

namespace MillionDollarScript\Classes\Products;

use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\Data\Options;

/**
 * Products API Client
 *
 * Handles communication with the extension-server-go Products API.
 * Fetches published products (premium plugins) and transforms them
 * to Extensions-compatible schema for unified display.
 */
class ProductsClient {

    /**
     * Get the base URL for the extension server.
     *
     * @return string
     */
    private static function get_base_url(): string {
        // Prefer option set in MDS settings; fallback to constant; then to local dev default
        $opt = is_callable([Options::class, 'get_option']) ? Options::get_option('extension_server_url', '') : '';
        if (is_string($opt) && $opt !== '') {
            return rtrim($opt, '/');
        }
        if (defined('MDS_EXTENSION_SERVER_URL')) {
            return rtrim((string) MDS_EXTENSION_SERVER_URL, '/');
        }
        // Default to production URL; override with MDS_EXTENSION_SERVER_URL constant or wp-config for dev
        return 'https://milliondollarscript.com';
    }

    /**
     * List published products from the extension server.
     *
     * Calls GET /api/public/products with optional filtering.
     *
     * @param array $args {
     *     Optional. Query parameters for filtering.
     *
     *     @type string $type      Product type filter (e.g., 'plugin', 'theme'). Default 'plugin'.
     *     @type bool   $published Filter by published status. Default true.
     *     @type int    $limit     Max results to return. Default 100.
     *     @type int    $offset    Pagination offset. Default 0.
     * }
     * @return array|false Array of products on success, false on failure.
     */
    public static function list_products( array $args = [] ): array|false {
        $defaults = [
            'type'      => 'plugin',
            'published' => true,
            'limit'     => 100,
            'offset'    => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        // Build query string
        $query_params = [
            'type'      => $args['type'],
            'published' => $args['published'] ? 'true' : 'false',
            'limit'     => absint($args['limit']),
            'offset'    => absint($args['offset']),
        ];

        $url = self::get_base_url() . '/api/public/products?' . http_build_query($query_params);

        // Check transient cache first
        $cache_key = 'mds_products_list_' . md5($url);
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent'   => 'MDS-WordPress-Plugin/' . (defined('MDS_VERSION') ? MDS_VERSION : '1.0.0'),
            ],
            'timeout'   => 20,
            'sslverify' => !Utility::is_development_environment(),
        ]);

        if (is_wp_error($response)) {
            error_log('ProductsClient::list_products() - HTTP Error: ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('ProductsClient::list_products() - HTTP ' . $status_code . ': ' . wp_remote_retrieve_body($response));
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data['success'])) {
            error_log('ProductsClient::list_products() - Invalid response format: ' . substr($body, 0, 200));
            return false;
        }

        $products = $data['data'] ?? [];

        // Cache for 60 seconds (same as extensions list)
        set_transient($cache_key, $products, 60);

        return $products;
    }

    /**
     * Get a single product by slug.
     *
     * Calls GET /api/public/products/{slug} to retrieve detailed
     * product information including all plans, pricing, and features.
     *
     * @param string $slug Product slug (e.g., 'my-premium-plugin').
     * @return array|false Product data on success, false on failure.
     */
    public static function get_product( string $slug ): array|false {
        if (empty($slug)) {
            error_log('ProductsClient::get_product() - Empty slug provided');
            return false;
        }

        $url = self::get_base_url() . '/api/public/products/' . urlencode($slug);

        // Check transient cache first
        $cache_key = 'mds_product_detail_' . $slug;
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent'   => 'MDS-WordPress-Plugin/' . (defined('MDS_VERSION') ? MDS_VERSION : '1.0.0'),
            ],
            'timeout'   => 20,
            'sslverify' => !Utility::is_development_environment(),
        ]);

        if (is_wp_error($response)) {
            error_log('ProductsClient::get_product() - HTTP Error: ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code === 404) {
            error_log('ProductsClient::get_product() - Product not found: ' . $slug);
            return false;
        }
        if ($status_code !== 200) {
            error_log('ProductsClient::get_product() - HTTP ' . $status_code . ': ' . wp_remote_retrieve_body($response));
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data['success'])) {
            error_log('ProductsClient::get_product() - Invalid response format: ' . substr($body, 0, 200));
            return false;
        }

        $product = $data['data'] ?? null;
        if (!$product) {
            error_log('ProductsClient::get_product() - No product data in response for: ' . $slug);
            return false;
        }

        // Cache for 60 seconds
        set_transient($cache_key, $product, 60);

        return $product;
    }

    /**
     * Create a Stripe checkout session for a product purchase.
     *
     * Note: This uses the existing /api/public/store/checkout endpoint
     * which already works with both Extensions and Products.
     *
     * @param array $args {
     *     Required checkout parameters.
     *
     *     @type string $priceId        Stripe price ID to purchase.
     *     @type string $extensionSlug  Product/extension slug.
     *     @type string $plan           Plan key (e.g., 'monthly', 'yearly', 'one_time').
     *     @type string $customerEmail  Customer email address.
     *     @type string $successUrl     URL to redirect on successful payment.
     *     @type string $cancelUrl      URL to redirect on cancelled payment.
     *     @type array  $metadata       Optional additional metadata.
     * }
     * @return array|false Checkout session data on success, false on failure.
     */
    public static function create_checkout( array $args = [] ): array|false {
        // Validate required fields
        $required = ['priceId', 'extensionSlug', 'plan', 'customerEmail', 'successUrl', 'cancelUrl'];
        foreach ($required as $field) {
            if (empty($args[$field])) {
                error_log("ProductsClient::create_checkout() - Missing required field: {$field}");
                return false;
            }
        }

        $url = self::get_base_url() . '/api/public/store/checkout';

        $body = [
            'priceId'       => $args['priceId'],
            'extensionSlug' => $args['extensionSlug'],
            'plan'          => $args['plan'],
            'customerEmail' => $args['customerEmail'],
            'successUrl'    => $args['successUrl'],
            'cancelUrl'     => $args['cancelUrl'],
            'metadata'      => $args['metadata'] ?? [],
        ];

        $response = wp_remote_post($url, [
            'body'      => json_encode($body),
            'headers'   => [
                'Content-Type' => 'application/json',
                'User-Agent'   => 'MDS-WordPress-Plugin/' . (defined('MDS_VERSION') ? MDS_VERSION : '1.0.0'),
            ],
            'timeout'   => 20,
            'sslverify' => !Utility::is_development_environment(),
        ]);

        if (is_wp_error($response)) {
            error_log('ProductsClient::create_checkout() - HTTP Error: ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('ProductsClient::create_checkout() - HTTP ' . $status_code . ': ' . wp_remote_retrieve_body($response));
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data['success'])) {
            error_log('ProductsClient::create_checkout() - Invalid response format: ' . substr($body, 0, 200));
            return false;
        }

        return $data;
    }

    /**
     * Clear cached product data.
     *
     * Useful when you need to force a refresh from the server.
     *
     * @param string|null $slug Optional. Clear cache for specific product slug.
     *                          If null, clears all product caches.
     * @return void
     */
    public static function clear_cache( ?string $slug = null ): void {
        if ($slug !== null) {
            delete_transient('mds_product_detail_' . $slug);
        } else {
            // Clear all product caches
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options}
                WHERE option_name LIKE '_transient_mds_product_%'
                OR option_name LIKE '_transient_timeout_mds_product_%'"
            );
        }
    }
}
