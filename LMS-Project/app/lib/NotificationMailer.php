<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Mailer.php';
require_once dirname(__DIR__) . '/lib/EmailTemplate.php';
require_once dirname(__DIR__) . '/models/SystemConfig.php';

/**
 * Centralized notification emails for UAT features (CMS-configurable).
 */
class NotificationMailer
{
    public static function isEnabled(string $configKey): bool
    {
        $value = SystemConfig::get($configKey, '1');

        return $value === null || $value === '' || $value === '1' || strtolower((string) $value) === 'true';
    }

    public static function adminEmail(): ?string
    {
        $configured = trim((string) (SystemConfig::get('admin_notification_email', '') ?? ''));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        $pdo = null;
        try {
            require_once dirname(__DIR__) . '/lib/Database.php';
            $pdo = Database::connection();
            $stmt = $pdo->query(
                "SELECT email FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1"
            );
            $row = $stmt ? $stmt->fetch() : false;
            $email = is_array($row) ? trim((string) ($row['email'] ?? '')) : '';
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        } catch (\Throwable) {
            // fall through
        }

        return null;
    }

    /**
     * @param array<string, mixed> $class
     * @param list<array<string, mixed>> $students
     */
    public static function notifyAdminClassScheduled(array $class, array $students): void
    {
        if (!self::isEnabled('notify_admin_class_scheduled')) {
            return;
        }

        $adminEmail = self::adminEmail();
        if ($adminEmail === null) {
            return;
        }

        $meetingLink = (string) ($class['meeting_link'] ?? '');
        $studentLines = [];
        foreach ($students as $student) {
            $studentLines[] = htmlspecialchars((string) ($student['name'] ?? 'Student'), ENT_QUOTES, 'UTF-8')
                . ' &lt;' . htmlspecialchars((string) ($student['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '&gt;';
        }
        $studentList = $studentLines !== [] ? implode('<br>', $studentLines) : '—';
        $scheduledStart = function_exists('formatClassScheduledAt')
            ? formatClassScheduledAt($class, 'l M j, Y g:i A')
            : (string) ($class['start_datetime'] ?? '');
        $scheduledEnd = function_exists('formatClassScheduledEndAt')
            ? formatClassScheduledEndAt($class, 'g:i A')
            : '';
        $timezoneLabel = function_exists('formatClassScheduledTimezoneLabel')
            ? formatClassScheduledTimezoneLabel($class)
            : (string) ($class['scheduled_timezone'] ?? APP_TIMEZONE);
        $whenLine = trim($scheduledStart . ($scheduledEnd !== '' ? ' – ' . $scheduledEnd : '') . ' ' . $timezoneLabel);

        $subject = EmailTemplate::subject('default', 'Admin Alert: Class Scheduled — ' . (string) ($class['title'] ?? 'Class'));
        $intro = '<p>A new class has been scheduled in ' . htmlspecialchars(EmailTemplate::brandName(), ENT_QUOTES, 'UTF-8') . '.</p>';
        $rows = [
            'Class' => htmlspecialchars((string) ($class['title'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'Teacher' => htmlspecialchars((string) ($class['teacher_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'Students' => $studentList,
            'Scheduled Time' => htmlspecialchars($whenLine, ENT_QUOTES, 'UTF-8'),
            'Google Meet' => $meetingLink !== ''
                ? '<a href="' . htmlspecialchars($meetingLink, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($meetingLink, ENT_QUOTES, 'UTF-8') . '</a>'
                : '—',
        ];
        $body = EmailTemplate::wrap('Admin Alert: Class Scheduled', $intro, $rows, null, null, false);

        Mailer::send($adminEmail, $subject, $body, true);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function notifyAdminReschedule(array $context): void
    {
        if (!self::isEnabled('notify_admin_reschedule')) {
            return;
        }

        $adminEmail = self::adminEmail();
        if ($adminEmail === null) {
            return;
        }

        $subject = EmailTemplate::subject('class_rescheduled');
        $intro = '<p>A class reschedule request requires your attention.</p>';
        $rows = [
            'Class' => htmlspecialchars((string) ($context['class_title'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'Teacher' => htmlspecialchars((string) ($context['teacher_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'Student' => htmlspecialchars((string) ($context['student_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'Original Date' => htmlspecialchars((string) ($context['original_date'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'Requested Date' => htmlspecialchars((string) ($context['requested_date'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'Requested By' => htmlspecialchars((string) ($context['requested_by'] ?? ''), ENT_QUOTES, 'UTF-8'),
        ];
        $body = EmailTemplate::wrap('Reschedule Request', $intro, $rows, null, null, false);

        Mailer::send($adminEmail, $subject, $body, true);
    }

    public static function notifyTeacherStudentAssigned(
        string $teacherEmail,
        string $teacherName,
        string $studentName,
        string $subject,
        string $assignedDate
    ): void {
        if (!self::isEnabled('notify_teacher_student_assigned')) {
            return;
        }
        if ($teacherEmail === '' || !filter_var($teacherEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $mailSubject = EmailTemplate::subject('default', 'New Student Assigned: ' . $studentName);
        $intro = '<p>Hi ' . htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>A student has been assigned to you.</p>';
        $rows = [
            'Student' => htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'),
            'Class Name' => htmlspecialchars($subject !== '' ? $subject : '—', ENT_QUOTES, 'UTF-8'),
            'Assigned Date' => htmlspecialchars($assignedDate, ENT_QUOTES, 'UTF-8'),
        ];
        $body = EmailTemplate::wrap('New Student Assignment', $intro, $rows);

        Mailer::send($teacherEmail, $mailSubject, $body, true);
    }
    public static function notifyClassAction(string $action, array $class, array $students): void
    {
        if (!self::isEnabled('notify_class_action')) {
            return;
        }

        $teacherEmail = trim((string) ($class['teacher_email'] ?? ''));
        $teacherName = trim((string) ($class['teacher_name'] ?? ''));

        $meetingLink = (string) ($class['meeting_link'] ?? '');
        $scheduledStart = function_exists('formatClassScheduledAt')
            ? formatClassScheduledAt($class, 'l M j, Y g:i A')
            : (string) ($class['start_datetime'] ?? '');
        $scheduledEnd = function_exists('formatClassScheduledEndAt')
            ? formatClassScheduledEndAt($class, 'g:i A')
            : '';
        $timezoneLabel = function_exists('formatClassScheduledTimezoneLabel')
            ? formatClassScheduledTimezoneLabel($class)
            : (string) ($class['scheduled_timezone'] ?? APP_TIMEZONE);
        $whenLine = trim($scheduledStart . ($scheduledEnd !== '' ? ' – ' . $scheduledEnd : '') . ' ' . $timezoneLabel);
        
        $title = (string) ($class['title'] ?? 'Class');
        
        $subject = EmailTemplate::subject('default', 'Class ' . $action . ': ' . $title);
        
        $intro = '<p>A class has been <strong>' . strtolower($action) . '</strong> in ' . htmlspecialchars(EmailTemplate::brandName(), ENT_QUOTES, 'UTF-8') . '.</p>';
        
        $rows = [
            'Action' => htmlspecialchars($action, ENT_QUOTES, 'UTF-8'),
            'Class' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            'Teacher' => htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8'),
            'Scheduled Time' => htmlspecialchars($whenLine, ENT_QUOTES, 'UTF-8'),
        ];
        if ($meetingLink !== '' && strtolower($action) !== 'cancelled') {
            $rows['Google Meet'] = '<a href="' . htmlspecialchars($meetingLink, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($meetingLink, ENT_QUOTES, 'UTF-8') . '</a>';
        }
        
        $body = EmailTemplate::wrap('Class ' . $action, $intro, $rows, null, null, false);
        
        // Notify Teacher
        if ($teacherEmail !== '' && filter_var($teacherEmail, FILTER_VALIDATE_EMAIL)) {
            Mailer::send($teacherEmail, $subject, $body, true);
        }
        
        // Notify Students
        foreach ($students as $student) {
            $studentEmail = trim((string) ($student['email'] ?? ''));
            if ($studentEmail !== '' && filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
                Mailer::send($studentEmail, $subject, $body, true);
            }
        }
    }
}
