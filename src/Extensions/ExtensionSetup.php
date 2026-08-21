<?php
/**
 * Setup wizard extension choices.
 *
 * @package MillionDollarScript\V3\Extensions
 */

namespace MillionDollarScript\V3\Extensions;

if (!defined('ABSPATH')) {
    exit;
}

final class ExtensionSetup {

    public const SELECTED_EXTENSIONS_OPTION = 'mds3_setup_selected_extensions';

    public function choices() {
        $catalog = (new ExtensionCatalog())->catalog();
        $choices = [];
        $by_slug = [];

        foreach (['installed', 'available'] as $group) {
            foreach ($catalog[$group] ?? [] as $item) {
                $slug = sanitize_key((string) ($item['slug'] ?? ''));
                if (!$slug || isset($by_slug[$slug])) {
                    continue;
                }

                $item['setup_source'] = 'core' === ($item['source'] ?? '') ? 'core' : $group;
                if ('core' !== $item['setup_source'] && !$this->is_setup_choice_item($item)) {
                    continue;
                }

                $choices[] = $item;
                $by_slug[$slug] = true;
            }
        }

        usort($choices, static function ($a, $b) {
            $category = strcmp((string) ($a['setup_category'] ?? ''), (string) ($b['setup_category'] ?? ''));
            if (0 !== $category) {
                return $category;
            }

            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/setup/extension/choices', $choices, $catalog);
    }

    public function selected_slugs(array $choices = null) {
        $choices = null === $choices ? $this->choices() : $choices;
        $stored = get_option(self::SELECTED_EXTENSIONS_OPTION, null);
        if (!is_array($stored)) {
            return $this->selection_plan($this->default_slugs($choices), $choices)['selected'];
        }

        return $this->selection_plan($stored, $choices)['selected'];
    }

    public function save_selection(array $selected_slugs) {
        $choices = $this->choices();
        $plan = $this->selection_plan($selected_slugs, $choices);
        $selected_slugs = $plan['selected'];

        if (!empty($plan['errors'])) {
            return [
                'activated' => [],
                'skipped' => $plan['skipped'],
                'errors' => $plan['errors'],
            ];
        }

        update_option(self::SELECTED_EXTENSIONS_OPTION, $selected_slugs, false);

        $result = $this->activate_selected_installed($selected_slugs, $choices);
        foreach ($plan['errors'] as $error) {
            $result['errors'][] = $error;
        }
        foreach ($plan['skipped'] as $skipped) {
            $result['skipped'][] = $skipped;
        }

        return $result;
    }

    public function ensure_selected($slug) {
        $slug = sanitize_key((string) $slug);
        if (!$slug) {
            return (new ExtensionRuntime())->selected_slugs();
        }

        $selected = (new ExtensionRuntime())->selected_slugs();
        if (!in_array($slug, $selected, true)) {
            $selected[] = $slug;
        }

        $selected = array_values(array_unique($selected));
        update_option(self::SELECTED_EXTENSIONS_OPTION, $selected, false);

        return $selected;
    }

    /**
     * Apply the low-friction defaults associated with activating an extension.
     *
     * @param string $slug Extension slug.
     * @return array Selected extension slugs.
     */
    public function apply_activation_defaults($slug) {
        $slug = sanitize_key((string) $slug);
        $selected = $this->ensure_selected($slug);

        if ('mds-woocommerce' === $slug) {
            $settings = get_option('mds3_settings', []);
            $settings = is_array($settings) ? $settings : [];
            $settings['payment_provider'] = 'woocommerce';
            update_option('mds3_settings', $settings, false);
        }

        return $selected;
    }

    public function dependency_summary(array $item) {
        $resolver = new ExtensionDependencyResolver();
        $missing = $resolver->missing_requirements($item);
        $conflicts = $resolver->active_conflicts($item);

        return [
            'missing' => $missing,
            'conflicts' => $conflicts,
            'installable' => empty($missing) && empty($conflicts),
        ];
    }

    public function selection_plan(array $selected_slugs, array $choices = null) {
        $choices = null === $choices ? $this->choices() : $choices;
        $by_slug = $this->choices_by_slug($choices);
        $selected = $this->known_slugs($selected_slugs, $by_slug);
        $auto_selected = [];
        $locked = [];
        $errors = [];
        $skipped = [];

        foreach ($by_slug as $slug => $item) {
            if (!empty($item['locked']) || (!empty($item['active']) && empty($item['bundled']))) {
                $selected[] = $slug;
                $locked[$slug] = $this->lock_reason($item);
            }
        }

        $selected = array_values(array_unique($selected));
        $safety = 0;
        do {
            $changed = false;
            foreach ($selected as $slug) {
                $item = $by_slug[$slug] ?? null;
                if (!$item) {
                    continue;
                }

                foreach ($this->capability_list($item['requires'] ?? []) as $requirement) {
                    $provider_slug = $this->provider_slug_for($requirement, $by_slug);
                    if ($provider_slug) {
                        if (!in_array($provider_slug, $selected, true)) {
                            $selected[] = $provider_slug;
                            $changed = true;
                        }

                        if ($provider_slug !== $slug) {
                            $auto_selected[$provider_slug][] = (string) ($item['name'] ?? $slug);
                            $locked[$provider_slug] = sprintf(
                                /* translators: %s: dependent extension name */
                                __('Required by %s', 'million-dollar-script'),
                                (string) ($item['name'] ?? $slug)
                            );
                        }
                    } elseif (!$provider_slug && !$this->core_satisfies($requirement)) {
                        $errors[] = sprintf(
                            /* translators: 1: extension name, 2: missing capability */
                            __('%1$s requires missing capability %2$s.', 'million-dollar-script'),
                            (string) ($item['name'] ?? $slug),
                            $requirement
                        );
                    }
                }
            }
            $selected = array_values(array_unique($selected));
            $safety++;
        } while ($changed && $safety < 20);

        $selected = $this->retain_installed_or_core_selections($selected, $by_slug, $skipped);
        $selected_capabilities = $this->selected_capabilities($selected, $by_slug);
        foreach ($selected as $slug) {
            $item = $by_slug[$slug] ?? null;
            if (!$item) {
                continue;
            }

            foreach ($this->capability_list($item['conflicts'] ?? []) as $conflict) {
                if (in_array($conflict, $selected_capabilities, true)) {
                    $errors[] = sprintf(
                        /* translators: 1: extension name, 2: conflicting capability */
                        __('%1$s conflicts with selected capability %2$s.', 'million-dollar-script'),
                        (string) ($item['name'] ?? $slug),
                        $conflict
                    );
                }
            }
        }

        return [
            'selected' => array_values(array_unique($selected)),
            'locked' => $locked,
            'auto_selected' => $auto_selected,
            'errors' => array_values(array_unique($errors)),
            'skipped' => array_values(array_unique($skipped)),
        ];
    }

    private function default_slugs(array $choices) {
        $defaults = ['mds-grid'];
        foreach ($choices as $item) {
            if (!empty($item['setup_default']) || !empty($item['active'])) {
                $defaults[] = sanitize_key((string) ($item['slug'] ?? ''));
            }
        }

        return array_values(array_unique(array_filter($defaults)));
    }

    private function is_setup_choice_item(array $item) {
        foreach (['provides', 'requires', 'recommends', 'conflicts'] as $key) {
            if (!empty($item[$key]) && is_array($item[$key])) {
                return true;
            }
        }

        if (!empty($item['setup_default']) || !empty($item['requires_service']) || !empty($item['license_required']) || !empty($item['locked'])) {
            return true;
        }

        return !empty($item['setup_category']) && 'extensions' !== sanitize_key((string) $item['setup_category']);
    }

    private function activate_selected_installed(array $selected_slugs, array $choices = null) {
        $result = [
            'activated' => [],
            'skipped' => [],
            'errors' => [],
        ];

        if (!current_user_can('activate_plugins')) {
            $result['skipped'][] = __('Current user cannot activate plugins.', 'million-dollar-script');

            return $result;
        }

        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $choices = null === $choices ? $this->choices() : $choices;
        $resolver = new ExtensionDependencyResolver();
        foreach ($choices as $item) {
            $slug = sanitize_key((string) ($item['slug'] ?? ''));
            $plugin_file = (string) ($item['plugin_file'] ?? '');
            if (!$slug || !in_array($slug, $selected_slugs, true) || !empty($item['active'])) {
                continue;
            }
            if (!$plugin_file || empty($item['installed'])) {
                continue;
            }

            $activation_error = $resolver->activation_error($item);
            if (is_wp_error($activation_error)) {
                $result['errors'][] = $activation_error->get_error_message();
                continue;
            }

            $activated = activate_plugin($plugin_file, '', false, true);
            if (is_wp_error($activated)) {
                $result['errors'][] = $activated->get_error_message();
                continue;
            }

            $result['activated'][] = (string) ($item['name'] ?? $slug);
        }

        return $result;
    }

    private function retain_installed_or_core_selections(array $selected, array $by_slug, array &$skipped) {
        $retained = [];
        foreach ($selected as $slug) {
            $item = $by_slug[$slug] ?? null;
            if (!$item) {
                continue;
            }

            $source = (string) ($item['setup_source'] ?? $item['source'] ?? '');
            if (empty($item['installed']) && 'core' !== $source) {
                $skipped[] = sprintf(
                    /* translators: %s: extension name */
                    __('%s is available but is not installed yet. Install it from Extensions when you are ready.', 'million-dollar-script'),
                    (string) ($item['name'] ?? $slug)
                );
                continue;
            }

            $retained[] = $slug;
        }

        return array_values(array_unique($retained));
    }

    private function choices_by_slug(array $choices) {
        $by_slug = [];
        foreach ($choices as $item) {
            $slug = sanitize_key((string) ($item['slug'] ?? ''));
            if ($slug) {
                $by_slug[$slug] = $item;
            }
        }

        return $by_slug;
    }

    private function known_slugs(array $selected_slugs, array $by_slug) {
        $known = [];
        foreach ($this->slug_list($selected_slugs) as $slug) {
            if (isset($by_slug[$slug])) {
                $known[] = $slug;
            }
        }

        return array_values(array_unique($known));
    }

    private function provider_slug_for($capability, array $by_slug) {
        foreach ($by_slug as $slug => $item) {
            if (in_array($capability, $this->provided_by_choice($item), true)) {
                return $slug;
            }
        }

        return '';
    }

    private function selected_capabilities(array $selected, array $by_slug) {
        $capabilities = [];
        foreach ($selected as $slug) {
            if (!isset($by_slug[$slug])) {
                continue;
            }
            $capabilities = array_merge($capabilities, $this->provided_by_choice($by_slug[$slug]));
        }

        return array_values(array_unique($capabilities));
    }

    private function provided_by_choice(array $item) {
        $provided = $this->capability_list($item['provides'] ?? []);
        $slug = $this->capability((string) ($item['slug'] ?? ''));
        if ($slug) {
            $provided[] = $slug;
        }

        return array_values(array_unique(array_filter($provided)));
    }

    private function core_satisfies($capability) {
        return in_array($capability, (new ExtensionDependencyResolver())->core_capabilities(), true);
    }

    private function capability_list($value) {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value);
        }
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $entry) {
            if (!is_scalar($entry)) {
                continue;
            }
            $capability = $this->capability((string) $entry);
            if ($capability) {
                $items[] = $capability;
            }
        }

        return array_values(array_unique($items));
    }

    private function capability($value) {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9._:-]/', '', $value);

        return (string) $value;
    }

    private function lock_reason(array $item) {
        if (!empty($item['locked'])) {
            return __('Bundled default', 'million-dollar-script');
        }
        if (!empty($item['active'])) {
            return __('Active extension', 'million-dollar-script');
        }

        return '';
    }

    private function slug_list($values) {
        if (!is_array($values)) {
            return [];
        }

        $slugs = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $slug = sanitize_key((string) $value);
            if ($slug) {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

}
