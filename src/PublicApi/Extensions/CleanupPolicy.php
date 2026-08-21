<?php
/**
 * Stable extension uninstall-cleanup policy facade.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Extensions;

use MillionDollarScript\V3\Extensions\ExtensionCleanupPolicy;

if (!defined('ABSPATH')) {
    exit;
}

final class CleanupPolicy {

    /**
     * Register an extension as a cleanup-policy participant.
     */
    public static function register(array $extension): bool {
        return ExtensionCleanupPolicy::register($extension);
    }

    /**
     * Return all registered cleanup-policy participants.
     */
    public static function registered(): array {
        return ExtensionCleanupPolicy::registered();
    }

    /**
     * Whether this extension is selected for cleanup.
     */
    public static function is_included(string $id): bool {
        return ExtensionCleanupPolicy::is_included($id);
    }

    /**
     * Whether core and extension policy both permit destructive cleanup.
     */
    public static function allows_cleanup(string $id): bool {
        return ExtensionCleanupPolicy::allows_cleanup($id);
    }

    /**
     * Delete explicitly declared extension-owned data when policy allows it.
     */
    public static function cleanup(string $id, array $ownership): bool {
        return ExtensionCleanupPolicy::cleanup($id, $ownership);
    }
}
