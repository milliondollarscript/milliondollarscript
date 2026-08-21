<?php
/**
 * Build-time distribution policy.
 *
 * @package MillionDollarScript\V3\Support
 */

namespace MillionDollarScript\V3\Support;

if (!defined('ABSPATH')) {
    exit;
}

final class Distribution {

    public const DIRECT = 'direct';
    public const WORDPRESS_ORG = 'wordpress.org';

    public static function id() {
        $distribution = defined('MILLION_DOLLAR_SCRIPT_DISTRIBUTION') ? strtolower(trim((string) MILLION_DOLLAR_SCRIPT_DISTRIBUTION)) : self::DIRECT;

        return self::WORDPRESS_ORG === $distribution ? self::WORDPRESS_ORG : self::DIRECT;
    }

    public static function is_wordpress_org() {
        return self::WORDPRESS_ORG === self::id();
    }

    public static function allows_custom_core_updates() {
        return !self::is_wordpress_org();
    }

    public static function allows_external_plugin_delivery() {
        return !self::is_wordpress_org();
    }

    public static function allows_remote_catalog() {
        return !self::is_wordpress_org();
    }

    public static function extension_catalog_url() {
        return 'https://milliondollarscript.com/catalog';
    }
}
