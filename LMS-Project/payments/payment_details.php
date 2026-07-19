<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/lib/Auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../app/lib/helpers.php';
require_once __DIR__ . '/payment_helper.php';

Auth::requireRole(['admin']);

$teacherId = (int) ($_GET['teacher_id'] ?? 0);
if ($teacherId <= 0) {
    http_response_code(400);
    echo 'Invalid teacher_id';
    exit;
}

$pdo = db();
$teacherStmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = :id AND role = "teacher" LIMIT 1');
$teacherStmt->execute(['id' => $teacherId]);
$teacher = $teacherStmt->fetch();
if (!$teacher) {
    http_response_code(404);
    echo 'Teacher not found';
    exit;
}

$snapshot = getTeacherPayoutSummary($teacherId);

$paymentsStmt = $pdo->prepare('SELECT * FROM teacher_payments WHERE teacher_id = :id ORDER BY created_at DESC, id DESC LIMIT 100');
$paymentsStmt->execute(['id' => $teacherId]);
$payments = $paymentsStmt->fetchAll() ?: [];

$logsStmt = $pdo->prepare(
    'SELECT tpl.*, cs.title AS class_title, cs.start_datetime, cs.start_time_utc,
            cs.scheduled_time_utc, cs.scheduled_timezone, cs.timezone,
            cs.teacher_joined_at, cs.teacher_join_delay_minutes
     FROM teacher_payment_logs tpl
     INNER JOIN class_sessions cs ON cs.id = tpl.class_id
     WHERE tpl.teacher_id = :id
     ORDER BY tpl.created_at DESC, tpl.id DESC
     LIMIT 200'
);
$logsStmt->execute(['id' => $teacherId]);
$logs = $logsStmt->fetchAll() ?: [];

$appBase = rtrim((string) appWebPath(), '/');
$paymentsRouteBase = $appBase . '/admin/payments';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Payment Details</h1>
            <p class="small text-muted mb-0"><?= htmlspecialchars((string) $teacher['name']) ?> (<?= htmlspecialchars((string) $teacher['email']) ?>)</p>
        </div>
        <a href="<?= htmlspecialchars($paymentsRouteBase) ?>" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">Total</div><div class="h5 mb-0"><?= htmlspecialchars(formatCurrency((float) $snapshot['total_earnings'])) ?></div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">Paid</div><div class="h5 mb-0"><?= htmlspecialchars(formatCurrency((float) $snapshot['paid_amount'])) ?></div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">Balance</div><div class="h5 mb-0"><?= htmlspecialchars(formatCurrency((float) $snapshot['pending_amount'])) ?></div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">Status</div><div class="h5 mb-0 text-capitalize"><?= htmlspecialchars((string) $snapshot['status']) ?></div></div></div></div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header">Payment Entries</div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Paid</th><th>Status</th><th>Remarks</th></tr></thead><tbody>
            <?php if (empty($payments)): ?><tr><td colspan="4" class="text-muted p-3">No payment entries.</td></tr><?php else: foreach ($payments as $p): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($p['payment_date'] ?? $p['created_at'] ?? '')) ?></td>
                    <td><?= htmlspecialchars(formatCurrency((float) ($p['paid_amount'] ?? 0))) ?></td>
                    <td><?= htmlspecialchars((string) ($p['payment_status'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($p['remarks'] ?? '')) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody></table></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">Class Logs</div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Class</th><th>Scheduled Start</th><th>Actual Join</th><th>Late Status</th><th>Amount</th><th>Status</th><th>Created</th></tr></thead><tbody>
            <?php if (empty($logs)): ?><tr><td colspan="7" class="text-muted p-3">No class logs.</td></tr><?php else: foreach ($logs as $l): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($l['class_title'] ?? '')) ?></td>
                    <td><?= htmlspecialchars(formatClassScheduledAt($l, 'd M Y h:i A T')) ?></td>
                    <td><?= !empty($l['teacher_joined_at']) ? htmlspecialchars(formatUtcForTimezone((string) $l['teacher_joined_at'], classScheduledTimezone($l, APP_TIMEZONE), 'd M Y h:i A T')) : '<span class="text-muted">Not recorded</span>' ?></td>
                    <td><?php $lateText = teacherLateJoinNoticeText($l); echo $lateText !== null ? teacherLateJoinNoticeHtml($l, 'mb-0') : '<span class="text-success small">On time</span>'; ?></td>
                    <td><?= htmlspecialchars(formatCurrency((float) ($l['amount'] ?? 0))) ?></td>
                    <td><?= htmlspecialchars((string) ($l['status'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($l['created_at'] ?? '')) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody></table></div>
    </div>
</div>
</body>
</html>
