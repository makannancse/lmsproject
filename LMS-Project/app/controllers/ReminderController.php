<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/Mailer.php';
require_once dirname(__DIR__) . '/config/config.php';

class ReminderController
{
    public static function sendUpcoming(): void
    {
        $token = $_GET['token'] ?? '';
        if ($token !== env('REMINDER_TOKEN', 'secret')) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT cs.*, u.name AS teacher_name, u.email AS teacher_email, u.timezone AS teacher_tz
             FROM class_sessions cs
             INNER JOIN users u ON u.id = cs.teacher_id
             WHERE cs.status = "scheduled"
               AND cs.start_datetime BETWEEN UTC_TIMESTAMP() AND DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR)'
        );
        $stmt->execute();
        $classes = $stmt->fetchAll() ?: [];

        foreach ($classes as $cls) {
            $startUtc = $cls['start_datetime'];

            // Teacher reminder
            if (!empty($cls['teacher_email'])) {
                $startTeacher = (new DateTimeImmutable($startUtc, new DateTimeZone('UTC')))
                    ->setTimezone(new DateTimeZone($cls['teacher_tz'] ?? APP_TIMEZONE))
                    ->format('Y-m-d H:i');
                Mailer::send(
                    $cls['teacher_email'],
                    'Reminder: upcoming class in 1 hour',
                    "Hi {$cls['teacher_name']},\n\n"
                    . "Your class \"{$cls['title']}\" starts at {$startTeacher}.\n\n"
                );
            }

            // Student reminders
            $stuStmt = $pdo->prepare(
                'SELECT u.name, u.email, u.timezone
                 FROM enrollments e
                 INNER JOIN users u ON u.id = e.student_id
                 WHERE e.class_id = :cid AND e.status = "active"'
            );
            $stuStmt->execute(['cid' => $cls['id']]);
            foreach ($stuStmt->fetchAll() as $stu) {
                if (empty($stu['email'])) {
                    continue;
                }
                $startStu = (new DateTimeImmutable($startUtc, new DateTimeZone('UTC')))
                    ->setTimezone(new DateTimeZone($stu['timezone'] ?? APP_TIMEZONE))
                    ->format('Y-m-d H:i');
                Mailer::send(
                    $stu['email'],
                    'Reminder: upcoming class in 1 hour',
                    "Hi {$stu['name']},\n\n"
                    . "Your class \"{$cls['title']}\" starts at {$startStu}.\n\n"
                );
            }
        }

        echo 'OK';
    }
}

