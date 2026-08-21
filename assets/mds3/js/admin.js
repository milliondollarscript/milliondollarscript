(function () {
    'use strict';

    function jsonAttr(element, name) {
        try {
            return JSON.parse(element.getAttribute(name) || '[]');
        } catch (error) {
            return [];
        }
    }

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function numberOrNull(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        var parsed = parseInt(value, 10);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function fillForm(form, values) {
        Object.keys(values || {}).forEach(function (name) {
            var input = form.querySelector('[name="' + name + '"]');
            if (input) {
                input.value = values[name];
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    function adminConfig() {
        return (window.MillionDollarScript && window.MillionDollarScript.admin) || {
            ajaxUrl: window.ajaxurl || '',
            orderDetailNonce: '',
            i18n: {
                loading: 'Loading...',
                error: 'Unable to load order details.'
            }
        };
    }

    function setupContinuityKey() {
        var scope = window.location.pathname.match(/^\/scope:[^/]+/);
        return 'mds3-setup-continuity:' + (scope ? scope[0] : 'site');
    }

    function showAdminToast(message, type) {
        var config = adminConfig();
        var container;
        var toast;
        var text;
        var dismiss;

        message = String(message || '').trim();
        if (!message) {
            return;
        }

        container = document.querySelector('[data-mds3-toast-region]');
        if (!container) {
            container = document.createElement('div');
            container.className = 'mds3-toast-region';
            container.setAttribute('data-mds3-toast-region', '');
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-relevant', 'additions');
            document.body.appendChild(container);
        }

        toast = document.createElement('div');
        toast.className = 'mds3-toast ' + ('error' === type ? 'is-error' : 'is-success');
        toast.setAttribute('role', 'error' === type ? 'alert' : 'status');

        text = document.createElement('p');
        text.textContent = message;
        toast.appendChild(text);

        dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'mds3-toast-dismiss';
        dismiss.setAttribute('aria-label', config.i18n && config.i18n.dismissNotice ? config.i18n.dismissNotice : 'Dismiss notification');
        dismiss.textContent = '\u00d7';
        dismiss.addEventListener('click', function () {
            toast.remove();
            if (!container.children.length) {
                container.remove();
            }
        });
        toast.appendChild(dismiss);
        container.appendChild(toast);
    }

    function saveSetupContinuity(toast) {
        var form = document.querySelector('#mds3-setup-preferences');
        var provider = form ? form.querySelector('select[name="payment_provider"]') : null;
        var advanced = form ? form.querySelector('details.mds3-setup-advanced') : null;
        var state;

        if (!form) {
            return;
        }

        state = {
            savedAt: Date.now(),
            provider: provider ? provider.value : '',
            advancedOpen: advanced ? advanced.open : false,
            scrollY: Math.max(0, window.scrollY || 0),
            toast: toast || null
        };

        try {
            window.sessionStorage.setItem(setupContinuityKey(), JSON.stringify(state));
        } catch (error) {
            // Setup still works when browser storage is unavailable.
        }
    }

    function restoreSetupContinuity() {
        var form = document.querySelector('#mds3-setup-preferences');
        var raw = '';
        var state = null;
        var provider;
        var advanced;

        if (!form) {
            return;
        }

        try {
            raw = window.sessionStorage.getItem(setupContinuityKey()) || '';
            window.sessionStorage.removeItem(setupContinuityKey());
            state = raw ? JSON.parse(raw) : null;
        } catch (error) {
            state = null;
        }

        if (!state || !state.savedAt || Date.now() - state.savedAt > 300000) {
            return;
        }

        provider = form.querySelector('select[name="payment_provider"]');
        if (provider && state.provider && provider.querySelector('option[value="' + state.provider + '"]')) {
            provider.value = state.provider;
        }

        advanced = form.querySelector('details.mds3-setup-advanced');
        if (advanced) {
            advanced.open = Boolean(state.advancedOpen);
        }

        window.setTimeout(function () {
            var target = form.querySelector('[data-mds3-install-dependency]:not([disabled]), [data-mds3-setup-extension-action]:not([disabled])') ||
                form.querySelector('[data-mds3-woocommerce-setup] h3') || provider;
            window.scrollTo(0, Number(state.scrollY) || 0);
            if (target) {
                if (!target.matches('button, a, input, select, textarea, [tabindex]')) {
                    target.setAttribute('tabindex', '-1');
                }
                target.focus({ preventScroll: true });
            }
        }, 50);

        if (state.toast && state.toast.message) {
            showAdminToast(state.toast.message, state.toast.type);
        }
    }

    function convertSetupNoticesToToasts() {
        var setup = document.querySelector('#mds3-setup-preferences');
        if (!setup) {
            return;
        }

        document.querySelectorAll('.mds3-admin > .notice').forEach(function (notice) {
            var paragraph = notice.querySelector('p');
            var message = ((paragraph ? paragraph.textContent : notice.textContent) || '').replace(/\s+/g, ' ').trim();
            showAdminToast(message, notice.classList.contains('notice-error') ? 'error' : 'success');
            notice.remove();
        });
    }

    function bindExtensionCleanupPolicy() {
        document.querySelectorAll('[data-mds3-extension-cleanup]').forEach(function (policy) {
            policy.querySelectorAll('[data-mds3-cleanup-select]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var checked = button.getAttribute('data-mds3-cleanup-select') === 'all';
                    policy.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                        input.checked = checked;
                    });
                });
            });
        });
    }

    function capability(value) {
        return String(value || '').toLowerCase().trim().replace(/[^a-z0-9._:-]/g, '');
    }

    function bindSetupExtensionSelector() {
        document.querySelectorAll('[data-mds3-setup-selector]').forEach(function (selector) {
            var notice = selector.querySelector('[data-mds3-setup-notice]');
            var choices = [];

            selector.querySelectorAll('[data-mds3-setup-choice]').forEach(function (article) {
                var input = article.querySelector('[data-mds3-setup-checkbox]');
                var hidden = article.querySelector('[data-mds3-setup-hidden]');
                var slug = article.getAttribute('data-slug') || '';
                var provides = jsonAttr(article, 'data-provides').map(capability).filter(Boolean);
                var requires = jsonAttr(article, 'data-requires').map(capability).filter(Boolean);
                var conflicts = jsonAttr(article, 'data-conflicts').map(capability).filter(Boolean);

                if (slug) {
                    provides.push(capability(slug));
                }

                choices.push({
                    article: article,
                    input: input,
                    hidden: hidden,
                    slug: slug,
                    name: article.getAttribute('data-name') || slug,
                    provides: provides,
                    requires: requires,
                    conflicts: conflicts,
                    baseLocked: article.getAttribute('data-base-locked') === '1',
                    lockReason: article.querySelector('[data-mds3-lock-reason]')
                });
            });

            function providerFor(requirement) {
                var found = null;
                choices.some(function (choice) {
                    if (choice.provides.indexOf(requirement) !== -1) {
                        found = choice;
                        return true;
                    }
                    return false;
                });
                return found;
            }

            function selectedCapabilities(selected) {
                var caps = [];
                selected.forEach(function (choice) {
                    choice.provides.forEach(function (provided) {
                        if (caps.indexOf(provided) === -1) {
                            caps.push(provided);
                        }
                    });
                });
                return caps;
            }

            function update() {
                var config = adminConfig();
                var selected = choices.filter(function (choice) {
                    return choice.input && choice.input.checked;
                });
                var autoLocked = {};
                var requiredBy = {};
                var changed = true;
                var safety = 0;
                var warnings = [];

                while (changed && safety < 20) {
                    changed = false;
                    selected.forEach(function (choice) {
                        choice.requires.forEach(function (requirement) {
                            var provider = providerFor(requirement);
                            if (!provider || !provider.input) {
                                return;
                            }
                            if (!provider.input.checked) {
                                provider.input.checked = true;
                                selected.push(provider);
                                changed = true;
                            }
                            if (provider.slug !== choice.slug && !provider.baseLocked) {
                                autoLocked[provider.slug] = true;
                                requiredBy[provider.slug] = requiredBy[provider.slug] || [];
                                if (requiredBy[provider.slug].indexOf(choice.name) === -1) {
                                    requiredBy[provider.slug].push(choice.name);
                                }
                            }
                        });
                    });
                    safety++;
                }

                selected = choices.filter(function (choice) {
                    return choice.input && choice.input.checked;
                });
                var caps = selectedCapabilities(selected);
                selected.forEach(function (choice) {
                    choice.conflicts.forEach(function (conflict) {
                        if (caps.indexOf(conflict) !== -1) {
                            warnings.push(String((config.i18n && config.i18n.conflictsWith) || '%1$s conflicts with %2$s.')
                                .replace('%1$s', choice.name)
                                .replace('%2$s', conflict));
                        }
                    });
                });

                choices.forEach(function (choice) {
                    if (!choice.input) {
                        return;
                    }

                    var locked = choice.baseLocked || !!autoLocked[choice.slug];
                    choice.input.disabled = locked;
                    choice.article.classList.toggle('is-selected', choice.input.checked);
                    choice.article.classList.toggle('is-auto-selected', !!autoLocked[choice.slug]);
                    choice.article.classList.toggle('needs-attention', warnings.some(function (warning) {
                        return warning.indexOf(choice.name) === 0;
                    }));

                    if (choice.hidden) {
                        choice.hidden.disabled = !(locked && choice.input.checked);
                    }

                    if (choice.lockReason) {
                        if (autoLocked[choice.slug]) {
                            choice.lockReason.hidden = false;
                            choice.lockReason.textContent = String((config.i18n && config.i18n.requiredBy) || 'Required by %s').replace('%s', requiredBy[choice.slug].join(', '));
                        } else if (!choice.baseLocked) {
                            choice.lockReason.hidden = true;
                            choice.lockReason.textContent = '';
                        }
                    }
                });

                if (notice) {
                    if (warnings.length) {
                        notice.hidden = false;
                        notice.textContent = warnings.join(' ');
                    } else {
                        notice.hidden = true;
                        notice.textContent = '';
                    }
                }
            }

            choices.forEach(function (choice) {
                if (choice.input) {
                    choice.input.addEventListener('change', update);
                }
            });

            update();
        });
    }

    function bindSetupPaymentProvider() {
        document.querySelectorAll('#mds3-setup-preferences').forEach(function (form) {
            var provider = form.querySelector('select[name="payment_provider"]');
            var woocommerceSetup = form.querySelector('[data-mds3-woocommerce-setup]');
            if (!provider || !woocommerceSetup) {
                return;
            }

            function update() {
                woocommerceSetup.hidden = 'woocommerce' !== provider.value;
            }

            provider.addEventListener('change', update);
            update();
        });
    }

    function bindPluginDependencyInstaller() {
        document.querySelectorAll('[data-mds3-install-dependency]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                var config = adminConfig();
                var dependency = button.getAttribute('data-dependency') || '';
                var panel = button.closest('.mds3-setup-payment-card');
                var status = panel ? panel.querySelector('[data-mds3-dependency-status]') : null;
                var body = new window.URLSearchParams();
                var originalText = button.textContent;

                if (!window.fetch || !window.URLSearchParams || !config.ajaxUrl || !dependency) {
                    return;
                }

                event.preventDefault();
                button.disabled = true;
                button.textContent = config.i18n && config.i18n.installing ? config.i18n.installing : 'Installing...';
                if (status) {
                    status.className = 'mds3-setup-dependency-status';
                    status.textContent = config.i18n && config.i18n.installing ? config.i18n.installing : 'Installing...';
                }

                body.set('action', 'mds3_install_plugin_dependency');
                body.set('dependency', dependency);
                body.set('nonce', config.dependencyNonce || '');

                window.fetch(config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body.toString()
                }).then(function (response) {
                    return response.json();
                }).then(function (payload) {
                    if (!payload || !payload.success) {
                        throw new Error(payload && payload.data && payload.data.message ? payload.data.message : '');
                    }
                    if (status) {
                        status.className = 'mds3-setup-dependency-status is-success';
                        status.textContent = payload.data && payload.data.message ? payload.data.message : ((config.i18n && config.i18n.dependencyReady) || 'Plugin installed and activated. You can continue setup.');
                    }
                    saveSetupContinuity({
                        message: payload.data && payload.data.message ? payload.data.message : ((config.i18n && config.i18n.dependencyReady) || 'Plugin installed and activated. You can continue setup.'),
                        type: 'success'
                    });
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 700);
                }).catch(function (error) {
                    button.disabled = false;
                    button.textContent = originalText;
                    if (status) {
                        status.className = 'mds3-setup-dependency-status is-error';
                        status.textContent = error && error.message ? error.message : ((config.i18n && config.i18n.dependencyFailed) || 'The plugin could not be installed automatically.');
                    }
                    showAdminToast(error && error.message ? error.message : ((config.i18n && config.i18n.dependencyFailed) || 'The plugin could not be installed automatically.'), 'error');
                });
            });
        });
    }

    function bindSetupExtensionActions() {
        document.querySelectorAll('[data-mds3-setup-extension-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.getAttribute('data-action') || '';
                var nonce = button.getAttribute('data-nonce') || '';
                var pluginFile = button.getAttribute('data-plugin-file') || '';
                var slug = button.getAttribute('data-slug') || '';
                var form;

                if (!action || !nonce) return;

                form = document.createElement('form');
                form.method = 'post';
                form.action = (adminConfig().adminPostUrl || 'admin-post.php');
                [
                    ['action', action],
                    ['_wpnonce', nonce],
                    ['redirect_to', 'setup'],
                    ['plugin_file', pluginFile],
                    ['slug', slug],
                    ['activate', '1']
                ].forEach(function (entry) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = entry[0];
                    input.value = entry[1];
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                saveSetupContinuity();
                button.disabled = true;
                button.textContent = adminConfig().i18n && adminConfig().i18n.extensionActivating ? adminConfig().i18n.extensionActivating : 'Activating extension...';
                form.submit();
            });
        });
    }

    function bindDashboardExtensionPagination() {
        document.querySelectorAll('[data-mds3-dashboard-extension-pagination]').forEach(function (grid) {
            if (grid.getAttribute('data-mds3-pagination-ready') === '1') {
                return;
            }

            var cards = Array.prototype.slice.call(grid.querySelectorAll('[data-mds3-dashboard-extension-card]'));
            var controls = grid.parentNode ? grid.parentNode.querySelector('[data-mds3-dashboard-extension-controls]') : null;
            var previous = controls ? controls.querySelector('[data-mds3-extension-page-prev]') : null;
            var next = controls ? controls.querySelector('[data-mds3-extension-page-next]') : null;
            var status = controls ? controls.querySelector('[data-mds3-extension-page-status]') : null;
            var page = 1;

            if (!cards.length || !controls || !previous || !next || !status) {
                return;
            }

            function gridColumns() {
                var maxSize = numberOrNull(grid.getAttribute('data-page-size')) || 8;
                var minWidth = numberOrNull(grid.getAttribute('data-card-min-width')) || 260;
                var style = window.getComputedStyle ? window.getComputedStyle(grid) : null;
                var gap = style ? parseFloat(style.columnGap || style.gap || '0') : 0;
                var width = grid.clientWidth || grid.getBoundingClientRect().width || minWidth;

                if (!Number.isFinite(gap)) {
                    gap = 0;
                }

                return clamp(Math.floor((width + gap) / (minWidth + gap)), 1, maxSize);
            }

            function pageSize() {
                var maxSize = numberOrNull(grid.getAttribute('data-page-size')) || 8;
                var mobileRows = numberOrNull(grid.getAttribute('data-mobile-rows')) || 1;
                var desktopRows = numberOrNull(grid.getAttribute('data-desktop-rows')) || 2;
                var rows = window.matchMedia && window.matchMedia('(max-width: 782px)').matches ? mobileRows : desktopRows;
                var size = gridColumns() * Math.max(1, rows);

                return clamp(size, 1, maxSize);
            }

            function update(shouldScroll) {
                var size = pageSize();
                var pages = Math.max(1, Math.ceil(cards.length / size));
                var config = adminConfig();
                var format = config.i18n && config.i18n.extensionPageStatus ? config.i18n.extensionPageStatus : 'Showing %1$d-%2$d of %3$d · Page %4$d of %5$d';
                var start = (page - 1) * size;
                var end = Math.min(cards.length, page * size);

                page = clamp(page, 1, pages);
                start = (page - 1) * size;
                end = Math.min(cards.length, page * size);
                controls.hidden = pages <= 1;
                grid.setAttribute('data-current-page', String(page));
                grid.setAttribute('data-current-page-size', String(size));
                controls.setAttribute('data-current-page', String(page));
                cards.forEach(function (card, index) {
                    var hidden = index < start || index >= end;
                    card.hidden = hidden;
                    card.classList.toggle('is-page-hidden', hidden);
                    card.setAttribute('aria-hidden', hidden ? 'true' : 'false');
                });
                previous.disabled = page <= 1;
                next.disabled = page >= pages;
                previous.setAttribute('aria-disabled', previous.disabled ? 'true' : 'false');
                next.setAttribute('aria-disabled', next.disabled ? 'true' : 'false');
                status.textContent = format
                    .replace('%1$d', String(start + 1))
                    .replace('%2$d', String(end))
                    .replace('%3$d', String(cards.length))
                    .replace('%4$d', String(page))
                    .replace('%5$d', String(pages));

                if (shouldScroll && !controls.hidden && typeof grid.scrollIntoView === 'function') {
                    grid.scrollIntoView({ block: 'start', behavior: 'smooth' });
                }
            }

            previous.addEventListener('click', function (event) {
                event.preventDefault();
                page -= 1;
                update(true);
            });
            next.addEventListener('click', function (event) {
                event.preventDefault();
                page += 1;
                update(true);
            });
            window.addEventListener('resize', function () {
                update(false);
            });
            grid.setAttribute('data-mds3-pagination-ready', '1');
            update();
        });
    }

    function fallbackCopyText(text) {
        var field = document.createElement('textarea');
        var success = false;

        field.value = text;
        field.setAttribute('readonly', 'readonly');
        field.style.position = 'fixed';
        field.style.left = '-9999px';
        field.style.top = '0';
        document.body.appendChild(field);
        field.select();

        try {
            success = document.execCommand('copy');
        } catch (error) {
            success = false;
        }

        document.body.removeChild(field);
        return Promise.resolve(success);
    }

    function copyText(text) {
        if (window.navigator && window.navigator.clipboard && window.navigator.clipboard.writeText) {
            return window.navigator.clipboard.writeText(text).then(function () {
                return true;
            }).catch(function () {
                return fallbackCopyText(text);
            });
        }

        return fallbackCopyText(text);
    }

    function bindShortcodeCopy() {
        document.querySelectorAll('[data-mds3-copy-shortcode]').forEach(function (button) {
            if (button.getAttribute('data-mds3-copy-ready') === '1') {
                return;
            }

            var config = adminConfig();
            var status = button.querySelector('.mds3-shortcode-copy-status');
            var defaultText = status ? status.textContent : '';
            var copiedText = config.i18n && config.i18n.shortcodeCopied ? config.i18n.shortcodeCopied : 'Copied';
            var failedText = config.i18n && config.i18n.shortcodeCopyFailed ? config.i18n.shortcodeCopyFailed : 'Copy failed';
            var timeout = null;

            function setStatus(message, state) {
                button.classList.remove('is-copied', 'has-error');
                if (state) {
                    button.classList.add(state);
                }
                if (status) {
                    status.textContent = message;
                }

                if (timeout) {
                    window.clearTimeout(timeout);
                }
                timeout = window.setTimeout(function () {
                    button.classList.remove('is-copied', 'has-error');
                    if (status) {
                        status.textContent = defaultText;
                    }
                }, 1800);
            }

            button.addEventListener('click', function () {
                var shortcode = button.getAttribute('data-mds3-copy-shortcode') || '';
                if (!shortcode) {
                    setStatus(failedText, 'has-error');
                    return;
                }

                copyText(shortcode).then(function (success) {
                    setStatus(success ? copiedText : failedText, success ? 'is-copied' : 'has-error');
                }).catch(function () {
                    setStatus(failedText, 'has-error');
                });
            });

            button.setAttribute('data-mds3-copy-ready', '1');
        });
    }

    function docsCodeLanguage(code) {
        var match = String(code.className || '').match(/(?:^|\s)language-([a-z0-9_+-]+)/i);
        return match ? match[1].toLowerCase() : 'plaintext';
    }

    function docsCodeKeywords(language) {
        var groups = {
            bash: 'case do done elif else esac export fi for function if in local readonly then unset until while',
            css: 'important media supports var',
            javascript: 'async await break case catch class const continue debugger default delete do else export extends false finally for from function get if import in instanceof let new null of return set static super switch this throw true try typeof undefined var void while with yield',
            json: 'false null true',
            markdown: 'false null true',
            php: 'abstract and array as break callable case catch class clone const continue declare default die do echo else elseif empty enddeclare endfor endforeach endif endswitch endwhile eval exit extends final finally fn for foreach function global goto if implements include include_once instanceof insteadof interface isset list match namespace new or print private protected public readonly require require_once return static switch throw trait try unset use var while xor yield',
            sql: 'alter and as asc between by case create delete desc distinct drop else end exists from group having in index inner insert into is join left like limit not null on or order outer primary references right select set table then union unique update values view when where',
            yaml: 'false null true yes no'
        };
        var words = groups[language] || groups.javascript;
        return words.split(/\s+/).reduce(function (result, word) {
            result[word] = true;
            return result;
        }, {});
    }

    function highlightDocsCode(code, language) {
        if (!code || ['none', 'plain', 'plaintext', 'text', 'txt'].indexOf(language) !== -1) {
            return;
        }

        var source = code.textContent || '';
        var keywords = docsCodeKeywords(language);
        var commentPattern = ['bash', 'shell', 'yaml', 'yml'].indexOf(language) !== -1 ? '#[^\n]*' : '(?:\\/\\*[\\s\\S]*?\\*\\/|\\/\\/[^\\n]*)';
        var markupPattern = ['html', 'markup', 'xml'].indexOf(language) !== -1 ? '|<!--[\\s\\S]*?-->|<\\/?[A-Za-z][^>]*>' : '';
        var pattern = new RegExp('(' + commentPattern + markupPattern + '|`(?:\\\\.|[^`\\\\])*`|"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|\\b\\d+(?:\\.\\d+)?\\b|\\b[A-Za-z_$][\\w$]*\\b|[{}\\[\\]();,.<>:+\\-*\\/%=!?&|]+)', 'g');
        var fragment = document.createDocumentFragment();
        var cursor = 0;
        var match;

        while ((match = pattern.exec(source)) !== null) {
            if (match.index > cursor) {
                fragment.appendChild(document.createTextNode(source.slice(cursor, match.index)));
            }

            var token = match[0];
            var className = '';
            if (/^(?:\/\*|\/\/|#|<!--)/.test(token)) {
                className = 'comment';
            } else if (/^<\/?[A-Za-z]/.test(token)) {
                className = 'tag';
            } else if (/^["'`]/.test(token)) {
                className = 'string';
            } else if (/^\d/.test(token)) {
                className = 'number';
            } else if (keywords[token.toLowerCase()]) {
                className = 'keyword';
            } else if (/^[{}\[\]();,.<>:+\-*\/%=!?&|]+$/.test(token)) {
                className = 'punctuation';
            }

            if (className) {
                var span = document.createElement('span');
                span.className = 'mds3-code-token mds3-code-token--' + className;
                span.textContent = token;
                fragment.appendChild(span);
            } else {
                fragment.appendChild(document.createTextNode(token));
            }
            cursor = pattern.lastIndex;
        }

        if (cursor < source.length) {
            fragment.appendChild(document.createTextNode(source.slice(cursor)));
        }

        code.replaceChildren(fragment);
    }

    function bindDocsCodeBlocks() {
        document.querySelectorAll('.mds3-docs-content pre code').forEach(function (code) {
            var pre = code.parentElement;
            if (!pre || pre.getAttribute('data-mds3-code-ready') === '1') {
                return;
            }

            var source = code.textContent || '';
            var wrapper = document.createElement('div');
            var button = document.createElement('button');
            var label = document.createElement('span');
            var status = document.createElement('span');
            var config = adminConfig();
            var copiedText = config.i18n && config.i18n.docsCodeCopied ? config.i18n.docsCodeCopied : 'Copied';
            var failedText = config.i18n && config.i18n.docsCodeCopyFailed ? config.i18n.docsCodeCopyFailed : 'Copy failed';
            var timeout = null;

            wrapper.className = 'mds3-docs-code-block';
            pre.parentNode.insertBefore(wrapper, pre);
            wrapper.appendChild(pre);

            button.type = 'button';
            button.className = 'mds3-docs-code-copy';
            button.setAttribute('aria-label', 'Copy code to clipboard');
            label.className = 'mds3-docs-code-copy-label';
            label.textContent = 'Copy';
            status.className = 'mds3-docs-code-copy-status';
            status.setAttribute('aria-live', 'polite');
            button.appendChild(label);
            button.appendChild(status);
            wrapper.appendChild(button);

            button.addEventListener('click', function () {
                copyText(source).then(function (success) {
                    button.classList.toggle('is-copied', success);
                    button.classList.toggle('has-error', !success);
                    status.textContent = success ? copiedText : failedText;
                    if (timeout) {
                        window.clearTimeout(timeout);
                    }
                    timeout = window.setTimeout(function () {
                        button.classList.remove('is-copied', 'has-error');
                        status.textContent = '';
                    }, 1800);
                }).catch(function () {
                    button.classList.add('has-error');
                    status.textContent = failedText;
                });
            });

            highlightDocsCode(code, docsCodeLanguage(code));
            pre.setAttribute('data-mds3-code-ready', '1');
        });
    }

    function bindExtensionOnboardingCategories() {
        document.querySelectorAll('[data-mds3-extension-category]').forEach(function (category) {
            if (category.getAttribute('data-mds3-category-ready') === '1') {
                return;
            }

            var key = category.getAttribute('data-mds3-extension-category') || '';
            var toggle = category.querySelector('[data-mds3-extension-category-toggle="' + key + '"]');
            var items = Array.prototype.slice.call(category.querySelectorAll('[data-mds3-extension-category-item="' + key + '"]')).filter(function (item) {
                return !item.disabled;
            });

            if (!toggle) {
                return;
            }

            function update() {
                var checked = items.filter(function (item) {
                    return item.checked;
                }).length;

                toggle.disabled = items.length < 1;
                toggle.checked = items.length > 0 && checked === items.length;
                toggle.indeterminate = checked > 0 && checked < items.length;
            }

            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
            });

            toggle.addEventListener('change', function () {
                items.forEach(function (item) {
                    item.checked = toggle.checked;
                });
                update();
            });

            items.forEach(function (item) {
                item.addEventListener('change', update);
            });

            category.setAttribute('data-mds3-category-ready', '1');
            update();
        });
    }

    function removeOrderDetailRows() {
        document.querySelectorAll('.mds3-order-detail-row').forEach(function (row) {
            row.remove();
        });
        document.querySelectorAll('.mds3-order-inspect[aria-expanded="true"]').forEach(function (button) {
            button.setAttribute('aria-expanded', 'false');
        });
    }

    function insertOrderDetail(row, orderId, html) {
        var detailRow = document.createElement('tr');
        var cell = document.createElement('td');

        detailRow.className = 'mds3-order-detail-row';
        detailRow.id = 'mds3-order-detail-' + String(orderId);
        detailRow.setAttribute('data-order-id', String(orderId));
        cell.colSpan = Math.max(1, row.children.length);
        cell.innerHTML = html;
        detailRow.appendChild(cell);
        row.parentNode.insertBefore(detailRow, row.nextSibling);
        return detailRow;
    }

    function bindOrderInspect() {
        document.querySelectorAll('.mds3-order-inspect').forEach(function (button) {
            button.addEventListener('click', function (event) {
                var config = adminConfig();
                var row = button.closest('tr');
                var orderId = button.getAttribute('data-order-id') || '';
                var alreadyOpen = row && row.nextElementSibling && row.nextElementSibling.classList.contains('mds3-order-detail-row') && row.nextElementSibling.getAttribute('data-order-id') === orderId;
                var body = new window.URLSearchParams();

                if (!window.fetch || !window.URLSearchParams || !config.ajaxUrl) {
                    window.location.href = button.getAttribute('data-inspect-url') || window.location.href;
                    return;
                }

                event.preventDefault();

                if (alreadyOpen) {
                    removeOrderDetailRows();
                    return;
                }

                removeOrderDetailRows();
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');

                body.set('action', 'mds3_order_detail');
                body.set('nonce', config.orderDetailNonce || '');
                body.set('order_id', orderId);

                window.fetch(config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body.toString()
                }).then(function (response) {
                    return response.json();
                }).then(function (payload) {
                    if (!payload || !payload.success || !payload.data || !payload.data.html || !row) {
                        throw new Error('Order detail failed');
                    }

                    initRegionEditors(insertOrderDetail(row, orderId, payload.data.html));
                    button.setAttribute('aria-expanded', 'true');
                }).catch(function () {
                    if (row) {
                        insertOrderDetail(row, orderId, '<div class="notice notice-error inline"><p>' + ((config.i18n && config.i18n.error) || 'Unable to load order details.') + '</p></div>');
                        button.setAttribute('aria-expanded', 'true');
                    }
                }).finally(function () {
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                });
            });
        });
    }

    function bindFormSelectAll(form, selectAllSelector, inputName) {
        var formId = form.id || '';
        var selectAll = formId ? document.querySelector(selectAllSelector + '[data-form="' + formId + '"]') : form.querySelector(selectAllSelector);
        var checkboxes = formId ? Array.prototype.slice.call(document.querySelectorAll('input[name="' + inputName + '"][form="' + formId + '"]')) : Array.prototype.slice.call(form.querySelectorAll('input[name="' + inputName + '"]'));

        function selectedCount() {
            return checkboxes.filter(function (checkbox) {
                return checkbox.checked;
            }).length;
        }

        function updateSelectAllState() {
            if (!selectAll) {
                return;
            }

            selectAll.checked = checkboxes.length > 0 && selectedCount() === checkboxes.length;
            selectAll.indeterminate = selectedCount() > 0 && selectedCount() < checkboxes.length;
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = selectAll.checked;
                });
                updateSelectAllState();
            });
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', updateSelectAllState);
        });

        updateSelectAllState();

        return {
            selectedCount: selectedCount
        };
    }

    function bindOrderBulkActions() {
        document.querySelectorAll('.mds3-order-bulk-form').forEach(function (form) {
            var status = form.querySelector('[name="bulk_status"]');
            var selection = bindFormSelectAll(form, '.mds3-order-select-all', 'order_ids[]');

            form.addEventListener('submit', function (event) {
                var message = form.getAttribute('data-confirm') || '';
                if (!status || !status.value || selection.selectedCount() < 1) {
                    event.preventDefault();
                    return;
                }

                if (message && !window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });
    }

    function bindGridBulkActions() {
        document.querySelectorAll('.mds3-grid-bulk-actions').forEach(function (form) {
            bindFormSelectAll(form, '.mds3-grid-select-all', 'grid_ids[]');
        });
    }

    function bindMigrationJobs() {
        document.querySelectorAll('[data-mds3-migration-job]').forEach(function (panel) {
            var config = adminConfig();
            var runId = panel.getAttribute('data-run-id') || '';
            var stage = panel.querySelector('[data-mds3-migration-progress-stage]');
            var percent = panel.querySelector('[data-mds3-migration-progress-percent]');
            var bar = panel.querySelector('[data-mds3-migration-progress-bar]');
            var message = panel.querySelector('[data-mds3-migration-progress-message]');
            var isRunning = false;

            function completeReload() {
                var url;
                try {
                    url = new URL(window.location.href);
                    url.searchParams.delete('started');
                    url.searchParams.delete('continued');
                    url.searchParams.delete('retried');
                    url.searchParams.set('imported', '1');
                    window.location.href = url.toString();
                } catch (error) {
                    window.location.reload();
                }
            }

            function updateProgress(data) {
                var value = clamp(parseInt(data.percent || 0, 10), 0, 100);
                if (stage) {
                    stage.textContent = data.stage_label || '';
                }
                if (percent) {
                    percent.textContent = value + '%';
                }
                if (bar) {
                    bar.style.width = value + '%';
                }
                if (message) {
                    message.textContent = data.message || '';
                }
            }

            function failProgress(text) {
                if (message) {
                    message.textContent = text || ((config.i18n && config.i18n.migrationError) || 'Migration progress could not be updated.');
                }
                isRunning = false;
            }

            function step() {
                var body = new window.URLSearchParams();

                if (isRunning || !window.fetch || !window.URLSearchParams || !config.ajaxUrl || !runId) {
                    return;
                }

                isRunning = true;
                body.set('action', 'mds3_migration_step');
                body.set('run_id', runId);
                body.set('nonce', config.migrationNonce || '');

                window.fetch(config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body.toString()
                }).then(function (response) {
                    return response.json();
                }).then(function (payload) {
                    if (!payload || !payload.success || !payload.data) {
                        throw new Error(payload && payload.data && payload.data.message ? payload.data.message : '');
                    }

                    updateProgress(payload.data);
                    isRunning = false;

                    if (payload.data.completed) {
                        window.setTimeout(completeReload, 500);
                    } else if (payload.data.status === 'running') {
                        window.setTimeout(step, 250);
                    }
                }).catch(function (error) {
                    failProgress(error && error.message ? error.message : '');
                });
            }

            if (panel.getAttribute('data-auto-run') === '1') {
                step();
            }
        });
    }

    function RegionEditor(element) {
        this.element = element;
        this.canvas = element.querySelector('canvas');
        this.viewport = element.querySelector('.mds3-region-editor-canvas-wrap');
        this.ctx = this.canvas ? this.canvas.getContext('2d') : null;
        this.section = element.closest('.mds3-region-panel') || element.closest('.mds3-order-placement-map-shell') || element.closest('.mds3-card');
        this.mode = element.getAttribute('data-mode') || 'availability';
        this.rows = Math.max(1, parseInt(element.getAttribute('data-rows') || '1', 10));
        this.cols = Math.max(1, parseInt(element.getAttribute('data-cols') || '1', 10));
        this.blocks = jsonAttr(element, 'data-blocks');
        this.rules = jsonAttr(element, 'data-rules');
        this.regions = jsonAttr(element, 'data-regions');
        this.readOnly = element.getAttribute('data-readonly') === '1';
        this.statusText = element.getAttribute('data-status') || '';
        this.selections = this.normalizeSelections(jsonAttr(element, 'data-selections'));
        this.moveShape = this.normalizeMoveShape(jsonAttr(element, 'data-move-shape'));
        this.moveRowSpan = Math.max(1, parseInt(element.getAttribute('data-move-row-span') || '1', 10));
        this.moveColSpan = Math.max(1, parseInt(element.getAttribute('data-move-col-span') || '1', 10));
        this.blockMap = this.indexBlocks(this.blocks);
        this.status = element.querySelector('.mds3-region-editor-status');
        this.zoomButtons = element.querySelectorAll('[data-mds3-region-zoom]');
        this.zoomLabel = element.querySelector('[data-mds3-region-zoom-label]');
        this.form = this.section ? this.section.querySelector('form') : null;
        this.selection = null;
        this.dragStart = null;
        this.zoom = 1;
        this.fitWidth = 0;
        this.fitHeight = 0;
        this.bind();
        this.applyZoom();
        if (this.readOnly) {
            this.syncSelectionFromData();
        } else {
            this.syncSelectionFromForm();
        }
        this.draw();
    }

    RegionEditor.prototype.bind = function () {
        var self = this;
        if (!this.canvas || !this.ctx) {
            return;
        }

        window.addEventListener('resize', function () {
            self.fitWidth = 0;
            self.fitHeight = 0;
            self.applyZoom();
        });

        this.zoomButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.getAttribute('data-mds3-region-zoom');
                if (action === 'in') {
                    self.setZoom(self.zoom + 0.5);
                } else if (action === 'out') {
                    self.setZoom(self.zoom - 0.5);
                } else {
                    self.setZoom(1);
                }
            });
        });

        if (this.readOnly) {
            return;
        }

        if (this.form) {
            ['row_from', 'row_to', 'col_from', 'col_to'].forEach(function (name) {
                var input = self.form.querySelector('[name="' + name + '"]');
                if (input) {
                    input.addEventListener('input', function () {
                        self.syncSelectionFromForm();
                        self.resetMovePreview();
                        self.draw();
                    });
                }
            });

            this.form.addEventListener('reset', function () {
                window.setTimeout(function () {
                    self.syncSelectionFromForm();
                    self.resetMovePreview();
                    self.draw();
                }, 0);
            });

            if (this.mode === 'move') {
                this.bindMovePreview();
            }
        }

        if (this.section) {
            this.section.querySelectorAll('.mds3-region-load').forEach(function (button) {
                button.addEventListener('click', function () {
                    self.loadRegion(button);
                });
            });
        }

        this.canvas.addEventListener('pointerdown', function (event) {
            var coord = self.coord(event);
            if (self.mode === 'move') {
                self.dragStart = null;
                self.selection = self.moveSelectionAt(coord);
                self.applySelection();
                self.draw();
                return;
            }

            var existingRegion = self.mode === 'availability' ? self.unavailableSelectionAt(coord) : null;
            if (existingRegion) {
                self.dragStart = null;
                self.selection = existingRegion;
                self.applySelection('available');
                self.draw();
                return;
            }

            self.dragStart = coord;
            self.selection = {
                row_from: self.dragStart.row,
                row_to: self.dragStart.row,
                col_from: self.dragStart.col,
                col_to: self.dragStart.col
            };
            self.canvas.setPointerCapture(event.pointerId);
            self.applySelection('unavailable');
            self.draw();
        });

        this.canvas.addEventListener('pointermove', function (event) {
            if (!self.dragStart) {
                return;
            }
            var coord = self.coord(event);
            self.selection = {
                row_from: Math.min(self.dragStart.row, coord.row),
                row_to: Math.max(self.dragStart.row, coord.row),
                col_from: Math.min(self.dragStart.col, coord.col),
                col_to: Math.max(self.dragStart.col, coord.col)
            };
            self.applySelection();
            self.draw();
        });

        this.canvas.addEventListener('pointerup', function (event) {
            self.dragStart = null;
            try {
                self.canvas.releasePointerCapture(event.pointerId);
            } catch (error) {}
        });
    };

    RegionEditor.prototype.indexBlocks = function (blocks) {
        var map = {};

        (blocks || []).forEach(function (block) {
            map[this.blockKey(block.row, block.col)] = block;
        }, this);

        return map;
    };

    RegionEditor.prototype.blockKey = function (row, col) {
        return String(row) + ':' + String(col);
    };

    RegionEditor.prototype.blockAt = function (row, col) {
        return this.blockMap[this.blockKey(row, col)] || null;
    };

    RegionEditor.prototype.unavailableSelectionAt = function (coord) {
        var region = this.regionAt(coord);
        var block = this.blockAt(coord.row, coord.col);
        var regionId = '';
        var bounds = null;

        if (region) {
            return this.normalizeSelection(region);
        }

        if (!block || block.status !== 'unavailable') {
            return null;
        }

        regionId = block.region_id || block.availability_region_id || '';
        if (regionId) {
            this.blocks.forEach(function (candidate) {
                if (candidate.status !== 'unavailable' || (candidate.region_id || candidate.availability_region_id || '') !== regionId) {
                    return;
                }
                bounds = this.expandBounds(bounds, candidate.row, candidate.col);
            }, this);
        } else {
            bounds = this.floodUnavailableBounds(coord);
        }

        return bounds ? this.normalizeSelection(bounds) : null;
    };

    RegionEditor.prototype.regionAt = function (coord) {
        for (var i = 0; i < this.regions.length; i++) {
            var region = this.regions[i];
            if (
                coord.row >= Number(region.row_from || 0) &&
                coord.row <= Number(region.row_to || 0) &&
                coord.col >= Number(region.col_from || 0) &&
                coord.col <= Number(region.col_to || 0)
            ) {
                return region;
            }
        }

        return null;
    };

    RegionEditor.prototype.expandBounds = function (bounds, row, col) {
        row = clamp(numberOrNull(row) || 0, 0, this.rows - 1);
        col = clamp(numberOrNull(col) || 0, 0, this.cols - 1);

        if (!bounds) {
            return {
                row_from: row,
                row_to: row,
                col_from: col,
                col_to: col
            };
        }

        bounds.row_from = Math.min(bounds.row_from, row);
        bounds.row_to = Math.max(bounds.row_to, row);
        bounds.col_from = Math.min(bounds.col_from, col);
        bounds.col_to = Math.max(bounds.col_to, col);
        return bounds;
    };

    RegionEditor.prototype.floodUnavailableBounds = function (coord) {
        var stack = [coord];
        var seen = {};
        var bounds = null;
        var directions = [
            { row: -1, col: 0 },
            { row: 1, col: 0 },
            { row: 0, col: -1 },
            { row: 0, col: 1 }
        ];

        while (stack.length) {
            var current = stack.pop();
            var key = this.blockKey(current.row, current.col);
            var block = this.blockAt(current.row, current.col);

            if (seen[key] || !block || block.status !== 'unavailable') {
                continue;
            }

            seen[key] = true;
            bounds = this.expandBounds(bounds, current.row, current.col);
            directions.forEach(function (direction) {
                var next = {
                    row: current.row + direction.row,
                    col: current.col + direction.col
                };

                if (next.row >= 0 && next.row < this.rows && next.col >= 0 && next.col < this.cols && !seen[this.blockKey(next.row, next.col)]) {
                    stack.push(next);
                }
            }, this);
        }

        return bounds;
    };

    RegionEditor.prototype.setZoom = function (zoom) {
        this.zoom = Math.round(clamp(Number(zoom) || 1, 1, 4) * 100) / 100;
        this.applyZoom();
    };

    RegionEditor.prototype.applyZoom = function () {
        var rect;

        if (!this.canvas || !this.ctx) {
            return;
        }

        if (!this.fitWidth || !this.fitHeight || this.zoom === 1) {
            this.canvas.style.width = '';
            this.canvas.style.height = '';
            rect = this.canvas.getBoundingClientRect();
            if (rect.width > 0) {
                this.fitWidth = rect.width;
            }
            if (rect.height > 0) {
                this.fitHeight = rect.height;
            }
        }

        if (this.zoom > 1 && this.fitWidth && this.fitHeight) {
            this.canvas.style.width = Math.round(this.fitWidth * this.zoom) + 'px';
            this.canvas.style.height = Math.round(this.fitHeight * this.zoom) + 'px';
        } else {
            this.canvas.style.width = '';
            this.canvas.style.height = '';
        }

        if (this.zoomLabel) {
            this.zoomLabel.textContent = Math.round(this.zoom * 100) + '%';
        }

        this.resize();
        this.draw();
    };

    RegionEditor.prototype.resize = function () {
        var rect = this.canvas.getBoundingClientRect();
        var ratio = window.devicePixelRatio || 1;
        this.canvas.width = Math.max(360, Math.floor(rect.width * ratio));
        this.canvas.height = Math.max(220, Math.floor(rect.height * ratio));
        this.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    };

    RegionEditor.prototype.metrics = function () {
        var rect = this.canvas.getBoundingClientRect();
        var scale = Math.min(rect.width / this.cols, rect.height / this.rows);
        scale = Math.max(0.02, scale);
        var width = this.cols * scale;
        var height = this.rows * scale;

        return {
            width: rect.width,
            height: rect.height,
            scale: scale,
            offsetX: (rect.width - width) / 2,
            offsetY: (rect.height - height) / 2
        };
    };

    RegionEditor.prototype.coord = function (event) {
        var rect = this.canvas.getBoundingClientRect();
        var metrics = this.metrics();
        var col = Math.floor((event.clientX - rect.left - metrics.offsetX) / metrics.scale);
        var row = Math.floor((event.clientY - rect.top - metrics.offsetY) / metrics.scale);

        return {
            row: clamp(row, 0, this.rows - 1),
            col: clamp(col, 0, this.cols - 1)
        };
    };

    RegionEditor.prototype.draw = function () {
        if (!this.ctx) {
            return;
        }

        var ctx = this.ctx;
        var metrics = this.metrics();
        var scale = metrics.scale;
        var width = this.cols * scale;
        var height = this.rows * scale;

        ctx.clearRect(0, 0, metrics.width, metrics.height);
        ctx.fillStyle = '#f8fafc';
        ctx.fillRect(0, 0, metrics.width, metrics.height);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(metrics.offsetX, metrics.offsetY, width, height);
        ctx.strokeStyle = '#94a3b8';
        ctx.strokeRect(metrics.offsetX, metrics.offsetY, width, height);

        this.drawGrid(ctx, metrics);
        this.drawRules(ctx, metrics);
        this.drawRegions(ctx, metrics);
        this.drawBlocks(ctx, metrics);
        this.drawSelections(ctx, metrics);
        this.drawSelection(ctx, metrics);
    };

    RegionEditor.prototype.drawGrid = function (ctx, metrics) {
        if (this.cols > 160 || this.rows > 160 || metrics.scale < 4) {
            return;
        }

        ctx.strokeStyle = '#e5e7eb';
        ctx.lineWidth = 1;
        for (var col = 1; col < this.cols; col++) {
            ctx.beginPath();
            ctx.moveTo(metrics.offsetX + col * metrics.scale, metrics.offsetY);
            ctx.lineTo(metrics.offsetX + col * metrics.scale, metrics.offsetY + this.rows * metrics.scale);
            ctx.stroke();
        }
        for (var row = 1; row < this.rows; row++) {
            ctx.beginPath();
            ctx.moveTo(metrics.offsetX, metrics.offsetY + row * metrics.scale);
            ctx.lineTo(metrics.offsetX + this.cols * metrics.scale, metrics.offsetY + row * metrics.scale);
            ctx.stroke();
        }
    };

    RegionEditor.prototype.drawRules = function (ctx, metrics) {
        if (this.mode !== 'price') {
            return;
        }

        this.rules.forEach(function (rule) {
            if (rule.status !== 'active' || rule.row_from === null || rule.col_from === null) {
                return;
            }

            var rowFrom = clamp(parseInt(rule.row_from, 10), 0, this.rows - 1);
            var rowTo = clamp(parseInt(rule.row_to === null ? rowFrom : rule.row_to, 10), 0, this.rows - 1);
            var colFrom = clamp(parseInt(rule.col_from, 10), 0, this.cols - 1);
            var colTo = clamp(parseInt(rule.col_to === null ? colFrom : rule.col_to, 10), 0, this.cols - 1);
            ctx.fillStyle = rule.color || 'rgba(37, 99, 235, 0.28)';
            ctx.globalAlpha = rule.color ? 0.32 : 1;
            ctx.fillRect(
                metrics.offsetX + colFrom * metrics.scale,
                metrics.offsetY + rowFrom * metrics.scale,
                Math.max(metrics.scale, (colTo - colFrom + 1) * metrics.scale),
                Math.max(metrics.scale, (rowTo - rowFrom + 1) * metrics.scale)
            );
            ctx.globalAlpha = 1;
        }, this);
    };

    RegionEditor.prototype.drawBlocks = function (ctx, metrics) {
        if (this.mode !== 'availability' && this.mode !== 'inspect' && this.mode !== 'move') {
            return;
        }

        this.blocks.forEach(function (block) {
            if (block.status === 'available') {
                return;
            }

            if (block.status === 'sold') {
                ctx.fillStyle = 'rgba(37, 99, 235, 0.45)';
            } else if (block.status === 'reserved') {
                ctx.fillStyle = 'rgba(217, 119, 6, 0.45)';
            } else {
                ctx.fillStyle = 'rgba(75, 85, 99, 0.58)';
            }
            ctx.fillRect(
                metrics.offsetX + block.col * metrics.scale,
                metrics.offsetY + block.row * metrics.scale,
                Math.max(1, metrics.scale),
                Math.max(1, metrics.scale)
            );
        });
    };

    RegionEditor.prototype.drawRegions = function (ctx, metrics) {
        if (this.mode !== 'availability' && this.mode !== 'inspect' && this.mode !== 'move') {
            return;
        }

        this.regions.forEach(function (region) {
            var rowFrom = clamp(parseInt(region.row_from || 0, 10), 0, this.rows - 1);
            var rowTo = clamp(parseInt(region.row_to || rowFrom, 10), 0, this.rows - 1);
            var colFrom = clamp(parseInt(region.col_from || 0, 10), 0, this.cols - 1);
            var colTo = clamp(parseInt(region.col_to || colFrom, 10), 0, this.cols - 1);

            ctx.fillStyle = 'rgba(75, 85, 99, 0.36)';
            ctx.fillRect(
                metrics.offsetX + colFrom * metrics.scale,
                metrics.offsetY + rowFrom * metrics.scale,
                Math.max(1, (colTo - colFrom + 1) * metrics.scale),
                Math.max(1, (rowTo - rowFrom + 1) * metrics.scale)
            );
        }, this);
    };

    RegionEditor.prototype.drawSelection = function (ctx, metrics) {
        if (!this.selection) {
            return;
        }

        if (this.mode === 'move') {
            this.drawMoveShape(ctx, metrics, this.selection);
            return;
        }

        this.drawSelectionRect(ctx, metrics, this.selection);
    };

    RegionEditor.prototype.drawMoveShape = function (ctx, metrics, selection) {
        var shape = this.moveShape.length ? this.moveShape : [{ row: 0, col: 0 }];
        ctx.fillStyle = selection.fill || 'rgba(5, 150, 105, 0.2)';
        ctx.strokeStyle = selection.color || '#059669';
        ctx.lineWidth = 2;

        shape.forEach(function (offset) {
            var row = selection.row_from + offset.row;
            var col = selection.col_from + offset.col;
            ctx.fillRect(
                metrics.offsetX + col * metrics.scale,
                metrics.offsetY + row * metrics.scale,
                Math.max(1, metrics.scale),
                Math.max(1, metrics.scale)
            );
            ctx.strokeRect(
                metrics.offsetX + col * metrics.scale,
                metrics.offsetY + row * metrics.scale,
                Math.max(1, metrics.scale),
                Math.max(1, metrics.scale)
            );
        });
    };

    RegionEditor.prototype.drawSelections = function (ctx, metrics) {
        if (!this.selections || !this.selections.length) {
            return;
        }

        this.selections.forEach(function (selection) {
            this.drawSelectionRect(ctx, metrics, selection);
        }, this);
    };

    RegionEditor.prototype.drawSelectionRect = function (ctx, metrics, selection) {
        var s = selection;
        ctx.fillStyle = s.fill || 'rgba(124, 58, 237, 0.22)';
        ctx.strokeStyle = s.color || '#7c3aed';
        ctx.lineWidth = 2;
        ctx.fillRect(
            metrics.offsetX + s.col_from * metrics.scale,
            metrics.offsetY + s.row_from * metrics.scale,
            Math.max(1, (s.col_to - s.col_from + 1) * metrics.scale),
            Math.max(1, (s.row_to - s.row_from + 1) * metrics.scale)
        );
        ctx.strokeRect(
            metrics.offsetX + s.col_from * metrics.scale,
            metrics.offsetY + s.row_from * metrics.scale,
            Math.max(1, (s.col_to - s.col_from + 1) * metrics.scale),
            Math.max(1, (s.row_to - s.row_from + 1) * metrics.scale)
        );
    };

    RegionEditor.prototype.applySelection = function (statusOverride) {
        if (!this.form || !this.selection) {
            return;
        }

        this.resetMovePreview();

        ['row_from', 'row_to', 'col_from', 'col_to'].forEach(function (name) {
            var input = this.form.querySelector('[name="' + name + '"]');
            if (input) {
                input.value = this.selection[name];
            }
        }, this);

        if (statusOverride) {
            var status = this.form.querySelector('[name="region_status"]');
            if (status) {
                status.value = statusOverride;
                status.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        if (this.status) {
            this.status.textContent = this.selectionLabel(statusOverride);
        }
    };

    RegionEditor.prototype.syncSelectionFromForm = function () {
        if (!this.form) {
            return;
        }

        var rowFrom = numberOrNull(this.form.querySelector('[name="row_from"]') ? this.form.querySelector('[name="row_from"]').value : null);
        var rowTo = numberOrNull(this.form.querySelector('[name="row_to"]') ? this.form.querySelector('[name="row_to"]').value : null);
        var colFrom = numberOrNull(this.form.querySelector('[name="col_from"]') ? this.form.querySelector('[name="col_from"]').value : null);
        var colTo = numberOrNull(this.form.querySelector('[name="col_to"]') ? this.form.querySelector('[name="col_to"]').value : null);

        if (rowFrom === null || colFrom === null) {
            this.selection = null;
            if (this.status) {
                this.status.textContent = this.mode === 'move' ? this.statusText : '';
            }
            return;
        }

        if (this.mode === 'move') {
            rowFrom = clamp(rowFrom, 0, Math.max(0, this.rows - this.moveRowSpan));
            colFrom = clamp(colFrom, 0, Math.max(0, this.cols - this.moveColSpan));
            rowTo = rowFrom + this.moveRowSpan - 1;
            colTo = colFrom + this.moveColSpan - 1;
            this.form.querySelector('[name="row_from"]').value = rowFrom;
            this.form.querySelector('[name="col_from"]').value = colFrom;
            if (this.form.querySelector('[name="row_to"]')) {
                this.form.querySelector('[name="row_to"]').value = rowTo;
            }
            if (this.form.querySelector('[name="col_to"]')) {
                this.form.querySelector('[name="col_to"]').value = colTo;
            }
        } else {
            rowTo = rowTo === null ? rowFrom : rowTo;
            colTo = colTo === null ? colFrom : colTo;
        }
        this.selection = this.normalizeSelection({
            row_from: rowFrom,
            row_to: rowTo,
            col_from: colFrom,
            col_to: colTo,
            color: this.mode === 'move' ? '#059669' : '',
            fill: this.mode === 'move' ? 'rgba(5, 150, 105, 0.2)' : ''
        });

        if (this.status) {
            this.status.textContent = this.selectionLabel();
        }
    };

    RegionEditor.prototype.normalizeSelection = function (selection) {
        var rowFrom = clamp(numberOrNull(selection.row_from) || 0, 0, this.rows - 1);
        var rowTo = clamp(numberOrNull(selection.row_to) === null ? rowFrom : numberOrNull(selection.row_to), 0, this.rows - 1);
        var colFrom = clamp(numberOrNull(selection.col_from) || 0, 0, this.cols - 1);
        var colTo = clamp(numberOrNull(selection.col_to) === null ? colFrom : numberOrNull(selection.col_to), 0, this.cols - 1);

        return {
            row_from: Math.min(rowFrom, rowTo),
            row_to: Math.max(rowFrom, rowTo),
            col_from: Math.min(colFrom, colTo),
            col_to: Math.max(colFrom, colTo),
            color: selection.color || '',
            fill: selection.fill || '',
            label: selection.label || ''
        };
    };

    RegionEditor.prototype.normalizeMoveShape = function (shape) {
        var unique = {};

        if (!Array.isArray(shape)) {
            return [];
        }

        shape.forEach(function (offset) {
            var row = Math.max(0, numberOrNull(offset && offset.row) || 0);
            var col = Math.max(0, numberOrNull(offset && offset.col) || 0);
            unique[row + ':' + col] = { row: row, col: col };
        });

        return Object.keys(unique).map(function (key) {
            return unique[key];
        });
    };

    RegionEditor.prototype.moveSelectionAt = function (coord) {
        var rowFrom = clamp(coord.row, 0, Math.max(0, this.rows - this.moveRowSpan));
        var colFrom = clamp(coord.col, 0, Math.max(0, this.cols - this.moveColSpan));

        return this.normalizeSelection({
            row_from: rowFrom,
            row_to: rowFrom + this.moveRowSpan - 1,
            col_from: colFrom,
            col_to: colFrom + this.moveColSpan - 1,
            color: '#059669',
            fill: 'rgba(5, 150, 105, 0.2)'
        });
    };

    RegionEditor.prototype.normalizeSelections = function (selections) {
        if (!Array.isArray(selections)) {
            return [];
        }

        return selections.map(function (selection) {
            return this.normalizeSelection(selection || {});
        }, this);
    };

    RegionEditor.prototype.syncSelectionFromData = function () {
        if (!this.status) {
            return;
        }

        if (this.statusText) {
            this.status.textContent = this.statusText;
            return;
        }

        if (this.selections.length === 1) {
            this.status.textContent = this.selections[0].label || this.selectionLabelFor(this.selections[0]);
        } else if (this.selections.length > 1) {
            this.status.textContent = this.selections.length + ' placements highlighted.';
        }
    };

    RegionEditor.prototype.selectionLabel = function (statusOverride) {
        return this.selectionLabelFor(this.selection, statusOverride);
    };

    RegionEditor.prototype.selectionLabelFor = function (selection, statusOverride) {
        if (!selection) {
            return '';
        }

        var total = this.mode === 'move' && this.moveShape.length
            ? this.moveShape.length
            : (selection.row_to - selection.row_from + 1) * (selection.col_to - selection.col_from + 1);
        var label = (this.mode === 'move' ? 'Proposed placement: ' : '') + 'Rows ' + selection.row_from + '-' + selection.row_to + ', columns ' + selection.col_from + '-' + selection.col_to + ', ' + total + ' blocks';
        var status = statusOverride || (this.form && this.form.querySelector('[name="region_status"]') ? this.form.querySelector('[name="region_status"]').value : '');
        if (status === 'available') {
            label += ' - apply to mark available';
        } else if (status === 'unavailable') {
            label += ' - apply to mark unavailable';
        }

        return label;
    };

    RegionEditor.prototype.resetMovePreview = function () {
        if (this.mode !== 'move' || !this.form) {
            return;
        }

        var preview = this.form.querySelector('[data-mds3-order-move-preview-status]');
        var submit = this.form.querySelector('[data-mds3-order-move-submit]');
        if (preview) {
            preview.classList.remove('is-error', 'is-success', 'is-loading');
            preview.textContent = '';
        }
        if (submit) {
            submit.disabled = true;
        }
    };

    RegionEditor.prototype.bindMovePreview = function () {
        var self = this;
        var button = this.form.querySelector('[data-mds3-order-move-preview]');
        var submit = this.form.querySelector('[data-mds3-order-move-submit]');
        var preview = this.form.querySelector('[data-mds3-order-move-preview-status]');

        if (!button || !submit || !preview) {
            return;
        }

        button.addEventListener('click', function () {
            var config = adminConfig();
            var rowInput = self.form.querySelector('[name="row_from"]');
            var colInput = self.form.querySelector('[name="col_from"]');
            var row = numberOrNull(rowInput ? rowInput.value : null);
            var col = numberOrNull(colInput ? colInput.value : null);
            var body = new window.URLSearchParams();
            var originalText = button.textContent;

            self.resetMovePreview();
            if (row === null || col === null || !window.fetch || !window.URLSearchParams || !config.ajaxUrl) {
                preview.classList.add('is-error');
                preview.textContent = config.i18n && config.i18n.moveChooseTarget ? config.i18n.moveChooseTarget : 'Choose a target row and column first.';
                return;
            }

            button.disabled = true;
            button.textContent = config.i18n && config.i18n.movePreviewing ? config.i18n.movePreviewing : 'Checking...';
            preview.classList.add('is-loading');
            preview.textContent = config.i18n && config.i18n.movePreviewing ? config.i18n.movePreviewing : 'Checking...';

            body.set('action', 'mds3_preview_order_move');
            body.set('nonce', self.form.querySelector('[name="preview_nonce"]').value || '');
            body.set('order_id', self.form.querySelector('[name="order_id"]').value || '');
            body.set('grid_id', self.form.querySelector('[name="grid_id"]').value || '');
            body.set('row_from', String(row));
            body.set('col_from', String(col));

            window.fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            }).then(function (response) {
                return response.json();
            }).then(function (payload) {
                if (!payload || !payload.success || !payload.data) {
                    throw new Error(payload && payload.data && payload.data.message ? payload.data.message : '');
                }

                preview.textContent = '';
                [payload.data.message, payload.data.price_message, payload.data.state_message].filter(Boolean).forEach(function (message) {
                    var line = document.createElement('p');
                    line.textContent = message;
                    preview.appendChild(line);
                });
                preview.classList.remove('is-loading', 'is-error');
                preview.classList.add('is-success');
                submit.disabled = false;
            }).catch(function (error) {
                preview.classList.remove('is-loading', 'is-success');
                preview.classList.add('is-error');
                preview.textContent = error && error.message
                    ? error.message
                    : ((config.i18n && config.i18n.movePreviewFailed) || 'The placement could not be previewed.');
                submit.disabled = true;
            }).finally(function () {
                button.disabled = false;
                button.textContent = originalText;
            });
        });
    };

    function initRegionEditors(scope) {
        (scope || document).querySelectorAll('.mds3-region-editor').forEach(function (element) {
            if (element.getAttribute('data-mds3-region-ready') === '1') {
                return;
            }

            element.setAttribute('data-mds3-region-ready', '1');
            new RegionEditor(element);
        });
    }

    RegionEditor.prototype.loadRegion = function (button) {
        if (!this.form) {
            return;
        }

        var values = {
            id: button.getAttribute('data-id') || '',
            row_from: button.getAttribute('data-row-from') || '',
            row_to: button.getAttribute('data-row-to') || '',
            col_from: button.getAttribute('data-col-from') || '',
            col_to: button.getAttribute('data-col-to') || '',
            block_id_from: button.getAttribute('data-block-id-from') || '',
            block_id_to: button.getAttribute('data-block-id-to') || '',
            price: button.getAttribute('data-price') || '',
            currency: button.getAttribute('data-currency') || '',
            color: button.getAttribute('data-color') || '#2563eb',
            status: button.getAttribute('data-status') || 'active',
            region_status: button.getAttribute('data-region-status') || button.getAttribute('data-status') || '',
            note: button.getAttribute('data-note') || ''
        };

        fillForm(this.form, values);
        this.syncSelectionFromForm();
        this.draw();
    };

    function bindColorPickers() {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.wpColorPicker) {
            return;
        }

        window.jQuery('.mds3-color-picker').wpColorPicker();
    }

    function bindHelpTooltips() {
        var activeTrigger = null;
        var popover = document.createElement('div');

        popover.className = 'mds3-help-popover';
        popover.id = 'mds3-help-popover';
        popover.setAttribute('role', 'tooltip');
        document.body.appendChild(popover);
        document.documentElement.classList.add('mds3-has-js-help');

        function hide() {
            activeTrigger = null;
            popover.classList.remove('is-visible');
            popover.textContent = '';
            document.querySelectorAll('.mds3-help[aria-expanded="true"]').forEach(function (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
                trigger.removeAttribute('data-click-open');
            });
        }

        function place(trigger) {
            var rect = trigger.getBoundingClientRect();
            var viewportWidth = window.innerWidth || document.documentElement.clientWidth;
            var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
            var width = Math.min(320, Math.max(180, viewportWidth - 32));
            var top;
            var left;

            popover.style.maxWidth = width + 'px';
            popover.style.left = '0';
            popover.style.top = '0';
            popover.classList.add('is-visible');

            top = rect.bottom + 8;
            if (top + popover.offsetHeight > viewportHeight - 12) {
                top = rect.top - popover.offsetHeight - 8;
            }
            top = clamp(top, 8, Math.max(8, viewportHeight - popover.offsetHeight - 8));

            left = rect.left + (rect.width / 2) - (popover.offsetWidth / 2);
            left = clamp(left, 8, Math.max(8, viewportWidth - popover.offsetWidth - 8));

            popover.style.left = left + 'px';
            popover.style.top = top + 'px';
        }

        function show(trigger) {
            var help = trigger.getAttribute('data-help') || trigger.getAttribute('aria-label') || '';
            if (!help) {
                return;
            }

            activeTrigger = trigger;
            popover.textContent = help;
            trigger.setAttribute('aria-expanded', 'true');
            trigger.setAttribute('aria-describedby', popover.id);
            place(trigger);
        }

        document.querySelectorAll('.mds3-help').forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', 'false');

            trigger.addEventListener('mouseenter', function () {
                show(trigger);
            });

            trigger.addEventListener('mouseleave', function () {
                if ('true' !== trigger.getAttribute('data-click-open')) {
                    hide();
                }
            });
            trigger.addEventListener('focus', function () {
                show(trigger);
            });
            trigger.addEventListener('blur', function () {
                if ('true' !== trigger.getAttribute('data-click-open')) {
                    hide();
                }
            });
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (activeTrigger === trigger && popover.classList.contains('is-visible') && 'true' === trigger.getAttribute('data-click-open')) {
                    hide();
                } else {
                    trigger.setAttribute('data-click-open', 'true');
                    show(trigger);
                }
            });
        });

        document.addEventListener('click', function (event) {
            if (activeTrigger && !popover.contains(event.target) && !activeTrigger.contains(event.target)) {
                hide();
            }
        });

        document.addEventListener('keydown', function (event) {
            if ('Escape' === event.key) {
                hide();
            }
        });

        window.addEventListener('resize', function () {
            if (activeTrigger) {
                place(activeTrigger);
            }
        });

        window.addEventListener('scroll', function () {
            if (activeTrigger) {
                place(activeTrigger);
            }
        }, true);
    }

    function bindPopupLayoutPreview() {
        document.querySelectorAll('[data-mds3-popup-layout-preview]').forEach(function (preview) {
            var form = preview.closest('form');
            var input = form ? form.querySelector('textarea[name="popup-template"]') : null;
            var mode = preview.querySelector('[data-mds3-popup-preview-mode]');
            var fallback = preview.querySelector('[data-mds3-popup-preview-fallback]');
            var parts = Array.prototype.slice.call(preview.querySelectorAll('[data-mds3-popup-preview-part]'));

            if (!input) {
                return;
            }

            function update() {
                var template = String(input.value || '');
                var builtIn = !template.trim();
                var tokenPattern = /%(image|alt_text|url|text)%/gi;
                var customText = template
                    .replace(tokenPattern, '')
                    .replace(/<[^>]*>/g, '')
                    .replace(/&nbsp;/gi, ' ')
                    .trim();
                var visible = 0;

                parts.forEach(function (part) {
                    var name = part.getAttribute('data-mds3-popup-preview-part');
                    var show = builtIn
                        ? 'custom' !== name
                        : ('custom' === name ? !!customText : new RegExp('%' + name + '%', 'i').test(template));
                    part.hidden = !show;
                    if (show) {
                        visible += 1;
                    }
                });

                if (mode) {
                    mode.textContent = builtIn
                        ? (preview.getAttribute('data-built-in-label') || '')
                        : (preview.getAttribute('data-custom-label') || '');
                }
                if (fallback) {
                    fallback.hidden = builtIn || visible > 0;
                }
            }

            input.addEventListener('input', update);
            input.addEventListener('change', update);
            update();
        });
    }

    function bindFormUtilities() {
        function editorElement(editor) {
            return editor && editor.id ? document.getElementById(editor.id) : null;
        }

        function isOrderEmailEditorElement(element) {
            var panel = element ? element.closest('[data-settings-group="order-emails"]') : null;
            return !!panel;
        }

        function autosizeTextarea(textarea) {
            if (!textarea || !isOrderEmailEditorElement(textarea)) {
                return;
            }
            textarea.style.height = 'auto';
            textarea.style.height = Math.max(220, textarea.scrollHeight + 8) + 'px';
        }

        function autosizeWpEditor(editor) {
            var element = editorElement(editor);
            var iframe;
            var body;
            var height;

            if (!element || !isOrderEmailEditorElement(element)) {
                return;
            }

            iframe = document.getElementById(editor.id + '_ifr');
            body = editor.getBody ? editor.getBody() : null;
            if (!iframe || !body) {
                autosizeTextarea(element);
                return;
            }

            height = Math.max(260, body.scrollHeight + 36);
            iframe.style.height = height + 'px';
            if (editor.theme && typeof editor.theme.resizeTo === 'function') {
                editor.theme.resizeTo(null, height);
            }
        }

        function bindOrderEmailEditorAutosize(root) {
            var scope = root || document;
            var textareas = [];
            if (window.tinymce && window.tinymce.editors) {
                window.tinymce.editors.forEach(function (editor) {
                    var element = editorElement(editor);
                    if (!element || (root && !root.contains(element)) || !isOrderEmailEditorElement(element)) {
                        return;
                    }
                    if (element.getAttribute('data-mds3-email-autosize-editor') !== 'true') {
                        element.setAttribute('data-mds3-email-autosize-editor', 'true');
                        editor.on('init keyup change SetContent setcontent input paste undo redo', function () {
                            autosizeWpEditor(editor);
                        });
                    }
                    autosizeWpEditor(editor);
                });
            }

            if (scope.matches && scope.matches('[data-settings-group="order-emails"]')) {
                textareas = Array.prototype.slice.call(scope.querySelectorAll('.mds3-editor-field textarea'));
            } else {
                textareas = Array.prototype.slice.call(scope.querySelectorAll('[data-settings-group="order-emails"] .mds3-editor-field textarea'));
            }

            textareas.forEach(function (textarea) {
                if (textarea.getAttribute('data-mds3-email-autosize') !== 'true') {
                    textarea.setAttribute('data-mds3-email-autosize', 'true');
                    textarea.addEventListener('input', function () {
                        autosizeTextarea(textarea);
                    });
                    textarea.addEventListener('change', function () {
                        autosizeTextarea(textarea);
                    });
                }
                autosizeTextarea(textarea);
            });
        }

        function refreshWpEditors(root) {
            window.setTimeout(function () {
                if (!window.tinymce || !window.tinymce.editors) {
                    bindOrderEmailEditorAutosize(root || document);
                    return;
                }

                window.tinymce.editors.forEach(function (editor) {
                    var element = editorElement(editor);
                    if (!element || (root && !root.contains(element))) {
                        return;
                    }

                    editor.fire('ResizeEditor');
                });
                bindOrderEmailEditorAutosize(root || document);
            }, 50);
        }

        document.querySelectorAll('.mds3-inline-form[data-mds3-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                var message = form.getAttribute('data-mds3-confirm') || '';
                if (message && !window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });

        function positionLicensePanel(button, panel) {
            var buttonRect = button.getBoundingClientRect();
            var viewportWidth = document.documentElement.clientWidth || window.innerWidth;
            var viewportHeight = document.documentElement.clientHeight || window.innerHeight;
            var gutter = 16;
            var adminBar = document.getElementById('wpadminbar');
            var adminBarHeight = adminBar ? adminBar.getBoundingClientRect().height : 0;
            var width = Math.min(380, Math.max(280, viewportWidth - (gutter * 2)));
            var panelRect;
            var left;
            var top;

            panel.classList.add('is-floating');
            panel.style.width = width + 'px';
            panel.style.maxHeight = Math.max(180, viewportHeight - adminBarHeight - (gutter * 2)) + 'px';
            panel.style.left = '0px';
            panel.style.top = '0px';
            panel.hidden = false;

            panelRect = panel.getBoundingClientRect();
            left = clamp(buttonRect.right - width, gutter, Math.max(gutter, viewportWidth - width - gutter));
            top = buttonRect.bottom + 8;
            if (top + panelRect.height > viewportHeight - gutter) {
                top = buttonRect.top - panelRect.height - 8;
            }
            top = clamp(top, adminBarHeight + gutter, Math.max(adminBarHeight + gutter, viewportHeight - panelRect.height - gutter));

            panel.style.left = left + 'px';
            panel.style.top = top + 'px';
        }

        function closeLicensePanel(button, returnFocus) {
            var panel = button ? document.getElementById(button.getAttribute('aria-controls') || '') : null;
            if (!button || !panel) {
                return;
            }
            button.setAttribute('aria-expanded', 'false');
            panel.hidden = true;
            panel.classList.remove('is-floating');
            panel.style.removeProperty('left');
            panel.style.removeProperty('top');
            panel.style.removeProperty('width');
            panel.style.removeProperty('max-height');
            if (returnFocus) {
                button.focus();
            }
        }

        function openLicensePanel(button, panel) {
            document.querySelectorAll('[data-mds3-license-toggle][aria-expanded="true"]').forEach(function (openButton) {
                if (openButton !== button) {
                    closeLicensePanel(openButton, false);
                }
            });

            button.setAttribute('aria-expanded', 'true');
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-modal', 'false');
            panel.setAttribute('tabindex', '-1');
            positionLicensePanel(button, panel);
            panel.focus({ preventScroll: true });

            window.setTimeout(function () {
                var input = panel.querySelector('input[type="password"], input[type="text"], button, a, select, textarea');
                if (input) {
                    input.focus();
                } else {
                    panel.focus();
                }
            }, 0);
        }

        document.querySelectorAll('[data-mds3-license-toggle]').forEach(function (button) {
            var panelId = button.getAttribute('aria-controls');
            var panel = panelId ? document.getElementById(panelId) : null;
            if (!panel) {
                return;
            }

            button.addEventListener('click', function (event) {
                event.preventDefault();
                var expanded = button.getAttribute('aria-expanded') === 'true';
                if (expanded) {
                    closeLicensePanel(button, true);
                } else {
                    openLicensePanel(button, panel);
                }
            });
        });

        document.addEventListener('click', function (event) {
            document.querySelectorAll('.mds3-extension-license-popover:not([hidden])').forEach(function (panel) {
                var toggle = document.querySelector('[data-mds3-license-toggle][aria-controls="' + panel.id + '"]');
                if (panel.contains(event.target) || (toggle && toggle.contains(event.target))) {
                    return;
                }
                if (toggle) {
                    closeLicensePanel(toggle, false);
                } else {
                    panel.hidden = true;
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }
            document.querySelectorAll('[data-mds3-license-toggle][aria-expanded="true"]').forEach(function (button) {
                closeLicensePanel(button, true);
            });
        });

        window.addEventListener('resize', function () {
            document.querySelectorAll('[data-mds3-license-toggle][aria-expanded="true"]').forEach(function (button) {
                var panel = document.getElementById(button.getAttribute('aria-controls') || '');
                if (panel) {
                    positionLicensePanel(button, panel);
                }
            });
        });

        window.addEventListener('scroll', function () {
            document.querySelectorAll('[data-mds3-license-toggle][aria-expanded="true"]').forEach(function (button) {
                var panel = document.getElementById(button.getAttribute('aria-controls') || '');
                if (panel) {
                    positionLicensePanel(button, panel);
                }
            });
        }, true);

        document.querySelectorAll('.mds3-settings-tabs').forEach(function (tabs, tabsIndex) {
            var container = tabs.closest('[data-mds3-tab-container]') || tabs.closest('form');
            if (!container) {
                return;
            }

            var buttons = Array.prototype.slice.call(tabs.querySelectorAll('[data-settings-tab]'));
            var panels = Array.prototype.slice.call(container.querySelectorAll('[data-settings-panel]'));

            function activateTab(button, focus) {
                var target = button.getAttribute('data-settings-tab');
                buttons.forEach(function (item) {
                    var active = item === button;
                    item.classList.toggle('is-active', active);
                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                    item.setAttribute('tabindex', active ? '0' : '-1');
                });
                panels.forEach(function (panel) {
                    var active = panel.getAttribute('data-settings-panel') === target;
                    panel.classList.toggle('is-active', active);
                    panel.hidden = !active;
                    if (active) {
                        refreshWpEditors(panel);
                    }
                });
                container.querySelectorAll('[data-settings-save-form]').forEach(function (form) {
                    form.classList.toggle('is-transfer-active', target === 'settings-transfer');
                });
                if (focus) {
                    button.focus();
                    button.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }
                window.dispatchEvent(new Event('resize'));
            }

            buttons.forEach(function (button, buttonIndex) {
                var target = button.getAttribute('data-settings-tab');
                var panel = panels.find(function (item) {
                    return item.getAttribute('data-settings-panel') === target;
                });
                var buttonId = button.id || 'mds3-settings-tab-' + tabsIndex + '-' + buttonIndex;
                button.id = buttonId;
                if (panel) {
                    var panelId = panel.id || 'mds3-settings-panel-' + tabsIndex + '-' + buttonIndex;
                    panel.id = panelId;
                    panel.setAttribute('role', 'tabpanel');
                    panel.setAttribute('aria-labelledby', buttonId);
                    button.setAttribute('aria-controls', panelId);
                }

                button.addEventListener('click', function () {
                    activateTab(button, false);
                });
                button.addEventListener('keydown', function (event) {
                    var currentIndex = buttons.indexOf(button);
                    var nextIndex = currentIndex;
                    if ('ArrowRight' === event.key) {
                        nextIndex = (currentIndex + 1) % buttons.length;
                    } else if ('ArrowLeft' === event.key) {
                        nextIndex = (currentIndex - 1 + buttons.length) % buttons.length;
                    } else if ('Home' === event.key) {
                        nextIndex = 0;
                    } else if ('End' === event.key) {
                        nextIndex = buttons.length - 1;
                    } else {
                        return;
                    }
                    event.preventDefault();
                    activateTab(buttons[nextIndex], true);
                });
            });

            var activeButton = buttons.find(function (button) {
                return button.classList.contains('is-active') || 'true' === button.getAttribute('aria-selected');
            }) || buttons[0];
            if (activeButton) {
                activateTab(activeButton, false);
            }
        });
        refreshWpEditors(document);

        document.querySelectorAll('.mds3-package-load').forEach(function (button) {
            button.addEventListener('click', function () {
                var panel = button.closest('.mds3-panel-content') || button.closest('.mds3-card');
                var form = panel ? panel.querySelector('form') : null;
                if (!form) {
                    return;
                }

                var payload = {};
                try {
                    payload = JSON.parse(button.getAttribute('data-package') || '{}');
                } catch (error) {
                    payload = {};
                }
                fillForm(form, payload);
            });
        });

        document.querySelectorAll('.mds3-form-reset').forEach(function (button) {
            button.addEventListener('click', function () {
                var form = button.closest('form');
                if (!form) {
                    return;
                }

                form.reset();
                fillForm(form, { id: '' });
            });
        });

        document.querySelectorAll('.mds3-image-field').forEach(function (field) {
            var input = field.querySelector('input[type="hidden"]');
            var preview = field.querySelector('.mds3-image-field-preview');
            var select = field.querySelector('.mds3-image-select');
            var clear = field.querySelector('.mds3-image-clear');
            var frame = null;

            function setPreview(attachment) {
                var url = attachment && attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : (attachment ? attachment.url : '');
                if (input) {
                    input.value = attachment && attachment.id ? attachment.id : '';
                }
                if (preview) {
                    preview.innerHTML = url ? '<img class="mds3-image-field-preview-image" src="' + url + '" alt="" />' : '<span>' + (field.getAttribute('data-empty-label') || 'No image selected') + '</span>';
                }
            }

            if (select) {
                select.addEventListener('click', function () {
                    var config = adminConfig();
                    if (!window.wp || !window.wp.media) {
                        return;
                    }

                    if (!frame) {
                        frame = window.wp.media({
                            title: config.i18n && config.i18n.selectImage ? config.i18n.selectImage : 'Select image',
                            button: {
                                text: config.i18n && config.i18n.useImage ? config.i18n.useImage : 'Use image'
                            },
                            library: {
                                type: 'image'
                            },
                            multiple: false
                        });

                        frame.on('select', function () {
                            setPreview(frame.state().get('selection').first().toJSON());
                        });
                    }

                    frame.open();
                });
            }

            if (clear) {
                clear.addEventListener('click', function () {
                    setPreview(null);
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindColorPickers();
        bindHelpTooltips();
        bindExtensionCleanupPolicy();
        bindPopupLayoutPreview();
        bindFormUtilities();
        bindDashboardExtensionPagination();
        restoreSetupContinuity();
        bindSetupPaymentProvider();
        convertSetupNoticesToToasts();
        bindSetupExtensionSelector();
        bindPluginDependencyInstaller();
        bindSetupExtensionActions();
        bindMigrationJobs();
        bindShortcodeCopy();
        bindDocsCodeBlocks();
        bindExtensionOnboardingCategories();
        bindOrderInspect();
        bindOrderBulkActions();
        bindGridBulkActions();
        initRegionEditors(document);
    });
}());
