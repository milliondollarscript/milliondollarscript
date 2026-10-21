<?php
/**
 * Shared behaviour for the admin checkbox accordions.
 *
 * One script serves both page accordions — the Million Dollar Script 2
 * upgrade-in-place chooser and the choose-which-pages-to-create chooser —
 * because both are the same control: a parent checkbox that selects the group,
 * per-item checkboxes that sync it, collapsible items whose checkbox must not
 * toggle the disclosure.
 *
 * The root element carries the configuration:
 *   class="mds3-upgrade-accordion"  scopes this script to its own group
 *   data-extra-forms='["id", ...]'  other forms that receive the checked
 *                                   values as hidden inputs at submit time
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
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
        // Extra forms (beyond the DOM-owning one) receive the checked values
        // as hidden inputs at submit time. They parse after this script, so
        // bind once the document is ready.
        var extraFormIds = [];
        try {
            extraFormIds = JSON.parse(root.getAttribute('data-extra-forms') || '[]');
        } catch (e) {
            extraFormIds = [];
        }
        function bindExtraForms() {
            extraFormIds.forEach(function (id) {
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
