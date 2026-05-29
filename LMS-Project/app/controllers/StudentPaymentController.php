<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/models/StudentPayment.php';
require_once dirname(__DIR__) . '/models/User.php';

class StudentPaymentController
{
    public static function adminIndex(): void
    {
        Auth::requireRole(['admin']);
        $status = trim((string) ($_GET['status'] ?? ''));
        if (!in_array($status, ['pending', 'paid'], true)) {
            $status = '';
        }

        View::render('payments/admin_index', [
            'pageTitle' => 'Student Payments',
            'payments' => StudentPayment::listForAdmin($status !== '' ? $status : null),
            'statusFilter' => $status,
            'students' => User::allStudents(),
        ]);
    }

    public static function markPaid(): void
    {
        Auth::requireRole(['admin']);
        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        if ($paymentId > 0) {
            StudentPayment::markPaid($paymentId);
        }
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        header('Location: ' . $base . '/admin/student-payments');
    }

    public static function studentIndex(): void
    {
        Auth::requireRole(['student']);
        $status = trim((string) ($_GET['status'] ?? ''));
        if (!in_array($status, ['pending', 'paid'], true)) {
            $status = '';
        }
        $studentId = (int) (Auth::user()['id'] ?? 0);

        View::render('payments/student_index', [
            'pageTitle' => 'My Payments',
            'payments' => StudentPayment::listForStudent($studentId, $status !== '' ? $status : null),
            'statusFilter' => $status,
        ]);
    }
}

