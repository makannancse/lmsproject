<?php

use function htmlspecialchars as h;

?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">System Settings</h1>
                <form method="post" action="<?= h(appWebPath() . '/settings') ?>">
                    <div class="mb-3">
                        <label for="payout_rate_per_hour" class="form-label">Payout Rate Per Hour (INR)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="payout_rate_per_hour"
                               name="payout_rate_per_hour" value="<?= h($payoutRatePerHour ?? '20') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="payout_rate_full_time" class="form-label">Default Full-time Rate (INR/hour)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="payout_rate_full_time"
                               name="payout_rate_full_time" value="<?= h($payoutRateFullTime ?? '30') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="payout_rate_part_time" class="form-label">Default Part-time Rate (INR/hour)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="payout_rate_part_time"
                               name="payout_rate_part_time" value="<?= h($payoutRatePartTime ?? '20') ?>">
                    </div>

                    <hr>
                    <h2 class="h6">Google Meet (Google Workspace)</h2>
                    <div class="mb-3">
                        <label for="google_client_id" class="form-label">Google Client ID</label>
                        <input type="text" class="form-control" id="google_client_id" name="google_client_id"
                               value="<?= h($googleClientId ?? '') ?>" placeholder="xxxxxxxx.apps.googleusercontent.com">
                    </div>
                    <div class="mb-3">
                        <label for="google_client_secret" class="form-label">Google Client Secret</label>
                        <input type="password" class="form-control" id="google_client_secret" name="google_client_secret"
                               placeholder="<?= !empty($googleClientSecretMasked) ? h($googleClientSecretMasked) : 'Leave blank to keep existing' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Required OAuth Redirect URI</label>
                        <input type="text" class="form-control" value="<?= h(googleOAuthRedirectUri()) ?>" readonly>
                        <div class="form-text">Use one shared Google OAuth app for all teachers. Add this exact URI in Google Cloud Console and use the <code>https://www.googleapis.com/auth/calendar</code> scope.</div>
                    </div>
                    <div class="mb-3">
                        <label for="google_calendar_id" class="form-label">Google Calendar ID</label>
                        <input type="text" class="form-control" id="google_calendar_id" name="google_calendar_id"
                               value="<?= h($googleCalendarId ?? 'primary') ?>" placeholder="primary">
                        <div class="form-text">Use <code>primary</code> so each Meet is created on the assigned teacher's own calendar and the teacher becomes organizer/host.</div>
                    </div>
                    <div class="mb-3">
                        <label for="google_workspace_domain" class="form-label">Google Workspace Domain</label>
                        <input type="text" class="form-control" id="google_workspace_domain" name="google_workspace_domain"
                               value="<?= h($googleWorkspaceDomain ?? '') ?>" placeholder="yourdomain.com">
                        <div class="form-text">Optional but recommended. If set, only teacher accounts from this domain can connect Google and host Meet sessions.</div>
                    </div>
                    <div class="mb-3">
                        <label for="static_meeting_link" class="form-label">Static Meeting Link (Fallback)</label>
                        <input type="url" class="form-control" id="static_meeting_link" name="static_meeting_link"
                               value="<?= h($staticMeetingLink ?? '') ?>" placeholder="https://meet.google.com/abc-defg-hij">
                        <div class="form-text">Used when API meeting creation fails or for local testing.</div>
                    </div>

                    <hr>
                    <h2 class="h6">SMTP Mail Settings</h2>
                    <div class="mb-3">
                        <label for="smtp_host" class="form-label">SMTP Host</label>
                        <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                               value="<?= h($smtpHost ?? '') ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="mb-3">
                        <label for="smtp_port" class="form-label">SMTP Port</label>
                        <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                               value="<?= h($smtpPort ?? '587') ?>" placeholder="587">
                    </div>
                    <div class="mb-3">
                        <label for="smtp_username" class="form-label">SMTP Username</label>
                        <input type="text" class="form-control" id="smtp_username" name="smtp_username"
                               value="<?= h($smtpUsername ?? '') ?>" placeholder="example@gmail.com">
                    </div>
                    <div class="mb-3">
                        <label for="smtp_password" class="form-label">SMTP Password / App Password</label>
                        <input type="password" class="form-control" id="smtp_password" name="smtp_password" placeholder="Leave blank to keep existing">
                    </div>
                    <div class="mb-3">
                        <label for="smtp_encryption" class="form-label">Encryption</label>
                        <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                            <option value="tls" <?= (($smtpEncryption ?? 'tls') === 'tls') ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= (($smtpEncryption ?? '') === 'ssl') ? 'selected' : '' ?>>SSL</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="mail_from" class="form-label">From Email</label>
                        <input type="email" class="form-control" id="mail_from" name="mail_from"
                               value="<?= h($mailFrom ?? '') ?>" placeholder="no-reply@example.com">
                    </div>
                    <div class="mb-3">
                        <label for="mail_from_name" class="form-label">From Name</label>
                        <input type="text" class="form-control" id="mail_from_name" name="mail_from_name"
                               value="<?= h($mailFromName ?? APP_NAME) ?>" placeholder="<?= h(APP_NAME) ?>">
                    </div>

                    <hr>
                    <h2 class="h6">Notification Emails</h2>
                    <div class="mb-3">
                        <label for="admin_notification_email" class="form-label">Admin Notification Email</label>
                        <input type="email" class="form-control" id="admin_notification_email" name="admin_notification_email"
                               value="<?= h($adminNotificationEmail ?? '') ?>" placeholder="Leave blank to use first active admin">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="notify_admin_class_scheduled" name="notify_admin_class_scheduled"
                               <?= ($notifyAdminClassScheduled ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notify_admin_class_scheduled">Email admin when classes are scheduled</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="notify_admin_reschedule" name="notify_admin_reschedule"
                               <?= ($notifyAdminReschedule ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notify_admin_reschedule">Email admin on reschedule requests</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="notify_teacher_student_assigned" name="notify_teacher_student_assigned"
                               <?= ($notifyTeacherStudentAssigned ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notify_teacher_student_assigned">Email teacher when a student is assigned</label>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>
