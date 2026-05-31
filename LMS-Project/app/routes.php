<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/Router.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/View.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/TeacherController.php';
require_once __DIR__ . '/controllers/StudentController.php';
require_once __DIR__ . '/controllers/ClassController.php';
require_once __DIR__ . '/controllers/SettingsController.php';
require_once __DIR__ . '/controllers/ReminderController.php';
require_once __DIR__ . '/controllers/ClassMasterController.php';
require_once __DIR__ . '/controllers/RescheduleController.php';
require_once __DIR__ . '/controllers/HomeworkController.php';
require_once __DIR__ . '/controllers/FeedbackController.php';
require_once __DIR__ . '/controllers/CalendarController.php';
require_once __DIR__ . '/controllers/ReportController.php';
require_once __DIR__ . '/controllers/TeacherStudentMapController.php';
require_once __DIR__ . '/controllers/StudentPaymentController.php';
require_once __DIR__ . '/controllers/GoogleIntegrationController.php';
require_once __DIR__ . '/controllers/MeetingTrackingController.php';
require_once __DIR__ . '/controllers/MeetingSyncDebugController.php';
require_once __DIR__ . '/controllers/RecordingController.php';
require_once __DIR__ . '/controllers/TeacherStudentApiController.php';
require_once __DIR__ . '/controllers/KeepAliveController.php';

$router = new Router();

Auth::startSession();

// Normalize base path: all redirects and form actions should respect BASE_PATH
$base = defined('BASE_PATH') ? BASE_PATH : '';

$router->get('/', function () use ($base) {
    header('Location: ' . $base . '/login');
});
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/ajax/keepalive', [KeepAliveController::class, 'ping']);

$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/meeting-sync-debug', [MeetingSyncDebugController::class, 'index']);
$router->get('/teacher', [TeacherController::class, 'dashboard']);
$router->get('/teacher/calendar', [CalendarController::class, 'teacherPage']);
$router->get('/admin/calendar', [CalendarController::class, 'adminPage']);
$router->get('/student/calendar', [CalendarController::class, 'studentPage']);
$router->get('/calendar/events', [CalendarController::class, 'serveEventsJson']);
$router->get('/api/teacher-students', [TeacherStudentApiController::class, 'listForTeacher']);
$router->get('/student', [StudentController::class, 'dashboard']);
$router->get('/classes', [ClassController::class, 'index']);
$router->get('/classes/create', [ClassController::class, 'createForm']);
$router->post('/classes', [ClassController::class, 'store']);
$router->get('/classes/edit', [ClassController::class, 'editForm']);
$router->post('/classes/update', [ClassController::class, 'update']);
$router->post('/classes/status', [ClassController::class, 'updateStatus']);
$router->post('/classes/recording-toggle', [ClassController::class, 'toggleRecording']);
$router->get('/classes/completed', [ClassController::class, 'completed']);
$router->get('/settings', [SettingsController::class, 'edit']);
$router->post('/settings', [SettingsController::class, 'update']);
$router->get('/admin/users', [AdminController::class, 'users']);
$router->get('/admin/teacher-students', [TeacherStudentMapController::class, 'form']);
$router->post('/admin/teacher-students', [TeacherStudentMapController::class, 'store']);
$router->get('/admin/users/create-student', [AdminController::class, 'createStudentForm']);
$router->get('/admin/users/create-teacher', [AdminController::class, 'createTeacherForm']);
$router->post('/admin/users', [AdminController::class, 'storeUser']);
$router->get('/reminders/send', [ReminderController::class, 'sendUpcoming']);

// Join tracking redirects to meeting link
$router->get('/join-class', [ClassController::class, 'joinTrack']);
$router->post('/meeting/track', [MeetingTrackingController::class, 'track']);
$router->get('/meeting/sync-ongoing', [MeetingTrackingController::class, 'syncOngoing']);
$router->post('/webhooks/google-meet', [MeetingTrackingController::class, 'webhook']);

// Admin: class types (class_master CRUD)
$router->get('/admin/class-types', [ClassMasterController::class, 'index']);
$router->get('/admin/class-types/create', [ClassMasterController::class, 'createForm']);
$router->post('/admin/class-types', [ClassMasterController::class, 'store']);
$router->get('/admin/class-types/edit', [ClassMasterController::class, 'editForm']);
$router->post('/admin/class-types/update', [ClassMasterController::class, 'update']);
$router->get('/admin/payments', function (): void {
    require_once dirname(__DIR__) . '/payments/teacher_payments.php';
});
$router->post('/admin/payments/process', function (): void {
    require_once dirname(__DIR__) . '/payments/process_payment.php';
});
$router->get('/admin/payments/details', function (): void {
    require_once dirname(__DIR__) . '/payments/payment_details.php';
});
$router->get('/admin/student-payments', [StudentPaymentController::class, 'adminIndex']);
$router->post('/admin/student-payments/mark-paid', [StudentPaymentController::class, 'markPaid']);
$router->get('/student/payments', [StudentPaymentController::class, 'studentIndex']);
$router->get('/admin/recordings', [RecordingController::class, 'adminIndex']);
$router->post('/admin/recordings/visibility', [RecordingController::class, 'toggleVisibility']);
$router->post('/recordings/manual-save', [RecordingController::class, 'manualSave']);

// Google OAuth2 + Google Meet integration
$router->get('/connect-google', [GoogleIntegrationController::class, 'connectGoogle']);
$router->post('/connect-google', [GoogleIntegrationController::class, 'connectGoogle']);
$router->get('/disconnect-google', [GoogleIntegrationController::class, 'disconnectGoogle']);
$router->post('/disconnect-google', [GoogleIntegrationController::class, 'disconnectGoogle']);
$router->get('/auth/google', [GoogleIntegrationController::class, 'authGoogle']);
$router->get('/auth/google/callback', [GoogleIntegrationController::class, 'authGoogleCallback']);
$router->get('/callback', [GoogleIntegrationController::class, 'callback']);
$router->post('/create-class', [GoogleIntegrationController::class, 'createClass']);

// Reports
$router->get('/teacher/reports', [ReportController::class, 'teacherIndex']);
$router->get('/teacher/reports/create', [ReportController::class, 'createForm']);
$router->post('/teacher/reports', [ReportController::class, 'store']);
$router->get('/admin/reports', [ReportController::class, 'adminIndex']);
$router->get('/admin/reports/create', [ReportController::class, 'createForm']);
$router->post('/admin/reports', [ReportController::class, 'store']);
$router->get('/student/reports', [ReportController::class, 'studentIndex']);
$router->get('/reports/view', [ReportController::class, 'show']);
$router->get('/reports/download', [ReportController::class, 'downloadPdf']);
$router->get('/admin/reports/import', [ReportController::class, 'importForm']);
$router->post('/admin/reports/import', [ReportController::class, 'importStore']);

// Reschedule
$router->get('/student/reschedule', [RescheduleController::class, 'studentIndex']);
$router->post('/student/reschedule', [RescheduleController::class, 'studentStore']);
$router->get('/teacher/reschedule', [RescheduleController::class, 'teacherIndex']);
$router->post('/teacher/reschedule/decide', [RescheduleController::class, 'teacherDecide']);
$router->get('/teacher/reschedule/new', [RescheduleController::class, 'teacherInitiateForm']);
$router->post('/teacher/reschedule/new', [RescheduleController::class, 'teacherInitiateStore']);
$router->get('/admin/reschedule', [RescheduleController::class, 'adminIndex']);
$router->post('/admin/reschedule/decide', [RescheduleController::class, 'adminDecide']);
$router->get('/admin/reschedule/new', [RescheduleController::class, 'teacherInitiateForm']);
$router->post('/admin/reschedule/new', [RescheduleController::class, 'teacherInitiateStore']);

// Homework
$router->get('/teacher/homework', [HomeworkController::class, 'teacherIndex']);
$router->get('/teacher/homework/create', [HomeworkController::class, 'teacherCreateForm']);
$router->post('/teacher/homework', [HomeworkController::class, 'teacherStore']);
$router->get('/teacher/homework/view', [HomeworkController::class, 'teacherViewClass']);
$router->get('/teacher/homework/edit', [HomeworkController::class, 'teacherEditForm']);
$router->post('/teacher/homework/update', [HomeworkController::class, 'teacherUpdate']);
$router->post('/teacher/homework/delete', [HomeworkController::class, 'teacherDelete']);
$router->post('/teacher/homework/complete', [HomeworkController::class, 'markCompleted']);
$router->get('/teacher/homework/submissions', [HomeworkController::class, 'teacherSubmissions']);
$router->get('/student/homework', [HomeworkController::class, 'studentIndex']);
$router->post('/student/homework/upload', [HomeworkController::class, 'studentUpload']);
$router->get('/homework/download', [HomeworkController::class, 'download']);

// Feedback (teacher, after N completed classes)
$router->get('/teacher/feedback', [FeedbackController::class, 'teacherIndex']);
$router->get('/teacher/feedback/create', [FeedbackController::class, 'teacherCreateForm']);
$router->post('/teacher/feedback', [FeedbackController::class, 'teacherStore']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
