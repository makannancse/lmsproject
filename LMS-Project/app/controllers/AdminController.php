<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/models/ClassSession.php';
require_once dirname(__DIR__) . '/models/TeacherPayout.php';
require_once dirname(__DIR__) . '/models/User.php';
require_once dirname(__DIR__) . '/models/ClassRecording.php';
require_once dirname(__DIR__) . '/lib/Mailer.php';
require_once dirname(__DIR__) . '/lib/EmailTemplate.php';
require_once dirname(__DIR__) . '/lib/Pagination.php';
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

        $currentLateClasses = ClassSession::findCurrentTeacherLate(8);
        $recentCompletedClasses = ClassSession::findRecentCompleted(8);

        View::render('admin/dashboard', [
            'pageTitle' => 'Admin Dashboard',
            'totalStudents' => $totalStudents,
            'totalTeachers' => $totalTeachers,
            'classStats' => $classStats,
            'totalPayoutPending' => $totalPayoutPending,
            'totalPayoutPaid' => $totalPayoutPaid,
            'teacherPayouts' => $teacherPayouts,
            'currentLateClasses' => $currentLateClasses,
            'recentCompletedClasses' => $recentCompletedClasses,
        ]);
    }
    public static function users(): void
    {
        Auth::requireRole(['admin']);

        $role = $_GET['role'] ?? 'student';
        if (!in_array($role, ['student', 'teacher', 'admin'], true)) {
            $role = 'student';
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $statusFilter = in_array($status, ['active', 'inactive'], true) ? $status : null;

        $req = Pagination::fromRequest();
        $searchQuery = $query !== '' ? $query : null;
        $total = User::countSearch($role, $searchQuery, $statusFilter);
        $users = User::searchPaged($role, $searchQuery, $statusFilter, $req['per_page'], $req['offset']);
        $pagination = Pagination::meta($total, $req['page'], $req['per_page']);

        View::render('admin/users/index', [
            'pageTitle' => 'Users',
            'role' => $role,
            'users' => $users,
            'searchQuery' => $query,
            'statusFilter' => $statusFilter ?? '',
            'pagination' => $pagination,
            'queryParams' => array_filter([
                'role' => $role,
                'q' => $query !== '' ? $query : null,
                'status' => $statusFilter,
            ], static fn($v) => $v !== null && $v !== ''),
        ]);
    }

    public static function editUserForm(): void
    {
        Auth::requireRole(['admin']);
        $base = appWebPath();

        $userId = (int) ($_GET['id'] ?? 0);
        $user = User::findById($userId);
        if ($user === null) {
            $_SESSION['flash_error'] = 'User not found.';
            redirectTo('/admin/users');
        }

        $role = (string) ($user['role'] ?? 'student');
        $profile = User::profileForUser($userId, $role) ?? $user;
        $nameParts = User::splitName((string) ($user['name'] ?? ''));

        $teachers = User::allTeachers(true);
        $timezoneOptions = supportedSchedulingTimezones();

        View::render('admin/users/edit', [
            'pageTitle' => 'Edit user',
            'user' => $profile,
            'editUserId' => $userId,
            'role' => $role,
            'firstName' => $nameParts['first_name'],
            'lastName' => $nameParts['last_name'],
            'teachers' => $teachers,
            'timezoneOptions' => $timezoneOptions,
            'errors' => [],
            'old' => [],
        ]);
    }

    public static function updateUser(): void
    {
        Auth::requireRole(['admin']);
        $base = appWebPath();
        $pdo = Database::connection();

        $userId = (int) ($_POST['user_id'] ?? 0);
        if (function_exists('logUserEdit')) {
            logUserEdit([
                'event' => 'update_request',
                'user_id' => $userId,
                'post_keys' => array_keys($_POST),
                'admin_id' => Auth::userId(),
            ]);
        }
        if ($userId <= 0) {
            $_SESSION['flash_error'] = 'Invalid user id.';
            redirectTo('/admin/users');
        }

        $user = User::findById($userId);
        if ($user === null) {
            if (function_exists('logUserEdit')) {
                logUserEdit([
                    'event' => 'user_not_found',
                    'user_id' => $userId,
                    'admin_id' => Auth::userId(),
                ]);
            }
            $_SESSION['flash_error'] = 'User not found.';
            redirectTo('/admin/users');
        }

        $role = (string) ($user['role'] ?? '');
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $name = User::combineName($firstName, $lastName);
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $timezone = normalizeTimezone((string) ($_POST['timezone'] ?? APP_TIMEZONE), APP_TIMEZONE);
        $status = strtolower(trim((string) ($_POST['status'] ?? 'active')));
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $errors = [];
        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }

        if (User::emailInUseByOtherUser($email, $userId)) {
            $errors[] = 'Email is already in use.';
            if (function_exists('logUserEdit')) {
                logUserEdit([
                    'event' => 'email_duplicate_rejected',
                    'user_id' => $userId,
                    'email' => $email,
                ]);
            }
        }

        $old = $_POST;
        $old['first_name'] = $firstName;
        $old['last_name'] = $lastName;
        if (!empty($errors)) {
            $profile = User::profileForUser($userId, $role) ?? $user;
            View::render('admin/users/edit', [
                'pageTitle' => 'Edit user',
                'user' => $profile,
                'editUserId' => $userId,
                'role' => $role,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'teachers' => User::allTeachers(true),
                'timezoneOptions' => supportedSchedulingTimezones(),
                'errors' => $errors,
                'old' => $old,
            ]);
            return;
        }

        $pdo->beginTransaction();
        try {
            User::updateCore($userId, [
                'name' => $name,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'timezone' => $timezone,
                'status' => $status,
            ]);

            if ($role === 'student') {
                $parentEmail = trim((string) ($_POST['parent_email'] ?? ''));
                $subject = trim((string) ($_POST['subject'] ?? ''));
                $paymentAmount = parseInrAmount($_POST['default_payment_amount'] ?? 0);
                $assignedTeacherId = (int) ($_POST['assigned_teacher_id'] ?? 0);

                $exists = $pdo->prepare('SELECT id FROM students WHERE user_id = :uid LIMIT 1');
                $exists->execute(['uid' => $userId]);
                if ($exists->fetch()) {
                    $pdo->prepare(
                        'UPDATE students
                         SET parent_email = :parent_email,
                             subject = :subject,
                             default_payment_amount = :amount,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE user_id = :uid'
                    )->execute([
                        'uid' => $userId,
                        'parent_email' => $parentEmail !== '' ? $parentEmail : null,
                        'subject' => $subject !== '' ? $subject : null,
                        'amount' => $paymentAmount,
                    ]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO students (user_id, parent_email, subject, default_payment_amount)
                         VALUES (:uid, :parent_email, :subject, :amount)'
                    )->execute([
                        'uid' => $userId,
                        'parent_email' => $parentEmail !== '' ? $parentEmail : null,
                        'subject' => $subject !== '' ? $subject : null,
                        'amount' => $paymentAmount,
                    ]);
                }

                $pdo->prepare('DELETE FROM teacher_students WHERE student_id = :sid')->execute(['sid' => $userId]);
                if ($assignedTeacherId > 0 && User::isActive(User::findById($assignedTeacherId))) {
                    $pdo->prepare(
                        'INSERT INTO teacher_students (teacher_id, student_id) VALUES (:tid, :sid)'
                    )->execute(['tid' => $assignedTeacherId, 'sid' => $userId]);

                    require_once dirname(__DIR__) . '/lib/NotificationMailer.php';
                    $teacher = User::findById($assignedTeacherId);
                    if ($teacher !== null) {
                        NotificationMailer::notifyTeacherStudentAssigned(
                            (string) ($teacher['email'] ?? ''),
                            (string) ($teacher['name'] ?? 'Teacher'),
                            $name,
                            $subject,
                            date('Y-m-d')
                        );
                    }
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            if (function_exists('logUserEdit')) {
                logUserEdit([
                    'event' => 'update_failed',
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
            $_SESSION['flash_error'] = 'Could not update user: ' . $e->getMessage();
            redirectTo('/admin/users/edit?id=' . $userId);
        }

        if (function_exists('logUserEdit')) {
            logUserEdit([
                'event' => 'update_success',
                'user_id' => $userId,
                'email' => $email,
                'role' => $role,
                'admin_id' => Auth::userId(),
            ]);
        }
        if (function_exists('writeStructuredLog')) {
            writeStructuredLog('user_management.log', [
                'event' => 'user_updated',
                'user_id' => $userId,
                'role' => $role,
                'admin_id' => Auth::userId(),
            ]);
        }

        $_SESSION['flash_success'] = 'User updated successfully.';
        redirectTo('/admin/users?role=' . urlencode($role));
    }

    public static function toggleUserStatus(): void
    {
        Auth::requireRole(['admin']);
        $base = appWebPath();

        $userId = (int) ($_POST['user_id'] ?? 0);
        $action = strtolower(trim((string) ($_POST['action'] ?? '')));
        $role = trim((string) ($_POST['role'] ?? 'student'));

        $user = User::findById($userId);
        if ($user === null) {
            $_SESSION['flash_error'] = 'User not found.';
            redirectTo('/admin/users?role=' . urlencode($role));
        }

        $currentUserId = (int) (Auth::user()['id'] ?? 0);
        if ($userId === $currentUserId && $action === 'deactivate') {
            $_SESSION['flash_error'] = 'You cannot deactivate your own account.';
            redirectTo('/admin/users?role=' . urlencode((string) ($user['role'] ?? $role)));
        }

        $newStatus = $action === 'activate' ? 'active' : 'inactive';
        try {
            User::setStatus($userId, $newStatus);
        } catch (\Throwable $e) {
            if (function_exists('writeStructuredLog')) {
                writeStructuredLog('user_management.log', [
                    'event' => 'toggle_status_failed',
                    'user_id' => $userId,
                    'action' => $action,
                    'error' => $e->getMessage(),
                    'admin_id' => Auth::userId(),
                ]);
            }
            $_SESSION['flash_error'] = 'Could not update user status: ' . $e->getMessage();
            redirectTo('/admin/users?role=' . urlencode((string) ($user['role'] ?? $role)));
        }

        if (function_exists('writeStructuredLog')) {
            writeStructuredLog('user_management.log', [
                'event' => 'toggle_status',
                'user_id' => $userId,
                'new_status' => $newStatus,
                'admin_id' => Auth::userId(),
            ]);
        }

        $_SESSION['flash_success'] = $newStatus === 'active'
            ? 'User activated successfully.'
            : 'User deactivated successfully.';
        redirectTo('/admin/users?role=' . urlencode((string) ($user['role'] ?? $role)));
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

        $base = appWebPath();
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
            $_SESSION['flash_warning'] = ucfirst($role) . ' account created, but the credential email could not be sent. Share the temporary password manually.';
        }

        redirectTo('/admin/users?role=' . urlencode($role));
    }

    public static function teacherPayments(): void
    {
        Auth::requireRole(['admin']);

        $statusFilter = (string) ($_GET['status'] ?? '');
        $teacherRows = Database::connection()->query('SELECT id FROM users WHERE role = "teacher"')->fetchAll() ?: [];
        foreach ($teacherRows as $tr) {
            refreshTeacherPaymentLogs((int) ($tr['id'] ?? 0));
        }

        $rows = getAllTeacherPayoutSummaries($statusFilter);
        $req = Pagination::fromRequest();
        $total = count($rows);
        $pagedRows = array_slice($rows, $req['offset'], $req['per_page']);
        $pagination = Pagination::meta($total, $req['page'], $req['per_page']);
        $totalPayout = 0.0;
        $totalPaid = 0.0;
        $totalPending = 0.0;
        foreach ($rows as $row) {
            $totalPayout += (float) ($row['total_earnings'] ?? 0);
            $totalPaid += (float) ($row['paid_amount'] ?? 0);
            $totalPending += (float) ($row['pending_amount'] ?? 0);
        }

        View::render('admin/payments/index', [
            'pageTitle' => 'Teacher Payments',
            'rows' => $pagedRows,
            'statusFilter' => $statusFilter,
            'totalPayout' => $totalPayout,
            'totalPaid' => $totalPaid,
            'totalPending' => $totalPending,
            'pagination' => $pagination,
            'queryParams' => array_filter(['status' => $statusFilter !== '' ? $statusFilter : null]),
        ]);
    }

    public static function processTeacherPayment(): void
    {
        Auth::requireRole(['admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $statusFilter = trim((string) ($_POST['status'] ?? ''));

        if ($teacherId <= 0) {
            $_SESSION['flash_error'] = 'Invalid teacher selected.';
            redirect('admin/payments?success=invalid_teacher');
        }

        try {
            if (isset($_POST['mark_paid'])) {
                $snapshot = getTeacherPayoutSummary($teacherId);
                if ((float) $snapshot['pending_amount'] > 0) {
                    createTeacherPaymentEntry($teacherId, (float) $snapshot['pending_amount'], 'Marked paid from dashboard');
                    $_SESSION['flash_success'] = 'Payment marked as paid successfully.';
                } else {
                    $_SESSION['flash_warning'] = 'No pending balance to mark as paid for this teacher.';
                }
                $suffix = $statusFilter !== '' ? ('&status=' . urlencode($statusFilter)) : '';
                redirect('admin/payments?success=paid' . $suffix);
            }

            if (isset($_POST['add_payment'])) {
                $amount = parseInrAmount($_POST['advance_amount'] ?? 0);
                $remarks = trim((string) ($_POST['remarks'] ?? 'Manual payment from dashboard'));
                if ($amount > 0) {
                    createTeacherPaymentEntry($teacherId, $amount, $remarks);
                    $_SESSION['flash_success'] = 'Payment recorded successfully.';
                } else {
                    $_SESSION['flash_warning'] = 'Enter a valid payment amount.';
                }
                $suffix = $statusFilter !== '' ? ('&status=' . urlencode($statusFilter)) : '';
                redirect('admin/payments?success=payment_added' . $suffix);
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Payment update failed: ' . $e->getMessage();
            redirect('admin/payments');
        }

        $_SESSION['flash_warning'] = 'No payment action was performed.';
        redirect('admin/payments?success=no_action');
    }

    public static function deleteUser(): void
    {
        Auth::requireRole(['admin']);

        $userId = (int) ($_POST['user_id'] ?? 0);
        $role = trim((string) ($_POST['role'] ?? 'student'));
        $currentUserId = (int) (Auth::user()['id'] ?? 0);

        if ($userId <= 0 || !in_array($role, ['student', 'teacher'], true)) {
            $_SESSION['flash_error'] = 'Invalid delete request.';
            redirectTo('/admin/users?role=' . urlencode($role));
        }
        if ($userId === $currentUserId) {
            $_SESSION['flash_error'] = 'You cannot delete your own account.';
            redirectTo('/admin/users?role=' . urlencode($role));
        }

        try {
            User::permanentlyDelete($userId, $role);
            $_SESSION['flash_success'] = ucfirst($role) . ' deleted permanently.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Delete failed: ' . $e->getMessage();
        }

        redirectTo('/admin/users?role=' . urlencode($role));
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
        $loginUrl = url('login');
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars($plainPassword, ENT_QUOTES, 'UTF-8');
        $safeRole = htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8');

        $firstName = explode(' ', trim($name))[0];
        $subject = 'Welcome to ' . EmailTemplate::brandName() . ', ' . $firstName . '!';
        $intro = '<p>Hi ' . $safeName . ',</p>'
            . '<p>Welcome to ' . htmlspecialchars(EmailTemplate::brandName(), ENT_QUOTES, 'UTF-8') . '! '
            . 'Your ' . $safeRole . ' account has been created.</p>'
            . '<p>Please sign in and change your password after your first login.</p>';
        $rows = [
            'Student Name' => $role === 'student' ? $safeName : '',
            'Teacher Name' => $role === 'teacher' ? $safeName : '',
            'Username' => $safeEmail,
            'Temporary Password' => $safePassword,
            'Login URL' => '<a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '</a>',
        ];
        if ($role === 'admin') {
            $rows = [
                'Name' => $safeName,
                'Username' => $safeEmail,
                'Temporary Password' => $safePassword,
                'Login URL' => '<a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '</a>',
            ];
        }
        $rows = array_filter($rows, static fn(string $v): bool => $v !== '');

        $body = EmailTemplate::wrap(
            'Welcome to ' . EmailTemplate::brandName(),
            $intro,
            $rows,
            'Sign In',
            $loginUrl
        );

        $result = Mailer::send($email, $subject, $body, true);
        EmailTemplate::logCredential(
            $email,
            !empty($result['success']),
            !empty($result['success']) ? 'sent' : null,
            $result['error'] ?? null
        );

        return $result;
    }
}
