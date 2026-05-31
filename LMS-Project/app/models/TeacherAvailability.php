<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

class TeacherAvailability
{
    public static function forTeacher(int $teacherId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT * FROM teacher_availability 
             WHERE teacher_id = :teacher_id 
             ORDER BY weekday, start_time'
        );
        $stmt->execute(['teacher_id' => $teacherId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function saveForTeacher(int $teacherId, array $slots): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $delete = $pdo->prepare('DELETE FROM teacher_availability WHERE teacher_id = :teacher_id');
            $delete->execute(['teacher_id' => $teacherId]);

            if (!empty($slots)) {
                $insert = $pdo->prepare(
                    'INSERT INTO teacher_availability 
                        (teacher_id, weekday, start_time, end_time, timezone, active)
                     VALUES (:teacher_id, :weekday, :start_time, :end_time, :timezone, 1)'
                );
                foreach ($slots as $slot) {
                    $insert->execute([
                        'teacher_id' => $teacherId,
                        'weekday' => (int) $slot['weekday'],
                        'start_time' => $slot['start_time'],
                        'end_time' => $slot['end_time'],
                        'timezone' => $slot['timezone'],
                    ]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

