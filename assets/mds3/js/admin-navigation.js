(function () {
    'use strict';

    function config() {
        return (window.MillionDollarScript && window.MillionDollarScript.adminNavigation) || {};
    }

    function pageFromUrl(url) {
        try {
            return new URL(url, window.location.href).searchParams.get('page') || '';
        } catch (error) {
            return '';
        }
    }

    function directChildLink(item) {
        if (!item || !item.children) {
            return null;
        }

        for (var index = 0; index < item.children.length; index++) {
            if (String(item.children[index].tagName || '').toLowerCase() === 'a') {
                return item.children[index];
            }
        }

        return item.querySelector ? item.querySelector('a') : null;
    }

    function extensionsStateKey() {
        return String(config().extensionsStateKey || 'mds3_sidebar_extensions_open');
    }

    function readExtensionsOpenState() {
        try {
            return window.localStorage.getItem(extensionsStateKey()) === '1';
        } catch (error) {
            return false;
        }
    }

    function writeExtensionsOpenState(expanded) {
        try {
            window.localStorage.setItem(extensionsStateKey(), expanded ? '1' : '0');
        } catch (error) {
            // Storage can be unavailable in private or locked-down browser contexts.
        }
    }

    function bindSidebarNavigation() {
        var navigation = config();
        var corePages = Array.isArray(navigation.corePages) ? navigation.corePages : [];
        var menu = document.querySelector('#toplevel_page_mds3 .wp-submenu');
        if (!menu || menu.getAttribute('data-mds3-navigation-ready') === '1') {
            return;
        }

        var extensionItems = [];
        Array.prototype.slice.call(menu.children).forEach(function (item) {
            var link = directChildLink(item);
            var page = link ? pageFromUrl(link.href) : '';
            if (!page || corePages.indexOf(page) !== -1) {
                return;
            }

            extensionItems.push({
                item: item,
                label: link.textContent || '',
                page: page
            });
        });

        if (extensionItems.length < 1) {
            menu.setAttribute('data-mds3-navigation-ready', '1');
            return;
        }

        var anchor = Array.prototype.slice.call(menu.children).filter(function (item) {
            var link = directChildLink(item);
            return link && pageFromUrl(link.href) === 'mds3-extensions';
        })[0];
        if (!anchor) {
            menu.setAttribute('data-mds3-navigation-ready', '1');
            return;
        }

        var toggle = document.createElement('button');
        var icon = document.createElement('span');
        var list = document.createElement('ul');
        var showLabel = navigation.showExtensionLinksLabel || 'Show extension links';
        var hideLabel = navigation.hideExtensionLinksLabel || 'Hide extension links';

        function setOpen(expanded, persist) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.setAttribute('aria-label', expanded ? hideLabel : showLabel);
            list.hidden = !expanded;
            anchor.classList.toggle('is-expanded', expanded);
            if (persist) {
                writeExtensionsOpenState(expanded);
            }
        }

        anchor.classList.add('mds3-sidebar-extensions-anchor');
        toggle.type = 'button';
        toggle.className = 'mds3-sidebar-extension-disclosure';
        toggle.setAttribute('aria-controls', 'mds3-sidebar-extension-submenu');
        icon.className = 'mds3-sidebar-extension-disclosure-icon';
        icon.setAttribute('aria-hidden', 'true');
        toggle.appendChild(icon);
        list.id = 'mds3-sidebar-extension-submenu';
        list.className = 'mds3-sidebar-extension-submenu';
        list.setAttribute('aria-label', 'Extensions');

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            setOpen(toggle.getAttribute('aria-expanded') !== 'true', true);
        });

        anchor.appendChild(toggle);
        anchor.appendChild(list);
        extensionItems.sort(function (a, b) {
            return String(a.label || '').localeCompare(String(b.label || ''), undefined, { sensitivity: 'base' });
        });
        extensionItems.forEach(function (entry) {
            list.appendChild(entry.item);
        });
        setOpen(readExtensionsOpenState(), false);
        menu.setAttribute('data-mds3-navigation-ready', '1');
    }

    document.addEventListener('DOMContentLoaded', bindSidebarNavigation);
}());
