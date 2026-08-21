<?php
/**
 * Extension-owned uninstall cleanup policy.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\V3\Extensions;

use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

final class ExtensionCleanupPolicy {

    const REGISTRY_OPTION = 'mds3_extension_cleanup_registry';
    const INCLUDED_OPTION = 'mds3_extension_cleanup_included';

    /**
     * Persist an extension as a known cleanup-policy participant.
     */
    public static function register(array $extension): bool {
        $id = sanitize_key((string) ($extension['id'] ?? ''));
        $label = sanitize_text_field((string) ($extension['label'] ?? ''));
        if ('' === $id || '' === $label) {
            return false;
        }

        $registry = self::registry_option();
        $registry[$id] = [
            'id' => $id,
            'label' => $label,
            'description' => sanitize_text_field((string) ($extension['description'] ?? '')),
            'default' => !array_key_exists('default', $extension) || (bool) $extension['default'],
        ];
        update_option(self::REGISTRY_OPTION, $registry, false);

        return true;
    }

    /**
     * Return known cleanup-policy participants.
     */
    public static function registered(): array {
        $registry = self::registry_option();
        uasort($registry, static function(array $left, array $right): int {
            return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return $registry;
    }

    /**
     * Whether an extension is included when global deletion is enabled.
     */
    public static function is_included(string $id): bool {
        $id = sanitize_key($id);
        $registry = self::registry_option();
        if ('' === $id || !isset($registry[$id])) {
            return false;
        }

        $included = get_option(self::INCLUDED_OPTION, []);
        $included = is_array($included) ? $included : [];
        if (!array_key_exists($id, $included)) {
            return !empty($registry[$id]['default']);
        }

        return (bool) $included[$id];
    }

    /**
     * Whether an extension may remove its data during uninstall.
     */
    public static function allows_cleanup(string $id): bool {
        $settings = get_option('mds3_settings', []);
        $parent = is_array($settings) ? ($settings['delete_data_on_uninstall'] ?? 'no') : 'no';

        return 'yes' === SettingsSchema::sanitize('delete_data_on_uninstall', $parent)
            && self::is_included($id);
    }

    /**
     * Save the selected extension IDs while preserving only known keys.
     */
    public static function save_inclusions(array $selected_ids): void {
        $selected = [];
        foreach ($selected_ids as $id) {
            $id = sanitize_key((string) $id);
            if ('' !== $id) {
                $selected[$id] = true;
            }
        }

        $included = [];
        foreach (self::registry_option() as $id => $extension) {
            $included[$id] = isset($selected[$id]);
        }
        update_option(self::INCLUDED_OPTION, $included, false);
    }

    /**
     * Remove only the explicitly declared data owned by one extension.
     */
    public static function cleanup(string $id, array $ownership): bool {
        if (!self::allows_cleanup($id)) {
            return false;
        }

        global $wpdb;

        self::delete_post_types((array) ($ownership['post_types'] ?? []));
        self::delete_meta_prefixes($wpdb->postmeta, 'meta_key', (array) ($ownership['post_meta_prefixes'] ?? []));
        self::delete_meta_prefixes($wpdb->usermeta, 'meta_key', (array) ($ownership['user_meta_prefixes'] ?? []));

        foreach ((array) ($ownership['tables'] ?? []) as $suffix) {
            $suffix = sanitize_key((string) $suffix);
            if (!self::is_owned_identifier($suffix)) {
                continue;
            }
            $table = $wpdb->prefix . $suffix;
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed sanitized suffix plus trusted WordPress prefix.
        }

        foreach ((array) ($ownership['option_names'] ?? []) as $option) {
            $option = sanitize_key((string) $option);
            if (self::is_owned_identifier($option)) {
                delete_option($option);
            }
        }
        self::delete_option_prefixes((array) ($ownership['option_prefixes'] ?? []));
        self::delete_transient_prefixes((array) ($ownership['transient_prefixes'] ?? []));

        foreach ((array) ($ownership['cron_hooks'] ?? []) as $hook) {
            $hook = sanitize_key((string) $hook);
            if (self::is_owned_identifier($hook)) {
                wp_clear_scheduled_hook($hook);
            }
        }

        self::forget($id);

        return true;
    }

    private static function forget(string $id): void {
        $id = sanitize_key($id);
        foreach ([self::REGISTRY_OPTION, self::INCLUDED_OPTION, 'mds3_registered_extensions'] as $option) {
            $values = get_option($option, []);
            if (!is_array($values) || !array_key_exists($id, $values)) {
                continue;
            }
            unset($values[$id]);
            update_option($option, $values, false);
        }
    }

    private static function delete_post_types(array $post_types): void {
        global $wpdb;

        $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), static function(string $post_type): bool {
            return self::is_owned_identifier($post_type);
        })));
        if (!$post_types) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type IN ({$placeholders})", $post_types)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated locally.
        foreach (array_chunk(array_map('absint', (array) $ids), 250) as $chunk) {
            $chunk = array_values(array_filter($chunk));
            if (!$chunk) {
                continue;
            }
            $id_placeholders = implode(',', array_fill(0, count($chunk), '%d'));
            $comment_ids = $wpdb->get_col($wpdb->prepare("SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID IN ({$id_placeholders})", $chunk)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated locally.
            if ($comment_ids) {
                $comment_ids = array_map('absint', $comment_ids);
                $comment_placeholders = implode(',', array_fill(0, count($comment_ids), '%d'));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->commentmeta} WHERE comment_id IN ({$comment_placeholders})", $comment_ids)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated locally.
            }
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->comments} WHERE comment_post_ID IN ({$id_placeholders})", $chunk)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated locally.
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$id_placeholders})", $chunk)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated locally.
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$id_placeholders})", $chunk)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated locally.
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->posts} WHERE ID IN ({$id_placeholders})", $chunk)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated locally.
        }
    }

    private static function delete_meta_prefixes(string $table, string $column, array $prefixes): void {
        global $wpdb;
        foreach ($prefixes as $prefix) {
            $prefix = (string) $prefix;
            if (!self::is_owned_identifier(ltrim($prefix, '_'))) {
                continue;
            }
            $like = $wpdb->esc_like($prefix) . '%';
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE {$column} LIKE %s", $like)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and column are fixed internal properties.
        }
    }

    private static function delete_option_prefixes(array $prefixes): void {
        global $wpdb;
        foreach ($prefixes as $prefix) {
            $prefix = sanitize_key((string) $prefix);
            if (!self::is_owned_identifier($prefix)) {
                continue;
            }
            $like = $wpdb->esc_like($prefix) . '%';
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted WordPress options table.
        }
    }

    private static function delete_transient_prefixes(array $prefixes): void {
        global $wpdb;
        foreach ($prefixes as $prefix) {
            $prefix = sanitize_key((string) $prefix);
            if (!self::is_owned_identifier($prefix)) {
                continue;
            }
            foreach (['_transient_', '_transient_timeout_', '_site_transient_', '_site_transient_timeout_'] as $system_prefix) {
                $like = $wpdb->esc_like($system_prefix . $prefix) . '%';
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted WordPress options table.
            }
        }
    }

    private static function registry_option(): array {
        $registry = get_option(self::REGISTRY_OPTION, []);
        if (!is_array($registry)) {
            return [];
        }

        $clean = [];
        foreach ($registry as $id => $extension) {
            $id = sanitize_key((string) $id);
            if ('' === $id || !is_array($extension)) {
                continue;
            }
            $label = sanitize_text_field((string) ($extension['label'] ?? ''));
            if ('' === $label) {
                continue;
            }
            $clean[$id] = [
                'id' => $id,
                'label' => $label,
                'description' => sanitize_text_field((string) ($extension['description'] ?? '')),
                'default' => !empty($extension['default']),
            ];
        }

        return $clean;
    }

    private static function is_owned_identifier(string $identifier): bool {
        return 0 === strpos($identifier, 'mds_') || 0 === strpos($identifier, 'mds3_');
    }
}
