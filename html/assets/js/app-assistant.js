/*
 * Command bar behaviour (chat-actions).
 *
 * Voice-first: users dictate with tools like Wispr Flow, which type into the
 * focused field — so focusing fast matters more than any mic button.
 */
(function () {
    'use strict';

    var input = document.getElementById('assistant-input');
    var form  = document.getElementById('assistant-form');

    if (!input || !form) { return; }

    /*
     * Ctrl/Cmd+K anywhere, or "/" when the user is not already typing into
     * something. The "/" shortcut must never steal a keystroke from a real
     * field, so inputs, textareas and contenteditable regions are exempt.
     */
    document.addEventListener('keydown', function (event) {
        var typingTarget = event.target.closest('input, textarea, select, [contenteditable="true"]');

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            input.focus();
            input.select();
            return;
        }

        if (event.key === '/' && !typingTarget) {
            event.preventDefault();
            input.focus();
        }
    });

    /* Clear and refocus after send, ready for the next utterance. */
    form.addEventListener('htmx:afterRequest', function (event) {
        if (event.detail.successful) {
            input.value = '';
            input.focus();
        }
    });

    /*
     * Navigation directives. The assistant service returns HX-Location for a
     * navigate tool call, which HTMX performs itself — nothing to do here but
     * keep the reply readable while it happens.
     *
     * Data actions arrive as HX-Trigger events instead; screens listen for
     * their own entity's event and refresh their own region, so the assistant
     * never needs to know a screen's markup.
     */
    document.body.addEventListener('htmx:beforeSwap', function (event) {
        if (event.detail.xhr && event.detail.xhr.getResponseHeader('HX-Location')) {
            input.blur();
        }
    });
})();
