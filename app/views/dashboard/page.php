<?php
/**
 * Dashboard screen — the Phase 2 default: stat cards over one canonical table.
 *
 * Carries its OWN .page-header and .main-content and replaces the whole of
 * #page-content, so the sidebar, header and footer are never touched by a swap.
 *
 * #page-content is stamped with data-screen by the controller; that is how the
 * command bar knows which screen an utterance like "log that" refers to.
 *
 * Expected: $user, $stats, $activity, $roleLabel
 */
?>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Dashboard</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><?= e($roleLabel) ?></li>
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
                <a href="/ama/" class="btn btn-light-brand" id="dashboard-ama-btn"
                   hx-get="/ama/" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="/ama/">
                    <i class="feather-message-circle me-2"></i>
                    <span>Ask Me Anything</span>
                </a>
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
    <div class="row" id="dashboard-stats">
<?php foreach ($stats as $stat): ?>
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full" id="dashboard-stat-<?= e($stat['id']) ?>">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-lg bg-soft-<?= e($stat['colour']) ?> text-<?= e($stat['colour']) ?>">
                                <i class="<?= e($stat['icon']) ?>"></i>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold text-dark" id="dashboard-stat-<?= e($stat['id']) ?>-value"><?= e($stat['value']) ?></div>
                                <span class="fs-12 text-muted"><?= e($stat['label']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php endforeach; ?>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">Recent Activity</h5>
                    <div class="card-header-action">
                        <span class="fs-11 text-muted">From the activity log &mdash; the feed for activity memory</span>
                    </div>
                </div>
                <div class="card-body custom-card-action p-0">
<?php if ($activity === []): ?>
                    <div class="card-body text-center py-5">
                        <i class="feather-clock fs-1 mb-4"></i>
                        <p class="text-muted mb-0">No activity recorded yet.</p>
                    </div>
<?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="dashboard-activity-table">
                            <thead class="thead-light">
                                <tr>
                                    <th id="dashboard-activity-col-when">When</th>
                                    <th id="dashboard-activity-col-who">Who</th>
                                    <th id="dashboard-activity-col-what">Action</th>
                                    <th id="dashboard-activity-col-where" class="text-end">Screen</th>
                                </tr>
                            </thead>
                            <tbody>
<?php   foreach ($activity as $row): ?>
                                <tr id="activity-row-<?= e($row['id']) ?>">
                                    <td>
                                        <span><?= e(format_date($row['occurred_at'])) ?></span>
                                        <small class="text-muted"><?= e(format_time($row['occurred_at'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="wd-10 ht-10 bg-success me-2 d-inline-block rounded-circle"></span>
                                        <span><?= e($row['display_name'] ?? 'System') ?></span>
                                        <small class="text-muted"><?= e($row['actor_role']) ?></small>
                                    </td>
                                    <td><span class="badge bg-soft-info text-info"><?= e($row['action']) ?></span></td>
                                    <td class="text-end"><small class="text-muted"><?= e($row['screen'] ?? '—') ?></small></td>
                                </tr>
<?php   endforeach; ?>
                            </tbody>
                        </table>
                    </div>
<?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
