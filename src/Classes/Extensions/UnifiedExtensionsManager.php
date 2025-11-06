<?php

namespace MillionDollarScript\Classes\Extensions;

use MillionDollarScript\Classes\Products\ProductsClient;

/**
 * Unified Extensions Manager
 *
 * Manages the unified display of both free extensions and premium products.
 * Fetches data from both the Extensions API and Products API, merges them
 * into a consistent schema, and provides a single interface for the Extensions page.
 */
class UnifiedExtensionsManager {

    /**
     * Fetch all available items (extensions + products).
     *
     * This method:
     * 1. Fetches free extensions from /api/public/extensions
     * 2. Fetches premium products from /api/public/products
     * 3. Merges both into a unified array
     * 4. Applies sorting and filtering
     *
     * @param array $args {
     *     Optional fetch parameters.
     *
     *     @type bool   $include_products Include products in results. Default true.
     *     @type bool   $include_extensions Include extensions in results. Default true.
     *     @type string $sort_by Field to sort by. Default 'display_name'.
     *     @type string $order Sort order ('asc' or 'desc'). Default 'asc'.
     * }
     * @return array Unified array of extensions and products.
     */
    public static function fetch_all( array $args = [] ): array {
        $defaults = [
            'include_products'   => true,
            'include_extensions' => true,
            'sort_by'            => 'display_name',
            'order'              => 'asc',
        ];

        $args = wp_parse_args($args, $defaults);

        $all_items = [];

        // Fetch extensions (free)
        if ($args['include_extensions']) {
            $extensions = self::fetch_extensions();
            if (is_array($extensions)) {
                $all_items = array_merge($all_items, $extensions);
            }
        }

        // Fetch products (premium)
        if ($args['include_products']) {
            $products = self::fetch_products();
            if (is_array($products)) {
                $all_items = array_merge($all_items, $products);
            }
        }

        // Sort merged array
        if (!empty($all_items)) {
            $all_items = self::sort_items($all_items, $args['sort_by'], $args['order']);
        }

        return $all_items;
    }

    /**
     * Fetch extensions from the extension server.
     *
     * Calls the existing Extensions API endpoint.
     *
     * @return array Array of extension objects.
     */
    private static function fetch_extensions(): array {
        $url = self::get_extension_server_base_url() . '/api/public/extensions';

        $response = wp_remote_get($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent'   => 'MDS-WordPress-Plugin/' . (defined('MDS_VERSION') ? MDS_VERSION : '1.0.0'),
            ],
            'timeout'   => 20,
            'sslverify' => !\MillionDollarScript\Classes\System\Utility::is_development_environment(),
        ]);

        if (is_wp_error($response)) {
            error_log('UnifiedExtensionsManager::fetch_extensions() - HTTP Error: ' . $response->get_error_message());
            return [];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('UnifiedExtensionsManager::fetch_extensions() - HTTP ' . $status_code);
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            error_log('UnifiedExtensionsManager::fetch_extensions() - Invalid JSON response');
            return [];
        }

        // Handle both {success: true, data: [...]} and direct array formats
        if (isset($data['success']) && isset($data['data'])) {
            return is_array($data['data']) ? $data['data'] : [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Fetch products from the extension server.
     *
     * Uses ProductsClient to fetch published products and transforms
     * them to Extensions-compatible schema.
     *
     * @return array Array of product objects in Extensions schema.
     */
    private static function fetch_products(): array {
        $products = ProductsClient::list_products([
            'type'      => 'plugin',
            'published' => true,
            'limit'     => 100,
        ]);

        if ($products === false || !is_array($products)) {
            error_log('UnifiedExtensionsManager::fetch_products() - Failed to fetch products');
            return [];
        }

        // Products API already returns Extensions-compatible schema
        // thanks to the server-side transformation layer.
        // No additional transformation needed here.

        return $products;
    }

    /**
     * Sort items by a specified field.
     *
     * @param array  $items   Array of items to sort.
     * @param string $sort_by Field name to sort by.
     * @param string $order   Sort order ('asc' or 'desc').
     * @return array Sorted array.
     */
    private static function sort_items( array $items, string $sort_by, string $order ): array {
        if (empty($items)) {
            return $items;
        }

        usort($items, function($a, $b) use ($sort_by, $order) {
            $a_value = $a[$sort_by] ?? '';
            $b_value = $b[$sort_by] ?? '';

            // Handle string comparison
            if (is_string($a_value) && is_string($b_value)) {
                $cmp = strcasecmp($a_value, $b_value);
            } else {
                $cmp = $a_value <=> $b_value;
            }

            return ($order === 'desc') ? -$cmp : $cmp;
        });

        return $items;
    }

    /**
     * Filter items by criteria.
     *
     * @param array $items   Array of items to filter.
     * @param array $filters Filter criteria.
     * @return array Filtered array.
     */
    public static function filter_items( array $items, array $filters ): array {
        if (empty($filters) || empty($items)) {
            return $items;
        }

        return array_filter($items, function($item) use ($filters) {
            // Filter by premium status
            if (isset($filters['is_premium'])) {
                $is_premium = !empty($item['isPremium']) || !empty($item['is_premium']);
                if ($is_premium !== (bool) $filters['is_premium']) {
                    return false;
                }
            }

            // Filter by installed status
            if (isset($filters['is_installed'])) {
                $is_installed = !empty($item['is_installed']);
                if ($is_installed !== (bool) $filters['is_installed']) {
                    return false;
                }
            }

            // Filter by search term
            if (!empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $name = strtolower($item['display_name'] ?? $item['name'] ?? '');
                $description = strtolower($item['description'] ?? '');

                if (strpos($name, $search) === false && strpos($description, $search) === false) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Get a single item by ID (extension or product).
     *
     * Attempts to fetch from both Extensions and Products APIs.
     *
     * @param string $id Item ID (UUID).
     * @return array|false Item data on success, false on failure.
     */
    public static function get_by_id( string $id ): array|false {
        if (empty($id)) {
            return false;
        }

        // Try fetching as extension first
        $url = self::get_extension_server_base_url() . '/api/public/extensions/' . urlencode($id);

        $response = wp_remote_get($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent'   => 'MDS-WordPress-Plugin/' . (defined('MDS_VERSION') ? MDS_VERSION : '1.0.0'),
            ],
            'timeout'   => 20,
            'sslverify' => !\MillionDollarScript\Classes\System\Utility::is_development_environment(),
        ]);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (is_array($data) && !empty($data['success']) && !empty($data['data'])) {
                return $data['data'];
            }
        }

        // If not found as extension, try as product by slug
        // (Products API uses slug as identifier, not ID)
        // This would require knowing the slug, which we don't have from ID alone.
        // For now, return false if not found as extension.

        return false;
    }

    /**
     * Get a single item by slug.
     *
     * Attempts to fetch from both Extensions and Products APIs.
     *
     * @param string $slug Item slug.
     * @return array|false Item data on success, false on failure.
     */
    public static function get_by_slug( string $slug ): array|false {
        if (empty($slug)) {
            return false;
        }

        // Try fetching as product first (products use slug as primary identifier)
        $product = ProductsClient::get_product($slug);
        if ($product !== false && is_array($product)) {
            return $product;
        }

        // Try fetching from extensions list (extensions don't have slug-based endpoint)
        $extensions = self::fetch_extensions();
        foreach ($extensions as $extension) {
            $ext_slug = $extension['name'] ?? $extension['slug'] ?? '';
            if ($ext_slug === $slug) {
                return $extension;
            }
        }

        return false;
    }

    /**
     * Clear all caches for unified extensions/products.
     *
     * @return void
     */
    public static function clear_cache(): void {
        ProductsClient::clear_cache();

        // Clear extensions cache (if any)
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_mds_extensions_%'
            OR option_name LIKE '_transient_timeout_mds_extensions_%'"
        );
    }

    /**
     * Get the extension server base URL.
     *
     * @return string
     */
    private static function get_extension_server_base_url(): string {
        $opt = is_callable([\MillionDollarScript\Classes\Data\Options::class, 'get_option'])
            ? \MillionDollarScript\Classes\Data\Options::get_option('extension_server_url', '')
            : '';

        if (is_string($opt) && $opt !== '') {
            return rtrim($opt, '/');
        }

        if (defined('MDS_EXTENSION_SERVER_URL')) {
            return rtrim((string) MDS_EXTENSION_SERVER_URL, '/');
        }

        // Default to production URL; override with MDS_EXTENSION_SERVER_URL constant or wp-config for dev
        return 'https://milliondollarscript.com';
    }
}
