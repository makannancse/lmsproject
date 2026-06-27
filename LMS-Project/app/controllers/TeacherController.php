<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Pagination.php';
require_once dirname(__DIR__) . '/models/ClassSession.php';
require_once dirname(__DIR__) . '/models/TeacherGoogleAccount.php';
require_once dirname(__DIR__) . '/lib/PayoutService.php';
require_once dirname(__DIR__) . '/models/ClassRecording.php';
require_once dirname(__DIR__) . '/lib/GoogleAccountType.php';
require_once dirname(__DIR__) . '/models/TeacherStudent.php';

class TeacherController
{
    /** @var list<int> */
    private const UPCOMING_PER_PAGE_OPTIONS = [10, 25, 50];

    public static function dashboard(): void
    {
        Auth::requireRole(['teacher']);
        $user = Auth::user();
        $teacherId = (int) ($user['id'] ?? 0);

        $upcomingSearch = trim((string) ($_GET['upcoming_q'] ?? ''));
        $upcomingReq = Pagination::fromRequestParams(
            'upcoming_page',
            'upcoming_per_page',
            self::UPCOMING_PER_PAGE_OPTIONS,
            10
        );
        $upcomingResult = ClassSession::findUpcomingByTeacherPaginated(
            $teacherId,
            $upcomingReq['per_page'],
            $upcomingReq['offset'],
            $upcomingSearch !== '' ? $upcomingSearch : null
        );
        $upcoming = $upcomingResult['rows'];
        $upcomingPagination = Pagination::meta(
            $upcomingResult['total'],
            $upcomingReq['page'],
            $upcomingReq['per_page']
        );
        $upcomingQueryParams = array_filter([
            'upcoming_q' => $upcomingSearch !== '' ? $upcomingSearch : null,
        ], static fn($v) => $v !== null && $v !== '');

        $completed = ClassSession::findCompletedByTeacher($teacherId);
        $upcomingCount = ClassSession::countUpcomingAppointmentsForTeacher($teacherId);

        $payoutBreakdown = PayoutService::calculateTeacherPayout($teacherId);
        $totalPayout = (float) ($payoutBreakdown['total'] ?? 0);
        $googleAccount = TeacherGoogleAccount::findByTeacherId($teacherId);
        if ($googleAccount !== null) {
            $accountKindRaw = strtolower(trim((string) ($googleAccount['account_type'] ?? '')));
            if ($accountKindRaw !== 'workspace' && $accountKindRaw !== 'personal') {
                $accountKindRaw = GoogleAccountType::profileFromEmail(
                    isset($googleAccount['google_email']) ? (string) $googleAccount['google_email'] : null
                )['account_type'];
            }
            $googleRecordingCapability = TeacherGoogleAccount::recordingSupportedFromAccountRow($googleAccount);
        } else {
            $accountKindRaw = 'workspace';
            $googleRecordingCapability = false;
        }
        $recordings = ClassRecording::listForTeacher($teacherId, 12);
        $assignedStudents = TeacherStudent::assignedStudentsDetailed($teacherId);

        View::render('teacher/dashboard', [
            'pageTitle' => 'Teacher Dashboard',
            'upcomingClasses' => $upcoming,
            'upcomingCount' => $upcomingCount,
            'upcomingPagination' => $upcomingPagination,
            'upcomingSearch' => $upcomingSearch,
            'upcomingQueryParams' => $upcomingQueryParams,
            'completedClasses' => $completed,
            'assignedStudents' => $assignedStudents,
            'totalPayout' => $totalPayout,
            'payoutBreakdown' => $payoutBreakdown,
            'googleAccount' => $googleAccount,
            'teacherGoogleAccountKind' => $accountKindRaw,
            'teacherGoogleRecordingCapability' => $googleRecordingCapability,
            'recordings' => $recordings,
        ]);
    }
}
