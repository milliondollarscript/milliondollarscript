<?php
/**
 * Runtime extension capability flags.
 *
 * @package MillionDollarScript\V3\Extensions
 */

namespace MillionDollarScript\V3\Extensions;

if (!defined('ABSPATH')) {
    exit;
}

final class ExtensionRuntime {

    public function is_enabled($slug) {
        $slug = sanitize_key((string) $slug);
        if (!$slug) {
            return false;
        }

        $selected = $this->selected_slugs();
        $enabled = in_array($slug, $selected, true);

        return (bool) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/runtime/enabled', $enabled, $slug, $selected);
    }

    public function selected_slugs() {
        $stored = get_option(ExtensionSetup::SELECTED_EXTENSIONS_OPTION, null);
        if (!is_array($stored)) {
            return ['mds-grid'];
        }

        $selected = [];
        foreach ($stored as $slug) {
            if (!is_scalar($slug)) {
                continue;
            }

            $slug = sanitize_key((string) $slug);
            if ($slug) {
                $selected[] = $slug;
            }
        }

        return array_values(array_unique($selected));
    }
}
