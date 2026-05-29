<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';
$class = $class ?? [];
$displayTimezone = $displayTimezone ?? APP_TIMEZONE;
$teacherGoogleEmail = (string) ($teacherGoogleEmail ?? '');
$recordingWorkflowSupported = (bool) ($recordingWorkflowSupported ?? true);
$startLabel = formatUtcForTimezone(classStartUtcValue($class), $displayTimezone, 'd M Y h:i A T');
$endLabel = formatUtcForTimezone(classEndUtcValue($class), $displayTimezone, 'd M Y h:i A T');
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
                        <?php else: ?>
                            <span class="badge text-bg-warning text-dark mt-2">⚠ Recording not supported for personal Gmail accounts</span>
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
                            <div class="small text-muted">Students stay in the waiting room until you open the class below.</div>
                        </div>
                    </div>
                </div>

                <?php if ($recordingWorkflowSupported): ?>
                    <div class="alert alert-warning mb-0">
                        Google Meet does not allow apps to start recording automatically. LearnWise records the
                        <strong>actual class start</strong> only after Google Meet reports that you really joined as host.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        Your Google account can host this Meet. Cloud recording and LearnWise Drive sync are available with
                        <strong>Google Workspace</strong> accounts only.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="teacherLaunchModal" tabindex="-1" aria-labelledby="teacherLaunchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title" id="teacherLaunchModalLabel"><?= $recordingWorkflowSupported ? h('Start Class Recording') : h('Start Class') ?></h5>
            </div>
            <div class="modal-body">
                <?php if ($recordingWorkflowSupported): ?>
                    <p class="mb-3">Please click <strong>Start recording</strong> in Google Meet before you begin teaching.</p>
                    <div class="border rounded-3 p-3 bg-light mb-3">
                        <div class="fw-semibold mb-2">Quick steps</div>
                        <ol class="small mb-0 ps-3">
                            <li>Join the meeting as host (you will be redirected after you confirm).</li>
                            <li>Activities → Recording → Start recording.</li>
                            <li>Then admit students from the lobby.</li>
                        </ol>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer" href="https://support.google.com/meet/answer/9308681">Recording help</a>
                    </div>
                <?php else: ?>
                    <p class="mb-3"><strong>Personal Gmail Meet session</strong> — you will open Meet as host. LearnWise does not require a recording acknowledgement for Gmail accounts.</p>
                    <p class="small text-muted mb-3">Meeting scheduling works with Gmail; advanced recording workflows need Google Workspace.</p>
                <?php endif; ?>
                <form method="post" action="<?= h($base . '/meeting/track') ?>" id="teacherLaunchForm">
                    <input type="hidden" name="class_id" value="<?= (int) ($class['id'] ?? 0) ?>">
                    <input type="hidden" name="event" value="teacher-start">
                    <input type="hidden" name="recording_acknowledged" value="1">
                    <div class="d-grid gap-2">
                        <?php if ($recordingWorkflowSupported): ?>
                            <button type="submit" class="btn btn-success">✅ I Started Recording</button>
                            <button type="button" class="btn btn-outline-secondary" id="teacherRemindLater">❌ Remind Me Again</button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-primary">Open Google Meet as Host</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('teacherLaunchModal');
    var remindBtn = document.getElementById('teacherRemindLater');
    if (!modalEl || typeof bootstrap === 'undefined') {
        return;
    }
    var modal = new bootstrap.Modal(modalEl, {
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
});
</script>
