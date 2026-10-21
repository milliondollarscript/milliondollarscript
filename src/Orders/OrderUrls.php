<?php
/**
 * Where a customer is sent to look at their own order.
 *
 * The private link an order confirmation sends is the only route into an order today,
 * and three places rebuilt that link or the page behind it separately (llm-task
 * e9f4451c). This is the one place that knows both.
 *
 * @package MillionDollarScript\V3\Orders
 */

namespace MillionDollarScript\V3\Orders;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderUrls {

    /**
     * The page where this customer can continue the order, carrying the link's two arguments.
     *
     * The Upload page is the customer's order screen. A site that has not assigned one
     * still has a route - the grid the order belongs to - and a site with neither gets
     * the home page rather than a broken link.
     */
    public static function customer_view_url(array $order) {
        $page_id = absint(get_option('mds3_page_upload_id', 0));
        $url = $page_id ? get_permalink($page_id) : '';
        if (!$url) {
            $grid_id = self::order_grid_id($order);
            $url = $grid_id ? self::grid_page_url($grid_id) : home_url('/');
        }

        return add_query_arg([
            'mds3_order_id' => absint($order['id'] ?? 0),
            'mds3_order_key' => rawurlencode((string) ($order['order_key'] ?? '')),
        ], $url);
    }

    public static function order_grid_id(array $order) {
        foreach ((new OrderRepository())->items(absint($order['id'] ?? 0)) as $item) {
            if (!empty($item['grid_id'])) {
                return absint($item['grid_id']);
            }
        }

        return 0;
    }

    /**
     * The public page that shows a grid: the page carrying its grid id.
     */
    public static function grid_page_url($grid_id) {
        $grid_id = absint($grid_id);
        if (!$grid_id) {
            return home_url('/');
        }

        $posts = get_posts([
            'post_type' => ['mds3_grid_page', 'page'],
            'post_status' => ['publish', 'private'],
            'meta_key' => '_mds3_grid_id',
            'meta_value' => $grid_id,
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
        ]);

        return $posts ? get_permalink(absint($posts[0])) : home_url('/');
    }
}
