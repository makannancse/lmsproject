<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Mailer.php';
require_once dirname(__DIR__) . '/models/StudentReport.php';
require_once dirname(__DIR__) . '/lib/ReportLog.php';
require_once dirname(__DIR__, 2) . '/reports/generate_report_pdf.php';

class ReportController
{
    private const GOOGLE_FORM_URL = 'https://docs.google.com/forms/d/e/1FAIpQLSfLHvJ5ks4z-XLFcPxzu_LsMfhdQjUed5gTW_57dl6hlQtTIQ/viewform';

    private const PERFORMANCE_OPTIONS = ['Excellent', 'Good', 'Average', 'Need Improvement', 'Other'];
    private const UNDERSTANDING_OPTIONS = ['Strong understanding', 'Basic understanding', 'Needs support'];
    private const APPLICATION_OPTIONS = ['Applies independently', 'Needs guidance', 'Struggles to apply'];
    private const HOMEWORK_OPTIONS = ['Always on time', 'Sometimes late', 'Often incomplete', 'Other'];
    private const ATTENTION_OPTIONS = ['Highly attentive', 'Moderately attentive', 'Easily distracted', 'Other'];
    private const PARTICIPATION_OPTIONS = ['Active', 'Moderate', 'Passive', 'Other'];
    private const BEHAVIOUR_OPTIONS = ['Excellent', 'Good', 'Needs Improvement', 'Other'];

    public static function createForm(): void
    {
        Auth::requireRole(['teacher', 'admin']);
        $user = Auth::user();
        $role = (string) ($user['role'] ?? '');

        $students = $role === 'admin' ? StudentReport::allStudents() : StudentReport::teacherStudents((int) ($user['id'] ?? 0));
        $teachers = $role === 'admin' ? StudentReport::allTeachers() : [];

        View::render('reports/create', [
            'pageTitle' => 'Create Report',
            'students' => $students,
            'teachers' => $teachers,
            'googleFormUrl' => self::GOOGLE_FORM_URL,
            'errors' => [],
            'old' => [],
            'options' => self::fieldOptions(),
        ]);
    }

    public static function store(): void
    {
        Auth::requireRole(['teacher', 'admin']);
        $user = Auth::user();
        $role = (string) ($user['role'] ?? '');
        $base = defined('BASE_PATH') ? BASE_PATH : '';

        $studentId = (int) ($_POST['student_id'] ?? 0);
        $teacherId = $role === 'admin' ? (int) ($_POST['teacher_id'] ?? 0) : (int) ($user['id'] ?? 0);
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $overallPerformance = trim((string) ($_POST['overall_performance'] ?? ''));
        $conceptUnderstanding = trim((string) ($_POST['concept_understanding'] ?? ''));
        $applicationAbility = trim((string) ($_POST['application_ability'] ?? ''));
        $homeworkCompletion = trim((string) ($_POST['homework_completion'] ?? ''));
        $attentionLevel = trim((string) ($_POST['attention_level'] ?? ''));
        $participationLevel = trim((string) ($_POST['participation_level'] ?? ''));
        $behaviour = trim((string) ($_POST['behaviour'] ?? ''));
        $subjectsAddressed = trim((string) ($_POST['subjects_addressed'] ?? ''));
        $futureFocus = trim((string) ($_POST['future_focus'] ?? ''));
        $recommendedFocus = trim((string) ($_POST['recommended_focus'] ?? ''));
        $studyStrategies = trim((string) ($_POST['study_strategies'] ?? ''));
        $additionalSupport = trim((string) ($_POST['additional_support'] ?? ''));
        $overallFeedback = trim((string) ($_POST['overall_feedback'] ?? ''));
        $reportDate = trim((string) ($_POST['report_date'] ?? ''));

        $errors = [];
        if ($studentId <= 0) {
            $errors[] = 'Student Name is required.';
        }
        if ($teacherId <= 0) {
            $errors[] = 'Teacher Name is required.';
        }
        if ($subject === '') {
            $errors[] = 'Subject is required.';
        }
        if ($reportDate === '') {
            $errors[] = 'Report Date is required.';
        }

        $errors = array_merge($errors, self::validateChoice($overallPerformance, self::PERFORMANCE_OPTIONS, 'Overall Academic Performance'));
        $errors = array_merge($errors, self::validateChoice($conceptUnderstanding, self::UNDERSTANDING_OPTIONS, 'Level of Concept Understanding'));
        $errors = array_merge($errors, self::validateChoice($applicationAbility, self::APPLICATION_OPTIONS, 'Ability to Apply Concepts and Knowledge Retention'));
        $errors = array_merge($errors, self::validateChoice($homeworkCompletion, self::HOMEWORK_OPTIONS, 'Homework Completion'));
        $errors = array_merge($errors, self::validateChoice($attentionLevel, self::ATTENTION_OPTIONS, 'Attention During Class'));
        $errors = array_merge($errors, self::validateChoice($participationLevel, self::PARTICIPATION_OPTIONS, 'Participation Level'));
        $errors = array_merge($errors, self::validateChoice($behaviour, self::BEHAVIOUR_OPTIONS, 'Behaviour & Discipline'));

        foreach ([
            'Subjects Addressed' => $subjectsAddressed,
            'Future Focus' => $futureFocus,
            'Recommended Areas for Focus' => $recommendedFocus,
            'Suggested Study Strategies' => $studyStrategies,
            'Additional Support Required' => $additionalSupport,
            'Overall Feedback' => $overallFeedback,
        ] as $label => $value) {
            if ($value === '') {
                $errors[] = $label . ' is required.';
            }
        }

        $studentProfile = StudentReport::getStudentProfile($studentId);
        if (!$studentProfile) {
            $errors[] = 'Invalid student selected.';
        }
        $teacherProfile = StudentReport::getTeacherProfile($teacherId);
        if (!$teacherProfile) {
            $errors[] = 'Invalid teacher selected.';
        }

        if ($role === 'teacher') {
            $allowed = array_map(static fn(array $r): int => (int) $r['id'], StudentReport::teacherStudents((int) ($user['id'] ?? 0)));
            if (!in_array($studentId, $allowed, true)) {
                $errors[] = 'Teacher can create reports only for assigned students.';
            }
        }

        if (!empty($errors)) {
            $students = $role === 'admin' ? StudentReport::allStudents() : StudentReport::teacherStudents((int) ($user['id'] ?? 0));
            $teachers = $role === 'admin' ? StudentReport::allTeachers() : [];
            View::render('reports/create', [
                'pageTitle' => 'Create Report',
                'students' => $students,
                'teachers' => $teachers,
                'googleFormUrl' => self::GOOGLE_FORM_URL,
                'errors' => $errors,
                'old' => $_POST,
                'options' => self::fieldOptions(),
            ]);
            return;
        }

        $mailResult = null;

        $reportId = StudentReport::create([
            'student_id' => $studentId,
            'teacher_id' => $teacherId,
            'email' => (string) ($studentProfile['email'] ?? ''),
            'student_name' => (string) ($studentProfile['name'] ?? ''),
            'teacher_name' => (string) ($teacherProfile['name'] ?? ''),
            'subject' => $subject,
            'overall_performance' => $overallPerformance,
            'concept_understanding' => $conceptUnderstanding,
            'application_ability' => $applicationAbility,
            'homework_completion' => $homeworkCompletion,
            'attention_level' => $attentionLevel,
            'participation_level' => $participationLevel,
            'behaviour' => $behaviour,
            'subjects_addressed' => $subjectsAddressed,
            'future_focus' => $futureFocus,
            'recommended_focus' => $recommendedFocus,
            'study_strategies' => $studyStrategies,
            'additional_support' => $additionalSupport,
            'overall_feedback' => $overallFeedback,
            'report_date' => $reportDate,
            'pdf_path' => '',
        ]);

        $report = StudentReport::findByIdForUser($reportId, $role === 'admin' ? 'admin' : 'teacher', (int) ($user['id'] ?? 0));
        $pdfInfo = generate_student_report_pdf($report ?: ['id' => $reportId]);
        if (!empty($pdfInfo['ok'])) {
            StudentReport::updatePdfPath($reportId, (string) $pdfInfo['relative_path']);
        } else {
            ReportLog::line(
                'Report id ' . $reportId . ' saved but PDF failed: ' . (string) ($pdfInfo['error'] ?? 'unknown')
            );
        }

        $parentEmail = trim((string) ($studentProfile['parent_email'] ?? ''));
        if ($parentEmail === '') {
            ReportLog::line('Email skipped: no parent_email for student user id ' . $studentId);
        } elseif (empty($pdfInfo['ok'])) {
            ReportLog::line('Email skipped: PDF not generated for report id ' . $reportId);
        } else {
            $subjectLine = 'Student Performance Report';
            $body = '<p>Please find attached the student report card.</p>';
            $mailResult = Mailer::send($parentEmail, $subjectLine, $body, true, [[
                'path' => (string) $pdfInfo['absolute_path'],
                'name' => 'report_' . $reportId . '.pdf',
            ]]);
            if (!empty($mailResult['success'])) {
                ReportLog::line('Email sent to parent: ' . $parentEmail . ' (report id ' . $reportId . ')');
            } else {
                ReportLog::line(
                    'Email failed for report id ' . $reportId . ' to ' . $parentEmail . ': ' . (string) ($mailResult['error'] ?? '')
                );
            }
        }

        $parts = ['Report saved.'];
        if (empty($pdfInfo['ok'])) {
            $parts[] = 'PDF generation failed — see logs/report.log.';
        } else {
            $parts[] = 'PDF generated.';
        }
        if ($parentEmail === '') {
            $parts[] = 'Parent/guardian email not set for this student — email not sent.';
        } elseif (empty($pdfInfo['ok'])) {
            $parts[] = 'Email not sent (no PDF).';
        } elseif ($mailResult !== null && !empty($mailResult['success'])) {
            $parts[] = 'Email sent to parent.';
        } elseif ($mailResult !== null) {
            $parts[] = 'Email failed — see logs/mail_error.log and logs/mail.log.';
        }
        $_SESSION['flash_success'] = implode(' ', $parts);
        header('Location: ' . $base . ($role === 'admin' ? '/admin/reports' : '/teacher/reports'));
    }

    public static function adminIndex(): void
    {
        Auth::requireRole(['admin']);
        $filters = self::collectFilters();
        $rows = StudentReport::listForUser('admin', (int) (Auth::user()['id'] ?? 0), $filters);
        View::render('reports/index', [
            'pageTitle' => 'Student Reports',
            'rows' => $rows,
            'role' => 'admin',
            'filters' => $filters,
            'students' => StudentReport::allStudents(),
            'teachers' => StudentReport::allTeachers(),
        ]);
    }

    public static function teacherIndex(): void
    {
        Auth::requireRole(['teacher']);
        $uid = (int) (Auth::user()['id'] ?? 0);
        $filters = self::collectFilters();
        $rows = StudentReport::listForUser('teacher', $uid, $filters);
        View::render('reports/index', [
            'pageTitle' => 'Student Reports',
            'rows' => $rows,
            'role' => 'teacher',
            'filters' => $filters,
            'students' => StudentReport::teacherStudents($uid),
            'teachers' => [],
        ]);
    }

    public static function studentIndex(): void
    {
        Auth::requireRole(['student']);
        $uid = (int) (Auth::user()['id'] ?? 0);
        $rows = StudentReport::listForUser('student', $uid, []);
        View::render('reports/index', [
            'pageTitle' => 'My Reports',
            'rows' => $rows,
            'role' => 'student',
            'filters' => [],
            'students' => [],
            'teachers' => [],
        ]);
    }

    public static function show(): void
    {
        Auth::requireRole(['admin', 'teacher', 'student']);
        $user = Auth::user();
        $role = (string) ($user['role'] ?? 'student');
        $uid = (int) ($user['id'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo 'Invalid report id';
            return;
        }

        $report = StudentReport::findByIdForUser($id, $role, $uid);
        if (!$report) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        View::render('reports/show', [
            'pageTitle' => 'Report Details',
            'report' => $report,
        ]);
    }

    public static function downloadPdf(): void
    {
        Auth::requireRole(['admin', 'teacher', 'student']);
        $user = Auth::user();
        $role = (string) ($user['role'] ?? 'student');
        $uid = (int) ($user['id'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo 'Invalid report id';
            return;
        }
        $report = StudentReport::findByIdForUser($id, $role, $uid);
        if (!$report) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        $relative = (string) ($report['pdf_path'] ?? '');
        if ($relative === '') {
            http_response_code(404);
            echo 'PDF not found';
            return;
        }
        $abs = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relative, '/\\'));
        if (!is_file($abs)) {
            http_response_code(404);
            echo 'PDF file missing';
            return;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($abs) . '"');
        header('Content-Length: ' . (string) filesize($abs));
        readfile($abs);
    }

    public static function importForm(): void
    {
        Auth::requireRole(['admin']);
        View::render('reports/import', [
            'pageTitle' => 'Import Reports',
            'googleFormUrl' => self::GOOGLE_FORM_URL,
        ]);
    }

    public static function importStore(): void
    {
        Auth::requireRole(['admin']);
        $base = defined('BASE_PATH') ? BASE_PATH : '';

        if (!isset($_FILES['csv']) || (int) ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $_SESSION['flash_warning'] = 'Please upload a valid CSV file.';
            header('Location: ' . $base . '/admin/reports/import');
            return;
        }

        $tmp = (string) ($_FILES['csv']['tmp_name'] ?? '');
        $fp = fopen($tmp, 'rb');
        if ($fp === false) {
            $_SESSION['flash_warning'] = 'Could not read CSV file.';
            header('Location: ' . $base . '/admin/reports/import');
            return;
        }

        $header = fgetcsv($fp);
        if (!is_array($header)) {
            fclose($fp);
            $_SESSION['flash_warning'] = 'CSV header is missing.';
            header('Location: ' . $base . '/admin/reports/import');
            return;
        }

        $map = array_flip(array_map(static fn($v): string => strtolower(trim((string) $v)), $header));
        $required = [
            'student_id', 'teacher_id', 'subject', 'overall_performance', 'concept_understanding',
            'application_ability', 'homework_completion', 'attention_level', 'participation_level',
            'behaviour', 'subjects_addressed', 'future_focus', 'recommended_focus', 'study_strategies',
            'additional_support', 'overall_feedback', 'report_date'
        ];
        foreach ($required as $col) {
            if (!isset($map[$col])) {
                fclose($fp);
                $_SESSION['flash_warning'] = 'CSV missing required column: ' . $col;
                header('Location: ' . $base . '/admin/reports/import');
                return;
            }
        }

        $count = 0;
        while (($row = fgetcsv($fp)) !== false) {
            $studentId = (int) ($row[$map['student_id']] ?? 0);
            $teacherId = (int) ($row[$map['teacher_id']] ?? 0);
            $studentProfile = StudentReport::getStudentProfile($studentId);
            $teacherProfile = StudentReport::getTeacherProfile($teacherId);
            if (!$studentProfile || !$teacherProfile) {
                continue;
            }

            $reportId = StudentReport::create([
                'student_id' => $studentId,
                'teacher_id' => $teacherId,
                'email' => (string) ($studentProfile['email'] ?? ''),
                'student_name' => (string) ($studentProfile['name'] ?? ''),
                'teacher_name' => (string) ($teacherProfile['name'] ?? ''),
                'subject' => trim((string) ($row[$map['subject']] ?? '')),
                'overall_performance' => trim((string) ($row[$map['overall_performance']] ?? '')),
                'concept_understanding' => trim((string) ($row[$map['concept_understanding']] ?? '')),
                'application_ability' => trim((string) ($row[$map['application_ability']] ?? '')),
                'homework_completion' => trim((string) ($row[$map['homework_completion']] ?? '')),
                'attention_level' => trim((string) ($row[$map['attention_level']] ?? '')),
                'participation_level' => trim((string) ($row[$map['participation_level']] ?? '')),
                'behaviour' => trim((string) ($row[$map['behaviour']] ?? '')),
                'subjects_addressed' => trim((string) ($row[$map['subjects_addressed']] ?? '')),
                'future_focus' => trim((string) ($row[$map['future_focus']] ?? '')),
                'recommended_focus' => trim((string) ($row[$map['recommended_focus']] ?? '')),
                'study_strategies' => trim((string) ($row[$map['study_strategies']] ?? '')),
                'additional_support' => trim((string) ($row[$map['additional_support']] ?? '')),
                'overall_feedback' => trim((string) ($row[$map['overall_feedback']] ?? '')),
                'report_date' => trim((string) ($row[$map['report_date']] ?? date('Y-m-d'))),
                'pdf_path' => '',
            ]);
            $count += ($reportId > 0 ? 1 : 0);
        }
        fclose($fp);

        $_SESSION['flash_success'] = 'Import completed. Added ' . $count . ' report(s).';
        header('Location: ' . $base . '/admin/reports');
    }

    private static function validateChoice(string $value, array $allowed, string $label): array
    {
        if ($value === '') {
            return [$label . ' is required.'];
        }
        if (!in_array($value, $allowed, true)) {
            return ['Invalid option selected for ' . $label . '.'];
        }
        return [];
    }

    private static function fieldOptions(): array
    {
        return [
            'overall_performance' => self::PERFORMANCE_OPTIONS,
            'concept_understanding' => self::UNDERSTANDING_OPTIONS,
            'application_ability' => self::APPLICATION_OPTIONS,
            'homework_completion' => self::HOMEWORK_OPTIONS,
            'attention_level' => self::ATTENTION_OPTIONS,
            'participation_level' => self::PARTICIPATION_OPTIONS,
            'behaviour' => self::BEHAVIOUR_OPTIONS,
        ];
    }

    private static function collectFilters(): array
    {
        return [
            'student_id' => (int) ($_GET['student_id'] ?? 0),
            'teacher_id' => (int) ($_GET['teacher_id'] ?? 0),
            'subject' => trim((string) ($_GET['subject'] ?? '')),
            'from_date' => trim((string) ($_GET['from_date'] ?? '')),
            'to_date' => trim((string) ($_GET['to_date'] ?? '')),
        ];
    }
}
