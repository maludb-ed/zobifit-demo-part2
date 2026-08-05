<?php
/**
 * One exchange in the command bar, rendered above the input.
 *
 * Replies stay to one short sentence — this surface is a command bar, not a
 * chat page. Long AMA conversations belong on the AMA screen.
 *
 * Expected: $message, $reply, $screen
 */
?>
<div class="assistant-reply-inner">
    <div class="card shadow-sm mb-0">
        <div class="card-body py-2 px-3">
            <div class="fs-11 text-muted mb-1">
                <i class="feather-corner-down-right me-1"></i><?= e($message) ?>
<?php if ($screen !== ''): ?>
                <span class="badge bg-soft-secondary text-secondary ms-2"><?= e($screen) ?></span>
<?php endif; ?>
            </div>
            <div class="fs-12 text-dark d-flex align-items-start justify-content-between gap-3">
                <span><?= e($reply) ?></span>
                <button type="button" class="btn btn-sm btn-light flex-shrink-0"
                        onclick="document.getElementById('assistant-reply').innerHTML='';"
                        aria-label="Dismiss">
                    <i class="feather-x"></i>
                </button>
            </div>
        </div>
    </div>
</div>
