<?php
/**
 * Stable extension admin presentation helpers.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Extensions;

use MillionDollarScript\V3\Admin\FieldHelp;
use MillionDollarScript\V3\Docs\DocsRegistry;

if (!defined('ABSPATH')) {
    exit;
}

final class Admin {

    public static function description($description, $id = '', $class = ''): string {
        return FieldHelp::description($description, $id, $class);
    }

    public static function help($help, $label = ''): string {
        return FieldHelp::info($help, $label);
    }

    public static function docs_link($target, $label = '', array $args = []): string {
        return FieldHelp::docs_link($target, $label, $args);
    }

    public static function docs_url($package, $doc_id = ''): string {
        return (new DocsRegistry())->package_url($package, $doc_id);
    }

    public static function docs_button($package, $label = '', $doc_id = ''): string {
        $url = self::docs_url($package, $doc_id);
        if ('' === $url) {
            return '';
        }

        return self::docs_index_button($label, $url);
    }

    public static function docs_index_button($label = '', $url = ''): string {
        $url = '' !== trim((string) $url)
            ? (string) $url
            : admin_url('admin.php?page=mds3-docs');
        $label = '' !== trim((string) $label) ? (string) $label : __('Documentation', 'million-dollar-script');

        return '<a class="button mds-extension-docs-button" href="' . esc_url($url) . '"><span class="dashicons dashicons-book" aria-hidden="true"></span><span>' . esc_html($label) . '</span></a>';
    }

    public static function shortcode_copy($shortcode, $label = ''): string {
        $shortcode = trim((string) $shortcode);
        if ('' === $shortcode) {
            return '';
        }

        $label = '' !== trim((string) $label)
            ? (string) $label
            : sprintf(
                /* translators: %s: shortcode to copy. */
                __('Copy shortcode %s', 'million-dollar-script'),
                $shortcode
            );

        return '<button type="button" class="mds3-shortcode-copy" data-mds3-copy-shortcode="' . esc_attr($shortcode) . '" aria-label="' . esc_attr($label) . '">'
            . '<code>' . esc_html($shortcode) . '</code>'
            . '<span class="mds3-shortcode-copy-status" aria-live="polite">' . esc_html__('Click to copy', 'million-dollar-script') . '</span>'
            . '</button>';
    }
}
