(function () {
    'use strict';

    var ALLOWED_INPUT_TYPES = new Set(['text', 'search', 'tel']);

    function hasPreserveCaseFlag(element) {
        if (!element || !element.closest) {
            return false;
        }

        return element.dataset.preserveCase === 'true'
            || element.dataset.allowLowercase === 'true'
            || Boolean(element.closest('[data-preserve-case="true"], [data-allow-lowercase="true"]'));
    }

    function isUppercaseField(element) {
        if (!element || hasPreserveCaseFlag(element) || element.disabled || element.readOnly) {
            return false;
        }

        if (element instanceof HTMLTextAreaElement) {
            return true;
        }

        if (element instanceof HTMLInputElement) {
            var type = String(element.type || 'text').toLowerCase();
            return ALLOWED_INPUT_TYPES.has(type);
        }

        return false;
    }

    function uppercaseValue(element) {
        if (!isUppercaseField(element)) {
            return;
        }

        var original = element.value;
        var upper = original.toLocaleUpperCase('es-EC');

        if (original === upper) {
            return;
        }

        var start = null;
        var end = null;
        var direction = null;

        try {
            start = element.selectionStart;
            end = element.selectionEnd;
            direction = element.selectionDirection;
        } catch (error) {
            start = null;
        }

        element.value = upper;

        if (start !== null) {
            try {
                element.setSelectionRange(start, end, direction || 'none');
            } catch (error) {
                // Algunos tipos de input no permiten controlar la seleccion.
            }
        }
    }

    function uppercaseEditable(element) {
        if (!element || !element.isContentEditable || hasPreserveCaseFlag(element)) {
            return;
        }

        var walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
        var node;

        while ((node = walker.nextNode())) {
            var upper = String(node.nodeValue || '').toLocaleUpperCase('es-EC');
            if (node.nodeValue !== upper) {
                node.nodeValue = upper;
            }
        }
    }

    function normalizeElement(element) {
        if (isUppercaseField(element)) {
            uppercaseValue(element);
            return;
        }

        if (element && element.isContentEditable) {
            uppercaseEditable(element);
        }
    }

    function normalizeForm(form) {
        if (!form || !form.querySelectorAll) {
            return;
        }

        form.querySelectorAll('input, textarea, [contenteditable="true"]').forEach(normalizeElement);
    }

    document.addEventListener('input', function (event) {
        normalizeElement(event.target);
    }, true);

    document.addEventListener('change', function (event) {
        normalizeElement(event.target);
    }, true);

    document.addEventListener('submit', function (event) {
        normalizeForm(event.target);
    }, true);

    function normalizePage() {
        document.querySelectorAll('input, textarea, [contenteditable="true"]').forEach(normalizeElement);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', normalizePage, { once: true });
    } else {
        normalizePage();
    }
})();
