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
if ($teacherId <= 0) {
    redirect('admin/payments?success=invalid_teacher');
}

if (isset($_POST['mark_paid'])) {
    $snapshot = getTeacherPayoutSummary($teacherId);
    if ((float) $snapshot['pending_amount'] > 0) {
        createTeacherPaymentEntry($teacherId, (float) $snapshot['pending_amount'], 'Marked paid from dashboard');
    }
    $suffix = $statusFilter !== '' ? ('&status=' . urlencode($statusFilter)) : '';
    redirect('admin/payments?success=paid' . $suffix);
}

if (isset($_POST['add_payment'])) {
    $amount = function_exists('parseInrAmount')
        ? parseInrAmount($_POST['advance_amount'] ?? 0)
        : round((float) ($_POST['advance_amount'] ?? 0), 2);
    $remarks = trim((string) ($_POST['remarks'] ?? 'Manual payment from dashboard'));
    if ($amount > 0) {
        createTeacherPaymentEntry($teacherId, $amount, $remarks);
    }
    $suffix = $statusFilter !== '' ? ('&status=' . urlencode($statusFilter)) : '';
    redirect('admin/payments?success=payment_added' . $suffix);
}

redirect('admin/payments?success=no_action');
