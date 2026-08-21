<?php
/**
 * Stable extension registration facade.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Extensions;

if (!defined('ABSPATH')) {
    exit;
}

final class Registry {

    public static function register(array $extension): bool {
        $id = sanitize_key($extension['id'] ?? '');
        if ('' === $id) {
            return false;
        }

        $extension['id'] = $id;
        $registered = get_option('mds3_registered_extensions', []);
        $registered = is_array($registered) ? $registered : [];
        $registered[$id] = $extension;
        update_option('mds3_registered_extensions', $registered, false);

        if (isset($extension['cleanup']) && is_array($extension['cleanup'])) {
            $cleanup = $extension['cleanup'];
            CleanupPolicy::register([
                'id' => $id,
                'label' => $cleanup['label'] ?? ($extension['name'] ?? $id),
                'description' => $cleanup['description'] ?? '',
                'default' => $cleanup['default'] ?? true,
            ]);
        }

        if (!empty($extension['init_callback']) && is_callable($extension['init_callback'])) {
            call_user_func($extension['init_callback']);
        }

        return true;
    }

    public static function registered(): array {
        $registered = get_option('mds3_registered_extensions', []);
        return is_array($registered) ? $registered : [];
    }
}
