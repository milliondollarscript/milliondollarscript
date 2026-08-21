<?php
/**
 * Shared admin field help markup.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin;

use MillionDollarScript\V3\Docs\DocsRegistry;

if (!defined('ABSPATH')) {
    exit;
}

final class FieldHelp {

    public static function description($description, $id = '', $class = '') {
        $description = trim((string) $description);
        if ('' === $description) {
            return '';
        }

        $classes = ['description', 'mds3-field-description'];
        foreach (preg_split('/\s+/', trim((string) $class)) ?: [] as $extra_class) {
            $extra_class = sanitize_html_class($extra_class);
            if ('' !== $extra_class) {
                $classes[] = $extra_class;
            }
        }
        $id_attr = '' !== (string) $id ? ' id="' . esc_attr((string) $id) . '"' : '';

        return '<span' . $id_attr . ' class="' . esc_attr(implode(' ', array_unique($classes))) . '">' . esc_html($description) . '</span>';
    }

    public static function info($help, $label = '') {
        $help = trim((string) $help);
        if ('' === $help) {
            return '';
        }

        $label = '' !== trim((string) $label) ? (string) $label : __('More information', 'million-dollar-script');

        return '<span class="mds3-help" tabindex="0" role="button" aria-haspopup="true" aria-expanded="false" aria-label="' . esc_attr($label . ': ' . $help) . '" data-help="' . esc_attr($help) . '">?</span>';
    }

    public static function docs_link($target, $label = '', array $args = []) {
        $target = trim((string) $target);
        if ('' === $target) {
            return '';
        }

        $label = '' !== trim((string) $label) ? (string) $label : __('Docs', 'million-dollar-script');
        $package = sanitize_key((string) ($args['package'] ?? ''));
        $fallback_url = (string) ($args['fallback_url'] ?? '');
        $is_url = (bool) preg_match('#^(https?:)?//#i', $target) || str_starts_with($target, '/') || str_starts_with($target, '#');
        $url = $is_url ? $target : (new DocsRegistry())->url($target, $package, $fallback_url);
        $admin_url = function_exists('admin_url') ? admin_url('admin.php') : '';
        $external = (bool) preg_match('#^https?://#i', $url) && (!$admin_url || !str_starts_with($url, $admin_url));

        return '<a class="mds3-docs-link" href="' . esc_url($url) . '"' . ($external ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . esc_html($label) . '</a>';
    }
}
