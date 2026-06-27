<?php

use function htmlspecialchars as h;

$base = appWebPath();
$class = $class ?? [];
$displayTimezone = $displayTimezone ?? APP_TIMEZONE;
$teacherGoogleEmail = (string) ($teacherGoogleEmail ?? '');
$openMeetUrl = (string) ($openMeetUrl ?? '');
$recordingWorkflowSupported = (bool) ($recordingWorkflowSupported ?? true);
$startLabel = formatUtcForTimezone(classStartUtcValue($class), $displayTimezone, 'd M Y h:i A T');
$endLabel = formatUtcForTimezone(classEndUtcValue($class), $displayTimezone, 'd M Y h:i A T');
$classStatus = strtolower(trim((string) ($class['status'] ?? 'scheduled')));
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <h1 class="h4 mb-1"><?= h((string) ($class['title'] ?? 'Launch Class')) ?></h1>
                        <p class="text-muted mb-0 small">You join as the Google Meet organizer (host).</p>
                        <?php if ($recordingWorkflowSupported): ?>
                            <span class="badge text-bg-success mt-2">Recording available (Workspace-style account)</span>
                        <? else: ?>
                            <span class="badge text-bg-warning text-dark mt-2">Recording not supported for personal Gmail</span>
                        <?php endif; ?>
                    </div>
                    <span class="badge rounded-pill text-bg-success px-3 py-2">Teacher Host</span>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                            <div class="small text-muted text-uppercase mb-1">Class Time</div>
                            <div class="fw-semibold"><?= h($startLabel) ?></div>
                            <div class="small text-muted">Ends <?= h($endLabel) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                            <div class="small text-muted text-uppercase mb-1">Meet host account</div>
                            <div class="fw-semibold"><?= h($teacherGoogleEmail !== '' ? $teacherGoogleEmail : 'Connected teacher account') ?></div>
                            <div class="small text-muted">Use this Google account when Meet asks you to sign in.</div>
                        </div>
                    </div>
                </div>

                <?php if ($openMeetUrl !== ''): ?>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <a href="<?= h($openMeetUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                            <i class="fa-solid fa-up-right-from-square me-1"></i> Open Meet in new tab
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($recordingWorkflowSupported): ?>
                    <div class="alert alert-warning mb-0">
                        Click <strong>Join Google Meet as Host</strong> below. After Meet opens, start recording
                        (Activities → Recording), then admit students. LearnWise marks the class
                        <strong>ongoing</strong> when you confirm; actual timings still sync from Google Meet.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        Click below to open Meet as host. Students can join once you have started the class from LearnWise.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade schedule-class-modal teacher-launch-modal" id="teacherLaunchModal" tabindex="-1" aria-labelledby="teacherLaunchModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title" id="teacherLaunchModalLabel"><?= $recordingWorkflowSupported ? h('Start Class') : h('Open Google Meet') ?></h5>
            </div>
            <div class="modal-body">
                <?php if ($recordingWorkflowSupported): ?>
                    <p class="mb-3">You will open Google Meet as the host. <strong>Start recording inside Meet</strong> before teaching (Activities → Recording → Start recording).</p>
                    <div class="border rounded-3 p-3 bg-light mb-3">
                        <div class="fw-semibold mb-2">Quick steps</div>
                        <ol class="small mb-0 ps-3">
                            <li>Click <strong>Join Google Meet as Host</strong> (LearnWise opens Meet).</li>
                            <li>In Meet: Activities → Recording → Start recording.</li>
                            <li>Admit students from the lobby when they arrive.</li>
                        </ol>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer" href="https://support.google.com/meet/answer/9308681">Recording help</a>
                        <?php if ($openMeetUrl !== ''): ?>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= h($openMeetUrl) ?>" target="_blank" rel="noopener noreferrer">Open Meet manually</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="mb-3">You will open Meet as host using your connected Google account.</p>
                <?php endif; ?>

                <?php if ($openMeetUrl === ''): ?>
                    <div class="alert alert-danger small">No meeting link is configured for this class. Ask an admin to edit the class or reconnect Google Calendar.</div>
                <?php else: ?>
                    <form method="post"
                          action="<?= h($base . '/meeting/track') ?>"
                          id="teacherLaunchForm"
                          class="no-app-loader"
                          data-loader-title="Opening Google Meet..."
                          data-loader-text="Starting your class session and redirecting to Meet.">
                        <input type="hidden" name="class_id" value="<?= (int) ($class['id'] ?? 0) ?>">
                        <input type="hidden" name="event" value="teacher-start">
                        <input type="hidden" name="recording_acknowledged" value="1">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg" id="teacherLaunchSubmit">
                                <i class="fa-solid fa-video me-1"></i>
                                <?= $recordingWorkflowSupported ? 'Join Google Meet as Host' : 'Open Google Meet as Host' ?>
                            </button>
                            <?php if ($recordingWorkflowSupported): ?>
                                <button type="button" class="btn btn-outline-secondary" id="teacherRemindLater">Remind me in 2 minutes</button>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="<?= h($base . '/assets/js/schedule-class-form.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.initLmsScheduleModals === 'function') {
        window.initLmsScheduleModals();
    }

    var modalEl = document.getElementById('teacherLaunchModal');
    var remindBtn = document.getElementById('teacherRemindLater');
    var launchForm = document.getElementById('teacherLaunchForm');
    if (!modalEl || typeof bootstrap === 'undefined') {
        return;
    }

    var modal = bootstrap.Modal.getOrCreateInstance(modalEl, {
        backdrop: 'static',
        keyboard: false
    });
    modal.show();

    var remindTimer;
    if (remindBtn) {
        remindBtn.addEventListener('click', function () {
            modal.hide();
            clearTimeout(remindTimer);
            remindTimer = setTimeout(function () {
                modal.show();
            }, 120000);
        });
    }

    if (launchForm) {
        launchForm.addEventListener('submit', function () {
            var submitBtn = document.getElementById('teacherLaunchSubmit');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Opening Meet…';
            }
            if (window.AppUI && typeof window.AppUI.showLoader === 'function') {
                window.AppUI.showLoader(
                    launchForm.dataset.loaderTitle || 'Opening Google Meet...',
                    launchForm.dataset.loaderText || 'Redirecting you to Meet as host.'
                );
            }
        });
    }
});
</script>
