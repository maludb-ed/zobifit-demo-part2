<?php
/**
 * The assistant command bar — on every screen, per chat-actions and the locked
 * design decisions. Voice-first: users dictate into it, so focus-fast matters
 * more than any mic button (Ctrl/Cmd+K, or "/" outside an input).
 *
 * Phase 2 ships the surface wired to a stub handler so every later screen
 * inherits it. Phase 4 replaces the stub with the Agent SDK service.
 *
 * Ids are fixed by the design system: #assistant-bar, #assistant-input,
 * #assistant-send-btn, #assistant-reply, #assistant-transcript.
 *
 * Screen context travels with every message — each screen partial stamps
 * #page-content with data-screen / data-entity / data-record-id, and hx-vals
 * reads them here. That is how "log that" and "add ten pounds to that" resolve.
 */
?>
<div id="assistant-reply" class="assistant-reply" aria-live="polite"></div>

<div id="assistant-bar" class="assistant-bar">
    <form id="assistant-form"
          hx-post="/assistant/message.php"
          hx-target="#assistant-reply"
          hx-swap="innerHTML"
          hx-vals='js:{
              screen:   document.getElementById("page-content")?.dataset.screen   || "",
              entity:   document.getElementById("page-content")?.dataset.entity   || "",
              recordId: document.getElementById("page-content")?.dataset.recordId || ""
          }'
          hx-indicator="#assistant-indicator"
          class="assistant-bar-form">

        <button type="button" class="btn btn-light assistant-bar-history" id="assistant-transcript-btn"
                data-bs-toggle="offcanvas" data-bs-target="#assistant-transcript"
                aria-label="Conversation history">
            <i class="feather-message-square"></i>
        </button>

        <input type="text" name="message" id="assistant-input" class="form-control assistant-bar-input"
               placeholder="Ask, or say what to record&hellip;  (Ctrl+K)" autocomplete="off">

        <span id="assistant-indicator" class="htmx-indicator assistant-bar-indicator">
            <span class="spinner-border spinner-border-sm text-primary"></span>
        </span>

        <button type="submit" class="btn btn-primary assistant-bar-send" id="assistant-send-btn" aria-label="Send">
            <i class="feather-send"></i>
        </button>
    </form>
</div>

<!-- Full history. Offcanvas is permitted here: a transcript is a quick view,
     not a form or a display page, and it is full-width on mobile. -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="assistant-transcript" aria-labelledby="assistant-transcript-title">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="assistant-transcript-title">Assistant</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body" id="assistant-transcript-body">
        <p class="text-muted fs-12 mb-0">Your conversation with the assistant appears here.</p>
    </div>
</div>
