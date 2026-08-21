<?php
/**
 * Extension catalog, dependency, setup-selector, license, and claim panels.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Extensions\ExtensionDependencyResolver;
use MillionDollarScript\V3\Extensions\ExtensionLicenseManager;
use MillionDollarScript\V3\Extensions\ExtensionSetup;
use MillionDollarScript\V3\Support\Distribution;
use MillionDollarScript\V3\Support\Template;

if (!defined('ABSPATH')) {
    exit;
}

trait RendersExtensionPanels {

    private function extension_list(array $items, $context = 'catalog') {
        $license_manager = new ExtensionLicenseManager();
        $claim_context = $this->extension_claim_context();
        $view_items = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = sanitize_key((string) ($item['slug'] ?? ''));
            $item['_dependency_rows'] = $this->extension_dependency_rows($item);
            $item['_license_label'] = !empty($item['license_required']) ? (
                $license_manager->is_active($slug)
                    ? ('bundle' === $license_manager->access_source($slug)
                        ? sprintf(
                            /* translators: %s: extension pack name */
                            __('Access via %s', 'million-dollar-script'),
                            $license_manager->bundle_name_for($slug)
                        )
                        : __('Licensed', 'million-dollar-script'))
                    : ($license_manager->tester_access_allows($slug) ? __('Tester access', 'million-dollar-script') : __('License required', 'million-dollar-script'))
            ) : '';
            if (
                $claim_context
                && $slug
                && $slug === (string) ($claim_context['slug'] ?? '')
                && !empty($item['license_required'])
                && !$license_manager->is_active($slug)
            ) {
                $item['_claim_token'] = (string) ($claim_context['claim_token'] ?? '');
            }
            $view_items[] = $item;
        }

        Template::display('admin/partials/extensions-list.php', [
            'context' => sanitize_key((string) $context) ?: 'catalog',
            'items' => $view_items,
        ], $this);
    }

    private function extension_bundle_list(array $bundles) {
        $license_manager = new ExtensionLicenseManager();
        $claim_context = $this->extension_claim_context();
        foreach ($bundles as $index => $bundle) {
            if (!is_array($bundle)) {
                unset($bundles[$index]);
                continue;
            }
            $slug = sanitize_key((string) ($bundle['slug'] ?? ''));
            if ($claim_context && $slug === (string) ($claim_context['slug'] ?? '') && !$license_manager->is_product_active($slug)) {
                $bundles[$index]['_claim_token'] = (string) ($claim_context['claim_token'] ?? '');
            }
        }

        Template::display('admin/partials/extension-bundles.php', [
            'bundles' => array_values($bundles),
            'license_manager' => $license_manager,
        ], $this);
    }

    private function extension_dependency_rows(array $item) {
        $rows = [];
        $resolver = new ExtensionDependencyResolver();
        $missing = $resolver->missing_requirements($item);
        $conflicts = $resolver->active_conflicts($item);
        if ($missing) {
            $rows[] = [__('Needs', 'million-dollar-script'), sprintf(
                /* translators: %s: missing extension capabilities */
                __('Missing %s', 'million-dollar-script'),
                implode(', ', $missing)
            )];
        }
        if ($conflicts) {
            $rows[] = [__('Conflict', 'million-dollar-script'), implode(', ', $conflicts)];
        }
        if (!empty($item['provides']) && is_array($item['provides'])) {
            $rows[] = [__('Provides', 'million-dollar-script'), implode(', ', $item['provides'])];
        }
        if (!empty($item['requires']) && is_array($item['requires'])) {
            $rows[] = [__('Requires', 'million-dollar-script'), implode(', ', $item['requires'])];
        }
        if (!empty($item['conflicts']) && is_array($item['conflicts'])) {
            $rows[] = [__('Conflicts', 'million-dollar-script'), implode(', ', $item['conflicts'])];
        }
        if (!empty($item['requires_service'])) {
            $rows[] = [__('Service', 'million-dollar-script'), __('External service required', 'million-dollar-script')];
        }
        if (!empty($item['minimum_security_level'])) {
            $rows[] = [__('API minimum', 'million-dollar-script'), (string) $item['minimum_security_level']];
        }

        return $rows;
    }

    private function setup_extension_selector(array $choices, array $selected_slugs, ExtensionSetup $extension_setup = null) {
        $extension_setup = $extension_setup ?: new ExtensionSetup();
        $selection_plan = $extension_setup->selection_plan($selected_slugs, $choices);
        $selected_slugs = $selection_plan['selected'];
        $locked_slugs = $selection_plan['locked'];
        $view_choices = [];

        foreach ($choices as $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = sanitize_key((string) ($item['slug'] ?? ''));
            if (!$slug) {
                continue;
            }

            $selected = in_array($slug, $selected_slugs, true);
            $base_locked = !empty($item['locked']) || (!empty($item['active']) && empty($item['bundled']));
            $locked = $base_locked || !empty($locked_slugs[$slug]);
            $summary = $extension_setup->dependency_summary($item);
            $classes = ['mds3-setup-extension-choice'];
            if ($selected) {
                $classes[] = 'is-selected';
            }
            if (!empty($item['active'])) {
                $classes[] = 'is-active';
            }
            if (!empty($selection_plan['auto_selected'][$slug])) {
                $classes[] = 'is-auto-selected';
            }
            if (!empty($summary['missing']) || !empty($summary['conflicts'])) {
                $classes[] = 'needs-attention';
            }

            $view_choices[] = [
                'base_locked' => $base_locked,
                'classes' => implode(' ', $classes),
                'conflicts_json' => wp_json_encode(array_values((array) ($item['conflicts'] ?? []))),
                'item' => $item,
                'locked' => $locked,
                'lock_reason' => !empty($locked_slugs[$slug]) && empty($item['locked']) ? (string) $locked_slugs[$slug] : '',
                'meta_rows' => $this->setup_extension_meta_rows($item, $summary),
                'provides_json' => wp_json_encode(array_values((array) ($item['provides'] ?? []))),
                'requires_json' => wp_json_encode(array_values((array) ($item['requires'] ?? []))),
                'selected' => $selected,
                'slug' => $slug,
                'status_label' => $this->setup_extension_status_label($item),
            ];
        }

        Template::display('admin/partials/setup-extension-selector.php', [
            'choices' => $view_choices,
        ], $this);
    }

    private function setup_extension_meta_rows(array $item, array $summary) {
        $rows = [];
        if (!empty($item['provides'])) {
            $rows[] = [__('Provides', 'million-dollar-script'), implode(', ', (array) $item['provides'])];
        }
        if (!empty($item['requires'])) {
            $rows[] = [__('Requires', 'million-dollar-script'), implode(', ', (array) $item['requires'])];
        }
        if (!empty($summary['missing'])) {
            $rows[] = [__('Missing', 'million-dollar-script'), implode(', ', (array) $summary['missing'])];
        }
        if (!empty($summary['conflicts'])) {
            $rows[] = [__('Conflicts', 'million-dollar-script'), implode(', ', (array) $summary['conflicts'])];
        }
        if (!empty($item['requires_service'])) {
            $rows[] = [__('Service', 'million-dollar-script'), __('External service required', 'million-dollar-script')];
        }
        if (!empty($item['license_required'])) {
            $rows[] = [__('License', 'million-dollar-script'), __('License required', 'million-dollar-script')];
        }

        return $rows;
    }

    private function setup_extension_status_label(array $item) {
        if (!empty($item['locked'])) {
            return __('Bundled default', 'million-dollar-script');
        }
        if (!empty($item['active'])) {
            return __('Selected and active', 'million-dollar-script');
        }
        if (!empty($item['installed'])) {
            return __('Installed', 'million-dollar-script');
        }
        if (!empty($item['license_required'])) {
            return __('Available with license', 'million-dollar-script');
        }
        if (!empty($item['requires_service'])) {
            return __('Available with service', 'million-dollar-script');
        }

        return __('Available', 'million-dollar-script');
    }

    private function extension_actions(array $item) {
        $license_manager = new ExtensionLicenseManager();
        $slug = sanitize_key((string) ($item['slug'] ?? ''));
        $license_active = 'bundle' === sanitize_key((string) ($item['product_type'] ?? ''))
            ? $license_manager->is_product_active($slug)
            : $license_manager->has_access($slug);

        Template::display('admin/partials/extension-actions.php', [
            'item' => $item,
            'external_plugin_delivery' => Distribution::allows_external_plugin_delivery(),
            'license_active' => $license_active,
            'license_manager' => $license_manager,
        ], $this);
    }

    private function extension_purchase_url(array $item) {
        $url = (string) ($item['purchase_url'] ?? '');
        $slug = sanitize_key((string) ($item['slug'] ?? ''));
        if (!$url || !$slug || empty($item['license_required'])) {
            return $url;
        }

        $claim_token = (new ExtensionLicenseManager())->pending_claim_token($slug);
        if (!$claim_token) {
            return $url;
        }

        $is_bundle = 'bundle' === sanitize_key((string) ($item['product_type'] ?? ''));
        $success_url = add_query_arg([
            'page' => 'mds3-extensions',
            'claimToken' => $claim_token,
            $is_bundle ? 'product' : 'extension' => $slug,
        ], admin_url('admin.php'));

        return add_query_arg([
            'claimToken' => $claim_token,
            'siteId' => rawurlencode(home_url('/')),
            'extensionSlug' => $slug,
            'productSlug' => $slug,
            'successUrl' => rawurlencode($success_url),
            'cancelUrl' => rawurlencode(admin_url('admin.php?page=mds3-extensions')),
        ], $url);
    }

    private function extension_license_controls(array $item, ExtensionLicenseManager $license_manager) {
        $slug = sanitize_key((string) ($item['slug'] ?? ''));
        if (!$slug) {
            return;
        }

        Template::display('admin/partials/extension-license-controls.php', [
            'license_manager' => $license_manager,
            'slug' => $slug,
        ], $this);
    }

    private function extension_claim_panel() {
        $claim_context = $this->extension_claim_context();
        if (!$claim_context) {
            return;
        }

        Template::display('admin/partials/extension-claim-panel.php', [
            'claim_token' => (string) ($claim_context['claim_token'] ?? ''),
            'slug' => (string) ($claim_context['slug'] ?? ''),
            'stub_session' => (string) ($claim_context['stub_session'] ?? ''),
        ], $this);
    }

    private function extension_claim_context() {
        $claim_token = sanitize_text_field(wp_unslash($_GET['claimToken'] ?? $_GET['claim'] ?? ''));
        $slug = sanitize_key(wp_unslash($_GET['product'] ?? $_GET['extension'] ?? $_GET['slug'] ?? ''));
        if (!$claim_token || !$slug) {
            return [];
        }

        $stub_session = sanitize_text_field(wp_unslash($_GET['stub_session'] ?? ''));
        if (!preg_match('/^cs_stub_[A-Za-z0-9_-]+$/', $stub_session)) {
            $stub_session = '';
        }

        return [
            'claim_token' => $claim_token,
            'slug' => $slug,
            'stub_session' => $stub_session,
        ];
    }

    private function extension_notice() {
        if (empty($_GET['mds3_extension_status']) || empty($_GET['mds3_extension_message'])) {
            return;
        }

        $status = sanitize_key(wp_unslash($_GET['mds3_extension_status']));
        $message = sanitize_text_field(wp_unslash($_GET['mds3_extension_message']));
        $class = 'notice-info';
        if ('success' === $status) {
            $class = 'notice-success';
        } elseif (in_array($status, ['error', 'warning'], true)) {
            $class = 'error' === $status ? 'notice-error' : 'notice-warning';
        }

        Template::display('admin/partials/extension-notice.php', [
            'class' => $class,
            'message' => $message,
        ], $this);
    }
}
