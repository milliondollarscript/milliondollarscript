<?php
/**
 * Million Dollar Script internal autoloader.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\V3;

if (!defined('ABSPATH')) {
    exit;
}

final class Autoload {

    /**
     * Register the autoloader.
     *
     * @param string $base_path Source directory.
     * @return void
     */
    public static function register($base_path) {
        spl_autoload_register(static function($class) use ($base_path) {
            $base_path = rtrim($base_path, '/\\') . '/';
            $prefixes = [
                'MillionDollarScript\V3\\' => $base_path,
                'MillionDollarScript\Core\\' => $base_path . 'PublicApi/Core/',
                'MillionDollarScript\Commerce\\' => $base_path . 'PublicApi/Commerce/',
                'MillionDollarScript\Media\\' => $base_path . 'PublicApi/Media/',
                'MillionDollarScript\Rendering\\' => $base_path . 'PublicApi/Rendering/',
                'MillionDollarScript\Extensions\\' => $base_path . 'PublicApi/Extensions/',
            ];

            foreach ($prefixes as $prefix => $directory) {
                if (0 !== strpos($class, $prefix)) {
                    continue;
                }

                $relative = substr($class, strlen($prefix));
                $file = $directory . str_replace('\\', '/', $relative) . '.php';
                if (is_readable($file)) {
                    require_once $file;
                }
                return;
            }
        });
    }
}
