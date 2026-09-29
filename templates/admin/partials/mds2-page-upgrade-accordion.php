<?php
/**
 * Per-page Million Dollar Script 2 upgrade choices.
 *
 * One collapsible group lists all detected MDS2 pages. Unmodified pages are
 * always updated in place (checkbox locked on). Modified pages let the user
 * choose: checked upgrades the page in place (original content saved);
 * unchecked keeps the page and a fresh MDS3 page is created instead.
 * The "Apply to all modified pages" checkbox sits outside the group.
 *
 * Pass space-separated form ids in $form_ids so a single chooser feeds both
 * the standard-pages form and the migration import form. The first id is the
 * DOM-owning form (the checkboxes render inside it, reinforced by the form
 * attribute); extra ids are synced by cloning checked values into hidden
 * inputs on submit, because browsers only honor a single id in the form
 * attribute.
 *
 * Expects: $candidates (array from LegacySource::page_candidates()),
 * $field (form field name, default mds2_upgrade_pages).
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$upgrade_field = isset($field) && $field ? $field : 'mds2_upgrade_pages';
$upgrade_form_ids = isset($form_ids) && is_string($form_ids) && $form_ids !== ''
    ? $form_ids
    : 'mds3-ensure-standard-pages';
$upgrade_form_id_list = array_values(array_filter(array_map('trim', explode(' ', $upgrade_form_ids))));
$upgrade_primary_form_id = $upgrade_form_id_list[0] ?? '';
$upgrade_extra_form_ids = array_slice($upgrade_form_id_list, 1);
$upgrade_labels = \MillionDollarScript\V3\Pages\PageRepository::labels();
$upgrade_parent_id = 'mds3-upgrade-apply-all-' . substr(md5($upgrade_field . $upgrade_form_ids), 0, 8);
$upgrade_modified = array_filter((array) $candidates, static function ($candidate) {
    return empty($candidate['unmodified']);
});
?>
<div class="mds3-upgrade-accordion">
    <p class="description">
        <?php esc_html_e('Unmodified Million Dollar Script 2 pages are updated in place. For pages whose content you changed, check the box to upgrade them in place (the original content is saved) or leave it unchecked to keep that page and create a new Million Dollar Script 3 page instead.', 'million-dollar-script'); ?>
    </p>
    <?php if ($candidates && $upgrade_modified) : ?>
        <label class="mds3-upgrade-accordion__parent" for="<?php echo esc_attr($upgrade_parent_id); ?>">
            <input type="checkbox" id="<?php echo esc_attr($upgrade_parent_id); ?>" data-upgrade-parent />
            <strong><?php esc_html_e('Apply to all modified pages', 'million-dollar-script'); ?></strong>
        </label>
    <?php endif; ?>
    <?php if (!$candidates) : ?>
        <p><?php esc_html_e('No Million Dollar Script 2 pages were detected.', 'million-dollar-script'); ?></p>
    <?php endif; ?>
    <?php if ($candidates) : ?>
    <details class="mds3-upgrade-accordion__group" open>
        <summary>
            <strong><?php esc_html_e('Detected Million Dollar Script 2 pages', 'million-dollar-script'); ?></strong>
            <em class="mds3-upgrade-accordion__count"><?php echo esc_html(sprintf(/* translators: %d: page count */ _n('%d page', '%d pages', count($candidates), 'million-dollar-script'), count($candidates))); ?></em>
        </summary>
    <?php endif; ?>
    <?php foreach ((array) $candidates as $upgrade_candidate) : ?>
        <?php
        $upgrade_post_id = absint($upgrade_candidate['post_id'] ?? 0);
        if (!$upgrade_post_id) {
            continue;
        }
        $upgrade_type = sanitize_key($upgrade_candidate['type'] ?? '');
        $upgrade_unmodified = !empty($upgrade_candidate['unmodified']);
        $upgrade_title = (string) ($upgrade_candidate['title'] ?: get_the_title($upgrade_post_id));
        $upgrade_url = (string) get_permalink($upgrade_post_id);
        ?>
        <details class="mds3-upgrade-accordion__item<?php echo $upgrade_unmodified ? ' is-locked' : ''; ?>" open>
            <summary>
                <input type="checkbox" name="<?php echo esc_attr($upgrade_field); ?>[]" value="<?php echo esc_attr($upgrade_post_id); ?>" data-upgrade-check<?php echo $upgrade_unmodified ? ' checked disabled' : ''; ?><?php echo $upgrade_primary_form_id ? ' form="' . esc_attr($upgrade_primary_form_id) . '"' : ''; ?> />
                <strong><?php echo esc_html($upgrade_title ? $upgrade_title : sprintf(__('Page %d', 'million-dollar-script'), $upgrade_post_id)); ?></strong>
                <?php if (!empty($upgrade_labels[$upgrade_type])) : ?>
                    <span class="mds3-upgrade-accordion__type"><?php echo esc_html($upgrade_labels[$upgrade_type]); ?></span>
                <?php endif; ?>
                <em class="mds3-upgrade-accordion__state">
                    <?php
                    if ($upgrade_unmodified) {
                        esc_html_e('Will be updated in place', 'million-dollar-script');
                    } else {
                        esc_html_e('Modified — choose below', 'million-dollar-script');
                    }
                    ?>
                </em>
                <?php if ($upgrade_url) : ?>
                    <a class="mds3-upgrade-accordion__view" href="<?php echo esc_url($upgrade_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('View', 'million-dollar-script'); ?></a>
                <?php endif; ?>
            </summary>
            <?php if (!$upgrade_unmodified) : ?>
                <div class="mds3-upgrade-accordion__body">
                    <p>
                        <?php esc_html_e('Checked: Million Dollar Script 3 updates this page in place and keeps a copy of the original content. Unchecked: this page is left untouched and a new Million Dollar Script 3 page is created instead.', 'million-dollar-script'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
    <?php if ($candidates) : ?>
    </details>
    <?php endif; ?>
    <script>
        (function () {
            var root = document.currentScript.closest('.mds3-upgrade-accordion');
            if (!root) {
                return;
            }
            var parent = root.querySelector('[data-upgrade-parent]');
            var checks = Array.prototype.slice.call(root.querySelectorAll('[data-upgrade-check]:not(:disabled)'));
            function syncParent() {
                if (!parent || !checks.length) {
                    return;
                }
                var all = checks.every(function (c) {
                    return c.checked;
                });
                var none = checks.every(function (c) {
                    return !c.checked;
                });
                parent.checked = all;
                parent.indeterminate = !all && !none;
            }
            if (parent && checks.length) {
                parent.addEventListener('change', function () {
                    var target = parent.checked;
                    checks.forEach(function (c) {
                        c.checked = target;
                    });
                    syncParent();
                });
            }
            checks.forEach(function (c) {
                c.addEventListener('change', syncParent);
            });
            // Checkbox clicks inside a <summary> must not toggle the disclosure.
            Array.prototype.forEach.call(root.querySelectorAll('summary input'), function (i) {
                i.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            });
            // Extra forms (beyond the DOM-owning one) receive the checked ids
            // as hidden inputs at submit time. They parse after this script,
            // so bind once the document is ready.
            function bindExtraForms() {
                <?php echo json_encode(array_values($upgrade_extra_form_ids)); ?>.forEach(function (id) {
                    var form = document.getElementById(id);
                    if (!form) {
                        return;
                    }
                    form.addEventListener('submit', function () {
                        Array.prototype.forEach.call(form.querySelectorAll('input[data-mds3-upgrade-sync]'), function (h) {
                            h.remove();
                        });
                        checks.forEach(function (c) {
                            if (!c.checked) {
                                return;
                            }
                            var h = document.createElement('input');
                            h.type = 'hidden';
                            h.name = c.name;
                            h.value = c.value;
                            h.setAttribute('data-mds3-upgrade-sync', '');
                            form.appendChild(h);
                        });
                    });
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindExtraForms);
            } else {
                bindExtraForms();
            }
            syncParent();
        })();
    </script>
</div>
