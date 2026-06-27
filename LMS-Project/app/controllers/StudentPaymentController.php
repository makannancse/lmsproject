<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/models/StudentPayment.php';
require_once dirname(__DIR__) . '/models/User.php';
require_once dirname(__DIR__) . '/lib/Pagination.php';

class StudentPaymentController
{
    public static function adminIndex(): void
    {
        Auth::requireRole(['admin']);
        $status = trim((string) ($_GET['status'] ?? ''));
        if (!in_array($status, ['pending', 'paid'], true)) {
            $status = '';
        }
        $req = Pagination::fromRequest();
        $statusFilter = $status !== '' ? $status : null;
        $total = StudentPayment::countForAdmin($statusFilter);
        $payments = StudentPayment::listForAdmin($statusFilter, null, $req['per_page'], $req['offset']);
        $pagination = Pagination::meta($total, $req['page'], $req['per_page']);

        View::render('payments/admin_index', [
            'pageTitle' => 'Student Payments',
            'payments' => $payments,
            'statusFilter' => $status,
            'students' => User::allStudents(),
            'pagination' => $pagination,
            'queryParams' => array_filter(['status' => $status !== '' ? $status : null]),
        ]);
    }

    public static function markPaid(): void
    {
        Auth::requireRole(['admin']);
        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        if ($paymentId > 0) {
            StudentPayment::markPaid($paymentId);
        }
        $base = appWebPath();
        redirectTo('/admin/student-payments');
    }

    public static function studentIndex(): void
    {
        Auth::requireRole(['student']);
        $status = trim((string) ($_GET['status'] ?? ''));
        if (!in_array($status, ['pending', 'paid'], true)) {
            $status = '';
        }
        $studentId = (int) (Auth::user()['id'] ?? 0);
        $req = Pagination::fromRequest();
        $statusFilter = $status !== '' ? $status : null;
        $total = StudentPayment::countForStudent($studentId, $statusFilter);
        $payments = StudentPayment::listForStudent($studentId, $statusFilter, $req['per_page'], $req['offset']);
        $pagination = Pagination::meta($total, $req['page'], $req['per_page']);

        View::render('payments/student_index', [
            'pageTitle' => 'My Payments',
            'payments' => $payments,
            'statusFilter' => $status,
            'pagination' => $pagination,
            'queryParams' => array_filter(['status' => $status !== '' ? $status : null]),
        ]);
    }
}

