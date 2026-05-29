<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/models/ClassSession.php';
require_once dirname(__DIR__) . '/models/TeacherGoogleAccount.php';
require_once dirname(__DIR__) . '/models/TeacherPayout.php';
require_once dirname(__DIR__) . '/models/User.php';
require_once dirname(__DIR__) . '/models/ClassRecording.php';
require_once dirname(__DIR__) . '/lib/Mailer.php';
require_once dirname(__DIR__, 2) . '/payments/payment_helper.php';

class AdminController
{
    public static function dashboard(): void
    {
        Auth::requireRole(['admin']);

        $pdo = Database::connection();

        // Totals
        $totalStudents = (int)($pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn() ?: 0);
        $totalTeachers = (int)($pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn() ?: 0);

        $classStats = ClassSession::countByStatus();

        $teacherIds = $pdo->query("SELECT id FROM users WHERE role = 'teacher'")->fetchAll() ?: [];
        foreach ($teacherIds as $teacherRow) {
            refreshTeacherPaymentLogs((int) ($teacherRow['id'] ?? 0));
        }
        $teacherPayouts = getAllTeacherPayoutSummaries();
        $totalPayoutPending = 0.0;
        $totalPayoutPaid = 0.0;
        foreach ($teacherPayouts as $tp) {
            $totalPayoutPending += (float) ($tp['pending_amount'] ?? 0);
            $totalPayoutPaid += (float) ($tp['paid_amount'] ?? 0);
        }

        // Recent classes
        $recentClassesStmt = $pdo->query(
            'SELECT cs.*, u.name AS teacher_name, cr.recording_title, cr.recording_duration, cr.visible_to_student,
                    tga.recording_supported AS teacher_recording_supported
             FROM class_sessions cs
             INNER JOIN users u ON u.id = cs.teacher_id
             LEFT JOIN teacher_google_accounts tga ON tga.teacher_id = cs.teacher_id
             LEFT JOIN class_recordings cr ON cr.class_id = cs.id
             ORDER BY cs.start_datetime DESC
             LIMIT 10'
        );
        $recentClasses = $recentClassesStmt->fetchAll() ?: [];
        $teacherGoogleAccounts = TeacherGoogleAccount::allWithTeacherNames();
        $recentRecordings = ClassRecording::listForAdmin([]);
        $recentRecordings = array_slice($recentRecordings, 0, 6);

        View::render('admin/dashboard', [
            'pageTitle' => 'Admin Dashboard',
            'totalStudents' => $totalStudents,
            'totalTeachers' => $totalTeachers,
            'classStats' => $classStats,
            'totalPayoutPending' => $totalPayoutPending,
            'totalPayoutPaid' => $totalPayoutPaid,
            'recentClasses' => $recentClasses,
            'recentRecordings' => $recentRecordings,
            'teacherPayouts' => $teacherPayouts,
            'teacherGoogleAccounts' => $teacherGoogleAccounts,
        ]);
    }

    public static function users(): void
    {
        Auth::requireRole(['admin']);

        $role = $_GET['role'] ?? 'student';
        if (!in_array($role, ['student', 'teacher', 'admin'], true)) {
            $role = 'student';
        }

        $users = User::allByRole($role);

        View::render('admin/users/index', [
            'pageTitle' => 'Users',
            'role' => $role,
            'users' => $users,
        ]);
    }

    public static function createStudentForm(): void
    {
        Auth::requireRole(['admin']);
        View::render('admin/users/create-student', [
            'pageTitle' => 'Create Student',
        ]);
    }

    public static function createTeacherForm(): void
    {
        Auth::requireRole(['admin']);
        View::render('admin/users/create-teacher', [
            'pageTitle' => 'Create Teacher',
        ]);
    }

    public static function storeUser(): void
    {
        Auth::requireRole(['admin']);
        $pdo = Database::connection();

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $autoGeneratePassword = (int) ($_POST['auto_generate_password'] ?? 0) === 1;
        $role = $_POST['role'] ?? 'student';
        $timezone = normalizeTimezone((string) ($_POST['timezone'] ?? APP_TIMEZONE), APP_TIMEZONE);
        $country = trim($_POST['country'] ?? '');
        $parentEmail = trim($_POST['parent_email'] ?? '');
        $employmentType = $_POST['employment_type'] ?? 'part_time';

        $errors = [];
        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if ($email === '') {
            $errors[] = 'Email is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is invalid.';
        }
        if ($role === 'student') {
            if ($parentEmail === '') {
                $errors[] = 'Parent/Guardian Email is required.';
            } elseif (!filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Parent/Guardian Email is invalid.';
            }
        }
        if (!in_array($role, ['student', 'teacher', 'admin'], true)) {
            $errors[] = 'Role is invalid.';
        }
        if ($autoGeneratePassword) {
            $password = self::generateSecurePassword();
            $confirmPassword = $password;
        }
        if ($password === '') {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($confirmPassword === '') {
            $errors[] = 'Confirm Password is required.';
        } elseif (!hash_equals($password, $confirmPassword)) {
            $errors[] = 'Password and Confirm Password must match.';
        }

        // Check unique email
        if (empty($errors) && User::findByEmail($email)) {
            $errors[] = 'A user with this email already exists.';
        }

        $base = defined('BASE_PATH') ? BASE_PATH : '';
        $isStudent = $role === 'student';
        $isTeacher = $role === 'teacher';

        if (!empty($errors)) {
            self::renderUserCreateForm($role, $errors, $_POST);
            return;
        }

        $plainPassword = $password;
        $passwordHash = password_hash($plainPassword, PASSWORD_BCRYPT);

        $pdo->beginTransaction();
        try {
            $userId = User::create([
                'name' => $name,
                'email' => $email,
                'password_hash' => $passwordHash,
                'role' => $role,
                'timezone' => $timezone,
            ]);

            if ($isStudent) {
                $stmt = $pdo->prepare(
                    'INSERT INTO students (user_id, country, parent_email, notes) VALUES (:user_id, :country, :parent_email, :notes)'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'country' => $country,
                    'parent_email' => $parentEmail,
                    'notes' => null,
                ]);
            } elseif ($isTeacher) {
                $stmt = $pdo->prepare(
                    'INSERT INTO teachers (user_id, employment_type, hourly_rate, notes)
                     VALUES (:user_id, :employment_type, :hourly_rate, :notes)'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'employment_type' => $employmentType,
                    'hourly_rate' => null,
                    'notes' => null,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $mailResult = self::sendAccountCreatedEmail($email, $name, $plainPassword, $role);
        if (!empty($mailResult['success'])) {
            $_SESSION['flash_success'] = ucfirst($role) . ' account created and credentials emailed successfully.';
        } else {
            $warning = ucfirst($role) . ' account created, but the credential email could not be sent.';
            if ($autoGeneratePassword) {
                $warning .= ' Generated password: ' . $plainPassword;
            }
            $_SESSION['flash_warning'] = $warning;
        }

        header('Location: ' . $base . '/admin/users?role=' . urlencode($role));
    }

    private static function renderUserCreateForm(string $role, array $errors, array $old): void
    {
        $isStudent = $role === 'student';
        $view = $isStudent ? 'admin/users/create-student' : 'admin/users/create-teacher';
        View::render($view, [
            'pageTitle' => $isStudent ? 'Create Student' : 'Create Teacher',
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    private static function generateSecurePassword(int $length = 12): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $max = strlen($alphabet) - 1;
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }

    private static function sendAccountCreatedEmail(string $email, string $name, string $plainPassword, string $role): array
    {
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        $appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
        $loginUrl = ($base !== '' && str_ends_with($appUrl, $base))
            ? ($appUrl . '/login')
            : ($appUrl . $base . '/login');
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars($plainPassword, ENT_QUOTES, 'UTF-8');
        $safeRole = htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8');
        $safeLoginUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

        $subject = 'Your LearnWise Account Created';
        $body = '
            <div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
                <h2 style="margin-bottom: 8px;">Welcome to LearnWise</h2>
                <p>Hi ' . $safeName . ',</p>
                <p>Your ' . $safeRole . ' account has been created.</p>
                <table cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse;">
                    <tr><td><strong>Login URL</strong></td><td><a href="' . $safeLoginUrl . '">' . $safeLoginUrl . '</a></td></tr>
                    <tr><td><strong>Email</strong></td><td>' . $safeEmail . '</td></tr>
                    <tr><td><strong>Password</strong></td><td>' . $safePassword . '</td></tr>
                </table>
                <p style="margin-top: 16px;">Please sign in and change your password after your first login.</p>
                <p>Regards,<br>' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . ' Team</p>
            </div>
        ';

        return Mailer::send($email, $subject, $body, true);
    }
}
