(function () {
    'use strict';

    var namespace = window.MillionDollarScript = window.MillionDollarScript || {};
    var gridConfig = namespace.grid || {};
    var gridInstances = namespace.gridInstances = namespace.gridInstances || [];

    function request(form) {
        return fetch(gridConfig.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: form
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (payload && typeof payload === 'object') {
                    payload.mds3HttpStatus = response.status;
                }
                return payload;
            });
        });
    }

    function post(action, data) {
        var form = new FormData();
        form.append('action', action);
        Object.keys(data || {}).forEach(function (key) {
            var value = data[key];
            if (Array.isArray(value)) {
                value.forEach(function (item, index) {
                    Object.keys(item).forEach(function (childKey) {
                        form.append(key + '[' + index + '][' + childKey + ']', item[childKey]);
                    });
                });
            } else {
                form.append(key, value);
            }
        });

        return request(form);
    }

    function i18n(key, fallback) {
        return (gridConfig && gridConfig.i18n && gridConfig.i18n[key]) || fallback;
    }

    function buildTileUrl(base, z, x, y, cacheKey, format) {
        var url = String(base || '');
        var hasTemplate;
        var separator;

        if (!url) {
            return '';
        }

        hasTemplate = /\{(?:z|x|y|format)\}/.test(url);
        if (hasTemplate) {
            return url
                .replace(/\{z\}/g, encodeURIComponent(z))
                .replace(/\{x\}/g, encodeURIComponent(x))
                .replace(/\{y\}/g, encodeURIComponent(y))
                .replace(/\{format\}/g, encodeURIComponent(format || 'png'));
        }

        separator = url.indexOf('?') >= 0 ? '&' : '?';
        return url + separator +
            'z=' + encodeURIComponent(z) +
            '&x=' + encodeURIComponent(x) +
            '&y=' + encodeURIComponent(y) +
            (cacheKey ? '&v=' + encodeURIComponent(cacheKey) : '') +
            (format ? '&format=' + encodeURIComponent(format) : '');
    }

    function formatCount(message, count) {
        return String(message || '').replace('%d', String(count)).replace('{count}', String(count));
    }

    function hasUrlProtocol(value) {
        return /^[a-z][a-z0-9+.-]*:/i.test(value);
    }

    function normalizeAdvertiserUrlValue(value) {
        var normalized = String(value || '').trim();
        if (!normalized) {
            return '';
        }

        while (/^(https?:\/\/)(https?:\/\/)/i.test(normalized)) {
            normalized = normalized.replace(/^(https?:\/\/)(https?:\/\/)/i, '$1');
        }

        if (normalized.indexOf('//') === 0) {
            normalized = 'https:' + normalized;
        } else if (!hasUrlProtocol(normalized)) {
            normalized = 'https://' + normalized.replace(/^\/+/, '');
        }

        return normalized;
    }

    function isValidAdvertiserUrl(value) {
        var parsed;
        if (!value) {
            return true;
        }
        if (/\s/.test(value)) {
            return false;
        }

        try {
            parsed = new URL(value);
        } catch (error) {
            return false;
        }

        return !!parsed.hostname && (parsed.protocol === 'http:' || parsed.protocol === 'https:');
    }

    function looksLikeAdvertiserUrl(value) {
        var normalized = String(value || '').trim();
        return !!normalized &&
            !/\s/.test(normalized) &&
            (/^(https?:)?\/\//i.test(normalized) || /^[^\s@/]+\.[^\s@]+/.test(normalized));
    }

    function normalizeAdvertiserUrlInput(input) {
        var normalized;
        if (!input) {
            return true;
        }

        normalized = normalizeAdvertiserUrlValue(input.value);
        input.value = normalized;
        if (isValidAdvertiserUrl(normalized)) {
            input.setCustomValidity('');
            return true;
        }

        input.setCustomValidity(i18n('invalidUrl', 'Enter a valid URL.'));
        return false;
    }

    function bindAdvertiserUrlInputs(root) {
        root.querySelectorAll('input[name="link_url"]').forEach(function (input) {
            if (input.getAttribute('data-mds3-url-bound') === 'true') {
                return;
            }

            input.setAttribute('data-mds3-url-bound', 'true');
            input.addEventListener('blur', function () {
                normalizeAdvertiserUrlInput(input);
            });
            input.addEventListener('change', function () {
                normalizeAdvertiserUrlInput(input);
            });
            input.addEventListener('input', function () {
                input.setCustomValidity('');
            });
        });
    }

    function importantStyle(element, property, value) {
        if (element && element.style && element.style.setProperty) {
            element.style.setProperty(property, value, 'important');
        }
    }

    function themeVariable(element, variable, fallback) {
        var value = '';

        if (element && window.getComputedStyle) {
            value = window.getComputedStyle(element).getPropertyValue(variable).trim();
        }

        return value || fallback;
    }

    function forceLightThemeChrome() {
        var roots;
        var links;
        var controls;
        var buttons;
        var secondaryButtons;
        var tableCells;
        var shellPanels;
        var themeRoot = document.querySelector('.mds3-theme-light.mds3-grid-shell, .mds3-theme-light.mds3-page-panel');
        var background = themeVariable(themeRoot, '--mds3-grid-bg', '#ffffff');
        var panel = themeVariable(themeRoot, '--mds3-grid-panel', '#f8fafc');
        var line = themeVariable(themeRoot, '--mds3-grid-line-soft', '#e5e7eb');
        var text = themeVariable(themeRoot, '--mds3-grid-text', '#111827');
        var muted = themeVariable(themeRoot, '--mds3-grid-muted', '#4b5563');
        var accent = themeVariable(themeRoot, '--mds3-grid-accent', '#2563eb');
        var buttonText = themeVariable(themeRoot, '--mds3-grid-button-text', '#ffffff');

        if (!themeRoot) {
            return;
        }

        document.documentElement.classList.add('mds3-force-light-page');
        roots = [
            document.documentElement,
            document.body,
            document.querySelector('.wp-site-blocks')
        ];
        document.querySelectorAll('.wp-block-template-part, .wp-site-blocks > header, .wp-site-blocks > footer, body > header, body > footer').forEach(function (element) {
            roots.push(element);
        });
        roots.forEach(function (element) {
            importantStyle(element, 'background-color', background);
            importantStyle(element, 'color', text);
            importantStyle(element, 'color-scheme', 'light');
        });

        links = document.querySelectorAll('.wp-site-blocks a:not(.button):not(.mds3-checkout-link)');
        links.forEach(function (link) {
            importantStyle(link, 'color', accent);
        });

        controls = document.querySelectorAll('.mds3-theme-light .mds3-placement-form input, .mds3-theme-light .mds3-placement-form select, .mds3-theme-light .mds3-placement-form textarea, .mds3-theme-light .mds3-package-select, .mds3-theme-light .mds3-advertiser-list-toolbar input[type="search"]');
        controls.forEach(function (control) {
            importantStyle(control, 'background-color', '#ffffff');
            importantStyle(control, 'border-color', '#cbd5e1');
            importantStyle(control, 'color', text);
        });

        buttons = document.querySelectorAll('.mds3-theme-light .mds3-placement-form button, .mds3-theme-light .mds3-order-list .button, .mds3-theme-light.mds3-page-panel .button, .mds3-theme-light .mds3-page-actions .button, .mds3-theme-light .mds3-reserve, .mds3-theme-light .mds3-grid-restore-progress, .mds3-theme-light .mds3-checkout-link');
        buttons.forEach(function (button) {
            importantStyle(button, 'background-color', accent);
            importantStyle(button, 'border-color', accent);
            importantStyle(button, 'color', buttonText);
        });

        secondaryButtons = document.querySelectorAll('.mds3-theme-light .mds3-grid-tools button, .mds3-theme-light .mds3-grid-actions .mds3-clear, .mds3-theme-light .mds3-grid-restore-dismiss, .mds3-theme-light .mds3-grid-map .ol-control button, .mds3-theme-light .mds3-placement-form .mds3-form-message button, .mds3-theme-light .mds3-placement-form .mds3-draft-notice button, .mds3-theme-light .mds3-placement-form .mds3-draft-image-notice button, .mds3-theme-light .mds3-placement-form .mds3-draft-image-remove');
        secondaryButtons.forEach(function (button) {
            button.classList.add('wp-dark-mode-ignore');
            importantStyle(button, 'background-color', '#ffffff');
            importantStyle(button, 'border-color', '#cbd5e1');
            importantStyle(button, 'color', text);
            importantStyle(button, 'text-shadow', 'none');
        });

        tableCells = document.querySelectorAll('.mds3-theme-light .mds3-order-list th, .mds3-theme-light .mds3-order-list td');
        tableCells.forEach(function (cell) {
            importantStyle(cell, 'background-color', background);
            importantStyle(cell, 'border-color', line);
            importantStyle(cell, 'color', text);
        });

        shellPanels = document.querySelectorAll('.mds3-grid-shell.mds3-theme-light > header, .mds3-theme-light .mds3-grid-tools, .mds3-theme-light .mds3-grid-footer, .mds3-theme-light .mds3-grid-actions, .mds3-theme-light .mds3-grid-restore-bar, .mds3-theme-light .mds3-placement-form, .mds3-theme-light .mds3-advertiser-list-toolbar, .mds3-theme-light .mds3-advertiser-accordion details');
        shellPanels.forEach(function (panel) {
            importantStyle(panel, 'background-color', background);
            importantStyle(panel, 'border-color', line);
            importantStyle(panel, 'color', text);
        });
        document.querySelectorAll('.mds3-grid-shell.mds3-theme-light > header h2, .mds3-grid-shell.mds3-theme-light > header p').forEach(function (element) {
            importantStyle(element, 'color', element.tagName === 'P' ? muted : text);
        });
    }

    function clearFormInvalidState(form) {
        if (!form) {
            return;
        }
        form.querySelectorAll('[aria-invalid="true"]').forEach(function (field) {
            field.removeAttribute('aria-invalid');
        });
    }

    function richTextWrapper(element) {
        return element && element.closest ? element.closest('.mds3-placement-popup-text') : null;
    }

    function richTextInputForSource(source) {
        var wrapper = richTextWrapper(source);
        return wrapper ? wrapper.querySelector('[data-mds3-rich-text-input]') : null;
    }

    function richTextSourceForEditor(editor) {
        var wrapper = richTextWrapper(editor);
        return wrapper ? wrapper.querySelector('textarea[name="popup_text"][data-mds3-rich-text-source]') : null;
    }

    function richTextFocusTarget(field) {
        return richTextInputForSource(field) || field;
    }

    function setFieldValidity(field, message) {
        var target = richTextFocusTarget(field);
        if (!field) {
            return;
        }
        if (typeof field.setCustomValidity === 'function') {
            field.setCustomValidity(message || '');
        }
        if (message) {
            field.setAttribute('aria-invalid', 'true');
            if (target && target !== field) {
                target.setAttribute('aria-invalid', 'true');
            }
        } else {
            field.removeAttribute('aria-invalid');
            if (target && target !== field) {
                target.removeAttribute('aria-invalid');
            }
        }
    }

    function sanitizeRichTextHtml(html) {
        var input = document.createElement('div');
        var output = document.createElement('div');
        var allowed = {
            BR: true,
            B: true,
            STRONG: true,
            I: true,
            EM: true,
            P: true
        };

        function copyChildren(from, to) {
            Array.prototype.slice.call(from.childNodes || []).forEach(function (child) {
                copyNode(child, to);
            });
        }

        function copyNode(node, parent) {
            var tag;
            var element;
            if (node.nodeType === Node.TEXT_NODE) {
                parent.appendChild(document.createTextNode(node.nodeValue || ''));
                return;
            }
            if (node.nodeType !== Node.ELEMENT_NODE) {
                return;
            }

            tag = node.tagName;
            if (tag === 'SCRIPT' || tag === 'STYLE') {
                return;
            }
            if (!allowed[tag]) {
                copyChildren(node, parent);
                return;
            }
            if (tag === 'BR') {
                parent.appendChild(document.createElement('br'));
                return;
            }

            element = document.createElement(tag.toLowerCase());
            copyChildren(node, element);
            parent.appendChild(element);
        }

        input.innerHTML = String(html || '');
        copyChildren(input, output);

        return output.innerHTML;
    }

    function updateRichTextPlaceholder(input) {
        if (input) {
            input.classList.toggle('mds3-rich-text-empty', !String(input.textContent || '').trim());
        }
    }

    function syncRichTextEditor(editor, emit) {
        var input = editor ? editor.querySelector('[data-mds3-rich-text-input]') : null;
        var source = richTextSourceForEditor(editor);
        if (!input || !source) {
            return;
        }

        source.mds3RichTextSyncing = true;
        try {
            source.value = sanitizeRichTextHtml(input.innerHTML);
            updateRichTextPlaceholder(input);
            if (emit) {
                source.dispatchEvent(new Event('input', { bubbles: true }));
            }
        } finally {
            source.mds3RichTextSyncing = false;
        }
    }

    function syncRichTextEditors(root) {
        (root || document).querySelectorAll('[data-mds3-rich-text-editor]').forEach(function (editor) {
            syncRichTextEditor(editor, false);
        });
    }

    function refreshRichTextEditorForSource(source) {
        var input = richTextInputForSource(source);
        if (!input || !source || source.mds3RichTextSyncing) {
            return;
        }

        input.innerHTML = sanitizeRichTextHtml(source.value);
        updateRichTextPlaceholder(input);
    }

    function refreshRichTextEditors(root) {
        (root || document).querySelectorAll('textarea[name="popup_text"][data-mds3-rich-text-source]').forEach(function (source) {
            refreshRichTextEditorForSource(source);
        });
    }

    function initializeRichTextEditors(root) {
        (root || document).querySelectorAll('[data-mds3-rich-text-editor]').forEach(function (editor) {
            var input = editor.querySelector('[data-mds3-rich-text-input]');
            var source = richTextSourceForEditor(editor);
            if (!input || !source || editor.getAttribute('data-mds3-rich-text-bound') === 'true') {
                return;
            }

            editor.setAttribute('data-mds3-rich-text-bound', 'true');
            refreshRichTextEditorForSource(source);
            input.addEventListener('input', function () {
                syncRichTextEditor(editor, true);
            });
            input.addEventListener('paste', function (event) {
                var text = event.clipboardData ? event.clipboardData.getData('text/plain') : '';
                event.preventDefault();
                document.execCommand('insertText', false, text);
                syncRichTextEditor(editor, true);
            });
            editor.querySelectorAll('[data-mds3-rich-command]').forEach(function (button) {
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
                button.addEventListener('click', function () {
                    var command = button.getAttribute('data-mds3-rich-command');
                    input.focus();
                    if (command === 'bold') {
                        document.execCommand('bold', false, null);
                    } else if (command === 'italic') {
                        document.execCommand('italic', false, null);
                    } else if (command === 'paragraph') {
                        document.execCommand('formatBlock', false, 'p');
                    }
                    syncRichTextEditor(editor, true);
                });
            });
            source.addEventListener('input', function () {
                refreshRichTextEditorForSource(source);
            });
        });
    }

    function namedField(form, name) {
        return form ? form.querySelector('[name="' + name + '"]') : null;
    }

    function namedFieldValue(form, name) {
        var field = namedField(form, name);
        return field ? String(field.value || '') : '';
    }

    function ensureHiddenField(form, name) {
        var field = namedField(form, name);
        if (field) {
            return field;
        }

        field = document.createElement('input');
        field.type = 'hidden';
        field.name = name;
        form.appendChild(field);
        return field;
    }

    function formHasDraftImage(form) {
        return !!(namedFieldValue(form, 'draft_attachment_id') && namedFieldValue(form, 'draft_token'));
    }

    function setDraftImageFields(form, draft) {
        var attachment = ensureHiddenField(form, 'draft_attachment_id');
        var token = ensureHiddenField(form, 'draft_token');
        if (!draft || !draft.attachment_id || !draft.token) {
            attachment.value = '';
            token.value = '';
            return;
        }

        attachment.value = String(draft.attachment_id || '');
        token.value = String(draft.token || '');
    }

    function clearDraftImageFields(form) {
        setDraftImageFields(form, null);
    }

    function draftImageRemoveButton(form) {
        return form ? form.querySelector('.mds3-draft-image-remove') : null;
    }

    function setDraftImageControlState(form, hasImage) {
        var imageInput = namedField(form, 'image');
        var remove = draftImageRemoveButton(form);

        if (imageInput) {
            if (hasImage) {
                imageInput.required = false;
            } else if (form && form.classList.contains('mds3-order-upload-form')) {
                imageInput.required = !!form.mds3OriginalImageRequired;
            } else {
                imageInput.required = true;
            }
        }
        if (remove) {
            remove.hidden = !hasImage;
        }
    }

    function ensureFormMessage(form) {
        var message;
        var text;
        var actions;
        var dismiss;
        if (!form) {
            return null;
        }

        message = form.querySelector(':scope > .mds3-form-message');
        if (message) {
            return message;
        }

        message = document.createElement('div');
        message.className = 'mds3-form-message';
        message.setAttribute('role', 'status');
        message.setAttribute('aria-live', 'polite');
        message.hidden = true;

        text = document.createElement('span');
        text.className = 'mds3-form-message-text';
        actions = document.createElement('span');
        actions.className = 'mds3-form-message-actions';
        dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'mds3-form-message-dismiss';
        dismiss.textContent = i18n('draftDismiss', 'Dismiss');
        dismiss.setAttribute('aria-label', i18n('dismissMessage', 'Dismiss message'));
        dismiss.addEventListener('click', function () {
            message.hidden = true;
        });

        actions.appendChild(dismiss);
        message.appendChild(text);
        message.appendChild(actions);
        form.insertBefore(message, form.firstChild);

        return message;
    }

    function showFormMessage(form, message, field) {
        var messageElement = ensureFormMessage(form);
        var text = messageElement ? messageElement.querySelector('.mds3-form-message-text') : null;
        if (!messageElement || !text || !message) {
            return;
        }

        text.textContent = message;
        messageElement.hidden = false;
        if (field) {
            setFieldValidity(field, message);
        }
    }

    function hideFormMessage(form) {
        var messageElement = form ? form.querySelector(':scope > .mds3-form-message') : null;
        if (messageElement) {
            messageElement.hidden = true;
        }
    }

    function placementFormValidation(form, normalizeUrls, uploadRules) {
        syncRichTextEditors(form);

        var imageInput = form ? form.querySelector('input[type="file"][name="image"]') : null;
        var urlInput = form ? form.querySelector('input[name="link_url"]') : null;
        var textInput = form ? form.querySelector('textarea[name="popup_text"]') : null;
        var rawUrl = urlInput ? String(urlInput.value || '').trim() : '';
        var rawText = textInput ? String(textInput.value || '').trim() : '';
        var urlRequired = uploadRules ? !!uploadRules.url_required : !!(urlInput && urlInput.required);
        var textRequired = uploadRules ? !!uploadRules.text_required : !!(textInput && textInput.required);
        var missingText = i18n('missingPopupText', 'Enter the popup text for this placement.');
        var normalizedUrl;

        clearFormInvalidState(form);
        [imageInput, urlInput, textInput].forEach(function (field) {
            setFieldValidity(field, '');
        });

        if (imageInput && imageInput.required && !imageInput.files.length && !formHasDraftImage(form)) {
            return {
                field: imageInput,
                message: i18n('missingImage', 'Choose an image before saving the ad.')
            };
        }
        if (textInput && textRequired && !rawText) {
            return {
                field: textInput,
                message: missingText
            };
        }
        if (urlInput && urlRequired && !rawUrl) {
            return {
                field: urlInput,
                message: i18n('missingUrl', 'Enter the advertiser destination URL.')
            };
        }
        if (urlInput && rawUrl) {
            if (normalizeUrls) {
                if (!normalizeAdvertiserUrlInput(urlInput)) {
                    return {
                        field: urlInput,
                        message: urlInput.validationMessage || i18n('invalidUrl', 'Enter a valid website URL.')
                    };
                }
            } else {
                normalizedUrl = normalizeAdvertiserUrlValue(rawUrl);
                if (!isValidAdvertiserUrl(normalizedUrl)) {
                    return {
                        field: urlInput,
                        message: i18n('invalidUrl', 'Enter a valid website URL.')
                    };
                }
            }
        }

        return {
            field: null,
            message: ''
        };
    }

    function validatePlacementForm(form, normalizeUrls, uploadRules) {
        var result = placementFormValidation(form, normalizeUrls, uploadRules);
        if (result.message) {
            showFormMessage(form, result.message, result.field);
            if (result.field && typeof richTextFocusTarget(result.field).focus === 'function') {
                richTextFocusTarget(result.field).focus({ preventScroll: false });
            }
            return false;
        }

        clearFormInvalidState(form);
        hideFormMessage(form);
        return true;
    }

    function draftStorageKey(form, key) {
        var host = window.location && window.location.host ? window.location.host : 'site';
        return 'mds3:placement-draft:' + host + ':' + key;
    }

    var draftStorageMaxAge = 7 * 24 * 60 * 60 * 1000;

    function purgeExpiredDraftStorage() {
        var prefix = 'mds3:placement-draft:';
        if (!window.localStorage) {
            return;
        }

        try {
            Object.keys(window.localStorage).forEach(function (key) {
                var parsed;
                if (key.indexOf(prefix) !== 0) {
                    return;
                }
                try {
                    parsed = JSON.parse(window.localStorage.getItem(key) || 'null');
                } catch (error) {
                    parsed = null;
                }
                if (!parsed || Date.now() - Number(parsed.savedAt || 0) > draftStorageMaxAge) {
                    window.localStorage.removeItem(key);
                }
            });
        } catch (error) {
            // Storage can be unavailable in private or locked-down browser contexts.
        }
    }

    function activeOrderStorageKey(gridId) {
        return draftStorageKey(null, 'grid:' + gridId + ':active-order');
    }

    function readActiveGridOrder(gridId) {
        var key = activeOrderStorageKey(gridId);
        var parsed;
        if (!gridId || !window.localStorage) {
            return null;
        }

        try {
            parsed = JSON.parse(window.localStorage.getItem(key) || 'null');
        } catch (error) {
            return null;
        }

        if (
            !parsed ||
            !parsed.order ||
            !parsed.order.id ||
            !parsed.order_key ||
            Date.now() - Number(parsed.savedAt || 0) > draftStorageMaxAge
        ) {
            try {
                window.localStorage.removeItem(key);
            } catch (error) {}
            return null;
        }

        return parsed;
    }

    function saveActiveGridOrder(gridId, order, orderKey, checkout, placementRect, placementMask) {
        var key = activeOrderStorageKey(gridId);
        if (!gridId || !order || !order.id || !orderKey || !window.localStorage) {
            return;
        }

        try {
            window.localStorage.setItem(key, JSON.stringify({
                order: {
                    id: order.id
                },
                order_key: orderKey,
                checkout: checkout || {},
                placement_rect: placementRect || null,
                placement_mask: Array.isArray(placementMask) ? placementMask : [],
                savedAt: Date.now()
            }));
        } catch (error) {}
    }

    function clearActiveGridOrder(gridId) {
        if (!gridId || !window.localStorage) {
            return;
        }

        try {
            window.localStorage.removeItem(activeOrderStorageKey(gridId));
        } catch (error) {}
    }

    function latestDraftForPrefix(prefixKey, currentKey) {
        var prefix = draftStorageKey(null, prefixKey);
        var latest = null;
        var latestKey = '';
        var draft;
        if (!window.localStorage) {
            return null;
        }

        try {
            Object.keys(window.localStorage).forEach(function (key) {
                if (key === currentKey || key.indexOf(prefix) !== 0) {
                    return;
                }
                draft = readDraft(key);
                if (!draft) {
                    return;
                }
                if (!latest || Number(draft.savedAt || 0) > Number(latest.savedAt || 0)) {
                    latest = draft;
                    latestKey = key;
                }
            });
        } catch (error) {
            return null;
        }

        return latest ? {
            draft: latest,
            key: latestKey
        } : null;
    }

    function draftFormControls(form) {
        return Array.prototype.slice.call(form ? form.querySelectorAll('input, textarea, select') : []).filter(function (field) {
            var type = String(field.type || '').toLowerCase();
            var name = field.name || '';
            if (!name || field.disabled) {
                return false;
            }
            return ['file', 'hidden', 'button', 'submit', 'reset', 'password'].indexOf(type) < 0 &&
                ['action', 'nonce', '_wpnonce', 'order_key', 'upload_context'].indexOf(name) < 0;
        });
    }

    function collectDraftData(form) {
        syncRichTextEditors(form);

        var fields = {};
        var fileNames = {};
        var hasData = false;
        draftFormControls(form).forEach(function (field) {
            var type = String(field.type || '').toLowerCase();
            if (type === 'checkbox') {
                fields[field.name] = field.checked ? '1' : '0';
            } else if (type === 'radio') {
                if (field.checked) {
                    fields[field.name] = field.value;
                }
            } else {
                fields[field.name] = field.value;
            }
            if (String(fields[field.name] || '').trim() !== '') {
                hasData = true;
            }
        });

        (form ? form.querySelectorAll('input[type="file"][name]') : []).forEach(function (input) {
            if (input.files && input.files.length) {
                fileNames[input.name] = input.files[0].name || '';
                hasData = true;
            }
        });

        if (formHasDraftImage(form)) {
            hasData = true;
        }

        return hasData ? {
            fields: fields,
            fileNames: fileNames,
            serverDraft: formHasDraftImage(form) ? {
                attachment_id: namedFieldValue(form, 'draft_attachment_id'),
                token: namedFieldValue(form, 'draft_token')
            } : null,
            savedAt: Date.now()
        } : null;
    }

    function readDraft(key) {
        var parsed;
        try {
            parsed = JSON.parse(window.localStorage.getItem(key) || 'null');
        } catch (error) {
            return null;
        }
        if (!parsed || !parsed.fields || Date.now() - Number(parsed.savedAt || 0) > draftStorageMaxAge) {
            try {
                window.localStorage.removeItem(key);
            } catch (error) {}
            return null;
        }

        return parsed;
    }

    function formHasDraftDifference(form, draft) {
        var current = collectDraftData(form) || { fields: {} };
        if (draft && draft.fileNames && Object.keys(draft.fileNames).length) {
            return true;
        }
        if (draft && draft.serverDraft && draft.serverDraft.attachment_id && draft.serverDraft.token) {
            return true;
        }

        return Object.keys(draft && draft.fields ? draft.fields : {}).some(function (name) {
            return String(current.fields[name] || '') !== String(draft.fields[name] || '');
        });
    }

    function applyDraftData(form, draft) {
        draftFormControls(form).forEach(function (field) {
            var type = String(field.type || '').toLowerCase();
            if (!Object.prototype.hasOwnProperty.call(draft.fields || {}, field.name)) {
                return;
            }
            if (type === 'checkbox') {
                field.checked = String(draft.fields[field.name]) === '1';
            } else if (type === 'radio') {
                field.checked = String(field.value) === String(draft.fields[field.name]);
            } else {
                field.value = draft.fields[field.name];
            }
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
        if (draft && draft.serverDraft) {
            setDraftImageFields(form, draft.serverDraft);
            setDraftImageControlState(form, true);
        }
        refreshRichTextEditors(form);
    }

    function ensureDraftNotice(form) {
        var notice;
        var text;
        var actions;
        var restore;
        var dismiss;
        if (!form) {
            return null;
        }
        notice = form.querySelector(':scope > .mds3-draft-notice');
        if (notice) {
            return notice;
        }

        notice = document.createElement('div');
        notice.className = 'mds3-draft-notice';
        notice.setAttribute('role', 'status');
        notice.setAttribute('aria-live', 'polite');
        notice.hidden = true;
        text = document.createElement('span');
        text.className = 'mds3-draft-text';
        actions = document.createElement('span');
        actions.className = 'mds3-draft-actions';
        restore = document.createElement('button');
        restore.type = 'button';
        restore.className = 'mds3-draft-restore';
        restore.textContent = i18n('draftRestore', 'Restore details');
        dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'mds3-draft-dismiss';
        dismiss.textContent = i18n('draftDismiss', 'Dismiss');
        actions.appendChild(restore);
        actions.appendChild(dismiss);
        notice.appendChild(text);
        notice.appendChild(actions);
        form.insertBefore(notice, form.firstChild);

        return notice;
    }

    function mergeExistingDraftFields(data, existing) {
        if (!data || !data.fields || !existing || !existing.fields) {
            return data;
        }

        Object.keys(existing.fields).forEach(function (name) {
            var current = data.fields[name];
            var previous = existing.fields[name];
            if (typeof previous === 'undefined') {
                return;
            }
            if (String(current || '').trim() === '' || (name === 'fit_mode' && String(current || '') === 'cover' && String(previous || '') !== '')) {
                data.fields[name] = previous;
            }
        });

        return data;
    }

    function saveFormDraft(form, options) {
        var key = form ? form.getAttribute('data-mds3-draft-key') : '';
        var data;
        var existing;
        if (!key || !window.localStorage) {
            return;
        }

        data = collectDraftData(form);
        if (data && options && options.preserveExistingFields) {
            existing = readDraft(key);
            data = mergeExistingDraftFields(data, existing);
        }
        try {
            if (data) {
                window.localStorage.setItem(key, JSON.stringify(data));
            } else {
                window.localStorage.removeItem(key);
            }
        } catch (error) {}
    }

    function clearStoredServerDraft(form) {
        var key = form ? form.getAttribute('data-mds3-draft-key') : '';
        var draft;
        if (!key || !window.localStorage) {
            return;
        }

        draft = readDraft(key);
        if (!draft || !draft.serverDraft) {
            return;
        }

        draft.serverDraft = null;
        draft.savedAt = Date.now();
        try {
            window.localStorage.setItem(key, JSON.stringify(draft));
        } catch (error) {}
    }

    function scheduleFormDraftSave(form) {
        if (!form) {
            return;
        }
        window.clearTimeout(form.mds3DraftTimer);
        form.mds3DraftTimer = window.setTimeout(function () {
            saveFormDraft(form);
        }, 350);
    }

    function bindFormDraft(form, rawKey, fallbackPrefix) {
        var key;
        var notice;
        var text;
        var restore;
        var dismiss;
        var existing;
        var sourceKey;
        var fallback;

        if (!form || !rawKey || !window.localStorage) {
            return;
        }

        key = draftStorageKey(form, rawKey);
        if (form.getAttribute('data-mds3-draft-key') === key) {
            return;
        }
        form.setAttribute('data-mds3-draft-key', key);

        notice = ensureDraftNotice(form);
        text = notice ? notice.querySelector('.mds3-draft-text') : null;
        restore = notice ? notice.querySelector('.mds3-draft-restore') : null;
        dismiss = notice ? notice.querySelector('.mds3-draft-dismiss') : null;
        existing = readDraft(key);
        sourceKey = key;
        if (!existing && fallbackPrefix) {
            fallback = latestDraftForPrefix(fallbackPrefix, key);
            if (fallback) {
                existing = fallback.draft;
                sourceKey = fallback.key;
            }
        }

        if (existing && formHasDraftDifference(form, existing) && notice && text) {
            notice.setAttribute('data-mds3-draft-source-key', sourceKey);
            text.textContent = i18n('draftFound', 'Saved ad details were found for this order.') +
                (existing.fileNames && Object.keys(existing.fileNames).length && !(existing.serverDraft && existing.serverDraft.attachment_id && existing.serverDraft.token) ? ' ' + i18n('draftFileNotice', 'Browsers cannot restore an unsaved image file.') : '');
            notice.hidden = false;
        } else if (notice) {
            notice.removeAttribute('data-mds3-draft-source-key');
            notice.hidden = true;
        }

        if (restore) {
            restore.onclick = function () {
                var restoreKey = notice.getAttribute('data-mds3-draft-source-key') || key;
                var draft = readDraft(restoreKey);
                if (!draft) {
                    notice.hidden = true;
                    return;
                }
                applyDraftData(form, draft);
                if (restoreKey !== key) {
                    try {
                        window.localStorage.removeItem(restoreKey);
                    } catch (error) {}
                }
                notice.hidden = true;
                showFormMessage(form, i18n('draftRestored', 'Saved ad details restored.'), null);
            };
        }

        if (dismiss) {
            dismiss.onclick = function () {
                var dismissKey = notice.getAttribute('data-mds3-draft-source-key') || key;
                try {
                    window.localStorage.removeItem(dismissKey);
                } catch (error) {}
                notice.hidden = true;
            };
        }

        if (form.getAttribute('data-mds3-draft-bound') !== 'true') {
            form.setAttribute('data-mds3-draft-bound', 'true');
            form.addEventListener('input', function (event) {
                if (String(event && event.target && event.target.type || '').toLowerCase() === 'file') {
                    return;
                }
                scheduleFormDraftSave(form);
            });
            form.addEventListener('change', function (event) {
                if (String(event && event.target && event.target.type || '').toLowerCase() === 'file') {
                    return;
                }
                scheduleFormDraftSave(form);
            });
            window.addEventListener('beforeunload', function () {
                saveFormDraft(form);
            });
        }

        return {
            draft: existing,
            key: key,
            notice: notice,
            sourceKey: sourceKey
        };
    }

    function clearFormDraft(form) {
        var key = form ? form.getAttribute('data-mds3-draft-key') : '';
        if (!key || !window.localStorage) {
            return;
        }
        try {
            window.localStorage.removeItem(key);
        } catch (error) {}
    }

    function restoreVisibleFormDraft(form) {
        var notice = form ? form.querySelector(':scope > .mds3-draft-notice') : null;
        var restore = notice && !notice.hidden ? notice.querySelector('.mds3-draft-restore') : null;
        if (!restore) {
            return false;
        }

        restore.click();
        return true;
    }

    function setOrderUploadBusy(form, busy, busyLabelKey) {
        var button = form ? form.querySelector('button[type="submit"]') : null;
        if (!form || !button) {
            return;
        }

        if (!button.getAttribute('data-mds3-label')) {
            button.setAttribute('data-mds3-label', button.textContent || '');
        }
        form.setAttribute('data-mds3-busy', busy ? 'true' : 'false');
        button.disabled = !!busy;
        button.setAttribute('aria-busy', busy ? 'true' : 'false');
        button.textContent = busy ? i18n(busyLabelKey || 'uploading', 'Saving...') : button.getAttribute('data-mds3-label');
    }

    function updateOrderUploadPreview(form, placement) {
        var panel = form ? form.closest('.mds3-order-upload-panel') : null;
        var preview = panel ? panel.querySelector('.mds3-current-placement') : null;
        var fileInput = form ? form.querySelector('input[type="file"][name="image"]') : null;
        var source = placement && placement.source ? placement.source : {};
        var fitMode = namedFieldValue(form, 'fit_mode') || placement.fit_mode || 'cover';
        var image;

        if (!preview || !source.url) {
            return;
        }

        image = document.createElement('img');
        image.src = source.url;
        image.alt = placement.alt_text || '';
        image.loading = 'lazy';
        image.decoding = 'async';
        image.style.objectFit = fitMode === 'contain' ? 'contain' : 'cover';
        preview.innerHTML = '';
        preview.appendChild(image);
        preview.hidden = false;

        if (fileInput) {
            fileInput.required = false;
        }
    }

    function ensureDraftImageNotice(form) {
        var notice;
        var text;
        var actions;
        var restore;
        var dismiss;
        if (!form) {
            return null;
        }

        notice = form.querySelector(':scope > .mds3-draft-image-notice');
        if (notice) {
            return notice;
        }

        notice = document.createElement('div');
        notice.className = 'mds3-draft-image-notice';
        notice.setAttribute('role', 'status');
        notice.setAttribute('aria-live', 'polite');
        notice.hidden = true;

        text = document.createElement('span');
        text.className = 'mds3-draft-image-text';
        restore = document.createElement('button');
        restore.type = 'button';
        restore.className = 'mds3-draft-image-restore';
        restore.textContent = i18n('draftImageRestore', 'Restore image');
        dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'mds3-draft-image-dismiss';
        dismiss.textContent = i18n('draftDismiss', 'Dismiss');

        actions = document.createElement('span');
        actions.className = 'mds3-draft-actions';
        actions.appendChild(restore);
        actions.appendChild(dismiss);
        notice.appendChild(text);
        notice.appendChild(actions);
        form.insertBefore(notice, form.firstChild);

        return notice;
    }

    function showDraftImageNotice(form, draft, onRestore) {
        var notice = ensureDraftImageNotice(form);
        var text = notice ? notice.querySelector('.mds3-draft-image-text') : null;
        var restore = notice ? notice.querySelector('.mds3-draft-image-restore') : null;
        var dismiss = notice ? notice.querySelector('.mds3-draft-image-dismiss') : null;
        if (!notice || !text || !draft) {
            return;
        }

        text.textContent = i18n('draftImageFound', 'A saved image was found for this order.');
        notice.hidden = false;
        if (restore) {
            restore.onclick = function () {
                notice.hidden = true;
                if (typeof onRestore === 'function') {
                    onRestore(draft);
                }
            };
        }
        if (dismiss) {
            dismiss.onclick = function () {
                notice.hidden = true;
            };
        }
    }

    function hideDraftImageNotice(form) {
        var notice = form ? form.querySelector(':scope > .mds3-draft-image-notice') : null;
        if (notice) {
            notice.hidden = true;
        }
    }

    function orderCredentials(form, gridId) {
        return {
            order_id: namedFieldValue(form, 'order_id') || (form ? form.getAttribute('data-mds3-order-id') : ''),
            order_key: namedFieldValue(form, 'order_key'),
            grid_id: gridId || namedFieldValue(form, 'grid_id') || ''
        };
    }

    function appendOrderCredentials(payload, credentials) {
        payload.append('nonce', gridConfig.nonce);
        payload.append('order_id', credentials.order_id || '');
        payload.append('order_key', credentials.order_key || '');
        if (credentials.grid_id) {
            payload.append('grid_id', credentials.grid_id);
        }
    }

    function fetchDraftImageState(form, gridId) {
        var credentials = orderCredentials(form, gridId);
        if (!credentials.order_id || !credentials.order_key) {
            return Promise.resolve(null);
        }

        return post('mds3_placement_draft_state', {
            nonce: gridConfig.nonce,
            order_id: credentials.order_id,
            order_key: credentials.order_key,
            grid_id: credentials.grid_id
        }).then(function (response) {
            if (!response || !response.success || !response.data) {
                return null;
            }

            return response.data.draft || null;
        });
    }

    function uploadDraftImage(form, file, gridId) {
        var payload = new FormData();
        var credentials = orderCredentials(form, gridId);
        if (!file || !credentials.order_id || !credentials.order_key) {
            return Promise.reject(new Error(i18n('missingImage', 'Choose an image before saving the ad.')));
        }

        payload.append('action', 'mds3_upload_placement_draft_image');
        appendOrderCredentials(payload, credentials);
        payload.append('image', file, file.name || 'placement-image');

        return request(payload).then(function (response) {
            if (!response || !response.success || !response.data || !response.data.draft) {
                throw new Error(response && response.data && response.data.message ? response.data.message : gridConfig.i18n.error);
            }

            return response.data.draft;
        });
    }

    function removeDraftImage(form, gridId) {
        var payload = new FormData();
        var credentials = orderCredentials(form, gridId);
        if (!credentials.order_id || !credentials.order_key || !namedFieldValue(form, 'draft_token')) {
            return Promise.resolve(null);
        }

        payload.append('action', 'mds3_remove_placement_draft_image');
        appendOrderCredentials(payload, credentials);
        payload.append('draft_token', namedFieldValue(form, 'draft_token'));

        return request(payload).then(function (response) {
            if (!response || !response.success) {
                throw new Error(response && response.data && response.data.message ? response.data.message : i18n('draftImageRemoveError', 'Draft image could not be removed.'));
            }

            return response.data || null;
        });
    }

    function canUseOpenLayers() {
        return !!(
            window.ol &&
            window.ol.Map &&
            window.ol.View &&
            window.ol.proj &&
            window.ol.layer &&
            window.ol.layer.Image &&
            window.ol.source &&
            window.ol.source.ImageCanvas
        );
    }

    function canUseOpenLayersTiles() {
        return !!(
            canUseOpenLayers() &&
            window.ol.layer.Tile &&
            window.ol.source.XYZ &&
            window.ol.tilegrid &&
            window.ol.tilegrid.TileGrid
        );
    }

    var GRID_SESSION_RELOAD_KEY = 'mds3GridSessionReload';

    function reloadForExpiredSession() {
        try {
            if (window.sessionStorage.getItem(GRID_SESSION_RELOAD_KEY)) {
                return false;
            }
            sessionStorage.setItem(GRID_SESSION_RELOAD_KEY, String(Date.now()));
        } catch (error) {
            return false;
        }

        window.location.reload();
        return true;
    }

    function Grid(element) {
        this.element = element;
        this.viewport = element.querySelector('.mds3-grid-viewport');
        this.mapElement = element.querySelector('.mds3-grid-map');
        this.canvasWrap = element.querySelector('.mds3-grid-canvas-wrap');
        this.canvas = element.querySelector('.mds3-grid-canvas');
        this.ctx = this.canvas ? this.canvas.getContext('2d') : null;
        this.popover = element.querySelector('.mds3-grid-popover');
        this.popoverBackdrop = null;
        this.selectionMessage = element.querySelector('.mds3-grid-selection-message');
        this.gridId = element.getAttribute('data-grid-id');
        this.readOnly = element.getAttribute('data-read-only') === 'true';
        this.rendererMode = element.getAttribute('data-renderer-mode') || 'auto';
        this.actions = element.querySelector('.mds3-grid-actions');
        this.selectionSizeButton = element.querySelector('.mds3-selection-size');
        this.selectionSize = 1;
        this.selectionSizeDialog = null;
        this.summary = element.querySelector('.mds3-selection-summary');
        this.packageSelect = element.querySelector('.mds3-package-select');
        this.subscriptionPlanSelect = element.querySelector('.mds3-subscription-plan-select');
        this.customerEmail = element.querySelector('.mds3-customer-email');
        this.loginRequiredLink = element.querySelector('.mds3-login-required');
        this.reserveButton = element.querySelector('.mds3-reserve');
        this.status = element.querySelector('.mds3-grid-status');
        this.inlineStats = element.querySelector('.mds3-grid-inline-stats');
        this.form = element.querySelector('.mds3-placement-form');
        this.restoreBar = null;
        this.checkoutLink = element.querySelector('.mds3-checkout-link');
        this.submitButton = this.form ? this.form.querySelector('button[type="submit"]') : null;
        this.checkoutUrl = '';
        this.afterUploadUrl = '';
        this.selectionValidity = null;
        this.uploadMessage = null;
        this.reserving = false;
        this.uploading = false;
        this.reserveButtonLabel = this.reserveButton ? this.reserveButton.textContent : '';
        this.viewAllButton = element.querySelector('.mds3-view-all');
        this.viewImagesButton = element.querySelector('.mds3-view-images');
        this.selected = [];
        this.state = null;
        this.currentOrder = null;
        this.currentOrderKey = '';
        this.currentPlacementRect = null;
        this.currentPlacementMask = [];
        this.draftPlacement = null;
        this.draftObjectUrl = '';
        this.draftUploadToken = 0;
        this.backgroundImage = null;
        this.backgroundImageUrl = '';
        this.focusedPlacementIndex = -1;
        this.activePopoverPlacementId = '';
        this.popoverHovered = false;
        this.popoverPinned = false;
        this.interactionFocused = false;
        this.interactionObserver = null;
        this.wheelZoomInteractions = [];
        this.imageCache = {};
        this.useOpenLayers = false;
        this.map = null;
        this.fitControl = null;
        this.remoteTileSource = null;
        this.olSource = null;
        this.metrics = null;
        this.resizeObserver = null;
        this.resizeFrame = 0;
        this.lastResponsiveHeight = 0;
        this.element.mds3Grid = this;
        this.load();
    }

    Grid.prototype.load = function () {
        var self = this;
        post('mds3_grid_state', {
            grid_id: this.gridId,
            nonce: gridConfig.nonce
        }).then(function (payload) {
            if (!payload || !payload.success) {
                /* 403 here means the page nonce expired (long idle); a fresh page
                   load mints a new nonce. Reload once; the flag guards against a
                   loop and is cleared on a successful load. */
                if (payload && payload.mds3HttpStatus === 403 && reloadForExpiredSession()) {
                    return;
                }
                throw new Error('load failed');
            }
            try {
                window.sessionStorage.removeItem(GRID_SESSION_RELOAD_KEY);
            } catch (error) {}
            self.state = payload.data;
            self.updateResponsiveViewport();
            self.updateInlineStats();
            self.populatePackages();
            self.updateTools();
            self.updateSelectionSizeControl();
            self.updateCustomerControls();
            self.bindControls();
            self.preloadBackgroundImage();
            self.preloadPlacementImages();
            self.initViewer();
            self.redraw();
            self.updateRestoreBar();
        }).catch(function () {
            self.element.innerHTML = '<div class="mds3-grid-error">' + gridConfig.i18n.error + '</div>';
        });
    };

    Grid.prototype.bindControls = function () {
        var self = this;

        if (this.bound) {
            return;
        }
        this.bound = true;

        window.addEventListener('resize', function () {
            self.scheduleResize();
        });

        if (window.ResizeObserver && this.element) {
            this.resizeObserver = new window.ResizeObserver(function () {
                self.scheduleResize();
            });
            this.resizeObserver.observe(this.element);
        }

        if (this.popover) {
            this.portalPopover();
            this.popover.addEventListener('mouseenter', function () {
                self.popoverHovered = true;
            });
            this.popover.addEventListener('mouseleave', function () {
                self.popoverHovered = false;
                if (!self.popoverPinned) {
                    self.hidePopover();
                }
            });
        }

        if (this.viewport) {
            this.viewport.addEventListener('pointerdown', function () {
                self.setInteractionFocused(true);
            });
            this.viewport.addEventListener('focusin', function () {
                self.setInteractionFocused(true);
            });
            this.viewport.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    self.setInteractionFocused(false);
                    if (self.mapElement && typeof self.mapElement.blur === 'function') {
                        self.mapElement.blur();
                    }
                }
            });

            if (window.IntersectionObserver) {
                this.interactionObserver = new window.IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.target === self.viewport && !entry.isIntersecting) {
                            self.setInteractionFocused(false);
                        }
                    });
                }, { threshold: 0 });
                this.interactionObserver.observe(this.viewport);
            }
        }

        document.addEventListener('pointerdown', function (event) {
            if (!self.popover || self.popover.hidden) {
                return;
            }
            if (self.popover.contains(event.target)) {
                return;
            }
            if (self.element.contains(event.target)) {
                return;
            }
            self.hidePopover();
        });

        if (this.viewAllButton) {
            this.viewAllButton.addEventListener('click', function () {
                self.fitGrid();
            });
        }

        if (this.viewImagesButton) {
            this.viewImagesButton.addEventListener('click', function () {
                self.fitNextPlacement();
            });
        }

        if (this.selectionMessage) {
            this.selectionMessage.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                self.hideSelectionMessage();
            });
        }

        if (this.selectionSizeButton) {
            this.selectionSizeButton.addEventListener('click', function () {
                self.openSelectionSizeDialog();
            });
        }

        if (this.customerEmail) {
            this.customerEmail.addEventListener('input', function () {
                self.updateSelectionValidity();
            });
            this.customerEmail.addEventListener('change', function () {
                self.updateSelectionValidity();
            });
        }

        if (this.canvas) {
            this.canvas.addEventListener('click', function (event) {
                self.handleCanvasClick(event);
            });

            this.canvas.addEventListener('mousemove', function (event) {
                self.updateCanvasCursor(event);
                self.updateCanvasPopover(event);
            });

            this.canvas.addEventListener('mouseleave', function (event) {
                if (!self.eventMovingIntoPopover(event) && !self.popoverPinned) {
                    self.hidePopover();
                }
            });
        }

        if (!this.readOnly) {
            this.element.querySelector('.mds3-clear').addEventListener('click', function () {
                self.selected = [];
                self.setStatus('');
                self.hideSelectionMessage();
                self.redraw();
            });

            if (this.reserveButton) {
                this.reserveButton.disabled = true;
                this.reserveButton.addEventListener('click', function () {
                    self.reserve();
                });
            }

            if (this.packageSelect) {
                this.packageSelect.addEventListener('change', function () {
                    self.updateSubscriptionPlans();
                    self.updateActions();
                });
            }
            if (this.subscriptionPlanSelect) {
                this.subscriptionPlanSelect.addEventListener('change', function () {
                    self.updateActions();
                });
                this.updateSubscriptionPlans();
            }

            if (this.form) {
                bindAdvertiserUrlInputs(this.form);
                initializeRichTextEditors(this.form);
                this.ensureUploadValidity();
                this.form.addEventListener('input', function () {
                    self.updateUploadValidity(false);
                });
                this.form.addEventListener('change', function () {
                    self.updateUploadValidity(false);
                });
                this.form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    self.submitPlacement();
                });
            }

            window.addEventListener('beforeunload', function () {
                if (self.form && !self.form.hidden) {
                    saveFormDraft(self.form);
                }
                if (self.currentOrder && self.currentOrderKey) {
                    saveActiveGridOrder(self.gridId, self.currentOrder, self.currentOrderKey, {
                        checkout_url: self.checkoutUrl,
                        after_upload_url: self.afterUploadUrl
                    }, self.currentPlacementRect, self.currentPlacementMask);
                }
            });
        }
    };

    Grid.prototype.updateTools = function () {
        if (this.viewImagesButton) {
            this.viewImagesButton.hidden = !this.hasPlacements();
        }
    };

    Grid.prototype.updateInlineStats = function () {
        var stats = this.state && this.state.stats ? this.state.stats : null;
        var unit;
        var formatNumber = function (value) {
            value = Number(value || 0);
            if (window.Intl && window.Intl.NumberFormat) {
                try {
                    return new window.Intl.NumberFormat().format(value);
                } catch (error) {}
            }

            return String(Math.round(value));
        };

        if (!this.inlineStats) {
            return;
        }

        if (!stats || stats.visible === false || (stats.grid_id && Number(stats.grid_id) !== Number(this.gridId || 0))) {
            this.inlineStats.hidden = true;
            return;
        }

        this.inlineStats.hidden = false;
        this.inlineStats.setAttribute('data-mds3-grid-stats', String(this.gridId || ''));

        unit = this.inlineStats.querySelector('[data-mds3-stat-unit]');
        if (unit) {
            unit.textContent = stats.unit_label || '';
        }

        this.inlineStats.querySelectorAll('[data-mds3-stat]').forEach(function (field) {
            var key = field.getAttribute('data-mds3-stat');
            field.textContent = formatNumber(stats[key]);
        });
    };

    Grid.prototype.hasPlacements = function () {
        return !!(this.state && this.state.placements && this.state.placements.length);
    };

    Grid.prototype.imagePlacements = function () {
        var placements = (this.state && this.state.placements) ? this.state.placements.slice() : [];

        return placements.sort(function (a, b) {
            var sourceA = a.source || {};
            var sourceB = b.source || {};
            var mpA = Number(sourceA.megapixels || 0);
            var mpB = Number(sourceB.megapixels || 0);
            var areaA = Number(a.width || 0) * Number(a.height || 0);
            var areaB = Number(b.width || 0) * Number(b.height || 0);

            if (mpA !== mpB) {
                return mpB - mpA;
            }

            if (areaA !== areaB) {
                return areaB - areaA;
            }

            return Number(a.id || 0) - Number(b.id || 0);
        });
    };

    Grid.prototype.populatePackages = function () {
        if (!this.packageSelect) {
            return;
        }

        var packages = this.state && Array.isArray(this.state.packages) ? this.state.packages : [];
        this.packageSelect.innerHTML = '';
        if (!packages.length) {
            this.packageSelect.hidden = true;
            this.updateSubscriptionPlans();
            return;
        }

        var defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = gridConfig.i18n.defaultPricing;
        this.packageSelect.appendChild(defaultOption);

        packages.forEach(function (pkg) {
            var option = document.createElement('option');
            option.value = pkg.id;
            option.textContent = pkg.title + (Number(pkg.price) > 0 ? ' (' + pkg.currency + ' ' + Number(pkg.price).toFixed(2) + ')' : '');
            if (pkg.is_default) {
                option.selected = true;
            }
            this.packageSelect.appendChild(option);
        }, this);

        this.packageSelect.hidden = false;
        this.updateSubscriptionPlans();
    };

    Grid.prototype.initViewer = function () {
        if (this.viewerReady) {
            return;
        }
        this.viewerReady = true;

        if (this.rendererMode !== 'classic' && canUseOpenLayers() && this.mapElement) {
            this.initOpenLayers();
            return;
        }

        this.element.classList.remove('mds3-openlayers');
    };

    Grid.prototype.initOpenLayers = function () {
        var self = this;
        var grid = this.state.grid;
        var extent = this.gridExtent();
        var layers = [];
        var projection = new window.ol.proj.Projection({
            code: 'MDS3:' + grid.id,
            extent: extent,
            units: 'pixels'
        });

        this.useOpenLayers = true;
        this.element.classList.add('mds3-openlayers');
        this.element.classList.toggle('mds3-remote-tiles', this.hasRemoteTiles());

        this.remoteTileSource = this.createRemoteTileSource(projection, extent);
        if (this.remoteTileSource) {
            layers.push(new window.ol.layer.Tile({
                source: this.remoteTileSource
            }));
        }

        this.olSource = new window.ol.source.ImageCanvas({
            projection: projection,
            ratio: 1.15,
            canvasFunction: function (canvasExtent, resolution, pixelRatio, size) {
                return self.openLayersCanvas(canvasExtent, resolution, pixelRatio, size);
            }
        });
        layers.push(new window.ol.layer.Image({
            source: this.olSource
        }));

        this.map = new window.ol.Map({
            target: this.mapElement,
            layers: layers,
            view: new window.ol.View({
                projection: projection,
                center: [grid.width / 2, -grid.height / 2],
                zoom: 0,
                minZoom: -8,
                maxZoom: 12
            })
        });
        this.captureWheelZoomInteractions();
        this.addFitControl();
        this.applyMapThemeOverrides();

        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                self.applyMapThemeOverrides();
                self.fitInitialView();
            });
        });

        this.map.on('singleclick', function (event) {
            var placement = self.placementAt(event.coordinate[0], -event.coordinate[1]);
            if (placement) {
                self.activatePlacement(placement, event.originalEvent);
                return;
            }

            self.hidePopover();
            if (!self.readOnly) {
                self.toggleAt(event.coordinate[0], -event.coordinate[1], event.originalEvent);
            }
        });

        this.map.on('pointermove', function (event) {
            if (event.dragging) {
                return;
            }

            var placement = self.placementAt(event.coordinate[0], -event.coordinate[1]);
            self.mapElement.style.cursor = placement ? 'pointer' : (self.readOnly ? '' : 'crosshair');
            if (!self.popoversEnabled() || self.popoverTrigger() === 'click') {
                return;
            }

            if (placement && event.originalEvent) {
                self.showPopover(placement, event.originalEvent.clientX, event.originalEvent.clientY, { pinned: false });
            }
        });

        this.mapElement.addEventListener('mouseleave', function (event) {
            if (!self.eventMovingIntoPopover(event) && !self.popoverPinned) {
                self.hidePopover();
            }
        });
    };

    Grid.prototype.captureWheelZoomInteractions = function () {
        var self = this;
        var MouseWheelZoom = window.ol && window.ol.interaction ? window.ol.interaction.MouseWheelZoom : null;

        this.wheelZoomInteractions = [];
        if (!this.map || !MouseWheelZoom || !this.map.getInteractions) {
            return;
        }

        this.map.getInteractions().forEach(function (interaction) {
            if (interaction instanceof MouseWheelZoom) {
                interaction.setActive(false);
                self.wheelZoomInteractions.push(interaction);
            }
        });
    };

    Grid.prototype.setInteractionFocused = function (active) {
        var hint = this.element.querySelector('.mds3-grid-interaction-hint');

        this.interactionFocused = !!active;
        if (this.viewport) {
            this.viewport.classList.toggle('mds3-grid-interaction-active', this.interactionFocused);
        }
        this.wheelZoomInteractions.forEach(function (interaction) {
            interaction.setActive(this.interactionFocused);
        }, this);
        if (hint) {
            hint.classList.toggle('is-active', this.interactionFocused);
            hint.textContent = this.interactionFocused
                ? i18n('wheelZoomActive', 'Wheel zoom is active. Press Escape to return to page scrolling.')
                : i18n('wheelZoomInactive', 'Click the grid to enable wheel zoom.');
        }
    };

    Grid.prototype.portalPopover = function () {
        var styles;
        var properties = [
            '--mds3-grid-popover-bg',
            '--mds3-grid-popover-border',
            '--mds3-grid-popover-text',
            '--mds3-grid-popover-muted',
            '--mds3-grid-popover-link',
            '--mds3-grid-popover-link-hover',
            '--mds3-grid-popover-shadow'
        ];

        if (!this.popover || this.popover.parentNode === document.body) {
            return;
        }

        styles = window.getComputedStyle(this.element);
        properties.forEach(function (property) {
            var value = styles.getPropertyValue(property);
            if (value) {
                this.popover.style.setProperty(property, value.trim());
            }
        }, this);
        this.popover.classList.add('mds3-grid-popover-portaled');
        document.body.appendChild(this.popover);
    };

    Grid.prototype.gridExtent = function () {
        var grid = this.state ? this.state.grid : {};

        return [0, -Number(grid.height || 0), Number(grid.width || 0), 0];
    };

    Grid.prototype.paddedGridExtent = function () {
        var grid = this.state ? this.state.grid : {};
        var width = Math.max(1, Number(grid.width || 0));
        var height = Math.max(1, Number(grid.height || 0));
        var blockSize = Math.max(Number(grid.block_width || 1), Number(grid.block_height || 1));
        var padding = Math.max(blockSize * 2, Math.min(width, height) * 0.02);

        return [-padding, -height - padding, width + padding, padding];
    };

    Grid.prototype.addFitControl = function () {
        var self = this;
        var element;
        var button;

        if (!this.map || this.fitControl || !window.ol || !window.ol.control || !window.ol.control.Control) {
            return;
        }

        element = document.createElement('div');
        element.className = 'mds3-fit-grid-control ol-unselectable ol-control';

        button = document.createElement('button');
        button.type = 'button';
        button.title = i18n('fitGrid', 'Fit grid');
        button.setAttribute('aria-label', i18n('fitGrid', 'Fit grid'));
        button.setAttribute('data-tooltip', i18n('fitGrid', 'Fit grid'));
        button.innerHTML = '<span class="mds3-fit-grid-icon" aria-hidden="true"></span>';
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            self.fitGrid();
        });

        element.appendChild(button);
        this.map.addControl(new window.ol.control.Control({ element: element }));
        this.fitControl = element;
    };

    Grid.prototype.hasRemoteTiles = function () {
        var tile = this.state && this.state.tile ? this.state.tile : {};
        var coverage = tile.cacheCoverage || {};

        if (this.backgroundImageConfig()) {
            return false;
        }

        if (tile.directMode === 'cache' && coverage.complete !== true) {
            return false;
        }

        return !!(tile.remote && tile.url && canUseOpenLayersTiles());
    };

    Grid.prototype.backgroundImageConfig = function () {
        var background = this.state && this.state.grid && this.state.grid.background_image;

        return background && background.url ? background : null;
    };

    Grid.prototype.preloadBackgroundImage = function () {
        var self = this;
        var background = this.backgroundImageConfig();
        var image;

        if (!background) {
            this.backgroundImage = null;
            this.backgroundImageUrl = '';
            return;
        }
        if (this.backgroundImage && this.backgroundImageUrl === background.url) {
            return;
        }

        image = new Image();
        this.backgroundImage = image;
        this.backgroundImageUrl = background.url;
        image.onload = function () {
            if (self.olSource && self.olSource.changed) {
                self.olSource.changed();
            }
            self.redraw();
        };
        image.onerror = function () {
            if (self.backgroundImage === image) {
                self.backgroundImage = null;
            }
            self.redraw();
        };
        image.src = background.url;
    };

    Grid.prototype.createRemoteTileSource = function (projection, extent) {
        var tile = this.state && this.state.tile ? this.state.tile : {};
        var grid = this.state ? this.state.grid : {};
        var tileSize = Math.max(64, Number(tile.tileSize || 256));
        var maxDimension = Math.max(Number(grid.width || 0), Number(grid.height || 0));
        var maxLevel = Math.max(0, Number(tile.maxLevel || 0));
        var minLevel = Math.max(0, Number(tile.minLevel || 0));
        var levelScheme = tile.levelScheme === 'deepzoom' ? 'deepzoom' : 'normalized';
        var highestLevelResolution;
        var resolutions = [];
        var url = tile.url || '';
        var fallbackUrl = tile.fallbackUrl || '';
        var cacheKey = tile.cacheKey || '';
        var format = tile.format || 'png';
        var directMode = tile.directMode || '';
        var coverage = tile.cacheCoverage || {};
        var completeLevels = {};
        var cachedTiles = {};
        var tileRanges = {};

        (coverage.completeLevels || []).forEach(function (level) {
            completeLevels[String(level)] = true;
        });
        (coverage.tiles || []).forEach(function (key) {
            cachedTiles[String(key)] = true;
        });

        if (!this.hasRemoteTiles()) {
            return null;
        }

        if (!maxLevel && maxDimension > 1) {
            maxLevel = levelScheme === 'deepzoom'
                ? Math.max(0, Math.ceil(Math.log(maxDimension) / Math.log(2)))
                : Math.max(0, Math.ceil(Math.log(maxDimension / tileSize) / Math.log(2)));
        }
        minLevel = Math.min(minLevel, maxLevel);
        highestLevelResolution = levelScheme === 'deepzoom'
            ? 1
            : (maxDimension > 0 ? maxDimension / (tileSize * Math.pow(2, maxLevel)) : 1);
        highestLevelResolution = Number.isFinite(highestLevelResolution) && highestLevelResolution > 0 ? highestLevelResolution : 1;

        for (var level = minLevel; level <= maxLevel; level++) {
            resolutions.push(highestLevelResolution * Math.pow(2, maxLevel - level));
        }

        function tileRangeForLevel(level) {
            var key = String(level);
            var resolution;
            var worldTileSize;
            if (tileRanges[key]) {
                return tileRanges[key];
            }

            resolution = highestLevelResolution * Math.pow(2, maxLevel - level);
            worldTileSize = Math.max(1, tileSize * resolution);
            tileRanges[key] = {
                columns: Math.max(1, Math.ceil(Number(grid.width || 0) / worldTileSize)),
                rows: Math.max(1, Math.ceil(Number(grid.height || 0) / worldTileSize))
            };

            return tileRanges[key];
        }

        return new window.ol.source.XYZ({
            interpolate: false,
            projection: projection,
            tileGrid: new window.ol.tilegrid.TileGrid({
                extent: extent,
                origin: [0, 0],
                resolutions: resolutions,
                tileSize: tileSize
            }),
            transition: 0,
            wrapX: false,
            tileUrlFunction: function (tileCoord) {
                var level;
                var tileKey;
                var range;
                if (!tileCoord || tileCoord[1] < 0 || tileCoord[2] < 0) {
                    return undefined;
                }

                level = minLevel + tileCoord[0];
                if (level < minLevel || level > maxLevel) {
                    return undefined;
                }

                range = tileRangeForLevel(level);
                if (tileCoord[1] >= range.columns || tileCoord[2] >= range.rows) {
                    return undefined;
                }

                if (directMode === 'cache') {
                    tileKey = level + '/' + tileCoord[1] + '/' + tileCoord[2];
                    if (completeLevels[String(level)] || cachedTiles[tileKey]) {
                        return buildTileUrl(url, level, tileCoord[1], tileCoord[2], cacheKey, format);
                    }
                    if (fallbackUrl && coverage.allowFallback === true) {
                        return buildTileUrl(fallbackUrl, level, tileCoord[1], tileCoord[2], cacheKey, format);
                    }
                    return undefined;
                }

                return buildTileUrl(url, level, tileCoord[1], tileCoord[2], cacheKey, format);
            },
            tileLoadFunction: function (imageTile, src) {
                var image = imageTile.getImage();
                image.src = src;
            }
        });
    };

    Grid.prototype.applyMapThemeOverrides = function () {
        if (!this.mapElement || !this.element.classList.contains('mds3-theme-light')) {
            return;
        }

        var background = themeVariable(this.element, '--mds3-grid-bg', '#ffffff');
        var line = themeVariable(this.element, '--mds3-grid-line-soft', '#e5e7eb');
        var text = themeVariable(this.element, '--mds3-grid-text', '#111827');

        this.mapElement.querySelectorAll('.ol-control, .ol-zoom').forEach(function (control) {
            control.classList.add('wp-dark-mode-ignore');
            control.style.setProperty('background', 'rgba(255, 255, 255, 0.92)', 'important');
            control.style.setProperty('background-color', 'rgba(255, 255, 255, 0.92)', 'important');
            control.style.setProperty('border-color', line, 'important');
            control.style.setProperty('box-shadow', '0 12px 24px rgba(15, 23, 42, 0.12)', 'important');
        });

        this.mapElement.querySelectorAll('.ol-control button').forEach(function (button) {
            button.classList.add('wp-dark-mode-ignore');
            button.style.setProperty('background', background, 'important');
            button.style.setProperty('background-color', background, 'important');
            button.style.setProperty('border-color', '#cbd5e1', 'important');
            button.style.setProperty('color', text, 'important');
            button.style.setProperty('text-shadow', 'none', 'important');
        });
    };

    Grid.prototype.shouldUseResponsiveHeight = function () {
        return this.element && this.element.getAttribute('data-responsive-height') === 'true';
    };

    Grid.prototype.updateResponsiveViewport = function () {
        if (!this.shouldUseResponsiveHeight() || !this.viewport || !this.state || !this.state.grid) {
            return false;
        }

        var grid = this.state.grid;
        var gridWidth = Math.max(1, Number(grid.width || 0));
        var gridHeight = Math.max(1, Number(grid.height || 0));
        var viewportWidth = Math.max(1, this.viewport.getBoundingClientRect().width || this.element.clientWidth || 0);
        var targetHeight = Math.round(Math.min(gridHeight, viewportWidth * (gridHeight / gridWidth)));

        if (!Number.isFinite(targetHeight) || targetHeight <= 0) {
            return false;
        }

        if (Math.abs(targetHeight - this.lastResponsiveHeight) < 1) {
            return false;
        }

        this.lastResponsiveHeight = targetHeight;
        this.element.style.setProperty('--mds3-grid-responsive-height', targetHeight + 'px');

        return true;
    };

    Grid.prototype.scheduleResize = function () {
        var self = this;

        if (this.resizeFrame) {
            return;
        }

        this.resizeFrame = window.requestAnimationFrame(function () {
            self.resizeFrame = 0;
            self.updateResponsiveViewport();
            if (self.map) {
                self.map.updateSize();
            }
            self.redraw();
        });
    };

    Grid.prototype.fitGrid = function () {
        var size;
        if (!this.map || !this.state) {
            return;
        }

        this.updateResponsiveViewport();
        this.map.updateSize();
        size = this.mapViewportSize();
        this.map.getView().fit(this.gridExtent(), {
            nearest: false,
            padding: [0, 0, 0, 0],
            size: size
        });
    };

    Grid.prototype.mapViewportSize = function () {
        var rect;
        if (this.viewport) {
            rect = this.viewport.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) {
                return [Math.max(1, Math.floor(rect.width)), Math.max(1, Math.floor(rect.height))];
            }
        }

        return this.map && this.map.getSize ? this.map.getSize() : undefined;
    };

    Grid.prototype.fitInitialView = function () {
        if (!this.map || !this.state) {
            return;
        }

        this.updateResponsiveViewport();
        this.map.updateSize();
        this.fitGrid();
    };

    Grid.prototype.fitPlacementOverview = function () {
        if (!this.map || !this.state || !this.hasPlacements()) {
            return;
        }

        var grid = this.state.grid;
        var minX = grid.width;
        var minY = grid.height;
        var maxX = 0;
        var maxY = 0;

        this.state.placements.forEach(function (placement) {
            minX = Math.min(minX, placement.x);
            minY = Math.min(minY, placement.y);
            maxX = Math.max(maxX, placement.x + placement.width);
            maxY = Math.max(maxY, placement.y + placement.height);
        });

        var occupiedWidth = Math.max(1, maxX - minX);
        var occupiedHeight = Math.max(1, maxY - minY);
        var pad = Math.max(
            grid.block_width * 4,
            grid.block_height * 4,
            Math.min(grid.width, grid.height) * 0.04,
            Math.max(occupiedWidth, occupiedHeight) * 0.12
        );

        minX = Math.max(0, minX - pad);
        minY = Math.max(0, minY - pad);
        maxX = Math.min(grid.width, maxX + pad);
        maxY = Math.min(grid.height, maxY + pad);

        this.map.getView().fit([minX, -maxY, maxX, -minY], {
            padding: [24, 24, 24, 24],
            nearest: false,
            maxZoom: 7
        });
    };

    Grid.prototype.fitNextPlacement = function () {
        var placements = this.imagePlacements();
        if (!this.map || !this.state || !placements.length) {
            return;
        }

        this.focusedPlacementIndex = (this.focusedPlacementIndex + 1) % placements.length;
        var placement = placements[this.focusedPlacementIndex];
        var minX = placement.x;
        var minY = placement.y;
        var maxX = placement.x + placement.width;
        var maxY = placement.y + placement.height;

        var pad = Math.max(40, Math.max(maxX - minX, maxY - minY) * 4);
        var grid = this.state.grid;
        minX = Math.max(0, minX - pad);
        minY = Math.max(0, minY - pad);
        maxX = Math.min(grid.width, maxX + pad);
        maxY = Math.min(grid.height, maxY + pad);

        this.map.getView().fit([minX, -maxY, maxX, -minY], {
            padding: [24, 24, 24, 24],
            nearest: false,
            maxZoom: 8
        });
    };

    Grid.prototype.openLayersCanvas = function (extent, resolution, pixelRatio, size) {
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(size[0] * pixelRatio));
        canvas.height = Math.max(1, Math.round(size[1] * pixelRatio));
        canvas.style.width = size[0] + 'px';
        canvas.style.height = size[1] + 'px';

        var ctx = canvas.getContext('2d');
        ctx.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        this.renderScene(ctx, {
            width: size[0],
            height: size[1],
            scale: 1 / resolution,
            offsetX: -extent[0] / resolution,
            offsetY: extent[3] / resolution
        }, {
            drawBase: !this.hasRemoteTiles()
        });

        return canvas;
    };

    Grid.prototype.size = function () {
        var rect = this.canvas.parentElement.getBoundingClientRect();
        var ratio = window.devicePixelRatio || 1;
        this.canvas.width = Math.max(320, Math.floor(rect.width * ratio));
        this.canvas.height = Math.max(240, Math.floor(rect.height * ratio));
        this.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

        return { width: rect.width, height: rect.height };
    };

    Grid.prototype.preloadPlacementImages = function () {
        if (this.hasRemoteTiles()) {
            return;
        }

        (this.state.placements || []).forEach(function (placement) {
            this.preloadPlacementImage(placement);
        }, this);
        if (this.draftPlacement) {
            this.preloadPlacementImage(this.draftPlacement);
        }
    };

    Grid.prototype.placementDisplayRect = function (transform, placement, allowOverviewMagnify) {
        var scale = transform.scale;
        var grid = this.state.grid;
        var x = transform.offsetX + placement.x * scale;
        var y = transform.offsetY + placement.y * scale;
        var width = placement.width * scale;
        var height = placement.height * scale;
        var displayWidth = width;
        var displayHeight = height;

        if (allowOverviewMagnify && Math.min(width, height) < 18) {
            var multiplier = 18 / Math.max(1, Math.min(width, height));
            displayWidth = width * multiplier;
            displayHeight = height * multiplier;
        }

        var displayX = x + (width - displayWidth) / 2;
        var displayY = y + (height - displayHeight) / 2;
        var gridLeft = transform.offsetX;
        var gridTop = transform.offsetY;
        var gridRight = gridLeft + grid.width * scale;
        var gridBottom = gridTop + grid.height * scale;

        displayX = Math.min(Math.max(displayX, gridLeft), gridRight - displayWidth);
        displayY = Math.min(Math.max(displayY, gridTop), gridBottom - displayHeight);

        return {
            x: displayX,
            y: displayY,
            width: displayWidth,
            height: displayHeight,
            magnified: displayWidth !== width || displayHeight !== height
        };
    };

    Grid.prototype.placementMaskRects = function (transform, placement, rect) {
        var scale = transform.scale;
        var mask = Array.isArray(placement.mask) ? placement.mask : [];
        var rects = [];

        mask.forEach(function (block) {
            var width = Number(block.width || 0) * scale;
            var height = Number(block.height || 0) * scale;
            if (width <= 0 || height <= 0) {
                return;
            }

            rects.push({
                x: rect.x + (Number(block.x || 0) - Number(placement.x || 0)) * scale,
                y: rect.y + (Number(block.y || 0) - Number(placement.y || 0)) * scale,
                width: width,
                height: height
            });
        });

        return rects;
    };

    Grid.prototype.clipPlacement = function (ctx, transform, placement, rect) {
        var maskRects = this.placementMaskRects(transform, placement, rect);

        ctx.beginPath();
        if (maskRects.length) {
            maskRects.forEach(function (mask) {
                ctx.rect(mask.x, mask.y, mask.width, mask.height);
            });
        } else {
            ctx.rect(rect.x, rect.y, rect.width, rect.height);
        }
        ctx.clip();

        return maskRects;
    };

    Grid.prototype.drawPlacementPlaceholder = function (ctx, transform, placement, rect) {
        var maskRects;

        ctx.save();
        maskRects = this.clipPlacement(ctx, transform, placement, rect);
        ctx.fillStyle = '#f1f5f9';
        ctx.fillRect(rect.x, rect.y, rect.width, rect.height);
        ctx.strokeStyle = '#94a3b8';
        ctx.lineWidth = 1;
        if (maskRects.length) {
            maskRects.forEach(function (mask) {
                ctx.strokeRect(mask.x + 0.5, mask.y + 0.5, Math.max(1, mask.width - 1), Math.max(1, mask.height - 1));
            });
        } else {
            ctx.strokeRect(rect.x + 0.5, rect.y + 0.5, Math.max(1, rect.width - 1), Math.max(1, rect.height - 1));
            ctx.beginPath();
            ctx.moveTo(rect.x, rect.y + rect.height);
            ctx.lineTo(rect.x + rect.width, rect.y);
            ctx.stroke();
        }
        ctx.restore();
    };

    Grid.prototype.drawImagePlacement = function (ctx, transform, placement, options) {
        options = options || {};
        var url = placement.source && placement.source.url;
        var image = url ? this.imageCache[url] : null;
        var rect = this.placementDisplayRect(transform, placement, false);

        if (!image || !image.complete || !image.naturalWidth || !image.naturalHeight) {
            this.preloadPlacementImage(placement);
            if (options.placeholder !== false) {
                this.drawPlacementPlaceholder(ctx, transform, placement, rect);
            }
            return;
        }

        var sourceRatio = image.naturalWidth / image.naturalHeight;
        var targetRatio = rect.width / Math.max(1, rect.height);
        var sx = 0;
        var sy = 0;
        var sw = image.naturalWidth;
        var sh = image.naturalHeight;

        if (placement.fit_mode !== 'contain') {
            if (sourceRatio > targetRatio) {
                sw = image.naturalHeight * targetRatio;
                sx = (image.naturalWidth - sw) / 2;
            } else {
                sh = image.naturalWidth / targetRatio;
                sy = (image.naturalHeight - sh) / 2;
            }
        }

        ctx.save();
        this.clipPlacement(ctx, transform, placement, rect);

        if (placement.fit_mode === 'contain') {
            var containScale = Math.min(rect.width / image.naturalWidth, rect.height / image.naturalHeight);
            var dw = image.naturalWidth * containScale;
            var dh = image.naturalHeight * containScale;
            ctx.drawImage(image, rect.x + (rect.width - dw) / 2, rect.y + (rect.height - dh) / 2, dw, dh);
        } else {
            ctx.drawImage(image, sx, sy, sw, sh, rect.x, rect.y, rect.width, rect.height);
        }

        ctx.restore();

        if (rect.magnified) {
            ctx.save();
            ctx.strokeStyle = 'rgba(15, 23, 42, 0.36)';
            ctx.lineWidth = 1;
            ctx.strokeRect(rect.x + 0.5, rect.y + 0.5, Math.max(1, rect.width - 1), Math.max(1, rect.height - 1));
            ctx.restore();
        }
    };

    Grid.prototype.shouldDrawRemotePlacementOverlay = function (transform) {
        return this.hasRemoteTiles() && transform && transform.scale >= 0.75;
    };

    Grid.prototype.placementVisibleInTransform = function (transform, placement) {
        var rect = this.placementDisplayRect(transform, placement, false);

        return rect.x + rect.width >= 0 &&
            rect.y + rect.height >= 0 &&
            rect.x <= transform.width &&
            rect.y <= transform.height;
    };

    Grid.prototype.drawDraftPlacementOutline = function (ctx, transform, placement) {
        var rect = this.placementDisplayRect(transform, placement, false);
        var masks = this.placementMaskRects(transform, placement, rect);

        ctx.save();
        ctx.strokeStyle = '#2563eb';
        ctx.lineWidth = 2;
        ctx.setLineDash([8, 4]);
        if (masks.length) {
            masks.forEach(function (mask) {
                ctx.strokeRect(mask.x + 1, mask.y + 1, Math.max(1, mask.width - 2), Math.max(1, mask.height - 2));
            });
        } else {
            ctx.strokeRect(rect.x + 1, rect.y + 1, Math.max(1, rect.width - 2), Math.max(1, rect.height - 2));
        }
        ctx.restore();
    };

    Grid.prototype.pointInPlacement = function (placement, x, y) {
        var mask = Array.isArray(placement.mask) ? placement.mask : [];
        var point = this.normalizeGridPoint(x, y);

        if (!point) {
            return false;
        }

        x = point.x;
        y = point.y;

        if (mask.length) {
            return mask.some(function (block) {
                return x >= Number(block.x || 0) &&
                    y >= Number(block.y || 0) &&
                    x < Number(block.x || 0) + Number(block.width || 0) &&
                    y < Number(block.y || 0) + Number(block.height || 0);
            });
        }

        return x >= Number(placement.x || 0) &&
            y >= Number(placement.y || 0) &&
            x < Number(placement.x || 0) + Number(placement.width || 0) &&
            y < Number(placement.y || 0) + Number(placement.height || 0);
    };

    Grid.prototype.normalizeGridPoint = function (x, y) {
        var grid = this.state && this.state.grid ? this.state.grid : null;
        var width;
        var height;
        var toleranceX;
        var toleranceY;
        var insetX;
        var insetY;

        if (!grid) {
            return null;
        }

        width = Number(grid.width || 0);
        height = Number(grid.height || 0);
        toleranceX = Math.max(1, Number(grid.block_width || 1) / 2);
        toleranceY = Math.max(1, Number(grid.block_height || 1) / 2);
        insetX = Math.max(0.000001, Number(grid.block_width || 1) * 0.000001);
        insetY = Math.max(0.000001, Number(grid.block_height || 1) * 0.000001);

        if (!Number.isFinite(x) || !Number.isFinite(y) || width <= 0 || height <= 0) {
            return null;
        }

        if (x < -toleranceX || y < -toleranceY || x > width + toleranceX || y > height + toleranceY) {
            return null;
        }

        return {
            x: Math.max(0, Math.min(x, width - insetX)),
            y: Math.max(0, Math.min(y, height - insetY))
        };
    };

    Grid.prototype.blockHasPlacement = function (block) {
        return (this.state.placements || []).some(function (placement) {
            var blockCenterX = block.x + block.width / 2;
            var blockCenterY = block.y + block.height / 2;

            return this.pointInPlacement(placement, blockCenterX, blockCenterY);
        }, this);
    };

    Grid.prototype.renderScene = function (ctx, transform, options) {
        if (!this.state) {
            return;
        }

        var grid = this.state.grid;
        var drawBase = !options || options.drawBase !== false;
        var scale = transform.scale;
        var offsetX = transform.offsetX;
        var offsetY = transform.offsetY;
        var blockW = grid.block_width * scale;
        var blockH = grid.block_height * scale;
        var columns = grid.virtual_blocks.columns;
        var rows = grid.virtual_blocks.rows;
        var self = this;
        var drawRemoteGridOverlay = !drawBase && blockW >= 3 && blockH >= 3;

        ctx.clearRect(0, 0, transform.width, transform.height);
        if (drawBase) {
            ctx.fillStyle = '#f8fafc';
            ctx.fillRect(0, 0, transform.width, transform.height);
            ctx.fillStyle = themeVariable(this.element, '--mds3-grid-bg', '#ffffff');
            ctx.fillRect(offsetX, offsetY, grid.width * scale, grid.height * scale);
            this.drawGridBackgroundImage(ctx, transform);
            ctx.strokeStyle = '#94a3b8';
            ctx.lineWidth = 1;
            ctx.strokeRect(offsetX, offsetY, grid.width * scale, grid.height * scale);

            this.drawGridLines(ctx, transform, columns, rows, blockW, blockH);
        }

        this.drawPriceRules(ctx, transform);
        this.drawAvailabilityRegions(ctx, transform);

        (this.state.blocks || []).forEach(function (block) {
            if (self.readOnly) {
                return;
            }

            if (block.status === 'available') {
                return;
            }

            if (block.status === 'sold') {
                return;
            }

            ctx.fillStyle = block.status === 'reserved' ? 'rgba(217, 119, 6, 0.26)' : 'rgba(75, 85, 99, 0.34)';
            ctx.fillRect(offsetX + block.x * scale, offsetY + block.y * scale, Math.max(2, block.width * scale), Math.max(2, block.height * scale));
        });

        if (drawBase) {
            (this.state.placements || []).forEach(function (placement) {
                self.drawImagePlacement(ctx, transform, placement);
            });
        } else if (this.shouldDrawRemotePlacementOverlay(transform)) {
            (this.state.placements || []).forEach(function (placement) {
                if (self.placementVisibleInTransform(transform, placement)) {
                    self.drawImagePlacement(ctx, transform, placement, { placeholder: false });
                }
            });
        }

        if (this.draftPlacement) {
            this.drawImagePlacement(ctx, transform, this.draftPlacement);
            this.drawDraftPlacementOutline(ctx, transform, this.draftPlacement);
        }

        (this.state.blocks || []).forEach(function (block) {
            if (self.readOnly) {
                return;
            }

            if (block.status === 'available') {
                return;
            }

            var x = offsetX + block.x * scale;
            var y = offsetY + block.y * scale;
            var width = Math.max(2, block.width * scale);
            var height = Math.max(2, block.height * scale);
            var hasPlacement = self.blockHasPlacement(block);

            if (block.status === 'sold' && hasPlacement) {
                return;
            }

            if (block.status === 'sold' && !hasPlacement && (width < 10 || height < 10)) {
                return;
            }

            ctx.save();
            ctx.lineWidth = Math.max(1, Math.min(3, scale * 2));
            if (block.status === 'sold' && !hasPlacement) {
                ctx.setLineDash([3, 2]);
            }
            ctx.strokeStyle = block.status === 'reserved' ? '#d97706' : (block.status === 'sold' ? (hasPlacement ? 'rgba(37, 99, 235, 0.56)' : '#64748b') : '#4b5563');
            ctx.strokeRect(x + 0.5, y + 0.5, Math.max(1, width - 1), Math.max(1, height - 1));
            if (block.status === 'unavailable' && width >= 6 && height >= 6) {
                ctx.beginPath();
                ctx.moveTo(x, y);
                ctx.lineTo(x + width, y + height);
                ctx.moveTo(x + width, y);
                ctx.lineTo(x, y + height);
                ctx.stroke();
            }
            ctx.restore();
        });

        if (drawRemoteGridOverlay) {
            ctx.save();
            ctx.strokeStyle = 'rgba(15, 23, 42, 0.36)';
            ctx.lineWidth = 1;
            ctx.strokeRect(offsetX + 0.5, offsetY + 0.5, Math.max(1, grid.width * scale - 1), Math.max(1, grid.height * scale - 1));
            ctx.restore();
            this.drawGridLines(ctx, transform, columns, rows, blockW, blockH, {
                strokeStyle: 'rgba(15, 23, 42, 0.22)'
            });
        }

        if (!this.readOnly) {
            ctx.fillStyle = 'rgba(37, 99, 235, 0.45)';
            this.selected.forEach(function (coord) {
                ctx.fillRect(offsetX + coord.col * blockW, offsetY + coord.row * blockH, Math.max(2, blockW), Math.max(2, blockH));
            });
        }

        this.updateActions();
    };

    Grid.prototype.drawGridBackgroundImage = function (ctx, transform) {
        var background = this.backgroundImageConfig();
        var image = this.backgroundImage;
        var grid = this.state && this.state.grid ? this.state.grid : {};
        var imageWidth;
        var imageHeight;
        var tileWidth;
        var tileHeight;
        var position;
        var gridX;
        var gridY;
        var drawX;
        var drawY;
        var repeat;
        var pattern;
        var matrix;

        if (!background || !image || !image.complete || !image.naturalWidth || !image.naturalHeight) {
            return;
        }

        imageWidth = image.naturalWidth;
        imageHeight = image.naturalHeight;
        tileWidth = imageWidth;
        tileHeight = imageHeight;
        if (background.fit === 'stretch') {
            tileWidth = Number(grid.width || 1);
            tileHeight = Number(grid.height || 1);
        } else if (background.fit === 'cover' || background.fit === 'contain') {
            var fitScale = background.fit === 'cover'
                ? Math.max(Number(grid.width || 1) / imageWidth, Number(grid.height || 1) / imageHeight)
                : Math.min(Number(grid.width || 1) / imageWidth, Number(grid.height || 1) / imageHeight);
            tileWidth = imageWidth * fitScale;
            tileHeight = imageHeight * fitScale;
        }

        position = String(background.position || 'center');
        gridX = position.indexOf('left') !== -1 ? 0 : (position.indexOf('right') !== -1 ? Number(grid.width || 1) - tileWidth : (Number(grid.width || 1) - tileWidth) / 2);
        gridY = position.indexOf('top') !== -1 ? 0 : (position.indexOf('bottom') !== -1 ? Number(grid.height || 1) - tileHeight : (Number(grid.height || 1) - tileHeight) / 2);
        drawX = transform.offsetX + gridX * transform.scale;
        drawY = transform.offsetY + gridY * transform.scale;
        repeat = ['repeat', 'repeat-x', 'repeat-y'].indexOf(background.repeat) !== -1 ? background.repeat : 'no-repeat';

        ctx.save();
        ctx.beginPath();
        ctx.rect(
            transform.offsetX,
            transform.offsetY,
            Number(grid.width || 1) * transform.scale,
            Number(grid.height || 1) * transform.scale
        );
        ctx.clip();
        ctx.globalAlpha = Math.max(0, Math.min(100, Number(background.opacity || 0))) / 100;

        if (repeat !== 'no-repeat' && typeof ctx.createPattern === 'function') {
            pattern = ctx.createPattern(image, repeat);
            if (pattern && typeof pattern.setTransform === 'function' && typeof window.DOMMatrix === 'function') {
                matrix = new window.DOMMatrix();
                matrix = matrix.translate(drawX, drawY).scale(
                    tileWidth * transform.scale / imageWidth,
                    tileHeight * transform.scale / imageHeight
                );
                pattern.setTransform(matrix);
                ctx.fillStyle = pattern;
                ctx.fillRect(
                    transform.offsetX,
                    transform.offsetY,
                    Number(grid.width || 1) * transform.scale,
                    Number(grid.height || 1) * transform.scale
                );
                ctx.restore();
                return;
            }
        }

        ctx.drawImage(
            image,
            drawX,
            drawY,
            tileWidth * transform.scale,
            tileHeight * transform.scale
        );
        ctx.restore();
    };

    Grid.prototype.drawGridLines = function (ctx, transform, columns, rows, blockW, blockH, options) {
        options = options || {};
        var offsetX = transform.offsetX;
        var offsetY = transform.offsetY;
        var grid = this.state.grid;
        var scale = transform.scale;
        var startCol;
        var endCol;
        var startRow;
        var endRow;
        var c;
        var r;

        if (blockW < 3 || blockH < 3) {
            return;
        }

        startCol = Math.max(1, Math.floor((0 - offsetX) / blockW));
        endCol = Math.min(columns - 1, Math.ceil((transform.width - offsetX) / blockW));
        startRow = Math.max(1, Math.floor((0 - offsetY) / blockH));
        endRow = Math.min(rows - 1, Math.ceil((transform.height - offsetY) / blockH));

        if (endCol < startCol && endRow < startRow) {
            return;
        }

        ctx.save();
        ctx.strokeStyle = options.strokeStyle || '#e5e7eb';
        ctx.lineWidth = 1;
        ctx.beginPath();
        for (c = startCol; c <= endCol; c++) {
            ctx.moveTo(offsetX + c * blockW, offsetY);
            ctx.lineTo(offsetX + c * blockW, offsetY + grid.height * scale);
        }
        for (r = startRow; r <= endRow; r++) {
            ctx.moveTo(offsetX, offsetY + r * blockH);
            ctx.lineTo(offsetX + grid.width * scale, offsetY + r * blockH);
        }
        ctx.stroke();
        ctx.restore();
    };

    Grid.prototype.drawAvailabilityRegions = function (ctx, transform) {
        if (this.readOnly || !this.state || !Array.isArray(this.state.availabilityRegions)) {
            return;
        }

        var grid = this.state.grid;
        var scale = transform.scale;
        var offsetX = transform.offsetX;
        var offsetY = transform.offsetY;
        var blockW = grid.block_width * scale;
        var blockH = grid.block_height * scale;

        ctx.save();
        ctx.fillStyle = 'rgba(75, 85, 99, 0.28)';
        ctx.strokeStyle = '#4b5563';
        ctx.lineWidth = Math.max(1, Math.min(3, scale * 2));
        this.state.availabilityRegions.forEach(function (region) {
            var rowFrom = Number(region.row_from || 0);
            var rowTo = Number(region.row_to || rowFrom);
            var colFrom = Number(region.col_from || 0);
            var colTo = Number(region.col_to || colFrom);
            var x = offsetX + colFrom * blockW;
            var y = offsetY + rowFrom * blockH;
            var width = Math.max(1, (colTo - colFrom + 1) * blockW);
            var height = Math.max(1, (rowTo - rowFrom + 1) * blockH);

            ctx.fillRect(x, y, width, height);
            ctx.strokeRect(x + 0.5, y + 0.5, Math.max(1, width - 1), Math.max(1, height - 1));
        });
        ctx.restore();
    };

    Grid.prototype.drawPriceRules = function (ctx, transform) {
        if (this.readOnly || !this.state || !Array.isArray(this.state.priceRules)) {
            return;
        }

        var grid = this.state.grid;
        var scale = transform.scale;
        var offsetX = transform.offsetX;
        var offsetY = transform.offsetY;
        var blockW = grid.block_width * scale;
        var blockH = grid.block_height * scale;
        var columns = grid.virtual_blocks.columns;

        ctx.save();
        this.state.priceRules.forEach(function (rule) {
            if (rule.status !== 'active') {
                return;
            }

            var bounds = this.priceRuleBounds(rule, columns);
            if (!bounds) {
                return;
            }

            ctx.globalAlpha = 0.18;
            ctx.fillStyle = rule.color || '#2563eb';
            ctx.fillRect(
                offsetX + bounds.col_from * blockW,
                offsetY + bounds.row_from * blockH,
                Math.max(1, (bounds.col_to - bounds.col_from + 1) * blockW),
                Math.max(1, (bounds.row_to - bounds.row_from + 1) * blockH)
            );
            ctx.globalAlpha = 1;
            ctx.strokeStyle = rule.color || 'rgba(37, 99, 235, 0.58)';
            ctx.lineWidth = Math.max(1, Math.min(2, scale));
            ctx.strokeRect(
                offsetX + bounds.col_from * blockW + 0.5,
                offsetY + bounds.row_from * blockH + 0.5,
                Math.max(1, (bounds.col_to - bounds.col_from + 1) * blockW - 1),
                Math.max(1, (bounds.row_to - bounds.row_from + 1) * blockH - 1)
            );
        }, this);
        ctx.restore();
    };

    Grid.prototype.priceRuleBounds = function (rule, columns) {
        var grid = this.state.grid;
        var rows = grid.virtual_blocks.rows;
        var hasCoords = rule.row_from !== null || rule.row_to !== null || rule.col_from !== null || rule.col_to !== null;
        var from;
        var to;

        if (hasCoords) {
            return {
                row_from: Math.max(0, Number(rule.row_from === null ? 0 : rule.row_from)),
                row_to: Math.min(rows - 1, Number(rule.row_to === null ? rows - 1 : rule.row_to)),
                col_from: Math.max(0, Number(rule.col_from === null ? 0 : rule.col_from)),
                col_to: Math.min(columns - 1, Number(rule.col_to === null ? columns - 1 : rule.col_to))
            };
        }

        if (rule.block_id_from === null && rule.block_id_to === null) {
            return null;
        }

        from = Math.max(0, Number(rule.block_id_from === null ? 0 : rule.block_id_from));
        to = Math.min(rows * columns - 1, Number(rule.block_id_to === null ? rows * columns - 1 : rule.block_id_to));
        if (from > to) {
            return null;
        }

        return {
            row_from: Math.floor(from / columns),
            row_to: Math.floor(to / columns),
            col_from: Math.floor(from / columns) === Math.floor(to / columns) ? from % columns : 0,
            col_to: Math.floor(from / columns) === Math.floor(to / columns) ? to % columns : columns - 1
        };
    };

    Grid.prototype.drawFallback = function () {
        if (!this.state || !this.ctx) {
            return;
        }

        var size = this.size();
        var grid = this.state.grid;
        var scale = Math.min(size.width / grid.width, size.height / grid.height);
        var offsetX = (size.width - grid.width * scale) / 2;
        var offsetY = (size.height - grid.height * scale) / 2;

        this.metrics = { scale: scale, offsetX: offsetX, offsetY: offsetY };
        this.renderScene(this.ctx, {
            width: size.width,
            height: size.height,
            scale: scale,
            offsetX: offsetX,
            offsetY: offsetY
        });
    };

    Grid.prototype.redraw = function () {
        if (this.useOpenLayers && this.olSource) {
            this.olSource.changed();
        } else {
            this.drawFallback();
        }
    };

    Grid.prototype.blockAt = function (row, col) {
        var grid = this.state.grid;
        var x = col * grid.block_width;
        var y = row * grid.block_height;
        return (this.state.blocks || []).find(function (block) {
            return block.x === x && block.y === y;
        });
    };

    Grid.prototype.availabilityRegionAt = function (row, col) {
        if (!this.state || !Array.isArray(this.state.availabilityRegions)) {
            return null;
        }

        for (var i = 0; i < this.state.availabilityRegions.length; i++) {
            var region = this.state.availabilityRegions[i];
            if (
                row >= Number(region.row_from || 0) &&
                row <= Number(region.row_to || 0) &&
                col >= Number(region.col_from || 0) &&
                col <= Number(region.col_to || 0)
            ) {
                return region;
            }
        }

        return null;
    };

    Grid.prototype.coordKey = function (row, col) {
        return row + ':' + col;
    };

    Grid.prototype.selectionMode = function () {
        var selection = this.state && this.state.selection ? this.state.selection : {};
        var mode = String(selection.selection_adjacency_mode || 'ADJACENT').toUpperCase();
        return ['ADJACENT', 'RECTANGLE', 'NONE'].indexOf(mode) >= 0 ? mode : 'ADJACENT';
    };

    Grid.prototype.multiBlockEnabled = function () {
        var selection = this.state && this.state.selection ? this.state.selection : {};
        return String(selection.block_selection_mode || 'YES').toUpperCase() !== 'NO';
    };

    Grid.prototype.isCoordAvailable = function (row, col) {
        var grid = this.state.grid;
        if (row < 0 || col < 0 || row >= grid.virtual_blocks.rows || col >= grid.virtual_blocks.columns) {
            return false;
        }

        if (this.availabilityRegionAt(row, col)) {
            return false;
        }

        var existing = this.blockAt(row, col);
        return !existing || existing.status === 'available';
    };

    Grid.prototype.selectedPackage = function () {
        var packageId = this.packageSelect && !this.packageSelect.hidden ? Number(this.packageSelect.value || 0) : 0;
        if (!packageId || !this.state || !Array.isArray(this.state.packages)) {
            return null;
        }

        return this.state.packages.find(function (pkg) {
            return Number(pkg.id || 0) === packageId;
        }) || null;
    };

    Grid.prototype.updateSubscriptionPlans = function () {
        if (!this.subscriptionPlanSelect) {
            return;
        }
        var packageId = this.packageSelect && !this.packageSelect.hidden ? String(this.packageSelect.value || '') : '';
        var visible = 0;
        Array.prototype.forEach.call(this.subscriptionPlanSelect.options, function (option) {
            if (!option.value) {
                option.hidden = false;
                return;
            }
            var boundPackage = String(option.getAttribute('data-package-id') || '');
            option.hidden = !!boundPackage && boundPackage !== packageId;
            if (!option.hidden) {
                visible += 1;
            }
        });
        if (this.subscriptionPlanSelect.selectedOptions.length && this.subscriptionPlanSelect.selectedOptions[0].hidden) {
            this.subscriptionPlanSelect.value = '';
        }
        var field = this.subscriptionPlanSelect.closest('.mds3-subscription-plan-field');
        if (field) {
            field.hidden = visible < 1;
        }
    };

    Grid.prototype.formatMoney = function (amount, currency) {
        amount = Number(amount || 0);
        currency = String(currency || (this.state && this.state.grid ? this.state.grid.currency : 'USD') || 'USD').toUpperCase();
        if (amount === 0) {
            return gridConfig.i18n.freePrice || 'Free';
        }
        if (window.Intl && window.Intl.NumberFormat) {
            try {
                return new window.Intl.NumberFormat(undefined, {
                    style: 'currency',
                    currency: currency
                }).format(amount);
            } catch (error) {}
        }

        return currency + ' ' + amount.toFixed(2);
    };

    Grid.prototype.priceRuleScore = function (rule, row, col, blockIndex) {
        var hasCoords = rule.row_from !== null || rule.row_to !== null || rule.col_from !== null || rule.col_to !== null;
        var score = 0;

        if (hasCoords) {
            if (rule.row_from !== null && row < Number(rule.row_from)) {
                return -1;
            }
            if (rule.row_to !== null && row > Number(rule.row_to)) {
                return -1;
            }
            if (rule.col_from !== null && col < Number(rule.col_from)) {
                return -1;
            }
            if (rule.col_to !== null && col > Number(rule.col_to)) {
                return -1;
            }
            ['row_from', 'row_to', 'col_from', 'col_to'].forEach(function (key) {
                if (rule[key] !== null) {
                    score++;
                }
            });
            return score;
        }

        if (rule.block_id_from !== null || rule.block_id_to !== null) {
            if (rule.block_id_from !== null && blockIndex < Number(rule.block_id_from)) {
                return -1;
            }
            if (rule.block_id_to !== null && blockIndex > Number(rule.block_id_to)) {
                return -1;
            }
            if (rule.block_id_from !== null) {
                score++;
            }
            if (rule.block_id_to !== null) {
                score++;
            }
            return score;
        }

        return 0;
    };

    Grid.prototype.effectivePriceForCoord = function (coord) {
        var grid = this.state.grid;
        var block = this.blockAt(coord.row, coord.col);
        var fallback = Number(grid.price_per_block || 0);
        var currency = grid.currency || 'USD';
        var columns = grid.virtual_blocks.columns;
        var blockIndex = coord.row * columns + coord.col;
        var best = null;
        var bestScore = -1;

        if (block && block.price_override !== null && block.price_override !== undefined && block.price_override !== '') {
            return {
                price: Number(block.price_override || 0),
                currency: currency,
                source: 'block_override'
            };
        }

        (this.state.priceRules || []).forEach(function (rule) {
            var score;
            if (rule.status !== 'active') {
                return;
            }
            score = this.priceRuleScore(rule, coord.row, coord.col, blockIndex);
            if (score < 0) {
                return;
            }
            if (score > bestScore || (score === bestScore && Number(rule.id || 0) > Number(best && best.id || 0))) {
                best = rule;
                bestScore = score;
            }
        }, this);

        if (best) {
            return {
                price: Number(best.price || fallback),
                currency: best.currency || currency,
                source: 'price_rule'
            };
        }

        return {
            price: fallback,
            currency: currency,
            source: 'grid'
        };
    };

    Grid.prototype.selectionEstimate = function () {
        var pkg = this.selectedPackage();
        var total = 0;
        var currency = this.state && this.state.grid ? this.state.grid.currency : 'USD';

        if (pkg && Number(pkg.price || 0) > 0) {
            return {
                total: Number(pkg.price || 0),
                currency: pkg.currency || currency,
                packageTitle: pkg.title || ''
            };
        }

        this.selected.forEach(function (coord) {
            var pricing = this.effectivePriceForCoord(coord);
            total += Number(pricing.price || 0);
            currency = pricing.currency || currency;
        }, this);

        return {
            total: total,
            currency: currency,
            packageTitle: pkg && pkg.title ? pkg.title : ''
        };
    };

    Grid.prototype.selectionSizeBounds = function () {
        if (!this.state || !this.multiBlockEnabled()) {
            return null;
        }

        var selection = this.selectionSettings();
        var virtual = this.state.grid ? this.state.grid.virtual_blocks : null;
        var total = virtual ? Math.max(1, virtual.rows * virtual.columns) : 0;
        var min = Math.max(1, Number(selection.min_blocks) || 1);
        var max = Number(selection.max_blocks) || 0;

        if (!max) {
            max = total || min;
        }
        if (total) {
            max = Math.min(max, total);
        }

        return { min: min, max: Math.max(min, max) };
    };

    Grid.prototype.selectionSizeStorageKey = function () {
        return 'mds3-selection-size:' + this.gridId;
    };

    Grid.prototype.effectiveSelectionSize = function (bounds) {
        bounds = bounds || this.selectionSizeBounds();
        if (!bounds) {
            return 1;
        }

        var stored = null;
        try {
            var raw = window.localStorage.getItem(this.selectionSizeStorageKey());
            stored = raw === null ? null : parseInt(raw, 10);
        } catch (error) {
            stored = null;
        }
        if (!Number.isFinite(stored)) {
            stored = bounds.min;
        }

        return Math.min(bounds.max, Math.max(bounds.min, stored));
    };

    Grid.prototype.updateSelectionSizeControl = function () {
        var button = this.selectionSizeButton;
        if (!button) {
            return;
        }

        var bounds = this.selectionSizeBounds();
        if (!bounds || bounds.min === bounds.max) {
            button.hidden = true;
            return;
        }

        var value = this.effectiveSelectionSize(bounds);
        this.selectionSize = value;
        button.hidden = false;
        if (this.actions && !this.readOnly && !this.currentOrder) {
            this.actions.hidden = false;
        }
        button.title = i18n('selectionSizeHint', 'Choose how many blocks each click selects when you start a new selection.');
        button.textContent = i18n('selectionSize', 'Selection size') + (value === bounds.min ? '' : ' \u00b7 ' + value);
        if (this.selectionSizeDialog) {
            var range = this.selectionSizeDialog.querySelector('input[type="range"]');
            if (range) {
                range.min = String(bounds.min);
                range.max = String(bounds.max);
                range.value = String(value);
                var valueLabel = this.selectionSizeDialog.querySelector('.mds3-selection-size-value');
                if (valueLabel) {
                    valueLabel.textContent = String(value);
                }
            }
        }
    };

    Grid.prototype.buildSelectionSizeDialog = function () {
        if (this.selectionSizeDialog) {
            return this.selectionSizeDialog;
        }

        var self = this;
        var inputId = 'mds3-selection-size-' + this.gridId;
        var dialog = document.createElement('dialog');
        dialog.className = 'mds3-selection-size-dialog';

        var form = document.createElement('form');
        form.className = 'mds3-selection-size-form';
        form.setAttribute('method', 'dialog');
        form.innerHTML =
            '<h3 class="mds3-selection-size-title">' + i18n('selectionSize', 'Selection size') + '</h3>' +
            '<p class="mds3-selection-size-hint">' + i18n('selectionSizeHint', 'Choose how many blocks each click selects when you start a new selection.') + '</p>' +
            '<div class="mds3-selection-size-row">' +
                '<label for="' + inputId + '">' + i18n('blocksPerClick', 'Blocks per click') + '</label>' +
                '<span class="mds3-selection-size-value">1</span>' +
            '</div>' +
            '<input type="range" id="' + inputId + '" min="1" max="1" step="1" value="1">' +
            '<div class="mds3-selection-size-actions">' +
                '<button type="submit" class="mds3-selection-size-done">' + i18n('selectionSizeDone', 'Done') + '</button>' +
            '</div>';

        form.querySelector('input[type="range"]').addEventListener('input', function (event) {
            var value = Number(event.target.value) || 1;
            form.querySelector('.mds3-selection-size-value').textContent = String(value);
            self.selectionSize = value;
            try {
                window.localStorage.setItem(self.selectionSizeStorageKey(), String(value));
            } catch (error) {}
            self.updateSelectionSizeControl();
        });

        dialog.appendChild(form);
        this.element.appendChild(dialog);
        this.selectionSizeDialog = dialog;

        return dialog;
    };

    Grid.prototype.openSelectionSizeDialog = function () {
        var bounds = this.selectionSizeBounds();
        if (!bounds || bounds.min === bounds.max) {
            return;
        }

        var dialog = this.buildSelectionSizeDialog();
        var range = dialog.querySelector('input[type="range"]');
        var value = this.effectiveSelectionSize(bounds);
        range.min = String(bounds.min);
        range.max = String(bounds.max);
        range.value = String(value);
        dialog.querySelector('.mds3-selection-size-value').textContent = String(value);

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    };

    Grid.prototype.selectionAreaAt = function (row, col) {
        var bounds = this.selectionSizeBounds();
        if (!bounds) {
            return null;
        }

        var count = this.effectiveSelectionSize(bounds);
        if (count <= 1) {
            return null;
        }

        var virtual = this.state.grid.virtual_blocks;
        if (this.selectionMode() === 'RECTANGLE') {
            return this.anchoredRectangleAt(row, col, count, bounds.max, virtual.rows, virtual.columns);
        }

        /* Row-major BFS fill: every added cell touches an earlier one, so the
           result is contiguous by construction. */
        var found = [];
        var seen = {};
        var queue = [[row, col]];
        var offsets = [[0, 1], [1, 0], [0, -1], [-1, 0]];
        seen[this.coordKey(row, col)] = true;

        while (queue.length && found.length < count) {
            var cell = queue.shift();
            found.push({ row: cell[0], col: cell[1], key: this.coordKey(cell[0], cell[1]) });
            offsets.forEach(function (offset) {
                var nextRow = cell[0] + offset[0];
                var nextCol = cell[1] + offset[1];
                if (nextRow < 0 || nextRow >= virtual.rows || nextCol < 0 || nextCol >= virtual.columns) {
                    return;
                }
                var nextKey = this.coordKey(nextRow, nextCol);
                if (seen[nextKey] || !this.isCoordAvailable(nextRow, nextCol)) {
                    return;
                }
                seen[nextKey] = true;
                queue.push([nextRow, nextCol]);
            }, this);
        }

        return found.length > 1 ? found : null;
    };

    Grid.prototype.anchoredRectangleAt = function (row, col, count, maxAllowed, rows, columns) {
        for (var height = 1; height <= rows - row; height++) {
            for (var width = 1; width <= columns - col; width++) {
                var area = height * width;
                if (area < count || area > maxAllowed) {
                    continue;
                }
                var rectangle = this.rectangleFromCoords([
                    { row: row, col: col },
                    { row: row + height - 1, col: col + width - 1 }
                ]);
                if (rectangle) {
                    return rectangle;
                }
            }
        }

        return null;
    };

    Grid.prototype.uniqueCoords = function (coords) {
        var seen = {};
        var unique = [];
        (coords || []).forEach(function (coord) {
            var row = Number(coord.row);
            var col = Number(coord.col);
            if (!Number.isFinite(row) || !Number.isFinite(col)) {
                return;
            }

            row = Math.floor(row);
            col = Math.floor(col);
            var key = this.coordKey(row, col);
            if (seen[key]) {
                return;
            }

            seen[key] = true;
            unique.push({ row: row, col: col, key: key });
        }, this);

        return unique;
    };

    Grid.prototype.isContiguous = function (coords) {
        coords = this.uniqueCoords(coords);
        if (coords.length <= 1) {
            return true;
        }

        var remaining = {};
        coords.forEach(function (coord) {
            remaining[coord.key] = coord;
        });

        var queue = [coords[0]];
        delete remaining[coords[0].key];

        while (queue.length) {
            var coord = queue.shift();
            [
                this.coordKey(coord.row - 1, coord.col),
                this.coordKey(coord.row + 1, coord.col),
                this.coordKey(coord.row, coord.col - 1),
                this.coordKey(coord.row, coord.col + 1)
            ].forEach(function (key) {
                if (!remaining[key]) {
                    return;
                }
                queue.push(remaining[key]);
                delete remaining[key];
            });
        }

        return Object.keys(remaining).length === 0;
    };

    Grid.prototype.rectangleFromCoords = function (coords) {
        coords = this.uniqueCoords(coords);
        if (!coords.length) {
            return [];
        }

        var rows = coords.map(function (coord) { return coord.row; });
        var cols = coords.map(function (coord) { return coord.col; });
        var minRow = Math.min.apply(Math, rows);
        var maxRow = Math.max.apply(Math, rows);
        var minCol = Math.min.apply(Math, cols);
        var maxCol = Math.max.apply(Math, cols);
        var rectangle = [];

        for (var row = minRow; row <= maxRow; row++) {
            for (var col = minCol; col <= maxCol; col++) {
                if (!this.isCoordAvailable(row, col)) {
                    return null;
                }
                rectangle.push({ row: row, col: col, key: this.coordKey(row, col) });
            }
        }

        return rectangle;
    };

    Grid.prototype.formsCompleteRectangle = function (coords) {
        coords = this.uniqueCoords(coords);
        if (coords.length <= 1) {
            return true;
        }

        var rows = coords.map(function (coord) { return coord.row; });
        var cols = coords.map(function (coord) { return coord.col; });
        var area = (Math.max.apply(Math, rows) - Math.min.apply(Math, rows) + 1) * (Math.max.apply(Math, cols) - Math.min.apply(Math, cols) + 1);

        return area === coords.length;
    };

    Grid.prototype.canvasGridPoint = function (event) {
        if (!this.metrics) {
            return null;
        }

        var rect = this.canvas.getBoundingClientRect();
        return {
            x: (event.clientX - rect.left - this.metrics.offsetX) / this.metrics.scale,
            y: (event.clientY - rect.top - this.metrics.offsetY) / this.metrics.scale
        };
    };

    Grid.prototype.handleCanvasClick = function (event) {
        var point = this.canvasGridPoint(event);
        var placement;
        if (!point) {
            return;
        }

        placement = this.placementAt(point.x, point.y);
        if (placement) {
            this.activatePlacement(placement, event);
            return;
        }

        this.hidePopover();
        if (!this.readOnly) {
            this.toggleAt(point.x, point.y, event);
        }
    };

    Grid.prototype.updateCanvasCursor = function (event) {
        var point = this.canvasGridPoint(event);
        if (!point || !this.canvas) {
            return;
        }

        this.canvas.style.cursor = this.placementAt(point.x, point.y) ? 'pointer' : (this.readOnly ? '' : 'crosshair');
    };

    Grid.prototype.updateCanvasPopover = function (event) {
        if (!this.popoversEnabled() || this.popoverTrigger() === 'click') {
            return;
        }

        var point = this.canvasGridPoint(event);
        if (!point) {
            return;
        }

        var placement = this.placementAt(point.x, point.y);
        if (placement) {
            this.showPopover(placement, event.clientX, event.clientY, { pinned: false });
        }
    };

    Grid.prototype.interactionSettings = function () {
        return this.state && this.state.interaction ? this.state.interaction : {};
    };

    Grid.prototype.popoversEnabled = function () {
        return String(this.interactionSettings().enable_mouseover || 'yes').toLowerCase() !== 'no';
    };

    Grid.prototype.popoverTrigger = function () {
        return String(this.interactionSettings().tooltip_trigger || 'mouseenter').toLowerCase() === 'click' ? 'click' : 'mouseenter';
    };

    Grid.prototype.eventMovingIntoPopover = function (event) {
        return !!(
            this.popover &&
            event &&
            event.relatedTarget &&
            this.popover.contains(event.relatedTarget)
        );
    };

    Grid.prototype.placementHref = function (placement) {
        return String((placement && (placement.click_url || placement.link_url)) || '').trim();
    };

    Grid.prototype.linkTarget = function () {
        return this.interactionSettings().link_target === '_self' ? '_self' : '_blank';
    };

    Grid.prototype.applyPlacementLinkTarget = function (link) {
        var target = this.linkTarget();
        link.target = target;
        if (target === '_blank') {
            link.rel = 'noopener noreferrer';
        }
    };

    Grid.prototype.createPlacementAnchor = function (placement, label, className) {
        var href = this.placementHref(placement);
        var link;

        if (!href) {
            return null;
        }

        link = document.createElement('a');
        link.href = href;
        link.textContent = label || href;
        if (className) {
            link.className = className;
        }
        this.applyPlacementLinkTarget(link);

        return link;
    };

    Grid.prototype.createPopoverImage = function (placement, source, title, maxImageSize) {
        var image = document.createElement('img');
        image.className = 'mds3-popover-image';
        image.src = source.url;
        image.alt = placement.alt_text || title || '';
        image.loading = 'lazy';
        image.decoding = 'async';
        image.style.maxWidth = maxImageSize + 'px';

        return image;
    };

    Grid.prototype.createLinkedPopoverImage = function (placement, image) {
        var link = this.createPlacementAnchor(placement, '', 'mds3-popover-image-link');

        if (!link) {
            return image;
        }

        link.textContent = '';
        link.setAttribute('aria-label', placement.alt_text || placement.link_url || gridConfig.i18n.adDetails);
        link.appendChild(image);

        return link;
    };

    Grid.prototype.createCustomFieldValue = function (field, value) {
        var cleanValue = String(value).replace(/<[^>]+>/g, '').trim();
        var href = String(field.url || field.link_url || '').trim();
        var link;

        if (!cleanValue) {
            return document.createTextNode('');
        }

        if (!href && looksLikeAdvertiserUrl(cleanValue)) {
            href = normalizeAdvertiserUrlValue(cleanValue);
        }

        if (href && isValidAdvertiserUrl(href)) {
            link = document.createElement('a');
            link.href = href;
            link.textContent = cleanValue;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            return link;
        }

        return document.createTextNode(cleanValue);
    };

    Grid.prototype.positionPopover = function (clientX, clientY, popoverWidth, templateSizing) {
        var viewportRect = this.viewport.getBoundingClientRect();
        var pointerX = Number.isFinite(clientX) ? clientX : viewportRect.left + viewportRect.width / 2;
        var pointerY = Number.isFinite(clientY) ? clientY : viewportRect.top + viewportRect.height / 2;
        var popoverRect;
        var left;
        var top;
        var maxLeft;
        var maxTop;
        var localX;
        var localY;
        var position = templateSizing && templateSizing.position ? templateSizing.position : 'auto';
        var gap = 14;

        this.applyPopoverSizing(popoverWidth, templateSizing || null);
        this.popover.style.left = '0px';
        this.popover.style.top = '0px';
        this.popover.style.visibility = 'hidden';
        this.popover.hidden = false;

        popoverRect = this.popover.getBoundingClientRect();
        maxLeft = Math.max(8, window.innerWidth - popoverRect.width - 8);
        maxTop = Math.max(8, window.innerHeight - popoverRect.height - 8);
        localX = pointerX;
        localY = pointerY;

        if (position === 'center') {
            left = viewportRect.left + (viewportRect.width - popoverRect.width) / 2;
            top = viewportRect.top + (viewportRect.height - popoverRect.height) / 2;
        } else if (position === 'top') {
            left = localX - popoverRect.width / 2;
            top = localY - popoverRect.height - gap;
        } else if (position === 'bottom') {
            left = localX - popoverRect.width / 2;
            top = localY + gap;
        } else if (position === 'left') {
            left = localX - popoverRect.width - gap;
            top = localY - popoverRect.height / 2;
        } else if (position === 'right') {
            left = localX + gap;
            top = localY - popoverRect.height / 2;
        } else {
            left = localX + gap;
            top = localY + gap;

            if (top + popoverRect.height > window.innerHeight - 8) {
                top = localY - popoverRect.height - gap;
            }
        }

        left = Math.max(8, Math.min(maxLeft, left));
        top = Math.max(8, Math.min(maxTop, top));

        this.popover.style.left = left + 'px';
        this.popover.style.top = top + 'px';
        this.popover.style.visibility = '';
        this.applyPopoverBackdrop(templateSizing || null);
        this.applyPopoverAnimation(templateSizing || null);
    };

    Grid.prototype.supportedPopoverCssSize = function (value, property) {
        var size = String(value || '').trim();
        if (!size) {
            return '';
        }

        if (property === 'width' && size.toLowerCase() === 'auto') {
            return 'auto';
        }

        if (window.CSS && CSS.supports && CSS.supports(property, size)) {
            return size;
        }

        if (/^\d+(?:\.\d+)?(?:px|rem|em|%|vw|vh|ch)$/i.test(size)) {
            return size;
        }

        return '';
    };

    Grid.prototype.constrainedPopoverWidthValue = function (property, value, availableWidth) {
        var constrained;
        if (!value) {
            return '';
        }

        constrained = 'min(' + Math.round(availableWidth) + 'px, ' + value + ')';
        if (window.CSS && CSS.supports && CSS.supports(property, constrained)) {
            return constrained;
        }

        return value;
    };

    Grid.prototype.templatePopoverSizing = function () {
        var settings = this.popover ? this.popover.querySelector('[data-mds-fields-popup-width], [data-mds-fields-popup-min-width], [data-mds-fields-popup-max-width], [data-mds-fields-popup-animation], [data-mds-fields-popup-position], [data-mds-fields-popup-backdrop]') : null;
        if (!settings) {
            return null;
        }

        return {
            width: this.supportedPopoverCssSize(settings.getAttribute('data-mds-fields-popup-width'), 'width'),
            minWidth: this.supportedPopoverCssSize(settings.getAttribute('data-mds-fields-popup-min-width'), 'min-width'),
            maxWidth: this.supportedPopoverCssSize(settings.getAttribute('data-mds-fields-popup-max-width'), 'max-width'),
            animation: this.supportedPopoverAnimation(settings.getAttribute('data-mds-fields-popup-animation')),
            position: this.supportedPopoverPosition(settings.getAttribute('data-mds-fields-popup-position')),
            backdrop: settings.getAttribute('data-mds-fields-popup-backdrop') === 'true'
        };
    };

    Grid.prototype.supportedPopoverAnimation = function (value) {
        var key = String(value || '').replace(/[^a-zA-Z0-9_-]/g, '').toLowerCase();
        var animations = {
            none: 'none',
            fadein: 'fadeIn',
            slidedown: 'slideDown',
            slideup: 'slideUp',
            slideleft: 'slideLeft',
            slideright: 'slideRight',
            zoomin: 'zoomIn',
            popin: 'popIn',
            blurin: 'blurIn'
        };

        return animations[key] || '';
    };

    Grid.prototype.supportedPopoverPosition = function (value) {
        var key = String(value || 'auto').replace(/[^a-zA-Z0-9_-]/g, '').toLowerCase();
        var positions = {
            auto: 'auto',
            center: 'center',
            top: 'top',
            bottom: 'bottom',
            left: 'left',
            right: 'right'
        };

        return positions[key] || 'auto';
    };

    Grid.prototype.applyPopoverSizing = function (popoverWidth, templateSizing) {
        var availableWidth = Math.max(180, window.innerWidth - 24);

        this.popover.style.display = '';
        this.popover.style.width = '';
        this.popover.style.minWidth = '';
        this.popover.style.maxWidth = '';

        if (!templateSizing) {
            this.popover.style.maxWidth = popoverWidth + 'px';
            this.popover.style.width = popoverWidth + 'px';
            return;
        }

        this.popover.style.maxWidth = this.constrainedPopoverWidthValue('max-width', templateSizing.maxWidth, availableWidth) || (Math.round(availableWidth) + 'px');
        if (templateSizing.minWidth) {
            this.popover.style.minWidth = this.constrainedPopoverWidthValue('min-width', templateSizing.minWidth, availableWidth);
        }
        if (templateSizing.width && templateSizing.width !== 'auto') {
            this.popover.style.width = templateSizing.width;
        }
    };

    Grid.prototype.applyPopoverAnimation = function (templateSizing) {
        var animation = templateSizing && templateSizing.animation ? templateSizing.animation : '';

        this.popover.classList.remove('mds3-grid-popover-animating');
        if (!animation || animation === 'none') {
            this.popover.removeAttribute('data-mds3-popover-animation');
            return;
        }

        this.popover.setAttribute('data-mds3-popover-animation', animation);
        void this.popover.offsetWidth;
        this.popover.classList.add('mds3-grid-popover-animating');
    };

    Grid.prototype.ensurePopoverBackdrop = function () {
        if (this.popoverBackdrop || !this.viewport) {
            return this.popoverBackdrop;
        }

        this.popoverBackdrop = document.createElement('div');
        this.popoverBackdrop.className = 'mds3-grid-popover-backdrop';
        this.popoverBackdrop.setAttribute('aria-hidden', 'true');
        this.popoverBackdrop.hidden = true;
        this.viewport.insertBefore(this.popoverBackdrop, this.popover || this.viewport.firstChild);

        return this.popoverBackdrop;
    };

    Grid.prototype.applyPopoverBackdrop = function (templateSizing) {
        var backdrop;
        if (!templateSizing || !templateSizing.backdrop) {
            if (this.popoverBackdrop) {
                this.popoverBackdrop.hidden = true;
            }
            return;
        }

        backdrop = this.ensurePopoverBackdrop();
        if (backdrop) {
            backdrop.hidden = false;
        }
    };

    Grid.prototype.showPopover = function (placement, clientX, clientY, options) {
        if (!this.popover) {
            return;
        }

        options = options || {};
        var viewportRect = this.viewport.getBoundingClientRect();
        var interaction = this.interactionSettings();
        var maxPopupSize = parseInt(interaction.max_popup_size || 320, 10);
        var maxImageSize = parseInt(interaction.max_image_size || 300, 10);
        if (!maxPopupSize || maxPopupSize < 180) {
            maxPopupSize = 180;
        }
        maxPopupSize = Math.min(800, maxPopupSize);
        if (!maxImageSize || maxImageSize < 80) {
            maxImageSize = 80;
        }
        maxImageSize = Math.min(600, maxImageSize);
        var popoverWidth = Math.min(maxPopupSize, Math.max(180, viewportRect.width - 24));
        var title = placement.alt_text || placement.link_url || gridConfig.i18n.adDetails;
        var url = placement.link_url || '';
        var source = placement.source && placement.source.url ? placement.source : null;
        var popupTextHtml = placement.popup_text_html || '';
        var popupText = placement.popup_text || '';
        var image = null;
        var imageMarkup = '';
        var imageNode = null;
        var link = null;

        this.popover.innerHTML = '';
        this.popoverPinned = !!options.pinned;
        this.activePopoverPlacementId = String(placement.id || '');
        this.popover.classList.toggle('is-pinned', this.popoverPinned);
        if (source) {
            image = this.createPopoverImage(placement, source, title, maxImageSize);
            imageNode = this.createLinkedPopoverImage(placement, image);
            imageMarkup = imageNode.outerHTML;
        }

        if (placement.popover_html) {
            this.popover.innerHTML = String(placement.popover_html).replace(/%image%/gi, imageMarkup);
            this.positionPopover(clientX, clientY, popoverWidth, this.templatePopoverSizing());
            return;
        }

        if (imageNode) {
            this.popover.appendChild(imageNode);
        }
        var strong = document.createElement('strong');
        strong.textContent = title;
        this.popover.appendChild(strong);
        if (url) {
            link = this.createPlacementAnchor(placement, url.replace(/^https?:\/\//, ''), 'mds3-popover-url');
            if (link) {
                this.popover.appendChild(link);
            }
        }
        if (popupTextHtml || popupText) {
            var text = document.createElement('div');
            text.className = 'mds3-popover-text';
            if (popupTextHtml) {
                text.innerHTML = popupTextHtml;
            } else {
                text.textContent = popupText;
            }
            this.popover.appendChild(text);
        }
        if (placement.advertiser_page_url) {
            var pageLink = document.createElement('a');
            pageLink.className = 'mds3-popover-page-link';
            pageLink.href = String(placement.advertiser_page_url);
            pageLink.textContent = String(placement.advertiser_page_label || 'View advertiser page');
            pageLink.target = placement.advertiser_page_target === '_blank' ? '_blank' : '_self';
            if (pageLink.target === '_blank') {
                pageLink.rel = 'noopener noreferrer';
            }
            this.popover.appendChild(pageLink);
        }
        if (placement.custom_fields && typeof placement.custom_fields === 'object') {
            Object.keys(placement.custom_fields).forEach(function (key) {
                var field = placement.custom_fields[key] || {};
                var value = field.formatted_value || field.value || '';
                if (!value) {
                    return;
                }
                var row = document.createElement('span');
                row.className = 'mds3-popover-custom-field';
                if (field.label) {
                    row.appendChild(document.createTextNode(field.label + ': '));
                }
                row.appendChild(this.createCustomFieldValue(field, value));
                this.popover.appendChild(row);
            }, this);
        }
        if (placement.manage_url) {
            var manageLink = document.createElement('a');
            manageLink.className = 'mds3-popover-manage-link';
            manageLink.href = placement.manage_url;
            manageLink.textContent = (gridConfig.i18n && gridConfig.i18n.managePlacement) || 'Manage this pixel';
            this.popover.appendChild(manageLink);
        }

        this.positionPopover(clientX, clientY, popoverWidth);
    };

    Grid.prototype.hidePopover = function () {
        if (this.popover) {
            this.popover.hidden = true;
            this.popover.classList.remove('is-pinned');
            this.popover.classList.remove('mds3-grid-popover-animating');
            this.popover.removeAttribute('data-mds3-popover-animation');
        }
        if (this.popoverBackdrop) {
            this.popoverBackdrop.hidden = true;
        }
        this.activePopoverPlacementId = '';
        this.popoverHovered = false;
        this.popoverPinned = false;
    };

    Grid.prototype.showSelectionMessage = function (message, event) {
        if (!this.selectionMessage || !this.viewport || !message) {
            return;
        }

        var viewportRect = this.viewport.getBoundingClientRect();
        var clientX = event && Number.isFinite(event.clientX) ? event.clientX : viewportRect.left + viewportRect.width / 2;
        var clientY = event && Number.isFinite(event.clientY) ? event.clientY : viewportRect.top + viewportRect.height / 2;
        var maxWidth = Math.min(360, Math.max(220, viewportRect.width - 24));
        var messageRect;
        var left;
        var top;

        this.hidePopover();
        this.selectionMessage.textContent = message;
        this.selectionMessage.setAttribute('aria-label', String(i18n('dismissSelectionMessage', 'Dismiss message: %s')).replace('%s', message));
        this.selectionMessage.style.maxWidth = maxWidth + 'px';
        this.selectionMessage.style.left = '0px';
        this.selectionMessage.style.top = '0px';
        this.selectionMessage.style.visibility = 'hidden';
        this.selectionMessage.hidden = false;

        messageRect = this.selectionMessage.getBoundingClientRect();
        left = clientX - viewportRect.left + 14;
        top = clientY - viewportRect.top - messageRect.height - 14;

        if (top < 8) {
            top = clientY - viewportRect.top + 14;
        }

        left = Math.max(8, Math.min(left, viewportRect.width - messageRect.width - 8));
        top = Math.max(8, Math.min(top, viewportRect.height - messageRect.height - 8));

        this.selectionMessage.style.left = left + 'px';
        this.selectionMessage.style.top = top + 'px';
        this.selectionMessage.style.visibility = '';
    };

    Grid.prototype.hideSelectionMessage = function () {
        if (this.selectionMessage) {
            this.selectionMessage.hidden = true;
            this.selectionMessage.textContent = '';
            this.selectionMessage.removeAttribute('aria-label');
        }
    };

    Grid.prototype.rejectSelection = function (message, event) {
        this.setStatus(message);
        this.showSelectionMessage(message, event);
        this.showSelectionValidity(false, message, false);
    };

    Grid.prototype.placementAt = function (x, y) {
        if (!this.state || !this.state.placements) {
            return null;
        }

        var placements = this.state.placements.slice().reverse();
        for (var i = 0; i < placements.length; i++) {
            var placement = placements[i];
            if (placement.status !== 'active') {
                continue;
            }

            if (this.pointInPlacement(placement, x, y)) {
                return placement;
            }
        }

        return null;
    };

    Grid.prototype.openPlacementUrl = function (placement) {
        var url = this.placementHref(placement);
        if (!url) {
            return false;
        }

        var target = this.linkTarget();

        if (target === '_self') {
            window.location.href = url;
        } else {
            var opened = window.open(url, '_blank', 'noopener');
            if (opened) {
                opened.opener = null;
            }
        }

        return true;
    };

    Grid.prototype.activatePlacement = function (placement, event) {
        if (!placement) {
            return false;
        }

        if (this.popoversEnabled() && this.popoverTrigger() === 'click') {
            this.showPopover(placement, event ? event.clientX : 0, event ? event.clientY : 0, { pinned: true });
            return true;
        }

        this.openPlacementUrl(placement);
        return true;
    };

    Grid.prototype.openPlacement = function (x, y) {
        var placement = this.placementAt(x, y);
        if (!placement) {
            return false;
        }

        return this.activatePlacement(placement);
    };

    Grid.prototype.toggleAt = function (x, y, event) {
        var grid = this.state.grid;
        var point;
        if (this.currentOrder) {
            this.rejectSelection(i18n('finishCurrentOrder', 'Finish the current order before selecting more blocks.'), event);
            return;
        }
        point = this.normalizeGridPoint(x, y);
        if (!point) {
            return;
        }

        var row = Math.floor(point.y / grid.block_height);
        var col = Math.floor(point.x / grid.block_width);
        if (!this.isCoordAvailable(row, col)) {
            this.rejectSelection(this.unavailableBlockMessage(row, col), event);
            return;
        }

        var key = this.coordKey(row, col);
        var index = this.selected.findIndex(function (coord) {
            return coord.key === key;
        });
        var mode = this.selectionMode();
        var next = [];

        if (index >= 0) {
            next = this.selected.filter(function (coord) {
                return coord.key !== key;
            });
            if (mode === 'ADJACENT' && next.length > 1 && !this.isContiguous(next)) {
                this.rejectSelection(gridConfig.i18n.adjacentRequired, event);
                return;
            }
            if (mode === 'RECTANGLE' && next.length > 1 && !this.formsCompleteRectangle(next)) {
                this.rejectSelection(gridConfig.i18n.rectangleRequired, event);
                return;
            }
        } else {
            var area = !this.selected.length && this.multiBlockEnabled() ? this.selectionAreaAt(row, col) : null;
            next = area || (this.multiBlockEnabled() ? this.uniqueCoords(this.selected.concat([{ row: row, col: col, key: key }])) : [{ row: row, col: col, key: key }]);
            if (mode === 'RECTANGLE') {
                next = this.rectangleFromCoords(next);
                if (!next) {
                    this.rejectSelection(gridConfig.i18n.selectionUnavailable, event);
                    return;
                }
            } else if (mode === 'ADJACENT' && next.length > 1 && !this.isContiguous(next)) {
                this.rejectSelection(gridConfig.i18n.adjacentRequired, event);
                return;
            }
        }

        this.selected = next;
        this.setStatus('');
        this.hideSelectionMessage();
        this.redraw();
    };

    Grid.prototype.unavailableBlockMessage = function (row, col) {
        var block = this.blockAt(row, col);
        if (this.availabilityRegionAt(row, col)) {
            return i18n('blockUnavailable', 'This block is unavailable.');
        }
        if (block && block.status === 'reserved') {
            return i18n('blockReserved', 'This block is reserved.');
        }
        if (block && block.status && block.status !== 'available') {
            return i18n('blockTaken', 'This block is already taken.');
        }

        return i18n('selectionUnavailable', 'One or more blocks in that selection are unavailable.');
    };

    Grid.prototype.updateActions = function () {
        var estimate;
        var parts;
        if (this.readOnly || !this.actions) {
            return;
        }

        if (this.currentOrder) {
            this.actions.hidden = true;
            this.updateRestoreBar();
            return;
        }

        var sizeControlVisible = !!(this.selectionSizeButton && !this.selectionSizeButton.hidden);
        this.actions.hidden = this.selected.length === 0 && !sizeControlVisible;
        this.updateRestoreBar();
        this.updateCustomerControls();
        if (this.selected.length) {
            estimate = this.selectionEstimate();
            var estimateLabel = String(gridConfig.i18n.selectionEstimate || 'Estimated total: %s').replace('%s', this.formatMoney(estimate.total, estimate.currency));
            parts = [
                formatCount(gridConfig.i18n.selectedCount || '%d selected', this.selected.length),
                estimateLabel
            ];
            if (estimate.packageTitle) {
                parts.push(estimate.packageTitle);
            }
            this.summary.textContent = parts.join(' - ');
        } else {
            this.summary.textContent = '';
        }
        this.updateSelectionValidity();
    };

    Grid.prototype.selectionSettings = function () {
        return this.state && this.state.selection ? this.state.selection : {};
    };

    Grid.prototype.guestEmailRequired = function () {
        var selection = this.selectionSettings();

        return !!selection.guest_email_required;
    };

    Grid.prototype.loginRequired = function () {
        var selection = this.selectionSettings();

        return !selection.user_logged_in && selection.accounts_optional === 'no';
    };

    Grid.prototype.updateCustomerControls = function () {
        var selection = this.selectionSettings();
        var requireEmail = this.guestEmailRequired();
        var requireLogin = this.loginRequired();

        if (this.customerEmail) {
            this.customerEmail.hidden = !requireEmail;
            this.customerEmail.required = !!requireEmail;
            this.customerEmail.disabled = !requireEmail;
        }

        if (this.loginRequiredLink) {
            this.loginRequiredLink.hidden = !requireLogin;
            if (selection.login_url) {
                this.loginRequiredLink.setAttribute('href', selection.login_url);
            } else {
                this.loginRequiredLink.removeAttribute('href');
            }
        }
    };

    Grid.prototype.setReserveBusy = function (busy) {
        var clearButton = this.element.querySelector('.mds3-clear');
        this.reserving = !!busy;
        this.element.classList.toggle('mds3-is-reserving', !!busy);
        if (!this.reserveButton) {
            return;
        }

        this.reserveButton.disabled = busy || !!this.selectionValidationMessage();
        this.reserveButton.setAttribute('aria-busy', busy ? 'true' : 'false');
        this.reserveButton.textContent = busy ? i18n('reserving', 'Reserving selection...') : this.reserveButtonLabel;
        if (clearButton) {
            clearButton.disabled = !!busy;
        }
        if (this.packageSelect) {
            this.packageSelect.disabled = !!busy;
        }
    };

    Grid.prototype.applyReservedBlocks = function (blocks) {
        var byCoordinate = {};

        this.state.blocks = Array.isArray(this.state.blocks) ? this.state.blocks : [];
        this.state.blocks.forEach(function (block, index) {
            byCoordinate[Number(block.x || 0) + ':' + Number(block.y || 0)] = index;
        });

        (Array.isArray(blocks) ? blocks : []).forEach(function (block) {
            var key = Number(block.x || 0) + ':' + Number(block.y || 0);
            if (Object.prototype.hasOwnProperty.call(byCoordinate, key)) {
                this.state.blocks[byCoordinate[key]] = block;
                return;
            }

            byCoordinate[key] = this.state.blocks.length;
            this.state.blocks.push(block);
        }, this);
    };

    Grid.prototype.upsertPlacement = function (placement) {
        var index = -1;

        if (!placement || !placement.id) {
            return;
        }

        this.state.placements = Array.isArray(this.state.placements) ? this.state.placements : [];
        this.state.placements.some(function (current, currentIndex) {
            if (Number(current.id || 0) === Number(placement.id || 0) || (
                Number(current.order_id || 0) > 0 &&
                Number(current.order_id || 0) === Number(placement.order_id || 0)
            )) {
                index = currentIndex;
                return true;
            }

            return false;
        });

        if (index >= 0) {
            this.state.placements[index] = placement;
            return;
        }

        this.state.placements.push(placement);
    };

    Grid.prototype.maskFromBlocks = function (blocks) {
        return (Array.isArray(blocks) ? blocks : []).map(function (block) {
            return {
                x: Number(block.x || 0),
                y: Number(block.y || 0),
                width: Number(block.width || 0),
                height: Number(block.height || 0)
            };
        }).filter(function (block) {
            return block.width > 0 && block.height > 0;
        });
    };

    Grid.prototype.currentDraftRect = function (draft) {
        var source = draft && draft.width && draft.height ? draft : this.currentPlacementRect;
        if (!source) {
            return null;
        }

        return {
            x: Number(source.x || 0),
            y: Number(source.y || 0),
            width: Math.max(1, Number(source.width || 1)),
            height: Math.max(1, Number(source.height || 1)),
            grid_id: Number(source.grid_id || this.gridId || 0),
            block_id: Number(source.block_id || 0)
        };
    };

    Grid.prototype.draftPlacementFromImage = function (draft, sourceUrl) {
        var rect = this.currentDraftRect(draft);
        var fitMode = namedFieldValue(this.form, 'fit_mode') || 'cover';
        var source = draft && draft.source ? draft.source : {};
        if (!rect || !(sourceUrl || source.url)) {
            return null;
        }

        return {
            id: 'draft-' + (this.currentOrder && this.currentOrder.id ? this.currentOrder.id : 'new'),
            grid_id: rect.grid_id,
            block_id: rect.block_id,
            order_id: this.currentOrder && this.currentOrder.id ? Number(this.currentOrder.id) : Number(draft && draft.order_id || 0),
            attachment_id: Number(draft && draft.attachment_id || 0),
            x: rect.x,
            y: rect.y,
            width: rect.width,
            height: rect.height,
            mask: Array.isArray(draft && draft.mask) && draft.mask.length ? draft.mask : this.currentPlacementMask,
            fit_mode: fitMode === 'contain' ? 'contain' : 'cover',
            alt_text: namedFieldValue(this.form, 'alt_text'),
            status: 'draft',
            source: {
                url: sourceUrl || source.url,
                width: Number(source.width || 0),
                height: Number(source.height || 0),
                megapixels: Number(source.megapixels || 0),
                mime_type: source.mime_type || ''
            }
        };
    };

    Grid.prototype.preloadPlacementImage = function (placement) {
        var self = this;
        var url = placement && placement.source && placement.source.url;
        if (!url || this.imageCache[url]) {
            return;
        }

        var image = new Image();
        image.onload = function () {
            self.redraw();
        };
        image.src = url;
        this.imageCache[url] = image;
    };

    Grid.prototype.applyDraftImage = function (draft, sourceUrl) {
        var placement = this.draftPlacementFromImage(draft, sourceUrl);
        if (!placement) {
            return;
        }

        this.draftPlacement = placement;
        this.preloadPlacementImage(placement);
        if (draft && draft.attachment_id && draft.token) {
            setDraftImageFields(this.form, draft);
            hideDraftImageNotice(this.form);
        }
        setDraftImageControlState(this.form, true);
        this.updateUploadValidity(false);
        this.redraw();
    };

    Grid.prototype.clearDraftImage = function () {
        if (this.draftObjectUrl) {
            URL.revokeObjectURL(this.draftObjectUrl);
            this.draftObjectUrl = '';
        }
        this.draftPlacement = null;
        clearDraftImageFields(this.form);
        setDraftImageControlState(this.form, false);
        this.updateUploadValidity(false);
        this.redraw();
    };

    Grid.prototype.handleDraftImageSelection = function (file) {
        var self = this;
        var localUrl;
        var token;
        if (!file || !this.form || !this.currentOrder || !this.currentOrderKey) {
            return;
        }

        token = ++this.draftUploadToken;
        if (this.draftObjectUrl) {
            URL.revokeObjectURL(this.draftObjectUrl);
        }
        localUrl = URL.createObjectURL(file);
        this.draftObjectUrl = localUrl;
        this.applyDraftImage({ source: { url: localUrl } }, localUrl);
        this.setStatus(i18n('draftImageUploading', 'Saving image for this order...'));

        uploadDraftImage(this.form, file, this.gridId).then(function (draft) {
            var input = namedField(self.form, 'image');
            if (token !== self.draftUploadToken) {
                return;
            }
            if (self.draftObjectUrl) {
                URL.revokeObjectURL(self.draftObjectUrl);
                self.draftObjectUrl = '';
            }
            if (input) {
                input.value = '';
                input.required = false;
            }
            self.applyDraftImage(draft, '');
            saveFormDraft(self.form, { preserveExistingFields: true });
            self.setStatus(i18n('draftImageSaved', 'Image saved for this order.'));
        }).catch(function (error) {
            if (token !== self.draftUploadToken) {
                return;
            }
            self.clearDraftImage();
            self.setStatus(error.message || gridConfig.i18n.error);
        });
    };

    Grid.prototype.restoreServerDraftImage = function () {
        var self = this;
        if (!this.form || !this.currentOrder || !this.currentOrderKey) {
            return;
        }

        fetchDraftImageState(this.form, this.gridId).then(function (draft) {
            if (!draft) {
                clearStoredServerDraft(self.form);
                return;
            }

            showDraftImageNotice(self.form, draft, function (serverDraft) {
                self.applyDraftImage(serverDraft, '');
                saveFormDraft(self.form, { preserveExistingFields: true });
                self.setStatus(i18n('draftImagePreviewReady', 'Image preview updated.'));
            });
        }).catch(function () {});
    };

    Grid.prototype.removeDraftImage = function () {
        var self = this;
        if (!this.form || !formHasDraftImage(this.form)) {
            this.clearDraftImage();
            return;
        }

        removeDraftImage(this.form, this.gridId).then(function () {
            self.clearDraftImage();
            saveFormDraft(self.form, { preserveExistingFields: true });
            self.setStatus(i18n('draftImageRemoved', 'Draft image removed.'));
        }).catch(function (error) {
            self.setStatus(error.message || i18n('draftImageRemoveError', 'Draft image could not be removed.'));
        });
    };

    Grid.prototype.refreshDraftFitMode = function () {
        if (!this.draftPlacement || !this.form) {
            return;
        }

        this.draftPlacement.fit_mode = namedFieldValue(this.form, 'fit_mode') === 'contain' ? 'contain' : 'cover';
        this.redraw();
    };

    Grid.prototype.bindDraftImageControls = function () {
        var self = this;
        var imageInput;
        var fitMode;
        var remove;
        if (!this.form || this.form.getAttribute('data-mds3-draft-image-bound') === 'true') {
            return;
        }

        this.form.setAttribute('data-mds3-draft-image-bound', 'true');
        imageInput = namedField(this.form, 'image');
        fitMode = namedField(this.form, 'fit_mode');
        remove = draftImageRemoveButton(this.form);

        if (imageInput) {
            imageInput.addEventListener('change', function () {
                if (imageInput.files && imageInput.files.length) {
                    self.handleDraftImageSelection(imageInput.files[0]);
                }
            });
        }

        if (fitMode) {
            fitMode.addEventListener('input', function () {
                self.refreshDraftFitMode();
            });
            fitMode.addEventListener('change', function () {
                self.refreshDraftFitMode();
            });
        }

        if (remove) {
            remove.addEventListener('click', function () {
                self.removeDraftImage();
            });
        }
    };

    Grid.prototype.reserve = function () {
        var self = this;
        var orderId = '';
        var data;
        if (this.reserving) {
            return;
        }
        if (!this.validateSelection(true)) {
            return;
        }

        this.setReserveBusy(true);
        this.setStatus(i18n('reserving', 'Reserving selection...'));

        post('mds3_reserve_blocks', {
            grid_id: this.gridId,
            nonce: gridConfig.nonce,
            email: this.customerEmail && !this.customerEmail.hidden ? this.customerEmail.value : '',
            package_id: this.packageSelect && !this.packageSelect.hidden ? this.packageSelect.value : '',
            subscription_plan_id: this.subscriptionPlanSelect && !this.subscriptionPlanSelect.hidden ? this.subscriptionPlanSelect.value : '',
            blocks: this.selected
        }).then(function (payload) {
            if (!payload || !payload.success) {
                self.setStatus(payload && payload.data && payload.data.message ? payload.data.message : gridConfig.i18n.error);
                self.setReserveBusy(false);
                return;
            }

            data = payload.data || {};
            self.applyReservedBlocks(data.blocks || []);
            self.selected = [];
            self.currentOrder = data.order || null;
            self.currentOrderKey = data.order_key || '';
            self.currentPlacementRect = data.placement_rect || null;
            self.currentPlacementMask = self.maskFromBlocks(data.blocks || []);
            saveActiveGridOrder(self.gridId, self.currentOrder, self.currentOrderKey, data.checkout || {}, self.currentPlacementRect, self.currentPlacementMask);
            orderId = self.currentOrder && self.currentOrder.id ? ' #' + self.currentOrder.id : '';
            self.setStatus(gridConfig.i18n.reserved + orderId);
            self.showPlacementForm(data.checkout || {});
            self.setReserveBusy(false);
            self.redraw();
        }).catch(function () {
            self.setStatus(gridConfig.i18n.error);
            self.setReserveBusy(false);
        });
    };

    Grid.prototype.ensureRestoreBar = function () {
        var text;
        var actions;
        var restore;
        var dismiss;
        if (this.restoreBar || this.readOnly) {
            return this.restoreBar;
        }

        this.restoreBar = document.createElement('div');
        this.restoreBar.className = 'mds3-grid-restore-bar';
        this.restoreBar.setAttribute('role', 'status');
        this.restoreBar.setAttribute('aria-live', 'polite');
        this.restoreBar.hidden = true;

        text = document.createElement('span');
        text.className = 'mds3-grid-restore-text';
        text.textContent = i18n('draftOrderFound', 'Saved order progress was found in this browser.');

        actions = document.createElement('span');
        actions.className = 'mds3-draft-actions';

        restore = document.createElement('button');
        restore.type = 'button';
        restore.className = 'mds3-grid-restore-progress';
        restore.textContent = i18n('draftOrderRestore', 'Restore progress');
        restore.addEventListener('click', this.restoreActiveOrder.bind(this));

        dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'mds3-grid-restore-dismiss';
        dismiss.textContent = i18n('draftDismiss', 'Dismiss');
        dismiss.addEventListener('click', this.dismissActiveOrder.bind(this));

        actions.appendChild(restore);
        actions.appendChild(dismiss);
        this.restoreBar.appendChild(text);
        this.restoreBar.appendChild(actions);
        this.element.insertBefore(this.restoreBar, this.actions || this.viewport);

        return this.restoreBar;
    };

    Grid.prototype.updateRestoreBar = function () {
        var activeOrder;
        var bar;
        if (this.readOnly) {
            return;
        }

        bar = this.ensureRestoreBar();
        if (!bar) {
            return;
        }

        activeOrder = readActiveGridOrder(this.gridId);
        bar.hidden = !activeOrder || !!this.currentOrder || this.selected.length > 0;
    };

    Grid.prototype.restoreActiveOrder = function () {
        var activeOrder = readActiveGridOrder(this.gridId);
        if (!activeOrder) {
            this.updateRestoreBar();
            return;
        }

        this.selected = [];
        this.currentOrder = activeOrder.order;
        this.currentOrderKey = activeOrder.order_key;
        this.currentPlacementRect = activeOrder.placement_rect || null;
        this.currentPlacementMask = Array.isArray(activeOrder.placement_mask) ? activeOrder.placement_mask : [];
        this.setStatus(i18n('draftOrderRestored', 'Order progress restored.'));
        this.showPlacementForm(activeOrder.checkout || {}, true);
        this.updateRestoreBar();
        this.redraw();
    };

    Grid.prototype.dismissActiveOrder = function () {
        clearActiveGridOrder(this.gridId);
        this.updateRestoreBar();
    };

    Grid.prototype.showPlacementForm = function (checkout, restoreDraft) {
        if (!this.form || !this.currentOrder) {
            return;
        }

        this.element.classList.add('mds3-has-active-order');
        if (this.actions) {
            this.actions.hidden = true;
        }
        this.form.hidden = false;
        this.checkoutUrl = checkout && checkout.checkout_url ? checkout.checkout_url : '';
        this.afterUploadUrl = checkout && checkout.after_upload_url ? checkout.after_upload_url : this.checkoutUrl;
        if (this.submitButton) {
            if (this.checkoutUrl) {
                this.submitButton.textContent = i18n('continueCheckout', 'Continue to checkout');
            } else if (this.afterUploadUrl) {
                this.submitButton.textContent = i18n('continue', 'Continue');
            } else {
                this.submitButton.textContent = i18n('saveAd', 'Save ad');
            }
        }
        if (this.checkoutLink) {
            this.checkoutLink.hidden = true;
            this.checkoutLink.removeAttribute('href');
            this.checkoutLink.setAttribute('aria-disabled', 'true');
        }
        ensureHiddenField(this.form, 'order_id').value = this.currentOrder.id || '';
        ensureHiddenField(this.form, 'order_key').value = this.currentOrderKey;
        this.bindDraftImageControls();
        bindFormDraft(this.form, 'grid:' + this.gridId + ':order:' + this.currentOrder.id, 'grid:' + this.gridId + ':order:');
        if (restoreDraft) {
            restoreVisibleFormDraft(this.form);
        }
        this.restoreServerDraftImage();
        this.updateUploadValidity(true);
    };

    Grid.prototype.submitPlacement = function () {
        var self = this;
        if (!this.form || !this.currentOrder || !this.currentOrderKey) {
            return;
        }
        if (this.uploading) {
            return;
        }

        if (!this.validateUpload(true)) {
            return;
        }

        syncRichTextEditors(this.form);
        this.uploading = true;
        setOrderUploadBusy(this.form, true);
        var form = new FormData(this.form);
        form.append('action', 'mds3_submit_placement');
        form.append('nonce', gridConfig.nonce);
        form.append('grid_id', this.gridId);
        form.append('order_id', this.currentOrder.id);
        form.append('order_key', this.currentOrderKey);
        this.setStatus(i18n('savingAd', 'Saving ad...'));

        request(form).then(function (payload) {
            if (!payload || !payload.success) {
                self.setStatus(payload && payload.data && payload.data.message ? payload.data.message : gridConfig.i18n.error);
                self.uploading = false;
                setOrderUploadBusy(self.form, false);
                return;
            }

            if (payload.data && payload.data.placement) {
                self.upsertPlacement(payload.data.placement);
                self.preloadPlacementImages();
            }
            self.setStatus(i18n('adSaved', 'Ad saved.'));
            clearFormDraft(self.form);
            self.clearDraftImage();
            clearActiveGridOrder(self.gridId);
            self.uploading = false;
            if (payload.data && payload.data.redirect_url) {
                window.location.href = payload.data.redirect_url;
                return;
            }
            self.form.reset();
            setOrderUploadBusy(self.form, false);
            self.updateUploadValidity(true);
            self.redraw();
        }).catch(function () {
            self.uploading = false;
            setOrderUploadBusy(self.form, false);
            self.setStatus(gridConfig.i18n.error);
        });
    };

    Grid.prototype.selectionValidationMessage = function () {
        var mode = this.selectionMode();
        if (!this.selected.length) {
            return gridConfig.i18n.selectBlocksFirst;
        }
        var selection = this.state && this.state.selection ? this.state.selection : {};
        var minBlocks = Math.max(1, Number(selection.min_blocks || 1));
        var maxBlocks = Math.max(0, Number(selection.max_blocks || 0));
        if (this.selected.length < minBlocks) {
            return formatCount(gridConfig.i18n.minBlocksRequired, minBlocks);
        }
        if (maxBlocks && this.selected.length > maxBlocks) {
            return formatCount(gridConfig.i18n.maxBlocksAllowed, maxBlocks);
        }
        if (!this.multiBlockEnabled() && this.selected.length > 1) {
            return gridConfig.i18n.singleBlockOnly;
        }
        if (mode === 'ADJACENT' && this.selected.length > 1 && !this.isContiguous(this.selected)) {
            return gridConfig.i18n.adjacentRequired;
        }
        if (mode === 'RECTANGLE' && this.selected.length > 1 && !this.formsCompleteRectangle(this.selected)) {
            return gridConfig.i18n.rectangleRequired;
        }
        if (this.loginRequired()) {
            return gridConfig.i18n.loginRequired;
        }
        if (this.guestEmailRequired()) {
            if (!this.customerEmail || !String(this.customerEmail.value || '').trim()) {
                return gridConfig.i18n.missingEmail;
            }
            if (!this.customerEmail.checkValidity()) {
                return gridConfig.i18n.invalidEmail;
            }
        }

        return '';
    };

    Grid.prototype.ensureSelectionValidity = function () {
        if (!this.actions || this.selectionValidity) {
            return;
        }

        this.selectionValidity = this.createValidityIndicator();
        this.actions.appendChild(this.selectionValidity);
    };

    Grid.prototype.showSelectionValidity = function (valid, message, open) {
        this.ensureSelectionValidity();
        if (!this.selectionValidity) {
            return;
        }

        this.updateValidityIndicator(this.selectionValidity, valid, message, open);
    };

    Grid.prototype.updateSelectionValidity = function () {
        var message = this.selectionValidationMessage();
        var valid = !message;
        if (this.reserveButton) {
            this.reserveButton.disabled = !valid;
        }
        this.showSelectionValidity(valid, valid ? gridConfig.i18n.selectionReady : message, false);
    };

    Grid.prototype.validateSelection = function (open) {
        var message = this.selectionValidationMessage();
        var valid = !message;
        this.showSelectionValidity(valid, valid ? gridConfig.i18n.selectionReady : message, open && !valid);
        if (this.reserveButton) {
            this.reserveButton.disabled = !valid;
        }

        return valid;
    };

    Grid.prototype.ensureUploadValidity = function () {
        if (!this.form || this.uploadMessage) {
            return;
        }

        this.uploadMessage = ensureFormMessage(this.form);
    };

    Grid.prototype.uploadValidationMessage = function (normalizeUrls) {
        var upload = this.state && this.state.upload ? this.state.upload : {};
        return placementFormValidation(this.form, normalizeUrls, upload).message;
    };

    Grid.prototype.updateUploadActions = function (valid) {
        if (this.submitButton) {
            this.submitButton.disabled = !valid;
        }
        if (this.checkoutLink) {
            this.checkoutLink.setAttribute('aria-disabled', 'true');
            this.checkoutLink.hidden = true;
            this.checkoutLink.removeAttribute('href');
        }
    };

    Grid.prototype.updateUploadValidity = function (openWhenInvalid) {
        var message;
        var valid;
        if (!this.form || this.form.hidden) {
            return;
        }

        this.ensureUploadValidity();
        message = this.uploadValidationMessage(false);
        valid = !message;
        this.updateUploadActions(valid);
        if (valid) {
            hideFormMessage(this.form);
        } else if (openWhenInvalid || (this.uploadMessage && !this.uploadMessage.hidden)) {
            showFormMessage(this.form, message, null);
        }
    };

    Grid.prototype.validateUpload = function (open) {
        var upload = this.state && this.state.upload ? this.state.upload : {};
        var result = placementFormValidation(this.form, true, upload);
        var message = result.message;
        var valid = !message;
        this.ensureUploadValidity();
        this.updateUploadActions(valid);
        if (valid) {
            hideFormMessage(this.form);
        } else if (open) {
            showFormMessage(this.form, message, result.field);
            if (result.field && typeof result.field.focus === 'function') {
                result.field.focus({ preventScroll: false });
            }
        }

        return valid;
    };

    Grid.prototype.createValidityIndicator = function () {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'mds3-validity-indicator is-invalid';
        button.setAttribute('aria-expanded', 'false');
        button.innerHTML = '<span aria-hidden="true">x</span><span class="mds3-validity-tooltip" role="status"></span>';
        button.addEventListener('click', function () {
            button.classList.toggle('is-open');
            button.setAttribute('aria-expanded', button.classList.contains('is-open') ? 'true' : 'false');
        });

        return button;
    };

    Grid.prototype.updateValidityIndicator = function (indicator, valid, message, open) {
        var glyph = indicator.querySelector('span[aria-hidden="true"]');
        var tooltip = indicator.querySelector('.mds3-validity-tooltip');
        indicator.classList.toggle('is-valid', !!valid);
        indicator.classList.toggle('is-invalid', !valid);
        indicator.setAttribute('aria-label', message || '');
        if (glyph) {
            glyph.textContent = valid ? '✓' : 'x';
        }
        if (tooltip) {
            tooltip.textContent = message || '';
        }
        if (valid) {
            indicator.classList.remove('is-open');
            indicator.setAttribute('aria-expanded', 'false');
            return;
        }
        if (open) {
            indicator.classList.add('is-open');
            indicator.setAttribute('aria-expanded', 'true');
        }
    };

    Grid.prototype.setStatus = function (message) {
        if (this.status) {
            this.status.textContent = message || '';
        }
    };

    function rememberOrderUploadPreview(form, force) {
        var panel = form ? form.closest('.mds3-order-upload-panel') : null;
        var preview = panel ? panel.querySelector('.mds3-current-placement') : null;
        if (!form || !preview || (form.mds3OriginalPreviewCaptured && !force)) {
            return;
        }

        form.mds3OriginalPreviewCaptured = true;
        form.mds3OriginalPreviewHtml = preview.innerHTML;
        form.mds3OriginalPreviewHidden = preview.hidden;
        form.mds3OriginalImageRequired = !!(namedField(form, 'image') && namedField(form, 'image').required);
    }

    function restoreOrderUploadPreview(form) {
        var panel = form ? form.closest('.mds3-order-upload-panel') : null;
        var preview = panel ? panel.querySelector('.mds3-current-placement') : null;
        var imageInput = namedField(form, 'image');
        if (!form || !preview) {
            return;
        }

        preview.innerHTML = form.mds3OriginalPreviewHtml || '';
        preview.hidden = !!form.mds3OriginalPreviewHidden;
        if (imageInput) {
            imageInput.required = !!form.mds3OriginalImageRequired;
        }
    }

    function applyOrderUploadDraftImage(form, draft, sourceUrl, saveOptions) {
        var payload = draft || {};
        if (sourceUrl) {
            payload = {
                alt_text: namedFieldValue(form, 'alt_text'),
                fit_mode: namedFieldValue(form, 'fit_mode'),
                source: {
                    url: sourceUrl
                }
            };
        }

        updateOrderUploadPreview(form, payload);
        if (draft && draft.attachment_id && draft.token) {
            setDraftImageFields(form, draft);
            hideDraftImageNotice(form);
        }
        setDraftImageControlState(form, true);
        saveFormDraft(form, saveOptions);
    }

    function bindOrderUploadDraftControls(form) {
        var imageInput;
        var fitMode;
        var remove;
        var status;
        if (!form || form.getAttribute('data-mds3-draft-image-bound') === 'true') {
            return;
        }

        form.setAttribute('data-mds3-draft-image-bound', 'true');
        rememberOrderUploadPreview(form);
        status = form.closest('.mds3-order-upload-panel') ? form.closest('.mds3-order-upload-panel').querySelector('.mds3-grid-status') : null;
        imageInput = namedField(form, 'image');
        fitMode = namedField(form, 'fit_mode');
        remove = draftImageRemoveButton(form);

        if (imageInput) {
            imageInput.addEventListener('change', function () {
                var file = imageInput.files && imageInput.files.length ? imageInput.files[0] : null;
                var localUrl;
                if (!file) {
                    return;
                }

                localUrl = URL.createObjectURL(file);
                applyOrderUploadDraftImage(form, null, localUrl, { preserveExistingFields: true });
                if (status) {
                    status.textContent = i18n('draftImageUploading', 'Saving image for this order...');
                }

                uploadDraftImage(form, file, '').then(function (draft) {
                    URL.revokeObjectURL(localUrl);
                    imageInput.value = '';
                    imageInput.required = false;
                    applyOrderUploadDraftImage(form, draft, '', { preserveExistingFields: true });
                    if (status) {
                        status.textContent = i18n('draftImageSaved', 'Image saved for this order.');
                    }
                }).catch(function (error) {
                    URL.revokeObjectURL(localUrl);
                    clearDraftImageFields(form);
                    setDraftImageControlState(form, false);
                    restoreOrderUploadPreview(form);
                    if (status) {
                        status.textContent = error.message || gridConfig.i18n.error;
                    }
                });
            });
        }

        if (fitMode) {
            fitMode.addEventListener('change', function () {
                var img = form.closest('.mds3-order-upload-panel') ? form.closest('.mds3-order-upload-panel').querySelector('.mds3-current-placement img') : null;
                if (img) {
                    img.style.objectFit = fitMode.value === 'contain' ? 'contain' : 'cover';
                }
            });
        }

        if (remove) {
            remove.addEventListener('click', function () {
                removeDraftImage(form, '').then(function () {
                    clearDraftImageFields(form);
                    setDraftImageControlState(form, false);
                    restoreOrderUploadPreview(form);
                    saveFormDraft(form, { preserveExistingFields: true });
                    if (status) {
                        status.textContent = i18n('draftImageRemoved', 'Draft image removed.');
                    }
                }).catch(function (error) {
                    if (status) {
                        status.textContent = error.message || i18n('draftImageRemoveError', 'Draft image could not be removed.');
                    }
                });
            });
        }

        fetchDraftImageState(form, '').then(function (draft) {
            if (!draft) {
                clearStoredServerDraft(form);
                return;
            }
            showDraftImageNotice(form, draft, function (serverDraft) {
                applyOrderUploadDraftImage(form, serverDraft, '', { preserveExistingFields: true });
                if (status) {
                    status.textContent = i18n('draftImagePreviewReady', 'Image preview updated.');
                }
            });
        }).catch(function () {});
    }

    function bindOrderUploadForms() {
        document.querySelectorAll('.mds3-order-upload-form').forEach(function (form) {
            function refreshVisibleMessage() {
                var current = form.querySelector(':scope > .mds3-form-message');
                var result;
                if (!current || current.hidden) {
                    return;
                }
                result = placementFormValidation(form, false, null);
                if (result.message) {
                    showFormMessage(form, result.message, result.field);
                } else {
                    hideFormMessage(form);
                }
            }

            bindAdvertiserUrlInputs(form);
            initializeRichTextEditors(form);
            bindOrderUploadDraftControls(form);
            bindFormDraft(form, 'upload:order:' + (form.getAttribute('data-mds3-order-id') || (form.querySelector('input[name="order_id"]') ? form.querySelector('input[name="order_id"]').value : '0')));
            form.addEventListener('input', refreshVisibleMessage);
            form.addEventListener('change', refreshVisibleMessage);
            form.addEventListener('submit', function (event) {
                var status = form.closest('.mds3-order-upload-panel') ? form.closest('.mds3-order-upload-panel').querySelector('.mds3-grid-status') : null;
                var payload;

                event.preventDefault();
                if (form.getAttribute('data-mds3-busy') === 'true') {
                    return;
                }
                if (!validatePlacementForm(form, true, null)) {
                    return;
                }

                syncRichTextEditors(form);
                payload = new FormData(form);
                payload.append('action', 'mds3_submit_placement');
                payload.append('nonce', gridConfig.nonce);

                if (status) {
                    status.textContent = gridConfig.i18n.savingAd;
                }
                setOrderUploadBusy(form, true, 'savingAd');

                request(payload).then(function (response) {
                    if (!response || !response.success) {
                        throw new Error(response && response.data && response.data.message ? response.data.message : gridConfig.i18n.error);
                    }

                    if (status) {
                        status.textContent = gridConfig.i18n.adSaved;
                    }
                    if (response.data && response.data.placement) {
                        updateOrderUploadPreview(form, response.data.placement);
                    }
                    clearFormDraft(form);
                    clearDraftImageFields(form);
                    setDraftImageControlState(form, false);
                    rememberOrderUploadPreview(form, true);
                    if (response.data && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                        return;
                    }
                    form.querySelectorAll('input[type="file"]').forEach(function (input) {
                        input.value = '';
                    });
                    setOrderUploadBusy(form, false);
                }).catch(function (error) {
                    setOrderUploadBusy(form, false);
                    if (status) {
                        status.textContent = error.message || gridConfig.i18n.error;
                    }
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        purgeExpiredDraftStorage();
        forceLightThemeChrome();
        window.setTimeout(forceLightThemeChrome, 150);
        window.setTimeout(forceLightThemeChrome, 750);
        bindOrderUploadForms();
        document.querySelectorAll('.mds3-grid-shell').forEach(function (element) {
            gridInstances.push(new Grid(element));
        });
    });
    window.addEventListener('load', forceLightThemeChrome);
}());
