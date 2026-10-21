<?php
/**
 * Page identity for URLs the plugin hands to customers.
 *
 * @package MillionDollarScript\V3\Support
 */

namespace MillionDollarScript\V3\Support;

if (!defined('ABSPATH')) {
    exit;
}

final class PageUrls {

    /**
     * The identity of the page a URL points at. An empty string means the URL does not name a
     * page on a site.
     *
     * A site still on plain permalinks writes every page as /?page_id=N and writes its front page
     * as /, so host and path cannot tell one page from another: the settings refused a valid
     * Thank You page as "this site's Pixel Grid page", and a payment panel could not see that its
     * Checkout URL pointed at the page the customer was standing on. When a URL resolves to a page
     * on this site, name that page - which also catches the same page written two ways. Anything
     * else, a payment provider or a path that is not a page, keeps the host-and-path answer.
     */
    public static function page_key($url) {
        $url = trim((string) $url);
        if ('' === $url) {
            return '';
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts) || !isset($parts['path'])) {
            return '';
        }

        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : strtolower((string) wp_parse_url((string) home_url(), PHP_URL_HOST));
        if ('' === $host) {
            return '';
        }

        if (function_exists('url_to_postid')) {
            $named = absint(url_to_postid(isset($parts['host']) ? $url : home_url($url)));
            if ($named) {
                return $host . '#page:' . $named;
            }
        }

        return $host . untrailingslashit($parts['path']);
    }

    /**
     * True when both URLs name the same page. A link that points at the page it is on reads
     * to a customer as a dead button, so the plugin has to be able to see the loop.
     */
    public static function is_same_page($left, $right) {
        $key = self::page_key($left);

        return '' !== $key && $key === self::page_key($right);
    }

    /**
     * The page being requested right now, as a URL.
     */
    public static function requested_page() {
        $requested = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';

        return home_url('/' === $requested || '' === $requested ? '/' : $requested);
    }
}
