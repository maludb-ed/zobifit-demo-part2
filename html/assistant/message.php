<?php
declare(strict_types=1);

/**
 * Command-bar handler — STUB.
 *
 * Phase 2 ships the surface so every screen inherits it from the first render.
 * Phase 4 replaces the body of this file with a call to the unified assistant
 * service (Claude Agent SDK), which routes the utterance to the actions MCP
 * server and returns a navigate directive, a data-action result, or an answer.
 *
 * What is already real and must not change in Phase 4:
 *   - session auth and CSRF on the way in
 *   - the activity-log row for every utterance
 *   - screen context (screen / entity / record id) arriving with the message
 *   - the reply shape: one short sentence in #assistant-reply
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

require_post();
verify_csrf();

$user = require_login();
$pdo  = db();

$message  = request_string('message', 2000);
$screen   = request_string('screen', 64);
$entity   = request_string('entity', 64);
$recordId = request_integer('recordId');

if ($message === '') {
    http_response_code(400);
    exit;
}

// Every utterance is logged exactly like a click, because in Phase 4 it becomes
// one — the assistant acts through these same endpoints.
log_activity(
    $pdo,
    'assistant_message',
    $screen !== '' ? $screen : ($_SERVER['HTTP_HX_CURRENT_URL'] ?? null),
    $entity !== '' ? $entity : null,
    $recordId,
    null,
    ['message' => $message]
);

$reply = 'The assistant is not connected yet — that arrives in Phase 4. '
       . 'I recorded what you said so the routing can be tested against it.';

echo view('partials/assistant-reply.php', [
    'message' => $message,
    'reply'   => $reply,
    'screen'  => $screen,
]);
