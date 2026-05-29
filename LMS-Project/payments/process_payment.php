<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/lib/Auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/payment_helper.php';

Auth::requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$teacherId = (int) ($_POST['teacher_id'] ?? 0);
$statusFilter = trim((string) ($_POST['status'] ?? ''));
$basePath = rtrim((string) (defined('BASE_PATH') ? BASE_PATH : ''), '/');
$redirectBase = $basePath . '/admin/payments';
if ($teacherId <= 0) {
    header('Location: ' . $redirectBase . '?success=invalid_teacher');
    exit;
}

if (isset($_POST['mark_paid'])) {
    $snapshot = getTeacherPayoutSummary($teacherId);
    if ((float) $snapshot['pending_amount'] > 0) {
        createTeacherPaymentEntry($teacherId, (float) $snapshot['pending_amount'], 'Marked paid from dashboard');
    }
    $suffix = $statusFilter !== '' ? ('&status=' . urlencode($statusFilter)) : '';
    header('Location: ' . $redirectBase . '?success=paid' . $suffix);
    exit;
}

if (isset($_POST['add_payment'])) {
    $amount = (float) ($_POST['advance_amount'] ?? 0);
    $remarks = trim((string) ($_POST['remarks'] ?? 'Manual payment from dashboard'));
    if ($amount > 0) {
        createTeacherPaymentEntry($teacherId, $amount, $remarks);
    }
    $suffix = $statusFilter !== '' ? ('&status=' . urlencode($statusFilter)) : '';
    header('Location: ' . $redirectBase . '?success=payment_added' . $suffix);
    exit;
}

header('Location: ' . $redirectBase . '?success=no_action');
exit;
