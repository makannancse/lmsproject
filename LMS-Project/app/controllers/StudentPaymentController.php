<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/models/StudentPayment.php';
require_once dirname(__DIR__) . '/models/User.php';
require_once dirname(__DIR__) . '/lib/Pagination.php';

class StudentPaymentController
{
    /**
     * @return array<string, mixed>
     */
    private static function extractAdminFilters(): array
    {
        $status = trim((string) ($_GET['status'] ?? ''));
        if (!in_array($status, ['pending', 'paid'], true)) {
            $status = '';
        }

        return [
            'status' => $status,
            'student_id' => (int) ($_GET['student_id'] ?? 0),
            'teacher_id' => (int) ($_GET['teacher_id'] ?? 0),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
    }

    public static function adminIndex(): void
    {
        Auth::requireRole(['admin']);
        $filters = self::extractAdminFilters();
        $req = Pagination::fromRequest();

        $total = StudentPayment::countForAdmin($filters);
        $payments = StudentPayment::listForAdmin($filters, null, $req['per_page'], $req['offset']);
        $summary = StudentPayment::sumForAdmin($filters);
        $pagination = Pagination::meta($total, $req['page'], $req['per_page']);

        $queryParams = array_filter([
            'status' => $filters['status'] !== '' ? $filters['status'] : null,
            'student_id' => $filters['student_id'] > 0 ? $filters['student_id'] : null,
            'teacher_id' => $filters['teacher_id'] > 0 ? $filters['teacher_id'] : null,
            'date_from' => $filters['date_from'] !== '' ? $filters['date_from'] : null,
            'date_to' => $filters['date_to'] !== '' ? $filters['date_to'] : null,
            'q' => $filters['q'] !== '' ? $filters['q'] : null,
        ]);

        View::render('payments/admin_index', [
            'pageTitle' => 'Student Payments',
            'payments' => $payments,
            'filters' => $filters,
            'statusFilter' => $filters['status'],
            'summary' => $summary,
            'students' => User::allStudents(),
            'teachers' => User::allTeachers(),
            'pagination' => $pagination,
            'queryParams' => $queryParams,
        ]);
    }

    public static function exportPdf(): void
    {
        Auth::requireRole(['admin']);
        require_once dirname(__DIR__) . '/lib/StudentPaymentPdfService.php';

        $filters = self::extractAdminFilters();
        // Fetch all matching records without pagination for PDF statement
        $payments = StudentPayment::listForAdmin($filters);
        $summary = StudentPayment::sumForAdmin($filters);

        StudentPaymentPdfService::streamPdf($payments, $summary, $filters);
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

