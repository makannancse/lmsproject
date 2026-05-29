<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/models/ClassSession.php';
require_once dirname(__DIR__) . '/models/TeacherPayout.php';
require_once dirname(__DIR__) . '/models/TeacherGoogleAccount.php';
require_once dirname(__DIR__) . '/lib/PayoutService.php';
require_once dirname(__DIR__) . '/models/ClassRecording.php';
require_once dirname(__DIR__) . '/lib/GoogleAccountType.php';

class TeacherController
{
    public static function dashboard(): void
    {
        Auth::requireRole(['teacher']);
        $user = Auth::user();
        $teacherId = (int) ($user['id'] ?? 0);

        $upcoming = ClassSession::findUpcomingByTeacher($teacherId);
        $completed = ClassSession::findCompletedByTeacher($teacherId);

        $totalPayout = TeacherPayout::totalForTeacher($teacherId, null);
        $payoutBreakdown = PayoutService::calculateTeacherPayout($teacherId);
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

        View::render('teacher/dashboard', [
            'pageTitle' => 'Teacher Dashboard',
            'upcomingClasses' => $upcoming,
            'completedClasses' => $completed,
            'totalPayout' => $totalPayout,
            'payoutBreakdown' => $payoutBreakdown,
            'googleAccount' => $googleAccount,
            'teacherGoogleAccountKind' => $accountKindRaw,
            'teacherGoogleRecordingCapability' => $googleRecordingCapability,
            'recordings' => $recordings,
        ]);
    }
}
