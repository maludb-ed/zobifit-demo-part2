<?php
/**
 * Phase 2 navigation stub.
 *
 * Every planned screen is reachable from the first day so the shell can be
 * reviewed as a whole and the assistant's screen registry resolves to something
 * real. Phase 3 replaces these one slice at a time.
 *
 * Expected: $screen (a screen_registry() entry), $slice
 */
?>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10"><?= e($screen['label']) ?></h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
<?php if (!empty($screen['group'])): ?>
            <li class="breadcrumb-item"><?= e($screen['group']) ?></li>
<?php endif; ?>
            <li class="breadcrumb-item"><?= e($screen['label']) ?></li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none">
                <a href="javascript:void(0)" class="page-header-right-close-toggle">
                    <i class="feather-arrow-left me-2"></i>
                    <span>Back</span>
                </a>
            </div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <span class="badge bg-soft-dark text-dark">Phase 3</span>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center">
            <a href="javascript:void(0)" class="page-header-right-open-toggle">
                <i class="feather-align-right fs-20"></i>
            </a>
        </div>
    </div>
</div>

<div class="main-content">
    <div class="row">
        <div class="col-lg-12">
            <div class="card stretch stretch-full">
                <div class="card-body text-center py-5">
                    <i class="<?= e($screen['icon']) ?> fs-1 mb-4"></i>
                    <h6 class="mb-2"><?= e($screen['label']) ?></h6>
                    <p class="text-muted mb-1"><?= e(ucfirst($screen['when'])) ?>.</p>
<?php if ($slice !== null): ?>
                    <p class="fs-12 text-muted mb-4">Built in Phase 3, slice <?= e($slice) ?>.</p>
<?php else: ?>
                    <p class="fs-12 text-muted mb-4">Built in a later phase.</p>
<?php endif; ?>
                    <a href="/" class="btn btn-sm btn-primary"
                       hx-get="/" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="/">
                        Back to dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
