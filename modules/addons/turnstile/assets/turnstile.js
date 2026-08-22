/**
 * Mounts a Cloudflare Turnstile widget into the relevant client area form.
 *
 * Configuration is read from the data-config attribute of this script tag, so
 * no inline script is required. The widget is rendered explicitly, which keeps
 * it out of forms it does not belong to.
 */
(function () {
    'use strict';

    var CALLBACK_NAME = 'turnstileCaptchaOnLoad';
    var MOUNTED_ATTRIBUTE = 'data-turnstile-mounted';

    var loader = document.currentScript || document.getElementById('turnstile-captcha-loader');
    if (!loader) {
        return;
    }

    var config;
    try {
        config = JSON.parse(loader.getAttribute('data-config') || '{}');
    } catch (error) {
        return;
    }

    if (!config.siteKey) {
        return;
    }

    var submitSelector = (config.submitSelectors || []).join(',');

    /**
     * Resolves the form to protect, preferring the selectors supplied by the
     * module and falling back to the form with the most fields on the page.
     */
    function resolveForm() {
        var selectors = config.formSelectors || [];

        for (var i = 0; i < selectors.length; i++) {
            var match = document.querySelector(selectors[i]);
            if (match) {
                var form = match.tagName === 'FORM' ? match : match.closest('form');
                if (form) {
                    return form;
                }
            }
        }

        return largestForm();
    }

    function largestForm() {
        var best = null;
        var bestScore = 0;

        Array.prototype.forEach.call(document.forms, function (form) {
            if (form.getAttribute('role') === 'search' || /search/i.test(form.getAttribute('action') || '')) {
                return;
            }

            if (!form.querySelector(submitSelector)) {
                return;
            }

            var score = form.querySelectorAll('input, select, textarea').length;
            if (score > bestScore) {
                best = form;
                bestScore = score;
            }
        });

        return best;
    }

    function createContainer(form) {
        var container = document.createElement('div');
        container.className = 'turnstile-captcha';
        container.style.margin = '15px 0';

        var submit = submitSelector ? form.querySelector(submitSelector) : null;
        if (submit && submit.parentNode) {
            submit.parentNode.insertBefore(container, submit);
        } else {
            form.appendChild(container);
        }

        return container;
    }

    function showAlert(form) {
        if (!config.alert || !form.parentNode) {
            return;
        }

        var alert = document.createElement('div');
        alert.className = 'alert alert-danger';
        alert.setAttribute('role', 'alert');
        alert.textContent = config.alert;

        form.parentNode.insertBefore(alert, form);
    }

    function mount() {
        if (!window.turnstile || typeof window.turnstile.render !== 'function') {
            return;
        }

        var form = resolveForm();
        if (!form || form.hasAttribute(MOUNTED_ATTRIBUTE)) {
            return;
        }

        form.setAttribute(MOUNTED_ATTRIBUTE, 'true');
        showAlert(form);

        window.turnstile.render(createContainer(form), {
            sitekey: config.siteKey,
            theme: config.theme || 'auto',
            size: config.size || 'normal',
            action: config.action || '',
            'response-field-name': config.responseField,
            'refresh-expired': 'auto',
            retry: 'auto'
        });
    }

    window[CALLBACK_NAME] = mount;

    // The API script may already have executed, for example when a template
    // moves scripts around or a cache serves it early.
    if (window.turnstile && typeof window.turnstile.render === 'function') {
        mount();
    }
})();
